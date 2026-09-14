<?php
use App\Core\View;
View::extend('blank');
View::section('content');
/** @var string|null $mobile */
?>
<div class="mr-auth__head">
    <div class="mr-auth__mark">✓</div>
    <h1 class="text-xl mb-1">نصب با موفقیت انجام شد</h1>
    <p class="text-sm text-muted m-0">سامانه آماده بهره‌برداری است.</p>
</div>
<div class="mr-auth__body">
    <div class="mr-alert mr-alert--success">
        <span aria-hidden="true">🔒</span>
        <span>نصاب به‌صورت خودکار غیرفعال شد (فایل <code>storage/installed.lock</code>).</span>
    </div>

    <?php if (!empty($mobile)): ?>
        <div class="border rounded-lg p-4 mb-4">
            <div class="text-sm text-muted mb-1">نام کاربری شما</div>
            <div class="mr-num font-bold" dir="ltr"><?= e($mobile) ?></div>
        </div>
    <?php endif; ?>

    <div class="text-sm text-muted mb-4">
        گام‌های پیشنهادی بعدی:
        <ul style="padding-inline-start:1.2rem">
            <li>تکمیل اطلاعات شعبه و ساعات کاری در «تنظیمات»</li>
            <li>افزودن کارکنان و تعیین برنامه هفتگی آن‌ها</li>
            <li>پیکربندی سرویس پیامک (بدون کلید هم سامانه کار می‌کند و پیام‌ها در لاگ ثبت می‌شوند)</li>
            <li>تنظیم کران‌جاب روزانه: <code dir="ltr">php cron/daily.php</code></li>
        </ul>
    </div>

    <a class="mr-btn mr-btn--primary mr-btn--block mr-btn--lg" href="<?= url('/login') ?>">ورود به پنل مدیریت</a>
    <a class="mr-btn mr-btn--ghost mr-btn--block mt-2" href="<?= url('/') ?>">مشاهده وب‌سایت</a>
</div>
<?php View::endSection();
