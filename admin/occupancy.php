<?php
$title = 'Room Occupancy';
require_once __DIR__ . '/../includes/admin-header.php';

$hostel_type = $_GET['hostel_type'] ?? '';
$search      = trim($_GET['search'] ?? '');

$conditions = [];
$params = [];

if ($hostel_type !== '' && in_array($hostel_type, ['Boys', 'Girls'])) {
    $conditions[] = "EXISTS (SELECT 1 FROM room_allocations ra JOIN students s ON s.id = ra.student_id WHERE ra.room_id = r.id AND ra.status = 'Active' AND s.hostel_type = ?)";
    $params[] = $hostel_type;
}

if ($search !== '') {
    $conditions[] = "r.room_no LIKE ?";
    $params[] = "%$search%";
}

$sql = "SELECT r.*, (r.capacity - r.occupancy) as free_beds FROM rooms r";
if ($conditions) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}
$sql .= " ORDER BY r.floor, r.room_no";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$rooms = $stmt->fetchAll();

$floors = [];
foreach ($rooms as $r) {
    $floors[$r['floor']][] = $r;
}

$total_rooms = count($rooms);
$occupied_rooms = 0;
$vacant_rooms = 0;
$maintenance_rooms = 0;
$total_occupancy = 0;
$total_capacity = 0;

foreach ($rooms as $r) {
    $total_occupancy += $r['occupancy'];
    $total_capacity += $r['capacity'];
    if ($r['status'] === 'Full') $occupied_rooms++;
    if ($r['free_beds'] > 0) $vacant_rooms++;
    if ($r['status'] === 'Maintenance') $maintenance_rooms++;
}

$occupancy_pct = $total_capacity > 0 ? round(($total_occupancy / $total_capacity) * 100) : 0;
?>
<div class="container-fluid">
    <div class="row g-3 mb-4">
        <div class="col-md">
            <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body text-white">
                    <h6 class="card-title mb-0">Total Rooms</h6>
                    <h2 class="mb-0 fw-bold"><?= $total_rooms ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="card-body text-white">
                    <h6 class="card-title mb-0">Occupied Rooms</h6>
                    <h2 class="mb-0 fw-bold"><?= $occupied_rooms ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <div class="card-body text-white">
                    <h6 class="card-title mb-0">Vacant Rooms</h6>
                    <h2 class="mb-0 fw-bold"><?= $vacant_rooms ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                <div class="card-body">
                    <h6 class="card-title mb-0">Under Maintenance</h6>
                    <h2 class="mb-0 fw-bold"><?= $maintenance_rooms ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                <div class="card-body">
                    <h6 class="card-title mb-0">Occupancy</h6>
                    <h2 class="mb-0 fw-bold"><?= $occupancy_pct ?>%</h2>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label small mb-1">Hostel Type</label>
                    <select name="hostel_type" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All</option>
                        <option value="Boys" <?= $hostel_type === 'Boys' ? 'selected' : '' ?>>Boys</option>
                        <option value="Girls" <?= $hostel_type === 'Girls' ? 'selected' : '' ?>>Girls</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Search Room</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Room number..." value="<?= sanitize($search) ?>">
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-primary">Filter</button>
                    <a href="<?= BASE_URL ?>/admin/occupancy.php" class="btn btn-sm btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="d-flex gap-3 mb-3 flex-wrap">
        <span><span class="badge bg-success">Available</span> Free beds available</span>
        <span><span class="badge bg-danger">Full</span> No free beds</span>
        <span><span class="badge bg-warning text-dark">Maintenance</span> Under maintenance</span>
        <span><span class="badge bg-secondary">Reserved</span> Reserved / Unknown</span>
    </div>

    <?php foreach ($floors as $floor => $floor_rooms):
        $floor_occ = array_sum(array_column($floor_rooms, 'occupancy'));
        $floor_cap = array_sum(array_column($floor_rooms, 'capacity'));
        $floor_pct = $floor_cap > 0 ? round(($floor_occ / $floor_cap) * 100) : 0;
    ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-layers"></i> Floor <?= sanitize($floor) ?></h5>
            <span class="text-muted small"><?= $floor_occ ?> / <?= $floor_cap ?> beds filled (<?= $floor_pct ?>%)</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ($floor_rooms as $r):
                    $color_class = match ($r['status']) {
                        'Available'    => 'success',
                        'Full'         => 'danger',
                        'Maintenance'  => 'warning',
                        default        => 'secondary'
                    };
                    $bg_color = match ($r['status']) {
                        'Available'    => '#d4edda',
                        'Full'         => '#f8d7da',
                        'Maintenance'  => '#fff3cd',
                        default        => '#e2e3e5'
                    };
                    $border_color = match ($r['status']) {
                        'Available'    => '#28a745',
                        'Full'         => '#dc3545',
                        'Maintenance'  => '#ffc107',
                        default        => '#6c757d'
                    };
                ?>
                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                    <div class="card h-100 border-0" style="background: <?= $bg_color ?>; border-left: 4px solid <?= $border_color ?>;">
                        <div class="card-body text-center p-3">
                            <h6 class="card-title mb-1 fw-bold"><?= sanitize($r['room_no']) ?></h6>
                            <div class="small text-muted mb-1"><?= sanitize($r['room_type']) ?></div>
                            <div class="small fw-semibold"><?= $r['occupancy'] ?> / <?= $r['capacity'] ?></div>
                            <span class="badge bg-<?= $color_class ?> mt-1"><?= $r['status'] ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
