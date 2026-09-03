<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('/posts/feed.php');
}

$recentPosts = [];
try {
    $stmt = $pdo->query(
        "SELECT p.id, p.type, p.title, p.location, p.photo_url, c.name AS category_name
         FROM posts p JOIN categories c ON c.id = p.category_id
         WHERE p.status <> 'resolved'
         ORDER BY p.created_at DESC LIMIT 6"
    );
    $recentPosts = $stmt->fetchAll();
} catch (Throwable $e) {
    // Keep the landing page usable even before the database seed is imported.
}

$pageTitle = 'Campus Lost & Found';
include __DIR__ . '/includes/header.php';
?>
<section class="landing-shell">
    <div class="landing-hero landing-hero-split">
        <div class="landing-copy">
            <span class="landing-eyebrow">Campus-only lost &amp; found</span>
            <h1>Lost it on campus?<br>Find it here.</h1>
            <p class="landing-lead">Report lost or found items, browse recent posts, and message the other person privately when something looks familiar.</p>
            <div class="landing-actions landing-actions-left">
                <a class="btn btn-primary btn-lg" href="<?= BASE_URL ?>/auth/register.php">Report an item</a>
                <a class="btn btn-lg" href="<?= BASE_URL ?>/auth/login.php">Browse items</a>
            </div>
            <div class="landing-trust">
                <span>✓ University accounts</span>
                <span>✓ Private messaging</span>
                <span>✓ Claim verification</span>
            </div>
        </div>
        <div class="landing-visual" aria-hidden="true">
            <div class="visual-orbit visual-orbit-one"></div>
            <div class="visual-orbit visual-orbit-two"></div>
            <div class="floating-item floating-item-a"><img src="<?= BASE_URL ?>/uploads/demo/black-backpack.jpg" alt=""></div>
            <div class="floating-item floating-item-b"><img src="<?= BASE_URL ?>/uploads/demo/airpods-pro.jpg" alt=""></div>
            <div class="floating-item floating-item-c"><img src="<?= BASE_URL ?>/uploads/demo/car-keys.jpg" alt=""></div>
            <div class="floating-item floating-item-d"><img src="<?= BASE_URL ?>/uploads/demo/black-watch.jpg" alt=""></div>
        </div>
    </div>

    <section class="landing-section" id="how-it-works">
        <div class="section-kicker">How it works</div>
        <div class="landing-feature-grid">
            <article><span class="feature-num">01</span><h2>Report</h2><p>Add a photo, location and a few details about the item you lost or found.</p></article>
            <article><span class="feature-num">02</span><h2>Match</h2><p>Use simple filters to scan recent campus reports without digging through social posts.</p></article>
            <article><span class="feature-num">03</span><h2>Return</h2><p>Message privately, verify the claim, then arrange a safe campus handoff.</p></article>
        </div>
    </section>

    <?php if ($recentPosts): ?>
    <section class="landing-section landing-recent">
        <div class="landing-section-head">
            <div><div class="section-kicker">Recent reports</div><h2>A quick look at the feed</h2></div>
            <a href="<?= BASE_URL ?>/auth/login.php" class="text-link">Sign in to browse →</a>
        </div>
        <div class="public-card-grid">
            <?php foreach ($recentPosts as $post): ?>
                <article class="public-card">
                    <div class="public-card-image" style="background-image:url('<?= e(post_photo_url($post['photo_url'])) ?>')"></div>
                    <div class="public-card-body">
                        <span class="type-tag type-<?= e($post['type']) ?>"><?= ucfirst(e($post['type'])) ?></span>
                        <h3><?= e($post['title']) ?></h3>
                        <p><?= e($post['category_name']) ?> · <?= e($post['location']) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="landing-cta">
        <div><span class="section-kicker">Ready when you need it</span><h2>One campus. One place to look.</h2></div>
        <a class="btn btn-primary btn-lg" href="<?= BASE_URL ?>/auth/register.php">Create account</a>
    </section>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
