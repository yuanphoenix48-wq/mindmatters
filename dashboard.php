<?php
session_start();
date_default_timezone_set('Asia/Manila'); // Set to your local timezone
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get user's profile picture and role
require_once 'connect.php';

$userId = $_SESSION['user_id'];
$sql = "SELECT profile_picture, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$profilePicture = $user['profile_picture'] ?? ($user['gender'] === 'female' ? 'images/profile/default_images/female_gender.png' : 'images/profile/default_images/male_gender.png');
$userRole = $user['role'];

// Redirect admin users to admin dashboard
if ($userRole === 'admin') {
    header('Location: admin_dashboard.php');
    exit();
}

// Fetch all appointments for doctors
$appointments = [];
if ($userRole === 'therapist') {
    $conn2 = new mysqli($servername, $username, $password, $dbname);
    $sql2 = "SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) AS client_name, 
            u.user_id AS client_user_id, u.email AS client_email, s.meet_link
            FROM sessions s
            JOIN users u ON s.client_id = u.id
            WHERE s.therapist_id = ? AND s.status = 'scheduled'
            ORDER BY s.session_date ASC, s.session_time ASC";
    $stmt2 = $conn2->prepare($sql2);
    $stmt2->bind_param("i", $userId);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $appointments = $result2->fetch_all(MYSQLI_ASSOC);
    $stmt2->close();
    $conn2->close();
}

// Fetch next scheduled session for clients
$nextSession = null;
$todayMoodLog = null;
$moodTrends = []; // Initialize for all users

if ($userRole === 'client') {
    $now = date('Y-m-d H:i:s');
    $sqlNext = "SELECT s.*, CONCAT(d.first_name, ' ', d.last_name) as therapist_name
        FROM sessions s
        LEFT JOIN users d ON s.therapist_id = d.id
        WHERE s.client_id = ? AND s.status = 'scheduled' AND CONCAT(s.session_date, ' ', s.session_time) >= ?
        ORDER BY s.session_date ASC, s.session_time ASC
        LIMIT 1";
    $stmtNext = $conn->prepare($sqlNext);
    $stmtNext->bind_param("is", $userId, $now);
    $stmtNext->execute();
    $resultNext = $stmtNext->get_result();
    $nextSession = $resultNext->fetch_assoc();
    $stmtNext->close();
    
    // Pull today's mood from pre-session assessment if exists for today's session
    $today = date('Y-m-d');
    $sqlMood = "SELECT mha.assessment_data, COALESCE(mha.completed_at, CONCAT(s.session_date,' ', s.session_time)) as completed_at
                FROM mental_health_assessments mha
                JOIN sessions s ON s.id = mha.session_id
                WHERE mha.client_id = ? AND mha.assessment_type = 'pre_session' AND s.session_date = ?
                ORDER BY COALESCE(mha.completed_at, CONCAT(s.session_date,' ', s.session_time)) DESC LIMIT 1";
    $stmtMood = $conn->prepare($sqlMood);
    $stmtMood->bind_param("is", $userId, $today);
    $stmtMood->execute();
    $resMood = $stmtMood->get_result()->fetch_assoc();
    $stmtMood->close();
    $todayMoodLog = null;
    if ($resMood) {
        $data = json_decode($resMood['assessment_data'] ?? '{}', true) ?: [];
        $todayMoodLog = [
            'mood_emoji' => $data['mood_emoji'] ?? null,
            'mood_rating' => isset($data['mood_rating']) ? (int)$data['mood_rating'] : null,
            'stress_level' => isset($data['stress_level']) ? (int)$data['stress_level'] : null,
            'anxiety_level' => isset($data['anxiety_level']) ? (int)$data['anxiety_level'] : null,
            'sleep_hours' => isset($data['sleep_hours']) ? (float)$data['sleep_hours'] : null,
        ];
    }

    // Build mood trends from pre-session assessments (last 30 days)
    $moodTrends = [];
    $sqlTrend = "SELECT assessment_data, completed_at FROM mental_health_assessments WHERE client_id = ? AND assessment_type = 'pre_session' AND completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) ORDER BY completed_at ASC";
    $stmtTrend = $conn->prepare($sqlTrend);
    $stmtTrend->bind_param("i", $userId);
    $stmtTrend->execute();
    $resTrend = $stmtTrend->get_result();
    while ($row = $resTrend->fetch_assoc()) {
        $d = json_decode($row['assessment_data'] ?? '{}', true) ?: [];
        $moodTrends[] = [
            'log_date' => $row['completed_at'],
            'mood_rating' => isset($d['mood_rating']) ? (int)$d['mood_rating'] : null,
            'stress_level' => isset($d['stress_level']) ? (int)$d['stress_level'] : null
        ];
    }
    $stmtTrend->close();
    
}

// Fetch today's appointments for therapists
$today = date('Y-m-d');
error_log('Today: ' . $today);
error_log('User ID: ' . $userId);
$todayAppointments = [];
if ($userRole === 'therapist') {
    $conn2 = new mysqli($servername, $username, $password, $dbname);
    $sql2 = "SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) AS client_name, 
            u.user_id AS client_user_id, u.email AS client_email, s.meet_link
            FROM sessions s
            JOIN users u ON s.client_id = u.id
            WHERE s.therapist_id = ? AND s.session_date = ? AND s.status = 'scheduled'
            ORDER BY s.session_time ASC";
    $stmt2 = $conn2->prepare($sql2);
    $stmt2->bind_param("is", $userId, $today);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $todayAppointments = $result2->fetch_all(MYSQLI_ASSOC);
    error_log('Today Appointments: ' . print_r($todayAppointments, true));
    $stmt2->close();

    // Fetch recent clients (last 5 clients)
    $sql3 = "SELECT DISTINCT u.*, MAX(s.session_date) as last_session
            FROM users u
            JOIN sessions s ON u.id = s.client_id
            WHERE s.therapist_id = ?
            GROUP BY u.id
            ORDER BY last_session DESC
            LIMIT 5";
    $stmt3 = $conn2->prepare($sql3);
    $stmt3->bind_param("i", $userId);
    $stmt3->execute();
    $result3 = $stmt3->get_result();
    $recentPatients = $result3->fetch_all(MYSQLI_ASSOC);
    $stmt3->close();

    // Fetch schedule for the next 7 days
    $sql4 = "SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) AS client_name
            FROM sessions s
            JOIN users u ON s.client_id = u.id
            WHERE s.therapist_id = ? 
            AND s.session_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            AND s.status = 'scheduled'
            ORDER BY s.session_date ASC, s.session_time ASC";
    $stmt4 = $conn2->prepare($sql4);
    $stmt4->bind_param("i", $userId);
    $stmt4->execute();
    $result4 = $stmt4->get_result();
    $schedule = $result4->fetch_all(MYSQLI_ASSOC);
    $stmt4->close();

    // Fetch pending requests for this therapist only
    $sql5 = "SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) AS client_name, 
            u.user_id AS client_user_id, u.email AS client_email
            FROM sessions s
            JOIN users u ON s.client_id = u.id
            WHERE s.therapist_id = ? AND s.status = 'pending'
            ORDER BY s.session_date ASC, s.session_time ASC";
    $stmt5 = $conn2->prepare($sql5);
    $stmt5->bind_param("i", $userId);
    $stmt5->execute();
    $result5 = $stmt5->get_result();
    $pendingRequests = $result5->fetch_all(MYSQLI_ASSOC);
    $stmt5->close();

    $conn2->close();
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title>Mind Matters - Dashboard</title>
  <?php 
  // Cache busting - use file modification time for automatic versioning
  $css_version = filemtime('styles/mobile.css');
  $js_version = filemtime('js/mobile.js');
  $global_version = filemtime('styles/global.css');
  $dashboard_version = filemtime('styles/dashboard.css');
  $notifications_version = filemtime('styles/notifications.css');
  $notifications_js_version = filemtime('js/notifications.js');
  ?>
  <link rel="stylesheet" href="styles/global.css?v=<?php echo $global_version; ?>"/>
  <link rel="stylesheet" href="styles/dashboard.css?v=<?php echo $dashboard_version; ?>"/>
  <link rel="stylesheet" href="styles/notifications.css?v=<?php echo $notifications_version; ?>"/>
  <link rel="stylesheet" href="styles/mobile.css?v=<?php echo $css_version; ?>"/>
  <script src="js/notifications.js?v=<?php echo $notifications_js_version; ?>"></script>
  <script src="js/mobile.js?v=<?php echo $js_version; ?>"></script>
  <script src="js/session_manager.js?v=<?php echo $js_version; ?>"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  
</head>
<body class="dbBody <?php echo ($userRole === 'client') ? 'client-role' : 'therapist-role'; ?>">
  <!-- Mobile Header -->
  <div class="mobile-header">
    <div class="mobile-header-content" style="display:grid;grid-template-columns:44px 1fr auto;align-items:center;gap:.75rem;">
      <button class="mobile-menu-btn" id="mobileMenuBtn" onclick="toggleMobileMenu()">
        <span class="hamburger"></span>
        <span class="hamburger"></span>
        <span class="hamburger"></span>
      </button>
      <div class="mobile-logo" style="justify-self:center;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Mind Matters</div>
      <div class="mobile-user-info">
        <img src="<?php echo htmlspecialchars($profilePicture); ?>" alt="Profile Picture" class="mobile-user-avatar">
        <span class="mobile-user-name"><?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
      </div>
    </div>
  </div>


  <!-- Mobile Menu Overlay -->
  <div class="mobile-menu-overlay" id="mobileMenuOverlay" onclick="closeMobileMenu()"></div>

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

  <div class="dbContainer">
    <div class="dbSidebar">
      <div class="sidebarProfile">
        <img src="<?php echo htmlspecialchars($profilePicture); ?>" alt="Profile Picture" class="defaultPicture" id="profilePic">
        <h1 class="profileName"><?php echo htmlspecialchars($_SESSION['first_name']); ?></h1>
        <p class="userRole"><?php echo ucfirst($userRole); ?></p>
      </div>
      <ul class="sidebarNavList">
        <li class="sidebarNavItem"><a href="dashboard.php" class="sidebarNavLink active">Home</a></li>
        <?php if ($userRole === 'client'): ?>
          <li class="sidebarNavItem"><a href="my_session.php" class="sidebarNavLink">My Sessions</a></li>
          <li class="sidebarNavItem"><a href="analytics_dashboard.php" class="sidebarNavLink">Analytics</a></li>
          <li class="sidebarNavItem"><a href="resources.php" class="sidebarNavLink">Resources and Guide</a></li>
          <li class="sidebarNavItem"><a href="community_forum.php" class="sidebarNavLink">Community Forum</a></li>
          <li class="sidebarNavItem"><a href="therapy_support.php" class="sidebarNavLink">Therapy Support</a></li>
          <li class="sidebarNavItem"><a href="student_messages.php" class="sidebarNavLink">Messages</a></li>
        <?php else: ?>
          <li class="sidebarNavItem"><a href="appointments.php" class="sidebarNavLink">Appointments</a></li>
          <li class="sidebarNavItem"><a href="pending_requests.php" class="sidebarNavLink">Pending Requests</a></li>
          <li class="sidebarNavItem"><a href="patients.php" class="sidebarNavLink">My Clients</a></li>
          <li class="sidebarNavItem"><a href="patient_tracking.php" class="sidebarNavLink">Client Tracking</a></li>
          <li class="sidebarNavItem"><a href="therapy_support.php" class="sidebarNavLink">Therapy Support</a></li>
          <li class="sidebarNavItem"><a href="community_forum.php" class="sidebarNavLink">Community Forum</a></li>
          <li class="sidebarNavItem"><a href="doctor_schedule.php" class="sidebarNavLink">Schedule</a></li>
          <li class="sidebarNavItem"><a href="analytics_dashboard.php" class="sidebarNavLink">Analytics</a></li>
          <li class="sidebarNavItem"><a href="student_messages.php" class="sidebarNavLink">Messages</a></li>
        <?php endif; ?>
        <li class="sidebarNavItem"><a href="profile_settings.php" class="sidebarNavLink">Settings</a></li>
      </ul>
      <div class="sidebarFooter">
        <button type="button" class="logoutButton" onclick="openLogoutConfirm()">Logout</button>
      </div>
    </div>
    <div class="dbMainContent">
      <?php if ($userRole === 'client'): ?>
        <div class="client-dashboard-grid">
          <div class="client-dashboard-card">
            <div class="dashboard-section-header">Your Next Therapy Session</div>
            <?php if ($nextSession): ?>
              <div class="next-session-details">
                <strong>Date:</strong> <?php echo date('F j, Y', strtotime($nextSession['session_date'])); ?><br>
                <strong>Time:</strong> <?php echo date('g:i A', strtotime($nextSession['session_time'])); ?><br>
                <strong>Therapist:</strong> <?php echo htmlspecialchars($nextSession['therapist_name']); ?><br>
              </div>
            <?php else: ?>
              <div class="no-next-session">
                You have no upcoming scheduled therapy sessions.
              </div>
            <?php endif; ?>
          </div>
          <div class="client-dashboard-card">
            <div class="dashboard-section-header">Mood Trends</div>
            <div style="width:100%; max-width:100%;">
              <canvas id="dashMoodChart" height="200"></canvas>
            </div>
          </div>
          <div class="client-dashboard-card">
            <div class="dashboard-section-header">Available Therapist</div>
            <button type="button" class="seeSpecialistsButton" onclick="window.location.href='available_therapists.php'">View Therapists</button>
          </div>
          <div class="client-dashboard-card">
            <div class="dashboard-section-header">Quote for the Day</div>
            <div class="quote-content">
              <?php
              // Daily rotating inspirational/psychological quotes
              $quotes = [
                'The greatest discovery of my generation is that a human being can alter his life by altering his attitudes of mind. - William James',
                'You have been assigned this mountain to show others it can be moved. - Mel Robbins',
                'The mind is everything. What you think you become. - Buddha',
                'Your limitationâ€”it\'s only your imagination. - Unknown',
                'Push yourself, because no one else is going to do it for you. - Unknown',
                'Sometimes later becomes never. Do it now. - Unknown',
                'Great things never come from comfort zones. - Unknown',
                'Dream it. Wish it. Do it. - Unknown',
                'Success doesn\'t just find you. You have to go out and get it. - Unknown',
                'The harder you work for something, the greater you\'ll feel when you achieve it. - Unknown',
                'Dream bigger. Do bigger. - Unknown',
                'Don\'t stop when you\'re tired. Stop when you\'re done. - Unknown',
                'Wake up with determination. Go to bed with satisfaction. - Unknown',
                'Do something today that your future self will thank you for. - Unknown',
                'Little things make big days. - Unknown',
                'It\'s going to be hard, but hard does not mean impossible. - Unknown',
                'Don\'t wait for opportunity. Create it. - Unknown',
                'Sometimes we\'re tested not to show our weaknesses, but to discover our strengths. - Unknown',
                'The key to success is to focus on goals, not obstacles. - Unknown',
                'Dream it. Believe it. Build it. - Unknown',
                'What lies behind us and what lies before us are tiny matters compared to what lies within us. - Ralph Waldo Emerson',
                'The only way to do great work is to love what you do. - Steve Jobs',
                'If you can dream it, you can do it. - Walt Disney',
                'The future belongs to those who believe in the beauty of their dreams. - Eleanor Roosevelt',
                'It is during our darkest moments that we must focus to see the light. - Aristotle',
                'The way to get started is to quit talking and begin doing. - Walt Disney',
                'Don\'t be pushed around by the fears in your mind. Be led by the dreams in your heart. - Roy T. Bennett',
                'Believe you can and you\'re halfway there. - Theodore Roosevelt',
                'When you have a dream, you\'ve got to grab it and never let go. - Carol Burnett',
                'Nothing is impossible, the word itself says "I\'m possible"! - Audrey Hepburn',
                'What we achieve inwardly will change outer reality. - Plutarch'
              ];

              // Rotate quote by day of year so it changes daily
              $dayOfYear = (int)date('z');
              $quoteIndex = $dayOfYear % count($quotes);
              $selectedQuote = $quotes[$quoteIndex];

              // Split into text and author if possible
              $quoteParts = explode(' - ', $selectedQuote, 2);
              $quoteText = $quoteParts[0];
              $quoteAuthor = isset($quoteParts[1]) ? $quoteParts[1] : 'Unknown';
              ?>
              <div class="quote-text">"<?php echo htmlspecialchars($quoteText); ?>"</div>
              <div class="quote-author">- <?php echo htmlspecialchars($quoteAuthor); ?></div>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="therapist-dashboard-main">
          <div class="nav-buttons">
            <button class="nav-button active" onclick="showSection('today-appointments')">Today's Appointments</button>
            <button class="nav-button" onclick="showSection('recent-clients')">Recent Clients</button>
            <button class="nav-button" onclick="showSection('schedule')">Your Schedule</button>
          </div>
          <!-- Today's Appointments Section -->
          <div id="today-appointments" class="content-section active">
            <h2>Today's Appointments</h2>
            <?php if (empty($todayAppointments)): ?>
              <div class="no-appointments">
                <h3>No appointments for today</h3>
                <p>You don't have any scheduled appointments for today.</p>
              </div>
            <?php else: ?>
              <div class="today-appointments-grid">
                  <?php foreach ($todayAppointments as $appointment): ?>
                    <div class="dashboard-card">
                      <div class="appointment-time">
                        <?php echo date('g:i A', strtotime($appointment['session_time'])); ?>
                      </div>
                      <div class="appointment-details">
                        <h3><?php echo htmlspecialchars($appointment['client_name']); ?></h3>
                        <p>Client ID: <?php echo htmlspecialchars($appointment['client_user_id']); ?></p>
                        <p>Email: <?php echo htmlspecialchars($appointment['client_email']); ?></p>
                        <?php if ($userRole === 'therapist'): ?>
                          <div class="meet-link-section" style="display:none;"></div>
                        <?php endif; ?>
                      </div>
                      <div class="appointment-actions">
                        <button class="join-button" onclick="joinSession(<?php echo $appointment['id']; ?>, '<?php echo htmlspecialchars($appointment['meet_link'] ?? ''); ?>')">
                          Join Session
                        </button>
                      </div>
                    </div>
                  <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Recent Patients Section -->
          <div id="recent-clients" class="content-section">
            <h2>Recent Clients</h2>
            <?php if (empty($recentPatients)): ?>
              <div class="no-clients">
                <h3>No recent clients</h3>
                <p>You haven't had any clients yet.</p>
              </div>
            <?php else: ?>
              <div class="recent-clients-grid">
                  <?php foreach ($recentPatients as $patient): ?>
                    <div class="dashboard-card">
                      <div class="client-info">
                        <h3><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h3>
                        <p>Client ID: <?php echo htmlspecialchars($patient['user_id']); ?></p>
                        <p>Last Session: <?php echo date('M j, Y', strtotime($patient['last_session'])); ?></p>
                        <p>Email: <?php echo htmlspecialchars($patient['email']); ?></p>
                      </div>
                    
                    </div>
                  <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Schedule Section -->
          <div id="schedule" class="content-section">
            <h2>Your Schedule</h2>
            <?php if (empty($schedule)): ?>
              <div class="no-schedule">
                <h3>No upcoming appointments</h3>
                <p>You don't have any scheduled appointments for the next 7 days.</p>
              </div>
            <?php else: ?>
              <div class="schedule-grid">
                <?php foreach ($schedule as $appointment): ?>
                  <div class="schedule-card">
                    <div class="schedule-date">
                      <?php echo date('F j, Y', strtotime($appointment['session_date'])); ?>
                    </div>
                    <div class="schedule-time">
                      <?php echo date('g:i A', strtotime($appointment['session_time'])); ?>
                    </div>
                    <div class="schedule-details">
                      <h3><?php echo htmlspecialchars($appointment['client_name']); ?></h3>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Pending Requests Section -->
          <div id="pending-requests" class="content-section">
            <h2>Pending Requests</h2>
            <?php if (empty($pendingRequests)): ?>
              <div class="no-requests">
                <h3>No pending requests</h3>
                <p>There are no pending teleconsultation requests at the moment.</p>
              </div>
            <?php else: ?>
              <div class="requests-grid">
                <?php foreach ($pendingRequests as $request): ?>
                  <div class="request-card">
                    <div class="request-header">
                      <div class="request-date">
                        <?php echo date('F j, Y', strtotime($request['session_date'])); ?>
                        at <?php echo date('g:i A', strtotime($request['session_time'])); ?>
                      </div>
                    </div>
                    <div class="request-details">
                      <div class="detail-item">
                        <span class="detail-label">Client</span>
                        <span class="detail-value"><?php echo htmlspecialchars($request['client_name']); ?></span>
                      </div>
                      <div class="detail-item">
                        <span class="detail-label">Client ID</span>
                        <span class="detail-value"><?php echo htmlspecialchars($request['client_user_id']); ?></span>
                      </div>
                      <div class="detail-item">
                        <span class="detail-label">Contact</span>
                        <span class="detail-value">Email: <?php echo htmlspecialchars($request['client_email']); ?></span>
                      </div>
                      <?php if ($request['notes']): ?>
                        <div class="detail-item">
                          <span class="detail-label">Notes</span>
                          <span class="detail-value"><?php echo nl2br(htmlspecialchars($request['notes'])); ?></span>
                        </div>
                      <?php endif; ?>
                    </div>
                    <div class="request-actions">
                      <button class="accept-button" onclick="acceptRequest(<?php echo $request['id']; ?>)">
                        Accept Request
                      </button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

<style>
  .nav-buttons {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
    padding: 0 20px;
  }

  .nav-button {
    padding: 12px 24px;
    border: none;
    border-radius: 25px;
    background: #f0f0f0;
    color: #333;
    font-size: 1.1em;
    cursor: pointer;
    transition: all 0.3s ease;
  }

  .nav-button:hover {
    background: #e0e0e0;
    transform: translateY(-2px);
  }

  .nav-button.active {
    background: #1D5D9B;
    color: white;
  }

  .content-section {
    display: none;
    padding: 20px;
  }

  .content-section.active {
    display: block;
  }

  .appointments-grid,
  .clients-grid,
  .schedule-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
  }

  .appointment-card,
  .client-card,
  .schedule-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    transition: transform 0.2s ease;
  }

  .appointment-card:hover,
  .client-card:hover,
  .schedule-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
  }

  .appointment-time,
  .schedule-date {
    font-size: 1.2em;
    font-weight: bold;
    color: #1D5D9B;
    margin-bottom: 10px;
  }

  .appointment-details h3,
  .client-info h3,
  .schedule-details h3 {
    margin: 0 0 10px 0;
    color: #333;
  }

  .appointment-details p,
  .client-info p {
    margin: 5px 0;
    color: #666;
  }

  .join-button,
  .view-history-button {
    background: #1D5D9B;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
  }

  .join-button:hover,
  .view-history-button:hover {
    background: #14487a;
    transform: translateY(-1px);
  }

  .no-appointments,
  .no-clients,
  .no-schedule,
  .no-messages {
    text-align: center;
    padding: 40px 20px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
  }

  .no-appointments h3,
  .no-clients h3,
  .no-schedule h3,
  .no-messages h3 {
    color: #1D5D9B;
    margin-bottom: 10px;
  }

  .no-appointments p,
  .no-clients p,
  .no-schedule p,
  .no-messages p {
    color: #666;
  }

  .requests-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
  }

  .request-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    transition: transform 0.2s ease;
  }

  .request-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
  }

  .request-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
  }

  .request-date {
    font-size: 1.2em;
    font-weight: bold;
    color: #1D5D9B;
  }

  .request-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
  }

  .accept-button {
    background: #1D5D9B;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
    font-size: 0.95em;
  }

  .accept-button:hover {
    background: #14487a;
    transform: translateY(-1px);
  }

  .no-requests {
    text-align: center;
    padding: 40px 20px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
  }

  .no-requests h3 {
    color: #1D5D9B;
    margin-bottom: 10px;
  }

  .no-requests p {
    color: #666;
  }


  .meet-link-section {
    margin-top: 10px;
    display: flex;
    gap: 10px;
    align-items: center;
  }

  .meet-link-input {
    flex: 1;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
  }

  .save-link-button {
    padding: 8px 16px;
    background-color: #1D5D9B;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
  }

  .save-link-button:hover {
    background-color: #14487a;
  }

  /* Mobile header positioning - now centralized in styles/mobile.css */

  /* Logout Confirm Modal */
  #logoutConfirmModal {
    display: none;
    position: fixed;
    z-index: 3000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    align-items: center;
    justify-content: center;
  }
  #logoutConfirmModal.show { display: flex; }
  #logoutConfirmModal .modal-content {
    width: 90%;
    max-width: 460px;
    border-radius: 12px;
    background: #ffffff;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
  }
  #logoutConfirmModal .modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid rgba(0,0,0,0.06);
    background: linear-gradient(135deg, #1D5D9B, #14487a);
    color: #ffffff;
  }
  #logoutConfirmModal .modal-header h3 { color: #ffffff !important; font-size: 1rem; line-height: 1.2; margin: 0; }
  #logoutConfirmModal .modal-body { padding: 20px; color: #333; }
  #logoutConfirmModal .modal-actions { display: flex; gap: 10px; justify-content: flex-end; padding: 0 20px 20px 20px; }
  #logoutConfirmModal .modal-actions .cancel-btn,
  #logoutConfirmModal .modal-actions .submit-btn {
    appearance: none;
    -webkit-appearance: none;
    border: 0;
    border-radius: 10px;
    padding: 0.65rem 1.1rem;
    font-weight: 700;
    font-size: 0.95rem;
    cursor: pointer;
    transition: transform .12s ease, box-shadow .2s ease, background .2s ease, color .2s ease, opacity .2s ease;
    outline: none;
    min-width: 96px;
  }
  #logoutConfirmModal .modal-actions .cancel-btn {
    background: #f1f3f5;
    color: #1D3557;
    box-shadow: 0 1px 2px rgba(0,0,0,0.06) inset;
  }
  #logoutConfirmModal .modal-actions .cancel-btn:hover { background: #e9ecef; }
  #logoutConfirmModal .modal-actions .cancel-btn:active { transform: translateY(1px); }
  #logoutConfirmModal .modal-actions .cancel-btn:focus-visible { box-shadow: 0 0 0 3px rgba(29,93,155,0.25); }

  #logoutConfirmModal .modal-actions .submit-btn {
    background: linear-gradient(135deg, #1D5D9B, #14487a);
    color: #ffffff;
    box-shadow: 0 6px 16px rgba(29,93,155,0.25);
  }
  #logoutConfirmModal .modal-actions .submit-btn:hover { background: linear-gradient(135deg, #14487a, #0d3a5f); }
  #logoutConfirmModal .modal-actions .submit-btn:active { transform: translateY(1px); }
  #logoutConfirmModal .modal-actions .submit-btn:focus-visible { box-shadow: 0 0 0 3px rgba(29,93,155,0.35); }
  @media (max-width: 480px) {
    #logoutConfirmModal .modal-actions .cancel-btn,
    #logoutConfirmModal .modal-actions .submit-btn { min-width: 0; padding: 0.7rem 1rem; font-size: 1rem; }
  }
</style>

<script>

  function showSection(sectionId) {
    // Hide all sections
    document.querySelectorAll('.content-section').forEach(section => {
      section.classList.remove('active');
    });
    
    // Show selected section
    document.getElementById(sectionId).classList.add('active');
    
    // Update active button
    document.querySelectorAll('.nav-button').forEach(button => {
      button.classList.remove('active');
    });
    event.target.classList.add('active');
  }

  function viewClientHistory(clientId) {
    window.location.href = `client_history.php?client_id=${clientId}`;
  }

  function saveMeetLink(sessionId) {
    const input = document.querySelector(`input[data-session-id="${sessionId}"]`);
    const meetLink = input.value.trim();
    
    if (!meetLink) { 
      if (typeof showToast === 'function') {
        showToast('Please enter a Google Meet link', 'warning'); 
      } else {
        alert('Please enter a Google Meet link');
      }
      return; 
    }
    
    if (!meetLink.includes('meet.google.com')) { 
      if (typeof showToast === 'function') {
        showToast('Please enter a valid Google Meet link', 'warning'); 
      } else {
        alert('Please enter a valid Google Meet link');
      }
      return; 
    }
    
    const formData = new FormData();
    formData.append('session_id', sessionId);
    formData.append('meet_link', meetLink);
    
    fetch('update_meet_link.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => { 
      if (data.success) { 
        if (typeof showToast === 'function') {
          showToast('Meet link saved successfully', 'success'); 
        } else {
          alert('Meet link saved successfully');
        }
      } else { 
        if (typeof showToast === 'function') {
          showToast('Failed to save meet link: ' + (data.error || 'Unknown error'), 'error'); 
        } else {
          alert('Failed to save meet link: ' + (data.error || 'Unknown error'));
        }
      } 
    })
    .catch(error => { 
      console.error('Error:', error); 
      if (typeof showToast === 'function') {
        showToast('An error occurred while saving the meet link', 'error'); 
      } else {
        alert('An error occurred while saving the meet link');
      }
    });
  }

  function joinSession(sessionId, meetLink) {
    if (!meetLink) { 
      if (typeof showToast === 'function') {
        showToast('No Google Meet link has been set for this session. Please contact the doctor.', 'warning'); 
      } else {
        alert('No Google Meet link has been set for this session. Please contact the doctor.');
      }
      return; 
    }
    window.open(meetLink, '_blank');
  }

  function acceptRequest(sessionId) {
    if (typeof showConfirm === 'function') {
      showConfirm('Are you sure you want to accept this teleconsultation request?', 'Accept Request', 'success').then((ok)=>{ if(!ok) return;
        fetch('accept_session.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ session_id: sessionId })
        })
        .then(response => {
          if (!response.ok) {
            throw new Error('Network response was not ok');
          }
          return response.json();
        })
        .then(data => { 
          if (data.success) { 
            if (typeof showToast === 'function') {
              showToast('Request accepted successfully', 'success'); 
            } else {
              alert('Request accepted successfully');
            }
            location.reload(); 
          } else { 
            if (typeof showToast === 'function') {
              showToast('Failed to accept request: ' + (data.error || 'Unknown error'), 'error'); 
            } else {
              alert('Failed to accept request: ' + (data.error || 'Unknown error'));
            }
          } 
        })
        .catch(error => { 
          console.error('Error:', error); 
          if (typeof showToast === 'function') {
            showToast('An error occurred while accepting the request. Please try again.', 'error'); 
          } else {
            alert('An error occurred while accepting the request. Please try again.');
          }
        });
      });
    } else {
      if (confirm('Are you sure you want to accept this teleconsultation request?')) {
        fetch('accept_session.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ session_id: sessionId })
        })
        .then(response => {
          if (!response.ok) {
            throw new Error('Network response was not ok');
          }
          return response.json();
        })
        .then(data => { 
          if (data.success) { 
            alert('Request accepted successfully');
            location.reload(); 
          } else { 
            alert('Failed to accept request: ' + (data.error || 'Unknown error'));
          } 
        })
        .catch(error => { 
          console.error('Error:', error); 
          alert('An error occurred while accepting the request. Please try again.');
        });
      }
    }
  }

  function openLogoutConfirm(){
    const modal = document.getElementById('logoutConfirmModal');
    const okBtn = document.getElementById('logoutConfirmOk');
    if (!modal || !okBtn) return;
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    // Hide sidebar/nav if visible (especially on mobile)
    try { if (typeof closeMobileMenu === 'function') closeMobileMenu(); } catch(e) {}
    document.body.classList.add('modal-open');
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
    function onBackdrop(e){ if(e.target === modal){ onCancel(); } }
    function onEsc(e){ if(e.key === 'Escape'){ onCancel(); } }
    okBtn.addEventListener('click', onOk);
    modal.addEventListener('click', onBackdrop);
    document.addEventListener('keydown', onEsc);
    window.closeLogoutConfirm = onCancel;
  }

  function closeLogoutConfirm(){ 
    if (typeof window.closeLogoutConfirm === 'function') window.closeLogoutConfirm(); 
  }

  // Mobile menu functions
  function toggleMobileMenu() {
    console.log('Toggle mobile menu clicked');
    const sidebar = document.querySelector('.dbSidebar');
    const overlay = document.getElementById('mobileMenuOverlay');
    const body = document.body;
    
    if (sidebar && overlay) {
      sidebar.classList.toggle('mobile-open');
      overlay.classList.toggle('active');
      body.classList.toggle('mobile-menu-open');
      console.log('Mobile menu toggled');
    } else {
      console.error('Sidebar or overlay not found');
    }
  }

  function closeMobileMenu() {
    console.log('Close mobile menu called');
    const sidebar = document.querySelector('.dbSidebar');
    const overlay = document.getElementById('mobileMenuOverlay');
    const body = document.body;
    
    if (sidebar && overlay) {
      sidebar.classList.remove('mobile-open');
      overlay.classList.remove('active');
      body.classList.remove('mobile-menu-open');
      console.log('Mobile menu closed');
    }
  }


  // Add this to handle sidebar navigation
  document.addEventListener('DOMContentLoaded', function() {
    // Get the current page from the URL
    const currentPage = window.location.pathname.split('/').pop();
    
    // If we're on the dashboard and there's a section parameter, show that section
    if (currentPage === 'dashboard.php') {
      const urlParams = new URLSearchParams(window.location.search);
      const section = urlParams.get('section');
      if (section) {
        showSection(section);
      }
    }
  });

  // Dashboard Mood Trends Chart
  (function(){
    const ctx = document.getElementById('dashMoodChart');
    if (!ctx) return;
    const data = <?php echo json_encode($moodTrends); ?>;
    const labels = data.map(d => new Date(d.log_date).toLocaleDateString());
    const mood = data.map(d => d.mood_rating !== null ? Number(d.mood_rating) : null);
    const stress = data.map(d => d.stress_level !== null ? Number(d.stress_level) : null);
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Mood',
          data: mood,
          borderColor: '#1D5D9B',
          backgroundColor: 'rgba(29,93,155,0.1)',
          tension: 0.35,
          spanGaps: true
        }, {
          label: 'Stress',
          data: stress,
          borderColor: '#f44336',
          backgroundColor: 'rgba(244,67,54,0.1)',
          tension: 0.35,
          spanGaps: true
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: true, max: 10 } },
        plugins: { legend: { display: true } }
      }
    });
  })();
</script>
</body>
</html>
