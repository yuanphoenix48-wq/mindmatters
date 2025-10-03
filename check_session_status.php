<?php
session_start();

// Allow CORS for all domains
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['valid' => false, 'error' => 'Not logged in']);
    exit();
}

// Check if session has timed out
$currentTime = time();
$sessionTimeout = $_SESSION['timeout'] ?? ($currentTime + (30 * 60)); // Default 30 minutes

if ($currentTime > $sessionTimeout) {
    // Session has expired
    session_destroy();
    echo json_encode(['valid' => false, 'error' => 'Session expired']);
    exit();
}

// Session is valid
echo json_encode(['valid' => true, 'user_id' => $_SESSION['user_id'], 'role' => $_SESSION['role']]);
?>

