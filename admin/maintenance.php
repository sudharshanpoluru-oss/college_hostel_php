<?php
$title = 'Maintenance Management';
require_once __DIR__ . '/../includes/admin-header.php';

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$filter_status   = sanitize($_GET['status'] ?? '');
$filter_category = sanitize($_GET['category'] ?? '');
$filter_priority = sanitize($_GET['priority'] ?? '');
$search          = sanitize($_GET['search'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $id = (int)$_POST['id'];

    if (isset($_POST['assign'])) {
        $assigned_to = (int)$_POST['assigned_to'];
        db()->prepare("UPDATE maintenance_requests SET assigned_to=?, status='Assigned', assigned_date=NOW() WHERE id=?")
            ->execute([$assigned_to, $id]);
        auditLog('Assign Maintenance', 'Maintenance', "Request #$id assigned to user ID: $assigned_to");
        addNotification($assigned_to, 'Maintenance Assigned', "Maintenance request #$id has been assigned to you.", 'info', BASE_URL . '/admin/maintenance.php?action=view&id=' . $id);
        $r = db()->prepare("SELECT created_by FROM maintenance_requests WHERE id=?");
        $r->execute([$id]);
        $req = $r->fetch();
        if ($req && $req['created_by']) {
            addNotification($req['created_by'], 'Maintenance Assigned', "Your maintenance request #$id has been assigned to staff.", 'info', BASE_URL . '/student/maintenance.php?action=view&id=' . $id);
        }
        setAlert('success', 'Request assigned successfully.');
        redirect(BASE_URL . '/admin/maintenance.php?action=view&id=' . $id);
    }

    if (isset($_POST['mark_progress'])) {
        db()->prepare("UPDATE maintenance_requests SET status='In Progress' WHERE id=?")->execute([$id]);
        auditLog('Start Maintenance', 'Maintenance', "Request #$id marked In Progress");
        $r = db()->prepare("SELECT created_by FROM maintenance_requests WHERE id=?");
        $r->execute([$id]);
        $req = $r->fetch();
        if ($req && $req['created_by']) {
            addNotification($req['created_by'], 'Maintenance In Progress', "Your maintenance request #$id is now in progress.", 'info', BASE_URL . '/student/maintenance.php?action=view&id=' . $id);
        }
        setAlert('success', 'Request marked as In Progress.');
        redirect(BASE_URL . '/admin/maintenance.php?action=view&id=' . $id);
    }

    if (isset($_POST['resolve'])) {
        $remarks = sanitize($_POST['completion_remarks'] ?? '');
        db()->prepare("UPDATE maintenance_requests SET status='Resolved', completed_date=NOW(), completion_remarks=? WHERE id=?")
            ->execute([$remarks, $id]);
        auditLog('Resolve Maintenance', 'Maintenance', "Request #$id resolved: $remarks");
        $r = db()->prepare("SELECT created_by FROM maintenance_requests WHERE id=?");
        $r->execute([$id]);
        $req = $r->fetch();
        if ($req && $req['created_by']) {
            addNotification($req['created_by'], 'Maintenance Resolved', "Your maintenance request #$id has been resolved.", 'success', BASE_URL . '/student/maintenance.php?action=view&id=' . $id);
        }
        setAlert('success', 'Request resolved successfully.');
        redirect(BASE_URL . '/admin/maintenance.php?action=view&id=' . $id);
    }

    if (isset($_POST['close'])) {
        db()->prepare("UPDATE maintenance_requests SET status='Closed' WHERE id=?")->execute([$id]);
        auditLog('Close Maintenance', 'Maintenance', "Request #$id closed");
        setAlert('success', 'Request closed.');
        redirect(BASE_URL . '/admin/maintenance.php');
    }

    if (isset($_POST['reject'])) {
        $reason = sanitize($_POST['rejection_reason'] ?? '');
        db()->prepare("UPDATE maintenance_requests SET status='Rejected', completion_remarks=? WHERE id=?")
            ->execute([$reason, $id]);
        auditLog('Reject Maintenance', 'Maintenance', "Request #$id rejected: $reason");
        $r = db()->prepare("SELECT created_by FROM maintenance_requests WHERE id=?");
        $r->execute([$id]);
        $req = $r->fetch();
        if ($req && $req['created_by']) {
            addNotification($req['created_by'], 'Maintenance Rejected', "Your maintenance request #$id has been rejected: $reason", 'danger', BASE_URL . '/student/maintenance.php?action=view&id=' . $id);
        }
        setAlert('success', 'Request rejected.');
        redirect(BASE_URL . '/admin/maintenance.php');
    }

    if (isset($_POST['update_status'])) {
        $status = sanitize($_POST['status'] ?? '');
        $remarks = sanitize($_POST['remarks'] ?? '');
        $allowed = ['Pending', 'Assigned', 'In Progress', 'Resolved', 'Closed', 'Rejected'];
        if (in_array($status, $allowed)) {
            $extra = '';
            $extraParams = [];
            if ($status === 'Resolved') {
                $extra = ', completed_date=NOW(), completion_remarks=?';
                $extraParams[] = $remarks;
            } elseif ($status === 'Assigned') {
                $extra = ', assigned_date=NOW()';
            }
            $extraParams[] = $id;
            db()->prepare("UPDATE maintenance_requests SET status=? $extra WHERE id=?")
                ->execute(array_merge([$status], $extraParams));
            auditLog('Update Maintenance Status', 'Maintenance', "Request #$id status changed to $status");
            setAlert('success', "Status updated to $status.");
        }
        redirect(BASE_URL . '/admin/maintenance.php?action=view&id=' . $id);
    }
}

$page    = (int)($_GET['p'] ?? 1);
$perPage = 10;

$where = [];
$params = [];

if ($filter_status !== '') {
    $where[] = "mr.status=?";
    $params[] = $filter_status;
}
if ($filter_category !== '') {
    $where[] = "mr.category=?";
    $params[] = $filter_category;
}
if ($filter_priority !== '') {
    $where[] = "mr.priority=?";
    $params[] = $filter_priority;
}
if ($search !== '') {
    $where[] = "(mr.description LIKE ? OR COALESCE(s.name,'') LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = db()->prepare("SELECT COUNT(*) FROM maintenance_requests mr LEFT JOIN students s ON s.id=mr.student_id $whereClause");
$total->execute($params);
$totalRows = $total->fetchColumn();
$offset = ($page - 1) * $perPage;
$pages  = paginate($page, $perPage, $totalRows);

$stmt = db()->prepare("
    SELECT mr.*, s.name AS student_name, s.roll_no, r.room_no,
           a.username AS assigned_name
    FROM maintenance_requests mr
    LEFT JOIN students s ON s.id=mr.student_id
    LEFT JOIN rooms r ON r.id=mr.room_id
    LEFT JOIN users a ON a.id=mr.assigned_to
    $whereClause
    ORDER BY FIELD(mr.priority,'Emergency','High','Medium','Low'), mr.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$requests = $stmt->fetchAll();

$assignableUsers = db()->query("SELECT id, username, role FROM users WHERE role IN ('admin','warden') AND status=1 ORDER BY username")->fetchAll();
$categories = ['Electrical','Plumbing','Furniture','Internet','Cleaning','Painting','Water Supply','Carpentry','Other'];
$statuses = ['Pending','Assigned','In Progress','Resolved','Closed','Rejected'];

$statusBadgeMap = [
    'Pending'    => 'warning',
    'Assigned'   => 'primary',
    'In Progress'=> 'info',
    'Resolved'   => 'success',
    'Closed'     => 'secondary',
    'Rejected'   => 'dark',
];
$priorityBadgeMap = [
    'Low'       => 'secondary',
    'Medium'    => 'info',
    'High'      => 'warning',
    'Emergency' => 'danger',
];
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h4 class="m-0">Maintenance Requests</h4>
        <div class="d-flex flex-wrap gap-1">
            <a href="?status=Pending" class="btn btn-sm btn-outline-warning <?= $filter_status==='Pending'?'active':'' ?>">Pending</a>
            <a href="?status=Assigned" class="btn btn-sm btn-outline-primary <?= $filter_status==='Assigned'?'active':'' ?>">Assigned</a>
            <a href="?status=In Progress" class="btn btn-sm btn-outline-info <?= $filter_status==='In Progress'?'active':'' ?>">In Progress</a>
            <a href="?status=Resolved" class="btn btn-sm btn-outline-success <?= $filter_status==='Resolved'?'active':'' ?>">Resolved</a>
            <a href="?" class="btn btn-sm btn-outline-secondary">All</a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-auto">
                    <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat ?>" <?= $filter_category===$cat?'selected':'' ?>><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <select name="priority" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Priorities</option>
                        <?php foreach (['Low','Medium','High','Emergency'] as $p): ?>
                        <option value="<?= $p ?>" <?= $filter_priority===$p?'selected':'' ?>><?= $p ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search description or student..." value="<?= escapeOutput($search) ?>">
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-primary" type="submit">Search</button>
                    <a href="<?= BASE_URL ?>/admin/maintenance.php" class="btn btn-sm btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($action === 'view' && $id):
    $stmt = db()->prepare("
        SELECT mr.*, s.name AS student_name, s.roll_no, s.phone, s.email, s.room_no AS s_room,
               r.room_no, a.username AS assigned_name,
               eu.username AS escalated_by_name
        FROM maintenance_requests mr
        LEFT JOIN students s ON s.id=mr.student_id
        LEFT JOIN rooms r ON r.id=mr.room_id
        LEFT JOIN users a ON a.id=mr.assigned_to
        LEFT JOIN users eu ON eu.id=mr.escalated_to
        WHERE mr.id=?
    ");
    $stmt->execute([$id]);
    $req = $stmt->fetch();
    if (!$req) { setAlert('danger','Request not found'); redirect(BASE_URL.'/admin/maintenance.php'); }
    ?>
    <div class="card mb-3 <?= $req['priority']==='Emergency'?'border-danger':($req['status']==='In Progress'?'border-info':'') ?>">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <strong>Request #<?= $req['id'] ?></strong>
                <?php if ($req['priority']==='Emergency'): ?>
                    <span class="badge bg-danger ms-2">EMERGENCY</span>
                <?php elseif ($req['priority']==='High'): ?>
                    <span class="badge bg-warning text-dark ms-2">High Priority</span>
                <?php endif; ?>
            </div>
            <span class="badge bg-<?= $statusBadgeMap[$req['status']] ?? 'secondary' ?> fs-6"><?= $req['status'] ?></span>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Category:</strong> <?= escapeOutput($req['category']) ?></p>
                    <p><strong>Priority:</strong>
                        <span class="badge bg-<?= $priorityBadgeMap[$req['priority']] ?? 'secondary' ?>"><?= escapeOutput($req['priority']) ?></span>
                    </p>
                    <?php if ($req['student_name']): ?>
                    <p><strong>Student:</strong> <?= escapeOutput($req['student_name']) ?> (<?= escapeOutput($req['roll_no']) ?>)</p>
                    <?php endif; ?>
                    <?php if ($req['room_no']): ?>
                    <p><strong>Room:</strong> <?= escapeOutput($req['room_no']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <?php if ($req['assigned_to']): ?>
                    <p><strong>Assigned To:</strong> <?= escapeOutput($req['assigned_name'] ?? 'User #'.$req['assigned_to']) ?></p>
                    <p><strong>Assigned Date:</strong> <?= $req['assigned_date'] ? date('d M Y, h:i A', strtotime($req['assigned_date'])) : '-' ?></p>
                    <?php endif; ?>
                    <?php if ($req['completed_date']): ?>
                    <p><strong>Completed Date:</strong> <?= date('d M Y, h:i A', strtotime($req['completed_date'])) ?></p>
                    <?php endif; ?>
                    <?php if ($req['escalated_to']): ?>
                    <p><strong>Escalated To:</strong> <?= escapeOutput($req['escalated_by_name'] ?? 'User #'.$req['escalated_to']) ?></p>
                    <p><strong>Escalated At:</strong> <?= $req['escalated_at'] ? date('d M Y, h:i A', strtotime($req['escalated_at'])) : '-' ?></p>
                    <?php endif; ?>
                    <p><strong>Created:</strong> <?= date('d M Y, h:i A', strtotime($req['created_at'])) ?></p>
                </div>
            </div>
            <hr>
            <p><strong>Description:</strong></p>
            <p class="text-muted"><?= nl2br(escapeOutput($req['description'])) ?></p>
            <?php if ($req['photos']): ?>
            <p><strong>Photos:</strong></p>
            <div class="d-flex flex-wrap gap-2 mb-2">
                <?php foreach (explode(',', $req['photos']) as $photo): $photo = trim($photo); if (!$photo) continue; ?>
                <a href="<?= BASE_URL ?>/uploads/<?= $photo ?>" target="_blank">
                    <img src="<?= BASE_URL ?>/uploads/<?= $photo ?>" class="img-thumbnail" style="max-height:150px" alt="Photo">
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if ($req['escalated_reason']): ?>
            <hr><p><strong>Escalation Reason:</strong><br><?= nl2br(escapeOutput($req['escalated_reason'])) ?></p>
            <?php endif; ?>
            <?php if ($req['completion_remarks']): ?>
            <hr><p><strong>Completion Remarks:</strong><br><?= nl2br(escapeOutput($req['completion_remarks'])) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-3 border-primary">
        <div class="card-header bg-primary text-white fw-bold">Actions</div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                <?php if ($req['status'] === 'Pending'): ?>
                <form method="post" class="row g-2 align-items-end" style="display:inline-flex">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $req['id'] ?>">
                    <div class="col-auto">
                        <select name="assigned_to" class="form-select form-select-sm" required>
                            <option value="">Assign to...</option>
                            <?php foreach ($assignableUsers as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $req['assigned_to']==$u['id']?'selected':'' ?>><?= escapeOutput($u['username']) ?> (<?= $u['role'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto"><button type="submit" name="assign" class="btn btn-primary btn-sm">Assign</button></div>
                </form>
                <form method="post" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $req['id'] ?>">
                    <button type="submit" name="reject" class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject</button>
                </form>
                <?php endif; ?>

                <?php if ($req['status'] === 'Assigned'): ?>
                <form method="post" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $req['id'] ?>">
                    <button type="submit" name="mark_progress" class="btn btn-info btn-sm">Mark In Progress</button>
                </form>
                <?php endif; ?>

                <?php if (in_array($req['status'], ['Assigned', 'In Progress'])): ?>
                <form method="post" class="row g-2 align-items-end" style="display:inline-flex">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $req['id'] ?>">
                    <div class="col-auto">
                        <input type="text" name="completion_remarks" class="form-control form-control-sm" placeholder="Resolution notes" required>
                    </div>
                    <div class="col-auto"><button type="submit" name="resolve" class="btn btn-success btn-sm">Resolve</button></div>
                </form>
                <?php endif; ?>

                <?php if (!in_array($req['status'], ['Closed', 'Rejected'])): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Close this request?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $req['id'] ?>">
                    <button type="submit" name="close" class="btn btn-secondary btn-sm">Close</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="post" class="modal-content">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $req['id'] ?>">
                <div class="modal-header"><h5 class="modal-title">Reject Request #<?= $req['id'] ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label>Reason for Rejection</label>
                    <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="reject" class="btn btn-dark">Reject</button>
                </div>
            </form>
        </div>
    </div>

    <a href="<?= BASE_URL ?>/admin/maintenance.php" class="btn btn-secondary mt-2">&larr; Back to List</a>

    <?php else: ?>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th>Room</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $r): ?>
                        <tr class="<?= $r['priority']==='Emergency'?'table-danger':($r['status']==='In Progress'?'table-info':'') ?>">
                            <td class="fw-medium">#<?= $r['id'] ?></td>
                            <td><?= escapeOutput($r['student_name'] ?? '—') ?></td>
                            <td><?= escapeOutput($r['room_no'] ?? '—') ?></td>
                            <td><?= escapeOutput($r['category']) ?></td>
                            <td>
                                <span class="badge bg-<?= $priorityBadgeMap[$r['priority']] ?? 'secondary' ?>"><?= escapeOutput($r['priority']) ?></span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $statusBadgeMap[$r['status']] ?? 'secondary' ?>"><?= $r['status'] ?></span>
                            </td>
                            <td><?= escapeOutput($r['assigned_name'] ?? '—') ?></td>
                            <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                            <td><a href="?action=view&id=<?= $r['id'] ?>" class="btn btn-sm btn-info">View</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$requests): ?><tr><td colspan="9" class="text-center text-muted">No maintenance requests found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($pages['totalPages'] > 1): ?>
        <div class="card-footer"><?= paginationLinks($page, $pages) ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
