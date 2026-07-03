<?php
session_start();

define('BASE_URL', 'http://localhost/hostel');
define('SITE_NAME', 'Hostel Management System');

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hostel_db');

define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/hostel/uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');

date_default_timezone_set('Asia/Kolkata');

define('RAZORPAY_KEY_ID', 'rzp_test_YOUR_KEY_ID');
define('RAZORPAY_KEY_SECRET', 'YOUR_KEY_SECRET');

define('PHONEPE_MERCHANT_ID', 'PGTESTPAYUAT86');
define('PHONEPE_SALT_KEY', '96434309-7796-489d-8924-ab56988a6076');
define('PHONEPE_SALT_INDEX', 1);
define('PHONEPE_ENV', 'UAT'); // 'UAT' or 'PROD'
