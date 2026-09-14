<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ServiceRepository;
use App\Repositories\StaffRepository;
use App\Services\AuditService;
use App\Services\ExportService;
use App\Services\InvoiceService;
use App\Services\LoyaltyService;
use App\Services\PaymentService;
use App\Core\View;
use App\Validators\Validator;

final class InvoiceController extends BaseController
{
    private InvoiceService $invoices;
    private PaymentService $payments;

    public function __construct()
    {
        $this->invoices = new InvoiceService();
        $this->payments = new PaymentService();
    }

    public function index(Request $request): Response
    {
        $this->authorize('finance.view');
        $filters = [
            'search'         => $request->str('search'),
            'status'         => $request->str('status'),
            'payment_status' => $request->str('payment_status'),
            'branch_id'      => $request->int('branch_id') ?? $this->scopedBranchId(),
            'customer_id'    => $request->int('customer_id'),
            'date_from'      => $request->str('date_from'),
            'date_to'        => $request->str('date_to'),
        ];
        $result = $this->invoices->paginate($filters, $request->page(), $request->perPage());

        if ($request->wantsJson()) {
            return $this->json($result['data'], ['total' => $result['total'], 'last_page' => $result['last_page']]);
        }
        return $this->view('admin/invoices/index', [
            'title'    => 'فاکتورها',
            'result'   => $result,
            'filters'  => $filters,
            'branches' => Database::instance()->select("SELECT id, name FROM branches WHERE deleted_at IS NULL AND status='ACTIVE' ORDER BY name"),
        ]);
    }

    /** Point of sale screen. */
    public function pos(Request $request): Response
    {
        $this->authorize('finance.create');
        $branchId = $this->scopedBranchId();
        return $this->view('admin/pos/index', [
            'title'      => 'صندوق فروش',
            'categories' => (new ServiceRepository())->categories(),
            'services'   => (new ServiceRepository())->activeList($branchId),
            'products'   => Database::instance()->select(
                "SELECT id, name, sale_price AS price, unit FROM products
                 WHERE deleted_at IS NULL AND status='ACTIVE' ORDER BY name"
            ),
            'staff'      => (new StaffRepository())->activeList($branchId),
            'branches'   => Database::instance()->select("SELECT id, name FROM branches WHERE deleted_at IS NULL AND status='ACTIVE' ORDER BY name"),
            'methods'    => PaymentService::METHODS,
            'taxRate'    => (float)setting('tax_rate', 0),
            'appointment' => $request->int('appointment_id'),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->authorize('finance.create');
        $data = Validator::validate($request->all(), [
            'customer_id'     => 'required|int|exists:customers,id',
            'branch_id'       => 'required|int|exists:branches,id',
            'staff_id'        => 'nullable|int|exists:staff,id',
            'appointment_id'  => 'nullable|int|exists:appointments,id',
            'items'           => 'required|array',
            'discount_code'   => 'nullable|string|max:40',
            'manual_discount' => 'nullable|numeric|min:0',
            'loyalty_points'  => 'nullable|int|min:0',
            'notes'           => 'nullable|string|max:500',
        ], [
            'customer_id' => 'مشتری', 'branch_id' => 'شعبه', 'items' => 'اقلام فاکتور',
            'manual_discount' => 'تخفیف', 'loyalty_points' => 'امتیاز مصرفی',
        ]);

        $invoice = $this->invoices->create([
            'customer_id'     => (int)$data['customer_id'],
            'branch_id'       => (int)$data['branch_id'],
            'staff_id'        => isset($data['staff_id']) ? (int)$data['staff_id'] : null,
            'appointment_id'  => isset($data['appointment_id']) ? (int)$data['appointment_id'] : null,
            'items'           => (array)$data['items'],
            'discount_code'   => $data['discount_code'] ?? null,
            'manual_discount' => (float)($data['manual_discount'] ?? 0),
            'loyalty_points'  => (int)($data['loyalty_points'] ?? 0),
            'notes'           => $data['notes'] ?? null,
            'payments'        => $request->arr('payments'),
            'created_by'      => $this->userId(),
        ]);

        if ($request->wantsJson()) {
            return $this->json($invoice);
        }
        return $this->redirect('/admin/invoices/' . $invoice['id'], 'success', 'فاکتور صادر شد.');
    }

    public function show(Request $request, string $id): Response
    {
        $this->authorize('finance.view');
        $invoice = $this->invoices->findFull((int)$id);
        if ($invoice === null) {
            $this->notFound('فاکتور یافت نشد.');
        }
        return $this->view('admin/invoices/show', [
            'title'   => 'فاکتور ' . $invoice['invoice_number'],
            'invoice' => $invoice,
            'methods' => PaymentService::METHODS,
        ]);
    }

    public function print(Request $request, string $id): Response
    {
        $this->authorize('finance.view');
        $invoice = $this->invoices->findFull((int)$id);
        if ($invoice === null) {
            $this->notFound('فاکتور یافت نشد.');
        }
        $html = View::renderRaw('admin/invoices/print', ['invoice' => $invoice]);
        return Response::html(ExportService::printableHtml('فاکتور ' . $invoice['invoice_number'], $html));
    }

    public function pay(Request $request, string $id): Response
    {
        $this->authorize('finance.create');
        $data = Validator::validate($request->all(), [
            'method'    => 'required|in:' . implode(',', array_keys(PaymentService::METHODS)),
            'amount'    => 'required|numeric|min:1',
            'reference' => 'nullable|string|max:80',
        ], ['method' => 'روش پرداخت', 'amount' => 'مبلغ']);

        $invoice = $this->invoices->find((int)$id);
        if ($invoice === null) {
            $this->notFound('فاکتور یافت نشد.');
        }

        $payment = $this->payments->record([
            'invoice_id'  => (int)$id,
            'customer_id' => (int)$invoice['customer_id'],
            'branch_id'   => (int)$invoice['branch_id'],
            'method'      => (string)$data['method'],
            'amount'      => (float)$data['amount'],
            'reference'   => $data['reference'] ?? null,
            'created_by'  => $this->userId(),
        ]);

        if ($request->wantsJson()) {
            return $this->json($payment);
        }
        return $this->back('success', 'پرداخت ثبت شد.');
    }

    public function refund(Request $request, string $id): Response
    {
        $this->authorize('finance.refund');
        $data = Validator::validate($request->all(), [
            'amount' => 'required|numeric|min:1',
            'method' => 'required|in:CASH,POS,TRANSFER,WALLET',
            'reason' => 'nullable|string|max:255',
        ], ['amount' => 'مبلغ', 'method' => 'روش بازپرداخت', 'reason' => 'دلیل']);

        $refund = $this->payments->refund((int)$id, (float)$data['amount'], (string)$data['method'], $data['reason'] ?? null);
        AuditService::log('invoice_refunded', 'invoices', (int)$id, null, $refund);

        if ($request->wantsJson()) {
            return $this->json($refund);
        }
        return $this->back('success', 'بازپرداخت ثبت شد.');
    }

    public function cancel(Request $request, string $id): Response
    {
        $this->authorize('finance.refund');
        $invoice = $this->invoices->cancel((int)$id, $request->str('reason') ?: null);
        if ($request->wantsJson()) {
            return $this->json($invoice);
        }
        return $this->back('success', 'فاکتور باطل شد.');
    }

    /** Live totals preview for the POS (no persistence). */
    public function quote(Request $request): Response
    {
        $this->authorize('finance.create');
        $items    = $request->arr('items');
        $subtotal = 0.0;
        $repo     = new ServiceRepository();
        $db       = Database::instance();
        $lines    = [];

        foreach ($items as $raw) {
            $qty  = max(0.01, (float)($raw['quantity'] ?? 1));
            $type = strtoupper((string)($raw['type'] ?? 'SERVICE'));
            if ($type === 'SERVICE') {
                $svc = $repo->find((int)($raw['service_id'] ?? 0));
                if ($svc === null) {
                    continue;
                }
                $unit  = (float)$svc['price'];
                $title = (string)$svc['name'];
            } else {
                $p = $db->selectOne('SELECT name, sale_price FROM products WHERE id = :p AND deleted_at IS NULL', ['p' => (int)($raw['product_id'] ?? 0)]);
                if ($p === null) {
                    continue;
                }
                $unit  = (float)$p['sale_price'];
                $title = (string)$p['name'];
            }
            $discount = max(0.0, (float)($raw['discount'] ?? 0));
            $total    = max(0.0, $unit * $qty - $discount);
            $subtotal += $total;
            $lines[]  = ['title' => $title, 'quantity' => $qty, 'unit_price' => $unit, 'discount' => $discount, 'total' => $total];
        }

        $discount = max(0.0, (float)$request->float('manual_discount', 0));
        $codeInfo = null;
        $code     = $request->str('discount_code');
        if ($code !== '') {
            $codeInfo = $this->invoices->applyDiscountCode($code, $subtotal, $request->int('customer_id'));
            $discount += (float)$codeInfo['amount'];
        }

        $loyalty = ['points' => 0, 'amount' => 0.0];
        $points  = $request->int('loyalty_points') ?? 0;
        if ($points > 0 && $request->int('customer_id')) {
            $loyalty = (new LoyaltyService())->calculateRedemption((int)$request->int('customer_id'), $points, max(0.0, $subtotal - $discount));
        }

        $taxRate  = (float)setting('tax_rate', 0);
        $taxable  = max(0.0, $subtotal - $discount - (float)$loyalty['amount']);
        $tax      = round($taxable * $taxRate / 100, 2);
        $total    = round($taxable + $tax, 2);

        return $this->json([
            'lines'    => $lines,
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'discount_code' => $codeInfo,
            'loyalty'  => $loyalty,
            'tax_rate' => $taxRate,
            'tax'      => $tax,
            'total'    => $total,
        ]);
    }

    public function export(Request $request): Response
    {
        $this->authorize('finance.view');
        $result = $this->invoices->paginate([
            'payment_status' => $request->str('status'),
            'branch_id'      => $request->int('branch_id') ?? $this->scopedBranchId(),
            'date_from'      => $request->str('date_from'),
            'date_to'        => $request->str('date_to'),
        ], 1, 10000);

        $rows = array_map(static fn ($i) => [
            'شماره فاکتور' => $i['invoice_number'],
            'تاریخ'        => $i['issue_date'],
            'مشتری'        => trim(($i['first_name'] ?? '') . ' ' . ($i['last_name'] ?? '')),
            'شعبه'         => $i['branch_name'] ?? '',
            'جمع کل'       => $i['subtotal'],
            'تخفیف'        => $i['discount_amount'],
            'مالیات'       => $i['tax_amount'],
            'قابل پرداخت'  => $i['total'],
            'پرداخت‌شده'    => $i['paid_amount'],
            'وضعیت'        => $i['payment_status'],
        ], $result['data']);

        return Response::download(ExportService::csv($rows), ExportService::filename('invoices'), 'text/csv; charset=UTF-8');
    }
}
