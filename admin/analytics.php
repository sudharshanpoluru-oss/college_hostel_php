<?php
$title = 'Analytics Dashboard';
require_once __DIR__ . '/../includes/admin-header.php';

// Filters
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to   = $_GET['date_to'] ?? date('Y-m-d');
$hostel_type = $_GET['hostel_type'] ?? '';
$year_month  = $_GET['year_month'] ?? '';

$hasHostelType = false;
try { $hasHostelType = (bool)db()->query("SHOW COLUMNS FROM students LIKE 'hostel_type'")->fetchColumn(); } catch (Exception $e) {}
$hostelFilter = ($hostel_type && $hasHostelType) ? " AND s.hostel_type = " . db()->quote($hostel_type) : '';
$dateFilter = " AND a.date BETWEEN " . db()->quote($date_from) . " AND " . db()->quote($date_to);

// ===== SUMMARY STATS =====

// Total Students
$totalStudents = db()->query("SELECT COUNT(*) FROM students WHERE status='Active'")->fetchColumn();
if ($hostel_type) {
    $st = db()->prepare("SELECT COUNT(*) FROM students WHERE status='Active' AND hostel_type=?");
    $st->execute([$hostel_type]);
    $totalStudents = $st->fetchColumn();
}

// Occupancy
$totalBeds    = db()->query("SELECT COALESCE(SUM(capacity),0) FROM rooms")->fetchColumn();
$occupiedBeds = db()->query("SELECT COALESCE(SUM(occupancy),0) FROM rooms")->fetchColumn();
$vacantBeds   = $totalBeds - $occupiedBeds;
$occPct       = $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 1) : 0;

// Avg Attendance %
$attSql = "SELECT ROUND(AVG(pct),1) FROM (
    SELECT (SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) / COUNT(*)) * 100 as pct
    FROM attendance a JOIN students s ON s.id=a.student_id
    WHERE a.date BETWEEN " . db()->quote($date_from) . " AND " . db()->quote($date_to) . " $hostelFilter
    GROUP BY a.date
) t";
$avgAttendance = db()->query($attSql)->fetchColumn() ?: 0;

// Total Revenue (by period)
$revSql = "SELECT COALESCE(SUM(f.paid_amount),0) FROM fees f
           JOIN students s ON s.id=f.student_id
           WHERE f.status='Paid' AND f.payment_date BETWEEN " . db()->quote($date_from) . " AND " . db()->quote($date_to) . " $hostelFilter";
$totalRevenue = db()->query($revSql)->fetchColumn();

// ===== CHART DATA =====

// 1. Hostel Occupancy
$occData = [$occupiedBeds, $vacantBeds];
$occLabels = ['Occupied', 'Vacant'];
$occColors = ['#4f46e5', '#e5e7eb'];

// 2. Attendance Trends (last 30 days)
$attLabels   = [];
$attPresent  = [];
$attAbsent   = [];
$attLate     = [];
for ($i = 29; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i day"));
    $attLabels[] = date('d M', strtotime("-$i day"));
    $p = db()->query("SELECT COUNT(*) FROM attendance a JOIN students s ON s.id=a.student_id WHERE a.date='$day' AND a.status='Present' $hostelFilter")->fetchColumn();
    $a = db()->query("SELECT COUNT(*) FROM attendance a JOIN students s ON s.id=a.student_id WHERE a.date='$day' AND a.status='Absent' $hostelFilter")->fetchColumn();
    $l = db()->query("SELECT COUNT(*) FROM attendance a JOIN students s ON s.id=a.student_id WHERE a.date='$day' AND a.status='Late' $hostelFilter")->fetchColumn();
    $attPresent[]  = (int)$p;
    $attAbsent[]   = (int)$a;
    $attLate[]     = (int)$l;
}

// 3. Complaint Trends by category
$compSql = "SELECT COALESCE(category,'Other') as cat, COUNT(*) as c
            FROM complaints c JOIN students s ON s.id=c.student_id
            WHERE c.created_at BETWEEN " . db()->quote($date_from) . " AND " . db()->quote($date_to) . " $hostelFilter
            GROUP BY cat ORDER BY c DESC";
$compRows = db()->query($compSql)->fetchAll();
$compLabels = []; $compData = [];
$compColorPalette = ['#4f46e5','#f59e0b','#ef4444','#10b981','#3b82f6','#8b5cf6','#ec4899','#14b8a6','#f97316'];
foreach ($compRows as $i => $r) {
    $compLabels[] = $r['cat'];
    $compData[]   = (int)$r['c'];
}

// 4. Leave Statistics
$leaveSql = "SELECT l.status, COUNT(*) as c
             FROM leaves l JOIN students s ON s.id=l.student_id
             WHERE 1=1 $hostelFilter
             GROUP BY l.status";
$leaveRows = db()->query($leaveSql)->fetchAll();
$leaveLabels  = []; $leaveData = []; $leaveColors = [];
$leaveColorMap = ['Pending' => '#f59e0b', 'Approved' => '#10b981', 'Rejected' => '#ef4444'];
foreach ($leaveRows as $r) {
    $leaveLabels[] = $r['status'];
    $leaveData[]   = (int)$r['c'];
    $leaveColors[] = $leaveColorMap[$r['status']] ?? '#6b7280';
}

// 5. Visitor Statistics (last 14 days)
$visLabels = [];
$visData   = [];
for ($i = 13; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i day"));
    $visLabels[] = date('d M', strtotime("-$i day"));
    $v = db()->query("SELECT COUNT(*) FROM visitor_logs vl
                      JOIN students s ON s.id=vl.student_id
                      WHERE DATE(vl.check_in)='$day' $hostelFilter")->fetchColumn();
    $visData[] = (int)$v;
}

// 6. Maintenance Requests by category
$maintLabels = []; $maintData = [];
$maintColors = ['#4f46e5','#f59e0b','#ef4444','#10b981','#3b82f6','#8b5cf6','#ec4899','#14b8a6','#f97316'];
try {
    $maintSql = "SELECT COALESCE(category,'Other') as cat, COUNT(*) as c
                 FROM maintenance_requests mr
                 LEFT JOIN students s ON s.id=mr.student_id
                 WHERE 1=1 $hostelFilter
                 GROUP BY cat ORDER BY c DESC";
    $maintRows = db()->query($maintSql)->fetchAll();
    foreach ($maintRows as $i => $r) {
        $maintLabels[] = $r['cat'];
        $maintData[]   = (int)$r['c'];
    }
} catch (Exception $e) {
    // Table may not exist yet
}

// 7. Fee Collection (last 12 months)
$feeLabels = [];
$feeData   = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i month"));
    $feeLabels[] = date('M Y', strtotime("-$i month"));
    $fSql = "SELECT COALESCE(SUM(f.paid_amount),0) FROM fees f
             JOIN students s ON s.id=f.student_id
             WHERE DATE_FORMAT(f.payment_date,'%Y-%m')=" . db()->quote($month) . " AND f.status='Paid' $hostelFilter";
    $feeData[] = (float)db()->query($fSql)->fetchColumn();
}

// 8. Room Type Distribution
$typeRows = db()->query("SELECT room_type, COUNT(*) as c FROM rooms GROUP BY room_type ORDER BY c DESC")->fetchAll();
$typeLabels = []; $typeData = [];
$typeColors = ['#4f46e5','#10b981','#f59e0b','#ef4444'];
foreach ($typeRows as $i => $r) {
    $typeLabels[] = $r['room_type'];
    $typeData[]   = (int)$r['c'];
}
?>
<div class="container-fluid px-0">
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label small mb-1">Date From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= $date_from ?>">
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Date To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= $date_to ?>">
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Hostel Type</label>
                    <select name="hostel_type" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="boys" <?= $hostel_type==='boys'?'selected':'' ?>>Boys</option>
                        <option value="girls" <?= $hostel_type==='girls'?'selected':'' ?>>Girls</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Year/Month</label>
                    <input type="month" name="year_month" class="form-control form-control-sm" value="<?= $year_month ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filter</button>
                    <a href="analytics.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i> Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row g-3 mb-4 stagger-children">
        <div class="col-md-3 col-6">
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
                        <?= $hostel_type ? ucfirst($hostel_type) : 'All' ?> hostel
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card border-success h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number text-success"><?= $occPct ?>%</div>
                            <div class="stat-label">Occupancy Rate</div>
                        </div>
                        <div class="stat-icon text-success"><i class="bi bi-building"></i></div>
                    </div>
                    <div class="mt-2 small text-muted">
                        <?= $occupiedBeds ?> / <?= $totalBeds ?> beds filled
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card border-info h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number text-info"><?= $avgAttendance ?>%</div>
                            <div class="stat-label">Avg Attendance</div>
                        </div>
                        <div class="stat-icon text-info"><i class="bi bi-calendar-check"></i></div>
                    </div>
                    <div class="mt-2 small text-muted">
                        <?= date('d M', strtotime($date_from)) ?> - <?= date('d M', strtotime($date_to)) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card border-warning h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number text-warning">₹<?= number_format($totalRevenue) ?></div>
                            <div class="stat-label">Total Revenue</div>
                        </div>
                        <div class="stat-icon text-warning"><i class="bi bi-cash-coin"></i></div>
                    </div>
                    <div class="mt-2 small text-muted">
                        Collected (filtered period)
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-pie-chart me-1"></i> Hostel Occupancy</div>
                <div class="card-body">
                    <div class="chart-container-sm" style="height:280px">
                        <canvas id="occupancyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-graph-up me-1"></i> Attendance Trends (Last 30 Days)</div>
                <div class="card-body">
                    <div class="chart-container-sm" style="height:280px">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-bar-chart me-1"></i> Complaint Trends by Category</div>
                <div class="card-body">
                    <div class="chart-container-sm" style="height:280px">
                        <canvas id="complaintChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-box-arrow-right me-1"></i> Leave Statistics</div>
                <div class="card-body">
                    <div class="chart-container-sm" style="height:280px">
                        <canvas id="leaveChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 3 -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-person-badge me-1"></i> Visitor Statistics (Last 14 Days)</div>
                <div class="card-body">
                    <div class="chart-container-sm" style="height:280px">
                        <canvas id="visitorChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-tools me-1"></i> Maintenance Requests by Category</div>
                <div class="card-body">
                    <div class="chart-container-sm" style="height:280px">
                        <canvas id="maintenanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 4 -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-cash-coin me-1"></i> Fee Collection (Monthly)</div>
                <div class="card-body">
                    <div class="chart-container-sm" style="height:280px">
                        <canvas id="feeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-door-open me-1"></i> Room Type Distribution</div>
                <div class="card-body">
                    <div class="chart-container-sm" style="height:280px">
                        <canvas id="roomTypeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Hostel Occupancy - Doughnut
    new Chart(document.getElementById('occupancyChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($occLabels) ?>,
            datasets: [{
                data: <?= json_encode($occData) ?>,
                backgroundColor: <?= json_encode($occColors) ?>,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { padding: 16, font: { size: 13 } } }
            }
        }
    });

    // 2. Attendance Trends - Line
    new Chart(document.getElementById('attendanceChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($attLabels) ?>,
            datasets: [
                {
                    label: 'Present',
                    data: <?= json_encode($attPresent) ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16,185,129,0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 2,
                    pointBackgroundColor: '#10b981'
                },
                {
                    label: 'Absent',
                    data: <?= json_encode($attAbsent) ?>,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239,68,68,0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 2,
                    pointBackgroundColor: '#ef4444'
                },
                {
                    label: 'Late',
                    data: <?= json_encode($attLate) ?>,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245,158,11,0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 2,
                    pointBackgroundColor: '#f59e0b'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { position: 'top', labels: { font: { size: 11 }, padding: 12 } }
            },
            scales: {
                x: { ticks: { maxTicksLimit: 15, font: { size: 10 } } },
                y: { beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });

    // 3. Complaint Trends - Bar
    new Chart(document.getElementById('complaintChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($compLabels) ?>,
            datasets: [{
                label: 'Complaints',
                data: <?= json_encode($compData) ?>,
                backgroundColor: <?= json_encode(array_slice($compColorPalette, 0, count($compLabels))) ?>,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, ticks: { stepSize: 1 } },
                y: { ticks: { font: { size: 11 } } }
            }
        }
    });

    // 4. Leave Statistics - Pie
    new Chart(document.getElementById('leaveChart'), {
        type: 'pie',
        data: {
            labels: <?= json_encode($leaveLabels) ?>,
            datasets: [{
                data: <?= json_encode($leaveData) ?>,
                backgroundColor: <?= json_encode($leaveColors) ?>,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { padding: 16, font: { size: 13 } } }
            }
        }
    });

    // 5. Visitor Statistics - Bar
    new Chart(document.getElementById('visitorChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($visLabels) ?>,
            datasets: [{
                label: 'Visitors',
                data: <?= json_encode($visData) ?>,
                backgroundColor: 'rgba(79,70,229,0.7)',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { maxTicksLimit: 14, font: { size: 10 } } },
                y: { beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });

    // 6. Maintenance Requests - Bar
    new Chart(document.getElementById('maintenanceChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($maintLabels) ?>,
            datasets: [{
                label: 'Requests',
                data: <?= json_encode($maintData) ?>,
                backgroundColor: <?= json_encode(array_slice($maintColors, 0, count($maintLabels))) ?>,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, ticks: { stepSize: 1 } },
                y: { ticks: { font: { size: 11 } } }
            }
        }
    });

    // 7. Fee Collection - Line
    new Chart(document.getElementById('feeChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($feeLabels) ?>,
            datasets: [{
                label: 'Revenue (₹)',
                data: <?= json_encode($feeData) ?>,
                borderColor: '#4f46e5',
                backgroundColor: 'rgba(79,70,229,0.1)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#4f46e5',
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: function(v) { return '₹' + v.toLocaleString(); } }
                }
            }
        }
    });

    // 8. Room Type Distribution - Pie
    new Chart(document.getElementById('roomTypeChart'), {
        type: 'pie',
        data: {
            labels: <?= json_encode($typeLabels) ?>,
            datasets: [{
                data: <?= json_encode($typeData) ?>,
                backgroundColor: <?= json_encode(array_slice($typeColors, 0, count($typeLabels))) ?>,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { padding: 16, font: { size: 13 } } }
            }
        }
    });
});
</script>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
