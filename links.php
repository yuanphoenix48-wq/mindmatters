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
    <title>External Resources - Mind Matters</title>
    <?php 
        $global_version = filemtime('styles/global.css');
        $dashboard_version = filemtime('styles/dashboard.css');
        $notifications_css_version = filemtime('styles/notifications.css');
        $articles_version = filemtime('styles/articles.css');
        $mobile_css_version = filemtime('styles/mobile.css');
        $notifications_js_version = filemtime('js/notifications.js');
        $mobile_js_version = filemtime('js/mobile.js');
    ?>
    <link rel="stylesheet" href="styles/global.css?v=<?php echo $global_version; ?>">
    <link rel="stylesheet" href="styles/dashboard.css?v=<?php echo $dashboard_version; ?>">
    <link rel="stylesheet" href="styles/notifications.css?v=<?php echo $notifications_css_version; ?>">
    <link rel="stylesheet" href="styles/articles.css?v=<?php echo $articles_version; ?>">
    <link rel="stylesheet" href="styles/mobile.css?v=<?php echo $mobile_css_version; ?>">
</head>
<body class="dbBody">
    <script src="js/notifications.js?v=<?php echo $notifications_js_version; ?>"></script>
    <script src="js/mobile.js?v=<?php echo $mobile_js_version; ?>"></script>
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
            <div class="links-container">
                <a href="resources.php" class="back-button">← Back to Resources</a>
                <header class="articles-header">
                    <h1>External Resources</h1>
                    <p>Curated collection of helpful websites, organizations, and support groups for mental health and psychology</p>
                </header>

                <div class="links-grid">
                    <!-- Professional Organizations -->
                    <section class="links-section">
                        <h2>🏛️ Professional Organizations</h2>
                        <div class="resource-cards">
                            <div class="resource-card">
                                <div class="resource-icon">🧠</div>
                                <h3>American Psychological Association (APA)</h3>
                                <p>The largest scientific and professional organization representing psychology in the United States.</p>
                                <div class="resource-links">
                                    <a href="https://www.apa.org" target="_blank" class="resource-link">Visit Website</a>
                                    <a href="https://www.apa.org/membership" target="_blank" class="resource-link secondary">Join APA</a>
                                </div>
                            </div>

                            <div class="resource-card">
                                <div class="resource-icon">🌍</div>
                                <h3>World Health Organization (WHO)</h3>
                                <p>Global authority on public health, including mental health policies and resources.</p>
                                <div class="resource-links">
                                    <a href="https://www.who.int/health-topics/mental-health" target="_blank" class="resource-link">Mental Health Resources</a>
                                    <a href="https://www.who.int/news-room/fact-sheets/detail/mental-health-strengthening-our-response" target="_blank" class="resource-link secondary">Fact Sheets</a>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Educational Resources -->
                    <section class="links-section">
                        <h2>📚 Educational Resources</h2>
                        <div class="resource-cards">
                            <div class="resource-card">
                                <div class="resource-icon">📖</div>
                                <h3>Psychology Journals & Research</h3>
                                <ul class="resource-list">
                                    <li>
                                        <a href="https://www.apa.org/pubs/journals" target="_blank">APA Journals</a>
                                        <span class="resource-tag">Peer-Reviewed</span>
                                    </li>
                                    <li>
                                        <a href="https://www.nature.com/subjects/psychology" target="_blank">Nature Psychology</a>
                                        <span class="resource-tag">Scientific</span>
                                    </li>
                                    <li>
                                        <a href="https://www.psychologicalscience.org/publications" target="_blank">Psychological Science</a>
                                        <span class="resource-tag">Research</span>
                                    </li>
                                </ul>
                            </div>

                            <div class="resource-card">
                                <div class="resource-icon">🎓</div>
                                <h3>Online Learning Platforms</h3>
                                <ul class="resource-list">
                                    <li>
                                        <a href="https://www.coursera.org/learn/introduction-psychology" target="_blank">Coursera Psychology Courses</a>
                                        <span class="resource-tag">Free Courses</span>
                                    </li>
                                    <li>
                                        <a href="https://www.ted.com/topics/psychology" target="_blank">TED Talks: Psychology</a>
                                        <span class="resource-tag">Inspirational</span>
                                    </li>
                                    <li>
                                        <a href="https://www.edx.org/learn/psychology" target="_blank">edX Psychology Programs</a>
                                        <span class="resource-tag">University Level</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <!-- Mental Health Organizations -->
                    <section class="links-section">
                        <h2>💚 Mental Health Organizations</h2>
                        <div class="resource-cards">
                            <div class="resource-card">
                                <div class="resource-icon">🏥</div>
                                <h3>National Institute of Mental Health (NIMH)</h3>
                                <p>Leading federal agency for research on mental disorders and mental health.</p>
                                <div class="resource-links">
                                    <a href="https://www.nimh.nih.gov" target="_blank" class="resource-link">Visit Website</a>
                                    <a href="https://www.nimh.nih.gov/health/publications" target="_blank" class="resource-link secondary">Publications</a>
                                </div>
                            </div>

                            <div class="resource-card">
                                <div class="resource-icon">🤝</div>
                                <h3>Mental Health America (MHA)</h3>
                                <p>Leading community-based nonprofit dedicated to addressing mental health needs.</p>
                                <div class="resource-links">
                                    <a href="https://www.mhanational.org" target="_blank" class="resource-link">Visit Website</a>
                                    <a href="https://www.mhanational.org/get-involved" target="_blank" class="resource-link secondary">Get Involved</a>
                                </div>
                            </div>

                            <div class="resource-card">
                                <div class="resource-icon">📞</div>
                                <h3>Crisis Support Resources</h3>
                                <ul class="resource-list">
                                    <li>
                                        <a href="https://www.crisistextline.org" target="_blank">Crisis Text Line</a>
                                        <span class="resource-tag">Text HOME to 741741</span>
                                    </li>
                                    <li>
                                        <a href="https://suicidepreventionlifeline.org" target="_blank">National Suicide Prevention Lifeline</a>
                                        <span class="resource-tag">988</span>
                                    </li>
                                    <li>
                                        <a href="https://www.samhsa.gov/find-help/national-helpline" target="_blank">SAMHSA National Helpline</a>
                                        <span class="resource-tag">1-800-662-4357</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <!-- Support Groups -->
                    <section class="links-section">
                        <h2>👥 Support Groups & Communities</h2>
                        <div class="resource-cards">
                            <div class="resource-card">
                                <div class="resource-icon">💬</div>
                                <h3>Online Support Communities</h3>
                                <ul class="resource-list">
                                    <li>
                                        <a href="https://www.reddit.com/r/mentalhealth" target="_blank">r/MentalHealth</a>
                                        <span class="resource-tag">Reddit Community</span>
                                    </li>
                                    <li>
                                        <a href="https://www.reddit.com/r/Anxiety" target="_blank">r/Anxiety</a>
                                        <span class="resource-tag">Anxiety Support</span>
                                    </li>
                                    <li>
                                        <a href="https://www.reddit.com/r/depression" target="_blank">r/Depression</a>
                                        <span class="resource-tag">Depression Support</span>
                                    </li>
                                </ul>
                            </div>

                            <div class="resource-card">
                                <div class="resource-icon">🎓</div>
                                <h3>Student Support Networks</h3>
                                <ul class="resource-list">
                                    <li>
                                        <a href="https://www.apa.org/education-career/guide" target="_blank">APA Student Resources</a>
                                        <span class="resource-tag">Professional Development</span>
                                    </li>
                                    <li>
                                        <a href="https://www.psychologicalscience.org/observer" target="_blank">APS Student Resources</a>
                                        <span class="resource-tag">Research Opportunities</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <!-- Research & Publications -->
                    <section class="links-section">
                        <h2>🔬 Research & Publications</h2>
                        <div class="resource-cards">
                            <div class="resource-card">
                                <div class="resource-icon">📊</div>
                                <h3>Academic Databases</h3>
                                <ul class="resource-list">
                                    <li>
                                        <a href="https://www.jstor.org" target="_blank">JSTOR</a>
                                        <span class="resource-tag">Research Papers</span>
                                    </li>
                                    <li>
                                        <a href="https://www.sciencedirect.com" target="_blank">ScienceDirect</a>
                                        <span class="resource-tag">Scientific Journals</span>
                                    </li>
                                    <li>
                                        <a href="https://pubmed.ncbi.nlm.nih.gov" target="_blank">PubMed</a>
                                        <span class="resource-tag">Medical Research</span>
                                    </li>
                                </ul>
                            </div>

                            <div class="resource-card">
                                <div class="resource-icon">✍️</div>
                                <h3>Research Tools & Guidelines</h3>
                                <ul class="resource-list">
                                    <li>
                                        <a href="https://apastyle.apa.org" target="_blank">APA Style Guide</a>
                                        <span class="resource-tag">Writing Format</span>
                                    </li>
                                    <li>
                                        <a href="https://www.researchgate.net" target="_blank">ResearchGate</a>
                                        <span class="resource-tag">Academic Network</span>
                                    </li>
                                    <li>
                                        <a href="https://www.zotero.org" target="_blank">Zotero</a>
                                        <span class="resource-tag">Reference Manager</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <!-- Apps & Tools -->
                    <section class="links-section">
                        <h2>📱 Apps & Digital Tools</h2>
                        <div class="resource-cards">
                            <div class="resource-card">
                                <div class="resource-icon">🧘</div>
                                <h3>Meditation & Mindfulness</h3>
                                <ul class="resource-list">
                                    <li>
                                        <a href="https://www.headspace.com" target="_blank">Headspace</a>
                                        <span class="resource-tag">Meditation App</span>
                                    </li>
                                    <li>
                                        <a href="https://www.calm.com" target="_blank">Calm</a>
                                        <span class="resource-tag">Sleep & Meditation</span>
                                    </li>
                                    <li>
                                        <a href="https://www.insighttimer.com" target="_blank">Insight Timer</a>
                                        <span class="resource-tag">Free Meditation</span>
                                    </li>
                                </ul>
                            </div>

                            <div class="resource-card">
                                <div class="resource-icon">📈</div>
                                <h3>Mood Tracking & Wellness</h3>
                                <ul class="resource-list">
                                    <li>
                                        <a href="https://www.daylio.net" target="_blank">Daylio</a>
                                        <span class="resource-tag">Mood Tracking</span>
                                    </li>
                                    <li>
                                        <a href="https://www.moodmeterapp.com" target="_blank">Mood Meter</a>
                                        <span class="resource-tag">Emotional Intelligence</span>
                                    </li>
                                    <li>
                                        <a href="https://www.forestapp.cc" target="_blank">Forest</a>
                                        <span class="resource-tag">Focus & Productivity</span>
                                    </li>
                                </ul>
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
    <script>
        // Fallback minimal toast if notifications system is unavailable
        if (typeof window.showToast !== 'function') {
            window.showToast = function(message, type) {
                try {
                    const t = document.createElement('div');
                    t.style.position = 'fixed';
                    t.style.top = '20px';
                    t.style.right = '20px';
                    t.style.zIndex = '99999';
                    t.style.background = type === 'success' ? '#d4edda' : type === 'error' ? '#f8d7da' : type === 'warning' ? '#fff3cd' : '#e9ecef';
                    t.style.color = '#111827';
                    t.style.border = '1px solid rgba(0,0,0,0.1)';
                    t.style.borderLeft = '4px solid ' + (type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : type === 'warning' ? '#ffc107' : '#1D5D9B');
                    t.style.borderRadius = '8px';
                    t.style.padding = '10px 14px';
                    t.style.boxShadow = '0 10px 20px rgba(0,0,0,0.12)';
                    t.textContent = message;
                    document.body.appendChild(t);
                    setTimeout(()=>{ t.style.transition = 'opacity .3s, transform .3s'; t.style.opacity='0'; t.style.transform='translateY(-6px)'; setTimeout(()=>{ try{ document.body.removeChild(t); }catch(_){} }, 350); }, 2500);
                } catch(_) { try { alert(message); } catch(__) {} }
            };
        }
    </script>

    <style>
        .links-container {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .links-grid {
            display: flex;
            flex-direction: column;
            gap: 3rem;
        }

        .links-section {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .links-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .links-section h2 {
            color: #1D5D9B;
            margin-bottom: 2rem;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Resource Cards */
        .resource-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }

        .resource-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 15px;
            padding: 2rem;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .resource-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #1D5D9B, #3498db);
        }

        .resource-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(29, 93, 155, 0.15);
        }

        .resource-icon {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            display: block;
        }

        .resource-card h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-size: 1.3rem;
            font-weight: 600;
        }

        .resource-card p {
            color: #666;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        /* Resource Lists */
        .resource-list {
            list-style: none;
            padding: 0;
            margin: 1.5rem 0;
        }

        .resource-list li {
            margin-bottom: 1.2rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            transition: background-color 0.2s ease;
        }

        .resource-list li:hover {
            background: #e9ecef;
        }

        .resource-list a {
            color: #1D5D9B;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: color 0.2s ease;
        }

        .resource-list a:hover {
            color: #14487a;
            text-decoration: underline;
        }

        .resource-tag {
            display: inline-block;
            background: linear-gradient(135deg, #1D5D9B, #3498db);
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            align-self: flex-start;
        }

        /* Resource Links */
        .resource-links {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .resource-link {
            display: inline-flex;
            align-items: center;
            padding: 0.8rem 1.5rem;
            background: linear-gradient(135deg, #1D5D9B, #3498db);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(29, 93, 155, 0.3);
        }

        .resource-link:hover {
            background: linear-gradient(135deg, #14487a, #2980b9);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(29, 93, 155, 0.4);
        }

        .resource-link.secondary {
            background: linear-gradient(135deg, #6c757d, #95a5a6);
            box-shadow: 0 2px 10px rgba(108, 117, 125, 0.3);
        }

        .resource-link.secondary:hover {
            background: linear-gradient(135deg, #5a6268, #7f8c8d);
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.4);
        }

        /* Back button styles */
        .back-button {
            display: inline-flex;
            align-items: center;
            padding: 0.8rem 1.5rem;
            background: linear-gradient(135deg, #1D5D9B, #3498db);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(29, 93, 155, 0.3);
        }

        .back-button:hover {
            background: linear-gradient(135deg, #14487a, #2980b9);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(29, 93, 155, 0.4);
        }

        /* Articles header */
        .articles-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .articles-header h1 {
            color: #1D5D9B;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .articles-header p {
            color: #666;
            font-size: 1.2rem;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .links-container {
                padding: 20px;
            }

            .links-section {
                padding: 1.5rem;
            }

            .resource-cards {
                grid-template-columns: 1fr;
            }

            .resource-links {
                flex-direction: column;
            }

            .articles-header h1 {
                font-size: 2rem;
            }

            .articles-header p {
                font-size: 1rem;
            }
        }

        @media (max-width: 480px) {
            .links-container {
                padding: 15px;
            }

            .resource-card {
                padding: 1.5rem;
            }

            .resource-icon {
                font-size: 2.5rem;
            }
        }
    </style>
</body>
</html>