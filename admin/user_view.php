<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_auth_check.php';

$userId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM users WHERE id=? AND role<>"admin" LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) { http_response_code(404); $pageTitle='User not found'; include __DIR__.'/../includes/admin_header.php'; echo '<div class="admin-empty-state"><strong>User not found</strong><p>This account may have been removed.</p></div>'; include __DIR__.'/../includes/admin_footer.php'; exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $newStatus = match ($action) { 'suspend'=>'suspended','ban'=>'banned','activate'=>'active',default=>null };
    if ($newStatus) {
        $old = $user['account_status'];
        $pdo->prepare('UPDATE users SET account_status=? WHERE id=?')->execute([$newStatus,$userId]);
        admin_log($pdo,'account_status','user',$userId,'Changed '.$user['name'].' from '.$old.' to '.$newStatus.'.');
        flash_set('Account status updated.','success');
        redirect('/admin/user_view.php?id='.$userId);
    }
}

$countStmt = $pdo->prepare("SELECT
    (SELECT COUNT(*) FROM posts WHERE user_id=?) reports,
    (SELECT COUNT(*) FROM comments WHERE user_id=?) comments,
    (SELECT COUNT(*) FROM claims WHERE claimant_id=?) claims,
    (SELECT COUNT(DISTINCT CASE WHEN sender_id=? THEN receiver_id ELSE sender_id END) FROM messages WHERE sender_id=? OR receiver_id=?) conversations");
$countStmt->execute([$userId,$userId,$userId,$userId,$userId,$userId]);
$counts = $countStmt->fetch();

$stmt = $pdo->prepare('SELECT p.*, c.name category_name FROM posts p JOIN categories c ON c.id=p.category_id WHERE p.user_id=? ORDER BY p.created_at DESC LIMIT 8');
$stmt->execute([$userId]);
$reports = $stmt->fetchAll();

$pageTitle = 'User details';
$adminActive = 'users';
include __DIR__ . '/../includes/admin_header.php';
?>
<a class="admin-back-link" href="<?= BASE_URL ?>/admin/users.php">← Back to users</a>
<section class="admin-user-hero admin-card">
    <div class="admin-user-hero-main">
        <span class="admin-large-avatar"><?php if ($user['profile_photo']): ?><img src="<?= e(profile_photo_url($user['profile_photo'])) ?>" alt=""><?php else: ?><?= e(strtoupper(substr(trim($user['name']),0,1))) ?><?php endif; ?></span>
        <div><div class="admin-user-name-row"><h2><?= e($user['name']) ?></h2><?= account_status_badge($user['account_status']) ?></div><p><?= e($user['department'] ?: 'No department') ?> · <?= e(person_type_label($user['person_type'])) ?></p><small>Member since <?= e(date('M Y', strtotime($user['created_at']))) ?></small></div>
    </div>
    <div class="admin-user-hero-actions">
        <?php if ($user['account_status'] !== 'active'): ?><form method="post"><?= csrf_field() ?><button class="btn btn-primary" name="action" value="activate">Reactivate</button></form><?php endif; ?>
        <?php if ($user['account_status'] !== 'suspended'): ?><form method="post"><?= csrf_field() ?><button class="btn" name="action" value="suspend">Suspend</button></form><?php endif; ?>
        <?php if ($user['account_status'] !== 'banned'): ?><form method="post" onsubmit="return confirm('Ban this account?');"><?= csrf_field() ?><button class="btn btn-danger" name="action" value="ban">Ban</button></form><?php endif; ?>
    </div>
</section>

<div class="admin-user-detail-grid">
    <section class="admin-card">
        <div class="admin-card-header"><div><span class="admin-card-kicker">Identity</span><h2>University account</h2></div></div>
        <dl class="admin-detail-list">
            <div><dt>Person type</dt><dd><?= e(person_type_label($user['person_type'])) ?></dd></div>
            <div><dt>University ID</dt><dd><?= e($user['university_id'] ?: '—') ?></dd></div>
            <div><dt>Email</dt><dd><?= e($user['email']) ?></dd></div>
            <div><dt>Phone</dt><dd><?= e($user['phone'] ?: '—') ?></dd></div>
            <div><dt>Department</dt><dd><?= e($user['department'] ?: '—') ?></dd></div>
            <?php if ($user['person_type']==='student'): ?><div><dt>Batch</dt><dd><?= e($user['batch'] ?: '—') ?></dd></div><?php endif; ?>
        </dl>
        <p class="admin-privacy-note">Passwords and password hashes are intentionally never displayed in the admin panel.</p>
    </section>
    <section class="admin-card">
        <div class="admin-card-header"><div><span class="admin-card-kicker">Participation</span><h2>System activity</h2></div></div>
        <div class="admin-user-stat-grid">
            <div><strong><?= (int)$counts['reports'] ?></strong><span>Reports</span></div>
            <div><strong><?= (int)$counts['comments'] ?></strong><span>Comments</span></div>
            <div><strong><?= (int)$counts['conversations'] ?></strong><span>Conversations</span></div>
            <div><strong><?= (int)$counts['claims'] ?></strong><span>Claims</span></div>
        </div>
    </section>
</div>

<section class="admin-card admin-user-reports">
    <div class="admin-card-header"><div><span class="admin-card-kicker">Recent reports</span><h2><?= e($user['name']) ?>'s posts</h2></div></div>
    <div class="admin-report-list">
        <?php foreach ($reports as $p): ?>
        <a class="admin-report-row" href="<?= BASE_URL ?>/admin/post_view.php?id=<?= (int)$p['id'] ?>">
            <span class="admin-report-thumb"><?php if ($p['photo_url']): ?><img src="<?= e(post_photo_url($p['photo_url'])) ?>" alt=""><?php endif; ?></span>
            <span class="admin-report-copy"><strong><?= e($p['title']) ?></strong><small><?= e($p['category_name']) ?> · <?= e($p['location']) ?></small></span>
            <span class="type-tag <?= $p['type']==='lost'?'type-lost':'' ?>"><?= e(ucfirst($p['type'])) ?></span>
            <?= status_badge($p['status']) ?>
        </a>
        <?php endforeach; ?>
        <?php if (!$reports): ?><div class="admin-empty-state compact"><strong>No reports</strong><p>This user has not posted any items.</p></div><?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
