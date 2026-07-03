<?php
$title = 'Events';
require_once __DIR__ . '/../includes/student-header.php';

$stmt = db()->prepare("SELECT * FROM hostel_events WHERE status=1 AND event_date >= CURDATE() ORDER BY event_date ASC");
$stmt->execute();
$events = $stmt->fetchAll();
?>

<div class="container-fluid px-0">
    <?php if ($events): ?>
        <div class="row g-3">
            <?php foreach ($events as $event): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="card h-100">
                        <div class="card-body d-flex align-items-start gap-3">
                            <div class="text-center flex-shrink-0 bg-light rounded p-2" style="min-width:60px">
                                <div class="fw-bold text-primary fs-3 lh-1"><?= date('d', strtotime($event['event_date'])) ?></div>
                                <div class="small text-muted text-uppercase fw-medium"><?= date('M', strtotime($event['event_date'])) ?></div>
                            </div>
                            <div class="min-w-0">
                                <h6 class="card-title mb-1 fw-semibold"><?= sanitize($event['title']) ?></h6>
                                <p class="card-text small text-muted mb-2"><?= sanitize($event['description']) ?></p>
                                <div class="d-flex flex-wrap gap-2 small">
                                    <?php if ($event['location']): ?>
                                        <span class="text-muted"><i class="bi bi-geo-alt"></i> <?= sanitize($event['location']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($event['event_time']): ?>
                                        <span class="text-muted"><i class="bi bi-clock"></i> <?= date('h:i A', strtotime($event['event_time'])) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-calendar-x display-4 text-muted"></i>
            <p class="text-muted mt-3 mb-0">No upcoming events</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/student-footer.php'; ?>
