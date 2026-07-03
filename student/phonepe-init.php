<?php
require_once __DIR__ . '/../includes/session.php';

if (!isLoggedIn() || !isStudent()) {
    redirect(BASE_URL . '/auth/login.php');
}

$fee_id = (int)($_GET['fee_id'] ?? 0);
if (!$fee_id) {
    redirect(BASE_URL . '/student/fees.php');
}

$stmt = db()->prepare("SELECT f.*, s.user_id, s.name, s.phone FROM fees f JOIN students s ON s.id = f.student_id WHERE f.id = ?");
$stmt->execute([$fee_id]);
$fee = $stmt->fetch();

if (!$fee || $fee['user_id'] != $_SESSION['user_id']) {
    redirect(BASE_URL . '/student/fees.php');
}

$amount = (float)$fee['due_amount'];
if ($amount <= 0) {
    redirect(BASE_URL . '/student/fees.php');
}

$merchantTransactionId = 'FEE' . $fee_id . '_' . uniqid();
$amountPaise = round($amount * 100);

$data = [
    'merchantId' => PHONEPE_MERCHANT_ID,
    'merchantTransactionId' => $merchantTransactionId,
    'merchantUserId' => 'USR' . $_SESSION['user_id'],
    'amount' => $amountPaise,
    'redirectUrl' => BASE_URL . '/student/phonepe-callback.php',
    'redirectMode' => 'REDIRECT',
    'callbackUrl' => BASE_URL . '/student/phonepe-callback.php',
    'mobileNumber' => $fee['phone'],
    'paymentInstrument' => ['type' => 'PAY_PAGE'],
];

$jsonData = json_encode($data);
$base64Data = base64_encode($jsonData);

$endpoint = '/pg/v1/pay';
$checksum = hash('sha256', $base64Data . $endpoint . PHONEPE_SALT_KEY) . '###' . PHONEPE_SALT_INDEX;

$base = PHONEPE_ENV === 'UAT'
    ? 'https://api-preprod.phonepe.com/apis/pg-sandbox'
    : 'https://api.phonepe.com/apis/hermes';
$apiUrl = $base . $endpoint;

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['request' => $base64Data]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-VERIFY: ' . $checksum,
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200) {
    $msg = $curlErr ?: "HTTP $httpCode from PhonePe";
    setAlert('danger', 'PhonePe connection failed: ' . $msg);
    redirect(BASE_URL . '/student/fees.php');
}

$result = json_decode($response, true);

if (!$result || !($result['success'] ?? false)) {
    setAlert('danger', 'PhonePe payment initiation failed: ' . ($result['message'] ?? 'Unknown error'));
    redirect(BASE_URL . '/student/fees.php');
}

$_SESSION['phonepe_txn'] = [
    'fee_id' => $fee_id,
    'merchantTransactionId' => $merchantTransactionId,
    'amount' => $amount,
];

$redirectUrl = $result['data']['instrumentResponse']['redirectInfo']['url'] ?? '';
if (!$redirectUrl) {
    setAlert('danger', 'No redirect URL from PhonePe.');
    redirect(BASE_URL . '/student/fees.php');
}

redirect($redirectUrl);
