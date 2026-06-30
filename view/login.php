<?php
$title = 'SecureBounty | Sign In';
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
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap"
        rel="stylesheet">
    <link href="view/assets/style.css" rel="stylesheet">
    <script>
        (function () {
            const stored = localStorage.getItem('theme');
            const preferred = stored || (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
            document.documentElement.setAttribute('data-theme', preferred);
        })();
    </script>
    <style>
        html,
        body {
            height: 100%;
        }

        body {
            display: flex;
            align-items: stretch;
            min-height: 100vh;
        }
    </style>
</head>

<body>

    <!-- ── Split layout ──────────────────────────────────────────── -->
    <div class="d-flex w-100" style="min-height:100vh;">

        <!-- Left — branding panel -->
        <div class="d-none d-lg-flex flex-column justify-content-between p-5"
            style="width:42%; background:var(--card); border-right:1px solid var(--border); flex-shrink:0;">

            <!-- Logo -->
            <a href="index.php?page=home" class="d-inline-flex align-items-center gap-2 text-decoration-none">
                <div class="brand-icon">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <span style="font-size:15px; font-weight:700; color:var(--foreground); letter-spacing:-0.02em;">
                    Secure<span style="color:var(--accent);">Bounty</span>
                </span>
            </a>

            <!-- Quote block -->
            <div>
                <blockquote style="border-left:3px solid var(--accent); padding-left:20px; margin:0;">
                    <p
                        style="font-size:16px; line-height:1.6; color:var(--foreground); font-weight:500; margin-bottom:12px;">
                        "Security research without structure is just noise. SecureBounty turns findings into action."
                    </p>
                    <footer style="font-size:13px; color:var(--muted-foreground);">
                        — Platform design principle
                    </footer>
                </blockquote>
            </div>

            <!-- Bottom stats -->
            <div class="d-flex gap-5">
                <div class="stat-block">
                    <div class="stat-number">500+</div>
                    <div class="stat-label">Researchers</div>
                </div>
                <div class="stat-block" style="border-left-color:var(--border);">
                    <div class="stat-number">4.2h</div>
                    <div class="stat-label">Avg triage</div>
                </div>
            </div>

        </div>

        <!-- Right — form panel -->
        <div class="d-flex flex-column justify-content-center align-items-center flex-grow-1 p-4 p-md-5">

            <!-- Mobile logo -->
            <a href="index.php?page=home" class="d-flex d-lg-none align-items-center gap-2 text-decoration-none mb-5">
                <div class="brand-icon">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <span style="font-size:15px; font-weight:700; color:var(--foreground);">
                    Secure<span style="color:var(--accent);">Bounty</span>
                </span>
            </a>

            <div style="width:100%; max-width:400px;">

                <div class="mb-5">
                    <h1 style="font-size:24px; margin-bottom:6px;">Sign in</h1>
                    <p class="text-muted" style="font-size:14px;">
                        Don't have an account?
                        <a href="index.php?page=register" style="color:var(--accent); font-weight:600;">Register</a>
                    </p>
                </div>

                <form method="POST" action="index.php?page=process-login">
                    <?php if (!empty($csrfToken)): ?>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="email" class="form-label-custom">Email address</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-envelope input-icon"></i>
                            <input type="email" id="email" name="email"
                                class="form-input-custom<?php echo !empty($errors['email']) ? ' input-error' : ''; ?>"
                                placeholder="you@domain.com" autocomplete="email" required>
                        </div>
                        <?php if (!empty($errors['email'])): ?>
                            <p class="error-msg"><?php echo htmlspecialchars($errors['email']); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="mb-5">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="password" class="form-label-custom mb-0">Password</label>
                            <a href="#" style="font-size:13px; color:var(--accent);">Forgot password?</a>
                        </div>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" id="password" name="password"
                                class="form-input-custom<?php echo !empty($errors['password']) ? ' input-error' : ''; ?>"
                                placeholder="••••••••••" autocomplete="current-password" required>
                        </div>
                        <?php if (!empty($errors['password'])): ?>
                            <p class="error-msg"><?php echo htmlspecialchars($errors['password']); ?></p>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn-primary-solid w-100 justify-content-center"
                        style="height:40px; font-size:14px;">
                        Sign in <i class="fa-solid fa-arrow-right fa-sm ms-1"></i>
                    </button>
                </form>

            </div>

            <!-- Back link -->
            <a href="index.php?page=home" class="mt-5 d-inline-flex align-items-center gap-2"
                style="font-size:13px; color:var(--muted-foreground); text-decoration:none;">
                <i class="fa-solid fa-arrow-left fa-xs"></i> Back to home
            </a>

        </div>
    </div>

    <!-- Flash message toasts -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <?php
    // Render toasts from controller-provided flash variables
    // (session values are already cleared by the controller before the view loads)
    $toasts = [];
    if (!empty($success)) {
        $toasts[] = ['type' => 'success', 'message' => $success];
    }
    if (!empty($error)) {
        $toasts[] = ['type' => 'error', 'message' => $error];
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
</body>

</html>