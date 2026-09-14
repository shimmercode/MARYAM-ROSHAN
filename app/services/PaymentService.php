<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\BusinessException;

/**
 * Payments, refunds and wallet operations. Refunds never delete payments —
 * they insert a compensating REFUND row.
 */
final class PaymentService
{
    public const METHODS = [
        'POS'      => 'کارتخوان',
        'CASH'     => 'نقد',
        'TRANSFER' => 'انتقال بانکی',
        'WALLET'   => 'کیف پول',
        'ONLINE'   => 'پرداخت آنلاین',
    ];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    public function nextNumber(string $prefix = 'PY'): string
    {
        $p   = $prefix . '-' . date('ymd') . '-';
        $max = (int)$this->db->scalar(
            'SELECT COALESCE(MAX(CAST(SUBSTRING(payment_number, 13) AS UNSIGNED)), 0) FROM payments WHERE payment_number LIKE :p',
            ['p' => $p . '%']
        );
        return $p . str_pad((string)($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Record a successful payment against an invoice (or a standalone deposit).
     * @param array{invoice_id?:?int, customer_id:int, branch_id?:?int, method:string,
     *              amount:float, reference?:?string, type?:string, note?:?string} $data
     */
    public function record(array $data): array
    {
        $amount = round((float)($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new BusinessException('مبلغ پرداخت باید بزرگ‌تر از صفر باشد.', 'INVALID_AMOUNT', 422);
        }
        $method = strtoupper((string)($data['method'] ?? 'CASH'));
        if (!array_key_exists($method, self::METHODS)) {
            throw new BusinessException('روش پرداخت نامعتبر است.', 'INVALID_METHOD', 422);
        }
        $customerId = (int)($data['customer_id'] ?? 0);
        $invoiceId  = isset($data['invoice_id']) && $data['invoice_id'] ? (int)$data['invoice_id'] : null;
        $type       = strtoupper((string)($data['type'] ?? 'PAYMENT'));

        return $this->db->transaction(function () use ($data, $amount, $method, $customerId, $invoiceId, $type): array {
            if ($invoiceId !== null) {
                $invoice = $this->db->selectOne('SELECT id, total, paid_amount, status, customer_id FROM invoices WHERE id = :i' . $this->db->forUpdate(), ['i' => $invoiceId]);
                if ($invoice === null) {
                    throw new BusinessException('فاکتور یافت نشد.', 'INVOICE_NOT_FOUND', 404);
                }
                if ($invoice['status'] === 'CANCELLED') {
                    throw new BusinessException('برای فاکتور لغوشده امکان پرداخت وجود ندارد.', 'INVOICE_CANCELLED', 422);
                }
                $remaining = round((float)$invoice['total'] - (float)$invoice['paid_amount'], 2);
                if ($amount > $remaining + 0.01) {
                    throw new BusinessException(
                        'مبلغ پرداخت از مانده فاکتور بیشتر است. مانده: ' . number_format($remaining) . ' تومان',
                        'OVERPAYMENT',
                        422
                    );
                }
            }

            // Wallet payments must debit the wallet.
            if ($method === 'WALLET') {
                $this->debitWallet($customerId, $amount, 'پرداخت فاکتور', 'invoice', $invoiceId);
            }

            $methodId = $this->db->scalar('SELECT id FROM payment_methods WHERE slug = :s', ['s' => $method]);

            $paymentId = $this->db->insert('payments', [
                'payment_number' => $this->nextNumber(),
                'invoice_id'     => $invoiceId,
                'customer_id'    => $customerId,
                'branch_id'      => $data['branch_id'] ?? null,
                'method_id'      => $methodId ? (int)$methodId : null,
                'method_slug'    => $method,
                'type'           => in_array($type, ['PAYMENT', 'REFUND', 'DEPOSIT', 'WALLET_TOPUP'], true) ? $type : 'PAYMENT',
                'amount'         => number_format($amount, 2, '.', ''),
                'status'         => 'SUCCESS',
                'reference'      => isset($data['reference']) ? mb_substr((string)$data['reference'], 0, 80) : null,
                'note'           => isset($data['note']) ? mb_substr((string)$data['note'], 0, 255) : null,
                'paid_at'        => date('Y-m-d H:i:s'),
                'created_by'     => AuthService::id(),
            ]);

            if ($invoiceId !== null) {
                (new InvoiceService($this->db))->recalculatePaymentStatus($invoiceId);
            }

            AuditService::log('payment_created', 'payments', $paymentId, null, [
                'amount' => $amount, 'method' => $method, 'invoice_id' => $invoiceId,
            ]);

            return $this->db->selectOne('SELECT * FROM payments WHERE id = :p', ['p' => $paymentId]) ?? [];
        });
    }

    /**
     * Refund: creates a compensating REFUND payment, reverses commission and
     * loyalty proportionally. Original rows are never deleted.
     */
    public function refund(int $invoiceId, float $amount, string $method = 'CASH', ?string $reason = null): array
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new BusinessException('مبلغ برگشت باید بزرگ‌تر از صفر باشد.', 'INVALID_AMOUNT', 422);
        }

        return $this->db->transaction(function () use ($invoiceId, $amount, $method, $reason): array {
            $invoice = $this->db->selectOne('SELECT * FROM invoices WHERE id = :i' . $this->db->forUpdate(), ['i' => $invoiceId]);
            if ($invoice === null) {
                throw new BusinessException('فاکتور یافت نشد.', 'INVOICE_NOT_FOUND', 404);
            }
            $refundable = round((float)$invoice['paid_amount'], 2);
            if ($amount > $refundable + 0.01) {
                throw new BusinessException(
                    'مبلغ برگشت از مبلغ پرداخت‌شده بیشتر است. حداکثر: ' . number_format($refundable) . ' تومان',
                    'REFUND_EXCEEDS_PAID',
                    422
                );
            }

            $methodId = $this->db->scalar('SELECT id FROM payment_methods WHERE slug = :s', ['s' => strtoupper($method)]);
            $paymentId = $this->db->insert('payments', [
                'payment_number' => $this->nextNumber('RF'),
                'invoice_id'     => $invoiceId,
                'customer_id'    => (int)$invoice['customer_id'],
                'branch_id'      => (int)$invoice['branch_id'],
                'method_id'      => $methodId ? (int)$methodId : null,
                'method_slug'    => strtoupper($method),
                'type'           => 'REFUND',
                'amount'         => number_format($amount, 2, '.', ''),
                'status'         => 'SUCCESS',
                'note'           => $reason !== null ? mb_substr($reason, 0, 255) : 'برگشت وجه',
                'paid_at'        => date('Y-m-d H:i:s'),
                'created_by'     => AuthService::id(),
            ]);

            if (strtoupper($method) === 'WALLET') {
                $this->creditWallet((int)$invoice['customer_id'], $amount, 'برگشت وجه فاکتور ' . $invoice['invoice_number'], 'invoice', $invoiceId);
            }

            $invoiceService = new InvoiceService($this->db);
            $invoiceService->recalculatePaymentStatus($invoiceId);

            // Proportional reversal of loyalty points earned on this invoice.
            $ratio  = (float)$invoice['total'] > 0 ? $amount / (float)$invoice['total'] : 0;
            $earned = (int)$this->db->scalar(
                "SELECT COALESCE(SUM(points),0) FROM loyalty_transactions
                 WHERE reference_type = 'invoice' AND reference_id = :i AND type = 'EARN'",
                ['i' => $invoiceId]
            );
            $reverse = (int)floor($earned * $ratio);
            if ($reverse > 0) {
                (new LoyaltyService($this->db))->adjust(
                    (int)$invoice['customer_id'],
                    -$reverse,
                    'ADJUST',
                    'کسر امتیاز به دلیل برگشت وجه',
                    'invoice',
                    $invoiceId
                );
            }

            // Full refund cancels the commission.
            $fresh = $this->db->selectOne('SELECT paid_amount FROM invoices WHERE id = :i', ['i' => $invoiceId]);
            if ($fresh !== null && (float)$fresh['paid_amount'] <= 0.01) {
                (new CommissionService($this->db))->cancelForInvoice($invoiceId);
            }

            (new \App\Repositories\CustomerRepository($this->db))->refreshAggregates((int)$invoice['customer_id']);

            AuditService::log('invoice_refunded', 'invoices', $invoiceId, null, [
                'amount' => $amount, 'method' => $method, 'reason' => $reason,
            ]);

            return $this->db->selectOne('SELECT * FROM payments WHERE id = :p', ['p' => $paymentId]) ?? [];
        });
    }

    /* ----------------------------- wallet ----------------------------- */

    public function wallet(int $customerId): array
    {
        $w = $this->db->selectOne('SELECT * FROM wallet_accounts WHERE customer_id = :c', ['c' => $customerId]);
        if ($w === null) {
            $id = $this->db->insert('wallet_accounts', ['customer_id' => $customerId, 'balance' => '0.00']);
            $w  = $this->db->selectOne('SELECT * FROM wallet_accounts WHERE id = :i', ['i' => $id]) ?? [];
        }
        return $w;
    }

    public function walletBalance(int $customerId): float
    {
        return (float)($this->db->scalar('SELECT balance FROM wallet_accounts WHERE customer_id = :c', ['c' => $customerId]) ?? 0);
    }

    public function creditWallet(int $customerId, float $amount, string $description, ?string $refType = null, ?int $refId = null): float
    {
        if ($amount <= 0) {
            throw new BusinessException('مبلغ باید بزرگ‌تر از صفر باشد.', 'INVALID_AMOUNT', 422);
        }
        return (float)$this->db->transaction(function () use ($customerId, $amount, $description, $refType, $refId): float {
            $wallet = $this->wallet($customerId);
            $new    = round((float)$wallet['balance'] + $amount, 2);
            $this->db->update('wallet_accounts', ['balance' => number_format($new, 2, '.', '')], 'id = :i', ['i' => (int)$wallet['id']]);
            $this->db->insert('wallet_transactions', [
                'wallet_id'      => (int)$wallet['id'],
                'customer_id'    => $customerId,
                'type'           => 'CREDIT',
                'amount'         => number_format($amount, 2, '.', ''),
                'balance_after'  => number_format($new, 2, '.', ''),
                'reference_type' => $refType,
                'reference_id'   => $refId,
                'description'    => $description,
                'created_by'     => AuthService::id(),
            ]);
            AuditService::log('wallet_credited', 'wallet_accounts', (int)$wallet['id'], null, ['amount' => $amount]);
            return $new;
        });
    }

    public function debitWallet(int $customerId, float $amount, string $description, ?string $refType = null, ?int $refId = null): float
    {
        if ($amount <= 0) {
            throw new BusinessException('مبلغ باید بزرگ‌تر از صفر باشد.', 'INVALID_AMOUNT', 422);
        }
        return (float)$this->db->transaction(function () use ($customerId, $amount, $description, $refType, $refId): float {
            $wallet  = $this->db->selectOne('SELECT * FROM wallet_accounts WHERE customer_id = :c' . $this->db->forUpdate(), ['c' => $customerId]);
            $wallet ??= $this->wallet($customerId);
            if ((string)$wallet['status'] === 'FROZEN') {
                throw new BusinessException('کیف پول مشتری مسدود است.', 'WALLET_FROZEN', 422);
            }
            $balance = (float)$wallet['balance'];
            if ($balance + 0.01 < $amount) {
                throw new BusinessException(
                    'موجودی کیف پول کافی نیست. موجودی فعلی: ' . number_format($balance) . ' تومان',
                    'INSUFFICIENT_WALLET_BALANCE',
                    422
                );
            }
            $new = round($balance - $amount, 2);
            $this->db->update('wallet_accounts', ['balance' => number_format($new, 2, '.', '')], 'id = :i', ['i' => (int)$wallet['id']]);
            $this->db->insert('wallet_transactions', [
                'wallet_id'      => (int)$wallet['id'],
                'customer_id'    => $customerId,
                'type'           => 'DEBIT',
                'amount'         => number_format($amount, 2, '.', ''),
                'balance_after'  => number_format($new, 2, '.', ''),
                'reference_type' => $refType,
                'reference_id'   => $refId,
                'description'    => $description,
                'created_by'     => AuthService::id(),
            ]);
            return $new;
        });
    }

    public function topUpWallet(int $customerId, float $amount, string $method = 'CASH', ?string $reference = null): array
    {
        return $this->db->transaction(function () use ($customerId, $amount, $method, $reference): array {
            $payment = $this->record([
                'customer_id' => $customerId,
                'method'      => $method,
                'amount'      => $amount,
                'type'        => 'WALLET_TOPUP',
                'reference'   => $reference,
                'note'        => 'شارژ کیف پول',
            ]);
            $balance = $this->creditWallet($customerId, $amount, 'شارژ کیف پول', 'payment', (int)$payment['id']);
            return ['payment' => $payment, 'balance' => $balance];
        });
    }

    /* ------------------------------ lists ----------------------------- */

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $where[] = "(p.payment_number LIKE :s1 OR i.invoice_number LIKE :s2 OR c.mobile LIKE :s3
                         OR CONCAT(c.first_name,' ',c.last_name) LIKE :s4)";
            $params += ['s1' => $like, 's2' => $like, 's3' => $like, 's4' => $like];
        }
        foreach ([['type', 'p.type', 'ty'], ['method', 'p.method_slug', 'me'], ['branch_id', 'p.branch_id', 'br'], ['customer_id', 'p.customer_id', 'cu']] as [$k, $col, $bind]) {
            if (!empty($filters[$k])) {
                $where[] = "{$col} = :{$bind}";
                $params[$bind] = $filters[$k];
            }
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'p.paid_at >= :df';
            $params['df'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'p.paid_at <= :dt';
            $params['dt'] = $filters['date_to'] . ' 23:59:59';
        }
        $w       = implode(' AND ', $where);
        $offset  = max(0, ($page - 1) * $perPage);
        $total   = (int)$this->db->scalar(
            "SELECT COUNT(*) FROM payments p
             JOIN customers c ON c.id = p.customer_id
             LEFT JOIN invoices i ON i.id = p.invoice_id WHERE {$w}",
            $params
        );
        $rows = $this->db->select(
            "SELECT p.id, p.payment_number, p.amount, p.type, p.method_slug, p.status, p.reference, p.paid_at,
                    i.invoice_number, c.first_name, c.last_name, c.mobile, b.name AS branch_name
             FROM payments p
             JOIN customers c ON c.id = p.customer_id
             LEFT JOIN invoices i ON i.id = p.invoice_id
             LEFT JOIN branches b ON b.id = p.branch_id
             WHERE {$w} ORDER BY p.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        $sums = $this->db->selectOne(
            "SELECT COALESCE(SUM(CASE WHEN p.type = 'REFUND' THEN -p.amount ELSE p.amount END),0) AS net,
                    COALESCE(SUM(CASE WHEN p.type = 'REFUND' THEN p.amount ELSE 0 END),0) AS refunds
             FROM payments p JOIN customers c ON c.id = p.customer_id
             LEFT JOIN invoices i ON i.id = p.invoice_id WHERE {$w}",
            $params
        ) ?? [];

        return [
            'data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage,
            'last_page' => max(1, (int)ceil($total / $perPage)), 'sums' => $sums,
        ];
    }

    public function recent(int $limit = 10, ?int $branchId = null): array
    {
        $sql = "SELECT p.id, p.payment_number, p.amount, p.type, p.method_slug, p.paid_at,
                       CONCAT(c.first_name,' ',c.last_name) AS customer_name, i.invoice_number
                FROM payments p
                JOIN customers c ON c.id = p.customer_id
                LEFT JOIN invoices i ON i.id = p.invoice_id
                WHERE p.status = 'SUCCESS'";
        $params = [];
        if ($branchId) {
            $sql .= ' AND p.branch_id = :b';
            $params['b'] = $branchId;
        }
        return $this->db->select($sql . " ORDER BY p.id DESC LIMIT {$limit}", $params);
    }
}
