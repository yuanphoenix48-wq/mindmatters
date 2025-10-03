USE mind_matters_db;

-- Add email verification fields to users table
ALTER TABLE users 
ADD COLUMN email_verified BOOLEAN DEFAULT FALSE,
ADD COLUMN verification_token VARCHAR(255) UNIQUE,
ADD COLUMN verification_expires TIMESTAMP;

-- Create index for verification token
CREATE INDEX idx_verification_token ON users(verification_token);





