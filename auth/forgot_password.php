<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    redirect('/posts/feed.php');
}

$sent = false;
$resetLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Always show the same message, whether or not the account exists,
    // so the form can't be used to enumerate registered emails.
    $sent = true;

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $stmt = $pdo->prepare(
            'INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$user['id'], $token, $expires]);

        $resetLink = BASE_URL . '/auth/reset_password.php?token=' . $token;
        // In production this link would be emailed to the user via mail().
        // It is displayed on-screen here since this project has no SMTP server configured.
    }
}

$pageTitle = 'Forgot Password';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-card">
    <h1>Forgot Password</h1>

    <?php if ($sent): ?>
        <div class="flash flash-success">
            If an account exists for that email, a password reset link has been sent.
        </div>
        <?php if ($resetLink): ?>
            <p class="muted">Demo mode (no email server configured) &mdash; use this link directly:</p>
            <p><a href="<?= e($resetLink) ?>"><?= e($resetLink) ?></a></p>
        <?php endif; ?>
    <?php else: ?>
        <form method="post" action="">
            <?= csrf_field() ?>
            <label>University Email
                <input type="email" name="email" required autofocus>
            </label>
            <button type="submit" class="btn btn-primary">Send Reset Link</button>
        </form>
    <?php endif; ?>
    <p><a href="<?= BASE_URL ?>/auth/login.php">Back to login</a></p>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
