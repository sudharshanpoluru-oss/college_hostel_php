<?php
require_once __DIR__ . '/../includes/session.php';

if (!isLoggedIn() || !isStudent()) {
    redirect(BASE_URL . '/auth/login.php');
}

$stmt = db()->prepare("SELECT * FROM students WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

$stmt = db()->prepare("SELECT ra.*, r.room_no, r.room_type FROM room_allocations ra JOIN rooms r ON r.id = ra.room_id WHERE ra.student_id = ? AND ra.status = 'Active' LIMIT 1");
$stmt->execute([$student['id']]);
$current = $stmt->fetch();

if (!$current) {
    setAlert('danger', 'You do not have an active room allocation.');
    $title = 'Change Room';
    require_once __DIR__ . '/../includes/student-header.php';
    displayAlert();
    require_once __DIR__ . '/../includes/student-footer.php';
    exit;
}

$pendingStmt = db()->prepare("SELECT * FROM room_change_requests WHERE student_id = ? AND status = 'Pending' LIMIT 1");
$pendingStmt->execute([$student['id']]);
$hasPending = $pendingStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$hasPending) {
    $requested_room_id = (int)($_POST['room_id'] ?? 0);
    $reason = sanitize($_POST['reason'] ?? '');

    if ($requested_room_id && $reason) {
        $check = db()->prepare("SELECT * FROM rooms WHERE id = ? AND status = 'Available' AND capacity > occupancy");
        $check->execute([$requested_room_id]);
        $room = $check->fetch();

        if ($room) {
            $stmt = db()->prepare("INSERT INTO room_change_requests (student_id, current_room_id, requested_room_id, reason) VALUES (?, ?, ?, ?)");
            $stmt->execute([$student['id'], $current['room_id'], $requested_room_id, $reason]);
            setAlert('success', 'Room change request submitted. Awaiting admin approval.');
            redirect(BASE_URL . '/student/room-change.php');
        } else {
            $error = 'Selected room is not available.';
        }
    } else {
        $error = 'Please select a room and provide a reason.';
    }
}

$requestsStmt = db()->prepare("SELECT rcr.*, cur.room_no AS current_room, req.room_no AS requested_room FROM room_change_requests rcr JOIN rooms cur ON cur.id = rcr.current_room_id JOIN rooms req ON req.id = rcr.requested_room_id WHERE rcr.student_id = ? ORDER BY rcr.applied_at DESC");
$requestsStmt->execute([$student['id']]);
$requests = $requestsStmt->fetchAll();

$availableStmt = db()->prepare("SELECT r.*, GROUP_CONCAT(CONCAT(s.name, ' (', s.course, ')') SEPARATOR ', ') AS occupants FROM rooms r LEFT JOIN room_allocations ra ON ra.room_id = r.id AND ra.status = 'Active' LEFT JOIN students s ON s.id = ra.student_id WHERE r.status = 'Available' AND r.capacity > r.occupancy AND r.id != ? GROUP BY r.id ORDER BY r.room_no");
$availableStmt->execute([$current['room_id']]);
$availableRooms = $availableStmt->fetchAll();

$title = 'Change Room';
require_once __DIR__ . '/../includes/student-header.php';
?>

<div class="container-fluid">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header"><strong>Current Room</strong></div>
                <div class="card-body">
                    <p><strong>Room:</strong> <?= htmlspecialchars($current['room_no']) ?> (<?= htmlspecialchars($current['room_type']) ?>)</p>
                </div>
            </div>

            <?php if (!$hasPending && $availableRooms): ?>
            <div class="card mt-3">
                <div class="card-header"><strong>Request Room Change</strong></div>
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Select New Room</label>
                            <select name="room_id" class="form-select" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($availableRooms as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['room_no']) ?> (<?= htmlspecialchars($r['room_type']) ?> - ₹<?= $r['fee_per_month'] ?>)<?= $r['occupants'] ? ' — ' . htmlspecialchars($r['occupants']) : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea name="reason" class="form-control" rows="4" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Submit Request</button>
                    </form>
                </div>
            </div>
            <?php elseif ($hasPending): ?>
                <div class="alert alert-warning mt-3">You already have a pending room change request.</div>
            <?php else: ?>
                <div class="alert alert-info mt-3">No rooms available for change at the moment.</div>
            <?php endif; ?>
        </div>

        <div class="col-md-7">
            <div class="card">
                <div class="card-header"><strong>My Requests</strong></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead><tr><th>Current Room</th><th>Requested Room</th><th>Reason</th><th>Status</th><th>Admin Remark</th><th>Date</th></tr></thead>
                            <tbody>
                                <?php if ($requests): ?>
                                    <?php foreach ($requests as $r): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($r['current_room']) ?></td>
                                            <td><?= htmlspecialchars($r['requested_room']) ?></td>
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
                                    <tr><td colspan="6" class="text-center text-muted">No requests yet.</td></tr>
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
