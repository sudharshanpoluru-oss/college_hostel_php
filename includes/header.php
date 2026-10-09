<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? SITE_NAME ?> - <?= SITE_NAME ?></title>
    <meta name="description" content="Hostels of <?= SITE_NAME ?> — safe and comfortable student accommodation with modern amenities, 24/7 security, and healthy meals.">
    <meta property="og:title" content="<?= SITE_NAME ?> - Student Hostel Accommodation">
    <meta property="og:description" content="Safe, comfortable, and affordable accommodation for students.">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=6">
</head>
<body>
<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="<?= BASE_URL ?>/public/index.php">
            <i class="bi bi-building"></i> <?= SITE_NAME ?>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item"><a class="nav-link text-dark" href="<?= BASE_URL ?>/public/index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link text-dark" href="<?= BASE_URL ?>/public/about.php">About</a></li>
                <li class="nav-item"><a class="nav-link text-dark" href="<?= BASE_URL ?>/public/rooms.php">Rooms</a></li>
                <li class="nav-item"><a class="nav-link text-dark" href="<?= BASE_URL ?>/public/amenities.php">Amenities</a></li>
                <li class="nav-item"><a class="nav-link text-dark" href="<?= BASE_URL ?>/public/gallery.php">Gallery</a></li>
                <li class="nav-item"><a class="nav-link text-dark" href="<?= BASE_URL ?>/public/contact.php">Contact</a></li>
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link text-dark dropdown-toggle fw-semibold" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle text-primary"></i> <?= sanitize($_SESSION['username']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/<?= isAdmin() ? 'admin' : 'student' ?>/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger fw-bold" href="<?= BASE_URL ?>/auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-success text-white fw-semibold px-3" href="<?= BASE_URL ?>/auth/login.php?role=student"><i class="bi bi-person"></i> Student Login</a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-outline-warning fw-semibold px-3" href="<?= BASE_URL ?>/auth/login.php?role=warden"><i class="bi bi-shield-check"></i> Warden</a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-outline-primary fw-semibold px-3" href="<?= BASE_URL ?>/auth/login.php?role=admin"><i class="bi bi-shield-lock"></i> Admin</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<?php $alert = displayAlert(); if ($alert): ?>
<div class="container mt-3" style="padding-top: 56px;">
    <?= $alert ?>
</div>
<?php endif; ?>
