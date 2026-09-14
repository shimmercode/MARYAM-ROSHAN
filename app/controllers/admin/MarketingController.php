<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\AutomationService;
use App\Services\NotificationService;
use App\Validators\Validator;

final class MarketingController extends BaseController
{
    /* ==================================================== campaigns ==== */

    public function campaigns(Request $request): Response
    {
        $this->authorize('marketing.view');
        $db = Database::instance();

        return $this->view('admin/marketing/campaigns', [
            'title'     => 'کمپین‌های پیامکی',
            'campaigns' => $db->select(
                "SELECT c.id, c.name, c.channel, c.message, c.status, c.sent_count, c.failed_count,
                        c.scheduled_at, c.created_at, s.name AS segment_name,
                        (SELECT COUNT(*) FROM campaign_audiences WHERE campaign_id = c.id) AS audience_count
                 FROM campaigns c
                 LEFT JOIN customer_segments s ON s.id = c.segment_id
                 ORDER BY c.id DESC LIMIT 100"
            ),
            'segments'  => $this->segments(),
            'stats'     => [
                'total'  => (int)$db->scalar('SELECT COUNT(*) FROM campaigns'),
                'sent'   => (int)$db->scalar('SELECT COALESCE(SUM(sent_count),0) FROM campaigns'),
                'failed' => (int)$db->scalar('SELECT COALESCE(SUM(failed_count),0) FROM campaigns'),
                'optout' => (int)$db->scalar('SELECT COUNT(*) FROM customers WHERE marketing_opt_in = 0 AND deleted_at IS NULL'),
            ],
        ]);
    }

    public function storeCampaign(Request $request): Response
    {
        $this->authorize('marketing.manage');
        $data = Validator::validate($request->all(), [
            'name'         => 'required|string|max:150',
            'channel'      => 'required|in:SMS,EMAIL,INAPP',
            'message'      => 'required|string|max:600',
            'segment'      => 'required|string|max:40',
            'scheduled_at' => 'nullable|string|max:20',
        ], ['name' => 'نام کمپین', 'message' => 'متن پیام', 'segment' => 'مخاطبان']);

        $db  = Database::instance();
        $seg = (string)$data['segment'];

        $id = $db->transaction(function () use ($db, $data, $seg) {
            $campaignId = $db->insert('campaigns', [
                'name'         => $data['name'],
                'channel'      => $data['channel'],
                'message'      => $data['message'],
                'segment_id'   => ctype_digit($seg) ? (int)$seg : null,
                'scheduled_at' => $data['scheduled_at'] ?: null,
                'status'       => $data['scheduled_at'] ? 'SCHEDULED' : 'DRAFT',
                'created_by'   => $this->userId(),
            ]);

            foreach ($this->audienceFor($seg) as $customer) {
                $db->insert('campaign_audiences', [
                    'campaign_id' => $campaignId,
                    'customer_id' => (int)$customer['id'],
                ]);
            }
            return $campaignId;
        });

        AuditService::log('campaign_created', 'campaigns', $id, null, ['name' => $data['name']]);
        return $this->redirect('/admin/marketing/campaigns', 'success', 'کمپین ساخته شد. برای ارسال روی «ارسال» بزنید.');
    }

    /**
     * Sends a campaign through the configured SMS provider. Every recipient gets
     * a campaign_messages row so failures are visible and retryable.
     */
    public function sendCampaign(Request $request, string $id): Response
    {
        $this->authorize('marketing.manage');
        $campaignId = (int)$id;
        $db         = Database::instance();

        $campaign = $db->selectOne('SELECT id, name, channel, message, status FROM campaigns WHERE id = :id', ['id' => $campaignId]);
        if ($campaign === null) {
            $this->notFound('کمپین یافت نشد.');
        }
        if (in_array($campaign['status'], ['COMPLETED', 'CANCELLED'], true)) {
            return $this->back('error', 'این کمپین قبلاً ارسال یا لغو شده است.');
        }

        $db->update('campaigns', ['status' => 'RUNNING'], 'id = :id', ['id' => $campaignId]);

        $recipients = $db->select(
            "SELECT a.id AS audience_id, c.id AS customer_id, c.first_name, c.last_name, c.mobile, c.email, c.marketing_opt_in
             FROM campaign_audiences a JOIN customers c ON c.id = a.customer_id
             WHERE a.campaign_id = :c AND a.status = 'PENDING' LIMIT 500",
            ['c' => $campaignId]
        );

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($recipients as $r) {
            if ((int)$r['marketing_opt_in'] !== 1) {
                $db->update('campaign_audiences', ['status' => 'SKIPPED'], 'id = :id', ['id' => (int)$r['audience_id']]);
                $skipped++;
                continue;
            }
            $body = strtr((string)$campaign['message'], [
                '{name}'  => $r['first_name'] . ' ' . $r['last_name'],
                '{first}' => (string)$r['first_name'],
            ]);
            $recipient = $campaign['channel'] === 'EMAIL' ? (string)($r['email'] ?? '') : (string)$r['mobile'];

            if ($recipient === '') {
                $db->update('campaign_audiences', ['status' => 'SKIPPED'], 'id = :id', ['id' => (int)$r['audience_id']]);
                $skipped++;
                continue;
            }

            $result = match ($campaign['channel']) {
                'EMAIL' => NotificationService::sendEmail($recipient, (string)$campaign['name'], $body, (int)$r['customer_id']),
                'INAPP' => ['success' => (bool)NotificationService::notifyCustomer((int)$r['customer_id'], (string)$campaign['name'], $body), 'error' => null],
                default => NotificationService::sendSms($recipient, $body, (int)$r['customer_id'], 'campaign', $campaignId),
            };

            $ok = (bool)($result['success'] ?? false);
            $db->insert('campaign_messages', [
                'campaign_id' => $campaignId,
                'customer_id' => (int)$r['customer_id'],
                'channel'     => $campaign['channel'],
                'recipient'   => $recipient,
                'body'        => $body,
                'status'      => $ok ? 'SENT' : 'FAILED',
                'error'       => $ok ? null : mb_substr((string)($result['error'] ?? 'خطای نامشخص'), 0, 255),
                'sent_at'     => $ok ? date('Y-m-d H:i:s') : null,
            ]);
            $db->update('campaign_audiences', ['status' => $ok ? 'SENT' : 'FAILED'], 'id = :id', ['id' => (int)$r['audience_id']]);
            $ok ? $sent++ : $failed++;
        }

        $db->execute(
            "UPDATE campaigns SET sent_count = sent_count + :s, failed_count = failed_count + :f, status = 'COMPLETED' WHERE id = :id",
            ['s' => $sent, 'f' => $failed, 'id' => $campaignId]
        );
        AuditService::log('campaign_sent', 'campaigns', $campaignId, null, ['sent' => $sent, 'failed' => $failed]);

        return $this->back(
            $failed > 0 ? 'warning' : 'success',
            sprintf('ارسال شد: %d موفق، %d ناموفق، %d رد شده.', $sent, $failed, $skipped)
        );
    }

    /* ================================================== automations ==== */

    public function automations(Request $request): Response
    {
        $this->authorize('marketing.view');
        return $this->view('admin/marketing/automations', [
            'title'       => 'اتوماسیون بازاریابی',
            'automations' => AutomationService::all(),
            'logs'        => AutomationService::logs(40),
            'triggers'    => AutomationService::TRIGGERS,
            'templates'   => Database::instance()->select("SELECT id, code, name FROM message_templates WHERE status='ACTIVE' ORDER BY name"),
        ]);
    }

    public function storeAutomation(Request $request): Response
    {
        $this->authorize('marketing.manage');
        $data = Validator::validate($request->all(), [
            'name'          => 'required|string|max:150',
            'trigger_event' => 'required|string|max:60',
            'description'   => 'nullable|string|max:255',
            'action_type'   => 'required|in:SEND_SMS,SEND_EMAIL,ADD_TAG,REMOVE_TAG,ADD_POINTS,CREATE_TASK,NOTIFY_ADMIN',
            'template_id'   => 'nullable|int',
            'delay_minutes' => 'nullable|int|min:0',
        ], ['name' => 'نام قانون', 'trigger_event' => 'رویداد', 'action_type' => 'اقدام']);

        if (!array_key_exists($data['trigger_event'], AutomationService::TRIGGERS)) {
            return $this->back('error', 'رویداد انتخابی معتبر نیست.');
        }

        $db = Database::instance();
        $id = $db->transaction(function () use ($db, $data, $request) {
            $autoId = $db->insert('automations', [
                'name'          => $data['name'],
                'trigger_event' => $data['trigger_event'],
                'description'   => $data['description'] ?? null,
                'status'        => 'ACTIVE',
            ]);

            // Optional single condition (field/operator/value).
            $field = $request->str('condition_field');
            if ($field !== '') {
                $db->insert('automation_rules', [
                    'automation_id' => $autoId,
                    'field'         => $field,
                    'operator'      => $request->str('condition_operator', 'EQ'),
                    'value'         => $request->str('condition_value'),
                ]);
            }

            $db->insert('automation_actions', [
                'automation_id' => $autoId,
                'action_type'   => $data['action_type'],
                'template_id'   => $data['template_id'] ?: null,
                'params'        => json_encode([
                    'message' => $request->str('action_message'),
                    'points'  => $request->int('action_points'),
                    'tag'     => $request->str('action_tag'),
                ], JSON_UNESCAPED_UNICODE),
                'delay_minutes' => (int)($data['delay_minutes'] ?? 0),
            ]);
            return $autoId;
        });

        AuditService::log('automation_created', 'automations', $id, null, ['name' => $data['name']]);
        return $this->redirect('/admin/marketing/automations', 'success', 'قانون اتوماسیون ایجاد شد.');
    }

    public function toggleAutomation(Request $request, string $id): Response
    {
        $this->authorize('marketing.manage');
        $db   = Database::instance();
        $auto = $db->selectOne('SELECT id, status FROM automations WHERE id = :id', ['id' => (int)$id]);
        if ($auto === null) {
            $this->notFound('قانون یافت نشد.');
        }
        $new = $auto['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
        $db->update('automations', ['status' => $new], 'id = :id', ['id' => (int)$id]);
        AuditService::log('automation_toggled', 'automations', (int)$id, $auto, ['status' => $new]);

        if ($request->wantsJson()) {
            return $this->json(['status' => $new]);
        }
        return $this->back('success', $new === 'ACTIVE' ? 'قانون فعال شد.' : 'قانون غیرفعال شد.');
    }

    /* ==================================================== discounts ==== */

    public function discounts(Request $request): Response
    {
        $this->authorize('marketing.view');
        $db = Database::instance();

        return $this->view('admin/marketing/discounts', [
            'title'     => 'تخفیف‌ها و کدها',
            'discounts' => $db->select(
                'SELECT d.id, d.name, d.type, d.value, d.max_amount, d.min_order, d.scope,
                        d.starts_at, d.ends_at, d.usage_limit, d.used_count, d.status,
                        (SELECT GROUP_CONCAT(code) FROM discount_codes WHERE discount_id = d.id) AS codes
                 FROM discounts d ORDER BY d.id DESC LIMIT 100'
            ),
            'services'  => $db->select("SELECT id, name FROM services WHERE deleted_at IS NULL AND status='ACTIVE' ORDER BY name"),
            'tiers'     => $db->select('SELECT id, name FROM loyalty_tiers ORDER BY sort_order'),
        ]);
    }

    public function storeDiscount(Request $request): Response
    {
        $this->authorize('marketing.manage');
        $data = Validator::validate($request->all(), [
            'name'        => 'required|string|max:120',
            'type'        => 'required|in:PERCENT,FIXED',
            'value'       => 'required|numeric|min:0',
            'max_amount'  => 'nullable|numeric|min:0',
            'min_order'   => 'nullable|numeric|min:0',
            'scope'       => 'required|in:ALL,SERVICE,CATEGORY,CUSTOMER,TIER',
            'scope_id'    => 'nullable|int',
            'starts_at'   => 'nullable|string|max:20',
            'ends_at'     => 'nullable|string|max:20',
            'usage_limit' => 'nullable|int|min:0',
            'code'        => 'nullable|string|max:30',
        ], ['name' => 'عنوان تخفیف', 'value' => 'مقدار', 'code' => 'کد تخفیف']);

        if ($data['type'] === 'PERCENT' && (float)$data['value'] > 100) {
            return $this->back('error', 'درصد تخفیف نمی‌تواند بیشتر از ۱۰۰ باشد.');
        }

        $db = Database::instance();
        $id = $db->transaction(function () use ($db, $data) {
            $discountId = $db->insert('discounts', [
                'name'        => $data['name'],
                'type'        => $data['type'],
                'value'       => number_format((float)$data['value'], 2, '.', ''),
                'max_amount'  => isset($data['max_amount']) && $data['max_amount'] !== null && $data['max_amount'] !== ''
                    ? number_format((float)$data['max_amount'], 2, '.', '') : null,
                'min_order'   => number_format((float)($data['min_order'] ?? 0), 2, '.', ''),
                'scope'       => $data['scope'],
                'scope_id'    => $data['scope_id'] ?: null,
                'starts_at'   => $data['starts_at'] ? $data['starts_at'] . ' 00:00:00' : null,
                'ends_at'     => $data['ends_at'] ? $data['ends_at'] . ' 23:59:59' : null,
                'usage_limit' => $data['usage_limit'] ?: null,
                'status'      => 'ACTIVE',
            ]);
            if (!empty($data['code'])) {
                $db->insert('discount_codes', [
                    'discount_id' => $discountId,
                    'code'        => mb_strtoupper((string)$data['code']),
                    'usage_limit' => $data['usage_limit'] ?: null,
                    'status'      => 'ACTIVE',
                ]);
            }
            return $discountId;
        });

        AuditService::log('discount_created', 'discounts', $id, null, ['name' => $data['name']]);
        return $this->redirect('/admin/marketing/discounts', 'success', 'تخفیف ثبت شد.');
    }

    /* ====================================================== helpers ==== */

    /** Named audiences plus dynamic segments stored in customer_segments. */
    private function segments(): array
    {
        $named = [
            ['key' => 'all',      'name' => 'همه مشتریان فعال'],
            ['key' => 'vip',      'name' => 'مشتریان VIP و طلایی'],
            ['key' => 'inactive', 'name' => 'مشتریان غیرفعال (بیش از ۹۰ روز)'],
            ['key' => 'birthday', 'name' => 'متولدین این ماه'],
            ['key' => 'new',      'name' => 'مشتریان جدید (۳۰ روز اخیر)'],
        ];
        foreach (Database::instance()->select('SELECT id, name FROM customer_segments ORDER BY name') as $s) {
            $named[] = ['key' => (string)$s['id'], 'name' => $s['name']];
        }
        return $named;
    }

    /**
     * Resolves a stored segment. Supported rule keys: tier (slug), min_visits,
     * min_spent, inactive_days, source. Unknown keys are ignored on purpose so
     * a malformed rule never sends to the wrong people.
     */
    private function audienceFromRules(int $segmentId): array
    {
        $db    = Database::instance();
        $rules = json_decode((string)$db->scalar('SELECT rules FROM customer_segments WHERE id = :s', ['s' => $segmentId]) ?: '[]', true);
        $rules = is_array($rules) ? $rules : [];

        $where  = "deleted_at IS NULL AND status = 'ACTIVE' AND marketing_opt_in = 1";
        $params = [];
        if (!empty($rules['tier'])) {
            $where .= ' AND loyalty_tier_id = (SELECT id FROM loyalty_tiers WHERE slug = :tier)';
            $params['tier'] = (string)$rules['tier'];
        }
        if (isset($rules['min_visits'])) {
            $where .= ' AND visits_count >= :mv';
            $params['mv'] = (int)$rules['min_visits'];
        }
        if (isset($rules['min_spent'])) {
            $where .= ' AND total_spent >= :ms';
            $params['ms'] = (float)$rules['min_spent'];
        }
        if (isset($rules['inactive_days'])) {
            $where .= ' AND (last_visit_at IS NULL OR last_visit_at < DATE_SUB(CURDATE(), INTERVAL :days DAY))';
            $params['days'] = (int)$rules['inactive_days'];
        }
        if (!empty($rules['source'])) {
            $where .= ' AND source = :src';
            $params['src'] = (string)$rules['source'];
        }

        return $db->select("SELECT id FROM customers WHERE {$where} LIMIT 5000", $params);
    }

    private function audienceFor(string $segment): array
    {
        $db   = Database::instance();
        $base = "FROM customers WHERE deleted_at IS NULL AND status = 'ACTIVE' AND marketing_opt_in = 1";

        return match ($segment) {
            'vip'      => $db->select(
                "SELECT id {$base} AND loyalty_tier_id IN (SELECT id FROM loyalty_tiers WHERE slug IN ('gold','vip')) LIMIT 5000"
            ),
            'inactive' => $db->select(
                "SELECT id {$base} AND (last_visit_at IS NULL OR last_visit_at < DATE_SUB(CURDATE(), INTERVAL 90 DAY)) LIMIT 5000"
            ),
            'birthday' => $db->select("SELECT id {$base} AND MONTH(birth_date) = MONTH(CURDATE()) LIMIT 5000"),
            'new'      => $db->select("SELECT id {$base} AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) LIMIT 5000"),
            'all'      => $db->select("SELECT id {$base} LIMIT 5000"),
            // Stored segments keep their filter in customer_segments.rules.
            default    => ctype_digit($segment)
                ? $this->audienceFromRules((int)$segment)
                : [],
        };
    }
}
