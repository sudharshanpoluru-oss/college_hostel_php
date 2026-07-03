<?php
require_once __DIR__ . '/../includes/session.php';

if (!isLoggedIn() || !isStudent()) {
    redirect(BASE_URL . '/auth/login.php');
}

$stmt = db()->prepare("SELECT * FROM students WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $guardian_phone = sanitize($_POST['guardian_phone']);

    $updateStmt = db()->prepare("UPDATE students SET email = ?, phone = ?, address = ?, guardian_phone = ? WHERE user_id = ?");
    $updateStmt->execute([$email, $phone, $address, $guardian_phone, $_SESSION['user_id']]);

    setAlert('success', 'Profile updated successfully.');
    redirect('profile.php');
}

$title = 'My Profile';
require_once __DIR__ . '/../includes/student-header.php';
?>

<div class="container-fluid">
    <h3 class="mb-4">My Profile</h3>

    <?= displayAlert() ?>

    <div class="row g-4">
        <div class="col-md-4 text-center">
            <div class="card">
                <div class="card-body">
                    <?php if ($student['photo']): ?>
                        <img src="<?= BASE_URL ?>/uploads/<?= $student['photo'] ?>" alt="Photo" class="img-fluid rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;">
                    <?php else: ?>
                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 150px; height: 150px;">
                            <span class="display-3 text-white"><?= strtoupper(substr($student['name'], 0, 1)) ?></span>
                        </div>
                    <?php endif; ?>
                    <h5><?= htmlspecialchars($student['name']) ?></h5>
                    <span class="badge bg-<?= $student['status'] == 'Active' ? 'success' : 'secondary' ?>"><?= $student['status'] ?></span>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Profile Details</strong>
                    <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#editProfileForm" aria-expanded="false">
                        Edit Profile
                    </button>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tr><th>Name</th><td><?= htmlspecialchars($student['name']) ?></td></tr>
                        <tr><th>Roll No</th><td><?= htmlspecialchars($student['roll_no']) ?></td></tr>
                        <tr><th>Email</th><td><?= htmlspecialchars($student['email']) ?></td></tr>
                        <tr><th>Phone</th><td><?= htmlspecialchars($student['phone']) ?></td></tr>
                        <tr><th>Address</th><td><?= htmlspecialchars($student['address']) ?></td></tr>
                        <tr><th>Gender</th><td><?= htmlspecialchars($student['gender']) ?></td></tr>
                        <tr><th>Course</th><td><?= htmlspecialchars($student['course']) ?></td></tr>
                        <tr><th>Year</th><td><?= htmlspecialchars($student['year']) ?></td></tr>
                        <tr><th>Guardian Name</th><td><?= htmlspecialchars($student['guardian_name']) ?></td></tr>
                        <tr><th>Guardian Phone</th><td><?= htmlspecialchars($student['guardian_phone']) ?></td></tr>
                        <tr><th>Admission Date</th><td><?= date('d M Y', strtotime($student['admission_date'])) ?></td></tr>
                        <tr><th>Join Date</th><td><?= date('d M Y', strtotime($student['join_date'])) ?></td></tr>
                    </table>
                </div>
            </div>

            <div class="collapse mb-4" id="editProfileForm">
                <div class="card">
                    <div class="card-header"><strong>Edit Profile</strong></div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($student['email']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($student['phone']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($student['address']) ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Guardian Phone</label>
                                <input type="text" name="guardian_phone" class="form-control" value="<?= htmlspecialchars($student['guardian_phone']) ?>">
                            </div>
                            <button type="submit" class="btn btn-success">Save Changes</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="text-end">
                <a href="<?= BASE_URL ?>/auth/change-password.php" class="btn btn-outline-secondary">Change Password</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.location.hash === '#edit') {
        document.getElementById('editProfileForm').classList.add('show');
    }
});
</script>

<?php require_once __DIR__ . '/../includes/student-footer.php'; ?>
