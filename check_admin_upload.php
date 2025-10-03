<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'connect.php';

echo "<h2>Admin Profile Picture Upload Diagnostic</h2>";

// Check session
echo "<h3>Session Status:</h3>";
echo "Session ID: " . session_id() . "<br>";
echo "User ID in session: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not set') . "<br>";
echo "User role in session: " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'Not set') . "<br>";

// Check database connection
echo "<h3>Database Connection:</h3>";
if ($conn) {
    echo "Database connection successful<br>";
    
    // Check admin user
    if (isset($_SESSION['user_id'])) {
        $sql = "SELECT id, role, profile_picture FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        echo "Admin user found: " . ($user ? 'Yes' : 'No') . "<br>";
        if ($user) {
            echo "User role: " . $user['role'] . "<br>";
            echo "Current profile picture: " . $user['profile_picture'] . "<br>";
        }
        $stmt->close();
    }
} else {
    echo "Database connection failed<br>";
}

// Check upload directory
echo "<h3>Upload Directory:</h3>";
$uploadDir = 'images/profile/user_uploads/';
echo "Upload directory exists: " . (file_exists($uploadDir) ? 'Yes' : 'No') . "<br>";
echo "Upload directory is writable: " . (is_writable($uploadDir) ? 'Yes' : 'No') . "<br>";
echo "Upload directory path: " . realpath($uploadDir) . "<br>";

// Check PHP upload settings
echo "<h3>PHP Upload Settings:</h3>";
echo "file_uploads: " . ini_get('file_uploads') . "<br>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "<br>";

// Test form
echo "<h3>Test Upload Form:</h3>";
echo "<form action='upload_profile_pic.php' method='POST' enctype='multipart/form-data'>";
echo "<input type='file' name='profile_pic' accept='image/*'><br>";
echo "<input type='submit' value='Test Upload'>";
echo "</form>";

$conn->close();
?> 