USE mind_matters_db;

-- Create password reset tokens table
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(100) NOT NULL,
    reset_code VARCHAR(6) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_email (email),
    INDEX idx_reset_code (reset_code),
    INDEX idx_expires_at (expires_at)
);

-- Clean up expired tokens (optional - can be run periodically)
-- DELETE FROM password_reset_tokens WHERE expires_at < NOW() OR used = TRUE;
