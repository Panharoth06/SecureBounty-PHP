<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\TestDatabaseHelper;

require_once __DIR__ . '/../../model/repository/ProgramRepository.php';
require_once __DIR__ . '/../../model/repository/ActivityLogRepository.php';
require_once __DIR__ . '/../../model/services/ProgramService.php';
require_once __DIR__ . '/../../model/services/ActivityLogService.php';

/**
 * Unit tests for ProgramService.
 *
 * @covers ProgramService
 */
class ProgramServiceTest extends TestCase
{
    private static mysqli $conn;
    private ProgramService $service;
    private ProgramRepository $programRepo;
    private ActivityLogService $activityLogService;
    private int $ownerId;

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

        $this->programRepo = new ProgramRepository(self::$conn);
        $activityLogRepo = new ActivityLogRepository(self::$conn);
        $this->activityLogService = new ActivityLogService($activityLogRepo);

        $this->service = new ProgramService(
            $this->programRepo,
            $this->activityLogService,
            self::$conn
        );

        // Create a test user (Program_Owner role = 2)
        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (2, 'Test', 'Owner', 'owner@test.com', '\$2y\$10\$dummyhash', 'active')"
        );
        $this->ownerId = (int) self::$conn->insert_id;
    }

    public static function tearDownAfterClass(): void
    {
        TestDatabaseHelper::cleanUp();
    }

    public function testCreateProgramReturnsProgramIdWithDraftStatus(): void
    {
        $id = $this->service->createProgram(
            $this->ownerId,
            'New Program',
            'Description here',
            'example.com/*',
            '127.0.0.1'
        );

        $this->assertGreaterThan(0, $id);

        $program = $this->programRepo->findById($id);
        $this->assertSame('draft', $program['status']);
        $this->assertSame('New Program', $program['title']);
    }

    public function testCreateProgramLogsActivity(): void
    {
        $this->service->createProgram(
            $this->ownerId,
            'Logged Program',
            'Description',
            'scope.com',
            '192.168.1.1'
        );

        $result = self::$conn->query(
            "SELECT * FROM activity_logs WHERE action = 'program.create' ORDER BY id DESC LIMIT 1"
        );
        $log = $result->fetch_assoc();

        $this->assertNotNull($log);
        $this->assertEquals($this->ownerId, $log['user_id']);
        $this->assertSame('program', $log['target_entity']);
        $this->assertSame('192.168.1.1', $log['ip_address']);
    }

    public function testCreateProgramThrowsOnEmptyTitle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Program title is required.');

        $this->service->createProgram($this->ownerId, '', 'Description', 'Scope');
    }

    public function testCreateProgramThrowsOnEmptyDescription(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Program description is required.');

        $this->service->createProgram($this->ownerId, 'Title', '', 'Scope');
    }

    public function testCreateProgramThrowsOnEmptyScope(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Program scope is required.');

        $this->service->createProgram($this->ownerId, 'Title', 'Description', '');
    }

    public function testCreateProgramThrowsOnWhitespaceOnlyFields(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->createProgram($this->ownerId, '   ', 'Description', 'Scope');
    }

    public function testUpdateProgramUpdatesFields(): void
    {
        $id = $this->service->createProgram($this->ownerId, 'Original', 'Original Desc', 'Original Scope');

        $this->service->updateProgram($id, $this->ownerId, 'Updated', 'New Desc', 'New Scope', '10.0.0.1');

        $program = $this->programRepo->findById($id);
        $this->assertSame('Updated', $program['title']);
        $this->assertSame('New Desc', $program['description']);
        $this->assertSame('New Scope', $program['scope']);
    }

    public function testUpdateProgramLogsActivity(): void
    {
        $id = $this->service->createProgram($this->ownerId, 'Program', 'Desc', 'Scope');

        $this->service->updateProgram($id, $this->ownerId, 'Updated Title', 'New Desc', 'New Scope', '10.0.0.1');

        $result = self::$conn->query(
            "SELECT * FROM activity_logs WHERE action = 'program.update' ORDER BY id DESC LIMIT 1"
        );
        $log = $result->fetch_assoc();

        $this->assertNotNull($log);
        $this->assertSame('program', $log['target_entity']);
    }

    public function testUpdateProgramThrowsOnEmptyFields(): void
    {
        $id = $this->service->createProgram($this->ownerId, 'Program', 'Desc', 'Scope');

        $this->expectException(InvalidArgumentException::class);
        $this->service->updateProgram($id, $this->ownerId, '', 'Desc', 'Scope');
    }

    public function testPublishProgramChangesStatusToActive(): void
    {
        $id = $this->service->createProgram($this->ownerId, 'Program', 'Description', 'Scope');

        // Add a reward policy so publish preconditions are met
        self::$conn->query(
            "INSERT INTO reward_policies (program_id, severity, min_reward, max_reward)
             VALUES ({$id}, 'high', 500.00, 5000.00)"
        );

        $result = $this->service->publishProgram($id, $this->ownerId, '127.0.0.1');

        $this->assertTrue($result);

        $program = $this->programRepo->findById($id);
        $this->assertSame('active', $program['status']);
    }

    public function testPublishProgramLogsActivity(): void
    {
        $id = $this->service->createProgram($this->ownerId, 'Program', 'Description', 'Scope');

        self::$conn->query(
            "INSERT INTO reward_policies (program_id, severity, min_reward, max_reward)
             VALUES ({$id}, 'medium', 100.00, 1000.00)"
        );

        $this->service->publishProgram($id, $this->ownerId, '127.0.0.1');

        $result = self::$conn->query(
            "SELECT * FROM activity_logs WHERE action = 'program.publish' ORDER BY id DESC LIMIT 1"
        );
        $log = $result->fetch_assoc();

        $this->assertNotNull($log);
        $this->assertSame('program', $log['target_entity']);
    }

    public function testPublishProgramFailsWithoutRewardPolicy(): void
    {
        $id = $this->service->createProgram($this->ownerId, 'Program', 'Description', 'Scope');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one reward policy is required before publishing.');

        $this->service->publishProgram($id, $this->ownerId);
    }

    public function testPublishProgramFailsWithEmptyTitle(): void
    {
        // Insert directly with empty title to bypass service validation
        self::$conn->query(
            "INSERT INTO programs (owner_id, title, description, scope, status)
             VALUES ({$this->ownerId}, '', 'Desc', 'Scope', 'draft')"
        );
        $id = (int) self::$conn->insert_id;

        self::$conn->query(
            "INSERT INTO reward_policies (program_id, severity, min_reward, max_reward)
             VALUES ({$id}, 'low', 50.00, 500.00)"
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Program title is required.');

        $this->service->publishProgram($id, $this->ownerId);
    }

    public function testPublishProgramThrowsForNonExistentProgram(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Program not found.');

        $this->service->publishProgram(99999, $this->ownerId);
    }

    public function testCloseProgramChangesStatusToClosed(): void
    {
        $id = $this->service->createProgram($this->ownerId, 'Program', 'Desc', 'Scope');
        $this->programRepo->updateStatus($id, 'active');

        $result = $this->service->closeProgram($id, $this->ownerId, '127.0.0.1');

        $this->assertTrue($result);

        $program = $this->programRepo->findById($id);
        $this->assertSame('closed', $program['status']);
    }

    public function testCloseProgramLogsActivity(): void
    {
        $id = $this->service->createProgram($this->ownerId, 'Program', 'Desc', 'Scope');
        $this->programRepo->updateStatus($id, 'active');

        $this->service->closeProgram($id, $this->ownerId, '127.0.0.1');

        $result = self::$conn->query(
            "SELECT * FROM activity_logs WHERE action = 'program.close' ORDER BY id DESC LIMIT 1"
        );
        $log = $result->fetch_assoc();

        $this->assertNotNull($log);
        $this->assertSame('program', $log['target_entity']);
        $this->assertEquals($id, $log['target_id']);
    }

    public function testCloseProgramThrowsForNonExistentProgram(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Program not found.');

        $this->service->closeProgram(99999, $this->ownerId);
    }

    public function testValidatePublishPreconditionsReturnsEmptyOnValid(): void
    {
        $id = $this->service->createProgram($this->ownerId, 'Title', 'Desc', 'Scope');

        self::$conn->query(
            "INSERT INTO reward_policies (program_id, severity, min_reward, max_reward)
             VALUES ({$id}, 'critical', 1000.00, 10000.00)"
        );

        $program = $this->programRepo->findById($id);
        $errors = $this->service->validatePublishPreconditions($program);

        $this->assertEmpty($errors);
    }

    public function testValidatePublishPreconditionsReturnsErrorsOnInvalid(): void
    {
        // Insert a program with empty fields directly
        self::$conn->query(
            "INSERT INTO programs (owner_id, title, description, scope, status)
             VALUES ({$this->ownerId}, '', '', '', 'draft')"
        );
        $id = (int) self::$conn->insert_id;

        $program = $this->programRepo->findById($id);
        $errors = $this->service->validatePublishPreconditions($program);

        $this->assertNotEmpty($errors);
        $this->assertGreaterThanOrEqual(3, count($errors)); // title, desc, scope, reward policy
    }
}
