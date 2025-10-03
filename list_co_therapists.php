<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit(); }
require_once 'connect.php';

$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
if (!$sessionId) { echo json_encode(['success'=>false,'error'=>'Missing session_id']); exit(); }

$sql = "SELECT st.therapist_id, st.status, u.first_name, u.last_name
        FROM session_therapists st
        JOIN users u ON u.id = st.therapist_id
        WHERE st.session_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $sessionId);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
$stmt->close();
$conn->close();
echo json_encode(['success'=>true,'co_therapists'=>$rows]);
?>


