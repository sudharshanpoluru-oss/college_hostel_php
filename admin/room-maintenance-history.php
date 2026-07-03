<?php
$title = 'Room Maintenance History';
require_once __DIR__ . '/../includes/admin-header.php';

$room_id = (int)($_GET['room_id'] ?? 0);
$action  = $_GET['action'] ?? 'list';
$id      = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $post_action = $_POST['post_action'] ?? '';
    $rid         = (int)($_POST['room_id'] ?? 0);

    if ($post_action === 'add' && $rid) {
        $problem      = sanitize($_POST['problem']);
        $solution     = sanitize($_POST['solution']);
        $category     = sanitize($_POST['category']);
        $completed_by = sanitize($_POST['completed_by']);
        $remarks      = sanitize($_POST['remarks']);
        $cost         = (float)($_POST['cost'] ?? 0);
        $repair_date  = sanitize($_POST['repair_date']);
        try {
            $stmt = db()->prepare("INSERT INTO room_maintenance_history (room_id, problem, solution, category, completed_by, remarks, cost, repair_date, created_by) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$rid, $problem, $solution, $category, $completed_by, $remarks, $cost, $repair_date, $_SESSION['user_id']]);
            auditLog('Add Maintenance Record', 'Room Maintenance', "Added repair record for room #$rid");
            setAlert('success', 'Repair record added.');
        } catch (PDOException $e) {
            setAlert('danger', 'Error: ' . $e->getMessage());
        }
        redirect(BASE_URL . '/admin/room-maintenance-history.php?room_id=' . $rid);
    }

    if ($post_action === 'edit' && $id) {
        $problem      = sanitize($_POST['problem']);
        $solution     = sanitize($_POST['solution']);
        $category     = sanitize($_POST['category']);
        $completed_by = sanitize($_POST['completed_by']);
        $remarks      = sanitize($_POST['remarks']);
        $cost         = (float)($_POST['cost'] ?? 0);
        $repair_date  = sanitize($_POST['repair_date']);
        $rid          = (int)($_POST['room_id']);
        try {
            $stmt = db()->prepare("UPDATE room_maintenance_history SET problem=?, solution=?, category=?, completed_by=?, remarks=?, cost=?, repair_date=? WHERE id=?");
            $stmt->execute([$problem, $solution, $category, $completed_by, $remarks, $cost, $repair_date, $id]);
            auditLog('Edit Maintenance Record', 'Room Maintenance', "Edited repair record #$id for room #$rid");
            setAlert('success', 'Repair record updated.');
        } catch (PDOException $e) {
            setAlert('danger', 'Error: ' . $e->getMessage());
        }
        redirect(BASE_URL . '/admin/room-maintenance-history.php?room_id=' . $rid);
    }

    if ($post_action === 'delete' && $id) {
        $stmt = db()->prepare("SELECT room_id FROM room_maintenance_history WHERE id=?");
        $stmt->execute([$id]);
        $rec = $stmt->fetch();
        if ($rec) {
            db()->prepare("DELETE FROM room_maintenance_history WHERE id=?")->execute([$id]);
            auditLog('Delete Maintenance Record', 'Room Maintenance', "Deleted repair record #$id for room #{$rec['room_id']}");
            setAlert('success', 'Repair record deleted.');
            redirect(BASE_URL . '/admin/room-maintenance-history.php?room_id=' . $rec['room_id']);
        }
        setAlert('danger', 'Record not found.');
        redirect(BASE_URL . '/admin/room-maintenance-history.php');
    }
}

// -- Mode 1: Room Selector --
if (!$room_id) {
    $search = sanitize($_GET['search'] ?? '');
    $page   = (int)($_GET['p'] ?? 1);
    $perPage = 15;

    if ($search) {
        $total = db()->prepare("SELECT COUNT(*) FROM rooms WHERE room_no LIKE ?");
        $total->execute(["%$search%"]);
    } else {
        $total = db()->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
    }
    $pages  = paginate($page, $perPage, $total);
    $offset = $pages['offset'];

    $sql = "SELECT r.*,
                   (SELECT COUNT(*) FROM room_maintenance_history WHERE room_id=r.id) AS repair_count,
                   (SELECT MAX(repair_date) FROM room_maintenance_history WHERE room_id=r.id) AS last_repair
            FROM rooms r";
    if ($search) {
        $sql .= " WHERE r.room_no LIKE ?";
    }
    $sql .= " ORDER BY r.room_no LIMIT $perPage OFFSET $offset";

    $stmt = db()->prepare($sql);
    if ($search) {
        $stmt->execute(["%$search%"]);
    } else {
        $stmt->execute();
    }
    $rooms = $stmt->fetchAll();
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0">Room Selector</h4>
    </div>
    <form method="get" class="row g-2 mb-3">
        <div class="col-auto flex-grow-1">
            <input type="text" name="search" class="form-control" placeholder="Search by room number..." value="<?= sanitize($search) ?>">
        </div>
        <div class="col-auto">
            <button class="btn btn-primary">Search</button>
            <?php if ($search): ?>
                <a href="<?= BASE_URL ?>/admin/room-maintenance-history.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </form>
    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Room No</th>
                    <th>Floor</th>
                    <th>Type</th>
                    <th>Capacity</th>
                    <th>Status</th>
                    <th>Total Repairs</th>
                    <th>Last Repair</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($rooms) > 0): foreach ($rooms as $r): ?>
                <tr>
                    <td><?= sanitize($r['room_no']) ?></td>
                    <td><?= sanitize($r['floor']) ?></td>
                    <td><?= $r['room_type'] ?></td>
                    <td><?= $r['capacity'] ?></td>
                    <td><span class="badge bg-<?= $r['status']=='Available'?'success':($r['status']=='Full'?'warning':'secondary') ?>"><?= $r['status'] ?></span></td>
                    <td><span class="badge bg-info"><?= (int)$r['repair_count'] ?></span></td>
                    <td><?= $r['last_repair'] ?? 'N/A' ?></td>
                    <td>
                        <a href="?room_id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-clock-history"></i> View History</a>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="8" class="text-center text-muted">No rooms found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= paginationLinks($page, $pages) ?>
</div>
<?php
require_once __DIR__ . '/../includes/admin-footer.php';
exit;
}

// -- Mode 2: History for a specific room --
$stmt = db()->prepare("SELECT * FROM rooms WHERE id=?");
$stmt->execute([$room_id]);
$room = $stmt->fetch();
if (!$room) { setAlert('danger', 'Room not found.'); redirect(BASE_URL . '/admin/room-maintenance-history.php'); }

$edit_record = null;
if ($action === 'edit' && $id) {
    $stmt = db()->prepare("SELECT * FROM room_maintenance_history WHERE id=? AND room_id=?");
    $stmt->execute([$id, $room_id]);
    $edit_record = $stmt->fetch();
}

$page    = (int)($_GET['p'] ?? 1);
$perPage = 10;
$total   = db()->prepare("SELECT COUNT(*) FROM room_maintenance_history WHERE room_id=?");
$total->execute([$room_id]);
$totalRecords = $total->fetchColumn();
$pages   = paginate($page, $perPage, $totalRecords);
$offset  = $pages['offset'];

$stmt = db()->prepare("SELECT * FROM room_maintenance_history WHERE room_id=? ORDER BY repair_date DESC, id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute([$room_id]);
$records = $stmt->fetchAll();

$stmt = db()->prepare("SELECT COALESCE(SUM(cost),0) FROM room_maintenance_history WHERE room_id=?");
$stmt->execute([$room_id]);
$total_cost = $stmt->fetchColumn();
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0">Maintenance History: Room <?= sanitize($room['room_no']) ?></h4>
        <a href="<?= BASE_URL ?>/admin/room-maintenance-history.php" class="btn btn-sm btn-secondary"><i class="bi bi-arrow-left"></i> All Rooms</a>
    </div>

    <div class="card mb-3">
        <div class="card-body py-2 d-flex flex-wrap gap-3 align-items-center">
            <strong><?= sanitize($room['room_no']) ?></strong> &middot;
            Floor: <?= sanitize($room['floor']) ?> &middot;
            Type: <?= $room['room_type'] ?> &middot;
            Capacity: <?= $room['capacity'] ?>
            <span class="ms-auto badge bg-<?= $room['status']=='Available'?'success':($room['status']=='Full'?'warning':'secondary') ?>"><?= $room['status'] ?></span>
            <span class="badge bg-dark">Total Repair Cost: ₹<?= number_format($total_cost, 2) ?></span>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-semibold"><?= $edit_record ? 'Edit Repair Record' : 'Add Repair Record' ?></div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <?= csrfField() ?>
                <input type="hidden" name="post_action" value="<?= $edit_record ? 'edit' : 'add' ?>">
                <input type="hidden" name="room_id" value="<?= $room_id ?>">
                <?php if ($edit_record): ?>
                <input type="hidden" name="id" value="<?= $edit_record['id'] ?>">
                <?php endif; ?>
                <div class="col-md-6">
                    <label class="form-label">Problem <span class="text-danger">*</span></label>
                    <input type="text" name="problem" class="form-control" value="<?= $edit_record ? sanitize($edit_record['problem']) : '' ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <option value="">-- Select --</option>
                        <?php foreach (['Electrical','Plumbing','Furniture','Internet','Cleaning','Painting','Water Supply','Carpentry','Other'] as $cat): ?>
                        <option value="<?= $cat ?>" <?= $edit_record && $edit_record['category']===$cat?'selected':'' ?>><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Repair Date <span class="text-danger">*</span></label>
                    <input type="date" name="repair_date" class="form-control" value="<?= $edit_record ? $edit_record['repair_date'] : date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Solution</label>
                    <textarea name="solution" class="form-control" rows="2"><?= $edit_record ? sanitize($edit_record['solution']) : '' ?></textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Completed By</label>
                    <input type="text" name="completed_by" class="form-control" value="<?= $edit_record ? sanitize($edit_record['completed_by']) : '' ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cost (₹)</label>
                    <input type="number" step="0.01" min="0" name="cost" class="form-control" value="<?= $edit_record ? $edit_record['cost'] : '' ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" rows="2"><?= $edit_record ? sanitize($edit_record['remarks']) : '' ?></textarea>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary"><?= $edit_record ? 'Update' : 'Add' ?> Record</button>
                    <?php if ($edit_record): ?>
                    <a href="?room_id=<?= $room_id ?>" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Problem</th>
                    <th>Solution</th>
                    <th>Category</th>
                    <th>Completed By</th>
                    <th>Cost</th>
                    <th>Remarks</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($records) > 0): foreach ($records as $rec): ?>
                <tr>
                    <td><?= $rec['repair_date'] ?></td>
                    <td><?= sanitize($rec['problem']) ?></td>
                    <td><?= sanitize($rec['solution']) ?></td>
                    <td><?= $rec['category'] ? '<span class="badge bg-secondary">' . sanitize($rec['category']) . '</span>' : '—' ?></td>
                    <td><?= sanitize($rec['completed_by']) ?: '—' ?></td>
                    <td>₹<?= number_format($rec['cost'], 2) ?></td>
                    <td><?= sanitize($rec['remarks']) ?: '—' ?></td>
                    <td>
                        <a href="?room_id=<?= $room_id ?>&action=edit&id=<?= $rec['id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this repair record?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="post_action" value="delete">
                            <input type="hidden" name="id" value="<?= $rec['id'] ?>">
                            <input type="hidden" name="room_id" value="<?= $room_id ?>">
                            <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="8" class="text-center text-muted">No maintenance records for this room.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= paginationLinks($page, $pages) ?>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
