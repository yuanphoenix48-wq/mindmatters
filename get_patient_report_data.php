<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once 'connect.php';

$userId = $_SESSION['user_id'];
$clientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if (!$clientId) {
    echo json_encode(['success' => false, 'error' => 'Invalid client ID']);
    exit();
}

try {
    // Debug: Check if connection is working
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit();
    }
    
    // Get client basic information
    $sql = "SELECT u.*, 
            COUNT(s.id) as total_sessions,
            MAX(s.session_date) as last_session,
            MIN(s.session_date) as first_session
            FROM users u 
            JOIN sessions s ON u.id = s.client_id 
            WHERE u.id = ? AND s.therapist_id = ?
            GROUP BY u.id";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'SQL prepare failed: ' . $conn->error]);
        exit();
    }
    
    $stmt->bind_param("ii", $clientId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $client = $result->fetch_assoc();
    $stmt->close();
    
    if (!$client) {
        echo json_encode(['success' => false, 'error' => 'Client not found or access denied. Client ID: ' . $clientId . ', User ID: ' . $userId]);
        exit();
    }
    
    // Get session details and assessments
    $sql = "SELECT s.*, 
            mha.assessment_type, mha.assessment_data, mha.total_score, mha.severity_level,
            mha.created_at as assessment_date
            FROM sessions s
            LEFT JOIN mental_health_assessments mha ON s.id = mha.session_id
            WHERE s.client_id = ? AND s.therapist_id = ?
            ORDER BY s.session_date DESC, s.session_time DESC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Session SQL prepare failed: ' . $conn->error]);
        exit();
    }
    
    $stmt->bind_param("ii", $clientId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $sessions = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get therapist notes from sessions
    $sql = "SELECT notes, created_at 
            FROM sessions 
            WHERE client_id = ? AND therapist_id = ? AND notes IS NOT NULL AND notes != ''
            ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Notes SQL prepare failed: ' . $conn->error]);
        exit();
    }
    
    $stmt->bind_param("ii", $clientId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $notes = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Process assessment data
    $assessmentHistory = [];
    $overallMoodTrend = [];
    $stressLevels = [];
    $anxietyLevels = [];
    
    foreach ($sessions as $session) {
        if ($session['assessment_data']) {
            $assessmentData = json_decode($session['assessment_data'], true);
            if ($assessmentData) {
                $assessmentHistory[] = [
                    'date' => $session['session_date'],
                    'type' => $session['assessment_type'],
                    'score' => $session['total_score'],
                    'severity' => $session['severity_level'],
                    'data' => $assessmentData
                ];
                
                // Extract mood and stress data for trends
                if (isset($assessmentData['mood_rating'])) {
                    $overallMoodTrend[] = [
                        'date' => $session['session_date'],
                        'rating' => $assessmentData['mood_rating']
                    ];
                }
                if (isset($assessmentData['stress_level'])) {
                    $stressLevels[] = [
                        'date' => $session['session_date'],
                        'level' => $assessmentData['stress_level']
                    ];
                }
                if (isset($assessmentData['anxiety_level'])) {
                    $anxietyLevels[] = [
                        'date' => $session['session_date'],
                        'level' => $assessmentData['anxiety_level']
                    ];
                }
            }
        }
    }
    
    // Generate overall report
    $overallReport = generateOverallReport($client, $sessions, $assessmentHistory, $notes);
    
    // Generate progress details
    $progressDetails = generateProgressDetails($assessmentHistory, $overallMoodTrend, $stressLevels, $anxietyLevels);
    
    // Generate additional notes
    $additionalNotes = generateAdditionalNotes($notes, $sessions);
    
    $response = [
        'success' => true,
        'data' => [
            'client_name' => $client['first_name'] . ' ' . $client['last_name'],
            'email' => $client['email'],
            'total_sessions' => $client['total_sessions'],
            'first_session' => $client['first_session'] ? date('M j, Y', strtotime($client['first_session'])) : 'N/A',
            'last_session' => $client['last_session'] ? date('M j, Y', strtotime($client['last_session'])) : 'N/A',
            'overall_report' => $overallReport,
            'progress_details' => $progressDetails,
            'additional_notes' => $additionalNotes,
            'assessment_history' => $assessmentHistory,
            'session_notes' => $notes
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error in get_patient_report_data.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

function generateOverallReport($client, $sessions, $assessmentHistory, $notes) {
    $report = "CLIENT OVERVIEW\n";
    $report .= "================\n\n";
    
    $report .= "Client: " . $client['first_name'] . ' ' . $client['last_name'] . "\n";
    $report .= "Total Sessions: " . count($sessions) . "\n";
    $report .= "Treatment Period: " . ($client['first_session'] ? date('M j, Y', strtotime($client['first_session'])) : 'N/A') . 
               " to " . ($client['last_session'] ? date('M j, Y', strtotime($client['last_session'])) : 'N/A') . "\n\n";
    
    if (!empty($assessmentHistory)) {
        $report .= "ASSESSMENT SUMMARY\n";
        $report .= "==================\n";
        
        $phq9Scores = array_filter($assessmentHistory, function($a) { return $a['type'] === 'phq9'; });
        $gad7Scores = array_filter($assessmentHistory, function($a) { return $a['type'] === 'gad7'; });
        
        if (!empty($phq9Scores)) {
            $latestPHQ9 = $phq9Scores[0];
            $report .= "Latest PHQ-9 Score: " . $latestPHQ9['score'] . " (" . ucfirst($latestPHQ9['severity']) . ")\n";
        }
        
        if (!empty($gad7Scores)) {
            $latestGAD7 = $gad7Scores[0];
            $report .= "Latest GAD-7 Score: " . $latestGAD7['score'] . " (" . ucfirst($latestGAD7['severity']) . ")\n";
        }
        
        $report .= "\n";
    }
    
    $report .= "TREATMENT PROGRESS\n";
    $report .= "==================\n";
    
    if (count($sessions) > 0) {
        $completedSessions = array_filter($sessions, function($s) { return $s['status'] === 'completed'; });
        $report .= "Completed Sessions: " . count($completedSessions) . "/" . count($sessions) . "\n";
        
        if (!empty($notes)) {
            $report .= "Therapist Notes Available: " . count($notes) . " entries\n";
        }
    }
    
    return $report;
}

function generateProgressDetails($assessmentHistory, $moodTrend, $stressLevels, $anxietyLevels) {
    $details = "PROGRESS ANALYSIS\n";
    $details .= "=================\n\n";
    
    if (!empty($moodTrend)) {
        $details .= "MOOD TREND ANALYSIS\n";
        $details .= "-------------------\n";
        $avgMood = array_sum(array_column($moodTrend, 'rating')) / count($moodTrend);
        $details .= "Average Mood Rating: " . round($avgMood, 1) . "/10\n";
        
        if (count($moodTrend) > 1) {
            $firstMood = $moodTrend[count($moodTrend) - 1]['rating'];
            $latestMood = $moodTrend[0]['rating'];
            $change = $latestMood - $firstMood;
            $details .= "Mood Change: " . ($change > 0 ? "+" : "") . $change . " points\n";
        }
        $details .= "\n";
    }
    
    if (!empty($stressLevels)) {
        $details .= "STRESS LEVEL ANALYSIS\n";
        $details .= "---------------------\n";
        $avgStress = array_sum(array_column($stressLevels, 'level')) / count($stressLevels);
        $details .= "Average Stress Level: " . round($avgStress, 1) . "/10\n";
        $details .= "\n";
    }
    
    if (!empty($anxietyLevels)) {
        $details .= "ANXIETY LEVEL ANALYSIS\n";
        $details .= "---------------------\n";
        $avgAnxiety = array_sum(array_column($anxietyLevels, 'level')) / count($anxietyLevels);
        $details .= "Average Anxiety Level: " . round($avgAnxiety, 1) . "/10\n";
        $details .= "\n";
    }
    
    if (!empty($assessmentHistory)) {
        $details .= "ASSESSMENT HISTORY\n";
        $details .= "------------------\n";
        foreach (array_slice($assessmentHistory, 0, 5) as $assessment) {
            $details .= date('M j, Y', strtotime($assessment['date'])) . ": ";
            $details .= strtoupper($assessment['type']) . " - ";
            
            // Handle different assessment types properly
            if ($assessment['type'] === 'pre_session') {
                $details .= "Pre-Session Assessment";
                if (isset($assessment['data']['mood_rating'])) {
                    $details .= " (Mood: " . $assessment['data']['mood_rating'] . "/10)";
                }
            } elseif ($assessment['type'] === 'post_session') {
                $details .= "Post-Session Assessment";
                if (isset($assessment['data']['post_mood_rating'])) {
                    $details .= " (Mood: " . $assessment['data']['post_mood_rating'] . "/10)";
                }
            } else {
                $details .= "Score: " . $assessment['score'] . " (" . ucfirst($assessment['severity']) . ")";
            }
            $details .= "\n";
        }
    }
    
    return $details;
}

function generateAdditionalNotes($notes, $sessions) {
    $additionalNotes = "THERAPIST NOTES & OBSERVATIONS\n";
    $additionalNotes .= "=============================\n\n";
    
    if (!empty($notes)) {
        $additionalNotes .= "SESSION NOTES:\n";
        $additionalNotes .= "-------------\n";
        foreach (array_slice($notes, 0, 3) as $note) {
            $additionalNotes .= "Date: " . date('M j, Y', strtotime($note['created_at'])) . "\n";
            $additionalNotes .= "Note: " . $note['notes'] . "\n\n";
        }
    }
    
    return $additionalNotes;
}

$conn->close();
?>
