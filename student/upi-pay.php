<?php
require_once __DIR__ . '/../includes/session.php';

if (!isLoggedIn() || !isStudent()) {
    redirect(BASE_URL . '/auth/login.php');
}

$upi_id = '9381869092-2@axl';

$fee_id = (int)($_GET['fee_id'] ?? 0);
$stmt = db()->prepare("SELECT f.*, s.user_id, s.name FROM fees f JOIN students s ON s.id = f.student_id WHERE f.id = ?");
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
    $utr = sanitize($_POST['utr'] ?? '');
    if (empty($utr)) {
        $error = 'Please enter your UPI transaction reference (UTR) number.';
    } else {
        $new_paid = $fee['paid_amount'] + $amount;
        $status = $new_paid >= $fee['total_fee'] ? 'Paid' : 'Partial';

        $stmt = db()->prepare("UPDATE fees SET paid_amount = ?, payment_mode = 'UPI', receipt_no = ?, transaction_id = ?, payment_date = CURDATE(), status = ? WHERE id = ?");
        $stmt->execute([$new_paid, $utr, 'UPI_' . $utr, $status, $fee_id]);

        $stmt = db()->prepare("INSERT INTO activity_log (user_type, user_id, action, details) VALUES ('student', ?, 'Fee Payment', ?)");
        $stmt->execute([$_SESSION['user_id'], 'UPI payment of ₹' . number_format($amount, 2) . ' for fee #' . $fee_id]);

        redirect(BASE_URL . '/student/fees.php?payment=success');
    }
}

$upi_link = 'upi://pay?pa=' . urlencode($upi_id) . '&pn=HOSTEL&am=' . $amount . '&tn=FEE' . $fee_id . '&cu=INR';
$qr_data = urlencode($upi_link);
$qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . $qr_data;

$title = 'Pay via UPI';
require_once __DIR__ . '/../includes/student-header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body text-center p-4">
                    <h4 class="mb-3">Pay via UPI</h4>

                    <img src="<?= BASE_URL ?>/assets/images/phonepe.jpeg" alt="PhonePe" style="height:50px;" class="mb-3">

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <small class="text-muted">Amount Due</small>
                        <h2 class="text-primary fw-bold">₹<?= number_format($amount, 2) ?></h2>
                    </div>

                    <div class="mb-3 p-3 bg-light rounded">
                        <small class="text-muted d-block">Send payment to UPI ID</small>
                        <strong class="fs-5"><?= $upi_id ?></strong>
                    </div>

                    <div class="mb-3">
                        <img src="<?= $qr_url ?>" alt="UPI QR Code" class="img-fluid rounded border p-2" style="max-width: 220px;">
                    </div>

                    <a href="<?= $upi_link ?>" class="btn btn-primary btn-lg w-100 mb-3" onclick="event.preventDefault(); window.location.href=this.href; setTimeout(function(){ document.getElementById('utr-section').style.display='block'; }, 3000);">
                        <i class="bi bi-phone"></i> Pay with UPI App
                    </a>

                    <hr id="utr-section" style="display:none;">

                    <script>
                    document.querySelector('a.btn-primary').addEventListener('click', function(){
                        setTimeout(function(){
                            document.getElementById('utr-section').style.display = 'block';
                            document.getElementById('utr-section').scrollIntoView({behavior:'smooth'});
                        }, 3000);
                    });
                    </script>

                    <form method="post">
                        <label class="form-label fw-semibold">Already paid? Enter UTR / Transaction Ref:</label>
                        <input type="text" name="utr" class="form-control form-control-lg mb-3" placeholder="e.g. HDFC123456789" required>
                        <button type="submit" class="btn btn-success w-100">Confirm Payment</button>
                    </form>

                    <p class="text-muted small mt-3">
                        <i class="bi bi-info-circle"></i> Click "Pay with UPI App" → pay in your UPI app → return here → enter UTR → Confirm.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/student-footer.php'; ?>
