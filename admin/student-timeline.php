<?php
$title = 'Student Timeline';
require_once __DIR__ . '/../includes/admin-header.php';

$student_id = (int)($_GET['student_id'] ?? 0);

$event_types_filter = sanitize($_GET['event_type'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

function getStudentInfo($id) {
    $stmt = db()->prepare(
        "SELECT s.*, r.room_no, r.room_type, ra.bed_no 
         FROM students s 
         LEFT JOIN room_allocations ra ON ra.student_id = s.id AND ra.status = 'Active' 
         LEFT JOIN rooms r ON r.id = ra.room_id 
         WHERE s.id = ?"
    );
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function collectTimelineEvents($student_id, $filter_type = '', $date_from = '', $date_to = '') {
    $events = [];

    $date_condition = '';
    $params = [$student_id];
    if ($date_from) {
        $date_condition .= " AND date >= ?";
        $params[] = $date_from;
    }
    if ($date_to) {
        $date_condition .= " AND date <= ?";
        $params[] = $date_to;
    }

    if (!$filter_type || $filter_type === 'admission') {
        $stmt = db()->prepare("SELECT id, admission_date AS event_date, 'admission' AS type, 'Student Admitted' AS title, CONCAT('Course: ', course, ' | Year: ', year) AS description, status FROM students WHERE id = ? AND admission_date IS NOT NULL");
        $stmt->execute([$student_id]);
        while ($r = $stmt->fetch()) {
            if ($r['event_date']) {
                if ($date_from && $r['event_date'] < $date_from) continue;
                if ($date_to && $r['event_date'] > $date_to) continue;
                $r['sort_date'] = $r['event_date'];
                $events[] = $r;
            }
        }

        $stmt = db()->prepare("SELECT id, join_date AS event_date, 'admission' AS type, 'Student Joined Hostel' AS title, 'Joined the hostel' AS description, status FROM students WHERE id = ? AND join_date IS NOT NULL");
        $stmt->execute([$student_id]);
        while ($r = $stmt->fetch()) {
            if ($r['event_date']) {
                if ($date_from && $r['event_date'] < $date_from) continue;
                if ($date_to && $r['event_date'] > $date_to) continue;
                $r['sort_date'] = $r['event_date'];
                $events[] = $r;
            }
        }
    }

    if (!$filter_type || $filter_type === 'room_allocation') {
        $stmt = db()->prepare(
            "SELECT ra.id, ra.allocation_date AS event_date, 'room_allocation' AS type, 'Room Allocated' AS title, 
             CONCAT('Room: ', r.room_no, ' | Bed: ', COALESCE(ra.bed_no, 'N/A')) AS description, ra.status 
             FROM room_allocations ra 
             JOIN rooms r ON r.id = ra.room_id 
             WHERE ra.student_id = ?$date_condition
             ORDER BY ra.allocation_date DESC"
        );
        $stmt->execute($params);
        while ($r = $stmt->fetch()) {
            $r['sort_date'] = $r['event_date'];
            $r['event_date'] .= ' 00:00:00';
            $events[] = $r;
        }

        $stmt = db()->prepare(
            "SELECT ra.id, ra.checkout_date AS event_date, 'room_allocation' AS type, 'Room Checked Out' AS title,
             CONCAT('Room: ', r.room_no) AS description, 'CheckedOut' AS status 
             FROM room_allocations ra 
             JOIN rooms r ON r.id = ra.room_id 
             WHERE ra.student_id = ? AND ra.checkout_date IS NOT NULL$date_condition
             ORDER BY ra.checkout_date DESC"
        );
        $checkout_params = [$student_id];
        if ($date_from) { $checkout_params[] = $date_from; }
        if ($date_to) { $checkout_params[] = $date_to; }
        $stmt->execute($checkout_params);
        while ($r = $stmt->fetch()) {
            $r['sort_date'] = $r['event_date'];
            $events[] = $r;
        }
    }

    if (!$filter_type || $filter_type === 'attendance') {
        $where = '';
        $att_params = [$student_id];
        if ($date_from) { $where .= " AND a.date >= ?"; $att_params[] = $date_from; }
        if ($date_to) { $where .= " AND a.date <= ?"; $att_params[] = $date_to; }
        $stmt = db()->prepare(
            "SELECT a.id, CONCAT(a.date, ' ', COALESCE(a.time, '00:00:00')) AS event_date, 'attendance' AS type, 
             CONCAT('Attendance: ', a.status) AS title, COALESCE(a.remarks, '') AS description, a.status 
             FROM attendance a WHERE a.student_id = ?$where
             ORDER BY a.date DESC LIMIT 50"
        );
        $stmt->execute($att_params);
        while ($r = $stmt->fetch()) {
            $r['sort_date'] = $r['event_date'];
            $events[] = $r;
        }
    }

    if (!$filter_type || $filter_type === 'leave') {
        $where = '';
        $leave_params = [$student_id];
        if ($date_from) { $where .= " AND l.applied_at >= ?"; $leave_params[] = $date_from . ' 00:00:00'; }
        if ($date_to) { $where .= " AND l.applied_at <= ?"; $leave_params[] = $date_to . ' 23:59:59'; }
        $stmt = db()->prepare(
            "SELECT l.id, l.applied_at AS event_date, 'leave' AS type, 
             CONCAT('Leave ', l.status) AS title, 
             CONCAT('From: ', l.from_date, ' To: ', COALESCE(l.to_date, 'N/A'), ' | Reason: ', LEFT(l.reason, 100)) AS description, 
             l.status FROM leaves l WHERE l.student_id = ?$where
             ORDER BY l.applied_at DESC"
        );
        $stmt->execute($leave_params);
        while ($r = $stmt->fetch()) {
            $r['sort_date'] = $r['event_date'];
            $events[] = $r;
        }
    }

    if (!$filter_type || $filter_type === 'complaint') {
        $where = '';
        $complaint_params = [$student_id];
        if ($date_from) { $where .= " AND c.created_at >= ?"; $complaint_params[] = $date_from . ' 00:00:00'; }
        if ($date_to) { $where .= " AND c.created_at <= ?"; $complaint_params[] = $date_to . ' 23:59:59'; }
        $stmt = db()->prepare(
            "SELECT c.id, c.created_at AS event_date, 'complaint' AS type, 
             CONCAT('Complaint: ', c.title) AS title, 
             CONCAT('Category: ', COALESCE(c.category, 'General'), ' | ', LEFT(c.description, 150)) AS description, 
             c.status FROM complaints c WHERE c.student_id = ?$where
             ORDER BY c.created_at DESC"
        );
        $stmt->execute($complaint_params);
        while ($r = $stmt->fetch()) {
            $r['sort_date'] = $r['event_date'];
            $events[] = $r;
        }
    }

    if (!$filter_type || $filter_type === 'visitor') {
        $where = '';
        $visitor_params = [$student_id];
        if ($date_from) { $where .= " AND v.check_in >= ?"; $visitor_params[] = $date_from . ' 00:00:00'; }
        if ($date_to) { $where .= " AND v.check_in <= ?"; $visitor_params[] = $date_to . ' 23:59:59'; }
        $stmt = db()->prepare(
            "SELECT v.id, v.check_in AS event_date, 'visitor' AS type, 
             CONCAT('Visitor: ', v.visitor_name) AS title, 
             CONCAT('Purpose: ', COALESCE(v.purpose, 'N/A'), ' | Contact: ', COALESCE(v.contact, 'N/A')) AS description, 
             COALESCE(v.status, 'Checked In') AS status 
             FROM visitor_logs v WHERE v.student_id = ?$where
             ORDER BY v.check_in DESC"
        );
        $stmt->execute($visitor_params);
        while ($r = $stmt->fetch()) {
            $r['sort_date'] = $r['event_date'];
            $events[] = $r;
        }
    }

    if (!$filter_type || $filter_type === 'medical') {
        $where = '';
        $med_params = [$student_id];
        if ($date_from) { $where .= " AND m.visit_date >= ?"; $med_params[] = $date_from; }
        if ($date_to) { $where .= " AND m.visit_date <= ?"; $med_params[] = $date_to; }
        $stmt = db()->prepare(
            "SELECT m.id, CONCAT(m.visit_date, ' 00:00:00') AS event_date, 'medical' AS type, 
             CONCAT('Medical Visit: ', COALESCE(m.disease, 'Checkup')) AS title, 
             CONCAT('Doctor: ', COALESCE(m.doctor, 'N/A'), ' | Hospital: ', COALESCE(m.hospital, 'N/A')) AS description, 
             'Info' AS status FROM medical_records m WHERE m.student_id = ?$where
             ORDER BY m.visit_date DESC"
        );
        $stmt->execute($med_params);
        while ($r = $stmt->fetch()) {
            $r['sort_date'] = $r['event_date'];
            $events[] = $r;
        }
    }

    if (!$filter_type || $filter_type === 'discipline') {
        $where = '';
        $disc_params = [$student_id];
        if ($date_from) { $where .= " AND d.created_at >= ?"; $disc_params[] = $date_from . ' 00:00:00'; }
        if ($date_to) { $where .= " AND d.created_at <= ?"; $disc_params[] = $date_to . ' 23:59:59'; }
        $stmt = db()->prepare(
            "SELECT d.id, d.created_at AS event_date, 'discipline' AS type, 
             CONCAT('Discipline: ', COALESCE(d.misconduct_type, 'Incident')) AS title, 
             CONCAT('Warning: ', d.warning_type, ' | Action: ', COALESCE(d.action_taken, 'N/A'), 
             CASE WHEN d.fine_amount > 0 THEN CONCAT(' | Fine: ₹', d.fine_amount) ELSE '' END) AS description, 
             d.warning_type AS status FROM discipline_records d WHERE d.student_id = ?$where
             ORDER BY d.created_at DESC"
        );
        $stmt->execute($disc_params);
        while ($r = $stmt->fetch()) {
            $r['sort_date'] = $r['event_date'];
            $events[] = $r;
        }
    }

    if (!$filter_type || $filter_type === 'fee') {
        $where = '';
        $fee_params = [$student_id];
        if ($date_from) { $where .= " AND f.payment_date >= ?"; $fee_params[] = $date_from; }
        if ($date_to) { $where .= " AND f.payment_date <= ?"; $fee_params[] = $date_to; }
        $stmt = db()->prepare(
            "SELECT f.id, CONCAT(f.payment_date, ' 00:00:00') AS event_date, 'fee' AS type, 
             CONCAT('Payment: ₹', f.paid_amount) AS title, 
             CONCAT('Mode: ', f.payment_mode, ' | Receipt: ', COALESCE(f.receipt_no, 'N/A')) AS description, 
             f.status FROM fees f WHERE f.student_id = ? AND f.payment_date IS NOT NULL$where
             ORDER BY f.payment_date DESC"
        );
        $stmt->execute($fee_params);
        while ($r = $stmt->fetch()) {
            $r['sort_date'] = $r['event_date'];
            $events[] = $r;
        }
    }

    if (!$filter_type || $filter_type === 'room_change') {
        $where = '';
        $rc_params = [$student_id];
        if ($date_from) { $where .= " AND rc.applied_at >= ?"; $rc_params[] = $date_from . ' 00:00:00'; }
        if ($date_to) { $where .= " AND rc.applied_at <= ?"; $rc_params[] = $date_to . ' 23:59:59'; }
        $stmt = db()->prepare(
            "SELECT rc.id, rc.applied_at AS event_date, 'room_change' AS type, 'Room Change Requested' AS title, 
             CONCAT('Status: ', rc.status, ' | Reason: ', LEFT(rc.reason, 100)) AS description, 
             rc.status FROM room_change_requests rc WHERE rc.student_id = ?$where
             ORDER BY rc.applied_at DESC"
        );
        $stmt->execute($rc_params);
        while ($r = $stmt->fetch()) {
            $r['sort_date'] = $r['event_date'];
            $events[] = $r;
        }
    }

    if (!$filter_type || $filter_type === 'vacate') {
        $where = '';
        $vac_params = [$student_id];
        if ($date_from) { $where .= " AND vr.applied_at >= ?"; $vac_params[] = $date_from . ' 00:00:00'; }
        if ($date_to) { $where .= " AND vr.applied_at <= ?"; $vac_params[] = $date_to . ' 23:59:59'; }
        $stmt = db()->prepare(
            "SELECT vr.id, vr.applied_at AS event_date, 'vacate' AS type, 'Vacate Requested' AS title, 
             CONCAT('Status: ', vr.status, ' | Reason: ', LEFT(vr.reason, 100)) AS description, 
             vr.status FROM vacate_requests vr WHERE vr.student_id = ?$where
             ORDER BY vr.applied_at DESC"
        );
        $stmt->execute($vac_params);
        while ($r = $stmt->fetch()) {
            $r['sort_date'] = $r['event_date'];
            $events[] = $r;
        }
    }

    if (!$filter_type || $filter_type === 'maintenance') {
        $where = '';
        $maint_params = [$student_id];
        if ($date_from) { $where .= " AND mr.created_at >= ?"; $maint_params[] = $date_from . ' 00:00:00'; }
        if ($date_to) { $where .= " AND mr.created_at <= ?"; $maint_params[] = $date_to . ' 23:59:59'; }
        $stmt = db()->prepare(
            "SELECT mr.id, mr.created_at AS event_date, 'maintenance' AS type, 
             CONCAT('Maintenance: ', mr.category) AS title, 
             CONCAT('Status: ', mr.status, ' | Priority: ', mr.priority, ' | ', LEFT(mr.description, 100)) AS description, 
             mr.status FROM maintenance_requests mr WHERE mr.student_id = ?$where
             ORDER BY mr.created_at DESC"
        );
        $stmt->execute($maint_params);
        while ($r = $stmt->fetch()) {
            $r['sort_date'] = $r['event_date'];
            $events[] = $r;
        }
    }

    usort($events, function ($a, $b) {
        return strtotime($b['sort_date']) - strtotime($a['sort_date']);
    });

    return $events;
}

function getTypeIcon($type) {
    $icons = [
        'admission'      => 'bi-person-plus-fill',
        'room_allocation' => 'bi-key-fill',
        'attendance'     => 'bi-calendar-check-fill',
        'leave'          => 'bi-box-arrow-right',
        'complaint'      => 'bi-exclamation-triangle-fill',
        'visitor'        => 'bi-person-badge-fill',
        'medical'        => 'bi-heart-pulse-fill',
        'discipline'     => 'bi-shield-exclamation-fill',
        'fee'            => 'bi-cash-coin',
        'room_change'    => 'bi-arrow-left-right',
        'vacate'         => 'bi-house-x-fill',
        'maintenance'    => 'bi-tools',
    ];
    return $icons[$type] ?? 'bi-circle-fill';
}

function getTypeColor($type) {
    $colors = [
        'admission'      => '#0d6efd',
        'room_allocation' => '#6610f2',
        'attendance'     => '#198754',
        'leave'          => '#fd7e14',
        'complaint'      => '#dc3545',
        'visitor'        => '#0dcaf0',
        'medical'        => '#d63384',
        'discipline'     => '#ffc107',
        'fee'            => '#20c997',
        'room_change'    => '#6f42c1',
        'vacate'         => '#212529',
        'maintenance'    => '#6c757d',
    ];
    return $colors[$type] ?? '#0d6efd';
}

function getStatusBadge($status) {
    $map = [
        'Active'     => 'success',
        'Inactive'   => 'secondary',
        'Pending'    => 'warning',
        'Approved'   => 'success',
        'Rejected'   => 'danger',
        'Resolved'   => 'success',
        'Working'    => 'info',
        'Present'    => 'success',
        'Absent'     => 'danger',
        'Late'       => 'warning',
        'CheckedOut' => 'secondary',
        'Paid'       => 'success',
        'Partial'    => 'warning',
        'Verbal'     => 'secondary',
        'Written'    => 'warning',
        'Final'      => 'danger',
        'Fine'       => 'danger',
        'Checked In' => 'info',
        'Assigned'   => 'info',
        'In Progress' => 'primary',
        'Closed'     => 'secondary',
        'On Leave'   => 'info',
        'Medical Leave' => 'danger',
        'Outside Hostel' => 'dark',
        'Info'       => 'info',
    ];
    $class = $map[$status] ?? 'primary';
    return "<span class=\"badge bg-$class\">" . escapeOutput($status) . "</span>";
}

$student = null;
$events = [];
if ($student_id) {
    $student = getStudentInfo($student_id);
    if ($student) {
        $events = collectTimelineEvents($student_id, $event_types_filter, $date_from, $date_to);
    }
}

$event_types = [
    ''              => 'All Events',
    'admission'     => 'Admission',
    'room_allocation' => 'Room Allocation',
    'attendance'    => 'Attendance',
    'leave'         => 'Leaves',
    'complaint'     => 'Complaints',
    'visitor'       => 'Visitors',
    'medical'       => 'Medical',
    'discipline'    => 'Discipline',
    'fee'           => 'Fees/Payments',
    'room_change'   => 'Room Changes',
    'vacate'        => 'Vacate Requests',
    'maintenance'   => 'Maintenance',
];
?>

<style>
.timeline {
    position: relative;
    padding: 0;
    list-style: none;
}
.timeline::before {
    content: '';
    position: absolute;
    left: 28px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}
.timeline-item {
    position: relative;
    padding-left: 70px;
    margin-bottom: 24px;
}
.timeline-icon {
    position: absolute;
    left: 15px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 14px;
    z-index: 1;
    box-shadow: 0 0 0 3px #fff;
}
.timeline-content {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 14px 18px;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
    transition: box-shadow .15s;
}
.timeline-content:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,.1);
}
.timeline-date {
    font-size: .78rem;
    color: #6c757d;
    margin-bottom: 2px;
}
.timeline-title {
    font-weight: 600;
    font-size: .95rem;
    margin-bottom: 4px;
}
.timeline-desc {
    font-size: .85rem;
    color: #495057;
}
.month-group {
    position: relative;
    padding-left: 70px;
    margin-bottom: 8px;
    margin-top: 20px;
}
.month-label {
    display: inline-block;
    background: #e9ecef;
    padding: 2px 14px;
    border-radius: 12px;
    font-size: .82rem;
    font-weight: 600;
    color: #495057;
}
.student-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 28px;
}
.student-card .photo {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(255,255,255,.3);
}
.student-card .photo-placeholder {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: rgba(255,255,255,.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    border: 3px solid rgba(255,255,255,.3);
}
.student-card .info h5 {
    margin-bottom: 2px;
    font-weight: 700;
}
.student-card .info small {
    opacity: .85;
}
.filter-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 16px 20px;
    margin-bottom: 24px;
}
</style>

<div class="container-fluid">

<?php if (!$student_id): ?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h5 class="mb-3"><i class="bi bi-search"></i> Select Student</h5>
                <form method="get" class="mb-3">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search by name, roll number, or phone..." value="<?= sanitize($_GET['search'] ?? '') ?>">
                        <button class="btn btn-primary"><i class="bi bi-search"></i> Search</button>
                    </div>
                </form>
                <?php
                $search = sanitize($_GET['search'] ?? '');
                $students = [];
                if ($search) {
                    $stmt = db()->prepare(
                        "SELECT s.id, s.name, s.roll_no, s.phone, s.course, s.year, s.photo, s.status, r.room_no 
                         FROM students s 
                         LEFT JOIN room_allocations ra ON ra.student_id = s.id AND ra.status = 'Active' 
                         LEFT JOIN rooms r ON r.id = ra.room_id 
                         WHERE s.name LIKE ? OR s.roll_no LIKE ? OR s.phone LIKE ?
                         ORDER BY s.name LIMIT 20"
                    );
                    $stmt->execute(["%$search%", "%$search%", "%$search%"]);
                    $students = $stmt->fetchAll();
                } else {
                    $stmt = db()->query(
                        "SELECT s.id, s.name, s.roll_no, s.phone, s.course, s.year, s.photo, s.status, r.room_no 
                         FROM students s 
                         LEFT JOIN room_allocations ra ON ra.student_id = s.id AND ra.status = 'Active' 
                         LEFT JOIN rooms r ON r.id = ra.room_id 
                         ORDER BY s.name LIMIT 20"
                    );
                    $students = $stmt->fetchAll();
                }
                ?>
                <?php if ($search && empty($students)): ?>
                    <div class="alert alert-info">No students found matching "<strong><?= sanitize($search) ?></strong>"</div>
                <?php endif; ?>
                <div class="list-group">
                    <?php foreach ($students as $s): ?>
                        <a href="?student_id=<?= $s['id'] ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                            <?php if ($s['photo']): ?>
                                <img src="<?= BASE_URL ?>/uploads/<?= $s['photo'] ?>" class="rounded-circle" width="44" height="44" style="object-fit:cover">
                            <?php else: ?>
                                <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" style="width:44px;height:44px;color:#fff;font-weight:600;font-size:18px"><?= strtoupper(substr($s['name'], 0, 1)) ?></div>
                            <?php endif; ?>
                            <div class="flex-grow-1">
                                <strong><?= sanitize($s['name']) ?></strong>
                                <br><small class="text-muted"><?= sanitize($s['roll_no']) ?> | <?= sanitize($s['course']) ?> <?= $s['year'] ? ' - '.sanitize($s['year']) : '' ?></small>
                            </div>
                            <span class="badge bg-<?= $s['status'] === 'Active' ? 'success' : 'secondary' ?>"><?= $s['status'] ?></span>
                            <span class="badge bg-info"><?= sanitize($s['room_no'] ?? 'N/A') ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif (!$student): ?>

<div class="alert alert-danger">Student not found.</div>
<a href="?" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Search</a>

<?php else: ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <a href="?" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Search</a>
        <a href="?student_id=<?= $student_id ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-clockwise"></i> Refresh</a>
    </div>
</div>

<div class="student-card">
    <div class="d-flex align-items-center gap-4 flex-wrap">
        <?php if ($student['photo']): ?>
            <img src="<?= BASE_URL ?>/uploads/<?= $student['photo'] ?>" class="photo" alt="Photo">
        <?php else: ?>
            <div class="photo-placeholder"><i class="bi bi-person"></i></div>
        <?php endif; ?>
        <div class="info flex-grow-1">
            <h5><?= sanitize($student['name']) ?></h5>
            <small>
                <?= sanitize($student['roll_no']) ?> &bull;
                <?= sanitize($student['course']) ?> <?= $student['year'] ? '- '.sanitize($student['year']) : '' ?> &bull;
                Room: <?= sanitize($student['room_no'] ?? 'N/A') ?> &bull;
                <?= $student['status'] ?>
            </small>
            <div class="mt-1 d-flex gap-2 flex-wrap">
                <span class="badge bg-light text-dark"><i class="bi bi-envelope"></i> <?= sanitize($student['email']) ?></span>
                <span class="badge bg-light text-dark"><i class="bi bi-telephone"></i> <?= sanitize($student['phone']) ?></span>
                <?php if ($student['guardian_name']): ?>
                    <span class="badge bg-light text-dark"><i class="bi bi-person-lines-fill"></i> <?= sanitize($student['guardian_name']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="text-end">
            <a href="<?= BASE_URL ?>/admin/students.php?action=view&id=<?= $student_id ?>" class="btn btn-sm btn-light" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Full Profile</a>
        </div>
    </div>
</div>

<div class="filter-card">
    <form method="get" class="row g-2 align-items-end">
        <input type="hidden" name="student_id" value="<?= $student_id ?>">
        <div class="col-auto">
            <label class="form-label small mb-1">Event Type</label>
            <select name="event_type" class="form-select form-select-sm">
                <?php foreach ($event_types as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $event_types_filter === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label small mb-1">From Date</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= $date_from ?>">
        </div>
        <div class="col-auto">
            <label class="form-label small mb-1">To Date</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= $date_to ?>">
        </div>
        <div class="col-auto">
            <button class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filter</button>
            <a href="?student_id=<?= $student_id ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle"></i> Clear</a>
        </div>
        <div class="col-auto ms-auto">
            <span class="text-muted small"><?= count($events) ?> event<?= count($events) !== 1 ? 's' : '' ?> found</span>
        </div>
    </form>
</div>

<?php if (empty($events)): ?>
    <div class="alert alert-info"><i class="bi bi-info-circle"></i> No timeline events found for this student.</div>
<?php else: ?>
    <?php
    $current_month = '';
    ?>
    <ul class="timeline">
        <?php foreach ($events as $event):
            $ts = strtotime($event['sort_date']);
            $month_key = date('Y-m', $ts);
            if ($month_key !== $current_month):
                $current_month = $month_key;
                $month_label = date('F Y', $ts);
            ?>
            <li class="month-group">
                <span class="month-label"><?= $month_label ?></span>
            </li>
            <?php endif; ?>
        <li class="timeline-item">
            <div class="timeline-icon" style="background:<?= getTypeColor($event['type']) ?>">
                <i class="bi <?= getTypeIcon($event['type']) ?>"></i>
            </div>
            <div class="timeline-content">
                <div class="timeline-date">
                    <i class="bi bi-clock"></i> <?= date('d M Y, h:i A', $ts) ?>
                </div>
                <div class="timeline-title"><?= escapeOutput($event['title']) ?></div>
                <?php if ($event['description']): ?>
                    <div class="timeline-desc"><?= escapeOutput($event['description']) ?></div>
                <?php endif; ?>
                <?php if (!empty($event['status'])): ?>
                    <div class="mt-1"><?= getStatusBadge($event['status']) ?></div>
                <?php endif; ?>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
