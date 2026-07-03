<?php
require_once __DIR__ . '/../includes/session.php';
$title = 'Home';
include __DIR__ . '/../includes/header.php';

$roomsCount = $studentsCount = 0;
try { $roomsCount = getTotal('rooms'); $studentsCount = getTotal('students', 'status="Active"'); } catch (Exception $e) {}

$featuredRooms = [];
try { $stmt = db()->prepare("SELECT * FROM rooms ORDER BY id DESC LIMIT 4"); $stmt->execute(); $featuredRooms = $stmt->fetchAll(); } catch (Exception $e) {}

$staff = [];
try { $stmt = db()->query("SELECT * FROM management_staff ORDER BY sort_order, id"); $staff = $stmt->fetchAll(); } catch (Exception $e) {}

$testimonials = [];
try { $stmt = db()->query("SELECT * FROM testimonials WHERE status=1 ORDER BY id DESC LIMIT 3"); $testimonials = $stmt->fetchAll(); } catch (Exception $e) {}

$faqs = [];
try { $stmt = db()->query("SELECT * FROM faq WHERE status=1 ORDER BY sort_order, id LIMIT 6"); $faqs = $stmt->fetchAll(); } catch (Exception $e) {}

$events = [];
try { $stmt = db()->query("SELECT * FROM hostel_events WHERE status=1 AND event_date >= CURDATE() ORDER BY event_date ASC LIMIT 3"); $events = $stmt->fetchAll(); } catch (Exception $e) {}

$notices = [];
try { $stmt = db()->query("SELECT * FROM notices WHERE status=1 AND (expiry_date IS NULL OR expiry_date >= CURDATE()) ORDER BY publish_date DESC LIMIT 3"); $notices = $stmt->fetchAll(); } catch (Exception $e) {}

$gallery = [];
try { $stmt = db()->query("SELECT * FROM gallery WHERE status=1 ORDER BY id DESC LIMIT 6"); $gallery = $stmt->fetchAll(); } catch (Exception $e) {}
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="hero-shape hero-shape-1"></div>
    <div class="hero-shape hero-shape-2"></div>
    <div class="hero-shape hero-shape-3"></div>
    <div class="hero-shape hero-shape-4"></div>
    <div class="container text-center">
        <div class="hero-badge">
            <i class="bi bi-star-fill" style="color:#f59e0b"></i>
            Premier Student Accommodation
        </div>
        <h1 class="mb-3">Your Home Away<br>From Home</h1>
        <p class="lead mb-4 mx-auto" style="max-width:600px">Safe, comfortable, and affordable accommodation for students pursuing their academic goals. Experience a home-like environment with modern amenities.</p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <a href="<?= BASE_URL ?>/public/rooms.php" class="btn btn-light btn-lg fw-bold px-4"><i class="bi bi-door-open"></i> View Rooms</a>
            <a href="<?= BASE_URL ?>/public/contact.php" class="btn btn-outline-light btn-lg fw-bold px-4"><i class="bi bi-envelope"></i> Contact Us</a>
            <?php if (!isLoggedIn()): ?>
            <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-success btn-lg fw-bold px-4"><i class="bi bi-person-plus"></i> Register Now</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Statistics -->
<section class="home-stats">
    <div class="container">
        <div class="row g-3 justify-content-center">
            <div class="col-md-3 col-6">
                <div class="card stat-card border-primary text-center">
                    <div class="card-body">
                        <i class="bi bi-door-open text-primary stat-icon"></i>
                        <h3 class="mt-2 text-primary fw-bold counter" data-target="<?= $roomsCount ?>"><?= $roomsCount ?></h3>
                        <p class="stat-label">Total Rooms</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card border-success text-center">
                    <div class="card-body">
                        <i class="bi bi-people text-success stat-icon"></i>
                        <h3 class="mt-2 text-success fw-bold counter" data-target="<?= $studentsCount ?>"><?= $studentsCount ?></h3>
                        <p class="stat-label">Happy Students</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card border-info text-center">
                    <div class="card-body">
                        <i class="bi bi-shield-check text-info stat-icon"></i>
                        <h3 class="mt-2 text-info fw-bold counter" data-target="24">24/7</h3>
                        <p class="stat-label">Security</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card border-warning text-center">
                    <div class="card-body">
                        <i class="bi bi-cup-hot text-warning stat-icon"></i>
                        <h3 class="mt-2 text-warning fw-bold counter" data-target="3">3</h3>
                        <p class="stat-label">Meals Daily</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Why Choose Us -->
<section class="py-5 scroll-fade">
    <div class="container">
        <h2 class="text-center fw-bold mb-2">Why Choose Our Hostel</h2>
        <div class="section-divider"></div>
        <p class="text-center text-muted mb-5">We provide the best living experience for students</p>
        <div class="row g-4 stagger-children">
            <div class="col-md-4">
                <div class="facility-card">
                    <i class="bi bi-shield-lock text-primary"></i>
                    <h5>24/7 Security</h5>
                    <p class="text-muted mb-0 small">CCTV surveillance, security guards, and secure access control for your safety.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="facility-card">
                    <i class="bi bi-wifi text-primary"></i>
                    <h5>High-Speed WiFi</h5>
                    <p class="text-muted mb-0 small">Stay connected with reliable high-speed internet throughout the hostel.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="facility-card">
                    <i class="bi bi-cup-hot text-primary"></i>
                    <h5>Healthy Meals</h5>
                    <p class="text-muted mb-0 small">Nutritious and hygienic meals prepared fresh daily in our mess.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="facility-card">
                    <i class="bi bi-book text-primary"></i>
                    <h5>Study Area</h5>
                    <p class="text-muted mb-0 small">Dedicated quiet study areas and a well-stocked library for academic success.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="facility-card">
                    <i class="bi bi-bandaid text-primary"></i>
                    <h5>Medical Support</h5>
                    <p class="text-muted mb-0 small">Emergency medical assistance and tie-ups with nearby hospitals.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="facility-card">
                    <i class="bi bi-activity text-primary"></i>
                    <h5>Recreation</h5>
                    <p class="text-muted mb-0 small">Indoor games, TV room, and outdoor sports facilities for relaxation.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Rooms -->
<section class="bg-light py-5 scroll-fade">
    <div class="container">
        <h2 class="text-center fw-bold mb-2">Our Rooms</h2>
        <div class="section-divider"></div>
        <p class="text-center text-muted mb-5">Choose from our range of comfortable rooms</p>
        <div class="row g-4">
            <?php if (count($featuredRooms) > 0): ?>
                <?php foreach ($featuredRooms as $room): ?>
                <div class="col-md-3 col-6">
                    <div class="card room-card h-100">
                        <div class="card-body">
                            <span class="badge bg-primary mb-2">Room <?= sanitize($room['room_no']) ?></span>
                            <h5 class="card-title"><?= sanitize($room['room_type']) ?></h5>
                            <p class="card-text small text-muted">
                                <i class="bi bi-people"></i> Capacity: <?= $room['capacity'] ?><br>
                                <i class="bi bi-currency-rupee"></i> Rs.<?= number_format($room['fee_per_month'], 0) ?>/month
                            </p>
                            <?php
                            $statusBadge = match($room['status']) {
                                'Available' => 'bg-success',
                                'Full' => 'bg-danger',
                                'Maintenance' => 'bg-warning text-dark',
                                default => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?= $statusBadge ?>"><?= $room['status'] ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12"><div class="alert alert-info text-center">No rooms available.</div></div>
            <?php endif; ?>
        </div>
        <div class="text-center mt-4">
            <a href="<?= BASE_URL ?>/public/rooms.php" class="btn btn-primary btn-lg px-4">View All Rooms <i class="bi bi-arrow-right"></i></a>
        </div>
    </div>
</section>

<!-- Gallery -->
<section class="py-5 scroll-fade">
    <div class="container">
        <h2 class="text-center fw-bold mb-2">Gallery</h2>
        <div class="section-divider"></div>
        <p class="text-center text-muted mb-5">A glimpse of our hostel life</p>
        <div class="row g-3">
            <?php if (count($gallery) > 0): ?>
                <?php foreach ($gallery as $img): ?>
                <div class="col-md-4 col-6">
                    <div class="gallery-item">
                        <img src="<?= BASE_URL ?>/uploads/<?= sanitize($img['image']) ?>" class="w-100" style="height:200px;object-fit:cover" alt="<?= sanitize($img['title']) ?>">
                        <div class="p-2">
                            <small class="fw-medium"><?= sanitize($img['title']) ?></small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <div class="col-md-4 col-6">
                <div class="gallery-item">
                    <div class="bg-light d-flex align-items-center justify-content-center" style="height:200px">
                        <i class="bi bi-image text-muted fs-1"></i>
                    </div>
                    <div class="p-2"><small class="fw-medium">Hostel Building</small></div>
                </div>
            </div>
            <div class="col-md-4 col-6">
                <div class="gallery-item">
                    <div class="bg-light d-flex align-items-center justify-content-center" style="height:200px">
                        <i class="bi bi-image text-muted fs-1"></i>
                    </div>
                    <div class="p-2"><small class="fw-medium">Common Room</small></div>
                </div>
            </div>
            <div class="col-md-4 col-6">
                <div class="gallery-item">
                    <div class="bg-light d-flex align-items-center justify-content-center" style="height:200px">
                        <i class="bi bi-image text-muted fs-1"></i>
                    </div>
                    <div class="p-2"><small class="fw-medium">Dining Hall</small></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <div class="text-center mt-4">
            <a href="<?= BASE_URL ?>/public/gallery.php" class="btn btn-outline-primary">View Full Gallery <i class="bi bi-arrow-right"></i></a>
        </div>
    </div>
</section>

<!-- Testimonials -->
<?php if (count($testimonials) > 0): ?>
<section class="bg-light py-5 scroll-fade">
    <div class="container">
        <h2 class="text-center fw-bold mb-2">Student Testimonials</h2>
        <div class="section-divider"></div>
        <p class="text-center text-muted mb-5">What our students say about us</p>
        <div class="row g-4">
            <?php foreach ($testimonials as $t): ?>
            <div class="col-md-4">
                <div class="testimonial-card">
                    <p class="mb-3"><?= sanitize($t['content']) ?></p>
                    <div class="d-flex align-items-center gap-2">
                        <div class="d-flex text-warning small">
                            <?php for ($i = 0; $i < $t['rating']; $i++): ?><i class="bi bi-star-fill"></i><?php endfor; ?>
                        </div>
                        <div class="fw-medium small">- <?= sanitize($t['name']) ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Events -->
<?php if (count($events) > 0): ?>
<section class="py-5 scroll-fade">
    <div class="container">
        <h2 class="text-center fw-bold mb-2">Hostel Events</h2>
        <div class="section-divider"></div>
        <p class="text-center text-muted mb-5">Upcoming events at our hostel</p>
        <div class="row g-4">
            <?php foreach ($events as $e): ?>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="text-center flex-shrink-0 bg-primary text-white rounded-3 px-3 py-2">
                                <div class="fw-bold fs-5"><?= date('d', strtotime($e['event_date'])) ?></div>
                                <div class="small"><?= date('M', strtotime($e['event_date'])) ?></div>
                            </div>
                            <div>
                                <h5 class="mb-1"><?= sanitize($e['title']) ?></h5>
                                <?php if ($e['location']): ?><small class="text-muted"><i class="bi bi-geo-alt"></i> <?= sanitize($e['location']) ?></small><?php endif; ?>
                            </div>
                        </div>
                        <p class="small text-muted mb-0"><?= sanitize(mb_substr($e['description'], 0, 150)) ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Latest Notices -->
<?php if (count($notices) > 0): ?>
<section class="bg-light py-5 scroll-fade">
    <div class="container">
        <h2 class="text-center fw-bold mb-2">Latest Notices</h2>
        <div class="section-divider"></div>
        <div class="row g-3">
            <?php foreach ($notices as $n): ?>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <span class="badge bg-<?= match($n['priority']){'Critical'=>'danger','Urgent'=>'warning','Normal'=>'info'} ?> mb-2"><?= $n['priority'] ?></span>
                        <h6 class="card-title"><?= sanitize($n['title']) ?></h6>
                        <p class="card-text small text-muted"><?= sanitize(mb_substr($n['content'], 0, 120)) ?></p>
                        <small class="text-muted"><?= date('d M Y', strtotime($n['publish_date'])) ?></small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- FAQ -->
<?php if (count($faqs) > 0): ?>
<section class="py-5 scroll-fade">
    <div class="container">
        <h2 class="text-center fw-bold mb-2">Frequently Asked Questions</h2>
        <div class="section-divider"></div>
        <p class="text-center text-muted mb-5">Find answers to common questions</p>
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="accordion" id="faqAccordion">
                    <?php foreach ($faqs as $i => $faq): ?>
                    <div class="accordion-item border-0 mb-2">
                        <h2 class="accordion-header">
                            <button class="accordion-button <?= $i > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= $faq['id'] ?>">
                                <?= sanitize($faq['question']) ?>
                            </button>
                        </h2>
                        <div id="faq<?= $faq['id'] ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted"><?= sanitize($faq['answer']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Management Team -->
<?php if (count($staff) > 0): ?>
<section class="bg-light py-5 scroll-fade">
    <div class="container">
        <h2 class="text-center fw-bold mb-2">Our Management</h2>
        <div class="section-divider"></div>
        <p class="text-center text-muted mb-5">Dedicated team ensuring the best experience</p>
        <div class="row g-4 justify-content-center">
            <?php foreach ($staff as $s): ?>
            <div class="col-md-4 col-6">
                <div class="card text-center h-100 border-0">
                    <div class="card-body">
                        <i class="<?= sanitize($s['icon']) ?> text-primary" style="font-size:2.5rem"></i>
                        <h5 class="mt-3 fw-bold"><?= sanitize($s['name']) ?></h5>
                        <p class="text-muted mb-1 small"><?= sanitize($s['designation']) ?></p>
                        <?php if ($s['description']): ?>
                        <small class="text-muted"><?= sanitize($s['description']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Contact -->
<section class="py-5 scroll-fade">
    <div class="container">
        <h2 class="text-center fw-bold mb-2">Get In Touch</h2>
        <div class="section-divider"></div>
        <div class="row g-4 mt-3">
            <div class="col-md-5">
                <div class="feature-list">
                    <li><i class="bi bi-geo-alt"></i> YSR Engineering College, Korrapadu Road, Proddatur - 516360</li>
                    <li><i class="bi bi-telephone"></i> +91 8564 254770</li>
                    <li><i class="bi bi-envelope"></i> principal.yvuce@gmail.com</li>
                    <li><i class="bi bi-clock"></i> Office Hours: 9:00 AM - 5:00 PM (Mon-Sat)</li>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <a href="#" class="btn btn-outline-primary btn-sm rounded-circle p-2" style="width:38px;height:38px"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="btn btn-outline-primary btn-sm rounded-circle p-2" style="width:38px;height:38px"><i class="bi bi-twitter-x"></i></a>
                    <a href="#" class="btn btn-outline-primary btn-sm rounded-circle p-2" style="width:38px;height:38px"><i class="bi bi-instagram"></i></a>
                    <a href="#" class="btn btn-outline-primary btn-sm rounded-circle p-2" style="width:38px;height:38px"><i class="bi bi-youtube"></i></a>
                </div>
            </div>
            <div class="col-md-7">
                <iframe src="https://www.google.com/maps?q=YSR+Engineering+College+Proddatur&output=embed" width="100%" height="280" style="border:0; border-radius:12px;" allowfullscreen loading="lazy"></iframe>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
