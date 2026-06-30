<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * NotificationRepository
 *
 * Manages CRUD operations for the notifications table.
 * Provides in-app notification storage and retrieval for users.
 *
 * @see Requirement 7.1 — Notify Program_Owner when a Report is submitted
 * @see Requirement 8.5 — Notify Researcher when Report status changes
 * @see Requirement 9.3 — Notify other participant when a Comment is added
 */
class NotificationRepository extends BaseRepository
{
    /**
     * Insert a new notification record.
     *
     * @param int         $userId          Recipient user ID.
     * @param string      $type            Notification type (e.g., 'report.submitted', 'report.status_change', 'comment.new').
     * @param string|null $referenceEntity Related entity type (e.g., 'report', 'comment') or null.
     * @param int|null    $referenceId     Related entity ID or null.
     * @param string      $message         Display message for the notification.
     * @return int The ID of the newly created notification.
     * @throws RuntimeException on insertion failure.
     */
    public function create(
        int $userId,
        string $type,
        ?string $referenceEntity,
        ?int $referenceId,
        string $message
    ): int {
        $sql = 'INSERT INTO notifications (user_id, type, reference_entity, reference_id, message, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())';

        $types = 'issis';
        $params = [$userId, $type, $referenceEntity, $referenceId, $message];

        $this->execute($sql, $types, $params);

        return $this->lastInsertId();
    }

    /**
     * Retrieve notifications for a specific user, ordered by most recent first.
     *
     * @param int $userId User ID to fetch notifications for.
     * @param int $limit  Maximum number of notifications to return.
     * @param int $offset Offset for pagination.
     * @return array Array of associative arrays representing notification rows.
     */
    public function getByUserId(int $userId, int $limit = 20, int $offset = 0): array
    {
        $sql = 'SELECT id, user_id, type, reference_entity, reference_id, message, is_read, created_at
                FROM notifications
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?';

        return $this->fetchAll($sql, 'iii', [$userId, $limit, $offset]);
    }

    /**
     * Mark a notification as read.
     *
     * @param int $notificationId The notification ID to mark as read.
     * @return int Number of affected rows (0 if notification not found, 1 if updated).
     * @throws RuntimeException on execution failure.
     */
    public function markAsRead(int $notificationId): int
    {
        $sql = 'UPDATE notifications SET is_read = 1 WHERE id = ?';

        return $this->execute($sql, 'i', [$notificationId]);
    }

    /**
     * Get the count of unread notifications for a user.
     *
     * @param int $userId User ID to count unread notifications for.
     * @return int Number of unread notifications.
     */
    public function getUnreadCount(int $userId): int
    {
        $sql = 'SELECT COUNT(*) AS unread_count
                FROM notifications
                WHERE user_id = ? AND is_read = 0';

        $row = $this->fetchOne($sql, 'i', [$userId]);

        return (int) ($row['unread_count'] ?? 0);
    }

    /**
     * Mark all unread notifications as read for a user.
     *
     * @param int $userId User ID whose notifications should be marked as read.
     * @return int Number of affected rows.
     * @throws RuntimeException on execution failure.
     */
    public function markAllAsRead(int $userId): int
    {
        $sql = 'UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0';

        return $this->execute($sql, 'i', [$userId]);
    }
}
