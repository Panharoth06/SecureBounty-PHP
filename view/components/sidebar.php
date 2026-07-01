<?php
/**
 * SecureBounty — Sidebar Navigation Component (Authenticated Layout)
 *
 * Expects:
 *   $activePage (string) — current page slug for highlight
 *   $_SESSION['role_id'] — 1=Admin, 2=Program_Owner, 3=Researcher
 *
 * @see Requirement 13.1, 13.2, 13.3
 */

$activePage ??= '';
$roleId = $_SESSION['role_id'] ?? 0;

/**
 * Helper: render a sidebar nav item
 */
function sidebarItem(string $page, string $icon, string $label, string $activePage): string
{
    $isActive = ($page === $activePage) ? ' sidebar-item-active' : '';
    $href = 'index.php?page=' . htmlspecialchars($page, ENT_QUOTES, 'UTF-8');
    return '<a href="' . $href . '" class="sidebar-item' . $isActive . '">'
        . '<i data-lucide="' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '"></i>'
        . '<span class="sidebar-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
        . '</a>';
}
?>

<aside class="app-sidebar" id="appSidebar">
    <nav class="sidebar-nav">
        <?php if ($roleId === 1): // Admin ?>
            <div class="sidebar-group">
                <p class="sidebar-group-label">Navigation</p>
                <?= sidebarItem('dashboard', 'layout-dashboard', 'Dashboard', $activePage) ?>
                <?= sidebarItem('admin-users', 'users', 'Users', $activePage) ?>
                <?= sidebarItem('admin-programs', 'shield', 'Programs', $activePage) ?>
                <?= sidebarItem('admin-activity-logs', 'activity', 'Activity Logs', $activePage) ?>
                <?= sidebarItem('leaderboard', 'trophy', 'Leaderboard', $activePage) ?>
            </div>
        <?php elseif ($roleId === 2): // Program Owner ?>
            <div class="sidebar-group">
                <p class="sidebar-group-label">Navigation</p>
                <?= sidebarItem('dashboard', 'layout-dashboard', 'Dashboard', $activePage) ?>
                <?= sidebarItem('programs', 'shield', 'My Programs', $activePage) ?>
                <?= sidebarItem('reports', 'file-text', 'Reports', $activePage) ?>
                <?= sidebarItem('leaderboard', 'trophy', 'Leaderboard', $activePage) ?>
            </div>
        <?php elseif ($roleId === 3): // Researcher ?>
            <div class="sidebar-group">
                <p class="sidebar-group-label">Navigation</p>
                <?= sidebarItem('dashboard', 'layout-dashboard', 'Dashboard', $activePage) ?>
                <?= sidebarItem('programs', 'shield', 'Programs', $activePage) ?>
                <?= sidebarItem('my-reports', 'file-text', 'My Reports', $activePage) ?>
                <?= sidebarItem('saved-programs', 'bookmark', 'Saved Programs', $activePage) ?>
                <?= sidebarItem('leaderboard', 'trophy', 'Leaderboard', $activePage) ?>
            </div>
        <?php endif; ?>
    </nav>
</aside>