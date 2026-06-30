<?php
/**
 * SecureBounty PageController
 * Manages loading views and passing metadata (titles, active states).
 */
class PageController
{

    public function home()
    {
        $title = 'SecureBounty | Bug Bounty & Vulnerability Disclosure Platform';
        $activePage = 'home';
        include 'view/home.php';
    }

    public function about()
    {
        $title = 'SecureBounty | About Us';
        $activePage = 'about';
        include 'view/about.php';
    }

    public function contact()
    {
        $title = 'SecureBounty | Contact Us';
        $activePage = 'contact';
        include 'view/contact.php';
    }

    public function docs()
    {
        $title = 'SecureBounty | Platform Documentation';
        $activePage = 'docs';
        include 'view/docs.php';
    }

    public function login()
    {
        $title = 'SecureBounty | Secure Login';
        $activePage = 'login';
        include 'view/login.php';
    }

    public function register()
    {
        $title = 'SecureBounty | Account Registration';
        $activePage = 'register';
        include 'view/register.php';
    }

    public function notFound()
    {
        $title = 'SecureBounty | Page Not Found';
        $activePage = '';

        http_response_code(404);
        include 'view/components/header.php';
        ?>
        <section class="section" style="padding-top:120px;">
            <div class="container text-center" style="max-width:480px;">
                <div style="color:var(--muted-foreground); margin-bottom:24px;">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size:32px;"></i>
                </div>
                <h1 style="font-size:30px; margin-bottom:8px;">404 — Page not found</h1>
                <p class="text-muted" style="font-size:14px; margin-bottom:32px;">
                    The page you're looking for doesn't exist or has been moved.
                </p>
                <a href="index.php?page=home" class="btn-primary-solid">
                    <i class="fa-solid fa-arrow-left" style="font-size:12px;"></i> Back to home
                </a>
            </div>
        </section>
        <?php
        include 'view/components/footer.php';
    }
}
