<?php
require_once __DIR__ . '/../includes/session.php';

if (!isLoggedIn() || !isStudent()) {
    redirect(BASE_URL . '/auth/login.php');
}

$action = $_GET['action'] ?? '';

if ($action === 'create-order') {
    header('Content-Type: application/json');

    $fee_id = (int)($_POST['fee_id'] ?? 0);

    $stmt = db()->prepare("SELECT f.*, s.user_id FROM fees f JOIN students s ON s.id = f.student_id WHERE f.id = ?");
    $stmt->execute([$fee_id]);
    $fee = $stmt->fetch();

    if (!$fee || $fee['user_id'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Invalid fee record']);
        exit;
    }

    $due = (float)$fee['paid_amount'];
    $amount = (float)$fee['due_amount'];

    if ($amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'No due amount']);
        exit;
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.razorpay.com/v1/orders',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'amount' => round($amount * 100),
            'currency' => 'INR',
            'receipt' => 'fee_' . $fee_id . '_' . time(),
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo json_encode(['success' => false, 'message' => 'Failed to create payment order']);
        exit;
    }

    $order = json_decode($response, true);

    echo json_encode([
        'success' => true,
        'order_id' => $order['id'],
        'amount' => $order['amount'],
        'fee_id' => $fee_id,
    ]);
    exit;
}

if ($action === 'verify') {
    header('Content-Type: application/json');

    $fee_id = (int)($_POST['fee_id'] ?? 0);
    $razorpay_payment_id = $_POST['razorpay_payment_id'] ?? '';
    $razorpay_order_id = $_POST['razorpay_order_id'] ?? '';
    $razorpay_signature = $_POST['razorpay_signature'] ?? '';

    $expected = hash_hmac('sha256', $razorpay_order_id . '|' . $razorpay_payment_id, RAZORPAY_KEY_SECRET);

    if ($expected !== $razorpay_signature) {
        echo json_encode(['success' => false, 'message' => 'Payment verification failed']);
        exit;
    }

    $stmt = db()->prepare("SELECT * FROM fees WHERE id = ?");
    $stmt->execute([$fee_id]);
    $fee = $stmt->fetch();

    if (!$fee) {
        echo json_encode(['success' => false, 'message' => 'Fee record not found']);
        exit;
    }

    $stmt = db()->prepare("SELECT user_id FROM students WHERE id = ?");
    $stmt->execute([$fee['student_id']]);
    $student = $stmt->fetch();

    if ($student['user_id'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $new_paid = $fee['paid_amount'] + $fee['due_amount'];

    if ($new_paid >= $fee['total_fee']) {
        $status = 'Paid';
    } elseif ($new_paid > 0) {
        $status = 'Partial';
    } else {
        $status = 'Pending';
    }

    $stmt = db()->prepare("UPDATE fees SET paid_amount = ?, payment_mode = 'Online', receipt_no = ?, transaction_id = ?, payment_date = CURDATE(), status = ? WHERE id = ?");
    $stmt->execute([$new_paid, $razorpay_order_id, $razorpay_payment_id, $status, $fee_id]);

    $stmt = db()->prepare("INSERT INTO activity_log (user_type, user_id, action, details) VALUES ('student', ?, 'Fee Payment', ?)");
    $stmt->execute([$_SESSION['user_id'], 'Online payment of ₹' . number_format($fee['due_amount'], 2) . ' for fee #' . $fee_id]);

    echo json_encode(['success' => true, 'message' => 'Payment successful']);
    exit;
}

redirect(BASE_URL . '/student/fees.php');
