<?php
$title = 'Attendance';
require_once __DIR__ . '/../includes/student-header.php';

$studentStmt = db()->prepare("SELECT * FROM students WHERE user_id = ?");
$studentStmt->execute([$_SESSION['user_id']]);
$student = $studentStmt->fetch();

$monthFilter = $_GET['month'] ?? '';

$sql = "SELECT * FROM attendance WHERE student_id = ?";
$params = [$student['id']];

if ($monthFilter) {
    $sql .= " AND DATE_FORMAT(date, '%Y-%m') = ?";
    $params[] = $monthFilter;
}

$sql .= " ORDER BY date DESC";
$attStmt = db()->prepare($sql);
$attStmt->execute($params);
$attendance = $attStmt->fetchAll();

$countStmt = db()->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present FROM attendance WHERE student_id = ?");
$countStmt->execute([$student['id']]);
$counts = $countStmt->fetch();
$percent = $counts['total'] ? round(($counts['present'] / $counts['total']) * 100, 1) : 0;
?>

<div class="container-fluid">
    <h3 class="mb-4">Attendance</h3>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h5 class="card-title">Attendance Percentage</h5>
                    <p class="display-4 text-info"><?= $percent ?>%</p>
                    <small><?= $counts['present'] ?>/<?= $counts['total'] ?> days</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Attendance Records</strong>
            <form method="GET" class="d-flex gap-2 align-items-center">
                <label class="form-label mb-0">Filter by Month:</label>
                <input type="month" name="month" class="form-control form-control-sm w-auto" value="<?= htmlspecialchars($monthFilter) ?>">
                <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                <?php if ($monthFilter): ?>
                    <a href="attendance.php" class="btn btn-sm btn-secondary">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($attendance): ?>
                            <?php $i = 1; foreach ($attendance as $row): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><?= date('d M Y', strtotime($row['date'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $row['status'] == 'Present' ? 'success' : 'danger' ?>">
                                            <?= $row['status'] ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center text-muted">No attendance records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/student-footer.php'; ?>
