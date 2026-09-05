<?php
// Shared helper functions used across the app.

function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function redirect($path) {
    header('Location: ' . BASE_URL . $path);
    exit;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Informational only — reflects the account's role for display purposes
// (e.g. showing an "Admin Panel" link). It must NEVER be used to gate access
// to admin actions; that requires an active admin session (see is_admin_logged_in()
// and includes/admin_auth_check.php). This keeps the two login realms fully
// separate: a public-site login can never itself unlock admin capabilities.
function account_role_is_admin() {
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

// --- Admin session (separate cookie/session from the public site; see
// includes/admin_session.php) ---
function is_admin_logged_in() {
    return isset($_SESSION['admin_id']);
}

function current_admin_id() {
    return $_SESSION['admin_id'] ?? null;
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function flash_set($msg, $type = 'success') {
    $_SESSION['flash'] = ['message' => $msg, 'type' => $type];
}

function flash_get() {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify() {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(400);
        die('Invalid or expired form submission. Please go back and try again.');
    }
}

function is_university_email($email) {
    $domain = UNIVERSITY_EMAIL_DOMAIN;
    $len = strlen($domain);
    return strtolower(substr($email, -$len)) === strtolower($domain);
}

function time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 2592000) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}

function status_badge($status) {
    $map = [
        'open' => 'badge-open',
        'claimed' => 'badge-claimed',
        'resolved' => 'badge-resolved',
        'pending' => 'badge-pending',
        'approved' => 'badge-resolved',
        'rejected' => 'badge-rejected',
    ];
    $class = $map[$status] ?? 'badge-open';
    return '<span class="badge ' . $class . '">' . e(ucfirst($status)) . '</span>';
}

function handle_photo_upload($fileField) {
    if (empty($_FILES[$fileField]) || $_FILES[$fileField]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$fileField];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed. Please try again.');
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('Photo is too large (max 5MB).');
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) {
        throw new RuntimeException('Only JPG, PNG, WEBP, or GIF photos are allowed.');
    }
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        default => 'bin',
    };
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename)) {
        throw new RuntimeException('Could not save uploaded photo.');
    }
    return $filename;
}


function mask_university_id($id) {
    $id = trim((string)$id);
    if ($id === '') return 'Not provided';
    $len = strlen($id);
    if ($len <= 4) return str_repeat('•', max(2, $len));
    return substr($id, 0, max(2, $len - 4)) . str_repeat('•', 2) . substr($id, -2);
}

function profile_photo_url($path) {
    $path = trim((string)$path);
    if ($path === '') return null;
    if (preg_match('#^https?://#i', $path)) return $path;
    $path = ltrim($path, '/');
    if (str_starts_with($path, 'uploads/')) $path = substr($path, 8);
    return UPLOAD_URL . $path;
}

function log_status_change(PDO $pdo, $postId, $oldStatus, $newStatus, $changedBy) {
    $stmt = $pdo->prepare(
        'INSERT INTO post_status_log (post_id, old_status, new_status, changed_by) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$postId, $oldStatus, $newStatus, $changedBy]);
}

function create_notification(PDO $pdo, $userId, $type, $message, $link = null) {
    $stmt = $pdo->prepare(
        'INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $type, $message, $link]);
}

function unread_notification_count(PDO $pdo, $userId) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function unread_message_count(PDO $pdo, $userId) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

// Normalize photo paths so both normal uploads (e.g. abc.jpg) and seeded
// demo paths (e.g. uploads/demo/watch.jpg) resolve correctly.
function post_photo_url($path) {
    $path = trim((string)$path);
    if ($path === '') return null;
    if (preg_match('#^https?://#i', $path)) return $path;
    $path = ltrim($path, '/');
    if (str_starts_with($path, 'uploads/')) {
        $path = substr($path, strlen('uploads/'));
    }
    return UPLOAD_URL . $path;
}

// Normalize notification links created by seed/demo data as well as links
// created by the application at runtime.
function app_link($path) {
    $path = trim((string)$path);
    if ($path === '') return '#';
    if (preg_match('#^https?://#i', $path) || str_starts_with($path, BASE_URL)) return $path;
    if ($path[0] !== '/') $path = '/' . $path;
    return BASE_URL . $path;
}

function admin_log(PDO $pdo, $actionType, $targetType = null, $targetId = null, $description = '') {
    if (!is_admin_logged_in()) return;
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO admin_activity (admin_id, action_type, target_type, target_id, description) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([current_admin_id(), $actionType, $targetType, $targetId, $description]);
    } catch (PDOException $e) {
        // Keep moderation actions usable if an older database has not yet run
        // the admin activity migration. The admin UI will surface the migration notice.
    }
}

function person_type_label($type) {
    return match ($type) {
        'faculty' => 'Faculty',
        'staff' => 'Staff',
        default => 'Student',
    };
}

function account_status_badge($status) {
    $class = match ($status) {
        'active' => 'admin-status-active',
        'suspended' => 'admin-status-suspended',
        'banned' => 'admin-status-banned',
        default => 'admin-status-neutral',
    };
    return '<span class="admin-status ' . $class . '">' . e(ucfirst($status)) . '</span>';
}

function admin_recent_events(PDO $pdo, $limit = 20) {
    $events = [];

    try {
        $rows = $pdo->query(
            "SELECT aa.id, aa.action_type, aa.target_type, aa.target_id, aa.description, aa.created_at,
                    u.name AS actor_name
             FROM admin_activity aa
             LEFT JOIN users u ON u.id = aa.admin_id
             ORDER BY aa.created_at DESC LIMIT 80"
        )->fetchAll();
        foreach ($rows as $row) {
            $events[] = [
                'kind' => 'admin',
                'title' => $row['description'] ?: ucfirst(str_replace('_', ' ', $row['action_type'])),
                'meta' => ($row['actor_name'] ?: 'Administrator') . ' · admin action',
                'created_at' => $row['created_at'],
                'target_type' => $row['target_type'],
                'target_id' => $row['target_id'],
            ];
        }
    } catch (PDOException $e) {}

    try {
        $rows = $pdo->query(
            "SELECT p.id, p.title, p.type, p.created_at, u.name AS user_name
             FROM posts p JOIN users u ON u.id = p.user_id
             ORDER BY p.created_at DESC LIMIT 60"
        )->fetchAll();
        foreach ($rows as $row) {
            $events[] = [
                'kind' => 'post',
                'title' => $row['user_name'] . ' reported “' . $row['title'] . '”',
                'meta' => ucfirst($row['type']) . ' item report',
                'created_at' => $row['created_at'],
                'target_type' => 'post',
                'target_id' => $row['id'],
            ];
        }
    } catch (PDOException $e) {}

    try {
        $rows = $pdo->query(
            "SELECT cl.id, cl.post_id, cl.status, cl.created_at, p.title, u.name AS user_name
             FROM claims cl
             JOIN posts p ON p.id = cl.post_id
             JOIN users u ON u.id = cl.claimant_id
             ORDER BY cl.created_at DESC LIMIT 60"
        )->fetchAll();
        foreach ($rows as $row) {
            $events[] = [
                'kind' => 'claim',
                'title' => $row['user_name'] . ' submitted a claim for “' . $row['title'] . '”',
                'meta' => 'Claim · ' . ucfirst($row['status']),
                'created_at' => $row['created_at'],
                'target_type' => 'post',
                'target_id' => $row['post_id'],
            ];
        }
    } catch (PDOException $e) {}

    try {
        $rows = $pdo->query(
            "SELECT psl.id, psl.post_id, psl.old_status, psl.new_status, psl.created_at, p.title,
                    u.name AS actor_name
             FROM post_status_log psl
             JOIN posts p ON p.id = psl.post_id
             LEFT JOIN users u ON u.id = psl.changed_by
             ORDER BY psl.created_at DESC LIMIT 60"
        )->fetchAll();
        foreach ($rows as $row) {
            $events[] = [
                'kind' => 'status',
                'title' => '“' . $row['title'] . '” changed to ' . ucfirst($row['new_status']),
                'meta' => ($row['actor_name'] ?: 'System') . ' · status update',
                'created_at' => $row['created_at'],
                'target_type' => 'post',
                'target_id' => $row['post_id'],
            ];
        }
    } catch (PDOException $e) {}

    try {
        $rows = $pdo->query(
            "SELECT id, name, person_type, created_at FROM users
             WHERE role <> 'admin' ORDER BY created_at DESC LIMIT 40"
        )->fetchAll();
        foreach ($rows as $row) {
            $events[] = [
                'kind' => 'user',
                'title' => $row['name'] . ' joined the system',
                'meta' => person_type_label($row['person_type']) . ' account',
                'created_at' => $row['created_at'],
                'target_type' => 'user',
                'target_id' => $row['id'],
            ];
        }
    } catch (PDOException $e) {}

    usort($events, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
    return array_slice($events, 0, max(1, (int)$limit));
}

function admin_event_url($event) {
    $type = $event['target_type'] ?? null;
    $id = (int)($event['target_id'] ?? 0);
    if ($type === 'post' && $id) return BASE_URL . '/admin/post_view.php?id=' . $id;
    if ($type === 'user' && $id) return BASE_URL . '/admin/user_view.php?id=' . $id;
    return null;
}
