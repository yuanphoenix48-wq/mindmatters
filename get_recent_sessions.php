<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['success' => false, 'error' => 'Not logged in']); exit(); }
require_once 'connect.php';

// Verify admin role
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$me = $result->fetch_assoc();
if (!$me || $me['role'] !== 'admin') { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit(); }
$stmt->close();

$sql = "SELECT s.id, s.session_date, s.session_time, s.status,
               CONCAT(stud.first_name, ' ', stud.last_name) AS client_name,
               CONCAT(doc.first_name, ' ', doc.last_name)   AS therapist_name
        FROM sessions s
        JOIN users stud ON s.client_id = stud.id
        LEFT JOIN users doc ON s.client_id = doc.id
        WHERE s.session_date >= CURDATE() - INTERVAL 7 DAY
        ORDER BY s.session_date DESC, s.session_time DESC
        LIMIT 10";
$res = $conn->query($sql);
$sessions = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $row['session_date'] = date('M j, Y', strtotime($row['session_date']));
        $row['session_time'] = date('g:i A', strtotime($row['session_time']));
        $sessions[] = $row;
    }
}

echo json_encode(['success' => true, 'sessions' => $sessions]);
$conn->close();
?>























































