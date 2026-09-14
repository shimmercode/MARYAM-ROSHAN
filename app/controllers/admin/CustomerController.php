<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\CustomerRepository;
use App\Services\AuditService;
use App\Services\ExportService;
use App\Services\LoyaltyService;
use App\Services\PaymentService;
use App\Validators\Validator;

final class CustomerController extends BaseController
{
    private CustomerRepository $repo;

    public function __construct()
    {
        $this->repo = new CustomerRepository();
    }

    public function index(Request $request): Response
    {
        $this->authorize('customers.view');
        $filters = [
            'search'        => $request->str('search'),
            'status'        => $request->str('status'),
            'branch_id'     => $request->int('branch_id'),
            'tier_id'       => $request->int('tier_id'),
            'tag_id'        => $request->int('tag_id'),
            'inactive_days' => $request->int('inactive_days'),
            'sort'          => $request->str('sort'),
            'dir'           => $request->str('dir', 'DESC'),
        ];
        $result = $this->repo->paginate($filters, $request->page(), $request->perPage());

        if ($request->wantsJson()) {
            return $this->json($result['data'], [
                'total' => $result['total'], 'page' => $result['page'], 'last_page' => $result['last_page'],
            ]);
        }

        return $this->view('admin/customers/index', [
            'title'    => 'مشتریان',
            'result'   => $result,
            'filters'  => $filters,
            'branches' => Database::instance()->select("SELECT id, name FROM branches WHERE deleted_at IS NULL AND status='ACTIVE' ORDER BY name"),
            'tiers'    => Database::instance()->select('SELECT id, name FROM loyalty_tiers ORDER BY min_points'),
            'tags'     => Database::instance()->select('SELECT id, name, color FROM customer_tags ORDER BY name'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('customers.create');
        return $this->view('admin/customers/form', [
            'title'    => 'مشتری جدید',
            'customer' => null,
            'branches' => Database::instance()->select("SELECT id, name FROM branches WHERE deleted_at IS NULL AND status='ACTIVE' ORDER BY name"),
            'staff'    => Database::instance()->select("SELECT id, first_name, last_name FROM staff WHERE deleted_at IS NULL AND status='ACTIVE' ORDER BY first_name"),
            'tags'     => Database::instance()->select('SELECT id, name, color FROM customer_tags ORDER BY name'),
            'selectedTags' => [],
        ]);
    }

    public function store(Request $request): Response
    {
        $this->authorize('customers.create');
        $data = $this->validateCustomer($request);

        $db = Database::instance();
        $id = $db->transaction(function () use ($db, $data) {
            $payload = $data['customer'];
            $payload['code']       = $this->repo->nextCode();
            $payload['created_by'] = $this->userId();
            $cid = $db->insert('customers', $payload);

            $db->insert('customer_loyalty', ['customer_id' => $cid, 'points_balance' => 0]);
            $db->insert('wallet_accounts', ['customer_id' => $cid, 'balance' => '0.00']);
            if ($data['profile'] !== []) {
                $db->insert('customer_profiles', array_merge(['customer_id' => $cid], $data['profile']));
            }
            if ($data['tags'] !== []) {
                $this->repo->syncTags($cid, $data['tags']);
            }
            (new LoyaltyService($db))->syncTier($cid, 0);
            return $cid;
        });

        AuditService::log('customer_created', 'customers', $id, null, $data['customer']);
        \App\Services\AutomationService::fire('customer_created', ['customer_id' => $id]);

        if ($request->wantsJson()) {
            return $this->json($this->repo->findFull($id));
        }
        return $this->redirect('/admin/customers/' . $id, 'success', 'مشتری با موفقیت ثبت شد.');
    }

    public function show(Request $request, string $id): Response
    {
        $this->authorize('customers.view');
        $customer = $this->repo->findFull((int)$id);
        if ($customer === null) {
            $this->notFound('مشتری یافت نشد.');
        }
        $cid = (int)$id;

        return $this->view('admin/customers/show', [
            'title'        => $customer['first_name'] . ' ' . $customer['last_name'],
            'customer'     => $customer,
            'tags'         => $this->repo->tags($cid),
            'appointments' => $this->repo->appointments($cid),
            'invoices'     => $this->repo->invoices($cid),
            'payments'     => $this->repo->payments($cid),
            'services'     => $this->repo->services($cid),
            'notes'        => $this->repo->notes($cid),
            'loyalty'      => $this->repo->loyaltyHistory($cid),
            'wallet'       => $this->repo->walletHistory($cid),
            'reviews'      => $this->repo->reviews($cid),
            'activity'     => $this->repo->activity($cid),
            'walletBalance' => (new PaymentService())->walletBalance($cid),
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->authorize('customers.edit');
        $customer = $this->repo->findFull((int)$id);
        if ($customer === null) {
            $this->notFound('مشتری یافت نشد.');
        }
        $profile = Database::instance()->selectOne('SELECT * FROM customer_profiles WHERE customer_id = :c', ['c' => (int)$id]);

        return $this->view('admin/customers/form', [
            'title'    => 'ویرایش مشتری',
            'customer' => array_merge($customer, $profile ?? []),
            'branches' => Database::instance()->select("SELECT id, name FROM branches WHERE deleted_at IS NULL AND status='ACTIVE' ORDER BY name"),
            'staff'    => Database::instance()->select("SELECT id, first_name, last_name FROM staff WHERE deleted_at IS NULL AND status='ACTIVE' ORDER BY first_name"),
            'tags'     => Database::instance()->select('SELECT id, name, color FROM customer_tags ORDER BY name'),
            'selectedTags' => array_column($this->repo->tags((int)$id), 'id'),
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->authorize('customers.edit');
        $cid = (int)$id;
        $old = $this->repo->find($cid);
        if ($old === null) {
            $this->notFound('مشتری یافت نشد.');
        }
        $data = $this->validateCustomer($request, $cid);

        $db = Database::instance();
        $db->transaction(function () use ($db, $cid, $data): void {
            $db->update('customers', $data['customer'], 'id = :id', ['id' => $cid]);
            if ($data['profile'] !== []) {
                $exists = $db->scalar('SELECT id FROM customer_profiles WHERE customer_id = :c', ['c' => $cid]);
                if ($exists) {
                    $db->update('customer_profiles', $data['profile'], 'id = :id', ['id' => (int)$exists]);
                } else {
                    $db->insert('customer_profiles', array_merge(['customer_id' => $cid], $data['profile']));
                }
            }
            $this->repo->syncTags($cid, $data['tags']);
        });

        AuditService::log('customer_updated', 'customers', $cid, $old, $data['customer']);

        if ($request->wantsJson()) {
            return $this->json($this->repo->findFull($cid));
        }
        return $this->redirect('/admin/customers/' . $cid, 'success', 'اطلاعات مشتری به‌روزرسانی شد.');
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->authorize('customers.delete');
        $cid = (int)$id;
        $customer = $this->repo->find($cid);
        if ($customer === null) {
            $this->notFound('مشتری یافت نشد.');
        }
        $futureAppointments = (int)Database::instance()->scalar(
            "SELECT COUNT(*) FROM appointments WHERE customer_id = :c AND starts_at > NOW() AND status NOT IN ('CANCELLED','COMPLETED')",
            ['c' => $cid]
        );
        if ($futureAppointments > 0) {
            return $this->back('error', 'این مشتری نوبت فعال دارد؛ ابتدا نوبت‌ها را لغو کنید.');
        }

        $this->repo->delete($cid);
        AuditService::log('customer_deleted', 'customers', $cid, $customer, null);

        if ($request->wantsJson()) {
            return $this->json(['deleted' => true]);
        }
        return $this->redirect('/admin/customers', 'success', 'مشتری حذف شد.');
    }

    public function addNote(Request $request, string $id): Response
    {
        $this->authorize('customers.edit');
        $data = Validator::validate($request->only(['note', 'is_pinned']), [
            'note' => 'required|string|max:2000',
        ], ['note' => 'یادداشت']);

        Database::instance()->insert('customer_notes', [
            'customer_id' => (int)$id,
            'user_id'     => $this->userId(),
            'note'        => (string)$data['note'],
            'is_pinned'   => $request->bool('is_pinned') ? 1 : 0,
        ]);
        return $this->back('success', 'یادداشت ثبت شد.');
    }

    public function adjustLoyalty(Request $request, string $id): Response
    {
        $this->authorize('customers.edit');
        $data = Validator::validate($request->only(['points', 'description']), [
            'points'      => 'required|int',
            'description' => 'nullable|string|max:255',
        ], ['points' => 'امتیاز', 'description' => 'توضیح']);

        $balance = (new LoyaltyService())->adjust(
            (int)$id,
            (int)$data['points'],
            'ADJUST',
            (string)($data['description'] ?? 'تنظیم دستی امتیاز')
        );
        if ($request->wantsJson()) {
            return $this->json(['balance' => $balance]);
        }
        return $this->back('success', 'امتیاز مشتری به‌روزرسانی شد.');
    }

    public function walletTopUp(Request $request, string $id): Response
    {
        $this->authorize('finance.create');
        $data = Validator::validate($request->only(['amount', 'method', 'reference']), [
            'amount'    => 'required|numeric|min:1000',
            'method'    => 'required|in:CASH,POS,TRANSFER,ONLINE',
            'reference' => 'nullable|string|max:80',
        ], ['amount' => 'مبلغ', 'method' => 'روش پرداخت']);

        $result = (new PaymentService())->topUpWallet((int)$id, (float)$data['amount'], (string)$data['method'], $data['reference'] ?? null);
        if ($request->wantsJson()) {
            return $this->json($result);
        }
        return $this->back('success', 'کیف پول با موفقیت شارژ شد.');
    }

    public function export(Request $request): Response
    {
        $this->authorize('customers.view');
        $result = $this->repo->paginate([
            'search' => $request->str('search'), 'status' => $request->str('status'),
        ], 1, 10000);

        $rows = array_map(static fn ($c) => [
            'کد'          => $c['code'],
            'نام'         => $c['first_name'],
            'نام خانوادگی' => $c['last_name'],
            'موبایل'      => $c['mobile'],
            'وضعیت'       => $c['status'],
            'تعداد مراجعه' => $c['visits_count'],
            'مجموع خرید'  => $c['total_spent'],
            'امتیاز'      => $c['loyalty_points'],
            'آخرین مراجعه' => $c['last_visit_at'],
        ], $result['data']);

        AuditService::log('customers_exported', 'customers', null, null, ['count' => count($rows)]);
        return Response::download(ExportService::csv($rows), ExportService::filename('customers'), 'text/csv; charset=UTF-8');
    }

    /** Typeahead used by POS and the booking wizard. */
    public function search(Request $request): Response
    {
        $this->authorize('customers.view');
        $term = $request->str('q');
        if (mb_strlen($term) < 2) {
            return $this->json([]);
        }
        return $this->json($this->repo->search($term, 12));
    }

    /** @return array{customer:array, profile:array, tags:array} */
    private function validateCustomer(Request $request, ?int $ignoreId = null): array
    {
        $ignore = $ignoreId !== null ? ',' . $ignoreId : '';
        $data = Validator::validate($request->all(), [
            'first_name'    => 'required|string|max:80',
            'last_name'     => 'required|string|max:80',
            'mobile'        => 'required|mobile|unique:customers,mobile' . $ignore,
            'email'         => 'nullable|email|max:150',
            'gender'        => 'nullable|in:FEMALE,MALE,OTHER',
            'birth_date'    => 'nullable|date',
            'national_code' => 'nullable|national_code',
            'preferred_branch_id' => 'nullable|int|exists:branches,id',
            'preferred_staff_id'  => 'nullable|int|exists:staff,id',
            'source'        => 'nullable|string|max:50',
            'status'        => 'nullable|in:ACTIVE,INACTIVE,BLACKLIST',
            'marketing_opt_in' => 'nullable|bool',
        ], [
            'first_name' => 'نام', 'last_name' => 'نام خانوادگی', 'mobile' => 'موبایل',
            'email' => 'ایمیل', 'birth_date' => 'تاریخ تولد', 'national_code' => 'کد ملی',
        ]);

        $customer = [
            'first_name'          => $data['first_name'],
            'last_name'           => $data['last_name'],
            'mobile'              => $data['mobile'],
            'email'               => $data['email'] ?? null,
            'gender'              => $data['gender'] ?? 'FEMALE',
            'birth_date'          => $data['birth_date'] ?? null,
            'national_code'       => $data['national_code'] ?? null,
            'preferred_branch_id' => $data['preferred_branch_id'] ?? null,
            'preferred_staff_id'  => $data['preferred_staff_id'] ?? null,
            'source'              => $data['source'] ?? null,
            'status'              => $data['status'] ?? 'ACTIVE',
            'marketing_opt_in'    => $request->bool('marketing_opt_in', true) ? 1 : 0,
        ];

        $profileData = Validator::validate($request->all(), [
            'skin_type'     => 'nullable|string|max:50',
            'hair_type'     => 'nullable|string|max:50',
            'allergies'     => 'nullable|string|max:1000',
            'medical_notes' => 'nullable|string|max:1000',
            'preferences'   => 'nullable|string|max:1000',
            'instagram'     => 'nullable|string|max:80',
        ]);
        $profile = array_filter($profileData, static fn ($v) => $v !== null && $v !== '');

        return ['customer' => $customer, 'profile' => $profile, 'tags' => $request->arr('tags')];
    }
}
