<?php
declare(strict_types=1);

/**
 * Lightweight test harness.
 *
 * PHPUnit is not installable on the target shared-hosting environment (no
 * Composer guarantee), so the suite is a set of plain PHP integration scripts
 * with a tiny assertion helper. Run them all with:  php tests/run.php
 */

define('MR_TEST_ROOT', dirname(__DIR__));

require_once MR_TEST_ROOT . '/app/core/Env.php';
require_once MR_TEST_ROOT . '/app/core/Config.php';
require_once MR_TEST_ROOT . '/app/core/Autoloader.php';

use App\Core\Autoloader;
use App\Core\Config;
use App\Core\Env;

Env::load(MR_TEST_ROOT . '/.env');
Autoloader::register(MR_TEST_ROOT . '/app');
Config::boot(MR_TEST_ROOT . '/config');
require_once MR_TEST_ROOT . '/app/helpers/functions.php';

date_default_timezone_set('Asia/Tehran');
mb_internal_encoding('UTF-8');

final class T
{
    public static int $passed = 0;
    public static int $failed = 0;
    /** @var array<int,string> */
    public static array $failures = [];
    public static string $suite = '';

    public static function suite(string $name): void
    {
        self::$suite = $name;
        echo "\n— {$name}\n";
    }

    public static function ok(bool $condition, string $message): void
    {
        if ($condition) {
            self::$passed++;
            echo "  ✓ {$message}\n";
        } else {
            self::$failed++;
            self::$failures[] = self::$suite . ' › ' . $message;
            echo "  ✗ {$message}\n";
        }
    }

    public static function same(mixed $expected, mixed $actual, string $message): void
    {
        $pass = $expected === $actual;
        if (!$pass) {
            $message .= ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
        }
        self::ok($pass, $message);
    }

    public static function throws(callable $fn, string $class, string $message): void
    {
        try {
            $fn();
            self::ok(false, $message . ' — no exception thrown');
        } catch (\Throwable $e) {
            self::ok($e instanceof $class, $message . ' — got ' . get_class($e));
        }
    }

    public static function summary(): int
    {
        $total = self::$passed + self::$failed;
        echo "\n" . str_repeat('=', 52) . "\n";
        echo "نتیجه: {$total} بررسی — " . self::$passed . " موفق، " . self::$failed . " ناموفق\n";
        foreach (self::$failures as $f) {
            echo "  ✗ {$f}\n";
        }
        return self::$failed === 0 ? 0 : 1;
    }
}
