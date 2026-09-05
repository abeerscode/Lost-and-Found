<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_auth_check.php';
$kind=$_GET['kind']??'all';
$events=admin_recent_events($pdo,120);
if(in_array($kind,['admin','post','claim','status','user'],true)) $events=array_values(array_filter($events,fn($e)=>$e['kind']===$kind));
$pageTitle='Activity'; $adminActive='activity'; include __DIR__.'/../includes/admin_header.php';
?>
<section class="admin-section-intro"><div><h2>System activity &amp; audit log</h2><p>A combined timeline of user activity, report changes, claims, registrations, and administrator actions.</p></div></section>
<nav class="admin-chip-nav">
    <?php foreach(['all'=>'All activity','admin'=>'Admin actions','post'=>'Reports','claim'=>'Claims','status'=>'Status changes','user'=>'Registrations'] as $v=>$l): ?><a class="<?= $kind===$v?'active':'' ?>" href="?kind=<?= $v ?>"><?= $l ?></a><?php endforeach; ?>
</nav>
<section class="admin-card admin-activity-card">
    <div class="admin-activity-list">
    <?php foreach($events as $event): $url=admin_event_url($event); ?>
        <?php if($url): ?><a class="admin-activity-row large" href="<?= e($url) ?>"><?php else: ?><div class="admin-activity-row large"><?php endif; ?>
            <span class="admin-activity-dot kind-<?= e($event['kind']) ?>"></span>
            <span class="admin-activity-content"><strong><?= e($event['title']) ?></strong><small><?= e($event['meta']) ?></small></span>
            <time><?= e(date('M j, Y · g:i A',strtotime($event['created_at']))) ?></time>
        <?php if($url): ?></a><?php else: ?></div><?php endif; ?>
    <?php endforeach; ?>
    <?php if(!$events): ?><div class="admin-empty-state"><strong>No activity</strong><p>No events match this filter yet.</p></div><?php endif; ?>
    </div>
</section>
<?php include __DIR__.'/../includes/admin_footer.php'; ?>
