<?php
$title = 'Student Management';
require_once __DIR__ . '/../includes/admin-header.php';

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        $name        = sanitize($_POST['name']);
        $roll_no     = sanitize($_POST['roll_no']);
        $email       = sanitize($_POST['email']);
        $phone       = sanitize($_POST['phone']);
        $address     = sanitize($_POST['address']);
        $gender      = sanitize($_POST['gender']);
        $course      = sanitize($_POST['course']);
        $year        = sanitize($_POST['year']);
        $guardian_name  = sanitize($_POST['guardian_name']);
        $guardian_phone = sanitize($_POST['guardian_phone']);
        $admission_date  = $_POST['admission_date'];
        $join_date       = $_POST['join_date'];
        $status       = sanitize($_POST['status']);

        $hostel_type = $gender === 'Female' ? 'girls' : 'boys';

        $photo = '';
        if (!empty($_FILES['photo']['name'])) {
            $ext  = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $photo = uniqid('stu_') . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/../uploads/' . $photo);
        }

        try {
            if ($action === 'add') {
                $hashed = password_hash('student123', PASSWORD_DEFAULT);
                $stmt = db()->prepare("INSERT INTO users (username,email,password,role,status,approved) VALUES (?,?,?,'student',1,1)");
                $stmt->execute([$roll_no, $email, $hashed]);
                $userId = db()->lastInsertId();

                $stmt = db()->prepare("INSERT INTO students (user_id,name,roll_no,email,phone,address,gender,course,year,guardian_name,guardian_phone,admission_date,join_date,status,photo,hostel_type) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$userId,$name,$roll_no,$email,$phone,$address,$gender,$course,$year,$guardian_name,$guardian_phone,$admission_date,$join_date,$status,$photo,$hostel_type]);

                setAlert('success', 'Student added successfully.');
            } else {
                $existing = db()->prepare("SELECT photo FROM students WHERE id=?");
                $existing->execute([$id]);
                $old = $existing->fetch();
                if (!$photo) $photo = $old['photo'];

                $stmt = db()->prepare("UPDATE students SET name=?,roll_no=?,email=?,phone=?,address=?,gender=?,course=?,year=?,guardian_name=?,guardian_phone=?,admission_date=?,join_date=?,status=?,photo=?,hostel_type=? WHERE id=?");
                $stmt->execute([$name,$roll_no,$email,$phone,$address,$gender,$course,$year,$guardian_name,$guardian_phone,$admission_date,$join_date,$status,$photo,$hostel_type,$id]);
                setAlert('success', 'Student updated successfully.');
            }
            redirect(BASE_URL . '/admin/students.php');
        } catch (PDOException $e) {
            setAlert('danger', 'Error: ' . $e->getMessage());
        }
    }
}

if ($action === 'approve' && $id) {
    $stmt = db()->prepare("SELECT user_id FROM students WHERE id=?");
    $stmt->execute([$id]);
    $s = $stmt->fetch();
    if ($s) {
        db()->prepare("UPDATE users SET approved = 1 WHERE id=?")->execute([$s['user_id']]);
        setAlert('success', 'Student approved.');
    }
    redirect(BASE_URL . '/admin/students.php');
}

if ($action === 'reject' && $id) {
    $stmt = db()->prepare("SELECT user_id FROM students WHERE id=?");
    $stmt->execute([$id]);
    $s = $stmt->fetch();
    if ($s && $s['user_id']) {
        db()->prepare("DELETE FROM users WHERE id=?")->execute([$s['user_id']]);
    }
    db()->prepare("DELETE FROM students WHERE id=?")->execute([$id]);
    setAlert('success', 'Registration rejected and deleted.');
    redirect(BASE_URL . '/admin/students.php');
}

if ($action === 'delete' && $id) {
    $check = db()->prepare("SELECT COUNT(*) FROM room_allocations WHERE student_id=? AND status='Active'");
    $check->execute([$id]);
    if ($check->fetchColumn() > 0) {
        setAlert('danger', 'Cannot delete student with active room allocation.');
    } else {
        $stmt = db()->prepare("SELECT user_id FROM students WHERE id=?");
        $stmt->execute([$id]);
        $s = $stmt->fetch();
        if ($s && $s['user_id']) {
            db()->prepare("DELETE FROM users WHERE id=?")->execute([$s['user_id']]);
        }
        db()->prepare("DELETE FROM students WHERE id=?")->execute([$id]);
        setAlert('success', 'Student deleted.');
    }
    redirect(BASE_URL . '/admin/students.php');
}

$page    = (int)($_GET['p'] ?? 1);
$perPage = 10;
$search  = sanitize($_GET['search'] ?? '');
$filter_course = sanitize($_GET['course'] ?? '');

$courses = db()->query("SELECT DISTINCT course FROM students WHERE course != '' ORDER BY course")->fetchAll(PDO::FETCH_COLUMN);

if ($action === 'list' || !$action):
    $where = '';
    $params = [];
    $conditions = [];
    if ($search) {
        $conditions[] = "(s.name LIKE ? OR s.roll_no LIKE ? OR s.phone LIKE ?)";
        $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
    }
    if ($filter_course) {
        $conditions[] = "s.course = ?";
        $params[] = $filter_course;
    }
    $conditions[] = "u.approved = 1";
    $where = "WHERE " . implode(' AND ', $conditions);
    $total = db()->prepare("SELECT COUNT(*) FROM students s JOIN users u ON u.id = s.user_id $where");
    $total->execute($params);
    $totalRows = $total->fetchColumn();
    $offset = ($page - 1) * $perPage;
    $pages  = paginate($page, $perPage, $totalRows);

    $stmt = db()->prepare("SELECT s.*, r.room_no FROM students s JOIN users u ON u.id = s.user_id LEFT JOIN room_allocations ra ON ra.student_id=s.id AND ra.status='Active' LEFT JOIN rooms r ON r.id=ra.room_id $where ORDER BY s.id DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $students = $stmt->fetchAll();
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0">Students</h4>
        <a href="?action=add" class="btn btn-primary btn-sm">+ Add Student</a>
    </div>
    <form method="get" class="row g-2 mb-3">
        <div class="col-auto"><input type="text" name="search" class="form-control form-control-sm" placeholder="Search name, roll, phone" value="<?= sanitize($search) ?>"></div>
        <div class="col-auto">
            <select name="course" class="form-select form-select-sm">
                <option value="">All Branches</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= sanitize($c) ?>" <?= $filter_course === $c ? 'selected' : '' ?>><?= sanitize($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">Search</button></div>
    </form>

    <?php
    $pendingStmt = db()->prepare("SELECT s.id, s.name, s.roll_no, s.email, s.phone, s.course, s.gender, u.id AS user_id FROM students s JOIN users u ON u.id = s.user_id WHERE u.approved = 0 AND u.role = 'student' ORDER BY s.id DESC");
    $pendingStmt->execute();
    $pending = $pendingStmt->fetchAll();
    ?>

    <?php if ($pending): ?>
    <div class="card border-warning mb-4">
        <div class="card-header bg-warning text-white fw-bold"><i class="bi bi-hourglass-split"></i> Pending Approvals (<?= count($pending) ?>)</div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead><tr><th>Name</th><th>Roll No</th><th>Email</th><th>Phone</th><th>Course</th><th>Gender</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($pending as $p): ?>
                    <tr>
                        <td><?= sanitize($p['name']) ?></td>
                        <td><?= sanitize($p['roll_no']) ?></td>
                        <td><?= sanitize($p['email']) ?></td>
                        <td><?= sanitize($p['phone']) ?></td>
                        <td><?= sanitize($p['course']) ?></td>
                        <td><?= $p['gender'] ?></td>
                        <td>
                            <form method="post" action="?action=approve&id=<?= $p['id'] ?>" style="display:inline"><?= csrfField() ?>
                                <button class="btn btn-success btn-sm" onclick="return confirm('Approve <?= sanitize($p['name']) ?>?')">Approve</button>
                            </form>
                            <form method="post" action="?action=reject&id=<?= $p['id'] ?>" style="display:inline"><?= csrfField() ?>
                                <button class="btn btn-danger btn-sm" onclick="return confirm('Reject <?= sanitize($p['name']) ?>? This will delete the registration.')">Reject</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <table class="table table-striped table-bordered">
        <thead><tr><th>Name</th><th>Roll No</th><th>Email</th><th>Phone</th><th>Course</th><th>Room</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($students as $s): ?>
            <tr>
                <td><?= sanitize($s['name']) ?></td>
                <td><?= sanitize($s['roll_no']) ?></td>
                <td><?= sanitize($s['email']) ?></td>
                <td><?= sanitize($s['phone']) ?></td>
                <td><?= sanitize($s['course']) ?></td>
                <td><?= sanitize($s['room_no']??'N/A') ?></td>
                <td><span class="badge bg-<?= $s['status']=='Active'?'success':'danger' ?>"><?= $s['status'] ?></span></td>
                <td>
                    <a href="?action=view&id=<?= $s['id'] ?>" class="btn btn-sm btn-info">View</a>
                    <a href="?action=edit&id=<?= $s['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                    <?php if (empty($s['room_no'])): ?>
                    <a href="?action=view&id=<?= $s['id'] ?>#assign" class="btn btn-sm btn-success">+ Room</a>
                    <?php endif; ?>
                    <a href="?action=delete&id=<?= $s['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this student?')">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?= paginationLinks($page, $pages) ?>
</div>
<?php elseif ($action === 'add' || ($action === 'edit' && $id)):
    $student = ['name'=>'','roll_no'=>'','email'=>'','phone'=>'','address'=>'','gender'=>'','course'=>'','year'=>'','guardian_name'=>'','guardian_phone'=>'','admission_date'=>'','join_date'=>'','status'=>'Active','photo'=>''];
    if ($action === 'edit') {
        $stmt = db()->prepare("SELECT * FROM students WHERE id=?");
        $stmt->execute([$id]);
        $student = $stmt->fetch();
        if (!$student) { setAlert('danger','Student not found'); redirect(BASE_URL.'/admin/students.php'); }
    }
?>
<div class="container-fluid">
    <h4 class="mb-3"><?= $action==='add'?'Add':'Edit' ?> Student</h4>
    <form method="post" enctype="multipart/form-data" class="row g-3"><?= csrfField() ?>
        <div class="col-md-4"><label>Name</label><input type="text" name="name" class="form-control" value="<?= sanitize($student['name']) ?>" required></div>
        <div class="col-md-4"><label>Roll No</label><input type="text" name="roll_no" class="form-control" value="<?= sanitize($student['roll_no']) ?>" required></div>
        <div class="col-md-4"><label>Email</label><input type="email" name="email" class="form-control" value="<?= sanitize($student['email']) ?>"></div>
        <div class="col-md-4"><label>Phone</label><input type="text" name="phone" class="form-control" value="<?= sanitize($student['phone']) ?>"></div>
        <div class="col-md-4"><label>Gender</label><select name="gender" class="form-select"><option value="Male" <?= $student['gender']=='Male'?'selected':'' ?>>Male</option><option value="Female" <?= $student['gender']=='Female'?'selected':'' ?>>Female</option><option value="Other" <?= $student['gender']=='Other'?'selected':'' ?>>Other</option></select></div>
        <div class="col-md-4"><label>Course</label><input type="text" name="course" class="form-control" value="<?= sanitize($student['course']) ?>"></div>
        <div class="col-md-3"><label>Year</label><input type="text" name="year" class="form-control" value="<?= sanitize($student['year']) ?>"></div>
        <div class="col-md-3"><label>Admission Date</label><input type="date" name="admission_date" class="form-control" value="<?= $student['admission_date'] ?>"></div>
        <div class="col-md-3"><label>Join Date</label><input type="date" name="join_date" class="form-control" value="<?= $student['join_date'] ?>"></div>
        <div class="col-md-3"><label>Status</label><select name="status" class="form-select"><option value="Active" <?= $student['status']=='Active'?'selected':'' ?>>Active</option><option value="Inactive" <?= $student['status']=='Inactive'?'selected':'' ?>>Inactive</option></select></div>
        <div class="col-md-6"><label>Address</label><textarea name="address" class="form-control"><?= sanitize($student['address']) ?></textarea></div>
        <div class="col-md-3"><label>Guardian Name</label><input type="text" name="guardian_name" class="form-control" value="<?= sanitize($student['guardian_name']) ?>"></div>
        <div class="col-md-3"><label>Guardian Phone</label><input type="text" name="guardian_phone" class="form-control" value="<?= sanitize($student['guardian_phone']) ?>"></div>
        <div class="col-md-4"><label>Photo</label><input type="file" name="photo" class="form-control"><?php if($student['photo']): ?><br><img src="<?= BASE_URL ?>/uploads/<?= $student['photo'] ?>" height="60"><?php endif; ?></div>
        <div class="col-12"><button class="btn btn-primary"><?= $action==='add'?'Add':'Update' ?> Student</button> <a href="<?= BASE_URL ?>/admin/students.php" class="btn btn-secondary">Cancel</a></div>
    </form>
</div>
<?php elseif ($action === 'view' && $id):
    $stmt = db()->prepare("SELECT s.*, r.room_no, r.room_type, r.id AS room_id, ra.bed_no, ra.allocation_date AS room_allocation_date, ra.id AS allocation_id FROM students s LEFT JOIN room_allocations ra ON ra.student_id=s.id AND ra.status='Active' LEFT JOIN rooms r ON r.id=ra.room_id WHERE s.id=?");
    $stmt->execute([$id]);
    $s = $stmt->fetch();
    if (!$s) { setAlert('danger','Student not found'); redirect(BASE_URL.'/admin/students.php'); }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $post_action = $_POST['_action'] ?? '';

        if ($post_action === 'assign_room') {
            requireCSRF();
            $room_id = (int)$_POST['room_id'];
            $bed_no  = trim($_POST['bed_no']);

            $check = db()->prepare("SELECT capacity,occupancy,status FROM rooms WHERE id=?");
            $check->execute([$room_id]);
            $room = $check->fetch();

            if (!$room || $room['occupancy'] >= $room['capacity']) {
                setAlert('danger', 'Room is full or not found.');
            } else {
                try {
                    db()->beginTransaction();
                    $stmt = db()->prepare("INSERT INTO room_allocations (student_id,room_id,bed_no,allocation_date,status) VALUES (?,?,?,NOW(),'Active')");
                    $stmt->execute([$id, $room_id, $bed_no]);
                    db()->prepare("UPDATE rooms SET occupancy=occupancy+1 WHERE id=?")->execute([$room_id]);
                    if ($room['occupancy'] + 1 >= $room['capacity']) {
                        db()->prepare("UPDATE rooms SET status='Full' WHERE id=?")->execute([$room_id]);
                    }
                    db()->commit();
                    setAlert('success', 'Room assigned successfully.');
                    redirect(BASE_URL . '/admin/students.php?action=view&id=' . $id);
                } catch (Exception $e) {
                    db()->rollBack();
                    setAlert('danger', 'Error: ' . $e->getMessage());
                }
            }
            // Refresh student data after assign attempt
            $stmt = db()->prepare("SELECT s.*, r.room_no, r.room_type, r.id AS room_id, ra.bed_no, ra.allocation_date AS room_allocation_date, ra.id AS allocation_id FROM students s LEFT JOIN room_allocations ra ON ra.student_id=s.id AND ra.status='Active' LEFT JOIN rooms r ON r.id=ra.room_id WHERE s.id=?");
            $stmt->execute([$id]);
            $s = $stmt->fetch();
        }

        if ($post_action === 'vacate_room') {
            requireCSRF();
            $allocation_id = (int)$_POST['allocation_id'];
            $alloc = db()->prepare("SELECT * FROM room_allocations WHERE id=? AND status='Active'");
            $alloc->execute([$allocation_id]);
            $a = $alloc->fetch();
            if ($a) {
                try {
                    db()->beginTransaction();
                    db()->prepare("UPDATE room_allocations SET checkout_date=NOW(), status='CheckedOut' WHERE id=?")->execute([$allocation_id]);
                    db()->prepare("UPDATE rooms SET occupancy=occupancy-1 WHERE id=?")->execute([$a['room_id']]);
                    $rm = db()->prepare("SELECT occupancy,capacity FROM rooms WHERE id=?");
                    $rm->execute([$a['room_id']]);
                    $roomData = $rm->fetch();
                    if ($roomData['occupancy'] < $roomData['capacity']) {
                        db()->prepare("UPDATE rooms SET status='Available' WHERE id=?")->execute([$a['room_id']]);
                    }
                    db()->commit();
                    setAlert('success', 'Room vacated.');
                    redirect(BASE_URL . '/admin/students.php?action=view&id=' . $id);
                } catch (Exception $e) {
                    db()->rollBack();
                    setAlert('danger', 'Error: ' . $e->getMessage());
                }
            }
            // Refresh student data after vacate attempt
            $stmt = db()->prepare("SELECT s.*, r.room_no, r.room_type, r.id AS room_id, ra.bed_no, ra.allocation_date AS room_allocation_date, ra.id AS allocation_id FROM students s LEFT JOIN room_allocations ra ON ra.student_id=s.id AND ra.status='Active' LEFT JOIN rooms r ON r.id=ra.room_id WHERE s.id=?");
            $stmt->execute([$id]);
            $s = $stmt->fetch();
        }
    }
    echo displayAlert();
?>
<div class="container-fluid">
    <h4 class="mb-3">Student Profile</h4>
    <div class="card">
        <div class="card-body">
            <div class="row">
                <?php if($s['photo']): ?><div class="col-md-2 mb-3"><img src="<?= BASE_URL ?>/uploads/<?= $s['photo'] ?>" class="img-fluid rounded"></div><?php endif; ?>
                <div class="col-md-10">
                    <table class="table table-bordered">
                        <tr><th>Name</th><td><?= sanitize($s['name']) ?></td><th>Roll No</th><td><?= sanitize($s['roll_no']) ?></td></tr>
                        <tr><th>Email</th><td><?= sanitize($s['email']) ?></td><th>Phone</th><td><?= sanitize($s['phone']) ?></td></tr>
                        <tr><th>Gender</th><td><?= $s['gender'] ?></td><th>Course</th><td><?= sanitize($s['course']) ?></td></tr>
                        <tr><th>Year</th><td><?= sanitize($s['year']) ?></td><th>Guardian</th><td><?= sanitize($s['guardian_name']) ?> (<?= $s['guardian_phone'] ?>)</td></tr>
                        <tr><th>Address</th><td><?= sanitize($s['address']) ?></td><th>Status</th><td><span class="badge bg-<?= $s['status']=='Active'?'success':'danger' ?>"><?= $s['status'] ?></span></td></tr>
                        <tr><th>Room</th><td><?= sanitize($s['room_no']??'N/A') ?></td><th>Bed No</th><td><?= $s['bed_no']??'N/A' ?></td></tr>
                        <tr><th>Admission Date</th><td><?= $s['admission_date'] ?></td><th>Join Date</th><td><?= $s['join_date'] ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php if ($s['room_no']): ?>
    <div class="card mt-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Room Assignment</strong>
            <form method="POST" action="?action=view&id=<?= $id ?>" style="display:inline" onsubmit="return confirm('Vacate this room?')"><?= csrfField() ?>
                <input type="hidden" name="_action" value="vacate_room">
                <input type="hidden" name="allocation_id" value="<?= $s['allocation_id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-house-x"></i> Vacate Room</button>
            </form>
        </div>
        <div class="card-body">
            <p><strong>Room:</strong> <?= sanitize($s['room_no']) ?> (<?= $s['room_type'] ?>)<br>
            <strong>Bed No:</strong> <?= sanitize($s['bed_no'] ?? 'N/A') ?><br>
            <strong>Allocated:</strong> <?= $s['room_allocation_date'] ?></p>
        </div>
    </div>
    <?php else: ?>
    <div class="card mt-3">
        <div class="card-header"><strong>Assign Room</strong></div>
        <div class="card-body">
            <form method="POST" action="?action=view&id=<?= $id ?>" class="row g-3"><?= csrfField() ?>
                <input type="hidden" name="_action" value="assign_room">
                <div class="col-md-5">
                    <label class="form-label">Select Room</label>
                    <select name="room_id" class="form-select" required>
                        <option value="">-- Choose Room --</option>
                        <?php
                        $stmt = db()->query("SELECT * FROM rooms WHERE status!='Full' ORDER BY room_no");
                        while ($r = $stmt->fetch()):
                        ?>
                        <option value="<?= $r['id'] ?>"><?= sanitize($r['room_no']) ?> - <?= $r['room_type'] ?> (<?= $r['capacity']-$r['occupancy'] ?> free)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Bed No</label>
                    <input type="text" name="bed_no" class="form-control" placeholder="e.g. A1">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-house-add"></i> Assign Room</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <a href="<?= BASE_URL ?>/admin/students.php" class="btn btn-secondary mt-3">Back to List</a>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
