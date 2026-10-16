<?php
/** @var array<int, array<string, mixed>> $assets */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var array<string, mixed> $filters */
/** @var array<int, array<string, mixed>> $labs */
/** @var bool $isAdmin */
use LabTrack\View\View;

$assetTypes = ['PC', 'Monitor', 'Keyboard', 'Mouse', 'CPU', 'Printer', 'Switch', 'AccessPoint', 'Other'];
$statuses = ['Active', 'Inactive', 'Under Repair', 'Faulty', 'Retired'];
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.75rem; font-weight: 700; color: #0f172a;">Hardware &amp; Asset Inventory</h1>
        <p style="color: var(--muted); font-size: 0.95rem;">Track physical IT assets, component assignments, and spare equipment.</p>
    </div>
    <?php if ($isAdmin): ?>
        <a href="/assets/create" class="btn btn-primary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Register Asset
        </a>
    <?php endif; ?>
</div>

<!-- Filter Bar -->
<div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
    <form method="GET" action="/inventory" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) auto; gap: 0.75rem; align-items: flex-end;">
        <div>
            <label class="form-label" style="font-size: 0.8rem;">Lab Facility</label>
            <select name="lab_id" class="form-control" style="padding: 0.45rem 0.65rem; font-size: 0.875rem;">
                <option value="">-- All Labs --</option>
                <?php foreach ($labs as $lab): ?>
                    <option value="<?= View::e($lab['lab_id']) ?>" <?= ($filters['lab_id'] ?? '') === $lab['lab_id'] ? 'selected' : '' ?>>
                        <?= View::e($lab['lab_name']) ?> (<?= View::e($lab['lab_id']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="form-label" style="font-size: 0.8rem;">Component Type</label>
            <select name="asset_type" class="form-control" style="padding: 0.45rem 0.65rem; font-size: 0.875rem;">
                <option value="">-- All Types --</option>
                <?php foreach ($assetTypes as $t): ?>
                    <option value="<?= View::e($t) ?>" <?= ($filters['asset_type'] ?? '') === $t ? 'selected' : '' ?>><?= View::e($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="form-label" style="font-size: 0.8rem;">Status</label>
            <select name="status" class="form-control" style="padding: 0.45rem 0.65rem; font-size: 0.875rem;">
                <option value="">-- All Statuses --</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= View::e($s) ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= View::e($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="form-label" style="font-size: 0.8rem;">Search (Tag, S/N, Seat)</label>
            <input type="text" name="search" class="form-control" placeholder="Search keywords..."
                   value="<?= View::e($filters['search'] ?? '') ?>" style="padding: 0.45rem 0.65rem; font-size: 0.875rem;">
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="padding: 0.45rem 1rem;">Filter</button>
            <a href="/inventory" class="btn btn-secondary" style="padding: 0.45rem 0.75rem;">Clear</a>
        </div>
    </form>
</div>

<!-- Assets Table -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div style="overflow-x: auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Asset Tag</th>
                    <th>Type</th>
                    <th>Model / Description</th>
                    <th>Serial Number</th>
                    <th>Lab</th>
                    <th>Assignment</th>
                    <th>Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($assets)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 3rem 1rem; color: var(--muted);">
                            <p style="font-size: 1.1rem; font-weight: 500;">No assets match your search criteria.</p>
                            <p style="font-size: 0.875rem; margin-top: 0.25rem;">Try adjusting or clearing your filters above.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($assets as $asset): ?>
                        <?php
                            $tag = (string) $asset['asset_tag'];
                            $status = (string) ($asset['status'] ?? 'Active');
                            $statusClass = match (strtolower(trim($status))) {
                                'active' => 'status-active',
                                'inactive' => 'status-inactive',
                                'faulty' => 'flash-error',
                                'under repair', 'in repair' => 'status-in-repair',
                                default => 'status-inactive',
                            };
                        ?>
                        <tr>
                            <td style="font-family: monospace; font-weight: 700;">
                                <a href="/assets/<?= urlencode($tag) ?>" style="color: var(--primary); text-decoration: none;">
                                    <?= View::e($tag) ?>
                                </a>
                            </td>
                            <td>
                                <span style="font-weight: 600; font-size: 0.85rem; background: #e0f2fe; color: #0369a1; padding: 0.15rem 0.5rem; border-radius: 4px;">
                                    <?= View::e($asset['asset_type']) ?>
                                </span>
                            </td>
                            <td><?= View::e($asset['brand_model'] ?: '—') ?></td>
                            <td style="font-family: monospace; font-size: 0.85rem; color: var(--muted);">
                                <?= View::e($asset['serial_number'] ?: '—') ?>
                            </td>
                            <td>
                                <span style="font-weight: 500;"><?= View::e($asset['lab_id']) ?></span>
                            </td>
                            <td>
                                <?php if (!empty($asset['seat_code'])): ?>
                                    <a href="/workstations/<?= (int)$asset['workstation_id'] ?>" style="color: #047857; font-weight: 600; text-decoration: none;">
                                        Seat <?= View::e($asset['seat_code']) ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: #64748b; font-size: 0.8rem; background: #f1f5f9; padding: 0.15rem 0.45rem; border-radius: 4px;">
                                        Spare / In Storage
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $statusClass ?>"><?= View::e($status) ?></span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="/assets/<?= urlencode($tag) ?>" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;">
                                    View
                                </a>
                                <?php if ($isAdmin): ?>
                                    <a href="/assets/<?= urlencode($tag) ?>/edit" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;">
                                        Edit
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div style="padding: 1rem; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); background: #f8fafc; font-size: 0.875rem;">
            <div>Showing page <?= $page ?> of <?= $totalPages ?> (<?= $total ?> total assets)</div>
            <div style="display: flex; gap: 0.35rem;">
                <?php
                    $qParams = array_filter($filters);
                    for ($p = 1; $p <= $totalPages; $p++):
                        $qParams['page'] = $p;
                        $pageUrl = '/inventory?' . http_build_query($qParams);
                ?>
                    <a href="<?= View::e($pageUrl) ?>" class="btn btn-secondary" style="padding: 0.3rem 0.65rem; font-size: 0.8rem; <?= $p === $page ? 'background: var(--primary); color: white; border-color: var(--primary);' : '' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
