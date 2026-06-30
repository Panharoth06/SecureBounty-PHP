<?php
/**
 * SecureBounty — Header / Navbar Component
 * Expects: $title (string), $activePage (string)
 */
$title = $title ?? 'SecureBounty';
$activePage = $activePage ?? 'home';

function navActive(string $page, string $active): string
{
    return $page === $active ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="SecureBounty — A centralized vulnerability disclosure and bug bounty management platform.">
    <title><?php echo htmlspecialchars($title); ?></title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Lucide Icons (SVG icon set) -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- FontAwesome (legacy support) -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Inter font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap"
        rel="stylesheet">
    <!-- Design System -->
    <link href="view/assets/style.css" rel="stylesheet">

    <!-- Theme initialization (prevents flash) -->
    <script>
        (function () {
            const stored = localStorage.getItem('theme');
            const preferred = stored || (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
            document.documentElement.setAttribute('data-theme', preferred);
        })();
    </script>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
        <div class="container">

            <!-- Brand -->
            <a class="navbar-brand" href="index.php?page=home">
                <div class="brand-icon">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <span class="brand-name">Secure<span class="brand-accent">Bounty</span></span>
            </a>

            <!-- Mobile toggle -->
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse"
                data-bs-target="#navMain" aria-controls="navMain" aria-expanded="false" aria-label="Toggle navigation">
                <i class="fa-solid fa-bars" style="color: var(--muted-foreground); font-size:1rem;"></i>
            </button>

            <div class="collapse navbar-collapse" id="navMain">
                <!-- Center links -->
                <ul class="navbar-nav mx-auto gap-1">
                    <li class="nav-item">
                        <a class="nav-link <?php echo navActive('home', $activePage); ?>"
                            href="index.php?page=home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo navActive('about', $activePage); ?>"
                            href="index.php?page=about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo navActive('docs', $activePage); ?>"
                            href="index.php?page=docs">Docs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo navActive('contact', $activePage); ?>"
                            href="index.php?page=contact">Contact</a>
                    </li>
                </ul>

                <!-- Auth actions + Theme toggle -->
                <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
                    <button class="theme-toggle" id="themeToggle" type="button" aria-label="Toggle theme">
                        <i class="fa-solid fa-sun" id="themeIcon"></i>
                    </button>
                    <?php if (!empty($_SESSION['user_id'])): ?>
                        <a href="index.php?page=dashboard" class="btn-ghost">
                            <i class="fa-solid fa-gauge fa-sm"></i> Dashboard
                        </a>
                        <a href="index.php?page=profile" class="btn-ghost" title="Profile">
                            <?php
                            $navFirst = $_SESSION['first_name'] ?? '';
                            $navLast = $_SESSION['last_name'] ?? '';
                            $navInitials = strtoupper(mb_substr($navFirst, 0, 1) . mb_substr($navLast, 0, 1));
                            ?>
                            <span class="avatar avatar-sm">
                                <?php echo htmlspecialchars($navInitials ?: 'U'); ?>
                            </span>
                        </a>
                        <a href="index.php?page=logout" class="btn-primary-solid">Sign out</a>
                    <?php else: ?>
                        <a href="index.php?page=login" class="btn-ghost">Sign in</a>
                        <a href="index.php?page=register" class="btn-primary-solid">Get started</a>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </nav>