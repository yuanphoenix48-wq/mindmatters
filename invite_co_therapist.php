<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'therapist') { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit(); }
require_once 'connect.php';

$payload = json_decode(file_get_contents('php://input'), true);
$sessionId = (int)($payload['session_id'] ?? 0);
$therapistId = (int)($payload['therapist_id'] ?? 0);
if (!$sessionId || !$therapistId) { echo json_encode(['success'=>false,'error'=>'Missing fields']); exit(); }

// Verify ownership of session
$ownerId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id FROM sessions WHERE id = ? AND therapist_id = ?");
$stmt->bind_param('ii', $sessionId, $ownerId);
$stmt->execute();
$own = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$own) { echo json_encode(['success'=>false,'error'=>'Not session owner']); exit(); }

// Target join table
$stTable = 'session_therapists';

// Insert or ignore if existing
$sql = "INSERT INTO {$stTable} (session_id, therapist_id, status) VALUES (?, ?, 'invited') ON DUPLICATE KEY UPDATE status = VALUES(status)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
  echo json_encode(['success'=>false,'error'=>'DB prepare failed']);
  $conn->close();
  exit();
}
$stmt->bind_param('ii', $sessionId, $therapistId);
$ok = $stmt->execute();
$stmt->close();

// Optionally append reason to session notes so co-therapist can read it in Pending Requests
if ($ok && !empty($payload['reason'])) {
  $reason = trim($payload['reason']);
  $stmt = $conn->prepare("SELECT notes FROM sessions WHERE id = ?");
  $stmt->bind_param('i', $sessionId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $existing = isset($row['notes']) ? (string)$row['notes'] : '';
  $append = (strlen($existing) ? "\n" : '') . 'Co-therapist Reason: ' . $reason;
  $stmt = $conn->prepare("UPDATE sessions SET notes = CONCAT(IFNULL(notes,''), ?) WHERE id = ?");
  $stmt->bind_param('si', $append, $sessionId);
  $stmt->execute();
  $stmt->close();
}

// If inserted/updated successfully, send email notification to invited therapist
if ($ok) {
  // Fetch invited therapist info (email, name)
  $stmt = $conn->prepare("SELECT email, first_name, last_name FROM users WHERE id = ? AND role = 'therapist'");
  $stmt->bind_param('i', $therapistId);
  $stmt->execute();
  $invitee = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  // Fetch session details (client, date, time) and primary therapist name
  $stmt = $conn->prepare("SELECT s.session_date, s.session_time, u.first_name AS client_first, u.last_name AS client_last, pt.first_name AS t_first, pt.last_name AS t_last FROM sessions s JOIN users u ON u.id = s.client_id JOIN users pt ON pt.id = s.therapist_id WHERE s.id = ?");
  $stmt->bind_param('i', $sessionId);
  $stmt->execute();
  $sess = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($invitee && $sess) {
    require_once __DIR__ . '/includes/EmailNotifications.php';
    $mailer = new EmailNotifications();
    $inviteeEmail = $invitee['email'];
    $inviteeName = trim(($invitee['first_name'] ?? '') . ' ' . ($invitee['last_name'] ?? ''));
    $primaryName = trim(($sess['t_first'] ?? '') . ' ' . ($sess['t_last'] ?? ''));
    $clientName = trim(($sess['client_first'] ?? '') . ' ' . ($sess['client_last'] ?? ''));
    $sessionDate = $sess['session_date'] ?? '';
    $sessionTime = $sess['session_time'] ?? '';
    $reason = isset($payload['reason']) ? trim($payload['reason']) : '';
    try { $mailer->sendCoTherapistInvite($inviteeEmail, $inviteeName, $primaryName, $clientName, $sessionDate, $sessionTime, $sessionId, $reason); } catch (Throwable $e) { error_log('Co-therapist invite mail failed: ' . $e->getMessage()); }
  }
}

echo json_encode(['success'=> $ok]);
$conn->close();
?>


