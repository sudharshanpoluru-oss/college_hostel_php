<?php
$title = 'Fee Management';
require_once __DIR__ . '/../includes/admin-header.php';

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

$totalCollected = max(0, (float)db()->query("SELECT COALESCE(SUM(paid_amount),0) FROM fees")->fetchColumn());
$totalPending   = max(0, (float)db()->query("SELECT COALESCE(SUM(total_fee-paid_amount),0) FROM fees WHERE status!='Paid'")->fetchColumn());
$totalDue       = max(0, (float)db()->query("SELECT COALESCE(SUM(due_amount),0) FROM fees")->fetchColumn());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        $student_id   = (int)$_POST['student_id'];
        $paid_amount  = (float)$_POST['paid_amount'];
        $payment_mode = 'Cash';
        $receipt_no   = sanitize($_POST['receipt_no']);
        $payment_date = $_POST['payment_date'];

        $bill_month       = sanitize($_POST['bill_month'] ?? '');
        $bill_year        = (int)($_POST['bill_year'] ?? 0);
        $academic_year    = sanitize($_POST['academic_year'] ?? '');
        $days_present     = max(0, (int)$_POST['days_present']);
        $charge_per_day   = max(0, (float)$_POST['charge_per_day']);
        $electricity_bill = max(0, (float)$_POST['electricity_bill']);
        $mess_bill        = max(0, (float)$_POST['mess_bill']);

        $computed = round($days_present * $charge_per_day + $electricity_bill + $mess_bill, 2);
        if ($action === 'edit' && $computed <= 0) {
            $total_fee = (float)db()->query("SELECT total_fee FROM fees WHERE id = " . $id)->fetchColumn();
        } else {
            $total_fee = $computed;
            if ($total_fee <= 0) {
                setAlert('danger', 'Enter billing details: Days Present x Charge/Day or Electricity/Mess amounts.');
                redirect(BASE_URL . '/admin/fees.php?action=' . ($action === 'edit' ? 'edit&id=' . $id : 'add'));
            }
        }

        if ($total_fee < 0 || $paid_amount < 0) {
            setAlert('danger', 'You entered minus values. Please enter correct value.');
            redirect(BASE_URL . '/admin/fees.php' . ($action === 'edit' ? '?action=edit&id=' . $id : '?action=add'));
        }

        if ($paid_amount >= $total_fee) $status = 'Paid';
        elseif ($paid_amount > 0) $status = 'Partial';
        else $status = 'Pending';

        try {
            if ($action === 'add') {
                $stmt = db()->prepare("INSERT INTO fees (student_id,bill_month,bill_year,academic_year,days_present,charge_per_day,electricity_bill,mess_bill,total_fee,paid_amount,payment_mode,receipt_no,payment_date,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$student_id,$bill_month ?: null,$bill_year ?: null,$academic_year ?: null,$days_present,$charge_per_day,$electricity_bill,$mess_bill,$total_fee,$paid_amount,$payment_mode,$receipt_no,$payment_date,$status]);
                setAlert('success', 'Fee record added.');
            } else {
                $stmt = db()->prepare("UPDATE fees SET student_id=?,bill_month=?,bill_year=?,academic_year=?,days_present=?,charge_per_day=?,electricity_bill=?,mess_bill=?,total_fee=?,paid_amount=?,payment_mode=?,receipt_no=?,payment_date=?,status=? WHERE id=?");
                $stmt->execute([$student_id,$bill_month ?: null,$bill_year ?: null,$academic_year ?: null,$days_present,$charge_per_day,$electricity_bill,$mess_bill,$total_fee,$paid_amount,$payment_mode,$receipt_no,$payment_date,$status,$id]);
                setAlert('success', 'Fee record updated.');
            }
            redirect(BASE_URL . '/admin/fees.php');
        } catch (PDOException $e) {
            setAlert('danger', 'Error: ' . $e->getMessage());
        }
    }

    if ($action === 'bulk') {
        $paid_amount      = (float)$_POST['paid_amount'];
        $payment_mode     = 'Cash';
        $receipt_no       = sanitize($_POST['receipt_no']);
        $payment_date     = $_POST['payment_date'];
        $bill_month       = sanitize($_POST['bill_month'] ?? '');
        $bill_year        = (int)($_POST['bill_year'] ?? 0);
        $academic_year    = sanitize($_POST['academic_year'] ?? '');
        $days_present     = max(0, (int)$_POST['days_present']);
        $charge_per_day   = max(0, (float)$_POST['charge_per_day']);
        $electricity_bill = max(0, (float)$_POST['electricity_bill']);
        $mess_bill        = max(0, (float)$_POST['mess_bill']);

        $total_fee = round($days_present * $charge_per_day + $electricity_bill + $mess_bill, 2);

        if ($total_fee < 0 || $paid_amount < 0) {
            setAlert('danger', 'You entered minus values. Please enter correct value.');
            redirect(BASE_URL . '/admin/fees.php?action=bulk');
        }

        if ($total_fee <= 0) {
            setAlert('danger', 'Total bill must be greater than zero. Enter Days Present x Charge/Day or Electricity/Mess amounts.');
            redirect(BASE_URL . '/admin/fees.php?action=bulk');
        }

        if ($paid_amount >= $total_fee) $status = 'Paid';
        elseif ($paid_amount > 0) $status = 'Partial';
        else $status = 'Pending';

        try {
            db()->beginTransaction();
            $ins = db()->prepare("INSERT INTO fees (student_id,bill_month,bill_year,academic_year,days_present,charge_per_day,electricity_bill,mess_bill,total_fee,paid_amount,payment_mode,receipt_no,payment_date,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $students = db()->query("SELECT id, user_id FROM students WHERE status='Active'")->fetchAll();
            $count = 0;
            foreach ($students as $s) {
                $ins->execute([$s['id'],$bill_month ?: null,$bill_year ?: null,$academic_year ?: null,$days_present,$charge_per_day,$electricity_bill,$mess_bill,$total_fee,$paid_amount,$payment_mode,$receipt_no,$payment_date,$status]);
                $period = trim(($bill_month ? $bill_month . ' ' : '') . $bill_year);
                addNotification($s['user_id'], 'Fee Issued', 'A bill of Rs.' . number_format($total_fee, 2) . ($period ? " for {$period}" : '') . ' has been issued to your account.', 'info');
                $count++;
            }
            db()->commit();
            setAlert('success', 'Bill issued to ' . $count . ' students. Total per student: Rs.' . number_format($total_fee, 2));
        } catch (PDOException $e) {
            db()->rollBack();
            setAlert('danger', 'Error: ' . $e->getMessage());
        }
        redirect(BASE_URL . '/admin/fees.php');
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
    <?php $upiRecent = db()->query("SELECT f.*, s.name AS student_name, s.roll_no FROM fees f JOIN students s ON s.id=f.student_id WHERE f.payment_mode IN ('UPI','SBI Collect') AND f.payment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) ORDER BY f.id DESC")->fetchAll(); ?>
    <?php if ($upiRecent): ?>
    <div class="card border-info mb-4">
        <div class="card-header bg-info text-white"><strong><i class="bi bi-bank"></i> Recent Online Payments — Last 7 Days (<?= count($upiRecent) ?>)</strong></div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th>Date</th><th>Student</th><th>Roll No</th><th>Reference No</th><th>Paid</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($upiRecent as $u): ?>
                    <tr>
                        <td><?= $u['payment_date'] ?></td>
                        <td><?= sanitize($u['student_name']) ?></td>
                        <td><?= sanitize($u['roll_no']) ?></td>
                        <td><code><?= sanitize($u['transaction_id'] ?: $u['receipt_no']) ?></code></td>
                        <td>₹<?= number_format($u['paid_amount'],2) ?></td>
                        <td><span class="badge bg-<?= $u['status']=='Paid'?'success':($u['status']=='Partial'?'warning':'secondary') ?>"><?= $u['status'] ?></span></td>
                        <td><a href="<?= BASE_URL ?>/student/receipt.php?fee_id=<?= $u['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Receipt</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0">Fee Records</h4>
        <div>
            <a href="?action=bulk" class="btn btn-success btn-sm"><i class="bi bi-people"></i> Issue Fee to All Students</a>
            <a href="?action=add" class="btn btn-primary btn-sm">+ Add Fee</a>
        </div>
    </div>
    <table class="table table-striped table-bordered">
        <thead><tr><th>Student</th><th>Roll No</th><th>Period</th><th>Total Bill</th><th>Paid</th><th>Due</th><th>Receipt / Txn ID</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($fees as $f): ?>
            <tr>
                <td><?= sanitize($f['student_name']) ?></td>
                <td><?= sanitize($f['roll_no']) ?></td>
                <td><?= sanitize(trim(($f['bill_month'] ?? '') . ' ' . ($f['bill_year'] ?? ''))) ?: '—' ?></td>
                <td>₹<?= number_format(max(0,$f['total_fee']),2) ?></td>
                <td>₹<?= number_format(max(0,$f['paid_amount']),2) ?></td>
                <td>₹<?= number_format(max(0,$f['due_amount']),2) ?></td>
                <td><?= sanitize($f['transaction_id'] ?? $f['receipt_no']) ?></td>
                <td><?= $f['payment_date'] ?></td>
                <td><span class="badge bg-<?= $f['status']=='Paid'?'success':($f['status']=='Partial'?'warning':'secondary') ?>"><?= $f['status'] ?></span></td>
                <td>
                    <a href="<?= BASE_URL ?>/student/receipt.php?fee_id=<?= $f['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Receipt</a>
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
        <div class="col-md-4"><label>Paid Amount (₹)</label><input type="number" step="0.01" min="0" name="paid_amount" class="form-control" value="<?= $fee['paid_amount'] ?>"></div>
        <div class="col-md-4"><label>Receipt No / DU Reference</label><input type="text" name="receipt_no" class="form-control" value="<?= sanitize($fee['receipt_no']??'') ?>"></div>
        <div class="col-md-4"><label>Payment Date</label><input type="date" name="payment_date" class="form-control" value="<?= $fee['payment_date'] ?>"></div>
        <div class="col-12"><hr><h6>Billing Details</h6></div>
        <div class="col-md-3">
            <label>Month</label>
            <select name="bill_month" class="form-select">
                <option value="">--</option>
                <?php foreach (['January','February','March','April','May','June','July','August','September','October','November','December'] as $mn): ?>
                <option value="<?= $mn ?>" <?= ($fee['bill_month']??'')===$mn?'selected':'' ?>><?= $mn ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3"><label>Year</label><input type="number" name="bill_year" min="2000" max="2100" class="form-control" value="<?= $fee['bill_year'] ?? date('Y') ?>"></div>
        <div class="col-md-3"><label>Academic Year</label><input type="text" name="academic_year" maxlength="9" placeholder="e.g. 2025-26" class="form-control" value="<?= sanitize($fee['academic_year']??'') ?>"></div>
        <div class="col-md-3"></div>
        <div class="col-md-3"><label>Days Present</label><input type="number" min="0" max="31" name="days_present" class="form-control billcalc" value="<?= $fee['days_present'] ?? 0 ?>"></div>
        <div class="col-md-3"><label>Charge/Day (₹)</label><input type="number" step="0.01" min="0" name="charge_per_day" class="form-control billcalc" value="<?= $fee['charge_per_day'] ?? '0.00' ?>"></div>
        <div class="col-md-3"><label>Electricity Bill (₹)</label><input type="number" step="0.01" min="0" name="electricity_bill" class="form-control billcalc" value="<?= $fee['electricity_bill'] ?? '0.00' ?>"></div>
        <div class="col-md-3"><label>Mess Bill (₹)</label><input type="number" step="0.01" min="0" name="mess_bill" class="form-control billcalc" value="<?= $fee['mess_bill'] ?? '0.00' ?>"></div>
        <div class="col-md-4"><label>Total Bill (₹) <small class="text-muted">(auto: Days × Charge/Day + Electricity + Mess)</small></label><input type="text" class="form-control fw-bold bg-light" id="autoTotal" readonly></div>
        <div class="col-12"><button class="btn btn-primary"><?= $action==='add'?'Add':'Update' ?> Bill</button> <a href="<?= BASE_URL ?>/admin/fees.php" class="btn btn-secondary">Cancel</a></div>
    </form>
    <script>
    function calcBill() {
        const d = parseFloat(document.querySelector('[name=days_present]').value) || 0;
        const c = parseFloat(document.querySelector('[name=charge_per_day]').value) || 0;
        const e = parseFloat(document.querySelector('[name=electricity_bill]').value) || 0;
        const m = parseFloat(document.querySelector('[name=mess_bill]').value) || 0;
        document.getElementById('autoTotal').value = (d * c + e + m).toFixed(2);
    }
    document.querySelectorAll('.billcalc').forEach(el => el.addEventListener('input', calcBill));
    calcBill();
    </script>

    <?php elseif ($action === 'bulk'): ?>
    <h4 class="mb-3">Issue Monthly Bill to All Students</h4>
    <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle"></i> This will create a bill record for <strong>every active student</strong>. Total is auto-calculated: Days Present × Charge/Day + Electricity + Mess.</div>
    <form method="post" action="?action=bulk" class="row g-3"><?= csrfField() ?>
        <div class="col-md-4"><label>Paid Amount (₹)</label><input type="number" step="0.01" min="0" name="paid_amount" class="form-control" value="0"></div>
        <div class="col-md-4"><label>Receipt No / DU Reference</label><input type="text" name="receipt_no" class="form-control"></div>
        <div class="col-md-4"><label>Payment Date</label><input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
        <div class="col-12"><hr></div>
        <div class="col-md-4">
            <label>Month</label>
            <select name="bill_month" class="form-select">
                <?php foreach (['January','February','March','April','May','June','July','August','September','October','November','December'] as $i => $mn): ?>
                <option value="<?= $mn ?>" <?= $i === (int)date('n') - 1 ? 'selected' : '' ?>><?= $mn ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4"><label>Year</label><input type="number" name="bill_year" min="2000" max="2100" class="form-control" value="<?= date('Y') ?>"></div>
        <div class="col-md-4"><label>Academic Year</label><input type="text" name="academic_year" maxlength="9" placeholder="e.g. 2025-26" class="form-control"></div>
        <div class="col-md-3"><label>Days Present</label><input type="number" min="0" max="31" name="days_present" class="form-control billcalc" required></div>
        <div class="col-md-3"><label>Charge/Day (₹)</label><input type="number" step="0.01" min="0" name="charge_per_day" class="form-control billcalc" required></div>
        <div class="col-md-3"><label>Electricity Bill (₹)</label><input type="number" step="0.01" min="0" name="electricity_bill" class="form-control billcalc" value="0"></div>
        <div class="col-md-3"><label>Mess Bill (₹)</label><input type="number" step="0.01" min="0" name="mess_bill" class="form-control billcalc" value="0"></div>
        <div class="col-md-4"><label>Total per Student (₹)</label><input type="text" class="form-control fw-bold bg-light" id="autoTotal" readonly></div>
        <div class="col-12"><button class="btn btn-success" onclick="return confirm('Issue this bill to ALL active students?')"><i class="bi bi-people"></i> Issue Bill to All Students</button> <a href="<?= BASE_URL ?>/admin/fees.php" class="btn btn-secondary">Cancel</a></div>
    </form>
    <script>
    function calcBill() {
        const d = parseFloat(document.querySelector('[name=days_present]').value) || 0;
        const c = parseFloat(document.querySelector('[name=charge_per_day]').value) || 0;
        const e = parseFloat(document.querySelector('[name=electricity_bill]').value) || 0;
        const m = parseFloat(document.querySelector('[name=mess_bill]').value) || 0;
        document.getElementById('autoTotal').value = (d * c + e + m).toFixed(2);
    }
    document.querySelectorAll('.billcalc').forEach(el => el.addEventListener('input', calcBill));
    </script>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
