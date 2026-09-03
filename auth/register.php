<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    redirect('/posts/feed.php');
}

$errors = [];
$old = ['name' => '', 'university_id' => '', 'email' => '', 'phone' => '', 'department' => '', 'batch' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $old['name'] = trim($_POST['name'] ?? '');
    $old['university_id'] = trim($_POST['university_id'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $old['phone'] = trim($_POST['phone'] ?? '');
    $old['department'] = trim($_POST['department'] ?? '');
    $old['batch'] = trim($_POST['batch'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($old['name'] === '') $errors[] = 'Name is required.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    } elseif (!is_university_email($old['email'])) {
        $errors[] = 'Registration is restricted to university email addresses (' . UNIVERSITY_EMAIL_DOMAIN . ').';
    }
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$old['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists.';
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, university_id, email, password_hash, phone, department, batch) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $old['name'],
            $old['university_id'] ?: null,
            $old['email'],
            $hash,
            $old['phone'] ?: null,
            $old['department'] ?: null,
            $old['batch'] ?: null,
        ]);
        flash_set('Account created successfully. Please log in.', 'success');
        redirect('/auth/login.php');
    }
}

$pageTitle = 'Register';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-card">
    <h1>Create an Account</h1>
    <p class="muted">Registration is restricted to university email addresses (<?= e(UNIVERSITY_EMAIL_DOMAIN) ?>).</p>

    <?php if ($errors): ?>
        <div class="flash flash-error">
            <ul><?php foreach ($errors as $err) echo '<li>' . e($err) . '</li>'; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?= csrf_field() ?>
        <label>Full Name
            <input type="text" name="name" value="<?= e($old['name']) ?>" required>
        </label>
        <label>Student/Staff ID
            <input type="text" name="university_id" value="<?= e($old['university_id']) ?>">
        </label>
        <label>University Email
            <input type="email" name="email" value="<?= e($old['email']) ?>" placeholder="you<?= e(UNIVERSITY_EMAIL_DOMAIN) ?>" required>
        </label>
        <label>Phone
            <input type="text" name="phone" value="<?= e($old['phone']) ?>">
        </label>
        <label>Department
            <input type="text" name="department" value="<?= e($old['department']) ?>">
        </label>
        <label>Batch
            <input type="text" name="batch" value="<?= e($old['batch']) ?>" placeholder="e.g. 2022">
        </label>
        <label>Password
            <input type="password" name="password" minlength="8" required>
        </label>
        <label>Confirm Password
            <input type="password" name="confirm_password" minlength="8" required>
        </label>
        <button type="submit" class="btn btn-primary">Register</button>
    </form>
    <p>Already have an account? <a href="<?= BASE_URL ?>/auth/login.php">Log in</a></p>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
