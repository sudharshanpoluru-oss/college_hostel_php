<?php
require_once __DIR__ . '/session.php';
requireWarden();
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
<nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background-color:#059669">
    <div class="container-fluid px-3">
        <button class="btn btn-sm btn-outline-light border-0 me-2 sidebar-toggle d-inline-flex d-md-none" type="button" onclick="toggleSidebar()">
            <i class="bi bi-list fs-5"></i>
        </button>
        <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>/warden/dashboard.php">
            <i class="bi bi-shield-check"></i> Warden Panel
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
                        <a href="<?= BASE_URL ?>/warden/notifications.php?mark=all" class="small text-decoration-none">Mark all read</a>
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
                        <a href="<?= BASE_URL ?>/warden/notifications.php" class="small text-decoration-none">View all notifications</a>
                    </div>
                </div>
            </div>
            <div class="dropdown">
                <a class="btn btn-sm btn-outline-light border-0 dropdown-toggle d-flex align-items-center gap-1" href="#" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle fs-5"></i>
                    <span class="d-none d-sm-inline"><?= sanitize($_SESSION['username']) ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/warden/profile.php"><i class="bi bi-person"></i> Profile</a></li>
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
    <div class="nav-item"><a class="nav-link <?= $navActive('dashboard.php') ?>" href="<?= BASE_URL ?>/warden/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></div>
    <div class="sidebar-section">Management</div>
    <div class="nav-item"><a class="nav-link <?= $navActive('attendance.php') ?>" href="<?= BASE_URL ?>/warden/attendance.php"><i class="bi bi-calendar-check"></i> Attendance</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('complaints.php') ?>" href="<?= BASE_URL ?>/warden/complaints.php"><i class="bi bi-exclamation-triangle"></i> Complaints</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('leaves.php') ?>" href="<?= BASE_URL ?>/warden/leaves.php"><i class="bi bi-box-arrow-right"></i> Leaves</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('visitors.php') ?>" href="<?= BASE_URL ?>/warden/visitors.php"><i class="bi bi-person-badge"></i> Visitors</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('maintenance.php') ?>" href="<?= BASE_URL ?>/warden/maintenance.php"><i class="bi bi-tools"></i> Maintenance</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('emergency.php') ?>" href="<?= BASE_URL ?>/warden/emergency.php"><i class="bi bi-exclamation-octagon"></i> Emergency</a></div>
    <div class="sidebar-section">Operations</div>
    <div class="nav-item"><a class="nav-link <?= $navActive('room-inspection.php') ?>" href="<?= BASE_URL ?>/warden/room-inspection.php"><i class="bi bi-door-open"></i> Room Inspection</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('daily-report.php') ?>" href="<?= BASE_URL ?>/warden/daily-report.php"><i class="bi bi-file-text"></i> Daily Report</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('discipline.php') ?>" href="<?= BASE_URL ?>/warden/discipline.php"><i class="bi bi-shield-exclamation"></i> Discipline</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('medical.php') ?>" href="<?= BASE_URL ?>/warden/medical.php"><i class="bi bi-heart-pulse"></i> Medical</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('night-roll-call.php') ?>" href="<?= BASE_URL ?>/warden/night-roll-call.php"><i class="bi bi-moon-stars"></i> Night Roll Call</a></div>
    <div class="sidebar-section">Info</div>
    <div class="nav-item"><a class="nav-link <?= $navActive('students.php') ?>" href="<?= BASE_URL ?>/warden/students.php"><i class="bi bi-people"></i> Students (Read Only)</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('notices.php') ?>" href="<?= BASE_URL ?>/warden/notices.php"><i class="bi bi-megaphone"></i> Notices</a></div>
    <div class="nav-item"><a class="nav-link <?= $navActive('notifications.php') ?>" href="<?= BASE_URL ?>/warden/notifications.php"><i class="bi bi-bell"></i> Notifications</a></div>
    <div class="sidebar-section">Account</div>
    <div class="nav-item"><a class="nav-link <?= $navActive('profile.php') ?>" href="<?= BASE_URL ?>/warden/profile.php"><i class="bi bi-person"></i> Profile</a></div>
</div>
<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-0 fw-bold"><?= $title ?></h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 mt-1">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/warden/dashboard.php">Warden</a></li>
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
