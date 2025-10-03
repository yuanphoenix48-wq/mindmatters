<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'therapist') { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit(); }
require_once 'connect.php';

$payload = json_decode(file_get_contents('php://input'), true);
$sessionId = (int)($payload['session_id'] ?? 0);
$targetTherapistId = (int)($payload['therapist_id'] ?? 0);
$date = $payload['session_date'] ?? '';
$time = $payload['session_time'] ?? '';
$reason = trim($payload['reason'] ?? '');

if (!$sessionId || !$targetTherapistId || !$date || !$time) { echo json_encode(['success'=>false,'error'=>'Missing fields']); exit(); }

// Look up client_id from the original session and ensure current user owns it
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT client_id, therapist_id FROM sessions WHERE id = ? AND therapist_id = ?");
$stmt->bind_param('ii', $sessionId, $userId);
$stmt->execute();
$res = $stmt->get_result();
$orig = $res->fetch_assoc();
$stmt->close();
if (!$orig) { echo json_encode(['success'=>false,'error'=>'Session not found']); exit(); }
$clientId = (int)$orig['client_id'];

// Verify chosen slot is within target therapist availability and not booked
$weekday = strtolower(date('l', strtotime($date))); // monday..sunday
$stmt = $conn->prepare("SELECT 1 FROM doctor_availability WHERE therapist_id = ? AND day_of_week = ? AND is_available = 1 AND ? BETWEEN start_time AND end_time");
$stmt->bind_param('iss', $targetTherapistId, $weekday, $time);
$stmt->execute();
$ok = $stmt->get_result()->num_rows > 0;
$stmt->close();
if (!$ok) { echo json_encode(['success'=>false,'error'=>'Selected time not within target therapist availability']); exit(); }

$stmt = $conn->prepare("SELECT 1 FROM sessions WHERE therapist_id = ? AND session_date = ? AND session_time = ? AND status IN ('pending','scheduled','completed')");
$stmt->bind_param('iss', $targetTherapistId, $date, $time);
$stmt->execute();
$booked = $stmt->get_result()->num_rows > 0;
$stmt->close();
if ($booked) { echo json_encode(['success'=>false,'error'=>'Time slot already booked']); exit(); }

// Create a new pending session for the target therapist
$notes = $reason ? ("Referral reason: " . $reason) : NULL;
if ($notes) {
  $stmt = $conn->prepare("INSERT INTO sessions (client_id, therapist_id, session_date, session_time, status, notes) VALUES (?, ?, ?, ?, 'pending', ?)");
  $stmt->bind_param('iisss', $clientId, $targetTherapistId, $date, $time, $notes);
} else {
  $stmt = $conn->prepare("INSERT INTO sessions (client_id, therapist_id, session_date, session_time, status) VALUES (?, ?, ?, ?, 'pending')");
  $stmt->bind_param('iiss', $clientId, $targetTherapistId, $date, $time);
}
if ($stmt->execute()) { echo json_encode(['success'=>true]); }
else { echo json_encode(['success'=>false,'error'=>'Failed to create referral']); }
$stmt->close();
$conn->close();
?>



