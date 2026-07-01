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

    /**
     * Find active programs matching the given filter criteria with pagination.
     *
     * Filters are applied with AND logic across categories and OR logic within each category:
     * - asset_type: programs with at least one asset of any selected type
     * - tag: programs associated with at least one of the selected tag IDs
     * - bounty_min: programs with at least one reward_policy where max_reward >= bounty_min
     * - bounty_max: programs with at least one reward_policy where max_reward <= bounty_max
     *
     * @param array $filters Associative array of filter criteria.
     * @param int   $limit   Maximum number of records to return.
     * @param int   $offset  Number of records to skip.
     * @return array Array of associative arrays (program rows).
     *
     * @see Requirement 5.1 — Filter by Asset_Type (OR within)
     * @see Requirement 5.2 — Filter by Technology_Tag (OR within)
     * @see Requirement 5.3 — Filter by Bounty_Range
     * @see Requirement 5.5 — AND logic across filter categories
     */
    public function findActiveWithFilters(array $filters, int $limit, int $offset): array
    {
        $where = "p.status = 'active'";
        $types = '';
        $params = [];

        $this->buildFilterClauses($filters, $where, $types, $params);

        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;

        $sql = "SELECT p.* FROM programs p WHERE {$where} ORDER BY p.created_at DESC LIMIT ? OFFSET ?";

        return $this->fetchAll($sql, $types, $params);
    }

    /**
     * Count active programs matching the given filter criteria.
     *
     * Uses the same filter logic as findActiveWithFilters() but returns only the count.
     *
     * @param array $filters Associative array of filter criteria.
     * @return int Total number of matching programs.
     *
     * @see Requirement 5.1 — Filter by Asset_Type (OR within)
     * @see Requirement 5.2 — Filter by Technology_Tag (OR within)
     * @see Requirement 5.3 — Filter by Bounty_Range
     * @see Requirement 5.5 — AND logic across filter categories
     */
    public function countActiveWithFilters(array $filters): int
    {
        $where = "p.status = 'active'";
        $types = '';
        $params = [];

        $this->buildFilterClauses($filters, $where, $types, $params);

        $sql = "SELECT COUNT(*) AS total FROM programs p WHERE {$where}";

        $row = $this->fetchOne($sql, $types, $params);

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Update a program's logo_path column.
     *
     * Pass null to remove the logo association.
     *
     * @param int         $id       Program ID.
     * @param string|null $logoPath File path to the logo, or null to clear.
     * @return int Number of affected rows (0 or 1).
     *
     * @see Requirement 7.1 — Store logo image associated with Program
     */
    public function updateLogoPath(int $id, ?string $logoPath): int
    {
        return $this->execute(
            'UPDATE programs SET logo_path = ?, updated_at = NOW() WHERE id = ?',
            'si',
            [$logoPath, $id]
        );
    }

    /**
     * Build dynamic WHERE clauses for filter queries.
     *
     * Appends EXISTS subqueries to the $where string and populates $types/$params
     * for parameterized binding.
     *
     * @param array  $filters Associative array of filter criteria.
     * @param string &$where  WHERE clause string (modified by reference).
     * @param string &$types  Type string for bind_param (modified by reference).
     * @param array  &$params Parameter values (modified by reference).
     */
    private function buildFilterClauses(array $filters, string &$where, string &$types, array &$params): void
    {
        // Filter by asset type (OR within — program has at least one asset of any selected type)
        if (!empty($filters['asset_type']) && is_array($filters['asset_type'])) {
            $assetTypes = $filters['asset_type'];
            $placeholders = implode(', ', array_fill(0, count($assetTypes), '?'));
            $where .= " AND EXISTS (SELECT 1 FROM program_assets pa WHERE pa.program_id = p.id AND pa.type IN ({$placeholders}))";
            $types .= str_repeat('s', count($assetTypes));
            foreach ($assetTypes as $assetType) {
                $params[] = $assetType;
            }
        }

        // Filter by tag IDs (OR within — program associated with at least one selected tag)
        if (!empty($filters['tag']) && is_array($filters['tag'])) {
            $tagIds = array_map('intval', $filters['tag']);
            $placeholders = implode(', ', array_fill(0, count($tagIds), '?'));
            $where .= " AND EXISTS (SELECT 1 FROM program_tags pt WHERE pt.program_id = p.id AND pt.tag_id IN ({$placeholders}))";
            $types .= str_repeat('i', count($tagIds));
            foreach ($tagIds as $tagId) {
                $params[] = $tagId;
            }
        }

        // Filter by minimum bounty (program has at least one reward_policy with max_reward >= bounty_min)
        if (isset($filters['bounty_min']) && is_numeric($filters['bounty_min'])) {
            $where .= " AND EXISTS (SELECT 1 FROM reward_policies rp WHERE rp.program_id = p.id AND rp.max_reward >= ?)";
            $types .= 'd';
            $params[] = (float) $filters['bounty_min'];
        }

        // Filter by maximum bounty (program has at least one reward_policy with max_reward <= bounty_max)
        if (isset($filters['bounty_max']) && is_numeric($filters['bounty_max'])) {
            $where .= " AND EXISTS (SELECT 1 FROM reward_policies rp WHERE rp.program_id = p.id AND rp.max_reward <= ?)";
            $types .= 'd';
            $params[] = (float) $filters['bounty_max'];
        }
    }
}
