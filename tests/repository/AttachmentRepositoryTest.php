<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\TestDatabaseHelper;

require_once __DIR__ . '/../../model/repository/AttachmentRepository.php';

/**
 * Unit tests for AttachmentRepository.
 *
 * @covers AttachmentRepository
 */
class AttachmentRepositoryTest extends TestCase
{
    private static mysqli $conn;
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

    public function testCreateReturnsAttachmentId(): void
    {
        $id = $this->repository->create(
            $this->reportId,
            'screenshot.png',
            'uploads/attachments/1/screenshot.png',
            'png',
            1024
        );

        $this->assertGreaterThan(0, $id);
    }

    public function testCreateStoresCorrectData(): void
    {
        $id = $this->repository->create(
            $this->reportId,
            'report.pdf',
            'uploads/attachments/1/report.pdf',
            'pdf',
            5242880
        );

        $attachment = $this->repository->findById($id);

        $this->assertNotNull($attachment);
        $this->assertEquals($this->reportId, $attachment['report_id']);
        $this->assertSame('report.pdf', $attachment['file_name']);
        $this->assertSame('uploads/attachments/1/report.pdf', $attachment['file_path']);
        $this->assertSame('pdf', $attachment['file_type']);
        $this->assertEquals(5242880, $attachment['file_size']);
        $this->assertNotEmpty($attachment['uploaded_at']);
    }

    public function testFindByIdReturnsNullForNonExistentId(): void
    {
        $result = $this->repository->findById(99999);
        $this->assertNull($result);
    }

    public function testFindByReportIdReturnsAllAttachments(): void
    {
        $this->repository->create($this->reportId, 'file1.png', 'path/file1.png', 'png', 1000);
        $this->repository->create($this->reportId, 'file2.jpg', 'path/file2.jpg', 'jpg', 2000);
        $this->repository->create($this->reportId, 'file3.txt', 'path/file3.txt', 'txt', 500);

        $attachments = $this->repository->findByReportId($this->reportId);

        $this->assertCount(3, $attachments);
    }

    public function testFindByReportIdReturnsEmptyForNoAttachments(): void
    {
        $attachments = $this->repository->findByReportId(99999);
        $this->assertCount(0, $attachments);
    }

    public function testFindByReportIdOrdersByUploadedAtAsc(): void
    {
        $id1 = $this->repository->create($this->reportId, 'first.png', 'path/first.png', 'png', 100);
        $id2 = $this->repository->create($this->reportId, 'second.jpg', 'path/second.jpg', 'jpg', 200);

        $attachments = $this->repository->findByReportId($this->reportId);

        $this->assertEquals($id1, $attachments[0]['id']);
        $this->assertEquals($id2, $attachments[1]['id']);
    }
}
