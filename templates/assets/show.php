<?php

use LabTrack\View\View;

$asset = $asset ?? [];
$tag = $asset['asset_tag'] ?? '';
$status = $asset['status'] ?? 'Spare';
$statusClass = match (strtolower(str_replace(' ', '', (string) $status))) {
    'inuse' => 'badge-success',
    'spare' => 'badge-info',
    'underrepair', 'repair' => 'badge-warning',
    'retired', 'decommissioned' => 'badge-danger',
    default => 'badge-neutral',
};

$specs = [];
if (!empty($asset['specifications'])) {
    if (is_array($asset['specifications'])) {
        $specs = $asset['specifications'];
    } elseif (is_string($asset['specifications'])) {
        $decoded = json_decode($asset['specifications'], true);
        if (is_array($decoded)) {
            $specs = $decoded;
        }
    }
}
$history = $asset['history'] ?? [];
?>

<div class="page-header d-flex justify-between items-center">
    <div>
        <div class="breadcrumb">
            <a href="/inventory">Inventory</a> &rarr; <span><?= View::e($tag) ?></span>
        </div>
        <h1 class="page-title d-flex items-center gap-2">
            <span><?= View::e($tag) ?></span>
            <span class="badge <?= $statusClass ?>"><?= View::e((string) $status) ?></span>
            <span class="badge badge-purple"><?= View::e((string) ($asset['asset_type'] ?? 'Hardware')) ?></span>
        </h1>
        <p class="page-subtitle"><?= View::e((string) ($asset['brand_model'] ?? 'Asset Specifications and Assignment Lifecycle')) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="/inventory" class="btn btn-secondary">&larr; Back to Inventory</a>
        <?php if (!empty($isAdmin)): ?>
            <a href="/assets/<?= urlencode((string) $tag) ?>/edit" class="btn btn-primary">Edit Asset</a>
            <?php if (($asset['status'] ?? '') !== 'Retired'): ?>
                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#retireAssetModal">
                    Retire Asset
                </button>
            <?php endif; ?>
            <form action="/assets/<?= urlencode((string) $tag) ?>/delete" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete asset <?= View::e($tag) ?>? This cannot be undone.');">
                <input type="hidden" name="csrf_token" value="<?= View::e(function_exists('generateCsrfToken') ? generateCsrfToken() : ($_SESSION['csrf_token'] ?? '')) ?>">
                <button type="submit" class="btn btn-danger">Delete</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-2 gap-4 mt-4">
    <!-- General Information Card -->
    <div class="card">
        <div class="card-header">
            <h3>Hardware Details</h3>
        </div>
        <div class="card-body">
            <table class="table-properties">
                <tbody>
                    <tr>
                        <th style="width: 35%;">Asset Tag</th>
                        <td><strong><?= View::e($tag) ?></strong></td>
                    </tr>
                    <tr>
                        <th>Asset Type</th>
                        <td><?= View::e((string) ($asset['asset_type'] ?? 'N/A')) ?></td>
                    </tr>
                    <tr>
                        <th>Brand & Model</th>
                        <td><?= View::e((string) ($asset['brand_model'] ?? '—')) ?></td>
                    </tr>
                    <tr>
                        <th>Serial Number</th>
                        <td><code><?= View::e((string) ($asset['serial_number'] ?? '—')) ?></code></td>
                    </tr>
                    <tr>
                        <th>Current Location</th>
                        <td>
                            <?php if (!empty($asset['lab_name'])): ?>
                                <a href="/labs/<?= urlencode((string) ($asset['lab_id'] ?? '')) ?>"><?= View::e($asset['lab_name']) ?></a>
                            <?php else: ?>
                                <span class="text-muted">Unassigned Lab</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Current Assignment</th>
                        <td>
                            <?php if (!empty($asset['seat_code'])): ?>
                                <a href="/workstations/<?= urlencode((string) ($asset['workstation_id'] ?? '')) ?>" class="badge badge-info font-mono">
                                    Workstation: <?= View::e($asset['seat_code']) ?>
                                </a>
                            <?php else: ?>
                                <span class="badge badge-neutral">Standalone / Unassigned</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Procurement & Financials Card -->
    <div class="card">
        <div class="card-header">
            <h3>Procurement & Financials</h3>
        </div>
        <div class="card-body">
            <table class="table-properties">
                <tbody>
                    <tr>
                        <th style="width: 35%;">Purchase Date</th>
                        <td><?= !empty($asset['purchase_date']) ? View::e((string) $asset['purchase_date']) : '<span class="text-muted">—</span>' ?></td>
                    </tr>
                    <tr>
                        <th>Purchase Cost</th>
                        <td>
                            <?php if (isset($asset['purchase_cost']) && $asset['purchase_cost'] !== null && $asset['purchase_cost'] !== ''): ?>
                                ₹<?= View::e(number_format((float) $asset['purchase_cost'], 2)) ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Warranty Expiry</th>
                        <td>
                            <?php if (!empty($asset['warranty_until'])): ?>
                                <?php 
                                    $isExpired = strtotime((string) $asset['warranty_until']) < time();
                                ?>
                                <span class="<?= $isExpired ? 'text-danger font-bold' : 'text-success' ?>">
                                    <?= View::e((string) $asset['warranty_until']) ?>
                                    <?= $isExpired ? ' (Expired)' : ' (Active)' ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Created At</th>
                        <td><?= !empty($asset['created_at']) ? View::e((string) $asset['created_at']) : '—' ?></td>
                    </tr>
                    <tr>
                        <th>Last Updated</th>
                        <td><?= !empty($asset['updated_at']) ? View::e((string) $asset['updated_at']) : '—' ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Technical Specifications Card -->
<div class="card mt-4">
    <div class="card-header">
        <h3>Technical Specifications</h3>
    </div>
    <div class="card-body">
        <?php if (!empty($specs)): ?>
            <div class="grid grid-3 gap-3">
                <?php foreach ($specs as $specKey => $specVal): ?>
                    <div class="spec-tile p-3 bg-light rounded border">
                        <div class="text-muted text-xs uppercase font-bold"><?= View::e(str_replace('_', ' ', (string) $specKey)) ?></div>
                        <div class="text-md font-semibold mt-1">
                            <?php if (is_bool($specVal)): ?>
                                <?= $specVal ? 'Yes' : 'No' ?>
                            <?php elseif (is_array($specVal)): ?>
                                <?= View::e(json_encode($specVal)) ?>
                            <?php else: ?>
                                <?= View::e((string) $specVal) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted italic mb-0">No custom specifications defined for this asset.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Assignment & Relocation History -->
<div class="card mt-4">
    <div class="card-header d-flex justify-between items-center">
        <h3>Deployment & Assignment History</h3>
        <span class="badge badge-neutral"><?= count($history) ?> records</span>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($history)): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Workstation</th>
                            <th>Lab</th>
                            <th>Installed At</th>
                            <th>Removed At</th>
                            <th>Duration / Status</th>
                            <th>Removal Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $h): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($h['workstation_id'])): ?>
                                        <a href="/workstations/<?= urlencode((string) $h['workstation_id']) ?>" class="font-mono font-bold">
                                            <?= View::e((string) ($h['seat_code'] ?? "#{$h['workstation_id']}")) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= View::e((string) ($h['lab_name'] ?? '—')) ?></td>
                                <td><code><?= View::e((string) ($h['installed_at'] ?? '—')) ?></code></td>
                                <td>
                                    <?php if (!empty($h['removed_at'])): ?>
                                        <code><?= View::e((string) $h['removed_at']) ?></code>
                                    <?php else: ?>
                                        <span class="badge badge-success">Currently Active</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (empty($h['removed_at'])): ?>
                                        <span class="badge badge-success">Active Deployment</span>
                                    <?php else: ?>
                                        <span class="text-muted">Concluded</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= !empty($h['removal_reason']) ? View::e((string) $h['removal_reason']) : '<span class="text-muted">—</span>' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="p-4 text-center text-muted">
                No past assignment or deployment history found for this asset.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Retire Asset Modal -->
<div class="modal fade" id="retireAssetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Retire / Dispose Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="/assets/<?= urlencode((string) $tag) ?>/retire">
                <input type="hidden" name="csrf_token" value="<?= View::e(function_exists('generateCsrfToken') ? generateCsrfToken() : ($_SESSION['csrf_token'] ?? '')) ?>">
                <div class="modal-body">
                    <p class="text-muted small">
                        Retiring an asset will mark it as <strong>Retired</strong> and immediately close any active workstation assignments. The asset will remain in historical logs but will be removed from active inventory.
                    </p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Reason for Retirement *</label>
                        <select name="reason" class="form-select form-select-sm mb-2" required>
                            <option value="Hardware Failure / Beyond Economic Repair">Hardware Failure / Beyond Economic Repair</option>
                            <option value="End of Useful Life / Obsolescence">End of Useful Life / Obsolescence</option>
                            <option value="Surplus / Sold / Donated">Surplus / Sold / Donated</option>
                            <option value="Damaged / Scrapped">Damaged / Scrapped</option>
                            <option value="Stolen / Lost">Stolen / Lost</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm">Confirm Retirement</button>
                </div>
            </form>
        </div>
    </div>
</div>
