<?php
session_start();
date_default_timezone_set('Asia/Manila');
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'connect.php';

$userId = $_SESSION['user_id'];
$sql = "SELECT profile_picture, role, first_name, last_name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$profilePicture = $user['profile_picture'] ?? 'images/profile/default_images/default_profile.png';
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
</head>
<body class="dbBody">
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
                <button type="button" class="logoutButton" onclick="confirmLogout()">Logout</button>
            </div>
        </div>
        
        <div class="dbMainContent">
            <div class="forum-container">
                <div class="forum-header">
                    <h2>Community Forum</h2>
                    <p>Share your thoughts, ask questions, and support each other in a safe, anonymous environment.</p>
                    <button class="new-post-btn" onclick="showNewPostForm()">Create New Post</button>
                </div>

                <!-- New Post Modal -->
                <div id="newPostModal" class="modal" style="display: none;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Create New Post</h3>
                            <span class="close" onclick="hideNewPostForm()">&times;</span>
                        </div>
                        <div class="modal-body">
                            <form id="postForm" onsubmit="submitPost(event)">
                                <div class="form-group">
                                    <label for="postTitle">Title:</label>
                                    <input type="text" id="postTitle" name="title" required maxlength="255" placeholder="Enter your post title">
                                </div>
                                
                                <div class="form-group">
                                    <label for="postCategory">Category:</label>
                                    <select id="postCategory" name="category" required>
                                        <option value="general">General</option>
                                        <option value="mental_health">Mental Health</option>
                                        <option value="therapy">Therapy</option>
                                        <option value="support">Support</option>
                                        <option value="resources">Resources</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="postContent">Content:</label>
                                    <textarea id="postContent" name="content" required rows="6" placeholder="Share your thoughts, questions, or experiences..."></textarea>
                                </div>
                                
                                <div class="form-group checkbox-group">
                                    <label>
                                        <input type="checkbox" id="postAnonymous" name="anonymous">
                                        Post anonymously
                                    </label>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="submit-btn">Create Post</button>
                                    <button type="button" class="cancel-btn" onclick="hideNewPostForm()">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
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
                                         <span class="like-icon">??</span>
                                         <span class="like-count"><?php echo $post['likes_count']; ?></span>
                                     </button>
                                    <button class="post-action-btn comment" onclick="toggleComments(<?php echo $post['id']; ?>)">
                                        <span>??</span>
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

    <script>
        function showNewPostForm() {
            const modal = document.getElementById('newPostModal');
            modal.style.display = 'flex';
            modal.classList.add('show');
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
            document.getElementById('postTitle').focus();
        }

        function hideNewPostForm() {
            const modal = document.getElementById('newPostModal');
            modal.style.display = 'none';
            modal.classList.remove('show');
            document.body.style.overflow = ''; // Restore scrolling
            document.getElementById('postForm').reset();
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('newPostModal');
            if (event.target === modal) {
                hideNewPostForm();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modal = document.getElementById('newPostModal');
                if (modal.style.display === 'flex') {
                    hideNewPostForm();
                }
            }
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
                    // Load comments if not already loaded
                    loadComments(postId);
                }
            }
        }

        // Load Comments Function
        function loadComments(postId) {
            const commentsList = document.getElementById('comments-list-' + postId);
            if (commentsList && commentsList.children.length === 0) {
                // Load comments via AJAX
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
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        commentsList.innerHTML = data.comments;
                    }
                })
                .catch(error => {
                    console.error('Error loading comments:', error);
                });
            }
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

            if (typeof showConfirm === 'function') {
                showConfirm(message).then((ok)=>{ if(ok){ doCreatePost(postData); } });
            } else {
                if (!confirm(message)) { return; }
                doCreatePost(postData);
            }
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

        function toggleComments(postId) {
            const commentsSection = document.getElementById(`comments-${postId}`);
            const isVisible = commentsSection.style.display !== 'none';
            
            if (!isVisible) {
                commentsSection.style.display = 'block';
                loadComments(postId);
            } else {
                commentsSection.style.display = 'none';
            }
        }

        function loadComments(postId) {
            fetch(`forum_actions.php?action=get_comments&post_id=${postId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayComments(postId, data.comments);
                }
            })
            .catch(error => {
                console.error('Error loading comments:', error);
            });
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
                             <span class="like-icon">??</span>
                             <span class="like-count">${comment.likes_count}</span>
                         </button>
                     </div>
                 `;
                 commentsList.appendChild(commentElement);
             });
        }

        function submitComment(event, postId) {
            event.preventDefault();
            
            const form = event.target;
            const formData = new FormData(form);
            const commentData = {
                post_id: postId,
                content: formData.get('content'),
                anonymous: formData.get('anonymous') === 'on'
            };

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
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    form.reset();
                    loadComments(postId);
                    // Update comment count
                    const commentCount = document.querySelector(`[data-post-id="${postId}"] .comment-count`);
                    if (commentCount) {
                        commentCount.textContent = parseInt(commentCount.textContent) + 1;
                    }
                    // Hide comment section after posting
                    setTimeout(() => {
                        const commentsSection = document.getElementById('comments-' + postId);
                        if (commentsSection) {
                            commentsSection.classList.remove('show');
                            commentsSection.style.display = 'none';
                        }
                    }, 2000); // Hide after 2 seconds
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred while posting the comment.', 'error');
            });
        }

        function toggleLike(itemId, type) {
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
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update like count without refreshing
                    updateLikeCount(itemId, type);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        function confirmLogout() {
            if (typeof showConfirm === 'function') {
                showConfirm('Are you sure you want to logout?').then((ok) => {
                    if (ok) window.location.href = 'logout.php';
                });
            } else {
                if (confirm('Are you sure you want to logout?')) {
                    window.location.href = 'logout.php';
                }
            }
        }

        function addNewPostToPage(postData) {
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
            
            // Create new post element with unique ID
            const uniqueId = 'new-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            const newPostElement = document.createElement('div');
            newPostElement.className = 'post-card';
            newPostElement.setAttribute('data-post-id', uniqueId);
            
            const currentDate = new Date().toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
            
            newPostElement.innerHTML = `
                <div class="post-header">
                    <div class="post-author">
                        <span class="author-name">${postData.anonymous ? 'Anonymous' : '<?php echo htmlspecialchars($userFirstName . ' ' . $userLastName); ?>'}</span>
                        ${postData.anonymous ? '<span class="anonymous-badge">Anonymous</span>' : '<span class="author-role"><?php echo ucfirst($userRole); ?></span>'}
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
                     <button class="action-btn like-btn" onclick="toggleLike('${uniqueId}', 'post')">
                         <span class="like-icon">??</span>
                         <span class="like-count">0</span>
                     </button>
                     <button class="action-btn comment-btn" onclick="toggleComments('${uniqueId}')">
                         <span class="comment-icon">??</span>
                         <span class="comment-count">0</span>
                     </button>
                 </div>
                 
                 <!-- Comments Section -->
                 <div id="comments-${uniqueId}" class="comments-section">
                     <div class="comments-list" id="comments-list-${uniqueId}">
                         <!-- Comments will be loaded here -->
                     </div>
                     
                     <div class="add-comment">
                         <form class="comment-form" onsubmit="submitComment(event, '${uniqueId}')">
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
            document.getElementById('postForm').reset();
        }

        function updateLikeCount(itemId, type) {
            let likeCountElement;
            let likeButton;
            
            if (type === 'post') {
                likeCountElement = document.querySelector(`[data-post-id="${itemId}"] .like-count`);
                likeButton = document.querySelector(`[data-post-id="${itemId}"] .like-btn`);
            } else if (type === 'comment') {
                likeCountElement = document.querySelector(`[data-comment-id="${itemId}"] .like-count`);
                likeButton = document.querySelector(`[data-comment-id="${itemId}"] .like-btn`);
            }
            
            if (likeCountElement && likeButton) {
                const currentCount = parseInt(likeCountElement.textContent) || 0;
                
                // Check if the button is already liked (has 'liked' class)
                if (likeButton.classList.contains('liked')) {
                    // Unlike: decrease count and remove 'liked' class
                    likeCountElement.textContent = Math.max(0, currentCount - 1);
                    likeButton.classList.remove('liked');
                } else {
                    // Like: increase count and add 'liked' class
                    likeCountElement.textContent = currentCount + 1;
                    likeButton.classList.add('liked');
                }
            }
        }
    </script>
</body>
</html>
