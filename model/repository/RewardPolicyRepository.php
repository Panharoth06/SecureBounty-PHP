<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * RewardPolicyRepository
 *
 * Handles all database operations for the `reward_policies` table.
 * Extends BaseRepository to leverage parameterized query helpers.
 *
 * @see Requirement 5.1 — Create reward policy with severity and amount
 * @see Requirement 5.2 — Support severity levels: critical, high, medium, low, informational
 * @see Requirement 5.3 — Update reward policy amounts
 * @see Requirement 5.4 — Delete policy only if no accepted reports reference it
 * @see Requirement 5.5 — Reject deletion if accepted reports reference the policy
 */
class RewardPolicyRepository extends BaseRepository
{
    /**
     * Valid severity levels for reward policies.
     */
    public const SEVERITY_LEVELS = ['critical', 'high', 'medium', 'low', 'informational'];

    /**
     * Insert a new reward policy and return the auto-generated ID.
     *
     * @param int    $programId  Program FK (references programs.id).
     * @param string $severity   Severity level (critical, high, medium, low, informational).
     * @param float  $minReward  Minimum reward amount (USD).
     * @param float  $maxReward  Maximum reward amount (USD).
     * @return int The ID of the newly created reward policy.
     * @throws RuntimeException on insertion failure.
     */
    public function create(int $programId, string $severity, float $minReward, float $maxReward): int
    {
        $sql = 'INSERT INTO reward_policies (program_id, severity, min_reward, max_reward, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())';

        $this->execute($sql, 'isdd', [$programId, $severity, $minReward, $maxReward]);

        return $this->lastInsertId();
    }

    /**
     * Find all reward policies for a given program.
     *
     * @param int $programId Program ID to look up policies for.
     * @return array Array of associative arrays (policy rows).
     */
    public function findByProgramId(int $programId): array
    {
        return $this->fetchAll(
            'SELECT * FROM reward_policies WHERE program_id = ? ORDER BY FIELD(severity, \'critical\', \'high\', \'medium\', \'low\', \'informational\')',
            'i',
            [$programId]
        );
    }

    /**
     * Find a reward policy by its ID.
     *
     * @param int $id Reward policy ID.
     * @return array|null Associative array of the policy row, or null if not found.
     */
    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM reward_policies WHERE id = ?',
            'i',
            [$id]
        );
    }

    /**
     * Update a reward policy's min and max reward amounts.
     *
     * @param int   $id        Reward policy ID to update.
     * @param float $minReward New minimum reward amount.
     * @param float $maxReward New maximum reward amount.
     * @return int Number of affected rows (0 or 1).
     * @throws RuntimeException on execution failure.
     */
    public function update(int $id, float $minReward, float $maxReward): int
    {
        return $this->execute(
            'UPDATE reward_policies SET min_reward = ?, max_reward = ?, updated_at = NOW() WHERE id = ?',
            'ddi',
            [$minReward, $maxReward, $id]
        );
    }

    /**
     * Delete a reward policy by ID.
     *
     * @param int $id Reward policy ID to delete.
     * @return int Number of affected rows (0 or 1).
     * @throws RuntimeException on execution failure.
     */
    public function delete(int $id): int
    {
        return $this->execute(
            'DELETE FROM reward_policies WHERE id = ?',
            'i',
            [$id]
        );
    }

    /**
     * Check if any accepted reports reference a given reward policy.
     *
     * Used to enforce the deletion constraint: policies referenced by
     * accepted reports cannot be deleted.
     *
     * @param int $policyId Reward policy ID to check.
     * @return bool True if at least one accepted report references the policy.
     */
    public function hasAcceptedReports(int $policyId): bool
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS cnt FROM reports WHERE reward_policy_id = ? AND status = \'accepted\'',
            'i',
            [$policyId]
        );

        return ($row !== null) && ((int) $row['cnt'] > 0);
    }
}
