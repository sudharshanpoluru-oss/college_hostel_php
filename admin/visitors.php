<?php
$title = 'Visitor Management';
require_once __DIR__ . '/../includes/admin-header.php';

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

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    $visitor_name = sanitize($_POST['visitor_name'] ?? '');
    $contact      = sanitize($_POST['contact'] ?? '');
    $purpose      = sanitize($_POST['purpose'] ?? '');
    $student_id   = !empty($_POST['student_id']) ? (int)$_POST['student_id'] : null;
    $remarks      = sanitize($_POST['remarks'] ?? '');

    if (!$visitor_name) {
        setAlert('danger', 'Visitor name is required.');
        redirect(BASE_URL . '/admin/visitors.php?action=add');
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
    redirect(BASE_URL . '/admin/visitors.php');
}

if ($action === 'check_out' && $id) {
    $stmt = db()->prepare("UPDATE visitor_logs SET check_out=NOW() WHERE id=? AND check_out IS NULL");
    $stmt->execute([$id]);
    setAlert('success', 'Visitor checked out.');
    redirect(BASE_URL . '/admin/visitors.php');
}

if ($action === 'approve' && $id) {
    $stmt = db()->prepare("UPDATE visitor_logs SET status='Approved', warden_approved=1 WHERE id=?");
    $stmt->execute([$id]);
    setAlert('success', 'Visitor approved.');
    redirect(BASE_URL . '/admin/visitors.php');
}

if ($action === 'qr' && $id) {
    $stmt = db()->prepare("SELECT v.*, s.name AS student_name, s.roll_no FROM visitor_logs v LEFT JOIN students s ON s.id=v.student_id WHERE v.id=?");
    $stmt->execute([$id]);
    $visitor = $stmt->fetch();
    if (!$visitor) { setAlert('danger', 'Visitor not found.'); redirect(BASE_URL . '/admin/visitors.php'); }
    ?>
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="m-0">Visitor QR Code</h5>
                <a href="<?= BASE_URL ?>/admin/visitors.php" class="btn btn-sm btn-secondary">&larr; Back</a>
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
    require_once __DIR__ . '/../includes/admin-footer.php';
    exit;
}

$page    = (int)($_GET['p'] ?? 1);
$perPage = 15;

$search = sanitize($_GET['search'] ?? '');
$where = '';
$params = [];
if ($search) {
    $where = "WHERE (v.visitor_name LIKE ? OR v.contact LIKE ?)";
    $params = ["%$search%", "%$search%"];
}

$total = db()->prepare("SELECT COUNT(*) FROM visitor_logs v $where");
$total->execute($params);
$totalRows = $total->fetchColumn();
$offset = ($page - 1) * $perPage;
$pages  = paginate($page, $perPage, $totalRows);

$stmt = db()->prepare("SELECT v.*, s.name AS student_name, s.roll_no FROM visitor_logs v LEFT JOIN students s ON s.id=v.student_id $where ORDER BY v.check_in DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$visitors = $stmt->fetchAll();

$students = db()->query("SELECT id, name, roll_no FROM students WHERE status='Active' ORDER BY name")->fetchAll();
?>
<div class="container-fluid">
    <?php if ($action === 'add'): ?>
    <h4 class="mb-3">Log New Visitor</h4>
    <form method="post" action="?action=add" enctype="multipart/form-data" class="row g-3"><?= csrfField() ?>
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
            <button class="btn btn-primary">Log Visitor</button>
            <a href="<?= BASE_URL ?>/admin/visitors.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>

    <?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0">Visitors</h4>
        <a href="?action=add" class="btn btn-primary btn-sm">+ Log Visitor</a>
    </div>
    <form method="get" class="row g-2 mb-3">
        <div class="col-auto"><input type="text" name="search" class="form-control form-control-sm" placeholder="Search name or contact" value="<?= sanitize($search) ?>"></div>
        <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">Search</button></div>
    </form>
    <table class="table table-striped table-bordered">
        <thead><tr><th>Visitor Name</th><th>Contact</th><th>Purpose</th><th>Check In</th><th>Check Out</th><th>QR</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($visitors as $v): ?>
            <tr class="<?= $v['check_out'] ? '' : 'table-success' ?>">
                <td><?= sanitize($v['visitor_name']) ?></td>
                <td><?= sanitize($v['contact']) ?></td>
                <td><?= sanitize($v['purpose']) ?></td>
                <td><?= $v['check_in'] ?></td>
                <td>
                    <?php if ($v['check_out']): ?>
                        <?= $v['check_out'] ?>
                    <?php else: ?>
                        <a href="?action=check_out&id=<?= $v['id'] ?>" class="btn btn-sm btn-warning" onclick="return confirm('Check out this visitor?')">Check Out</a>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if (in_array($v['status'], ['Approved', 'Checked In'])): ?>
                    <a href="?action=qr&id=<?= $v['id'] ?>" class="btn btn-sm btn-outline-dark" title="Generate QR"><i class="bi bi-qr-code"></i></a>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="?action=view&id=<?= $v['id'] ?>" class="btn btn-sm btn-info">View</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$visitors): ?><tr><td colspan="7" class="text-center text-muted">No visitors found.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?= paginationLinks($page, $pages) ?>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
