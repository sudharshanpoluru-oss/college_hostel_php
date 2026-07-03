<?php
$title = 'Mess Menu Management';
require_once __DIR__ . '/../includes/admin-header.php';

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        $day       = sanitize($_POST['day']);
        $meal_type = sanitize($_POST['meal_type']);
        $menu_items = $_POST['menu_items'];
        $date      = $_POST['date'];
        $status    = (int)sanitize($_POST['status']);

        try {
            if ($action === 'add') {
                $stmt = db()->prepare("INSERT INTO mess_menu (day,meal_type,menu_items,date,status) VALUES (?,?,?,?,?)");
                $stmt->execute([$day,$meal_type,$menu_items,$date,$status]);
                setAlert('success', 'Menu item added.');
            } else {
                $stmt = db()->prepare("UPDATE mess_menu SET day=?,meal_type=?,menu_items=?,date=?,status=? WHERE id=?");
                $stmt->execute([$day,$meal_type,$menu_items,$date,$status,$id]);
                setAlert('success', 'Menu item updated.');
            }
            redirect(BASE_URL . '/admin/mess.php');
        } catch (PDOException $e) {
            setAlert('danger', 'Error: ' . $e->getMessage());
        }
    }
}

if ($action === 'delete' && $id) {
    db()->prepare("DELETE FROM mess_menu WHERE id=?")->execute([$id]);
    setAlert('success', 'Menu item deleted.');
    redirect(BASE_URL . '/admin/mess.php');
}

$stmt = db()->query("SELECT * FROM mess_menu ORDER BY FIELD(day,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), FIELD(meal_type,'Breakfast','Lunch','Evening Snacks','Dinner')");
$items = $stmt->fetchAll();
$grouped = [];
foreach ($items as $item) {
    $grouped[$item['day']][] = $item;
}
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0">Mess Menu</h4>
        <a href="?action=add" class="btn btn-primary btn-sm">+ Add Menu Item</a>
    </div>

    <?php if ($action === 'list'): ?>
    <?php foreach ($grouped as $day => $dayItems): ?>
    <div class="card mb-3">
        <div class="card-header"><strong><?= $day ?></strong></div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead><tr><th>Meal Type</th><th>Menu Items</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($dayItems as $m): ?>
                    <tr>
                        <td><?= $m['meal_type'] ?></td>
                        <td><?= nl2br(sanitize($m['menu_items'])) ?></td>
                        <td><?= $m['date'] ?></td>
                        <td><span class="badge bg-<?= $m['status']==1?'success':'secondary' ?>"><?= $m['status']==1?'Active':'Inactive' ?></span></td>
                        <td>
                            <a href="?action=edit&id=<?= $m['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                            <a href="?action=delete&id=<?= $m['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>

    <?php elseif ($action === 'add' || ($action === 'edit' && $id)):
        $menu = ['day'=>'Monday','meal_type'=>'Breakfast','menu_items'=>'','date'=>date('Y-m-d'),'status'=>1];
        if ($action === 'edit') {
            $stmt = db()->prepare("SELECT * FROM mess_menu WHERE id=?");
            $stmt->execute([$id]);
            $menu = $stmt->fetch();
            if (!$menu) { setAlert('danger','Not found'); redirect(BASE_URL.'/admin/mess.php'); }
        }
    ?>
    <h4 class="mb-3"><?= $action==='add'?'Add':'Edit' ?> Menu Item</h4>
    <form method="post" action="?action=add" class="row g-3"><?= csrfField() ?>
        <div class="col-md-4"><label>Day</label><select name="day" class="form-select"><?php foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $d): ?><option value="<?= $d ?>" <?= $menu['day']==$d?'selected':'' ?>><?= $d ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label>Meal Type</label><select name="meal_type" class="form-select"><option value="Breakfast" <?= $menu['meal_type']=='Breakfast'?'selected':'' ?>>Breakfast</option><option value="Lunch" <?= $menu['meal_type']=='Lunch'?'selected':'' ?>>Lunch</option><option value="Evening Snacks" <?= $menu['meal_type']=='Evening Snacks'?'selected':'' ?>>Evening Snacks</option><option value="Dinner" <?= $menu['meal_type']=='Dinner'?'selected':'' ?>>Dinner</option></select></div>
        <div class="col-md-4"><label>Status</label>            <select name="status" class="form-select"><option value="1" <?= $menu['status']==1?'selected':'' ?>>Active</option><option value="0" <?= $menu['status']==0?'selected':'' ?>>Inactive</option></select></div>
        <div class="col-md-4"><label>Date</label><input type="date" name="date" class="form-control" value="<?= $menu['date'] ?>"></div>
        <div class="col-12"><label>Menu Items</label><textarea name="menu_items" class="form-control" rows="4"><?= sanitize($menu['menu_items']) ?></textarea></div>
        <div class="col-12"><button class="btn btn-primary"><?= $action==='add'?'Add':'Update' ?> Menu</button> <a href="<?= BASE_URL ?>/admin/mess.php" class="btn btn-secondary">Cancel</a></div>
    </form>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
