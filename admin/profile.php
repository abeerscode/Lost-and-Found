<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_auth_check.php';
$adminId=(int)current_admin_id();
$stmt=$pdo->prepare('SELECT * FROM users WHERE id=? AND role="admin" LIMIT 1'); $stmt->execute([$adminId]); $admin=$stmt->fetch();
$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_verify(); $action=$_POST['action']??'profile';
    if($action==='profile'){
        $name=trim($_POST['name']??''); $phone=trim($_POST['phone']??''); $current=$_POST['current_password']??'';
        if($name==='') $errors[]='Name is required.';
        if(!$current || !password_verify($current,$admin['password_hash'])) $errors[]='Current password is required to save administrator profile changes.';
        $photo=$admin['profile_photo'];
        if(!$errors && !empty($_FILES['profile_photo']) && $_FILES['profile_photo']['error']!==UPLOAD_ERR_NO_FILE){ try{$photo=handle_photo_upload('profile_photo');}catch(RuntimeException $e){$errors[]=$e->getMessage();} }
        if(!$errors){ $pdo->prepare('UPDATE users SET name=?,phone=?,profile_photo=? WHERE id=?')->execute([$name,$phone,$photo,$adminId]); $_SESSION['admin_name']=$name; admin_log($pdo,'admin_profile','user',$adminId,'Updated administrator profile information.'); flash_set('Admin profile updated.','success'); redirect('/admin/profile.php'); }
    } elseif($action==='password'){
        $current=$_POST['current_password']??''; $new=$_POST['new_password']??''; $confirm=$_POST['confirm_password']??'';
        if(!password_verify($current,$admin['password_hash'])) $errors[]='Current password is incorrect.';
        if(strlen($new)<8) $errors[]='New password must be at least 8 characters.';
        if($new!==$confirm) $errors[]='New password confirmation does not match.';
        if(!$errors){ $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$adminId]); admin_log($pdo,'admin_password','user',$adminId,'Changed administrator account password.'); flash_set('Administrator password updated.','success'); redirect('/admin/profile.php'); }
    }
}
$pageTitle='Admin profile'; $adminActive='profile'; include __DIR__.'/../includes/admin_header.php';
?>
<section class="admin-profile-hero admin-card">
    <span class="admin-profile-avatar"><?php if($admin['profile_photo']): ?><img src="<?= e(profile_photo_url($admin['profile_photo'])) ?>" alt=""><?php else: ?>AD<?php endif; ?></span>
    <div><span class="admin-card-kicker">Administrator account</span><h2><?= e($admin['name']) ?></h2><p><?= e($admin['department'] ?: 'IT Administration') ?> · <?= e($admin['email']) ?></p></div>
    <a class="btn admin-logout-button" href="<?= BASE_URL ?>/admin/logout.php">Log out</a>
</section>
<?php if($errors): ?><div class="flash flash-error"><ul><?php foreach($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="admin-profile-grid">
<section class="admin-card"><div class="admin-card-header"><div><span class="admin-card-kicker">Account</span><h2>Profile information</h2></div></div><form method="post" enctype="multipart/form-data" class="admin-form"><?= csrf_field() ?><input type="hidden" name="action" value="profile"><label>Profile picture<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif"></label><label>Name<input type="text" name="name" value="<?= e($admin['name']) ?>" required></label><label>Email<input type="email" value="<?= e($admin['email']) ?>" disabled></label><label>Phone<input type="text" name="phone" value="<?= e($admin['phone'] ?? '') ?>"></label><label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label><button class="btn btn-primary" type="submit">Save profile</button></form></section>
<section class="admin-card"><div class="admin-card-header"><div><span class="admin-card-kicker">Security</span><h2>Change password</h2></div></div><p class="admin-form-copy">Admin passwords are never displayed. Confirm the existing password before setting a new one.</p><form method="post" class="admin-form"><?= csrf_field() ?><input type="hidden" name="action" value="password"><label>Current password<input type="password" name="current_password" required></label><label>New password <span class="field-hint">At least 8 characters</span><input type="password" name="new_password" minlength="8" required></label><label>Confirm new password<input type="password" name="confirm_password" minlength="8" required></label><button class="btn" type="submit">Update password</button></form></section>
</div>
<?php include __DIR__.'/../includes/admin_footer.php'; ?>
