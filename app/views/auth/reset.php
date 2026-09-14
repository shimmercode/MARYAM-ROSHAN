<?php
use App\Core\View;
View::extend('blank');
View::section('content');
/** @var string $token */
?>
<div class="mr-auth__head">
    <div class="mr-auth__mark">🔐</div>
    <h1 class="text-xl mb-1">تعیین رمز عبور جدید</h1>
    <p class="text-sm text-muted m-0">رمز جدید باید حداقل ۸ کاراکتر باشد.</p>
</div>
<div class="mr-auth__body">
    <?= partial('flash') ?>
    <form method="post" action="<?= url('/reset-password') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="mr-field">
            <label class="mr-label" for="password">رمز عبور جدید <span class="req">*</span></label>
            <input class="mr-input" id="password" name="password" type="password" minlength="8" required autofocus autocomplete="new-password">
            <div class="mr-error" data-error-for="password"><?= e(error_for('password')) ?></div>
        </div>
        <div class="mr-field">
            <label class="mr-label" for="password_confirmation">تکرار رمز عبور <span class="req">*</span></label>
            <input class="mr-input" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
        </div>
        <button class="mr-btn mr-btn--primary mr-btn--block mr-btn--lg" type="submit">ثبت رمز جدید</button>
    </form>
    <div class="text-center mt-4"><a class="text-sm" href="<?= url('/login') ?>">بازگشت به ورود</a></div>
</div>
<?php View::endSection();
