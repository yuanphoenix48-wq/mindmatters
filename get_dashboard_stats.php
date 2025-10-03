<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['success' => false, 'error' => 'Not logged in']); exit(); }

require_once 'connect.php';

// Verify admin role
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$me = $result->fetch_assoc();
if (!$me || $me['role'] !== 'admin') { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit(); }
$stmt->close();

// Fetch stats
$sql = "SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as total_students,
            SUM(CASE WHEN role = 'doctor' THEN 1 ELSE 0 END) as total_doctors
        FROM users 
        WHERE role != 'admin'";
$res = $conn->query($sql);
$stats = $res ? $res->fetch_assoc() : ['total_users'=>0,'total_students'=>0,'total_doctors'=>0];
$stats['active_users'] = $stats['total_users'];

echo json_encode(['success' => true, 'stats' => $stats]);

$conn->close();
?>























































