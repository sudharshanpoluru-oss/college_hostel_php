<?php
require_once __DIR__ . '/../includes/session.php';

if (!isLoggedIn() || !isStudent()) {
    redirect(BASE_URL . '/auth/login.php');
}

$stmt = db()->prepare("SELECT * FROM students WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

if (!$student) {
    setAlert('danger', 'Student record not found.');
    $title = 'Leave Request';
    require_once __DIR__ . '/../includes/student-header.php';
    displayAlert();
    require_once __DIR__ . '/../includes/student-footer.php';
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $from_date = $_POST['from_date'] ?? '';
    $to_date = $_POST['to_date'] ?? '';
    $reason = sanitize($_POST['reason'] ?? '');

    if (empty($from_date)) $errors[] = 'From date is required';
    if (empty($to_date)) $errors[] = 'To date is required';
    if (empty($reason)) $errors[] = 'Reason is required';
    if ($from_date && $to_date && $from_date > $to_date) $errors[] = 'From date cannot be after to date';
    if (empty($errors)) {
        $stmt = db()->prepare("INSERT INTO leaves (student_id, from_date, to_date, reason) VALUES (?, ?, ?, ?)");
        $stmt->execute([$student['id'], $from_date, $to_date, $reason]);
        setAlert('success', 'Leave request submitted successfully. Awaiting admin approval.');
        redirect(BASE_URL . '/student/leave.php');
    }
}

$stmt = db()->prepare("SELECT * FROM leaves WHERE student_id = ? ORDER BY applied_at DESC");
$stmt->execute([$student['id']]);
$leaves = $stmt->fetchAll();

$title = 'Leave Request';
require_once __DIR__ . '/../includes/student-header.php';
?>

<div class="container-fluid">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?><div><?= $e ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header"><strong>Apply for Leave</strong></div>
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">From Date</label>
                            <input type="date" name="from_date" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">To Date</label>
                            <input type="date" name="to_date" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea name="reason" class="form-control" rows="4" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Submit Request</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card">
                <div class="card-header"><strong>My Leave Requests</strong></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr><th>From</th><th>To</th><th>Reason</th><th>Status</th><th>Admin Remark</th><th>Applied</th></tr>
                            </thead>
                            <tbody>
                                <?php if ($leaves): ?>
                                    <?php foreach ($leaves as $l): ?>
                                        <tr>
                                            <td><?= $l['from_date'] ?></td>
                                            <td><?= $l['to_date'] ?></td>
                                            <td><?= htmlspecialchars($l['reason']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $l['status'] == 'Approved' ? 'success' : ($l['status'] == 'Rejected' ? 'danger' : 'warning') ?>">
                                                    <?= $l['status'] ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($l['admin_remark'] ?? '--') ?></td>
                                            <td><?= date('d M Y', strtotime($l['applied_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center text-muted">No leave requests yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/student-footer.php'; ?>
