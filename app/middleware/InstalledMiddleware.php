<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;

/** Requires a completed installation; otherwise sends the visitor to /install. */
final class InstalledMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (!is_file((string)Config::get('app.installed_flag'))) {
            return Response::redirect(url('/install'));
        }
        return $next($request);
    }
}
