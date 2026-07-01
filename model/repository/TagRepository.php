<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * TagRepository
 *
 * Handles all database operations for the `technology_tags` and `program_tags` tables.
 * Extends BaseRepository to leverage parameterized query helpers.
 *
 * @see Requirement 2.1 — Associate Technology_Tags with Programs
 * @see Requirement 2.3 — Remove tag association while retaining shared pool
 * @see Requirement 2.4 — Maintain shared pool of Technology_Tags
 * @see Requirement 2.5 — Case-insensitive matching for tag creation
 */
class TagRepository extends BaseRepository
{
    /**
     * Find an existing tag by normalized name or create a new one.
     * Uses LOWER() for case-insensitive matching.
     *
     * @param string $name The tag name (original casing preserved on creation).
     * @return int The tag ID (existing or newly created).
     * @throws RuntimeException on insertion failure.
     */
    public function findOrCreate(string $name): int
    {
        $normalizedName = strtolower($name);

        $existing = $this->fetchOne(
            'SELECT id FROM technology_tags WHERE normalized_name = LOWER(?)',
            's',
            [$normalizedName]
        );

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $this->execute(
            'INSERT INTO technology_tags (name, normalized_name) VALUES (?, ?)',
            'ss',
            [$name, $normalizedName]
        );

        return $this->lastInsertId();
    }

    /**
     * Find a tag by its name (case-insensitive).
     *
     * @param string $name The tag name to search for.
     * @return array|null Associative array of the tag row, or null if not found.
     */
    public function findByName(string $name): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM technology_tags WHERE normalized_name = LOWER(?)',
            's',
            [strtolower($name)]
        );
    }

    /**
     * Search tags by prefix (case-insensitive) for autocomplete.
     *
     * @param string $prefix The prefix to search for.
     * @param int    $limit  Maximum number of results to return.
     * @return array Array of associative arrays (tag rows).
     */
    public function searchByPrefix(string $prefix, int $limit = 10): array
    {
        $normalizedPrefix = strtolower($prefix) . '%';

        return $this->fetchAll(
            'SELECT * FROM technology_tags WHERE normalized_name LIKE ? ORDER BY normalized_name ASC LIMIT ?',
            'si',
            [$normalizedPrefix, $limit]
        );
    }

    /**
     * Associate a tag with a program via the program_tags junction table.
     *
     * @param int $tagId     The tag ID.
     * @param int $programId The program ID.
     * @return bool True if association was created, false if it already exists (duplicate).
     */
    public function associateWithProgram(int $tagId, int $programId): bool
    {
        $sql = 'INSERT INTO program_tags (program_id, tag_id) VALUES (?, ?)';

        $stmt = $this->conn->prepare($sql);

        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare statement: ' . $this->conn->error);
        }

        $stmt->bind_param('ii', $programId, $tagId);

        try {
            $stmt->execute();
            return $stmt->affected_rows > 0;
        } catch (\mysqli_sql_exception $e) {
            // Error code 1062 = duplicate entry (unique constraint violation)
            if ($e->getCode() === 1062) {
                return false;
            }
            throw $e;
        } finally {
            $stmt->close();
        }
    }

    /**
     * Remove the association between a tag and a program.
     * The tag itself remains in the shared pool.
     *
     * @param int $tagId     The tag ID.
     * @param int $programId The program ID.
     * @return int Number of affected rows (0 or 1).
     */
    public function dissociateFromProgram(int $tagId, int $programId): int
    {
        return $this->execute(
            'DELETE FROM program_tags WHERE tag_id = ? AND program_id = ?',
            'ii',
            [$tagId, $programId]
        );
    }

    /**
     * Find all tags associated with a program.
     *
     * @param int $programId The program ID.
     * @return array Array of associative arrays (tag rows with association data).
     */
    public function findByProgramId(int $programId): array
    {
        return $this->fetchAll(
            'SELECT t.* FROM technology_tags t
             INNER JOIN program_tags pt ON pt.tag_id = t.id
             WHERE pt.program_id = ?
             ORDER BY t.normalized_name ASC',
            'i',
            [$programId]
        );
    }

    /**
     * Get every tag in the shared pool ordered alphabetically by display name.
     *
     * Used by the program-listing filter panel to populate the technology tag
     * multi-select.
     *
     * @return array Array of tag rows.
     *
     * @see Requirement 5.2 — Filter programs by Technology_Tag
     */
    public function findAll(): array
    {
        return $this->fetchAll(
            'SELECT * FROM technology_tags ORDER BY name ASC'
        );
    }

    /**
     * Count the number of tags associated with a program.
     *
     * @param int $programId The program ID.
     * @return int The tag count.
     */
    public function countByProgramId(int $programId): int
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS cnt FROM program_tags WHERE program_id = ?',
            'i',
            [$programId]
        );

        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Check if a specific tag is already associated with a program.
     *
     * @param int $tagId     The tag ID.
     * @param int $programId The program ID.
     * @return bool True if the association exists.
     */
    public function isAssociatedWithProgram(int $tagId, int $programId): bool
    {
        $row = $this->fetchOne(
            'SELECT 1 FROM program_tags WHERE tag_id = ? AND program_id = ? LIMIT 1',
            'ii',
            [$tagId, $programId]
        );

        return $row !== null;
    }
}
