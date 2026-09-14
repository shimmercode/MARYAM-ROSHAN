<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Helpers\Format;
use App\Services\Sms\KavenegarProvider;
use App\Services\Sms\LogSmsProvider;
use App\Services\Sms\MelipayamakProvider;
use App\Services\Sms\SmsProviderInterface;

/**
 * Unified notification layer: in-app, SMS and email.
 * Every outbound message is recorded in communication_logs.
 */
final class NotificationService
{
    /* ----------------------------- in-app ----------------------------- */

    public static function notifyUser(int $userId, string $title, string $body = '', string $type = 'INFO', ?string $link = null, ?string $icon = null): int
    {
        $db = Database::instance();
        return $db->transaction(function () use ($db, $userId, $title, $body, $type, $link, $icon): int {
            $nid = $db->insert('notifications', [
                'title' => $title, 'body' => $body, 'type' => $type, 'link' => $link, 'icon' => $icon,
            ]);
            $db->insert('user_notifications', ['notification_id' => $nid, 'user_id' => $userId, 'status' => 'UNREAD']);
            return $nid;
        });
    }

    public static function notifyCustomer(int $customerId, string $title, string $body = '', string $type = 'INFO', ?string $link = null): int
    {
        $db = Database::instance();
        return $db->transaction(function () use ($db, $customerId, $title, $body, $type, $link): int {
            $nid = $db->insert('notifications', ['title' => $title, 'body' => $body, 'type' => $type, 'link' => $link]);
            $db->insert('user_notifications', ['notification_id' => $nid, 'customer_id' => $customerId, 'status' => 'UNREAD']);
            return $nid;
        });
    }

    /** Notify every user holding a given permission (e.g. low-stock alerts). */
    public static function notifyByPermission(string $permission, string $title, string $body = '', string $type = 'INFO', ?string $link = null): void
    {
        $userIds = Database::instance()->select(
            'SELECT DISTINCT ur.user_id FROM user_roles ur
             JOIN role_permissions rp ON rp.role_id = ur.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE p.slug = :perm',
            ['perm' => $permission]
        );
        foreach ($userIds as $row) {
            self::notifyUser((int)$row['user_id'], $title, $body, $type, $link);
        }
    }

    public static function unreadCount(int $userId): int
    {
        try {
            return (int)Database::instance()->scalar(
                "SELECT COUNT(*) FROM user_notifications WHERE user_id = :u AND status = 'UNREAD'",
                ['u' => $userId]
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function listFor(int $userId, string $status = 'UNREAD', int $limit = 20, int $offset = 0): array
    {
        $statusSql = $status === 'ALL' ? '' : ' AND un.status = :st';
        $params    = ['u' => $userId];
        if ($status !== 'ALL') {
            $params['st'] = $status;
        }
        return Database::instance()->select(
            "SELECT un.id, un.status, un.read_at, n.title, n.body, n.type, n.icon, n.link, n.created_at
             FROM user_notifications un
             JOIN notifications n ON n.id = un.notification_id
             WHERE un.user_id = :u{$statusSql}
             ORDER BY un.id DESC LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    public static function listForCustomer(int $customerId, int $limit = 20): array
    {
        return Database::instance()->select(
            "SELECT un.id, un.status, n.title, n.body, n.type, n.link, n.created_at
             FROM user_notifications un
             JOIN notifications n ON n.id = un.notification_id
             WHERE un.customer_id = :c ORDER BY un.id DESC LIMIT {$limit}",
            ['c' => $customerId]
        );
    }

    public static function markRead(int $userNotificationId, int $userId): void
    {
        Database::instance()->execute(
            "UPDATE user_notifications SET status = 'READ', read_at = NOW() WHERE id = :id AND user_id = :u",
            ['id' => $userNotificationId, 'u' => $userId]
        );
    }

    public static function markAllRead(int $userId): int
    {
        return Database::instance()->execute(
            "UPDATE user_notifications SET status = 'READ', read_at = NOW() WHERE user_id = :u AND status = 'UNREAD'",
            ['u' => $userId]
        );
    }

    public static function archive(int $userNotificationId, int $userId): void
    {
        Database::instance()->execute(
            "UPDATE user_notifications SET status = 'ARCHIVED' WHERE id = :id AND user_id = :u",
            ['id' => $userNotificationId, 'u' => $userId]
        );
    }

    /* ------------------------------- SMS ------------------------------ */

    public static function smsProvider(): SmsProviderInterface
    {
        return match ((string)Config::get('services.sms.driver', 'log')) {
            'kavenegar'   => new KavenegarProvider(),
            'melipayamak' => new MelipayamakProvider(),
            default       => new LogSmsProvider(),
        };
    }

    /** @return array{success:bool, error:?string} */
    public static function sendSms(string $mobile, string $message, ?int $customerId = null, ?string $refType = null, ?int $refId = null): array
    {
        $mobile = Format::mobile($mobile);
        if (!Format::isValidMobile($mobile)) {
            return ['success' => false, 'error' => 'شماره موبایل معتبر نیست.'];
        }

        $provider = self::smsProvider();
        $enabled  = (bool)Config::get('services.sms.enabled', false);
        if (!$enabled && $provider->name() !== 'log') {
            $provider = new LogSmsProvider();
        }

        $result = $provider->send($mobile, $message);

        try {
            Database::instance()->insert('communication_logs', [
                'customer_id'    => $customerId,
                'channel'        => 'SMS',
                'direction'      => 'OUT',
                'recipient'      => $mobile,
                'body'           => $message,
                'status'         => $result['success'] ? 'SENT' : 'FAILED',
                'provider'       => $provider->name(),
                'provider_ref'   => $result['reference'] ?? null,
                'error'          => $result['error'] ?? null,
                'reference_type' => $refType,
                'reference_id'   => $refId,
            ]);
        } catch (\Throwable $e) {
            Logger::error('Failed to log SMS', ['message' => $e->getMessage()]);
        }

        return ['success' => (bool)$result['success'], 'error' => $result['error'] ?? null];
    }

    /* ------------------------------ Email ----------------------------- */

    public static function sendEmail(string $to, string $subject, string $body, ?int $customerId = null): array
    {
        $driver  = (string)Config::get('services.mail.driver', 'log');
        $enabled = (bool)Config::get('services.mail.enabled', false);
        $ok      = true;
        $error   = null;

        if (!$enabled || $driver === 'log') {
            Logger::info('Email (log driver)', ['to' => $to, 'subject' => $subject]);
        } elseif ($driver === 'mail' && function_exists('mail')) {
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: ' . Config::get('services.mail.from_name') . ' <' . Config::get('services.mail.from') . '>',
            ];
            $ok = @mail($to, $subject, $body, implode("\r\n", $headers));
            if (!$ok) {
                $error = 'ارسال ایمیل ناموفق بود.';
            }
        } else {
            $ok    = false;
            $error = 'درایور ایمیل پشتیبانی نمی‌شود: ' . $driver;
        }

        try {
            Database::instance()->insert('communication_logs', [
                'customer_id' => $customerId,
                'channel'     => 'EMAIL',
                'direction'   => 'OUT',
                'recipient'   => $to,
                'subject'     => $subject,
                'body'        => $body,
                'status'      => $ok ? 'SENT' : 'FAILED',
                'provider'    => $driver,
                'error'       => $error,
            ]);
        } catch (\Throwable) {
        }

        return ['success' => $ok, 'error' => $error];
    }

    /* ---------------------------- Templates --------------------------- */

    public static function renderTemplate(string $code, array $vars = []): ?array
    {
        $tpl = Database::instance()->selectOne(
            "SELECT code, channel, subject, body FROM message_templates WHERE code = :c AND status = 'ACTIVE'",
            ['c' => $code]
        );
        if ($tpl === null) {
            return null;
        }
        $replace = static function (?string $text) use ($vars): string {
            foreach ($vars as $k => $v) {
                $text = str_replace('{' . $k . '}', (string)$v, (string)$text);
            }
            return (string)$text;
        };
        return [
            'channel' => $tpl['channel'],
            'subject' => $replace($tpl['subject']),
            'body'    => $replace($tpl['body']),
        ];
    }

    public static function sendTemplate(string $code, string $recipient, array $vars = [], ?int $customerId = null, ?string $refType = null, ?int $refId = null): array
    {
        $tpl = self::renderTemplate($code, $vars);
        if ($tpl === null) {
            return ['success' => false, 'error' => 'قالب پیام یافت نشد: ' . $code];
        }
        return match ($tpl['channel']) {
            'EMAIL' => self::sendEmail($recipient, (string)$tpl['subject'], (string)$tpl['body'], $customerId),
            'INAPP' => $customerId
                ? ['success' => (bool)self::notifyCustomer($customerId, (string)($tpl['subject'] ?: 'اعلان'), (string)$tpl['body']), 'error' => null]
                : ['success' => false, 'error' => 'مشتری مشخص نشده است.'],
            default => self::sendSms($recipient, (string)$tpl['body'], $customerId, $refType, $refId),
        };
    }
}
