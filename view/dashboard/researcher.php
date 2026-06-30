<?php
/**
 * SecureBounty — Researcher Dashboard View
 *
 * Displays researcher-specific data: enrolled programs, submitted reports,
 * saved programs, and recent notifications.
 *
 * Expected variables (set by controller):
 *   $title            (string) — Page title
 *   $activePage       (string) — Sidebar highlight slug
 *   $enrolledPrograms (int)    — Number of programs the researcher is enrolled in
 *   $submittedReports (int)    — Total reports submitted by this researcher
 *   $savedPrograms    (int)    — Number of bookmarked programs
 *   $notifications    (array)  — Recent notifications [{message, type, created_at, is_read}]
 *
 * @see Requirement 13.3, 13.4
 */

$title ??= 'Researcher Dashboard';
$activePage ??= 'dashboard';
$enrolledPrograms ??= 0;
$submittedReports ??= 0;
$savedPrograms ??= 0;
$notifications ??= [];

include __DIR__ . '/../components/layout.php';
?>

<!-- Page Header -->
<div style="margin-bottom: var(--space-lg);">
    <h1 style="font-size: var(--text-display); font-weight: 700; color: var(--foreground); margin: 0;">
        Dashboard
    </h1>
    <p style="font-size: var(--text-small); color: var(--muted-foreground); margin-top: var(--space-xs);">
        Your research activity and notifications
    </p>
</div>

<!-- Stat Cards Grid -->
<div
    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-lg); margin-bottom: var(--space-xl);">
    <?php
    $label = 'Enrolled Programs';
    $value = $enrolledPrograms;
    $icon = 'shield';
    include __DIR__ . '/../components/stat-card.php';
    ?>

    <?php
    $label = 'Submitted Reports';
    $value = $submittedReports;
    $icon = 'file-text';
    include __DIR__ . '/../components/stat-card.php';
    ?>

    <?php
    $label = 'Saved Programs';
    $value = $savedPrograms;
    $icon = 'bookmark';
    include __DIR__ . '/../components/stat-card.php';
    ?>
</div>

<!-- Notifications Section -->
<div
    style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: var(--space-lg);">
    <h2
        style="font-size: var(--text-heading); font-weight: 600; color: var(--foreground); margin: 0 0 var(--space-md) 0;">
        <i data-lucide="bell"
            style="width: 18px; height: 18px; display: inline-block; vertical-align: middle; margin-right: var(--space-sm);"></i>
        Recent Notifications
    </h2>

    <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <i data-lucide="bell-off"></i>
            </div>
            <p class="empty-state-title">No notifications</p>
            <p class="empty-state-description">You'll be notified when your reports are reviewed or comments are added.</p>
        </div>
    <?php else: ?>
        <ul style="list-style: none; padding: 0; margin: 0;">
            <?php foreach ($notifications as $notification): ?>
                <li
                    style="display: flex; align-items: center; gap: var(--space-md); padding: var(--space-sm) var(--space-md); border-bottom: 1px solid var(--border); border-radius: var(--radius-md); <?= empty($notification['is_read']) ? 'background: var(--muted);' : '' ?>">
                    <i data-lucide="<?= htmlspecialchars($notification['type'] === 'comment.new' ? 'message-square' : 'info', ENT_QUOTES, 'UTF-8') ?>"
                        style="width: 16px; height: 16px; color: var(--muted-foreground); flex-shrink: 0;"></i>
                    <span style="font-size: var(--text-body); color: var(--foreground); flex: 1;">
                        <?= htmlspecialchars($notification['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <span
                        style="font-family: 'JetBrains Mono', monospace; font-size: var(--text-small); color: var(--muted-foreground); white-space: nowrap;">
                        <?= htmlspecialchars($notification['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../components/layout_end.php'; ?>