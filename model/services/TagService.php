<?php

require_once __DIR__ . '/../repository/TagRepository.php';
require_once __DIR__ . '/../repository/ProgramRepository.php';

/**
 * TagService
 *
 * Manages technology stack tag operations for programs: adding, removing,
 * searching, and validating tags. Enforces ownership verification and
 * the 20-tag-per-program limit.
 *
 * @see Requirement 2.1 — Associate Technology_Tags with Programs
 * @see Requirement 2.2 — Allow up to 20 Technology_Tags per Program
 * @see Requirement 2.3 — Remove tag association while retaining shared pool
 * @see Requirement 2.5 — Case-insensitive matching for tag creation
 * @see Requirement 2.6 — Validate tag name format and length
 * @see Requirement 2.7 — Reject invalid tag names with error message
 * @see Requirement 2.8 — Reject duplicate tag association
 */
class TagService
{
    /** @var int Maximum number of tags allowed per program */
    private const MAX_TAGS_PER_PROGRAM = 20;

    /** @var int Maximum length for a tag name */
    private const MAX_TAG_NAME_LENGTH = 50;

    /** @var string Regex pattern for valid tag names */
    private const TAG_NAME_PATTERN = '/^[a-zA-Z0-9.+\-]+$/';

    private TagRepository $tagRepository;
    private ProgramRepository $programRepository;

    /**
     * @param TagRepository    $tagRepository    Repository for tag DB operations.
     * @param ProgramRepository $programRepository Repository for program DB operations.
     */
    public function __construct(TagRepository $tagRepository, ProgramRepository $programRepository)
    {
        $this->tagRepository = $tagRepository;
        $this->programRepository = $programRepository;
    }

    /**
     * Add a technology tag to a program.
     *
     * Validates the tag name, verifies program ownership, checks the 20-tag limit,
     * finds or creates the tag in the shared pool, and associates it with the program.
     * Rejects the operation if the tag is already associated with the program.
     *
     * @param int    $programId The program ID to add the tag to.
     * @param int    $ownerId   The user ID of the program owner (for ownership verification).
     * @param string $tagName   The tag name to add.
     * @return void
     * @throws RuntimeException If ownership verification fails.
     * @throws InvalidArgumentException If tag name is invalid, limit is reached, or tag is already associated.
     *
     * @see Requirement 2.1 — Associate Technology_Tags with Programs
     * @see Requirement 2.2 — Allow up to 20 Technology_Tags per Program
     * @see Requirement 2.5 — Case-insensitive matching for tag creation
     * @see Requirement 2.8 — Reject duplicate tag association
     */
    public function addTag(int $programId, int $ownerId, string $tagName): void
    {
        // Validate tag name
        $errors = $this->validateTagName($tagName);
        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        // Verify ownership
        $this->verifyOwnership($programId, $ownerId);

        // Check 20-tag limit
        $currentCount = $this->tagRepository->countByProgramId($programId);
        if ($currentCount >= self::MAX_TAGS_PER_PROGRAM) {
            throw new InvalidArgumentException(
                'Maximum of ' . self::MAX_TAGS_PER_PROGRAM . ' tags per program reached.'
            );
        }

        // Find or create the tag in the shared pool (case-insensitive)
        $tagId = $this->tagRepository->findOrCreate(trim($tagName));

        // Associate with program (rejects duplicates)
        $associated = $this->tagRepository->associateWithProgram($tagId, $programId);
        if (!$associated) {
            throw new InvalidArgumentException('This tag is already associated with the program.');
        }
    }

    /**
     * Remove a technology tag association from a program.
     *
     * Verifies program ownership then dissociates the tag. The tag remains
     * in the shared pool for reuse by other programs.
     *
     * @param int $programId The program ID to remove the tag from.
     * @param int $ownerId   The user ID of the program owner (for ownership verification).
     * @param int $tagId     The tag ID to dissociate.
     * @return void
     * @throws RuntimeException If ownership verification fails.
     *
     * @see Requirement 2.3 — Remove tag association while retaining shared pool
     */
    public function removeTag(int $programId, int $ownerId, int $tagId): void
    {
        $this->verifyOwnership($programId, $ownerId);

        $this->tagRepository->dissociateFromProgram($tagId, $programId);
    }

    /**
     * Get all tags associated with a program.
     *
     * @param int $programId The program ID.
     * @return array Array of tag records (associative arrays).
     */
    public function getTagsByProgram(int $programId): array
    {
        return $this->tagRepository->findByProgramId($programId);
    }

    /**
     * Search tags by prefix for autocomplete functionality.
     *
     * @param string $query The search prefix.
     * @return array Array of matching tag records.
     */
    public function searchTags(string $query): array
    {
        return $this->tagRepository->searchByPrefix($query);
    }

    /**
     * Get the count of tags associated with a program.
     *
     * @param int $programId The program ID.
     * @return int The tag count.
     */
    public function getTagCountForProgram(int $programId): int
    {
        return $this->tagRepository->countByProgramId($programId);
    }

    /**
     * Validate a tag name against format and length rules.
     *
     * Rules:
     * - Name must be non-empty after trimming
     * - Name must not exceed 50 characters
     * - Name must match pattern: ^[a-zA-Z0-9.+\-]+$
     *
     * @param string $name The tag name to validate.
     * @return array Array of error messages (empty if valid).
     *
     * @see Requirement 2.6 — Validate tag name format and length
     * @see Requirement 2.7 — Reject invalid tag names with error message
     */
    public function validateTagName(string $name): array
    {
        $errors = [];
        $trimmed = trim($name);

        if ($trimmed === '') {
            $errors[] = 'Tag name cannot be empty.';
            return $errors;
        }

        if (strlen($trimmed) > self::MAX_TAG_NAME_LENGTH) {
            $errors[] = 'Tag name must not exceed ' . self::MAX_TAG_NAME_LENGTH . ' characters.';
        }

        if (!preg_match(self::TAG_NAME_PATTERN, $trimmed)) {
            $errors[] = 'Tag name may only contain letters, numbers, hyphens, periods, and plus signs.';
        }

        return $errors;
    }

    /**
     * Verify that the given user is the owner of the specified program.
     *
     * @param int $programId The program ID.
     * @param int $ownerId   The expected owner user ID.
     * @return void
     * @throws RuntimeException If program not found or ownership does not match.
     */
    private function verifyOwnership(int $programId, int $ownerId): void
    {
        $program = $this->programRepository->findById($programId);

        if ($program === null) {
            throw new RuntimeException('Program not found.');
        }

        if ((int) $program['owner_id'] !== $ownerId) {
            throw new RuntimeException('You do not have permission to modify this program.');
        }
    }
}
