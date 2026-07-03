<?php
require_once __DIR__ . '/../includes/session.php';
$title = 'Rooms';
include __DIR__ . '/../includes/header.php';

$conditions = [];
$params = [];

if (!empty($_GET['room_type'])) {
    $conditions[] = "room_type = ?";
    $params[] = sanitize($_GET['room_type']);
}
if (!empty($_GET['status'])) {
    $conditions[] = "status = ?";
    $params[] = sanitize($_GET['status']);
}
if (!empty($_GET['search'])) {
    $conditions[] = "room_no LIKE ?";
    $params[] = '%' . sanitize($_GET['search']) . '%';
}

$whereClause = '';
if (count($conditions) > 0) {
    $whereClause = 'WHERE ' . implode(' AND ', $conditions);
}

$rooms = [];
try {
    $stmt = db()->prepare("SELECT * FROM rooms $whereClause ORDER BY room_no ASC");
    $stmt->execute($params);
    $rooms = $stmt->fetchAll();
} catch (Exception $e) {
    $rooms = [];
}

$selectedType = $_GET['room_type'] ?? '';
$selectedStatus = $_GET['status'] ?? '';
$searchValue = $_GET['search'] ?? '';
?>

<section class="bg-primary text-white py-4">
    <div class="container">
        <h1 class="fw-bold">Our Rooms</h1>
        <p class="lead mb-0">Find the perfect room for your stay</p>
    </div>
</section>

<section class="bg-light py-4">
    <div class="container">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="GET" action="" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Room Type</label>
                        <select name="room_type" class="form-select">
                            <option value="">All Types</option>
                            <option value="Single" <?= $selectedType === 'Single' ? 'selected' : '' ?>>Single</option>
                            <option value="Double" <?= $selectedType === 'Double' ? 'selected' : '' ?>>Double</option>
                            <option value="Triple" <?= $selectedType === 'Triple' ? 'selected' : '' ?>>Triple</option>
                            <option value="Dormitory" <?= $selectedType === 'Dormitory' ? 'selected' : '' ?>>Dormitory</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="Available" <?= $selectedStatus === 'Available' ? 'selected' : '' ?>>Available</option>
                            <option value="Full" <?= $selectedStatus === 'Full' ? 'selected' : '' ?>>Full</option>
                            <option value="Maintenance" <?= $selectedStatus === 'Maintenance' ? 'selected' : '' ?>>Maintenance</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Search Room No</label>
                        <input type="text" name="search" class="form-control" placeholder="e.g. 101, 201..." value="<?= sanitize($searchValue) ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Filter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<section class="bg-white py-4">
    <div class="container">
        <?php if (count($rooms) > 0): ?>
            <div class="row g-4">
                <?php foreach ($rooms as $room): ?>
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge bg-dark fs-6">Room <?= sanitize($room['room_no']) ?></span>
                                    <?php
                                    $statusBadge = match($room['status']) {
                                        'Available' => 'bg-success',
                                        'Full' => 'bg-danger',
                                        'Maintenance' => 'bg-warning text-dark',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $statusBadge ?> fs-6"><?= $room['status'] ?></span>
                                </div>
                                <h5 class="card-title text-primary"><?= sanitize($room['room_type']) ?></h5>
                                <ul class="list-unstyled small">
                                    <li class="mb-1"><i class="bi bi-people"></i> <strong>Capacity:</strong> <?= $room['capacity'] ?></li>
                                    <li class="mb-1"><i class="bi bi-person-check"></i> <strong>Occupancy:</strong> <?= $room['occupancy'] ?></li>
                                    <li class="mb-1"><i class="bi bi-person-plus"></i> <strong>Available Beds:</strong> <?= $room['capacity'] - $room['occupancy'] ?></li>
                                    <li class="mb-1"><i class="bi bi-currency-rupee"></i> <strong>Fee:</strong> ₹<?= number_format($room['fee_per_month'], 2) ?>/month</li>
                                </ul>
                                <?php if (!empty($room['description'])): ?>
                                    <p class="card-text small text-muted"><?= sanitize($room['description']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle"></i> No rooms found matching your criteria.
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
