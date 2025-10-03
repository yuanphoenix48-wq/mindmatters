<?php
session_start();

// Allow CORS for all domains
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Database connection
include 'connect.php';
require_once 'includes/ForgotPassword.php';

// Get the action from POST data
$action = $_POST['action'] ?? '';

// Initialize ForgotPassword class
$forgotPassword = new ForgotPassword($conn);

switch ($action) {
    case 'send_code':
        handleSendCode($forgotPassword);
        break;
        
    case 'verify_code':
        handleVerifyCode($forgotPassword);
        break;
        
    case 'reset_password':
        handleResetPassword($forgotPassword);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function handleSendCode($forgotPassword) {
    $email = $_POST['email'] ?? '';
    
    // Validate input
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email address is required']);
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
        return;
    }
    
    // Send reset code
    $result = $forgotPassword->sendResetCode($email);
    echo json_encode($result);
}

function handleVerifyCode($forgotPassword) {
    $email = $_POST['email'] ?? '';
    $code = $_POST['code'] ?? '';
    
    // Validate input
    if (empty($email) || empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Email and verification code are required']);
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
        return;
    }
    
    if (!preg_match('/^\d{6}$/', $code)) {
        echo json_encode(['success' => false, 'message' => 'Verification code must be 6 digits']);
        return;
    }
    
    // Verify code
    $result = $forgotPassword->verifyResetCode($email, $code);
    echo json_encode($result);
}

function handleResetPassword($forgotPassword) {
    $email = $_POST['email'] ?? '';
    $code = $_POST['code'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate input
    if (empty($email) || empty($code) || empty($newPassword) || empty($confirmPassword)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
        return;
    }
    
    if (!preg_match('/^\d{6}$/', $code)) {
        echo json_encode(['success' => false, 'message' => 'Verification code must be 6 digits']);
        return;
    }
    
    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        return;
    }
    
    if (strlen($newPassword) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
        return;
    }
    
    // Additional password strength validation
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $newPassword)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number']);
        return;
    }
    
    // Reset password
    $result = $forgotPassword->resetPassword($email, $code, $newPassword);
    echo json_encode($result);
}

$conn->close();
?>
