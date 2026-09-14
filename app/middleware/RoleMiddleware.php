<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

/** Usage: 'RoleMiddleware:SUPER_ADMIN,ADMIN' */
final class RoleMiddleware implements MiddlewareInterface
{
    private array $roles;

    public function __construct(string ...$roles)
    {
        $this->roles = array_map('trim', $roles);
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!AuthService::hasAnyRole($this->roles)) {
            throw new HttpException(403, 'این بخش برای نقش کاربری شما در دسترس نیست.');
        }
        return $next($request);
    }
}
