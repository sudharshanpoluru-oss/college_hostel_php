<?php
$title = 'Events';
require_once __DIR__ . '/../includes/admin-header.php';

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$search = trim($_GET['q'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        $title       = sanitize($_POST['title']);
        $description = $_POST['description'];
        $event_date  = $_POST['event_date'];
        $event_time  = $_POST['event_time'] ?? null;
        $location    = sanitize($_POST['location'] ?? '');
        $status      = (int)sanitize($_POST['status'] ?? 0);
        $created_by  = $_SESSION['user_id'] ?? 0;

        try {
            if ($action === 'add') {
                $stmt = db()->prepare("INSERT INTO hostel_events (title,description,event_date,event_time,location,status,created_by) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$title,$description,$event_date,$event_time,$location,$status,$created_by]);
                setAlert('success', 'Event added.');
            } else {
                $stmt = db()->prepare("UPDATE hostel_events SET title=?,description=?,event_date=?,event_time=?,location=?,status=? WHERE id=?");
                $stmt->execute([$title,$description,$event_date,$event_time,$location,$status,$id]);
                setAlert('success', 'Event updated.');
            }
            redirect(BASE_URL . '/admin/events.php');
        } catch (PDOException $e) {
            setAlert('danger', 'Error: ' . $e->getMessage());
        }
    }
}

if ($action === 'delete' && $id) {
    db()->prepare("DELETE FROM hostel_events WHERE id=?")->execute([$id]);
    setAlert('success', 'Event deleted.');
    redirect(BASE_URL . '/admin/events.php');
}

$page    = (int)($_GET['p'] ?? 1);
$perPage = 10;

if ($search) {
    $stmt = db()->prepare("SELECT COUNT(*) FROM hostel_events WHERE title LIKE ? OR description LIKE ? OR location LIKE ?");
    $stmt->execute(["%$search%", "%$search%", "%$search%"]);
} else {
    $stmt = db()->query("SELECT COUNT(*) FROM hostel_events");
}
$total  = $stmt->fetchColumn();
$offset = ($page - 1) * $perPage;
$pages  = paginate($page, $perPage, $total);

if ($search) {
    $stmt = db()->prepare("SELECT * FROM hostel_events WHERE title LIKE ? OR description LIKE ? OR location LIKE ? ORDER BY event_date DESC, event_time ASC LIMIT $perPage OFFSET $offset");
    $stmt->execute(["%$search%", "%$search%", "%$search%"]);
} else {
    $stmt = db()->prepare("SELECT * FROM hostel_events ORDER BY event_date DESC, event_time ASC LIMIT $perPage OFFSET $offset");
    $stmt->execute();
}
$events = $stmt->fetchAll();
?>
<div class="container-fluid">
    <?php if ($action === 'list'): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0">Events</h4>
        <div>
            <form method="get" class="d-inline-block me-2">
                <div class="input-group input-group-sm">
                    <input type="text" name="q" class="form-control" placeholder="Search events..." value="<?= escapeOutput($search) ?>">
                    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                    <?php if ($search): ?>
                    <a href="?" class="btn btn-outline-danger"><i class="bi bi-x"></i></a>
                    <?php endif; ?>
                </div>
            </form>
            <a href="?action=add" class="btn btn-primary btn-sm">+ Add Event</a>
        </div>
    </div>
    <table class="table table-striped table-bordered">
        <thead><tr><th>Title</th><th>Date</th><th>Time</th><th>Location</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            <?php if (count($events) > 0): ?>
            <?php foreach ($events as $e): ?>
            <tr>
                <td><?= sanitize($e['title']) ?></td>
                <td><?= $e['event_date'] ?></td>
                <td><?= $e['event_time'] ? date('h:i A', strtotime($e['event_time'])) : '-' ?></td>
                <td><?= $e['location'] ? sanitize($e['location']) : '-' ?></td>
                <td><span class="badge bg-<?= $e['status']==1?'success':'secondary' ?>"><?= $e['status']==1?'Active':'Inactive' ?></span></td>
                <td>
                    <a href="?action=edit&id=<?= $e['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                    <a href="?action=delete&id=<?= $e['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this event?')">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php else: ?>
            <tr><td colspan="6" class="text-center text-muted">No events found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?= paginationLinks($page, $pages, $search ? "&q=" . urlencode($search) : '') ?>

    <?php elseif ($action === 'add' || ($action === 'edit' && $id)):
        $event = ['title'=>'','description'=>'','event_date'=>date('Y-m-d'),'event_time'=>'','location'=>'','status'=>1];
        if ($action === 'edit') {
            $stmt = db()->prepare("SELECT * FROM hostel_events WHERE id=?");
            $stmt->execute([$id]);
            $event = $stmt->fetch();
            if (!$event) { setAlert('danger','Event not found'); redirect(BASE_URL.'/admin/events.php'); }
        }
    ?>
    <h4 class="mb-3"><?= $action==='add'?'Add':'Edit' ?> Event</h4>
    <form method="post" action="?action=add" class="row g-3"><?= csrfField() ?>
        <div class="col-md-6"><label>Title <span class="text-danger">*</span></label><input type="text" name="title" class="form-control" value="<?= sanitize($event['title']) ?>" required></div>
        <div class="col-md-3"><label>Event Date <span class="text-danger">*</span></label><input type="date" name="event_date" class="form-control" value="<?= $event['event_date'] ?>" required></div>
        <div class="col-md-3"><label>Event Time</label><input type="time" name="event_time" class="form-control" value="<?= $event['event_time'] ?>"></div>
        <div class="col-md-6"><label>Location</label><input type="text" name="location" class="form-control" value="<?= sanitize($event['location']) ?>"></div>
        <div class="col-md-3"><label>Description</label><textarea name="description" class="form-control" rows="4"><?= sanitize($event['description']) ?></textarea></div>
        <div class="col-md-3 d-flex align-items-end pb-3">
            <div class="form-check">
                <input type="hidden" name="status" value="0">
                <input type="checkbox" name="status" class="form-check-input" id="statusCheck" value="1" <?= $event['status']==1?'checked':'' ?>>
                <label class="form-check-label" for="statusCheck">Active</label>
            </div>
        </div>
        <div class="col-12"><button class="btn btn-primary"><?= $action==='add'?'Add':'Update' ?> Event</button> <a href="<?= BASE_URL ?>/admin/events.php" class="btn btn-secondary">Cancel</a></div>
    </form>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
