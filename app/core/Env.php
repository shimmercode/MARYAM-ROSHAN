<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimal .env loader (no external dependency).
 */
final class Env
{
    private static array $vars = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        self::$loaded = true;
        if (!is_file($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));
            if (strlen($val) > 1 && (
                ($val[0] === '"' && str_ends_with($val, '"')) ||
                ($val[0] === "'" && str_ends_with($val, "'"))
            )) {
                $val = substr($val, 1, -1);
            }
            self::$vars[$key] = $val;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$vars)) {
            return self::cast(self::$vars[$key]);
        }
        $v = getenv($key);
        if ($v !== false) {
            return self::cast($v);
        }
        return $default;
    }

    public static function set(string $key, string $value): void
    {
        self::$vars[$key] = $value;
    }

    public static function isLoaded(): bool
    {
        return self::$loaded;
    }

    private static function cast(string $v): mixed
    {
        return match (strtolower($v)) {
            'true'  => true,
            'false' => false,
            'null', '' => $v === '' ? '' : null,
            default => $v,
        };
    }
}
