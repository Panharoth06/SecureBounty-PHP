<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * AssetRepository
 *
 * Handles all database operations for the `program_assets` table.
 * Extends BaseRepository to leverage parameterized query helpers.
 *
 * @see Requirement 1.1 — Store Asset with name, type, and program reference
 * @see Requirement 1.3 — Delete Asset record from database
 * @see Requirement 1.4 — Update Asset name and type
 * @see Requirement 1.8 — Reject duplicate Asset name within same Program
 * @see Requirement 1.9 — Display total count of Assets grouped by type
 */
class AssetRepository extends BaseRepository
{
    /**
     * Insert a new asset record and return the new ID.
     *
     * @param int    $programId Program ID (FK → programs.id).
     * @param string $name      Asset name (max 255 characters).
     * @param string $type      Asset type (Domain, Wildcard, iOS App Store, Android Play Store, Windows App, Other).
     * @return int The ID of the newly created asset.
     * @throws RuntimeException on insertion failure.
     */
    public function create(int $programId, string $name, string $type): int
    {
        $sql = 'INSERT INTO program_assets (program_id, name, type) VALUES (?, ?, ?)';

        $this->execute($sql, 'iss', [$programId, $name, $type]);

        return $this->lastInsertId();
    }

    /**
     * Find a single asset by its ID.
     *
     * @param int $id Asset ID.
     * @return array|null Associative array of the asset row, or null if not found.
     */
    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM program_assets WHERE id = ?',
            'i',
            [$id]
        );
    }

    /**
     * Find all assets for a given program.
     *
     * @param int $programId Program ID.
     * @return array Array of associative arrays (asset rows).
     */
    public function findByProgramId(int $programId): array
    {
        return $this->fetchAll(
            'SELECT * FROM program_assets WHERE program_id = ? ORDER BY type ASC, name ASC',
            'i',
            [$programId]
        );
    }

    /**
     * Update an existing asset's name and type.
     *
     * @param int    $id   Asset ID.
     * @param string $name New asset name.
     * @param string $type New asset type.
     * @return int Number of affected rows.
     * @throws RuntimeException on execution failure.
     */
    public function update(int $id, string $name, string $type): int
    {
        $sql = 'UPDATE program_assets SET name = ?, type = ? WHERE id = ?';

        return $this->execute($sql, 'ssi', [$name, $type, $id]);
    }

    /**
     * Delete an asset by its ID.
     *
     * @param int $id Asset ID.
     * @return int Number of affected rows.
     * @throws RuntimeException on execution failure.
     */
    public function delete(int $id): int
    {
        return $this->execute(
            'DELETE FROM program_assets WHERE id = ?',
            'i',
            [$id]
        );
    }

    /**
     * Count assets grouped by type for a given program.
     *
     * Returns an associative array where keys are asset types and values are counts.
     * Types with zero assets are not included in the result.
     *
     * @param int $programId Program ID.
     * @return array Associative array of type => count (e.g., ['Domain' => 3, 'Wildcard' => 1]).
     */
    public function countByTypeForProgram(int $programId): array
    {
        $rows = $this->fetchAll(
            'SELECT type, COUNT(*) AS count FROM program_assets WHERE program_id = ? GROUP BY type',
            'i',
            [$programId]
        );

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['type']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Check whether an asset with the given name already exists in the specified program.
     *
     * Optionally excludes a specific asset ID (useful for update validation).
     *
     * @param string   $name      Asset name to check.
     * @param int      $programId Program ID to scope the check.
     * @param int|null $excludeId Asset ID to exclude from the check (for updates).
     * @return bool True if a matching asset exists, false otherwise.
     */
    public function existsByNameAndProgram(string $name, int $programId, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $row = $this->fetchOne(
                'SELECT COUNT(*) AS cnt FROM program_assets WHERE name = ? AND program_id = ? AND id != ?',
                'sii',
                [$name, $programId, $excludeId]
            );
        } else {
            $row = $this->fetchOne(
                'SELECT COUNT(*) AS cnt FROM program_assets WHERE name = ? AND program_id = ?',
                'si',
                [$name, $programId]
            );
        }

        return ($row['cnt'] ?? 0) > 0;
    }
}
