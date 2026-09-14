<?php
use App\Core\View;
View::extend('blank');
View::section('content');

/** @var int $status @var string $message @var \Throwable|null $exception */
$titles = [
    403 => 'دسترسی غیرمجاز',
    404 => 'صفحه یافت نشد',
    405 => 'متد غیرمجاز',
    419 => 'نشست منقضی شده',
    429 => 'درخواست بیش از حد',
    500 => 'خطای داخلی سرور',
];
$icons = [403 => '🔒', 404 => '🧭', 419 => '⏳', 429 => '🚦', 500 => '🛠'];
$title = $titles[$status] ?? 'خطا';
?>
<div class="mr-auth__head">
    <div class="mr-auth__mark"><?= $icons[$status] ?? '⚠️' ?></div>
    <div class="text-3xl font-bold mr-num"><?= fa($status) ?></div>
    <h1 class="text-xl mb-1"><?= e($title) ?></h1>
    <p class="text-sm text-muted m-0"><?= e($message) ?></p>
</div>
<div class="mr-auth__body">
    <?php if ($status === 419): ?>
        <div class="mr-alert mr-alert--warning">
            <span aria-hidden="true">🔄</span>
            <span>برای ادامه، صفحه را بازخوانی کرده و دوباره تلاش کنید.</span>
        </div>
    <?php endif; ?>

    <div class="flex gap-2">
        <a class="mr-btn mr-btn--primary flex-1" href="<?= url('/') ?>">صفحه اصلی</a>
        <button class="mr-btn mr-btn--ghost flex-1" type="button" onclick="history.back()">بازگشت</button>
    </div>

    <?php if (!empty($exception) && config('app.debug')): ?>
        <details class="mt-5">
            <summary class="text-sm cursor-pointer">جزئیات فنی (فقط در حالت توسعه)</summary>
            <pre dir="ltr" class="text-xs bg-canvas p-3 rounded mt-2 overflow-auto" style="max-height:320px"><?=
                e($exception->getFile() . ':' . $exception->getLine() . "\n\n" . $exception->getTraceAsString())
            ?></pre>
        </details>
    <?php endif; ?>
</div>
<?php View::endSection();
