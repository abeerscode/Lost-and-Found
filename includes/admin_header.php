<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' — ' : '' ?>Admin Panel</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">
</head>
<body>
<header class="topbar admin-topbar">
    <div class="topbar-inner">
        <a class="brand" href="<?= BASE_URL ?>/admin/dashboard.php">
            <span class="brand-mark">LF</span>
            <span class="brand-text">Admin</span>
        </a>
        <?php if (is_admin_logged_in()): ?>
        <nav class="nav-links">
            <span class="muted"><?= e($_SESSION['admin_name']) ?></span>
            <a href="<?= BASE_URL ?>/index.php">Main site</a>
            <a href="<?= BASE_URL ?>/admin/logout.php">Log out</a>
        </nav>
        <?php endif; ?>
    </div>
</header>
<main class="page">
<?php $flash = flash_get(); if ($flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>
