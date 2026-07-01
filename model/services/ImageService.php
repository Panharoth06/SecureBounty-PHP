<?php

/**
 * ImageService
 *
 * Handles image upload, validation, resizing, and deletion for program logos and user avatars.
 * Uses PHP's GD library for image processing.
 *
 * @see Requirement 7.1 — Store program logo image file associated with Program
 * @see Requirement 7.2 — Restrict logo file types to PNG, JPG, GIF by extension and MIME type
 * @see Requirement 7.3 — Reject disallowed file types with validation error
 * @see Requirement 7.4 — Restrict logo file size to 1 byte minimum and 2 MB maximum
 * @see Requirement 7.5 — Reject oversized or empty files with validation error
 * @see Requirement 7.6 — Resize logos to 200x200 max maintaining aspect ratio
 * @see Requirement 7.10 — Replace existing logo and delete previous file on re-upload
 * @see Requirement 8.2 — Restrict avatar to PNG, JPG, GIF with max 2 MB
 * @see Requirement 8.3 — Reject invalid avatar uploads with validation error
 * @see Requirement 8.4 — Resize avatar to 150x150 and store associated with user
 */
class ImageService
{
    /** @var int Maximum file size in bytes (2 MB) */
    private const MAX_FILE_SIZE = 2097152;

    /** @var int Minimum file size in bytes */
    private const MIN_FILE_SIZE = 1;

    /** @var string[] Allowed file extensions */
    private const ALLOWED_EXTENSIONS = ['png', 'jpg', 'gif'];

    /** @var array<string, string> Extension to MIME type mapping */
    private const MIME_MAP = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
    ];

    /** @var int Maximum logo dimension in pixels */
    private const LOGO_MAX_WIDTH = 200;
    private const LOGO_MAX_HEIGHT = 200;

    /** @var int Maximum avatar dimension in pixels */
    private const AVATAR_MAX_WIDTH = 150;
    private const AVATAR_MAX_HEIGHT = 150;

    /** @var string Base path for uploads directory */
    private string $basePath;

    /**
     * @param string|null $basePath Base upload path. Defaults to project root + '/uploads'.
     */
    public function __construct(?string $basePath = null)
    {
        if ($basePath === null) {
            $this->basePath = dirname(__DIR__, 2) . '/uploads';
        } else {
            $this->basePath = rtrim($basePath, '/');
        }
    }

    /**
     * Upload and process a program logo image.
     *
     * Validates the file, creates the logos directory if needed, deletes any existing
     * logo for the program, moves the uploaded file, and resizes it to 200x200 max.
     *
     * @param array $file Uploaded file data from $_FILES (must contain 'name', 'tmp_name', 'size', 'error').
     * @param int $programId Program ID to associate the logo with.
     * @return string Relative path to the stored logo (e.g., "uploads/logos/program_1.png").
     * @throws InvalidArgumentException If file validation fails.
     * @throws RuntimeException If file move, directory creation, or image processing fails.
     */
    public function uploadLogo(array $file, int $programId): string
    {
        $errors = $this->validateImage($file);
        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $logosDir = $this->basePath . '/logos';

        // Create logos directory if it doesn't exist
        if (!is_dir($logosDir)) {
            if (!mkdir($logosDir, 0755, true)) {
                throw new RuntimeException("Failed to create logos directory: {$logosDir}");
            }
        }

        // Delete existing logo files for this program (any extension)
        $this->deleteLogo($programId);

        // Determine destination path
        $destPath = $logosDir . "/program_{$programId}.{$extension}";

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new RuntimeException("Failed to move uploaded file to: {$destPath}");
        }

        // Resize to 200x200 max maintaining aspect ratio
        $this->resizeImage($destPath, $destPath, self::LOGO_MAX_WIDTH, self::LOGO_MAX_HEIGHT);

        // Return relative path
        return "uploads/logos/program_{$programId}.{$extension}";
    }

    /**
     * Upload and process a user avatar image.
     *
     * Validates the file, creates the avatars directory if needed, deletes any existing
     * avatar for the user, moves the uploaded file, and resizes it to 150x150 max.
     *
     * @param array $file Uploaded file data from $_FILES (must contain 'name', 'tmp_name', 'size', 'error').
     * @param int $userId User ID to associate the avatar with.
     * @return string Relative path to the stored avatar (e.g., "uploads/avatars/user_1.png").
     * @throws InvalidArgumentException If file validation fails.
     * @throws RuntimeException If file move, directory creation, or image processing fails.
     */
    public function uploadAvatar(array $file, int $userId): string
    {
        $errors = $this->validateImage($file);
        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $avatarsDir = $this->basePath . '/avatars';

        // Create avatars directory if it doesn't exist
        if (!is_dir($avatarsDir)) {
            if (!mkdir($avatarsDir, 0755, true)) {
                throw new RuntimeException("Failed to create avatars directory: {$avatarsDir}");
            }
        }

        // Delete existing avatar files for this user (any extension)
        $this->deleteAvatar($userId);

        // Determine destination path
        $destPath = $avatarsDir . "/user_{$userId}.{$extension}";

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new RuntimeException("Failed to move uploaded file to: {$destPath}");
        }

        // Resize to 150x150 max maintaining aspect ratio
        $this->resizeImage($destPath, $destPath, self::AVATAR_MAX_WIDTH, self::AVATAR_MAX_HEIGHT);

        // Return relative path
        return "uploads/avatars/user_{$userId}.{$extension}";
    }

    /**
     * Delete all logo files for a given program.
     *
     * Finds and removes any file matching the pattern uploads/logos/program_{id}.*
     *
     * @param int $programId Program ID whose logo files should be deleted.
     * @return void
     */
    public function deleteLogo(int $programId): void
    {
        $logosDir = $this->basePath . '/logos';
        if (!is_dir($logosDir)) {
            return;
        }

        $pattern = $logosDir . "/program_{$programId}.*";
        $files = glob($pattern);
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }

    /**
     * Delete all avatar files for a given user.
     *
     * Finds and removes any file matching the pattern uploads/avatars/user_{id}.*
     *
     * @param int $userId User ID whose avatar files should be deleted.
     * @return void
     */
    public function deleteAvatar(int $userId): void
    {
        $avatarsDir = $this->basePath . '/avatars';
        if (!is_dir($avatarsDir)) {
            return;
        }

        $pattern = $avatarsDir . "/user_{$userId}.*";
        $files = glob($pattern);
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }

    /**
     * Validate an uploaded image file.
     *
     * Checks:
     * - Upload was successful (error === UPLOAD_ERR_OK)
     * - File size >= 1 byte and <= $maxSize (default 2 MB)
     * - File extension (from original name) is one of: png, jpg, gif (case-insensitive)
     * - MIME type matches the expected type for the file extension
     *
     * @param array $file Uploaded file data from $_FILES.
     * @param int $maxSize Maximum file size in bytes. Default: 2097152 (2 MB).
     * @return array Array of error messages. Empty array means valid.
     */
    public function validateImage(array $file, int $maxSize = self::MAX_FILE_SIZE): array
    {
        $errors = [];

        // Check upload error status
        $uploadError = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($uploadError !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload failed. Please try again.';
            return $errors;
        }

        // Validate file size
        $fileSize = (int) ($file['size'] ?? 0);
        if ($fileSize < self::MIN_FILE_SIZE) {
            $errors[] = 'File must be at least 1 byte in size.';
        } elseif ($fileSize > $maxSize) {
            $maxMb = round($maxSize / 1048576, 1);
            $errors[] = "File size exceeds the maximum allowed size of {$maxMb} MB.";
        }

        // Validate file extension
        $fileName = $file['name'] ?? '';
        if (empty($fileName)) {
            $errors[] = 'File name is required.';
            return $errors;
        }

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $errors[] = "File type '{$extension}' is not allowed. Allowed types: " . implode(', ', self::ALLOWED_EXTENSIONS) . '.';
            return $errors;
        }

        // Validate MIME type matches extension
        $expectedMime = self::MIME_MAP[$extension];
        $actualMime = $file['type'] ?? '';

        if ($actualMime !== $expectedMime) {
            $errors[] = "File MIME type '{$actualMime}' does not match the expected type for .{$extension} files.";
        }

        return $errors;
    }

    /**
     * Resize an image to fit within the specified maximum dimensions while maintaining aspect ratio.
     *
     * Uses PHP's GD library. Only scales down — images smaller than max dimensions are left unchanged.
     * Supports JPEG, PNG, and GIF formats.
     *
     * @param string $sourcePath Path to the source image file.
     * @param string $destPath Path where the resized image should be saved.
     * @param int $maxWidth Maximum width in pixels.
     * @param int $maxHeight Maximum height in pixels.
     * @return void
     * @throws RuntimeException If the image cannot be read or processed.
     */
    private function resizeImage(string $sourcePath, string $destPath, int $maxWidth, int $maxHeight): void
    {
        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            throw new RuntimeException("Unable to read image file: {$sourcePath}");
        }

        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];

        // If image is already within bounds, no resizing needed
        if ($originalWidth <= $maxWidth && $originalHeight <= $maxHeight) {
            return;
        }

        // Calculate new dimensions maintaining aspect ratio
        $widthRatio = $maxWidth / $originalWidth;
        $heightRatio = $maxHeight / $originalHeight;
        $ratio = min($widthRatio, $heightRatio);

        $newWidth = (int) round($originalWidth * $ratio);
        $newHeight = (int) round($originalHeight * $ratio);

        // Create source image resource based on MIME type
        $sourceImage = $this->createImageFromFile($sourcePath, $mimeType);

        // Create new canvas
        $destImage = imagecreatetruecolor($newWidth, $newHeight);
        if ($destImage === false) {
            imagedestroy($sourceImage);
            throw new RuntimeException("Failed to create image canvas for resizing.");
        }

        // Preserve transparency for PNG and GIF
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($destImage, false);
            imagesavealpha($destImage, true);
            $transparent = imagecolorallocatealpha($destImage, 0, 0, 0, 127);
            imagefill($destImage, 0, 0, $transparent);
        }

        // Resample
        $result = imagecopyresampled(
            $destImage,
            $sourceImage,
            0,
            0,
            0,
            0,
            $newWidth,
            $newHeight,
            $originalWidth,
            $originalHeight
        );

        if ($result === false) {
            imagedestroy($sourceImage);
            imagedestroy($destImage);
            throw new RuntimeException("Failed to resample image.");
        }

        // Save resized image
        $this->saveImage($destImage, $destPath, $mimeType);

        // Clean up
        imagedestroy($sourceImage);
        imagedestroy($destImage);
    }

    /**
     * Create a GD image resource from a file based on its MIME type.
     *
     * @param string $filePath Path to the image file.
     * @param string $mimeType MIME type of the image.
     * @return \GdImage The created image resource.
     * @throws RuntimeException If the image format is unsupported or the file cannot be read.
     */
    private function createImageFromFile(string $filePath, string $mimeType): \GdImage
    {
        $image = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($filePath),
            'image/png' => @imagecreatefrompng($filePath),
            'image/gif' => @imagecreatefromgif($filePath),
            default => throw new RuntimeException("Unsupported image type: {$mimeType}"),
        };

        if ($image === false) {
            throw new RuntimeException("Failed to create image from file: {$filePath}");
        }

        return $image;
    }

    /**
     * Save a GD image resource to a file based on the target MIME type.
     *
     * @param \GdImage $image The GD image resource to save.
     * @param string $destPath Destination file path.
     * @param string $mimeType MIME type determining the output format.
     * @return void
     * @throws RuntimeException If the image cannot be saved.
     */
    private function saveImage(\GdImage $image, string $destPath, string $mimeType): void
    {
        $result = match ($mimeType) {
            'image/jpeg' => imagejpeg($image, $destPath, 90),
            'image/png' => imagepng($image, $destPath, 6),
            'image/gif' => imagegif($image, $destPath),
            default => throw new RuntimeException("Unsupported output image type: {$mimeType}"),
        };

        if ($result === false) {
            throw new RuntimeException("Failed to save image to: {$destPath}");
        }
    }
}
