<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'connect.php';

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'client';

// Get user's profile picture and basic info
$sql = "SELECT first_name, profile_picture, gender FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Set profile picture with proper fallback
$profilePicture = $user['profile_picture'] ?? ($user['gender'] === 'female' ? 'images/profile/default_images/female_gender.png' : 'images/profile/default_images/male_gender.png');
$firstName = $user['first_name'] ?? $_SESSION['first_name'] ?? 'User';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validate passwords
    if ($newPassword !== $confirmPassword) {
        $message = 'New passwords do not match.';
        $messageType = 'error';
    } else {
        // Enforce strength policy
        $isStrong = (strlen($newPassword) >= 8 && strlen($newPassword) <= 72
            && preg_match('/[A-Z]/', $newPassword)
            && preg_match('/[a-z]/', $newPassword)
            && preg_match('/[0-9]/', $newPassword)
            && preg_match('/[^A-Za-z0-9]/', $newPassword));
        if (!$isStrong) {
            $message = 'Password must be 8+ chars and include uppercase, lowercase, number, and special character.';
            $messageType = 'error';
        } else {
            // Get current password hash
            $sql = "SELECT password FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            
            // Verify current password
            if (password_verify($currentPassword, $user['password'])) {
                // Check password history - prevent reuse of last 5 passwords and current
                $histSql = "SELECT password_hash FROM password_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
                $histStmt = $conn->prepare($histSql);
                $histStmt->bind_param("i", $userId);
                $histStmt->execute();
                $histRes = $histStmt->get_result();
                $reused = false;
                while ($row = $histRes->fetch_assoc()) {
                    if (password_verify($newPassword, $row['password_hash'])) { $reused = true; break; }
                }
                $histStmt->close();

                if (!$reused && password_verify($newPassword, $user['password'])) { $reused = true; }

                if ($reused) {
                    $message = 'You cannot reuse your recent passwords. Please choose a new password.';
                    $messageType = 'error';
                } else {
                    // Update password
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updateSql = "UPDATE users SET password = ? WHERE id = ?";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bind_param("si", $hashedPassword, $userId);
                    
                    if ($updateStmt->execute()) {
                        $updateStmt->close();
                        // Insert new hash into history
                        $insSql = "INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)";
                        $insStmt = $conn->prepare($insSql);
                        $insStmt->bind_param("is", $userId, $hashedPassword);
                        $insStmt->execute();
                        $insStmt->close();
                        
                        // Trim history to last 5 entries
                        $trimSql = "DELETE FROM password_history WHERE user_id = ? AND id NOT IN (SELECT id FROM (SELECT id FROM password_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 5) t)";
                        $trimStmt = $conn->prepare($trimSql);
                        $trimStmt->bind_param("ii", $userId, $userId);
                        $trimStmt->execute();
                        $trimStmt->close();

                        $message = 'Password changed successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to update password.';
                        $messageType = 'error';
                        $updateStmt->close();
                    }
                }
            } else {
                $message = 'Current password is incorrect.';
                $messageType = 'error';
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Mind Matters</title>
    <link rel="stylesheet" href="styles/global.css">
    <link rel="stylesheet" href="styles/dashboard.css">
    <link rel="stylesheet" href="styles/profile_settings.css">
    <link rel="stylesheet" href="styles/notifications.css">
    <link rel="stylesheet" href="styles/mobile.css">
    <script src="js/notifications.js"></script>
    <script src="js/mobile.js"></script>
    <style>
        /* Enhanced Change Password Styling */
        .password-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 2rem;
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .password-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #e9ecef;
        }

        .password-header h2 {
            color: #1D5D9B;
            font-size: 2rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .password-header h2::before {
            content: '🔐';
            font-size: 1.5rem;
        }

        .password-header p {
            color: #6c757d;
            font-size: 1rem;
            margin: 0;
        }

        .password-form {
            display: grid;
            gap: 1.5rem;
        }

        .form-group {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group label {
            font-weight: 600;
            color: #1D3557;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group label::before {
            content: '•';
            color: #1D5D9B;
            font-weight: bold;
        }

        .password-input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-input-group input {
            width: 100%;
            padding: 0.875rem 3rem 0.875rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
            color: #1D3557;
        }

        .password-input-group input:focus {
            outline: none;
            border-color: #1D5D9B;
            background: white;
            box-shadow: 0 0 0 3px rgba(29, 93, 155, 0.1);
            transform: translateY(-1px);
        }

        .password-toggle {
            position: absolute;
            right: 0.75rem;
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 6px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .password-toggle:hover {
            background: #e9ecef;
            color: #1D5D9B;
        }

        .form-help {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.25rem;
            padding: 0.5rem 0.75rem;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid #1D5D9B;
        }

        .password-strength {
            margin-top: 0.5rem;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .strength-bar {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }

        .strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .strength-fill.weak { background: #dc3545; width: 25%; }
        .strength-fill.fair { background: #ffc107; width: 50%; }
        .strength-fill.good { background: #17a2b8; width: 75%; }
        .strength-fill.strong { background: #28a745; width: 100%; }

        .strength-text {
            font-size: 0.8rem;
            font-weight: 600;
            text-align: center;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e9ecef;
        }

        .btn {
            flex: 1;
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1D5D9B, #14487a);
            color: white;
            box-shadow: 0 4px 12px rgba(29, 93, 155, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(29, 93, 155, 0.4);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #6c757d;
            border: 2px solid #e9ecef;
        }

        .btn-secondary:hover {
            background: #e9ecef;
            color: #495057;
            transform: translateY(-1px);
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-success::before {
            content: '✅';
            font-size: 1.2rem;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-error::before {
            content: '❌';
            font-size: 1.2rem;
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
            
            /* New Mobile Menu Button */
            .mobile-menu-btn { display: flex !important; position: fixed !important; top: 1rem !important; left: 1rem !important; z-index: 10000 !important; background: var(--primary-color) !important; border: none !important; border-radius: 8px !important; width: 50px !important; height: 50px !important; cursor: pointer !important; box-shadow: 0 4px 12px rgba(29,93,155,0.3) !important; transition: all 0.3s ease !important; flex-direction: column !important; justify-content: center !important; align-items: center !important; padding: 0 !important; outline: none !important; }
            .mobile-menu-btn .hamburger { width: 20px; height: 2px; background: #fff; margin: 2px 0; transition: all 0.3s ease; border-radius: 1px; }
            .mobile-menu-btn.active .hamburger:nth-child(1) { transform: rotate(45deg) translate(5px,5px); }
            .mobile-menu-btn.active .hamburger:nth-child(2) { opacity: 0; }
            .mobile-menu-btn.active .hamburger:nth-child(3) { transform: rotate(-45deg) translate(7px,-6px); }
            
            /* Overlay */
            .mobile-menu-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; opacity: 0; transition: opacity 0.3s ease; }
            .mobile-menu-overlay.active { display: block; opacity: 1; }
            
            /* Sidebar slide-in */
            .dbSidebar { transform: translateX(-100%); transition: transform 0.3s cubic-bezier(0.4,0,0.2,1); width: 280px; z-index: 10001; position: fixed; top: 0; left: 0; height: 100vh; overflow-y: auto; }
            .dbSidebar.mobile-open { transform: translateX(0); }
            .mobile-menu-open .dbSidebar { transform: translateX(0); }
            .mobile-menu-open { overflow: hidden; }
            .mobile-menu-open .dbMainContent { pointer-events: none; }
            
            /* Hide old toggle */
            .mobile-nav-toggle { display: none !important; }
            
            /* Add top padding to main content */
            .dbMainContent { padding-top: 80px !important; }
            
            .password-container {
                padding: 1rem !important;
                margin: 1rem !important;
                border-radius: 12px !important;
            }
            
            .password-header h2 {
                font-size: 1.5rem !important;
            }
            
            .form-actions {
                flex-direction: column !important;
            }
            
            .btn {
                width: 100% !important;
            }
        }

        /* Logout Confirm Modal */
        #logoutConfirmModal { display: none; position: fixed; z-index: 3000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        #logoutConfirmModal.show { display: flex; }
        #logoutConfirmModal .modal-content { width: 90%; max-width: 460px; border-radius: 12px; background: #ffffff; box-shadow: 0 20px 60px rgba(0,0,0,0.15); padding: 0; border: none; overflow: hidden; }
        #logoutConfirmModal .modal-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid rgba(0,0,0,0.06); background: linear-gradient(135deg, #1D5D9B, #14487a); color: #ffffff; }
        #logoutConfirmModal .modal-header h3 { color: #ffffff !important; font-size: 1rem; line-height: 1.2; margin: 0; }
        #logoutConfirmModal .modal-body { padding: 20px; color: #333; }
        #logoutConfirmModal .modal-actions { display: flex; gap: 10px; justify-content: flex-end; padding: 0 20px 20px 20px; }
        #logoutConfirmModal .modal-actions .cancel-btn,
        #logoutConfirmModal .modal-actions .submit-btn { appearance: none; -webkit-appearance: none; border: 0; border-radius: 10px; padding: 0.65rem 1.1rem; font-weight: 700; font-size: 0.95rem; cursor: pointer; transition: transform .12s ease, box-shadow .2s ease, background .2s ease, color .2s ease, opacity .2s ease; outline: none; min-width: 96px; }
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
                <span class="mobile-user-name"><?php echo htmlspecialchars($firstName); ?></span>
            </div>
        </div>
    </div>

    <!-- Mobile Menu Overlay -->
    <div id="mobileMenuOverlay" class="mobile-menu-overlay" onclick="closeMobileMenu()"></div>

    <div class="dbContainer">
        <div class="dbSidebar">
            <div class="sidebarProfile">
                <img src="<?php echo htmlspecialchars($profilePicture); ?>" alt="Profile Picture" class="defaultPicture" id="profilePic">
                <h1 class="profileName"><?php echo htmlspecialchars($firstName); ?></h1>
                <p class="userRole"><?php echo ucfirst($userRole); ?></p>
            </div>
            <ul class="sidebarNavList">
                <li class="sidebarNavItem"><a href="dashboard.php" class="sidebarNavLink">Home</a></li>
                <?php if (in_array($userRole, ['client','student'])): ?>
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
                    <li class="sidebarNavItem"><a href="therapy_support.php" class="sidebarNavLink">Therapy Support</a></li>
                    <li class="sidebarNavItem"><a href="community_forum.php" class="sidebarNavLink">Community Forum</a></li>
                    <li class="sidebarNavItem"><a href="doctor_schedule.php" class="sidebarNavLink">Schedule</a></li>
                    <li class="sidebarNavItem"><a href="analytics_dashboard.php" class="sidebarNavLink">Analytics</a></li>
                    <li class="sidebarNavItem"><a href="student_messages.php" class="sidebarNavLink">Messages</a></li>
                <?php endif; ?>
                <li class="sidebarNavItem"><a href="profile_settings.php" class="sidebarNavLink active">Settings</a></li>
            </ul>
            <div class="sidebarFooter">
                <button type="button" class="logoutButton" onclick="openLogoutConfirm()">Logout</button>
            </div>
        </div>
        
        <div class="dbMainContent">
            <div class="password-container">
                <div class="password-header">
                    <h2>Change Password</h2>
                    <p>Update your account password for better security</p>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="change_password.php" class="password-form">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <div class="password-input-group">
                            <input type="password" id="current_password" name="current_password" 
                                   value="<?php echo isset($_POST['current_password']) && $messageType==='error' ? htmlspecialchars($_POST['current_password']) : ''; ?>" 
                                   required placeholder="Enter your current password">
                            <button type="button" class="password-toggle" id="toggleCurrent">Show</button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <div class="password-input-group">
                            <input type="password" id="new_password" name="new_password" 
                                   value="<?php echo isset($_POST['new_password']) && $messageType==='error' ? htmlspecialchars($_POST['new_password']) : ''; ?>" 
                                   required minlength="8" placeholder="Enter your new password">
                            <button type="button" class="password-toggle" id="toggleNew">Show</button>
                        </div>
                        <div class="form-help">
                            Password must be 8+ characters and include uppercase, lowercase, number, and special character.
                        </div>
                        <div class="password-strength" id="passwordStrength" style="display: none;">
                            <div class="strength-bar">
                                <div class="strength-fill" id="strengthFill"></div>
                            </div>
                            <div class="strength-text" id="strengthText">Password strength</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="password-input-group">
                            <input type="password" id="confirm_password" name="confirm_password" 
                                   value="<?php echo isset($_POST['confirm_password']) && $messageType==='error' ? htmlspecialchars($_POST['confirm_password']) : ''; ?>" 
                                   required minlength="8" placeholder="Confirm your new password">
                            <button type="button" class="password-toggle" id="toggleConfirm">Show</button>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <span>🔐</span> Change Password
                        </button>
                        <a href="profile_settings.php" class="btn btn-secondary">
                            <span>↩️</span> Back to Settings
                        </a>
                    </div>
                </form>
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
        
        // Mobile burger menu functions
        function toggleMobileMenu(){ const s=document.querySelector('.dbSidebar'); const o=document.getElementById('mobileMenuOverlay'); const b=document.getElementById('mobileMenuBtn'); if(s&&o&&b){ s.classList.contains('mobile-open')?closeMobileMenu():openMobileMenu(); } }
        function openMobileMenu(){ const s=document.querySelector('.dbSidebar'); const o=document.getElementById('mobileMenuOverlay'); const b=document.getElementById('mobileMenuBtn'); const body=document.body; if(s&&o&&b){ s.classList.add('mobile-open'); o.classList.add('active'); b.classList.add('active'); body.classList.add('mobile-menu-open'); } }
        function closeMobileMenu(){ const s=document.querySelector('.dbSidebar'); const o=document.getElementById('mobileMenuOverlay'); const b=document.getElementById('mobileMenuBtn'); const body=document.body; if(s&&o&&b){ s.classList.remove('mobile-open'); o.classList.remove('active'); b.classList.remove('active'); body.classList.remove('mobile-menu-open'); } }
        document.addEventListener('DOMContentLoaded', function(){ document.querySelectorAll('.sidebarNavLink').forEach(l=>l.addEventListener('click', ()=>closeMobileMenu())); document.addEventListener('keydown', e=>{ if(e.key==='Escape') closeMobileMenu(); }); });
        
        // Password confirmation validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.password-form');
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            const strengthIndicator = document.getElementById('passwordStrength');
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            // Password strength checker
            function checkPasswordStrength(password) {
                let score = 0;
                let feedback = [];
                
                if (password.length >= 8) score += 1;
                else feedback.push('At least 8 characters');
                
                if (/[A-Z]/.test(password)) score += 1;
                else feedback.push('Uppercase letter');
                
                if (/[a-z]/.test(password)) score += 1;
                else feedback.push('Lowercase letter');
                
                if (/[0-9]/.test(password)) score += 1;
                else feedback.push('Number');
                
                if (/[^A-Za-z0-9]/.test(password)) score += 1;
                else feedback.push('Special character');
                
                return { score, feedback };
            }
            
            // Update password strength indicator
            function updateStrengthIndicator(password) {
                if (password.length === 0) {
                    strengthIndicator.style.display = 'none';
                    return;
                }
                
                strengthIndicator.style.display = 'block';
                const { score, feedback } = checkPasswordStrength(password);
                
                strengthFill.className = 'strength-fill';
                if (score <= 1) {
                    strengthFill.classList.add('weak');
                    strengthText.textContent = 'Weak - ' + feedback.join(', ');
                    strengthText.style.color = '#dc3545';
                } else if (score <= 2) {
                    strengthFill.classList.add('fair');
                    strengthText.textContent = 'Fair - ' + feedback.join(', ');
                    strengthText.style.color = '#ffc107';
                } else if (score <= 3) {
                    strengthFill.classList.add('good');
                    strengthText.textContent = 'Good - ' + feedback.join(', ');
                    strengthText.style.color = '#17a2b8';
                } else {
                    strengthFill.classList.add('strong');
                    strengthText.textContent = 'Strong password!';
                    strengthText.style.color = '#28a745';
                }
            }
            
            // Real-time password strength checking
            newPassword.addEventListener('input', function() {
                updateStrengthIndicator(this.value);
            });
            
            // Form validation
            form.addEventListener('submit', function(e) {
                if (newPassword.value !== confirmPassword.value) {
                    e.preventDefault();
                    showToast('New passwords do not match!', 'error');
                    confirmPassword.focus();
                    return;
                }
                
                const { score } = checkPasswordStrength(newPassword.value);
                if (score < 3) {
                    e.preventDefault();
                    showToast('Password is too weak. Please make it stronger.', 'error');
                    newPassword.focus();
                    return;
                }
            });
            
            // Real-time password matching
            confirmPassword.addEventListener('input', function() {
                if (this.value && this.value !== newPassword.value) {
                    this.setCustomValidity('Passwords do not match');
                    this.style.borderColor = '#dc3545';
                } else {
                    this.setCustomValidity('');
                    this.style.borderColor = '#e9ecef';
                }
            });
            
            // Show/hide password buttons
            const hook = (inputId, btnId) => {
                const input = document.getElementById(inputId);
                const btn = document.getElementById(btnId);
                if (!input || !btn) return;
                
                btn.addEventListener('click', () => {
                    const isPassword = input.type === 'password';
                    input.type = isPassword ? 'text' : 'password';
                    btn.textContent = isPassword ? 'Hide' : 'Show';
                    btn.style.color = isPassword ? '#1D5D9B' : '#6c757d';
                });
            };
            
            hook('current_password', 'toggleCurrent');
            hook('new_password', 'toggleNew');
            hook('confirm_password', 'toggleConfirm');
            
            // Add focus effects
            const inputs = document.querySelectorAll('.password-input-group input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });
        });
    </script>
</body>
</html>

