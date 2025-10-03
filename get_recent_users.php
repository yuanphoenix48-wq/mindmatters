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

$sql = "SELECT id, first_name, last_name, role, created_at
        FROM users
        WHERE role != 'admin'
        ORDER BY created_at DESC
        LIMIT 10";
$res = $conn->query($sql);
$users = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $row['created_at'] = date('M j, Y', strtotime($row['created_at']));
        $users[] = $row;
    }
}

echo json_encode(['success' => true, 'users' => $users]);
$conn->close();
?>























































