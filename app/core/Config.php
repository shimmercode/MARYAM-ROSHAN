<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Dot-notation configuration registry backed by config/*.php files.
 */
final class Config
{
    private static array $items = [];
    private static string $dir = '';

    public static function boot(string $configDir): void
    {
        self::$dir = rtrim($configDir, '/');
        foreach (glob(self::$dir . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            /** @psalm-suppress UnresolvableInclude */
            self::$items[$name] = require $file;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $node  = self::$items;
        foreach ($parts as $p) {
            if (!is_array($node) || !array_key_exists($p, $node)) {
                return $default;
            }
            $node = $node[$p];
        }
        return $node;
    }

    public static function set(string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        $node  = &self::$items;
        foreach ($parts as $p) {
            if (!isset($node[$p]) || !is_array($node[$p])) {
                $node[$p] = [];
            }
            $node = &$node[$p];
        }
        $node = $value;
    }

    public static function all(): array
    {
        return self::$items;
    }
}
