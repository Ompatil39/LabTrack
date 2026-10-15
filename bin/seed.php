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

    $computerStatement = $pdo->prepare(
        'INSERT INTO computers (lab_id, mac_address, hostname, ip_address, status, cpu_usage, ram_usage, disk_usage, active_user, missing_peripherals, last_heartbeat_at)
         VALUES (:lab_id, :mac_address, :hostname, :ip_address, :status, :cpu_usage, :ram_usage, :disk_usage, :active_user, :missing_peripherals, :last_heartbeat_at)
         ON DUPLICATE KEY UPDATE
            hostname = VALUES(hostname),
            ip_address = VALUES(ip_address),
            status = VALUES(status),
            cpu_usage = VALUES(cpu_usage),
            ram_usage = VALUES(ram_usage),
            disk_usage = VALUES(disk_usage),
            active_user = VALUES(active_user),
            missing_peripherals = VALUES(missing_peripherals),
            last_heartbeat_at = VALUES(last_heartbeat_at)'
    );

    $telemetryInsert = $pdo->prepare(
        'INSERT INTO telemetry_logs (computer_id, mac_address, hostname, ip_address, cpu_usage, ram_usage, disk_usage, network_latency_ms, active_user, peripherals, received_at)
         VALUES (:computer_id, :mac_address, :hostname, :ip_address, :cpu_usage, :ram_usage, :disk_usage, :network_latency_ms, :active_user, :peripherals, :received_at)'
    );

    $computerLookup = $pdo->prepare('SELECT computer_id FROM computers WHERE mac_address = :mac_address LIMIT 1');

    mt_srand(20261015);

    for ($labNumber = 1; $labNumber <= 3; $labNumber++) {
        $labId = sprintf('LAB%03d', $labNumber);
        for ($pcNumber = 1; $pcNumber <= 10; $pcNumber++) {
            $globalIndex = (($labNumber - 1) * 10) + $pcNumber;
            $hostname = sprintf('LAB%d-PC%02d', $labNumber, $pcNumber);
            $macAddress = sprintf('02:1B:44:%02X:%02X:%02X', $labNumber, $pcNumber, $globalIndex);
            $ipAddress = sprintf('192.168.%d.%d', $labNumber, 100 + $pcNumber);

            $baseCpu = mt_rand(10, 65);
            $baseRam = mt_rand(25, 80);
            $baseDisk = mt_rand(20, 75);
            $missingPeripherals = json_encode([], JSON_THROW_ON_ERROR);
            $lastHeartbeat = (new DateTimeImmutable('-' . mt_rand(0, 20) . ' seconds'))->format('Y-m-d H:i:s');

            $computerStatement->execute([
                'lab_id' => $labId,
                'mac_address' => $macAddress,
                'hostname' => $hostname,
                'ip_address' => $ipAddress,
                'status' => 'online',
                'cpu_usage' => $baseCpu,
                'ram_usage' => $baseRam,
                'disk_usage' => $baseDisk,
                'active_user' => 'student_' . str_pad((string) $globalIndex, 3, '0', STR_PAD_LEFT),
                'missing_peripherals' => $missingPeripherals,
                'last_heartbeat_at' => $lastHeartbeat,
            ]);

            $computerLookup->execute(['mac_address' => $macAddress]);
            $computerRow = $computerLookup->fetch();
            $computerId = $computerRow['computer_id'] ?? null;

            for ($hour = 23; $hour >= 0; $hour--) {
                $timePoint = (new DateTimeImmutable('-' . $hour . ' hours'))->setTime((int) date('H'), mt_rand(0, 59), mt_rand(0, 59));

                $cpuUsage = min(99.9, max(2.0, $baseCpu + mt_rand(-8, 8)));
                $ramUsage = min(99.9, max(5.0, $baseRam + mt_rand(-6, 6)));
                $diskUsage = min(99.9, max(10.0, $baseDisk + mt_rand(-2, 3)));
                $peripherals = [
                    'keyboard' => true,
                    'mouse' => true,
                    'usb_storage_connected' => mt_rand(0, 4) === 0,
                ];

                if ($globalIndex === 7 && $hour % 6 === 0) {
                    $cpuUsage = 95.0;
                }

                if ($globalIndex === 4 && $hour % 5 === 0) {
                    $peripherals['mouse'] = false;
                }

                $telemetryInsert->execute([
                    'computer_id' => $computerId,
                    'mac_address' => $macAddress,
                    'hostname' => $hostname,
                    'ip_address' => $ipAddress,
                    'cpu_usage' => $cpuUsage,
                    'ram_usage' => $ramUsage,
                    'disk_usage' => $diskUsage,
                    'network_latency_ms' => mt_rand(5, 45),
                    'active_user' => 'student_' . str_pad((string) $globalIndex, 3, '0', STR_PAD_LEFT),
                    'peripherals' => json_encode($peripherals, JSON_THROW_ON_ERROR),
                    'received_at' => $timePoint->format('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    $pdo->commit();
    echo 'Database seeding complete.' . PHP_EOL;
} catch (Throwable $throwable) {
    $pdo->rollBack();
    throw $throwable;
}
