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

    public function testFindActiveWithFiltersNoFiltersReturnsAllActive(): void
    {
        $id1 = $this->repository->create($this->ownerId, 'Active 1', 'Desc', 'Scope');
        $id2 = $this->repository->create($this->ownerId, 'Active 2', 'Desc', 'Scope');
        $id3 = $this->repository->create($this->ownerId, 'Draft', 'Desc', 'Scope');

        $this->repository->updateStatus($id1, 'active');
        $this->repository->updateStatus($id2, 'active');
        // id3 remains 'draft'

        $results = $this->repository->findActiveWithFilters([], 20, 0);

        $this->assertCount(2, $results);
    }

    public function testFindActiveWithFiltersAssetType(): void
    {
        $id1 = $this->repository->create($this->ownerId, 'Web Program', 'Desc', 'Scope');
        $id2 = $this->repository->create($this->ownerId, 'Mobile Program', 'Desc', 'Scope');
        $this->repository->updateStatus($id1, 'active');
        $this->repository->updateStatus($id2, 'active');

        // Add assets
        self::$conn->query("INSERT INTO program_assets (program_id, name, type) VALUES ({$id1}, 'example.com', 'Domain')");
        self::$conn->query("INSERT INTO program_assets (program_id, name, type) VALUES ({$id2}, 'com.app', 'Android Play Store')");

        $results = $this->repository->findActiveWithFilters(['asset_type' => ['Domain']], 20, 0);

        $this->assertCount(1, $results);
        $this->assertEquals($id1, $results[0]['id']);
    }

    public function testFindActiveWithFiltersTagIds(): void
    {
        $id1 = $this->repository->create($this->ownerId, 'PHP Program', 'Desc', 'Scope');
        $id2 = $this->repository->create($this->ownerId, 'Java Program', 'Desc', 'Scope');
        $this->repository->updateStatus($id1, 'active');
        $this->repository->updateStatus($id2, 'active');

        // Add tags
        self::$conn->query("INSERT INTO technology_tags (name, normalized_name) VALUES ('PHP', 'php')");
        $phpTagId = (int) self::$conn->insert_id;
        self::$conn->query("INSERT INTO technology_tags (name, normalized_name) VALUES ('Java', 'java')");
        $javaTagId = (int) self::$conn->insert_id;

        self::$conn->query("INSERT INTO program_tags (program_id, tag_id) VALUES ({$id1}, {$phpTagId})");
        self::$conn->query("INSERT INTO program_tags (program_id, tag_id) VALUES ({$id2}, {$javaTagId})");

        $results = $this->repository->findActiveWithFilters(['tag' => [$phpTagId]], 20, 0);

        $this->assertCount(1, $results);
        $this->assertEquals($id1, $results[0]['id']);
    }

    public function testFindActiveWithFiltersBountyRange(): void
    {
        $id1 = $this->repository->create($this->ownerId, 'High Bounty', 'Desc', 'Scope');
        $id2 = $this->repository->create($this->ownerId, 'Low Bounty', 'Desc', 'Scope');
        $this->repository->updateStatus($id1, 'active');
        $this->repository->updateStatus($id2, 'active');

        // Add reward policies
        self::$conn->query("INSERT INTO reward_policies (program_id, severity, min_reward, max_reward) VALUES ({$id1}, 'critical', 5000, 10000)");
        self::$conn->query("INSERT INTO reward_policies (program_id, severity, min_reward, max_reward) VALUES ({$id2}, 'critical', 100, 500)");

        // Filter: bounty_min = 1000 (only program with max_reward >= 1000)
        $results = $this->repository->findActiveWithFilters(['bounty_min' => 1000], 20, 0);
        $this->assertCount(1, $results);
        $this->assertEquals($id1, $results[0]['id']);

        // Filter: bounty_max = 600 (only program with max_reward <= 600)
        $results = $this->repository->findActiveWithFilters(['bounty_max' => 600], 20, 0);
        $this->assertCount(1, $results);
        $this->assertEquals($id2, $results[0]['id']);
    }

    public function testFindActiveWithFiltersCombinedAndLogic(): void
    {
        $id1 = $this->repository->create($this->ownerId, 'Full Program', 'Desc', 'Scope');
        $id2 = $this->repository->create($this->ownerId, 'Partial Program', 'Desc', 'Scope');
        $this->repository->updateStatus($id1, 'active');
        $this->repository->updateStatus($id2, 'active');

        // id1 has both Domain asset and a high bounty
        self::$conn->query("INSERT INTO program_assets (program_id, name, type) VALUES ({$id1}, 'example.com', 'Domain')");
        self::$conn->query("INSERT INTO reward_policies (program_id, severity, min_reward, max_reward) VALUES ({$id1}, 'critical', 5000, 10000)");

        // id2 has Domain asset but low bounty
        self::$conn->query("INSERT INTO program_assets (program_id, name, type) VALUES ({$id2}, 'test.com', 'Domain')");
        self::$conn->query("INSERT INTO reward_policies (program_id, severity, min_reward, max_reward) VALUES ({$id2}, 'low', 50, 200)");

        // Combined filter: Domain + bounty_min 1000 (AND logic)
        $results = $this->repository->findActiveWithFilters([
            'asset_type' => ['Domain'],
            'bounty_min' => 1000,
        ], 20, 0);

        $this->assertCount(1, $results);
        $this->assertEquals($id1, $results[0]['id']);
    }

    public function testCountActiveWithFiltersNoFilters(): void
    {
        $id1 = $this->repository->create($this->ownerId, 'Active 1', 'Desc', 'Scope');
        $id2 = $this->repository->create($this->ownerId, 'Active 2', 'Desc', 'Scope');
        $this->repository->create($this->ownerId, 'Draft', 'Desc', 'Scope');

        $this->repository->updateStatus($id1, 'active');
        $this->repository->updateStatus($id2, 'active');

        $count = $this->repository->countActiveWithFilters([]);

        $this->assertSame(2, $count);
    }

    public function testCountActiveWithFiltersAssetType(): void
    {
        $id1 = $this->repository->create($this->ownerId, 'Web Program', 'Desc', 'Scope');
        $id2 = $this->repository->create($this->ownerId, 'Mobile Program', 'Desc', 'Scope');
        $this->repository->updateStatus($id1, 'active');
        $this->repository->updateStatus($id2, 'active');

        self::$conn->query("INSERT INTO program_assets (program_id, name, type) VALUES ({$id1}, 'example.com', 'Domain')");
        self::$conn->query("INSERT INTO program_assets (program_id, name, type) VALUES ({$id2}, 'com.app', 'Android Play Store')");

        $count = $this->repository->countActiveWithFilters(['asset_type' => ['Domain']]);

        $this->assertSame(1, $count);
    }

    public function testUpdateLogoPathSetsPath(): void
    {
        $id = $this->repository->create($this->ownerId, 'Program', 'Desc', 'Scope');

        $affected = $this->repository->updateLogoPath($id, 'uploads/logos/program_1.png');

        $this->assertSame(1, $affected);

        $program = $this->repository->findById($id);
        $this->assertSame('uploads/logos/program_1.png', $program['logo_path']);
    }

    public function testUpdateLogoPathClearsWithNull(): void
    {
        $id = $this->repository->create($this->ownerId, 'Program', 'Desc', 'Scope');

        $this->repository->updateLogoPath($id, 'uploads/logos/program_1.png');
        $this->repository->updateLogoPath($id, null);

        $program = $this->repository->findById($id);
        $this->assertNull($program['logo_path']);
    }

    public function testFindActiveWithFiltersPagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $id = $this->repository->create($this->ownerId, "Active {$i}", 'Desc', 'Scope');
            $this->repository->updateStatus($id, 'active');
        }

        $page1 = $this->repository->findActiveWithFilters([], 2, 0);
        $page2 = $this->repository->findActiveWithFilters([], 2, 2);

        $this->assertCount(2, $page1);
        $this->assertCount(2, $page2);
        $this->assertNotEquals($page1[0]['id'], $page2[0]['id']);
    }
}
