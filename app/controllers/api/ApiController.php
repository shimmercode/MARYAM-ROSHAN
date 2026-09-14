<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\Exceptions\BusinessException;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Jalali;
use App\Repositories\AppointmentRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\StaffRepository;
use App\Services\AnalyticsService;
use App\Services\AppointmentService;
use App\Services\AuthService;
use App\Services\NotificationService;
use App\Validators\Validator;

/**
 * Authenticated internal API used by the admin/staff UIs.
 * Responses always use the {success,data}/{success,error} envelope built by
 * BaseController::json() and ::fail().
 */
final class ApiController extends BaseController
{
    public function me(Request $request): Response
    {
        $user = $this->user();
        if ($user === null) {
            return $this->fail('UNAUTHENTICATED', 'ابتدا وارد شوید.', 401);
        }

        return $this->json([
            'id'          => (int)$user['id'],
            'name'        => $user['first_name'] . ' ' . $user['last_name'],
            'mobile'      => $user['mobile'],
            'avatar'      => $user['avatar'] ?? null,
            'branch_id'   => $user['branch_id'] ?? null,
            'roles'       => $user['roles'] ?? [],
            'permissions' => AuthService::permissions(),
            'unread'      => NotificationService::unreadCount((int)$user['id']),
        ]);
    }

    /* ===================================================== customers === */

    public function customers(Request $request): Response
    {
        $this->authorize('customers.view');
        $search = $request->str('search');

        if ($search !== '') {
            return $this->json(['items' => (new CustomerRepository())->search($search, 15)]);
        }

        $result = (new CustomerRepository())->paginate(
            ['status' => $request->str('status'), 'tier_id' => $request->int('tier_id')],
            $request->page(),
            $request->perPage()
        );

        return $this->json(['items' => $result['data']], [
            'page'     => $result['page'],
            'lastPage' => $result['last_page'],
            'total'    => $result['total'],
        ]);
    }

    public function customer(Request $request, string $id): Response
    {
        $this->authorize('customers.view');
        $repo     = new CustomerRepository();
        $customer = $repo->findFull((int)$id);
        if ($customer === null) {
            return $this->fail('NOT_FOUND', 'مشتری یافت نشد.', 404);
        }

        return $this->json([
            'customer'     => $customer,
            'appointments' => $repo->appointments((int)$id, 10),
            'invoices'     => $repo->invoices((int)$id, 10),
            'services'     => $repo->services((int)$id, 10),
        ]);
    }

    /* ================================================== appointments === */

    public function appointments(Request $request): Response
    {
        $this->authorize('appointments.view');
        $from = $request->str('from') ?: date('Y-m-d');
        $to   = $request->str('to') ?: $from;

        return $this->json([
            'items' => (new AppointmentRepository())->calendar(
                $from,
                $to,
                $this->scopedBranchId() ?? $request->int('branch_id'),
                $request->int('staff_id')
            ),
            'statuses' => AppointmentService::STATUSES,
        ]);
    }

    public function appointment(Request $request, string $id): Response
    {
        $this->authorize('appointments.view');
        $repo        = new AppointmentRepository();
        $appointment = $repo->findFull((int)$id);
        if ($appointment === null) {
            return $this->fail('NOT_FOUND', 'نوبت یافت نشد.', 404);
        }
        return $this->json(['appointment' => $appointment]);
    }

    public function storeAppointment(Request $request): Response
    {
        $this->authorize('appointments.manage');
        $data = Validator::validate($request->all(), [
            'customer_id' => 'required|int|exists:customers,id',
            'staff_id'    => 'required|int|exists:staff,id',
            'branch_id'   => 'required|int|exists:branches,id',
            'date'        => 'required|date',
            'time'        => 'required|time',
            'service_ids' => 'required|array',
            'notes'       => 'nullable|string|max:500',
        ], ['customer_id' => 'مشتری', 'staff_id' => 'متخصص', 'date' => 'تاریخ', 'time' => 'ساعت']);

        try {
            $appointment = (new AppointmentService())->create([
                'customer_id' => (int)$data['customer_id'],
                'staff_id'    => (int)$data['staff_id'],
                'branch_id'   => (int)$data['branch_id'],
                'date'        => (string)$data['date'],
                'time'        => substr((string)$data['time'], 0, 5),
                'service_ids' => array_map('intval', (array)$data['service_ids']),
                'notes'       => $data['notes'] ?? null,
                'status'      => 'CONFIRMED',
                'source'      => 'ADMIN',
            ]);
        } catch (BusinessException $e) {
            return $this->fail($e->errorCode(), $e->getMessage(), 422);
        }

        return $this->json(['appointment' => $appointment]);
    }

    public function appointmentStatus(Request $request, string $id): Response
    {
        $this->authorize('appointments.manage');
        try {
            $result = (new AppointmentService())->changeStatus(
                (int)$id,
                strtoupper($request->str('status')),
                $request->str('note') ?: null,
                $this->userId()
            );
        } catch (BusinessException $e) {
            return $this->fail($e->errorCode(), $e->getMessage(), 422);
        }
        return $this->json($result);
    }

    /* ============================================ services and staff === */

    public function services(Request $request): Response
    {
        $this->authorize('services.view');
        $repo = new ServiceRepository();

        return $this->json([
            'items'      => $repo->activeList($request->int('branch_id')),
            'categories' => $repo->categories(),
        ]);
    }

    public function staff(Request $request): Response
    {
        $this->authorize('staff.view');
        $serviceId = $request->int('service_id');
        $repo      = new StaffRepository();

        return $this->json([
            'items' => $serviceId
                ? (new ServiceRepository())->staffFor($serviceId, $request->int('branch_id'))
                : $repo->activeList($this->scopedBranchId() ?? $request->int('branch_id')),
        ]);
    }

    public function slots(Request $request): Response
    {
        $this->authorize('appointments.view');
        $data = Validator::validate($request->all(), [
            'staff_id'  => 'required|int|exists:staff,id',
            'branch_id' => 'required|int|exists:branches,id',
            'date'      => 'required|date',
            'duration'  => 'required|int|min:5',
        ], ['staff_id' => 'متخصص', 'branch_id' => 'شعبه', 'date' => 'تاریخ', 'duration' => 'مدت']);

        $slots = (new AppointmentService())->availableSlots(
            (int)$data['staff_id'],
            (int)$data['branch_id'],
            (string)$data['date'],
            (int)$data['duration'],
            0,
            $request->int('exclude_id')
        );

        return $this->json([
            'slots'  => $slots,
            'date'   => $data['date'],
            'jalali' => Jalali::format((string)$data['date'], 'l j F Y'),
        ]);
    }

    /* ====================================================== invoices === */

    public function invoices(Request $request): Response
    {
        $this->authorize('invoices.view');
        $db     = Database::instance();
        $page   = $request->page();
        $per    = $request->perPage();
        $params = [];
        $where  = "WHERE i.status <> 'CANCELLED'";

        if ($customerId = $request->int('customer_id')) {
            $where .= ' AND i.customer_id = :c';
            $params['c'] = $customerId;
        }
        if ($branchId = ($this->scopedBranchId() ?? $request->int('branch_id'))) {
            $where .= ' AND i.branch_id = :b';
            $params['b'] = $branchId;
        }

        $total = (int)$db->scalar("SELECT COUNT(*) FROM invoices i {$where}", $params);
        $rows  = $db->select(
            "SELECT i.id, i.invoice_number, i.issue_date, i.total, i.paid_amount, i.status,
                    CONCAT(c.first_name,' ',c.last_name) AS customer_name
             FROM invoices i JOIN customers c ON c.id = i.customer_id
             {$where} ORDER BY i.id DESC LIMIT {$per} OFFSET " . (($page - 1) * $per),
            $params
        );

        return $this->json(['items' => $rows], [
            'page' => $page, 'lastPage' => max(1, (int)ceil($total / $per)), 'total' => $total,
        ]);
    }

    public function invoice(Request $request, string $id): Response
    {
        $this->authorize('invoices.view');
        $db      = Database::instance();
        $invoice = $db->selectOne(
            "SELECT i.*, CONCAT(c.first_name,' ',c.last_name) AS customer_name, c.mobile
             FROM invoices i JOIN customers c ON c.id = i.customer_id WHERE i.id = :id",
            ['id' => (int)$id]
        );
        if ($invoice === null) {
            return $this->fail('NOT_FOUND', 'فاکتور یافت نشد.', 404);
        }

        return $this->json([
            'invoice'  => $invoice,
            'items'    => $db->select('SELECT description, quantity, unit_price, discount_amount, total FROM invoice_items WHERE invoice_id = :i', ['i' => (int)$id]),
            'payments' => $db->select('SELECT amount, method, paid_at, reference FROM payments WHERE invoice_id = :i', ['i' => (int)$id]),
        ]);
    }

    /* ===================================================== analytics === */

    public function kpis(Request $request): Response
    {
        $this->authorize('reports.view');
        $analytics = new AnalyticsService();
        $branchId  = $this->scopedBranchId() ?? $request->int('branch_id');

        return $this->json([
            'kpis'    => $analytics->dashboardKpis($branchId),
            'metrics' => $analytics->businessMetrics($branchId),
        ]);
    }

    public function series(Request $request): Response
    {
        $this->authorize('reports.view');
        $analytics = new AnalyticsService();
        $days      = max(7, min(365, $request->int('days', 30) ?? 30));
        $branchId  = $this->scopedBranchId() ?? $request->int('branch_id');

        return $this->json(match ($request->str('type', 'revenue')) {
            'appointments' => ['series' => $analytics->appointmentSeries($days, $branchId)],
            'services'     => ['series' => $analytics->topServices(8, $branchId, $days)],
            'staff'        => ['series' => $analytics->staffPerformance(8, $branchId, $days)],
            'branches'     => ['series' => $analytics->branchPerformance($days)],
            default        => ['series' => $analytics->revenueSeries($days, $branchId)],
        });
    }

    /* ================================================= notifications === */

    public function notifications(Request $request): Response
    {
        $userId = (int)$this->userId();
        $items  = NotificationService::listFor($userId, strtoupper($request->str('status', 'UNREAD')), 20);
        foreach ($items as &$item) {
            $item['created_fa'] = Jalali::ago($item['created_at']);
        }
        unset($item);

        return $this->json(['items' => $items, 'unread' => NotificationService::unreadCount($userId)]);
    }

    public function readNotification(Request $request, string $id): Response
    {
        $userId = (int)$this->userId();
        NotificationService::markRead((int)$id, $userId);

        return $this->json(['unread' => NotificationService::unreadCount($userId)]);
    }
}
