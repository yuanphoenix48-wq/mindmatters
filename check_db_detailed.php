<?php
require_once 'connect.php';

echo "=== Detailed Database Structure Check ===\n\n";

// Check each verification field individually
$fields = ['email_verified', 'verification_token', 'verification_expires'];

foreach ($fields as $field) {
    $sql = "SHOW COLUMNS FROM users LIKE '$field'";
    $result = $conn->query($sql);
    
    if ($result->num_rows === 0) {
        echo "❌ Field '$field' NOT found\n";
    } else {
        $row = $result->fetch_assoc();
        echo "✅ Field '$field' found: " . $row['Type'] . " (Null: " . $row['Null'] . ", Default: " . $row['Default'] . ")\n";
    }
}

echo "\n=== All Columns in Users Table ===\n";
$sql = "SHOW COLUMNS FROM users";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . " - Null: " . $row['Null'] . " - Default: " . $row['Default'] . "\n";
}

$conn->close();
?>





