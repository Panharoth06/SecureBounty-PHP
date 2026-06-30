<?php

require_once __DIR__ . '/../repository/ActivityLogRepository.php';

/**
 * ActivityLogService
 *
 * Provides a clean interface for logging user actions. Wraps
 * ActivityLogRepository and handles JSON encoding of details.
 *
 * @see Requirement 12.1 — Log every state-changing action
 * @see Requirement 12.2 — Record user_id, action, target_entity, timestamp, IP
 * @see Requirement 12.5 — Immutable append-only log
 */
class ActivityLogService
{
    private ActivityLogRepository $repository;

    /**
     * @param ActivityLogRepository $repository
     */
    public function __construct(ActivityLogRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Record an activity log entry.
     *
     * @param int              $userId       Acting user's ID.
     * @param string           $action       Action type (e.g., 'user.register').
     * @param string           $targetEntity Target entity type (e.g., 'user', 'program').
     * @param int|null         $targetId     Target record ID (null for system events).
     * @param array|string|null $details     Additional context — arrays are JSON-encoded automatically.
     * @param string           $ipAddress    Client IP address.
     * @return int The ID of the newly created log entry.
     */
    public function log(
        int $userId,
        string $action,
        string $targetEntity,
        ?int $targetId = null,
        array|string|null $details = null,
        string $ipAddress = ''
    ): int {
        // JSON-encode details if provided as an array
        $encodedDetails = null;
        if (is_array($details)) {
            $encodedDetails = json_encode($details, JSON_UNESCAPED_UNICODE);
        } elseif (is_string($details)) {
            $encodedDetails = $details;
        }

        return $this->repository->create(
            $userId,
            $action,
            $targetEntity,
            $targetId,
            $encodedDetails,
            $ipAddress
        );
    }
}
