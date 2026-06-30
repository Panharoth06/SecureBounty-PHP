<?php
/**
 * User Profile View
 *
 * Displays the authenticated user's profile information.
 * Variables available:
 *   $title - Page title
 *   $activePage - Active navigation item
 *   $user - User data array (id, first_name, last_name, email, role_name, status, created_at)
 */
?>
<?php include __DIR__ . '/components/header.php'; ?>

<section class="section" style="padding-top:120px;">
    <div class="container" style="max-width:600px;">
        <h1 style="font-size:24px; margin-bottom:24px;">My Profile</h1>

        <div
            style="background:var(--card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:32px;">
            <div class="mb-4">
                <label class="form-label-custom">Name</label>
                <p style="font-size:14px; color:var(--foreground); margin:0;">
                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES, 'UTF-8'); ?>
                </p>
            </div>

            <div class="mb-4">
                <label class="form-label-custom">Email</label>
                <p style="font-size:14px; color:var(--foreground); margin:0;">
                    <?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>
                </p>
            </div>

            <div class="mb-4">
                <label class="form-label-custom">Role</label>
                <p style="font-size:14px; color:var(--foreground); margin:0;">
                    <?php echo htmlspecialchars($user['role_name'], ENT_QUOTES, 'UTF-8'); ?>
                </p>
            </div>

            <div class="mb-4">
                <label class="form-label-custom">Status</label>
                <p style="font-size:14px; color:var(--foreground); margin:0;">
                    <?php echo htmlspecialchars($user['status'], ENT_QUOTES, 'UTF-8'); ?>
                </p>
            </div>

            <div>
                <label class="form-label-custom">Member since</label>
                <p style="font-size:14px; color:var(--foreground); margin:0;">
                    <?php echo htmlspecialchars(date('F j, Y', strtotime($user['created_at'])), ENT_QUOTES, 'UTF-8'); ?>
                </p>
            </div>
        </div>

        <div class="mt-4">
            <a href="index.php?page=logout" class="btn-primary-solid" style="font-size:14px;">
                <i class="fa-solid fa-right-from-bracket"></i> Sign out
            </a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/components/footer.php'; ?>