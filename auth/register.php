<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/mailer.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? BASE_URL . '/admin/dashboard.php' : BASE_URL . '/student/dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $name              = sanitize($_POST['name'] ?? '');
    $roll_no           = sanitize($_POST['roll_no'] ?? '');
    $email             = sanitize($_POST['email'] ?? '');
    $phone             = sanitize($_POST['phone'] ?? '');
    $address           = sanitize($_POST['address'] ?? '');
    $gender            = sanitize($_POST['gender'] ?? '');
    $blood_group       = sanitize($_POST['blood_group'] ?? '');
    $course            = sanitize($_POST['course'] ?? '');
    $department        = sanitize($_POST['department'] ?? '');
    $year              = sanitize($_POST['year'] ?? '');
    $admission_year    = sanitize($_POST['admission_year'] ?? '');
    $guardian_name     = sanitize($_POST['guardian_name'] ?? '');
    $guardian_phone    = sanitize($_POST['guardian_phone'] ?? '');
    $emergency_name    = sanitize($_POST['emergency_contact_name'] ?? '');
    $emergency_phone   = sanitize($_POST['emergency_contact'] ?? '');
    $medical_info      = sanitize($_POST['medical_info'] ?? '');
    $agree             = isset($_POST['agree']);
    $photo = '';

    $errors = [];
    if (empty($name)) $errors[] = 'Name is required';
    if (empty($roll_no)) $errors[] = 'Roll Number is required';
    if (empty($email)) $errors[] = 'Email is required';
    elseif (!validateEmail($email)) $errors[] = 'Please enter a valid email';
    if (empty($phone)) $errors[] = 'Phone number is required';
    elseif (!preg_match('/^[0-9]{10}$/', $phone)) $errors[] = 'Phone number must be exactly 10 digits';
    if (!empty($guardian_phone) && !preg_match('/^[0-9]{10}$/', $guardian_phone)) $errors[] = 'Guardian phone must be exactly 10 digits';
    if (empty($gender)) $errors[] = 'Gender is required';
    if (empty($_FILES['photo']['name'])) $errors[] = 'Photo is required';
    if (!$agree) $errors[] = 'You must agree to the terms';

    if (count($errors) === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) $errors[] = 'Photo must be jpg, jpeg, png, or gif';
        elseif ($_FILES['photo']['size'] > 2 * 1024 * 1024) $errors[] = 'Photo must be less than 2MB';
        else { $photo = uniqid('stu_') . '.' . $ext; move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/../uploads/' . $photo); }
    }

    if (count($errors) === 0) {
        try {
            $check = db()->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check->execute([$roll_no, $email]);
            if ($check->fetch()) {
                $error = 'Roll Number or Email already registered';
            } else {
                $sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghjkmnpqrstuvwxyz', '23456789', '!@#$%&*?'];
                $password = '';
                foreach ($sets as $set) $password .= $set[random_int(0, strlen($set) - 1)];
                $all = implode('', $sets);
                while (strlen($password) < 12) $password .= $all[random_int(0, strlen($all) - 1)];
                $chars = str_split($password);
                for ($i = count($chars) - 1; $i > 0; $i--) { $j = random_int(0, $i); [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]]; }
                $password = implode('', $chars);

                db()->beginTransaction();
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = db()->prepare("INSERT INTO users (username, email, password, role, status, approved) VALUES (?, ?, ?, 'student', 1, 0)");
                $stmt->execute([$roll_no, $email, $hashed]);
                $userId = db()->lastInsertId();

                $hostel_type = $gender === 'Female' ? 'girls' : 'boys';
                $stmt = db()->prepare("INSERT INTO students (user_id, name, roll_no, email, phone, address, gender, blood_group, course, department, year, admission_year, guardian_name, guardian_phone, emergency_contact_name, emergency_contact, medical_info, photo, admission_date, join_date, status, hostel_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURDATE(), 'Active', ?)");
                $stmt->execute([$userId, $name, $roll_no, $email, $phone, $address, $gender, $blood_group, $course, $department, $year, $admission_year, $guardian_name, $guardian_phone, $emergency_name, $emergency_phone, $medical_info, $photo, $hostel_type]);
                db()->commit();

                addNotification($userId, 'Registration Submitted', 'Your registration has been submitted for admin approval.', 'info');

                $loginUrl = BASE_URL . '/auth/login.php?role=student';
                $body = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto">'
                      . '<h2 style="color:#0d6efd">Welcome to ' . SITE_NAME . '</h2>'
                      . '<p>Hello <strong>' . htmlspecialchars($name) . '</strong>,</p>'
                      . '<p>Your account has been registered successfully. Here are your login credentials:</p>'
                      . '<table cellpadding="8" style="background:#f8f9fa;border-radius:8px;width:100%">'
                      . '<tr><td><strong>Login ID (Roll No):</strong></td><td>' . htmlspecialchars($roll_no) . '</td></tr>'
                      . '<tr><td><strong>Email:</strong></td><td>' . htmlspecialchars($email) . '</td></tr>'
                      . '<tr><td><strong>Password:</strong></td><td style="font-family:monospace;font-size:16px"><strong>' . htmlspecialchars($password) . '</strong></td></tr>'
                      . '</table>'
                      . '<p style="margin-top:16px"><a href="' . $loginUrl . '" style="background:#198754;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none">Login Now</a></p>'
                      . '<p style="color:#6c757d;font-size:13px">Note: You can login once an admin approves your account.</p>'
                      . '</div>';

                if (sendMail($email, 'Your Hostel Account Password - ' . SITE_NAME, $body)) {
                    $success = 'Registration submitted! Your password has been sent to your email. An admin will review and approve your account.';
                } else {
                    $success = 'Registration submitted! However, we could not email your password. Please contact the admin office to get your login credentials.';
                }
            }
        } catch (Exception $e) {
            db()->rollBack();
            $error = 'Registration failed. Please try again.';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - <?= SITE_NAME ?></title>
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
                <p class="text-white-50 small mb-0">New student? You're in the right place.</p>
            </div>
            <div class="my-auto">
                <h1>Join your campus home.</h1>
                <p class="lead-text mt-3">Register once — your account password will be emailed to you after admin approval.</p>
                <div class="mt-4">
                    <div class="auth-feature"><i class="bi bi-person-check"></i> Simple one-page registration</div>
                    <div class="auth-feature"><i class="bi bi-envelope-check"></i> Secure password by email</div>
                    <div class="auth-feature"><i class="bi bi-shield-check"></i> Admin-verified accounts</div>
                </div>
            </div>
            <div class="small text-white-50">&copy; <?= date('Y') ?> <?= SITE_NAME ?></div>
        </aside>
        <main class="auth-form-side w-100">
                <div class="login-card" style="max-width:760px">
                    <div class="card shadow-lg border-0 rounded-4">
                        <div class="card-body p-5">
                            <div class="text-center mb-4">
                                <div class="bg-primary text-white d-inline-flex align-items-center justify-content-center rounded-3 p-3 mb-3" style="width:64px;height:64px">
                                    <i class="bi bi-person-plus fs-3"></i>
                                </div>
                                <h4 class="fw-bold">Student Registration</h4>
                                <p class="text-muted small">Fill in your details to create your hostel account</p>
                            </div>

                            <?php if ($success): ?>
                                <div class="alert alert-success text-center py-3"><i class="bi bi-check-circle"></i> <?= $success ?></div>
                                <div class="text-center mt-3">
                                    <a href="<?= BASE_URL ?>/auth/login.php?role=student" class="btn btn-primary">Login Now</a>
                                </div>
                            <?php else: ?>

                            <?php if ($error): ?>
                                <div class="alert alert-danger py-2 small"><?= $error ?></div>
                            <?php endif; ?>

                            <form method="POST" enctype="multipart/form-data">
                                <?= csrfField() ?>

                                <h6 class="fw-bold text-primary mb-3"><i class="bi bi-person"></i> Personal Information</h6>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                        <input type="text" name="name" class="form-control" value="<?= sanitize($_POST['name'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Roll Number <span class="text-danger">*</span></label>
                                        <input type="text" name="roll_no" class="form-control" value="<?= sanitize($_POST['roll_no'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" name="email" class="form-control" value="<?= sanitize($_POST['email'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Phone <span class="text-danger">*</span></label>
                                        <input type="text" name="phone" class="form-control" value="<?= sanitize($_POST['phone'] ?? '') ?>" required maxlength="10" pattern="[0-9]{10}" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Gender <span class="text-danger">*</span></label>
                                        <select name="gender" class="form-select" required>
                                            <option value="">Select</option>
                                            <option value="Male" <?= ($_POST['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                            <option value="Female" <?= ($_POST['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                            <option value="Other" <?= ($_POST['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Blood Group</label>
                                        <select name="blood_group" class="form-select">
                                            <option value="">Select</option>
                                            <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                                            <option value="<?= $bg ?>" <?= ($_POST['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Photo <span class="text-danger">*</span></label>
                                        <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/gif" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Address</label>
                                        <textarea name="address" class="form-control" rows="2"><?= sanitize($_POST['address'] ?? '') ?></textarea>
                                    </div>
                                </div>

                                <h6 class="fw-bold text-success mb-3"><i class="bi bi-book"></i> Academic Information</h6>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label">Course</label>
                                        <select name="course" class="form-select">
                                            <option value="">Select</option>
                                            <?php foreach (['B.Tech','B.E.','B.Sc','B.Com','BBA','BCA','B.A.','B.Pharm','M.Tech','M.E.','M.Sc','M.Com','MBA','MCA','M.A.','M.Pharm','Ph.D'] as $c): ?>
                                            <option value="<?= $c ?>" <?= ($_POST['course'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Department</label>
                                        <select name="department" class="form-select">
                                            <option value="">Select</option>
                                            <?php foreach (['Computer Science & Engineering','Information Technology','Electronics & Communication','Electrical & Electronics','Mechanical Engineering','Civil Engineering','Artificial Intelligence & Data Science','Biotechnology','Chemical Engineering','Pharmacy','MMT','Commerce','Business Administration','Arts & Humanities','Science'] as $d): ?>
                                            <option value="<?= $d ?>" <?= ($_POST['department'] ?? '') === $d ? 'selected' : '' ?>><?= $d ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Year</label>
                                        <select name="year" class="form-select">
                                            <option value="">Select</option>
                                            <?php for ($y = 1; $y <= 4; $y++): ?>
                                            <option value="<?= $y ?>" <?= ($_POST['year'] ?? '') == $y ? 'selected' : '' ?>>Year <?= $y ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Admission Year</label>
                                        <select name="admission_year" class="form-select">
                                            <option value="">Select</option>
                                            <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                            <option value="<?= $y ?>" <?= ($_POST['admission_year'] ?? '') == $y ? 'selected' : '' ?>><?= $y ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>

                                <h6 class="fw-bold text-warning mb-3"><i class="bi bi-shield"></i> Guardian & Emergency</h6>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label">Guardian Name</label>
                                        <input type="text" name="guardian_name" class="form-control" value="<?= sanitize($_POST['guardian_name'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Guardian Phone</label>
                                        <input type="text" name="guardian_phone" class="form-control" value="<?= sanitize($_POST['guardian_phone'] ?? '') ?>" maxlength="10" pattern="[0-9]{10}" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Medical Information</label>
                                        <textarea name="medical_info" class="form-control" rows="2" placeholder="Any medical conditions, allergies, or medications"><?= sanitize($_POST['medical_info'] ?? '') ?></textarea>
                                    </div>
                                </div>

                                <h6 class="fw-bold text-info mb-3"><i class="bi bi-envelope"></i> Account Details</h6>
                                <div class="alert alert-info py-2 small">
                                    <i class="bi bi-info-circle"></i> A secure password (uppercase, lowercase, number &amp; symbol) will be generated automatically and sent to your email after successful registration.
                                </div>

                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" name="agree" id="agree" required>
                                    <label class="form-check-label small" for="agree">
                                        I agree to the <a href="#" class="text-decoration-none">Terms & Conditions</a> and <a href="#" class="text-decoration-none">Hostel Rules</a>
                                    </label>
                                </div>

                                <button type="submit" class="btn btn-success w-100 py-2 fw-semibold">
                                    <i class="bi bi-person-plus"></i> Create Account
                                </button>
                            </form>

                            <div class="text-center mt-3">
                                <p class="mb-0 small">Already have an account? <a href="<?= BASE_URL ?>/auth/login.php?role=student">Login here</a></p>
                                <a href="<?= BASE_URL ?>/public/index.php" class="text-decoration-none small">
                                    <i class="bi bi-arrow-left"></i> Back to Home
                                </a>
                            </div>

                            <?php endif; ?>
                        </div>
                    </div>
                </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
