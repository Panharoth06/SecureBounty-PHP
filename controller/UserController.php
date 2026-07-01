<?php

require_once __DIR__ . '/../model/repository/UserRepository.php';
require_once __DIR__ . '/../model/repository/ActivityLogRepository.php';
require_once __DIR__ . '/../model/repository/LeaderboardRepository.php';
require_once __DIR__ . '/../model/services/AuthService.php';
require_once __DIR__ . '/../model/services/ValidationService.php';
require_once __DIR__ . '/../model/services/ActivityLogService.php';
require_once __DIR__ . '/../model/services/ProfileService.php';
require_once __DIR__ . '/../model/services/ImageService.php';
require_once __DIR__ . '/../model/services/LeaderboardService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/HttpRedirect.php';

/**
 * UserController
 *
 * Handles user registration, authentication (login/logout), and profile display.
 * Validates CSRF tokens on all state-changing actions and logs registration/login
 * events via ActivityLogService.
 *
 * @see Requirement 1.1 — Create user account with selected role
 * @see Requirement 1.2 — Detect duplicate email on registration
 * @see Requirement 1.3 — Display validation errors for invalid fields
 * @see Requirement 1.5 — Log registration event
 * @see Requirement 2.1 — Authenticated session creation
 * @see Requirement 2.2 — Generic error on invalid credentials
 * @see Requirement 2.3 — Session destruction on logout
 * @see Requirement 2.5 — Redirect to login on expired session
 * @see Requirement 14.4 — CSRF token validation on all state-changing form submissions
 */
class UserController
{
    private AuthService $authService;
    private ValidationService $validationService;
    private ProfileService $profileService;
    private ImageService $imageService;
    private LeaderboardService $leaderboardService;
    private UserRepository $userRepository;

    public function __construct()
    {
        $conn = require __DIR__ . '/../config/database.php';

        $userRepository = new UserRepository($conn);
        $activityLogRepository = new ActivityLogRepository($conn);
        $activityLogService = new ActivityLogService($activityLogRepository);
        $leaderboardRepository = new LeaderboardRepository($conn);

        $this->authService = new AuthService($userRepository, $activityLogService);
        $this->validationService = new ValidationService($conn);
        $this->profileService = new ProfileService($userRepository);
        $this->imageService = new ImageService();
        $this->leaderboardService = new LeaderboardService($leaderboardRepository);
        $this->userRepository = $userRepository;
    }

    /**
     * Display the registration form with a CSRF token.
     *
     * @return void
     */
    public function register(): void
    {
        // Ensure session is started for CSRF token generation
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $csrfToken = $this->validationService->generateCsrfToken(session_id());

        // Retrieve any flash messages from previous request
        $errors = $_SESSION['flash_errors'] ?? [];
        $success = $_SESSION['flash_success'] ?? null;
        $oldInput = $_SESSION['flash_old_input'] ?? [];
        unset($_SESSION['flash_errors'], $_SESSION['flash_success'], $_SESSION['flash_old_input']);

        $title = 'SecureBounty | Create Account';
        $activePage = 'register';

        include __DIR__ . '/../view/register.php';
    }

    /**
     * Process the registration form submission.
     * Validates CSRF token, sanitizes input, calls AuthService, handles errors.
     *
     * @return void
     */
    public function processRegister(): void
    {
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
        $firstName = $this->validationService->sanitizeInput($_POST['first_name'] ?? '');
        $lastName = $this->validationService->sanitizeInput($_POST['last_name'] ?? '');
        $email = $this->validationService->sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $role = $this->validationService->sanitizeInput($_POST['role'] ?? 'researcher');

        // Validate password confirmation
        $errors = [];
        if ($password !== $confirmPassword) {
            $errors['confirm_password'] = 'Passwords do not match';
        }

        // Map role name to role_id
        $roleId = $this->mapRoleToId($role);
        if ($roleId === null) {
            $errors['role'] = 'Invalid role selected';
        }

        // If local validation errors exist, redirect back with errors
        if (!empty($errors)) {
            $_SESSION['flash_errors'] = $errors;
            $_SESSION['flash_old_input'] = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'role' => $role,
            ];
            redirectTo('index.php?page=register');
        }

        // Call AuthService to register
        $result = $this->authService->register($firstName, $lastName, $email, $password, $roleId);

        if (!$result['success']) {
            $_SESSION['flash_errors'] = $result['errors'];
            $_SESSION['flash_old_input'] = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'role' => $role,
            ];
            redirectTo('index.php?page=register');
        }

        // Registration successful — redirect to login with success message
        $_SESSION['flash_success'] = 'Account created successfully. Please sign in.';
        redirectTo('index.php?page=login');
    }

    /**
     * Display the login form with a CSRF token.
     *
     * @return void
     */
    public function login(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $csrfToken = $this->validationService->generateCsrfToken(session_id());

        // Retrieve any flash messages
        $errors = $_SESSION['flash_errors'] ?? [];
        $success = $_SESSION['flash_success'] ?? null;
        $error = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_errors'], $_SESSION['flash_success'], $_SESSION['flash_error']);

        $title = 'SecureBounty | Secure Login';
        $activePage = 'login';

        include __DIR__ . '/../view/login.php';
    }

    /**
     * Process the login form submission.
     * Validates CSRF token, calls AuthService->login(), handles errors or redirects.
     *
     * @return void
     */
    public function processLogin(): void
    {
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
        $email = $this->validationService->sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Call AuthService to authenticate
        $result = $this->authService->login($email, $password);

        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['error'];
            redirectTo('index.php?page=login');
        }

        // Login successful — redirect to dashboard
        redirectTo('index.php?page=dashboard');
    }

    /**
     * Log out the current user and redirect to login.
     *
     * @return void
     */
    public function logout(): void
    {
        $this->authService->logout();

        // Start a new session to set flash message
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['flash_success'] = 'You have been logged out successfully.';
        redirectTo('index.php?page=login');
    }

    /**
     * Display the user profile page (requires authentication).
     *
     * @return void
     */
    public function profile(): void
    {
        // Enforce authentication
        $authMiddleware = new AuthMiddleware();
        $authMiddleware->handle();

        $user = $this->authService->getCurrentUser();

        if ($user === null) {
            redirectTo('index.php?page=login');
        }

        $title = 'SecureBounty | My Profile';
        $activePage = 'profile';

        include __DIR__ . '/../view/profile.php';
    }

    /**
     * Display the profile edit form pre-populated with current data.
     *
     * @return void
     *
     * @see Requirement 8.1 — Profile edit form pre-populated with current data
     */
    public function editProfile(): void
    {
        // Enforce authentication
        $authMiddleware = new AuthMiddleware();
        $authMiddleware->handle();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $user = $this->authService->getCurrentUser();

        if ($user === null) {
            redirectTo('index.php?page=login');
        }

        $profile = $this->profileService->getProfile((int) $user['id']);

        $csrfToken = $this->validationService->generateCsrfToken(session_id());

        // Retrieve flash errors and old input from previous request
        $errors = $_SESSION['flash_errors'] ?? [];
        $oldInput = $_SESSION['flash_old_input'] ?? [];
        unset($_SESSION['flash_errors'], $_SESSION['flash_old_input']);

        $title = 'SecureBounty | Edit Profile';
        $activePage = 'profile';

        include __DIR__ . '/../view/profile/edit.php';
    }

    /**
     * Process the profile edit form submission.
     *
     * Handles avatar upload, validates profile fields, and saves changes.
     *
     * @return void
     *
     * @see Requirement 8.4 — Resize avatar to 150x150 and store associated with user
     * @see Requirement 8.7 — Reject save with field-specific validation errors
     * @see Requirement 8.8 — Save valid profile changes
     */
    public function processEditProfile(): void
    {
        // Enforce authentication
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

        $user = $this->authService->getCurrentUser();

        if ($user === null) {
            redirectTo('index.php?page=login');
        }

        $userId = (int) $user['id'];

        // Collect POST data
        $data = [
            'display_name' => $_POST['display_name'] ?? '',
            'bio' => $_POST['bio'] ?? '',
            'website_url' => $_POST['website_url'] ?? '',
            'github_url' => $_POST['github_url'] ?? '',
            'linkedin_url' => $_POST['linkedin_url'] ?? '',
            'facebook_url' => $_POST['facebook_url'] ?? '',
            'youtube_url' => $_POST['youtube_url'] ?? '',
            'instagram_url' => $_POST['instagram_url'] ?? '',
        ];

        // Validate profile fields
        $errors = $this->profileService->validateProfile($data);

        // Handle avatar upload if a file was provided
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $avatarPath = $this->imageService->uploadAvatar($_FILES['avatar'], $userId);
                $this->userRepository->updateAvatarPath($userId, $avatarPath);
            } catch (\InvalidArgumentException $e) {
                $errors['avatar'] = $e->getMessage();
            } catch (\RuntimeException $e) {
                $errors['avatar'] = 'Failed to upload avatar. Please try again.';
            }
        }

        // If validation errors, redirect back with errors and old input
        if (!empty($errors)) {
            $_SESSION['flash_errors'] = $errors;
            $_SESSION['flash_old_input'] = $data;
            redirectTo('index.php?page=profile-edit');
        }

        // Save profile changes
        try {
            $this->profileService->updateProfile($userId, $data);
        } catch (\RuntimeException $e) {
            $_SESSION['flash_errors'] = ['general' => 'Failed to update profile. Please try again.'];
            $_SESSION['flash_old_input'] = $data;
            redirectTo('index.php?page=profile-edit');
        }

        // Success — redirect to public profile page
        $_SESSION['flash_success'] = 'Profile updated successfully.';
        redirectTo('index.php?page=public-profile&id=' . $userId);
    }

    /**
     * Display the public profile page for a researcher.
     *
     * Shows avatar, display name, bio, social links, reputation score,
     * rank, and accepted report count.
     *
     * @return void
     *
     * @see Requirement 8.9 — Display avatar, display name, bio, and social links
     * @see Requirement 8.10 — Display reputation score, accepted report count, and rank
     * @see Requirement 8.11 — Avatar placeholder with initials
     */
    public function publicProfile(): void
    {
        // Enforce authentication (any authenticated user can view)
        $authMiddleware = new AuthMiddleware();
        $authMiddleware->handle();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $profileUserId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($profileUserId <= 0) {
            http_response_code(404);
            echo 'User not found.';
            return;
        }

        try {
            $profile = $this->profileService->getPublicProfile($profileUserId);
        } catch (\RuntimeException $e) {
            http_response_code(404);
            echo 'User not found.';
            return;
        }

        // Get leaderboard stats (rank, score, accepted count, severity breakdown)
        $stats = $this->leaderboardService->getResearcherStats($profileUserId);

        // Retrieve any flash messages
        $success = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        $title = 'SecureBounty | Researcher Profile';
        $activePage = 'profile';

        include __DIR__ . '/../view/profile/public.php';
    }

    /**
     * Map role string from form to database role ID.
     *
     * @param string $role Role string ('researcher' or 'owner').
     * @return int|null Role ID or null if invalid.
     */
    private function mapRoleToId(string $role): ?int
    {
        return match (strtolower($role)) {
            'researcher' => 3,
            'owner', 'program_owner' => 2,
            default => null,
        };
    }
}
