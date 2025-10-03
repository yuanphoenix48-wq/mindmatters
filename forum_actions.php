<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

require_once 'connect.php';

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

// Debug logging
error_log("DEBUG forum_actions.php - Action: " . $action);
error_log("DEBUG forum_actions.php - Input: " . json_encode($input));
error_log("DEBUG forum_actions.php - User ID: " . $userId);

try {
    switch ($action) {
        case 'create_post':
            createPost($conn, $userId, $input);
            break;
            
        case 'create_comment':
            createComment($conn, $userId, $input);
            break;
            
        case 'get_comments':
            $postId = $input['post_id'] ?? $_GET['post_id'] ?? 0;
            getComments($conn, $postId);
            break;
            
        case 'toggle_like':
            toggleLike($conn, $userId, $input);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();

function createPost($conn, $userId, $input) {
    $title = trim($input['title'] ?? '');
    $content = trim($input['content'] ?? '');
    $category = $input['category'] ?? 'general';
    $isAnonymous = $input['anonymous'] ?? false;
    
    // Debug: Log the received values
    error_log("DEBUG createPost - title: " . $title);
    error_log("DEBUG createPost - content: " . $content);
    error_log("DEBUG createPost - category: " . $category);
    error_log("DEBUG createPost - anonymous: " . var_export($isAnonymous, true));
    error_log("DEBUG createPost - anonymous type: " . gettype($isAnonymous));
    
    if (empty($title) || empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Title and content are required']);
        return;
    }
    
    if (strlen($title) > 255) {
        echo json_encode(['success' => false, 'message' => 'Title is too long']);
        return;
    }
    
    $validCategories = ['general', 'mental_health', 'therapy', 'support', 'resources'];
    if (!in_array($category, $validCategories)) {
        echo json_encode(['success' => false, 'message' => 'Invalid category']);
        return;
    }
    
    // Convert boolean to integer for MySQL
    $isAnonymousInt = $isAnonymous ? 1 : 0;
    error_log("DEBUG createPost - anonymous converted to int: " . $isAnonymousInt);
    
    $sql = "INSERT INTO forum_posts (user_id, title, content, is_anonymous, category) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issis", $userId, $title, $content, $isAnonymousInt, $category);
    
    if ($stmt->execute()) {
        error_log("DEBUG createPost - Post created successfully");
        $postId = $conn->insert_id; // Get the ID of the newly created post
        echo json_encode(['success' => true, 'message' => 'Post created successfully', 'post_id' => $postId]);
    } else {
        error_log("DEBUG createPost - Failed to create post: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Failed to create post: ' . $stmt->error]);
    }
    
    $stmt->close();
}

function createComment($conn, $userId, $input) {
    $postId = $input['post_id'] ?? 0;
    $content = trim($input['content'] ?? '');
    $isAnonymous = $input['anonymous'] ?? false;
    
    // Debug: Log the received values
    error_log("DEBUG createComment - post_id: " . $postId);
    error_log("DEBUG createComment - content: " . $content);
    error_log("DEBUG createComment - anonymous: " . var_export($isAnonymous, true));
    error_log("DEBUG createComment - anonymous type: " . gettype($isAnonymous));
    
    if (empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Comment content is required']);
        return;
    }
    
    if ($postId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
        return;
    }
    
    // Verify post exists
    $checkSql = "SELECT id FROM forum_posts WHERE id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("i", $postId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        $checkStmt->close();
        return;
    }
    $checkStmt->close();
    
    // Convert boolean to integer for MySQL
    $isAnonymousInt = $isAnonymous ? 1 : 0;
    error_log("DEBUG createComment - anonymous converted to int: " . $isAnonymousInt);
    
    $sql = "INSERT INTO forum_comments (post_id, user_id, content, is_anonymous) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisi", $postId, $userId, $content, $isAnonymousInt);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Comment posted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to post comment']);
    }
    
    $stmt->close();
 }

function getComments($conn, $postId) {
    // Debug logging
    error_log("DEBUG getComments - postId: " . $postId);
    error_log("DEBUG getComments - postId type: " . gettype($postId));
    
    if (!$postId || $postId <= 0) {
        error_log("DEBUG getComments - Invalid post ID: " . $postId);
        echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
        return;
    }
    
    // Get current user ID from session
    $userId = $_SESSION['user_id'];
    error_log("DEBUG getComments - userId: " . $userId);
    
    $sql = "SELECT fc.*, 
            CASE 
                WHEN fc.is_anonymous = 1 THEN 'Anonymous'
                ELSE CONCAT(u.first_name, ' ', u.last_name)
            END as author_name,
            CASE 
                WHEN fc.is_anonymous = 1 THEN 'Anonymous'
                ELSE u.role
            END as author_role,
            DATE_FORMAT(fc.created_at, '%M %j, %Y %g:%i %p') as created_at,
            (SELECT COUNT(*) FROM forum_likes WHERE comment_id = fc.id AND user_id = ?) as user_liked
            FROM forum_comments fc
            JOIN users u ON fc.user_id = u.id
            WHERE fc.post_id = ?
            ORDER BY fc.created_at ASC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("DEBUG getComments - Prepare failed: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database prepare failed']);
        return;
    }
    
    $stmt->bind_param("ii", $userId, $postId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = $row;
    }
    
    error_log("DEBUG getComments - Found " . count($comments) . " comments");
    
    echo json_encode(['success' => true, 'comments' => $comments]);
    $stmt->close();
}

function toggleLike($conn, $userId, $input) {
    $itemId = $input['item_id'] ?? 0;
    $type = $input['type'] ?? '';
    
    if ($itemId <= 0 || !in_array($type, ['post', 'comment'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        return;
    }
    
    // Check if like already exists
    $checkSql = "SELECT id FROM forum_likes WHERE user_id = ? AND ";
    $checkSql .= ($type === 'post') ? "post_id = ?" : "comment_id = ?";
    
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("ii", $userId, $itemId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $likeExists = $result->num_rows > 0;
    $checkStmt->close();
    
    if ($likeExists) {
        // Remove like
        $deleteSql = "DELETE FROM forum_likes WHERE user_id = ? AND ";
        $deleteSql .= ($type === 'post') ? "post_id = ?" : "comment_id = ?";
        
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->bind_param("ii", $userId, $itemId);
        $deleteStmt->execute();
        $deleteStmt->close();
        
        // Update like count
        $updateSql = "UPDATE forum_" . ($type === 'post' ? 'posts' : 'comments') . " SET likes_count = likes_count - 1 WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("i", $itemId);
        $updateStmt->execute();
        $updateStmt->close();
        
        echo json_encode(['success' => true, 'message' => 'Like removed']);
    } else {
        // Add like
        $insertSql = "INSERT INTO forum_likes (user_id, " . ($type === 'post' ? 'post_id' : 'comment_id') . ") VALUES (?, ?)";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param("ii", $userId, $itemId);
        $insertStmt->execute();
        $insertStmt->close();
        
        // Update like count
        $updateSql = "UPDATE forum_" . ($type === 'post' ? 'posts' : 'comments') . " SET likes_count = likes_count + 1 WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("i", $itemId);
        $updateStmt->execute();
        $updateStmt->close();
        
        echo json_encode(['success' => true, 'message' => 'Like added']);
    }
}
?>
