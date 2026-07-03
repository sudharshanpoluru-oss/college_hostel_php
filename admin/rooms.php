<?php
$title = 'Room Management';
require_once __DIR__ . '/../includes/admin-header.php';

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        $room_no   = sanitize($_POST['room_no']);
        $floor     = sanitize($_POST['floor']);
        $room_type = sanitize($_POST['room_type']);
        $capacity  = (int)$_POST['capacity'];
        $fee       = (float)$_POST['fee_per_month'];
        $desc      = sanitize($_POST['description']);
        $status    = sanitize($_POST['status']);

        try {
            if ($action === 'add') {
                $stmt = db()->prepare("INSERT INTO rooms (room_no,floor,room_type,capacity,fee_per_month,description,status) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$room_no,$floor,$room_type,$capacity,$fee,$desc,$status]);
                setAlert('success', 'Room added.');
            } else {
                $stmt = db()->prepare("UPDATE rooms SET room_no=?,floor=?,room_type=?,capacity=?,fee_per_month=?,description=?,status=? WHERE id=?");
                $stmt->execute([$room_no,$floor,$room_type,$capacity,$fee,$desc,$status,$id]);
                setAlert('success', 'Room updated.');
            }
            redirect(BASE_URL . '/admin/rooms.php');
        } catch (PDOException $e) {
            setAlert('danger', 'Error: ' . $e->getMessage());
        }
    }
}

if ($action === 'delete' && $id) {
    $check = db()->prepare("SELECT occupancy FROM rooms WHERE id=?");
    $check->execute([$id]);
    $r = $check->fetch();
    if ($r && $r['occupancy'] > 0) {
        setAlert('danger', 'Cannot delete room with active occupants.');
    } else {
        db()->prepare("DELETE FROM rooms WHERE id=?")->execute([$id]);
        setAlert('success', 'Room deleted.');
    }
    redirect(BASE_URL . '/admin/rooms.php');
}

if ($action === 'view' && $id) {
    $stmt = db()->prepare("SELECT * FROM rooms WHERE id=?");
    $stmt->execute([$id]);
    $room = $stmt->fetch();
    if (!$room) { setAlert('danger','Room not found'); redirect(BASE_URL.'/admin/rooms.php'); }

    $stmt = db()->prepare("SELECT s.name, s.roll_no, s.email, s.phone, s.course, s.year, ra.bed_no, ra.allocation_date FROM room_allocations ra JOIN students s ON s.id=ra.student_id WHERE ra.room_id=? AND ra.status='Active' ORDER BY s.name");
    $stmt->execute([$id]);
    $students = $stmt->fetchAll();
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0">Room <?= sanitize($room['room_no']) ?> - Students</h4>
        <a href="<?= BASE_URL ?>/admin/rooms.php" class="btn btn-sm btn-secondary">Back to Rooms</a>
    </div>
    <div class="card mb-3">
        <div class="card-body py-2">
            <strong><?= $room['room_type'] ?></strong> &middot; Capacity: <?= $room['capacity'] ?> &middot; Occupied: <?= $room['occupancy'] ?> &middot; Fee: ₹<?= number_format($room['fee_per_month']) ?>/month
            &middot; <span class="badge bg-<?= $room['status']=='Available'?'success':($room['status']=='Full'?'warning':'secondary') ?>"><?= $room['status'] ?></span>
        </div>
    </div>
    <table class="table table-bordered table-striped">
        <thead><tr><th>#</th><th>Name</th><th>Roll No</th><th>Email</th><th>Phone</th><th>Course</th><th>Year</th><th>Bed No</th><th>Allocated</th></tr></thead>
        <tbody>
            <?php if (count($students) > 0): $i=0; foreach ($students as $s): $i++; ?>
            <tr>
                <td><?= $i ?></td>
                <td><?= sanitize($s['name']) ?></td>
                <td><?= sanitize($s['roll_no']) ?></td>
                <td><?= sanitize($s['email']) ?></td>
                <td><?= sanitize($s['phone']) ?></td>
                <td><?= sanitize($s['course']) ?></td>
                <td><?= sanitize($s['year']) ?></td>
                <td><?= $s['bed_no'] ?? 'N/A' ?></td>
                <td><?= $s['allocation_date'] ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="9" class="text-center text-muted">No students in this room.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
require_once __DIR__ . '/../includes/admin-footer.php';
exit;
}

$page    = (int)($_GET['p'] ?? 1);
$perPage = 10;

$total = db()->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$offset = ($page - 1) * $perPage;
$pages  = paginate($page, $perPage, $total);

$stmt = db()->prepare("SELECT *, (capacity-occupancy) AS available_beds FROM rooms ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute();
$rooms = $stmt->fetchAll();
?>
<div class="container-fluid">
    <?php if ($action === 'list'): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0">Rooms</h4>
        <a href="?action=add" class="btn btn-primary btn-sm">+ Add Room</a>
    </div>
    <table class="table table-striped table-bordered">
        <thead><tr><th>Room No</th><th>Floor</th><th>Type</th><th>Capacity</th><th>Occupancy</th><th>Available</th><th>Fee/Month</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($rooms as $r): ?>
            <tr>
                <td><?= sanitize($r['room_no']) ?></td>
                <td><?= sanitize($r['floor']) ?></td>
                <td><?= $r['room_type'] ?></td>
                <td><?= $r['capacity'] ?></td>
                <td><?= $r['occupancy'] ?></td>
                <td><?= $r['available_beds'] ?></td>
                <td>₹<?= number_format($r['fee_per_month']) ?></td>
                <td><span class="badge bg-<?= $r['status']=='Available'?'success':($r['status']=='Full'?'warning':'secondary') ?>"><?= $r['status'] ?></span></td>
                <td>
                    <a href="?action=view&id=<?= $r['id'] ?>" class="btn btn-sm btn-info">Students</a>
                    <a href="?action=edit&id=<?= $r['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                    <a href="?action=delete&id=<?= $r['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?= paginationLinks($page, $pages) ?>

    <?php elseif ($action === 'add' || ($action === 'edit' && $id)):
        $room = ['room_no'=>'','floor'=>'','room_type'=>'Single','capacity'=>1,'fee_per_month'=>0,'description'=>'','status'=>'Available'];
        if ($action === 'edit') {
            $stmt = db()->prepare("SELECT * FROM rooms WHERE id=?");
            $stmt->execute([$id]);
            $room = $stmt->fetch();
            if (!$room) { setAlert('danger','Not found'); redirect(BASE_URL.'/admin/rooms.php'); }
        }
    ?>
    <h4 class="mb-3"><?= $action==='add'?'Add':'Edit' ?> Room</h4>
    <form method="post" action="?action=add" class="row g-3"><?= csrfField() ?>
        <div class="col-md-4"><label>Room No</label><input type="text" name="room_no" class="form-control" value="<?= sanitize($room['room_no']) ?>" required></div>
        <div class="col-md-4"><label>Floor</label><input type="text" name="floor" class="form-control" value="<?= sanitize($room['floor']) ?>"></div>
        <div class="col-md-4"><label>Room Type</label><select name="room_type" class="form-select"><option value="Single" <?= $room['room_type']=='Single'?'selected':'' ?>>Single</option><option value="Double" <?= $room['room_type']=='Double'?'selected':'' ?>>Double</option><option value="Triple" <?= $room['room_type']=='Triple'?'selected':'' ?>>Triple</option><option value="Dormitory" <?= $room['room_type']=='Dormitory'?'selected':'' ?>>Dormitory</option></select></div>
        <div class="col-md-4"><label>Capacity</label><input type="number" name="capacity" class="form-control" value="<?= $room['capacity'] ?>" required></div>
        <div class="col-md-4"><label>Fee per Month (₹)</label><input type="number" step="0.01" name="fee_per_month" class="form-control" value="<?= $room['fee_per_month'] ?>"></div>
        <div class="col-md-4"><label>Status</label><select name="status" class="form-select"><option value="Available" <?= $room['status']=='Available'?'selected':'' ?>>Available</option><option value="Full" <?= $room['status']=='Full'?'selected':'' ?>>Full</option><option value="Maintenance" <?= $room['status']=='Maintenance'?'selected':'' ?>>Maintenance</option></select></div>
        <div class="col-12"><label>Description</label><textarea name="description" class="form-control"><?= sanitize($room['description']) ?></textarea></div>
        <div class="col-12"><button class="btn btn-primary"><?= $action==='add'?'Add':'Update' ?> Room</button> <a href="<?= BASE_URL ?>/admin/rooms.php" class="btn btn-secondary">Cancel</a></div>
    </form>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
