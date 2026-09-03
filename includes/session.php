<?php
// Public-site session (default PHP session cookie, e.g. PHPSESSID).
// Completely separate from the admin session in includes/admin_session.php,
// so being logged into the public site never touches admin login state.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
