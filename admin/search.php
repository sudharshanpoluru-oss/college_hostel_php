<?php
$title = 'Global Search';
require_once __DIR__ . '/../includes/admin-header.php';

$q = sanitize($_GET['q'] ?? '');
$hasQuery = $q !== '';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-lg-8 mx-auto">
            <form method="get" class="position-relative">
                <div class="input-group input-group-lg shadow-sm">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control border-start-0 ps-0" placeholder="Search students, rooms, visitors, complaints, leaves, fees, wardens..." value="<?= sanitize($q) ?>" autofocus>
                    <?php if ($hasQuery): ?>
                    <a href="<?= BASE_URL ?>/admin/search.php" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                    <button class="btn btn-primary px-4">Search</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!$hasQuery): ?>
    <div class="text-center py-5">
        <i class="bi bi-search text-muted" style="font-size:4rem"></i>
        <h5 class="text-muted mt-3">Start typing to search...</h5>
        <p class="text-muted">Search across students, rooms, visitors, complaints, leaves, fees, and wardens</p>
    </div>
    <?php else:
        $like = "%$q%";

        // ---- 1. Students ----
        $stmt = db()->prepare("SELECT id, name, roll_no, email, phone, course, status, photo FROM students WHERE name LIKE ? OR roll_no LIKE ? OR email LIKE ? OR phone LIKE ? LIMIT 5");
        $stmt->execute([$like, $like, $like, $like]);
        $students = $stmt->fetchAll();

        $stmt = db()->prepare("SELECT COUNT(*) FROM students WHERE name LIKE ? OR roll_no LIKE ? OR email LIKE ? OR phone LIKE ?");
        $stmt->execute([$like, $like, $like, $like]);
        $studentsTotal = $stmt->fetchColumn();

        // ---- 2. Rooms ----
        $stmt = db()->prepare("SELECT id, room_no, floor, room_type, capacity, occupancy, fee_per_month, status FROM rooms WHERE room_no LIKE ? LIMIT 5");
        $stmt->execute([$like]);
        $rooms = $stmt->fetchAll();

        $stmt = db()->prepare("SELECT COUNT(*) FROM rooms WHERE room_no LIKE ?");
        $stmt->execute([$like]);
        $roomsTotal = $stmt->fetchColumn();

        // ---- 3. Visitors ----
        $stmt = db()->prepare("SELECT v.id, v.visitor_name, v.contact, v.purpose, v.check_in, v.check_out, v.status, s.name AS student_name FROM visitor_logs v LEFT JOIN students s ON s.id = v.student_id WHERE v.visitor_name LIKE ? OR v.contact LIKE ? LIMIT 5");
        $stmt->execute([$like, $like]);
        $visitors = $stmt->fetchAll();

        $stmt = db()->prepare("SELECT COUNT(*) FROM visitor_logs WHERE visitor_name LIKE ? OR contact LIKE ?");
        $stmt->execute([$like, $like]);
        $visitorsTotal = $stmt->fetchColumn();

        // ---- 4. Complaints ----
        $stmt = db()->prepare("SELECT c.id, c.title, c.description, c.status, c.priority, c.created_at, s.name AS student_name FROM complaints c JOIN students s ON s.id = c.student_id WHERE c.title LIKE ? OR c.description LIKE ? LIMIT 5");
        $stmt->execute([$like, $like]);
        $complaints = $stmt->fetchAll();

        $stmt = db()->prepare("SELECT COUNT(*) FROM complaints WHERE title LIKE ? OR description LIKE ?");
        $stmt->execute([$like, $like]);
        $complaintsTotal = $stmt->fetchColumn();

        // ---- 5. Leaves ----
        $stmt = db()->prepare("SELECT l.id, l.from_date, l.to_date, l.reason, l.status, l.applied_at, s.name AS student_name, s.roll_no FROM leaves l JOIN students s ON s.id = l.student_id WHERE s.name LIKE ? LIMIT 5");
        $stmt->execute([$like]);
        $leaves = $stmt->fetchAll();

        $stmt = db()->prepare("SELECT COUNT(*) FROM leaves l JOIN students s ON s.id = l.student_id WHERE s.name LIKE ?");
        $stmt->execute([$like]);
        $leavesTotal = $stmt->fetchColumn();

        // ---- 6. Fees ----
        $stmt = db()->prepare("SELECT f.id, f.total_fee, f.paid_amount, f.due_amount, f.payment_mode, f.receipt_no, f.transaction_id, f.payment_date, f.status, s.name AS student_name, s.roll_no FROM fees f JOIN students s ON s.id = f.student_id WHERE s.name LIKE ? LIMIT 5");
        $stmt->execute([$like]);
        $fees = $stmt->fetchAll();

        $stmt = db()->prepare("SELECT COUNT(*) FROM fees f JOIN students s ON s.id = f.student_id WHERE s.name LIKE ?");
        $stmt->execute([$like]);
        $feesTotal = $stmt->fetchColumn();

        // ---- 7. Wardens ----
        $stmt = db()->prepare("SELECT w.id, w.name, w.phone, w.email, w.shift, w.hostel_type, w.status, u.username FROM wardens w JOIN users u ON u.id = w.user_id WHERE w.name LIKE ? OR u.username LIKE ? LIMIT 5");
        $stmt->execute([$like, $like]);
        $wardens = $stmt->fetchAll();

        $stmt = db()->prepare("SELECT COUNT(*) FROM wardens w JOIN users u ON u.id = w.user_id WHERE w.name LIKE ? OR u.username LIKE ?");
        $stmt->execute([$like, $like]);
        $wardensTotal = $stmt->fetchColumn();
    ?>

    <div class="row g-4">
        <?php if ($studentsTotal + $roomsTotal + $visitorsTotal + $complaintsTotal + $leavesTotal + $feesTotal + $wardensTotal === 0): ?>
        <div class="col-12">
            <div class="text-center py-5">
                <i class="bi bi-exclamation-circle text-muted" style="font-size:3rem"></i>
                <h5 class="text-muted mt-3">No results found for "<?= sanitize($q) ?>"</h5>
                <p class="text-muted">Try different keywords or check your spelling</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Students -->
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <strong><i class="bi bi-people text-primary"></i> Students <span class="badge bg-primary rounded-pill"><?= $studentsTotal ?></span></strong>
                    <a href="<?= BASE_URL ?>/admin/students.php<?= $q ? '?search=' . urlencode($q) : '' ?>" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if ($students): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($students as $r): ?>
                        <a href="<?= BASE_URL ?>/admin/students.php?action=view&id=<?= $r['id'] ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width:40px;height:40px">
                                    <?php if ($r['photo']): ?>
                                    <img src="<?= BASE_URL ?>/uploads/<?= $r['photo'] ?>" class="rounded-circle" style="width:40px;height:40px;object-fit:cover">
                                    <?php else: ?>
                                    <i class="bi bi-person text-secondary"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-truncate"><?= sanitize($r['name']) ?></div>
                                <small class="text-muted"><?= sanitize($r['roll_no']) ?> &middot; <?= sanitize($r['course'] ?: 'N/A') ?></small>
                            </div>
                            <span class="badge bg-<?= $r['status'] === 'Active' ? 'success' : 'secondary' ?> flex-shrink-0"><?= $r['status'] ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4 text-muted"><i class="bi bi-search me-1"></i>No results found</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Rooms -->
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <strong><i class="bi bi-door-open text-success"></i> Rooms <span class="badge bg-success rounded-pill"><?= $roomsTotal ?></span></strong>
                    <a href="<?= BASE_URL ?>/admin/rooms.php<?= $q ? '?search=' . urlencode($q) : '' ?>" class="btn btn-sm btn-outline-success">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if ($rooms): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($rooms as $r): ?>
                        <a href="<?= BASE_URL ?>/admin/rooms.php?action=view&id=<?= $r['id'] ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                            <div class="flex-shrink-0">
                                <div class="rounded bg-light d-flex align-items-center justify-content-center" style="width:40px;height:40px">
                                    <i class="bi bi-door-open text-success"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold">Room <?= sanitize($r['room_no']) ?> <small class="text-muted">(<?= $r['room_type'] ?>)</small></div>
                                <small class="text-muted"><?= sanitize($r['floor']) ?> Floor &middot; ₹<?= number_format($r['fee_per_month']) ?>/mo</small>
                            </div>
                            <span class="badge bg-<?= $r['status'] === 'Available' ? 'success' : ($r['status'] === 'Full' ? 'warning' : 'danger') ?> flex-shrink-0"><?= $r['status'] ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4 text-muted"><i class="bi bi-search me-1"></i>No results found</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Visitors -->
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <strong><i class="bi bi-person-badge text-warning"></i> Visitors <span class="badge bg-warning text-dark rounded-pill"><?= $visitorsTotal ?></span></strong>
                    <a href="<?= BASE_URL ?>/admin/visitors.php<?= $q ? '?search=' . urlencode($q) : '' ?>" class="btn btn-sm btn-outline-warning">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if ($visitors): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($visitors as $r): ?>
                        <a href="<?= BASE_URL ?>/admin/visitors.php?action=view&id=<?= $r['id'] ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                            <div class="flex-shrink-0">
                                <div class="rounded bg-light d-flex align-items-center justify-content-center" style="width:40px;height:40px">
                                    <i class="bi bi-person-badge text-warning"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-truncate"><?= sanitize($r['visitor_name']) ?></div>
                                <small class="text-muted"><?= sanitize($r['contact'] ?: '') ?> <?= $r['student_name'] ? '&middot; ' . sanitize($r['student_name']) : '' ?></small>
                            </div>
                            <span class="badge bg-<?= $r['check_out'] ? 'secondary' : 'success' ?> flex-shrink-0"><?= $r['check_out'] ? 'Out' : 'In' ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4 text-muted"><i class="bi bi-search me-1"></i>No results found</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Complaints -->
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <strong><i class="bi bi-exclamation-triangle text-danger"></i> Complaints <span class="badge bg-danger rounded-pill"><?= $complaintsTotal ?></span></strong>
                    <a href="<?= BASE_URL ?>/admin/complaints.php<?= $q ? '?search=' . urlencode($q) : '' ?>" class="btn btn-sm btn-outline-danger">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if ($complaints): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($complaints as $r): ?>
                        <a href="<?= BASE_URL ?>/admin/complaints.php?action=view&id=<?= $r['id'] ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                            <div class="flex-shrink-0">
                                <div class="rounded bg-light d-flex align-items-center justify-content-center" style="width:40px;height:40px">
                                    <i class="bi bi-exclamation-triangle text-danger"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-truncate"><?= sanitize($r['title']) ?></div>
                                <small class="text-muted"><?= sanitize($r['student_name']) ?> &middot; <?= date('d M Y', strtotime($r['created_at'])) ?></small>
                            </div>
                            <span class="badge bg-<?= $r['status'] === 'Resolved' ? 'success' : ($r['status'] === 'Pending' ? 'warning' : 'info') ?> flex-shrink-0"><?= $r['status'] ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4 text-muted"><i class="bi bi-search me-1"></i>No results found</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Leaves -->
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <strong><i class="bi bi-box-arrow-right text-info"></i> Leaves <span class="badge bg-info text-dark rounded-pill"><?= $leavesTotal ?></span></strong>
                    <a href="<?= BASE_URL ?>/admin/leaves.php" class="btn btn-sm btn-outline-info">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if ($leaves): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($leaves as $r): ?>
                        <a href="<?= BASE_URL ?>/admin/leaves.php?action=review&id=<?= $r['id'] ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                            <div class="flex-shrink-0">
                                <div class="rounded bg-light d-flex align-items-center justify-content-center" style="width:40px;height:40px">
                                    <i class="bi bi-box-arrow-right text-info"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-truncate"><?= sanitize($r['student_name']) ?> <small class="text-muted">(<?= sanitize($r['roll_no']) ?>)</small></div>
                                <small class="text-muted"><?= $r['from_date'] ?> to <?= $r['to_date'] ?></small>
                            </div>
                            <span class="badge bg-<?= $r['status'] === 'Approved' ? 'success' : ($r['status'] === 'Rejected' ? 'danger' : 'warning') ?> flex-shrink-0"><?= $r['status'] ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4 text-muted"><i class="bi bi-search me-1"></i>No results found</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Fees -->
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <strong><i class="bi bi-cash-coin text-success"></i> Fees/Payments <span class="badge bg-success rounded-pill"><?= $feesTotal ?></span></strong>
                    <a href="<?= BASE_URL ?>/admin/fees.php" class="btn btn-sm btn-outline-success">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if ($fees): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($fees as $r): ?>
                        <a href="<?= BASE_URL ?>/admin/fees.php?action=edit&id=<?= $r['id'] ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                            <div class="flex-shrink-0">
                                <div class="rounded bg-light d-flex align-items-center justify-content-center" style="width:40px;height:40px">
                                    <i class="bi bi-cash-coin text-success"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-truncate"><?= sanitize($r['student_name']) ?> <small class="text-muted">(<?= sanitize($r['roll_no']) ?>)</small></div>
                                <small class="text-muted">₹<?= number_format($r['paid_amount'], 2) ?> paid of ₹<?= number_format($r['total_fee'], 2) ?></small>
                            </div>
                            <span class="badge bg-<?= $r['status'] === 'Paid' ? 'success' : ($r['status'] === 'Partial' ? 'warning' : 'secondary') ?> flex-shrink-0"><?= $r['status'] ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4 text-muted"><i class="bi bi-search me-1"></i>No results found</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Wardens -->
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <strong><i class="bi bi-shield-check text-secondary"></i> Wardens <span class="badge bg-secondary rounded-pill"><?= $wardensTotal ?></span></strong>
                    <a href="<?= BASE_URL ?>/admin/wardens.php" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if ($wardens): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($wardens as $r): ?>
                        <a href="<?= BASE_URL ?>/admin/wardens.php?action=edit&id=<?= $r['id'] ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width:40px;height:40px">
                                    <i class="bi bi-shield-check text-secondary"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold"><?= sanitize($r['name']) ?></div>
                                <small class="text-muted">@<?= sanitize($r['username']) ?> &middot; <?= $r['shift'] ?> Shift</small>
                            </div>
                            <span class="badge bg-<?= $r['status'] ? 'success' : 'secondary' ?> flex-shrink-0"><?= $r['status'] ? 'Active' : 'Inactive' ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4 text-muted"><i class="bi bi-search me-1"></i>No results found</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Summary row -->
        <?php if ($studentsTotal + $roomsTotal + $visitorsTotal + $complaintsTotal + $leavesTotal + $feesTotal + $wardensTotal > 0): ?>
        <div class="col-12">
            <div class="card bg-light border-0">
                <div class="card-body text-center text-muted small">
                    <i class="bi bi-info-circle me-1"></i>
                    Found
                    <?php $parts = []; if ($studentsTotal) $parts[] = "$studentsTotal students"; if ($roomsTotal) $parts[] = "$roomsTotal rooms"; if ($visitorsTotal) $parts[] = "$visitorsTotal visitors"; if ($complaintsTotal) $parts[] = "$complaintsTotal complaints"; if ($leavesTotal) $parts[] = "$leavesTotal leaves"; if ($feesTotal) $parts[] = "$feesTotal fees"; if ($wardensTotal) $parts[] = "$wardensTotal wardens"; ?>
                    <?= implode(', ', $parts) ?> for "<strong><?= sanitize($q) ?></strong>"
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
