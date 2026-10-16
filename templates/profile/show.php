<?php
/** @var string $title */
/** @var array $user */
/** @var bool $forcePasswordChange */
/** @var array|null $flash */
use LabTrack\View\View;
?>

<div style="margin-bottom: 2rem;">
    <h1 style="font-size: 1.85rem; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;">Account &amp; Security Settings</h1>
    <p style="color: var(--muted); font-size: 0.95rem;">Manage your profile information and update your login password.</p>
</div>

<?php if (!empty($flash)): ?>
    <div style="padding: 1rem 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.925rem;
                <?= $flash['type'] === 'error' ? 'background: #fef2f2; color: #991b1b; border: 1px solid #fecaca;' : 'background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;' ?>">
        <?= View::e($flash['message']) ?>
    </div>
<?php endif; ?>

<?php if ($forcePasswordChange): ?>
    <div style="background: #fffbeb; border: 1px solid #fde68a; border-left: 4px solid var(--warning); padding: 1.25rem; border-radius: 8px; margin-bottom: 2rem;">
        <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            <div>
                <strong style="color: #92400e; font-size: 1rem;">Password Change Required</strong>
                <p style="color: #b45309; font-size: 0.9rem; margin-top: 0.25rem;">
                    Your account is configured with a default or temporary password. Please set a new personal password below to ensure full security access.
                </p>
            </div>
        </div>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem;">
    <!-- Profile Details Form -->
    <div class="card" style="padding: 1.75rem;">
        <h2 style="font-size: 1.2rem; font-weight: 600; color: #0f172a; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            Profile Information
        </h2>

        <form action="/profile" method="POST">
            <?= View::csrf() ?>
            <input type="hidden" name="action" value="profile">

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem;">Username</label>
                <input type="text" class="form-control" value="<?= View::e($user['username'] ?? '') ?>" disabled
                       style="background: #f1f5f9; cursor: not-allowed;">
                <small style="color: var(--muted); font-size: 0.775rem;">Usernames are permanently assigned and cannot be modified.</small>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="full_name" style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem;">Full Name *</label>
                <input type="text" id="full_name" name="full_name" class="form-control" required
                       value="<?= View::e($user['full_name'] ?? '') ?>" placeholder="Your full name">
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="email" style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem;">Email Address *</label>
                <input type="email" id="email" name="email" class="form-control" required
                       value="<?= View::e($user['email'] ?? '') ?>" placeholder="you@institution.edu">
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="contact_number" style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem;">Contact Number</label>
                <input type="text" id="contact_number" name="contact_number" class="form-control"
                       value="<?= View::e($user['contact_number'] ?? '') ?>" placeholder="+91 9876543210">
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="org_name" style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem;">Department / Organization</label>
                <input type="text" id="org_name" name="org_name" class="form-control"
                       value="<?= View::e($user['org_name'] ?? '') ?>" placeholder="e.g. Computer Science & Engineering">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                Save Profile Changes
            </button>
        </form>
    </div>

    <!-- Password Change Form -->
    <div class="card" style="padding: 1.75rem;">
        <h2 style="font-size: 1.2rem; font-weight: 600; color: #0f172a; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            Change Login Password
        </h2>

        <form action="/profile" method="POST">
            <?= View::csrf() ?>
            <input type="hidden" name="action" value="password">

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="current_password" style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem;">Current Password *</label>
                <input type="password" id="current_password" name="current_password" class="form-control" required
                       placeholder="Enter current password" autocomplete="current-password">
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="new_password" style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem;">New Password *</label>
                <input type="password" id="new_password" name="new_password" class="form-control" required minlength="6"
                       placeholder="Minimum 6 characters" autocomplete="new-password">
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="confirm_password" style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem;">Confirm New Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="6"
                       placeholder="Repeat new password" autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-outline" style="width: 100%; border-color: var(--primary); color: var(--primary);">
                Update Password
            </button>
        </form>
    </div>
</div>
