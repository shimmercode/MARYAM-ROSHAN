<?php
use App\Core\View;

View::extend('admin');
View::section('content');
/** @var array $kpis @var array $metrics @var array $todayList */
?>
<div class="ph mr-page-head">
    <div>
        <div class="mr-breadcrumb">پنل مدیریت</div>
        <h1>داشبورد</h1>
        <p>خلاصه عملکرد امروز — <?= jdate(date('Y-m-d'), 'l j F Y') ?></p>
    </div>
    <div class="pa flex gap-2 flex-wrap">
        <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/appointments/calendar') ?>">تقویم نوبت‌ها</a>
        <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/customers/create') ?>">مشتری جدید</a>
        <a class="mr-btn mr-btn--primary" href="<?= url('/admin/pos') ?>">صندوق فروش</a>
    </div>
</div>

<section class="kg">
    <?= component('kpi', [
        'label' => 'فروش امروز', 'icon' => '💰', 'tone' => 'success',
        'value' => money($kpis['revenue_today']), 'delta' => $kpis['revenue_change'],
    ]) ?>
    <?= component('kpi', [
        'label' => 'نوبت‌های امروز', 'icon' => '🗓', 'tone' => 'info',
        'value' => fa($kpis['appointments_today']),
        'hint'  => 'عدم حضور: ' . fa($kpis['no_show_today']),
        'href'  => '/admin/appointments',
    ]) ?>
    <?= component('kpi', [
        'label' => 'مشتریان جدید امروز', 'icon' => '✨', 'tone' => 'primary',
        'value' => fa($kpis['new_customers']),
        'hint'  => 'بازگشتی: ' . fa($kpis['returning_customers']),
        'href'  => '/admin/customers',
    ]) ?>
    <?= component('kpi', [
        'label' => 'میانگین سبد خرید', 'icon' => '🧾', 'tone' => 'warning',
        'value' => money($kpis['average_ticket']), 'hint' => '۳۰ روز گذشته',
    ]) ?>
</section>

<section class="mr-card mb-5" id="liveOperations" aria-live="polite">
    <div class="mr-card__head"><h2 class="mr-card__title">وضعیت لحظه‌ای سالن</h2><span class="text-xs text-muted" id="liveUpdated">در حال دریافت...</span></div>
    <div class="mr-card__body grid grid-cols-2 md:grid-cols-5 gap-3">
        <div><span class="text-xs text-muted">حاضر</span><strong class="block text-xl" id="livePresent">—</strong></div>
        <div><span class="text-xs text-muted">در حال خدمت</span><strong class="block text-xl text-info" id="liveServing">—</strong></div>
        <div><span class="text-xs text-muted">آزاد</span><strong class="block text-xl text-success" id="liveAvailable">—</strong></div>
        <div><span class="text-xs text-muted">نیازمند بررسی</span><strong class="block text-xl text-warning" id="liveAttention">—</strong></div>
        <div><span class="text-xs text-muted">غایب</span><strong class="block text-xl text-danger" id="liveAbsent">—</strong></div>
    </div>
</section>
<section class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
    <div class="mr-card"><div class="mr-card__head"><h2 class="mr-card__title">هشدارهای عملیاتی</h2></div><div class="mr-card__body" id="liveAlerts"><span class="text-muted text-sm">در حال دریافت...</span></div></div>
    <div class="mr-card"><div class="mr-card__head"><h2 class="mr-card__title">فعالیت‌های اخیر</h2></div><div class="mr-card__body" id="liveActivities"><span class="text-muted text-sm">در حال دریافت...</span></div></div>
</section>

<section class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
    <div class="mr-card col-span-2">
        <div class="mr-card__head">
            <h2 class="mr-card__title">روند فروش</h2>
            <div class="flex gap-1">
                <button class="mr-btn mr-btn--ghost mr-btn--sm" data-range="7">۷ روز</button>
                <button class="mr-btn mr-btn--soft mr-btn--sm" data-range="30">۳۰ روز</button>
                <button class="mr-btn mr-btn--ghost mr-btn--sm" data-range="90">۹۰ روز</button>
            </div>
        </div>
        <div class="mr-card__body" id="revenueChartHost" style="min-height:280px">
            <canvas id="revenueChart" height="110" aria-label="نمودار فروش"></canvas>
        </div>
    </div>

    <div class="mr-card">
        <div class="mr-card__head"><h2 class="mr-card__title">شاخص‌های کسب‌وکار</h2></div>
        <div class="mr-card__body flex flex-col gap-3">
            <?php
            $rows = [
                ['نرخ بازگشت مشتری', $metrics['repeat_rate'] . '٪'],
                ['نرخ نگه‌داشت (۹۰ روز)', $metrics['retention_rate'] . '٪'],
                ['نرخ ریزش', $metrics['churn_rate'] . '٪'],
                ['ارزش طول عمر مشتری', money($metrics['clv'])],
                ['ضریب اشغال', $metrics['occupancy'] . '٪'],
                ['نرخ عدم حضور', $metrics['no_show_rate'] . '٪'],
            ];
            foreach ($rows as [$label, $value]): ?>
                <div class="flex items-center justify-between border-b py-2">
                    <span class="text-sm text-muted"><?= e($label) ?></span>
                    <span class="font-bold mr-num"><?= fa($value) ?></span>
                </div>
            <?php endforeach; ?>
            <div class="flex items-center justify-between">
                <span class="text-sm text-muted">مانده مطالبات</span>
                <span class="font-bold text-danger mr-num"><?= money($kpis['unpaid_total']) ?></span>
            </div>
        </div>
    </div>
</section>

<section class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
    <div class="mr-card col-span-2">
        <div class="mr-card__head">
            <h2 class="mr-card__title">نوبت‌های امروز</h2>
            <a class="text-sm" href="<?= url('/admin/appointments') ?>">مشاهده همه</a>
        </div>
        <?php if ($todayList === []): ?>
            <?= component('empty', [
                'icon' => '🗓', 'title' => 'امروز نوبتی ثبت نشده است',
                'text' => 'می‌توانید همین حالا اولین نوبت امروز را ثبت کنید.',
                'actionHref' => '/admin/appointments/create', 'actionLabel' => 'ثبت نوبت',
            ]) ?>
        <?php else: ?>
        <div class="mr-table__wrap">
            <table class="mr-table">
                <thead><tr>
                    <th>ساعت</th><th>مشتری</th><th>خدمات</th><th>متخصص</th><th>مبلغ</th><th>وضعیت</th><th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($todayList as $a): ?>
                    <tr>
                        <td class="mr-num font-medium"><?= fa(substr((string)$a['start_time'], 0, 5)) ?></td>
                        <td>
                            <div class="font-medium"><?= e($a['customer_name']) ?></div>
                            <div class="text-xs text-muted mr-num"><?= fa($a['mobile']) ?></div>
                        </td>
                        <td class="text-sm text-muted truncate" style="max-width:220px"><?= e($a['services'] ?? '—') ?></td>
                        <td class="text-sm">
                            <span class="mr-badge" style="background:<?= e($a['staff_color'] ?? '#eee') ?>22">
                                <?= e($a['staff_name']) ?>
                            </span>
                        </td>
                        <td class="mr-num"><?= money($a['total_price'], false) ?></td>
                        <td><?= component('status_badge', ['status' => $a['status']]) ?></td>
                        <td><a class="mr-btn mr-btn--ghost mr-btn--sm" href="<?= url('/admin/appointments/' . $a['id']) ?>">جزئیات</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="mr-card">
        <div class="mr-card__head"><h2 class="mr-card__title">مشتریان نیازمند پیگیری</h2></div>
        <?php if ($alerts === []): ?>
            <?= component('empty', ['icon' => '🎉', 'title' => 'همه‌چیز مرتب است', 'text' => 'مشتری غیرفعالی وجود ندارد.']) ?>
        <?php else: ?>
            <div class="mr-card__body flex flex-col gap-3">
                <?php foreach ($alerts as $c): ?>
                    <a class="flex items-center gap-3" href="<?= url('/admin/customers/' . $c['id']) ?>">
                        <span class="mr-avatar"><?= e(mb_substr((string)$c['first_name'], 0, 1)) ?></span>
                        <span class="flex-1 min-w-0">
                            <span class="block text-sm font-medium truncate"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></span>
                            <span class="block text-xs text-muted"><?= fa((int)$c['days_since']) ?> روز از آخرین مراجعه</span>
                        </span>
                        <span class="mr-badge mr-badge--danger"><?= fa(round((float)$c['churn_risk'])) ?>٪</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="mr-card">
        <div class="mr-card__head"><h2 class="mr-card__title">پرفروش‌ترین خدمات</h2></div>
        <div class="mr-card__body">
            <?php if ($topServices === []): ?>
                <?= component('empty', ['icon' => '✂️', 'title' => 'داده‌ای موجود نیست', 'text' => 'پس از صدور اولین فاکتورها نمایش داده می‌شود.']) ?>
            <?php else: ?>
                <?php $max = max(array_map(static fn ($s) => (float)$s['revenue'], $topServices)) ?: 1; ?>
                <?php foreach ($topServices as $s): ?>
                    <div class="mb-3">
                        <div class="flex justify-between text-sm">
                            <span><?= e($s['name']) ?></span>
                            <span class="mr-num text-muted"><?= money($s['revenue'], false) ?></span>
                        </div>
                        <div class="rounded-full bg-canvas" style="height:6px">
                            <div class="rounded-full bg-primary" style="height:6px;width:<?= round((float)$s['revenue'] / $max * 100) ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="mr-card">
        <div class="mr-card__head"><h2 class="mr-card__title">آخرین پرداخت‌ها</h2></div>
        <div class="mr-card__body">
            <?php if ($recentPayments === []): ?>
                <?= component('empty', ['icon' => '💳', 'title' => 'پرداختی ثبت نشده']) ?>
            <?php else: ?>
                <?php foreach ($recentPayments as $p): ?>
                    <div class="flex items-center justify-between border-b py-2">
                        <div>
                            <div class="text-sm font-medium"><?= e($p['customer_name']) ?></div>
                            <div class="text-xs text-muted mr-num"><?= e($p['payment_number']) ?> · <?= jdate($p['paid_at'], 'Y/m/d H:i') ?></div>
                        </div>
                        <span class="mr-num font-bold <?= $p['type'] === 'REFUND' ? 'text-danger' : 'text-success' ?>">
                            <?= money($p['amount'], false) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="mr-card">
        <div class="mr-card__head">
            <h2 class="mr-card__title">هشدار موجودی</h2>
            <a class="text-sm" href="<?= url('/admin/inventory') ?>">انبار</a>
        </div>
        <div class="mr-card__body">
            <?php if ($lowStock === []): ?>
                <?= component('empty', ['icon' => '📦', 'title' => 'موجودی‌ها سالم است']) ?>
            <?php else: ?>
                <?php foreach ($lowStock as $p): ?>
                    <div class="flex items-center justify-between border-b py-2">
                        <div>
                            <div class="text-sm font-medium"><?= e($p['name']) ?></div>
                            <div class="text-xs text-muted"><?= e($p['branch_name']) ?></div>
                        </div>
                        <span class="mr-badge mr-badge--warning mr-num">
                            <?= fa($p['quantity']) ?> <?= e($p['unit']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php
View::endSection();

View::section('scripts');
?>
<script src="<?= asset('vendor/chart/chart.umd.min.js') ?>"></script>
<script type="module">
import { get, toFa, showError } from '<?= asset('js/core.js') ?>';

const host = document.getElementById('revenueChartHost');
let chart = null;

async function load(days) {
    try {
        const data = await get('<?= url('/admin/dashboard/charts') ?>', { days });
        const labels = data.revenue.map((r) => toFa(r.label ?? r.date));
        const revenue = data.revenue.map((r) => Number(r.value ?? 0));
        const appts = data.appointments.map((r) => Number(r.value ?? 0));

        if (chart) chart.destroy();
        const ctx = document.getElementById('revenueChart');
        if (!ctx || typeof Chart === 'undefined') return;

        chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    { label: 'فروش (تومان)', data: revenue, borderColor: '#384381', backgroundColor: 'rgba(56,67,129,.12)',
                      fill: true, tension: .35, borderWidth: 2, pointRadius: 0, yAxisID: 'y' },
                    { label: 'تعداد نوبت', data: appts, borderColor: '#4690bf', borderDash: [5, 4],
                      tension: .35, borderWidth: 2, pointRadius: 0, yAxisID: 'y1' },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { labels: { font: { family: 'Vazirmatn' } } } },
                scales: {
                    x: { reverse: true, ticks: { font: { family: 'Vazirmatn' }, maxTicksLimit: 10 }, grid: { display: false } },
                    y:  { position: 'right', ticks: { font: { family: 'Vazirmatn' }, callback: (v) => toFa(v.toLocaleString('en-US')) } },
                    y1: { position: 'left', grid: { display: false }, ticks: { font: { family: 'Vazirmatn' }, precision: 0 } },
                },
            },
        });
    } catch (e) {
        showError(host, e.message, () => load(days));
    }
}

document.querySelectorAll('[data-range]').forEach((btn) => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('[data-range]').forEach((b) => {
            b.classList.remove('mr-btn--soft'); b.classList.add('mr-btn--ghost');
        });
        btn.classList.add('mr-btn--soft'); btn.classList.remove('mr-btn--ghost');
        load(btn.dataset.range);
    });
});

load(30);

async function loadLive() {
    try {
        const live = await get('<?= url('/api/v1/live-status') ?>');
        const s = live.summary?.staff || {};
        document.getElementById('livePresent').textContent = toFa(s.PRESENT || 0);
        document.getElementById('liveServing').textContent = toFa(s.SERVING || 0);
        document.getElementById('liveAvailable').textContent = toFa(s.AVAILABLE || 0);
        document.getElementById('liveAttention').textContent = toFa(s.ATTENTION || 0);
        document.getElementById('liveAbsent').textContent = toFa(s.ABSENT || 0);
        document.getElementById('liveAlerts').innerHTML = (live.alerts || []).slice(0, 6).map(a => `<div class=\"border-b py-2 text-sm\"><strong class=\"text-warning\">⚠ ${a.staff_name}</strong><div class=\"text-muted\">${a.message}</div></div>`).join('') || '<span class=\"text-success text-sm\">هشداری وجود ندارد.</span>';
        document.getElementById('liveActivities').innerHTML = (live.activities || []).slice(0, 6).map(a => `<div class=\"border-b py-2 text-sm\"><strong>${a.staff_name || 'سیستم'}</strong><div class=\"text-muted\">${a.event_type} · ${a.created_at}</div></div>`).join('') || '<span class=\"text-muted text-sm\">فعالیتی ثبت نشده است.</span>';
        document.getElementById('liveUpdated').textContent = 'آخرین بروزرسانی: ' + new Date().toLocaleTimeString('fa-IR');
    } catch (e) {
        document.getElementById('liveUpdated').textContent = 'دریافت وضعیت زنده ناموفق بود';
    }
}
loadLive();
window.setInterval(loadLive, 10000);
</script>
<?php
View::endSection();
