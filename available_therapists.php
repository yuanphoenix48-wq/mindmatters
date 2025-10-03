<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'connect.php';

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'client';

// Fetch current user's profile picture and first name for sidebar
$profilePicture = 'images/profile/default_images/default_profile.png';
$firstName = $_SESSION['first_name'] ?? 'Student';
$stmtProfile = $conn->prepare("SELECT first_name, profile_picture FROM users WHERE id = ?");
if ($stmtProfile) {
    $stmtProfile->bind_param("i", $userId);
    $stmtProfile->execute();
    $resProfile = $stmtProfile->get_result();
    if ($rowP = $resProfile->fetch_assoc()) {
        $firstName = $rowP['first_name'] ?: $firstName;
        if (!empty($rowP['profile_picture'])) {
            $profilePicture = $rowP['profile_picture'];
        }
    }
    $stmtProfile->close();
}

// Only clients/students should access this page
if ($userRole !== 'client') {
    header('Location: dashboard.php');
    exit();
}

// Fetch therapists with computed average rating
$therapists = [];
$sql = "SELECT u.id, u.user_id, u.first_name, u.last_name, u.email, u.profile_picture,
               u.specialization, u.years_of_experience, u.languages_spoken, u.contact_number, u.license_number,
               COALESCE(AVG(sf.session_rating), 0) AS avg_rating,
               COUNT(sf.id) AS rating_count
        FROM users u
        LEFT JOIN student_feedback sf ON sf.therapist_id = u.id
        WHERE u.role = 'therapist'
        GROUP BY u.id, u.user_id, u.first_name, u.last_name, u.email, u.profile_picture,
                 u.specialization, u.years_of_experience, u.languages_spoken, u.contact_number, u.license_number
        ORDER BY avg_rating DESC, u.last_name ASC";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $therapists[] = $row;
    }
}

// Fetch availability for all listed therapists
$therapistIds = array_map(function($t) { return (int)$t['id']; }, $therapists);
$availabilityByTherapist = [];
if (!empty($therapistIds)) {
    $idsPlaceholders = implode(',', array_fill(0, count($therapistIds), '?'));
    $types = str_repeat('i', count($therapistIds));
    $stmt = $conn->prepare("SELECT therapist_id, day_of_week, start_time, end_time, is_available
                            FROM doctor_availability
                            WHERE therapist_id IN ($idsPlaceholders)
                            ORDER BY FIELD(day_of_week, 'monday','tuesday','wednesday','thursday','friday','saturday','sunday'), start_time");
    if ($stmt) {
        $stmt->bind_param($types, ...$therapistIds);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $tid = (int)$row['therapist_id'];
            if (!isset($availabilityByTherapist[$tid])) $availabilityByTherapist[$tid] = [];
            $availabilityByTherapist[$tid][] = $row;
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Therapists</title>
    <?php 
    $global_version = file_exists('styles/global.css') ? filemtime('styles/global.css') : time();
    $dashboard_version = file_exists('styles/dashboard.css') ? filemtime('styles/dashboard.css') : time();
    $notifications_version = file_exists('styles/notifications.css') ? filemtime('styles/notifications.css') : time();
    $mobile_css_version = file_exists('styles/mobile.css') ? filemtime('styles/mobile.css') : time();
    $available_therapists_css_version = file_exists('styles/available_therapists.css') ? filemtime('styles/available_therapists.css') : time();
    $notifications_js_version = file_exists('js/notifications.js') ? filemtime('js/notifications.js') : time();
    $mobile_js_version = file_exists('js/mobile.js') ? filemtime('js/mobile.js') : time();
    ?>
    <link rel="stylesheet" href="styles/global.css?v=<?php echo $global_version; ?>"/>
    <link rel="stylesheet" href="styles/dashboard.css?v=<?php echo $dashboard_version; ?>"/>
    <link rel="stylesheet" href="styles/notifications.css?v=<?php echo $notifications_version; ?>"/>
    <link rel="stylesheet" href="styles/mobile.css?v=<?php echo $mobile_css_version; ?>"/>
    <link rel="stylesheet" href="styles/available_therapists.css?v=<?php echo $available_therapists_css_version; ?>"/>
    <script src="js/notifications.js?v=<?php echo $notifications_js_version; ?>"></script>
    <script src="js/mobile.js?v=<?php echo $mobile_js_version; ?>"></script>
    <script>
        function formatTimeLabel(t) {
            // Expect HH:MM:SS or HH:MM
            try {
                const [h,m] = t.split(':');
                let hh = parseInt(h,10);
                const ampm = hh >= 12 ? 'PM' : 'AM';
                hh = hh % 12; if (hh === 0) hh = 12;
                return hh + ':' + m + ' ' + ampm;
            } catch(e) { return t; }
        }

    
    </script>
</head>
<body class="dbBody client-role">
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
    <div class="mobile-menu-overlay" id="mobileMenuOverlay" onclick="closeMobileMenu()"></div>
    <div class="dbContainer">
        <div class="dbSidebar">
            <div class="sidebarProfile">
                <img src="<?php echo htmlspecialchars($profilePicture); ?>" alt="Profile Picture" class="defaultPicture">
                <h1 class="profileName"><?php echo htmlspecialchars($firstName); ?></h1>
                <p class="userRole">Client</p>
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
            <div class="content-container">
                <div class="top-back"><button class="back-btn" onclick="window.location.href='dashboard.php'">Back</button></div>
                <div class="header-bar">
                    <h2>Available Therapists</h2>
                </div>

                <?php if (empty($therapists)): ?>
                    <div class="no-sessions">
                        <h2>No therapists found</h2>
                        <p>Please check back later.</p>
                    </div>
                <?php else: ?>
                    <div class="therapists-grid">
                    <?php foreach ($therapists as $t): ?>
                        <?php
                            $tid = (int)$t['id'];
                            $picture = $t['profile_picture'] ?: 'images/profile/default_images/default_profile.png';
                            $avg = number_format((float)$t['avg_rating'], 1);
                            $count = (int)$t['rating_count'];
                            $daysOrder = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
                            $grouped = [
                                'monday'=>[], 'tuesday'=>[], 'wednesday'=>[], 'thursday'=>[], 'friday'=>[], 'saturday'=>[], 'sunday'=>[]
                            ];
                            if (isset($availabilityByTherapist[$tid])) {
                                foreach ($availabilityByTherapist[$tid] as $row) {
                                    $grouped[$row['day_of_week']][] = $row;
                                }
                            }
                        ?>
                        <div class="therapist-card">
                            <div class="therapist-header">
                                <img class="therapist-avatar" src="<?php echo htmlspecialchars($picture); ?>" alt="Avatar">
                                <div>
                                    <h3 class="therapist-name">Dr. <?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></h3>
                                    <p class="therapist-email"><?php echo htmlspecialchars($t['email']); ?></p>
                                    <div class="rating">★ <?php echo $avg; ?> (<?php echo $count; ?>)</div>
                                </div>
                            </div>

                            <div class="therapist-info">
                                <?php if (!empty($t['specialization'])): ?>
                                <div><strong>Specialization:</strong> <?php echo htmlspecialchars($t['specialization']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($t['years_of_experience'])): ?>
                                <div><strong>Experience:</strong> <?php echo (int)$t['years_of_experience']; ?> years</div>
                                <?php endif; ?>
                                <?php if (!empty($t['languages_spoken'])): ?>
                                <div><strong>Languages:</strong> <?php echo htmlspecialchars($t['languages_spoken']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($t['contact_number'])): ?>
                                <div><strong>Contact:</strong> <?php echo htmlspecialchars($t['contact_number']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($t['license_number'])): ?>
                                <div><strong>License #:</strong> <?php echo htmlspecialchars($t['license_number']); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="schedule">
                                <?php foreach ($daysOrder as $day): ?>
                                    <div class="day">
                                        <span class="day-name"><?php echo ucfirst($day); ?></span>
                                        <span class="slots">
                                            <?php if (!empty($grouped[$day])): ?>
                                                <?php foreach ($grouped[$day] as $slot): ?>
                                                    <span class="slot">
                                                        <?php echo date('g:i A', strtotime($slot['start_time'])); ?> - <?php echo date('g:i A', strtotime($slot['end_time'])); ?>
                                                        <?php if (!(int)$slot['is_available']): ?> (Unavailable)<?php endif; ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="empty-day">No availability</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<script>
function confirmLogout() {
    showConfirm('Are you sure you want to logout?').then((ok)=>{ if (ok) window.location.href='logout.php'; });
}
</script>
</body>
</html>


