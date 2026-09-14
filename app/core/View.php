<?php
declare(strict_types=1);

namespace App\Core;

use App\Helpers\Format;
use RuntimeException;

/**
 * Plain-PHP template engine with layout inheritance and component partials.
 */
final class View
{
    private static array $shared = [];
    private static array $sections = [];
    private static array $stack = [];
    private static ?string $layout = null;

    public static function share(string $k, mixed $v): void
    {
        self::$shared[$k] = $v;
    }

    public static function sharedData(): array
    {
        return self::$shared;
    }

    public static function render(string $template, array $data = []): string
    {
        self::$layout   = null;
        self::$sections = [];
        $content = self::renderRaw($template, $data);

        // Layouts may nest.
        $guard = 0;
        while (self::$layout !== null && $guard++ < 5) {
            $layout = self::$layout;
            self::$layout = null;
            // Only fall back to the template's own output when it didn't
            // already capture an explicit content section itself (the
            // normal case: every view wraps its body in
            // View::section('content') ... View::endSection()).
            if (!array_key_exists('content', self::$sections)) {
                self::$sections['content'] = $content;
            }
            $content = self::renderRaw('layouts/' . $layout, $data);
        }
        return $content;
    }

    public static function renderRaw(string $template, array $data = []): string
    {
        $file = self::path($template);
        if (!is_file($file)) {
            throw new RuntimeException('View not found: ' . $template);
        }
        $vars = array_merge(self::$shared, $data);
        extract($vars, EXTR_SKIP);
        ob_start();
        try {
            /** @psalm-suppress UnresolvableInclude */
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string)ob_get_clean();
    }

    /** Render a component partial: component('card', [...]) */
    public static function component(string $name, array $data = []): string
    {
        return self::renderRaw('components/' . $name, $data);
    }

    public static function partial(string $name, array $data = []): string
    {
        return self::renderRaw('partials/' . $name, $data);
    }

    public static function extend(string $layout): void
    {
        self::$layout = $layout;
    }

    public static function section(string $name): void
    {
        self::$stack[] = $name;
        ob_start();
    }

    public static function endSection(): void
    {
        $name = array_pop(self::$stack);
        if ($name !== null) {
            self::$sections[$name] = (string)ob_get_clean();
        }
    }

    public static function yield(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }

    public static function hasSection(string $name): bool
    {
        return isset(self::$sections[$name]);
    }

    public static function path(string $template): string
    {
        $base = (string)Config::get('app.views_path', dirname(__DIR__) . '/views');
        return $base . '/' . str_replace(['..', '\\'], '', $template) . '.php';
    }

    /** Escape helper (XSS). */
    public static function e(mixed $v): string
    {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
