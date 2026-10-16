<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Asset Maintenance & Lifecycle</h1>
            <p class="text-muted small mb-0">Record repairs, preventive servicing, inspections, and track downtime and costs.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="/maintenance/reports" class="btn btn-outline-info btn-sm">
                <i class="bi bi-graph-up me-1"></i> Warranty & Depreciation Reports
            </a>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#logMaintenanceModal">
                <i class="bi bi-tools me-1"></i> Log Maintenance
            </button>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="/maintenance" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <select name="lab_id" class="form-select form-select-sm">
                        <option value="">All Labs</option>
                        <?php foreach ($labs as $lab): ?>
                            <option value="<?= htmlspecialchars((string) $lab['lab_id']) ?>" <?= ($filters['lab_id'] ?? '') === $lab['lab_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $lab['lab_name']) ?> (<?= htmlspecialchars((string) $lab['lab_id']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="maintenance_type" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        <?php foreach (['Repair', 'Preventive', 'Inspection', 'Upgrade'] as $t): ?>
                            <option value="<?= $t ?>" <?= ($filters['maintenance_type'] ?? '') === $t ? 'selected' : '' ?>>
                                <?= $t ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="number" name="asset_id" class="form-control form-control-sm" placeholder="Filter by Asset ID" value="<?= htmlspecialchars($filters['asset_id'] ?? '') ?>">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filter</button>
                    <a href="/maintenance" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Maintenance Records Table -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light py-3">
            <h6 class="m-0 font-weight-bold text-primary">Maintenance Records (<?= (int) ($pagination['total'] ?? 0) ?>)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 70px;">ID</th>
                            <th>Type</th>
                            <th>Asset</th>
                            <th>Lab / Seat</th>
                            <th>Title & Description</th>
                            <th>Cost</th>
                            <th>Downtime</th>
                            <th>Dates</th>
                            <th>Technician</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    <i class="bi bi-tools fs-2 d-block mb-2"></i>
                                    No maintenance records found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($records as $r): ?>
                                <tr>
                                    <td class="small text-muted">#<?= (int) $r['record_id'] ?></td>
                                    <td>
                                        <?php
                                            $badge = match ($r['maintenance_type']) {
                                                'Repair' => 'bg-danger',
                                                'Preventive' => 'bg-success',
                                                'Inspection' => 'bg-info',
                                                'Upgrade' => 'bg-primary',
                                                default => 'bg-secondary'
                                            };
                                        ?>
                                        <span class="badge <?= $badge ?>"><?= htmlspecialchars((string) $r['maintenance_type']) ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-bold small"><?= htmlspecialchars((string) $r['asset_tag']) ?></div>
                                        <div class="text-muted extra-small" style="font-size: 0.75rem;"><?= htmlspecialchars((string) ($r['brand_model'] ?: $r['asset_type'])) ?></div>
                                    </td>
                                    <td>
                                        <div class="small"><?= htmlspecialchars((string) ($r['lab_name'] ?? $r['lab_id'])) ?></div>
                                        <?php if (!empty($r['seat_code'])): ?>
                                            <span class="badge bg-light text-dark border">Seat <?= htmlspecialchars((string) $r['seat_code']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <div class="fw-semibold"><?= htmlspecialchars((string) $r['title']) ?></div>
                                        <?php if (!empty($r['description'])): ?>
                                            <div class="text-muted text-truncate" style="max-width: 250px;"><?= htmlspecialchars((string) $r['description']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($r['vendor'])): ?>
                                            <span class="text-muted extra-small">Vendor: <?= htmlspecialchars((string) $r['vendor']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small fw-bold">₹<?= number_format((float) $r['cost'], 2) ?></td>
                                    <td class="small">
                                        <?= (float) $r['downtime_hours'] ?> hrs
                                    </td>
                                    <td class="extra-small" style="font-size: 0.75rem;">
                                        <div><strong>Started:</strong> <?= htmlspecialchars(date('Y-m-d H:i', strtotime((string) $r['started_at']))) ?></div>
                                        <?php if (!empty($r['completed_at'])): ?>
                                            <div class="text-success"><strong>Done:</strong> <?= htmlspecialchars(date('Y-m-d H:i', strtotime((string) $r['completed_at']))) ?></div>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">In Progress</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?= htmlspecialchars((string) ($r['performed_by_username'] ?? '—')) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Log Maintenance Modal -->
<div class="modal fade" id="logMaintenanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Log Maintenance Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="/maintenance">
                <?php if (function_exists('csrfInputField')) { echo csrfInputField(); } ?>
                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Asset ID *</label>
                        <input type="number" name="asset_id" class="form-control form-control-sm" required placeholder="e.g. 1">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Maintenance Type *</label>
                        <select name="maintenance_type" class="form-select form-select-sm" required>
                            <option value="Preventive">Preventive Maintenance</option>
                            <option value="Repair">Repair / Fix</option>
                            <option value="Inspection">Periodic Inspection</option>
                            <option value="Upgrade">Hardware / OS Upgrade</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Title / Task Summary *</label>
                        <input type="text" name="title" class="form-control form-control-sm" placeholder="e.g. Clean internal fans and reapply thermal paste" required maxlength="150">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Cost (₹)</label>
                        <input type="number" step="0.01" name="cost" class="form-control form-control-sm" value="0.00">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Vendor / Service Provider</label>
                        <input type="text" name="vendor" class="form-control form-control-sm" placeholder="e.g. Dell Authorised Service">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Started At</label>
                        <input type="datetime-local" name="started_at" class="form-control form-control-sm" value="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Completed At (leave empty if ongoing)</label>
                        <input type="datetime-local" name="completed_at" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Next Scheduled Date (Preventive reminder)</label>
                        <input type="date" name="next_scheduled_date" class="form-control form-control-sm">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Work Details / Notes</label>
                        <textarea name="description" class="form-control form-control-sm" rows="3" placeholder="Additional details, parts replaced, diagnostic findings..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>
