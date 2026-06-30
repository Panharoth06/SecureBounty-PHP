<?php

require_once __DIR__ . '/MiddlewareInterface.php';
require_once __DIR__ . '/AuthMiddleware.php';

/**
 * ProgramOwnerMiddleware
 *
 * Verifies that the authenticated user has the Program_Owner role (role_id = 2).
 * Invokes AuthMiddleware first to ensure a valid session exists,
 * then checks the role. Returns HTTP 403 if the user is not a Program Owner.
 *
 * @see Requirement 3.3 — Program_Owner access to program creation, management, report review
 * @see Requirement 3.5 — Return HTTP 403 for unauthorized route access
 */
class ProgramOwnerMiddleware implements MiddlewareInterface
{
    private const PROGRAM_OWNER_ROLE_ID = 2;

    /**
     * Verify the current user is authenticated and has Program_Owner role.
     *
     * @return void
     */
    public function handle(): void
    {
        // First, ensure user is authenticated
        $authMiddleware = new AuthMiddleware();
        $authMiddleware->handle();

        // Check role_id matches Program_Owner
        if (!isset($_SESSION['role_id']) || (int) $_SESSION['role_id'] !== self::PROGRAM_OWNER_ROLE_ID) {
            http_response_code(403);
            echo 'Access denied. You do not have permission to access this resource.';
            exit;
        }
    }
}
