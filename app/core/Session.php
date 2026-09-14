<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    private static bool $started = false;

    public static function start(bool $secure = false): void
    {
        if (self::$started || PHP_SAPI === 'cli') {
            self::$started = true;
            if (!isset($_SESSION)) {
                $_SESSION = [];
            }
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $cfg = (array)Config::get('auth.session', []);
        session_name((string)($cfg['name'] ?? 'MR_SESSION'));
        session_set_cookie_params([
            'lifetime' => (int)($cfg['lifetime'] ?? 7200),
            'path'     => '/',
            'domain'   => (string)($cfg['domain'] ?? ''),
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => (string)($cfg['samesite'] ?? 'Lax'),
        ]);
        session_start();
        self::$started = true;

        // Idle timeout + periodic id rotation to limit fixation.
        $idle = (int)($cfg['idle_timeout'] ?? 7200);
        if (isset($_SESSION['_last_activity']) && time() - (int)$_SESSION['_last_activity'] > $idle) {
            self::destroy();
            session_start();
        }
        $_SESSION['_last_activity'] = time();
        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = time();
        } elseif (time() - (int)$_SESSION['_created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_created'] = time();
        }
    }

    public static function get(string $k, mixed $default = null): mixed
    {
        return $_SESSION[$k] ?? $default;
    }

    public static function set(string $k, mixed $v): void
    {
        $_SESSION[$k] = $v;
    }

    public static function has(string $k): bool
    {
        return isset($_SESSION[$k]);
    }

    public static function forget(string $k): void
    {
        unset($_SESSION[$k]);
    }

    public static function all(): array
    {
        return $_SESSION ?? [];
    }

    public static function regenerate(): void
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool)$p['secure'], (bool)$p['httponly']);
            }
            session_destroy();
        }
    }

    /* ---------- flash ---------- */

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function pullFlash(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }

    public static function flashInput(array $input): void
    {
        unset($input['password'], $input['password_confirmation'], $input['csrf_token']);
        $_SESSION['_old_input'] = $input;
    }

    public static function oldInput(): array
    {
        $o = $_SESSION['_old_input'] ?? [];
        unset($_SESSION['_old_input']);
        return $o;
    }

    public static function flashErrors(array $errors): void
    {
        $_SESSION['_errors'] = $errors;
    }

    public static function pullErrors(): array
    {
        $e = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);
        return $e;
    }
}
