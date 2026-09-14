<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Daily-rotating file logger. Never logs secrets.
 */
final class Logger
{
    private const REDACT = ['password', 'password_confirmation', 'pass', 'token', 'api_key', 'secret', 'csrf_token', 'remember_token'];

    public static function dir(): string
    {
        $dir = (string)Config::get('app.storage_path', dirname(__DIR__, 2) . '/storage') . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        $line = sprintf(
            "[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context ? json_encode(self::redact($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );
        @file_put_contents(self::dir() . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function security(string $message, array $context = []): void
    {
        @file_put_contents(
            self::dir() . '/security-' . date('Y-m-d') . '.log',
            sprintf("[%s] %s %s\n", date('Y-m-d H:i:s'), $message, json_encode(self::redact($context), JSON_UNESCAPED_UNICODE)),
            FILE_APPEND | LOCK_EX
        );
        self::log('security', $message, $context);
    }

    public static function info(string $m, array $c = []): void
    {
        self::log('info', $m, $c);
    }

    public static function warning(string $m, array $c = []): void
    {
        self::log('warning', $m, $c);
    }

    public static function error(string $m, array $c = []): void
    {
        self::log('error', $m, $c);
    }

    private static function redact(array $ctx): array
    {
        foreach ($ctx as $k => $v) {
            if (is_array($v)) {
                $ctx[$k] = self::redact($v);
            } elseif (in_array(strtolower((string)$k), self::REDACT, true)) {
                $ctx[$k] = '***';
            }
        }
        return $ctx;
    }
}
