<?php
require_once __DIR__ . '/../includes/session.php';

if (!isLoggedIn() || !isStudent()) {
    redirect(BASE_URL . '/auth/login.php');
}

define('SBI_COLLECT_URL', 'https://sbcollect.sbi.bank.in/sbicollect/icollecthome.htm');

$fee_id = (int)($_GET['fee_id'] ?? 0);
$stmt = db()->prepare("SELECT f.*, s.user_id, s.name, s.roll_no FROM fees f JOIN students s ON s.id = f.student_id WHERE f.id = ?");
$stmt->execute([$fee_id]);
$fee = $stmt->fetch();

if (!$fee || $fee['user_id'] != $_SESSION['user_id']) {
    redirect(BASE_URL . '/student/fees.php');
}

$amount = (float)$fee['due_amount'];
if ($amount <= 0) {
    redirect(BASE_URL . '/student/fees.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ref = strtoupper(sanitize($_POST['sbi_ref'] ?? ''));
    if (empty($ref)) {
        $error = 'Please enter your SBI Collect reference / DU / CLRN number.';
    } elseif (!preg_match('/^[A-Z0-9]{6,20}$/', $ref)) {
        $error = 'Invalid reference number. It contains only letters and digits (6-20 characters), e.g. DU1234567890.';
    } else {
        $new_paid = $fee['paid_amount'] + $amount;
        $status = $new_paid >= $fee['total_fee'] ? 'Paid' : 'Partial';

        $stmt = db()->prepare("UPDATE fees SET paid_amount = ?, payment_mode = 'SBI Collect', receipt_no = ?, transaction_id = ?, payment_date = CURDATE(), status = ? WHERE id = ?");
        $stmt->execute([$new_paid, $ref, 'SBICOLLECT_' . $ref, $status, $fee_id]);

        $stmt = db()->prepare("INSERT INTO activity_log (user_id, action, description) VALUES (?, 'Fee Payment', ?)");
        $stmt->execute([$_SESSION['user_id'], 'SBI Collect payment of Rs.' . number_format($amount, 2) . ' for fee #' . $fee_id]);

        redirect(BASE_URL . '/student/fees.php?payment=success');
    }
}

$title = 'Pay Fee via SBI Collect';
require_once __DIR__ . '/../includes/student-header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h4 class="mb-3"><i class="bi bi-bank"></i> Pay via SBI Collect</h4>

                    <div class="mb-3 text-center">
                        <small class="text-muted">Amount Due</small>
                        <h2 class="text-primary fw-bold">₹<?= number_format($amount, 2) ?></h2>
                    </div>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle"></i> <?= $error ?></div>
                    <?php endif; ?>

                    <div class="alert alert-info py-3">
                        <strong><i class="bi bi-list-ol"></i> Follow these steps:</strong>
                        <ol class="mb-0 mt-2 small">
                            <li>Click the <strong>Open SBI Collect</strong> button below (opens in a new tab)</li>
                            <li>Select <strong>State</strong> → Educational Institutions → <strong><?= SITE_NAME ?></strong></li>
                            <li>Select the payment category, then enter:
                                <ul class="mb-0">
                                    <li>Roll Number: <strong><?= sanitize($fee['roll_no']) ?></strong></li>
                                    <li>Amount: <strong>₹<?= number_format($amount, 2) ?></strong> (pay exactly this)</li>
                                </ul>
                            </li>
                            <li>Complete the payment (Net Banking / Card / UPI — any bank)</li>
                            <li>Note the <strong>reference / DU / CLRN number</strong> shown on the SBI receipt</li>
                            <li>Come back here and enter that number below</li>
                        </ol>
                    </div>

                    <div class="text-center mb-4">
                        <a href="<?= SBI_COLLECT_URL ?>" target="_blank" rel="noopener" class="btn btn-primary btn-lg px-5">
                            <i class="bi bi-box-arrow-up-right"></i> Open SBI Collect
                        </a>
                    </div>

                    <hr>

                    <form method="post" class="mt-3">
                        <label class="form-label fw-semibold">Step 2 — Enter SBI Collect Reference Number:</label>
                        <input type="text" name="sbi_ref" class="form-control form-control-lg mb-3 text-uppercase"
                               placeholder="e.g. DU1234567890" maxlength="20" pattern="[A-Za-z0-9]{6,20}"
                               title="Reference number: only letters and digits (6-20 characters)" required>
                        <button type="submit" class="btn btn-success w-100 btn-lg">
                            <i class="bi bi-check-circle"></i> Confirm Payment
                        </button>
                    </form>

                    <p class="text-muted small mt-3 text-center">
                        <i class="bi bi-shield-check"></i> Your reference number is verified against SBI records by the hostel office before final approval.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/student-footer.php'; ?>
