USE mind_matters_db;

-- Modify the role column to include 'admin' in the ENUM
ALTER TABLE users 
MODIFY COLUMN role ENUM('client', 'therapist', 'admin') NOT NULL DEFAULT 'client'; 
 
-- Ensure contact_number exists
ALTER TABLE users ADD COLUMN IF NOT EXISTS contact_number VARCHAR(30) NULL;