<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/**
 * @var string $report @var array $reports @var array $columns @var array $rows
 * @var array $summary @var array|null $chart @var array $range
 * @var int|null $branchId @var array $branches
 */
$moneyCols = ['subtotal', 'discount', 'tax', 'total', 'paid', 'due', 'revenue', 'commission', 'value', 'total_spent', 'purchase_price', 'stock_value'];
$query = ['from' => $range['from'], 'to' => $range['to'], 'branch_id' => $branchId];
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb"><a href="<?= url('/admin/reports') ?>">گزارش‌ها</a> / <?= e($reports[$report]) ?></div>
        <h1><?= e($reports[$report]) ?></h1>
        <p><?= e($range['label']) ?></p>
    </div>
    <a class="mr-btn mr-btn--primary" href="<?= url('/admin/reports/' . $report . '/export?' . http_build_query($query)) ?>">خروجی CSV</a>
</div>

<nav class="flex gap-2 flex-wrap mb-4">
    <?php foreach ($reports as $key => $label): ?>
        <a class="mr-btn mr-btn--sm <?= $key === $report ? 'mr-btn--primary' : 'mr-btn--ghost' ?>"
           href="<?= url('/admin/reports/' . $key . '?' . http_build_query($query)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<form class="mr-card mb-4" method="get" action="<?= url('/admin/reports/' . $report) ?>">
    <div class="mr-card__body grid grid-cols-1 md:grid-cols-4 gap-3">
        <div class="mr-field mb-0">
            <label class="mr-label" for="from">از تاریخ</label>
            <input class="mr-input mr-num" id="from" name="from" type="date" dir="ltr" value="<?= e($range['from']) ?>">
        </div>
        <div class="mr-field mb-0">
            <label class="mr-label" for="to">تا تاریخ</label>
            <input class="mr-input mr-num" id="to" name="to" type="date" dir="ltr" value="<?= e($range['to']) ?>">
        </div>
        <div class="mr-field mb-0">
            <label class="mr-label" for="branch_id">شعبه</label>
            <select class="mr-select" id="branch_id" name="branch_id">
                <option value="">همه</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= (int)$b['id'] ?>" <?= (int)$branchId === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-end"><button class="mr-btn mr-btn--primary w-full" type="submit">اعمال بازه</button></div>
    </div>
</form>

<?php if ($summary !== []): ?>
    <section class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <?php foreach ($summary as $label => $value): ?>
            <?= component('kpi', [
                'label' => (string)$label,
                'value' => is_numeric($value) && $value > 10000 ? money($value) : fa(is_float($value) ? round($value) : $value),
            ]) ?>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php if ($chart !== null && $chart['labels'] !== []): ?>
    <div class="mr-card mb-4">
        <div class="mr-card__head"><h2 class="mr-card__title"><?= e($chart['label']) ?></h2></div>
        <div class="mr-card__body"><canvas id="reportChart" height="90"></canvas></div>
    </div>
<?php endif; ?>

<div class="mr-card">
    <div class="mr-card__body">
        <?php if ($rows === []): ?>
            <?= component('empty', ['icon' => '📄', 'title' => 'داده‌ای در این بازه یافت نشد', 'text' => 'بازه زمانی یا شعبه را تغییر دهید.']) ?>
        <?php else: ?>
            <div class="mr-table__wrap">
                <table class="mr-table">
                    <thead>
                    <tr><?php foreach ($columns as $header): ?><th><?= e($header) ?></th><?php endforeach; ?></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($columns as $key => $header): $val = $row[$key] ?? ''; ?>
                                <td class="<?= is_numeric($val) ? 'mr-num' : '' ?>">
                                    <?php if (in_array($key, $moneyCols, true)): ?>
                                        <?= money($val, false) ?>
                                    <?php elseif (is_numeric($val)): ?>
                                        <?= fa($val) ?>
                                    <?php else: ?>
                                        <?= e((string)$val) ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php View::endSection();

if ($chart !== null && $chart['labels'] !== []):
    View::section('scripts'); ?>
    <script src="<?= asset('vendor/chart/chart.umd.min.js') ?>"></script>
    <script>
    (function () {
        const canvas = document.getElementById('reportChart');
        if (!canvas || typeof Chart === 'undefined') return;
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chart['labels'], JSON_UNESCAPED_UNICODE) ?>,
                datasets: [{
                    label: <?= json_encode($chart['label'], JSON_UNESCAPED_UNICODE) ?>,
                    data: <?= json_encode($chart['values']) ?>,
                    backgroundColor: 'rgba(201, 162, 39, 0.65)',
                    borderColor: '#C9A227',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { font: { family: 'Vazirmatn' } } },
                          x: { ticks: { font: { family: 'Vazirmatn' } } } }
            }
        });
    })();
    </script>
    <?php View::endSection();
endif;
