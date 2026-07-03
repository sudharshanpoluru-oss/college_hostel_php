<?php
require_once __DIR__ . '/../includes/session.php';
$title = 'Gallery';
include __DIR__ . '/../includes/header.php';

$images = [];
try {
    $stmt = db()->prepare("SELECT * FROM gallery WHERE status = 1 ORDER BY created_at DESC");
    $stmt->execute();
    $images = $stmt->fetchAll();
} catch (Exception $e) {
    $images = [];
}

$placeholders = [];
if (count($images) === 0) {
    $placeholders = [
        ['title' => 'Main Building', 'category' => 'Exterior', 'color' => '#4e73df'],
        ['title' => 'Reception Area', 'category' => 'Interior', 'color' => '#1cc88a'],
        ['title' => 'Common Room', 'category' => 'Common Areas', 'color' => '#36b9cc'],
        ['title' => 'Library', 'category' => 'Facilities', 'color' => '#f6c23e'],
        ['title' => 'Dining Hall', 'category' => 'Facilities', 'color' => '#e74a3b'],
        ['title' => 'Gym', 'category' => 'Facilities', 'color' => '#858796'],
        ['title' => 'Garden Area', 'category' => 'Exterior', 'color' => '#5a5c69'],
        ['title' => 'Study Room', 'category' => 'Common Areas', 'color' => '#2c9faf']
    ];
}
?>

<section class="bg-primary text-white py-4">
    <div class="container">
        <h1 class="fw-bold">Gallery</h1>
        <p class="lead mb-0">A glimpse into hostel life</p>
    </div>
</section>

<section class="bg-white py-5">
    <div class="container">
        <?php if (count($images) > 0): ?>
            <div class="row g-4">
                <?php foreach ($images as $image): ?>
                    <div class="col-md-4 col-6">
                        <div class="card h-100 shadow-sm gallery-item" role="button" data-bs-toggle="modal" data-bs-target="#imageModal" data-title="<?= sanitize($image['title']) ?>" data-category="<?= sanitize($image['category']) ?>" data-src="<?= BASE_URL ?>/uploads/<?= sanitize($image['image']) ?>">
                            <div style="height: 200px; background: #e9ecef; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                <img src="<?= BASE_URL ?>/uploads/<?= sanitize($image['image']) ?>" alt="<?= sanitize($image['title']) ?>" class="img-fluid" style="object-fit: cover; width: 100%; height: 100%;" onerror="this.parentElement.innerHTML='<i class=\'bi bi-image text-secondary\' style=\'font-size:3rem\'></i>'">
                            </div>
                            <div class="card-body">
                                <h6 class="card-title mb-1"><?= sanitize($image['title']) ?></h6>
                                <span class="badge bg-primary"><?= sanitize($image['category']) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($placeholders as $item): ?>
                    <div class="col-md-3 col-6">
                        <div class="card h-100 shadow-sm gallery-item" role="button" data-bs-toggle="modal" data-bs-target="#imageModal" data-title="<?= $item['title'] ?>" data-category="<?= $item['category'] ?>" data-color="<?= $item['color'] ?>">
                            <div style="height: 180px; background: <?= $item['color'] ?>; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-building text-white" style="font-size: 3rem;"></i>
                            </div>
                            <div class="card-body text-center">
                                <h6 class="card-title"><?= $item['title'] ?></h6>
                                <span class="badge bg-primary"><?= $item['category'] ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-0" id="modalBody">
            </div>
            <div class="modal-footer justify-content-between">
                <span class="badge bg-primary" id="modalCategory"></span>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var items = document.querySelectorAll('.gallery-item');
    items.forEach(function(item) {
        item.addEventListener('click', function() {
            var title = this.dataset.title;
            var category = this.dataset.category;
            var src = this.dataset.src;
            var color = this.dataset.color;
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalCategory').textContent = category;
            var body = document.getElementById('modalBody');
            if (src) {
                body.innerHTML = '<img src="' + src + '" class="img-fluid" alt="' + title + '" style="max-height:70vh;">';
            } else if (color) {
                body.innerHTML = '<div style="height:300px;background:' + color + ';display:flex;align-items:center;justify-content:center;"><i class="bi bi-building text-white" style="font-size:5rem;"></i></div>';
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
