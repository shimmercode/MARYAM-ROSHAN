<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Exceptions\BusinessException;

/**
 * Database backup generation. Files are stored OUTSIDE the public directory
 * and can only be downloaded through a permission-checked controller action.
 */
final class BackupService
{
    public static function dir(): string
    {
        $dir = (string)Config::get('database.backup_path', dirname(__DIR__, 2) . '/storage/backups');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /** Pure-PHP dump (works on shared hosting without exec/mysqldump). */
    public static function create(): array
    {
        $db     = Database::instance();
        $driver = $db->driver();
        $name   = 'backup-' . date('Ymd-His') . '.sql';
        $path   = self::dir() . '/' . $name;

        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new BusinessException('امکان ایجاد فایل پشتیبان وجود ندارد.', 'BACKUP_FAILED', 500);
        }

        fwrite($handle, "-- Maryam Roshan backup\n-- " . date('Y-m-d H:i:s') . "\n\n");
        fwrite($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        $tables = $driver === 'sqlite'
            ? array_column($db->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"), 'name')
            : array_map(static fn ($r) => array_values($r)[0], $db->select('SHOW TABLES'));

        foreach ($tables as $table) {
            $safe = $db->quoteIdent((string)$table);
            if ($driver !== 'sqlite') {
                $create = $db->selectOne("SHOW CREATE TABLE {$safe}");
                if ($create !== null) {
                    fwrite($handle, "DROP TABLE IF EXISTS {$safe};\n" . array_values($create)[1] . ";\n\n");
                }
            }
            $rows = $db->select("SELECT * FROM {$safe}");
            foreach (array_chunk($rows, 100) as $chunk) {
                foreach ($chunk as $row) {
                    $cols = implode(', ', array_map(static fn ($c) => $db->quoteIdent((string)$c), array_keys($row)));
                    $vals = implode(', ', array_map(static function ($v) use ($db) {
                        if ($v === null) {
                            return 'NULL';
                        }
                        return $db->pdo()->quote((string)$v);
                    }, array_values($row)));
                    fwrite($handle, "INSERT INTO {$safe} ({$cols}) VALUES ({$vals});\n");
                }
            }
            fwrite($handle, "\n");
        }
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);
        @chmod($path, 0600);

        AuditService::log('backup_created', 'system', null, null, ['file' => $name]);

        return ['name' => $name, 'path' => $path, 'size' => (int)filesize($path), 'created_at' => date('Y-m-d H:i:s')];
    }

    public static function listAll(): array
    {
        $files = glob(self::dir() . '/backup-*.sql') ?: [];
        rsort($files);
        return array_map(static fn ($f) => [
            'name'       => basename($f),
            'size'       => (int)filesize($f),
            'created_at' => date('Y-m-d H:i:s', (int)filemtime($f)),
        ], $files);
    }

    /** Safe read: only files matching the backup pattern inside the backup dir. */
    public static function read(string $name): string
    {
        if (!preg_match('/^backup-\d{8}-\d{6}\.sql$/', $name)) {
            throw new BusinessException('نام فایل پشتیبان نامعتبر است.', 'INVALID_BACKUP', 422);
        }
        $path = self::dir() . '/' . $name;
        if (!is_file($path)) {
            throw new BusinessException('فایل پشتیبان یافت نشد.', 'BACKUP_NOT_FOUND', 404);
        }
        AuditService::log('backup_downloaded', 'system', null, null, ['file' => $name]);
        return (string)file_get_contents($path);
    }

    public static function delete(string $name): bool
    {
        if (!preg_match('/^backup-\d{8}-\d{6}\.sql$/', $name)) {
            return false;
        }
        $path = self::dir() . '/' . $name;
        if (is_file($path)) {
            AuditService::log('backup_deleted', 'system', null, null, ['file' => $name]);
            return @unlink($path);
        }
        return false;
    }

    public static function prune(int $keep = 10): int
    {
        $files = glob(self::dir() . '/backup-*.sql') ?: [];
        rsort($files);
        $removed = 0;
        foreach (array_slice($files, $keep) as $f) {
            if (@unlink($f)) {
                $removed++;
            }
        }
        return $removed;
    }
}
