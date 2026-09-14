<?php
declare(strict_types=1);

namespace App\Controllers\Staff;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\Exceptions\BusinessException;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Jalali;
use App\Repositories\CustomerRepository;
use App\Repositories\StaffRepository;
use App\Services\AppointmentService;
use App\Services\CommissionService;

/**
 * Self-service portal for specialists. Every query is scoped to the signed-in
 * user's own staff record — a specialist can never read another's data.
 */
final class PortalController extends BaseController
{
    private StaffRepository $staff;

    public function __construct()
    {
        $this->staff = new StaffRepository();
    }

    public function index(Request $request): Response
    {
        $me    = $this->me();
        $today = date('Y-m-d');
        $db    = Database::instance();

        $monthStart = date('Y-m-01');
        $counts = $db->selectOne(
            "SELECT
                COUNT(CASE WHEN appointment_date = :today1 THEN 1 END) AS today_count,
                COUNT(CASE WHEN appointment_date = :today2 AND status = 'COMPLETED' THEN 1 END) AS today_done,
                COUNT(CASE WHEN appointment_date BETWEEN :ms1 AND :today3 THEN 1 END) AS month_count,
                COALESCE(SUM(CASE WHEN appointment_date BETWEEN :ms2 AND :today4 AND status = 'COMPLETED' THEN total_price END), 0) AS month_revenue
             FROM appointments WHERE staff_id = :s",
            [
                'today1' => $today, 'today2' => $today, 'today3' => $today, 'today4' => $today,
                'ms1' => $monthStart, 'ms2' => $monthStart, 's' => $me['id'],
            ]
        ) ?? [];

        $commission = (new CommissionService())->summary((int)$me['id'], date('Y-m'));

        return $this->view('staff/dashboard', [
            'title'      => 'میز کار من',
            'me'         => $me,
            'today'      => $this->staff->todaySchedule((int)$me['id'], $today),
            'todayFa'    => Jalali::format($today, 'l j F Y'),
            'kpis'       => [
                'today_count'   => (int)($counts['today_count'] ?? 0),
                'today_done'    => (int)($counts['today_done'] ?? 0),
                'month_count'   => (int)($counts['month_count'] ?? 0),
                'month_revenue' => (float)($counts['month_revenue'] ?? 0),
                'commission'    => (float)($commission['total'] ?? 0),
                'rating'        => (float)$me['rating'],
            ],
            'upcoming'   => $db->select(
                "SELECT a.id, a.code, a.appointment_date, a.start_time, a.status, a.total_price,
                        CONCAT(c.first_name,' ',c.last_name) AS customer_name, c.mobile,
                        (SELECT GROUP_CONCAT(s.name SEPARATOR '، ') FROM appointment_items ai
                         JOIN services s ON s.id = ai.service_id WHERE ai.appointment_id = a.id) AS services
                 FROM appointments a JOIN customers c ON c.id = a.customer_id
                 WHERE a.staff_id = :s AND a.appointment_date > :today
                   AND a.status IN ('PENDING','CONFIRMED')
                 ORDER BY a.appointment_date, a.start_time LIMIT 10",
                ['s' => $me['id'], 'today' => $today]
            ),
            'statuses'   => AppointmentService::STATUSES,
        ]);
    }

    public function schedule(Request $request): Response
    {
        $me   = $this->me();
        $from = $request->str('from') ?: date('Y-m-d');
        if (strtotime($from) === false) {
            $from = date('Y-m-d');
        }
        $to = date('Y-m-d', strtotime($from . ' +6 days'));

        $days = [];
        for ($d = 0; $d < 7; $d++) {
            $date = date('Y-m-d', strtotime($from . " +{$d} days"));
            $days[$date] = [
                'date'      => $date,
                'label'     => Jalali::format($date, 'l j F'),
                'is_today'  => $date === date('Y-m-d'),
                'slots'     => [],
            ];
        }
        foreach (Database::instance()->select(
            "SELECT a.id, a.code, a.appointment_date, a.start_time, a.end_time, a.status,
                    CONCAT(c.first_name,' ',c.last_name) AS customer_name
             FROM appointments a JOIN customers c ON c.id = a.customer_id
             WHERE a.staff_id = :s AND a.appointment_date BETWEEN :f AND :t
               AND a.status <> 'CANCELLED'
             ORDER BY a.appointment_date, a.start_time",
            ['s' => $me['id'], 'f' => $from, 't' => $to]
        ) as $row) {
            $days[$row['appointment_date']]['slots'][] = $row;
        }

        return $this->view('staff/schedule', [
            'title'    => 'برنامه هفتگی من',
            'me'       => $me,
            'days'     => array_values($days),
            'from'     => $from,
            'prev'     => date('Y-m-d', strtotime($from . ' -7 days')),
            'next'     => date('Y-m-d', strtotime($from . ' +7 days')),
            'weekly'   => $this->staff->weeklyShifts((int)$me['id']),
            'leaves'   => Database::instance()->select(
                "SELECT start_date, end_date, type, status FROM leaves
                 WHERE staff_id = :s AND end_date >= CURDATE() ORDER BY start_date LIMIT 10",
                ['s' => $me['id']]
            ),
            'statuses' => AppointmentService::STATUSES,
        ]);
    }

    public function appointments(Request $request): Response
    {
        $me     = $this->me();
        $db     = Database::instance();
        $status = $request->str('status');
        $date   = $request->str('date');
        $page   = $request->page();
        $per    = $request->perPage();

        $where  = 'WHERE a.staff_id = :s';
        $params = ['s' => $me['id']];
        if ($status !== '' && isset(AppointmentService::STATUSES[$status])) {
            $where .= ' AND a.status = :st';
            $params['st'] = $status;
        }
        if ($date !== '' && strtotime($date) !== false) {
            $where .= ' AND a.appointment_date = :d';
            $params['d'] = $date;
        }

        $total = (int)$db->scalar("SELECT COUNT(*) FROM appointments a {$where}", $params);
        $rows  = $db->select(
            "SELECT a.id, a.code, a.appointment_date, a.start_time, a.end_time, a.status, a.total_price, a.notes,
                    a.customer_id, CONCAT(c.first_name,' ',c.last_name) AS customer_name, c.mobile,
                    (SELECT GROUP_CONCAT(s.name SEPARATOR '، ') FROM appointment_items ai
                     JOIN services s ON s.id = ai.service_id WHERE ai.appointment_id = a.id) AS services
             FROM appointments a JOIN customers c ON c.id = a.customer_id
             {$where} ORDER BY a.appointment_date DESC, a.start_time DESC
             LIMIT {$per} OFFSET " . (($page - 1) * $per),
            $params
        );
        foreach ($rows as &$r) {
            $r['date_fa'] = Jalali::format($r['appointment_date'], 'j F Y');
        }
        unset($r);

        return $this->view('staff/appointments', [
            'title'       => 'نوبت‌های من',
            'me'          => $me,
            'appointments' => $rows,
            'statuses'    => AppointmentService::STATUSES,
            'transitions' => AppointmentService::TRANSITIONS,
            'filters'     => ['status' => $status, 'date' => $date],
            'page'        => $page,
            'lastPage'    => max(1, (int)ceil($total / $per)),
            'total'       => $total,
        ]);
    }

    public function changeStatus(Request $request, string $id): Response
    {
        $me            = $this->me();
        $appointmentId = (int)$id;
        $owner = (int)Database::instance()->scalar(
            'SELECT staff_id FROM appointments WHERE id = :id',
            ['id' => $appointmentId]
        );
        if ($owner !== (int)$me['id']) {
            return $this->fail('FORBIDDEN', 'این نوبت متعلق به شما نیست.', 403);
        }

        try {
            $result = (new AppointmentService())->changeStatus(
                $appointmentId,
                strtoupper($request->str('status')),
                $request->str('note') ?: null,
                $this->userId()
            );
        } catch (BusinessException $e) {
            if ($request->wantsJson()) {
                return $this->fail($e->errorCode(), $e->getMessage(), 422);
            }
            return $this->back('error', $e->getMessage());
        }

        if ($request->wantsJson()) {
            return $this->json($result);
        }
        return $this->back('success', 'وضعیت نوبت به‌روزرسانی شد.');
    }

    public function commissions(Request $request): Response
    {
        $me      = $this->me();
        $period  = $request->str('period') ?: date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $period = date('Y-m');
        }
        $service = new CommissionService();

        $periods = [];
        for ($i = 0; $i < 12; $i++) {
            $p = date('Y-m', strtotime("-{$i} months"));
            $periods[$p] = Jalali::format($p . '-01', 'F Y');
        }

        return $this->view('staff/commissions', [
            'title'       => 'پورسانت من',
            'me'          => $me,
            'period'      => $period,
            'periods'     => $periods,
            'summary'     => $service->summary((int)$me['id'], $period),
            'commissions' => $service->listFor((int)$me['id'], $period, 200),
        ]);
    }

    public function performance(Request $request): Response
    {
        $me   = $this->me();
        $from = $request->str('from') ?: date('Y-m-01', strtotime('-2 months'));
        $to   = $request->str('to') ?: date('Y-m-d');
        $db   = Database::instance();

        return $this->view('staff/performance', [
            'title'       => 'عملکرد من',
            'me'          => $me,
            'from'        => $from,
            'to'          => $to,
            'stats'       => $this->staff->performance((int)$me['id'], $from, $to),
            'evaluations' => $db->select(
                "SELECT e.id, e.period_start, e.period_end, e.total_score, e.level, e.status, e.created_at,
                        CONCAT(u.first_name,' ',u.last_name) AS evaluator
                 FROM performance_evaluations e
                 LEFT JOIN users u ON u.id = e.evaluated_by
                 WHERE e.staff_id = :s AND e.status = 'FINAL'
                 ORDER BY e.id DESC LIMIT 12",
                ['s' => $me['id']]
            ),
            'reviews'     => $db->select(
                "SELECT r.rating, r.comment, r.created_at, s.name AS service_name
                 FROM reviews r LEFT JOIN services s ON s.id = r.service_id
                 WHERE r.staff_id = :s AND r.status = 'APPROVED'
                 ORDER BY r.id DESC LIMIT 15",
                ['s' => $me['id']]
            ),
            'topServices' => $db->select(
                "SELECT s.name, COUNT(*) AS times, COALESCE(SUM(ai.price),0) AS revenue
                 FROM appointment_items ai
                 JOIN appointments a ON a.id = ai.appointment_id
                 JOIN services s ON s.id = ai.service_id
                 WHERE a.staff_id = :s AND a.status = 'COMPLETED' AND a.appointment_date BETWEEN :f AND :t
                 GROUP BY s.id, s.name ORDER BY times DESC LIMIT 8",
                ['s' => $me['id'], 'f' => $from, 't' => $to]
            ),
        ]);
    }

    /** A specialist may only open customers they have actually served. */
    public function customer(Request $request, string $id): Response
    {
        $me         = $this->me();
        $customerId = (int)$id;

        $served = (int)Database::instance()->scalar(
            'SELECT COUNT(*) FROM appointments WHERE staff_id = :s AND customer_id = :c',
            ['s' => $me['id'], 'c' => $customerId]
        );
        if ($served === 0) {
            return $this->fail('FORBIDDEN', 'شما سابقه‌ای با این مشتری ندارید.', 403);
        }

        $repo     = new CustomerRepository();
        $customer = $repo->findFull($customerId);
        if ($customer === null) {
            $this->notFound('مشتری یافت نشد.');
        }

        return $this->view('staff/customer', [
            'title'        => $customer['first_name'] . ' ' . $customer['last_name'],
            'me'           => $me,
            'customer'     => $customer,
            'appointments' => Database::instance()->select(
                "SELECT a.id, a.code, a.appointment_date, a.start_time, a.status, a.total_price, a.notes,
                        (SELECT GROUP_CONCAT(s.name SEPARATOR '، ') FROM appointment_items ai
                         JOIN services s ON s.id = ai.service_id WHERE ai.appointment_id = a.id) AS services
                 FROM appointments a WHERE a.customer_id = :c AND a.staff_id = :s
                 ORDER BY a.appointment_date DESC LIMIT 30",
                ['c' => $customerId, 's' => $me['id']]
            ),
            'services'     => $repo->services($customerId, 20),
            'statuses'     => AppointmentService::STATUSES,
        ]);
    }

    /* ====================================================== internals == */

    private function me(): array
    {
        $record = $this->staff->findByUserId((int)$this->userId());
        if ($record === null) {
            $this->notFound('برای حساب شما پرونده پرسنلی ثبت نشده است. با مدیر سالن تماس بگیرید.');
        }
        return $record;
    }
}
