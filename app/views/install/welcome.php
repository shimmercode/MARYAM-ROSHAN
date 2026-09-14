<?php
use App\Core\View;
View::extend('blank');
View::section('content');
?>
<div class="mr-auth__head">
    <div class="mr-auth__mark">م</div>
    <h1 class="text-xl mb-1">نصب سامانه مریم روشن</h1>
    <p class="text-sm text-muted m-0">پرتال دیجیتال سالن زیبایی — نسخه <?= fa(config('app.version', '0.1.0')) ?></p>
</div>
<div class="mr-auth__body">
    <div class="mr-steps"><span class="done"></span><span></span><span></span><span></span></div>
    <?= partial('flash') ?>

    <p class="text-sm">این نصاب در چهار گام سامانه را آماده بهره‌برداری می‌کند:</p>
    <ol class="text-sm text-muted" style="padding-inline-start:1.2rem">
        <li>بررسی پیش‌نیازهای سرور</li>
        <li>ساخت پایگاه داده و جداول</li>
        <li>درج داده‌های پایه (خدمات، نقش‌ها، تنظیمات)</li>
        <li>ساخت حساب مدیر ارشد</li>
    </ol>
    <div class="mr-alert mr-alert--info mt-4">
        <span aria-hidden="true">🔒</span>
        <span>پس از پایان نصب، این بخش به‌صورت خودکار غیرفعال می‌شود.</span>
    </div>
    <a class="mr-btn mr-btn--primary mr-btn--block mr-btn--lg mt-4" href="<?= url('/install/requirements') ?>">
        شروع نصب
    </a>
</div>
<?php View::endSection();
