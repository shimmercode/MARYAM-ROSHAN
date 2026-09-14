<?php
declare(strict_types=1);

namespace App\Controllers\Public_;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Jalali;
use App\Repositories\CustomerRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\StaffRepository;
use App\Services\AppointmentService;
use App\Services\LoyaltyService;
use App\Validators\Validator;

/** Public online booking wizard: service → specialist → slot → details. */
final class BookingController extends BaseController
{
    private AppointmentService $appointments;

    public function __construct()
    {
        $this->appointments = new AppointmentService();
    }

    public function index(Request $request): Response
    {
        $rules = $this->appointments->bookingRules();

        return $this->view('public/booking', [
            'title'       => 'رزرو آنلاین نوبت | سالن زیبایی مریم روشن',
            'description' => 'در چند ثانیه نوبت خود را آنلاین رزرو کنید.',
            'categories'  => (new ServiceRepository())->categories(),
            'services'    => Database::instance()->select(
                "SELECT s.id, s.name, s.slug, s.price, s.duration_minutes, s.category_id, c.name AS category_name
                 FROM services s JOIN service_categories c ON c.id = s.category_id
                 WHERE s.status = 'ACTIVE' AND s.online_booking = 1 AND s.deleted_at IS NULL
                 ORDER BY c.sort_order, s.name"
            ),
            'branches'    => Database::instance()->select(
                "SELECT id, name, address FROM branches WHERE status = 'ACTIVE' AND deleted_at IS NULL ORDER BY id"
            ),
            'staff'       => (new StaffRepository())->activeList(),
            'rules'       => $rules,
            'maxDate'     => date('Y-m-d', strtotime('+' . (int)$rules['max_advance_days'] . ' days')),
            'preselect'   => $request->str('service'),
        ]);
    }

    /** Free slots for the chosen service set — used by the wizard (AJAX). */
    public function slots(Request $request): Response
    {
        $data = Validator::validate($request->all(), [
            'branch_id'   => 'required|int|exists:branches,id',
            'date'        => 'required|date',
            'service_ids' => 'required|array',
            'staff_id'    => 'nullable|int|exists:staff,id',
        ], ['branch_id' => 'شعبه', 'date' => 'تاریخ', 'service_ids' => 'خدمات']);

        $rules = $this->appointments->bookingRules((int)$data['branch_id']);
        if ((int)$rules['allow_online_booking'] !== 1) {
            return $this->fail('ONLINE_BOOKING_DISABLED', 'رزرو آنلاین در حال حاضر غیرفعال است.', 422);
        }
        if (strtotime((string)$data['date']) > strtotime('+' . (int)$rules['max_advance_days'] . ' days')) {
            return $this->fail('DATE_TOO_FAR', 'امکان رزرو در این بازه زمانی وجود ندارد.', 422);
        }

        $repo       = new ServiceRepository();
        $serviceIds = array_map('intval', (array)$data['service_ids']);
        $candidates = isset($data['staff_id'])
            ? [(int)$data['staff_id']]
            : $this->staffCapableOfAll($serviceIds, (int)$data['branch_id']);

        if ($candidates === []) {
            return $this->json(['staff' => [], 'message' => 'متخصصی برای ترکیب خدمات انتخابی در دسترس نیست.']);
        }

        $out = [];
        foreach ($candidates as $staffId) {
            $duration = 0;
            $buffer   = 0;
            foreach ($serviceIds as $sid) {
                $duration += $repo->durationFor($sid, $staffId);
                $svc = $repo->find($sid);
                $buffer = max($buffer, (int)($svc['buffer_minutes'] ?? 0));
            }
            if ($duration <= 0) {
                continue;
            }
            $slots = $this->appointments->availableSlots(
                $staffId,
                (int)$data['branch_id'],
                (string)$data['date'],
                $duration,
                $buffer
            );
            if ($slots === []) {
                continue;
            }
            $staff = Database::instance()->selectOne(
                "SELECT id, CONCAT(first_name,' ',last_name) AS name, job_title, avatar, rating FROM staff WHERE id = :s",
                ['s' => $staffId]
            );
            $out[] = ['staff' => $staff, 'slots' => $slots, 'duration' => $duration];
        }

        return $this->json([
            'staff'  => $out,
            'date'   => $data['date'],
            'jalali' => Jalali::format((string)$data['date'], 'l j F Y'),
        ]);
    }

    public function store(Request $request): Response
    {
        $data = Validator::validate($request->all(), [
            'first_name'  => 'required|string|max:80',
            'last_name'   => 'required|string|max:80',
            'mobile'      => 'required|mobile',
            'branch_id'   => 'required|int|exists:branches,id',
            'staff_id'    => 'required|int|exists:staff,id',
            'date'        => 'required|date',
            'time'        => 'required|time',
            'service_ids' => 'required|array',
            'notes'       => 'nullable|string|max:500',
        ], [
            'first_name' => 'نام', 'last_name' => 'نام خانوادگی', 'mobile' => 'موبایل',
            'branch_id' => 'شعبه', 'staff_id' => 'متخصص', 'date' => 'تاریخ', 'time' => 'ساعت',
            'service_ids' => 'خدمات',
        ]);

        $db   = Database::instance();
        $repo = new CustomerRepository();

        // Find or create the customer (public booking must not require an account).
        $customer = $repo->findByMobile((string)$data['mobile']);
        if ($customer === null) {
            $customerId = $db->transaction(function () use ($db, $repo, $data) {
                $id = $db->insert('customers', [
                    'code'                => $repo->nextCode(),
                    'first_name'          => $data['first_name'],
                    'last_name'           => $data['last_name'],
                    'mobile'              => $data['mobile'],
                    'preferred_branch_id' => (int)$data['branch_id'],
                    'source'              => 'WEBSITE',
                    'status'              => 'ACTIVE',
                ]);
                $db->insert('customer_loyalty', ['customer_id' => $id, 'points_balance' => 0]);
                $db->insert('wallet_accounts', ['customer_id' => $id, 'balance' => '0.00']);
                (new LoyaltyService($db))->syncTier($id, 0);
                return $id;
            });
        } else {
            $customerId = (int)$customer['id'];
            if ((string)$customer['status'] === 'BLACKLIST') {
                return $this->fail('CUSTOMER_BLOCKED', 'امکان رزرو آنلاین برای این شماره وجود ندارد. لطفاً تماس بگیرید.', 403);
            }
        }

        $appointment = $this->appointments->create([
            'customer_id' => $customerId,
            'staff_id'    => (int)$data['staff_id'],
            'branch_id'   => (int)$data['branch_id'],
            'date'        => (string)$data['date'],
            'time'        => substr((string)$data['time'], 0, 5),
            'service_ids' => array_map('intval', (array)$data['service_ids']),
            'notes'       => $data['notes'] ?? null,
            'status'      => 'PENDING',
            'source'      => 'ONLINE',
        ]);

        if ($request->wantsJson()) {
            return $this->json(['code' => $appointment['code'], 'redirect' => url('/booking/success/' . $appointment['code'])]);
        }
        return $this->redirect('/booking/success/' . $appointment['code']);
    }

    public function success(Request $request, string $code): Response
    {
        $appointment = Database::instance()->selectOne(
            "SELECT a.code, a.appointment_date, a.start_time, a.status, a.total_price,
                    CONCAT(c.first_name,' ',c.last_name) AS customer_name,
                    CONCAT(s.first_name,' ',s.last_name) AS staff_name,
                    b.name AS branch_name, b.address, b.phone,
                    (SELECT GROUP_CONCAT(sv.name SEPARATOR '، ') FROM appointment_items ai
                     JOIN services sv ON sv.id = ai.service_id WHERE ai.appointment_id = a.id) AS services
             FROM appointments a
             JOIN customers c ON c.id = a.customer_id
             JOIN staff s     ON s.id = a.staff_id
             JOIN branches b  ON b.id = a.branch_id
             WHERE a.code = :c",
            ['c' => $code]
        );
        if ($appointment === null) {
            $this->notFound('نوبت مورد نظر یافت نشد.');
        }

        return $this->view('public/booking_success', [
            'title'       => 'نوبت شما ثبت شد',
            'description' => 'جزئیات نوبت رزروشده در سالن زیبایی مریم روشن.',
            'appointment' => $appointment,
        ]);
    }

    /** @return int[] staff ids able to perform every requested service */
    private function staffCapableOfAll(array $serviceIds, int $branchId): array
    {
        if ($serviceIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
        $params       = $serviceIds;
        $params[]     = count($serviceIds);
        $params[]     = $branchId;

        $rows = Database::instance()->select(
            "SELECT ss.staff_id
             FROM staff_services ss
             JOIN staff s ON s.id = ss.staff_id
             WHERE ss.service_id IN ({$placeholders})
               AND s.status = 'ACTIVE' AND s.online_booking = 1 AND s.deleted_at IS NULL
             GROUP BY ss.staff_id
             HAVING COUNT(DISTINCT ss.service_id) = ?
                AND MAX(s.branch_id) = ?",
            $params
        );
        return array_map(static fn ($r) => (int)$r['staff_id'], $rows);
    }
}
