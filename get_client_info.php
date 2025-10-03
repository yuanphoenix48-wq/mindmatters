<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a therapist
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'therapist') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Database connection
require_once 'connect.php';

// Get client ID from POST data
$clientId = $_POST['client_id'] ?? '';

if (empty($clientId)) {
    echo json_encode(['success' => false, 'message' => 'Client ID is required']);
    exit();
}

try {
    $therapistId = $_SESSION['user_id'];
    
    // Verify that this client has sessions with the current therapist
    $verifyQuery = "SELECT COUNT(*) as session_count FROM sessions WHERE client_id = ? AND therapist_id = ?";
    $verifyStmt = $conn->prepare($verifyQuery);
    $verifyStmt->bind_param("ii", $clientId, $therapistId);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();
    $sessionCount = $verifyResult->fetch_assoc()['session_count'];
    
    if ($sessionCount == 0) {
        echo json_encode(['success' => false, 'message' => 'You do not have access to this client\'s information']);
        exit();
    }
    
    // Fetch comprehensive client information
    $clientQuery = "
        SELECT 
            u.*,
            COUNT(s.id) as total_sessions,
            SUM(CASE WHEN s.status = 'completed' THEN 1 ELSE 0 END) as completed_sessions,
            SUM(CASE WHEN s.status = 'pending' THEN 1 ELSE 0 END) as pending_sessions,
            MIN(s.session_date) as first_session,
            MAX(s.session_date) as last_session,
            MAX(s.created_at) as last_activity
        FROM users u
        LEFT JOIN sessions s ON u.id = s.client_id AND s.therapist_id = ?
        WHERE u.id = ? AND u.role = 'client'
        GROUP BY u.id
    ";
    
    $clientStmt = $conn->prepare($clientQuery);
    $clientStmt->bind_param("ii", $therapistId, $clientId);
    $clientStmt->execute();
    $clientResult = $clientStmt->get_result();
    
    if ($clientResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Client not found']);
        exit();
    }
    
    $client = $clientResult->fetch_assoc();
    
    // Ensure profile picture path is correct
    if (empty($client['profile_picture']) || !file_exists($client['profile_picture'])) {
        $client['profile_picture'] = $client['gender'] === 'female' 
            ? 'images/profile/default_images/female_gender.png' 
            : 'images/profile/default_images/male_gender.png';
    }
    
    // Format dates for better display
    if ($client['created_at']) {
        $client['created_at_formatted'] = date('F j, Y', strtotime($client['created_at']));
    }
    
    if ($client['first_session']) {
        $client['first_session_formatted'] = date('F j, Y', strtotime($client['first_session']));
    }
    
    if ($client['last_session']) {
        $client['last_session_formatted'] = date('F j, Y', strtotime($client['last_session']));
    }
    
    // Get additional session statistics
    $statsQuery = "
        SELECT 
            COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled_sessions,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_sessions,
            AVG(CASE WHEN status = 'completed' AND session_date IS NOT NULL THEN 1 ELSE NULL END) as avg_completion_rate
        FROM sessions 
        WHERE client_id = ? AND therapist_id = ?
    ";
    
    $statsStmt = $conn->prepare($statsQuery);
    $statsStmt->bind_param("ii", $clientId, $therapistId);
    $statsStmt->execute();
    $statsResult = $statsStmt->get_result();
    $stats = $statsResult->fetch_assoc();
    
    // Merge statistics with client data
    $client = array_merge($client, $stats);
    
    // Get recent session notes (last 3 sessions)
    $notesQuery = "
        SELECT session_date, status, notes 
        FROM sessions 
        WHERE client_id = ? AND therapist_id = ? 
        ORDER BY session_date DESC 
        LIMIT 3
    ";
    
    $notesStmt = $conn->prepare($notesQuery);
    $notesStmt->bind_param("ii", $clientId, $therapistId);
    $notesStmt->execute();
    $notesResult = $notesStmt->get_result();
    
    $recentSessions = [];
    while ($session = $notesResult->fetch_assoc()) {
        $recentSessions[] = [
            'date' => $session['session_date'],
            'date_formatted' => date('M j, Y', strtotime($session['session_date'])),
            'status' => $session['status'],
            'notes' => $session['notes'] ? substr($session['notes'], 0, 100) . '...' : 'No notes'
        ];
    }
    
    $client['recent_sessions'] = $recentSessions;
    
    // Clean up sensitive information
    unset($client['password']);
    
    echo json_encode([
        'success' => true,
        'client' => $client,
        'message' => 'Client information retrieved successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching client info: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while fetching client information'
    ]);
} finally {
    if (isset($verifyStmt)) $verifyStmt->close();
    if (isset($clientStmt)) $clientStmt->close();
    if (isset($statsStmt)) $statsStmt->close();
    if (isset($notesStmt)) $notesStmt->close();
    $conn->close();
}
?>
