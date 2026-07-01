<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\TestDatabaseHelper;

require_once __DIR__ . '/../../model/repository/LeaderboardRepository.php';

/**
 * Unit tests for LeaderboardRepository.
 *
 * @covers LeaderboardRepository
 */
class LeaderboardRepositoryTest extends TestCase
{
    private static mysqli $conn;
    private LeaderboardRepository $repo;
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

        $this->repo = new LeaderboardRepository(self::$conn);

        // Create a program owner
        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
             VALUES (2, 'Program', 'Owner', 'owner@test.com', '\$2y\$10\$dummyhash', 'active')"
        );
        $this->ownerId = (int) self::$conn->insert_id;

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

    /**
     * Helper to create a researcher user and return their ID.
     */
    private function createResearcher(string $firstName, string $lastName, string $email, int $score = 0, ?string $earliestAccepted = null): int
    {
        $escapedEarliest = $earliestAccepted !== null ? "'{$earliestAccepted}'" : 'NULL';
        self::$conn->query(
            "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status, reputation_score, earliest_accepted_at)
             VALUES (3, '{$firstName}', '{$lastName}', '{$email}', '\$2y\$10\$dummyhash', 'active', {$score}, {$escapedEarliest})"
        );
        return (int) self::$conn->insert_id;
    }

    /**
     * Helper to create an accepted report with final_severity for a researcher.
     */
    private function createAcceptedReport(int $researcherId, string $severity): int
    {
        self::$conn->query(
            "INSERT INTO reports (program_id, researcher_id, title, description, steps_to_reproduce, impact, status, final_severity)
             VALUES ({$this->programId}, {$researcherId}, 'Test Report', 'Desc', 'Steps', 'Impact', 'accepted', '{$severity}')"
        );
        return (int) self::$conn->insert_id;
    }

    public function testGetLeaderboardExcludesZeroScore(): void
    {
        $this->createResearcher('Zero', 'Score', 'zero@test.com', 0);
        $this->createResearcher('Ranked', 'User', 'ranked@test.com', 50, '2024-01-01 00:00:00');

        $results = $this->repo->getLeaderboard(25, 0);

        $this->assertCount(1, $results);
        $this->assertSame('Ranked', $results[0]['first_name']);
    }

    public function testGetLeaderboardOrdersByScoreDescThenEarliestAcceptedAsc(): void
    {
        $this->createResearcher('Low', 'Score', 'low@test.com', 10, '2024-01-01 00:00:00');
        $this->createResearcher('High', 'Score', 'high@test.com', 100, '2024-03-01 00:00:00');
        $this->createResearcher('Mid', 'Score', 'mid@test.com', 50, '2024-02-01 00:00:00');

        $results = $this->repo->getLeaderboard(25, 0);

        $this->assertCount(3, $results);
        $this->assertSame('High', $results[0]['first_name']);
        $this->assertSame('Mid', $results[1]['first_name']);
        $this->assertSame('Low', $results[2]['first_name']);
    }

    public function testGetLeaderboardTieBreakingByEarliestAccepted(): void
    {
        $this->createResearcher('Later', 'User', 'later@test.com', 50, '2024-06-01 00:00:00');
        $this->createResearcher('Earlier', 'User', 'earlier@test.com', 50, '2024-01-01 00:00:00');

        $results = $this->repo->getLeaderboard(25, 0);

        $this->assertCount(2, $results);
        $this->assertSame('Earlier', $results[0]['first_name']);
        $this->assertSame('Later', $results[1]['first_name']);
    }

    public function testGetLeaderboardRespectsLimitAndOffset(): void
    {
        $this->createResearcher('User1', 'A', 'u1@test.com', 100, '2024-01-01 00:00:00');
        $this->createResearcher('User2', 'B', 'u2@test.com', 90, '2024-01-01 00:00:00');
        $this->createResearcher('User3', 'C', 'u3@test.com', 80, '2024-01-01 00:00:00');

        $results = $this->repo->getLeaderboard(2, 0);
        $this->assertCount(2, $results);
        $this->assertSame('User1', $results[0]['first_name']);

        $results = $this->repo->getLeaderboard(2, 2);
        $this->assertCount(1, $results);
        $this->assertSame('User3', $results[0]['first_name']);
    }

    public function testGetTotalRankedCountExcludesZeroScore(): void
    {
        $this->createResearcher('Zero', 'Score', 'zero@test.com', 0);
        $this->createResearcher('Ranked1', 'User', 'ranked1@test.com', 50, '2024-01-01 00:00:00');
        $this->createResearcher('Ranked2', 'User', 'ranked2@test.com', 30, '2024-02-01 00:00:00');

        $count = $this->repo->getTotalRankedCount();

        $this->assertSame(2, $count);
    }

    public function testGetTotalRankedCountReturnsZeroWhenNoRanked(): void
    {
        $this->createResearcher('Zero', 'Score', 'zero@test.com', 0);

        $count = $this->repo->getTotalRankedCount();

        $this->assertSame(0, $count);
    }

    public function testGetResearcherRankReturnsCorrectPosition(): void
    {
        $id1 = $this->createResearcher('First', 'Place', 'first@test.com', 100, '2024-01-01 00:00:00');
        $id2 = $this->createResearcher('Second', 'Place', 'second@test.com', 50, '2024-02-01 00:00:00');
        $id3 = $this->createResearcher('Third', 'Place', 'third@test.com', 25, '2024-03-01 00:00:00');

        $this->assertSame(1, $this->repo->getResearcherRank($id1));
        $this->assertSame(2, $this->repo->getResearcherRank($id2));
        $this->assertSame(3, $this->repo->getResearcherRank($id3));
    }

    public function testGetResearcherRankReturnsNullForZeroScore(): void
    {
        $id = $this->createResearcher('Unranked', 'User', 'unranked@test.com', 0);

        $this->assertNull($this->repo->getResearcherRank($id));
    }

    public function testGetResearcherRankTieBreaking(): void
    {
        $id1 = $this->createResearcher('Earlier', 'User', 'earlier@test.com', 50, '2024-01-01 00:00:00');
        $id2 = $this->createResearcher('Later', 'User', 'later@test.com', 50, '2024-06-01 00:00:00');

        $this->assertSame(1, $this->repo->getResearcherRank($id1));
        $this->assertSame(2, $this->repo->getResearcherRank($id2));
    }

    public function testGetResearcherScoreDataReturnsData(): void
    {
        $id = $this->createResearcher('Test', 'User', 'test@test.com', 75, '2024-03-15 10:00:00');
        $this->createAcceptedReport($id, 'critical');
        $this->createAcceptedReport($id, 'high');

        $data = $this->repo->getResearcherScoreData($id);

        $this->assertNotNull($data);
        $this->assertEquals(75, $data['reputation_score']);
        $this->assertSame('2024-03-15 10:00:00', $data['earliest_accepted_at']);
        $this->assertEquals(2, $data['accepted_count']);
    }

    public function testGetResearcherScoreDataReturnsNullForNonExistentUser(): void
    {
        $data = $this->repo->getResearcherScoreData(99999);

        $this->assertNull($data);
    }

    public function testGetSeverityBreakdownReturnsGroupedCounts(): void
    {
        $id = $this->createResearcher('Test', 'User', 'test@test.com', 100, '2024-01-01 00:00:00');
        $this->createAcceptedReport($id, 'critical');
        $this->createAcceptedReport($id, 'critical');
        $this->createAcceptedReport($id, 'high');
        $this->createAcceptedReport($id, 'low');

        $breakdown = $this->repo->getSeverityBreakdown($id);

        // Index by severity for easy assertions
        $bySeverity = [];
        foreach ($breakdown as $row) {
            $bySeverity[$row['final_severity']] = (int) $row['count'];
        }

        $this->assertSame(2, $bySeverity['critical']);
        $this->assertSame(1, $bySeverity['high']);
        $this->assertSame(1, $bySeverity['low']);
        $this->assertArrayNotHasKey('medium', $bySeverity);
        $this->assertArrayNotHasKey('informational', $bySeverity);
    }

    public function testGetSeverityBreakdownReturnsEmptyForNoReports(): void
    {
        $id = $this->createResearcher('Test', 'User', 'test@test.com', 0);

        $breakdown = $this->repo->getSeverityBreakdown($id);

        $this->assertEmpty($breakdown);
    }

    public function testUpdateReputationScoreUpdatesUserScore(): void
    {
        $id = $this->createResearcher('Test', 'User', 'test@test.com', 0);

        $this->repo->updateReputationScore($id, 150);

        $row = self::$conn->query("SELECT reputation_score FROM users WHERE id = {$id}")->fetch_assoc();
        $this->assertEquals(150, $row['reputation_score']);
    }

    public function testGetEarliestAcceptedDateReturnsDate(): void
    {
        $id = $this->createResearcher('Test', 'User', 'test@test.com', 50, '2024-01-01 00:00:00');

        // Create reports with different timestamps
        self::$conn->query(
            "INSERT INTO reports (program_id, researcher_id, title, description, steps_to_reproduce, impact, status, final_severity, updated_at)
             VALUES ({$this->programId}, {$id}, 'Report 1', 'Desc', 'Steps', 'Impact', 'accepted', 'high', '2024-03-15 10:00:00')"
        );
        self::$conn->query(
            "INSERT INTO reports (program_id, researcher_id, title, description, steps_to_reproduce, impact, status, final_severity, updated_at)
             VALUES ({$this->programId}, {$id}, 'Report 2', 'Desc', 'Steps', 'Impact', 'accepted', 'medium', '2024-01-10 08:00:00')"
        );

        $date = $this->repo->getEarliestAcceptedDate($id);

        $this->assertSame('2024-01-10 08:00:00', $date);
    }

    public function testGetEarliestAcceptedDateReturnsNullForNoAcceptedReports(): void
    {
        $id = $this->createResearcher('Test', 'User', 'test@test.com', 0);

        // Create a pending report (not accepted)
        self::$conn->query(
            "INSERT INTO reports (program_id, researcher_id, title, description, steps_to_reproduce, impact, status)
             VALUES ({$this->programId}, {$id}, 'Pending Report', 'Desc', 'Steps', 'Impact', 'pending')"
        );

        $date = $this->repo->getEarliestAcceptedDate($id);

        $this->assertNull($date);
    }
}
