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

$sql = "SELECT s.id, s.session_date, s.session_time, s.status,
               CONCAT(stud.first_name, ' ', stud.last_name) AS client_name,
               CONCAT(doc.first_name, ' ', doc.last_name)   AS therapist_name
        FROM sessions s
        JOIN users stud ON s.client_id = stud.id
        LEFT JOIN users doc ON s.therapist_id = doc.id
        ORDER BY s.session_date DESC, s.session_time DESC
        LIMIT 100";
$res = $conn->query($sql);
$sessions = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Management - Mind Matters</title>
    <link rel="stylesheet" href="styles/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="styles/dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="styles/admin_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="styles/admin_sessions.css?v=<?php echo time(); ?>">
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
		/* View Feedback button */
		.view-feedback-btn{ padding:.45rem .85rem; font-size:.85rem; border-radius:8px; display:inline-flex; align-items:center; gap:6px; box-shadow:0 1px 1px rgba(0,0,0,.05); transition:all .15s ease; background:#2563eb; border:1px solid #1d4ed8; color:#ffffff }
		.view-feedback-btn:hover{ background:#1d4ed8; border-color:#1e40af }
		.view-feedback-btn:active{ transform:translateY(1px) }
		.view-feedback-btn .btn-text{ display:inline-block }
		@media (max-width: 520px){ .view-feedback-btn{ padding:.4rem .7rem; font-size:.8rem } .view-feedback-btn .btn-text{ display:none } }
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
                <li class="sidebarNavItem active"><a href="admin_sessions.php" class="sidebarNavLink">Session Management</a></li>
                <li class="sidebarNavItem"><a href="admin_reports.php" class="sidebarNavLink">Reports</a></li>
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
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo count(array_filter($sessions, function($s) { return $s['status'] === 'scheduled'; })); ?></h3>
                        <p>Scheduled</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo count(array_filter($sessions, function($s) { return $s['status'] === 'completed'; })); ?></h3>
                        <p>Completed</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo count(array_filter($sessions, function($s) { return $s['status'] === 'pending'; })); ?></h3>
                        <p>Pending</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo count(array_filter($sessions, function($s) { return $s['status'] === 'cancelled'; })); ?></h3>
                        <p>Cancelled</p>
                    </div>
                </div>
            </div>

            <div class="dashboard-card">
                <h2><i class="fas fa-calendar-alt"></i> Session Management</h2>
                <div class="users-table-container">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-calendar"></i> Date</th>
                                <th><i class="fas fa-clock"></i> Time</th>
                                <th><i class="fas fa-user"></i> Client</th>
                                <th><i class="fas fa-user-md"></i> Therapist</th>
									<th><i class="fas fa-info-circle"></i> Status</th>
									<th><i class="fas fa-comments"></i> Feedback</th>
                            </tr>
                        </thead>
                        <tbody>
							<?php if (empty($sessions)): ?>
								<tr><td colspan="6" style="text-align: center; padding: 3rem 1rem; color: var(--gray-500); font-style: italic;">
                                <i class="fas fa-calendar-times" style="font-size: 2rem; margin-bottom: 1rem; display: block; color: var(--gray-400);"></i>
                                No sessions found.
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($sessions as $s): ?>
								<tr data-session-id="<?php echo $s['id']; ?>">
                                <td><?php echo date('M j, Y', strtotime($s['session_date'])); ?></td>
                                <td><?php echo date('g:i A', strtotime($s['session_time'])); ?></td>
                                <td><?php echo htmlspecialchars($s['client_name']); ?></td>
                                <td><?php echo htmlspecialchars($s['therapist_name'] ?? '-'); ?></td>
									<td><span class="status-badge <?php echo $s['status']; ?>"><?php echo ucfirst($s['status']); ?></span></td>
									<td>
										<button type="button" class="submit-btn view-feedback-btn" onclick="openFeedbackModal(<?php echo (int)$s['id']; ?>)">
											<i class="fas fa-eye"></i>
											<span class="btn-text">View</span>
										</button>
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

	<!-- Session Feedback Modal -->
	<div id="adminFeedbackModal" style="display:none; position: fixed; inset: 0; background: rgba(17,24,39,.6); z-index: 1000; align-items: center; justify-content: center; padding: 16px;">
		<div class="modal-content" style="background: linear-gradient(180deg,#ffffff,#f9fafb); width: 100%; max-width: 820px; max-height: 92vh; overflow: auto; border-radius: 14px; box-shadow: 0 20px 40px rgba(0,0,0,.25); border: 1px solid #e5e7eb;">
			<div class="modal-header" style="display:flex; align-items:center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid #e5e7eb; position: sticky; top: 0; background: #ffffff; z-index: 1;">
				<h3 style="margin:0; font-size: 1.125rem; display:flex; align-items:center; gap:8px;"><span>🗒️</span><span>Session Feedback</span></h3>
				<span class="close" style="cursor:pointer; font-size: 1.5rem; line-height: 1; padding: 4px 8px; border-radius: 6px;" onclick="closeAdminFeedback()" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='transparent'">&times;</span>
			</div>
			<div class="modal-body" id="adminFeedbackBody" style="padding: 16px 20px;">
				<div style="color:#6b7280;">Loading feedback...</div>
			</div>
			<div class="modal-actions" style="display:flex; justify-content:flex-end; gap: 8px; padding: 12px 20px; border-top: 1px solid #e5e7eb; background:#ffffff; position: sticky; bottom:0;">
				<button type="button" class="cancel-btn" style="border-radius:8px; padding:.5rem .9rem;" onclick="closeAdminFeedback()">Close</button>
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

		// Admin Feedback Modal
		function openFeedbackModal(sessionId) {
			const modal = document.getElementById('adminFeedbackModal');
			const body = document.getElementById('adminFeedbackBody');
			if (!modal || !body) return;
			body.innerHTML = '<div style="color:#6b7280;">Loading feedback...</div>';
			modal.style.display = 'flex';
			document.body.style.overflow = 'hidden';
			fetch('get_session_feedback.php?session_id=' + encodeURIComponent(String(sessionId)), { credentials: 'same-origin' })
				.then(async r => {
					if (!r.ok) {
						const txt = await r.text().catch(()=>'');
						throw new Error(txt || ('HTTP ' + r.status));
					}
					return r.json();
				})
				.then(data => {
					if (!data || data.success !== true) {
						const err = data && data.error ? String(data.error) : 'Unable to load feedback.';
						body.innerHTML = '<div style="color:#ef4444;">' + escapeHtml(err) + '</div>';
						return;
					}
					const parts = [];
					// Add small style helpers for chips
					const styleTagId = 'admin-feedback-inline-styles';
					if (!document.getElementById(styleTagId)) {
						const st = document.createElement('style');
						st.id = styleTagId;
						st.textContent = '.chip{display:inline-block;background:#f3f4f6;color:#374151;border:1px solid #e5e7eb;border-radius:999px;padding:2px 8px;font-size:.75rem;margin-right:6px} .view-feedback-btn{padding:.45rem .85rem;font-size:.85rem;border-radius:8px;display:inline-flex;align-items:center;gap:6px;box-shadow:0 1px 1px rgba(0,0,0,.05);transition:all .15s ease;background:#2563eb;border:1px solid #1d4ed8;color:#ffffff} .view-feedback-btn:hover{background:#1d4ed8;border-color:#1e40af} .view-feedback-btn:active{transform:translateY(1px)} .view-feedback-btn .btn-text{display:inline-block} @media (max-width: 520px){ .view-feedback-btn{padding:.4rem .7rem;font-size:.8rem} .view-feedback-btn .btn-text{display:none} }';
						document.head.appendChild(st);
					}
					// Client feedback intentionally omitted for consistency; showing System Feedback only
					// System feedback (array)
					if (Array.isArray(data.system) && data.system.length > 0) {
						const sysBlocks = data.system.map(sf => {
							const role = (sf.user_role || '').toLowerCase();
							const who = role ? (role.charAt(0).toUpperCase() + role.slice(1)) : 'User';
							const chips = [
								sf.ease_of_scheduling ? ('<span class="chip">Scheduling: ' + sf.ease_of_scheduling + '/5</span>') : '',
								sf.ease_of_use ? ('<span class="chip">Ease of Use: ' + sf.ease_of_use + '/5</span>') : '',
								typeof sf.recommend === 'number' ? ('<span class="chip">Recommend: ' + (sf.recommend ? 'Yes' : 'No') + '</span>') : ''
							].filter(Boolean).join(' ');
							const improvementText = (sf.improvement !== null && sf.improvement !== undefined) ? String(sf.improvement).trim() : '';
							return (
								'<div class="sf-item" style="border:1px solid #e5e7eb; border-radius:10px; padding:12px 14px; margin-bottom:12px; background:#fff;">' +
									'<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">' +
										'<div style="display:flex; align-items:center; gap:8px; font-weight:600; color:#111827;"><span>🛠️</span><span>System Feedback</span></div>' +
										'<div style="font-size:.8rem; color:#6b7280;">' + (sf.user_name ? escapeHtml(sf.user_name) + ' • ' : '') + who + '</div>' +
									'</div>' +
									(chips ? ('<div style="margin-bottom:6px;">' + chips + '</div>') : '') +
									(sf.liked_most ? ('<div style="color:#374151; margin-bottom:4px;"><strong>Liked Most:</strong> ' + escapeHtml(sf.liked_most) + '</div>') : '') +
									('<div style="color:#374151; margin-bottom:4px;"><strong>Improvement:</strong> ' + (improvementText !== '' ? escapeHtml(improvementText) : '<span style="color:#6b7280;">—</span>') + '</div>') +
								'</div>'
							);
						}).join('');
						parts.push('<div style="margin-bottom:16px;">' + sysBlocks + '</div>');
					}

					if (parts.length === 0) {
						body.innerHTML = '<div style="color:#6b7280;">No feedback submitted for this session.</div>';
					} else {
						body.innerHTML = parts.join('');
					}
				})
				.catch((e) => {
					body.innerHTML = '<div style="color:#ef4444;">' + escapeHtml(String(e && e.message ? e.message : 'Unable to load feedback.')) + '</div>';
				});
		}

		function closeAdminFeedback() {
			const modal = document.getElementById('adminFeedbackModal');
			if (!modal) return;
			modal.style.display = 'none';
			document.body.style.overflow = '';
		}

		// Basic HTML escaper
		function escapeHtml(str) {
			return String(str)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#039;');
		}

    </script>
</body>
</html>

