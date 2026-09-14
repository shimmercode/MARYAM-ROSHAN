<?php
use App\Core\View;
View::extend('portal');
View::section('content');
/** @var float $balance @var array $history */
?>
<section class="next-card mb-4">
    <div class="next-card__label">موجودی کیف پول</div>
    <div class="next-card__time mr-num"><?= money($balance) ?></div>
    <div class="next-card__meta">می‌توانید از این موجودی برای تسویه فاکتورهای خود استفاده کنید.</div>
</section>

<div class="mr-card">
    <div class="mr-card__head"><h2 class="mr-card__title">گردش حساب</h2></div>
    <div class="mr-card__body">
        <?php if ($history === []): ?>
            <?= component('empty', ['icon' => '👛', 'title' => 'هنوز تراکنشی ثبت نشده است']) ?>
        <?php else: ?>
            <?php foreach ($history as $t): $in = (float)$t['amount'] >= 0; ?>
                <div class="slot-row">
                    <div class="slot-row__body">
                        <strong><?= e($t['description'] ?? ($in ? 'افزایش موجودی' : 'برداشت')) ?></strong>
                        <small><?= jdate($t['created_at'], 'j F Y — H:i') ?></small>
                    </div>
                    <div class="mr-num font-bold <?= $in ? 'text-success' : 'text-danger' ?>">
                        <?= $in ? '+' : '−' ?> <?= money(abs((float)$t['amount']), false) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php View::endSection();
