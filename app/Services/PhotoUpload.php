<?php

declare(strict_types=1);

namespace App\Services;

use Agus\Exceptions\ValidationException;

/**
 * Saves a listing photo to public/uploads/listings with a random name.
 * The file type is checked from its contents, not from the name the browser sent.
 */
final class PhotoUpload
{
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const MAX_BYTES = 3 * 1024 * 1024;
    private const FOLDER = 'uploads/listings';

    /** Returns the saved path (e.g. uploads/listings/ab12.jpg), or null when no file was chosen. */
    public static function store(?array $file): ?string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            self::fail('The photo could not be uploaded. Please try again.');
        }
        if ($file['size'] > self::MAX_BYTES) {
            self::fail('The photo must be smaller than 3 MB.');
        }
        $type = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::TYPES[$type])) {
            self::fail('The photo must be a JPG, PNG or WebP image.');
        }

        $dir = base_path('public/' . self::FOLDER);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = bin2hex(random_bytes(8)) . '.' . self::TYPES[$type];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            self::fail('The photo could not be saved.');
        }
        return self::FOLDER . '/' . $name;
    }

    public static function delete(?string $path): void
    {
        if ($path && str_starts_with($path, self::FOLDER . '/')) {
            $full = base_path('public/' . $path);
            if (is_file($full)) {
                unlink($full);
            }
        }
    }

    private static function fail(string $message): never
    {
        throw new ValidationException(['photo' => [$message]], $_POST);
    }
}
