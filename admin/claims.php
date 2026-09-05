<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $claimId = (int)($_POST['claim_id'] ?? 0);
    $decision = $_POST['decision'] ?? '';
    if (!in_array($decision, ['approved','rejected'], true)) {
        flash_set('Invalid claim decision.', 'error'); redirect('/admin/claims.php');
    }
    $stmt=$pdo->prepare("SELECT cl.*, p.title,p.is_high_value,p.status post_status,p.user_id owner_id FROM claims cl JOIN posts p ON p.id=cl.post_id WHERE cl.id=?");
    $stmt->execute([$claimId]); $claim=$stmt->fetch();
    if(!$claim){ flash_set('Claim not found.','error'); redirect('/admin/claims.php'); }
    if(!$claim['is_high_value']){ flash_set('Ordinary claims are resolved by the post owner. Admin approval is reserved for high-value items.','error'); redirect('/admin/claims.php'); }
    if($claim['status']!=='pending'){ flash_set('This claim has already been resolved.','error'); redirect('/admin/claims.php'); }

    $pdo->beginTransaction();
    $pdo->prepare('UPDATE claims SET status=?, verified_by_admin=? WHERE id=?')->execute([$decision,current_admin_id(),$claimId]);
    if($decision==='approved'){
        $pdo->prepare("UPDATE posts SET status='resolved' WHERE id=?")->execute([$claim['post_id']]);
        log_status_change($pdo,$claim['post_id'],$claim['post_status'],'resolved',current_admin_id());
        $pdo->prepare("UPDATE claims SET status='rejected' WHERE post_id=? AND id<>? AND status='pending'")->execute([$claim['post_id'],$claimId]);
    }
    $pdo->commit();

    create_notification($pdo,$claim['claimant_id'],'claim_'.$decision,'Your claim on “'.$claim['title'].'” was '.$decision.' by an administrator.','/posts/view.php?id='.$claim['post_id']);
    create_notification($pdo,$claim['owner_id'],'claim_review','An administrator '.$decision.' a claim on “'.$claim['title'].'”.','/posts/view.php?id='.$claim['post_id']);
    admin_log($pdo,'claim_'.$decision,'claim',$claimId,ucfirst($decision).' high-value claim #'.$claimId.' for “'.$claim['title'].'”.');
    flash_set('Claim '.$decision.'.','success');
    redirect('/admin/claims.php');
}

$status=$_GET['status']??'pending';
$high=$_GET['high_value']??'all';
$postFilter=(int)($_GET['post_id']??0);
$params=[]; $where=['1=1'];
if(in_array($status,['pending','approved','rejected'],true)){ $where[]='cl.status=?'; $params[]=$status; }
if($high==='yes') $where[]='p.is_high_value=1';
elseif($high==='no') $where[]='p.is_high_value=0';
if($postFilter>0){ $where[]='p.id=?'; $params[]=$postFilter; }
$sql="SELECT cl.*, p.title,p.type,p.status post_status,p.is_high_value,p.photo_url,p.user_id owner_id,
             claimant.name claimant_name, claimant.profile_photo claimant_photo, claimant.person_type claimant_type,
             owner.name owner_name, owner.profile_photo owner_photo
      FROM claims cl JOIN posts p ON p.id=cl.post_id
      JOIN users claimant ON claimant.id=cl.claimant_id
      JOIN users owner ON owner.id=p.user_id
      WHERE ".implode(' AND ',$where)." ORDER BY p.is_high_value DESC, cl.created_at DESC";
$stmt=$pdo->prepare($sql); $stmt->execute($params); $claims=$stmt->fetchAll();
$counts=[
    'pending'=>(int)$pdo->query("SELECT COUNT(*) FROM claims WHERE status='pending'")->fetchColumn(),
    'high'=>(int)$pdo->query("SELECT COUNT(*) FROM claims cl JOIN posts p ON p.id=cl.post_id WHERE cl.status='pending' AND p.is_high_value=1")->fetchColumn(),
    'approved'=>(int)$pdo->query("SELECT COUNT(*) FROM claims WHERE status='approved'")->fetchColumn(),
    'rejected'=>(int)$pdo->query("SELECT COUNT(*) FROM claims WHERE status='rejected'")->fetchColumn(),
];
$pageTitle='Claims'; $adminActive='claims'; include __DIR__.'/../includes/admin_header.php';
?>
<section class="admin-section-intro"><div><h2>Ownership verification</h2><p>High-value reports require administrator sign-off. Ordinary claims stay visible here but remain under the post owner's control.</p></div></section>
<div class="admin-claim-stats">
    <a href="?status=pending" class="<?= $status==='pending'?'active':'' ?>"><strong><?= $counts['pending'] ?></strong><span>Pending</span></a>
    <a href="?status=pending&high_value=yes"><strong><?= $counts['high'] ?></strong><span>High-value verification</span></a>
    <a href="?status=approved"><strong><?= $counts['approved'] ?></strong><span>Approved</span></a>
    <a href="?status=rejected"><strong><?= $counts['rejected'] ?></strong><span>Rejected</span></a>
</div>
<form class="admin-filter-bar compact" method="get">
    <select name="status" onchange="this.form.submit()"><option value="all" <?= $status==='all'?'selected':'' ?>>All statuses</option><?php foreach(['pending','approved','rejected'] as $s): ?><option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select>
    <select name="high_value" onchange="this.form.submit()"><option value="all">All claim types</option><option value="yes" <?= $high==='yes'?'selected':'' ?>>High-value only</option><option value="no" <?= $high==='no'?'selected':'' ?>>Ordinary only</option></select>
    <?php if($postFilter): ?><input type="hidden" name="post_id" value="<?= $postFilter ?>"><a class="btn" href="<?= BASE_URL ?>/admin/claims.php">Clear post filter</a><?php endif; ?>
</form>
<div class="admin-claims-list">
<?php foreach($claims as $cl): ?>
    <article class="admin-card admin-claim-card" id="claim-<?= (int)$cl['id'] ?>">
        <div class="admin-claim-item-head">
            <span class="admin-claim-thumb"><?php if($cl['photo_url']): ?><img src="<?= e(post_photo_url($cl['photo_url'])) ?>" alt=""><?php endif; ?></span>
            <div class="admin-claim-title"><div><?= $cl['is_high_value']?'<span class="admin-priority-pill">High-value</span>':'<span class="admin-soft-pill">Owner review</span>' ?> <?= status_badge($cl['status']) ?></div><h2><a href="<?= BASE_URL ?>/admin/post_view.php?id=<?= (int)$cl['post_id'] ?>"><?= e($cl['title']) ?></a></h2><p><?= e(ucfirst($cl['type'])) ?> report · <?= e(time_ago($cl['created_at'])) ?></p></div>
        </div>
        <div class="admin-claim-people">
            <a href="<?= BASE_URL ?>/admin/user_view.php?id=<?= (int)$cl['claimant_id'] ?>" class="admin-claim-person"><span class="admin-table-avatar"><?php if($cl['claimant_photo']): ?><img src="<?= e(profile_photo_url($cl['claimant_photo'])) ?>" alt=""><?php else: ?><?= e(strtoupper(substr(trim($cl['claimant_name']),0,1))) ?><?php endif; ?></span><span><small>Claimant</small><strong><?= e($cl['claimant_name']) ?></strong><span><?= e(person_type_label($cl['claimant_type'])) ?></span></span></a>
            <span class="admin-claim-arrow">→</span>
            <a href="<?= BASE_URL ?>/admin/user_view.php?id=<?= (int)$cl['owner_id'] ?>" class="admin-claim-person"><span class="admin-table-avatar"><?php if($cl['owner_photo']): ?><img src="<?= e(profile_photo_url($cl['owner_photo'])) ?>" alt=""><?php else: ?><?= e(strtoupper(substr(trim($cl['owner_name']),0,1))) ?><?php endif; ?></span><span><small>Report owner</small><strong><?= e($cl['owner_name']) ?></strong></span></a>
        </div>
        <div class="admin-proof-box"><span>Claim proof</span><p><?= nl2br(e($cl['proof_description'])) ?></p></div>
        <?php if($cl['status']==='pending'): ?>
            <?php if($cl['is_high_value']): ?>
                <form class="admin-claim-actions" method="post"><?= csrf_field() ?><input type="hidden" name="claim_id" value="<?= (int)$cl['id'] ?>"><button class="btn btn-primary" name="decision" value="approved">Verify &amp; approve</button><button class="btn btn-danger" name="decision" value="rejected">Reject</button></form>
            <?php else: ?><p class="admin-claim-owner-note">This ordinary claim is waiting for the report owner. Admin can inspect it but does not override the owner workflow.</p><?php endif; ?>
        <?php endif; ?>
    </article>
<?php endforeach; ?>
<?php if(!$claims): ?><div class="admin-card admin-empty-state"><strong>No claims match these filters</strong><p>There is nothing to review in this view.</p></div><?php endif; ?>
</div>
<?php include __DIR__.'/../includes/admin_footer.php'; ?>
