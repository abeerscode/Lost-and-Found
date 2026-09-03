<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$selectedCategoryId = max(0, (int)($_GET['category_id'] ?? 0));

$pageTitle = 'Feed';
include __DIR__ . '/../includes/header.php';
?>
<div class="feed-header feed-header-simple">
    <div>
        <h1>Browse items</h1>
        <p>Search recent lost and found reports from around campus.</p>
    </div>
</div>

<?php
$locations = $pdo->query("SELECT DISTINCT location FROM posts WHERE location <> '' ORDER BY location")->fetchAll(PDO::FETCH_COLUMN);
?>

<form id="filter-form" class="feed-toolbar" autocomplete="off">
    <div class="feed-toolbar-main">
        <label class="feed-search" for="f-q">
            <span class="feed-search-icon" aria-hidden="true">⌕</span>
            <input type="search" name="q" id="f-q" placeholder='Search items, e.g. "backpack"' aria-label="Search items">
        </label>

        <select name="type" id="f-type" aria-label="Item type">
            <option value="">All items</option>
            <option value="lost">Lost</option>
            <option value="found">Found</option>
        </select>

        <select name="category_id" id="f-category" aria-label="Category">
            <option value="">All categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $selectedCategoryId === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="location" id="f-location" aria-label="Location">
            <option value="">All locations</option>
            <?php foreach ($locations as $location): ?>
                <option value="<?= e($location) ?>"><?= e($location) ?></option>
            <?php endforeach; ?>
        </select>

        <button class="toolbar-filter-btn" type="button" id="advanced-filter-toggle" aria-expanded="false" aria-controls="advanced-filters">
            <span aria-hidden="true">☷</span> Filters
        </button>
    </div>

    <div class="feed-toolbar-secondary">
        <div class="category-chips" id="category-chips" aria-label="Quick category filters">
            <a class="category-chip <?= $selectedCategoryId === 0 ? 'active' : '' ?>" data-category="" href="<?= BASE_URL ?>/posts/feed.php">All</a>
            <?php foreach ($categories as $cat): ?>
                <a class="category-chip <?= $selectedCategoryId === (int)$cat['id'] ? 'active' : '' ?>" data-category="<?= $cat['id'] ?>" href="<?= BASE_URL ?>/posts/feed.php?category_id=<?= $cat['id'] ?>"><?= e($cat['name']) ?></a>
            <?php endforeach; ?>
        </div>

        <select name="sort" id="f-sort" class="feed-sort" aria-label="Sort results">
            <option value="newest">Newest</option>
            <option value="oldest">Oldest</option>
            <option value="nearest">Nearest item date</option>
        </select>
    </div>

    <div class="advanced-filters" id="advanced-filters" hidden>
        <label>Status
            <select name="status" id="f-status">
                <option value="">Any status</option>
                <option value="open">Open</option>
                <option value="claimed">Claimed</option>
                <option value="resolved">Resolved</option>
            </select>
        </label>
        <label>From
            <input type="date" name="date_from" id="f-date-from">
        </label>
        <label>To
            <input type="date" name="date_to" id="f-date-to">
        </label>
        <button type="button" class="btn btn-secondary btn-small" id="clear-filters">Clear filters</button>
    </div>
</form>

<section class="feed-panel feed-panel-full">
    <div id="feed-status" class="feed-status muted"></div>
    <div id="feed-results" class="post-grid">
        <p class="muted">Loading posts&hellip;</p>
    </div>
    <div id="feed-pagination" class="pagination"></div>
</section>

<template id="post-card-template">
    <a class="post-card" href="">
        <div class="post-card-photo"></div>
        <div class="post-card-body">
            <div class="post-card-top">
                <span class="type-tag"></span>
                <span class="status-tag"></span>
            </div>
            <h3 class="post-card-title"></h3>
            <p class="post-card-meta"></p>
            <p class="post-card-desc"></p>
        </div>
    </a>
</template>

<script>window.APP_BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
<script src="<?= BASE_URL ?>/js/search.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
