<?php
$title = 'Complaint Management';
require_once __DIR__ . '/../includes/admin-header.php';

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$filter = sanitize($_GET['status'] ?? '');

// ---- POST handlers ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];

    // Escalation: assign to maintenance
    if (isset($_POST['assign_maintenance'])) {
        $assigned_to = (int)$_POST['assigned_to'];
        db()->prepare("UPDATE complaints SET assigned_to=?, assigned_role='maintenance', status='Assigned to Maintenance' WHERE id=?")
            ->execute([$assigned_to, $id]);
        logComplaintAction($id, 'Assigned to Maintenance', "Assigned to user ID: $assigned_to");
        auditLog('Assign Complaint', 'Complaints', "Complaint #$id assigned to maintenance");
        setAlert('success', 'Complaint assigned to maintenance.');
        redirect(BASE_URL . '/admin/complaints.php?action=view&id=' . $id);
    }

    // Escalation: resolve by admin
    if (isset($_POST['resolve_complaint'])) {
        $resolution_notes = sanitize($_POST['resolution_notes']);
        $now = date('Y-m-d H:i:s');
        db()->prepare("UPDATE complaints SET status='Resolved by Admin', resolved_by=?, resolved_at=?, resolution_notes=?, admin_response=?, resolution_date=CURDATE() WHERE id=?")
            ->execute([$_SESSION['user_id'], $now, $resolution_notes, $resolution_notes, $id]);

        $c = db()->prepare("SELECT student_id FROM complaints WHERE id=?");
        $c->execute([$id]);
        $comp = $c->fetch();
        if ($comp) {
            $studentUser = db()->prepare("SELECT user_id FROM students WHERE id=?");
            $studentUser->execute([$comp['student_id']]);
            $su = $studentUser->fetch();
            if ($su) {
                addNotification($su['user_id'], 'Complaint Resolved', "Your complaint #$id has been resolved by admin.", 'success', BASE_URL.'/student/complaints.php');
            }
        }
        logComplaintAction($id, 'Resolved by Admin', $resolution_notes);
        auditLog('Resolve Complaint', 'Complaints', "Complaint #$id resolved by admin");
        setAlert('success', 'Complaint resolved successfully.');
        redirect(BASE_URL . '/admin/complaints.php?action=view&id=' . $id);
    }

    // Escalation: close
    if (isset($_POST['close_complaint'])) {
        db()->prepare("UPDATE complaints SET status='Closed' WHERE id=?")->execute([$id]);
        logComplaintAction($id, 'Closed', "Closed by admin");
        auditLog('Close Complaint', 'Complaints', "Complaint #$id closed");
        setAlert('success', 'Complaint closed.');
        redirect(BASE_URL . '/admin/complaints.php');
    }

    // Escalation: reject
    if (isset($_POST['reject_complaint'])) {
        $reason = sanitize($_POST['rejection_reason'] ?? '');
        db()->prepare("UPDATE complaints SET status='Rejected', admin_response=? WHERE id=?")->execute([$reason, $id]);
        logComplaintAction($id, 'Rejected', $reason);
        auditLog('Reject Complaint', 'Complaints', "Complaint #$id rejected: $reason");
        setAlert('success', 'Complaint rejected.');
        redirect(BASE_URL . '/admin/complaints.php');
    }

    // Escalation: under review
    if (isset($_POST['under_review'])) {
        db()->prepare("UPDATE complaints SET status='Under Admin Review' WHERE id=?")->execute([$id]);
        logComplaintAction($id, 'Under Admin Review', 'Admin is reviewing');
        auditLog('Review Complaint', 'Complaints', "Complaint #$id under admin review");
        setAlert('success', 'Complaint marked under review.');
        redirect(BASE_URL . '/admin/complaints.php?action=view&id=' . $id);
    }

    // Original: response + status update
    $response = sanitize($_POST['admin_response']);
    $status   = sanitize($_POST['status']);
    $resolution_date = $status === 'Resolved' ? date('Y-m-d') : null;
    db()->prepare("UPDATE complaints SET admin_response=?, status=?, resolution_date=? WHERE id=?")
        ->execute([$response, $status, $resolution_date, $id]);
    logComplaintAction($id, "Status changed to $status", $response);
    auditLog('Update Complaint', 'Complaints', "Complaint #$id set to $status");
    setAlert('success', 'Response submitted.');
    redirect(BASE_URL . '/admin/complaints.php');
}

// ---- Pagination & listing ----
$page    = (int)($_GET['p'] ?? 1);
$perPage = 10;

$where = '';
$params = [];
if ($filter) {
    if ($filter === 'Escalated') {
        $where = "WHERE (c.status IN ('Escalated to Admin','Under Admin Review','Assigned to Maintenance') OR c.priority='Emergency')";
    } else {
        $where = "WHERE c.status=?";
        $params[] = $filter;
    }
}

$total = db()->prepare("SELECT COUNT(*) FROM complaints c $where");
$total->execute($params);
$totalRows = $total->fetchColumn();
$offset = ($page - 1) * $perPage;
$pages  = paginate($page, $perPage, $totalRows);

$stmt = db()->prepare("
    SELECT c.*,
           s.name AS student_name, s.roll_no,
           eb.name AS escalated_by_name,
           w.name AS warden_name
    FROM complaints c
    JOIN students s ON s.id=c.student_id
    LEFT JOIN wardens w ON w.user_id=c.escalated_by
    LEFT JOIN students eb ON eb.user_id=c.escalated_by
    $where
    ORDER BY
        CASE WHEN c.priority='Emergency' THEN 0 ELSE 1 END,
        c.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$complaints = $stmt->fetchAll();
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0">Complaints</h4>
        <div>
            <a href="?status=Pending" class="btn btn-sm btn-outline-warning <?= $filter=='Pending'?'active':'' ?>">Pending</a>
            <a href="?status=Working" class="btn btn-sm btn-outline-info <?= $filter=='Working'?'active':'' ?>">Working</a>
            <a href="?status=Escalated" class="btn btn-sm btn-outline-danger <?= $filter=='Escalated'?'active':'' ?>">Escalated</a>
            <a href="?status=Resolved" class="btn btn-sm btn-outline-success <?= $filter=='Resolved'?'active':'' ?>">Resolved</a>
            <a href="?" class="btn btn-sm btn-outline-secondary">All</a>
        </div>
    </div>

    <?php if ($action === 'view' && $id):
    $stmt = db()->prepare("
        SELECT c.*, s.name AS student_name, s.roll_no,
               eb.name AS escalated_by_name, eb.roll_no AS escalated_by_roll,
               w.name AS warden_name
        FROM complaints c
        JOIN students s ON s.id=c.student_id
        LEFT JOIN wardens w ON w.user_id=c.escalated_by
        LEFT JOIN students eb ON eb.user_id=c.escalated_by
        WHERE c.id=?
    ");
    $stmt->execute([$id]);
    $c = $stmt->fetch();
    if (!$c) { setAlert('danger','Not found'); redirect(BASE_URL.'/admin/complaints.php'); }

    $logs = db()->prepare("SELECT cl.*, u.username FROM complaint_logs cl LEFT JOIN users u ON u.id=cl.performed_by WHERE cl.complaint_id=? ORDER BY cl.created_at DESC");
    $logs->execute([$id]);
    $complaintLogs = $logs->fetchAll();

    $maintenanceUsers = db()->query("SELECT id, username FROM users WHERE role IN ('admin','warden') ORDER BY username")->fetchAll();
    ?>
    <div class="card mb-3 <?= $c['priority']==='Emergency'?'border-danger':(in_array($c['status'],['Escalated to Admin','Under Admin Review','Assigned to Maintenance'])?'border-warning':'') ?>">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <strong><?= sanitize($c['title']) ?></strong>
                <?php if ($c['priority']==='Emergency'): ?>
                    <span class="badge bg-danger ms-2">EMERGENCY</span>
                <?php elseif ($c['priority']==='High'): ?>
                    <span class="badge bg-warning text-dark ms-2">High Priority</span>
                <?php endif; ?>
            </div>
            <div>
                <?php
                $badgeMap = [
                    'Pending' => 'warning',
                    'Working' => 'info',
                    'Resolved' => 'success',
                    'Escalated to Admin' => 'danger',
                    'Under Admin Review' => 'warning',
                    'Assigned to Maintenance' => 'primary',
                    'Resolved by Admin' => 'success',
                    'Closed' => 'secondary',
                    'Rejected' => 'dark',
                ];
                $bg = $badgeMap[$c['status']] ?? 'secondary';
                ?>
                <span class="badge bg-<?= $bg ?>"><?= $c['status'] ?></span>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Student:</strong> <?= sanitize($c['student_name']) ?> (<?= sanitize($c['roll_no']) ?>)</p>
                    <p><strong>Category:</strong> <?= sanitize($c['category']) ?></p>
                    <p><strong>Priority:</strong>
                        <span class="badge bg-<?= $c['priority']==='Emergency'?'danger':($c['priority']==='High'?'warning':($c['priority']==='Low'?'secondary':'info')) ?>">
                            <?= sanitize($c['priority']) ?>
                        </span>
                    </p>
                </div>
                <div class="col-md-6">
                    <?php if ($c['escalated_by']): ?>
                    <p><strong>Escalated By:</strong> <?= sanitize($c['warden_name'] ?: $c['escalated_by_name']) ?></p>
                    <p><strong>Escalation Reason:</strong> <?= nl2br(sanitize($c['escalation_reason'])) ?></p>
                    <p><strong>Escalated At:</strong> <?= $c['escalated_at'] ?></p>
                    <?php endif; ?>
                    <?php if ($c['assigned_to']): ?>
                    <p><strong>Assigned To:</strong> User #<?= $c['assigned_to'] ?> (<?= sanitize($c['assigned_role']) ?>)</p>
                    <?php endif; ?>
                    <?php if ($c['resolved_by']): ?>
                    <p><strong>Resolved By:</strong> <?= $c['resolved_by'] ?></p>
                    <p><strong>Resolved At:</strong> <?= $c['resolved_at'] ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <p><strong>Description:</strong><br><?= nl2br(sanitize($c['description'])) ?></p>
            <?php if ($c['attachment']): ?>
            <p><a href="<?= BASE_URL ?>/uploads/<?= $c['attachment'] ?>" target="_blank">View Attachment</a></p>
            <?php endif; ?>
            <?php if ($c['admin_response']): ?>
            <hr><p><strong>Admin Response:</strong><br><?= nl2br(sanitize($c['admin_response'])) ?></p>
            <?php if ($c['resolution_date']): ?><p><strong>Resolution Date:</strong> <?= $c['resolution_date'] ?></p><?php endif; ?>
            <?php endif; ?>
            <?php if ($c['resolution_notes']): ?>
            <hr><p><strong>Resolution Notes:</strong><br><?= nl2br(sanitize($c['resolution_notes'])) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Escalation action buttons -->
    <?php if (in_array($c['status'], ['Escalated to Admin','Under Admin Review','Assigned to Maintenance'])): ?>
    <div class="card mb-3 border-primary">
        <div class="card-header bg-primary text-white fw-bold">Escalation Actions</div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                <?php if ($c['status'] === 'Escalated to Admin'): ?>
                <form method="post" action="?action=view&id=<?= $c['id'] ?>" style="display:inline">
                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                    <button type="submit" name="under_review" class="btn btn-warning">Mark Under Review</button>
                </form>
                <?php endif; ?>

                <?php if (in_array($c['status'], ['Under Admin Review','Assigned to Maintenance'])): ?>
                <form method="post" action="?action=view&id=<?= $c['id'] ?>" class="row g-2 align-items-end" style="display:inline-flex">
                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                    <div class="col-auto">
                        <select name="assigned_to" class="form-select form-select-sm" required>
                            <option value="">Assign to...</option>
                            <?php foreach ($maintenanceUsers as $mu): ?>
                            <option value="<?= $mu['id'] ?>" <?= $c['assigned_to']==$mu['id']?'selected':'' ?>><?= sanitize($mu['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto"><button type="submit" name="assign_maintenance" class="btn btn-primary btn-sm">Assign to Maintenance</button></div>
                </form>
                <?php endif; ?>

                <form method="post" action="?action=view&id=<?= $c['id'] ?>" style="display:inline">
                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                    <div class="input-group input-group-sm">
                        <input type="text" name="resolution_notes" class="form-control" placeholder="Resolution notes" required>
                        <button type="submit" name="resolve_complaint" class="btn btn-success">Resolve</button>
                    </div>
                </form>

                <form method="post" action="?action=view&id=<?= $c['id'] ?>" style="display:inline" onsubmit="return confirm('Close this complaint?')">
                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                    <button type="submit" name="close_complaint" class="btn btn-secondary btn-sm">Close</button>
                </form>

                <button type="button" class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="post" action="?action=view&id=<?= $c['id'] ?>" class="modal-content">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <div class="modal-header"><h5 class="modal-title">Reject Complaint #<?= $c['id'] ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label>Reason for Rejection</label>
                    <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="reject_complaint" class="btn btn-dark">Reject</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Legacy response form -->
    <form method="post" action="?action=view&id=<?= $c['id'] ?>" class="card mb-3">
        <input type="hidden" name="id" value="<?= $c['id'] ?>">
        <div class="card-body">
            <div class="mb-3"><label>Admin Response</label><textarea name="admin_response" class="form-control" rows="4"><?= sanitize($c['admin_response']) ?></textarea></div>
            <div class="row g-2">
                <div class="col-auto">
                    <select name="status" class="form-select">
                        <option value="Pending" <?= $c['status']=='Pending'?'selected':'' ?>>Pending</option>
                        <option value="Working" <?= $c['status']=='Working'?'selected':'' ?>>Working</option>
                        <option value="Resolved" <?= $c['status']=='Resolved'?'selected':'' ?>>Resolved</option>
                        <option value="Escalated to Admin" <?= $c['status']=='Escalated to Admin'?'selected':'' ?>>Escalated to Admin</option>
                        <option value="Under Admin Review" <?= $c['status']=='Under Admin Review'?'selected':'' ?>>Under Admin Review</option>
                        <option value="Assigned to Maintenance" <?= $c['status']=='Assigned to Maintenance'?'selected':'' ?>>Assigned to Maintenance</option>
                        <option value="Resolved by Admin" <?= $c['status']=='Resolved by Admin'?'selected':'' ?>>Resolved by Admin</option>
                        <option value="Closed" <?= $c['status']=='Closed'?'selected':'' ?>>Closed</option>
                        <option value="Rejected" <?= $c['status']=='Rejected'?'selected':'' ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-auto"><button class="btn btn-primary">Submit Response</button></div>
            </div>
        </div>
    </form>

    <!-- Complaint logs -->
    <?php if ($complaintLogs): ?>
    <div class="card mb-3">
        <div class="card-header"><strong>Activity Log</strong></div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead><tr><th>Action</th><th>By</th><th>Remarks</th><th>Date</th></tr></thead>
                <tbody>
                    <?php foreach ($complaintLogs as $log): ?>
                    <tr>
                        <td><?= sanitize($log['action']) ?></td>
                        <td><?= sanitize($log['username']??'System') ?></td>
                        <td><?= nl2br(sanitize($log['remarks']??'')) ?></td>
                        <td><?= $log['created_at'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <a href="<?= BASE_URL ?>/admin/complaints.php" class="btn btn-secondary mt-2">Back</a>

    <?php else: ?>
    <table class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>Student</th>
                <th>Title</th>
                <th>Category</th>
                <th>Date</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Escalated</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($complaints as $c): ?>
            <?php $isEscalated = in_array($c['status'], ['Escalated to Admin','Under Admin Review','Assigned to Maintenance']) || $c['priority']==='Emergency'; ?>
            <tr class="<?= $c['priority']==='Emergency'?'table-danger':($isEscalated?'table-warning':'') ?>">
                <td><?= sanitize($c['student_name']) ?></td>
                <td>
                    <?= sanitize($c['title']) ?>
                    <?php if ($c['priority']==='Emergency'): ?>
                        <span class="badge bg-danger ms-1">Emergency</span>
                    <?php endif; ?>
                </td>
                <td><?= sanitize($c['category']) ?></td>
                <td><?= $c['created_at'] ?></td>
                <td>
                    <span class="badge bg-<?= $c['priority']==='Emergency'?'danger':($c['priority']==='High'?'warning':($c['priority']==='Low'?'secondary':'info')) ?>">
                        <?= sanitize($c['priority']) ?>
                    </span>
                </td>
                <td>
                    <?php
                    $map = ['Pending'=>'warning','Working'=>'info','Resolved'=>'success','Escalated to Admin'=>'danger','Under Admin Review'=>'warning','Assigned to Maintenance'=>'primary','Resolved by Admin'=>'success','Closed'=>'secondary','Rejected'=>'dark'];
                    $bg = $map[$c['status']] ?? 'secondary';
                    ?>
                    <span class="badge bg-<?= $bg ?>"><?= $c['status'] ?></span>
                </td>
                <td>
                    <?php if ($c['escalated_by']): ?>
                        <span class="small"><?= sanitize($c['warden_name'] ?: $c['escalated_by_name']) ?></span>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td><a href="?action=view&id=<?= $c['id'] ?>" class="btn btn-sm btn-info">View</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$complaints): ?><tr><td colspan="8" class="text-center text-muted">No complaints found.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?= paginationLinks($page, $pages) ?>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
