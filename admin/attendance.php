<?php
$title = 'Attendance Management';
require_once __DIR__ . '/../includes/admin-header.php';

// --- CSV Export ---
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $where = '1=1'; $params = [];
    if (!empty($_GET['date'])) { $where .= " AND a.date=?"; $params[] = $_GET['date']; }
    if (!empty($_GET['from'])) { $where .= " AND a.date>=?"; $params[] = $_GET['from']; }
    if (!empty($_GET['to'])) { $where .= " AND a.date<=?"; $params[] = $_GET['to']; }
    if (!empty($_GET['student'])) { $where .= " AND (s.name LIKE ? OR s.roll_no LIKE ?)"; $params[] = "%{$_GET['student']}%"; $params[] = "%{$_GET['student']}%"; }
    if (!empty($_GET['room'])) { $where .= " AND r.room_no=?"; $params[] = $_GET['room']; }
    if (!empty($_GET['course'])) { $where .= " AND s.course=?"; $params[] = $_GET['course']; }
    if (!empty($_GET['year'])) { $where .= " AND s.year=?"; $params[] = $_GET['year']; }
    $sql = "SELECT a.date, s.name, s.roll_no, COALESCE(r.room_no,'-') AS room, s.course, s.year, a.status, a.remarks, COALESCE(u.username,a.taken_role,'-') AS taken_by, a.time FROM attendance a JOIN students s ON s.id=a.student_id LEFT JOIN room_allocations al ON al.student_id=s.id AND al.status='Active' LEFT JOIN rooms r ON r.id=al.room_id LEFT JOIN users u ON u.id=a.taken_by WHERE $where ORDER BY a.date DESC, s.name";
    $stmt = db()->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=attendance_export_'.date('Ymd').'.csv');
    $out = fopen('php://output','w');
    fputcsv($out, ['Date','Student','Roll No','Room','Course','Year','Status','Remarks','Taken By','Time']);
    foreach ($rows as $r) fputcsv($out, [$r['date'],$r['name'],$r['roll_no'],$r['room'],$r['course'],$r['year'],$r['status'],$r['remarks'],$r['taken_by'],$r['time']]);
    fclose($out); exit;
}

$action = $_GET['action'] ?? 'list';
$page   = (int)($_GET['p'] ?? 1);
$perPage = 10;
$today  = date('Y-m-d');

// --- POST: Mark Attendance ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'mark') {
    $date    = $_POST['date'] ?? $today;
    $students = $_POST['student_id'] ?? [];
    $statuses = $_POST['status'] ?? [];
    $remarks = $_POST['remarks'] ?? [];
    try {
        db()->beginTransaction();
        $locked = db()->prepare("SELECT student_id FROM attendance WHERE date=? AND is_locked=1");
        $locked->execute([$date]);
        $lockedIds = $locked->fetchAll(PDO::FETCH_COLUMN);
        $stmt = db()->prepare("INSERT INTO attendance (student_id,date,status,remarks,taken_by,taken_role,is_locked,time) VALUES (?,?,?,?,?,'admin',0,NOW()) ON DUPLICATE KEY UPDATE status=VALUES(status),remarks=VALUES(remarks),taken_by=VALUES(taken_by),taken_role=VALUES(taken_role),time=NOW()");
        foreach ($students as $sid) {
            if (in_array($sid, $lockedIds)) continue;
            $s = $statuses[$sid] ?? 'Present';
            $r = $remarks[$sid] ?? '';
            $stmt->execute([$sid, $date, $s, $r, $_SESSION['user_id']]);
        }
        db()->commit();
        setAlert('success', 'Attendance marked for ' . $date);
    } catch (Exception $e) {
        db()->rollBack();
        setAlert('danger', 'Error: ' . $e->getMessage());
    }
    redirect(BASE_URL . '/admin/attendance.php');
}

// --- POST: Lock/Unlock ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action_lock'])) {
        $d = $_POST['lock_date'] ?? $today;
        db()->prepare("UPDATE attendance SET is_locked=1 WHERE date=?")->execute([$d]);
        auditLog('Attendance Locked','Attendance','Locked for '.$d);
        setAlert('success','Attendance locked for '.$d);
        redirect(BASE_URL.'/admin/attendance.php');
    }
    if (isset($_POST['action_unlock'])) {
        $d = $_POST['unlock_date'] ?? $today;
        db()->prepare("UPDATE attendance SET is_locked=0 WHERE date=?")->execute([$d]);
        auditLog('Attendance Unlocked','Attendance','Unlocked for '.$d);
        setAlert('success','Attendance unlocked for '.$d);
        redirect(BASE_URL.'/admin/attendance.php');
    }
}

$summaryDate = $_GET['date'] ?? $today;

$sumStmt = db()->prepare("SELECT COUNT(*) AS total, SUM(status='Present') AS present, SUM(status='Absent') AS absent, SUM(status='Late') AS late, SUM(status IN ('On Leave','Medical Leave','Weekend Leave')) AS on_leave FROM attendance WHERE date=?");
$sumStmt->execute([$summaryDate]);
$sum = $sumStmt->fetch();
$sTotal = $sum['total'] ?: 0; $sPresent = $sum['present'] ?: 0; $sAbsent = $sum['absent'] ?: 0; $sLate = $sum['late'] ?: 0; $sLeave = $sum['on_leave'] ?: 0; $sPct = $sTotal ? round($sPresent/$sTotal*100,1) : 0;

$overall = db()->query("SELECT ROUND(SUM(status='Present')/COUNT(*)*100,1) FROM attendance")->fetchColumn() ?: 0;

// Auto-detect alerts
$noAttendToday = db()->prepare("SELECT COUNT(*) FROM attendance WHERE date=?");
$noAttendToday->execute([$today]);
$noAttendToday = $noAttendToday->fetchColumn() == 0;

$freqLate = db()->query("SELECT s.id,s.name,s.roll_no,COUNT(*) AS cnt FROM attendance a JOIN students s ON s.id=a.student_id WHERE a.status='Late' AND a.date>=DATE_SUB(CURDATE(),INTERVAL 30 DAY) GROUP BY s.id HAVING cnt>3")->fetchAll();

$consecAbsent = db()->prepare("SELECT DISTINCT s.id,s.name,s.roll_no FROM students s WHERE s.status='Active' AND EXISTS(SELECT 1 FROM attendance a WHERE a.student_id=s.id AND a.status='Absent' AND a.date=?) AND EXISTS(SELECT 1 FROM attendance a WHERE a.student_id=s.id AND a.status='Absent' AND a.date=DATE_SUB(?,INTERVAL 1 DAY)) AND EXISTS(SELECT 1 FROM attendance a WHERE a.student_id=s.id AND a.status='Absent' AND a.date=DATE_SUB(?,INTERVAL 2 DAY))");
$consecAbsent->execute([$today,$today,$today]);
$consecAbsent = $consecAbsent->fetchAll();

// Filter building
$where = '1=1'; $params = [];
if (!empty($_GET['date'])) { $where .= " AND a.date=?"; $params[] = $_GET['date']; }
if (!empty($_GET['from'])) { $where .= " AND a.date>=?"; $params[] = $_GET['from']; }
if (!empty($_GET['to'])) { $where .= " AND a.date<=?"; $params[] = $_GET['to']; }
if (!empty($_GET['student'])) { $where .= " AND (s.name LIKE ? OR s.roll_no LIKE ?)"; $params[] = "%{$_GET['student']}%"; $params[] = "%{$_GET['student']}%"; }
if (!empty($_GET['room'])) { $where .= " AND r.room_no=?"; $params[] = $_GET['room']; }
if (!empty($_GET['course'])) { $where .= " AND s.course=?"; $params[] = $_GET['course']; }
if (!empty($_GET['year'])) { $where .= " AND s.year=?"; $params[] = $_GET['year']; }

$countSql = "SELECT COUNT(*) FROM attendance a JOIN students s ON s.id=a.student_id LEFT JOIN room_allocations al ON al.student_id=s.id AND al.status='Active' LEFT JOIN rooms r ON r.id=al.room_id WHERE $where";
$total = db()->prepare($countSql); $total->execute($params); $totalRows = $total->fetchColumn();
$pages = paginate($page, $perPage, $totalRows);
$offset = $pages['offset'];
?>
<div class="container-fluid">

<?php if (count($consecAbsent)): ?>
<div class="alert alert-danger alert-dismissible fade show"><strong><i class="bi bi-exclamation-triangle"></i> Consecutive Absences:</strong> <?= count($consecAbsent) ?> student(s) absent for 3+ consecutive days.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if (count($freqLate)): ?>
<div class="alert alert-warning alert-dismissible fade show"><strong><i class="bi bi-clock"></i> Frequent Late:</strong> <?= count($freqLate) ?> student(s) with 3+ late marks in last 30 days.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($noAttendToday): ?>
<div class="alert alert-info alert-dismissible fade show"><strong><i class="bi bi-info-circle"></i> No Attendance Today:</strong> No records for <?= $today ?>.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-2"><div class="card text-bg-primary"><div class="card-body text-center"><h5><?= $sTotal ?></h5><p>Total (<?= $summaryDate ?>)</p></div></div></div>
    <div class="col-md-2"><div class="card text-bg-success"><div class="card-body text-center"><h5><?= $sPresent ?></h5><p>Present</p></div></div></div>
    <div class="col-md-2"><div class="card text-bg-danger"><div class="card-body text-center"><h5><?= $sAbsent ?></h5><p>Absent</p></div></div></div>
    <div class="col-md-2"><div class="card text-bg-warning"><div class="card-body text-center"><h5><?= $sLate ?></h5><p>Late</p></div></div></div>
    <div class="col-md-2"><div class="card text-bg-info"><div class="card-body text-center"><h5><?= $sLeave ?></h5><p>On Leave</p></div></div></div>
    <div class="col-md-2"><div class="card text-bg-secondary"><div class="card-body text-center"><h5><?= $sPct ?>%</h5><p>% Present</p></div></div></div>
</div>

<div class="d-flex gap-2 mb-3 flex-wrap">
    <a href="?action=mark" class="btn btn-primary <?= $action==='mark'?'active':'' ?>"><i class="bi bi-pencil-square"></i> Mark Attendance</a>
    <a href="?action=list" class="btn btn-info <?= $action==='list'?'active':'' ?>"><i class="bi bi-clock-history"></i> History</a>
</div>

<?php if ($action === 'mark'): ?>

<h5 class="mb-3"><i class="bi bi-pencil-square"></i> Mark Attendance</h5>
<form method="post" action="?action=mark">
    <div class="row mb-3">
        <div class="col-md-3"><label class="form-label">Date</label><input type="date" name="date" class="form-control" value="<?= $today ?>" max="<?= $today ?>" required></div>
    </div>
    <?php
    $markDate = $_GET['date'] ?? $today;
    $lockedForDate = db()->prepare("SELECT student_id FROM attendance WHERE date=? AND is_locked=1");
    $lockedForDate->execute([$markDate]);
    $lockedIds = $lockedForDate->fetchAll(PDO::FETCH_COLUMN);
    $studSql = "SELECT s.id, s.name, s.roll_no, COALESCE(r.room_no,'-') AS room_no FROM students s LEFT JOIN room_allocations al ON al.student_id=s.id AND al.status='Active' LEFT JOIN rooms r ON r.id=al.room_id WHERE s.status='Active' ORDER BY s.name";
    $studList = db()->query($studSql)->fetchAll();
    ?>
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-dark"><tr><th>#</th><th>Name</th><th>Roll No</th><th>Room</th><th>Status</th><th>Remarks</th></tr></thead>
            <tbody>
                <?php $i=0; foreach ($studList as $s): $i++; $isLocked = in_array($s['id'], $lockedIds); ?>
                <tr>
                    <td><?= $i ?></td>
                    <td><?= sanitize($s['name']) ?></td>
                    <td><?= sanitize($s['roll_no']) ?></td>
                    <td><?= sanitize($s['room_no']) ?></td>
                    <td>
                        <?php if ($isLocked): ?>
                            <span class="badge bg-secondary">Locked</span>
                            <?php $ex = db()->prepare("SELECT status FROM attendance WHERE student_id=? AND date=?"); $ex->execute([$s['id'],$markDate]); $exStatus = $ex->fetchColumn(); ?>
                            <span class="badge bg-info"><?= $exStatus ?: 'N/A' ?></span>
                        <?php else: ?>
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach (['Present','Absent','Late','On Leave','Medical Leave'] as $st): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="status[<?= $s['id'] ?>]" value="<?= $st ?>" <?= $st==='Present'?'checked':'' ?> id="s<?= $s['id'] ?>_<?= $st ?>">
                                    <label class="form-check-label" for="s<?= $s['id'] ?>_<?= $st ?>"><?= $st ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><?= $isLocked ? '<em class="text-muted small">Locked</em>' : '<input type="text" name="remarks['.$s['id'].']" class="form-control form-control-sm" placeholder="Optional" style="min-width:120px">' ?></td>
                </tr>
                <input type="hidden" name="student_id[]" value="<?= $s['id'] ?>">
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <button class="btn btn-primary"><i class="bi bi-save"></i> Save Attendance</button>
    <a href="?action=list" class="btn btn-outline-secondary">Cancel</a>
</form>

<?php else: ?>

<form method="get" class="row g-2 mb-3">
    <input type="hidden" name="action" value="list">
    <div class="col-auto"><input type="date" name="from" class="form-control form-control-sm" value="<?= sanitize($_GET['from']??'') ?>" placeholder="From"></div>
    <div class="col-auto"><input type="date" name="to" class="form-control form-control-sm" value="<?= sanitize($_GET['to']??'') ?>" placeholder="To"></div>
    <div class="col-auto">
        <select name="room" class="form-select form-select-sm">
            <option value="">All Rooms</option>
            <?php foreach (db()->query("SELECT id,room_no FROM rooms ORDER BY room_no") as $rm): ?>
            <option value="<?= $rm['room_no'] ?>" <?= ($_GET['room']??'')===$rm['room_no']?'selected':'' ?>><?= sanitize($rm['room_no']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <select name="course" class="form-select form-select-sm">
            <option value="">All Courses</option>
            <?php foreach (db()->query("SELECT DISTINCT course FROM students WHERE course IS NOT NULL ORDER BY course") as $c): ?>
            <option value="<?= $c['course'] ?>" <?= ($_GET['course']??'')===$c['course']?'selected':'' ?>><?= sanitize($c['course']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <select name="year" class="form-select form-select-sm">
            <option value="">All Years</option>
            <?php foreach (db()->query("SELECT DISTINCT year FROM students WHERE year IS NOT NULL ORDER BY year") as $y): ?>
            <option value="<?= $y['year'] ?>" <?= ($_GET['year']??'')===$y['year']?'selected':'' ?>><?= sanitize($y['year']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto"><input type="text" name="student" class="form-control form-control-sm" placeholder="Name or Roll No" value="<?= sanitize($_GET['student']??'') ?>"></div>
    <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i> Filter</button><a href="?action=list" class="btn btn-sm btn-outline-danger ms-1"><i class="bi bi-x"></i> Clear</a></div>
</form>

<div class="d-flex justify-content-between align-items-center mb-2">
    <span></span>
    <div class="d-flex gap-2">
        <form method="post" action="?action=lock" class="d-inline"><input type="hidden" name="lock_date" value="<?= sanitize($_GET['from']??$today) ?>"><button type="submit" name="action_lock" class="btn btn-sm btn-warning" onclick="return confirm('Lock attendance for this date range?')"><i class="bi bi-lock"></i> Lock</button></form>
        <form method="post" action="?action=unlock" class="d-inline"><input type="hidden" name="unlock_date" value="<?= sanitize($_GET['from']??$today) ?>"><button type="submit" name="action_unlock" class="btn btn-sm btn-outline-warning" onclick="return confirm('Unlock attendance for this date range?')"><i class="bi bi-unlock"></i> Unlock</button></form>
        <a href="?<?= $_SERVER['QUERY_STRING'] ?>&export=excel" class="btn btn-sm btn-success"><i class="bi bi-file-earmark-excel"></i> Excel</a>
        <a href="#" onclick="window.print();return false;" class="btn btn-sm btn-primary"><i class="bi bi-printer"></i> Print</a>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-hover">
        <thead class="table-dark"><tr><th>#</th><th>Date</th><th>Time</th><th>Student</th><th>Room</th><th>Status</th><th>Taken By</th><th>Remarks</th><th>Locked</th></tr></thead>
        <tbody>
            <?php
            $sql = "SELECT a.*, s.name AS student_name, s.roll_no, COALESCE(r.room_no,'-') AS room_no, u.username AS taken_by_name FROM attendance a JOIN students s ON s.id=a.student_id LEFT JOIN room_allocations al ON al.student_id=s.id AND al.status='Active' LEFT JOIN rooms r ON r.id=al.room_id LEFT JOIN users u ON u.id=a.taken_by WHERE $where ORDER BY a.date DESC, a.time DESC LIMIT $perPage OFFSET $offset";
            $stmt = db()->prepare($sql); $stmt->execute($params); $records = $stmt->fetchAll();
            ?>
            <?php if ($records): $i = $offset; foreach ($records as $r): $i++; ?>
            <tr>
                <td><?= $i ?></td>
                <td><?= $r['date'] ?></td>
                <td><?= $r['time'] ? date('h:i A',strtotime($r['time'])) : '-' ?></td>
                <td><?= sanitize($r['student_name']) ?> <small class="text-muted">(<?= sanitize($r['roll_no']) ?>)</small></td>
                <td><?= sanitize($r['room_no']) ?></td>
                <td><?php $b = ['Present'=>'success','Absent'=>'danger','Late'=>'warning','On Leave'=>'info','Medical Leave'=>'info','Weekend Leave'=>'secondary','Outside Hostel'=>'dark']; $bg = $b[$r['status']]??'secondary'; ?><span class="badge bg-<?= $bg ?>"><?= $r['status'] ?></span></td>
                <td><small><?= sanitize($r['taken_by_name'] ?? $r['taken_role'] ?? '-') ?></small></td>
                <td><small><?= sanitize($r['remarks'] ?? '') ?></small></td>
                <td><?= $r['is_locked'] ? '<i class="bi bi-lock-fill text-danger" title="Locked"></i>' : '<span class="text-muted">-</span>' ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="9" class="text-center text-muted">No records found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?= paginationLinks($page, $pages) ?>

<?php endif; ?>
</div>

<style>
@media print { .sidebar, .navbar, .btn, form, .no-print { display:none!important; } .main-content { margin:0!important; width:100%!important; } }
</style>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
