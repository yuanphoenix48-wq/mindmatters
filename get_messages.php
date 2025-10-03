<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

if (!isset($_GET['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'User ID not provided']);
    exit();
}

// Include database connection
require_once 'connect.php';

$userId = $_SESSION['user_id'];
$otherUserId = $_GET['user_id'];

// Get user info
$sql = "SELECT first_name, last_name, profile_picture, gender FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $otherUserId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit();
}

// Get messages
$sql = "SELECT * FROM messages 
        WHERE (sender_id = ? AND receiver_id = ?) 
        OR (sender_id = ? AND receiver_id = ?)
        ORDER BY created_at ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $userId, $otherUserId, $otherUserId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$messages = $result->fetch_all(MYSQLI_ASSOC);

$defaultProfile = ($user['gender'] === 'female') ? 'images/profile/default_images/female_gender.png' : 'images/profile/default_images/male_gender.png';

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'messages' => $messages,
    'user_name' => $user['first_name'] . ' ' . $user['last_name'],
    'user_avatar' => $user['profile_picture'] ?? $defaultProfile
]);

$stmt->close();
$conn->close();
?> 