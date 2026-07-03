<?php
$title = 'Emergency Reporting';
require_once __DIR__ . '/../includes/student-header.php';

$tableExists = false;
try {
    db()->query("SELECT 1 FROM emergency_reports LIMIT 1");
    $tableExists = true;
} catch (Exception $e) {
    setAlert('warning', 'Emergency module not available yet.');
}

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();

    if (!$tableExists) {
        setAlert('danger', 'Emergency module not available.');
        redirect(BASE_URL . '/student/emergency.php');
    }

    if (isset($_POST['report'])) {
        $category    = sanitize($_POST['category']);
        $description = sanitize($_POST['description']);
        $location    = sanitize($_POST['location']);
        $reporter_name  = sanitize($_POST['reporter_name']);
        $reporter_phone = sanitize($_POST['reporter_phone']);
        $reporter_id    = (int)$_SESSION['user_id'];

        $stmt = db()->prepare("INSERT INTO emergency_reports (category, priority, description, location, reporter_name, reporter_phone, reporter_id)
            VALUES (?, 'Critical', ?, ?, ?, ?, ?)");
        $stmt->execute([$category, $description, $location, $reporter_name, $reporter_phone, $reporter_id]);
        $newId = db()->lastInsertId();

        $logStmt = db()->prepare("INSERT INTO emergency_logs (emergency_id, action, performed_by, role, remarks) VALUES (?, 'Reported', ?, ?, 'Reported by student')");
        $logStmt->execute([$newId, $_SESSION['user_id'], $_SESSION['role']]);

        auditLog('Report Emergency', 'Emergency', "Student reported emergency #$newId");

        $admins = db()->query("SELECT id FROM users WHERE role='admin' AND status=1")->fetchAll();
        foreach ($admins as $admin) {
            addNotification((int)$admin['id'], 'New Emergency Report', "Student $reporter_name reported an emergency at $location", 'danger', BASE_URL.'/admin/emergency.php?action=view&id='.$newId);
        }

        setAlert('success', 'Emergency reported successfully. Authorities have been notified.');
        redirect(BASE_URL . '/student/emergency.php');
    }
}

$reports = [];
if ($tableExists) {
    $stmt = db()->prepare("SELECT * FROM emergency_reports WHERE reporter_id=? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $reports = $stmt->fetchAll();
}

$student = db()->prepare("SELECT name, phone FROM students WHERE user_id=?");
$student->execute([$_SESSION['user_id']]);
$s = $student->fetch();
$studentName  = $s ? $s['name'] : '';
$studentPhone = $s ? $s['phone'] : '';
?>
<div class="container-fluid px-0">
    <div class="alert alert-danger border-start border-4 border-danger mb-3">
        <strong><i class="bi bi-exclamation-triangle-fill me-1"></i> For genuine emergencies only.</strong>
        Misuse of this system may result in disciplinary action.
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white fw-bold"><i class="bi bi-plus-circle me-1"></i> Report Emergency</div>
                <div class="card-body">
                    <form method="post">
                        <?= csrfField() ?>
                        <div class="mb-3">
                            <label class="form-label">Your Name</label>
                            <input type="text" name="reporter_name" class="form-control" value="<?= sanitize($studentName) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="reporter_phone" class="form-control" value="<?= sanitize($studentPhone) ?>" required>
                        </div>
                        <div class="mb-3">
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
                        <div class="mb-3">
                            <label class="form-label">Location <span class="text-danger">*</span></label>
                            <input type="text" name="location" class="form-control" required placeholder="e.g., Block A, Room 101">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="4" required placeholder="Describe the emergency situation..."></textarea>
                        </div>
                        <button type="submit" name="report" class="btn btn-danger w-100"><i class="bi bi-send"></i> Submit Emergency Report</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header fw-bold"><i class="bi bi-list me-1"></i> My Reports</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Category</th>
                                    <th>Location</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports as $e): ?>
                                <tr class="<?= $e['priority']==='Critical'?'table-danger':'' ?>">
                                    <td class="fw-medium">#<?= $e['id'] ?></td>
                                    <td><span class="badge bg-info"><?= sanitize($e['category']) ?></span></td>
                                    <td><?= sanitize($e['location']) ?></td>
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
                                </tr>
                                <?php endforeach; ?>
                                <?php if (!$reports): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">No reports yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/student-footer.php'; ?>
