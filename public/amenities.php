<?php
require_once __DIR__ . '/../includes/session.php';
$title = 'Amenities';
include __DIR__ . '/../includes/header.php';

$amenities = [
    [
        'icon' => 'bi-wifi',
        'title' => '24/7 WiFi',
        'description' => 'High-speed internet connectivity available throughout the hostel premises. Stay connected with your studies and loved ones.',
        'status' => 'Available'
    ],
    [
        'icon' => 'bi-shield-lock',
        'title' => 'Security',
        'description' => 'Round-the-clock security with CCTV surveillance, biometric entry, and trained security personnel ensuring a safe environment.',
        'status' => 'Available'
    ],
    [
        'icon' => 'bi-droplet',
        'title' => 'Laundry',
        'description' => 'On-site laundry facility equipped with modern washing machines and dryers for your convenience.',
        'status' => 'Available'
    ],
    [
        'icon' => 'bi-activity',
        'title' => 'Gym',
        'description' => 'Fully equipped fitness center with cardio machines, weight training equipment, and yoga space.',
        'status' => 'Available'
    ],
    [
        'icon' => 'bi-book',
        'title' => 'Library',
        'description' => 'Well-stocked library with textbooks, reference materials, journals, and a quiet study area.',
        'status' => 'Available'
    ],
    [
        'icon' => 'bi-tv',
        'title' => 'Common Room',
        'description' => 'Spacious common room with television, indoor games, newspapers, and magazines for recreation.',
        'status' => 'Available'
    ],
    [
        'icon' => 'bi-car-front',
        'title' => 'Parking',
        'description' => 'Secure parking area for bicycles, scooters, and motorcycles with 24/7 surveillance.',
        'status' => 'Available'
    ],
    [
        'icon' => 'bi-lightning',
        'title' => 'Power Backup',
        'description' => 'Uninterrupted power supply with automatic generator backup and UPS in common areas.',
        'status' => 'Available'
    ]
];
?>

<section class="bg-primary text-white py-4">
    <div class="container">
        <h1 class="fw-bold">Amenities</h1>
        <p class="lead mb-0">Everything we offer for a comfortable living experience</p>
    </div>
</section>

<section class="bg-white pt-5 pb-0">
    <div class="container">
        <h2 class="text-center fw-bold mb-2">Take a Look Around</h2>
        <div class="section-divider"></div>
        <p class="text-center text-muted mb-4">Real glimpses of our hostel facilities</p>
        <div class="row g-3">
            <?php
            $amenityPhotos = [
                ['sample-library.jpg', 'Library'],
                ['sample-gym.jpg', 'Gym'],
                ['sample-common-room.jpg', 'Common Room'],
                ['sample-dining.jpg', 'Dining Hall'],
            ];
            foreach ($amenityPhotos as $ap): ?>
            <div class="col-md-3 col-6">
                <div class="gallery-item">
                    <img src="<?= BASE_URL ?>/uploads/<?= $ap[0] ?>" class="w-100" style="height: 180px; object-fit: cover;" alt="<?= $ap[1] ?>" loading="lazy">
                    <div class="p-2"><small class="fw-medium"><?= $ap[1] ?></small></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="bg-white py-5">
    <div class="container">
        <div class="row g-4">
            <?php foreach ($amenities as $amenity): ?>
                <div class="col-md-4 col-6">
                    <div class="card h-100 shadow-sm border-0 text-center">
                        <div class="card-body">
                            <i class="bi <?= $amenity['icon'] ?> text-primary" style="font-size: 3rem;"></i>
                            <h5 class="fw-bold mt-3"><?= $amenity['title'] ?></h5>
                            <p class="card-text small text-muted"><?= $amenity['description'] ?></p>
                            <span class="badge bg-success"><?= $amenity['status'] ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="bg-light py-5">
    <div class="container text-center">
        <h2 class="fw-bold">Additional Services</h2>
        <p class="text-muted">We also provide the following value-added services</p>
        <div class="row g-4 mt-3">
            <div class="col-md-3 col-6">
                <div class="p-3">
                    <i class="bi bi-truck text-primary" style="font-size: 2rem;"></i>
                    <h6 class="mt-2">Courier Handling</h6>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3">
                    <i class="bi bi-water text-primary" style="font-size: 2rem;"></i>
                    <h6 class="mt-2">RO Water Purifier</h6>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3">
                    <i class="bi bi-bandaid text-primary" style="font-size: 2rem;"></i>
                    <h6 class="mt-2">First Aid Kit</h6>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3">
                    <i class="bi bi-house-add text-primary" style="font-size: 2rem;"></i>
                    <h6 class="mt-2">Housekeeping</h6>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
