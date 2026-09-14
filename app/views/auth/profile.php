<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array|null $currentUser */
$u = $currentUser ?? [];
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb"><a href="<?= url('/admin') ?>">پنل مدیریت</a> / پروفایل</div>
        <h1>پروفایل من</h1>
        <p>مشاهده اطلاعات حساب و تغییر رمز عبور</p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="mr-card">
        <div class="mr-card__body text-center">
            <div class="mr-avatar mr-avatar--lg mx-auto mb-3"><?= e(mb_substr((string)($u['first_name'] ?? '؟'), 0, 1)) ?></div>
            <div class="font-bold"><?= e(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))) ?></div>
            <div class="text-sm text-muted mr-num" dir="ltr"><?= e($u['mobile'] ?? '') ?></div>
            <?php if (!empty($u['email'])): ?>
                <div class="text-xs text-muted" dir="ltr"><?= e($u['email']) ?></div>
            <?php endif; ?>
            <div class="mt-3"><span class="mr-badge mr-badge--gold"><?= e($u['role_name'] ?? 'کاربر') ?></span></div>
            <?php if (!empty($u['last_login_at'])): ?>
                <div class="text-xs text-muted mt-3">آخرین ورود: <?= jdate($u['last_login_at'], 'Y/m/d H:i') ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="mr-card col-span-2">
        <div class="mr-card__head"><h2 class="mr-card__title">تغییر رمز عبور</h2></div>
        <div class="mr-card__body">
            <form method="post" action="<?= url('/profile/password') ?>" class="max-w-md">
                <?= csrf_field() ?>
                <div class="mr-field">
                    <label class="mr-label" for="current_password">رمز عبور فعلی <span class="req">*</span></label>
                    <input class="mr-input" id="current_password" name="current_password" type="password" required autocomplete="current-password">
                    <div class="mr-error" data-error-for="current_password"><?= e(error_for('current_password')) ?></div>
                </div>
                <div class="mr-field">
                    <label class="mr-label" for="password">رمز عبور جدید <span class="req">*</span></label>
                    <input class="mr-input" id="password" name="password" type="password" minlength="8" required autocomplete="new-password">
                    <div class="mr-help">حداقل ۸ کاراکتر؛ ترکیبی از حروف و عدد توصیه می‌شود.</div>
                    <div class="mr-error" data-error-for="password"><?= e(error_for('password')) ?></div>
                </div>
                <div class="mr-field">
                    <label class="mr-label" for="password_confirmation">تکرار رمز جدید <span class="req">*</span></label>
                    <input class="mr-input" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                </div>
                <button class="mr-btn mr-btn--primary" type="submit">ذخیره رمز جدید</button>
            </form>
        </div>
    </div>
</div>
<?php View::endSection();
