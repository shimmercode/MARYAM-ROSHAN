<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array $settings @var array $fields @var string $smsDriver @var bool $smsReady */
$groups = [
    'general'      => ['title' => 'اطلاعات سالن', 'icon' => '🏛'],
    'booking'      => ['title' => 'رزرو نوبت', 'icon' => '🗓'],
    'finance'      => ['title' => 'مالی و فاکتور', 'icon' => '💵'],
    'loyalty'      => ['title' => 'باشگاه مشتریان', 'icon' => '🎁'],
    'notification' => ['title' => 'اطلاع‌رسانی', 'icon' => '🔔'],
    'seo'          => ['title' => 'سئو و متادیتا', 'icon' => '🔍'],
];
$labels = [
    'salon_name' => 'نام سالن', 'salon_tagline' => 'شعار', 'salon_phone' => 'تلفن',
    'salon_email' => 'ایمیل', 'salon_address' => 'آدرس', 'instagram' => 'اینستاگرام',
    'telegram' => 'تلگرام', 'whatsapp' => 'واتس‌اپ',
    'booking_enabled' => 'رزرو آنلاین فعال باشد', 'booking_max_days' => 'حداکثر روز آینده قابل رزرو',
    'booking_min_hours' => 'حداقل فاصله رزرو (ساعت)', 'booking_slot_minutes' => 'طول هر بازه (دقیقه)',
    'booking_auto_confirm' => 'تایید خودکار نوبت‌ها', 'booking_buffer_minutes' => 'فاصله بین نوبت‌ها (دقیقه)',
    'cancel_hours' => 'مهلت لغو نوبت (ساعت)',
    'currency' => 'واحد پول', 'tax_percent' => 'درصد مالیات', 'invoice_prefix' => 'پیش‌شماره فاکتور',
    'invoice_footer' => 'یادداشت پای فاکتور',
    'loyalty_enabled' => 'باشگاه مشتریان فعال باشد', 'loyalty_points_per_unit' => 'امتیاز به ازای هر واحد خرید',
    'loyalty_point_value' => 'ارزش ریالی هر امتیاز',
    'sms_enabled' => 'ارسال پیامک فعال باشد', 'sms_reminder_hours' => 'یادآوری نوبت (ساعت قبل)',
    'notify_on_booking' => 'اطلاع به مدیر هنگام رزرو',
    'meta_title' => 'عنوان متا', 'meta_description' => 'توضیح متا', 'google_analytics' => 'کد گوگل آنالیتیکس',
];
$byGroup = [];
foreach ($fields as $key => [$type, $group]) {
    $byGroup[$group][$key] = $type;
}
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb">پنل مدیریت / تنظیمات</div>
        <h1>تنظیمات سامانه</h1>
        <p>پیکربندی عمومی، رزرو، مالی و اطلاع‌رسانی</p>
    </div>
    <div class="flex gap-2 flex-wrap">
        <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/settings/branches') ?>">شعبه‌ها</a>
        <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/settings/users') ?>">کاربران</a>
        <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/settings/backups') ?>">پشتیبان</a>
        <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/settings/audit') ?>">فعالیت‌ها</a>
        <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/settings/documents') ?>">آیین‌نامه و اسناد</a>
    </div>
</div>

<div class="mr-alert mr-alert--<?= $smsReady ? 'success' : 'warning' ?> mb-4">
    سرویس پیامک: <strong><?= e($smsDriver) ?></strong> —
    <?= $smsReady
        ? 'کلیدها پیکربندی شده‌اند و پیام‌ها واقعاً ارسال می‌شوند.'
        : 'کلید API تنظیم نشده است؛ پیام‌ها فقط در فایل لاگ ثبت می‌شوند. مقادیر را در فایل .env قرار دهید.' ?>
</div>

<form method="post" action="<?= url('/admin/settings') ?>">
    <?= csrf_field() ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php foreach ($groups as $group => $meta): if (empty($byGroup[$group])) { continue; } ?>
            <div class="mr-card">
                <div class="mr-card__head">
                    <h2 class="mr-card__title"><?= $meta['icon'] ?> <?= e($meta['title']) ?></h2>
                </div>
                <div class="mr-card__body">
                    <?php foreach ($byGroup[$group] as $key => $type): $value = $settings[$key] ?? ''; ?>
                        <?php if ($type === 'BOOL'): ?>
                            <label class="flex items-center gap-2 border-b py-2">
                                <input type="hidden" name="<?= e($key) ?>" value="0">
                                <input type="checkbox" name="<?= e($key) ?>" value="1" <?= (int)$value === 1 ? 'checked' : '' ?>>
                                <span class="text-sm"><?= e($labels[$key] ?? $key) ?></span>
                            </label>
                        <?php else: ?>
                            <div class="mr-field">
                                <label class="mr-label" for="set_<?= e($key) ?>"><?= e($labels[$key] ?? $key) ?></label>
                                <?php if (in_array($key, ['salon_address', 'invoice_footer', 'meta_description'], true)): ?>
                                    <textarea class="mr-textarea" id="set_<?= e($key) ?>" name="<?= e($key) ?>" rows="2"><?= e((string)$value) ?></textarea>
                                <?php else: ?>
                                    <input class="mr-input <?= in_array($type, ['INT', 'FLOAT'], true) ? 'mr-num' : '' ?>"
                                           id="set_<?= e($key) ?>" name="<?= e($key) ?>"
                                           type="<?= in_array($type, ['INT', 'FLOAT'], true) ? 'number' : 'text' ?>"
                                           <?= $type === 'FLOAT' ? 'step="0.01"' : '' ?>
                                           value="<?= e((string)$value) ?>">
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="mt-4">
        <button class="mr-btn mr-btn--primary mr-btn--lg" type="submit">ذخیره تنظیمات</button>
    </div>
</form>
<?php View::endSection();
