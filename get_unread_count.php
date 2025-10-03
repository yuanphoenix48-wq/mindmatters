<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit(); }
require_once 'connect.php';
$uid = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM messages WHERE receiver_id = ? AND read_at IS NULL");
$stmt->bind_param('i', $uid);
$stmt->execute();
$cnt = ($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt->close();
$conn->close();
echo json_encode(['success'=>true,'count'=>$cnt]);
?>


