<?php
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

function requireCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCSRFToken($token)) {
            setAlert('danger', 'Security token validation failed. Please try again.');
            redirect($_SERVER['HTTP_REFERER'] ?? BASE_URL);
        }
    }
}

function validatePassword($password) {
    $errors = [];
    if (strlen($password) < 8) $errors[] = 'At least 8 characters';
    if (!preg_match('/[A-Z]/', $password)) $errors[] = 'One uppercase letter';
    if (!preg_match('/[a-z]/', $password)) $errors[] = 'One lowercase letter';
    if (!preg_match('/[0-9]/', $password)) $errors[] = 'One number';
    if (!preg_match('/[^A-Za-z0-9]/', $password)) $errors[] = 'One special character';
    return $errors;
}

function secureSession() {
    if (isset($_SESSION['last_regeneration']) && (time() - $_SESSION['last_regeneration'] > 300)) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
        session_destroy();
        redirect(BASE_URL . '/auth/login.php?expired=1');
    }
    $_SESSION['last_activity'] = time();
}

function checkLoginAttempts($username) {
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM login_attempts 
         WHERE username = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
    );
    $stmt->execute([$username]);
    return $stmt->fetchColumn() >= 5;
}

function recordLoginAttempt($username) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = db()->prepare("INSERT INTO login_attempts (username, ip_address) VALUES (?, ?)");
    $stmt->execute([$username, $ip]);
}

function clearLoginAttempts($username) {
    $stmt = db()->prepare("DELETE FROM login_attempts WHERE username = ?");
    $stmt->execute([$username]);
}

function escapeOutput($value) {
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
}

function sendJSON($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function rateLimit($key, $maxRequests = 60, $period = 60) {
    $file = sys_get_temp_dir() . '/ratelimit_' . md5($key);
    $data = @file_get_contents($file);
    $data = $data ? json_decode($data, true) : ['count' => 0, 'reset' => time() + $period];
    if (time() > $data['reset']) {
        $data = ['count' => 0, 'reset' => time() + $period];
    }
    $data['count']++;
    file_put_contents($file, json_encode($data), LOCK_EX);
    return $data['count'] <= $maxRequests;
}

function addNotification($userId, $title, $message, $type = 'info', $link = null, $module = null) {
    $stmt = db()->prepare(
        "INSERT INTO notifications (user_id, title, message, type, link, module) VALUES (?, ?, ?, ?, ?, ?)"
    );
    return $stmt->execute([$userId, $title, $message, $type, $link, $module]);
}

function getUnreadNotificationsByModule($userId, $module = null) {
    if ($module) {
        $stmt = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0 AND module = ?");
        $stmt->execute([$userId, $module]);
    } else {
        $stmt = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
    }
    return $stmt->fetchColumn();
}

function getUnreadNotificationCount($userId) {
    $stmt = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

function getNotifications($userId, $limit = 10) {
    $stmt = db()->prepare(
        "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?"
    );
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

function auditLog($action, $module = null, $description = null) {
    $hostelType = '';
    if (isWarden()) {
        $hostelType = getWardenHostelType();
        $hostelType = $hostelType ? " [Hostel: $hostelType]" : '';
    }
    $stmt = db()->prepare(
        "INSERT INTO audit_logs (user_id, username, role, action, module, description, ip_address, user_agent) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $_SESSION['user_id'] ?? null,
        $_SESSION['username'] ?? 'system',
        $_SESSION['role'] ?? 'system',
        $action,
        $module,
        ($description ?? '') . $hostelType,
        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
    ]);
}

function logComplaintAction($complaintId, $action, $remarks = null) {
    $stmt = db()->prepare(
        "INSERT INTO complaint_logs (complaint_id, action, performed_by, role, remarks) 
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $complaintId,
        $action,
        $_SESSION['user_id'] ?? null,
        $_SESSION['role'] ?? null,
        $remarks
    ]);
}

function notifyNewComplaint($complaintId, $studentName, $title) {
    $stmt = db()->prepare("SELECT id FROM users WHERE role IN ('admin','warden') AND status=1");
    $stmt->execute();
    while ($user = $stmt->fetch()) {
        addNotification($user['id'], 'New Complaint', "{$studentName} submitted: {$title}", 'warning', BASE_URL . '/admin/complaints.php?action=view&id=' . $complaintId, 'complaints');
    }
}

function notifyLeaveRequest($leaveId, $studentName) {
    $stmt = db()->prepare("SELECT id FROM users WHERE role IN ('admin','warden') AND status=1");
    $stmt->execute();
    while ($user = $stmt->fetch()) {
        addNotification($user['id'], 'Leave Request', "{$studentName} applied for leave", 'info', BASE_URL . '/warden/leaves.php?action=review&id=' . $leaveId, 'leaves');
    }
}

function notifyLeaveApproved($userId, $leaveId, $status) {
    addNotification($userId, "Leave {$status}", "Your leave request #{$leaveId} has been {$status}.", $status === 'Approved' ? 'success' : 'danger', BASE_URL . '/student/leave.php', 'leaves');
}

function notifyEmergency($emergencyId, $reporter, $category) {
    $stmt = db()->prepare("SELECT id FROM users WHERE role='admin' AND status=1");
    $stmt->execute();
    while ($user = $stmt->fetch()) {
        addNotification($user['id'], "EMERGENCY: {$category}", "Reported by: {$reporter}", 'danger', BASE_URL . '/admin/emergency.php?action=view&id=' . $emergencyId, 'emergency');
    }
}

function notifyMaintenanceRequest($requestId, $studentName) {
    $stmt = db()->prepare("SELECT id FROM users WHERE role IN ('admin','warden') AND status=1");
    $stmt->execute();
    while ($user = $stmt->fetch()) {
        addNotification($user['id'], 'Maintenance Request', "{$studentName} requested maintenance", 'info', BASE_URL . '/admin/maintenance.php?action=view&id=' . $requestId, 'maintenance');
    }
}

function notifyMaintenanceResolved($userId, $requestId) {
    addNotification($userId, 'Maintenance Completed', "Your maintenance request #{$requestId} has been resolved.", 'success', BASE_URL . '/student/maintenance.php', 'maintenance');
}

function notifyComplaintResolved($userId, $complaintId) {
    addNotification($userId, 'Complaint Resolved', "Your complaint #{$complaintId} has been resolved.", 'success', BASE_URL . '/student/complaints.php', 'complaints');
}

function notifyAttendanceMissing($date) {
    $stmt = db()->prepare("SELECT id FROM users WHERE role='admin' AND status=1");
    $stmt->execute();
    while ($user = $stmt->fetch()) {
        addNotification($user['id'], 'Attendance Missing', "Attendance not submitted for {$date}", 'warning', BASE_URL . '/admin/attendance.php', 'attendance');
    }
}

function notifyEscalation($module, $itemId, $reason) {
    $stmt = db()->prepare("SELECT id FROM users WHERE role='admin' AND status=1");
    $stmt->execute();
    while ($user = $stmt->fetch()) {
        addNotification($user['id'], "Escalated: {$module}", "Reason: {$reason}", 'danger', BASE_URL . "/admin/{$module}.php?action=view&id={$itemId}", 'escalation');
    }
}

function notifyWardenNewVisitor() {
    $stmt = db()->prepare("SELECT id FROM users WHERE role='warden' AND status=1");
    $stmt->execute();
    while ($user = $stmt->fetch()) {
        addNotification($user['id'], 'Visitor Waiting', 'A visitor is waiting at the gate.', 'info', BASE_URL . '/warden/visitors.php', 'visitors');
    }
}

// Ensure notifications table has module column
try { db()->query("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS `module` varchar(50) DEFAULT NULL AFTER `link`"); } catch (Exception $e) {}
