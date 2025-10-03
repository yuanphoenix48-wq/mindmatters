<?php
require_once 'connect.php';

echo "=== Fixing Database Structure ===\n\n";

// Check what fields are missing
$requiredFields = [
    'first_name' => "ALTER TABLE users ADD COLUMN first_name VARCHAR(50) NOT NULL AFTER user_id",
    'last_name' => "ALTER TABLE users ADD COLUMN last_name VARCHAR(50) NOT NULL AFTER first_name",
    'section' => "ALTER TABLE users ADD COLUMN section VARCHAR(10) AFTER last_name",
    'gender' => "ALTER TABLE users ADD COLUMN gender ENUM('male','female') NOT NULL AFTER section",
    'email' => "ALTER TABLE users ADD COLUMN email VARCHAR(100) NOT NULL AFTER gender",
    'password' => "ALTER TABLE users ADD COLUMN password VARCHAR(255) NOT NULL AFTER email",
    'profile_picture' => "ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT 'images/profile/default_images/default.png' AFTER password",
    'role' => "ALTER TABLE users ADD COLUMN role ENUM('student','doctor','admin') NOT NULL DEFAULT 'student' AFTER profile_picture",
    'email_verified' => "ALTER TABLE users ADD COLUMN email_verified BOOLEAN DEFAULT FALSE AFTER role",
    'verification_token' => "ALTER TABLE users ADD COLUMN verification_token VARCHAR(255) UNIQUE AFTER email_verified",
    'verification_expires' => "ALTER TABLE users ADD COLUMN verification_expires TIMESTAMP AFTER verification_token"
];

foreach ($requiredFields as $field => $sql) {
    // Check if field exists
    $checkSql = "SHOW COLUMNS FROM users LIKE '$field'";
    $result = $conn->query($checkSql);
    
    if ($result->num_rows === 0) {
        echo "❌ Field '$field' missing - adding...\n";
        if ($conn->query($sql)) {
            echo "✅ Field '$field' added successfully\n";
        } else {
            echo "❌ Error adding field '$field': " . $conn->error . "\n";
        }
    } else {
        echo "✅ Field '$field' already exists\n";
    }
}

echo "\n=== Final Database Structure ===\n";
$sql = "SHOW COLUMNS FROM users";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . " - Null: " . $row['Null'] . " - Default: " . $row['Default'] . "\n";
}

$conn->close();
?>





