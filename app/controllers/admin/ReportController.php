<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Jalali;
use App\Services\AnalyticsService;
use App\Services\ExportService;

/**
 * Read-only reporting. Every report method returns the same shape
 * (['columns' => [...], 'rows' => [...], 'summary' => [...]]) so the view and
 * the CSV exporter can share one renderer.
 */
final class ReportController extends BaseController
{
    private const REPORTS = [
        'sales'        => 'گزارش فروش',
        'appointments' => 'گزارش نوبت‌ها',
        'staff'        => 'گزارش عملکرد پرسنل',
        'customers'    => 'گزارش مشتریان',
        'inventory'    => 'گزارش انبار',
    ];

    public function index(Request $request): Response
    {
        $this->authorize('reports.view');
        $analytics = new AnalyticsService();

        return $this->view('admin/reports/index', [
            'title'    => 'گزارش‌ها',
            'reports'  => self::REPORTS,
            'kpis'     => $analytics->dashboardKpis($this->scopedBranchId()),
            'metrics'  => $analytics->businessMetrics($this->scopedBranchId()),
            'branches' => $analytics->branchPerformance(30),
        ]);
    }

    public function sales(Request $request): Response
    {
        return $this->render('sales', $request);
    }

    public function appointments(Request $request): Response
    {
        return $this->render('appointments', $request);
    }

    public function staff(Request $request): Response
    {
        return $this->render('staff', $request);
    }

    public function customers(Request $request): Response
    {
        return $this->render('customers', $request);
    }

    public function inventory(Request $request): Response
    {
        return $this->render('inventory', $request);
    }

    public function export(Request $request, string $report): Response
    {
        $this->authorize('reports.export');
        if (!isset(self::REPORTS[$report])) {
            $this->notFound('گزارش یافت نشد.');
        }
        $result = $this->build($report, $this->range($request), $this->scopedBranchId() ?? $request->int('branch_id'));

        $rows = [];
        foreach ($result['rows'] as $row) {
            $line = [];
            foreach ($result['columns'] as $key => $label) {
                $line[$label] = $row[$key] ?? '';
            }
            $rows[] = $line;
        }

        return Response::download(
            ExportService::csv($rows),
            ExportService::filename('report-' . $report),
            'text/csv; charset=UTF-8'
        );
    }

    /* ====================================================== internals == */

    private function render(string $report, Request $request): Response
    {
        $this->authorize('reports.view');
        $range    = $this->range($request);
        $branchId = $this->scopedBranchId() ?? $request->int('branch_id');
        $result   = $this->build($report, $range, $branchId);

        return $this->view('admin/reports/show', [
            'title'    => self::REPORTS[$report],
            'report'   => $report,
            'reports'  => self::REPORTS,
            'columns'  => $result['columns'],
            'rows'     => $result['rows'],
            'summary'  => $result['summary'],
            'chart'    => $result['chart'] ?? null,
            'range'    => $range,
            'branchId' => $branchId,
            'branches' => Database::instance()->select("SELECT id, name FROM branches WHERE deleted_at IS NULL AND status='ACTIVE' ORDER BY name"),
        ]);
    }

    /** @return array{from:string,to:string,label:string} */
    private function range(Request $request): array
    {
        $from = $request->str('from') ?: date('Y-m-d', strtotime('-29 days'));
        $to   = $request->str('to') ?: date('Y-m-d');
        if (strtotime($from) === false || strtotime($to) === false || $from > $to) {
            $from = date('Y-m-d', strtotime('-29 days'));
            $to   = date('Y-m-d');
        }
        return [
            'from'  => $from,
            'to'    => $to,
            'label' => Jalali::format($from, 'j F Y') . ' تا ' . Jalali::format($to, 'j F Y'),
        ];
    }

    private function build(string $report, array $range, ?int $branchId): array
    {
        $db     = Database::instance();
        $params = ['from' => $range['from'], 'to' => $range['to']];
        $branch = '';
        if ($branchId) {
            $branch = ' AND branch_id = :b';
            $params['b'] = $branchId;
        }

        return match ($report) {
            'sales' => (function () use ($db, $params, $branch) {
                $rows = $db->select(
                    "SELECT issue_date AS day,
                            COUNT(*) AS invoices,
                            SUM(subtotal) AS subtotal,
                            SUM(discount_amount) AS discount,
                            SUM(tax_amount) AS tax,
                            SUM(total) AS total,
                            SUM(paid_amount) AS paid,
                            SUM(total - paid_amount) AS due
                     FROM invoices
                     WHERE status <> 'CANCELLED' AND issue_date BETWEEN :from AND :to{$branch}
                     GROUP BY issue_date ORDER BY issue_date",
                    $params
                );
                foreach ($rows as &$r) {
                    $r['day_fa'] = Jalali::format($r['day'], 'j F Y');
                }
                unset($r);

                return [
                    'columns' => [
                        'day_fa' => 'تاریخ', 'invoices' => 'تعداد فاکتور', 'subtotal' => 'جمع خدمات',
                        'discount' => 'تخفیف', 'tax' => 'مالیات', 'total' => 'مبلغ کل',
                        'paid' => 'پرداخت‌شده', 'due' => 'مانده',
                    ],
                    'rows'    => $rows,
                    'summary' => [
                        'مبلغ کل فروش'   => array_sum(array_column($rows, 'total')),
                        'کل تخفیف'       => array_sum(array_column($rows, 'discount')),
                        'کل دریافتی'     => array_sum(array_column($rows, 'paid')),
                        'مانده دریافتنی' => array_sum(array_column($rows, 'due')),
                    ],
                    'chart'   => [
                        'labels' => array_column($rows, 'day_fa'),
                        'values' => array_map('floatval', array_column($rows, 'total')),
                        'label'  => 'فروش روزانه',
                    ],
                ];
            })(),

            'appointments' => (function () use ($db, $params, $branch) {
                $rows = $db->select(
                    "SELECT appointment_date AS day, status, COUNT(*) AS count, SUM(total_price) AS value
                     FROM appointments
                     WHERE appointment_date BETWEEN :from AND :to{$branch}
                     GROUP BY appointment_date, status ORDER BY appointment_date, status",
                    $params
                );
                $labels = \App\Services\AppointmentService::STATUSES;
                foreach ($rows as &$r) {
                    $r['day_fa']       = Jalali::format($r['day'], 'j F Y');
                    $r['status_label'] = $labels[$r['status']] ?? $r['status'];
                }
                unset($r);

                $byStatus = [];
                foreach ($rows as $r) {
                    $byStatus[$r['status_label']] = ($byStatus[$r['status_label']] ?? 0) + (int)$r['count'];
                }

                return [
                    'columns' => ['day_fa' => 'تاریخ', 'status_label' => 'وضعیت', 'count' => 'تعداد', 'value' => 'ارزش'],
                    'rows'    => $rows,
                    'summary' => array_merge(['کل نوبت‌ها' => array_sum(array_column($rows, 'count'))], $byStatus),
                    'chart'   => ['labels' => array_keys($byStatus), 'values' => array_values($byStatus), 'label' => 'نوبت بر اساس وضعیت'],
                ];
            })(),

            'staff' => (function () use ($db, $params, $branch) {
                $staffParams = [
                    'from1' => $params['from'], 'to1' => $params['to'],
                    'from2' => $params['from'], 'to2' => $params['to'],
                ];
                if (isset($params['b'])) {
                    $staffParams['b'] = $params['b'];
                }
                $rows = $db->select(
                    "SELECT s.id, CONCAT(u.first_name,' ',u.last_name) AS staff_name, s.job_title,
                            COUNT(DISTINCT a.id) AS appointments,
                            COUNT(DISTINCT CASE WHEN a.status = 'COMPLETED' THEN a.id END) AS completed,
                            COUNT(DISTINCT CASE WHEN a.status = 'NO_SHOW' THEN a.id END) AS no_show,
                            COALESCE(SUM(CASE WHEN a.status = 'COMPLETED' THEN a.total_price END), 0) AS revenue,
                            COALESCE((SELECT SUM(c.amount) FROM commissions c
                                      WHERE c.staff_id = s.id AND c.created_at BETWEEN :from1 AND DATE_ADD(:to1, INTERVAL 1 DAY)), 0) AS commission,
                            s.rating
                     FROM staff s
                     JOIN users u ON u.id = s.user_id
                     LEFT JOIN appointments a ON a.staff_id = s.id AND a.appointment_date BETWEEN :from2 AND :to2
                     WHERE s.deleted_at IS NULL" . str_replace('branch_id', 's.branch_id', $branch) . "
                     GROUP BY s.id, staff_name, s.job_title, s.rating
                     ORDER BY revenue DESC",
                    $staffParams
                );
                return [
                    'columns' => [
                        'staff_name' => 'پرسنل', 'job_title' => 'سمت', 'appointments' => 'نوبت',
                        'completed' => 'تکمیل‌شده', 'no_show' => 'عدم مراجعه',
                        'revenue' => 'درآمد', 'commission' => 'پورسانت', 'rating' => 'امتیاز',
                    ],
                    'rows'    => $rows,
                    'summary' => [
                        'کل درآمد'   => array_sum(array_column($rows, 'revenue')),
                        'کل پورسانت' => array_sum(array_column($rows, 'commission')),
                        'کل نوبت'    => array_sum(array_column($rows, 'appointments')),
                    ],
                    'chart'   => [
                        'labels' => array_column($rows, 'staff_name'),
                        'values' => array_map('floatval', array_column($rows, 'revenue')),
                        'label'  => 'درآمد هر پرسنل',
                    ],
                ];
            })(),

            'customers' => (function () use ($db, $params) {
                $rows = $db->select(
                    "SELECT c.code, CONCAT(c.first_name,' ',c.last_name) AS customer_name, c.mobile,
                            t.name AS tier, c.visits_count, c.total_spent, c.loyalty_points,
                            c.last_visit_at, c.churn_risk
                     FROM customers c
                     LEFT JOIN loyalty_tiers t ON t.id = c.loyalty_tier_id
                     WHERE c.deleted_at IS NULL AND DATE(c.created_at) BETWEEN :from AND :to
                     ORDER BY c.total_spent DESC LIMIT 1000",
                    $params
                );
                foreach ($rows as &$r) {
                    $r['last_visit_fa'] = $r['last_visit_at'] ? Jalali::format($r['last_visit_at'], 'j F Y') : '—';
                }
                unset($r);

                return [
                    'columns' => [
                        'code' => 'کد', 'customer_name' => 'مشتری', 'mobile' => 'موبایل', 'tier' => 'سطح',
                        'visits_count' => 'مراجعه', 'total_spent' => 'مجموع خرید',
                        'loyalty_points' => 'امتیاز', 'last_visit_fa' => 'آخرین مراجعه', 'churn_risk' => 'ریسک ریزش',
                    ],
                    'rows'    => $rows,
                    'summary' => [
                        'مشتریان جدید'  => count($rows),
                        'مجموع خرید'    => array_sum(array_column($rows, 'total_spent')),
                        'میانگین خرید'  => $rows ? array_sum(array_column($rows, 'total_spent')) / count($rows) : 0,
                    ],
                ];
            })(),

            'inventory' => (function () use ($db) {
                $rows = $db->select(
                    "SELECT p.sku, p.name AS product, p.unit, b.name AS branch,
                            COALESCE(i.quantity, 0) AS quantity, p.reorder_level,
                            p.purchase_price, (COALESCE(i.quantity,0) * p.purchase_price) AS stock_value,
                            CASE WHEN COALESCE(i.quantity,0) <= p.reorder_level THEN 'نیاز به سفارش' ELSE 'کافی' END AS stock_status
                     FROM products p
                     LEFT JOIN inventory i ON i.product_id = p.id
                     LEFT JOIN branches b ON b.id = i.branch_id
                     WHERE p.deleted_at IS NULL
                     ORDER BY stock_value DESC LIMIT 1000"
                );
                return [
                    'columns' => [
                        'sku' => 'کد کالا', 'product' => 'کالا', 'branch' => 'شعبه', 'quantity' => 'موجودی',
                        'unit' => 'واحد', 'reorder_level' => 'نقطه سفارش', 'purchase_price' => 'قیمت خرید',
                        'stock_value' => 'ارزش موجودی', 'stock_status' => 'وضعیت',
                    ],
                    'rows'    => $rows,
                    'summary' => [
                        'ارزش کل انبار'  => array_sum(array_column($rows, 'stock_value')),
                        'تعداد اقلام'    => count($rows),
                        'اقلام کم‌موجود' => count(array_filter($rows, static fn ($r) => $r['stock_status'] === 'نیاز به سفارش')),
                    ],
                ];
            })(),

            default => ['columns' => [], 'rows' => [], 'summary' => []],
        };
    }
}
