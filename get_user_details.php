<?php
session_start();
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

// Get user ID from request
if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'No user ID provided']);
    exit();
}

$requestedUserId = intval($_GET['id']);

// Fetch user details
$sql = "SELECT id, first_name, last_name, email, role, user_id, gender, section, created_at 
        FROM users 
        WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $requestedUserId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    echo json_encode(['success' => true, 'user' => $user]);
} else {
    echo json_encode(['success' => false, 'error' => 'User not found']);
}

$stmt->close();
$conn->close();
?> 