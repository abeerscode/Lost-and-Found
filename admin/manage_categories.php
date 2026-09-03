<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_auth_check.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $highValueDefault = !empty($_POST['is_high_value_default']);
        if ($name === '') {
            $errors[] = 'Category name is required.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO categories (name, is_high_value_default) VALUES (?, ?)');
            try {
                $stmt->execute([$name, $highValueDefault ? 1 : 0]);
                flash_set('Category added.', 'success');
                redirect('/admin/manage_categories.php');
            } catch (PDOException $ex) {
                $errors[] = 'A category with that name already exists.';
            }
        }
    } elseif ($action === 'delete') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE category_id = ?');
        $stmt->execute([$categoryId]);
        if ((int)$stmt->fetchColumn() > 0) {
            flash_set('Cannot delete a category that has posts assigned to it.', 'error');
        } else {
            $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$categoryId]);
            flash_set('Category deleted.', 'success');
        }
        redirect('/admin/manage_categories.php');
    }
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();

$pageTitle = 'Manage Categories';
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-panel">
    <h1>Manage Categories</h1>
    <nav class="admin-nav">
        <a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a>
        <a href="<?= BASE_URL ?>/admin/manage_posts.php">Manage Posts</a>
        <a href="<?= BASE_URL ?>/admin/manage_users.php">Manage Users</a>
        <a href="<?= BASE_URL ?>/admin/manage_categories.php" class="active">Manage Categories</a>
        <a href="<?= BASE_URL ?>/claims/admin_verify_claim.php">Verify High-Value Claims</a>
    </nav>

    <?php if ($errors): ?>
        <div class="flash flash-error">
            <ul><?php foreach ($errors as $err) echo '<li>' . e($err) . '</li>'; ?></ul>
        </div>
    <?php endif; ?>

    <table class="admin-table">
        <thead><tr><th>Name</th><th>High-Value by Default</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($categories as $cat): ?>
            <tr>
                <td><?= e($cat['name']) ?></td>
                <td><?= $cat['is_high_value_default'] ? 'Yes' : 'No' ?></td>
                <td>
                    <form method="post" action="" onsubmit="return confirm('Delete this category?');" style="display:inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Add Category</h2>
    <form method="post" action="" class="inline-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <label>Name <input type="text" name="name" required></label>
        <label class="checkbox-label"><input type="checkbox" name="is_high_value_default"> High-value by default</label>
        <button type="submit" class="btn btn-primary">Add Category</button>
    </form>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
