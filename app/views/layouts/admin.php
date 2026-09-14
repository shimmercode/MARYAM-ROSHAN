<?php
/**
 * Admin layout. Sections: content, head, scripts, page_actions.
 * @var string $title
 */
use App\Core\View;

$title = $title ?? 'پنل مدیریت';
?>
<!doctype html>
<html lang="fa" dir="rtl" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="base-url" content="<?= e(url('/')) ?>">
<meta name="robots" content="noindex,nofollow">
<title><?= e($title) ?> | <?= e(setting('salon_name', 'سالن زیبایی مریم روشن')) ?></title>
<link rel="icon" href="<?= asset('images/favicon.svg') ?>">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap/bootstrap.rtl.min.css') ?>">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= asset('css/tokens.css') ?>">
<link rel="stylesheet" href="<?= asset('css/utilities.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="stylesheet" href="<?= asset('css/mockup.css') ?>">
<?= View::yield('head') ?>
</head>
<body>
<div class="app mr-shell">
    <?= partial('admin/sidebar') ?>

    <div class="mw mr-main">
        <?= partial('admin/topbar', ['title' => $title]) ?>

        <main class="mc mr-content">
            <?= partial('flash') ?>
            <?= View::yield('content') ?>
        </main>

        <footer class="footer mr-no-print text-center text-xs text-muted py-4">
            <?= e(setting('salon_name', 'سالن زیبایی مریم روشن')) ?> — نسخه <?= fa(config('app.version', '0.1.0')) ?>
        </footer>
    </div>
</div>

<script src="<?= asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>" defer></script>
<script type="module" src="<?= asset('js/core.js') ?>"></script>
<?= View::yield('scripts') ?>
</body>
</html>
