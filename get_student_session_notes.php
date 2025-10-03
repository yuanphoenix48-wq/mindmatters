<?php
	session_start();
	if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['session_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing session ID']);
    exit();
}

$sessionId = $_GET['session_id'];
$clientId = $_SESSION['user_id'];

// Database connection
require_once 'connect.php';

// Get session notes for the client
$sql = "SELECT notes FROM sessions WHERE id = ? AND client_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $sessionId, $clientId);
$stmt->execute();
$result = $stmt->get_result();
$session = $result->fetch_assoc();

if ($session) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'notes' => $session['notes']]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Session not found or does not belong to client']);
}

$stmt->close();
$conn->close();
?> 