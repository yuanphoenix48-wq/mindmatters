<?php
	session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'therapist') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['request_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing request ID']);
    exit();
}

$requestId = (int)$_GET['request_id']; // This is the session id
$therapistId = (int)$_SESSION['user_id'];

require_once 'connect.php';

// Get session notes if therapist is primary or is an invited/accepted co-therapist
$sql = "SELECT s.notes
        FROM sessions s
        WHERE s.id = ?
          AND (
            s.therapist_id = ?
            OR EXISTS (
              SELECT 1 FROM session_therapists st
              WHERE st.session_id = s.id AND st.therapist_id = ? AND st.status IN ('invited','accepted')
            )
          )";
$stmt = $conn->prepare($sql);
if (!$stmt) { echo json_encode(['success'=>false,'error'=>'DB error']); exit(); }
$stmt->bind_param("iii", $requestId, $therapistId, $therapistId);
$stmt->execute();
$result = $stmt->get_result();
$request = $result->fetch_assoc();

if ($request) {
    echo json_encode(['success' => true, 'notes' => $request['notes']]);
} else {
    echo json_encode(['success' => false, 'error' => 'Request not found']);
}

$stmt->close();
$conn->close();
?>