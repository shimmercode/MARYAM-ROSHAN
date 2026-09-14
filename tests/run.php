<?php
declare(strict_types=1);

/**
 * Test runner:  php tests/run.php  [filter]
 *
 * Files named unit_*.php run without any database.
 * Files named integration_*.php require a configured MySQL connection and are
 * skipped automatically when the database is unreachable.
 */

require_once __DIR__ . '/bootstrap.php';

$filter = $argv[1] ?? '';
$files  = array_merge(glob(__DIR__ . '/unit_*.php') ?: [], glob(__DIR__ . '/integration_*.php') ?: []);
sort($files);

echo "اجرای آزمون‌های سامانه مریم روشن\n" . str_repeat('=', 52) . "\n";

$dbAvailable = false;
try {
    \App\Core\Database::instance()->scalar('SELECT 1');
    $dbAvailable = true;
} catch (\Throwable $e) {
    echo "ℹ پایگاه داده در دسترس نیست — آزمون‌های یکپارچگی رد می‌شوند.\n";
}

foreach ($files as $file) {
    $name = basename($file, '.php');
    if ($filter !== '' && !str_contains($name, $filter)) {
        continue;
    }
    if (str_starts_with($name, 'integration_') && !$dbAvailable) {
        echo "\n— {$name} (رد شد: بدون پایگاه داده)\n";
        continue;
    }
    require $file;
}

exit(T::summary());
