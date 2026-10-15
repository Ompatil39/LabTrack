<?php
require_once __DIR__ . '/../includes/security.php';
requireLogin(true);
enforceCsrfProtection(true);

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<tr><td colspan="7">Invalid request</td></tr>';
    exit;
}

$lab_id = trim((string) ($_POST['lab_id'] ?? ''));
$search = trim((string) ($_POST['search'] ?? ''));
$status = trim((string) ($_POST['status'] ?? ''));
$category = trim((string) ($_POST['category'] ?? ''));
$type = trim((string) ($_POST['type'] ?? 'All'));

if ($lab_id === '') {
    echo '<tr><td colspan="7">Lab is required</td></tr>';
    exit;
}

$scope = restrictLabSql('lab_id', 'd');
$params = [$lab_id];
$types = 's';
$conditions = ['d.lab_id = ?'];

if ($type === 'PC') {
    $conditions[] = "d.device_type = 'PC'";
} elseif ($category !== '') {
    $conditions[] = 'd.device_type = ?';
    $types .= 's';
    $params[] = $category;
}

if ($search !== '') {
    $conditions[] = '(d.device_id LIKE ? OR d.device_name LIKE ?)';
    $types .= 'ss';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($status !== '') {
    $conditions[] = 'd.status = ?';
    $types .= 's';
    $params[] = $status;
}

if ($scope['clause'] !== '') {
    $conditions[] = trim($scope['clause']);
    $types .= $scope['types'];
    $params = array_merge($params, $scope['params']);
}

$sql = "SELECT d.device_id, d.device_name, d.device_type,
        CASE
            WHEN d.device_type = 'PC' THEN (SELECT pc_id FROM pc_details WHERE device_id = d.device_id LIMIT 1)
            ELSE 'N/A'
        END AS pc_id,
        d.status,
        'Working' AS remarks
        FROM devices d
        WHERE " . implode(' AND ', $conditions);

$rows = db_fetch_all($conn, $sql, $types, $params);

if (empty($rows)) {
    echo '<tr><td colspan="7">No devices found</td></tr>';
    exit;
}

foreach ($rows as $row) {
    echo '<tr>';
    echo '<td>' . e($row['device_id']) . '</td>';
    echo '<td>' . e($row['device_name']) . '</td>';
    echo '<td>' . e($row['device_type']) . '</td>';
    echo '<td>' . e($row['pc_id']) . '</td>';
    echo "<td><span class='status status-" . strtolower(str_replace(' ', '-', (string) $row['status'])) . "'>" . e($row['status']) . '</span></td>';
    echo '<td>' . e($row['remarks']) . '</td>';
    echo '<td>
            <a href="viewDevice.php?id=' . rawurlencode((string) $row['device_id']) . '" class="none">
                <button class="btn-icon view-btn"><i class="fas fa-eye"></i></button>
            </a>
            <a href="editDevice.php?id=' . rawurlencode((string) $row['device_id']) . '" class="none">
                <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i></button>
            </a>
            <button class="btn-icon delete-btn delete-trigger" data-id="' . e($row['device_id']) . '" data-name="' . e($row['device_name']) . '"><i class="fa-solid fa-trash"></i></button>
          </td>';
    echo '</tr>';
}
