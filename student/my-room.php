<?php
require_once __DIR__ . '/../includes/session.php';

if (!isLoggedIn() || !isStudent()) {
    redirect(BASE_URL . '/auth/login.php');
}

$studentStmt = db()->prepare("SELECT * FROM students WHERE user_id = ?");
$studentStmt->execute([$_SESSION['user_id']]);
$student = $studentStmt->fetch();

$allocStmt = db()->prepare("SELECT ra.*, r.room_no, r.floor, r.room_type, r.fee_per_month, r.status as room_status FROM room_allocations ra JOIN rooms r ON ra.room_id = r.id WHERE ra.student_id = ? AND ra.status = 'Active' LIMIT 1");
$allocStmt->execute([$student['id']]);
$allocation = $allocStmt->fetch();

$title = 'My Room';
require_once __DIR__ . '/../includes/student-header.php';
?>

<div class="container-fluid">
    <h3 class="mb-4">My Room</h3>

    <?php if ($allocation): ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Room Allocation Details</strong>
                <a href="<?= BASE_URL ?>/student/room-change.php" class="btn btn-sm btn-warning">Request Change</a>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr><th>Room Number</th><td><?= htmlspecialchars($allocation['room_no']) ?></td></tr>
                    <tr><th>Bed Number</th><td><?= htmlspecialchars($allocation['bed_no']) ?></td></tr>
                    <tr><th>Room Type</th><td><?= htmlspecialchars($allocation['room_type']) ?></td></tr>
                    <tr><th>Floor</th><td><?= htmlspecialchars($allocation['floor']) ?></td></tr>
                    <tr><th>Monthly Fee</th><td>₹<?= number_format($allocation['fee_per_month']) ?></td></tr>
                    <tr><th>Allocation Date</th><td><?= date('d M Y', strtotime($allocation['allocation_date'])) ?></td></tr>
                    <tr><th>Room Status</th><td><span class="badge bg-<?= $allocation['room_status'] == 'Active' ? 'success' : 'secondary' ?>"><?= $allocation['room_status'] ?></span></td></tr>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> No room has been allocated to you yet.
            <a href="<?= BASE_URL ?>/student/complaints.php" class="alert-link">Contact Admin</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/student-footer.php'; ?>
