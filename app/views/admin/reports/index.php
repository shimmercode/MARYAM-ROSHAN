<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array $reports @var array $kpis @var array $metrics @var array $branches */
$icons = ['sales' => '💵', 'appointments' => '🗓', 'staff' => '👩‍🎨', 'customers' => '🧾', 'inventory' => '📦'];
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb">پنل مدیریت / گزارش‌ها</div>
        <h1>مرکز گزارش‌ها</h1>
        <p>تحلیل عملکرد کسب‌وکار با امکان خروجی CSV</p>
    </div>
</div>

<section class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <?= component('kpi', ['label' => 'درآمد امروز', 'icon' => '💰', 'value' => money($kpis['revenue_today']), 'delta' => $kpis['revenue_change']]) ?>
    <?= component('kpi', ['label' => 'درآمد این ماه', 'icon' => '📈', 'value' => money($kpis['month_revenue'])]) ?>
    <?= component('kpi', ['label' => 'نوبت‌های امروز', 'icon' => '🗓', 'value' => fa((int)$kpis['appointments_today']), 'hint' => 'عدم مراجعه: ' . fa((int)$kpis['no_show_today'])]) ?>
    <?= component('kpi', ['label' => 'مانده دریافتنی', 'icon' => '🧾', 'value' => money($kpis['unpaid_total'])]) ?>
</section>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
    <?php foreach ($reports as $key => $label): ?>
        <a class="mr-card" href="<?= url('/admin/reports/' . $key) ?>">
            <div class="mr-card__body">
                <div class="text-2xl mb-2"><?= $icons[$key] ?? '📊' ?></div>
                <strong><?= e($label) ?></strong>
                <div class="text-xs text-muted mt-1">مشاهده با بازه زمانی دلخواه و خروجی CSV</div>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="mr-card">
        <div class="mr-card__head"><h2 class="mr-card__title">شاخص‌های کسب‌وکار</h2></div>
        <div class="mr-card__body">
            <?php foreach ([
                'کل مشتریان'            => fa((int)$metrics['total_customers']),
                'نرخ بازگشت مشتری'      => fa($metrics['repeat_rate']) . '٪',
                'نرخ نگهداشت (۹۰ روز)'  => fa($metrics['retention_rate']) . '٪',
                'نرخ ریزش'              => fa($metrics['churn_rate']) . '٪',
                'ارزش طول عمر مشتری'    => money($metrics['clv']),
                'ضریب اشغال ظرفیت'      => fa($metrics['occupancy']) . '٪',
                'نرخ عدم مراجعه'        => fa($metrics['no_show_rate']) . '٪',
                'مشتریان فعال ۹۰ روزه'  => fa((int)$metrics['active_90d']),
            ] as $label => $value): ?>
                <div class="flex justify-between border-b py-2 text-sm">
                    <span class="text-muted"><?= e($label) ?></span>
                    <span class="mr-num"><?= $value ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="mr-card">
        <div class="mr-card__head"><h2 class="mr-card__title">عملکرد شعبه‌ها (۳۰ روز)</h2></div>
        <div class="mr-card__body">
            <?php if ($branches === []): ?>
                <?= component('empty', ['icon' => '🏢', 'title' => 'داده‌ای ثبت نشده است']) ?>
            <?php else: ?>
                <div class="mr-table__wrap">
                    <table class="mr-table">
                        <thead><tr><th>شعبه</th><th>نوبت</th><th>درآمد</th></tr></thead>
                        <tbody>
                        <?php foreach ($branches as $b): ?>
                            <tr>
                                <td><?= e($b['name']) ?></td>
                                <td class="mr-num"><?= fa((int)$b['appointments']) ?></td>
                                <td class="mr-num"><?= money($b['revenue'], false) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php View::endSection();
