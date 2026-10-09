<?php
$title = 'Fee Details';
require_once __DIR__ . '/../includes/student-header.php';

$studentStmt = db()->prepare("SELECT * FROM students WHERE user_id = ?");
$studentStmt->execute([$_SESSION['user_id']]);
$student = $studentStmt->fetch();

$feesStmt = db()->prepare("SELECT * FROM fees WHERE student_id = ? ORDER BY payment_date DESC");
$feesStmt->execute([$student['id']]);
$fees = $feesStmt->fetchAll();

$totalPaid = 0;
$totalDue = 0;
foreach ($fees as $fee) {
    $totalPaid += max(0, $fee['paid_amount']);
    $totalDue += max(0, $fee['due_amount']);
}
?>

<div class="container-fluid">
    <?php if (isset($_GET['payment']) && $_GET['payment'] === 'success'): ?>
        <div class="alert alert-success alert-dismissible fade show">Payment successful! Your fee record has been updated.</div>
    <?php elseif (isset($_GET['payment']) && $_GET['payment'] === 'failed'): ?>
        <div class="alert alert-danger alert-dismissible fade show">Payment failed or was cancelled. Please try again.</div>
    <?php endif; ?>

    <h3 class="mb-4">Fee Details</h3>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body">
                    <h5 class="card-title text-success">Total Paid</h5>
                    <p class="card-text display-6">₹<?= number_format($totalPaid) ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body">
                    <h5 class="card-title text-danger">Current Due</h5>
                    <p class="card-text display-6">₹<?= number_format($totalDue) ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Payment History</strong></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Month</th>
                            <th>Year</th>
                            <th>Academic Year</th>
                            <th>Days Present</th>
                            <th>Charge/Day</th>
                            <th>Electricity Bill</th>
                            <th>Mess Bill</th>
                            <th>Total Bill</th>
                            <th>Payment Status</th>
                            <th>DU Reference No</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($fees): ?>
                            <?php foreach ($fees as $fee): ?>
                                <tr>
                                    <td><?= htmlspecialchars($fee['bill_month'] ?? '') ?: '—' ?></td>
                                    <td><?= htmlspecialchars($fee['bill_year'] ?? '') ?: '—' ?></td>
                                    <td><?= htmlspecialchars($fee['academic_year'] ?? '') ?: '—' ?></td>
                                    <td><?= (int)($fee['days_present'] ?? 0) ?></td>
                                    <td>₹<?= number_format(max(0,$fee['charge_per_day'] ?? 0), 2) ?></td>
                                    <td>₹<?= number_format(max(0,$fee['electricity_bill'] ?? 0), 2) ?></td>
                                    <td>₹<?= number_format(max(0,$fee['mess_bill'] ?? 0), 2) ?></td>
                                    <td><strong>₹<?= number_format(max(0,$fee['total_fee'])) ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?= $fee['status'] == 'Paid' ? 'success' : ($fee['status'] == 'Partial' ? 'warning' : 'danger') ?>">
                                            <?= htmlspecialchars($fee['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($fee['receipt_no']) ?: '—' ?></td>
                                    <td>
                                        <?php if ($fee['paid_amount'] > 0): ?>
                                            <a href="<?= BASE_URL ?>/student/receipt.php?fee_id=<?= $fee['id'] ?>" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-receipt"></i> Receipt</a>
                                        <?php endif; ?>
                                        <?php if ($fee['due_amount'] > 0): ?>
                                            <a href="<?= BASE_URL ?>/student/sbi-pay.php?fee_id=<?= $fee['id'] ?>" class="btn btn-success btn-sm"><i class="bi bi-bank"></i> Pay via SBI Collect</a>
                                        <?php endif; ?>
                                        <?php if ($fee['due_amount'] <= 0 && $fee['paid_amount'] <= 0): ?>
                                            <span class="text-muted">--</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center text-muted">No fee records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/student-footer.php'; ?>
