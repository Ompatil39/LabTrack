<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">System Audit Logs</h1>
            <p class="text-muted small mb-0">Track administrative actions, entity modifications, part swaps, and security events.</p>
        </div>
        <div>
            <a href="/admin/audit-logs" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </a>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Logs</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="/admin/audit-logs" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Action</label>
                    <select name="action" class="form-select form-select-sm">
                        <option value="">All Actions</option>
                        <?php foreach (['CREATE', 'UPDATE', 'DELETE', 'SWAP', 'RETIRE', 'LOGIN', 'LOGIN_FAILED', 'STATUS_CHANGE'] as $act): ?>
                            <option value="<?= htmlspecialchars($act) ?>" <?= ($filters['action'] ?? '') === $act ? 'selected' : '' ?>>
                                <?= htmlspecialchars($act) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Entity Type</label>
                    <select name="entity_type" class="form-select form-select-sm">
                        <option value="">All Entities</option>
                        <?php foreach (['asset', 'workstation', 'lab', 'ticket', 'maintenance', 'user'] as $ent): ?>
                            <option value="<?= htmlspecialchars($ent) ?>" <?= ($filters['entity_type'] ?? '') === $ent ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucfirst($ent)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Entity ID</label>
                    <input type="text" name="entity_id" class="form-control form-select-sm" placeholder="e.g. PC-2026-0001" value="<?= htmlspecialchars($filters['entity_id'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Username</label>
                    <input type="text" name="username" class="form-control form-select-sm" placeholder="e.g. admin" value="<?= htmlspecialchars($filters['username'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">From Date</label>
                    <input type="date" name="start_date" class="form-control form-select-sm" value="<?= htmlspecialchars($filters['start_date'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">To Date</label>
                    <input type="date" name="end_date" class="form-control form-select-sm" value="<?= htmlspecialchars($filters['end_date'] ?? '') ?>">
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <a href="/admin/audit-logs" class="btn btn-sm btn-outline-secondary">Reset</a>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-filter me-1"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Audit Logs Table Card -->
    <div class="card shadow-sm">
        <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Audit Log Entries (<?= (int) ($pagination['total'] ?? 0) ?> total)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="width: 70px;">ID</th>
                            <th scope="col" style="width: 170px;">Timestamp</th>
                            <th scope="col" style="width: 130px;">User</th>
                            <th scope="col" style="width: 120px;">Action</th>
                            <th scope="col" style="width: 130px;">Entity</th>
                            <th scope="col" style="width: 150px;">Entity ID</th>
                            <th scope="col">Changes / Details</th>
                            <th scope="col" style="width: 120px;">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                    No audit log entries found matching criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="text-muted small">#<?= (int) $log['audit_id'] ?></td>
                                    <td class="small text-nowrap"><?= htmlspecialchars((string) $log['created_at']) ?></td>
                                    <td>
                                        <div class="fw-bold small"><?= htmlspecialchars((string) ($log['username'] ?? 'System/Guest')) ?></div>
                                        <?php if (!empty($log['user_uid'])): ?>
                                            <span class="badge bg-secondary-subtle text-secondary small">UID: <?= (int) $log['user_uid'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                            $actionBadge = match ($log['action']) {
                                                'CREATE' => 'success',
                                                'UPDATE' => 'info',
                                                'DELETE' => 'danger',
                                                'SWAP' => 'warning',
                                                'RETIRE' => 'secondary',
                                                'LOGIN' => 'primary',
                                                'LOGIN_FAILED' => 'danger',
                                                'STATUS_CHANGE' => 'primary',
                                                default => 'dark'
                                            };
                                        ?>
                                        <span class="badge bg-<?= $actionBadge ?>"><?= htmlspecialchars((string) $log['action']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars((string) $log['entity_type']) ?></span>
                                    </td>
                                    <td class="font-monospace small"><?= htmlspecialchars((string) $log['entity_id']) ?></td>
                                    <td class="small">
                                        <?php
                                            $oldVal = !empty($log['old_values']) ? (is_string($log['old_values']) ? json_decode($log['old_values'], true) : $log['old_values']) : null;
                                            $newVal = !empty($log['new_values']) ? (is_string($log['new_values']) ? json_decode($log['new_values'], true) : $log['new_values']) : null;
                                        ?>
                                        <?php if ($oldVal !== null || $newVal !== null): ?>
                                            <details>
                                                <summary class="text-primary text-decoration-none cursor-pointer small">View diff</summary>
                                                <div class="mt-2 p-2 bg-light rounded border font-monospace text-wrap" style="max-height: 200px; overflow-y: auto;">
                                                    <?php if ($oldVal !== null): ?>
                                                        <div class="text-danger"><strong>Old:</strong> <?= htmlspecialchars(json_encode($oldVal, JSON_PRETTY_PRINT)) ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($newVal !== null): ?>
                                                        <div class="text-success mt-1"><strong>New:</strong> <?= htmlspecialchars(json_encode($newVal, JSON_PRETTY_PRINT)) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </details>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small font-monospace text-muted"><?= htmlspecialchars((string) ($log['ip_address'] ?? '—')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
                <div class="card-footer bg-light py-3 d-flex justify-content-between align-items-center">
                    <span class="small text-muted">
                        Page <?= (int) $pagination['page'] ?> of <?= (int) $pagination['total_pages'] ?>
                    </span>
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm mb-0">
                            <?php if ($pagination['page'] > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $pagination['page'] - 1])) ?>">Previous</a>
                                </li>
                            <?php endif; ?>
                            <li class="page-item active">
                                <span class="page-link"><?= (int) $pagination['page'] ?></span>
                            </li>
                            <?php if ($pagination['page'] < $pagination['total_pages']): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $pagination['page'] + 1])) ?>">Next</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
