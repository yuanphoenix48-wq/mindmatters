<?php
require_once 'connect.php';

echo "=== Database Structure Check ===\n\n";

// Check if verification fields exist
$sql = "SHOW COLUMNS FROM users LIKE 'email_verified'";
$result = $conn->query($sql);

if ($result->num_rows === 0) {
    echo "❌ Verification fields NOT found in users table!\n";
    echo "You need to run the SQL script to add these fields.\n\n";
    
    echo "Run this SQL in phpMyAdmin:\n";
    echo "ALTER TABLE users ADD COLUMN email_verified BOOLEAN DEFAULT FALSE;\n";
    echo "ALTER TABLE users ADD COLUMN verification_token VARCHAR(255) UNIQUE;\n";
    echo "ALTER TABLE users ADD COLUMN verification_expires TIMESTAMP;\n";
} else {
    echo "✅ Verification fields found!\n\n";
    
    // Show all columns
    $sql = "DESCRIBE users";
    $result = $conn->query($sql);
    
    echo "Users table structure:\n";
    echo "Field\t\tType\t\tNull\tDefault\n";
    echo "-----\t\t----\t\t----\t-------\n";
    
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . "\t\t" . $row['Type'] . "\t" . $row['Null'] . "\t" . $row['Default'] . "\n";
    }
}

$conn->close();
?>





