<?php
require_once __DIR__ . '/../includes/session.php';
requireLogin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($current) || empty($new) || empty($confirm)) {
        $error = 'All fields are required';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match';
    } elseif (strlen($new) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        $stmt = db()->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!password_verify($current, $user['password'])) {
            $error = 'Current password is incorrect';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = db()->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $_SESSION['user_id']]);
            $success = 'Password changed successfully';
            logActivity($_SESSION['user_id'], 'Password Change', 'Password changed');
        }
    }
}

$backLink = isAdmin() ? BASE_URL . '/admin/profile.php' : BASE_URL . '/student/profile.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-key"></i> Change Password</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
                        <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Update Password</button>
                        </form>
                    </div>
                    <div class="card-footer">
                        <a href="<?= $backLink ?>" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Back</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
