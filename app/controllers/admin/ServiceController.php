<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ServiceRepository;
use App\Services\AuditService;
use App\Services\UploadService;
use App\Validators\Validator;

final class ServiceController extends BaseController
{
    private ServiceRepository $repo;

    public function __construct()
    {
        $this->repo = new ServiceRepository();
    }

    public function index(Request $request): Response
    {
        $this->authorize('services.view');
        $filters = [
            'search'      => $request->str('search'),
            'category_id' => $request->int('category_id'),
            'status'      => $request->str('status'),
            'branch_id'   => $request->int('branch_id') ?? $this->scopedBranchId(),
        ];
        $result = $this->repo->paginate($filters, $request->page(), $request->perPage());

        if ($request->wantsJson()) {
            return $this->json($result['data'], ['total' => $result['total'], 'last_page' => $result['last_page']]);
        }
        return $this->view('admin/services/index', [
            'title'      => 'خدمات',
            'result'     => $result,
            'filters'    => $filters,
            'categories' => $this->repo->categories(),
            'branches'   => Database::instance()->select("SELECT id, name FROM branches WHERE deleted_at IS NULL AND status='ACTIVE' ORDER BY name"),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('services.create');
        return $this->formView(null);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->authorize('services.edit');
        $service = $this->repo->findFull((int)$id);
        if ($service === null) {
            $this->notFound('خدمت یافت نشد.');
        }
        return $this->formView($service);
    }

    public function store(Request $request): Response
    {
        $this->authorize('services.create');
        $data = $this->validateService($request);

        $db = Database::instance();
        $id = $db->transaction(function () use ($db, $data, $request) {
            $payload = $data['service'];
            $payload['image'] = $this->handleImage($request);
            $sid = $db->insert('services', $payload);
            $this->repo->syncStaff($sid, $data['staff_ids']);
            $this->repo->syncBranches($sid, $data['branch_ids']);
            $this->syncRecipe($sid, $data['recipe']);
            return $sid;
        });

        AuditService::log('service_created', 'services', $id, null, $data['service']);
        return $this->redirect('/admin/services/' . $id . '/edit', 'success', 'خدمت با موفقیت ثبت شد.');
    }

    public function update(Request $request, string $id): Response
    {
        $this->authorize('services.edit');
        $sid = (int)$id;
        $old = $this->repo->find($sid);
        if ($old === null) {
            $this->notFound('خدمت یافت نشد.');
        }
        $data = $this->validateService($request, $sid);

        $db = Database::instance();
        $db->transaction(function () use ($db, $sid, $data, $request): void {
            $payload = $data['service'];
            $image = $this->handleImage($request);
            if ($image !== null) {
                $payload['image'] = $image;
            }
            $db->update('services', $payload, 'id = :id', ['id' => $sid]);
            $this->repo->syncStaff($sid, $data['staff_ids']);
            $this->repo->syncBranches($sid, $data['branch_ids']);
            $this->syncRecipe($sid, $data['recipe']);
        });

        AuditService::log('service_updated', 'services', $sid, $old, $data['service']);
        return $this->redirect('/admin/services/' . $sid . '/edit', 'success', 'خدمت به‌روزرسانی شد.');
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->authorize('services.delete');
        $sid = (int)$id;
        $service = $this->repo->find($sid);
        if ($service === null) {
            $this->notFound('خدمت یافت نشد.');
        }
        $future = (int)Database::instance()->scalar(
            "SELECT COUNT(*) FROM appointment_items ai
             JOIN appointments a ON a.id = ai.appointment_id
             WHERE ai.service_id = :s AND a.starts_at > NOW() AND a.status NOT IN ('CANCELLED','COMPLETED')",
            ['s' => $sid]
        );
        if ($future > 0) {
            return $this->back('error', 'این خدمت در نوبت‌های آینده استفاده شده است.');
        }
        $this->repo->delete($sid);
        AuditService::log('service_deleted', 'services', $sid, $service, null);
        return $this->redirect('/admin/services', 'success', 'خدمت حذف شد.');
    }

    public function toggleStatus(Request $request, string $id): Response
    {
        $this->authorize('services.edit');
        $service = $this->repo->find((int)$id);
        if ($service === null) {
            $this->notFound('خدمت یافت نشد.');
        }
        $new = $service['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
        Database::instance()->update('services', ['status' => $new], 'id = :id', ['id' => (int)$id]);
        AuditService::log('service_status_changed', 'services', (int)$id, ['status' => $service['status']], ['status' => $new]);

        if ($request->wantsJson()) {
            return $this->json(['status' => $new]);
        }
        return $this->back('success', 'وضعیت خدمت تغییر کرد.');
    }

    /* ----- categories ----- */

    public function categories(Request $request): Response
    {
        $this->authorize('services.view');
        return $this->view('admin/services/categories', [
            'title'      => 'دسته‌بندی خدمات',
            'categories' => $this->repo->categories(true),
        ]);
    }

    public function storeCategory(Request $request): Response
    {
        $this->authorize('services.create');
        $data = Validator::validate($request->all(), [
            'name'        => 'required|string|max:100',
            'parent_id'   => 'nullable|int|exists:service_categories,id',
            'icon'        => 'nullable|string|max:50',
            'sort_order'  => 'nullable|int',
            'description' => 'nullable|string|max:500',
        ], ['name' => 'نام دسته']);

        Database::instance()->insert('service_categories', [
            'name'        => $data['name'],
            'slug'        => slugify((string)$data['name']),
            'parent_id'   => $data['parent_id'] ?? null,
            'icon'        => $data['icon'] ?? null,
            'sort_order'  => (int)($data['sort_order'] ?? 0),
            'description' => $data['description'] ?? null,
        ]);
        return $this->back('success', 'دسته‌بندی ثبت شد.');
    }

    public function updateCategory(Request $request, string $id): Response
    {
        $this->authorize('services.edit');
        $data = Validator::validate($request->all(), [
            'name'       => 'required|string|max:100',
            'icon'       => 'nullable|string|max:50',
            'sort_order' => 'nullable|int',
            'status'     => 'nullable|in:ACTIVE,INACTIVE',
        ], ['name' => 'نام دسته']);

        Database::instance()->update('service_categories', [
            'name'       => $data['name'],
            'slug'       => slugify((string)$data['name']),
            'icon'       => $data['icon'] ?? null,
            'sort_order' => (int)($data['sort_order'] ?? 0),
            'status'     => $data['status'] ?? 'ACTIVE',
        ], 'id = :id', ['id' => (int)$id]);
        return $this->back('success', 'دسته‌بندی به‌روزرسانی شد.');
    }

    public function destroyCategory(Request $request, string $id): Response
    {
        $this->authorize('services.delete');
        $count = (int)Database::instance()->scalar('SELECT COUNT(*) FROM services WHERE category_id = :c AND deleted_at IS NULL', ['c' => (int)$id]);
        if ($count > 0) {
            return $this->back('error', 'این دسته‌بندی دارای خدمت فعال است.');
        }
        Database::instance()->delete('service_categories', 'id = :id', ['id' => (int)$id]);
        return $this->back('success', 'دسته‌بندی حذف شد.');
    }

    /* ----- helpers ----- */

    private function formView(?array $service): Response
    {
        $id = $service !== null ? (int)$service['id'] : 0;
        return $this->view('admin/services/form', [
            'title'      => $service !== null ? 'ویرایش خدمت' : 'خدمت جدید',
            'service'    => $service,
            'categories' => $this->repo->categories(),
            'branches'   => Database::instance()->select("SELECT id, name FROM branches WHERE deleted_at IS NULL AND status='ACTIVE' ORDER BY name"),
            'staff'      => Database::instance()->select("SELECT id, first_name, last_name FROM staff WHERE deleted_at IS NULL AND status='ACTIVE' ORDER BY first_name"),
            'products'   => Database::instance()->select("SELECT id, name, unit FROM products WHERE deleted_at IS NULL AND status='ACTIVE' ORDER BY name"),
            'selectedStaff'    => $id ? $this->repo->staffIds($id) : [],
            'selectedBranches' => $id ? $this->repo->branchIds($id) : [],
            'recipe'     => $id ? $this->repo->recipe($id)['items'] : [],
        ]);
    }

    private function handleImage(Request $request): ?string
    {
        $file = $request->file('image');
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return (new UploadService())->store($file, 'services', $this->userId())['path'];
    }

    /** Replace the service recipe (products consumed per performance). */
    private function syncRecipe(int $serviceId, array $recipe): void
    {
        $db = Database::instance();
        $recipeId = $db->scalar('SELECT id FROM service_recipes WHERE service_id = :s', ['s' => $serviceId]);

        $lines = [];
        foreach ($recipe as $line) {
            $productId = (int)($line['product_id'] ?? 0);
            $qty       = (float)($line['quantity'] ?? 0);
            if ($productId > 0 && $qty > 0) {
                $lines[$productId] = ['quantity' => $qty, 'unit' => (string)($line['unit'] ?? 'گرم')];
            }
        }

        if ($lines === []) {
            if ($recipeId !== null) {
                $db->delete('recipe_items', 'recipe_id = :r', ['r' => (int)$recipeId]);
            }
            return;
        }

        if ($recipeId === null) {
            $recipeId = $db->insert('service_recipes', ['service_id' => $serviceId]);
        }
        $db->delete('recipe_items', 'recipe_id = :r', ['r' => (int)$recipeId]);
        foreach ($lines as $productId => $line) {
            $db->insert('recipe_items', [
                'recipe_id'  => (int)$recipeId,
                'product_id' => $productId,
                'quantity'   => $line['quantity'],
                'unit'       => $line['unit'],
            ]);
        }
    }

    /** @return array{service:array, staff_ids:array, branch_ids:array, recipe:array} */
    private function validateService(Request $request, ?int $ignoreId = null): array
    {
        $ignore = $ignoreId !== null ? ',' . $ignoreId : '';
        $data = Validator::validate($request->all(), [
            'name'             => 'required|string|max:150',
            'slug'             => 'nullable|string|max:160|unique:services,slug' . $ignore,
            'category_id'      => 'required|int|exists:service_categories,id',
            'price'            => 'required|numeric|min:0',
            'duration_minutes' => 'required|int|min:5|max:600',
            'buffer_minutes'   => 'nullable|int|min:0|max:120',
            'cost'             => 'nullable|numeric|min:0',
            'commission_percent' => 'nullable|numeric|min:0|max:100',
            'short_description' => 'nullable|string|max:300',
            'description'      => 'nullable|string|max:5000',
            'gender'           => 'nullable|in:FEMALE,MALE,ANY',
            'status'           => 'nullable|in:ACTIVE,INACTIVE',
            'is_featured'      => 'nullable|bool',
            'online_booking'   => 'nullable|bool',
            'requires_deposit' => 'nullable|bool',
            'deposit_amount'   => 'nullable|numeric|min:0',
            'meta_title'       => 'nullable|string|max:160',
            'meta_description' => 'nullable|string|max:300',
        ], [
            'name' => 'نام خدمت', 'category_id' => 'دسته‌بندی', 'price' => 'قیمت',
            'duration_minutes' => 'مدت زمان', 'buffer_minutes' => 'زمان آماده‌سازی',
        ]);

        $slug = $data['slug'] ?? null;
        if ($slug === null || $slug === '') {
            $slug = slugify((string)$data['name']);
        }

        return [
            'service' => [
                'name'             => $data['name'],
                'slug'             => $slug,
                'category_id'      => (int)$data['category_id'],
                'price'            => number_format((float)$data['price'], 2, '.', ''),
                'cost'             => number_format((float)($data['cost'] ?? 0), 2, '.', ''),
                'duration_minutes' => (int)$data['duration_minutes'],
                'buffer_minutes'   => (int)($data['buffer_minutes'] ?? 0),
                'commission_type'  => 'PERCENT',
                'commission_value' => (float)($data['commission_percent'] ?? 0),
                'short_description' => $data['short_description'] ?? null,
                'description'      => $data['description'] ?? null,
                'gender'           => $data['gender'] ?? 'ANY',
                'status'           => $data['status'] ?? 'ACTIVE',
                'is_featured'      => $request->bool('is_featured') ? 1 : 0,
                'online_booking'   => $request->bool('online_booking', true) ? 1 : 0,
                'requires_deposit' => $request->bool('requires_deposit') ? 1 : 0,
                'deposit_amount'   => number_format((float)($data['deposit_amount'] ?? 0), 2, '.', ''),
                'meta_title'       => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
            ],
            'staff_ids'  => array_map('intval', $request->arr('staff_ids')),
            'branch_ids' => array_map('intval', $request->arr('branch_ids')),
            'recipe'     => $request->arr('recipe'),
        ];
    }
}
