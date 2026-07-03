<?php
$title = 'Room Inspection';
require_once __DIR__ . '/../includes/warden-header.php';

$action = $_GET['action'] ?? 'list';
$page   = (int)($_GET['p'] ?? 1);
$perPage = 20;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    requireCSRF();
    $room_id = (int)($_POST['room_id'] ?? 0);
    $inspection_date = $_POST['inspection_date'] ?? '';
    $cleanliness = (int)($_POST['cleanliness_rating'] ?? 0);
    $furniture = (int)($_POST['furniture_condition'] ?? 0);
    $electrical = (int)($_POST['electrical_status'] ?? 0);
    $plumbing = (int)($_POST['plumbing_status'] ?? 0);
    $damages = trim($_POST['damages'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $result = $_POST['result'] ?? 'Pass';

    try {
        $stmt = db()->prepare("INSERT INTO room_inspections (room_id, inspection_date, cleanliness_rating, furniture_condition, electrical_status, plumbing_status, damages, remarks, result, inspector, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$room_id, $inspection_date, $cleanliness, $furniture, $electrical, $plumbing, $damages, $remarks, $result, $_SESSION['username'], $_SESSION['user_id']]);
        auditLog('Room Inspection Added', 'Room Inspection', "Inspection for room ID $room_id on $inspection_date");
        setAlert('success', 'Room inspection recorded successfully.');
    } catch (Exception $e) {
        setAlert('danger', 'Error: ' . $e->getMessage());
    }
    redirect(BASE_URL . '/warden/room-inspection.php');
}

// --- LIST ---
$countSql = "SELECT COUNT(*) FROM room_inspections";
$totalRows = db()->query($countSql)->fetchColumn();
$pages = paginate($page, $perPage, $totalRows);

$sql = "SELECT ri.*, r.room_no FROM room_inspections ri JOIN rooms r ON r.id = ri.room_id ORDER BY ri.inspection_date DESC, ri.created_at DESC LIMIT $perPage OFFSET {$pages['offset']}";
$records = db()->query($sql)->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="mb-0"><i class="bi bi-door-open"></i> Room Inspection</h5>
    <a href="?action=add" class="btn btn-primary btn-sm <?= $action === 'add' ? 'active' : '' ?>"><i class="bi bi-plus-lg"></i> New Inspection</a>
</div>

<?php if ($action === 'add'): ?>
<div class="card">
    <div class="card-header"><strong>Add Inspection</strong></div>
    <div class="card-body">
        <form method="post" action="?action=add">
            <?= csrfField() ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Room <span class="text-danger">*</span></label>
                    <select name="room_id" class="form-select" required>
                        <option value="">Select Room</option>
                        <?php $rooms = db()->query("SELECT id, room_no, floor FROM rooms ORDER BY room_no")->fetchAll(); ?>
                        <?php foreach ($rooms as $rm): ?>
                        <option value="<?= $rm['id'] ?>"><?= sanitize($rm['room_no']) ?> (<?= sanitize($rm['floor'] ?? 'GF') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Inspection Date <span class="text-danger">*</span></label>
                    <input type="date" name="inspection_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Result</label>
                    <select name="result" class="form-select">
                        <option value="Pass">Pass</option>
                        <option value="Fail">Fail</option>
                        <option value="Needs Improvement">Needs Improvement</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cleanliness (1-5)</label>
                    <input type="number" name="cleanliness_rating" class="form-control" min="1" max="5" value="3" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Furniture (1-5)</label>
                    <input type="number" name="furniture_condition" class="form-control" min="1" max="5" value="3" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Electrical (1-5)</label>
                    <input type="number" name="electrical_status" class="form-control" min="1" max="5" value="3" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Plumbing (1-5)</label>
                    <input type="number" name="plumbing_status" class="form-control" min="1" max="5" value="3" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Damages</label>
                    <textarea name="damages" class="form-control" rows="2" placeholder="Describe any damages found..."></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="mt-3">
                <button class="btn btn-primary"><i class="bi bi-save"></i> Save Inspection</button>
                <a href="?" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php else: ?>

<div class="table-responsive">
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Room</th>
                <th>Date</th>
                <th>Cleanliness</th>
                <th>Furniture</th>
                <th>Electrical</th>
                <th>Plumbing</th>
                <th>Result</th>
                <th>Inspector</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($records): $i = $pages['offset']; ?>
                <?php foreach ($records as $r): $i++; ?>
                <tr>
                    <td><?= $i ?></td>
                    <td class="fw-medium"><?= sanitize($r['room_no']) ?></td>
                    <td><?= $r['inspection_date'] ?></td>
                    <td><span class="badge bg-<?= $r['cleanliness_rating'] >= 4 ? 'success' : ($r['cleanliness_rating'] >= 3 ? 'warning' : 'danger') ?>"><?= $r['cleanliness_rating'] ?>/5</span></td>
                    <td><span class="badge bg-<?= $r['furniture_condition'] >= 4 ? 'success' : ($r['furniture_condition'] >= 3 ? 'warning' : 'danger') ?>"><?= $r['furniture_condition'] ?>/5</span></td>
                    <td><span class="badge bg-<?= $r['electrical_status'] >= 4 ? 'success' : ($r['electrical_status'] >= 3 ? 'warning' : 'danger') ?>"><?= $r['electrical_status'] ?>/5</span></td>
                    <td><span class="badge bg-<?= $r['plumbing_status'] >= 4 ? 'success' : ($r['plumbing_status'] >= 3 ? 'warning' : 'danger') ?>"><?= $r['plumbing_status'] ?>/5</span></td>
                    <td>
                        <?php
                        $rBadge = match($r['result']) { 'Pass' => 'success', 'Fail' => 'danger', 'Needs Improvement' => 'warning' };
                        ?>
                        <span class="badge bg-<?= $rBadge ?>"><?= $r['result'] ?></span>
                    </td>
                    <td><small><?= sanitize($r['inspector'] ?? '-') ?></small></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-info" title="View Details"
                           onclick="showInspection(<?= $r['id'] ?>)" data-id="<?= $r['id'] ?>">
                            <i class="bi bi-eye"></i>
                        </button>
                        <div id="inspDetail<?= $r['id'] ?>" style="display:none">
                            <strong>Room:</strong> <?= sanitize($r['room_no']) ?><br>
                            <strong>Date:</strong> <?= $r['inspection_date'] ?><br>
                            <strong>Cleanliness:</strong> <?= $r['cleanliness_rating'] ?>/5<br>
                            <strong>Furniture:</strong> <?= $r['furniture_condition'] ?>/5<br>
                            <strong>Electrical:</strong> <?= $r['electrical_status'] ?>/5<br>
                            <strong>Plumbing:</strong> <?= $r['plumbing_status'] ?>/5<br>
                            <strong>Damages:</strong> <?= sanitize($r['damages'] ?? 'None') ?><br>
                            <strong>Remarks:</strong> <?= sanitize($r['remarks'] ?? 'None') ?><br>
                            <strong>Result:</strong> <?= $r['result'] ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="10" class="text-center text-muted">No inspections found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?= paginationLinks($page, $pages) ?>
<?php endif; ?>

<div class="modal fade" id="inspectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Inspection Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="inspectionModalBody"></div>
        </div>
    </div>
</div>
<script>
function showInspection(id) {
    var el = document.getElementById('inspDetail' + id);
    if (el) {
        document.getElementById('inspectionModalBody').innerHTML = el.innerHTML;
        var modal = new bootstrap.Modal(document.getElementById('inspectionModal'));
        modal.show();
    }
}
</script>
<?php require_once __DIR__ . '/../includes/warden-footer.php'; ?>
