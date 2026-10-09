<?php
$title = 'Manage Wardens';
require_once __DIR__ . '/../includes/admin-header.php';

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $email = sanitize($_POST['email']);
    $shift = sanitize($_POST['shift'] ?? 'Day');
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $auto_user  = isset($_POST['auto_username']);
    $auto_pass  = isset($_POST['auto_password']);

    $photo = '';
    if (!empty($_FILES['photo']['name'])) {
        $ext   = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $photo = uniqid('warden_') . '.' . $ext;
        move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/../uploads/' . $photo);
    }

    $hostel_type = sanitize($_POST['hostel_type'] ?? '');

    try {
        if ($action === 'add') {
            if ($auto_user) {
                $username = 'wdn_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name)) . '_' . rand(100, 999);
            }
            if ($auto_pass || empty($password)) {
                $password = bin2hex(random_bytes(4));
            }
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            if (empty($username)) $username = $auto_user ? $username : 'wdn_' . rand(10000, 99999);

            $stmt = db()->prepare("INSERT INTO users (username, email, password, role, status, approved) VALUES (?, ?, ?, 'warden', 1, 1)");
            $stmt->execute([$username, $email, $hashed]);
            $userId = db()->lastInsertId();

            $stmt = db()->prepare("INSERT INTO wardens (user_id, name, phone, email, photo, shift, hostel_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$userId, $name, $phone, $email, $photo, $shift, $hostel_type]);

            auditLog('Create Warden', 'Wardens', "Created warden: $name (username: $username)");
            setAlert('success', "Warden added. Username: $username, Password: $password");
        } else {
            $existing = db()->prepare("SELECT w.photo, w.user_id FROM wardens w WHERE w.id=?");
            $existing->execute([$id]);
            $old = $existing->fetch();
            if (!$old) { setAlert('danger', 'Warden not found'); redirect(BASE_URL . '/admin/wardens.php'); }
            if (!$photo) $photo = $old['photo'];

            $hostel_type = sanitize($_POST['hostel_type'] ?? '');

            $stmt = db()->prepare("UPDATE wardens SET name=?, phone=?, email=?, photo=?, shift=?, hostel_type=? WHERE id=?");
            $stmt->execute([$name, $phone, $email, $photo, $shift, $hostel_type, $id]);

            $newPassword = '';
            if ($auto_pass || (!empty($password) && !$auto_pass)) {
                $password = $auto_pass ? bin2hex(random_bytes(4)) : $password;
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                db()->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hashed, $old['user_id']]);
                $newPassword = $auto_pass ? " New password: $password." : ' Password changed.';
            }

            if ($auto_user) {
                $username = 'wdn_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name)) . '_' . rand(100, 999);
            }

            if (!empty($username)) {
                db()->prepare("UPDATE users SET username=?, email=? WHERE id=?")->execute([$username, $email, $old['user_id']]);
            } else {
                db()->prepare("UPDATE users SET email=? WHERE id=?")->execute([$email, $old['user_id']]);
            }

            auditLog('Update Warden', 'Wardens', "Updated warden: $name (ID: $id)");
            setAlert('success', 'Warden updated.' . $newPassword);
        }
        redirect(BASE_URL . '/admin/wardens.php');
    } catch (PDOException $e) {
        setAlert('danger', 'Error: ' . $e->getMessage());
    }
}

if ($action === 'toggle_status' && $id) {
    $stmt = db()->prepare("SELECT w.status, w.name, w.user_id FROM wardens w WHERE w.id=?");
    $stmt->execute([$id]);
    $w = $stmt->fetch();
    if ($w) {
        $newStatus = $w['status'] ? 0 : 1;
        db()->prepare("UPDATE wardens SET status=? WHERE id=?")->execute([$newStatus, $id]);
        db()->prepare("UPDATE users SET status=? WHERE id=?")->execute([$newStatus, $w['user_id']]);
        auditLog('Toggle Warden Status', 'Wardens', "Toggled warden: {$w['name']} to " . ($newStatus ? 'Active' : 'Inactive'));
        setAlert('success', 'Warden status updated.');
    }
    redirect(BASE_URL . '/admin/wardens.php');
}

if ($action === 'delete' && $id) {
    $stmt = db()->prepare("SELECT w.name, w.user_id FROM wardens w WHERE w.id=?");
    $stmt->execute([$id]);
    $w = $stmt->fetch();
    if ($w) {
        $stmt = db()->prepare("SELECT COUNT(*) FROM leaves WHERE warden_id=? AND (warden_approved IS NULL OR warden_approved=0)");
        $stmt->execute([$w['user_id']]);
        if ($stmt->fetchColumn() > 0) {
            setAlert('danger', 'Cannot delete warden with pending leave approvals.');
        } else {
            db()->prepare("DELETE FROM users WHERE id=?")->execute([$w['user_id']]);
            auditLog('Delete Warden', 'Wardens', "Deleted warden: {$w['name']}");
            setAlert('success', 'Warden deleted.');
        }
    }
    redirect(BASE_URL . '/admin/wardens.php');
}

$page    = (int)($_GET['p'] ?? 1);
$perPage = 10;

$total = db()->query("SELECT COUNT(*) FROM wardens")->fetchColumn();
$pages = paginate($page, $perPage, $total);
$offset = ($page - 1) * $perPage;

$wardens = db()->query("SELECT w.*, u.username FROM wardens w JOIN users u ON u.id = w.user_id ORDER BY w.id DESC LIMIT $perPage OFFSET $offset")->fetchAll();
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0">Wardens</h4>
        <a href="?action=add" class="btn btn-primary btn-sm">+ Add Warden</a>
    </div>

    <?php if ($action === 'add' || ($action === 'edit' && $id)):
        $w = ['name'=>'', 'phone'=>'', 'email'=>'', 'photo'=>'', 'shift'=>'Day', 'hostel_type'=>'', 'username'=>''];
        if ($action === 'edit') {
            $stmt = db()->prepare("SELECT w.*, u.username FROM wardens w JOIN users u ON u.id = w.user_id WHERE w.id=?");
            $stmt->execute([$id]);
            $w = $stmt->fetch();
            if (!$w) { setAlert('danger','Not found'); redirect(BASE_URL.'/admin/wardens.php'); }
        }
    ?>
    <div class="card">
        <div class="card-header"><strong><?= $action==='add'?'Add':'Edit' ?> Warden</strong></div>
        <div class="card-body">
            <form method="post" action="<?= $action === 'edit' ? '?action=edit&id=' . $id : '?action=add' ?>" enctype="multipart/form-data" class="row g-3"><?= csrfField() ?>
                <div class="col-md-6"><label>Warden Name</label><input type="text" name="name" class="form-control" value="<?= sanitize($w['name']) ?>" required></div>
                <div class="col-md-3"><label>Phone</label><input type="text" name="phone" class="form-control" value="<?= sanitize($w['phone']) ?>"></div>
                <div class="col-md-3"><label>Email</label><input type="email" name="email" class="form-control" value="<?= sanitize($w['email']) ?>"></div>
                <div class="col-md-4"><label>Photo</label><input type="file" name="photo" class="form-control"><?php if($w['photo']): ?><br><img src="<?= BASE_URL ?>/uploads/<?= $w['photo'] ?>" height="60"><?php endif; ?></div>
                <div class="col-md-2"><label>Shift</label><select name="shift" class="form-select"><option value="Day" <?= $w['shift']=='Day'?'selected':'' ?>>Day</option><option value="Night" <?= $w['shift']=='Night'?'selected':'' ?>>Night</option></select></div>
                <div class="col-md-2"><label>Hostel</label><select name="hostel_type" class="form-select"><option value="">Select</option><option value="boys" <?= $w['hostel_type']=='boys'?'selected':'' ?>>Boys Hostel</option><option value="girls" <?= $w['hostel_type']=='girls'?'selected':'' ?>>Girls Hostel</option></select></div>
                <div class="col-12"><hr><h6>Login Credentials</h6></div>
                <?php if ($action === 'add'): ?>
                <div class="col-md-4">
                    <label>Username</label>
                    <div class="input-group">
                        <input type="text" name="username" class="form-control" value="<?= sanitize($w['username']) ?>" placeholder="Auto-generate">
                        <div class="input-group-text"><input type="checkbox" name="auto_username" checked onchange="this.previousElementSibling.previousElementSibling.disabled=this.checked"> Auto</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <label>Password</label>
                    <div class="input-group">
                        <input type="text" name="password" class="form-control" value="" placeholder="Auto-generate">
                        <div class="input-group-text"><input type="checkbox" name="auto_password" checked onchange="this.previousElementSibling.previousElementSibling.disabled=this.checked"> Auto</div>
                    </div>
                </div>
                <?php else: ?>
                <div class="col-md-4">
                    <label>Username</label>
                    <div class="input-group">
                        <input type="text" name="username" class="form-control" value="<?= sanitize($w['username']) ?>">
                        <div class="input-group-text"><input type="checkbox" name="auto_username" onchange="this.previousElementSibling.previousElementSibling.disabled=this.checked"> Auto</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <label>New Password <small class="text-muted">(leave blank to keep)</small></label>
                    <input type="text" name="password" class="form-control" placeholder="Enter new password">
                    <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="auto_password" id="autoPass" onchange="this.previousElementSibling.previousElementSibling.disabled=this.checked"><label class="form-check-label">Auto-generate</label></div>
                </div>
                <?php endif; ?>
                <div class="col-12 mt-3">
                    <button class="btn btn-primary"><?= $action==='add'?'Add':'Update' ?> Warden</button>
                    <a href="<?= BASE_URL ?>/admin/wardens.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php else: ?>
    <table class="table table-bordered table-striped">
        <thead><tr><th>Photo</th><th>Name</th><th>Username</th><th>Phone</th><th>Email</th><th>Shift</th><th>Hostel</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($wardens as $w): ?>
            <tr>
                <td><?php if ($w['photo']): ?><img src="<?= BASE_URL ?>/uploads/<?= $w['photo'] ?>" height="40" class="rounded"><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                <td><?= sanitize($w['name']) ?></td>
                <td><?= sanitize($w['username']) ?></td>
                <td><?= sanitize($w['phone']) ?></td>
                <td><?= sanitize($w['email']) ?></td>
                <td><span class="badge bg-<?= $w['shift']=='Night'?'dark':'info' ?>"><?= $w['shift'] ?></span></td>
                <td><span class="badge bg-<?= ($w['hostel_type'] ?? '')=='boys'?'primary':'danger' ?>"><?= isset($w['hostel_type']) && $w['hostel_type'] ? ucfirst($w['hostel_type']) : '—' ?></span></td>
                <td><span class="badge bg-<?= $w['status']?'success':'secondary' ?>"><?= $w['status']?'Active':'Inactive' ?></span></td>
                <td><?= $w['created_at'] ?></td>
                <td class="text-nowrap">
                    <a href="?action=edit&id=<?= $w['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                    <a href="?action=toggle_status&id=<?= $w['id'] ?>" class="btn btn-sm btn-<?= $w['status']?'secondary':'success' ?>"><?= $w['status']?'Deactivate':'Activate' ?></a>
                    <a href="?action=delete&id=<?= $w['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete warden <?= sanitize($w['name']) ?>? This will also remove the user account.')">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$wardens): ?><tr><td colspan="10" class="text-center text-muted">No wardens found.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?= paginationLinks($page, $pages) ?>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('input[type=checkbox][name=auto_username], input[type=checkbox][name=auto_password]').forEach(cb => {
    cb.addEventListener('change', function() {
        const input = this.closest('.input-group')?.querySelector('input[type=text]');
        if (input) input.disabled = this.checked;
        if (this.name === 'auto_password') {
            this.closest('.col-md-4')?.querySelector('input[type=text]')?.setAttribute('placeholder', this.checked ? 'Auto-generate' : 'Enter password');
        }
    });
    cb.dispatchEvent(new Event('change'));
});
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
