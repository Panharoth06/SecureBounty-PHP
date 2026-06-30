<?php
/**
 * SecureBounty — Notifications Page View
 *
 * Lists all notifications for the authenticated user.
 * Notifications are marked as read when this page is viewed.
 *
 * Expected variables (set by controller/router):
 *   $title         (string) — Page title
 *   $activePage    (string) — Sidebar highlight slug
 *   $notifications (array)  — All notifications [{id, message, type, reference_entity, reference_id, is_read, created_at}]
 *
 * @see Requirement 7.1, 8.5, 9.3, 13.3
 */

$title ??= 'Notifications';
$activePage ??= 'notifications';
$notifications ??= [];

include __DIR__ . '/components/layout.php';

/**
 * Map notification type to a lucide icon name.
 */
function notifPageIcon(string $type): string
{
    return match (true) {
        str_starts_with($type, 'report.submitted') => 'file-plus',
        str_starts_with($type, 'report.status') => 'file-check',
        str_starts_with($type, 'comment.') => 'message-square',
        str_starts_with($type, 'program.') => 'shield',
        default => 'bell',
    };
}
?>

<!-- Page Header -->
<div style="margin-bottom: var(--space-lg);">
    <h1 style="font-size: var(--text-display); font-weight: 700; color: var(--foreground); margin: 0;">
        <i data-lucide="bell"
            style="width: 24px; height: 24px; display: inline-block; vertical-align: middle; margin-right: var(--space-sm);"></i>
        Notifications
    </h1>
    <p style="font-size: var(--text-small); color: var(--muted-foreground); margin-top: var(--space-xs);">
        All your notifications in one place
    </p>
</div>

<!-- Notifications List -->
<div
    style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden;">
    <?php if (empty($notifications)): ?>
        <div class="empty-state" style="padding: var(--space-xl);">
            <div class="empty-state-icon">
                <i data-lucide="bell-off"></i>
            </div>
            <p class="empty-state-title">No notifications</p>
            <p class="empty-state-description">You'll be notified when your reports are reviewed or comments are added.</p>
        </div>
    <?php else: ?>
        <ul style="list-style: none; padding: 0; margin: 0;">
            <?php foreach ($notifications as $notification):
                $entity = $notification['reference_entity'] ?? null;
                $refId = isset($notification['reference_id']) ? (int) $notification['reference_id'] : null;
                $targetUrl = match ($entity) {
                    'report' => 'index.php?page=report-detail&id=' . $refId,
                    'program' => 'index.php?page=program-detail&id=' . $refId,
                    default => '#',
                };
                $clickUrl = empty($notification['is_read']) && $targetUrl !== '#'
                    ? 'index.php?page=notification-click&id=' . (int) $notification['id'] . '&redirect=' . urlencode($targetUrl)
                    : $targetUrl;
                ?>
                <a href="<?= htmlspecialchars($clickUrl, ENT_QUOTES, 'UTF-8') ?>"
                    style="text-decoration:none; color:inherit; display:block;">
                    <li
                        style="display: flex; align-items: flex-start; gap: var(--space-md); padding: var(--space-md) var(--space-lg); border-bottom: 1px solid var(--border); cursor:pointer; transition:background 0.1s; <?= empty($notification['is_read']) ? 'background: var(--muted);' : '' ?>">
                        <i data-lucide="<?= htmlspecialchars(notifPageIcon($notification['type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            style="width: 18px; height: 18px; color: var(--muted-foreground); flex-shrink: 0; margin-top: 2px;"></i>
                        <div style="flex: 1; min-width: 0;">
                            <p style="margin: 0; font-size: var(--text-body); color: var(--foreground);">
                                <?= htmlspecialchars($notification['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </p>
                            <span
                                style="font-family: 'JetBrains Mono', monospace; font-size: var(--text-small); color: var(--muted-foreground);">
                                <?= htmlspecialchars($notification['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                        <?php if (empty($notification['is_read'])): ?>
                            <span
                                style="width: 8px; height: 8px; border-radius: 50%; background: var(--primary); flex-shrink: 0; margin-top: 6px;"
                                title="Unread"></span>
                        <?php endif; ?>
                    </li>
                </a>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/components/layout_end.php'; ?>