<?php

require_once __DIR__ . '/../model/repository/UserRepository.php';
require_once __DIR__ . '/../model/repository/ActivityLogRepository.php';
require_once __DIR__ . '/../model/services/AuthService.php';
require_once __DIR__ . '/../model/services/ValidationService.php';
require_once __DIR__ . '/../model/services/ActivityLogService.php';
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

    public function __construct()
    {
        $conn = require __DIR__ . '/../config/database.php';

        $userRepository = new UserRepository($conn);
        $activityLogRepository = new ActivityLogRepository($conn);
        $activityLogService = new ActivityLogService($activityLogRepository);

        $this->authService = new AuthService($userRepository, $activityLogService);
        $this->validationService = new ValidationService($conn);
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
