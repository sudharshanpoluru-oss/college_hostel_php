<?php
require_once __DIR__ . '/../includes/session.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? BASE_URL . '/admin/dashboard.php' : BASE_URL . '/student/dashboard.php');
}

$error = '';
$selectedRole = $_GET['role'] ?? ($_POST['role'] ?? 'student');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? '';
    $remember = isset($_POST['remember']);

    requireCSRF();

    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password';
    } elseif (checkLoginAttempts($username)) {
        $error = 'Account temporarily locked due to too many failed attempts. Please try again after 15 minutes.';
    } else {
        $stmt = db()->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 1 AND role = ? LIMIT 1");
        $stmt->execute([$username, $username, $role]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_activity'] = time();
            $_SESSION['last_regeneration'] = time();
            clearLoginAttempts($username);
            logActivity($user['id'], 'Login', 'User logged in');

            if ($remember) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + 86400 * 30, '/', '', false, true);
                setcookie('remember_user', $user['id'], time() + 86400 * 30, '/', '', false, true);
                file_put_contents(sys_get_temp_dir() . '/remember_' . $token, $user['id']);
            }

            if ($user['role'] === 'admin') {
                setAlert('success', 'Welcome back, ' . sanitize($user['username']) . '!');
                redirect(BASE_URL . '/admin/dashboard.php');
            } elseif ($user['role'] === 'warden') {
                setAlert('success', 'Welcome back, ' . sanitize($user['username']) . '!');
                redirect(BASE_URL . '/warden/dashboard.php');
            } else {
                setAlert('success', 'Welcome back, ' . sanitize($user['username']) . '!');
                redirect(BASE_URL . '/student/dashboard.php');
            }
        } else {
            recordLoginAttempt($username);
            $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= SITE_NAME ?></title>
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
                <p class="text-white-50 small mb-0">Hostel administration, simplified.</p>
            </div>
            <div class="my-auto">
                <h1>Your hostel, fully in sync.</h1>
                <p class="lead-text mt-3">One portal for rooms, mess, fees and campus life — built for students, wardens and administrators alike.</p>
                <div class="mt-4">
                    <div class="auth-feature"><i class="bi bi-door-open"></i> Room allocations &amp; change requests</div>
                    <div class="auth-feature"><i class="bi bi-calendar-check"></i> Daily attendance &amp; roll call</div>
                    <div class="auth-feature"><i class="bi bi-cash-coin"></i> Transparent fee tracking</div>
                    <div class="auth-feature"><i class="bi bi-megaphone"></i> Instant notices &amp; alerts</div>
                </div>
            </div>
            <div class="small text-white-50">&copy; <?= date('Y') ?> <?= SITE_NAME ?></div>
        </aside>
        <main class="auth-form-side w-100">
            <div class="login-card">
                    <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
                        <div class="card-body p-5">
                            <div class="text-center mb-4">
                                <div class="bg-primary text-white d-inline-flex align-items-center justify-content-center rounded-3 p-3 mb-3" style="width:64px;height:64px">
                                    <i class="bi bi-building fs-3"></i>
                                </div>
                                <h4 class="fw-bold"><?= SITE_NAME ?></h4>
                                <p class="text-muted small">Sign in to your account</p>
                            </div>

                            <ul class="nav nav-pills nav-justified mb-4 gap-2" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?= $selectedRole === 'student' ? 'active' : '' ?>" type="button" onclick="switchRole('student')">
                                        <i class="bi bi-person"></i> Student
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?= $selectedRole === 'warden' ? 'active' : '' ?>" type="button" onclick="switchRole('warden')">
                                        <i class="bi bi-shield-check"></i> Warden
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?= $selectedRole === 'admin' ? 'active' : '' ?>" type="button" onclick="switchRole('admin')">
                                        <i class="bi bi-shield-lock"></i> Admin
                                    </button>
                                </li>
                            </ul>

                            <p class="text-muted text-center mb-4 small">
                                <?= $selectedRole === 'student' ? 'Sign in with your Roll Number and password' : 'Sign in with your admin credentials' ?>
                            </p>

                            <?php if ($error): ?>
                                <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle"></i> <?= $error ?></div>
                            <?php endif; ?>
                            <?php if (isset($_GET['expired'])): ?>
                                <div class="alert alert-warning py-2 small"><i class="bi bi-clock"></i> Session expired. Please login again.</div>
                            <?php endif; ?>
                            <?php if (isset($_GET['registered'])): ?>
                                <div class="alert alert-success py-2 small"><i class="bi bi-check-circle"></i> Registration successful! Please wait for admin approval.</div>
                            <?php endif; ?>

                            <form method="POST">
                                <input type="hidden" name="role" id="roleInput" value="<?= $selectedRole ?>">
                                <?= csrfField() ?>
                                <div class="mb-3">
                                    <label class="form-label small fw-medium"><?= $selectedRole === 'student' ? 'Roll Number' : 'Username / Email' ?></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                                        <input type="text" name="username" class="form-control" placeholder="<?= $selectedRole === 'student' ? 'Enter your roll number' : 'Enter username or email' ?>" required autofocus>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <label class="form-label small fw-medium">Password</label>
                                        <a href="<?= BASE_URL ?>/auth/forgot-password.php" class="small text-decoration-none">Forgot?</a>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                        <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                                        <label class="form-check-label small" for="remember">Remember me</label>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                                    <i class="bi bi-box-arrow-in-right"></i> Sign In
                                </button>
                            </form>

                            <?php if ($selectedRole === 'student'): ?>
                            <div class="text-center mt-3">
                                <p class="mb-0 small">Don't have an account? <a href="<?= BASE_URL ?>/auth/register.php">Register here</a></p>
                            </div>
                            <?php endif; ?>
                            <div class="text-center mt-2">
                                <a href="<?= BASE_URL ?>/public/index.php" class="text-decoration-none small">
                                    <i class="bi bi-arrow-left"></i> Back to Home
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
        </main>
    </div>
    <script>
    function switchRole(role) {
        document.getElementById('roleInput').value = role;
        document.querySelectorAll('.nav-pills .nav-link').forEach(function(btn) { btn.classList.remove('active'); });
        event.target.closest('.nav-link').classList.add('active');
        var info = document.querySelector('.text-muted.text-center');
        if (info) info.textContent = role === 'student' ? 'Sign in with your Roll Number and password' : 'Sign in with your credentials';
        var labels = document.querySelectorAll('label');
        for (var i = 0; i < labels.length; i++) {
            if (labels[i].textContent.trim() === 'Roll Number' || labels[i].textContent.trim() === 'Username / Email') {
                labels[i].textContent = role === 'student' ? 'Roll Number' : 'Username / Email';
                break;
            }
        }
        var inp = document.querySelector('input[name="username"]');
        if (inp) inp.placeholder = role === 'student' ? 'Enter your roll number' : 'Enter username or email';
        var regLink = document.querySelector('.text-center.mt-3 p');
        if (regLink && regLink.parentElement) regLink.parentElement.style.display = role === 'student' ? '' : 'none';
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
