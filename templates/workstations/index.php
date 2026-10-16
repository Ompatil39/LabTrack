<?php
/** @var array<int, array<string, mixed>> $workstations */
/** @var array<int, array<string, mixed>> $labs */
/** @var string $selectedLab */
/** @var bool $isAdmin */
use LabTrack\View\View;
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.75rem; font-weight: 700; color: #0f172a;">Lab Workstations</h1>
        <p style="color: var(--muted); font-size: 0.95rem;">Physical seats and assigned computing setups across laboratories.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center;">
        <form method="GET" action="/workstations" style="display: flex; gap: 0.5rem;">
            <select name="lab_id" class="form-control" style="width: auto; padding: 0.45rem 0.85rem;" onchange="this.form.submit()">
                <option value="">-- All Accessible Labs --</option>
                <?php foreach ($labs as $lab): ?>
                    <option value="<?= View::e($lab['lab_id']) ?>" <?= $selectedLab === $lab['lab_id'] ? 'selected' : '' ?>>
                        <?= View::e($lab['lab_name']) ?> (<?= View::e($lab['lab_id']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($selectedLab !== ''): ?>
                <a href="/workstations" class="btn btn-secondary" style="padding: 0.45rem 0.75rem;">Clear</a>
            <?php endif; ?>
        </form>

        <?php if ($isAdmin): ?>
            <a href="/workstations/create<?= $selectedLab !== '' ? '?lab_id=' . urlencode($selectedLab) : '' ?>" class="btn btn-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Add Workstation
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="padding: 0; overflow: hidden;">
    <div style="overflow-x: auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Seat Code</th>
                    <th>Lab Facility</th>
                    <th>Hostname</th>
                    <th>Status</th>
                    <th>Installed Hardware</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($workstations)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 3rem 1rem; color: var(--muted);">
                            <p style="font-size: 1.1rem; font-weight: 500;">No workstations found.</p>
                            <p style="font-size: 0.875rem; margin-top: 0.25rem;">
                                <?= $isAdmin ? 'Click "Add Workstation" to configure seats and components in this lab.' : 'No workstations currently assigned to your view.' ?>
                            </p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($workstations as $ws): ?>
                        <?php
                            $wsId = (int) $ws['workstation_id'];
                            $status = (string) ($ws['status'] ?? 'Active');
                            $statusClass = match (strtolower(trim($status))) {
                                'active' => 'status-active',
                                'inactive' => 'status-inactive',
                                'under repair', 'in repair' => 'status-in-repair',
                                default => 'status-inactive',
                            };
                        ?>
                        <tr>
                            <td style="font-family: monospace; font-size: 1rem; font-weight: 700; color: #1e293b;">
                                <a href="/workstations/<?= $wsId ?>" style="color: var(--primary); text-decoration: none;">
                                    <?= View::e($ws['seat_code']) ?>
                                </a>
                            </td>
                            <td>
                                <strong style="color: #1e293b;"><?= View::e($ws['lab_name'] ?? $ws['lab_id']) ?></strong>
                                <span style="display: block; font-size: 0.8rem; color: var(--muted);"><?= View::e($ws['lab_id']) ?></span>
                            </td>
                            <td>
                                <?php if (!empty($ws['hostname'])): ?>
                                    <span style="font-family: monospace; color: #475569;"><?= View::e($ws['hostname']) ?></span>
                                <?php else: ?>
                                    <span style="color: var(--muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $statusClass ?>"><?= View::e($status) ?></span>
                            </td>
                            <td>
                                <span style="font-weight: 600; color: #0f172a;"><?= View::e((string)($ws['active_parts_count'] ?? 0)) ?></span> parts active
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="/workstations/<?= $wsId ?>" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;">
                                    View Parts
                                </a>
                                <?php if ($isAdmin): ?>
                                    <a href="/workstations/<?= $wsId ?>/edit" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;">
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
