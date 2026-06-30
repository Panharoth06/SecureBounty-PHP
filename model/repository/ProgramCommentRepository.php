<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * ProgramCommentRepository
 *
 * Handles all database operations for the `program_comments` table.
 * Supports threaded discussions on program pages between researchers and program owners.
 */
class ProgramCommentRepository extends BaseRepository
{
    /**
     * Insert a new program comment and return the new ID.
     *
     * @param int      $programId Program this comment belongs to.
     * @param int      $userId    Comment author user ID.
     * @param string   $body      Comment content.
     * @param int|null $parentId  Parent comment ID for threaded replies (null = top-level).
     * @return int The ID of the newly created comment.
     * @throws RuntimeException on insertion failure.
     */
    public function create(int $programId, int $userId, string $body, ?int $parentId = null): int
    {
        if ($parentId !== null) {
            $sql = 'INSERT INTO program_comments (program_id, user_id, parent_id, body, created_at)
                    VALUES (?, ?, ?, ?, NOW())';
            $this->execute($sql, 'iiis', [$programId, $userId, $parentId, $body]);
        } else {
            $sql = 'INSERT INTO program_comments (program_id, user_id, parent_id, body, created_at)
                    VALUES (?, ?, NULL, ?, NOW())';
            $this->execute($sql, 'iis', [$programId, $userId, $body]);
        }

        return $this->lastInsertId();
    }

    /**
     * Find all comments for a program, ordered by created_at ASC.
     * Includes user first/last name and role via JOIN.
     *
     * @param int $programId Program ID.
     * @return array Array of associative arrays (comment rows with user names and role).
     */
    public function findByProgramId(int $programId): array
    {
        return $this->fetchAll(
            'SELECT pc.*, u.first_name AS author_first_name, u.last_name AS author_last_name,
                    r.name AS author_role
             FROM program_comments pc
             INNER JOIN users u ON pc.user_id = u.id
             INNER JOIN roles r ON u.role_id = r.id
             WHERE pc.program_id = ?
             ORDER BY pc.created_at ASC',
            'i',
            [$programId]
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
            'SELECT pc.*, u.first_name AS author_first_name, u.last_name AS author_last_name
             FROM program_comments pc
             INNER JOIN users u ON pc.user_id = u.id
             WHERE pc.id = ?',
            'i',
            [$id]
        );
    }
}
