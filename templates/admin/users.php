<?php
/** @var string $title */
/** @var array $users */
/** @var array $labs */
/** @var array|null $flash */
use LabTrack\View\View;
?>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 1.85rem; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;">Staff &amp; Incharge Management</h1>
        <p style="color: var(--muted); font-size: 0.95rem;">Register laboratory incharges, grant lab administration rights, and view user rosters.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('new-user-card').scrollIntoView({ behavior: 'smooth' });">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="12" y2="12"></line></svg>
        Register New Incharge
    </button>
</div>

<?php if (!empty($flash)): ?>
    <div style="padding: 1rem 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.925rem;
                <?= $flash['type'] === 'error' ? 'background: #fef2f2; color: #991b1b; border: 1px solid #fecaca;' : 'background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;' ?>">
        <?= View::e($flash['message']) ?>
    </div>
<?php endif; ?>

<!-- Users Roster Table -->
<div class="card" style="padding: 0; overflow: hidden; margin-bottom: 2.5rem;">
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
        <h2 style="font-size: 1.15rem; font-weight: 600; color: #0f172a; margin: 0;">Registered System Users</h2>
        <span style="font-size: 0.85rem; color: var(--muted);"><?= count($users) ?> total users</span>
    </div>

    <div style="overflow-x: auto;">
        <table class="table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="padding: 0.75rem 1.25rem; text-align: left; font-size: 0.8rem; color: var(--muted); text-transform: uppercase;">User</th>
                    <th style="padding: 0.75rem 1.25rem; text-align: left; font-size: 0.8rem; color: var(--muted); text-transform: uppercase;">Role</th>
                    <th style="padding: 0.75rem 1.25rem; text-align: left; font-size: 0.8rem; color: var(--muted); text-transform: uppercase;">Contact &amp; Org</th>
                    <th style="padding: 0.75rem 1.25rem; text-align: left; font-size: 0.8rem; color: var(--muted); text-transform: uppercase;">Assigned Laboratories</th>
                    <th style="padding: 0.75rem 1.25rem; text-align: left; font-size: 0.8rem; color: var(--muted); text-transform: uppercase;">Registered</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="5" style="padding: 2rem; text-align: center; color: var(--muted);">No users found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 0.85rem 1.25rem;">
                                <strong><?= View::e($u['full_name']) ?></strong><br>
                                <span style="font-size: 0.825rem; color: var(--muted); font-family: monospace;">@<?= View::e($u['username']) ?></span>
                            </td>
                            <td style="padding: 0.85rem 1.25rem;">
                                <?php if ($u['user_type'] === 'Admin'): ?>
                                    <span style="background: #dbeafe; color: #1e40af; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">ADMIN</span>
                                <?php else: ?>
                                    <span style="background: #f1f5f9; color: #475569; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">INCHARGE</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.85rem 1.25rem; font-size: 0.85rem;">
                                <div><a href="mailto:<?= View::e($u['email']) ?>" style="color: var(--primary); text-decoration: none;"><?= View::e($u['email']) ?></a></div>
                                <?php if (!empty($u['contact_number'])): ?>
                                    <div style="color: var(--muted);"><?= View::e($u['contact_number']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($u['org_name'])): ?>
                                    <div style="font-size: 0.775rem; color: var(--muted);"><?= View::e($u['org_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.85rem 1.25rem;">
                                <?php if ($u['user_type'] === 'Admin'): ?>
                                    <span style="color: var(--muted); font-size: 0.825rem; font-style: italic;">All laboratories (Full access)</span>
                                <?php elseif (!empty($u['labs'])): ?>
                                    <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                                        <?php foreach ($u['labs'] as $assignedLab): ?>
                                            <span style="background: #eff6ff; color: var(--primary); padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.775rem; font-weight: 500;">
                                                <?= View::e($assignedLab['lab_name'] ?? $assignedLab['lab_id']) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #ef4444; font-size: 0.825rem; font-style: italic;">No labs assigned</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.85rem 1.25rem; font-size: 0.825rem; color: var(--muted);">
                                <?= !empty($u['created_at']) ? View::e(date('M d, Y', strtotime($u['created_at']))) : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Register Incharge Card -->
<div id="new-user-card" class="card" style="padding: 1.75rem; max-width: 800px;">
    <h2 style="font-size: 1.25rem; font-weight: 600; color: #0f172a; margin-bottom: 0.5rem;">Register New Lab Incharge</h2>
    <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 1.5rem;">Create a staff account and associate their managed computer laboratories.</p>

    <form action="/admin/users" method="POST">
        <?= View::csrf() ?>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem; margin-bottom: 1.25rem;">
            <div class="form-group">
                <label for="username" style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem;">Username *</label>
                <input type="text" id="username" name="username" class="form-control" required
                       placeholder="e.g. jsmith_lab" pattern="[a-zA-Z0-9_\.]{3,30}">
            </div>

            <div class="form-group">
                <label for="full_name" style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem;">Full Name *</label>
                <input type="text" id="full_name" name="full_name" class="form-control" required
                       placeholder="e.g. Dr. John Smith">
            </div>

            <div class="form-group">
                <label for="email" style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem;">Email Address *</label>
                <input type="email" id="email" name="email" class="form-control" required
                       placeholder="john.smith@institution.edu">
            </div>

            <div class="form-group">
                <label for="password" style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem;">Temporary Password *</label>
                <input type="password" id="password" name="password" class="form-control" required minlength="6"
                       placeholder="Minimum 6 characters">
                <small style="font-size: 0.775rem; color: var(--muted);">User will be forced to change this on their first login.</small>
            </div>

            <div class="form-group">
                <label for="contact_number" style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem;">Contact Number</label>
                <input type="text" id="contact_number" name="contact_number" class="form-control"
                       placeholder="+91 9876543210">
            </div>

            <div class="form-group">
                <label for="org_name" style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem;">Department / Unit</label>
                <input type="text" id="org_name" name="org_name" class="form-control"
                       placeholder="Department of Computer Technology">
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 1.5rem;">
            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 0.5rem;">
                Assigned Computer Laboratories
            </label>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.5rem; background: #f8fafc; padding: 1rem; border-radius: 6px; border: 1px solid var(--border); max-height: 200px; overflow-y: auto;">
                <?php if (empty($labs)): ?>
                    <span style="color: var(--muted); font-size: 0.85rem;">No laboratories configured yet.</span>
                <?php else: ?>
                    <?php foreach ($labs as $lab): ?>
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: #334155; cursor: pointer;">
                            <input type="checkbox" name="labs[]" value="<?= View::e($lab['lab_id']) ?>">
                            <span><?= View::e($lab['lab_name']) ?> <small style="color: var(--muted);">(<?= View::e($lab['lab_id']) ?>)</small></span>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem;">
            Create Incharge Account
        </button>
    </form>
</div>
