<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' — ' : '' ?>Lost &amp; Found</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="<?= is_logged_in() ? BASE_URL . '/posts/feed.php' : BASE_URL . '/index.php' ?>">
            <span class="brand-mark">LF</span>
            <span class="brand-text">Lost &amp; Found</span>
        </a>
        <?php if (is_logged_in()): ?>
        <nav class="nav-links">
            <a href="<?= BASE_URL ?>/posts/feed.php">Browse</a>
            <a href="<?= BASE_URL ?>/messages/inbox.php">Inbox<?php $mc = unread_message_count($pdo, current_user_id()); if ($mc > 0): ?><span class="pill"><?= $mc ?></span><?php endif; ?></a>
            <a href="<?= BASE_URL ?>/notifications/list.php">Notifications<?php $nc = unread_notification_count($pdo, current_user_id()); if ($nc > 0): ?><span class="pill"><?= $nc ?></span><?php endif; ?></a>
            <a href="<?= BASE_URL ?>/auth/profile.php">Profile</a>
            <?php if (account_role_is_admin()): ?><a href="<?= BASE_URL ?>/admin/login.php" title="Admin access requires a separate login">Admin</a><?php endif; ?>
            <a href="<?= BASE_URL ?>/auth/logout.php">Log out</a>
            <a class="nav-cta" href="<?= BASE_URL ?>/posts/create.php">Report item</a>
        </nav>
        <?php else: ?>
        <nav class="nav-links public-nav">
            <a href="<?= BASE_URL ?>/index.php#how-it-works">How it works</a>
            <a href="<?= BASE_URL ?>/auth/login.php">Log in</a>
            <a class="nav-cta" href="<?= BASE_URL ?>/auth/register.php">Create account</a>
        </nav>
        <?php endif; ?>
    </div>
</header>
<main class="page<?= !is_logged_in() ? ' page-public' : '' ?>">
<?php $flash = flash_get(); if ($flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>
