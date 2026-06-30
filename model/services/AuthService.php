<?php

require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/ActivityLogService.php';

/**
 * AuthService
 *
 * Handles user registration, authentication, session management,
 * and password hashing. Uses UserRepository for data access and
 * ActivityLogService for audit logging.
 *
 * @see Requirement 1.1 — Create user account with selected role
 * @see Requirement 1.2 — Detect duplicate email on registration
 * @see Requirement 1.3 — Display validation errors for invalid fields
 * @see Requirement 1.4 — Hash passwords using secure algorithm (Bcrypt)
 * @see Requirement 1.5 — Log registration event
 * @see Requirement 2.1 — Authenticated session creation
 * @see Requirement 2.2 — Generic error on invalid credentials
 * @see Requirement 2.3 — Session destruction on logout
 * @see Requirement 2.5 — Redirect to login on expired session
 */
class AuthService
{
    private UserRepository $userRepository;
    private ActivityLogService $activityLogService;

    /**
     * @param UserRepository    $userRepository    Repository for user data access.
     * @param ActivityLogService $activityLogService Service for audit logging.
     */
    public function __construct(UserRepository $userRepository, ActivityLogService $activityLogService)
    {
        $this->userRepository = $userRepository;
        $this->activityLogService = $activityLogService;
    }

    /**
     * Register a new user account.
     *
     * Validates inputs, checks email uniqueness, hashes the password,
     * creates the user record, and logs the registration event.
     *
     * @param string $firstName User's first name.
     * @param string $lastName  User's last name.
     * @param string $email     User's email address.
     * @param string $password  Plain-text password.
     * @param int    $roleId    Selected role ID (Program_Owner or Researcher).
     * @return array ['success' => bool, 'errors' => array, 'user_id' => int|null]
     */
    public function register(
        string $firstName,
        string $lastName,
        string $email,
        string $password,
        int $roleId
    ): array {
        $errors = [];

        // Validate required fields
        if (trim($firstName) === '') {
            $errors['first_name'] = 'First name is required';
        }
        if (trim($lastName) === '') {
            $errors['last_name'] = 'Last name is required';
        }
        if (trim($email) === '') {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email format is invalid';
        }
        if (trim($password) === '') {
            $errors['password'] = 'Password is required';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'user_id' => null];
        }

        // Check email uniqueness
        $existingUser = $this->userRepository->findByEmail($email);
        if ($existingUser !== null) {
            $errors['email'] = 'This email is already registered';
            return ['success' => false, 'errors' => $errors, 'user_id' => null];
        }

        // Hash password and create user
        $passwordHash = $this->hashPassword($password);
        $userId = $this->userRepository->create($roleId, $firstName, $lastName, $email, $passwordHash);

        // Log registration activity
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $this->activityLogService->log(
            $userId,
            'user.register',
            'user',
            $userId,
            ['role_id' => $roleId],
            $ipAddress
        );

        return ['success' => true, 'errors' => [], 'user_id' => $userId];
    }

    /**
     * Authenticate a user with email and password.
     *
     * On success: starts/regenerates session, stores user data.
     * On failure: returns generic error (no distinction between wrong email/password).
     *
     * @param string $email    User's email address.
     * @param string $password Plain-text password to verify.
     * @return array ['success' => bool, 'error' => string|null, 'user' => array|null]
     */
    public function login(string $email, string $password): array
    {
        $genericError = 'Invalid email or password';

        // Find user by email
        $user = $this->userRepository->findByEmail($email);

        if ($user === null) {
            return ['success' => false, 'error' => $genericError, 'user' => null];
        }

        // Verify password
        if (!$this->verifyPassword($password, $user['password_hash'])) {
            return ['success' => false, 'error' => $genericError, 'user' => null];
        }

        // Check account status
        if ($user['status'] !== 'active') {
            return ['success' => false, 'error' => $genericError, 'user' => null];
        }

        // Start session and store user data
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->regenerateSession();

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role_id'] = (int) $user['role_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];

        // Log login activity
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $this->activityLogService->log(
            (int) $user['id'],
            'user.login',
            'user',
            (int) $user['id'],
            null,
            $ipAddress
        );

        return ['success' => true, 'error' => null, 'user' => $user];
    }

    /**
     * Destroy the current session and log out the user.
     *
     * @return void
     */
    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Log logout before destroying session
        if (isset($_SESSION['user_id'])) {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
            $this->activityLogService->log(
                (int) $_SESSION['user_id'],
                'user.logout',
                'user',
                (int) $_SESSION['user_id'],
                null,
                $ipAddress
            );
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Hash a plain-text password using Bcrypt.
     *
     * @param string $password Plain-text password.
     * @return string Bcrypt hash string.
     */
    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * Verify a plain-text password against a stored hash.
     *
     * @param string $password Plain-text password to check.
     * @param string $hash     Stored password hash.
     * @return bool True if the password matches the hash.
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Get the currently authenticated user from the session.
     *
     * Returns the full user record (with role name) if a session is active
     * and contains a valid user_id. Returns null otherwise.
     *
     * @return array|null User row with role_name, or null if not authenticated.
     */
    public function getCurrentUser(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        return $this->userRepository->findById((int) $_SESSION['user_id']);
    }

    /**
     * Regenerate the session ID to prevent session fixation attacks.
     *
     * @return void
     */
    public function regenerateSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        session_regenerate_id(true);
    }
}
