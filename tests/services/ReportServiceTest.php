<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\TestDatabaseHelper;

require_once __DIR__ . '/../../model/repository/ReportRepository.php';
require_once __DIR__ . '/../../model/repository/UserProgramRepository.php';
require_once __DIR__ . '/../../model/repository/ProgramRepository.php';
require_once __DIR__ . '/../../model/repository/RewardPolicyRepository.php';
require_once __DIR__ . '/../../model/repository/NotificationRepository.php';
require_once __DIR__ . '/../../model/repository/ActivityLogRepository.php';
require_once __DIR__ . '/../../model/services/ReportService.php';
require_once __DIR__ . '/../../model/services/NotificationService.php';
require_once __DIR__ . '/../../model/services/ActivityLogService.php';

/**
 * Unit tests for ReportService.
 *
 * @covers ReportService
 */
class ReportServiceTest extends TestCase
{
    private static mysqli $conn;
    private ReportService $service;
    private ReportRepository $reportRepo;
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

        $this->reportRepo = new ReportRepository(self::$conn);
        $userProgramRepo = new UserProgramRepository(self::$conn);
        $programRepo = new ProgramRepository(self::$conn);
        $rewardPolicyRepo = new RewardPolicyRepository(self::$conn);
        $notificationRepo = new NotificationRepository(self::$conn);
        $activityLogRepo = new ActivityLogRepository(self::$conn);

        $notificationService = new NotificationService($notificationRepo);
        $activityLogService = new ActivityLogService($activityLogRepo);

        $this->service = new ReportService(
            $this->reportRepo,
            $userProgramRepo,
            $programRepo,
            $rewardPolicyRepo,
            $notificationService,
            $activityLogService
        );

        // Create a program owner (role_id = 2)
        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (2, 'Program', 'Owner', 'owner@test.com', '\$2y\$10\$dummyhash', 'active')"
        );
        $this->ownerId = (int) self::$conn->insert_id;

        // Create a researcher (role_id = 3)
        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (3, 'Sec', 'Researcher', 'researcher@test.com', '\$2y\$10\$dummyhash', 'active')"
        );
        $this->researcherId = (int) self::$conn->insert_id;

        // Create an active program
        self::$conn->query(
            "INSERT INTO programs (owner_id, title, description, scope, status)
             VALUES ({$this->ownerId}, 'Active Program', 'Program Description', 'example.com', 'active')"
        );
        $this->programId = (int) self::$conn->insert_id;

        // Enroll the researcher
        self::$conn->query(
            "INSERT INTO user_programs (user_id, program_id, enrolled_at)
             VALUES ({$this->researcherId}, {$this->programId}, NOW())"
        );
    }

    public static function tearDownAfterClass(): void
    {
        TestDatabaseHelper::cleanUp();
    }

    public function testSubmitReportCreatesReportWithPendingStatus(): void
    {
        $reportId = $this->service->submitReport(
            $this->researcherId,
            $this->programId,
            'XSS in Login',
            'Found reflected XSS',
            '1. Navigate to login page\n2. Insert payload',
            'Session hijacking',
            '127.0.0.1'
        );

        $this->assertGreaterThan(0, $reportId);

        $report = $this->reportRepo->findById($reportId);
        $this->assertSame('pending', $report['status']);
        $this->assertSame('XSS in Login', $report['title']);
    }

    public function testSubmitReportNotifiesProgramOwner(): void
    {
        $this->service->submitReport(
            $this->researcherId,
            $this->programId,
            'New Vuln',
            'Description',
            'Steps',
            'Impact',
            '127.0.0.1'
        );

        $result = self::$conn->query(
            "SELECT * FROM notifications WHERE user_id = {$this->ownerId} AND type = 'report.submitted'"
        );
        $notification = $result->fetch_assoc();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('New Vuln', $notification['message']);
    }

    public function testSubmitReportLogsActivity(): void
    {
        $this->service->submitReport(
            $this->researcherId,
            $this->programId,
            'Report Title',
            'Description',
            'Steps',
            'Impact',
            '192.168.1.1'
        );

        $result = self::$conn->query(
            "SELECT * FROM activity_logs WHERE action = 'report.submit' ORDER BY id DESC LIMIT 1"
        );
        $log = $result->fetch_assoc();

        $this->assertNotNull($log);
        $this->assertEquals($this->researcherId, $log['user_id']);
        $this->assertSame('report', $log['target_entity']);
        $this->assertSame('192.168.1.1', $log['ip_address']);
    }

    public function testSubmitReportRejectsWith403IfNotEnrolled(): void
    {
        // Create a second researcher who is NOT enrolled
        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (3, 'Other', 'Researcher', 'other@test.com', '\$2y\$10\$dummyhash', 'active')"
        );
        $otherResearcherId = (int) self::$conn->insert_id;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(403);

        $this->service->submitReport(
            $otherResearcherId,
            $this->programId,
            'Report',
            'Desc',
            'Steps',
            'Impact'
        );
    }

    public function testSubmitReportRejectsWith403IfProgramNotActive(): void
    {
        // Create a draft program
        self::$conn->query(
            "INSERT INTO programs (owner_id, title, description, scope, status)
             VALUES ({$this->ownerId}, 'Draft Program', 'Desc', 'Scope', 'draft')"
        );
        $draftProgramId = (int) self::$conn->insert_id;

        // Enroll researcher in the draft program
        self::$conn->query(
            "INSERT INTO user_programs (user_id, program_id, enrolled_at)
             VALUES ({$this->researcherId}, {$draftProgramId}, NOW())"
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(403);

        $this->service->submitReport(
            $this->researcherId,
            $draftProgramId,
            'Report',
            'Desc',
            'Steps',
            'Impact'
        );
    }

    public function testSubmitReportThrowsOnEmptyTitle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Report title is required.');

        $this->service->submitReport(
            $this->researcherId,
            $this->programId,
            '',
            'Description',
            'Steps',
            'Impact'
        );
    }

    public function testChangeStatusUpdatesReport(): void
    {
        $reportId = $this->service->submitReport(
            $this->researcherId,
            $this->programId,
            'Report',
            'Desc',
            'Steps',
            'Impact'
        );

        $result = $this->service->changeStatus($reportId, 'triaged', $this->ownerId, '127.0.0.1');

        $this->assertTrue($result);

        $report = $this->reportRepo->findById($reportId);
        $this->assertSame('triaged', $report['status']);
    }

    public function testChangeStatusNotifiesResearcher(): void
    {
        $reportId = $this->service->submitReport(
            $this->researcherId,
            $this->programId,
            'My Report',
            'Desc',
            'Steps',
            'Impact'
        );

        $this->service->changeStatus($reportId, 'triaged', $this->ownerId);

        $result = self::$conn->query(
            "SELECT * FROM notifications WHERE user_id = {$this->researcherId} AND type = 'report.status_change'"
        );
        $notification = $result->fetch_assoc();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('triaged', $notification['message']);
    }

    public function testChangeStatusThrowsOnInvalidStatus(): void
    {
        $reportId = $this->service->submitReport(
            $this->researcherId,
            $this->programId,
            'Report',
            'Desc',
            'Steps',
            'Impact'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid report status');

        $this->service->changeStatus($reportId, 'invalid_status', $this->ownerId);
    }

    public function testChangeStatusThrowsOnNonExistentReport(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Report not found.');

        $this->service->changeStatus(99999, 'triaged', $this->ownerId);
    }

    public function testAcceptReportSetsStatusToAccepted(): void
    {
        $reportId = $this->service->submitReport(
            $this->researcherId,
            $this->programId,
            'Critical Bug',
            'Desc',
            'Steps',
            'Impact'
        );

        // Add reward policy for 'high' severity
        self::$conn->query(
            "INSERT INTO reward_policies (program_id, severity, min_reward, max_reward)
             VALUES ({$this->programId}, 'high', 1000.00, 5000.00)"
        );

        $result = $this->service->acceptReport($reportId, 'high', $this->ownerId, '127.0.0.1');

        $this->assertTrue($result);

        $report = $this->reportRepo->findById($reportId);
        $this->assertSame('accepted', $report['status']);
        $this->assertSame('high', $report['final_severity']);
    }

    public function testAcceptReportAutoLinksRewardPolicy(): void
    {
        $reportId = $this->service->submitReport(
            $this->researcherId,
            $this->programId,
            'Critical Bug',
            'Desc',
            'Steps',
            'Impact'
        );

        // Add reward policy for 'critical' severity
        self::$conn->query(
            "INSERT INTO reward_policies (program_id, severity, min_reward, max_reward)
             VALUES ({$this->programId}, 'critical', 5000.00, 20000.00)"
        );
        $policyId = (int) self::$conn->insert_id;

        $this->service->acceptReport($reportId, 'critical', $this->ownerId);

        $report = $this->reportRepo->findById($reportId);
        $this->assertEquals($policyId, $report['reward_policy_id']);
    }

    public function testAcceptReportNotifiesResearcher(): void
    {
        $reportId = $this->service->submitReport(
            $this->researcherId,
            $this->programId,
            'Accepted Report',
            'Desc',
            'Steps',
            'Impact'
        );

        self::$conn->query(
            "INSERT INTO reward_policies (program_id, severity, min_reward, max_reward)
             VALUES ({$this->programId}, 'medium', 200.00, 1000.00)"
        );

        $this->service->acceptReport($reportId, 'medium', $this->ownerId);

        $result = self::$conn->query(
            "SELECT * FROM notifications
             WHERE user_id = {$this->researcherId}
             AND type = 'report.status_change'
             AND message LIKE '%accepted%'
             ORDER BY id DESC LIMIT 1"
        );
        $notification = $result->fetch_assoc();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('medium', $notification['message']);
    }

    public function testGetReportDetailReturnsReport(): void
    {
        $reportId = $this->service->submitReport(
            $this->researcherId,
            $this->programId,
            'Detail Report',
            'Full description',
            'Reproduction steps',
            'High impact'
        );

        $detail = $this->service->getReportDetail($reportId);

        $this->assertNotNull($detail);
        $this->assertSame('Detail Report', $detail['title']);
        $this->assertSame('Sec', $detail['researcher_first_name']);
        $this->assertSame('Active Program', $detail['program_title']);
    }

    public function testGetReportDetailReturnsNullForNonExistent(): void
    {
        $detail = $this->service->getReportDetail(99999);
        $this->assertNull($detail);
    }
}
