<?php
use App\Core\View;
View::extend('blank');
View::section('content');
?>
<div class="mr-auth__head">
    <div class="mr-auth__mark">🔑</div>
    <h1 class="text-xl mb-1">بازیابی رمز عبور</h1>
    <p class="text-sm text-muted m-0">لینک بازیابی برای شما ارسال می‌شود.</p>
</div>
<div class="mr-auth__body">
    <?= partial('flash') ?>
    <form method="post" action="<?= url('/forgot-password') ?>">
        <?= csrf_field() ?>
        <div class="mr-field">
            <label class="mr-label" for="identifier">موبایل یا ایمیل حساب <span class="req">*</span></label>
            <input class="mr-input" id="identifier" name="identifier" dir="ltr" required autofocus
                   value="<?= e(old('identifier')) ?>">
            <div class="mr-error" data-error-for="identifier"><?= e(error_for('identifier')) ?></div>
        </div>
        <button class="mr-btn mr-btn--primary mr-btn--block mr-btn--lg" type="submit">ارسال لینک بازیابی</button>
    </form>
    <div class="text-center mt-4"><a class="text-sm" href="<?= url('/login') ?>">بازگشت به صفحه ورود</a></div>
</div>
<?php View::endSection();
