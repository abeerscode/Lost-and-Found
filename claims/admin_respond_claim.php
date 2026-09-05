<?php
// Admin-only endpoint for approving/rejecting claims on high-value items
// (FR-4.4, FR-4.5). Gated exclusively by the admin session — completely
// separate from claims/respond_claim.php, which handles ordinary
// (non-high-value) claims under the post owner's public-site session.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/claims.php');
}
csrf_verify();

$claimId = (int)($_POST['claim_id'] ?? 0);
$decision = $_POST['decision'] ?? '';

if (!in_array($decision, ['approved', 'rejected'], true)) {
    flash_set('Invalid decision.', 'error');
    redirect('/admin/claims.php');
}

$stmt = $pdo->prepare(
    'SELECT cl.*, p.is_high_value, p.title, p.id AS post_id, p.status AS post_status
     FROM claims cl JOIN posts p ON p.id = cl.post_id WHERE cl.id = ?'
);
$stmt->execute([$claimId]);
$claim = $stmt->fetch();

if (!$claim) {
    flash_set('Claim not found.', 'error');
    redirect('/admin/claims.php');
}
if (!$claim['is_high_value']) {
    flash_set('Only high-value claims are handled here; ordinary claims are resolved by the post owner.', 'error');
    redirect('/admin/claims.php');
}
if ($claim['status'] !== 'pending') {
    flash_set('This claim has already been resolved.', 'error');
    redirect('/admin/claims.php');
}

$pdo->beginTransaction();
$stmt = $pdo->prepare('UPDATE claims SET status = ?, verified_by_admin = ? WHERE id = ?');
$stmt->execute([$decision, current_admin_id(), $claimId]);

if ($decision === 'approved') {
    // FR-2.5: automatically mark the post Resolved once a claim is approved.
    $stmt = $pdo->prepare('UPDATE posts SET status = "resolved" WHERE id = ?');
    $stmt->execute([$claim['post_id']]);
    log_status_change($pdo, $claim['post_id'], $claim['post_status'], 'resolved', current_admin_id());

    // Auto-reject any other pending claims on the same post.
    $stmt = $pdo->prepare('UPDATE claims SET status = "rejected" WHERE post_id = ? AND id != ? AND status = "pending"');
    $stmt->execute([$claim['post_id'], $claimId]);
}
$pdo->commit();

create_notification(
    $pdo, $claim['claimant_id'], 'claim_' . $decision,
    'Your claim on "' . $claim['title'] . '" was ' . $decision . ' by an administrator.',
    BASE_URL . '/posts/view.php?id=' . $claim['post_id']
);

admin_log($pdo, 'claim_' . $decision, 'claim', $claimId, ucfirst($decision) . ' high-value claim #' . $claimId . ' for “' . $claim['title'] . '”.');

flash_set('Claim ' . $decision . '.', 'success');
redirect('/admin/claims.php');
