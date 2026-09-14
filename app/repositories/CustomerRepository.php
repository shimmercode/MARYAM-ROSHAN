<?php
declare(strict_types=1);

namespace App\Repositories;

final class CustomerRepository extends BaseRepository
{
    protected string $table = 'customers';
    protected bool $softDeletes = true;
    protected array $sortable = ['id', 'first_name', 'last_name', 'mobile', 'total_spent', 'visits_count', 'last_visit_at', 'loyalty_points', 'created_at'];
    protected array $columns = [
        'id', 'user_id', 'code', 'first_name', 'last_name', 'mobile', 'email', 'gender', 'birth_date',
        'national_code', 'preferred_branch_id', 'preferred_staff_id', 'source', 'status', 'visits_count',
        'total_spent', 'last_visit_at', 'first_visit_at', 'loyalty_points', 'loyalty_tier_id',
        'health_score', 'churn_risk', 'marketing_opt_in', 'created_at',
    ];

    public function nextCode(): string
    {
        $max = (int)$this->db->scalar("SELECT COALESCE(MAX(CAST(SUBSTRING(code, 4) AS UNSIGNED)), 0) FROM customers WHERE code LIKE 'MR-%'");
        return 'MR-' . str_pad((string)($max + 1), 5, '0', STR_PAD_LEFT);
    }

    public function findByMobile(string $mobile): ?array
    {
        return $this->findBy('mobile', $mobile);
    }

    public function findFull(int $id): ?array
    {
        return $this->db->selectOne(
            "SELECT c.*, b.name AS branch_name,
                    CONCAT(s.first_name, ' ', s.last_name) AS preferred_staff_name,
                    lt.name AS tier_name, lt.color AS tier_color, lt.discount_percent AS tier_discount,
                    w.balance AS wallet_balance
             FROM customers c
             LEFT JOIN branches b        ON b.id = c.preferred_branch_id
             LEFT JOIN staff s           ON s.id = c.preferred_staff_id
             LEFT JOIN loyalty_tiers lt  ON lt.id = c.loyalty_tier_id
             LEFT JOIN wallet_accounts w ON w.customer_id = c.id
             WHERE c.id = :id AND c.deleted_at IS NULL LIMIT 1",
            ['id' => $id]
        );
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $where  = ['c.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $where[] = "(c.first_name LIKE :s1 OR c.last_name LIKE :s2 OR c.mobile LIKE :s3 OR c.code LIKE :s4
                         OR CONCAT(c.first_name, ' ', c.last_name) LIKE :s5)";
            $params += ['s1' => $like, 's2' => $like, 's3' => $like, 's4' => $like, 's5' => $like];
        }
        if (!empty($filters['status'])) {
            $where[] = 'c.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['branch_id'])) {
            $where[] = 'c.preferred_branch_id = :bid';
            $params['bid'] = (int)$filters['branch_id'];
        }
        if (!empty($filters['tier_id'])) {
            $where[] = 'c.loyalty_tier_id = :tid';
            $params['tid'] = (int)$filters['tier_id'];
        }
        if (!empty($filters['tag_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM customer_tag_map m WHERE m.customer_id = c.id AND m.tag_id = :tag)';
            $params['tag'] = (int)$filters['tag_id'];
        }
        if (!empty($filters['churn'])) {
            $where[] = 'c.churn_risk >= :churn';
            $params['churn'] = (float)$filters['churn'];
        }
        if (!empty($filters['inactive_days'])) {
            $where[] = '(c.last_visit_at IS NULL OR c.last_visit_at < DATE_SUB(NOW(), INTERVAL :days DAY))';
            $params['days'] = (int)$filters['inactive_days'];
        }

        $w = implode(' AND ', $where);
        $sortMap = [
            'first_name' => 'c.first_name', 'last_name' => 'c.last_name', 'mobile' => 'c.mobile',
            'total_spent' => 'c.total_spent', 'visits_count' => 'c.visits_count',
            'last_visit_at' => 'c.last_visit_at', 'loyalty_points' => 'c.loyalty_points',
            'created_at' => 'c.created_at', 'id' => 'c.id',
        ];
        $sortCol = $sortMap[$filters['sort'] ?? ''] ?? 'c.id';
        $dir     = strtoupper((string)($filters['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT c.id, c.code, c.first_name, c.last_name, c.mobile, c.status, c.visits_count,
                       c.total_spent, c.last_visit_at, c.loyalty_points, c.health_score, c.churn_risk,
                       b.name AS branch_name, lt.name AS tier_name, lt.color AS tier_color
                FROM customers c
                LEFT JOIN branches b       ON b.id = c.preferred_branch_id
                LEFT JOIN loyalty_tiers lt ON lt.id = c.loyalty_tier_id
                WHERE {$w} ORDER BY {$sortCol} {$dir}";
        $countSql = "SELECT COUNT(*) FROM customers c WHERE {$w}";

        return $this->paginateQuery($sql, $countSql, $params, $page, $perPage);
    }

    /** Fast typeahead search for POS/booking. */
    public function search(string $term, int $limit = 10): array
    {
        $like = '%' . $term . '%';
        return $this->db->select(
            "SELECT id, code, first_name, last_name, mobile, loyalty_points, total_spent
             FROM customers
             WHERE deleted_at IS NULL AND status <> 'BLACKLIST'
               AND (first_name LIKE :a OR last_name LIKE :b OR mobile LIKE :c OR code LIKE :d
                    OR CONCAT(first_name, ' ', last_name) LIKE :e)
             ORDER BY last_visit_at DESC LIMIT {$limit}",
            ['a' => $like, 'b' => $like, 'c' => $like, 'd' => $like, 'e' => $like]
        );
    }

    public function appointments(int $customerId, int $limit = 20): array
    {
        return $this->db->select(
            "SELECT a.id, a.code, a.appointment_date, a.start_time, a.end_time, a.status, a.total_price,
                    CONCAT(s.first_name, ' ', s.last_name) AS staff_name, b.name AS branch_name,
                    (SELECT GROUP_CONCAT(sv.name SEPARATOR '، ') FROM appointment_items ai
                     JOIN services sv ON sv.id = ai.service_id WHERE ai.appointment_id = a.id) AS services
             FROM appointments a
             JOIN staff s    ON s.id = a.staff_id
             JOIN branches b ON b.id = a.branch_id
             WHERE a.customer_id = :c
             ORDER BY a.appointment_date DESC, a.start_time DESC LIMIT {$limit}",
            ['c' => $customerId]
        );
    }

    public function invoices(int $customerId, int $limit = 20): array
    {
        return $this->db->select(
            'SELECT id, invoice_number, issue_date, total, paid_amount, due_amount, payment_status
             FROM invoices WHERE customer_id = :c ORDER BY id DESC LIMIT ' . $limit,
            ['c' => $customerId]
        );
    }

    public function payments(int $customerId, int $limit = 20): array
    {
        return $this->db->select(
            'SELECT p.id, p.payment_number, p.amount, p.type, p.method_slug, p.status, p.paid_at,
                    i.invoice_number
             FROM payments p
             LEFT JOIN invoices i ON i.id = p.invoice_id
             WHERE p.customer_id = :c ORDER BY p.id DESC LIMIT ' . $limit,
            ['c' => $customerId]
        );
    }

    public function services(int $customerId, int $limit = 30): array
    {
        return $this->db->select(
            'SELECT sv.name, sc.name AS category, COUNT(*) AS times, MAX(i.issue_date) AS last_date,
                    SUM(ii.total) AS total_spent
             FROM invoice_items ii
             JOIN invoices i  ON i.id = ii.invoice_id
             JOIN services sv ON sv.id = ii.service_id
             JOIN service_categories sc ON sc.id = sv.category_id
             WHERE i.customer_id = :c AND ii.item_type = \'SERVICE\'
             GROUP BY sv.id, sv.name, sc.name
             ORDER BY times DESC LIMIT ' . $limit,
            ['c' => $customerId]
        );
    }

    public function notes(int $customerId): array
    {
        return $this->db->select(
            "SELECT n.id, n.note, n.is_pinned, n.created_at, CONCAT(u.first_name,' ',u.last_name) AS author
             FROM customer_notes n LEFT JOIN users u ON u.id = n.user_id
             WHERE n.customer_id = :c ORDER BY n.is_pinned DESC, n.id DESC LIMIT 50",
            ['c' => $customerId]
        );
    }

    public function loyaltyHistory(int $customerId, int $limit = 30): array
    {
        return $this->db->select(
            'SELECT id, type, points, balance_after, description, reference_type, reference_id, created_at
             FROM loyalty_transactions WHERE customer_id = :c ORDER BY id DESC LIMIT ' . $limit,
            ['c' => $customerId]
        );
    }

    public function walletHistory(int $customerId, int $limit = 30): array
    {
        return $this->db->select(
            'SELECT id, type, amount, balance_after, description, created_at
             FROM wallet_transactions WHERE customer_id = :c ORDER BY id DESC LIMIT ' . $limit,
            ['c' => $customerId]
        );
    }

    public function reviews(int $customerId): array
    {
        return $this->db->select(
            "SELECT r.id, r.rating, r.title, r.comment, r.status, r.created_at,
                    CONCAT(s.first_name,' ',s.last_name) AS staff_name, sv.name AS service_name
             FROM reviews r
             LEFT JOIN staff s    ON s.id = r.staff_id
             LEFT JOIN services sv ON sv.id = r.service_id
             WHERE r.customer_id = :c ORDER BY r.id DESC LIMIT 30",
            ['c' => $customerId]
        );
    }

    public function activity(int $customerId, int $limit = 40): array
    {
        return $this->db->select(
            "SELECT 'APPOINTMENT' AS kind, a.created_at AS at, CONCAT('نوبت ', a.code, ' — ', a.status) AS title
             FROM appointments a WHERE a.customer_id = :c1
             UNION ALL
             SELECT 'INVOICE', i.created_at, CONCAT('فاکتور ', i.invoice_number)
             FROM invoices i WHERE i.customer_id = :c2
             UNION ALL
             SELECT 'PAYMENT', p.created_at, CONCAT('پرداخت ', p.payment_number)
             FROM payments p WHERE p.customer_id = :c3
             UNION ALL
             SELECT 'LOYALTY', l.created_at, CONCAT('امتیاز ', l.type, ': ', l.points)
             FROM loyalty_transactions l WHERE l.customer_id = :c4
             ORDER BY at DESC LIMIT {$limit}",
            ['c1' => $customerId, 'c2' => $customerId, 'c3' => $customerId, 'c4' => $customerId]
        );
    }

    public function tags(int $customerId): array
    {
        return $this->db->select(
            'SELECT t.id, t.name, t.color FROM customer_tags t
             JOIN customer_tag_map m ON m.tag_id = t.id WHERE m.customer_id = :c',
            ['c' => $customerId]
        );
    }

    public function syncTags(int $customerId, array $tagIds): void
    {
        $this->db->transaction(function () use ($customerId, $tagIds): void {
            $this->db->delete('customer_tag_map', 'customer_id = :c', ['c' => $customerId]);
            foreach (array_unique(array_map('intval', $tagIds)) as $t) {
                $this->db->insert('customer_tag_map', ['customer_id' => $customerId, 'tag_id' => $t]);
            }
        });
    }

    /** Recompute denormalized aggregates from source-of-truth tables. */
    public function refreshAggregates(int $customerId): void
    {
        $agg = $this->db->selectOne(
            "SELECT COUNT(DISTINCT a.id) AS visits, MAX(a.ends_at) AS last_visit, MIN(a.ends_at) AS first_visit
             FROM appointments a WHERE a.customer_id = :c AND a.status = 'COMPLETED'",
            ['c' => $customerId]
        ) ?? [];
        $spent = (float)$this->db->scalar(
            "SELECT COALESCE(SUM(total - refunded_amount), 0) FROM invoices
             WHERE customer_id = :c AND status <> 'CANCELLED'",
            ['c' => $customerId]
        );
        $this->update($customerId, [
            'visits_count'   => (int)($agg['visits'] ?? 0),
            'total_spent'    => number_format($spent, 2, '.', ''),
            'last_visit_at'  => $agg['last_visit'] ?? null,
            'first_visit_at' => $agg['first_visit'] ?? null,
        ]);
    }

    public function birthdaysToday(): array
    {
        return $this->db->select(
            "SELECT id, first_name, last_name, mobile, birth_date FROM customers
             WHERE deleted_at IS NULL AND status = 'ACTIVE' AND birth_date IS NOT NULL
               AND MONTH(birth_date) = MONTH(CURDATE()) AND DAY(birth_date) = DAY(CURDATE())"
        );
    }
}
