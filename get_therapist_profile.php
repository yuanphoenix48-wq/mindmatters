<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'client' && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'therapist')) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once 'connect.php';

$therapistId = isset($_GET['therapist_id']) ? (int)$_GET['therapist_id'] : 0;
if (!$therapistId) {
    echo json_encode(['success' => false, 'error' => 'Missing therapist_id']);
    exit();
}

// Fetch therapist details with average rating
$sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.profile_picture,
               u.specialization, u.years_of_experience, u.languages_spoken,
               u.contact_number, u.license_number,
               COALESCE(AVG(sf.session_rating), 0) AS avg_rating,
               COUNT(sf.id) AS rating_count
        FROM users u
        LEFT JOIN student_feedback sf ON sf.therapist_id = u.id
        WHERE u.id = ? AND u.role = 'therapist'";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Failed to prepare statement']);
    exit();
}
$stmt->bind_param('i', $therapistId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'Therapist not found']);
    exit();
}

// Normalize picture and numeric fields
$row['profile_picture'] = $row['profile_picture'] ?: 'images/profile/default_images/default_profile.png';
$row['avg_rating'] = (float) number_format((float) $row['avg_rating'], 1, '.', '');
$row['rating_count'] = (int) $row['rating_count'];

echo json_encode(['success' => true, 'therapist' => $row]);
?>



