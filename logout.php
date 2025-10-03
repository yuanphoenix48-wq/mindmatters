<?php
session_start();

// Check if logout is due to inactivity
$inactivityLogout = isset($_GET['reason']) && $_GET['reason'] === 'inactivity';

// Clean up persistent tokens if user was logged in
if (isset($_SESSION['user_id'])) {
    include 'connect.php';
    require_once 'includes/TokenManager.php';
    
    $tokenManager = new TokenManager($conn);
    
    // Get the persistent token from cookie
    $persistentToken = $_COOKIE['persistent_token'] ?? '';
    
    if (!empty($persistentToken)) {
        // Revoke the specific token
        $tokenManager->revokeToken($persistentToken);
    }
    
    // Also revoke all user tokens for security (optional - can be removed if you want to keep other device sessions)
    // $tokenManager->revokeAllUserTokens($_SESSION['user_id']);
    
    $conn->close();
}

// Clear the persistent token cookie
setcookie('persistent_token', '', time() - 3600, '/');

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page with appropriate message
if ($inactivityLogout) {
    header('Location: index.php?session_expired=1');
} else {
    header('Location: index.php');
}
exit();
?> 