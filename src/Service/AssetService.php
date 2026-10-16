<?php

declare(strict_types=1);

namespace LabTrack\Service;

use InvalidArgumentException;
use LabTrack\AssetTagGenerator;
use LabTrack\Repository\AssetRepository;
use LabTrack\Repository\LabRepository;
use LabTrack\WorkstationService;
use RuntimeException;

class AssetService
{
    private AssetRepository $assetRepository;
    private LabRepository $labRepository;

    public function __construct(?AssetRepository $assetRepository = null, ?LabRepository $labRepository = null)
    {
        $this->assetRepository = $assetRepository ?? new AssetRepository();
        $this->labRepository = $labRepository ?? new LabRepository();
    }

    /**
     * @param array $filters
     * @param int $page
     * @param int $perPage
     * @param int|null $userUid
     * @param bool $isAdmin
     * @param string $username
     * @param string $fullName
     * @return array{items: array, total: int, page: int, per_page: int, total_pages: int}
     */
    public function listAssets(
        array $filters = [],
        int $page = 1,
        int $perPage = 25,
        ?int $userUid = null,
        bool $isAdmin = false,
        string $username = '',
        string $fullName = ''
    ): array {
        $page = max(1, $page);
        $perPage = max(5, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $total = $this->assetRepository->countAll($filters, $userUid, $isAdmin, $username, $fullName);
        $items = $this->assetRepository->findAll($filters, $perPage, $offset, $userUid, $isAdmin, $username, $fullName);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function getAssetByTag(
        string $tag,
        ?int $userUid = null,
        bool $isAdmin = false,
        string $username = '',
        string $fullName = ''
    ): ?array {
        $asset = $this->assetRepository->findByTag($tag);
        if ($asset === null) {
            return null;
        }

        $labId = (string) $asset['lab_id'];
        if (!$this->labRepository->canUserAccessLab($labId, $userUid, $isAdmin, $username, $fullName)) {
            throw new RuntimeException("Access denied to asset '{$tag}'.");
        }

        // Decode JSON specs if present
        if (isset($asset['specs']) && is_string($asset['specs'])) {
            $asset['specs'] = json_decode($asset['specs'], true) ?: [];
        }

        // Get assignment history if mysqli is available
        $mysqli = function_exists('db_mysqli') ? db_mysqli() : null;
        $history = [];
        if ($mysqli !== null) {
            $history = WorkstationService::getAssetHistory($mysqli, (int) $asset['asset_id']);
        }
        $asset['history'] = $history;

        return $asset;
    }

    public function registerAsset(array $data): string
    {
        $assetType = (string) ($data['asset_type'] ?? '');
        $labId = (string) ($data['lab_id'] ?? '');

        if ($assetType === '' || $labId === '') {
            throw new InvalidArgumentException('Asset type and lab_id are required.');
        }

        // Generate atomic tag using AssetTagGenerator
        $mysqli = function_exists('db_mysqli') ? db_mysqli() : null;
        if ($mysqli !== null) {
            $tag = AssetTagGenerator::nextTag($mysqli, $assetType);
        } else {
            // Fallback for offline testing without live DB
            $prefix = AssetTagGenerator::getPrefixMap()[$assetType] ?? 'AST';
            $tag = sprintf('%s-%d-%04d', $prefix, (int) date('Y'), random_int(1000, 9999));
        }

        $data['asset_tag'] = $tag;
        $this->assetRepository->create($data);

        AuditService::record('CREATE', 'asset', $tag, null, $data);

        return $tag;
    }

    public function updateAsset(int $assetId, array $data): bool
    {
        $existing = $this->assetRepository->findById($assetId);
        if ($existing === null) {
            throw new InvalidArgumentException("Asset #{$assetId} not found.");
        }

        $res = $this->assetRepository->update($assetId, $data);
        if ($res) {
            AuditService::record('UPDATE', 'asset', (string) $existing['asset_tag'], $existing, $data);
        }

        return $res;
    }

    public function updateStatus(int $assetId, string $status): bool
    {
        $res = $this->assetRepository->updateStatus($assetId, $status);
        if ($res) {
            AuditService::record('STATUS_CHANGE', 'asset', (string) $assetId, null, ['status' => $status]);
        }
        return $res;
    }

    public function deleteAsset(int $assetId): bool
    {
        $existing = $this->assetRepository->findById($assetId);
        $res = $this->assetRepository->delete($assetId);
        if ($res) {
            $tag = $existing !== null ? (string) $existing['asset_tag'] : (string) $assetId;
            AuditService::record('DELETE', 'asset', $tag, $existing, null);
        }
        return $res;
    }

    public function retireAsset(
        string $tag,
        string $reason,
        ?int $userUid = null,
        bool $isAdmin = false,
        string $username = '',
        string $fullName = ''
    ): bool {
        $asset = $this->assetRepository->findByTag($tag);
        if ($asset === null) {
            throw new InvalidArgumentException("Asset '{$tag}' not found.");
        }

        $labId = (string) $asset['lab_id'];
        if (!$this->labRepository->canUserAccessLab($labId, $userUid, $isAdmin, $username, $fullName)) {
            throw new RuntimeException("Access denied to retire asset '{$tag}'.");
        }

        $res = $this->assetRepository->retire((int) $asset['asset_id'], $reason);
        if ($res) {
            AuditService::record('RETIRE', 'asset', $tag, $asset, [
                'status' => 'Retired',
                'retire_reason' => $reason,
            ]);
        }

        return $res;
    }
}
