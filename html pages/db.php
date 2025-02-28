<?php
// Database configuration
$host = "localhost"; // Replace with your database host if not local
$user = "root";      // Replace with your database username
$password = "";      // No password
$database = "lab_db"; // Database name

// Create a connection to the database
$conn = new mysqli($host, $user, $password, $database);


if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

?>