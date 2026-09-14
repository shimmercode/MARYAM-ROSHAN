<?php
declare(strict_types=1);

namespace App\Repositories;

final class StaffRepository extends BaseRepository
{
    protected string $table = 'staff';
    protected bool $softDeletes = true;
    protected array $sortable = ['id', 'first_name', 'last_name', 'rating', 'hire_date', 'created_at'];
    protected array $columns = [
        'id', 'user_id', 'code', 'first_name', 'last_name', 'mobile', 'email', 'branch_id', 'job_title',
        'employment_type', 'hire_date', 'base_salary', 'commission_percent', 'color', 'avatar', 'bio',
        'rating', 'status', 'is_public', 'created_at',
    ];

    public function nextCode(): string
    {
        $max = (int)$this->db->scalar("SELECT COALESCE(MAX(CAST(SUBSTRING(code, 4) AS UNSIGNED)), 0) FROM staff WHERE code LIKE 'ST-%'");
        return 'ST-' . str_pad((string)($max + 1), 4, '0', STR_PAD_LEFT);
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $where  = ['s.deleted_at IS NULL'];
        $params = [];
        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $where[] = "(s.first_name LIKE :a OR s.last_name LIKE :b OR s.mobile LIKE :c OR s.code LIKE :d
                         OR EXISTS (SELECT 1 FROM staff_specialties sp WHERE sp.staff_id = s.id AND sp.title LIKE :e))";
            $params += ['a' => $like, 'b' => $like, 'c' => $like, 'd' => $like, 'e' => $like];
        }
        if (!empty($filters['branch_id'])) {
            $where[] = 's.branch_id = :bid';
            $params['bid'] = (int)$filters['branch_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 's.status = :st';
            $params['st'] = $filters['status'];
        }
        $w   = implode(' AND ', $where);
        $map = ['first_name' => 's.first_name', 'last_name' => 's.last_name', 'rating' => 's.rating', 'hire_date' => 's.hire_date', 'id' => 's.id'];
        $col = $map[$filters['sort'] ?? ''] ?? 's.id';
        $dir = strtoupper((string)($filters['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT s.id, s.code, s.first_name, s.last_name, s.mobile, s.job_title, s.status,
                       s.avatar, s.color, s.rating, s.commission_percent, b.name AS branch_name,
                       (SELECT COUNT(*) FROM staff_services ss WHERE ss.staff_id = s.id) AS services_count,
                       (SELECT GROUP_CONCAT(sp.title SEPARATOR '، ') FROM staff_specialties sp WHERE sp.staff_id = s.id) AS specialties
                FROM staff s LEFT JOIN branches b ON b.id = s.branch_id
                WHERE {$w} ORDER BY {$col} {$dir}";
        $countSql = "SELECT COUNT(*) FROM staff s WHERE {$w}";
        return $this->paginateQuery($sql, $countSql, $params, $page, $perPage);
    }

    public function findFull(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT s.*, b.name AS branch_name, p.national_code, p.birth_date, p.address,
                    p.emergency_contact, p.emergency_phone, p.bank_account, p.instagram
             FROM staff s
             LEFT JOIN branches b ON b.id = s.branch_id
             LEFT JOIN staff_profiles p ON p.staff_id = s.id
             WHERE s.id = :id AND s.deleted_at IS NULL LIMIT 1',
            ['id' => $id]
        );
    }

    public function findByUserId(int $userId): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM staff WHERE user_id = :u AND deleted_at IS NULL LIMIT 1',
            ['u' => $userId]
        );
    }

    public function activeList(?int $branchId = null): array
    {
        $sql    = "SELECT id, first_name, last_name, color, branch_id, avatar, job_title FROM staff
                   WHERE status = 'ACTIVE' AND deleted_at IS NULL";
        $params = [];
        if ($branchId) {
            $sql .= ' AND branch_id = :b';
            $params['b'] = $branchId;
        }
        return $this->db->select($sql . ' ORDER BY first_name, last_name', $params);
    }

    public function publicTeam(): array
    {
        return $this->db->select(
            "SELECT s.id, s.first_name, s.last_name, s.job_title, s.avatar, s.bio, s.rating,
                    b.name AS branch_name,
                    (SELECT GROUP_CONCAT(sp.title SEPARATOR '، ') FROM staff_specialties sp WHERE sp.staff_id = s.id) AS specialties
             FROM staff s LEFT JOIN branches b ON b.id = s.branch_id
             WHERE s.status = 'ACTIVE' AND s.is_public = 1 AND s.deleted_at IS NULL
             ORDER BY s.rating DESC, s.first_name"
        );
    }

    public function specialties(int $staffId): array
    {
        return $this->db->select('SELECT id, title, level FROM staff_specialties WHERE staff_id = :s', ['s' => $staffId]);
    }

    public function syncSpecialties(int $staffId, array $titles): void
    {
        $this->db->transaction(function () use ($staffId, $titles): void {
            $this->db->delete('staff_specialties', 'staff_id = :s', ['s' => $staffId]);
            foreach (array_filter(array_map('trim', $titles)) as $t) {
                $this->db->insert('staff_specialties', ['staff_id' => $staffId, 'title' => mb_substr($t, 0, 80)]);
            }
        });
    }

    public function serviceIds(int $staffId): array
    {
        return array_map('intval', array_column(
            $this->db->select('SELECT service_id FROM staff_services WHERE staff_id = :s', ['s' => $staffId]),
            'service_id'
        ));
    }

    public function syncServices(int $staffId, array $serviceIds): void
    {
        $this->db->transaction(function () use ($staffId, $serviceIds): void {
            $this->db->delete('staff_services', 'staff_id = :s', ['s' => $staffId]);
            foreach (array_unique(array_map('intval', $serviceIds)) as $svc) {
                $this->db->insert('staff_services', ['staff_id' => $staffId, 'service_id' => $svc]);
            }
        });
    }

    public function shifts(int $staffId, string $from, string $to): array
    {
        return $this->db->select(
            'SELECT id, shift_date, start_time, end_time, break_start, break_end, status, branch_id
             FROM staff_shifts WHERE staff_id = :s AND shift_date BETWEEN :f AND :t
             ORDER BY shift_date, start_time',
            ['s' => $staffId, 'f' => $from, 't' => $to]
        );
    }

    /** Recurring weekly availability pattern (0=Saturday … 6=Friday). */
    public function weeklyShifts(int $staffId): array
    {
        $rows = $this->db->select(
            'SELECT weekday, start_time, end_time, break_start, break_end, is_active
             FROM staff_weekly_shifts WHERE staff_id = :s ORDER BY weekday',
            ['s' => $staffId]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r['weekday']] = $r;
        }
        return $out;
    }

    public function todaySchedule(int $staffId, ?string $date = null): array
    {
        $date ??= date('Y-m-d');
        return $this->db->select(
            "SELECT a.id, a.code, a.start_time, a.end_time, a.status, a.total_price, a.notes,
                    c.first_name, c.last_name, c.mobile, c.id AS customer_id,
                    (SELECT GROUP_CONCAT(sv.name SEPARATOR '، ') FROM appointment_items ai
                     JOIN services sv ON sv.id = ai.service_id WHERE ai.appointment_id = a.id) AS services
             FROM appointments a JOIN customers c ON c.id = a.customer_id
             WHERE a.staff_id = :s AND a.appointment_date = :d AND a.status <> 'CANCELLED'
             ORDER BY a.start_time",
            ['s' => $staffId, 'd' => $date]
        );
    }

    /** Sales + commission summary for a period. */
    public function performance(int $staffId, string $from, string $to): array
    {
        $sales = $this->db->selectOne(
            "SELECT COALESCE(SUM(ii.total), 0) AS revenue, COUNT(DISTINCT ii.invoice_id) AS invoices,
                    COUNT(*) AS items
             FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id
             WHERE ii.staff_id = :s AND i.issue_date BETWEEN :f AND :t AND i.status <> 'CANCELLED'",
            ['s' => $staffId, 'f' => $from, 't' => $to]
        ) ?? [];

        $appointments = $this->db->selectOne(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN status = 'NO_SHOW'   THEN 1 ELSE 0 END) AS no_show,
                    SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) AS cancelled
             FROM appointments WHERE staff_id = :s AND appointment_date BETWEEN :f AND :t",
            ['s' => $staffId, 'f' => $from, 't' => $to]
        ) ?? [];

        $commission = (float)$this->db->scalar(
            "SELECT COALESCE(SUM(amount), 0) FROM commissions
             WHERE staff_id = :s AND created_at BETWEEN :f AND :t AND status <> 'CANCELLED'",
            ['s' => $staffId, 'f' => $from . ' 00:00:00', 't' => $to . ' 23:59:59']
        );

        $rating = (float)$this->db->scalar(
            "SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE staff_id = :s AND status = 'APPROVED'",
            ['s' => $staffId]
        );

        return [
            'revenue'      => (float)($sales['revenue'] ?? 0),
            'invoices'     => (int)($sales['invoices'] ?? 0),
            'items'        => (int)($sales['items'] ?? 0),
            'appointments' => (int)($appointments['total'] ?? 0),
            'completed'    => (int)($appointments['completed'] ?? 0),
            'no_show'      => (int)($appointments['no_show'] ?? 0),
            'cancelled'    => (int)($appointments['cancelled'] ?? 0),
            'commission'   => $commission,
            'rating'       => round($rating, 2),
        ];
    }

    public function refreshRating(int $staffId): void
    {
        $avg = (float)$this->db->scalar(
            "SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE staff_id = :s AND status = 'APPROVED'",
            ['s' => $staffId]
        );
        $this->update($staffId, ['rating' => number_format($avg, 2, '.', '')]);
    }
}
