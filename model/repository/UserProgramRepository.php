<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * UserProgramRepository
 *
 * Handles all database operations for the `user_programs` junction table.
 * Manages researcher enrollment in bug bounty programs.
 *
 * @see Requirement 6.2 — Create User_Program record on enrollment
 * @see Requirement 6.5 — Prevent duplicate enrollment (UNIQUE constraint)
 */
class UserProgramRepository extends BaseRepository
{
    /**
     * Enroll a researcher in a program.
     *
     * Relies on the UNIQUE KEY `uq_user_program` (user_id, program_id) to prevent
     * duplicate enrollments at the database level. Handles duplicate gracefully
     * by returning false instead of throwing.
     *
     * @param int $userId    Researcher user ID.
     * @param int $programId Program ID to enroll in.
     * @return bool True if enrollment was created, false if already enrolled.
     */
    public function enroll(int $userId, int $programId): bool
    {
        $sql = 'INSERT INTO user_programs (user_id, program_id, enrolled_at)
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
     * Check whether a researcher is enrolled in a specific program.
     *
     * @param int $userId    Researcher user ID.
     * @param int $programId Program ID to check.
     * @return bool True if enrolled, false otherwise.
     */
    public function isEnrolled(int $userId, int $programId): bool
    {
        $row = $this->fetchOne(
            'SELECT id FROM user_programs WHERE user_id = ? AND program_id = ?',
            'ii',
            [$userId, $programId]
        );

        return $row !== null;
    }

    /**
     * Get all programs a researcher is enrolled in, with program details.
     *
     * @param int $userId Researcher user ID.
     * @return array Array of associative arrays with enrollment + program fields.
     */
    public function getByUserId(int $userId): array
    {
        return $this->fetchAll(
            'SELECT up.id, up.user_id, up.program_id, up.enrolled_at,
                    p.title, p.description, p.scope, p.status AS program_status, p.owner_id
             FROM user_programs up
             INNER JOIN programs p ON up.program_id = p.id
             WHERE up.user_id = ?
             ORDER BY up.enrolled_at DESC',
            'i',
            [$userId]
        );
    }

    /**
     * Get all researchers enrolled in a specific program.
     *
     * @param int $programId Program ID.
     * @return array Array of associative arrays with enrollment + user fields.
     */
    public function getByProgramId(int $programId): array
    {
        return $this->fetchAll(
            'SELECT up.id, up.user_id, up.program_id, up.enrolled_at,
                    u.first_name, u.last_name, u.email
             FROM user_programs up
             INNER JOIN users u ON up.user_id = u.id
             WHERE up.program_id = ?
             ORDER BY up.enrolled_at DESC',
            'i',
            [$programId]
        );
    }
}
