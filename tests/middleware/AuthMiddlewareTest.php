<?php

declare(strict_types=1);

namespace Tests\Middleware;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AuthMiddleware.
 *
 * Tests verify the middleware structure and contract.
 * Integration tests requiring DB are skipped if no connection available.
 *
 * @see Requirement 2.4 — Enforce role-based access control on protected routes
 * @see Requirement 2.5 — Redirect to login on expired/invalid session
 */
class AuthMiddlewareTest extends TestCase
{
    /**
     * Test that AuthMiddleware implements MiddlewareInterface.
     */
    public function testImplementsMiddlewareInterface(): void
    {
        require_once dirname(__DIR__, 2) . '/middleware/AuthMiddleware.php';

        $reflection = new \ReflectionClass(\AuthMiddleware::class);
        $this->assertTrue($reflection->implementsInterface(\MiddlewareInterface::class));
    }

    /**
     * Test that AuthMiddleware has the handle method.
     */
    public function testHasHandleMethod(): void
    {
        require_once dirname(__DIR__, 2) . '/middleware/AuthMiddleware.php';

        $reflection = new \ReflectionClass(\AuthMiddleware::class);
        $this->assertTrue($reflection->hasMethod('handle'));

        $method = $reflection->getMethod('handle');
        $this->assertTrue($method->isPublic());
        $this->assertEquals(0, $method->getNumberOfParameters());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testPassesThroughForActiveUser(): void
    {
        // Check if DB is available before running
        try {
            \Tests\TestDatabaseHelper::getConnection();
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }

        \Tests\TestDatabaseHelper::migrate();
        \Tests\TestDatabaseHelper::seed();
        $conn = \Tests\TestDatabaseHelper::getConnection();

        // Create an active test user
        $conn->query(
            "INSERT INTO users (id, role_id, first_name, last_name, email, password_hash, status)
             VALUES (1, 1, 'Test', 'Admin', 'admin@test.com', '\$2y\$10\$abcdefghijklmnopqrstuuWRb/wPJ0fVH0Jf0RRPqQDfAx.LnZ1GS', 'active')"
        );

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user_id'] = 1;
        $_SESSION['role_id'] = 1;

        require_once dirname(__DIR__, 2) . '/middleware/AuthMiddleware.php';

        $middleware = new \AuthMiddleware();
        $middleware->handle();

        // If we reach here, middleware passed through successfully
        $this->assertEquals(1, $_SESSION['user_id']);

        \Tests\TestDatabaseHelper::cleanUp();
    }
}
