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

// Get therapist notes for the specific session
$sql = "SELECT dn.*, CONCAT(u.first_name, ' ', u.last_name) as therapist_name, s.session_date, s.session_time
        FROM doctor_notes dn
        JOIN sessions s ON dn.session_id = s.id
        JOIN users u ON dn.therapist_id = u.id
        WHERE dn.session_id = ? AND dn.client_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $sessionId, $clientId);
$stmt->execute();
$result = $stmt->get_result();
$therapistNotes = $result->fetch_assoc();

if ($therapistNotes) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'notes' => $therapistNotes['notes'],
        'diagnosis' => $therapistNotes['diagnosis'],
        'treatment_plan' => $therapistNotes['treatment_plan'],
        'progress_status' => $therapistNotes['progress_status'],
        'next_session_recommendations' => $therapistNotes['next_session_recommendations'],
        'therapist_name' => $therapistNotes['therapist_name'],
        'session_date' => $therapistNotes['session_date'],
        'session_time' => $therapistNotes['session_time'],
        'created_at' => $therapistNotes['created_at']
    ]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No therapist notes found for this session']);
}

$stmt->close();
$conn->close();
?>

