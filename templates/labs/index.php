<?php
/** @var array<int, array<string, mixed>> $labs */
/** @var bool $isAdmin */
use LabTrack\View\View;
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <div>
        <h1 style="font-size: 1.75rem; font-weight: 700; color: #0f172a;">Computer Labs</h1>
        <p style="color: var(--muted); font-size: 0.95rem;">Overview of laboratories, capacities, and assigned staff.</p>
    </div>
    <?php if ($isAdmin): ?>
        <a href="/labs/create" class="btn btn-primary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Add New Lab
        </a>
    <?php endif; ?>
</div>

<div class="card" style="padding: 0; overflow: hidden;">
    <div style="overflow-x: auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Lab ID</th>
                    <th>Lab Name</th>
                    <th>Department</th>
                    <th>Incharge</th>
                    <th>Location</th>
                    <th>Capacity</th>
                    <th>Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($labs)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 3rem 1rem; color: var(--muted);">
                            <p style="font-size: 1.1rem; font-weight: 500;">No laboratories found.</p>
                            <p style="font-size: 0.875rem; margin-top: 0.25rem;">
                                <?= $isAdmin ? 'Click "Add New Lab" above to register your first lab.' : 'You have not been assigned to any lab yet.' ?>
                            </p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($labs as $lab): ?>
                        <?php
                            $status = (string) ($lab['status'] ?? 'Active');
                            $statusClass = match (strtolower(trim($status))) {
                                'active' => 'status-active',
                                'inactive' => 'status-inactive',
                                'in repair', 'under repair' => 'status-in-repair',
                                default => 'status-inactive',
                            };
                            $labId = (string) ($lab['lab_id'] ?? '');
                        ?>
                        <tr>
                            <td style="font-family: monospace; font-weight: 600; color: #1e293b;">
                                <a href="/labs/<?= urlencode($labId) ?>" style="color: var(--primary); text-decoration: none;">
                                    <?= View::e($labId) ?>
                                </a>
                            </td>
                            <td style="font-weight: 600;">
                                <a href="/labs/<?= urlencode($labId) ?>" style="color: var(--text); text-decoration: none;">
                                    <?= View::e($lab['lab_name'] ?? '') ?>
                                </a>
                            </td>
                            <td><?= View::e($lab['department'] ?? '—') ?></td>
                            <td>
                                <?= View::e($lab['incharge_user_full_name'] ?: ($lab['lab_incharge'] ?? '—')) ?>
                            </td>
                            <td style="color: var(--muted); font-size: 0.85rem;">
                                <?php if (!empty($lab['building'])): ?>
                                    <?= View::e($lab['building']) ?>,
                                <?php endif; ?>
                                Room <?= View::e($lab['room_number'] ?? '—') ?>
                            </td>
                            <td>
                                <span style="font-weight: 600;"><?= View::e((string) ($lab['room_capacity'] ?? 0)) ?></span> seats
                            </td>
                            <td>
                                <span class="status-badge <?= $statusClass ?>">
                                    <?= View::e($status) ?>
                                </span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="/labs/<?= urlencode($labId) ?>" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;">
                                    View
                                </a>
                                <?php if ($isAdmin): ?>
                                    <a href="/labs/<?= urlencode($labId) ?>/edit" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;">
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
</div>
