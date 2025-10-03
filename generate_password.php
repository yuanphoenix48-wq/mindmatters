<?php
$password = "admin123"; // This is the actual password you'll use to login
$hashed = password_hash($password, PASSWORD_DEFAULT);
echo "Password: " . $password . "<br>";
echo "Hashed password for SQL: " . $hashed;
?> 