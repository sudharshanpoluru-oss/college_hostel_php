<?php
$title = 'Reports';
require_once __DIR__ . '/../includes/admin-header.php';

$activeTab = $_GET['tab'] ?? 'students';
?>
<div class="container-fluid">
    <h4 class="mb-4">Reports</h4>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link <?= $activeTab=='students'?'active':'' ?>" href="?tab=students">Students</a></li>
        <li class="nav-item"><a class="nav-link <?= $activeTab=='rooms'?'active':'' ?>" href="?tab=rooms">Rooms</a></li>
        <li class="nav-item"><a class="nav-link <?= $activeTab=='fees'?'active':'' ?>" href="?tab=fees">Fees</a></li>
        <li class="nav-item"><a class="nav-link <?= $activeTab=='attendance'?'active':'' ?>" href="?tab=attendance">Attendance</a></li>
        <li class="nav-item"><a class="nav-link <?= $activeTab=='complaints'?'active':'' ?>" href="?tab=complaints">Complaints</a></li>
    </ul>

    <?php if ($activeTab === 'students'): ?>
    <div class="card">
        <div class="card-header"><strong>Student Report</strong></div>
        <div class="card-body">
            <form method="get" class="row g-2 mb-3">
                <input type="hidden" name="tab" value="students">
                <div class="col-auto"><input type="text" name="course" class="form-control form-control-sm" placeholder="Course" value="<?= sanitize($_GET['course']??'') ?>"></div>
                <div class="col-auto"><input type="text" name="year" class="form-control form-control-sm" placeholder="Year" value="<?= sanitize($_GET['year']??'') ?>"></div>
                <div class="col-auto"><select name="status" class="form-select form-select-sm"><option value="">All Status</option><option value="Active" <?= ($_GET['status']??'')=='Active'?'selected':'' ?>>Active</option><option value="Inactive" <?= ($_GET['status']??'')=='Inactive'?'selected':'' ?>>Inactive</option></select></div>
                <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">Filter</button></div>
            </form>
            <table class="table table-bordered table-striped">
                <thead><tr><th>#</th><th>Name</th><th>Roll No</th><th>Course</th><th>Year</th><th>Status</th></tr></thead>
                <tbody>
                    <?php
                    $w = ''; $p = [];
                    if (!empty($_GET['course'])) { $w .= " AND course LIKE ?"; $p[] = "%{$_GET['course']}%"; }
                    if (!empty($_GET['year'])) { $w .= " AND year LIKE ?"; $p[] = "%{$_GET['year']}%"; }
                    if (!empty($_GET['status'])) { $w .= " AND status=?"; $p[] = $_GET['status']; }
                    $sql = "SELECT * FROM students WHERE 1=1 $w ORDER BY name";
                    $stmt = db()->prepare($sql); $stmt->execute($p); $i=0;
                    while ($s = $stmt->fetch()): ?>
                    <tr><td><?= ++$i ?></td><td><?= sanitize($s['name']) ?></td><td><?= sanitize($s['roll_no']) ?></td><td><?= sanitize($s['course']) ?></td><td><?= sanitize($s['year']) ?></td><td><span class="badge bg-<?= $s['status']=='Active'?'success':'danger' ?>"><?= $s['status'] ?></span></td></tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($activeTab === 'rooms'): ?>
    <div class="card">
        <div class="card-header"><strong>Room Report</strong></div>
        <div class="card-body">
            <table class="table table-bordered table-striped">
                <thead><tr><th>#</th><th>Room No</th><th>Floor</th><th>Type</th><th>Capacity</th><th>Occupancy</th><th>Available</th><th>Status</th></tr></thead>
                <tbody>
                    <?php $i=0; $stmt = db()->query("SELECT *, (capacity-occupancy) AS available FROM rooms ORDER BY room_no");
                    while ($r = $stmt->fetch()): ?>
                    <tr><td><?= ++$i ?></td><td><?= sanitize($r['room_no']) ?></td><td><?= sanitize($r['floor']) ?></td><td><?= $r['room_type'] ?></td><td><?= $r['capacity'] ?></td><td><?= $r['occupancy'] ?></td><td><?= $r['available'] ?></td><td><span class="badge bg-<?= $r['status']=='Available'?'success':($r['status']=='Full'?'warning':'secondary') ?>"><?= $r['status'] ?></span></td></tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($activeTab === 'fees'): ?>
    <div class="card">
        <div class="card-header"><strong>Fee Report</strong></div>
        <div class="card-body">
            <form method="get" class="row g-2 mb-3">
                <input type="hidden" name="tab" value="fees">
                <div class="col-auto"><input type="date" name="from" class="form-control form-control-sm" value="<?= sanitize($_GET['from']??'') ?>"></div>
                <div class="col-auto"><input type="date" name="to" class="form-control form-control-sm" value="<?= sanitize($_GET['to']??'') ?>"></div>
                <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">Filter</button></div>
            </form>
            <table class="table table-bordered table-striped">
                <thead><tr><th>#</th><th>Student</th><th>Total Fee</th><th>Paid</th><th>Due</th><th>Date</th><th>Status</th></tr></thead>
                <tbody>
                    <?php
                    $w = ''; $p = [];
                    if (!empty($_GET['from'])) { $w .= " AND f.payment_date >= ?"; $p[] = $_GET['from']; }
                    if (!empty($_GET['to'])) { $w .= " AND f.payment_date <= ?"; $p[] = $_GET['to']; }
                    $sql = "SELECT f.*, s.name AS sname, s.roll_no FROM fees f JOIN students s ON s.id=f.student_id WHERE 1=1 $w ORDER BY f.payment_date DESC";
                    $stmt = db()->prepare($sql); $stmt->execute($p); $i=0;
                    while ($f = $stmt->fetch()): ?>
                    <tr><td><?= ++$i ?></td><td><?= sanitize($f['sname']) ?></td><td>₹<?= number_format($f['total_fee'],2) ?></td><td>₹<?= number_format($f['paid_amount'],2) ?></td><td>₹<?= number_format($f['due_amount'],2) ?></td><td><?= $f['payment_date'] ?></td><td><span class="badge bg-<?= $f['status']=='Paid'?'success':($f['status']=='Partial'?'warning':'secondary') ?>"><?= $f['status'] ?></span></td></tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($activeTab === 'attendance'): ?>
    <div class="card">
        <div class="card-header"><strong>Attendance Report</strong></div>
        <div class="card-body">
            <form method="get" class="row g-2 mb-3">
                <input type="hidden" name="tab" value="attendance">
                <div class="col-auto"><input type="date" name="from" class="form-control form-control-sm" value="<?= sanitize($_GET['from']??'') ?>"></div>
                <div class="col-auto"><input type="date" name="to" class="form-control form-control-sm" value="<?= sanitize($_GET['to']??'') ?>"></div>
                <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">Filter</button></div>
            </form>
            <table class="table table-bordered table-striped">
                <thead><tr><th>#</th><th>Student</th><th>Roll No</th><th>Date</th><th>Status</th></tr></thead>
                <tbody>
                    <?php
                    $w = ''; $p = [];
                    if (!empty($_GET['from'])) { $w .= " AND a.date >= ?"; $p[] = $_GET['from']; }
                    if (!empty($_GET['to'])) { $w .= " AND a.date <= ?"; $p[] = $_GET['to']; }
                    $sql = "SELECT a.*, s.name AS sname, s.roll_no FROM attendance a JOIN students s ON s.id=a.student_id WHERE 1=1 $w ORDER BY a.date DESC, s.name";
                    $stmt = db()->prepare($sql); $stmt->execute($p); $i=0;
                    while ($a = $stmt->fetch()): ?>
                    <tr><td><?= ++$i ?></td><td><?= sanitize($a['sname']) ?></td><td><?= sanitize($a['roll_no']) ?></td><td><?= $a['date'] ?></td><td><span class="badge bg-<?= $a['status']=='Present'?'success':'danger' ?>"><?= $a['status'] ?></span></td></tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($activeTab === 'complaints'): ?>
    <div class="card">
        <div class="card-header"><strong>Complaint Report</strong></div>
        <div class="card-body">
            <form method="get" class="row g-2 mb-3">
                <input type="hidden" name="tab" value="complaints">
                <div class="col-auto"><select name="status" class="form-select form-select-sm"><option value="">All</option><option value="Pending" <?= ($_GET['status']??'')=='Pending'?'selected':'' ?>>Pending</option><option value="Working" <?= ($_GET['status']??'')=='Working'?'selected':'' ?>>Working</option><option value="Resolved" <?= ($_GET['status']??'')=='Resolved'?'selected':'' ?>>Resolved</option></select></div>
                <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">Filter</button></div>
            </form>
            <table class="table table-bordered table-striped">
                <thead><tr><th>#</th><th>Student</th><th>Title</th><th>Category</th><th>Status</th></tr></thead>
                <tbody>
                    <?php
                    $w = ''; $p = [];
                    if (!empty($_GET['status'])) { $w .= " AND c.status=?"; $p[] = $_GET['status']; }
                    $sql = "SELECT c.*, s.name AS sname, s.roll_no FROM complaints c JOIN students s ON s.id=c.student_id WHERE 1=1 $w ORDER BY c.id DESC";
                    $stmt = db()->prepare($sql); $stmt->execute($p); $i=0;
                    while ($c = $stmt->fetch()): ?>
                    <tr><td><?= ++$i ?></td><td><?= sanitize($c['sname']) ?></td><td><?= sanitize($c['title']) ?></td><td><?= sanitize($c['category']) ?></td><td><span class="badge bg-<?= $c['status']=='Pending'?'warning':($c['status']=='Working'?'info':'success') ?>"><?= $c['status'] ?></span></td></tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
