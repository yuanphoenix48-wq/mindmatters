<?php
require_once 'connect.php';

echo "=== Checking Users Table ===\n\n";

// Check recent users
$sql = "SELECT id, user_id, first_name, last_name, email, email_verified, verification_token, verification_expires, created_at FROM users ORDER BY created_at DESC LIMIT 5";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "Recent users:\n";
    echo "ID\tUserID\tName\t\tEmail\t\tVerified\tToken\t\tExpires\t\tCreated\n";
    echo "--\t------\t----\t\t-----\t\t--------\t-----\t\t--------\t-------\n";
    
    while ($row = $result->fetch_assoc()) {
        $token = $row['verification_token'] ? substr($row['verification_token'], 0, 10) . "..." : "NULL";
        $verified = $row['email_verified'] ? "YES" : "NO";
        echo $row['id'] . "\t" . 
             $row['user_id'] . "\t" . 
             $row['first_name'] . " " . $row['last_name'] . "\t" . 
             $row['email'] . "\t" . 
             $verified . "\t\t" . 
             $token . "\t" . 
             $row['verification_expires'] . "\t" . 
             $row['created_at'] . "\n";
    }
} else {
    echo "No users found in the table.\n";
}

echo "\n=== Table Structure ===\n";
$sql = "SHOW COLUMNS FROM users";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . " - Null: " . $row['Null'] . " - Default: " . $row['Default'] . "\n";
}

$conn->close();
?>





