<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit(); }
require_once 'connect.php';
$me = (int)$_SESSION['user_id'];
$other = isset($_POST['other_id']) ? (int)$_POST['other_id'] : ((int)($_GET['other_id'] ?? 0));
if (!$other) { echo json_encode(['success'=>false,'error'=>'Missing other_id']); exit(); }

$stmt = $conn->prepare("UPDATE messages SET read_at = NOW() WHERE receiver_id = ? AND sender_id = ? AND read_at IS NULL");
$stmt->bind_param('ii', $me, $other);
$ok = $stmt->execute();
$stmt->close();
$conn->close();
echo json_encode(['success'=>$ok]);
?>


