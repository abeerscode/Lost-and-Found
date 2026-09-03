<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();

$errors = [];
$old = [
    'type' => ($_GET['type'] ?? '') === 'lost' ? 'lost' : 'found',
    'title' => '', 'description' => '', 'category_id' => '',
    'location' => '', 'item_date' => '', 'item_time' => '', 'high_value' => false,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $old['type'] = ($_POST['type'] ?? '') === 'lost' ? 'lost' : 'found';
    $old['title'] = trim($_POST['title'] ?? '');
    $old['description'] = trim($_POST['description'] ?? '');
    $old['category_id'] = (int)($_POST['category_id'] ?? 0);
    $old['location'] = trim($_POST['location'] ?? '');
    $old['item_date'] = $_POST['item_date'] ?? '';
    $old['item_time'] = $_POST['item_time'] ?? '';
    $old['high_value'] = !empty($_POST['high_value']);

    if ($old['title'] === '') $errors[] = 'Title is required.';
    if ($old['description'] === '') $errors[] = 'Description is required.';
    if ($old['location'] === '') $errors[] = 'Location is required.';
    if (!$old['item_date']) $errors[] = 'Date is required.';

    $categoryIds = array_column($categories, 'id');
    if (!in_array($old['category_id'], $categoryIds, true)) {
        $errors[] = 'Please choose a valid category.';
    }

    $itemDateTime = null;
    if (!$errors) {
        $time = $old['item_time'] ?: '00:00';
        $itemDateTime = $old['item_date'] . ' ' . $time . ':00';
        if (!strtotime($itemDateTime)) $errors[] = 'Invalid date/time.';
    }

    $photoFilename = null;
    if (!$errors) {
        try {
            $photoFilename = handle_photo_upload('photo');
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if (!$errors) {
        $category = null;
        foreach ($categories as $c) {
            if ((int)$c['id'] === $old['category_id']) { $category = $c; break; }
        }
        $isHighValue = $old['high_value'] || (int)$category['is_high_value_default'] === 1;

        $stmt = $pdo->prepare(
            'INSERT INTO posts (user_id, type, title, description, category_id, location, item_datetime, photo_url, is_high_value)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            current_user_id(), $old['type'], $old['title'], $old['description'],
            $old['category_id'], $old['location'], $itemDateTime, $photoFilename, $isHighValue ? 1 : 0,
        ]);
        $postId = $pdo->lastInsertId();
        log_status_change($pdo, $postId, null, 'open', current_user_id());

        flash_set('Your ' . $old['type'] . ' item post has been published.', 'success');
        redirect('/posts/view.php?id=' . $postId);
    }
}

$pageTitle = 'New Post';
include __DIR__ . '/../includes/header.php';
?>
<div class="form-card">
    <h1>Report a Lost or Found Item</h1>

    <?php if ($errors): ?>
        <div class="flash flash-error">
            <ul><?php foreach ($errors as $err) echo '<li>' . e($err) . '</li>'; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" action="" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="type-toggle">
            <label><input type="radio" name="type" value="found" <?= $old['type'] === 'found' ? 'checked' : '' ?>> I Found an Item</label>
            <label><input type="radio" name="type" value="lost" <?= $old['type'] === 'lost' ? 'checked' : '' ?>> I Lost an Item</label>
        </div>

        <label>Title
            <input type="text" name="title" value="<?= e($old['title']) ?>" placeholder="e.g. Black leather wallet" required>
        </label>
        <label>Description
            <textarea name="description" rows="4" required><?= e($old['description']) ?></textarea>
        </label>
        <label>Category
            <select name="category_id" required>
                <option value="">Select a category</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $old['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Location
            <input type="text" name="location" value="<?= e($old['location']) ?>" placeholder="e.g. Library, 2nd floor" required>
        </label>
        <div class="field-row">
            <label>Date
                <input type="date" name="item_date" value="<?= e($old['item_date']) ?>" required>
            </label>
            <label>Time (optional)
                <input type="time" name="item_time" value="<?= e($old['item_time']) ?>">
            </label>
        </div>
        <label>Photo (optional)
            <input type="file" name="photo" accept="image/*">
        </label>
        <label class="checkbox-label">
            <input type="checkbox" name="high_value" <?= $old['high_value'] ? 'checked' : '' ?>>
            This item is valuable (cash, ID/passport, laptop, phone, etc.) &mdash; requires admin verification to claim.
        </label>
        <button type="submit" class="btn btn-primary">Publish Post</button>
    </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
