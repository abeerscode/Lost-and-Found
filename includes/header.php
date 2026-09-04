<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' — ' : '' ?>Lost &amp; Found</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css?v=<?= @filemtime(__DIR__ . '/../css/style.css') ?: time() ?>">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="<?= is_logged_in() ? BASE_URL . '/posts/feed.php' : BASE_URL . '/index.php' ?>">
            <span class="brand-mark">LF</span>
            <span class="brand-text">Lost &amp; Found</span>
        </a>

        <?php if (is_logged_in()): ?>
            <?php
            $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
            $homeActive = str_contains($currentPath, '/posts/feed.php');
            $inboxActive = str_contains($currentPath, '/messages/');
            $notificationsActive = str_contains($currentPath, '/notifications/');
            $mc = unread_message_count($pdo, current_user_id());
            $nc = unread_notification_count($pdo, current_user_id());

            $headerUser = null;
            try {
                $headerUserStmt = $pdo->prepare('SELECT name, profile_photo FROM users WHERE id = ? LIMIT 1');
                $headerUserStmt->execute([current_user_id()]);
                $headerUser = $headerUserStmt->fetch();
            } catch (Throwable $e) {
                $headerUser = null;
            }

            $headerInitials = 'U';
            if ($headerUser && !empty($headerUser['name'])) {
                $headerInitials = '';
                foreach (preg_split('/\s+/', trim($headerUser['name'])) as $part) {
                    if ($part !== '') $headerInitials .= strtoupper(substr($part, 0, 1));
                }
                $headerInitials = substr($headerInitials, 0, 2) ?: 'U';
            }
            ?>

            <nav class="app-nav" aria-label="Primary navigation">
                <a class="app-nav-item<?= $homeActive ? ' active' : '' ?>" href="<?= BASE_URL ?>/posts/feed.php" aria-label="Home"<?= $homeActive ? ' aria-current="page"' : '' ?>>
                    <span class="app-nav-icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 10.8 12 3l9 7.8v9.7a.5.5 0 0 1-.5.5h-5.8v-6.4H9.3V21H3.5a.5.5 0 0 1-.5-.5z"/></svg>
                    </span>
                    <span class="app-nav-label">Home</span>
                </a>
                <a class="app-nav-item<?= $inboxActive ? ' active' : '' ?>" href="<?= BASE_URL ?>/messages/inbox.php" aria-label="Inbox"<?= $inboxActive ? ' aria-current="page"' : '' ?>>
                    <span class="app-nav-icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 5.5h16a1 1 0 0 1 1 1v10a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 16.5v-10a1 1 0 0 1 1-1Z"/><path d="m4 7 6.5 5.1a2.4 2.4 0 0 0 3 0L20 7"/></svg>
                    </span>
                    <span class="app-nav-label">Inbox</span>
                    <?php if ($mc > 0): ?><span class="app-nav-badge"><?= $mc > 99 ? '99+' : $mc ?></span><?php endif; ?>
                </a>
                <a class="app-nav-item<?= $notificationsActive ? ' active' : '' ?>" href="<?= BASE_URL ?>/notifications/list.php" aria-label="Notifications"<?= $notificationsActive ? ' aria-current="page"' : '' ?>>
                    <span class="app-nav-icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 8.5h18C21 16 18 16 18 9Z"/><path d="M9.5 20a2.8 2.8 0 0 0 5 0"/></svg>
                    </span>
                    <span class="app-nav-label">Notifications</span>
                    <?php if ($nc > 0): ?><span class="app-nav-badge"><?= $nc > 99 ? '99+' : $nc ?></span><?php endif; ?>
                </a>
            </nav>

            <a class="header-profile" href="<?= BASE_URL ?>/auth/profile.php" aria-label="Open profile">
                <?php if ($headerUser && !empty($headerUser['profile_photo'])): ?>
                    <img src="<?= e(profile_photo_url($headerUser['profile_photo'])) ?>" alt="">
                <?php else: ?>
                    <span class="header-profile-fallback" aria-hidden="true"><?= e($headerInitials) ?></span>
                <?php endif; ?>
            </a>
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
