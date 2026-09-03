<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$postId = (int)($_GET['post_id'] ?? $_POST['post_id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT p.*, u.name AS owner_name FROM posts p JOIN users u ON u.id = p.user_id WHERE p.id = ?'
);
$stmt->execute([$postId]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    die('Post not found.');
}
if ((int)$post['user_id'] === (int)current_user_id()) {
    flash_set('You cannot submit a claim on your own post.', 'error');
    redirect('/posts/view.php?id=' . $postId);
}

$errors = [];
$proof = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $proof = trim($_POST['proof_description'] ?? '');
    if ($proof === '') {
        $errors[] = 'Please describe your proof of ownership.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'SELECT id FROM claims WHERE post_id = ? AND claimant_id = ? AND status = "pending"'
        );
        $stmt->execute([$postId, current_user_id()]);
        if ($stmt->fetch()) {
            $errors[] = 'You already have a pending claim on this post.';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO claims (post_id, claimant_id, proof_description) VALUES (?, ?, ?)'
        );
        $stmt->execute([$postId, current_user_id(), $proof]);

        if ($post['status'] === 'open') {
            $pdo->prepare('UPDATE posts SET status = "claimed" WHERE id = ?')->execute([$postId]);
            log_status_change($pdo, $postId, 'open', 'claimed', current_user_id());
        }

        create_notification(
            $pdo, $post['user_id'], 'claim_request',
            $_SESSION['name'] . ' submitted a claim request on your post "' . $post['title'] . '"',
            BASE_URL . '/posts/view.php?id=' . $postId
        );

        flash_set('Your claim request has been submitted.', 'success');
        redirect('/posts/view.php?id=' . $postId);
    }
}

$pageTitle = 'Submit Claim';
include __DIR__ . '/../includes/header.php';
?>
<div class="form-card">
    <h1>Submit Claim Request</h1>
    <p class="muted">Post: <strong><?= e($post['title']) ?></strong> by <?= e($post['owner_name']) ?></p>
    <?php if ($post['is_high_value']): ?>
        <p class="flash flash-error" style="background:#fff7e6;color:#9a6b00;">
            This item is flagged as <strong>high-value</strong>. An administrator will verify your proof of ownership before this claim can be approved.
        </p>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="flash flash-error">
            <ul><?php foreach ($errors as $err) echo '<li>' . e($err) . '</li>'; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
        <label>Describe your proof of ownership
            <textarea name="proof_description" rows="5" placeholder="e.g. Unique scratch on the back, contents of the wallet, serial number, purchase receipt..." required><?= e($proof) ?></textarea>
        </label>
        <button type="submit" class="btn btn-primary">Submit Claim</button>
    </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
