<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$viewerId = (int) current_user_id();
$profileId = max(1, (int)($_GET['id'] ?? $viewerId));
$isOwner = $profileId === $viewerId;
$editing = $isOwner && isset($_GET['edit']);
$errors = [];

$stmt = $pdo->prepare('SELECT id, name, university_id, email, password_hash, role, phone, department, batch, profile_photo, account_status, created_at FROM users WHERE id = ?');
$stmt->execute([$profileId]);
$user = $stmt->fetch();
if (!$user || $user['account_status'] !== 'active') {
    http_response_code(404);
    $pageTitle = 'Profile not found';
    include __DIR__ . '/../includes/header.php';
    echo '<div class="empty-state"><h1>Profile not available</h1><p>This university member could not be found.</p></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwner) {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $batch = trim($_POST['batch'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';

    if ($name === '') $errors[] = 'Name is required.';
    if ($currentPassword === '' || !password_verify($currentPassword, $user['password_hash'])) {
        $errors[] = 'Enter your current password to confirm these changes.';
    }

    $profilePhoto = $user['profile_photo'];
    if (!$errors && !empty($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        try {
            $profilePhoto = handle_photo_upload('profile_photo');
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare('UPDATE users SET name = ?, department = ?, batch = ?, phone = ?, profile_photo = ? WHERE id = ?');
        $stmt->execute([$name, $department ?: null, $batch ?: null, $phone ?: null, $profilePhoto ?: null, $viewerId]);
        $_SESSION['name'] = $name;
        flash_set('Profile updated successfully.', 'success');
        redirect('/auth/profile.php');
    }
    $user['name'] = $name;
    $user['department'] = $department;
    $user['batch'] = $batch;
    $user['phone'] = $phone;
}

$stmt = $pdo->prepare('SELECT p.*, c.name AS category_name FROM posts p JOIN categories c ON c.id = p.category_id WHERE p.user_id = ? ORDER BY p.created_at DESC');
$stmt->execute([$profileId]);
$posts = $stmt->fetchAll();
$lostCount = 0; $foundCount = 0; $resolvedCount = 0;
foreach ($posts as $p) {
    if ($p['type'] === 'lost') $lostCount++;
    if ($p['type'] === 'found') $foundCount++;
    if ($p['status'] === 'resolved') $resolvedCount++;
}

$initials = '';
foreach (preg_split('/\s+/', trim($user['name'])) as $part) { if ($part !== '') $initials .= strtoupper(substr($part,0,1)); }
$initials = substr($initials, 0, 2) ?: 'U';
$pageTitle = $isOwner ? 'My Profile' : $user['name'];
include __DIR__ . '/../includes/header.php';
?>
<div class="profile-page">
    <section class="profile-hero">
        <div class="profile-identity">
            <?php if ($user['profile_photo']): ?>
                <img class="profile-avatar" src="<?= e(profile_photo_url($user['profile_photo'])) ?>" alt="<?= e($user['name']) ?>">
            <?php else: ?>
                <div class="profile-avatar profile-avatar-fallback" aria-hidden="true"><?= e($initials) ?></div>
            <?php endif; ?>
            <div class="profile-title-block">
                <div class="profile-name-line">
                    <h1><?= e($user['name']) ?></h1>
                    <span class="verified-badge">✓ University member</span>
                </div>
                <p><?= e($user['department'] ?: 'Department not added') ?><?= $user['batch'] ? ' · Batch ' . e($user['batch']) : '' ?></p>
                <p class="muted">Member since <?= e(date('M Y', strtotime($user['created_at']))) ?></p>
            </div>
        </div>
        <div class="profile-actions">
            <?php if ($isOwner): ?>
                <?php if ($editing): ?><a class="btn" href="<?= BASE_URL ?>/auth/profile.php">Cancel</a><?php else: ?><a class="btn btn-primary" href="<?= BASE_URL ?>/auth/profile.php?edit=1">Update profile</a><?php endif; ?>
            <?php else: ?>
                <a class="btn btn-primary" href="<?= BASE_URL ?>/messages/conversation.php?with=<?= $user['id'] ?>">Message</a>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($editing): ?>
    <section class="profile-edit-card">
        <div class="section-heading"><div><span class="section-kicker">Account settings</span><h2>Update profile</h2></div><p>Public identity fields can be updated here. Your university ID remains locked.</p></div>
        <?php if ($errors): ?><div class="flash flash-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="profile-edit-form">
            <?= csrf_field() ?>
            <div class="profile-photo-field">
                <div><?php if ($user['profile_photo']): ?><img class="profile-avatar small" src="<?= e(profile_photo_url($user['profile_photo'])) ?>" alt=""><?php else: ?><div class="profile-avatar profile-avatar-fallback small"><?= e($initials) ?></div><?php endif; ?></div>
                <label>Profile picture<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif"><small>JPG, PNG, WEBP or GIF. Maximum 5 MB.</small></label>
            </div>
            <div class="profile-form-grid">
                <label>Full name<input type="text" name="name" value="<?= e($user['name']) ?>" required></label>
                <label>Department<input type="text" name="department" value="<?= e($user['department']) ?>" placeholder="Computer Science & Engineering"></label>
                <label>Batch<input type="text" name="batch" value="<?= e($user['batch']) ?>" placeholder="2022"></label>
                <label>Phone <span class="private-note">Private</span><input type="text" name="phone" value="<?= e($user['phone']) ?>" placeholder="Your contact number"></label>
                <label>University ID <span class="locked-note">Locked</span><input type="text" value="<?= e($user['university_id']) ?>" disabled></label>
                <label>Email <span class="private-note">Private</span><input type="email" value="<?= e($user['email']) ?>" disabled></label>
            </div>
            <div class="password-confirm-box">
                <div><strong>Confirm your identity</strong><p>Enter your current password before saving profile changes.</p></div>
                <label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label>
            </div>
            <button type="submit" class="btn btn-primary">Save changes</button>
        </form>
    </section>
    <?php endif; ?>

    <div class="profile-layout">
        <aside class="profile-about-card">
            <div class="section-heading compact"><div><span class="section-kicker">About</span><h2>University identity</h2></div></div>
            <dl class="profile-details">
                <div><dt>Department</dt><dd><?= e($user['department'] ?: 'Not added') ?></dd></div>
                <div><dt>Batch</dt><dd><?= e($user['batch'] ?: 'Not added') ?></dd></div>
                <div><dt>Student / Staff ID</dt><dd><?= e($isOwner ? ($user['university_id'] ?: 'Not added') : mask_university_id($user['university_id'])) ?></dd></div>
                <div><dt>Member since</dt><dd><?= e(date('F Y', strtotime($user['created_at']))) ?></dd></div>
                <?php if ($isOwner): ?>
                <div><dt>Email <span class="private-note">Private</span></dt><dd><?= e($user['email']) ?></dd></div>
                <div><dt>Phone <span class="private-note">Private</span></dt><dd><?= e($user['phone'] ?: 'Not added') ?></dd></div>
                <?php endif; ?>
            </dl>
            <?php if (!$isOwner): ?><p class="privacy-note">Contact details and the full university ID are hidden for privacy. Use the in-app message system to contact this member.</p><?php endif; ?>
        </aside>

        <section class="profile-activity">
            <div class="profile-stats">
                <div><strong><?= count($posts) ?></strong><span>Reports</span></div>
                <div><strong><?= $lostCount ?></strong><span>Lost</span></div>
                <div><strong><?= $foundCount ?></strong><span>Found</span></div>
                <div><strong><?= $resolvedCount ?></strong><span>Resolved</span></div>
            </div>
            <div class="profile-posts-heading"><div><span class="section-kicker"><?= $isOwner ? 'Your activity' : 'Public activity' ?></span><h2><?= $isOwner ? 'My reports' : e($user['name']) . '’s reports' ?></h2></div></div>
            <?php if (!$posts): ?>
                <div class="profile-empty"><strong>No reports yet</strong><p><?= $isOwner ? 'Items you report will appear here.' : 'This member has not posted any lost or found items yet.' ?></p></div>
            <?php else: ?>
                <div class="profile-post-grid">
                <?php foreach ($posts as $post): ?>
                    <a class="profile-post-card" href="<?= BASE_URL ?>/posts/view.php?id=<?= $post['id'] ?>">
                        <div class="profile-post-image" <?php if ($post['photo_url']): ?>style="background-image:url('<?= e(post_photo_url($post['photo_url'])) ?>')"<?php endif; ?>></div>
                        <div class="profile-post-body">
                            <div class="post-card-top"><span class="type-tag <?= $post['type']==='lost' ? 'type-lost' : '' ?>"><?= e(ucfirst($post['type'])) ?></span><?= status_badge($post['status']) ?></div>
                            <h3><?= e($post['title']) ?></h3>
                            <p><?= e($post['location']) ?> · <?= e(date('M j, Y', strtotime($post['item_datetime']))) ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
