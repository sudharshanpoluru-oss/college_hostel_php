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
    $title = 'Vacate Request';
    require_once __DIR__ . '/../includes/student-header.php';
    displayAlert();
    require_once __DIR__ . '/../includes/student-footer.php';
    exit;
}

$alloc = db()->prepare("SELECT ra.*, r.room_no, r.room_type FROM room_allocations ra JOIN rooms r ON r.id=ra.room_id WHERE ra.student_id=? AND ra.status='Active'");
$alloc->execute([$student['id']]);
$allocation = $alloc->fetch();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $reason = sanitize($_POST['reason'] ?? '');

    if (empty($reason)) $errors[] = 'Reason is required';
    if (empty($allocation)) $errors[] = 'You do not have an active room allocation.';

    if (empty($errors)) {
        $stmt = db()->prepare("INSERT INTO vacate_requests (student_id, reason) VALUES (?, ?)");
        $stmt->execute([$student['id'], $reason]);
        setAlert('success', 'Vacate request submitted. Awaiting admin approval.');
        redirect(BASE_URL . '/student/vacate.php');
    }
}

$stmt = db()->prepare("SELECT * FROM vacate_requests WHERE student_id = ? ORDER BY applied_at DESC");
$stmt->execute([$student['id']]);
$requests = $stmt->fetchAll();

$title = 'Vacate Request';
require_once __DIR__ . '/../includes/student-header.php';
?>

<div class="container-fluid">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?><div><?= $e ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($allocation): ?>
    <div class="alert alert-info">
        <strong>Your Room:</strong> <?= sanitize($allocation['room_no']) ?> (<?= $allocation['room_type'] ?>)
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header"><strong>Request to Vacate Hostel</strong></div>
                <div class="card-body">
                    <?php if ($allocation): ?>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Reason for Leaving</label>
                            <textarea name="reason" class="form-control" rows="4" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger w-100"><i class="bi bi-house-x"></i> Submit Vacate Request</button>
                    </form>
                    <?php else: ?>
                    <p class="text-muted mb-0">You have no active room allocation.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card">
                <div class="card-header"><strong>My Vacate Requests</strong></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr><th>Reason</th><th>Status</th><th>Admin Remark</th><th>Applied</th></tr>
                            </thead>
                            <tbody>
                                <?php if ($requests): ?>
                                    <?php foreach ($requests as $r): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($r['reason']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $r['status'] == 'Approved' ? 'success' : ($r['status'] == 'Rejected' ? 'danger' : 'warning') ?>">
                                                    <?= $r['status'] ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($r['admin_remark'] ?? '--') ?></td>
                                            <td><?= date('d M Y', strtotime($r['applied_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted">No vacate requests yet.</td></tr>
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
