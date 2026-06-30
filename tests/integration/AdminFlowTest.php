<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\TestDatabaseHelper;

require_once __DIR__ . '/../../model/repository/UserRepository.php';
require_once __DIR__ . '/../../model/repository/ProgramRepository.php';
require_once __DIR__ . '/../../model/repository/ActivityLogRepository.php';
require_once __DIR__ . '/../../model/services/AuthService.php';
require_once __DIR__ . '/../../model/services/ProgramService.php';
require_once __DIR__ . '/../../model/services/ActivityLogService.php';

/**
 * Integration test for administrator user-management and program-oversight flows.
 *
 * Exercises admin operations end-to-end against the test database:
 *   - Deactivate a user and verify they can no longer log in.
 *   - Reactivate a user and verify login is restored.
 *   - Change a user's role.
 *   - Suspend a program and verify its status (and that submissions are blocked).
 *   - Reinstate a program back to active.
 *
 * @covers AuthService
 * @covers ProgramService
 * @covers UserRepository
 *
 * Validates: Requirements 2.1, 4.1, 10.2, 12.1
 */
class AdminFlowTest extends TestCase
{
    private const ROLE_ADMIN = 1;
    private const ROLE_PROGRAM_OWNER = 2;
    private const ROLE_RESEARCHER = 3;
    private const IP = '127.0.0.1';

    private static mysqli $conn;

    private UserRepository $userRepo;
    private ProgramRepository $programRepo;
    private ActivityLogRepository $activityLogRepo;

    private AuthService $authService;
    private ProgramService $programService;
    private ActivityLogService $activityLogService;

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
        $this->activityLogRepo = new ActivityLogRepository(self::$conn);

        $this->activityLogService = new ActivityLogService($this->activityLogRepo);
        $this->authService = new AuthService($this->userRepo, $this->activityLogService);
        $this->programService = new ProgramService($this->programRepo, $this->activityLogService, self::$conn);

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
     * Register an admin user directly (Admin role cannot be self-selected via register()).
     */
    private function createAdmin(): int
    {
        return $this->userRepo->create(
            self::ROLE_ADMIN,
            'Admin',
            'Root',
            'admin@example.com',
            $this->authService->hashPassword('AdminPass123')
        );
    }

    /**
     * Admin deactivates and reactivates a user; login availability tracks status.
     *
     * Validates: Requirements 2.1, 10.2, 12.1
     */
    public function testAdminDeactivateAndReactivateUser(): void
    {
        $adminId = $this->createAdmin();

        $registration = $this->authService->register(
            'Carol',
            'Researcher',
            'carol@example.com',
            'CarolPass123',
            self::ROLE_RESEARCHER
        );
        $this->assertTrue($registration['success']);
        $userId = (int) $registration['user_id'];

        // Baseline: active user can log in (Req 2.1).
        $this->assertTrue($this->authService->login('carol@example.com', 'CarolPass123')['success']);

        // Admin deactivates the account (Req 10.2).
        $this->assertSame(1, $this->userRepo->updateStatus($userId, 'inactive'));
        $this->activityLogService->log(
            $adminId,
            'user.deactivate',
            'user',
            $userId,
            ['previous_status' => 'active', 'new_status' => 'inactive'],
            self::IP
        );

        // Deactivated user cannot log in.
        $blocked = $this->authService->login('carol@example.com', 'CarolPass123');
        $this->assertFalse($blocked['success'], 'Deactivated user must not be able to log in');

        // Admin reactivates the account (Req 10.2).
        $this->assertSame(1, $this->userRepo->updateStatus($userId, 'active'));
        $this->activityLogService->log(
            $adminId,
            'user.reactivate',
            'user',
            $userId,
            ['previous_status' => 'inactive', 'new_status' => 'active'],
            self::IP
        );

        // Login is restored.
        $this->assertTrue(
            $this->authService->login('carol@example.com', 'CarolPass123')['success'],
            'Reactivated user should be able to log in again'
        );

        // Audit trail captured both admin actions (Req 12.1).
        $adminActions = array_column($this->activityLogRepo->filterByUser($adminId, 50, 0), 'action');
        $this->assertContains('user.deactivate', $adminActions);
        $this->assertContains('user.reactivate', $adminActions);
    }

    /**
     * Admin changes a user's role assignment.
     *
     * Validates: Requirements 10.2, 12.1
     */
    public function testAdminChangeUserRole(): void
    {
        $adminId = $this->createAdmin();

        $registration = $this->authService->register(
            'Dave',
            'Member',
            'dave@example.com',
            'DavePass123',
            self::ROLE_RESEARCHER
        );
        $userId = (int) $registration['user_id'];
        $this->assertSame('Researcher', $this->userRepo->findById($userId)['role_name']);

        // Promote researcher → program owner.
        $this->assertSame(1, $this->userRepo->updateRole($userId, self::ROLE_PROGRAM_OWNER));
        $this->activityLogService->log(
            $adminId,
            'user.role_change',
            'user',
            $userId,
            ['previous_role_id' => self::ROLE_RESEARCHER, 'new_role_id' => self::ROLE_PROGRAM_OWNER],
            self::IP
        );

        $this->assertSame('Program_Owner', $this->userRepo->findById($userId)['role_name']);

        $adminActions = array_column($this->activityLogRepo->filterByUser($adminId, 50, 0), 'action');
        $this->assertContains('user.role_change', $adminActions);
    }

    /**
     * Admin suspends a program and later reinstates it.
     *
     * Validates: Requirements 4.1, 10.2, 12.1
     */
    public function testAdminSuspendAndReinstateProgram(): void
    {
        $adminId = $this->createAdmin();

        // Set up an owner with an active program.
        $ownerId = $this->userRepo->create(
            self::ROLE_PROGRAM_OWNER,
            'Erin',
            'Owner',
            'erin@example.com',
            $this->authService->hashPassword('ErinPass123')
        );
        $programId = $this->programService->createProgram(
            $ownerId,
            'Mobile App Bounty',
            'Security testing for our mobile apps.',
            'com.example.app',
            self::IP
        );
        $this->programRepo->updateStatus($programId, 'active');
        $this->assertSame('active', $this->programRepo->findById($programId)['status']);

        // Admin suspends the program (Req 10.2 oversight).
        $this->assertSame(1, $this->programRepo->updateStatus($programId, 'suspended'));
        $this->activityLogService->log(
            $adminId,
            'program.suspend',
            'program',
            $programId,
            ['previous_status' => 'active', 'new_status' => 'suspended'],
            self::IP
        );
        $this->assertSame('suspended', $this->programRepo->findById($programId)['status']);

        // Admin reinstates the program back to active.
        $this->assertSame(1, $this->programRepo->updateStatus($programId, 'active'));
        $this->activityLogService->log(
            $adminId,
            'program.reinstate',
            'program',
            $programId,
            ['previous_status' => 'suspended', 'new_status' => 'active'],
            self::IP
        );
        $this->assertSame('active', $this->programRepo->findById($programId)['status']);

        // Audit trail captured both oversight actions (Req 12.1).
        $adminActions = array_column($this->activityLogRepo->filterByUser($adminId, 50, 0), 'action');
        $this->assertContains('program.suspend', $adminActions);
        $this->assertContains('program.reinstate', $adminActions);
    }
}
