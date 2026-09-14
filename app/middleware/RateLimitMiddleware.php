<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Exceptions\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;

/**
 * File-based sliding window rate limiter.
 * Usage: 'RateLimit:bucket,max,minutes' e.g. 'RateLimit:login,10,300' -> 10 requests
 * per 300 seconds, bucketed separately per route group so different endpoints
 * (login, booking, api, ...) don't share the same counter.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(private string $bucket = 'default', private string $max = '60', private string $minutes = '1')
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $max    = max(1, (int)$this->max);
        $window = max(1, (int)$this->minutes) * 60;
        $key    = sha1($this->bucket . '|' . $request->ip() . '|' . $request->path());
        $dir    = (string)Config::get('app.storage_path') . '/cache/ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . $key . '.json';
        $now  = time();

        $hits = [];
        if (is_file($file)) {
            $data = json_decode((string)file_get_contents($file), true);
            $hits = is_array($data) ? $data : [];
        }
        $hits = array_values(array_filter($hits, static fn ($t) => (int)$t > $now - $window));

        if (count($hits) >= $max) {
            Logger::security('Rate limit exceeded', ['ip' => $request->ip(), 'path' => $request->path()]);
            throw new HttpException(429, 'تعداد درخواست‌ها بیش از حد مجاز است. کمی بعد تلاش کنید.');
        }
        $hits[] = $now;
        @file_put_contents($file, json_encode($hits), LOCK_EX);

        $response = $next($request);
        return $response
            ->withHeader('X-RateLimit-Limit', (string)$max)
            ->withHeader('X-RateLimit-Remaining', (string)max(0, $max - count($hits)));
    }
}
