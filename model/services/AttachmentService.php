<?php

require_once __DIR__ . '/../repository/AttachmentRepository.php';

/**
 * AttachmentService
 *
 * Manages file upload validation, storage, and database persistence for report attachments.
 *
 * @see Requirement 7.3 — Validate file type and size, store Attachment linked to Report
 * @see Requirement 7.4 — Restrict allowed attachment file types to PNG, JPG, GIF, PDF, TXT, ZIP
 * @see Requirement 7.5 — Restrict each Attachment to maximum file size of 10 MB
 */
class AttachmentService
{
    /** @var string[] Allowed file extensions for attachments */
    private const ALLOWED_FILE_TYPES = ['png', 'jpg', 'gif', 'pdf', 'txt', 'zip'];

    /** @var int Maximum file size in bytes (10 MB) */
    private const MAX_FILE_SIZE = 10485760;

    private AttachmentRepository $attachmentRepository;

    /**
     * @param AttachmentRepository $attachmentRepository Repository for attachment DB operations.
     */
    public function __construct(AttachmentRepository $attachmentRepository)
    {
        $this->attachmentRepository = $attachmentRepository;
    }

    /**
     * Upload an attachment for a report.
     *
     * Validates the file (type + size), generates a storage path,
     * moves the uploaded file, and creates a database record.
     *
     * @param int   $reportId Report ID to attach the file to.
     * @param array $file     Uploaded file data from $_FILES (must contain 'name', 'tmp_name', 'size').
     * @return int The ID of the newly created attachment record.
     * @throws InvalidArgumentException if file validation fails.
     * @throws RuntimeException if file move or directory creation fails.
     */
    public function uploadAttachment(int $reportId, array $file): int
    {
        $errors = $this->validateFile($file);

        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $fileName = $file['name'];
        $storagePath = $this->generateStoragePath($reportId, $fileName);

        // Create directory if it doesn't exist
        $directory = dirname($storagePath);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new RuntimeException("Failed to create upload directory: {$directory}");
            }
        }

        // Move the uploaded file to the storage path
        if (!move_uploaded_file($file['tmp_name'], $storagePath)) {
            throw new RuntimeException("Failed to move uploaded file to: {$storagePath}");
        }

        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fileSize = (int) $file['size'];

        return $this->attachmentRepository->create(
            $reportId,
            $fileName,
            $storagePath,
            $fileExtension,
            $fileSize
        );
    }

    /**
     * Validate an uploaded file for type and size constraints.
     *
     * Checks the file extension (from filename) against allowed types,
     * and verifies the file size does not exceed the 10 MB limit.
     *
     * @param array $file Uploaded file data from $_FILES (must contain 'name', 'size').
     * @return array Array of error messages. Empty array if valid.
     */
    public function validateFile(array $file): array
    {
        $errors = [];

        // Validate file name is present
        if (empty($file['name'])) {
            $errors[] = 'File name is required.';
            return $errors;
        }

        // Validate file type by extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_FILE_TYPES, true)) {
            $errors[] = "File type '{$extension}' is not allowed. Allowed types: " . implode(', ', self::ALLOWED_FILE_TYPES) . '.';
        }

        // Validate file size
        $fileSize = (int) ($file['size'] ?? 0);
        if ($fileSize <= 0) {
            $errors[] = 'File size must be greater than 0.';
        } elseif ($fileSize > self::MAX_FILE_SIZE) {
            $errors[] = 'File size exceeds the maximum allowed size of 10 MB.';
        }

        return $errors;
    }

    /**
     * Generate a storage path for an uploaded file.
     *
     * Sanitizes the filename to remove path traversal characters and
     * constructs the path as: uploads/attachments/{report_id}/{sanitized_filename}
     *
     * @param int    $reportId Report ID.
     * @param string $fileName Original filename.
     * @return string The generated storage path.
     */
    public function generateStoragePath(int $reportId, string $fileName): string
    {
        $sanitizedFileName = $this->sanitizeFileName($fileName);

        return "uploads/attachments/{$reportId}/{$sanitizedFileName}";
    }

    /**
     * Sanitize a filename to remove path traversal and unsafe characters.
     *
     * Removes directory separators, null bytes, and relative path components.
     * Replaces spaces with underscores and strips non-alphanumeric characters
     * except dots, hyphens, and underscores.
     *
     * @param string $fileName Raw filename to sanitize.
     * @return string Sanitized filename safe for filesystem storage.
     */
    private function sanitizeFileName(string $fileName): string
    {
        // Remove any directory components (path traversal protection)
        $fileName = basename($fileName);

        // Remove null bytes
        $fileName = str_replace("\0", '', $fileName);

        // Remove relative path components
        $fileName = str_replace(['../', '..\\', '..'], '', $fileName);

        // Replace spaces with underscores
        $fileName = str_replace(' ', '_', $fileName);

        // Keep only safe characters: alphanumeric, dots, hyphens, underscores
        $fileName = preg_replace('/[^a-zA-Z0-9._\-]/', '', $fileName);

        // Ensure filename is not empty after sanitization
        if (empty($fileName)) {
            $fileName = 'unnamed_file';
        }

        return $fileName;
    }
}
