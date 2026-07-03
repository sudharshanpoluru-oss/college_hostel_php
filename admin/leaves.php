<?php
$title = 'Leave Management';
require_once __DIR__ . '/../includes/admin-header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($action === 'approve' && $id) {
    $remark = sanitize($_POST['remark'] ?? '');
    $stmt = db()->prepare("UPDATE leaves SET status = 'Approved', admin_remark = ? WHERE id = ?");
    $stmt->execute([$remark, $id]);
    setAlert('success', 'Leave approved.');
    redirect(BASE_URL . '/admin/leaves.php');
}

if ($action === 'reject' && $id) {
    $remark = sanitize($_POST['remark'] ?? '');
    $stmt = db()->prepare("UPDATE leaves SET status = 'Rejected', admin_remark = ? WHERE id = ?");
    $stmt->execute([$remark, $id]);
    setAlert('success', 'Leave rejected.');
    redirect(BASE_URL . '/admin/leaves.php');
}

$page = (int)($_GET['p'] ?? 1);
$perPage = 15;
$total = db()->query("SELECT COUNT(*) FROM leaves")->fetchColumn();
$offset = ($page - 1) * $perPage;
$pages = paginate($page, $perPage, $total);

$stmt = db()->prepare("SELECT l.*, s.name AS student_name, s.roll_no, w.username AS warden_name FROM leaves l JOIN students s ON s.id = l.student_id LEFT JOIN users w ON w.id = l.warden_id ORDER BY l.applied_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute();
$leaves = $stmt->fetchAll();
?>

<div class="container-fluid">
    <h4 class="mb-4">Leave Requests</h4>

    <?php if ($action === 'review' && $id):
        $stmt = db()->prepare("SELECT l.*, s.name AS student_name, s.roll_no, s.email, s.phone, w.username AS warden_name FROM leaves l JOIN students s ON s.id = l.student_id LEFT JOIN users w ON w.id = l.warden_id WHERE l.id = ?");
        $stmt->execute([$id]);
        $leave = $stmt->fetch();
        if (!$leave) { setAlert('danger', 'Not found'); redirect(BASE_URL . '/admin/leaves.php'); }
    ?>
        <div class="card">
            <div class="card-header"><strong>Review Leave Request</strong></div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3"><strong>Student:</strong> <?= sanitize($leave['student_name']) ?></div>
                    <div class="col-md-3"><strong>Roll No:</strong> <?= sanitize($leave['roll_no']) ?></div>
                    <div class="col-md-3"><strong>Email:</strong> <?= sanitize($leave['email']) ?></div>
                    <div class="col-md-3"><strong>Phone:</strong> <?= sanitize($leave['phone']) ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3"><strong>From:</strong> <?= $leave['from_date'] ?></div>
                    <div class="col-md-3"><strong>To:</strong> <?= $leave['to_date'] ?></div>
                    <div class="col-md-2"><strong>Days:</strong> <?= max(1, (strtotime($leave['to_date']) - strtotime($leave['from_date'])) / 86400 + 1) ?></div>
                    <div class="col-md-4"><strong>Status:</strong>
                        <span class="badge bg-<?= $leave['status'] == 'Approved' ? 'success' : ($leave['status'] == 'Rejected' ? 'danger' : 'warning') ?>">
                            <?= $leave['status'] ?>
                        </span>
                        <?php if ($leave['warden_name']): ?>
                            <span class="small text-muted ms-2">by <?= sanitize($leave['warden_name']) ?></span>
                        <?php endif; ?>
                        <?php if ($leave['is_escalated']): ?>
                            <span class="badge bg-info ms-1">Escalated</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mb-3">
                    <strong>Reason:</strong>
                    <p><?= nl2br(htmlspecialchars($leave['reason'])) ?></p>
                </div>

                <?php if ($leave['warden_remark']): ?>
                <div class="mb-3">
                    <strong>Warden Remark:</strong>
                    <p class="mb-0"><?= htmlspecialchars($leave['warden_remark']) ?></p>
                    <?php if ($leave['warden_action_at']): ?>
                        <small class="text-muted">on <?= date('d M Y h:i A', strtotime($leave['warden_action_at'])) ?></small>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($leave['status'] === 'Pending' && !$leave['is_escalated']): ?>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <form method="post" action="?action=approve&id=<?= $id ?>">
                                <div class="input-group">
                                    <input type="text" name="remark" class="form-control" placeholder="Approval remark (optional)">
                                    <button class="btn btn-success">Approve</button>
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
                    <p class="text-muted">Already <?= strtolower($leave['status']) ?>. <?= $leave['admin_remark'] ? 'Admin remark: ' . htmlspecialchars($leave['admin_remark']) : '' ?></p>
                    <a href="<?= BASE_URL ?>/admin/leaves.php" class="btn btn-secondary">Back</a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <table class="table table-bordered table-striped">
            <thead><tr><th>Student</th><th>Roll No</th><th>From</th><th>To</th><th>Reason</th><th>Status</th><th>Approved By</th><th>Applied</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($leaves as $l): ?>
                <tr>
                    <td><?= sanitize($l['student_name']) ?></td>
                    <td><?= sanitize($l['roll_no']) ?></td>
                    <td><?= $l['from_date'] ?></td>
                    <td><?= $l['to_date'] ?></td>
                    <td><?= htmlspecialchars(implode(' ', array_slice(explode(' ', $l['reason']), 0, 8))) . (str_word_count($l['reason']) > 8 ? '...' : '') ?></td>
                    <td>
                        <span class="badge bg-<?= $l['status'] == 'Approved' ? 'success' : ($l['status'] == 'Rejected' ? 'danger' : 'warning') ?>">
                            <?= $l['status'] ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($l['warden_name']): ?>
                            <span class="small"><?= sanitize($l['warden_name']) ?> <span class="text-muted">(Warden)</span></span>
                        <?php elseif ($l['status'] !== 'Pending'): ?>
                            <span class="small">Admin</span>
                        <?php else: ?>
                            <span class="text-muted small">&mdash;</span>
                        <?php endif; ?>
                    </td>
                    <td><?= date('d M Y', strtotime($l['applied_at'])) ?></td>
                    <td><a href="?action=review&id=<?= $l['id'] ?>" class="btn btn-sm btn-primary">Review</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$leaves): ?><tr><td colspan="9" class="text-center text-muted">No leave requests.</td></tr><?php endif; ?>
            </tbody>
        </table>
        <?= paginationLinks($page, $pages) ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
