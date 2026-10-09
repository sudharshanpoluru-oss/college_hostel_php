<?php
$title = 'Notice Management';
require_once __DIR__ . '/../includes/warden-header.php';

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        $title       = sanitize($_POST['title']);
        $content     = $_POST['content'];
        $priority    = sanitize($_POST['priority']);
        $publish_date = $_POST['publish_date'];
        $expiry_date  = $_POST['expiry_date'];
        $status      = (int)sanitize($_POST['status']);
        $created_by  = $_SESSION['user_id'] ?? 0;

        try {
            if ($action === 'add') {
                $stmt = db()->prepare("INSERT INTO notices (title,content,priority,publish_date,expiry_date,status,created_by) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$title,$content,$priority,$publish_date,$expiry_date,$status,$created_by]);
                setAlert('success', 'Notice added.');
            } else {
                $stmt = db()->prepare("UPDATE notices SET title=?,content=?,priority=?,publish_date=?,expiry_date=?,status=? WHERE id=?");
                $stmt->execute([$title,$content,$priority,$publish_date,$expiry_date,$status,$id]);
                setAlert('success', 'Notice updated.');
            }
            redirect(BASE_URL . '/warden/notices.php');
        } catch (PDOException $e) {
            setAlert('danger', 'Error: ' . $e->getMessage());
        }
    }
}

if ($action === 'delete' && $id) {
    db()->prepare("DELETE FROM notices WHERE id=?")->execute([$id]);
    setAlert('success', 'Notice deleted.');
    redirect(BASE_URL . '/warden/notices.php');
}

$page    = (int)($_GET['p'] ?? 1);
$perPage = 10;

$total = db()->query("SELECT COUNT(*) FROM notices")->fetchColumn();
$offset = ($page - 1) * $perPage;
$pages  = paginate($page, $perPage, $total);

$stmt = db()->prepare("SELECT * FROM notices ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute();
$notices = $stmt->fetchAll();
?>
<div class="container-fluid">
    <?php if ($action === 'list'): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0">Notices</h4>
        <a href="?action=add" class="btn btn-primary btn-sm">+ Add Notice</a>
    </div>
    <table class="table table-striped table-bordered">
        <thead><tr><th>Title</th><th>Priority</th><th>Publish Date</th><th>Expiry Date</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($notices as $n): ?>
            <tr>
                <td><?= sanitize($n['title']) ?></td>
                <td><span class="badge bg-<?= $n['priority']=='Normal'?'primary':($n['priority']=='Urgent'?'warning':'danger') ?>"><?= $n['priority'] ?></span></td>
                <td><?= $n['publish_date'] ?></td>
                <td><?= $n['expiry_date'] ?? '-' ?></td>
                                <td><span class="badge bg-<?= $n['status']==1?'success':'secondary' ?>"><?= $n['status']==1?'Active':'Inactive' ?></span></td>
                <td>
                    <a href="?action=edit&id=<?= $n['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                    <a href="?action=delete&id=<?= $n['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?= paginationLinks($page, $pages) ?>

    <?php elseif ($action === 'add' || ($action === 'edit' && $id)):
        $notice = ['title'=>'','content'=>'','priority'=>'Normal','publish_date'=>date('Y-m-d'),'expiry_date'=>'','status'=>1];
        if ($action === 'edit') {
            $stmt = db()->prepare("SELECT * FROM notices WHERE id=?");
            $stmt->execute([$id]);
            $notice = $stmt->fetch();
            if (!$notice) { setAlert('danger','Not found'); redirect(BASE_URL.'/warden/notices.php'); }
        }
    ?>
    <h4 class="mb-3"><?= $action==='add'?'Add':'Edit' ?> Notice</h4>
    <form method="post" action="?action=add" class="row g-3">
        <?= csrfField() ?>
        <div class="col-md-6"><label>Title</label><input type="text" name="title" class="form-control" value="<?= sanitize($notice['title']) ?>" required></div>
        <div class="col-md-3"><label>Priority</label><select name="priority" class="form-select"><option value="Normal" <?= $notice['priority']=='Normal'?'selected':'' ?>>Normal</option><option value="Urgent" <?= $notice['priority']=='Urgent'?'selected':'' ?>>Urgent</option><option value="Critical" <?= $notice['priority']=='Critical'?'selected':'' ?>>Critical</option></select></div>
        <div class="col-md-3"><label>Status</label><select name="status" class="form-select"><option value="1" <?= $notice['status']==1?'selected':'' ?>>Active</option><option value="0" <?= $notice['status']==0?'selected':'' ?>>Inactive</option></select></div>
        <div class="col-md-4"><label>Publish Date</label><input type="date" name="publish_date" class="form-control" value="<?= $notice['publish_date'] ?>"></div>
        <div class="col-md-4"><label>Expiry Date</label><input type="date" name="expiry_date" class="form-control" value="<?= $notice['expiry_date'] ?>"></div>
        <div class="col-12"><label>Content</label><textarea name="content" class="form-control" rows="6"><?= sanitize($notice['content']) ?></textarea></div>
        <div class="col-12"><button class="btn btn-primary"><?= $action==='add'?'Add':'Update' ?> Notice</button> <a href="<?= BASE_URL ?>/warden/notices.php" class="btn btn-secondary">Cancel</a></div>
    </form>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/warden-footer.php'; ?>
