<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_auth_check.php';

$q = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'all';
$status = $_GET['status'] ?? 'all';
$categoryId = (int)($_GET['category_id'] ?? 0);
$personType = $_GET['person_type'] ?? 'all';
$params = [];
$where = ['1=1'];
if ($q !== '') {
    $where[] = '(p.title LIKE ? OR p.description LIKE ? OR p.location LIKE ? OR u.name LIKE ?)';
    $like = '%' . $q . '%'; array_push($params,$like,$like,$like,$like);
}
if (in_array($type,['lost','found'],true)) { $where[]='p.type=?'; $params[]=$type; }
if (in_array($status,['open','claimed','resolved'],true)) { $where[]='p.status=?'; $params[]=$status; }
if ($categoryId > 0) { $where[]='p.category_id=?'; $params[]=$categoryId; }
if (in_array($personType,['student','faculty','staff'],true)) { $where[]='u.person_type=?'; $params[]=$personType; }

$sql = "SELECT p.*, u.name owner_name, u.profile_photo owner_profile_photo, u.person_type, c.name category_name,
        (SELECT COUNT(*) FROM comments cm WHERE cm.post_id=p.id) comment_count,
        (SELECT COUNT(*) FROM claims cl WHERE cl.post_id=p.id) claim_count
        FROM posts p JOIN users u ON u.id=p.user_id JOIN categories c ON c.id=p.category_id
        WHERE ".implode(' AND ',$where).' ORDER BY p.created_at DESC';
$stmt=$pdo->prepare($sql); $stmt->execute($params); $posts=$stmt->fetchAll();
$categories=$pdo->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();

$pageTitle='Posts'; $adminActive='posts'; include __DIR__.'/../includes/admin_header.php';
?>
<section class="admin-section-intro">
    <div><h2>All reports</h2><p>Search and moderate lost/found reports while preserving the original user-submitted content.</p></div>
    <a class="btn" href="<?= BASE_URL ?>/admin/manage_categories.php">Manage categories</a>
</section>
<form class="admin-filter-bar admin-filter-bar-wide" method="get">
    <label class="admin-search-field"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search title, description, location or owner"></label>
    <select name="type" onchange="this.form.submit()"><option value="all">All types</option><option value="lost" <?= $type==='lost'?'selected':'' ?>>Lost</option><option value="found" <?= $type==='found'?'selected':'' ?>>Found</option></select>
    <select name="status" onchange="this.form.submit()"><option value="all">All statuses</option><?php foreach(['open','claimed','resolved'] as $s): ?><option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select>
    <select name="category_id" onchange="this.form.submit()"><option value="0">All categories</option><?php foreach($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $categoryId===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
    <select name="person_type" onchange="this.form.submit()"><option value="all">All people</option><?php foreach(['student'=>'Students','faculty'=>'Faculty','staff'=>'Staff'] as $v=>$l): ?><option value="<?= $v ?>" <?= $personType===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select>
    <button class="btn admin-filter-submit" type="submit">Search</button>
</form>
<section class="admin-card admin-table-card">
    <div class="admin-card-header table-title"><div><h2><?= count($posts) ?> reports</h2><p>Click a report to inspect its details, comments and claims.</p></div></div>
    <div class="admin-table-wrap">
        <table class="admin-data-table admin-posts-table">
            <thead><tr><th>Report</th><th>Owner</th><th>Category</th><th>Type</th><th>Status</th><th>Activity</th><th></th></tr></thead>
            <tbody>
            <?php foreach($posts as $p): ?>
                <tr>
                    <td><a class="admin-report-table-cell" href="<?= BASE_URL ?>/admin/post_view.php?id=<?= (int)$p['id'] ?>"><span class="admin-table-thumb"><?php if($p['photo_url']): ?><img src="<?= e(post_photo_url($p['photo_url'])) ?>" alt=""><?php endif; ?></span><span><strong><?= e($p['title']) ?></strong><small><?= e($p['location']) ?> · <?= e(time_ago($p['created_at'])) ?></small></span></a></td>
                    <td><a class="admin-owner-inline" href="<?= BASE_URL ?>/admin/user_view.php?id=<?= (int)$p['user_id'] ?>"><span class="admin-mini-avatar"><?php if($p['owner_profile_photo']): ?><img src="<?= e(profile_photo_url($p['owner_profile_photo'])) ?>" alt=""><?php else: ?><?= e(strtoupper(substr(trim($p['owner_name']),0,1))) ?><?php endif; ?></span><span><?= e($p['owner_name']) ?><small><?= e(person_type_label($p['person_type'])) ?></small></span></a></td>
                    <td><?= e($p['category_name']) ?></td>
                    <td><span class="type-tag <?= $p['type']==='lost'?'type-lost':'' ?>"><?= e(ucfirst($p['type'])) ?></span></td>
                    <td><?= status_badge($p['status']) ?><?= $p['is_high_value']?' <span class="badge badge-highvalue">HV</span>':'' ?></td>
                    <td><?= (int)$p['comment_count'] ?> comments · <?= (int)$p['claim_count'] ?> claims</td>
                    <td><a class="admin-icon-button" href="<?= BASE_URL ?>/admin/post_view.php?id=<?= (int)$p['id'] ?>"><svg viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if(!$posts): ?><tr><td colspan="7"><div class="admin-empty-state compact"><strong>No matching reports</strong><p>Try clearing one of the filters.</p></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include __DIR__.'/../includes/admin_footer.php'; ?>
