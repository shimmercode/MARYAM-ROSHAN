<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;

/** Calculates operational status from persisted attendance, shifts, presence and service sessions. */
final class LiveStatusService
{
    private const STALE_SECONDS = 45;
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    public function clockIn(int $staffId): void
    {
        $this->db->run("INSERT INTO attendance (staff_id, work_date, check_in, status) VALUES (:s, CURDATE(), NOW(), 'PRESENT') ON DUPLICATE KEY UPDATE check_in = COALESCE(check_in, NOW()), status = 'PRESENT'", ['s' => $staffId]);
        $this->event($staffId, 'CLOCK_IN');
    }

    public function clockOut(int $staffId): void
    {
        $this->db->execute("UPDATE attendance SET check_out = NOW(), worked_minutes = TIMESTAMPDIFF(MINUTE, check_in, NOW()) WHERE staff_id = :s AND work_date = CURDATE()", ['s' => $staffId]);
        $this->db->run("INSERT INTO staff_presence (staff_id, online_status, last_activity_at) VALUES (:s, 0, NOW()) ON DUPLICATE KEY UPDATE online_status = 0, last_activity_at = NOW()", ['s' => $staffId]);
        $this->event($staffId, 'CLOCK_OUT');
    }

    public function startBreak(int $staffId, ?string $reason = null): void
    {
        $this->db->execute("UPDATE staff_breaks SET ended_at = NOW() WHERE staff_id = :s AND ended_at IS NULL", ['s' => $staffId]);
        $this->db->insert('staff_breaks', ['staff_id' => $staffId, 'reason' => $reason]);
        $this->event($staffId, 'BREAK_STARTED', ['reason' => $reason]);
    }

    public function endBreak(int $staffId): void
    {
        $this->db->execute("UPDATE staff_breaks SET ended_at = NOW() WHERE staff_id = :s AND ended_at IS NULL", ['s' => $staffId]);
        $this->event($staffId, 'BREAK_ENDED');
    }

    public function heartbeat(int $staffId, Request $request): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->run(
            "INSERT INTO staff_presence (staff_id, online_status, last_activity_at, last_ip)
             VALUES (:s, 1, :at, :ip)
             ON DUPLICATE KEY UPDATE online_status = 1, last_activity_at = VALUES(last_activity_at),
             last_ip = VALUES(last_ip), updated_at = CURRENT_TIMESTAMP",
            ['s' => $staffId, 'at' => $now, 'ip' => $request->ip()]
        );
        $this->event($staffId, 'HEARTBEAT');
    }

    public function startService(int $staffId, int $appointmentId, ?int $serviceId = null, ?int $seatId = null): void
    {
        $appointment = $this->db->selectOne('SELECT customer_id, branch_id FROM appointments WHERE id = :id AND staff_id = :staff LIMIT 1', ['id' => $appointmentId, 'staff' => $staffId]);
        if (!$appointment) throw new \RuntimeException('نوبت متعلق به این پرسنل نیست.');
        $this->db->execute("UPDATE staff_service_sessions SET status = 'COMPLETED', ended_at = NOW() WHERE staff_id = :s AND status = 'ACTIVE'", ['s' => $staffId]);
        $this->db->insert('staff_service_sessions', ['staff_id' => $staffId, 'appointment_id' => $appointmentId, 'customer_id' => $appointment['customer_id'], 'service_id' => $serviceId, 'seat_id' => $seatId, 'started_at' => date('Y-m-d H:i:s'), 'status' => 'ACTIVE']);
        $this->db->execute("UPDATE appointments SET status = 'IN_PROGRESS' WHERE id = :id AND staff_id = :s", ['id' => $appointmentId, 's' => $staffId]);
        $this->event($staffId, 'SERVICE_STARTED', ['appointment_id' => $appointmentId, 'seat_id' => $seatId]);
    }

    public function endService(int $staffId, int $appointmentId): void
    {
        $this->db->execute("UPDATE staff_service_sessions SET status = 'COMPLETED', ended_at = NOW() WHERE staff_id = :s AND appointment_id = :a AND status = 'ACTIVE'", ['s' => $staffId, 'a' => $appointmentId]);
        $this->db->execute("UPDATE appointments SET status = 'COMPLETED' WHERE id = :id AND staff_id = :s", ['id' => $appointmentId, 's' => $staffId]);
        $this->event($staffId, 'SERVICE_ENDED', ['appointment_id' => $appointmentId]);
    }

    public function statuses(?int $branchId = null, ?string $since = null): array
    {
        $where = "s.deleted_at IS NULL AND s.status = 'ACTIVE'";
        $params = [];
        if ($branchId !== null) { $where .= ' AND s.branch_id = :branch'; $params['branch'] = $branchId; }
        $rows = $this->db->select(
            "SELECT s.id AS staff_id, s.first_name, s.last_name, s.branch_id, b.name AS branch_name,
                    p.online_status, p.last_activity_at, a.status AS attendance_status,
                    sh.start_time AS shift_start, sh.end_time AS shift_end,
                    ss.id AS session_id, ss.customer_id, ss.service_id, ss.started_at, ss.expected_end_at,
                    brk.id AS break_id, seat.code AS seat_code, sa.status AS seat_assignment_status,
                    CONCAT(c.first_name, ' ', c.last_name) AS customer_name, svc.name AS service_name
             FROM staff s
             LEFT JOIN branches b ON b.id = s.branch_id
             LEFT JOIN staff_presence p ON p.staff_id = s.id
             LEFT JOIN attendance a ON a.staff_id = s.id AND a.work_date = CURDATE()
             LEFT JOIN staff_shifts sh ON sh.staff_id = s.id AND sh.shift_date = CURDATE()
             LEFT JOIN staff_service_sessions ss ON ss.staff_id = s.id AND ss.status = 'ACTIVE'
             LEFT JOIN staff_breaks brk ON brk.staff_id = s.id AND brk.ended_at IS NULL
             LEFT JOIN seat_assignments sa ON sa.staff_id = s.id AND sa.status = 'OCCUPIED'
             LEFT JOIN seats seat ON seat.id = sa.seat_id
             LEFT JOIN customers c ON c.id = ss.customer_id
             LEFT JOIN services svc ON svc.id = ss.service_id
             WHERE {$where}
             ORDER BY s.first_name, s.last_name",
            $params
        );
        $now = time(); $out = [];
        foreach ($rows as $r) {
            $activeSession = $r['session_id'] !== null;
            $online = $r['last_activity_at'] !== null && ($now - strtotime((string)$r['last_activity_at'])) <= self::STALE_SECONDS;
            $inShift = $this->inShift($r['shift_start'], $r['shift_end']);
            $attendance = (string)($r['attendance_status'] ?? 'ABSENT');
            $status = 'ABSENT'; $label = 'غایب';
            if ($attendance === 'LEAVE') { $status = 'LEAVE'; $label = 'مرخصی'; }
            elseif (!$inShift && $online) { $status = 'OFF_SHIFT'; $label = 'خارج از شیفت'; }
            elseif (!$inShift && $attendance === 'PRESENT') { $status = 'OFF_SHIFT'; $label = 'خارج از شیفت'; }
            elseif ($r['break_id'] !== null) { $status = 'BREAK'; $label = 'در استراحت'; }
            elseif ($activeSession) { $status = 'SERVING'; $label = 'در حال ارائه خدمت'; }
            elseif ($inShift && $online && $attendance === 'PRESENT') { $status = 'AVAILABLE'; $label = 'آزاد / آماده خدمت'; }
            elseif ($inShift && $attendance === 'PRESENT') { $status = 'ATTENTION'; $label = 'نیازمند بررسی'; }
            $r['status'] = $status; $r['status_label'] = $label;
            $r['online'] = $online; $r['in_shift'] = $inShift;
            $r['last_activity_age'] = $r['last_activity_at'] ? max(0, $now - strtotime((string)$r['last_activity_at'])) : null;
            if ($since === null || $r['last_activity_at'] === null || $r['last_activity_at'] > $since) $out[] = $r;
        }
        return $out;
    }

    public function summary(?int $branchId = null): array
    {
        $rows = $this->statuses($branchId);
        $keys = ['PRESENT' => 0, 'SERVING' => 0, 'AVAILABLE' => 0, 'BREAK' => 0, 'ABSENT' => 0, 'ATTENTION' => 0, 'OFF_SHIFT' => 0];
        foreach ($rows as $r) { $keys[$r['status']] = ($keys[$r['status']] ?? 0) + 1; if ($r['attendance_status'] === 'PRESENT') $keys['PRESENT']++; }
        return ['staff' => $keys, 'total' => count($rows), 'updated_at' => date(DATE_ATOM)];
    }

    public function activities(?int $branchId = null, int $limit = 20): array
    {
        $branch = $branchId === null ? '' : ' AND s.branch_id = :branch';
        $params = $branchId === null ? [] : ['branch' => $branchId];
        return $this->db->select("SELECT e.id, e.event_type, e.created_at, e.payload,
                CONCAT(s.first_name, ' ', s.last_name) AS staff_name
            FROM operational_events e LEFT JOIN staff s ON s.id = e.staff_id
            WHERE 1=1 {$branch} ORDER BY e.id DESC LIMIT " . max(1, min(100, $limit)), $params);
    }

    public function alerts(?int $branchId = null): array
    {
        $alerts = [];
        foreach ($this->statuses($branchId) as $row) {
            if ($row['status'] === 'ATTENTION') $alerts[] = ['type' => 'INACTIVE_STAFF', 'severity' => 'warning', 'staff_id' => $row['staff_id'], 'message' => 'فعالیت پرسنل در شیفت ثبت نشده است.', 'staff_name' => trim($row['first_name'] . ' ' . $row['last_name'])];
            if ($row['in_shift'] && $row['seat_code'] === null && $row['status'] !== 'ABSENT') $alerts[] = ['type' => 'EMPTY_SEAT', 'severity' => 'warning', 'staff_id' => $row['staff_id'], 'message' => 'جایگاه پرسنل بدون تخصیص فعال است.', 'staff_name' => trim($row['first_name'] . ' ' . $row['last_name'])];
        }
        return $alerts;
    }

    private function inShift(?string $start, ?string $end): bool
    {
        if (!$start || !$end) return false;
        $now = date('H:i:s');
        return $start <= $end ? ($now >= $start && $now <= $end) : ($now >= $start || $now <= $end);
    }

    private function event(int $staffId, string $type, array $payload = []): void
    {
        $this->db->insert('operational_events', ['staff_id' => $staffId, 'event_type' => $type, 'payload' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null]);
    }
}
