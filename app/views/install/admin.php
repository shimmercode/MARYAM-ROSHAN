<?php
use App\Core\View;
View::extend('blank');
View::section('content');
?>
<div class="mr-auth__head">
    <div class="mr-auth__mark">۴</div>
    <h1 class="text-xl mb-1">ساخت حساب مدیر ارشد</h1>
    <p class="text-sm text-muted m-0">با این حساب به پنل مدیریت وارد می‌شوید.</p>
</div>
<div class="mr-auth__body">
    <div class="mr-steps"><span class="done"></span><span class="done"></span><span class="done"></span><span class="done"></span></div>
    <?= partial('flash') ?>

    <form method="post" action="<?= url('/install/admin') ?>" id="adminForm">
        <?= csrf_field() ?>
        <div class="grid grid-cols-2 gap-3">
            <div class="mr-field">
                <label class="mr-label" for="first_name">نام <span class="req">*</span></label>
                <input class="mr-input" id="first_name" name="first_name" value="<?= e(old('first_name')) ?>" required>
                <div class="mr-error" data-error-for="first_name"><?= e(error_for('first_name')) ?></div>
            </div>
            <div class="mr-field">
                <label class="mr-label" for="last_name">نام خانوادگی <span class="req">*</span></label>
                <input class="mr-input" id="last_name" name="last_name" value="<?= e(old('last_name')) ?>" required>
                <div class="mr-error" data-error-for="last_name"><?= e(error_for('last_name')) ?></div>
            </div>
        </div>
        <div class="mr-field">
            <label class="mr-label" for="mobile">موبایل (نام کاربری) <span class="req">*</span></label>
            <input class="mr-input mr-num" id="mobile" name="mobile" dir="ltr" inputmode="numeric"
                   placeholder="09121234567" value="<?= e(old('mobile')) ?>" required>
            <div class="mr-error" data-error-for="mobile"><?= e(error_for('mobile')) ?></div>
        </div>
        <div class="mr-field">
            <label class="mr-label" for="email">ایمیل</label>
            <input class="mr-input" id="email" name="email" type="email" dir="ltr" value="<?= e(old('email')) ?>">
            <div class="mr-error" data-error-for="email"><?= e(error_for('email')) ?></div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div class="mr-field">
                <label class="mr-label" for="password">رمز عبور <span class="req">*</span></label>
                <input class="mr-input" id="password" name="password" type="password" minlength="8" required autocomplete="new-password">
                <div class="mr-help">حداقل ۸ کاراکتر</div>
                <div class="mr-error" data-error-for="password"><?= e(error_for('password')) ?></div>
            </div>
            <div class="mr-field">
                <label class="mr-label" for="password_confirmation">تکرار رمز عبور <span class="req">*</span></label>
                <input class="mr-input" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm mb-4 cursor-pointer">
            <input type="checkbox" name="demo_data" value="1" checked>
            <span>درج داده‌های نمونه فارسی (کارکنان، مشتریان و برنامه کاری)</span>
        </label>

        <button class="mr-btn mr-btn--primary mr-btn--block mr-btn--lg" type="submit" id="adminSubmit">
            پایان نصب
        </button>
    </form>
</div>
<?php View::endSection();

View::section('scripts'); ?>
<script type="module">
import { busy } from '<?= asset('js/core.js') ?>';
document.getElementById('adminForm')?.addEventListener('submit', () => {
    busy(document.getElementById('adminSubmit'), true, 'در حال تکمیل نصب…');
});
</script>
<?php View::endSection();
