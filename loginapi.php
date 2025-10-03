<?php
session_start();

// Allow CORS for all domains (you can restrict it to specific domains later)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Simple rate limiting - check if too many login attempts
if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= 10) {
    $lastAttempt = $_SESSION['last_login_attempt'] ?? 0;
    if (time() - $lastAttempt < 300) { // 5 minutes lockout
        echo json_encode(['error' => 'Too many login attempts. Please try again in 5 minutes.']);
        exit();
    } else {
        // Reset attempts after lockout period
        unset($_SESSION['login_attempts']);
        unset($_SESSION['last_login_attempt']);
    }
}

// Database connection
include 'connect.php';
require_once 'includes/TokenManager.php';

// Get user input
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// Validate input
if (empty($email) || empty($password)) {
    echo json_encode(['error' => 'Email and password are required']);
    exit();
}

// Prepare and execute query - automatically detect role from database
$sql = "SELECT * FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
    exit();
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    // Check if user has a valid role
    if (empty($user['role']) || !in_array($user['role'], ['client', 'therapist', 'admin'])) {
        echo json_encode(['error' => 'Invalid user account']);
        exit();
    }

    // Enforce Fatima domain for client accounts
    if ($user['role'] === 'client') {
        $allowedDomain = '@student.fatima.edu.ph';
        if (strtolower(substr($user['email'], -strlen($allowedDomain))) !== $allowedDomain) {
            // Treat as not found to avoid leaking info
            $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
            $_SESSION['last_login_attempt'] = time();
            echo json_encode(['error' => 'Email not found']);
            exit();
        }
        
        // Check if email is verified for clients
        if (!$user['email_verified']) {
            echo json_encode(['error' => 'Please verify your email address before logging in. Check your inbox for the verification link.']);
            exit();
        }
    }
    
    // Require email verification for therapists as well
    if ($user['role'] === 'therapist') {
        if (!$user['email_verified']) {
            echo json_encode(['error' => 'Please verify your email address before logging in. Check your inbox for the verification link.']);
            exit();
        }
    }
    
    if (password_verify($password, $user['password'])) {
        // Store user information in session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['role'] = $user['role'];
        
        // Set session timeout for 30 minutes of inactivity
        $_SESSION['timeout'] = time() + (30 * 60);
        $_SESSION['last_activity'] = time() * 1000;
        
        // Generate persistent token for auto-login
        $tokenManager = new TokenManager($conn);
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $persistentToken = $tokenManager->createToken($user['id'], $userAgent, $ipAddress);
        
        // Clean up expired tokens periodically
        $tokenManager->cleanupExpiredTokens();
        
        // Reset login attempts on successful login
        unset($_SESSION['login_attempts']);
        unset($_SESSION['last_login_attempt']);
        
        echo json_encode([
            'success' => true, 
            'role' => $user['role'],
            'persistent_token' => $persistentToken
        ]);
    } else {
        // Track failed login attempt
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        $_SESSION['last_login_attempt'] = time();
        
        echo json_encode(['error' => 'Incorrect password']);
    }
} else {
    // Track failed login attempt
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['last_login_attempt'] = time();
    
    echo json_encode(['error' => 'Email not found']);
}

$stmt->close();
$conn->close();
?>
