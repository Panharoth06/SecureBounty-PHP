<?php

require_once __DIR__ . '/../repository/CommentRepository.php';
require_once __DIR__ . '/../repository/ReportRepository.php';
require_once __DIR__ . '/NotificationService.php';

/**
 * CommentService
 *
 * Business logic for report comments and communication between
 * the researcher and program owner.
 *
 * @see Requirement 9.1 — Store comment with author, timestamp, and report reference
 * @see Requirement 9.2 — Restrict access to report submitter and program owner
 * @see Requirement 9.3 — Notify other participant on new comment
 * @see Requirement 9.4 — Display comments in chronological order
 * @see Requirement 9.5 — Return HTTP 403 if unauthorized user attempts access
 */
class CommentService
{
    private CommentRepository $commentRepository;
    private ReportRepository $reportRepository;
    private NotificationService $notificationService;

    /**
     * @param CommentRepository   $commentRepository
     * @param ReportRepository    $reportRepository
     * @param NotificationService $notificationService
     */
    public function __construct(
        CommentRepository $commentRepository,
        ReportRepository $reportRepository,
        NotificationService $notificationService
    ) {
        $this->commentRepository = $commentRepository;
        $this->reportRepository = $reportRepository;
        $this->notificationService = $notificationService;
    }

    /**
     * Add a comment to a report.
     *
     * Verifies the user has access (must be report researcher or program owner),
     * creates the comment, and notifies the other participant.
     *
     * @param int      $reportId Report ID to comment on.
     * @param int      $userId   User ID of the comment author.
     * @param string   $body     Comment body text.
     * @param int|null $parentId Parent comment ID for threaded replies (null = top-level).
     * @return int The ID of the newly created comment.
     * @throws RuntimeException with code 403 if user is not authorized.
     * @throws RuntimeException if report not found.
     */
    public function addComment(int $reportId, int $userId, string $body, ?int $parentId = null): int
    {
        // Verify access — throws 403 if unauthorized
        if (!$this->verifyAccess($reportId, $userId)) {
            throw new RuntimeException('Access denied: you are not authorized to comment on this report.', 403);
        }

        // If parent_id is provided, verify it belongs to the same report
        if ($parentId !== null) {
            $parentComment = $this->commentRepository->findById($parentId);
            if ($parentComment === null || (int) $parentComment['report_id'] !== $reportId) {
                throw new RuntimeException('Invalid parent comment.', 400);
            }
        }

        // Create the comment
        $commentId = $this->commentRepository->create($reportId, $userId, $body, $parentId);

        // Notify the other participant
        $this->notifyOtherParticipant($reportId, $userId);

        return $commentId;
    }

    /**
     * Get all comments for a report.
     *
     * Verifies the user has access before returning comments.
     *
     * @param int $reportId Report ID to retrieve comments for.
     * @param int $userId   User ID requesting the comments.
     * @return array Array of comment records ordered by created_at ASC.
     * @throws RuntimeException with code 403 if user is not authorized.
     */
    public function getCommentsForReport(int $reportId, int $userId): array
    {
        // Verify access — throws 403 if unauthorized
        if (!$this->verifyAccess($reportId, $userId)) {
            throw new RuntimeException('Access denied: you are not authorized to view comments on this report.', 403);
        }

        return $this->commentRepository->findByReportId($reportId);
    }

    /**
     * Verify whether a user has access to comment on / view comments for a report.
     *
     * A user has access if they are either:
     * - The researcher who submitted the report, OR
     * - The program owner of the program the report belongs to.
     *
     * @param int $reportId Report ID to check access for.
     * @param int $userId   User ID to check.
     * @return bool True if user has access, false otherwise.
     * @throws RuntimeException if report not found.
     */
    public function verifyAccess(int $reportId, int $userId): bool
    {
        $report = $this->reportRepository->findById($reportId);

        if ($report === null) {
            throw new RuntimeException('Report not found.', 404);
        }

        $researcherId = (int) $report['researcher_id'];
        $programOwnerId = (int) $report['program_owner_id'];

        return $userId === $researcherId || $userId === $programOwnerId;
    }

    /**
     * Notify the other participant in the conversation when a new comment is added.
     *
     * - If the researcher comments, notify the program owner.
     * - If the program owner comments, notify the researcher.
     *
     * @param int $reportId Report ID the comment was added to.
     * @param int $commentAuthorId User ID of the comment author.
     * @return void
     */
    private function notifyOtherParticipant(int $reportId, int $commentAuthorId): void
    {
        $report = $this->reportRepository->findById($reportId);

        if ($report === null) {
            return;
        }

        $researcherId = (int) $report['researcher_id'];
        $programOwnerId = (int) $report['program_owner_id'];

        // Determine who to notify
        if ($commentAuthorId === $researcherId) {
            $recipientId = $programOwnerId;
        } else {
            $recipientId = $researcherId;
        }

        $this->notificationService->notify(
            $recipientId,
            'comment.new',
            'report',
            $reportId,
            'A new comment was added to report: ' . $report['title']
        );
    }
}
