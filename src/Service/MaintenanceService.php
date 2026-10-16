<?php

declare(strict_types=1);

namespace LabTrack\Service;

use InvalidArgumentException;
use LabTrack\Repository\AssetRepository;
use LabTrack\Repository\MaintenanceRepository;

class MaintenanceService
{
    private MaintenanceRepository $maintenanceRepo;
    private AssetRepository $assetRepo;

    public function __construct(
        ?MaintenanceRepository $maintenanceRepo = null,
        ?AssetRepository $assetRepo = null
    ) {
        $this->maintenanceRepo = $maintenanceRepo ?? new MaintenanceRepository();
        $this->assetRepo = $assetRepo ?? new AssetRepository();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function logMaintenance(array $data, ?int $userUid = null): int
    {
        $assetId = (int) ($data['asset_id'] ?? 0);
        $asset = $this->assetRepo->findById($assetId);
        if ($asset === null) {
            throw new InvalidArgumentException("Asset #{$assetId} not found.");
        }

        if (empty($data['lab_id'])) {
            $data['lab_id'] = (string) $asset['lab_id'];
        }
        if (empty($data['workstation_id']) && !empty($asset['workstation_id'])) {
            $data['workstation_id'] = (int) $asset['workstation_id'];
        }

        // Compute downtime_hours if completed_at is set
        if (!empty($data['completed_at']) && !empty($data['started_at'])) {
            $start = strtotime((string) $data['started_at']);
            $end = strtotime((string) $data['completed_at']);
            if ($end >= $start && $start > 0) {
                $data['downtime_hours'] = round(($end - $start) / 3600.0, 2);
            }
        }

        $data['performed_by_uid'] = $userUid;

        $recordId = $this->maintenanceRepo->create($data);

        // Record audit
        AuditService::record('CREATE', 'maintenance', (string) $recordId, null, [
            'asset_tag' => $asset['asset_tag'],
            'type' => $data['maintenance_type'],
            'cost' => $data['cost'] ?? 0.0,
            'title' => $data['title'],
        ]);

        // Status side-effects
        if (($data['maintenance_type'] ?? '') === 'Repair' && empty($data['completed_at'])) {
            $this->assetRepo->updateStatus($assetId, 'Under Repair');
        } elseif (!empty($data['completed_at']) && ($asset['status'] ?? '') === 'Under Repair') {
            $this->assetRepo->updateStatus($assetId, 'Active');
        }

        return $recordId;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public function listMaintenance(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = max(5, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $total = $this->maintenanceRepo->countAll($filters);
        $items = $this->maintenanceRepo->findAll($filters, $perPage, $offset);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Calculate depreciation and warranty alerts report.
     *
     * @return array{
     *   warranty_alerts: array{expired: array, within_30: array, within_60: array, within_90: array},
     *   depreciation: array{assets: array, total_cost: float, total_depreciation: float, total_book_value: float},
     *   aggregate_stats: array
     * }
     */
    public function getReports(): array
    {
        // 1. Warranty Alerts
        $rawAlerts = $this->maintenanceRepo->getWarrantyAlerts(90);
        $alerts = [
            'expired' => [],
            'within_30' => [],
            'within_60' => [],
            'within_90' => [],
        ];

        foreach ($rawAlerts as $a) {
            $days = (int) $a['days_left'];
            if ($days < 0) {
                $alerts['expired'][] = $a;
            } elseif ($days <= 30) {
                $alerts['within_30'][] = $a;
            } elseif ($days <= 60) {
                $alerts['within_60'][] = $a;
            } else {
                $alerts['within_90'][] = $a;
            }
        }

        // 2. Straight-line Depreciation
        $depAssets = $this->maintenanceRepo->getAssetsForDepreciation();
        $processedDep = [];
        $totalCost = 0.0;
        $totalDep = 0.0;
        $totalBook = 0.0;
        $now = time();

        foreach ($depAssets as $asset) {
            $purchaseCost = (float) ($asset['purchase_cost'] ?? 0.0);
            $salvageValue = (float) ($asset['salvage_value'] ?? 0.0);
            $usefulLife = max(1, (int) ($asset['useful_life_years'] ?? 5));

            $ageYears = 0.0;
            if (!empty($asset['purchase_date'])) {
                $purchaseTs = strtotime((string) $asset['purchase_date']);
                if ($purchaseTs > 0 && $now > $purchaseTs) {
                    $ageYears = round(($now - $purchaseTs) / (365.25 * 86400.0), 2);
                }
            }

            // Straight-line formula:
            // Annual Depreciation = (Purchase Cost - Salvage Value) / Useful Life
            // Book Value = max(Salvage Value, Purchase Cost - (Annual Depreciation * Age))
            $depreciableAmount = max(0.0, $purchaseCost - $salvageValue);
            $annualDepreciation = $depreciableAmount / $usefulLife;
            $accumulatedDepreciation = min($depreciableAmount, round($annualDepreciation * $ageYears, 2));
            $bookValue = max($salvageValue, round($purchaseCost - $accumulatedDepreciation, 2));

            $totalCost += $purchaseCost;
            $totalDep += $accumulatedDepreciation;
            $totalBook += $bookValue;

            $asset['age_years'] = $ageYears;
            $asset['annual_depreciation'] = round($annualDepreciation, 2);
            $asset['accumulated_depreciation'] = $accumulatedDepreciation;
            $asset['book_value'] = $bookValue;

            $processedDep[] = $asset;
        }

        // 3. Aggregate Stats
        $aggregateStats = $this->maintenanceRepo->getAggregateStats();

        return [
            'warranty_alerts' => $alerts,
            'depreciation' => [
                'assets' => $processedDep,
                'total_cost' => round($totalCost, 2),
                'total_depreciation' => round($totalDep, 2),
                'total_book_value' => round($totalBook, 2),
            ],
            'aggregate_stats' => $aggregateStats,
        ];
    }
}
