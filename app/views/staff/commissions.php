<?php
use App\Core\View;
View::extend('portal');
View::section('content');
/** @var string $period @var array $periods @var array $summary @var array $commissions */
?>
<form class="mb-4" method="get" action="<?= url('/staff/commissions') ?>">
    <div class="mr-field mb-0">
        <label class="mr-label" for="period">دوره</label>
        <select class="mr-select" id="period" name="period" onchange="this.form.submit()">
            <?php foreach ($periods as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $period === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<section class="grid grid-cols-3 gap-3 mb-4">
    <?= component('kpi', ['label' => 'جمع پورسانت', 'icon' => '💰', 'value' => money($summary['total'] ?? 0)]) ?>
    <?= component('kpi', ['label' => 'پرداخت‌شده', 'icon' => '✅', 'value' => money($summary['paid'] ?? 0)]) ?>
    <?= component('kpi', ['label' => 'در انتظار', 'icon' => '⏳', 'value' => money($summary['pending'] ?? 0)]) ?>
</section>

<div class="mr-card">
    <div class="mr-card__head"><h2 class="mr-card__title">ریز پورسانت</h2></div>
    <div class="mr-card__body">
        <?php if ($commissions === []): ?>
            <?= component('empty', ['icon' => '💰', 'title' => 'در این دوره پورسانتی ثبت نشده است']) ?>
        <?php else: ?>
            <div class="mr-table__wrap">
                <table class="mr-table">
                    <thead><tr><th>تاریخ</th><th>خدمت</th><th>مبلغ پایه</th><th>نرخ</th><th>پورسانت</th><th>وضعیت</th></tr></thead>
                    <tbody>
                    <?php foreach ($commissions as $c): ?>
                        <tr>
                            <td class="text-xs"><?= jdate($c['created_at'], 'j F') ?></td>
                            <td><?= e($c['service_name'] ?? '—') ?></td>
                            <td class="mr-num"><?= money($c['base_amount'], false) ?></td>
                            <td class="mr-num"><?= $c['calc_type'] === 'PERCENT' ? fa((float)$c['calc_value']) . '٪' : money($c['calc_value'], false) ?></td>
                            <td class="mr-num font-bold"><?= money($c['amount'], false) ?></td>
                            <td><?= component('status_badge', ['status' => $c['status']]) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php View::endSection();
