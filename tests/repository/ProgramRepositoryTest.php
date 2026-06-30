<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\TestDatabaseHelper;

require_once __DIR__ . '/../../model/repository/ProgramRepository.php';

/**
 * Unit tests for ProgramRepository.
 *
 * @covers ProgramRepository
 */
class ProgramRepositoryTest extends TestCase
{
    private static mysqli $conn;
    private ProgramRepository $repository;
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
        $this->repository = new ProgramRepository(self::$conn);

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

    public function testCreateReturnsProgramIdWithDraftStatus(): void
    {
        $id = $this->repository->create($this->ownerId, 'Test Program', 'A description', 'example.com');

        $this->assertGreaterThan(0, $id);

        $program = $this->repository->findById($id);
        $this->assertNotNull($program);
        $this->assertSame('draft', $program['status']);
        $this->assertSame('Test Program', $program['title']);
        $this->assertSame('A description', $program['description']);
        $this->assertSame('example.com', $program['scope']);
        $this->assertEquals($this->ownerId, $program['owner_id']);
    }

    public function testFindByIdReturnsNullForNonExistentId(): void
    {
        $result = $this->repository->findById(99999);
        $this->assertNull($result);
    }

    public function testFindByOwnerIdReturnsOwnedPrograms(): void
    {
        $this->repository->create($this->ownerId, 'Program A', 'Desc A', 'Scope A');
        $this->repository->create($this->ownerId, 'Program B', 'Desc B', 'Scope B');

        $programs = $this->repository->findByOwnerId($this->ownerId);

        $this->assertCount(2, $programs);
    }

    public function testFindByOwnerIdReturnsEmptyForOtherOwner(): void
    {
        $this->repository->create($this->ownerId, 'Program A', 'Desc A', 'Scope A');

        $programs = $this->repository->findByOwnerId(99999);

        $this->assertCount(0, $programs);
    }

    public function testFindActiveReturnsOnlyActivePrograms(): void
    {
        $id1 = $this->repository->create($this->ownerId, 'Active Program', 'Desc', 'Scope');
        $id2 = $this->repository->create($this->ownerId, 'Draft Program', 'Desc', 'Scope');

        $this->repository->updateStatus($id1, 'active');
        // id2 remains 'draft'

        $active = $this->repository->findActive();

        $this->assertCount(1, $active);
        $this->assertEquals($id1, $active[0]['id']);
    }

    public function testUpdateModifiesTitleDescriptionScope(): void
    {
        $id = $this->repository->create($this->ownerId, 'Original', 'Original Desc', 'Original Scope');

        $this->repository->update($id, 'Updated', 'Updated Desc', 'Updated Scope');

        $program = $this->repository->findById($id);
        $this->assertSame('Updated', $program['title']);
        $this->assertSame('Updated Desc', $program['description']);
        $this->assertSame('Updated Scope', $program['scope']);
    }

    public function testUpdateStatusChangesStatus(): void
    {
        $id = $this->repository->create($this->ownerId, 'Program', 'Desc', 'Scope');

        $this->repository->updateStatus($id, 'active');
        $program = $this->repository->findById($id);
        $this->assertSame('active', $program['status']);

        $this->repository->updateStatus($id, 'closed');
        $program = $this->repository->findById($id);
        $this->assertSame('closed', $program['status']);
    }

    public function testGetAllReturnsAllPrograms(): void
    {
        $this->repository->create($this->ownerId, 'Program 1', 'Desc', 'Scope');
        $this->repository->create($this->ownerId, 'Program 2', 'Desc', 'Scope');
        $this->repository->create($this->ownerId, 'Program 3', 'Desc', 'Scope');

        $all = $this->repository->getAll();

        $this->assertCount(3, $all);
    }

    public function testGetAllWithStatusFilter(): void
    {
        $id1 = $this->repository->create($this->ownerId, 'Active', 'Desc', 'Scope');
        $id2 = $this->repository->create($this->ownerId, 'Draft', 'Desc', 'Scope');

        $this->repository->updateStatus($id1, 'active');

        $active = $this->repository->getAll('active');
        $draft = $this->repository->getAll('draft');

        $this->assertCount(1, $active);
        $this->assertCount(1, $draft);
        $this->assertEquals($id1, $active[0]['id']);
        $this->assertEquals($id2, $draft[0]['id']);
    }

    public function testGetAllWithPagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->repository->create($this->ownerId, "Program {$i}", 'Desc', 'Scope');
        }

        $page1 = $this->repository->getAll(null, 2, 0);
        $page2 = $this->repository->getAll(null, 2, 2);

        $this->assertCount(2, $page1);
        $this->assertCount(2, $page2);
        $this->assertNotEquals($page1[0]['id'], $page2[0]['id']);
    }
}
