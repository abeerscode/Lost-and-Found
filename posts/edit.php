<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$postId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
$stmt->execute([$postId]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    die('Post not found.');
}
// NFR-2.5: only the post owner can edit (admin moderation is handled
// separately, under the admin session, via admin/manage_posts.php).
if ((int)$post['user_id'] !== (int)current_user_id()) {
    http_response_code(403);
    die('You do not have permission to edit this post.');
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $location = trim($_POST['location'] ?? '');
    $itemDate = $_POST['item_date'] ?? '';
    $itemTime = $_POST['item_time'] ?? '';
    $highValue = !empty($_POST['high_value']);

    if ($title === '') $errors[] = 'Title is required.';
    if ($description === '') $errors[] = 'Description is required.';
    if ($location === '') $errors[] = 'Location is required.';
    if (!$itemDate) $errors[] = 'Date is required.';

    $categoryIds = array_column($categories, 'id');
    if (!in_array($categoryId, $categoryIds, true)) {
        $errors[] = 'Please choose a valid category.';
    }

    $itemDateTime = $itemDate . ' ' . ($itemTime ?: '00:00') . ':00';
    if (!$errors && !strtotime($itemDateTime)) $errors[] = 'Invalid date/time.';

    $photoFilename = $post['photo_url'];
    if (!$errors) {
        try {
            $newPhoto = handle_photo_upload('photo');
            if ($newPhoto) $photoFilename = $newPhoto;
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'UPDATE posts SET title=?, description=?, category_id=?, location=?, item_datetime=?, photo_url=?, is_high_value=? WHERE id=?'
        );
        $stmt->execute([$title, $description, $categoryId, $location, $itemDateTime, $photoFilename, $highValue ? 1 : 0, $postId]);
        flash_set('Post updated.', 'success');
        redirect('/posts/view.php?id=' . $postId);
    }
    $post = array_merge($post, [
        'title' => $title, 'description' => $description, 'category_id' => $categoryId,
        'location' => $location, 'is_high_value' => $highValue ? 1 : 0,
    ]);
}

[$datePart, $timePart] = explode(' ', $post['item_datetime']) + ['', ''];
$timePart = substr($timePart, 0, 5);

$pageTitle = 'Edit Post';
include __DIR__ . '/../includes/header.php';
?>
<div class="form-card">
    <h1>Edit Post</h1>

    <?php if ($errors): ?>
        <div class="flash flash-error">
            <ul><?php foreach ($errors as $err) echo '<li>' . e($err) . '</li>'; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" action="" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $post['id'] ?>">
        <label>Title
            <input type="text" name="title" value="<?= e($post['title']) ?>" required>
        </label>
        <label>Description
            <textarea name="description" rows="4" required><?= e($post['description']) ?></textarea>
        </label>
        <label>Category
            <select name="category_id" required>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $post['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Location
            <input type="text" name="location" value="<?= e($post['location']) ?>" required>
        </label>
        <div class="field-row">
            <label>Date
                <input type="date" name="item_date" value="<?= e($datePart) ?>" required>
            </label>
            <label>Time
                <input type="time" name="item_time" value="<?= e($timePart) ?>">
            </label>
        </div>
        <?php if ($post['photo_url']): ?>
            <p><img class="post-photo-thumb" src="<?= e(post_photo_url($post['photo_url'])) ?>" alt=""></p>
        <?php endif; ?>
        <label>Replace Photo (optional)
            <input type="file" name="photo" accept="image/*">
        </label>
        <label class="checkbox-label">
            <input type="checkbox" name="high_value" <?= $post['is_high_value'] ? 'checked' : '' ?>>
            This item is valuable &mdash; requires admin verification to claim.
        </label>
        <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
