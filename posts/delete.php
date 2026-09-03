<?php
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
$stmt = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
$stmt->execute([$postId]);
$post = $stmt->fetch();

if (!$post) {
    flash_set('Post not found.', 'error');
    redirect('/posts/feed.php');
}
// NFR-2.5: only the post owner can delete here (admin moderation removal
// is handled separately, under the admin session, via admin/manage_posts.php).
if ((int)$post['user_id'] !== (int)current_user_id()) {
    http_response_code(403);
    die('You do not have permission to delete this post.');
}

if ($post['photo_url'] && is_file(UPLOAD_DIR . $post['photo_url'])) {
    unlink(UPLOAD_DIR . $post['photo_url']);
}

$stmt = $pdo->prepare('DELETE FROM posts WHERE id = ?');
$stmt->execute([$postId]);

flash_set('Post deleted.', 'success');
redirect('/posts/feed.php');
