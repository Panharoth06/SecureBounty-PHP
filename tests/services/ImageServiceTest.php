<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../model/services/ImageService.php';

/**
 * Unit tests for ImageService.
 *
 * @covers ImageService
 */
class ImageServiceTest extends TestCase
{
    private ImageService $service;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/image_service_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->service = new ImageService($this->tempDir);
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    // --- validateImage() tests ---

    public function testValidateImageReturnsEmptyArrayForValidPng(): void
    {
        $file = [
            'name' => 'logo.png',
            'size' => 1024,
            'tmp_name' => '/tmp/phpXXXXX',
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/png',
        ];

        $errors = $this->service->validateImage($file);

        $this->assertEmpty($errors);
    }

    public function testValidateImageReturnsEmptyArrayForValidJpg(): void
    {
        $file = [
            'name' => 'photo.jpg',
            'size' => 50000,
            'tmp_name' => '/tmp/phpXXXXX',
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/jpeg',
        ];

        $errors = $this->service->validateImage($file);

        $this->assertEmpty($errors);
    }

    public function testValidateImageReturnsEmptyArrayForValidGif(): void
    {
        $file = [
            'name' => 'animation.gif',
            'size' => 2000,
            'tmp_name' => '/tmp/phpXXXXX',
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/gif',
        ];

        $errors = $this->service->validateImage($file);

        $this->assertEmpty($errors);
    }

    public function testValidateImageRejectsUploadError(): void
    {
        $file = [
            'name' => 'logo.png',
            'size' => 1024,
            'tmp_name' => '/tmp/phpXXXXX',
            'error' => UPLOAD_ERR_INI_SIZE,
            'type' => 'image/png',
        ];

        $errors = $this->service->validateImage($file);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('upload failed', $errors[0]);
    }

    public function testValidateImageRejectsZeroSizeFile(): void
    {
        $file = [
            'name' => 'empty.png',
            'size' => 0,
            'tmp_name' => '/tmp/phpXXXXX',
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/png',
        ];

        $errors = $this->service->validateImage($file);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('at least 1 byte', $errors[0]);
    }

    public function testValidateImageRejectsOversizedFile(): void
    {
        $file = [
            'name' => 'huge.png',
            'size' => 2097153, // 2 MB + 1 byte
            'tmp_name' => '/tmp/phpXXXXX',
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/png',
        ];

        $errors = $this->service->validateImage($file);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('maximum allowed size', $errors[0]);
    }

    public function testValidateImageAcceptsExactly2MbFile(): void
    {
        $file = [
            'name' => 'max.png',
            'size' => 2097152, // Exactly 2 MB
            'tmp_name' => '/tmp/phpXXXXX',
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/png',
        ];

        $errors = $this->service->validateImage($file);

        $this->assertEmpty($errors);
    }

    public function testValidateImageAcceptsExactly1ByteFile(): void
    {
        $file = [
            'name' => 'tiny.png',
            'size' => 1, // Exactly 1 byte minimum
            'tmp_name' => '/tmp/phpXXXXX',
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/png',
        ];

        $errors = $this->service->validateImage($file);

        $this->assertEmpty($errors);
    }

    public function testValidateImageRejectsDisallowedExtension(): void
    {
        $file = [
            'name' => 'document.pdf',
            'size' => 1024,
            'tmp_name' => '/tmp/phpXXXXX',
            'error' => UPLOAD_ERR_OK,
            'type' => 'application/pdf',
        ];

        $errors = $this->service->validateImage($file);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('not allowed', $errors[0]);
    }

    public function testValidateImageRejectsMismatchedMimeType(): void
    {
        $file = [
            'name' => 'fake.png',
            'size' => 1024,
            'tmp_name' => '/tmp/phpXXXXX',
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/jpeg', // MIME says jpeg but extension is png
        ];

        $errors = $this->service->validateImage($file);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('MIME type', $errors[0]);
    }

    public function testValidateImageRejectsMissingFileName(): void
    {
        $file = [
            'name' => '',
            'size' => 1024,
            'tmp_name' => '/tmp/phpXXXXX',
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/png',
        ];

        $errors = $this->service->validateImage($file);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('name is required', $errors[0]);
    }

    public function testValidateImageAcceptsUppercaseExtension(): void
    {
        $file = [
            'name' => 'LOGO.PNG',
            'size' => 1024,
            'tmp_name' => '/tmp/phpXXXXX',
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/png',
        ];

        $errors = $this->service->validateImage($file);

        $this->assertEmpty($errors);
    }

    public function testValidateImageWithCustomMaxSize(): void
    {
        $file = [
            'name' => 'photo.jpg',
            'size' => 500000, // 500 KB
            'tmp_name' => '/tmp/phpXXXXX',
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/jpeg',
        ];

        // Use smaller custom max size (100 KB)
        $errors = $this->service->validateImage($file, 102400);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('maximum allowed size', $errors[0]);
    }

    public function testValidateImageRejectsExeExtension(): void
    {
        $file = [
            'name' => 'malware.exe',
            'size' => 1024,
            'tmp_name' => '/tmp/phpXXXXX',
            'error' => UPLOAD_ERR_OK,
            'type' => 'application/x-executable',
        ];

        $errors = $this->service->validateImage($file);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('not allowed', $errors[0]);
    }

    // --- deleteLogo() tests ---

    public function testDeleteLogoRemovesExistingFile(): void
    {
        $logosDir = $this->tempDir . '/logos';
        mkdir($logosDir, 0755, true);
        file_put_contents($logosDir . '/program_1.png', 'fake image data');

        $this->assertFileExists($logosDir . '/program_1.png');

        $this->service->deleteLogo(1);

        $this->assertFileDoesNotExist($logosDir . '/program_1.png');
    }

    public function testDeleteLogoRemovesAllExtensions(): void
    {
        $logosDir = $this->tempDir . '/logos';
        mkdir($logosDir, 0755, true);
        file_put_contents($logosDir . '/program_5.png', 'data');
        file_put_contents($logosDir . '/program_5.jpg', 'data');
        file_put_contents($logosDir . '/program_5.gif', 'data');

        $this->service->deleteLogo(5);

        $this->assertFileDoesNotExist($logosDir . '/program_5.png');
        $this->assertFileDoesNotExist($logosDir . '/program_5.jpg');
        $this->assertFileDoesNotExist($logosDir . '/program_5.gif');
    }

    public function testDeleteLogoDoesNotAffectOtherPrograms(): void
    {
        $logosDir = $this->tempDir . '/logos';
        mkdir($logosDir, 0755, true);
        file_put_contents($logosDir . '/program_1.png', 'data');
        file_put_contents($logosDir . '/program_2.png', 'data');

        $this->service->deleteLogo(1);

        $this->assertFileDoesNotExist($logosDir . '/program_1.png');
        $this->assertFileExists($logosDir . '/program_2.png');
    }

    public function testDeleteLogoDoesNothingWhenDirectoryMissing(): void
    {
        // Should not throw even if logos directory doesn't exist
        $this->service->deleteLogo(999);
        $this->assertTrue(true); // No exception thrown
    }

    // --- deleteAvatar() tests ---

    public function testDeleteAvatarRemovesExistingFile(): void
    {
        $avatarsDir = $this->tempDir . '/avatars';
        mkdir($avatarsDir, 0755, true);
        file_put_contents($avatarsDir . '/user_1.jpg', 'fake avatar data');

        $this->assertFileExists($avatarsDir . '/user_1.jpg');

        $this->service->deleteAvatar(1);

        $this->assertFileDoesNotExist($avatarsDir . '/user_1.jpg');
    }

    public function testDeleteAvatarRemovesAllExtensions(): void
    {
        $avatarsDir = $this->tempDir . '/avatars';
        mkdir($avatarsDir, 0755, true);
        file_put_contents($avatarsDir . '/user_3.png', 'data');
        file_put_contents($avatarsDir . '/user_3.gif', 'data');

        $this->service->deleteAvatar(3);

        $this->assertFileDoesNotExist($avatarsDir . '/user_3.png');
        $this->assertFileDoesNotExist($avatarsDir . '/user_3.gif');
    }

    public function testDeleteAvatarDoesNotAffectOtherUsers(): void
    {
        $avatarsDir = $this->tempDir . '/avatars';
        mkdir($avatarsDir, 0755, true);
        file_put_contents($avatarsDir . '/user_1.png', 'data');
        file_put_contents($avatarsDir . '/user_2.png', 'data');

        $this->service->deleteAvatar(1);

        $this->assertFileDoesNotExist($avatarsDir . '/user_1.png');
        $this->assertFileExists($avatarsDir . '/user_2.png');
    }

    public function testDeleteAvatarDoesNothingWhenDirectoryMissing(): void
    {
        // Should not throw even if avatars directory doesn't exist
        $this->service->deleteAvatar(999);
        $this->assertTrue(true);
    }

    // --- uploadLogo() tests ---

    public function testUploadLogoThrowsOnInvalidFile(): void
    {
        $file = [
            'name' => 'document.pdf',
            'size' => 1024,
            'tmp_name' => '/tmp/phpXXXXX',
            'error' => UPLOAD_ERR_OK,
            'type' => 'application/pdf',
        ];

        $this->expectException(InvalidArgumentException::class);

        $this->service->uploadLogo($file, 1);
    }

    public function testUploadLogoThrowsOnOversizedFile(): void
    {
        $file = [
            'name' => 'huge.png',
            'size' => 2097153,
            'tmp_name' => '/tmp/phpXXXXX',
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/png',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('maximum allowed size');

        $this->service->uploadLogo($file, 1);
    }

    // --- uploadAvatar() tests ---

    public function testUploadAvatarThrowsOnInvalidFile(): void
    {
        $file = [
            'name' => 'script.js',
            'size' => 1024,
            'tmp_name' => '/tmp/phpXXXXX',
            'error' => UPLOAD_ERR_OK,
            'type' => 'application/javascript',
        ];

        $this->expectException(InvalidArgumentException::class);

        $this->service->uploadAvatar($file, 1);
    }

    public function testUploadAvatarThrowsOnMismatchedMime(): void
    {
        $file = [
            'name' => 'fake.gif',
            'size' => 1024,
            'tmp_name' => '/tmp/phpXXXXX',
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/png', // MIME doesn't match .gif extension
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MIME type');

        $this->service->uploadAvatar($file, 1);
    }

    // --- Constructor tests ---

    public function testConstructorUsesDefaultPathWhenNull(): void
    {
        $service = new ImageService();
        // Just verify it creates without error — the default path points to project root/uploads
        $this->assertInstanceOf(ImageService::class, $service);
    }

    public function testConstructorUsesProvidedPath(): void
    {
        $customPath = $this->tempDir . '/custom_uploads';
        $service = new ImageService($customPath);
        $this->assertInstanceOf(ImageService::class, $service);
    }
}
