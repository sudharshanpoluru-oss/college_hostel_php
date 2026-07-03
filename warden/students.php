<?php
$title = 'Students';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/functions.php';
$hostelType = getWardenHostelType();
$hostelFilter = $hostelType ? " AND s.hostel_type = '$hostelType'" : '';

// JSON endpoint for profile modal
if (isset($_GET['action']) && $_GET['action'] === 'profile' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = db()->prepare("SELECT s.*, COALESCE(r.room_no, '-') AS room_no FROM students s LEFT JOIN room_allocations al ON al.student_id = s.id AND al.status = 'Active' LEFT JOIN rooms r ON r.id = al.room_id WHERE s.id = ? $hostelFilter");
    $stmt->execute([$id]);
    $student = $stmt->fetch();
    if ($student) {
        sendJSON($student);
    } else {
        sendJSON(['error' => 'Student not found'], 404);
    }
}

require_once __DIR__ . '/../includes/warden-header.php';

$page   = (int)($_GET['p'] ?? 1);
$perPage = 20;
$search = trim($_GET['search'] ?? '');

$where = "WHERE s.status = 'Active'";
$params = [];
if ($search !== '') {
    $where .= " AND (s.name LIKE ? OR s.roll_no LIKE ? OR s.phone LIKE ? OR s.email LIKE ? OR s.department LIKE ? OR s.year LIKE ?)";
    $searchTerm = "%$search%";
    for ($i = 0; $i < 6; $i++) $params[] = $searchTerm;
}
$where .= $hostelFilter;

$countSql = "SELECT COUNT(*) FROM students s $where";
$countStmt = db()->prepare($countSql);
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();
$pages = paginate($page, $perPage, $totalRows);

$sql = "SELECT s.*, COALESCE(r.room_no, '-') AS room_no FROM students s LEFT JOIN room_allocations al ON al.student_id = s.id AND al.status = 'Active' LEFT JOIN rooms r ON r.id = al.room_id $where ORDER BY s.name ASC LIMIT $perPage OFFSET {$pages['offset']}";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="mb-0"><i class="bi bi-people"></i> Students (Read Only)</h5>
    <form method="get" class="d-flex gap-2">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name, roll, phone, email, dept, year..." value="<?= sanitize($search) ?>" style="min-width:250px">
        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
        <?php if ($search !== ''): ?>
        <a href="?" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></a>
        <?php endif; ?>
    </form>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Photo</th>
                <th>Name</th>
                <th>Roll No</th>
                <th>Room</th>
                <th>Course</th>
                <th>Department</th>
                <th>Year</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($records): $i = $pages['offset']; ?>
                <?php foreach ($records as $r): $i++; ?>
                <tr>
                    <td><?= $i ?></td>
                    <td>
                        <?php if ($r['photo']): ?>
                        <img src="<?= BASE_URL ?>/uploads/<?= sanitize($r['photo']) ?>" alt="Photo" class="rounded" style="width:40px;height:40px;object-fit:cover;">
                        <?php else: ?>
                        <i class="bi bi-person-circle fs-4 text-muted"></i>
                        <?php endif; ?>
                    </td>
                    <td class="fw-medium"><?= sanitize($r['name']) ?></td>
                    <td><?= sanitize($r['roll_no']) ?></td>
                    <td><?= sanitize($r['room_no']) ?></td>
                    <td><small><?= sanitize($r['course'] ?? '-') ?></small></td>
                    <td><small><?= sanitize($r['department'] ?? '-') ?></small></td>
                    <td><small><?= sanitize($r['year'] ?? '-') ?></small></td>
                    <td><?= sanitize($r['phone'] ?? '-') ?></td>
                    <td><small><?= sanitize($r['email'] ?? '-') ?></small></td>
                    <td>
                        <a href="#" class="btn btn-sm btn-outline-info" title="View Profile"
                           onclick="showStudentProfile(<?= $r['id'] ?>); return false;">
                            <i class="bi bi-person-lines-fill"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="11" class="text-center text-muted">No active students found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?= paginationLinks($page, $pages) ?>

<!-- Student Profile Modal -->
<div class="modal fade" id="studentProfileModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-badge"></i> Student Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="studentProfileBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">Loading...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showStudentProfile(studentId) {
    const modal = new bootstrap.Modal(document.getElementById('studentProfileModal'));
    const body = document.getElementById('studentProfileBody');
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Loading...</p></div>';
    modal.show();

    fetch('<?= BASE_URL ?>/warden/students.php?action=profile&id=' + studentId)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                body.innerHTML = '<div class="alert alert-danger">' + data.error + '</div>';
                return;
            }
            let html = '<div class="row">';
            html += '<div class="col-md-4 text-center mb-3">';
            if (data.photo) {
                html += '<img src="<?= BASE_URL ?>/uploads/' + data.photo + '" class="rounded img-fluid" style="max-height:200px;object-fit:cover;">';
            } else {
                html += '<i class="bi bi-person-circle" style="font-size:100px;color:#ccc;"></i>';
            }
            html += '</div>';
            html += '<div class="col-md-8"><table class="table table-sm">';
            html += '<tr><th>Name</th><td>' + data.name + '</td></tr>';
            html += '<tr><th>Roll No</th><td>' + data.roll_no + '</td></tr>';
            html += '<tr><th>Room</th><td>' + data.room_no + '</td></tr>';
            html += '<tr><th>Course</th><td>' + (data.course || '-') + '</td></tr>';
            html += '<tr><th>Department</th><td>' + (data.department || '-') + '</td></tr>';
            html += '<tr><th>Year</th><td>' + (data.year || '-') + '</td></tr>';
            html += '<tr><th>Phone</th><td>' + (data.phone || '-') + '</td></tr>';
            html += '<tr><th>Email</th><td>' + (data.email || '-') + '</td></tr>';
            html += '<tr><th>Gender</th><td>' + data.gender + '</td></tr>';
            html += '<tr><th>Blood Group</th><td>' + (data.blood_group || '-') + '</td></tr>';
            html += '<tr><th>Guardian</th><td>' + (data.guardian_name || '-') + ' (' + (data.guardian_phone || '-') + ')</td></tr>';
            html += '<tr><th>Emergency Contact</th><td>' + (data.emergency_contact_name || '-') + ' - ' + (data.emergency_contact || '-') + '</td></tr>';
            html += '<tr><th>Status</th><td><span class="badge bg-success">' + data.status + '</span></td></tr>';
            html += '</table></div></div>';
            body.innerHTML = html;
        })
        .catch(() => {
            body.innerHTML = '<div class="alert alert-danger">Failed to load profile.</div>';
        });
}
</script>

<?php require_once __DIR__ . '/../includes/warden-footer.php'; ?>
