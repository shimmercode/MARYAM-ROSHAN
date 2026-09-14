<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\LiveStatusService;

final class LiveStatusController extends BaseController
{
    public function heartbeat(Request $request): Response
    {
        $user = AuthService::currentUser();
        $staff = $user['staff'] ?? null;
        if (!$staff) return $this->fail('STAFF_PROFILE_REQUIRED', 'حساب شما به پرونده پرسنلی متصل نیست.', 403);
        (new LiveStatusService())->heartbeat((int)$staff['id'], $request);
        return $this->json(['ok' => true, 'server_time' => date(DATE_ATOM)]);
    }

    private function staffId(): ?int
    {
        $staff = AuthService::currentUser()['staff'] ?? null;
        return $staff ? (int)$staff['id'] : null;
    }

    public function clockIn(Request $request): Response
    {
        $id = $this->staffId(); if (!$id) return $this->fail('STAFF_PROFILE_REQUIRED', 'پرونده پرسنلی یافت نشد.', 403);
        (new LiveStatusService())->clockIn($id); return $this->json(['status' => 'PRESENT']);
    }

    public function clockOut(Request $request): Response
    {
        $id = $this->staffId(); if (!$id) return $this->fail('STAFF_PROFILE_REQUIRED', 'پرونده پرسنلی یافت نشد.', 403);
        (new LiveStatusService())->clockOut($id); return $this->json(['status' => 'OFFLINE']);
    }

    public function startBreak(Request $request): Response
    {
        $id = $this->staffId(); if (!$id) return $this->fail('STAFF_PROFILE_REQUIRED', 'پرونده پرسنلی یافت نشد.', 403);
        (new LiveStatusService())->startBreak($id, $request->str('reason')); return $this->json(['status' => 'BREAK']);
    }

    public function endBreak(Request $request): Response
    {
        $id = $this->staffId(); if (!$id) return $this->fail('STAFF_PROFILE_REQUIRED', 'پرونده پرسنلی یافت نشد.', 403);
        (new LiveStatusService())->endBreak($id); return $this->json(['status' => 'PRESENT']);
    }

    public function startService(Request $request): Response
    {
        $staff = (AuthService::currentUser()['staff'] ?? null);
        if (!$staff) return $this->fail('STAFF_PROFILE_REQUIRED', 'پرونده پرسنلی یافت نشد.', 403);
        $appointment = $request->int('appointment_id');
        if (!$appointment) return $this->fail('VALIDATION_ERROR', 'شناسه نوبت الزامی است.', 422);
        (new LiveStatusService())->startService((int)$staff['id'], $appointment, $request->int('service_id'), $request->int('seat_id'));
        return $this->json(['ok' => true, 'status' => 'SERVING']);
    }

    public function endService(Request $request): Response
    {
        $staff = (AuthService::currentUser()['staff'] ?? null);
        if (!$staff) return $this->fail('STAFF_PROFILE_REQUIRED', 'پرونده پرسنلی یافت نشد.', 403);
        $appointment = $request->int('appointment_id');
        if (!$appointment) return $this->fail('VALIDATION_ERROR', 'شناسه نوبت الزامی است.', 422);
        (new LiveStatusService())->endService((int)$staff['id'], $appointment);
        return $this->json(['ok' => true, 'status' => 'AVAILABLE']);
    }

    public function index(Request $request): Response
    {
        $branch = $this->scopedBranchId();
        if ($branch === null && $request->int('branch_id')) $branch = $request->int('branch_id');
        $since = $request->query('since');
        $service = new LiveStatusService();
        return $this->json([
            'summary' => $service->summary($branch),
            'staff' => $service->statuses($branch, is_string($since) && $since !== '' ? $since : null),
            'activities' => $service->activities($branch, 12),
            'alerts' => $service->alerts($branch),
            'server_time' => date(DATE_ATOM),
        ]);
    }
}
