<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'connect.php';

$userId = $_SESSION['user_id'];

// Get user's profile picture and role
$sql = "SELECT profile_picture, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$userRole = $user['role'];
$therapistProfilePicture = $user['profile_picture'] ?? 'images/profile/default_images/default_profile.png';
$stmt->close();

// Redirect if not a therapist
if ($userRole !== 'therapist') {
    header('Location: dashboard.php');
    exit();
}

$sessionId = $_GET['session_id'] ?? null;
$clientId = $_GET['client_id'] ?? null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sessionId = (int)$_POST['session_id'];
    $clientId = (int)$_POST['client_id'];
    $notes = $_POST['notes'];
    $diagnosis = $_POST['diagnosis'] ?? '';
    $treatmentPlan = $_POST['treatment_plan'] ?? '';
    $progressStatus = $_POST['progress_status'];
    $nextSessionRecommendations = $_POST['next_session_recommendations'] ?? '';

    $sql = "INSERT INTO doctor_notes (session_id, therapist_id, client_id, notes, diagnosis, treatment_plan, progress_status, next_session_recommendations) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiisssss", $sessionId, $userId, $clientId, $notes, $diagnosis, $treatmentPlan, $progressStatus, $nextSessionRecommendations);

    if ($stmt->execute()) {
        $successMessage = "Notes saved successfully!";
    } else {
        $errorMessage = "Error saving notes: " . $conn->error;
    }
    $stmt->close();
}

// Get session details if session_id is provided
$sessionDetails = null;
if ($sessionId) {
    $sql = "SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) AS client_name, u.email AS client_email 
            FROM sessions s 
            JOIN users u ON s.client_id = u.id 
            WHERE s.id = ? AND s.therapist_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $sessionId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $sessionDetails = $result->fetch_assoc();
    $stmt->close();
}

// Get client details if client_id is provided
$clientDetails = null;
if ($clientId) {
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $clientId);
    $stmt->execute();
    $result = $stmt->get_result();
    $clientDetails = $result->fetch_assoc();
    $stmt->close();
}

// Get recent notes for this client
$recentNotes = [];
if ($clientId) {
    $sql = "SELECT dn.*, s.session_date, s.session_time 
            FROM doctor_notes dn 
            JOIN sessions s ON dn.session_id = s.id 
            WHERE dn.client_id = ? AND dn.therapist_id = ? 
            ORDER BY dn.created_at DESC 
            LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $clientId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $recentNotes = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Therapist Notes - Mind Matters</title>
    <link rel="stylesheet" href="styles/global.css">
    <link rel="stylesheet" href="styles/dashboard.css">
    <link rel="stylesheet" href="styles/notifications.css">
    <style>
        .notes-container {
            padding: 20px;
            max-width: 1000px;
            margin: 0 auto;
        }
        .notes-form {
            background: white;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-section {
            margin-bottom: 25px;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
        }
        .section-title {
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
            font-size: 1.1em;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .submit-btn {
            background: #1D5D9B;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
        }
        .submit-btn:hover {
            background: #14487a;
        }
        .session-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #1D5D9B;
        }
        .recent-notes {
            background: white;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .note-entry {
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .note-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .note-date {
            font-weight: 600;
            color: #333;
        }
        .progress-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: 500;
        }
        .progress-status.improved { background: #d4edda; color: #155724; }
        .progress-status.no_change { background: #fff3cd; color: #856404; }
        .progress-status.declined { background: #f8d7da; color: #721c24; }
        .note-content {
            margin-top: 10px;
        }
        .note-section {
            margin-bottom: 10px;
        }
        .note-section strong {
            color: #333;
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
        
        /* Back Button Styling */
        .back-button-container {
            margin-bottom: 20px;
        }
        
        .back-button {
            background: linear-gradient(135deg, #1D5D9B 0%, #14487a 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(29, 93, 155, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-button:hover {
            background: linear-gradient(135deg, #14487a 0%, #0f3a5c 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(29, 93, 155, 0.4);
        }
        
        .back-button:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(29, 93, 155, 0.3);
        }
    </style>
</head>
<body class="dbBody">
    <div class="dbContainer">
        <div class="dbSidebar">
            <div class="sidebarProfile">
                <img src="<?php echo htmlspecialchars($therapistProfilePicture); ?>" alt="Profile Picture" class="defaultPicture">
                <h1 class="profileName"><?php echo htmlspecialchars($_SESSION['first_name']); ?></h1>
                <p class="userRole"><?php echo ucfirst($userRole); ?></p>
            </div>
            <ul class="sidebarNavList">
                <li class="sidebarNavItem"><a href="dashboard.php" class="sidebarNavLink">Home</a></li>
                <li class="sidebarNavItem"><a href="appointments.php" class="sidebarNavLink">Appointments</a></li>
                <li class="sidebarNavItem"><a href="pending_requests.php" class="sidebarNavLink">Pending Requests</a></li>
                <li class="sidebarNavItem"><a href="patients.php" class="sidebarNavLink">My Clients</a></li>
                <li class="sidebarNavItem"><a href="patient_tracking.php" class="sidebarNavLink">Client Tracking</a></li>
                <li class="sidebarNavItem"><a href="therapy_support.php" class="sidebarNavLink">Therapy Support</a></li>
                <li class="sidebarNavItem"><a href="community_forum.php" class="sidebarNavLink">Community Forum</a></li>
                <li class="sidebarNavItem"><a href="doctor_schedule.php" class="sidebarNavLink">Schedule</a></li>
                <li class="sidebarNavItem"><a href="analytics_dashboard.php" class="sidebarNavLink">Analytics</a></li>
                <li class="sidebarNavItem"><a href="student_messages.php" class="sidebarNavLink">Messages</a></li>
                <li class="sidebarNavItem"><a href="profile_settings.php" class="sidebarNavLink">Settings</a></li>
            </ul>
            <div class="sidebarFooter">
                <button type="button" class="logoutButton" onclick="confirmLogout()">Logout</button>
            </div>
        </div>

        <div class="dbMainContent">
            <div class="notes-container">
                <?php if (isset($_GET['from']) && $_GET['from'] === 'appointments'): ?>
                    <div class="back-button-container">
                        <button class="back-button" onclick="window.location.href='appointments.php'">
                            ← Back to Appointments
                        </button>
                    </div>
                <?php endif; ?>
                <h2>Therapist Notes & Progress Tracking</h2>

                <?php if (isset($successMessage)): ?>
                    <div class="success-message"><?php echo htmlspecialchars($successMessage); ?></div>
                <?php endif; ?>

                <?php if (isset($errorMessage)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($errorMessage); ?></div>
                <?php endif; ?>

                <?php if ($sessionDetails): ?>
                    <div class="session-info">
                        <h3>Session Information</h3>
                        <p><strong>Student:</strong> <?php echo htmlspecialchars($sessionDetails['client_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($sessionDetails['client_email']); ?></p>
                        <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($sessionDetails['session_date'])); ?></p>
                        <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($sessionDetails['session_time'])); ?></p>
                        <p><strong>Status:</strong> <?php echo ucfirst($sessionDetails['status']); ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" class="notes-form">
                    <div class="form-section">
                        <div class="section-title">Session Notes</div>
                        <div class="form-group">
                            <label for="notes">Clinical Notes *</label>
                            <textarea name="notes" id="notes" required placeholder="Record your observations, what was discussed, patient's responses, etc."></textarea>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-title">Assessment & Diagnosis</div>
                        <div class="form-group">
                            <label for="diagnosis">Diagnosis/Clinical Impression</label>
                            <textarea name="diagnosis" id="diagnosis" placeholder="Current diagnosis or clinical impression..."></textarea>
                        </div>
                        <div class="form-group">
                            <label for="treatment_plan">Treatment Plan</label>
                            <textarea name="treatment_plan" id="treatment_plan" placeholder="Current treatment plan, interventions, recommendations..."></textarea>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-title">Progress Evaluation</div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="progress_status">Progress Status *</label>
                                <select name="progress_status" id="progress_status" required>
                                    <option value="">Select progress status</option>
                                    <option value="improved">Improved</option>
                                    <option value="no_change">No Change</option>
                                    <option value="declined">Declined</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="next_session_recommendations">Recommendations for Next Session</label>
                            <textarea name="next_session_recommendations" id="next_session_recommendations" placeholder="What should be focused on in the next session? Any specific areas to address?"></textarea>
                        </div>
                    </div>

                    <?php if ($sessionId): ?>
                        <input type="hidden" name="session_id" value="<?php echo $sessionId; ?>">
                    <?php else: ?>
                        <div class="form-group">
                            <label for="session_id">Session ID *</label>
                            <input type="number" name="session_id" id="session_id" required>
                        </div>
                    <?php endif; ?>

                    <?php if ($clientId): ?>
                        <input type="hidden" name="client_id" value="<?php echo $clientId; ?>">
                    <?php else: ?>
                        <div class="form-group">
                            <label for="client_id">Student ID *</label>
                            <input type="number" name="client_id" id="client_id" required>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="submit-btn">Save Notes</button>
                </form>

                <?php if (!empty($recentNotes)): ?>
                    <div class="recent-notes">
                        <h3>Recent Notes for This Student</h3>
                        <?php foreach ($recentNotes as $note): ?>
                            <div class="note-entry">
                                <div class="note-header">
                                    <div class="note-date">
                                        <?php echo date('M j, Y g:i A', strtotime($note['created_at'])); ?>
                                        (Session: <?php echo date('M j, Y', strtotime($note['session_date'])); ?>)
                                    </div>
                                    <div class="progress-status <?php echo $note['progress_status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $note['progress_status'])); ?>
                                    </div>
                                </div>
                                <div class="note-content">
                                    <div class="note-section">
                                        <strong>Notes:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($note['notes'])); ?>
                                    </div>
                                    <?php if ($note['diagnosis']): ?>
                                        <div class="note-section">
                                            <strong>Diagnosis:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($note['diagnosis'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($note['treatment_plan']): ?>
                                        <div class="note-section">
                                            <strong>Treatment Plan:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($note['treatment_plan'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($note['next_session_recommendations']): ?>
                                        <div class="note-section">
                                            <strong>Next Session Recommendations:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($note['next_session_recommendations'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
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
