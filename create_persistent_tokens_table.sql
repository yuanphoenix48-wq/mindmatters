USE mind_matters_db;

-- Create table for persistent login tokens
CREATE TABLE IF NOT EXISTS persistent_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    last_used TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    user_agent TEXT,
    ip_address VARCHAR(45),
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_user_id (user_id),
    INDEX idx_token (token),
    INDEX idx_expires_at (expires_at),
    INDEX idx_active (is_active),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create index for efficient cleanup of expired tokens
CREATE INDEX idx_cleanup ON persistent_tokens(expires_at, is_active);

