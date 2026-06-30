<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * ReportRepository
 *
 * Handles all database operations for the `reports` table.
 * Extends BaseRepository to leverage parameterized query helpers.
 *
 * @see Requirement 7.1 — Create report with status 'pending'
 * @see Requirement 7.2 — Require title, description, steps_to_reproduce, impact
 * @see Requirement 7.6 — Reject submission if researcher not enrolled
 * @see Requirement 8.1 — Display reports grouped by status for program owner
 * @see Requirement 8.2 — Update report status with activity logging
 * @see Requirement 8.3 — Support report statuses: pending, triaged, accepted, rejected, resolved
 * @see Requirement 8.4 — Associate reward policy on acceptance
 * @see Requirement 8.5 — Notify researcher on status change
 */
class ReportRepository extends BaseRepository
{
    /**
     * Valid report statuses.
     */
    public const STATUSES = ['pending', 'triaged', 'accepted', 'rejected', 'resolved'];

    /**
     * Insert a new report with status 'pending' and return the new ID.
     *
     * @param int    $programId        Target program ID.
     * @param int    $researcherId     Submitting researcher user ID.
     * @param string $title            Report title.
     * @param string $description      Vulnerability description.
     * @param string $stepsToReproduce Reproduction steps.
     * @param string $impact           Impact assessment.
     * @return int The ID of the newly created report.
     * @throws RuntimeException on insertion failure.
     */
    public function create(
        int $programId,
        int $researcherId,
        string $title,
        string $description,
        string $stepsToReproduce,
        string $impact
    ): int {
        $sql = 'INSERT INTO reports (program_id, researcher_id, title, description, steps_to_reproduce, impact, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, \'pending\', NOW(), NOW())';

        $this->execute($sql, 'iissss', [$programId, $researcherId, $title, $description, $stepsToReproduce, $impact]);

        return $this->lastInsertId();
    }

    /**
     * Find a report by its ID, including researcher and program details via JOIN.
     *
     * @param int $id Report ID.
     * @return array|null Associative array of the report with joined data, or null if not found.
     */
    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT r.*,
                    u.first_name AS researcher_first_name,
                    u.last_name AS researcher_last_name,
                    u.email AS researcher_email,
                    p.title AS program_title,
                    p.owner_id AS program_owner_id,
                    p.status AS program_status
             FROM reports r
             INNER JOIN users u ON r.researcher_id = u.id
             INNER JOIN programs p ON r.program_id = p.id
             WHERE r.id = ?',
            'i',
            [$id]
        );
    }

    /**
     * Find all reports for a given program.
     *
     * @param int $programId Program ID.
     * @return array Array of associative arrays (report rows).
     */
    public function findByProgramId(int $programId): array
    {
        return $this->fetchAll(
            'SELECT r.*, u.first_name AS researcher_first_name, u.last_name AS researcher_last_name
             FROM reports r
             INNER JOIN users u ON r.researcher_id = u.id
             WHERE r.program_id = ?
             ORDER BY r.created_at DESC',
            'i',
            [$programId]
        );
    }

    /**
     * Find all reports submitted by a specific researcher.
     *
     * @param int $researcherId Researcher user ID.
     * @return array Array of associative arrays (report rows with program title).
     */
    public function findByResearcherId(int $researcherId): array
    {
        return $this->fetchAll(
            'SELECT r.*, p.title AS program_title
             FROM reports r
             INNER JOIN programs p ON r.program_id = p.id
             WHERE r.researcher_id = ?
             ORDER BY r.created_at DESC',
            'i',
            [$researcherId]
        );
    }

    /**
     * Update the status of a report.
     *
     * @param int    $reportId Report ID to update.
     * @param string $status   New status (pending, triaged, accepted, rejected, resolved).
     * @return int Number of affected rows (0 or 1).
     * @throws RuntimeException on execution failure.
     */
    public function updateStatus(int $reportId, string $status): int
    {
        return $this->execute(
            'UPDATE reports SET status = ?, updated_at = NOW() WHERE id = ?',
            'si',
            [$status, $reportId]
        );
    }

    /**
     * Update the final_severity field on a report.
     *
     * @param int    $reportId      Report ID.
     * @param string $finalSeverity Final severity level (critical, high, medium, low, informational).
     * @return int Number of affected rows (0 or 1).
     * @throws RuntimeException on execution failure.
     */
    public function updateFinalSeverity(int $reportId, string $finalSeverity): int
    {
        return $this->execute(
            'UPDATE reports SET final_severity = ?, updated_at = NOW() WHERE id = ?',
            'si',
            [$finalSeverity, $reportId]
        );
    }

    /**
     * Set the reward_policy_id for a report (used on acceptance).
     *
     * @param int $reportId       Report ID.
     * @param int $rewardPolicyId Reward policy ID to associate.
     * @return int Number of affected rows (0 or 1).
     * @throws RuntimeException on execution failure.
     */
    public function setRewardPolicy(int $reportId, int $rewardPolicyId): int
    {
        return $this->execute(
            'UPDATE reports SET reward_policy_id = ?, updated_at = NOW() WHERE id = ?',
            'ii',
            [$rewardPolicyId, $reportId]
        );
    }

    /**
     * Update the CVSS fields on a report.
     *
     * @param int    $reportId      Report ID.
     * @param string $cvssVector    CVSS 3.1 vector string.
     * @param float  $cvssScore     Computed CVSS base score.
     * @param string $cvssSeverity  CVSS-derived severity rating.
     * @param string $submittedBy   Who submitted the CVSS ('researcher' or 'program_owner').
     * @return int Number of affected rows (0 or 1).
     * @throws RuntimeException on execution failure.
     */
    public function updateCvss(int $reportId, string $cvssVector, float $cvssScore, string $cvssSeverity, string $submittedBy): int
    {
        return $this->execute(
            'UPDATE reports SET cvss_vector = ?, cvss_score = ?, cvss_severity = ?, cvss_submitted_by = ?, updated_at = NOW() WHERE id = ?',
            'sdssi',
            [$cvssVector, $cvssScore, $cvssSeverity, $submittedBy, $reportId]
        );
    }

    /**
     * Update a report's title and description.
     *
     * @param int    $reportId    Report ID.
     * @param string $title       New title.
     * @param string $description New description.
     * @return int Number of affected rows (0 or 1).
     * @throws RuntimeException on execution failure.
     */
    public function updateReport(int $reportId, string $title, string $description): int
    {
        return $this->execute(
            'UPDATE reports SET title = ?, description = ?, updated_at = NOW() WHERE id = ?',
            'ssi',
            [$title, $description, $reportId]
        );
    }

    /**
     * Delete a report by its ID.
     *
     * @param int $reportId Report ID.
     * @return int Number of affected rows (0 or 1).
     * @throws RuntimeException on execution failure.
     */
    public function delete(int $reportId): int
    {
        return $this->execute(
            'DELETE FROM reports WHERE id = ?',
            'i',
            [$reportId]
        );
    }

    /**
     * Get reports for a program grouped by status.
     *
     * @param int $programId Program ID.
     * @return array Associative array keyed by status, each value being an array of report rows.
     */
    public function getByProgramGroupedByStatus(int $programId): array
    {
        $reports = $this->fetchAll(
            'SELECT r.*, u.first_name AS researcher_first_name, u.last_name AS researcher_last_name
             FROM reports r
             INNER JOIN users u ON r.researcher_id = u.id
             WHERE r.program_id = ?
             ORDER BY r.created_at DESC',
            'i',
            [$programId]
        );

        $grouped = [];
        foreach (self::STATUSES as $status) {
            $grouped[$status] = [];
        }

        foreach ($reports as $report) {
            $grouped[$report['status']][] = $report;
        }

        return $grouped;
    }
}
