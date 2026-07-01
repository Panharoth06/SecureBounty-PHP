<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\TestDatabaseHelper;

require_once __DIR__ . '/../../model/services/StatisticsService.php';

/**
 * Unit tests for StatisticsService.
 *
 * @covers StatisticsService
 */
class StatisticsServiceTest extends TestCase
{
    private static mysqli $conn;
    private StatisticsService $service;
    private int $ownerId;
    private int $researcherId;
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

        $this->service = new StatisticsService(self::$conn);

        // Create a test program owner
        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (2, 'Test', 'Owner', 'owner@test.com', '\$2y\$10\$dummyhash', 'active')"
        );
        $this->ownerId = (int) self::$conn->insert_id;

        // Create a test researcher
        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (3, 'Test', 'Researcher', 'researcher@test.com', '\$2y\$10\$dummyhash', 'active')"
        );
        $this->researcherId = (int) self::$conn->insert_id;

        // Create a test program
        self::$conn->query(
            "INSERT INTO programs (owner_id, title, description, scope, status)
             VALUES ({$this->ownerId}, 'Test Program', 'Description', 'scope.com', 'active')"
        );
        $this->programId = (int) self::$conn->insert_id;
    }

    public static function tearDownAfterClass(): void
    {
        TestDatabaseHelper::cleanUp();
    }

    // --- getReportCount tests ---

    public function testGetReportCountReturnsZeroForProgramWithNoReports(): void
    {
        $count = $this->service->getReportCount($this->programId);
        $this->assertSame(0, $count);
    }

    public function testGetReportCountReturnsCorrectCount(): void
    {
        $this->insertReport($this->programId, $this->researcherId, 'pending');
        $this->insertReport($this->programId, $this->researcherId, 'accepted');
        $this->insertReport($this->programId, $this->researcherId, 'rejected');

        $count = $this->service->getReportCount($this->programId);
        $this->assertSame(3, $count);
    }

    public function testGetReportCountDoesNotCountOtherPrograms(): void
    {
        // Create another program
        self::$conn->query(
            "INSERT INTO programs (owner_id, title, description, scope, status)
             VALUES ({$this->ownerId}, 'Other Program', 'Desc', 'other.com', 'active')"
        );
        $otherProgramId = (int) self::$conn->insert_id;

        $this->insertReport($this->programId, $this->researcherId, 'pending');
        $this->insertReport($otherProgramId, $this->researcherId, 'pending');

        $count = $this->service->getReportCount($this->programId);
        $this->assertSame(1, $count);
    }

    // --- getEnrolledCount tests ---

    public function testGetEnrolledCountReturnsZeroForProgramWithNoEnrollments(): void
    {
        $count = $this->service->getEnrolledCount($this->programId);
        $this->assertSame(0, $count);
    }

    public function testGetEnrolledCountReturnsCorrectCount(): void
    {
        $this->enrollResearcher($this->researcherId, $this->programId);

        // Create and enroll a second researcher
        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (3, 'Another', 'Researcher', 'another@test.com', '\$2y\$10\$dummyhash', 'active')"
        );
        $secondResearcherId = (int) self::$conn->insert_id;
        $this->enrollResearcher($secondResearcherId, $this->programId);

        $count = $this->service->getEnrolledCount($this->programId);
        $this->assertSame(2, $count);
    }

    // --- calculateResponseRate tests ---

    public function testCalculateResponseRateReturnsNullWhenNoReports(): void
    {
        $rate = $this->service->calculateResponseRate($this->programId);
        $this->assertNull($rate);
    }

    public function testCalculateResponseRateReturnsNullWhenAllReportsNewerThan7Days(): void
    {
        // Insert a report created "now" (less than 7 days ago)
        $this->insertReport($this->programId, $this->researcherId, 'pending');

        $rate = $this->service->calculateResponseRate($this->programId);
        $this->assertNull($rate);
    }

    public function testCalculateResponseRateReturns100WhenAllEligibleReportsResponded(): void
    {
        // Insert a report created 10 days ago, responded to within 7 days of submission
        $createdAt = date('Y-m-d H:i:s', strtotime('-10 days'));
        $respondedAt = date('Y-m-d H:i:s', strtotime('-5 days')); // 5 days after created (within 7 days)

        $this->insertReportWithDates(
            $this->programId,
            $this->researcherId,
            'triaged',
            $createdAt,
            $respondedAt
        );

        $rate = $this->service->calculateResponseRate($this->programId);
        $this->assertSame(100, $rate);
    }

    public function testCalculateResponseRateReturns0WhenNoEligibleReportsResponded(): void
    {
        // Insert a report created 10 days ago, still pending (never responded)
        $createdAt = date('Y-m-d H:i:s', strtotime('-10 days'));

        $this->insertReportWithDates(
            $this->programId,
            $this->researcherId,
            'pending',
            $createdAt,
            $createdAt
        );

        $rate = $this->service->calculateResponseRate($this->programId);
        $this->assertSame(0, $rate);
    }

    public function testCalculateResponseRateExcludesLateResponses(): void
    {
        // Report created 20 days ago, responded to 8 days after submission (too late)
        $createdAt = date('Y-m-d H:i:s', strtotime('-20 days'));
        $respondedAt = date('Y-m-d H:i:s', strtotime('-12 days')); // 8 days after created (> 7 days)

        $this->insertReportWithDates(
            $this->programId,
            $this->researcherId,
            'triaged',
            $createdAt,
            $respondedAt
        );

        $rate = $this->service->calculateResponseRate($this->programId);
        $this->assertSame(0, $rate);
    }

    public function testCalculateResponseRateCalculatesCorrectPercentage(): void
    {
        // 2 eligible reports: 1 responded within 7 days, 1 not responded
        $createdAt = date('Y-m-d H:i:s', strtotime('-10 days'));
        $respondedAt = date('Y-m-d H:i:s', strtotime('-6 days')); // 4 days after (within 7)

        // Report 1: responded within time
        $this->insertReportWithDates(
            $this->programId,
            $this->researcherId,
            'triaged',
            $createdAt,
            $respondedAt
        );

        // Report 2: still pending (not responded)
        $this->insertReportWithDates(
            $this->programId,
            $this->researcherId,
            'pending',
            $createdAt,
            $createdAt
        );

        $rate = $this->service->calculateResponseRate($this->programId);
        $this->assertSame(50, $rate);
    }

    // --- getProgramStatistics tests ---

    public function testGetProgramStatisticsReturnsCorrectStructure(): void
    {
        $stats = $this->service->getProgramStatistics($this->programId);

        $this->assertArrayHasKey('report_count', $stats);
        $this->assertArrayHasKey('enrolled_count', $stats);
        $this->assertArrayHasKey('response_rate', $stats);
        $this->assertArrayHasKey('badges', $stats);
        $this->assertArrayHasKey('responsive', $stats['badges']);
        $this->assertArrayHasKey('popular', $stats['badges']);
    }

    public function testGetProgramStatisticsResponsiveBadgeWhenRateAbove80(): void
    {
        // Create 5 eligible reports all responded to within 7 days (100% rate)
        $createdAt = date('Y-m-d H:i:s', strtotime('-10 days'));
        $respondedAt = date('Y-m-d H:i:s', strtotime('-7 days'));

        for ($i = 0; $i < 5; $i++) {
            $this->insertReportWithDates(
                $this->programId,
                $this->researcherId,
                'triaged',
                $createdAt,
                $respondedAt
            );
        }

        $stats = $this->service->getProgramStatistics($this->programId);
        $this->assertTrue($stats['badges']['responsive']);
    }

    public function testGetProgramStatisticsNoResponsiveBadgeWhenRateBelow80(): void
    {
        // 5 eligible reports, only 3 responded (60% rate)
        $createdAt = date('Y-m-d H:i:s', strtotime('-10 days'));
        $respondedAt = date('Y-m-d H:i:s', strtotime('-7 days'));

        for ($i = 0; $i < 3; $i++) {
            $this->insertReportWithDates(
                $this->programId,
                $this->researcherId,
                'triaged',
                $createdAt,
                $respondedAt
            );
        }
        for ($i = 0; $i < 2; $i++) {
            $this->insertReportWithDates(
                $this->programId,
                $this->researcherId,
                'pending',
                $createdAt,
                $createdAt
            );
        }

        $stats = $this->service->getProgramStatistics($this->programId);
        $this->assertFalse($stats['badges']['responsive']);
    }

    public function testGetProgramStatisticsNoResponsiveBadgeWhenRateIsNull(): void
    {
        // No eligible reports
        $stats = $this->service->getProgramStatistics($this->programId);
        $this->assertFalse($stats['badges']['responsive']);
    }

    public function testGetProgramStatisticsPopularBadgeWhenEnrolledGte10(): void
    {
        // Enroll 10 researchers
        for ($i = 0; $i < 10; $i++) {
            self::$conn->query(
                "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
                 VALUES (3, 'Researcher', '{$i}', 'researcher{$i}@test.com', '\$2y\$10\$dummyhash', 'active')"
            );
            $userId = (int) self::$conn->insert_id;
            $this->enrollResearcher($userId, $this->programId);
        }

        $stats = $this->service->getProgramStatistics($this->programId);
        $this->assertTrue($stats['badges']['popular']);
    }

    public function testGetProgramStatisticsNoPopularBadgeWhenEnrolledLt10(): void
    {
        // Enroll 9 researchers
        for ($i = 0; $i < 9; $i++) {
            self::$conn->query(
                "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
                 VALUES (3, 'Researcher', '{$i}', 'researcher{$i}@test.com', '\$2y\$10\$dummyhash', 'active')"
            );
            $userId = (int) self::$conn->insert_id;
            $this->enrollResearcher($userId, $this->programId);
        }

        $stats = $this->service->getProgramStatistics($this->programId);
        $this->assertFalse($stats['badges']['popular']);
    }

    // --- getBulkProgramStatistics tests ---

    public function testGetBulkProgramStatisticsReturnsKeyedByProgramId(): void
    {
        // Create a second program
        self::$conn->query(
            "INSERT INTO programs (owner_id, title, description, scope, status)
             VALUES ({$this->ownerId}, 'Second Program', 'Desc', 'second.com', 'active')"
        );
        $secondProgramId = (int) self::$conn->insert_id;

        $results = $this->service->getBulkProgramStatistics([$this->programId, $secondProgramId]);

        $this->assertArrayHasKey($this->programId, $results);
        $this->assertArrayHasKey($secondProgramId, $results);
        $this->assertArrayHasKey('report_count', $results[$this->programId]);
        $this->assertArrayHasKey('report_count', $results[$secondProgramId]);
    }

    public function testGetBulkProgramStatisticsHandlesEmptyArray(): void
    {
        $results = $this->service->getBulkProgramStatistics([]);
        $this->assertEmpty($results);
    }

    // --- Helper methods ---

    private function insertReport(int $programId, int $researcherId, string $status): int
    {
        self::$conn->query(
            "INSERT INTO reports (program_id, researcher_id, title, description, steps_to_reproduce, impact, status)
             VALUES ({$programId}, {$researcherId}, 'Test Report', 'Desc', 'Steps', 'Impact', '{$status}')"
        );
        return (int) self::$conn->insert_id;
    }

    private function insertReportWithDates(
        int $programId,
        int $researcherId,
        string $status,
        string $createdAt,
        string $updatedAt
    ): int {
        $stmt = self::$conn->prepare(
            "INSERT INTO reports (program_id, researcher_id, title, description, steps_to_reproduce, impact, status, created_at, updated_at)
             VALUES (?, ?, 'Test Report', 'Desc', 'Steps', 'Impact', ?, ?, ?)"
        );
        $stmt->bind_param('iisss', $programId, $researcherId, $status, $createdAt, $updatedAt);
        $stmt->execute();
        $id = (int) self::$conn->insert_id;
        $stmt->close();
        return $id;
    }

    private function enrollResearcher(int $userId, int $programId): void
    {
        self::$conn->query(
            "INSERT INTO user_programs (user_id, program_id) VALUES ({$userId}, {$programId})"
        );
    }
}
