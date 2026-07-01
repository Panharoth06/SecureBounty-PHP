<?php
/**
 * SecureBounty - Web-Based Bug Bounty Management Platform
 * Main entry point and front controller router.
 *
 * Dispatches requests to appropriate controllers based on the 'page' query parameter.
 * Controllers handle their own middleware invocation for protected routes.
 *
 * @see Requirement 2.4 — Role-based access control on all protected routes
 * @see Requirement 3.1 — Three distinct roles enforced
 * @see Requirement 3.2 — Admin access to management features
 * @see Requirement 3.3 — Program_Owner access to program/report features
 * @see Requirement 3.4 — Researcher access to browsing/submission features
 * @see Requirement 3.5 — HTTP 403 on unauthorized route access
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require controller classes
require_once __DIR__ . '/controller/PageController.php';
require_once __DIR__ . '/controller/UserController.php';
require_once __DIR__ . '/controller/ProgramController.php';
require_once __DIR__ . '/controller/ReportController.php';
require_once __DIR__ . '/controller/CommentController.php';
require_once __DIR__ . '/controller/AdminController.php';
require_once __DIR__ . '/controller/RewardPolicyController.php';
require_once __DIR__ . '/controller/DashboardController.php';
require_once __DIR__ . '/controller/LeaderboardController.php';
require_once __DIR__ . '/controller/AssetController.php';
require_once __DIR__ . '/controller/TagController.php';

// Determine target page parameter (defaults to home)
$page = filter_input(INPUT_GET, 'page', FILTER_UNSAFE_RAW) ?? 'home';

// Sanitize parameter to avoid directory traversal
$page = preg_replace('/[^a-zA-Z0-9_-]/', '', $page);

// Router dispatch
switch ($page) {
    // =========================================================
    // Public pages (PageController)
    // =========================================================
    case 'home':
        $controller = new PageController();
        $controller->home();
        break;
    case 'about':
        $controller = new PageController();
        $controller->about();
        break;
    case 'contact':
        $controller = new PageController();
        $controller->contact();
        break;
    case 'docs':
        $controller = new PageController();
        $controller->docs();
        break;

    // =========================================================
    // Authentication and user routes (UserController)
    // =========================================================
    case 'login':
        $controller = new UserController();
        $controller->login();
        break;
    case 'register':
        $controller = new UserController();
        $controller->register();
        break;
    case 'process-register':
        $controller = new UserController();
        $controller->processRegister();
        break;
    case 'process-login':
        $controller = new UserController();
        $controller->processLogin();
        break;
    case 'logout':
        $controller = new UserController();
        $controller->logout();
        break;
    case 'profile':
        $controller = new UserController();
        $controller->profile();
        break;
    case 'profile-edit':
        $controller = new UserController();
        $controller->editProfile();
        break;
    case 'process-profile-edit':
        $controller = new UserController();
        $controller->processEditProfile();
        break;
    case 'public-profile':
        $controller = new UserController();
        $controller->publicProfile();
        break;

    // =========================================================
    // Dashboard — role-specific routing
    // =========================================================
    case 'dashboard':
        $roleId = $_SESSION['role_id'] ?? null;
        if ($roleId === 1) {
            // Admin dashboard
            $controller = new AdminController();
            $controller->dashboard();
        } elseif ($roleId === 2 || $roleId === 3) {
            // Program Owner / Researcher dashboards
            $controller = new DashboardController();
            $controller->index();
        } else {
            // Not authenticated — redirect to login
            header('Location: index.php?page=login');
            exit;
        }
        break;

    // =========================================================
    // Leaderboard (LeaderboardController)
    // =========================================================
    case 'leaderboard':
        $controller = new LeaderboardController();
        $controller->index();
        break;

    // =========================================================
    // Program routes (ProgramController)
    // =========================================================
    case 'programs':
        $controller = new ProgramController();
        $controller->list();
        break;
    case 'program-detail':
        $controller = new ProgramController();
        $controller->detail();
        break;
    case 'program-create':
        $controller = new ProgramController();
        $controller->create();
        break;
    case 'process-program-create':
        $controller = new ProgramController();
        $controller->processCreate();
        break;
    case 'program-edit':
        $controller = new ProgramController();
        $controller->edit();
        break;
    case 'process-program-edit':
        $controller = new ProgramController();
        $controller->processEdit();
        break;
    case 'program-publish':
        $controller = new ProgramController();
        $controller->publish();
        break;
    case 'program-close':
        $controller = new ProgramController();
        $controller->close();
        break;
    case 'program-enroll':
        $controller = new ProgramController();
        $controller->enroll();
        break;
    case 'program-save':
        $controller = new ProgramController();
        $controller->saveProgram();
        break;
    case 'program-unsave':
        $controller = new ProgramController();
        $controller->unsaveProgram();
        break;
    case 'saved-programs':
        $controller = new ProgramController();
        $controller->savedPrograms();
        break;
    case 'program-add-comment':
        $controller = new ProgramController();
        $controller->addProgramComment();
        break;
    case 'program-add-reply':
        $controller = new ProgramController();
        $controller->addProgramReply();
        break;

    // =========================================================
    // Program asset routes (AssetController)
    // =========================================================
    case 'asset-add':
        $controller = new AssetController();
        $controller->add();
        break;
    case 'asset-update':
        $controller = new AssetController();
        $controller->update();
        break;
    case 'asset-delete':
        $controller = new AssetController();
        $controller->delete();
        break;

    // =========================================================
    // Program tag routes (TagController)
    // =========================================================
    case 'tag-add':
        $controller = new TagController();
        $controller->add();
        break;
    case 'tag-remove':
        $controller = new TagController();
        $controller->remove();
        break;
    case 'tag-search':
        $controller = new TagController();
        $controller->search();
        break;

    // =========================================================
    // Reward policy routes (RewardPolicyController)
    // =========================================================
    case 'reward-policy-create':
        $controller = new RewardPolicyController();
        $controller->create();
        break;
    case 'process-reward-policy-create':
        $controller = new RewardPolicyController();
        $controller->processCreate();
        break;
    case 'reward-policy-edit':
        $controller = new RewardPolicyController();
        $controller->edit();
        break;
    case 'process-reward-policy-edit':
        $controller = new RewardPolicyController();
        $controller->processEdit();
        break;
    case 'reward-policy-delete':
        $controller = new RewardPolicyController();
        $controller->delete();
        break;

    // =========================================================
    // Report routes (ReportController)
    // =========================================================
    case 'reports':
    case 'my-reports':
        $controller = new ReportController();
        $controller->list();
        break;
    case 'report-submit':
        $controller = new ReportController();
        $controller->submit();
        break;
    case 'process-report-submit':
        $controller = new ReportController();
        $controller->processSubmit();
        break;
    case 'report-detail':
        $controller = new ReportController();
        $controller->detail();
        break;
    case 'report-change-status':
        $controller = new ReportController();
        $controller->changeStatus();
        break;
    case 'report-upload-attachment':
        $controller = new ReportController();
        $controller->uploadAttachment();
        break;
    case 'download-attachment':
        $controller = new ReportController();
        $controller->downloadAttachment();
        break;
    case 'report-edit-cvss':
        $controller = new ReportController();
        $controller->editCvss();
        break;
    case 'report-set-severity':
        $controller = new ReportController();
        $controller->setFinalSeverity();
        break;
    case 'report-edit':
        $controller = new ReportController();
        $controller->edit();
        break;
    case 'process-report-edit':
        $controller = new ReportController();
        $controller->processEdit();
        break;
    case 'report-delete':
        $controller = new ReportController();
        $controller->deleteReport();
        break;

    // =========================================================
    // Comment routes (CommentController)
    // =========================================================
    case 'add-comment':
        $controller = new CommentController();
        $controller->addComment();
        break;
    case 'add-reply':
        $controller = new CommentController();
        $controller->addReply();
        break;

    // =========================================================
    // Admin panel routes (AdminController)
    // =========================================================
    case 'admin-dashboard':
        $controller = new AdminController();
        $controller->dashboard();
        break;
    case 'admin-users':
        $controller = new AdminController();
        $controller->userList();
        break;
    case 'admin-deactivate-user':
        $controller = new AdminController();
        $controller->deactivateUser();
        break;
    case 'admin-reactivate-user':
        $controller = new AdminController();
        $controller->reactivateUser();
        break;
    case 'admin-change-role':
        $controller = new AdminController();
        $controller->changeRole();
        break;
    case 'admin-programs':
        $controller = new AdminController();
        $controller->programList();
        break;
    case 'admin-suspend-program':
        $controller = new AdminController();
        $controller->suspendProgram();
        break;
    case 'admin-reinstate-program':
        $controller = new AdminController();
        $controller->reinstateProgram();
        break;
    case 'admin-activity-logs':
        $controller = new AdminController();
        $controller->activityLogs();
        break;

    // =========================================================
    // Notifications page (authenticated)
    // =========================================================
    case 'notifications-mark-read':
        require_once __DIR__ . '/middleware/AuthMiddleware.php';
        $middleware = new AuthMiddleware();
        $middleware->handle();

        require_once __DIR__ . '/model/repository/BaseRepository.php';
        require_once __DIR__ . '/model/repository/NotificationRepository.php';

        $notifConn = require __DIR__ . '/config/database.php';
        $notifRepo = new NotificationRepository($notifConn);
        $notifRepo->markAllAsRead((int) $_SESSION['user_id']);

        // Redirect back to the referring page or notifications
        $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php?page=notifications';
        header('Location: ' . $referer);
        exit;

    case 'notification-click':
        require_once __DIR__ . '/middleware/AuthMiddleware.php';
        $middleware = new AuthMiddleware();
        $middleware->handle();

        require_once __DIR__ . '/model/repository/BaseRepository.php';
        require_once __DIR__ . '/model/repository/NotificationRepository.php';

        $notifConn = require __DIR__ . '/config/database.php';
        $notifRepo = new NotificationRepository($notifConn);

        $notifId = (int) ($_GET['id'] ?? 0);
        $redirect = $_GET['redirect'] ?? 'index.php?page=notifications';

        if ($notifId > 0) {
            $notifRepo->markAsRead($notifId);
        }

        header('Location: ' . $redirect);
        exit;

    case 'notifications':
        require_once __DIR__ . '/middleware/AuthMiddleware.php';
        $middleware = new AuthMiddleware();
        $middleware->handle();

        require_once __DIR__ . '/model/repository/NotificationRepository.php';
        require_once __DIR__ . '/model/services/NotificationService.php';

        $notifConn = require __DIR__ . '/config/database.php';
        $notifRepo = new NotificationRepository($notifConn);
        $notifService = new NotificationService($notifRepo);
        $userId = (int) $_SESSION['user_id'];

        // Fetch all notifications for the user
        $notifications = $notifService->getNotificationsForUser($userId, 50, 0);

        // Mark unread notifications as read on view
        foreach ($notifications as $notification) {
            if (empty($notification['is_read'])) {
                $notifService->markAsRead((int) $notification['id']);
            }
        }

        $title = 'Notifications';
        $activePage = 'notifications';
        require __DIR__ . '/view/notifications.php';
        break;

    // =========================================================
    // Fallback — 404
    // =========================================================
    default:
        $controller = new PageController();
        $controller->notFound();
        break;
}
