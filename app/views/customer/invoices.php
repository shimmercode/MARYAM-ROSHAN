<?php
use App\Core\View;
View::extend('portal');
View::section('content');
/** @var array $invoices @var array $totals */
?>
<section class="grid grid-cols-3 gap-3 mb-4">
    <?= component('kpi', ['label' => 'مجموع فاکتورها', 'icon' => '🧾', 'value' => money($totals['total'])]) ?>
    <?= component('kpi', ['label' => 'پرداخت‌شده', 'icon' => '✅', 'value' => money($totals['paid'])]) ?>
    <?= component('kpi', ['label' => 'مانده', 'icon' => '⏳', 'value' => money($totals['due'])]) ?>
</section>

<?php if ($invoices === []): ?>
    <?= component('empty', ['icon' => '🧾', 'title' => 'هنوز فاکتوری برای شما صادر نشده است']) ?>
<?php else: ?>
    <?php foreach ($invoices as $inv): ?>
        <a class="mr-card mb-3 block" href="<?= url('/customer/invoices/' . $inv['id']) ?>">
            <div class="mr-card__body flex justify-between items-center">
                <div>
                    <div class="font-bold mr-num" dir="ltr"><?= e($inv['invoice_number']) ?></div>
                    <div class="text-sm text-muted"><?= jdate($inv['issue_date'], 'j F Y') ?></div>
                </div>
                <div class="text-end">
                    <div class="font-bold mr-num"><?= money($inv['total']) ?></div>
                    <?= component('status_badge', ['status' => $inv['payment_status']]) ?>
                </div>
            </div>
        </a>
    <?php endforeach; ?>
<?php endif; ?>
<?php View::endSection();
