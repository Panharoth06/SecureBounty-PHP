<!-- ── Footer ─────────────────────────────────────────────── -->
<footer class="footer-custom">
    <div class="container">
        <div class="row g-5 mb-5">

            <!-- Brand column -->
            <div class="col-lg-4">
                <a href="index.php?page=home" class="footer-brand mb-3 d-inline-flex">
                    <div class="brand-icon">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    Secure<span class="brand-accent">Bounty</span>
                </a>
                <p class="text-muted mt-3" style="max-width:280px; line-height:1.6; font-size:14px;">
                    A centralized vulnerability disclosure platform connecting organizations with security researchers
                    worldwide.
                </p>
                <div class="d-flex gap-2 mt-4">
                    <a href="#" class="social-btn" aria-label="GitHub"><i class="fa-brands fa-github"></i></a>
                    <a href="#" class="social-btn" aria-label="Twitter"><i class="fa-brands fa-twitter"></i></a>
                    <a href="#" class="social-btn" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
                    <a href="#" class="social-btn" aria-label="Discord"><i class="fa-brands fa-discord"></i></a>
                </div>
            </div>

            <!-- Platform links -->
            <div class="col-6 col-md-3 col-lg-2">
                <p class="footer-section-label">Platform</p>
                <ul class="list-unstyled d-flex flex-column gap-2">
                    <li><a href="index.php?page=home" class="footer-link">Home</a></li>
                    <li><a href="index.php?page=about" class="footer-link">About</a></li>
                    <li><a href="index.php?page=docs" class="footer-link">Docs</a></li>
                    <li><a href="index.php?page=contact" class="footer-link">Contact</a></li>
                </ul>
            </div>

            <!-- Resources links -->
            <div class="col-6 col-md-3 col-lg-2">
                <p class="footer-section-label">Resources</p>
                <ul class="list-unstyled d-flex flex-column gap-2">
                    <li><a href="index.php?page=docs#engagement" class="footer-link">Rules of Engagement</a></li>
                    <li><a href="index.php?page=docs#rewards" class="footer-link">Reward Tiers</a></li>
                    <li><a href="#" class="footer-link">Leaderboard</a></li>
                    <li><a href="#" class="footer-link">API Reference</a></li>
                </ul>
            </div>

            <!-- CTA column -->
            <div class="col-lg-4">
                <p class="footer-section-label">Get started today</p>
                <p class="text-muted mb-3" style="line-height:1.6; font-size:14px;">
                    Launch a program or submit your first vulnerability report in minutes.
                </p>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="index.php?page=register" class="btn-primary-solid">Join platform</a>
                    <a href="index.php?page=docs" class="btn-ghost">Read docs</a>
                </div>
            </div>

        </div>

        <hr class="divider">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 pt-4">
            <p class="text-muted mb-0" style="font-size:13px;">
                &copy; <?php echo date('Y'); ?> SecureBounty. All rights reserved.
            </p>
            <div class="d-flex gap-4">
                <a href="#" class="footer-link" style="font-size:13px;">Privacy Policy</a>
                <a href="#" class="footer-link" style="font-size:13px;">Terms of Service</a>
            </div>
        </div>

    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Theme toggle + Navbar shrink -->
<script>
    (function () {
        // Theme toggle
        const toggle = document.getElementById('themeToggle');
        const icon = document.getElementById('themeIcon');

        function updateIcon() {
            const theme = document.documentElement.getAttribute('data-theme');
            if (icon) {
                icon.className = theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
            }
        }

        if (toggle) {
            toggle.addEventListener('click', function () {
                const current = document.documentElement.getAttribute('data-theme');
                const next = current === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', next);
                localStorage.setItem('theme', next);
                updateIcon();
            });
        }

        updateIcon();

        // Navbar shrink on scroll
        const nav = document.querySelector('.navbar-custom');
        if (nav) {
            const onScroll = () => nav.classList.toggle('scrolled', window.scrollY > 40);
            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();
        }
    })();
</script>

</body>

</html>