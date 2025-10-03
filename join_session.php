<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Location: index.php');
    exit();
}

require_once 'connect.php';

// Ensure server interprets session_date/session_time in your local timezone
date_default_timezone_set('Asia/Manila'); // PH time

$clientId = (int)$_SESSION['user_id'];
$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;

if ($sessionId <= 0) {
    http_response_code(400);
    echo 'Invalid session.';
    exit();
}

$stmt = $conn->prepare("SELECT id, client_id, status, session_date, session_time, meet_link FROM sessions WHERE id = ?");
$stmt->bind_param('i', $sessionId);
$stmt->execute();
$res = $stmt->get_result();
$session = $res->fetch_assoc();
$stmt->close();

if (!$session || (int)$session['client_id'] !== $clientId) {
    http_response_code(403);
    echo 'You are not authorized to join this session.';
    exit();
}

if ($session['status'] !== 'scheduled') {
    http_response_code(409);
    echo 'This session is not scheduled.';
    exit();
}

if (empty($session['meet_link'])) {
    http_response_code(409);
    echo 'Meeting link is not available yet.';
    exit();
}

// No need to override timezone again; use configured timezone above

$sessionDateTime = strtotime($session['session_date'] . ' ' . $session['session_time']);
$now = time();

// Allow join from 5 minutes before scheduled time onwards (grace window)
if (isset($_GET['check'])) {
    header('Content-Type: application/json');
    $canJoin = $now >= ($sessionDateTime - 5 * 60) && !empty($session['meet_link']) && $session['status'] === 'scheduled';
    echo json_encode([
        'success' => true,
        'canJoin' => $canJoin,
        'now' => date('c', $now),
        'start' => date('c', $sessionDateTime),
        'hasLink' => !empty($session['meet_link']),
        'status' => $session['status']
    ]);
    exit();
}

if ($now < ($sessionDateTime - 5 * 60)) {
    http_response_code(409);
    echo 'You can join 5 minutes before the scheduled time.';
    exit();
}

// Redirect without exposing the link elsewhere
header('Location: ' . $session['meet_link']);
exit();


