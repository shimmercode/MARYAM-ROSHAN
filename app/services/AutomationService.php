<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

/**
 * Rule-based marketing automation: Trigger -> Conditions -> Actions.
 * No visual builder; the backend model is real and testable.
 */
final class AutomationService
{
    public const TRIGGERS = [
        'appointment_created'   => 'ایجاد نوبت',
        'appointment_confirmed' => 'تایید نوبت',
        'appointment_completed' => 'تکمیل نوبت',
        'appointment_cancelled' => 'لغو نوبت',
        'appointment_no_show'   => 'عدم مراجعه',
        'invoice_created'       => 'صدور فاکتور',
        'birthday'              => 'تولد مشتری',
        'customer_inactive'     => 'عدم مراجعه طولانی',
        'review_requested'      => 'درخواست نظرسنجی',
        'loyalty_tier_changed'  => 'تغییر سطح وفاداری',
        'customer_created'      => 'ثبت مشتری جدید',
    ];

    public const ACTIONS = [
        'SEND_SMS'     => 'ارسال پیامک',
        'SEND_EMAIL'   => 'ارسال ایمیل',
        'ADD_TAG'      => 'افزودن برچسب',
        'REMOVE_TAG'   => 'حذف برچسب',
        'ADD_POINTS'   => 'افزودن امتیاز',
        'CREATE_TASK'  => 'ایجاد وظیفه',
        'NOTIFY_ADMIN' => 'اطلاع به مدیر',
    ];

    /** Fire a trigger; matching automations run their actions. */
    public static function fire(string $trigger, array $context = []): void
    {
        try {
            $db          = Database::instance();
            $automations = $db->select(
                "SELECT id, name FROM automations WHERE trigger_event = :t AND status = 'ACTIVE'",
                ['t' => $trigger]
            );
            foreach ($automations as $automation) {
                $id      = (int)$automation['id'];
                $matched = self::evaluate($id, $context);
                $ran     = 0;
                $error   = null;
                if ($matched) {
                    try {
                        $ran = self::runActions($id, $context);
                        $db->execute('UPDATE automations SET run_count = run_count + 1 WHERE id = :i', ['i' => $id]);
                    } catch (\Throwable $e) {
                        $error = mb_substr($e->getMessage(), 0, 255);
                        Logger::error('Automation action failed', ['automation_id' => $id, 'message' => $e->getMessage()]);
                    }
                }
                $db->insert('automation_logs', [
                    'automation_id' => $id,
                    'trigger_event' => $trigger,
                    'context'       => json_encode($context, JSON_UNESCAPED_UNICODE),
                    'matched'       => $matched ? 1 : 0,
                    'actions_run'   => $ran,
                    'error'         => $error,
                ]);
            }
        } catch (\Throwable $e) {
            // Automation must never break the main business transaction path.
            Logger::error('Automation trigger failed', ['trigger' => $trigger, 'message' => $e->getMessage()]);
        }
    }

    private static function evaluate(int $automationId, array $context): bool
    {
        $rules = Database::instance()->select('SELECT field, operator, value FROM automation_rules WHERE automation_id = :a', ['a' => $automationId]);
        if ($rules === []) {
            return true; // no conditions -> always match
        }
        $facts = self::facts($context);
        foreach ($rules as $rule) {
            $actual   = $facts[$rule['field']] ?? null;
            $expected = $rule['value'];
            $ok = match ((string)$rule['operator']) {
                'EQ'       => (string)$actual === (string)$expected,
                'NEQ'      => (string)$actual !== (string)$expected,
                'GT'       => (float)$actual >  (float)$expected,
                'GTE'      => (float)$actual >= (float)$expected,
                'LT'       => (float)$actual <  (float)$expected,
                'LTE'      => (float)$actual <= (float)$expected,
                'IN'       => in_array((string)$actual, array_map('trim', explode(',', (string)$expected)), true),
                'CONTAINS' => str_contains((string)$actual, (string)$expected),
                default    => false,
            };
            if (!$ok) {
                return false;
            }
        }
        return true;
    }

    /** Enrich the trigger context with customer facts usable in conditions. */
    private static function facts(array $context): array
    {
        $facts = $context;
        if (!empty($context['customer_id'])) {
            $c = Database::instance()->selectOne(
                'SELECT visits_count, total_spent, loyalty_points, health_score, churn_risk, gender,
                        preferred_branch_id, loyalty_tier_id, marketing_opt_in
                 FROM customers WHERE id = :c',
                ['c' => (int)$context['customer_id']]
            );
            if ($c !== null) {
                $facts = array_merge($facts, $c);
            }
        }
        return $facts;
    }

    private static function runActions(int $automationId, array $context): int
    {
        $db      = Database::instance();
        $actions = $db->select(
            'SELECT action_type, template_id, params, delay_minutes FROM automation_actions
             WHERE automation_id = :a ORDER BY sort_order, id',
            ['a' => $automationId]
        );

        $customerId = isset($context['customer_id']) ? (int)$context['customer_id'] : null;
        $customer   = $customerId ? $db->selectOne('SELECT id, first_name, last_name, mobile, email FROM customers WHERE id = :c', ['c' => $customerId]) : null;
        $count      = 0;

        foreach ($actions as $action) {
            $params = json_decode((string)($action['params'] ?? '{}'), true) ?: [];
            $vars   = array_merge($context, [
                'name'   => $customer ? trim($customer['first_name'] . ' ' . $customer['last_name']) : '',
                'first'  => $customer['first_name'] ?? '',
                'salon'  => (string)SettingsService::get('salon_name', 'سالن زیبایی مریم روشن'),
            ]);

            switch ((string)$action['action_type']) {
                case 'SEND_SMS':
                    if ($customer && !empty($customer['mobile'])) {
                        $body = self::resolveBody($action, $params, $vars);
                        if ($body !== null) {
                            NotificationService::sendSms((string)$customer['mobile'], $body, $customerId, 'automation', $automationId);
                            $count++;
                        }
                    }
                    break;

                case 'SEND_EMAIL':
                    if ($customer && !empty($customer['email'])) {
                        $body = self::resolveBody($action, $params, $vars);
                        if ($body !== null) {
                            NotificationService::sendEmail((string)$customer['email'], (string)($params['subject'] ?? 'اطلاع‌رسانی'), $body, $customerId);
                            $count++;
                        }
                    }
                    break;

                case 'ADD_TAG':
                    if ($customerId && !empty($params['tag'])) {
                        $tagId = $db->scalar('SELECT id FROM customer_tags WHERE name = :n', ['n' => $params['tag']])
                              ?: $db->insert('customer_tags', ['name' => (string)$params['tag']]);
                        $exists = $db->scalar('SELECT COUNT(*) FROM customer_tag_map WHERE customer_id = :c AND tag_id = :t', ['c' => $customerId, 't' => (int)$tagId]);
                        if (!$exists) {
                            $db->insert('customer_tag_map', ['customer_id' => $customerId, 'tag_id' => (int)$tagId]);
                        }
                        $count++;
                    }
                    break;

                case 'REMOVE_TAG':
                    if ($customerId && !empty($params['tag'])) {
                        $tagId = $db->scalar('SELECT id FROM customer_tags WHERE name = :n', ['n' => $params['tag']]);
                        if ($tagId) {
                            $db->delete('customer_tag_map', 'customer_id = :c AND tag_id = :t', ['c' => $customerId, 't' => (int)$tagId]);
                            $count++;
                        }
                    }
                    break;

                case 'ADD_POINTS':
                    if ($customerId && (int)($params['points'] ?? 0) !== 0) {
                        (new LoyaltyService())->adjust($customerId, (int)$params['points'], 'ADJUST', 'اتوماسیون: ' . ($params['reason'] ?? ''), 'automation', $automationId);
                        $count++;
                    }
                    break;

                case 'CREATE_TASK':
                    $db->insert('tickets', [
                        'ticket_number' => 'TK-' . date('ymd') . '-' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT),
                        'customer_id'   => $customerId,
                        'subject'       => (string)($params['subject'] ?? 'وظیفه خودکار'),
                        'category'      => 'AUTOMATION',
                        'priority'      => (string)($params['priority'] ?? 'NORMAL'),
                        'status'        => 'OPEN',
                    ]);
                    $count++;
                    break;

                case 'NOTIFY_ADMIN':
                    NotificationService::notifyByPermission(
                        'reports.view',
                        (string)($params['title'] ?? 'اعلان اتوماسیون'),
                        (string)($params['body'] ?? ''),
                        'INFO'
                    );
                    $count++;
                    break;
            }
        }
        return $count;
    }

    private static function resolveBody(array $action, array $params, array $vars): ?string
    {
        if (!empty($action['template_id'])) {
            $tpl = Database::instance()->selectOne('SELECT body FROM message_templates WHERE id = :t', ['t' => (int)$action['template_id']]);
            $body = $tpl['body'] ?? null;
        } else {
            $body = $params['message'] ?? null;
        }
        if ($body === null) {
            return null;
        }
        foreach ($vars as $k => $v) {
            if (is_scalar($v)) {
                $body = str_replace('{' . $k . '}', (string)$v, (string)$body);
            }
        }
        return (string)$body;
    }

    /* ------------------------------ CRUD ------------------------------ */

    public static function all(): array
    {
        return Database::instance()->select(
            'SELECT a.*, (SELECT COUNT(*) FROM automation_actions x WHERE x.automation_id = a.id) AS actions_count,
                    (SELECT COUNT(*) FROM automation_rules r WHERE r.automation_id = a.id) AS rules_count
             FROM automations a ORDER BY a.id DESC'
        );
    }

    public static function find(int $id): ?array
    {
        $a = Database::instance()->selectOne('SELECT * FROM automations WHERE id = :i', ['i' => $id]);
        if ($a === null) {
            return null;
        }
        $a['rules']   = Database::instance()->select('SELECT * FROM automation_rules WHERE automation_id = :a ORDER BY id', ['a' => $id]);
        $a['actions'] = Database::instance()->select('SELECT * FROM automation_actions WHERE automation_id = :a ORDER BY sort_order, id', ['a' => $id]);
        return $a;
    }

    public static function logs(int $limit = 50): array
    {
        return Database::instance()->select(
            'SELECT l.*, a.name FROM automation_logs l JOIN automations a ON a.id = l.automation_id
             ORDER BY l.id DESC LIMIT ' . $limit
        );
    }
}
