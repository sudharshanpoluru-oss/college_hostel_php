<?php
$title = 'Dashboard';
require_once __DIR__ . '/../includes/student-header.php';

$studentStmt = db()->prepare("SELECT * FROM students WHERE user_id = ?");
$studentStmt->execute([$_SESSION['user_id']]);
$student = $studentStmt->fetch();

if (!$student) {
    setAlert('danger', 'Student record not found. Please contact admin.');
    displayAlert();
    require_once __DIR__ . '/../includes/student-footer.php';
    exit;
}

$roomStmt = db()->prepare("SELECT r.*, ra.bed_no, ra.allocation_date FROM room_allocations ra JOIN rooms r ON ra.room_id = r.id WHERE ra.student_id = ? AND ra.status = 'Active' LIMIT 1");
$roomStmt->execute([$student['id']]);
$room = $roomStmt->fetch();

$feeStmt = db()->prepare("SELECT total_fee, paid_amount, due_amount, status, payment_date FROM fees WHERE student_id = ? ORDER BY id DESC LIMIT 5");
$feeStmt->execute([$student['id']]);
$fees = $feeStmt->fetchAll();

$attStmt = db()->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present, SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent FROM attendance WHERE student_id = ?");
$attStmt->execute([$student['id']]);
$attendance = $attStmt->fetch();

$compStmt = db()->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) as pending, SUM(CASE WHEN status='Working' THEN 1 ELSE 0 END) as working, SUM(CASE WHEN status='Resolved' THEN 1 ELSE 0 END) as resolved FROM complaints WHERE student_id = ?");
$compStmt->execute([$student['id']]);
$complaints = $compStmt->fetch();

$leaveStmt = db()->prepare("SELECT COUNT(*) FROM leaves WHERE student_id = ? AND status = 'Pending'");
$leaveStmt->execute([$student['id']]);
$pendingLeaves = $leaveStmt->fetchColumn();

// Today's menu
$todayMenu = db()->prepare("SELECT * FROM mess_menu WHERE day = DAYNAME(CURDATE()) AND status=1 ORDER BY FIELD(meal_type,'Breakfast','Lunch','Evening Snacks','Dinner')");
$todayMenu->execute();
$todaysMenu = $todayMenu->fetchAll();

// Upcoming events
$events = db()->prepare("SELECT * FROM hostel_events WHERE event_date >= CURDATE() AND status=1 ORDER BY event_date ASC LIMIT 3");
$events->execute();
$upcomingEvents = $events->fetchAll();

// Latest notices
$noticesStmt = db()->prepare("SELECT * FROM notices WHERE status = 1 AND (expiry_date IS NULL OR expiry_date >= CURDATE()) ORDER BY publish_date DESC LIMIT 5");
$noticesStmt->execute();
$notices = $noticesStmt->fetchAll();

// Recent activity
$activityStmt = db()->prepare("SELECT * FROM activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$activityStmt->execute([$_SESSION['user_id']]);
$activities = $activityStmt->fetchAll();

// Monthly attendance for chart
$monthlyAtt = db()->prepare("SELECT DATE_FORMAT(date,'%b') as month, COUNT(*) as total, SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) as present FROM attendance WHERE student_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(date,'%Y-%m') ORDER BY MIN(date)");
$monthlyAtt->execute([$student['id']]);
$attData = $monthlyAtt->fetchAll();
$attMonths = []; $attPresent = []; $attTotal = [];
foreach ($attData as $a) {
    $attMonths[] = $a['month'];
    $attPresent[] = (int)$a['present'];
    $attTotal[] = (int)$a['total'];
}
$attPct = $attendance['total'] > 0 ? round(($attendance['present'] / $attendance['total']) * 100, 1) : 0;
?>
<div class="container-fluid px-0">
    <div class="d-flex align-items-center gap-3 mb-4">
        <div>
            <?php if ($student['photo']): ?>
            <img src="<?= BASE_URL ?>/uploads/<?= $student['photo'] ?>" class="profile-avatar" alt="">
            <?php else: ?>
            <div class="profile-avatar d-flex align-items-center justify-content-center bg-light text-primary fs-2">
                <i class="bi bi-person-circle"></i>
            </div>
            <?php endif; ?>
        </div>
        <div>
            <h4 class="mb-0 fw-bold">Welcome, <?= sanitize($student['name']) ?>!</h4>
            <p class="text-muted mb-0"><?= sanitize($student['roll_no']) ?> &middot; <?= sanitize($student['course']) ?> &middot; Year <?= sanitize($student['year']) ?></p>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row g-3 mb-4 stagger-children">
        <div class="col-md-3 col-6">
            <div class="card stat-card border-primary h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number text-primary"><?= $room ? sanitize($room['room_no']) : '--' ?></div>
                            <div class="stat-label">My Room</div>
                        </div>
                        <div class="stat-icon text-primary"><i class="bi bi-door-open"></i></div>
                    </div>
                    <?php if ($room): ?>
                    <div class="mt-1 small text-muted"><?= sanitize($room['room_type']) ?> &middot; Bed <?= $room['bed_no'] ?></div>
                    <?php else: ?>
                    <div class="mt-1 small text-muted">Not allocated yet</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card border-success h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number text-success"><?= $attPct ?>%</div>
                            <div class="stat-label">Attendance</div>
                        </div>
                        <div class="stat-icon text-success"><i class="bi bi-calendar-check"></i></div>
                    </div>
                    <div class="mt-1 small text-muted"><?= (int)$attendance['present'] ?>/<?= (int)$attendance['total'] ?> days</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card border-warning h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number text-warning"><?= (int)$complaints['pending'] ?></div>
                            <div class="stat-label">Pending Issues</div>
                        </div>
                        <div class="stat-icon text-warning"><i class="bi bi-exclamation-triangle"></i></div>
                    </div>
                    <div class="mt-1 small text-muted"><?= (int)$complaints['resolved'] ?> resolved</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card border-info h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number text-info"><?= $pendingLeaves ?></div>
                            <div class="stat-label">Leave Requests</div>
                        </div>
                        <div class="stat-icon text-info"><i class="bi bi-box-arrow-right"></i></div>
                    </div>
                    <div class="mt-1 small text-muted">Pending approval</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <!-- Attendance Chart -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-bar-chart me-1"></i> Monthly Attendance</span>
                    <span class="badge bg-<?= $attPct >= 75 ? 'success' : 'warning' ?>"><?= $attPct ?>%</span>
                </div>
                <div class="card-body">
                    <div class="chart-container-sm" style="height:220px">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <!-- Today's Menu -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-header"><i class="bi bi-cup-hot me-1"></i> Today's Menu</div>
                <div class="card-body p-0">
                    <?php if (count($todaysMenu) > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($todaysMenu as $meal): ?>
                        <div class="list-group-item py-2 px-3">
                            <small class="text-muted fw-medium d-block"><?= $meal['meal_type'] ?></small>
                            <span><?= sanitize($meal['menu_items']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-3 text-muted small">No menu for today</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- Quick Actions -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-header"><i class="bi bi-lightning me-1"></i> Quick Actions</div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="<?= BASE_URL ?>/student/complaints.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-plus-circle"></i> Raise Complaint</a>
                        <a href="<?= BASE_URL ?>/student/leave.php" class="btn btn-outline-success btn-sm"><i class="bi bi-calendar-plus"></i> Apply Leave</a>
                        <a href="<?= BASE_URL ?>/student/fees.php" class="btn btn-outline-warning btn-sm"><i class="bi bi-cash"></i> Pay Fees</a>
                        <a href="<?= BASE_URL ?>/student/room-change.php" class="btn btn-outline-info btn-sm"><i class="bi bi-arrow-left-right"></i> Change Room</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <!-- Fee Status -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-cash-coin me-1"></i> Payment History</span>
                    <a href="<?= BASE_URL ?>/student/fees.php" class="small">View all</a>
                </div>
                <div class="card-body p-0">
                    <?php if (count($fees) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr><th>Amount</th><th>Paid</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fees as $f): ?>
                                <tr>
                                    <td>Rs.<?= number_format($f['total_fee']) ?></td>
                                    <td>Rs.<?= number_format($f['paid_amount']) ?></td>
                                    <td><span class="badge bg-<?= match($f['status']){'Paid'=>'success','Partial'=>'warning','Pending'=>'danger'} ?>"><?= $f['status'] ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-3 text-muted small">No fee records</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- Upcoming Events -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><i class="bi bi-calendar-event me-1"></i> Upcoming Events</div>
                <div class="card-body p-0">
                    <?php if (count($upcomingEvents) > 0): ?>
                        <?php foreach ($upcomingEvents as $e): ?>
                        <div class="d-flex align-items-start gap-3 p-3 border-bottom">
                            <div class="text-center flex-shrink-0">
                                <div class="fw-bold text-primary fs-5"><?= date('d', strtotime($e['event_date'])) ?></div>
                                <div class="small text-muted"><?= date('M', strtotime($e['event_date'])) ?></div>
                            </div>
                            <div>
                                <div class="fw-medium"><?= sanitize($e['title']) ?></div>
                                <div class="small text-muted"><?= sanitize($e['description']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div class="text-center py-3 text-muted small">No upcoming events</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- Recent Activity -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><i class="bi bi-activity me-1"></i> Recent Activity</div>
                <div class="card-body p-3">
                    <?php if (count($activities) > 0): ?>
                    <div class="timeline">
                        <?php foreach ($activities as $a): ?>
                        <div class="timeline-item info">
                            <div class="timeline-content">
                                <div class="small fw-medium"><?= sanitize($a['action']) ?></div>
                                <div class="small text-muted"><?= timeAgo($a['created_at']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-3 text-muted small">No recent activity</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Latest Notices -->
    <?php if (count($notices) > 0): ?>
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-megaphone me-1"></i> Latest Notices</div>
        <div class="card-body p-0">
            <?php foreach ($notices as $i => $notice): ?>
            <div class="p-3 <?= $i > 0 ? 'border-top' : '' ?>">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="badge bg-<?= match($notice['priority']){'Critical'=>'danger','Urgent'=>'warning','Normal'=>'info'} ?> me-1"><?= $notice['priority'] ?></span>
                        <span class="fw-medium"><?= sanitize($notice['title']) ?></span>
                    </div>
                    <small class="text-muted"><?= date('d M Y', strtotime($notice['publish_date'])) ?></small>
                </div>
                <div class="mt-1 small text-muted"><?= nl2br(sanitize(mb_substr($notice['content'], 0, 200))) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('attendanceChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($attMonths) ?>,
            datasets: [{
                label: 'Present',
                data: <?= json_encode($attPresent) ?>,
                backgroundColor: 'rgba(16,185,129,0.7)',
                borderRadius: 4
            }, {
                label: 'Total',
                data: <?= json_encode($attTotal) ?>,
                backgroundColor: 'rgba(100,116,139,0.3)',
                borderRadius: 4
            }]
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
<?php require_once __DIR__ . '/../includes/student-footer.php'; ?>
