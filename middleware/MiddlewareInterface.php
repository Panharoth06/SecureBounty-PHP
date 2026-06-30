<?php

/**
 * MiddlewareInterface
 *
 * Contract for all route middleware. Implementations check session/role
 * requirements and terminate with HTTP 403 if access is denied.
 *
 * @see Requirement 2.4 — Enforce role-based access control on protected routes
 * @see Requirement 3.5 — Return HTTP 403 for unauthorized route access
 */
interface MiddlewareInterface
{
    /**
     * Check if the current session meets access requirements.
     * Sends HTTP 403 and terminates if unauthorized.
     *
     * @return void
     */
    public function handle(): void;
}
