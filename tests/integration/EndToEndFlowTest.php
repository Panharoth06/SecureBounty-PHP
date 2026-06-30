<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\TestDatabaseHelper;

require_once __DIR__ . '/../../model/repository/UserRepository.php';
require_once __DIR__ . '/../../model/repository/ProgramRepository.php';
require_once __DIR__ . '/../../model/repository/ReportRepository.php';
require_once __DIR__ . '/../../model/repository/RewardPolicyRepository.php';
require_once __DIR__ . '/../../model/repository/CommentRepository.php';
require_once __DIR__ . '/../../model/repository/ActivityLogRepository.php';
require_once __DIR__ . '/../../model/repository/NotificationRepository.php';
require_once __DIR__ . '/../../model/repository/UserProgramRepository.php';
require_once __DIR__ . '/../../model/services/AuthService.php';
require_once __DIR__ . '/../../model/services/ProgramService.php';
require_once __DIR__ . '/../../model/services/ReportService.php';
require_once __DIR__ . '/../../model/services/RewardPolicyService.php';
require_once __DIR__ . '/../../model/services/CommentService.php';
require_once __DIR__ . '/../../model/services/ActivityLogService.php';
require_once __DIR__ . '/../../model/services/NotificationService.php';

/**
 * End-to-end integration test for the complete SecureBounty bounty lifecycle.
 *
 * Exercises the full happy-path workflow across multiple services wired together
 * with real repositories backed by the test database:
 *   register (owner + researcher) → login → create program → add reward policy →
 *   publish → enroll researcher → submit report → change status → accept report
 *   (verify reward policy auto-link) → add comment.
 *
 * @covers AuthService
 * @covers ProgramService
 * @covers RewardPolicyService
 * @covers ReportService
 * @covers CommentService
 *
 * Validates: Requirements 1.1, 2.1, 4.1, 4.2, 6.2, 7.1, 8.2, 9.1, 12.1
 */
class EndToEndFlowTest extends TestCase
{
    private const ROLE_PROGRAM_OWNER = 2;
    private const ROLE_RESEARCHER = 3;
    private const IP = '127.0.0.1';

    private static mysqli $conn;

    private UserRepository $userRepo;
    private ProgramRepository $programRepo;
    private ReportRepository $reportRepo;
    private RewardPolicyRepository $rewardPolicyRepo;
    private CommentRepository $commentRepo;
    private ActivityLogRepository $activityLogRepo;
    private NotificationRepository $notificationRepo;
    private UserProgramRepository $userProgramRepo;

    private AuthService $authService;
    private ProgramService $programService;
    private ReportService $reportService;
    private RewardPolicyService $rewardPolicyService;
    private CommentService $commentService;
    private ActivityLogService $activityLogService;
    private NotificationService $notificationService;

    public static function setUpBeforeClass(): void
    {
        TestDatabaseHelper::migrate();
        TestDatabaseHelper::seed();
        self::$conn = TestDatabaseHelper::getConnection();
    }

    protected function setUp(): void
    {
        TestDatabaseHelper::cleanUp();
        TestDatabaseHelper::seed();

        $this->userRepo = new UserRepository(self::$conn);
        $this->programRepo = new ProgramRepository(self::$conn);
        $this->reportRepo = new ReportRepository(self::$conn);
        $this->rewardPolicyRepo = new RewardPolicyRepository(self::$conn);
        $this->commentRepo = new CommentRepository(self::$conn);
        $this->activityLogRepo = new ActivityLogRepository(self::$conn);
        $this->notificationRepo = new NotificationRepository(self::$conn);
        $this->userProgramRepo = new UserProgramRepository(self::$conn);

        $this->activityLogService = new ActivityLogService($this->activityLogRepo);
        $this->notificationService = new NotificationService($this->notificationRepo);
        $this->authService = new AuthService($this->userRepo, $this->activityLogService);
        $this->programService = new ProgramService($this->programRepo, $this->activityLogService, self::$conn);
        $this->reportService = new ReportService(
            $this->reportRepo,
            $this->userProgramRepo,
            $this->programRepo,
            $this->rewardPolicyRepo,
            $this->notificationService,
            $this->activityLogService
        );
        $this->rewardPolicyService = new RewardPolicyService($this->rewardPolicyRepo, $this->activityLogService);
        $this->commentService = new CommentService($this->commentRepo, $this->reportRepo, $this->notificationService);

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SERVER['REMOTE_ADDR'] = self::IP;
    }

    public static function tearDownAfterClass(): void
    {
        TestDatabaseHelper::cleanUp();
    }

    /**
     * Full bounty lifecycle from registration through accepted report and comment.
     *
     * Validates: Requirements 1.1, 2.1, 4.1, 4.2, 6.2, 7.1, 8.2, 9.1, 12.1
     */
    public function testFullBountyLifecycle(): void
    {
        // --- Register a program owner (Req 1.1) ---
        $ownerResult = $this->authService->register(
            'Alice',
            'Owner',
            'alice.owner@example.com',
            'OwnerPass123',
            self::ROLE_PROGRAM_OWNER
        );
        $this->assertTrue($ownerResult['success'], 'Program owner registration should succeed');
        $ownerId = (int) $ownerResult['user_id'];
        $this->assertGreaterThan(0, $ownerId);

        // --- Register a researcher (Req 1.1) ---
        $researcherResult = $this->authService->register(
            'Bob',
            'Researcher',
            'bob.researcher@example.com',
            'ResearchPass123',
            self::ROLE_RESEARCHER
        );
        $this->assertTrue($researcherResult['success'], 'Researcher registration should succeed');
        $researcherId = (int) $researcherResult['user_id'];
        $this->assertGreaterThan(0, $researcherId);

        // --- Login as the owner (Req 2.1) ---
        $login = $this->authService->login('alice.owner@example.com', 'OwnerPass123');
        $this->assertTrue($login['success'], 'Owner should be able to log in');
        $this->assertSame('alice.owner@example.com', $login['user']['email']);

        // --- Create a program in draft status (Req 4.1) ---
        $programId = $this->programService->createProgram(
            $ownerId,
            'Web Application Bounty',
            'Find vulnerabilities in our flagship web application.',
            '*.example.com',
            self::IP
        );
        $this->assertGreaterThan(0, $programId);
        $this->assertSame('draft', $this->programRepo->findById($programId)['status']);

        // --- Add a reward policy (precondition for publishing) ---
        $policyResult = $this->rewardPolicyService->createPolicy(
            $programId,
            'high',
            500.00,
            5000.00,
            $ownerId,
            self::IP
        );
        $this->assertTrue($policyResult['success'], 'Reward policy creation should succeed');
        $policyId = (int) $policyResult['policy_id'];
        $this->assertGreaterThan(0, $policyId);

        // --- Publish the program (draft → active) (Req 4.2) ---
        $this->assertTrue($this->programService->publishProgram($programId, $ownerId, self::IP));
        $this->assertSame('active', $this->programRepo->findById($programId)['status']);

        // --- Enroll the researcher in the program (Req 6.2) ---
        $this->assertTrue($this->userProgramRepo->enroll($researcherId, $programId), 'Enrollment should succeed');
        $this->assertTrue($this->userProgramRepo->isEnrolled($researcherId, $programId));

        // --- Submit a report (Req 7.1) ---
        $reportId = $this->reportService->submitReport(
            $researcherId,
            $programId,
            'Reflected XSS in search',
            'A reflected XSS exists in the search parameter.',
            "1. Open /search\n2. Inject <script>alert(1)</script>\n3. Observe execution",
            'Session hijacking of authenticated users.',
            self::IP
        );
        $this->assertGreaterThan(0, $reportId);
        $this->assertSame('pending', $this->reportRepo->findById($reportId)['status']);

        // --- Change status pending → triaged (Req 8.2) ---
        $this->assertTrue($this->reportService->changeStatus($reportId, 'triaged', $ownerId, self::IP));
        $this->assertSame('triaged', $this->reportRepo->findById($reportId)['status']);

        // --- Accept the report and verify reward policy auto-link (Req 8.2) ---
        $this->assertTrue($this->reportService->acceptReport($reportId, 'high', $ownerId, self::IP));
        $acceptedReport = $this->reportRepo->findById($reportId);
        $this->assertSame('accepted', $acceptedReport['status']);
        $this->assertSame('high', $acceptedReport['final_severity']);
        $this->assertEquals(
            $policyId,
            (int) $acceptedReport['reward_policy_id'],
            'Accepting a high-severity report should auto-link the matching high reward policy'
        );

        // --- Add a comment by the program owner (Req 9.1) ---
        $commentId = $this->commentService->addComment(
            $reportId,
            $ownerId,
            'Confirmed. We will ship a fix in the next release.'
        );
        $this->assertGreaterThan(0, $commentId);

        $comments = $this->commentRepo->findByReportId($reportId);
        $this->assertCount(1, $comments);
        $this->assertSame('Confirmed. We will ship a fix in the next release.', $comments[0]['body']);
        $this->assertEquals($ownerId, (int) $comments[0]['user_id']);

        // --- Verify the researcher can also comment and is authorized (Req 9.1) ---
        $replyId = $this->commentService->addComment(
            $reportId,
            $researcherId,
            'Thanks for the quick triage!'
        );
        $this->assertGreaterThan(0, $replyId);
        $this->assertCount(2, $this->commentRepo->findByReportId($reportId));

        // --- Spot-check the audit trail accumulated across the flow (Req 12.1) ---
        $actions = array_column($this->activityLogRepo->getAll(100, 0), 'action');
        $this->assertContains('user.register', $actions);
        $this->assertContains('program.create', $actions);
        $this->assertContains('program.publish', $actions);
        $this->assertContains('report.submit', $actions);
        $this->assertContains('report.status_change', $actions);
        $this->assertContains('report.accept', $actions);
    }
}
