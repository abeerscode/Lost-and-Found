<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_auth_check.php';

$postId=(int)($_GET['id']??0);
$stmt=$pdo->prepare("SELECT p.*, u.name owner_name,u.profile_photo owner_profile_photo,u.person_type,u.email owner_email,c.name category_name FROM posts p JOIN users u ON u.id=p.user_id JOIN categories c ON c.id=p.category_id WHERE p.id=?");
$stmt->execute([$postId]); $post=$stmt->fetch();
if(!$post){ http_response_code(404); $pageTitle='Report not found'; include __DIR__.'/../includes/admin_header.php'; echo '<div class="admin-empty-state"><strong>Report not found</strong><p>This post may have been removed.</p></div>'; include __DIR__.'/../includes/admin_footer.php'; exit; }

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_verify(); $action=$_POST['action']??'';
    if($action==='status'){
        $new=$_POST['status']??'';
        if(in_array($new,['open','claimed','resolved'],true) && $new!==$post['status']){
            $old=$post['status']; $pdo->prepare('UPDATE posts SET status=? WHERE id=?')->execute([$new,$postId]);
            log_status_change($pdo,$postId,$old,$new,current_admin_id());
            admin_log($pdo,'post_status','post',$postId,'Changed “'.$post['title'].'” from '.$old.' to '.$new.'.');
            create_notification($pdo,$post['user_id'],'status','An administrator changed the status of “'.$post['title'].'” to '.$new.'.','/posts/view.php?id='.$postId);
            flash_set('Report status updated.','success');
        }
    } elseif($action==='high_value'){
        $new=!empty($_POST['is_high_value'])?1:0;
        $pdo->prepare('UPDATE posts SET is_high_value=? WHERE id=?')->execute([$new,$postId]);
        admin_log($pdo,'high_value_flag','post',$postId,($new?'Marked':'Unmarked').' “'.$post['title'].'” as high-value.');
        flash_set('High-value flag updated.','success');
    } elseif($action==='delete'){
        admin_log($pdo,'delete_post','post',$postId,'Removed report “'.$post['title'].'” posted by '.$post['owner_name'].'.');
        $photo=$post['photo_url'];
        if($photo && !preg_match('#^https?://#i',$photo)){
            $relative=ltrim($photo,'/'); if(str_starts_with($relative,'uploads/'))$relative=substr($relative,8);
            if(is_file(UPLOAD_DIR.$relative)) @unlink(UPLOAD_DIR.$relative);
        }
        $pdo->prepare('DELETE FROM posts WHERE id=?')->execute([$postId]);
        flash_set('Report removed.','success'); redirect('/admin/posts.php');
    }
    redirect('/admin/post_view.php?id='.$postId);
}

$stmt=$pdo->prepare("SELECT cm.*,u.name author_name,u.profile_photo FROM comments cm JOIN users u ON u.id=cm.user_id WHERE cm.post_id=? ORDER BY cm.created_at DESC LIMIT 12"); $stmt->execute([$postId]); $comments=$stmt->fetchAll();
$stmt=$pdo->prepare("SELECT cl.*,u.name claimant_name,u.profile_photo,p.is_high_value FROM claims cl JOIN users u ON u.id=cl.claimant_id JOIN posts p ON p.id=cl.post_id WHERE cl.post_id=? ORDER BY cl.created_at DESC"); $stmt->execute([$postId]); $claims=$stmt->fetchAll();
$pageTitle='Report details'; $adminActive='posts'; include __DIR__.'/../includes/admin_header.php';
?>
<a class="admin-back-link" href="<?= BASE_URL ?>/admin/posts.php">← Back to posts</a>
<div class="admin-post-detail-grid">
    <section class="admin-card admin-post-main-card">
        <div class="admin-post-detail-head">
            <div><span class="type-tag <?= $post['type']==='lost'?'type-lost':'' ?>"><?= e(ucfirst($post['type'])) ?></span> <?= status_badge($post['status']) ?> <?= $post['is_high_value']?'<span class="badge badge-highvalue">High-value</span>':'' ?><h2><?= e($post['title']) ?></h2><p><?= e($post['category_name']) ?> · <?= e($post['location']) ?></p></div>
            <span class="admin-post-date"><?= e(date('M j, Y g:i A',strtotime($post['item_datetime']))) ?></span>
        </div>
        <?php if($post['photo_url']): ?><img class="admin-post-detail-image" src="<?= e(post_photo_url($post['photo_url'])) ?>" alt="<?= e($post['title']) ?>"><?php endif; ?>
        <div class="admin-post-description"><h3>Description</h3><p><?= nl2br(e($post['description'])) ?></p></div>
        <div class="admin-owner-box">
            <span class="admin-table-avatar large"><?php if($post['owner_profile_photo']): ?><img src="<?= e(profile_photo_url($post['owner_profile_photo'])) ?>" alt=""><?php else: ?><?= e(strtoupper(substr(trim($post['owner_name']),0,1))) ?><?php endif; ?></span>
            <span><small>Posted by</small><a href="<?= BASE_URL ?>/admin/user_view.php?id=<?= (int)$post['user_id'] ?>"><strong><?= e($post['owner_name']) ?></strong></a><small><?= e(person_type_label($post['person_type'])) ?> · <?= e($post['owner_email']) ?></small></span>
        </div>
    </section>
    <aside class="admin-card admin-moderation-card">
        <div class="admin-card-header"><div><span class="admin-card-kicker">Moderation</span><h2>Report controls</h2></div></div>
        <form method="post" class="admin-moderation-form"><?= csrf_field() ?><input type="hidden" name="action" value="status"><label>Status<select name="status"><?php foreach(['open','claimed','resolved'] as $s): ?><option value="<?= $s ?>" <?= $post['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></label><button class="btn btn-primary" type="submit">Update status</button></form>
        <form method="post" class="admin-moderation-form admin-checkbox-form"><?= csrf_field() ?><input type="hidden" name="action" value="high_value"><label class="checkbox-label"><input type="checkbox" name="is_high_value" value="1" <?= $post['is_high_value']?'checked':'' ?>> Require admin verification for claims</label><button class="btn" type="submit">Save verification flag</button></form>
        <div class="admin-danger-zone"><strong>Remove report</strong><p>Use only for spam, abuse, duplicates, or content that should not remain in the system.</p><form method="post" onsubmit="return confirm('Permanently remove this report?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-danger" type="submit">Remove report</button></form></div>
    </aside>
</div>

<div class="admin-post-support-grid">
<section class="admin-card"><div class="admin-card-header"><div><span class="admin-card-kicker">Discussion</span><h2>Comments (<?= count($comments) ?> shown)</h2></div></div><div class="admin-comment-list"><?php foreach($comments as $c): ?><div class="admin-comment-row"><span class="admin-mini-avatar"><?php if($c['profile_photo']): ?><img src="<?= e(profile_photo_url($c['profile_photo'])) ?>" alt=""><?php else: ?><?= e(strtoupper(substr(trim($c['author_name']),0,1))) ?><?php endif; ?></span><span><strong><?= e($c['author_name']) ?></strong><p><?= e($c['message']) ?></p><small><?= e(time_ago($c['created_at'])) ?></small></span></div><?php endforeach; ?><?php if(!$comments): ?><div class="admin-empty-state compact"><strong>No comments</strong></div><?php endif; ?></div></section>
<section class="admin-card"><div class="admin-card-header"><div><span class="admin-card-kicker">Ownership</span><h2>Claims (<?= count($claims) ?>)</h2></div><?php if($claims): ?><a class="admin-text-link" href="<?= BASE_URL ?>/admin/claims.php?post_id=<?= $postId ?>">Open queue</a><?php endif; ?></div><div class="admin-claim-mini-list"><?php foreach($claims as $cl): ?><div class="admin-claim-mini-row"><span class="admin-mini-avatar"><?php if($cl['profile_photo']): ?><img src="<?= e(profile_photo_url($cl['profile_photo'])) ?>" alt=""><?php else: ?><?= e(strtoupper(substr(trim($cl['claimant_name']),0,1))) ?><?php endif; ?></span><span><strong><?= e($cl['claimant_name']) ?></strong><small><?= e(time_ago($cl['created_at'])) ?></small></span><?= status_badge($cl['status']) ?></div><?php endforeach; ?><?php if(!$claims): ?><div class="admin-empty-state compact"><strong>No claims</strong><p>No one has claimed this item.</p></div><?php endif; ?></div></section>
</div>
<?php include __DIR__.'/../includes/admin_footer.php'; ?>
