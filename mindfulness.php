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
    <title>Mindfulness & Wellness - Mind Matters</title>
    <link rel="stylesheet" href="styles/global.css">
    <link rel="stylesheet" href="styles/dashboard.css">
    <link rel="stylesheet" href="styles/notifications.css">
    <link rel="stylesheet" href="styles/articles.css">
    <script src="js/notifications.js"></script>
</head>
<body class="dbBody">
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
                <button type="button" class="logoutButton" onclick="confirmLogout()">Logout</button>
            </div>
        </div>

        <div class="dbMainContent">
            <div class="mindfulness-container">
                <a href="resources.php" class="back-button">← Back to Resources</a>
            <header class="articles-header">
                <h1>Mindfulness & Wellness</h1>
                <p>Discover practices and exercises to enhance your mental well-being and academic performance</p>
            </header>

            <div class="mindfulness-grid">
                <!-- Quick Mindfulness -->
                <section class="mindfulness-section">
                    <h2>Quick Mindfulness Exercises</h2>
                    <div class="exercise-cards">
                        <div class="exercise-card">
                            <div class="exercise-icon">⏱️</div>
                            <h3>5-Minute Breathing</h3>
                            <p>Short breathing exercise to center yourself before class or study sessions.</p>
                            <div class="exercise-steps">
                                <span>1. Find a quiet space</span>
                                <span>2. Sit comfortably</span>
                                <span>3. Focus on your breath</span>
                            </div>
                            <a href="#" class="exercise-button">Start Exercise</a>
                        </div>

                        <div class="exercise-card">
                            <div class="exercise-icon">👁️</div>
                            <h3>Body Scan</h3>
                            <p>Quick body awareness exercise to release tension and stress.</p>
                            <div class="exercise-steps">
                                <span>1. Close your eyes</span>
                                <span>2. Scan from head to toe</span>
                                <span>3. Release tension</span>
                            </div>
                            <a href="#" class="exercise-button">Start Exercise</a>
                        </div>
                    </div>
                </section>

                <!-- Guided Practices -->
                <section class="mindfulness-section">
                    <h2>Guided Practices</h2>
                    <div class="practice-cards">
                        <div class="practice-card">
                            <div class="practice-thumbnail">
                                <img src="images/mindfulness/meditation.jpg" alt="Guided Meditation">
                                <span class="duration">15 min</span>
                            </div>
                            <div class="practice-content">
                                <h3>Guided Meditation for Students</h3>
                                <p>Specially designed meditation sessions for academic focus and stress relief.</p>
                                <div class="practice-tags">
                                    <span>Focus</span>
                                    <span>Stress Relief</span>
                                    <span>Academic</span>
                                </div>
                                <a href="#" class="practice-button">Start Practice</a>
                            </div>
                        </div>

                        <div class="practice-card">
                            <div class="practice-thumbnail">
                                <img src="images/mindfulness/yoga.jpg" alt="Yoga for Students">
                                <span class="duration">20 min</span>
                            </div>
                            <div class="practice-content">
                                <h3>Yoga for Students</h3>
                                <p>Gentle yoga sequences to improve focus and reduce study-related tension.</p>
                                <div class="practice-tags">
                                    <span>Physical</span>
                                    <span>Mental</span>
                                    <span>Flexibility</span>
                                </div>
                                <a href="#" class="practice-button">Start Practice</a>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Wellness Tips -->
                <section class="mindfulness-section">
                    <h2>Daily Wellness Tips</h2>
                    <div class="tips-container">
                        <div class="tip-card">
                            <div class="tip-icon">💧</div>
                            <h3>Stay Hydrated</h3>
                            <p>Keep a water bottle with you and aim to drink 8 glasses of water daily.</p>
                        </div>

                        <div class="tip-card">
                            <div class="tip-icon">🌙</div>
                            <h3>Sleep Schedule</h3>
                            <p>Maintain a consistent sleep schedule for better academic performance.</p>
                        </div>

                        <div class="tip-card">
                            <div class="tip-icon">🥗</div>
                            <h3>Healthy Eating</h3>
                            <p>Fuel your brain with nutritious meals and snacks throughout the day.</p>
                        </div>

                        <div class="tip-card">
                            <div class="tip-icon">🚶</div>
                            <h3>Take Breaks</h3>
                            <p>Incorporate short breaks during study sessions to maintain focus.</p>
                        </div>
                    </div>
                </section>

                <!-- Progress Tracker -->
                <section class="mindfulness-section">
                    <h2>Wellness Progress Tracker</h2>
                    <div class="tracker-card">
                        <div class="tracker-header">
                            <h3>Your Wellness Journey</h3>
                            <p>Track your mindfulness and wellness practices</p>
                        </div>
                        <div class="tracker-stats">
                            <div class="stat">
                                <span class="stat-number">0</span>
                                <span class="stat-label">Days Streak</span>
                            </div>
                            <div class="stat">
                                <span class="stat-number">0</span>
                                <span class="stat-label">Minutes Practiced</span>
                            </div>
                            <div class="stat">
                                <span class="stat-number">0</span>
                                <span class="stat-label">Exercises Completed</span>
                            </div>
                        </div>
                        <a href="#" class="tracker-button">Start Tracking</a>
                    </div>
                </section>
            </div>
            </div>
        </div>
    </div>

    <script>
        function confirmLogout() {
            showConfirm('Are you sure you want to logout?').then((ok) => {
                if (ok) {
                    window.location.href = 'logout.php';
                }
            });
        }
    </script>

    <style>
        .mindfulness-container {
            padding: 30px;
        }
        .mindfulness-grid {
            display: flex;
            flex-direction: column;
            gap: 3rem;
        }

        .mindfulness-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .mindfulness-section h2 {
            color: #2c3e50;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        /* Exercise Cards */
        .exercise-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .exercise-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
        }

        .exercise-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .exercise-card h3 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .exercise-steps {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin: 1rem 0;
            text-align: left;
        }

        .exercise-steps span {
            color: #666;
            font-size: 0.9rem;
        }

        /* Practice Cards */
        .practice-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .practice-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .practice-thumbnail {
            position: relative;
            height: 200px;
        }

        .practice-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .duration {
            position: absolute;
            bottom: 1rem;
            right: 1rem;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.9rem;
        }

        .practice-content {
            padding: 1.5rem;
        }

        .practice-tags {
            display: flex;
            gap: 0.5rem;
            margin: 1rem 0;
            flex-wrap: wrap;
        }

        .practice-tags span {
            background: #f0f2f5;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.85rem;
            color: #666;
        }

        /* Tips Container */
        .tips-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .tip-card {
            text-align: center;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .tip-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        /* Tracker Card */
        .tracker-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
        }

        .tracker-stats {
            display: flex;
            justify-content: space-around;
            margin: 2rem 0;
        }

        .stat {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .stat-number {
            font-size: 2rem;
            color: #3498db;
            font-weight: bold;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        /* Buttons */
        .exercise-button,
        .practice-button,
        .tracker-button {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .exercise-button:hover,
        .practice-button:hover,
        .tracker-button:hover {
            background-color: #2980b9;
        }

        @media (max-width: 768px) {
            .mindfulness-section {
                padding: 1.5rem;
            }

            .tracker-stats {
                flex-direction: column;
                gap: 1.5rem;
            }
        }

        /* Back button styles (blue, consistent across pages) */
        .back-button { display:inline-flex; align-items:center; padding:0.8rem 1.5rem; background-color:#1D5D9B; color:#fff; text-decoration:none; border-radius:6px; font-weight:600; transition:background-color 0.2s ease; margin-bottom:1.5rem; border:none; }
        .back-button:hover { background-color:#14487a; }
    </style>
</body>
</html> 