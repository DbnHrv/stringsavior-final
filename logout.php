<?php
session_start(); // Start the session
$_SESSION = array();
// Destroy the session itself
session_destroy();
// Redirect to login page (or any other page)
header("Location: login.php");
exit;
?>
