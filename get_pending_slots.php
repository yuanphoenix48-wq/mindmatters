<?php
header('Content-Type: application/json');
require_once 'connect.php';

$therapistId = isset($_GET['therapist_id']) ? intval($_GET['therapist_id']) : 0;
$sessionDate = isset($_GET['session_date']) ? $_GET['session_date'] : '';

if (!$therapistId || !$sessionDate) {
    echo json_encode(['success' => false, 'slots' => []]);
    exit;
}

try {
    // Pending requests that would conflict for the therapist on the date
    $sql = "SELECT session_time FROM sessions WHERE therapist_id = ? AND session_date = ? AND status = 'pending'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $therapistId, $sessionDate);
    $stmt->execute();
    $res = $stmt->get_result();
    $slots = [];
    while ($row = $res->fetch_assoc()) {
        $slots[] = $row['session_time'];
    }
    $stmt->close();
    echo json_encode(['success' => true, 'slots' => $slots]);
} catch (Throwable $e) {
    error_log('get_pending_slots: ' . $e->getMessage());
    echo json_encode(['success' => false, 'slots' => []]);
}
?>





























