<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Jalali;
use App\Services\NotificationService;

final class NotificationController extends BaseController
{
    public function index(Request $request): Response
    {
        $userId = (int)$this->userId();
        $status = strtoupper($request->str('status', 'ALL'));
        if (!in_array($status, ['ALL', 'UNREAD', 'READ', 'ARCHIVED'], true)) {
            $status = 'ALL';
        }

        $items = NotificationService::listFor($userId, $status, 50);
        foreach ($items as &$item) {
            $item['created_fa'] = Jalali::ago($item['created_at']);
        }
        unset($item);

        if ($request->wantsJson()) {
            return $this->json([
                'items'  => $items,
                'unread' => NotificationService::unreadCount($userId),
            ]);
        }

        return $this->view('admin/notifications/index', [
            'title'         => 'اعلان‌ها',
            'notifications' => $items,
            'status'        => $status,
            'unreadCount'   => NotificationService::unreadCount($userId),
        ]);
    }

    public function read(Request $request, string $id): Response
    {
        $userId = (int)$this->userId();
        NotificationService::markRead((int)$id, $userId);

        if ($request->wantsJson()) {
            return $this->json(['unread' => NotificationService::unreadCount($userId)]);
        }
        return $this->back();
    }

    public function readAll(Request $request): Response
    {
        $userId  = (int)$this->userId();
        $changed = NotificationService::markAllRead($userId);

        if ($request->wantsJson()) {
            return $this->json(['marked' => $changed, 'unread' => 0]);
        }
        return $this->back('success', 'همه اعلان‌ها خوانده‌شده علامت خوردند.');
    }

    public function archive(Request $request, string $id): Response
    {
        $userId = (int)$this->userId();
        NotificationService::archive((int)$id, $userId);

        if ($request->wantsJson()) {
            return $this->json(['unread' => NotificationService::unreadCount($userId)]);
        }
        return $this->back('success', 'اعلان بایگانی شد.');
    }
}
