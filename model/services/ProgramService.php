<?php

require_once __DIR__ . '/../repository/ProgramRepository.php';
require_once __DIR__ . '/ActivityLogService.php';

/**
 * ProgramService
 *
 * Manages the program lifecycle: creation, updates, publishing, and closing.
 * Enforces publish preconditions and logs all state changes via ActivityLogService.
 *
 * @see Requirement 4.1 — Create program with status 'draft'
 * @see Requirement 4.2 — Publish program (draft → active) with preconditions
 * @see Requirement 4.3 — Update active program with activity logging
 * @see Requirement 4.4 — Close program (active → closed)
 * @see Requirement 4.5 — Require title, description, scope, and ≥1 reward policy before publish
 * @see Requirement 4.6 — Validate non-empty required fields on create/update
 */
class ProgramService
{
    private ProgramRepository $programRepository;
    private ActivityLogService $activityLogService;
    private mysqli $conn;

    /**
     * @param ProgramRepository  $programRepository  Repository for program DB operations.
     * @param ActivityLogService $activityLogService  Service for audit logging.
     * @param mysqli             $conn               Database connection (for reward policy check).
     */
    public function __construct(
        ProgramRepository $programRepository,
        ActivityLogService $activityLogService,
        mysqli $conn
    ) {
        $this->programRepository = $programRepository;
        $this->activityLogService = $activityLogService;
        $this->conn = $conn;
    }

    /**
     * Create a new program in 'draft' status.
     *
     * @param int    $ownerId     Owner user ID.
     * @param string $title       Program title (must be non-empty).
     * @param string $description Program description (must be non-empty).
     * @param string $scope       Scope definition (must be non-empty).
     * @param string $ipAddress   Client IP address for activity logging.
     * @return int The ID of the newly created program.
     * @throws InvalidArgumentException if required fields are empty.
     */
    public function createProgram(
        int $ownerId,
        string $title,
        string $description,
        string $scope,
        string $ipAddress = ''
    ): int {
        $this->validateRequiredFields($title, $description, $scope);

        $programId = $this->programRepository->create($ownerId, $title, $description, $scope);

        $this->activityLogService->log(
            $ownerId,
            'program.create',
            'program',
            $programId,
            ['title' => $title, 'status' => 'draft'],
            $ipAddress
        );

        return $programId;
    }

    /**
     * Update a program's title, description, and scope.
     *
     * @param int    $programId   Program ID to update.
     * @param int    $userId      Acting user ID (for activity log).
     * @param string $title       New title (must be non-empty).
     * @param string $description New description (must be non-empty).
     * @param string $scope       New scope (must be non-empty).
     * @param string $ipAddress   Client IP address for activity logging.
     * @return int Number of affected rows.
     * @throws InvalidArgumentException if required fields are empty.
     */
    public function updateProgram(
        int $programId,
        int $userId,
        string $title,
        string $description,
        string $scope,
        string $ipAddress = ''
    ): int {
        $this->validateRequiredFields($title, $description, $scope);

        $affected = $this->programRepository->update($programId, $title, $description, $scope);

        $this->activityLogService->log(
            $userId,
            'program.update',
            'program',
            $programId,
            ['title' => $title],
            $ipAddress
        );

        return $affected;
    }

    /**
     * Publish a draft program (change status to 'active').
     *
     * Validates that all publish preconditions are met:
     * - Non-empty title, description, and scope
     * - At least one reward policy exists for the program
     *
     * @param int    $programId Program ID to publish.
     * @param int    $userId    Acting user ID (for activity log).
     * @param string $ipAddress Client IP address for activity logging.
     * @return bool True on successful publish.
     * @throws InvalidArgumentException if preconditions are not met.
     * @throws RuntimeException if program not found.
     */
    public function publishProgram(int $programId, int $userId, string $ipAddress = ''): bool
    {
        $program = $this->programRepository->findById($programId);

        if ($program === null) {
            throw new RuntimeException('Program not found.');
        }

        $errors = $this->validatePublishPreconditions($program);

        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $this->programRepository->updateStatus($programId, 'active');

        $this->activityLogService->log(
            $userId,
            'program.publish',
            'program',
            $programId,
            ['previous_status' => $program['status'], 'new_status' => 'active'],
            $ipAddress
        );

        return true;
    }

    /**
     * Close an active program (change status to 'closed').
     *
     * @param int    $programId Program ID to close.
     * @param int    $userId    Acting user ID (for activity log).
     * @param string $ipAddress Client IP address for activity logging.
     * @return bool True on successful close.
     * @throws RuntimeException if program not found.
     */
    public function closeProgram(int $programId, int $userId, string $ipAddress = ''): bool
    {
        $program = $this->programRepository->findById($programId);

        if ($program === null) {
            throw new RuntimeException('Program not found.');
        }

        $this->programRepository->updateStatus($programId, 'closed');

        $this->activityLogService->log(
            $userId,
            'program.close',
            'program',
            $programId,
            ['previous_status' => $program['status'], 'new_status' => 'closed'],
            $ipAddress
        );

        return true;
    }

    /**
     * Validate that all publish preconditions are met for a program.
     *
     * Checks:
     * - Non-empty title
     * - Non-empty description
     * - Non-empty scope
     * - At least one reward policy exists for the program
     *
     * @param array $program Program data (associative array from repository).
     * @return array Array of error messages (empty if all preconditions met).
     */
    public function validatePublishPreconditions(array $program): array
    {
        $errors = [];

        if (empty(trim($program['title'] ?? ''))) {
            $errors[] = 'Program title is required.';
        }

        if (empty(trim($program['description'] ?? ''))) {
            $errors[] = 'Program description is required.';
        }

        if (empty(trim($program['scope'] ?? ''))) {
            $errors[] = 'Program scope is required.';
        }

        // Check for at least one reward policy
        $programId = (int) $program['id'];
        $rewardPolicyCount = $this->countRewardPolicies($programId);

        if ($rewardPolicyCount < 1) {
            $errors[] = 'At least one reward policy is required before publishing.';
        }

        return $errors;
    }

    /**
     * Count the number of reward policies for a given program.
     * Uses a direct query to avoid circular dependency with RewardPolicyRepository.
     *
     * @param int $programId Program ID.
     * @return int Number of reward policies.
     */
    private function countRewardPolicies(int $programId): int
    {
        $stmt = $this->conn->prepare('SELECT COUNT(*) AS cnt FROM reward_policies WHERE program_id = ?');

        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare statement: ' . $this->conn->error);
        }

        $stmt->bind_param('i', $programId);

        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Query execution failed: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $result->free();
        $stmt->close();

        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Validate that required fields are non-empty.
     *
     * @param string $title       Program title.
     * @param string $description Program description.
     * @param string $scope       Program scope.
     * @throws InvalidArgumentException if any field is empty.
     */
    private function validateRequiredFields(string $title, string $description, string $scope): void
    {
        $errors = [];

        if (empty(trim($title))) {
            $errors[] = 'Program title is required.';
        }

        if (empty(trim($description))) {
            $errors[] = 'Program description is required.';
        }

        if (empty(trim($scope))) {
            $errors[] = 'Program scope is required.';
        }

        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }
    }
}
