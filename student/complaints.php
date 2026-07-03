<?php
require_once __DIR__ . '/../includes/session.php';

if (!isLoggedIn() || !isStudent()) {
    redirect(BASE_URL . '/auth/login.php');
}

$studentStmt = db()->prepare("SELECT * FROM students WHERE user_id = ?");
$studentStmt->execute([$_SESSION['user_id']]);
$student = $studentStmt->fetch();

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $postAction = $_POST['action'] ?? 'add';

    if ($postAction === 'add') {
        $comp_title = sanitize($_POST['title']);
        $category = sanitize($_POST['category']);
        $priority = sanitize($_POST['priority'] ?? 'Medium');
        $description = sanitize($_POST['description']);
        $attachment = '';

        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
            $attachment = uniqid('comp_') . '.' . $ext;
            move_uploaded_file($_FILES['attachment']['tmp_name'], __DIR__ . '/../uploads/' . $attachment);
        }

        $stmt = db()->prepare("INSERT INTO complaints (student_id, title, category, priority, description, attachment, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
        $stmt->execute([$student['id'], $comp_title, $category, $priority, $description, $attachment]);
        setAlert('success', 'Complaint submitted successfully.');
        redirect('complaints.php');
    }

    if ($postAction === 'add_followup') {
        $complaintId = (int)$_POST['complaint_id'];
        $message = sanitize($_POST['message'] ?? '');

        $checkStmt = db()->prepare("SELECT id FROM complaints WHERE id=? AND student_id=?");
        $checkStmt->execute([$complaintId, $student['id']]);
        if ($checkStmt->fetch() && $message !== '') {
            logComplaintAction($complaintId, 'Follow-up', $message);
            setAlert('success', 'Follow-up added.');
        } else {
            setAlert('danger', 'Invalid complaint or empty message.');
        }
        redirect('complaints.php?action=view&id='.$complaintId);
    }

    redirect('complaints.php');
}

if ($action === 'view' && $id) {
    $stmt = db()->prepare("SELECT c.*, s.name AS student_name, s.roll_no, s.room_no FROM complaints c JOIN students s ON s.id=c.student_id WHERE c.id=? AND c.student_id=?");
    $stmt->execute([$id, $student['id']]);
    $c = $stmt->fetch();
    if (!$c) { setAlert('danger', 'Complaint not found.'); redirect('complaints.php'); }

    $logsStmt = db()->prepare("SELECT cl.*, u.username FROM complaint_logs cl LEFT JOIN users u ON u.id=cl.performed_by WHERE cl.complaint_id=? ORDER BY cl.created_at ASC");
    $logsStmt->execute([$id]);
    $logs = $logsStmt->fetchAll();

    $title = 'Complaint #'.$id;
    require_once __DIR__ . '/../includes/student-header.php';
    ?>
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="complaints.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to List</a>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong><i class="bi bi-exclamation-triangle me-1"></i> <?= escapeOutput($c['title']) ?></strong>
                        <span class="badge bg-<?= match($c['status']){'Pending'=>'warning','New'=>'primary','Under Inspection'=>'info','In Progress'=>'warning','Resolved by Warden'=>'success','Escalated to Admin'=>'danger','Under Admin Review'=>'dark','Closed'=>'secondary',default=>'secondary'} ?> fs-6"><?= escapeOutput($c['status']) ?></span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <span class="badge bg-<?= match($c['priority']){'Emergency'=>'danger','High'=>'warning','Medium'=>'primary','Low'=>'secondary',default=>'primary'} ?> fs-6"><?= escapeOutput($c['priority'] ?? 'Medium') ?></span>
                            <span class="badge bg-info ms-1 fs-6"><?= escapeOutput($c['category'] ?? 'General') ?></span>
                        </div>
                        <p class="mb-1"><strong>Description:</strong></p>
                        <p class="text-muted"><?= nl2br(escapeOutput($c['description'])) ?></p>
                        <?php if ($c['attachment']): ?>
                        <p><strong>Attachment:</strong> <a href="<?= BASE_URL ?>/uploads/<?= $c['attachment'] ?>" target="_blank" class="btn btn-sm btn-outline-info"><i class="bi bi-paperclip"></i> View Attachment</a></p>
                        <?php endif; ?>
                        <?php if ($c['resolution_notes']): ?>
                        <hr><p><strong>Resolution Notes:</strong><br><?= nl2br(escapeOutput($c['resolution_notes'])) ?></p>
                        <?php endif; ?>
                        <?php if ($c['escalation_reason']): ?>
                        <hr><p><strong>Escalation Reason:</strong><br><?= nl2br(escapeOutput($c['escalation_reason'])) ?></p>
                        <?php endif; ?>
                        <hr>
                        <div class="row text-muted small">
                            <div class="col-md-4">Created: <?= date('d M Y, h:i A', strtotime($c['created_at'])) ?></div>
                            <?php if ($c['resolved_at']): ?><div class="col-md-4">Resolved: <?= date('d M Y, h:i A', strtotime($c['resolved_at'])) ?></div><?php endif; ?>
                            <?php if ($c['escalated_at']): ?><div class="col-md-4">Escalated: <?= date('d M Y, h:i A', strtotime($c['escalated_at'])) ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><i class="bi bi-clock-history me-1"></i> Timeline</div>
                    <div class="card-body p-0">
                        <?php if (count($logs) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($logs as $log): ?>
                            <div class="list-group-item py-3 px-3 d-flex align-items-start gap-3">
                                <div class="timeline-dot mt-1 <?= match($log['action']){'Resolved by Warden'=>'bg-success','Escalated to Admin'=>'bg-danger','Under Inspection'=>'bg-info','In Progress'=>'bg-warning','Follow-up'=>'bg-secondary',default=>'bg-secondary'} ?>"></div>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-medium small"><?= escapeOutput($log['action']) ?></div>
                                    <?php if (!empty($log['remarks'])): ?>
                                    <div class="text-muted small"><?= escapeOutput($log['remarks']) ?></div>
                                    <?php endif; ?>
                                    <div class="text-muted" style="font-size:0.7rem">
                                        <?= date('d M Y, h:i A', strtotime($log['created_at'])) ?>
                                        by <?= escapeOutput($log['username'] ?? 'System') ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4 text-muted small">No activity yet.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header"><i class="bi bi-chat-dots me-1"></i> Add Follow-up</div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="add_followup">
                            <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
                            <?= csrfField() ?>
                            <div class="mb-2">
                                <textarea name="message" class="form-control" rows="3" placeholder="Type your follow-up message..." required></textarea>
                            </div>
                            <button class="btn btn-primary btn-sm"><i class="bi bi-send"></i> Send Follow-up</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-person me-1"></i> Your Info</div>
                    <div class="card-body">
                        <p class="mb-1"><strong>Name:</strong> <?= escapeOutput($c['student_name']) ?></p>
                        <p class="mb-1"><strong>Roll No:</strong> <?= escapeOutput($c['roll_no']) ?></p>
                        <p class="mb-0"><strong>Room:</strong> <?= escapeOutput($c['room_no'] ?? 'N/A') ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
    .timeline-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; margin-top: 4px; }
    .timeline-dot.bg-success { background-color: #10b981; }
    .timeline-dot.bg-danger { background-color: #ef4444; }
    .timeline-dot.bg-primary { background-color: #3b82f6; }
    .timeline-dot.bg-info { background-color: #06b6d4; }
    .timeline-dot.bg-warning { background-color: #f59e0b; }
    .timeline-dot.bg-secondary { background-color: #6b7280; }
    </style>
    <?php
    require_once __DIR__ . '/../includes/student-footer.php';
    exit;
}

$compStmt = db()->prepare("SELECT * FROM complaints WHERE student_id = ? ORDER BY id DESC");
$compStmt->execute([$student['id']]);
$complaints = $compStmt->fetchAll();

$categories = ['Plumbing', 'Electrical', 'Cleaning', 'Noise', 'Other'];
$title = 'Complaints';
require_once __DIR__ . '/../includes/student-header.php';
?>

<div class="container-fluid">
    <h3 class="mb-4">Complaints</h3>

    <?= displayAlert() ?>

    <div class="card mb-4">
        <div class="card-header"><strong>Submit Complaint</strong></div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <?= csrfField() ?>
                <div class="mb-3">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat ?>"><?= $cat ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select" required>
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                            <option value="Emergency">Emergency</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4" required></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Attachment (optional)</label>
                    <input type="file" name="attachment" class="form-control">
                </div>
                <button type="submit" class="btn btn-primary">Submit Complaint</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>My Complaints (<?= count($complaints) ?>)</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($complaints): ?>
                            <?php $i = 1; foreach ($complaints as $comp): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><?= escapeOutput($comp['title']) ?></td>
                                    <td><?= escapeOutput($comp['category']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= match($comp['priority']){'Emergency'=>'danger','High'=>'warning','Medium'=>'primary','Low'=>'secondary',default=>'primary'} ?>">
                                            <?= escapeOutput($comp['priority'] ?? 'Medium') ?>
                                        </span>
                                    </td>
                                    <td><?= date('d M Y', strtotime($comp['created_at'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= match($comp['status']){'Pending'=>'warning','New'=>'primary','Under Inspection'=>'info','In Progress'=>'warning','Resolved by Warden'=>'success','Escalated to Admin'=>'danger','Under Admin Review'=>'dark','Closed'=>'secondary',default=>'secondary'} ?>">
                                            <?= escapeOutput($comp['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?action=view&id=<?= $comp['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                        <?php if ($comp['attachment']): ?>
                                            <a href="<?= BASE_URL ?>/uploads/<?= $comp['attachment'] ?>" target="_blank" class="btn btn-sm btn-outline-info"><i class="bi bi-paperclip"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No complaints filed.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/student-footer.php'; ?>
