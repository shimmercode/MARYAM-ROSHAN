<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\StaffRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\CommissionService;
use App\Services\PerformanceEvaluationService;
use App\Services\UploadService;
use App\Validators\Validator;

final class StaffController extends BaseController
{
    private StaffRepository $repo;

    public function __construct()
    {
        $this->repo = new StaffRepository();
    }

    public function index(Request $request): Response
    {
        $this->authorize('staff.view');
        $filters = [
            'search'    => $request->str('search'),
            'branch_id' => $request->int('branch_id') ?? $this->scopedBranchId(),
            'status'    => $request->str('status'),
        ];
        $result = $this->repo->paginate($filters, $request->page(), $request->perPage());

        if ($request->wantsJson()) {
            return $this->json($result['data'], ['total' => $result['total'], 'last_page' => $result['last_page']]);
        }
        return $this->view('admin/staff/index', [
            'title'    => 'کارکنان',
            'result'   => $result,
            'filters'  => $filters,
            'branches' => Database::instance()->select("SELECT id, name FROM branches WHERE deleted_at IS NULL AND status='ACTIVE' ORDER BY name"),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('staff.create');
        return $this->formView(null);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->authorize('staff.edit');
        $staff = $this->repo->findFull((int)$id);
        if ($staff === null) {
            $this->notFound('کارمند یافت نشد.');
        }
        return $this->formView($staff);
    }

    public function show(Request $request, string $id): Response
    {
        $this->authorize('staff.view');
        $sid   = (int)$id;
        $staff = $this->repo->findFull($sid);
        if ($staff === null) {
            $this->notFound('کارمند یافت نشد.');
        }
        // Default reporting window: current Gregorian month, current period.
        $from       = date('Y-m-01');
        $to         = date('Y-m-d');
        $period     = date('Y-m');
        $commission = new CommissionService();

        return $this->view('admin/staff/show', [
            'title'       => $staff['first_name'] . ' ' . $staff['last_name'],
            'staff'       => $staff,
            'specialties' => $this->repo->specialties($sid),
            'schedule'    => $this->repo->todaySchedule($sid),
            'performance' => $this->repo->performance($sid, $from, $to),
            'period'      => $period,
            'weeklyShifts' => $this->repo->weeklyShifts($sid),
            'commissions' => $commission->listFor($sid, $period, 20),
            'commissionSummary' => $commission->summary($sid, $period),
            'evaluations' => Database::instance()->select(
                'SELECT id, period_start, period_end, total_score, special_score, level, rank_label, status, created_at
                 FROM performance_evaluations WHERE staff_id = :s ORDER BY period_end DESC LIMIT 12',
                ['s' => $sid]
            ),
            'instantOffers'  => Database::instance()->select(
                'SELECT io.*, CONCAT(c.first_name," ",c.last_name) AS customer_name
                 FROM instant_offers io LEFT JOIN customers c ON c.id = io.customer_id
                 WHERE io.staff_id = :s ORDER BY io.created_at DESC LIMIT 50',
                ['s' => $sid]
            ),
            'journeyEntries' => Database::instance()->select(
                'SELECT cje.*, CONCAT(c.first_name," ",c.last_name) AS customer_name
                 FROM customer_journey_entries cje LEFT JOIN customers c ON c.id = cje.customer_id
                 WHERE cje.staff_id = :s ORDER BY cje.created_at DESC LIMIT 50',
                ['s' => $sid]
            ),
            'customerReports' => Database::instance()->select(
                'SELECT * FROM customer_management_reports WHERE staff_id = :s ORDER BY period_to DESC LIMIT 30',
                ['s' => $sid]
            ),
            'evaluationRubric' => PerformanceEvaluationService::rubric(),
            'evaluationLevels' => PerformanceEvaluationService::levelTable(),
            'lastEvaluationItems' => (function () use ($sid) {
                $last = Database::instance()->selectOne(
                    'SELECT id FROM performance_evaluations WHERE staff_id = :s ORDER BY id DESC LIMIT 1', ['s' => $sid]
                );
                if ($last === null) {
                    return [];
                }
                return Database::instance()->select(
                    'SELECT * FROM performance_evaluation_items WHERE evaluation_id = :e ORDER BY sort_order',
                    ['e' => (int)$last['id']]
                );
            })(),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->authorize('staff.create');
        $data = $this->validateStaff($request);

        $db = Database::instance();
        $id = $db->transaction(function () use ($db, $data, $request) {
            $userId = null;
            if ($data['account']['create_account']) {
                $userId = $db->insert('users', [
                    'first_name' => $data['staff']['first_name'],
                    'last_name'  => $data['staff']['last_name'],
                    'mobile'     => $data['staff']['mobile'],
                    'email'      => $data['staff']['email'],
                    'password'   => AuthService::hash($data['account']['password']),
                    'branch_id'  => $data['staff']['branch_id'],
                    'status'     => 'ACTIVE',
                ]);
                (new UserRepository())->assignRoleBySlug($userId, $data['account']['role']);
            }

            $payload = $data['staff'];
            $payload['user_id'] = $userId;
            $payload['code']    = $this->repo->nextCode();
            $avatar = $this->handleAvatar($request);
            if ($avatar !== null) {
                $payload['avatar'] = $avatar;
            }
            $sid = $db->insert('staff', $payload);

            $this->saveProfile($db, $sid, $data['profile']);
            $this->repo->syncServices($sid, $data['service_ids']);
            $this->repo->syncSpecialties($sid, $data['specialties']);
            $this->saveShifts($sid, $data['shifts']);
            return $sid;
        });

        AuditService::log('staff_created', 'staff', $id, null, $data['staff']);
        return $this->redirect('/admin/staff/' . $id, 'success', 'کارمند با موفقیت ثبت شد.');
    }

    public function update(Request $request, string $id): Response
    {
        $this->authorize('staff.edit');
        $sid = (int)$id;
        $old = $this->repo->find($sid);
        if ($old === null) {
            $this->notFound('کارمند یافت نشد.');
        }
        $data = $this->validateStaff($request, $sid);

        $db = Database::instance();
        $db->transaction(function () use ($db, $sid, $old, $data, $request): void {
            $payload = $data['staff'];
            $avatar  = $this->handleAvatar($request);
            if ($avatar !== null) {
                $payload['avatar'] = $avatar;
            }
            $db->update('staff', $payload, 'id = :id', ['id' => $sid]);

            if ($old['user_id'] !== null) {
                $db->update('users', [
                    'first_name' => $payload['first_name'],
                    'last_name'  => $payload['last_name'],
                    'mobile'     => $payload['mobile'],
                    'email'      => $payload['email'],
                    'branch_id'  => $payload['branch_id'],
                ], 'id = :id', ['id' => (int)$old['user_id']]);
            }

            $this->saveProfile($db, $sid, $data['profile']);
            $this->repo->syncServices($sid, $data['service_ids']);
            $this->repo->syncSpecialties($sid, $data['specialties']);
            $this->saveShifts($sid, $data['shifts']);
        });

        AuditService::log('staff_updated', 'staff', $sid, $old, $data['staff']);
        return $this->redirect('/admin/staff/' . $sid, 'success', 'اطلاعات کارمند به‌روزرسانی شد.');
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->authorize('staff.delete');
        $sid   = (int)$id;
        $staff = $this->repo->find($sid);
        if ($staff === null) {
            $this->notFound('کارمند یافت نشد.');
        }
        $future = (int)Database::instance()->scalar(
            "SELECT COUNT(*) FROM appointments WHERE staff_id = :s AND starts_at > NOW() AND status NOT IN ('CANCELLED','COMPLETED')",
            ['s' => $sid]
        );
        if ($future > 0) {
            return $this->back('error', 'این کارمند نوبت فعال دارد؛ ابتدا نوبت‌ها را منتقل یا لغو کنید.');
        }

        $db = Database::instance();
        $db->transaction(function () use ($db, $sid, $staff): void {
            $this->repo->delete($sid);
            if ($staff['user_id'] !== null) {
                $db->update('users', ['status' => 'INACTIVE'], 'id = :id', ['id' => (int)$staff['user_id']]);
            }
        });
        AuditService::log('staff_deleted', 'staff', $sid, $staff, null);
        return $this->redirect('/admin/staff', 'success', 'کارمند حذف شد.');
    }

    public function saveShiftsAction(Request $request, string $id): Response
    {
        $this->authorize('staff.edit');
        $this->saveShifts((int)$id, $request->arr('shifts'));
        if ($request->wantsJson()) {
            return $this->json($this->repo->weeklyShifts((int)$id));
        }
        return $this->back('success', 'برنامه کاری ذخیره شد.');
    }

    public function storeLeave(Request $request, string $id): Response
    {
        $this->authorize('staff.edit');
        $data = Validator::validate($request->all(), [
            'from_date' => 'required|date',
            'to_date'   => 'required|date',
            'type'      => 'required|in:ANNUAL,SICK,UNPAID,MISSION,OTHER',
            'reason'    => 'nullable|string|max:255',
        ], ['from_date' => 'از تاریخ', 'to_date' => 'تا تاریخ', 'type' => 'نوع مرخصی']);

        if (strtotime((string)$data['to_date']) < strtotime((string)$data['from_date'])) {
            return $this->back('error', 'تاریخ پایان نمی‌تواند قبل از تاریخ شروع باشد.');
        }

        Database::instance()->insert('leaves', [
            'staff_id'   => (int)$id,
            'start_date' => $data['from_date'],
            'end_date'   => $data['to_date'],
            'type'      => $data['type'],
            'reason'    => $data['reason'] ?? null,
            'status'    => 'APPROVED',
            'approved_by' => $this->userId(),
            'approved_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->back('success', 'مرخصی ثبت شد.');
    }

    /* ============================================ instant offers form == */

    public function storeInstantOffer(Request $request, string $id): Response
    {
        $this->authorize('staff.edit');
        $sid = (int)$id;
        $data = Validator::validate($request->all(), [
            'customer_code'      => 'nullable|string|max:20',
            'first_name'         => 'nullable|string|max:80',
            'last_name'          => 'nullable|string|max:80',
            'mobile'             => 'nullable|mobile',
            'service_line'       => 'nullable|string|max:120',
            'source'             => 'nullable|string|max:120',
            'offer_line_1'       => 'nullable|string|max:150',
            'offer_line_2'       => 'nullable|string|max:150',
            'offer_line_3'       => 'nullable|string|max:150',
            'booking_date'       => 'nullable|date',
            'purchase_date'      => 'nullable|date',
            'next_purchase_date' => 'nullable|date',
            'notes'              => 'nullable|string|max:2000',
        ], ['mobile' => 'شماره تماس']);

        $customer = $this->resolveCustomerByCode($data['customer_code'] ?? null);
        if (($data['first_name'] ?? '') === '' && ($data['last_name'] ?? '') === '' && $customer === null) {
            return $this->back('error', 'نام مشتری یا کد مشتری را وارد کنید.');
        }

        Database::instance()->insert('instant_offers', [
            'staff_id'           => $sid,
            'customer_id'        => $customer['id'] ?? null,
            'first_name'         => $customer['first_name'] ?? ($data['first_name'] ?? null),
            'last_name'          => $customer['last_name'] ?? ($data['last_name'] ?? null),
            'mobile'             => $customer['mobile'] ?? ($data['mobile'] ?? null),
            'service_line'       => $data['service_line'] ?? null,
            'source'             => $data['source'] ?? null,
            'offer_line_1'       => $data['offer_line_1'] ?? null,
            'offer_line_2'       => $data['offer_line_2'] ?? null,
            'offer_line_3'       => $data['offer_line_3'] ?? null,
            'booking_date'       => $data['booking_date'] ?? null,
            'purchase_date'      => $data['purchase_date'] ?? null,
            'next_purchase_date' => $data['next_purchase_date'] ?? null,
            'notes'              => $data['notes'] ?? null,
            'created_by'         => $this->userId(),
        ]);

        return $this->back('success', 'آفر در لحظه ثبت شد.');
    }

    public function deleteInstantOffer(Request $request, string $id, string $offerId): Response
    {
        $this->authorize('staff.edit');
        Database::instance()->delete('instant_offers', 'id = :i AND staff_id = :s', ['i' => (int)$offerId, 's' => (int)$id]);
        return $this->back('success', 'ردیف حذف شد.');
    }

    /* ============================================ customer journey form == */

    public function storeJourneyEntry(Request $request, string $id): Response
    {
        $this->authorize('staff.edit');
        $sid = (int)$id;
        $data = Validator::validate($request->all(), [
            'customer_code'      => 'nullable|string|max:20',
            'first_name'         => 'nullable|string|max:80',
            'last_name'          => 'nullable|string|max:80',
            'service_line'       => 'nullable|string|max:120',
            'source'             => 'nullable|string|max:120',
            'consulted_date'     => 'nullable|date',
            'appointment_date'   => 'nullable|date',
            'purchase_date'      => 'nullable|date',
            'satisfaction_date'  => 'nullable|date',
            'next_purchase_date' => 'nullable|date',
            'referred_date'      => 'nullable|date',
            'notes'              => 'nullable|string|max:2000',
        ], []);

        $customer = $this->resolveCustomerByCode($data['customer_code'] ?? null);
        if (($data['first_name'] ?? '') === '' && ($data['last_name'] ?? '') === '' && $customer === null) {
            return $this->back('error', 'نام مشتری یا کد مشتری را وارد کنید.');
        }

        Database::instance()->insert('customer_journey_entries', [
            'staff_id'           => $sid,
            'customer_id'        => $customer['id'] ?? null,
            'first_name'         => $customer['first_name'] ?? ($data['first_name'] ?? null),
            'last_name'          => $customer['last_name'] ?? ($data['last_name'] ?? null),
            'service_line'       => $data['service_line'] ?? null,
            'source'             => $data['source'] ?? null,
            'consulted_date'     => $data['consulted_date'] ?? null,
            'appointment_date'   => $data['appointment_date'] ?? null,
            'purchase_date'      => $data['purchase_date'] ?? null,
            'satisfaction_date'  => $data['satisfaction_date'] ?? null,
            'next_purchase_date' => $data['next_purchase_date'] ?? null,
            'referred_date'      => $data['referred_date'] ?? null,
            'notes'              => $data['notes'] ?? null,
            'created_by'         => $this->userId(),
        ]);

        return $this->back('success', 'رکورد سفر مشتری ثبت شد.');
    }

    public function deleteJourneyEntry(Request $request, string $id, string $entryId): Response
    {
        $this->authorize('staff.edit');
        Database::instance()->delete('customer_journey_entries', 'id = :i AND staff_id = :s', ['i' => (int)$entryId, 's' => (int)$id]);
        return $this->back('success', 'ردیف حذف شد.');
    }

    /* ======================================== customer management report == */

    public function storeCustomerReport(Request $request, string $id): Response
    {
        $this->authorize('staff.edit');
        $sid = (int)$id;
        $data = Validator::validate($request->all(), [
            'line_name'        => 'required|string|max:120',
            'period_from'      => 'required|date',
            'period_to'        => 'required|date',
            'prev_period_from' => 'nullable|date',
            'prev_period_to'   => 'nullable|date',
            'analysis'         => 'nullable|string|max:2000',
        ], ['line_name' => 'نام لاین خدمات', 'period_from' => 'از تاریخ دوره', 'period_to' => 'تا تاریخ دوره']);

        $num = static fn (string $key): ?string => $request->str($key) !== '' ? (string)(float)$request->str($key) : null;
        $int = static fn (string $key): ?int => $request->str($key) !== '' ? (int)$request->str($key) : null;

        Database::instance()->insert('customer_management_reports', [
            'staff_id'                 => $sid,
            'line_name'                => $data['line_name'],
            'period_from'              => $data['period_from'],
            'period_to'                => $data['period_to'],
            'prev_period_from'         => $data['prev_period_from'] ?? null,
            'prev_period_to'           => $data['prev_period_to'] ?? null,
            'total_customers'          => $int('total_customers'),
            'total_customers_prev'     => $int('total_customers_prev'),
            'new_customers'            => $int('new_customers'),
            'new_customers_prev'       => $int('new_customers_prev'),
            'returning_customers'      => $int('returning_customers'),
            'returning_customers_prev' => $int('returning_customers_prev'),
            'loyalty_percent'          => $num('loyalty_percent'),
            'loyalty_percent_prev'     => $num('loyalty_percent_prev'),
            'sales_amount'             => $num('sales_amount'),
            'sales_amount_prev'        => $num('sales_amount_prev'),
            'sales_count'              => $int('sales_count'),
            'sales_count_prev'         => $int('sales_count_prev'),
            'salon_credit'             => $num('salon_credit'),
            'salon_credit_prev'        => $num('salon_credit_prev'),
            'offer_amount'             => $num('offer_amount'),
            'offer_amount_prev'        => $num('offer_amount_prev'),
            'offer_purchase'           => $num('offer_purchase'),
            'offer_purchase_prev'      => $num('offer_purchase_prev'),
            'other_campaigns'          => $request->str('other_campaigns') ?: null,
            'other_campaigns_prev'     => $request->str('other_campaigns_prev') ?: null,
            'rank_in_line'             => $request->str('rank_in_line') ?: null,
            'rank_in_line_prev'        => $request->str('rank_in_line_prev') ?: null,
            'analysis'                 => $data['analysis'] ?? null,
            'created_by'               => $this->userId(),
        ]);

        return $this->back('success', 'گزارش مدیریت مشتریان ثبت شد.');
    }

    public function deleteCustomerReport(Request $request, string $id, string $reportId): Response
    {
        $this->authorize('staff.edit');
        Database::instance()->delete('customer_management_reports', 'id = :i AND staff_id = :s', ['i' => (int)$reportId, 's' => (int)$id]);
        return $this->back('success', 'ردیف حذف شد.');
    }

    /* ================================================ performance evaluation == */

    public function storeEvaluation(Request $request, string $id): Response
    {
        $this->authorize('staff.edit');
        $sid = (int)$id;

        $data = Validator::validate($request->all(), [
            'period_start'  => 'required|date',
            'period_end'    => 'required|date',
            'special_score' => 'nullable|numeric|min:0|max:4',
            'summary'       => 'nullable|string|max:2000',
            'status'        => 'nullable|in:DRAFT,FINAL',
        ], ['period_start' => 'شروع دوره', 'period_end' => 'پایان دوره']);

        if (strtotime((string)$data['period_end']) < strtotime((string)$data['period_start'])) {
            return $this->back('error', 'پایان دوره نمی‌تواند قبل از شروع دوره باشد.');
        }

        $scores = [];
        foreach ($request->arr('scores') as $i => $v) {
            $scores[(int)$i] = $v === '' || $v === null ? null : (int)$v;
        }

        PerformanceEvaluationService::save(
            $sid,
            (string)$data['period_start'],
            (string)$data['period_end'],
            $scores,
            (float)($data['special_score'] ?? 0),
            $this->userId(),
            $data['summary'] ?? null,
            $data['status'] ?? 'FINAL'
        );

        AuditService::log('performance_evaluation_created', 'performance_evaluations', $sid, null, ['staff_id' => $sid]);
        return $this->redirect('/admin/staff/' . $sid . '#evaluation', 'success', 'ارزیابی عملکرد ثبت شد.');
    }

    /** یک مشتری را با کد مشتری (اختیاری) پیدا می‌کند تا فرم‌های زنده به آن متصل شوند. */
    private function resolveCustomerByCode(?string $code): ?array
    {
        $code = trim((string)$code);
        if ($code === '') {
            return null;
        }
        return Database::instance()->selectOne(
            'SELECT id, first_name, last_name, mobile FROM customers WHERE code = :c AND deleted_at IS NULL',
            ['c' => $code]
        );
    }

    /* ----- helpers ----- */

    private function formView(?array $staff): Response
    {
        $id = $staff !== null ? (int)$staff['id'] : 0;
        return $this->view('admin/staff/form', [
            'title'    => $staff !== null ? 'ویرایش کارمند' : 'کارمند جدید',
            'staff'    => $staff,
            'branches' => Database::instance()->select("SELECT id, name FROM branches WHERE deleted_at IS NULL AND status='ACTIVE' ORDER BY name"),
            'services' => Database::instance()->select("SELECT id, name FROM services WHERE deleted_at IS NULL AND status='ACTIVE' ORDER BY name"),
            'roles'    => Database::instance()->select("SELECT slug, name FROM roles WHERE slug <> 'CUSTOMER' ORDER BY id"),
            'selectedServices' => $id ? $this->repo->serviceIds($id) : [],
            'specialties' => $id ? $this->repo->specialties($id) : [],
            'shifts'   => $id ? $this->repo->weeklyShifts($id) : [],
            'weekdays' => ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'],
        ]);
    }

    private function handleAvatar(Request $request): ?string
    {
        $file = $request->file('avatar');
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return (new UploadService())->store($file, 'staff', $this->userId())['path'];
    }

    private function saveProfile(Database $db, int $staffId, array $profile): void
    {
        if ($profile === []) {
            return;
        }
        $id = $db->scalar('SELECT id FROM staff_profiles WHERE staff_id = :s', ['s' => $staffId]);
        if ($id !== null) {
            $db->update('staff_profiles', $profile, 'id = :id', ['id' => (int)$id]);
        } else {
            $db->insert('staff_profiles', array_merge(['staff_id' => $staffId], $profile));
        }
    }

    private function saveShifts(int $staffId, array $shifts): void
    {
        $db = Database::instance();
        $db->transaction(function () use ($db, $staffId, $shifts): void {
            $db->delete('staff_weekly_shifts', 'staff_id = :s', ['s' => $staffId]);
            foreach ($shifts as $weekday => $shift) {
                $weekday = (int)$weekday;
                if ($weekday < 0 || $weekday > 6 || empty($shift['enabled'])) {
                    continue;
                }
                $start = (string)($shift['start_time'] ?? '');
                $end   = (string)($shift['end_time'] ?? '');
                if (!preg_match('/^\d{2}:\d{2}/', $start) || !preg_match('/^\d{2}:\d{2}/', $end) || $end <= $start) {
                    continue;
                }
                $db->insert('staff_weekly_shifts', [
                    'staff_id'         => $staffId,
                    'weekday'          => $weekday,
                    'start_time'       => substr($start, 0, 5) . ':00',
                    'end_time'         => substr($end, 0, 5) . ':00',
                    'break_start'      => !empty($shift['break_start']) ? substr((string)$shift['break_start'], 0, 5) . ':00' : null,
                    'break_end'        => !empty($shift['break_end']) ? substr((string)$shift['break_end'], 0, 5) . ':00' : null,
                    'is_active'        => 1,
                ]);
            }
        });
    }

    /** @return array{staff:array, profile:array, account:array, service_ids:array, specialties:array, shifts:array} */
    private function validateStaff(Request $request, ?int $ignoreId = null): array
    {
        $staffRow = $ignoreId !== null ? $this->repo->find($ignoreId) : null;
        $userIgnore = $staffRow !== null && $staffRow['user_id'] !== null ? ',' . (int)$staffRow['user_id'] : '';
        $ignore = $ignoreId !== null ? ',' . $ignoreId : '';

        $data = Validator::validate($request->all(), [
            'first_name' => 'required|string|max:80',
            'last_name'  => 'required|string|max:80',
            'mobile'     => 'required|mobile|unique:staff,mobile' . $ignore,
            'email'      => 'nullable|email|max:150',
            'national_code' => 'nullable|national_code',
            'branch_id'  => 'required|int|exists:branches,id',
            'job_title'  => 'nullable|string|max:80',
            'bio'        => 'nullable|string|max:2000',
            'hire_date'  => 'nullable|date',
            'base_salary' => 'nullable|numeric|min:0',
            'commission_percent' => 'nullable|numeric|min:0|max:100',
            'commission_type' => 'nullable|in:PERCENT,FIXED,TIERED,NONE',
            'status'     => 'nullable|in:ACTIVE,INACTIVE,ON_LEAVE',
            'show_on_website' => 'nullable|bool',
            'online_booking'  => 'nullable|bool',
        ], [
            'first_name' => 'نام', 'last_name' => 'نام خانوادگی', 'mobile' => 'موبایل',
            'branch_id' => 'شعبه', 'commission_percent' => 'درصد کمیسیون',
        ]);

        $account = ['create_account' => false, 'password' => '', 'role' => 'SPECIALIST'];
        if ($ignoreId === null && $request->bool('create_account')) {
            $acc = Validator::validate($request->all(), [
                'password' => 'required|string|min:8|confirmed',
                'role'     => 'required|string|max:30',
                'mobile'   => 'required|mobile|unique:users,mobile' . $userIgnore,
            ], ['password' => 'رمز عبور', 'role' => 'نقش']);
            $account = ['create_account' => true, 'password' => (string)$acc['password'], 'role' => (string)$acc['role']];
        }

        return [
            'staff' => [
                'first_name'    => $data['first_name'],
                'last_name'     => $data['last_name'],
                'mobile'        => $data['mobile'],
                'email'         => $data['email'] ?? null,
                'branch_id'     => (int)$data['branch_id'],
                'job_title'     => $data['job_title'] ?? null,
                'bio'           => $data['bio'] ?? null,
                'hire_date'     => $data['hire_date'] ?? null,
                'base_salary'   => number_format((float)($data['base_salary'] ?? 0), 2, '.', ''),
                'commission_type'    => $data['commission_type'] ?? 'PERCENT',
                'commission_percent' => (float)($data['commission_percent'] ?? 0),
                'status'          => $data['status'] ?? 'ACTIVE',
                'is_public'       => $request->bool('show_on_website', true) ? 1 : 0,
                'online_booking'  => $request->bool('online_booking', true) ? 1 : 0,
            ],
            'profile'     => array_filter([
                'national_code' => $data['national_code'] ?? null,
            ], static fn ($v) => $v !== null && $v !== ''),
            'account'     => $account,
            'service_ids' => array_map('intval', $request->arr('service_ids')),
            'specialties' => array_values(array_filter(array_map('trim', $request->arr('specialties')))),
            'shifts'      => $request->arr('shifts'),
        ];
    }
}
