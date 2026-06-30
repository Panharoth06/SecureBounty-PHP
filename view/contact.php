<?php
// Form handling
$errors = [];
$success = '';
$name = $email = $subject = $message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS));
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL));
    $subject = trim(filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_SPECIAL_CHARS));
    $message = trim(filter_input(INPUT_POST, 'message', FILTER_SANITIZE_SPECIAL_CHARS));

    if (empty($name))
        $errors['name'] = 'Full name is required.';
    if (!$email)
        $errors['email'] = 'A valid email address is required.';
    if (empty($subject))
        $errors['subject'] = 'Subject is required.';
    if (empty($message))
        $errors['message'] = 'Message is required.';

    if (empty($errors)) {
        $success = 'Your message has been sent. Our team will respond shortly.';
        $name = $email = $subject = $message = '';
    }
}

include 'view/components/header.php';
?>

<!-- ── Page header ───────────────────────────────────────────── -->
<section class="section-sm" style="padding-top:80px;">
    <div class="container" style="max-width:640px;">
        <p class="hero-eyebrow">Get in touch</p>
        <h1 style="font-size:30px; margin-bottom:12px;">Contact the team</h1>
        <p class="text-muted" style="font-size:14px;">Have questions about programs, pricing, or the platform? Drop us a
            message below.</p>
    </div>
</section>

<!-- ── Warning callout ───────────────────────────────────────── -->
<div class="container mb-4" style="max-width:960px;">
    <div class="callout callout-warning">
        <span class="callout-icon flex-shrink-0 mt-1">
            <i class="fa-solid fa-triangle-exclamation" style="font-size:14px;"></i>
        </span>
        <div>
            <strong style="color:var(--foreground);">Do not submit vulnerability reports here.</strong>
            <span class="ms-1">Use this form only for general inquiries. To report a security issue, please
                <a href="index.php?page=register" style="color:var(--warning);">register as a researcher</a> and submit
                through the dashboard.</span>
        </div>
    </div>
</div>

<!-- ── Main layout ───────────────────────────────────────────── -->
<section class="section-sm" style="padding-top:0;">
    <div class="container" style="max-width:960px;">
        <div class="row g-5">

            <!-- Form -->
            <div class="col-lg-7">
                <div class="card-surface">
                    <h2 style="font-size:16px; margin-bottom:24px;">Send a message</h2>

                    <?php if ($success): ?>
                        <div class="callout callout-success mb-4">
                            <span class="callout-icon"><i class="fa-solid fa-circle-check"
                                    style="font-size:14px;"></i></span>
                            <span><?php echo $success; ?></span>
                        </div>
                    <?php endif; ?>

                    <form action="index.php?page=contact" method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label-custom">Full name</label>
                                <input type="text" id="name" name="name"
                                    class="form-input-custom <?php echo isset($errors['name']) ? 'is-error' : ''; ?>"
                                    value="<?php echo htmlspecialchars($name); ?>" placeholder="Jane Doe">
                                <?php if (isset($errors['name'])): ?>
                                    <p class="error-msg"><?php echo $errors['name']; ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label-custom">Email address</label>
                                <input type="email" id="email" name="email"
                                    class="form-input-custom <?php echo isset($errors['email']) ? 'is-error' : ''; ?>"
                                    value="<?php echo htmlspecialchars((string) $email); ?>"
                                    placeholder="you@company.com">
                                <?php if (isset($errors['email'])): ?>
                                    <p class="error-msg"><?php echo $errors['email']; ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="col-12">
                                <label for="subject" class="form-label-custom">Subject</label>
                                <input type="text" id="subject" name="subject"
                                    class="form-input-custom <?php echo isset($errors['subject']) ? 'is-error' : ''; ?>"
                                    value="<?php echo htmlspecialchars($subject); ?>"
                                    placeholder="e.g. Organisation demo request">
                                <?php if (isset($errors['subject'])): ?>
                                    <p class="error-msg"><?php echo $errors['subject']; ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="col-12">
                                <label for="message" class="form-label-custom">Message</label>
                                <textarea id="message" name="message" rows="6"
                                    class="form-input-custom <?php echo isset($errors['message']) ? 'is-error' : ''; ?>"
                                    placeholder="How can we help?"><?php echo htmlspecialchars($message); ?></textarea>
                                <?php if (isset($errors['message'])): ?>
                                    <p class="error-msg"><?php echo $errors['message']; ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="col-12 pt-1">
                                <button type="submit" class="btn-primary-solid" style="height:40px;">
                                    <i class="fa-solid fa-paper-plane" style="font-size:12px;"></i> Send message
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Contact info -->
            <div class="col-lg-5 d-flex flex-column gap-4">

                <div class="card-surface">
                    <h3 style="font-size:16px; margin-bottom:20px;">Contact details</h3>
                    <div class="d-flex flex-column gap-4">
                        <?php
                        $contacts = [
                            ['fa-envelope', 'Security email', 'secops@securebounty.example.com'],
                            ['fa-phone', 'Support line', '+1 (800) 555-SECURE'],
                            ['fa-map-location-dot', 'Headquarters', '100 Cyber Security Pkwy, Suite 404, San Francisco, CA'],
                        ];
                        foreach ($contacts as $c):
                            ?>
                            <div class="d-flex gap-3 align-items-start">
                                <div style="color:var(--accent); width:18px; margin-top:2px; text-align:center;">
                                    <i class="fa-solid <?php echo $c[0]; ?>" style="font-size:13px;"></i>
                                </div>
                                <div>
                                    <p
                                        style="font-size:12px; font-weight:500; text-transform:uppercase; letter-spacing:0.05em; color:var(--muted-foreground); margin-bottom:4px;">
                                        <?php echo $c[1]; ?></p>
                                    <p style="font-size:14px; color:var(--foreground); margin-bottom:0;">
                                        <?php echo $c[2]; ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- PGP Key -->
                <div class="card-surface">
                    <h3 style="font-size:16px; margin-bottom:8px;">PGP public key</h3>
                    <p class="text-muted mb-3" style="font-size:13px;">Encrypt sensitive messages before sending.</p>
                    <pre style="font-size:12px; margin:0;"><code>-----BEGIN PGP PUBLIC KEY BLOCK-----
mQENBF2Qx5wBCAD3z9n+x5p5K4VnI3zY
jJ6v7vX3vW3mXpY6F8J7j8w1B2mX8zP1
JqP5fG6mY5vW3mXpY6F8J7j8w1B2mX8z
=sD8w
-----END PGP PUBLIC KEY BLOCK-----</code></pre>
                    <a href="#" class="btn-ghost mt-3 d-inline-flex"
                        style="height:32px; font-size:13px; padding:6px 12px;">
                        <i class="fa-solid fa-download" style="font-size:11px;"></i> Download .asc
                    </a>
                </div>

            </div>
        </div>
    </div>
</section>

<?php include 'view/components/footer.php'; ?>