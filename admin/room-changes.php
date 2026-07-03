<?php
$title = 'Room Change Requests';
require_once __DIR__ . '/../includes/admin-header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($action === 'approve' && $id) {
    $stmt = db()->prepare("SELECT rcr.*, s.user_id FROM room_change_requests rcr JOIN students s ON s.id = rcr.student_id WHERE rcr.id = ?");
    $stmt->execute([$id]);
    $req = $stmt->fetch();

    if ($req && $req['status'] === 'Pending') {
        $remark = sanitize($_POST['remark'] ?? '');

        try {
            db()->beginTransaction();

            db()->prepare("UPDATE room_allocations SET room_id = ?, allocation_date = CURDATE() WHERE student_id = ? AND status = 'Active'")->execute([$req['requested_room_id'], $req['student_id']]);

            db()->prepare("UPDATE rooms SET occupancy = occupancy - 1 WHERE id = ?")->execute([$req['current_room_id']]);
            db()->prepare("UPDATE rooms SET occupancy = occupancy + 1 WHERE id = ?")->execute([$req['requested_room_id']]);

            db()->prepare("UPDATE rooms SET status = 'Available' WHERE id = ? AND capacity > occupancy")->execute([$req['current_room_id']]);
            db()->prepare("UPDATE rooms SET status = CASE WHEN capacity <= occupancy THEN 'Full' ELSE 'Available' END WHERE id = ?")->execute([$req['requested_room_id']]);

            db()->prepare("UPDATE room_change_requests SET status = 'Approved', admin_remark = ? WHERE id = ?")->execute([$remark, $id]);

            db()->commit();
            setAlert('success', 'Room change approved and allocation updated.');
        } catch (Exception $e) {
            db()->rollBack();
            setAlert('danger', 'Error: ' . $e->getMessage());
        }
    }
    redirect(BASE_URL . '/admin/room-changes.php');
}

if ($action === 'reject' && $id) {
    $remark = sanitize($_POST['remark'] ?? '');
    db()->prepare("UPDATE room_change_requests SET status = 'Rejected', admin_remark = ? WHERE id = ?")->execute([$remark, $id]);
    setAlert('success', 'Room change request rejected.');
    redirect(BASE_URL . '/admin/room-changes.php');
}

$page = (int)($_GET['p'] ?? 1);
$perPage = 15;
$total = db()->query("SELECT COUNT(*) FROM room_change_requests")->fetchColumn();
$offset = ($page - 1) * $perPage;
$pages = paginate($page, $perPage, $total);

$stmt = db()->prepare("SELECT rcr.*, s.name AS student_name, s.roll_no, cur.room_no AS current_room, req.room_no AS requested_room FROM room_change_requests rcr JOIN students s ON s.id = rcr.student_id JOIN rooms cur ON cur.id = rcr.current_room_id JOIN rooms req ON req.id = rcr.requested_room_id ORDER BY rcr.applied_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute();
$requests = $stmt->fetchAll();
?>

<div class="container-fluid">
    <h4 class="mb-4">Room Change Requests</h4>

    <?php if ($action === 'review' && $id):
        $stmt = db()->prepare("SELECT rcr.*, s.name AS student_name, s.roll_no, s.email, s.phone, cur.room_no AS current_room, req.room_no AS requested_room FROM room_change_requests rcr JOIN students s ON s.id = rcr.student_id JOIN rooms cur ON cur.id = rcr.current_room_id JOIN rooms req ON req.id = rcr.requested_room_id WHERE rcr.id = ?");
        $stmt->execute([$id]);
        $req = $stmt->fetch();
        if (!$req) { setAlert('danger', 'Not found'); redirect(BASE_URL . '/admin/room-changes.php'); }
    ?>
        <div class="card">
            <div class="card-header"><strong>Review Request</strong></div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4"><strong>Student:</strong> <?= sanitize($req['student_name']) ?></div>
                    <div class="col-md-4"><strong>Roll No:</strong> <?= sanitize($req['roll_no']) ?></div>
                    <div class="col-md-4"><strong>Status:</strong> <span class="badge bg-<?= $req['status'] == 'Approved' ? 'success' : ($req['status'] == 'Rejected' ? 'danger' : 'warning') ?>"><?= $req['status'] ?></span></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4"><strong>Current Room:</strong> <?= sanitize($req['current_room']) ?></div>
                    <div class="col-md-4"><strong>Requested Room:</strong> <?= sanitize($req['requested_room']) ?></div>
                    <div class="col-md-4"><strong>Applied:</strong> <?= date('d M Y', strtotime($req['applied_at'])) ?></div>
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
                                    <button class="btn btn-success">Approve & Allocate</button>
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
                    <a href="<?= BASE_URL ?>/admin/room-changes.php" class="btn btn-secondary">Back</a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <table class="table table-bordered table-striped">
            <thead><tr><th>Student</th><th>Roll No</th><th>Current Room</th><th>Requested Room</th><th>Status</th><th>Applied</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($requests as $r): ?>
                <tr>
                    <td><?= sanitize($r['student_name']) ?></td>
                    <td><?= sanitize($r['roll_no']) ?></td>
                    <td><?= sanitize($r['current_room']) ?></td>
                    <td><?= sanitize($r['requested_room']) ?></td>
                    <td>
                        <span class="badge bg-<?= $r['status'] == 'Approved' ? 'success' : ($r['status'] == 'Rejected' ? 'danger' : 'warning') ?>">
                            <?= $r['status'] ?>
                        </span>
                    </td>
                    <td><?= date('d M Y', strtotime($r['applied_at'])) ?></td>
                    <td><a href="?action=review&id=<?= $r['id'] ?>" class="btn btn-sm btn-primary">Review</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$requests): ?><tr><td colspan="7" class="text-center text-muted">No requests.</td></tr><?php endif; ?>
            </tbody>
        </table>
        <?= paginationLinks($page, $pages) ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
