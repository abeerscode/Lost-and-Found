<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $userId = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($userId === (int)current_admin_id()) {
        flash_set('You cannot change your own administrator account status here.', 'error');
        redirect('/admin/users.php');
    }
    $stmt = $pdo->prepare('SELECT name, account_status FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $target = $stmt->fetch();
    $newStatus = match ($action) {
        'suspend' => 'suspended',
        'ban' => 'banned',
        'activate' => 'active',
        default => null,
    };
    if ($target && $newStatus) {
        $pdo->prepare('UPDATE users SET account_status = ? WHERE id = ?')->execute([$newStatus, $userId]);
        admin_log($pdo, 'account_status', 'user', $userId, 'Changed ' . $target['name'] . ' from ' . $target['account_status'] . ' to ' . $newStatus . '.');
        flash_set('User account updated.', 'success');
    }
    redirect('/admin/users.php');
}

$q = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'all';
$status = $_GET['status'] ?? 'all';
$params = [];
$where = ["role <> 'admin'"];
if ($q !== '') {
    $where[] = '(name LIKE ? OR email LIKE ? OR university_id LIKE ? OR department LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if (in_array($type, ['student','faculty','staff'], true)) {
    $where[] = 'person_type = ?';
    $params[] = $type;
}
if (in_array($status, ['active','suspended','banned'], true)) {
    $where[] = 'account_status = ?';
    $params[] = $status;
}
$sql = "SELECT u.*,
        (SELECT COUNT(*) FROM posts p WHERE p.user_id=u.id) AS report_count,
        (SELECT COUNT(*) FROM claims c WHERE c.claimant_id=u.id) AS claim_count
        FROM users u WHERE " . implode(' AND ', $where) . ' ORDER BY u.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Users';
$adminActive = 'users';
include __DIR__ . '/../includes/admin_header.php';
?>
<section class="admin-section-intro">
    <div><h2>University members</h2><p>Review member identity, account status, and system participation without exposing password data.</p></div>
</section>

<form class="admin-filter-bar" method="get">
    <label class="admin-search-field"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, email, ID or department"></label>
    <select name="type" onchange="this.form.submit()">
        <option value="all">All types</option>
        <?php foreach (['student'=>'Students','faculty'=>'Faculty','staff'=>'Staff'] as $value=>$label): ?><option value="<?= $value ?>" <?= $type===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?>
    </select>
    <select name="status" onchange="this.form.submit()">
        <option value="all">All statuses</option>
        <?php foreach (['active'=>'Active','suspended'=>'Suspended','banned'=>'Banned'] as $value=>$label): ?><option value="<?= $value ?>" <?= $status===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?>
    </select>
    <button class="btn admin-filter-submit" type="submit">Search</button>
</form>

<section class="admin-card admin-table-card">
    <div class="admin-card-header table-title">
        <div><h2><?= count($users) ?> members</h2><p>Student, faculty and staff accounts.</p></div>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-data-table">
            <thead><tr><th>Member</th><th>Type</th><th>University ID</th><th>Department</th><th>Activity</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <a class="admin-user-cell" href="<?= BASE_URL ?>/admin/user_view.php?id=<?= (int)$u['id'] ?>">
                            <span class="admin-table-avatar"><?php if ($u['profile_photo']): ?><img src="<?= e(profile_photo_url($u['profile_photo'])) ?>" alt=""><?php else: ?><?= e(strtoupper(substr(trim($u['name']),0,1))) ?><?php endif; ?></span>
                            <span><strong><?= e($u['name']) ?></strong><small><?= e($u['email']) ?></small></span>
                        </a>
                    </td>
                    <td><span class="admin-soft-pill"><?= e(person_type_label($u['person_type'])) ?></span></td>
                    <td><?= e($u['university_id'] ?: '—') ?></td>
                    <td><?= e($u['department'] ?: '—') ?></td>
                    <td><strong><?= (int)$u['report_count'] ?></strong> reports · <strong><?= (int)$u['claim_count'] ?></strong> claims</td>
                    <td><?= account_status_badge($u['account_status']) ?></td>
                    <td class="admin-row-actions"><a class="admin-icon-button" href="<?= BASE_URL ?>/admin/user_view.php?id=<?= (int)$u['id'] ?>" aria-label="View user"><svg viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$users): ?><tr><td colspan="7"><div class="admin-empty-state compact"><strong>No matching users</strong><p>Try clearing one of the filters.</p></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
