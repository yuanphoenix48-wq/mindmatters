-- ========================================
-- MIND MATTERS - CONTACT MESSAGES TABLE
-- ========================================

CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'replied', 'closed') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ONimage.png UPDATE CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add some sample data for testing (optional)
-- INSERT INTO contact_messages (name, email, message, status) VALUES
-- ('John Doe', 'john@example.com', 'This is a test message from the contact form.', 'new'),
-- ('Jane Smith', 'jane@example.com', 'I would like to know more about your services.', 'read');

