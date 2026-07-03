<?php
$title = 'Medical Records';
require_once __DIR__ . '/../includes/warden-header.php';

$hostelType = getWardenHostelType();
$hostelFilter = $hostelType ? " AND s.hostel_type = '$hostelType'" : '';

$action = $_GET['action'] ?? 'list';
$page   = (int)($_GET['p'] ?? 1);
$perPage = 20;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    requireCSRF();
    $student_id = (int)($_POST['student_id'] ?? 0);
    $disease = trim($_POST['disease'] ?? '');
    $symptoms = trim($_POST['symptoms'] ?? '');
    $medicine = trim($_POST['medicine'] ?? '');
    $doctor = trim($_POST['doctor'] ?? '');
    $hospital = trim($_POST['hospital'] ?? '');
    $visit_date = $_POST['visit_date'] ?? date('Y-m-d');
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    try {
        $stmt = db()->prepare("INSERT INTO medical_records (student_id, disease, symptoms, medicine, doctor, hospital, visit_date, emergency_contact, remarks, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$student_id, $disease, $symptoms, $medicine, $doctor, $hospital, $visit_date, $emergency_contact, $remarks, $_SESSION['user_id']]);
        auditLog('Medical Record Added', 'Medical', "Medical record added for student ID $student_id");
        setAlert('success', 'Medical record added successfully.');
    } catch (Exception $e) {
        setAlert('danger', 'Error: ' . $e->getMessage());
    }
    redirect(BASE_URL . '/warden/medical.php');
}

// --- LIST ---
$countSql = "SELECT COUNT(*) FROM medical_records m JOIN students s ON s.id = m.student_id WHERE 1=1 $hostelFilter";
$totalRows = db()->query($countSql)->fetchColumn();
$pages = paginate($page, $perPage, $totalRows);

$sql = "SELECT m.*, s.name AS student_name, s.roll_no FROM medical_records m JOIN students s ON s.id = m.student_id WHERE 1=1 $hostelFilter ORDER BY m.visit_date DESC, m.created_at DESC LIMIT $perPage OFFSET {$pages['offset']}";
$records = db()->query($sql)->fetchAll();
$students = db()->query("SELECT id, name, roll_no FROM students WHERE status = 'Active'" . ($hostelType ? " AND hostel_type='$hostelType'" : '') . " ORDER BY name")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="mb-0"><i class="bi bi-heart-pulse"></i> Medical Records</h5>
    <a href="?action=add" class="btn btn-primary btn-sm <?= $action === 'add' ? 'active' : '' ?>"><i class="bi bi-plus-lg"></i> Add Record</a>
</div>

<?php if ($action === 'add'): ?>
<div class="card">
    <div class="card-header"><strong>Add Medical Record</strong></div>
    <div class="card-body">
        <form method="post" action="?action=add">
            <?= csrfField() ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Student <span class="text-danger">*</span></label>
                    <select name="student_id" class="form-select" required>
                        <option value="">Select Student</option>
                        <?php foreach ($students as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= sanitize($s['name']) ?> (<?= sanitize($s['roll_no']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Visit Date <span class="text-danger">*</span></label>
                    <input type="date" name="visit_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Disease / Condition</label>
                    <input type="text" name="disease" class="form-control" placeholder="e.g. Fever, Cold">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Symptoms</label>
                    <textarea name="symptoms" class="form-control" rows="2" placeholder="Symptoms..."></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Medicine Prescribed</label>
                    <textarea name="medicine" class="form-control" rows="2" placeholder="Medicine details..."></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Doctor</label>
                    <input type="text" name="doctor" class="form-control" placeholder="Doctor name">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Hospital / Clinic</label>
                    <input type="text" name="hospital" class="form-control" placeholder="Hospital name">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Emergency Contact</label>
                    <input type="text" name="emergency_contact" class="form-control" placeholder="Phone">
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

<?php else: ?>

<div class="table-responsive">
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Student</th>
                <th>Disease</th>
                <th>Doctor</th>
                <th>Hospital</th>
                <th>Visit Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($records): $i = $pages['offset']; ?>
                <?php foreach ($records as $r): $i++; ?>
                <tr>
                    <td><?= $i ?></td>
                    <td class="fw-medium"><?= sanitize($r['student_name']) ?> <small class="text-muted">(<?= sanitize($r['roll_no']) ?>)</small></td>
                    <td><?= sanitize($r['disease'] ?? '-') ?></td>
                    <td><?= sanitize($r['doctor'] ?? '-') ?></td>
                    <td><?= sanitize($r['hospital'] ?? '-') ?></td>
                    <td><small><?= $r['visit_date'] ?></small></td>
                    <td>
                        <a href="#" class="btn btn-sm btn-outline-info" title="Details"
                           onclick="alert('Student: <?= sanitize($r['student_name']) ?>\nDisease: <?= sanitize($r['disease'] ?? 'N/A') ?>\nSymptoms: <?= sanitize($r['symptoms'] ?? 'N/A') ?>\nMedicine: <?= sanitize($r['medicine'] ?? 'N/A') ?>\nDoctor: <?= sanitize($r['doctor'] ?? 'N/A') ?>\nHospital: <?= sanitize($r['hospital'] ?? 'N/A') ?>\nVisit Date: <?= $r['visit_date'] ?>\nEmergency Contact: <?= sanitize($r['emergency_contact'] ?? 'N/A') ?>\nRemarks: <?= sanitize($r['remarks'] ?? 'N/A') ?>'); return false;">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" class="text-center text-muted">No medical records found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?= paginationLinks($page, $pages) ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/warden-footer.php'; ?>
