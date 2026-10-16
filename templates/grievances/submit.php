<?php
/** @var string $title */
/** @var array  $labs    list of lab rows */
/** @var array  $errors  validation errors */
/** @var array  $old     repopulate form fields */

use LabTrack\View\View;

$isSubmitted = isset($_GET['submitted']);
ob_start();
?>
<div class="page-header">
    <h1 class="page-title"><i class="fas fa-exclamation-circle"></i> Submit a Grievance</h1>
    <p class="page-subtitle">Use this form to report a lab equipment issue. No login required.</p>
</div>

<?php if ($isSubmitted): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        Your grievance has been submitted successfully. We will review it and notify you by email.
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <strong><i class="fas fa-exclamation-triangle"></i> Please fix the following errors:</strong>
        <ul class="mt-2 mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo View::e($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-clipboard-list"></i> Grievance Details</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="/grievances" id="grievance-submit-form" novalidate>
            <?php if (function_exists('csrfInputField')) { echo csrfInputField(); } ?>

            <div class="form-section">
                <h3 class="form-section-title">Your Information</h3>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="submitted_by" class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" id="submitted_by" name="submitted_by" class="form-control"
                               value="<?php echo View::e($old['submitted_by'] ?? ''); ?>"
                               maxlength="100" required placeholder="Your full name">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="stud_enrollment" class="form-label">Enrollment Number <span class="required">*</span></label>
                        <input type="text" id="stud_enrollment" name="stud_enrollment" class="form-control"
                               value="<?php echo View::e($old['stud_enrollment'] ?? ''); ?>"
                               maxlength="20" required placeholder="e.g. 22CO001">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="class" class="form-label">Class <span class="required">*</span></label>
                        <input type="text" id="class" name="class" class="form-control"
                               value="<?php echo View::e($old['class'] ?? ''); ?>"
                               maxlength="10" required placeholder="e.g. CO-5I">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="stud_email" class="form-label">Email Address <span class="required">*</span></label>
                        <input type="email" id="stud_email" name="stud_email" class="form-control"
                               value="<?php echo View::e($old['stud_email'] ?? ''); ?>"
                               maxlength="100" required placeholder="you@example.com">
                        <small class="form-text text-muted">We will send status updates to this address.</small>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">Lab &amp; Equipment</h3>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="lab_id" class="form-label">Lab <span class="required">*</span></label>
                        <select id="lab_id" name="lab_id" class="form-control" required>
                            <option value="">— Select Lab —</option>
                            <?php foreach ($labs as $lab): ?>
                                <option value="<?php echo View::e($lab['lab_id']); ?>"
                                    <?php echo (($old['lab_id'] ?? '') === $lab['lab_id']) ? 'selected' : ''; ?>>
                                    <?php echo View::e($lab['lab_name']); ?> (<?php echo View::e($lab['lab_id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="device_category" class="form-label">Device Category <span class="required">*</span></label>
                        <select id="device_category" name="device_category" class="form-control" required>
                            <option value="">— Select Category —</option>
                            <?php foreach (['PC', 'Monitor', 'Keyboard', 'Mouse', 'CPU', 'Printer', 'Other'] as $cat): ?>
                                <option value="<?php echo View::e($cat); ?>"
                                    <?php echo (($old['device_category'] ?? '') === $cat) ? 'selected' : ''; ?>>
                                    <?php echo View::e($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="device_name" class="form-label">Device / Asset Name</label>
                        <input type="text" id="device_name" name="device_name" class="form-control"
                               value="<?php echo View::e($old['device_name'] ?? ''); ?>"
                               maxlength="100" placeholder="e.g. Seat 3 PC, Lab Printer A">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="asset_id" class="form-label">Asset Tag (if known)</label>
                        <input type="text" id="asset_id_display" class="form-control"
                               placeholder="Start typing to search…" autocomplete="off">
                        <input type="hidden" id="asset_id" name="asset_id"
                               value="<?php echo View::e((string)($old['asset_id'] ?? '')); ?>">
                        <small class="form-text text-muted">Optional — helps staff locate the asset faster.</small>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">Issue Description</h3>
                <div class="form-group">
                    <label for="grievance_desc" class="form-label">Describe the Problem <span class="required">*</span></label>
                    <textarea id="grievance_desc" name="grievance_desc" class="form-control"
                              rows="6" maxlength="5000" required
                              placeholder="Please describe the issue in detail — what happened, when, and any steps you tried."><?php echo View::e($old['grievance_desc'] ?? ''); ?></textarea>
                    <small class="form-text text-muted">Max 5000 characters.</small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="submit-grievance-btn">
                    <i class="fas fa-paper-plane"></i> Submit Grievance
                </button>
                <button type="reset" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Clear Form
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Minimal asset search — fetches from /api/assets when a lab is selected
(function () {
    const labSelect     = document.getElementById('lab_id');
    const assetDisplay  = document.getElementById('asset_id_display');
    const assetHidden   = document.getElementById('asset_id');

    let debounceTimer;

    assetDisplay.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const labId = labSelect ? labSelect.value : '';
        const q     = this.value.trim();
        if (q.length < 2) {
            assetHidden.value = '';
            return;
        }
        debounceTimer = setTimeout(function () {
            const url = '/api/assets?lab_id=' + encodeURIComponent(labId) + '&search=' + encodeURIComponent(q) + '&limit=10';
            fetch(url, { credentials: 'same-origin' })
                .then(r => r.ok ? r.json() : null)
                .then(json => {
                    if (!json || !json.data) return;
                    // Simple: if exactly one result, auto-fill hidden field
                    if (json.data.length === 1) {
                        assetHidden.value  = json.data[0].asset_id;
                        assetDisplay.value = json.data[0].asset_tag + ' — ' + (json.data[0].brand_model || json.data[0].asset_type);
                    }
                })
                .catch(() => {});
        }, 350);
    });
})();
</script>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout/base.php';
