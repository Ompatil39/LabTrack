<?php

declare(strict_types=1);

namespace LabTrack\Tests\Feature;

use InvalidArgumentException;
use LabTrack\Service\WorkstationService;
use LabTrack\Support\DeviceStatus;
use LabTrack\Validation\WorkstationRequest;
use PHPUnit\Framework\TestCase;

class WorkstationAndSwapTest extends TestCase
{
    private function getDbConnection(): ?\mysqli
    {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $db = getenv('DB_DATABASE') ?: 'labtrack';
        $port = (int) (getenv('DB_PORT') ?: 3306);

        mysqli_report(MYSQLI_REPORT_OFF);
        try {
            $conn = @new \mysqli($host, $user, $pass, $db, $port);
            if ($conn->connect_error) {
                return null;
            }
            $conn->set_charset('utf8mb4');
            return $conn;
        } catch (\Throwable) {
            return null;
        }
    }

    public function testWorkstationCreationPayloadWith5Parts(): void
    {
        $payload = [
            'lab_id' => '1',
            'seat_code' => 'W-10',
            'pc_brand' => 'HP',
            'pc_model' => 'EliteDesk 800',
            'processor' => 'Intel Core i5-10500',
            'ram' => '16GB',
            'storage' => '256GB SSD',
            'os' => 'Windows 11 Pro',
            'monitor_brand' => 'HP 24f',
            'keyboard_brand' => 'Logitech K120',
            'mouse_brand' => 'Logitech B100',
            'ups_brand' => 'APC 600VA',
        ];

        $validation = WorkstationRequest::validateCreate($payload);
        $this->assertTrue($validation['valid']);
        $this->assertSame('W-10', $validation['data']['seat_code']);
        $this->assertCount(5, $validation['data']['parts']);

        $types = array_column($validation['data']['parts'], 'asset_type');
        $this->assertContains('PC', $types);
        $this->assertContains('Monitor', $types);
        $this->assertContains('Keyboard', $types);
        $this->assertContains('Mouse', $types);
        $this->assertContains('CPU', $types);
    }

    public function testCreateWorkstationWithPartsValidationFailsOnMissingFields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('lab_id and seat_code are required');

        /** @var \mysqli $dummyConn */
        $dummyConn = $this->createMock(\mysqli::class);
        WorkstationService::createWorkstationWithParts($dummyConn, ['lab_id' => ''], []);
    }

    public function testPartSwapLifecycleAgainstLiveDatabase(): void
    {
        $conn = $this->getDbConnection();
        if ($conn === null) {
            $this->markTestSkipped('Live MySQL server not available in current local environment; skipped live DB swap test.');
        }

        // 1. Ensure test lab exists
        $conn->query("INSERT INTO labs (lab_id, lab_name, department, room_number, status)
                      VALUES ('LAB-SWAP', 'Swap Test Lab', 'CSE', '999', 'Active')
                      ON DUPLICATE KEY UPDATE lab_name = VALUES(lab_name)");

        // 2. Create workstation with 5 parts
        $wsData = [
            'lab_id' => 'LAB-SWAP',
            'seat_code' => 'SWAP-' . uniqid(),
            'hostname' => 'HOST-SWAP-01',
            'status' => 'Active',
        ];

        $parts = [
            ['asset_type' => 'PC', 'brand_model' => 'Dell OptiPlex 7090'],
            ['asset_type' => 'Monitor', 'brand_model' => 'Dell P2419H'],
            ['asset_type' => 'Keyboard', 'brand_model' => 'Dell KB216'],
            ['asset_type' => 'Mouse', 'brand_model' => 'Dell MS116'],
            ['asset_type' => 'UPS', 'brand_model' => 'APC Back-UPS 650'],
        ];

        $created = WorkstationService::createWorkstationWithParts($conn, $wsData, $parts);
        $wsId = $created['workstation_id'];
        $this->assertGreaterThan(0, $wsId);
        $this->assertCount(5, $created['assets']);

        // Identify the created monitor
        $oldMonitor = null;
        foreach ($created['assets'] as $a) {
            if ($a['asset_type'] === 'Monitor') {
                $oldMonitor = $a;
                break;
            }
        }
        $this->assertNotNull($oldMonitor);
        $oldAssetId = (int) $oldMonitor['asset_id'];

        // 3. Register a spare replacement monitor
        $tag = 'MON-SPARE-' . uniqid();
        $conn->query("INSERT INTO assets (asset_tag, asset_type, lab_id, brand_model, status)
                      VALUES ('{$tag}', 'Monitor', 'LAB-SWAP', 'LG UltraGear 24\"', 'Active')");
        $newAssetId = (int) $conn->insert_id;

        // 4. Execute part swap: replace old monitor with replacement monitor
        $swapReason = 'Backlight dead after power surge';
        WorkstationService::swapWorkstationPart(
            $conn,
            $wsId,
            $oldAssetId,
            $newAssetId,
            $swapReason,
            DeviceStatus::FAULTY
        );

        // 5. Verify old asset is marked Faulty and its assignment ended with removed_at and reason
        $resOldAsset = $conn->query("SELECT status FROM assets WHERE asset_id = {$oldAssetId}");
        $rowOldAsset = $resOldAsset->fetch_assoc();
        $this->assertSame(DeviceStatus::FAULTY, $rowOldAsset['status']);

        $resOldAssign = $conn->query("SELECT removed_at, removal_reason FROM asset_assignments
                                      WHERE asset_id = {$oldAssetId} AND workstation_id = {$wsId}");
        $rowOldAssign = $resOldAssign->fetch_assoc();
        $this->assertNotNull($rowOldAssign['removed_at']);
        $this->assertSame($swapReason, $rowOldAssign['removal_reason']);

        // 6. Verify new asset is marked Active and has an open assignment
        $resNewAsset = $conn->query("SELECT status FROM assets WHERE asset_id = {$newAssetId}");
        $rowNewAsset = $resNewAsset->fetch_assoc();
        $this->assertSame(DeviceStatus::ACTIVE, $rowNewAsset['status']);

        $resNewAssign = $conn->query("SELECT removed_at FROM asset_assignments
                                      WHERE asset_id = {$newAssetId} AND workstation_id = {$wsId} AND removed_at IS NULL");
        $this->assertSame(1, $resNewAssign->num_rows);

        // 7. Verify full assignment history is retrievable
        $history = WorkstationService::getAssetHistory($conn, $oldAssetId);
        $this->assertNotEmpty($history);
        $this->assertSame($swapReason, $history[0]['removal_reason']);
    }
}
