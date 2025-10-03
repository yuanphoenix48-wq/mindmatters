<?php
// Start output buffering to prevent any output from interfering with headers
ob_start();

session_start();

// Debug logging
error_log("generate_patient_report.php called");
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'not set'));

if (!isset($_SESSION['user_id'])) {
    error_log("Unauthorized access to generate_patient_report.php");
    http_response_code(401);
    exit('Unauthorized');
}

require_once 'connect.php';
require_once 'vendor/autoload.php';

// Support both JSON POST and querystring GET for WebView compatibility
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    error_log("Input received: " . json_encode($input));
    $clientId = isset($input['patient_id']) ? (int)$input['patient_id'] : 0;
    $clientName = isset($input['patient_name']) ? $input['patient_name'] : 'Unknown Client';
} else {
    $clientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
    $clientName = isset($_GET['patient_name']) ? $_GET['patient_name'] : 'Unknown Client';
}

error_log("Client ID: " . $clientId . ", Client Name: " . $clientName);

if (!$clientId) {
    error_log("Invalid client ID provided");
    http_response_code(400);
    exit('Invalid client ID');
}

try {
    $userId = $_SESSION['user_id'];
    
    // Get patient data (reuse the same logic as get_patient_report_data.php)
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
    
    // Ensure client array has required keys with defaults
    $client = array_merge([
        'first_name' => 'Unknown',
        'last_name' => 'Client',
        'email' => 'N/A',
        'total_sessions' => 0,
        'first_session' => null,
        'last_session' => null
    ], $client);
    
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
    
    // Ensure sessions is an array
    if (!is_array($sessions)) {
        $sessions = [];
    }
    
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
    
    // Ensure notes is an array
    if (!is_array($notes)) {
        $notes = [];
    }
    
    // Create TCPDF object
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Mind Matters Therapy Platform');
    $pdf->SetAuthor('Mind Matters');
    $pdf->SetTitle('Client Report - ' . $client['first_name'] . ' ' . $client['last_name']);
    $pdf->SetSubject('Client Treatment Report');
    $pdf->SetKeywords('therapy, client, report, mental health');
    
    // Set default header data
    $pdf->SetHeaderData('', 0, 'CLIENT REPORT', 'Mind Matters Therapy Platform');
    
    // Set header and footer fonts
    $pdf->setHeaderFont(Array('helvetica', '', 10));
    $pdf->setFooterFont(Array('helvetica', '', 8));
    
    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont('courier');
    
    // Set margins
    $pdf->SetMargins(15, 25, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Set image scale factor
    $pdf->setImageScale(1.25);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', 'B', 16);
    
    // Title
    $pdf->Cell(0, 10, 'CLIENT REPORT', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Subtitle
    $pdf->SetFont('helvetica', 'I', 12);
    $pdf->Cell(0, 10, 'Generated for: ' . $client['first_name'] . ' ' . $client['last_name'], 0, 1, 'C');
    $pdf->Ln(10);
    
    // Client Information
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'CLIENT INFORMATION', 0, 1, 'L');
    $pdf->Ln(5);
    
    $clientInfo = [
        'Client Name' => $client['first_name'] . ' ' . $client['last_name'],
        'Email Address' => $client['email'],
        'Total Sessions' => $client['total_sessions'],
        'First Session' => $client['first_session'] ? date('M j, Y', strtotime($client['first_session']) ?: time()) : 'N/A',
        'Last Session' => $client['last_session'] ? date('M j, Y', strtotime($client['last_session']) ?: time()) : 'N/A',
        'Report Generated' => date('M j, Y \a\t g:i A')
    ];
    
    $pdf->SetFont('helvetica', '', 11);
    foreach ($clientInfo as $label => $value) {
        $pdf->Cell(0, 8, $label . ': ' . $value, 0, 1, 'L');
    }
    
    $pdf->Ln(10);
    
    // Overall Report Section
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'TREATMENT OVERVIEW', 0, 1, 'L');
    $pdf->Ln(5);
    
    $overallReport = generateOverallReport($client, $sessions, $notes);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->MultiCell(0, 6, $overallReport, 0, 'L');
    
    $pdf->Ln(10);
    
    // Process assessment data for trends
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
    
    // Progress Details Section
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'PROGRESS ANALYSIS', 0, 1, 'L');
    $pdf->Ln(5);
    
    $progressDetails = generateProgressDetails($assessmentHistory, $overallMoodTrend, $stressLevels, $anxietyLevels);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->MultiCell(0, 6, $progressDetails, 0, 'L');
    
    $pdf->Ln(10);
    
    // Assessment Summary Section
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'ASSESSMENT SUMMARY', 0, 1, 'L');
    $pdf->Ln(5);
    
    $assessments = array_filter($sessions, function($s) { return !empty($s['assessment_type']); });
    if (!empty($assessments)) {
        $assessmentCount = 0;
        foreach (array_slice($assessments, 0, 5) as $assessment) {
            $assessmentCount++;
            $assessmentDate = date('M j, Y', strtotime($assessment['session_date']) ?: time());
            $assessmentTime = date('g:i A', strtotime($assessment['session_time']) ?: time());
            
            // Assessment header
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, "Assessment #{$assessmentCount} - {$assessmentDate}", 0, 1, 'L');
            
            // Basic assessment info
            $pdf->SetFont('helvetica', 'B', 10);
            if ($assessment['assessment_type'] === 'pre_session') {
                $pdf->Cell(0, 6, "Assessment Type: " . strtoupper($assessment['assessment_type']) . " (Pre-Session Questionnaire)", 0, 1, 'L');
            } elseif ($assessment['assessment_type'] === 'post_session') {
                $pdf->Cell(0, 6, "Assessment Type: " . strtoupper($assessment['assessment_type']) . " (Post-Session Questionnaire)", 0, 1, 'L');
            } else {
                $severityText = $assessment['severity_level'] ? ucfirst($assessment['severity_level']) : 'Not assessed';
                $pdf->Cell(0, 6, "Assessment Type: " . strtoupper($assessment['assessment_type']) . 
                            " (Score: {$assessment['total_score']}, Severity: {$severityText})", 0, 1, 'L');
            }
            
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 6, "Date & Time: {$assessmentDate} at {$assessmentTime}", 0, 1, 'L');
            
            // Add detailed metrics if available
            if ($assessment['assessment_data']) {
                $assessmentData = json_decode($assessment['assessment_data'], true);
                if ($assessmentData) {
                    $pdf->SetFont('helvetica', 'B', 10);
                    $pdf->Cell(0, 6, "Detailed Metrics:", 0, 1, 'L');
                    
                    $metricsText = '';
                    
                    // Handle pre-session assessment data
                    if ($assessment['assessment_type'] === 'pre_session') {
                        if (isset($assessmentData['mood_emoji'])) {
                            $metricsText .= "• Mood: {$assessmentData['mood_emoji']} ";
                        }
                        if (isset($assessmentData['mood_rating'])) {
                            $moodStatus = $assessmentData['mood_rating'] >= 7 ? 'Good' : ($assessmentData['mood_rating'] >= 4 ? 'Moderate' : 'Low');
                            $metricsText .= "({$assessmentData['mood_rating']}/10 - {$moodStatus})\n";
                        }
                        if (isset($assessmentData['stress_level'])) {
                            $stressStatus = $assessmentData['stress_level'] >= 7 ? 'High' : ($assessmentData['stress_level'] >= 4 ? 'Moderate' : 'Low');
                            $metricsText .= "• Stress Level: {$assessmentData['stress_level']}/10 ({$stressStatus})\n";
                        }
                        if (isset($assessmentData['anxiety_level'])) {
                            $anxietyStatus = $assessmentData['anxiety_level'] >= 7 ? 'High' : ($assessmentData['anxiety_level'] >= 4 ? 'Moderate' : 'Low');
                            $metricsText .= "• Anxiety Level: {$assessmentData['anxiety_level']}/10 ({$anxietyStatus})\n";
                        }
                        if (isset($assessmentData['sleep_hours'])) {
                            $metricsText .= "• Sleep Hours: {$assessmentData['sleep_hours']} hours\n";
                        }
                        if (isset($assessmentData['concerns']) && !empty($assessmentData['concerns'])) {
                            $metricsText .= "• Concerns: " . substr($assessmentData['concerns'], 0, 100) . (strlen($assessmentData['concerns']) > 100 ? '...' : '') . "\n";
                        }
                    } elseif ($assessment['assessment_type'] === 'post_session') {
                        // Handle post-session assessment data
                        if (isset($assessmentData['post_mood_emoji'])) {
                            $metricsText .= "• Post-Session Mood: {$assessmentData['post_mood_emoji']} ";
                        }
                        if (isset($assessmentData['post_mood_rating'])) {
                            $moodStatus = $assessmentData['post_mood_rating'] >= 7 ? 'Good' : ($assessmentData['post_mood_rating'] >= 4 ? 'Moderate' : 'Low');
                            $metricsText .= "({$assessmentData['post_mood_rating']}/10 - {$moodStatus})\n";
                        }
                        if (isset($assessmentData['post_stress_level'])) {
                            $stressStatus = $assessmentData['post_stress_level'] >= 7 ? 'High' : ($assessmentData['post_stress_level'] >= 4 ? 'Moderate' : 'Low');
                            $metricsText .= "• Post-Session Stress: {$assessmentData['post_stress_level']}/10 ({$stressStatus})\n";
                        }
                        if (isset($assessmentData['post_anxiety_level'])) {
                            $anxietyStatus = $assessmentData['post_anxiety_level'] >= 7 ? 'High' : ($assessmentData['post_anxiety_level'] >= 4 ? 'Moderate' : 'Low');
                            $metricsText .= "• Post-Session Anxiety: {$assessmentData['post_anxiety_level']}/10 ({$anxietyStatus})\n";
                        }
                        if (isset($assessmentData['addressed_concerns'])) {
                            $metricsText .= "• Concerns Addressed: {$assessmentData['addressed_concerns']}\n";
                        }
                        if (isset($assessmentData['post_most_helpful']) && !empty($assessmentData['post_most_helpful'])) {
                            $metricsText .= "• Most Helpful: " . substr($assessmentData['post_most_helpful'], 0, 100) . (strlen($assessmentData['post_most_helpful']) > 100 ? '...' : '') . "\n";
                        }
                        if (isset($assessmentData['post_follow_up_needs']) && !empty($assessmentData['post_follow_up_needs'])) {
                            $metricsText .= "• Follow-up Needs: " . substr($assessmentData['post_follow_up_needs'], 0, 100) . (strlen($assessmentData['post_follow_up_needs']) > 100 ? '...' : '') . "\n";
                        }
                    } else {
                        // Handle other assessment types (PHQ-9, GAD-7, etc.)
                        if (isset($assessmentData['mood_rating'])) {
                            $moodEmoji = $assessmentData['mood_rating'] >= 7 ? '😊' : ($assessmentData['mood_rating'] >= 4 ? '😐' : '😔');
                            $moodStatus = $assessmentData['mood_rating'] >= 7 ? 'Good' : ($assessmentData['mood_rating'] >= 4 ? 'Moderate' : 'Low');
                            $metricsText .= "• Mood: {$assessmentData['mood_rating']}/10 ({$moodStatus}) {$moodEmoji}\n";
                        }
                        if (isset($assessmentData['stress_level'])) {
                            $stressEmoji = $assessmentData['stress_level'] >= 7 ? '😰' : ($assessmentData['stress_level'] >= 4 ? '😟' : '😌');
                            $stressStatus = $assessmentData['stress_level'] >= 7 ? 'High' : ($assessmentData['stress_level'] >= 4 ? 'Moderate' : 'Low');
                            $metricsText .= "• Stress: {$assessmentData['stress_level']}/10 ({$stressStatus}) {$stressEmoji}\n";
                        }
                        if (isset($assessmentData['anxiety_level'])) {
                            $anxietyEmoji = $assessmentData['anxiety_level'] >= 7 ? '😨' : ($assessmentData['anxiety_level'] >= 4 ? '😟' : '😌');
                            $anxietyStatus = $assessmentData['anxiety_level'] >= 7 ? 'High' : ($assessmentData['anxiety_level'] >= 4 ? 'Moderate' : 'Low');
                            $metricsText .= "• Anxiety: {$assessmentData['anxiety_level']}/10 ({$anxietyStatus}) {$anxietyEmoji}\n";
                        }
                    }
                    
                    if ($metricsText) {
                        $pdf->SetFont('helvetica', '', 10);
                        $pdf->MultiCell(0, 5, $metricsText, 0, 'L');
                    }
                }
            }
            
            $pdf->Ln(5);
        }
    } else {
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->Cell(0, 6, 'No assessment data available.', 0, 1, 'L');
    }
    
    $pdf->Ln(10);
    
    // Session History Section
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'SESSION HISTORY', 0, 1, 'L');
    $pdf->Ln(5);
    
    if (!empty($sessions)) {
        $sessionCount = 0;
        foreach (array_slice($sessions, 0, 10) as $session) {
            $sessionCount++;
            $sessionDate = date('M j, Y', strtotime($session['session_date']) ?: time());
            $sessionTime = date('g:i A', strtotime($session['session_time']) ?: time());
            $status = ucfirst($session['status']);
            
            // Session header
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, "Session #{$sessionCount} - {$sessionDate}", 0, 1, 'L');
            
            // Basic session info
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 6, "Date & Time: {$sessionDate} at {$sessionTime}", 0, 1, 'L');
            $pdf->Cell(0, 6, "Status: {$status}", 0, 1, 'L');
            
            if ($session['notes']) {
                $pdf->Cell(0, 6, "Notes: {$session['notes']}", 0, 1, 'L');
            }
            
            if ($session['assessment_type']) {
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->Cell(0, 6, "Assessment: " . strtoupper($session['assessment_type']) . 
                            " (Score: {$session['total_score']}, Severity: " . ucfirst($session['severity_level']) . ")", 0, 1, 'L');
                
                // Add detailed assessment metrics if available
                if ($session['assessment_data']) {
                    $assessmentData = json_decode($session['assessment_data'], true);
                    if ($assessmentData) {
                        $metricsText = '';
                        if (isset($assessmentData['mood_rating'])) {
                            $moodEmoji = $assessmentData['mood_rating'] >= 7 ? '😊' : ($assessmentData['mood_rating'] >= 4 ? '😐' : '😔');
                            $metricsText .= "Mood: {$assessmentData['mood_rating']}/10 {$moodEmoji}";
                        }
                        if (isset($assessmentData['stress_level'])) {
                            $stressEmoji = $assessmentData['stress_level'] >= 7 ? '😰' : ($assessmentData['stress_level'] >= 4 ? '😟' : '😌');
                            $metricsText .= ($metricsText ? ' | ' : '') . "Stress: {$assessmentData['stress_level']}/10 {$stressEmoji}";
                        }
                        if (isset($assessmentData['anxiety_level'])) {
                            $anxietyEmoji = $assessmentData['anxiety_level'] >= 7 ? '😨' : ($assessmentData['anxiety_level'] >= 4 ? '😟' : '😌');
                            $metricsText .= ($metricsText ? ' | ' : '') . "Anxiety: {$assessmentData['anxiety_level']}/10 {$anxietyEmoji}";
                        }
                        
                        if ($metricsText) {
                            $pdf->SetFont('helvetica', '', 10);
                            $pdf->Cell(0, 6, "Detailed Metrics: {$metricsText}", 0, 1, 'L');
                        }
                    }
                }
            }
            
            $pdf->Ln(5);
        }
    } else {
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->Cell(0, 6, 'No session history available.', 0, 1, 'L');
    }
    
    $pdf->Ln(10);
    
    // Additional Notes Section
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'ADDITIONAL NOTES', 0, 1, 'L');
    $pdf->Ln(5);
    
    $additionalNotes = generateAdditionalNotes($notes);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->MultiCell(0, 6, $additionalNotes, 0, 'L');
    
    $pdf->Ln(10);
    
    // Footer
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->Cell(0, 6, 'Report generated by Mind Matters', 0, 1, 'C');
    
    // Generate the PDF
    $filename = "client_report_{$clientId}_" . date('Y-m-d_H-i-s') . ".pdf";
    
    // Clear all output buffers to ensure clean PDF output
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('X-Content-Type-Options: nosniff');
    header('Access-Control-Expose-Headers: Content-Disposition, Content-Type');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    header('Expires: 0');
    
    // Output PDF (force download)
    $pdf->Output($filename, 'D');
    
} catch (Exception $e) {
    error_log("Error in generate_patient_report.php: " . $e->getMessage());
    http_response_code(500);
    // Clear all output buffers before sending error response
    while (ob_get_level()) {
        ob_end_clean();
    }
    exit('Report generation failed: ' . $e->getMessage());
}

function generateOverallReport($client, $sessions, $notes) {
    $report = "This comprehensive report provides an overview of the client's treatment progress, assessment history, and therapeutic journey.\n\n";
    
    $report .= "TREATMENT SUMMARY\n";
    $report .= "================\n";
    $report .= "• Total sessions conducted: " . count($sessions) . "\n";
    $report .= "• Treatment period: " . ($client['first_session'] ? date('M j, Y', strtotime($client['first_session']) ?: time()) : 'N/A') . 
               " to " . ($client['last_session'] ? date('M j, Y', strtotime($client['last_session']) ?: time()) : 'N/A') . "\n";
    
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
        if (!empty($assessments)) {
            $latestAssessment = $assessments[0];
            $report .= "• Latest assessment: " . strtoupper($latestAssessment['assessment_type']) . 
                       " (Score: " . $latestAssessment['total_score'] . ", " . 
                       ucfirst($latestAssessment['severity_level']) . " severity)\n";
        }
    } else {
        $report .= "• No formal assessments completed\n";
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

function generateAdditionalNotes($notes) {
    $additionalNotes = "THERAPIST OBSERVATIONS\n";
    $additionalNotes .= "====================\n\n";
    
    if (!empty($notes)) {
        $additionalNotes .= "RECENT SESSION NOTES\n";
        $additionalNotes .= "--------------------\n";
        foreach (array_slice($notes, 0, 3) as $note) {
            $additionalNotes .= "• " . date('M j, Y', strtotime($note['created_at']) ?: time()) . ": " . $note['notes'] . "\n";
        }
        $additionalNotes .= "\n";
    } else {
        $additionalNotes .= "No recent session notes available.\n\n";
    }
    
    return $additionalNotes;
}

$conn->close();
?>
