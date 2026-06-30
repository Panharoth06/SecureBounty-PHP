<?php

declare(strict_types=1);

namespace Tests\Middleware;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for role-based middleware (Admin, ProgramOwner, Researcher).
 *
 * Tests verify middleware structure, interface compliance, and role constants.
 * Integration tests requiring DB are skipped if no connection available.
 *
 * @see Requirement 3.2 — Admin access to management features
 * @see Requirement 3.3 — Program_Owner access to program management
 * @see Requirement 3.4 — Researcher access to program browsing and reporting
 * @see Requirement 3.5 — HTTP 403 for unauthorized route access
 */
class RoleMiddlewareTest extends TestCase
{
    /**
     * Test that AdminMiddleware implements MiddlewareInterface.
     */
    public function testAdminMiddlewareImplementsInterface(): void
    {
        require_once dirname(__DIR__, 2) . '/middleware/AdminMiddleware.php';

        $reflection = new \ReflectionClass(\AdminMiddleware::class);
        $this->assertTrue($reflection->implementsInterface(\MiddlewareInterface::class));
    }

    /**
     * Test that ProgramOwnerMiddleware implements MiddlewareInterface.
     */
    public function testProgramOwnerMiddlewareImplementsInterface(): void
    {
        require_once dirname(__DIR__, 2) . '/middleware/ProgramOwnerMiddleware.php';

        $reflection = new \ReflectionClass(\ProgramOwnerMiddleware::class);
        $this->assertTrue($reflection->implementsInterface(\MiddlewareInterface::class));
    }

    /**
     * Test that ResearcherMiddleware implements MiddlewareInterface.
     */
    public function testResearcherMiddlewareImplementsInterface(): void
    {
        require_once dirname(__DIR__, 2) . '/middleware/ResearcherMiddleware.php';

        $reflection = new \ReflectionClass(\ResearcherMiddleware::class);
        $this->assertTrue($reflection->implementsInterface(\MiddlewareInterface::class));
    }

    /**
     * Test that AdminMiddleware uses correct role constant.
     */
    public function testAdminMiddlewareUsesCorrectRoleId(): void
    {
        require_once dirname(__DIR__, 2) . '/middleware/AdminMiddleware.php';

        $reflection = new \ReflectionClass(\AdminMiddleware::class);
        $constant = $reflection->getReflectionConstant('ADMIN_ROLE_ID');

        $this->assertNotFalse($constant);
        $this->assertEquals(1, $constant->getValue());
    }

    /**
     * Test that ProgramOwnerMiddleware uses correct role constant.
     */
    public function testProgramOwnerMiddlewareUsesCorrectRoleId(): void
    {
        require_once dirname(__DIR__, 2) . '/middleware/ProgramOwnerMiddleware.php';

        $reflection = new \ReflectionClass(\ProgramOwnerMiddleware::class);
        $constant = $reflection->getReflectionConstant('PROGRAM_OWNER_ROLE_ID');

        $this->assertNotFalse($constant);
        $this->assertEquals(2, $constant->getValue());
    }

    /**
     * Test that ResearcherMiddleware uses correct role constant.
     */
    public function testResearcherMiddlewareUsesCorrectRoleId(): void
    {
        require_once dirname(__DIR__, 2) . '/middleware/ResearcherMiddleware.php';

        $reflection = new \ReflectionClass(\ResearcherMiddleware::class);
        $constant = $reflection->getReflectionConstant('RESEARCHER_ROLE_ID');

        $this->assertNotFalse($constant);
        $this->assertEquals(3, $constant->getValue());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testAdminMiddlewarePassesForAdminUser(): void
    {
        try {
            \Tests\TestDatabaseHelper::getConnection();
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }

        \Tests\TestDatabaseHelper::migrate();
        \Tests\TestDatabaseHelper::seed();
        $conn = \Tests\TestDatabaseHelper::getConnection();

        $conn->query(
            "INSERT INTO users (id, role_id, first_name, last_name, email, password_hash, status)
             VALUES (1, 1, 'Test', 'Admin', 'admin@test.com', '\$2y\$10\$abcdefghijklmnopqrstuuWRb/wPJ0fVH0Jf0RRPqQDfAx.LnZ1GS', 'active')"
        );

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user_id'] = 1;
        $_SESSION['role_id'] = 1;

        require_once dirname(__DIR__, 2) . '/middleware/AdminMiddleware.php';

        $middleware = new \AdminMiddleware();
        $middleware->handle();

        // If we reach here, middleware passed
        $this->assertEquals(1, $_SESSION['role_id']);

        \Tests\TestDatabaseHelper::cleanUp();
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProgramOwnerMiddlewarePassesForOwnerUser(): void
    {
        try {
            \Tests\TestDatabaseHelper::getConnection();
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }

        \Tests\TestDatabaseHelper::migrate();
        \Tests\TestDatabaseHelper::seed();
        $conn = \Tests\TestDatabaseHelper::getConnection();

        $conn->query(
            "INSERT INTO users (id, role_id, first_name, last_name, email, password_hash, status)
             VALUES (2, 2, 'Test', 'Owner', 'owner@test.com', '\$2y\$10\$abcdefghijklmnopqrstuuWRb/wPJ0fVH0Jf0RRPqQDfAx.LnZ1GS', 'active')"
        );

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user_id'] = 2;
        $_SESSION['role_id'] = 2;

        require_once dirname(__DIR__, 2) . '/middleware/ProgramOwnerMiddleware.php';

        $middleware = new \ProgramOwnerMiddleware();
        $middleware->handle();

        // If we reach here, middleware passed
        $this->assertEquals(2, $_SESSION['role_id']);

        \Tests\TestDatabaseHelper::cleanUp();
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testResearcherMiddlewarePassesForResearcherUser(): void
    {
        try {
            \Tests\TestDatabaseHelper::getConnection();
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }

        \Tests\TestDatabaseHelper::migrate();
        \Tests\TestDatabaseHelper::seed();
        $conn = \Tests\TestDatabaseHelper::getConnection();

        $conn->query(
            "INSERT INTO users (id, role_id, first_name, last_name, email, password_hash, status)
             VALUES (3, 3, 'Test', 'Researcher', 'researcher@test.com', '\$2y\$10\$abcdefghijklmnopqrstuuWRb/wPJ0fVH0Jf0RRPqQDfAx.LnZ1GS', 'active')"
        );

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user_id'] = 3;
        $_SESSION['role_id'] = 3;

        require_once dirname(__DIR__, 2) . '/middleware/ResearcherMiddleware.php';

        $middleware = new \ResearcherMiddleware();
        $middleware->handle();

        // If we reach here, middleware passed
        $this->assertEquals(3, $_SESSION['role_id']);

        \Tests\TestDatabaseHelper::cleanUp();
    }
}
