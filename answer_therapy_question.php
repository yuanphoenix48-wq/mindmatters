<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['therapist','doctor','admin'])) { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit(); }
require_once 'connect.php';

$payload = json_decode(file_get_contents('php://input'), true);
$id = (int)($payload['id'] ?? 0);
$answer = trim($payload['answer'] ?? '');
if (!$id || $answer === '') { echo json_encode(['success'=>false,'error'=>'Missing fields']); exit(); }

$ok = false;
$clientId = null;

// Update answer with robust prepare/execute checks
if ($stmt = $conn->prepare("UPDATE therapy_questions SET answer = ?, status='answered', answered_at = NOW() WHERE id = ?")) {
    $stmt->bind_param('si', $answer, $id);
    $ok = (bool)$stmt->execute();
    $stmt->close();
} else {
    echo json_encode(['success'=>false,'error'=>'Database error']);
    $conn->close();
    exit();
}

// Try to fetch client id and create notification, but never fail the request if these steps error
if ($ok) {
    try {
        if ($q = $conn->prepare("SELECT client_id FROM therapy_questions WHERE id = ?")) {
            $q->bind_param('i', $id);
            if ($q->execute()) {
                $q->bind_result($clientId);
                $q->fetch();
            }
            $q->close();
        }

        if ($clientId) {
            $hasTable = @$conn->query("SHOW TABLES LIKE 'notifications'");
            if ($hasTable && $hasTable instanceof mysqli_result) {
                if ($hasTable->num_rows > 0) {
                    $type = 'therapy_answer';
                    $title = 'New Answer to Your Therapy Question';
                    $message = 'A therapist responded to your question. Tap to view.';
                    $link = 'therapy_support.php';
                    if ($ins = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES (?,?,?,?,?,0,NOW())")) {
                        $ins->bind_param('issss', $clientId, $type, $title, $message, $link);
                        @$ins->execute();
                        $ins->close();
                    }
                }
                $hasTable->free();
            }
        }
    } catch (Throwable $e) {
        // Swallow errors from auxiliary notification logic to avoid 500s
    }
}

$conn->close();
echo json_encode(['success'=>$ok]);