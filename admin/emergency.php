<?php
$title = 'Emergency Management';
require_once __DIR__ . '/../includes/admin-header.php';

$tableExists = false;
try {
    db()->query("SELECT 1 FROM emergency_reports LIMIT 1");
    $tableExists = true;
} catch (Exception $e) {
    setAlert('warning', 'Run the migration SQL (sql/migration_v3.sql) to enable the Emergency module.');
}

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$filterCategory = sanitize($_GET['category'] ?? '');
$filterStatus   = sanitize($_GET['status'] ?? '');
$filterPriority = sanitize($_GET['priority'] ?? '');

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
        redirect(BASE_URL . '/admin/emergency.php');
    }

    $id = (int)$_POST['id'];

    if (isset($_POST['assign'])) {
        $assigned_to = (int)$_POST['assigned_to'];
        db()->prepare("UPDATE emergency_reports SET assigned_to=?, status='Assigned' WHERE id=?")
            ->execute([$assigned_to, $id]);
        logEmergencyAction($id, 'Assigned', "Assigned to user ID: $assigned_to");

        $r = db()->prepare("SELECT reporter_id FROM emergency_reports WHERE id=?");
        $r->execute([$id]);
        $rep = $r->fetch();
        if ($rep && $rep['reporter_id']) {
            addNotification((int)$rep['reporter_id'], 'Emergency Assigned', "Your emergency report #$id has been assigned to staff.", 'info', BASE_URL.'/student/emergency.php');
        }
        auditLog('Assign Emergency', 'Emergency', "Emergency #$id assigned to user $assigned_to");
        setAlert('success', 'Emergency assigned successfully.');
        redirect(BASE_URL . '/admin/emergency.php?action=view&id=' . $id);
    }

    if (isset($_POST['mark_in_progress'])) {
        db()->prepare("UPDATE emergency_reports SET status='In Progress' WHERE id=?")->execute([$id]);
        logEmergencyAction($id, 'In Progress', 'Marked as in progress');
        auditLog('Update Emergency', 'Emergency', "Emergency #$id marked In Progress");
        setAlert('success', 'Emergency marked in progress.');
        redirect(BASE_URL . '/admin/emergency.php?action=view&id=' . $id);
    }

    if (isset($_POST['resolve'])) {
        $resolution = sanitize($_POST['resolution'] ?? '');
        $now = date('Y-m-d H:i:s');
        db()->prepare("UPDATE emergency_reports SET status='Resolved', resolution=?, resolved_at=? WHERE id=?")
            ->execute([$resolution, $now, $id]);
        logEmergencyAction($id, 'Resolved', $resolution);

        $r = db()->prepare("SELECT reporter_id FROM emergency_reports WHERE id=?");
        $r->execute([$id]);
        $rep = $r->fetch();
        if ($rep && $rep['reporter_id']) {
            addNotification((int)$rep['reporter_id'], 'Emergency Resolved', "Your emergency report #$id has been resolved.", 'success', BASE_URL.'/student/emergency.php');
        }
        auditLog('Resolve Emergency', 'Emergency', "Emergency #$id resolved");
        setAlert('success', 'Emergency resolved.');
        redirect(BASE_URL . '/admin/emergency.php?action=view&id=' . $id);
    }

    if (isset($_POST['close'])) {
        db()->prepare("UPDATE emergency_reports SET status='Closed' WHERE id=?")->execute([$id]);
        logEmergencyAction($id, 'Closed', 'Closed by admin');
        auditLog('Close Emergency', 'Emergency', "Emergency #$id closed");
        setAlert('success', 'Emergency closed.');
        redirect(BASE_URL . '/admin/emergency.php');
    }

    if (isset($_POST['reopen'])) {
        db()->prepare("UPDATE emergency_reports SET status='Open', resolution=NULL, resolved_at=NULL WHERE id=?")->execute([$id]);
        logEmergencyAction($id, 'Reopened', 'Reopened by admin');
        auditLog('Reopen Emergency', 'Emergency', "Emergency #$id reopened");
        setAlert('success', 'Emergency reopened.');
        redirect(BASE_URL . '/admin/emergency.php?action=view&id=' . $id);
    }
}

if ($action === 'view' && $id) {
    if (!$tableExists) { setAlert('danger', 'Not available.'); redirect(BASE_URL . '/admin/emergency.php'); }
    $stmt = db()->prepare("SELECT * FROM emergency_reports WHERE id=?");
    $stmt->execute([$id]);
    $e = $stmt->fetch();
    if (!$e) { setAlert('danger', 'Not found'); redirect(BASE_URL . '/admin/emergency.php'); }

    $logs = db()->prepare("SELECT el.*, u.username FROM emergency_logs el LEFT JOIN users u ON u.id=el.performed_by WHERE el.emergency_id=? ORDER BY el.created_at ASC");
    $logs->execute([$id]);
    $timeline = $logs->fetchAll();

    $assignableUsers = db()->query("SELECT id, username FROM users WHERE role IN ('admin','warden') ORDER BY username")->fetchAll();
    ?>
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <a href="<?= BASE_URL ?>/admin/emergency.php" class="btn btn-outline-secondary btn-sm">&larr; Back to List</a>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($e['status'] === 'Open'): ?>
                <form method="post" style="display:inline" action="?action=view&id=<?= $id ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $e['id'] ?>">
                    <button type="submit" name="mark_in_progress" class="btn btn-warning btn-sm">Mark In Progress</button>
                </form>
                <?php endif; ?>
                <?php if (in_array($e['status'], ['Open', 'Assigned', 'In Progress'])): ?>
                <form method="post" class="row g-1 align-items-end" style="display:inline-flex" action="?action=view&id=<?= $id ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $e['id'] ?>">
                    <div class="col-auto">
                        <select name="assigned_to" class="form-select form-select-sm" required>
                            <option value="">Assign to...</option>
                            <?php foreach ($assignableUsers as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $e['assigned_to']==$u['id']?'selected':'' ?>><?= sanitize($u['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto"><button type="submit" name="assign" class="btn btn-primary btn-sm">Assign</button></div>
                </form>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#resolveModal">Resolve</button>
                <?php endif; ?>
                <?php if (in_array($e['status'], ['Resolved', 'Closed'])): ?>
                <form method="post" style="display:inline" action="?action=view&id=<?= $id ?>" onsubmit="return confirm('Reopen this emergency?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $e['id'] ?>">
                    <button type="submit" name="reopen" class="btn btn-info btn-sm">Reopen</button>
                </form>
                <?php endif; ?>
                <?php if ($e['status'] !== 'Closed'): ?>
                <form method="post" style="display:inline" action="?action=view&id=<?= $id ?>" onsubmit="return confirm('Close this emergency?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $e['id'] ?>">
                    <button type="submit" name="close" class="btn btn-secondary btn-sm">Close</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3 <?= $e['priority']==='Critical'?'border-danger':($e['priority']==='High'?'border-warning':'') ?>">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <strong>Emergency #<?= $e['id'] ?></strong>
                            <?php if ($e['priority']==='Critical'): ?><span class="badge bg-danger ms-2">CRITICAL</span>
                            <?php elseif ($e['priority']==='High'): ?><span class="badge bg-warning text-dark ms-2">HIGH</span>
                            <?php elseif ($e['priority']==='Low'): ?><span class="badge bg-secondary ms-2">Low</span>
                            <?php else: ?><span class="badge bg-info ms-2">Medium</span><?php endif; ?>
                        </div>
                        <span class="badge bg-<?= match($e['status']){'Open'=>'danger','Assigned'=>'primary','In Progress'=>'warning','Resolved'=>'success','Closed'=>'secondary',default=>'secondary'} ?> fs-6"><?= $e['status'] ?></span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Category:</strong> <?= sanitize($e['category']) ?></p>
                                <p><strong>Location:</strong> <?= sanitize($e['location']) ?></p>
                                <p><strong>Reporter:</strong> <?= sanitize($e['reporter_name']) ?></p>
                                <p><strong>Phone:</strong> <?= sanitize($e['reporter_phone']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Created:</strong> <?= $e['created_at'] ?></p>
                                <?php if ($e['assigned_to']): ?><p><strong>Assigned To:</strong> User #<?= $e['assigned_to'] ?></p><?php endif; ?>
                                <?php if ($e['resolved_at']): ?><p><strong>Resolved At:</strong> <?= $e['resolved_at'] ?></p><?php endif; ?>
                            </div>
                        </div>
                        <p><strong>Description:</strong><br><?= nl2br(sanitize($e['description'])) ?></p>
                        <?php if ($e['resolution']): ?>
                        <hr><p><strong>Resolution:</strong><br><?= nl2br(sanitize($e['resolution'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($timeline): ?>
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-clock-history me-1"></i> Timeline</div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($timeline as $log): ?>
                            <div class="list-group-item py-3 px-3 d-flex align-items-start gap-3">
                                <div class="timeline-dot mt-1 <?= match($log['action']){'Resolved'=>'bg-success','Assigned'=>'bg-primary','In Progress'=>'bg-warning','Closed'=>'bg-secondary','Reopened'=>'bg-info',default=>'bg-secondary'} ?>"></div>
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
                    <button type="submit" name="resolve" class="btn btn-success">Resolve</button>
                </div>
            </form>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/../includes/admin-footer.php';
    exit;
}

$page    = (int)($_GET['p'] ?? 1);
$perPage = 10;

$where  = [];
$params = [];

if ($filterCategory !== '') {
    $where[] = 'category = ?';
    $params[] = $filterCategory;
}
if ($filterStatus !== '') {
    $where[] = 'status = ?';
    $params[] = $filterStatus;
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
?>
<div class="container-fluid">
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
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="Open" <?= $filterStatus==='Open'?'selected':'' ?>>Open</option>
                        <option value="Assigned" <?= $filterStatus==='Assigned'?'selected':'' ?>>Assigned</option>
                        <option value="In Progress" <?= $filterStatus==='In Progress'?'selected':'' ?>>In Progress</option>
                        <option value="Resolved" <?= $filterStatus==='Resolved'?'selected':'' ?>>Resolved</option>
                        <option value="Closed" <?= $filterStatus==='Closed'?'selected':'' ?>>Closed</option>
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
                    <a href="<?= BASE_URL ?>/admin/emergency.php" class="btn btn-sm btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-exclamation-triangle me-1"></i> Emergency Reports (<?= $totalRows ?? 0 ?>)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Reporter</th>
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
                                <span class="badge bg-<?= match($e['status']){'Open'=>'danger','Assigned'=>'primary','In Progress'=>'warning','Resolved'=>'success','Closed'=>'secondary',default=>'secondary'} ?>">
                                    <?= $e['status'] ?>
                                </span>
                            </td>
                            <td><?= $e['created_at'] ?></td>
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
<style>
.timeline-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; margin-top: 4px; }
.timeline-dot.bg-success { background-color: #10b981; }
.timeline-dot.bg-danger { background-color: #ef4444; }
.timeline-dot.bg-primary { background-color: #3b82f6; }
.timeline-dot.bg-info { background-color: #06b6d4; }
.timeline-dot.bg-warning { background-color: #f59e0b; }
.timeline-dot.bg-secondary { background-color: #6b7280; }
</style>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
