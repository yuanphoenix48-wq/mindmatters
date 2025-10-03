<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit(); }
require_once 'connect.php';

$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
$clientId = $_SESSION['user_id'];
if (!$sessionId) { echo json_encode(['success'=>false,'error'=>'Missing session_id']); exit(); }

$sql = "SELECT s.id, s.client_id, s.proposed_date, s.proposed_time, s.endorsed_therapist_id,
               s.endorse_proposed_date, s.endorse_proposed_time, s.notes,
               et.first_name AS et_first, et.last_name AS et_last
        FROM sessions s
        LEFT JOIN users et ON et.id = s.endorsed_therapist_id
        WHERE s.id = ? AND s.client_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $sessionId, $clientId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) { echo json_encode(['success'=>false,'error'=>'Session not found']); exit(); }

$proposedDate = $row['proposed_date'] ?? null;
$proposedTime = $row['proposed_time'] ?? null;
$endorsedId = $row['endorsed_therapist_id'] ?? null;
$endPropDate = $row['endorse_proposed_date'] ?? null;
$endPropTime = $row['endorse_proposed_time'] ?? null;
$endorsedName = null;
if ($row['et_first']) {
    $endorsedName = $row['et_first'] . ' ' . $row['et_last'];
}

// Fallback: try parse notes if columns absent
if ((!$proposedDate || !$proposedTime || !$endorsedName) && !empty($row['notes'])) {
    if (!$proposedDate || !$proposedTime) {
        if (preg_match('/Proposed:\s*(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}:\d{2})/i', $row['notes'], $m)) {
            $proposedDate = $proposedDate ?: $m[1];
            $proposedTime = $proposedTime ?: $m[2];
        }
    }
    if ((!$endPropDate || !$endPropTime) && !empty($row['notes'])) {
        if (preg_match('/\[Endorsement Proposed\]\s*(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}:\d{2})/i', $row['notes'], $m2)) {
            $endPropDate = $endPropDate ?: $m2[1];
            $endPropTime = $endPropTime ?: $m2[2];
        }
    }
    if (!$endorsedName && preg_match('/Suggested therapist ID:\s*(\d+)/i', $row['notes'], $m)) {
        $tid = (int)$m[1];
        $q = $conn->prepare('SELECT first_name, last_name FROM users WHERE id = ?');
        $q->bind_param('i', $tid);
        $q->execute();
        $r = $q->get_result()->fetch_assoc();
        if ($r) { $endorsedName = $r['first_name'].' '.$r['last_name']; $endorsedId = $tid; }
        $q->close();
    }
}

echo json_encode([
    'success' => true,
    'proposed_date' => $proposedDate,
    'proposed_time' => $proposedTime,
    'endorsed_therapist_id' => $endorsedId,
    'endorsed_therapist_name' => $endorsedName,
    'endorse_proposed_date' => $endPropDate,
    'endorse_proposed_time' => $endPropTime
]);

$conn->close();
?>


