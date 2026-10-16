<?php
/** @var array<string, mixed> $workstation */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
use LabTrack\View\View;

$wsId = (int) $workstation['workstation_id'];
?>

<div style="margin-bottom: 1.5rem;">
    <a href="/workstations/<?= $wsId ?>" style="color: var(--muted); text-decoration: none; font-size: 0.875rem;">&larr; Back to Workstation</a>
    <h1 style="font-size: 1.75rem; font-weight: 700; color: #0f172a; margin-top: 0.5rem;">
        Edit Workstation: <?= View::e($workstation['seat_code']) ?>
    </h1>
    <p style="color: var(--muted); font-size: 0.95rem;">Update seat identifiers and operational status.</p>
</div>

<div class="card" style="max-width: 600px;">
    <form action="/workstations/<?= $wsId ?>" method="POST">
        <?= View::csrf() ?>

        <div class="form-group">
            <label class="form-label">Lab Facility</label>
            <input type="text" class="form-control" value="<?= View::e($workstation['lab_id']) ?>" disabled style="background:#f1f5f9; cursor:not-allowed;">
        </div>

        <div class="form-group">
            <label for="seat_code" class="form-label">Seat Code *</label>
            <input type="text" id="seat_code" name="seat_code" class="form-control"
                   value="<?= View::e($old['seat_code'] ?? $workstation['seat_code'] ?? '') ?>" required>
            <?php if (!empty($errors['seat_code'])): ?>
                <div class="form-error"><?= View::e($errors['seat_code']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="hostname" class="form-label">Network Hostname</label>
            <input type="text" id="hostname" name="hostname" class="form-control"
                   value="<?= View::e($old['hostname'] ?? $workstation['hostname'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="status" class="form-label">Status *</label>
            <select id="status" name="status" class="form-control" required>
                <?php
                    $curStatus = $old['status'] ?? $workstation['status'] ?? 'Active';
                    $statuses = ['Active', 'Inactive', 'Under Repair'];
                    foreach ($statuses as $st):
                ?>
                    <option value="<?= View::e($st) ?>" <?= $curStatus === $st ? 'selected' : '' ?>><?= View::e($st) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem; border-top: 1px solid var(--border); padding-top: 1rem;">
            <a href="/workstations/<?= $wsId ?>" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>
