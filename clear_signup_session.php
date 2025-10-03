<?php
session_start();
unset($_SESSION['signup_old']);
echo json_encode(['success' => true]);
?>

