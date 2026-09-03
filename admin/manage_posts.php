<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $postId = (int)($_POST['post_id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete') {
        $stmt = $pdo->prepare('SELECT photo_url FROM posts WHERE id = ?');
        $stmt->execute([$postId]);
        $photo = $stmt->fetchColumn();
        if ($photo && is_file(UPLOAD_DIR . $photo)) unlink(UPLOAD_DIR . $photo);
        $pdo->prepare('DELETE FROM posts WHERE id = ?')->execute([$postId]);
        flash_set('Post removed.', 'success');
    }
    redirect('/admin/manage_posts.php');
}

$posts = $pdo->query(
    "SELECT p.*, u.name AS owner_name, c.name AS category_name
     FROM posts p JOIN users u ON u.id = p.user_id JOIN categories c ON c.id = p.category_id
     ORDER BY p.created_at DESC"
)->fetchAll();

$pageTitle = 'Manage Posts';
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-panel">
    <h1>Manage Posts</h1>
    <nav class="admin-nav">
        <a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a>
        <a href="<?= BASE_URL ?>/admin/manage_posts.php" class="active">Manage Posts</a>
        <a href="<?= BASE_URL ?>/admin/manage_users.php">Manage Users</a>
        <a href="<?= BASE_URL ?>/admin/manage_categories.php">Manage Categories</a>
        <a href="<?= BASE_URL ?>/claims/admin_verify_claim.php">Verify High-Value Claims</a>
    </nav>

    <table class="admin-table">
        <thead><tr><th>Title</th><th>Type</th><th>Category</th><th>Owner</th><th>Status</th><th>Posted</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($posts as $p): ?>
            <tr>
                <td><a href="<?= BASE_URL ?>/posts/view.php?id=<?= $p['id'] ?>"><?= e($p['title']) ?></a></td>
                <td><?= e(ucfirst($p['type'])) ?></td>
                <td><?= e($p['category_name']) ?></td>
                <td><?= e($p['owner_name']) ?></td>
                <td><?= status_badge($p['status']) ?> <?= $p['is_high_value'] ? '<span class="badge badge-highvalue">HV</span>' : '' ?></td>
                <td><?= e(time_ago($p['created_at'])) ?></td>
                <td>
                    <form method="post" action="" onsubmit="return confirm('Remove this post?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="post_id" value="<?= $p['id'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
