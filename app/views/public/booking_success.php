<?php
use App\Core\View;
View::extend('public');
View::section('content');
/** @var array $appointment */
?>
<section class="section">
    <div class="site-container" style="max-width:640px">
        <div class="mr-card text-center">
            <div class="mr-card__body">
                <div style="font-size:3rem" aria-hidden="true">🎉</div>
                <h1 class="text-xl mb-2">نوبت شما ثبت شد</h1>
                <p class="text-sm text-muted mb-5">
                    کد پیگیری: <strong class="mr-num" dir="ltr"><?= e($appointment['code']) ?></strong><br>
                    وضعیت فعلی: <?= component('status_badge', ['status' => $appointment['status']]) ?>
                    — پس از تأیید سالن، پیامک تأیید برای شما ارسال می‌شود.
                </p>

                <div class="text-start border rounded-lg p-4 mb-4">
                    <?php
                    $rows = [
                        'تاریخ'  => jdate($appointment['appointment_date'], 'l j F Y'),
                        'ساعت'   => fa(substr((string)$appointment['start_time'], 0, 5)),
                        'خدمات'  => $appointment['services'] ?? '—',
                        'متخصص'  => $appointment['staff_name'],
                        'شعبه'   => $appointment['branch_name'],
                        'مبلغ تقریبی' => money($appointment['total_price']),
                    ];
                    foreach ($rows as $label => $value): ?>
                        <div class="flex justify-between border-b py-2 text-sm">
                            <span class="text-muted"><?= e($label) ?></span>
                            <span class="font-medium"><?= e($value) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mr-alert mr-alert--info text-start">
                    <span aria-hidden="true">📍</span>
                    <span><?= e($appointment['address'] ?? '') ?> — تلفن: <span class="mr-num" dir="ltr"><?= fa($appointment['phone'] ?? '') ?></span></span>
                </div>

                <div class="flex gap-2 mt-4">
                    <a class="mr-btn mr-btn--primary flex-1" href="<?= url('/') ?>">بازگشت به سایت</a>
                    <button class="mr-btn mr-btn--ghost flex-1" type="button" onclick="window.print()">چاپ رسید</button>
                </div>
            </div>
        </div>
    </div>
</section>
<?php View::endSection();
