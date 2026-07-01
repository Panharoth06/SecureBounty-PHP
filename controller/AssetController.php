<?php

require_once __DIR__ . '/../model/repository/AssetRepository.php';
require_once __DIR__ . '/../model/repository/ProgramRepository.php';
require_once __DIR__ . '/../model/services/AssetService.php';
require_once __DIR__ . '/../model/services/ValidationService.php';
require_once __DIR__ . '/../middleware/ProgramOwnerMiddleware.php';

/**
 * AssetController
 *
 * Handles CRUD operations on program assets initiated from the program edit
 * page. All state-changing actions require CSRF validation and program
 * ownership verification (enforced by AssetService).
 *
 * @see Requirement 1.1 — Add Asset
 * @see Requirement 1.3 — Delete Asset
 * @see Requirement 1.4 — Update Asset
 * @see Requirement 1.6, 1.7, 1.8 — Validation errors flashed back to edit page
 * @see Requirement 1.10 — Ownership verification before mutations
 */
class AssetController
{
    private AssetService $assetService;
    private ValidationService $validationService;

    public function __construct()
    {
        $conn = require __DIR__ . '/../config/database.php';

        $assetRepository = new AssetRepository($conn);
        $programRepository = new ProgramRepository($conn);

        $this->assetService = new AssetService($assetRepository, $programRepository);
        $this->validationService = new ValidationService($conn);
    }

    /**
     * Add a new asset to a program.
     * POST: csrf_token, program_id, name, type
     */
    public function add(): void
    {
        $middleware = new ProgramOwnerMiddleware();
        $middleware->handle();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = $_POST['csrf_token'] ?? '';
        if (!$this->validationService->validateCsrfToken($token, session_id())) {
            http_response_code(403);
            echo 'CSRF validation failed. Please try again.';
            return;
        }

        $programId = (int) ($_POST['program_id'] ?? 0);
        $name = (string) ($_POST['name'] ?? '');
        $type = (string) ($_POST['type'] ?? '');
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        try {
            $this->assetService->addAsset($programId, $userId, $name, $type);
            $_SESSION['flash_success'] = 'Asset added.';
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_asset_errors'] = [$e->getMessage()];
            $_SESSION['flash_asset_old_input'] = ['name' => $name, 'type' => $type];
        } catch (RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: index.php?page=program-edit&id=' . $programId);
        exit;
    }

    /**
     * Update an existing asset.
     * POST: csrf_token, id, program_id, name, type
     */
    public function update(): void
    {
        $middleware = new ProgramOwnerMiddleware();
        $middleware->handle();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = $_POST['csrf_token'] ?? '';
        if (!$this->validationService->validateCsrfToken($token, session_id())) {
            http_response_code(403);
            echo 'CSRF validation failed. Please try again.';
            return;
        }

        $assetId = (int) ($_POST['id'] ?? 0);
        $programId = (int) ($_POST['program_id'] ?? 0);
        $name = (string) ($_POST['name'] ?? '');
        $type = (string) ($_POST['type'] ?? '');
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        try {
            $this->assetService->updateAsset($assetId, $userId, $name, $type);
            $_SESSION['flash_success'] = 'Asset updated.';
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_asset_errors'] = [$e->getMessage()];
            $_SESSION['flash_asset_old_input'] = ['name' => $name, 'type' => $type];
        } catch (RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: index.php?page=program-edit&id=' . $programId);
        exit;
    }

    /**
     * Delete an asset.
     * POST: csrf_token, id, program_id
     */
    public function delete(): void
    {
        $middleware = new ProgramOwnerMiddleware();
        $middleware->handle();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = $_POST['csrf_token'] ?? '';
        if (!$this->validationService->validateCsrfToken($token, session_id())) {
            http_response_code(403);
            echo 'CSRF validation failed. Please try again.';
            return;
        }

        $assetId = (int) ($_POST['id'] ?? 0);
        $programId = (int) ($_POST['program_id'] ?? 0);
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        try {
            $this->assetService->deleteAsset($assetId, $userId);
            $_SESSION['flash_success'] = 'Asset removed.';
        } catch (RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: index.php?page=program-edit&id=' . $programId);
        exit;
    }
}
