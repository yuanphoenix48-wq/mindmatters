<?php
require_once 'connect.php';
require_once 'includes/EmailVerification.php';

echo "=== Debug Signup Process ===\n\n";

// Test data
$firstName = 'Test';
$lastName = 'Student';
$email = 'test@student.fatima.edu.ph';
$hashedPassword = password_hash('test123', PASSWORD_DEFAULT);
$studentId = 'TEST123';
$gender = 'male';
$profilePicture = 'images/profile/default_images/male_gender.png';
$section = 'A1';

// Generate verification data
$emailVerification = new EmailVerification();
$verificationToken = $emailVerification->generateVerificationToken();
$verificationExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));

echo "=== Generated Data ===\n";
echo "Token: $verificationToken\n";
echo "Expires: $verificationExpires\n\n";

// Test the exact SQL from signupapi.php
$sql = "INSERT INTO users (first_name, last_name, email, password, role, user_id, gender, profile_picture, section, verification_token, verification_expires, email_verified) 
        VALUES (?, ?, ?, ?, 'student', ?, ?, ?, ?, ?, ?, FALSE)";

echo "=== SQL Query ===\n";
echo $sql . "\n\n";

echo "=== Parameter Count ===\n";
$placeholderCount = substr_count($sql, '?');
echo "SQL placeholders (?): $placeholderCount\n\n";

echo "=== Variables to Bind ===\n";
$variables = [$firstName, $lastName, $email, $hashedPassword, $studentId, $gender, $profilePicture, $section, $verificationToken, $verificationExpires];
echo "Variables count: " . count($variables) . "\n";
foreach ($variables as $i => $var) {
    echo ($i+1) . ". " . (is_string($var) ? substr($var, 0, 20) . "..." : $var) . "\n";
}

echo "\n=== Bind String ===\n";
$bindString = str_repeat('s', count($variables));
echo "Bind string: '$bindString' (length: " . strlen($bindString) . ")\n\n";

// Try to prepare and bind
$stmt = $conn->prepare($sql);
if ($stmt) {
    echo "✅ SQL prepared successfully\n";
    
    // Try binding
    if ($stmt->bind_param($bindString, $firstName, $lastName, $email, $hashedPassword, $studentId, $gender, $profilePicture, $section, $verificationToken, $verificationExpires)) {
        echo "✅ Parameters bound successfully\n";
        
        // Try to execute (but don't actually insert)
        echo "✅ Ready to execute (but not executing for safety)\n";
        
    } else {
        echo "❌ Binding failed: " . $stmt->error . "\n";
    }
} else {
    echo "❌ SQL preparation failed: " . $conn->error . "\n";
}

$conn->close();
echo "\n=== Debug Complete ===\n";
?>





