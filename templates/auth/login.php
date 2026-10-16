<?php
/** @var string|null $error */
/** @var string $oldUsername */
/** @var string $captcha */
use LabTrack\View\View;
?>

<div style="margin-bottom: 1.5rem; text-align: center;">
    <h1 style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-bottom: 0.35rem;">Staff &amp; Admin Sign In</h1>
    <p style="font-size: 0.875rem; color: var(--muted); margin: 0;">Access your lab management dashboard</p>
</div>

<?php if (!empty($error)): ?>
    <div class="alert-error" role="alert">
        <?= View::e($error) ?>
    </div>
<?php endif; ?>

<form action="/login" method="POST" autocomplete="on">
    <?= View::csrf() ?>

    <div class="form-group">
        <label for="username" class="form-label">Username</label>
        <input type="text" id="username" name="username" class="form-control"
               value="<?= View::e($oldUsername) ?>"
               required autofocus autocomplete="username" placeholder="Enter your username">
    </div>

    <div class="form-group">
        <label for="password" class="form-label">Password</label>
        <input type="password" id="password" name="password" class="form-control"
               required autocomplete="current-password" placeholder="Enter your password">
    </div>

    <div class="form-group">
        <label for="captcha" class="form-label">
            Security Question: <strong style="color: var(--primary); font-size: 1rem;"><?= View::e($captcha) ?></strong>
        </label>
        <div style="display: flex; gap: 0.5rem;">
            <input type="text" id="captcha" name="captcha" class="form-control"
                   required placeholder="Your answer" autocomplete="off">
            <button type="button" class="btn btn-outline" id="refresh-captcha"
                    style="padding: 0.65rem 0.85rem; border: 1px solid var(--border); border-radius: 6px; background: transparent; cursor: pointer;"
                    title="Refresh question">
                &#x21bb;
            </button>
        </div>
        <small style="font-size: 0.775rem; color: var(--muted); display: block; margin-top: 0.25rem;">
            Solve the arithmetic challenge to verify human session.
        </small>
    </div>

    <button type="submit" class="btn-submit" style="margin-top: 0.5rem;">
        Sign In
    </button>
</form>

<div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--border); text-align: center; font-size: 0.85rem;">
    <a href="/grievances/submit" style="color: var(--primary); text-decoration: none; font-weight: 500;">
        &larr; Are you a student? Submit a Grievance here
    </a>
</div>

<script>
document.getElementById('refresh-captcha')?.addEventListener('click', function () {
    fetch('/auth/captcha', { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(data => {
            if (data.captcha) {
                const label = document.querySelector('label[for="captcha"] strong');
                if (label) label.textContent = data.captcha;
            }
        })
        .catch(() => {});
});
</script>
