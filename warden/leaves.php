<?php
$title = 'Leave Management';
require_once __DIR__ . '/../includes/warden-header.php';

$hostelType = getWardenHostelType();
$hostelFilter = $hostelType ? " AND s.hostel_type = '$hostelType'" : '';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$page = (int)($_GET['p'] ?? 1);
$perPage = 15;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();

    if ($action === 'approve' && $id) {
        $remark = sanitize($_POST['remark'] ?? '');
        $stmt = db()->prepare("UPDATE leaves SET status = 'Approved', warden_approved = 1, warden_id = ?, warden_remark = ?, warden_action_at = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $remark, $id]);
        auditLog('Leave Approved', 'Leaves', "Leave #$id approved by warden");
        setAlert('success', 'Leave approved successfully.');
        redirect(BASE_URL . '/warden/leaves.php');
    }

    if ($action === 'reject' && $id) {
        $remark = sanitize($_POST['remark'] ?? '');
        if (empty($remark)) {
            setAlert('danger', 'Rejection reason is required.');
            redirect(BASE_URL . "/warden/leaves.php?action=review&id=$id");
        }
        $stmt = db()->prepare("UPDATE leaves SET status = 'Rejected', warden_approved = 0, warden_id = ?, warden_remark = ?, warden_action_at = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $remark, $id]);
        auditLog('Leave Rejected', 'Leaves', "Leave #$id rejected by warden. Reason: $remark");
        setAlert('success', 'Leave rejected.');
        redirect(BASE_URL . '/warden/leaves.php');
    }

    if ($action === 'escalate' && $id) {
        $remark = sanitize($_POST['remark'] ?? '');
        $stmt = db()->prepare("UPDATE leaves SET is_escalated = 1, status = 'Pending', warden_remark = ?, warden_id = ?, warden_action_at = NOW() WHERE id = ?");
        $stmt->execute([$remark, $_SESSION['user_id'], $id]);

        $leaveStmt = db()->prepare("SELECT l.*, s.name AS student_name, s.roll_no FROM leaves l JOIN students s ON s.id = l.student_id WHERE l.id = ? $hostelFilter");
        $leaveStmt->execute([$id]);
        $leave = $leaveStmt->fetch();
        $adminStmt = db()->query("SELECT id FROM users WHERE role = 'admin' AND status = 1");
        while ($admin = $adminStmt->fetch()) {
            addNotification($admin['id'], 'Leave Escalated', "Leave #$id by {$leave['student_name']} ({$leave['roll_no']}) escalated to admin.", 'warning', BASE_URL . '/admin/leaves.php?action=review&id=' . $id);
        }

        auditLog('Leave Escalated', 'Leaves', "Leave #$id escalated to admin by warden. Remark: $remark");
        setAlert('success', 'Leave escalated to admin.');
        redirect(BASE_URL . '/warden/leaves.php');
    }
}

// ─── Review ──────────────────────────────────────────────────────────────────
if ($action === 'review' && $id):
    $stmt = db()->prepare("SELECT l.*, s.name AS student_name, s.roll_no, s.email, s.phone, s.course, s.year FROM leaves l JOIN students s ON s.id = l.student_id WHERE l.id = ? $hostelFilter");
    $stmt->execute([$id]);
    $leave = $stmt->fetch();
    if (!$leave) { setAlert('danger', 'Leave request not found.'); redirect(BASE_URL . '/warden/leaves.php'); }

    $roomStmt = db()->prepare("SELECT r.room_no FROM room_allocations ra JOIN rooms r ON r.id = ra.room_id WHERE ra.student_id = ? AND ra.status = 'Active' LIMIT 1");
    $roomStmt->execute([$leave['student_id']]);
    $room = $roomStmt->fetchColumn();
?>
<div class="container-fluid">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><i class="bi bi-file-text me-1"></i> Review Leave Request #<?= $id ?></strong>
            <a href="<?= BASE_URL ?>/warden/leaves.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3"><strong>Student:</strong> <?= sanitize($leave['student_name']) ?></div>
                <div class="col-md-2"><strong>Roll No:</strong> <?= sanitize($leave['roll_no']) ?></div>
                <div class="col-md-3"><strong>Email:</strong> <?= sanitize($leave['email']) ?></div>
                <div class="col-md-2"><strong>Phone:</strong> <?= sanitize($leave['phone']) ?></div>
                <div class="col-md-2"><strong>Room:</strong> <?= sanitize($room ?: '-') ?></div>
            </div>
            <div class="row mb-3">
                <div class="col-md-2"><strong>From:</strong> <?= $leave['from_date'] ?></div>
                <div class="col-md-2"><strong>To:</strong> <?= $leave['to_date'] ?></div>
                <div class="col-md-2"><strong>Days:</strong> <?= max(1, (strtotime($leave['to_date']) - strtotime($leave['from_date'])) / 86400 + 1) ?></div>
                <div class="col-md-2">
                    <strong>Type:</strong>
                    <span class="badge bg-<?= match($leave['leave_type'] ?? 'Regular'){'Emergency'=>'danger','Medical'=>'info','Weekend'=>'secondary',default=>'primary'} ?>">
                        <?= $leave['leave_type'] ?? 'Regular' ?>
                    </span>
                </div>
                <div class="col-md-2">
                    <strong>Status:</strong>
                    <?php
                    $st = $leave['status'];
                    if ($leave['is_escalated']) { $stBadge = 'info'; $stLabel = 'Escalated'; }
                    elseif ($st === 'Approved') { $stBadge = 'success'; $stLabel = 'Approved'; }
                    elseif ($st === 'Rejected') { $stBadge = 'danger'; $stLabel = 'Rejected'; }
                    else { $stBadge = 'warning'; $stLabel = 'Pending'; }
                    ?>
                    <span class="badge bg-<?= $stBadge ?>"><?= $stLabel ?></span>
                </div>
                <div class="col-md-2"><strong>Applied:</strong> <?= date('d M Y', strtotime($leave['applied_at'])) ?></div>
            </div>
            <div class="mb-3">
                <strong>Reason:</strong>
                <p class="mb-0"><?= nl2br(htmlspecialchars($leave['reason'])) ?></p>
            </div>

            <?php if ($leave['status'] === 'Pending' && !$leave['is_escalated']): ?>
                <hr>
                <h6 class="mb-3"><i class="bi bi-shield-check"></i> Warden Action</h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <form method="post" action="?action=approve&id=<?= $id ?>">
                            <?= csrfField() ?>
                            <div class="input-group">
                                <input type="text" name="remark" class="form-control" placeholder="Remark (optional)" maxlength="500">
                                <button class="btn btn-success"><i class="bi bi-check-lg"></i> Approve</button>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-4">
                        <form method="post" action="?action=reject&id=<?= $id ?>" onsubmit="return confirm('Reject this leave request?')">
                            <?= csrfField() ?>
                            <div class="input-group">
                                <input type="text" name="remark" class="form-control" placeholder="Rejection reason *" required maxlength="500">
                                <button class="btn btn-danger"><i class="bi bi-x-lg"></i> Reject</button>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-4">
                        <form method="post" action="?action=escalate&id=<?= $id ?>" onsubmit="return confirm('Escalate this leave to admin?')">
                            <?= csrfField() ?>
                            <div class="input-group">
                                <input type="text" name="remark" class="form-control" placeholder="Escalation reason" maxlength="500">
                                <button class="btn btn-info text-white"><i class="bi bi-send"></i> Escalate</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php elseif ($leave['is_escalated']): ?>
                <div class="alert alert-info mb-0">This leave has been escalated to admin. <?= $leave['warden_remark'] ? 'Warden remark: ' . htmlspecialchars($leave['warden_remark']) : '' ?></div>
            <?php else: ?>
                <div class="alert alert-<?= $leave['status'] === 'Approved' ? 'success' : 'danger' ?> mb-0">
                    <strong><?= $leave['status'] ?>:</strong>
                    <?= htmlspecialchars($leave['warden_remark'] ?: $leave['admin_remark'] ?: 'No remarks') ?>
                    <?php if ($leave['warden_action_at']): ?>
                        <br><small class="text-muted">Action taken on <?= date('d M Y h:i A', strtotime($leave['warden_action_at'])) ?></small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// ─── List ────────────────────────────────────────────────────────────────────
else:
    // Build filter query
    $where = '1=1';
    $params = [];

    $filterStatus = $_GET['status'] ?? '';
    if ($filterStatus === 'pending') { $where .= " AND l.status = 'Pending' AND l.is_escalated = 0"; }
    elseif ($filterStatus === 'approved') { $where .= " AND l.status = 'Approved'"; }
    elseif ($filterStatus === 'rejected') { $where .= " AND l.status = 'Rejected'"; }
    elseif ($filterStatus === 'escalated') { $where .= " AND l.is_escalated = 1"; }

    if (!empty($_GET['from'])) { $where .= " AND l.from_date >= ?"; $params[] = $_GET['from']; }
    if (!empty($_GET['to'])) { $where .= " AND l.to_date <= ?"; $params[] = $_GET['to']; }
    if (!empty($_GET['student'])) { $where .= " AND (s.name LIKE ? OR s.roll_no LIKE ?)"; $params[] = "%{$_GET['student']}%"; $params[] = "%{$_GET['student']}%"; }
    $where .= $hostelFilter;

    $countSql = "SELECT COUNT(*) FROM leaves l JOIN students s ON s.id = l.student_id WHERE $where";
    $countStmt = db()->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();

    $pages = paginate($page, $perPage, $total);

    $sql = "SELECT l.*, s.name AS student_name, s.roll_no FROM leaves l JOIN students s ON s.id = l.student_id WHERE $where ORDER BY l.applied_at DESC LIMIT $perPage OFFSET {$pages['offset']}";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $leaves = $stmt->fetchAll();
?>

<div class="container-fluid">
    <!-- Filter form -->
    <form method="get" class="row g-2 mb-3">
        <div class="col-auto">
            <select name="status" class="form-select form-select-sm">
                <option value="">All Status</option>
                <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                <option value="escalated" <?= $filterStatus === 'escalated' ? 'selected' : '' ?>>Escalated</option>
            </select>
        </div>
        <div class="col-auto">
            <input type="date" name="from" class="form-control form-control-sm" value="<?= sanitize($_GET['from'] ?? '') ?>" placeholder="From">
        </div>
        <div class="col-auto">
            <input type="date" name="to" class="form-control form-control-sm" value="<?= sanitize($_GET['to'] ?? '') ?>" placeholder="To">
        </div>
        <div class="col-auto">
            <input type="text" name="student" class="form-control form-control-sm" placeholder="Search student..." value="<?= sanitize($_GET['student'] ?? '') ?>">
        </div>
        <div class="col-auto">
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i> Filter</button>
            <a href="<?= BASE_URL ?>/warden/leaves.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i> Clear</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Student</th>
                    <th>Type</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($leaves): ?>
                    <?php foreach ($leaves as $l): ?>
                    <tr>
                        <td><?= $l['id'] ?></td>
                        <td><?= sanitize($l['student_name']) ?> <small class="text-muted">(<?= sanitize($l['roll_no']) ?>)</small></td>
                        <td>
                            <span class="badge bg-<?= match($l['leave_type'] ?? 'Regular'){'Emergency'=>'danger','Medical'=>'info','Weekend'=>'secondary',default=>'primary'} ?>">
                                <?= $l['leave_type'] ?? 'Regular' ?>
                            </span>
                        </td>
                        <td><?= $l['from_date'] ?></td>
                        <td><?= $l['to_date'] ?></td>
                        <td><?= htmlspecialchars(implode(' ', array_slice(explode(' ', $l['reason']), 0, 8))) . (str_word_count($l['reason']) > 8 ? '...' : '') ?></td>
                        <td>
                            <?php
                            if ($l['is_escalated']) { $badge = 'info'; $label = 'Escalated'; }
                            elseif ($l['status'] === 'Approved') { $badge = 'success'; $label = 'Approved'; }
                            elseif ($l['status'] === 'Rejected') { $badge = 'danger'; $label = 'Rejected'; }
                            else { $badge = 'warning'; $label = 'Pending'; }
                            ?>
                            <span class="badge bg-<?= $badge ?>"><?= $label ?></span>
                        </td>
                        <td>
                            <a href="?action=review&id=<?= $l['id'] ?>" class="btn btn-sm btn-primary"><i class="bi bi-eye"></i> Review</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center text-muted py-3">No leave requests found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?= paginationLinks($page, $pages) ?>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/warden-footer.php'; ?>
