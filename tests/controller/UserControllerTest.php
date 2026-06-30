<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\TestDatabaseHelper;

/**
 * UserControllerTest
 *
 * Tests the UserController methods for registration, login, logout, and profile.
 * Uses a real test database to validate CSRF token generation, user creation,
 * and authentication flows.
 */
class UserControllerTest extends TestCase
{
    private static \mysqli $conn;

    public static function setUpBeforeClass(): void
    {
        self::$conn = TestDatabaseHelper::getConnection();
        TestDatabaseHelper::migrate();
        TestDatabaseHelper::seed();
    }

    protected function setUp(): void
    {
        TestDatabaseHelper::cleanUp();
        TestDatabaseHelper::seed();

        // Reset session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
    }

    public static function tearDownAfterClass(): void
    {
        TestDatabaseHelper::closeConnection();
    }

    /**
     * Test that the UserController can be instantiated without errors.
     */
    public function testControllerInstantiation(): void
    {
        require_once __DIR__ . '/../../controller/UserController.php';

        $controller = new UserController();
        $this->assertInstanceOf(UserController::class, $controller);
    }

    /**
     * Test that mapRoleToId correctly maps role names to IDs.
     * Uses reflection to access private method.
     */
    public function testMapRoleToId(): void
    {
        require_once __DIR__ . '/../../controller/UserController.php';

        $controller = new UserController();
        $reflection = new \ReflectionMethod($controller, 'mapRoleToId');
        $reflection->setAccessible(true);

        $this->assertEquals(3, $reflection->invoke($controller, 'researcher'));
        $this->assertEquals(2, $reflection->invoke($controller, 'owner'));
        $this->assertEquals(2, $reflection->invoke($controller, 'program_owner'));
        $this->assertNull($reflection->invoke($controller, 'admin'));
        $this->assertNull($reflection->invoke($controller, 'invalid'));
        $this->assertNull($reflection->invoke($controller, ''));
    }

    /**
     * Test that processRegister rejects requests without valid CSRF token (HTTP 403).
     */
    public function testProcessRegisterRejectsMissingCsrfToken(): void
    {
        require_once __DIR__ . '/../../controller/UserController.php';

        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_POST['csrf_token'] = 'invalid_token';
        $_POST['first_name'] = 'John';
        $_POST['last_name'] = 'Doe';
        $_POST['email'] = 'john@example.com';
        $_POST['password'] = 'securepass123';
        $_POST['confirm_password'] = 'securepass123';
        $_POST['role'] = 'researcher';

        $controller = new UserController();

        ob_start();
        $controller->processRegister();
        $output = ob_get_clean();

        $this->assertEquals(403, http_response_code());
        $this->assertStringContainsString('CSRF validation failed', $output);
    }

    /**
     * Test that processLogin rejects requests without valid CSRF token (HTTP 403).
     */
    public function testProcessLoginRejectsMissingCsrfToken(): void
    {
        require_once __DIR__ . '/../../controller/UserController.php';

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_POST['csrf_token'] = 'invalid_token';
        $_POST['email'] = 'test@example.com';
        $_POST['password'] = 'password123';

        $controller = new UserController();

        ob_start();
        $controller->processLogin();
        $output = ob_get_clean();

        $this->assertEquals(403, http_response_code());
        $this->assertStringContainsString('CSRF validation failed', $output);
    }

    /**
     * Test that registration with password mismatch sets error in session.
     */
    public function testProcessRegisterPasswordMismatch(): void
    {
        require_once __DIR__ . '/../../controller/UserController.php';
        require_once __DIR__ . '/../../model/services/ValidationService.php';

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Generate a valid CSRF token
        $validationService = new ValidationService(self::$conn);
        $csrfToken = $validationService->generateCsrfToken(session_id());

        $_POST['csrf_token'] = $csrfToken;
        $_POST['first_name'] = 'John';
        $_POST['last_name'] = 'Doe';
        $_POST['email'] = 'john@example.com';
        $_POST['password'] = 'securepass123';
        $_POST['confirm_password'] = 'differentpass';
        $_POST['role'] = 'researcher';

        $controller = new UserController();

        // processRegister will call header() and exit, we need to catch that
        // We use output buffering and check session state instead
        try {
            ob_start();
            $controller->processRegister();
            ob_end_clean();
        } catch (\Exception $e) {
            ob_end_clean();
        }

        // Check that errors were set in session flash
        $this->assertArrayHasKey('confirm_password', $_SESSION['flash_errors'] ?? []);
        $this->assertEquals('Passwords do not match', $_SESSION['flash_errors']['confirm_password']);
    }

    /**
     * Test that registration with invalid role sets error in session.
     */
    public function testProcessRegisterInvalidRole(): void
    {
        require_once __DIR__ . '/../../controller/UserController.php';
        require_once __DIR__ . '/../../model/services/ValidationService.php';

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $validationService = new ValidationService(self::$conn);
        $csrfToken = $validationService->generateCsrfToken(session_id());

        $_POST['csrf_token'] = $csrfToken;
        $_POST['first_name'] = 'John';
        $_POST['last_name'] = 'Doe';
        $_POST['email'] = 'john@example.com';
        $_POST['password'] = 'securepass123';
        $_POST['confirm_password'] = 'securepass123';
        $_POST['role'] = 'admin'; // Admin role not allowed on registration

        $controller = new UserController();

        try {
            ob_start();
            $controller->processRegister();
            ob_end_clean();
        } catch (\Exception $e) {
            ob_end_clean();
        }

        $this->assertArrayHasKey('role', $_SESSION['flash_errors'] ?? []);
        $this->assertEquals('Invalid role selected', $_SESSION['flash_errors']['role']);
    }
}
