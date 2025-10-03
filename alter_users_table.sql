USE mind_matters_db;

-- Add role column if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS role ENUM('client', 'therapist', 'admin') NOT NULL DEFAULT 'client';

-- Add user_id column if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS user_id VARCHAR(20) UNIQUE NOT NULL;

-- Add student_id column if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS student_id VARCHAR(20) UNIQUE NULL;

-- Add profile_picture column if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255) DEFAULT 'images/profile/default_images/default_profile.png';

-- Add specialization column if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS specialization VARCHAR(100) NULL;

-- Add profile_picture column to users table
ALTER TABLE users
ADD COLUMN profile_picture VARCHAR(255) DEFAULT 'images/profile/default_images/default_profile.png';

-- Add role column if it doesn't exist
ALTER TABLE users
ADD COLUMN IF NOT EXISTS role VARCHAR(50);

-- Add user_id column if it doesn't exist
ALTER TABLE users
ADD COLUMN IF NOT EXISTS user_id INT AUTO_INCREMENT PRIMARY KEY;

-- Add gender column if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS gender ENUM('male','female') DEFAULT 'male';
-- Add contact_number for storing phone of therapists (nullable for others)
ALTER TABLE users ADD COLUMN IF NOT EXISTS contact_number VARCHAR(30) NULL;

-- Add therapist-specific fields on users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS license_number VARCHAR(50) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS years_of_experience INT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS languages_spoken VARCHAR(255) NULL;
-- Add specialization column if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS specialization VARCHAR(100) NULL;

-- Modify role column to include 'admin'
ALTER TABLE users 
MODIFY COLUMN role ENUM('client', 'therapist', 'admin') NOT NULL DEFAULT 'client';