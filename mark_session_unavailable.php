<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'therapist') { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit(); }
require_once 'connect.php';

$payload = json_decode(file_get_contents('php://input'), true);
$sessionId = (int)($payload['session_id'] ?? 0);
$newDate = $payload['proposed_date'] ?? '';
$newTime = $payload['proposed_time'] ?? '';
$therapistId = $_SESSION['user_id'];

if (!$sessionId) { echo json_encode(['success'=>false,'error'=>'Missing session_id']); exit(); }

// Verify session belongs to this therapist and is scheduled
$stmt = $conn->prepare("SELECT id FROM sessions WHERE id = ? AND therapist_id = ? AND status = 'scheduled'");
$stmt->bind_param('ii', $sessionId, $therapistId);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) { echo json_encode(['success'=>false,'error'=>'Session not found']); exit(); }
$stmt->close();

// Store unavailability and proposed reschedule
// Add columns if they exist; otherwise append to notes
$hasCols = [ 'therapist_unavailable' => false, 'proposed_date' => false, 'proposed_time' => false ];
foreach ($hasCols as $col => $_) {
  $r = $conn->query("SHOW COLUMNS FROM sessions LIKE '".$conn->real_escape_string($col)."'");
  $hasCols[$col] = $r && $r->num_rows > 0;
}

if ($hasCols['therapist_unavailable'] && $hasCols['proposed_date'] && $hasCols['proposed_time']) {
  $stmt = $conn->prepare("UPDATE sessions SET therapist_unavailable = 1, proposed_date = ?, proposed_time = ? WHERE id = ?");
  $stmt->bind_param('ssi', $newDate, $newTime, $sessionId);
} else {
  $append = "\n[Therapist Unavailable] Proposed: ".$newDate." ".$newTime;
  $stmt = $conn->prepare("UPDATE sessions SET notes = CONCAT(IFNULL(notes,''), ? ) WHERE id = ?");
  $stmt->bind_param('si', $append, $sessionId);
}

if ($stmt->execute()) { echo json_encode(['success'=>true]); } else { echo json_encode(['success'=>false,'error'=>'Failed to update']); }
$stmt->close();
$conn->close();
?>




