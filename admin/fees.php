<?php
$title = 'Fee Management';
require_once __DIR__ . '/../includes/admin-header.php';

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

$totalCollected = db()->query("SELECT COALESCE(SUM(paid_amount),0) FROM fees")->fetchColumn();
$totalPending   = db()->query("SELECT COALESCE(SUM(total_fee-paid_amount),0) FROM fees WHERE status!='Paid'")->fetchColumn();
$totalDue       = db()->query("SELECT COALESCE(SUM(due_amount),0) FROM fees")->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        $student_id   = (int)$_POST['student_id'];
        $total_fee    = (float)$_POST['total_fee'];
        $paid_amount  = (float)$_POST['paid_amount'];
        $payment_mode = sanitize($_POST['payment_mode']);
        $receipt_no   = sanitize($_POST['receipt_no']);
        $payment_date = $_POST['payment_date'];

        if ($paid_amount >= $total_fee) $status = 'Paid';
        elseif ($paid_amount > 0) $status = 'Partial';
        else $status = 'Pending';

        try {
            if ($action === 'add') {
                $stmt = db()->prepare("INSERT INTO fees (student_id,total_fee,paid_amount,payment_mode,receipt_no,payment_date,status) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$student_id,$total_fee,$paid_amount,$payment_mode,$receipt_no,$payment_date,$status]);
                setAlert('success', 'Fee record added.');
            } else {
                $stmt = db()->prepare("UPDATE fees SET student_id=?,total_fee=?,paid_amount=?,payment_mode=?,receipt_no=?,payment_date=?,status=? WHERE id=?");
                $stmt->execute([$student_id,$total_fee,$paid_amount,$payment_mode,$receipt_no,$payment_date,$status,$id]);
                setAlert('success', 'Fee record updated.');
            }
            redirect(BASE_URL . '/admin/fees.php');
        } catch (PDOException $e) {
            setAlert('danger', 'Error: ' . $e->getMessage());
        }
    }
}

if ($action === 'delete' && $id) {
    db()->prepare("DELETE FROM fees WHERE id=?")->execute([$id]);
    setAlert('success', 'Fee record deleted.');
    redirect(BASE_URL . '/admin/fees.php');
}

$page    = (int)($_GET['p'] ?? 1);
$perPage = 10;

$total = db()->query("SELECT COUNT(*) FROM fees")->fetchColumn();
$offset = ($page - 1) * $perPage;
$pages  = paginate($page, $perPage, $total);

$stmt = db()->prepare("SELECT f.*, s.name AS student_name, s.roll_no FROM fees f JOIN students s ON s.id=f.student_id ORDER BY f.id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute();
$fees = $stmt->fetchAll();
?>
<div class="container-fluid">
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card text-bg-success"><div class="card-body"><h5>₹<?= number_format($totalCollected,2) ?></h5><p>Total Collected</p></div></div></div>
        <div class="col-md-4"><div class="card text-bg-warning"><div class="card-body"><h5>₹<?= number_format($totalPending,2) ?></h5><p>Total Pending</p></div></div></div>
        <div class="col-md-4"><div class="card text-bg-danger"><div class="card-body"><h5>₹<?= number_format($totalDue,2) ?></h5><p>Total Due</p></div></div></div>
    </div>

    <?php if ($action === 'list'): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0">Fee Records</h4>
        <a href="?action=add" class="btn btn-primary btn-sm">+ Add Fee</a>
    </div>
    <table class="table table-striped table-bordered">
        <thead><tr><th>Student</th><th>Roll No</th><th>Total Fee</th><th>Paid</th><th>Due</th><th>Payment Mode</th><th>Receipt / Txn ID</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($fees as $f): ?>
            <tr>
                <td><?= sanitize($f['student_name']) ?></td>
                <td><?= sanitize($f['roll_no']) ?></td>
                <td>₹<?= number_format($f['total_fee'],2) ?></td>
                <td>₹<?= number_format($f['paid_amount'],2) ?></td>
                <td>₹<?= number_format($f['due_amount'],2) ?></td>
                <td><?= $f['payment_mode'] ?></td>
                <td><?= sanitize($f['transaction_id'] ?? $f['receipt_no']) ?></td>
                <td><?= $f['payment_date'] ?></td>
                <td><span class="badge bg-<?= $f['status']=='Paid'?'success':($f['status']=='Partial'?'warning':'secondary') ?>"><?= $f['status'] ?></span></td>
                <td>
                    <a href="?action=edit&id=<?= $f['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                    <a href="?action=delete&id=<?= $f['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?= paginationLinks($page, $pages) ?>

    <?php elseif ($action === 'add' || ($action === 'edit' && $id)):
        $fee = ['student_id'=>'','total_fee'=>'','paid_amount'=>'','payment_mode'=>'','receipt_no'=>'','payment_date'=>date('Y-m-d')];
        if ($action === 'edit') {
            $stmt = db()->prepare("SELECT * FROM fees WHERE id=?");
            $stmt->execute([$id]);
            $fee = $stmt->fetch();
            if (!$fee) { setAlert('danger','Not found'); redirect(BASE_URL.'/admin/fees.php'); }
        }
    ?>
    <h4 class="mb-3"><?= $action==='add'?'Add':'Edit' ?> Fee Record</h4>
    <form method="post" action="?action=add" class="row g-3"><?= csrfField() ?>
        <div class="col-md-4">
            <label>Student</label>
            <select name="student_id" class="form-select" required>
                <option value="">-- Select --</option>
                <?php $sts = db()->query("SELECT id,name,roll_no FROM students WHERE status='Active'");
                while ($s = $sts->fetch()): ?>
                <option value="<?= $s['id'] ?>" <?= ($fee['student_id']??'')==$s['id']?'selected':'' ?>><?= sanitize($s['name']) ?> (<?= sanitize($s['roll_no']) ?>)</option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-4"><label>Total Fee (₹)</label><input type="number" step="0.01" name="total_fee" class="form-control" value="<?= $fee['total_fee'] ?>" required></div>
        <div class="col-md-4"><label>Paid Amount (₹)</label><input type="number" step="0.01" name="paid_amount" class="form-control" value="<?= $fee['paid_amount'] ?>"></div>
        <div class="col-md-4"><label>Payment Mode</label><select name="payment_mode" class="form-select"><option value="Cash" <?= ($fee['payment_mode']??'')=='Cash'?'selected':'' ?>>Cash</option><option value="DD" <?= ($fee['payment_mode']??'')=='DD'?'selected':'' ?>>DD</option><option value="Cheque" <?= ($fee['payment_mode']??'')=='Cheque'?'selected':'' ?>>Cheque</option><option value="Online" <?= ($fee['payment_mode']??'')=='Online'?'selected':'' ?>>Online</option></select></div>
        <div class="col-md-4"><label>Receipt No</label><input type="text" name="receipt_no" class="form-control" value="<?= sanitize($fee['receipt_no']??'') ?>"></div>
        <div class="col-md-4"><label>Payment Date</label><input type="date" name="payment_date" class="form-control" value="<?= $fee['payment_date'] ?>"></div>
        <div class="col-12"><button class="btn btn-primary"><?= $action==='add'?'Add':'Update' ?> Fee</button> <a href="<?= BASE_URL ?>/admin/fees.php" class="btn btn-secondary">Cancel</a></div>
    </form>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
