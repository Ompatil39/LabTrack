<?php

use LabTrack\View\View;

$asset = $asset ?? [];
$labs = $labs ?? [];
$errors = $errors ?? [];
$old = $old ?? $asset;

$tag = $asset['asset_tag'] ?? '';
$statuses = ['Spare', 'In Use', 'Under Repair', 'Retired', 'Decommissioned'];

$specsStr = '';
if (!empty($old['specifications'])) {
    if (is_array($old['specifications'])) {
        $specsStr = json_encode($old['specifications'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    } elseif (is_string($old['specifications'])) {
        $specsStr = $old['specifications'];
    }
}
?>

<div class="page-header">
    <div class="breadcrumb">
        <a href="/inventory">Inventory</a> &rarr;
        <a href="/assets/<?= urlencode((string) $tag) ?>"><?= View::e($tag) ?></a> &rarr;
        <span>Edit</span>
    </div>
    <h1 class="page-title">Edit Asset <?= View::e($tag) ?></h1>
    <p class="page-subtitle">Update hardware configuration, status, and laboratory assignment.</p>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger mb-4">
        <?= View::e($errors['general']) ?>
    </div>
<?php endif; ?>

<div class="card max-w-3xl">
    <div class="card-body">
        <form action="/assets/<?= urlencode((string) $tag) ?>" method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= View::e(function_exists('generateCsrfToken') ? generateCsrfToken() : ($_SESSION['csrf_token'] ?? '')) ?>">

            <div class="grid grid-2 gap-4">
                <!-- Asset Tag (Read Only) -->
                <div class="form-group">
                    <label class="form-label">Asset Tag (Immutable)</label>
                    <input type="text" class="form-control font-mono" value="<?= View::e($tag) ?>" disabled readonly>
                    <small class="form-hint">Unique identifier allocated by tag generator.</small>
                </div>

                <!-- Asset Type (Read Only) -->
                <div class="form-group">
                    <label class="form-label">Asset Type (Immutable)</label>
                    <input type="text" class="form-control" value="<?= View::e((string) ($asset['asset_type'] ?? '')) ?>" disabled readonly>
                    <small class="form-hint">Classification cannot be altered after creation.</small>
                </div>
            </div>

            <div class="grid grid-2 gap-4 mt-3">
                <!-- Status -->
                <div class="form-group">
                    <label for="status" class="form-label required">Device Status</label>
                    <select id="status" name="status" class="form-select <?= isset($errors['status']) ? 'is-invalid' : '' ?>">
                        <?php foreach ($statuses as $st): ?>
                            <option value="<?= View::e($st) ?>" <?= ($old['status'] ?? '') === $st ? 'selected' : '' ?>>
                                <?= View::e($st) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['status'])): ?>
                        <div class="invalid-feedback"><?= View::e($errors['status']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Laboratory -->
                <div class="form-group">
                    <label for="lab_id" class="form-label">Laboratory Location</label>
                    <select id="lab_id" name="lab_id" class="form-select <?= isset($errors['lab_id']) ? 'is-invalid' : '' ?>">
                        <option value="">-- No Lab / Storage --</option>
                        <?php foreach ($labs as $lab): ?>
                            <?php $lId = (string) ($lab['id'] ?? $lab['lab_id']); ?>
                            <option value="<?= View::e($lId) ?>" <?= ((string) ($old['lab_id'] ?? '')) === $lId ? 'selected' : '' ?>>
                                <?= View::e($lab['name'] ?? $lab['lab_name']) ?> (<?= View::e($lab['short_code'] ?? '') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['lab_id'])): ?>
                        <div class="invalid-feedback"><?= View::e($errors['lab_id']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid grid-2 gap-4 mt-3">
                <!-- Brand & Model -->
                <div class="form-group">
                    <label for="brand_model" class="form-label">Brand & Model</label>
                    <input type="text" id="brand_model" name="brand_model" class="form-control <?= isset($errors['brand_model']) ? 'is-invalid' : '' ?>"
                           value="<?= View::e((string) ($old['brand_model'] ?? '')) ?>" maxlength="100">
                    <?php if (isset($errors['brand_model'])): ?>
                        <div class="invalid-feedback"><?= View::e($errors['brand_model']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Serial Number -->
                <div class="form-group">
                    <label for="serial_number" class="form-label">Serial Number</label>
                    <input type="text" id="serial_number" name="serial_number" class="form-control <?= isset($errors['serial_number']) ? 'is-invalid' : '' ?>"
                           value="<?= View::e((string) ($old['serial_number'] ?? '')) ?>" maxlength="100">
                    <?php if (isset($errors['serial_number'])): ?>
                        <div class="invalid-feedback"><?= View::e($errors['serial_number']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid grid-3 gap-4 mt-3">
                <!-- Purchase Cost -->
                <div class="form-group">
                    <label for="purchase_cost" class="form-label">Purchase Cost (₹)</label>
                    <input type="number" step="0.01" min="0" id="purchase_cost" name="purchase_cost" class="form-control <?= isset($errors['purchase_cost']) ? 'is-invalid' : '' ?>"
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

                <!-- Warranty Until -->
                <div class="form-group">
                    <label for="warranty_until" class="form-label">Warranty Expiration</label>
                    <input type="date" id="warranty_until" name="warranty_until" class="form-control <?= isset($errors['warranty_until']) ? 'is-invalid' : '' ?>"
                           value="<?= View::e((string) ($old['warranty_until'] ?? '')) ?>">
                    <?php if (isset($errors['warranty_until'])): ?>
                        <div class="invalid-feedback"><?= View::e($errors['warranty_until']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group mt-3">
                <label for="specifications" class="form-label">Technical Specifications (JSON format)</label>
                <textarea id="specifications" name="specifications" class="form-control font-mono <?= isset($errors['specifications']) ? 'is-invalid' : '' ?>"
                          rows="4" placeholder='{"ram": "16GB", "storage": "512GB SSD"}'><?= View::e($specsStr) ?></textarea>
                <?php if (isset($errors['specifications'])): ?>
                    <div class="invalid-feedback"><?= View::e($errors['specifications']) ?></div>
                <?php endif; ?>
            </div>

            <div class="d-flex justify-between items-center mt-4 pt-3 border-top">
                <a href="/assets/<?= urlencode((string) $tag) ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
