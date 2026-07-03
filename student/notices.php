<?php
$title = 'Notices';
require_once __DIR__ . '/../includes/student-header.php';

$stmt = db()->prepare("SELECT * FROM notices WHERE status = 1 AND (expiry_date IS NULL OR expiry_date >= CURDATE()) ORDER BY publish_date DESC");
$stmt->execute();
$notices = $stmt->fetchAll();
?>

<div class="container-fluid">
    <h3 class="mb-4">Notices</h3>

    <?php if ($notices): ?>
        <div class="row g-3">
            <?php foreach ($notices as $notice): ?>
                <?php
                $borderClass = '';
                $priorityBadge = 'primary';
                if ($notice['priority'] === 'Urgent') {
                    $borderClass = 'border-warning border-3';
                    $priorityBadge = 'warning';
                } elseif ($notice['priority'] === 'Critical') {
                    $borderClass = 'border-danger border-3';
                    $priorityBadge = 'danger';
                }
                ?>
                <div class="col-md-6">
                    <div class="card h-100 <?= $borderClass ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title mb-0"><?= htmlspecialchars($notice['title']) ?></h5>
                                <span class="badge bg-<?= $priorityBadge ?>"><?= htmlspecialchars($notice['priority']) ?></span>
                            </div>
                            <p class="card-text"><?= nl2br(htmlspecialchars($notice['content'])) ?></p>
                            <small class="text-muted">Published: <?= date('d M Y', strtotime($notice['publish_date'])) ?></small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">No notices available at the moment.</div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/student-footer.php'; ?>
