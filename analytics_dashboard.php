<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'connect.php';

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'client';
// Ensure pagination vars exist for both roles to avoid undefined warnings in the view
$activityPage = isset($_GET['activity_page']) ? max(1, (int)$_GET['activity_page']) : 1;
$feedbackPage = isset($_GET['feedback_page']) ? max(1, (int)$_GET['feedback_page']) : 1;

// Get user's profile picture
$sql = "SELECT profile_picture, gender FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$profilePicture = $user['profile_picture'] ?? ($user['gender'] === 'female' ? 'images/profile/default_images/female_gender.png' : 'images/profile/default_images/male_gender.png');
$stmt->close();

// Get analytics data based on user role
if ($userRole === 'client') {
    // client analytics
    $sql = "SELECT 
                COUNT(*) as total_sessions,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_sessions
            FROM sessions s
            WHERE s.client_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $overviewStats = $result->fetch_assoc();
    $stmt->close();

    // Get pre-session assessments (last 30 days) as mood source
    $sql = "SELECT assessment_data, completed_at 
            FROM mental_health_assessments 
            WHERE client_id = ? AND assessment_type = 'pre_session' AND completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY completed_at ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $moodTrends = [];
    $avgMoodSum = 0; $avgStressSum = 0; $avgAnxietySum = 0; $avgSleepSum = 0; $avgCount = 0;
    while ($row = $res->fetch_assoc()) {
        $data = json_decode($row['assessment_data'] ?? '{}', true) ?: [];
        $logDate = $row['completed_at'];
        $mood = isset($data['mood_rating']) ? (int)$data['mood_rating'] : null;
        $stress = isset($data['stress_level']) ? (int)$data['stress_level'] : null;
        $anxiety = isset($data['anxiety_level']) ? (int)$data['anxiety_level'] : null;
        $sleep = isset($data['sleep_hours']) ? (float)$data['sleep_hours'] : null;
        $moodTrends[] = [
            'log_date' => $logDate,
            'mood_rating' => $mood,
            'stress_level' => $stress,
            'anxiety_level' => $anxiety,
            'sleep_hours' => $sleep,
        ];
        if ($mood !== null) $avgMoodSum += $mood;
        if ($stress !== null) $avgStressSum += $stress;
        if ($anxiety !== null) $avgAnxietySum += $anxiety;
        if ($sleep !== null) $avgSleepSum += $sleep;
        $avgCount++;
    }
    $stmt->close();
    // Derive averages similar to previous overview
    $overviewStats['avg_mood'] = $avgCount ? ($avgMoodSum / $avgCount) : 0;
    $overviewStats['avg_stress'] = $avgCount ? ($avgStressSum / $avgCount) : 0;
    $overviewStats['avg_anxiety'] = $avgCount ? ($avgAnxietySum / $avgCount) : 0;
    $overviewStats['avg_sleep'] = $avgCount ? ($avgSleepSum / $avgCount) : 0;

    // Get assessment trends
    $sql = "SELECT assessment_type, total_score, severity_level, completed_at 
            FROM mental_health_assessments 
            WHERE client_id = ? 
            ORDER BY completed_at DESC 
            LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $assessments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Recent therapist feedback for the client (paginated)
    $perPage = 5;
    $feedbackOffset = ($feedbackPage - 1) * $perPage;
    // Recent activity pagination (min 5)
    $perPageActivity = 5;
    $activityOffset = ($activityPage - 1) * $perPageActivity;
    // Detect feedback table name
    $tfTable = 'therapist_feedback';
    $resTbl = $conn->query("SHOW TABLES LIKE 'therapist_feedback'");
    if (!$resTbl || $resTbl->num_rows === 0) {
        $resTbl2 = $conn->query("SHOW TABLES LIKE 'doctor_feedback'");
        if ($resTbl2 && $resTbl2->num_rows > 0) { $tfTable = 'doctor_feedback'; }
        else { $tfTable = null; }
    }

    $recentTherapistFeedback = [];
    if ($tfTable) {
        $sql = "SELECT df.id, df.session_id, COALESCE(df.created_at, s.session_date) AS created_at,
                       s.session_date, s.session_time,
                       CONCAT(t.first_name, ' ', t.last_name) AS therapist_name
                FROM $tfTable df
                JOIN sessions s ON s.id = df.session_id
                JOIN users t ON t.id = df.therapist_id
                WHERE df.client_id = ?
                ORDER BY df.id DESC
                LIMIT ? OFFSET ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("iii", $userId, $perPage, $feedbackOffset);
            $stmt->execute();
            $res = $stmt->get_result();
            $recentTherapistFeedback = $res->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }

    // Get recent activity (Mood from pre_session + assessments)
    $recentActivity = [];
    // Pre-session as Mood Log
    $sql = "SELECT assessment_data, completed_at 
            FROM mental_health_assessments 
            WHERE client_id = ? AND assessment_type = 'pre_session' AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $data = json_decode($row['assessment_data'] ?? '{}', true) ?: [];
            $mr = isset($data['mood_rating']) ? (int)$data['mood_rating'] : null;
            $recentActivity[] = [
                'type' => 'mood',
                'date' => $row['completed_at'],
                'description' => 'Mood: ' . ($mr !== null ? $mr : '-') . '/10'
            ];
        }
        $stmt->close();
    }
    $sql = "SELECT 'assessment' as type, completed_at as date, CONCAT(assessment_type, ' assessment') as description
            FROM mental_health_assessments 
            WHERE client_id = ? AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $recentActivity = array_merge($recentActivity, $res->fetch_all(MYSQLI_ASSOC));
        $stmt->close();
    }
    // Sort and paginate to 5 per page (client view)
    usort($recentActivity, function($a,$b){ return strtotime($b['date']) - strtotime($a['date']); });
    $recentActivity = array_slice($recentActivity, $activityOffset, $perPageActivity);

} else {
    // therapist analytics
    $perPage = 5;
    $activityPage = isset($_GET['activity_page']) ? max(1, (int)$_GET['activity_page']) : 1;
    $feedbackPage = isset($_GET['feedback_page']) ? max(1, (int)$_GET['feedback_page']) : 1;
    $activityOffset = ($activityPage - 1) * $perPage;
    $feedbackOffset = ($feedbackPage - 1) * $perPage;
    $sql = "SELECT 
                COUNT(DISTINCT s.client_id) as total_patients,
                COUNT(s.id) as total_sessions,
                SUM(CASE WHEN s.status = 'completed' THEN 1 ELSE 0 END) as completed_sessions,
                AVG(sf.session_rating) as avg_session_rating,
                AVG(sf.helpfulness_rating) as avg_helpfulness_rating
            FROM sessions s
            LEFT JOIN student_feedback sf ON s.id = sf.session_id
            WHERE s.therapist_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $overviewStats = $result->fetch_assoc();
    $stmt->close();

    // Mood Trends (therapist view) removed per request
    $moodTrends = [];

    // Get progress status distribution
    $sql = "SELECT 
                progress_status,
                COUNT(*) as count
            FROM doctor_notes 
            WHERE therapist_id = ? 
            GROUP BY progress_status";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $progressStats = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Get recent activity (paginated)
    $sql = "SELECT 'session' as type, s.session_date as date, CONCAT('Session with ', u.first_name, ' ', u.last_name) as description
            FROM sessions s
            JOIN users u ON s.client_id = u.id
            WHERE s.therapist_id = ? AND s.session_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            UNION ALL
            SELECT 'note' as type, dn.created_at as date, CONCAT('Notes for ', u.first_name, ' ', u.last_name) as description
            FROM doctor_notes dn
            JOIN users u ON dn.client_id = u.id
            WHERE dn.therapist_id = ? AND dn.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ORDER BY date DESC 
            LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $userId, $userId, $perPage, $activityOffset);
    $stmt->execute();
    $result = $stmt->get_result();
    $recentActivity = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Get recent client feedback for this therapist (paginated)
    $sql = "SELECT sf.id, sf.session_id, sf.session_rating, sf.helpfulness_rating, sf.mood_after_session, sf.what_went_well, sf.what_can_improve, sf.additional_comments, sf.is_anonymous,
                   COALESCE(sf.created_at, s.session_date) AS created_at,
                   CONCAT(u.first_name, ' ', u.last_name) AS client_name,
                   s.session_date, s.session_time
            FROM student_feedback sf
            JOIN sessions s ON s.id = sf.session_id
            JOIN users u ON u.id = sf.client_id
            WHERE sf.therapist_id = ?
            ORDER BY sf.id DESC
            LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $userId, $perPage, $feedbackOffset);
    $stmt->execute();
    $result = $stmt->get_result();
    $recentFeedback = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - Mind Matters</title>
    <link rel="stylesheet" href="styles/global.css">
    <link rel="stylesheet" href="styles/dashboard.css">
    <link rel="stylesheet" href="styles/analytics_dashboard.css">
    <link rel="stylesheet" href="styles/notifications.css">
    <link rel="stylesheet" href="styles/mobile.css">
    <script src="js/notifications.js"></script>
    <script src="js/mobile.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Pagination Styling */
        .pagination-btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            margin: 0 0.25rem;
            background: linear-gradient(135deg, #1D5D9B, #14487a);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            min-width: 44px;
            min-height: 44px;
            touch-action: manipulation;
        }

        .pagination-btn:hover {
            background: linear-gradient(135deg, #14487a, #0d3a5f);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(29, 93, 155, 0.3);
            color: white;
            text-decoration: none;
        }

        .pagination-btn:active {
            transform: translateY(0);
        }

        .pagination-info {
            display: inline-block;
            padding: 0.5rem 1rem;
            margin: 0 0.25rem;
            background: #f8f9fa;
            color: #333;
            border-radius: 8px;
            font-weight: 600;
            border: 1px solid #e9ecef;
        }

        /* Mobile Pagination */
        @media (max-width: 768px) {
            .pagination-btn {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
                margin: 0.25rem;
            }
            
            .pagination-info {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
                margin: 0.25rem;
            }
        }
        
        /* Hide mobile header on desktop by default */
        .mobile-header {
            display: none;
        }

        /* Mobile header positioning */
        @media (max-width: 768px) {
            .mobile-header {
                display: block !important;
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                width: 100% !important;
                z-index: 1000 !important;
                background: linear-gradient(135deg, #1D5D9B 0%, #14487a 100%) !important;
                color: white !important;
                padding: 1rem !important;
                /* removed extra left padding since button inside header */
                padding-right: 1rem !important;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1) !important;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
                box-sizing: border-box !important;
            }
            
            .mobile-header-content {
                display: grid !important;
                grid-template-columns: 44px 1fr auto !important;
                align-items: center !important;
                width: 100% !important;
                max-width: 100% !important;
                overflow: hidden !important;
                gap: 0.75rem !important;
            }
            
            .mobile-logo {
                font-size: 1.4rem !important;
                font-weight: 700 !important;
                color: var(--white) !important;
                font-family: 'Lora', serif !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                justify-self: center !important;
                text-align: center !important;
            }
            
            .mobile-menu-btn { position: relative !important; top: 0 !important; left: 0 !important; z-index: 1 !important; background: var(--primary-color) !important; border: none !important; border-radius: 8px !important; width: 44px !important; height: 44px !important; display: flex !important; align-items: center !important; justify-content: center !important; cursor: pointer !important; box-shadow: 0 4px 12px rgba(0,0,0,0.2) !important; transition: all 0.3s ease !important; padding: 0 !important; outline: none !important; }
            .mobile-menu-btn .hamburger { width: 20px; height: 2px; background: #fff; margin: 2px 0; transition: all 0.3s ease; border-radius: 1px; }
            .mobile-menu-btn.active .hamburger:nth-child(1) { transform: rotate(45deg) translate(5px, 5px); }
            .mobile-menu-btn.active .hamburger:nth-child(2) { opacity: 0; }
            .mobile-menu-btn.active .hamburger:nth-child(3) { transform: rotate(-45deg) translate(7px, -6px); }
            
            .mobile-menu-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; opacity: 0; transition: opacity 0.3s ease; }
            .mobile-menu-overlay.active { display: block; opacity: 1; }
            
            .dbSidebar { transform: translateX(-100%); transition: transform 0.3s cubic-bezier(0.4,0,0.2,1); width: 280px; z-index: 10001; position: fixed; top: 0; left: 0; height: 100vh; overflow-y: auto; }
            .dbSidebar.mobile-open { transform: translateX(0); }
            .mobile-menu-open .dbSidebar { transform: translateX(0); }
            .mobile-menu-open { overflow: hidden; }
            .mobile-menu-open .dbMainContent { pointer-events: none; }
            
            .mobile-nav-toggle {
                position: fixed !important;
                top: 1rem !important;
                left: 1rem !important;
                z-index: 1001 !important;
                background: rgba(255, 255, 255, 0.2) !important;
                color: white !important;
                border: none !important;
                border-radius: 50% !important;
                width: 48px !important;
                height: 48px !important;
                display: none !important;
                align-items: center !important;
                justify-content: center !important;
                font-size: 1.2rem !important;
            }

            /* Add top padding to main content to account for fixed header */
            .dbMainContent {
                padding-top: 80px !important;
            }
            
            .analytics-container {
                padding: 1rem !important;
            }
        }
        /* Hide sidebar when modal open (match messages behavior) */
        @media (max-width: 768px) {
            body.modal-open .dbSidebar { transform: translateX(-100%) !important; }
            body.modal-open #mobileMenuOverlay { display: none !important; opacity: 0 !important; }
        }
        /* Therapist Feedback Modal */
        #tfModal {
            display: none;
            position: fixed;
            z-index: 12000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            -webkit-backdrop-filter: blur(2px);
            backdrop-filter: blur(2px);
            align-items: center;
            justify-content: center;
            padding: 16px;
            box-sizing: border-box;
        }
        #tfModal.show { display: flex; }
        #tfModal .modal-content {
            width: 92%;
            max-width: 640px;
            border-radius: 16px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            box-shadow: 0 24px 80px rgba(0,0,0,0.18);
            padding: 0;
            border: 1px solid rgba(20,72,122,0.08);
            overflow: hidden;
            transform: translateY(12px) scale(0.98);
            opacity: 0;
            transition: opacity .25s ease, transform .25s ease;
        }
        #tfModal.show .modal-content { opacity: 1; transform: translateY(0) scale(1); }
        #tfModal .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            background: linear-gradient(135deg, #1D5D9B, #14487a);
            color: #ffffff;
        }
        #tfModal .modal-header h3 { color: #ffffff !important; font-size: 1.05rem; line-height: 1.2; margin: 0; letter-spacing: .2px; }
        #tfModal .modal-header .close {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: background .2s ease, transform .12s ease;
        }
        #tfModal .modal-header .close:hover { background: rgba(255,255,255,0.15); }
        #tfModal .modal-header .close:active { transform: scale(0.96); }
        #tfModal .modal-body { padding: 20px; color: #2c3e50; }
        #tfModal .tf-meta { display: grid; grid-template-columns: 1fr; gap: 8px; margin-bottom: 14px; font-size: 0.95rem; }
        #tfModal .tf-meta .tf-meta-item { display: flex; gap: 8px; align-items: center; color: #334155; }
        #tfModal .tf-summary {
            background: linear-gradient(135deg, #f8fafc, #eef2f7);
            border: 1px solid #e5ebf3;
            padding: 16px;
            border-radius: 12px;
            line-height: 1.6;
            color: #2c3e50;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04) inset;
        }
        #tfModal .tf-empty { color: #6b7280; font-style: italic; }
        @media (max-width: 480px) { #tfModal .modal-header h3 { font-size: 1rem; } }

        /* Client Feedback Modal (therapist view) - enhanced */
        #feedbackModal {
            display: none;
            position: fixed;
            z-index: 12000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            -webkit-backdrop-filter: blur(2px);
            backdrop-filter: blur(2px);
            align-items: center;
            justify-content: center;
            padding: 16px;
            box-sizing: border-box;
        }
        #feedbackModal.show { display: flex; }
        #feedbackModal .modal-content {
            width: 92%;
            max-width: 680px;
            border-radius: 16px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            border: 1px solid rgba(20,72,122,0.08);
            box-shadow: 0 24px 80px rgba(0,0,0,0.18);
            overflow: hidden;
            transform: translateY(12px) scale(0.98);
            opacity: 0;
            transition: opacity .25s ease, transform .25s ease;
        }
        #feedbackModal.show .modal-content { opacity: 1; transform: translateY(0) scale(1); }
        #feedbackModal .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            background: linear-gradient(135deg, #1D5D9B, #14487a);
            color: #ffffff;
        }
        #feedbackModal .modal-header h3 { margin: 0; font-size: 1.1rem; letter-spacing: .2px; color: #ffffff !important; }
        #feedbackModal .modal-header .close { cursor: pointer; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; transition: background .2s ease, transform .12s ease; }
        #feedbackModal .modal-header .close:hover { background: rgba(255,255,255,0.15); }
        #feedbackModal .modal-header .close:active { transform: scale(0.96); }
        #feedbackModal .modal-body { padding: 20px; color: #2c3e50; }
        #feedbackModal .row { margin-bottom: 12px; line-height: 1.6; }
        #feedbackModal .label { display: inline-block; min-width: 140px; font-weight: 800; color: #1D3557; }
        #feedbackModal .row + .row { border-top: 1px dashed #e5ebf3; padding-top: 12px; }
        @media (max-width: 480px) {
            #feedbackModal .modal-header h3 { font-size: 1rem; }
            #feedbackModal .label { min-width: 110px; }
        }

        /* Custom Confirm Modal */
        #logoutConfirmModal {
            display: none;
            position: fixed;
            z-index: 3000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }
        #logoutConfirmModal.show { display: flex; }
        #logoutConfirmModal .modal-content {
            width: 90%;
            max-width: 460px;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            padding: 0;
            border: none;
            overflow: hidden;
        }
        #logoutConfirmModal .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            background: linear-gradient(135deg, #1D5D9B, #14487a);
            color: #ffffff;
        }
        #logoutConfirmModal .modal-header h3 { color: #ffffff !important; font-size: 1rem; line-height: 1.2; margin: 0; }
        #logoutConfirmModal .modal-body { padding: 20px; color: #333; }
        #logoutConfirmModal .modal-actions { display: flex; gap: 10px; justify-content: flex-end; padding: 0 20px 20px 20px; }
        #logoutConfirmModal .modal-actions .cancel-btn,
        #logoutConfirmModal .modal-actions .submit-btn {
            appearance: none;
            -webkit-appearance: none;
            border: 0;
            border-radius: 10px;
            padding: 0.65rem 1.1rem;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: transform .12s ease, box-shadow .2s ease, background .2s ease, color .2s ease, opacity .2s ease;
            outline: none;
            min-width: 96px;
        }
        #logoutConfirmModal .modal-actions .cancel-btn { background: #f1f3f5; color: #1D3557; box-shadow: 0 1px 2px rgba(0,0,0,0.06) inset; }
        #logoutConfirmModal .modal-actions .cancel-btn:hover { background: #e9ecef; }
        #logoutConfirmModal .modal-actions .cancel-btn:active { transform: translateY(1px); }
        #logoutConfirmModal .modal-actions .cancel-btn:focus-visible { box-shadow: 0 0 0 3px rgba(29,93,155,0.25); }
        #logoutConfirmModal .modal-actions .submit-btn { background: linear-gradient(135deg, #1D5D9B, #14487a); color: #ffffff; box-shadow: 0 6px 16px rgba(29,93,155,0.25); }
        #logoutConfirmModal .modal-actions .submit-btn:hover { background: linear-gradient(135deg, #14487a, #0d3a5f); }
        #logoutConfirmModal .modal-actions .submit-btn:active { transform: translateY(1px); }
        #logoutConfirmModal .modal-actions .submit-btn:focus-visible { box-shadow: 0 0 0 3px rgba(29,93,155,0.35); }
        @media (max-width: 480px) { #logoutConfirmModal .modal-actions .cancel-btn, #logoutConfirmModal .modal-actions .submit-btn { min-width: 0; padding: 0.7rem 1rem; font-size: 1rem; } }
    </style>
</head>
<body class="dbBody">
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="mobile-header-content">
            <button class="mobile-menu-btn" id="mobileMenuBtn" onclick="toggleMobileMenu()">
                <span class="hamburger"></span>
                <span class="hamburger"></span>
                <span class="hamburger"></span>
            </button>
            <div class="mobile-logo">Mind Matters</div>
            <div class="mobile-user-info">
                <img src="<?php echo htmlspecialchars($profilePicture); ?>" alt="Profile Picture" class="mobile-user-avatar">
                <span class="mobile-user-name"><?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
            </div>
        </div>
    </div>

    <!-- Read-only Therapist Feedback Modal -->
    <div id="tfModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Therapist Feedback</h3>
                <span class="close" onclick="closeTfModal()" style="cursor:pointer">&times;</span>
            </div>
            <div class="modal-body">
                <div id="tfBody">Loading...</div>
            </div>
        </div>
    </div>

    <!-- Client Feedback Modal (Therapist View) -->
    <div id="feedbackModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Client Feedback</h3>
                <span class="close" onclick="closeFeedbackModal()">&times;</span>
            </div>
            <div class="modal-body" id="feedbackBody">Loading...</div>
        </div>
    </div>

    <!-- Mobile Menu Overlay -->
    <div id="mobileMenuOverlay" class="mobile-menu-overlay" onclick="closeMobileMenu()"></div>

    <div class="dbContainer">
        <div class="dbSidebar">
            <div class="sidebarProfile">
                <img src="<?php echo htmlspecialchars($profilePicture); ?>" alt="Profile Picture" class="defaultPicture" id="profilePic">
                <h1 class="profileName"><?php echo htmlspecialchars($_SESSION['first_name']); ?></h1>
                <p class="userRole"><?php echo ucfirst($userRole); ?></p>
            </div>
            <ul class="sidebarNavList">
                <?php if ($userRole === 'client'): ?>
                    <li class="sidebarNavItem"><a href="dashboard.php" class="sidebarNavLink">Home</a></li>
                    <li class="sidebarNavItem"><a href="my_session.php" class="sidebarNavLink">My Sessions</a></li>
                    <li class="sidebarNavItem"><a href="analytics_dashboard.php" class="sidebarNavLink active">Analytics</a></li>
                    <li class="sidebarNavItem"><a href="resources.php" class="sidebarNavLink">Resources and Guide</a></li>
                    <li class="sidebarNavItem"><a href="community_forum.php" class="sidebarNavLink">Community Forum</a></li>
                    <li class="sidebarNavItem"><a href="therapy_support.php" class="sidebarNavLink">Therapy Support</a></li>
                    <li class="sidebarNavItem"><a href="student_messages.php" class="sidebarNavLink">Messages</a></li>
                    <li class="sidebarNavItem"><a href="profile_settings.php" class="sidebarNavLink">Settings</a></li>
                <?php else: ?>
                    <li class="sidebarNavItem"><a href="dashboard.php" class="sidebarNavLink">Home</a></li>
                    <li class="sidebarNavItem"><a href="appointments.php" class="sidebarNavLink">Appointments</a></li>
                    <li class="sidebarNavItem"><a href="pending_requests.php" class="sidebarNavLink">Pending Requests</a></li>
                    <li class="sidebarNavItem"><a href="patients.php" class="sidebarNavLink">My Clients</a></li>
                    <li class="sidebarNavItem"><a href="patient_tracking.php" class="sidebarNavLink">Client Tracking</a></li>
                    <li class="sidebarNavItem"><a href="therapy_support.php" class="sidebarNavLink">Therapy Support</a></li>
                    <li class="sidebarNavItem"><a href="community_forum.php" class="sidebarNavLink">Community Forum</a></li>
					<li class="sidebarNavItem"><a href="doctor_schedule.php" class="sidebarNavLink">Schedule</a></li>
                    <li class="sidebarNavItem"><a href="analytics_dashboard.php" class="sidebarNavLink active">Analytics</a></li>
                    <li class="sidebarNavItem"><a href="student_messages.php" class="sidebarNavLink">Messages</a></li>
                    <li class="sidebarNavItem"><a href="profile_settings.php" class="sidebarNavLink">Settings</a></li>
                <?php endif; ?>
            </ul>
            <div class="sidebarFooter">
                <button type="button" class="logoutButton" onclick="openLogoutConfirm()">Logout</button>
            </div>
        </div>

        <div class="dbMainContent">
            <div class="analytics-container">
                <div class="analytics-header">
                    <h2><?php echo $userRole === 'client' ? 'My Progress Analytics' : 'Clinical Analytics'; ?></h2>
                    <p>Track your <?php echo $userRole === 'client' ? 'mental health progress' : 'clinical practice and patient outcomes'; ?> with detailed insights and trends.</p>
                </div>

                <!-- Overview Statistics -->
                <div class="stats-grid">
                    <?php if ($userRole === 'client'): ?>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $overviewStats['total_sessions'] ?? 0; ?></div>
                            <div class="stat-label">Total Sessions</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $overviewStats['completed_sessions'] ?? 0; ?></div>
                            <div class="stat-label">Completed Sessions</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo round($overviewStats['avg_mood'] ?? 0, 1); ?></div>
                            <div class="stat-label">Average Mood (30d)</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo round($overviewStats['avg_sleep'] ?? 0, 1); ?>h</div>
                            <div class="stat-label">Average Sleep</div>
                        </div>
                    <?php else: ?>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $overviewStats['total_patients'] ?? 0; ?></div>
                            <div class="stat-label">Total Patients</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $overviewStats['total_sessions'] ?? 0; ?></div>
                            <div class="stat-label">Total Sessions</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo round($overviewStats['avg_session_rating'] ?? 0, 1); ?></div>
                            <div class="stat-label">Avg Session Rating</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo round($overviewStats['avg_helpfulness_rating'] ?? 0, 1); ?></div>
                            <div class="stat-label">Avg Helpfulness Rating</div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Charts -->
                <div class="charts-grid">
                    <?php if ($userRole === 'client'): ?>
                        <div class="chart-container" style="grid-column: 1 / -1;">
                            <div class="chart-title">Mood Trends (Last 30 Days)</div>
                            <div class="chart-canvas">
                                <canvas id="moodChart"></canvas>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($userRole === 'therapist' && !empty($progressStats)): ?>
                        <div class="chart-container">
                            <div class="chart-title">Progress Status Distribution</div>
                            <div class="chart-canvas">
                                <canvas id="progressChart"></canvas>
                            </div>
                        </div>
                    <?php elseif ($userRole === 'therapist'): ?>
                        <div class="chart-container">
                            <div class="chart-title">Assessment Scores</div>
                            <div class="chart-canvas">
                                <canvas id="assessmentChart"></canvas>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Activity -->
                <div class="activity-section" id="recent-activity">
                    <h3>Recent Activity</h3>
                    <?php if (empty($recentActivity)): ?>
                        <div class="no-data">No recent activity recorded.</div>
                    <?php else: ?>
                        <?php foreach ($recentActivity as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon <?php echo $activity['type']; ?>">
                                    <?php
                                    switch($activity['type']) {
                                        case 'mood': echo '😊'; break;
                                        case 'assessment': echo '📋'; break;
                                        case 'tfeedback': echo '🧑‍⚕️'; break;
                                        case 'session': echo '💬'; break;
                                        case 'note': echo '📝'; break;
                                    }
                                    ?>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title"><?php echo htmlspecialchars($activity['description']); ?></div>
                                    <div class="activity-date"><?php echo date('M j, Y g:i A', strtotime($activity['date'])); ?></div>
                                </div>
                                <?php if ($activity['type'] === 'tfeedback' && !empty($activity['session_id'])): ?>
                                    <div class="activity-actions">
                                        <button class="pagination-btn" onclick="viewTherapistFeedback(<?php echo (int)$activity['session_id']; ?>)">View</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <div>
                        <?php $prevA = max(1, $activityPage-1); $nextA = $activityPage+1; ?>
                        <a href="analytics_dashboard.php?activity_page=<?php echo $prevA; ?>&feedback_page=<?php echo $feedbackPage; ?>#recent-activity" class="pagination-btn">« Prev</a>
                        <span class="pagination-info">Page <?php echo $activityPage; ?></span>
                        <a href="analytics_dashboard.php?activity_page=<?php echo $nextA; ?>&feedback_page=<?php echo $feedbackPage; ?>#recent-activity" class="pagination-btn">Next »</a>
                    </div>
                </div>

                <?php if ($userRole === 'client'): ?>
                <div class="activity-section" id="recent-therapist-feedback">
                    <h3>Recent Therapist Feedback</h3>
                    <?php if (empty($recentTherapistFeedback)): ?>
                        <div class="no-data">No therapist feedback yet.</div>
                    <?php else: ?>
                        <?php foreach ($recentTherapistFeedback as $fb): ?>
                            <div class="activity-item">
                                <div class="activity-icon note">🧑‍⚕️</div>
                                <div class="activity-content">
                                    <div class="activity-title">Dr. <?php echo htmlspecialchars($fb['therapist_name']); ?> — Session on <?php echo date('M j, Y', strtotime($fb['session_date'])); ?> at <?php echo date('g:i A', strtotime($fb['session_time'])); ?></div>
                                    <div class="activity-date">Submitted: <?php echo date('M j, Y g:i A', strtotime($fb['created_at'])); ?></div>
                                    <div>
                                        <button class="pagination-btn" type="button" data-view-therapist-feedback data-session-id="<?php echo (int)$fb['session_id']; ?>">
                                            View Feedback
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <div>
                        <?php $prevF = max(1, $feedbackPage-1); $nextF = $feedbackPage+1; ?>
                        <a href="analytics_dashboard.php?activity_page=<?php echo $activityPage; ?>&feedback_page=<?php echo $prevF; ?>#recent-therapist-feedback" class="pagination-btn">« Prev</a>
                        <span class="pagination-info">Page <?php echo $feedbackPage; ?></span>
                        <a href="analytics_dashboard.php?activity_page=<?php echo $activityPage; ?>&feedback_page=<?php echo $nextF; ?>#recent-therapist-feedback" class="pagination-btn">Next »</a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($userRole === 'therapist'): ?>
                <div class="activity-section" id="recent-feedback">
                    <h3>Recent Client Feedback</h3>
                    <?php if (empty($recentFeedback)): ?>
                        <div class="no-data">No client feedback yet.</div>
                    <?php else: ?>
                        <?php foreach ($recentFeedback as $fb): ?>
                            <div class="activity-item">
                                <div class="activity-icon note">📝</div>
                                <div class="activity-content">
                                    <div class="activity-title">
                                        <?php echo !empty($fb['is_anonymous']) ? 'Anonymous' : htmlspecialchars($fb['client_name']); ?>
                                    </div>
                                    <div>
                                        <button class="pagination-btn" type="button"
                                            data-session-id="<?php echo (int)$fb['session_id']; ?>"
                                            data-client-name="<?php echo htmlspecialchars(!empty($fb['is_anonymous']) ? 'Anonymous' : $fb['client_name']); ?>"
                                            data-submitted-at="<?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($fb['created_at']))); ?>"
                                            data-session-rating="<?php echo (int)$fb['session_rating']; ?>"
                                            data-helpfulness-rating="<?php echo (int)$fb['helpfulness_rating']; ?>"
                                            data-mood-after="<?php echo htmlspecialchars($fb['mood_after_session'] ?? ''); ?>"
                                            data-what-went-well="<?php echo htmlspecialchars($fb['what_went_well'] ?? ''); ?>"
                                            data-what-can-improve="<?php echo htmlspecialchars($fb['what_can_improve'] ?? ''); ?>"
                                            data-additional-comments="<?php echo htmlspecialchars($fb['additional_comments'] ?? ''); ?>">
                                            View
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <div>
                        <?php $prevF = max(1, $feedbackPage-1); $nextF = $feedbackPage+1; ?>
                        <a href="analytics_dashboard.php?activity_page=<?php echo $activityPage; ?>&feedback_page=<?php echo $prevF; ?>#recent-feedback" class="pagination-btn">« Prev</a>
                        <span class="pagination-info">Page <?php echo $feedbackPage; ?></span>
                        <a href="analytics_dashboard.php?activity_page=<?php echo $activityPage; ?>&feedback_page=<?php echo $nextF; ?>#recent-feedback" class="pagination-btn">Next »</a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($userRole === 'therapist' && !empty($progressStats)): ?>
                    <div class="activity-section">
                        <h3>Progress Overview</h3>
                        <div class="progress-distribution">
                            <?php foreach ($progressStats as $stat): ?>
                                <div class="progress-item">
                                    <div class="progress-count"><?php echo $stat['count']; ?></div>
                                    <div class="progress-label"><?php echo ucfirst(str_replace('_', ' ', $stat['progress_status'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Logout Confirm Modal -->
    <div id="logoutConfirmModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Logout</h3>
                <span class="close" onclick="closeLogoutConfirm()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to logout?</p>
            </div>
            <div class="modal-actions">
                <button type="button" class="cancel-btn" onclick="closeLogoutConfirm()">Cancel</button>
                <button type="button" class="submit-btn" id="logoutConfirmOk">Logout</button>
            </div>
        </div>
    </div>

    <script>
        function openLogoutConfirm(){
            const modal = document.getElementById('logoutConfirmModal');
            const okBtn = document.getElementById('logoutConfirmOk');
            if (!modal || !okBtn) return;
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            try { if (typeof closeMobileMenu === 'function') closeMobileMenu(); } catch(e) {}
            document.body.classList.add('modal-open');
            const onOk = ()=>{ cleanup(); window.location.href='logout.php'; };
            const onCancel = ()=>{ cleanup(); };
            function cleanup(){
                modal.classList.remove('show');
                document.body.style.overflow = '';
                document.body.classList.remove('modal-open');
                okBtn.removeEventListener('click', onOk);
                modal.removeEventListener('click', onBackdrop);
                document.removeEventListener('keydown', onEsc);
            }
            function onBackdrop(e){ if(e.target === modal){ onCancel(); } }
            function onEsc(e){ if(e.key === 'Escape'){ onCancel(); } }
            okBtn.addEventListener('click', onOk);
            modal.addEventListener('click', onBackdrop);
            document.addEventListener('keydown', onEsc);
            window.closeLogoutConfirm = onCancel;
        }

        function closeLogoutConfirm(){ if (typeof window.closeLogoutConfirm === 'function') window.closeLogoutConfirm(); }

        // Mobile menu functions
        function toggleMobileMenu() {
            const sidebar = document.querySelector('.dbSidebar');
            const overlay = document.getElementById('mobileMenuOverlay');
            const menuBtn = document.getElementById('mobileMenuBtn');
            if (sidebar && overlay && menuBtn) {
                const isOpen = sidebar.classList.contains('mobile-open');
                if (isOpen) { closeMobileMenu(); } else { openMobileMenu(); }
            }
        }
        function openMobileMenu() {
            const sidebar = document.querySelector('.dbSidebar');
            const overlay = document.getElementById('mobileMenuOverlay');
            const menuBtn = document.getElementById('mobileMenuBtn');
            const body = document.body;
            if (sidebar && overlay && menuBtn) {
                sidebar.classList.add('mobile-open');
                overlay.classList.add('active');
                menuBtn.classList.add('active');
                body.classList.add('mobile-menu-open');
            }
        }
        function closeMobileMenu() {
            const sidebar = document.querySelector('.dbSidebar');
            const overlay = document.getElementById('mobileMenuOverlay');
            const menuBtn = document.getElementById('mobileMenuBtn');
            const body = document.body;
            if (sidebar && overlay && menuBtn) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
                menuBtn.classList.remove('active');
                body.classList.remove('mobile-menu-open');
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.sidebarNavLink').forEach(link => link.addEventListener('click', () => closeMobileMenu()));
            document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeMobileMenu(); });
            // Delegate click for therapist recent feedback buttons
            document.addEventListener('click', function(e){
                const btn = e.target && e.target.closest('button.pagination-btn[data-session-id]');
                if (btn) {
                    e.preventDefault();
                    // Therapist view (client feedback) buttons use data attributes without marker
                    if (btn.hasAttribute('data-view-therapist-feedback')) {
                        try { viewTherapistFeedback(btn.getAttribute('data-session-id')); } catch(_) {}
                    } else {
                        try { openFeedbackFromBtn(btn); } catch(_) {}
                    }
                }
            });
        });

        // Mood chart only for client analytics
        <?php if ($userRole === 'client'): ?>
        const moodCanvas = document.getElementById('moodChart');
        const moodCtx = moodCanvas ? moodCanvas.getContext('2d') : null;
        const moodData = <?php echo json_encode(isset($moodTrends) ? $moodTrends : []); ?>;
        if (moodCtx) new Chart(moodCtx, {
            type: 'line',
            data: {
                labels: moodData.map(item => new Date(item.log_date).toLocaleDateString()),
                datasets: [{
                    label: 'Mood Rating',
                    data: moodData.map(item => parseFloat(item.avg_mood || item.mood_rating)),
                    borderColor: '#2196f3',
                    backgroundColor: 'rgba(33, 150, 243, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Stress Level',
                    data: moodData.map(item => parseFloat(item.avg_stress || item.stress_level)),
                    borderColor: '#f44336',
                    backgroundColor: 'rgba(244, 67, 54, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, max: 10 } },
                plugins: { legend: { display: true } }
            }
        });
        <?php endif; ?>

        <?php if ($userRole === 'therapist' && !empty($progressStats)): ?>
        // Progress Chart
        const progressCanvas = document.getElementById('progressChart');
        const progressCtx = progressCanvas ? progressCanvas.getContext('2d') : null;
        const progressData = <?php echo json_encode(isset($progressStats) ? $progressStats : []); ?>;
        
        if (progressCtx) new Chart(progressCtx, {
            type: 'doughnut',
            data: {
                labels: progressData.map(item => item.progress_status.replace('_', ' ').toUpperCase()),
                datasets: [{
                    data: progressData.map(item => item.count),
                    backgroundColor: [
                        '#4caf50',
                        '#ff9800',
                        '#f44336'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        <?php else: ?>
        // Assessment Chart
        const assessmentCanvas = document.getElementById('assessmentChart');
        const assessmentCtx = assessmentCanvas ? assessmentCanvas.getContext('2d') : null;
        const assessmentData = <?php echo json_encode(isset($assessments) ? $assessments : []); ?>;
        
        if (assessmentCtx && assessmentData.length > 0) {
            new Chart(assessmentCtx, {
                type: 'bar',
                data: {
                    labels: assessmentData.map(item => item.assessment_type.toUpperCase()),
                    datasets: [{
                        label: 'Score',
                        data: assessmentData.map(item => item.total_score),
                        backgroundColor: '#9c27b0',
                        borderColor: '#7b1fa2',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        } else {
            if (assessmentCanvas) assessmentCanvas.innerHTML = '<div class="no-data">No assessment data available</div>';
        }
        <?php endif; ?>

        // Therapist feedback viewer
        async function viewTherapistFeedback(sessionId){
            const modal = document.getElementById('tfModal');
            const body = document.getElementById('tfBody');
            if (!modal || !body) return;
            body.innerHTML = 'Loading...';
            modal.classList.add('show');
            modal.style.display = 'flex';
            try {
                const res = await fetch('view_therapist_feedback.php?session_id=' + encodeURIComponent(sessionId));
                const data = await res.json();
                if (!data.success) { body.innerHTML = '<div class="tf-empty">No feedback found.</div>'; return; }
                const f = data.feedback;
                body.innerHTML = `
                    <div class="tf-meta">
                        <div class="tf-meta-item">🧑‍⚕️ <strong>Therapist:</strong>&nbsp;Dr. ${escapeHtml(f.therapist_name||'')}</div>
                        <div class="tf-meta-item">🗓️ <strong>Submitted:</strong>&nbsp;${escapeHtml(f.created_at_label||'')}</div>
                    </div>
                    ${f.summary ? `<div class="tf-summary">${formatTfSummary(f.summary)}</div>` : '<div class="tf-empty">No written summary provided.</div>'}
                `;
            } catch(e){
                body.innerHTML = '<div class="tf-empty">Failed to load feedback.</div>';
            }
        }
        function closeTfModal(){ const m=document.getElementById('tfModal'); if(m){ m.classList.remove('show'); setTimeout(()=>{ if(m) m.style.display='none'; }, 200); } }
        // Close on backdrop click and Esc for better UX
        (function(){
            const modal = document.getElementById('tfModal');
            if (!modal) return;
            modal.addEventListener('click', function(e){ if(e.target === modal){ closeTfModal(); } });
            document.addEventListener('keydown', function(e){ if(e.key === 'Escape' && modal.classList.contains('show')){ closeTfModal(); } });
        })();
        function formatTfSummary(text){
            let safe = escapeHtml(String(text||''));
            safe = safe.replace(/\n/g,'<br>');
            const labels = [
                'Session Effectiveness',
                'Observed Progress',
                'Behavioral Observations',
                'Coping Skills Observed',
                'Recommendations for Next Session'
            ];
            labels.forEach(function(label){
                const escaped = label.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
                const re = new RegExp('(^|<br>)(\\s*)' + escaped + '\\s*:', 'gi');
                safe = safe.replace(re, function(_, br, ws){ return (br||'') + (ws||'') + '<strong>' + label + ':</strong>'; });
            });
            return safe;
        }
        // Map mood text/emoji to a friendly label
        function deriveMoodLabel(text){
            const s = String(text || '').toLowerCase();
            if (/[😀😃😄😁😊🙂☺️😺]/u.test(s)) return 'Happy';
            if (/[🙁☹️😞😟😔😢😭😿]/u.test(s)) return 'Sad';
            if (/[😬😰😟😨😥😓😖😣😱🤯]/u.test(s)) return 'Anxious';
            if (/[😡😠🤬]/u.test(s)) return 'Angry';
            if (/[😴🥱]/u.test(s)) return 'Tired';
            if (/[😌🧘]/u.test(s)) return 'Calm';
            if (/[😐😑]/u.test(s)) return 'Neutral';
            if (/(very )?(happy|good|great|positive|joy|joyful|uplifted)/.test(s)) return 'Happy';
            if (/(sad|down|blue|unhappy|depressed)/.test(s)) return 'Sad';
            if (/(anxious|anxiety|nervous|worried|on edge)/.test(s)) return 'Anxious';
            if (/(stressed|stress|overwhelmed|tense)/.test(s)) return 'Stressed';
            if (/(angry|mad|frustrated|irritated)/.test(s)) return 'Angry';
            if (/(tired|sleepy|exhausted|fatigued|drained)/.test(s)) return 'Tired';
            if (/(calm|relaxed|peaceful|at ease)/.test(s)) return 'Calm';
            if (/(neutral|okay|ok|fine|meh)/.test(s)) return 'Neutral';
            return '';
        }
        // Client Feedback (Therapist View) - enhanced interactions with proper cleanup
        window.openFeedbackFromBtn = function(btn){
            if (!btn) return;
            const clientName = btn.getAttribute('data-client-name') || '';
            const submittedAt = btn.getAttribute('data-submitted-at') || '';
            const sessionRating = Number(btn.getAttribute('data-session-rating')) || 0;
            const helpfulnessRating = Number(btn.getAttribute('data-helpfulness-rating')) || 0;
            const moodAfter = btn.getAttribute('data-mood-after') || '';
            const whatWentWell = btn.getAttribute('data-what-went-well') || '';
            const whatCanImprove = btn.getAttribute('data-what-can-improve') || '';
            const additionalComments = btn.getAttribute('data-additional-comments') || '';

            const modal = document.getElementById('feedbackModal');
            const body = document.getElementById('feedbackBody');
            if (!modal || !body) return;

            const moodLabel = deriveMoodLabel(moodAfter);
            body.innerHTML = `
                <div class="row"><span class="label">Client:</span> ${escapeHtml(clientName)}</div>
                <div class="row"><span class="label">Session Rating:</span> ${(sessionRating||0)}/5</div>
                <div class="row"><span class="label">Helpfulness Rating:</span> ${(helpfulnessRating||0)}/5</div>
                ${moodAfter ? `<div class=\"row\"><span class=\"label\">Mood After:</span> ${escapeHtml(moodAfter)}${moodLabel ? ` — <em>${escapeHtml(moodLabel)}</em>` : ''}</div>` : ''}
                ${whatWentWell ? `<div class=\"row\"><span class=\"label\">What went well:</span><br>${escapeHtml(whatWentWell).replace(/\n/g,'<br>')}</div>` : ''}
                ${whatCanImprove ? `<div class=\"row\"><span class=\"label\">To improve:</span><br>${escapeHtml(whatCanImprove).replace(/\n/g,'<br>')}</div>` : ''}
                ${additionalComments ? `<div class=\"row\"><span class=\"label\">Comments:</span><br>${escapeHtml(additionalComments).replace(/\n/g,'<br>')}</div>` : '<div class=\"row\">No additional comments provided.</div>'}
            `;

            function cleanup(){
                modal.classList.remove('show');
                document.body.style.overflow = '';
                document.body.classList.remove('modal-open');
                modal.removeEventListener('click', onBackdrop);
                document.removeEventListener('keydown', onEsc);
            }
            function onBackdrop(e){ if(e.target === modal){ cleanup(); } }
            function onEsc(e){ if(e.key === 'Escape'){ cleanup(); } }

            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            document.body.classList.add('modal-open');
            modal.addEventListener('click', onBackdrop);
            document.addEventListener('keydown', onEsc);

            window.closeFeedbackModal = cleanup;
        }
        // Listeners are attached per-open with cleanup; no global listeners needed here
        function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c])); }
    </script>
</body>
</html>
