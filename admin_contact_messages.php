<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit(); }
require_once 'connect.php';

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT profile_picture, role, first_name FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
if (!$admin || $admin['role'] !== 'admin') { header('Location: dashboard.php'); exit(); }

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status' && isset($_POST['message_id'], $_POST['status'])) {
        $message_id = (int)$_POST['message_id'];
        $status = $_POST['status'];
        
        $sql = "UPDATE contact_messages SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $status, $message_id);
        $stmt->execute();
        $stmt->close();
        
        header('Location: admin_contact_messages.php');
        exit;
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT * FROM contact_messages WHERE 1=1";
$params = [];
$types = "";

if ($status_filter !== 'all') {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR email LIKE ? OR message LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$messages = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get status counts
$status_counts = [];
$statuses = ['new', 'read', 'closed'];
foreach ($statuses as $status) {
    $sql = "SELECT COUNT(*) as count FROM contact_messages WHERE status = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $status_counts[$status] = $result->fetch_assoc()['count'];
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Messages - Mind Matters</title>
    <link rel="stylesheet" href="styles/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="styles/dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="styles/admin_dashboard.css?v=<?php echo time(); ?>">
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
        
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .filter-row {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #2C3E50;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 0.25rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-new { background: #e3f2fd; color: #1976d2; }
        .status-read { background: #fff3e0; color: #f57c00; }
        .status-closed { background: #ffebee; color: #c62828; }
        
        /* Increase font size for stat card numbers */
        .stat-content h3 {
            font-size: 3rem !important;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
            line-height: 1;
        }
        
        .message-preview {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 0.5rem;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .users-table {
                font-size: 0.875rem;
            }
            
            .users-table th,
            .users-table td {
                padding: 0.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
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
                <img src="<?php echo htmlspecialchars($admin['profile_picture'] ?? 'images/profile/default_images/default_profile.png'); ?>" alt="Profile" class="mobile-user-avatar">
                <span class="mobile-user-name"><?php echo htmlspecialchars($admin['first_name']); ?></span>
            </div>
        </div>
    </div>
    <div class="dbContainer">
        <div class="dbSidebar">
            <div class="sidebarProfile">
                <img src="<?php echo htmlspecialchars($admin['profile_picture'] ?? 'images/profile/default_images/default_profile.png'); ?>" alt="Profile Picture" class="defaultPicture" id="profilePic">
                <h1 class="profileName"><?php echo htmlspecialchars($admin['first_name']); ?></h1>
                <p class="userRole">Admin</p>
            </div>
            <ul class="sidebarNavList">
                <li class="sidebarNavItem"><a href="admin_dashboard.php" class="sidebarNavLink">Admin Home</a></li>
                <li class="sidebarNavItem"><a href="admin_users.php" class="sidebarNavLink">User Management</a></li>
                <li class="sidebarNavItem"><a href="admin_sessions.php" class="sidebarNavLink">Session Management</a></li>
                <li class="sidebarNavItem"><a href="admin_reports.php" class="sidebarNavLink">Reports</a></li>
                <li class="sidebarNavItem"><a href="admin_settings.php" class="sidebarNavLink">System Settings</a></li>
                <li class="sidebarNavItem active"><a href="admin_contact_messages.php" class="sidebarNavLink">Contact Messages</a></li>
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
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo array_sum($status_counts); ?></h3>
                        <p>Total Messages</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $status_counts['new']; ?></h3>
                        <p>New Messages</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $status_counts['read']; ?></h3>
                        <p>Read Messages</p>
                    </div>
                </div>
            </div>

            <div class="dashboard-card">
                <h2><i class="fas fa-comments"></i> Contact Messages</h2>
                
                <div class="filters">
                    <form method="GET" class="filter-row">
                        <div class="filter-group">
                            <label for="status">Status:</label>
                            <select name="status" id="status">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                                <option value="new" <?php echo $status_filter === 'new' ? 'selected' : ''; ?>>New</option>
                                <option value="read" <?php echo $status_filter === 'read' ? 'selected' : ''; ?>>Read</option>
                                <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="search">Search:</label>
                            <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search messages...">
                        </div>
                        <div class="filter-group">
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </div>
                    </form>
                </div>
                
                <div class="users-table-container">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-user"></i> Name</th>
                                <th><i class="fas fa-envelope"></i> Email</th>
                                <th><i class="fas fa-comment"></i> Message</th>
                                <th><i class="fas fa-info-circle"></i> Status</th>
                                <th><i class="fas fa-calendar"></i> Date</th>
                                <th><i class="fas fa-cogs"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($messages)): ?>
                            <tr><td colspan="6" style="text-align: center; padding: 3rem 1rem; color: var(--gray-500); font-style: italic;">
                                <i class="fas fa-envelope-open" style="font-size: 2rem; margin-bottom: 1rem; display: block; color: var(--gray-400);"></i>
                                No messages found.
                            </td></tr>
                            <?php else: ?>
                            <?php foreach ($messages as $message): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($message['name']); ?></td>
                                <td><?php echo htmlspecialchars($message['email']); ?></td>
                                <td>
                                    <div class="message-preview" title="<?php echo htmlspecialchars($message['message']); ?>">
                                        <?php echo htmlspecialchars(substr($message['message'], 0, 100)) . (strlen($message['message']) > 100 ? '...' : ''); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $message['status']; ?>">
                                        <?php echo ucfirst($message['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-primary" onclick="viewMessage(<?php echo $message['id']; ?>)">View</button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                            <select name="status" onchange="this.form.submit()" class="btn-sm">
                                                <option value="new" <?php echo $message['status'] === 'new' ? 'selected' : ''; ?>>New</option>
                                                <option value="read" <?php echo $message['status'] === 'read' ? 'selected' : ''; ?>>Read</option>
                                                <option value="closed" <?php echo $message['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                            </select>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Message Modal -->
    <div id="messageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Message Details</h3>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <div id="modalBody">
                <!-- Message content will be loaded here -->
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

        function viewMessage(messageId) {
            // Show loading state
            document.getElementById('modalBody').innerHTML = `
                <p><strong>Loading message details...</strong></p>
            `;
            document.getElementById('messageModal').classList.add('show');
            
            // Fetch message details via AJAX
            fetch('get_message_details.php?id=' + messageId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const message = data.message;
                        document.getElementById('modalBody').innerHTML = `
                            <div style="margin-bottom: 1rem;">
                                <h4 style="margin: 0 0 0.5rem 0; color: #1D5D9B;">From: ${message.name}</h4>
                                <p style="margin: 0 0 0.5rem 0; color: #666;">${message.email}</p>
                                <p style="margin: 0; color: #666; font-size: 0.9rem;">${new Date(message.created_at).toLocaleString()}</p>
                            </div>
                            <div style="border-top: 1px solid #eee; padding-top: 1rem;">
                                <h5 style="margin: 0 0 0.5rem 0; color: #333;">Message:</h5>
                                <div style="background: #f8f9fa; padding: 1rem; border-radius: 0.5rem; white-space: pre-wrap; line-height: 1.5;">${message.message}</div>
                            </div>
                        `;
                    } else {
                        document.getElementById('modalBody').innerHTML = `
                            <p style="color: #dc3545;"><strong>Error loading message details.</strong></p>
                            <p>${data.error || 'Unknown error occurred.'}</p>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('modalBody').innerHTML = `
                        <p style="color: #dc3545;"><strong>Error loading message details.</strong></p>
                        <p>Failed to fetch message details. Please try again.</p>
                    `;
                });
        }
        
        function closeModal() {
            document.getElementById('messageModal').classList.remove('show');
        }
        
        // Close modal when clicking outside
        document.getElementById('messageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>

