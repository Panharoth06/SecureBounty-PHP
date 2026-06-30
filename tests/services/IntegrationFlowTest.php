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
 * Integration tests for end-to-end service-layer flows.
 *
 * Tests the full lifecycle of the SecureBounty platform at the service layer:
 * - Full researcher flow (register → login → program → report → comment)
 * - Admin user management flow (deactivate/reactivate)
 * - Activity log verification across operations
 *
 * @covers AuthService
 * @covers ProgramService
 * @covers ReportService
 * @covers CommentService
 * @covers RewardPolicyService
 * @covers ActivityLogService
 *
 * Validates: Requirements 1.1, 2.1, 4.1, 4.2, 6.2, 7.1, 8.2, 9.1, 10.2, 12.1
 */
class IntegrationFlowTest extends TestCase
{
    private static mysqli $conn;

    // Repositories
    private UserRepository $userRepo;
    private ProgramRepository $programRepo;
    private ReportRepository $reportRepo;
    private RewardPolicyRepository $rewardPolicyRepo;
    private CommentRepository $commentRepo;
    private ActivityLogRepository $activityLogRepo;
    private NotificationRepository $notificationRepo;
    private UserProgramRepository $userProgramRepo;

    // Services
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

        // Instantiate repositories
        $this->userRepo = new UserRepository(self::$conn);
        $this->programRepo = new ProgramRepository(self::$conn);
        $this->reportRepo = new ReportRepository(self::$conn);
        $this->rewardPolicyRepo = new RewardPolicyRepository(self::$conn);
        $this->commentRepo = new CommentRepository(self::$conn);
        $this->activityLogRepo = new ActivityLogRepository(self::$conn);
        $this->notificationRepo = new NotificationRepository(self::$conn);
        $this->userProgramRepo = new UserProgramRepository(self::$conn);

        // Instantiate services
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
        $this->commentService = new CommentService(
            $this->commentRepo,
            $this->reportRepo,
            $this->notificationService
        );

        // Suppress session-related warnings in test environment
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    public static function tearDownAfterClass(): void
    {
        TestDatabaseHelper::cleanUp();
    }

    /**
     * Test 1: Full researcher flow.
     *
     * Registration → Login → Create Program → Add Reward Policy → Publish →
     * Enroll Researcher → Submit Report → Change Status → Accept → Comment →
     * Verify activity logs and notifications.
     *
     * Validates: Requirements 1.1, 2.1, 4.1, 4.2, 6.2, 7.1, 8.2, 9.1, 12.1
     */
    public function testFullResearcherFlow(): void
    {
        // Step 1: Register a program owner
        $ownerResult = $this->authService->register(
            'Alice',
            'Owner',
            'alice@example.com',
            'SecurePass123',
            2 // Program_Owner role
        );
        $this->assertTrue($ownerResult['success'], 'Program owner registration should succeed');
        $this->assertNotNull($ownerResult['user_id']);
        $ownerId = $ownerResult['user_id'];

        // Step 2: Register a researcher
        $researcherResult = $this->authService->register(
            'Bob',
            'Researcher',
            'bob@example.com',
            'SecurePass456',
            3 // Researcher role
        );
        $this->assertTrue($researcherResult['success'], 'Researcher registration should succeed');
        $this->assertNotNull($researcherResult['user_id']);
        $researcherId = $researcherResult['user_id'];

        // Step 3: Login as owner
        $loginResult = $this->authService->login('alice@example.com', 'SecurePass123');
        $this->assertTrue($loginResult['success'], 'Owner login should succeed');
        $this->assertNotNull($loginResult['user']);
        $this->assertSame('alice@example.com', $loginResult['user']['email']);

        // Step 4: Create program
        $programId = $this->programService->createProgram(
            $ownerId,
            'Web App Security Program',
            'Find vulnerabilities in our web application',
            '*.example.com',
            '127.0.0.1'
        );
        $this->assertGreaterThan(0, $programId);

        $program = $this->programRepo->findById($programId);
        $this->assertSame('draft', $program['status']);

        // Step 5: Add reward policy
        $policyResult = $this->rewardPolicyService->createPolicy(
            $programId,
            'high',
            500.00,
            5000.00,
            $ownerId,
            '127.0.0.1'
        );
        $this->assertTrue($policyResult['success'], 'Reward policy creation should succeed');
        $this->assertNotNull($policyResult['policy_id']);

        // Step 6: Publish program
        $published = $this->programService->publishProgram($programId, $ownerId, '127.0.0.1');
        $this->assertTrue($published);

        $program = $this->programRepo->findById($programId);
        $this->assertSame('active', $program['status']);

        // Step 7: Enroll researcher
        $enrolled = $this->userProgramRepo->enroll($researcherId, $programId);
        $this->assertTrue($enrolled, 'Enrollment should succeed');
        $this->assertTrue($this->userProgramRepo->isEnrolled($researcherId, $programId));

        // Step 8: Submit report
        $reportId = $this->reportService->submitReport(
            $researcherId,
            $programId,
            'XSS in Search Field',
            'Reflected XSS found in the search input parameter',
            '1. Navigate to search page\n2. Enter <script>alert(1)</script>\n3. Observe alert',
            'Attacker can steal session cookies from authenticated users',
            '127.0.0.1'
        );
        $this->assertGreaterThan(0, $reportId);

        $report = $this->reportRepo->findById($reportId);
        $this->assertSame('pending', $report['status']);

        // Step 9: Change status to triaged
        $statusChanged = $this->reportService->changeStatus($reportId, 'triaged', $ownerId, '127.0.0.1');
        $this->assertTrue($statusChanged);

        $report = $this->reportRepo->findById($reportId);
        $this->assertSame('triaged', $report['status']);

        // Step 10: Accept report
        $accepted = $this->reportService->acceptReport($reportId, 'high', $ownerId, '127.0.0.1');
        $this->assertTrue($accepted);

        $report = $this->reportRepo->findById($reportId);
        $this->assertSame('accepted', $report['status']);
        $this->assertSame('high', $report['final_severity']);

        // Step 11: Add comment by program owner
        $commentId = $this->commentService->addComment(
            $reportId,
            $ownerId,
            'Great find! We will patch this in the next release.'
        );
        $this->assertGreaterThan(0, $commentId);

        // Verify comment was stored correctly
        $comments = $this->commentRepo->findByReportId($reportId);
        $this->assertCount(1, $comments);
        $this->assertSame('Great find! We will patch this in the next release.', $comments[0]['body']);
        $this->assertEquals($ownerId, $comments[0]['user_id']);

        // Step 12: Verify activity logs were created
        $logs = $this->activityLogRepo->getAll(50, 0);
        $logActions = array_column($logs, 'action');

        $this->assertContains('user.register', $logActions, 'Registration should be logged');
        $this->assertContains('user.login', $logActions, 'Login should be logged');
        $this->assertContains('program.create', $logActions, 'Program creation should be logged');
        $this->assertContains('reward_policy.create', $logActions, 'Reward policy creation should be logged');
        $this->assertContains('program.publish', $logActions, 'Program publish should be logged');
        $this->assertContains('report.submit', $logActions, 'Report submission should be logged');
        $this->assertContains('report.status_change', $logActions, 'Status change should be logged');
        $this->assertContains('report.accept', $logActions, 'Report acceptance should be logged');

        // Step 13: Verify notifications were sent
        // Owner should have received a notification when report was submitted
        $ownerNotifications = $this->notificationRepo->getByUserId($ownerId, 50, 0);
        $ownerNotifTypes = array_column($ownerNotifications, 'type');
        $this->assertContains('report.submitted', $ownerNotifTypes, 'Owner should be notified of report submission');

        // Researcher should be notified of status changes and comments
        $researcherNotifications = $this->notificationRepo->getByUserId($researcherId, 50, 0);
        $researcherNotifTypes = array_column($researcherNotifications, 'type');
        $this->assertContains('report.status_change', $researcherNotifTypes, 'Researcher should be notified of status change');
        $this->assertContains('comment.new', $researcherNotifTypes, 'Researcher should be notified of new comment');
    }

    /**
     * Test 2: Admin user management flow.
     *
     * Create admin → Deactivate user → Verify cannot login →
     * Reactivate user → Verify can login again.
     *
     * Validates: Requirements 2.1, 10.2
     */
    public function testAdminUserManagementFlow(): void
    {
        // Create a regular user first
        $userResult = $this->authService->register(
            'Charlie',
            'User',
            'charlie@example.com',
            'SecurePass789',
            3 // Researcher role
        );
        $this->assertTrue($userResult['success']);
        $userId = $userResult['user_id'];

        // Verify user can login initially
        $loginResult = $this->authService->login('charlie@example.com', 'SecurePass789');
        $this->assertTrue($loginResult['success'], 'User should be able to login when active');

        // Create admin user directly in DB (Admin role = 1)
        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (1, 'Admin', 'Super', 'admin@example.com', '" .
            password_hash('AdminPass123', PASSWORD_BCRYPT) . "', 'active')"
        );
        $adminId = (int) self::$conn->insert_id;
        $this->assertGreaterThan(0, $adminId);

        // Deactivate user via UserRepository->updateStatus()
        $affected = $this->userRepo->updateStatus($userId, 'inactive');
        $this->assertEquals(1, $affected);

        // Log the deactivation activity (as admin would)
        $this->activityLogService->log(
            $adminId,
            'user.deactivate',
            'user',
            $userId,
            ['previous_status' => 'active', 'new_status' => 'inactive'],
            '127.0.0.1'
        );

        // Verify user cannot login when inactive
        $loginResult = $this->authService->login('charlie@example.com', 'SecurePass789');
        $this->assertFalse($loginResult['success'], 'Deactivated user should not be able to login');

        // Reactivate user
        $affected = $this->userRepo->updateStatus($userId, 'active');
        $this->assertEquals(1, $affected);

        // Log the reactivation activity
        $this->activityLogService->log(
            $adminId,
            'user.reactivate',
            'user',
            $userId,
            ['previous_status' => 'inactive', 'new_status' => 'active'],
            '127.0.0.1'
        );

        // Verify user can login again
        $loginResult = $this->authService->login('charlie@example.com', 'SecurePass789');
        $this->assertTrue($loginResult['success'], 'Reactivated user should be able to login');

        // Verify activity logs recorded the admin actions
        $logs = $this->activityLogRepo->filterByUser($adminId, 10, 0);
        $adminActions = array_column($logs, 'action');
        $this->assertContains('user.deactivate', $adminActions, 'Deactivation should be logged');
        $this->assertContains('user.reactivate', $adminActions, 'Reactivation should be logged');
    }

    /**
     * Test 3: Activity log verification across multiple operations.
     *
     * Performs multiple operations and verifies that each one creates
     * an activity log entry with correct fields (user_id, action, target_entity,
     * target_id, ip_address).
     *
     * Validates: Requirements 12.1
     */
    public function testActivityLogCreationForMultipleOperations(): void
    {
        // Register a user
        $ownerResult = $this->authService->register(
            'Diana',
            'ProgramOwner',
            'diana@example.com',
            'SecurePass111',
            2 // Program_Owner
        );
        $this->assertTrue($ownerResult['success']);
        $ownerId = $ownerResult['user_id'];

        // Create a program
        $programId = $this->programService->createProgram(
            $ownerId,
            'API Security Audit',
            'Test our REST API for vulnerabilities',
            'api.example.com/*',
            '10.0.0.1'
        );

        // Add a reward policy
        $policyResult = $this->rewardPolicyService->createPolicy(
            $programId,
            'critical',
            1000.00,
            10000.00,
            $ownerId,
            '10.0.0.2'
        );
        $this->assertTrue($policyResult['success']);

        // Publish the program
        $this->programService->publishProgram($programId, $ownerId, '10.0.0.3');

        // Query activity_logs table directly
        $result = self::$conn->query('SELECT * FROM activity_logs ORDER BY id ASC');
        $allLogs = [];
        while ($row = $result->fetch_assoc()) {
            $allLogs[] = $row;
        }

        // Verify we have at least the expected number of log entries
        // Registration (x1) + login (from register side-effect: none, just register) +
        // program.create + reward_policy.create + program.publish = at least 4
        $this->assertGreaterThanOrEqual(4, count($allLogs));

        // Verify registration log entry
        $registerLog = $this->findLogByAction($allLogs, 'user.register');
        $this->assertNotNull($registerLog, 'Registration log entry should exist');
        $this->assertEquals($ownerId, $registerLog['user_id']);
        $this->assertSame('user', $registerLog['target_entity']);
        $this->assertEquals($ownerId, $registerLog['target_id']);
        $this->assertNotEmpty($registerLog['created_at']);

        // Verify program creation log
        $programLog = $this->findLogByAction($allLogs, 'program.create');
        $this->assertNotNull($programLog, 'Program creation log entry should exist');
        $this->assertEquals($ownerId, $programLog['user_id']);
        $this->assertSame('program', $programLog['target_entity']);
        $this->assertEquals($programId, $programLog['target_id']);
        $this->assertSame('10.0.0.1', $programLog['ip_address']);

        // Verify reward policy log
        $policyLog = $this->findLogByAction($allLogs, 'reward_policy.create');
        $this->assertNotNull($policyLog, 'Reward policy creation log entry should exist');
        $this->assertEquals($ownerId, $policyLog['user_id']);
        $this->assertSame('reward_policy', $policyLog['target_entity']);
        $this->assertSame('10.0.0.2', $policyLog['ip_address']);

        // Verify publish log
        $publishLog = $this->findLogByAction($allLogs, 'program.publish');
        $this->assertNotNull($publishLog, 'Program publish log entry should exist');
        $this->assertEquals($ownerId, $publishLog['user_id']);
        $this->assertSame('program', $publishLog['target_entity']);
        $this->assertEquals($programId, $publishLog['target_id']);
        $this->assertSame('10.0.0.3', $publishLog['ip_address']);

        // Verify details JSON is stored correctly for program.create
        $details = json_decode($programLog['details'], true);
        $this->assertIsArray($details);
        $this->assertSame('API Security Audit', $details['title']);
        $this->assertSame('draft', $details['status']);

        // Verify details JSON for program.publish
        $publishDetails = json_decode($publishLog['details'], true);
        $this->assertIsArray($publishDetails);
        $this->assertSame('draft', $publishDetails['previous_status']);
        $this->assertSame('active', $publishDetails['new_status']);
    }

    /**
     * Helper: Find the first log entry with a given action.
     */
    private function findLogByAction(array $logs, string $action): ?array
    {
        foreach ($logs as $log) {
            if ($log['action'] === $action) {
                return $log;
            }
        }
        return null;
    }
}
