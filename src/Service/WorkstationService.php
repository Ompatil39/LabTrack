<?php

declare(strict_types=1);

namespace LabTrack\Service;

use LabTrack\Support\AssetTagGenerator;

/**
 * Transactional domain service for Workstation and Asset operations.
 *
 * All methods that modify data use explicit MySQL transactions to ensure
 * atomicity. Callers receive clear typed returns or exceptions on failure.
 */
final class WorkstationService
{
    /**
     * Create a complete workstation with all its parts in a single transaction.
     *
     * $wsData keys:
     *   - lab_id        (string, required)
     *   - seat_code     (string, required, e.g. 'A-01')
     *   - hostname      (string, optional)
     *   - status        ('Active'|'Inactive'|'Under Repair', default 'Active')
     *
     * $partsData: array of arrays, each with:
     *   - asset_type    (string, required: 'PC'|'Monitor'|'Keyboard'|'Mouse'|'CPU')
     *   - brand_model   (string, optional)
     *   - serial_number (string, optional)
     *   - specs         (array, optional — will be JSON-encoded)
     *   - status        (string, optional, default 'Active')
     *   - purchase_date (string, optional, 'YYYY-MM-DD')
     *   - warranty_until(string, optional, 'YYYY-MM-DD')
     *   - purchase_cost (float, optional)
     *
     * Returns:
     *   [
     *     'workstation_id' => int,
     *     'assets'         => [asset_id => int, asset_tag => string, asset_type => string] (per part)
     *   ]
     *
     * @throws \RuntimeException on any DB error (transaction rolled back)
     */
    public static function createWorkstationWithParts(
        \mysqli $conn,
        array $wsData,
        array $partsData
    ): array {
        $labId = (string) ($wsData['lab_id'] ?? '');
        $seatCode = (string) ($wsData['seat_code'] ?? '');
        $hostname = $wsData['hostname'] ?? null;
        $wsStatus = $wsData['status'] ?? 'Active';

        if ($labId === '' || $seatCode === '') {
            throw new \InvalidArgumentException('lab_id and seat_code are required');
        }

        $conn->begin_transaction();

        try {
            // 1. Create workstation
            $ws = $conn->prepare(
                'INSERT INTO workstations (lab_id, seat_code, hostname, status) VALUES (?, ?, ?, ?)'
            );
            if (!$ws) {
                throw new \RuntimeException('Prepare workstations failed: ' . $conn->error);
            }
            $ws->bind_param('ssss', $labId, $seatCode, $hostname, $wsStatus);
            if (!$ws->execute()) {
                throw new \RuntimeException('Insert workstation failed: ' . $ws->error);
            }
            $workstationId = (int) $conn->insert_id;
            $ws->close();

            // 2. Create assets and assignments
            $pcAssetId = null;
            $createdAssets = [];

            foreach ($partsData as $partData) {
                $assetType = (string) ($partData['asset_type'] ?? '');
                $brandModel = $partData['brand_model'] ?? null;
                $serialNumber = $partData['serial_number'] ?? null;
                $assetStatus = $partData['status'] ?? 'Active';
                $purchaseDate = $partData['purchase_date'] ?? null;
                $warrantyUntil = $partData['warranty_until'] ?? null;
                $purchaseCost = isset($partData['purchase_cost']) ? (float) $partData['purchase_cost'] : null;
                $specs = isset($partData['specs']) && is_array($partData['specs'])
                                ? json_encode($partData['specs'], JSON_UNESCAPED_UNICODE)
                                : null;

                // Generate atomic tag
                $assetTag = AssetTagGenerator::nextTag($conn, $assetType);

                // Insert asset
                $aStmt = $conn->prepare(
                    'INSERT INTO assets (asset_tag, asset_type, lab_id, brand_model, serial_number, status,
                                         purchase_date, warranty_until, purchase_cost, specs)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                if (!$aStmt) {
                    throw new \RuntimeException('Prepare assets failed: ' . $conn->error);
                }
                $aStmt->bind_param(
                    'ssssssssds',
                    $assetTag,
                    $assetType,
                    $labId,
                    $brandModel,
                    $serialNumber,
                    $assetStatus,
                    $purchaseDate,
                    $warrantyUntil,
                    $purchaseCost,
                    $specs
                );
                if (!$aStmt->execute()) {
                    throw new \RuntimeException('Insert asset failed: ' . $aStmt->error);
                }
                $assetId = (int) $conn->insert_id;
                $aStmt->close();

                if ($assetType === 'PC') {
                    $pcAssetId = $assetId;
                }

                $createdAssets[] = ['asset_id' => $assetId, 'asset_tag' => $assetTag, 'asset_type' => $assetType];
            }

            // 3. Insert asset_assignments (PC as root, peripherals pointing to PC as parent)
            foreach ($createdAssets as $asset) {
                $parentId = ($asset['asset_type'] !== 'PC') ? $pcAssetId : null;
                self::insertAssignment($conn, $asset['asset_id'], $workstationId, $parentId);
            }

            $conn->commit();

            AuditService::record('CREATE', 'workstation', (string) $workstationId, null, [
                'seat_code' => $seatCode,
                'lab_id' => $labId,
                'hostname' => $hostname,
                'asset_count' => count($createdAssets),
            ]);

            return [
                'workstation_id' => $workstationId,
                'assets' => $createdAssets,
            ];
        } catch (\Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }

    /**
     * Get complete workstation details including current parts, history, and grievances.
     */
    public static function getWorkstationDetails(\mysqli $conn, int $workstationId): ?array
    {
        // Workstation metadata
        $ws = self::fetchOne(
            $conn,
            'SELECT * FROM workstations WHERE workstation_id = ? LIMIT 1',
            'i',
            [$workstationId]
        );
        if ($ws === null) {
            return null;
        }

        // Currently installed assets
        $currentParts = self::fetchAll(
            $conn,
            'SELECT a.*, aa.assignment_id, aa.installed_at, aa.parent_asset_id
             FROM asset_assignments aa
             JOIN assets a ON a.asset_id = aa.asset_id
             WHERE aa.workstation_id = ? AND aa.removed_at IS NULL
             ORDER BY a.asset_type',
            'i',
            [$workstationId]
        );

        // Full assignment history
        $history = self::fetchAll(
            $conn,
            'SELECT aa.*, a.asset_tag, a.asset_type, a.brand_model
             FROM asset_assignments aa
             JOIN assets a ON a.asset_id = aa.asset_id
             WHERE aa.workstation_id = ?
             ORDER BY aa.installed_at DESC',
            'i',
            [$workstationId]
        );

        // Open grievances linked to this workstation or its current assets
        $currentAssetIds = array_column($currentParts, 'asset_id');
        $grievances = [];
        if (!empty($currentAssetIds)) {
            $placeholders = implode(',', array_fill(0, count($currentAssetIds), '?'));
            $types = 'i' . str_repeat('i', count($currentAssetIds));
            $params = array_merge([$workstationId], array_values($currentAssetIds));
            $grievances = self::fetchAll(
                $conn,
                "SELECT * FROM grievances
                 WHERE (workstation_id = ? OR asset_id IN ({$placeholders}))
                   AND status NOT IN ('Resolved','Closed')
                 ORDER BY submission_date DESC",
                $types,
                $params
            );
        } else {
            $grievances = self::fetchAll(
                $conn,
                "SELECT * FROM grievances WHERE workstation_id = ? AND status NOT IN ('Resolved','Closed')",
                'i',
                [$workstationId]
            );
        }

        return [
            'workstation' => $ws,
            'current_parts' => $currentParts,
            'history' => $history,
            'grievances' => $grievances,
        ];
    }

    /**
     * Get the full assignment history of a specific physical asset.
     */
    public static function getAssetHistory(\mysqli $conn, int $assetId): array
    {
        return self::fetchAll(
            $conn,
            'SELECT w.seat_code, w.lab_id, aa.installed_at, aa.removed_at, aa.removal_reason,
                    aa.assignment_id, aa.workstation_id
             FROM asset_assignments aa
             JOIN workstations w ON w.workstation_id = aa.workstation_id
             WHERE aa.asset_id = ?
             ORDER BY aa.installed_at DESC',
            'i',
            [$assetId]
        );
    }

    /**
     * Swap one installed part at a workstation with another asset.
     *
     * @throws \RuntimeException  If old asset is not currently installed, or new asset is active elsewhere
     * @throws \LogicException    If constraint validation fails
     */
    public static function swapWorkstationPart(
        \mysqli $conn,
        int $workstationId,
        int $oldAssetId,
        int $newAssetId,
        string $reason,
        ?string $newStatusForOldAsset = 'Faulty'
    ): void {
        $conn->begin_transaction();

        try {
            // Verify old asset is currently installed at this workstation
            $oldAssignment = self::fetchOne(
                $conn,
                'SELECT assignment_id, parent_asset_id FROM asset_assignments
                 WHERE workstation_id = ? AND asset_id = ? AND removed_at IS NULL LIMIT 1',
                'ii',
                [$workstationId, $oldAssetId]
            );

            if ($oldAssignment === null) {
                throw new \RuntimeException(
                    "Asset #{$oldAssetId} is not currently installed at workstation #{$workstationId}"
                );
            }

            // Check if new asset is currently active elsewhere — close it cleanly
            $newCurrentAssignment = self::fetchOne(
                $conn,
                'SELECT assignment_id, workstation_id FROM asset_assignments WHERE asset_id = ? AND removed_at IS NULL LIMIT 1',
                'i',
                [$newAssetId]
            );

            if ($newCurrentAssignment !== null) {
                // Close existing assignment for new asset
                $closeStmt = $conn->prepare(
                    'UPDATE asset_assignments SET removed_at = NOW(), removal_reason = ? WHERE assignment_id = ?'
                );
                if (!$closeStmt) {
                    throw new \RuntimeException('Prepare close assignment failed: ' . $conn->error);
                }
                $closedReason = "Reassigned to workstation #{$workstationId} (swap)";
                $closeStmt->bind_param('si', $closedReason, $newCurrentAssignment['assignment_id']);
                $closeStmt->execute();
                $closeStmt->close();
            }

            // Close old assignment
            $removeStmt = $conn->prepare(
                'UPDATE asset_assignments SET removed_at = NOW(), removal_reason = ? WHERE assignment_id = ?'
            );
            if (!$removeStmt) {
                throw new \RuntimeException('Prepare remove failed: ' . $conn->error);
            }
            $removeStmt->bind_param('si', $reason, $oldAssignment['assignment_id']);
            $removeStmt->execute();
            $removeStmt->close();

            // Update old asset status
            if ($newStatusForOldAsset !== null) {
                $statusStmt = $conn->prepare('UPDATE assets SET status = ? WHERE asset_id = ?');
                if ($statusStmt) {
                    $statusStmt->bind_param('si', $newStatusForOldAsset, $oldAssetId);
                    $statusStmt->execute();
                    $statusStmt->close();
                }
            }

            // Insert new assignment (preserving parent_asset_id from old assignment)
            $parentAssetId = $oldAssignment['parent_asset_id'];
            self::insertAssignment($conn, $newAssetId, $workstationId, $parentAssetId);

            // Set new asset status to Active
            $conn->query("UPDATE assets SET status = 'Active' WHERE asset_id = {$newAssetId}");

            $conn->commit();

            AuditService::record('SWAP', 'workstation', (string) $workstationId, [
                'old_asset_id' => $oldAssetId,
            ], [
                'new_asset_id' => $newAssetId,
                'reason' => $reason,
                'status_for_old' => $newStatusForOldAsset,
            ]);
        } catch (\Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private static function insertAssignment(
        \mysqli $conn,
        int $assetId,
        int $workstationId,
        ?int $parentAssetId
    ): void {
        $stmt = $conn->prepare(
            'INSERT INTO asset_assignments (asset_id, workstation_id, parent_asset_id) VALUES (?, ?, ?)'
        );
        if (!$stmt) {
            throw new \RuntimeException('Prepare assignment failed: ' . $conn->error);
        }
        $stmt->bind_param('iii', $assetId, $workstationId, $parentAssetId);
        if (!$stmt->execute()) {
            throw new \RuntimeException('Insert assignment failed: ' . $stmt->error);
        }
        $stmt->close();
    }

    private static function fetchOne(\mysqli $conn, string $sql, string $types = '', array $params = []): ?array
    {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        if ($types !== '' && !empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }

    private static function fetchAll(\mysqli $conn, string $sql, string $types = '', array $params = []): array
    {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        if ($types !== '' && !empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }
}

class_alias(WorkstationService::class, 'LabTrack\WorkstationService');
