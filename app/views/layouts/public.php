<?php
/**
 * Public website layout with SEO meta and JSON-LD.
 * @var string $title @var string|null $description @var string|null $jsonLd
 */
use App\Core\View;

$title       = $title ?? (string)setting('meta_title', 'سالن زیبایی مریم روشن');
$description = $description ?? (string)setting('meta_description', '');
$canonical   = rtrim((string)config('app.url', ''), '/') . ($currentPath ?? '/');
$salon       = (string)setting('salon_name', 'سالن زیبایی مریم روشن');

$nav = [
    ['/', 'خانه'], ['/services', 'خدمات'], ['/team', 'تیم ما'],
    ['/gallery', 'گالری'], ['/blog', 'مجله'], ['/contact', 'تماس با ما'],
];
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($salon) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:locale" content="fa_IR">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#384381">
<link rel="icon" href="<?= asset('images/favicon.svg') ?>">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap/bootstrap.rtl.min.css') ?>">
<link rel="stylesheet" href="<?= asset('css/tokens.css') ?>">
<link rel="stylesheet" href="<?= asset('css/utilities.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="stylesheet" href="<?= asset('css/site.css') ?>">

<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'BeautySalon',
    'name'     => $salon,
    'description' => $description,
    'url'      => rtrim((string)config('app.url', ''), '/'),
    'telephone' => (string)setting('salon_phone', ''),
    'address'  => [
        '@type' => 'PostalAddress',
        'streetAddress' => (string)setting('salon_address', ''),
        'addressLocality' => 'تهران',
        'addressCountry' => 'IR',
    ],
    'openingHours' => 'Sa-Th 09:00-21:00',
    'priceRange'   => '$$',
    'image'        => rtrim((string)config('app.url', ''), '/') . '/assets/images/og-cover.jpg',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
</script>
<?= View::yield('jsonld') ?>
<?= View::yield('head') ?>
</head>
<body class="site">

<header class="site-header" id="siteHeader">
    <div class="site-container flex items-center gap-4">
        <a class="site-logo" href="<?= url('/') ?>">
            <span class="site-logo__mark">م</span>
            <span>
                <span class="site-logo__name"><?= e($salon) ?></span>
                <span class="site-logo__tag"><?= e(setting('salon_tagline', '')) ?></span>
            </span>
        </a>

        <nav class="site-nav" id="siteNav" aria-label="منوی اصلی">
            <?php foreach ($nav as [$href, $label]):
                $isActive = $href === '/' ? (($currentPath ?? '') === '/') : str_starts_with((string)($currentPath ?? ''), $href); ?>
                <a href="<?= url($href) ?>" class="<?= $isActive ? 'is-active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="flex items-center gap-2 ms-auto">
            <a class="mr-btn mr-btn--primary" href="<?= url('/booking') ?>">رزرو نوبت</a>
            <a class="mr-btn mr-btn--ghost sm:hidden" href="<?= url('/login') ?>">ورود</a>
            <button class="mr-iconbtn md:hidden" type="button" id="navToggle" aria-label="منو" aria-expanded="false">☰</button>
        </div>
    </div>
</header>

<main>
    <?= partial('flash') ?>
    <?= View::yield('content') ?>
</main>

<footer class="site-footer">
    <div class="site-container grid grid-cols-1 md:grid-cols-4 gap-6">
        <div>
            <div class="site-logo mb-3">
                <span class="site-logo__mark">م</span>
                <span class="site-logo__name"><?= e($salon) ?></span>
            </div>
            <p class="text-sm" style="opacity:.8"><?= e(setting('salon_tagline', '')) ?></p>
        </div>
        <div>
            <h3 class="text-sm font-bold mb-3">دسترسی سریع</h3>
            <?php foreach ($nav as [$href, $label]): ?>
                <a class="block text-sm mb-2" href="<?= url($href) ?>" style="opacity:.85"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <div>
            <h3 class="text-sm font-bold mb-3">خدمات پرطرفدار</h3>
            <?php foreach (['رنگ مو و مش' => 'hair-color', 'هیدرافیشیال' => 'hydrafacial', 'میکاپ عروس' => 'bridal-makeup', 'کاشت ناخن' => 'nail-extension'] as $label => $slug): ?>
                <a class="block text-sm mb-2" href="<?= url('/services/' . $slug) ?>" style="opacity:.85"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <div>
            <h3 class="text-sm font-bold mb-3">تماس با ما</h3>
            <p class="text-sm mb-2" style="opacity:.85"><?= e(setting('salon_address', '')) ?></p>
            <p class="text-sm mb-2 mr-num" style="opacity:.85"><?= fa(setting('salon_phone', '')) ?></p>
            <p class="text-sm" style="opacity:.85">
                اینستاگرام:
                <a dir="ltr" href="https://instagram.com/<?= e(setting('salon_instagram', '')) ?>" rel="noopener nofollow" target="_blank">
                    @<?= e(setting('salon_instagram', '')) ?>
                </a>
            </p>
        </div>
    </div>
    <div class="site-container border-t mt-6 pt-4 text-center text-xs" style="opacity:.6">
        © <?= fa(jdate(date('Y-m-d'), 'Y')) ?> <?= e($salon) ?> — تمامی حقوق محفوظ است.
    </div>
</footer>

<script type="module" src="<?= asset('js/core.js') ?>"></script>
<script>
document.getElementById('navToggle')?.addEventListener('click', function () {
    const nav = document.getElementById('siteNav');
    const open = nav.classList.toggle('is-open');
    this.setAttribute('aria-expanded', String(open));
});
window.addEventListener('scroll', () => {
    document.getElementById('siteHeader')?.classList.toggle('is-scrolled', window.scrollY > 12);
}, { passive: true });
</script>
<?= View::yield('scripts') ?>
</body>
</html>
