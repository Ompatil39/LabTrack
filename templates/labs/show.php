<?php
/** @var array<string, mixed> $lab */
/** @var bool $isAdmin */
use LabTrack\View\View;
$labId = (string) ($lab['lab_id'] ?? '');
$status = (string) ($lab['status'] ?? 'Active');
$statusClass = match (strtolower(trim($status))) {
    'active' => 'status-active',
    'inactive' => 'status-inactive',
    'in repair', 'under repair' => 'status-in-repair',
    default => 'status-inactive',
};
$stats = $lab['stats'] ?? [];
?>

<div style="margin-bottom: 1.5rem;">
    <a href="/labs" style="color: var(--muted); text-decoration: none; font-size: 0.875rem;">&larr; Back to Labs List</a>
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-top: 0.5rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <h1 style="font-size: 1.85rem; font-weight: 700; color: #0f172a;"><?= View::e($lab['lab_name'] ?? $labId) ?></h1>
                <span class="status-badge <?= $statusClass ?>"><?= View::e($status) ?></span>
            </div>
            <p style="color: var(--muted); font-size: 0.95rem; margin-top: 0.25rem;">
                Lab ID: <span style="font-family: monospace; font-weight: 600; color: #1e293b;"><?= View::e($labId) ?></span>
                &bull; <?= View::e($lab['department'] ?? '') ?>
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <?php if ($isAdmin): ?>
                <a href="/labs/<?= urlencode($labId) ?>/edit" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                    Edit Lab
                </a>
            <?php endif; ?>
            <a href="/inventory?lab_id=<?= urlencode($labId) ?>" class="btn btn-primary" onerror="this.href='html%20pages/inventory.php?lab_id=<?= urlencode($labId) ?>'">
                View Inventory
            </a>
        </div>
    </div>
</div>

<!-- Stats row -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="card" style="margin-bottom: 0;">
        <div style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--muted);">Capacity</div>
        <div style="font-size: 1.75rem; font-weight: 700; color: #0f172a; margin-top: 0.25rem;">
            <?= View::e((string)($lab['room_capacity'] ?? 0)) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--muted);">Maximum workstations</div>
    </div>

    <div class="card" style="margin-bottom: 0;">
        <div style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--muted);">Workstations / Seats</div>
        <div style="font-size: 1.75rem; font-weight: 700; color: #2563eb; margin-top: 0.25rem;">
            <?= View::e((string)($stats['workstations_count'] ?? 0)) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--muted);">Registered in data model</div>
    </div>

    <div class="card" style="margin-bottom: 0;">
        <div style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--muted);">Active Devices</div>
        <div style="font-size: 1.75rem; font-weight: 700; color: #10b981; margin-top: 0.25rem;">
            <?= View::e((string)($stats['active_devices'] ?? 0)) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--muted);">Operational hardware</div>
    </div>

    <div class="card" style="margin-bottom: 0;">
        <div style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--muted);">Under Repair</div>
        <div style="font-size: 1.75rem; font-weight: 700; color: <?= !empty($stats['repair_devices']) ? '#ef4444' : '#64748b' ?>; margin-top: 0.25rem;">
            <?= View::e((string)($stats['repair_devices'] ?? 0)) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--muted);">Hardware needing attention</div>
    </div>
</div>

<!-- Details grid -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
    <div class="card">
        <h2 style="font-size: 1.15rem; font-weight: 600; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
            Facility Information
        </h2>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; font-size: 0.9rem;">
            <div>
                <span style="color: var(--muted); display: block; font-size: 0.8rem;">Building</span>
                <strong style="color: #1e293b;"><?= View::e($lab['building'] ?? 'Not specified') ?></strong>
            </div>
            <div>
                <span style="color: var(--muted); display: block; font-size: 0.8rem;">Room Number</span>
                <strong style="color: #1e293b;"><?= View::e($lab['room_number'] ?? '—') ?></strong>
            </div>
            <div>
                <span style="color: var(--muted); display: block; font-size: 0.8rem;">Department</span>
                <strong style="color: #1e293b;"><?= View::e($lab['department'] ?? '—') ?></strong>
            </div>
            <div>
                <span style="color: var(--muted); display: block; font-size: 0.8rem;">Establishment Date</span>
                <strong style="color: #1e293b;"><?= View::e($lab['establishment_date'] ?? '—') ?></strong>
            </div>
        </div>
    </div>

    <div class="card">
        <h2 style="font-size: 1.15rem; font-weight: 600; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
            Assigned Incharge
        </h2>
        <div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.9rem;">
            <div>
                <span style="color: var(--muted); display: block; font-size: 0.8rem;">Name / Account</span>
                <strong style="color: #1e293b;">
                    <?= View::e($lab['incharge_user_full_name'] ?: ($lab['lab_incharge'] ?? '—')) ?>
                </strong>
                <?php if (!empty($lab['lab_incharge'])): ?>
                    <span style="color: var(--muted); font-size: 0.8rem;">(<?= View::e($lab['lab_incharge']) ?>)</span>
                <?php endif; ?>
            </div>
            <?php if (!empty($lab['incharge_email'])): ?>
                <div>
                    <span style="color: var(--muted); display: block; font-size: 0.8rem;">Email</span>
                    <a href="mailto:<?= View::e($lab['incharge_email']) ?>" style="color: var(--primary);">
                        <?= View::e($lab['incharge_email']) ?>
                    </a>
                </div>
            <?php endif; ?>
            <?php if (!empty($lab['incharge_contact'])): ?>
                <div>
                    <span style="color: var(--muted); display: block; font-size: 0.8rem;">Contact</span>
                    <span style="color: #1e293b;"><?= View::e($lab['incharge_contact']) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
