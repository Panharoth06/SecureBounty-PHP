<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * CommentRepository
 *
 * Handles all database operations for the `comments` table.
 * Extends BaseRepository to leverage parameterized query helpers.
 *
 * @see Requirement 9.1 — Store comment with author, timestamp, and report reference
 * @see Requirement 9.4 — Display comments in chronological order
 * @see Requirement 9.5 — Restrict comment access to report submitter and program owner
 */
class CommentRepository extends BaseRepository
{
    /**
     * Insert a new comment and return the new ID.
     *
     * @param int      $reportId Report this comment belongs to.
     * @param int      $userId   Comment author user ID.
     * @param string   $body     Comment content.
     * @param int|null $parentId Parent comment ID for threaded replies (null = top-level).
     * @return int The ID of the newly created comment.
     * @throws RuntimeException on insertion failure.
     */
    public function create(int $reportId, int $userId, string $body, ?int $parentId = null): int
    {
        $sql = 'INSERT INTO comments (report_id, user_id, parent_id, body, created_at)
                VALUES (?, ?, ?, ?, NOW())';

        $types = 'ii' . ($parentId !== null ? 'i' : 'i') . 's';

        // Handle nullable parent_id properly
        if ($parentId !== null) {
            $this->execute($sql, 'iiis', [$reportId, $userId, $parentId, $body]);
        } else {
            $sqlNull = 'INSERT INTO comments (report_id, user_id, parent_id, body, created_at)
                        VALUES (?, ?, NULL, ?, NOW())';
            $this->execute($sqlNull, 'iis', [$reportId, $userId, $body]);
        }

        return $this->lastInsertId();
    }

    /**
     * Find all comments for a report, ordered by created_at ASC.
     * Includes user first/last name via JOIN.
     *
     * @param int $reportId Report ID.
     * @return array Array of associative arrays (comment rows with user names).
     */
    public function findByReportId(int $reportId): array
    {
        return $this->fetchAll(
            'SELECT c.*, u.first_name AS author_first_name, u.last_name AS author_last_name
             FROM comments c
             INNER JOIN users u ON c.user_id = u.id
             WHERE c.report_id = ?
             ORDER BY c.created_at ASC',
            'i',
            [$reportId]
        );
    }

    /**
     * Find a single comment by its ID.
     *
     * @param int $id Comment ID.
     * @return array|null Associative array of the comment, or null if not found.
     */
    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT c.*, u.first_name AS author_first_name, u.last_name AS author_last_name
             FROM comments c
             INNER JOIN users u ON c.user_id = u.id
             WHERE c.id = ?',
            'i',
            [$id]
        );
    }
}
