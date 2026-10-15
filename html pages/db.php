<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config/config.php';

$dbConfig = app_config()['db'];
$conn = new mysqli(
    $dbConfig['host'],
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['database'],
    $dbConfig['port']
);

if ($conn->connect_error) {
    app_logger()->error('mysqli_connection_failed', ['error' => $conn->connect_error]);
    die('Connection failed.');
}
