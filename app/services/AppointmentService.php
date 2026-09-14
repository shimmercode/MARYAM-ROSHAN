<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\BusinessException;
use App\Helpers\Format;
use App\Repositories\AppointmentRepository;
use App\Repositories\ServiceRepository;
use DateTimeImmutable;

/**
 * Real booking engine: availability calculation, conflict detection and
 * transactional appointment creation.
 *
 * Weekday convention: 0 = Saturday ... 6 = Friday (Iranian week).
 */
final class AppointmentService
{
    public const STATUSES = [
        'PENDING'     => 'در انتظار تایید',
        'CONFIRMED'   => 'تایید شده',
        'CHECKED_IN'  => 'حاضر شده',
        'IN_PROGRESS' => 'در حال انجام',
        'COMPLETED'   => 'تکمیل شده',
        'CANCELLED'   => 'لغو شده',
        'NO_SHOW'     => 'عدم مراجعه',
    ];

    /** Allowed status transitions. */
    public const TRANSITIONS = [
        'PENDING'     => ['CONFIRMED', 'CHECKED_IN', 'CANCELLED', 'NO_SHOW'],
        'CONFIRMED'   => ['CHECKED_IN', 'IN_PROGRESS', 'CANCELLED', 'NO_SHOW'],
        'CHECKED_IN'  => ['IN_PROGRESS', 'COMPLETED', 'CANCELLED', 'NO_SHOW'],
        'IN_PROGRESS' => ['COMPLETED', 'CANCELLED'],
        'COMPLETED'   => [],
        'CANCELLED'   => [],
        'NO_SHOW'     => ['CONFIRMED'],
    ];

    private Database $db;
    private AppointmentRepository $appointments;
    private ServiceRepository $services;

    public function __construct(?Database $db = null)
    {
        $this->db           = $db ?? Database::instance();
        $this->appointments = new AppointmentRepository($this->db);
        $this->services     = new ServiceRepository($this->db);
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status] ?? $status;
    }

    /* ------------------------------------------------------------------ */
    /* Availability                                                        */
    /* ------------------------------------------------------------------ */

    /** Iranian weekday index: 0 = Saturday ... 6 = Friday. */
    public static function weekdayIndex(string $date): int
    {
        return ((int)date('w', strtotime($date)) + 1) % 7;
    }

    /**
     * Compute free start times for a staff member on a date.
     * @return array<int, array{time:string, label:string}>
     */
    public function availableSlots(int $staffId, int $branchId, string $date, int $durationMinutes, int $bufferMinutes = 0, ?int $excludeAppointmentId = null): array
    {
        $rules = $this->bookingRules($branchId);
        $step  = max(5, (int)$rules['slot_step_minutes']);
        $total = $durationMinutes + $bufferMinutes;
        if ($total <= 0) {
            throw new BusinessException('مدت زمان خدمت نامعتبر است.', 'INVALID_DURATION', 422);
        }

        $window = $this->workingWindow($staffId, $branchId, $date);
        if ($window === null) {
            return [];
        }

        $busy = [];
        foreach ($this->appointments->busyIntervals($staffId, $date) as $b) {
            if ($excludeAppointmentId !== null && isset($b['id']) && (int)$b['id'] === $excludeAppointmentId) {
                continue;
            }
            $busy[] = [strtotime((string)$b['starts_at']), strtotime((string)$b['ends_at'])];
        }
        foreach ($window['breaks'] as $br) {
            $busy[] = [strtotime($date . ' ' . $br[0]), strtotime($date . ' ' . $br[1])];
        }

        $minLead   = (int)$rules['min_lead_minutes'];
        $earliest  = time() + $minLead * 60;
        $dayStart  = strtotime($date . ' ' . $window['start']);
        $dayEnd    = strtotime($date . ' ' . $window['end']);

        $slots = [];
        for ($t = $dayStart; $t + $total * 60 <= $dayEnd; $t += $step * 60) {
            if ($t < $earliest) {
                continue;
            }
            $end = $t + $total * 60;
            $free = true;
            foreach ($busy as [$bs, $be]) {
                if ($t < $be && $end > $bs) {
                    $free = false;
                    break;
                }
            }
            if ($free) {
                $slots[] = [
                    'time'  => date('H:i', $t),
                    'label' => Format::digits(date('H:i', $t)),
                    'end'   => date('H:i', $t + $durationMinutes * 60),
                ];
            }
        }
        return $slots;
    }

    /**
     * Working window for staff on a date, taking branch hours, shifts and leave into account.
     * @return array{start:string, end:string, breaks:array}|null
     */
    public function workingWindow(int $staffId, int $branchId, string $date): ?array
    {
        // Approved leave blocks the whole day (or a time range).
        $leave = $this->db->selectOne(
            "SELECT start_time, end_time FROM leaves
             WHERE staff_id = :s AND status = 'APPROVED' AND :d BETWEEN start_date AND end_date LIMIT 1",
            ['s' => $staffId, 'd' => $date]
        );
        $leaveBreak = null;
        if ($leave !== null) {
            if (empty($leave['start_time']) || empty($leave['end_time'])) {
                return null; // full-day leave
            }
            $leaveBreak = [(string)$leave['start_time'], (string)$leave['end_time']];
        }

        $branch = $this->db->selectOne(
            "SELECT opening_time, closing_time, working_days, status FROM branches WHERE id = :b AND deleted_at IS NULL",
            ['b' => $branchId]
        );
        if ($branch === null || $branch['status'] !== 'ACTIVE') {
            return null;
        }
        $workingDays = array_map('intval', array_filter(explode(',', (string)$branch['working_days']), 'strlen'));
        if (!in_array(self::weekdayIndex($date), $workingDays, true)) {
            return null;
        }

        $start  = substr((string)$branch['opening_time'], 0, 5);
        $end    = substr((string)$branch['closing_time'], 0, 5);
        $breaks = [];

        // An explicit shift narrows the window.
        $shift = $this->db->selectOne(
            "SELECT start_time, end_time, break_start, break_end FROM staff_shifts
             WHERE staff_id = :s AND shift_date = :d AND status = 'SCHEDULED' LIMIT 1",
            ['s' => $staffId, 'd' => $date]
        );
        if ($shift === null) {
            // Fall back to the recurring weekly shift pattern.
            $shift = $this->db->selectOne(
                'SELECT start_time, end_time, break_start, break_end FROM staff_weekly_shifts
                 WHERE staff_id = :s AND weekday = :w AND is_active = 1 LIMIT 1',
                ['s' => $staffId, 'w' => self::weekdayIndex($date)]
            );
        }
        if ($shift !== null) {
            $start = substr((string)$shift['start_time'], 0, 5);
            $end   = substr((string)$shift['end_time'], 0, 5);
            if (!empty($shift['break_start']) && !empty($shift['break_end'])) {
                $breaks[] = [substr((string)$shift['break_start'], 0, 5), substr((string)$shift['break_end'], 0, 5)];
            }
        }
        if ($leaveBreak !== null) {
            $breaks[] = [substr($leaveBreak[0], 0, 5), substr($leaveBreak[1], 0, 5)];
        }
        if (strtotime($date . ' ' . $end) <= strtotime($date . ' ' . $start)) {
            return null;
        }
        return ['start' => $start, 'end' => $end, 'breaks' => $breaks];
    }

    public function bookingRules(?int $branchId = null): array
    {
        $row = null;
        if ($branchId) {
            $row = $this->db->selectOne('SELECT * FROM booking_rules WHERE branch_id = :b LIMIT 1', ['b' => $branchId]);
        }
        $row ??= $this->db->selectOne('SELECT * FROM booking_rules WHERE branch_id IS NULL LIMIT 1');
        return $row ?? [
            'min_lead_minutes'     => 60,
            'max_advance_days'     => 60,
            'slot_step_minutes'    => 15,
            'cancellation_hours'   => 6,
            'allow_online_booking' => 1,
            'require_deposit'      => 0,
            'deposit_percent'      => 0,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Booking                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Create an appointment atomically.
     *
     * @param array{customer_id:int, staff_id:int, branch_id:int, date:string, time:string,
     *              service_ids:array<int>, resource_id?:?int, notes?:?string, source?:string,
     *              status?:string, created_by?:?int} $data
     */
    public function create(array $data): array
    {
        $customerId = (int)($data['customer_id'] ?? 0);
        $staffId    = (int)($data['staff_id'] ?? 0);
        $branchId   = (int)($data['branch_id'] ?? 0);
        $date       = Format::toEnglishDigits((string)($data['date'] ?? ''));
        $time       = Format::toEnglishDigits((string)($data['time'] ?? ''));
        $serviceIds = array_values(array_unique(array_map('intval', (array)($data['service_ids'] ?? []))));
        $resourceId = isset($data['resource_id']) && $data['resource_id'] ? (int)$data['resource_id'] : null;

        if ($serviceIds === []) {
            throw new BusinessException('حداقل یک خدمت باید انتخاب شود.', 'NO_SERVICE', 422);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
            throw new BusinessException('تاریخ یا ساعت نوبت معتبر نیست.', 'INVALID_DATETIME', 422);
        }
        $time = substr($time, 0, 5);

        return $this->db->transaction(function () use ($customerId, $staffId, $branchId, $date, $time, $serviceIds, $resourceId, $data) {
            /* 1. customer */
            $customer = $this->db->selectOne(
                'SELECT id, status, first_name, last_name, mobile FROM customers WHERE id = :c AND deleted_at IS NULL',
                ['c' => $customerId]
            );
            if ($customer === null) {
                throw new BusinessException('مشتری یافت نشد.', 'CUSTOMER_NOT_FOUND', 404);
            }
            if ($customer['status'] === 'BLACKLIST') {
                throw new BusinessException('امکان رزرو برای این مشتری وجود ندارد.', 'CUSTOMER_BLACKLISTED', 403);
            }

            /* 2. staff */
            $staff = $this->db->selectOne(
                'SELECT id, status, branch_id FROM staff WHERE id = :s AND deleted_at IS NULL',
                ['s' => $staffId]
            );
            if ($staff === null || $staff['status'] !== 'ACTIVE') {
                throw new BusinessException('متخصص انتخاب‌شده در دسترس نیست.', 'STAFF_UNAVAILABLE', 422);
            }

            /* 3. branch */
            $branch = $this->db->selectOne(
                "SELECT id, name, status FROM branches WHERE id = :b AND deleted_at IS NULL",
                ['b' => $branchId]
            );
            if ($branch === null || $branch['status'] !== 'ACTIVE') {
                throw new BusinessException('شعبه انتخاب‌شده فعال نیست.', 'BRANCH_INACTIVE', 422);
            }

            /* 4. services + duration/price, and staff capability */
            $duration = 0;
            $buffer   = 0;
            $total    = 0.0;
            $items    = [];
            foreach ($serviceIds as $sid) {
                $svc = $this->db->selectOne(
                    "SELECT id, name, price, duration_minutes, buffer_minutes, status, requires_resource
                     FROM services WHERE id = :s AND deleted_at IS NULL",
                    ['s' => $sid]
                );
                if ($svc === null || $svc['status'] !== 'ACTIVE') {
                    throw new BusinessException('خدمت انتخاب‌شده فعال نیست.', 'SERVICE_INACTIVE', 422);
                }
                $canDo = (int)$this->db->scalar(
                    'SELECT COUNT(*) FROM staff_services WHERE staff_id = :st AND service_id = :sv',
                    ['st' => $staffId, 'sv' => $sid]
                );
                if ($canDo === 0) {
                    throw new BusinessException(
                        'متخصص انتخاب‌شده خدمت «' . $svc['name'] . '» را ارائه نمی‌دهد.',
                        'STAFF_SERVICE_MISMATCH',
                        422
                    );
                }
                $price    = $this->services->priceFor($sid, $staffId);
                $dur      = $this->services->durationFor($sid, $staffId);
                $duration += $dur;
                $buffer    = max($buffer, (int)$svc['buffer_minutes']);
                $total    += $price;
                $items[]   = ['service_id' => $sid, 'price' => $price, 'duration_minutes' => $dur, 'quantity' => 1];
            }

            $startsAt = $date . ' ' . $time . ':00';
            $endTime  = date('H:i:s', strtotime($startsAt) + $duration * 60);
            $endsAt   = date('Y-m-d H:i:s', strtotime($startsAt) + ($duration + $buffer) * 60);

            /* 5/6. working hours + leave */
            $window = $this->workingWindow($staffId, $branchId, $date);
            if ($window === null) {
                throw new BusinessException('در تاریخ انتخاب‌شده، متخصص یا شعبه فعال نیست.', 'OUTSIDE_WORKING_HOURS', 422);
            }
            $wStart = strtotime($date . ' ' . $window['start']);
            $wEnd   = strtotime($date . ' ' . $window['end']);
            if (strtotime($startsAt) < $wStart || strtotime($date . ' ' . $endTime) > $wEnd) {
                throw new BusinessException(
                    'ساعت انتخاب‌شده خارج از ساعات کاری است (' . Format::digits($window['start'] . ' تا ' . $window['end']) . ').',
                    'OUTSIDE_WORKING_HOURS',
                    422
                );
            }
            foreach ($window['breaks'] as $br) {
                $bs = strtotime($date . ' ' . $br[0]);
                $be = strtotime($date . ' ' . $br[1]);
                if (strtotime($startsAt) < $be && strtotime($endsAt) > $bs) {
                    throw new BusinessException('ساعت انتخاب‌شده با زمان استراحت/مرخصی تداخل دارد.', 'BREAK_CONFLICT', 409);
                }
            }

            /* lead time + max advance */
            $rules = $this->bookingRules($branchId);
            if (($data['source'] ?? 'ADMIN') === 'ONLINE') {
                if (!(int)$rules['allow_online_booking']) {
                    throw new BusinessException('رزرو آنلاین در حال حاضر غیرفعال است.', 'ONLINE_BOOKING_DISABLED', 403);
                }
                if (strtotime($startsAt) < time() + (int)$rules['min_lead_minutes'] * 60) {
                    throw new BusinessException('برای این زمان امکان رزرو آنلاین وجود ندارد.', 'LEAD_TIME', 422);
                }
            }
            if (strtotime($date) > strtotime('+' . (int)$rules['max_advance_days'] . ' days')) {
                throw new BusinessException('امکان رزرو برای این تاریخ (خیلی دور) وجود ندارد.', 'TOO_FAR_AHEAD', 422);
            }

            /* 7. resource */
            if ($resourceId !== null) {
                $res = $this->db->selectOne('SELECT id, status, branch_id FROM resources WHERE id = :r', ['r' => $resourceId]);
                if ($res === null || $res['status'] !== 'ACTIVE' || (int)$res['branch_id'] !== $branchId) {
                    throw new BusinessException('منبع (اتاق/صندلی) انتخاب‌شده در دسترس نیست.', 'RESOURCE_UNAVAILABLE', 422);
                }
                if ($this->appointments->conflictsForResource($resourceId, $startsAt, $endsAt, null, true) !== []) {
                    throw new BusinessException('منبع انتخاب‌شده در این بازه رزرو شده است.', 'RESOURCE_CONFLICT', 409);
                }
            }

            /* 8. conflict (locked) */
            $conflicts = $this->appointments->conflictsForStaff($staffId, $startsAt, $endsAt, null, true);
            if ($conflicts !== []) {
                throw new BusinessException(
                    'این بازه زمانی قبلاً برای متخصص رزرو شده است. لطفاً زمان دیگری انتخاب کنید.',
                    'DOUBLE_BOOKING',
                    409,
                    ['conflict_code' => $conflicts[0]['code'] ?? null]
                );
            }

            /* also prevent the same customer double-booking themselves */
            $selfConflict = $this->db->select(
                "SELECT id FROM appointments WHERE customer_id = :c AND status NOT IN ('CANCELLED','NO_SHOW')
                 AND starts_at < :e AND ends_at > :s",
                ['c' => $customerId, 's' => $startsAt, 'e' => $endsAt]
            );
            if ($selfConflict !== []) {
                throw new BusinessException('این مشتری در همین بازه نوبت دیگری دارد.', 'CUSTOMER_DOUBLE_BOOKING', 409);
            }

            /* 9. create */
            $deposit = 0.0;
            if ((int)$rules['require_deposit']) {
                $deposit = round($total * (float)$rules['deposit_percent'] / 100, 2);
            }
            $status = (string)($data['status'] ?? 'PENDING');
            if (!array_key_exists($status, self::STATUSES)) {
                $status = 'PENDING';
            }

            $appointmentId = $this->db->insert('appointments', [
                'code'             => $this->appointments->nextCode(),
                'customer_id'      => $customerId,
                'staff_id'         => $staffId,
                'branch_id'        => $branchId,
                'resource_id'      => $resourceId,
                'appointment_date' => $date,
                'start_time'       => $time . ':00',
                'end_time'         => $endTime,
                'starts_at'        => $startsAt,
                'ends_at'          => $endsAt,
                'duration_minutes' => $duration,
                'buffer_minutes'   => $buffer,
                'status'           => $status,
                'source'           => (string)($data['source'] ?? 'ADMIN'),
                'total_price'      => number_format($total, 2, '.', ''),
                'deposit_amount'   => number_format($deposit, 2, '.', ''),
                'notes'            => isset($data['notes']) ? mb_substr((string)$data['notes'], 0, 500) : null,
                'created_by'       => $data['created_by'] ?? AuthService::id(),
            ]);

            /* 10. items */
            foreach ($items as $it) {
                $this->db->insert('appointment_items', [
                    'appointment_id'   => $appointmentId,
                    'service_id'       => $it['service_id'],
                    'staff_id'         => $staffId,
                    'price'            => number_format($it['price'], 2, '.', ''),
                    'duration_minutes' => $it['duration_minutes'],
                    'quantity'         => $it['quantity'],
                ]);
            }

            /* 11. status history */
            $this->appointments->addStatusHistory($appointmentId, null, $status, $data['created_by'] ?? AuthService::id(), 'ایجاد نوبت');

            /* 12. resource allocation + reminder */
            if ($resourceId !== null) {
                $this->db->insert('resource_allocations', [
                    'resource_id'    => $resourceId,
                    'appointment_id' => $appointmentId,
                    'starts_at'      => $startsAt,
                    'ends_at'        => $endsAt,
                ]);
            }
            $reminderAt = date('Y-m-d H:i:s', strtotime($startsAt) - 24 * 3600);
            if (strtotime($reminderAt) > time()) {
                $this->db->insert('appointment_reminders', [
                    'appointment_id' => $appointmentId,
                    'channel'        => 'SMS',
                    'scheduled_at'   => $reminderAt,
                    'status'         => 'PENDING',
                ]);
            }

            AuditService::log('appointment_created', 'appointments', $appointmentId, null, [
                'customer_id' => $customerId, 'staff_id' => $staffId, 'starts_at' => $startsAt,
            ]);

            $appointment = $this->appointments->findFull($appointmentId) ?? [];
            AutomationService::fire('appointment_created', [
                'appointment_id' => $appointmentId,
                'customer_id'    => $customerId,
                'staff_id'       => $staffId,
                'branch_id'      => $branchId,
                'total'          => $total,
            ]);
            return $appointment;
        });
    }

    /* ------------------------------------------------------------------ */
    /* Status changes                                                      */
    /* ------------------------------------------------------------------ */

    public function changeStatus(int $appointmentId, string $newStatus, ?string $note = null, ?int $userId = null): array
    {
        if (!array_key_exists($newStatus, self::STATUSES)) {
            throw new BusinessException('وضعیت نامعتبر است.', 'INVALID_STATUS', 422);
        }
        return $this->db->transaction(function () use ($appointmentId, $newStatus, $note, $userId) {
            $appointment = $this->db->selectOne(
                'SELECT * FROM appointments WHERE id = :a' . $this->db->forUpdate(),
                ['a' => $appointmentId]
            );
            if ($appointment === null) {
                throw new BusinessException('نوبت یافت نشد.', 'APPOINTMENT_NOT_FOUND', 404);
            }
            $current = (string)$appointment['status'];
            if ($current === $newStatus) {
                return $appointment;
            }
            if (!in_array($newStatus, self::TRANSITIONS[$current] ?? [], true)) {
                throw new BusinessException(
                    'تغییر وضعیت از «' . self::statusLabel($current) . '» به «' . self::statusLabel($newStatus) . '» مجاز نیست.',
                    'INVALID_TRANSITION',
                    422
                );
            }

            $update = ['status' => $newStatus];
            if ($newStatus === 'CANCELLED') {
                $update['cancelled_at']  = date('Y-m-d H:i:s');
                $update['cancel_reason'] = $note !== null ? mb_substr($note, 0, 255) : null;
            }
            if ($newStatus === 'COMPLETED') {
                $update['completed_at'] = date('Y-m-d H:i:s');
            }
            $this->db->update('appointments', $update, 'id = :a', ['a' => $appointmentId]);
            $this->appointments->addStatusHistory($appointmentId, $current, $newStatus, $userId ?? AuthService::id(), $note);

            if (in_array($newStatus, ['CANCELLED', 'NO_SHOW'], true)) {
                $this->db->execute(
                    "UPDATE appointment_reminders SET status = 'CANCELLED' WHERE appointment_id = :a AND status = 'PENDING'",
                    ['a' => $appointmentId]
                );
            }

            if ($newStatus === 'COMPLETED') {
                // Consume inventory defined by service recipes.
                foreach ($this->appointments->items($appointmentId) as $item) {
                    (new InventoryService($this->db))->consumeForService(
                        (int)$item['service_id'],
                        (int)$appointment['branch_id'],
                        'appointment',
                        $appointmentId
                    );
                }
                (new \App\Repositories\CustomerRepository($this->db))->refreshAggregates((int)$appointment['customer_id']);
            }

            AuditService::log('appointment_status_changed', 'appointments', $appointmentId, ['status' => $current], ['status' => $newStatus]);
            AutomationService::fire('appointment_' . strtolower($newStatus), [
                'appointment_id' => $appointmentId,
                'customer_id'    => (int)$appointment['customer_id'],
                'staff_id'       => (int)$appointment['staff_id'],
                'branch_id'      => (int)$appointment['branch_id'],
            ]);

            return $this->appointments->findFull($appointmentId) ?? [];
        });
    }

    public function cancel(int $appointmentId, ?string $reason, ?int $userId = null, bool $enforcePolicy = false): array
    {
        if ($enforcePolicy) {
            $appointment = $this->appointments->find($appointmentId);
            if ($appointment !== null) {
                $hours = (int)$this->bookingRules((int)$appointment['branch_id'])['cancellation_hours'];
                if (strtotime((string)$appointment['starts_at']) - time() < $hours * 3600) {
                    throw new BusinessException(
                        'لغو نوبت تنها تا ' . Format::digits((string)$hours) . ' ساعت قبل امکان‌پذیر است. لطفاً با سالن تماس بگیرید.',
                        'CANCELLATION_WINDOW_PASSED',
                        422
                    );
                }
            }
        }
        return $this->changeStatus($appointmentId, 'CANCELLED', $reason, $userId);
    }

    /** Reschedule with the same conflict guarantees. */
    public function reschedule(int $appointmentId, string $date, string $time, ?int $staffId = null): array
    {
        return $this->db->transaction(function () use ($appointmentId, $date, $time, $staffId) {
            $appointment = $this->db->selectOne('SELECT * FROM appointments WHERE id = :a' . $this->db->forUpdate(), ['a' => $appointmentId]);
            if ($appointment === null) {
                throw new BusinessException('نوبت یافت نشد.', 'APPOINTMENT_NOT_FOUND', 404);
            }
            if (in_array($appointment['status'], ['COMPLETED', 'CANCELLED'], true)) {
                throw new BusinessException('نوبت تکمیل‌شده یا لغوشده قابل جابه‌جایی نیست.', 'INVALID_STATE', 422);
            }
            $staffId  ??= (int)$appointment['staff_id'];
            $date      = Format::toEnglishDigits($date);
            $time      = substr(Format::toEnglishDigits($time), 0, 5);
            $duration  = (int)$appointment['duration_minutes'];
            $buffer    = (int)$appointment['buffer_minutes'];
            $startsAt  = $date . ' ' . $time . ':00';
            $endTime   = date('H:i:s', strtotime($startsAt) + $duration * 60);
            $endsAt    = date('Y-m-d H:i:s', strtotime($startsAt) + ($duration + $buffer) * 60);

            $window = $this->workingWindow($staffId, (int)$appointment['branch_id'], $date);
            if ($window === null) {
                throw new BusinessException('در تاریخ انتخاب‌شده، متخصص یا شعبه فعال نیست.', 'OUTSIDE_WORKING_HOURS', 422);
            }
            if (strtotime($startsAt) < strtotime($date . ' ' . $window['start'])
                || strtotime($date . ' ' . $endTime) > strtotime($date . ' ' . $window['end'])) {
                throw new BusinessException('ساعت انتخاب‌شده خارج از ساعات کاری است.', 'OUTSIDE_WORKING_HOURS', 422);
            }
            if ($this->appointments->conflictsForStaff($staffId, $startsAt, $endsAt, $appointmentId, true) !== []) {
                throw new BusinessException('این بازه زمانی قبلاً رزرو شده است.', 'DOUBLE_BOOKING', 409);
            }

            $this->db->update('appointments', [
                'staff_id'         => $staffId,
                'appointment_date' => $date,
                'start_time'       => $time . ':00',
                'end_time'         => $endTime,
                'starts_at'        => $startsAt,
                'ends_at'          => $endsAt,
            ], 'id = :a', ['a' => $appointmentId]);

            $this->appointments->addStatusHistory(
                $appointmentId,
                (string)$appointment['status'],
                (string)$appointment['status'],
                AuthService::id(),
                'جابه‌جایی نوبت به ' . $date . ' ' . $time
            );
            $this->db->execute(
                "UPDATE appointment_reminders SET scheduled_at = :s WHERE appointment_id = :a AND status = 'PENDING'",
                ['s' => date('Y-m-d H:i:s', strtotime($startsAt) - 86400), 'a' => $appointmentId]
            );
            AuditService::log('appointment_rescheduled', 'appointments', $appointmentId, [
                'starts_at' => $appointment['starts_at'],
            ], ['starts_at' => $startsAt]);

            return $this->appointments->findFull($appointmentId) ?? [];
        });
    }

    public function statusCounts(?int $branchId = null, ?string $date = null): array
    {
        $where  = ['1=1'];
        $params = [];
        if ($branchId) {
            $where[] = 'branch_id = :b';
            $params['b'] = $branchId;
        }
        if ($date) {
            $where[] = 'appointment_date = :d';
            $params['d'] = $date;
        }
        $rows = $this->db->select(
            'SELECT status, COUNT(*) AS c FROM appointments WHERE ' . implode(' AND ', $where) . ' GROUP BY status',
            $params
        );
        $out = array_fill_keys(array_keys(self::STATUSES), 0);
        foreach ($rows as $r) {
            $out[$r['status']] = (int)$r['c'];
        }
        return $out;
    }
}
