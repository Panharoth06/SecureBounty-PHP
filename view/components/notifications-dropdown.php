<?php
/**
 * SecureBounty — Notifications Dropdown Component
 *
 * Renders a dropdown panel showing the most recent notifications.
 * Included inside the topbar near the bell icon.
 *
 * Expects (set by layout.php):
 *   $__recentNotifications (array) — Recent notifications [{id, message, type, reference_entity, reference_id, is_read, created_at}]
 *   $unreadCount (int) — Number of unread notifications
 *
 * @see Requirement 7.1, 8.5, 9.3, 13.3
 */

$__recentNotifications ??= [];

/**
 * Map notification type to a lucide icon name.
 */
if (!function_exists('notifDropdownIcon')) {
    function notifDropdownIcon(string $type): string
    {
        return match (true) {
            str_starts_with($type, 'report.submitted') => 'file-plus',
            str_starts_with($type, 'report.status') => 'file-check',
            str_starts_with($type, 'comment.') => 'message-square',
            str_starts_with($type, 'program.') => 'shield',
            default => 'bell',
        };
    }
}

/**
 * Build a link URL based on the notification's reference entity and ID.
 */
if (!function_exists('notifDropdownLink')) {
    function notifDropdownLink(?string $entity, ?int $id): string
    {
        if (!$entity || !$id) {
            return 'index.php?page=notifications';
        }
        return match ($entity) {
            'report' => 'index.php?page=report-detail&id=' . (int) $id,
            'program' => 'index.php?page=program-detail&id=' . (int) $id,
            default => 'index.php?page=notifications',
        };
    }
}

/**
 * Format a timestamp as a relative "time ago" string.
 */
if (!function_exists('notifTimeAgo')) {
    function notifTimeAgo(string $datetime): string
    {
        $now = new DateTime();
        $then = new DateTime($datetime);
        $diff = $now->diff($then);

        if ($diff->y > 0) {
            return $diff->y . 'y ago';
        }
        if ($diff->m > 0) {
            return $diff->m . 'mo ago';
        }
        if ($diff->d > 0) {
            return $diff->d . 'd ago';
        }
        if ($diff->h > 0) {
            return $diff->h . 'h ago';
        }
        if ($diff->i > 0) {
            return $diff->i . 'm ago';
        }
        return 'just now';
    }
}
?>

<div class="notifications-dropdown" id="notificationsDropdown">
    <div class="notifications-dropdown-header">
        <span class="notifications-dropdown-title">Notifications</span>
        <?php if ($unreadCount > 0): ?>
            <a href="index.php?page=notifications-mark-read" class="notifications-mark-all" title="Mark all as read">
                <i data-lucide="check-check"></i> Mark all read
            </a>
        <?php endif; ?>
    </div>

    <div class="notifications-dropdown-body">
        <?php if (empty($__recentNotifications)): ?>
            <div class="notifications-dropdown-empty">
                <i data-lucide="bell-off"></i>
                <p>No notifications yet</p>
            </div>
        <?php else: ?>
            <?php foreach ($__recentNotifications as $notif):
                $targetUrl = notifDropdownLink($notif['reference_entity'] ?? null, isset($notif['reference_id']) ? (int) $notif['reference_id'] : null);
                $clickUrl = empty($notif['is_read'])
                    ? 'index.php?page=notification-click&id=' . (int) $notif['id'] . '&redirect=' . urlencode($targetUrl)
                    : $targetUrl;
                ?>
                <a href="<?= htmlspecialchars($clickUrl, ENT_QUOTES, 'UTF-8') ?>"
                    class="notifications-dropdown-item <?= empty($notif['is_read']) ? 'unread' : '' ?>">
                    <span class="notif-icon">
                        <i
                            data-lucide="<?= htmlspecialchars(notifDropdownIcon($notif['type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></i>
                    </span>
                    <span class="notif-content">
                        <span class="notif-message"><?= htmlspecialchars($notif['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                        <span
                            class="notif-time"><?= htmlspecialchars(notifTimeAgo($notif['created_at'] ?? 'now'), ENT_QUOTES, 'UTF-8') ?></span>
                    </span>
                    <?php if (empty($notif['is_read'])): ?>
                        <span class="notif-unread-dot" title="Unread"></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="notifications-dropdown-footer">
        <a href="index.php?page=notifications" class="notifications-view-all">
            View all notifications
        </a>
    </div>
</div>