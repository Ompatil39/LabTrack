<?php
/** @var string $title */
/** @var array  $grievance   single grievance row */
/** @var bool   $isAdmin */
/** @var array  $statuses    allowed status values */
/** @var array|null $flash */

use LabTrack\View\View;

function grvStatusClass(string $status): string
{
    return match ($status) {
        'Submitted'    => 'badge-warning',
        'Under Review' => 'badge-info',
        'In Progress'  => 'badge-primary',
        'Resolved'     => 'badge-success',
        'Closed'       => 'badge-secondary',
        default        => 'badge-light',
    };
}

function grvStatusIcon(string $status): string
{
    return match ($status) {
        'Submitted'    => 'fa-paper-plane',
        'Under Review' => 'fa-search',
        'In Progress'  => 'fa-spinner fa-spin',
        'Resolved'     => 'fa-check-circle',
        'Closed'       => 'fa-lock',
        default        => 'fa-circle',
    };
}

ob_start();
$gId = (int) $grievance['grievance_id'];
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title">
            <i class="fas fa-exclamation-circle"></i>
            Grievance #<?php echo $gId; ?>
        </h1>
        <p class="page-subtitle">
            Submitted by <?php echo View::e($grievance['submitted_by']); ?>
            on <?php echo View::e(date('d M Y H:i', strtotime($grievance['submission_date']))); ?>
        </p>
    </div>
    <a href="/grievances" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Grievances
    </a>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert alert-<?php echo View::e($flash['type'] === 'error' ? 'danger' : $flash['type']); ?> alert-dismissible">
        <?php echo View::e($flash['message']); ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Main details -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title mb-0"><i class="fas fa-file-alt"></i> Grievance Details</h2>
                <span class="badge <?php echo grvStatusClass($grievance['status']); ?> badge-lg">
                    <i class="fas <?php echo grvStatusIcon($grievance['status']); ?>"></i>
                    <?php echo View::e($grievance['status']); ?>
                </span>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Submitted By</dt>
                    <dd class="col-sm-8"><?php echo View::e($grievance['submitted_by']); ?></dd>

                    <dt class="col-sm-4">Enrollment</dt>
                    <dd class="col-sm-8"><?php echo View::e($grievance['stud_enrollment']); ?></dd>

                    <dt class="col-sm-4">Class</dt>
                    <dd class="col-sm-8"><?php echo View::e($grievance['class']); ?></dd>

                    <dt class="col-sm-4">Email</dt>
                    <dd class="col-sm-8">
                        <a href="mailto:<?php echo View::e($grievance['stud_email']); ?>">
                            <?php echo View::e($grievance['stud_email']); ?>
                        </a>
                    </dd>

                    <dt class="col-sm-4">Lab</dt>
                    <dd class="col-sm-8"><?php echo View::e($grievance['lab_name'] ?? $grievance['lab_id']); ?></dd>

                    <dt class="col-sm-4">Device Category</dt>
                    <dd class="col-sm-8"><?php echo View::e($grievance['device_category']); ?></dd>

                    <?php if (!empty($grievance['device_name'])): ?>
                        <dt class="col-sm-4">Device Name</dt>
                        <dd class="col-sm-8"><?php echo View::e($grievance['device_name']); ?></dd>
                    <?php endif; ?>

                    <?php if (!empty($grievance['asset_tag'])): ?>
                        <dt class="col-sm-4">Asset Tag</dt>
                        <dd class="col-sm-8">
                            <a href="/assets/<?php echo View::e($grievance['asset_tag']); ?>">
                                <?php echo View::e($grievance['asset_tag']); ?>
                            </a>
                            <?php if (!empty($grievance['asset_brand_model'])): ?>
                                &mdash; <?php echo View::e($grievance['asset_brand_model']); ?>
                            <?php endif; ?>
                        </dd>
                    <?php elseif (!empty($grievance['device_id'])): ?>
                        <dt class="col-sm-4">Legacy Device ID</dt>
                        <dd class="col-sm-8"><?php echo View::e($grievance['device_id']); ?></dd>
                    <?php endif; ?>

                    <?php if (!empty($grievance['seat_code'])): ?>
                        <dt class="col-sm-4">Workstation</dt>
                        <dd class="col-sm-8">
                            <a href="/workstations/<?php echo (int) $grievance['workstation_id']; ?>">
                                <?php echo View::e($grievance['seat_code']); ?>
                            </a>
                        </dd>
                    <?php endif; ?>

                    <dt class="col-sm-4">Submitted</dt>
                    <dd class="col-sm-8"><?php echo View::e(date('d M Y, H:i', strtotime($grievance['submission_date']))); ?></dd>
                </dl>
            </div>
        </div>

        <!-- Description -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-align-left"></i> Problem Description</h3>
            </div>
            <div class="card-body">
                <p class="mb-0" style="white-space: pre-wrap;"><?php echo View::e($grievance['grievance_desc']); ?></p>
            </div>
        </div>

        <!-- Admin note (read-only display if set) -->
        <?php if (!empty($grievance['admin_note'])): ?>
            <div class="card mb-4 border-info">
                <div class="card-header bg-info text-white">
                    <h3 class="card-title mb-0"><i class="fas fa-sticky-note"></i> Admin Note</h3>
                </div>
                <div class="card-body">
                    <p class="mb-0" style="white-space: pre-wrap;"><?php echo View::e($grievance['admin_note']); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- SLA & Ticket Tracking Info -->
        <?php if (!empty($grievance['tracking_code'])): ?>
            <div class="card mb-4 border-0 shadow-sm bg-light">
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-sm-4">
                            <span class="text-muted small">Tracking Code:</span>
                            <div class="fw-bold font-monospace"><?php echo View::e($grievance['tracking_code']); ?></div>
                        </div>
                        <div class="col-sm-4">
                            <span class="text-muted small">Priority:</span>
                            <div><span class="badge badge-secondary"><?php echo View::e($grievance['priority'] ?? 'Medium'); ?></span></div>
                        </div>
                        <div class="col-sm-4">
                            <span class="text-muted small">SLA Resolution Target:</span>
                            <div class="small fw-semibold"><?php echo View::e($grievance['sla_resolve_due_at'] ?? 'N/A'); ?></div>
                            <?php if (!empty($grievance['escalated_to_admin'])): ?>
                                <span class="badge badge-danger">Escalated to Admin</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Status History Timeline -->
        <?php if (!empty($history)): ?>
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-light">
                    <h3 class="card-title mb-0"><i class="fas fa-history"></i> Status Timeline</h3>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($history as $h): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <div>
                                    <span class="badge badge-secondary"><?php echo View::e($h['new_status']); ?></span>
                                    <span class="small ms-2"><?php echo View::e($h['note'] ?: 'Status updated'); ?></span>
                                    <?php if (!empty($h['username'])): ?>
                                        <span class="text-muted small ms-1">(by <?php echo View::e($h['username']); ?>)</span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-muted small"><?php echo View::e($h['created_at']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <!-- Comments & Internal Notes -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0"><i class="fas fa-comments"></i> Ticket Discussion & Internal Notes</h3>
            </div>
            <div class="card-body">
                <?php if (empty($comments)): ?>
                    <p class="text-muted small">No comments logged yet.</p>
                <?php else: ?>
                    <div class="mb-3">
                        <?php foreach ($comments as $c): ?>
                            <div class="p-3 mb-2 rounded <?php echo !empty($c['is_internal']) ? 'border border-warning bg-warning-subtle' : 'bg-light border'; ?>">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div class="fw-bold small">
                                        <?php echo View::e($c['author_name']); ?>
                                        <?php if (!empty($c['is_internal'])): ?>
                                            <span class="badge badge-warning ms-1">Internal Note</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-muted small"><?php echo View::e($c['created_at']); ?></span>
                                </div>
                                <div class="small" style="white-space: pre-wrap;"><?php echo View::e($c['body']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/grievances/<?php echo $gId; ?>/comments">
                    <?php if (function_exists('csrfInputField')) { echo csrfInputField(); } ?>
                    <div class="form-group mb-2">
                        <textarea name="body" class="form-control" rows="3" placeholder="Add a comment or internal note…" required></textarea>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="is_internal" value="1" id="is_internal">
                        <label class="form-check-label small" for="is_internal">
                            Internal note (hidden from students on public track page)
                        </label>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-paper-plane"></i> Post Comment
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Sidebar: status update + delete -->
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-edit"></i> Update Status</h3>
            </div>
            <div class="card-body">
                <form method="POST"
                      action="/grievances/<?php echo $gId; ?>/status"
                      id="status-update-form">
                    <?php if (function_exists('csrfInputField')) { echo csrfInputField(); } ?>

                    <div class="form-group">
                        <label for="status" class="form-label">New Status</label>
                        <select id="status" name="status" class="form-control">
                            <?php foreach ($statuses as $s): ?>
                                <option value="<?php echo View::e($s); ?>"
                                    <?php echo ($grievance['status'] === $s) ? 'selected' : ''; ?>>
                                    <?php echo View::e($s); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="admin_note" class="form-label">Note (optional)</label>
                        <textarea id="admin_note" name="admin_note" class="form-control" rows="3"
                                  maxlength="2000" placeholder="Add a note for the student…"><?php echo View::e($grievance['admin_note'] ?? ''); ?></textarea>
                        <small class="form-text text-muted">Student will receive an email notification.</small>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-save"></i> Update Status
                    </button>
                </form>
            </div>
        </div>

        <?php if ($isAdmin): ?>
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h3 class="card-title mb-0"><i class="fas fa-trash"></i> Danger Zone</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-2">Permanently delete this grievance. This cannot be undone.</p>
                    <form method="POST"
                          action="/grievances/<?php echo $gId; ?>/delete"
                          onsubmit="return confirm('Delete grievance #<?php echo $gId; ?>? This cannot be undone.')">
                        <?php if (function_exists('csrfInputField')) { echo csrfInputField(); } ?>
                        <button type="submit" class="btn btn-danger btn-block">
                            <i class="fas fa-trash"></i> Delete Grievance
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout/base.php';
