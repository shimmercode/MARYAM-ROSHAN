<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AppointmentRepository;
use App\Services\AnalyticsService;
use App\Services\InventoryService;
use App\Services\PaymentService;
use App\Services\LiveStatusService;

final class DashboardController extends BaseController
{
    public function index(Request $request): Response
    {
        $branchId  = $this->scopedBranchId();
        $analytics = new AnalyticsService();

        return $this->view('admin/dashboard', [
            'title'        => 'داشبورد',
            'kpis'         => $analytics->dashboardKpis($branchId),
            'metrics'      => $analytics->businessMetrics($branchId),
            'todayList'    => (new AppointmentRepository())->todayList($branchId, 12),
            'upcoming'     => (new AppointmentRepository())->upcoming($branchId, 8),
            'recentPayments' => (new PaymentService())->recent(8, $branchId),
            'alerts'       => $analytics->customerAlerts(6),
            'lowStock'     => (new InventoryService())->lowStock($branchId, 5),
            'topServices'  => $analytics->topServices(6, $branchId),
            'staffPerf'    => $analytics->staffPerformance(6, $branchId),
            'branchPerf'   => $branchId === null ? $analytics->branchPerformance() : [],
            'liveSummary' => $this->liveSummary($branchId),
        ]);
    }

    private function liveSummary(?int $branchId): array
    {
        try {
            return (new LiveStatusService())->summary($branchId);
        } catch (\Throwable) {
            return ['staff' => [], 'total' => 0, 'updated_at' => date(DATE_ATOM), 'available' => false];
        }
    }

    /** JSON feed for the dashboard charts. */
    public function charts(Request $request): Response
    {
        $branchId  = $this->scopedBranchId();
        $days      = min(180, max(7, (int)$request->query('days', 30)));
        $analytics = new AnalyticsService();

        return $this->json([
            'revenue'      => $analytics->revenueSeries($days, $branchId),
            'appointments' => $analytics->appointmentSeries($days, $branchId),
            'services'     => $analytics->topServices(8, $branchId, $days),
            'staff'        => $analytics->staffPerformance(8, $branchId, $days),
            'branches'     => $branchId === null ? $analytics->branchPerformance($days) : [],
        ]);
    }
}
