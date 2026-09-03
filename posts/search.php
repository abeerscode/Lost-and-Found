<?php
// AJAX endpoint: keyword search + category/type/status/date filters + sorting (FR-3.x).
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Login required.']);
    exit;
}

$q = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? '';
$categoryId = (int)($_GET['category_id'] ?? 0);
$status = $_GET['status'] ?? '';
$location = trim($_GET['location'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(p.title LIKE ? OR p.description LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if (in_array($type, ['lost', 'found'], true)) {
    $where[] = 'p.type = ?';
    $params[] = $type;
}
if ($categoryId > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $categoryId;
}
if (in_array($status, ['open', 'claimed', 'resolved'], true)) {
    $where[] = 'p.status = ?';
    $params[] = $status;
}
if ($location !== '') {
    $where[] = 'p.location LIKE ?';
    $params[] = '%' . $location . '%';
}
if ($dateFrom !== '') {
    $where[] = 'p.item_datetime >= ?';
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $where[] = 'p.item_datetime <= ?';
    $params[] = $dateTo . ' 23:59:59';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$orderSql = match ($sort) {
    'oldest' => 'ORDER BY p.created_at ASC',
    'nearest' => 'ORDER BY ABS(DATEDIFF(p.item_datetime, NOW())) ASC',
    default => 'ORDER BY p.created_at DESC',
};

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM posts p $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$sql = "SELECT p.*, c.name AS category_name, u.name AS owner_name
        FROM posts p
        JOIN categories c ON c.id = p.category_id
        JOIN users u ON u.id = p.user_id
        $whereSql
        $orderSql
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

$results = array_map(function ($p) {
    return [
        'id' => (int)$p['id'],
        'type' => $p['type'],
        'title' => $p['title'],
        'description' => mb_strimwidth($p['description'], 0, 140, '…'),
        'category' => $p['category_name'],
        'location' => $p['location'],
        'item_datetime' => $p['item_datetime'],
        'status' => $p['status'],
        'is_high_value' => (bool)$p['is_high_value'],
        'photo_url' => post_photo_url($p['photo_url']),
        'owner_name' => $p['owner_name'],
        'time_ago' => time_ago($p['created_at']),
        'view_url' => BASE_URL . '/posts/view.php?id=' . $p['id'],
    ];
}, $posts);

echo json_encode([
    'results' => $results,
    'total' => $total,
    'page' => $page,
    'per_page' => $perPage,
    'total_pages' => (int)ceil($total / $perPage),
]);
