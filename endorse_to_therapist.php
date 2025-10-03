<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'therapist') { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit(); }
require_once 'connect.php';

$payload = json_decode(file_get_contents('php://input'), true);
$sessionId = (int)($payload['session_id'] ?? 0);
$newTherapistId = (int)($payload['therapist_id'] ?? 0);
$proposedDate = $payload['proposed_date'] ?? null;
$proposedTime = $payload['proposed_time'] ?? null;
$therapistId = $_SESSION['user_id'];

if (!$sessionId || !$newTherapistId) { echo json_encode(['success'=>false,'error'=>'Missing fields']); exit(); }

// Verify session belongs to current therapist
$stmt = $conn->prepare("SELECT id FROM sessions WHERE id = ? AND therapist_id = ? AND status IN ('scheduled','pending')");
$stmt->bind_param('ii', $sessionId, $therapistId);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) { echo json_encode(['success'=>false,'error'=>'Session not found']); exit(); }
$stmt->close();

// If a specific date/time is proposed for the endorsed therapist, validate it is available
if ($proposedDate && $proposedTime && $newTherapistId) {
  // Optional: verify proposed time is within the endorsed therapist's availability
  try {
    $weekday = strtolower(date('l', strtotime($proposedDate)));
    $stmt = $conn->prepare("SELECT 1 FROM doctor_availability WHERE therapist_id = ? AND day_of_week = ? AND is_available = 1 AND ? BETWEEN start_time AND end_time");
    $stmt->bind_param('iss', $newTherapistId, $weekday, $proposedTime);
    $stmt->execute();
    $ok = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if (!$ok) { echo json_encode(['success'=>false,'error'=>'Selected time is outside the endorsed therapist\'s availability']); exit(); }
  } catch (Throwable $e) { /* ignore availability check failure, continue to conflict check */ }

  // Hard conflict check: endorsed therapist already booked at that date/time
  $stmt = $conn->prepare("SELECT 1 FROM sessions WHERE therapist_id = ? AND session_date = ? AND session_time = ? AND status IN ('pending','scheduled','completed') LIMIT 1");
  $stmt->bind_param('iss', $newTherapistId, $proposedDate, $proposedTime);
  $stmt->execute();
  $hasConflict = $stmt->get_result()->num_rows > 0;
  $stmt->close();
  if ($hasConflict) { echo json_encode(['success'=>false,'error'=>'Selected time is already booked for the endorsed therapist']); exit(); }
}

// Mark endorsement (and store proposed date/time if columns exist)
$hasEndCol = $conn->query("SHOW COLUMNS FROM sessions LIKE 'endorsed_therapist_id'");
$hasEndPropDate = $conn->query("SHOW COLUMNS FROM sessions LIKE 'endorse_proposed_date'");
$hasEndPropTime = $conn->query("SHOW COLUMNS FROM sessions LIKE 'endorse_proposed_time'");

if ($hasEndCol && $hasEndCol->num_rows > 0) {
  if ($hasEndPropDate && $hasEndPropDate->num_rows > 0 && $hasEndPropTime && $hasEndPropTime->num_rows > 0) {
    // Save endorsement with its own proposed date/time columns (does NOT overwrite reschedule proposal)
    $stmt = $conn->prepare("UPDATE sessions SET endorsed_therapist_id = ?, endorse_proposed_date = ?, endorse_proposed_time = ? WHERE id = ?");
    $stmt->bind_param('issi', $newTherapistId, $proposedDate, $proposedTime, $sessionId);
  } else {
    // Save endorsement only, keep proposed_date/time (reschedule) intact
    $stmt = $conn->prepare("UPDATE sessions SET endorsed_therapist_id = ? WHERE id = ?");
    $stmt->bind_param('ii', $newTherapistId, $sessionId);
    // Append proposed endorsement timing to notes so it can still be shown
    if ($proposedDate && $proposedTime) {
      $append = "\n[Endorsement Proposed] ".$proposedDate." ".$proposedTime;
      $stmt2 = $conn->prepare("UPDATE sessions SET notes = CONCAT(IFNULL(notes,''), ? ) WHERE id = ?");
      $stmt2->bind_param('si', $append, $sessionId);
      $stmt2->execute();
      $stmt2->close();
    }
  }
} else {
  // Fallback entirely to notes
  $append = "\n[Endorsement] Suggested therapist ID: ".$newTherapistId;
  if ($proposedDate && $proposedTime) { $append .= " | [Endorsement Proposed] ".$proposedDate." ".$proposedTime; }
  $stmt = $conn->prepare("UPDATE sessions SET notes = CONCAT(IFNULL(notes,''), ? ) WHERE id = ?");
  $stmt->bind_param('si', $append, $sessionId);
}

if ($stmt->execute()) { echo json_encode(['success'=>true]); } else { echo json_encode(['success'=>false,'error'=>'Failed to update']); }
$stmt->close();
$conn->close();
?>


