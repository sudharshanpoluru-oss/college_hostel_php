<?php
$title = 'Discipline Records';
require_once __DIR__ . '/../includes/warden-header.php';

$hostelFilter = getHostelFilterCondition('s');

$action = $_GET['action'] ?? 'list';
$page   = (int)($_GET['p'] ?? 1);
$perPage = 20;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    requireCSRF();
    $student_id = (int)($_POST['student_id'] ?? 0);
    $room_id = (int)($_POST['room_id'] ?? 0);
    $warning_type = $_POST['warning_type'] ?? 'Verbal';
    $misconduct_type = trim($_POST['misconduct_type'] ?? '');
    $fine_amount = (float)($_POST['fine_amount'] ?? 0);
    $action_taken = trim($_POST['action_taken'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    try {
        $stmt = db()->prepare("INSERT INTO discipline_records (student_id, room_id, warning_type, misconduct_type, fine_amount, action_taken, remarks, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$student_id, $room_id, $warning_type, $misconduct_type, $fine_amount, $action_taken, $remarks, $_SESSION['user_id']]);
        auditLog('Discipline Record Added', 'Discipline', "Record added for student ID $student_id");
        setAlert('success', 'Discipline record added successfully.');
    } catch (Exception $e) {
        setAlert('danger', 'Error: ' . $e->getMessage());
    }
    redirect(BASE_URL . '/warden/discipline.php');
}

// Auto-fetch room on student select (AJAX helper via session variable)
$studentId = (int)($_GET['student_id'] ?? 0);
$autoRoomId = 0;
if ($studentId) {
    $rm = db()->prepare("SELECT room_id FROM room_allocations WHERE student_id = ? AND status = 'Active' LIMIT 1");
    $rm->execute([$studentId]);
    $autoRoomId = (int)$rm->fetchColumn();
}

// --- LIST ---
$countSql = "SELECT COUNT(*) FROM discipline_records d JOIN students s ON s.id = d.student_id WHERE 1=1 $hostelFilter";
$totalRows = db()->query($countSql)->fetchColumn();
$pages = paginate($page, $perPage, $totalRows);

$sql = "SELECT d.*, s.name AS student_name, s.roll_no, s.photo, r.room_no FROM discipline_records d JOIN students s ON s.id = d.student_id LEFT JOIN rooms r ON r.id = d.room_id WHERE 1=1 $hostelFilter ORDER BY d.created_at DESC LIMIT $perPage OFFSET {$pages['offset']}";
$records = db()->query($sql)->fetchAll();
$students = db()->query("SELECT s.id, s.name, s.roll_no FROM students s WHERE s.status = 'Active'" . getHostelFilterCondition('s') . " ORDER BY s.name")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="mb-0"><i class="bi bi-shield-exclamation"></i> Discipline Records</h5>
    <a href="?action=add" class="btn btn-primary btn-sm <?= $action === 'add' ? 'active' : '' ?>"><i class="bi bi-plus-lg"></i> Add Record</a>
</div>

<?php if ($action === 'add'): ?>
<div class="card">
    <div class="card-header"><strong>Add Discipline Record</strong></div>
    <div class="card-body">
        <form method="post" action="?action=add">
            <?= csrfField() ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Student <span class="text-danger">*</span></label>
                    <select name="student_id" class="form-select" id="studentSelect" required>
                        <option value="">Select Student</option>
                        <?php foreach ($students as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $studentId === (int)$s['id'] ? 'selected' : '' ?>><?= sanitize($s['name']) ?> (<?= sanitize($s['roll_no']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Room</label>
                    <input type="text" class="form-control" id="roomDisplay" value="<?= $autoRoomId ? '(Auto-selected)' : '' ?>" readonly>
                    <input type="hidden" name="room_id" id="roomIdInput" value="<?= $autoRoomId ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Warning Type <span class="text-danger">*</span></label>
                    <select name="warning_type" class="form-select" required>
                        <option value="Verbal">Verbal</option>
                        <option value="Written">Written</option>
                        <option value="Final">Final</option>
                        <option value="Fine">Fine</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Misconduct Type</label>
                    <input type="text" name="misconduct_type" class="form-control" placeholder="e.g. Late night noise">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fine Amount (₹)</label>
                    <input type="number" name="fine_amount" class="form-control" min="0" step="0.01" value="0.00">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Action Taken</label>
                    <textarea name="action_taken" class="form-control" rows="2" placeholder="Action taken..."></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="mt-3">
                <button class="btn btn-primary"><i class="bi bi-save"></i> Save Record</button>
                <a href="?" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('studentSelect')?.addEventListener('change', function() {
    const sid = this.value;
    if (sid) {
        fetch('<?= BASE_URL ?>/warden/discipline.php?action=list&student_id=' + sid)
            .then(r => r.text())
            .then(html => {
                // Parse room from the page (we embedded it in the form)
                // Actually just redirect to reload with student_id param
                window.location.href = '?action=add&student_id=' + sid;
            });
    }
});
</script>

<?php else: ?>

<div class="table-responsive">
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Student</th>
                <th>Room</th>
                <th>Warning Type</th>
                <th>Misconduct</th>
                <th>Fine</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($records): $i = $pages['offset']; ?>
                <?php foreach ($records as $r): $i++; ?>
                <tr>
                    <td><?= $i ?></td>
                    <td class="fw-medium"><?= sanitize($r['student_name']) ?> <small class="text-muted">(<?= sanitize($r['roll_no']) ?>)</small></td>
                    <td><?= sanitize($r['room_no'] ?? '-') ?></td>
                    <td>
                        <?php $wBadge = match($r['warning_type']) { 'Verbal' => 'secondary', 'Written' => 'warning', 'Final' => 'danger', 'Fine' => 'info' }; ?>
                        <span class="badge bg-<?= $wBadge ?>"><?= $r['warning_type'] ?></span>
                    </td>
                    <td><small><?= sanitize($r['misconduct_type'] ?? '-') ?></small></td>
                    <td><?= $r['fine_amount'] > 0 ? '₹' . number_format($r['fine_amount'], 2) : '-' ?></td>
                    <td><small><?= date('d M Y', strtotime($r['created_at'])) ?></small></td>
                    <td>
                        <a href="#" class="btn btn-sm btn-outline-info" title="Details"
                           onclick="alert('Student: <?= sanitize($r['student_name']) ?>\nWarning: <?= $r['warning_type'] ?>\nMisconduct: <?= sanitize($r['misconduct_type'] ?? 'N/A') ?>\nFine: <?= $r['fine_amount'] > 0 ? '₹' . number_format($r['fine_amount'], 2) : 'None' ?>\nAction Taken: <?= sanitize($r['action_taken'] ?? 'N/A') ?>\nRemarks: <?= sanitize($r['remarks'] ?? 'N/A') ?>'); return false;">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="8" class="text-center text-muted">No discipline records found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?= paginationLinks($page, $pages) ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/warden-footer.php'; ?>
