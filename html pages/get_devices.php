<?php
require_once __DIR__ . '/../includes/security.php';
requireLogin(true);

include 'db.php';

$lab_id = trim((string) ($_GET['lab_id'] ?? ''));
$device_type = trim((string) ($_GET['device_type'] ?? ''));
$devices = [];

if ($lab_id !== '' && $device_type !== '') {
    $scope = restrictLabSql('lab_id');
    $sql = 'SELECT device_id, device_name FROM devices WHERE lab_id = ? AND device_type = ? AND status = ?';
    $types = 'sss';
    $params = [$lab_id, $device_type, 'Active'];

    if ($scope['clause'] !== '') {
        $sql .= ' AND ' . trim($scope['clause']);
        $types .= $scope['types'];
        $params = array_merge($params, $scope['params']);
    }

    $devices = db_fetch_all($conn, $sql, $types, $params);
}

header('Content-Type: application/json');
echo json_encode($devices);
