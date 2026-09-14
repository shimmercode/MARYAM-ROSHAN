<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\ExportService;
use App\Services\InventoryService;
use App\Validators\Validator;

final class InventoryController extends BaseController
{
    private InventoryService $inventory;

    public function __construct()
    {
        $this->inventory = new InventoryService();
    }

    public function index(Request $request): Response
    {
        $this->authorize('inventory.view');
        $branchId = $request->int('branch_id') ?? $this->scopedBranchId();
        $db       = Database::instance();

        $params = [];
        $where  = 'WHERE p.deleted_at IS NULL';
        if ($branchId) {
            $where .= ' AND i.branch_id = :b';
            $params['b'] = $branchId;
        }
        $search = $request->str('search');
        if ($search !== '') {
            $where .= ' AND (p.name LIKE :q OR p.sku LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }

        return $this->view('admin/inventory/index', [
            'title'   => 'انبار',
            'levels'  => $db->select(
                "SELECT p.id, p.name, p.sku, p.unit, p.reorder_level, p.purchase_price, p.sale_price,
                        COALESCE(i.quantity, 0) AS quantity, COALESCE(i.reserved, 0) AS reserved,
                        b.name AS branch_name, b.id AS branch_id,
                        (COALESCE(i.quantity,0) * p.purchase_price) AS stock_value
                 FROM products p
                 LEFT JOIN inventory i ON i.product_id = p.id
                 LEFT JOIN branches b  ON b.id = i.branch_id
                 {$where}
                 ORDER BY (COALESCE(i.quantity,0) <= p.reorder_level) DESC, p.name
                 LIMIT 300",
                $params
            ),
            'lowStock' => $this->inventory->lowStock($branchId, 50),
            'branches' => $db->select("SELECT id, name FROM branches WHERE deleted_at IS NULL AND status='ACTIVE' ORDER BY name"),
            'filters'  => ['branch_id' => $branchId, 'search' => $search],
            'totals'   => [
                'value' => (float)$db->scalar(
                    'SELECT COALESCE(SUM(i.quantity * p.purchase_price), 0)
                     FROM inventory i JOIN products p ON p.id = i.product_id WHERE p.deleted_at IS NULL'
                ),
                'products' => (int)$db->scalar("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND status='ACTIVE'"),
            ],
        ]);
    }

    public function products(Request $request): Response
    {
        $this->authorize('inventory.view');
        $db     = Database::instance();
        $page   = $request->page();
        $per    = $request->perPage();
        $search = $request->str('search');

        $where  = 'WHERE p.deleted_at IS NULL';
        $params = [];
        if ($search !== '') {
            $where .= ' AND (p.name LIKE :q OR p.sku LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        $total = (int)$db->scalar("SELECT COUNT(*) FROM products p {$where}", $params);
        $rows  = $db->select(
            "SELECT p.id, p.name, p.sku, p.unit, p.purchase_price, p.sale_price, p.is_retail,
                    p.reorder_level, p.status, c.name AS category_name,
                    (SELECT COALESCE(SUM(quantity),0) FROM inventory WHERE product_id = p.id) AS total_quantity
             FROM products p LEFT JOIN product_categories c ON c.id = p.category_id
             {$where} ORDER BY p.name LIMIT {$per} OFFSET " . (($page - 1) * $per),
            $params
        );

        return $this->view('admin/inventory/products', [
            'title'    => 'کالاها',
            'products' => $rows,
            'page'     => $page,
            'lastPage' => max(1, (int)ceil($total / $per)),
            'total'    => $total,
            'search'   => $search,
        ]);
    }

    public function createProduct(Request $request): Response
    {
        $this->authorize('inventory.manage');
        return $this->productForm(null);
    }

    public function editProduct(Request $request, string $id): Response
    {
        $this->authorize('inventory.manage');
        $product = Database::instance()->selectOne('SELECT * FROM products WHERE id = :id AND deleted_at IS NULL', ['id' => (int)$id]);
        if ($product === null) {
            $this->notFound('کالا یافت نشد.');
        }
        return $this->productForm($product);
    }

    public function storeProduct(Request $request): Response
    {
        $this->authorize('inventory.manage');
        $data = $this->validateProduct($request);

        $db = Database::instance();
        $id = $db->transaction(function () use ($db, $data, $request) {
            $pid = $db->insert('products', $data);
            $initial = (float)$request->float('initial_quantity', 0);
            $branchId = $request->int('branch_id');
            if ($initial > 0 && $branchId) {
                (new InventoryService($db))->move(
                    $pid, $branchId, 'IN', $initial, 'INITIAL', null,
                    'موجودی اولیه', (float)$data['purchase_price']
                );
            }
            return $pid;
        });

        AuditService::log('product_created', 'products', $id, null, $data);
        return $this->redirect('/admin/inventory/products', 'success', 'کالا ثبت شد.');
    }

    public function updateProduct(Request $request, string $id): Response
    {
        $this->authorize('inventory.manage');
        $pid = (int)$id;
        $old = Database::instance()->selectOne('SELECT * FROM products WHERE id = :id', ['id' => $pid]);
        if ($old === null) {
            $this->notFound('کالا یافت نشد.');
        }
        $data = $this->validateProduct($request, $pid);
        Database::instance()->update('products', $data, 'id = :id', ['id' => $pid]);
        AuditService::log('product_updated', 'products', $pid, $old, $data);

        return $this->redirect('/admin/inventory/products', 'success', 'کالا به‌روزرسانی شد.');
    }

    /** Manual stock adjustment (IN / OUT / correction). */
    public function adjust(Request $request): Response
    {
        $this->authorize('inventory.manage');
        $data = Validator::validate($request->all(), [
            'product_id' => 'required|int|exists:products,id',
            'branch_id'  => 'required|int|exists:branches,id',
            'type'       => 'required|in:IN,OUT,ADJUST,WASTE',
            'quantity'   => 'required|numeric',
            'note'       => 'nullable|string|max:255',
            'unit_cost'  => 'nullable|numeric|min:0',
        ], [
            'product_id' => 'کالا', 'branch_id' => 'شعبه', 'type' => 'نوع تراکنش',
            'quantity' => 'مقدار', 'note' => 'توضیح',
        ]);

        $balance = $this->inventory->move(
            (int)$data['product_id'],
            (int)$data['branch_id'],
            (string)$data['type'],
            abs((float)$data['quantity']),
            'MANUAL',
            null,
            $data['note'] ?? null,
            (float)($data['unit_cost'] ?? 0)
        );

        if ($request->wantsJson()) {
            return $this->json(['balance' => $balance]);
        }
        return $this->back('success', 'موجودی به‌روزرسانی شد.');
    }

    public function transactions(Request $request): Response
    {
        $this->authorize('inventory.view');
        $productId = $request->int('product_id');
        $db        = Database::instance();

        if ($request->str('export') === 'csv') {
            $rows = $db->select(
                "SELECT t.created_at, p.name AS product, t.type, t.quantity, t.balance_after, t.note
                 FROM inventory_transactions t JOIN products p ON p.id = t.product_id
                 ORDER BY t.id DESC LIMIT 5000"
            );
            $csv = array_map(static fn ($r) => [
                'تاریخ' => $r['created_at'], 'کالا' => $r['product'], 'نوع' => $r['type'],
                'مقدار' => $r['quantity'], 'مانده' => $r['balance_after'], 'توضیح' => $r['note'],
            ], $rows);
            return Response::download(ExportService::csv($csv), ExportService::filename('inventory-transactions'), 'text/csv; charset=UTF-8');
        }

        return $this->view('admin/inventory/transactions', [
            'title'        => 'گردش انبار',
            'transactions' => $productId
                ? $this->inventory->transactions($productId, 200)
                : $db->select(
                    "SELECT t.id, t.type, t.quantity, t.balance_after, t.note, t.created_at,
                            p.name AS product_name, p.unit, b.name AS branch_name,
                            CONCAT(u.first_name,' ',u.last_name) AS user_name
                     FROM inventory_transactions t
                     JOIN products p ON p.id = t.product_id
                     LEFT JOIN branches b ON b.id = t.branch_id
                     LEFT JOIN users u ON u.id = t.created_by
                     ORDER BY t.id DESC LIMIT 200"
                ),
            'products'  => $db->select("SELECT id, name FROM products WHERE deleted_at IS NULL ORDER BY name"),
            'productId' => $productId,
        ]);
    }

    /* ----- helpers ----- */

    private function productForm(?array $product): Response
    {
        $db = Database::instance();
        return $this->view('admin/inventory/product_form', [
            'title'      => $product !== null ? 'ویرایش کالا' : 'کالای جدید',
            'product'    => $product,
            'categories' => $db->select('SELECT id, name FROM product_categories ORDER BY name'),
            'brands'     => $db->select('SELECT id, name FROM product_brands ORDER BY name'),
            'branches'   => $db->select("SELECT id, name FROM branches WHERE deleted_at IS NULL AND status='ACTIVE' ORDER BY name"),
        ]);
    }

    private function validateProduct(Request $request, ?int $ignoreId = null): array
    {
        $ignore = $ignoreId !== null ? ',' . $ignoreId : '';
        $data = Validator::validate($request->all(), [
            'name'           => 'required|string|max:150',
            'sku'            => 'required|string|max:40|unique:products,sku' . $ignore,
            'category_id'    => 'nullable|int|exists:product_categories,id',
            'brand_id'       => 'nullable|int|exists:product_brands,id',
            'unit'           => 'required|string|max:20',
            'purchase_price' => 'required|numeric|min:0',
            'sale_price'     => 'nullable|numeric|min:0',
            'reorder_level'  => 'nullable|numeric|min:0',
            'is_retail'      => 'nullable|bool',
            'status'         => 'nullable|in:ACTIVE,INACTIVE',
        ], [
            'name' => 'نام کالا', 'sku' => 'کد کالا', 'unit' => 'واحد',
            'purchase_price' => 'قیمت خرید', 'sale_price' => 'قیمت فروش',
        ]);

        return [
            'name'           => $data['name'],
            'sku'            => $data['sku'],
            'category_id'    => $data['category_id'] ?? null,
            'brand_id'       => $data['brand_id'] ?? null,
            'unit'           => $data['unit'],
            'purchase_price' => number_format((float)$data['purchase_price'], 2, '.', ''),
            'sale_price'     => number_format((float)($data['sale_price'] ?? 0), 2, '.', ''),
            'reorder_level'  => (float)($data['reorder_level'] ?? 0),
            'is_retail'      => $request->bool('is_retail') ? 1 : 0,
            'status'         => $data['status'] ?? 'ACTIVE',
        ];
    }
}
