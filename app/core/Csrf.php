<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public const FIELD = 'csrf_token';

    public static function token(): string
    {
        $t = Session::get('_csrf_token');
        if (!is_string($t) || $t === '') {
            $t = bin2hex(random_bytes(32));
            Session::set('_csrf_token', $t);
        }
        return $t;
    }

    public static function verify(?string $token): bool
    {
        $expected = Session::get('_csrf_token');
        return is_string($expected) && is_string($token) && $token !== '' && hash_equals($expected, $token);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function rotate(): void
    {
        Session::forget('_csrf_token');
        self::token();
    }
}
