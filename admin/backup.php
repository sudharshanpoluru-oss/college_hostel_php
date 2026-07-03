<?php
$title = 'Backup & Restore';
require_once __DIR__ . '/../includes/admin-header.php';

$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0755, true);
}

// Ensure backup_history table exists
try {
    db()->query("SELECT 1 FROM backup_history LIMIT 1");
} catch (Exception $e) {
    db()->exec("CREATE TABLE IF NOT EXISTS `backup_history` (
      `id` int PRIMARY KEY AUTO_INCREMENT,
      `filename` varchar(255) NOT NULL,
      `filepath` varchar(500) NOT NULL,
      `filesize` bigint DEFAULT 0,
      `type` enum('manual','automatic') NOT NULL DEFAULT 'manual',
      `created_by` int DEFAULT NULL,
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function createBackup() {
    $backupDir = __DIR__ . '/../backups';
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0755, true);
    }
    $filename = 'hostel_backup_' . date('Ymd_His') . '.sql';
    $filepath = $backupDir . '/' . $filename;

    $tables = db()->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $output = "-- Hostel Management System Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n\n";
    $output .= "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "`;\nUSE `" . DB_NAME . "`;\n\n";

    foreach ($tables as $table) {
        $create = db()->query("SHOW CREATE TABLE `$table`")->fetch();
        $output .= "\n\n" . $create['Create Table'] . ";\n\n";

        $rows = db()->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_NUM);
        if (count($rows) > 0) {
            $cols = db()->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
            $colList = '`' . implode('`,`', $cols) . '`';
            foreach ($rows as $row) {
                $values = array_map(function($v) {
                    return $v === null ? 'NULL' : "'" . str_replace("'", "\\'", $v) . "'";
                }, $row);
                $output .= "INSERT INTO `$table` ($colList) VALUES (" . implode(',', $values) . ");\n";
            }
        }
    }

    file_put_contents($filepath, $output);
    $fsize = filesize($filepath);

    $stmt = db()->prepare("INSERT INTO backup_history (filename, filepath, filesize, type, created_by) VALUES (?, ?, ?, 'manual', ?)");
    $stmt->execute([$filename, $filepath, $fsize, $_SESSION['user_id']]);

    auditLog('Create Backup', 'Backup', "Manual backup created: $filename ($fsize bytes)");

    return ['filename' => $filename, 'filepath' => $filepath, 'filesize' => $fsize];
}

function restoreBackup($filepath) {
    if (!file_exists($filepath)) {
        throw new Exception('Backup file not found.');
    }
    $sql = file_get_contents($filepath);
    if (empty(trim($sql))) {
        throw new Exception('Backup file is empty.');
    }
    $statements = explode(";\n", $sql);
    $count = 0;
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (!empty($stmt) && stripos($stmt, 'CREATE DATABASE') === false && stripos($stmt, 'USE ') === false) {
            db()->exec($stmt);
            $count++;
        }
    }
    return $count;
}

function formatSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

function getDiskSpace() {
    $path = __DIR__ . '/../backups';
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
    $free = @disk_free_space($path);
    $total = @disk_total_space($path);
    if ($free === false || $total === false) return null;
    return ['free' => $free, 'total' => $total, 'used' => $total - $free];
}

// Auto-delete backups older than 30 days
$oldBackups = db()->prepare("SELECT id, filepath FROM backup_history WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
$oldBackups->execute();
foreach ($oldBackups->fetchAll() as $old) {
    if (file_exists($old['filepath'])) {
        @unlink($old['filepath']);
    }
    db()->prepare("DELETE FROM backup_history WHERE id = ?")->execute([$old['id']]);
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_backup') {
        try {
            $result = createBackup();
            setAlert('success', 'Backup created successfully: <strong>' . $result['filename'] . '</strong> (' . formatSize($result['filesize']) . ')');
        } catch (Exception $e) {
            setAlert('danger', 'Backup failed: ' . $e->getMessage());
        }
        redirect(BASE_URL . '/admin/backup.php');
    }

    if ($action === 'restore' && isset($_POST['filepath'])) {
        try {
            $filepath = $_POST['filepath'];
            $count = restoreBackup($filepath);
            auditLog('Restore Backup', 'Backup', "Database restored from: " . basename($filepath) . " ($count statements executed)");
            setAlert('success', "Database restored successfully from backup. $count statements executed.");
        } catch (Exception $e) {
            setAlert('danger', 'Restore failed: ' . $e->getMessage());
        }
        redirect(BASE_URL . '/admin/backup.php');
    }

    if ($action === 'delete_file' && isset($_POST['filename'])) {
        $filepath = $backupDir . '/' . basename($_POST['filename']);
        if (file_exists($filepath)) {
            @unlink($filepath);
            auditLog('Delete Backup File', 'Backup', "Backup file deleted: " . $_POST['filename']);
            setAlert('success', 'Backup file deleted successfully.');
        } else {
            setAlert('danger', 'Backup file not found.');
        }
        redirect(BASE_URL . '/admin/backup.php');
    }

    if ($action === 'delete' && isset($_POST['id'])) {
        $stmt = db()->prepare("SELECT filepath FROM backup_history WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $backup = $stmt->fetch();
        if ($backup) {
            if (file_exists($backup['filepath'])) {
                @unlink($backup['filepath']);
            }
            db()->prepare("DELETE FROM backup_history WHERE id = ?")->execute([$_POST['id']]);
            auditLog('Delete Backup', 'Backup', "Backup deleted: ID " . $_POST['id']);
            setAlert('success', 'Backup deleted successfully.');
        } else {
            setAlert('danger', 'Backup record not found.');
        }
        redirect(BASE_URL . '/admin/backup.php');
    }
}

$backupFiles = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $f) {
        if ($f !== '.' && $f !== '..' && pathinfo($f, PATHINFO_EXTENSION) === 'sql') {
            $fp = $backupDir . '/' . $f;
            $backupFiles[] = [
                'filename' => $f,
                'filepath' => $fp,
                'filesize' => filesize($fp),
                'date' => date('Y-m-d H:i:s', filemtime($fp))
            ];
        }
    }
    usort($backupFiles, function($a, $b) {
        return strcmp($b['date'], $a['date']);
    });
}

$history = db()->query("SELECT h.*, u.username FROM backup_history h LEFT JOIN users u ON u.id = h.created_by ORDER BY h.created_at DESC")->fetchAll();

$diskInfo = getDiskSpace();
?>

<style>
.backup-card { border: 1px solid #e0e0e0; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); transition: box-shadow 0.2s; }
.backup-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
.disk-bar { height: 8px; border-radius: 4px; background: #e9ecef; overflow: hidden; }
.disk-bar-fill { height: 100%; border-radius: 4px; transition: width 0.3s; }
.action-btn { min-width: 90px; }
</style>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card backup-card">
            <div class="card-body text-center p-4">
                <div class="mb-3">
                    <i class="bi bi-cloud-arrow-up fs-1 text-primary"></i>
                </div>
                <h5 class="fw-bold">Create Backup</h5>
                <p class="text-muted small mb-3">Generate a full database backup of the Hostel Management System.</p>
                <form method="post" action="?action=create_backup">
                    <input type="hidden" name="action" value="create_backup">
                    <button type="submit" class="btn btn-primary w-100 action-btn">
                        <i class="bi bi-hdd-stack"></i> Create Backup Now
                    </button>
                </form>
            </div>
        </div>
        <?php if ($diskInfo): ?>
        <div class="card backup-card mt-3">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-hdd"></i> Disk Space (Backups)</h6>
                <?php
                $usedPercent = $diskInfo['total'] > 0 ? round(($diskInfo['used'] / $diskInfo['total']) * 100, 1) : 0;
                $barColor = $usedPercent > 90 ? 'bg-danger' : ($usedPercent > 70 ? 'bg-warning' : 'bg-success');
                ?>
                <div class="disk-bar mb-2">
                    <div class="disk-bar-fill <?= $barColor ?>" style="width: <?= $usedPercent ?>%"></div>
                </div>
                <div class="d-flex justify-content-between small text-muted">
                    <span>Used: <?= formatSize($diskInfo['used']) ?></span>
                    <span>Free: <?= formatSize($diskInfo['free']) ?></span>
                </div>
                <div class="small text-muted mt-1">Total: <?= formatSize($diskInfo['total']) ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-8">
        <div class="card backup-card">
            <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="bi bi-archive"></i> Available Backups</h5>
                <span class="badge bg-secondary"><?= count($backupFiles) ?> file(s)</span>
            </div>
            <div class="card-body p-0">
                <?php if (count($backupFiles) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">#</th>
                                <th>Filename</th>
                                <th>Size</th>
                                <th>Date</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($backupFiles as $bf): ?>
                            <tr>
                                <td class="ps-4 text-muted"><?= $i++ ?></td>
                                <td><code class="small"><?= escapeOutput($bf['filename']) ?></code></td>
                                <td><?= formatSize($bf['filesize']) ?></td>
                                <td><?= date('d M Y, h:i A', strtotime($bf['date'])) ?></td>
                                <td class="text-end pe-4">
                                    <a href="<?= BASE_URL ?>/backups/<?= rawurlencode($bf['filename']) ?>" class="btn btn-sm btn-outline-success me-1" title="Download" download>
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-primary me-1" title="Restore" onclick="confirmRestore('<?= str_replace("'", "\\'", $bf['filepath']) ?>')">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Delete" onclick="confirmDelete('<?= str_replace("'", "\\'", $bf['filename']) ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox fs-1 text-muted"></i>
                    <p class="text-muted mt-2">No backup files found. Create your first backup.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card backup-card mt-4">
            <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="bi bi-clock-history"></i> Backup History</h5>
                <span class="badge bg-secondary"><?= count($history) ?> record(s)</span>
            </div>
            <div class="card-body p-0">
                <?php if (count($history) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">#</th>
                                <th>Filename</th>
                                <th>Size</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Created By</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $j = 1; foreach ($history as $h): ?>
                            <tr>
                                <td class="ps-4 text-muted"><?= $j++ ?></td>
                                <td><code class="small"><?= escapeOutput($h['filename']) ?></code></td>
                                <td><?= formatSize($h['filesize']) ?></td>
                                <td><?= date('d M Y, h:i A', strtotime($h['created_at'])) ?></td>
                                <td><span class="badge bg-<?= $h['type'] === 'manual' ? 'info' : 'secondary' ?>"><?= ucfirst($h['type']) ?></span></td>
                                <td><?= escapeOutput($h['username'] ?? 'System') ?></td>
                                <td class="text-end pe-4">
                                    <?php if (file_exists($h['filepath'])): ?>
                                    <a href="<?= BASE_URL ?>/backups/<?= rawurlencode($h['filename']) ?>" class="btn btn-sm btn-outline-success me-1" title="Download" download>
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <?php endif; ?>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this backup record and file?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $h['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-clock fs-1 text-muted"></i>
                    <p class="text-muted mt-2">No backup history recorded yet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<form method="post" id="restoreForm" style="display:none">
    <input type="hidden" name="action" value="restore">
    <input type="hidden" name="filepath" id="restoreFilepath">
</form>

<form method="post" id="deleteFileForm" style="display:none">
    <input type="hidden" name="action" value="delete_file">
    <input type="hidden" name="filename" id="deleteFilename">
</form>

<script>
function confirmRestore(filepath) {
    if (confirm('WARNING: Restoring will overwrite the current database. All existing data will be replaced.\n\nAre you sure you want to proceed?')) {
        document.getElementById('restoreFilepath').value = filepath;
        document.getElementById('restoreForm').submit();
    }
}
function confirmDelete(filename) {
    if (confirm('Delete this backup file permanently?')) {
        document.getElementById('deleteFilename').value = filename;
        document.getElementById('deleteFileForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
