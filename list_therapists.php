<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit(); }
require_once 'connect.php';

$excludeId = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : 0;
if ($excludeId > 0) {
  $stmt = $conn->prepare("SELECT id, first_name, last_name FROM users WHERE role = 'therapist' AND id <> ? ORDER BY first_name, last_name");
  $stmt->bind_param('i', $excludeId);
} else {
  $stmt = $conn->prepare("SELECT id, first_name, last_name FROM users WHERE role = 'therapist' ORDER BY first_name, last_name");
}
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
$stmt->close();
$conn->close();
echo json_encode(['success'=>true,'therapists'=>$rows]);
?>


