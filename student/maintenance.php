<?php
$title = 'Maintenance Request';
require_once __DIR__ . '/../includes/student-header.php';

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

$studentStmt = db()->prepare("SELECT * FROM students WHERE user_id = ?");
$studentStmt->execute([$_SESSION['user_id']]);
$student = $studentStmt->fetch();

$roomStmt = db()->prepare("SELECT r.* FROM rooms r JOIN room_allocations ra ON ra.room_id=r.id WHERE ra.student_id=? AND ra.status='Active' LIMIT 1");
$roomStmt->execute([$student['id']]);
$room = $roomStmt->fetch();

$categories = ['Electrical','Plumbing','Furniture','Internet','Cleaning','Painting','Water Supply','Carpentry','Other'];

$statusBadgeMap = [
    'Pending'    => 'warning',
    'Assigned'   => 'primary',
    'In Progress'=> 'info',
    'Resolved'   => 'success',
    'Closed'     => 'secondary',
    'Rejected'   => 'dark',
];
$priorityBadgeMap = [
    'Low'       => 'secondary',
    'Medium'    => 'info',
    'High'      => 'warning',
    'Emergency' => 'danger',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();

    $category    = sanitize($_POST['category'] ?? '');
    $priority    = sanitize($_POST['priority'] ?? 'Medium');
    $description = sanitize($_POST['description'] ?? '');
    $photos = [];

    if (!in_array($category, $categories)) {
        setAlert('danger', 'Invalid category selected.');
        redirect('maintenance.php');
    }
    if (!in_array($priority, ['Low','Medium','High','Emergency'])) {
        $priority = 'Medium';
    }
    if (empty($description)) {
        setAlert('danger', 'Description is required.');
        redirect('maintenance.php');
    }

    if (isset($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
        $allowedExts = ['jpg','jpeg','png','gif','webp'];
        foreach ($_FILES['photos']['error'] as $key => $error) {
            if ($error === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['photos']['name'][$key], PATHINFO_EXTENSION));
                if (in_array($ext, $allowedExts)) {
                    $filename = uniqid('maint_') . '.' . $ext;
                    move_uploaded_file($_FILES['photos']['tmp_name'][$key], __DIR__ . '/../uploads/' . $filename);
                    $photos[] = $filename;
                }
            }
        }
    } elseif (isset($_FILES['photos']) && $_FILES['photos']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['photos']['name'], PATHINFO_EXTENSION));
        $filename = uniqid('maint_') . '.' . $ext;
        move_uploaded_file($_FILES['photos']['tmp_name'], __DIR__ . '/../uploads/' . $filename);
        $photos[] = $filename;
    }

    $photosStr = $photos ? implode(',', $photos) : null;

    $stmt = db()->prepare("INSERT INTO maintenance_requests (student_id, room_id, category, priority, description, photos, status, created_by) VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?)");
    $stmt->execute([$student['id'], $room ? $room['id'] : null, $category, $priority, $description, $photosStr, $_SESSION['user_id']]);

    $requestId = db()->lastInsertId();
    auditLog('Create Maintenance Request', 'Maintenance', "Student created maintenance request #$requestId");

    $wardenStmt = db()->prepare("SELECT user_id FROM wardens WHERE status=1");
    $wardenStmt->execute();
    $wardens = $wardenStmt->fetchAll();
    foreach ($wardens as $w) {
        addNotification($w['user_id'], 'New Maintenance Request', "New maintenance request #$requestId created by " . $student['name'], 'info', BASE_URL . '/warden/maintenance.php?action=view&id=' . $requestId);
    }

    $adminStmt = db()->prepare("SELECT id FROM users WHERE role='admin' AND status=1");
    $adminStmt->execute();
    $admins = $adminStmt->fetchAll();
    foreach ($admins as $a) {
        addNotification($a['id'], 'New Maintenance Request', "New maintenance request #$requestId created by " . $student['name'], 'info', BASE_URL . '/admin/maintenance.php?action=view&id=' . $requestId);
    }

    setAlert('success', 'Maintenance request submitted successfully.');
    redirect('maintenance.php');
}

$myStmt = db()->prepare("
    SELECT mr.*, r.room_no, a.username AS assigned_name
    FROM maintenance_requests mr
    LEFT JOIN rooms r ON r.id=mr.room_id
    LEFT JOIN users a ON a.id=mr.assigned_to
    WHERE mr.student_id=?
    ORDER BY mr.created_at DESC
");
$myStmt->execute([$student['id']]);
$requests = $myStmt->fetchAll();
?>

<div class="container-fluid">
    <h3 class="mb-4">Maintenance Request</h3>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header"><strong>Submit New Request</strong></div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrfField() ?>
                        <div class="mb-3">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat ?>"><?= $cat ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Priority <span class="text-danger">*</span></label>
                            <select name="priority" class="form-select" required>
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                                <option value="Emergency">Emergency</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="4" required placeholder="Describe the issue in detail..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Photos (optional, max 5)</label>
                            <input type="file" name="photos[]" class="form-control" multiple accept="image/jpeg,image/png,image/gif,image/webp">
                            <div class="form-text">Upload up to 5 photos showing the issue.</div>
                        </div>
                        <?php if ($room): ?>
                        <div class="mb-3">
                            <label class="form-label">Room</label>
                            <input type="text" class="form-control" value="<?= escapeOutput($room['room_no']) ?>" disabled>
                        </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary w-100">Submit Request</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <?php if ($action === 'view' && $id):
            $detailStmt = db()->prepare("
                SELECT mr.*, r.room_no, a.username AS assigned_name
                FROM maintenance_requests mr
                LEFT JOIN rooms r ON r.id=mr.room_id
                LEFT JOIN users a ON a.id=mr.assigned_to
                WHERE mr.id=? AND mr.student_id=?
            ");
            $detailStmt->execute([$id, $student['id']]);
            $d = $detailStmt->fetch();
            if (!$d): ?>
                <div class="alert alert-danger">Request not found.</div>
            <?php else: ?>
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Request #<?= $d['id'] ?></strong>
                    <span class="badge bg-<?= $statusBadgeMap[$d['status']] ?? 'secondary' ?> fs-6"><?= $d['status'] ?></span>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <span class="badge bg-<?= $priorityBadgeMap[$d['priority']] ?? 'primary' ?>"><?= $d['priority'] ?></span>
                        <span class="badge bg-info ms-1"><?= escapeOutput($d['category']) ?></span>
                        <?php if ($d['room_no']): ?><span class="badge bg-secondary ms-1">Room: <?= escapeOutput($d['room_no']) ?></span><?php endif; ?>
                    </div>
                    <p><strong>Description:</strong></p>
                    <p class="text-muted"><?= nl2br(escapeOutput($d['description'])) ?></p>
                    <?php if ($d['photos']): ?>
                    <p><strong>Photos:</strong></p>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <?php foreach (explode(',', $d['photos']) as $photo): $photo = trim($photo); if (!$photo) continue; ?>
                        <a href="<?= BASE_URL ?>/uploads/<?= $photo ?>" target="_blank">
                            <img src="<?= BASE_URL ?>/uploads/<?= $photo ?>" class="img-thumbnail" style="max-height:120px" alt="Photo">
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($d['assigned_name']): ?>
                    <p><strong>Assigned To:</strong> <?= escapeOutput($d['assigned_name']) ?></p>
                    <?php endif; ?>
                    <?php if ($d['assigned_date']): ?>
                    <p><strong>Assigned Date:</strong> <?= date('d M Y, h:i A', strtotime($d['assigned_date'])) ?></p>
                    <?php endif; ?>
                    <?php if ($d['completed_date']): ?>
                    <p><strong>Completed Date:</strong> <?= date('d M Y, h:i A', strtotime($d['completed_date'])) ?></p>
                    <?php endif; ?>
                    <?php if ($d['completion_remarks']): ?>
                    <hr><p><strong>Completion Remarks:</strong><br><?= nl2br(escapeOutput($d['completion_remarks'])) ?></p>
                    <?php endif; ?>
                    <hr>
                    <div class="text-muted small">
                        Submitted: <?= date('d M Y, h:i A', strtotime($d['created_at'])) ?>
                        <?php if ($d['updated_at'] && $d['updated_at'] !== $d['created_at']): ?>
                        | Updated: <?= date('d M Y, h:i A', strtotime($d['updated_at'])) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="maintenance.php" class="btn btn-sm btn-outline-secondary">&larr; Back to List</a>
                </div>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="card">
                <div class="card-header"><strong>My Requests</strong></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>#</th>
                                    <th>Category</th>
                                    <th>Priority</th>
                                    <th>Room</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Assigned To</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($requests): $i = 1; foreach ($requests as $r): ?>
                                <tr class="<?= $r['priority']==='Emergency'?'table-danger':'' ?>">
                                    <td><?= $i++ ?></td>
                                    <td><?= escapeOutput($r['category']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $priorityBadgeMap[$r['priority']] ?? 'secondary' ?>"><?= $r['priority'] ?></span>
                                    </td>
                                    <td><?= escapeOutput($r['room_no'] ?? '—') ?></td>
                                    <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $statusBadgeMap[$r['status']] ?? 'secondary' ?>"><?= $r['status'] ?></span>
                                    </td>
                                    <td><?= escapeOutput($r['assigned_name'] ?? '—') ?></td>
                                    <td><a href="?action=view&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-info">View</a></td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr><td colspan="8" class="text-center text-muted">No maintenance requests submitted.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/student-footer.php'; ?>
