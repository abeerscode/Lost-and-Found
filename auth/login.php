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
            <input type="password" name="password" required>
        </label>
        <button type="submit" class="btn btn-primary">Log In</button>
    </form>
    <p><a href="<?= BASE_URL ?>/auth/forgot_password.php">Forgot password?</a></p>
    <p>New here? <a href="<?= BASE_URL ?>/auth/register.php">Create an account</a></p>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
