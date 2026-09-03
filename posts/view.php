<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$postId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT p.*, c.name AS category_name, u.name AS owner_name, u.id AS owner_id
     FROM posts p JOIN categories c ON c.id = p.category_id JOIN users u ON u.id = p.user_id
     WHERE p.id = ?'
);
$stmt->execute([$postId]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    $pageTitle = 'Not Found';
    include __DIR__ . '/../includes/header.php';
    echo '<p>This post no longer exists.</p>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$isOwner = (int)$post['owner_id'] === (int)current_user_id();

// Comments
$stmt = $pdo->prepare(
    'SELECT cm.*, u.name AS author_name, u.id AS author_id FROM comments cm JOIN users u ON u.id = cm.user_id
     WHERE cm.post_id = ? ORDER BY cm.created_at ASC'
);
$stmt->execute([$postId]);
$comments = $stmt->fetchAll();

$commentsByParent = [];
foreach ($comments as $comment) {
    $parentKey = $comment['parent_id'] === null ? 0 : (int)$comment['parent_id'];
    $commentsByParent[$parentKey][] = $comment;
}
$topLevelComments = $commentsByParent[0] ?? [];

// Claims (the post owner sees all claims on their post; a claimant sees only
// their own — admin-side review happens separately in the admin panel)
if ($isOwner) {
    $stmt = $pdo->prepare(
        'SELECT cl.*, u.name AS claimant_name FROM claims cl JOIN users u ON u.id = cl.claimant_id
         WHERE cl.post_id = ? ORDER BY cl.created_at DESC'
    );
    $stmt->execute([$postId]);
} else {
    $stmt = $pdo->prepare(
        'SELECT cl.*, u.name AS claimant_name FROM claims cl JOIN users u ON u.id = cl.claimant_id
         WHERE cl.post_id = ? AND cl.claimant_id = ? ORDER BY cl.created_at DESC'
    );
    $stmt->execute([$postId, current_user_id()]);
}
$claims = $stmt->fetchAll();

$myPendingClaim = null;
foreach ($claims as $c) {
    if ((int)$c['claimant_id'] === (int)current_user_id() && $c['status'] === 'pending') {
        $myPendingClaim = $c;
    }
}

$isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') || (($_POST['response_format'] ?? '') === 'json');
$commentError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'comment') {
    csrf_verify();
    $message = trim($_POST['message'] ?? '');
    $parentId = (int)($_POST['parent_id'] ?? 0);
    $parentComment = null;

    if ($parentId > 0) {
        $parentStmt = $pdo->prepare(
            'SELECT cm.id, cm.user_id, cm.parent_id, u.name AS author_name
             FROM comments cm JOIN users u ON u.id = cm.user_id
             WHERE cm.id = ? AND cm.post_id = ?'
        );
        $parentStmt->execute([$parentId, $postId]);
        $parentComment = $parentStmt->fetch();
        if (!$parentComment) {
            $parentId = 0;
        } elseif (!empty($parentComment['parent_id'])) {
            // Keep the conversation readable: replies are one level deep.
            $parentId = (int)$parentComment['parent_id'];
            $parentStmt->execute([$parentId, $postId]);
            $parentComment = $parentStmt->fetch();
        }
    }

    if ($message === '') {
        $commentError = 'Comment cannot be empty.';
        if ($isAjax) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => $commentError]);
            exit;
        }
    } else {
        $stmt = $pdo->prepare('INSERT INTO comments (post_id, user_id, parent_id, message) VALUES (?, ?, ?, ?)');
        $stmt->execute([$postId, current_user_id(), $parentId ?: null, $message]);
        $newCommentId = (int)$pdo->lastInsertId();
        $createdStmt = $pdo->prepare('SELECT created_at FROM comments WHERE id = ?');
        $createdStmt->execute([$newCommentId]);
        $newCreatedAt = $createdStmt->fetchColumn() ?: date('Y-m-d H:i:s');

        $notified = [];
        if ($parentId > 0 && $parentComment && (int)$parentComment['user_id'] !== (int)current_user_id()) {
            create_notification(
                $pdo, $parentComment['user_id'], 'comment_reply',
                $_SESSION['name'] . ' replied to your comment on "' . $post['title'] . '"',
                BASE_URL . '/posts/view.php?id=' . $postId . '#comment-' . $newCommentId
            );
            $notified[(int)$parentComment['user_id']] = true;
        }
        if (!$isOwner && !isset($notified[(int)$post['owner_id']])) {
            create_notification(
                $pdo, $post['owner_id'], 'comment',
                $_SESSION['name'] . ($parentId > 0 ? ' replied on your post "' : ' commented on your post "') . $post['title'] . '"',
                BASE_URL . '/posts/view.php?id=' . $postId . '#comment-' . $newCommentId
            );
        }
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'id' => $newCommentId,
                'parent_id' => $parentId ?: null,
                'author_id' => (int)current_user_id(),
                'author_name' => $_SESSION['name'],
                'message' => $message,
                'created_at' => date('c', strtotime($newCreatedAt)),
                'profile_url' => BASE_URL . '/auth/profile.php?id=' . current_user_id(),
            ]);
            exit;
        }
        redirect('/posts/view.php?id=' . $postId . '#comment-' . $newCommentId);
    }
}

$pageTitle = $post['title'];
include __DIR__ . '/../includes/header.php';
?>
<div class="post-detail">
    <div class="post-detail-header">
        <span class="type-tag type-<?= e($post['type']) ?>"><?= $post['type'] === 'lost' ? 'Lost Item' : 'Found Item' ?></span>
        <?= status_badge($post['status']) ?>
        <?php if ($post['is_high_value']): ?>
            <span class="badge badge-highvalue">High-Value &mdash; Admin Verification Required</span>
        <?php endif; ?>
    </div>

    <h1><?= e($post['title']) ?></h1>

    <?php if ($post['photo_url']): ?>
        <img class="post-photo" src="<?= e(post_photo_url($post['photo_url'])) ?>" alt="<?= e($post['title']) ?>">
    <?php endif; ?>

    <dl class="post-meta">
        <dt>Category</dt><dd><?= e($post['category_name']) ?></dd>
        <dt>Location</dt><dd><?= e($post['location']) ?></dd>
        <dt>Date/Time</dt><dd><?= e(date('M j, Y g:i A', strtotime($post['item_datetime']))) ?></dd>
        <dt>Posted by</dt><dd><a class="profile-inline-link" href="<?= BASE_URL ?>/auth/profile.php?id=<?= $post['owner_id'] ?>"><?= e($post['owner_name']) ?></a> &middot; <?= e(time_ago($post['created_at'])) ?></dd>
    </dl>

    <p class="post-description"><?= nl2br(e($post['description'])) ?></p>

    <?php if ($isOwner): ?>
        <div class="post-owner-actions">
            <div class="post-owner-actions-main">
                <a class="btn" href="<?= BASE_URL ?>/posts/edit.php?id=<?= $post['id'] ?>">Edit Post</a>
                <form method="post" action="<?= BASE_URL ?>/posts/delete.php" onsubmit="return confirm('Delete this post permanently?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $post['id'] ?>">
                    <button type="submit" class="btn btn-danger">Delete Post</button>
                </form>
            </div>
            <form class="post-status-control" method="post" action="<?= BASE_URL ?>/posts/update_status.php">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $post['id'] ?>">
                <label for="post-status">Status</label>
                <select id="post-status" name="status" onchange="this.form.submit()">
                    <?php foreach (['open', 'claimed', 'resolved'] as $s): ?>
                        <option value="<?= $s ?>" <?= $post['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    <?php elseif (!$myPendingClaim): ?>
        <div class="action-bar">
            <a class="btn btn-primary" href="<?= BASE_URL ?>/claims/create_claim.php?post_id=<?= $post['id'] ?>">Submit Claim Request</a>
            <a class="btn" href="<?= BASE_URL ?>/messages/conversation.php?with=<?= $post['owner_id'] ?>&post_id=<?= $post['id'] ?>">Message Poster</a>
        </div>
    <?php else: ?>
        <p class="muted">Your claim request is pending review.</p>
    <?php endif; ?>

    <?php if ($isOwner): ?>
        <section class="claims-section">
            <h2>Claim Requests (<?= count($claims) ?>)</h2>
            <?php if (!$claims): ?>
                <p class="muted">No claim requests yet.</p>
            <?php endif; ?>
            <?php foreach ($claims as $claim): ?>
                <div class="claim-card">
                    <div class="claim-card-top">
                        <strong><?= e($claim['claimant_name']) ?></strong>
                        <?= status_badge($claim['status']) ?>
                        <span class="muted"><?= e(time_ago($claim['created_at'])) ?></span>
                    </div>
                    <p><?= nl2br(e($claim['proof_description'])) ?></p>
                    <?php if ($claim['status'] === 'pending'): ?>
                        <?php if ($post['is_high_value']): ?>
                            <p class="muted">This item is flagged high-value &mdash; it has been routed to an administrator for verification. You'll be notified once it's reviewed.</p>
                        <?php else: ?>
                            <form method="post" action="<?= BASE_URL ?>/claims/respond_claim.php" style="display:inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="claim_id" value="<?= $claim['id'] ?>">
                                <button type="submit" name="decision" value="approved" class="btn btn-primary btn-sm">Approve</button>
                                <button type="submit" name="decision" value="rejected" class="btn btn-danger btn-sm">Reject</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="comments-section" id="comments">
        <div class="comments-heading-row">
            <h2 id="comments-heading">Comments <span class="comment-count-badge" id="comments-count"><?= count($comments) ?></span></h2>
            <span class="muted comments-helper">Ask questions or reply to another student.</span>
        </div>
        <?php if ($commentError): ?>
            <div class="flash flash-error"><?= e($commentError) ?></div>
        <?php endif; ?>

        <div id="comment-list" class="comment-list">
        <?php foreach ($topLevelComments as $comment): ?>
            <article class="comment-thread" id="comment-<?= (int)$comment['id'] ?>" data-comment-id="<?= (int)$comment['id'] ?>">
                <div class="comment-row">
                    <a class="comment-avatar" href="<?= BASE_URL ?>/auth/profile.php?id=<?= (int)$comment['author_id'] ?>" aria-label="View <?= e($comment['author_name']) ?>'s profile"><?= e(strtoupper(substr(trim($comment['author_name']), 0, 1))) ?></a>
                    <div class="comment-content-wrap">
                        <div class="comment-bubble">
                            <a class="comment-author" href="<?= BASE_URL ?>/auth/profile.php?id=<?= (int)$comment['author_id'] ?>"><?= e($comment['author_name']) ?></a>
                            <p><?= nl2br(e($comment['message'])) ?></p>
                        </div>
                        <div class="comment-meta-actions">
                            <span class="relative-time" data-timestamp="<?= e(date('c', strtotime($comment['created_at']))) ?>"><?= e(time_ago($comment['created_at'])) ?></span>
                            <button type="button" class="comment-reply-btn" data-reply-to="<?= (int)$comment['id'] ?>" data-reply-name="<?= e($comment['author_name']) ?>">Reply</button>
                        </div>

                        <div class="comment-replies" data-replies-for="<?= (int)$comment['id'] ?>">
                        <?php foreach (($commentsByParent[(int)$comment['id']] ?? []) as $reply): ?>
                            <article class="comment-reply" id="comment-<?= (int)$reply['id'] ?>">
                                <a class="comment-avatar comment-avatar-sm" href="<?= BASE_URL ?>/auth/profile.php?id=<?= (int)$reply['author_id'] ?>" aria-label="View <?= e($reply['author_name']) ?>'s profile"><?= e(strtoupper(substr(trim($reply['author_name']), 0, 1))) ?></a>
                                <div class="comment-content-wrap">
                                    <div class="comment-bubble">
                                        <a class="comment-author" href="<?= BASE_URL ?>/auth/profile.php?id=<?= (int)$reply['author_id'] ?>"><?= e($reply['author_name']) ?></a>
                                        <p><?= nl2br(e($reply['message'])) ?></p>
                                    </div>
                                    <div class="comment-meta-actions">
                                        <span class="relative-time" data-timestamp="<?= e(date('c', strtotime($reply['created_at']))) ?>"><?= e(time_ago($reply['created_at'])) ?></span>
                                        <button type="button" class="comment-reply-btn" data-reply-to="<?= (int)$comment['id'] ?>" data-reply-name="<?= e($reply['author_name']) ?>">Reply</button>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        </div>

                        <form method="post" action="" class="inline-reply-form" data-parent-id="<?= (int)$comment['id'] ?>" hidden>
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="comment">
                            <input type="hidden" name="parent_id" value="<?= (int)$comment['id'] ?>">
                            <div class="comment-composer comment-composer-reply">
                                <span class="comment-avatar comment-avatar-sm comment-avatar-self" aria-hidden="true"><?= e(strtoupper(substr(trim($_SESSION['name'] ?? 'U'), 0, 1))) ?></span>
                                <textarea name="message" rows="1" maxlength="1000" placeholder="Write a reply..." required></textarea>
                                <button type="submit" class="comment-send-btn" aria-label="Post reply" title="Post reply">&#10148;</button>
                            </div>
                            <button type="button" class="reply-cancel-btn">Cancel</button>
                        </form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
        </div>

        <?php if (!$comments): ?>
            <p class="muted comments-empty" id="comments-empty">No comments yet. Ask the first question.</p>
        <?php endif; ?>

        <form method="post" action="" id="comment-form" class="main-comment-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="comment">
            <input type="hidden" name="parent_id" value="">
            <div class="comment-composer">
                <span class="comment-avatar comment-avatar-self" aria-hidden="true"><?= e(strtoupper(substr(trim($_SESSION['name'] ?? 'U'), 0, 1))) ?></span>
                <textarea name="message" rows="1" maxlength="1000" placeholder="Write a comment..." required></textarea>
                <button type="submit" class="comment-send-btn" aria-label="Post comment" title="Post comment">&#10148;</button>
            </div>
        </form>
    </section>
</div>
<script src="<?= BASE_URL ?>/js/claims.js?v=20260904-2"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
