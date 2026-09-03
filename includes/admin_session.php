<?php
// Admin-panel session — uses its own cookie name (ADMIN_SESSION_NAME) so it
// never collides with the public-site session in includes/session.php.
// This is what lets someone be logged into the public site as themselves
// AND logged into /admin as an administrator at the same time in one
// browser: two independent cookies, two independent PHP sessions.
if (session_status() === PHP_SESSION_NONE) {
    session_name(ADMIN_SESSION_NAME);
    session_start();
}
