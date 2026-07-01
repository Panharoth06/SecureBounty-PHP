<?php

require_once __DIR__ . '/../repository/LeaderboardRepository.php';

/**
 * LeaderboardService
 *
 * Manages researcher leaderboard operations including paginated listing,
 * rank retrieval, statistics, and reputation score recalculation.
 * Reputation scores are derived exclusively from accepted reports with
 * non-null final_severity values.
 *
 * @see Requirement 3.1 — Calculate Reputation_Score from accepted reports
 * @see Requirement 3.2 — Point values per severity level
 * @see Requirement 3.3 — Display researchers ranked by Reputation_Score descending
 * @see Requirement 3.5 — Paginate leaderboard with 25 entries per page
 * @see Requirement 3.7 — Score floor at zero on rejection
 * @see Requirement 9.1 — Only count reports with status=accepted AND non-null final_severity
 * @see Requirement 9.2 — Award points when final_severity is assigned
 * @see Requirement 9.5 — Recalculate on status/severity change
 * @see Requirement 9.6 — Handle severity changes (subtract old, add new)
 */
class LeaderboardService
{
    /**
     * Point values per accepted report severity level.
     *
     * @see Requirement 3.2
     */
    public const SEVERITY_POINTS = [
        'critical' => 50,
        'high' => 30,
        'medium' => 15,
        'low' => 5,
        'informational' => 1,
    ];

    private LeaderboardRepository $leaderboardRepository;

    /**
     * @param LeaderboardRepository $leaderboardRepository Repository for leaderboard DB operations.
     */
    public function __construct(LeaderboardRepository $leaderboardRepository)
    {
        $this->leaderboardRepository = $leaderboardRepository;
    }

    /**
     * Get a paginated leaderboard with ranked researchers.
     *
     * Returns researchers ordered by reputation_score DESC with tie-breaking
     * by earliest_accepted_at ASC. Each entry includes severity breakdown.
     *
     * @param int $page    Current page number (1-based).
     * @param int $perPage Number of entries per page.
     * @return array Associative array with keys: entries, total, page, perPage, totalPages.
     *
     * @see Requirement 3.3 — Ranked by Reputation_Score descending
     * @see Requirement 3.5 — Paginate with 25 entries per page
     */
    public function getLeaderboard(int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $offset = ($page - 1) * $perPage;

        $entries = $this->leaderboardRepository->getLeaderboard($perPage, $offset);
        $total = $this->leaderboardRepository->getTotalRankedCount();

        // Enrich each entry with severity breakdown
        foreach ($entries as &$entry) {
            $entry['severity_breakdown'] = $this->getSeverityBreakdownMap((int) $entry['id']);
        }
        unset($entry);

        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;

        return [
            'entries' => $entries,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * Get the rank position of a specific researcher.
     *
     * Delegates to the repository which handles tie-breaking by earliest_accepted_at.
     *
     * @param int $userId The researcher's user ID.
     * @return int|null The rank (1-based), or null if not ranked (zero score).
     *
     * @see Requirement 9.3 — Tie-breaking by earliest accepted date
     * @see Requirement 9.4 — Exclude researchers with zero accepted reports
     */
    public function getResearcherRank(int $userId): ?int
    {
        return $this->leaderboardRepository->getResearcherRank($userId);
    }

    /**
     * Get comprehensive statistics for a researcher including rank, score,
     * accepted count, and severity breakdown.
     *
     * @param int $userId The researcher's user ID.
     * @return array Associative array with keys: rank, score, accepted_count, severity_breakdown.
     *
     * @see Requirement 3.1 — Reputation_Score from accepted reports
     */
    public function getResearcherStats(int $userId): array
    {
        $rank = $this->leaderboardRepository->getResearcherRank($userId);
        $scoreData = $this->leaderboardRepository->getResearcherScoreData($userId);

        $score = 0;
        $acceptedCount = 0;

        if ($scoreData !== null) {
            $score = (int) ($scoreData['reputation_score'] ?? 0);
            $acceptedCount = (int) ($scoreData['accepted_count'] ?? 0);
        }

        $severityBreakdown = $this->getSeverityBreakdownMap($userId);

        return [
            'rank' => $rank,
            'score' => $score,
            'accepted_count' => $acceptedCount,
            'severity_breakdown' => $severityBreakdown,
        ];
    }

    /**
     * Recalculate and persist the reputation score for a researcher.
     *
     * Calculation:
     * 1. Get severity breakdown (counts per severity from accepted reports with final_severity)
     * 2. Sum: count * points_per_severity for each severity level
     * 3. Floor at zero (score cannot be negative)
     * 4. Update reputation_score in the database
     * 5. Update earliest_accepted_at for tie-breaking
     *
     * @param int $researcherId The researcher's user ID.
     * @return int The newly calculated reputation score.
     *
     * @see Requirement 3.1 — Calculate Reputation_Score
     * @see Requirement 3.2 — Point values: Critical=50, High=30, Medium=15, Low=5, Informational=1
     * @see Requirement 3.7 — Score floor at zero
     * @see Requirement 9.1 — Only accepted reports with non-null final_severity
     * @see Requirement 9.5 — Recalculate on status/severity change
     * @see Requirement 9.6 — Handle severity changes
     */
    public function recalculateScore(int $researcherId): int
    {
        // Step 1: Get severity breakdown from repository
        $breakdownRows = $this->leaderboardRepository->getSeverityBreakdown($researcherId);

        // Step 2: Calculate score from breakdown
        $score = 0;
        foreach ($breakdownRows as $row) {
            $severity = strtolower($row['final_severity']);
            $count = (int) $row['count'];
            $points = self::SEVERITY_POINTS[$severity] ?? 0;
            $score += $count * $points;
        }

        // Step 3: Floor at zero
        $score = max(0, $score);

        // Step 4: Update reputation_score
        $this->leaderboardRepository->updateReputationScore($researcherId, $score);

        // Step 5: Update earliest_accepted_at for tie-breaking
        $earliestDate = $this->leaderboardRepository->getEarliestAcceptedDate($researcherId);
        $this->updateEarliestAcceptedAt($researcherId, $earliestDate);

        return $score;
    }

    /**
     * Get the severity breakdown as a normalized map for a researcher.
     *
     * Returns an associative array with all severity levels as keys and
     * their counts as values. Missing severities default to 0.
     *
     * @param int $userId The researcher's user ID.
     * @return array Associative array e.g. ['critical' => 2, 'high' => 5, ...].
     */
    private function getSeverityBreakdownMap(int $userId): array
    {
        $breakdownRows = $this->leaderboardRepository->getSeverityBreakdown($userId);

        // Initialize all severities to zero
        $map = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'informational' => 0,
        ];

        foreach ($breakdownRows as $row) {
            $severity = strtolower($row['final_severity']);
            if (isset($map[$severity])) {
                $map[$severity] = (int) $row['count'];
            }
        }

        return $map;
    }

    /**
     * Update the earliest_accepted_at timestamp for a researcher.
     *
     * This is used for tie-breaking in leaderboard ranking.
     *
     * @param int         $userId       The researcher's user ID.
     * @param string|null $earliestDate The earliest accepted date, or null.
     *
     * @see Requirement 9.3 — Tie-breaking by earliest accepted date
     */
    private function updateEarliestAcceptedAt(int $userId, ?string $earliestDate): void
    {
        $this->leaderboardRepository->updateEarliestAcceptedAt($userId, $earliestDate);
    }
}
