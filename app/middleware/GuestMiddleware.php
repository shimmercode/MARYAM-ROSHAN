<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class GuestMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (AuthService::check()) {
            return Response::redirect(url(AuthService::homeRoute()));
        }
        return $next($request);
    }
}
