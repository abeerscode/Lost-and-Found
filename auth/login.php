<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    redirect('/posts/feed.php');
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $errors[] = 'Invalid email or password.';
    } elseif ($user['account_status'] !== 'active') {
        $errors[] = 'Your account has been ' . $user['account_status'] . '. Contact an administrator.';
    }

    if (!$errors) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['last_activity'] = time();
        flash_set('Welcome back, ' . $user['name'] . '!', 'success');
        redirect('/posts/feed.php');
    }
}

$pageTitle = 'Login';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-card">
    <h1>Log In</h1>

    <?php if ($errors): ?>
        <div class="flash flash-error">
            <ul><?php foreach ($errors as $err) echo '<li>' . e($err) . '</li>'; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?= csrf_field() ?>
        <label>University Email
            <input type="email" name="email" value="<?= e($email) ?>" required autofocus>
        </label>
        <label>Password
            <span class="password-field">
                <input id="login-password" type="password" name="password" minlength="8" autocomplete="current-password" required>
                <button class="password-toggle" type="button" data-password-toggle="login-password" aria-label="Show password" aria-pressed="false">
                    <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"/><circle cx="12" cy="12" r="2.5"/></svg>
                    <svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A10.7 10.7 0 0 1 12 6c6 0 9.5 6 9.5 6a16.8 16.8 0 0 1-2.1 2.8M6.2 6.2C3.8 7.8 2.5 12 2.5 12s3.5 6 9.5 6c1.6 0 3-.4 4.2-1"/></svg>
                </button>
            </span>
        </label>
        <button type="submit" class="btn btn-primary">Log In</button>
    </form>
    <p><a href="<?= BASE_URL ?>/auth/forgot_password.php">Forgot password?</a></p>
    <p>New here? <a href="<?= BASE_URL ?>/auth/register.php">Create an account</a></p>
</div>
<script src="<?= BASE_URL ?>/js/auth-forms.js?v=<?= @filemtime(__DIR__ . '/../js/auth-forms.js') ?: time() ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
