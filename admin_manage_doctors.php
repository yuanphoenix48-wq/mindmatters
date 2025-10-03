<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit(); }
require_once 'connect.php';

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$me = $result->fetch_assoc();
if (!$me || $me['role'] !== 'admin') { header('Location: dashboard.php'); exit(); }
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Therapists - Mind Matters</title>
  <link rel="stylesheet" href="styles/admin_dashboard.css">
</head>
<body class="dbBody">
  <div class="dbContainer">
    <h1>Manage Therapists</h1>
    <p>Coming soon. For now, go to <a href="admin_users.php?tab=therapist">User Management â€¢ Therapists</a>.</p>
  </div>
</body>
</html>























































