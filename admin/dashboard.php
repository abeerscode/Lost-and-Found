<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_auth_check.php';

$stats = [
    'users' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role <> 'admin'")->fetchColumn(),
    'students' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role <> 'admin' AND person_type='student'")->fetchColumn(),
    'faculty' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role <> 'admin' AND person_type='faculty'")->fetchColumn(),
    'staff' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role <> 'admin' AND person_type='staff'")->fetchColumn(),
    'posts' => (int)$pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn(),
    'open' => (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE status='open'")->fetchColumn(),
    'claimed' => (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE status='claimed'")->fetchColumn(),
    'resolved' => (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE status='resolved'")->fetchColumn(),
    'pending_claims' => (int)$pdo->query("SELECT COUNT(*) FROM claims WHERE status='pending'")->fetchColumn(),
    'high_value_pending' => (int)$pdo->query(
        "SELECT COUNT(*) FROM claims cl JOIN posts p ON p.id=cl.post_id WHERE cl.status='pending' AND p.is_high_value=1"
    )->fetchColumn(),
];

$attentionClaims = $pdo->query(
    "SELECT cl.id, cl.created_at, cl.claimant_id, p.id AS post_id, p.title, p.is_high_value,
            u.name AS claimant_name, u.profile_photo
     FROM claims cl
     JOIN posts p ON p.id = cl.post_id
     JOIN users u ON u.id = cl.claimant_id
     WHERE cl.status='pending'
     ORDER BY p.is_high_value DESC, cl.created_at ASC
     LIMIT 5"
)->fetchAll();

$recentEvents = admin_recent_events($pdo, 7);
$pageTitle = 'Dashboard';
$adminActive = 'dashboard';
include __DIR__ . '/../includes/admin_header.php';
?>
<section class="admin-section-intro">
    <div>
        <h2>System overview</h2>
        <p>Monitor campus activity, verification work, and account health from one place.</p>
    </div>
</section>

<div class="admin-metric-grid">
    <article class="admin-metric-card">
        <span class="admin-metric-icon metric-users"><svg viewBox="0 0 24 24"><path d="M16 20v-1.5A3.5 3.5 0 0 0 12.5 15h-5A3.5 3.5 0 0 0 4 18.5V20M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/></svg></span>
        <strong><?= $stats['users'] ?></strong><span>Members</span>
        <small><?= $stats['students'] ?> students · <?= $stats['faculty'] ?> faculty · <?= $stats['staff'] ?> staff</small>
    </article>
    <article class="admin-metric-card">
        <span class="admin-metric-icon metric-posts"><svg viewBox="0 0 24 24"><path d="M5 3h11l3 3v15H5zM8 10h8M8 14h8"/></svg></span>
        <strong><?= $stats['posts'] ?></strong><span>Reports</span>
        <small><?= $stats['open'] ?> open · <?= $stats['claimed'] ?> claimed</small>
    </article>
    <article class="admin-metric-card">
        <span class="admin-metric-icon metric-resolved"><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg></span>
        <strong><?= $stats['resolved'] ?></strong><span>Resolved</span>
        <small><?= $stats['posts'] ? round($stats['resolved'] / $stats['posts'] * 100) : 0 ?>% resolution rate</small>
    </article>
    <article class="admin-metric-card<?= $stats['pending_claims'] ? ' attention' : '' ?>">
        <span class="admin-metric-icon metric-claims"><svg viewBox="0 0 24 24"><path d="M12 3 4 6v5c0 5.2 3.4 8.8 8 10 4.6-1.2 8-4.8 8-10V6zM9 12l2 2 4-5"/></svg></span>
        <strong><?= $stats['pending_claims'] ?></strong><span>Pending claims</span>
        <small><?= $stats['high_value_pending'] ?> require admin verification</small>
    </article>
</div>

<div class="admin-dashboard-grid">
    <section class="admin-card admin-attention-card">
        <div class="admin-card-header">
            <div><span class="admin-card-kicker">Requires attention</span><h2>Pending verification</h2></div>
            <a class="admin-text-link" href="<?= BASE_URL ?>/admin/claims.php">View all</a>
        </div>
        <?php if (!$attentionClaims): ?>
            <div class="admin-empty-state compact"><strong>Nothing waiting</strong><p>There are no pending claims right now.</p></div>
        <?php else: ?>
            <div class="admin-attention-list">
            <?php foreach ($attentionClaims as $claim): ?>
                <a class="admin-attention-item" href="<?= BASE_URL ?>/admin/claims.php?claim_id=<?= (int)$claim['id'] ?>">
                    <span class="admin-mini-avatar">
                        <?php if (!empty($claim['profile_photo'])): ?><img src="<?= e(profile_photo_url($claim['profile_photo'])) ?>" alt=""><?php else: ?><?= e(strtoupper(substr(trim($claim['claimant_name']), 0, 1))) ?><?php endif; ?>
                    </span>
                    <span class="admin-attention-copy">
                        <strong><?= e($claim['claimant_name']) ?></strong>
                        <span>Claim for <?= e($claim['title']) ?></span>
                        <small><?= e(time_ago($claim['created_at'])) ?><?= $claim['is_high_value'] ? ' · High-value' : '' ?></small>
                    </span>
                    <?= $claim['is_high_value'] ? '<span class="admin-priority-pill">Verify</span>' : '<span class="admin-soft-pill">Pending</span>' ?>
                </a>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin-card">
        <div class="admin-card-header">
            <div><span class="admin-card-kicker">Live system</span><h2>Recent activity</h2></div>
            <a class="admin-text-link" href="<?= BASE_URL ?>/admin/activity.php">View log</a>
        </div>
        <div class="admin-activity-list compact">
            <?php foreach ($recentEvents as $event): $url = admin_event_url($event); ?>
                <?php if ($url): ?><a class="admin-activity-row" href="<?= e($url) ?>"><?php else: ?><div class="admin-activity-row"><?php endif; ?>
                    <span class="admin-activity-dot kind-<?= e($event['kind']) ?>"></span>
                    <span><strong><?= e($event['title']) ?></strong><small><?= e($event['meta']) ?> · <?= e(time_ago($event['created_at'])) ?></small></span>
                <?php if ($url): ?></a><?php else: ?></div><?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
