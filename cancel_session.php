<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$sessionId = $data['session_id'] ?? null;
$cancelReason = trim($data['reason'] ?? '');

if (!$sessionId) {
    echo json_encode(['success' => false, 'error' => 'Session ID is required']);
    exit();
}

// Reason requirement will depend on session status (scheduled requires reason; pending does not)

// Database connection
require_once 'connect.php';
// Verify that the session belongs to the client
$studentId = $_SESSION['user_id'];
$sql = "SELECT id, status, notes FROM sessions WHERE id = ? AND client_id = ? AND (status = 'scheduled' OR status = 'pending')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $sessionId, $studentId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Session not found or cannot be cancelled']);
    exit();
}

// Decide behavior based on status
$sessionRow = $result->fetch_assoc();
$status = $sessionRow['status'];

if ($status === 'scheduled') {
    if ($cancelReason === '') {
        echo json_encode(['success' => false, 'error' => 'Please provide a reason for cancelling the session.']);
        exit();
    }
    // Update with reason
    $saved = false;
    $hasCancelReasonCol = false;
    $colCheck = $conn->query("SHOW COLUMNS FROM sessions LIKE 'cancellation_reason'");
    if ($colCheck && $colCheck->num_rows > 0) { $hasCancelReasonCol = true; }
    if ($hasCancelReasonCol) {
        $sql = "UPDATE sessions SET status = 'cancelled', cancellation_reason = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $cancelReason, $sessionId);
        $saved = $stmt->execute();
    } else {
        $sql = "UPDATE sessions SET status = 'cancelled', notes = CONCAT(IFNULL(notes,''), ?) WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $append = "\n[Cancellation Reason] " . $cancelReason;
        $stmt->bind_param("si", $append, $sessionId);
        $saved = $stmt->execute();
    }
} else { // pending
    // Allow cancel with no reason and do not append anything
    $sql = "UPDATE sessions SET status = 'cancelled' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $sessionId);
    $saved = $stmt->execute();
}

if ($saved) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to cancel session']);
}

$stmt->close();
$conn->close();
?> 