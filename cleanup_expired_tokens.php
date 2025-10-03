<?php
/**
 * Cleanup script for expired persistent tokens
 * This script should be run periodically (e.g., via cron job) to clean up expired tokens
 */

// Database connection
include 'connect.php';
require_once 'includes/TokenManager.php';

// Initialize token manager
$tokenManager = new TokenManager($conn);

// Clean up expired tokens
$result = $tokenManager->cleanupExpiredTokens();

if ($result) {
    echo "Expired tokens cleaned up successfully.\n";
} else {
    echo "Error cleaning up expired tokens.\n";
}

$conn->close();
?>

