<?php
use App\Core\View;
View::extend('blank');
View::section('content');
/** @var array $stats */
$stats = $stats ?? ['customers' => 0, 'staff' => 0, 'appointments' => 0];
?>
<div class="mr-auth__brand">
    <div class="mr-auth__brand-mark">
        <div class="mark">م</div>
        <div>
            <h2><?= e(setting('salon_name', 'سالن زیبایی مریم روشن')) ?></h2>
            <p class="lead">پنل مدیریت یکپارچه سالن</p>
        </div>
    </div>

    <div class="d-flex flex-column gap-3">
        <div class="lb-feat">
            <span class="ic" aria-hidden="true"><i class="bi bi-calendar-check"></i></span>
            <span class="tx"><b>مدیریت نوبت‌ها</b><span>تقویم زنده، یادآوری خودکار و کاهش عدم حضور</span></span>
        </div>
        <div class="lb-feat">
            <span class="ic" aria-hidden="true"><i class="bi bi-people"></i></span>
            <span class="tx"><b>پرونده ۳۶۰ درجه مشتری</b><span>سابقه خرید، وفاداری و کیف پول در یک نگاه</span></span>
        </div>
        <div class="lb-feat">
            <span class="ic" aria-hidden="true"><i class="bi bi-graph-up-arrow"></i></span>
            <span class="tx"><b>گزارش‌های لحظه‌ای</b><span>فروش، عملکرد پرسنل و شاخص‌های کسب‌وکار</span></span>
        </div>
    </div>

    <div class="lb-stats">
        <div class="stat"><b class="mr-num"><?= fa($stats['customers']) ?></b><span>مشتری</span></div>
        <div class="stat"><b class="mr-num"><?= fa($stats['staff']) ?></b><span>متخصص</span></div>
        <div class="stat"><b class="mr-num"><?= fa($stats['appointments']) ?></b><span>نوبت ثبت‌شده</span></div>
    </div>
</div>

<div class="login-form">
<div class="mr-auth__head lf-head">
    <div class="mr-auth__mark">م</div>
    <h2 class="text-xl mb-1"><?= e(setting('salon_name', 'سالن زیبایی مریم روشن')) ?></h2>
    <p>برای ادامه وارد حساب خود شوید</p>
</div>
<div class="mr-auth__body">
    <?= partial('flash') ?>

    <form method="post" action="<?= url('/login') ?>" id="loginForm" novalidate>
        <?= csrf_field() ?>
        <div class="mr-field fg">
            <label class="mr-label" for="identifier">موبایل یا ایمیل <span class="req">*</span></label>
            <input class="mr-input fc <?= error_for('identifier') ? 'is-invalid' : '' ?>" id="identifier" name="identifier"
                   dir="ltr" autocomplete="username" value="<?= e(old('identifier')) ?>" required autofocus>
            <div class="mr-error" data-error-for="identifier"><?= e(error_for('identifier')) ?></div>
        </div>

        <div class="mr-field fg">
            <label class="mr-label" for="password">رمز عبور <span class="req">*</span></label>
            <div class="relative">
                <input class="mr-input fc <?= error_for('password') ? 'is-invalid' : '' ?>" id="password" name="password"
                       type="password" autocomplete="current-password" required>
                <button type="button" class="mr-btn mr-btn--ghost mr-btn--sm absolute"
                        style="inset-inline-start:6px;top:5px" data-toggle-password aria-label="نمایش رمز">👁</button>
            </div>
            <div class="mr-error" data-error-for="password"><?= e(error_for('password')) ?></div>
        </div>

        <div class="flex items-center justify-between mb-4">
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" name="remember" value="1"> <span>مرا به خاطر بسپار</span>
            </label>
            <a class="text-sm" href="<?= url('/forgot-password') ?>">رمز عبور را فراموش کرده‌ام</a>
        </div>

        <button class="mr-btn mr-btn--primary mr-btn--block mr-btn--lg btn-login" type="submit" id="loginSubmit">ورود</button>
    </form>

    <div class="text-center text-sm text-muted mt-5">
        مشتری هستید و حساب ندارید؟
        <a href="<?= url('/booking') ?>">رزرو نوبت آنلاین</a>
    </div>
</div>
</div>
<?php View::endSection();

View::section('scripts'); ?>
<script type="module">
import { busy } from '<?= asset('js/core.js') ?>';

document.querySelector('[data-toggle-password]')?.addEventListener('click', () => {
    const input = document.getElementById('password');
    input.type = input.type === 'password' ? 'text' : 'password';
});

document.getElementById('loginForm')?.addEventListener('submit', (e) => {
    const form = e.target;
    if (!form.identifier.value.trim() || !form.password.value) {
        e.preventDefault();
        form.querySelector('[data-error-for="identifier"]').textContent =
            form.identifier.value.trim() ? '' : 'وارد کردن موبایل یا ایمیل الزامی است.';
        form.querySelector('[data-error-for="password"]').textContent =
            form.password.value ? '' : 'وارد کردن رمز عبور الزامی است.';
        return;
    }
    busy(document.getElementById('loginSubmit'), true, 'در حال ورود…');
});
</script>
<?php View::endSection();
