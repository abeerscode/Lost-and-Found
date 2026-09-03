<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$errors = [];
$done = false;

$stmt = $pdo->prepare(
    'SELECT pr.*, u.email FROM password_resets pr JOIN users u ON u.id = pr.user_id
     WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()'
);
$stmt->execute([$token]);
$reset = $stmt->fetch();

if (!$reset) {
    $errors[] = 'This password reset link is invalid or has expired.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $reset['user_id']]);
        $pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = ?')->execute([$reset['id']]);
        $pdo->commit();
        $done = true;
    }
}

$pageTitle = 'Reset Password';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-card">
    <h1>Reset Password</h1>

    <?php if ($errors): ?>
        <div class="flash flash-error">
            <ul><?php foreach ($errors as $err) echo '<li>' . e($err) . '</li>'; ?></ul>
        </div>
    <?php endif; ?>

    <?php if ($done): ?>
        <div class="flash flash-success">Your password has been updated.</div>
        <p><a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-primary">Log In</a></p>
    <?php elseif ($reset): ?>
        <form method="post" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <label>New Password
                <input type="password" name="password" minlength="8" required autofocus>
            </label>
            <label>Confirm Password
                <input type="password" name="confirm_password" minlength="8" required>
            </label>
            <button type="submit" class="btn btn-primary">Update Password</button>
        </form>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
