<?php
$title = 'Vacate Requests';
require_once __DIR__ . '/../includes/admin-header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($action === 'approve' && $id) {
    $remark = sanitize($_POST['remark'] ?? '');

    $stmt = db()->prepare("SELECT vr.*, s.id AS sid, s.user_id FROM vacate_requests vr JOIN students s ON s.id = vr.student_id WHERE vr.id = ? AND vr.status = 'Pending'");
    $stmt->execute([$id]);
    $req = $stmt->fetch();

    if ($req) {
        try {
            db()->beginTransaction();

            $sid = $req['sid'];

            $student = db()->prepare("SELECT * FROM students WHERE id = ?");
            $student->execute([$sid]);
            $s = $student->fetch();

            $user = db()->prepare("SELECT username, email FROM users WHERE id = ?");
            $user->execute([$req['user_id']]);
            $u = $user->fetch();

            $alloc = db()->prepare("SELECT ra.*, r.room_no FROM room_allocations ra JOIN rooms r ON r.id = ra.room_id WHERE ra.student_id = ? AND ra.status = 'Active'");
            $alloc->execute([$sid]);
            $a = $alloc->fetch();

            $lastRoomNo = $a ? $a['room_no'] : null;

            $fees = db()->prepare("SELECT * FROM fees WHERE student_id = ?");
            $fees->execute([$sid]);
            $feesData = $fees->fetchAll(PDO::FETCH_ASSOC);

            $attendance = db()->prepare("SELECT * FROM attendance WHERE student_id = ?");
            $attendance->execute([$sid]);
            $attData = $attendance->fetchAll(PDO::FETCH_ASSOC);

            $complaints = db()->prepare("SELECT * FROM complaints WHERE student_id = ?");
            $complaints->execute([$sid]);
            $compData = $complaints->fetchAll(PDO::FETCH_ASSOC);

            $leaves = db()->prepare("SELECT * FROM leaves WHERE student_id = ?");
            $leaves->execute([$sid]);
            $leavesData = $leaves->fetchAll(PDO::FETCH_ASSOC);

            $roomChanges = db()->prepare("SELECT * FROM room_change_requests WHERE student_id = ?");
            $roomChanges->execute([$sid]);
            $rcData = $roomChanges->fetchAll(PDO::FETCH_ASSOC);

            $relatedData = json_encode([
                'fees' => $feesData,
                'attendance' => $attData,
                'complaints' => $compData,
                'leaves' => $leavesData,
                'room_change_requests' => $rcData,
            ]);

            db()->prepare("INSERT INTO vacated_students (original_id, original_user_id, name, roll_no, email, phone, address, gender, course, year, guardian_name, guardian_phone, admission_date, join_date, photo, last_room_no, vacate_reason, admin_remark, related_data) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([
                $sid, $req['user_id'],
                $s['name'], $s['roll_no'], $s['email'], $s['phone'], $s['address'],
                $s['gender'], $s['course'], $s['year'],
                $s['guardian_name'], $s['guardian_phone'],
                $s['admission_date'], $s['join_date'], $s['photo'],
                $lastRoomNo, $req['reason'], $remark, $relatedData
            ]);

            if ($a) {
                db()->prepare("DELETE FROM room_allocations WHERE id = ?")->execute([$a['id']]);
                db()->prepare("UPDATE rooms SET occupancy = occupancy - 1 WHERE id = ?")->execute([$a['room_id']]);
                $rm = db()->prepare("SELECT occupancy, capacity FROM rooms WHERE id = ?");
                $rm->execute([$a['room_id']]);
                $roomData = $rm->fetch();
                if ($roomData['occupancy'] < $roomData['capacity']) {
                    db()->prepare("UPDATE rooms SET status = 'Available' WHERE id = ?")->execute([$a['room_id']]);
                }
            }

            db()->prepare("DELETE FROM fees WHERE student_id = ?")->execute([$sid]);
            db()->prepare("DELETE FROM attendance WHERE student_id = ?")->execute([$sid]);
            db()->prepare("DELETE FROM complaints WHERE student_id = ?")->execute([$sid]);
            db()->prepare("DELETE FROM leaves WHERE student_id = ?")->execute([$sid]);
            db()->prepare("DELETE FROM room_change_requests WHERE student_id = ?")->execute([$sid]);
            db()->prepare("DELETE FROM students WHERE id = ?")->execute([$sid]);
            db()->prepare("DELETE FROM users WHERE id = ?")->execute([$req['user_id']]);

            db()->prepare("UPDATE vacate_requests SET status = 'Approved', admin_remark = ? WHERE id = ?")->execute([$remark, $id]);

            db()->commit();
            setAlert('success', 'Vacate approved. Student data archived and removed.');
        } catch (Exception $e) {
            db()->rollBack();
            setAlert('danger', 'Error: ' . $e->getMessage());
        }
    }
    redirect(BASE_URL . '/admin/vacate-requests.php');
}

if ($action === 'reject' && $id) {
    $remark = sanitize($_POST['remark'] ?? '');
    db()->prepare("UPDATE vacate_requests SET status = 'Rejected', admin_remark = ? WHERE id = ?")->execute([$remark, $id]);
    setAlert('success', 'Vacate request rejected.');
    redirect(BASE_URL . '/admin/vacate-requests.php');
}

$page = (int)($_GET['p'] ?? 1);
$perPage = 15;
$total = db()->query("SELECT COUNT(*) FROM vacate_requests")->fetchColumn();
$offset = ($page - 1) * $perPage;
$pages = paginate($page, $perPage, $total);

$stmt = db()->prepare("SELECT vr.*, s.name AS student_name, s.roll_no FROM vacate_requests vr JOIN students s ON s.id = vr.student_id ORDER BY vr.applied_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute();
$requests = $stmt->fetchAll();
?>

<div class="container-fluid">
    <h4 class="mb-4">Vacate Requests</h4>

    <?php if ($action === 'review' && $id):
        $stmt = db()->prepare("SELECT vr.*, s.name AS student_name, s.roll_no, s.email, s.phone, r.room_no, r.room_type FROM vacate_requests vr JOIN students s ON s.id = vr.student_id LEFT JOIN room_allocations ra ON ra.student_id = s.id AND ra.status = 'Active' LEFT JOIN rooms r ON r.id = ra.room_id WHERE vr.id = ?");
        $stmt->execute([$id]);
        $req = $stmt->fetch();
        if (!$req) { setAlert('danger', 'Not found'); redirect(BASE_URL . '/admin/vacate-requests.php'); }
    ?>
        <div class="card">
            <div class="card-header"><strong>Review Vacate Request</strong></div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3"><strong>Student:</strong> <?= sanitize($req['student_name']) ?></div>
                    <div class="col-md-3"><strong>Roll No:</strong> <?= sanitize($req['roll_no']) ?></div>
                    <div class="col-md-3"><strong>Email:</strong> <?= sanitize($req['email']) ?></div>
                    <div class="col-md-3"><strong>Phone:</strong> <?= sanitize($req['phone']) ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3"><strong>Room:</strong> <?= sanitize($req['room_no'] ?? 'N/A') ?></div>
                    <div class="col-md-3"><strong>Type:</strong> <?= $req['room_type'] ?? 'N/A' ?></div>
                    <div class="col-md-3"><strong>Applied:</strong> <?= date('d M Y', strtotime($req['applied_at'])) ?></div>
                    <div class="col-md-3"><strong>Status:</strong>
                        <span class="badge bg-<?= $req['status'] == 'Approved' ? 'success' : ($req['status'] == 'Rejected' ? 'danger' : 'warning') ?>">
                            <?= $req['status'] ?>
                        </span>
                    </div>
                </div>
                <div class="mb-3">
                    <strong>Reason:</strong>
                    <p><?= nl2br(htmlspecialchars($req['reason'])) ?></p>
                </div>

                <?php if ($req['status'] === 'Pending'): ?>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <form method="post" action="?action=approve&id=<?= $id ?>">
                                <div class="input-group">
                                    <input type="text" name="remark" class="form-control" placeholder="Approval remark (optional)">
                                    <button class="btn btn-success">Approve & Vacate</button>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form method="post" action="?action=reject&id=<?= $id ?>">
                                <div class="input-group">
                                    <input type="text" name="remark" class="form-control" placeholder="Rejection reason" required>
                                    <button class="btn btn-danger">Reject</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Already <?= strtolower($req['status']) ?>. Remark: <?= htmlspecialchars($req['admin_remark'] ?? 'N/A') ?></p>
                    <a href="<?= BASE_URL ?>/admin/vacate-requests.php" class="btn btn-secondary">Back</a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <table class="table table-bordered table-striped">
            <thead><tr><th>Student</th><th>Roll No</th><th>Reason</th><th>Status</th><th>Applied</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($requests as $r): ?>
                <tr>
                    <td><?= sanitize($r['student_name']) ?></td>
                    <td><?= sanitize($r['roll_no']) ?></td>
                    <td><?= htmlspecialchars(implode(' ', array_slice(explode(' ', $r['reason']), 0, 8))) . (str_word_count($r['reason']) > 8 ? '...' : '') ?></td>
                    <td>
                        <span class="badge bg-<?= $r['status'] == 'Approved' ? 'success' : ($r['status'] == 'Rejected' ? 'danger' : 'warning') ?>">
                            <?= $r['status'] ?>
                        </span>
                    </td>
                    <td><?= date('d M Y', strtotime($r['applied_at'])) ?></td>
                    <td><a href="?action=review&id=<?= $r['id'] ?>" class="btn btn-sm btn-primary">Review</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$requests): ?><tr><td colspan="6" class="text-center text-muted">No vacate requests.</td></tr><?php endif; ?>
            </tbody>
        </table>
        <?= paginationLinks($page, $pages) ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
