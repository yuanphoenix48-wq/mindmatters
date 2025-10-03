-- Add reminder tracking columns to sessions table
ALTER TABLE sessions 
ADD COLUMN reminder_24h_sent BOOLEAN DEFAULT FALSE,
ADD COLUMN reminder_10min_sent BOOLEAN DEFAULT FALSE,
ADD COLUMN feedback_requested BOOLEAN DEFAULT FALSE;

-- Add indexes for better performance
CREATE INDEX idx_reminder_24h ON sessions(session_date, status, reminder_24h_sent);
CREATE INDEX idx_reminder_10min ON sessions(session_date, session_time, status, reminder_10min_sent);
CREATE INDEX idx_feedback_request ON sessions(session_date, status, feedback_requested);



