<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\TestDatabaseHelper;

require_once __DIR__ . '/../../model/repository/AttachmentRepository.php';
require_once __DIR__ . '/../../model/services/AttachmentService.php';

/**
 * Unit tests for AttachmentService.
 *
 * @covers AttachmentService
 */
class AttachmentServiceTest extends TestCase
{
    private static mysqli $conn;
    private AttachmentService $service;
    private AttachmentRepository $repository;
    private int $reportId;

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

        $this->repository = new AttachmentRepository(self::$conn);
        $this->service = new AttachmentService($this->repository);

        // Create a test user (Researcher role = 3)
        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (3, 'Test', 'Researcher', 'researcher@test.com', '\$2y\$10\$dummyhash', 'active')"
        );
        $researcherId = (int) self::$conn->insert_id;

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
        $programId = (int) self::$conn->insert_id;

        // Create a report
        self::$conn->query(
            "INSERT INTO reports (program_id, researcher_id, title, description, steps_to_reproduce, impact, status)
             VALUES ({$programId}, {$researcherId}, 'Test Report', 'Desc', 'Steps', 'Impact', 'pending')"
        );
        $this->reportId = (int) self::$conn->insert_id;
    }

    public static function tearDownAfterClass(): void
    {
        TestDatabaseHelper::cleanUp();
    }

    // --- validateFile() tests ---

    public function testValidateFileReturnsEmptyArrayForValidFile(): void
    {
        $file = [
            'name' => 'screenshot.png',
            'size' => 1024,
            'tmp_name' => '/tmp/phpXXXXX',
        ];

        $errors = $this->service->validateFile($file);

        $this->assertEmpty($errors);
    }

    public function testValidateFileRejectsDisallowedExtension(): void
    {
        $file = [
            'name' => 'malware.exe',
            'size' => 1024,
            'tmp_name' => '/tmp/phpXXXXX',
        ];

        $errors = $this->service->validateFile($file);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('not allowed', $errors[0]);
    }

    public function testValidateFileRejectsOversizedFile(): void
    {
        $file = [
            'name' => 'large-file.pdf',
            'size' => 10485761, // 10 MB + 1 byte
            'tmp_name' => '/tmp/phpXXXXX',
        ];

        $errors = $this->service->validateFile($file);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('10 MB', $errors[0]);
    }

    public function testValidateFileRejectsZeroSizeFile(): void
    {
        $file = [
            'name' => 'empty.txt',
            'size' => 0,
            'tmp_name' => '/tmp/phpXXXXX',
        ];

        $errors = $this->service->validateFile($file);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('greater than 0', $errors[0]);
    }

    public function testValidateFileRejectsMissingFileName(): void
    {
        $file = [
            'name' => '',
            'size' => 1024,
            'tmp_name' => '/tmp/phpXXXXX',
        ];

        $errors = $this->service->validateFile($file);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('required', $errors[0]);
    }

    public function testValidateFileAcceptsAllAllowedTypes(): void
    {
        $allowedTypes = ['png', 'jpg', 'gif', 'pdf', 'txt', 'zip'];

        foreach ($allowedTypes as $type) {
            $file = [
                'name' => "test-file.{$type}",
                'size' => 1024,
                'tmp_name' => '/tmp/phpXXXXX',
            ];

            $errors = $this->service->validateFile($file);
            $this->assertEmpty($errors, "Expected '{$type}' to be a valid type");
        }
    }

    public function testValidateFileAcceptsMaxSizeFile(): void
    {
        $file = [
            'name' => 'max-size.pdf',
            'size' => 10485760, // Exactly 10 MB
            'tmp_name' => '/tmp/phpXXXXX',
        ];

        $errors = $this->service->validateFile($file);

        $this->assertEmpty($errors);
    }

    // --- generateStoragePath() tests ---

    public function testGenerateStoragePathReturnsCorrectFormat(): void
    {
        $path = $this->service->generateStoragePath(42, 'screenshot.png');

        $this->assertSame('uploads/attachments/42/screenshot.png', $path);
    }

    public function testGenerateStoragePathSanitizesSpecialCharacters(): void
    {
        $path = $this->service->generateStoragePath(1, 'file name (1).png');

        // Spaces become underscores, parentheses are removed
        $this->assertSame('uploads/attachments/1/file_name_1.png', $path);
    }

    public function testGenerateStoragePathRemovesPathTraversal(): void
    {
        $path = $this->service->generateStoragePath(1, '../../../etc/passwd');

        // Path traversal should be neutralized
        $this->assertStringNotContainsString('..', $path);
        $this->assertStringStartsWith('uploads/attachments/1/', $path);
    }

    public function testGenerateStoragePathPreservesDotsAndHyphens(): void
    {
        $path = $this->service->generateStoragePath(5, 'my-report.v2.pdf');

        $this->assertSame('uploads/attachments/5/my-report.v2.pdf', $path);
    }

    // --- uploadAttachment() tests ---

    public function testUploadAttachmentThrowsOnInvalidFile(): void
    {
        $file = [
            'name' => 'virus.exe',
            'size' => 1024,
            'tmp_name' => '/tmp/phpXXXXX',
        ];

        $this->expectException(InvalidArgumentException::class);

        $this->service->uploadAttachment($this->reportId, $file);
    }

    public function testUploadAttachmentThrowsOnOversizedFile(): void
    {
        $file = [
            'name' => 'huge.pdf',
            'size' => 10485761,
            'tmp_name' => '/tmp/phpXXXXX',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('10 MB');

        $this->service->uploadAttachment($this->reportId, $file);
    }
}
