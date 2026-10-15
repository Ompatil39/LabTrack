<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use LabTrack\ApiResponse;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed.', 405);
    exit;
}

function request_headers_normalized(): array
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    if (!is_array($headers)) {
        $headers = [];
    }

    $normalized = [];
    foreach ($headers as $name => $value) {
        $normalized[strtolower((string) $name)] = is_array($value) ? implode(',', $value) : (string) $value;
    }

    return $normalized;
}

function is_authenticated_agent(array $headers): bool
{
    $config = app_config();
    $agentKey = $config['agent']['ingest_key'];
    $headerKey = $headers['x-agent-key'] ?? '';

    if ($agentKey !== '' && hash_equals($agentKey, $headerKey)) {
        return true;
    }

    $authorization = $headers['authorization'] ?? '';
    if (!str_starts_with($authorization, 'Bearer ')) {
        return false;
    }

    $token = trim(substr($authorization, 7));
    if ($token === '') {
        return false;
    }

    try {
        JWT::decode($token, new Key($config['jwt']['secret'], 'HS256'));
        return true;
    } catch (Throwable) {
        return false;
    }
}

function collect_missing_peripherals(array $peripherals): string
{
    $missing = [];

    foreach ($peripherals as $name => $value) {
        if ($value === false) {
            $missing[(string) $name] = true;
        }
    }

    return json_encode($missing, JSON_THROW_ON_ERROR);
}

$headers = request_headers_normalized();
if (!is_authenticated_agent($headers)) {
    ApiResponse::error('Unauthorized agent request.', 401);
    exit;
}

$rawInput = file_get_contents('php://input');
if ($rawInput === false) {
    ApiResponse::error('Unable to read request body.', 400);
    exit;
}

$payload = json_decode($rawInput, true);
if (!is_array($payload)) {
    ApiResponse::error('Invalid JSON payload.', 422);
    exit;
}

$requiredFields = ['mac_address', 'hostname', 'ip_address', 'cpu_usage', 'ram_usage', 'disk_usage'];
foreach ($requiredFields as $field) {
    if (!array_key_exists($field, $payload)) {
        ApiResponse::error('Validation failed.', 422, ['missing_field' => $field]);
        exit;
    }
}

if (!preg_match('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', (string) $payload['mac_address'])) {
    ApiResponse::error('Validation failed.', 422, ['invalid_field' => 'mac_address']);
    exit;
}

$cpuUsage = (float) $payload['cpu_usage'];
$ramUsage = (float) $payload['ram_usage'];
$diskUsage = (float) $payload['disk_usage'];
$networkLatency = isset($payload['network_latency_ms']) ? (int) $payload['network_latency_ms'] : null;

foreach (['cpu_usage' => $cpuUsage, 'ram_usage' => $ramUsage, 'disk_usage' => $diskUsage] as $metricName => $value) {
    if ($value < 0 || $value > 100) {
        ApiResponse::error('Validation failed.', 422, ['invalid_field' => $metricName]);
        exit;
    }
}

$peripherals = is_array($payload['peripherals'] ?? null) ? $payload['peripherals'] : [];
$missingPeripherals = collect_missing_peripherals($peripherals);

$pdo = db_pdo();

$staleUpdate = $pdo->prepare(
    "UPDATE computers
     SET status = 'offline'
     WHERE last_heartbeat_at IS NOT NULL
       AND last_heartbeat_at < (NOW() - INTERVAL 30 SECOND)
       AND status <> 'offline'"
);

$upsert = $pdo->prepare(
    "INSERT INTO computers
      (lab_id, mac_address, hostname, ip_address, status, cpu_usage, ram_usage, disk_usage, active_user, missing_peripherals, last_heartbeat_at)
     VALUES
      (:lab_id, :mac_address, :hostname, :ip_address, 'online', :cpu_usage, :ram_usage, :disk_usage, :active_user, :missing_peripherals, NOW())
     ON DUPLICATE KEY UPDATE
      hostname = VALUES(hostname),
      ip_address = VALUES(ip_address),
      status = 'online',
      cpu_usage = VALUES(cpu_usage),
      ram_usage = VALUES(ram_usage),
      disk_usage = VALUES(disk_usage),
      active_user = VALUES(active_user),
      missing_peripherals = VALUES(missing_peripherals),
      last_heartbeat_at = NOW()"
);

$selectComputer = $pdo->prepare('SELECT computer_id FROM computers WHERE mac_address = :mac_address LIMIT 1');

$historyInsert = $pdo->prepare(
    'INSERT INTO telemetry_logs
      (computer_id, mac_address, hostname, ip_address, cpu_usage, ram_usage, disk_usage, network_latency_ms, active_user, peripherals)
     VALUES
      (:computer_id, :mac_address, :hostname, :ip_address, :cpu_usage, :ram_usage, :disk_usage, :network_latency_ms, :active_user, :peripherals)'
);

$labId = isset($payload['lab_id']) && is_string($payload['lab_id']) && $payload['lab_id'] !== ''
    ? $payload['lab_id']
    : 'LAB001';

$activeUser = isset($payload['active_user']) && is_string($payload['active_user'])
    ? $payload['active_user']
    : null;

try {
    $pdo->beginTransaction();

    $staleUpdate->execute();

    $upsert->execute([
        'lab_id' => $labId,
        'mac_address' => strtoupper((string) $payload['mac_address']),
        'hostname' => (string) $payload['hostname'],
        'ip_address' => (string) $payload['ip_address'],
        'cpu_usage' => $cpuUsage,
        'ram_usage' => $ramUsage,
        'disk_usage' => $diskUsage,
        'active_user' => $activeUser,
        'missing_peripherals' => $missingPeripherals,
    ]);

    $selectComputer->execute(['mac_address' => strtoupper((string) $payload['mac_address'])]);
    $computer = $selectComputer->fetch();

    $historyInsert->execute([
        'computer_id' => $computer['computer_id'] ?? null,
        'mac_address' => strtoupper((string) $payload['mac_address']),
        'hostname' => (string) $payload['hostname'],
        'ip_address' => (string) $payload['ip_address'],
        'cpu_usage' => $cpuUsage,
        'ram_usage' => $ramUsage,
        'disk_usage' => $diskUsage,
        'network_latency_ms' => $networkLatency,
        'active_user' => $activeUser,
        'peripherals' => json_encode($peripherals, JSON_THROW_ON_ERROR),
    ]);

    $pdo->commit();

    ApiResponse::success([
        'mac_address' => strtoupper((string) $payload['mac_address']),
        'status' => 'online',
        'last_heartbeat_at' => gmdate('c'),
    ], 202);
} catch (Throwable $throwable) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    app_logger()->error('telemetry_ingestion_failure', ['error' => $throwable->getMessage()]);
    ApiResponse::error('Failed to ingest telemetry payload.', 500);
}
