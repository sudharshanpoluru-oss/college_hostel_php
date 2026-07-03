<?php
$title = 'Vacated Students';
require_once __DIR__ . '/../includes/admin-header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($action === 'view' && $id):
    $stmt = db()->prepare("SELECT * FROM vacated_students WHERE id = ?");
    $stmt->execute([$id]);
    $vs = $stmt->fetch();
    if (!$vs) { setAlert('danger', 'Not found'); redirect(BASE_URL . '/admin/vacated-students.php'); }
    $related = json_decode($vs['related_data'], true);
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0">Vacated Student Details</h4>
        <a href="<?= BASE_URL ?>/admin/vacated-students.php" class="btn btn-sm btn-secondary">Back</a>
    </div>
    <div class="card mb-3">
        <div class="card-header"><strong>Personal Info</strong></div>
        <div class="card-body">
            <table class="table table-bordered mb-0">
                <tr><th style="width:150px">Name</th><td><?= sanitize($vs['name']) ?></td><th style="width:150px">Roll No</th><td><?= sanitize($vs['roll_no']) ?></td></tr>
                <tr><th>Email</th><td><?= sanitize($vs['email']) ?></td><th>Phone</th><td><?= sanitize($vs['phone']) ?></td></tr>
                <tr><th>Gender</th><td><?= $vs['gender'] ?></td><th>Course</th><td><?= sanitize($vs['course']) ?></td></tr>
                <tr><th>Year</th><td><?= sanitize($vs['year']) ?></td><th>Last Room</th><td><?= sanitize($vs['last_room_no'] ?? 'N/A') ?></td></tr>
                <tr><th>Guardian</th><td><?= sanitize($vs['guardian_name']) ?> (<?= $vs['guardian_phone'] ?>)</td><th>Address</th><td><?= sanitize($vs['address']) ?></td></tr>
                <tr><th>Admission Date</th><td><?= $vs['admission_date'] ?></td><th>Join Date</th><td><?= $vs['join_date'] ?></td></tr>
                <tr><th>Vacate Reason</th><td colspan="3"><?= nl2br(htmlspecialchars($vs['vacate_reason'])) ?></td></tr>
                <tr><th>Admin Remark</th><td colspan="3"><?= htmlspecialchars($vs['admin_remark'] ?? '--') ?></td></tr>
                <tr><th>Vacated At</th><td colspan="3"><?= $vs['vacated_at'] ?></td></tr>
            </table>
        </div>
    </div>

    <?php if ($related): foreach (['fees', 'attendance', 'complaints', 'leaves', 'room_change_requests'] as $section): ?>
        <?php if (!empty($related[$section])): ?>
        <div class="card mb-3">
            <div class="card-header"><strong><?= ucfirst(str_replace('_', ' ', $section)) ?></strong> <span class="badge bg-secondary"><?= count($related[$section]) ?></span></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead><tr>
                            <?php foreach (array_keys($related[$section][0]) as $col): ?>
                                <th><?= htmlspecialchars($col) ?></th>
                            <?php endforeach; ?>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($related[$section] as $row): ?>
                            <tr>
                                <?php foreach ($row as $val): ?>
                                    <td><?= htmlspecialchars($val ?? '') ?></td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endforeach; endif; ?>
</div>
<?php else:
    $page = (int)($_GET['p'] ?? 1);
    $perPage = 15;
    $search = sanitize($_GET['search'] ?? '');

    $where = '';
    $params = [];
    if ($search) {
        $where = "WHERE name LIKE ? OR roll_no LIKE ? OR course LIKE ? OR last_room_no LIKE ? OR vacate_reason LIKE ?";
        $params = array_fill(0, 5, "%$search%");
    }

    $total = db()->prepare("SELECT COUNT(*) FROM vacated_students $where");
    $total->execute($params);
    $totalRows = $total->fetchColumn();
    $offset = ($page - 1) * $perPage;
    $pages = paginate($page, $perPage, $totalRows);

    $stmt = db()->prepare("SELECT * FROM vacated_students $where ORDER BY vacated_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $records = $stmt->fetchAll();
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0">Vacated Students Archive</h4>
        <form method="get" class="d-flex">
            <input type="text" name="search" class="form-control form-control-sm me-2" placeholder="Search name, roll, room..." value="<?= sanitize($search) ?>">
            <button class="btn btn-sm btn-outline-secondary">Search</button>
        </form>
    </div>
    <table class="table table-bordered table-striped">
        <thead><tr><th>Name</th><th>Roll No</th><th>Course</th><th>Last Room</th><th>Reason</th><th>Vacated At</th><th>Action</th></tr></thead>
        <tbody>
            <?php foreach ($records as $r): ?>
            <tr>
                <td><?= sanitize($r['name']) ?></td>
                <td><?= sanitize($r['roll_no']) ?></td>
                <td><?= sanitize($r['course']) ?></td>
                <td><?= sanitize($r['last_room_no'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars(implode(' ', array_slice(explode(' ', $r['vacate_reason']), 0, 8))) . (str_word_count($r['vacate_reason']) > 8 ? '...' : '') ?></td>
                <td><?= date('d M Y', strtotime($r['vacated_at'])) ?></td>
                <td><a href="?action=view&id=<?= $r['id'] ?>" class="btn btn-sm btn-info">View</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$records): ?><tr><td colspan="7" class="text-center text-muted">No vacated student records.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?= paginationLinks($page, $pages) ?>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
