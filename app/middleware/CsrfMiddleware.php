<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Exceptions\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;

final class CsrfMiddleware implements MiddlewareInterface
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, callable $next): Response
    {
        if (in_array($request->method(), self::SAFE_METHODS, true)) {
            return $next($request);
        }

        $token = $request->str(Csrf::FIELD) ?: (string)$request->header('X-CSRF-Token', '');
        if (!Csrf::verify($token)) {
            Logger::security('CSRF token mismatch', ['path' => $request->path(), 'ip' => $request->ip()]);
            throw new HttpException(419, 'نشست شما منقضی شده است. لطفاً صفحه را تازه‌سازی کنید.');
        }
        return $next($request);
    }
}
