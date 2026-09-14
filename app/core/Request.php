<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    private array $query;
    private array $body;
    private array $server;
    private array $cookies;
    private array $files;
    private array $attributes = [];
    private ?array $json = null;

    public function __construct(array $query, array $body, array $server, array $cookies, array $files)
    {
        $this->query   = $query;
        $this->body    = $body;
        $this->server  = $server;
        $this->cookies = $cookies;
        $this->files   = $files;
    }

    public static function capture(): self
    {
        return new self($_GET, $_POST, $_SERVER, $_COOKIE, $_FILES);
    }

    public function method(): string
    {
        $m = strtoupper((string)($this->server['REQUEST_METHOD'] ?? 'GET'));
        if ($m === 'POST') {
            $override = strtoupper((string)($this->body['_method'] ?? ''));
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }
        return $m;
    }

    public function path(): string
    {
        $uri  = (string)($this->server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $base = rtrim((string)Config::get('app.base_path', ''), '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public function isAjax(): bool
    {
        return strtolower((string)($this->server['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    public function wantsJson(): bool
    {
        if ($this->isAjax()) {
            return true;
        }
        $accept = (string)($this->server['HTTP_ACCEPT'] ?? '');
        return str_contains($accept, 'application/json') || str_starts_with($this->path(), '/api/');
    }

    public function isSecure(): bool
    {
        if (($this->server['HTTPS'] ?? '') !== '' && strtolower((string)$this->server['HTTPS']) !== 'off') {
            return true;
        }
        return strtolower((string)($this->server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    public function ip(): string
    {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($keys as $k) {
            if (!empty($this->server[$k])) {
                $ip = trim(explode(',', (string)$this->server[$k])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    public function userAgent(): string
    {
        return mb_substr((string)($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($this->server[$key]) ? (string)$this->server[$key] : $default;
    }

    public function json(): array
    {
        if ($this->json === null) {
            $raw  = file_get_contents('php://input') ?: '';
            $data = json_decode($raw, true);
            $this->json = is_array($data) ? $data : [];
        }
        return $this->json;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->body)) {
            return $this->body[$key];
        }
        $json = str_contains((string)$this->header('Content-Type', ''), 'application/json') ? $this->json() : [];
        if (array_key_exists($key, $json)) {
            return $json[$key];
        }
        return $this->query[$key] ?? $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $v = $this->input($key, $default);
        return is_scalar($v) ? trim((string)$v) : $default;
    }

    public function int(string $key, ?int $default = null): ?int
    {
        $v = $this->input($key, null);
        if ($v === null || $v === '') {
            return $default;
        }
        return is_numeric($v) ? (int)$v : $default;
    }

    public function float(string $key, ?float $default = null): ?float
    {
        $v = $this->input($key, null);
        if ($v === null || $v === '') {
            return $default;
        }
        return is_numeric($v) ? (float)$v : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $v = $this->input($key, null);
        if ($v === null) {
            return $default;
        }
        return in_array(strtolower((string)$v), ['1', 'true', 'on', 'yes'], true);
    }

    public function arr(string $key): array
    {
        $v = $this->input($key, []);
        return is_array($v) ? $v : [];
    }

    public function all(): array
    {
        $json = str_contains((string)$this->header('Content-Type', ''), 'application/json') ? $this->json() : [];
        return array_merge($this->query, $json, $this->body);
    }

    public function only(array $keys): array
    {
        $all = $this->all();
        $out = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $all)) {
                $out[$k] = $all[$k];
            }
        }
        return $out;
    }

    public function query(string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->query : ($this->query[$key] ?? $default);
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function setAttribute(string $k, mixed $v): void
    {
        $this->attributes[$k] = $v;
    }

    public function attribute(string $k, mixed $default = null): mixed
    {
        return $this->attributes[$k] ?? $default;
    }

    public function page(): int
    {
        return max(1, (int)($this->query['page'] ?? 1));
    }

    public function perPage(): int
    {
        $allowed = [20, 50, 100];
        $pp = (int)($this->query['per_page'] ?? 20);
        return in_array($pp, $allowed, true) ? $pp : 20;
    }
}
