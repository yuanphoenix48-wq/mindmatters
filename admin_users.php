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

// Set active tab from query parameter, default to 'all'
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'all';

// Build WHERE clause based on tab
$where = "role != 'admin'";
if ($activeTab === 'client') {
    $where .= " AND role = 'client'";
} elseif ($activeTab === 'therapist') {
    $where .= " AND role = 'therapist'";
}

$sql = "SELECT id, first_name, last_name, email, role, user_id, gender, profile_picture, created_at, section 
        FROM users 
        WHERE $where
        ORDER BY created_at DESC";
$result = $conn->query($sql);
$users = $result->fetch_all(MYSQLI_ASSOC);

// Get user statistics
$sql = "SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN role = 'client' THEN 1 ELSE 0 END) as total_clients,
            SUM(CASE WHEN role = 'therapist' THEN 1 ELSE 0 END) as total_therapists
        FROM users 
        WHERE role != 'admin'";
$result = $conn->query($sql);
$stats = $result->fetch_assoc();

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Mind Matters</title>
    <?php 
      $global_version = filemtime('styles/global.css');
      $dashboard_version = filemtime('styles/dashboard.css');
      $admin_dashboard_version = filemtime('styles/admin_dashboard.css');
      $admin_users_version = filemtime('styles/admin_users.css');
      $notifications_version = filemtime('styles/notifications.css');
      $mobile_css_version = filemtime('styles/mobile.css');
      $mobile_js_version = filemtime('js/mobile.js');
      $notifications_js_version = filemtime('js/notifications.js');
    ?>
    <link rel="stylesheet" href="styles/global.css?v=<?php echo $global_version; ?>">
    <link rel="stylesheet" href="styles/dashboard.css?v=<?php echo $dashboard_version; ?>">
    <link rel="stylesheet" href="styles/admin_dashboard.css?v=<?php echo $admin_dashboard_version; ?>">
    <link rel="stylesheet" href="styles/admin_users.css?v=<?php echo $admin_users_version; ?>">
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
                <li class="sidebarNavItem"><a href="admin_dashboard.php" class="sidebarNavLink">Admin Home</a></li>
                <li class="sidebarNavItem active"><a href="admin_users.php" class="sidebarNavLink">User Management</a></li>
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
                        <h3>Therapists</h3>
                        <p class="stat-number"><?php echo number_format($stats['total_therapists']); ?></p>
                    </div>
                </div>
            </div>

            <!-- User Management Content -->
            <div class="dashboard-card">
                <div class="content-header">
                    <h2>User Management</h2>
                    <button class="action-button add-user" id="toggleAddUserFormBtn">
                        <i class="fas fa-user-plus"></i> Add New User
                    </button>
                </div>
                <?php if (isset($_GET['error'])): ?>
                <div class="alert">
                    <?php echo htmlspecialchars(str_replace(',', '\n', $_GET['error'])); ?>
                </div>
                <?php elseif (isset($_GET['success'])): ?>
                <div class="alert">
                    User added successfully.
                </div>
                <?php endif; ?>

                <!-- Tabs -->
                <div class="user-tabs">
                    <a class="tab-button <?php if ($activeTab === 'all') echo 'active'; ?>" href="admin_users.php?tab=all">All Users</a>
                    <a class="tab-button <?php if ($activeTab === 'client') echo 'active'; ?>" href="admin_users.php?tab=client">Clients</a>
                    <a class="tab-button <?php if ($activeTab === 'therapist') echo 'active'; ?>" href="admin_users.php?tab=therapist">Therapists</a>
                </div>
                
                <!-- Add New User Form -->
                <div id="addUserFormContainer">
                    <h3>Add New User</h3>
                    <form action="admin_add_user.php" method="POST" id="addUserForm">
                        <!-- Role Selection -->
                        <div>
                            <label for="role">Role:</label>
                            <select id="role" name="role" required onchange="toggleRoleFields()">
                                <option value="client" <?php echo (($_SESSION['add_user_old']['role'] ?? 'client')==='client') ? 'selected' : ''; ?>>Client</option>
                                <option value="therapist" <?php echo (($_SESSION['add_user_old']['role'] ?? '')==='therapist') ? 'selected' : ''; ?>>Therapist</option>
                            </select>
                        </div>

                        <!-- ID Fields (shown based on role) -->
                        <div id="clientFields">
                            <div>
                                <label for="client_id">Client ID:</label>
                                <input type="text" id="client_id" name="client_id" value="<?php echo htmlspecialchars($_SESSION['add_user_old']['client_id'] ?? ''); ?>">
                            </div>
                            <div>
                                <label for="section">Section:</label>
                                <input type="text" id="section" name="section" value="<?php echo htmlspecialchars($_SESSION['add_user_old']['section'] ?? ''); ?>">
                            </div>
                        </div>

                        <div id="therapistFields">
                            <div>
                                <label for="license_id">License Number (PRC or equivalent):</label>
                                <input type="text" id="license_id" name="license_id" value="<?php echo htmlspecialchars($_SESSION['add_user_old']['license_id'] ?? ''); ?>">
                            </div>
                            <div>
                                <label for="contact_number">Contact Number:</label>
                                <input type="text" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($_SESSION['add_user_old']['contact_number'] ?? ''); ?>" placeholder="e.g., +63 912 345 6789">
                            </div>
                            <div>
                                <label for="specialization">Field of Specialization:</label>
                                <input type="text" id="specialization" name="specialization" value="<?php echo htmlspecialchars($_SESSION['add_user_old']['specialization'] ?? ''); ?>" placeholder="e.g., CBT, Child Psychology">
                            </div>
                            <div>
                                <label for="years_experience">Years of Experience:</label>
                                <input type="number" min="0" id="years_experience" name="years_experience" value="<?php echo htmlspecialchars($_SESSION['add_user_old']['years_experience'] ?? ''); ?>">
                            </div>
                            <div>
                                <label for="languages_spoken">Languages Spoken:</label>
                                <input type="text" id="languages_spoken" name="languages_spoken" value="<?php echo htmlspecialchars($_SESSION['add_user_old']['languages_spoken'] ?? ''); ?>" placeholder="e.g., English, Filipino">
                            </div>
                        </div>

                        <!-- Common Fields -->
                        <div class="common-fields">
                            <div>
                                <label for="first_name">First Name:</label>
                                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($_SESSION['add_user_old']['first_name'] ?? ''); ?>" required>
                            </div>
                            <div>
                                <label for="last_name">Last Name:</label>
                                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($_SESSION['add_user_old']['last_name'] ?? ''); ?>" required>
                            </div>
                            <div>
                                <label for="gender">Gender:</label>
                                <select id="gender" name="gender" required>
                                    <option value="male" <?php echo (($_SESSION['add_user_old']['gender'] ?? '')==='male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo (($_SESSION['add_user_old']['gender'] ?? '')==='female') ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                            <div>
                                <label for="email">Email:</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_SESSION['add_user_old']['email'] ?? ''); ?>" required>
                            </div>
                            <div>
                                <label for="password">Password:</label>
                                <div class="password-container" style="position: relative; display: block; width: 100%;">
                                    <input type="password" id="password" name="password" value="<?php echo htmlspecialchars($_SESSION['add_user_old']['password'] ?? ''); ?>" required style="padding-right: 3.5rem; width: 100%;">
                                    <button type="button" id="toggleAdminPassword" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: rgba(255, 255, 255, 0.95); border: 1px solid #d1d5db; color: #6b7280; cursor: pointer; padding: 0.5rem 0.75rem; font-size: 0.875rem; border-radius: 8px; z-index: 1000; font-weight: 500; margin: 0; width: auto; height: auto; line-height: 1; white-space: nowrap; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);">Show</button>
                                </div>
                                <small>Must be 8+ with uppercase, lowercase, number, and special character.</small>
                            </div>
                            <div>
                                <label for="confirm_password">Confirm Password:</label>
                                <div class="password-container" style="position: relative; display: block; width: 100%;">
                                    <input type="password" id="confirm_password" name="confirm_password" value="<?php echo htmlspecialchars($_SESSION['add_user_old']['confirm_password'] ?? ''); ?>" required style="padding-right: 3.5rem; width: 100%;">
                                    <button type="button" id="toggleAdminPassword2" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: rgba(255, 255, 255, 0.95); border: 1px solid #d1d5db; color: #6b7280; cursor: pointer; padding: 0.5rem 0.75rem; font-size: 0.875rem; border-radius: 8px; z-index: 1000; font-weight: 500; margin: 0; width: auto; height: auto; line-height: 1; white-space: nowrap; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);">Show</button>
                                </div>
                            </div>
                        </div>

                        <div class="form-buttons">
                            <button type="submit">Add User</button>
                            <button type="button" id="cancelAddUserBtn">Cancel</button>
                        </div>
                    </form>
                </div>

                <div class="users-table-container">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Email</th>
                                <th>User ID</th>
                                <th>Gender</th>
                                <?php if ($activeTab === 'client' || $activeTab === 'all'): ?>
                                <th>Section</th>
                                <?php endif; ?>
                                <th>Joined Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <?php if ($activeTab === 'therapist'): ?>
                            <tr class="user-row" data-role="therapist">
                                <td>
                                    <div class="user-info">
                                        <img src="<?php echo htmlspecialchars($user['profile_picture'] ?? 'images/profile/default_images/default_profile.png'); ?>" 
                                             alt="Profile" class="user-avatar">
                                        <span><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                                    </div>
                                </td>
                                <td><span class="role-badge therapist">Therapist</span></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                                <td><?php echo htmlspecialchars($user['gender']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn view" onclick="viewUserDetails(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="action-btn edit" onclick="editUser(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn delete" onclick="confirmDelete(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <tr class="user-row" data-role="<?php echo $user['role']; ?>">
                                <td>
                                    <div class="user-info">
                                        <img src="<?php echo htmlspecialchars($user['profile_picture'] ?? 'images/profile/default_images/default_profile.png'); ?>" 
                                             alt="Profile" class="user-avatar">
                                        <span><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                                    </div>
                                </td>
                                <td><span class="role-badge <?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                                <td><?php echo htmlspecialchars($user['gender']); ?></td>
                                <td><?php echo htmlspecialchars($user['section'] ?? '-'); ?></td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn view" onclick="viewUserDetails(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="action-btn edit" onclick="editUser(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn delete" onclick="confirmDelete(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- User Details Modal -->
    <div id="userDetailsModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>User Details</h2>
            <div id="userDetailsContent">
                <div class="user-details-grid">
                    <div class="detail-item">
                        <label>Name:</label>
                        <span id="modalUserName"></span>
                    </div>
                    <div class="detail-item">
                        <label>Role:</label>
                        <span id="modalUserRole"></span>
                    </div>
                    <div class="detail-item">
                        <label>Email:</label>
                        <span id="modalUserEmail"></span>
                    </div>
                    <div class="detail-item">
                        <label>User ID:</label>
                        <span id="modalUserId"></span>
                    </div>
                    <div class="detail-item">
                        <label>Gender:</label>
                        <span id="modalUserGender"></span>
                    </div>
                    <div class="detail-item client-only">
                        <label>Section:</label>
                        <span id="modalUserSection"></span>
                    </div>
                    <div class="detail-item">
                        <label>Joined Date:</label>
                        <span id="modalUserJoined"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <span class="close edit-close">&times;</span>
            <h2>Edit User</h2>
            <form id="editUserForm">
                <input type="hidden" id="editUserId" name="id">
                <div class="user-details-grid">
                    <div class="detail-item">
                        <label for="editFirstName">First Name:</label>
                        <input type="text" id="editFirstName" name="first_name" required>
                    </div>
                    <div class="detail-item">
                        <label for="editLastName">Last Name:</label>
                        <input type="text" id="editLastName" name="last_name" required>
                    </div>
                    <div class="detail-item">
                        <label for="editRole">Role:</label>
                        <input type="text" id="editRole" name="role" readonly>
                    </div>
                    <div class="detail-item">
                        <label for="editEmail">Email:</label>
                        <input type="email" id="editEmail" name="email" required>
                    </div>
                    <div class="detail-item">
                        <label for="editUserIdField">User ID:</label>
                        <input type="text" id="editUserIdField" name="user_id" required>
                    </div>
                    <div class="detail-item">
                        <label for="editGender">Gender:</label>
                        <select id="editGender" name="gender" required>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </div>
                    <div class="detail-item edit-section-field">
                        <label for="editSection">Section:</label>
                        <input type="text" id="editSection" name="section">
                    </div>
                </div>
                <div>
                    <button type="button" class="action-button" id="saveEditUserBtn">Save</button>
                    <button type="button" class="action-button edit-cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .user-tabs {
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }

        .tab-button {
            padding: 8px 16px;
            margin-right: 10px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 14px;
            color: #666;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .tab-button:hover {
            background-color: #f0f0f0;
        }

        .tab-button.active {
            background-color: #4CAF50;
            color: white;
        }

        .user-row {
            display: table-row;
        }

        .user-row.hidden {
            display: none;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 8px;
            position: relative;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: black;
        }

        .user-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .detail-item label {
            font-weight: bold;
            color: #666;
        }

        .detail-item span {
            color: #333;
        }

        .modal input, .modal select {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1em;
        }
    </style>

    <script src="js/admin_dashboard.js"></script>
    <script src="js/notifications.js?v=<?php echo $notifications_js_version; ?>"></script>
    <script src="js/mobile.js?v=<?php echo $mobile_js_version; ?>"></script>
    <script>
        function confirmDelete(userId) {
            showConfirm('Are you sure you want to delete this user? This action cannot be undone.').then((ok)=>{ if(!ok) return;
                fetch('delete_user.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + encodeURIComponent(userId)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('User deleted successfully!', 'success');
                        location.reload();
                    } else {
                        showToast(data.error || 'Failed to delete user.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error deleting user.', 'error');
                });
            });
        }
    </script>
    <script>
        const toggleAddUserFormBtn = document.getElementById('toggleAddUserFormBtn');
        const addUserFormContainer = document.getElementById('addUserFormContainer');
        const cancelAddUserBtn = document.getElementById('cancelAddUserBtn');

        toggleAddUserFormBtn.addEventListener('click', () => {
            addUserFormContainer.style.display = 'block';
            toggleAddUserFormBtn.style.display = 'none'; // Hide the add button when form is visible
        });

        cancelAddUserBtn.addEventListener('click', () => {
            addUserFormContainer.style.display = 'none';
            toggleAddUserFormBtn.style.display = 'inline-block'; // Show the add button when form is hidden
            <?php unset($_SESSION['add_user_old']); ?>
        });
    </script>
    <script>
        function toggleRoleFields() {
            const role = document.getElementById('role').value;
            const clientFields = document.getElementById('clientFields');
            const therapistFields = document.getElementById('therapistFields');
            const clientIdInput = document.getElementById('client_id');
            const licenseIdInput = document.getElementById('license_id');
            const contactNumberInput = document.getElementById('contact_number');
            const specializationInput = document.getElementById('specialization');
            const yearsExperienceInput = document.getElementById('years_experience');
            const languagesSpokenInput = document.getElementById('languages_spoken');
            const sectionInput = document.getElementById('section');

            // Hide all role-specific fields first
            clientFields.style.display = 'none';
            therapistFields.style.display = 'none';

            // Reset required attributes
            clientIdInput.required = false;
            licenseIdInput.required = false;
            if (contactNumberInput) contactNumberInput.required = false;
            if (specializationInput) specializationInput.required = false;
            if (yearsExperienceInput) yearsExperienceInput.required = false;
            if (languagesSpokenInput) languagesSpokenInput.required = false;
            sectionInput.required = false;

            // Show and set required fields based on role
            if (role === 'client') {
                clientFields.style.display = 'block';
                clientFields.classList.add('show');
                clientIdInput.required = true;
                sectionInput.required = true;
            } else if (role === 'therapist') {
                therapistFields.style.display = 'block';
                therapistFields.classList.add('show');
                licenseIdInput.required = true;
                if (contactNumberInput) contactNumberInput.required = true;
                if (specializationInput) specializationInput.required = true;
                if (yearsExperienceInput) yearsExperienceInput.required = true;
                if (languagesSpokenInput) languagesSpokenInput.required = true;
            }
        }

        // Add form validation
        document.getElementById('addUserForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const role = document.getElementById('role').value;

            if (password !== confirmPassword) { e.preventDefault(); showToast('Passwords do not match!', 'error'); return; }
            const strong = password.length >= 8 && password.length <= 72 && /[A-Z]/.test(password) && /[a-z]/.test(password) && /[0-9]/.test(password) && /[^A-Za-z0-9]/.test(password);
            if (!strong) { e.preventDefault(); showToast('Password must be 8+ chars incl uppercase, lowercase, number, special.', 'warning'); return; }

            if (role === 'client') {
                const clientId = document.getElementById('client_id').value;
                const section = document.getElementById('section').value;
                if (!clientId || !section) { e.preventDefault(); showToast('Please fill in all required client fields!', 'warning'); return; }
            } else if (role === 'therapist') {
                const licenseId = document.getElementById('license_id').value;
                const contact = document.getElementById('contact_number').value;
                const spec = document.getElementById('specialization').value;
                const years = document.getElementById('years_experience').value;
                const langs = document.getElementById('languages_spoken').value;
                if (!licenseId || !contact || !spec || !years || !langs) { e.preventDefault(); showToast('Please fill in all required therapist fields!', 'warning'); return; }
            }
        });

        // Add this to show client fields by default when page loads
        document.addEventListener('DOMContentLoaded', function() {
            toggleRoleFields(); // This will show client fields by default
        });
    </script>
    <script>
        // Toggle show/hide in admin add user form
        (function(){
            function hook(inputId, btnId){
                const i = document.getElementById(inputId);
                const b = document.getElementById(btnId);
                if(!i || !b) return;
                
                // Force button positioning as fallback
                b.style.position = 'absolute';
                b.style.right = '0.75rem';
                b.style.top = '50%';
                b.style.transform = 'translateY(-50%)';
                b.style.zIndex = '1000';
                b.style.display = 'block';
                
                b.addEventListener('click', function(){
                    const isPass = i.type === 'password';
                    i.type = isPass ? 'text' : 'password';
                    b.textContent = isPass ? 'Hide' : 'Show';
                });
            }
            hook('password','toggleAdminPassword');
            hook('confirm_password','toggleAdminPassword2');
        })();
    </script>
    <script>
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-button');
            const userRows = document.querySelectorAll('.user-row');

            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Update active tab
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    const selectedRole = this.dataset.role;

                    // Show/hide users based on role
                    userRows.forEach(row => {
                        if (selectedRole === 'all' || row.dataset.role === selectedRole) {
                            row.classList.remove('hidden');
                        } else {
                            row.classList.add('hidden');
                        }
                    });
                });
            });
        });
    </script>
    <script>
        // Modal functionality
        const modal = document.getElementById('userDetailsModal');

        function viewUserDetails(userId) {
            // Fetch user details
            fetch(`get_user_details.php?id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('modalUserName').textContent = data.user.first_name + ' ' + data.user.last_name;
                        document.getElementById('modalUserRole').textContent = data.user.role;
                        document.getElementById('modalUserEmail').textContent = data.user.email;
                        document.getElementById('modalUserId').textContent = data.user.user_id;
                        document.getElementById('modalUserGender').textContent = data.user.gender;
                        document.getElementById('modalUserSection').textContent = data.user.section || '-';
                        document.getElementById('modalUserJoined').textContent = new Date(data.user.created_at).toLocaleDateString();
                        
                        // Show/hide section based on role
                        const sectionElement = document.querySelector('.client-only');
                        if (data.user.role === 'client') {
                            sectionElement.style.display = 'flex';
                        } else {
                            sectionElement.style.display = 'none';
                        }
                        
                        modal.style.display = 'flex';
                        modal.classList.add('show');
                        document.body.style.overflow = 'hidden';
                    } else {
                        showToast('Failed to load user details', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error loading user details', 'error');
                });
        }

        // Close modal functions
        function closeModal(modalElement) {
            modalElement.classList.remove('show');
            setTimeout(() => {
                modalElement.style.display = 'none';
            }, 300);
            document.body.style.overflow = '';
        }

        // Close modal when clicking close button
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('close')) {
                const modal = event.target.closest('.modal');
                if (modal) {
                    closeModal(modal);
                }
            }
        });

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal(event.target);
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const openModal = document.querySelector('.modal[style*="flex"]');
                if (openModal) {
                    closeModal(openModal);
                }
            }
        });
    </script>
    <script>
        // Edit Modal functionality
        const editModal = document.getElementById('editUserModal');
        const saveEditUserBtn = document.getElementById('saveEditUserBtn');

        function editUser(userId) {
            fetch(`get_user_details.php?id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('editUserId').value = data.user.id;
                        document.getElementById('editFirstName').value = data.user.first_name;
                        document.getElementById('editLastName').value = data.user.last_name;
                        document.getElementById('editRole').value = data.user.role;
                        document.getElementById('editEmail').value = data.user.email;
                        document.getElementById('editUserIdField').value = data.user.user_id;
                        document.getElementById('editGender').value = data.user.gender;
                        document.getElementById('editSection').value = data.user.section || '';
                        // Show/hide section field
                        const sectionField = document.querySelector('.edit-section-field');
                        if (data.user.role === 'client') {
                            sectionField.style.display = 'flex';
                        } else {
                            sectionField.style.display = 'none';
                        }
                        editModal.style.display = 'flex';
                        editModal.classList.add('show');
                        document.body.style.overflow = 'hidden';
                    } else {
                        showToast('Failed to load user details', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error loading user details', 'error');
                });
        }

        // Close edit modal when clicking close or cancel buttons
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('edit-close') || event.target.classList.contains('edit-cancel')) {
                closeModal(editModal);
            }
        });

        saveEditUserBtn.onclick = function() {
            const form = document.getElementById('editUserForm');
            const formData = new FormData(form);
            fetch('update_user_details.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) { 
                    showToast('User updated successfully!', 'success'); 
                    closeModal(editModal);
                    location.reload(); 
                }
                else { 
                    showToast(data.error || 'Failed to update user.', 'error'); 
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error updating user.', 'error');
            });
        }

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