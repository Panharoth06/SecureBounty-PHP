<?php

require_once __DIR__ . '/../model/repository/ProgramRepository.php';
require_once __DIR__ . '/../model/repository/RewardPolicyRepository.php';
require_once __DIR__ . '/../model/repository/ActivityLogRepository.php';
require_once __DIR__ . '/../model/services/RewardPolicyService.php';
require_once __DIR__ . '/../model/services/ActivityLogService.php';
require_once __DIR__ . '/../model/services/ValidationService.php';
require_once __DIR__ . '/../middleware/ProgramOwnerMiddleware.php';
require_once __DIR__ . '/HttpRedirect.php';

/**
 * RewardPolicyController
 *
 * Handles reward policy management (create, edit, delete) for programs.
 * All actions are restricted to the Program Owner who owns the parent program.
 * Validates CSRF tokens on all state-changing actions.
 *
 * @see Requirement 5.1 — Create reward policy with severity and reward amount
 * @see Requirement 5.3 — Update reward policy and log activity
 * @see Requirement 5.4 — Delete policy only if no accepted reports reference it
 * @see Requirement 5.5 — Reject deletion with error if accepted reports exist
 */
class RewardPolicyController
{
    private RewardPolicyService $rewardPolicyService;
    private RewardPolicyRepository $rewardPolicyRepository;
    private ProgramRepository $programRepository;
    private ValidationService $validationService;

    public function __construct()
    {
        $conn = require __DIR__ . '/../config/database.php';

        $this->rewardPolicyRepository = new RewardPolicyRepository($conn);
        $this->programRepository = new ProgramRepository($conn);
        $activityLogService = new ActivityLogService(new ActivityLogRepository($conn));
        $this->rewardPolicyService = new RewardPolicyService($this->rewardPolicyRepository, $activityLogService);
        $this->validationService = new ValidationService($conn);
    }

    /**
     * Display the create reward policy form.
     *
     * @return void
     */
    public function create(): void
    {
        $this->guard();

        $programId = (int) ($_GET['program_id'] ?? 0);
        $program = $this->requireOwnedProgram($programId);

        $csrfToken = $this->validationService->generateCsrfToken(session_id());
        $errors = $_SESSION['flash_errors'] ?? [];
        $oldInput = $_SESSION['flash_old_input'] ?? [];
        unset($_SESSION['flash_errors'], $_SESSION['flash_old_input']);

        $mode = 'create';
        $policy = null;
        $title = 'SecureBounty | Add Reward Policy';
        $activePage = 'programs';

        include __DIR__ . '/../view/programs/reward-policy-form.php';
    }

    /**
     * Process the create reward policy submission.
     *
     * @return void
     */
    public function processCreate(): void
    {
        $this->guard();

        if (!$this->validationService->validateCsrfToken($_POST['csrf_token'] ?? '', session_id())) {
            http_response_code(403);
            echo 'CSRF validation failed. Please try again.';
            return;
        }

        $programId = (int) ($_POST['program_id'] ?? 0);
        $program = $this->requireOwnedProgram($programId);

        $severity = $this->validationService->sanitizeInput($_POST['severity'] ?? '');
        $minReward = (float) ($_POST['min_reward'] ?? 0);
        $maxReward = (float) ($_POST['max_reward'] ?? 0);
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

        $result = $this->rewardPolicyService->createPolicy(
            $programId,
            $severity,
            $minReward,
            $maxReward,
            $userId,
            $ipAddress
        );

        if (!$result['success']) {
            $_SESSION['flash_errors'] = $result['errors'];
            $_SESSION['flash_old_input'] = [
                'severity' => $severity,
                'min_reward' => $minReward,
                'max_reward' => $maxReward,
            ];
            redirectTo('index.php?page=reward-policy-create&program_id=' . $programId);
        }

        $_SESSION['flash_success'] = 'Reward policy added.';
        redirectTo('index.php?page=program-edit&id=' . $programId);
    }

    /**
     * Display the edit reward policy form.
     *
     * @return void
     */
    public function edit(): void
    {
        $this->guard();

        $policyId = (int) ($_GET['id'] ?? 0);
        $programId = (int) ($_GET['program_id'] ?? 0);

        $policy = $this->rewardPolicyRepository->findById($policyId);
        if ($policy === null || (int) $policy['program_id'] !== $programId) {
            $_SESSION['flash_error'] = 'Reward policy not found.';
            redirectTo('index.php?page=program-edit&id=' . $programId);
        }

        $program = $this->requireOwnedProgram($programId);

        $csrfToken = $this->validationService->generateCsrfToken(session_id());
        $errors = $_SESSION['flash_errors'] ?? [];
        $oldInput = $_SESSION['flash_old_input'] ?? [];
        unset($_SESSION['flash_errors'], $_SESSION['flash_old_input']);

        $mode = 'edit';
        $title = 'SecureBounty | Edit Reward Policy';
        $activePage = 'programs';

        include __DIR__ . '/../view/programs/reward-policy-form.php';
    }

    /**
     * Process the edit reward policy submission.
     *
     * @return void
     */
    public function processEdit(): void
    {
        $this->guard();

        if (!$this->validationService->validateCsrfToken($_POST['csrf_token'] ?? '', session_id())) {
            http_response_code(403);
            echo 'CSRF validation failed. Please try again.';
            return;
        }

        $policyId = (int) ($_POST['id'] ?? 0);
        $programId = (int) ($_POST['program_id'] ?? 0);

        $policy = $this->rewardPolicyRepository->findById($policyId);
        if ($policy === null || (int) $policy['program_id'] !== $programId) {
            $_SESSION['flash_error'] = 'Reward policy not found.';
            redirectTo('index.php?page=program-edit&id=' . $programId);
        }

        $this->requireOwnedProgram($programId);

        $minReward = (float) ($_POST['min_reward'] ?? 0);
        $maxReward = (float) ($_POST['max_reward'] ?? 0);
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

        $result = $this->rewardPolicyService->updatePolicy($policyId, $minReward, $maxReward, $userId, $ipAddress);

        if (!$result['success']) {
            $_SESSION['flash_errors'] = $result['errors'];
            $_SESSION['flash_old_input'] = ['min_reward' => $minReward, 'max_reward' => $maxReward];
            redirectTo('index.php?page=reward-policy-edit&id=' . $policyId . '&program_id=' . $programId);
        }

        $_SESSION['flash_success'] = 'Reward policy updated.';
        redirectTo('index.php?page=program-edit&id=' . $programId);
    }

    /**
     * Delete a reward policy (POST, CSRF-protected).
     *
     * @return void
     */
    public function delete(): void
    {
        $this->guard();

        if (!$this->validationService->validateCsrfToken($_POST['csrf_token'] ?? '', session_id())) {
            http_response_code(403);
            echo 'CSRF validation failed. Please try again.';
            return;
        }

        $policyId = (int) ($_POST['id'] ?? ($_GET['id'] ?? 0));
        $programId = (int) ($_POST['program_id'] ?? ($_GET['program_id'] ?? 0));

        $policy = $this->rewardPolicyRepository->findById($policyId);
        if ($policy === null || (int) $policy['program_id'] !== $programId) {
            $_SESSION['flash_error'] = 'Reward policy not found.';
            redirectTo('index.php?page=program-edit&id=' . $programId);
        }

        $this->requireOwnedProgram($programId);

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

        $result = $this->rewardPolicyService->deletePolicy($policyId, $userId, $ipAddress);

        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['errors']['policy'] ?? 'Unable to delete reward policy.';
        } else {
            $_SESSION['flash_success'] = 'Reward policy deleted.';
        }

        redirectTo('index.php?page=program-edit&id=' . $programId);
    }

    /**
     * Enforce Program Owner authentication and ensure the session is started.
     *
     * @return void
     */
    private function guard(): void
    {
        (new ProgramOwnerMiddleware())->handle();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Load a program and verify the current user owns it.
     * Redirects with an error if the program is missing or not owned.
     *
     * @param int $programId
     * @return array The program row.
     */
    private function requireOwnedProgram(int $programId): array
    {
        $program = $this->programRepository->findById($programId);

        if ($program === null) {
            $_SESSION['flash_error'] = 'Program not found.';
            redirectTo('index.php?page=programs');
        }

        if ((int) $program['owner_id'] !== (int) ($_SESSION['user_id'] ?? 0)) {
            http_response_code(403);
            echo 'Access denied. You do not own this program.';
            exit;
        }

        return $program;
    }
}
