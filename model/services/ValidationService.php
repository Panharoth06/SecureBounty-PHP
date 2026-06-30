<?php

require_once __DIR__ . '/../repository/BaseRepository.php';

/**
 * ValidationService
 *
 * Provides input sanitization, field validation, file validation,
 * output encoding, and CSRF token management.
 *
 * @see Requirement 14.1 — Validate and sanitize all user-submitted input
 * @see Requirement 14.2 — Parameterized queries for all database operations
 * @see Requirement 14.3 — Encode output data rendered in HTML views
 * @see Requirement 14.4 — CSRF token validation on all state-changing form submissions
 * @see Requirement 14.5 — Reject requests failing CSRF validation with HTTP 403
 */
class ValidationService extends BaseRepository
{
    /** @var string[] Allowed file extensions for attachments */
    private const ALLOWED_FILE_TYPES = ['png', 'jpg', 'gif', 'pdf', 'txt', 'zip'];

    /** @var int Maximum file size in bytes (10 MB) */
    private const MAX_FILE_SIZE = 10485760;

    /** @var int CSRF token expiry in seconds (30 minutes) */
    private const CSRF_TOKEN_EXPIRY = 1800;

    /**
     * Sanitize user input by stripping HTML tags and trimming whitespace.
     *
     * @param string $input Raw user input.
     * @return string Sanitized input.
     */
    public function sanitizeInput(string $input): string
    {
        return trim(strip_tags($input));
    }

    /**
     * Validate that required fields are non-empty.
     *
     * @param array  $data   Associative array of field => value pairs.
     * @param array  $fields List of field names that are required.
     * @return array         Associative array of field => error message for invalid fields.
     *                       Empty array if all fields are valid.
     */
    public function validateRequired(array $data, array $fields): array
    {
        $errors = [];

        foreach ($fields as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }

        return $errors;
    }

    /**
     * Validate an email address format.
     *
     * @param string $email The email address to validate.
     * @return bool True if the email is valid, false otherwise.
     */
    public function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate a file extension against allowed types.
     *
     * @param string $fileType The file extension (e.g., 'png', 'pdf').
     * @return bool True if the file type is allowed, false otherwise.
     */
    public function validateFileType(string $fileType): bool
    {
        return in_array(strtolower($fileType), self::ALLOWED_FILE_TYPES, true);
    }

    /**
     * Validate a file size against the maximum allowed size.
     *
     * @param int $fileSize File size in bytes.
     * @return bool True if the file size is within limits, false otherwise.
     */
    public function validateFileSize(int $fileSize): bool
    {
        return $fileSize > 0 && $fileSize <= self::MAX_FILE_SIZE;
    }

    /**
     * Generate a cryptographically random CSRF token, store it in the database
     * with a 30-minute expiry, and return the token value.
     *
     * @param string $sessionId The current PHP session ID.
     * @return string The generated CSRF token.
     * @throws RuntimeException If token generation or storage fails.
     */
    public function generateCsrfToken(string $sessionId): string
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + self::CSRF_TOKEN_EXPIRY);

        $this->execute(
            'INSERT INTO csrf_tokens (session_id, token, expires_at) VALUES (?, ?, ?)',
            'sss',
            [$sessionId, $token, $expiresAt]
        );

        return $token;
    }

    /**
     * Validate a CSRF token: check it exists in the database, is not expired,
     * then delete it (single-use token).
     *
     * @param string $token     The CSRF token to validate.
     * @param string $sessionId The current PHP session ID.
     * @return bool True if the token is valid, false otherwise.
     */
    public function validateCsrfToken(string $token, string $sessionId): bool
    {
        $row = $this->fetchOne(
            'SELECT id, expires_at FROM csrf_tokens WHERE token = ? AND session_id = ?',
            'ss',
            [$token, $sessionId]
        );

        if ($row === null) {
            return false;
        }

        // Check if token has expired
        if (strtotime($row['expires_at']) < time()) {
            // Delete expired token
            $this->execute('DELETE FROM csrf_tokens WHERE id = ?', 'i', [$row['id']]);
            return false;
        }

        // Token is valid — delete it (single-use)
        $this->execute('DELETE FROM csrf_tokens WHERE id = ?', 'i', [$row['id']]);

        return true;
    }

    /**
     * Escape output for safe rendering in HTML views.
     * Converts special characters to HTML entities to prevent XSS.
     *
     * @param string $output The raw string to escape.
     * @return string The escaped string safe for HTML output.
     */
    public function escapeOutput(string $output): string
    {
        return htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
    }
}
