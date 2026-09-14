<?php
use App\Core\View;
View::extend('portal');
View::section('content');
/** @var array $days @var string $from @var string $prev @var string $next @var array $weekly @var array $leaves */
$weekdays = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
?>
<div class="flex justify-between items-center mb-4">
    <a class="mr-btn mr-btn--ghost mr-btn--sm" href="<?= url('/staff/schedule?from=' . $prev) ?>">هفته قبل ›</a>
    <span class="text-sm text-muted"><?= jdate($from, 'j F Y') ?></span>
    <a class="mr-btn mr-btn--ghost mr-btn--sm" href="<?= url('/staff/schedule?from=' . $next) ?>">‹ هفته بعد</a>
</div>

<div class="week-grid mb-4">
    <?php foreach ($days as $day): ?>
        <div class="week-day <?= $day['is_today'] ? 'is-today' : '' ?>">
            <div class="week-day__head"><?= e($day['label']) ?></div>
            <?php if ($day['slots'] === []): ?>
                <div class="text-xs text-muted">بدون نوبت</div>
            <?php else: ?>
                <?php foreach ($day['slots'] as $s): ?>
                    <div class="week-day__item">
                        <span class="mr-num font-bold"><?= fa(substr((string)$s['start_time'], 0, 5)) ?></span>
                        <span><?= e($s['customer_name']) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<section class="grid grid-cols-1 md:grid-cols-2 gap-3">
    <div class="mr-card">
        <div class="mr-card__head"><h2 class="mr-card__title">شیفت‌های ثابت من</h2></div>
        <div class="mr-card__body">
            <?php if ($weekly === []): ?>
                <?= component('empty', ['icon' => '🕐', 'title' => 'شیفتی تعریف نشده است', 'text' => 'برای تنظیم شیفت با مدیر شعبه هماهنگ کنید.']) ?>
            <?php else: ?>
                <?php foreach ($weekly as $w): ?>
                    <div class="slot-row">
                        <div class="slot-row__body"><strong><?= e($weekdays[(int)$w['weekday']] ?? '—') ?></strong></div>
                        <div class="mr-num text-sm">
                            <?= fa(substr((string)$w['start_time'], 0, 5)) ?> — <?= fa(substr((string)$w['end_time'], 0, 5)) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="mr-card">
        <div class="mr-card__head"><h2 class="mr-card__title">مرخصی‌های پیش‌رو</h2></div>
        <div class="mr-card__body">
            <?php if ($leaves === []): ?>
                <?= component('empty', ['icon' => '🌴', 'title' => 'مرخصی ثبت‌شده‌ای ندارید']) ?>
            <?php else: ?>
                <?php foreach ($leaves as $l): ?>
                    <div class="slot-row">
                        <div class="slot-row__body">
                            <strong><?= jdate($l['start_date'], 'j F') ?> تا <?= jdate($l['end_date'], 'j F Y') ?></strong>
                            <small><?= e($l['type']) ?></small>
                        </div>
                        <?= component('status_badge', ['status' => $l['status']]) ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php View::endSection();
