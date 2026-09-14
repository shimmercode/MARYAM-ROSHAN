<?php
declare(strict_types=1);

namespace App\Core;

/**
 * PSR-4 style autoloader mapping the App\ namespace onto the app/ directory.
 * Directory names are lowercase, class names are StudlyCase.
 */
final class Autoloader
{
    private static array $map = [];

    public static function register(string $baseDir): void
    {
        self::$map = [
            'App\\Core\\'         => $baseDir . '/core/',
            'App\\Controllers\\'  => $baseDir . '/controllers/',
            'App\\Models\\'       => $baseDir . '/models/',
            'App\\Services\\'     => $baseDir . '/services/',
            'App\\Repositories\\' => $baseDir . '/repositories/',
            'App\\Middleware\\'   => $baseDir . '/middleware/',
            'App\\Helpers\\'      => $baseDir . '/helpers/',
            'App\\Validators\\'   => $baseDir . '/validators/',
        ];

        spl_autoload_register(static function (string $class): void {
            foreach (self::$map as $prefix => $dir) {
                if (!str_starts_with($class, $prefix)) {
                    continue;
                }
                $relative = substr($class, strlen($prefix));
                $relative = str_replace('\\', '/', $relative);
                $file     = $dir . $relative . '.php';
                if (is_file($file)) {
                    require_once $file;
                    return;
                }
                // Allow sub-namespaces stored in lowercase folders (Admin -> admin).
                $parts = explode('/', $relative);
                $last  = array_pop($parts);
                $lower = implode('/', array_map('strtolower', $parts));
                $file2 = $dir . ($lower !== '' ? $lower . '/' : '') . $last . '.php';
                if (is_file($file2)) {
                    require_once $file2;
                    return;
                }
            }
        });
    }
}
