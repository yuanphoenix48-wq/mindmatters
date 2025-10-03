<?php
session_start();
header('Content-Type: application/json');
ob_start();

// Require admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
	echo json_encode(['success' => false, 'error' => 'unauthorized']);
	exit();
}

require_once 'connect.php';

$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
if ($sessionId <= 0) {
	echo json_encode(['success' => false, 'error' => 'invalid_session']);
	exit();
}

// Fetch client feedback (student_feedback)
$clientFeedback = null;
if ($stmt = $conn->prepare("SELECT sf.*, 
		CONCAT(c.first_name,' ',c.last_name) AS client_name,
		CONCAT(t.first_name,' ',t.last_name) AS therapist_name,
		COALESCE(sf.created_at, s.session_date) AS submitted_at
	FROM student_feedback sf
	JOIN sessions s ON s.id = sf.session_id
	JOIN users c ON c.id = sf.client_id
	LEFT JOIN users t ON t.id = sf.therapist_id
	WHERE sf.session_id = ?
	LIMIT 1")) {
	$stmt->bind_param('i', $sessionId);
	$stmt->execute();
	$res = $stmt->get_result();
	$clientFeedback = $res->fetch_assoc() ?: null;
	$stmt->close();
}

// Detect therapist feedback table
$therapistTable = 'therapist_feedback';
$resTbl = $conn->query("SHOW TABLES LIKE 'therapist_feedback'");
if (!$resTbl || $resTbl->num_rows === 0) {
	$resTbl2 = $conn->query("SHOW TABLES LIKE 'doctor_feedback'");
	if ($resTbl2 && $resTbl2->num_rows > 0) { $therapistTable = 'doctor_feedback'; }
}

// Fetch therapist feedback if table exists
$therapistFeedback = null;
if (isset($therapistTable)) {
	$sqlTf = "SELECT df.*, 
		CONCAT(t.first_name,' ',t.last_name) AS therapist_name,
		CONCAT(c.first_name,' ',c.last_name) AS client_name,
		COALESCE(df.created_at, s.session_date) AS submitted_at
		FROM $therapistTable df
		JOIN sessions s ON s.id = df.session_id
		JOIN users t ON t.id = df.therapist_id
		LEFT JOIN users c ON c.id = df.client_id
		WHERE df.session_id = ?
		LIMIT 1";
	if ($stmt2 = $conn->prepare($sqlTf)) {
		$stmt2->bind_param('i', $sessionId);
		$stmt2->execute();
		$res2 = $stmt2->get_result();
		$therapistFeedback = $res2->fetch_assoc() ?: null;
		$stmt2->close();
	}
}

// Fetch system feedback for this session (both client and therapist submissions)
$systemFeedback = [];
if ($stmt3 = $conn->prepare("SELECT sf.*, 
        CONCAT(u.first_name,' ',u.last_name) AS user_name
        FROM system_feedback sf
        LEFT JOIN users u ON u.id = sf.user_id
        WHERE sf.session_id = ?
        ORDER BY COALESCE(sf.created_at, sf.id) DESC")) {
    $stmt3->bind_param('i', $sessionId);
    $stmt3->execute();
    $res3 = $stmt3->get_result();
    while ($row = $res3->fetch_assoc()) { $systemFeedback[] = $row; }
    $stmt3->close();
}

$conn->close();

// Ensure no stray output corrupts JSON
if (ob_get_length()) { ob_clean(); }

echo json_encode([
    'success' => true,
    'client' => $clientFeedback,
    'therapist' => $therapistFeedback,
    'system' => $systemFeedback,
]);
exit;

