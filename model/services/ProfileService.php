<?php

require_once __DIR__ . '/../repository/UserRepository.php';

/**
 * ProfileService
 *
 * Handles researcher profile retrieval, validation, and updates.
 * Uses UserRepository for data access.
 *
 * @see Requirement 8.1 — Profile edit form pre-populated with current data
 * @see Requirement 8.5 — Profile field editing (display name, bio, social links)
 * @see Requirement 8.6 — Social link URL validation (https:// prefix + URL syntax)
 * @see Requirement 8.7 — Reject save with field-specific validation errors
 * @see Requirement 8.8 — Save valid profile changes
 */
class ProfileService
{
    private UserRepository $userRepository;

    /**
     * Social URL fields that require https:// validation.
     */
    private const SOCIAL_URL_FIELDS = [
        'website_url',
        'github_url',
        'linkedin_url',
        'facebook_url',
        'youtube_url',
        'instagram_url',
    ];

    /**
     * @param UserRepository $userRepository Repository for user data access.
     */
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Get the full profile data for a user.
     *
     * Returns the user record including role name, profile fields,
     * and all associated data needed for the profile edit form.
     *
     * @param int $userId User ID to retrieve.
     * @return array|null Associative array of user data, or null if not found.
     *
     * @see Requirement 8.1 — Profile edit form pre-populated with current data
     */
    public function getProfile(int $userId): ?array
    {
        return $this->userRepository->findById($userId);
    }

    /**
     * Update a user's profile with validated data.
     *
     * Validates the provided fields and, if valid, persists the changes
     * via UserRepository. Throws RuntimeException if validation fails.
     *
     * @param int   $userId User ID to update.
     * @param array $data   Associative array of profile fields to update.
     * @return void
     * @throws RuntimeException If validation fails.
     *
     * @see Requirement 8.7 — Reject save with field-specific validation errors
     * @see Requirement 8.8 — Save valid profile changes
     */
    public function updateProfile(int $userId, array $data): void
    {
        $errors = $this->validateProfile($data);

        if (!empty($errors)) {
            throw new \RuntimeException('Profile validation failed');
        }

        // Build the fields array with only allowed profile fields
        $allowedFields = array_merge(['display_name', 'bio'], self::SOCIAL_URL_FIELDS);
        $fields = [];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[$field] = $data[$field];
            }
        }

        if (!empty($fields)) {
            $this->userRepository->updateProfile($userId, $fields);
        }
    }

    /**
     * Validate profile data and return field-specific errors.
     *
     * Checks:
     * - display_name: if present and non-empty, trimmed length must be 2-50 chars
     * - bio: if present, must be ≤500 characters
     * - Social URL fields: if present and non-empty, must start with "https://"
     *   and pass filter_var URL validation
     *
     * @param array $data Associative array of profile fields to validate.
     * @return array Associative array of field => error message. Empty if valid.
     *
     * @see Requirement 8.5 — Display name 2-50 chars, bio ≤500 chars
     * @see Requirement 8.6 — Social link URLs must begin with "https://" and be valid
     */
    public function validateProfile(array $data): array
    {
        $errors = [];

        // Validate display_name: if present and non-empty, trimmed length 2-50
        if (array_key_exists('display_name', $data) && $data['display_name'] !== '' && $data['display_name'] !== null) {
            $trimmedName = trim($data['display_name']);
            $nameLength = mb_strlen($trimmedName);

            if ($nameLength < 2 || $nameLength > 50) {
                $errors['display_name'] = 'Display name must be between 2 and 50 characters';
            }
        }

        // Validate bio: if present, must be ≤500 characters
        if (array_key_exists('bio', $data) && $data['bio'] !== null) {
            if (mb_strlen($data['bio']) > 500) {
                $errors['bio'] = 'Bio must not exceed 500 characters';
            }
        }

        // Validate social URL fields
        foreach (self::SOCIAL_URL_FIELDS as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== '' && $data[$field] !== null) {
                $url = $data[$field];

                if (strpos($url, 'https://') !== 0) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must start with https://';
                } elseif (filter_var($url, FILTER_VALIDATE_URL) === false) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be a valid URL';
                }
            }
        }

        return $errors;
    }

    /**
     * Get the public profile for a user.
     *
     * Returns only publicly visible fields (excludes password, email, etc.).
     * Throws RuntimeException if the user is not found.
     *
     * @param int $userId User ID to retrieve.
     * @return array Associative array of public profile fields.
     * @throws RuntimeException If user is not found.
     *
     * @see Requirement 8.8 — Public researcher profile page
     */
    public function getPublicProfile(int $userId): array
    {
        $profile = $this->userRepository->findPublicProfile($userId);

        if ($profile === null) {
            throw new \RuntimeException('User not found');
        }

        return $profile;
    }
}
