<?php
use App\Core\View;
View::extend('portal');
View::section('content');
/** @var array $stats @var array $evaluations @var array $reviews @var array $topServices @var string $from @var string $to */
$total = max(1, (int)$stats['appointments']);
$rate  = (int)round((int)$stats['completed'] / $total * 100);
?>
<form class="mr-card mb-4" method="get" action="<?= url('/staff/performance') ?>">
    <div class="mr-card__body grid grid-cols-2 md:grid-cols-3 gap-3">
        <div class="mr-field mb-0">
            <label class="mr-label" for="from">از تاریخ</label>
            <input class="mr-input mr-num" id="from" name="from" type="date" dir="ltr" value="<?= e($from) ?>">
        </div>
        <div class="mr-field mb-0">
            <label class="mr-label" for="to">تا تاریخ</label>
            <input class="mr-input mr-num" id="to" name="to" type="date" dir="ltr" value="<?= e($to) ?>">
        </div>
        <div class="flex items-end">
            <button class="mr-btn mr-btn--primary mr-btn--block" type="submit">نمایش</button>
        </div>
    </div>
</form>

<section class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <?= component('kpi', ['label' => 'درآمد ایجادشده', 'icon' => '📈', 'value' => money($stats['revenue'])]) ?>
    <?= component('kpi', ['label' => 'نوبت‌ها', 'icon' => '🗓', 'value' => fa((int)$stats['appointments']), 'hint' => 'تکمیل‌شده: ' . fa((int)$stats['completed'])]) ?>
    <?= component('kpi', ['label' => 'نرخ تکمیل', 'icon' => '✅', 'value' => fa($rate) . '٪', 'hint' => 'عدم حضور: ' . fa((int)$stats['no_show'])]) ?>
    <?= component('kpi', ['label' => 'امتیاز مشتریان', 'icon' => '⭐', 'value' => fa(number_format((float)$stats['rating'], 1))]) ?>
</section>

<section class="mr-card mb-4">
    <div class="mr-card__head"><h2 class="mr-card__title">پرتکرارترین خدمات من</h2></div>
    <div class="mr-card__body">
        <?php if ($topServices === []): ?>
            <?= component('empty', ['icon' => '✂️', 'title' => 'در این بازه خدمتی ثبت نشده است']) ?>
        <?php else: ?>
            <?php $max = max(array_map(static fn ($s) => (int)$s['times'], $topServices)); ?>
            <?php foreach ($topServices as $s): ?>
                <div class="mb-3">
                    <div class="flex justify-between text-sm mb-1">
                        <span><?= e($s['name']) ?></span>
                        <span class="mr-num text-muted"><?= fa((int)$s['times']) ?> بار · <?= money($s['revenue']) ?></span>
                    </div>
                    <div style="height:6px;background:var(--mr-border);border-radius:99px;overflow:hidden">
                        <div style="height:100%;width:<?= (int)round((int)$s['times'] / max(1, $max) * 100) ?>%;background:var(--mr-gold)"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<section class="mr-card mb-4">
    <div class="mr-card__head"><h2 class="mr-card__title">ارزیابی‌های دوره‌ای</h2></div>
    <div class="mr-card__body">
        <?php if ($evaluations === []): ?>
            <?= component('empty', ['icon' => '📋', 'title' => 'هنوز ارزیابی منتشرشده‌ای ندارید', 'text' => 'ارزیابی‌ها پس از تأیید مدیر اینجا نمایش داده می‌شوند.']) ?>
        <?php else: ?>
            <?php foreach ($evaluations as $ev): ?>
                <div class="slot-row">
                    <div class="slot-row__body">
                        <strong>دوره <?= fa($ev['period_start']) ?> تا <?= fa($ev['period_end']) ?></strong>
                        <small>ارزیاب: <?= e($ev['evaluator'] ?? '—') ?></small>
                    </div>
                    <div class="text-end">
                        <div class="font-bold mr-num"><?= fa(number_format((float)$ev['total_score'], 1)) ?></div>
                        <span class="mr-badge mr-badge--gold"><?= e($ev['level']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<section class="mr-card">
    <div class="mr-card__head"><h2 class="mr-card__title">نظر مشتریان درباره من</h2></div>
    <div class="mr-card__body">
        <?php if ($reviews === []): ?>
            <?= component('empty', ['icon' => '💬', 'title' => 'هنوز نظری ثبت نشده است']) ?>
        <?php else: ?>
            <?php foreach ($reviews as $r): ?>
                <div class="border-b py-3">
                    <div class="stars mb-1"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></div>
                    <p class="text-sm mb-1"><?= e($r['comment'] ?? '') ?></p>
                    <small class="text-xs text-muted"><?= e($r['service_name'] ?? '') ?> · <?= jdate($r['created_at'], 'j F Y') ?></small>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
<?php View::endSection();
