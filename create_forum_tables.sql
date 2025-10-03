USE mind_matters_db;

-- Create forum_posts table
CREATE TABLE IF NOT EXISTS forum_posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    is_anonymous BOOLEAN DEFAULT FALSE,
    category ENUM('general', 'mental_health', 'therapy', 'support', 'resources') DEFAULT 'general',
    likes_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create forum_comments table
CREATE TABLE IF NOT EXISTS forum_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    is_anonymous BOOLEAN DEFAULT FALSE,
    likes_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create forum_likes table for tracking likes on posts and comments
CREATE TABLE IF NOT EXISTS forum_likes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    post_id INT NULL,
    comment_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (comment_id) REFERENCES forum_comments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_like (user_id, post_id, comment_id),
    CHECK ((post_id IS NOT NULL AND comment_id IS NULL) OR (post_id IS NULL AND comment_id IS NOT NULL))
);

-- Create indexes for better performance
CREATE INDEX idx_forum_posts_user_id ON forum_posts(user_id);
CREATE INDEX idx_forum_posts_category ON forum_posts(category);
CREATE INDEX idx_forum_posts_created_at ON forum_posts(created_at);
CREATE INDEX idx_forum_comments_post_id ON forum_comments(post_id);
CREATE INDEX idx_forum_comments_user_id ON forum_comments(user_id);
CREATE INDEX idx_forum_likes_user_id ON forum_likes(user_id);
CREATE INDEX idx_forum_likes_post_id ON forum_likes(post_id);
CREATE INDEX idx_forum_likes_comment_id ON forum_likes(comment_id);




