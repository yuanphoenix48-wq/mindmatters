<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'connect.php';

$userId = $_SESSION['user_id'];
$sql = "SELECT profile_picture, role, first_name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

// Verify admin role
if ($admin['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

// Get system statistics
$sql = "SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN role = 'client' THEN 1 ELSE 0 END) as total_clients,
    SUM(CASE WHEN role = 'therapist' THEN 1 ELSE 0 END) as total_therapists
FROM users 
WHERE role != 'admin'";
$result = $conn->query($sql);
$stats = $result->fetch_assoc();

// Add active users count (all users are considered active for now)
$stats['active_users'] = $stats['total_users'];

// Get recent sessions
$sql = "SELECT s.*, 
    CONCAT(stud.first_name, ' ', stud.last_name) as client_name,
    CONCAT(doc.first_name, ' ', doc.last_name) as therapist_name
FROM sessions s
JOIN users stud ON s.client_id = stud.id
JOIN users doc ON s.therapist_id = doc.id
WHERE s.session_date >= CURDATE() - INTERVAL 7 DAY
ORDER BY s.session_date DESC, s.session_time DESC
LIMIT 5";
$result = $conn->query($sql);
$recentSessions = $result->fetch_all(MYSQLI_ASSOC);

// Get recent user registrations
$sql = "SELECT id, first_name, last_name, role, created_at 
FROM users 
WHERE role != 'admin' 
ORDER BY created_at DESC 
LIMIT 5";
$result = $conn->query($sql);
$recentUsers = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Home - Mind Matters</title>
    <?php 
      $global_version = filemtime('styles/global.css');
      $dashboard_version = filemtime('styles/dashboard.css');
      $admin_dashboard_version = filemtime('styles/admin_dashboard.css');
      $notifications_version = filemtime('styles/notifications.css');
      $mobile_css_version = filemtime('styles/mobile.css');
      $mobile_js_version = filemtime('js/mobile.js');
      $notifications_js_version = filemtime('js/notifications.js');
    ?>
    <link rel="stylesheet" href="styles/global.css?v=<?php echo $global_version; ?>">
    <link rel="stylesheet" href="styles/dashboard.css?v=<?php echo $dashboard_version; ?>">
    <link rel="stylesheet" href="styles/admin_dashboard.css?v=<?php echo $admin_dashboard_version; ?>">
    <link rel="stylesheet" href="styles/notifications.css?v=<?php echo $notifications_version; ?>">
    <link rel="stylesheet" href="styles/mobile.css?v=<?php echo $mobile_css_version; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="dbBody admin-role">
    <!-- Logout Confirm Modal (unified, same as Messages/Dashboard) -->
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
                <img src="<?php echo htmlspecialchars($admin['profile_picture'] ?? 'images/profile/default_images/default_profile.png'); ?>" alt="Profile Picture" class="mobile-user-avatar">
                <span class="mobile-user-name"><?php echo htmlspecialchars($admin['first_name']); ?></span>
            </div>
        </div>
    </div>

    <!-- Mobile Menu Overlay -->
    <div class="mobile-menu-overlay" id="mobileMenuOverlay" onclick="closeMobileMenu()"></div>
    
    <div class="dbContainer">
        <!-- Sidebar -->
        <div class="dbSidebar">
            <div class="sidebarProfile">
                <img src="<?php echo htmlspecialchars($admin['profile_picture'] ?? 'images/profile/default_images/default_profile.png'); ?>" alt="Profile Picture" class="defaultPicture" id="profilePic">
                <h1 class="profileName"><?php echo htmlspecialchars($admin['first_name']); ?></h1>
                <p class="userRole">Admin</p>
            </div>
            <ul class="sidebarNavList">
                <li class="sidebarNavItem active"><a href="admin_dashboard.php" class="sidebarNavLink">Admin Home</a></li>
                <li class="sidebarNavItem"><a href="admin_users.php" class="sidebarNavLink">User Management</a></li>
                <li class="sidebarNavItem"><a href="admin_sessions.php" class="sidebarNavLink">Session Management</a></li>
                <li class="sidebarNavItem"><a href="admin_reports.php" class="sidebarNavLink">Reports</a></li>
                <li class="sidebarNavItem"><a href="admin_settings.php" class="sidebarNavLink">System Settings</a></li>
                <li class="sidebarNavItem"><a href="admin_contact_messages.php" class="sidebarNavLink">Contact Messages</a></li>
                <li class="sidebarNavItem"><a href="profile_settings.php" class="sidebarNavLink">Profile Settings</a></li>
            </ul>
            <div class="sidebarFooter">
                <button type="button" class="logoutButton" onclick="openLogoutConfirm()">Logout</button>
            </div>
        </div>

        <!-- Main Content -->
        <div class="dbMainContent">
            <!-- Statistics Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-users stat-icon"></i>
                    <div class="stat-content">
                        <h3>Total Users</h3>
                        <p class="stat-number"><?php echo number_format($stats['total_users']); ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-user-graduate stat-icon"></i>
                    <div class="stat-content">
                        <h3>Clients</h3>
                        <p class="stat-number"><?php echo number_format($stats['total_clients']); ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-user-md stat-icon"></i>
                    <div class="stat-content">
                        <h3>therapists</h3>
                        <p class="stat-number"><?php echo number_format($stats['total_therapists']); ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-user-check stat-icon"></i>
                    <div class="stat-content">
                        <h3>Active Users</h3>
                        <p class="stat-number"><?php echo number_format($stats['active_users']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h2>Quick Actions</h2>
                <div class="action-buttons">
                    <button class="action-button add-user" onclick="window.location.href='admin_add_user.php'">
                        <i class="fas fa-user-plus"></i> Add New User
                    </button>
                    <button class="action-button manage-therapists" onclick="window.location.href='admin_users.php'">
                        <i class="fas fa-user-md"></i> Manage Users
                    </button>
                    <button class="action-button view-reports" onclick="window.location.href='admin_reports.php'">
                        <i class="fas fa-chart-bar"></i> View Reports
                    </button>
                    <button class="action-button system-settings" onclick="window.location.href='admin_settings.php'">
                        <i class="fas fa-cog"></i> System Settings
                    </button>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="dashboard-grid">
                <!-- Recent Sessions -->
                <div class="dashboard-card">
                    <h2>Recent Sessions</h2>
                    <div class="section-container">
                        <?php if (empty($recentSessions)): ?>
                            <p class="no-data">No recent sessions found.</p>
                        <?php else: ?>
                            <div class="recent-list">
                                <?php foreach ($recentSessions as $session): ?>
                                    <div class="recent-item">
                                        <div class="recent-item-header">
                                            <span class="session-date"><?php echo date('M j, Y', strtotime($session['session_date'])); ?></span>
                                            <span class="session-time"><?php echo date('g:i A', strtotime($session['session_time'])); ?></span>
                                        </div>
                                        <div class="recent-item-content">
                                            <p><strong>Client:</strong> <?php echo htmlspecialchars($session['client_name']); ?></p>
                                            <p><strong>Therapist:</strong> <?php echo htmlspecialchars($session['therapist_name']); ?></p>
                                            <p><strong>Status:</strong> <span class="status-badge <?php echo $session['status']; ?>"><?php echo ucfirst($session['status']); ?></span></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Users -->
                <div class="dashboard-card">
                    <h2>Recent Registrations</h2>
                    <div class="section-container">
                        <?php if (empty($recentUsers)): ?>
                            <p class="no-data">No recent user registrations.</p>
                        <?php else: ?>
                            <div class="recent-list">
                                <?php foreach ($recentUsers as $user): ?>
                                    <div class="recent-item">
                                        <div class="recent-item-header">
                                            <span class="user-role <?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span>
                                            <span class="registration-date"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></span>
                                        </div>
                                        <div class="recent-item-content">
                                            <p><strong>Name:</strong> <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                                            <div class="user-actions">
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/admin_dashboard.js"></script>
    <script src="js/notifications.js?v=<?php echo $notifications_js_version; ?>"></script>
    <script src="js/mobile.js?v=<?php echo $mobile_js_version; ?>"></script>
    <script>
        // Logout modal handlers (aligned with Messages/Dashboard)
        function openLogoutConfirm(){
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
    </script>
</body>
</html> 