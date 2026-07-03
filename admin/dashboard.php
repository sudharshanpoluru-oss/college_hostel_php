<?php
$title = 'Dashboard';
require_once __DIR__ . '/../includes/admin-header.php';

// Stats
$totalStudents = db()->query("SELECT COUNT(*) FROM students WHERE status='Active'")->fetchColumn();
$totalRooms    = db()->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$occupiedBeds  = db()->query("SELECT COALESCE(SUM(occupancy),0) FROM rooms")->fetchColumn();
$vacantBeds    = db()->query("SELECT COALESCE(SUM(capacity-occupancy),0) FROM rooms")->fetchColumn();
$totalRevenue  = db()->query("SELECT COALESCE(SUM(paid_amount),0) FROM fees WHERE status='Paid'")->fetchColumn();
$pendingFees   = db()->query("SELECT COALESCE(SUM(due_amount),0) FROM fees WHERE status!='Paid'")->fetchColumn();
$pendingComplaints = db()->query("SELECT COUNT(*) FROM complaints WHERE status='Pending'")->fetchColumn();
$pendingLeaves = db()->query("SELECT COUNT(*) FROM leaves WHERE status='Pending'")->fetchColumn();
$todayPresent  = db()->query("SELECT COUNT(*) FROM attendance WHERE date=CURDATE() AND status='Present'")->fetchColumn();
$todayAbsent   = db()->query("SELECT COUNT(*) FROM attendance WHERE date=CURDATE() AND status='Absent'")->fetchColumn();
$escalatedComplaints = db()->query("SELECT COUNT(*) FROM complaints WHERE status IN ('Escalated to Admin','Under Admin Review')")->fetchColumn();
$highPriorityEscalated = db()->query("SELECT COUNT(*) FROM complaints WHERE status IN ('Escalated to Admin','Under Admin Review') AND priority IN ('High','Emergency')")->fetchColumn();
try { $maintenanceRequests = db()->query("SELECT COUNT(*) FROM maintenance_requests WHERE status NOT IN ('Resolved','Closed')")->fetchColumn(); } catch (Exception $e) { $maintenanceRequests = 0; }

// Escalated complaints for table
$escalatedList = db()->query("
    SELECT c.*, s.name as sname, s.roll_no, u.username as escalated_by_name
    FROM complaints c 
    JOIN students s ON s.id=c.student_id 
    LEFT JOIN users u ON u.id=c.escalated_by 
    WHERE c.status IN ('Escalated to Admin','Under Admin Review')
    ORDER BY c.priority='Emergency' DESC, c.priority='High' DESC, c.escalated_at DESC 
    LIMIT 10
")->fetchAll();

// Monthly revenue for chart (last 6 months)
$revenueData = [];
$monthLabels = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-{$i} month"));
    $monthLabels[] = date('M Y', strtotime("-{$i} month"));
    $rev = db()->prepare("SELECT COALESCE(SUM(paid_amount),0) FROM fees WHERE DATE_FORMAT(payment_date,'%Y-%m')=? AND status='Paid'");
    $rev->execute([$month]);
    $revenueData[] = (float)$rev->fetchColumn();
}

// Complaint stats for pie chart
$complaintStatuses = db()->query("SELECT status, COUNT(*) as c FROM complaints GROUP BY status")->fetchAll();
$compLabels = []; $compData = []; $compColors = [];
foreach ($complaintStatuses as $cs) {
    $compLabels[] = $cs['status'];
    $compData[] = (int)$cs['c'];
    $compColors[] = match($cs['status']) { 'Pending' => '#f59e0b', 'Working' => '#3b82f6', 'Resolved' => '#10b981' };
}

// Student registration trend (last 6 months)
$regData = [];
$regLabels = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-{$i} month"));
    $regLabels[] = date('M', strtotime("-{$i} month"));
    $reg = db()->prepare("SELECT COUNT(*) FROM students WHERE DATE_FORMAT(admission_date,'%Y-%m')=?");
    $reg->execute([$month]);
    $regData[] = (int)$reg->fetchColumn();
}

// Recent payments
$recentPayments = db()->query("
    SELECT f.*, s.name, s.roll_no 
    FROM fees f JOIN students s ON s.id=f.student_id 
    WHERE f.status='Paid' 
    ORDER BY f.payment_date DESC LIMIT 5
")->fetchAll();

// Recent complaints
$recentComplaints = db()->query("
    SELECT c.*, s.name, s.roll_no 
    FROM complaints c JOIN students s ON s.id=c.student_id 
    ORDER BY c.created_at DESC LIMIT 5
")->fetchAll();

// Recent admissions
$recentAdmissions = db()->query("
    SELECT * FROM students WHERE status='Active' 
    ORDER BY admission_date DESC LIMIT 5
")->fetchAll();
?>
<div class="container-fluid px-0">
    <!-- Stats Row -->
    <div class="row g-3 mb-4 stagger-children">
        <div class="col-md-2 col-6">
            <div class="card stat-card border-primary h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number text-primary"><?= $totalStudents ?></div>
                            <div class="stat-label">Total Students</div>
                        </div>
                        <div class="stat-icon text-primary"><i class="bi bi-people"></i></div>
                    </div>
                    <div class="mt-2 small text-muted">
                        <i class="bi bi-arrow-up text-success"></i> Active students
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card stat-card border-success h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number text-success"><?= $totalRooms ?></div>
                            <div class="stat-label">Total Rooms</div>
                        </div>
                        <div class="stat-icon text-success"><i class="bi bi-door-open"></i></div>
                    </div>
                    <div class="mt-2 small text-muted">
                        <i class="bi bi-building"></i> <?= $occupiedBeds ?> occupied / <?= $vacantBeds ?> vacant
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card stat-card border-warning h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number text-warning"><?= $pendingComplaints ?></div>
                            <div class="stat-label">Pending Complaints</div>
                        </div>
                        <div class="stat-icon text-warning"><i class="bi bi-exclamation-triangle"></i></div>
                    </div>
                    <div class="mt-2 small text-muted">
                        <i class="bi bi-hourglass"></i> <?= $pendingLeaves ?> pending leaves
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card stat-card border-info h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number text-info">₹<?= number_format($totalRevenue) ?></div>
                            <div class="stat-label">Total Revenue</div>
                        </div>
                        <div class="stat-icon text-info"><i class="bi bi-cash-coin"></i></div>
                    </div>
                    <div class="mt-2 small text-muted">
                        <i class="bi bi-clock"></i> ₹<?= number_format($pendingFees) ?> pending
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card stat-card border-danger h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number text-danger"><?= $escalatedComplaints ?></div>
                            <div class="stat-label">Escalated</div>
                        </div>
                        <div class="stat-icon text-danger"><i class="bi bi-arrow-up-circle"></i></div>
                    </div>
                    <div class="mt-2 small text-muted">
                        <i class="bi bi-exclamation"></i> <?= $highPriorityEscalated ?> high priority
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php
// Hostel Summaries (only if hostel_type column exists)
$hasHostelType = false;
try {
    $checkCol = db()->query("SHOW COLUMNS FROM students LIKE 'hostel_type'");
    $hasHostelType = (bool)$checkCol->fetchColumn();
} catch (Exception $e) {}
if ($hasHostelType):
?>
<div class="row g-3 mb-4">
    <?php
    foreach (['boys','girls'] as $ht):
        $htStudents = db()->prepare("SELECT COUNT(*) FROM students WHERE status='Active' AND hostel_type=?");
        $htStudents->execute([$ht]);
        $htStudentCount = $htStudents->fetchColumn();
        
        $htOccupied = db()->prepare("SELECT COUNT(DISTINCT ra.room_id) FROM room_allocations ra JOIN students s ON s.id=ra.student_id WHERE ra.status='Active' AND s.hostel_type=?");
        $htOccupied->execute([$ht]);
        $htOccupiedCount = $htOccupied->fetchColumn();
        
        $htComplaints = db()->prepare("SELECT COUNT(*) FROM complaints c JOIN students s ON s.id=c.student_id WHERE c.status NOT IN ('Resolved','Closed','Rejected') AND s.hostel_type=?");
        $htComplaints->execute([$ht]);
        $htComplaintCount = $htComplaints->fetchColumn();
        
        $htAttToday = db()->prepare("SELECT COUNT(*) FROM attendance a JOIN students s ON s.id=a.student_id WHERE a.date=CURDATE() AND s.hostel_type=?");
        $htAttToday->execute([$ht]);
        $htAttCount = $htAttToday->fetchColumn();
        
        $htPresentToday = db()->prepare("SELECT COUNT(*) FROM attendance a JOIN students s ON s.id=a.student_id WHERE a.date=CURDATE() AND a.status='Present' AND s.hostel_type=?");
        $htPresentToday->execute([$ht]);
        $htPresentCount = $htPresentToday->fetchColumn();
        $htAttPct = $htAttCount > 0 ? round(($htPresentCount/$htAttCount)*100) : 0;
    ?>
    <div class="col-md-6">
        <div class="card border-<?= $ht=='boys'?'primary':'danger' ?> h-100">
            <div class="card-header bg-<?= $ht=='boys'?'primary':'danger' ?> text-white d-flex justify-content-between">
                <span><i class="bi bi-building me-1"></i> <?= ucfirst($ht) ?> Hostel</span>
                <span class="badge bg-light text-dark"><?= $htStudentCount ?> Students</span>
            </div>
            <div class="card-body">
                <div class="row text-center g-2">
                    <div class="col-4"><div class="fw-bold fs-5"><?= $htOccupiedCount ?></div><small>Occupied Rooms</small></div>
                    <div class="col-4"><div class="fw-bold fs-5"><?= $htAttPct ?>%</div><small>Attendance</small></div>
                    <div class="col-4"><div class="fw-bold fs-5"><?= $htComplaintCount ?></div><small>Open Complaints</small></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Pending Items Overview -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="bi bi-list-check"></i> Pending Items Overview</div>
            <div class="card-body">
                <div class="row text-center g-2">
                    <div class="col-2">
                        <div class="fw-bold fs-4 text-warning"><?= $pendingComplaints ?></div>
                        <small>Complaints</small>
                    </div>
                    <div class="col-2">
                        <div class="fw-bold fs-4 text-warning"><?= $pendingLeaves ?></div>
                        <small>Leaves</small>
                    </div>
                    <div class="col-2">
                        <div class="fw-bold fs-4 text-warning"><?= $maintenanceRequests ?? 0 ?></div>
                        <small>Maintenance</small>
                    </div>
                    <div class="col-2">
                        <div class="fw-bold fs-4 text-warning"><?= $escalatedComplaints ?></div>
                        <small>Escalated</small>
                    </div>
                    <div class="col-2">
                        <div class="fw-bold fs-4 text-info"><?= $pendingFees > 0 ? '₹'.number_format($pendingFees) : '0' ?></div>
                        <small>Pending Fees</small>
                    </div>
                    <div class="col-2">
                        <div class="fw-bold fs-4 text-primary"><?= $vacantBeds ?></div>
                        <small>Vacant Beds</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Today's Attendance + Occupancy -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card text-bg-primary h-100">
                <div class="card-body text-center">
                    <div class="fs-1 fw-bold"><?= $todayPresent ?></div>
                    <div class="small opacity-75">Present Today</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-bg-danger h-100">
                <div class="card-body text-center">
                    <div class="fs-1 fw-bold"><?= $todayAbsent ?></div>
                    <div class="small opacity-75">Absent Today</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-bg-success h-100">
                <div class="card-body text-center">
                    <?php $occPct = $totalRooms > 0 ? round(($occupiedBeds / ($occupiedBeds + $vacantBeds)) * 100) : 0; ?>
                    <div class="fs-1 fw-bold"><?= $occPct ?>%</div>
                    <div class="small opacity-75">Occupancy Rate</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-bg-dark h-100">
                <div class="card-body text-center">
                    <div class="fs-1 fw-bold"><?= $vacantBeds ?></div>
                    <div class="small opacity-75">Vacant Beds</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-graph-up me-1"></i> Monthly Revenue</span>
                    <span class="badge bg-primary"><?= date('Y') ?></span>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><i class="bi bi-pie-chart me-1"></i> Complaints</div>
                <div class="card-body">
                    <div class="chart-container-sm" style="height:250px">
                        <canvas id="complaintChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-graph-up-arrow me-1"></i> Registration Trend</div>
                <div class="card-body">
                    <div class="chart-container-sm" style="height:250px">
                        <canvas id="regChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-cash me-1"></i> Recent Payments</span>
                    <a href="<?= BASE_URL ?>/admin/fees.php" class="small">View all</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr><th>Student</th><th>Roll No</th><th>Amount</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentPayments as $p): ?>
                                <tr>
                                    <td class="fw-medium"><?= sanitize($p['name']) ?></td>
                                    <td><?= sanitize($p['roll_no']) ?></td>
                                    <td>₹<?= number_format($p['paid_amount']) ?></td>
                                    <td><?= date('d M', strtotime($p['payment_date'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recentPayments)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No recent payments</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-exclamation-circle me-1"></i> Recent Complaints</span>
                    <a href="<?= BASE_URL ?>/admin/complaints.php" class="small">View all</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr><th>Student</th><th>Issue</th><th>Status</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentComplaints as $c): ?>
                                <tr>
                                    <td class="fw-medium"><?= sanitize($c['name']) ?></td>
                                    <td><?= sanitize(mb_substr($c['title'], 0, 30)) ?></td>
                                    <td><span class="badge bg-<?= match($c['status']){'Pending'=>'warning','Working'=>'info','Resolved'=>'success'} ?>"><?= $c['status'] ?></span></td>
                                    <td><?= date('d M', strtotime($c['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recentComplaints)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No recent complaints</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-person-plus me-1"></i> Recent Admissions</span>
                    <a href="<?= BASE_URL ?>/admin/students.php" class="small">View all</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr><th>Name</th><th>Roll No</th><th>Course</th><th>Joined</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentAdmissions as $s): ?>
                                <tr>
                                    <td class="fw-medium"><?= sanitize($s['name']) ?></td>
                                    <td><?= sanitize($s['roll_no']) ?></td>
                                    <td><?= sanitize($s['course']) ?></td>
                                    <td><?= date('d M', strtotime($s['admission_date'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recentAdmissions)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No recent admissions</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row g-3 mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header"><i class="bi bi-lightning me-1"></i> Quick Actions</div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?= BASE_URL ?>/admin/students.php?action=add" class="btn btn-primary"><i class="bi bi-person-plus"></i> Add Student</a>
                        <a href="<?= BASE_URL ?>/admin/attendance.php" class="btn btn-success"><i class="bi bi-calendar-check"></i> Take Attendance</a>
                        <a href="<?= BASE_URL ?>/admin/visitors.php?action=add" class="btn btn-info text-white"><i class="bi bi-person-badge"></i> Add Visitor</a>
                        <a href="<?= BASE_URL ?>/admin/notices.php?action=add" class="btn btn-warning"><i class="bi bi-megaphone"></i> Create Notice</a>
                        <a href="<?= BASE_URL ?>/admin/maintenance.php?action=add" class="btn btn-secondary"><i class="bi bi-tools"></i> Maintenance Request</a>
                        <a href="<?= BASE_URL ?>/admin/reports.php" class="btn btn-dark"><i class="bi bi-file-text"></i> Generate Report</a>
                        <a href="<?= BASE_URL ?>/admin/analytics.php" class="btn btn-outline-primary"><i class="bi bi-graph-up"></i> Analytics</a>
                        <a href="<?= BASE_URL ?>/admin/occupancy.php" class="btn btn-outline-success"><i class="bi bi-building"></i> Room Occupancy</a>
                        <a href="<?= BASE_URL ?>/admin/search.php" class="btn btn-outline-info"><i class="bi bi-search"></i> Global Search</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($escalatedList)): ?>
    <div class="row g-3 mt-1">
        <div class="col-12">
            <div class="card border-danger">
                <div class="card-header d-flex justify-content-between align-items-center bg-danger text-white">
                    <span><i class="bi bi-arrow-up-circle me-1"></i> Escalated Complaints (<?= count($escalatedList) ?>)</span>
                    <a href="<?= BASE_URL ?>/admin/complaints.php?filter=escalated" class="text-white small">View all</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr><th>Student</th><th>Issue</th><th>Priority</th><th>Escalated By</th><th>Date</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($escalatedList as $e): ?>
                                <tr>
                                    <td class="fw-medium"><?= sanitize($e['sname']) ?> <small class="text-muted">(<?= sanitize($e['roll_no']) ?>)</small></td>
                                    <td><?= sanitize(mb_substr($e['title'], 0, 40)) ?></td>
                                    <td><span class="badge bg-<?= match($e['priority']){'Low'=>'secondary','Medium'=>'info','High'=>'warning','Emergency'=>'danger'} ?>"><?= $e['priority'] ?></span></td>
                                    <td><?= sanitize($e['escalated_by_name'] ?? 'Warden') ?></td>
                                    <td><?= date('d M H:i', strtotime($e['escalated_at'] ?? $e['created_at'])) ?></td>
                                    <td><span class="badge bg-danger"><?= $e['status'] ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Revenue Chart
    new Chart(document.getElementById('revenueChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($monthLabels) ?>,
            datasets: [{
                label: 'Revenue (₹)',
                data: <?= json_encode($revenueData) ?>,
                borderColor: '#4f46e5',
                backgroundColor: 'rgba(79,70,229,0.1)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#4f46e5'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { callback: function(v) { return '₹' + v.toLocaleString(); } } } }
        }
    });

    // Complaint Pie Chart
    new Chart(document.getElementById('complaintChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($compLabels) ?>,
            datasets: [{
                data: <?= json_encode($compData) ?>,
                backgroundColor: <?= json_encode($compColors) ?>,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { padding: 12, font: { size: 12 } } } }
        }
    });

    // Registration Trend
    new Chart(document.getElementById('regChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($regLabels) ?>,
            datasets: [{
                label: 'New Students',
                data: <?= json_encode($regData) ?>,
                backgroundColor: 'rgba(79,70,229,0.7)',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
});
</script>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
