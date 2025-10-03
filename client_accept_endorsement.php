<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit(); }
require_once 'connect.php';

$payload = json_decode(file_get_contents('php://input'), true);
$sessionId = (int)($payload['session_id'] ?? 0);
$userId = $_SESSION['user_id'];

if (!$sessionId) { echo json_encode(['success'=>false,'error'=>'Missing session_id']); exit(); }

// Load endorsed therapist
$stmt = $conn->prepare("SELECT endorsed_therapist_id, endorse_proposed_date, endorse_proposed_time FROM sessions WHERE id = ? AND client_id = ?");
$stmt->bind_param('ii', $sessionId, $userId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
if (!$row || empty($row['endorsed_therapist_id'])) { echo json_encode(['success'=>false,'error'=>'No endorsed therapist']); exit(); }

// Assign and mark pending for new therapist to accept, clear any proposals
$hasEndPropDate = $conn->query("SHOW COLUMNS FROM sessions LIKE 'endorse_proposed_date'");
$hasEndPropTime = $conn->query("SHOW COLUMNS FROM sessions LIKE 'endorse_proposed_time'");

// Build update including optional schedule switch to the endorsement proposed date/time
$setParts = [
  "therapist_id = ?",
  "status = 'pending'",
  "therapist_unavailable = 0",
  "proposed_date = NULL",
  "proposed_time = NULL",
  "endorsed_therapist_id = NULL"
];

// If endorsement proposed date/time columns exist AND values are present, set official schedule
$useEndorseSchedule = false;
if (($hasEndPropDate && $hasEndPropDate->num_rows > 0) && ($hasEndPropTime && $hasEndPropTime->num_rows > 0) && !empty($row['endorse_proposed_date']) && !empty($row['endorse_proposed_time'])) {
  $setParts[] = "session_date = '".$conn->real_escape_string($row['endorse_proposed_date'])."'";
  $setParts[] = "session_time = '".$conn->real_escape_string($row['endorse_proposed_time'])."'";
  $setParts[] = "endorse_proposed_date = NULL";
  $setParts[] = "endorse_proposed_time = NULL";
  $useEndorseSchedule = true;
} else if (($hasEndPropDate && $hasEndPropDate->num_rows > 0) && ($hasEndPropTime && $hasEndPropTime->num_rows > 0)) {
  // Clear endorsement proposals even if empty values
  $setParts[] = "endorse_proposed_date = NULL";
  $setParts[] = "endorse_proposed_time = NULL";
}

$sql = "UPDATE sessions SET ".implode(', ', $setParts)." WHERE id = ? AND client_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('iii', $row['endorsed_therapist_id'], $sessionId, $userId);
$ok = $stmt->execute();
$stmt->close();

// Clean up notes metadata lines
if ($ok) {
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


