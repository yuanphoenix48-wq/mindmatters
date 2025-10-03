<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit(); }
require_once 'connect.php';

$payload = json_decode(file_get_contents('php://input'), true);
$sessionId = (int)($payload['session_id'] ?? 0);
$userId = $_SESSION['user_id'];

if (!$sessionId) { echo json_encode(['success'=>false,'error'=>'Missing session_id']); exit(); }

// Load proposed values
$stmt = $conn->prepare("SELECT proposed_date, proposed_time FROM sessions WHERE id = ? AND client_id = ?");
$stmt->bind_param('ii', $sessionId, $userId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
if (!$row || empty($row['proposed_date']) || empty($row['proposed_time'])) { echo json_encode(['success'=>false,'error'=>'No proposed schedule']); exit(); }

// Apply reschedule
$hasEndPropDate = $conn->query("SHOW COLUMNS FROM sessions LIKE 'endorse_proposed_date'");
$hasEndPropTime = $conn->query("SHOW COLUMNS FROM sessions LIKE 'endorse_proposed_time'");
$hasEndorsedCol = $conn->query("SHOW COLUMNS FROM sessions LIKE 'endorsed_therapist_id'");

if (($hasEndPropDate && $hasEndPropDate->num_rows > 0) && ($hasEndPropTime && $hasEndPropTime->num_rows > 0) && ($hasEndorsedCol && $hasEndorsedCol->num_rows > 0)) {
  $stmt = $conn->prepare("UPDATE sessions SET session_date = ?, session_time = ?, therapist_unavailable = 0, proposed_date = NULL, proposed_time = NULL, endorsed_therapist_id = NULL, endorse_proposed_date = NULL, endorse_proposed_time = NULL WHERE id = ? AND client_id = ?");
  $stmt->bind_param('ssii', $row['proposed_date'], $row['proposed_time'], $sessionId, $userId);
} else {
  $stmt = $conn->prepare("UPDATE sessions SET session_date = ?, session_time = ?, therapist_unavailable = 0, proposed_date = NULL, proposed_time = NULL WHERE id = ? AND client_id = ?");
  $stmt->bind_param('ssii', $row['proposed_date'], $row['proposed_time'], $sessionId, $userId);
}
if ($stmt->execute()) {
  // Clean up notes metadata lines
  $stmt->close();
  $stmt = $conn->prepare("SELECT notes FROM sessions WHERE id = ?");
  $stmt->bind_param('i', $sessionId);
  $stmt->execute();
  $notesRes = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if ($notesRes) {
    $lines = preg_split("/\r?\n/", (string)$notesRes['notes']);
    $filtered = [];
    foreach ($lines as $ln) { if (strlen(trim($ln)) && strpos(trim($ln), '[') === 0) continue; $filtered[] = $ln; }
    $newNotes = trim(implode("\n", $filtered));
    $stmt = $conn->prepare("UPDATE sessions SET notes = ? WHERE id = ?");
    $stmt->bind_param('si', $newNotes, $sessionId);
    $stmt->execute();
    $stmt->close();
  }
  echo json_encode(['success'=>true]);
} else {
  echo json_encode(['success'=>false,'error'=>'Failed to update']);
}
$conn->close();
?>


