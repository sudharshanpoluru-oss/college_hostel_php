<?php
$title = 'Emergency Management';
require_once __DIR__ . '/../includes/warden-header.php';

$tableExists = false;
try {
    db()->query("SELECT 1 FROM emergency_reports LIMIT 1");
    $tableExists = true;
} catch (Exception $e) {
    setAlert('warning', 'Run the migration SQL (sql/migration_v3.sql) to enable the Emergency module.');
}

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

function logEmergencyAction($emergencyId, $action, $remarks = null) {
    $stmt = db()->prepare(
        "INSERT INTO emergency_logs (emergency_id, action, performed_by, role, remarks) 
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $emergencyId,
        $action,
        $_SESSION['user_id'] ?? null,
        $_SESSION['role'] ?? null,
        $remarks
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();

    if (!$tableExists) {
        setAlert('danger', 'Emergency module not available. Run the migration.');
        redirect(BASE_URL . '/warden/emergency.php');
    }

    if (isset($_POST['report'])) {
        $category   = sanitize($_POST['category']);
        $priority   = sanitize($_POST['priority']);
        $description = sanitize($_POST['description']);
        $location   = sanitize($_POST['location']);
        $reporter_name  = sanitize($_POST['reporter_name']);
        $reporter_phone = sanitize($_POST['reporter_phone']);
        $reporter_id    = (int)($_POST['reporter_id'] ?? $_SESSION['user_id']);

        $stmt = db()->prepare("INSERT INTO emergency_reports (category, priority, description, location, reporter_name, reporter_phone, reporter_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$category, $priority, $description, $location, $reporter_name, $reporter_phone, $reporter_id]);
        $newId = db()->lastInsertId();

        logEmergencyAction($newId, 'Reported', 'Reported by warden');
        auditLog('Report Emergency', 'Emergency', "Emergency #$newId reported by warden");

        $admins = db()->query("SELECT id FROM users WHERE role='admin' AND status=1")->fetchAll();
        foreach ($admins as $admin) {
            addNotification((int)$admin['id'], 'New Emergency: ' . $priority, "Emergency #$newId reported by warden at $location", 'danger', BASE_URL.'/admin/emergency.php?action=view&id='.$newId);
        }

        setAlert('success', 'Emergency reported successfully.');
        redirect(BASE_URL . '/warden/emergency.php');
    }

    if (isset($_POST['resolve'])) {
        $id = (int)$_POST['id'];
        $resolution = sanitize($_POST['resolution'] ?? '');
        $now = date('Y-m-d H:i:s');
        db()->prepare("UPDATE emergency_reports SET status='Resolved', resolution=?, resolved_at=? WHERE id=?")
            ->execute([$resolution, $now, $id]);
        logEmergencyAction($id, 'Resolved', $resolution);
        auditLog('Resolve Emergency', 'Emergency', "Warden resolved emergency #$id");

        $r = db()->prepare("SELECT reporter_id FROM emergency_reports WHERE id=?");
        $r->execute([$id]);
        $rep = $r->fetch();
        if ($rep && $rep['reporter_id']) {
            addNotification((int)$rep['reporter_id'], 'Emergency Resolved', "Your emergency report #$id has been resolved.", 'success', BASE_URL.'/student/emergency.php');
        }
        setAlert('success', 'Emergency resolved.');
        redirect(BASE_URL . '/warden/emergency.php?action=view&id=' . $id);
    }

    if (isset($_POST['escalate'])) {
        $id = (int)$_POST['id'];
        $reason = sanitize($_POST['reason'] ?? '');
        db()->prepare("UPDATE emergency_reports SET status='Escalated to Admin' WHERE id=?")->execute([$id]);
        logEmergencyAction($id, 'Escalated to Admin', $reason);
        auditLog('Escalate Emergency', 'Emergency', "Warden escalated emergency #$id to admin");

        $admins = db()->query("SELECT id FROM users WHERE role='admin' AND status=1")->fetchAll();
        foreach ($admins as $admin) {
            addNotification((int)$admin['id'], 'Emergency Escalated', "Emergency #$id escalated by warden: $reason", 'danger', BASE_URL.'/admin/emergency.php?action=view&id='.$id);
        }
        setAlert('success', 'Emergency escalated to admin.');
        redirect(BASE_URL . '/warden/emergency.php?action=view&id=' . $id);
    }
}

if ($action === 'view' && $id) {
    if (!$tableExists) { setAlert('danger', 'Emergency module not available.'); redirect(BASE_URL . '/warden/emergency.php'); }
    $stmt = db()->prepare("SELECT * FROM emergency_reports WHERE id=?");
    $stmt->execute([$id]);
    $e = $stmt->fetch();
    if (!$e) { setAlert('danger', 'Not found'); redirect(BASE_URL . '/warden/emergency.php'); }

    $logs = db()->prepare("SELECT el.*, u.username FROM emergency_logs el LEFT JOIN users u ON u.id=el.performed_by WHERE el.emergency_id=? ORDER BY el.created_at ASC");
    $logs->execute([$id]);
    $timeline = $logs->fetchAll();
    ?>
    <div class="container-fluid px-0">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <a href="<?= BASE_URL ?>/warden/emergency.php" class="btn btn-outline-secondary btn-sm">&larr; Back</a>
            <div class="d-flex gap-2">
                <?php if (in_array($e['status'], ['Open', 'Assigned', 'In Progress'])): ?>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#resolveModal"><i class="bi bi-check-circle"></i> Resolve</button>
                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#escalateModal"><i class="bi bi-arrow-up-circle"></i> Escalate to Admin</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3 <?= $e['priority']==='Critical'?'border-danger':($e['priority']==='High'?'border-warning':'') ?>">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>Emergency #<?= $e['id'] ?></strong>
                        <span class="badge bg-<?= match($e['status']){'Open'=>'danger','Assigned'=>'primary','In Progress'=>'warning','Resolved'=>'success','Escalated to Admin'=>'danger','Closed'=>'secondary',default=>'secondary'} ?> fs-6"><?= $e['status'] ?></span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <span class="badge bg-<?= $e['priority']==='Critical'?'danger':($e['priority']==='High'?'warning':($e['priority']==='Low'?'secondary':'info')) ?> fs-6"><?= sanitize($e['priority']) ?></span>
                            <span class="badge bg-info ms-1 fs-6"><?= sanitize($e['category']) ?></span>
                        </div>
                        <p><strong>Location:</strong> <?= sanitize($e['location']) ?></p>
                        <p><strong>Reported By:</strong> <?= sanitize($e['reporter_name']) ?> (<?= sanitize($e['reporter_phone']) ?>)</p>
                        <p><strong>Description:</strong><br><?= nl2br(sanitize($e['description'])) ?></p>
                        <?php if ($e['resolution']): ?>
                        <hr><p><strong>Resolution:</strong><br><?= nl2br(sanitize($e['resolution'])) ?></p>
                        <?php endif; ?>
                        <hr>
                        <div class="row text-muted small">
                            <div class="col-md-4">Created: <?= $e['created_at'] ?></div>
                            <?php if ($e['resolved_at']): ?><div class="col-md-4">Resolved: <?= $e['resolved_at'] ?></div><?php endif; ?>
                            <?php if ($e['assigned_to']): ?><div class="col-md-4">Assigned To: User #<?= $e['assigned_to'] ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($timeline): ?>
                <div class="card">
                    <div class="card-header"><i class="bi bi-clock-history me-1"></i> Timeline</div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($timeline as $log): ?>
                            <div class="list-group-item py-3 px-3 d-flex align-items-start gap-3">
                                <div class="timeline-dot mt-1 <?= match($log['action']){'Resolved'=>'bg-success','Assigned'=>'bg-primary','In Progress'=>'bg-warning','Reported'=>'bg-danger','Escalated to Admin'=>'bg-danger','Closed'=>'bg-secondary',default=>'bg-secondary'} ?>"></div>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-medium small"><?= sanitize($log['action']) ?></div>
                                    <?php if (!empty($log['remarks'])): ?>
                                    <div class="text-muted small"><?= sanitize($log['remarks']) ?></div>
                                    <?php endif; ?>
                                    <div class="text-muted" style="font-size:0.7rem">
                                        <?= $log['created_at'] ?> by <?= sanitize($log['username']??'System') ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="resolveModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="post" class="modal-content" action="?action=view&id=<?= $id ?>">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $e['id'] ?>">
                <div class="modal-header"><h5 class="modal-title">Resolve Emergency #<?= $e['id'] ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label">Resolution Details</label>
                    <textarea name="resolution" class="form-control" rows="4" required placeholder="Describe how this emergency was resolved..."></textarea>
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
            <form method="post" class="modal-content" action="?action=view&id=<?= $id ?>">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $e['id'] ?>">
                <div class="modal-header"><h5 class="modal-title">Escalate Emergency #<?= $e['id'] ?> to Admin</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label">Reason for Escalation <span class="text-danger">*</span></label>
                    <textarea name="reason" class="form-control" rows="3" required placeholder="Why is this being escalated?"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="escalate" class="btn btn-danger"><i class="bi bi-arrow-up-circle"></i> Escalate to Admin</button>
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
$filterStatus   = sanitize($_GET['status'] ?? '');
$filterCategory = sanitize($_GET['category'] ?? '');
$filterPriority = sanitize($_GET['priority'] ?? '');

$where  = [];
$params = [];

$hostelType = getWardenHostelType();

if ($hostelType) {
    $where[] = "reporter_id IN (SELECT user_id FROM students WHERE hostel_type=?)";
    $params[] = $hostelType;
}

if ($filterStatus !== '') {
    $where[] = 'status = ?';
    $params[] = $filterStatus;
}
if ($filterCategory !== '') {
    $where[] = 'category = ?';
    $params[] = $filterCategory;
}
if ($filterPriority !== '') {
    $where[] = 'priority = ?';
    $params[] = $filterPriority;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$reports = []; $pagesInfo = ['total' => 0, 'page' => $page, 'perPage' => $perPage, 'offset' => 0, 'pages' => 1];

if ($tableExists) {
    $total = db()->prepare("SELECT COUNT(*) FROM emergency_reports $whereClause");
    $total->execute($params);
    $totalRows = $total->fetchColumn();
    $pagesInfo = paginate($page, $perPage, $totalRows);
    $offset = $pagesInfo['offset'];

    $stmt = db()->prepare("
        SELECT * FROM emergency_reports
        $whereClause
        ORDER BY
            CASE priority WHEN 'Critical' THEN 0 WHEN 'High' THEN 1 WHEN 'Medium' THEN 2 ELSE 3 END,
            created_at DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $reports = $stmt->fetchAll();
}

$wardenName = '';
$wardenPhone = '';
$wStmt = db()->prepare("SELECT name, phone FROM wardens WHERE user_id=?");
$wStmt->execute([$_SESSION['user_id']]);
$w = $wStmt->fetch();
if ($w) { $wardenName = $w['name']; $wardenPhone = $w['phone']; }
?>
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="m-0">Emergency Reports</h5>
        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#reportModal"><i class="bi bi-plus-lg"></i> Report Emergency</button>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-auto">
                    <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <option value="Fire" <?= $filterCategory==='Fire'?'selected':'' ?>>Fire</option>
                        <option value="Medical" <?= $filterCategory==='Medical'?'selected':'' ?>>Medical</option>
                        <option value="Security" <?= $filterCategory==='Security'?'selected':'' ?>>Security</option>
                        <option value="Infrastructure" <?= $filterCategory==='Infrastructure'?'selected':'' ?>>Infrastructure</option>
                        <option value="Natural Disaster" <?= $filterCategory==='Natural Disaster'?'selected':'' ?>>Natural Disaster</option>
                        <option value="Other" <?= $filterCategory==='Other'?'selected':'' ?>>Other</option>
                    </select>
                </div>
                <div class="col-auto">
                    <select name="priority" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Priority</option>
                        <option value="Critical" <?= $filterPriority==='Critical'?'selected':'' ?>>Critical</option>
                        <option value="High" <?= $filterPriority==='High'?'selected':'' ?>>High</option>
                        <option value="Medium" <?= $filterPriority==='Medium'?'selected':'' ?>>Medium</option>
                        <option value="Low" <?= $filterPriority==='Low'?'selected':'' ?>>Low</option>
                    </select>
                </div>
                <div class="col-auto">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="Open" <?= $filterStatus==='Open'?'selected':'' ?>>Open</option>
                        <option value="Assigned" <?= $filterStatus==='Assigned'?'selected':'' ?>>Assigned</option>
                        <option value="In Progress" <?= $filterStatus==='In Progress'?'selected':'' ?>>In Progress</option>
                        <option value="Resolved" <?= $filterStatus==='Resolved'?'selected':'' ?>>Resolved</option>
                        <option value="Escalated to Admin" <?= $filterStatus==='Escalated to Admin'?'selected':'' ?>>Escalated</option>
                        <option value="Closed" <?= $filterStatus==='Closed'?'selected':'' ?>>Closed</option>
                    </select>
                </div>
                <div class="col-auto">
                    <a href="<?= BASE_URL ?>/warden/emergency.php" class="btn btn-sm btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-exclamation-triangle me-1"></i> All Emergencies (<?= $totalRows ?? 0 ?>)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Reported By</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $e): ?>
                        <tr class="<?= $e['priority']==='Critical'?'table-danger':($e['priority']==='High'?'table-warning':'') ?>">
                            <td class="fw-medium">#<?= $e['id'] ?></td>
                            <td><span class="badge bg-info"><?= sanitize($e['category']) ?></span></td>
                            <td><?= sanitize($e['location']) ?></td>
                            <td><?= sanitize($e['reporter_name']) ?></td>
                            <td>
                                <span class="badge bg-<?= $e['priority']==='Critical'?'danger':($e['priority']==='High'?'warning':($e['priority']==='Low'?'secondary':'info')) ?>">
                                    <?= sanitize($e['priority']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= match($e['status']){'Open'=>'danger','Assigned'=>'primary','In Progress'=>'warning','Resolved'=>'success','Escalated to Admin'=>'danger','Closed'=>'secondary',default=>'secondary'} ?>">
                                    <?= $e['status'] ?>
                                </span>
                            </td>
                            <td><?= date('d M Y', strtotime($e['created_at'])) ?></td>
                            <td><a href="?action=view&id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$reports): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No emergency reports found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($pagesInfo['totalPages'] > 1): ?>
        <div class="card-footer"><?= paginationLinks($page, $pagesInfo) ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="post" class="modal-content" action="">
            <?= csrfField() ?>
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-danger me-1"></i> Report Emergency</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <select name="category" class="form-select" required>
                            <option value="">Select Category</option>
                            <option value="Fire">Fire</option>
                            <option value="Medical">Medical</option>
                            <option value="Security">Security</option>
                            <option value="Infrastructure">Infrastructure</option>
                            <option value="Natural Disaster">Natural Disaster</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Priority <span class="text-danger">*</span></label>
                        <select name="priority" class="form-select" required>
                            <option value="">Select Priority</option>
                            <option value="Low">Low</option>
                            <option value="Medium">Medium</option>
                            <option value="High">High</option>
                            <option value="Critical">Critical</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Your Name <span class="text-danger">*</span></label>
                        <input type="text" name="reporter_name" class="form-control" value="<?= sanitize($wardenName) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone <span class="text-danger">*</span></label>
                        <input type="text" name="reporter_phone" class="form-control" value="<?= sanitize($wardenPhone) ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Location <span class="text-danger">*</span></label>
                        <input type="text" name="location" class="form-control" required placeholder="e.g., Block A, Room 101">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" rows="4" required placeholder="Describe the emergency situation in detail..."></textarea>
                    </div>
                </div>
                <input type="hidden" name="reporter_id" value="<?= $_SESSION['user_id'] ?>">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="report" class="btn btn-danger"><i class="bi bi-send"></i> Report Emergency</button>
            </div>
        </form>
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
