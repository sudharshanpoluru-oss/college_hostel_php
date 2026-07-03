<?php
$title = 'Dashboard';
require_once __DIR__ . '/../includes/warden-header.php';

$hostelType = getWardenHostelType();
$hostelFilter = $hostelType ? " AND s.hostel_type = '$hostelType'" : '';
$roomFilter = $hostelType ? " AND r.hostel_type = '$hostelType'" : '';

// Today's attendance stats
$todayPresentStmt = db()->prepare("SELECT COUNT(*) FROM attendance JOIN students s ON s.id = attendance.student_id WHERE attendance.date = CURDATE() AND attendance.status = 'Present' $hostelFilter");
$todayPresentStmt->execute();
$todayPresent = (int)$todayPresentStmt->fetchColumn();

$todayAbsentStmt = db()->prepare("SELECT COUNT(*) FROM attendance JOIN students s ON s.id = attendance.student_id WHERE attendance.date = CURDATE() AND attendance.status = 'Absent' $hostelFilter");
$todayAbsentStmt->execute();
$todayAbsent = (int)$todayAbsentStmt->fetchColumn();

$todayLateStmt = db()->prepare("SELECT COUNT(*) FROM attendance JOIN students s ON s.id = attendance.student_id WHERE attendance.date = CURDATE() AND attendance.status = 'Late' $hostelFilter");
$todayLateStmt->execute();
$todayLate = (int)$todayLateStmt->fetchColumn();

$todayLeaveStmt = db()->prepare("SELECT COUNT(*) FROM attendance JOIN students s ON s.id = attendance.student_id WHERE attendance.date = CURDATE() AND attendance.status = 'Leave' $hostelFilter");
$todayLeaveStmt->execute();
$todayLeave = (int)$todayLeaveStmt->fetchColumn();

$totalToday = $todayPresent + $todayAbsent + $todayLate + $todayLeave;
$attPct = $totalToday > 0 ? round(($todayPresent / $totalToday) * 100, 1) : 0;

// Pending leaves
$pendingLeavesStmt = db()->prepare("SELECT COUNT(*) FROM leaves JOIN students s ON s.id = leaves.student_id WHERE leaves.status = 'Pending' $hostelFilter");
$pendingLeavesStmt->execute();
$pendingLeaves = (int)$pendingLeavesStmt->fetchColumn();

// Pending complaints
$pendingComplaintsStmt = db()->prepare("SELECT COUNT(*) FROM complaints JOIN students s ON s.id = complaints.student_id WHERE complaints.status IN ('New','Under Inspection','In Progress') $hostelFilter");
$pendingComplaintsStmt->execute();
$pendingComplaints = (int)$pendingComplaintsStmt->fetchColumn();

// Today's visitors
$todayVisitorsStmt = db()->prepare("SELECT COUNT(*) FROM visitor_logs JOIN students s ON s.id = visitor_logs.student_id WHERE DATE(visitor_logs.check_in) = CURDATE() $hostelFilter");
$todayVisitorsStmt->execute();
$todayVisitors = (int)$todayVisitorsStmt->fetchColumn();

// Vacant beds
$vacantBedsStmt = db()->prepare("SELECT COALESCE(SUM(capacity - occupancy), 0) FROM rooms");
$vacantBedsStmt->execute();
$vacantBeds = (int)$vacantBedsStmt->fetchColumn();

// Escalated complaints
$escapedComplaintsStmt = db()->prepare("SELECT COUNT(*) FROM complaints JOIN students s ON s.id = complaints.student_id WHERE complaints.escalated_to IS NOT NULL $hostelFilter");
$escapedComplaintsStmt->execute();
$escalatedComplaints = (int)$escapedComplaintsStmt->fetchColumn();

// Maintenance requests
try {
    $maintenanceStmt = db()->prepare("SELECT COUNT(*) FROM maintenance_requests WHERE status = 'Pending'");
    $maintenanceStmt->execute();
    $maintenanceRequests = (int)$maintenanceStmt->fetchColumn();
} catch (Exception $e) {
    $maintenanceRequests = 0;
}

// Recent activities
$activitiesStmt = db()->prepare("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 10");
$activitiesStmt->execute();
$activities = $activitiesStmt->fetchAll();

// Today's notices
$noticesStmt = db()->prepare("SELECT * FROM notices WHERE status = 1 AND (expiry_date IS NULL OR expiry_date >= CURDATE()) ORDER BY publish_date DESC");
$noticesStmt->execute();
$notices = $noticesStmt->fetchAll();

// Recent complaints for table
$recentComplaintsStmt = db()->prepare("
    SELECT c.*, s.name, s.roll_no
    FROM complaints c
    JOIN students s ON s.id = c.student_id
    WHERE 1=1 $hostelFilter
    ORDER BY c.created_at DESC
    LIMIT 5
");
$recentComplaintsStmt->execute();
$recentComplaints = $recentComplaintsStmt->fetchAll();

// Recent leaves for table
$recentLeavesStmt = db()->prepare("
    SELECT l.*, s.name, s.roll_no
    FROM leaves l
    JOIN students s ON s.id = l.student_id
    WHERE 1=1 $hostelFilter
    ORDER BY l.applied_at DESC
    LIMIT 5
");
$recentLeavesStmt->execute();
$recentLeaves = $recentLeavesStmt->fetchAll();

// Weekly attendance trend (last 7 days)
$weeklyLabels = [];
$weeklyPresent = [];
$weeklyAbsent = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} day"));
    $weeklyLabels[] = date('D', strtotime("-{$i} day"));
    $pStmt = db()->prepare("SELECT COUNT(*) FROM attendance JOIN students s ON s.id = attendance.student_id WHERE attendance.date = ? AND attendance.status = 'Present' $hostelFilter");
    $pStmt->execute([$day]);
    $weeklyPresent[] = (int)$pStmt->fetchColumn();
    $aStmt = db()->prepare("SELECT COUNT(*) FROM attendance JOIN students s ON s.id = attendance.student_id WHERE attendance.date = ? AND attendance.status = 'Absent' $hostelFilter");
    $aStmt->execute([$day]);
    $weeklyAbsent[] = (int)$aStmt->fetchColumn();
}

$totalBedsStmt = db()->prepare("SELECT COALESCE(SUM(capacity), 0) FROM rooms");
$totalBedsStmt->execute();
$totalBeds = (int)$totalBedsStmt->fetchColumn();
$occPct = $totalBeds > 0 ? round(($totalBeds - $vacantBeds) / $totalBeds * 100) : 0;
?>
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">
            <i class="bi bi-building"></i> <?= $hostelType ? ucfirst($hostelType) . ' Hostel' : 'Hostel' ?> Dashboard
            <?php if ($hostelType): ?>
                <span class="badge bg-<?= $hostelType === 'boys' ? 'primary' : 'danger' ?> ms-2"><?= ucfirst($hostelType) ?></span>
            <?php endif; ?>
        </h5>
    </div>
    <!-- Row 1: 4 stat cards -->
    <div class="row g-3 mb-4 stagger-children">
        <div class="col-md-3 col-6">
            <div class="card stat-card border-primary h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number text-primary"><?= $attPct ?>%</div>
                            <div class="stat-label">Attendance</div>
                        </div>
                        <div class="stat-icon text-primary"><i class="bi bi-calendar-check"></i></div>
                    </div>
                    <div class="mt-2 small text-muted"><?= $todayPresent ?>/<?= $totalToday ?> present today</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card border-warning h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number text-warning"><?= $pendingLeaves ?></div>
                            <div class="stat-label">Pending Leaves</div>
                        </div>
                        <div class="stat-icon text-warning"><i class="bi bi-box-arrow-right"></i></div>
                    </div>
                    <div class="mt-2 small text-muted">Awaiting approval</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card border-danger h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number text-danger"><?= $pendingComplaints ?></div>
                            <div class="stat-label">Pending Complaints</div>
                        </div>
                        <div class="stat-icon text-danger"><i class="bi bi-exclamation-triangle"></i></div>
                    </div>
                    <div class="mt-2 small text-muted"><?= $escalatedComplaints ?> escalated</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card border-success h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number text-success"><?= $vacantBeds ?></div>
                            <div class="stat-label">Vacant Beds</div>
                        </div>
                        <div class="stat-icon text-success"><i class="bi bi-bed"></i></div>
                    </div>
                    <div class="mt-2 small text-muted"><?= $occPct ?>% occupancy</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 2: Today's stats -->
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
            <div class="card text-bg-info h-100">
                <div class="card-body text-center">
                    <div class="fs-1 fw-bold"><?= $todayVisitors ?></div>
                    <div class="small opacity-75">Visitors Today</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-bg-dark h-100">
                <div class="card-body text-center">
                    <div class="fs-1 fw-bold"><?= $escalatedComplaints ?></div>
                    <div class="small opacity-75">Escalated</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 3: Charts -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-pie-chart me-1"></i> Today's Attendance</div>
                <div class="card-body">
                    <div class="chart-container-sm" style="height:260px">
                        <canvas id="attPieChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-bar-chart me-1"></i> Weekly Attendance Trend</div>
                <div class="card-body">
                    <div class="chart-container-sm" style="height:260px">
                        <canvas id="weeklyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 4: Tables -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-exclamation-circle me-1"></i> Recent Complaints</span>
                    <a href="<?= BASE_URL ?>/warden/complaints.php" class="small">View all</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr><th>Student</th><th>Title</th><th>Status</th><th>Priority</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentComplaints as $c): ?>
                                <tr>
                                    <td class="fw-medium"><?= sanitize($c['name']) ?></td>
                                    <td><?= sanitize(mb_substr($c['title'], 0, 30)) ?></td>
                                    <td><span class="badge bg-<?= match($c['status']){'New'=>'primary','Under Inspection'=>'info','In Progress'=>'warning','Resolved'=>'success','Rejected'=>'danger'} ?>"><?= $c['status'] ?></span></td>
                                    <td><span class="badge bg-<?= match($c['priority']){'High'=>'danger','Medium'=>'warning','Low'=>'secondary'} ?>"><?= $c['priority'] ?? 'Normal' ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recentComplaints)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No complaints</td></tr>
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
                    <span><i class="bi bi-box-arrow-right me-1"></i> Recent Leaves</span>
                    <a href="<?= BASE_URL ?>/warden/leaves.php" class="small">View all</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr><th>Student</th><th>From</th><th>To</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentLeaves as $l): ?>
                                <tr>
                                    <td class="fw-medium"><?= sanitize($l['name']) ?></td>
                                    <td><?= date('d M', strtotime($l['from_date'])) ?></td>
                                    <td><?= date('d M', strtotime($l['to_date'])) ?></td>
                                    <td><span class="badge bg-<?= match($l['status']){'Pending'=>'warning','Approved'=>'success','Rejected'=>'danger'} ?>"><?= $l['status'] ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recentLeaves)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No leave requests</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 5: Quick Actions -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header"><i class="bi bi-lightning me-1"></i> Quick Actions</div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?= BASE_URL ?>/warden/attendance.php" class="btn btn-primary"><i class="bi bi-calendar-check"></i> Mark Attendance</a>
                        <a href="<?= BASE_URL ?>/warden/complaints.php" class="btn btn-warning"><i class="bi bi-exclamation-triangle"></i> View Complaints</a>
                        <a href="<?= BASE_URL ?>/warden/leaves.php" class="btn btn-success"><i class="bi bi-check-circle"></i> Approve Leaves</a>
                        <a href="<?= BASE_URL ?>/warden/room-inspection.php" class="btn btn-info text-white"><i class="bi bi-building"></i> Room Inspection</a>
                        <a href="<?= BASE_URL ?>/warden/maintenance.php" class="btn btn-secondary"><i class="bi bi-tools"></i> Maintenance</a>
                        <a href="<?= BASE_URL ?>/warden/visitors.php" class="btn btn-dark"><i class="bi bi-person-badge"></i> Visitors Log</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 6: Recent Activities + Notices -->
    <div class="row g-3 mb-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header"><i class="bi bi-activity me-1"></i> Recent Activities</div>
                <div class="card-body p-0">
                    <?php if (count($activities) > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($activities as $a): ?>
                        <div class="list-group-item py-3 px-3 d-flex align-items-start gap-3">
                            <div class="timeline-dot mt-1"></div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-medium small"><?= escapeOutput($a['action']) ?></div>
                                <?php if (!empty($a['description'])): ?>
                                <div class="text-muted small"><?= escapeOutput(mb_substr($a['description'], 0, 120)) ?></div>
                                <?php endif; ?>
                                <div class="text-muted" style="font-size:0.7rem"><?= timeAgo($a['created_at']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4 text-muted small">No recent activities</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-megaphone me-1"></i> Notices</span>
                    <a href="<?= BASE_URL ?>/warden/notices.php" class="small">View all</a>
                </div>
                <div class="card-body p-0">
                    <?php if (count($notices) > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($notices as $n): ?>
                        <div class="list-group-item py-3 px-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="badge bg-<?= match($n['priority']){'Critical'=>'danger','Urgent'=>'warning','Normal'=>'info'} ?> me-1"><?= $n['priority'] ?? 'Normal' ?></span>
                                    <span class="fw-medium small"><?= escapeOutput($n['title']) ?></span>
                                </div>
                                <small class="text-muted"><?= date('d M', strtotime($n['publish_date'])) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4 text-muted small">No notices</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Attendance Pie Chart
    new Chart(document.getElementById('attPieChart'), {
        type: 'doughnut',
        data: {
            labels: ['Present', 'Absent', 'Late', 'Leave'],
            datasets: [{
                data: [<?= $todayPresent ?>, <?= $todayAbsent ?>, <?= $todayLate ?>, <?= $todayLeave ?>],
                backgroundColor: ['#10b981', '#ef4444', '#f59e0b', '#6366f1'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { padding: 12, font: { size: 12 } } }
            }
        }
    });

    // Weekly Attendance Bar Chart
    new Chart(document.getElementById('weeklyChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($weeklyLabels) ?>,
            datasets: [
                {
                    label: 'Present',
                    data: <?= json_encode($weeklyPresent) ?>,
                    backgroundColor: 'rgba(16,185,129,0.7)',
                    borderRadius: 4
                },
                {
                    label: 'Absent',
                    data: <?= json_encode($weeklyAbsent) ?>,
                    backgroundColor: 'rgba(239,68,68,0.7)',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top', labels: { font: { size: 11 } } } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
});
</script>
<?php require_once __DIR__ . '/../includes/warden-footer.php'; ?>
