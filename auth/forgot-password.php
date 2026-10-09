<?php
require_once __DIR__ . '/../includes/session.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? BASE_URL . '/admin/dashboard.php' : BASE_URL . '/student/dashboard.php');
}

$step = isset($_GET['token']) ? 'reset' : (isset($_POST['email']) ? 'process' : 'request');
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();

    if ($step === 'process') {
        $email = sanitize($_POST['email'] ?? '');
        if (empty($email) || !validateEmail($email)) {
            $error = 'Please enter a valid email address';
        } else {
            $stmt = db()->prepare("SELECT id, username FROM users WHERE email = ? AND status = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user) {
                $token = bin2hex(random_bytes(32));
                $stmt = db()->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
                $stmt->execute([$user['id'], $token]);
                $resetLink = BASE_URL . '/auth/forgot-password.php?token=' . $token;
                $success = 'Password reset link has been sent to your email.<br><small class="text-muted">(Demo: <a href="' . $resetLink . '">' . $resetLink . '</a>)</small>';
            } else {
                $success = 'If the email is registered, you will receive a password reset link.';
            }
        }
    } elseif ($step === 'reset') {
        $token = $_GET['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = db()->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW()");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();

        if (!$reset) {
            $error = 'Invalid or expired reset token';
        } elseif (empty($password)) {
            $error = 'Please enter a new password';
        } else {
            $pwErrors = validatePassword($password);
            if ($pwErrors) {
                $error = 'Password must contain: ' . implode(', ', $pwErrors);
            } elseif ($password !== $confirm) {
                $error = 'Passwords do not match';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                db()->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $reset['user_id']]);
                db()->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")->execute([$reset['id']]);
                $success = 'Password has been reset successfully. You can now login with your new password.';
                $step = 'done';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=6">
</head>
<body>
    <div class="auth-shell">
        <aside class="auth-brand d-none d-lg-flex">
            <div>
                <div class="brand-logo mb-3"><i class="bi bi-building"></i></div>
                <h4 class="fw-bold mb-1"><?= SITE_NAME ?></h4>
                <p class="text-white-50 small mb-0">Account recovery, made easy.</p>
            </div>
            <div class="my-auto">
                <h1>Locked out? No problem.</h1>
                <p class="lead-text mt-3">Enter your registered email and we'll send you a secure link to reset your password.</p>
            </div>
            <div class="small text-white-50">&copy; <?= date('Y') ?> <?= SITE_NAME ?></div>
        </aside>
        <main class="auth-form-side w-100">
                <div class="login-card">
                    <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
                        <div class="card-body p-5">
                            <div class="text-center mb-4">
                                <div class="bg-primary text-white d-inline-flex align-items-center justify-content-center rounded-3 p-3 mb-3" style="width:64px;height:64px">
                                    <i class="bi bi-key fs-3"></i>
                                </div>
                                <h4 class="fw-bold"><?= $step === 'reset' ? 'Reset Password' : 'Forgot Password' ?></h4>
                                <p class="text-muted small">
                                    <?= $step === 'request' ? 'Enter your email to receive a reset link' : ($step === 'reset' ? 'Enter your new password' : '') ?>
                                </p>
                            </div>

                            <?php if ($error): ?>
                                <div class="alert alert-danger py-2 small"><?= $error ?></div>
                            <?php endif; ?>
                            <?php if ($success): ?>
                                <div class="alert alert-success py-2 small"><?= $success ?></div>
                                <div class="text-center mt-3">
                                    <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-primary">Login Now</a>
                                </div>
                            <?php endif; ?>

                            <?php if (!$success): ?>
                                <?php if ($step === 'request' || $step === 'process'): ?>
                                <form method="POST">
                                    <?= csrfField() ?>
                                    <div class="mb-4">
                                        <label class="form-label small fw-medium">Email Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                            <input type="email" name="email" class="form-control" placeholder="Enter your registered email" required autofocus>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                                        <i class="bi bi-send"></i> Send Reset Link
                                    </button>
                                </form>
                                <?php elseif ($step === 'reset'): ?>
                                <form method="POST">
                                    <?= csrfField() ?>
                                    <div class="mb-3">
                                        <label class="form-label small fw-medium">New Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                            <input type="password" name="password" class="form-control" placeholder="Min 8 characters" required minlength="8">
                                        </div>
                                        <div class="form-text">Min 8 chars, 1 uppercase, 1 number, 1 special char</div>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label small fw-medium">Confirm Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                            <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required minlength="8">
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                                        <i class="bi bi-check-circle"></i> Reset Password
                                    </button>
                                </form>
                                <?php endif; ?>
                            <?php endif; ?>

                            <div class="text-center mt-3">
                                <a href="<?= BASE_URL ?>/auth/login.php" class="text-decoration-none small">
                                    <i class="bi bi-arrow-left"></i> Back to Login
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
