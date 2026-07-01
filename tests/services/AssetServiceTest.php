<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\TestDatabaseHelper;

require_once __DIR__ . '/../../model/repository/AssetRepository.php';
require_once __DIR__ . '/../../model/repository/ProgramRepository.php';
require_once __DIR__ . '/../../model/services/AssetService.php';

/**
 * Unit tests for AssetService.
 *
 * @covers AssetService
 */
class AssetServiceTest extends TestCase
{
    private static mysqli $conn;
    private AssetService $service;
    private AssetRepository $assetRepo;
    private ProgramRepository $programRepo;
    private int $ownerId;
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

        $this->assetRepo = new AssetRepository(self::$conn);
        $this->programRepo = new ProgramRepository(self::$conn);

        $this->service = new AssetService($this->assetRepo, $this->programRepo);

        // Create a test user (Program_Owner role = 2)
        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (2, 'Test', 'Owner', 'owner@test.com', '\$2y\$10\$dummyhash', 'active')"
        );
        $this->ownerId = (int) self::$conn->insert_id;

        // Create a test program
        $this->programId = $this->programRepo->create($this->ownerId, 'Test Program', 'Description', 'Scope');
    }

    public static function tearDownAfterClass(): void
    {
        TestDatabaseHelper::cleanUp();
    }

    // ─── addAsset() ─────────────────────────────────────────────────────

    public function testAddAssetReturnsNewId(): void
    {
        $id = $this->service->addAsset($this->programId, $this->ownerId, 'example.com', 'Domain');

        $this->assertGreaterThan(0, $id);
    }

    public function testAddAssetStoresCorrectData(): void
    {
        $id = $this->service->addAsset($this->programId, $this->ownerId, 'example.com', 'Domain');

        $asset = $this->assetRepo->findById($id);
        $this->assertSame('example.com', $asset['name']);
        $this->assertSame('Domain', $asset['type']);
        $this->assertEquals($this->programId, $asset['program_id']);
    }

    public function testAddAssetTrimsName(): void
    {
        $id = $this->service->addAsset($this->programId, $this->ownerId, '  example.com  ', 'Domain');

        $asset = $this->assetRepo->findById($id);
        $this->assertSame('example.com', $asset['name']);
    }

    public function testAddAssetThrowsOnEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Asset name is required');

        $this->service->addAsset($this->programId, $this->ownerId, '', 'Domain');
    }

    public function testAddAssetThrowsOnWhitespaceOnlyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Asset name is required');

        $this->service->addAsset($this->programId, $this->ownerId, '   ', 'Domain');
    }

    public function testAddAssetThrowsOnNameExceeding255Chars(): void
    {
        $longName = str_repeat('a', 256);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must not exceed 255 characters');

        $this->service->addAsset($this->programId, $this->ownerId, $longName, 'Domain');
    }

    public function testAddAssetThrowsOnInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid asset type');

        $this->service->addAsset($this->programId, $this->ownerId, 'example.com', 'InvalidType');
    }

    public function testAddAssetThrowsOnDuplicateName(): void
    {
        $this->service->addAsset($this->programId, $this->ownerId, 'example.com', 'Domain');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already exists');

        $this->service->addAsset($this->programId, $this->ownerId, 'example.com', 'Wildcard');
    }

    public function testAddAssetThrowsOnNonOwner(): void
    {
        // Create another user
        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (2, 'Other', 'User', 'other@test.com', '\$2y\$10\$dummyhash', 'active')"
        );
        $otherUserId = (int) self::$conn->insert_id;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Access denied');

        $this->service->addAsset($this->programId, $otherUserId, 'example.com', 'Domain');
    }

    public function testAddAssetThrowsOnNonExistentProgram(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Program not found');

        $this->service->addAsset(99999, $this->ownerId, 'example.com', 'Domain');
    }

    public function testAddAssetAllowsSameNameInDifferentPrograms(): void
    {
        $programId2 = $this->programRepo->create($this->ownerId, 'Program 2', 'Desc', 'Scope');

        $id1 = $this->service->addAsset($this->programId, $this->ownerId, 'example.com', 'Domain');
        $id2 = $this->service->addAsset($programId2, $this->ownerId, 'example.com', 'Domain');

        $this->assertGreaterThan(0, $id1);
        $this->assertGreaterThan(0, $id2);
        $this->assertNotEquals($id1, $id2);
    }

    /**
     * @dataProvider validTypesProvider
     */
    public function testAddAssetAcceptsAllValidTypes(string $type): void
    {
        $id = $this->service->addAsset($this->programId, $this->ownerId, "asset-{$type}", $type);

        $this->assertGreaterThan(0, $id);
    }

    public static function validTypesProvider(): array
    {
        return [
            ['Domain'],
            ['Wildcard'],
            ['iOS App Store'],
            ['Android Play Store'],
            ['Windows App'],
            ['Other'],
        ];
    }

    // ─── updateAsset() ──────────────────────────────────────────────────

    public function testUpdateAssetChangesNameAndType(): void
    {
        $id = $this->service->addAsset($this->programId, $this->ownerId, 'old.com', 'Domain');

        $this->service->updateAsset($id, $this->ownerId, 'new.com', 'Wildcard');

        $asset = $this->assetRepo->findById($id);
        $this->assertSame('new.com', $asset['name']);
        $this->assertSame('Wildcard', $asset['type']);
    }

    public function testUpdateAssetThrowsOnNonExistentAsset(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Asset not found');

        $this->service->updateAsset(99999, $this->ownerId, 'name', 'Domain');
    }

    public function testUpdateAssetThrowsOnNonOwner(): void
    {
        $id = $this->service->addAsset($this->programId, $this->ownerId, 'example.com', 'Domain');

        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (2, 'Other', 'User', 'other2@test.com', '\$2y\$10\$dummyhash', 'active')"
        );
        $otherUserId = (int) self::$conn->insert_id;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Access denied');

        $this->service->updateAsset($id, $otherUserId, 'new.com', 'Domain');
    }

    public function testUpdateAssetAllowsSameNameOnSelf(): void
    {
        $id = $this->service->addAsset($this->programId, $this->ownerId, 'example.com', 'Domain');

        // Updating with the same name should not throw duplicate error
        $this->service->updateAsset($id, $this->ownerId, 'example.com', 'Wildcard');

        $asset = $this->assetRepo->findById($id);
        $this->assertSame('example.com', $asset['name']);
        $this->assertSame('Wildcard', $asset['type']);
    }

    public function testUpdateAssetThrowsOnDuplicateNameWithOtherAsset(): void
    {
        $this->service->addAsset($this->programId, $this->ownerId, 'first.com', 'Domain');
        $id2 = $this->service->addAsset($this->programId, $this->ownerId, 'second.com', 'Domain');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already exists');

        $this->service->updateAsset($id2, $this->ownerId, 'first.com', 'Domain');
    }

    // ─── deleteAsset() ──────────────────────────────────────────────────

    public function testDeleteAssetRemovesRecord(): void
    {
        $id = $this->service->addAsset($this->programId, $this->ownerId, 'example.com', 'Domain');

        $this->service->deleteAsset($id, $this->ownerId);

        $asset = $this->assetRepo->findById($id);
        $this->assertNull($asset);
    }

    public function testDeleteAssetThrowsOnNonExistentAsset(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Asset not found');

        $this->service->deleteAsset(99999, $this->ownerId);
    }

    public function testDeleteAssetThrowsOnNonOwner(): void
    {
        $id = $this->service->addAsset($this->programId, $this->ownerId, 'example.com', 'Domain');

        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (2, 'Other', 'User', 'other3@test.com', '\$2y\$10\$dummyhash', 'active')"
        );
        $otherUserId = (int) self::$conn->insert_id;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Access denied');

        $this->service->deleteAsset($id, $otherUserId);
    }

    // ─── getAssetsByProgram() ───────────────────────────────────────────

    public function testGetAssetsByProgramReturnsAllAssets(): void
    {
        $this->service->addAsset($this->programId, $this->ownerId, 'alpha.com', 'Domain');
        $this->service->addAsset($this->programId, $this->ownerId, 'beta.com', 'Wildcard');

        $assets = $this->service->getAssetsByProgram($this->programId);

        $this->assertCount(2, $assets);
    }

    public function testGetAssetsByProgramReturnsEmptyForNoAssets(): void
    {
        $assets = $this->service->getAssetsByProgram($this->programId);

        $this->assertEmpty($assets);
    }

    // ─── getAssetCountsByType() ─────────────────────────────────────────

    public function testGetAssetCountsByTypeReturnsCorrectCounts(): void
    {
        $this->service->addAsset($this->programId, $this->ownerId, 'a.com', 'Domain');
        $this->service->addAsset($this->programId, $this->ownerId, 'b.com', 'Domain');
        $this->service->addAsset($this->programId, $this->ownerId, '*.c.com', 'Wildcard');

        $counts = $this->service->getAssetCountsByType($this->programId);

        $this->assertSame(2, $counts['Domain']);
        $this->assertSame(1, $counts['Wildcard']);
        $this->assertArrayNotHasKey('Other', $counts);
    }

    public function testGetAssetCountsByTypeReturnsEmptyForNoAssets(): void
    {
        $counts = $this->service->getAssetCountsByType($this->programId);

        $this->assertEmpty($counts);
    }

    // ─── validateAsset() ────────────────────────────────────────────────

    public function testValidateAssetReturnsEmptyOnValid(): void
    {
        $errors = $this->service->validateAsset('example.com', 'Domain', $this->programId);

        $this->assertEmpty($errors);
    }

    public function testValidateAssetReturnsErrorOnEmptyName(): void
    {
        $errors = $this->service->validateAsset('', 'Domain', $this->programId);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('required', $errors[0]);
    }

    public function testValidateAssetReturnsErrorOnInvalidType(): void
    {
        $errors = $this->service->validateAsset('example.com', 'NotAType', $this->programId);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Invalid asset type', $errors[0]);
    }

    public function testValidateAssetReturnsMultipleErrors(): void
    {
        $errors = $this->service->validateAsset('', 'NotAType', $this->programId);

        $this->assertCount(2, $errors);
    }

    public function testValidateAssetExcludesIdOnUpdate(): void
    {
        $id = $this->service->addAsset($this->programId, $this->ownerId, 'example.com', 'Domain');

        // Validate the same name excluding the current asset should pass
        $errors = $this->service->validateAsset('example.com', 'Domain', $this->programId, $id);

        $this->assertEmpty($errors);
    }
}
