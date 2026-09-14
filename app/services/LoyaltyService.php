<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\BusinessException;

/**
 * Points, tiers, redemption and referrals.
 * Thresholds and rates are configurable through settings / loyalty_tiers.
 */
final class LoyaltyService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /** How many Toman equal one point when earning. */
    public function earnRate(): float
    {
        return max(1.0, (float)SettingsService::get('loyalty_earn_rate', 10000));
    }

    /** Toman value of one point when redeeming. */
    public function pointValue(): float
    {
        return max(0.0, (float)SettingsService::get('loyalty_point_value', 1000));
    }

    public function tiers(): array
    {
        return $this->db->select('SELECT * FROM loyalty_tiers ORDER BY min_points');
    }

    public function balance(int $customerId): int
    {
        return (int)($this->db->scalar('SELECT points_balance FROM customer_loyalty WHERE customer_id = :c', ['c' => $customerId]) ?? 0);
    }

    private function account(int $customerId): array
    {
        $acc = $this->db->selectOne('SELECT * FROM customer_loyalty WHERE customer_id = :c' . $this->db->forUpdate(), ['c' => $customerId]);
        if ($acc === null) {
            $id  = $this->db->insert('customer_loyalty', ['customer_id' => $customerId, 'points_balance' => 0]);
            $acc = $this->db->selectOne('SELECT * FROM customer_loyalty WHERE id = :i', ['i' => $id]) ?? [];
        }
        return $acc;
    }

    /**
     * Add or subtract points and keep the ledger + tier in sync.
     * @param int $points positive to credit, negative to debit
     */
    public function adjust(int $customerId, int $points, string $type, ?string $description = null, ?string $refType = null, ?int $refId = null): int
    {
        if ($points === 0) {
            return $this->balance($customerId);
        }
        return $this->db->transaction(function () use ($customerId, $points, $type, $description, $refType, $refId): int {
            $acc     = $this->account($customerId);
            $balance = (int)$acc['points_balance'] + $points;
            if ($balance < 0) {
                throw new BusinessException('امتیاز کافی برای این عملیات وجود ندارد.', 'INSUFFICIENT_POINTS', 422);
            }

            $this->db->update('customer_loyalty', [
                'points_balance'  => $balance,
                'points_earned'   => (int)$acc['points_earned'] + max(0, $points),
                'points_redeemed' => (int)$acc['points_redeemed'] + max(0, -$points),
            ], 'id = :id', ['id' => (int)$acc['id']]);

            $this->db->insert('loyalty_transactions', [
                'customer_id'    => $customerId,
                'type'           => $type,
                'points'         => $points,
                'balance_after'  => $balance,
                'reference_type' => $refType,
                'reference_id'   => $refId,
                'description'    => $description,
                'created_by'     => AuthService::id(),
            ]);

            $this->db->update('customers', ['loyalty_points' => $balance], 'id = :c', ['c' => $customerId]);
            $this->syncTier($customerId, $balance);
            return $balance;
        });
    }

    public function earnFromInvoice(int $customerId, float $amount, int $invoiceId): int
    {
        $tier       = $this->currentTier($customerId);
        $multiplier = $tier !== null ? (float)$tier['point_multiplier'] : 1.0;
        $points     = (int)floor($amount / $this->earnRate() * $multiplier);
        if ($points <= 0) {
            return $this->balance($customerId);
        }
        return $this->adjust($customerId, $points, 'EARN', 'امتیاز خرید فاکتور', 'invoice', $invoiceId);
    }

    /** @return array{points:int, amount:float} */
    public function calculateRedemption(int $customerId, int $requestedPoints, float $invoiceTotal): array
    {
        $balance = $this->balance($customerId);
        $points  = max(0, min($requestedPoints, $balance));
        $maxPct  = (float)SettingsService::get('loyalty_max_redeem_percent', 50);
        $maxAmt  = $invoiceTotal * $maxPct / 100;
        $amount  = min($points * $this->pointValue(), $maxAmt);
        $points  = $this->pointValue() > 0 ? (int)ceil($amount / $this->pointValue()) : 0;
        return ['points' => $points, 'amount' => round($amount, 2)];
    }

    public function redeem(int $customerId, int $points, int $invoiceId): float
    {
        if ($points <= 0) {
            return 0.0;
        }
        $this->adjust($customerId, -$points, 'REDEEM', 'استفاده از امتیاز در فاکتور', 'invoice', $invoiceId);
        return round($points * $this->pointValue(), 2);
    }

    public function currentTier(int $customerId): ?array
    {
        return $this->db->selectOne(
            'SELECT lt.* FROM loyalty_tiers lt
             JOIN customers c ON c.loyalty_tier_id = lt.id WHERE c.id = :c',
            ['c' => $customerId]
        );
    }

    public function syncTier(int $customerId, ?int $balance = null): ?array
    {
        $balance ??= $this->balance($customerId);
        $tier = $this->db->selectOne(
            'SELECT * FROM loyalty_tiers WHERE min_points <= :p ORDER BY min_points DESC LIMIT 1',
            ['p' => $balance]
        );
        if ($tier === null) {
            return null;
        }
        $currentTierId = (int)($this->db->scalar('SELECT loyalty_tier_id FROM customers WHERE id = :c', ['c' => $customerId]) ?? 0);
        if ($currentTierId !== (int)$tier['id']) {
            $this->db->update('customers', ['loyalty_tier_id' => (int)$tier['id']], 'id = :c', ['c' => $customerId]);
            $this->db->execute(
                'UPDATE customer_loyalty SET tier_id = :t, tier_changed_at = NOW() WHERE customer_id = :c',
                ['t' => (int)$tier['id'], 'c' => $customerId]
            );
            AutomationService::fire('loyalty_tier_changed', [
                'customer_id' => $customerId,
                'tier'        => $tier['slug'],
                'tier_name'   => $tier['name'],
            ]);
            NotificationService::notifyCustomer(
                $customerId,
                'ارتقای سطح باشگاه مشتریان',
                'سطح وفاداری شما به «' . $tier['name'] . '» ارتقا یافت.',
                'SUCCESS'
            );
        }
        return $tier;
    }

    public function birthdayReward(int $customerId): int
    {
        $points = (int)SettingsService::get('loyalty_birthday_points', 100);
        if ($points <= 0) {
            return $this->balance($customerId);
        }
        $already = (int)$this->db->scalar(
            "SELECT COUNT(*) FROM loyalty_transactions
             WHERE customer_id = :c AND type = 'BIRTHDAY' AND YEAR(created_at) = YEAR(CURDATE())",
            ['c' => $customerId]
        );
        if ($already > 0) {
            return $this->balance($customerId);
        }
        return $this->adjust($customerId, $points, 'BIRTHDAY', 'هدیه تولد');
    }

    /* ----------------------------- referrals ---------------------------- */

    public function createReferralCode(int $customerId): string
    {
        $existing = $this->db->scalar("SELECT code FROM referrals WHERE referrer_id = :c AND status = 'PENDING' LIMIT 1", ['c' => $customerId]);
        if ($existing) {
            return (string)$existing;
        }
        do {
            $code   = 'REF' . strtoupper(bin2hex(random_bytes(3)));
            $exists = $this->db->scalar('SELECT id FROM referrals WHERE code = :c', ['c' => $code]);
        } while ($exists);

        $this->db->insert('referrals', [
            'referrer_id'   => $customerId,
            'code'          => $code,
            'status'        => 'PENDING',
            'reward_points' => (int)SettingsService::get('loyalty_referral_points', 200),
        ]);
        return $code;
    }

    public function completeReferral(string $code, int $referredCustomerId): bool
    {
        return (bool)$this->db->transaction(function () use ($code, $referredCustomerId): bool {
            $ref = $this->db->selectOne("SELECT * FROM referrals WHERE code = :c AND status = 'PENDING'", ['c' => $code]);
            if ($ref === null || (int)$ref['referrer_id'] === $referredCustomerId) {
                return false;
            }
            $this->db->update('referrals', [
                'referred_id'  => $referredCustomerId,
                'status'       => 'COMPLETED',
                'completed_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => (int)$ref['id']]);

            $points = (int)$ref['reward_points'];
            $this->adjust((int)$ref['referrer_id'], $points, 'REFERRAL', 'پاداش معرفی مشتری جدید', 'referral', (int)$ref['id']);
            $this->adjust($referredCustomerId, (int)round($points / 2), 'REFERRAL', 'هدیه ثبت‌نام با کد معرف', 'referral', (int)$ref['id']);
            return true;
        });
    }

    public function history(int $customerId, int $limit = 50): array
    {
        return $this->db->select(
            'SELECT id, type, points, balance_after, description, created_at
             FROM loyalty_transactions WHERE customer_id = :c ORDER BY id DESC LIMIT ' . $limit,
            ['c' => $customerId]
        );
    }

    public function stats(): array
    {
        $rows = $this->db->select(
            'SELECT lt.id, lt.name, lt.color, lt.min_points, COUNT(c.id) AS customers
             FROM loyalty_tiers lt LEFT JOIN customers c ON c.loyalty_tier_id = lt.id AND c.deleted_at IS NULL
             GROUP BY lt.id, lt.name, lt.color, lt.min_points ORDER BY lt.min_points'
        );
        return [
            'tiers'           => $rows,
            'total_points'    => (int)$this->db->scalar('SELECT COALESCE(SUM(points_balance),0) FROM customer_loyalty'),
            'points_redeemed' => (int)$this->db->scalar('SELECT COALESCE(SUM(points_redeemed),0) FROM customer_loyalty'),
            'members'         => (int)$this->db->scalar('SELECT COUNT(*) FROM customer_loyalty WHERE points_balance > 0'),
        ];
    }
}
