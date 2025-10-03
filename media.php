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

// Fetch media resources from database
$sql = "SELECT * FROM media_resources ORDER BY featured DESC, created_at DESC";
$result = $conn->query($sql);
$mediaResources = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multimedia Recommendations - Mind Matters</title>
    <?php 
        $global_version = filemtime('styles/global.css');
        $dashboard_version = filemtime('styles/dashboard.css');
        $notifications_css_version = filemtime('styles/notifications.css');
        $media_css_version = filemtime('styles/media.css');
        $mobile_css_version = filemtime('styles/mobile.css');
        $notifications_js_version = filemtime('js/notifications.js');
        $mobile_js_version = filemtime('js/mobile.js');
        $session_mgr_js_version = filemtime('js/session_manager.js');
    ?>
    <link rel="stylesheet" href="styles/global.css?v=<?php echo $global_version; ?>">
    <link rel="stylesheet" href="styles/dashboard.css?v=<?php echo $dashboard_version; ?>">
    <link rel="stylesheet" href="styles/notifications.css?v=<?php echo $notifications_css_version; ?>">
    <link rel="stylesheet" href="styles/media.css?v=<?php echo $media_css_version; ?>">
    <link rel="stylesheet" href="styles/mobile.css?v=<?php echo $mobile_css_version; ?>">
</head>
<body class="dbBody">
    <script src="js/notifications.js?v=<?php echo $notifications_js_version; ?>"></script>
    <script src="js/mobile.js?v=<?php echo $mobile_js_version; ?>"></script>
    <script src="js/session_manager.js?v=<?php echo $session_mgr_js_version; ?>"></script>
    <!-- Logout Confirm Modal (unified, same as My Sessions/Resources/Articles/Selfhelp) -->
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
            <div class="media-container">
                <a href="resources.php" class="back-button">← Back to Resources</a>
            <header class="articles-header">
                <h1>Multimedia Recommendations</h1>
                <p>Explore curated videos, podcasts, and other media resources for mental health support</p>
            </header>

            <div class="media-categories">
                <button class="category-btn active" data-category="all">All</button>
                <button class="category-btn" data-category="videos">Videos</button>
                <button class="category-btn" data-category="podcasts">Podcasts</button>
                <button class="category-btn" data-category="books">Books</button>
                <button class="category-btn" data-category="apps">Apps</button>
                <button class="category-btn" data-category="interactive">Interactive</button>
                <button class="category-btn" data-category="websites">Websites</button>
            </div>

            <div class="media-grid">
                <?php if (empty($mediaResources)): ?>
                    <!-- Fallback content if database is empty -->
                    <div class="media-card" data-category="videos">
                        <div class="media-thumbnail">
                            <img src="https://images.unsplash.com/photo-1506905925346-14bda5d4c69d?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80" alt="Understanding Anxiety">
                            <span class="media-type">🎥 Video</span>
                        </div>
                        <div class="media-content">
                            <h3>Understanding Anxiety in College Students</h3>
                            <p>A comprehensive guide to recognizing and managing anxiety in academic settings.</p>
                            <div class="media-meta">
                                <span class="duration">15:30</span>
                                <span class="source">Psychology Department</span>
                            </div>
                            <a href="https://www.youtube.com/watch?v=example1" target="_blank" class="media-button">Watch Now</a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Dynamic content from database -->
                    <?php foreach ($mediaResources as $resource): ?>
                        <div class="media-card" data-category="<?php echo htmlspecialchars($resource['category']); ?>">
                            <div class="media-thumbnail">
                                <img src="<?php echo htmlspecialchars($resource['thumbnail_url'] ?? 'https://images.unsplash.com/photo-1506905925346-14bda5d4c69d?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80'); ?>" alt="<?php echo htmlspecialchars($resource['title']); ?>">
                                <span class="media-type">
                                    <?php
                                    $typeIcons = [
                                        'videos' => '🎥',
                                        'podcasts' => '🎧',
                                        'books' => '📚',
                                        'apps' => '📱',
                                        'interactive' => '🔄',
                                        'websites' => '🌐'
                                    ];
                                    echo $typeIcons[$resource['category']] ?? '📄';
                                    ?>
                                    <?php echo htmlspecialchars($resource['media_type']); ?>
                                </span>
                            </div>
                            <div class="media-content">
                                <h3><?php echo htmlspecialchars($resource['title']); ?></h3>
                                <p><?php echo htmlspecialchars($resource['description']); ?></p>
                                <div class="media-meta">
                                    <?php if ($resource['duration']): ?>
                                        <span class="duration"><?php echo htmlspecialchars($resource['duration']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($resource['author']): ?>
                                        <span class="author"><?php echo htmlspecialchars($resource['author']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($resource['source']): ?>
                                        <span class="source"><?php echo htmlspecialchars($resource['source']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($resource['platform']): ?>
                                        <span class="platform"><?php echo htmlspecialchars($resource['platform']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($resource['rating']): ?>
                                        <span class="rating"><?php echo htmlspecialchars($resource['rating']); ?> ★</span>
                                    <?php endif; ?>
                                    <?php if ($resource['year']): ?>
                                        <span class="year"><?php echo htmlspecialchars($resource['year']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <a href="<?php echo htmlspecialchars($resource['external_url']); ?>" target="_blank" class="media-button">
                                    <?php
                                    $buttonTexts = [
                                        'videos' => 'Watch Now',
                                        'podcasts' => 'Listen Now',
                                        'books' => 'Read More',
                                        'apps' => 'Learn More',
                                        'interactive' => 'Try Now',
                                        'websites' => 'Visit Site'
                                    ];
                                    echo $buttonTexts[$resource['category']] ?? 'Learn More';
                                    ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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


    <script>
        // Category filtering functionality
        const categoryButtons = document.querySelectorAll('.category-btn');
        const mediaCards = document.querySelectorAll('.media-card');

        categoryButtons.forEach(button => {
            button.addEventListener('click', () => {
                // Remove active class from all buttons
                categoryButtons.forEach(btn => btn.classList.remove('active'));
                // Add active class to clicked button
                button.classList.add('active');

                const category = button.dataset.category;

                mediaCards.forEach(card => {
                    if (category === 'all' || card.dataset.category === category) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html> 