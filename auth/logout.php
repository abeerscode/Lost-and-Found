<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

$_SESSION = [];
session_destroy();
session_start();
flash_set('You have been logged out.', 'success');
redirect('/auth/login.php');
