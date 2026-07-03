<?php
$title = 'My Profile';
require_once __DIR__ . '/../includes/admin-header.php';

$userId = $_SESSION['user_id'] ?? 0;

$stmt = db()->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    setAlert('danger', 'User not found.');
    redirect(BASE_URL . '/admin/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $email    = sanitize($_POST['email']);

    $stmt = db()->prepare("UPDATE users SET username=?, email=? WHERE id=?");
    $stmt->execute([$username, $email, $userId]);
    setAlert('success', 'Profile updated.');
    redirect(BASE_URL . '/admin/profile.php');
}
?>
<div class="container-fluid">
    <h4 class="mb-4">My Profile</h4>

    <div class="card mb-4">
        <div class="card-header"><strong>Profile Information</strong></div>
        <div class="card-body">
            <table class="table table-bordered">
                <tr><th>Username</th><td><?= sanitize($user['username']) ?></td></tr>
                <tr><th>Email</th><td><?= sanitize($user['email']) ?></td></tr>
                <tr><th>Role</th><td><span class="badge bg-primary"><?= sanitize($user['role']) ?></span></td></tr>
                <tr><th>Member Since</th><td><?= $user['created_at'] ?></td></tr>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Edit Profile</strong></div>
        <div class="card-body">
            <form method="post" action="" class="row g-3"><?= csrfField() ?>
                <div class="col-md-6"><label>Username</label><input type="text" name="username" class="form-control" value="<?= sanitize($user['username']) ?>" required></div>
                <div class="col-md-6"><label>Email</label><input type="email" name="email" class="form-control" value="<?= sanitize($user['email']) ?>" required></div>
                <div class="col-12">
                    <button class="btn btn-primary">Update Profile</button>
                    <a href="<?= BASE_URL ?>/auth/change-password.php" class="btn btn-outline-secondary">Change Password</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
