<?php

require_once __DIR__ . '/../model/repository/ProgramRepository.php';
require_once __DIR__ . '/../model/repository/ReportRepository.php';
require_once __DIR__ . '/../model/repository/UserProgramRepository.php';
require_once __DIR__ . '/../model/repository/SavedProgramRepository.php';
require_once __DIR__ . '/../model/repository/NotificationRepository.php';
require_once __DIR__ . '/../model/repository/ActivityLogRepository.php';
require_once __DIR__ . '/../model/repository/UserRepository.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/HttpRedirect.php';

/**
 * DashboardController
 *
 * Renders the role-specific dashboard with data gathered from repositories.
 * Dispatches by the authenticated user's role.
 *
 * @see Requirement 13.1 — Admin dashboard
 * @see Requirement 13.2 — Program Owner dashboard
 * @see Requirement 13.3 — Researcher dashboard
 * @see Requirement 13.4 — Load only the authenticated user's data
 */
class DashboardController
{
    private mysqli $conn;
    private ProgramRepository $programRepository;
    private ReportRepository $reportRepository;
    private UserProgramRepository $userProgramRepository;
    private SavedProgramRepository $savedProgramRepository;
    private NotificationRepository $notificationRepository;
    private ActivityLogRepository $activityLogRepository;
    private UserRepository $userRepository;

    public function __construct()
    {
        $conn = require __DIR__ . '/../config/database.php';
        $this->conn = $conn;
        $this->programRepository = new ProgramRepository($conn);
        $this->reportRepository = new ReportRepository($conn);
        $this->userProgramRepository = new UserProgramRepository($conn);
        $this->savedProgramRepository = new SavedProgramRepository($conn);
        $this->notificationRepository = new NotificationRepository($conn);
        $this->activityLogRepository = new ActivityLogRepository($conn);
        $this->userRepository = new UserRepository($conn);
    }

    /**
     * Dispatch to the dashboard matching the authenticated user's role.
     *
     * @return void
     */
    public function index(): void
    {
        (new AuthMiddleware())->handle();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $roleId = (int) ($_SESSION['role_id'] ?? 0);

        switch ($roleId) {
            case 1:
                $this->admin();
                break;
            case 2:
                $this->programOwner();
                break;
            case 3:
                $this->researcher();
                break;
            default:
                redirectTo('index.php?page=login');
        }
    }

    /**
     * Admin dashboard: platform-wide stats and recent activity.
     */
    private function admin(): void
    {
        $totalUsers = count($this->userRepository->getAll(100000, 0));
        $allPrograms = $this->programRepository->getAll(null, 100000, 0);
        $totalPrograms = count($allPrograms);

        $pendingReportsList = $this->decoratePendingAcrossPrograms($allPrograms);
        $pendingReports = count($pendingReportsList);

        $recentActivity = array_map(static function (array $row): array {
            return [
                'user_name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                'action' => $row['action'] ?? '',
                'target_entity' => $row['target_entity'] ?? '',
                'created_at' => $row['created_at'] ?? '',
            ];
        }, $this->activityLogRepository->getAll(10, 0));

        $title = 'SecureBounty | Admin Dashboard';
        $activePage = 'dashboard';
        include __DIR__ . '/../view/dashboard/admin.php';
    }

    /**
     * Program owner dashboard: owned programs, pending reports, recent comments.
     */
    private function programOwner(): void
    {
        $ownerId = (int) ($_SESSION['user_id'] ?? 0);
        $programs = $this->programRepository->findByOwnerId($ownerId);

        $ownedPrograms = count($programs);
        $pendingReportsList = $this->decoratePendingAcrossPrograms($programs);
        $pendingReports = count($pendingReportsList);

        // All reports across owned programs (decorated with program title and status)
        $allReportsList = [];
        foreach ($programs as $program) {
            foreach ($this->reportRepository->findByProgramId((int) $program['id']) as $report) {
                $allReportsList[] = [
                    'id' => (int) $report['id'],
                    'title' => $report['title'] ?? '',
                    'program_title' => $program['title'] ?? '',
                    'status' => $report['status'] ?? 'pending',
                    'severity' => $report['final_severity'] ?? $report['cvss_severity'] ?? null,
                    'researcher_name' => trim(($report['researcher_first_name'] ?? '') . ' ' . ($report['researcher_last_name'] ?? '')),
                    'created_at' => $report['created_at'] ?? '',
                ];
            }
        }
        $totalReports = count($allReportsList);

        $recentComments = $this->recentCommentsForOwner($ownerId);

        $title = 'SecureBounty | Dashboard';
        $activePage = 'dashboard';
        include __DIR__ . '/../view/dashboard/program-owner.php';
    }

    /**
     * Researcher dashboard: enrolled programs, submitted reports, saved programs, notifications.
     */
    private function researcher(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $enrolledPrograms = count($this->userProgramRepository->getByUserId($userId));
        $submittedReports = count($this->reportRepository->findByResearcherId($userId));
        $savedPrograms = count($this->savedProgramRepository->getSavedByUserId($userId));
        $notifications = $this->notificationRepository->getByUserId($userId, 10, 0);

        $title = 'SecureBounty | Dashboard';
        $activePage = 'dashboard';
        include __DIR__ . '/../view/dashboard/researcher.php';
    }

    /**
     * Build a decorated list of pending reports across a set of programs.
     *
     * @param array $programs Program rows (must contain id, title).
     * @return array List of [id, title, program_title, severity, created_at].
     */
    private function decoratePendingAcrossPrograms(array $programs): array
    {
        $pending = [];
        foreach ($programs as $program) {
            $grouped = $this->reportRepository->getByProgramGroupedByStatus((int) $program['id']);
            foreach ($grouped['pending'] ?? [] as $report) {
                $pending[] = [
                    'id' => (int) $report['id'],
                    'title' => $report['title'] ?? '',
                    'program_title' => $program['title'] ?? '',
                    'severity' => $report['final_severity'] ?? $report['cvss_severity'] ?? 'medium',
                    'created_at' => $report['created_at'] ?? '',
                ];
            }
        }
        return $pending;
    }

    /**
     * Fetch recent comments on the owner's programs (from program_comments).
     *
     * @param int $ownerId
     * @return array List of [body, user_name, program_title, created_at].
     */
    private function recentCommentsForOwner(int $ownerId): array
    {
        $sql = 'SELECT pc.body, pc.created_at, p.title AS program_title,
                       u.first_name, u.last_name
                FROM program_comments pc
                INNER JOIN programs p ON pc.program_id = p.id
                INNER JOIN users u ON pc.user_id = u.id
                WHERE p.owner_id = ? AND pc.user_id != ?
                ORDER BY pc.created_at DESC
                LIMIT 5';

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return [];
        }
        $stmt->bind_param('ii', $ownerId, $ownerId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        return array_map(static function (array $row): array {
            return [
                'body' => $row['body'] ?? '',
                'user_name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                'program_title' => $row['program_title'] ?? '',
                'created_at' => $row['created_at'] ?? '',
            ];
        }, $rows);
    }
}
