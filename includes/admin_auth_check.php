<?php
// Include at the top of every admin-panel page, after includes/admin_session.php
// and config/db.php. Guards the *admin* session only — has no knowledge of,
// and no effect on, the public-site session in includes/session.php.

if (!is_admin_logged_in()) {
    flash_set('Please log in to the admin panel to continue.', 'error');
    redirect('/admin/login.php');
}

if (isset($_SESSION['admin_last_activity']) &&
    (time() - $_SESSION['admin_last_activity']) > ADMIN_SESSION_TIMEOUT_SECONDS) {
    session_unset();
    session_destroy();
    session_start();
    flash_set('Your admin session expired due to inactivity. Please log in again.', 'error');
    redirect('/admin/login.php');
}
$_SESSION['admin_last_activity'] = time();

// Re-verify on every request that the account is still an active admin —
// covers the case where another admin revokes/suspends this account mid-session.
$stmt = $pdo->prepare('SELECT role, account_status FROM users WHERE id = ?');
$stmt->execute([current_admin_id()]);
$admin = $stmt->fetch();
if (!$admin || $admin['role'] !== 'admin' || $admin['account_status'] !== 'active') {
    session_unset();
    session_destroy();
    session_start();
    flash_set('Your admin access is no longer valid.', 'error');
    redirect('/admin/login.php');
}
