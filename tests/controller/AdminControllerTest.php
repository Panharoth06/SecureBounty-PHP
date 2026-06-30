<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\TestDatabaseHelper;

/**
 * AdminControllerTest
 *
 * Tests the AdminController methods for user management, program oversight,
 * and activity log viewing. Validates CSRF enforcement, self-deactivation
 * prevention, and admin middleware application.
 */
class AdminControllerTest extends TestCase
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

        // Seed the authenticated admin user (id=1) that the tests assume is
        // logged in via $_SESSION['user_id'] = 1. AuthMiddleware verifies the
        // account exists and is active via a real database lookup, so the row
        // must be present for the admin actions under test to run.
        self::$conn->query(
            "INSERT INTO users (id, role_id, first_name, last_name, email, password_hash, status)
             VALUES (1, 1, 'Test', 'Admin', 'admin@securebounty.test',
                     '\$2y\$10\$abcdefghijklmnopqrstuuWRb/wPJ0fVH0Jf0RRPqQDfAx.LnZ1GS', 'active')"
        );

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
     * Test that the AdminController can be instantiated without errors.
     */
    public function testControllerInstantiation(): void
    {
        require_once __DIR__ . '/../../controller/AdminController.php';

        $controller = new AdminController();
        $this->assertInstanceOf(AdminController::class, $controller);
    }

    /**
     * Test that deactivateUser rejects requests without valid CSRF token (HTTP 403).
     */
    public function testDeactivateUserRejectsMissingCsrfToken(): void
    {
        require_once __DIR__ . '/../../controller/AdminController.php';

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Simulate admin session
        $_SESSION['user_id'] = 1;
        $_SESSION['role_id'] = 1;
        $_SESSION['status'] = 'active';

        $_POST['csrf_token'] = 'invalid_token';
        $_POST['user_id'] = '2';

        $controller = new AdminController();

        ob_start();
        $controller->deactivateUser();
        $output = ob_get_clean();

        $this->assertEquals(403, http_response_code());
        $this->assertStringContainsString('CSRF validation failed', $output);
    }

    /**
     * Test that deactivateUser prevents admin from deactivating their own account.
     */
    public function testDeactivateUserPreventsSelfDeactivation(): void
    {
        require_once __DIR__ . '/../../controller/AdminController.php';
        require_once __DIR__ . '/../../model/services/ValidationService.php';

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Simulate admin session
        $_SESSION['user_id'] = 1;
        $_SESSION['role_id'] = 1;
        $_SESSION['status'] = 'active';

        // Generate a valid CSRF token
        $validationService = new ValidationService(self::$conn);
        $csrfToken = $validationService->generateCsrfToken(session_id());

        $_POST['csrf_token'] = $csrfToken;
        $_POST['user_id'] = '1'; // Same as admin user_id (self-deactivation)

        $controller = new AdminController();

        try {
            ob_start();
            $controller->deactivateUser();
            ob_end_clean();
        } catch (\Exception $e) {
            ob_end_clean();
        }

        // Check that error was set in session flash
        $this->assertEquals('You cannot deactivate your own account.', $_SESSION['flash_error'] ?? '');
    }

    /**
     * Test that reactivateUser rejects requests without valid CSRF token (HTTP 403).
     */
    public function testReactivateUserRejectsMissingCsrfToken(): void
    {
        require_once __DIR__ . '/../../controller/AdminController.php';

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user_id'] = 1;
        $_SESSION['role_id'] = 1;
        $_SESSION['status'] = 'active';

        $_POST['csrf_token'] = 'bad_token';
        $_POST['user_id'] = '2';

        $controller = new AdminController();

        ob_start();
        $controller->reactivateUser();
        $output = ob_get_clean();

        $this->assertEquals(403, http_response_code());
        $this->assertStringContainsString('CSRF validation failed', $output);
    }

    /**
     * Test that changeRole rejects requests without valid CSRF token (HTTP 403).
     */
    public function testChangeRoleRejectsMissingCsrfToken(): void
    {
        require_once __DIR__ . '/../../controller/AdminController.php';

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user_id'] = 1;
        $_SESSION['role_id'] = 1;
        $_SESSION['status'] = 'active';

        $_POST['csrf_token'] = 'bad_token';
        $_POST['user_id'] = '2';
        $_POST['role_id'] = '3';

        $controller = new AdminController();

        ob_start();
        $controller->changeRole();
        $output = ob_get_clean();

        $this->assertEquals(403, http_response_code());
        $this->assertStringContainsString('CSRF validation failed', $output);
    }

    /**
     * Test that suspendProgram rejects requests without valid CSRF token (HTTP 403).
     */
    public function testSuspendProgramRejectsMissingCsrfToken(): void
    {
        require_once __DIR__ . '/../../controller/AdminController.php';

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user_id'] = 1;
        $_SESSION['role_id'] = 1;
        $_SESSION['status'] = 'active';

        $_POST['csrf_token'] = 'bad_token';
        $_POST['program_id'] = '1';

        $controller = new AdminController();

        ob_start();
        $controller->suspendProgram();
        $output = ob_get_clean();

        $this->assertEquals(403, http_response_code());
        $this->assertStringContainsString('CSRF validation failed', $output);
    }

    /**
     * Test that reinstateProgram rejects requests without valid CSRF token (HTTP 403).
     */
    public function testReinstateProgramRejectsMissingCsrfToken(): void
    {
        require_once __DIR__ . '/../../controller/AdminController.php';

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user_id'] = 1;
        $_SESSION['role_id'] = 1;
        $_SESSION['status'] = 'active';

        $_POST['csrf_token'] = 'bad_token';
        $_POST['program_id'] = '1';

        $controller = new AdminController();

        ob_start();
        $controller->reinstateProgram();
        $output = ob_get_clean();

        $this->assertEquals(403, http_response_code());
        $this->assertStringContainsString('CSRF validation failed', $output);
    }
}
