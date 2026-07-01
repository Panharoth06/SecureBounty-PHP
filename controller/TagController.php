<?php

require_once __DIR__ . '/../model/repository/TagRepository.php';
require_once __DIR__ . '/../model/repository/ProgramRepository.php';
require_once __DIR__ . '/../model/services/TagService.php';
require_once __DIR__ . '/../model/services/ValidationService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/ProgramOwnerMiddleware.php';

/**
 * TagController
 *
 * Handles technology tag association/dissociation for programs and
 * exposes a JSON autocomplete search endpoint. add/remove require
 * Program_Owner role and CSRF validation; search requires only that
 * the user is authenticated.
 *
 * @see Requirement 2.1 — Associate tag with program
 * @see Requirement 2.2 — Enforce 20 tag limit per program
 * @see Requirement 2.3 — Remove tag association
 * @see Requirement 2.7 — Reject invalid tag names with validation errors
 * @see Requirement 2.8 — Reject duplicate tag association
 */
class TagController
{
    private TagService $tagService;
    private ValidationService $validationService;

    public function __construct()
    {
        $conn = require __DIR__ . '/../config/database.php';

        $tagRepository = new TagRepository($conn);
        $programRepository = new ProgramRepository($conn);

        $this->tagService = new TagService($tagRepository, $programRepository);
        $this->validationService = new ValidationService($conn);
    }

    /**
     * Add a tag to a program.
     * POST: csrf_token, program_id, name
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
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        try {
            $this->tagService->addTag($programId, $userId, $name);
            $_SESSION['flash_success'] = 'Tag added.';
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_tag_errors'] = [$e->getMessage()];
            $_SESSION['flash_tag_old_input'] = ['name' => $name];
        } catch (RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: index.php?page=program-edit&id=' . $programId);
        exit;
    }

    /**
     * Remove a tag association from a program.
     * POST: csrf_token, program_id, tag_id
     */
    public function remove(): void
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
        $tagId = (int) ($_POST['tag_id'] ?? 0);
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        try {
            $this->tagService->removeTag($programId, $userId, $tagId);
            $_SESSION['flash_success'] = 'Tag removed.';
        } catch (RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: index.php?page=program-edit&id=' . $programId);
        exit;
    }

    /**
     * JSON autocomplete search.
     * GET: q (query prefix)
     * Returns: JSON array of [{id, name}] (max 10 results)
     */
    public function search(): void
    {
        $authMiddleware = new AuthMiddleware();
        $authMiddleware->handle();

        $query = trim((string) ($_GET['q'] ?? ''));

        header('Content-Type: application/json; charset=utf-8');

        if ($query === '') {
            echo json_encode([]);
            return;
        }

        $results = $this->tagService->searchTags($query);

        // Return a minimal shape for the autocomplete client
        $shaped = array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
            ];
        }, $results);

        echo json_encode($shaped);
    }
}
