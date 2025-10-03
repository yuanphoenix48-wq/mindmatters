<?php
session_start();
date_default_timezone_set('Asia/Manila');
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'connect.php';

$userId = $_SESSION['user_id'];
$sql = "SELECT profile_picture, role, first_name, last_name, gender FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$profilePicture = $user['profile_picture'] ?? ($user['gender'] === 'female' ? 'images/profile/default_images/female_gender.png' : 'images/profile/default_images/male_gender.png');
$userRole = $user['role'];
$userFirstName = $user['first_name'];
$userLastName = $user['last_name'];

// Redirect admin users to admin dashboard
if ($userRole === 'admin') {
    header('Location: admin_dashboard.php');
    exit();
}

// Fetch forum posts with user information
$posts = [];
$sqlPosts = "SELECT fp.*, 
             CASE 
                 WHEN fp.is_anonymous = 1 THEN 'Anonymous'
                 ELSE CONCAT(u.first_name, ' ', u.last_name)
             END as author_name,
             CASE 
                 WHEN fp.is_anonymous = 1 THEN 'Anonymous'
                 ELSE u.role
             END as author_role,
             (SELECT COUNT(*) FROM forum_comments WHERE post_id = fp.id) as comment_count,
             (SELECT COUNT(*) FROM forum_likes WHERE post_id = fp.id AND user_id = ?) as user_liked
             FROM forum_posts fp
             JOIN users u ON fp.user_id = u.id
             ORDER BY fp.created_at DESC";
$stmtPosts = $conn->prepare($sqlPosts);
$stmtPosts->bind_param("i", $userId);
$stmtPosts->execute();
$resultPosts = $stmtPosts->get_result();
$posts = $resultPosts->fetch_all(MYSQLI_ASSOC);
$stmtPosts->close();

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mind Matters - Community Forum</title>
    <link rel="stylesheet" href="styles/global.css">
    <link rel="stylesheet" href="styles/dashboard.css">
    <link rel="stylesheet" href="styles/community_forum.css">
    <link rel="stylesheet" href="styles/notifications.css">
    <link rel="stylesheet" href="styles/mobile.css">
    <script src="js/notifications.js"></script>
    <script src="js/mobile.js"></script>
    <style>
        /* Hide mobile header on desktop by default */
        .mobile-header {
            display: none;
        }

        /* Mobile header positioning */
        @media (max-width: 768px) {
            .mobile-header {
                display: block !important;
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                width: 100% !important;
                z-index: 1000 !important;
                background: linear-gradient(135deg, #1D5D9B 0%, #14487a 100%) !important;
                color: white !important;
                padding: 1rem !important;
                /* removed extra left padding since button is inside header */
                padding-right: 1rem !important;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1) !important;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
                box-sizing: border-box !important;
            }
            
            .mobile-header-content {
                display: grid !important;
                grid-template-columns: 44px 1fr auto !important;
                align-items: center !important;
                width: 100% !important;
                max-width: 100% !important;
                overflow: hidden !important;
                gap: 0.75rem !important;
            }
            
            .mobile-logo {
                font-size: 1.4rem !important;
                font-weight: 700 !important;
                color: var(--white) !important;
                font-family: 'Lora', serif !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                justify-self: center !important;
                text-align: center !important;
            }
            
            .mobile-user-info {
                display: flex !important;
                align-items: center !important;
                gap: 0.75rem !important;
            }
            
            .mobile-user-avatar {
                width: 36px !important;
                height: 36px !important;
                border-radius: 50% !important;
                object-fit: cover !important;
                border: 2px solid rgba(255, 255, 255, 0.3) !important;
            }
            
            .mobile-user-name {
                font-weight: 600 !important;
                color: var(--white) !important;
                font-size: 0.9rem !important;
            }
            
            .mobile-menu-btn { position: relative !important; top: 0 !important; left: 0 !important; z-index: 1 !important; background: var(--primary-color) !important; border: none !important; border-radius: 8px !important; width: 44px !important; height: 44px !important; display: flex !important; align-items: center !important; justify-content: center !important; cursor: pointer !important; box-shadow: 0 4px 12px rgba(0,0,0,0.2) !important; transition: all 0.3s ease !important; padding: 0 !important; outline: none !important; }
            .mobile-menu-btn .hamburger { width: 20px; height: 2px; background: #fff; margin: 2px 0; transition: all 0.3s ease; border-radius: 1px; }
            .mobile-menu-btn.active .hamburger:nth-child(1) { transform: rotate(45deg) translate(5px, 5px); }
            .mobile-menu-btn.active .hamburger:nth-child(2) { opacity: 0; }
            .mobile-menu-btn.active .hamburger:nth-child(3) { transform: rotate(-45deg) translate(7px, -6px); }
            
            .mobile-menu-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 9999;
                opacity: 0;
                transition: opacity 0.3s ease;
            }
            
            .mobile-menu-overlay.active {
                display: block;
                opacity: 1;
            }
            
            .dbSidebar {
                transform: translateX(-100%);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                width: 280px;
                z-index: 10001;
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                overflow-y: auto;
            }
            
            .dbSidebar.mobile-open {
                transform: translateX(0);
            }
            
            .mobile-menu-open .dbSidebar {
                transform: translateX(0);
            }
            
            .mobile-menu-open {
                overflow: hidden;
            }
            
            .mobile-menu-open .dbMainContent {
                pointer-events: none;
            }

            /* Add top padding to main content to account for fixed header */
            .dbMainContent {
                padding-top: 80px !important;
            }
            
            .forum-container {
                padding: 1rem !important;
            }
            
        }

        /* New Post Modal Styles */
        .post-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .post-modal.show {
            display: flex;
            opacity: 1;
        }

        .post-modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }

        .post-modal-content {
            position: relative;
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            margin: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }

        .post-modal.show .post-modal-content {
            transform: scale(1);
        }

        .post-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
            background: linear-gradient(135deg, #1D5D9B, #14487a);
            color: white;
            border-radius: 12px 12px 0 0;
        }

        .post-modal-header h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            color: #ffffff !important;
        }

        .post-modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .post-modal-close:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .post-modal-body {
            padding: 24px;
            max-height: 60vh;
            overflow-y: auto;
        }

        .form-field {
            margin-bottom: 20px;
        }

        .form-field label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }

        .form-field input,
        .form-field select,
        .form-field textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }

        .form-field input:focus,
        .form-field select:focus,
        .form-field textarea:focus {
            outline: none;
            border-color: #1D5D9B;
            box-shadow: 0 0 0 3px rgba(29, 93, 155, 0.1);
        }

        .form-field textarea {
            resize: vertical;
            min-height: 120px;
        }

        .checkbox-field {
            margin-bottom: 24px;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-weight: 500;
        }

        .checkbox-label input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
            transform: scale(1.2);
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .btn-cancel,
        .btn-submit {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            font-size: 16px;
        }

        .btn-cancel {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .btn-cancel:hover {
            background: #e5e7eb;
        }

        .btn-submit {
            background: linear-gradient(135deg, #1D5D9B, #14487a);
            color: white;
            box-shadow: 0 4px 12px rgba(29, 93, 155, 0.3);
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(29, 93, 155, 0.4);
        }

        /* Mobile Styles for New Modal */
        @media (max-width: 768px) {
            .post-modal-content {
                width: 95%;
                margin: 20px auto;
                max-height: 85vh;
            }

            .post-modal-header {
                padding: 16px 20px;
            }

            .post-modal-header h3 {
                font-size: 1.25rem;
            }

            .post-modal-body {
                padding: 20px;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn-cancel,
            .btn-submit {
                width: 100%;
            }
        }

        /* Logout Confirm Modal (match Messages) */
        #logoutConfirmModal { display: none; position: fixed; z-index: 3000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        #logoutConfirmModal.show { display: flex; }
        #logoutConfirmModal .modal-content { width: 90%; max-width: 460px; border-radius: 12px; background: #ffffff; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
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

        /* Enhanced Confirm Modal styling */
        #confirmModal {
            display: none;
            position: fixed;
            z-index: 11060;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        #confirmModal.show {
            display: flex;
            opacity: 1;
        }
        
        #confirmModal .modal-content {
            width: 90%;
            max-width: 480px;
            border-radius: 16px;
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.2), 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.8);
            transform: scale(0.9) translateY(-20px);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            overflow: hidden;
        }
        
        #confirmModal.show .modal-content {
            transform: scale(1) translateY(0);
        }
        
        #confirmModal .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 24px 28px;
            background: linear-gradient(135deg, #1D5D9B, #14487a);
            color: white;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        #confirmModal .modal-header h3 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 700;
            color: #ffffff !important;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        #confirmModal .modal-header h3::before {
            content: "⚠️";
            font-size: 1.2rem;
        }
        
        #confirmModal .close {
            color: rgba(255, 255, 255, 0.8);
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            border: none;
        }
        
        #confirmModal .close:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            transform: scale(1.1);
        }
        
        #confirmModal .modal-body {
            padding: 32px 28px;
            text-align: center;
        }
        
        #confirmMessage {
            color: #374151;
            font-size: 1.1rem;
            line-height: 1.6;
            margin: 0;
            font-weight: 500;
        }
        
        #confirmModal .modal-actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            padding: 0 28px 28px 28px;
        }
        
        #confirmModal .cancel-btn,
        #confirmModal .submit-btn {
            appearance: none;
            -webkit-appearance: none;
            border: 0;
            border-radius: 12px;
            padding: 14px 28px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            outline: none;
            min-width: 120px;
            position: relative;
            overflow: hidden;
        }
        
        #confirmModal .cancel-btn {
            background: linear-gradient(135deg, #f1f3f5, #e9ecef);
            color: #495057;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        #confirmModal .cancel-btn:hover {
            background: linear-gradient(135deg, #e9ecef, #dee2e6);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }
        
        #confirmModal .cancel-btn:active {
            transform: translateY(0);
        }
        
        #confirmModal .cancel-btn:focus-visible {
            box-shadow: 0 0 0 3px rgba(108, 117, 125, 0.25);
        }
        
        #confirmModal .submit-btn {
            background: linear-gradient(135deg, #1D5D9B, #14487a);
            color: #ffffff;
            box-shadow: 0 6px 20px rgba(29, 93, 155, 0.3);
        }
        
        #confirmModal .submit-btn:hover {
            background: linear-gradient(135deg, #14487a, #0d3a5f);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(29, 93, 155, 0.4);
        }
        
        #confirmModal .submit-btn:active {
            transform: translateY(0);
        }
        
        #confirmModal .submit-btn:focus-visible {
            box-shadow: 0 0 0 3px rgba(29, 93, 155, 0.35);
        }
        
        /* Mobile styles for confirm modal */
        @media (max-width: 768px) {
            #confirmModal .modal-content {
                width: 95%;
                margin: 20px;
                max-width: none;
            }
            
            #confirmModal .modal-header {
                padding: 20px 24px;
            }
            
            #confirmModal .modal-header h3 {
                font-size: 1.2rem;
            }
            
            #confirmModal .modal-body {
                padding: 24px;
            }
            
            #confirmMessage {
                font-size: 1rem;
            }
            
            #confirmModal .modal-actions {
                flex-direction: column;
                gap: 12px;
                padding: 0 24px 24px 24px;
            }
            
            #confirmModal .cancel-btn,
            #confirmModal .submit-btn {
                width: 100%;
                padding: 16px 24px;
                font-size: 1.1rem;
            }
        }
        
        /* Comment Section Styles */
        .comments-section {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .comments-section.show {
            display: block;
        }
        
        .comments-list {
            margin-bottom: 20px;
        }
        
        .comment-item {
            background: white;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .comment-author {
            font-weight: 600;
            color: #1D5D9B;
        }
        
        .comment-date {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .comment-content {
            color: #333;
            line-height: 1.5;
        }
        
        .add-comment {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .comment-form textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            resize: vertical;
            font-family: inherit;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .comment-form textarea:focus {
            outline: none;
            border-color: #1D5D9B;
            box-shadow: 0 0 0 2px rgba(29, 93, 155, 0.1);
        }
        
        .comment-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }
        
        .anonymous-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #6c757d;
        }
        
        .anonymous-checkbox input[type="checkbox"] {
            margin: 0;
        }
        
        .submit-comment-btn {
            background: linear-gradient(135deg, #1D5D9B, #14487a);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .submit-comment-btn:hover {
            background: linear-gradient(135deg, #14487a, #0d3a5f);
            transform: translateY(-1px);
        }
        
        .submit-comment-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Post Action Buttons */
        .post-action-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border: 1px solid #e9ecef;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
            color: #6c757d;
        }
        
        .post-action-btn:hover {
            background: #f8f9fa;
            border-color: #1D5D9B;
            color: #1D5D9B;
        }
        
        .post-action-btn.liked {
            background: #ffe6e6;
            border-color: #ff6b6b;
            color: #ff6b6b;
        }
        
        .post-action-btn.liked .like-icon {
            color: #ff6b6b;
        }
        
        .action-btn.liked {
            background: #ffe6e6;
            border-color: #ff6b6b;
            color: #ff6b6b;
        }
        
        .post-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        /* Action buttons in new posts */
        .action-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border: 1px solid #e9ecef;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 13px;
            color: #6c757d;
        }
        
        .action-btn:hover {
            background: #f8f9fa;
            border-color: #1D5D9B;
            color: #1D5D9B;
        }
        
        /* No comments message */
        .no-comments {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 20px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
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
    <div id="mobileMenuOverlay" class="mobile-menu-overlay" onclick="closeMobileMenu()"></div>

    <div class="dbContainer">
        <div class="dbSidebar">
            <div class="sidebarProfile">
                <img src="<?php echo htmlspecialchars($profilePicture); ?>" alt="Profile Picture" class="defaultPicture" id="profilePic">
                <h1 class="profileName"><?php echo htmlspecialchars($_SESSION['first_name']); ?></h1>
                <p class="userRole"><?php echo ucfirst($userRole); ?></p>
            </div>
            <ul class="sidebarNavList">
                <li class="sidebarNavItem"><a href="dashboard.php" class="sidebarNavLink">Home</a></li>
                <?php if ($userRole === 'client' || $userRole === 'student'): ?>
                    <li class="sidebarNavItem"><a href="my_session.php" class="sidebarNavLink">My Sessions</a></li>
                    <li class="sidebarNavItem"><a href="analytics_dashboard.php" class="sidebarNavLink">Analytics</a></li>
                    <li class="sidebarNavItem"><a href="resources.php" class="sidebarNavLink">Resources and Guide</a></li>
                    <li class="sidebarNavItem"><a href="community_forum.php" class="sidebarNavLink active">Community Forum</a></li>
                    <li class="sidebarNavItem"><a href="therapy_support.php" class="sidebarNavLink">Therapy Support</a></li>
                    <li class="sidebarNavItem"><a href="student_messages.php" class="sidebarNavLink">Messages</a></li>
                <?php else: ?>
                    <li class="sidebarNavItem"><a href="appointments.php" class="sidebarNavLink">Appointments</a></li>
                    <li class="sidebarNavItem"><a href="pending_requests.php" class="sidebarNavLink">Pending Requests</a></li>
                    <li class="sidebarNavItem"><a href="patients.php" class="sidebarNavLink">My Clients</a></li>
                    <li class="sidebarNavItem"><a href="patient_tracking.php" class="sidebarNavLink">Client Tracking</a></li>
                    <li class="sidebarNavItem"><a href="therapy_support.php" class="sidebarNavLink">Therapy Support</a></li>
                    <li class="sidebarNavItem"><a href="community_forum.php" class="sidebarNavLink active">Community Forum</a></li>
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
            <div class="forum-container">
                <div class="forum-header">
                    <h2>Community Forum</h2>
                    <p>Share your thoughts, ask questions, and support each other in a safe, anonymous environment.</p>
                    <button class="new-post-btn" onclick="showPostModal()">Create New Post</button>
                </div>

                

                <!-- Posts List -->
                <div class="posts-container">
                    <?php if (empty($posts)): ?>
                        <div class="no-posts">
                            <p>No posts yet. Be the first to start a conversation!</p>
                        </div>
                    <?php else: ?>
                                         <?php foreach ($posts as $post): ?>
                             <!-- Debug: Post ID: <?php echo $post['id']; ?>, Anonymous: <?php echo var_export($post['is_anonymous'], true); ?> (<?php echo gettype($post['is_anonymous']); ?>), Author: <?php echo $post['author_name']; ?>, Role: <?php echo $post['author_role']; ?> -->
                             <div class="post-card" data-post-id="<?php echo $post['id']; ?>">
                                                                 <div class="post-header">
                                     <div class="post-author">
                                         <span class="author-name"><?php echo htmlspecialchars($post['author_name']); ?></span>
                                         <?php if ($post['is_anonymous'] == 1): ?>
                                             <span class="anonymous-badge">Anonymous</span>
                                         <?php else: ?>
                                             <span class="author-role"><?php echo ucfirst($post['author_role']); ?></span>
                                         <?php endif; ?>
                                     </div>
                                    <div class="post-meta">
                                        <span class="post-category"><?php echo ucfirst(str_replace('_', ' ', $post['category'])); ?></span>
                                        <span class="post-date"><?php echo date('M j, Y g:i A', strtotime($post['created_at'])); ?></span>
                                    </div>
                                </div>
                                
                                <div class="post-content">
                                    <h3 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                                    <p class="post-text"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                                </div>
                                
                                                                 <div class="post-actions">
                                     <button class="post-action-btn like <?php echo ($post['user_liked'] > 0) ? 'liked' : ''; ?>" onclick="toggleLike(<?php echo $post['id']; ?>, 'post')">
                                         <span class="like-icon">&#10084;&#65039;</span>
                                         <span class="like-count"><?php echo $post['likes_count']; ?></span>
                                     </button>
                                    <button class="post-action-btn comment" onclick="toggleComments(<?php echo $post['id']; ?>)">
                                        <span>💬</span>
                                        <span class="comment-count"><?php echo $post['comment_count']; ?></span>
                                    </button>
                                </div>
                                
                                <!-- Comments Section -->
                                <div id="comments-<?php echo $post['id']; ?>" class="comments-section">
                                    <div class="comments-list" id="comments-list-<?php echo $post['id']; ?>">
                                        <!-- Comments will be loaded here -->
                                    </div>
                                    
                                    <div class="add-comment">
                                        <form class="comment-form" onsubmit="submitComment(event, <?php echo $post['id']; ?>)">
                                            <textarea name="content" placeholder="Write a comment..." required rows="3"></textarea>
                                            <div class="comment-actions">
                                                <label class="anonymous-checkbox">
                                                    <input type="checkbox" name="anonymous">
                                                    Comment anonymously
                                                </label>
                                                <button type="submit" class="submit-comment-btn">Post Comment</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- New Post Modal -->
    <div id="createPostModal" class="post-modal">
        <div class="post-modal-overlay" onclick="closePostModal()"></div>
        <div class="post-modal-content">
            <div class="post-modal-header">
                <h3>Create New Post</h3>
                <button class="post-modal-close" onclick="closePostModal()">&times;</button>
            </div>
            <div class="post-modal-body">
                <form id="newPostForm" onsubmit="handlePostSubmit(event)">
                    <div class="form-field">
                        <label for="postTitle">Title</label>
                        <input type="text" id="postTitle" name="title" required maxlength="255" placeholder="Enter your post title">
                    </div>
                    
                    <div class="form-field">
                        <label for="postCategory">Category</label>
                        <select id="postCategory" name="category" required>
                            <option value="general">General</option>
                            <option value="mental_health">Mental Health</option>
                            <option value="therapy">Therapy</option>
                            <option value="support">Support</option>
                            <option value="resources">Resources</option>
                        </select>
                    </div>
                    
                    <div class="form-field">
                        <label for="postContent">Content</label>
                        <textarea id="postContent" name="content" required rows="6" placeholder="Share your thoughts, questions, or experiences..."></textarea>
                    </div>
                    
                    <div class="form-field checkbox-field">
                        <label class="checkbox-label">
                            <input type="checkbox" id="postAnonymous" name="anonymous">
                            <span class="checkmark"></span>
                            Post anonymously
                        </label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn-cancel" onclick="closePostModal()">Cancel</button>
                        <button type="submit" class="btn-submit">Create Post</button>
                    </div>
                </form>
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

    <!-- Custom Confirm Modal -->
    <div id="confirmModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Action</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <p id="confirmMessage"></p>
            </div>
            <div class="modal-actions">
                <button type="button" class="cancel-btn">Cancel</button>
                <button type="button" class="submit-btn" id="confirmOkBtn">OK</button>
            </div>
        </div>
    </div>

    <script>
        let _cfSavedScrollY = 0;
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



        // New Post Modal Functions
        function showPostModal() {
            const modal = document.getElementById('createPostModal');
            if (modal) {
                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
                // Close mobile menu if open
                closeMobileMenu();
                // Focus on title input
                setTimeout(() => {
                    const titleInput = document.getElementById('postTitle');
                    if (titleInput) titleInput.focus();
                }, 100);
            }
        }

        function closePostModal() {
            const modal = document.getElementById('createPostModal');
            if (modal) {
                modal.classList.remove('show');
                document.body.style.overflow = '';
                // Reset form
                const form = document.getElementById('newPostForm');
                if (form) form.reset();
            }
        }

        function handlePostSubmit(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const postData = {
                title: formData.get('title'),
                category: formData.get('category'),
                content: formData.get('content'),
                anonymous: formData.get('anonymous') === 'on'
            };

            const message = postData.anonymous
              ? 'Post anonymously? Your name will not be shown.'
              : 'You are about to post with your name visible. Proceed?';

            showCustomConfirm(message).then((ok) => {
                if (ok) {
                    createPost(postData);
                }
            });
        }

        // Notification System
        function showToast(message, type = 'info') {
            // Remove any existing toasts
            const existingToasts = document.querySelectorAll('.toast');
            existingToasts.forEach(toast => toast.remove());
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <div class="toast-content">
                    <span class="toast-icon">${type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️'}</span>
                    <span class="toast-message">${message}</span>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            `;
            
            // Add toast styles
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
                color: white !important;
                padding: 16px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                z-index: 10000;
                display: flex;
                align-items: center;
                gap: 12px;
                min-width: 300px;
                max-width: 400px;
                animation: slideInRight 0.3s ease;
                font-weight: 500;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            `;
            
            // Add animation styles
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOutRight {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
                .toast-content { 
                    display: flex; 
                    align-items: center; 
                    gap: 8px; 
                    flex: 1; 
                    color: white !important;
                }
                .toast-icon { 
                    font-size: 18px; 
                    color: white !important;
                }
                .toast-message { 
                    flex: 1; 
                    color: white !important;
                    font-size: 14px;
                    line-height: 1.4;
                }
                .toast-close { 
                    background: none; 
                    border: none; 
                    color: white !important; 
                    font-size: 20px; 
                    cursor: pointer; 
                    padding: 0; 
                    width: 24px; 
                    height: 24px; 
                    display: flex; 
                    align-items: center; 
                    justify-content: center;
                    border-radius: 50%;
                    transition: background-color 0.2s;
                }
                .toast-close:hover { 
                    background: rgba(255, 255, 255, 0.2); 
                }
            `;
            document.head.appendChild(style);
            
            // Add to page
            document.body.appendChild(toast);
            
            // Auto remove after 15 seconds (significantly increased)
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.style.animation = 'slideOutRight 0.5s ease';
                    setTimeout(() => {
                        if (toast.parentElement) {
                            toast.remove();
                        }
                    }, 500);
                }
            }, 15000);
        }

        // Enhanced createPost function with better error handling
        function createPost(postData) {
            console.log('Creating post:', postData);
            
            // Show loading state
            const submitBtn = document.querySelector('#newPostForm .btn-submit');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Creating...';
            submitBtn.disabled = true;
            
            fetch('forum_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'create_post',
                    ...postData
                })
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                return response.text().then(text => {
                    console.log('Raw response text:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Failed to parse JSON:', e);
                        console.error('Response text:', text);
                        throw new Error('Invalid JSON response');
                    }
                });
            })
            .then(data => {
                console.log('Parsed response data:', data);
                console.log('Data type:', typeof data);
                console.log('Success value:', data.success);
                console.log('Success type:', typeof data.success);
                
                // Reset button state
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
                
                if (data && data.success === true) {
                    console.log('Post created successfully!');
                    showToast('Post created successfully!', 'success');
                    closePostModal();
                    // Add the new post to the page with real post ID
                    addNewPostToPage(postData, data.post_id);
                } else {
                    console.log('Post creation failed:', data);
                    const errorMessage = data && data.message ? data.message : 'Failed to create post';
                    showToast('Error: ' + errorMessage, 'error');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                
                // Reset button state
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
                
                showToast('An error occurred while creating the post: ' + error.message, 'error');
            });
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modal = document.getElementById('createPostModal');
                if (modal && modal.classList.contains('show')) {
                    closePostModal();
                } else {
                    closeMobileMenu();
                }
            }
        });

        // Mobile menu event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Close mobile menu when clicking on sidebar links
            const sidebarLinks = document.querySelectorAll('.sidebarNavLink');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function() {
                    closeMobileMenu();
                });
            });
        });

        // Toggle Comments Function
        function toggleComments(postId) {
            const commentsSection = document.getElementById('comments-' + postId);
            if (commentsSection) {
                if (commentsSection.classList.contains('show')) {
                    commentsSection.classList.remove('show');
                    commentsSection.style.display = 'none';
                } else {
                    commentsSection.classList.add('show');
                    commentsSection.style.display = 'block';
                    // Always load comments when opening
                    loadComments(postId);
                }
            }
        }

        // Load Comments Function
        function loadComments(postId) {
            const commentsList = document.getElementById('comments-list-' + postId);
            if (commentsList) {
                // Always load comments (remove the length check)
                console.log('Loading comments for post:', postId);
                console.log('Post ID type:', typeof postId);
                console.log('Post ID value:', postId);
                
                fetch('forum_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'get_comments',
                        post_id: postId
                    })
                })
                .then(response => {
                    console.log('Comments response status:', response.status);
                    return response.text().then(text => {
                        console.log('Raw comments response:', text);
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('Failed to parse comments JSON:', e);
                            throw new Error('Invalid JSON response');
                        }
                    });
                })
                .then(data => {
                    console.log('Comments data received:', data);
                    if (data && data.success) {
                        displayComments(postId, data.comments);
                    } else {
                        console.error('Failed to load comments:', data);
                        commentsList.innerHTML = '<p class="no-comments">Error loading comments. Please try again.</p>';
                    }
                })
                .catch(error => {
                    console.error('Error loading comments:', error);
                    commentsList.innerHTML = '<p class="no-comments">Error loading comments. Please try again.</p>';
                });
            }
        }

        function displayComments(postId, comments) {
            const commentsList = document.getElementById(`comments-list-${postId}`);
            commentsList.innerHTML = '';
            
            if (comments.length === 0) {
                commentsList.innerHTML = '<p class="no-comments">No comments yet. Be the first to comment!</p>';
                return;
            }
            
            comments.forEach(comment => {
                const commentElement = document.createElement('div');
                commentElement.className = 'comment-item';
                commentElement.setAttribute('data-comment-id', comment.id);
                commentElement.innerHTML = `
                    <div class="comment-header">
                        <span class="comment-author">${comment.author_name}</span>
                        ${comment.is_anonymous == 1 ? '<span class="anonymous-badge">Anonymous</span>' : `<span class="comment-role">${comment.author_role}</span>`}
                        <span class="comment-date">${comment.created_at}</span>
                    </div>
                    <div class="comment-content">
                        <p>${comment.content}</p>
                    </div>
                    <div class="comment-actions">
                        <button class="action-btn like-btn ${comment.user_liked > 0 ? 'liked' : ''}" onclick="toggleLike(${comment.id}, 'comment')">
                            <span class="like-icon">&#10084;&#65039;</span>
                            <span class="like-count">${comment.likes_count}</span>
                        </button>
                    </div>
                `;
                commentsList.appendChild(commentElement);
            });
        }

        async function submitComment(event, postId) {
            event.preventDefault();
            
            const form = event.target;
            const formData = new FormData(form);
            const commentData = {
                post_id: postId,
                content: formData.get('content'),
                anonymous: formData.get('anonymous') === 'on'
            };

            // Show confirmation dialog
            const confirmed = await showCustomConfirm('Are you sure you want to post this comment?');
            if (!confirmed) {
                return;
            }

            // Show loading state
            const submitBtn = form.querySelector('.submit-comment-btn');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Posting...';
            submitBtn.disabled = true;

            fetch('forum_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'create_comment',
                    ...commentData
                })
            })
            .then(response => {
                console.log('Comment response status:', response.status);
                return response.text().then(text => {
                    console.log('Raw comment response text:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Failed to parse comment JSON:', e);
                        throw new Error('Invalid JSON response');
                    }
                });
            })
            .then(data => {
                console.log('Comment response data:', data);
                
                // Reset button state
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
                
                if (data && data.success === true) {
                    showToast('Comment posted successfully!', 'success');
                    form.reset();
                    
                    // Reload comments to show the new comment immediately
                    setTimeout(() => {
                        loadComments(postId);
                    }, 500); // Small delay to ensure server has processed the comment
                    
                    // Update comment count
                    const commentCount = document.querySelector(`[data-post-id="${postId}"] .comment-count`);
                    if (commentCount) {
                        commentCount.textContent = parseInt(commentCount.textContent) + 1;
                    }
                    
                    // Keep comment section open so user can see their comment
                    // Don't auto-hide the section
                } else {
                    const errorMessage = data && data.message ? data.message : 'Failed to post comment';
                    showToast('Error: ' + errorMessage, 'error');
                }
            })
            .catch(error => {
                console.error('Comment error:', error);
                
                // Reset button state
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
                
                showToast('An error occurred while posting the comment: ' + error.message, 'error');
            });
        }

        function submitPost(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const postData = {
                title: formData.get('title'),
                category: formData.get('category'),
                content: formData.get('content'),
                anonymous: formData.get('anonymous') === 'on'
            };

            const message = postData.anonymous
              ? 'Post anonymously? Your name will not be shown.'
              : 'You are about to post with your name visible. Proceed?';

            showCustomConfirm(message).then((ok)=>{ if(ok){ doCreatePost(postData); } });
        }

        function doCreatePost(postData){
            fetch('forum_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'create_post',
                    ...postData
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Post created successfully!', 'success');
                    hideNewPostForm();
                    // Add the new post to the top of the posts container without refreshing
                    addNewPostToPage(postData);
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred while creating the post.', 'error');
            });
        }



        function toggleLike(itemId, type) {
            console.log('Toggling like for:', itemId, type);
            
            fetch('forum_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'toggle_like',
                    item_id: itemId,
                    type: type
                })
            })
            .then(response => {
                console.log('Like response status:', response.status);
                return response.text().then(text => {
                    console.log('Raw like response:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Failed to parse like JSON:', e);
                        throw new Error('Invalid JSON response');
                    }
                });
            })
            .then(data => {
                console.log('Like response data:', data);
                if (data && data.success) {
                    // Update like count without refreshing
                    updateLikeCount(itemId, type);
                    // Show notification
                    showToast(data.message || 'Like updated successfully!', 'success');
                } else {
                    const errorMessage = data && data.message ? data.message : 'Failed to update like';
                    showToast('Error: ' + errorMessage, 'error');
                }
            })
            .catch(error => {
                console.error('Like error:', error);
                showToast('An error occurred while updating like: ' + error.message, 'error');
            });
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

        function addNewPostToPage(postData, realPostId) {
            const postsContainer = document.querySelector('.posts-container');
            if (!postsContainer) {
                console.error('Posts container not found');
                return;
            }
            
            const noPostsDiv = postsContainer.querySelector('.no-posts');
            
            // Remove "no posts" message if it exists
            if (noPostsDiv) {
                noPostsDiv.remove();
            }
            
            // If this is the first post, clear the container
            if (postsContainer.children.length === 0 || (postsContainer.children.length === 1 && noPostsDiv)) {
                postsContainer.innerHTML = '';
            }
            
            // Create new post element with the real post ID from database
            const newPostElement = document.createElement('div');
            newPostElement.className = 'post-card';
            newPostElement.setAttribute('data-post-id', realPostId);
            
            const currentDate = new Date().toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
            
            // Get user info from global variables (set in PHP)
            const userName = '<?php echo htmlspecialchars($userFirstName . ' ' . $userLastName); ?>';
            const userRole = '<?php echo ucfirst($userRole); ?>';
            
            newPostElement.innerHTML = `
                <div class="post-header">
                    <div class="post-author">
                        <span class="author-name">${postData.anonymous ? 'Anonymous' : userName}</span>
                        ${postData.anonymous ? '<span class="anonymous-badge">Anonymous</span>' : `<span class="author-role">${userRole}</span>`}
                    </div>
                    <div class="post-meta">
                        <span class="post-category">${postData.category.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</span>
                        <span class="post-date">${currentDate}</span>
                    </div>
                </div>
                
                <div class="post-content">
                    <h3 class="post-title">${postData.title}</h3>
                    <p class="post-text">${postData.content.replace(/\n/g, '<br>')}</p>
                </div>
                
                <div class="post-actions">
                    <button class="post-action-btn like" onclick="toggleLike(${realPostId}, 'post')">
                        <span class="like-icon">&#10084;&#65039;</span>
                        <span class="like-count">0</span>
                    </button>
                    <button class="post-action-btn comment" onclick="toggleComments(${realPostId})">
                        <span>💬</span>
                        <span class="comment-count">0</span>
                    </button>
                </div>
                
                <!-- Comments Section -->
                <div id="comments-${realPostId}" class="comments-section">
                    <div class="comments-list" id="comments-list-${realPostId}">
                        <!-- Comments will be loaded here -->
                    </div>
                    
                    <div class="add-comment">
                        <form class="comment-form" onsubmit="submitComment(event, ${realPostId})">
                            <textarea name="content" placeholder="Write a comment..." required rows="3"></textarea>
                            <div class="comment-actions">
                                <label class="anonymous-checkbox">
                                    <input type="checkbox" name="anonymous">
                                    Comment anonymously
                                </label>
                                <button type="submit" class="submit-comment-btn">Post Comment</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            
            // Insert at the top of posts container
            postsContainer.insertBefore(newPostElement, postsContainer.firstChild);
            
            // Reset the form
            const form = document.getElementById('newPostForm');
            if (form) {
                form.reset();
            } else {
                console.warn('Form not found for reset');
            }
        }

        function updateLikeCount(itemId, type) {
            console.log('Updating like count for:', itemId, type);
            
            let likeCountElement;
            let likeButton;
            
            if (type === 'post') {
                // Try both selectors for existing posts and new posts
                likeCountElement = document.querySelector(`[data-post-id="${itemId}"] .like-count`);
                likeButton = document.querySelector(`[data-post-id="${itemId}"] .like-btn`) || 
                           document.querySelector(`[data-post-id="${itemId}"] .post-action-btn.like`);
                
                // If not found with data-post-id, try direct post ID (for existing posts)
                if (!likeCountElement || !likeButton) {
                    likeCountElement = document.querySelector(`button[onclick*="toggleLike(${itemId}, 'post')"] .like-count`);
                    likeButton = document.querySelector(`button[onclick*="toggleLike(${itemId}, 'post')"]`);
                }
            } else if (type === 'comment') {
                likeCountElement = document.querySelector(`[data-comment-id="${itemId}"] .like-count`);
                likeButton = document.querySelector(`[data-comment-id="${itemId}"] .like-btn`);
            }
            
            console.log('Found elements:', {
                likeCountElement: !!likeCountElement,
                likeButton: !!likeButton,
                currentCount: likeCountElement ? likeCountElement.textContent : 'not found',
                isLiked: likeButton ? likeButton.classList.contains('liked') : 'not found'
            });
            
            if (likeCountElement && likeButton) {
                const currentCount = parseInt(likeCountElement.textContent) || 0;
                
                // Check if the button is already liked (has 'liked' class)
                if (likeButton.classList.contains('liked')) {
                    // Unlike: decrease count and remove 'liked' class
                    likeCountElement.textContent = Math.max(0, currentCount - 1);
                    likeButton.classList.remove('liked');
                    console.log('Unliked - new count:', likeCountElement.textContent);
                } else {
                    // Like: increase count and add 'liked' class
                    likeCountElement.textContent = currentCount + 1;
                    likeButton.classList.add('liked');
                    console.log('Liked - new count:', likeCountElement.textContent);
                }
            } else {
                console.error('Could not find like elements for:', itemId, type);
                console.log('Available buttons:', document.querySelectorAll('button[onclick*="toggleLike"]'));
            }
        }
    </script>
    <script>
        function showCustomConfirm(message){
            return new Promise((resolve)=>{
                console.log('showCustomConfirm called with message:', message);
                const modal = document.getElementById('confirmModal');
                const msg = document.getElementById('confirmMessage');
                const okBtn = document.getElementById('confirmOkBtn');
                const cancelBtn = modal.querySelector('.cancel-btn');
                const closeBtn = modal.querySelector('.close');
                
                if (!modal || !msg || !okBtn || !cancelBtn || !closeBtn) {
                    console.error('Modal elements not found:', {
                        modal: !!modal, 
                        msg: !!msg, 
                        okBtn: !!okBtn, 
                        cancelBtn: !!cancelBtn, 
                        closeBtn: !!closeBtn
                    });
                    resolve(false);
                    return;
                }
                
                msg.textContent = message;
                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
                
                const onOk = (e) => {
                    console.log('OK button clicked');
                    e.preventDefault();
                    e.stopPropagation();
                    cleanup();
                    resolve(true);
                };
                
                const onCancel = (e) => {
                    console.log('Cancel action triggered');
                    if (e) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    cleanup();
                    resolve(false);
                };
                
                function cleanup(){
                    console.log('Cleaning up modal');
                    modal.classList.remove('show');
                    document.body.style.overflow = '';
                    okBtn.removeEventListener('click', onOk);
                    cancelBtn.removeEventListener('click', onCancel);
                    closeBtn.removeEventListener('click', onCancel);
                    modal.removeEventListener('click', onBackdrop);
                    document.removeEventListener('keydown', onEsc);
                    window.closeConfirm = null;
                }
                
                function onBackdrop(e){ 
                    if(e.target === modal){ 
                        console.log('Backdrop clicked');
                        onCancel(e); 
                    } 
                }
                
                function onEsc(e){ 
                    if(e.key === 'Escape'){ 
                        console.log('Escape key pressed');
                        onCancel(e); 
                    } 
                }
                
                // Remove any existing listeners first
                okBtn.removeEventListener('click', onOk);
                cancelBtn.removeEventListener('click', onCancel);
                closeBtn.removeEventListener('click', onCancel);
                modal.removeEventListener('click', onBackdrop);
                document.removeEventListener('keydown', onEsc);
                
                // Add new listeners
                okBtn.addEventListener('click', onOk);
                cancelBtn.addEventListener('click', onCancel);
                closeBtn.addEventListener('click', onCancel);
                modal.addEventListener('click', onBackdrop);
                document.addEventListener('keydown', onEsc);
                window.closeConfirm = onCancel;
                
                console.log('Event listeners attached');
            });
        }
    </script>
</body>
</html>
