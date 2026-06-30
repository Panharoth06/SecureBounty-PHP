<?php
$role  = filter_input(INPUT_GET, 'role', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'researcher';
$title = 'SecureBounty | Create Account';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="view/assets/style.css" rel="stylesheet">
    <script>
        (function() {
            const stored = localStorage.getItem('theme');
            const preferred = stored || (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
            document.documentElement.setAttribute('data-theme', preferred);
        })();
    </script>
</head>
<body>

<!-- ── Split layout ──────────────────────────────────────────── -->
<div class="d-flex w-100" style="min-height:100vh;">

    <!-- Left — branding panel -->
    <div class="d-none d-lg-flex flex-column justify-content-between p-5"
         style="width:38%; background:var(--card); border-right:1px solid var(--border); flex-shrink:0;">

        <!-- Logo -->
        <a href="index.php?page=home" class="d-inline-flex align-items-center gap-2 text-decoration-none">
            <div class="brand-icon">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <span style="font-size:15px; font-weight:700; color:var(--foreground); letter-spacing:-0.02em;">
                Secure<span style="color:var(--accent);">Bounty</span>
            </span>
        </a>

        <!-- Role highlights -->
        <div class="d-flex flex-column gap-4">
            <p style="font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:var(--muted-foreground); margin:0;">
                Choose your role
            </p>

            <div id="panel-researcher"
                 style="padding:20px; border-radius:var(--radius-lg); border:1px solid var(--accent-ring); background:var(--accent-subtle); transition:all 150ms ease;">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <i class="fa-solid fa-user-secret" style="color:var(--accent); font-size:18px;"></i>
                    <span style="font-weight:600; font-size:14px; color:var(--foreground);">Security Researcher</span>
                </div>
                <p style="font-size:13px; color:var(--muted-foreground); margin:0; line-height:1.5;">
                    Hunt vulnerabilities, earn reputation points, and collect bounties on accepted reports.
                </p>
            </div>

            <div id="panel-owner"
                 style="padding:20px; border-radius:var(--radius-lg); border:1px solid var(--border); background:transparent; transition:all 150ms ease;">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <i class="fa-solid fa-building-shield" style="color:var(--muted-foreground); font-size:18px;"></i>
                    <span style="font-weight:600; font-size:14px; color:var(--foreground);">Program Owner</span>
                </div>
                <p style="font-size:13px; color:var(--muted-foreground); margin:0; line-height:1.5;">
                    Launch bounty programs, review incoming reports, and coordinate remediation.
                </p>
            </div>
        </div>

        <!-- Bottom note -->
        <p style="font-size:13px; color:var(--muted-foreground); margin:0; line-height:1.6;">
            By registering you agree to our
            <a href="index.php?page=docs#engagement" style="color:var(--accent);">rules of engagement</a>
            and terms of service.
        </p>

    </div>

    <!-- Right — form panel -->
    <div class="d-flex flex-column justify-content-center align-items-center flex-grow-1 p-4 p-md-5">

        <!-- Mobile logo -->
        <a href="index.php?page=home"
           class="d-flex d-lg-none align-items-center gap-2 text-decoration-none mb-5">
            <div class="brand-icon">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <span style="font-size:15px; font-weight:700; color:var(--foreground);">
                Secure<span style="color:var(--accent);">Bounty</span>
            </span>
        </a>

        <div style="width:100%; max-width:480px;">

            <div class="mb-5">
                <h1 style="font-size:24px; margin-bottom:6px;">Create your account</h1>
                <p class="text-muted" style="font-size:14px;">
                    Already registered?
                    <a href="index.php?page=login" style="color:var(--accent); font-weight:600;">Sign in</a>
                </p>
            </div>

            <!-- Role toggle -->
            <div class="mb-4">
                <p class="form-label-custom mb-2">I am a…</p>
                <div class="row g-2">
                    <div class="col-6">
                        <input type="radio" class="btn-check" name="role" id="roleResearcher"
                               value="researcher" <?php echo $role !== 'owner' ? 'checked' : ''; ?> autocomplete="off">
                        <label class="d-flex align-items-center gap-2 p-3 w-100 role-label"
                               for="roleResearcher"
                               style="background:var(--muted); border:1px solid var(--border); border-radius:var(--radius-md); cursor:pointer; transition:all 150ms ease; font-size:14px; font-weight:500; color:var(--muted-foreground);">
                            <i class="fa-solid fa-user-secret"></i>
                            Researcher
                        </label>
                    </div>
                    <div class="col-6">
                        <input type="radio" class="btn-check" name="role" id="roleOwner"
                               value="owner" <?php echo $role === 'owner' ? 'checked' : ''; ?> autocomplete="off">
                        <label class="d-flex align-items-center gap-2 p-3 w-100 role-label"
                               for="roleOwner"
                               style="background:var(--muted); border:1px solid var(--border); border-radius:var(--radius-md); cursor:pointer; transition:all 150ms ease; font-size:14px; font-weight:500; color:var(--muted-foreground);">
                            <i class="fa-solid fa-building"></i>
                            Program Owner
                        </label>
                    </div>
                </div>
            </div>

            <form method="POST" action="index.php?page=process-register">
                <?php if (!empty($csrfToken)): ?>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="first_name" class="form-label-custom">First name</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-user input-icon"></i>
                            <input type="text" id="first_name" name="first_name" class="form-input-custom<?php echo !empty($errors['first_name']) ? ' input-error' : ''; ?>"
                                   placeholder="Jane"
                                   value="<?php echo htmlspecialchars($oldInput['first_name'] ?? ''); ?>"
                                   autocomplete="given-name" required>
                        </div>
                        <?php if (!empty($errors['first_name'])): ?>
                            <p class="error-msg"><?php echo htmlspecialchars($errors['first_name']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label for="last_name" class="form-label-custom">Last name</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-user input-icon"></i>
                            <input type="text" id="last_name" name="last_name" class="form-input-custom<?php echo !empty($errors['last_name']) ? ' input-error' : ''; ?>"
                                   placeholder="Doe"
                                   value="<?php echo htmlspecialchars($oldInput['last_name'] ?? ''); ?>"
                                   autocomplete="family-name" required>
                        </div>
                        <?php if (!empty($errors['last_name'])): ?>
                            <p class="error-msg"><?php echo htmlspecialchars($errors['last_name']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-12">
                        <label for="email" class="form-label-custom">Email address</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-envelope input-icon"></i>
                            <input type="email" id="email" name="email" class="form-input-custom<?php echo !empty($errors['email']) ? ' input-error' : ''; ?>"
                                   placeholder="you@domain.com"
                                   value="<?php echo htmlspecialchars($oldInput['email'] ?? ''); ?>"
                                   autocomplete="email" required>
                        </div>
                        <?php if (!empty($errors['email'])): ?>
                            <p class="error-msg"><?php echo htmlspecialchars($errors['email']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label for="password" class="form-label-custom">Password</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" id="password" name="password" class="form-input-custom<?php echo !empty($errors['password']) ? ' input-error' : ''; ?>"
                                   placeholder="••••••••••" autocomplete="new-password" required>
                        </div>
                        <?php if (!empty($errors['password'])): ?>
                            <p class="error-msg"><?php echo htmlspecialchars($errors['password']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label for="confirm_password" class="form-label-custom">Confirm password</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input-custom<?php echo !empty($errors['confirm_password']) ? ' input-error' : ''; ?>"
                                   placeholder="••••••••••" autocomplete="new-password" required>
                        </div>
                        <?php if (!empty($errors['confirm_password'])): ?>
                            <p class="error-msg"><?php echo htmlspecialchars($errors['confirm_password']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Hidden role field synced with radio toggle -->
                <input type="hidden" name="role" id="roleHidden" value="<?php echo htmlspecialchars($oldInput['role'] ?? $role); ?>">

                <div class="d-flex align-items-start gap-2 mt-4 mb-4">
                    <input type="checkbox" id="terms" name="terms"
                           style="margin-top:3px; flex-shrink:0; accent-color:var(--accent);" required>
                    <label for="terms" class="text-muted" style="font-size:13px; line-height:1.5;">
                        I agree to the
                        <a href="index.php?page=docs#engagement" style="color:var(--accent);">rules of engagement</a>
                        and terms of service.
                    </label>
                </div>

                <button type="submit"
                        class="btn-primary-solid w-100 justify-content-center"
                        style="height:40px; font-size:14px;">
                    Create account <i class="fa-solid fa-arrow-right fa-sm ms-1"></i>
                </button>
            </form>

        </div>

        <!-- Back link -->
        <a href="index.php?page=home"
           class="mt-5 d-inline-flex align-items-center gap-2"
           style="font-size:13px; color:var(--muted-foreground); text-decoration:none;">
            <i class="fa-solid fa-arrow-left fa-xs"></i> Back to home
        </a>

    </div>
</div>

<!-- Flash message toasts -->
<script src="https://unpkg.com/lucide@latest"></script>
<?php
// Render toasts from controller-provided flash variables
$toasts = [];
if (!empty($success)) {
    $toasts[] = ['type' => 'success', 'message' => $success];
}
// If there are field errors, show a general error toast
if (!empty($errors)) {
    $toasts[] = ['type' => 'error', 'message' => 'Please fix the errors below and try again.'];
}
if (!empty($toasts)): ?>
<div class="toast-container" id="toastContainer">
    <?php foreach ($toasts as $index => $toast): ?>
        <div class="toast toast-<?php echo htmlspecialchars($toast['type']); ?> active" id="toast-<?php echo $index; ?>"
            role="alert" aria-live="polite">
            <div class="toast-content">
                <div class="toast-icon">
                    <?php if ($toast['type'] === 'success'): ?>
                        <i data-lucide="check-circle"></i>
                    <?php else: ?>
                        <i data-lucide="alert-circle"></i>
                    <?php endif; ?>
                </div>
                <p class="toast-message">
                    <?php echo htmlspecialchars($toast['message']); ?>
                </p>
                <button class="toast-close" onclick="dismissToast('toast-<?php echo $index; ?>')"
                    aria-label="Dismiss notification">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="toast-progress"></div>
        </div>
    <?php endforeach; ?>
</div>
<script>
    function dismissToast(id) {
        const toast = document.getElementById(id);
        if (toast) {
            toast.classList.remove('active');
            setTimeout(() => toast.remove(), 200);
        }
    }
    document.querySelectorAll('.toast.active').forEach(function (toast) {
        setTimeout(function () {
            toast.classList.remove('active');
            setTimeout(() => toast.remove(), 200);
        }, 5000);
    });
</script>
<?php endif; ?>
<script>lucide.createIcons();</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sync role card highlight with radio + left panel + hidden field
(function () {
    const radios = document.querySelectorAll('input[name="role"]');
    const labels = document.querySelectorAll('.role-label');
    const panelR = document.getElementById('panel-researcher');
    const panelO = document.getElementById('panel-owner');
    const roleHidden = document.getElementById('roleHidden');

    function update() {
        const val = document.querySelector('input[name="role"]:checked')?.value;

        // Sync hidden form field
        if (roleHidden) {
            roleHidden.value = val || 'researcher';
        }

        labels.forEach(l => {
            l.style.borderColor = 'var(--border)';
            l.style.background  = 'var(--muted)';
            l.style.color       = 'var(--muted-foreground)';
        });

        const activeLabel = document.querySelector('.role-label[for="role' +
            (val === 'owner' ? 'Owner' : 'Researcher') + '"]');
        if (activeLabel) {
            activeLabel.style.borderColor = 'var(--accent)';
            activeLabel.style.background  = 'var(--accent-subtle)';
            activeLabel.style.color       = 'var(--accent)';
        }

        // Sync left branding panel
        if (panelR && panelO) {
            if (val === 'owner') {
                panelO.style.borderColor = 'var(--accent-ring)';
                panelO.style.background  = 'var(--accent-subtle)';
                panelR.style.borderColor = 'var(--border)';
                panelR.style.background  = 'transparent';
            } else {
                panelR.style.borderColor = 'var(--accent-ring)';
                panelR.style.background  = 'var(--accent-subtle)';
                panelO.style.borderColor = 'var(--border)';
                panelO.style.background  = 'transparent';
            }
        }
    }

    radios.forEach(r => r.addEventListener('change', update));
    update();
})();
</script>
</body>
</html>
