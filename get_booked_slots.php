<?php
session_start();
header('Content-Type: application/json');
// Allow clients, therapists, and admins to query booked slots
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['client','therapist','admin'], true)) { echo json_encode(['success'=>false,'slots'=>[]]); exit(); }
require_once 'connect.php';

$therapistId = isset($_GET['therapist_id']) ? (int)$_GET['therapist_id'] : 0;
$date = $_GET['session_date'] ?? '';
if (!$therapistId || !$date) { echo json_encode(['success'=>true,'slots'=>[]]); exit(); }

$stmt = $conn->prepare("SELECT session_time FROM sessions WHERE therapist_id = ? AND session_date = ? AND status IN ('pending','scheduled','completed')");
$stmt->bind_param('is', $therapistId, $date);
$stmt->execute();
$res = $stmt->get_result();
$slots = [];
while ($row = $res->fetch_assoc()) { $slots[] = $row['session_time']; }
$stmt->close();
$conn->close();
echo json_encode(['success'=>true,'slots'=>$slots]);
?>




