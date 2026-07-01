<?php

require_once __DIR__ . '/../repository/AssetRepository.php';
require_once __DIR__ . '/../repository/ProgramRepository.php';

/**
 * AssetService
 *
 * Provides business logic for program asset management including
 * creation, update, deletion, and validation with ownership verification.
 * Uses AssetRepository for data access and ProgramRepository for
 * ownership checks.
 *
 * @see Requirement 1.1 — Store Asset with name, type, and program reference
 * @see Requirement 1.3 — Delete Asset record from database
 * @see Requirement 1.4 — Update Asset name and type
 * @see Requirement 1.5 — Validate non-empty name and valid type before saving
 * @see Requirement 1.6 — Reject empty/whitespace-only name with validation error
 * @see Requirement 1.7 — Reject invalid Asset_Type with validation error
 * @see Requirement 1.8 — Reject duplicate Asset name within same Program
 * @see Requirement 1.10 — Verify authenticated user is program owner before mutations
 */
class AssetService
{
    /**
     * Valid asset type values.
     */
    public const VALID_TYPES = [
        'Domain',
        'Wildcard',
        'iOS App Store',
        'Android Play Store',
        'Windows App',
        'Other',
    ];

    private AssetRepository $assetRepository;
    private ProgramRepository $programRepository;

    /**
     * @param AssetRepository  $assetRepository  Repository for asset DB operations.
     * @param ProgramRepository $programRepository Repository for program DB operations (ownership checks).
     */
    public function __construct(
        AssetRepository $assetRepository,
        ProgramRepository $programRepository
    ) {
        $this->assetRepository = $assetRepository;
        $this->programRepository = $programRepository;
    }

    /**
     * Add a new asset to a program.
     *
     * Validates input, verifies the caller owns the program, checks for
     * duplicate names, then creates the asset record.
     *
     * @param int    $programId Program ID to add the asset to.
     * @param int    $ownerId   Authenticated user ID (must be the program owner).
     * @param string $name      Asset name (max 255 characters, non-empty after trim).
     * @param string $type      Asset type (must be one of VALID_TYPES).
     * @return int The ID of the newly created asset.
     * @throws RuntimeException If ownership verification fails.
     * @throws InvalidArgumentException If validation fails.
     *
     * @see Requirement 1.1 — Store Asset with name, type, and program reference
     * @see Requirement 1.5 — Validate non-empty name and valid type
     * @see Requirement 1.10 — Verify program ownership
     */
    public function addAsset(int $programId, int $ownerId, string $name, string $type): int
    {
        $this->verifyOwnership($programId, $ownerId);

        $name = trim($name);

        $errors = $this->validateAsset($name, $type, $programId);

        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        return $this->assetRepository->create($programId, $name, $type);
    }

    /**
     * Update an existing asset's name and type.
     *
     * Finds the asset by ID, verifies the caller owns the associated program,
     * validates the new values, then updates the record.
     *
     * @param int    $assetId Asset ID to update.
     * @param int    $ownerId Authenticated user ID (must be the program owner).
     * @param string $name    New asset name (max 255 characters, non-empty after trim).
     * @param string $type    New asset type (must be one of VALID_TYPES).
     * @return void
     * @throws RuntimeException If asset not found or ownership verification fails.
     * @throws InvalidArgumentException If validation fails.
     *
     * @see Requirement 1.4 — Update Asset name and type
     * @see Requirement 1.10 — Verify program ownership
     */
    public function updateAsset(int $assetId, int $ownerId, string $name, string $type): void
    {
        $asset = $this->assetRepository->findById($assetId);

        if ($asset === null) {
            throw new RuntimeException('Asset not found.');
        }

        $programId = (int) $asset['program_id'];

        $this->verifyOwnership($programId, $ownerId);

        $name = trim($name);

        $errors = $this->validateAsset($name, $type, $programId, $assetId);

        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $this->assetRepository->update($assetId, $name, $type);
    }

    /**
     * Delete an asset from a program.
     *
     * Finds the asset by ID, verifies the caller owns the associated program,
     * then deletes the record.
     *
     * @param int $assetId Asset ID to delete.
     * @param int $ownerId Authenticated user ID (must be the program owner).
     * @return void
     * @throws RuntimeException If asset not found or ownership verification fails.
     *
     * @see Requirement 1.3 — Delete Asset record from database
     * @see Requirement 1.10 — Verify program ownership
     */
    public function deleteAsset(int $assetId, int $ownerId): void
    {
        $asset = $this->assetRepository->findById($assetId);

        if ($asset === null) {
            throw new RuntimeException('Asset not found.');
        }

        $programId = (int) $asset['program_id'];

        $this->verifyOwnership($programId, $ownerId);

        $this->assetRepository->delete($assetId);
    }

    /**
     * Get all assets for a given program.
     *
     * @param int $programId Program ID.
     * @return array Array of asset associative arrays.
     */
    public function getAssetsByProgram(int $programId): array
    {
        return $this->assetRepository->findByProgramId($programId);
    }

    /**
     * Get asset counts grouped by type for a program.
     *
     * Returns an associative array where keys are asset types and values are
     * counts. Types with zero assets are not included.
     *
     * @param int $programId Program ID.
     * @return array Associative array of type => count.
     *
     * @see Requirement 1.9 — Display total count of Assets grouped by type
     */
    public function getAssetCountsByType(int $programId): array
    {
        return $this->assetRepository->countByTypeForProgram($programId);
    }

    /**
     * Validate asset input data.
     *
     * Checks:
     * - Name is non-empty after trim
     * - Name does not exceed 255 characters
     * - Type is one of the valid ENUM values
     * - Name is unique within the program (excluding the current asset on update)
     *
     * @param string   $name      Asset name (should already be trimmed).
     * @param string   $type      Asset type to validate.
     * @param int      $programId Program ID for uniqueness check.
     * @param int|null $excludeId Asset ID to exclude from uniqueness check (for updates).
     * @return array Array of error messages (empty if valid).
     *
     * @see Requirement 1.5 — Validate non-empty name and valid type
     * @see Requirement 1.6 — Reject empty/whitespace-only name
     * @see Requirement 1.7 — Reject invalid Asset_Type
     * @see Requirement 1.8 — Reject duplicate name within same program
     */
    public function validateAsset(string $name, string $type, int $programId, ?int $excludeId = null): array
    {
        $errors = [];

        // Check non-empty name
        if ($name === '') {
            $errors[] = 'Asset name is required and cannot be empty or whitespace-only.';
        } elseif (mb_strlen($name) > 255) {
            $errors[] = 'Asset name must not exceed 255 characters.';
        }

        // Check valid type
        if (!in_array($type, self::VALID_TYPES, true)) {
            $errors[] = 'Invalid asset type. Allowed types: ' . implode(', ', self::VALID_TYPES) . '.';
        }

        // Check uniqueness within program (only if name is valid)
        if ($name !== '' && mb_strlen($name) <= 255) {
            if ($this->assetRepository->existsByNameAndProgram($name, $programId, $excludeId)) {
                $errors[] = 'An asset with this name already exists in the program.';
            }
        }

        return $errors;
    }

    /**
     * Verify that the given user is the owner of the specified program.
     *
     * @param int $programId Program ID.
     * @param int $ownerId   User ID to verify as owner.
     * @return void
     * @throws RuntimeException If the program is not found or user is not the owner.
     *
     * @see Requirement 1.10 — Verify authenticated user is program owner
     */
    private function verifyOwnership(int $programId, int $ownerId): void
    {
        $program = $this->programRepository->findById($programId);

        if ($program === null) {
            throw new RuntimeException('Program not found.');
        }

        if ((int) $program['owner_id'] !== $ownerId) {
            throw new RuntimeException('Access denied. You are not the owner of this program.');
        }
    }
}
