<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Core\Database;
use App\Services\AuthService;
use App\Services\NotificationService;

abstract class BaseController
{
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        $user = AuthService::currentUser();

        View::share('appName', (string)Config::get('app.name'));
        View::share('currentUser', $user);
        View::share('currentPath', Request::capture()->path());
        View::share('flashes', Session::pullFlash());
        View::share('errors', Session::pullErrors());
        View::share('old', Session::oldInput());
        View::share('unreadCount', $user ? NotificationService::unreadCount((int)$user['id']) : 0);
        View::share('sidebarBadges', $user ? $this->sidebarBadges() : ['appointments_today' => 0, 'unpaid_invoices' => 0]);

        return Response::html(View::render($template, $data), $status);
    }

    /**
     * Small real-time counters shown as sidebar nav badges. Never fabricated —
     * each is a direct COUNT() query, fails soft to 0 so a DB hiccup never
     * breaks the whole admin shell.
     */
    private function sidebarBadges(): array
    {
        try {
            $today = date('Y-m-d');
            return [
                'appointments_today' => (int)Database::instance()->scalar(
                    "SELECT COUNT(*) FROM appointments WHERE appointment_date = :d AND status NOT IN ('CANCELLED')",
                    ['d' => $today]
                ),
                'unpaid_invoices' => (int)Database::instance()->scalar(
                    "SELECT COUNT(*) FROM invoices WHERE payment_status IN ('UNPAID','PARTIAL') AND status <> 'CANCELLED'"
                ),
            ];
        } catch (\Throwable) {
            return ['appointments_today' => 0, 'unpaid_invoices' => 0];
        }
    }

    protected function json(mixed $data = [], array $meta = []): Response
    {
        return Response::success($data, $meta);
    }

    protected function fail(string $code, string $message, int $status = 400, array $details = []): Response
    {
        return Response::error($code, $message, $status, $details);
    }

    protected function redirect(string $path, ?string $flashType = null, ?string $flashMessage = null): Response
    {
        if ($flashType !== null && $flashMessage !== null) {
            Session::flash($flashType, $flashMessage);
        }
        return Response::redirect(url($path));
    }

    protected function back(?string $flashType = null, ?string $flashMessage = null): Response
    {
        if ($flashType !== null && $flashMessage !== null) {
            Session::flash($flashType, $flashMessage);
        }
        $referer = Request::capture()->header('Referer');
        return Response::redirect($referer ?: url('/'));
    }

    protected function authorize(string $permission): void
    {
        if (!AuthService::can($permission)) {
            throw new HttpException(403, 'شما دسترسی لازم برای این عملیات را ندارید.');
        }
    }

    protected function user(): ?array
    {
        return AuthService::currentUser();
    }

    protected function userId(): ?int
    {
        return AuthService::id();
    }

    /** Branch scoping for non-admin roles. */
    protected function scopedBranchId(): ?int
    {
        $user = $this->user();
        if ($user === null) {
            return null;
        }
        if (AuthService::hasAnyRole(['SUPER_ADMIN', 'ADMIN'])) {
            return null; // all branches
        }
        return $user['branch_id'] !== null ? (int)$user['branch_id'] : null;
    }

    protected function notFound(string $message = 'مورد درخواستی یافت نشد.'): never
    {
        throw new HttpException(404, $message);
    }
}
