<?php

require_once __DIR__ . '/../repository/NotificationRepository.php';

/**
 * NotificationService
 *
 * Provides in-app notification dispatch and retrieval.
 * Wraps the NotificationRepository to offer a clean interface for
 * controllers and other services to send notifications.
 *
 * @see Requirement 7.1 — Notify Program_Owner when a Report is submitted
 * @see Requirement 8.5 — Notify Researcher when Report status changes
 * @see Requirement 9.3 — Notify other participant when a Comment is added
 */
class NotificationService
{
    private NotificationRepository $notificationRepository;

    /**
     * @param NotificationRepository $notificationRepository
     */
    public function __construct(NotificationRepository $notificationRepository)
    {
        $this->notificationRepository = $notificationRepository;
    }

    /**
     * Dispatch an in-app notification to a user.
     *
     * @param int         $userId          Recipient user ID.
     * @param string      $type            Notification type (e.g., 'report.submitted', 'report.status_change', 'comment.new').
     * @param string|null $referenceEntity Related entity type (e.g., 'report', 'comment') or null.
     * @param int|null    $referenceId     Related entity ID or null.
     * @param string      $message         Human-readable notification message.
     * @return int The ID of the created notification.
     */
    public function notify(
        int $userId,
        string $type,
        ?string $referenceEntity,
        ?int $referenceId,
        string $message
    ): int {
        return $this->notificationRepository->create(
            $userId,
            $type,
            $referenceEntity,
            $referenceId,
            $message
        );
    }

    /**
     * Get notifications for a user with pagination.
     *
     * @param int $userId User ID to retrieve notifications for.
     * @param int $limit  Maximum number of results.
     * @param int $offset Pagination offset.
     * @return array Array of notification records.
     */
    public function getNotificationsForUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        return $this->notificationRepository->getByUserId($userId, $limit, $offset);
    }

    /**
     * Mark a notification as read.
     *
     * @param int $notificationId The notification ID to mark as read.
     * @return int Number of affected rows.
     */
    public function markAsRead(int $notificationId): int
    {
        return $this->notificationRepository->markAsRead($notificationId);
    }

    /**
     * Get the count of unread notifications for a user.
     *
     * @param int $userId User ID.
     * @return int Unread notification count.
     */
    public function getUnreadCount(int $userId): int
    {
        return $this->notificationRepository->getUnreadCount($userId);
    }

    /**
     * Mark all unread notifications as read for a user.
     *
     * @param int $userId User ID.
     * @return int Number of notifications marked as read.
     */
    public function markAllAsRead(int $userId): int
    {
        return $this->notificationRepository->markAllAsRead($userId);
    }
}
