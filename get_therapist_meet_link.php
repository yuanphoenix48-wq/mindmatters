<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once 'connect.php';

$therapistId = $_GET['therapist_id'] ?? '';

if (empty($therapistId)) {
    echo json_encode(['success' => false, 'error' => 'Therapist ID is required']);
    exit();
}

// Get the therapist's meet link from their profile
$sql = "SELECT default_meet_link FROM users WHERE id = ? AND role = 'therapist'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $therapistId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $therapist = $result->fetch_assoc();
    echo json_encode([
        'success' => true, 
        'meet_link' => $therapist['default_meet_link'] ?? ''
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Therapist not found']);
}

$stmt->close();
$conn->close();
?>

