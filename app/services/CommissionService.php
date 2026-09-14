<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Configurable commission engine.
 *
 * Rule resolution order (most specific wins, then lowest priority number):
 *   STAFF > SERVICE > CATEGORY > BRANCH > GLOBAL
 *
 * Calculated amounts are STORED on the commissions row together with the
 * calc_type/calc_value actually used, so later rule changes never rewrite
 * historical commission.
 */
final class CommissionService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /** @return array{amount:float, rule_id:?int, calc_type:string, calc_value:float} */
    public function calculate(int $staffId, ?int $serviceId, ?int $branchId, float $baseAmount): array
    {
        $rule = $this->resolveRule($staffId, $serviceId, $branchId, $baseAmount);

        if ($rule === null) {
            // Fall back to per-service, then per-staff default percentages.
            if ($serviceId !== null) {
                $svc = $this->db->selectOne('SELECT commission_type, commission_value FROM services WHERE id = :s', ['s' => $serviceId]);
                if ($svc !== null && $svc['commission_type'] !== 'NONE' && (float)$svc['commission_value'] > 0) {
                    $type  = (string)$svc['commission_type'];
                    $value = (float)$svc['commission_value'];
                    return [
                        'amount'     => $this->apply($type, $value, $baseAmount),
                        'rule_id'    => null,
                        'calc_type'  => $type,
                        'calc_value' => $value,
                    ];
                }
                $custom = $this->db->scalar(
                    'SELECT commission_percent FROM staff_services WHERE staff_id = :st AND service_id = :sv',
                    ['st' => $staffId, 'sv' => $serviceId]
                );
                if ($custom !== null && (float)$custom > 0) {
                    return ['amount' => $this->apply('PERCENT', (float)$custom, $baseAmount), 'rule_id' => null, 'calc_type' => 'PERCENT', 'calc_value' => (float)$custom];
                }
            }
            $pct = (float)($this->db->scalar('SELECT commission_percent FROM staff WHERE id = :s', ['s' => $staffId]) ?? 0);
            return ['amount' => $this->apply('PERCENT', $pct, $baseAmount), 'rule_id' => null, 'calc_type' => 'PERCENT', 'calc_value' => $pct];
        }

        $type  = (string)$rule['calc_type'];
        $value = (float)$rule['value'];
        if ($type === 'TIERED') {
            $value = $this->tierValue($rule, $baseAmount);
            return ['amount' => $this->apply('PERCENT', $value, $baseAmount), 'rule_id' => (int)$rule['id'], 'calc_type' => 'TIERED', 'calc_value' => $value];
        }
        return ['amount' => $this->apply($type, $value, $baseAmount), 'rule_id' => (int)$rule['id'], 'calc_type' => $type, 'calc_value' => $value];
    }

    private function apply(string $type, float $value, float $base): float
    {
        return round($type === 'FIXED' ? $value : $base * $value / 100, 2);
    }

    private function tierValue(array $rule, float $base): float
    {
        $tiers = json_decode((string)($rule['tiers'] ?? '[]'), true);
        if (!is_array($tiers)) {
            return (float)$rule['value'];
        }
        $selected = (float)$rule['value'];
        usort($tiers, static fn ($a, $b) => ($a['from'] ?? 0) <=> ($b['from'] ?? 0));
        foreach ($tiers as $t) {
            if ($base >= (float)($t['from'] ?? 0)) {
                $selected = (float)($t['percent'] ?? $selected);
            }
        }
        return $selected;
    }

    private function resolveRule(int $staffId, ?int $serviceId, ?int $branchId, float $baseAmount): ?array
    {
        $categoryId = null;
        if ($serviceId !== null) {
            $categoryId = $this->db->scalar('SELECT category_id FROM services WHERE id = :s', ['s' => $serviceId]);
        }

        $candidates = [
            ['STAFF', $staffId],
            ['SERVICE', $serviceId],
            ['CATEGORY', $categoryId !== null ? (int)$categoryId : null],
            ['BRANCH', $branchId],
            ['GLOBAL', null],
        ];

        foreach ($candidates as [$scope, $scopeId]) {
            if ($scope !== 'GLOBAL' && $scopeId === null) {
                continue;
            }
            $sql = "SELECT * FROM commission_rules
                    WHERE status = 'ACTIVE' AND scope = :scope
                      AND (valid_from IS NULL OR valid_from <= CURDATE())
                      AND (valid_to   IS NULL OR valid_to   >= CURDATE())";
            $params = ['scope' => $scope];
            if ($scope === 'GLOBAL') {
                $sql .= ' AND scope_id IS NULL';
            } else {
                $sql .= ' AND scope_id = :sid';
                $params['sid'] = $scopeId;
            }
            $rule = $this->db->selectOne($sql . ' ORDER BY priority ASC, id DESC LIMIT 1', $params);
            if ($rule !== null) {
                return $rule;
            }
        }
        return null;
    }

    /** Record commissions for all service lines of an invoice. */
    public function recordForInvoice(int $invoiceId): float
    {
        return (float)$this->db->transaction(function () use ($invoiceId): float {
            $invoice = $this->db->selectOne('SELECT id, branch_id, issue_date FROM invoices WHERE id = :i', ['i' => $invoiceId]);
            if ($invoice === null) {
                return 0.0;
            }
            $this->db->delete('commissions', "invoice_id = :i AND status = 'PENDING'", ['i' => $invoiceId]);

            $items = $this->db->select(
                "SELECT id, service_id, staff_id, total FROM invoice_items
                 WHERE invoice_id = :i AND item_type = 'SERVICE' AND staff_id IS NOT NULL",
                ['i' => $invoiceId]
            );
            $sum = 0.0;
            foreach ($items as $item) {
                $calc = $this->calculate(
                    (int)$item['staff_id'],
                    $item['service_id'] !== null ? (int)$item['service_id'] : null,
                    (int)$invoice['branch_id'],
                    (float)$item['total']
                );
                if ($calc['amount'] <= 0) {
                    continue;
                }
                $this->db->insert('commissions', [
                    'staff_id'        => (int)$item['staff_id'],
                    'invoice_id'      => $invoiceId,
                    'invoice_item_id' => (int)$item['id'],
                    'service_id'      => $item['service_id'],
                    'rule_id'         => $calc['rule_id'],
                    'base_amount'     => number_format((float)$item['total'], 2, '.', ''),
                    'calc_type'       => $calc['calc_type'],
                    'calc_value'      => number_format($calc['calc_value'], 2, '.', ''),
                    'amount'          => number_format($calc['amount'], 2, '.', ''),
                    'status'          => 'PENDING',
                    'period'          => substr((string)$invoice['issue_date'], 0, 7),
                ]);
                $sum += $calc['amount'];
            }
            return round($sum, 2);
        });
    }

    /** Cancel commissions when an invoice is cancelled/refunded. */
    public function cancelForInvoice(int $invoiceId): int
    {
        return $this->db->execute(
            "UPDATE commissions SET status = 'CANCELLED' WHERE invoice_id = :i AND status IN ('PENDING','APPROVED')",
            ['i' => $invoiceId]
        );
    }

    public function summary(int $staffId, string $period): array
    {
        $row = $this->db->selectOne(
            "SELECT COALESCE(SUM(amount),0) AS total,
                    COALESCE(SUM(CASE WHEN status = 'PAID' THEN amount ELSE 0 END),0) AS paid,
                    COALESCE(SUM(CASE WHEN status IN ('PENDING','APPROVED') THEN amount ELSE 0 END),0) AS pending,
                    COUNT(*) AS items
             FROM commissions WHERE staff_id = :s AND period = :p AND status <> 'CANCELLED'",
            ['s' => $staffId, 'p' => $period]
        );
        return $row ?? ['total' => 0, 'paid' => 0, 'pending' => 0, 'items' => 0];
    }

    public function listFor(int $staffId, string $period, int $limit = 100): array
    {
        return $this->db->select(
            'SELECT c.id, c.base_amount, c.calc_type, c.calc_value, c.amount, c.status, c.created_at,
                    sv.name AS service_name, i.invoice_number
             FROM commissions c
             LEFT JOIN services sv ON sv.id = c.service_id
             LEFT JOIN invoices i  ON i.id = c.invoice_id
             WHERE c.staff_id = :s AND c.period = :p
             ORDER BY c.id DESC LIMIT ' . $limit,
            ['s' => $staffId, 'p' => $period]
        );
    }

    public function periodReport(string $period, ?int $branchId = null): array
    {
        $sql = "SELECT s.id, s.first_name, s.last_name, s.code, b.name AS branch_name,
                       COALESCE(SUM(c.amount),0) AS commission,
                       COALESCE(SUM(c.base_amount),0) AS sales,
                       COUNT(c.id) AS items
                FROM staff s
                LEFT JOIN commissions c ON c.staff_id = s.id AND c.period = :p AND c.status <> 'CANCELLED'
                LEFT JOIN branches b ON b.id = s.branch_id
                WHERE s.deleted_at IS NULL";
        $params = ['p' => $period];
        if ($branchId) {
            $sql .= ' AND s.branch_id = :b';
            $params['b'] = $branchId;
        }
        return $this->db->select($sql . ' GROUP BY s.id, s.first_name, s.last_name, s.code, b.name ORDER BY commission DESC', $params);
    }

    public function markPaid(array $commissionIds): int
    {
        if ($commissionIds === []) {
            return 0;
        }
        $ids = implode(',', array_map('intval', $commissionIds));
        $n   = $this->db->execute("UPDATE commissions SET status = 'PAID', paid_at = NOW() WHERE id IN ({$ids}) AND status <> 'CANCELLED'");
        AuditService::log('commissions_paid', 'commissions', null, null, ['ids' => $commissionIds]);
        return $n;
    }
}
