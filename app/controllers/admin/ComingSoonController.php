<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Request;
use App\Core\Response;

/**
 * Honest placeholder for sidebar items that name a real product concept
 * (from the client-approved menu structure) with no backend feature behind
 * it yet — e.g. Waitlist, Reputation, Roles. Never fabricates data; it just
 * says "coming soon" so the menu item is clickable instead of 404ing.
 */
final class ComingSoonController extends BaseController
{
    /** Slug => human title, kept in sync with sidebar.php's nav map. */
    private const TITLES = [
        'booking-engine'    => 'موتور رزرو',
        'capacity'          => 'منابع و ظرفیت',
        'payments'          => 'پرداخت‌ها',
        'wallet'            => 'کیف پول',
        'commission'        => 'پورسانت',
        'cost'              => 'بهای تمام‌شده',
        'profitability'     => 'سودآوری',
        'churn'             => 'مدیریت ریزش',
        'surveys'           => 'نظرسنجی‌ها',
        'reputation'        => 'Reputation',
        'tickets'           => 'تیکت و شکایات',
        'omnichannel'       => 'ارتباطات (Omnichannel)',
        'waitlist'          => 'Waitlist',
        'suppliers'         => 'تأمین‌کنندگان',
        'procurement'       => 'درخواست خرید',
        'workflow'          => 'Workflow',
        'security'          => 'Security Center',
        'roles'             => 'نقش‌ها',
        'templates'         => 'Templateها',
        'franchise'         => 'Franchise',
    ];

    public function show(Request $request, string $slug): Response
    {
        $title = self::TITLES[$slug] ?? 'این بخش';

        return $this->view('admin/coming_soon', [
            'title' => $title,
            'slug'  => $slug,
        ]);
    }
}
