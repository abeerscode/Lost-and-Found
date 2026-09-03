<?php
// FR-5.3: in-app notifications for comments, messages, and claim status updates.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$userId = current_user_id();

$pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')->execute([$userId]);

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

$pageTitle = 'Notifications';
include __DIR__ . '/../includes/header.php';
?>
<div class="notifications-list">
    <h1>Notifications</h1>
    <?php if (!$notifications): ?>
        <p class="muted">You have no notifications yet.</p>
    <?php endif; ?>
    <?php foreach ($notifications as $n): ?>
        <a class="notification-row" href="<?= e(app_link($n['link'] ?? '#')) ?>">
            <span><?= e($n['message']) ?></span>
            <span class="muted time"><?= e(time_ago($n['created_at'])) ?></span>
        </a>
    <?php endforeach; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
