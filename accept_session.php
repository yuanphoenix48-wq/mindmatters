<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'therapist') {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get session_id from either JSON or form data
    $input = json_decode(file_get_contents('php://input'), true);
    $session_id = isset($input['session_id']) ? intval($input['session_id']) : (isset($_POST['session_id']) ? intval($_POST['session_id']) : 0);
    $therapist_id = $_SESSION['user_id'];

    if ($session_id === 0) {
        $response = ['success' => false, 'error' => 'Invalid session ID'];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // First, check if the session is still pending and assigned to this therapist
        $check_sql = "SELECT id FROM sessions WHERE id = ? AND therapist_id = ? AND status = 'pending'";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $session_id, $therapist_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("Session is no longer available or has already been accepted by another therapist.");
        }

        // Get therapist's default meet link
        $doctor_sql = "SELECT default_meet_link FROM users WHERE id = ?";
        $doctor_stmt = $conn->prepare($doctor_sql);
        $doctor_stmt->bind_param("i", $therapist_id);
        $doctor_stmt->execute();
        $doctor_result = $doctor_stmt->get_result();
        $doctor_data = $doctor_result->fetch_assoc();
        $default_meet_link = $doctor_data['default_meet_link'] ?? null;
        $doctor_stmt->close();

        // Update this session to scheduled and assign the doctor
        if ($default_meet_link) {
            // If doctor has a default meet link, use it
            $update_sql = "UPDATE sessions SET status = 'scheduled', therapist_id = ?, meet_link = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("isi", $therapist_id, $default_meet_link, $session_id);
        } else {
            // If no default meet link, just update status and doctor
            $update_sql = "UPDATE sessions SET status = 'scheduled', therapist_id = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ii", $therapist_id, $session_id);
        }
        $update_stmt->execute();

        // Get session and user details for notification
        $sessionDetailsSql = "SELECT s.client_id, s.session_date, s.session_time, s.meet_link,
                                     u.email as client_email, u.first_name as client_name,
                                     t.first_name as therapist_name
                              FROM sessions s
                              JOIN users u ON s.client_id = u.id
                              JOIN users t ON s.therapist_id = t.id
                              WHERE s.id = ?";
        $sessionDetailsStmt = $conn->prepare($sessionDetailsSql);
        $sessionDetailsStmt->bind_param("i", $session_id);
        $sessionDetailsStmt->execute();
        $sessionDetailsResult = $sessionDetailsStmt->get_result();
        $sessionDetails = $sessionDetailsResult->fetch_assoc();
        $sessionDetailsStmt->close();

        // Commit transaction
        $conn->commit();
        
        // Send notification to client about session acceptance
        if ($sessionDetails) {
            require_once 'includes/EmailNotifications.php';
            $emailNotifications = new EmailNotifications();
            
            $emailNotifications->sendTherapistAcceptanceNotification(
                $sessionDetails['client_email'],
                $sessionDetails['client_name'],
                $sessionDetails['therapist_name'],
                $sessionDetails['session_date'],
                $sessionDetails['session_time'],
                $sessionDetails['meet_link']
            );
        }
        
        $response = ['success' => true, 'message' => 'Session accepted successfully'];
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $response = ['success' => false, 'error' => $e->getMessage()];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// If not POST request, redirect to dashboard
header('Location: dashboard.php');
exit(); 