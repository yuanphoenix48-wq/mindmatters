-- System feedback table for clients and therapists
CREATE TABLE IF NOT EXISTS system_feedback (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  user_role ENUM('client','therapist','admin') NOT NULL,
  session_id INT NULL,
  ease_of_scheduling TINYINT NOT NULL CHECK (ease_of_scheduling BETWEEN 1 AND 5),
  ease_of_use TINYINT NOT NULL CHECK (ease_of_use BETWEEN 1 AND 5),
  liked_most TEXT NULL,
  improvement TEXT NULL,
  recommend TINYINT(1) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id),
  INDEX idx_session_id (session_id),
  CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_feedback_session FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE SET NULL
);


