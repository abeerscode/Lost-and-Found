<?php
// Destroys only the admin session — the public-site session (if any) in the
// same browser is untouched.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_session.php';
require_once __DIR__ . '/../includes/functions.php';

$_SESSION = [];
session_destroy();
session_name(ADMIN_SESSION_NAME);
session_start();
flash_set('Logged out of the admin panel.', 'success');
redirect('/admin/login.php');
