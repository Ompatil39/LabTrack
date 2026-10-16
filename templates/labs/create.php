<?php
/** @var array<int, array<string, mixed>> $incharges */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
use LabTrack\View\View;
?>

<div style="margin-bottom: 1.5rem;">
    <a href="/labs" style="color: var(--muted); text-decoration: none; font-size: 0.875rem;">&larr; Back to Labs</a>
    <h1 style="font-size: 1.75rem; font-weight: 700; color: #0f172a; margin-top: 0.5rem;">Add New Computer Lab</h1>
    <p style="color: var(--muted); font-size: 0.95rem;">Register a physical computer lab facility into LabTrack.</p>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="flash-alert flash-error">
        <?= View::e($errors['general']) ?>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 750px;">
    <form action="/labs" method="POST">
        <?= View::csrf() ?>

        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1rem;">
            <div class="form-group">
                <label for="lab_id" class="form-label">Lab ID *</label>
                <input type="text" id="lab_id" name="lab_id" class="form-control"
                       placeholder="e.g. LAB-101"
                       value="<?= View::e($old['lab_id'] ?? '') ?>" required>
                <?php if (!empty($errors['lab_id'])): ?>
                    <div class="form-error"><?= View::e($errors['lab_id']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="lab_name" class="form-label">Lab Name *</label>
                <input type="text" id="lab_name" name="lab_name" class="form-control"
                       placeholder="e.g. Advanced Computing Lab"
                       value="<?= View::e($old['lab_name'] ?? '') ?>" required>
                <?php if (!empty($errors['lab_name'])): ?>
                    <div class="form-error"><?= View::e($errors['lab_name']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="department" class="form-label">Department *</label>
                <input type="text" id="department" name="department" class="form-control"
                       placeholder="e.g. Computer Science"
                       value="<?= View::e($old['department'] ?? '') ?>" required>
                <?php if (!empty($errors['department'])): ?>
                    <div class="form-error"><?= View::e($errors['department']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="status" class="form-label">Status *</label>
                <select id="status" name="status" class="form-control" required>
                    <?php
                        $selectedStatus = $old['status'] ?? 'Active';
                        $statuses = ['Active', 'InActive', 'In Repair'];
                        foreach ($statuses as $st):
                    ?>
                        <option value="<?= View::e($st) ?>" <?= $selectedStatus === $st ? 'selected' : '' ?>>
                            <?= View::e($st) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['status'])): ?>
                    <div class="form-error"><?= View::e($errors['status']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-group">
            <label for="lab_incharge_select" class="form-label">Assigned Lab Incharge *</label>
            <?php if (!empty($incharges)): ?>
                <select id="lab_incharge_select" class="form-control" onchange="syncIncharge(this)">
                    <option value="">-- Select Incharge Account --</option>
                    <?php foreach ($incharges as $inc): ?>
                        <?php
                            $incUid = $inc['uid'] ?? '';
                            $incName = $inc['full_name'] ?: ($inc['username'] ?? '');
                            $incUser = $inc['username'] ?? '';
                            $isSelected = (isset($old['incharge_uid']) && (string)$old['incharge_uid'] === (string)$incUid)
                                       || (isset($old['lab_incharge']) && $old['lab_incharge'] === $incUser);
                        ?>
                        <option value="<?= View::e((string)$incUid) ?>" data-name="<?= View::e($incUser) ?>" <?= $isSelected ? 'selected' : '' ?>>
                            <?= View::e($incName) ?> (<?= View::e($incUser) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" id="incharge_uid" name="incharge_uid" value="<?= View::e((string)($old['incharge_uid'] ?? '')) ?>">
                <input type="text" id="lab_incharge" name="lab_incharge" class="form-control" style="margin-top: 0.5rem;"
                       placeholder="Or specify Incharge username/name manually"
                       value="<?= View::e($old['lab_incharge'] ?? '') ?>" required>
            <?php else: ?>
                <input type="text" id="lab_incharge" name="lab_incharge" class="form-control"
                       placeholder="Enter Incharge username or full name"
                       value="<?= View::e($old['lab_incharge'] ?? '') ?>" required>
            <?php endif; ?>
            <?php if (!empty($errors['lab_incharge'])): ?>
                <div class="form-error"><?= View::e($errors['lab_incharge']) ?></div>
            <?php endif; ?>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="room_capacity" class="form-label">Room Capacity (Workstations) *</label>
                <input type="number" id="room_capacity" name="room_capacity" class="form-control" min="1"
                       placeholder="e.g. 30"
                       value="<?= View::e((string)($old['room_capacity'] ?? '')) ?>" required>
                <?php if (!empty($errors['room_capacity'])): ?>
                    <div class="form-error"><?= View::e($errors['room_capacity']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="establishment_date" class="form-label">Establishment Date *</label>
                <input type="date" id="establishment_date" name="establishment_date" class="form-control"
                       value="<?= View::e($old['establishment_date'] ?? date('Y-m-d')) ?>" required>
                <?php if (!empty($errors['establishment_date'])): ?>
                    <div class="form-error"><?= View::e($errors['establishment_date']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="building" class="form-label">Building / Block</label>
                <input type="text" id="building" name="building" class="form-control"
                       placeholder="e.g. Science Block B"
                       value="<?= View::e($old['building'] ?? '') ?>">
                <?php if (!empty($errors['building'])): ?>
                    <div class="form-error"><?= View::e($errors['building']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="room_number" class="form-label">Room Number *</label>
                <input type="text" id="room_number" name="room_number" class="form-control"
                       placeholder="e.g. 304"
                       value="<?= View::e($old['room_number'] ?? '') ?>" required>
                <?php if (!empty($errors['room_number'])): ?>
                    <div class="form-error"><?= View::e($errors['room_number']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1rem; border-top: 1px solid var(--border); padding-top: 1rem;">
            <a href="/labs" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Create Lab</button>
        </div>
    </form>
</div>

<script>
function syncIncharge(select) {
    var opt = select.options[select.selectedIndex];
    var uidInput = document.getElementById('incharge_uid');
    var nameInput = document.getElementById('lab_incharge');
    if (uidInput) {
        uidInput.value = opt.value || '';
    }
    if (nameInput && opt.getAttribute('data-name')) {
        nameInput.value = opt.getAttribute('data-name');
    }
}
</script>
