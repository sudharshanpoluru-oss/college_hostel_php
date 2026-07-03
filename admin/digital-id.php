<?php
$title = 'Digital Hostel ID';
require_once __DIR__ . '/../includes/admin-header.php';

// Auto-create student_digital_ids table if not exists
try {
    db()->query("SELECT 1 FROM student_digital_ids LIMIT 1");
} catch (Exception $e) {
    db()->exec("CREATE TABLE IF NOT EXISTS student_digital_ids (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL UNIQUE,
        id_number VARCHAR(50) NOT NULL UNIQUE,
        qr_code VARCHAR(500) DEFAULT NULL,
        blood_group VARCHAR(5) DEFAULT NULL,
        emergency_contact VARCHAR(20) DEFAULT NULL,
        emergency_name VARCHAR(100) DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$studentId = (int)($_GET['student_id'] ?? 0);

// Handle form submission for updating blood group / emergency contact
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $studentId && isset($_POST['update_digital_id'])) {
    requireCSRF();
    $blood_group      = sanitize($_POST['blood_group']);
    $emergency_contact = sanitize($_POST['emergency_contact']);
    $emergency_name   = sanitize($_POST['emergency_name']);

    try {
        $existing = db()->prepare("SELECT id FROM student_digital_ids WHERE student_id=?");
        $existing->execute([$studentId]);
        $row = $existing->fetch();

        if ($row) {
            db()->prepare("UPDATE student_digital_ids SET blood_group=?, emergency_contact=?, emergency_name=? WHERE student_id=?")
                ->execute([$blood_group, $emergency_contact, $emergency_name, $studentId]);
        } else {
            $idNumber = 'HSTL-' . date('Y') . '-' . str_pad($studentId, 4, '0', STR_PAD_LEFT);
            db()->prepare("INSERT INTO student_digital_ids (student_id, id_number, blood_group, emergency_contact, emergency_name) VALUES (?,?,?,?,?)")
                ->execute([$studentId, $idNumber, $blood_group, $emergency_contact, $emergency_name]);
        }
        setAlert('success', 'Digital ID updated successfully.');
    } catch (Exception $e) {
        setAlert('danger', 'Digital ID table not available. Please run the database migration.');
    }
    redirect(BASE_URL . '/admin/digital-id.php?student_id=' . $studentId);
}

// Helper: get or create digital ID record
function getOrCreateDigitalId($studentId) {
    try {
        $stmt = db()->prepare("SELECT * FROM student_digital_ids WHERE student_id=?");
        $stmt->execute([$studentId]);
        $digital = $stmt->fetch();

        if (!$digital) {
            $stu = db()->prepare("SELECT blood_group, emergency_contact, emergency_contact_name FROM students WHERE id=?");
            $stu->execute([$studentId]);
            $s = $stu->fetch();
            $idNumber = 'HSTL-' . date('Y') . '-' . str_pad($studentId, 4, '0', STR_PAD_LEFT);
            db()->prepare("INSERT INTO student_digital_ids (student_id, id_number, blood_group, emergency_contact, emergency_name) VALUES (?,?,?,?,?)")
                ->execute([$studentId, $idNumber, $s['blood_group'] ?? null, $s['emergency_contact'] ?? null, $s['emergency_contact_name'] ?? null]);
            $stmt = db()->prepare("SELECT * FROM student_digital_ids WHERE student_id=?");
            $stmt->execute([$studentId]);
            $digital = $stmt->fetch();
        }
        return $digital;
    } catch (Exception $e) {
        return false;
    }
}

// ─── Mode 2: ID Card View ───────────────────────────────────────────
if ($studentId):
    $stmt = db()->prepare("SELECT s.*, r.room_no, r.room_type, ra.bed_no FROM students s LEFT JOIN room_allocations ra ON ra.student_id=s.id AND ra.status='Active' LEFT JOIN rooms r ON r.id=ra.room_id WHERE s.id=?");
    $stmt->execute([$studentId]);
    $s = $stmt->fetch();

    if (!$s) {
        setAlert('danger', 'Student not found.');
        redirect(BASE_URL . '/admin/digital-id.php');
    }

    $digital = getOrCreateDigitalId($studentId);
    if ($digital === false) {
        $digital = ['id_number' => 'N/A', 'blood_group' => '', 'emergency_contact' => '', 'emergency_name' => '', 'issued_at' => date('Y-m-d H:i:s')];
    }

    $qrData = BASE_URL . '/student/profile.php?id=' . $studentId;
    $qrUrl  = 'https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=' . urlencode($qrData) . '&choe=UTF-8';

    // Determine academic year for "Valid Until"
    $yearNow = date('Y');
    $monthNow = (int)date('m');
    $academicEnd = ($monthNow > 6) ? ($yearNow + 1) : $yearNow;
    $validUntil = 'June ' . $academicEnd;

    $photoUrl = $s['photo'] ? BASE_URL . '/uploads/' . $s['photo'] : BASE_URL . '/assets/img/default-avatar.png';
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0"><i class="bi bi-card-heading"></i> Digital Hostel ID</h4>
        <div>
            <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
            <a href="<?= BASE_URL ?>/admin/digital-id.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to List</a>
        </div>
    </div>

    <div class="row g-4">
        <!-- ID Card -->
        <div class="col-lg-5">
            <div class="id-card" id="idCard">
                <div class="id-card-header">
                    <div class="id-card-logo">
                        <i class="bi bi-building"></i>
                    </div>
                    <div class="id-card-title">
                        <h5><?= SITE_NAME ?></h5>
                        <span>Digital Identity Card</span>
                    </div>
                </div>
                <div class="id-card-body">
                    <div class="id-card-photo">
                        <img src="<?= $photoUrl ?>" alt="Photo" onerror="this.src='<?= BASE_URL ?>/assets/img/default-avatar.png'">
                    </div>
                    <div class="id-card-details">
                        <h4 class="id-card-name"><?= sanitize($s['name']) ?></h4>
                        <table class="id-card-table">
                            <tr>
                                <td>ID Number</td>
                                <td><strong><?= sanitize($digital['id_number']) ?></strong></td>
                            </tr>
                            <tr>
                                <td>Roll No</td>
                                <td><?= sanitize($s['roll_no']) ?></td>
                            </tr>
                            <tr>
                                <td>Room</td>
                                <td><?= sanitize($s['room_no'] ?? 'N/A') ?> <?= $s['bed_no'] ? '/ Bed ' . sanitize($s['bed_no']) : '' ?></td>
                            </tr>
                            <tr>
                                <td>Course</td>
                                <td><?= sanitize($s['course']) ?> (<?= sanitize($s['year']) ?>)</td>
                            </tr>
                            <tr>
                                <td>Blood Group</td>
                                <td><span class="badge bg-danger"><?= sanitize($digital['blood_group'] ?: 'N/A') ?></span></td>
                            </tr>
                            <tr>
                                <td>Emergency</td>
                                <td>
                                    <?php if ($digital['emergency_name']): ?>
                                        <?= sanitize($digital['emergency_name']) ?> -
                                    <?php endif; ?>
                                    <?= sanitize($digital['emergency_contact'] ?: 'N/A') ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Issue Date</td>
                                <td><?= !empty($digital['issued_at']) ? date('d M Y', strtotime($digital['issued_at'])) : date('d M Y') ?></td>
                            </tr>
                            <tr>
                                <td>Valid Until</td>
                                <td><?= $validUntil ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="id-card-footer">
                    <div class="id-card-qr">
                        <img src="<?= $qrUrl ?>" alt="QR Code" width="80" height="80">
                    </div>
                    <div class="id-card-footer-text">
                        <small><?= BASE_URL ?>/student/profile.php?id=<?= $studentId ?></small>
                        <br><small>Authorised by Hostel Administration</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Form -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header fw-bold"><i class="bi bi-pencil-square"></i> Update ID Details</div>
                <div class="card-body">
                    <form method="post" class="row g-3"><?= csrfField() ?>
                        <div class="col-md-4">
                            <label class="form-label">Blood Group</label>
                            <select name="blood_group" class="form-select">
                                <option value="">-- Select --</option>
                                <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                                    <option value="<?= $bg ?>" <?= ($digital['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Emergency Contact Name</label>
                            <input type="text" name="emergency_name" class="form-control" value="<?= sanitize($digital['emergency_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Emergency Contact Phone</label>
                            <input type="text" name="emergency_contact" class="form-control" value="<?= sanitize($digital['emergency_contact'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <button type="submit" name="update_digital_id" class="btn btn-primary"><i class="bi bi-save"></i> Save Details</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header fw-bold"><i class="bi bi-info-circle"></i> Student Information</div>
                <div class="card-body">
                    <table class="table table-sm table-bordered mb-0">
                        <tr><th style="width:150px">Name</th><td><?= sanitize($s['name']) ?></td></tr>
                        <tr><th>Roll No</th><td><?= sanitize($s['roll_no']) ?></td></tr>
                        <tr><th>Email</th><td><?= sanitize($s['email']) ?></td></tr>
                        <tr><th>Phone</th><td><?= sanitize($s['phone']) ?></td></tr>
                        <tr><th>Gender</th><td><?= $s['gender'] ?></td></tr>
                        <tr><th>Course & Year</th><td><?= sanitize($s['course']) ?> - <?= sanitize($s['year']) ?></td></tr>
                        <tr><th>Room</th><td><?= sanitize($s['room_no'] ?? 'Not Allocated') ?></td></tr>
                        <tr><th>Address</th><td><?= sanitize($s['address']) ?></td></tr>
                        <tr><th>Guardian</th><td><?= sanitize($s['guardian_name']) ?> (<?= sanitize($s['guardian_phone']) ?>)</td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    body { background: #fff !important; }
    .sidebar, .navbar, .main-content > .container-fluid > .d-flex, .card:not(#idCard), .btn, .breadcrumb { display: none !important; }
    .main-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
    .id-card { box-shadow: none !important; border: 2px solid #000 !important; page-break-inside: avoid; }
    .container-fluid > .row { display: block !important; }
    .col-lg-5 { width: 100% !important; max-width: 420px !important; margin: 0 auto !important; }
    .id-card-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}

.id-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,.12);
    overflow: hidden;
    max-width: 420px;
    border: 1px solid #e0e0e0;
}

.id-card-header {
    background: linear-gradient(135deg, #1a237e 0%, #283593 50%, #3949ab 100%);
    color: #fff;
    padding: 20px 20px 16px;
    display: flex;
    align-items: center;
    gap: 14px;
}

.id-card-logo {
    width: 48px;
    height: 48px;
    background: rgba(255,255,255,.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.id-card-title h5 { margin: 0; font-weight: 700; font-size: 16px; line-height: 1.3; }
.id-card-title span { font-size: 11px; opacity: .85; }

.id-card-body {
    padding: 20px;
    display: flex;
    gap: 16px;
}

.id-card-photo {
    flex-shrink: 0;
}

.id-card-photo img {
    width: 100px;
    height: 120px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid #e0e0e0;
}

.id-card-details { flex: 1; min-width: 0; }

.id-card-name {
    font-size: 16px;
    font-weight: 700;
    margin: 0 0 10px;
    color: #1a237e;
    line-height: 1.3;
}

.id-card-table { width: 100%; font-size: 12px; }
.id-card-table td { padding: 3px 0; vertical-align: top; }
.id-card-table td:first-child { color: #666; width: 90px; padding-right: 6px; }

.id-card-footer {
    background: #f5f5f5;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-top: 1px solid #e0e0e0;
}

.id-card-qr { flex-shrink: 0; }
.id-card-qr img { display: block; }

.id-card-footer-text { font-size: 10px; color: #666; line-height: 1.4; }
</style>

<?php
// ─── Mode 1: Student List ───────────────────────────────────────────
else:
    $page    = (int)($_GET['p'] ?? 1);
    $perPage = 15;
    $search  = sanitize($_GET['search'] ?? '');

    $where = '';
    $params = [];
    $conditions = ["s.status = 'Active'"];
    if ($search) {
        $conditions[] = "(s.name LIKE ? OR s.roll_no LIKE ?)";
        $params = array_merge($params, ["%$search%", "%$search%"]);
    }
    $where = "WHERE " . implode(' AND ', $conditions);
    $total = db()->prepare("SELECT COUNT(*) FROM students s $where");
    $total->execute($params);
    $totalRows = $total->fetchColumn();
    $offset = ($page - 1) * $perPage;
    $pages  = paginate($page, $perPage, $totalRows);

    try {
        $stmt = db()->prepare("SELECT s.*, r.room_no, d.id_number FROM students s LEFT JOIN room_allocations ra ON ra.student_id=s.id AND ra.status='Active' LEFT JOIN rooms r ON r.id=ra.room_id LEFT JOIN student_digital_ids d ON d.student_id=s.id $where ORDER BY s.name ASC LIMIT $perPage OFFSET $offset");
        $stmt->execute($params);
        $students = $stmt->fetchAll();
    } catch (Exception $e) {
        $stmt = db()->prepare("SELECT s.*, r.room_no FROM students s LEFT JOIN room_allocations ra ON ra.student_id=s.id AND ra.status='Active' LEFT JOIN rooms r ON r.id=ra.room_id $where ORDER BY s.name ASC LIMIT $perPage OFFSET $offset");
        $stmt->execute($params);
        $students = $stmt->fetchAll();
    }
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0"><i class="bi bi-card-heading"></i> Digital Hostel IDs</h4>
    </div>

    <form method="get" class="row g-2 mb-3">
        <div class="col-auto">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by name or roll no..." value="<?= sanitize($search) ?>">
        </div>
        <div class="col-auto">
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i> Search</button>
        </div>
        <?php if ($search): ?>
        <div class="col-auto">
            <a href="<?= BASE_URL ?>/admin/digital-id.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i> Clear</a>
        </div>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Roll No</th>
                    <th>Course</th>
                    <th>Year</th>
                    <th>Room</th>
                    <th>ID Number</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($students) === 0): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No active students found.</td></tr>
                <?php endif; ?>
                <?php foreach ($students as $i => $s): ?>
                <tr>
                    <td><?= $offset + $i + 1 ?></td>
                    <td>
                        <?php if ($s['photo']): ?>
                            <img src="<?= BASE_URL ?>/uploads/<?= $s['photo'] ?>" alt="" style="width:30px;height:36px;object-fit:cover;border-radius:4px" class="me-1">
                        <?php endif; ?>
                        <?= sanitize($s['name']) ?>
                    </td>
                    <td><?= sanitize($s['roll_no']) ?></td>
                    <td><?= sanitize($s['course']) ?></td>
                    <td><?= sanitize($s['year']) ?></td>
                    <td><?= sanitize($s['room_no'] ?? 'N/A') ?></td>
                    <td><code><?= sanitize($s['id_number'] ?? '—') ?></code></td>
                    <td>
                        <a href="?student_id=<?= $s['id'] ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-eye"></i> View ID
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= paginationLinks($page, $pages) ?>
</div>
<?php
endif;

require_once __DIR__ . '/../includes/admin-footer.php';
?>
