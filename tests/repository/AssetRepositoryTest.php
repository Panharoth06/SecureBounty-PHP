<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\TestDatabaseHelper;

require_once __DIR__ . '/../../model/repository/AssetRepository.php';

/**
 * Unit tests for AssetRepository.
 *
 * @covers AssetRepository
 */
class AssetRepositoryTest extends TestCase
{
    private static mysqli $conn;
    private AssetRepository $repository;
    private int $programId;

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
        $this->repository = new AssetRepository(self::$conn);

        // Create a program owner
        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (2, 'Test', 'Owner', 'owner@test.com', '\$2y\$10\$dummyhash', 'active')"
        );
        $ownerId = (int) self::$conn->insert_id;

        // Create a program
        self::$conn->query(
            "INSERT INTO programs (owner_id, title, description, scope, status)
             VALUES ({$ownerId}, 'Test Program', 'Desc', 'Scope', 'active')"
        );
        $this->programId = (int) self::$conn->insert_id;
    }

    public static function tearDownAfterClass(): void
    {
        TestDatabaseHelper::cleanUp();
    }

    public function testCreateReturnsAssetId(): void
    {
        $id = $this->repository->create($this->programId, 'example.com', 'Domain');

        $this->assertGreaterThan(0, $id);
    }

    public function testCreateStoresCorrectData(): void
    {
        $id = $this->repository->create($this->programId, 'api.example.com', 'Domain');

        $asset = $this->repository->findById($id);

        $this->assertNotNull($asset);
        $this->assertEquals($this->programId, $asset['program_id']);
        $this->assertSame('api.example.com', $asset['name']);
        $this->assertSame('Domain', $asset['type']);
        $this->assertNotEmpty($asset['created_at']);
        $this->assertNotEmpty($asset['updated_at']);
    }

    public function testFindByIdReturnsNullForNonExistentId(): void
    {
        $result = $this->repository->findById(99999);
        $this->assertNull($result);
    }

    public function testFindByProgramIdReturnsAllAssets(): void
    {
        $this->repository->create($this->programId, 'example.com', 'Domain');
        $this->repository->create($this->programId, '*.example.com', 'Wildcard');
        $this->repository->create($this->programId, 'MyApp', 'iOS App Store');

        $assets = $this->repository->findByProgramId($this->programId);

        $this->assertCount(3, $assets);
    }

    public function testFindByProgramIdReturnsEmptyForNoAssets(): void
    {
        $assets = $this->repository->findByProgramId(99999);
        $this->assertCount(0, $assets);
    }

    public function testFindByProgramIdOrdersByTypeAndName(): void
    {
        $this->repository->create($this->programId, 'z-wildcard.com', 'Wildcard');
        $this->repository->create($this->programId, 'a-domain.com', 'Domain');
        $this->repository->create($this->programId, 'b-domain.com', 'Domain');

        $assets = $this->repository->findByProgramId($this->programId);

        // Domain comes before Wildcard alphabetically
        $this->assertSame('Domain', $assets[0]['type']);
        $this->assertSame('a-domain.com', $assets[0]['name']);
        $this->assertSame('Domain', $assets[1]['type']);
        $this->assertSame('b-domain.com', $assets[1]['name']);
        $this->assertSame('Wildcard', $assets[2]['type']);
    }

    public function testUpdateModifiesNameAndType(): void
    {
        $id = $this->repository->create($this->programId, 'old-name.com', 'Domain');

        $affected = $this->repository->update($id, 'new-name.com', 'Wildcard');

        $this->assertEquals(1, $affected);

        $asset = $this->repository->findById($id);
        $this->assertSame('new-name.com', $asset['name']);
        $this->assertSame('Wildcard', $asset['type']);
    }

    public function testUpdateReturnsZeroForNonExistentId(): void
    {
        $affected = $this->repository->update(99999, 'name.com', 'Domain');
        $this->assertEquals(0, $affected);
    }

    public function testDeleteRemovesAsset(): void
    {
        $id = $this->repository->create($this->programId, 'to-delete.com', 'Domain');

        $affected = $this->repository->delete($id);

        $this->assertEquals(1, $affected);
        $this->assertNull($this->repository->findById($id));
    }

    public function testDeleteReturnsZeroForNonExistentId(): void
    {
        $affected = $this->repository->delete(99999);
        $this->assertEquals(0, $affected);
    }

    public function testCountByTypeForProgramReturnsGroupedCounts(): void
    {
        $this->repository->create($this->programId, 'a.com', 'Domain');
        $this->repository->create($this->programId, 'b.com', 'Domain');
        $this->repository->create($this->programId, '*.c.com', 'Wildcard');
        $this->repository->create($this->programId, 'MyApp', 'iOS App Store');

        $counts = $this->repository->countByTypeForProgram($this->programId);

        $this->assertSame(2, $counts['Domain']);
        $this->assertSame(1, $counts['Wildcard']);
        $this->assertSame(1, $counts['iOS App Store']);
        $this->assertArrayNotHasKey('Android Play Store', $counts);
        $this->assertArrayNotHasKey('Windows App', $counts);
        $this->assertArrayNotHasKey('Other', $counts);
    }

    public function testCountByTypeForProgramReturnsEmptyForNoAssets(): void
    {
        $counts = $this->repository->countByTypeForProgram(99999);
        $this->assertEmpty($counts);
    }

    public function testExistsByNameAndProgramReturnsTrueForDuplicate(): void
    {
        $this->repository->create($this->programId, 'existing.com', 'Domain');

        $exists = $this->repository->existsByNameAndProgram('existing.com', $this->programId);

        $this->assertTrue($exists);
    }

    public function testExistsByNameAndProgramReturnsFalseForNewName(): void
    {
        $exists = $this->repository->existsByNameAndProgram('new-name.com', $this->programId);

        $this->assertFalse($exists);
    }

    public function testExistsByNameAndProgramExcludesGivenId(): void
    {
        $id = $this->repository->create($this->programId, 'mysite.com', 'Domain');

        // Should not consider itself a duplicate
        $exists = $this->repository->existsByNameAndProgram('mysite.com', $this->programId, $id);

        $this->assertFalse($exists);
    }

    public function testExistsByNameAndProgramDetectsDuplicateExcludingDifferentId(): void
    {
        $this->repository->create($this->programId, 'taken.com', 'Domain');
        $otherId = $this->repository->create($this->programId, 'other.com', 'Domain');

        // 'taken.com' exists, and we're excluding otherId (not the one with 'taken.com')
        $exists = $this->repository->existsByNameAndProgram('taken.com', $this->programId, $otherId);

        $this->assertTrue($exists);
    }

    public function testExistsByNameAndProgramScopedToProgram(): void
    {
        $this->repository->create($this->programId, 'shared.com', 'Domain');

        // Create a second program
        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (2, 'Other', 'Owner', 'other-owner@test.com', '\$2y\$10\$dummyhash', 'active')"
        );
        $otherOwnerId = (int) self::$conn->insert_id;

        self::$conn->query(
            "INSERT INTO programs (owner_id, title, description, scope, status)
             VALUES ({$otherOwnerId}, 'Other Program', 'Desc', 'Scope', 'active')"
        );
        $otherProgramId = (int) self::$conn->insert_id;

        // Same name in a different program should not exist
        $exists = $this->repository->existsByNameAndProgram('shared.com', $otherProgramId);

        $this->assertFalse($exists);
    }
}
