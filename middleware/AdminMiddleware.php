<?php

require_once __DIR__ . '/MiddlewareInterface.php';
require_once __DIR__ . '/AuthMiddleware.php';

/**
 * AdminMiddleware
 *
 * Verifies that the authenticated user has the Admin role (role_id = 1).
 * Invokes AuthMiddleware first to ensure a valid session exists,
 * then checks the role. Returns HTTP 403 if the user is not an Admin.
 *
 * @see Requirement 3.2 — Admin access to user management, program oversight, configuration
 * @see Requirement 3.5 — Return HTTP 403 for unauthorized route access
 */
class AdminMiddleware implements MiddlewareInterface
{
    private const ADMIN_ROLE_ID = 1;

    /**
     * Verify the current user is authenticated and has Admin role.
     *
     * @return void
     */
    public function handle(): void
    {
        // First, ensure user is authenticated
        $authMiddleware = new AuthMiddleware();
        $authMiddleware->handle();

        // Check role_id matches Admin
        if (!isset($_SESSION['role_id']) || (int) $_SESSION['role_id'] !== self::ADMIN_ROLE_ID) {
            http_response_code(403);
            echo 'Access denied. You do not have permission to access this resource.';
            exit;
        }
    }
}
