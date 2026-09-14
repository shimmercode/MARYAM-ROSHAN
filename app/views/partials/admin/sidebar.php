<?php
/**
 * Admin navigation — grouped, colour-dotted, accordion sidebar matching the
 * client-approved mockup 1:1 (group order, labels, icons, colours).
 *
 * Every item is one of:
 *   - a REAL route in this app (verified against routes/web.php)
 *   - an external link (وب‌سایت عمومی → the salon's real separate website)
 *   - a portal link into the real customer/staff portal of this same app
 *   - a "/admin/soon/{slug}" honest placeholder for menu items that name a
 *     real product concept with no backend behind it yet (see
 *     Admin\ComingSoonController) — never a page with fabricated data.
 *
 * Items are additionally filtered by permission so no item a role cannot
 * use is ever shown, same as before this rebuild.
 */
$groups = [
    [
        'label' => 'اصلی',
        'color' => '#384381',
        'items' => [
            ['/admin',                        'داشبورد',       'bi-grid-1x2-fill', null, null],
            ['/admin/appointments',           'نوبت‌ها',       'bi-calendar-check', 'appointments.view', 'appointments_today'],
            ['/admin/appointments/calendar',  'تقویم',         'bi-calendar3', 'appointments.view', null],
            ['/admin/soon/booking-engine',    'موتور رزرو',    'bi-lightning-charge', null, 'soon'],
            ['/admin/soon/capacity',          'منابع و ظرفیت', 'bi-diagram-3', null, 'soon'],
            ['/admin/services',               'خدمات',         'bi-stars', 'services.view', null],
            ['/admin/staff',                  'پرسنل',         'bi-person-badge', 'staff.view', null],
            ['/admin/staff',                  'شیفت‌بندی',     'bi-clock-history', 'staff.view', null],
            ['/admin/settings/branches',      'شعب',           'bi-shop', 'settings.manage', null],
            ['/admin/customers',              'مشتریان',       'bi-people', 'customers.view', null],
        ],
    ],
    [
        'label' => 'فروش و مالی',
        'color' => '#4690BF',
        'items' => [
            ['/admin/pos',               'POS و Checkout', 'bi-cart-check', 'finance.create', null],
            ['/admin/reports/sales',     'فروش',           'bi-graph-up-arrow', 'reports.view', null],
            ['/admin/invoices',          'فاکتورها',       'bi-receipt', 'finance.view', 'unpaid_invoices'],
            ['/admin/soon/payments',     'پرداخت‌ها',      'bi-credit-card', null, 'soon'],
            ['/admin/soon/wallet',       'کیف پول',        'bi-wallet2', null, 'soon'],
            ['/admin/soon/commission',   'پورسانت',        'bi-percent', null, 'soon'],
            ['/admin/soon/cost',         'بهای تمام‌شده',  'bi-tags', null, 'soon'],
            ['/admin/soon/profitability','سودآوری',        'bi-piggy-bank', null, 'soon'],
        ],
    ],
    [
        'label' => 'CRM و وفاداری',
        'color' => '#2e9e6b',
        'items' => [
            ['/admin/customers',           'Customer 360',      'bi-person-vcard', 'customers.view', null],
            ['/admin/marketing/discounts', 'باشگاه مشتریان',    'bi-award', 'marketing.view', null],
            ['/admin/soon/churn',          'مدیریت ریزش',       'bi-graph-down-arrow', null, 'soon'],
            ['/admin/soon/surveys',        'نظرسنجی‌ها',        'bi-clipboard-check', null, 'soon'],
            ['/admin/soon/reputation',     'Reputation',         'bi-star-half', null, 'soon'],
            ['/admin/soon/tickets',        'تیکت و شکایات',     'bi-life-preserver', null, 'soon'],
        ],
    ],
    [
        'label' => 'بازاریابی',
        'color' => '#e0a43a',
        'items' => [
            ['/admin/marketing/campaigns',   'کمپین‌ها',                 'bi-megaphone', 'marketing.view', null],
            ['/admin/marketing/automations', 'Marketing Automation',     'bi-robot', 'marketing.view', null],
            ['/admin/soon/omnichannel',      'ارتباطات (Omnichannel)',   'bi-chat-dots', null, 'soon'],
            ['/admin/soon/waitlist',         'Waitlist',                 'bi-hourglass-split', null, 'soon'],
            ['/admin/marketing/discounts',   'تخفیف‌ها',                 'bi-percent', 'marketing.view', null],
        ],
    ],
    [
        'label' => 'انبار و تأمین',
        'color' => '#b87d7b',
        'items' => [
            ['/admin/inventory/products',     'محصولات',          'bi-box-seam', 'inventory.view', null],
            ['/admin/inventory',               'انبار',            'bi-boxes', 'inventory.view', null],
            ['/admin/inventory/transactions',  'گردش کالا',        'bi-arrow-left-right', 'inventory.view', null],
            ['/admin/soon/suppliers',          'تأمین‌کنندگان',    'bi-truck', null, 'soon'],
            ['/admin/soon/procurement',        'درخواست خرید',     'bi-cart-plus', null, 'soon'],
        ],
    ],
    [
        'label' => 'Enterprise',
        'color' => '#d9534f',
        'items' => [
            ['/admin/reports',            'BI & Analytics',  'bi-bar-chart-line', 'reports.view', null],
            ['/admin/soon/workflow',      'Workflow',        'bi-diagram-2', null, 'soon'],
            ['/admin/settings/audit',     'Audit Log',       'bi-journal-text', 'settings.manage', null],
            ['/admin/soon/security',      'Security',        'bi-shield-lock', null, 'soon'],
            ['/admin/settings/users',     'کاربران',         'bi-person-gear', 'settings.manage', null],
            ['/admin/soon/roles',         'نقش‌ها',          'bi-key', null, 'soon'],
            ['/admin/notifications',      'اعلان‌ها',        'bi-bell', null, null],
            ['/admin/settings',           'تنظیمات',         'bi-gear', 'settings.manage', null],
            ['/admin/soon/templates',     'Templateها',      'bi-file-earmark-richtext', null, 'soon'],
            ['/admin/settings/backups',   'پشتیبان‌گیری',    'bi-cloud-arrow-down', 'settings.manage', null],
            ['/admin/settings/branches',  'چندشعبه‌ای',      'bi-diagram-3-fill', 'settings.manage', null],
            ['/admin/soon/franchise',     'Franchise',       'bi-building', null, 'soon'],
            ['https://maryamroshan.com',  'وب‌سایت عمومی',   'bi-globe2', null, 'external'],
            ['/customer',                 'پنل مشتری',       'bi-person-square', null, 'portal'],
            ['/staff',                    'پنل پرسنل',       'bi-person-workspace', null, 'portal'],
        ],
    ],
];

$currentPath    = $currentPath ?? '';
$unreadCount    = $unreadCount ?? 0;
$sidebarBadges  = $sidebarBadges ?? ['appointments_today' => 0, 'unpaid_invoices' => 0];

$badgeValue = static function (?string $key) use ($unreadCount, $sidebarBadges): ?int {
    return match ($key) {
        'appointments_today' => (int)$sidebarBadges['appointments_today'],
        'unpaid_invoices'    => (int)$sidebarBadges['unpaid_invoices'],
        default              => null,
    };
};
?>
<aside class="sidebar mr-sidebar" id="mrSidebar">
    <div class="sb-h">
        <div class="logo">
            <div class="logo-i" aria-hidden="true">م</div>
            <div class="logo-t">
                <h1><?= e(setting('salon_name', 'مریم روشن')) ?></h1>
                <span>پنل مدیریت</span>
            </div>
        </div>
    </div>

    <nav class="sb-nav" aria-label="منوی اصلی" id="sidebarNav">
        <?php foreach ($groups as $gi => $group):
            $visible = array_filter($group['items'], static fn ($i) => $i[3] === null || can($i[3]));
            if ($visible === []) {
                continue;
            }
            $groupActive = false;
            foreach ($visible as $it) {
                if ($it[0] !== '/admin' && $it[0] !== '/customer' && $it[0] !== '/staff'
                    && str_starts_with('https', $it[0]) === false
                    && str_starts_with($currentPath, $it[0])) {
                    $groupActive = true;
                }
            }
            if ($currentPath === '/admin' && $group['label'] === 'اصلی') {
                $groupActive = true;
            }
            $groupId = 'nsg-' . $gi;
            ?>
            <div class="ns <?= $groupActive ? '' : 'is-collapsed' ?>" data-nav-group>
                <button type="button" class="ns-t" data-nav-toggle aria-expanded="<?= $groupActive ? 'true' : 'false' ?>" aria-controls="<?= $groupId ?>">
                    <span class="ns-d" style="background:<?= e($group['color']) ?>" aria-hidden="true"></span>
                    <span><?= e($group['label']) ?></span>
                    <span class="chev" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
                </button>
                <div class="ns-list" id="<?= $groupId ?>">
                    <?php foreach ($visible as [$href, $label, $icon, $perm, $badgeKind]):
                        $isExternal = str_starts_with($href, 'http');
                        $isPortal   = $badgeKind === 'portal';
                        $active     = !$isExternal && ($href === '/admin' ? $currentPath === '/admin' : str_starts_with($currentPath, $href));
                        $target     = $isExternal || $isPortal ? ' target="_blank" rel="noopener"' : '';
                        $count      = $badgeKind === 'soon' ? null : $badgeValue($badgeKind);
                        if ($href === '/admin/notifications') {
                            $count = $unreadCount > 0 ? min($unreadCount, 99) : null;
                        }
                        ?>
                        <a class="ni <?= $active ? 'active' : '' ?>" href="<?= $isExternal ? e($href) : url($href) ?>"<?= $target ?>
                           <?= $active ? 'aria-current="page"' : '' ?>>
                            <i class="bi <?= e($icon) ?>" aria-hidden="true"></i>
                            <span><?= e($label) ?></span>
                            <?php if ($badgeKind === 'soon'): ?>
                                <span class="nb soon">به‌زودی</span>
                            <?php elseif ($count !== null && $count > 0): ?>
                                <span class="nb <?= $href === '/admin/invoices' ? 'dg' : '' ?>"><?= fa($count) ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </nav>
</aside>
<script>
(function () {
    var nav = document.getElementById('sidebarNav');
    if (!nav || nav.dataset.bound) return;
    nav.dataset.bound = '1';
    nav.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-nav-toggle]');
        if (!btn) return;
        var group = btn.closest('[data-nav-group]');
        var collapsed = group.classList.toggle('is-collapsed');
        btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    });
})();
</script>
