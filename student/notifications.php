<?php
$title = 'Notifications';
require_once __DIR__ . '/../includes/student-header.php';

if (isset($_GET['mark']) && $_GET['mark'] === 'all') {
    $stmt = db()->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    setAlert('success', 'All notifications marked as read.');
    redirect(BASE_URL . '/student/notifications.php');
}

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
if ($action === 'mark' && $id) {
    $stmt = db()->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $_SESSION['user_id']]);
    setAlert('success', 'Notification marked as read.');
    redirect(BASE_URL . '/student/notifications.php');
}

$filter = sanitize($_GET['filter'] ?? '');

$where   = "WHERE user_id = ?";
$params  = [$_SESSION['user_id']];
if ($filter === 'read') {
    $where .= " AND is_read = 1";
} elseif ($filter === 'unread') {
    $where .= " AND is_read = 0";
}

$page    = (int)($_GET['p'] ?? 1);
$perPage = 15;

$total = db()->prepare("SELECT COUNT(*) FROM notifications $where");
$total->execute($params);
$totalRows = $total->fetchColumn();
$pages  = paginate($page, $perPage, $totalRows);
$offset = ($page - 1) * $perPage;

$stmt = db()->prepare("SELECT * FROM notifications $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$notifications = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0">Notifications</h4>
        <div class="d-flex gap-2 align-items-center">
            <div class="btn-group btn-group-sm">
                <a href="?" class="btn btn-outline-secondary <?= $filter === '' ? 'active' : '' ?>">All</a>
                <a href="?filter=unread" class="btn btn-outline-secondary <?= $filter === 'unread' ? 'active' : '' ?>">Unread</a>
                <a href="?filter=read" class="btn btn-outline-secondary <?= $filter === 'read' ? 'active' : '' ?>">Read</a>
            </div>
            <a href="?mark=all" class="btn btn-primary btn-sm" onclick="return confirm('Mark all notifications as read?')">Mark All as Read</a>
        </div>
    </div>

    <table class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>Title</th>
                <th>Message</th>
                <th>Type</th>
                <th>Status</th>
                <th>Date</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($notifications as $n): ?>
            <tr class="<?= $n['is_read'] ? '' : 'table-active' ?>">
                <td><?= escapeOutput($n['title']) ?></td>
                <td><?= escapeOutput(mb_substr($n['message'], 0, 100)) ?></td>
                <td>
                    <span class="badge bg-<?= $n['type'] === 'info' ? 'info' : ($n['type'] === 'success' ? 'success' : ($n['type'] === 'warning' ? 'warning' : ($n['type'] === 'danger' ? 'danger' : 'primary'))) ?>">
                        <?= sanitize($n['type']) ?>
                    </span>
                </td>
                <td>
                    <?php if ($n['is_read']): ?>
                        <span class="text-success"><i class="bi bi-check-circle"></i> Read</span>
                    <?php else: ?>
                        <span class="text-warning"><i class="bi bi-envelope"></i> Unread</span>
                    <?php endif; ?>
                </td>
                <td><?= timeAgo($n['created_at']) ?></td>
                <td>
                    <?php if (!$n['is_read']): ?>
                        <a href="?action=mark&id=<?= $n['id'] ?>" class="btn btn-sm btn-outline-primary">Mark Read</a>
                    <?php else: ?>
                        <span class="text-muted small">&mdash;</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$notifications): ?>
            <tr><td colspan="6" class="text-center text-muted">No notifications found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?= paginationLinks($page, $pages) ?>
</div>

<?php require_once __DIR__ . '/../includes/student-footer.php'; ?>
