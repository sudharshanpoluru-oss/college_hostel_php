<?php
$title = 'Manage Staff';
require_once __DIR__ . '/../includes/admin-header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'add' || $action === 'edit')) {
    $name = sanitize($_POST['name']);
    $designation = sanitize($_POST['designation']);
    $description = sanitize($_POST['description']);
    $icon = sanitize($_POST['icon']);
    $sort_order = (int)$_POST['sort_order'];

    try {
        if ($action === 'add') {
            $stmt = db()->prepare("INSERT INTO management_staff (name, designation, description, icon, sort_order) VALUES (?,?,?,?,?)");
            $stmt->execute([$name, $designation, $description, $icon, $sort_order]);
            setAlert('success', 'Staff added.');
        } else {
            $stmt = db()->prepare("UPDATE management_staff SET name=?, designation=?, description=?, icon=?, sort_order=? WHERE id=?");
            $stmt->execute([$name, $designation, $description, $icon, $sort_order, $id]);
            setAlert('success', 'Staff updated.');
        }
        redirect(BASE_URL . '/admin/manage-staff.php');
    } catch (PDOException $e) {
        setAlert('danger', 'Error: ' . $e->getMessage());
    }
}

if ($action === 'delete' && $id) {
    db()->prepare("DELETE FROM management_staff WHERE id=?")->execute([$id]);
    setAlert('success', 'Staff deleted.');
    redirect(BASE_URL . '/admin/manage-staff.php');
}

$staff = db()->query("SELECT * FROM management_staff ORDER BY sort_order, id")->fetchAll();
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0">Management Staff</h4>
        <a href="?action=add" class="btn btn-primary btn-sm">+ Add Staff</a>
    </div>

    <?php if ($action === 'add' || ($action === 'edit' && $id)):
        $s = ['name'=>'', 'designation'=>'', 'description'=>'', 'icon'=>'bi bi-person-badge', 'sort_order'=>'0'];
        if ($action === 'edit') {
            $stmt = db()->prepare("SELECT * FROM management_staff WHERE id=?");
            $stmt->execute([$id]);
            $s = $stmt->fetch();
            if (!$s) { setAlert('danger','Not found'); redirect(BASE_URL.'/admin/manage-staff.php'); }
        }
    ?>
    <div class="card">
        <div class="card-header"><strong><?= $action==='add'?'Add':'Edit' ?> Staff</strong></div>
        <div class="card-body">
            <form method="post" action="?action=add" class="row g-3"><?= csrfField() ?>
                <div class="col-md-6"><label>Name</label><input type="text" name="name" class="form-control" value="<?= sanitize($s['name']) ?>" required></div>
                <div class="col-md-6"><label>Designation</label><input type="text" name="designation" class="form-control" value="<?= sanitize($s['designation']) ?>" required></div>
                <div class="col-md-6"><label>Description</label><input type="text" name="description" class="form-control" value="<?= sanitize($s['description']) ?>"></div>
                <div class="col-md-3"><label>Icon Class</label><input type="text" name="icon" class="form-control" value="<?= sanitize($s['icon']) ?>"></div>
                <div class="col-md-3"><label>Sort Order</label><input type="number" name="sort_order" class="form-control" value="<?= $s['sort_order'] ?>"></div>
                <div class="col-12"><button class="btn btn-primary"><?= $action==='add'?'Add':'Update' ?></button> <a href="<?= BASE_URL ?>/admin/manage-staff.php" class="btn btn-secondary">Cancel</a></div>
            </form>
        </div>
    </div>
    <?php else: ?>
    <table class="table table-bordered table-striped">
        <thead><tr><th>Order</th><th>Icon</th><th>Name</th><th>Designation</th><th>Description</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($staff as $s): ?>
            <tr>
                <td><?= $s['sort_order'] ?></td>
                <td><i class="<?= sanitize($s['icon']) ?>" style="font-size:1.2rem"></i></td>
                <td><?= sanitize($s['name']) ?></td>
                <td><?= sanitize($s['designation']) ?></td>
                <td><?= sanitize($s['description']) ?></td>
                <td>
                    <a href="?action=edit&id=<?= $s['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                    <a href="?action=delete&id=<?= $s['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$staff): ?><tr><td colspan="6" class="text-center text-muted">No staff added.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
