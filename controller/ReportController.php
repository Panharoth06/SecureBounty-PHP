<?php

require_once __DIR__ . '/../model/repository/ReportRepository.php';
require_once __DIR__ . '/../model/repository/AttachmentRepository.php';
require_once __DIR__ . '/../model/repository/ProgramRepository.php';
require_once __DIR__ . '/../model/repository/UserProgramRepository.php';
require_once __DIR__ . '/../model/repository/RewardPolicyRepository.php';
require_once __DIR__ . '/../model/repository/NotificationRepository.php';
require_once __DIR__ . '/../model/repository/ActivityLogRepository.php';
require_once __DIR__ . '/../model/services/ReportService.php';
require_once __DIR__ . '/../model/services/AttachmentService.php';
require_once __DIR__ . '/../model/services/CvssCalculatorService.php';
require_once __DIR__ . '/../model/services/ValidationService.php';
require_once __DIR__ . '/../model/services/NotificationService.php';
require_once __DIR__ . '/../model/services/ActivityLogService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/ProgramOwnerMiddleware.php';
require_once __DIR__ . '/../middleware/ResearcherMiddleware.php';

/**
 * ReportController
 *
 * Handles vulnerability report submission, detail viewing, status management,
 * attachment uploads, CVSS editing, and final severity assignment.
 * Validates CSRF tokens on all state-changing actions.
 *
 * @see Requirement 7.1 — Create report with status 'pending', notify program owner
 * @see Requirement 7.2 — Require title, description, steps_to_reproduce, impact
 * @see Requirement 7.3 — Validate file type and size, store Attachment linked to Report
 * @see Requirement 7.6 — Reject submission if researcher not enrolled (HTTP 403)
 * @see Requirement 8.1 — Display reports grouped by status
 * @see Requirement 8.2 — Update report status with activity logging
 * @see Requirement 8.3 — Support statuses: pending, triaged, accepted, rejected, resolved
 * @see Requirement 8.4 — Associate reward policy on acceptance based on severity
 */
class ReportController
{
    private ReportService $reportService;
    private AttachmentService $attachmentService;
    private CvssCalculatorService $cvssCalculatorService;
    private ValidationService $validationService;
    private ReportRepository $reportRepository;
    private AttachmentRepository $attachmentRepository;
    private ProgramRepository $programRepository;

    public function __construct()
    {
        $conn = require __DIR__ . '/../config/database.php';

        $reportRepository = new ReportRepository($conn);
        $attachmentRepository = new AttachmentRepository($conn);
        $programRepository = new ProgramRepository($conn);
        $userProgramRepository = new UserProgramRepository($conn);
        $rewardPolicyRepository = new RewardPolicyRepository($conn);
        $notificationRepository = new NotificationRepository($conn);
        $activityLogRepository = new ActivityLogRepository($conn);

        $notificationService = new NotificationService($notificationRepository);
        $activityLogService = new ActivityLogService($activityLogRepository);

        $this->reportRepository = $reportRepository;
        $this->attachmentRepository = $attachmentRepository;
        $this->programRepository = $programRepository;
        $this->reportService = new ReportService(
            $reportRepository,
            $userProgramRepository,
            $programRepository,
            $rewardPolicyRepository,
            $notificationService,
            $activityLogService
        );
        $this->attachmentService = new AttachmentService($attachmentRepository);
        $this->cvssCalculatorService = new CvssCalculatorService();
        $this->validationService = new ValidationService($conn);
    }

    /**
     * List reports for the current user.
     *
     * Program Owners (role 2) see reports across all their owned programs.
     * Researchers (role 3) see the reports they have submitted.
     * Results are grouped by status for the management list view.
     *
     * @return void
     */
    public function list(): void
    {
        (new AuthMiddleware())->handle();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $roleId = (int) ($_SESSION['role_id'] ?? 0);
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $statuses = ['pending', 'triaged', 'accepted', 'rejected', 'resolved'];
        $reports = array_fill_keys($statuses, []);

        if ($roleId === 2) {
            // Program Owner: aggregate reports across owned programs.
            $programs = $this->programRepository->findByOwnerId($userId);
            foreach ($programs as $program) {
                foreach ($this->reportRepository->findByProgramId((int) $program['id']) as $report) {
                    $report['program_title'] = $program['title'];
                    $status = $report['status'] ?? 'pending';
                    if (isset($reports[$status])) {
                        $reports[$status][] = $report;
                    }
                }
            }
        } else {
            // Researcher: their own submitted reports (program_title is joined in).
            foreach ($this->reportRepository->findByResearcherId($userId) as $report) {
                $status = $report['status'] ?? 'pending';
                if (isset($reports[$status])) {
                    $reports[$status][] = $report;
                }
            }
        }

        $success = $_SESSION['flash_success'] ?? null;
        $error = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $csrfToken = $this->validationService->generateCsrfToken(session_id());

        $title = 'SecureBounty | Reports';
        $activePage = $roleId === 2 ? 'reports' : 'my-reports';

        include __DIR__ . '/../view/reports/list.php';
    }

    /**
     * Display the report submission form.
     * Restricted to Researchers. Requires program_id from $_GET.
     *
     * @return void
     */
    public function submit(): void
    {
        $middleware = new ResearcherMiddleware();
        $middleware->handle();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $programId = (int) ($_GET['program_id'] ?? 0);

        $program = $this->programRepository->findById($programId);

        if ($program === null) {
            $_SESSION['flash_error'] = 'Program not found.';
            header('Location: index.php?page=programs');
            exit;
        }

        $csrfToken = $this->validationService->generateCsrfToken(session_id());

        // Retrieve flash messages and old input
        $errors = $_SESSION['flash_errors'] ?? [];
        $old = $_SESSION['flash_old_input'] ?? [];
        unset($_SESSION['flash_errors'], $_SESSION['flash_old_input']);

        $title = 'SecureBounty | Submit Report';
        $activePage = 'reports';
        $programTitle = $program['title'] ?? '';

        include __DIR__ . '/../view/reports/submit.php';
    }

    /**
     * Process report submission form (POST).
     * Restricted to Researchers. Validates CSRF token.
     * Sanitizes inputs, calls ReportService, handles file uploads.
     *
     * @return void
     */
    public function processSubmit(): void
    {
        $middleware = new ResearcherMiddleware();
        $middleware->handle();

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

        // Sanitize inputs
        $programId = (int) ($_POST['program_id'] ?? 0);
        $reportTitle = $this->validationService->sanitizeInput($_POST['title'] ?? '');
        $description = $this->validationService->sanitizeInput($_POST['description'] ?? '');
        $stepsToReproduce = '';
        $impact = '';

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

        try {
            $reportId = $this->reportService->submitReport(
                $userId,
                $programId,
                $reportTitle,
                $description,
                $stepsToReproduce,
                $impact,
                $ipAddress
            );

            // Handle CVSS data if provided
            $cvssVector = trim($_POST['cvss_vector'] ?? '');
            $cvssScore = $_POST['cvss_score'] ?? '';
            $cvssSeverity = trim($_POST['cvss_severity'] ?? '');

            if ($cvssVector !== '' && $cvssScore !== '' && $cvssSeverity !== '') {
                $this->reportRepository->updateCvss(
                    $reportId,
                    $cvssVector,
                    (float) $cvssScore,
                    $cvssSeverity,
                    'researcher'
                );
            }

            // Handle file upload if present
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                try {
                    $this->attachmentService->uploadAttachment($reportId, $_FILES['attachment']);
                } catch (InvalidArgumentException $e) {
                    // Attachment failed but report was created — flash warning
                    $_SESSION['flash_error'] = 'Report submitted but attachment upload failed: ' . $e->getMessage();
                    header('Location: index.php?page=report-detail&id=' . $reportId);
                    exit;
                }
            }

            $_SESSION['flash_success'] = 'Report submitted successfully.';
            header('Location: index.php?page=report-detail&id=' . $reportId);
            exit;
        } catch (RuntimeException $e) {
            if ($e->getCode() === 403) {
                http_response_code(403);
                echo $e->getMessage();
                return;
            }
            $_SESSION['flash_error'] = $e->getMessage();
            header('Location: index.php?page=report-submit&program_id=' . $programId);
            exit;
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_errors'] = ['general' => $e->getMessage()];
            $_SESSION['flash_old_input'] = [
                'title' => $reportTitle,
                'description' => $description,
            ];
            header('Location: index.php?page=report-submit&program_id=' . $programId);
            exit;
        }
    }

    /**
     * Display report detail page.
     * Restricted to authenticated users. Verifies access (researcher who submitted OR program owner).
     *
     * @param int|null $reportId Report ID (from route parameter or $_GET['id']).
     * @return void
     */
    public function detail(?int $reportId = null): void
    {
        $authMiddleware = new AuthMiddleware();
        $authMiddleware->handle();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $reportId = $reportId ?? (int) ($_GET['id'] ?? 0);

        $report = $this->reportRepository->findById($reportId);

        if ($report === null) {
            $_SESSION['flash_error'] = 'Report not found.';
            header('Location: index.php?page=programs');
            exit;
        }

        // Verify access: researcher who submitted OR program owner
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $isResearcher = ((int) $report['researcher_id'] === $userId);
        $isProgramOwner = ((int) $report['program_owner_id'] === $userId);

        if (!$isResearcher && !$isProgramOwner) {
            http_response_code(403);
            echo 'Access denied. You do not have permission to view this report.';
            return;
        }

        // Load attachments
        $attachments = $this->attachmentRepository->findByReportId($reportId);

        // Set role flags for the view
        $isReporter = $isResearcher;

        // Generate CSRF token for state-changing actions on this page
        $csrfToken = $this->validationService->generateCsrfToken(session_id());

        // Retrieve flash messages
        $success = $_SESSION['flash_success'] ?? null;
        $error = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $title = 'SecureBounty | Report: ' . htmlspecialchars($report['title'], ENT_QUOTES, 'UTF-8');
        $activePage = 'reports';

        include __DIR__ . '/../view/reports/detail.php';
    }

    /**
     * Change report status (POST).
     * Restricted to Program Owners. Validates CSRF token.
     * For 'accepted' status, calls acceptReport() instead of changeStatus().
     *
     * @return void
     */
    public function changeStatus(): void
    {
        $middleware = new ProgramOwnerMiddleware();
        $middleware->handle();

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
        $status = $this->validationService->sanitizeInput($_POST['status'] ?? '');
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

        try {
            if ($status === 'accepted') {
                // For acceptance, require final_severity
                $finalSeverity = $this->validationService->sanitizeInput($_POST['final_severity'] ?? '');
                $this->reportService->acceptReport($reportId, $finalSeverity, $userId, $ipAddress);
                $_SESSION['flash_success'] = 'Report accepted successfully.';
            } else {
                $this->reportService->changeStatus($reportId, $status, $userId, $ipAddress);
                $_SESSION['flash_success'] = 'Report status updated successfully.';
            }
        } catch (RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: index.php?page=report-detail&id=' . $reportId);
        exit;
    }

    /**
     * Upload an attachment to a report (POST).
     * Restricted to Researchers. Validates CSRF token.
     *
     * @return void
     */
    public function uploadAttachment(): void
    {
        $middleware = new ResearcherMiddleware();
        $middleware->handle();

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

        if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash_error'] = 'No file uploaded or upload error occurred.';
            header('Location: index.php?page=report-detail&id=' . $reportId);
            exit;
        }

        try {
            $this->attachmentService->uploadAttachment($reportId, $_FILES['attachment']);
            $_SESSION['flash_success'] = 'Attachment uploaded successfully.';
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        } catch (RuntimeException $e) {
            $_SESSION['flash_error'] = 'File upload failed: ' . $e->getMessage();
        }

        header('Location: index.php?page=report-detail&id=' . $reportId);
        exit;
    }

    /**
     * Download an attachment file.
     * Restricted to the report's researcher or the program owner.
     *
     * @return void
     */
    public function downloadAttachment(): void
    {
        $authMiddleware = new AuthMiddleware();
        $authMiddleware->handle();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $attachmentId = (int) ($_GET['id'] ?? 0);
        $attachment = $this->attachmentRepository->findById($attachmentId);

        if ($attachment === null) {
            http_response_code(404);
            echo 'Attachment not found.';
            return;
        }

        // Verify access via the report
        $report = $this->reportRepository->findById((int) $attachment['report_id']);
        if ($report === null) {
            http_response_code(404);
            echo 'Report not found.';
            return;
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $isResearcher = ((int) $report['researcher_id'] === $userId);
        $isProgramOwner = ((int) $report['program_owner_id'] === $userId);

        if (!$isResearcher && !$isProgramOwner) {
            http_response_code(403);
            echo 'Access denied.';
            return;
        }

        $filePath = $attachment['file_path'];
        if (!file_exists($filePath)) {
            http_response_code(404);
            echo 'File not found on server.';
            return;
        }

        // Serve the file
        $mimeTypes = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'zip' => 'application/zip',
        ];
        $ext = strtolower($attachment['file_type']);
        $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . basename($attachment['file_name']) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, must-revalidate');

        readfile($filePath);
        exit;
    }

    /**
     * Edit CVSS metrics for a report (POST).
     * Restricted to authenticated users with access (researcher or program owner).
     * Validates CSRF token. Parses CVSS metrics from form, computes score via CvssCalculatorService.
     *
     * @return void
     */
    public function editCvss(): void
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
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $roleId = (int) ($_SESSION['role_id'] ?? 0);

        // Load report and verify access
        $report = $this->reportRepository->findById($reportId);

        if ($report === null) {
            $_SESSION['flash_error'] = 'Report not found.';
            header('Location: index.php?page=programs');
            exit;
        }

        // Verify access: researcher who submitted OR program owner
        $isResearcher = ((int) $report['researcher_id'] === $userId);
        $isProgramOwner = ((int) $report['program_owner_id'] === $userId);

        if (!$isResearcher && !$isProgramOwner) {
            http_response_code(403);
            echo 'Access denied. You do not have permission to edit CVSS for this report.';
            return;
        }

        // Parse CVSS metrics from form
        $metrics = [
            'AV' => $this->validationService->sanitizeInput($_POST['cvss_av'] ?? ''),
            'AC' => $this->validationService->sanitizeInput($_POST['cvss_ac'] ?? ''),
            'PR' => $this->validationService->sanitizeInput($_POST['cvss_pr'] ?? ''),
            'UI' => $this->validationService->sanitizeInput($_POST['cvss_ui'] ?? ''),
            'S' => $this->validationService->sanitizeInput($_POST['cvss_s'] ?? ''),
            'C' => $this->validationService->sanitizeInput($_POST['cvss_c'] ?? ''),
            'I' => $this->validationService->sanitizeInput($_POST['cvss_i'] ?? ''),
            'A' => $this->validationService->sanitizeInput($_POST['cvss_a'] ?? ''),
        ];

        try {
            // Build vector string from metrics
            $vectorString = $this->cvssCalculatorService->buildVector($metrics);

            // Compute score
            $score = $this->cvssCalculatorService->computeScore($vectorString);

            // Derive severity
            $severity = $this->cvssCalculatorService->deriveSeverity($score);

            // Determine who submitted: researcher or program_owner
            $submittedBy = $isProgramOwner ? 'program_owner' : 'researcher';

            // Update report CVSS fields
            $this->reportRepository->updateCvss($reportId, $vectorString, $score, $severity, $submittedBy);

            $_SESSION['flash_success'] = 'CVSS score updated successfully.';
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_error'] = 'Invalid CVSS metrics: ' . $e->getMessage();
        }

        header('Location: index.php?page=report-detail&id=' . $reportId);
        exit;
    }

    /**
     * Set final severity on a report (POST).
     * Restricted to Program Owners. Validates CSRF token.
     *
     * @return void
     */
    public function setFinalSeverity(): void
    {
        $middleware = new ProgramOwnerMiddleware();
        $middleware->handle();

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
        $finalSeverity = $this->validationService->sanitizeInput($_POST['final_severity'] ?? '');
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $validSeverities = ['critical', 'high', 'medium', 'low', 'informational'];
        if (!in_array($finalSeverity, $validSeverities, true)) {
            $_SESSION['flash_error'] = 'Invalid severity level.';
            header('Location: index.php?page=report-detail&id=' . $reportId);
            exit;
        }

        $report = $this->reportRepository->findById($reportId);

        if ($report === null) {
            $_SESSION['flash_error'] = 'Report not found.';
            header('Location: index.php?page=programs');
            exit;
        }

        // Verify the user is the program owner for this report
        if ((int) $report['program_owner_id'] !== $userId) {
            http_response_code(403);
            echo 'Access denied. You are not the owner of this program.';
            return;
        }

        $this->reportRepository->updateFinalSeverity($reportId, $finalSeverity);

        $_SESSION['flash_success'] = 'Final severity set successfully.';
        header('Location: index.php?page=report-detail&id=' . $reportId);
        exit;
    }

    /**
     * List the authenticated Researcher's own reports, grouped by status.
     * Restricted to Researchers.
     *
     * @return void
     */
    public function myReports(): void
    {
        $middleware = new ResearcherMiddleware();
        $middleware->handle();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $researcherId = (int) ($_SESSION['user_id'] ?? 0);
        $firstName = $_SESSION['first_name'] ?? '';
        $lastName = $_SESSION['last_name'] ?? '';

        $rows = $this->reportRepository->findByResearcherId($researcherId);

        $statuses = ['pending', 'triaged', 'accepted', 'rejected', 'resolved'];
        $reports = array_fill_keys($statuses, []);

        foreach ($rows as $report) {
            $report['researcher_first_name'] = $firstName;
            $report['researcher_last_name'] = $lastName;
            $status = $report['status'] ?? 'pending';
            if (isset($reports[$status])) {
                $reports[$status][] = $report;
            }
        }

        $csrfToken = $this->validationService->generateCsrfToken(session_id());
        $success = $_SESSION['flash_success'] ?? null;
        $error = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $title = 'SecureBounty | My Reports';
        $activePage = 'my-reports';

        include __DIR__ . '/../view/reports/list.php';
    }

    /**
     * Display the report edit form.
     * Restricted to the researcher who submitted the report.
     *
     * @return void
     */
    public function edit(): void
    {
        $authMiddleware = new AuthMiddleware();
        $authMiddleware->handle();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $reportId = (int) ($_GET['id'] ?? 0);
        $report = $this->reportRepository->findById($reportId);

        if ($report === null) {
            $_SESSION['flash_error'] = 'Report not found.';
            header('Location: index.php?page=reports');
            exit;
        }

        // Only the researcher who submitted can edit
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ((int) $report['researcher_id'] !== $userId) {
            http_response_code(403);
            echo 'Access denied. Only the report author can edit.';
            return;
        }

        $csrfToken = $this->validationService->generateCsrfToken(session_id());

        $errors = $_SESSION['flash_errors'] ?? [];
        $old = $_SESSION['flash_old_input'] ?? [];
        unset($_SESSION['flash_errors'], $_SESSION['flash_old_input']);

        $title = 'SecureBounty | Edit Report';
        $activePage = 'my-reports';

        include __DIR__ . '/../view/reports/edit.php';
    }

    /**
     * Process report edit form (POST).
     * Restricted to the researcher who submitted the report.
     *
     * @return void
     */
    public function processEdit(): void
    {
        $authMiddleware = new AuthMiddleware();
        $authMiddleware->handle();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Validate CSRF
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->validationService->validateCsrfToken($token, session_id())) {
            http_response_code(403);
            echo 'CSRF validation failed.';
            return;
        }

        $reportId = (int) ($_POST['report_id'] ?? 0);
        $report = $this->reportRepository->findById($reportId);

        if ($report === null) {
            $_SESSION['flash_error'] = 'Report not found.';
            header('Location: index.php?page=reports');
            exit;
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ((int) $report['researcher_id'] !== $userId) {
            http_response_code(403);
            echo 'Access denied.';
            return;
        }

        $newTitle = $this->validationService->sanitizeInput($_POST['title'] ?? '');
        $newDescription = $this->validationService->sanitizeInput($_POST['description'] ?? '');

        if (empty(trim($newTitle))) {
            $_SESSION['flash_errors'] = ['title' => 'Title is required.'];
            $_SESSION['flash_old_input'] = ['title' => $newTitle, 'description' => $newDescription];
            header('Location: index.php?page=report-edit&id=' . $reportId);
            exit;
        }

        $this->reportRepository->updateReport($reportId, $newTitle, $newDescription);

        // Handle CVSS data if provided
        $cvssVector = trim($_POST['cvss_vector'] ?? '');
        $cvssScore = $_POST['cvss_score'] ?? '';
        $cvssSeverity = trim($_POST['cvss_severity'] ?? '');

        if ($cvssVector !== '' && $cvssScore !== '' && $cvssSeverity !== '') {
            $this->reportRepository->updateCvss(
                $reportId,
                $cvssVector,
                (float) $cvssScore,
                $cvssSeverity,
                'researcher'
            );
        }

        // Handle file upload if present
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            try {
                $this->attachmentService->uploadAttachment($reportId, $_FILES['attachment']);
            } catch (InvalidArgumentException $e) {
                $_SESSION['flash_error'] = 'Report updated but attachment upload failed: ' . $e->getMessage();
                header('Location: index.php?page=report-detail&id=' . $reportId);
                exit;
            }
        }

        $_SESSION['flash_success'] = 'Report updated successfully.';
        header('Location: index.php?page=report-detail&id=' . $reportId);
        exit;
    }

    /**
     * Delete a report (POST).
     * Restricted to the researcher who submitted the report.
     *
     * @return void
     */
    public function deleteReport(): void
    {
        $authMiddleware = new AuthMiddleware();
        $authMiddleware->handle();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Validate CSRF
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->validationService->validateCsrfToken($token, session_id())) {
            http_response_code(403);
            echo 'CSRF validation failed.';
            return;
        }

        $reportId = (int) ($_POST['report_id'] ?? 0);
        $report = $this->reportRepository->findById($reportId);

        if ($report === null) {
            $_SESSION['flash_error'] = 'Report not found.';
            header('Location: index.php?page=my-reports');
            exit;
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ((int) $report['researcher_id'] !== $userId) {
            http_response_code(403);
            echo 'Access denied.';
            return;
        }

        $this->reportRepository->delete($reportId);
        $_SESSION['flash_success'] = 'Report deleted.';
        header('Location: index.php?page=my-reports');
        exit;
    }
}
