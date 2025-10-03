USE mind_matters_db;

-- Quick fix for the immediate sessions table issue
-- This will add the new columns and copy data from old columns

-- Step 1: Add new columns to sessions table
ALTER TABLE sessions 
ADD COLUMN client_id INT NULL AFTER id,
ADD COLUMN therapist_id INT NULL AFTER client_id;

-- Step 2: Copy data from old columns to new columns
UPDATE sessions SET client_id = student_id WHERE student_id IS NOT NULL;
UPDATE sessions SET therapist_id = doctor_id WHERE doctor_id IS NOT NULL;

-- Step 3: Make client_id NOT NULL (since every session must have a client)
ALTER TABLE sessions 
MODIFY COLUMN client_id INT NOT NULL;

-- Step 4: Add foreign key constraints for new columns
ALTER TABLE sessions 
ADD CONSTRAINT fk_sessions_client_id 
FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE sessions 
ADD CONSTRAINT fk_sessions_therapist_id 
FOREIGN KEY (therapist_id) REFERENCES users(id) ON DELETE CASCADE;

-- Step 5: Drop old foreign key constraints (if they exist)
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

SELECT 'Sessions table updated successfully! The dashboard should now work.' as Status;



















































