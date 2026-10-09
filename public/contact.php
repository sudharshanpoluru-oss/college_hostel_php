<?php
require_once __DIR__ . '/../includes/session.php';
$title = 'Contact Us';
include __DIR__ . '/../includes/header.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');

    $errors = [];
    if (empty($name)) $errors[] = 'Name is required.';
    if (empty($email)) $errors[] = 'Email is required.';
    elseif (!validateEmail($email)) $errors[] = 'Please enter a valid email address.';
    if (empty($message)) $errors[] = 'Message is required.';

    if (count($errors) > 0) {
        $error = implode('<br>', $errors);
    } else {
        try {
            $stmt = db()->prepare("INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $subject, $message]);
            $success = 'Thank you for your message! We will get back to you soon.';
        } catch (Exception $e) {
            $error = 'Something went wrong. Please try again later.';
        }
    }
}
?>

<section class="bg-primary text-white py-4">
    <div class="container">
        <h1 class="fw-bold">Contact Us</h1>
        <p class="lead mb-0">We'd love to hear from you</p>
    </div>
</section>

<section class="bg-white py-5">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-6">
                <h2 class="fw-bold mb-4">Send Us a Message</h2>
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle"></i> <?= $success ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle"></i> <?= $error ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required value="<?= sanitize($_POST['name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required value="<?= sanitize($_POST['email'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?= sanitize($_POST['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Subject</label>
                            <input type="text" name="subject" class="form-control" value="<?= sanitize($_POST['subject'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Message <span class="text-danger">*</span></label>
                            <textarea name="message" class="form-control" rows="5" required><?= sanitize($_POST['message'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-send"></i> Send Message</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="col-lg-6">
                <h2 class="fw-bold mb-4">Get In Touch</h2>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <div class="d-flex mb-3">
                            <i class="bi bi-geo-alt text-primary me-3" style="font-size: 1.5rem;"></i>
                            <div>
                                <h6 class="fw-bold mb-1">Address</h6>
                                <p class="mb-0 text-muted">YSR Engineering College of YVU, Korrapadu Road,<br>Proddatur, Kadapa District - 516360, Andhra Pradesh</p>
                            </div>
                        </div>
                        <div class="d-flex mb-3">
                            <i class="bi bi-telephone text-primary me-3" style="font-size: 1.5rem;"></i>
                            <div>
                                <h6 class="fw-bold mb-1">Phone</h6>
                                <p class="mb-0 text-muted">+91 8564 254770</p>
                            </div>
                        </div>
                        <div class="d-flex">
                            <i class="bi bi-envelope text-primary me-3" style="font-size: 1.5rem;"></i>
                            <div>
                                <h6 class="fw-bold mb-1">Email</h6>
                                <p class="mb-0 text-muted">principal.yvuce@gmail.com</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="rounded overflow-hidden shadow-sm">
                    <iframe src="https://www.google.com/maps?q=YSR+Engineering+College+Proddatur&output=embed" width="100%" height="300" style="border:0;" allowfullscreen loading="lazy"></iframe>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
