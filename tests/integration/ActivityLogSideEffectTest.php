<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\TestDatabaseHelper;

require_once __DIR__ . '/../../model/repository/UserRepository.php';
require_once __DIR__ . '/../../model/repository/ProgramRepository.php';
require_once __DIR__ . '/../../model/repository/ReportRepository.php';
require_once __DIR__ . '/../../model/repository/RewardPolicyRepository.php';
require_once __DIR__ . '/../../model/repository/ActivityLogRepository.php';
require_once __DIR__ . '/../../model/repository/NotificationRepository.php';
require_once __DIR__ . '/../../model/repository/UserProgramRepository.php';
require_once __DIR__ . '/../../model/services/AuthService.php';
require_once __DIR__ . '/../../model/services/ProgramService.php';
require_once __DIR__ . '/../../model/services/ReportService.php';
require_once __DIR__ . '/../../model/services/ActivityLogService.php';
require_once __DIR__ . '/../../model/services/NotificationService.php';

/**
 * Integration test verifying that activity log entries are created as a
 * side-effect of service operations.
 *
 * Each state-changing service call (register, program create, report submit,
 * status change) must append exactly the expected row to `activity_logs`,
 * carrying the correct user_id, action, target_entity, target_id, ip_address
 * and JSON details.
 *
 * @covers AuthService
 * @covers ProgramService
 * @covers ReportService
 * @covers ActivityLogService
 *
 * Validates: Requirements 1.1, 4.1, 7.1, 8.2, 12.1
 */
class ActivityLogSideEffectTest extends TestCase
{
    private const ROLE_PROGRAM_OWNER = 2;
    private const ROLE_RESEARCHER = 3;

    private static mysqli $conn;

    private UserRepository $userRepo;
    private ProgramRepository $programRepo;
    private ReportRepository $reportRepo;
    private RewardPolicyRepository $rewardPolicyRepo;
    private ActivityLogRepository $activityLogRepo;
    private NotificationRepository $notificationRepo;
    private UserProgramRepository $userProgramRepo;

    private AuthService $authService;
    private ProgramService $programService;
    private ReportService $reportService;
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
     * Count the rows currently in the activity_logs table.
     */
    private function logCount(): int
    {
        $row = self::$conn->query('SELECT COUNT(*) AS c FROM activity_logs')->fetch_assoc();
        return (int) $row['c'];
    }

    /**
     * Fetch the most recent activity log row matching a given action.
     */
    private function latestLog(string $action): ?array
    {
        $logs = $this->activityLogRepo->filterByAction($action, 1, 0);
        return $logs[0] ?? null;
    }

    /**
     * register() appends a single 'user.register' entry targeting the new user.
     *
     * Validates: Requirements 1.1, 12.1
     */
    public function testRegisterCreatesActivityLog(): void
    {
        $before = $this->logCount();

        $result = $this->authService->register(
            'Faye',
            'Owner',
            'faye@example.com',
            'FayePass123',
            self::ROLE_PROGRAM_OWNER
        );
        $userId = (int) $result['user_id'];

        $this->assertSame($before + 1, $this->logCount(), 'register() should create exactly one log entry');

        $log = $this->latestLog('user.register');
        $this->assertNotNull($log);
        $this->assertEquals($userId, (int) $log['user_id']);
        $this->assertSame('user', $log['target_entity']);
        $this->assertEquals($userId, (int) $log['target_id']);
        $details = json_decode($log['details'], true);
        $this->assertSame(self::ROLE_PROGRAM_OWNER, (int) $details['role_id']);
    }

    /**
     * createProgram() appends a single 'program.create' entry targeting the program.
     *
     * Validates: Requirements 4.1, 12.1
     */
    public function testCreateProgramCreatesActivityLog(): void
    {
        $ownerId = $this->userRepo->create(
            self::ROLE_PROGRAM_OWNER,
            'Gina',
            'Owner',
            'gina@example.com',
            $this->authService->hashPassword('GinaPass123')
        );

        $before = $this->logCount();

        $programId = $this->programService->createProgram(
            $ownerId,
            'Cloud Infra Bounty',
            'Report misconfigurations in our cloud infrastructure.',
            'cloud.example.com',
            '10.0.0.1'
        );

        $this->assertSame($before + 1, $this->logCount(), 'createProgram() should create exactly one log entry');

        $log = $this->latestLog('program.create');
        $this->assertNotNull($log);
        $this->assertEquals($ownerId, (int) $log['user_id']);
        $this->assertSame('program', $log['target_entity']);
        $this->assertEquals($programId, (int) $log['target_id']);
        $this->assertSame('10.0.0.1', $log['ip_address']);
        $details = json_decode($log['details'], true);
        $this->assertSame('Cloud Infra Bounty', $details['title']);
        $this->assertSame('draft', $details['status']);
    }

    /**
     * submitReport() appends a single 'report.submit' entry targeting the report.
     *
     * Validates: Requirements 7.1, 12.1
     */
    public function testSubmitReportCreatesActivityLog(): void
    {
        [$ownerId, $researcherId, $programId] = $this->seedActiveEnrolledProgram();

        $before = $this->logCount();

        $reportId = $this->reportService->submitReport(
            $researcherId,
            $programId,
            'IDOR on profile endpoint',
            'User profiles are accessible by manipulating the id parameter.',
            "1. GET /profile?id=2\n2. Observe another user's data",
            'Unauthorized data disclosure.',
            '10.0.0.2'
        );

        $this->assertSame($before + 1, $this->logCount(), 'submitReport() should create exactly one log entry');

        $log = $this->latestLog('report.submit');
        $this->assertNotNull($log);
        $this->assertEquals($researcherId, (int) $log['user_id']);
        $this->assertSame('report', $log['target_entity']);
        $this->assertEquals($reportId, (int) $log['target_id']);
        $this->assertSame('10.0.0.2', $log['ip_address']);
        $details = json_decode($log['details'], true);
        $this->assertEquals($programId, (int) $details['program_id']);
        $this->assertSame('IDOR on profile endpoint', $details['title']);
    }

    /**
     * changeStatus() appends a single 'report.status_change' entry with status transition.
     *
     * Validates: Requirements 8.2, 12.1
     */
    public function testChangeStatusCreatesActivityLog(): void
    {
        [$ownerId, $researcherId, $programId] = $this->seedActiveEnrolledProgram();
        $reportId = $this->reportService->submitReport(
            $researcherId,
            $programId,
            'CSRF on settings form',
            'The settings form lacks CSRF protection.',
            "1. Craft a forged POST\n2. Submit while victim is authenticated",
            'Account takeover via settings change.',
            '10.0.0.3'
        );

        $before = $this->logCount();

        $this->reportService->changeStatus($reportId, 'triaged', $ownerId, '10.0.0.4');

        $this->assertSame($before + 1, $this->logCount(), 'changeStatus() should create exactly one log entry');

        $log = $this->latestLog('report.status_change');
        $this->assertNotNull($log);
        $this->assertEquals($ownerId, (int) $log['user_id']);
        $this->assertSame('report', $log['target_entity']);
        $this->assertEquals($reportId, (int) $log['target_id']);
        $this->assertSame('10.0.0.4', $log['ip_address']);
        $details = json_decode($log['details'], true);
        $this->assertSame('pending', $details['previous_status']);
        $this->assertSame('triaged', $details['new_status']);
    }

    /**
     * Seed an owner, researcher, active program (with reward policy) and enrollment.
     *
     * @return array{0:int,1:int,2:int} [ownerId, researcherId, programId]
     */
    private function seedActiveEnrolledProgram(): array
    {
        $ownerId = $this->userRepo->create(
            self::ROLE_PROGRAM_OWNER,
            'Owen',
            'Owner',
            'owen' . uniqid() . '@example.com',
            $this->authService->hashPassword('OwenPass123')
        );
        $researcherId = $this->userRepo->create(
            self::ROLE_RESEARCHER,
            'Rita',
            'Researcher',
            'rita' . uniqid() . '@example.com',
            $this->authService->hashPassword('RitaPass123')
        );

        $programId = $this->programService->createProgram(
            $ownerId,
            'API Bounty',
            'Test our public API surface.',
            'api.example.com',
            '10.0.0.1'
        );
        $this->rewardPolicyRepo->create($programId, 'high', 500.00, 5000.00);
        $this->programRepo->updateStatus($programId, 'active');
        $this->userProgramRepo->enroll($researcherId, $programId);

        return [$ownerId, $researcherId, $programId];
    }
}
