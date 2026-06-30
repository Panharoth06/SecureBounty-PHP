<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\TestDatabaseHelper;

require_once __DIR__ . '/../../model/repository/ActivityLogRepository.php';

/**
 * Unit tests for ActivityLogRepository.
 *
 * @covers ActivityLogRepository
 */
class ActivityLogRepositoryTest extends TestCase
{
    private static \mysqli $conn;
    private ActivityLogRepository $repository;
    private int $userId1;
    private int $userId2;

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
        $this->repository = new ActivityLogRepository(self::$conn);

        // Create test users
        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (1, 'Admin', 'User', 'admin@test.com', '\$2y\$10\$dummyhash', 'active')"
        );
        $this->userId1 = (int) self::$conn->insert_id;

        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (3, 'Researcher', 'One', 'researcher@test.com', '\$2y\$10\$dummyhash', 'active')"
        );
        $this->userId2 = (int) self::$conn->insert_id;
    }

    public static function tearDownAfterClass(): void
    {
        TestDatabaseHelper::cleanUp();
    }

    public function testGetAllReturnsPaginatedResultsInReverseChronologicalOrder(): void
    {
        // Insert logs with slight delay to ensure distinct timestamps
        $this->repository->create($this->userId1, 'user.register', 'user', 1, null, '127.0.0.1');
        sleep(1);
        $this->repository->create($this->userId1, 'program.create', 'program', 2, null, '127.0.0.1');
        sleep(1);
        $this->repository->create($this->userId2, 'report.submit', 'report', 3, null, '192.168.1.1');

        $results = $this->repository->getAll(10, 0);

        $this->assertCount(3, $results);
        // Reverse chronological: newest first
        $this->assertSame('report.submit', $results[0]['action']);
        $this->assertSame('program.create', $results[1]['action']);
        $this->assertSame('user.register', $results[2]['action']);
    }

    public function testGetAllIncludesUserName(): void
    {
        $this->repository->create($this->userId1, 'user.register', 'user', 1, null, '127.0.0.1');

        $results = $this->repository->getAll(10, 0);

        $this->assertCount(1, $results);
        $this->assertSame('Admin', $results[0]['first_name']);
        $this->assertSame('User', $results[0]['last_name']);
    }

    public function testGetAllPagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->repository->create($this->userId1, "action.{$i}", 'entity', $i, null, '127.0.0.1');
        }

        $page1 = $this->repository->getAll(2, 0);
        $page2 = $this->repository->getAll(2, 2);

        $this->assertCount(2, $page1);
        $this->assertCount(2, $page2);
        $this->assertNotEquals($page1[0]['id'], $page2[0]['id']);
    }

    public function testFilterByUserReturnsOnlyMatchingUser(): void
    {
        $this->repository->create($this->userId1, 'user.login', 'user', null, null, '127.0.0.1');
        $this->repository->create($this->userId2, 'report.submit', 'report', 1, null, '192.168.1.1');
        $this->repository->create($this->userId1, 'program.create', 'program', 2, null, '127.0.0.1');

        $results = $this->repository->filterByUser($this->userId1, 10, 0);

        $this->assertCount(2, $results);
        foreach ($results as $row) {
            $this->assertEquals($this->userId1, $row['user_id']);
        }
    }

    public function testFilterByActionReturnsOnlyMatchingAction(): void
    {
        $this->repository->create($this->userId1, 'user.register', 'user', 1, null, '127.0.0.1');
        $this->repository->create($this->userId2, 'user.register', 'user', 2, null, '192.168.1.1');
        $this->repository->create($this->userId1, 'program.create', 'program', 3, null, '127.0.0.1');

        $results = $this->repository->filterByAction('user.register', 10, 0);

        $this->assertCount(2, $results);
        foreach ($results as $row) {
            $this->assertSame('user.register', $row['action']);
        }
    }

    public function testFilterByDateRangeReturnsOnlyMatchingDates(): void
    {
        // Insert with explicit timestamps via raw SQL
        $stmt = self::$conn->prepare(
            "INSERT INTO activity_logs (user_id, action, target_entity, target_id, ip_address, created_at)
             VALUES (?, ?, ?, NULL, '127.0.0.1', ?)"
        );

        $dates = ['2024-01-10 10:00:00', '2024-02-15 12:00:00', '2024-03-20 14:00:00'];
        foreach ($dates as $i => $date) {
            $action = "action.{$i}";
            $entity = 'entity';
            $stmt->bind_param('isss', $this->userId1, $action, $entity, $date);
            $stmt->execute();
        }
        $stmt->close();

        $results = $this->repository->filterByDateRange('2024-02-01 00:00:00', '2024-02-28 23:59:59', 10, 0);

        $this->assertCount(1, $results);
        $this->assertSame('action.1', $results[0]['action']);
    }

    public function testGetFilteredWithMultipleFilters(): void
    {
        $this->repository->create($this->userId1, 'user.register', 'user', 1, null, '127.0.0.1');
        $this->repository->create($this->userId1, 'program.create', 'program', 2, null, '127.0.0.1');
        $this->repository->create($this->userId2, 'user.register', 'user', 3, null, '192.168.1.1');

        // Filter by user AND action
        $filters = ['user_id' => $this->userId1, 'action' => 'user.register'];
        $results = $this->repository->getFiltered($filters, 10, 0);

        $this->assertCount(1, $results);
        $this->assertEquals($this->userId1, $results[0]['user_id']);
        $this->assertSame('user.register', $results[0]['action']);
    }

    public function testGetFilteredWithNoFiltersReturnsAll(): void
    {
        $this->repository->create($this->userId1, 'action.one', 'entity', null, null, '127.0.0.1');
        $this->repository->create($this->userId2, 'action.two', 'entity', null, null, '192.168.1.1');

        $results = $this->repository->getFiltered([], 10, 0);

        $this->assertCount(2, $results);
    }

    public function testGetFilteredWithDateRange(): void
    {
        $stmt = self::$conn->prepare(
            "INSERT INTO activity_logs (user_id, action, target_entity, target_id, ip_address, created_at)
             VALUES (?, ?, ?, NULL, '127.0.0.1', ?)"
        );

        $dates = ['2024-01-10 10:00:00', '2024-02-15 12:00:00', '2024-03-20 14:00:00'];
        foreach ($dates as $i => $date) {
            $action = "action.{$i}";
            $entity = 'entity';
            $stmt->bind_param('isss', $this->userId1, $action, $entity, $date);
            $stmt->execute();
        }
        $stmt->close();

        $filters = ['start_date' => '2024-02-01 00:00:00', 'end_date' => '2024-03-31 23:59:59'];
        $results = $this->repository->getFiltered($filters, 10, 0);

        $this->assertCount(2, $results);
    }

    public function testGetTotalCountWithNoFilters(): void
    {
        $this->repository->create($this->userId1, 'action.one', 'entity', null, null, '127.0.0.1');
        $this->repository->create($this->userId2, 'action.two', 'entity', null, null, '192.168.1.1');
        $this->repository->create($this->userId1, 'action.three', 'entity', null, null, '127.0.0.1');

        $count = $this->repository->getTotalCount();

        $this->assertSame(3, $count);
    }

    public function testGetTotalCountWithFilters(): void
    {
        $this->repository->create($this->userId1, 'user.register', 'user', 1, null, '127.0.0.1');
        $this->repository->create($this->userId1, 'program.create', 'program', 2, null, '127.0.0.1');
        $this->repository->create($this->userId2, 'user.register', 'user', 3, null, '192.168.1.1');

        $countByUser = $this->repository->getTotalCount(['user_id' => $this->userId1]);
        $this->assertSame(2, $countByUser);

        $countByAction = $this->repository->getTotalCount(['action' => 'user.register']);
        $this->assertSame(2, $countByAction);

        $countCombined = $this->repository->getTotalCount(['user_id' => $this->userId1, 'action' => 'user.register']);
        $this->assertSame(1, $countCombined);
    }

    public function testGetAllReturnsEmptyForNoLogs(): void
    {
        $results = $this->repository->getAll(10, 0);
        $this->assertCount(0, $results);
    }

    public function testGetTotalCountReturnsZeroForNoLogs(): void
    {
        $count = $this->repository->getTotalCount();
        $this->assertSame(0, $count);
    }
}
