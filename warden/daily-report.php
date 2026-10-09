<?php
$title = 'Daily Report';
require_once __DIR__ . '/../includes/warden-header.php';

$hostelType = getWardenHostelType();

$action = $_GET['action'] ?? 'list';
$page   = (int)($_GET['p'] ?? 1);
$perPage = 20;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    requireCSRF();
    $report_date = $_POST['report_date'] ?? date('Y-m-d');
    $attendance = trim($_POST['attendance_summary'] ?? '');
    $complaints = trim($_POST['complaints_summary'] ?? '');
    $visitors = trim($_POST['visitors_summary'] ?? '');
    $sick = trim($_POST['sick_students'] ?? '');
    $maintenance = trim($_POST['maintenance_issues'] ?? '');
    $discipline = trim($_POST['discipline_cases'] ?? '');
    $notes = trim($_POST['important_notes'] ?? '');

    $check = db()->prepare("SELECT COUNT(*) FROM daily_reports WHERE report_date = ?");
    $check->execute([$report_date]);
    if ($check->fetchColumn() > 0) {
        setAlert('danger', 'A report for ' . $report_date . ' already exists.');
    } else {
        try {
            $stmt = db()->prepare("INSERT INTO daily_reports (report_date, attendance_summary, complaints_summary, visitors_summary, sick_students, maintenance_issues, discipline_cases, important_notes, submitted_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$report_date, $attendance, $complaints, $visitors, $sick, $maintenance, $discipline, $notes, $_SESSION['user_id']]);
            auditLog('Daily Report Added', 'Daily Report', "Report submitted for $report_date");
            setAlert('success', 'Daily report submitted successfully.');
        } catch (Exception $e) {
            setAlert('danger', 'Error: ' . $e->getMessage());
        }
    }
    redirect(BASE_URL . '/warden/daily-report.php');
}

// --- LIST ---
$countSql = "SELECT COUNT(*) FROM daily_reports";
$totalRows = db()->query($countSql)->fetchColumn();
$pages = paginate($page, $perPage, $totalRows);

$sql = "SELECT dr.*, u.username AS submitter FROM daily_reports dr LEFT JOIN users u ON u.id = dr.submitted_by ORDER BY dr.report_date DESC, dr.submitted_at DESC LIMIT $perPage OFFSET {$pages['offset']}";
$records = db()->query($sql)->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="mb-0"><i class="bi bi-file-text"></i> Daily Report <?= $hostelType ? '<span class="badge bg-info ms-2">' . ucfirst($hostelType) . '</span>' : '' ?></h5>
    <a href="?action=add" class="btn btn-primary btn-sm <?= $action === 'add' ? 'active' : '' ?>"><i class="bi bi-plus-lg"></i> New Report</a>
</div>

<?php if ($action === 'add'): ?>
<div class="card">
    <div class="card-header"><strong>Submit Daily Report</strong></div>
    <div class="card-body">
        <form method="post" action="?action=add">
            <?= csrfField() ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Report Date <span class="text-danger">*</span></label>
                    <input type="date" name="report_date" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="row g-3 mt-2">
                <div class="col-md-6">
                    <label class="form-label">Attendance Summary</label>
                    <textarea name="attendance_summary" class="form-control" rows="3" placeholder="Total present, absent, late count..."></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Complaints Summary</label>
                    <textarea name="complaints_summary" class="form-control" rows="3" placeholder="Any complaints received today..."></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Visitors Summary</label>
                    <textarea name="visitors_summary" class="form-control" rows="3" placeholder="Visitor entries today..."></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Sick Students</label>
                    <textarea name="sick_students" class="form-control" rows="3" placeholder="Students reported sick..."></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Maintenance Issues</label>
                    <textarea name="maintenance_issues" class="form-control" rows="3" placeholder="Any maintenance issues reported..."></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Discipline Cases</label>
                    <textarea name="discipline_cases" class="form-control" rows="3" placeholder="Any discipline issues today..."></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Important Notes</label>
                    <textarea name="important_notes" class="form-control" rows="3" placeholder="Any additional important information..."></textarea>
                </div>
            </div>
            <div class="mt-3">
                <button class="btn btn-primary"><i class="bi bi-save"></i> Submit Report</button>
                <a href="?" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php else: ?>

<div class="table-responsive">
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Submitted By</th>
                <th>Summary Preview</th>
                <th>Submitted At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($records): $i = $pages['offset']; ?>
                <?php foreach ($records as $r): $i++; ?>
                <tr>
                    <td><?= $i ?></td>
                    <td class="fw-medium"><?= $r['report_date'] ?></td>
                    <td><?= sanitize($r['submitter'] ?? '-') ?></td>
                    <td><small><?= sanitize(mb_substr($r['important_notes'] ?? $r['attendance_summary'] ?? 'No notes', 0, 80)) ?>...</small></td>
                    <td><small><?= date('d M Y h:i A', strtotime($r['submitted_at'])) ?></small></td>
                    <td>
                        <a href="#" class="btn btn-sm btn-outline-info" title="View Full Report"
                           onclick="alert('Date: <?= $r['report_date'] ?>\n\nAttendance: <?= sanitize($r['attendance_summary'] ?? 'N/A') ?>\n\nComplaints: <?= sanitize($r['complaints_summary'] ?? 'N/A') ?>\n\nVisitors: <?= sanitize($r['visitors_summary'] ?? 'N/A') ?>\n\nSick Students: <?= sanitize($r['sick_students'] ?? 'N/A') ?>\n\nMaintenance: <?= sanitize($r['maintenance_issues'] ?? 'N/A') ?>\n\nDiscipline: <?= sanitize($r['discipline_cases'] ?? 'N/A') ?>\n\nImportant Notes: <?= sanitize($r['important_notes'] ?? 'N/A') ?>'); return false;">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" class="text-center text-muted">No reports found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?= paginationLinks($page, $pages) ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/warden-footer.php'; ?>
