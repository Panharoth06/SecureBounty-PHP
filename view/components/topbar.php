<?php
/**
 * SecureBounty — Topbar Component (Authenticated Layout)
 *
 * Expects:
 *   $_SESSION['user_id'], $_SESSION['first_name'], $_SESSION['last_name']
 *   $unreadCount (int) — injected by layout or controller (defaults to 0)
 *
 * @see Requirement 13.1, 13.2, 13.3
 */

$unreadCount ??= 0;
$firstName = $_SESSION['first_name'] ?? 'User';
$lastName = $_SESSION['last_name'] ?? '';
$initials = strtoupper(mb_substr($firstName, 0, 1) . mb_substr($lastName, 0, 1));
$fullName = htmlspecialchars($firstName . ' ' . $lastName, ENT_QUOTES, 'UTF-8');
?>

<header class="app-topbar">
    <!-- Left: Sidebar toggle (mobile) + Logo -->
    <div class="topbar-left">
        <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Toggle sidebar">
            <i data-lucide="menu"></i>
        </button>
        <a href="index.php?page=dashboard" class="topbar-brand">
            <div class="brand-icon">
                <i data-lucide="shield"></i>
            </div>
            <span class="brand-name">Secure<span class="brand-accent">Bounty</span></span>
        </a>
    </div>

    <!-- Right: Actions -->
    <div class="topbar-right">
        <!-- Theme toggle -->
        <button class="theme-toggle" id="themeToggleApp" type="button" aria-label="Toggle theme">
            <i data-lucide="sun" class="theme-icon-light"></i>
            <i data-lucide="moon" class="theme-icon-dark"></i>
        </button>

        <!-- Notifications -->
        <div class="topbar-notifications-wrapper" id="notificationsWrapper">
            <button class="topbar-icon-btn" id="notificationsToggle" type="button" aria-label="Notifications"
                aria-expanded="false">
                <i data-lucide="bell"></i>
                <?php if ($unreadCount > 0): ?>
                    <span class="notification-badge">
                        <?= $unreadCount > 99 ? '99+' : (int) $unreadCount ?>
                    </span>
                <?php endif; ?>
            </button>
            <?php include __DIR__ . '/notifications-dropdown.php'; ?>
        </div>

        <!-- User menu -->
        <div class="topbar-user-menu" id="userMenuWrapper">
            <button class="topbar-user-btn" id="userMenuToggle" type="button" aria-label="User menu"
                aria-expanded="false">
                <span class="avatar">
                    <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span class="topbar-user-name">
                    <?= $fullName ?>
                </span>
                <i data-lucide="chevron-down" class="topbar-chevron"></i>
            </button>
            <div class="topbar-dropdown" id="userDropdown">
                <a href="index.php?page=profile" class="dropdown-item">
                    <i data-lucide="user"></i> Profile
                </a>
                <a href="index.php?page=profile-edit" class="dropdown-item">
                    <i data-lucide="edit-3"></i> Edit Profile
                </a>
                <?php if (!empty($_SESSION['user_id'])): ?>
                    <a href="index.php?page=public-profile&amp;id=<?= (int) $_SESSION['user_id'] ?>" class="dropdown-item">
                        <i data-lucide="user-circle"></i> Public Profile
                    </a>
                <?php endif; ?>
                <hr class="dropdown-divider">
                <a href="index.php?page=logout" class="dropdown-item dropdown-item-danger">
                    <i data-lucide="log-out"></i> Logout
                </a>
            </div>
        </div>
    </div>
</header>