<?php
declare(strict_types=1);

/**
 * Shared bootstrap for every scheduled job.
 *
 * Cron jobs run outside the HTTP kernel, so they build the minimum runtime by
 * hand: environment, autoloader, config, helpers and a database connection.
 * They must never emit HTML and must exit with a non-zero status on failure so
 * the host's cron mailer reports the problem.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("این اسکریپت فقط از طریق خط فرمان قابل اجراست.\n");
}

define('MR_ROOT', dirname(__DIR__));

require_once MR_ROOT . '/app/core/Env.php';
require_once MR_ROOT . '/app/core/Config.php';
require_once MR_ROOT . '/app/core/Autoloader.php';

use App\Core\Autoloader;
use App\Core\Config;
use App\Core\Env;

Env::load(MR_ROOT . '/.env');
Autoloader::register(MR_ROOT . '/app');
Config::boot(MR_ROOT . '/config');
require_once MR_ROOT . '/app/helpers/functions.php';

date_default_timezone_set((string)Env::get('APP_TIMEZONE', 'Asia/Tehran'));
mb_internal_encoding('UTF-8');
set_time_limit(0);

/** Timestamped console line, also mirrored into the application log. */
function cron_log(string $message, string $level = 'info'): void
{
    $line = sprintf('[%s] %s', date('Y-m-d H:i:s'), $message);
    echo $line, PHP_EOL;

    $file = MR_ROOT . '/storage/logs/cron-' . date('Y-m') . '.log';
    @file_put_contents($file, strtoupper($level) . ' ' . $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * Runs one named task, isolating its failures so a single broken job never
 * aborts the rest of the nightly run.
 *
 * @return array{name:string,ok:bool,result:mixed,error:?string}
 */
function cron_task(string $name, callable $fn): array
{
    $started = microtime(true);
    try {
        $result = $fn();
        cron_log(sprintf('✔ %s (%.2fs)', $name, microtime(true) - $started));
        return ['name' => $name, 'ok' => true, 'result' => $result, 'error' => null];
    } catch (\Throwable $e) {
        cron_log(sprintf('✘ %s — %s', $name, $e->getMessage()), 'error');
        return ['name' => $name, 'ok' => false, 'result' => null, 'error' => $e->getMessage()];
    }
}

/** Exits with 0 when every task succeeded, 1 otherwise. */
function cron_finish(array $tasks): void
{
    $failed = array_filter($tasks, static fn (array $t) => !$t['ok']);
    cron_log(sprintf(
        'پایان اجرا — %d کار، %d موفق، %d ناموفق.',
        count($tasks),
        count($tasks) - count($failed),
        count($failed)
    ));
    exit($failed === [] ? 0 : 1);
}
