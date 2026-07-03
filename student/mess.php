<?php
$title = 'Mess Menu';
require_once __DIR__ . '/../includes/student-header.php';

$todayStmt = db()->prepare("SELECT * FROM mess_menu WHERE (date = CURDATE() OR (day = DAYNAME(CURDATE()) AND status = 1))");
$todayStmt->execute();
$todayItems = $todayStmt->fetchAll();

$weeklyStmt = db()->prepare("SELECT * FROM mess_menu WHERE status = 1 ORDER BY FIELD(day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), FIELD(meal_type, 'Breakfast', 'Lunch', 'Evening Snacks', 'Dinner')");
$weeklyStmt->execute();
$weeklyItems = $weeklyStmt->fetchAll();

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$mealTypes = ['Breakfast', 'Lunch', 'Evening Snacks', 'Dinner'];
$weeklyGrid = [];
foreach ($weeklyItems as $item) {
    $weeklyGrid[$item['day']][$item['meal_type']] = $item['menu_items'];
}

$todayGrouped = [];
foreach ($todayItems as $item) {
    $todayGrouped[$item['meal_type']][] = $item;
}
?>

<div class="container-fluid">
    <h3 class="mb-4">Mess Menu</h3>

    <?php if ($todayGrouped): ?>
    <div class="card mb-4">
        <div class="card-header"><strong>Today's Menu (<?= date('l, d M Y') ?>)</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ($mealTypes as $meal): if (!isset($todayGrouped[$meal])) continue; ?>
                    <div class="col-md-3">
                        <div class="card h-100">
                            <div class="card-header text-center fw-bold"><?= $meal ?></div>
                            <div class="card-body">
                                <?php foreach ($todayGrouped[$meal] as $item): ?>
                                    <p class="card-text"><?= nl2br(htmlspecialchars($item['menu_items'])) ?></p>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><strong>Weekly Menu</strong></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Day</th>
                            <?php foreach ($mealTypes as $meal): ?>
                                <th><?= $meal ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($days as $day): ?>
                            <tr>
                                <td class="fw-bold"><?= $day ?></td>
                                <?php foreach ($mealTypes as $meal): ?>
                                    <td><?= isset($weeklyGrid[$day][$meal]) ? nl2br(htmlspecialchars($weeklyGrid[$day][$meal])) : '-' ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/student-footer.php'; ?>
