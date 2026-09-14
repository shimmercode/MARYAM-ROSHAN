<?php
use App\Core\View;
View::extend('portal');
View::section('content');
/** @var array $customer @var array $appointments @var array $services */
?>
<section class="mr-card mb-4">
    <div class="mr-card__body">
        <div class="flex items-center gap-3 mb-3">
            <span class="mr-avatar" style="width:52px;height:52px;font-size:1.1rem">
                <?= e(mb_substr((string)$customer['first_name'], 0, 1)) ?>
            </span>
            <div>
                <h2 class="text-lg mb-0"><?= e($customer['first_name'] . ' ' . $customer['last_name']) ?></h2>
                <a class="text-sm mr-num" dir="ltr" href="tel:<?= e($customer['mobile']) ?>"><?= fa($customer['mobile']) ?></a>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-3">
            <div class="text-center">
                <div class="text-xs text-muted">مراجعه</div>
                <div class="font-bold mr-num"><?= fa((int)$customer['visits_count']) ?></div>
            </div>
            <div class="text-center">
                <div class="text-xs text-muted">سطح</div>
                <div class="font-bold"><?= e($customer['tier_name'] ?? 'برنزی') ?></div>
            </div>
            <div class="text-center">
                <div class="text-xs text-muted">آخرین مراجعه</div>
                <div class="font-bold text-xs"><?= $customer['last_visit_at'] ? jdate($customer['last_visit_at'], 'j F Y') : '—' ?></div>
            </div>
        </div>
    </div>
</section>

<?php if ($services !== []): ?>
    <section class="mr-card mb-4">
        <div class="mr-card__head"><h2 class="mr-card__title">خدمات پرتکرار این مشتری</h2></div>
        <div class="mr-card__body flex gap-2 flex-wrap">
            <?php foreach ($services as $s): ?>
                <span class="mr-badge"><?= e($s['name']) ?> × <?= fa((int)$s['times']) ?></span>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="mr-card">
    <div class="mr-card__head"><h2 class="mr-card__title">سابقه مراجعه نزد من</h2></div>
    <div class="mr-card__body">
        <?php if ($appointments === []): ?>
            <?= component('empty', ['icon' => '📭', 'title' => 'سابقه‌ای ثبت نشده است']) ?>
        <?php else: ?>
            <?php foreach ($appointments as $a): ?>
                <div class="slot-row">
                    <div class="slot-row__time text-xs"><?= jdate($a['appointment_date'], 'j F y') ?></div>
                    <div class="slot-row__body">
                        <strong><?= e($a['services'] ?? '') ?></strong>
                        <?php if (!empty($a['notes'])): ?><small>📝 <?= e($a['notes']) ?></small><?php endif; ?>
                    </div>
                    <?= component('status_badge', ['status' => $a['status']]) ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
<?php View::endSection();
