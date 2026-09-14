<?php
declare(strict_types=1);

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Helpers\Format;
use App\Helpers\Jalali;
use App\Services\AuthService;

if (!function_exists('e')) {
    function e(mixed $v): string
    {
        return View::e($v);
    }
}

if (!function_exists('url')) {
    function url(string $path = '/'): string
    {
        $base = rtrim((string)Config::get('app.url', ''), '/');
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/')) . '?v=' . Config::get('app.version', '1');
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        $old = View::sharedData()['old'] ?? [];
        return $old[$key] ?? $default;
    }
}

if (!function_exists('error_for')) {
    function error_for(string $key): ?string
    {
        $errors = View::sharedData()['errors'] ?? [];
        $v = $errors[$key] ?? null;
        return is_array($v) ? (string)($v[0] ?? null) : $v;
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return AuthService::currentUser();
    }
}

if (!function_exists('can')) {
    function can(string $permission): bool
    {
        return AuthService::can($permission);
    }
}

if (!function_exists('money')) {
    function money(float|string|null $v, bool $withCurrency = true): string
    {
        return Format::money($v, $withCurrency);
    }
}

if (!function_exists('fa')) {
    function fa(string|int|float|null $v): string
    {
        return Format::digits((string)($v ?? ''));
    }
}

if (!function_exists('jdate')) {
    function jdate(?string $datetime, string $pattern = 'Y/m/d'): string
    {
        return Format::digits(Jalali::format($datetime, $pattern));
    }
}

if (!function_exists('component')) {
    function component(string $name, array $data = []): string
    {
        return View::component($name, $data);
    }
}

if (!function_exists('partial')) {
    function partial(string $name, array $data = []): string
    {
        return View::partial($name, $data);
    }
}

if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return \App\Services\SettingsService::get($key, $default);
    }
}

if (!function_exists('active_class')) {
    function active_class(string $prefix, string $class = 'is-active'): string
    {
        $path = View::sharedData()['currentPath'] ?? '';
        return str_starts_with((string)$path, $prefix) ? $class : '';
    }
}

if (!function_exists('str_random')) {
    function str_random(int $len = 16): string
    {
        return substr(bin2hex(random_bytes((int)ceil($len / 2))), 0, $len);
    }
}

if (!function_exists('slugify')) {
    /** URL-safe slug that keeps Persian letters intact. */
    function slugify(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[\x{200C}\x{200F}\x{200E}]/u', '', $value) ?? $value;
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? $value;
        $value = trim((string)$value, '-');
        $value = mb_strtolower($value);
        return $value !== '' ? $value : 'item-' . substr(bin2hex(random_bytes(4)), 0, 6);
    }
}
