<?php
use App\Core\View;
View::extend('portal');
View::section('content');
/** @var array $customer @var string $birthFa */
?>
<div class="mr-card">
    <div class="mr-card__body">
        <form method="post" action="<?= url('/customer/profile') ?>">
            <?= csrf_field() ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="mr-field">
                    <label class="mr-label" for="first_name">نام <span class="req">*</span></label>
                    <input class="mr-input" id="first_name" name="first_name" required
                           value="<?= e(old('first_name', $customer['first_name'])) ?>">
                    <div class="mr-error" data-error-for="first_name"><?= e(error_for('first_name')) ?></div>
                </div>
                <div class="mr-field">
                    <label class="mr-label" for="last_name">نام خانوادگی <span class="req">*</span></label>
                    <input class="mr-input" id="last_name" name="last_name" required
                           value="<?= e(old('last_name', $customer['last_name'])) ?>">
                    <div class="mr-error" data-error-for="last_name"><?= e(error_for('last_name')) ?></div>
                </div>
            </div>

            <div class="mr-field">
                <label class="mr-label" for="mobile">موبایل</label>
                <input class="mr-input mr-num" id="mobile" dir="ltr" value="<?= e($customer['mobile']) ?>" readonly>
                <div class="mr-help">برای تغییر شماره موبایل با پذیرش سالن تماس بگیرید.</div>
            </div>

            <div class="mr-field">
                <label class="mr-label" for="email">ایمیل</label>
                <input class="mr-input" id="email" name="email" type="email" dir="ltr"
                       value="<?= e(old('email', $customer['email'] ?? '')) ?>">
                <div class="mr-error" data-error-for="email"><?= e(error_for('email')) ?></div>
            </div>

            <div class="mr-field">
                <label class="mr-label" for="birth_date">تاریخ تولد</label>
                <input class="mr-input mr-num" id="birth_date" name="birth_date" dir="ltr"
                       placeholder="۱۳۷۰/۰۵/۱۲" value="<?= e(old('birth_date', $birthFa)) ?>">
                <div class="mr-help">در ماه تولد خود هدیه باشگاه مشتریان دریافت می‌کنید.</div>
                <div class="mr-error" data-error-for="birth_date"><?= e(error_for('birth_date')) ?></div>
            </div>

            <div class="mr-field">
                <label class="mr-label" for="address">آدرس</label>
                <textarea class="mr-textarea" id="address" name="address" rows="2"><?= e(old('address', $customer['address'] ?? '')) ?></textarea>
            </div>

            <label class="flex items-center gap-2 mb-4">
                <input type="checkbox" name="marketing_opt_in" value="1"
                    <?= (int)$customer['marketing_opt_in'] === 1 ? 'checked' : '' ?>>
                <span class="text-sm">مایل به دریافت پیامک تخفیف‌ها و کمپین‌ها هستم</span>
            </label>

            <button class="mr-btn mr-btn--primary mr-btn--block" type="submit">ذخیره تغییرات</button>
        </form>
    </div>
</div>
<?php View::endSection();
