<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'connect.php';

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'therapist';

// Fetch profile info for sidebar
$sqlProfile = "SELECT first_name, profile_picture, gender FROM users WHERE id = ?";
$stmtProfile = $conn->prepare($sqlProfile);
$stmtProfile->bind_param("i", $userId);
$stmtProfile->execute();
$resProfile = $stmtProfile->get_result();
$me = $resProfile->fetch_assoc();
$stmtProfile->close();
$firstName = $me['first_name'] ?? ($_SESSION['first_name'] ?? '');
$profilePicture = $me['profile_picture'] ?? ($me['gender'] === 'female' ? 'images/profile/default_images/female_gender.png' : 'images/profile/default_images/male_gender.png');

// Redirect if not a therapist
if ($userRole !== 'therapist') {
    header('Location: dashboard.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_schedule') {
        $dayOfWeek = $_POST['day_of_week'];
        $startTime = $_POST['start_time'];
        $endTime = $_POST['end_time'];
        $isAvailable = isset($_POST['is_available']) ? 1 : 0;

        // Check if schedule already exists
        $checkSql = "SELECT id FROM doctor_availability WHERE therapist_id = ? AND day_of_week = ? AND start_time = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("iss", $userId, $dayOfWeek, $startTime);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if (!$existing) {
            $sql = "INSERT INTO doctor_availability (therapist_id, day_of_week, start_time, end_time, is_available) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssi", $userId, $dayOfWeek, $startTime, $endTime, $isAvailable);
            
            if ($stmt->execute()) {
                $successMessage = "Schedule added successfully!";
            } else {
                $errorMessage = "Error adding schedule: " . $conn->error;
            }
            $stmt->close();
        } else {
            $errorMessage = "A schedule already exists for this day and time.";
        }
    } elseif ($action === 'update_schedule') {
        $scheduleId = (int)$_POST['schedule_id'];
        $startTime = $_POST['start_time'];
        $endTime = $_POST['end_time'];
        $isAvailable = isset($_POST['is_available']) ? 1 : 0;

        $sql = "UPDATE doctor_availability SET start_time = ?, end_time = ?, is_available = ? WHERE id = ? AND therapist_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiii", $startTime, $endTime, $isAvailable, $scheduleId, $userId);
        
        if ($stmt->execute()) {
            $successMessage = "Schedule updated successfully!";
        } else {
            $errorMessage = "Error updating schedule: " . $conn->error;
        }
        $stmt->close();
    } elseif ($action === 'delete_schedule') {
        $scheduleId = (int)$_POST['schedule_id'];

        $sql = "DELETE FROM doctor_availability WHERE id = ? AND therapist_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $scheduleId, $userId);
        
        if ($stmt->execute()) {
            $successMessage = "Schedule deleted successfully!";
        } else {
            $errorMessage = "Error deleting schedule: " . $conn->error;
        }
        $stmt->close();
    }
}

// Get current availability schedule
$sql = "SELECT * FROM doctor_availability WHERE therapist_id = ? ORDER BY day_of_week, start_time";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$schedules = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Group schedules by day
$schedulesByDay = [];
foreach ($schedules as $schedule) {
    $schedulesByDay[$schedule['day_of_week']][] = $schedule;
}

$daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
$dayNames = [
    'monday' => 'Monday',
    'tuesday' => 'Tuesday', 
    'wednesday' => 'Wednesday',
    'thursday' => 'Thursday',
    'friday' => 'Friday',
    'saturday' => 'Saturday',
    'sunday' => 'Sunday'
];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Therapist Schedule - Mind Matters</title>
    <link rel="stylesheet" href="styles/global.css">
    <link rel="stylesheet" href="styles/dashboard.css">
    <link rel="stylesheet" href="styles/doctor_schedule.css">
    <link rel="stylesheet" href="styles/notifications.css">
    <link rel="stylesheet" href="styles/mobile.css">
	<script src="js/mobile.js"></script>
    <style>
        /* Modern Modal Styling */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease-out;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            position: relative;
            animation: slideIn 0.3s ease-out;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e9ecef;
        }

        .modal-header h3 {
            margin: 0;
            color: #1D3557;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: #666;
            font-size: 1.8rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
            min-width: 44px;
            min-height: 44px;
            touch-action: manipulation;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            background-color: #f8f9fa;
            color: #333;
            transform: scale(1.1);
        }

        .modal-close:active {
            transform: scale(0.95);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .form-group input[type="time"],
        .form-group input[type="checkbox"] {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input[type="time"]:focus {
            outline: none;
            border-color: #1D5D9B;
            box-shadow: 0 0 0 3px rgba(29, 93, 155, 0.1);
        }

        .form-group input[type="checkbox"] {
            width: auto;
            margin-right: 0.5rem;
        }

        .add-btn {
            background: linear-gradient(135deg, #1D5D9B, #14487a);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        .add-btn:hover {
            background: linear-gradient(135deg, #14487a, #0d3a5f);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(29, 93, 155, 0.3);
        }

        .add-btn:active {
            transform: translateY(0);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { 
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to { 
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 1rem;
                padding: 1.5rem;
                max-height: 95vh;
            }

            .modal-header h3 {
                font-size: 1.25rem;
            }

            .modal-close {
                font-size: 1.5rem;
                min-width: 40px;
                min-height: 40px;
            }

            .form-group input[type="time"] {
                padding: 1rem;
                font-size: 1.1rem;
            }

            .add-btn {
                padding: 1rem;
                font-size: 1.1rem;
            }
            
            /* Mobile Menu Button (burger) */
            .mobile-menu-btn {
                position: relative !important;
                top: 0 !important;
                left: 0 !important;
                z-index: 1 !important;
                background: var(--primary-color) !important;
                border: none !important;
                border-radius: 8px !important;
                width: 44px !important;
                height: 44px !important;
                cursor: pointer !important;
                box-shadow: 0 4px 12px rgba(29, 93, 155, 0.3) !important;
                transition: all 0.3s ease !important;
                flex-direction: column !important;
                justify-content: center !important;
                align-items: center !important;
                padding: 0 !important;
                outline: none !important;
                pointer-events: auto !important;
                touch-action: manipulation !important;
                -webkit-tap-highlight-color: transparent !important;
                -webkit-touch-callout: none !important;
                -webkit-user-select: none !important;
                user-select: none !important;
            }

            .mobile-menu-btn .hamburger {
                width: 20px;
                height: 2px;
                background: #fff;
                margin: 2px 0;
                transition: all 0.3s ease;
                border-radius: 1px;
            }

            .mobile-menu-btn.active .hamburger:nth-child(1) { transform: rotate(45deg) translate(5px, 5px); }
            .mobile-menu-btn.active .hamburger:nth-child(2) { opacity: 0; }
            .mobile-menu-btn.active .hamburger:nth-child(3) { transform: rotate(-45deg) translate(7px, -6px); }

            /* Mobile Menu Overlay */
            .mobile-menu-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 9999;
                opacity: 0;
                transition: opacity 0.3s ease;
            }

            .mobile-menu-overlay.active { display: block; opacity: 1; }

            /* Sidebar slide-in */
            .dbSidebar { transform: translateX(-100%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); width: 280px; z-index: 10001; position: fixed; top: 0; left: 0; height: 100vh; overflow-y: auto; }
            .dbSidebar.mobile-open { transform: translateX(0); }
            .mobile-menu-open .dbSidebar { transform: translateX(0); }
            .mobile-menu-open { overflow: hidden; }
            .mobile-menu-open .dbMainContent { pointer-events: none; }
            
            /* Mobile Schedule Enhancements */
            .day-card {
                margin-bottom: 1rem;
                padding: 1rem;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }
            
            .time-slot {
                padding: 0.75rem;
                margin-bottom: 0.5rem;
                border-radius: 8px;
                background: #f8f9fa;
                border: 1px solid #e9ecef;
            }
            
            .time-info {
                font-size: 0.9rem;
                margin-bottom: 0.5rem;
            }
            
            .schedule-actions {
                display: flex;
                gap: 0.5rem;
                flex-wrap: wrap;
            }
            
            .schedule-actions button {
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
                border-radius: 6px;
                min-width: 44px;
                min-height: 44px;
                touch-action: manipulation;
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
                /* removed extra left padding since button is inside header */
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
                color: white !important;
                font-family: 'Lora', serif !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                justify-self: center !important;
                text-align: center !important;
            }
            
            .mobile-user-info {
                display: flex !important;
                align-items: center !important;
                gap: 0.75rem !important;
            }
            
            .mobile-user-avatar {
                width: 36px !important;
                height: 36px !important;
                border-radius: 50% !important;
                object-fit: cover !important;
                border: 2px solid rgba(255, 255, 255, 0.3) !important;
            }
            
            .mobile-user-name {
                font-weight: 600 !important;
                color: var(--white) !important;
                font-size: 0.9rem !important;
            }
            
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
            }
            
            /* Add top padding to main content to account for fixed header */
            .dbMainContent {
                padding-top: 80px !important;
            }
            
            .schedule-container {
                padding: 1rem !important;
            }
        }
        /* Hide sidebar when a modal is open (align with Messages) */
        @media (max-width: 768px) {
            body.modal-open .dbSidebar { transform: translateX(-100%) !important; }
            body.modal-open #mobileMenuOverlay { display: none !important; opacity: 0 !important; }
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
            padding: 0; /* override generic .modal-content padding */
            border: none; /* ensure no border from global styles */
            overflow: hidden; /* keep header flush to edges */
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
        #logoutConfirmModal .modal-actions .cancel-btn {
            background: #f1f3f5;
            color: #1D3557;
            box-shadow: 0 1px 2px rgba(0,0,0,0.06) inset;
        }
        #logoutConfirmModal .modal-actions .cancel-btn:hover { background: #e9ecef; }
        #logoutConfirmModal .modal-actions .cancel-btn:active { transform: translateY(1px); }
        #logoutConfirmModal .modal-actions .cancel-btn:focus-visible { box-shadow: 0 0 0 3px rgba(29,93,155,0.25); }

        #logoutConfirmModal .modal-actions .submit-btn {
            background: linear-gradient(135deg, #1D5D9B, #14487a);
            color: #ffffff;
            box-shadow: 0 6px 16px rgba(29,93,155,0.25);
        }
        #logoutConfirmModal .modal-actions .submit-btn:hover { background: linear-gradient(135deg, #14487a, #0d3a5f); }
        #logoutConfirmModal .modal-actions .submit-btn:active { transform: translateY(1px); }
        #logoutConfirmModal .modal-actions .submit-btn:focus-visible { box-shadow: 0 0 0 3px rgba(29,93,155,0.35); }
        @media (max-width: 480px) {
            #logoutConfirmModal .modal-actions .cancel-btn,
            #logoutConfirmModal .modal-actions .submit-btn { min-width: 0; padding: 0.7rem 1rem; font-size: 1rem; }
        }
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
                <span class="mobile-user-name"><?php echo htmlspecialchars($firstName); ?></span>
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
                <p class="userRole">Therapist</p>
            </div>
            <ul class="sidebarNavList">
                <li class="sidebarNavItem"><a href="dashboard.php" class="sidebarNavLink">Home</a></li>
                <li class="sidebarNavItem"><a href="appointments.php" class="sidebarNavLink">Appointments</a></li>
                <li class="sidebarNavItem"><a href="pending_requests.php" class="sidebarNavLink">Pending Requests</a></li>
                <li class="sidebarNavItem"><a href="patients.php" class="sidebarNavLink">My Clients</a></li>
                <li class="sidebarNavItem"><a href="patient_tracking.php" class="sidebarNavLink">Client Tracking</a></li>
                <li class="sidebarNavItem"><a href="therapy_support.php" class="sidebarNavLink">Therapy Support</a></li>
                <li class="sidebarNavItem"><a href="community_forum.php" class="sidebarNavLink">Community Forum</a></li>
                <li class="sidebarNavItem"><a href="doctor_schedule.php" class="sidebarNavLink active">Schedule</a></li>
                <li class="sidebarNavItem"><a href="analytics_dashboard.php" class="sidebarNavLink">Analytics</a></li>
                <li class="sidebarNavItem"><a href="student_messages.php" class="sidebarNavLink">Messages</a></li>
                <li class="sidebarNavItem"><a href="profile_settings.php" class="sidebarNavLink">Settings</a></li>
            </ul>
            <div class="sidebarFooter">
            <button type="button" class="logoutButton" onclick="openLogoutConfirm()">Logout</button>
            </div>
        </div>

        <div class="dbMainContent">
            <div class="schedule-container">
                <h2>Therapist Availability Schedule</h2>
                <p>Manage your weekly availability schedule. Students will only be able to book appointments during your available hours.</p>

                <?php if (isset($successMessage)): ?>
                    <div class="success-message"><?php echo htmlspecialchars($successMessage); ?></div>
                <?php endif; ?>

                <?php if (isset($errorMessage)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($errorMessage); ?></div>
                <?php endif; ?>

                <form method="POST" class="schedule-form">
                    <input type="hidden" name="action" value="add_schedule">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="day_of_week">Day of Week</label>
                            <select name="day_of_week" id="day_of_week" required>
                                <option value="">Select day</option>
                                <?php foreach ($daysOfWeek as $day): ?>
                                    <option value="<?php echo $day; ?>"><?php echo $dayNames[$day]; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="start_time">Start Time</label>
                            <input type="time" name="start_time" id="start_time" required>
                        </div>
                        <div class="form-group">
                            <label for="end_time">End Time</label>
                            <input type="time" name="end_time" id="end_time" required>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_available" value="1" checked>
                                Available
                            </label>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="add-btn">Add Schedule</button>
                        </div>
                    </div>
                </form>

                <div class="schedule-display">
                    <h3>Current Schedule</h3>
                    <?php foreach ($daysOfWeek as $day): ?>
                        <div class="day-schedule">
                            <div class="day-header">
                                <div class="day-name"><?php echo $dayNames[$day]; ?></div>
                                <div class="day-status <?php echo isset($schedulesByDay[$day]) && !empty($schedulesByDay[$day]) ? 'available' : 'unavailable'; ?>">
                                    <?php echo isset($schedulesByDay[$day]) && !empty($schedulesByDay[$day]) ? 'Available' : 'No Schedule'; ?>
                                </div>
                            </div>
                            
                            <?php if (isset($schedulesByDay[$day]) && !empty($schedulesByDay[$day])): ?>
                                <div class="time-slots">
                                    <?php foreach ($schedulesByDay[$day] as $schedule): ?>
                                        <div class="time-slot">
                                            <div class="time-info">
                                                <?php echo date('g:i A', strtotime($schedule['start_time'])); ?> - 
                                                <?php echo date('g:i A', strtotime($schedule['end_time'])); ?>
                                                <?php if (!$schedule['is_available']): ?>
                                                    <span>(Unavailable)</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="time-actions">
                                                <button class="edit-btn" onclick="editSchedule(<?php echo $schedule['id']; ?>, '<?php echo $schedule['start_time']; ?>', '<?php echo $schedule['end_time']; ?>', <?php echo $schedule['is_available']; ?>)">Edit</button>
                                                <button class="delete-btn" onclick="deleteSchedule(<?php echo $schedule['id']; ?>)">Delete</button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="no-schedule">No availability set for this day</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
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

    <!-- Edit Schedule Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Schedule</h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="update_schedule">
                <input type="hidden" name="schedule_id" id="edit_schedule_id">
                <div class="form-group">
                    <label for="edit_start_time">Start Time</label>
                    <input type="time" name="start_time" id="edit_start_time" required>
                </div>
                <div class="form-group">
                    <label for="edit_end_time">End Time</label>
                    <input type="time" name="end_time" id="edit_end_time" required>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_available" id="edit_is_available" value="1">
                        Available
                    </label>
                </div>
                <button type="submit" class="add-btn">Update Schedule</button>
            </form>
        </div>
    </div>

    <script src="js/notifications.js"></script>
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
            const body = document.body;
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
        // Close on sidebar link click and Escape
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.sidebarNavLink').forEach(link => {
                link.addEventListener('click', () => closeMobileMenu());
            });
            document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeMobileMenu(); });
        });

        function editSchedule(scheduleId, startTime, endTime, isAvailable) {
            document.getElementById('edit_schedule_id').value = scheduleId;
            document.getElementById('edit_start_time').value = startTime;
            document.getElementById('edit_end_time').value = endTime;
            document.getElementById('edit_is_available').checked = isAvailable == 1;
            
            const modal = document.getElementById('editModal');
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            
            // Add haptic feedback on mobile
            if (window.innerWidth <= 768 && navigator.vibrate) {
                navigator.vibrate(50);
            }
        }
        
        function closeEditModal() {
            const modal = document.getElementById('editModal');
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
            
            // Add haptic feedback on mobile
            if (window.innerWidth <= 768 && navigator.vibrate) {
                navigator.vibrate(50);
            }
        }

        function deleteSchedule(scheduleId) {
            if (confirm('Are you sure you want to delete this schedule?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_schedule">
                    <input type="hidden" name="schedule_id" value="${scheduleId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('editModal');
            
            // Close modal when clicking outside
            modal.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeEditModal();
                }
            });
            
            // Close modal with Escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    if (modal.classList.contains('show')) {
                        closeEditModal();
                    }
                }
            });
            
            // Add touch event listeners for mobile
            const closeBtn = document.querySelector('.modal-close');
            if (closeBtn) {
                closeBtn.addEventListener('touchstart', function(e) {
                    e.preventDefault();
                    closeEditModal();
                });
            }
        });

        // Time validation
        document.getElementById('start_time').addEventListener('change', function() {
            const startTime = this.value;
            const endTimeInput = document.getElementById('end_time');
            if (startTime && endTimeInput.value && startTime >= endTimeInput.value) {
                alert('End time must be after start time');
                endTimeInput.value = '';
            }
        });

        document.getElementById('end_time').addEventListener('change', function() {
            const endTime = this.value;
            const startTimeInput = document.getElementById('start_time');
            if (endTime && startTimeInput.value && endTime <= startTimeInput.value) {
                alert('End time must be after start time');
                this.value = '';
            }
        });
    </script>
</body>
</html>
