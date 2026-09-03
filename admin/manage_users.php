<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $userId = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($userId === (int)current_admin_id()) {
        flash_set('You cannot change your own account status.', 'error');
        redirect('/admin/manage_users.php');
    }

    $newStatus = match ($action) {
        'suspend' => 'suspended',
        'ban' => 'banned',
        'activate' => 'active',
        default => null,
    };
    if ($newStatus) {
        $pdo->prepare('UPDATE users SET account_status = ? WHERE id = ?')->execute([$newStatus, $userId]);
        flash_set('User account updated.', 'success');
    }
    redirect('/admin/manage_users.php');
}

$users = $pdo->query('SELECT * FROM users ORDER BY created_at DESC')->fetchAll();

$pageTitle = 'Manage Users';
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-panel">
    <h1>Manage Users</h1>
    <nav class="admin-nav">
        <a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a>
        <a href="<?= BASE_URL ?>/admin/manage_posts.php">Manage Posts</a>
        <a href="<?= BASE_URL ?>/admin/manage_users.php" class="active">Manage Users</a>
        <a href="<?= BASE_URL ?>/admin/manage_categories.php">Manage Categories</a>
        <a href="<?= BASE_URL ?>/claims/admin_verify_claim.php">Verify High-Value Claims</a>
    </nav>

    <table class="admin-table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= e($u['name']) ?></td>
                <td><?= e($u['email']) ?></td>
                <td><?= e($u['role']) ?></td>
                <td><?= e($u['department']) ?></td>
                <td><?= e(ucfirst($u['account_status'])) ?></td>
                <td>
                    <?php if ((int)$u['id'] !== (int)current_admin_id()): ?>
                        <form method="post" action="" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <?php if ($u['account_status'] !== 'suspended'): ?>
                                <button type="submit" name="action" value="suspend" class="btn btn-sm">Suspend</button>
                            <?php endif; ?>
                            <?php if ($u['account_status'] !== 'banned'): ?>
                                <button type="submit" name="action" value="ban" class="btn btn-danger btn-sm">Ban</button>
                            <?php endif; ?>
                            <?php if ($u['account_status'] !== 'active'): ?>
                                <button type="submit" name="action" value="activate" class="btn btn-primary btn-sm">Reactivate</button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
