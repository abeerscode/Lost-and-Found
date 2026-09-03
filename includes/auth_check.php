<?php
// Include at the top of any protected page. Requires config.php, db.php,
// and functions.php to already be loaded by the including script.

if (!is_logged_in()) {
    flash_set('Please log in to continue.', 'error');
    redirect('/auth/login.php');
}

// Session inactivity timeout (FR-1.6).
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT_SECONDS) {
    session_unset();
    session_destroy();
    session_start();
    flash_set('Your session expired due to inactivity. Please log in again.', 'error');
    redirect('/auth/login.php');
}
$_SESSION['last_activity'] = time();

// Suspended/banned accounts are locked out immediately (FR-6.3).
$stmt = $pdo->prepare('SELECT account_status FROM users WHERE id = ?');
$stmt->execute([current_user_id()]);
$status = $stmt->fetchColumn();
if ($status !== 'active') {
    session_unset();
    session_destroy();
    session_start();
    flash_set('Your account has been ' . $status . '. Contact an administrator.', 'error');
    redirect('/auth/login.php');
}
