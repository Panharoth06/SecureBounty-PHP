<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * ActivityLogRepository
 *
 * Insert-only repository for the activity_logs table.
 * No update() or delete() methods are provided to enforce immutability
 * at the application layer.
 *
 * @see Requirement 12.1 — Log every state-changing action
 * @see Requirement 12.2 — Record user_id, action, target_entity, timestamp, IP
 * @see Requirement 12.3 — Display logs in reverse chronological order with pagination
 * @see Requirement 12.4 — Filter by user, action type, or date range
 * @see Requirement 12.5 — Retain logs indefinitely, prevent deletion
 */
class ActivityLogRepository extends BaseRepository
{
    /**
     * Insert a new activity log entry.
     *
     * @param int         $userId       Acting user's ID.
     * @param string      $action       Action type (e.g., 'user.register', 'report.status_change').
     * @param string      $targetEntity Target entity type (e.g., 'program', 'report', 'user').
     * @param int|null    $targetId     Target record ID (nullable for system events).
     * @param string|null $details      JSON-encoded additional context (nullable).
     * @param string      $ipAddress    Client IP address (supports IPv6, max 45 chars).
     * @return int The ID of the newly created log entry.
     * @throws RuntimeException on insertion failure.
     */
    public function create(
        int $userId,
        string $action,
        string $targetEntity,
        ?int $targetId,
        ?string $details,
        string $ipAddress
    ): int {
        $sql = 'INSERT INTO activity_logs (user_id, action, target_entity, target_id, details, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())';

        $types = 'ississ';
        $params = [$userId, $action, $targetEntity, $targetId, $details, $ipAddress];

        $this->execute($sql, $types, $params);

        return $this->lastInsertId();
    }

    /**
     * Get all activity log entries with pagination, in reverse chronological order.
     * Includes user first/last name via JOIN with users table.
     *
     * @param int $limit  Maximum number of records to return.
     * @param int $offset Number of records to skip.
     * @return array Array of associative arrays (log entries with user name).
     */
    public function getAll(int $limit = 20, int $offset = 0): array
    {
        $sql = 'SELECT al.*, u.first_name, u.last_name
                FROM activity_logs al
                JOIN users u ON al.user_id = u.id
                ORDER BY al.created_at DESC
                LIMIT ? OFFSET ?';

        return $this->fetchAll($sql, 'ii', [$limit, $offset]);
    }

    /**
     * Filter activity log entries by user ID, with pagination.
     *
     * @param int $userId User ID to filter by.
     * @param int $limit  Maximum number of records to return.
     * @param int $offset Number of records to skip.
     * @return array Array of matching log entries with user name.
     */
    public function filterByUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        $sql = 'SELECT al.*, u.first_name, u.last_name
                FROM activity_logs al
                JOIN users u ON al.user_id = u.id
                WHERE al.user_id = ?
                ORDER BY al.created_at DESC
                LIMIT ? OFFSET ?';

        return $this->fetchAll($sql, 'iii', [$userId, $limit, $offset]);
    }

    /**
     * Filter activity log entries by action type, with pagination.
     *
     * @param string $action Action type to filter by (e.g., 'user.register').
     * @param int    $limit  Maximum number of records to return.
     * @param int    $offset Number of records to skip.
     * @return array Array of matching log entries with user name.
     */
    public function filterByAction(string $action, int $limit = 20, int $offset = 0): array
    {
        $sql = 'SELECT al.*, u.first_name, u.last_name
                FROM activity_logs al
                JOIN users u ON al.user_id = u.id
                WHERE al.action = ?
                ORDER BY al.created_at DESC
                LIMIT ? OFFSET ?';

        return $this->fetchAll($sql, 'sii', [$action, $limit, $offset]);
    }

    /**
     * Filter activity log entries by date range, with pagination.
     *
     * @param string $startDate Start date (inclusive), format 'YYYY-MM-DD' or 'YYYY-MM-DD HH:MM:SS'.
     * @param string $endDate   End date (inclusive), format 'YYYY-MM-DD' or 'YYYY-MM-DD HH:MM:SS'.
     * @param int    $limit     Maximum number of records to return.
     * @param int    $offset    Number of records to skip.
     * @return array Array of matching log entries with user name.
     */
    public function filterByDateRange(string $startDate, string $endDate, int $limit = 20, int $offset = 0): array
    {
        $sql = 'SELECT al.*, u.first_name, u.last_name
                FROM activity_logs al
                JOIN users u ON al.user_id = u.id
                WHERE al.created_at >= ? AND al.created_at <= ?
                ORDER BY al.created_at DESC
                LIMIT ? OFFSET ?';

        return $this->fetchAll($sql, 'ssii', [$startDate, $endDate, $limit, $offset]);
    }

    /**
     * Get activity log entries with combined filters, in reverse chronological order.
     *
     * Supported filter keys:
     * - 'user_id'    (int)    — Filter by acting user
     * - 'action'     (string) — Filter by action type
     * - 'start_date' (string) — Filter entries on or after this date
     * - 'end_date'   (string) — Filter entries on or before this date
     *
     * @param array $filters Associative array with optional filter keys.
     * @param int   $limit   Maximum number of records to return.
     * @param int   $offset  Number of records to skip.
     * @return array Array of matching log entries with user name.
     */
    public function getFiltered(array $filters, int $limit = 20, int $offset = 0): array
    {
        $sql = 'SELECT al.*, u.first_name, u.last_name
                FROM activity_logs al
                JOIN users u ON al.user_id = u.id';

        $conditions = [];
        $types = '';
        $params = [];

        if (!empty($filters['user_id'])) {
            $conditions[] = 'al.user_id = ?';
            $types .= 'i';
            $params[] = (int) $filters['user_id'];
        }

        if (!empty($filters['action'])) {
            $conditions[] = 'al.action = ?';
            $types .= 's';
            $params[] = $filters['action'];
        }

        if (!empty($filters['start_date'])) {
            $conditions[] = 'al.created_at >= ?';
            $types .= 's';
            $params[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $conditions[] = 'al.created_at <= ?';
            $types .= 's';
            $params[] = $filters['end_date'];
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY al.created_at DESC LIMIT ? OFFSET ?';
        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;

        return $this->fetchAll($sql, $types, $params);
    }

    /**
     * Get the total count of activity log entries matching the given filters.
     * Used for pagination UI calculations.
     *
     * @param array $filters Associative array with optional filter keys (same as getFiltered).
     * @return int Total number of matching records.
     */
    public function getTotalCount(array $filters = []): int
    {
        $sql = 'SELECT COUNT(*) AS total FROM activity_logs al';

        $conditions = [];
        $types = '';
        $params = [];

        if (!empty($filters['user_id'])) {
            $conditions[] = 'al.user_id = ?';
            $types .= 'i';
            $params[] = (int) $filters['user_id'];
        }

        if (!empty($filters['action'])) {
            $conditions[] = 'al.action = ?';
            $types .= 's';
            $params[] = $filters['action'];
        }

        if (!empty($filters['start_date'])) {
            $conditions[] = 'al.created_at >= ?';
            $types .= 's';
            $params[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $conditions[] = 'al.created_at <= ?';
            $types .= 's';
            $params[] = $filters['end_date'];
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $row = $this->fetchOne($sql, $types, $params);

        return (int) ($row['total'] ?? 0);
    }
}
