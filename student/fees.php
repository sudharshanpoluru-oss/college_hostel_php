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
    $totalPaid += $fee['paid_amount'];
    $totalDue += $fee['due_amount'];
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
                            <th>Total Fee</th>
                            <th>Paid Amount</th>
                            <th>Due Amount</th>
                            <th>Payment Mode</th>
                            <th>Receipt No</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($fees): ?>
                            <?php foreach ($fees as $fee): ?>
                                <tr>
                                    <td>₹<?= number_format($fee['total_fee']) ?></td>
                                    <td>₹<?= number_format($fee['paid_amount']) ?></td>
                                    <td>₹<?= number_format($fee['due_amount']) ?></td>
                                    <td><?= htmlspecialchars($fee['payment_mode']) ?></td>
                                    <td><?= htmlspecialchars($fee['transaction_id'] ?? $fee['receipt_no']) ?></td>
                                    <td><?= date('d M Y', strtotime($fee['payment_date'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $fee['status'] == 'Paid' ? 'success' : ($fee['status'] == 'Partial' ? 'warning' : 'danger') ?>">
                                            <?= $fee['status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($fee['due_amount'] > 0): ?>
                                            <div class="d-flex gap-1 flex-wrap">
                                                <button class="btn btn-success btn-sm pay-now" data-fee-id="<?= $fee['id'] ?>" data-amount="<?= $fee['due_amount'] ?>">Razorpay</button>
                                                <a href="<?= BASE_URL ?>/student/upi-pay.php?fee_id=<?= $fee['id'] ?>" class="btn btn-primary btn-sm">Pay via UPI</a>
                                            </div>
                                        <?php else: ?>
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

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.querySelectorAll('.pay-now').forEach(btn => {
    btn.addEventListener('click', function() {
        const feeId = this.dataset.feeId;
        const amount = parseFloat(this.dataset.amount);
        const btnEl = this;
        btnEl.disabled = true;
        btnEl.textContent = 'Processing...';

        fetch('<?= BASE_URL ?>/student/pay.php?action=create-order', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'fee_id=' + feeId
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert(data.message);
                btnEl.disabled = false;
                btnEl.textContent = 'Pay Now';
                return;
            }

            const options = {
                key: '<?= RAZORPAY_KEY_ID ?>',
                amount: data.amount,
                currency: 'INR',
                name: '<?= SITE_NAME ?>',
                description: 'Fee Payment',
                order_id: data.order_id,
                prefill: {
                    name: '<?= addslashes($student['name']) ?>',
                    email: '<?= addslashes($student['email']) ?>',
                    contact: '<?= addslashes($student['phone']) ?>'
                },
                handler: function(response) {
                    fetch('<?= BASE_URL ?>/student/pay.php?action=verify', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'fee_id=' + feeId + '&razorpay_payment_id=' + response.razorpay_payment_id + '&razorpay_order_id=' + response.razorpay_order_id + '&razorpay_signature=' + response.razorpay_signature
                    })
                    .then(res => res.json())
                    .then(result => {
                        if (result.success) {
                            window.location.href = '<?= BASE_URL ?>/student/fees.php?payment=success';
                        } else {
                            alert(result.message);
                            window.location.href = '<?= BASE_URL ?>/student/fees.php?payment=failed';
                        }
                    });
                },
                modal: {
                    ondismiss: function() {
                        btnEl.disabled = false;
                        btnEl.textContent = 'Pay Now';
                    }
                }
            };

            const rzp = new Razorpay(options);
            rzp.open();
        })
        .catch(() => {
            alert('Something went wrong. Please try again.');
            btnEl.disabled = false;
            btnEl.textContent = 'Pay Now';
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/student-footer.php'; ?>
