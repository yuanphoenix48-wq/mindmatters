<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['client','student'])) {
    header('Location: index.php');
    exit();
}

require_once 'connect.php';

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'student';

// Get user profile info
$sql = "SELECT first_name, profile_picture FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$firstName = $user['first_name'] ?? '';
$profilePicture = $user['profile_picture'] ?? 'images/profile/default_images/default_profile.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Hotlines - Mind Matters</title>
    <link rel="stylesheet" href="styles/global.css">
    <link rel="stylesheet" href="styles/dashboard.css">
    <link rel="stylesheet" href="styles/notifications.css">
    <link rel="stylesheet" href="styles/articles.css">
    <link rel="stylesheet" href="styles/mobile.css">
    <script src="js/notifications.js"></script>
    <script src="js/mobile.js"></script>
</head>
<body class="dbBody">
    <!-- Logout Confirm Modal (unified, same as other pages) -->
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
    <!-- Mobile Header (mirrored from my_session.php) -->
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
                <img src="<?php echo htmlspecialchars($profilePicture); ?>" alt="Profile Picture" class="defaultPicture">
                <h1 class="profileName"><?php echo htmlspecialchars($firstName); ?></h1>
                <p class="userRole"><?php echo ucfirst($userRole); ?></p>
            </div>
            <ul class="sidebarNavList">
                <li class="sidebarNavItem"><a href="dashboard.php" class="sidebarNavLink">Home</a></li>
                <li class="sidebarNavItem"><a href="my_session.php" class="sidebarNavLink">My Sessions</a></li>
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
            <div class="emergency-container">
                <a href="resources.php" class="back-button">← Back to Resources</a>
            <header class="articles-header">
                <h1>Emergency Hotlines</h1>
                <p>Immediate support and crisis intervention services available 24/7</p>
            </header>

            <div class="emergency-grid">
                <!-- National Emergency Numbers -->
                <section class="emergency-section">
                    <h2>National Emergency Numbers</h2>
                    <div class="hotline-cards">
                        <div class="hotline-card primary">
                            <div class="hotline-icon">🚑</div>
                            <h3>National Emergency Hotline</h3>
                            <p class="hotline-number">911</p>
                            <p class="hotline-desc">For all types of emergencies</p>
                        </div>

                        <div class="hotline-card primary">
                            <div class="hotline-icon">🚓</div>
                            <h3>Philippine National Police</h3>
                            <p class="hotline-number">117</p>
                            <p class="hotline-desc">For police assistance</p>
                        </div>
                    </div>
                </section>

                <!-- Mental Health Crisis Hotlines -->
                <section class="emergency-section">
                    <h2>Mental Health Crisis Hotlines</h2>
                    <div class="hotline-cards">
                        <div class="hotline-card crisis">
                            <div class="hotline-icon">💚</div>
                            <h3>National Center for Mental Health</h3>
                            <p class="hotline-number">0917-899-8727</p>
                            <p class="hotline-desc">24/7 Crisis Hotline</p>
                            <p class="hotline-note">Available in Filipino and English</p>
                        </div>

                        <div class="hotline-card crisis">
                            <div class="hotline-icon">💙</div>
                            <h3>Hopeline Philippines</h3>
                            <p class="hotline-number">0919-057-1553</p>
                            <p class="hotline-number">1800-1888-1553</p>
                            <p class="hotline-desc">24/7 Suicide Prevention Hotline</p>
                            <p class="hotline-note">Free calls from Globe and TM</p>
                        </div>

                        <div class="hotline-card crisis">
                            <div class="hotline-icon">💜</div>
                            <h3>In Touch Community Services</h3>
                            <p class="hotline-number">(02) 8893-7603</p>
                            <p class="hotline-number">0917-800-1123</p>
                            <p class="hotline-desc">24/7 Crisis Line</p>
                            <p class="hotline-note">Professional counseling services</p>
                        </div>
                    </div>
                </section>

                <!-- University Support Services -->
                <section class="emergency-section">
                    <h2>University Support Services</h2>
                    <div class="hotline-cards">
                        <div class="hotline-card university">
                            <div class="hotline-icon">🎓</div>
                            <h3>Psychology Department</h3>
                            <p class="hotline-number">(02) 8123-4567</p>
                            <p class="hotline-desc">Monday to Friday, 8:00 AM - 5:00 PM</p>
                            <p class="hotline-note">For academic and mental health support</p>
                        </div>

                        <div class="hotline-card university">
                            <div class="hotline-icon">🏥</div>
                            <h3>University Health Services</h3>
                            <p class="hotline-number">(02) 8123-4568</p>
                            <p class="hotline-desc">24/7 Medical Emergency</p>
                            <p class="hotline-note">Located at the University Medical Center</p>
                        </div>
                    </div>
                </section>

                <!-- What to Do in a Crisis -->
                <section class="emergency-section">
                    <h2>What to Do in a Crisis</h2>
                    <div class="crisis-steps">
                        <div class="step-card">
                            <div class="step-number">1</div>
                            <h3>Stay Calm</h3>
                            <p>Take deep breaths and try to remain composed. Your safety is the priority.</p>
                        </div>

                        <div class="step-card">
                            <div class="step-number">2</div>
                            <h3>Call for Help</h3>
                            <p>Dial the appropriate hotline number. Help is available 24/7.</p>
                        </div>

                        <div class="step-card">
                            <div class="step-number">3</div>
                            <h3>Stay Connected</h3>
                            <p>Keep the line open and stay on the phone until help arrives.</p>
                        </div>

                        <div class="step-card">
                            <div class="step-number">4</div>
                            <h3>Follow Instructions</h3>
                            <p>Listen carefully and follow the guidance provided by the responder.</p>
                        </div>
                    </div>
                </section>

                <!-- Emergency Contacts -->
                <section class="emergency-section">
                    <h2>Save These Numbers</h2>
                    <div class="save-contacts">
                        <p>Add these emergency numbers to your phone contacts for quick access:</p>
                        <div class="contact-buttons">
                            <button class="contact-button" onclick="saveContact('911', 'National Emergency')">
                                Save 911
                            </button>
                            <button class="contact-button" onclick="saveContact('0917-899-USAP', 'NCMH Crisis')">
                                Save NCMH
                            </button>
                            <button class="contact-button" onclick="saveContact('0917-558-4673', 'Hopeline')">
                                Save Hopeline
                            </button>
                        </div>
                    </div>
                </section>
            </div>
            </div>
        </div>
    </div>

    <script>
        // Logout modal handlers (aligned with other pages)
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
                document.body.classList.remove('mobile-menu-open');
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

    <style>
        .emergency-container {
            padding: 30px;
        }
        .emergency-grid {
            display: flex;
            flex-direction: column;
            gap: 3rem;
        }

        .emergency-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .emergency-section h2 {
            color: #2c3e50;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        /* Hotline Cards */
        .hotline-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .hotline-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .hotline-card:hover {
            transform: translateY(-5px);
        }

        .hotline-card.primary {
            background: #fff3f3;
            border-left: 4px solid #ff4444;
        }

        .hotline-card.crisis {
            background: #f0f7ff;
            border-left: 4px solid #3498db;
        }

        .hotline-card.university {
            background: #f0fff4;
            border-left: 4px solid #2ecc71;
        }

        .hotline-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .hotline-card h3 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .hotline-number {
            font-size: 1.5rem;
            color: #e74c3c;
            font-weight: bold;
            margin: 0.5rem 0;
        }

        .hotline-desc {
            color: #666;
            margin-bottom: 0.5rem;
        }

        .hotline-note {
            color: #888;
            font-size: 0.9rem;
            font-style: italic;
        }

        /* Crisis Steps */
        .crisis-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .step-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
        }

        .step-number {
            width: 40px;
            height: 40px;
            background: #3498db;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.2rem;
            font-weight: bold;
        }

        /* Save Contacts */
        .save-contacts {
            text-align: center;
        }

        .contact-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .contact-button {
            padding: 0.8rem 1.5rem;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .contact-button:hover {
            background-color: #2980b9;
        }

        @media (max-width: 768px) {
            .emergency-section {
                padding: 1.5rem;
            }

            .hotline-cards {
                grid-template-columns: 1fr;
            }

            .crisis-steps {
                grid-template-columns: 1fr;
            }

            .contact-buttons {
                flex-direction: column;
            }
        }

        /* Back button styles (blue, consistent across pages) */
        .back-button { display:inline-flex; align-items:center; padding:0.8rem 1.5rem; background-color:#1D5D9B; color:#fff; text-decoration:none; border-radius:6px; font-weight:600; transition:background-color 0.2s ease; margin-bottom:1.5rem; border:none; }
        .back-button:hover { background-color:#14487a; }
    </style>

    <script>
        function saveContact(number, name) {
            // This is a placeholder function. In a real implementation,
            // this would use the Web Share API or create a vCard file
            alert(`Please save this number to your contacts:\n${name}: ${number}`);
        }
    </script>
</body>
</html> 