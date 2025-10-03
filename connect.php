<?php
// Database connection settings
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mind_matters_db";

// Set PHP timezone (Philippines UTC+8) unconditionally to avoid host defaults
date_default_timezone_set('Asia/Manila');

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    // You might want to log this error instead of dying in a production environment
    // error_log("Database Connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
}

// Set MySQL session time zone to UTC+8 to match PHP
@$conn->query("SET time_zone = '+08:00'"); 