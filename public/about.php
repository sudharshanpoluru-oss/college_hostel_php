<?php
require_once __DIR__ . '/../includes/session.php';
$title = 'About Us';
include __DIR__ . '/../includes/header.php';
?>

<section class="bg-primary text-white py-5">
    <div class="container text-center">
        <h1 class="display-4 fw-bold">About <?= SITE_NAME ?></h1>
        <p class="lead">Providing quality accommodation for students since 2010</p>
    </div>
</section>

<section class="bg-white py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h2 class="fw-bold">Welcome to Our Hostel</h2>
                <p><?= SITE_NAME ?> is a premier student accommodation facility dedicated to providing a comfortable, safe, and supportive living environment for students. Located in the heart of the city, our hostel offers easy access to major educational institutions, libraries, and recreational areas.</p>
                <p>We understand the needs of students and strive to create a home-like atmosphere where academic excellence can flourish. With modern amenities, dedicated staff, and a vibrant community, we ensure that every student feels welcomed and supported throughout their academic journey.</p>
            </div>
            <div class="col-lg-6">
                <div class="bg-light p-4 rounded shadow-sm">
                    <i class="bi bi-building text-primary" style="font-size: 4rem;"></i>
                    <h4 class="mt-2">Our Legacy</h4>
                    <p class="text-muted">Over 15 years of excellence in student accommodation, hosting thousands of students from diverse backgrounds and disciplines.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="bg-light py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <i class="bi bi-bullseye text-primary" style="font-size: 3rem;"></i>
                        <h3 class="fw-bold mt-3">Our Mission</h3>
                        <p class="text-muted">To provide a safe, comfortable, and conducive living environment that supports students in their academic pursuits and personal growth. We are committed to fostering a community of respect, learning, and mutual support.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <i class="bi bi-eye text-primary" style="font-size: 3rem;"></i>
                        <h3 class="fw-bold mt-3">Our Vision</h3>
                        <p class="text-muted">To be the leading student accommodation provider recognized for excellence in service, safety, and student satisfaction. We aim to create a benchmark for quality hostel living across the region.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="bg-white py-5">
    <div class="container">
        <h2 class="fw-bold text-center mb-4">Rules & Regulations</h2>
        <p class="text-center text-muted mb-4">Guidelines to ensure a harmonious living environment</p>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <ol class="list-group list-group-numbered">
                    <li class="list-group-item">Students must maintain silence in the study hours (7:00 PM - 6:00 AM).</li>
                    <li class="list-group-item">Visitors are not allowed inside the rooms without prior permission from the warden.</li>
                    <li class="list-group-item">Consumption of alcohol, tobacco, or any intoxicating substances is strictly prohibited.</li>
                    <li class="list-group-item">Students must return to the hostel before the designated curfew time (9:00 PM).</li>
                    <li class="list-group-item">Damage to hostel property will result in fines and disciplinary action.</li>
                    <li class="list-group-item">Room changes are allowed only with the warden's approval.</li>
                    <li class="list-group-item">Electrical appliances are not permitted without authorization.</li>
                    <li class="list-group-item">Students are responsible for keeping their rooms and common areas clean.</li>
                    <li class="list-group-item">Mess fees must be paid before the 10th of every month.</li>
                    <li class="list-group-item">Any grievances should be reported to the warden or through the complaint system.</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="bg-light py-5">
    <div class="container">
        <h2 class="fw-bold text-center mb-4">Why Choose Us</h2>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm text-center">
                    <div class="card-body">
                        <i class="bi bi-shield-check text-success" style="font-size: 2.5rem;"></i>
                        <h5 class="fw-bold mt-2">Safe & Secure</h5>
                        <p class="text-muted small">24/7 security, CCTV surveillance, and restricted entry ensure your safety at all times.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm text-center">
                    <div class="card-body">
                        <i class="bi bi-wifi text-primary" style="font-size: 2.5rem;"></i>
                        <h5 class="fw-bold mt-2">Modern Amenities</h5>
                        <p class="text-muted small">High-speed WiFi, gym, library, and fully equipped common rooms for your convenience.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm text-center">
                    <div class="card-body">
                        <i class="bi bi-people text-warning" style="font-size: 2.5rem;"></i>
                        <h5 class="fw-bold mt-2">Community Living</h5>
                        <p class="text-muted small">A vibrant community of students from diverse backgrounds fostering friendships and collaboration.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm text-center">
                    <div class="card-body">
                        <i class="bi bi-geo-alt text-danger" style="font-size: 2.5rem;"></i>
                        <h5 class="fw-bold mt-2">Prime Location</h5>
                        <p class="text-muted small">Close to universities, colleges, libraries, and city center with easy transportation access.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm text-center">
                    <div class="card-body">
                        <i class="bi bi-cup-hot text-info" style="font-size: 2.5rem;"></i>
                        <h5 class="fw-bold mt-2">Healthy Meals</h5>
                        <p class="text-muted small">Nutritious and hygienic meals prepared under strict quality standards in our mess.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm text-center">
                    <div class="card-body">
                        <i class="bi bi-headset text-primary" style="font-size: 2.5rem;"></i>
                        <h5 class="fw-bold mt-2">Dedicated Support</h5>
                        <p class="text-muted small">24/7 staff support, online complaint system, and regular parent communication for peace of mind.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="bg-white py-5">
    <div class="container">
        <h2 class="fw-bold text-center mb-4">Our Staff</h2>
        <p class="text-center text-muted mb-4">Dedicated team ensuring your comfort and safety</p>
        <div class="row g-4">
            <div class="col-md-3">
                <div class="card text-center h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <i class="bi bi-person-vcard text-primary" style="font-size: 3rem;"></i>
                        <h5 class="fw-bold mt-2">Dr. Suresh Kumar</h5>
                        <span class="badge bg-primary">Chief Warden</span>
                        <p class="small text-muted mt-2">Oversees all hostel operations</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <i class="bi bi-person-badge text-success" style="font-size: 3rem;"></i>
                        <h5 class="fw-bold mt-2">Mrs. Anita Sharma</h5>
                        <span class="badge bg-success">Deputy Warden</span>
                        <p class="small text-muted mt-2">Student welfare and discipline</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <i class="bi bi-tools text-warning" style="font-size: 3rem;"></i>
                        <h5 class="fw-bold mt-2">Mr. Rajesh Singh</h5>
                        <span class="badge bg-warning text-dark">Caretaker</span>
                        <p class="small text-muted mt-2">Maintenance and facility management</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <i class="bi bi-shield-check text-danger" style="font-size: 3rem;"></i>
                        <h5 class="fw-bold mt-2">Mr. Vijay Patel</h5>
                        <span class="badge bg-danger">Security Head</span>
                        <p class="small text-muted mt-2">Security and access control</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
