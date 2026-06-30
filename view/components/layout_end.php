</div><!-- /.app-content -->
</main><!-- /.app-main -->
</div><!-- /.app-layout-wrapper -->

<!-- Lucide Icons -->
<script src="https://unpkg.com/lucide@latest"></script>
<script>lucide.createIcons();</script>

<!-- Flash message toasts -->
<?php include __DIR__ . '/toast.php'; ?>
<!-- Re-init Lucide for toast icons -->
<script>lucide.createIcons();</script>

<!-- App Layout Scripts -->
<script>
    (function () {
        // ── Theme Toggle ──────────────────────────────────────────
        const themeBtn = document.getElementById('themeToggleApp');
        if (themeBtn) {
            themeBtn.addEventListener('click', function () {
                const current = document.documentElement.getAttribute('data-theme');
                const next = current === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', next);
                localStorage.setItem('theme', next);
            });
        }

        // ── User Dropdown ─────────────────────────────────────────
        const userToggle = document.getElementById('userMenuToggle');
        const userDropdown = document.getElementById('userDropdown');

        // ── Notifications Dropdown ────────────────────────────────
        const notifToggle = document.getElementById('notificationsToggle');
        const notifDropdown = document.getElementById('notificationsDropdown');

        if (userToggle && userDropdown) {
            userToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                const isOpen = userDropdown.classList.toggle('open');
                userToggle.setAttribute('aria-expanded', isOpen);
                // Close notifications dropdown when user menu opens
                if (notifDropdown) notifDropdown.classList.remove('open');
                if (notifToggle) notifToggle.setAttribute('aria-expanded', 'false');
            });
            document.addEventListener('click', function () {
                userDropdown.classList.remove('open');
                userToggle.setAttribute('aria-expanded', 'false');
            });
        }

        if (notifToggle && notifDropdown) {
            notifToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                const isOpen = notifDropdown.classList.toggle('open');
                notifToggle.setAttribute('aria-expanded', isOpen);
                // Close user menu when notifications dropdown opens
                if (userDropdown) userDropdown.classList.remove('open');
                if (userToggle) userToggle.setAttribute('aria-expanded', 'false');
            });
            notifDropdown.addEventListener('click', function (e) {
                e.stopPropagation();
            });
            document.addEventListener('click', function () {
                notifDropdown.classList.remove('open');
                notifToggle.setAttribute('aria-expanded', 'false');
            });
        }

        // ── Sidebar Toggle (mobile) ───────────────────────────────
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('appSidebar');
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', function () {
                sidebar.classList.toggle('sidebar-open');
            });
        }
    })();
</script>
</body>

</html>