<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;

/**
 * Lightweight router with {param} placeholders, middleware stack and groups.
 */
final class Router
{
    /** @var array<string, array<int, array{pattern:string, regex:string, params:array, handler:mixed, middleware:array, name:?string}>> */
    private array $routes = [];
    private array $groupStack = [];
    private array $named = [];

    public function get(string $p, mixed $h): self
    {
        return $this->add('GET', $p, $h);
    }

    public function post(string $p, mixed $h): self
    {
        return $this->add('POST', $p, $h);
    }

    public function put(string $p, mixed $h): self
    {
        return $this->add('PUT', $p, $h);
    }

    public function patch(string $p, mixed $h): self
    {
        return $this->add('PATCH', $p, $h);
    }

    public function delete(string $p, mixed $h): self
    {
        return $this->add('DELETE', $p, $h);
    }

    public function group(array $attrs, callable $fn): void
    {
        $this->groupStack[] = $attrs;
        $fn($this);
        array_pop($this->groupStack);
    }

    public function middleware(array|string $m): self
    {
        $m = (array)$m;
        $method = $this->lastMethod;
        $idx    = array_key_last($this->routes[$method]);
        $this->routes[$method][$idx]['middleware'] = array_merge($this->routes[$method][$idx]['middleware'], $m);
        return $this;
    }

    public function name(string $name): self
    {
        $method = $this->lastMethod;
        $idx    = array_key_last($this->routes[$method]);
        $this->routes[$method][$idx]['name'] = $name;
        $this->named[$name] = $this->routes[$method][$idx]['pattern'];
        return $this;
    }

    private string $lastMethod = 'GET';

    private function add(string $method, string $path, mixed $handler): self
    {
        $prefix     = '';
        $middleware = [];
        foreach ($this->groupStack as $g) {
            $prefix     .= rtrim((string)($g['prefix'] ?? ''), '/');
            $middleware  = array_merge($middleware, (array)($g['middleware'] ?? []));
        }
        $pattern = $prefix . '/' . trim($path, '/');
        $pattern = '/' . trim($pattern, '/');
        if ($pattern === '/' && $path !== '/') {
            $pattern = '/';
        }

        $params = [];
        $regex  = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/', function ($m) use (&$params) {
            $params[] = $m[1];
            return '(' . ($m[2] ?? '[^/]+') . ')';
        }, $pattern) ?? $pattern;

        $this->routes[$method][] = [
            'pattern'    => $pattern,
            'regex'      => '#^' . $regex . '$#u',
            'params'     => $params,
            'handler'    => $handler,
            'middleware' => $middleware,
            'name'       => null,
        ];
        $this->lastMethod = $method;
        return $this;
    }

    public function route(string $name, array $params = []): string
    {
        $p = $this->named[$name] ?? '/';
        foreach ($params as $k => $v) {
            $p = str_replace('{' . $k . '}', (string)$v, $p);
        }
        return $p;
    }

    /** @return array{handler:mixed, middleware:array, params:array} */
    public function match(string $method, string $path): array
    {
        foreach ($this->routes[$method] ?? [] as $r) {
            if (preg_match($r['regex'], $path, $m)) {
                array_shift($m);
                $params = [];
                foreach ($r['params'] as $i => $name) {
                    $params[$name] = $m[$i] ?? null;
                }
                return ['handler' => $r['handler'], 'middleware' => $r['middleware'], 'params' => $params];
            }
        }
        // Distinguish 405 from 404.
        foreach ($this->routes as $m2 => $set) {
            if ($m2 === $method) {
                continue;
            }
            foreach ($set as $r) {
                if (preg_match($r['regex'], $path)) {
                    throw new HttpException(405, 'متد درخواست مجاز نیست.');
                }
            }
        }
        throw new HttpException(404, 'صفحه مورد نظر یافت نشد.');
    }

    public function all(): array
    {
        return $this->routes;
    }
}
