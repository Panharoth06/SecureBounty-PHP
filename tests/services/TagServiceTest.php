<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\TestDatabaseHelper;

require_once __DIR__ . '/../../model/repository/TagRepository.php';
require_once __DIR__ . '/../../model/repository/ProgramRepository.php';
require_once __DIR__ . '/../../model/services/TagService.php';

/**
 * Unit tests for TagService.
 *
 * @covers TagService
 */
class TagServiceTest extends TestCase
{
    private static mysqli $conn;
    private TagService $service;
    private TagRepository $tagRepo;
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

        $this->tagRepo = new TagRepository(self::$conn);
        $this->programRepo = new ProgramRepository(self::$conn);

        $this->service = new TagService($this->tagRepo, $this->programRepo);

        // Create a test user (Program_Owner role = 2)
        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (2, 'Test', 'Owner', 'tagowner@test.com', '\$2y\$10\$dummyhash', 'active')"
        );
        $this->ownerId = (int) self::$conn->insert_id;

        // Create a test program
        $this->programId = $this->programRepo->create($this->ownerId, 'Test Program', 'Description', 'Scope');
    }

    public static function tearDownAfterClass(): void
    {
        TestDatabaseHelper::cleanUp();
    }

    // --- addTag() tests ---

    public function testAddTagAssociatesTagWithProgram(): void
    {
        $this->service->addTag($this->programId, $this->ownerId, 'PHP');

        $tags = $this->service->getTagsByProgram($this->programId);
        $this->assertCount(1, $tags);
        $this->assertSame('php', $tags[0]['normalized_name']);
    }

    public function testAddTagCreatesTagInSharedPool(): void
    {
        $this->service->addTag($this->programId, $this->ownerId, 'React');

        $tag = $this->tagRepo->findByName('react');
        $this->assertNotNull($tag);
        $this->assertSame('React', $tag['name']);
    }

    public function testAddTagReusesExistingTagFromPool(): void
    {
        // Create a second program owned by same owner
        $programId2 = $this->programRepo->create($this->ownerId, 'Program 2', 'Desc', 'Scope');

        $this->service->addTag($this->programId, $this->ownerId, 'Node.js');
        $this->service->addTag($programId2, $this->ownerId, 'node.js');

        // Both should reference the same tag ID
        $tags1 = $this->service->getTagsByProgram($this->programId);
        $tags2 = $this->service->getTagsByProgram($programId2);

        $this->assertSame((int) $tags1[0]['id'], (int) $tags2[0]['id']);
    }

    public function testAddTagThrowsOnInvalidName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tag name cannot be empty.');

        $this->service->addTag($this->programId, $this->ownerId, '   ');
    }

    public function testAddTagThrowsOnSpecialCharacters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tag name may only contain');

        $this->service->addTag($this->programId, $this->ownerId, 'C# Language');
    }

    public function testAddTagThrowsOnExceedingMaxLength(): void
    {
        $longName = str_repeat('a', 51);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must not exceed 50 characters');

        $this->service->addTag($this->programId, $this->ownerId, $longName);
    }

    public function testAddTagThrowsWhenNotOwner(): void
    {
        // Create a different user
        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (2, 'Other', 'User', 'other@test.com', '\$2y\$10\$dummyhash', 'active')"
        );
        $otherId = (int) self::$conn->insert_id;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('You do not have permission to modify this program.');

        $this->service->addTag($this->programId, $otherId, 'PHP');
    }

    public function testAddTagThrowsWhenProgramNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Program not found.');

        $this->service->addTag(99999, $this->ownerId, 'PHP');
    }

    public function testAddTagThrowsWhenAtMaxLimit(): void
    {
        // Add 20 tags
        for ($i = 1; $i <= 20; $i++) {
            $this->service->addTag($this->programId, $this->ownerId, "tag{$i}");
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Maximum of 20 tags per program reached.');

        $this->service->addTag($this->programId, $this->ownerId, 'tag21');
    }

    public function testAddTagThrowsOnDuplicateAssociation(): void
    {
        $this->service->addTag($this->programId, $this->ownerId, 'PHP');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already associated');

        $this->service->addTag($this->programId, $this->ownerId, 'php');
    }

    // --- removeTag() tests ---

    public function testRemoveTagDissociatesFromProgram(): void
    {
        $this->service->addTag($this->programId, $this->ownerId, 'PHP');
        $tags = $this->service->getTagsByProgram($this->programId);
        $tagId = (int) $tags[0]['id'];

        $this->service->removeTag($this->programId, $this->ownerId, $tagId);

        $tagsAfter = $this->service->getTagsByProgram($this->programId);
        $this->assertCount(0, $tagsAfter);
    }

    public function testRemoveTagPreservesTagInPool(): void
    {
        $this->service->addTag($this->programId, $this->ownerId, 'Python');
        $tags = $this->service->getTagsByProgram($this->programId);
        $tagId = (int) $tags[0]['id'];

        $this->service->removeTag($this->programId, $this->ownerId, $tagId);

        // Tag should still exist in the shared pool
        $tag = $this->tagRepo->findByName('python');
        $this->assertNotNull($tag);
    }

    public function testRemoveTagThrowsWhenNotOwner(): void
    {
        $this->service->addTag($this->programId, $this->ownerId, 'PHP');
        $tags = $this->service->getTagsByProgram($this->programId);
        $tagId = (int) $tags[0]['id'];

        // Create a different user
        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (2, 'Other', 'User', 'other2@test.com', '\$2y\$10\$dummyhash', 'active')"
        );
        $otherId = (int) self::$conn->insert_id;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('You do not have permission to modify this program.');

        $this->service->removeTag($this->programId, $otherId, $tagId);
    }

    // --- getTagsByProgram() tests ---

    public function testGetTagsByProgramReturnsAllTags(): void
    {
        $this->service->addTag($this->programId, $this->ownerId, 'PHP');
        $this->service->addTag($this->programId, $this->ownerId, 'MySQL');
        $this->service->addTag($this->programId, $this->ownerId, 'Redis');

        $tags = $this->service->getTagsByProgram($this->programId);
        $this->assertCount(3, $tags);
    }

    public function testGetTagsByProgramReturnsEmptyForNoTags(): void
    {
        $tags = $this->service->getTagsByProgram($this->programId);
        $this->assertCount(0, $tags);
    }

    // --- searchTags() tests ---

    public function testSearchTagsReturnsPrefixMatches(): void
    {
        $this->service->addTag($this->programId, $this->ownerId, 'PHP');
        $this->service->addTag($this->programId, $this->ownerId, 'Python');

        $results = $this->service->searchTags('ph');
        $this->assertCount(1, $results);
        $this->assertSame('php', $results[0]['normalized_name']);
    }

    public function testSearchTagsReturnsEmptyForNoMatch(): void
    {
        $this->service->addTag($this->programId, $this->ownerId, 'PHP');

        $results = $this->service->searchTags('xyz');
        $this->assertCount(0, $results);
    }

    // --- getTagCountForProgram() tests ---

    public function testGetTagCountForProgramReturnsCorrectCount(): void
    {
        $this->service->addTag($this->programId, $this->ownerId, 'PHP');
        $this->service->addTag($this->programId, $this->ownerId, 'MySQL');

        $count = $this->service->getTagCountForProgram($this->programId);
        $this->assertSame(2, $count);
    }

    public function testGetTagCountForProgramReturnsZeroForNoTags(): void
    {
        $count = $this->service->getTagCountForProgram($this->programId);
        $this->assertSame(0, $count);
    }

    // --- validateTagName() tests ---

    public function testValidateTagNameAcceptsValidNames(): void
    {
        $this->assertEmpty($this->service->validateTagName('PHP'));
        $this->assertEmpty($this->service->validateTagName('node.js'));
        $this->assertEmpty($this->service->validateTagName('C++'));
        $this->assertEmpty($this->service->validateTagName('vue-router'));
        $this->assertEmpty($this->service->validateTagName('a'));
        $this->assertEmpty($this->service->validateTagName(str_repeat('x', 50)));
    }

    public function testValidateTagNameRejectsEmptyString(): void
    {
        $errors = $this->service->validateTagName('');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('cannot be empty', $errors[0]);
    }

    public function testValidateTagNameRejectsWhitespaceOnly(): void
    {
        $errors = $this->service->validateTagName('   ');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('cannot be empty', $errors[0]);
    }

    public function testValidateTagNameRejectsOverMaxLength(): void
    {
        $errors = $this->service->validateTagName(str_repeat('a', 51));
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('50 characters', $errors[0]);
    }

    public function testValidateTagNameRejectsInvalidCharacters(): void
    {
        $errors = $this->service->validateTagName('C# Sharp');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('may only contain', $errors[0]);
    }

    public function testValidateTagNameRejectsSpaces(): void
    {
        $errors = $this->service->validateTagName('My Tag');
        $this->assertNotEmpty($errors);
    }

    public function testValidateTagNameRejectsSpecialSymbols(): void
    {
        $errors = $this->service->validateTagName('tag@name');
        $this->assertNotEmpty($errors);

        $errors = $this->service->validateTagName('tag!name');
        $this->assertNotEmpty($errors);
    }
}
