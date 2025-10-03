<?php
	session_start();
	if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'therapist') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['appointment_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing appointment ID']);
    exit();
}

$appointmentId = (int)$_GET['appointment_id'];

// Database connection
require_once 'connect.php';

// Get appointment notes for primary or accepted co-therapists
$sql = "SELECT s.notes FROM sessions s
        WHERE s.id = ? AND (
          s.therapist_id = ? OR EXISTS (
            SELECT 1 FROM session_therapists st
            WHERE st.session_id = s.id AND st.therapist_id = ? AND st.status IN ('invited','accepted')
          )
        )";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $appointmentId, $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();

if ($appointment) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'notes' => $appointment['notes']]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Appointment not found']);
}

$stmt->close();
$conn->close();
?> 