<?php
// Global site configuration. Adjust for your environment.

// Application timezone. Database timestamps in this local XAMPP project are
// interpreted as Bangladesh local time, and ISO timestamps sent to JavaScript
// must include the +06:00 offset so relative times remain correct after reloads.
date_default_timezone_set('Asia/Dhaka');

// Restrict registration/login to this email domain (FR-1.1, FR-1.2, NFR-2.1).
define('UNIVERSITY_EMAIL_DOMAIN', '@university.edu');

// Base URL of the app as seen in the browser (no trailing slash).
define('BASE_URL', '/lost-and-found');

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

// Session inactivity timeout in seconds (FR-1.6).
define('SESSION_TIMEOUT_SECONDS', 30 * 60);

// Admin uses a completely separate session (own cookie name), so a browser
// can hold an active public-site login and an active admin-panel login at
// the same time without one signing the other out. See includes/session.php
// vs includes/admin_session.php.
define('ADMIN_SESSION_NAME', 'LNF_ADMIN_SESSID');
define('ADMIN_SESSION_TIMEOUT_SECONDS', 20 * 60);

// NOTE: this file intentionally does not call session_start(). Every entry
// point must explicitly include either includes/session.php (public site)
// or includes/admin_session.php (admin panel) so it's always clear which
// authentication realm a given script runs under.
