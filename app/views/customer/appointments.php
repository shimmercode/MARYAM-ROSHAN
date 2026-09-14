<?php
use App\Core\View;
View::extend('portal');
View::section('content');
/** @var array $appointments @var string $scope @var array $statuses */
?>
<div class="flex gap-2 mb-4">
    <a class="mr-btn <?= $scope !== 'past' ? 'mr-btn--primary' : 'mr-btn--ghost' ?> mr-btn--sm flex-1"
       href="<?= url('/customer/appointments?scope=upcoming') ?>">نوبت‌های پیش‌رو</a>
    <a class="mr-btn <?= $scope === 'past' ? 'mr-btn--primary' : 'mr-btn--ghost' ?> mr-btn--sm flex-1"
       href="<?= url('/customer/appointments?scope=past') ?>">سوابق</a>
</div>

<?php if ($appointments === []): ?>
    <?= component('empty', [
        'icon' => '🗓',
        'title' => $scope === 'past' ? 'سابقه‌ای ثبت نشده است' : 'نوبت پیش‌رویی ندارید',
        'text'  => 'برای رزرو نوبت جدید روی دکمه زیر بزنید.',
        'actionHref' => '/booking', 'actionLabel' => 'رزرو نوبت',
    ]) ?>
<?php else: ?>
    <?php foreach ($appointments as $a): ?>
        <article class="mr-card mb-3">
            <div class="mr-card__body">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <div class="font-bold"><?= e($a['date_fa']) ?></div>
                        <div class="text-sm text-muted mr-num">
                            ساعت <?= fa(substr((string)$a['start_time'], 0, 5)) ?>
                            تا <?= fa(substr((string)$a['end_time'], 0, 5)) ?>
                        </div>
                    </div>
                    <?= component('status_badge', ['status' => $a['status']]) ?>
                </div>

                <div class="text-sm mb-1">✂️ <?= e($a['services'] ?? '') ?></div>
                <div class="text-sm text-muted mb-1">👩‍🎨 <?= e($a['staff_name']) ?></div>
                <div class="text-sm text-muted mb-1">📍 <?= e($a['branch_name']) ?> — <?= e($a['address']) ?></div>
                <div class="text-sm font-bold text-primary mr-num mt-2"><?= money($a['total_price']) ?></div>
                <div class="text-xs text-muted mt-1">کد پیگیری: <span class="mr-num" dir="ltr"><?= e($a['code']) ?></span></div>

                <div class="flex gap-2 mt-3 flex-wrap">
                    <?php if (!empty($a['cancelable'])): ?>
                        <form method="post" action="<?= url('/customer/appointments/' . $a['id'] . '/cancel') ?>"
                              data-confirm="آیا از لغو این نوبت مطمئن هستید؟">
                            <?= csrf_field() ?>
                            <button class="mr-btn mr-btn--danger mr-btn--sm" type="submit">لغو نوبت</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($a['status'] === 'COMPLETED' && (int)$a['reviewed'] === 0): ?>
                        <a class="mr-btn mr-btn--soft mr-btn--sm" href="<?= url('/customer/reviews') ?>">ثبت نظر</a>
                    <?php endif; ?>
                    <a class="mr-btn mr-btn--ghost mr-btn--sm" href="tel:<?= e($a['phone']) ?>">تماس با سالن</a>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
<?php View::endSection();
