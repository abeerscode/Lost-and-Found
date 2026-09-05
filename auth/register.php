<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    redirect('/posts/feed.php');
}

$errors = [];
$old = ['name' => '', 'university_id' => '', 'email' => '', 'phone' => '', 'department' => '', 'batch' => '', 'person_type' => 'student'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $old['name'] = trim($_POST['name'] ?? '');
    $old['university_id'] = trim($_POST['university_id'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $old['phone'] = trim($_POST['phone'] ?? '');
    $old['department'] = trim($_POST['department'] ?? '');
    $old['batch'] = trim($_POST['batch'] ?? '');
    $old['person_type'] = strtolower(trim($_POST['person_type'] ?? 'student'));
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($old['name'] === '') $errors[] = 'Name is required.';
    $allowedPersonTypes = ['student', 'faculty', 'staff'];
    if (!in_array($old['person_type'], $allowedPersonTypes, true)) $errors[] = 'Select a valid person type.';
    if ($old['university_id'] === '') $errors[] = 'University ID is required.';
    if ($old['person_type'] === 'student' && $old['batch'] === '') $errors[] = 'Batch is required for students.';
    if ($old['person_type'] !== 'student') $old['batch'] = '';
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
            'INSERT INTO users (name, university_id, email, password_hash, role, person_type, phone, department, batch) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $old['name'],
            $old['university_id'] ?: null,
            $old['email'],
            $hash,
            'user',
            $old['person_type'],
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
        <label>Person type
            <select id="person_type" name="person_type" required>
                <option value="student" <?= $old['person_type'] === 'student' ? 'selected' : '' ?>>Student</option>
                <option value="faculty" <?= $old['person_type'] === 'faculty' ? 'selected' : '' ?>>Faculty</option>
                <option value="staff" <?= $old['person_type'] === 'staff' ? 'selected' : '' ?>>Staff</option>
            </select>
        </label>
        <label>University ID
            <input type="text" name="university_id" value="<?= e($old['university_id']) ?>" required>
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
        <label id="batch-field">Batch <span class="field-hint">Students only</span>
            <input id="batch" type="text" name="batch" value="<?= e($old['batch']) ?>" placeholder="e.g. 2022">
        </label>
        <label>Password <span class="field-hint">At least 8 characters</span>
            <span class="password-field">
                <input id="register-password" type="password" name="password" minlength="8" autocomplete="new-password" required>
                <button class="password-toggle" type="button" data-password-toggle="register-password" aria-label="Show password" aria-pressed="false">
                    <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"/><circle cx="12" cy="12" r="2.5"/></svg>
                    <svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A10.7 10.7 0 0 1 12 6c6 0 9.5 6 9.5 6a16.8 16.8 0 0 1-2.1 2.8M6.2 6.2C3.8 7.8 2.5 12 2.5 12s3.5 6 9.5 6c1.6 0 3-.4 4.2-1"/></svg>
                </button>
            </span>
        </label>
        <label>Confirm Password
            <span class="password-field">
                <input id="register-confirm-password" type="password" name="confirm_password" minlength="8" autocomplete="new-password" required>
                <button class="password-toggle" type="button" data-password-toggle="register-confirm-password" aria-label="Show password" aria-pressed="false">
                    <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"/><circle cx="12" cy="12" r="2.5"/></svg>
                    <svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A10.7 10.7 0 0 1 12 6c6 0 9.5 6 9.5 6a16.8 16.8 0 0 1-2.1 2.8M6.2 6.2C3.8 7.8 2.5 12 2.5 12s3.5 6 9.5 6c1.6 0 3-.4 4.2-1"/></svg>
                </button>
            </span>
        </label>
        <button type="submit" class="btn btn-primary">Register</button>
    </form>
    <p>Already have an account? <a href="<?= BASE_URL ?>/auth/login.php">Log in</a></p>
</div>
<script src="<?= BASE_URL ?>/js/auth-forms.js?v=<?= @filemtime(__DIR__ . '/../js/auth-forms.js') ?: time() ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
