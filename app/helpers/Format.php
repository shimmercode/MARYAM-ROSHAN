<?php
declare(strict_types=1);

namespace App\Helpers;

final class Format
{
    private const FA = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    private const EN = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    private const AR = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    public static function digits(string $s): string
    {
        return str_replace(self::EN, self::FA, $s);
    }

    public static function toEnglishDigits(string $s): string
    {
        return str_replace(array_merge(self::FA, self::AR), array_merge(self::EN, self::EN), $s);
    }

    /** Money formatting; values are DECIMAL strings. */
    public static function money(float|string|null $amount, bool $withCurrency = true, bool $persian = true): string
    {
        $n = number_format((float)($amount ?? 0), 0, '.', ',');
        if ($persian) {
            $n = self::digits($n);
        }
        return $withCurrency ? $n . ' تومان' : $n;
    }

    public static function number(float|int|string|null $n, int $decimals = 0): string
    {
        return self::digits(number_format((float)($n ?? 0), $decimals, '.', ','));
    }

    public static function percent(float|int|null $n, int $decimals = 1): string
    {
        return self::digits(number_format((float)($n ?? 0), $decimals)) . '٪';
    }

    /** Normalize Iranian mobile numbers to 09xxxxxxxxx. */
    public static function mobile(string $mobile): string
    {
        $m = preg_replace('/\D/', '', self::toEnglishDigits($mobile)) ?? '';
        if (str_starts_with($m, '0098')) {
            $m = '0' . substr($m, 4);
        } elseif (str_starts_with($m, '98') && strlen($m) === 12) {
            $m = '0' . substr($m, 2);
        } elseif (strlen($m) === 10 && str_starts_with($m, '9')) {
            $m = '0' . $m;
        }
        return $m;
    }

    public static function isValidMobile(string $mobile): bool
    {
        return (bool)preg_match('/^09\d{9}$/', self::mobile($mobile));
    }

    public static function duration(int $minutes): string
    {
        if ($minutes < 60) {
            return self::digits((string)$minutes) . ' دقیقه';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return self::digits((string)$h) . ' ساعت' . ($m ? ' و ' . self::digits((string)$m) . ' دقیقه' : '');
    }

    public static function slug(string $text): string
    {
        $text = trim(preg_replace('/[\s\-]+/u', '-', strip_tags($text)) ?? '');
        $text = preg_replace('/[^\p{L}\p{N}\-]/u', '', $text) ?? '';
        return mb_strtolower(trim($text, '-'), 'UTF-8');
    }

    public static function excerpt(?string $text, int $len = 120): string
    {
        $text = trim((string)$text);
        return mb_strlen($text, 'UTF-8') > $len ? mb_substr($text, 0, $len, 'UTF-8') . '…' : $text;
    }

    public static function maskMobile(string $mobile): string
    {
        $m = self::mobile($mobile);
        return strlen($m) === 11 ? self::digits(substr($m, 0, 4) . '***' . substr($m, -4)) : $m;
    }

    public static function initials(string $first, string $last = ''): string
    {
        return mb_substr(trim($first), 0, 1, 'UTF-8') . mb_substr(trim($last), 0, 1, 'UTF-8');
    }
}
