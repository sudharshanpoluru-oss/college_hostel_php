<?php
$title = 'Complaints';
require_once __DIR__ . '/../includes/warden-header.php';

$hostelFilter = getHostelFilterCondition('s');

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $id = (int)$_POST['id'];

    if ($action === 'take' || $action === 'assign_to_warden') {
        $stmt = db()->prepare("UPDATE complaints SET assigned_to=?, assigned_role='warden', status='Under Inspection' WHERE id=?");
        $stmt->execute([$_SESSION['user_id'], $id]);
        logComplaintAction($id, 'Under Inspection', 'Assigned to warden for inspection');
        $stuStmt = db()->prepare("SELECT s.user_id FROM students s JOIN complaints c ON c.student_id=s.id WHERE c.id=? $hostelFilter");
        $stuStmt->execute([$id]);
        $stu = $stuStmt->fetch();
        if ($stu) addNotification((int)$stu['user_id'], 'Complaint Under Inspection', 'Your complaint #'.$id.' is now under inspection by the warden.', 'info', BASE_URL.'/student/complaints.php');
        auditLog('Assign Complaint', 'Complaints', 'Warden assigned complaint #'.$id);
        setAlert('success', 'Complaint assigned to you.');
        redirect('complaints.php?action=view&id='.$id);
    }

    if ($action === 'add_progress') {
        $notes = sanitize($_POST['notes'] ?? '');
        $progressAttachment = '';

        if (isset($_FILES['progress_attachment']) && $_FILES['progress_attachment']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['progress_attachment']['name'], PATHINFO_EXTENSION);
            $progressAttachment = uniqid('prog_') . '.' . $ext;
            move_uploaded_file($_FILES['progress_attachment']['tmp_name'], __DIR__ . '/../uploads/' . $progressAttachment);
        }

        $remarksText = $notes;
        if ($progressAttachment) {
            $remarksText .= "\n[Attachment: " . $progressAttachment . ']';
            db()->prepare("UPDATE complaints SET attachment=? WHERE id=?")->execute([$progressAttachment, $id]);
        }

        db()->prepare("UPDATE complaints SET status='In Progress' WHERE id=? AND status NOT IN ('Resolved by Warden', 'Escalated to Admin', 'Closed')")->execute([$id]);
        logComplaintAction($id, 'In Progress', $remarksText);
        auditLog('Progress Update', 'Complaints', 'Warden added progress to complaint #'.$id);
        setAlert('success', 'Progress update added.');
        redirect('complaints.php?action=view&id='.$id);
    }

    if ($action === 'resolve' || $action === 'resolve_by_warden') {
        $resolution_notes = sanitize($_POST['resolution_notes'] ?? '');
        $stmt = db()->prepare("UPDATE complaints SET status='Resolved by Warden', resolved_by=?, resolved_at=NOW(), resolution_notes=? WHERE id=?");
        $stmt->execute([$_SESSION['user_id'], $resolution_notes, $id]);
        logComplaintAction($id, 'Resolved by Warden', $resolution_notes);
        $stuStmt = db()->prepare("SELECT s.user_id FROM students s JOIN complaints c ON c.student_id=s.id WHERE c.id=? $hostelFilter");
        $stuStmt->execute([$id]);
        $stu = $stuStmt->fetch();
        if ($stu) addNotification((int)$stu['user_id'], 'Complaint Resolved', 'Your complaint #'.$id.' has been resolved by warden.', 'success', BASE_URL.'/student/complaints.php');
        auditLog('Resolve Complaint', 'Complaints', 'Warden resolved complaint #'.$id);
        setAlert('success', 'Complaint resolved successfully.');
        redirect('complaints.php?action=view&id='.$id);
    }

    if ($action === 'escalate' || $action === 'escalate_to_admin') {
        $reason = sanitize($_POST['reason'] ?? '');
        $priority = sanitize($_POST['priority'] ?? 'Medium');
        $description = sanitize($_POST['description'] ?? '');
        $attachment = '';

        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
            $attachment = uniqid('esc_') . '.' . $ext;
            move_uploaded_file($_FILES['attachment']['tmp_name'], __DIR__ . '/../uploads/' . $attachment);
        }

        $adminsStmt = db()->prepare("SELECT id FROM users WHERE role='admin' AND status=1");
        $adminsStmt->execute();
        $admins = $adminsStmt->fetchAll();
        $adminId = !empty($admins) ? (int)$admins[0]['id'] : null;

        $stmt = db()->prepare("UPDATE complaints SET escalated_to=?, escalated_by=?, escalation_reason=?, escalated_at=NOW(), status='Escalated to Admin', priority=?, description=CONCAT(description, ?) WHERE id=?");
        $descAppend = "\n\n--- Escalation Note ---\n" . $description;
        $stmt->execute([$adminId, $_SESSION['user_id'], $reason, $priority, $descAppend, $id]);

        if ($attachment) {
            db()->prepare("UPDATE complaints SET attachment=? WHERE id=?")->execute([$attachment, $id]);
        }

        logComplaintAction($id, 'Escalated to Admin', $reason);
        foreach ($admins as $admin) {
            addNotification((int)$admin['id'], 'Complaint Escalated', 'Complaint #'.$id.' escalated by warden: '.$reason, 'danger', BASE_URL.'/admin/complaints.php?action=view&id='.$id);
        }
        auditLog('Escalate Complaint', 'Complaints', 'Warden escalated complaint #'.$id.' to admin');
        setAlert('success', 'Complaint escalated to admin successfully.');
        redirect('complaints.php?action=view&id='.$id);
    }

    if ($action === 'update_status') {
        $status = sanitize($_POST['status'] ?? '');
        $remarks = sanitize($_POST['remarks'] ?? '');
        $allowed = ['Under Inspection', 'In Progress', 'Resolved by Warden'];
        if (in_array($status, $allowed)) {
            db()->prepare("UPDATE complaints SET status=? WHERE id=?")->execute([$status, $id]);
            logComplaintAction($id, $status, $remarks);
            setAlert('success', 'Complaint status updated to ' . $status);
        }
        redirect('complaints.php?action=view&id='.$id);
    }

    redirect('complaints.php');
}

if ($action === 'view' && $id) {
    $stmt = db()->prepare("SELECT c.*, s.name AS student_name, s.roll_no, s.phone, s.email, s.room_no, s.course, s.year FROM complaints c JOIN students s ON s.id=c.student_id WHERE c.id=? $hostelFilter");
    $stmt->execute([$id]);
    $c = $stmt->fetch();
    if (!$c) { setAlert('danger', 'Complaint not found.'); redirect('complaints.php'); }

    $logsStmt = db()->prepare("SELECT cl.*, u.username FROM complaint_logs cl LEFT JOIN users u ON u.id=cl.performed_by WHERE cl.complaint_id=? ORDER BY cl.created_at ASC");
    $logsStmt->execute([$id]);
    $logs = $logsStmt->fetchAll();
    ?>
    <div class="container-fluid px-0">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <a href="complaints.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to List</a>
            <div>
                <?php if ($c['assigned_to'] != $_SESSION['user_id'] && empty($c['assigned_to'])): ?>
                    <form method="post" action="?action=assign_to_warden" class="d-inline" onsubmit="return confirm('Assign this complaint to yourself?')">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <?= csrfField() ?>
                        <button class="btn btn-primary btn-sm"><i class="bi bi-hand-index"></i> Take Assignment</button>
                    </form>
                <?php endif; ?>
                <?php if ($c['assigned_to'] == $_SESSION['user_id'] && !in_array($c['status'], ['Resolved by Warden', 'Escalated to Admin', 'Closed'])): ?>
                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#resolveModal"><i class="bi bi-check-circle"></i> Resolve</button>
                    <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#escalateModal"><i class="bi bi-arrow-up-circle"></i> Escalate to Admin</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong><i class="bi bi-exclamation-triangle me-1"></i> <?= escapeOutput($c['title']) ?></strong>
                        <span class="badge bg-<?= match($c['status']){'New'=>'primary','Assigned to Warden'=>'secondary','Under Inspection'=>'info','In Progress'=>'warning','Resolved by Warden'=>'success','Escalated to Admin'=>'danger','Under Admin Review'=>'dark','Closed'=>'secondary',default=>'secondary'} ?> fs-6"><?= $c['status'] ?></span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <span class="badge bg-<?= match($c['priority']){'Emergency'=>'danger','High'=>'warning','Medium'=>'primary','Low'=>'secondary',default=>'primary'} ?> fs-6"><?= $c['priority'] ?? 'Medium' ?></span>
                            <span class="badge bg-info ms-1 fs-6"><?= escapeOutput($c['category'] ?? 'General') ?></span>
                        </div>
                        <p class="mb-1"><strong>Description:</strong></p>
                        <p class="text-muted"><?= nl2br(escapeOutput($c['description'])) ?></p>
                        <?php if ($c['attachment']): ?>
                        <p><strong>Attachment:</strong> <a href="<?= BASE_URL ?>/uploads/<?= $c['attachment'] ?>" target="_blank" class="btn btn-sm btn-outline-info"><i class="bi bi-paperclip"></i> View Attachment</a></p>
                        <?php endif; ?>
                        <?php if ($c['resolution_notes']): ?>
                        <hr><p><strong>Resolution Notes:</strong><br><?= nl2br(escapeOutput($c['resolution_notes'])) ?></p>
                        <?php endif; ?>
                        <?php if ($c['escalation_reason']): ?>
                        <hr><p><strong>Escalation Reason:</strong><br><?= nl2br(escapeOutput($c['escalation_reason'])) ?></p>
                        <?php endif; ?>
                        <hr>
                        <div class="row text-muted small">
                            <div class="col-md-4">Created: <?= date('d M Y, h:i A', strtotime($c['created_at'])) ?></div>
                            <?php if ($c['resolved_at']): ?><div class="col-md-4">Resolved: <?= date('d M Y, h:i A', strtotime($c['resolved_at'])) ?></div><?php endif; ?>
                            <?php if ($c['escalated_at']): ?><div class="col-md-4">Escalated: <?= date('d M Y, h:i A', strtotime($c['escalated_at'])) ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><i class="bi bi-clock-history me-1"></i> Status Timeline</div>
                    <div class="card-body p-0">
                        <?php if (count($logs) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($logs as $log): ?>
                            <div class="list-group-item py-3 px-3 d-flex align-items-start gap-3">
                                <div class="timeline-dot mt-1 <?= match($log['action']){'Resolved by Warden'=>'bg-success','Escalated to Admin'=>'bg-danger','Assigned to Warden'=>'bg-primary','Under Inspection'=>'bg-info','In Progress'=>'bg-warning',default=>'bg-secondary'} ?>"></div>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-medium small"><?= escapeOutput($log['action']) ?></div>
                                    <?php if (!empty($log['remarks'])): ?>
                                    <div class="text-muted small"><?= escapeOutput($log['remarks']) ?></div>
                                    <?php endif; ?>
                                    <div class="text-muted" style="font-size:0.7rem">
                                        <?= date('d M Y, h:i A', strtotime($log['created_at'])) ?>
                                        by <?= escapeOutput($log['username'] ?? 'System') ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4 text-muted small">No activity logged yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-person me-1"></i> Student Info</div>
                    <div class="card-body">
                        <p class="mb-1"><strong>Name:</strong> <?= escapeOutput($c['student_name']) ?></p>
                        <p class="mb-1"><strong>Roll No:</strong> <?= escapeOutput($c['roll_no']) ?></p>
                        <?php if ($c['room_no']): ?><p class="mb-1"><strong>Room:</strong> <?= escapeOutput($c['room_no']) ?></p><?php endif; ?>
                        <?php if ($c['course']): ?><p class="mb-1"><strong>Course:</strong> <?= escapeOutput($c['course']) ?></p><?php endif; ?>
                        <?php if ($c['year']): ?><p class="mb-1"><strong>Year:</strong> <?= escapeOutput($c['year']) ?></p><?php endif; ?>
                        <p class="mb-1"><strong>Email:</strong> <?= escapeOutput($c['email']) ?></p>
                        <p class="mb-0"><strong>Phone:</strong> <?= escapeOutput($c['phone']) ?></p>
                    </div>
                </div>

                <?php if ($c['assigned_to'] == $_SESSION['user_id'] && !in_array($c['status'], ['Resolved by Warden', 'Escalated to Admin', 'Closed'])): ?>
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-arrow-repeat me-1"></i> Quick Status Update</div>
                    <div class="card-body">
                        <form method="post" action="?action=update_status">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <?= csrfField() ?>
                            <div class="mb-2">
                                <select name="status" class="form-select form-select-sm" required>
                                    <option value="">Select Status</option>
                                    <option value="Under Inspection" <?= $c['status']=='Under Inspection'?'selected':'' ?>>Under Inspection</option>
                                    <option value="In Progress" <?= $c['status']=='In Progress'?'selected':'' ?>>In Progress</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <textarea name="remarks" class="form-control form-control-sm" rows="2" placeholder="Remarks (optional)"><?= escapeOutput($c['resolution_notes'] ?? '') ?></textarea>
                            </div>
                            <button class="btn btn-primary btn-sm w-100">Update Status</button>
                        </form>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-journal-text me-1"></i> Add Progress Update</div>
                    <div class="card-body">
                        <form method="post" action="?action=add_progress" enctype="multipart/form-data">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <?= csrfField() ?>
                            <div class="mb-2">
                                <textarea name="notes" class="form-control form-control-sm" rows="3" placeholder="Investigation notes / progress details..." required></textarea>
                            </div>
                            <div class="mb-2">
                                <input type="file" name="progress_attachment" class="form-control form-control-sm" accept="image/*,.pdf,.doc,.docx">
                                <small class="text-muted">Optional attachment (image, PDF, document)</small>
                            </div>
                            <button class="btn btn-warning btn-sm w-100"><i class="bi bi-send"></i> Add Progress</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Resolve Modal -->
    <div class="modal fade" id="resolveModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="post" action="?action=resolve_by_warden" class="modal-content">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <?= csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Resolve Complaint #<?= $c['id'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Resolution Notes</label>
                        <textarea name="resolution_notes" class="form-control" rows="4" placeholder="Describe how this complaint was resolved..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> Resolve Complaint</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Escalate Modal -->
    <div class="modal fade" id="escalateModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="post" action="?action=escalate_to_admin" class="modal-content" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <?= csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Escalate Complaint #<?= $c['id'] ?> to Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Reason for Escalation <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required placeholder="Why is this being escalated?"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                            <option value="Emergency">Emergency</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Additional Details</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Any additional information for the admin..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Attachment (optional)</label>
                        <input type="file" name="attachment" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-arrow-up-circle"></i> Escalate to Admin</button>
                </div>
            </form>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/../includes/warden-footer.php';
    exit;
}

// ====================== LIST VIEW ======================
$page    = (int)($_GET['p'] ?? 1);
$perPage = 15;
$filterStatus  = sanitize($_GET['status'] ?? '');
$filterPriority = sanitize($_GET['priority'] ?? '');
$filterDate    = sanitize($_GET['date'] ?? '');

$where = "WHERE (c.assigned_to = ? OR c.assigned_to IS NULL)";
$params = [$_SESSION['user_id']];

if ($filterStatus !== '') {
    $where .= " AND c.status = ?";
    $params[] = $filterStatus;
}
if ($filterPriority !== '') {
    $where .= " AND c.priority = ?";
    $params[] = $filterPriority;
}
if ($filterDate !== '') {
    $where .= " AND DATE(c.created_at) = ?";
    $params[] = $filterDate;
}
$where .= $hostelFilter;

$totalStmt = db()->prepare("SELECT COUNT(*) FROM complaints c JOIN students s ON s.id=c.student_id $where");
$totalStmt->execute($params);
$totalRows = $totalStmt->fetchColumn();

$pages = paginate($page, $perPage, $totalRows);
$offset = $pages['offset'];

$stmt = db()->prepare("SELECT c.*, s.name AS student_name, s.roll_no FROM complaints c JOIN students s ON s.id=c.student_id $where ORDER BY FIELD(c.status, 'New', 'Assigned to Warden', 'Under Inspection', 'In Progress', 'Escalated to Admin', 'Resolved by Warden', 'Closed') ASC, c.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$complaints = $stmt->fetchAll();
?>

<div class="container-fluid px-0">
    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-auto">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="New" <?= $filterStatus==='New'?'selected':'' ?>>New</option>
                        <option value="Assigned to Warden" <?= $filterStatus==='Assigned to Warden'?'selected':'' ?>>Assigned to Warden</option>
                        <option value="Under Inspection" <?= $filterStatus==='Under Inspection'?'selected':'' ?>>Under Inspection</option>
                        <option value="In Progress" <?= $filterStatus==='In Progress'?'selected':'' ?>>In Progress</option>
                        <option value="Resolved by Warden" <?= $filterStatus==='Resolved by Warden'?'selected':'' ?>>Resolved by Warden</option>
                        <option value="Escalated to Admin" <?= $filterStatus==='Escalated to Admin'?'selected':'' ?>>Escalated to Admin</option>
                    </select>
                </div>
                <div class="col-auto">
                    <select name="priority" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Priority</option>
                        <option value="Low" <?= $filterPriority==='Low'?'selected':'' ?>>Low</option>
                        <option value="Medium" <?= $filterPriority==='Medium'?'selected':'' ?>>Medium</option>
                        <option value="High" <?= $filterPriority==='High'?'selected':'' ?>>High</option>
                        <option value="Emergency" <?= $filterPriority==='Emergency'?'selected':'' ?>>Emergency</option>
                    </select>
                </div>
                <div class="col-auto">
                    <input type="date" name="date" class="form-control form-control-sm" value="<?= $filterDate ?>" onchange="this.form.submit()">
                </div>
                <div class="col-auto">
                    <a href="complaints.php" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-exclamation-triangle me-1"></i> All Complaints (<?= $totalRows ?>)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($complaints as $c): ?>
                        <tr>
                            <td class="fw-medium">#<?= $c['id'] ?></td>
                            <td><?= escapeOutput($c['student_name'] ?? 'N/A') ?></td>
                            <td><?= escapeOutput(mb_substr($c['title'], 0, 50)) ?></td>
                            <td><?= escapeOutput($c['category'] ?? '-') ?></td>
                            <td>
                                <span class="badge bg-<?= match($c['priority']){'Emergency'=>'danger','High'=>'warning','Medium'=>'primary','Low'=>'secondary',default=>'primary'} ?>">
                                    <?= $c['priority'] ?? 'Medium' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= match($c['status']){'New'=>'primary','Assigned to Warden'=>'secondary','Under Inspection'=>'info','In Progress'=>'warning','Resolved by Warden'=>'success','Escalated to Admin'=>'danger','Under Admin Review'=>'dark','Closed'=>'secondary',default=>'secondary'} ?>">
                                    <?= $c['status'] ?>
                                </span>
                            </td>
                            <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                            <td>
                                <a href="?action=view&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($complaints)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No complaints found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($pages['totalPages'] > 1): ?>
        <div class="card-footer">
            <?= paginationLinks($pages) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.timeline-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; margin-top: 4px; }
.timeline-dot.bg-success { background-color: #10b981; }
.timeline-dot.bg-danger { background-color: #ef4444; }
.timeline-dot.bg-primary { background-color: #3b82f6; }
.timeline-dot.bg-info { background-color: #06b6d4; }
.timeline-dot.bg-warning { background-color: #f59e0b; }
.timeline-dot.bg-secondary { background-color: #6b7280; }
</style>

<?php require_once __DIR__ . '/../includes/warden-footer.php'; ?>
