<?php

/**
 * StatisticsService
 *
 * Calculates program health indicators: report counts, enrolled researcher counts,
 * response rates, and badge eligibility. Uses direct mysqli queries since it
 * aggregates data across multiple tables (reports, user_programs).
 *
 * @see Requirement 6.1 — Report count as total reports submitted against the program
 * @see Requirement 6.2 — Enrolled researcher count from user_programs records
 * @see Requirement 6.3 — Response rate: percentage of eligible reports responded to within 7 days
 * @see Requirement 6.7 — Display "N/A" when zero reports submitted
 * @see Requirement 6.8 — Display "N/A" when all reports are newer than 7 days
 */
class StatisticsService
{
    private mysqli $conn;

    /**
     * @param mysqli $conn Active MySQLi connection instance.
     */
    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Get complete statistics for a single program.
     *
     * Returns report count, enrolled researcher count, response rate,
     * and badge eligibility (Responsive and Popular).
     *
     * @param int $programId Program ID.
     * @return array Associative array with keys: report_count, enrolled_count, response_rate, badges.
     *
     * @see Requirement 6.1 — Report count
     * @see Requirement 6.2 — Enrolled researcher count
     * @see Requirement 6.3 — Response rate calculation
     * @see Requirement 6.4 — Responsive badge (response_rate >= 80)
     * @see Requirement 6.5 — Popular badge (enrolled_count >= 10)
     */
    public function getProgramStatistics(int $programId): array
    {
        $reportCount = $this->getReportCount($programId);
        $enrolledCount = $this->getEnrolledCount($programId);
        $responseRate = $this->calculateResponseRate($programId);

        $badges = [
            'responsive' => $responseRate !== null && $responseRate >= 80,
            'popular' => $enrolledCount >= 10,
        ];

        return [
            'report_count' => $reportCount,
            'enrolled_count' => $enrolledCount,
            'response_rate' => $responseRate,
            'badges' => $badges,
        ];
    }

    /**
     * Get statistics for multiple programs in bulk.
     *
     * Returns an associative array keyed by program_id, each value containing
     * the same structure as getProgramStatistics().
     *
     * @param array $programIds Array of program IDs.
     * @return array Associative array keyed by program_id.
     */
    public function getBulkProgramStatistics(array $programIds): array
    {
        $results = [];

        foreach ($programIds as $programId) {
            $results[(int) $programId] = $this->getProgramStatistics((int) $programId);
        }

        return $results;
    }

    /**
     * Calculate the response rate for a program.
     *
     * Eligible reports: reports submitted more than 7 days ago.
     * Responded reports: eligible reports where status != 'pending' AND
     * the status was changed (updated_at) within 7 days of submission (created_at).
     *
     * @param int $programId Program ID.
     * @return int|null Response rate as a rounded percentage, or null if no eligible reports.
     *
     * @see Requirement 6.3 — Response rate formula
     * @see Requirement 6.7 — Null when zero reports
     * @see Requirement 6.8 — Null when all reports are newer than 7 days
     */
    public function calculateResponseRate(int $programId): ?int
    {
        // Count eligible reports (older than 7 days)
        $eligibleSql = 'SELECT COUNT(*) AS cnt FROM reports WHERE program_id = ? AND created_at < NOW() - INTERVAL 7 DAY';
        $eligibleCount = $this->fetchCount($eligibleSql, 'i', [$programId]);

        if ($eligibleCount === 0) {
            return null;
        }

        // Count responded reports (eligible + status changed from pending within 7 days of submission)
        $respondedSql = 'SELECT COUNT(*) AS cnt FROM reports
            WHERE program_id = ?
            AND created_at < NOW() - INTERVAL 7 DAY
            AND status != ?
            AND updated_at <= created_at + INTERVAL 7 DAY';
        $respondedCount = $this->fetchCount($respondedSql, 'is', [$programId, 'pending']);

        return (int) round(($respondedCount / $eligibleCount) * 100);
    }

    /**
     * Get the total number of enrolled researchers for a program.
     *
     * @param int $programId Program ID.
     * @return int Number of enrolled researchers.
     *
     * @see Requirement 6.2 — Enrolled researcher count from user_programs
     */
    public function getEnrolledCount(int $programId): int
    {
        $sql = 'SELECT COUNT(*) AS cnt FROM user_programs WHERE program_id = ?';

        return $this->fetchCount($sql, 'i', [$programId]);
    }

    /**
     * Get the total number of reports submitted for a program.
     *
     * @param int $programId Program ID.
     * @return int Number of reports.
     *
     * @see Requirement 6.1 — Report count as total reports of any status
     */
    public function getReportCount(int $programId): int
    {
        $sql = 'SELECT COUNT(*) AS cnt FROM reports WHERE program_id = ?';

        return $this->fetchCount($sql, 'i', [$programId]);
    }

    /**
     * Execute a COUNT query and return the integer result.
     *
     * @param string $sql    SQL with ? placeholders, expected to SELECT COUNT(*) AS cnt.
     * @param string $types  Type string for bind_param.
     * @param array  $params Values to bind.
     * @return int The count value.
     * @throws RuntimeException on query failure.
     */
    private function fetchCount(string $sql, string $types, array $params): int
    {
        $stmt = $this->conn->prepare($sql);

        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare statement: ' . $this->conn->error);
        }

        if ($types !== '' && count($params) > 0) {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Query execution failed: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $result->free();
        $stmt->close();

        return (int) ($row['cnt'] ?? 0);
    }
}
