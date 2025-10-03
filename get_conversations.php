<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

require_once 'connect.php';

$userId = $_SESSION['user_id'];
$sql = "SELECT role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$userRole = $user['role'];

if ($userRole === 'client') {
    // Conversations from messages UNION therapist from accepted/scheduled sessions (even without prior messages)
    $sql = "(
                SELECT 
                    u.id as other_id,
                    u.first_name,
                    u.last_name,
                    u.profile_picture,
                    m2.message as last_message,
                    m2.created_at as sort_time
                FROM users u
                JOIN (
                    SELECT 
                        CASE WHEN sender_id = $userId THEN receiver_id ELSE sender_id END as other_user_id,
                        MAX(id) as max_message_id
                    FROM messages
                    WHERE sender_id = $userId OR receiver_id = $userId
                    GROUP BY other_user_id
                ) m1 ON u.id = m1.other_user_id
                JOIN messages m2 ON m2.id = m1.max_message_id
                WHERE u.role = 'therapist'
            )
            UNION
            (
                SELECT DISTINCT 
                    u.id as other_id,
                    u.first_name,
                    u.last_name,
                    u.profile_picture,
                    'No messages yet' as last_message,
                    CONCAT(s.session_date,' ', s.session_time) as sort_time
                FROM sessions s
                JOIN users u ON u.id = s.therapist_id
                WHERE s.client_id = $userId AND s.status IN ('scheduled','accepted')
                AND u.id NOT IN (
                    SELECT DISTINCT CASE WHEN sender_id = $userId THEN receiver_id ELSE sender_id END
                    FROM messages WHERE sender_id = $userId OR receiver_id = $userId
                )
            )
            ORDER BY sort_time DESC";
} else if ($userRole === 'therapist') {
    $sql = "(
                SELECT 
                    u.id as other_id,
                    u.first_name,
                    u.last_name,
                    u.profile_picture,
                    m2.message as last_message,
                    m2.created_at as sort_time
                FROM users u
                JOIN (
                    SELECT 
                        CASE WHEN sender_id = $userId THEN receiver_id ELSE sender_id END as other_user_id,
                        MAX(id) as max_message_id
                    FROM messages
                    WHERE sender_id = $userId OR receiver_id = $userId
                    GROUP BY other_user_id
                ) m1 ON u.id = m1.other_user_id
                JOIN messages m2 ON m2.id = m1.max_message_id
                WHERE u.role IN ('client','student')
            )
            UNION
            (
                SELECT DISTINCT 
                    u.id as other_id,
                    u.first_name,
                    u.last_name,
                    u.profile_picture,
                    'No messages yet' as last_message,
                    CONCAT(s.session_date,' ', s.session_time) as sort_time
                FROM sessions s
                JOIN users u ON u.id = s.client_id
                WHERE s.therapist_id = $userId AND s.status IN ('scheduled','accepted')
                AND u.id NOT IN (
                    SELECT DISTINCT CASE WHEN sender_id = $userId THEN receiver_id ELSE sender_id END
                    FROM messages WHERE sender_id = $userId OR receiver_id = $userId
                )
            )
            ORDER BY sort_time DESC";
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid user role']);
    exit();
}

$result = $conn->query($sql);
$conversations = $result->fetch_all(MYSQLI_ASSOC);

header('Content-Type: application/json');
echo json_encode(['success' => true, 'conversations' => $conversations]);
$conn->close(); 