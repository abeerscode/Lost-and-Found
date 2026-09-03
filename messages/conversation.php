<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$userId = current_user_id();
$withId = (int)($_GET['with'] ?? $_POST['with'] ?? 0);
$postId = isset($_GET['post_id']) ? (int)$_GET['post_id'] : (isset($_POST['post_id']) ? (int)$_POST['post_id'] : null);
if ($withId === (int)$userId || $withId <= 0) { http_response_code(400); die('Invalid conversation.'); }

$stmt = $pdo->prepare('SELECT id, name, department FROM users WHERE id = ?');
$stmt->execute([$withId]);
$partner = $stmt->fetch();
if (!$partner) { http_response_code(404); die('User not found.'); }

$post = null;
if ($postId) {
    $stmt = $pdo->prepare('SELECT id, title, type, location, photo_url, status FROM posts WHERE id = ?');
    $stmt->execute([$postId]);
    $post = $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $content = trim($_POST['content'] ?? '');
    if ($content !== '') {
        $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, post_id, content) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $withId, $postId, $content]);
        create_notification($pdo, $withId, 'message', $_SESSION['name'] . ' sent you a message.', BASE_URL . '/messages/conversation.php?with=' . $userId . ($postId ? '&post_id=' . $postId : ''));
    }
    redirect('/messages/conversation.php?with=' . $withId . ($postId ? '&post_id=' . $postId : ''));
}

if ($postId) {
    $pdo->prepare('UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND post_id = ?')->execute([$withId, $userId, $postId]);
    $stmt = $pdo->prepare('SELECT * FROM messages WHERE post_id = ? AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) ORDER BY created_at ASC');
    $stmt->execute([$postId, $userId, $withId, $withId, $userId]);
} else {
    $pdo->prepare('UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?')->execute([$withId, $userId]);
    $stmt = $pdo->prepare('SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC');
    $stmt->execute([$userId, $withId, $withId, $userId]);
}
$thread = $stmt->fetchAll();

$pageTitle = 'Conversation with ' . $partner['name'];
include __DIR__ . '/../includes/header.php';
?>
<div class="chat-shell">
    <div class="chat-topbar">
        <a class="chat-back" href="<?= BASE_URL ?>/messages/inbox.php">←</a>
        <div class="conversation-avatar large"><?= e(strtoupper(substr($partner['name'], 0, 1))) ?></div>
        <div class="chat-person"><h1><a class="profile-inline-link" href="<?= BASE_URL ?>/auth/profile.php?id=<?= $partner['id'] ?>"><?= e($partner['name']) ?></a></h1><p><?= e($partner['department'] ?: 'University member') ?></p></div>
    </div>
    <?php if ($post): ?>
    <a class="chat-item-context" href="<?= BASE_URL ?>/posts/view.php?id=<?= $post['id'] ?>">
        <?php if ($post['photo_url']): ?><img src="<?= e(post_photo_url($post['photo_url'])) ?>" alt=""><?php endif; ?>
        <div><span class="section-kicker">Conversation about</span><strong><?= e($post['title']) ?></strong><small><?= ucfirst(e($post['type'])) ?> · <?= e($post['location']) ?></small></div>
        <span class="context-arrow">→</span>
    </a>
    <?php endif; ?>
    <div class="message-thread" id="message-thread">
        <?php if (!$thread): ?><div class="chat-empty"><p>No messages yet. Send the first message about this item.</p></div><?php endif; ?>
        <?php foreach ($thread as $msg): ?>
            <div class="message-bubble <?= (int)$msg['sender_id'] === (int)$userId ? 'mine' : 'theirs' ?>">
                <p><?= nl2br(e($msg['content'])) ?></p>
                <span class="muted time"><?= e(date('g:i A', strtotime($msg['created_at']))) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <form method="post" action="" class="message-composer">
        <?= csrf_field() ?><input type="hidden" name="with" value="<?= $withId ?>"><?php if ($postId): ?><input type="hidden" name="post_id" value="<?= $postId ?>"><?php endif; ?>
        <textarea name="content" rows="1" placeholder="Write a message…" required></textarea>
        <button type="submit" class="btn btn-primary">Send</button>
    </form>
</div>
<script>const mt=document.getElementById('message-thread'); if(mt) mt.scrollTop=mt.scrollHeight;</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
