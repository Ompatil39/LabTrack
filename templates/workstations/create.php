<?php
/** @var array<int, array<string, mixed>> $labs */
/** @var string $selectedLab */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
use LabTrack\View\View;
?>

<div style="margin-bottom: 1.5rem;">
    <a href="/workstations" style="color: var(--muted); text-decoration: none; font-size: 0.875rem;">&larr; Back to Workstations</a>
    <h1 style="font-size: 1.75rem; font-weight: 700; color: #0f172a; margin-top: 0.5rem;">Add Workstation Setup</h1>
    <p style="color: var(--muted); font-size: 0.95rem;">
        Creates a physical workstation seat and registers bundled components with unique atomic asset tags in a single transaction.
    </p>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="flash-alert flash-error"><?= View::e($errors['general']) ?></div>
<?php endif; ?>

<form action="/workstations" method="POST">
    <?= View::csrf() ?>

    <div class="card" style="margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
            1. Workstation Seat Details
        </h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
            <div class="form-group">
                <label for="lab_id" class="form-label">Lab Facility *</label>
                <select id="lab_id" name="lab_id" class="form-control" required>
                    <option value="">-- Select Lab --</option>
                    <?php foreach ($labs as $lab): ?>
                        <option value="<?= View::e($lab['lab_id']) ?>" <?= ($old['lab_id'] ?? $selectedLab) === $lab['lab_id'] ? 'selected' : '' ?>>
                            <?= View::e($lab['lab_name']) ?> (<?= View::e($lab['lab_id']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['lab_id'])): ?>
                    <div class="form-error"><?= View::e($errors['lab_id']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="seat_code" class="form-label">Seat Code *</label>
                <input type="text" id="seat_code" name="seat_code" class="form-control" placeholder="e.g. A-01, WS-15"
                       value="<?= View::e($old['seat_code'] ?? '') ?>" required>
                <?php if (!empty($errors['seat_code'])): ?>
                    <div class="form-error"><?= View::e($errors['seat_code']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="hostname" class="form-label">Network Hostname</label>
                <input type="text" id="hostname" name="hostname" class="form-control" placeholder="e.g. LAB1-PC01"
                       value="<?= View::e($old['hostname'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="status" class="form-label">Status *</label>
                <select id="status" name="status" class="form-control" required>
                    <option value="Active" <?= ($old['status'] ?? 'Active') === 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Inactive" <?= ($old['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="Under Repair" <?= ($old['status'] ?? '') === 'Under Repair' ? 'selected' : '' ?>>Under Repair</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Bundled Components (PC, Monitor, Keyboard, Mouse, CPU) -->
    <div class="card" style="margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
            2. Initial Component Hardware Specifications
        </h2>

        <!-- PC Unit -->
        <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid var(--border);">
            <h3 style="font-size: 1rem; font-weight: 700; color: #1e40af; margin-bottom: 0.75rem;">PC Host Unit</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem;">
                <div>
                    <label class="form-label">Model Name</label>
                    <input type="text" name="parts[pc][brand_model]" class="form-control" placeholder="e.g. Dell OptiPlex 7090"
                           value="<?= View::e($old['parts']['pc']['brand_model'] ?? 'Dell OptiPlex') ?>">
                </div>
                <div>
                    <label class="form-label">Serial Number</label>
                    <input type="text" name="parts[pc][serial_number]" class="form-control" placeholder="e.g. DL-987213">
                </div>
                <div>
                    <label class="form-label">Processor</label>
                    <input type="text" name="parts[pc][specs][processor]" class="form-control" placeholder="e.g. Intel Core i7-11700">
                </div>
                <div>
                    <label class="form-label">RAM</label>
                    <input type="text" name="parts[pc][specs][ram]" class="form-control" placeholder="e.g. 16GB DDR4">
                </div>
                <div>
                    <label class="form-label">Storage</label>
                    <input type="text" name="parts[pc][specs][storage]" class="form-control" placeholder="e.g. 512GB NVMe SSD">
                </div>
                <div>
                    <label class="form-label">Operating System</label>
                    <input type="text" name="parts[pc][specs][os]" class="form-control" placeholder="e.g. Ubuntu 22.04 LTS">
                </div>
            </div>
        </div>

        <!-- Monitor -->
        <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid var(--border);">
            <h3 style="font-size: 1rem; font-weight: 700; color: #1e40af; margin-bottom: 0.75rem;">Display Monitor</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem;">
                <div>
                    <label class="form-label">Model Name</label>
                    <input type="text" name="parts[monitor][brand_model]" class="form-control" placeholder="e.g. Dell P2419H 24-inch"
                           value="<?= View::e($old['parts']['monitor']['brand_model'] ?? 'Dell 24-inch Monitor') ?>">
                </div>
                <div>
                    <label class="form-label">Serial Number</label>
                    <input type="text" name="parts[monitor][serial_number]" class="form-control" placeholder="e.g. CN-087612">
                </div>
                <div>
                    <label class="form-label">Resolution</label>
                    <input type="text" name="parts[monitor][specs][resolution]" class="form-control" placeholder="e.g. 1920x1080 @ 60Hz"
                           value="1920x1080">
                </div>
            </div>
        </div>

        <!-- Peripherals (Keyboard, Mouse, CPU) -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
            <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px solid var(--border);">
                <h3 style="font-size: 0.95rem; font-weight: 700; color: #1e40af; margin-bottom: 0.75rem;">Keyboard</h3>
                <div class="form-group">
                    <label class="form-label">Model</label>
                    <input type="text" name="parts[keyboard][brand_model]" class="form-control" placeholder="e.g. Dell KB216 Wired"
                           value="Dell Wired Keyboard">
                </div>
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <input type="text" name="parts[keyboard][specs][type]" class="form-control" value="Wired USB">
                </div>
            </div>

            <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px solid var(--border);">
                <h3 style="font-size: 0.95rem; font-weight: 700; color: #1e40af; margin-bottom: 0.75rem;">Mouse</h3>
                <div class="form-group">
                    <label class="form-label">Model</label>
                    <input type="text" name="parts[mouse][brand_model]" class="form-control" placeholder="e.g. Dell MS116 Optical"
                           value="Dell Optical Mouse">
                </div>
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <input type="text" name="parts[mouse][specs][type]" class="form-control" value="Optical USB">
                </div>
            </div>
        </div>

        <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px solid var(--border);">
            <h3 style="font-size: 0.95rem; font-weight: 700; color: #1e40af; margin-bottom: 0.75rem;">Cabinet &amp; Power Supply</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div>
                    <label class="form-label">Chassis / Cabinet</label>
                    <input type="text" name="parts[cpu][brand_model]" class="form-control" placeholder="e.g. Dell Tower Chassis"
                           value="Dell Tower Cabinet">
                </div>
                <div>
                    <label class="form-label">Power Supply Unit</label>
                    <input type="text" name="parts[cpu][specs][power_supply]" class="form-control" placeholder="e.g. 260W 80 Plus Bronze"
                           value="260W PSU">
                </div>
            </div>
        </div>
    </div>

    <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-bottom: 2rem;">
        <a href="/workstations" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem; font-size: 1rem;">
            Save Workstation &amp; Generate Asset Tags
        </button>
    </div>
</form>
