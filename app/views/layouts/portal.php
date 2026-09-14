<?php
/**
 * Mobile-first layout shared by the staff (/staff) and customer (/customer)
 * portals. Sections: content, head, scripts.
 *
 * @var string $title
 * @var string $portal   'staff' | 'customer'
 * @var array  $nav      [['href'=>..., 'label'=>..., 'icon'=>...], ...]
 */
use App\Core\View;

$title = $title ?? 'پنل کاربری';
// Which portal we are in is derived from the request path so controllers do
// not have to repeat it in every view() call.
$portal = $portal ?? (str_starts_with($currentPath ?? '', '/staff') ? 'staff' : 'customer');
$base   = '/' . $portal;

$nav = $nav ?? ($portal === 'staff'
    ? [
        ['href' => '/staff',              'label' => 'میز کار',   'icon' => '🏠'],
        ['href' => '/staff/schedule',     'label' => 'برنامه',    'icon' => '🗓'],
        ['href' => '/staff/appointments', 'label' => 'نوبت‌ها',   'icon' => '📋'],
        ['href' => '/staff/commissions',  'label' => 'پورسانت',   'icon' => '💰'],
        ['href' => '/staff/performance',  'label' => 'عملکرد',    'icon' => '📈'],
    ]
    : [
        ['href' => '/customer',              'label' => 'خانه',      'icon' => '🏠'],
        ['href' => '/customer/appointments', 'label' => 'نوبت‌ها',   'icon' => '🗓'],
        ['href' => '/customer/invoices',     'label' => 'فاکتورها',  'icon' => '🧾'],
        ['href' => '/customer/loyalty',      'label' => 'باشگاه',    'icon' => '🎁'],
        ['href' => '/customer/profile',      'label' => 'پروفایل',   'icon' => '👤'],
    ]);

$path = $currentPath ?? '/';
?>
<!doctype html>
<html lang="fa" dir="rtl" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="base-url" content="<?= e(url('/')) ?>">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#1B1B23">
<title><?= e($title) ?> | <?= e(setting('salon_name', 'سالن زیبایی مریم روشن')) ?></title>
<link rel="icon" href="<?= asset('images/favicon.svg') ?>">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap/bootstrap.rtl.min.css') ?>">
<link rel="stylesheet" href="<?= asset('css/tokens.css') ?>">
<link rel="stylesheet" href="<?= asset('css/utilities.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="stylesheet" href="<?= asset('css/portal.css') ?>">
<?= View::yield('head') ?>
</head>
<body class="portal-body">

<header class="portal-header mr-no-print">
    <div class="portal-header__inner">
        <a class="portal-brand" href="<?= url($base) ?>">
            <span class="portal-brand__mark" aria-hidden="true">م‌ر</span>
            <span>
                <span class="portal-brand__name"><?= e(setting('salon_name', 'مریم روشن')) ?></span>
                <span class="portal-brand__sub"><?= $portal === 'staff' ? 'پنل پرسنل' : 'پنل مشتری' ?></span>
            </span>
        </a>
        <div class="flex items-center gap-2">
            <?php if (!empty($currentUser)): ?>
                <span class="mr-avatar" title="<?= e($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?>">
                    <?= e(mb_substr((string)$currentUser['first_name'], 0, 1)) ?>
                </span>
            <?php endif; ?>
            <form method="post" action="<?= url('/logout') ?>" class="inline">
                <?= csrf_field() ?>
                <button class="mr-iconbtn" type="submit" title="خروج از حساب" aria-label="خروج">⎋</button>
            </form>
        </div>
    </div>
</header>

<main class="portal-main">
    <h1 class="portal-title"><?= e($title) ?></h1>
    <?= partial('flash') ?>
    <?= View::yield('content') ?>
</main>

<nav class="portal-tabbar mr-no-print" aria-label="ناوبری اصلی">
    <?php foreach ($nav as $item):
        $active = $item['href'] === $base
            ? rtrim($path, '/') === rtrim($base, '/')
            : str_starts_with($path, $item['href']);
    ?>
        <a class="portal-tab <?= $active ? 'is-active' : '' ?>" href="<?= url($item['href']) ?>"
           <?= $active ? 'aria-current="page"' : '' ?>>
            <span class="portal-tab__icon" aria-hidden="true"><?= $item['icon'] ?></span>
            <span class="portal-tab__label"><?= e($item['label']) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<script type="module" src="<?= asset('js/core.js') ?>"></script>
<?= View::yield('scripts') ?>
</body>
</html>
