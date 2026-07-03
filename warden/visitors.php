<?php
$title = 'Visitor Management';
require_once __DIR__ . '/../includes/warden-header.php';

$hostelType = getWardenHostelType();
$hostelFilter = $hostelType ? " AND s.hostel_type = '$hostelType'" : '';

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

function generateVisitorQR($visitor) {
    $data = json_encode([
        'type' => 'visitor',
        'id' => $visitor['id'],
        'name' => $visitor['visitor_name'],
        'contact' => $visitor['contact'],
        'purpose' => $visitor['purpose'],
        'check_in' => $visitor['check_in']
    ]);
    return 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' . urlencode($data) . '&choe=UTF-8';
}
$page   = (int)($_GET['p'] ?? 1);
$perPage = 20;
$search = trim($_GET['search'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'approve' && $id) {
        $stmt = db()->prepare("UPDATE visitor_logs SET warden_approved = 1, status = 'Approved' WHERE id = ?");
        $stmt->execute([$id]);
        auditLog('Visitor Approved', 'Visitors', "Visitor ID $id approved");
        setAlert('success', 'Visitor approved successfully.');
    } elseif ($action === 'reject' && $id) {
        $stmt = db()->prepare("UPDATE visitor_logs SET status = 'Rejected' WHERE id = ?");
        $stmt->execute([$id]);
        auditLog('Visitor Rejected', 'Visitors', "Visitor ID $id rejected");
        setAlert('success', 'Visitor rejected.');
    } elseif ($action === 'check_out' && $id) {
        $stmt = db()->prepare("UPDATE visitor_logs SET check_out = NOW(), status = 'Checked Out' WHERE id = ?");
        $stmt->execute([$id]);
        auditLog('Visitor Checked Out', 'Visitors', "Visitor ID $id checked out");
        setAlert('success', 'Visitor checked out successfully.');
    } elseif ($action === 'add') {
        $visitor_name = sanitize($_POST['visitor_name'] ?? '');
        $contact      = sanitize($_POST['contact'] ?? '');
        $purpose      = sanitize($_POST['purpose'] ?? '');
        $student_id   = !empty($_POST['student_id']) ? (int)$_POST['student_id'] : null;
        $remarks      = sanitize($_POST['remarks'] ?? '');
        if (!$visitor_name) {
            setAlert('danger', 'Visitor name is required.');
            redirect(BASE_URL . '/warden/visitors.php?action=add');
        }
        $id_proof = '';
        if (!empty($_FILES['id_proof']['name'])) {
            $ext      = pathinfo($_FILES['id_proof']['name'], PATHINFO_EXTENSION);
            $id_proof = uniqid('vis_') . '.' . $ext;
            move_uploaded_file($_FILES['id_proof']['tmp_name'], __DIR__ . '/../uploads/' . $id_proof);
        }
        $stmt = db()->prepare("INSERT INTO visitor_logs (visitor_name, contact, purpose, student_id, check_in, id_proof, remarks, created_by) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)");
        $stmt->execute([$visitor_name, $contact, $purpose, $student_id, $id_proof, $remarks, $_SESSION['user_id']]);
        setAlert('success', 'Visitor logged successfully.');
        redirect(BASE_URL . '/warden/visitors.php');
    }
    redirect(BASE_URL . '/warden/visitors.php');
}

if ($action === 'qr' && $id) {
    $stmt = db()->prepare("SELECT v.*, s.name AS student_name, s.roll_no FROM visitor_logs v LEFT JOIN students s ON s.id=v.student_id WHERE v.id=? $hostelFilter");
    $stmt->execute([$id]);
    $visitor = $stmt->fetch();
    if (!$visitor) { setAlert('danger', 'Visitor not found.'); redirect(BASE_URL . '/warden/visitors.php'); }
    ?>
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="m-0">Visitor QR Code</h5>
                <a href="<?= BASE_URL ?>/warden/visitors.php" class="btn btn-sm btn-secondary">&larr; Back</a>
            </div>
            <div class="card-body text-center">
                <p><strong>Visitor:</strong> <?= sanitize($visitor['visitor_name']) ?></p>
                <p><strong>Contact:</strong> <?= sanitize($visitor['contact']) ?></p>
                <p><strong>Purpose:</strong> <?= sanitize($visitor['purpose']) ?></p>
                <p><strong>Status:</strong> <?= $visitor['status'] ?></p>
                <img src="<?= generateVisitorQR($visitor) ?>" alt="QR Code" class="img-fluid border p-2">
                <br><br>
                <button onclick="window.print()" class="btn btn-primary">Print</button>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/../includes/warden-footer.php';
    exit;
}

$students = db()->query("SELECT id, name, roll_no FROM students WHERE status='Active'" . ($hostelType ? " AND hostel_type='$hostelType'" : '') . " ORDER BY name")->fetchAll();

$where = '';
$params = [];
if ($search !== '') {
    $where = "WHERE (v.visitor_name LIKE ? OR v.contact LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$where .= $hostelFilter ? ($where ? $hostelFilter : "WHERE 1=1 $hostelFilter") : '';

$totalSql = "SELECT COUNT(*) FROM visitor_logs v LEFT JOIN students s ON s.id = v.student_id $where";
$totalStmt = db()->prepare($totalSql);
$totalStmt->execute($params);
$totalRows = $totalStmt->fetchColumn();
$pages = paginate($page, $perPage, $totalRows);

$sql = "SELECT v.*, s.name AS student_name, s.roll_no FROM visitor_logs v LEFT JOIN students s ON s.id = v.student_id $where ORDER BY v.check_in DESC LIMIT $perPage OFFSET {$pages['offset']}";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();
?>

<div class="container-fluid">
    <?php if ($action === 'add'): ?>
    <h4 class="mb-3">Log New Visitor</h4>
    <form method="post" action="?action=add" enctype="multipart/form-data" class="row g-3">
        <div class="col-md-4"><label>Visitor Name *</label><input type="text" name="visitor_name" class="form-control" required></div>
        <div class="col-md-4"><label>Contact</label><input type="text" name="contact" class="form-control"></div>
        <div class="col-md-4"><label>Student (optional)</label>
            <select name="student_id" class="form-select">
                <option value="">-- General Visitor --</option>
                <?php foreach ($students as $s): ?>
                <option value="<?= $s['id'] ?>"><?= sanitize($s['name']) ?> (<?= sanitize($s['roll_no']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6"><label>Purpose</label><textarea name="purpose" class="form-control" rows="3"></textarea></div>
        <div class="col-md-6"><label>Remarks</label><textarea name="remarks" class="form-control" rows="3"></textarea></div>
        <div class="col-md-4"><label>ID Proof</label><input type="file" name="id_proof" class="form-control"></div>
        <div class="col-12">
            <?= csrfField() ?>
            <button class="btn btn-primary">Log Visitor</button>
            <a href="<?= BASE_URL ?>/warden/visitors.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>

    <?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-person-badge"></i> Visitor Management</h5>
        <div class="d-flex gap-2">
            <a href="?action=add" class="btn btn-primary btn-sm">+ Log Visitor</a>
            <form method="get" class="d-flex gap-2">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by name or contact..." value="<?= sanitize($search) ?>" style="min-width:200px">
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
                <?php if ($search !== ''): ?>
                <a href="?" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Student</th>
                    <th>Purpose</th>
                    <th>Check In</th>
                    <th>Status</th>
                    <th>QR</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($records): $i = $pages['offset']; ?>
                    <?php foreach ($records as $r): $i++;
                    $isPending = $r['status'] === 'Pending';
                    ?>
                    <tr class="<?= $isPending ? 'table-warning' : '' ?>">
                        <td><?= $i ?></td>
                        <td class="fw-medium"><?= sanitize($r['visitor_name']) ?></td>
                        <td><?= sanitize($r['contact'] ?? '-') ?></td>
                        <td><small><?= sanitize($r['student_name'] ?? '-') ?> <?= $r['roll_no'] ? '(' . sanitize($r['roll_no']) . ')' : '' ?></small></td>
                        <td><small><?= sanitize($r['purpose'] ?? '-') ?></small></td>
                        <td><small><?= date('d M Y h:i A', strtotime($r['check_in'])) ?></small></td>
                        <td>
                            <?php
                            $badge = match($r['status']) { 'Pending' => 'warning', 'Checked In' => 'info', 'Approved' => 'success', 'Rejected' => 'danger', 'Checked Out' => 'secondary' };
                            ?>
                            <span class="badge bg-<?= $badge ?>"><?= $r['status'] ?></span>
                        </td>
                        <td class="text-center">
                            <?php if (in_array($r['status'], ['Approved', 'Checked In'])): ?>
                            <a href="?action=qr&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-dark" title="QR Code"><i class="bi bi-qr-code"></i></a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <?php if ($r['status'] === 'Pending'): ?>
                                <form method="post" action="?action=checkout&id=<?= $r['id'] ?>" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <button type="submit" name="action" value="approve" class="btn btn-sm btn-success" title="Approve"><i class="bi bi-check-lg"></i></button>
                                    <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger" title="Reject" onclick="return confirm('Reject this visitor?')"><i class="bi bi-x-lg"></i></button>
                                </form>
                                <?php endif; ?>
                                <?php if (in_array($r['status'], ['Approved','Checked In'])): ?>
                                <form method="post" action="?action=checkout&id=<?= $r['id'] ?>" class="d-inline tr-checkout-form">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <button type="submit" name="action" value="check_out" class="btn btn-sm btn-secondary" title="Check Out" onclick="return confirm('Check out this visitor?')"><i class="bi bi-box-arrow-left"></i></button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="9" class="text-center text-muted">No visitors found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= paginationLinks($page, $pages) ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/warden-footer.php'; ?>
