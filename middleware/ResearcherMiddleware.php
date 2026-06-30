<?php

require_once __DIR__ . '/MiddlewareInterface.php';
require_once __DIR__ . '/AuthMiddleware.php';

/**
 * ResearcherMiddleware
 *
 * Verifies that the authenticated user has the Researcher role (role_id = 3).
 * Invokes AuthMiddleware first to ensure a valid session exists,
 * then checks the role. Returns HTTP 403 if the user is not a Researcher.
 *
 * @see Requirement 3.4 — Researcher access to program browsing, enrollment, report submission
 * @see Requirement 3.5 — Return HTTP 403 for unauthorized route access
 */
class ResearcherMiddleware implements MiddlewareInterface
{
    private const RESEARCHER_ROLE_ID = 3;

    /**
     * Verify the current user is authenticated and has Researcher role.
     *
     * @return void
     */
    public function handle(): void
    {
        // First, ensure user is authenticated
        $authMiddleware = new AuthMiddleware();
        $authMiddleware->handle();

        // Check role_id matches Researcher
        if (!isset($_SESSION['role_id']) || (int) $_SESSION['role_id'] !== self::RESEARCHER_ROLE_ID) {
            http_response_code(403);
            echo 'Access denied. You do not have permission to access this resource.';
            exit;
        }
    }
}
