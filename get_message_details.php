<?php
session_start();
if (!isset($_SESSION['user_id'])) { 
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']); 
    exit(); 
}

require_once 'connect.php';

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if (!$user || $user['role'] !== 'admin') { 
    echo json_encode(['success' => false, 'error' => 'Admin access required']); 
    exit(); 
}

// Get message ID from request
$messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($messageId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid message ID']);
    exit();
}

try {
    // Fetch message details from database
    $sql = "SELECT id, name, email, message, status, created_at, updated_at, ip_address, user_agent 
            FROM contact_messages 
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $messageId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($message = $result->fetch_assoc()) {
        // Return message details
        echo json_encode([
            'success' => true,
            'message' => $message
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Message not found'
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>

