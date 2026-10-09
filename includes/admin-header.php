<?php
require_once __DIR__ . '/session.php';
requireAdmin();
$title ??= 'Dashboard';
$currentPage = basename($_SERVER['PHP_SELF']);
$navActive = function (string $file) use ($currentPage): string {
    return $file === $currentPage ? 'active' : '';
};
$notifCount = getUnreadNotificationCount($_SESSION['user_id']);
$notifications = getNotifications($_SESSION['user_id'], 5);
ob_start();
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?> - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=6">
</head>
<body>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
    <div class="container-fluid px-3">
        <button class="btn btn-sm btn-outline-light border-0 me-2 sidebar-toggle d-inline-flex d-md-none" type="button" onclick="toggleSidebar()">
            <i class="bi bi-list fs-5"></i>
        </button>
        <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>/admin/dashboard.php">
            <i class="bi bi-shield-lock"></i> Admin Panel
        </a>
        <div class="d-flex align-items-center gap-2">
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-light border-0 position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-bell fs-5"></i>
                    <?php if ($notifCount > 0): ?>
                        <span class="notification-badge"><?= $notifCount > 9 ? '9+' : $notifCount ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end notif-dropdown">
                    <div class="dropdown-header d-flex justify-content-between align-items-center">
                        <span>Notifications</span>
                        <?php if ($notifCount > 0): ?>
                        <a href="<?= BASE_URL ?>/admin/notifications.php?mark=all" class="small text-decoration-none">Mark all read</a>
                        <?php endif; ?>
                    </div>
                    <?php if (count($notifications) > 0): ?>
                        <?php foreach ($notifications as $n): ?>
                        <a class="dropdown-item notification-item <?= $n['is_read'] ? '' : 'unread' ?>" href="<?= $n['link'] ?: '#' ?>">
                            <div class="d-flex align-items-start gap-2">
                                <span class="notification-dot <?= $n['type'] ?> mt-1"></span>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-semibold small"><?= escapeOutput($n['title']) ?></div>
                                    <div class="text-muted" style="font-size:0.75rem"><?= escapeOutput(mb_substr($n['message'], 0, 80)) ?></div>
                                    <div class="text-muted" style="font-size:0.65rem"><?= timeAgo($n['created_at']) ?></div>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-3 text-muted small">No notifications</div>
                    <?php endif; ?>
                    <div class="dropdown-footer">
                        <a href="<?= BASE_URL ?>/admin/notifications.php" class="small text-decoration-none">View all notifications</a>
                    </div>
                </div>
            </div>
            <div class="dropdown">
                <a class="btn btn-sm btn-outline-light border-0 dropdown-toggle d-flex align-items-center gap-1" href="#" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle fs-5"></i>
                    <span class="d-none d-sm-inline"><?= sanitize($_SESSION['username']) ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/profile.php"><i class="bi bi-person"></i> Profile</a></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/public/index.php" target="_blank"><i class="bi bi-globe"></i> View Site</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger fw-bold" href="<?= BASE_URL ?>/auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>
<div class="sidebar" id="adminSidebar">
    <div class="sidebar-section">Main</div>
    <div class="nav-item"><a class="nav-link <?= $navActive('dashboard.php') ?>" href="<?= BASE_URL ?>/admin/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('students.php') ?>" href="<?= BASE_URL ?>/admin/students.php"><i class="bi bi-people"></i> Students</a></div>
    <div class="sidebar-section">Accommodation</div>
    <div class="nav-item"><a class="nav-link <?= $navActive('rooms.php') ?>" href="<?= BASE_URL ?>/admin/rooms.php"><i class="bi bi-door-open"></i> Rooms</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('allocations.php') ?>" href="<?= BASE_URL ?>/admin/allocations.php"><i class="bi bi-key"></i> Allocations</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('room-changes.php') ?>" href="<?= BASE_URL ?>/admin/room-changes.php"><i class="bi bi-arrow-left-right"></i> Room Changes</a></div>
    <div class="sidebar-section">Finance</div>
    <div class="nav-item"><a class="nav-link <?= $navActive('fees.php') ?>" href="<?= BASE_URL ?>/admin/fees.php"><i class="bi bi-cash-coin"></i> Fees</a></div>
    <div class="sidebar-section">Management</div>
    <div class="nav-item"><a class="nav-link <?= $navActive('wardens.php') ?>" href="<?= BASE_URL ?>/admin/wardens.php"><i class="bi bi-shield-check"></i> Wardens</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('attendance.php') ?>" href="<?= BASE_URL ?>/admin/attendance.php"><i class="bi bi-calendar-check"></i> Attendance</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('complaints.php') ?>" href="<?= BASE_URL ?>/admin/complaints.php"><i class="bi bi-exclamation-triangle"></i> Complaints</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('leaves.php') ?>" href="<?= BASE_URL ?>/admin/leaves.php"><i class="bi bi-box-arrow-right"></i> Leaves</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('maintenance.php') ?>" href="<?= BASE_URL ?>/admin/maintenance.php"><i class="bi bi-tools"></i> Maintenance</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('emergency.php') ?>" href="<?= BASE_URL ?>/admin/emergency.php"><i class="bi bi-exclamation-octagon"></i> Emergency</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('vacate-requests.php') ?>" href="<?= BASE_URL ?>/admin/vacate-requests.php"><i class="bi bi-house-x"></i> Vacate Requests</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('vacated-students.php') ?>" href="<?= BASE_URL ?>/admin/vacated-students.php"><i class="bi bi-archive"></i> Vacated</a></div>
    <div class="sidebar-section">Operations</div>
    <div class="nav-item"><a class="nav-link <?= $navActive('manage-staff.php') ?>" href="<?= BASE_URL ?>/admin/manage-staff.php"><i class="bi bi-people-fill"></i> Staff</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('notices.php') ?>" href="<?= BASE_URL ?>/admin/notices.php"><i class="bi bi-megaphone"></i> Notices</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('mess.php') ?>" href="<?= BASE_URL ?>/admin/mess.php"><i class="bi bi-cup-hot"></i> Mess Menu</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('events.php') ?>" href="<?= BASE_URL ?>/admin/events.php"><i class="bi bi-calendar-event"></i> Events</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('visitors.php') ?>" href="<?= BASE_URL ?>/admin/visitors.php"><i class="bi bi-person-badge"></i> Visitors</a></div>
    <div class="sidebar-section">Analytics</div>
    <div class="nav-item"><a class="nav-link <?= $navActive('analytics.php') ?>" href="<?= BASE_URL ?>/admin/analytics.php"><i class="bi bi-graph-up"></i> Analytics</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('occupancy.php') ?>" href="<?= BASE_URL ?>/admin/occupancy.php"><i class="bi bi-building"></i> Room Occupancy</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('search.php') ?>" href="<?= BASE_URL ?>/admin/search.php"><i class="bi bi-search"></i> Global Search</a></div>
    <div class="sidebar-section">Reports</div>
    <div class="nav-item"><a class="nav-link <?= $navActive('reports.php') ?>" href="<?= BASE_URL ?>/admin/reports.php"><i class="bi bi-file-text"></i> Reports</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('notifications.php') ?>" href="<?= BASE_URL ?>/admin/notifications.php"><i class="bi bi-bell"></i> Notifications</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('digital-id.php') ?>" href="<?= BASE_URL ?>/admin/digital-id.php"><i class="bi bi-card-id"></i> Digital ID</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('student-timeline.php') ?>" href="<?= BASE_URL ?>/admin/student-timeline.php"><i class="bi bi-clock-history"></i> Student Timeline</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('backup.php') ?>" href="<?= BASE_URL ?>/admin/backup.php"><i class="bi bi-cloud-arrow-down"></i> Backup</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('profile.php') ?>" href="<?= BASE_URL ?>/admin/profile.php"><i class="bi bi-person"></i> Profile</a></div>
</div>
<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-0 fw-bold"><?= $title ?></h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 mt-1">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Admin</a></li>
                    <li class="breadcrumb-item active"><?= $title ?></li>
                </ol>
            </nav>
        </div>
    </div>
    <?= displayAlert() ?>
<script>
function toggleSidebar() {
    document.getElementById('adminSidebar').classList.toggle('show');
    document.getElementById('sidebarBackdrop').classList.toggle('show');
}
document.getElementById('sidebarBackdrop')?.addEventListener('click', function() {
    document.getElementById('adminSidebar').classList.remove('show');
    this.classList.remove('show');
});
</script>
