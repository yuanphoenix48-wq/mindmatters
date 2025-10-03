USE mind_matters_db;

-- Fix the sessions table to match the updated PHP code
-- Your users table is already correct with the role column

-- Step 1: Check if sessions table exists and fix it
ALTER TABLE sessions 
ADD COLUMN client_id INT NULL AFTER id,
ADD COLUMN therapist_id INT NULL AFTER client_id;

-- Step 2: Copy data from old columns to new columns
UPDATE sessions SET client_id = student_id WHERE student_id IS NOT NULL;
UPDATE sessions SET therapist_id = doctor_id WHERE doctor_id IS NOT NULL;

-- Step 3: Make client_id NOT NULL (every session must have a client)
ALTER TABLE sessions 
MODIFY COLUMN client_id INT NOT NULL;

-- Step 4: Add foreign key constraints for new columns
ALTER TABLE sessions 
ADD CONSTRAINT fk_sessions_client_id 
FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE sessions 
ADD CONSTRAINT fk_sessions_therapist_id 
FOREIGN KEY (therapist_id) REFERENCES users(id) ON DELETE CASCADE;

-- Step 5: Drop old foreign key constraints (disable checks temporarily)
SET FOREIGN_KEY_CHECKS = 0;
ALTER TABLE sessions DROP FOREIGN KEY IF EXISTS sessions_ibfk_1;
ALTER TABLE sessions DROP FOREIGN KEY IF EXISTS sessions_ibfk_2;
SET FOREIGN_KEY_CHECKS = 1;

-- Step 6: Drop old columns
ALTER TABLE sessions 
DROP COLUMN student_id,
DROP COLUMN doctor_id;

-- Step 7: Update indexes
DROP INDEX IF EXISTS idx_student_sessions ON sessions;
DROP INDEX IF EXISTS idx_doctor_sessions ON sessions;

CREATE INDEX idx_client_sessions ON sessions(client_id);
CREATE INDEX idx_therapist_sessions ON sessions(therapist_id);

-- Step 8: Fix other tables that might exist
-- Check and fix mood_logs table if it exists
SET @table_exists = (SELECT COUNT(*) FROM information_schema.tables 
                     WHERE table_schema = 'mind_matters_db' AND table_name = 'mood_logs');
SET @sql = IF(@table_exists > 0, 
    'ALTER TABLE mood_logs CHANGE COLUMN student_id client_id INT NOT NULL', 
    'SELECT "mood_logs table does not exist" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and fix mental_health_assessments table if it exists
SET @table_exists = (SELECT COUNT(*) FROM information_schema.tables 
                     WHERE table_schema = 'mind_matters_db' AND table_name = 'mental_health_assessments');
SET @sql = IF(@table_exists > 0, 
    'ALTER TABLE mental_health_assessments CHANGE COLUMN student_id client_id INT NOT NULL', 
    'SELECT "mental_health_assessments table does not exist" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and fix doctor_notes table if it exists
SET @table_exists = (SELECT COUNT(*) FROM information_schema.tables 
                     WHERE table_schema = 'mind_matters_db' AND table_name = 'doctor_notes');
SET @sql = IF(@table_exists > 0, 
    'ALTER TABLE doctor_notes CHANGE COLUMN student_id client_id INT NOT NULL, CHANGE COLUMN doctor_id therapist_id INT NOT NULL', 
    'SELECT "doctor_notes table does not exist" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and fix doctor_availability table if it exists
SET @table_exists = (SELECT COUNT(*) FROM information_schema.tables 
                     WHERE table_schema = 'mind_matters_db' AND table_name = 'doctor_availability');
SET @sql = IF(@table_exists > 0, 
    'ALTER TABLE doctor_availability CHANGE COLUMN doctor_id therapist_id INT NOT NULL', 
    'SELECT "doctor_availability table does not exist" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and fix messages table if it exists
SET @table_exists = (SELECT COUNT(*) FROM information_schema.tables 
                     WHERE table_schema = 'mind_matters_db' AND table_name = 'messages');
SET @sql = IF(@table_exists > 0, 
    'ALTER TABLE messages CHANGE COLUMN sender_id client_id INT NOT NULL, CHANGE COLUMN receiver_id therapist_id INT NOT NULL', 
    'SELECT "messages table does not exist" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Show completion message
SELECT 'Database columns updated successfully! Your system should now work with the new terminology.' as Status;



















































