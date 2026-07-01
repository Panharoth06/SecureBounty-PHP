<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * LeaderboardRepository
 *
 * Handles all database operations for the researcher leaderboard,
 * including reputation scoring, ranking, and severity breakdowns.
 * Extends BaseRepository to leverage parameterized query helpers.
 *
 * @see Requirement 3.1 — Calculate Reputation_Score from accepted reports
 * @see Requirement 3.3 — Display researchers ranked by Reputation_Score descending
 * @see Requirement 9.3 — Tie-breaking by earliest accepted date
 * @see Requirement 9.4 — Exclude researchers with zero accepted reports
 */
class LeaderboardRepository extends BaseRepository
{
    /**
     * Get paginated leaderboard entries ordered by reputation_score DESC,
     * earliest_accepted_at ASC for tie-breaking.
     * Excludes researchers with zero reputation score.
     *
     * @param int $limit  Maximum number of entries to return.
     * @param int $offset Number of entries to skip.
     * @return array Array of associative arrays with user leaderboard data.
     */
    public function getLeaderboard(int $limit, int $offset): array
    {
        return $this->fetchAll(
            'SELECT u.id, u.first_name, u.last_name, u.display_name,
                    u.avatar_path, u.reputation_score, u.earliest_accepted_at,
                    (SELECT COUNT(*) FROM reports r
                     WHERE r.researcher_id = u.id
                       AND r.status = \'accepted\'
                       AND r.final_severity IS NOT NULL) AS accepted_count
             FROM users u
             WHERE u.reputation_score > 0
             ORDER BY u.reputation_score DESC, u.earliest_accepted_at ASC
             LIMIT ? OFFSET ?',
            'ii',
            [$limit, $offset]
        );
    }

    /**
     * Get the total number of researchers with a reputation score greater than zero.
     *
     * @return int Total count of ranked researchers.
     */
    public function getTotalRankedCount(): int
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total FROM users WHERE reputation_score > 0'
        );

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Get the rank position of a specific researcher on the leaderboard.
     * Uses a counting approach: rank = number of users with higher score + 1,
     * with tie-breaking by earliest_accepted_at.
     *
     * @param int $userId The researcher's user ID.
     * @return int|null The rank position (1-based), or null if not ranked (zero score).
     */
    public function getResearcherRank(int $userId): ?int
    {
        // First get the researcher's score data
        $userData = $this->fetchOne(
            'SELECT reputation_score, earliest_accepted_at
             FROM users
             WHERE id = ?',
            'i',
            [$userId]
        );

        if ($userData === null || (int) $userData['reputation_score'] === 0) {
            return null;
        }

        $score = (int) $userData['reputation_score'];
        $earliestAccepted = $userData['earliest_accepted_at'];

        // Count users ranked above this researcher
        // A user ranks higher if they have a higher score,
        // or same score but earlier earliest_accepted_at
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS rank_above FROM users
             WHERE reputation_score > 0
               AND (reputation_score > ?
                    OR (reputation_score = ? AND earliest_accepted_at < ?))',
            'iis',
            [$score, $score, $earliestAccepted]
        );

        return (int) ($row['rank_above'] ?? 0) + 1;
    }

    /**
     * Get a researcher's reputation score data including score, earliest accepted date,
     * and accepted report count.
     *
     * @param int $userId The researcher's user ID.
     * @return array|null Associative array with reputation_score, earliest_accepted_at,
     *                    and accepted_count, or null if user not found.
     */
    public function getResearcherScoreData(int $userId): ?array
    {
        return $this->fetchOne(
            'SELECT u.reputation_score, u.earliest_accepted_at,
                    (SELECT COUNT(*) FROM reports r
                     WHERE r.researcher_id = u.id
                       AND r.status = \'accepted\'
                       AND r.final_severity IS NOT NULL) AS accepted_count
             FROM users u
             WHERE u.id = ?',
            'i',
            [$userId]
        );
    }

    /**
     * Get the count of accepted reports grouped by final_severity for a researcher.
     *
     * @param int $userId The researcher's user ID.
     * @return array Array of associative arrays with 'final_severity' and 'count' keys.
     */
    public function getSeverityBreakdown(int $userId): array
    {
        return $this->fetchAll(
            'SELECT final_severity, COUNT(*) AS count
             FROM reports
             WHERE researcher_id = ?
               AND status = \'accepted\'
               AND final_severity IS NOT NULL
             GROUP BY final_severity',
            'i',
            [$userId]
        );
    }

    /**
     * Update the reputation_score for a specific user.
     *
     * @param int $userId The user ID to update.
     * @param int $score  The new reputation score value.
     * @return void
     * @throws RuntimeException on execution failure.
     */
    public function updateReputationScore(int $userId, int $score): void
    {
        $this->execute(
            'UPDATE users SET reputation_score = ? WHERE id = ?',
            'ii',
            [$score, $userId]
        );
    }

    /**
     * Find the earliest updated_at date from accepted reports for a researcher.
     * This represents when the researcher first had a report accepted.
     *
     * @param int $userId The researcher's user ID.
     * @return string|null The earliest accepted date as a string, or null if no accepted reports.
     */
    public function getEarliestAcceptedDate(int $userId): ?string
    {
        $row = $this->fetchOne(
            'SELECT MIN(updated_at) AS earliest_date
             FROM reports
             WHERE researcher_id = ?
               AND status = \'accepted\'',
            'i',
            [$userId]
        );

        return $row['earliest_date'] ?? null;
    }

    /**
     * Update the earliest_accepted_at timestamp for a user.
     * Used for tie-breaking in leaderboard rankings.
     *
     * @param int         $userId       The user ID to update.
     * @param string|null $earliestDate The earliest accepted date, or null.
     * @return void
     * @throws RuntimeException on execution failure.
     */
    public function updateEarliestAcceptedAt(int $userId, ?string $earliestDate): void
    {
        $this->execute(
            'UPDATE users SET earliest_accepted_at = ? WHERE id = ?',
            'si',
            [$earliestDate, $userId]
        );
    }
}
