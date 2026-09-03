<?php
// Dedicated admin login — separate from auth/login.php. Uses the admin
// session (own cookie, see includes/admin_session.php), so logging in here
// does not touch or replace any public-site login already active in the
// same browser, and vice versa.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_admin_logged_in()) {
    redirect('/admin/dashboard.php');
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
    } elseif ($user['role'] !== 'admin') {
        $errors[] = 'This account does not have administrator access.';
    } elseif ($user['account_status'] !== 'active') {
        $errors[] = 'This account has been ' . $user['account_status'] . '.';
    }

    if (!$errors) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_name'] = $user['name'];
        $_SESSION['admin_last_activity'] = time();
        flash_set('Welcome to the admin panel, ' . $user['name'] . '.', 'success');
        redirect('/admin/dashboard.php');
    }
}

$pageTitle = 'Admin Login';
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="auth-card">
    <h1>Admin Login</h1>
    <p class="muted">Separate from the public-site login. Only accounts with administrator access can sign in here.</p>

    <?php if ($errors): ?>
        <div class="flash flash-error">
            <ul><?php foreach ($errors as $err) echo '<li>' . e($err) . '</li>'; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?= csrf_field() ?>
        <label>Admin Email
            <input type="email" name="email" value="<?= e($email) ?>" required autofocus>
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <button type="submit" class="btn btn-primary">Log In to Admin Panel</button>
    </form>
    <p><a href="<?= BASE_URL ?>/index.php">&larr; Back to the main site</a></p>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
