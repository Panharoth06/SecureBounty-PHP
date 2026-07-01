<?php

require_once __DIR__ . '/../model/repository/LeaderboardRepository.php';
require_once __DIR__ . '/../model/services/LeaderboardService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

/**
 * LeaderboardController
 *
 * Displays the paginated researcher leaderboard with reputation scores,
 * severity breakdowns, and the current user's rank.
 * Accessible to all authenticated users regardless of role.
 *
 * @see Requirement 3.3 — Display researchers ranked by Reputation_Score descending
 * @see Requirement 3.4 — Display rank, display name, score, accepted count, severity breakdown
 * @see Requirement 3.5 — Paginate leaderboard with 25 entries per page
 * @see Requirement 3.8 — Display current user's rank and score
 * @see Requirement 3.9 — Display "Unranked" for zero accepted reports
 * @see Requirement 3.10 — Accessible to all authenticated users
 */
class LeaderboardController
{
    private LeaderboardRepository $leaderboardRepository;
    private LeaderboardService $leaderboardService;

    public function __construct()
    {
        $conn = require __DIR__ . '/../config/database.php';

        $this->leaderboardRepository = new LeaderboardRepository($conn);
        $this->leaderboardService = new LeaderboardService($this->leaderboardRepository);
    }

    /**
     * Display the paginated leaderboard page.
     *
     * Shows researchers ranked by reputation score with severity breakdowns.
     * Includes the current authenticated user's rank position.
     *
     * @return void
     *
     * @see Requirement 3.3 — Ranked by Reputation_Score descending
     * @see Requirement 3.4 — Show rank, display name, score, accepted count, severity breakdown
     * @see Requirement 3.5 — 25 entries per page
     * @see Requirement 3.8 — Show current user's rank
     * @see Requirement 3.9 — "Unranked" for zero accepted reports
     * @see Requirement 3.10 — All authenticated users can view
     */
    public function index(): void
    {
        (new AuthMiddleware())->handle();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $page = (int) ($_GET['page'] ?? 1);
        $page = max(1, $page);

        $leaderboardData = $this->leaderboardService->getLeaderboard($page, 25);

        $entries = $leaderboardData['entries'];
        $total = $leaderboardData['total'];
        $currentPage = $leaderboardData['page'];
        $totalPages = $leaderboardData['totalPages'];

        // Get the current user's rank
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $currentUserRank = $this->leaderboardService->getResearcherRank($userId);

        $title = 'SecureBounty | Leaderboard';
        $activePage = 'leaderboard';

        include __DIR__ . '/../view/leaderboard/index.php';
    }
}
