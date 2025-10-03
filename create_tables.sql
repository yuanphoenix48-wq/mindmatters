USE mind_matters_db;

-- Create therapists table
CREATE TABLE IF NOT EXISTS therapists (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    license_number VARCHAR(50) UNIQUE NOT NULL,
    specialization VARCHAR(100),
    years_of_experience INT,
    languages_spoken VARCHAR(255),
    bio TEXT,
    consultation_fee DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create clients table
CREATE TABLE IF NOT EXISTS clients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    client_id VARCHAR(50) UNIQUE NOT NULL,
    section VARCHAR(50),
    date_of_birth DATE,
    address TEXT,
    emergency_contact VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
); 

-- Self-help tool assignments
