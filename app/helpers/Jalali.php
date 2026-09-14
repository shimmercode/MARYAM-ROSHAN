<?php
declare(strict_types=1);

namespace App\Helpers;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Gregorian <-> Jalali (Shamsi) conversion.
 * Database always stores Gregorian; Jalali is a presentation concern.
 */
final class Jalali
{
    public const MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    public const WEEKDAYS = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

    /** @return array{0:int,1:int,2:int} [jy, jm, jd] */
    public static function toJalali(int $gy, int $gm, int $gd): array
    {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
              + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }
        return [$jy, $jm, $jd];
    }

    /** @return array{0:int,1:int,2:int} [gy, gm, gd] */
    public static function toGregorian(int $jy, int $jm, int $jd): array
    {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4) + $jd
              + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
        $gy = 400 * intdiv($days, 146097);
        $days %= 146097;
        if ($days > 36524) {
            $gy += 100 * intdiv(--$days, 36524);
            $days %= 36524;
            if ($days >= 365) {
                $days++;
            }
        }
        $gy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $gy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        $gd = $days + 1;
        $sal_a = [0, 31, (($gy % 4 === 0 && $gy % 100 !== 0) || ($gy % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $gm = 0;
        while ($gm < 13 && $gd > $sal_a[$gm]) {
            $gd -= $sal_a[$gm];
            $gm++;
        }
        return [$gy, $gm, $gd];
    }

    public static function isLeap(int $jy): bool
    {
        return self::leapCheck($jy);
    }

    private static function leapCheck(int $jy): bool
    {
        $mod = $jy % 33;
        return in_array($mod, [1, 5, 9, 13, 17, 22, 26, 30], true);
    }

    public static function daysInMonth(int $jy, int $jm): int
    {
        if ($jm <= 6) {
            return 31;
        }
        if ($jm <= 11) {
            return 30;
        }
        return self::leapCheck($jy) ? 30 : 29;
    }

    /** Format a Gregorian datetime string as Jalali. Supported: Y, m, n, d, j, H, i, s, F, l */
    public static function format(?string $datetime, string $pattern = 'Y/m/d', ?string $tz = null): string
    {
        if ($datetime === null || $datetime === '' || str_starts_with($datetime, '0000')) {
            return '-';
        }
        try {
            $dt = new DateTimeImmutable($datetime, new DateTimeZone($tz ?? date_default_timezone_get()));
        } catch (\Throwable) {
            return '-';
        }
        [$jy, $jm, $jd] = self::toJalali((int)$dt->format('Y'), (int)$dt->format('n'), (int)$dt->format('j'));
        $weekday = self::WEEKDAYS[((int)$dt->format('w') + 1) % 7];

        $map = [
            'Y' => (string)$jy,
            'm' => str_pad((string)$jm, 2, '0', STR_PAD_LEFT),
            'n' => (string)$jm,
            'd' => str_pad((string)$jd, 2, '0', STR_PAD_LEFT),
            'j' => (string)$jd,
            'H' => $dt->format('H'),
            'i' => $dt->format('i'),
            's' => $dt->format('s'),
            'F' => self::MONTHS[$jm - 1],
            'l' => $weekday,
        ];
        $out = '';
        foreach (str_split($pattern) as $ch) {
            $out .= $map[$ch] ?? $ch;
        }
        return $out;
    }

    /** Parse "1403/05/12" (or with -) into Y-m-d Gregorian. */
    public static function parse(string $jalaliDate): ?string
    {
        $jalaliDate = Format::toEnglishDigits(trim($jalaliDate));
        if (!preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $jalaliDate, $m)) {
            return null;
        }
        [$gy, $gm, $gd] = self::toGregorian((int)$m[1], (int)$m[2], (int)$m[3]);
        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }

    public static function today(string $pattern = 'Y/m/d'): string
    {
        return self::format(date('Y-m-d H:i:s'), $pattern);
    }

    /** Relative human string in Persian. */
    public static function ago(?string $datetime): string
    {
        if (!$datetime) {
            return '-';
        }
        $diff = time() - strtotime($datetime);
        if ($diff < 60) {
            return 'لحظاتی پیش';
        }
        if ($diff < 3600) {
            return Format::digits((string)intdiv($diff, 60)) . ' دقیقه پیش';
        }
        if ($diff < 86400) {
            return Format::digits((string)intdiv($diff, 3600)) . ' ساعت پیش';
        }
        if ($diff < 2592000) {
            return Format::digits((string)intdiv($diff, 86400)) . ' روز پیش';
        }
        return self::format($datetime, 'Y/m/d');
    }
}
