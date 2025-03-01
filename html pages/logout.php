<?php
// logout.php - Place this in a separate file

// Start the session if it's not already started
session_start();

// Unset all session variables
$_SESSION = array();

// If a session cookie is used, destroy it
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit;
