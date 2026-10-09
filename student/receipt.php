<?php
require_once __DIR__ . '/../includes/session.php';

if (!isLoggedIn()) {
    redirect(BASE_URL . '/auth/login.php');
}

$fee_id = (int)($_GET['fee_id'] ?? 0);

$stmt = db()->prepare("SELECT f.*, s.name, s.roll_no, COALESCE(r.room_no,'-') AS room_no, s.course, s.department, s.phone, s.user_id
                       FROM fees f
                       JOIN students s ON s.id = f.student_id
                       LEFT JOIN room_allocations al ON al.student_id = s.id AND al.status = 'Active'
                       LEFT JOIN rooms r ON r.id = al.room_id
                       WHERE f.id = ?");
$stmt->execute([$fee_id]);
$fee = $stmt->fetch();

if (!$fee || (!isAdmin() && $fee['user_id'] != $_SESSION['user_id'])) {
    redirect(BASE_URL . '/student/fees.php');
}

$statusColor = ['Paid' => '#198754', 'Partial' => '#b8860b', 'Pending' => '#6c757d'];
$sColor = $statusColor[$fee['status']] ?? '#6c757d';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Receipt #<?= $fee['id'] ?> - <?= SITE_NAME ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, Helvetica, sans-serif; }
        body { background: #f0f2f5; padding: 30px 10px; }
        .receipt { max-width: 640px; margin: 0 auto; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.12); }
        .head { background: #0d3b66; color: #fff; padding: 22px 28px; }
        .head h1 { font-size: 22px; letter-spacing: .5px; }
        .head p { font-size: 12px; opacity: .85; margin-top: 4px; }
        .tag { float: right; text-align: right; font-size: 12px; line-height: 1.7; }
        .tag b { font-size: 15px; }
        .body { padding: 24px 28px; }
        h3.sec { font-size: 13px; color: #0d3b66; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #e8ecf1; padding-bottom: 6px; margin: 18px 0 12px; }
        table.info td { padding: 4px 0; font-size: 14px; vertical-align: top; }
        table.info td:first-child { color: #666; width: 160px; }
        table.amt { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.amt td { padding: 9px 12px; font-size: 14px; border-bottom: 1px solid #eef1f5; }
        table.amt tr:last-child td { border-bottom: none; }
        .total { background: #f5f8fb; font-weight: bold; font-size: 15px !important; }
        .badge { display: inline-block; padding: 3px 12px; border-radius: 20px; color: #fff; font-size: 12px; font-weight: bold; }
        .foot { border-top: 1px dashed #ccd5df; margin: 20px 28px 0; padding: 16px 0 24px; font-size: 11px; color: #888; text-align: center; }
        .actions { max-width: 640px; margin: 14px auto 0; text-align: center; }
        .btn { display: inline-block; padding: 10px 26px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; text-decoration: none; }
        .btn-print { background: #0d6efd; color: #fff; }
        .btn-back { background: #6c757d; color: #fff; margin-left: 8px; }
        @media print {
            body { background: #fff; padding: 0; }
            .receipt { box-shadow: none; max-width: 100%; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="head">
            <div class="tag">Receipt No:<br><b>#<?= str_pad($fee['id'], 6, '0', STR_PAD_LEFT) ?></b></div>
            <h1><?= SITE_NAME ?></h1>
            <p>Hostel Fee Payment Receipt</p>
        </div>
        <div class="body">
            <h3 class="sec">Student Details</h3>
            <table class="info">
                <tr><td>Name</td><td>: <?= htmlspecialchars($fee['name']) ?></td></tr>
                <tr><td>Roll Number</td><td>: <?= htmlspecialchars($fee['roll_no']) ?></td></tr>
                <tr><td>Room No</td><td>: <?= htmlspecialchars($fee['room_no'] ?: '—') ?></td></tr>
                <tr><td>Course / Dept</td><td>: <?= htmlspecialchars(($fee['course'] ?: '—') . ($fee['department'] ? ' / ' . $fee['department'] : '')) ?></td></tr>
                <tr><td>Payment Date</td><td>: <?= date('d M Y', strtotime($fee['payment_date'])) ?></td></tr>
                <tr><td>Billing Period</td><td>: <?= htmlspecialchars(trim(($fee['bill_month'] ?? '') . ' ' . ($fee['bill_year'] ?? ''))) ?: '—' ?></td></tr>
                <?php if (!empty($fee['academic_year'])): ?>
                <tr><td>Academic Year</td><td>: <?= htmlspecialchars($fee['academic_year']) ?></td></tr>
                <?php endif; ?>
                <tr><td>Status</td><td>: <span class="badge" style="background:<?= $sColor ?>"><?= htmlspecialchars($fee['status']) ?></span></td></tr>
            </table>

            <h3 class="sec">Bill Breakdown</h3>
            <table class="amt">
                <tr><td>Days Present × Charge/Day (<?= (int)$fee['days_present'] ?> × ₹<?= number_format(max(0,$fee['charge_per_day']), 2) ?>)</td><td style="text-align:right">₹<?= number_format(round((int)$fee['days_present'] * max(0,$fee['charge_per_day']), 2), 2) ?></td></tr>
                <tr><td>Electricity Bill</td><td style="text-align:right">₹<?= number_format(max(0,$fee['electricity_bill']), 2) ?></td></tr>
                <tr><td>Mess Bill</td><td style="text-align:right">₹<?= number_format(max(0,$fee['mess_bill']), 2) ?></td></tr>
                <tr class="total"><td>Total Bill</td><td style="text-align:right">₹<?= number_format(max(0,$fee['total_fee']), 2) ?></td></tr>
            </table>

            <h3 class="sec">Payment Details</h3>
            <table class="amt">
                <tr><td>Amount Paid</td><td style="text-align:right">₹<?= number_format(max(0,$fee['paid_amount']), 2) ?></td></tr>
                <tr><td>Balance Due</td><td style="text-align:right">₹<?= number_format(max(0,$fee['due_amount']), 2) ?></td></tr>
                <tr><td>Payment Mode</td><td style="text-align:right"><?= htmlspecialchars($fee['payment_mode']) ?></td></tr>
                <tr><td><?= $fee['payment_mode'] === 'UPI' ? 'UTR / Txn Ref' : 'Receipt / Txn ID' ?></td><td style="text-align:right"><?= htmlspecialchars($fee['transaction_id'] ?: $fee['receipt_no'] ?: '—') ?></td></tr>
            </table>
        </div>
        <div class="foot">
            This is a computer generated receipt of <?= SITE_NAME ?>.<br>
            Generated on <?= date('d M Y, h:i A') ?>
        </div>
    </div>

    <div class="actions">
        <button class="btn btn-print" onclick="window.print()">🖨 Print / Save PDF</button>
        <a class="btn btn-back" href="<?= isAdmin() ? BASE_URL . '/admin/fees.php' : BASE_URL . '/student/fees.php' ?>">Back</a>
    </div>
</body>
</html>
