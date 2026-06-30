<?php
/**
 * SecureBounty — Admin Dashboard View
 *
 * Displays platform-wide statistics: total users, total programs,
 * recent activity feed, and pending reports count/table.
 *
 * Expected variables (set by controller):
 *   $title        (string) — Page title
 *   $activePage   (string) — Sidebar highlight slug
 *   $totalUsers   (int)    — Total registered users
 *   $totalPrograms(int)    — Total programs on platform
 *   $pendingReports(int)   — Count of reports with status "pending"
 *   $recentActivity(array) — Recent activity log entries [{action, target_entity, created_at, user_name}]
 *   $pendingReportsList(array) — Pending reports [{id, title, program_title, severity, created_at}]
 *
 * @see Requirement 13.1, 13.4
 */

$title ??= 'Admin Dashboard';
$activePage ??= 'dashboard';
$totalUsers ??= 0;
$totalPrograms ??= 0;
$pendingReports ??= 0;
$recentActivity ??= [];
$pendingReportsList ??= [];

include __DIR__ . '/../components/layout.php';
?>

<!-- Page Header -->
<div style="margin-bottom: var(--space-lg);">
    <h1 style="font-size: var(--text-display); font-weight: 700; color: var(--foreground); margin: 0;">
        Dashboard
    </h1>
    <p style="font-size: var(--text-small); color: var(--muted-foreground); margin-top: var(--space-xs);">
        Platform overview and recent activity
    </p>
</div>

<!-- Stat Cards Grid -->
<div
    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-lg); margin-bottom: var(--space-xl);">
    <?php
    $label = 'Total Users';
    $value = $totalUsers;
    $icon = 'users';
    include __DIR__ . '/../components/stat-card.php';
    ?>

    <?php
    $label = 'Total Programs';
    $value = $totalPrograms;
    $icon = 'shield';
    include __DIR__ . '/../components/stat-card.php';
    ?>

    <?php
    $label = 'Pending Reports';
    $value = $pendingReports;
    $icon = 'file-text';
    include __DIR__ . '/../components/stat-card.php';
    ?>

    <?php
    $label = 'Active Programs';
    $value = $totalPrograms;
    $icon = 'activity';
    include __DIR__ . '/../components/stat-card.php';
    ?>
</div>

<!-- Recent Activity Section -->
<div
    style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: var(--space-lg); margin-bottom: var(--space-lg);">
    <h2
        style="font-size: var(--text-heading); font-weight: 600; color: var(--foreground); margin: 0 0 var(--space-md) 0;">
        <i data-lucide="activity"
            style="width: 18px; height: 18px; display: inline-block; vertical-align: middle; margin-right: var(--space-sm);"></i>
        Recent Activity
    </h2>

    <?php if (empty($recentActivity)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <i data-lucide="inbox"></i>
            </div>
            <p class="empty-state-title">No recent activity</p>
            <p class="empty-state-description">Activity will appear here as users interact with the platform.</p>
        </div>
    <?php else: ?>
        <ul style="list-style: none; padding: 0; margin: 0;">
            <?php foreach ($recentActivity as $activity): ?>
                <li
                    style="display: flex; align-items: center; gap: var(--space-md); padding: var(--space-sm) 0; border-bottom: 1px solid var(--border);">
                    <span
                        style="font-family: 'JetBrains Mono', monospace; font-size: var(--text-small); color: var(--muted-foreground); white-space: nowrap;">
                        <?= htmlspecialchars($activity['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <span style="font-size: var(--text-body); color: var(--foreground);">
                        <strong>
                            <?= htmlspecialchars($activity['user_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </strong>
                        <?= htmlspecialchars($activity['action'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        <span style="color: var(--muted-foreground);">
                            <?= htmlspecialchars($activity['target_entity'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<!-- Pending Reports Table -->
<div
    style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: var(--space-lg);">
    <h2
        style="font-size: var(--text-heading); font-weight: 600; color: var(--foreground); margin: 0 0 var(--space-md) 0;">
        <i data-lucide="file-text"
            style="width: 18px; height: 18px; display: inline-block; vertical-align: middle; margin-right: var(--space-sm);"></i>
        Pending Reports
    </h2>

    <?php if (empty($pendingReportsList)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <i data-lucide="inbox"></i>
            </div>
            <p class="empty-state-title">No pending reports</p>
            <p class="empty-state-description">Reports awaiting review will appear here.</p>
        </div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Program</th>
                    <th>Severity</th>
                    <th>Submitted</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingReportsList as $report): ?>
                    <tr>
                        <td style="color: var(--foreground); font-weight: 500;">
                            <?= htmlspecialchars($report['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($report['program_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td>
                            <span
                                class="badge badge-severity-<?= htmlspecialchars($report['severity'] ?? 'medium', ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars(ucfirst($report['severity'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td style="font-size: var(--text-small); color: var(--muted-foreground);">
                            <?= htmlspecialchars($report['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../components/layout_end.php'; ?>