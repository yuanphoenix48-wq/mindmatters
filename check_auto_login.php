<?php
session_start();

// Allow CORS for all domains
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Database connection
include 'connect.php';
require_once 'includes/TokenManager.php';

// Get the persistent token from request
$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? '';

if (empty($token)) {
    echo json_encode(['valid' => false, 'error' => 'No token provided']);
    exit();
}

// Initialize token manager
$tokenManager = new TokenManager($conn);

// Validate the token
$userData = $tokenManager->validateToken($token);

if ($userData) {
    // Token is valid, create session
    $_SESSION['user_id'] = $userData['id'];
    $_SESSION['user_email'] = $userData['email'];
    $_SESSION['first_name'] = $userData['first_name'];
    $_SESSION['last_name'] = $userData['last_name'];
    $_SESSION['role'] = $userData['role'];
    
    // Set session timeout for 30 minutes of inactivity
    $_SESSION['timeout'] = time() + (30 * 60);
    $_SESSION['last_activity'] = time() * 1000;
    
    echo json_encode([
        'valid' => true,
        'user_id' => $userData['id'],
        'role' => $userData['role'],
        'redirect_url' => $userData['role'] === 'admin' ? 'admin_dashboard.php' : 'dashboard.php'
    ]);
} else {
    // Token is invalid or expired
    echo json_encode(['valid' => false, 'error' => 'Invalid or expired token']);
}

$conn->close();
?>

