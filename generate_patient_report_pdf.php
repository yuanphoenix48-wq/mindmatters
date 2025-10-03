<?php
session_start();

// Debug logging
error_log("generate_patient_report_pdf.php called");
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'not set'));

if (!isset($_SESSION['user_id'])) {
    error_log("Unauthorized access to generate_patient_report_pdf.php");
    http_response_code(401);
    exit('Unauthorized');
}

require_once 'connect.php';

$input = json_decode(file_get_contents('php://input'), true);
error_log("Input received: " . json_encode($input));

$clientId = isset($input['patient_id']) ? (int)$input['patient_id'] : 0;
$clientName = isset($input['patient_name']) ? $input['patient_name'] : 'Unknown Client';

error_log("Client ID: " . $clientId . ", Client Name: " . $clientName);

if (!$clientId) {
    error_log("Invalid client ID provided");
    http_response_code(400);
    exit('Invalid client ID');
}

try {
    $userId = $_SESSION['user_id'];
    
    // Get patient data
    $sql = "SELECT u.*, 
            COUNT(s.id) as total_sessions,
            MAX(s.session_date) as last_session,
            MIN(s.session_date) as first_session
            FROM users u 
            JOIN sessions s ON u.id = s.client_id 
            WHERE u.id = ? AND s.therapist_id = ?
            GROUP BY u.id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $clientId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $client = $result->fetch_assoc();
    $stmt->close();
    
    if (!$client) {
        http_response_code(404);
        exit('Client not found');
    }
    
    // Get detailed session and assessment data
    $sql = "SELECT s.*, 
            mha.assessment_type, mha.assessment_data, mha.total_score, mha.severity_level,
            mha.created_at as assessment_date
            FROM sessions s
            LEFT JOIN mental_health_assessments mha ON s.id = mha.session_id
            WHERE s.client_id = ? AND s.therapist_id = ?
            ORDER BY s.session_date DESC, s.session_time DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $clientId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $sessions = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get therapist notes
    $sql = "SELECT notes, created_at 
            FROM sessions 
            WHERE client_id = ? AND therapist_id = ? AND notes IS NOT NULL AND notes != ''
            ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $clientId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $notes = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Generate HTML content for PDF
    $html = generateHTMLReport($client, $sessions, $notes);
    
    // Set headers for download
    $filename = "client_report_{$clientId}_" . date('Y-m-d_H-i-s') . ".html";
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    header('Expires: 0');
    
    // Output HTML
    echo $html;
    
} catch (Exception $e) {
    error_log("Error in generate_patient_report_pdf.php: " . $e->getMessage());
    http_response_code(500);
    exit('Report generation failed: ' . $e->getMessage());
}

function generateHTMLReport($client, $sessions, $notes) {
    $overallReport = generateOverallReport($client, $sessions, $notes);
    $progressDetails = generateProgressDetails($sessions);
    $additionalNotes = generateAdditionalNotes($notes);
    
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Client Report - ' . htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #1D5D9B; padding-bottom: 20px; }
        .header h1 { color: #1D5D9B; margin: 0; }
        .header h2 { color: #666; margin: 10px 0 0 0; font-weight: normal; }
        .section { margin-bottom: 30px; }
        .section h3 { color: #1D5D9B; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
        .client-info { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .client-info table { width: 100%; }
        .client-info td { padding: 5px 0; }
        .client-info td:first-child { font-weight: bold; width: 30%; }
        .content { white-space: pre-wrap; }
        .footer { margin-top: 40px; text-align: center; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>CLIENT REPORT</h1>
        <h2>Generated for: ' . htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) . '</h2>
        <p>Generated on: ' . date('M j, Y \a\t g:i A') . '</p>
    </div>
    
    <div class="section">
        <h3>CLIENT INFORMATION</h3>
        <div class="client-info">
            <table>
                <tr><td>Client Name:</td><td>' . htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) . '</td></tr>
                <tr><td>Email Address:</td><td>' . htmlspecialchars($client['email']) . '</td></tr>
                <tr><td>Total Sessions:</td><td>' . $client['total_sessions'] . '</td></tr>
                <tr><td>First Session:</td><td>' . ($client['first_session'] ? date('M j, Y', strtotime($client['first_session'])) : 'N/A') . '</td></tr>
                <tr><td>Last Session:</td><td>' . ($client['last_session'] ? date('M j, Y', strtotime($client['last_session'])) : 'N/A') . '</td></tr>
            </table>
        </div>
    </div>
    
    <div class="section">
        <h3>TREATMENT OVERVIEW</h3>
        <div class="content">' . htmlspecialchars($overallReport) . '</div>
    </div>
    
    <div class="section">
        <h3>PROGRESS ANALYSIS</h3>
        <div class="content">' . htmlspecialchars($progressDetails) . '</div>
    </div>
    
    <div class="section">
        <h3>ADDITIONAL NOTES</h3>
        <div class="content">' . htmlspecialchars($additionalNotes) . '</div>
    </div>
    
    <div class="footer">
        <p>This report was generated by Mind Matters Therapy Platform</p>
    </div>
</body>
</html>';
    
    return $html;
}

function generateOverallReport($client, $sessions, $notes) {
    $report = "This comprehensive report provides an overview of the client's treatment progress, assessment history, and therapeutic journey.\n\n";
    
    $report .= "TREATMENT SUMMARY\n";
    $report .= "================\n";
    $report .= "• Total sessions conducted: " . count($sessions) . "\n";
    $report .= "• Treatment period: " . ($client['first_session'] ? date('M j, Y', strtotime($client['first_session'])) : 'N/A') . 
               " to " . ($client['last_session'] ? date('M j, Y', strtotime($client['last_session'])) : 'N/A') . "\n";
    
    $completedSessions = array_filter($sessions, function($s) { return $s['status'] === 'completed'; });
    $completionRate = count($sessions) > 0 ? round((count($completedSessions) / count($sessions)) * 100, 1) : 0;
    $report .= "• Completed sessions: " . count($completedSessions) . " (" . $completionRate . "% completion rate)\n";
    
    if (!empty($notes)) {
        $report .= "• Therapist notes available: " . count($notes) . " entries\n";
    }
    
    $report .= "\nASSESSMENT OVERVIEW\n";
    $report .= "==================\n";
    $assessments = array_filter($sessions, function($s) { return !empty($s['assessment_type']); });
    if (!empty($assessments)) {
        $report .= "• Total assessments completed: " . count($assessments) . "\n";
        
        $phq9Count = count(array_filter($assessments, function($a) { return $a['assessment_type'] === 'phq9'; }));
        $gad7Count = count(array_filter($assessments, function($a) { return $a['assessment_type'] === 'gad7'; }));
        
        if ($phq9Count > 0) $report .= "• PHQ-9 (Depression) assessments: " . $phq9Count . "\n";
        if ($gad7Count > 0) $report .= "• GAD-7 (Anxiety) assessments: " . $gad7Count . "\n";
        
        // Add latest assessment scores
        $latestAssessment = $assessments[0];
        $report .= "• Latest assessment: " . strtoupper($latestAssessment['assessment_type']) . 
                   " (Score: " . $latestAssessment['total_score'] . ", " . 
                   ucfirst($latestAssessment['severity_level']) . " severity)\n";
    } else {
        $report .= "• No formal assessments completed\n";
    }
    
    return $report;
}

function generateProgressDetails($sessions) {
    $details = "PROGRESS ANALYSIS\n";
    $details .= "================\n\n";
    
    $assessments = array_filter($sessions, function($s) { return !empty($s['assessment_type']); });
    
    if (!empty($assessments)) {
        $details .= "ASSESSMENT TRENDS\n";
        $details .= "-----------------\n";
        
        $phq9Scores = array_filter($assessments, function($a) { return $a['assessment_type'] === 'phq9'; });
        $gad7Scores = array_filter($assessments, function($a) { return $a['assessment_type'] === 'gad7'; });
        
        if (!empty($phq9Scores)) {
            $latestPHQ9 = $phq9Scores[0];
            $details .= "• PHQ-9 (Depression) - Latest Score: " . $latestPHQ9['total_score'] . " (" . ucfirst($latestPHQ9['severity_level']) . " severity)\n";
            
            if (count($phq9Scores) > 1) {
                $firstPHQ9 = end($phq9Scores);
                $change = $latestPHQ9['total_score'] - $firstPHQ9['total_score'];
                $trend = $change > 0 ? "worsening" : ($change < 0 ? "improving" : "stable");
                $details .= "  - Change from first assessment: " . ($change > 0 ? "+" : "") . $change . " points (" . $trend . ")\n";
            }
        }
        
        if (!empty($gad7Scores)) {
            $latestGAD7 = $gad7Scores[0];
            $details .= "• GAD-7 (Anxiety) - Latest Score: " . $latestGAD7['total_score'] . " (" . ucfirst($latestGAD7['severity_level']) . " severity)\n";
            
            if (count($gad7Scores) > 1) {
                $firstGAD7 = end($gad7Scores);
                $change = $latestGAD7['total_score'] - $firstGAD7['total_score'];
                $trend = $change > 0 ? "worsening" : ($change < 0 ? "improving" : "stable");
                $details .= "  - Change from first assessment: " . ($change > 0 ? "+" : "") . $change . " points (" . $trend . ")\n";
            }
        }
        
        $details .= "\n";
    }
    
    $details .= "TREATMENT COMPLIANCE\n";
    $details .= "-------------------\n";
    $completedSessions = array_filter($sessions, function($s) { return $s['status'] === 'completed'; });
    $completionRate = count($sessions) > 0 ? (count($completedSessions) / count($sessions)) * 100 : 0;
    $details .= "• Session completion rate: " . round($completionRate, 1) . "%\n";
    
    if ($completionRate >= 80) {
        $details .= "• Compliance status: Excellent\n";
    } elseif ($completionRate >= 60) {
        $details .= "• Compliance status: Good\n";
    } elseif ($completionRate >= 40) {
        $details .= "• Compliance status: Fair\n";
    } else {
        $details .= "• Compliance status: Needs improvement\n";
    }
    
    return $details;
}

function generateAdditionalNotes($notes) {
    $additionalNotes = "THERAPIST OBSERVATIONS\n";
    $additionalNotes .= "====================\n\n";
    
    if (!empty($notes)) {
        $additionalNotes .= "RECENT SESSION NOTES\n";
        $additionalNotes .= "--------------------\n";
        foreach (array_slice($notes, 0, 3) as $note) {
            $additionalNotes .= "• " . date('M j, Y', strtotime($note['created_at'])) . ": " . $note['notes'] . "\n";
        }
        $additionalNotes .= "\n";
    } else {
        $additionalNotes .= "No recent session notes available.\n\n";
    }
    
    $additionalNotes .= "TREATMENT RECOMMENDATIONS\n";
    $additionalNotes .= "========================\n";
    $additionalNotes .= "• Continue regular monitoring of client progress\n";
    $additionalNotes .= "• Review assessment trends and adjust treatment plan as needed\n";
    $additionalNotes .= "• Maintain consistent session schedule for optimal outcomes\n";
    $additionalNotes .= "• Consider additional assessments if progress stalls\n";
    $additionalNotes .= "• Document any significant changes in client presentation\n";
    
    return $additionalNotes;
}

$conn->close();
?>
