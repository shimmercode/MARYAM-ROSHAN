<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;

/** Blocks the installer once installation has completed (self-disabling installer). */
final class NotInstalledMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (is_file((string)Config::get('app.installed_flag'))) {
            throw new HttpException(
                403,
                'نصب سامانه قبلاً انجام شده است. برای نصب مجدد، فایل storage/installed.lock را حذف کنید.'
            );
        }
        return $next($request);
    }
}
