<?php
// Post owner approves/rejects a claim on their own post (FR-4.3).
// Gated by the public-site session only. High-value items are always
// refused here — they must go through claims/admin_respond_claim.php,
// which requires an active admin session (FR-4.4, FR-4.5).
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/posts/feed.php');
}
csrf_verify();

$claimId = (int)($_POST['claim_id'] ?? 0);
$decision = $_POST['decision'] ?? '';

if (!in_array($decision, ['approved', 'rejected'], true)) {
    flash_set('Invalid decision.', 'error');
    redirect('/posts/feed.php');
}

$stmt = $pdo->prepare(
    'SELECT cl.*, p.user_id AS owner_id, p.is_high_value, p.title, p.id AS post_id, p.status AS post_status
     FROM claims cl JOIN posts p ON p.id = cl.post_id WHERE cl.id = ?'
);
$stmt->execute([$claimId]);
$claim = $stmt->fetch();

if (!$claim) {
    flash_set('Claim not found.', 'error');
    redirect('/posts/feed.php');
}
if ((int)$claim['owner_id'] !== (int)current_user_id()) {
    http_response_code(403);
    die('You do not have permission to respond to this claim.');
}
if ($claim['is_high_value']) {
    flash_set('This item is high-value — only an administrator can approve or reject this claim via the admin panel.', 'error');
    redirect('/posts/view.php?id=' . $claim['post_id']);
}
if ($claim['status'] !== 'pending') {
    flash_set('This claim has already been resolved.', 'error');
    redirect('/posts/view.php?id=' . $claim['post_id']);
}

$pdo->beginTransaction();
$stmt = $pdo->prepare('UPDATE claims SET status = ? WHERE id = ?');
$stmt->execute([$decision, $claimId]);

if ($decision === 'approved') {
    // FR-2.5: automatically mark the post Resolved once a claim is approved.
    $stmt = $pdo->prepare('UPDATE posts SET status = "resolved" WHERE id = ?');
    $stmt->execute([$claim['post_id']]);
    log_status_change($pdo, $claim['post_id'], $claim['post_status'], 'resolved', current_user_id());

    // Auto-reject any other pending claims on the same post.
    $stmt = $pdo->prepare('UPDATE claims SET status = "rejected" WHERE post_id = ? AND id != ? AND status = "pending"');
    $stmt->execute([$claim['post_id'], $claimId]);
}
$pdo->commit();

create_notification(
    $pdo, $claim['claimant_id'], 'claim_' . $decision,
    'Your claim on "' . $claim['title'] . '" was ' . $decision . '.',
    BASE_URL . '/posts/view.php?id=' . $claim['post_id']
);

flash_set('Claim ' . $decision . '.', 'success');
redirect('/posts/view.php?id=' . $claim['post_id']);
