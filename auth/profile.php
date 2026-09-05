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

$stmt = $pdo->prepare('SELECT id, name, university_id, email, password_hash, role, person_type, phone, department, batch, profile_photo, account_status, created_at FROM users WHERE id = ?');
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
    if (($user['person_type'] ?? 'student') !== 'student') $batch = '';
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
$allPosts = $stmt->fetchAll();

$allowedStatusFilters = ['all', 'open', 'claimed', 'resolved'];
$statusFilter = strtolower(trim($_GET['status'] ?? 'all'));
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = 'all';
}

$posts = $statusFilter === 'all'
    ? $allPosts
    : array_values(array_filter($allPosts, static fn($post) => $post['status'] === $statusFilter));

$lostCount = 0; $foundCount = 0; $resolvedCount = 0;
foreach ($allPosts as $p) {
    if ($p['type'] === 'lost') $lostCount++;
    if ($p['type'] === 'found') $foundCount++;
    if ($p['status'] === 'resolved') $resolvedCount++;
}

$personTypeLabels = ['student' => 'Student', 'faculty' => 'Faculty', 'staff' => 'Staff'];
$personTypeLabel = $personTypeLabels[$user['person_type'] ?? 'student'] ?? 'University member';

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
                <p class="profile-meta-line"><?= e($user['department'] ?: 'Department not added') ?> · <?= e($personTypeLabel) ?></p>
            </div>
        </div>
        <div class="profile-hero-summary">
            <div class="profile-stats profile-hero-stats profile-stats-gradient">
                <div class="profile-stat profile-stat-reports">
                    <span class="profile-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v5h5"/></svg></span>
                    <strong><?= count($allPosts) ?></strong><span>Reports</span>
                </div>
                <div class="profile-stat profile-stat-lost">
                    <span class="profile-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 2.8 20h18.4L12 3z"/><path d="M12 9v5m0 3h.01"/></svg></span>
                    <strong><?= $lostCount ?></strong><span>Lost</span>
                </div>
                <div class="profile-stat profile-stat-found">
                    <span class="profile-stat-icon" aria-hidden="true"><circle cx="11" cy="11" r="6"/><path d="m16 16 4 4"/></svg></span>
                    <strong><?= $foundCount ?></strong><span>Found</span>
                </div>
                <div class="profile-stat profile-stat-resolved">
                    <span class="profile-stat-icon" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg></span>
                    <strong><?= $resolvedCount ?></strong><span>Resolved</span>
                </div>
            </div>
            <?php if (!$isOwner): ?>
            <div class="profile-actions">
                <a class="btn btn-primary" href="<?= BASE_URL ?>/messages/conversation.php?with=<?= $user['id'] ?>">Message</a>
            </div>
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
                <label>Person type <span class="locked-note">Locked</span><input type="text" value="<?= e($personTypeLabel) ?>" disabled></label>
                <?php if (($user['person_type'] ?? 'student') === 'student'): ?>
                    <label>Batch<input type="text" name="batch" value="<?= e($user['batch']) ?>" placeholder="2022"></label>
                <?php else: ?>
                    <label>Batch<input type="text" value="Not applicable" disabled></label>
                <?php endif; ?>
                <label>Phone <span class="private-note">Private</span><input type="text" name="phone" value="<?= e($user['phone']) ?>" placeholder="Your contact number"></label>
                <label>University ID <span class="locked-note">Locked</span><input type="text" value="<?= e($user['university_id']) ?>" disabled></label>
                <label>Email <span class="private-note">Private</span><input type="email" value="<?= e($user['email']) ?>" disabled></label>
            </div>
            <div class="password-confirm-box">
                <div><strong>Confirm your identity</strong><p>Enter your current password before saving profile changes.</p></div>
                <label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label>
            </div>
            <div class="profile-edit-actions">
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a class="btn profile-cancel-button" href="<?= BASE_URL ?>/auth/profile.php">Cancel</a>
            </div>
        </form>
    </section>
    <?php endif; ?>

    <div class="profile-dashboard-layout<?= $isOwner ? ' is-owner' : ' is-public' ?>">
        <aside class="profile-left-column">
            <section class="profile-about-card profile-identity-card">
                <div class="section-heading compact"><div><span class="section-kicker">About</span><h2>University identity</h2></div></div>

                <dl class="identity-details">
                    <div class="identity-detail-row">
                        <span class="identity-detail-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 10h18M5 10v8m4-8v8m6-8v8m4-8v8M3 20h18M12 4l9 4H3l9-4z"/></svg></span>
                        <div><dt>Department</dt><dd><?= e($user['department'] ?: 'Not added') ?></dd></div>
                    </div>
                    <div class="identity-detail-row">
                        <span class="identity-detail-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M16 20v-1.5a4.5 4.5 0 0 0-4.5-4.5h-4A4.5 4.5 0 0 0 3 18.5V20m6.5-10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7zm7-1a3 3 0 0 1 0 5.8M21 20v-1.5a4.5 4.5 0 0 0-3.2-4.3"/></svg></span>
                        <div><dt>Batch</dt><dd><?= e($user['batch'] ?: 'Not added') ?></dd></div>
                    </div>
                    <div class="identity-detail-row">
                        <span class="identity-detail-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8" cy="11" r="2"/><path d="M5.5 16c.7-1.7 1.6-2.5 2.5-2.5s1.8.8 2.5 2.5M13 10h5m-5 4h5"/></svg></span>
                        <div><dt>University ID</dt><dd><?= e($isOwner ? ($user['university_id'] ?: 'Not added') : mask_university_id($user['university_id'])) ?></dd></div>
                    </div>
                    <?php if ($isOwner): ?>
                    <div class="identity-detail-row">
                        <span class="identity-detail-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg></span>
                        <div><dt>Email <span class="private-note">Private</span></dt><dd><?= e($user['email']) ?></dd></div>
                    </div>
                    <div class="identity-detail-row">
                        <span class="identity-detail-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7.6 3.8 10 8.2 7.8 10c1.2 2.6 3.4 4.8 6 6l1.8-2.2 4.4 2.4c.3.2.5.5.4.9-.4 2.3-2.4 3.9-4.7 3.7C9 20.1 3.9 15 3.2 8.3 3 6 4.6 4 6.9 3.6c.3 0 .6 0 .7.2z"/></svg></span>
                        <div><dt>Phone <span class="private-note">Private</span></dt><dd><?= e($user['phone'] ?: 'Not added') ?></dd></div>
                    </div>
                    <?php endif; ?>
                    <div class="identity-detail-row">
                        <span class="identity-detail-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4m8-4v4M3 10h18m-13 4h3m2 0h3m-8 3h3"/></svg></span>
                        <div><dt>Member since</dt><dd><?= e(date('F Y', strtotime($user['created_at']))) ?></dd></div>
                    </div>
                </dl>

                <?php if (!$isOwner): ?>
                    <p class="privacy-note">Contact details and the full university ID are hidden for privacy. Use the in-app message system to contact this member.</p>
                <?php endif; ?>

                <?php if ($isOwner && !$editing): ?>
                    <div class="identity-card-actions">
                        <a class="btn btn-primary identity-edit-button" href="<?= BASE_URL ?>/auth/profile.php?edit=1">Edit profile</a>
                        <a class="identity-logout-link" href="<?= BASE_URL ?>/auth/logout.php">
                            <span class="identity-logout-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M10 5H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4m4-3 4-4-4-4m4 4H9"/></svg></span>
                            <span class="identity-logout-text">Log out</span>
                        </a>
                    </div>
                <?php endif; ?>
            </section>
        </aside>

        <main class="profile-center-column">
            <section class="profile-activity">
                <div class="profile-posts-heading">
                    <div>
                        <h2><?= $isOwner ? 'My Reports' : e($user['name']) . '’s Reports' ?></h2>
                    </div>
                    <div class="profile-post-tools">
                        <?php if ($isOwner): ?>
                            <a class="btn btn-primary profile-post-create" href="<?= BASE_URL ?>/posts/create.php" aria-label="Post a lost or found item"><span aria-hidden="true">+</span> Post item</a>
                        <?php endif; ?>
                        <form method="get" class="profile-status-filter">
                            <?php if (!$isOwner): ?><input type="hidden" name="id" value="<?= $profileId ?>"><?php endif; ?>
                            <select id="profile-status" name="status" aria-label="Filter reports by status" onchange="this.form.submit()">
                                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All statuses</option>
                                <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Open</option>
                                <option value="claimed" <?= $statusFilter === 'claimed' ? 'selected' : '' ?>>Claimed</option>
                                <option value="resolved" <?= $statusFilter === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                            </select>
                        </form>
                    </div>
                </div>

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
        </main>

    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
