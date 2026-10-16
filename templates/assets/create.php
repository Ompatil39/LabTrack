<?php

use LabTrack\View\View;

$labs = $labs ?? [];
$selectedLab = (string) ($selectedLab ?? '');
$errors = $errors ?? [];
$old = $old ?? [];

$types = ['PC', 'Monitor', 'Keyboard', 'Mouse', 'CPU', 'Printer', 'Switch', 'AccessPoint', 'Other'];
$statuses = ['Spare', 'In Use', 'Under Repair', 'Retired', 'Decommissioned'];
?>

<div class="page-header">
    <div class="breadcrumb">
        <a href="/inventory">Inventory</a> &rarr; <span>Register Asset</span>
    </div>
    <h1 class="page-title">Register Physical Asset</h1>
    <p class="page-subtitle">Add standalone or spare hardware assets directly to laboratory inventory.</p>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger mb-4">
        <?= View::e($errors['general']) ?>
    </div>
<?php endif; ?>

<div class="card max-w-3xl">
    <div class="card-body">
        <form action="/assets" method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= View::e(function_exists('generateCsrfToken') ? generateCsrfToken() : ($_SESSION['csrf_token'] ?? '')) ?>">

            <div class="grid grid-2 gap-4">
                <!-- Asset Type -->
                <div class="form-group">
                    <label for="asset_type" class="form-label required">Asset Type</label>
                    <select id="asset_type" name="asset_type" class="form-select <?= isset($errors['asset_type']) ? 'is-invalid' : '' ?>" required>
                        <option value="">-- Select Asset Type --</option>
                        <?php foreach ($types as $type): ?>
                            <option value="<?= View::e($type) ?>" <?= ($old['asset_type'] ?? '') === $type ? 'selected' : '' ?>>
                                <?= View::e($type) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['asset_type'])): ?>
                        <div class="invalid-feedback"><?= View::e($errors['asset_type']) ?></div>
                    <?php endif; ?>
                    <small class="form-hint">Physical hardware classification.</small>
                </div>

                <!-- Laboratory -->
                <div class="form-group">
                    <label for="lab_id" class="form-label required">Lab Location</label>
                    <select id="lab_id" name="lab_id" class="form-select <?= isset($errors['lab_id']) ? 'is-invalid' : '' ?>" required>
                        <option value="">-- Select Laboratory --</option>
                        <?php foreach ($labs as $lab): ?>
                            <?php $lId = (string) ($lab['id'] ?? $lab['lab_id']); ?>
                            <option value="<?= View::e($lId) ?>" <?= ((string) ($old['lab_id'] ?? $selectedLab)) === $lId ? 'selected' : '' ?>>
                                <?= View::e($lab['name'] ?? $lab['lab_name']) ?> (<?= View::e($lab['short_code'] ?? '') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['lab_id'])): ?>
                        <div class="invalid-feedback"><?= View::e($errors['lab_id']) ?></div>
                    <?php endif; ?>
                    <small class="form-hint">Lab where this asset is initially inventoried.</small>
                </div>
            </div>

            <div class="grid grid-2 gap-4 mt-3">
                <!-- Brand & Model -->
                <div class="form-group">
                    <label for="brand_model" class="form-label">Brand & Model</label>
                    <input type="text" id="brand_model" name="brand_model" class="form-control <?= isset($errors['brand_model']) ? 'is-invalid' : '' ?>"
                           placeholder="e.g. Dell OptiPlex 7090, Logitech K120"
                           value="<?= View::e((string) ($old['brand_model'] ?? '')) ?>" maxlength="100">
                    <?php if (isset($errors['brand_model'])): ?>
                        <div class="invalid-feedback"><?= View::e($errors['brand_model']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Serial Number -->
                <div class="form-group">
                    <label for="serial_number" class="form-label">Serial Number</label>
                    <input type="text" id="serial_number" name="serial_number" class="form-control <?= isset($errors['serial_number']) ? 'is-invalid' : '' ?>"
                           placeholder="e.g. SN-8839210-X"
                           value="<?= View::e((string) ($old['serial_number'] ?? '')) ?>" maxlength="100">
                    <?php if (isset($errors['serial_number'])): ?>
                        <div class="invalid-feedback"><?= View::e($errors['serial_number']) ?></div>
                    <?php endif; ?>
                    <small class="form-hint">Manufacturer serial number if available.</small>
                </div>
            </div>

            <div class="grid grid-3 gap-4 mt-3">
                <!-- Initial Status -->
                <div class="form-group">
                    <label for="status" class="form-label">Initial Status</label>
                    <select id="status" name="status" class="form-select <?= isset($errors['status']) ? 'is-invalid' : '' ?>">
                        <?php foreach ($statuses as $st): ?>
                            <option value="<?= View::e($st) ?>" <?= ($old['status'] ?? 'Spare') === $st ? 'selected' : '' ?>>
                                <?= View::e($st) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['status'])): ?>
                        <div class="invalid-feedback"><?= View::e($errors['status']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Purchase Cost -->
                <div class="form-group">
                    <label for="purchase_cost" class="form-label">Purchase Cost (₹)</label>
                    <input type="number" step="0.01" min="0" id="purchase_cost" name="purchase_cost" class="form-control <?= isset($errors['purchase_cost']) ? 'is-invalid' : '' ?>"
                           placeholder="0.00"
                           value="<?= View::e((string) ($old['purchase_cost'] ?? '')) ?>">
                    <?php if (isset($errors['purchase_cost'])): ?>
                        <div class="invalid-feedback"><?= View::e($errors['purchase_cost']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Purchase Date -->
                <div class="form-group">
                    <label for="purchase_date" class="form-label">Purchase Date</label>
                    <input type="date" id="purchase_date" name="purchase_date" class="form-control <?= isset($errors['purchase_date']) ? 'is-invalid' : '' ?>"
                           value="<?= View::e((string) ($old['purchase_date'] ?? '')) ?>">
                    <?php if (isset($errors['purchase_date'])): ?>
                        <div class="invalid-feedback"><?= View::e($errors['purchase_date']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid grid-2 gap-4 mt-3">
                <!-- Warranty Until -->
                <div class="form-group">
                    <label for="warranty_until" class="form-label">Warranty Expiration Date</label>
                    <input type="date" id="warranty_until" name="warranty_until" class="form-control <?= isset($errors['warranty_until']) ? 'is-invalid' : '' ?>"
                           value="<?= View::e((string) ($old['warranty_until'] ?? '')) ?>">
                    <?php if (isset($errors['warranty_until'])): ?>
                        <div class="invalid-feedback"><?= View::e($errors['warranty_until']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Specifications JSON -->
                <div class="form-group">
                    <label for="specifications" class="form-label">Technical Specifications (JSON)</label>
                    <textarea id="specifications" name="specifications" class="form-control font-mono <?= isset($errors['specifications']) ? 'is-invalid' : '' ?>"
                              rows="3" placeholder='{"ram": "16GB", "storage": "512GB SSD", "ports": 24}'><?= View::e(is_array($old['specifications'] ?? null) ? json_encode($old['specifications'], JSON_PRETTY_PRINT) : (string) ($old['specifications'] ?? '')) ?></textarea>
                    <?php if (isset($errors['specifications'])): ?>
                        <div class="invalid-feedback"><?= View::e($errors['specifications']) ?></div>
                    <?php endif; ?>
                    <small class="form-hint">Optional JSON object of key-value attributes.</small>
                </div>
            </div>

            <div class="d-flex justify-between items-center mt-4 pt-3 border-top">
                <a href="/inventory" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Register Asset & Generate Tag</button>
            </div>
        </form>
    </div>
</div>
