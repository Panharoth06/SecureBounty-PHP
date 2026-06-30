<?php

require_once __DIR__ . '/../model/repository/UserRepository.php';
require_once __DIR__ . '/../model/repository/ProgramRepository.php';
require_once __DIR__ . '/../model/repository/ActivityLogRepository.php';
require_once __DIR__ . '/../model/repository/NotificationRepository.php';
require_once __DIR__ . '/../model/services/ActivityLogService.php';
require_once __DIR__ . '/../model/services/NotificationService.php';
require_once __DIR__ . '/../model/services/ValidationService.php';
require_once __DIR__ . '/../middleware/AdminMiddleware.php';
require_once __DIR__ . '/HttpRedirect.php';

/**
 * AdminController
 *
 * Handles admin panel operations: user management, program oversight,
 * and activity log viewing. All actions require AdminMiddleware (role_id = 1).
 * Validates CSRF tokens on all state-changing actions.
 *
 * @see Requirement 10.1 — Paginated user listing for Admin
 * @see Requirement 10.2 — Deactivate user account
 * @see Requirement 10.3 — Reactivate user account
 * @see Requirement 10.4 — Change user role
 * @see Requirement 10.5 — Prevent admin from deactivating own account
 * @see Requirement 11.1 — View all programs with status filter
 * @see Requirement 11.2 — Suspend program
 * @see Requirement 11.3 — Reinstate suspended program
 * @see Requirement 11.4 — Admin dashboard with platform stats
 */
class AdminController
{
    private UserRepository $userRepository;
    private ProgramRepository $programRepository;
    private ActivityLogRepository $activityLogRepository;
    private ActivityLogService $activityLogService;
    private NotificationService $notificationService;
    private ValidationService $validationService;

    public function __construct()
    {
        $conn = require __DIR__ . '/../config/database.php';

        $this->userRepository = new UserRepository($conn);
        $this->programRepository = new ProgramRepository($conn);
        $this->activityLogRepository = new ActivityLogRepository($conn);
        $this->activityLogService = new ActivityLogService($this->activityLogRepository);
        $notificationRepository = new \NotificationRepository($conn);
        $this->notificationService = new NotificationService($notificationRepository);
        $this->validationService = new ValidationService($conn);
    }

    /**
     * Display admin dashboard with platform statistics.
     *
     * @return void
     */
    public function dashboard(): void
    {
        $this->applyMiddleware();

        $users = $this->userRepository->getAll(1000, 0);
        $totalUsers = count($users);

        $programs = $this->programRepository->getAll(null, 1000, 0);
        $totalPrograms = count($programs);

        $pendingReports = 0; // Placeholder — ReportRepository integration in later tasks

        $recentActivity = $this->activityLogRepository->getAll(10, 0);

        // Retrieve flash messages
        $success = $_SESSION['flash_success'] ?? null;
        $error = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $title = 'SecureBounty | Admin Dashboard';
        $activePage = 'admin-dashboard';

        include __DIR__ . '/../view/dashboard/admin.php';
    }

    /**
     * Display paginated user list with role and status information.
     *
     * @return void
     */
    public function userList(): void
    {
        $this->applyMiddleware();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $users = $this->userRepository->getAll($limit, $offset);

        $csrfToken = $this->validationService->generateCsrfToken(session_id());

        // Retrieve flash messages
        $success = $_SESSION['flash_success'] ?? null;
        $error = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $title = 'SecureBounty | User Management';
        $activePage = 'admin-users';

        include __DIR__ . '/../view/admin/users.php';
    }

    /**
     * Deactivate a user account.
     * POST action. Validates CSRF. Prevents self-deactivation.
     *
     * @return void
     */
    public function deactivateUser(): void
    {
        $this->applyMiddleware();

        // Validate CSRF token
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->validationService->validateCsrfToken($token, session_id())) {
            http_response_code(403);
            echo 'CSRF validation failed. Please try again.';
            return;
        }

        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        $adminUserId = (int) ($_SESSION['user_id'] ?? 0);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

        // Prevent admin from deactivating their own account
        if ($targetUserId === $adminUserId) {
            $_SESSION['flash_error'] = 'You cannot deactivate your own account.';
            redirectTo('index.php?page=admin-users');
        }

        // Verify target user exists
        $targetUser = $this->userRepository->findById($targetUserId);
        if ($targetUser === null) {
            $_SESSION['flash_error'] = 'User not found.';
            redirectTo('index.php?page=admin-users');
        }

        $this->userRepository->updateStatus($targetUserId, 'inactive');

        // Log the admin action
        $this->activityLogService->log(
            $adminUserId,
            'admin.user.deactivate',
            'user',
            $targetUserId,
            ['previous_status' => $targetUser['status']],
            $ipAddress
        );

        $_SESSION['flash_success'] = 'User account deactivated successfully.';
        redirectTo('index.php?page=admin-users');
    }

    /**
     * Reactivate a user account.
     * POST action. Validates CSRF.
     *
     * @return void
     */
    public function reactivateUser(): void
    {
        $this->applyMiddleware();

        // Validate CSRF token
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->validationService->validateCsrfToken($token, session_id())) {
            http_response_code(403);
            echo 'CSRF validation failed. Please try again.';
            return;
        }

        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        $adminUserId = (int) ($_SESSION['user_id'] ?? 0);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

        // Verify target user exists
        $targetUser = $this->userRepository->findById($targetUserId);
        if ($targetUser === null) {
            $_SESSION['flash_error'] = 'User not found.';
            redirectTo('index.php?page=admin-users');
        }

        $this->userRepository->updateStatus($targetUserId, 'active');

        // Log the admin action
        $this->activityLogService->log(
            $adminUserId,
            'admin.user.reactivate',
            'user',
            $targetUserId,
            ['previous_status' => $targetUser['status']],
            $ipAddress
        );

        $_SESSION['flash_success'] = 'User account reactivated successfully.';
        redirectTo('index.php?page=admin-users');
    }

    /**
     * Change a user's role assignment.
     * POST action. Validates CSRF.
     *
     * @return void
     */
    public function changeRole(): void
    {
        $this->applyMiddleware();

        // Validate CSRF token
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->validationService->validateCsrfToken($token, session_id())) {
            http_response_code(403);
            echo 'CSRF validation failed. Please try again.';
            return;
        }

        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        $newRoleId = (int) ($_POST['role_id'] ?? 0);
        $adminUserId = (int) ($_SESSION['user_id'] ?? 0);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

        // Verify target user exists
        $targetUser = $this->userRepository->findById($targetUserId);
        if ($targetUser === null) {
            $_SESSION['flash_error'] = 'User not found.';
            redirectTo('index.php?page=admin-users');
        }

        // Validate role_id is within expected range (1=Admin, 2=Program_Owner, 3=Researcher)
        if ($newRoleId < 1 || $newRoleId > 3) {
            $_SESSION['flash_error'] = 'Invalid role selection.';
            redirectTo('index.php?page=admin-users');
        }

        $previousRoleId = (int) $targetUser['role_id'];
        $this->userRepository->updateRole($targetUserId, $newRoleId);

        // Log the admin action with previous and new role
        $this->activityLogService->log(
            $adminUserId,
            'admin.user.change_role',
            'user',
            $targetUserId,
            [
                'previous_role_id' => $previousRoleId,
                'new_role_id' => $newRoleId,
            ],
            $ipAddress
        );

        $_SESSION['flash_success'] = 'User role updated successfully.';
        redirectTo('index.php?page=admin-users');
    }

    /**
     * Display all programs with optional status filter and pagination.
     *
     * @return void
     */
    public function programList(): void
    {
        $this->applyMiddleware();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $statusFilter = $_GET['status'] ?? null;

        // Sanitize status filter
        $allowedStatuses = ['draft', 'active', 'closed', 'suspended'];
        if ($statusFilter !== null && !in_array($statusFilter, $allowedStatuses, true)) {
            $statusFilter = null;
        }

        $programs = $this->programRepository->getAll($statusFilter, $limit, $offset);

        $csrfToken = $this->validationService->generateCsrfToken(session_id());

        // Retrieve flash messages
        $success = $_SESSION['flash_success'] ?? null;
        $error = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $title = 'SecureBounty | Program Oversight';
        $activePage = 'admin-programs';

        include __DIR__ . '/../view/admin/programs.php';
    }

    /**
     * Suspend a program.
     * POST action. Validates CSRF. Notifies program owner.
     *
     * @return void
     */
    public function suspendProgram(): void
    {
        $this->applyMiddleware();

        // Validate CSRF token
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->validationService->validateCsrfToken($token, session_id())) {
            http_response_code(403);
            echo 'CSRF validation failed. Please try again.';
            return;
        }

        $programId = (int) ($_POST['program_id'] ?? 0);
        $adminUserId = (int) ($_SESSION['user_id'] ?? 0);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

        // Verify program exists
        $program = $this->programRepository->findById($programId);
        if ($program === null) {
            $_SESSION['flash_error'] = 'Program not found.';
            redirectTo('index.php?page=admin-programs');
        }

        $this->programRepository->updateStatus($programId, 'suspended');

        // Log the admin action
        $this->activityLogService->log(
            $adminUserId,
            'admin.program.suspend',
            'program',
            $programId,
            ['previous_status' => $program['status']],
            $ipAddress
        );

        // Notify the program owner
        $this->notificationService->notify(
            (int) $program['owner_id'],
            'program.suspended',
            'program',
            $programId,
            'Your program "' . $program['title'] . '" has been suspended by an administrator.'
        );

        $_SESSION['flash_success'] = 'Program suspended successfully.';
        redirectTo('index.php?page=admin-programs');
    }

    /**
     * Reinstate a suspended program.
     * POST action. Validates CSRF. Notifies program owner.
     *
     * @return void
     */
    public function reinstateProgram(): void
    {
        $this->applyMiddleware();

        // Validate CSRF token
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->validationService->validateCsrfToken($token, session_id())) {
            http_response_code(403);
            echo 'CSRF validation failed. Please try again.';
            return;
        }

        $programId = (int) ($_POST['program_id'] ?? 0);
        $adminUserId = (int) ($_SESSION['user_id'] ?? 0);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

        // Verify program exists
        $program = $this->programRepository->findById($programId);
        if ($program === null) {
            $_SESSION['flash_error'] = 'Program not found.';
            redirectTo('index.php?page=admin-programs');
        }

        $this->programRepository->updateStatus($programId, 'active');

        // Log the admin action
        $this->activityLogService->log(
            $adminUserId,
            'admin.program.reinstate',
            'program',
            $programId,
            ['previous_status' => $program['status']],
            $ipAddress
        );

        // Notify the program owner
        $this->notificationService->notify(
            (int) $program['owner_id'],
            'program.reinstated',
            'program',
            $programId,
            'Your program "' . $program['title'] . '" has been reinstated by an administrator.'
        );

        $_SESSION['flash_success'] = 'Program reinstated successfully.';
        redirectTo('index.php?page=admin-programs');
    }

    /**
     * Display activity logs with filtering and pagination.
     *
     * @return void
     */
    public function activityLogs(): void
    {
        $this->applyMiddleware();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        // Collect filter parameters into array format expected by ActivityLogRepository
        $filters = [];

        if (isset($_GET['user_id']) && $_GET['user_id'] !== '') {
            $filters['user_id'] = (int) $_GET['user_id'];
        }

        if (isset($_GET['action']) && $_GET['action'] !== '') {
            $filters['action'] = $_GET['action'];
        }

        if (isset($_GET['date_from']) && $_GET['date_from'] !== '') {
            $filters['start_date'] = $_GET['date_from'] . ' 00:00:00';
        }

        if (isset($_GET['date_to']) && $_GET['date_to'] !== '') {
            $filters['end_date'] = $_GET['date_to'] . ' 23:59:59';
        }

        $logs = $this->activityLogRepository->getFiltered($filters, $limit, $offset);

        // Retrieve flash messages
        $success = $_SESSION['flash_success'] ?? null;
        $error = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $title = 'SecureBounty | Activity Logs';
        $activePage = 'admin-activity-logs';

        include __DIR__ . '/../view/admin/activity-logs.php';
    }

    /**
     * Apply AdminMiddleware to verify the current user is an authenticated admin.
     * Also ensures session is started.
     *
     * @return void
     */
    private function applyMiddleware(): void
    {
        $middleware = new AdminMiddleware();
        $middleware->handle();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
