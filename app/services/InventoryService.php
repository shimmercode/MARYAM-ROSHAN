<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\BusinessException;

/**
 * Stock movements. Every change writes an inventory_transactions row so the
 * ledger can always be replayed.
 */
final class InventoryService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    public function levelOf(int $productId, int $branchId): float
    {
        return (float)($this->db->scalar(
            'SELECT quantity FROM inventory WHERE product_id = :p AND branch_id = :b',
            ['p' => $productId, 'b' => $branchId]
        ) ?? 0);
    }

    /**
     * Apply a stock movement.
     * @param string $type IN|OUT|ADJUST|WASTE|TRANSFER|CONSUME
     */
    public function move(
        int $productId,
        int $branchId,
        string $type,
        float $quantity,
        ?string $refType = null,
        ?int $refId = null,
        ?string $note = null,
        float $unitCost = 0.0,
        bool $allowNegative = false
    ): float {
        if ($quantity <= 0) {
            throw new BusinessException('مقدار باید بزرگ‌تر از صفر باشد.', 'INVALID_QUANTITY', 422);
        }
        $type = strtoupper($type);
        if (!in_array($type, ['IN', 'OUT', 'ADJUST', 'WASTE', 'TRANSFER', 'CONSUME'], true)) {
            throw new BusinessException('نوع تراکنش انبار نامعتبر است.', 'INVALID_TYPE', 422);
        }

        return $this->db->transaction(function () use ($productId, $branchId, $type, $quantity, $refType, $refId, $note, $unitCost, $allowNegative): float {
            $row = $this->db->selectOne(
                'SELECT id, quantity FROM inventory WHERE product_id = :p AND branch_id = :b' . $this->db->forUpdate(),
                ['p' => $productId, 'b' => $branchId]
            );
            if ($row === null) {
                $invId   = $this->db->insert('inventory', ['product_id' => $productId, 'branch_id' => $branchId, 'quantity' => 0]);
                $current = 0.0;
            } else {
                $invId   = (int)$row['id'];
                $current = (float)$row['quantity'];
            }

            $delta = in_array($type, ['IN'], true) ? $quantity : -$quantity;
            if ($type === 'ADJUST') {
                $delta = $quantity - $current; // ADJUST sets an absolute level
            }
            $new = $current + $delta;

            if ($new < 0 && !$allowNegative) {
                $product = (string)$this->db->scalar('SELECT name FROM products WHERE id = :p', ['p' => $productId]);
                throw new BusinessException(
                    'موجودی کافی برای «' . $product . '» وجود ندارد. موجودی فعلی: ' . rtrim(rtrim(number_format($current, 3, '.', ''), '0'), '.'),
                    'INSUFFICIENT_STOCK',
                    409
                );
            }

            $this->db->update('inventory', ['quantity' => number_format($new, 3, '.', '')], 'id = :id', ['id' => $invId]);
            $this->db->insert('inventory_transactions', [
                'product_id'     => $productId,
                'branch_id'      => $branchId,
                'type'           => $type,
                'quantity'       => number_format(abs($delta), 3, '.', ''),
                'balance_after'  => number_format($new, 3, '.', ''),
                'unit_cost'      => number_format($unitCost, 2, '.', ''),
                'reference_type' => $refType,
                'reference_id'   => $refId,
                'note'           => $note,
                'created_by'     => AuthService::id(),
            ]);

            $this->checkReorder($productId, $branchId, $new);
            return $new;
        });
    }

    private function checkReorder(int $productId, int $branchId, float $level): void
    {
        $product = $this->db->selectOne('SELECT name, reorder_level FROM products WHERE id = :p', ['p' => $productId]);
        if ($product === null) {
            return;
        }
        $threshold = (float)$product['reorder_level'];
        if ($threshold <= 0) {
            return;
        }
        $open = $this->db->scalar(
            "SELECT id FROM stock_alerts WHERE product_id = :p AND branch_id = :b AND status = 'OPEN'",
            ['p' => $productId, 'b' => $branchId]
        );
        if ($level <= $threshold && !$open) {
            $this->db->insert('stock_alerts', [
                'product_id' => $productId,
                'branch_id'  => $branchId,
                'level'      => number_format($level, 3, '.', ''),
                'threshold'  => number_format($threshold, 3, '.', ''),
                'status'     => 'OPEN',
            ]);
            NotificationService::notifyByPermission(
                'inventory.view',
                'هشدار موجودی انبار',
                'موجودی «' . $product['name'] . '» به حد بحرانی رسیده است.',
                'WARNING',
                '/admin/inventory'
            );
        } elseif ($level > $threshold && $open) {
            $this->db->update('stock_alerts', ['status' => 'RESOLVED', 'resolved_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int)$open]);
        }
    }

    /** Deduct all recipe materials for a completed service. */
    public function consumeForService(int $serviceId, int $branchId, ?string $refType = null, ?int $refId = null): array
    {
        $recipe = $this->db->selectOne(
            "SELECT id FROM service_recipes WHERE service_id = :s AND status = 'ACTIVE'",
            ['s' => $serviceId]
        );
        if ($recipe === null) {
            return [];
        }
        $items = $this->db->select(
            'SELECT product_id, quantity FROM recipe_items WHERE recipe_id = :r',
            ['r' => (int)$recipe['id']]
        );
        $consumed = [];
        foreach ($items as $item) {
            // Recipe consumption may drive stock negative rather than blocking a
            // completed service; the alert system surfaces the shortage.
            $balance = $this->move(
                (int)$item['product_id'],
                $branchId,
                'CONSUME',
                (float)$item['quantity'],
                $refType,
                $refId,
                'مصرف مواد خدمت',
                0.0,
                true
            );
            $consumed[] = ['product_id' => (int)$item['product_id'], 'quantity' => (float)$item['quantity'], 'balance' => $balance];
        }
        return $consumed;
    }

    public function receivePurchaseOrder(int $poId, array $lines, ?string $note = null): int
    {
        return $this->db->transaction(function () use ($poId, $lines, $note): int {
            $po = $this->db->selectOne('SELECT id, branch_id, status FROM purchase_orders WHERE id = :p', ['p' => $poId]);
            if ($po === null) {
                throw new BusinessException('سفارش خرید یافت نشد.', 'PO_NOT_FOUND', 404);
            }
            if (in_array($po['status'], ['RECEIVED', 'CANCELLED'], true)) {
                throw new BusinessException('این سفارش قابل دریافت نیست.', 'PO_CLOSED', 422);
            }

            $receiptId = $this->db->insert('goods_receipts', [
                'po_id'          => $poId,
                'receipt_number' => 'GR-' . date('ymd') . '-' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT),
                'received_by'    => AuthService::id(),
                'notes'          => $note,
            ]);

            foreach ($lines as $line) {
                $itemId = (int)($line['item_id'] ?? 0);
                $qty    = (float)($line['quantity'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $item = $this->db->selectOne('SELECT id, product_id, quantity, received_quantity, unit_price FROM purchase_order_items WHERE id = :i AND po_id = :p', ['i' => $itemId, 'p' => $poId]);
                if ($item === null) {
                    continue;
                }
                $this->move((int)$item['product_id'], (int)$po['branch_id'], 'IN', $qty, 'goods_receipt', $receiptId, 'رسید کالا', (float)$item['unit_price']);
                $this->db->execute(
                    'UPDATE purchase_order_items SET received_quantity = received_quantity + :q WHERE id = :i',
                    ['q' => number_format($qty, 3, '.', ''), 'i' => $itemId]
                );
            }

            $pending = (int)$this->db->scalar(
                'SELECT COUNT(*) FROM purchase_order_items WHERE po_id = :p AND received_quantity < quantity',
                ['p' => $poId]
            );
            $this->db->update('purchase_orders', ['status' => $pending > 0 ? 'PARTIAL' : 'RECEIVED'], 'id = :p', ['p' => $poId]);
            AuditService::log('goods_received', 'purchase_orders', $poId);
            return $receiptId;
        });
    }

    public function lowStock(?int $branchId = null, int $limit = 20): array
    {
        $sql = 'SELECT p.id, p.name, p.sku, p.unit, p.reorder_level, i.quantity, b.name AS branch_name
                FROM inventory i
                JOIN products p ON p.id = i.product_id
                JOIN branches b ON b.id = i.branch_id
                WHERE p.deleted_at IS NULL AND p.reorder_level > 0 AND i.quantity <= p.reorder_level';
        $params = [];
        if ($branchId) {
            $sql .= ' AND i.branch_id = :b';
            $params['b'] = $branchId;
        }
        return $this->db->select($sql . " ORDER BY (i.quantity - p.reorder_level) LIMIT {$limit}", $params);
    }

    public function transactions(int $productId, int $limit = 50): array
    {
        return $this->db->select(
            "SELECT t.id, t.type, t.quantity, t.balance_after, t.note, t.created_at, b.name AS branch_name,
                    CONCAT(u.first_name,' ',u.last_name) AS user_name
             FROM inventory_transactions t
             JOIN branches b ON b.id = t.branch_id
             LEFT JOIN users u ON u.id = t.created_by
             WHERE t.product_id = :p ORDER BY t.id DESC LIMIT {$limit}",
            ['p' => $productId]
        );
    }
}
