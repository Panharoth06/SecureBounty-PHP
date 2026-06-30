<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\TestDatabaseHelper;

require_once __DIR__ . '/../../model/repository/CommentRepository.php';
require_once __DIR__ . '/../../model/repository/ReportRepository.php';
require_once __DIR__ . '/../../model/repository/NotificationRepository.php';
require_once __DIR__ . '/../../model/services/CommentService.php';
require_once __DIR__ . '/../../model/services/NotificationService.php';

/**
 * Unit tests for CommentService.
 *
 * @covers CommentService
 * @covers CommentRepository
 */
class CommentServiceTest extends TestCase
{
    private static mysqli $conn;
    private CommentService $service;
    private CommentRepository $commentRepo;
    private int $ownerId;
    private int $researcherId;
    private int $unauthorizedUserId;
    private int $programId;
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

        $this->commentRepo = new CommentRepository(self::$conn);
        $reportRepo = new ReportRepository(self::$conn);
        $notificationRepo = new NotificationRepository(self::$conn);
        $notificationService = new NotificationService($notificationRepo);

        $this->service = new CommentService(
            $this->commentRepo,
            $reportRepo,
            $notificationService
        );

        // Create test users
        // Program Owner (role_id = 2)
        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (2, 'Program', 'Owner', 'owner@test.com', '\$2y\$10\$dummyhash', 'active')"
        );
        $this->ownerId = (int) self::$conn->insert_id;

        // Researcher (role_id = 3)
        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (3, 'Security', 'Researcher', 'researcher@test.com', '\$2y\$10\$dummyhash', 'active')"
        );
        $this->researcherId = (int) self::$conn->insert_id;

        // Unauthorized user (role_id = 3)
        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (3, 'Unauthorized', 'User', 'unauthorized@test.com', '\$2y\$10\$dummyhash', 'active')"
        );
        $this->unauthorizedUserId = (int) self::$conn->insert_id;

        // Create a program owned by the program owner
        self::$conn->query(
            "INSERT INTO programs (owner_id, title, description, scope, status)
             VALUES ({$this->ownerId}, 'Test Program', 'A test program', 'example.com', 'active')"
        );
        $this->programId = (int) self::$conn->insert_id;

        // Create a report submitted by the researcher
        self::$conn->query(
            "INSERT INTO reports (program_id, researcher_id, title, description, steps_to_reproduce, impact, status)
             VALUES ({$this->programId}, {$this->researcherId}, 'Test Report', 'A vulnerability', 'Step 1', 'High impact', 'pending')"
        );
        $this->reportId = (int) self::$conn->insert_id;
    }

    public static function tearDownAfterClass(): void
    {
        TestDatabaseHelper::cleanUp();
    }

    // --- verifyAccess tests ---

    public function testVerifyAccessReturnsTrueForResearcher(): void
    {
        $result = $this->service->verifyAccess($this->reportId, $this->researcherId);
        $this->assertTrue($result);
    }

    public function testVerifyAccessReturnsTrueForProgramOwner(): void
    {
        $result = $this->service->verifyAccess($this->reportId, $this->ownerId);
        $this->assertTrue($result);
    }

    public function testVerifyAccessReturnsFalseForUnauthorizedUser(): void
    {
        $result = $this->service->verifyAccess($this->reportId, $this->unauthorizedUserId);
        $this->assertFalse($result);
    }

    public function testVerifyAccessThrowsForNonExistentReport(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(404);

        $this->service->verifyAccess(99999, $this->researcherId);
    }

    // --- addComment tests ---

    public function testAddCommentByResearcherReturnsId(): void
    {
        $commentId = $this->service->addComment(
            $this->reportId,
            $this->researcherId,
            'This is a comment from the researcher.'
        );

        $this->assertGreaterThan(0, $commentId);
    }

    public function testAddCommentByProgramOwnerReturnsId(): void
    {
        $commentId = $this->service->addComment(
            $this->reportId,
            $this->ownerId,
            'This is a comment from the program owner.'
        );

        $this->assertGreaterThan(0, $commentId);
    }

    public function testAddCommentByUnauthorizedUserThrows403(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(403);

        $this->service->addComment(
            $this->reportId,
            $this->unauthorizedUserId,
            'Should not be allowed.'
        );
    }

    public function testAddCommentNotifiesOtherParticipant(): void
    {
        // Researcher comments → program owner should be notified
        $this->service->addComment(
            $this->reportId,
            $this->researcherId,
            'Hello, I found a vulnerability.'
        );

        $result = self::$conn->query(
            "SELECT * FROM notifications WHERE user_id = {$this->ownerId} AND type = 'comment.new' LIMIT 1"
        );
        $notification = $result->fetch_assoc();

        $this->assertNotNull($notification);
        $this->assertEquals($this->reportId, $notification['reference_id']);
        $this->assertSame('report', $notification['reference_entity']);
    }

    public function testAddCommentByOwnerNotifiesResearcher(): void
    {
        // Program owner comments → researcher should be notified
        $this->service->addComment(
            $this->reportId,
            $this->ownerId,
            'Thank you for your report.'
        );

        $result = self::$conn->query(
            "SELECT * FROM notifications WHERE user_id = {$this->researcherId} AND type = 'comment.new' LIMIT 1"
        );
        $notification = $result->fetch_assoc();

        $this->assertNotNull($notification);
        $this->assertEquals($this->reportId, $notification['reference_id']);
    }

    // --- Threaded replies ---

    public function testAddReplyToExistingComment(): void
    {
        $parentId = $this->service->addComment(
            $this->reportId,
            $this->researcherId,
            'Parent comment.'
        );

        $replyId = $this->service->addComment(
            $this->reportId,
            $this->ownerId,
            'Reply to parent.',
            $parentId
        );

        $this->assertGreaterThan(0, $replyId);

        $reply = $this->commentRepo->findById($replyId);
        $this->assertEquals($parentId, $reply['parent_id']);
    }

    public function testAddReplyToInvalidParentThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(400);

        $this->service->addComment(
            $this->reportId,
            $this->researcherId,
            'Reply to non-existent comment.',
            99999
        );
    }

    // --- getCommentsForReport tests ---

    public function testGetCommentsForReportReturnsChronologicalOrder(): void
    {
        $this->service->addComment($this->reportId, $this->researcherId, 'First comment.');
        $this->service->addComment($this->reportId, $this->ownerId, 'Second comment.');
        $this->service->addComment($this->reportId, $this->researcherId, 'Third comment.');

        $comments = $this->service->getCommentsForReport($this->reportId, $this->researcherId);

        $this->assertCount(3, $comments);
        $this->assertSame('First comment.', $comments[0]['body']);
        $this->assertSame('Second comment.', $comments[1]['body']);
        $this->assertSame('Third comment.', $comments[2]['body']);
    }

    public function testGetCommentsForReportIncludesUserNames(): void
    {
        $this->service->addComment($this->reportId, $this->researcherId, 'A comment.');

        $comments = $this->service->getCommentsForReport($this->reportId, $this->researcherId);

        $this->assertCount(1, $comments);
        $this->assertSame('Security', $comments[0]['author_first_name']);
        $this->assertSame('Researcher', $comments[0]['author_last_name']);
    }

    public function testGetCommentsForReportThrows403ForUnauthorizedUser(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(403);

        $this->service->getCommentsForReport($this->reportId, $this->unauthorizedUserId);
    }

    public function testGetCommentsForReportReturnsEmptyArrayWhenNoComments(): void
    {
        $comments = $this->service->getCommentsForReport($this->reportId, $this->researcherId);
        $this->assertIsArray($comments);
        $this->assertEmpty($comments);
    }
}
