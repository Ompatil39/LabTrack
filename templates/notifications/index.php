<?php
/** @var string $title */
/** @var array $notifications */
/** @var bool $isAdmin */
use LabTrack\View\View;
?>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 1.85rem; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;">Notifications &amp; Alerts</h1>
        <p style="color: var(--muted); font-size: 0.95rem;">Real-time feed of equipment complaints, status transitions, and laboratory service requests.</p>
    </div>
    <a href="/grievances" class="btn btn-outline" style="font-size: 0.875rem;">
        View Grievance Roster &rarr;
    </a>
</div>

<div class="card" style="padding: 0; overflow: hidden; margin-bottom: 2rem;">
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #fafbfc;">
        <h2 style="font-size: 1.1rem; font-weight: 600; color: #0f172a; margin: 0;">Recent Activity</h2>
        <span style="font-size: 0.85rem; color: var(--muted);"><?= count($notifications) ?> notifications</span>
    </div>

    <div style="divide-y: 1px solid var(--border);">
        <?php if (empty($notifications)): ?>
            <div style="padding: 3rem 1.5rem; text-align: center; color: var(--muted);">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 0.75rem; color: var(--success);"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <p style="font-weight: 600; font-size: 1.05rem; margin-bottom: 0.25rem;">All caught up!</p>
                <p style="font-size: 0.875rem;">There are no active or pending laboratory notifications requiring attention.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $n): ?>
                <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; <?= $n['is_read'] ? 'background: #fff; opacity: 0.75;' : 'background: #f8fafc;' ?>">
                    <div style="display: flex; gap: 1rem; align-items: flex-start;">
                        <div style="padding: 0.6rem; border-radius: 8px; background: #eff6ff; color: var(--primary); margin-top: 0.15rem;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                        </div>
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; margin-bottom: 0.35rem;">
                                <strong style="font-size: 0.95rem; color: #0f172a;"><?= View::e($n['title']) ?></strong>
                                <span style="background: #e2e8f0; color: #334155; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 500;">
                                    <?= View::e($n['lab_name']) ?>
                                </span>
                                <?php
                                $badgeStyle = match ($n['status']) {
                                    'Submitted'    => 'background: #fffbeb; color: #b45309;',
                                    'In Progress'  => 'background: #eff6ff; color: #1d4ed8;',
                                    'Under Review' => 'background: #f5f3ff; color: #6d28d9;',
                                    'Resolved'     => 'background: #f0fdf4; color: #15803d;',
                                    default        => 'background: #f1f5f9; color: #475569;',
                                };
                                ?>
                                <span style="padding: 0.15rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; <?= $badgeStyle ?>">
                                    <?= View::e($n['status']) ?>
                                </span>
                                <?php if (!$n['is_read']): ?>
                                    <span style="background: #ef4444; color: #fff; padding: 0.1rem 0.4rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 700;">NEW</span>
                                <?php endif; ?>
                            </div>
                            <p style="color: #475569; font-size: 0.9rem; margin-bottom: 0.5rem; line-height: 1.45;">
                                <?= View::e($n['message']) ?>
                            </p>
                            <small style="color: var(--muted); font-size: 0.8rem;">
                                Reported at <?= View::e(date('M d, Y H:i', strtotime($n['timestamp']))) ?>
                            </small>
                        </div>
                    </div>

                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <a href="<?= View::e($n['link']) ?>" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">
                            Review &rarr;
                        </a>
                        <?php if (!$n['is_read']): ?>
                            <form action="/notifications/<?= (int) $n['id'] ?>/read" method="POST" style="display: inline;">
                                <?= View::csrf() ?>
                                <button type="submit" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;" title="Mark as read">
                                    ✓ Read
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
