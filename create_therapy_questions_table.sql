-- Therapy Questions table
CREATE TABLE IF NOT EXISTS therapy_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  question TEXT NOT NULL,
  answer TEXT NULL,
  status ENUM('open','answered','closed') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  answered_at TIMESTAMP NULL DEFAULT NULL,
  INDEX (user_id, status, created_at),
  CONSTRAINT fk_tq_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



