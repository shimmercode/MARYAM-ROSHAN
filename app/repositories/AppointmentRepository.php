<?php
declare(strict_types=1);

namespace App\Repositories;

final class AppointmentRepository extends BaseRepository
{
    protected string $table = 'appointments';
    protected array $sortable = ['id', 'appointment_date', 'start_time', 'status', 'total_price', 'created_at'];
    protected array $columns = [
        'id', 'code', 'customer_id', 'staff_id', 'branch_id', 'resource_id', 'appointment_date',
        'start_time', 'end_time', 'starts_at', 'ends_at', 'duration_minutes', 'buffer_minutes',
        'status', 'source', 'total_price', 'deposit_amount', 'notes', 'invoice_id', 'created_at',
    ];

    public function nextCode(): string
    {
        $prefix = 'AP' . date('ymd');
        $max    = (int)$this->db->scalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(code, 9) AS UNSIGNED)), 0) FROM appointments WHERE code LIKE :p",
            ['p' => $prefix . '%']
        );
        return $prefix . str_pad((string)($max + 1), 3, '0', STR_PAD_LEFT);
    }

    public function findFull(int $id): ?array
    {
        return $this->db->selectOne(
            "SELECT a.*, c.first_name, c.last_name, c.mobile, c.code AS customer_code,
                    CONCAT(s.first_name, ' ', s.last_name) AS staff_name, s.color AS staff_color,
                    b.name AS branch_name, r.name AS resource_name,
                    i.invoice_number
             FROM appointments a
             JOIN customers c ON c.id = a.customer_id
             JOIN staff s     ON s.id = a.staff_id
             JOIN branches b  ON b.id = a.branch_id
             LEFT JOIN resources r ON r.id = a.resource_id
             LEFT JOIN invoices i  ON i.id = a.invoice_id
             WHERE a.id = :id LIMIT 1",
            ['id' => $id]
        );
    }

    public function items(int $appointmentId): array
    {
        return $this->db->select(
            'SELECT ai.id, ai.service_id, ai.price, ai.duration_minutes, ai.quantity,
                    sv.name AS service_name, sv.cost, sc.name AS category_name
             FROM appointment_items ai
             JOIN services sv ON sv.id = ai.service_id
             JOIN service_categories sc ON sc.id = sv.category_id
             WHERE ai.appointment_id = :a',
            ['a' => $appointmentId]
        );
    }

    public function history(int $appointmentId): array
    {
        return $this->db->select(
            "SELECT h.from_status, h.to_status, h.note, h.created_at,
                    CONCAT(u.first_name, ' ', u.last_name) AS changed_by_name
             FROM appointment_status_history h
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.appointment_id = :a ORDER BY h.id",
            ['a' => $appointmentId]
        );
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $where[] = "(a.code LIKE :s1 OR c.mobile LIKE :s2 OR CONCAT(c.first_name,' ',c.last_name) LIKE :s3)";
            $params += ['s1' => $like, 's2' => $like, 's3' => $like];
        }
        if (!empty($filters['status'])) {
            $where[] = 'a.status = :st';
            $params['st'] = $filters['status'];
        }
        if (!empty($filters['branch_id'])) {
            $where[] = 'a.branch_id = :bid';
            $params['bid'] = (int)$filters['branch_id'];
        }
        if (!empty($filters['staff_id'])) {
            $where[] = 'a.staff_id = :sid';
            $params['sid'] = (int)$filters['staff_id'];
        }
        if (!empty($filters['customer_id'])) {
            $where[] = 'a.customer_id = :cid';
            $params['cid'] = (int)$filters['customer_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'a.appointment_date >= :df';
            $params['df'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'a.appointment_date <= :dt';
            $params['dt'] = $filters['date_to'];
        }

        $w   = implode(' AND ', $where);
        $map = ['appointment_date' => 'a.appointment_date', 'start_time' => 'a.start_time', 'status' => 'a.status', 'total_price' => 'a.total_price', 'id' => 'a.id'];
        $col = $map[$filters['sort'] ?? ''] ?? 'a.appointment_date';
        $dir = strtoupper((string)($filters['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT a.id, a.code, a.appointment_date, a.start_time, a.end_time, a.status, a.total_price,
                       a.source, a.invoice_id,
                       c.id AS customer_id, c.first_name, c.last_name, c.mobile,
                       CONCAT(s.first_name,' ',s.last_name) AS staff_name, s.color AS staff_color,
                       b.name AS branch_name,
                       (SELECT GROUP_CONCAT(sv.name SEPARATOR '، ') FROM appointment_items ai
                        JOIN services sv ON sv.id = ai.service_id WHERE ai.appointment_id = a.id) AS services
                FROM appointments a
                JOIN customers c ON c.id = a.customer_id
                JOIN staff s     ON s.id = a.staff_id
                JOIN branches b  ON b.id = a.branch_id
                WHERE {$w} ORDER BY {$col} {$dir}, a.start_time {$dir}";
        $countSql = "SELECT COUNT(*) FROM appointments a JOIN customers c ON c.id = a.customer_id WHERE {$w}";

        return $this->paginateQuery($sql, $countSql, $params, $page, $perPage);
    }

    /** Calendar feed for a date range. */
    public function calendar(string $from, string $to, ?int $branchId = null, ?int $staffId = null): array
    {
        $where  = ['a.appointment_date BETWEEN :f AND :t', "a.status <> 'CANCELLED'"];
        $params = ['f' => $from, 't' => $to];
        if ($branchId) {
            $where[] = 'a.branch_id = :b';
            $params['b'] = $branchId;
        }
        if ($staffId) {
            $where[] = 'a.staff_id = :s';
            $params['s'] = $staffId;
        }
        $w = implode(' AND ', $where);
        return $this->db->select(
            "SELECT a.id, a.code, a.appointment_date, a.start_time, a.end_time, a.status,
                    a.staff_id, a.branch_id, a.total_price,
                    CONCAT(c.first_name,' ',c.last_name) AS customer_name, c.mobile,
                    CONCAT(s.first_name,' ',s.last_name) AS staff_name, s.color AS staff_color,
                    (SELECT GROUP_CONCAT(sv.name SEPARATOR '، ') FROM appointment_items ai
                     JOIN services sv ON sv.id = ai.service_id WHERE ai.appointment_id = a.id) AS services
             FROM appointments a
             JOIN customers c ON c.id = a.customer_id
             JOIN staff s     ON s.id = a.staff_id
             WHERE {$w} ORDER BY a.appointment_date, a.start_time",
            $params
        );
    }

    public function todayList(?int $branchId = null, int $limit = 50): array
    {
        $sql = "SELECT a.id, a.code, a.start_time, a.end_time, a.status, a.total_price,
                       CONCAT(c.first_name,' ',c.last_name) AS customer_name, c.mobile,
                       CONCAT(s.first_name,' ',s.last_name) AS staff_name, s.color AS staff_color,
                       (SELECT GROUP_CONCAT(sv.name SEPARATOR '، ') FROM appointment_items ai
                        JOIN services sv ON sv.id = ai.service_id WHERE ai.appointment_id = a.id) AS services
                FROM appointments a
                JOIN customers c ON c.id = a.customer_id
                JOIN staff s     ON s.id = a.staff_id
                WHERE a.appointment_date = CURDATE() AND a.status <> 'CANCELLED'";
        $params = [];
        if ($branchId) {
            $sql .= ' AND a.branch_id = :b';
            $params['b'] = $branchId;
        }
        return $this->db->select($sql . " ORDER BY a.start_time LIMIT {$limit}", $params);
    }

    public function upcoming(?int $branchId = null, int $limit = 10): array
    {
        $sql = "SELECT a.id, a.code, a.appointment_date, a.start_time, a.status,
                       CONCAT(c.first_name,' ',c.last_name) AS customer_name, c.mobile,
                       CONCAT(s.first_name,' ',s.last_name) AS staff_name
                FROM appointments a
                JOIN customers c ON c.id = a.customer_id
                JOIN staff s     ON s.id = a.staff_id
                WHERE a.starts_at > NOW() AND a.status IN ('PENDING','CONFIRMED')";
        $params = [];
        if ($branchId) {
            $sql .= ' AND a.branch_id = :b';
            $params['b'] = $branchId;
        }
        return $this->db->select($sql . " ORDER BY a.starts_at LIMIT {$limit}", $params);
    }

    /**
     * Overlap detection for a staff member. Buffer is included in the stored range comparison.
     * Uses FOR UPDATE inside a transaction to prevent race-condition double booking.
     */
    public function conflictsForStaff(int $staffId, string $startsAt, string $endsAt, ?int $excludeId = null, bool $lock = false): array
    {
        $sql = "SELECT id, code, starts_at, ends_at, status FROM appointments
                WHERE staff_id = :s
                  AND status NOT IN ('CANCELLED','NO_SHOW')
                  AND starts_at < :ends AND ends_at > :starts";
        $params = ['s' => $staffId, 'starts' => $startsAt, 'ends' => $endsAt];
        if ($excludeId) {
            $sql .= ' AND id <> :ex';
            $params['ex'] = $excludeId;
        }
        if ($lock) {
            $sql .= $this->db->forUpdate();
        }
        return $this->db->select($sql, $params);
    }

    public function conflictsForResource(int $resourceId, string $startsAt, string $endsAt, ?int $excludeId = null, bool $lock = false): array
    {
        $sql = "SELECT id, code FROM appointments
                WHERE resource_id = :r
                  AND status NOT IN ('CANCELLED','NO_SHOW')
                  AND starts_at < :ends AND ends_at > :starts";
        $params = ['r' => $resourceId, 'starts' => $startsAt, 'ends' => $endsAt];
        if ($excludeId) {
            $sql .= ' AND id <> :ex';
            $params['ex'] = $excludeId;
        }
        if ($lock) {
            $sql .= $this->db->forUpdate();
        }
        return $this->db->select($sql, $params);
    }

    /** Busy intervals used by the availability engine. */
    public function busyIntervals(int $staffId, string $date): array
    {
        return $this->db->select(
            "SELECT starts_at, ends_at FROM appointments
             WHERE staff_id = :s AND appointment_date = :d AND status NOT IN ('CANCELLED','NO_SHOW')
             ORDER BY starts_at",
            ['s' => $staffId, 'd' => $date]
        );
    }

    public function addStatusHistory(int $appointmentId, ?string $from, string $to, ?int $userId, ?string $note = null): void
    {
        $this->db->insert('appointment_status_history', [
            'appointment_id' => $appointmentId,
            'from_status'    => $from,
            'to_status'      => $to,
            'note'           => $note,
            'changed_by'     => $userId,
        ]);
    }

    public function forCustomer(int $customerId, ?string $scope = null, int $limit = 50): array
    {
        $sql = "SELECT a.id, a.code, a.appointment_date, a.start_time, a.end_time, a.status, a.total_price,
                       CONCAT(s.first_name,' ',s.last_name) AS staff_name, b.name AS branch_name,
                       (SELECT GROUP_CONCAT(sv.name SEPARATOR '، ') FROM appointment_items ai
                        JOIN services sv ON sv.id = ai.service_id WHERE ai.appointment_id = a.id) AS services
                FROM appointments a
                JOIN staff s    ON s.id = a.staff_id
                JOIN branches b ON b.id = a.branch_id
                WHERE a.customer_id = :c";
        if ($scope === 'upcoming') {
            $sql .= " AND a.starts_at >= NOW() AND a.status NOT IN ('CANCELLED','COMPLETED')";
        } elseif ($scope === 'past') {
            $sql .= " AND (a.starts_at < NOW() OR a.status IN ('CANCELLED','COMPLETED'))";
        }
        return $this->db->select($sql . " ORDER BY a.appointment_date DESC, a.start_time DESC LIMIT {$limit}", ['c' => $customerId]);
    }

    public function dueReminders(int $hoursBefore = 24, int $limit = 200): array
    {
        return $this->db->select(
            "SELECT r.id AS reminder_id, r.channel, a.id, a.code, a.appointment_date, a.start_time,
                    a.starts_at, c.id AS customer_id, c.first_name, c.last_name, c.mobile,
                    CONCAT(s.first_name,' ',s.last_name) AS staff_name, b.name AS branch_name
             FROM appointment_reminders r
             JOIN appointments a ON a.id = r.appointment_id
             JOIN customers c    ON c.id = a.customer_id
             JOIN staff s        ON s.id = a.staff_id
             JOIN branches b     ON b.id = a.branch_id
             WHERE r.status = 'PENDING' AND r.scheduled_at <= NOW()
               AND a.status IN ('PENDING','CONFIRMED')
             ORDER BY r.scheduled_at LIMIT {$limit}"
        );
    }
}
