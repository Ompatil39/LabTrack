<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Search Card -->
            <div class="card shadow-sm mb-4 border-0">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <span class="badge bg-primary-subtle text-primary mb-2 px-3 py-2 rounded-pill">Public Ticket Portal</span>
                        <h2 class="h3 fw-bold text-dark">Track Your Grievance</h2>
                        <p class="text-muted small">Enter your unique ticket tracking code (e.g., TKT-2026-ABC123) to view live progress, SLA status, and updates.</p>
                    </div>

                    <form method="GET" action="/tickets/track" class="d-flex gap-2">
                        <input type="text" name="code" class="form-control form-control-lg text-uppercase font-monospace" placeholder="TKT-2026-XXXXXX" value="<?= htmlspecialchars($code ?? '') ?>" required>
                        <button type="submit" class="btn btn-primary btn-lg px-4">
                            <i class="bi bi-search me-1"></i> Track
                        </button>
                    </form>
                </div>
            </div>

            <?php if (!empty($code) && empty($ticket)): ?>
                <div class="alert alert-warning shadow-sm border-0 d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                    <div>
                        <strong>No ticket found.</strong> We couldn't find any ticket with tracking code <code class="fw-bold"><?= htmlspecialchars($code) ?></code>. Please verify the code and try again.
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($ticket)): ?>
                <!-- Ticket Details Card -->
                <div class="card shadow-sm border-0 mb-4 overflow-hidden">
                    <div class="card-header bg-dark text-white py-3 d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-white-50 small">Tracking Code:</span>
                            <span class="font-monospace fw-bold ms-1"><?= htmlspecialchars((string) ($ticket['tracking_code'] ?? '')) ?></span>
                        </div>
                        <div>
                            <?php
                                $status = (string) ($ticket['status'] ?? 'Submitted');
                                $badgeClass = match ($status) {
                                    'Submitted' => 'bg-info',
                                    'In Progress' => 'bg-warning text-dark',
                                    'Under Review' => 'bg-secondary',
                                    'Resolved' => 'bg-success',
                                    'Closed' => 'bg-dark',
                                    default => 'bg-primary'
                                };
                            ?>
                            <span class="badge <?= $badgeClass ?> px-3 py-2"><?= htmlspecialchars($status) ?></span>
                        </div>
                    </div>

                    <div class="card-body p-4">
                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <div class="text-muted small">Submitted By</div>
                                <div class="fw-bold"><?= htmlspecialchars((string) ($ticket['submitted_by'] ?? '—')) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars((string) ($ticket['class'] ?? '')) ?> (<?= htmlspecialchars((string) ($ticket['stud_enrollment'] ?? '')) ?>)</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Lab & Location</div>
                                <div class="fw-bold"><?= htmlspecialchars((string) ($ticket['lab_name'] ?? $ticket['lab_id'] ?? '—')) ?></div>
                                <?php if (!empty($ticket['seat_code'])): ?>
                                    <span class="badge bg-light text-dark border">Seat <?= htmlspecialchars((string) $ticket['seat_code']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Equipment / Asset</div>
                                <div>
                                    <span class="fw-semibold"><?= htmlspecialchars((string) ($ticket['device_category'] ?? '—')) ?></span>
                                    <?php if (!empty($ticket['asset_tag'])): ?>
                                        <code class="ms-1"><?= htmlspecialchars((string) $ticket['asset_tag']) ?></code>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Priority & SLA Resolution Target</div>
                                <div>
                                    <span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars((string) ($ticket['priority'] ?? 'Medium')) ?></span>
                                    <?php if (!empty($ticket['sla_resolve_due_at'])): ?>
                                        <span class="small ms-1 text-muted">Due by <?= htmlspecialchars((string) $ticket['sla_resolve_due_at']) ?></span>
                                        <?php if (strtotime((string) $ticket['sla_resolve_due_at']) < time() && !in_array($status, ['Resolved', 'Closed'], true)): ?>
                                            <span class="badge bg-danger ms-1">SLA Breached</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="p-3 bg-light rounded border mb-4">
                            <div class="fw-bold small text-muted text-uppercase mb-1">Issue Description</div>
                            <p class="mb-0 text-break"><?= nl2br(htmlspecialchars((string) ($ticket['grievance_desc'] ?? ''))) ?></p>
                        </div>

                        <!-- Status History -->
                        <?php if (!empty($ticket['history'])): ?>
                            <div class="mb-4">
                                <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-1"></i> Status Timeline</h6>
                                <div class="timeline">
                                    <?php foreach ($ticket['history'] as $hist): ?>
                                        <div class="d-flex mb-2 small">
                                            <div class="text-muted me-3 font-monospace" style="min-width: 140px;">
                                                <?= htmlspecialchars(date('M d, H:i', strtotime((string) $hist['created_at']))) ?>
                                            </div>
                                            <div>
                                                <span class="badge bg-light text-dark border me-1"><?= htmlspecialchars((string) $hist['new_status']) ?></span>
                                                <span class="text-muted"><?= htmlspecialchars((string) ($hist['note'] ?: 'Status updated')) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Public Comments -->
                        <hr>
                        <div class="mt-4">
                            <h6 class="fw-bold mb-3"><i class="bi bi-chat-left-text me-1"></i> Updates & Discussion</h6>

                            <?php if (empty($ticket['comments'])): ?>
                                <p class="text-muted small">No updates yet. Staff will post progress notes here.</p>
                            <?php else: ?>
                                <div class="d-flex flex-column gap-3 mb-4">
                                    <?php foreach ($ticket['comments'] as $c): ?>
                                        <div class="p-3 rounded border <?= !empty($c['author_uid']) ? 'bg-primary-subtle border-primary-subtle' : 'bg-light' ?>">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <div class="fw-bold small">
                                                    <?= htmlspecialchars((string) $c['author_name']) ?>
                                                    <?php if (!empty($c['author_uid'])): ?>
                                                        <span class="badge bg-primary text-white ms-1">Staff</span>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="text-muted extra-small" style="font-size: 0.75rem;">
                                                    <?= htmlspecialchars((string) $c['created_at']) ?>
                                                </span>
                                            </div>
                                            <div class="small text-break"><?= nl2br(htmlspecialchars((string) $c['body'])) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Add Reply Form -->
                            <form method="POST" action="/grievances/<?= (int) $ticket['grievance_id'] ?>/comments" class="mt-3">
                                <input type="hidden" name="tracking_code" value="<?= htmlspecialchars((string) $ticket['tracking_code']) ?>">
                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Your Name</label>
                                    <input type="text" name="author_name" class="form-control form-control-sm" placeholder="Enter your name" required>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Reply / Additional Information</label>
                                    <textarea name="body" class="form-control form-control-sm" rows="3" placeholder="Provide extra details or questions..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-send me-1"></i> Post Reply
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
