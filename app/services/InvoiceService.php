<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\BusinessException;

/**
 * Invoice creation and lifecycle. All financial mutations are transactional
 * and never delete history — cancellations/refunds create compensating rows.
 */
final class InvoiceService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    public function nextNumber(): string
    {
        $prefix = 'INV-' . date('ymd') . '-';
        $max    = (int)$this->db->scalar(
            'SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_number, 14) AS UNSIGNED)), 0) FROM invoices WHERE invoice_number LIKE :p',
            ['p' => $prefix . '%']
        );
        return $prefix . str_pad((string)($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create an invoice (optionally with payment, loyalty use and wallet).
     *
     * @param array{
     *   customer_id:int, branch_id:int, staff_id?:?int, appointment_id?:?int,
     *   items:array<array{type?:string, service_id?:?int, product_id?:?int, staff_id?:?int,
     *                     title?:string, quantity?:float, unit_price?:float, discount?:float}>,
     *   discount_code?:?string, manual_discount?:float, loyalty_points?:int,
     *   tax_rate?:?float, notes?:?string, payments?:array<array{method:string, amount:float, reference?:string}>
     * } $data
     */
    public function create(array $data): array
    {
        $customerId = (int)($data['customer_id'] ?? 0);
        $branchId   = (int)($data['branch_id'] ?? 0);
        $items      = (array)($data['items'] ?? []);

        if ($items === []) {
            throw new BusinessException('فاکتور باید حداقل یک ردیف داشته باشد.', 'EMPTY_INVOICE', 422);
        }

        return $this->db->transaction(function () use ($data, $customerId, $branchId, $items): array {
            $customer = $this->db->selectOne('SELECT id, first_name, last_name, mobile FROM customers WHERE id = :c AND deleted_at IS NULL', ['c' => $customerId]);
            if ($customer === null) {
                throw new BusinessException('مشتری یافت نشد.', 'CUSTOMER_NOT_FOUND', 404);
            }
            if ($this->db->scalar('SELECT id FROM branches WHERE id = :b AND deleted_at IS NULL', ['b' => $branchId]) === null) {
                throw new BusinessException('شعبه یافت نشد.', 'BRANCH_NOT_FOUND', 404);
            }

            /* ---------- normalize lines ---------- */
            $lines    = [];
            $subtotal = 0.0;
            foreach ($items as $raw) {
                $type = strtoupper((string)($raw['type'] ?? 'SERVICE'));
                $qty  = max(0.01, (float)($raw['quantity'] ?? 1));
                $lineDiscount = max(0.0, (float)($raw['discount'] ?? 0));

                if ($type === 'SERVICE') {
                    $sid = (int)($raw['service_id'] ?? 0);
                    $svc = $this->db->selectOne('SELECT id, name, price, cost FROM services WHERE id = :s AND deleted_at IS NULL', ['s' => $sid]);
                    if ($svc === null) {
                        throw new BusinessException('خدمت انتخاب‌شده یافت نشد.', 'SERVICE_NOT_FOUND', 404);
                    }
                    $unit  = isset($raw['unit_price']) && $raw['unit_price'] !== '' ? (float)$raw['unit_price'] : (float)$svc['price'];
                    $title = (string)$svc['name'];
                    $cost  = (float)$svc['cost'];
                    $productId = null;
                } elseif ($type === 'PRODUCT') {
                    $pid  = (int)($raw['product_id'] ?? 0);
                    $prod = $this->db->selectOne('SELECT id, name, sale_price, purchase_price FROM products WHERE id = :p AND deleted_at IS NULL', ['p' => $pid]);
                    if ($prod === null) {
                        throw new BusinessException('محصول انتخاب‌شده یافت نشد.', 'PRODUCT_NOT_FOUND', 404);
                    }
                    $unit  = isset($raw['unit_price']) && $raw['unit_price'] !== '' ? (float)$raw['unit_price'] : (float)$prod['sale_price'];
                    $title = (string)$prod['name'];
                    $cost  = (float)$prod['purchase_price'];
                    $sid   = null;
                    $productId = $pid;
                } else {
                    $unit  = (float)($raw['unit_price'] ?? 0);
                    $title = (string)($raw['title'] ?? 'سایر');
                    $cost  = 0.0;
                    $sid   = null;
                    $productId = null;
                }

                $lineTotal = max(0.0, $unit * $qty - $lineDiscount);
                $subtotal += $lineTotal;
                $lines[] = [
                    'item_type'  => $type,
                    'service_id' => $sid,
                    'product_id' => $productId,
                    'staff_id'   => isset($raw['staff_id']) && $raw['staff_id'] ? (int)$raw['staff_id'] : ($data['staff_id'] ?? null),
                    'title'      => $title,
                    'quantity'   => number_format($qty, 2, '.', ''),
                    'unit_price' => number_format($unit, 2, '.', ''),
                    'discount'   => number_format($lineDiscount, 2, '.', ''),
                    'total'      => number_format($lineTotal, 2, '.', ''),
                    'cost'       => number_format($cost * $qty, 2, '.', ''),
                ];
            }

            /* ---------- discounts ---------- */
            $discountAmount = max(0.0, (float)($data['manual_discount'] ?? 0));
            $discountId     = null;
            $discountCodeId = null;
            if (!empty($data['discount_code'])) {
                $applied = $this->applyDiscountCode((string)$data['discount_code'], $subtotal, $customerId);
                $discountAmount += $applied['amount'];
                $discountId      = $applied['discount_id'];
                $discountCodeId  = $applied['code_id'];
            }
            $tier = (new LoyaltyService($this->db))->currentTier($customerId);
            if ($tier !== null && (float)$tier['discount_percent'] > 0) {
                $discountAmount += round($subtotal * (float)$tier['discount_percent'] / 100, 2);
            }
            $discountAmount = min($discountAmount, $subtotal);

            /* ---------- loyalty redemption ---------- */
            $loyalty       = new LoyaltyService($this->db);
            $pointsToUse   = max(0, (int)($data['loyalty_points'] ?? 0));
            $loyaltyAmount = 0.0;
            $pointsUsed    = 0;
            if ($pointsToUse > 0) {
                $calc          = $loyalty->calculateRedemption($customerId, $pointsToUse, $subtotal - $discountAmount);
                $pointsUsed    = $calc['points'];
                $loyaltyAmount = $calc['amount'];
            }

            /* ---------- tax & total ---------- */
            $taxRate    = isset($data['tax_rate'])
                ? (float)$data['tax_rate']
                : (float)SettingsService::get('tax_rate', 0);
            $taxable    = max(0.0, $subtotal - $discountAmount - $loyaltyAmount);
            $taxAmount  = round($taxable * $taxRate / 100, 2);
            $total      = round($taxable + $taxAmount, 2);

            /* ---------- persist ---------- */
            $invoiceId = $this->db->insert('invoices', [
                'invoice_number'      => $this->nextNumber(),
                'customer_id'         => $customerId,
                'staff_id'            => $data['staff_id'] ?? null,
                'branch_id'           => $branchId,
                'appointment_id'      => $data['appointment_id'] ?? null,
                'issue_date'          => date('Y-m-d'),
                'subtotal'            => number_format($subtotal, 2, '.', ''),
                'discount_amount'     => number_format($discountAmount, 2, '.', ''),
                'discount_id'         => $discountId,
                'loyalty_discount'    => number_format($loyaltyAmount, 2, '.', ''),
                'loyalty_points_used' => $pointsUsed,
                'tax_rate'            => number_format($taxRate, 2, '.', ''),
                'tax_amount'          => number_format($taxAmount, 2, '.', ''),
                'total'               => number_format($total, 2, '.', ''),
                'paid_amount'         => '0.00',
                'due_amount'          => number_format($total, 2, '.', ''),
                'payment_status'      => 'UNPAID',
                'status'              => 'ISSUED',
                'notes'               => isset($data['notes']) ? mb_substr((string)$data['notes'], 0, 500) : null,
                'created_by'          => AuthService::id(),
            ]);

            foreach ($lines as $line) {
                $line['invoice_id'] = $invoiceId;
                $this->db->insert('invoice_items', $line);
            }

            if ($pointsUsed > 0) {
                $loyalty->redeem($customerId, $pointsUsed, $invoiceId);
            }
            if ($discountId !== null) {
                $this->db->insert('discount_usages', [
                    'discount_id' => $discountId,
                    'code_id'     => $discountCodeId,
                    'customer_id' => $customerId,
                    'invoice_id'  => $invoiceId,
                    'amount'      => number_format($discountAmount, 2, '.', ''),
                ]);
                $this->db->execute('UPDATE discounts SET used_count = used_count + 1 WHERE id = :d', ['d' => $discountId]);
                if ($discountCodeId !== null) {
                    $this->db->execute('UPDATE discount_codes SET used_count = used_count + 1 WHERE id = :c', ['c' => $discountCodeId]);
                }
            }

            // Sell-through of retail products reduces stock.
            $inventory = new InventoryService($this->db);
            foreach ($lines as $line) {
                if ($line['item_type'] === 'PRODUCT' && $line['product_id']) {
                    $inventory->move((int)$line['product_id'], $branchId, 'OUT', (float)$line['quantity'], 'invoice', $invoiceId, 'فروش محصول', 0.0, true);
                }
            }

            // Commission is stored at issue time using the then-current rules.
            (new CommissionService($this->db))->recordForInvoice($invoiceId);

            // Link appointment.
            if (!empty($data['appointment_id'])) {
                $this->db->update('appointments', ['invoice_id' => $invoiceId], 'id = :a', ['a' => (int)$data['appointment_id']]);
            }

            // Optional immediate payments (POS flow).
            $paymentService = new PaymentService($this->db);
            foreach ((array)($data['payments'] ?? []) as $p) {
                $amount = (float)($p['amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }
                $paymentService->record([
                    'invoice_id'  => $invoiceId,
                    'customer_id' => $customerId,
                    'branch_id'   => $branchId,
                    'method'      => (string)($p['method'] ?? 'CASH'),
                    'amount'      => $amount,
                    'reference'   => $p['reference'] ?? null,
                ]);
            }

            $this->recalculatePaymentStatus($invoiceId);

            // Points earned on the finally-paid value.
            $fresh = $this->find($invoiceId);
            if ($fresh !== null && (float)$fresh['paid_amount'] > 0) {
                $loyalty->earnFromInvoice($customerId, (float)$fresh['paid_amount'], $invoiceId);
            }

            (new \App\Repositories\CustomerRepository($this->db))->refreshAggregates($customerId);

            AuditService::log('invoice_created', 'invoices', $invoiceId, null, [
                'total' => $total, 'customer_id' => $customerId,
            ]);
            AutomationService::fire('invoice_created', [
                'invoice_id' => $invoiceId, 'customer_id' => $customerId, 'total' => $total,
            ]);

            return $this->findFull($invoiceId) ?? [];
        });
    }

    /** @return array{amount:float, discount_id:?int, code_id:?int} */
    public function applyDiscountCode(string $code, float $subtotal, ?int $customerId = null): array
    {
        $row = $this->db->selectOne(
            "SELECT dc.id AS code_id, dc.usage_limit AS code_limit, dc.used_count AS code_used,
                    d.id, d.type, d.value, d.max_amount, d.min_order, d.usage_limit, d.used_count,
                    d.starts_at, d.ends_at, d.status
             FROM discount_codes dc JOIN discounts d ON d.id = dc.discount_id
             WHERE dc.code = :c AND dc.status = 'ACTIVE'",
            ['c' => mb_strtoupper(trim($code))]
        );
        if ($row === null || $row['status'] !== 'ACTIVE') {
            throw new BusinessException('کد تخفیف نامعتبر است.', 'INVALID_DISCOUNT_CODE', 422);
        }
        if ($row['starts_at'] !== null && strtotime((string)$row['starts_at']) > time()) {
            throw new BusinessException('کد تخفیف هنوز فعال نشده است.', 'DISCOUNT_NOT_STARTED', 422);
        }
        if ($row['ends_at'] !== null && strtotime((string)$row['ends_at']) < time()) {
            throw new BusinessException('کد تخفیف منقضی شده است.', 'DISCOUNT_EXPIRED', 422);
        }
        if ($row['usage_limit'] !== null && (int)$row['used_count'] >= (int)$row['usage_limit']) {
            throw new BusinessException('ظرفیت استفاده از این کد تخفیف تکمیل شده است.', 'DISCOUNT_EXHAUSTED', 422);
        }
        if ($row['code_limit'] !== null && (int)$row['code_used'] >= (int)$row['code_limit']) {
            throw new BusinessException('ظرفیت استفاده از این کد تخفیف تکمیل شده است.', 'DISCOUNT_EXHAUSTED', 422);
        }
        if ($subtotal < (float)$row['min_order']) {
            throw new BusinessException('حداقل مبلغ فاکتور برای این کد رعایت نشده است.', 'DISCOUNT_MIN_ORDER', 422);
        }

        $amount = $row['type'] === 'PERCENT' ? $subtotal * (float)$row['value'] / 100 : (float)$row['value'];
        if ($row['max_amount'] !== null) {
            $amount = min($amount, (float)$row['max_amount']);
        }
        return ['amount' => round(min($amount, $subtotal), 2), 'discount_id' => (int)$row['id'], 'code_id' => (int)$row['code_id']];
    }

    public function recalculatePaymentStatus(int $invoiceId): void
    {
        $invoice = $this->db->selectOne('SELECT id, total, status FROM invoices WHERE id = :i', ['i' => $invoiceId]);
        if ($invoice === null) {
            return;
        }
        $paid = (float)$this->db->scalar(
            "SELECT COALESCE(SUM(CASE WHEN type = 'REFUND' THEN -amount ELSE amount END), 0)
             FROM payments WHERE invoice_id = :i AND status = 'SUCCESS'",
            ['i' => $invoiceId]
        );
        $refunded = (float)$this->db->scalar(
            "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = :i AND type = 'REFUND' AND status = 'SUCCESS'",
            ['i' => $invoiceId]
        );
        $total = (float)$invoice['total'];
        $due   = max(0.0, round($total - $paid, 2));

        $status = 'UNPAID';
        if ($invoice['status'] === 'CANCELLED') {
            $status = 'CANCELLED';
        } elseif ($refunded > 0 && $paid <= 0.01) {
            $status = 'REFUNDED';
        } elseif ($due <= 0.01 && $paid > 0) {
            $status = 'PAID';
        } elseif ($paid > 0) {
            $status = 'PARTIAL';
        }

        $this->db->update('invoices', [
            'paid_amount'     => number_format(max(0, $paid), 2, '.', ''),
            'due_amount'      => number_format($due, 2, '.', ''),
            'refunded_amount' => number_format($refunded, 2, '.', ''),
            'payment_status'  => $status,
        ], 'id = :i', ['i' => $invoiceId]);
    }

    public function cancel(int $invoiceId, ?string $reason = null): array
    {
        return $this->db->transaction(function () use ($invoiceId, $reason): array {
            $invoice = $this->db->selectOne('SELECT * FROM invoices WHERE id = :i' . $this->db->forUpdate(), ['i' => $invoiceId]);
            if ($invoice === null) {
                throw new BusinessException('فاکتور یافت نشد.', 'INVOICE_NOT_FOUND', 404);
            }
            if ($invoice['status'] === 'CANCELLED') {
                throw new BusinessException('این فاکتور قبلاً لغو شده است.', 'ALREADY_CANCELLED', 422);
            }
            if ((float)$invoice['paid_amount'] > 0) {
                throw new BusinessException('فاکتور پرداخت‌شده را ابتدا باید برگشت (Refund) داد.', 'INVOICE_PAID', 422);
            }

            $this->db->update('invoices', [
                'status'         => 'CANCELLED',
                'payment_status' => 'CANCELLED',
                'cancelled_at'   => date('Y-m-d H:i:s'),
                'notes'          => trim((string)$invoice['notes'] . "\nلغو: " . (string)$reason),
            ], 'id = :i', ['i' => $invoiceId]);

            (new CommissionService($this->db))->cancelForInvoice($invoiceId);

            // Reverse loyalty points that were redeemed/earned on this invoice.
            $loyalty = new LoyaltyService($this->db);
            if ((int)$invoice['loyalty_points_used'] > 0) {
                $loyalty->adjust((int)$invoice['customer_id'], (int)$invoice['loyalty_points_used'], 'ADJUST', 'بازگشت امتیاز به دلیل لغو فاکتور', 'invoice', $invoiceId);
            }
            $earned = (int)$this->db->scalar(
                "SELECT COALESCE(SUM(points),0) FROM loyalty_transactions
                 WHERE reference_type = 'invoice' AND reference_id = :i AND type = 'EARN'",
                ['i' => $invoiceId]
            );
            if ($earned > 0) {
                $loyalty->adjust((int)$invoice['customer_id'], -$earned, 'ADJUST', 'حذف امتیاز فاکتور لغو شده', 'invoice', $invoiceId);
            }

            (new \App\Repositories\CustomerRepository($this->db))->refreshAggregates((int)$invoice['customer_id']);
            AuditService::log('invoice_cancelled', 'invoices', $invoiceId, ['status' => $invoice['status']], ['status' => 'CANCELLED', 'reason' => $reason]);

            return $this->findFull($invoiceId) ?? [];
        });
    }

    public function find(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM invoices WHERE id = :i', ['i' => $id]);
    }

    public function findFull(int $id): ?array
    {
        $invoice = $this->db->selectOne(
            "SELECT i.*, c.first_name, c.last_name, c.mobile, c.code AS customer_code, c.email,
                    b.name AS branch_name, b.address AS branch_address, b.phone AS branch_phone,
                    CONCAT(s.first_name,' ',s.last_name) AS staff_name,
                    CONCAT(u.first_name,' ',u.last_name) AS created_by_name
             FROM invoices i
             JOIN customers c ON c.id = i.customer_id
             JOIN branches b  ON b.id = i.branch_id
             LEFT JOIN staff s ON s.id = i.staff_id
             LEFT JOIN users u ON u.id = i.created_by
             WHERE i.id = :i LIMIT 1",
            ['i' => $id]
        );
        if ($invoice === null) {
            return null;
        }
        $invoice['items'] = $this->db->select(
            "SELECT ii.*, CONCAT(s.first_name,' ',s.last_name) AS staff_name
             FROM invoice_items ii LEFT JOIN staff s ON s.id = ii.staff_id
             WHERE ii.invoice_id = :i ORDER BY ii.id",
            ['i' => $id]
        );
        $invoice['payments'] = $this->db->select(
            'SELECT id, payment_number, type, method_slug, amount, status, reference, paid_at
             FROM payments WHERE invoice_id = :i ORDER BY id',
            ['i' => $id]
        );
        return $invoice;
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $where[] = "(i.invoice_number LIKE :s1 OR c.mobile LIKE :s2 OR CONCAT(c.first_name,' ',c.last_name) LIKE :s3)";
            $params += ['s1' => $like, 's2' => $like, 's3' => $like];
        }
        foreach ([['payment_status', 'i.payment_status', 'ps'], ['branch_id', 'i.branch_id', 'bid'], ['customer_id', 'i.customer_id', 'cid'], ['staff_id', 'i.staff_id', 'sid']] as [$key, $col, $bind]) {
            if (!empty($filters[$key])) {
                $where[] = "{$col} = :{$bind}";
                $params[$bind] = $filters[$key];
            }
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'i.issue_date >= :df';
            $params['df'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'i.issue_date <= :dt';
            $params['dt'] = $filters['date_to'];
        }
        $w = implode(' AND ', $where);

        $sql = "SELECT i.id, i.invoice_number, i.issue_date, i.subtotal, i.discount_amount, i.tax_amount,
                       i.total, i.paid_amount, i.due_amount, i.payment_status, i.status,
                       c.id AS customer_id, c.first_name, c.last_name, c.mobile,
                       b.name AS branch_name, CONCAT(s.first_name,' ',s.last_name) AS staff_name
                FROM invoices i
                JOIN customers c ON c.id = i.customer_id
                JOIN branches b  ON b.id = i.branch_id
                LEFT JOIN staff s ON s.id = i.staff_id
                WHERE {$w} ORDER BY i.id DESC";
        $countSql = "SELECT COUNT(*) FROM invoices i JOIN customers c ON c.id = i.customer_id WHERE {$w}";

        $page    = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset  = ($page - 1) * $perPage;
        $total   = (int)$this->db->scalar($countSql, $params);
        $rows    = $this->db->select($sql . " LIMIT {$perPage} OFFSET {$offset}", $params);
        $sums    = $this->db->selectOne(
            "SELECT COALESCE(SUM(i.total),0) AS total, COALESCE(SUM(i.paid_amount),0) AS paid,
                    COALESCE(SUM(i.due_amount),0) AS due
             FROM invoices i JOIN customers c ON c.id = i.customer_id WHERE {$w}",
            $params
        ) ?? [];

        return [
            'data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage,
            'last_page' => max(1, (int)ceil($total / $perPage)),
            'from' => $total ? $offset + 1 : 0, 'to' => min($offset + $perPage, $total),
            'sums' => $sums,
        ];
    }

    public function forCustomer(int $customerId, int $limit = 50): array
    {
        return $this->db->select(
            'SELECT id, invoice_number, issue_date, total, paid_amount, due_amount, payment_status, status
             FROM invoices WHERE customer_id = :c ORDER BY id DESC LIMIT ' . $limit,
            ['c' => $customerId]
        );
    }
}
