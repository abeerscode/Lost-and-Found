<?php
// FR-2.4: allow a post's status to be updated by the poster.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/posts/feed.php');
}
csrf_verify();

$postId = (int)($_POST['id'] ?? 0);
$newStatus = $_POST['status'] ?? '';

$stmt = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
$stmt->execute([$postId]);
$post = $stmt->fetch();

if (!$post) {
    flash_set('Post not found.', 'error');
    redirect('/posts/feed.php');
}
if ((int)$post['user_id'] !== (int)current_user_id()) {
    http_response_code(403);
    die('You do not have permission to update this post.');
}
if (!in_array($newStatus, ['open', 'claimed', 'resolved'], true)) {
    flash_set('Invalid status.', 'error');
    redirect('/posts/view.php?id=' . $postId);
}

if ($newStatus !== $post['status']) {
    $stmt = $pdo->prepare('UPDATE posts SET status = ? WHERE id = ?');
    $stmt->execute([$newStatus, $postId]);
    log_status_change($pdo, $postId, $post['status'], $newStatus, current_user_id());
}

flash_set('Post status updated to ' . ucfirst($newStatus) . '.', 'success');
redirect('/posts/view.php?id=' . $postId);
