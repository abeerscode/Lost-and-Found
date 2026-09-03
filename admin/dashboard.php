<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_auth_check.php';

$stats = [
    'total_posts' => (int)$pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn(),
    'lost_posts' => (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE type='lost'")->fetchColumn(),
    'found_posts' => (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE type='found'")->fetchColumn(),
    'resolved_posts' => (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE status='resolved'")->fetchColumn(),
    'total_users' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'pending_claims' => (int)$pdo->query("SELECT COUNT(*) FROM claims WHERE status='pending'")->fetchColumn(),
    'high_value_pending' => (int)$pdo->query(
        "SELECT COUNT(*) FROM claims cl JOIN posts p ON p.id = cl.post_id WHERE cl.status='pending' AND p.is_high_value=1"
    )->fetchColumn(),
];
$resolvedRate = $stats['total_posts'] > 0 ? round($stats['resolved_posts'] / $stats['total_posts'] * 100) : 0;

$categoryStats = $pdo->query(
    "SELECT c.name, COUNT(p.id) AS total FROM categories c
     LEFT JOIN posts p ON p.category_id = c.id
     GROUP BY c.id ORDER BY total DESC"
)->fetchAll();

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-panel">
    <h1>Admin Dashboard</h1>
    <nav class="admin-nav">
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="active">Dashboard</a>
        <a href="<?= BASE_URL ?>/admin/manage_posts.php">Manage Posts</a>
        <a href="<?= BASE_URL ?>/admin/manage_users.php">Manage Users</a>
        <a href="<?= BASE_URL ?>/admin/manage_categories.php">Manage Categories</a>
        <a href="<?= BASE_URL ?>/claims/admin_verify_claim.php">Verify High-Value Claims</a>
    </nav>

    <div class="stat-grid">
        <div class="stat-card"><span class="stat-value"><?= $stats['total_posts'] ?></span><span class="stat-label">Total Posts</span></div>
        <div class="stat-card"><span class="stat-value"><?= $stats['lost_posts'] ?></span><span class="stat-label">Lost</span></div>
        <div class="stat-card"><span class="stat-value"><?= $stats['found_posts'] ?></span><span class="stat-label">Found</span></div>
        <div class="stat-card"><span class="stat-value"><?= $resolvedRate ?>%</span><span class="stat-label">Resolved Rate</span></div>
        <div class="stat-card"><span class="stat-value"><?= $stats['total_users'] ?></span><span class="stat-label">Users</span></div>
        <div class="stat-card"><span class="stat-value"><?= $stats['pending_claims'] ?></span><span class="stat-label">Pending Claims</span></div>
        <div class="stat-card highlight"><span class="stat-value"><?= $stats['high_value_pending'] ?></span><span class="stat-label">High-Value Awaiting Verification</span></div>
    </div>

    <h2>Posts by Category</h2>
    <table class="admin-table">
        <thead><tr><th>Category</th><th>Posts</th></tr></thead>
        <tbody>
        <?php foreach ($categoryStats as $row): ?>
            <tr><td><?= e($row['name']) ?></td><td><?= $row['total'] ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
