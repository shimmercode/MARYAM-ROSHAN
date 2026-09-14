<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Jalali;
use App\Repositories\AppointmentRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\StaffRepository;
use App\Services\AppointmentService;
use App\Services\ExportService;
use App\Validators\Validator;

final class AppointmentController extends BaseController
{
    private AppointmentRepository $repo;
    private AppointmentService $service;

    public function __construct()
    {
        $this->repo    = new AppointmentRepository();
        $this->service = new AppointmentService();
    }

    public function index(Request $request): Response
    {
        $this->authorize('appointments.view');
        $filters = [
            'search'    => $request->str('search'),
            'status'    => $request->str('status'),
            'branch_id' => $request->int('branch_id') ?? $this->scopedBranchId(),
            'staff_id'  => $request->int('staff_id'),
            'date_from' => $request->str('date_from'),
            'date_to'   => $request->str('date_to'),
            'sort'      => $request->str('sort'),
            'dir'       => $request->str('dir', 'DESC'),
        ];
        $result = $this->repo->paginate($filters, $request->page(), $request->perPage());

        if ($request->wantsJson()) {
            return $this->json($result['data'], ['total' => $result['total'], 'last_page' => $result['last_page']]);
        }

        return $this->view('admin/appointments/index', [
            'title'    => 'نوبت‌ها',
            'result'   => $result,
            'filters'  => $filters,
            'statuses' => AppointmentService::STATUSES,
            'counts'   => $this->service->statusCounts($filters['branch_id'] ?: null),
            'branches' => Database::instance()->select("SELECT id, name FROM branches WHERE deleted_at IS NULL AND status='ACTIVE' ORDER BY name"),
            'staff'    => (new StaffRepository())->activeList($filters['branch_id'] ?: null),
        ]);
    }

    public function calendar(Request $request): Response
    {
        $this->authorize('appointments.view');
        return $this->view('admin/appointments/calendar', [
            'title'    => 'تقویم نوبت‌ها',
            'branches' => Database::instance()->select("SELECT id, name FROM branches WHERE deleted_at IS NULL AND status='ACTIVE' ORDER BY name"),
            'staff'    => (new StaffRepository())->activeList($this->scopedBranchId()),
            'today'    => date('Y-m-d'),
            'todayJalali' => Jalali::today('Y/m/d'),
        ]);
    }

    /** JSON feed for the calendar UI. */
    public function calendarFeed(Request $request): Response
    {
        $this->authorize('appointments.view');
        $from = $request->str('from', date('Y-m-d'));
        $to   = $request->str('to', date('Y-m-d', strtotime('+7 days')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            return $this->fail('VALIDATION_ERROR', 'بازه تاریخ نامعتبر است.', 422);
        }
        $events = $this->repo->calendar($from, $to, $request->int('branch_id') ?? $this->scopedBranchId(), $request->int('staff_id'));
        return $this->json($events);
    }

    public function create(Request $request): Response
    {
        $this->authorize('appointments.create');
        return $this->view('admin/appointments/form', [
            'title'    => 'رزرو نوبت جدید',
            'branches' => Database::instance()->select("SELECT id, name FROM branches WHERE deleted_at IS NULL AND status='ACTIVE' ORDER BY name"),
            'categories' => (new ServiceRepository())->categories(),
            'services' => (new ServiceRepository())->activeList(),
            'staff'    => (new StaffRepository())->activeList(),
            'customerId' => $request->int('customer_id'),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->authorize('appointments.create');
        $data = Validator::validate($request->all(), [
            'customer_id' => 'required|int|exists:customers,id',
            'staff_id'    => 'required|int|exists:staff,id',
            'branch_id'   => 'required|int|exists:branches,id',
            'date'        => 'required|date',
            'time'        => 'required|time',
            'service_ids' => 'required|array',
            'resource_id' => 'nullable|int',
            'notes'       => 'nullable|string|max:500',
            'status'      => 'nullable|in:PENDING,CONFIRMED',
        ], [
            'customer_id' => 'مشتری', 'staff_id' => 'متخصص', 'branch_id' => 'شعبه',
            'date' => 'تاریخ', 'time' => 'ساعت', 'service_ids' => 'خدمات',
        ]);

        $appointment = $this->service->create([
            'customer_id' => (int)$data['customer_id'],
            'staff_id'    => (int)$data['staff_id'],
            'branch_id'   => (int)$data['branch_id'],
            'date'        => (string)$data['date'],
            'time'        => substr((string)$data['time'], 0, 5),
            'service_ids' => $data['service_ids'],
            'resource_id' => $data['resource_id'] ?? null,
            'notes'       => $data['notes'] ?? null,
            'status'      => $data['status'] ?? 'CONFIRMED',
            'source'      => 'ADMIN',
            'created_by'  => $this->userId(),
        ]);

        if ($request->wantsJson()) {
            return $this->json($appointment);
        }
        return $this->redirect('/admin/appointments/' . $appointment['id'], 'success', 'نوبت با موفقیت ثبت شد.');
    }

    public function show(Request $request, string $id): Response
    {
        $this->authorize('appointments.view');
        $appointment = $this->repo->findFull((int)$id);
        if ($appointment === null) {
            $this->notFound('نوبت یافت نشد.');
        }
        return $this->view('admin/appointments/show', [
            'title'       => 'نوبت ' . $appointment['code'],
            'appointment' => $appointment,
            'items'       => $this->repo->items((int)$id),
            'history'     => $this->repo->history((int)$id),
            'statuses'    => AppointmentService::STATUSES,
        ]);
    }

    public function changeStatus(Request $request, string $id): Response
    {
        $this->authorize('appointments.edit');
        $data = Validator::validate($request->only(['status', 'note']), [
            'status' => 'required|in:' . implode(',', array_keys(AppointmentService::STATUSES)),
            'note'   => 'nullable|string|max:255',
        ], ['status' => 'وضعیت']);

        $appointment = $this->service->changeStatus((int)$id, (string)$data['status'], $data['note'] ?? null, $this->userId());
        if ($request->wantsJson()) {
            return $this->json($appointment);
        }
        return $this->back('success', 'وضعیت نوبت به‌روزرسانی شد.');
    }

    public function cancel(Request $request, string $id): Response
    {
        $this->authorize('appointments.cancel');
        $reason = $request->str('reason');
        $this->service->cancel((int)$id, $reason !== '' ? $reason : null, $this->userId());
        if ($request->wantsJson()) {
            return $this->json(['cancelled' => true]);
        }
        return $this->back('success', 'نوبت لغو شد.');
    }

    public function reschedule(Request $request, string $id): Response
    {
        $this->authorize('appointments.edit');
        $data = Validator::validate($request->only(['date', 'time', 'staff_id']), [
            'date'     => 'required|date',
            'time'     => 'required|time',
            'staff_id' => 'nullable|int|exists:staff,id',
        ], ['date' => 'تاریخ', 'time' => 'ساعت']);

        $appointment = $this->service->reschedule(
            (int)$id,
            (string)$data['date'],
            substr((string)$data['time'], 0, 5),
            isset($data['staff_id']) ? (int)$data['staff_id'] : null
        );
        if ($request->wantsJson()) {
            return $this->json($appointment);
        }
        return $this->back('success', 'نوبت جابه‌جا شد.');
    }

    /** Free time slots for a staff member (used by the booking wizard). */
    public function slots(Request $request): Response
    {
        $this->authorize('appointments.view');
        $data = Validator::validate($request->all(), [
            'staff_id'    => 'required|int|exists:staff,id',
            'branch_id'   => 'required|int|exists:branches,id',
            'date'        => 'required|date',
            'service_ids' => 'required|array',
        ], ['staff_id' => 'متخصص', 'branch_id' => 'شعبه', 'date' => 'تاریخ', 'service_ids' => 'خدمات']);

        $repo     = new ServiceRepository();
        $duration = 0;
        $buffer   = 0;
        foreach ((array)$data['service_ids'] as $sid) {
            $duration += $repo->durationFor((int)$sid, (int)$data['staff_id']);
            $svc = $repo->find((int)$sid);
            $buffer = max($buffer, (int)($svc['buffer_minutes'] ?? 0));
        }
        if ($duration <= 0) {
            return $this->fail('VALIDATION_ERROR', 'خدمت معتبری انتخاب نشده است.', 422);
        }

        $slots = $this->service->availableSlots(
            (int)$data['staff_id'],
            (int)$data['branch_id'],
            (string)$data['date'],
            $duration,
            $buffer,
            $request->int('exclude_id')
        );

        return $this->json([
            'slots'    => $slots,
            'duration' => $duration,
            'buffer'   => $buffer,
            'date'     => $data['date'],
            'jalali'   => Jalali::format((string)$data['date'], 'l j F Y'),
        ]);
    }

    public function export(Request $request): Response
    {
        $this->authorize('appointments.view');
        $result = $this->repo->paginate([
            'status'    => $request->str('status'),
            'branch_id' => $request->int('branch_id') ?? $this->scopedBranchId(),
            'date_from' => $request->str('date_from'),
            'date_to'   => $request->str('date_to'),
        ], 1, 10000);

        $rows = array_map(static fn ($a) => [
            'کد'      => $a['code'],
            'تاریخ'   => $a['appointment_date'],
            'ساعت'    => $a['start_time'],
            'مشتری'   => $a['first_name'] . ' ' . $a['last_name'],
            'موبایل'  => $a['mobile'],
            'متخصص'   => $a['staff_name'],
            'شعبه'    => $a['branch_name'],
            'خدمات'   => $a['services'],
            'وضعیت'   => AppointmentService::statusLabel((string)$a['status']),
            'مبلغ'    => $a['total_price'],
        ], $result['data']);

        return Response::download(ExportService::csv($rows), ExportService::filename('appointments'), 'text/csv; charset=UTF-8');
    }
}
