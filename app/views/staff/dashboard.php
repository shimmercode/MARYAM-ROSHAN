<?php
use App\Core\View;
View::extend('portal');
View::section('content');
/** @var array $me @var array $today @var string $todayFa @var array $kpis @var array $upcoming @var array $statuses */
?>
<section class="next-card mb-4">
    <div class="next-card__label">امروز — <?= e($todayFa) ?></div>
    <div class="next-card__time mr-num"><?= fa($kpis['today_count']) ?> نوبت</div>
    <div class="flex gap-2 mt-3 flex-wrap"><button class="mr-btn mr-btn--primary mr-btn--sm" id="clockInBtn">ثبت ورود</button><button class="mr-btn mr-btn--ghost mr-btn--sm" id="clockOutBtn">ثبت خروج</button><button class="mr-btn mr-btn--ghost mr-btn--sm" id="breakStartBtn">شروع استراحت</button><button class="mr-btn mr-btn--ghost mr-btn--sm" id="breakEndBtn">پایان استراحت</button></div>
    <div class="next-card__meta">
        <?= fa($kpis['today_done']) ?> مورد انجام شده ·
        درآمد این ماه <span class="mr-num"><?= money($kpis['month_revenue']) ?></span>
    </div>
</section>

<section class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <?= component('kpi', ['label' => 'نوبت‌های این ماه', 'icon' => '🗓', 'value' => fa($kpis['month_count'])]) ?>
    <?= component('kpi', ['label' => 'پورسانت این ماه', 'icon' => '💰', 'value' => money($kpis['commission']), 'href' => '/staff/commissions']) ?>
    <?= component('kpi', ['label' => 'امتیاز من', 'icon' => '⭐', 'value' => fa(number_format((float)$kpis['rating'], 1)), 'href' => '/staff/performance']) ?>
    <?= component('kpi', ['label' => 'درآمد این ماه', 'icon' => '📈', 'value' => money($kpis['month_revenue'])]) ?>
</section>

<section class="mr-card mb-4">
    <div class="mr-card__head">
        <h2 class="mr-card__title">برنامه امروز</h2>
        <a class="text-sm" href="<?= url('/staff/schedule') ?>">برنامه هفته</a>
    </div>
    <div class="mr-card__body">
        <?php if ($today === []): ?>
            <?= component('empty', ['icon' => '☕️', 'title' => 'امروز نوبتی ندارید', 'text' => 'از وقت آزادتان لذت ببرید.']) ?>
        <?php else: ?>
            <?php foreach ($today as $a): ?>
                <div class="slot-row">
                    <div class="slot-row__time mr-num"><?= fa(substr((string)$a['start_time'], 0, 5)) ?></div>
                    <div class="slot-row__body">
                        <strong><?= e($a['customer_name'] ?? '') ?></strong>
                        <small><?= e($a['services'] ?? $a['service_name'] ?? '') ?></small>
                    </div>
                    <?= component('status_badge', ['status' => $a['status']]) ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<section class="mr-card">
    <div class="mr-card__head"><h2 class="mr-card__title">نوبت‌های پیش‌رو</h2></div>
    <div class="mr-card__body">
        <?php if ($upcoming === []): ?>
            <?= component('empty', ['icon' => '📭', 'title' => 'نوبت آینده‌ای ثبت نشده است']) ?>
        <?php else: ?>
            <?php foreach ($upcoming as $a): ?>
                <div class="slot-row">
                    <div class="slot-row__time text-xs"><?= jdate($a['appointment_date'], 'j F') ?></div>
                    <div class="slot-row__body">
                        <strong><?= e($a['customer_name']) ?></strong>
                        <small><?= fa(substr((string)$a['start_time'], 0, 5)) ?> · <?= e($a['services'] ?? '') ?></small>
                    </div>
                    <?= component('status_badge', ['status' => $a['status']]) ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
<?php View::endSection();

View::section('scripts'); ?>
<script type="module">
import { post } from '<?= asset('js/core.js') ?>';
const heartbeat = () => post('<?= url('/api/v1/live-status/heartbeat') ?>', {}).catch(() => {});
const action = (url) => post(url, {}).catch(() => {});
document.getElementById('clockInBtn')?.addEventListener('click', () => action('<?= url('/api/v1/live-status/clock-in') ?>'));
document.getElementById('clockOutBtn')?.addEventListener('click', () => action('<?= url('/api/v1/live-status/clock-out') ?>'));
document.getElementById('breakStartBtn')?.addEventListener('click', () => action('<?= url('/api/v1/live-status/break-start') ?>'));
document.getElementById('breakEndBtn')?.addEventListener('click', () => action('<?= url('/api/v1/live-status/break-end') ?>'));
heartbeat();
window.setInterval(heartbeat, 15000);
</script>
<?php View::endSection();
