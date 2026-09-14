<?php
use App\Core\View;
View::extend('portal');
View::section('content');
/** @var array $customer @var array|null $tier @var int $points @var float $wallet @var array|null $next @var array $recent @var array $unpaid */
?>
<?php if ($next !== null): ?>
    <section class="next-card mb-4">
        <div class="next-card__label">نوبت بعدی شما</div>
        <div class="next-card__time mr-num"><?= fa(substr((string)$next['start_time'], 0, 5)) ?></div>
        <div class="next-card__meta mb-3"><?= jdate($next['appointment_date'], 'l j F Y') ?></div>
        <div class="next-card__meta"><?= e($next['services'] ?? '') ?> — <?= e($next['staff_name']) ?></div>
        <div class="next-card__meta">📍 <?= e($next['branch_name']) ?></div>
        <div class="flex gap-2 mt-4">
            <a class="mr-btn mr-btn--primary mr-btn--sm" href="<?= url('/customer/appointments') ?>">جزئیات نوبت</a>
            <span class="mr-badge mr-badge--gold align-self-center"><?= e($statuses[$next['status']] ?? $next['status']) ?></span>
        </div>
    </section>
<?php else: ?>
    <section class="mr-card mb-4">
        <div class="mr-card__body text-center">
            <div style="font-size:2.4rem" aria-hidden="true">✨</div>
            <h2 class="text-md mb-1">نوبت فعالی ندارید</h2>
            <p class="text-sm text-muted mb-4">همین حالا نوبت بعدی خود را رزرو کنید.</p>
            <a class="mr-btn mr-btn--primary" href="<?= url('/booking') ?>">رزرو نوبت جدید</a>
        </div>
    </section>
<?php endif; ?>

<section class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <?= component('kpi', ['label' => 'امتیاز باشگاه', 'icon' => '🎁', 'value' => fa($points), 'href' => '/customer/loyalty']) ?>
    <?= component('kpi', ['label' => 'کیف پول', 'icon' => '👛', 'value' => money($wallet), 'href' => '/customer/wallet']) ?>
    <?= component('kpi', ['label' => 'تعداد مراجعه', 'icon' => '💺', 'value' => fa((int)$customer['visits_count'])]) ?>
    <?= component('kpi', [
        'label' => 'سطح عضویت', 'icon' => '👑',
        'value' => $tier['name'] ?? 'برنزی',
        'hint'  => isset($tier['discount_percent']) ? fa((float)$tier['discount_percent']) . '٪ تخفیف دائمی' : null,
        'href'  => '/customer/loyalty',
    ]) ?>
</section>

<?php if ($unpaid !== []): ?>
    <section class="mr-card mb-4">
        <div class="mr-card__head"><h2 class="mr-card__title">فاکتورهای پرداخت‌نشده</h2></div>
        <div class="mr-card__body">
            <?php foreach ($unpaid as $inv): ?>
                <div class="slot-row">
                    <div class="slot-row__body">
                        <strong><?= e($inv['invoice_number']) ?></strong>
                        <small><?= jdate($inv['issue_date'], 'j F Y') ?></small>
                    </div>
                    <div class="text-end">
                        <div class="font-bold text-danger mr-num"><?= money($inv['due']) ?></div>
                        <a class="text-xs" href="<?= url('/customer/invoices/' . $inv['id']) ?>">مشاهده</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="mr-card">
    <div class="mr-card__head">
        <h2 class="mr-card__title">آخرین مراجعه‌ها</h2>
        <a class="text-sm" href="<?= url('/customer/appointments?scope=past') ?>">همه</a>
    </div>
    <div class="mr-card__body">
        <?php if ($recent === []): ?>
            <?= component('empty', ['icon' => '📭', 'title' => 'هنوز سابقه مراجعه ندارید', 'actionHref' => '/booking', 'actionLabel' => 'رزرو نوبت']) ?>
        <?php else: ?>
            <?php foreach ($recent as $a): ?>
                <div class="slot-row">
                    <div class="slot-row__time mr-num"><?= fa(substr((string)$a['start_time'], 0, 5)) ?></div>
                    <div class="slot-row__body">
                        <strong><?= e($a['services'] ?? $a['service_name'] ?? 'خدمت') ?></strong>
                        <small><?= jdate($a['appointment_date'], 'j F Y') ?></small>
                    </div>
                    <?= component('status_badge', ['status' => $a['status']]) ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
<?php View::endSection();
