-- Create sessions table
CREATE TABLE IF NOT EXISTS sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    therapist_id INT NULL,
    session_date DATE NOT NULL,
    session_time TIME NOT NULL,
    status ENUM('pending', 'scheduled', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id),
    FOREIGN KEY (therapist_id) REFERENCES users(id)
);

-- Add index for faster queries
CREATE INDEX idx_client_sessions ON sessions(client_id);
CREATE INDEX idx_therapist_sessions ON sessions(therapist_id);
CREATE INDEX idx_session_date ON sessions(session_date); 