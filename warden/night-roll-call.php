<?php
$title = 'Night Roll Call';
require_once __DIR__ . '/../includes/warden-header.php';

$hostelType = getWardenHostelType();
$hostelFilter = $hostelType ? " AND s.hostel_type = '$hostelType'" : '';

$action = $_GET['action'] ?? 'list';
$page   = (int)($_GET['p'] ?? 1);
$perPage = 20;
$today  = date('Y-m-d');

$filterDate = $_GET['date'] ?? '';
$filterStatus = $_GET['status'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'mark') {
    requireCSRF();
    $date     = $_POST['date'] ?? $today;
    $students = $_POST['student_id'] ?? [];
    $statuses = $_POST['status'] ?? [];
    $remarks  = $_POST['remarks'] ?? [];

    try {
        db()->beginTransaction();
        $stmt = db()->prepare(
            "INSERT INTO night_roll_call (student_id, date, status, remarks, recorded_by)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks), recorded_by = VALUES(recorded_by)"
        );
        foreach ($students as $i => $sid) {
            $s = $statuses[$sid] ?? 'Present';
            $r = $remarks[$sid] ?? '';
            $stmt->execute([$sid, $date, $s, $r, $_SESSION['user_id']]);
        }
        db()->commit();
        auditLog('Night Roll Call Marked', 'Night Roll Call', "Roll call marked for $date");
        setAlert('success', 'Night roll call marked for ' . $date);
    } catch (Exception $e) {
        db()->rollBack();
        setAlert('danger', 'Error: ' . $e->getMessage());
    }
    redirect(BASE_URL . '/warden/night-roll-call.php');
}

// --- LIST ---
$where = '';
$params = [];
if ($filterDate !== '') {
    $where .= " AND n.date = ?";
    $params[] = $filterDate;
}
if ($filterStatus !== '') {
    $where .= " AND n.status = ?";
    $params[] = $filterStatus;
}

$countSql = "SELECT COUNT(*) FROM night_roll_call n JOIN students s ON s.id = n.student_id WHERE 1=1 $where $hostelFilter";
$countStmt = db()->prepare($countSql);
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();
$pages = paginate($page, $perPage, $totalRows);

$sql = "SELECT n.*, s.name AS student_name, s.roll_no, COALESCE(r.room_no, '-') AS room_no FROM night_roll_call n JOIN students s ON s.id = n.student_id LEFT JOIN room_allocations al ON al.student_id = s.id AND al.status = 'Active' LEFT JOIN rooms r ON r.id = al.room_id WHERE 1=1 $where $hostelFilter ORDER BY n.date DESC, s.name ASC LIMIT $perPage OFFSET {$pages['offset']}";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="mb-0"><i class="bi bi-moon-stars"></i> Night Roll Call</h5>
    <a href="?action=mark" class="btn btn-primary btn-sm <?= $action === 'mark' ? 'active' : '' ?>"><i class="bi bi-pencil-square"></i> Mark Roll Call</a>
</div>

<?php if ($action === 'mark'): ?>
<h6 class="mb-3"><i class="bi bi-pencil-square"></i> Mark Night Roll Call</h6>
<form method="post" action="?action=mark">
    <?= csrfField() ?>
    <div class="row mb-3">
        <div class="col-md-3">
            <label class="form-label">Date <span class="text-danger">*</span></label>
            <input type="date" name="date" class="form-control" value="<?= $today ?>" max="<?= $today ?>" required>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Roll No</th>
                    <th>Room</th>
                    <th>Status</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = db()->query("SELECT s.id, s.name, s.roll_no, COALESCE(r.room_no, '-') AS room_no FROM students s LEFT JOIN room_allocations al ON al.student_id = s.id AND al.status = 'Active' LEFT JOIN rooms r ON r.id = al.room_id WHERE s.status = 'Active'" . ($hostelType ? " AND s.hostel_type='$hostelType'" : '') . " ORDER BY s.name");
                $i = 0;
                while ($s = $stmt->fetch()):
                $i++;
                ?>
                <tr>
                    <td><?= $i ?></td>
                    <td><?= sanitize($s['name']) ?></td>
                    <td><?= sanitize($s['roll_no']) ?></td>
                    <td><?= sanitize($s['room_no']) ?></td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            <?php $statuses = ['Present', 'Absent', 'Outside', 'Leave', 'Late Return']; ?>
                            <?php foreach ($statuses as $st): ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="status[<?= $s['id'] ?>]" value="<?= $st ?>" <?= $st === 'Present' ? 'checked' : '' ?> id="n<?= $s['id'] ?>_<?= $st ?>">
                                <label class="form-check-label" for="n<?= $s['id'] ?>_<?= $st ?>"><?= $st ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </td>
                    <td>
                        <input type="text" name="remarks[<?= $s['id'] ?>]" class="form-control form-control-sm" placeholder="Optional" style="min-width:120px">
                    </td>
                </tr>
                <input type="hidden" name="student_id[]" value="<?= $s['id'] ?>">
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <button class="btn btn-primary"><i class="bi bi-save"></i> Save Roll Call</button>
    <a href="?" class="btn btn-outline-secondary">Cancel</a>
</form>

<?php else: ?>

<div class="d-flex gap-2 mb-3 flex-wrap">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-auto">
            <input type="date" name="date" class="form-control form-control-sm" value="<?= sanitize($filterDate) ?>" placeholder="Filter date">
        </div>
        <div class="col-auto">
            <select name="status" class="form-select form-select-sm">
                <option value="">All Status</option>
                <?php foreach (['Present', 'Absent', 'Outside', 'Leave', 'Late Return'] as $st): ?>
                <option value="<?= $st ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= $st ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i> Filter</button>
            <a href="?" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i> Clear</a>
        </div>
    </form>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Student</th>
                <th>Room</th>
                <th>Status</th>
                <th>Remarks</th>
                <th>Recorded By</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($records): $i = $pages['offset']; ?>
                <?php foreach ($records as $r): $i++; ?>
                <tr>
                    <td><?= $i ?></td>
                    <td><?= $r['date'] ?></td>
                    <td class="fw-medium"><?= sanitize($r['student_name']) ?> <small class="text-muted">(<?= sanitize($r['roll_no']) ?>)</small></td>
                    <td><?= sanitize($r['room_no']) ?></td>
                    <td>
                        <?php
                        $nBadge = match($r['status']) { 'Present' => 'success', 'Absent' => 'danger', 'Outside' => 'warning', 'Leave' => 'info', 'Late Return' => 'dark' };
                        ?>
                        <span class="badge bg-<?= $nBadge ?>"><?= $r['status'] ?></span>
                    </td>
                    <td><small><?= sanitize($r['remarks'] ?? '') ?></small></td>
                    <td><small><?= $r['recorded_by'] ? 'Warden #' . $r['recorded_by'] : '-' ?></small></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" class="text-center text-muted">No records found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?= paginationLinks($page, $pages) ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/warden-footer.php'; ?>
