<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\TestDatabaseHelper;

require_once __DIR__ . '/../../model/repository/TagRepository.php';

/**
 * Unit tests for TagRepository.
 *
 * @covers TagRepository
 */
class TagRepositoryTest extends TestCase
{
    private static mysqli $conn;
    private TagRepository $repository;
    private int $programId;
    private int $programId2;

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
        $this->repository = new TagRepository(self::$conn);

        // Create a program owner
        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (2, 'Test', 'Owner', 'owner@test.com', '\$2y\$10\$dummyhash', 'active')"
        );
        $ownerId = (int) self::$conn->insert_id;

        // Create two programs
        self::$conn->query(
            "INSERT INTO programs (owner_id, title, description, scope, status)
             VALUES ({$ownerId}, 'Program One', 'Desc', 'Scope', 'active')"
        );
        $this->programId = (int) self::$conn->insert_id;

        self::$conn->query(
            "INSERT INTO programs (owner_id, title, description, scope, status)
             VALUES ({$ownerId}, 'Program Two', 'Desc', 'Scope', 'active')"
        );
        $this->programId2 = (int) self::$conn->insert_id;
    }

    public static function tearDownAfterClass(): void
    {
        TestDatabaseHelper::cleanUp();
    }

    // --- findOrCreate tests ---

    public function testFindOrCreateCreatesNewTag(): void
    {
        $tagId = $this->repository->findOrCreate('PHP');

        $this->assertGreaterThan(0, $tagId);
    }

    public function testFindOrCreateReturnsSameIdForExistingTag(): void
    {
        $tagId1 = $this->repository->findOrCreate('PHP');
        $tagId2 = $this->repository->findOrCreate('PHP');

        $this->assertSame($tagId1, $tagId2);
    }

    public function testFindOrCreateIsCaseInsensitive(): void
    {
        $tagId1 = $this->repository->findOrCreate('PHP');
        $tagId2 = $this->repository->findOrCreate('php');
        $tagId3 = $this->repository->findOrCreate('Php');

        $this->assertSame($tagId1, $tagId2);
        $this->assertSame($tagId1, $tagId3);
    }

    public function testFindOrCreatePreservesOriginalCasing(): void
    {
        $this->repository->findOrCreate('ReactJS');

        $tag = $this->repository->findByName('reactjs');

        $this->assertNotNull($tag);
        $this->assertSame('ReactJS', $tag['name']);
        $this->assertSame('reactjs', $tag['normalized_name']);
    }

    // --- findByName tests ---

    public function testFindByNameReturnsTagRecord(): void
    {
        $this->repository->findOrCreate('JavaScript');

        $tag = $this->repository->findByName('javascript');

        $this->assertNotNull($tag);
        $this->assertSame('JavaScript', $tag['name']);
        $this->assertSame('javascript', $tag['normalized_name']);
        $this->assertArrayHasKey('id', $tag);
        $this->assertArrayHasKey('created_at', $tag);
    }

    public function testFindByNameReturnsNullForNonExistent(): void
    {
        $result = $this->repository->findByName('nonexistent');

        $this->assertNull($result);
    }

    public function testFindByNameIsCaseInsensitive(): void
    {
        $this->repository->findOrCreate('AWS');

        $tag = $this->repository->findByName('aws');
        $this->assertNotNull($tag);

        $tag2 = $this->repository->findByName('AWS');
        $this->assertNotNull($tag2);
        $this->assertSame($tag['id'], $tag2['id']);
    }

    // --- searchByPrefix tests ---

    public function testSearchByPrefixFindsMatchingTags(): void
    {
        $this->repository->findOrCreate('react');
        $this->repository->findOrCreate('react-native');
        $this->repository->findOrCreate('redis');
        $this->repository->findOrCreate('python');

        $results = $this->repository->searchByPrefix('rea');

        $this->assertCount(2, $results);
    }

    public function testSearchByPrefixIsCaseInsensitive(): void
    {
        $this->repository->findOrCreate('PHP');
        $this->repository->findOrCreate('python');

        $results = $this->repository->searchByPrefix('PH');

        $this->assertCount(1, $results);
        $this->assertSame('php', $results[0]['normalized_name']);
    }

    public function testSearchByPrefixRespectsLimit(): void
    {
        $this->repository->findOrCreate('tag-1');
        $this->repository->findOrCreate('tag-2');
        $this->repository->findOrCreate('tag-3');

        $results = $this->repository->searchByPrefix('tag', 2);

        $this->assertCount(2, $results);
    }

    public function testSearchByPrefixReturnsEmptyForNoMatch(): void
    {
        $this->repository->findOrCreate('PHP');

        $results = $this->repository->searchByPrefix('xyz');

        $this->assertCount(0, $results);
    }

    // --- associateWithProgram tests ---

    public function testAssociateWithProgramReturnsTrue(): void
    {
        $tagId = $this->repository->findOrCreate('PHP');

        $result = $this->repository->associateWithProgram($tagId, $this->programId);

        $this->assertTrue($result);
    }

    public function testAssociateWithProgramReturnsFalseOnDuplicate(): void
    {
        $tagId = $this->repository->findOrCreate('PHP');

        $this->repository->associateWithProgram($tagId, $this->programId);
        $result = $this->repository->associateWithProgram($tagId, $this->programId);

        $this->assertFalse($result);
    }

    public function testAssociateWithProgramAllowsSameTagOnDifferentPrograms(): void
    {
        $tagId = $this->repository->findOrCreate('PHP');

        $result1 = $this->repository->associateWithProgram($tagId, $this->programId);
        $result2 = $this->repository->associateWithProgram($tagId, $this->programId2);

        $this->assertTrue($result1);
        $this->assertTrue($result2);
    }

    // --- dissociateFromProgram tests ---

    public function testDissociateFromProgramRemovesAssociation(): void
    {
        $tagId = $this->repository->findOrCreate('PHP');
        $this->repository->associateWithProgram($tagId, $this->programId);

        $affected = $this->repository->dissociateFromProgram($tagId, $this->programId);

        $this->assertSame(1, $affected);
        $this->assertFalse($this->repository->isAssociatedWithProgram($tagId, $this->programId));
    }

    public function testDissociateFromProgramPreservesTagInPool(): void
    {
        $tagId = $this->repository->findOrCreate('PHP');
        $this->repository->associateWithProgram($tagId, $this->programId);

        $this->repository->dissociateFromProgram($tagId, $this->programId);

        // Tag should still exist in the shared pool
        $tag = $this->repository->findByName('PHP');
        $this->assertNotNull($tag);
        $this->assertSame($tagId, (int) $tag['id']);
    }

    public function testDissociateFromProgramReturnsZeroForNonExistent(): void
    {
        $tagId = $this->repository->findOrCreate('PHP');

        $affected = $this->repository->dissociateFromProgram($tagId, $this->programId);

        $this->assertSame(0, $affected);
    }

    // --- findByProgramId tests ---

    public function testFindByProgramIdReturnsTags(): void
    {
        $tagId1 = $this->repository->findOrCreate('PHP');
        $tagId2 = $this->repository->findOrCreate('MySQL');
        $this->repository->associateWithProgram($tagId1, $this->programId);
        $this->repository->associateWithProgram($tagId2, $this->programId);

        $tags = $this->repository->findByProgramId($this->programId);

        $this->assertCount(2, $tags);
    }

    public function testFindByProgramIdReturnsEmptyForNoTags(): void
    {
        $tags = $this->repository->findByProgramId($this->programId);

        $this->assertCount(0, $tags);
    }

    public function testFindByProgramIdOrdersByNormalizedName(): void
    {
        $tagId1 = $this->repository->findOrCreate('PHP');
        $tagId2 = $this->repository->findOrCreate('AWS');
        $tagId3 = $this->repository->findOrCreate('MySQL');
        $this->repository->associateWithProgram($tagId1, $this->programId);
        $this->repository->associateWithProgram($tagId2, $this->programId);
        $this->repository->associateWithProgram($tagId3, $this->programId);

        $tags = $this->repository->findByProgramId($this->programId);

        $this->assertSame('aws', $tags[0]['normalized_name']);
        $this->assertSame('mysql', $tags[1]['normalized_name']);
        $this->assertSame('php', $tags[2]['normalized_name']);
    }

    // --- countByProgramId tests ---

    public function testCountByProgramIdReturnsCorrectCount(): void
    {
        $tagId1 = $this->repository->findOrCreate('PHP');
        $tagId2 = $this->repository->findOrCreate('MySQL');
        $tagId3 = $this->repository->findOrCreate('Redis');
        $this->repository->associateWithProgram($tagId1, $this->programId);
        $this->repository->associateWithProgram($tagId2, $this->programId);
        $this->repository->associateWithProgram($tagId3, $this->programId);

        $count = $this->repository->countByProgramId($this->programId);

        $this->assertSame(3, $count);
    }

    public function testCountByProgramIdReturnsZeroForNoTags(): void
    {
        $count = $this->repository->countByProgramId($this->programId);

        $this->assertSame(0, $count);
    }

    // --- isAssociatedWithProgram tests ---

    public function testIsAssociatedWithProgramReturnsTrueWhenAssociated(): void
    {
        $tagId = $this->repository->findOrCreate('PHP');
        $this->repository->associateWithProgram($tagId, $this->programId);

        $result = $this->repository->isAssociatedWithProgram($tagId, $this->programId);

        $this->assertTrue($result);
    }

    public function testIsAssociatedWithProgramReturnsFalseWhenNotAssociated(): void
    {
        $tagId = $this->repository->findOrCreate('PHP');

        $result = $this->repository->isAssociatedWithProgram($tagId, $this->programId);

        $this->assertFalse($result);
    }
}
