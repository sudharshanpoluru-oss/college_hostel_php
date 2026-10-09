<?php
$title = 'Maintenance';
require_once __DIR__ . '/../includes/warden-header.php';

$hostelType = getWardenHostelType();

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$filter_status   = sanitize($_GET['status'] ?? '');
$filter_category = sanitize($_GET['category'] ?? '');
$filter_priority = sanitize($_GET['priority'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $id = (int)$_POST['id'];

    if ($action === 'escalate') {
        $reason = sanitize($_POST['reason'] ?? '');
        $adminStmt = db()->prepare("SELECT id FROM users WHERE role='admin' AND status=1 LIMIT 1");
        $adminStmt->execute();
        $admin = $adminStmt->fetch();
        $adminId = $admin ? (int)$admin['id'] : null;

        db()->prepare("UPDATE maintenance_requests SET escalated_to=?, escalated_reason=?, escalated_at=NOW(), status='Pending' WHERE id=?")
            ->execute([$adminId, $reason, $id]);
        auditLog('Escalate Maintenance', 'Maintenance', "Request #$id escalated to admin: $reason");
        if ($adminId) {
            addNotification($adminId, 'Maintenance Escalated', "Maintenance request #$id escalated by warden: $reason", 'danger', BASE_URL . '/admin/maintenance.php?action=view&id=' . $id);
        }
        setAlert('success', 'Request escalated to admin.');
        redirect('maintenance.php?action=view&id=' . $id);
    }

    if (isset($_POST['resolve'])) {
        $remarks = sanitize($_POST['completion_remarks'] ?? '');
        db()->prepare("UPDATE maintenance_requests SET status='Resolved', completed_date=NOW(), completion_remarks=? WHERE id=?")
            ->execute([$remarks, $id]);
        auditLog('Resolve Maintenance', 'Maintenance', "Warden resolved request #$id: $remarks");
        $r = db()->prepare("SELECT created_by FROM maintenance_requests WHERE id=?");
        $r->execute([$id]);
        $req = $r->fetch();
        if ($req && $req['created_by']) {
            addNotification($req['created_by'], 'Maintenance Resolved', "Your maintenance request #$id has been resolved.", 'success', BASE_URL . '/student/maintenance.php?action=view&id=' . $id);
        }
        setAlert('success', 'Request resolved successfully.');
        redirect('maintenance.php?action=view&id=' . $id);
    }

    if (isset($_POST['close'])) {
        db()->prepare("UPDATE maintenance_requests SET status='Closed' WHERE id=?")->execute([$id]);
        auditLog('Close Maintenance', 'Maintenance', "Warden closed request #$id");
        setAlert('success', 'Request closed.');
        redirect('maintenance.php');
    }

    if (isset($_POST['update_status'])) {
        $status = sanitize($_POST['status'] ?? '');
        $remarks = sanitize($_POST['remarks'] ?? '');
        $allowed = ['Assigned', 'In Progress'];
        if (in_array($status, $allowed)) {
            $extra = $status === 'Assigned' ? ', assigned_date=NOW()' : '';
            db()->prepare("UPDATE maintenance_requests SET status=? $extra WHERE id=?")->execute([$status, $id]);
            auditLog('Update Maintenance', 'Maintenance', "Request #$id status changed to $status");
            setAlert('success', "Status updated to $status.");
        }
        redirect('maintenance.php?action=view&id=' . $id);
    }

    redirect('maintenance.php');
}

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

if ($action === 'view' && $id) {
    $stmt = db()->prepare("
        SELECT mr.*, s.name AS student_name, s.roll_no, s.phone, s.email, r.room_no,
               a.username AS assigned_name
        FROM maintenance_requests mr
        LEFT JOIN students s ON s.id=mr.student_id AND (? IS NULL OR s.hostel_type=?)
        LEFT JOIN rooms r ON r.id=mr.room_id
        LEFT JOIN users a ON a.id=mr.assigned_to
        WHERE mr.id=?
    ");
    $stmt->execute([$hostelType, $hostelType, $id]);
    $req = $stmt->fetch();
    if (!$req) { setAlert('danger', 'Request not found.'); redirect('maintenance.php'); }

    ?>
    <div class="container-fluid px-0">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <a href="maintenance.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to List</a>
            <div>
                <?php if (!in_array($req['status'], ['Resolved', 'Closed', 'Rejected'])): ?>
                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#resolveModal"><i class="bi bi-check-circle"></i> Resolve</button>
                    <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#escalateModal"><i class="bi bi-arrow-up-circle"></i> Escalate to Admin</button>
                    <button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#closeModal"><i class="bi bi-x-circle"></i> Close</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong><i class="bi bi-tools me-1"></i> Maintenance Request #<?= $req['id'] ?></strong>
                        <span class="badge bg-<?= $statusBadgeMap[$req['status']] ?? 'secondary' ?> fs-6"><?= $req['status'] ?></span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <span class="badge bg-<?= $priorityBadgeMap[$req['priority']] ?? 'primary' ?> fs-6"><?= $req['priority'] ?? 'Medium' ?></span>
                            <span class="badge bg-info ms-1 fs-6"><?= escapeOutput($req['category'] ?? 'Other') ?></span>
                        </div>
                        <p class="mb-1"><strong>Description:</strong></p>
                        <p class="text-muted"><?= nl2br(escapeOutput($req['description'])) ?></p>
                        <?php if ($req['photos']): ?>
                        <p><strong>Photos:</strong></p>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <?php foreach (explode(',', $req['photos']) as $photo): $photo = trim($photo); if (!$photo) continue; ?>
                            <a href="<?= BASE_URL ?>/uploads/<?= $photo ?>" target="_blank">
                                <img src="<?= BASE_URL ?>/uploads/<?= $photo ?>" class="img-thumbnail" style="max-height:120px" alt="Photo">
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($req['completion_remarks']): ?>
                        <hr><p><strong>Completion Remarks:</strong><br><?= nl2br(escapeOutput($req['completion_remarks'])) ?></p>
                        <?php endif; ?>
                        <?php if ($req['escalated_reason']): ?>
                        <hr><p><strong>Escalation Reason:</strong><br><?= nl2br(escapeOutput($req['escalated_reason'])) ?></p>
                        <?php endif; ?>
                        <hr>
                        <div class="row text-muted small">
                            <div class="col-md-4">Created: <?= date('d M Y, h:i A', strtotime($req['created_at'])) ?></div>
                            <?php if ($req['assigned_date']): ?><div class="col-md-4">Assigned: <?= date('d M Y, h:i A', strtotime($req['assigned_date'])) ?></div><?php endif; ?>
                            <?php if ($req['completed_date']): ?><div class="col-md-4">Completed: <?= date('d M Y, h:i A', strtotime($req['completed_date'])) ?></div><?php endif; ?>
                            <?php if ($req['escalated_at']): ?><div class="col-md-4">Escalated: <?= date('d M Y, h:i A', strtotime($req['escalated_at'])) ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-person me-1"></i> Student Info</div>
                    <div class="card-body">
                        <?php if ($req['student_name']): ?>
                        <p class="mb-1"><strong>Name:</strong> <?= escapeOutput($req['student_name']) ?></p>
                        <p class="mb-1"><strong>Roll No:</strong> <?= escapeOutput($req['roll_no']) ?></p>
                        <?php if ($req['room_no']): ?><p class="mb-1"><strong>Room:</strong> <?= escapeOutput($req['room_no']) ?></p><?php endif; ?>
                        <p class="mb-1"><strong>Email:</strong> <?= escapeOutput($req['email']) ?></p>
                        <p class="mb-0"><strong>Phone:</strong> <?= escapeOutput($req['phone']) ?></p>
                        <?php else: ?>
                        <p class="text-muted mb-0">No student associated (staff-created request)</p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($req['assigned_to']): ?>
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-person-badge me-1"></i> Assigned To</div>
                    <div class="card-body">
                        <p class="mb-0"><?= escapeOutput($req['assigned_name'] ?? 'User #'.$req['assigned_to']) ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!in_array($req['status'], ['Resolved', 'Closed', 'Rejected'])): ?>
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-arrow-repeat me-1"></i> Quick Status Update</div>
                    <div class="card-body">
                        <form method="post" action="?action=view&amp;id=<?= $id ?>">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= $req['id'] ?>">
                            <div class="mb-2">
                                <select name="status" class="form-select form-select-sm" required>
                                    <option value="">Select Status</option>
                                    <option value="Assigned" <?= $req['status']==='Assigned'?'selected':'' ?>>Assigned</option>
                                    <option value="In Progress" <?= $req['status']==='In Progress'?'selected':'' ?>>In Progress</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <textarea name="remarks" class="form-control form-control-sm" rows="2" placeholder="Remarks (optional)"><?= escapeOutput($req['completion_remarks'] ?? '') ?></textarea>
                            </div>
                            <button class="btn btn-primary btn-sm w-100">Update Status</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="resolveModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="post" action="?action=view&amp;id=<?= $id ?>" class="modal-content">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $req['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Resolve Request #<?= $req['id'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Completion Remarks</label>
                        <textarea name="completion_remarks" class="form-control" rows="4" placeholder="Describe how this request was resolved..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="resolve" class="btn btn-success"><i class="bi bi-check-circle"></i> Resolve</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="escalateModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="post" action="?action=escalate&amp;id=<?= $id ?>" class="modal-content">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $req['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Escalate Request #<?= $req['id'] ?> to Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Reason for Escalation <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required placeholder="Why is this being escalated?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="escalate" class="btn btn-danger"><i class="bi bi-arrow-up-circle"></i> Escalate to Admin</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="closeModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="post" action="?action=view&amp;id=<?= $id ?>" class="modal-content">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $req['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Close Request #<?= $req['id'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to close this maintenance request?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="close" class="btn btn-secondary">Close Request</button>
                </div>
            </form>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/../includes/warden-footer.php';
    exit;
}

$page    = (int)($_GET['p'] ?? 1);
$perPage = 15;

$where = [];
$params = [];

if ($hostelType) {
    $where[] = "s.hostel_type=?";
    $params[] = $hostelType;
}

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

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$totalStmt = db()->prepare("SELECT COUNT(*) FROM maintenance_requests mr LEFT JOIN students s ON s.id=mr.student_id $whereClause");
$totalStmt->execute($params);
$totalRows = $totalStmt->fetchColumn();

$pages = paginate($page, $perPage, $totalRows);
$offset = $pages['offset'];

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

$categories = ['Electrical','Plumbing','Furniture','Internet','Cleaning','Painting','Water Supply','Carpentry','Other'];

?>

<div class="container-fluid px-0">
    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-auto">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <?php foreach (['Pending','Assigned','In Progress','Resolved','Closed','Rejected'] as $st): ?>
                        <option value="<?= $st ?>" <?= $filter_status===$st?'selected':'' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
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
                        <option value="">All Priority</option>
                        <?php foreach (['Low','Medium','High','Emergency'] as $p): ?>
                        <option value="<?= $p ?>" <?= $filter_priority===$p?'selected':'' ?>><?= $p ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <a href="maintenance.php" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-tools me-1"></i> Maintenance Requests (<?= $totalRows ?>)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
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
                        <tr>
                            <td class="fw-medium">#<?= $r['id'] ?></td>
                            <td><?= escapeOutput($r['student_name'] ?? '—') ?></td>
                            <td><?= escapeOutput($r['room_no'] ?? '—') ?></td>
                            <td><?= escapeOutput($r['category']) ?></td>
                            <td>
                                <span class="badge bg-<?= $priorityBadgeMap[$r['priority']] ?? 'secondary' ?>"><?= $r['priority'] ?? 'Medium' ?></span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $statusBadgeMap[$r['status']] ?? 'secondary' ?>"><?= $r['status'] ?></span>
                            </td>
                            <td><?= escapeOutput($r['assigned_name'] ?? '—') ?></td>
                            <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                            <td>
                                <a href="?action=view&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($requests)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No maintenance requests found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($pages['totalPages'] > 1): ?>
        <div class="card-footer"><?= paginationLinks($pages) ?></div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/warden-footer.php'; ?>
