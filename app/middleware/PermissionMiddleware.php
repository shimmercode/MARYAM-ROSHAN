<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Exceptions\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

/**
 * Usage: 'PermissionMiddleware:customers.view' or multiple (OR): 'PermissionMiddleware:a,b'
 */
final class PermissionMiddleware implements MiddlewareInterface
{
    private array $permissions;

    public function __construct(string ...$permissions)
    {
        $this->permissions = $permissions;
    }

    public function handle(Request $request, callable $next): Response
    {
        foreach ($this->permissions as $p) {
            if (AuthService::can(trim($p))) {
                return $next($request);
            }
        }
        Logger::security('Permission denied', [
            'user_id'     => AuthService::id(),
            'required'    => $this->permissions,
            'path'        => $request->path(),
            'ip'          => $request->ip(),
        ]);
        throw new HttpException(403, 'شما دسترسی لازم برای این عملیات را ندارید.');
    }
}
