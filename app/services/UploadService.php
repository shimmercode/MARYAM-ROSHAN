<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Exceptions\BusinessException;
use App\Core\Logger;

/**
 * Hardened file upload: extension + MIME + size validation, random filenames,
 * no executable content, records everything in file_uploads.
 */
final class UploadService
{
    private const BLOCKED = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phar', 'exe', 'sh', 'bat', 'cgi', 'pl', 'js', 'html', 'htm', 'svg'];

    /**
     * @param array $file $_FILES entry
     * @return array{path:string, url:string, stored_name:string, id:int}
     */
    public static function store(array $file, string $folder = 'general', ?string $entityType = null, ?int $entityId = null): array
    {
        if (!isset($file['tmp_name'], $file['error'])) {
            throw new BusinessException('فایلی دریافت نشد.', 'NO_FILE', 422);
        }
        if ((int)$file['error'] !== UPLOAD_ERR_OK) {
            throw new BusinessException(self::errorMessage((int)$file['error']), 'UPLOAD_ERROR', 422);
        }

        $maxSize = (int)Config::get('app.uploads.max_size', 5242880);
        $size    = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxSize) {
            throw new BusinessException(
                'حجم فایل باید بین ۱ بایت تا ' . round($maxSize / 1048576, 1) . ' مگابایت باشد.',
                'FILE_TOO_LARGE',
                422
            );
        }

        $original  = (string)($file['name'] ?? 'file');
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowed   = (array)Config::get('app.uploads.allowed_ext', []);

        if ($extension === '' || in_array($extension, self::BLOCKED, true) || !in_array($extension, $allowed, true)) {
            Logger::security('Blocked upload extension', ['ext' => $extension, 'name' => $original]);
            throw new BusinessException('نوع فایل مجاز نیست. فرمت‌های مجاز: ' . implode(', ', $allowed), 'INVALID_EXTENSION', 422);
        }
        // Reject double extensions such as "image.php.jpg".
        if (preg_match('/\.(' . implode('|', self::BLOCKED) . ')\./i', $original)) {
            Logger::security('Blocked double-extension upload', ['name' => $original]);
            throw new BusinessException('نام فایل مجاز نیست.', 'INVALID_FILENAME', 422);
        }

        $mime = self::detectMime((string)$file['tmp_name']);
        $allowedMimes = (array)Config::get('app.uploads.allowed_mimes', []);
        if (!in_array($mime, $allowedMimes, true)) {
            Logger::security('Blocked upload MIME', ['mime' => $mime, 'name' => $original]);
            throw new BusinessException('محتوای فایل با فرمت مجاز مطابقت ندارد.', 'INVALID_MIME', 422);
        }
        if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $info = @getimagesize((string)$file['tmp_name']);
            if ($info === false) {
                throw new BusinessException('فایل تصویری معتبر نیست.', 'INVALID_IMAGE', 422);
            }
        }

        $folder     = preg_replace('/[^a-z0-9_\-]/i', '', $folder) ?: 'general';
        $dir        = rtrim((string)Config::get('app.uploads_path'), '/') . '/' . $folder . '/' . date('Y/m');
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new BusinessException('امکان ذخیره فایل وجود ندارد.', 'STORAGE_ERROR', 500);
        }
        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
        $fullPath   = $dir . '/' . $storedName;

        $moved = is_uploaded_file((string)$file['tmp_name'])
            ? move_uploaded_file((string)$file['tmp_name'], $fullPath)
            : rename((string)$file['tmp_name'], $fullPath);
        if (!$moved) {
            throw new BusinessException('ذخیره فایل ناموفق بود.', 'STORAGE_ERROR', 500);
        }
        @chmod($fullPath, 0644);

        $relative = 'uploads/' . $folder . '/' . date('Y/m') . '/' . $storedName;
        $id = Database::instance()->insert('file_uploads', [
            'original_name' => mb_substr($original, 0, 255),
            'stored_name'   => $storedName,
            'path'          => $relative,
            'mime_type'     => $mime,
            'extension'     => $extension,
            'size_bytes'    => $size,
            'entity_type'   => $entityType,
            'entity_id'     => $entityId,
            'uploaded_by'   => AuthService::id(),
        ]);

        return ['id' => $id, 'path' => $relative, 'url' => url($relative), 'stored_name' => $storedName];
    }

    public static function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = (string)finfo_file($finfo, $path);
                finfo_close($finfo);
                if ($mime !== '') {
                    return $mime;
                }
            }
        }
        if (function_exists('mime_content_type')) {
            return (string)mime_content_type($path);
        }
        return 'application/octet-stream';
    }

    public static function delete(string $relativePath): bool
    {
        $base = rtrim((string)Config::get('app.public_path'), '/');
        $full = realpath($base . '/' . ltrim($relativePath, '/'));
        if ($full === false || !str_starts_with($full, $base . '/uploads')) {
            return false;
        }
        Database::instance()->delete('file_uploads', 'path = :p', ['p' => $relativePath]);
        return @unlink($full);
    }

    private static function errorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'حجم فایل بیش از حد مجاز است.',
            UPLOAD_ERR_PARTIAL   => 'فایل به‌طور کامل بارگذاری نشد.',
            UPLOAD_ERR_NO_FILE   => 'فایلی انتخاب نشده است.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'خطای ذخیره‌سازی روی سرور.',
            default              => 'بارگذاری فایل ناموفق بود.',
        };
    }
}
