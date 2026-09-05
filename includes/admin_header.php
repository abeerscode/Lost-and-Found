<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' — ' : '' ?>Lost &amp; Found Admin</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css?v=<?= @filemtime(__DIR__ . '/../css/style.css') ?: time() ?>">
</head>
<body class="admin-body">
<?php if (is_admin_logged_in()): ?>
<?php
$currentAdmin = null;
try {
    $stmt = $pdo->prepare('SELECT id, name, email, profile_photo, department, person_type FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([current_admin_id()]);
    $currentAdmin = $stmt->fetch();
} catch (Throwable $e) {
    $currentAdmin = null;
}
$adminName = $currentAdmin['name'] ?? ($_SESSION['admin_name'] ?? 'Administrator');
$adminInitials = '';
foreach (preg_split('/\s+/', trim($adminName)) as $part) {
    if ($part !== '') $adminInitials .= strtoupper(substr($part, 0, 1));
}
$adminInitials = substr($adminInitials, 0, 2) ?: 'AD';
$currentFile = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
$active = $adminActive ?? match ($currentFile) {
    'users.php', 'user_view.php' => 'users',
    'posts.php', 'post_view.php', 'manage_categories.php', 'categories.php' => 'posts',
    'claims.php', 'admin_verify_claim.php' => 'claims',
    'activity.php' => 'activity',
    'profile.php' => 'profile',
    default => 'dashboard',
};
$pendingClaimCount = 0;
try {
    $pendingClaimCount = (int)$pdo->query("SELECT COUNT(*) FROM claims WHERE status='pending'")->fetchColumn();
} catch (Throwable $e) {}
?>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <div class="admin-sidebar-main">
            <a class="admin-brand" href="<?= BASE_URL ?>/admin/dashboard.php">
                <span class="admin-brand-mark">LF</span>
                <span><strong>Lost &amp; Found</strong><small>Administration</small></span>
            </a>

            <nav class="admin-sidebar-nav" aria-label="Admin navigation">
                <a class="admin-sidebar-link<?= $active === 'dashboard' ? ' active' : '' ?>" href="<?= BASE_URL ?>/admin/dashboard.php">
                    <svg viewBox="0 0 24 24"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>
                    <span>Dashboard</span>
                </a>
                <a class="admin-sidebar-link<?= $active === 'users' ? ' active' : '' ?>" href="<?= BASE_URL ?>/admin/users.php">
                    <svg viewBox="0 0 24 24"><path d="M16 20v-1.5A3.5 3.5 0 0 0 12.5 15h-5A3.5 3.5 0 0 0 4 18.5V20M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM17 8a3 3 0 1 0 0-6M18 15h1a3 3 0 0 1 3 3v2"/></svg>
                    <span>Users</span>
                </a>
                <a class="admin-sidebar-link<?= $active === 'posts' ? ' active' : '' ?>" href="<?= BASE_URL ?>/admin/posts.php">
                    <svg viewBox="0 0 24 24"><path d="M5 3h11l3 3v15H5zM8 10h8M8 14h8M8 18h5"/></svg>
                    <span>Posts</span>
                </a>
                <a class="admin-sidebar-link<?= $active === 'claims' ? ' active' : '' ?>" href="<?= BASE_URL ?>/admin/claims.php">
                    <svg viewBox="0 0 24 24"><path d="M12 3 4 6v5c0 5.2 3.4 8.8 8 10 4.6-1.2 8-4.8 8-10V6zM9 12l2 2 4-5"/></svg>
                    <span>Claims</span>
                    <?php if ($pendingClaimCount > 0): ?><span class="admin-nav-count"><?= $pendingClaimCount > 99 ? '99+' : $pendingClaimCount ?></span><?php endif; ?>
                </a>
                <a class="admin-sidebar-link<?= $active === 'activity' ? ' active' : '' ?>" href="<?= BASE_URL ?>/admin/activity.php">
                    <svg viewBox="0 0 24 24"><path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/></svg>
                    <span>Activity</span>
                </a>
            </nav>
        </div>

        <div class="admin-sidebar-footer">
            <a class="admin-secondary-link" href="<?= BASE_URL ?>/index.php">
                <svg viewBox="0 0 24 24"><path d="M10 6H5a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5M14 3h7v7M21 3l-9 9"/></svg>
                <span>Main site</span>
            </a>
            <a class="admin-account-card<?= $active === 'profile' ? ' active' : '' ?>" href="<?= BASE_URL ?>/admin/profile.php">
                <span class="admin-account-avatar">
                    <?php if ($currentAdmin && !empty($currentAdmin['profile_photo'])): ?>
                        <img src="<?= e(profile_photo_url($currentAdmin['profile_photo'])) ?>" alt="">
                    <?php else: ?><?= e($adminInitials) ?><?php endif; ?>
                </span>
                <span class="admin-account-meta"><strong><?= e($adminName) ?></strong><small>Administrator</small></span>
                <svg class="admin-account-chevron" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg>
            </a>
        </div>
    </aside>

    <section class="admin-workspace">
        <header class="admin-workspace-header">
            <div>
                <span class="admin-eyebrow">Administration</span>
                <h1><?= e($pageTitle ?? 'Dashboard') ?></h1>
            </div>
            <div class="admin-header-actions">
                <span class="admin-header-role">Administrator</span>
            </div>
        </header>
        <main class="admin-content">
            <?php $flash = flash_get(); if ($flash): ?>
                <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>
<?php else: ?>
<header class="admin-login-topbar">
    <a class="brand" href="<?= BASE_URL ?>/index.php"><span class="brand-mark">LF</span><span class="brand-text">Lost &amp; Found</span></a>
    <span class="admin-login-label">Admin access</span>
</header>
<main class="admin-login-page">
<?php $flash = flash_get(); if ($flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>
<?php endif; ?>
