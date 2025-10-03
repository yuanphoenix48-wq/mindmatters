<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get user's role
require_once 'connect.php';

$userId = $_SESSION['user_id'];
$sql = "SELECT role, profile_picture FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$userRole = $user['role'];
$profilePicture = $user['profile_picture'] ?? ($user['gender'] === 'female' ? 'images/profile/default_images/female_gender.png' : 'images/profile/default_images/male_gender.png');

// Redirect if not a therapist
if ($userRole !== 'therapist') {
    header('Location: dashboard.php');
    exit();
}

// Fetch all appointments
$sql = "SELECT s.*, 
        CONCAT(u.first_name, ' ', u.last_name) AS client_name, 
        u.user_id AS client_user_id, 
        u.email AS client_email, 
        s.meet_link,
        et.first_name AS endorsed_first, et.last_name AS endorsed_last,
        (s.therapist_id = ?) AS is_primary,
        CONCAT(pt.first_name, ' ', pt.last_name) AS primary_therapist_name,
        (
          SELECT GROUP_CONCAT(
            CONCAT(
              'Dr. ', cu.first_name, ' ', cu.last_name,
              ' (',
              CASE 
                WHEN cst.status = 'accepted' THEN 'Accepted'
                WHEN cst.status = 'invited' THEN 'Invited'
                WHEN cst.status = 'declined' THEN 'Declined'
                ELSE cst.status
              END,
              ')'
            )
            SEPARATOR ', '
          )
          FROM session_therapists cst
          JOIN users cu ON cu.id = cst.therapist_id
          WHERE cst.session_id = s.id AND cst.status IN ('invited','accepted','declined')
        ) AS co_therapists_names,
        (
          EXISTS(SELECT 1 FROM doctor_feedback df WHERE df.session_id = s.id AND df.therapist_id = ?)
          OR EXISTS(SELECT 1 FROM doctor_feedback tf WHERE tf.session_id = s.id AND tf.therapist_id = ?)
        ) AS has_doctor_feedback,
        s.cancellation_reason
        FROM sessions s
        JOIN users u ON s.client_id = u.id
        JOIN users pt ON pt.id = s.therapist_id
        LEFT JOIN users et ON et.id = s.endorsed_therapist_id
        WHERE (s.therapist_id = ? OR EXISTS(SELECT 1 FROM session_therapists st WHERE st.session_id = s.id AND st.therapist_id = ? AND st.status = 'accepted'))
        ORDER BY s.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiiii", $userId, $userId, $userId, $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$appointments = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - Mind Matters</title>
    <link rel="stylesheet" href="styles/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="styles/dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="styles/appointments.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="styles/notifications.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="styles/mobile.css?v=<?php echo time(); ?>">
	<script src="js/mobile.js?v=<?php echo time(); ?>"></script>
    <style>
        .appointments-container {
            padding: 20px;
        }
        .appointment-filters {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        
        #apptStatusTabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        #apptStatusTabs button {
            padding: 10px 20px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            background: #f8f9fa;
            color: #495057;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        #apptStatusTabs button.active,
        #apptStatusTabs button:hover {
            background: #1D5D9B;
            color: white;
            border-color: #1D5D9B;
        }
        .appointment-filters select,
        .appointment-filters input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .appointment-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .appointment-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .appointment-status.scheduled { background: #1976d2; color: white; }
        .appointment-status.completed { background: #2e7d32; color: white; }
        .appointment-status.cancelled { background: #c62828; color: white; }
        .appointment-details {
            display: grid;
            grid-template-columns: 1fr 1fr 1.6fr auto;
            gap: 10px;
            margin-bottom: 15px;
            align-items: start;
        }
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        .detail-item.email-item .detail-value {
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        .detail-item.co-item {
            justify-self: end;
            text-align: right;
            max-width: 260px;
        }
        .detail-item.co-item .detail-value {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 240px;
            display: inline-block;
        }
        .detail-label {
            font-weight: bold;
            color: #666;
            font-size: 0.9em;
        }
        .detail-value {
            margin-top: 4px;
        }
        .appointment-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .action-button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        .join-button { background: #673ab7; color: white; }
        .complete-button { background: #4caf50; color: white; }
        .notes-button { background: #ff9800; color: white; }
        .action-button:hover { opacity: 0.9; }
        
        /* Additional action button colors */
        .action-button[onclick*="viewCancellationReason"] { 
            background: #6c757d; 
            color: white; 
        }
        .action-button[onclick*="viewCancellationReason"]:before {
            content: '❓ ';
        }
        .action-button[onclick*="doctor_notes.php"] { 
            background: #17a2b8; 
            color: white; 
        }
        .action-button[onclick*="doctor_notes.php"]:before {
            content: '📝 ';
        }
        .action-button[onclick*="patient_tracking.php"] { 
            background: #6f42c1; 
            color: white; 
        }
        .action-button[onclick*="patient_tracking.php"]:before {
            content: '📊 ';
        }
        .action-button[onclick*="session_feedback.php"] { 
            background: #28a745; 
            color: white; 
        }
        .action-button[onclick*="session_feedback.php"]:before {
            content: '💬 ';
        }
        .action-button[onclick*="openManageModal"] { 
            background: #fd7e14; 
            color: white; 
        }
        .action-button[onclick*="openManageModal"]:before {
            content: '⚙️ ';
        }
        /* Colored actions for propose reschedule and confirm endorsement */
        .action-button[onclick*="submitRescheduleTherapist"] {
            background: #1D5D9B;
            color: #ffffff;
        }
        .action-button[onclick*="submitRescheduleTherapist"]:hover {
            background: #14487a;
        }
        .action-button[onclick*="submitEndorseUnified"] {
            background: #6f42c1;
            color: #ffffff;
        }
        .action-button[onclick*="submitEndorseUnified"]:hover {
            background: #5a32a3;
        }
        .action-button[onclick*="openInviteModal"] { 
            background: #20c997; 
            color: white; 
        }
        .action-button[onclick*="openInviteModal"]:before {
            content: '👥 ';
        }
        /* Add color for Invite button inside Add Co-Therapist modal */
        .action-button[onclick*="submitInvite"] {
            background: #1D5D9B;
            color: #ffffff;
        }
        .action-button[onclick*="submitInvite"]:hover {
            background: #14487a;
        }
        .meet-link-section {
            grid-column: 1 / -1;
        }
        .meet-link-input-group {
            display: flex;
            gap: 10px;
            margin-top: 4px;
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
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }
        
        .modal.show {
            display: flex;
        }
        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 20px;
            border: 1px solid #888;
            width: 50%;
            max-width: 600px;
            border-radius: 8px;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
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
        #notesContent {
            margin-top: 20px;
            white-space: pre-wrap;
            line-height: 1.5;
        }

        /* Mobile Enhancements for Appointments */
        @media (max-width: 768px) {
            /* Ensure content clears fixed header */
            .dbMainContent {
                margin-top: 90px !important;
            }

            /* Force white text for View Notes on mobile */
            .action-button.notes-button { color: #fff !important; }

            .appointments-container {
                padding: 0.5rem;
            }
            
            .appointment-filters {
                flex-direction: column;
                gap: 0.75rem;
                margin-bottom: 1rem;
            }
            
            .appointment-filters input {
                width: 100%;
                padding: 0.75rem;
                font-size: 16px; /* Prevents zoom on iOS */
                border-radius: 8px;
                border: 2px solid #e9ecef;
                transition: border-color 0.3s ease;
            }
            
            .appointment-filters input:focus {
                border-color: var(--primary-color);
                outline: none;
                box-shadow: 0 0 0 3px rgba(29, 93, 155, 0.1);
            }
            
            #apptStatusTabs {
                display: flex;
                background: var(--white);
                border-radius: 12px;
                padding: 0.25rem;
                margin-bottom: 1rem;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
                overflow-x: auto;
            }
            
            #apptStatusTabs button {
                flex: 1;
                padding: 0.875rem 1rem;
                border: none;
                border-radius: 8px;
                font-weight: 600;
                font-size: 0.9rem;
                cursor: pointer;
                transition: all 0.3s ease;
                white-space: nowrap;
                min-width: 100px;
                background: #f8f9fa;
                color: #495057;
                border: 2px solid #e9ecef;
            }
            
            #apptStatusTabs button.active,
            #apptStatusTabs button:hover {
                background: var(--primary-color);
                color: white;
                box-shadow: 0 2px 8px rgba(29, 93, 155, 0.3);
            }
            
            .appointment-card {
                background: var(--white);
                border-radius: 12px;
                padding: 1.25rem;
                margin-bottom: 1rem;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
                border: 1px solid rgba(0, 0, 0, 0.05);
                transition: all 0.3s ease;
                position: relative;
                overflow: hidden;
            }
            
            .appointment-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            }
            
            .appointment-card:hover {
                box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
                transform: translateY(-2px);
            }
            
            .appointment-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
                margin-bottom: 1rem;
                padding-bottom: 0.75rem;
                border-bottom: 1px solid #e9ecef;
            }
            
            .appointment-date {
                font-size: 1.1rem;
                font-weight: 700;
                color: var(--primary-color);
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            
            .appointment-date::before {
                content: '📅';
                font-size: 1rem;
            }
            
            .appointment-status {
                padding: 0.5rem 1rem;
                border-radius: 20px;
                font-size: 0.85rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                align-self: flex-start;
            }
            
            .appointment-details { grid-template-columns: 1fr; }
            .detail-item.co-item { justify-self: start; text-align: left; max-width: 100%; }
            .detail-item.co-item .detail-value { white-space: normal; overflow: visible; text-overflow: initial; max-width: 100%; }
            
            .detail-item {
                padding: 0.75rem;
                background: #f8f9fa;
                border-radius: 8px;
                border-left: 3px solid var(--primary-color);
            }
            
            .detail-label {
                font-size: 0.8rem;
                font-weight: 600;
                color: var(--gray-600);
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 0.25rem;
            }
            
            .detail-value {
                font-size: 0.9rem;
                color: var(--dark-color);
                font-weight: 500;
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
            
            .meet-link-section {
                grid-column: 1;
                margin-top: 0.75rem;
                padding: 1rem;
                background: #f8f9fa;
                border-radius: 8px;
                border: 1px solid #e9ecef;
            }
            
            .meet-link-input-group {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .meet-link-input {
                width: 100%;
                padding: 0.75rem;
                border: 1px solid #ced4da;
                border-radius: 6px;
                font-size: 0.9rem;
                margin-bottom: 0.5rem;
            }
            
            .save-link-button {
                width: 100%;
                padding: 0.75rem;
                background: var(--primary-color);
                color: white;
                border: none;
                border-radius: 6px;
                font-weight: 600;
                font-size: 0.9rem;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            
            .save-link-button:hover {
                background: var(--primary-dark);
                transform: translateY(-1px);
            }
            
            .appointment-actions {
                flex-direction: column;
                gap: 0.75rem;
                margin-top: 1rem;
                padding-top: 1rem;
                border-top: 1px solid #e9ecef;
            }
            
            .action-button {
                width: 100%;
                padding: 0.875rem 1rem;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                font-weight: 600;
                font-size: 0.9rem;
                transition: all 0.3s ease;
                min-height: 48px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
            }
            
            .join-button {
                background: linear-gradient(135deg, #28a745, #20c997);
                color: white;
                box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
            }
            
            .join-button::before {
                content: '🚀';
                font-size: 1rem;
            }
            
            .join-button:hover {
                background: linear-gradient(135deg, #218838, #1ea085);
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
            }
            
            .complete-button {
                background: linear-gradient(135deg, #28a745, #20c997);
                color: white;
                box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
            }
            
            .complete-button::before {
                content: '✓';
                font-size: 1rem;
                font-weight: bold;
            }
            
            .complete-button:hover {
                background: linear-gradient(135deg, #218838, #1ea085);
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
            }
            
            
            .notes-button {
                background: linear-gradient(135deg, #ffc107, #e0a800);
                color: #212529;
                box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3);
            }
            
            .notes-button::before {
                content: '📝';
                font-size: 1rem;
            }
            
            .notes-button:hover {
                background: linear-gradient(135deg, #e0a800, #d39e00);
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(255, 193, 7, 0.4);
            }
            
            /* Additional mobile action button colors */
            .action-button[onclick*="viewCancellationReason"] { 
                background: linear-gradient(135deg, #6c757d, #5a6268);
                color: white;
                box-shadow: 0 2px 8px rgba(108, 117, 125, 0.3);
            }
            
            .action-button[onclick*="viewCancellationReason"]:before {
                content: '❓ ';
                font-size: 1rem;
            }
            
            .action-button[onclick*="viewCancellationReason"]:hover {
                background: linear-gradient(135deg, #5a6268, #495057);
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(108, 117, 125, 0.4);
            }
            
            .action-button[onclick*="doctor_notes.php"] { 
                background: linear-gradient(135deg, #17a2b8, #138496);
                color: white;
                box-shadow: 0 2px 8px rgba(23, 162, 184, 0.3);
            }
            
            .action-button[onclick*="doctor_notes.php"]:before {
                content: '📝 ';
                font-size: 1rem;
            }
            
            .action-button[onclick*="doctor_notes.php"]:hover {
                background: linear-gradient(135deg, #138496, #117a8b);
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(23, 162, 184, 0.4);
            }
            
            .action-button[onclick*="patient_tracking.php"] { 
                background: linear-gradient(135deg, #6f42c1, #5a32a3);
                color: white;
                box-shadow: 0 2px 8px rgba(111, 66, 193, 0.3);
            }
            
            .action-button[onclick*="patient_tracking.php"]:before {
                content: '📊 ';
                font-size: 1rem;
            }
            
            .action-button[onclick*="patient_tracking.php"]:hover {
                background: linear-gradient(135deg, #5a32a3, #4c2a8a);
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(111, 66, 193, 0.4);
            }
            
            .action-button[onclick*="session_feedback.php"] { 
                background: linear-gradient(135deg, #28a745, #20c997);
                color: white;
                box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
            }
            
            .action-button[onclick*="session_feedback.php"]:before {
                content: '💬 ';
                font-size: 1rem;
            }
            
            .action-button[onclick*="session_feedback.php"]:hover {
                background: linear-gradient(135deg, #218838, #1ea085);
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
            }
            
            .action-button[onclick*="openManageModal"] { 
                background: linear-gradient(135deg, #fd7e14, #e55a00);
                color: white;
                box-shadow: 0 2px 8px rgba(253, 126, 20, 0.3);
            }
            
            .action-button[onclick*="openManageModal"]:before {
                content: '⚙️ ';
                font-size: 1rem;
            }
            
            .action-button[onclick*="openManageModal"]:hover {
                background: linear-gradient(135deg, #e55a00, #cc5500);
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(253, 126, 20, 0.4);
            }
            
            .action-button[onclick*="openInviteModal"] { 
                background: linear-gradient(135deg, #20c997, #17a2b8);
                color: white;
                box-shadow: 0 2px 8px rgba(32, 201, 151, 0.3);
            }
            
            .action-button[onclick*="openInviteModal"]:before {
                content: '👥 ';
                font-size: 1rem;
            }
            
            .action-button[onclick*="openInviteModal"]:hover {
                background: linear-gradient(135deg, #17a2b8, #138496);
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(32, 201, 151, 0.4);
            }
            
            .modal-content {
                margin: auto;
                width: 95%;
                max-width: 500px;
                border-radius: 12px;
                max-height: 85vh;
                overflow-y: auto;
                background: var(--white);
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                position: relative;
            }
            
            .modal {
                align-items: center;
                justify-content: center;
            }
            
            .close {
                font-size: 1.5rem;
                padding: 0.5rem;
                min-width: 44px;
                min-height: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .no-appointments {
                text-align: center;
                padding: 2rem 1rem;
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
                border: 2px dashed var(--primary-color);
                margin: 1rem 0;
            }
            
            .no-appointments h3 {
                color: var(--primary-color);
                margin-bottom: 0.75rem;
                font-size: 1.3rem;
                font-weight: 700;
            }
            
            .no-appointments p {
                color: var(--gray-600);
                font-size: 1rem;
                font-weight: 500;
                line-height: 1.5;
            }
            
            /* Mobile form enhancements */
            .modal-content select,
            .modal-content textarea {
                width: 100%;
                padding: 0.75rem;
                border: 1px solid #ced4da;
                border-radius: 6px;
                font-size: 16px; /* Prevents zoom on iOS */
                margin-bottom: 0.75rem;
            }
            
            .modal-content label {
                font-weight: 600;
                color: var(--dark-color);
                margin-bottom: 0.5rem;
                display: block;
            }
            
            .modal-content hr {
                margin: 1.5rem 0;
                border: none;
                height: 1px;
                background: #e9ecef;
            }
            
            .modal-content h3 {
                font-size: 1.4rem;
                font-weight: 700;
                color: var(--dark-color);
                margin-bottom: 1rem;
                padding-bottom: 0.5rem;
                border-bottom: 3px solid var(--primary-color);
            }
            
            .modal-content h4 {
                font-size: 1.1rem;
                font-weight: 600;
                color: var(--dark-color);
                margin-bottom: 0.75rem;
            }
        }
        
        @media (max-width: 480px) {
            .appointments-container {
                padding: 0.25rem;
            }
            
            .appointment-card {
                padding: 1rem;
                border-radius: 10px;
            }
            
            .appointment-header {
                gap: 0.5rem;
                margin-bottom: 0.75rem;
            }
            
            .appointment-date {
                font-size: 1rem;
            }
            
            .appointment-status {
                padding: 0.375rem 0.75rem;
                font-size: 0.8rem;
            }
            
            .detail-item {
                padding: 0.625rem;
            }
            
            .detail-label {
                font-size: 0.75rem;
            }
            
            .detail-value {
                font-size: 0.85rem;
            }
            
            .action-button {
                padding: 0.75rem;
                font-size: 0.85rem;
                min-height: 44px;
            }
            
            .notes-button {
                color: #fff;
            }
            
            .meet-link-section {
                padding: 0.75rem;
            }
            
            .meet-link-input {
                padding: 0.625rem;
                font-size: 0.85rem;
            }
            
            .save-link-button {
                padding: 0.625rem;
                font-size: 0.85rem;
            }
            
            #apptStatusTabs button {
                padding: 0.75rem 0.875rem;
                font-size: 0.85rem;
                min-width: 80px;
            }
            
            .modal-content {
                padding: 1rem;
            }
            
            .modal-content h3 {
                font-size: 1.2rem;
            }
            
            .modal-content h4 {
                font-size: 1rem;
            }
        }

        /* Mobile header positioning is centralized in styles/mobile.css */
        
        /* Enhanced Cancellation Modal Styling */
        .cancellation-modal-content {
            max-width: 500px;
            width: 90%;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 25px;
        }
        
        .modal-header h3 {
            color: #dc3545;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-header h3::before {
            content: "🚫";
            font-size: 1.3rem;
        }
        
        .modal-body {
            padding: 0;
        }
        
        .cancellation-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: linear-gradient(135deg, #fff5f5 0%, #fef2f2 100%);
            border-radius: 8px;
            border-left: 4px solid #dc3545;
        }
        
        .info-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .info-text {
            color: #721c24;
            font-weight: 500;
            margin: 0;
            line-height: 1.5;
        }
        
        .cancellation-reason-box {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            min-height: 100px;
            position: relative;
        }
        
        .cancellation-reason-box::before {
            content: "💬";
            position: absolute;
            top: -10px;
            left: 20px;
            background: #f8f9fa;
            padding: 0 10px;
            font-size: 1.2rem;
        }
        
        #cancellationReasonContent {
            color: #495057;
            font-size: 1rem;
            line-height: 1.6;
            font-style: italic;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        #cancellationReasonContent:empty::after {
            content: "No cancellation reason provided by the client.";
            color: #6c757d;
            font-style: normal;
        }
        
        /* Enhanced close button for cancellation modal */
        .cancellation-modal-content .close {
            color: #dc3545;
            font-size: 1.8rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 5px;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .cancellation-modal-content .close:hover {
            background: #f8d7da;
            color: #721c24;
            transform: scale(1.1);
        }
        
        /* Enhanced Session Notes Modal Styling */
        .notes-modal-content {
            max-width: 600px;
            width: 90%;
        }
        
        .notes-modal-content .modal-header h3 {
            color: #1D5D9B;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .notes-modal-content .modal-header h3::before {
            content: "📋";
            font-size: 1.3rem;
        }
        
        .notes-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: linear-gradient(135deg, #f0f8ff 0%, #e6f3ff 100%);
            border-radius: 8px;
            border-left: 4px solid #1D5D9B;
        }
        
        .notes-info .info-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .notes-info .info-text {
            color: #1D5D9B;
            font-weight: 500;
            margin: 0;
            line-height: 1.5;
        }
        
        .notes-content-box {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            min-height: 150px;
            position: relative;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .notes-content-box::before {
            content: "📄";
            position: absolute;
            top: -10px;
            left: 20px;
            background: #f8f9fa;
            padding: 0 10px;
            font-size: 1.2rem;
        }
        
        #notesContent {
            color: #495057;
            font-size: 1rem;
            line-height: 1.6;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        #notesContent:empty::after {
            content: "No notes available from the client for this session.";
            color: #6c757d;
            font-style: italic;
        }
        
        #notesContent strong {
            color: #1D5D9B;
            font-weight: 600;
        }
        
        /* Enhanced close button for notes modal */
        .notes-modal-content .close {
            color: #1D5D9B;
            font-size: 1.8rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 5px;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .notes-modal-content .close:hover {
            background: #e6f3ff;
            color: #14487a;
            transform: scale(1.1);
        }
        /* Logout confirm modal visibility and stacking */
        #logoutConfirmModal.show { display: flex !important; }
        @media (max-width: 768px) {
            body.modal-open .dbSidebar { transform: translateX(-100%) !important; }
            body.modal-open #mobileMenuOverlay { display: none !important; opacity: 0 !important; }
        }
        /* Remove any leading icon from the logout modal title */
        #logoutConfirmModal .modal-header h3::before { content: none !important; }

        /* Enhanced Invite Co-Therapist Modal Styling */
        .invite-modal-content {
            width: 90%;
            max-width: 500px;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            padding: 0;
            border: none;
            overflow: hidden;
        }

        .invite-modal-content .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 2px solid #e9ecef;
            background: #fff;
            color: #20c997;
        }

        .invite-modal-content .modal-header h3 {
            color: #20c997;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .invite-modal-content .modal-header h3::before {
            content: "👥";
            font-size: 1.3rem;
        }

        .invite-modal-content .modal-body {
            padding: 20px;
        }

        .invite-modal-content .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            padding: 0 20px 20px 20px;
        }

        .invite-modal-content .close {
            color: #20c997;
            font-size: 1.8rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 5px;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .invite-modal-content .close:hover {
            background: #e8f5f3;
            color: #17a2b8;
            transform: scale(1.1);
        }

        .invite-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: linear-gradient(135deg, #e8f5f3 0%, #d1f2eb 100%);
            border-radius: 8px;
            border-left: 4px solid #20c997;
        }

        .invite-info .info-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .invite-info .info-text {
            color: #20c997;
            font-weight: 500;
            margin: 0;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
            font-size: 0.95rem;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fff;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: #20c997;
            box-shadow: 0 0 0 3px rgba(32, 201, 151, 0.1);
        }

        .form-control:hover {
            border-color: #17a2b8;
        }

        .submit-button {
            background: linear-gradient(135deg, #20c997, #17a2b8);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(32, 201, 151, 0.3);
        }

        .submit-button:hover {
            background: linear-gradient(135deg, #17a2b8, #138496);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(32, 201, 151, 0.4);
        }

        .cancel-button {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .cancel-button:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }

        /* Mobile Enhancements for Invite Modal */
        @media (max-width: 768px) {
            .invite-modal-content {
                width: 95%;
                max-width: 400px;
                margin: 20px auto;
            }

            .invite-modal-content .modal-header {
                padding: 12px 16px;
            }

            .invite-modal-content .modal-header h3 {
                font-size: 1.3rem;
            }

            .invite-modal-content .modal-body {
                padding: 16px;
            }

            .invite-info {
                padding: 12px;
                margin-bottom: 16px;
            }

            .form-group {
                margin-bottom: 16px;
            }

            .form-control {
                padding: 10px 14px;
                font-size: 16px; /* Prevents zoom on iOS */
            }

            .invite-modal-content .modal-actions {
                flex-direction: column;
                gap: 8px;
                padding: 0 16px 16px 16px;
            }

            .submit-button,
            .cancel-button {
                width: 100%;
                padding: 14px 20px;
                font-size: 1rem;
            }

            .invite-modal-content .close {
                width: 40px;
                height: 40px;
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .invite-modal-content {
                width: 98%;
                margin: 10px auto;
            }

            .invite-modal-content .modal-header {
                padding: 10px 14px;
            }

            .invite-modal-content .modal-header h3 {
                font-size: 1.2rem;
            }

            .invite-modal-content .modal-body {
                padding: 14px;
            }

            .invite-info {
                padding: 10px;
                margin-bottom: 14px;
            }

            .form-group {
                margin-bottom: 14px;
            }

            .form-control {
                padding: 8px 12px;
            }
        }

        /* Ensure Add Co-Therapist modal shows on mobile without affecting other modals */
        #inviteModal { z-index: 20040; }
        #inviteModal.show { display: flex !important; }

        /* Compatibility: remove emoji pseudo-elements that show as unfamiliar boxes on some devices */
        .action-button::before,
        .action-button[onclick*="viewCancellationReason"]:before,
        .action-button[onclick*="doctor_notes.php"]:before,
        .action-button[onclick*="patient_tracking.php"]:before,
        .action-button[onclick*="session_feedback.php"]:before,
        .action-button[onclick*="openManageModal"]:before,
        .action-button[onclick*="openInviteModal"]:before,
        .appointment-card::before,
        .appointment-date::before,
        .join-button::before,
        .complete-button::before,
        .notes-button::before,
        .notes-modal-content .modal-header h3::before,
        .invite-modal-content .modal-header h3::before,
        .cancellation-reason-box::before,
        .modal-header h3::before { content: '' !important; }
    </style>
</head>
<body class="dbBody">
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
                <img src="<?php echo htmlspecialchars($profilePicture); ?>" alt="Profile Picture" class="mobile-user-avatar">
                <span class="mobile-user-name"><?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
            </div>
        </div>
    </div>

    <!-- Mobile Menu Overlay -->
    <div class="mobile-menu-overlay" id="mobileMenuOverlay" onclick="closeMobileMenu()"></div>

    <div class="dbContainer">
        <div class="dbSidebar">
            <div class="sidebarProfile">
                <img src="<?php echo htmlspecialchars($profilePicture); ?>" alt="Profile Picture" class="defaultPicture" id="profilePic">
                <h1 class="profileName"><?php echo htmlspecialchars($_SESSION['first_name']); ?></h1>
                <p class="userRole"><?php echo ucfirst($userRole); ?></p>
            </div>
            <ul class="sidebarNavList">
                <li class="sidebarNavItem"><a href="dashboard.php" class="sidebarNavLink">Home</a></li>
                <li class="sidebarNavItem"><a href="appointments.php" class="sidebarNavLink active">Appointments</a></li>
                <li class="sidebarNavItem"><a href="pending_requests.php" class="sidebarNavLink">Pending Requests</a></li>
                <li class="sidebarNavItem"><a href="patients.php" class="sidebarNavLink">My Clients</a></li>
                <li class="sidebarNavItem"><a href="patient_tracking.php" class="sidebarNavLink">Client Tracking</a></li>
                <li class="sidebarNavItem"><a href="therapy_support.php" class="sidebarNavLink">Therapy Support</a></li>
                <li class="sidebarNavItem"><a href="community_forum.php" class="sidebarNavLink">Community Forum</a></li>
                <li class="sidebarNavItem"><a href="doctor_schedule.php" class="sidebarNavLink">Schedule</a></li>
                <li class="sidebarNavItem"><a href="analytics_dashboard.php" class="sidebarNavLink">Analytics</a></li>
                <li class="sidebarNavItem"><a href="student_messages.php" class="sidebarNavLink">Messages</a></li>
                <li class="sidebarNavItem"><a href="profile_settings.php" class="sidebarNavLink">Settings</a></li>
            </ul>
            <div class="sidebarFooter">
                <button type="button" class="logoutButton" onclick="openLogoutConfirm()">Logout</button>
            </div>
        </div>

        <div class="dbMainContent">
            <div class="appointments-container">
                <h2>Appointments</h2>
                <div id="apptStatusTabs">
                    <button type="button" id="apptTabScheduled" class="action-button">Scheduled</button>
                    <button type="button" id="apptTabCompleted" class="action-button">Completed</button>
                    <button type="button" id="apptTabCancelled" class="action-button">Cancelled</button>
                </div>
                <div class="appointment-filters">
                    <input type="date" id="dateFilter" onchange="filterAppointments()">
                </div>

                <?php if (isset($_GET['message']) && $_GET['message'] === 'system_feedback_submitted'): ?>
                    <div class="success-message" style="background:#d4edda; color:#155724; padding:12px; border:1px solid #c3e6cb; border-radius:8px; margin-bottom:12px;">✅ System Feedback Submitted! Thank you for helping improve Mind Matters.</div>
                <?php endif; ?>
                <?php if (empty($appointments)): ?>
                    <div class="no-appointments">
                        <h3>No appointments found</h3>
                        <p>You don't have any appointments at the moment.</p>
                    </div>
                <?php else: ?>
                    <div id="appointmentsList">
                        <?php foreach ($appointments as $appointment): ?>
                            <div class="appointment-card" data-id="<?php echo (int)$appointment['id']; ?>" data-status="<?php echo htmlspecialchars($appointment['status']); ?>" 
                                 data-date="<?php echo htmlspecialchars($appointment['session_date']); ?>" data-time="<?php echo htmlspecialchars($appointment['session_time']); ?>" data-datetime="<?php echo htmlspecialchars($appointment['session_date'] . ' ' . $appointment['session_time']); ?>">
                                <div class="appointment-header">
                                    <div class="appointment-date">
                                        <?php echo date('F j, Y', strtotime($appointment['session_date'])); ?>
                                        at <?php echo date('g:i A', strtotime($appointment['session_time'])); ?>
                                        <?php if ($appointment['status'] === 'scheduled'): ?>
                                        <div class="appointment-countdown" id="appt-countdown-<?php echo $appointment['id']; ?>">
                                            <small><span class="countdown-text">Loading countdown...</span></small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="appointment-status <?php echo htmlspecialchars($appointment['status']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($appointment['status'])); ?>
                                    </div>
                                </div>
                                <div class="appointment-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Client</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($appointment['client_name']); ?></span>
                                    </div>
                                    <?php if (empty($appointment['is_primary'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Primary Therapist</span>
                                        <span class="detail-value">Dr. <?php echo htmlspecialchars($appointment['primary_therapist_name']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Client ID</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($appointment['client_user_id']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Email</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($appointment['client_email']); ?></span>
                                    </div>
                                    <?php if (!empty($appointment['is_primary'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Co-Therapist(s)</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($appointment['co_therapists_names'] ?: 'None'); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($appointment['status'] === 'scheduled'): ?>
                                    <div class="detail-item meet-link-section">
                                        <span class="detail-label">Google Meet Link</span>
                                        <?php if (!empty($appointment['is_primary'])): ?>
                                        <div class="meet-link-input-group">
                                            <input type="text" 
                                                   class="meet-link-input" 
                                                   placeholder="Enter Google Meet link" 
                                                   value="<?php echo htmlspecialchars($appointment['meet_link'] ?? ''); ?>"
                                                   data-session-id="<?php echo $appointment['id']; ?>">
                                            <button class="save-link-button" onclick="saveMeetLink(<?php echo $appointment['id']; ?>)">Save Link</button>
                                        </div>
                                        <?php else: ?>
                                            <div class="meet-link-input-group">
                                                <input type="text" class="meet-link-input" value="<?php echo htmlspecialchars($appointment['meet_link'] ?? ''); ?>" readonly>
                                            </div>
                                            <small>Primary therapist's link (view only)</small>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php
                                      $reschedLine = '';
                                      $endorseLine = '';
                                      if (!empty($appointment['proposed_date']) && !empty($appointment['proposed_time'])) {
                                        $reschedLine = 'Proposed Reschedule: ' . date('F j, Y', strtotime($appointment['proposed_date'])) . ' at ' . date('g:i A', strtotime($appointment['proposed_time']));
                                      }
                                      if (!empty($appointment['endorse_proposed_date']) && !empty($appointment['endorse_proposed_time'])) {
                                        $endorseLine = 'Endorsement Proposed: ' . date('F j, Y', strtotime($appointment['endorse_proposed_date'])) . ' at ' . date('g:i A', strtotime($appointment['endorse_proposed_time']));
                                        if (!empty($appointment['endorsed_first'])) {
                                          $endorseLine .= ' — Dr. ' . htmlspecialchars($appointment['endorsed_first'] . ' ' . $appointment['endorsed_last']);
                                        }
                                      }
                                ?>
                                <?php if (!empty($reschedLine) || !empty($endorseLine)): ?>
                                  <div>
                                    <?php if (!empty($reschedLine)): ?>
                                      <div><span><?php echo htmlspecialchars($reschedLine); ?></span></div>
                                    <?php endif; ?>
                                    <?php if (!empty($endorseLine)): ?>
                                      <div><span><?php echo htmlspecialchars($endorseLine); ?></span></div>
                                    <?php endif; ?>
                                  </div>
                                <?php endif; ?>
                                <div class="appointment-actions">
                                    <?php if ($appointment['status'] === 'scheduled'): ?>
                                        <button id="appt-join-btn-<?php echo $appointment['id']; ?>" class="action-button join-button" onclick="joinSession(<?php echo $appointment['id']; ?>, '<?php echo htmlspecialchars($appointment['meet_link'] ?? ''); ?>')" data-start-ts="<?php echo strtotime($appointment['session_date'] . ' ' . $appointment['session_time']) * 1000; ?>">Join Session</button>
                                        <button class="action-button notes-button" onclick="viewNotes(<?php echo $appointment['id']; ?>)">View Notes</button>
                                        <button class="action-button complete-button" onclick="completeAppointment(<?php echo $appointment['id']; ?>)">Complete</button>
                                        <button class="action-button" onclick="openManageModal(<?php echo $appointment['id']; ?>, '<?php echo htmlspecialchars($appointment['session_date']); ?>', '<?php echo htmlspecialchars($appointment['session_time']); ?>')">Manage Availability</button>
                                        <button class="action-button" onclick="openInviteModal(<?php echo $appointment['id']; ?>)">Add Co-Therapist</button>
                                    <?php elseif ($appointment['status'] === 'cancelled'): ?>
                                        <button class="action-button" onclick="viewCancellationReason(<?php echo $appointment['id']; ?>, '<?php echo htmlspecialchars($appointment['cancellation_reason'] ?? ''); ?>')">View Cancellation Reason</button>
                                    <?php endif; ?>
                                    <button class="action-button" onclick="window.location.href='doctor_notes.php?session_id=<?php echo $appointment['id']; ?>&client_id=<?php echo $appointment['client_id']; ?>&from=appointments'">Add Notes</button>
                                    <button class="action-button" onclick="window.location.href='patient_tracking.php?client_id=<?php echo $appointment['client_id']; ?>'">Track Client</button>
                                    <?php if ($appointment['status'] === 'completed' && !(int)$appointment['has_doctor_feedback']): ?>
                                        <button class="action-button" onclick="window.location.href='session_feedback.php?session_id=<?php echo $appointment['id']; ?>&type=doctor'">Therapist Feedback</button>
                                    <?php endif; ?>
                                    <?php if ($appointment['status'] === 'completed'): ?>
                                        <?php
                                            $sfCompleted = false; $sfCreated = null;
                                            if ($stmtSF = $conn->prepare("SELECT id, created_at FROM system_feedback WHERE session_id = ? AND user_id = ? LIMIT 1")) {
                                                $stmtSF->bind_param("ii", $appointment['id'], $userId);
                                                $stmtSF->execute();
                                                $r = $stmtSF->get_result()->fetch_assoc();
                                                if ($r) { $sfCompleted = true; $sfCreated = $r['created_at'] ?? null; }
                                                $stmtSF->close();
                                            }
                                        ?>
                                        <?php if ($sfCompleted): ?>
                                            <div class="feedback-completed" style="flex: 1 1 100%; margin-top:10px;">
                                                <div class="completed-icon">✅</div>
                                                <div class="completed-text">
                                                    <strong>System Feedback Completed</strong><br>
                                                    <?php if ($sfCreated): ?>Submitted on <?php echo date('M j, Y g:i A', strtotime($sfCreated)); ?><?php else: ?>Thank you for your feedback on the system.<?php endif; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <button class="action-button" style="background:#17a2b8; border-color:#17a2b8; color:#ffffff;" onclick="window.location.href='system_feedback.php?session_id=<?php echo $appointment['id']; ?>&from=appointments'">System Feedback</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Logout Confirm Modal (consistent with other pages) -->
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

    <div id="notesModal" class="modal">
        <div class="modal-content notes-modal-content">
            <div class="modal-header">
                <h3>Client Session Notes</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="notes-info">
                    <div class="info-icon">📝</div>
                    <p class="info-text">Session notes from the client:</p>
                </div>
                <div class="notes-content-box">
                    <div id="notesContent"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Unified Manage Modal (Reschedule + Endorse) -->
    <div id="manageModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeManageModal()">&times;</span>
            <h3>Manage Availability</h3>
            <div>
                <div>
                    <h4>Re-schedule</h4>
                    <div>
                        <div>
                            <label>Preferred Date</label>
                            <select id="reDate" class="meet-link-input"></select>
                        </div>
                        <div>
                            <label>Preferred Time</label>
                            <select id="reTime" class="meet-link-input" disabled></select>
                        </div>
                    </div>
                    <button class="action-button" onclick="submitRescheduleTherapist()">Propose Reschedule</button>
                </div>
                <hr>
                <div>
                    <h4>Endorse to Another Therapist</h4>
                    <div>
                        <div>
                            <label>Select Therapist</label>
                            <select id="enTherapist" class="meet-link-input"></select>
                        </div>
                        <div>
                            <label>Preferred Date</label>
                            <select id="enDate" class="meet-link-input" disabled></select>
                        </div>
                        <div>
                            <label>Preferred Time</label>
                            <select id="enTime" class="meet-link-input" disabled></select>
                        </div>
                    </div>
                    <button class="action-button" onclick="submitEndorseUnified()">Confirm Endorsement</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Invite Co-Therapist Modal -->
    <div id="inviteModal" class="modal">
        <div class="modal-content invite-modal-content">
            <div class="modal-header">
                <h3>Add Co-Therapist</h3>
                <span class="close" onclick="closeInviteModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="invite-info">
                    <div class="info-icon">👥</div>
                    <p class="info-text">Invite another therapist to collaborate on this session</p>
                </div>
                <div class="form-group">
                    <label for="coTherapist">Select Therapist</label>
                    <select id="coTherapist" class="form-control">
                        <option value="">Choose a therapist...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="coReason">Reason / Specialization (optional)</label>
                    <textarea id="coReason" rows="3" class="form-control" placeholder="Explain why you're inviting this therapist or their specialization..."></textarea>
                </div>
            </div>
            <div class="modal-actions">
                <button class="action-button cancel-button" onclick="closeInviteModal()">Cancel</button>
                <button class="action-button submit-button" onclick="submitInvite()">Send Invitation</button>
            </div>
        </div>
    </div>

    <!-- Cancellation Reason Modal -->
    <div id="cancellationModal" class="modal">
        <div class="modal-content cancellation-modal-content">
            <div class="modal-header">
                <h3>Session Cancellation Reason</h3>
                <span class="close" onclick="closeCancellationModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="cancellation-info">
                    <div class="info-icon">⚠️</div>
                    <p class="info-text">The client provided the following reason for cancelling this session:</p>
                </div>
                <div class="cancellation-reason-box">
                    <div id="cancellationReasonContent"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/notifications.js?v=<?php echo time(); ?>"></script>
    <script src="js/session_manager.js?v=<?php echo time(); ?>"></script>
    <script>
        // Align client/server time similar to my_session
        const APPT_SERVER_NOW_TS = <?php echo round(microtime(true) * 1000); ?>;
        const APPT_CLIENT_NOW_TS_AT_RENDER = Date.now();
        const APPT_CLOCK_OFFSET_MS = APPT_SERVER_NOW_TS - APPT_CLIENT_NOW_TS_AT_RENDER;
        let currentStatus = 'scheduled';

        function filterAppointments() {
            const statusFilter = currentStatus; // controlled by tabs
            const dateFilter = document.getElementById('dateFilter').value;
            const appointments = document.querySelectorAll('.appointment-card');

            appointments.forEach(appointment => {
                const status = appointment.dataset.status;
                const date = appointment.dataset.date;
                let show = true;

                if (statusFilter && statusFilter !== 'all' && status !== statusFilter) {
                    show = false;
                }

                if (dateFilter && date !== dateFilter) {
                    show = false;
                }

                appointment.style.display = show ? 'block' : 'none';
            });
        }

        function completeAppointment(appointmentId) {
            showConfirm('Are you sure you want to mark this appointment as completed?').then((ok)=>{ if(!ok) return;
                fetch('update_appointment_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        appointment_id: appointmentId,
                        status: 'completed'
                    })
                })
                .then(r=>safeParseJSON(r))
                .then(data => { 
                    if (data.success) { 
                        // Update the card locally
                        const card = document.querySelector(`.appointment-card[data-id="${appointmentId}"]`);
                        if (card) {
                            card.setAttribute('data-status','completed');
                            const statusEl = card.querySelector('.appointment-status');
                            if (statusEl) { statusEl.className = 'appointment-status completed'; statusEl.textContent = 'Completed'; }
                        }
                        // Apply current filters and, if on Scheduled tab, hide it now
                        filterAppointments();
                        // Prompt to give feedback
                        showConfirm('Session marked as completed. Would you like to write your therapist feedback now?').then(go=>{
                            if (go) { window.location.href = 'session_feedback.php?session_id=' + encodeURIComponent(appointmentId) + '&type=doctor'; }
                            else { showToast('Reminder: Please provide your feedback for this session when ready.', 'info'); }
                        });
                    } else { 
                        showToast('Error updating appointment status: ' + (data.error || 'Unknown error'), 'error'); 
                    } 
                })
                .catch(error => { console.error('Error:', error); showToast('Error updating appointment status', 'error'); });
            });
        }


        function viewNotes(appointmentId) {
            fetch('get_appointment_notes.php?appointment_id=' + appointmentId)
                .then(r=>safeParseJSON(r))
                .then(data => {
                    if (data.success) {
                        const modal = document.getElementById('notesModal');
                        const notesContent = document.getElementById('notesContent');
                        let formattedNotes = data.notes || 'No notes available for this session.';
                        // Remove system metadata lines (e.g., [Therapist Unavailable], [Endorsement], etc.)
                        formattedNotes = formattedNotes
                            .split('\n')
                            .filter(line => !line.trim().startsWith('['))
                            .filter(line => !/^co-therapist reason:/i.test(line.trim()))
                            .join('\n');
                        formattedNotes = formattedNotes.replace('Reason:', '<strong>Reason:</strong>');
                        formattedNotes = formattedNotes.replace('Symptoms:', '<strong>Symptoms:</strong>');
                        notesContent.innerHTML = formattedNotes;
                        modal.classList.add('show');
                    } else {
                        showToast('Error fetching notes: ' + (data.error || 'Unknown error'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error fetching notes', 'error');
                });
        }

        function joinSession(sessionId, meetLink) {
            if (!meetLink) { showToast('No Google Meet link has been set for this session. Please set a meet link first.', 'warning'); return; }
            window.open(meetLink, '_blank');
        }

        // Unified Manage Modal (Reschedule + Endorse)
        let currentSessionId = null;
        function openManageModal(sessionId, currDate, currTime){
            currentSessionId = sessionId; document.getElementById('manageModal').classList.add('show');
            const currentDate = currDate; const currentTime = currTime; // for filtering
            // Load therapist (self) availability
            fetch('get_therapist_availability.php?therapist_id=<?php echo $userId; ?>').then(r=>safeParseJSON(r)).then(d=>{
                if(!d.success){ return; }
                buildDateTimeDropdowns(d.data, 'reDate', 'reTime', undefined, currentDate, currentTime);
            });
            // Load therapists list for endorsement
            const enTherSel = document.getElementById('enTherapist');
            enTherSel.innerHTML = '<option>Loading...</option>';
            fetch('list_therapists.php?exclude_id=<?php echo $userId; ?>').then(r=>safeParseJSON(r)).then(d=>{
                if(!d.success){ enTherSel.innerHTML = '<option>Error loading</option>'; return; }
                enTherSel.innerHTML = d.therapists.map(t=>`<option value="${t.id}">Dr. ${t.first_name} ${t.last_name}</option>`).join('');
                onTherapistChange();
                enTherSel.onchange = onTherapistChange;
            });
        }
        function closeManageModal(){ document.getElementById('manageModal').classList.remove('show'); currentSessionId=null; }

        function toMinutes(hms){ const [h,m] = hms.split(':'); return parseInt(h)*60+parseInt(m); }
        function fromMinutes(min){ const h = Math.floor(min/60).toString().padStart(2,'0'); const m = (min%60).toString().padStart(2,'0'); return `${h}:${m}:00`; }
        function toLabel(hms){ const [h,m] = hms.split(':'); const d = new Date(); d.setHours(parseInt(h), parseInt(m), 0, 0); return d.toLocaleTimeString([], {hour:'numeric', minute:'2-digit'}); }

        function buildDateTimeDropdowns(daysData, dateSelId, timeSelId, therapistId, currentDate, currentTime){
            const availabilityMap = {};
            Object.keys(daysData||{}).forEach(day=>{ (daysData[day]||[]).forEach(s=>{ if(!availabilityMap[s.date]) availabilityMap[s.date]=[]; availabilityMap[s.date].push({start:s.start_time, end:s.end_time}); }); });
            const dateSel = document.getElementById(dateSelId);
            const timeSel = document.getElementById(timeSelId);
            dateSel.innerHTML=''; timeSel.innerHTML=''; dateSel.disabled=true; timeSel.disabled=true;
            const dates = Object.keys(availabilityMap).sort();
            async function fetchTimes(date){
                const tid = (therapistId||<?php echo $userId; ?>);
                const res = await fetch('get_booked_slots.php?therapist_id=' + tid + '&session_date=' + date);
                const d = await safeParseJSON(res);
                const booked = new Set(d.slots||[]);
                // Fetch pending slots for visual warning
                let pending = new Set();
                try {
                    const rp = await fetch('get_pending_slots.php?therapist_id=' + tid + '&session_date=' + date);
                    const dp = await safeParseJSON(rp);
                    pending = new Set((dp.slots||[]));
                } catch(e) { /* ignore */ }
                const options=[];

                // Determine if selected date is today; filter out past times
                const now = new Date();
                const y = now.getFullYear();
                const m = String(now.getMonth()+1).padStart(2,'0');
                const dd = String(now.getDate()).padStart(2,'0');
                const todayStr = `${y}-${m}-${dd}`;
                const isToday = (date === todayStr);
                const nowMinutes = now.getHours()*60 + now.getMinutes();

                (availabilityMap[date]||[]).forEach(w=>{ let t=toMinutes(w.start), e=toMinutes(w.end); while(t+60<=e){
                    if (isToday && t <= nowMinutes) { t += 60; continue; }
                    const h=fromMinutes(t);
                    // Exclude already booked starts and exclude the current session slot only
                    const isCurrentSlot = (date === (currentDate||'') && h === (currentTime||''));
                    if(!booked.has(h) && !isCurrentSlot) {
                        const label = toLabel(h) + (pending.has(h) ? ' (Pending)' : '');
                        options.push({value:h,label});
                    }
                    t+=60; } });
                return options;
            }
            (async ()=>{
                const validDates=[]; for(const dt of dates){ const times=await fetchTimes(dt); if(times.length) validDates.push({dt,times}); }
                if(!validDates.length){ dateSel.innerHTML='<option value="">No available dates</option>'; dateSel.disabled=true; return; }
                dateSel.innerHTML=validDates.map(v=>`<option value="${v.dt}">${new Date(v.dt).toLocaleDateString(undefined,{month:'long',day:'numeric',year:'numeric'})}</option>`).join('');
                function populate(times){ 
                    if(times.length > 0) {
                        timeSel.innerHTML = times.map(o=>`<option value="${o.value}">${o.label}</option>`).join(''); 
                        timeSel.value = times[0].value; // Auto-select first available time
                    } else {
                        timeSel.innerHTML = '<option value="">No available times</option>';
                    }
                    timeSel.disabled=false; 
                }
                populate(validDates[0].times); dateSel.value=validDates[0].dt;
                dateSel.disabled=false;
                dateSel.onchange = async ()=>{ const pick=validDates.find(v=>v.dt===dateSel.value) || {times: await fetchTimes(dateSel.value)}; populate(pick.times); };
            })();
        }

        function onTherapistChange(){
            const tid = document.getElementById('enTherapist').value;
            document.getElementById('enDate').disabled = true; document.getElementById('enTime').disabled = true;
            fetch('get_therapist_availability.php?therapist_id=' + tid).then(r=>safeParseJSON(r)).then(d=>{
                if(!d.success){ return; }
                buildDateTimeDropdowns(d.data, 'enDate', 'enTime', tid);
            });
        }
        // Add change handler after modal opens (element exists)
        // Invite co-therapist modal logic
        function openInviteModal(sessionId){
            currentSessionId = sessionId; 
            const modal = document.getElementById('inviteModal');
            try { modal.style.display = ''; } catch(e) {}
            modal.classList.add('show');
            try { document.body.classList.add('modal-open'); } catch(e) {}
            const sel = document.getElementById('coTherapist'); 
            sel.innerHTML = '<option value="">Loading...</option>';
            fetch('list_therapists.php?exclude_id=<?php echo $userId; ?>').then(r=>safeParseJSON(r)).then(d=>{
                if(!d.success){ 
                    sel.innerHTML = '<option value="">Error loading therapists</option>'; 
                    return; 
                }
                sel.innerHTML = '<option value="">Choose a therapist...</option>' + 
                    d.therapists.map(t=>`<option value="${t.id}">Dr. ${t.first_name} ${t.last_name}</option>`).join('');
            });
        }
        function closeInviteModal(){ 
            const modal = document.getElementById('inviteModal');
            modal.classList.remove('show');
            try { document.body.classList.remove('modal-open'); } catch(e) {}
            currentSessionId=null; 
            // Clear form
            document.getElementById('coTherapist').value = '';
            document.getElementById('coReason').value = '';
        }
        function submitInvite(){
            const sel = document.getElementById('coTherapist');
            const therapistId = parseInt(sel.value,10);
            if(!currentSessionId){ showToast('No session selected','error'); return; }
            if(!therapistId || Number.isNaN(therapistId)){ showToast('Please select a therapist','warning'); return; }
            const reason = document.getElementById('coReason').value.trim();
            fetch('invite_co_therapist.php', {
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify({ session_id: currentSessionId, therapist_id: therapistId, reason })
            })
            .then(r=>{ if(!r.ok) throw new Error('Network error'); return safeParseJSON(r); })
            .then(d=>{ 
                if(d.success){ 
                    // Close modal, show toast, and update UI without reload
                    closeInviteModal();
                    // Pull selected therapist display text
                    const selectedOption = sel.options[sel.selectedIndex];
                    const invitedName = selectedOption ? selectedOption.text : 'Selected therapist';
                    showToast('Invitation sent to ' + invitedName, 'success');
                    // Update co-therapists display on the current appointment card (primary therapist view only)
                    try {
                        const card = document.querySelector(`.appointment-card[data-id="${currentSessionId}"]`);
                        if (card) {
                            // Find the detail item whose label is "Co-Therapist(s)"
                            const detailItems = Array.from(card.querySelectorAll('.detail-item'));
                            const coItem = detailItems.find(di => {
                                const lbl = di.querySelector('.detail-label');
                                return lbl && lbl.textContent.trim().toLowerCase() === 'co-therapist(s)';
                            });
                            if (coItem) {
                                const valueEl = coItem.querySelector('.detail-value');
                                if (valueEl) {
                                    const currentText = (valueEl.textContent || '').trim();
                                    const invitedTag = invitedName.replace(/^Dr\.\s*/i, 'Dr. ');
                                    const newText = currentText && currentText.toLowerCase() !== 'none' 
                                        ? (currentText + ', ' + invitedTag + ' (invited)')
                                        : (invitedTag + ' (invited)');
                                    valueEl.textContent = newText;
                                }
                            }
                        }
                    } catch(e) { /* no-op */ }
                } else { 
                    showToast('Failed to invite: ' + (d.error||'Unknown error'),'error'); 
                } 
            })
            .catch(err=>{ console.error(err); showToast('Error sending invite','error'); });
        }

        // Safely parse JSON, tolerating servers that sometimes return HTML error pages
        async function safeParseJSON(response){
            try {
                const contentType = response.headers ? (response.headers.get('content-type') || '') : '';
                if (contentType.includes('application/json')) { return await response.json(); }
                const text = await response.text();
                try { return JSON.parse(text); } catch (e) {
                    console.error('Non-JSON response body:', text);
                    return { success: false, error: 'Server returned non-JSON response' };
                }
            } catch (e) {
                console.error('Failed to parse response as JSON:', e);
                return { success: false, error: 'Failed to parse server response' };
            }
        }

        function submitRescheduleTherapist(){
            const btns = Array.from(document.querySelectorAll('#manageModal .action-button')).filter(b=>b.textContent.trim().toLowerCase().includes('propose reschedule'));
            const btn = btns[0] || null;
            const d = document.getElementById('reDate').value; const t = document.getElementById('reTime').value;
            if (!d || !t) { showToast('Please select both date and time for reschedule', 'warning'); return; }
            if (btn) { btn.disabled = true; const prev = btn.textContent; btn.setAttribute('data-prev', prev); btn.textContent = '⏳ Submitting...'; }
            const fd = new FormData();
            fd.append('session_id', currentSessionId);
            fd.append('proposed_date', d);
            fd.append('proposed_time', t);
            fetch('mark_session_unavailable.php',{ method:'POST', body: fd })
              .then(r=>safeParseJSON(r)).then(data=>{ 
                if(data.success){ 
                    showToast('Reschedule request submitted successfully! The client will be notified.', 'success');
                    try { closeManageModal(); } catch(e) { /* no-op */ }
                } else { 
                    showToast('Failed: ' + (data.error||'Unknown error'),'error'); 
                }
              })
              .catch(error => {
                console.error('Error:', error);
                showToast('Error submitting reschedule request', 'error');
              })
              .finally(()=>{ if (btn) { btn.disabled = false; const prev = btn.getAttribute('data-prev')||'Propose Reschedule'; btn.textContent = prev; btn.removeAttribute('data-prev'); } });
        }

        function submitEndorseUnified(){
            const tid = document.getElementById('enTherapist').value; const d = document.getElementById('enDate').value; const t = document.getElementById('enTime').value;
            const btns = Array.from(document.querySelectorAll('#manageModal .action-button')).filter(b=>b.textContent.trim().toLowerCase().includes('confirm endorsement'));
            const btn = btns[0] || null;
            if (!tid || !d || !t) { showToast('Please select therapist, date, and time for endorsement', 'warning'); return; }
            if (btn) { btn.disabled = true; const prev = btn.textContent; btn.setAttribute('data-prev', prev); btn.textContent = '⏳ Submitting...'; }
            const fd = new FormData();
            fd.append('session_id', currentSessionId);
            fd.append('therapist_id', parseInt(tid,10));
            fd.append('proposed_date', d);
            fd.append('proposed_time', t);
            fetch('endorse_to_therapist.php',{ method:'POST', body: fd })
              .then(r=>safeParseJSON(r)).then(d=>{ 
                if(d.success){ 
                    showToast('Endorsement request submitted successfully! The selected therapist will be notified.', 'success');
                    try { closeManageModal(); } catch(e) { /* no-op */ }
                } else { 
                    showToast('Failed: ' + (d.error||'Unknown error'),'error'); 
                }
              })
              .catch(error => {
                console.error('Error:', error);
                showToast('Error submitting endorsement request', 'error');
              })
              .finally(()=>{ if (btn) { btn.disabled = false; const prev = btn.getAttribute('data-prev')||'Confirm Endorsement'; btn.textContent = prev; btn.removeAttribute('data-prev'); } });
        }

        function saveMeetLink(sessionId) {
            const input = document.querySelector(`input[data-session-id="${sessionId}"]`);
            const meetLink = input.value.trim();
            
            if (!meetLink) { showToast('Please enter a Google Meet link', 'warning'); return; }
            
            if (!meetLink.includes('meet.google.com')) { showToast('Please enter a valid Google Meet link', 'warning'); return; }
            
            const formData = new FormData();
            formData.append('session_id', sessionId);
            formData.append('meet_link', meetLink);
            
            fetch('update_meet_link.php', {
                method: 'POST',
                body: formData
            })
            .then(r=>safeParseJSON(r))
            .then(data => { if (data.success) { showToast('Meet link saved successfully', 'success'); } else { showToast('Failed to save meet link: ' + (data.error || 'Unknown error'), 'error'); } })
            .catch(error => { console.error('Error:', error); showToast('An error occurred while saving the meet link', 'error'); });
        }


        // Add modal close functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Show success notification if a co-therapist was invited and page reloaded
            try {
                if (localStorage.getItem('MM_APPT_CO_INVITE_SUCCESS') === '1') {
                    showToast('Co-therapist invitation sent successfully.', 'success');
                    localStorage.removeItem('MM_APPT_CO_INVITE_SUCCESS');
                }
            } catch(e) {}
            // Countdown enablement and display
            document.querySelectorAll('.join-button[id^="appt-join-btn-"]').forEach(btn => {
                const startTs = parseInt(btn.getAttribute('data-start-ts'), 10);
                if (!startTs || isNaN(startTs)) return;
                const sessionId = btn.id.replace('appt-join-btn-','');
                const countdownEl = document.querySelector(`#appt-countdown-${sessionId} .countdown-text`);
                const GRACE_MS = 5 * 60 * 1000;
                const update = () => {
                    const nowMs = Date.now() + APPT_CLOCK_OFFSET_MS;
                    const diffMs = startTs - nowMs;
                    if (diffMs <= 0) { btn.disabled = false; if (countdownEl) countdownEl.textContent = 'Session is starting'; return; }
                    if (diffMs <= GRACE_MS) { btn.disabled = false; }
                    if (countdownEl) {
                        const totalSeconds = Math.floor(diffMs / 1000);
                        const days = Math.floor(totalSeconds / (24*3600));
                        const hours = Math.floor((totalSeconds % (24*3600)) / 3600);
                        const minutes = Math.floor((totalSeconds % 3600) / 60);
                        const seconds = totalSeconds % 60;
                        let parts = [];
                        if (days) parts.push(days + 'd');
                        if (hours || days) parts.push(hours + 'h');
                        if (minutes || hours || days) parts.push(minutes + 'm');
                        parts.push(seconds + 's');
                        countdownEl.textContent = (diffMs <= GRACE_MS ? 'Join opens in ' : 'Starts in ') + parts.join(' ');
                    }
                };
                update();
                setInterval(update, 1000);
            });
            const notesModal = document.getElementById('notesModal');
            const cancellationModal = document.getElementById('cancellationModal');
            
            // Status filter tabs behavior (Scheduled/Completed/Cancelled)
            const cards = Array.from(document.querySelectorAll('.appointment-card[data-status]'));
            const tabScheduled = document.getElementById('apptTabScheduled');
            const tabCompleted = document.getElementById('apptTabCompleted');
            const tabCancelled = document.getElementById('apptTabCancelled');

            function setActive(tab){
                [tabScheduled, tabCompleted, tabCancelled].forEach(b=>{ 
                    if(!b) return; 
                    b.style.background = '#f8f9fa'; 
                    b.style.color = '#495057';
                    b.style.borderColor = '#e9ecef';
                    b.classList.remove('active');
                });
                if (tab) {
                    tab.style.background = '#1D5D9B';
                    tab.style.color = 'white';
                    tab.style.borderColor = '#1D5D9B';
                    tab.classList.add('active');
                }
            }

            function sortScheduledAscending(){
                const container = document.getElementById('appointmentsList');
                if (!container) return;
                const items = Array.from(container.querySelectorAll('.appointment-card[data-status="scheduled"]'));
                items.sort((a,b)=>{
                    const at = new Date((a.getAttribute('data-datetime')||'').replace(' ', 'T')).getTime();
                    const bt = new Date((b.getAttribute('data-datetime')||'').replace(' ', 'T')).getTime();
                    return at - bt;
                });
                items.forEach(el=>container.appendChild(el));
            }

            if (tabScheduled && tabCompleted && tabCancelled){
                tabScheduled.addEventListener('click', ()=>{ setActive(tabScheduled); currentStatus = 'scheduled'; filterAppointments(); sortScheduledAscending(); });
                tabCompleted.addEventListener('click', ()=>{ setActive(tabCompleted); currentStatus = 'completed'; filterAppointments(); });
                tabCancelled.addEventListener('click', ()=>{ setActive(tabCancelled); currentStatus = 'cancelled'; filterAppointments(); });
                // default view: Completed if system feedback was just submitted
                const urlParams = new URLSearchParams(window.location.search);
                const goCompleted = urlParams.get('message') === 'feedback_submitted';
                if (goCompleted) { setActive(tabCompleted); currentStatus = 'completed'; filterAppointments(); }
                else { setActive(tabScheduled); currentStatus = 'scheduled'; filterAppointments(); sortScheduledAscending(); }
            }

            // Notes modal close functionality
            const notesCloseBtn = document.querySelector('#notesModal .close');
            if (notesCloseBtn) {
                notesCloseBtn.onclick = function() {
                    notesModal.classList.remove('show');
                }
            }

            // Cancellation modal close functionality
            const cancelCloseBtn = document.querySelector('#cancellationModal .close');
            if (cancelCloseBtn) {
                cancelCloseBtn.onclick = function() {
                    cancellationModal.classList.remove('show');
                }
            }

            window.addEventListener('click', function(event) {
                if (event.target === notesModal) {
                    notesModal.classList.remove('show');
                }
                if (event.target === cancellationModal) {
                    cancellationModal.classList.remove('show');
                }
                if (event.target === document.getElementById('inviteModal')) {
                    closeInviteModal();
                }
            });

            window.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    if (notesModal.classList.contains('show')) {
                        notesModal.classList.remove('show');
                    }
                    if (cancellationModal.classList.contains('show')) {
                        cancellationModal.classList.remove('show');
                    }
                    if (document.getElementById('inviteModal').style.display === 'flex') {
                        closeInviteModal();
                    }
                }
            });
        });

        function viewCancellationReason(sessionId, reason) {
            const modal = document.getElementById('cancellationModal');
            const content = document.getElementById('cancellationReasonContent');
            
            // If we have a direct cancellation reason, use it
            if (reason && reason.trim() !== '') {
                content.textContent = reason;
                modal.classList.add('show');
                return;
            }
            
            // Otherwise, fetch from notes to check for cancellation reason
            fetch('get_appointment_notes.php?appointment_id=' + sessionId)
                .then(r=>safeParseJSON(r))
                .then(data => {
                    if (data.success && data.notes) {
                        // Look for cancellation reason in notes
                        const notes = data.notes;
                        const cancelMatch = notes.match(/\[Cancellation Reason\]\s*(.+)/);
                        if (cancelMatch) {
                            content.textContent = cancelMatch[1].trim();
                        } else {
                            content.textContent = 'No cancellation reason provided by the client.';
                        }
                    } else {
                        content.textContent = 'No cancellation reason provided by the client.';
                    }
                    modal.classList.add('show');
                })
                .catch(error => {
                    console.error('Error fetching notes:', error);
                    content.textContent = 'No cancellation reason provided by the client.';
                    modal.classList.add('show');
                });
        }

        function closeCancellationModal() {
            document.getElementById('cancellationModal').classList.remove('show');
        }

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

        // Mobile menu functions
        function toggleMobileMenu() {
            console.log('Toggle mobile menu called');
            const sidebar = document.querySelector('.dbSidebar');
            const overlay = document.getElementById('mobileMenuOverlay');
            const menuBtn = document.getElementById('mobileMenuBtn');
            const body = document.body;
            
            if (sidebar && overlay && menuBtn) {
                const isOpen = sidebar.classList.contains('mobile-open');
                
                if (isOpen) {
                    closeMobileMenu();
                } else {
                    openMobileMenu();
                }
            } else {
                console.error('Required elements not found:', {
                    sidebar: !!sidebar,
                    overlay: !!overlay,
                    menuBtn: !!menuBtn
                });
            }
        }

        function openMobileMenu() {
            console.log('Opening mobile menu');
            const sidebar = document.querySelector('.dbSidebar');
            const overlay = document.getElementById('mobileMenuOverlay');
            const menuBtn = document.getElementById('mobileMenuBtn');
            const body = document.body;
            
            if (sidebar && overlay && menuBtn) {
                sidebar.classList.add('mobile-open');
                overlay.classList.add('active');
                menuBtn.classList.add('active');
                body.classList.add('mobile-menu-open');
                console.log('Mobile menu opened');
            }
        }

        function closeMobileMenu() {
            console.log('Closing mobile menu');
            const sidebar = document.querySelector('.dbSidebar');
            const overlay = document.getElementById('mobileMenuOverlay');
            const menuBtn = document.getElementById('mobileMenuBtn');
            const body = document.body;
            
            if (sidebar && overlay && menuBtn) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
                menuBtn.classList.remove('active');
                body.classList.remove('mobile-menu-open');
                console.log('Mobile menu closed');
            }
        }

        // Mobile-specific enhancements
        if (window.innerWidth <= 768) {
            // Add mobile animations to appointment cards
            const appointmentCards = document.querySelectorAll('.appointment-card');
            const cardObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry, index) => {
                    if (entry.isIntersecting) {
                        setTimeout(() => {
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }, index * 100);
                    }
                });
            }, { threshold: 0.1 });

            appointmentCards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                cardObserver.observe(card);
            });

            // Add mobile button press animations
            const actionButtons = document.querySelectorAll('.action-button');
            actionButtons.forEach(button => {
                button.addEventListener('touchstart', function() {
                    this.classList.add('mobile-button-press');
                });
                
                button.addEventListener('touchend', function() {
                    setTimeout(() => {
                        this.classList.remove('mobile-button-press');
                    }, 200);
                });
            });

            // Add mobile loading states
            const buttonsWithLoading = document.querySelectorAll('.join-button, .complete-button, .save-link-button');
            buttonsWithLoading.forEach(button => {
                button.addEventListener('click', function() {
                    const originalText = this.innerHTML;
                    this.innerHTML = '⏳ Loading...';
                    this.disabled = true;
                    this.classList.add('mobile-loading');
                    
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.disabled = false;
                        this.classList.remove('mobile-loading');
                    }, 2000);
                });
            });

            // Add mobile swipe gestures for tabs
            let startX = 0;
            let endX = 0;
            
            document.addEventListener('touchstart', function(e) {
                startX = e.touches[0].clientX;
            });
            
            document.addEventListener('touchend', function(e) {
                endX = e.changedTouches[0].clientX;
                const diffX = startX - endX;
                
                // Swipe left to go to next tab
                if (Math.abs(diffX) > 50 && diffX > 0) {
                    if (currentStatus === 'scheduled' && tabCompleted) {
                        tabCompleted.click();
                    } else if (currentStatus === 'completed' && tabCancelled) {
                        tabCancelled.click();
                    }
                }
                
                // Swipe right to go to previous tab
                if (Math.abs(diffX) > 50 && diffX < 0) {
                    if (currentStatus === 'cancelled' && tabCompleted) {
                        tabCompleted.click();
                    } else if (currentStatus === 'completed' && tabScheduled) {
                        tabScheduled.click();
                    }
                }
            });

            // Add mobile pull-to-refresh
            let startY = 0;
            let currentY = 0;
            let isPulling = false;
            let pullDistance = 0;
            
            const refreshIndicator = document.createElement('div');
            refreshIndicator.className = 'pull-refresh-indicator';
            refreshIndicator.innerHTML = '⬇️ Pull to refresh';
            refreshIndicator.style.cssText = `
                position: fixed;
                top: -50px;
                left: 50%;
                transform: translateX(-50%);
                background: var(--primary-color);
                color: white;
                padding: 10px 20px;
                border-radius: 0 0 20px 20px;
                font-size: 14px;
                font-weight: 600;
                z-index: 1000;
                transition: top 0.3s ease;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            `;
            document.body.appendChild(refreshIndicator);
            
            document.addEventListener('touchstart', function(e) {
                if (window.scrollY === 0) {
                    startY = e.touches[0].clientY;
                    isPulling = true;
                }
            });
            
            document.addEventListener('touchmove', function(e) {
                if (isPulling && window.scrollY === 0) {
                    currentY = e.touches[0].clientY;
                    pullDistance = currentY - startY;
                    
                    if (pullDistance > 0) {
                        e.preventDefault();
                        refreshIndicator.style.top = Math.min(pullDistance - 50, 0) + 'px';
                        
                        if (pullDistance > 100) {
                            refreshIndicator.innerHTML = '⬆️ Release to refresh';
                            refreshIndicator.style.background = '#28a745';
                        } else {
                            refreshIndicator.innerHTML = '⬇️ Pull to refresh';
                            refreshIndicator.style.background = 'var(--primary-color)';
                        }
                    }
                }
            });
            
            document.addEventListener('touchend', function(e) {
                if (isPulling && pullDistance > 100) {
                    refreshIndicator.innerHTML = '🔄 Refreshing...';
                    refreshIndicator.style.background = '#ffc107';
                    refreshIndicator.style.top = '0px';
                    
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    refreshIndicator.style.top = '-50px';
                }
                
                isPulling = false;
                pullDistance = 0;
            });

            // Add mobile haptic feedback
            if ('vibrate' in navigator) {
                actionButtons.forEach(button => {
                    button.addEventListener('touchstart', function() {
                        navigator.vibrate(10);
                    });
                });
            }
        }
    </script>
</body>
</html> 