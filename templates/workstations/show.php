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

<?php
$hasDrift = (bool) ($workstation['has_hardware_drift'] ?? ($telemetry['has_hardware_drift'] ?? false));
$driftAlerts = $openDriftAlerts ?? ($telemetry['drift_alerts'] ?? []);
?>
<!-- Prominent Drift Warning Banner -->
<div id="telemetry-drift-banner" class="alert alert-warning" style="display: <?= $hasDrift ? 'block' : 'none' ?>; margin-bottom: 1.5rem; background: #fffbeb; border: 1px solid #fde68a; border-left: 4px solid #f59e0b; padding: 1rem; border-radius: 6px;">
    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
        <span style="font-size: 1.25rem;">⚠️</span>
        <strong style="color: #92400e; font-size: 1rem;">Hardware Drift Detected!</strong>
    </div>
    <p style="font-size: 0.875rem; color: #78350f; margin-bottom: 0.75rem;">
        The workstation reported hardware specifications differing from registered asset inventory.
    </p>
    <div id="telemetry-drift-list" style="display: flex; flex-direction: column; gap: 0.5rem;">
        <?php foreach ($driftAlerts as $alert): ?>
            <?php
            $fieldName = (string) $alert['field_name'];
            $label = match ($fieldName) {
                'ram_gb' => 'RAM',
                'disk_gb' => 'Disk',
                'cpu_model' => 'CPU Model',
                'mac_address' => 'MAC Address',
                default => ucfirst(str_replace('_', ' ', $fieldName)),
            };
            ?>
            <div style="display: flex; justify-content: space-between; align-items: center; background: #ffffff; padding: 0.5rem 0.75rem; border-radius: 4px; border: 1px solid #fef3c7;">
                <span style="font-size: 0.875rem; color: #1e293b;">
                    <strong><?= View::e($label) ?> drift detected:</strong> registered <?= View::e((string)$alert['expected_value']) ?>, reported <?= View::e((string)$alert['reported_value']) ?>
                </span>
                <form method="POST" action="/workstations/<?= $wsId ?>/drift/<?= (int)$alert['drift_id'] ?>/resolve" style="margin: 0; display: inline-flex; gap: 0.25rem;">
                    <?= View::csrf() ?>
                    <button type="submit" name="action" value="Acknowledged" class="btn btn-sm btn-secondary" style="font-size: 0.75rem; padding: 0.2rem 0.5rem;">Acknowledge</button>
                    <button type="submit" name="action" value="Resolved" class="btn btn-sm btn-primary" style="font-size: 0.75rem; padding: 0.2rem 0.5rem;">Resolve</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php
$telStatus = (string) ($telemetry['status'] ?? 'Offline');
$telBadgeClass = match (strtolower($telStatus)) {
    'online' => 'status-active',
    'stale' => 'status-in-repair',
    default => 'status-inactive',
};
$lastSeen = $telemetry['last_seen_at'] ?? $workstation['last_seen_at'] ?? null;
$loggedUser = $telemetry['logged_in_user'] ?? $workstation['logged_in_user'] ?? '—';
$cpuPct = isset($telemetry['latest_cpu_pct']) ? (float)$telemetry['latest_cpu_pct'] : (isset($workstation['latest_cpu_pct']) ? (float)$workstation['latest_cpu_pct'] : 0.0);
$ramPct = isset($telemetry['latest_ram_pct']) ? (float)$telemetry['latest_ram_pct'] : (isset($workstation['latest_ram_pct']) ? (float)$workstation['latest_ram_pct'] : 0.0);
$diskPct = isset($telemetry['latest_disk_pct']) ? (float)$telemetry['latest_disk_pct'] : (isset($workstation['latest_disk_pct']) ? (float)$workstation['latest_disk_pct'] : 0.0);
$temp = isset($telemetry['cpu_temp_c']) ? (float)$telemetry['cpu_temp_c'] : (isset($workstation['cpu_temp_c']) ? (float)$workstation['cpu_temp_c'] : null);
$smartHealth = (string) ($telemetry['smart_health'] ?? $workstation['smart_health'] ?? 'UNKNOWN');
$smartClass = match (strtoupper($smartHealth)) {
    'HEALTHY' => 'status-active',
    'WARNING' => 'status-in-repair',
    'FAILING' => 'status-inactive',
    default => 'status-inactive',
};
?>
<!-- Live Host Telemetry Card -->
<div class="card" id="telemetry-card" data-workstation-id="<?= $wsId ?>" style="margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <h2 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin: 0;">Live Host Telemetry</h2>
            <span id="telemetry-status-badge" class="status-badge <?= $telBadgeClass ?>"><?= View::e($telStatus) ?></span>
        </div>
        <div style="font-size: 0.85rem; color: #64748b;">
            Last Seen: <span id="telemetry-last-seen" style="font-weight: 600; color: #334155;"><?= $lastSeen ? View::e((string)$lastSeen) : 'Never' ?></span>
            &bull; User: <span id="telemetry-logged-user" style="font-weight: 600; color: #334155;"><?= View::e((string)$loggedUser) ?></span>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
        <!-- CPU Bar -->
        <div style="background: #f8fafc; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #e2e8f0;">
            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.35rem;">
                <span style="font-weight: 600; color: #334155;">CPU Usage</span>
                <span id="telemetry-cpu-text" style="font-weight: 700; color: #0284c7;"><?= number_format($cpuPct, 1) ?>%</span>
            </div>
            <div style="background: #e2e8f0; border-radius: 9999px; height: 8px; overflow: hidden;">
                <div id="telemetry-cpu-bar" style="background: #0284c7; height: 100%; width: <?= min(100.0, max(0.0, $cpuPct)) ?>%; transition: width 0.3s ease;"></div>
            </div>
        </div>

        <!-- RAM Bar -->
        <div style="background: #f8fafc; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #e2e8f0;">
            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.35rem;">
                <span style="font-weight: 600; color: #334155;">RAM Usage</span>
                <span id="telemetry-ram-text" style="font-weight: 700; color: #8b5cf6;"><?= number_format($ramPct, 1) ?>%</span>
            </div>
            <div style="background: #e2e8f0; border-radius: 9999px; height: 8px; overflow: hidden;">
                <div id="telemetry-ram-bar" style="background: #8b5cf6; height: 100%; width: <?= min(100.0, max(0.0, $ramPct)) ?>%; transition: width 0.3s ease;"></div>
            </div>
        </div>

        <!-- Disk Bar -->
        <div style="background: #f8fafc; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #e2e8f0;">
            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.35rem;">
                <span style="font-weight: 600; color: #334155;">Disk Usage</span>
                <span id="telemetry-disk-text" style="font-weight: 700; color: #10b981;"><?= number_format($diskPct, 1) ?>%</span>
            </div>
            <div style="background: #e2e8f0; border-radius: 9999px; height: 8px; overflow: hidden;">
                <div id="telemetry-disk-bar" style="background: #10b981; height: 100%; width: <?= min(100.0, max(0.0, $diskPct)) ?>%; transition: width 0.3s ease;"></div>
            </div>
        </div>

        <!-- Temperature & SMART -->
        <div style="background: #f8fafc; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #e2e8f0; display: flex; justify-content: space-around; align-items: center;">
            <div style="text-align: center;">
                <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">CPU Temp</div>
                <div id="telemetry-temp" style="font-size: 1.1rem; font-weight: 700; color: #334155; margin-top: 0.15rem;">
                    <?= $temp !== null ? View::e(number_format($temp, 1) . '°C') : 'N/A' ?>
                </div>
            </div>
            <div style="width: 1px; height: 32px; background: #cbd5e1;"></div>
            <div style="text-align: center;">
                <div style="font-size: 0.75rem; color: #64748b; font-weight: 600; margin-bottom: 0.25rem;">S.M.A.R.T.</div>
                <span id="telemetry-smart-badge" class="status-badge <?= $smartClass ?>"><?= View::e($smartHealth) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Admin Agent Enrollment Panel -->
<?php if ($isAdmin): ?>
    <div class="card" style="margin-bottom: 1.5rem; background: #f8fafc; border: 1px dashed #cbd5e1;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h3 style="font-size: 1rem; font-weight: 700; color: #1e293b; margin: 0 0 0.25rem 0;">Workstation Monitoring Agent</h3>
                <p style="font-size: 0.85rem; color: #64748b; margin: 0;">Generate a one-time 24-hour token to link the Python background agent to this seat.</p>
            </div>
            <form method="POST" action="/workstations/<?= $wsId ?>/enroll-token">
                <?= View::csrf() ?>
                <button type="submit" class="btn btn-secondary" style="font-weight: 600;">
                    Generate Enrollment Token
                </button>
            </form>
        </div>
        <?php if (!empty($flash['enroll_token'])): ?>
            <div style="margin-top: 1rem; padding: 1rem; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px;">
                <p style="font-size: 0.875rem; font-weight: 700; color: #065f46; margin: 0 0 0.5rem 0;">
                    Enrollment Token Generated (Expires in 24 hours — displayed only once):
                </p>
                <div style="margin-bottom: 0.5rem;">
                    <label style="font-size: 0.75rem; color: #047857; font-weight: 600;">Plaintext Token:</label>
                    <input type="text" readonly value="<?= View::e((string)$flash['enroll_token']) ?>" style="width: 100%; font-family: monospace; font-size: 0.85rem; padding: 0.35rem 0.5rem; border: 1px solid #6ee7b7; border-radius: 4px; background: #fff;" onclick="this.select()">
                </div>
                <div>
                    <label style="font-size: 0.75rem; color: #047857; font-weight: 600;">Run command on workstation:</label>
                    <input type="text" readonly value="<?= View::e((string)$flash['enroll_command']) ?>" style="width: 100%; font-family: monospace; font-size: 0.85rem; padding: 0.35rem 0.5rem; border: 1px solid #6ee7b7; border-radius: 4px; background: #fff;" onclick="this.select()">
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

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

<script src="/assets/js/telemetry.js"></script>
