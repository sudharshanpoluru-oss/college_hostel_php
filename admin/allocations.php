<?php
$title = 'Room Allocations';
require_once __DIR__ . '/../includes/admin-header.php';

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();

    $postAction = $_POST['action'] ?? $action;

    if ($postAction === 'allocate') {
        $student_id = (int)$_POST['student_id'];
        $room_id    = (int)$_POST['room_id'];
        $bed_no     = sanitize($_POST['bed_no']);

        $check = db()->prepare("SELECT capacity,occupancy,status FROM rooms WHERE id=?");
        $check->execute([$room_id]);
        $room = $check->fetch();

        if (!$room || ($room['occupancy'] >= $room['capacity'])) {
            setAlert('danger', 'Room is full or not found.');
        } else {
            try {
                db()->beginTransaction();
                $stmt = db()->prepare("INSERT INTO room_allocations (student_id,room_id,bed_no,allocation_date,status) VALUES (?,?,?,NOW(),'Active')");
                $stmt->execute([$student_id, $room_id, $bed_no]);

                db()->prepare("UPDATE rooms SET occupancy=occupancy+1 WHERE id=?")->execute([$room_id]);
                if (($room['occupancy'] + 1) >= $room['capacity']) {
                    db()->prepare("UPDATE rooms SET status='Full' WHERE id=?")->execute([$room_id]);
                }
                db()->commit();
                setAlert('success', 'Room allocated.');
                redirect(BASE_URL . '/admin/allocations.php');
            } catch (Exception $e) {
                db()->rollBack();
                setAlert('danger', 'Error: ' . $e->getMessage());
            }
        }
    }

    if ($postAction === 'transfer' && $id) {
        $new_room_id = (int)$_POST['new_room_id'];

        $alloc = db()->prepare("SELECT * FROM room_allocations WHERE id=? AND status='Active'");
        $alloc->execute([$id]);
        $a = $alloc->fetch();
        if (!$a) { setAlert('danger','Allocation not found.'); redirect(BASE_URL.'/admin/allocations.php'); }

        $check = db()->prepare("SELECT capacity,occupancy FROM rooms WHERE id=?");
        $check->execute([$new_room_id]);
        $r = $check->fetch();
        if (!$r || $r['occupancy'] >= $r['capacity']) {
            setAlert('danger','Target room is full.');
        } else {
            try {
                db()->beginTransaction();
                // update allocation to new room
                db()->prepare("UPDATE room_allocations SET room_id=?, allocation_date=NOW() WHERE id=?")->execute([$new_room_id, $id]);
                db()->prepare("UPDATE rooms SET occupancy=occupancy-1 WHERE id=?")->execute([$a['room_id']]);
                $oldRoom = db()->prepare("SELECT occupancy,capacity FROM rooms WHERE id=?");
                $oldRoom->execute([$a['room_id']]);
                $or = $oldRoom->fetch();
                if ($or['occupancy'] < $or['capacity']) {
                    db()->prepare("UPDATE rooms SET status='Available' WHERE id=?")->execute([$a['room_id']]);
                }
                db()->prepare("UPDATE rooms SET occupancy=occupancy+1 WHERE id=?")->execute([$new_room_id]);
                $newRoom = db()->prepare("SELECT occupancy,capacity FROM rooms WHERE id=?");
                $newRoom->execute([$new_room_id]);
                $nr = $newRoom->fetch();
                if ($nr['occupancy'] >= $nr['capacity']) {
                    db()->prepare("UPDATE rooms SET status='Full' WHERE id=?")->execute([$new_room_id]);
                }
                db()->commit();
                setAlert('success', 'Room transferred.');
                redirect(BASE_URL . '/admin/allocations.php');
            } catch (Exception $e) {
                db()->rollBack();
                setAlert('danger', 'Error: ' . $e->getMessage());
            }
        }
    }
}

if ($action === 'vacate' && $id) {
    $alloc = db()->prepare("SELECT * FROM room_allocations WHERE id=? AND status='Active'");
    $alloc->execute([$id]);
    $a = $alloc->fetch();
    if ($a) {
        try {
            db()->beginTransaction();
            db()->prepare("UPDATE room_allocations SET checkout_date=NOW(), status='CheckedOut' WHERE id=?")->execute([$id]);
            db()->prepare("UPDATE rooms SET occupancy=occupancy-1 WHERE id=?")->execute([$a['room_id']]);
            $rm = db()->prepare("SELECT occupancy,capacity FROM rooms WHERE id=?");
            $rm->execute([$a['room_id']]);
            $roomData = $rm->fetch();
            if ($roomData['occupancy'] < $roomData['capacity']) {
                db()->prepare("UPDATE rooms SET status='Available' WHERE id=?")->execute([$a['room_id']]);
            }
            db()->commit();
            setAlert('success', 'Room vacated.');
        } catch (Exception $e) {
            db()->rollBack();
            setAlert('danger', 'Error: ' . $e->getMessage());
        }
    }
    redirect(BASE_URL . '/admin/allocations.php');
}

$page    = (int)($_GET['p'] ?? 1);
$perPage = 10;

$total = db()->query("SELECT COUNT(*) FROM room_allocations")->fetchColumn();
$offset = ($page - 1) * $perPage;
$pages  = paginate($page, $perPage, $total);

$stmt = db()->prepare("SELECT ra.*, s.name AS student_name, s.roll_no, r.room_no FROM room_allocations ra JOIN students s ON s.id=ra.student_id JOIN rooms r ON r.id=ra.room_id ORDER BY ra.id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute();
$allocations = $stmt->fetchAll();
?>
<div class="container-fluid">
    <?php if ($action === 'list'): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0">Allocations</h4>
        <a href="?action=allocate" class="btn btn-primary btn-sm">+ New Allocation</a>
    </div>
    <table class="table table-striped table-bordered">
        <thead><tr><th>Student</th><th>Roll No</th><th>Room</th><th>Bed No</th><th>Allocation Date</th><th>Checkout Date</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($allocations as $a): ?>
            <tr>
                <td><?= sanitize($a['student_name']) ?></td>
                <td><?= sanitize($a['roll_no']) ?></td>
                <td><?= sanitize($a['room_no']) ?></td>
                <td><?= $a['bed_no'] ?></td>
                <td><?= $a['allocation_date'] ?></td>
                <td><?= $a['checkout_date'] ?? '-' ?></td>
                <td><span class="badge bg-<?= $a['status']=='Active'?'success':'secondary' ?>"><?= $a['status'] ?></span></td>
                <td>
                    <?php if ($a['status'] === 'Active'): ?>
                    <a href="?action=transfer&id=<?= $a['id'] ?>" class="btn btn-sm btn-info">Transfer</a>
                    <a href="?action=vacate&id=<?= $a['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Vacate this allocation?')">Vacate</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?= paginationLinks($page, $pages) ?>

    <?php elseif ($action === 'allocate'): ?>
    <h4 class="mb-3">New Allocation</h4>
    <form method="post" action="?action=allocate" class="row g-3"><?= csrfField() ?>
        <input type="hidden" name="action" value="allocate">
        <div class="col-md-4">
            <label>Student</label>
            <select name="student_id" class="form-select" required>
                <option value="">-- Select Student --</option>
                <?php
                $stmt = db()->query("SELECT id,name,roll_no FROM students WHERE status='Active' AND id NOT IN (SELECT student_id FROM room_allocations WHERE status='Active')");
                while ($s = $stmt->fetch()):
                ?>
                <option value="<?= $s['id'] ?>"><?= sanitize($s['name']) ?> (<?= sanitize($s['roll_no']) ?>)</option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label>Room</label>
            <select name="room_id" class="form-select" required>
                <option value="">-- Select Room --</option>
                <?php
                $stmt = db()->query("SELECT * FROM rooms WHERE status!='Full'");
                while ($r = $stmt->fetch()):
                ?>
                <option value="<?= $r['id'] ?>"><?= sanitize($r['room_no']) ?> (<?= $r['room_type'] ?> - <?= $r['capacity']-$r['occupancy'] ?> free)</option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-4"><label>Bed No</label><input type="text" name="bed_no" class="form-control" placeholder="e.g. A1"></div>
        <div class="col-12"><button class="btn btn-primary">Allocate</button> <a href="<?= BASE_URL ?>/admin/allocations.php" class="btn btn-secondary">Cancel</a></div>
    </form>

    <?php elseif ($action === 'transfer' && $id):
    $alloc = db()->prepare("SELECT ra.*, s.name AS student_name, s.roll_no FROM room_allocations ra JOIN students s ON s.id=ra.student_id WHERE ra.id=? AND ra.status='Active'");
    $alloc->execute([$id]);
    $a = $alloc->fetch();
    if (!$a) { setAlert('danger','Allocation not found'); redirect(BASE_URL.'/admin/allocations.php'); }
    ?>
    <h4 class="mb-3">Transfer - <?= sanitize($a['student_name']) ?> (<?= sanitize($a['roll_no']) ?>)</h4>
    <form method="post" action="?action=transfer&id=<?= $id ?>" class="row g-3"><?= csrfField() ?>
        <input type="hidden" name="action" value="transfer">
        <div class="col-md-4">
            <label>New Room</label>
            <select name="new_room_id" class="form-select" required>
                <option value="">-- Select Room --</option>
                <?php
                $stmt = db()->query("SELECT * FROM rooms WHERE status!='Full'");
                while ($r = $stmt->fetch()):
                ?>
                <option value="<?= $r['id'] ?>"><?= sanitize($r['room_no']) ?> (<?= $r['room_type'] ?> - <?= $r['capacity']-$r['occupancy'] ?> free)</option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-12"><button class="btn btn-info">Transfer</button> <a href="<?= BASE_URL ?>/admin/allocations.php" class="btn btn-secondary">Cancel</a></div>
    </form>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
