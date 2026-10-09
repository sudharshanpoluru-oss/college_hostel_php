<?php
require_once __DIR__ . '/../includes/session.php';

if (!isLoggedIn() || !isStudent()) {
    redirect(BASE_URL . '/auth/login.php');
}

$code = $_GET['code'] ?? '';
$merchantTransactionId = $_GET['merchantTransactionId'] ?? '';
$transactionId = $_GET['transactionId'] ?? '';

if ($code === 'PAYMENT_SUCCESS' && $merchantTransactionId) {
    $endpoint = '/pg/v1/status/' . PHONEPE_MERCHANT_ID . '/' . $merchantTransactionId;
    $checksum = hash('sha256', $endpoint . PHONEPE_SALT_KEY) . '###' . PHONEPE_SALT_INDEX;

    $base = PHONEPE_ENV === 'UAT'
        ? 'https://api-preprod.phonepe.com/apis/pg-sandbox'
        : 'https://api.phonepe.com/apis/hermes';
    $apiUrl = $base . $endpoint;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-VERIFY: ' . $checksum,
            'X-MERCHANT-ID: ' . PHONEPE_MERCHANT_ID,
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $result = json_decode($response, true);

        if ($result && ($result['success'] ?? false) && ($result['code'] === 'PAYMENT_SUCCESS')) {
            preg_match('/^FEE(\d+)_/', $merchantTransactionId, $m);
            $fee_id = (int)($m[1] ?? 0);

            if ($fee_id) {
                $stmt = db()->prepare("SELECT f.*, s.user_id FROM fees f JOIN students s ON s.id = f.student_id WHERE f.id = ?");
                $stmt->execute([$fee_id]);
                $fee = $stmt->fetch();

                if ($fee && $fee['user_id'] == $_SESSION['user_id']) {
                    $new_paid = $fee['paid_amount'] + $fee['due_amount'];

                    if ($new_paid >= $fee['total_fee']) {
                        $status = 'Paid';
                    } elseif ($new_paid > 0) {
                        $status = 'Partial';
                    } else {
                        $status = 'Pending';
                    }

                    $stmt = db()->prepare("UPDATE fees SET paid_amount = ?, payment_mode = 'Online', receipt_no = ?, transaction_id = ?, payment_date = CURDATE(), status = ? WHERE id = ?");
                    $stmt->execute([$new_paid, $merchantTransactionId, 'PHONEPE_' . $transactionId, $status, $fee_id]);

                    $stmt = db()->prepare("INSERT INTO activity_log (user_id, action, description) VALUES (?, 'Fee Payment', ?)");
                    $stmt->execute([$_SESSION['user_id'], 'PhonePe payment of ₹' . number_format($fee['due_amount'], 2) . ' for fee #' . $fee_id]);

                    redirect(BASE_URL . '/student/fees.php?payment=success');
                }
            }
        }
    }
}

redirect(BASE_URL . '/student/fees.php?payment=failed');
