<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'connect.php';

// Enforce: client must complete post-assessment for last completed session before booking a new one
$clientGuardId = $_SESSION['user_id'];
$lockReasons = [];
$bookingLocked = false;
if ($stmtLast = $conn->prepare("SELECT id FROM sessions WHERE client_id = ? AND status = 'completed' ORDER BY session_date DESC, session_time DESC LIMIT 1")) {
	$stmtLast->bind_param("i", $clientGuardId);
	$stmtLast->execute();
	$resLast = $stmtLast->get_result();
	if ($rowLast = $resLast->fetch_assoc()) {
		$lastId = (int)$rowLast['id'];
        // Check post assessment
		if ($stmtPA = $conn->prepare("SELECT id FROM mental_health_assessments WHERE session_id = ? AND assessment_type = 'post_session' LIMIT 1")) {
			$stmtPA->bind_param("i", $lastId);
			$stmtPA->execute();
			$hasPost = $stmtPA->get_result()->fetch_assoc() !== null;
			$stmtPA->close();
			if (!$hasPost) { $bookingLocked = true; $lockReasons[] = 'Post-Session Assessment'; }
		}
        // No feedback checks required per updated policy
	}
	$stmtLast->close();
}
// If locked, redirect back to my sessions with guidance
if ($bookingLocked) {
	$reasonText = urlencode('Complete '.implode(' and ', $lockReasons).' for your last session first');
	header('Location: my_session.php?error=booking_locked&reason='.$reasonText);
	exit();
}

// Handle session booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && $_POST['step'] === 'questions') {
    $client_id = $_SESSION['user_id'];
    $reason = $_POST['reason'] ?? '';
    $symptoms = $_POST['symptoms'] ?? '';
    $session_date = $_POST['session_date'] ?? date('Y-m-d');
    $session_time = $_POST['session_time'] ?? date('H:i:s');
    $notes = "Reason: $reason\nSymptoms: $symptoms";
    $therapistId = isset($_POST['therapist_id']) ? (int)$_POST['therapist_id'] : 0;

    // Double-check guard right before inserting (anti-bypass)
    $recheckLocked = false; $reasons = [];
    if ($stmtLast2 = $conn->prepare("SELECT id FROM sessions WHERE client_id = ? AND status = 'completed' ORDER BY session_date DESC, session_time DESC LIMIT 1")) {
        $stmtLast2->bind_param("i", $client_id);
        $stmtLast2->execute();
        $resLast2 = $stmtLast2->get_result();
        if ($row2 = $resLast2->fetch_assoc()) {
            $lastId2 = (int)$row2['id'];
            if ($stmtPA2 = $conn->prepare("SELECT id FROM mental_health_assessments WHERE session_id = ? AND assessment_type = 'post_session' LIMIT 1")) {
                $stmtPA2->bind_param("i", $lastId2);
                $stmtPA2->execute();
                $hasPost2 = $stmtPA2->get_result()->fetch_assoc() !== null;
                $stmtPA2->close();
                if (!$hasPost2) { $recheckLocked = true; $reasons[] = 'Post-Session Assessment'; }
            }
            // No feedback checks required per updated policy
        }
        $stmtLast2->close();
    }
    if ($recheckLocked) {
        $booking_error = 'Booking locked. Please complete '.implode(' and ', $reasons).' for your last session first.';
    } else {
    // Create a single session and attach to selected therapist so only they see it
    $sql = "INSERT INTO sessions (client_id, therapist_id, session_date, session_time, status, notes) 
            VALUES (?, ?, ?, ?, 'pending', ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisss", $client_id, $therapistId, $session_date, $session_time, $notes);
    
    if ($stmt->execute()) {
        $sessionId = $conn->insert_id;
        
        // Send notification to therapist about pending appointment
        require_once 'includes/EmailNotifications.php';
        $emailNotifications = new EmailNotifications();
        
        // Get therapist details
        $therapistSql = "SELECT email, first_name FROM users WHERE id = ?";
        $therapistStmt = $conn->prepare($therapistSql);
        $therapistStmt->bind_param("i", $therapistId);
        $therapistStmt->execute();
        $therapistResult = $therapistStmt->get_result();
        $therapist = $therapistResult->fetch_assoc();
        $therapistStmt->close();
        
        // Get client details
        $clientSql = "SELECT first_name FROM users WHERE id = ?";
        $clientStmt = $conn->prepare($clientSql);
        $clientStmt->bind_param("i", $client_id);
        $clientStmt->execute();
        $clientResult = $clientStmt->get_result();
        $client = $clientResult->fetch_assoc();
        $clientStmt->close();
        
        if ($therapist && $client) {
            $emailNotifications->sendAppointmentPendingNotification(
                $therapist['email'],
                $therapist['first_name'],
                $client['first_name'],
                $session_date,
                $session_time,
                $sessionId
            );
        }
        
        header('Location: my_session.php');
        exit();
    } else {
        $booking_error = $stmt->error;
    }
    }
}

// Get all therapists for selection
$sql = "SELECT id, first_name, last_name, profile_picture FROM users WHERE role = 'therapist' ORDER BY first_name, last_name";
$result = $conn->query($sql);
$therapists = $result->fetch_all(MYSQLI_ASSOC);

// Fetch profile info for sidebar
$clientId = $_SESSION['user_id'];
$profileSql = "SELECT first_name, profile_picture, role FROM users WHERE id = ?";
$profileStmt = $conn->prepare($profileSql);
$profileStmt->bind_param("i", $clientId);
$profileStmt->execute();
$profileRes = $profileStmt->get_result();
$profile = $profileRes->fetch_assoc();
$firstName = $profile['first_name'] ?? '';
$profilePicture = $profile['profile_picture'] ?? 'images/profile/default_images/default_profile.png';
$userRole = $profile['role'] ?? 'client';
$profileStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Session - Mind Matters</title>
    <?php 
    $css_version_global = file_exists('styles/global.css') ? filemtime('styles/global.css') : time();
    $css_version_dashboard = file_exists('styles/dashboard.css') ? filemtime('styles/dashboard.css') : time();
    $css_version_mobile = file_exists('styles/mobile.css') ? filemtime('styles/mobile.css') : time();
    $css_version_notifications = file_exists('styles/notifications.css') ? filemtime('styles/notifications.css') : time();
    $css_available_doctors = file_exists('styles/available_doctors.css') ? filemtime('styles/available_doctors.css') : time();
    $js_version_mobile = file_exists('js/mobile.js') ? filemtime('js/mobile.js') : time();
    $js_version_notifications = file_exists('js/notifications.js') ? filemtime('js/notifications.js') : time();
    ?>
    <link rel="stylesheet" href="styles/global.css?v=<?php echo $css_version_global; ?>">
    <link rel="stylesheet" href="styles/dashboard.css?v=<?php echo $css_version_dashboard; ?>">
    <link rel="stylesheet" href="styles/notifications.css?v=<?php echo $css_version_notifications; ?>">
    <link rel="stylesheet" href="styles/mobile.css?v=<?php echo $css_version_mobile; ?>">
    <link rel="stylesheet" href="styles/available_doctors.css?v=<?php echo $css_available_doctors; ?>">
    <script src="js/notifications.js?v=<?php echo $js_version_notifications; ?>"></script>
    <script src="js/mobile.js?v=<?php echo $js_version_mobile; ?>"></script>
    <style>
        .booking-form, .booking-success { max-width: 800px; margin: 20px 0; background: #fff; border-radius: 0; box-shadow: 0 4px 8px rgba(0,0,0,0.08); padding: 30px; }
        .booking-form label { font-weight: 600; margin-top: 10px; display: block; }
        .booking-form input, .booking-form textarea { width: 100%; padding: 12px 14px; border-radius: 8px; border: 2px solid #cbd5e1; margin-bottom: 15px; }
        .booking-form textarea { font-size: 16px; line-height: 1.6; font-family: 'Source Sans Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; color:#0f172a; background:#ffffff; min-height: 140px; resize: vertical; box-shadow: 0 2px 10px rgba(2,6,23,0.04); }
        .booking-form textarea::placeholder { color:#64748b; opacity:1; }
        .booking-form textarea:focus { border-color:#1D5D9B; outline:none; box-shadow: 0 0 0 4px rgba(29,93,155,0.12); }
        .booking-form button { background: #1D5D9B; color: #fff; border: none; padding: 12px 25px; border-radius: 0; font-weight: 600; font-size: 1em; cursor: pointer; transition: background 0.2s; margin-top: 14px; }
        .booking-form button:hover { background: #14487a; }
        .booking-success { text-align: center; }
        .booking-success h2 { color: #1D5D9B; }
        .booking-success a { display: inline-block; margin-top: 20px; background: #1D5D9B; color: #fff; padding: 10px 22px; border-radius: 0; text-decoration: none; font-weight: 600; }
        .booking-success a:hover { background: #14487a; }
        .datetime-group { display: flex; gap: 15px; }
        .datetime-group > div { flex: 1; }
        .datetime-group { margin-bottom: 8px; }
        .styled-select { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; background: #ffffff; color: #334155; font-size: 14px; }
        .styled-select:disabled { background: #f1f5f9; color: #94a3b8; cursor: not-allowed; }
        /* Simple table for availability */
        .avail-table { width:100%; border-collapse: separate; border-spacing:0; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; box-shadow: 0 6px 20px rgba(2,6,23,0.06); background:#ffffff; }
        .avail-table thead th { background: linear-gradient(180deg,#f1f5f9, #e9eef6); color:#0f172a; font-weight:800; text-transform: uppercase; letter-spacing:.4px; font-size:12px; border-bottom:1px solid #dde3ec; }
        .avail-table th, .avail-table td { padding:12px 14px; text-align:left; }
        .avail-table tbody td { font-size:14px; color:#0f172a; border-bottom:1px solid #eef2f7; }
        .avail-table tbody tr:nth-child(odd) { background:#fbfdff; }
        .avail-table tbody tr:nth-child(even) { background:#f7fbff; }
        .avail-table tbody tr:hover { background:#eef6ff; transition: background .15s ease; }
        .avail-table tr:last-child td { border-bottom:none; }
        .avail-table td:nth-child(2) { font-weight:800; color:#1D5D9B; }
        .avail-table td:nth-child(4) { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; letter-spacing:.2px; }
        /* Prevent horizontal scroll and fit content */
        html, body { max-width: 100%; overflow-x: hidden; }
        .dbContainer, .dbMainContent, .booking-form { max-width: 100%; }
        .booking-form input, .booking-form textarea, .booking-form select { max-width: 100%; }
        .avail-table { table-layout: fixed; }
        .avail-table th:nth-child(1), .avail-table td:nth-child(1) { width: 22%; }
        .avail-table th:nth-child(2), .avail-table td:nth-child(2) { width: 18%; }
        .avail-table th:nth-child(3), .avail-table td:nth-child(3) { width: 25%; }
        .avail-table th:nth-child(4), .avail-table td:nth-child(4) { width: 35%; }
        .avail-table td:nth-child(4) { white-space: nowrap; font-size: 15px; font-weight: 700; color:#0f172a; letter-spacing:.2px; font-family: 'Source Sans Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; font-variant-numeric: tabular-nums; }
        .avail-table .time-range { display:inline-block; padding:6px 10px; background:#eef6ff; border:1px solid #dbeafe; border-radius:999px; }
        /* Mobile header positioning overrides to keep burger within header */
        @media (max-width: 768px) {
            .mobile-header {
              position: fixed !important;
              top: 0 !important;
              left: 0 !important;
              right: 0 !important;
              z-index: 1000 !important;
              background: linear-gradient(135deg, #1D5D9B 0%, #14487a 100%) !important;
              color: white !important;
              padding: 1rem !important;
              box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1) !important;
              border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
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
              color: white !important;
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
            .mobile-user-info {
              display: flex !important;
              align-items: center !important;
              gap: 0.5rem !important;
              flex-shrink: 0 !important;
              justify-self: end !important;
            }
            .mobile-user-avatar { width: 32px !important; height: 32px !important; border-radius: 50% !important; object-fit: cover !important; border: 2px solid rgba(255, 255, 255, 0.3) !important; }
            .mobile-user-name { color: white !important; font-weight: 600 !important; font-size: 0.9rem !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; max-width: 80px !important; }
            .dbMainContent { margin-top: 80px !important; }
        }
        @media (max-width: 768px){
            .datetime-group { flex-direction: column; gap: 10px; }
            .dbMainContent { margin-top: 80px; }
            /* Ensure availability table fits on small screens */
            .avail-table { width: 100%; table-layout: fixed; }
            .avail-table th, .avail-table td { padding: 10px 10px; }
            .avail-table th:nth-child(1), .avail-table td:nth-child(1) { width: 24%; }
            .avail-table th:nth-child(2), .avail-table td:nth-child(2) { width: 18%; }
            .avail-table th:nth-child(3), .avail-table td:nth-child(3) { width: 24%; }
            .avail-table th:nth-child(4), .avail-table td:nth-child(4) { width: 34%; }
            .avail-table td:nth-child(4) { white-space: normal; }
            .avail-table .time-range { display: inline-block; width: 100%; text-align: center; padding: 6px 8px; font-size: 14px; box-sizing: border-box; }
        }
        /* Enhanced Therapist Picker styles */
        .therapist-select { appearance:none; -webkit-appearance:none; background:#ffffff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%231D5D9B' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 12px center/18px; border:2px solid #cbd5e1; border-radius:12px; padding:12px 44px 12px 14px; font-weight:600; color:#0f172a; box-shadow: 0 4px 18px rgba(2,6,23,0.04); transition: box-shadow .2s, border-color .2s; }
        .therapist-select:focus { border-color:#1D5D9B; box-shadow: 0 8px 26px rgba(29,93,155,.15); outline:none; }
        .therapist-select:hover { border-color:#94a3b8; }
        #therapistInfo { display:none; margin-top:16px; }
        #therapistInfo .tp-card { display:grid; grid-template-columns: 72px 1fr; gap:14px; align-items:center; padding:16px; border:1px solid #e2e8f0; border-radius:14px; background:#ffffff; box-shadow: 0 8px 24px rgba(2,6,23,0.06); }
        #therapistInfo .tp-avatar { width:72px; height:72px; border-radius:50%; object-fit:cover; border:3px solid rgba(29,93,155,0.15); box-shadow: 0 8px 18px rgba(29,93,155,0.18); }
        #therapistInfo .tp-name { font-size:1.15rem; font-weight:800; color:#0f172a; letter-spacing:0.2px; }
        #therapistInfo .tp-sub { color:#475569; font-weight:600; font-size:.9rem; }
        #therapistInfo .tp-meta { display:flex; flex-wrap:wrap; gap:10px 16px; margin-top:8px; }
        #therapistInfo .tp-badge { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; background:#f1f5f9; color:#0f172a; border:1px solid #e2e8f0; border-radius:999px; font-size:.82rem; font-weight:700; white-space:nowrap; }
        #therapistInfo .tp-badge .star { color:#f59e0b; }
        #therapistInfo .tp-details { margin-top:12px; display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
        #therapistInfo .tp-detail { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; color:#0f172a; font-size:.92rem; }
        @media (max-width: 640px){
          #therapistInfo .tp-card { grid-template-columns: 56px 1fr; }
          #therapistInfo .tp-avatar { width:56px; height:56px; }
          #therapistInfo .tp-details { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="dbBody">
    <!-- Mobile Header (same as dashboard) -->
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
                <span class="mobile-user-name"><?php echo htmlspecialchars($firstName); ?></span>
            </div>
        </div>
    </div>

    <!-- Logout Confirm Modal (unified) -->
    <div id="logoutConfirmModal" style="display:none; position:fixed; z-index:12000; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
        <div class="modal-content" style="width:90%; max-width:460px; border-radius:12px; background:#ffffff; box-shadow:0 20px 60px rgba(0,0,0,0.15); padding:0; border:none; overflow:hidden;">
            <div class="modal-header" style="display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid rgba(0,0,0,0.06); background:linear-gradient(135deg, #1D5D9B, #14487a); color:#ffffff;">
                <h3 style="margin:0; color:#ffffff !important; font-size:1rem; line-height:1.2;">Confirm Logout</h3>
                <span class="close" onclick="closeLogoutConfirm()" style="cursor:pointer">&times;</span>
            </div>
            <div class="modal-body" style="padding:20px; color:#333;">
                <p>Are you sure you want to logout?</p>
            </div>
            <div class="modal-actions" style="display:flex; gap:10px; justify-content:flex-end; padding:0 20px 20px 20px;">
                <button type="button" class="cancel-btn" onclick="closeLogoutConfirm()" style="appearance:none; -webkit-appearance:none; border:0; border-radius:10px; padding:0.65rem 1.1rem; font-weight:700; font-size:0.95rem; cursor:pointer; background:#f1f3f5; color:#1D3557;">Cancel</button>
                <button type="button" class="submit-btn" id="logoutConfirmOk" style="appearance:none; -webkit-appearance:none; border:0; border-radius:10px; padding:0.65rem 1.1rem; font-weight:700; font-size:0.95rem; cursor:pointer; background:linear-gradient(135deg,#1D5D9B,#14487a); color:#fff;">Logout</button>
            </div>
        </div>
    </div>

    <!-- Mobile Menu Overlay -->
    <div class="mobile-menu-overlay" id="mobileMenuOverlay" onclick="closeMobileMenu()"></div>
    <div class="dbContainer">
        <div class="dbSidebar">
            <div class="sidebarProfile">
                <img src="<?php echo htmlspecialchars($profilePicture); ?>" alt="Profile Picture" class="defaultPicture" id="profilePic">
                <h1 class="profileName"><?php echo htmlspecialchars($firstName); ?></h1>
                <p class="userRole"><?php echo ucfirst($userRole); ?></p>
            </div>
            <ul class="sidebarNavList">
                <li class="sidebarNavItem"><a href="dashboard.php" class="sidebarNavLink">Home</a></li>
                <li class="sidebarNavItem"><a href="my_session.php" class="sidebarNavLink">My Session</a></li>
                <li class="sidebarNavItem"><a href="analytics_dashboard.php" class="sidebarNavLink">Analytics</a></li>
                <li class="sidebarNavItem"><a href="resources.php" class="sidebarNavLink">Resources and Guide</a></li>
                <li class="sidebarNavItem"><a href="community_forum.php" class="sidebarNavLink">Community Forum</a></li>
                <li class="sidebarNavItem"><a href="therapy_support.php" class="sidebarNavLink">Therapy Support</a></li>
                <li class="sidebarNavItem"><a href="student_messages.php" class="sidebarNavLink">Messages</a></li>
                <li class="sidebarNavItem"><a href="profile_settings.php" class="sidebarNavLink">Settings</a></li>
            </ul>
            <div class="sidebarFooter">
                <button type="button" class="logoutButton" onclick="openLogoutConfirm()">Logout</button>
            </div>
        </div>

        <div class="dbMainContent">
            <?php if (!isset($booking_success)): ?>
                <!-- Therapist Selection -->
                <div class="booking-form" id="therapistPicker">
                    <h2>Select a Therapist</h2>
                    <select id="therapist_id" class="styled-select therapist-select">
                        <option value="">-- Choose Therapist --</option>
                        <?php foreach ($therapists as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small>Selecting a therapist will load their available dates and times.</small>
                    <div id="therapistInfo"></div>
                    <div id="therapistSchedule">
                        <h3>Upcoming Availability (next 4 weeks)</h3>
                        <table class="avail-table">
                            <thead>
                                <tr><th>Month</th><th>Day</th><th>Date</th><th>Time</th></tr>
                            </thead>
                            <tbody id="availRows"><tr><td colspan="3">Select a therapist to view schedule.</td></tr></tbody>
                        </table>
                    </div>
                </div>

                <!-- Teleconsultation Request Form -->
                <form class="booking-form" method="POST" id="bookingForm">
                    <h2>Request a Teleconsultation</h2>
                    <input type="hidden" name="step" value="questions">
                    <input type="hidden" name="therapist_id" id="selected_therapist_id" value="">
                    <label for="reason">Reason for Consultation</label>
                    <textarea id="reason" name="reason" rows="3" required></textarea>
                    <label for="symptoms">Describe your symptoms</label>
                    <textarea id="symptoms" name="symptoms" rows="3" required></textarea>
                    <div class="datetime-group">
                        <div>
                            <label for="session_date">Preferred Date</label>
                            <select id="session_date" name="session_date" class="styled-select" required disabled></select>
                        </div>
                        <div>
                            <label for="session_time">Preferred Time</label>
                            <select id="session_time" name="session_time" class="styled-select" required disabled></select>
                        </div>
                    </div>
                    <button type="submit" id="submitBtn" disabled>Submit Request</button>
                </form>
            <?php elseif (isset($booking_success) && $booking_success): ?>
                <!-- Success Message -->
                <div class="booking-success">
                    <h2>Request Submitted Successfully!</h2>
                    <p>Your teleconsultation request has been sent. You will be notified when a doctor accepts your request.</p>
                    <a href="my_session.php">Go to My Sessions</a>
                </div>
            <?php elseif (isset($booking_error)): ?>
                <div class="booking-success">
                    <h2>Request Failed</h2>
                    <p><?php echo htmlspecialchars($booking_error); ?></p>
                    <a href="book_session.php">Try Again</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    // Logout modal handlers (aligned with dashboard)
    function openLogoutConfirm() {
        const modal = document.getElementById('logoutConfirmModal');
        const okBtn = document.getElementById('logoutConfirmOk');
        if (!modal || !okBtn) return;
        modal.classList.add('show');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        document.body.classList.add('modal-open');
        try { if (typeof closeMobileMenu === 'function') closeMobileMenu(); } catch(e) {}
        try {
            const overlay = document.getElementById('mobileMenuOverlay');
            const sidebar = document.querySelector('.dbSidebar');
            if (overlay) overlay.classList.remove('active');
            if (sidebar) sidebar.classList.remove('mobile-open');
        } catch(e) {}
        const prevTransform = document.body.style.transform || '';
        const prevBackground = document.body.style.background || '';
        document.body.setAttribute('data-prev-transform', prevTransform);
        document.body.setAttribute('data-prev-bg', prevBackground);
        document.body.style.transform = 'none';
        document.body.style.background = '';
        const onOk = ()=>{ cleanup(); window.location.href='logout.php'; };
        const onCancel = ()=>{ cleanup(); };
        function cleanup(){
            modal.classList.remove('show');
            modal.style.display = 'none';
            document.body.style.overflow = '';
            document.body.classList.remove('modal-open');
            okBtn.removeEventListener('click', onOk);
            modal.removeEventListener('click', onBackdrop);
            document.removeEventListener('keydown', onEsc);
            const t = document.body.getAttribute('data-prev-transform');
            const b = document.body.getAttribute('data-prev-bg');
            document.body.style.transform = t || '';
            document.body.style.background = b || '';
            document.body.removeAttribute('data-prev-transform');
            document.body.removeAttribute('data-prev-bg');
        }
        function onBackdrop(e){ if(e.target===modal){ onCancel(); } }
        function onEsc(e){ if(e.key==='Escape'){ onCancel(); } }
        okBtn.addEventListener('click', onOk);
        modal.addEventListener('click', onBackdrop);
        document.addEventListener('keydown', onEsc);
        window.closeLogoutConfirm = onCancel;
    }
    function closeLogoutConfirm(){ if (typeof window.closeLogoutConfirm === 'function') window.closeLogoutConfirm(); }

    // Mobile menu functions (aligned with dashboard)
    function toggleMobileMenu() {
        const sidebar = document.querySelector('.dbSidebar');
        const overlay = document.getElementById('mobileMenuOverlay');
        const body = document.body;
        if (sidebar && overlay) {
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');
            body.classList.toggle('mobile-menu-open');
        }
    }
    function closeMobileMenu() {
        const sidebar = document.querySelector('.dbSidebar');
        const overlay = document.getElementById('mobileMenuOverlay');
        const body = document.body;
        if (sidebar && overlay) {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
            body.classList.remove('mobile-menu-open');
        }
    }
    </script>
    <script>
      const therapistSelect = document.getElementById('therapist_id');
      const dateInput = document.getElementById('session_date');
      const timeSelect = document.getElementById('session_time');
      const submitBtn = document.getElementById('submitBtn');
      const selectedTherapistIdInput = document.getElementById('selected_therapist_id');
		  const therapistInfo = document.getElementById('therapistInfo');

      let availabilityMap = {}; // { 'YYYY-MM-DD': [{start,end}], ... }

      function formatDateLabel(yyyyMmDd){
        const [y,m,d] = yyyyMmDd.split('-').map(s=>parseInt(s,10));
        const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        const label = `${months[m-1]} ${d}. ${y}`;
        return label;
      }

          function renderTherapistInfo(info){
            if (!info) { therapistInfo.style.display='none'; therapistInfo.innerHTML=''; return; }
            const pic = info.profile_picture || 'images/profile/default_images/default_profile.png';
            const name = 'Dr. ' + (info.first_name||'') + ' ' + (info.last_name||'');
            const email = info.email || '';
            const ratingVal = typeof info.avg_rating !== 'undefined' ? Number(info.avg_rating).toFixed(1) : null;
            const ratingCnt = parseInt(info.rating_count||0,10);
            const spec = info.specialization ? `<span class="tp-badge"><span class="star">★</span>${escapeHtml(info.specialization)}</span>` : '';
            const expYears = info.years_of_experience ? `<span class="tp-badge">${parseInt(info.years_of_experience,10)} yrs exp</span>` : '';
            const langs = info.languages_spoken ? `<span class="tp-badge">${escapeHtml(info.languages_spoken)}</span>` : '';
            const license = info.license_number ? `<div class="tp-detail"><strong>License #:</strong> ${escapeHtml(info.license_number)}</div>` : '';
            const contact = info.contact_number ? `<div class="tp-detail"><strong>Contact:</strong> ${escapeHtml(info.contact_number)}</div>` : '';
            const rating = ratingVal !== null ? `<span class="tp-badge"><span class="star">★</span>${ratingVal} (${ratingCnt})</span>` : '';
            therapistInfo.innerHTML = `
              <div class="tp-card">
                <img class="tp-avatar" src="${escapeAttr(pic)}" alt="Avatar"/>
                <div>
                  <div class="tp-name">${escapeHtml(name)}</div>
                  <div class="tp-sub">${escapeHtml(email)}</div>
                  <div class="tp-meta">${rating}${spec}${expYears}${langs}</div>
                  <div class="tp-details">${license}${contact}</div>
                </div>
              </div>
            `;
            therapistInfo.style.display = 'block';
          }

		  function escapeHtml(s){ return String(s).replace(/[&<>"']/g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c])); }
		  function escapeAttr(s){ return String(s).replace(/"/g,'&quot;'); }

		  therapistSelect.addEventListener('change', () => {
        const tid = therapistSelect.value;
        selectedTherapistIdInput.value = tid;
        dateInput.innerHTML = '';
        timeSelect.innerHTML = '';
        dateInput.disabled = !tid;
        timeSelect.disabled = true;
        submitBtn.disabled = true;
			if (!tid) { renderTherapistInfo(null); return; }

			// Load therapist profile details
			fetch('get_therapist_profile.php?therapist_id=' + encodeURIComponent(tid))
			  .then(r=>r.json())
			  .then(d=>{ if (d && d.success) renderTherapistInfo(d.therapist); else renderTherapistInfo(null); })
			  .catch(()=>{ renderTherapistInfo(null); });

        fetch('get_therapist_availability.php?therapist_id=' + tid)
          .then(r=>r.json())
          .then(d=>{
            if(!d.success){ alert('Failed to load availability'); return; }
            // Build date->slots map
            availabilityMap = {};
            const days = d.data || {};
            // Render table rows and date options
            const rows = document.getElementById('availRows');
            rows.innerHTML = '';
            Object.keys(days).forEach(day=>{
              (days[day]||[]).forEach(slot=>{
                const date = slot.date;
                if(!availabilityMap[date]) availabilityMap[date] = [];
                availabilityMap[date].push({start:slot.start_time, end:slot.end_time, startL:slot.start_time_label, endL:slot.end_time_label});
              });
            });
            const datesSorted = Object.keys(availabilityMap).sort();
            if (datesSorted.length === 0) {
                rows.innerHTML = '<tr><td colspan="4">No availability found.</td></tr>';
            } else {
                datesSorted.forEach(d=>{
                    const dt = new Date(d);
                    const month = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][dt.getMonth()];
                    const weekday = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][dt.getDay()];
                    const dateStr = `${month} ${dt.getDate()}. ${dt.getFullYear()}`;
                    (availabilityMap[d]||[]).forEach(w=>{
                        const tr = document.createElement('tr');
                        tr.innerHTML = `<td>${month}</td><td>${weekday}</td><td>${dt.getDate()}. ${dt.getFullYear()}</td><td><span class="time-range">${w.startL} – ${w.endL}</span></td>`;
                        rows.appendChild(tr);
                    });
                });
            }
            // All candidate dates from availability windows
            const allDates = Object.keys(availabilityMap).sort();
            // Try to find the first date that still has free one-hour slots (after filtering booked)
            pickFirstAvailableDate(allDates, tid).then(({goodDate, options})=>{
              // Build the date dropdown but only include dates that have at least one free slot
              const dateHasOptions = new Set();
              if (goodDate) dateHasOptions.add(goodDate);
              // Optionally pre-compute others in background
              buildDateDropdown(allDates, tid, dateHasOptions).then((finalDates)=>{
                dateInput.innerHTML = '<option value="">-- Select Date --</option>' + finalDates.map(d=>`<option value="${d}">${formatDateLabel(d)}</option>`).join('');
                dateInput.disabled = finalDates.length === 0;
                if (goodDate && options && options.length){
                  dateInput.value = goodDate;
                  populateTimes(options);
                } else {
                  timeSelect.innerHTML = '<option value="">No available times</option>';
                  timeSelect.disabled = true; submitBtn.disabled = true;
                }
              });
            });
          });
      });

      dateInput.addEventListener('change', onDateChange);
      function onDateChange(){
        const date = dateInput.value;
        timeSelect.innerHTML = '';
        submitBtn.disabled = true;
        if (!date || !availabilityMap[date]) { timeSelect.disabled = true; return; }
        fetchTimesForDate(date, therapistSelect.value).then((options)=>{
          populateTimes(options);
        });
      }

      function populateTimes(options){
        if(options.length > 0) {
          timeSelect.innerHTML = options.map(o=>`<option value="${o.value}">${o.label}</option>`).join('');
          timeSelect.value = options[0].value; // Auto-select first available time
          timeSelect.disabled = false; 
          submitBtn.disabled = false; // Enable submit since time is selected
        } else {
          timeSelect.innerHTML = '<option value="">No available times</option>';
          timeSelect.disabled = true; 
          submitBtn.disabled = true;
        }
        timeSelect.onchange = ()=>{ submitBtn.disabled = !timeSelect.value; };
      }

      async function pickFirstAvailableDate(dates, therapistId){
        for (const d of dates){
          const options = await fetchTimesForDate(d, therapistId);
          if (options.length) { return {goodDate: d, options}; }
        }
        return {goodDate: null, options: []};
      }

      async function buildDateDropdown(dates, therapistId, seedSet){
        const out = [];
        for (const d of dates){
          const has = seedSet && seedSet.has(d) ? true : (await fetchTimesForDate(d, therapistId)).length > 0;
          if (has) out.push(d);
        }
        return out;
      }

      async function fetchTimesForDate(date, therapistId){
        // Generate one-hour starts inside availability windows and remove already booked
        const windows = availabilityMap[date] || [];
        if (!windows.length) return [];
        const res = await fetch('get_booked_slots.php?therapist_id=' + therapistId + '&session_date=' + date);
        const d = await res.json();
        const booked = new Set((d.slots||[]));
        const options = [];

        // Determine if selected date is today; if so, filter out past times relative to now
        const now = new Date();
        const y = now.getFullYear();
        const m = String(now.getMonth()+1).padStart(2,'0');
        const dd = String(now.getDate()).padStart(2,'0');
        const todayStr = `${y}-${m}-${dd}`;
        const isToday = (date === todayStr);
        const nowMinutes = now.getHours()*60 + now.getMinutes();

        windows.forEach(w=>{
          let t = toMinutes(w.start);
          const end = toMinutes(w.end);
          while (t + 60 <= end) {
            if (isToday && t <= nowMinutes) { t += 60; continue; }
            const hhmmss = fromMinutes(t);
            if (!booked.has(hhmmss)) { options.push({value: hhmmss, label: toLabel(hhmmss)}); }
            t += 60;
          }
        });
        return options;
      }

      function toMinutes(hms){ const [h,m] = hms.split(':'); return parseInt(h)*60+parseInt(m); }
      function fromMinutes(min){ const h = Math.floor(min/60).toString().padStart(2,'0'); const m = (min%60).toString().padStart(2,'0'); return `${h}:${m}:00`; }
      function toLabel(hms){ const [h,m] = hms.split(':'); const d = new Date(); d.setHours(parseInt(h), parseInt(m), 0, 0); return d.toLocaleTimeString([], {hour:'numeric', minute:'2-digit'}); }
      function isBlocked(hhmmss, bookedSet){
        // if exact hour start is booked, block this hour and next becomes available
        if (bookedSet.has(hhmmss)) return true;
        return false;
      }
    </script>
</body>
</html> 