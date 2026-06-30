<?php

require_once __DIR__ . '/../model/repository/CommentRepository.php';
require_once __DIR__ . '/../model/repository/ReportRepository.php';
require_once __DIR__ . '/../model/repository/NotificationRepository.php';
require_once __DIR__ . '/../model/services/CommentService.php';
require_once __DIR__ . '/../model/services/ValidationService.php';
require_once __DIR__ . '/../model/services/NotificationService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

/**
 * CommentController
 *
 * Handles comment retrieval, creation, and threaded replies on vulnerability reports.
 * Validates CSRF tokens on all state-changing actions. Access control is delegated
 * to CommentService which verifies the user is either the report's researcher
 * or the program's owner.
 *
 * @see Requirement 9.1 — Store comment with author, timestamp, and report reference
 * @see Requirement 9.2 — Restrict access to report submitter and program owner
 * @see Requirement 9.3 — Notify other participant on new comment
 * @see Requirement 9.5 — Return HTTP 403 if unauthorized user attempts access
 */
class CommentController
{
    private CommentService $commentService;
    private ValidationService $validationService;

    public function __construct()
    {
        $conn = require __DIR__ . '/../config/database.php';

        $commentRepository = new CommentRepository($conn);
        $reportRepository = new ReportRepository($conn);
        $notificationRepository = new NotificationRepository($conn);

        $notificationService = new NotificationService($notificationRepository);

        $this->commentService = new CommentService(
            $commentRepository,
            $reportRepository,
            $notificationService
        );
        $this->validationService = new ValidationService($conn);
    }

    /**
     * Get comments for a report.
     * Restricted to authenticated users. Access control handled by CommentService
     * (throws 403 if user is not report researcher or program owner).
     *
     * @return void
     */
    public function getComments(): void
    {
        $authMiddleware = new AuthMiddleware();
        $authMiddleware->handle();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $reportId = (int) ($_GET['report_id'] ?? 0);
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        try {
            $comments = $this->commentService->getCommentsForReport($reportId, $userId);

            // Typically used within the report detail page context
            $title = 'SecureBounty | Comments';
            $activePage = 'reports';

            include __DIR__ . '/../view/reports/comments.php';
        } catch (RuntimeException $e) {
            if ($e->getCode() === 403) {
                http_response_code(403);
                echo 'Access denied. You do not have permission to view comments on this report.';
                return;
            }
            if ($e->getCode() === 404) {
                http_response_code(404);
                echo 'Report not found.';
                return;
            }
            http_response_code(500);
            echo 'An error occurred while loading comments.';
        }
    }

    /**
     * Add a top-level comment to a report (POST).
     * Restricted to authenticated users. Validates CSRF token.
     * Access control handled by CommentService.
     *
     * @return void
     */
    public function addComment(): void
    {
        $authMiddleware = new AuthMiddleware();
        $authMiddleware->handle();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Validate CSRF token
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->validationService->validateCsrfToken($token, session_id())) {
            http_response_code(403);
            echo 'CSRF validation failed. Please try again.';
            return;
        }

        $reportId = (int) ($_POST['report_id'] ?? 0);
        $body = $this->validationService->sanitizeInput($_POST['body'] ?? '');
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        if ($body === '') {
            $_SESSION['flash_error'] = 'Comment body cannot be empty.';
            header('Location: index.php?page=report-detail&id=' . $reportId);
            exit;
        }

        try {
            $this->commentService->addComment($reportId, $userId, $body);
            $_SESSION['flash_success'] = 'Comment added successfully.';
        } catch (RuntimeException $e) {
            if ($e->getCode() === 403) {
                http_response_code(403);
                echo 'Access denied. You do not have permission to comment on this report.';
                return;
            }
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: index.php?page=report-detail&id=' . $reportId);
        exit;
    }

    /**
     * Add a threaded reply to an existing comment (POST).
     * Restricted to authenticated users. Validates CSRF token.
     * Access control handled by CommentService.
     *
     * @return void
     */
    public function addReply(): void
    {
        $authMiddleware = new AuthMiddleware();
        $authMiddleware->handle();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Validate CSRF token
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->validationService->validateCsrfToken($token, session_id())) {
            http_response_code(403);
            echo 'CSRF validation failed. Please try again.';
            return;
        }

        $reportId = (int) ($_POST['report_id'] ?? 0);
        $body = $this->validationService->sanitizeInput($_POST['body'] ?? '');
        $parentId = (int) ($_POST['parent_id'] ?? 0);
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        if ($body === '') {
            $_SESSION['flash_error'] = 'Reply body cannot be empty.';
            header('Location: index.php?page=report-detail&id=' . $reportId);
            exit;
        }

        if ($parentId <= 0) {
            $_SESSION['flash_error'] = 'Invalid parent comment.';
            header('Location: index.php?page=report-detail&id=' . $reportId);
            exit;
        }

        try {
            $this->commentService->addComment($reportId, $userId, $body, $parentId);
            $_SESSION['flash_success'] = 'Reply added successfully.';
        } catch (RuntimeException $e) {
            if ($e->getCode() === 403) {
                http_response_code(403);
                echo 'Access denied. You do not have permission to comment on this report.';
                return;
            }
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: index.php?page=report-detail&id=' . $reportId);
        exit;
    }
}
