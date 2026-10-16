<?php
/** @var string $title */
/** @var array  $grievances  list rows */
/** @var array  $labs        for filter dropdown */
/** @var array  $filters     current filter state */
/** @var bool   $isAdmin */
/** @var int    $page */
/** @var int    $totalCount */
/** @var int    $limit */
/** @var array|null $flash */

use LabTrack\Validation\GrievanceRequest;
use LabTrack\View\View;

$totalPages = $limit > 0 ? (int) ceil($totalCount / $limit) : 1;

function grievanceStatusClass(string $status): string
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

function grievanceStatusIcon(string $status): string
{
    return match ($status) {
        'Submitted'    => 'fa-paper-plane',
        'Under Review' => 'fa-search',
        'In Progress'  => 'fa-spinner',
        'Resolved'     => 'fa-check-circle',
        'Closed'       => 'fa-lock',
        default        => 'fa-circle',
    };
}

ob_start();
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-exclamation-circle"></i> Grievances</h1>
        <p class="page-subtitle">
            <?php echo $isAdmin ? 'All labs' : 'Your assigned labs'; ?> &mdash;
            <?php echo number_format($totalCount); ?> grievance<?php echo $totalCount !== 1 ? 's' : ''; ?> found
        </p>
    </div>
    <a href="/grievances/submit" class="btn btn-outline-primary" target="_blank">
        <i class="fas fa-plus"></i> New Grievance (Public Form)
    </a>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert alert-<?php echo View::e($flash['type'] === 'error' ? 'danger' : $flash['type']); ?> alert-dismissible">
        <?php echo View::e($flash['message']); ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="/grievances" class="form-inline flex-wrap gap-2" id="grievance-filter-form">
            <div class="form-group mr-2">
                <label for="filter-lab" class="sr-only">Lab</label>
                <select id="filter-lab" name="lab_id" class="form-control form-control-sm">
                    <option value="">All Labs</option>
                    <?php foreach ($labs as $lab): ?>
                        <option value="<?php echo View::e($lab['lab_id']); ?>"
                            <?php echo ($filters['lab_id'] === $lab['lab_id']) ? 'selected' : ''; ?>>
                            <?php echo View::e($lab['lab_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mr-2">
                <label for="filter-status" class="sr-only">Status</label>
                <select id="filter-status" name="status" class="form-control form-control-sm">
                    <option value="">All Statuses</option>
                    <?php foreach (GrievanceRequest::ALLOWED_STATUSES as $s): ?>
                        <option value="<?php echo View::e($s); ?>"
                            <?php echo ($filters['status'] === $s) ? 'selected' : ''; ?>>
                            <?php echo View::e($s); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mr-2">
                <label for="filter-search" class="sr-only">Search</label>
                <input type="text" id="filter-search" name="search" class="form-control form-control-sm"
                       value="<?php echo View::e($filters['search']); ?>"
                       placeholder="Search name, enrollment, description…">
            </div>
            <button type="submit" class="btn btn-sm btn-primary mr-1">
                <i class="fas fa-search"></i> Filter
            </button>
            <a href="/grievances" class="btn btn-sm btn-secondary">
                <i class="fas fa-times"></i> Clear
            </a>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($grievances)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-inbox fa-3x mb-3"></i>
                <p>No grievances found matching your filters.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Submitted By</th>
                            <th>Lab</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grievances as $g): ?>
                            <tr>
                                <td><?php echo (int) $g['grievance_id']; ?></td>
                                <td>
                                    <strong><?php echo View::e($g['submitted_by']); ?></strong><br>
                                    <small class="text-muted"><?php echo View::e($g['stud_enrollment']); ?></small>
                                </td>
                                <td><?php echo View::e($g['lab_name'] ?? $g['lab_id']); ?></td>
                                <td><?php echo View::e($g['device_category']); ?></td>
                                <td>
                                    <span class="badge <?php echo grievanceStatusClass($g['status']); ?>">
                                        <i class="fas <?php echo grievanceStatusIcon($g['status']); ?>"></i>
                                        <?php echo View::e($g['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo View::e(date('d M Y H:i', strtotime($g['submission_date']))); ?></small>
                                </td>
                                <td class="text-right">
                                    <a href="/grievances/<?php echo (int) $g['grievance_id']; ?>"
                                       class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if ($isAdmin): ?>
                                        <form method="POST"
                                              action="/grievances/<?php echo (int) $g['grievance_id']; ?>/delete"
                                              class="d-inline"
                                              onsubmit="return confirm('Delete grievance #<?php echo (int) $g['grievance_id']; ?>? This cannot be undone.')">
                                            <?php if (function_exists('csrfInputField')) { echo csrfInputField(); } ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav class="px-3 py-2" aria-label="Grievance pagination">
                    <ul class="pagination pagination-sm mb-0 justify-content-end">
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                                <a class="page-link"
                                   href="/grievances?<?php echo http_build_query(array_merge($filters, ['page' => $p])); ?>">
                                    <?php echo $p; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout/base.php';
