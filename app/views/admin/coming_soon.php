<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var string $title @var string $slug */
?>
<div class="ph">
    <div>
        <h1><?= e($title) ?></h1>
        <p>این بخش از پنل مدیریت هنوز به دیتابیس واقعی وصل نشده است.</p>
    </div>
</div>

<div class="card">
    <div class="mr-state">
        <div class="mr-state__icon" aria-hidden="true"><i class="bi bi-hourglass-split"></i></div>
        <div class="mr-state__title">این بخش به‌زودی فعال خواهد شد</div>
        <div class="mr-state__text">
            «<?= e($title) ?>» در ساختار منوی تأییدشده وجود دارد، اما پیاده‌سازی سرور و دیتابیس آن هنوز
            انجام نشده — به همین دلیل به‌جای نمایش داده ساختگی، همین پیام صادقانه نشان داده می‌شود.
            به محض توسعهٔ این قابلیت، این صفحه با داده واقعی جایگزین می‌شود.
        </div>
        <div class="mt-4">
            <a class="mr-btn mr-btn--ghost" href="<?= url('/admin') ?>">بازگشت به داشبورد</a>
        </div>
    </div>
</div>
<?php View::endSection();
