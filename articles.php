<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['client','student'])) {
    header('Location: index.php');
    exit();
}

// Include database connection
require_once 'connect.php';

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'client';

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

// Fetch articles
$sql = "SELECT id, title, summary, link, author, date, category, image_url, read_time, featured FROM articles ORDER BY featured DESC, date DESC";
$result = $conn->query($sql);
$articles = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Educational Articles - Mind Matters</title>
    <?php 
        $global_version = filemtime('styles/global.css');
        $dashboard_version = filemtime('styles/dashboard.css');
        $notifications_css_version = filemtime('styles/notifications.css');
        $articles_version = filemtime('styles/articles.css');
        $mobile_css_version = filemtime('styles/mobile.css');
        $mobile_js_version = filemtime('js/mobile.js');
        $notifications_js_version = filemtime('js/notifications.js');
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
    <!-- Logout Confirm Modal (unified, same as My Sessions/Resources) -->
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
            <div class="articles-container">
                <a href="resources.php" class="back-button">← Back to Resources</a>
            <header class="articles-header">
                <h1>Educational Articles</h1>
                <p>Explore our collection of articles written by mental health professionals and experts</p>
            </header>

            <div class="search-filter">
                <input type="text" id="searchArticles" placeholder="Search articles...">
                <select id="categoryFilter">
                    <option value="all">All Categories</option>
                    <option value="stress">Stress Management</option>
                    <option value="anxiety">Anxiety & Depression</option>
                    <option value="academic">Academic Success</option>
                    <option value="relationships">Relationships</option>
                    <option value="self-care">Self-Care</option>
                    <option value="mindfulness">Mindfulness</option>
                    <option value="sleep">Sleep & Mental Health</option>
                    <option value="therapy">Therapy & Counseling</option>
                    <option value="coping">Coping Strategies</option>
                    <option value="depression">Depression</option>
                </select>
            </div>

            <div class="articles-grid">
                <?php if (empty($articles)): ?>
                    <!-- Default articles if database is empty -->
                    <div class="article-card featured" data-category="stress">
                        <div class="article-image">
                            <img src="images/articles/top 10 stress.jpg" alt="Managing Academic Stress">
                            <div class="featured-badge">Featured</div>
                        </div>
                        <div class="article-content">
                            <div class="article-category">Stress Management</div>
                            <h3>Managing Academic Stress: A Complete Guide for Students</h3>
                            <p>Learn evidence-based strategies to manage academic pressure, maintain work-life balance, and thrive in your studies while preserving your mental health.</p>
                            <div class="article-meta">
                                <span class="author">Dr. Sarah Mitchell</span>
                                <span class="date">December 15, 2023</span>
                                <span class="read-time">8 min read</span>
                            </div>
                            <a href="https://www.verywellmind.com/managing-academic-stress-3145269" target="_blank" class="read-more">Read Full Article</a>
                        </div>
                    </div>

                    <div class="article-card" data-category="anxiety">
                        <div class="article-image">
                            <img src="https://images.unsplash.com/photo-1559757148-5c350d0d3c56?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Understanding Anxiety">
                        </div>
                        <div class="article-content">
                            <div class="article-category">Anxiety & Depression</div>
                            <h3>Understanding Anxiety: Signs, Symptoms, and When to Seek Help</h3>
                            <p>Comprehensive guide to recognizing anxiety symptoms, understanding different types of anxiety disorders, and knowing when professional help is needed.</p>
                            <div class="article-meta">
                                <span class="author">Dr. Michael Chen</span>
                                <span class="date">December 10, 2023</span>
                                <span class="read-time">6 min read</span>
                            </div>
                            <a href="https://www.nimh.nih.gov/health/topics/anxiety-disorders" target="_blank" class="read-more">Read More</a>
                        </div>
                    </div>

                    <div class="article-card" data-category="academic">
                        <div class="article-image">
                            <img src="https://images.unsplash.com/photo-1434030216411-0b793f4b4173?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Study Techniques">
                        </div>
                        <div class="article-content">
                            <div class="article-category">Academic Success</div>
                            <h3>Effective Study Techniques for Better Mental Health</h3>
                            <p>Discover study methods that promote both academic success and mental well-being, including time management and stress-reduction techniques.</p>
                            <div class="article-meta">
                                <span class="author">Dr. Emily Rodriguez</span>
                                <span class="date">December 8, 2023</span>
                                <span class="read-time">7 min read</span>
                            </div>
                            <a href="https://www.apa.org/topics/learning/study-techniques" target="_blank" class="read-more">Read More</a>
                        </div>
                    </div>

                    <div class="article-card" data-category="relationships">
                        <div class="article-image">
                            <img src="https://images.unsplash.com/photo-1529156069898-49953e39b3ac?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Healthy Relationships">
                        </div>
                        <div class="article-content">
                            <div class="article-category">Relationships</div>
                            <h3>Building Healthy Relationships in College</h3>
                            <p>Learn how to develop meaningful connections, set boundaries, and maintain healthy relationships while navigating college life and mental health challenges.</p>
                            <div class="article-meta">
                                <span class="author">Dr. Jennifer Walsh</span>
                                <span class="date">December 5, 2023</span>
                                <span class="read-time">5 min read</span>
                            </div>
                            <a href="https://www.psychologytoday.com/us/blog/teen-angst/201401/healthy-relationships-college" target="_blank" class="read-more">Read More</a>
                        </div>
                    </div>

                    <div class="article-card" data-category="self-care">
                        <div class="article-image">
                            <img src="https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Self Care">
                        </div>
                        <div class="article-content">
                            <div class="article-category">Self-Care</div>
                            <h3>The Art of Self-Care: A Student's Essential Guide</h3>
                            <p>Practical self-care strategies tailored for students, including physical, emotional, and mental wellness practices that fit into busy academic schedules.</p>
                            <div class="article-meta">
                                <span class="author">Dr. Lisa Thompson</span>
                                <span class="date">December 3, 2023</span>
                                <span class="read-time">9 min read</span>
                            </div>
                            <a href="https://www.mentalhealth.org.uk/explore-mental-health/a-z-topics/self-care" target="_blank" class="read-more">Read More</a>
                        </div>
                    </div>

                    <div class="article-card" data-category="anxiety">
                        <div class="article-image">
                            <img src="https://images.unsplash.com/photo-1506905925346-14bda5d4c69d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Sleep and Mental Health">
                        </div>
                        <div class="article-content">
                            <div class="article-category">Anxiety & Depression</div>
                            <h3>Sleep and Mental Health: The Connection You Need to Know</h3>
                            <p>Explore the vital relationship between sleep quality and mental health, with practical tips for improving sleep hygiene and managing sleep-related anxiety.</p>
                            <div class="article-meta">
                                <span class="author">Dr. Robert Kim</span>
                                <span class="date">November 28, 2023</span>
                                <span class="read-time">6 min read</span>
                            </div>
                            <a href="https://www.sleepfoundation.org/mental-health" target="_blank" class="read-more">Read More</a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Dynamic articles from database -->
                    <?php foreach ($articles as $index => $article): ?>
                        <div class="article-card <?php echo $article['featured'] ? 'featured' : ''; ?>" data-category="<?php echo htmlspecialchars($article['category']); ?>">
                            <div class="article-image">
                                <img src="<?php echo htmlspecialchars($article['image_url'] ?? 'https://images.unsplash.com/photo-1506905925346-14bda5d4c69d?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80'); ?>" alt="<?php echo htmlspecialchars($article['title']); ?>">
                                <?php if ($article['featured']): ?>
                                    <div class="featured-badge">Featured</div>
                                <?php endif; ?>
                            </div>
                            <div class="article-content">
                                <div class="article-category"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $article['category']))); ?></div>
                                <h3><?php echo htmlspecialchars($article['title']); ?></h3>
                                <p><?php echo htmlspecialchars($article['summary']); ?></p>
                                <div class="article-meta">
                                    <span class="author"><?php echo htmlspecialchars($article['author']); ?></span>
                                    <span class="date"><?php echo date('F j, Y', strtotime($article['date'])); ?></span>
                                    <span class="read-time"><?php echo $article['read_time'] ?? 5; ?> min read</span>
                                </div>
                                <a href="<?php echo htmlspecialchars($article['link']); ?>" target="_blank" class="read-more">Read More</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="pagination">
                <button class="prev-page">Previous</button>
                <span class="page-numbers">1 of 1</span>
                <button class="next-page">Next</button>
            </div>
            </div>
        </div>
    </div>

    <script>
        // Logout modal handlers (aligned with My Sessions/Resources)
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

        // Search and filter functionality
        document.getElementById('searchArticles').addEventListener('input', filterArticles);
        document.getElementById('categoryFilter').addEventListener('change', filterArticles);

        function filterArticles() {
            const searchTerm = document.getElementById('searchArticles').value.toLowerCase();
            const category = document.getElementById('categoryFilter').value;
            const articles = document.querySelectorAll('.article-card');

            articles.forEach(article => {
                const title = article.querySelector('h3').textContent.toLowerCase();
                const content = article.querySelector('p').textContent.toLowerCase();
                
                // Get the data-category and format it to match the dropdown value format
                const rawArticleCategory = article.dataset.category;
                let articleCategoryForComparison = rawArticleCategory;
                // If the category contains a hyphen, assume the dropdown value is the part before the hyphen
                if (rawArticleCategory.includes('-')) {
                    articleCategoryForComparison = rawArticleCategory.split('-')[0];
                }

                const matchesSearch = title.includes(searchTerm) || content.includes(searchTerm);
                // Compare the formatted article category with the dropdown value
                const matchesCategory = category === 'all' || articleCategoryForComparison === category;

                article.style.display = matchesSearch && matchesCategory ? 'block' : 'none';
            });
        }
    </script>

</body>
</html> 