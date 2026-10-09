<?php
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function redirect($url) {
    while (ob_get_level()) ob_end_clean();
    header("Location: $url");
    exit;
}

function setAlert($type, $message) {
    $_SESSION['alert'] = ['type' => $type, 'message' => $message];
}

function displayAlert() {
    if (isset($_SESSION['alert'])) {
        $type = $_SESSION['alert']['type'];
        $msg = $_SESSION['alert']['message'];
        unset($_SESSION['alert']);
        return "<div class='alert alert-{$type} alert-dismissible fade show'>
            {$msg}<button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    }
    return '';
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'student';
}

function isWarden() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'warden';
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/auth/login.php');
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        redirect(BASE_URL . '/student/dashboard.php');
    }
}

function requireStudent() {
    requireLogin();
    if (!isStudent()) {
        redirect(BASE_URL . '/admin/dashboard.php');
    }
}

function requireWarden() {
    requireLogin();
    if (!isWarden()) {
        if (isAdmin()) redirect(BASE_URL . '/admin/dashboard.php');
        redirect(BASE_URL . '/student/dashboard.php');
    }
}

function logActivity($userId, $action, $description = '') {
    $stmt = db()->prepare("INSERT INTO activity_log (user_id, action, description) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $action, $description]);
}

function timeAgo($timestamp) {
    $diff = time() - strtotime($timestamp);
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 2592000) return floor($diff / 86400) . ' days ago';
    return date('d M Y', strtotime($timestamp));
}

function getTotal($table, $where = '1=1') {
    $stmt = db()->query("SELECT COUNT(*) FROM $table WHERE $where");
    return $stmt->fetchColumn();
}

function getSum($table, $column, $where = '1=1') {
    $stmt = db()->query("SELECT COALESCE(SUM($column), 0) FROM $table WHERE $where");
    return $stmt->fetchColumn();
}

function paginate($page, $perPage, $total) {
    $totalPages = ceil($total / $perPage);
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    return ['page' => $page, 'perPage' => $perPage, 'offset' => $offset, 'totalPages' => $totalPages, 'total' => $total];
}

function isHttps() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || $_SERVER['SERVER_PORT'] == 443;
}

function getBaseUrl() {
    $protocol = isHttps() ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . $host . dirname($_SERVER['SCRIPT_NAME'], 2);
}

function paginationLinks($page, $pages = null) {
    if ($pages === null && is_array($page)) {
        $pages = $page;
        $page = $pages['page'] ?? 1;
    } elseif (is_array($pages)) {
        $totalPages = $pages['totalPages'] ?? 0;
        $currentPage = $pages['page'] ?? 1;
    } else {
        $totalPages = (int)$pages;
        $currentPage = (int)$page;
    }
    $totalPages = $totalPages ?? 0;
    $currentPage = $currentPage ?? 1;
    if ($totalPages <= 1) return '';

    $baseUrl = preg_replace('/[?&]p(?:age)?=\d+/', '', $_SERVER['REQUEST_URI']);
    $glue = (strpos($baseUrl, '?') === false) ? '?p=' : '&p=';

    $html = '<nav><ul class="pagination justify-content-center">';
    $prev = $currentPage > 1 ? $currentPage - 1 : 1;
    $html .= "<li class='page-item " . ($currentPage == 1 ? 'disabled' : '') . "'>
        <a class='page-link' href='{$baseUrl}{$glue}{$prev}'>Previous</a></li>";
    for ($i = 1; $i <= $totalPages; $i++) {
        $active = $i == $currentPage ? 'active' : '';
        $html .= "<li class='page-item {$active}'><a class='page-link' href='{$baseUrl}{$glue}{$i}'>{$i}</a></li>";
    }
    $next = $currentPage < $totalPages ? $currentPage + 1 : $totalPages;
    $html .= "<li class='page-item " . ($currentPage == $totalPages ? 'disabled' : '') . "'>
        <a class='page-link' href='{$baseUrl}{$glue}{$next}'>Next</a></li></ul></nav>";
    return $html;
}

function getWardenHostelType() {
    if (!isWarden()) return null;
    try {
        $stmt = db()->prepare("SELECT hostel_type FROM wardens WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $type = $stmt->fetchColumn();
        return in_array($type, ['boys', 'girls']) ? $type : null;
    } catch (Exception $e) {
        return null;
    }
}

function requireRole($role) {
    requireLogin();
    $roles = is_array($role) ? $role : [$role];
    if (!in_array($_SESSION['role'], $roles)) {
        if (isAdmin()) redirect(BASE_URL . '/admin/dashboard.php');
        elseif (isWarden()) redirect(BASE_URL . '/warden/dashboard.php');
        else redirect(BASE_URL . '/student/dashboard.php');
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
    return $user;
}

function hasPermission($module, $action) {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'admin') return true;
    if ($role === 'warden' && in_array($module, ['attendance','complaints','leaves','visitors','notices','students','maintenance','emergency'])) return true;
    if ($role === 'student' && in_array($module, ['attendance','complaints','leave','visitors','notices','fees','profile'])) return true;
    return false;
}

function getHostelFilterCondition($alias = 's') {
    if (!isWarden()) return '';
    $hostelType = getWardenHostelType();
    if (!$hostelType) return '';
    if (!in_array($hostelType, ['boys', 'girls'])) return '';
    return " AND $alias.hostel_type = '$hostelType'";
}
