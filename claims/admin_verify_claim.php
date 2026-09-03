<?php
// Admin queue for verifying claims on high-value items (FR-4.4, FR-4.5).
// Approve/reject actions are submitted to claims/admin_respond_claim.php,
// a separate endpoint gated by the admin session only — the post owner's
// endpoint (claims/respond_claim.php) explicitly refuses high-value items.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_auth_check.php';

$stmt = $pdo->query(
    "SELECT cl.*, p.title, p.id AS post_id, u.name AS claimant_name
     FROM claims cl
     JOIN posts p ON p.id = cl.post_id
     JOIN users u ON u.id = cl.claimant_id
     WHERE p.is_high_value = 1 AND cl.status = 'pending'
     ORDER BY cl.created_at ASC"
);
$pendingClaims = $stmt->fetchAll();

$pageTitle = 'Verify High-Value Claims';
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-panel">
    <h1>High-Value Claim Verification</h1>
    <nav class="admin-nav">
        <a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a>
        <a href="<?= BASE_URL ?>/admin/manage_posts.php">Manage Posts</a>
        <a href="<?= BASE_URL ?>/admin/manage_users.php">Manage Users</a>
        <a href="<?= BASE_URL ?>/admin/manage_categories.php">Manage Categories</a>
        <a href="<?= BASE_URL ?>/claims/admin_verify_claim.php" class="active">Verify High-Value Claims</a>
    </nav>
    <p class="muted">These claims are on items flagged as high-value and require admin sign-off before release (FR-4.4/FR-4.5).</p>

    <?php if (!$pendingClaims): ?>
        <p class="muted">No high-value claims are pending verification.</p>
    <?php endif; ?>

    <?php foreach ($pendingClaims as $claim): ?>
        <div class="claim-card">
            <div class="claim-card-top">
                <strong><?= e($claim['claimant_name']) ?></strong> claims
                <a href="<?= BASE_URL ?>/posts/view.php?id=<?= $claim['post_id'] ?>"><?= e($claim['title']) ?></a>
                <span class="muted"><?= e(time_ago($claim['created_at'])) ?></span>
            </div>
            <p><?= nl2br(e($claim['proof_description'])) ?></p>
            <form method="post" action="<?= BASE_URL ?>/claims/admin_respond_claim.php" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="claim_id" value="<?= $claim['id'] ?>">
                <button type="submit" name="decision" value="approved" class="btn btn-primary btn-sm">Verify &amp; Approve</button>
                <button type="submit" name="decision" value="rejected" class="btn btn-danger btn-sm">Reject</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
