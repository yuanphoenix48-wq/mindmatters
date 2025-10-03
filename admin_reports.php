<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit(); }
require_once 'connect.php';

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT role, profile_picture, first_name FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$me = $result->fetch_assoc();
if (!$me || $me['role'] !== 'admin') { header('Location: dashboard.php'); exit(); }
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports - Mind Matters</title>
  <link rel="stylesheet" href="styles/global.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="styles/dashboard.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="styles/admin_dashboard.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="styles/admin_reports.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="styles/mobile.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
      /* Logout Confirm Modal (match Messages) */
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
      @media (max-width: 768px) { body.modal-open .dbSidebar { transform: translateX(-100%) !important; } body.modal-open #mobileMenuOverlay { display: none !important; opacity: 0 !important; } }
  </style>
</head>
<body class="dbBody">
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="mobile-header-content">
            <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
                <div class="hamburger"></div>
                <div class="hamburger"></div>
                <div class="hamburger"></div>
            </button>
            <div class="mobile-logo">Mind Matters</div>
            <div class="mobile-user-info">
                <img src="<?php echo htmlspecialchars($me['profile_picture'] ?? 'images/profile/default_images/default_profile.png'); ?>" alt="Profile" class="mobile-user-avatar">
                <span class="mobile-user-name"><?php echo htmlspecialchars($me['first_name'] ?? 'Admin'); ?></span>
            </div>
        </div>
    </div>
  <div class="dbContainer">
    <div class="dbSidebar">
      <div class="sidebarProfile">
        <img src="<?php echo htmlspecialchars($me['profile_picture'] ?? 'images/profile/default_images/default_profile.png'); ?>" alt="Profile Picture" class="defaultPicture" id="profilePic">
        <h1 class="profileName"><?php echo htmlspecialchars($me['first_name'] ?? 'Admin'); ?></h1>
        <p class="userRole">Admin</p>
      </div>
      <ul class="sidebarNavList">
        <li class="sidebarNavItem"><a href="admin_dashboard.php" class="sidebarNavLink">Admin Home</a></li>
        <li class="sidebarNavItem"><a href="admin_users.php" class="sidebarNavLink">User Management</a></li>
        <li class="sidebarNavItem"><a href="admin_sessions.php" class="sidebarNavLink">Session Management</a></li>
        <li class="sidebarNavItem active"><a href="admin_reports.php" class="sidebarNavLink">Reports</a></li>
        <li class="sidebarNavItem"><a href="admin_settings.php" class="sidebarNavLink">System Settings</a></li>
        <li class="sidebarNavItem"><a href="admin_contact_messages.php" class="sidebarNavLink">Contact Messages</a></li>
        <li class="sidebarNavItem"><a href="profile_settings.php" class="sidebarNavLink">Profile Settings</a></li>
  
    </ul>
      <div class="sidebarFooter">
        <button type="button" class="logoutButton" onclick="openLogoutConfirm()">Logout</button>
      </div>
    </div>
    <div class="dbMainContent">
      <!-- Statistics Overview -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon">
            <i class="fas fa-file-csv"></i>
          </div>
          <div class="stat-content">
            <h3>2</h3>
            <p>Report Types</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">
            <i class="fas fa-download"></i>
          </div>
          <div class="stat-content">
            <h3>CSV</h3>
            <p>Export Format</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">
            <i class="fas fa-chart-line"></i>
          </div>
          <div class="stat-content">
            <h3>Analytics</h3>
            <p>Data Insights</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">
            <i class="fas fa-database"></i>
          </div>
          <div class="stat-content">
            <h3>Real-time</h3>
            <p>Live Data</p>
          </div>
        </div>
      </div>

      <div class="dashboard-card">
        <h2><i class="fas fa-chart-bar"></i> Reports & Analytics</h2>
        <p class="reports-description">Download comprehensive CSV reports for data analysis and insights.</p>
        <div class="reports-grid">
          <div class="report-card">
            <div class="report-icon">
              <i class="fas fa-users"></i>
            </div>
            <div class="report-content">
              <h3>Users Report</h3>
              <p>Complete user data including profiles, roles, and registration information.</p>
              <a class="report-button" href="report_users_csv.php">
                <i class="fas fa-download"></i>
                Download Users CSV
              </a>
            </div>
          </div>
          <div class="report-card">
            <div class="report-icon">
              <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="report-content">
              <h3>Sessions Report</h3>
              <p>Session data including dates, times, participants, and status information.</p>
              <a class="report-button" href="report_sessions_csv.php">
                <i class="fas fa-download"></i>
                Download Sessions CSV
              </a>
            </div>
          </div>
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

  <script src="js/admin_dashboard.js"></script>
  <script src="js/global.js"></script>
  <script>
      // Mobile menu toggle
      function toggleMobileMenu() {
          document.body.classList.toggle('mobile-menu-open');
      }

      // Close mobile menu when clicking outside
      document.addEventListener('click', function(event) {
          const sidebar = document.querySelector('.dbSidebar');
          const menuBtn = document.querySelector('.mobile-menu-btn');
          
          if (!sidebar.contains(event.target) && !menuBtn.contains(event.target)) {
              document.body.classList.remove('mobile-menu-open');
          }
      });

      // Logout confirmation functions
      function openLogoutConfirm(){
          const modal = document.getElementById('logoutConfirmModal');
          const okBtn = document.getElementById('logoutConfirmOk');
          if (!modal || !okBtn) return;
          modal.classList.add('show');
          document.body.style.overflow = 'hidden';
          document.body.classList.add('modal-open');
          try { if (typeof closeMobileMenu === 'function') closeMobileMenu(); } catch(e){}
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
          function onBackdrop(e){ if(e.target===modal){ onCancel(); } }
          function onEsc(e){ if(e.key==='Escape'){ onCancel(); } }
          okBtn.addEventListener('click', onOk);
          modal.addEventListener('click', onBackdrop);
          document.addEventListener('keydown', onEsc);
          window.closeLogoutConfirm = onCancel;
      }

      function closeLogoutConfirm() {
          const modal = document.getElementById('logoutConfirmModal');
          if (modal) {
              modal.classList.remove('show');
              document.body.style.overflow = '';
              document.body.classList.remove('modal-open');
          }
      }
  </script>
</body>
</html>

