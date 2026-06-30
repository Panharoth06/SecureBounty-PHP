<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * ProgramRepository
 *
 * Handles all database operations for the `programs` table.
 * Extends BaseRepository to leverage parameterized query helpers.
 *
 * @see Requirement 4.1 — Create program with status 'draft'
 * @see Requirement 4.2 — Publish program (status → 'active')
 * @see Requirement 4.3 — Update active program
 * @see Requirement 4.4 — Close program (status → 'closed')
 * @see Requirement 4.5 — Validate required fields before publishing
 * @see Requirement 4.6 — Validate non-empty fields on create/update
 */
class ProgramRepository extends BaseRepository
{
    /**
     * Insert a new program with status 'draft' and return the new ID.
     *
     * @param int    $ownerId     Owner user ID (FK → users.id).
     * @param string $title       Program title.
     * @param string $description Program description.
     * @param string $scope       Scope definition (in-scope assets).
     * @return int The ID of the newly created program.
     * @throws RuntimeException on insertion failure.
     */
    public function create(int $ownerId, string $title, string $description, string $scope): int
    {
        $sql = 'INSERT INTO programs (owner_id, title, description, scope, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, \'draft\', NOW(), NOW())';

        $this->execute($sql, 'isss', [$ownerId, $title, $description, $scope]);

        return $this->lastInsertId();
    }

    /**
     * Find a program by its ID.
     *
     * @param int $id Program ID.
     * @return array|null Associative array of the program row, or null if not found.
     */
    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM programs WHERE id = ?',
            'i',
            [$id]
        );
    }

    /**
     * Find all programs owned by a specific user.
     *
     * @param int $ownerId Owner user ID.
     * @return array Array of associative arrays (program rows).
     */
    public function findByOwnerId(int $ownerId): array
    {
        return $this->fetchAll(
            'SELECT * FROM programs WHERE owner_id = ? ORDER BY created_at DESC',
            'i',
            [$ownerId]
        );
    }

    /**
     * Find all programs with status 'active' (for researcher listing).
     *
     * @return array Array of associative arrays (active program rows).
     */
    public function findActive(): array
    {
        return $this->fetchAll(
            'SELECT * FROM programs WHERE status = \'active\' ORDER BY created_at DESC'
        );
    }

    /**
     * Update a program's title, description, and scope.
     *
     * @param int    $id          Program ID to update.
     * @param string $title       New title.
     * @param string $description New description.
     * @param string $scope       New scope.
     * @return int Number of affected rows (0 or 1).
     * @throws RuntimeException on execution failure.
     */
    public function update(int $id, string $title, string $description, string $scope): int
    {
        return $this->execute(
            'UPDATE programs SET title = ?, description = ?, scope = ?, updated_at = NOW() WHERE id = ?',
            'sssi',
            [$title, $description, $scope, $id]
        );
    }

    /**
     * Update a program's status field.
     *
     * @param int    $id     Program ID to update.
     * @param string $status New status ('draft', 'active', 'closed', 'suspended').
     * @return int Number of affected rows (0 or 1).
     * @throws RuntimeException on execution failure.
     */
    public function updateStatus(int $id, string $status): int
    {
        return $this->execute(
            'UPDATE programs SET status = ?, updated_at = NOW() WHERE id = ?',
            'si',
            [$status, $id]
        );
    }

    /**
     * Get all programs with optional status filter and pagination.
     *
     * @param string|null $status Optional status to filter by.
     * @param int         $limit  Maximum number of records to return.
     * @param int         $offset Number of records to skip.
     * @return array Array of associative arrays (program rows).
     */
    public function getAll(?string $status = null, int $limit = 20, int $offset = 0): array
    {
        if ($status !== null) {
            return $this->fetchAll(
                'SELECT * FROM programs WHERE status = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
                'sii',
                [$status, $limit, $offset]
            );
        }

        return $this->fetchAll(
            'SELECT * FROM programs ORDER BY created_at DESC LIMIT ? OFFSET ?',
            'ii',
            [$limit, $offset]
        );
    }
}
