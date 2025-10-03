<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

require_once 'connect.php';

// Verify admin role
$userId = $_SESSION['user_id'];
$sql = "SELECT role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
if ($admin['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Validate input
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Missing user ID']);
    exit();
}
if ($id == $userId) {
    echo json_encode(['success' => false, 'error' => 'You cannot delete yourself.']);
    exit();
}
// Prevent deleting admins
$sql = "SELECT role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if (!$user || $user['role'] === 'admin') {
    echo json_encode(['success' => false, 'error' => 'Cannot delete this user.']);
    exit();
}
// Delete user
$sql = "DELETE FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database delete failed']);
}
$stmt->close();
$conn->close(); 