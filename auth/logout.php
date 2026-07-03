<?php
require_once __DIR__ . '/../includes/session.php';

if (isLoggedIn()) {
    logActivity($_SESSION['user_id'], 'Logout', 'User logged out');
}

$_SESSION = [];
session_destroy();
redirect(BASE_URL . '/public/index.php');
