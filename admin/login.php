<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
if(is_admin_logged_in()) redirect('/admin/dashboard.php');
$errors=[]; $email='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_verify(); $email=trim($_POST['email']??''); $password=$_POST['password']??'';
    $stmt=$pdo->prepare('SELECT * FROM users WHERE email=?'); $stmt->execute([$email]); $user=$stmt->fetch();
    if(!$user || !password_verify($password,$user['password_hash'])) $errors[]='Invalid email or password.';
    elseif($user['role']!=='admin') $errors[]='This account does not have administrator access.';
    elseif($user['account_status']!=='active') $errors[]='This administrator account is '.$user['account_status'].'.';
    if(!$errors){ session_regenerate_id(true); $_SESSION['admin_id']=$user['id']; $_SESSION['admin_name']=$user['name']; $_SESSION['admin_last_activity']=time(); flash_set('Welcome back, '.$user['name'].'.','success'); redirect('/admin/dashboard.php'); }
}
$pageTitle='Admin Login'; include __DIR__.'/../includes/admin_header.php';
?>
<div class="admin-login-card">
    <div class="admin-login-card-head"><span class="admin-brand-mark large">LF</span><span class="admin-card-kicker">Restricted access</span><h1>Administrator login</h1><p>Sign in with an account that has administrator privileges.</p></div>
    <?php if($errors): ?><div class="flash flash-error"><ul><?php foreach($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" class="admin-form"><?= csrf_field() ?><label>Admin email<input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username"></label><label>Password<span class="password-field"><input id="admin-password" type="password" name="password" required autocomplete="current-password"><button class="password-toggle" type="button" data-password-toggle="admin-password" aria-label="Show password"><svg class="eye-open" viewBox="0 0 24 24"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.7"/></svg><svg class="eye-closed" viewBox="0 0 24 24"><path d="m3 3 18 18M10.6 6.2A9.8 9.8 0 0 1 12 6c6 0 9.5 6 9.5 6a16 16 0 0 1-3.1 3.7M6.2 6.2C3.8 8 2.5 12 2.5 12s3.5 6 9.5 6c1.1 0 2.1-.2 3-.5"/></svg></button></span></label><button class="btn btn-primary admin-login-submit" type="submit">Enter admin panel</button></form>
    <p class="admin-login-note">Admin authentication uses a separate session from the public Lost &amp; Found account.</p>
</div>
<script src="<?= BASE_URL ?>/js/auth-forms.js?v=<?= @filemtime(__DIR__ . '/../js/auth-forms.js') ?: time() ?>"></script>
<?php include __DIR__.'/../includes/admin_footer.php'; ?>
