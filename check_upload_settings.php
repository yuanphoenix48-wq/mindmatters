<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>PHP Upload Settings:</h2>";
echo "file_uploads: " . ini_get('file_uploads') . "<br>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "<br>";
echo "upload_tmp_dir: " . ini_get('upload_tmp_dir') . "<br>";

echo "<h2>Directory Permissions:</h2>";
$uploadDir = 'images/profile/user_uploads/';
echo "Upload directory exists: " . (file_exists($uploadDir) ? 'Yes' : 'No') . "<br>";
echo "Upload directory is writable: " . (is_writable($uploadDir) ? 'Yes' : 'No') . "<br>";
echo "Upload directory path: " . realpath($uploadDir) . "<br>";

echo "<h2>Session Status:</h2>";
echo "Session ID: " . session_id() . "<br>";
echo "Session Status: " . session_status() . "<br>";
echo "User ID in session: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not set') . "<br>";

echo "<h2>Server Information:</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
?> 