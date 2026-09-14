<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Database-driven settings with an in-request cache.
 */
final class SettingsService
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                $rows = Database::instance()->select('SELECT setting_key, setting_value, type FROM settings');
                foreach ($rows as $r) {
                    self::$cache[$r['setting_key']] = self::cast($r['setting_value'], (string)$r['type']);
                }
            } catch (\Throwable) {
                self::$cache = [];
            }
        }
        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function set(string $key, mixed $value, string $type = 'STRING', string $group = 'general', ?string $label = null): void
    {
        $db  = Database::instance();
        $raw = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (is_bool($value) ? ($value ? '1' : '0') : (string)$value);
        $exists = $db->scalar('SELECT id FROM settings WHERE setting_key = :k', ['k' => $key]);
        if ($exists) {
            $db->update('settings', ['setting_value' => $raw, 'type' => $type], 'id = :id', ['id' => (int)$exists]);
        } else {
            $db->insert('settings', [
                'setting_key'   => $key,
                'setting_value' => $raw,
                'type'          => $type,
                'group_name'    => $group,
                'label'         => $label ?? $key,
            ]);
        }
        self::$cache = null;
    }

    public static function setMany(array $pairs, string $group = 'general'): void
    {
        Database::instance()->transaction(function () use ($pairs, $group): void {
            foreach ($pairs as $k => $v) {
                self::set((string)$k, $v, is_numeric($v) ? 'DECIMAL' : 'STRING', $group);
            }
        });
        self::$cache = null;
    }

    public static function group(string $group): array
    {
        $rows = Database::instance()->select(
            'SELECT setting_key, setting_value, type, label FROM settings WHERE group_name = :g ORDER BY id',
            ['g' => $group]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[$r['setting_key']] = [
                'value' => self::cast($r['setting_value'], (string)$r['type']),
                'label' => $r['label'],
                'type'  => $r['type'],
            ];
        }
        return $out;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    private static function cast(?string $value, string $type): mixed
    {
        return match ($type) {
            'INT'     => (int)$value,
            'DECIMAL' => (float)$value,
            'BOOL'    => in_array($value, ['1', 'true', 'yes', 'on'], true),
            'JSON'    => json_decode((string)$value, true) ?? [],
            default   => $value,
        };
    }
}
