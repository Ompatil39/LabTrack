<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config/config.php';

$pdo = db_pdo();
$pdo->beginTransaction();

try {
    $users = [
        [
            'user_id' => 'USR-ADMIN-001',
            'username' => 'admin',
            'password' => password_hash('admin123', PASSWORD_BCRYPT),
            'email' => 'admin@labtrack.local',
            'user_type' => 'Admin',
            'full_name' => 'System Administrator',
            'contact_number' => '9000000001',
            'org_name' => 'LabTrack Institute',
        ],
        [
            'user_id' => 'USR-INCH-001',
            'username' => 'incharge_lab1',
            'password' => password_hash('incharge123', PASSWORD_BCRYPT),
            'email' => 'incharge1@labtrack.local',
            'user_type' => 'Incharge',
            'full_name' => 'Lab Instructor One',
            'contact_number' => '9000000002',
            'org_name' => 'LabTrack Institute',
        ],
        [
            'user_id' => 'USR-INCH-002',
            'username' => 'incharge_lab2',
            'password' => password_hash('incharge123', PASSWORD_BCRYPT),
            'email' => 'incharge2@labtrack.local',
            'user_type' => 'Incharge',
            'full_name' => 'Lab Instructor Two',
            'contact_number' => '9000000003',
            'org_name' => 'LabTrack Institute',
        ],
    ];

    $userStatement = $pdo->prepare(
        'INSERT INTO users (user_id, username, password, email, user_type, full_name, contact_number, org_name)
         VALUES (:user_id, :username, :password, :email, :user_type, :full_name, :contact_number, :org_name)
         ON DUPLICATE KEY UPDATE
            username = VALUES(username),
            password = VALUES(password),
            email = VALUES(email),
            user_type = VALUES(user_type),
            full_name = VALUES(full_name),
            contact_number = VALUES(contact_number),
            org_name = VALUES(org_name)'
    );

    foreach ($users as $user) {
        $userStatement->execute($user);
    }

    $labs = [
        [
            'lab_id' => 'LAB001',
            'lab_name' => 'Computer Lab 1',
            'status' => 'Active',
            'department' => 'Computer Science',
            'lab_incharge' => 'Lab Instructor One',
            'establishment_date' => '2020-06-15',
            'room_capacity' => 10,
            'building' => 'Main Block',
            'room_number' => '101',
        ],
        [
            'lab_id' => 'LAB002',
            'lab_name' => 'Computer Lab 2',
            'status' => 'Active',
            'department' => 'Information Technology',
            'lab_incharge' => 'Lab Instructor Two',
            'establishment_date' => '2021-08-20',
            'room_capacity' => 10,
            'building' => 'Main Block',
            'room_number' => '102',
        ],
        [
            'lab_id' => 'LAB003',
            'lab_name' => 'Computer Lab 3',
            'status' => 'Active',
            'department' => 'Electronics',
            'lab_incharge' => 'Lab Instructor One',
            'establishment_date' => '2022-01-10',
            'room_capacity' => 10,
            'building' => 'Annex Block',
            'room_number' => '201',
        ],
    ];

    $labStatement = $pdo->prepare(
        'INSERT INTO labs (lab_id, lab_name, status, department, lab_incharge, establishment_date, room_capacity, building, room_number)
         VALUES (:lab_id, :lab_name, :status, :department, :lab_incharge, :establishment_date, :room_capacity, :building, :room_number)
         ON DUPLICATE KEY UPDATE
            lab_name = VALUES(lab_name),
            status = VALUES(status),
            department = VALUES(department),
            lab_incharge = VALUES(lab_incharge),
            establishment_date = VALUES(establishment_date),
            room_capacity = VALUES(room_capacity),
            building = VALUES(building),
            room_number = VALUES(room_number)'
    );

    foreach ($labs as $lab) {
        $labStatement->execute($lab);
    }

    // Seed devices for the labs
    $devices = [
        [
            'device_id' => 'PC-2026-0001',
            'lab_id' => 'LAB001',
            'device_type' => 'PC',
            'device_name' => 'Workstation 01',
            'serial_number' => 'WS-LAB1-001',
            'status' => 'Active',
            'qr_code' => 'PC-2026-0001',
        ],
        [
            'device_id' => 'PC-2026-0002',
            'lab_id' => 'LAB001',
            'device_type' => 'PC',
            'device_name' => 'Workstation 02',
            'serial_number' => 'WS-LAB1-002',
            'status' => 'Active',
            'qr_code' => 'PC-2026-0002',
        ],
        [
            'device_id' => 'PC-2026-0003',
            'lab_id' => 'LAB002',
            'device_type' => 'PC',
            'device_name' => 'Workstation 03',
            'serial_number' => 'WS-LAB2-001',
            'status' => 'Under Repair',
            'qr_code' => 'PC-2026-0003',
        ],
        [
            'device_id' => 'PRT-2026-0001',
            'lab_id' => 'LAB001',
            'device_type' => 'Printer',
            'device_name' => 'HP LaserJet Pro',
            'serial_number' => 'PRT-HP-001',
            'status' => 'Active',
            'qr_code' => 'PRT-2026-0001',
        ],
    ];

    $deviceStatement = $pdo->prepare(
        'INSERT INTO devices (device_id, lab_id, device_type, device_name, serial_number, status, qr_code)
         VALUES (:device_id, :lab_id, :device_type, :device_name, :serial_number, :status, :qr_code)
         ON DUPLICATE KEY UPDATE
            lab_id = VALUES(lab_id),
            device_type = VALUES(device_type),
            device_name = VALUES(device_name),
            serial_number = VALUES(serial_number),
            status = VALUES(status),
            qr_code = VALUES(qr_code)'
    );

    foreach ($devices as $device) {
        $deviceStatement->execute($device);
    }

    $pcStatement = $pdo->prepare(
        'INSERT INTO pc_details (device_id, processor, ram, storage, operating_system, ethernet_mac, wifi_adapter, ip_address)
         VALUES (:device_id, :processor, :ram, :storage, :operating_system, :ethernet_mac, :wifi_adapter, :ip_address)'
    );

    $checkPcStmt = $pdo->prepare('SELECT pc_id FROM pc_details WHERE device_id = ? LIMIT 1');

    $pcList = [
        [
            'device_id' => 'PC-2026-0001',
            'processor' => 'Intel Core i7-12700',
            'ram' => '16GB DDR4',
            'storage' => '512GB NVMe SSD',
            'operating_system' => 'Ubuntu 22.04 LTS',
            'ethernet_mac' => '02:1B:44:01:01:01',
            'wifi_adapter' => 'Intel Wi-Fi 6',
            'ip_address' => '192.168.1.101',
        ],
        [
            'device_id' => 'PC-2026-0002',
            'processor' => 'Intel Core i7-12700',
            'ram' => '16GB DDR4',
            'storage' => '512GB NVMe SSD',
            'operating_system' => 'Windows 11 Pro',
            'ethernet_mac' => '02:1B:44:01:01:02',
            'wifi_adapter' => 'Intel Wi-Fi 6',
            'ip_address' => '192.168.1.102',
        ],
        [
            'device_id' => 'PC-2026-0003',
            'processor' => 'AMD Ryzen 7 5700G',
            'ram' => '16GB DDR4',
            'storage' => '1TB NVMe SSD',
            'operating_system' => 'Ubuntu 22.04 LTS',
            'ethernet_mac' => '02:1B:44:02:01:01',
            'wifi_adapter' => 'None',
            'ip_address' => '192.168.2.101',
        ],
    ];

    foreach ($pcList as $pc) {
        $checkPcStmt->execute([$pc['device_id']]);
        if (!$checkPcStmt->fetch()) {
            $pcStatement->execute($pc);
        }
    }

    $pdo->commit();
    echo 'Database seeding complete.' . PHP_EOL;
} catch (Throwable $throwable) {
    $pdo->rollBack();
    throw $throwable;
}
