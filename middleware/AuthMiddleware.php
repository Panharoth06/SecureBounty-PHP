<?php

require_once __DIR__ . '/MiddlewareInterface.php';
require_once __DIR__ . '/../controller/HttpRedirect.php';

/**
 * AuthMiddleware
 *
 * Verifies that the user has an active authenticated session.
 * Starts the session if not already active, checks for user_id in session,
 * and verifies the user account is still active. Redirects to login if
 * authentication fails.
 *
 * @see Requirement 2.4 — Enforce role-based access control on protected routes
 * @see Requirement 2.5 — Redirect to login on expired/invalid session
 * @see Requirement 3.1 — Enforce three distinct roles
 */
class AuthMiddleware implements MiddlewareInterface
{
    /**
     * Check that a valid, active user session exists.
     *
     * - Starts session if not already started
     * - Checks $_SESSION['user_id'] is set
     * - Verifies user account status is 'active' via database lookup
     * - Redirects to login page if any check fails
     *
     * @return void
     */
    public function handle(): void
    {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check if user_id exists in session
        if (!isset($_SESSION['user_id'])) {
            $this->redirectToLogin();
            return;
        }

        // Verify user is still active in the database
        $conn = require __DIR__ . '/../config/database.php';

        $stmt = $conn->prepare('SELECT status FROM users WHERE id = ?');
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user === null || $user['status'] !== 'active') {
            // User not found or deactivated — destroy session and redirect
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
            $this->redirectToLogin();
            return;
        }
    }

    /**
     * Redirect the user to the login page and terminate execution.
     *
     * @return void
     */
    private function redirectToLogin(): void
    {
        redirectTo('index.php?page=login');
    }
}
