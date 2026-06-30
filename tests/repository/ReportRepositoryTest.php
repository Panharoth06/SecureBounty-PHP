<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\TestDatabaseHelper;

require_once __DIR__ . '/../../model/repository/ReportRepository.php';

/**
 * Unit tests for ReportRepository.
 *
 * @covers ReportRepository
 */
class ReportRepositoryTest extends TestCase
{
    private static mysqli $conn;
    private ReportRepository $repo;
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

        $this->repo = new ReportRepository(self::$conn);

        // Create a program owner
        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (2, 'Program', 'Owner', 'owner@test.com', '\$2y\$10\$dummyhash', 'active')"
        );
        $this->ownerId = (int) self::$conn->insert_id;

        // Create a researcher
        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (3, 'Sec', 'Researcher', 'researcher@test.com', '\$2y\$10\$dummyhash', 'active')"
        );
        $this->researcherId = (int) self::$conn->insert_id;

        // Create a program
        self::$conn->query(
            "INSERT INTO programs (owner_id, title, description, scope, status)
             VALUES ({$this->ownerId}, 'Test Program', 'Desc', 'Scope', 'active')"
        );
        $this->programId = (int) self::$conn->insert_id;
    }

    public static function tearDownAfterClass(): void
    {
        TestDatabaseHelper::cleanUp();
    }

    public function testCreateReturnsNewIdWithPendingStatus(): void
    {
        $id = $this->repo->create(
            $this->programId,
            $this->researcherId,
            'XSS Vulnerability',
            'Found XSS in login form',
            '1. Go to login\n2. Enter <script>',
            'Account takeover possible'
        );

        $this->assertGreaterThan(0, $id);

        $report = $this->repo->findById($id);
        $this->assertNotNull($report);
        $this->assertSame('pending', $report['status']);
        $this->assertSame('XSS Vulnerability', $report['title']);
    }

    public function testFindByIdReturnsNullForNonExistent(): void
    {
        $result = $this->repo->findById(99999);
        $this->assertNull($result);
    }

    public function testFindByIdReturnsJoinedData(): void
    {
        $id = $this->repo->create(
            $this->programId,
            $this->researcherId,
            'SQL Injection',
            'Found SQLi',
            'Steps',
            'Data leak'
        );

        $report = $this->repo->findById($id);

        $this->assertNotNull($report);
        $this->assertSame('Sec', $report['researcher_first_name']);
        $this->assertSame('Researcher', $report['researcher_last_name']);
        $this->assertSame('Test Program', $report['program_title']);
        $this->assertEquals($this->ownerId, $report['program_owner_id']);
    }

    public function testFindByProgramIdReturnsAllProgramReports(): void
    {
        $this->repo->create($this->programId, $this->researcherId, 'Report 1', 'Desc', 'Steps', 'Impact');
        $this->repo->create($this->programId, $this->researcherId, 'Report 2', 'Desc', 'Steps', 'Impact');

        $reports = $this->repo->findByProgramId($this->programId);

        $this->assertCount(2, $reports);
        $this->assertArrayHasKey('researcher_first_name', $reports[0]);
    }

    public function testFindByResearcherIdReturnsAllResearcherReports(): void
    {
        $this->repo->create($this->programId, $this->researcherId, 'Report A', 'Desc', 'Steps', 'Impact');

        $reports = $this->repo->findByResearcherId($this->researcherId);

        $this->assertCount(1, $reports);
        $this->assertArrayHasKey('program_title', $reports[0]);
        $this->assertSame('Test Program', $reports[0]['program_title']);
    }

    public function testUpdateStatusChangesReportStatus(): void
    {
        $id = $this->repo->create($this->programId, $this->researcherId, 'Report', 'Desc', 'Steps', 'Impact');

        $affected = $this->repo->updateStatus($id, 'triaged');

        $this->assertSame(1, $affected);

        $report = $this->repo->findById($id);
        $this->assertSame('triaged', $report['status']);
    }

    public function testSetRewardPolicyLinksPolicy(): void
    {
        $id = $this->repo->create($this->programId, $this->researcherId, 'Report', 'Desc', 'Steps', 'Impact');

        // Create a reward policy
        self::$conn->query(
            "INSERT INTO reward_policies (program_id, severity, min_reward, max_reward)
             VALUES ({$this->programId}, 'high', 500.00, 5000.00)"
        );
        $policyId = (int) self::$conn->insert_id;

        $affected = $this->repo->setRewardPolicy($id, $policyId);

        $this->assertSame(1, $affected);

        $report = $this->repo->findById($id);
        $this->assertEquals($policyId, $report['reward_policy_id']);
    }

    public function testGetByProgramGroupedByStatusReturnsAllStatusKeys(): void
    {
        $this->repo->create($this->programId, $this->researcherId, 'Pending Report', 'Desc', 'Steps', 'Impact');
        $id2 = $this->repo->create($this->programId, $this->researcherId, 'Triaged Report', 'Desc', 'Steps', 'Impact');
        $this->repo->updateStatus($id2, 'triaged');

        $grouped = $this->repo->getByProgramGroupedByStatus($this->programId);

        $this->assertArrayHasKey('pending', $grouped);
        $this->assertArrayHasKey('triaged', $grouped);
        $this->assertArrayHasKey('accepted', $grouped);
        $this->assertArrayHasKey('rejected', $grouped);
        $this->assertArrayHasKey('resolved', $grouped);

        $this->assertCount(1, $grouped['pending']);
        $this->assertCount(1, $grouped['triaged']);
        $this->assertCount(0, $grouped['accepted']);
    }
}
