<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\Exceptions\BusinessException;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Jalali;
use App\Repositories\CustomerRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\StaffRepository;
use App\Services\AppointmentService;
use App\Services\LoyaltyService;
use App\Validators\Validator;

/**
 * Unauthenticated read-mostly API for the public website / future mobile app.
 * Only data that is already published on the website is exposed here, and the
 * single write endpoint (book) is rate-limited in routes/api.php.
 */
final class PublicApiController extends BaseController
{
    public function services(Request $request): Response
    {
        $repo     = new ServiceRepository();
        $category = $request->str('category');

        $sql = "SELECT s.id, s.name, s.slug, s.short_description, s.price, s.duration_minutes,
                       s.image, s.is_featured, s.online_booking, c.name AS category_name, c.slug AS category_slug
                FROM services s JOIN service_categories c ON c.id = s.category_id
                WHERE s.status = 'ACTIVE' AND s.deleted_at IS NULL";
        $params = [];
        if ($category !== '') {
            $sql .= ' AND c.slug = :cat';
            $params['cat'] = $category;
        }
        $sql .= ' ORDER BY c.sort_order, s.name LIMIT 200';

        return $this->json([
            'items'      => Database::instance()->select($sql, $params),
            'categories' => $repo->categories(),
        ]);
    }

    public function staff(Request $request): Response
    {
        $serviceId = $request->int('service_id');

        return $this->json([
            'items' => $serviceId
                ? (new ServiceRepository())->staffFor($serviceId, $request->int('branch_id'))
                : (new StaffRepository())->publicTeam(),
        ]);
    }

    public function branches(Request $request): Response
    {
        return $this->json([
            'items' => Database::instance()->select(
                "SELECT id, name, phone, address, opening_time, closing_time, working_days, latitude, longitude
                 FROM branches WHERE status = 'ACTIVE' AND deleted_at IS NULL ORDER BY id"
            ),
        ]);
    }

    public function slots(Request $request): Response
    {
        $data = Validator::validate($request->all(), [
            'branch_id'  => 'required|int|exists:branches,id',
            'service_id' => 'required|int|exists:services,id',
            'date'       => 'required|date',
            'staff_id'   => 'nullable|int|exists:staff,id',
        ], ['branch_id' => 'شعبه', 'service_id' => 'خدمت', 'date' => 'تاریخ']);

        $service = new AppointmentService();
        $rules   = $service->bookingRules((int)$data['branch_id']);
        if ((int)$rules['allow_online_booking'] !== 1) {
            return $this->fail('ONLINE_BOOKING_DISABLED', 'رزرو آنلاین در حال حاضر غیرفعال است.', 422);
        }

        $repo      = new ServiceRepository();
        $serviceId = (int)$data['service_id'];
        $candidates = isset($data['staff_id']) && $data['staff_id']
            ? [(int)$data['staff_id']]
            : array_map(static fn ($s) => (int)$s['id'], $repo->staffFor($serviceId, (int)$data['branch_id']));

        $out = [];
        foreach ($candidates as $staffId) {
            $slots = $service->availableSlots(
                $staffId,
                (int)$data['branch_id'],
                (string)$data['date'],
                $repo->durationFor($serviceId, $staffId)
            );
            if ($slots === []) {
                continue;
            }
            $out[] = [
                'staff' => Database::instance()->selectOne(
                    "SELECT id, CONCAT(first_name,' ',last_name) AS name, job_title, avatar, rating
                     FROM staff WHERE id = :s",
                    ['s' => $staffId]
                ),
                'slots' => $slots,
            ];
        }

        return $this->json([
            'staff'  => $out,
            'date'   => $data['date'],
            'jalali' => Jalali::format((string)$data['date'], 'l j F Y'),
        ]);
    }

    public function book(Request $request): Response
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
            'branch_id' => 'شعبه', 'staff_id' => 'متخصص', 'date' => 'تاریخ',
            'time' => 'ساعت', 'service_ids' => 'خدمات',
        ]);

        $db       = Database::instance();
        $repo     = new CustomerRepository();
        $customer = $repo->findByMobile((string)$data['mobile']);

        if ($customer === null) {
            $customerId = $db->transaction(function () use ($db, $repo, $data) {
                $id = $db->insert('customers', [
                    'code'                => $repo->nextCode(),
                    'first_name'          => $data['first_name'],
                    'last_name'           => $data['last_name'],
                    'mobile'              => $data['mobile'],
                    'preferred_branch_id' => (int)$data['branch_id'],
                    'source'              => 'API',
                    'status'              => 'ACTIVE',
                ]);
                $db->insert('customer_loyalty', ['customer_id' => $id, 'points_balance' => 0]);
                $db->insert('wallet_accounts', ['customer_id' => $id, 'balance' => '0.00']);
                (new LoyaltyService($db))->syncTier($id, 0);
                return $id;
            });
        } else {
            if ((string)$customer['status'] === 'BLACKLIST') {
                return $this->fail('CUSTOMER_BLOCKED', 'امکان رزرو آنلاین برای این شماره وجود ندارد.', 403);
            }
            $customerId = (int)$customer['id'];
        }

        try {
            $appointment = (new AppointmentService())->create([
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
        } catch (BusinessException $e) {
            return $this->fail($e->errorCode(), $e->getMessage(), $e->status());
        }

        return $this->json([
            'code'   => $appointment['code'],
            'status' => $appointment['status'] ?? 'PENDING',
            'url'    => url('/booking/success/' . $appointment['code']),
        ]);
    }
}
