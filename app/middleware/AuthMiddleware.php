<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (!AuthService::check()) {
            // Try the "remember me" cookie before rejecting.
            if (!AuthService::loginFromRememberCookie($request)) {
                if ($request->wantsJson()) {
                    throw new HttpException(401, 'برای ادامه باید وارد شوید.');
                }
                Session::set('_intended_url', $request->path());
                return Response::redirect(url('/login'));
            }
        }
        return $next($request);
    }
}
