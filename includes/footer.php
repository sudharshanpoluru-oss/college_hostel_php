<footer class="mt-5 py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-building text-primary"></i> <?= SITE_NAME ?></h5>
                <p class="small text-secondary mb-3">The official hostel portal of YSR Engineering College of YVU, Proddatur — providing safe, comfortable, and affordable accommodation for students. We are committed to creating a home-like environment that fosters academic excellence.</p>
                <div class="d-flex gap-2">
                    <a href="#" class="social-link"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="social-link"><i class="bi bi-twitter-x"></i></a>
                    <a href="#" class="social-link"><i class="bi bi-instagram"></i></a>
                    <a href="#" class="social-link"><i class="bi bi-youtube"></i></a>
                    <a href="#" class="social-link"><i class="bi bi-linkedin"></i></a>
                </div>
            </div>
            <div class="col-lg-2 col-md-4">
                <h6>Quick Links</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="<?= BASE_URL ?>/public/index.php" class="text-decoration-none">Home</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>/public/about.php" class="text-decoration-none">About Us</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>/public/rooms.php" class="text-decoration-none">Our Rooms</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>/public/amenities.php" class="text-decoration-none">Amenities</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>/public/gallery.php" class="text-decoration-none">Gallery</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-4">
                <h6>For Students</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="<?= BASE_URL ?>/auth/login.php?role=student" class="text-decoration-none">Student Login</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>/auth/register.php" class="text-decoration-none">Register Now</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>/public/contact.php" class="text-decoration-none">Contact Us</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>/public/faq.php" class="text-decoration-none">FAQ</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-4">
                <h6>Contact</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><i class="bi bi-geo-alt text-primary me-1"></i> YSR Engineering College, Korrapadu Road, Proddatur - 516360</li>
                    <li class="mb-2"><i class="bi bi-telephone text-primary me-1"></i> +91 8564 254770</li>
                    <li class="mb-2"><i class="bi bi-envelope text-primary me-1"></i> principal.yvuce@gmail.com</li>
                </ul>
            </div>
        </div>
        <hr class="my-4 opacity-25">
        <div class="row align-items-center">
            <div class="col-md-6">
                <p class="small mb-0 text-secondary">&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</p>
            </div>
            <div class="col-md-6 text-md-end">
                <a href="#" class="small text-decoration-none me-3">Privacy Policy</a>
                <a href="#" class="small text-decoration-none me-3">Terms of Service</a>
                <a href="<?= BASE_URL ?>/public/contact.php" class="small text-decoration-none">Support</a>
            </div>
        </div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/script.js"></script>
</body>
</html>
