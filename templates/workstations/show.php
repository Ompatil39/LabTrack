<?php
/** @var array<string, mixed> $workstation */
/** @var array<int, array<string, mixed>> $currentParts */
/** @var array<int, array<string, mixed>> $history */
/** @var array<int, array<string, mixed>> $grievances */
/** @var array<string, array<int, array<string, mixed>>> $availableSpares */
/** @var bool $isAdmin */
use LabTrack\View\View;

$wsId = (int) $workstation['workstation_id'];
$status = (string) ($workstation['status'] ?? 'Active');
$statusClass = match (strtolower(trim($status))) {
    'active' => 'status-active',
    'inactive' => 'status-inactive',
    'under repair', 'in repair' => 'status-in-repair',
    default => 'status-inactive',
};
?>

<div style="margin-bottom: 1.5rem;">
    <a href="/workstations?lab_id=<?= urlencode((string)$workstation['lab_id']) ?>" style="color: var(--muted); text-decoration: none; font-size: 0.875rem;">
        &larr; Back to Workstations (<?= View::e($workstation['lab_name'] ?? $workstation['lab_id']) ?>)
    </a>
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-top: 0.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <h1 style="font-size: 1.85rem; font-weight: 700; color: #0f172a;">Seat: <?= View::e($workstation['seat_code']) ?></h1>
                <span class="status-badge <?= $statusClass ?>"><?= View::e($status) ?></span>
            </div>
            <p style="color: var(--muted); font-size: 0.95rem; margin-top: 0.25rem;">
                Lab: <a href="/labs/<?= urlencode((string)$workstation['lab_id']) ?>" style="color: var(--primary); text-decoration: none; font-weight: 600;"><?= View::e($workstation['lab_name'] ?? $workstation['lab_id']) ?></a>
                <?php if (!empty($workstation['hostname'])): ?>
                    &bull; Hostname: <span style="font-family: monospace; color: #334155; font-weight: 600;"><?= View::e($workstation['hostname']) ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <?php if ($isAdmin): ?>
                <a href="/workstations/<?= $wsId ?>/edit" class="btn btn-secondary">Edit Seat</a>
            <?php endif; ?>
            <a href="#swap-section" class="btn btn-primary" onclick="document.getElementById('swap-card').scrollIntoView({behavior: 'smooth'})">
                Swap Component
            </a>
        </div>
    </div>
</div>

<!-- Current Installed Parts -->
<div style="margin-bottom: 2rem;">
    <h2 style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-bottom: 1rem;">Currently Installed Hardware</h2>
    <?php if (empty($currentParts)): ?>
        <div class="card" style="text-align: center; color: var(--muted); padding: 2rem;">
            No hardware components are currently assigned to this workstation seat.
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1rem;">
            <?php foreach ($currentParts as $part): ?>
                <?php
                    $pStatus = (string) ($part['status'] ?? 'Active');
                    $pStatusClass = match (strtolower(trim($pStatus))) {
                        'active' => 'status-active',
                        'inactive' => 'status-inactive',
                        'faulty' => 'flash-error',
                        'under repair', 'in repair' => 'status-in-repair',
                        default => 'status-inactive',
                    };
                    $specs = isset($part['specs']) && is_string($part['specs']) ? json_decode($part['specs'], true) : ($part['specs'] ?? []);
                ?>
                <div class="card" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--muted);"><?= View::e($part['asset_type']) ?></span>
                            <span class="status-badge <?= $pStatusClass ?>"><?= View::e($pStatus) ?></span>
                        </div>
                        <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 0.35rem;">
                            <a href="/assets/<?= urlencode((string)$part['asset_tag']) ?>" style="color: var(--primary); text-decoration: none;">
                                <?= View::e($part['asset_tag']) ?>
                            </a>
                        </h3>
                        <p style="font-size: 0.875rem; color: #334155; margin-top: 0.25rem; font-weight: 500;">
                            <?= View::e($part['brand_model'] ?: 'Generic ' . $part['asset_type']) ?>
                        </p>
                        <?php if (!empty($part['serial_number'])): ?>
                            <p style="font-size: 0.75rem; color: var(--muted); font-family: monospace; margin-top: 0.2rem;">
                                S/N: <?= View::e($part['serial_number']) ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($specs) && is_array($specs)): ?>
                            <div style="margin-top: 0.75rem; padding-top: 0.5rem; border-top: 1px dashed var(--border); font-size: 0.8rem; color: #475569;">
                                <?php foreach ($specs as $sk => $sv): ?>
                                    <?php if ($sv !== null && $sv !== ''): ?>
                                        <div><strong><?= View::e(ucwords(str_replace('_', ' ', $sk))) ?>:</strong> <?= View::e((string)$sv) ?></div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top: 1rem; padding-top: 0.5rem; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                        <small style="color: var(--muted); font-size: 0.75rem;">Installed: <?= View::e(substr((string)($part['installed_at'] ?? ''), 0, 10)) ?></small>
                        <a href="/assets/<?= urlencode((string)$part['asset_tag']) ?>" style="font-size: 0.8rem; color: var(--primary); text-decoration: none; font-weight: 600;">Details &rarr;</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Swap Component Section -->
<div id="swap-section" style="margin-bottom: 2rem;">
    <div class="card" id="swap-card" style="border-left: 4px solid var(--primary);">
        <h2 style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;">Hardware Component Swap</h2>
        <p style="color: var(--muted); font-size: 0.875rem; margin-bottom: 1.25rem;">
            Atomically replace an installed component with a lab spare. Reassignment history is preserved automatically.
        </p>

        <form action="/workstations/<?= $wsId ?>/swap" method="POST" id="swap-form">
            <?= View::csrf() ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                <div class="form-group">
                    <label for="old_asset_id" class="form-label">Part to Remove *</label>
                    <select id="old_asset_id" name="old_asset_id" class="form-control" required onchange="filterSparesByType(this.value)">
                        <option value="">-- Select Installed Part --</option>
                        <?php foreach ($currentParts as $p): ?>
                            <option value="<?= (int)$p['asset_id'] ?>" data-type="<?= View::e($p['asset_type']) ?>">
                                <?= View::e($p['asset_type']) ?>: <?= View::e($p['asset_tag']) ?> (<?= View::e($p['brand_model'] ?: 'Generic') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="new_asset_id" class="form-label">Replacement Spare Part *</label>
                    <select id="new_asset_id" name="new_asset_id" class="form-control" required>
                        <option value="">-- Select Spare from Lab --</option>
                        <?php foreach ($availableSpares as $type => $spares): ?>
                            <optgroup label="Spares: <?= View::e($type) ?>" class="spare-group spare-group-<?= View::e($type) ?>">
                                <?php foreach ($spares as $sp): ?>
                                    <option value="<?= (int)$sp['asset_id'] ?>" data-type="<?= View::e($sp['asset_type']) ?>">
                                        <?= View::e($sp['asset_tag']) ?> &bull; <?= View::e($sp['brand_model'] ?: $sp['asset_type']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="old_status" class="form-label">Status for Removed Part *</label>
                    <select id="old_status" name="old_status" class="form-control" required>
                        <option value="Faulty">Faulty (Needs repair)</option>
                        <option value="Under Repair">Under Repair</option>
                        <option value="Inactive">Inactive (Spare / Storage)</option>
                        <option value="Retired">Retired (Decommissioned)</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-top: 0.5rem;">
                <label for="reason" class="form-label">Reason for Replacement *</label>
                <input type="text" id="reason" name="reason" class="form-control"
                       placeholder="e.g. Broken left click button, upgrading RAM, fan failure" required>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 1rem;">
                <button type="submit" class="btn btn-primary">Execute Swap &amp; Log History</button>
            </div>
        </form>
    </div>
</div>

<!-- Assignment History -->
<div style="margin-bottom: 2rem;">
    <h2 style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-bottom: 1rem;">Hardware Assignment History</h2>
    <div class="card" style="padding: 0; overflow: hidden;">
        <table class="table">
            <thead>
                <tr>
                    <th>Asset Tag</th>
                    <th>Component Type</th>
                    <th>Model / Description</th>
                    <th>Installed At</th>
                    <th>Removed At</th>
                    <th>Removal Reason</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 2rem 1rem; color: var(--muted);">
                            No historical assignment changes recorded for this seat yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td>
                                <a href="/assets/<?= urlencode((string)$h['asset_tag']) ?>" style="font-family: monospace; font-weight: 600; color: var(--primary); text-decoration: none;">
                                    <?= View::e($h['asset_tag']) ?>
                                </a>
                            </td>
                            <td><span style="font-weight: 600;"><?= View::e($h['asset_type']) ?></span></td>
                            <td><?= View::e($h['brand_model'] ?? '—') ?></td>
                            <td><?= View::e($h['installed_at'] ?? '—') ?></td>
                            <td>
                                <?php if (!empty($h['removed_at'])): ?>
                                    <span style="color: var(--muted);"><?= View::e($h['removed_at']) ?></span>
                                <?php else: ?>
                                    <span class="status-badge status-active">Currently Installed</span>
                                <?php endif; ?>
                            </td>
                            <td><?= View::e($h['removal_reason'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Open Grievances -->
<?php if (!empty($grievances)): ?>
    <div style="margin-bottom: 2rem;">
        <h2 style="font-size: 1.25rem; font-weight: 700; color: #ef4444; margin-bottom: 1rem;">Open Grievances on this Seat</h2>
        <div class="card" style="padding: 0; overflow: hidden;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Grievance ID</th>
                        <th>Submitted By</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grievances as $g): ?>
                        <tr>
                            <td style="font-weight: 600;">#<?= (int)$g['grievance_id'] ?></td>
                            <td><?= View::e($g['submitted_by']) ?></td>
                            <td><?= View::e(mb_substr((string)$g['grievance_desc'], 0, 80)) ?>...</td>
                            <td><span class="status-badge status-in-repair"><?= View::e($g['status']) ?></span></td>
                            <td><?= View::e(substr((string)$g['submission_date'], 0, 10)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<script>
function filterSparesByType(selectedOldAssetId) {
    var oldSelect = document.getElementById('old_asset_id');
    var opt = oldSelect.options[oldSelect.selectedIndex];
    var type = opt.getAttribute('data-type');
    var newSelect = document.getElementById('new_asset_id');

    if (!type) {
        return;
    }

    // Auto-focus matching group
    var groups = newSelect.getElementsByTagName('optgroup');
    for (var i = 0; i < groups.length; i++) {
        if (groups[i].classList.contains('spare-group-' + type)) {
            groups[i].style.display = '';
        } else {
            groups[i].style.display = 'none';
        }
    }
}
</script>
