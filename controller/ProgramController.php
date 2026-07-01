<?php

require_once __DIR__ . '/../model/repository/ProgramRepository.php';
require_once __DIR__ . '/../model/repository/RewardPolicyRepository.php';
require_once __DIR__ . '/../model/repository/UserProgramRepository.php';
require_once __DIR__ . '/../model/repository/SavedProgramRepository.php';
require_once __DIR__ . '/../model/repository/ProgramCommentRepository.php';
require_once __DIR__ . '/../model/repository/ActivityLogRepository.php';
require_once __DIR__ . '/../model/repository/AssetRepository.php';
require_once __DIR__ . '/../model/repository/TagRepository.php';
require_once __DIR__ . '/../model/services/ProgramService.php';
require_once __DIR__ . '/../model/services/RewardPolicyService.php';
require_once __DIR__ . '/../model/services/ValidationService.php';
require_once __DIR__ . '/../model/services/ActivityLogService.php';
require_once __DIR__ . '/../model/services/StatisticsService.php';
require_once __DIR__ . '/../model/services/ImageService.php';
require_once __DIR__ . '/../model/services/AssetService.php';
require_once __DIR__ . '/../model/services/TagService.php';
require_once __DIR__ . '/../model/services/FormattingService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/ProgramOwnerMiddleware.php';
require_once __DIR__ . '/../middleware/ResearcherMiddleware.php';

/**
 * ProgramController
 *
 * Handles program CRUD operations for Program Owners and program
 * discovery/enrollment/bookmarking for Researchers.
 * Validates CSRF tokens on all state-changing actions.
 *
 * @see Requirement 4.1 — Create program with status 'draft'
 * @see Requirement 4.2 — Publish program (draft → active)
 * @see Requirement 4.3 — Update active program with activity logging
 * @see Requirement 4.4 — Close program (active → closed)
 * @see Requirement 4.5 — Require title, description, scope, ≥1 reward policy before publish
 * @see Requirement 4.6 — Validate non-empty required fields on create/update
 * @see Requirement 6.1 — Display all active programs for researchers
 * @see Requirement 6.2 — Enroll researcher in program
 * @see Requirement 6.3 — Save (bookmark) program
 * @see Requirement 6.4 — Remove saved program
 * @see Requirement 6.5 — Prevent duplicate enrollment
 */
class ProgramController
{
    private ProgramService $programService;
    private RewardPolicyService $rewardPolicyService;
    private UserProgramRepository $userProgramRepository;
    private SavedProgramRepository $savedProgramRepository;
    private ProgramCommentRepository $programCommentRepository;
    private ValidationService $validationService;
    private ProgramRepository $programRepository;
    private RewardPolicyRepository $rewardPolicyRepository;
    private StatisticsService $statisticsService;
    private ImageService $imageService;
    private AssetService $assetService;
    private TagService $tagService;
    private TagRepository $tagRepository;

    public function __construct()
    {
        $conn = require __DIR__ . '/../config/database.php';

        $programRepository = new ProgramRepository($conn);
        $rewardPolicyRepository = new RewardPolicyRepository($conn);
        $activityLogRepository = new ActivityLogRepository($conn);
        $activityLogService = new ActivityLogService($activityLogRepository);
        $assetRepository = new AssetRepository($conn);
        $tagRepository = new TagRepository($conn);

        $this->programRepository = $programRepository;
        $this->rewardPolicyRepository = $rewardPolicyRepository;
        $this->programService = new ProgramService($programRepository, $activityLogService, $conn);
        $this->rewardPolicyService = new RewardPolicyService($rewardPolicyRepository, $activityLogService);
        $this->userProgramRepository = new UserProgramRepository($conn);
        $this->savedProgramRepository = new SavedProgramRepository($conn);
        $this->programCommentRepository = new ProgramCommentRepository($conn);
        $this->validationService = new ValidationService($conn);
        $this->statisticsService = new StatisticsService($conn);
        $this->imageService = new ImageService();
        $this->assetService = new AssetService($assetRepository, $programRepository);
        $this->tagService = new TagService($tagRepository, $programRepository);
        $this->tagRepository = $tagRepository;
    }

    /**
     * Display program listing.
     * Researchers see active programs with filters and statistics; Program Owners see their own programs.
     *
     * @return void
     */
    public function list(): void
    {
        $authMiddleware = new AuthMiddleware();
        $authMiddleware->handle();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $roleId = (int) ($_SESSION['role_id'] ?? 0);
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        // Defaults for variables consumed by the researcher view (kept in scope
        // for both branches so the include block does not warn on undefined vars).
        $filters = [];
        $allTags = [];
        $statistics = [];
        $assetCounts = [];
        $tags = [];
        $totalCount = 0;
        $page = 1;
        $perPage = 12;
        $filterErrors = [];

        if ($roleId === 2) {
            // Program Owner — show their own programs with reward ranges
            $programs = $this->programRepository->findByOwnerId($userId);
            foreach ($programs as &$prog) {
                $policies = $this->rewardPolicyRepository->findByProgramId((int) $prog['id']);
                if (!empty($policies)) {
                    $prog['min_reward'] = min(array_column($policies, 'min_reward'));
                    $prog['max_reward'] = max(array_column($policies, 'max_reward'));
                }
            }
            unset($prog);
        } else {
            // Researcher — show active programs with filters, statistics, enrollment/saved status

            // Detect invalid bounty range before normalising filters so the panel can show an inline error
            if (
                isset($_GET['bounty_min'], $_GET['bounty_max'])
                && is_numeric($_GET['bounty_min'])
                && is_numeric($_GET['bounty_max'])
                && (float) $_GET['bounty_min'] > (float) $_GET['bounty_max']
            ) {
                $filterErrors['bounty_range'] = 'Minimum bounty cannot exceed maximum.';
            }

            // Resolve filters: GET params > session > none
            $filters = $this->resolveFilters();

            // Full tag pool for the filter panel multi-select
            $allTags = $this->tagRepository->findAll();

            // Get page and pagination params
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = 12;
            $offset = ($page - 1) * $perPage;

            // Query programs with filters
            $programs = $this->programRepository->findActiveWithFilters($filters, $perPage, $offset);
            $totalCount = $this->programRepository->countActiveWithFilters($filters);

            // Gather program IDs for bulk operations
            $programIds = array_map(fn($p) => (int) $p['id'], $programs);

            // Get bulk statistics for all programs on page
            $statistics = [];
            if (!empty($programIds)) {
                $statistics = $this->statisticsService->getBulkProgramStatistics($programIds);
            }

            // Get asset counts and tags for each program, and enrich with enrollment/saved status
            $assetCounts = [];
            $tags = [];
            foreach ($programs as &$prog) {
                $progId = (int) $prog['id'];
                $prog['is_enrolled'] = $this->userProgramRepository->isEnrolled($userId, $progId);
                $prog['is_saved'] = $this->savedProgramRepository->isSaved($userId, $progId);

                // Asset counts by type
                $assetCounts[$progId] = $this->assetService->getAssetCountsByType($progId);

                // Tags for this program
                $tags[$progId] = $this->tagService->getTagsByProgram($progId);

                // Reward range
                $policies = $this->rewardPolicyRepository->findByProgramId($progId);
                if (!empty($policies)) {
                    $prog['min_reward'] = min(array_column($policies, 'min_reward'));
                    $prog['max_reward'] = max(array_column($policies, 'max_reward'));
                } else {
                    $prog['min_reward'] = null;
                    $prog['max_reward'] = null;
                }
            }
            unset($prog);
        }

        $csrfToken = $this->validationService->generateCsrfToken(session_id());

        // Retrieve flash messages
        $success = $_SESSION['flash_success'] ?? null;
        $error = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $title = 'SecureBounty | Programs';
        $activePage = 'programs';

        include __DIR__ . '/../view/programs/list.php';
    }

    /**
     * Resolve filter parameters from GET, session, or default (no filters).
     *
     * Logic:
     * - If GET has clear_filters=1 → clear session, return empty
     * - If GET has filter params → use those and store in session
     * - If no GET filter params but session has stored filters → use session
     * - Default: no filters
     *
     * @return array Associative array of filter criteria.
     */
    private function resolveFilters(): array
    {
        // Clear filters explicitly
        if (isset($_GET['clear_filters']) && $_GET['clear_filters'] === '1') {
            unset($_SESSION['program_filters']);
            return [];
        }

        // Check if GET has any filter params
        $hasGetFilters = isset($_GET['asset_type'])
            || isset($_GET['tag'])
            || isset($_GET['bounty_min'])
            || isset($_GET['bounty_max']);

        if ($hasGetFilters) {
            $filters = [];

            // Parse asset_type[] array
            if (!empty($_GET['asset_type']) && is_array($_GET['asset_type'])) {
                $filters['asset_type'] = array_filter($_GET['asset_type'], fn($v) => $v !== '');
            }

            // Parse tag[] array
            if (!empty($_GET['tag']) && is_array($_GET['tag'])) {
                $filters['tag'] = array_map('intval', $_GET['tag']);
                $filters['tag'] = array_filter($filters['tag'], fn($v) => $v > 0);
            }

            // Parse bounty_min (validate numeric)
            if (isset($_GET['bounty_min']) && $_GET['bounty_min'] !== '' && is_numeric($_GET['bounty_min'])) {
                $filters['bounty_min'] = (float) $_GET['bounty_min'];
            }

            // Parse bounty_max (validate numeric)
            if (isset($_GET['bounty_max']) && $_GET['bounty_max'] !== '' && is_numeric($_GET['bounty_max'])) {
                $filters['bounty_max'] = (float) $_GET['bounty_max'];
            }

            // Validate bounty range (min <= max)
            if (isset($filters['bounty_min'], $filters['bounty_max'])) {
                if ($filters['bounty_min'] > $filters['bounty_max']) {
                    // Invalid range — remove both bounty filters
                    unset($filters['bounty_min'], $filters['bounty_max']);
                }
            }

            // Store in session
            $_SESSION['program_filters'] = $filters;

            return $filters;
        }

        // Use session filters if available
        if (!empty($_SESSION['program_filters']) && is_array($_SESSION['program_filters'])) {
            return $_SESSION['program_filters'];
        }

        // Default: no filters
        return [];
    }

    /**
     * Display program detail with reward policies, assets, tags, statistics, and enrollment status.
     *
     * @param int|null $programId Program ID (from route parameter or $_GET['id']).
     * @return void
     */
    public function detail(?int $programId = null): void
    {
        $authMiddleware = new AuthMiddleware();
        $authMiddleware->handle();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $programId = $programId ?? (int) ($_GET['id'] ?? 0);

        $program = $this->programRepository->findById($programId);

        if ($program === null) {
            $_SESSION['flash_error'] = 'Program not found.';
            header('Location: index.php?page=programs');
            exit;
        }

        $rewardPolicies = $this->rewardPolicyRepository->findByProgramId($programId);

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $isEnrolled = $this->userProgramRepository->isEnrolled($userId, $programId);
        $isSaved = $this->savedProgramRepository->isSaved($userId, $programId);

        // Fetch program-level comments (threaded discussion)
        $programComments = $this->programCommentRepository->findByProgramId($programId);

        // Fetch assets for this program
        $assets = $this->assetService->getAssetsByProgram($programId);

        // Fetch tags for this program
        $programTags = $this->tagService->getTagsByProgram($programId);

        // Get statistics for this program (report count, enrolled count, response rate, badges)
        $programStatistics = $this->statisticsService->getProgramStatistics($programId);

        // Logo path info for display
        $logoPath = $program['logo_path'] ?? null;

        // Generate CSRF token for state-changing actions on this page
        $csrfToken = $this->validationService->generateCsrfToken(session_id());

        // Retrieve flash messages
        $success = $_SESSION['flash_success'] ?? null;
        $error = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $title = 'SecureBounty | ' . htmlspecialchars($program['title'], ENT_QUOTES, 'UTF-8');
        $activePage = 'programs';

        include __DIR__ . '/../view/programs/detail.php';
    }

    /**
     * Display the program creation form.
     * Restricted to Program Owners.
     *
     * @return void
     */
    public function create(): void
    {
        $middleware = new ProgramOwnerMiddleware();
        $middleware->handle();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $csrfToken = $this->validationService->generateCsrfToken(session_id());

        // Retrieve flash messages and old input
        $errors = $_SESSION['flash_errors'] ?? [];
        $oldInput = $_SESSION['flash_old_input'] ?? [];
        unset($_SESSION['flash_errors'], $_SESSION['flash_old_input']);

        $title = 'SecureBounty | Create Program';
        $activePage = 'programs';

        include __DIR__ . '/../view/programs/create.php';
    }

    /**
     * Process program creation form submission.
     * Restricted to Program Owners. Validates CSRF token.
     * Handles optional logo upload.
     *
     * @return void
     */
    public function processCreate(): void
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

        // Sanitize inputs
        $titleInput = $this->validationService->sanitizeInput($_POST['title'] ?? '');
        $description = $this->validationService->sanitizeInput($_POST['description'] ?? '');
        $scope = $this->validationService->sanitizeInput($_POST['scope'] ?? '');

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

        try {
            $programId = $this->programService->createProgram(
                $userId,
                $titleInput,
                $description,
                $scope,
                $ipAddress
            );

            // Handle logo upload if a file was provided
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                try {
                    $logoPath = $this->imageService->uploadLogo($_FILES['logo'], $programId);
                    $this->programRepository->updateLogoPath($programId, $logoPath);
                } catch (InvalidArgumentException $e) {
                    $_SESSION['flash_error'] = 'Program created, but logo upload failed: ' . $e->getMessage();
                    header('Location: index.php?page=program-detail&id=' . $programId);
                    exit;
                } catch (RuntimeException $e) {
                    $_SESSION['flash_error'] = 'Program created, but logo upload failed: ' . $e->getMessage();
                    header('Location: index.php?page=program-detail&id=' . $programId);
                    exit;
                }
            }

            $_SESSION['flash_success'] = 'Program created successfully.';
            header('Location: index.php?page=program-detail&id=' . $programId);
            exit;
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_errors'] = ['general' => $e->getMessage()];
            $_SESSION['flash_old_input'] = [
                'title' => $titleInput,
                'description' => $description,
                'scope' => $scope,
            ];
            header('Location: index.php?page=program-create');
            exit;
        }
    }

    /**
     * Display the program edit form.
     * Restricted to Program Owners. Verifies ownership.
     *
     * @param int|null $programId Program ID (from route parameter or $_GET['id']).
     * @return void
     */
    public function edit(?int $programId = null): void
    {
        $middleware = new ProgramOwnerMiddleware();
        $middleware->handle();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $programId = $programId ?? (int) ($_GET['id'] ?? 0);
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $program = $this->programRepository->findById($programId);

        if ($program === null) {
            $_SESSION['flash_error'] = 'Program not found.';
            header('Location: index.php?page=programs');
            exit;
        }

        // Verify ownership
        if ((int) $program['owner_id'] !== $userId) {
            http_response_code(403);
            echo 'Access denied. You do not own this program.';
            return;
        }

        $rewardPolicies = $this->rewardPolicyRepository->findByProgramId($programId);
        $csrfToken = $this->validationService->generateCsrfToken(session_id());

        // Assets and tags for in-page management sections
        $assets = $this->assetService->getAssetsByProgram($programId);
        $assetTypes = AssetService::VALID_TYPES;
        $tags = $this->tagService->getTagsByProgram($programId);
        $tagCount = count($tags);
        $tagLimit = 20;

        // Retrieve flash messages and old input
        $errors = $_SESSION['flash_errors'] ?? [];
        $oldInput = $_SESSION['flash_old_input'] ?? [];
        $assetErrors = $_SESSION['flash_asset_errors'] ?? [];
        $tagErrors = $_SESSION['flash_tag_errors'] ?? [];
        $assetOldInput = $_SESSION['flash_asset_old_input'] ?? [];
        $tagOldInput = $_SESSION['flash_tag_old_input'] ?? [];
        unset(
            $_SESSION['flash_errors'],
            $_SESSION['flash_old_input'],
            $_SESSION['flash_asset_errors'],
            $_SESSION['flash_tag_errors'],
            $_SESSION['flash_asset_old_input'],
            $_SESSION['flash_tag_old_input']
        );

        $title = 'SecureBounty | Edit Program';
        $activePage = 'programs';

        include __DIR__ . '/../view/programs/edit.php';
    }

    /**
     * Process program edit form submission.
     * Restricted to Program Owners. Validates CSRF token and ownership.
     * Handles optional logo upload.
     *
     * @param int|null $programId Program ID (from route parameter or $_GET['id']).
     * @return void
     */
    public function processEdit(?int $programId = null): void
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

        $programId = $programId ?? (int) ($_GET['id'] ?? 0);
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

        $program = $this->programRepository->findById($programId);

        if ($program === null) {
            $_SESSION['flash_error'] = 'Program not found.';
            header('Location: index.php?page=programs');
            exit;
        }

        // Verify ownership
        if ((int) $program['owner_id'] !== $userId) {
            http_response_code(403);
            echo 'Access denied. You do not own this program.';
            return;
        }

        // Sanitize inputs
        $titleInput = $this->validationService->sanitizeInput($_POST['title'] ?? '');
        $description = $this->validationService->sanitizeInput($_POST['description'] ?? '');
        $scope = $this->validationService->sanitizeInput($_POST['scope'] ?? '');

        try {
            $this->programService->updateProgram(
                $programId,
                $userId,
                $titleInput,
                $description,
                $scope,
                $ipAddress
            );

            // Handle logo upload if a file was provided
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                try {
                    $logoPath = $this->imageService->uploadLogo($_FILES['logo'], $programId);
                    $this->programRepository->updateLogoPath($programId, $logoPath);
                } catch (InvalidArgumentException $e) {
                    $_SESSION['flash_error'] = 'Program updated, but logo upload failed: ' . $e->getMessage();
                    header('Location: index.php?page=program-detail&id=' . $programId);
                    exit;
                } catch (RuntimeException $e) {
                    $_SESSION['flash_error'] = 'Program updated, but logo upload failed: ' . $e->getMessage();
                    header('Location: index.php?page=program-detail&id=' . $programId);
                    exit;
                }
            }

            $_SESSION['flash_success'] = 'Program updated successfully.';
            header('Location: index.php?page=program-detail&id=' . $programId);
            exit;
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_errors'] = ['general' => $e->getMessage()];
            $_SESSION['flash_old_input'] = [
                'title' => $titleInput,
                'description' => $description,
                'scope' => $scope,
            ];
            header('Location: index.php?page=program-edit&id=' . $programId);
            exit;
        }
    }

    /**
     * Publish a draft program (change status to 'active').
     * Restricted to Program Owners. Validates CSRF token.
     *
     * @param int|null $programId Program ID (from route parameter or $_GET['id']).
     * @return void
     */
    public function publish(?int $programId = null): void
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

        $programId = $programId ?? (int) ($_GET['id'] ?? 0);
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

        try {
            $this->programService->publishProgram($programId, $userId, $ipAddress);

            $_SESSION['flash_success'] = 'Program published successfully.';
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        } catch (RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: index.php?page=program-detail&id=' . $programId);
        exit;
    }

    /**
     * Close an active program (change status to 'closed').
     * Restricted to Program Owners. Validates CSRF token.
     *
     * @param int|null $programId Program ID (from route parameter or $_GET['id']).
     * @return void
     */
    public function close(?int $programId = null): void
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

        $programId = $programId ?? (int) ($_GET['id'] ?? 0);
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

        try {
            $this->programService->closeProgram($programId, $userId, $ipAddress);

            $_SESSION['flash_success'] = 'Program closed successfully.';
        } catch (RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: index.php?page=program-detail&id=' . $programId);
        exit;
    }

    /**
     * Enroll the authenticated researcher in a program.
     * Restricted to Researchers. Validates CSRF token.
     *
     * @param int|null $programId Program ID (from route parameter or $_GET['id']).
     * @return void
     */
    public function enroll(?int $programId = null): void
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

        $programId = $programId ?? (int) ($_GET['id'] ?? 0);
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $enrolled = $this->userProgramRepository->enroll($userId, $programId);

        if ($enrolled) {
            $_SESSION['flash_success'] = 'Successfully enrolled in the program.';
        } else {
            $_SESSION['flash_error'] = 'You are already enrolled in this program.';
        }

        header('Location: index.php?page=program-detail&id=' . $programId);
        exit;
    }

    /**
     * Save (bookmark) a program for the authenticated researcher.
     * Restricted to Researchers. Validates CSRF token.
     *
     * @param int|null $programId Program ID (from route parameter or $_GET['id']).
     * @return void
     */
    public function saveProgram(?int $programId = null): void
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

        $programId = $programId ?? (int) ($_GET['id'] ?? 0);
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $saved = $this->savedProgramRepository->save($userId, $programId);

        if ($saved) {
            $_SESSION['flash_success'] = 'Program saved to your bookmarks.';
        } else {
            $_SESSION['flash_error'] = 'Program is already in your bookmarks.';
        }

        header('Location: index.php?page=program-detail&id=' . $programId);
        exit;
    }

    /**
     * Remove a saved (bookmarked) program for the authenticated researcher.
     * Restricted to Researchers. Validates CSRF token.
     *
     * @param int|null $programId Program ID (from route parameter or $_GET['id']).
     * @return void
     */
    public function unsaveProgram(?int $programId = null): void
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

        $programId = $programId ?? (int) ($_GET['id'] ?? 0);
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $removed = $this->savedProgramRepository->unsave($userId, $programId);

        if ($removed) {
            $_SESSION['flash_success'] = 'Program removed from your bookmarks.';
        } else {
            $_SESSION['flash_error'] = 'Program was not in your bookmarks.';
        }

        // Redirect back to referring page if it's the saved-programs list
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if (str_contains($referer, 'page=saved-programs')) {
            header('Location: index.php?page=saved-programs');
        } else {
            header('Location: index.php?page=program-detail&id=' . $programId);
        }
        exit;
    }

    /**
     * List all programs saved (bookmarked) by the authenticated researcher.
     * Restricted to Researchers.
     *
     * @return void
     */
    public function savedPrograms(): void
    {
        $middleware = new ResearcherMiddleware();
        $middleware->handle();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $programs = $this->savedProgramRepository->getSavedByUserId($userId);

        $csrfToken = $this->validationService->generateCsrfToken(session_id());

        // Retrieve flash messages
        $success = $_SESSION['flash_success'] ?? null;
        $error = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $title = 'SecureBounty | Saved Programs';
        $activePage = 'saved-programs';

        include __DIR__ . '/../view/programs/saved.php';
    }

    /**
     * Add a top-level comment to a program discussion (POST).
     * Restricted to authenticated users who are enrolled researchers or the program owner.
     * Validates CSRF token.
     *
     * @return void
     */
    public function addProgramComment(): void
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

        $programId = (int) ($_POST['program_id'] ?? 0);
        $body = trim($_POST['body'] ?? '');
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $roleId = (int) ($_SESSION['role_id'] ?? 0);

        if ($body === '') {
            $_SESSION['flash_error'] = 'Comment body cannot be empty.';
            header('Location: index.php?page=program-detail&id=' . $programId);
            exit;
        }

        // Access check: must be the program owner or an enrolled researcher
        $program = $this->programRepository->findById($programId);
        if ($program === null) {
            $_SESSION['flash_error'] = 'Program not found.';
            header('Location: index.php?page=programs');
            exit;
        }

        $isOwner = ($roleId === 2 && (int) $program['owner_id'] === $userId);
        $isResearcher = ($roleId === 3);

        if (!$isOwner && !$isResearcher) {
            http_response_code(403);
            echo 'Access denied.';
            return;
        }

        $this->programCommentRepository->create($programId, $userId, $body);
        $_SESSION['flash_success'] = 'Comment posted.';

        header('Location: index.php?page=program-detail&id=' . $programId . '#comments');
        exit;
    }

    /**
     * Add a threaded reply to an existing program comment (POST).
     * Restricted to authenticated users who are enrolled researchers or the program owner.
     * Validates CSRF token.
     *
     * @return void
     */
    public function addProgramReply(): void
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

        $programId = (int) ($_POST['program_id'] ?? 0);
        $parentId = (int) ($_POST['parent_id'] ?? 0);
        $body = trim($_POST['body'] ?? '');
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $roleId = (int) ($_SESSION['role_id'] ?? 0);

        if ($body === '') {
            $_SESSION['flash_error'] = 'Reply body cannot be empty.';
            header('Location: index.php?page=program-detail&id=' . $programId . '#comments');
            exit;
        }

        if ($parentId <= 0) {
            $_SESSION['flash_error'] = 'Invalid parent comment.';
            header('Location: index.php?page=program-detail&id=' . $programId . '#comments');
            exit;
        }

        // Access check: must be the program owner or an enrolled researcher
        $program = $this->programRepository->findById($programId);
        if ($program === null) {
            $_SESSION['flash_error'] = 'Program not found.';
            header('Location: index.php?page=programs');
            exit;
        }

        $isOwner = ($roleId === 2 && (int) $program['owner_id'] === $userId);
        $isResearcher = ($roleId === 3);

        if (!$isOwner && !$isResearcher) {
            http_response_code(403);
            echo 'Access denied.';
            return;
        }

        // Verify parent comment belongs to this program
        $parentComment = $this->programCommentRepository->findById($parentId);
        if ($parentComment === null || (int) $parentComment['program_id'] !== $programId) {
            $_SESSION['flash_error'] = 'Invalid parent comment.';
            header('Location: index.php?page=program-detail&id=' . $programId . '#comments');
            exit;
        }

        $this->programCommentRepository->create($programId, $userId, $body, $parentId);
        $_SESSION['flash_success'] = 'Reply posted.';

        header('Location: index.php?page=program-detail&id=' . $programId . '#comments');
        exit;
    }
}
