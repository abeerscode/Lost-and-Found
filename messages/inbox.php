<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$userId = current_user_id();

// Latest message for each partner + item context. Keeping post_id in the
// grouping lets the demo conversation show separate threads for separate items.
$stmt = $pdo->prepare(
    "SELECT m.*, u.name AS partner_name, u.id AS partner_id, p.title AS post_title, p.photo_url
     FROM messages m
     JOIN users u ON u.id = IF(m.sender_id = ?, m.receiver_id, m.sender_id)
     LEFT JOIN posts p ON p.id = m.post_id
     WHERE m.id IN (
        SELECT MAX(id) FROM messages
        WHERE sender_id = ? OR receiver_id = ?
        GROUP BY IF(sender_id = ?, receiver_id, sender_id), COALESCE(post_id, 0)
     )
     ORDER BY m.created_at DESC"
);
$stmt->execute([$userId, $userId, $userId, $userId]);
$conversations = $stmt->fetchAll();

$pageTitle = 'Inbox';
include __DIR__ . '/../includes/header.php';
?>
<div class="inbox-layout">
    <section class="inbox-list inbox-list-wide">
        <div class="panel-heading">
            <div><span class="section-kicker">Messages</span><h1>Inbox</h1></div>
            <span class="muted"><?= count($conversations) ?> conversation<?= count($conversations) === 1 ? '' : 's' ?></span>
        </div>
        <?php if (!$conversations): ?><div class="empty-state"><div class="empty-icon">✉</div><h2>No conversations yet</h2><p>Open an item and message its poster to start a conversation.</p></div><?php endif; ?>
        <div class="conversation-list">
        <?php foreach ($conversations as $conv): ?>
            <a class="conversation-row <?= ($conv['receiver_id'] == $userId && !$conv['is_read']) ? 'unread' : '' ?>"
               href="<?= BASE_URL ?>/messages/conversation.php?with=<?= $conv['partner_id'] ?><?= $conv['post_id'] ? '&post_id=' . $conv['post_id'] : '' ?>">
                <div class="conversation-avatar"><?= e(strtoupper(substr($conv['partner_name'], 0, 1))) ?></div>
                <div class="conversation-main">
                    <div class="conversation-line"><strong><?= e($conv['partner_name']) ?></strong><span class="muted time"><?= e(time_ago($conv['created_at'])) ?></span></div>
                    <?php if ($conv['post_title']): ?><div class="conversation-item">About: <?= e($conv['post_title']) ?></div><?php endif; ?>
                    <span class="conversation-preview"><?= e(mb_strimwidth($conv['content'], 0, 110, '…')) ?></span>
                </div>
                <?php if ($conv['receiver_id'] == $userId && !$conv['is_read']): ?><span class="unread-dot" title="Unread"></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
        </div>
    </section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
