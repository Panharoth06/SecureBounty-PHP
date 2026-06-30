<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * SavedProgramRepository
 *
 * Handles all database operations for the `saved_programs` junction table.
 * Manages researcher bookmarks for bug bounty programs.
 *
 * @see Requirement 6.3 — Create Saved_Program record (bookmark)
 * @see Requirement 6.4 — Delete Saved_Program record (remove bookmark)
 */
class SavedProgramRepository extends BaseRepository
{
    /**
     * Save (bookmark) a program for a researcher.
     *
     * Relies on the UNIQUE KEY `uq_saved_program` (user_id, program_id) to prevent
     * duplicate bookmarks at the database level. Handles duplicate gracefully
     * by returning false instead of throwing.
     *
     * @param int $userId    Researcher user ID.
     * @param int $programId Program ID to bookmark.
     * @return bool True if bookmark was created, false if already saved.
     */
    public function save(int $userId, int $programId): bool
    {
        $sql = 'INSERT INTO saved_programs (user_id, program_id, saved_at)
                VALUES (?, ?, NOW())';

        $stmt = $this->conn->prepare($sql);

        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare statement: ' . $this->conn->error);
        }

        $stmt->bind_param('ii', $userId, $programId);

        try {
            $result = $stmt->execute();

            if (!$result) {
                // MySQL error 1062 = Duplicate entry for UNIQUE constraint
                if ($this->conn->errno === 1062) {
                    return false;
                }
                throw new RuntimeException('Statement execution failed: ' . $stmt->error);
            }

            return $stmt->affected_rows > 0;
        } finally {
            $stmt->close();
        }
    }

    /**
     * Remove a saved (bookmarked) program for a researcher.
     *
     * @param int $userId    Researcher user ID.
     * @param int $programId Program ID to un-bookmark.
     * @return bool True if a record was deleted, false if no record existed.
     */
    public function unsave(int $userId, int $programId): bool
    {
        $affectedRows = $this->execute(
            'DELETE FROM saved_programs WHERE user_id = ? AND program_id = ?',
            'ii',
            [$userId, $programId]
        );

        return $affectedRows > 0;
    }

    /**
     * Get all programs saved by a researcher, with program details.
     *
     * @param int $userId Researcher user ID.
     * @return array Array of associative arrays with bookmark + program fields.
     */
    public function getSavedByUserId(int $userId): array
    {
        return $this->fetchAll(
            'SELECT sp.id, sp.user_id, sp.program_id, sp.saved_at,
                    p.title, p.description, p.scope, p.status AS program_status, p.owner_id
             FROM saved_programs sp
             INNER JOIN programs p ON sp.program_id = p.id
             WHERE sp.user_id = ?
             ORDER BY sp.saved_at DESC',
            'i',
            [$userId]
        );
    }

    /**
     * Check whether a researcher has saved (bookmarked) a specific program.
     *
     * @param int $userId    Researcher user ID.
     * @param int $programId Program ID to check.
     * @return bool True if saved, false otherwise.
     */
    public function isSaved(int $userId, int $programId): bool
    {
        $row = $this->fetchOne(
            'SELECT id FROM saved_programs WHERE user_id = ? AND program_id = ?',
            'ii',
            [$userId, $programId]
        );

        return $row !== null;
    }
}
