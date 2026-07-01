<?php

require_once __DIR__ . '/../repository/ReportRepository.php';
require_once __DIR__ . '/../repository/UserProgramRepository.php';
require_once __DIR__ . '/../repository/ProgramRepository.php';
require_once __DIR__ . '/../repository/RewardPolicyRepository.php';
require_once __DIR__ . '/NotificationService.php';
require_once __DIR__ . '/ActivityLogService.php';
require_once __DIR__ . '/LeaderboardService.php';

/**
 * ReportService
 *
 * Manages report lifecycle: submission, status transitions, reward association.
 * Enforces enrollment and program-active preconditions, dispatches notifications,
 * and logs all state-changing actions.
 *
 * @see Requirement 7.1 — Create report with status 'pending', notify program owner
 * @see Requirement 7.2 — Require title, description, steps_to_reproduce, impact
 * @see Requirement 7.6 — Reject submission if researcher not enrolled (HTTP 403)
 * @see Requirement 8.1 — Display reports grouped by status
 * @see Requirement 8.2 — Update report status with activity logging
 * @see Requirement 8.3 — Support statuses: pending, triaged, accepted, rejected, resolved
 * @see Requirement 8.4 — Associate reward policy on acceptance based on severity
 * @see Requirement 8.5 — Notify researcher on status change
 */
class ReportService
{
    private ReportRepository $reportRepository;
    private UserProgramRepository $userProgramRepository;
    private ProgramRepository $programRepository;
    private RewardPolicyRepository $rewardPolicyRepository;
    private NotificationService $notificationService;
    private ActivityLogService $activityLogService;
    private ?LeaderboardService $leaderboardService;

    /**
     * @param ReportRepository        $reportRepository
     * @param UserProgramRepository   $userProgramRepository
     * @param ProgramRepository       $programRepository
     * @param RewardPolicyRepository  $rewardPolicyRepository
     * @param NotificationService     $notificationService
     * @param ActivityLogService      $activityLogService
     * @param LeaderboardService|null $leaderboardService Optional. When provided, reputation
     *                                                   scores are recalculated on report
     *                                                   acceptance and on status transitions
     *                                                   to/from 'accepted'. When null, the
     *                                                   recalculation step is skipped (no-op).
     */
    public function __construct(
        ReportRepository $reportRepository,
        UserProgramRepository $userProgramRepository,
        ProgramRepository $programRepository,
        RewardPolicyRepository $rewardPolicyRepository,
        NotificationService $notificationService,
        ActivityLogService $activityLogService,
        ?LeaderboardService $leaderboardService = null
    ) {
        $this->reportRepository = $reportRepository;
        $this->userProgramRepository = $userProgramRepository;
        $this->programRepository = $programRepository;
        $this->rewardPolicyRepository = $rewardPolicyRepository;
        $this->notificationService = $notificationService;
        $this->activityLogService = $activityLogService;
        $this->leaderboardService = $leaderboardService;
    }

    /**
     * Submit a new vulnerability report.
     *
     * Validates researcher enrollment and program active status before creating
     * the report. Notifies the program owner and logs the activity.
     *
     * @param int    $researcherId     Submitting researcher user ID.
     * @param int    $programId        Target program ID.
     * @param string $title            Report title.
     * @param string $description      Vulnerability description.
     * @param string $stepsToReproduce Reproduction steps.
     * @param string $impact           Impact assessment.
     * @param string $ipAddress        Client IP for activity logging.
     * @return int The ID of the newly created report.
     * @throws RuntimeException with code 403 if researcher is not enrolled or program is not active.
     * @throws InvalidArgumentException if required fields are empty.
     */
    public function submitReport(
        int $researcherId,
        int $programId,
        string $title,
        string $description,
        string $stepsToReproduce,
        string $impact,
        string $ipAddress = ''
    ): int {
        // Verify program is active
        $program = $this->programRepository->findById($programId);
        if ($program === null || $program['status'] !== 'active') {
            throw new RuntimeException('Program is not active and cannot accept submissions.', 403);
        }

        // Verify researcher is enrolled in the program
        if (!$this->userProgramRepository->isEnrolled($researcherId, $programId)) {
            throw new RuntimeException('Researcher is not enrolled in this program.', 403);
        }

        // Validate required fields
        $this->validateReportFields($title, $description, $stepsToReproduce, $impact);

        // Create the report
        $reportId = $this->reportRepository->create(
            $programId,
            $researcherId,
            $title,
            $description,
            $stepsToReproduce,
            $impact
        );

        // Notify program owner
        $this->notificationService->notify(
            (int) $program['owner_id'],
            'report.submitted',
            'report',
            $reportId,
            "New vulnerability report submitted: \"{$title}\""
        );

        // Log activity
        $this->activityLogService->log(
            $researcherId,
            'report.submit',
            'report',
            $reportId,
            ['program_id' => $programId, 'title' => $title],
            $ipAddress
        );

        return $reportId;
    }

    /**
     * Change the status of a report.
     *
     * Updates the status, logs the change, and notifies the submitting researcher.
     *
     * @param int    $reportId   Report ID to update.
     * @param string $status     New status (pending, triaged, accepted, rejected, resolved).
     * @param int    $userId     Acting user ID (for activity log).
     * @param string $ipAddress  Client IP for activity logging.
     * @return bool True on success.
     * @throws RuntimeException if report not found or invalid status.
     */
    public function changeStatus(int $reportId, string $status, int $userId, string $ipAddress = ''): bool
    {
        if (!in_array($status, ReportRepository::STATUSES, true)) {
            throw new RuntimeException('Invalid report status: ' . $status);
        }

        $report = $this->reportRepository->findById($reportId);
        if ($report === null) {
            throw new RuntimeException('Report not found.');
        }

        $previousStatus = $report['status'];

        $this->reportRepository->updateStatus($reportId, $status);

        // Recalculate researcher reputation score on transitions to/from 'accepted'
        // (Requirement 3.6, 3.7, 9.5: rejection floors score at zero; acceptance awards points)
        if (
            $this->leaderboardService !== null
            && ($previousStatus === 'accepted' || $status === 'accepted')
        ) {
            $this->leaderboardService->recalculateScore((int) $report['researcher_id']);
        }

        // Log activity
        $this->activityLogService->log(
            $userId,
            'report.status_change',
            'report',
            $reportId,
            ['previous_status' => $previousStatus, 'new_status' => $status],
            $ipAddress
        );

        // Notify the researcher
        $this->notificationService->notify(
            (int) $report['researcher_id'],
            'report.status_change',
            'report',
            $reportId,
            "Your report \"{$report['title']}\" status changed to: {$status}"
        );

        return true;
    }

    /**
     * Accept a report: sets status to 'accepted', assigns final severity,
     * and auto-links the matching reward policy for the program.
     *
     * @param int    $reportId      Report ID to accept.
     * @param string $finalSeverity Final severity level (critical, high, medium, low, informational).
     * @param int    $userId        Acting user ID (for activity log).
     * @param string $ipAddress     Client IP for activity logging.
     * @return bool True on success.
     * @throws RuntimeException if report not found.
     */
    public function acceptReport(int $reportId, string $finalSeverity, int $userId, string $ipAddress = ''): bool
    {
        $report = $this->reportRepository->findById($reportId);
        if ($report === null) {
            throw new RuntimeException('Report not found.');
        }

        $previousStatus = $report['status'];

        // Update status to 'accepted'
        $this->reportRepository->updateStatus($reportId, 'accepted');

        // Set final_severity on the report
        $this->setFinalSeverity($reportId, $finalSeverity);

        // Auto-link reward policy by matching severity for the program
        $this->autoLinkRewardPolicy($reportId, (int) $report['program_id'], $finalSeverity);

        // Recalculate researcher reputation score (Requirement 9.2, 9.5)
        if ($this->leaderboardService !== null) {
            $this->leaderboardService->recalculateScore((int) $report['researcher_id']);
        }

        // Log activity
        $this->activityLogService->log(
            $userId,
            'report.accept',
            'report',
            $reportId,
            [
                'previous_status' => $previousStatus,
                'new_status' => 'accepted',
                'final_severity' => $finalSeverity,
            ],
            $ipAddress
        );

        // Notify the researcher
        $this->notificationService->notify(
            (int) $report['researcher_id'],
            'report.status_change',
            'report',
            $reportId,
            "Your report \"{$report['title']}\" has been accepted with severity: {$finalSeverity}"
        );

        return true;
    }

    /**
     * Get full report detail including all related data.
     *
     * @param int $reportId Report ID.
     * @return array|null Report data with joined fields, or null if not found.
     */
    public function getReportDetail(int $reportId): ?array
    {
        return $this->reportRepository->findById($reportId);
    }

    /**
     * Set the final_severity field on a report.
     *
     * @param int    $reportId      Report ID.
     * @param string $finalSeverity Severity level.
     * @return void
     */
    private function setFinalSeverity(int $reportId, string $finalSeverity): void
    {
        // Use the base connection through the repository's parent to update final_severity
        $this->reportRepository->updateFinalSeverity($reportId, $finalSeverity);
    }

    /**
     * Auto-link the reward policy matching the given severity for the program.
     *
     * Searches the program's reward policies for one matching the final severity.
     * If found, associates it with the report.
     *
     * @param int    $reportId  Report ID.
     * @param int    $programId Program ID.
     * @param string $severity  Severity level to match.
     * @return void
     */
    private function autoLinkRewardPolicy(int $reportId, int $programId, string $severity): void
    {
        $policies = $this->rewardPolicyRepository->findByProgramId($programId);

        foreach ($policies as $policy) {
            if ($policy['severity'] === $severity) {
                $this->reportRepository->setRewardPolicy($reportId, (int) $policy['id']);
                break;
            }
        }
    }

    /**
     * Validate that required report fields are non-empty.
     *
     * @param string $title            Report title.
     * @param string $description      Description.
     * @param string $stepsToReproduce Steps to reproduce.
     * @param string $impact           Impact assessment.
     * @throws InvalidArgumentException if any field is empty.
     */
    private function validateReportFields(
        string $title,
        string $description,
        string $stepsToReproduce,
        string $impact
    ): void {
        $errors = [];

        if (empty(trim($title))) {
            $errors[] = 'Report title is required.';
        }

        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }
    }
}
