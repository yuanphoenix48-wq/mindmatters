<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'connect.php';

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'student';

// Get assessment type if provided
$assessmentType = $_GET['type'] ?? 'phq9'; // Can be 'phq9', 'gad7', 'pre_session', 'post_session'
$sessionId = isset($_GET['session_id']) ? intval($_GET['session_id']) : null;

// Gate pre-session assessment availability: only within 5 minutes before the scheduled session
$preWindowAllowed = true;
$preGateMessage = '';
if ($assessmentType === 'pre_session') {
    if (!$sessionId) {
        $preWindowAllowed = false;
        $preGateMessage = 'Pre-Assessment requires a valid session. Please open from My Sessions.';
    } else {
        // Verify session belongs to user and is scheduled
        $sql = "SELECT id, client_id, session_date, session_time, status FROM sessions WHERE id = ? LIMIT 1";
        if ($stmtTmp = $conn->prepare($sql)) {
            $stmtTmp->bind_param("i", $sessionId);
            $stmtTmp->execute();
            $resTmp = $stmtTmp->get_result();
            $sess = $resTmp->fetch_assoc();
            $stmtTmp->close();
            if (!$sess || (int)$sess['client_id'] !== (int)$userId) {
                $preWindowAllowed = false;
                $preGateMessage = 'This pre-assessment is not available for your account.';
            } else {
                // Compute window: open at (session_date + session_time - 5 minutes)
                date_default_timezone_set('Asia/Manila');
                $nowTs = time();
                $startTs = strtotime($sess['session_date'] . ' ' . $sess['session_time']);
                $openTs = $startTs - 5 * 60; // 5 minutes before
                if ($nowTs < $openTs) {
                    $preWindowAllowed = false;
                    $preGateMessage = 'Pre-Assessment opens 5 minutes before your scheduled session.';
                }
                // Optionally, also prevent after session end if desired (not required by spec)
            }
        }
    }
}

// Fetch user details
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assessmentData = $_POST;
    $totalScore = 0;
    $severityLevel = null;
    
    // Get assessment type from POST data
    $assessmentType = $_POST['assessment_type'] ?? $assessmentType;
    $sessionId = isset($_POST['session_id']) ? intval($_POST['session_id']) : $sessionId;
    
    // Calculate scores based on assessment type
    if ($assessmentType === 'phq9') {
        $totalScore = array_sum(array_slice($assessmentData, 0, 9));
        if ($totalScore <= 4) $severityLevel = 'minimal';
        elseif ($totalScore <= 9) $severityLevel = 'mild';
        elseif ($totalScore <= 14) $severityLevel = 'moderate';
        elseif ($totalScore <= 19) $severityLevel = 'moderately_severe';
        else $severityLevel = 'severe';
    } elseif ($assessmentType === 'gad7') {
        $totalScore = array_sum(array_slice($assessmentData, 0, 7));
        if ($totalScore <= 4) $severityLevel = 'minimal';
        elseif ($totalScore <= 9) $severityLevel = 'mild';
        elseif ($totalScore <= 14) $severityLevel = 'moderate';
        else $severityLevel = 'severe';
    } elseif ($assessmentType === 'pre_session') {
        // No numeric scoring; store inputs as-is
        $totalScore = 0;
        $severityLevel = null;
    }
    
// Save assessment to database
    $sql = "INSERT INTO mental_health_assessments (client_id, session_id, assessment_type, assessment_data, total_score, severity_level) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $assessmentDataJson = json_encode($assessmentData);
    $stmt->bind_param("iissis", $userId, $sessionId, $assessmentType, $assessmentDataJson, $totalScore, $severityLevel);
    
    if ($stmt->execute()) {
        // For pre-session assessments, return user to My Sessions immediately
        if ($assessmentType === 'pre_session') {
            header('Location: my_session.php?message=assessment_completed');
            exit();
        }
        if ($assessmentType === 'post_session') {
            header('Location: my_session.php?message=post_assessment_completed');
            exit();
        }
        $successMessage = "Assessment completed successfully!";
        if ($totalScore > 0) {
            $successMessage .= " Your score: $totalScore ($severityLevel severity)";
        }
    } else {
        $errorMessage = "Error saving assessment: " . $conn->error;
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mental Health Assessment - Mind Matters</title>
    <link rel="stylesheet" href="styles/global.css">
    <link rel="stylesheet" href="styles/dashboard.css">
    <link rel="stylesheet" href="styles/notifications.css">
    <link rel="stylesheet" href="styles/mobile.css">
    <script src="js/mobile.js"></script>
    <style>
        .assessment-container { max-width: 960px; margin: 0 auto; padding: 20px; box-sizing: border-box; }

        .back-button { display:inline-flex; align-items:center; padding:0.75rem 1.25rem; background: linear-gradient(135deg, #1D5D9B, #14487a); color:#ffffff; text-decoration:none; border-radius: 10px; font-weight:700; transition: all 0.2s ease; margin-bottom:1.5rem; border:none; box-shadow: 0 2px 8px rgba(0,0,0,0.12); }
        .back-button:hover { filter: brightness(1.05); transform: translateY(-1px); }

        .assessment-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .assessment-form { background: #ffffff; border-radius: 12px; padding: 24px; box-shadow: 0 8px 20px rgba(0,0,0,0.06); }
        .question-group {
            margin-bottom: 20px;
            padding: 16px 18px;
            border: 1px solid #e9ecef;
            border-radius: 10px;
        }
        .question-text {
            font-weight: 600;
            margin-bottom: 12px;
            color: #333;
        }
        .rating-scale { display: grid; grid-template-columns: repeat(auto-fit, minmax(88px, 1fr)); gap: 8px; align-items: start; }
        .rating-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
            min-width: 0;
        }
        .rating-option input[type="radio"] { margin-bottom: 6px; transform: scale(1.05); }
        .rating-option label { font-size: 0.95rem; text-align: center; cursor: pointer; word-wrap: break-word; overflow-wrap: anywhere; color:#2f3b4a; }
        /* Mood logger style */
        .mood-grid { display:grid; grid-template-columns: repeat(6, minmax(80px, 1fr)); gap: 16px; justify-items:center; }
        .mood-option { display:flex; flex-direction:column; align-items:center; gap:6px; }
        .mood-option input[type="radio"] { width:22px; height:22px; }
        .mood-emoji { font-size:28px; line-height:1; }
        .mood-caption { font-size: 14px; color:#444; white-space: nowrap; }
        .submit-btn {
            background: #1D5D9B;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            margin-top: 20px;
        }
        .submit-btn:hover {
            background: #14487a;
        }
        .assessment-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #1D5D9B;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        /* Prevent horizontal overflow */
        .dbMainContent { width: 100%; max-width: 100%; overflow-x: hidden; }
        .assessment-form, .assessment-container, body { max-width: 100%; }
        .dbContainer { box-sizing: border-box; }

        /* Mobile tweaks */
        @media (max-width: 768px) {
            .assessment-container { padding: 12px; }
            .assessment-form { padding: 16px; border-radius: 10px; }
            .question-group { padding: 12px; }
            /* Keep options horizontal and wrapping on mobile */
            .rating-scale { grid-template-columns: repeat(5, 1fr); gap: 8px; }
            .mood-grid { grid-template-columns: repeat(5, 1fr); gap: 12px; }
            .mood-emoji { font-size:24px; }
            .mood-caption { font-size: 13px; }
            .rating-option { align-items: center; }
            .rating-option input[type="radio"] { width: 22px; height: 22px; margin-bottom: 4px; }
            .rating-option label { font-size: 0.95rem; }
            .dbMainContent { margin-top: 80px; }
            /* Ensure burger is within header and aligned */
            .mobile-header { position: fixed !important; top:0 !important; left:0 !important; right:0 !important; z-index:1000 !important; background: linear-gradient(135deg, #1D5D9B, #14487a) !important; color:#fff !important; padding:1rem !important; box-shadow: 0 2px 10px rgba(0,0,0,0.1) !important; border-bottom: 1px solid rgba(255,255,255,0.1) !important; }
            .mobile-header-content { display:grid !important; grid-template-columns:44px 1fr auto !important; align-items:center !important; gap:.75rem !important; }
            .mobile-logo { justify-self:center !important; color:#fff !important; text-align:center !important; font-weight:700 !important; font-size:1.2rem !important; }
            .mobile-menu-btn { position:relative !important; top:0 !important; left:0 !important; width:44px !important; height:44px !important; border-radius:8px !important; background: var(--primary-color) !important; display:flex !important; align-items:center !important; justify-content:center !important; cursor:pointer !important; border:none !important; padding:0 !important; outline:none !important; box-shadow: 0 4px 12px rgba(0,0,0,0.2) !important; transition: all 0.3s ease !important; }
            .mobile-menu-btn .hamburger { display:block !important; width:20px !important; height:2px !important; background:#fff !important; margin:2px 0 !important; transition: all 0.3s ease !important; border-radius:1px !important; }
            .mobile-user-info { justify-self:end !important; display:flex !important; align-items:center !important; gap:.5rem !important; }
            .mobile-user-name { max-width:90px !important; overflow:hidden !important; text-overflow:ellipsis !important; white-space:nowrap !important; color:#fff !important; }
            .mobile-user-avatar { width:32px !important; height:32px !important; border-radius:50% !important; object-fit:cover !important; border: 2px solid rgba(255,255,255,0.3) !important; }
        }

        /* Desktop refinement for readability */
        @media (min-width: 992px) {
            .assessment-container { max-width: 1000px; }
            .rating-scale { grid-template-columns: repeat(10, minmax(60px, 1fr)); gap: 12px; }
            .rating-option input[type="radio"] { transform: scale(1.1); }
            .question-group { padding: 18px 20px; }
        }
    </style>
</head>
<body class="dbBody <?php echo ($userRole === 'client') ? 'client-role' : 'therapist-role'; ?>">
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
                <img src="<?php echo htmlspecialchars($user['profile_picture'] ?? 'images/profile/default_images/default_profile.png'); ?>" alt="Profile Picture" class="mobile-user-avatar">
                <span class="mobile-user-name"><?php echo htmlspecialchars($user['first_name'] ?? ''); ?></span>
            </div>
        </div>
    </div>
    <!-- Mobile Menu Overlay -->
    <div class="mobile-menu-overlay" id="mobileMenuOverlay" onclick="closeMobileMenu()"></div>
    <div class="dbContainer">
        <div class="dbSidebar">
            <div class="sidebarProfile">
                <img src="<?php echo htmlspecialchars($user['profile_picture'] ?? 'images/profile/default_images/default_profile.png'); ?>" alt="Profile Picture" class="defaultPicture">
                <h1 class="profileName"><?php echo htmlspecialchars($user['first_name']); ?></h1>
                <p class="userRole"><?php echo ucfirst($userRole); ?></p>
            </div>
            <ul class="sidebarNavList">
                <li class="sidebarNavItem"><a href="dashboard.php" class="sidebarNavLink">Home</a></li>
                <?php if ($userRole === 'client'): ?>
                    <li class="sidebarNavItem"><a href="my_session.php" class="sidebarNavLink">My Sessions</a></li>
                    <li class="sidebarNavItem"><a href="analytics_dashboard.php" class="sidebarNavLink">Analytics</a></li>
                    <li class="sidebarNavItem"><a href="resources.php" class="sidebarNavLink">Resources and Guide</a></li>
                    <li class="sidebarNavItem"><a href="community_forum.php" class="sidebarNavLink">Community Forum</a></li>
                    <li class="sidebarNavItem"><a href="therapy_support.php" class="sidebarNavLink">Therapy Support</a></li>
                    <li class="sidebarNavItem"><a href="student_messages.php" class="sidebarNavLink">Messages</a></li>
                <?php else: ?>
                    <li class="sidebarNavItem"><a href="appointments.php" class="sidebarNavLink">Appointments</a></li>
                    <li class="sidebarNavItem"><a href="pending_requests.php" class="sidebarNavLink">Pending Requests</a></li>
                    <li class="sidebarNavItem"><a href="patients.php" class="sidebarNavLink">My Clients</a></li>
                    <li class="sidebarNavItem"><a href="patient_tracking.php" class="sidebarNavLink">Client Tracking</a></li>
                    <li class="sidebarNavItem"><a href="doctor_schedule.php" class="sidebarNavLink">Schedule</a></li>
                    <li class="sidebarNavItem"><a href="analytics_dashboard.php" class="sidebarNavLink">Analytics</a></li>
                <?php endif; ?>
                <li class="sidebarNavItem"><a href="student_messages.php" class="sidebarNavLink">Messages</a></li>
                <li class="sidebarNavItem"><a href="profile_settings.php" class="sidebarNavLink">Settings</a></li>
            </ul>
            <div class="sidebarFooter">
                <button type="button" class="logoutButton" onclick="confirmLogout()">Logout</button>
            </div>
        </div>

        <div class="dbMainContent">
            <div class="assessment-container">
                <a href="my_session.php" class="back-button">← Back to My Sessions</a>
                
                <div class="assessment-header">
                    <h2>Mental Health Assessment</h2>
                    <p>Please answer the following questions honestly. This helps us provide better care.</p>
                </div>

                <?php if (isset($successMessage)): ?>
                    <div class="success-message"><?php echo htmlspecialchars($successMessage); ?></div>
                <?php endif; ?>

                <?php if (isset($errorMessage)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($errorMessage); ?></div>
                <?php endif; ?>

                <div class="assessment-info">
                    <strong>Assessment Type:</strong> <?php echo strtoupper($assessmentType); ?><br>
                    <strong>Purpose:</strong> 
                    <?php
                    switch($assessmentType) {
                        case 'phq9':
                            echo "Depression screening using PHQ-9 (Patient Health Questionnaire-9)";
                            break;
                        case 'gad7':
                            echo "Anxiety screening using GAD-7 (Generalized Anxiety Disorder 7-item scale)";
                            break;
                        case 'pre_session':
                            echo "Pre-Session Assessment (Before Session)";
                            break;
                        case 'post_session':
                            echo "Post-Session Assessment (After Session)";
                            break;
                        default:
                            echo "Mental health assessment";
                    }
                    ?>
                </div>

                <?php $displayType = ($assessmentType === 'gad7') ? 'gad7' : (($assessmentType === 'pre_session') ? 'pre_session' : (($assessmentType === 'post_session') ? 'post_session' : 'phq9')); ?>
                <form method="POST" class="assessment-form">
                    <?php if ($displayType === 'phq9'): ?>
                        <!-- PHQ-9 Questions -->
                        <div class="question-group">
                            <div class="question-text">1. Little interest or pleasure in doing things</div>
                            <div class="rating-scale">
                                <div class="rating-option">
                                    <input type="radio" name="q1" value="0" id="q1_0" required>
                                    <label for="q1_0">Not at all</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q1" value="1" id="q1_1">
                                    <label for="q1_1">Several days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q1" value="2" id="q1_2">
                                    <label for="q1_2">More than half the days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q1" value="3" id="q1_3">
                                    <label for="q1_3">Nearly every day</label>
                                </div>
                            </div>
                        </div>

                        <div class="question-group">
                            <div class="question-text">2. Feeling down, depressed, or hopeless</div>
                            <div class="rating-scale">
                                <div class="rating-option">
                                    <input type="radio" name="q2" value="0" id="q2_0" required>
                                    <label for="q2_0">Not at all</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q2" value="1" id="q2_1">
                                    <label for="q2_1">Several days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q2" value="2" id="q2_2">
                                    <label for="q2_2">More than half the days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q2" value="3" id="q2_3">
                                    <label for="q2_3">Nearly every day</label>
                                </div>
                            </div>
                        </div>

                        <div class="question-group">
                            <div class="question-text">3. Trouble falling or staying asleep, or sleeping too much</div>
                            <div class="rating-scale">
                                <div class="rating-option">
                                    <input type="radio" name="q3" value="0" id="q3_0" required>
                                    <label for="q3_0">Not at all</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q3" value="1" id="q3_1">
                                    <label for="q3_1">Several days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q3" value="2" id="q3_2">
                                    <label for="q3_2">More than half the days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q3" value="3" id="q3_3">
                                    <label for="q3_3">Nearly every day</label>
                                </div>
                            </div>
                        </div>

                        <div class="question-group">
                            <div class="question-text">4. Feeling tired or having little energy</div>
                            <div class="rating-scale">
                                <div class="rating-option">
                                    <input type="radio" name="q4" value="0" id="q4_0" required>
                                    <label for="q4_0">Not at all</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q4" value="1" id="q4_1">
                                    <label for="q4_1">Several days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q4" value="2" id="q4_2">
                                    <label for="q4_2">More than half the days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q4" value="3" id="q4_3">
                                    <label for="q4_3">Nearly every day</label>
                                </div>
                            </div>
                        </div>

                        <div class="question-group">
                            <div class="question-text">5. Poor appetite or overeating</div>
                            <div class="rating-scale">
                                <div class="rating-option">
                                    <input type="radio" name="q5" value="0" id="q5_0" required>
                                    <label for="q5_0">Not at all</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q5" value="1" id="q5_1">
                                    <label for="q5_1">Several days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q5" value="2" id="q5_2">
                                    <label for="q5_2">More than half the days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q5" value="3" id="q5_3">
                                    <label for="q5_3">Nearly every day</label>
                                </div>
                            </div>
                        </div>

                        <div class="question-group">
                            <div class="question-text">6. Feeling bad about yourself - or that you are a failure or have let yourself or your family down</div>
                            <div class="rating-scale">
                                <div class="rating-option">
                                    <input type="radio" name="q6" value="0" id="q6_0" required>
                                    <label for="q6_0">Not at all</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q6" value="1" id="q6_1">
                                    <label for="q6_1">Several days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q6" value="2" id="q6_2">
                                    <label for="q6_2">More than half the days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q6" value="3" id="q6_3">
                                    <label for="q6_3">Nearly every day</label>
                                </div>
                            </div>
                        </div>

                        <div class="question-group">
                            <div class="question-text">7. Trouble concentrating on things, such as reading the newspaper or watching television</div>
                            <div class="rating-scale">
                                <div class="rating-option">
                                    <input type="radio" name="q7" value="0" id="q7_0" required>
                                    <label for="q7_0">Not at all</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q7" value="1" id="q7_1">
                                    <label for="q7_1">Several days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q7" value="2" id="q7_2">
                                    <label for="q7_2">More than half the days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q7" value="3" id="q7_3">
                                    <label for="q7_3">Nearly every day</label>
                                </div>
                            </div>
                        </div>

                        <div class="question-group">
                            <div class="question-text">8. Moving or speaking so slowly that other people could have noticed, or the opposite - being so fidgety or restless that you have been moving around a lot more than usual</div>
                            <div class="rating-scale">
                                <div class="rating-option">
                                    <input type="radio" name="q8" value="0" id="q8_0" required>
                                    <label for="q8_0">Not at all</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q8" value="1" id="q8_1">
                                    <label for="q8_1">Several days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q8" value="2" id="q8_2">
                                    <label for="q8_2">More than half the days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q8" value="3" id="q8_3">
                                    <label for="q8_3">Nearly every day</label>
                                </div>
                            </div>
                        </div>

                        <div class="question-group">
                            <div class="question-text">9. Thoughts that you would be better off dead, or of hurting yourself</div>
                            <div class="rating-scale">
                                <div class="rating-option">
                                    <input type="radio" name="q9" value="0" id="q9_0" required>
                                    <label for="q9_0">Not at all</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q9" value="1" id="q9_1">
                                    <label for="q9_1">Several days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q9" value="2" id="q9_2">
                                    <label for="q9_2">More than half the days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q9" value="3" id="q9_3">
                                    <label for="q9_3">Nearly every day</label>
                                </div>
                            </div>
                        </div>

                    <?php elseif ($displayType === 'gad7'): ?>
                        <!-- GAD-7 Questions -->
                        <div class="question-group">
                            <div class="question-text">1. Feeling nervous, anxious, or on edge</div>
                            <div class="rating-scale">
                                <div class="rating-option">
                                    <input type="radio" name="q1" value="0" id="q1_0" required>
                                    <label for="q1_0">Not at all</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q1" value="1" id="q1_1">
                                    <label for="q1_1">Several days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q1" value="2" id="q1_2">
                                    <label for="q1_2">More than half the days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q1" value="3" id="q1_3">
                                    <label for="q1_3">Nearly every day</label>
                                </div>
                            </div>
                        </div>

                        <div class="question-group">
                            <div class="question-text">2. Not being able to stop or control worrying</div>
                            <div class="rating-scale">
                                <div class="rating-option">
                                    <input type="radio" name="q2" value="0" id="q2_0" required>
                                    <label for="q2_0">Not at all</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q2" value="1" id="q2_1">
                                    <label for="q2_1">Several days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q2" value="2" id="q2_2">
                                    <label for="q2_2">More than half the days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q2" value="3" id="q2_3">
                                    <label for="q2_3">Nearly every day</label>
                                </div>
                            </div>
                        </div>

                        <div class="question-group">
                            <div class="question-text">3. Worrying too much about different things</div>
                            <div class="rating-scale">
                                <div class="rating-option">
                                    <input type="radio" name="q3" value="0" id="q3_0" required>
                                    <label for="q3_0">Not at all</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q3" value="1" id="q3_1">
                                    <label for="q3_1">Several days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q3" value="2" id="q3_2">
                                    <label for="q3_2">More than half the days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q3" value="3" id="q3_3">
                                    <label for="q3_3">Nearly every day</label>
                                </div>
                            </div>
                        </div>

                        <div class="question-group">
                            <div class="question-text">4. Trouble relaxing</div>
                            <div class="rating-scale">
                                <div class="rating-option">
                                    <input type="radio" name="q4" value="0" id="q4_0" required>
                                    <label for="q4_0">Not at all</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q4" value="1" id="q4_1">
                                    <label for="q4_1">Several days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q4" value="2" id="q4_2">
                                    <label for="q4_2">More than half the days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q4" value="3" id="q4_3">
                                    <label for="q4_3">Nearly every day</label>
                                </div>
                            </div>
                        </div>

                        <div class="question-group">
                            <div class="question-text">5. Being so restless that it's hard to sit still</div>
                            <div class="rating-scale">
                                <div class="rating-option">
                                    <input type="radio" name="q5" value="0" id="q5_0" required>
                                    <label for="q5_0">Not at all</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q5" value="1" id="q5_1">
                                    <label for="q5_1">Several days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q5" value="2" id="q5_2">
                                    <label for="q5_2">More than half the days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q5" value="3" id="q5_3">
                                    <label for="q5_3">Nearly every day</label>
                                </div>
                            </div>
                        </div>

                        <div class="question-group">
                            <div class="question-text">6. Becoming easily annoyed or irritable</div>
                            <div class="rating-scale">
                                <div class="rating-option">
                                    <input type="radio" name="q6" value="0" id="q6_0" required>
                                    <label for="q6_0">Not at all</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q6" value="1" id="q6_1">
                                    <label for="q6_1">Several days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q6" value="2" id="q6_2">
                                    <label for="q6_2">More than half the days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q6" value="3" id="q6_3">
                                    <label for="q6_3">Nearly every day</label>
                                </div>
                            </div>
                        </div>

                        <div class="question-group">
                            <div class="question-text">7. Feeling afraid as if something awful might happen</div>
                            <div class="rating-scale">
                                <div class="rating-option">
                                    <input type="radio" name="q7" value="0" id="q7_0" required>
                                    <label for="q7_0">Not at all</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q7" value="1" id="q7_1">
                                    <label for="q7_1">Several days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q7" value="2" id="q7_2">
                                    <label for="q7_2">More than half the days</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="q7" value="3" id="q7_3">
                                    <label for="q7_3">Nearly every day</label>
                                </div>
                            </div>
                        </div>

                    <?php elseif ($displayType === 'pre_session'): ?>
                        <!-- Pre-session Assessment -->
                        <div class="question-group">
                            <div class="question-text">How are you feeling today?</div>
                            <div class="mood-grid">
                                <?php
                                    $moodOptions = [
                                        '😀' => 'Happy',
                                        '🙂' => 'Neutral',
                                        '😔' => 'Sad',
                                        '😢' => 'Very Sad',
                                        '😡' => 'Angry',
                                        '😰' => 'Anxious'
                                    ];
                                    $i = 0;
                                    foreach ($moodOptions as $emoji => $labelText):
                                        $val = ++$i;
                                ?>
                                    <div class="mood-option">
                                        <input type="radio" name="mood_emoji" value="<?php echo $emoji; ?>" id="mood_emoji_<?php echo $val; ?>" required>
                                        <div class="mood-emoji" aria-hidden="true"><?php echo $emoji; ?></div>
                                        <label for="mood_emoji_<?php echo $val; ?>" class="mood-caption"><?php echo htmlspecialchars($labelText); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="question-group">
                            <div class="question-text">How would you rate your current mood? (1 = Very poor, 10 = Excellent)</div>
                            <div class="rating-scale">
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <div class="rating-option">
                                        <input type="radio" name="mood_rating" value="<?php echo $i; ?>" id="mood_<?php echo $i; ?>" required>
                                        <label for="mood_<?php echo $i; ?>"><?php echo $i; ?></label>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="question-group">
                            <div class="question-text">How would you rate your general stress level? (1 = Not stress at all, 10 = Extremely stressed)</div>
                            <div class="rating-scale">
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <div class="rating-option">
                                        <input type="radio" name="stress_level" value="<?php echo $i; ?>" id="stress_<?php echo $i; ?>" required>
                                        <label for="stress_<?php echo $i; ?>"><?php echo $i; ?></label>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="question-group">
                            <div class="question-text">How would you rate your anxiety level? (1 = Not anxious at all, 10 = Extremely anxious)</div>
                            <div class="rating-scale">
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <div class="rating-option">
                                        <input type="radio" name="anxiety_level" value="<?php echo $i; ?>" id="anxiety_<?php echo $i; ?>" required>
                                        <label for="anxiety_<?php echo $i; ?>"><?php echo $i; ?></label>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="question-group">
                            <div class="question-text">How many hours of sleep did you get last night?</div>
                            <input type="number" name="sleep_hours" min="0" max="24" step="0.5" required style="width: 100px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>

                        <div class="question-group">
                            <div class="question-text">What are the concerns you'd like to discuss today?</div>
                            <textarea name="concerns" rows="4" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" placeholder="Please share what's on your mind..."></textarea>
                        </div>
                    <?php elseif ($displayType === 'post_session'): ?>
                        <!-- Post-session Assessment (After Session) -->
                        <div class="question-group">
                            <div class="question-text">How are you feeling after the session?</div>
                            <div class="mood-grid">
                                <?php
                                    $postMoodOptions = [
                                        '😀' => 'Happy',
                                        '🙂' => 'Neutral',
                                        '😔' => 'Sad',
                                        '😢' => 'Very Sad',
                                        '😡' => 'Angry',
                                        '😰' => 'Anxious'
                                    ];
                                    $i = 0;
                                    foreach ($postMoodOptions as $emoji => $labelText):
                                        $val = ++$i;
                                ?>
                                    <div class="mood-option">
                                        <input type="radio" name="post_mood_emoji" value="<?php echo $emoji; ?>" id="post_mood_emoji_<?php echo $val; ?>" required>
                                        <div class="mood-emoji" aria-hidden="true"><?php echo $emoji; ?></div>
                                        <label for="post_mood_emoji_<?php echo $val; ?>" class="mood-caption"><?php echo htmlspecialchars($labelText); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="question-group">
                            <div class="question-text">How would you rate your mood now? (1 = Very poor, 10 = Excellent)</div>
                            <div class="rating-scale">
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <div class="rating-option">
                                        <input type="radio" name="post_mood_rating" value="<?php echo $i; ?>" id="post_mood_<?php echo $i; ?>" required>
                                        <label for="post_mood_<?php echo $i; ?>"><?php echo $i; ?></label>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="question-group">
                            <div class="question-text">How would you rate your stress level now? (1 = Not stressed at all, 10 = Extremely stressed)</div>
                            <div class="rating-scale">
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <div class="rating-option">
                                        <input type="radio" name="post_stress_level" value="<?php echo $i; ?>" id="post_stress_<?php echo $i; ?>" required>
                                        <label for="post_stress_<?php echo $i; ?>"><?php echo $i; ?></label>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="question-group">
                            <div class="question-text">How would you rate your anxiety level now? (1 = Not anxious at all, 10 = Extremely anxious)</div>
                            <div class="rating-scale">
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <div class="rating-option">
                                        <input type="radio" name="post_anxiety_level" value="<?php echo $i; ?>" id="post_anxiety_<?php echo $i; ?>" required>
                                        <label for="post_anxiety_<?php echo $i; ?>"><?php echo $i; ?></label>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="question-group">
                            <div class="question-text">Do you feel the session addressed your concerns?</div>
                            <div class="rating-scale">
                                <div class="rating-option">
                                    <input type="radio" name="addressed_concerns" value="Yes" id="concerns_yes" required>
                                    <label for="concerns_yes">Yes</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="addressed_concerns" value="No" id="concerns_no">
                                    <label for="concerns_no">No</label>
                                </div>
                                <div class="rating-option">
                                    <input type="radio" name="addressed_concerns" value="Partially" id="concerns_partial">
                                    <label for="concerns_partial">Partially</label>
                                </div>
                            </div>
                        </div>

                        <div class="question-group">
                            <div class="question-text">What was most helpful for you in today’s session?</div>
                            <textarea name="post_most_helpful" rows="4" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" placeholder="Share what helped you most..."></textarea>
                        </div>

                        <div class="question-group">
                            <div class="question-text">Are there any follow-up actions or support you feel you need?</div>
                            <textarea name="post_follow_up_needs" rows="4" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" placeholder="Let us know any next steps or support you need..."></textarea>
                        </div>
                    <?php endif; ?>

                    <input type="hidden" name="assessment_type" value="<?php echo htmlspecialchars($assessmentType); ?>">
                    <?php if ($sessionId): ?>
                        <input type="hidden" name="session_id" value="<?php echo (int)$sessionId; ?>">
                    <?php endif; ?>

                    <button type="submit" class="submit-btn">Submit Assessment</button>
                </form>
            </div>
        </div>
    </div>

    <script src="js/notifications.js"></script>
    <script>
        function confirmLogout() {
            showConfirm('Are you sure you want to logout?').then((ok) => {
                if (ok) {
                    window.location.href = 'logout.php';
                }
            });
        }
    </script>
</body>
</html>