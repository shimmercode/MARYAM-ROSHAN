<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array $branches @var array $staff @var string $today @var string $todayJalali */
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb"><a href="<?= url('/admin/appointments') ?>">نوبت‌ها</a> / تقویم</div>
        <h1>تقویم نوبت‌ها</h1>
        <p>نمای هفتگی — امروز <span class="mr-num"><?= fa($todayJalali) ?></span></p>
    </div>
    <div class="flex gap-2 flex-wrap">
        <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/appointments') ?>">نمای فهرستی</a>
        <a class="mr-btn mr-btn--primary" href="<?= url('/admin/appointments/create') ?>">رزرو نوبت</a>
    </div>
</div>

<div class="mr-card mb-4">
    <div class="mr-card__body grid grid-cols-1 md:grid-cols-4 gap-3">
        <div class="mr-field mb-0">
            <label class="mr-label" for="branchFilter">شعبه</label>
            <select class="mr-select" id="branchFilter">
                <option value="">همه شعبه‌ها</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mr-field mb-0">
            <label class="mr-label" for="staffFilter">متخصص</label>
            <select class="mr-select" id="staffFilter">
                <option value="">همه پرسنل</option>
                <?php foreach ($staff as $s): ?>
                    <option value="<?= (int)$s['id'] ?>"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mr-field mb-0">
            <label class="mr-label" for="fromDate">شروع هفته</label>
            <input class="mr-input mr-num" id="fromDate" type="date" dir="ltr" value="<?= e($today) ?>">
        </div>
        <div class="flex items-end gap-2">
            <button class="mr-btn mr-btn--ghost flex-1" type="button" id="prevWeek">هفته قبل ›</button>
            <button class="mr-btn mr-btn--ghost flex-1" type="button" id="nextWeek">‹ هفته بعد</button>
        </div>
    </div>
</div>

<div class="mr-card">
    <div class="mr-card__body" id="calendarHost" style="min-height:320px"></div>
</div>
<?php View::endSection();

View::section('scripts'); ?>
<script type="module">
import { get, escapeHtml, toFa, money, showLoading, showEmpty, showError } from '<?= asset('js/core.js') ?>';

const host = document.getElementById('calendarHost');
const fromInput = document.getElementById('fromDate');

const STATUS_CLASS = {
    PENDING: 'mr-badge--warning', CONFIRMED: 'mr-badge--info', CHECKED_IN: 'mr-badge--info',
    IN_PROGRESS: 'mr-badge--gold', COMPLETED: 'mr-badge--success',
    CANCELLED: 'mr-badge--danger', NO_SHOW: 'mr-badge--danger',
};

const shift = (date, days) => {
    const d = new Date(date);
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
};

async function load() {
    const from = fromInput.value;
    const to   = shift(from, 6);
    showLoading(host, 5);
    try {
        const events = await get('<?= url('/admin/appointments/calendar/feed') ?>', {
            from, to,
            branch_id: document.getElementById('branchFilter').value,
            staff_id: document.getElementById('staffFilter').value,
        });

        if (!events.length) {
            showEmpty(host, 'در این هفته نوبتی ثبت نشده است', 'بازه یا فیلترها را تغییر دهید.', '📅');
            return;
        }

        // Group by day so each column stays independent.
        const byDay = {};
        for (let i = 0; i < 7; i++) byDay[shift(from, i)] = [];
        events.forEach((ev) => { (byDay[ev.date || ev.appointment_date] ??= []).push(ev); });

        host.innerHTML = `<div class="week-grid">${Object.entries(byDay).map(([date, list]) => `
            <div class="week-day ${date === '<?= $today ?>' ? 'is-today' : ''}">
                <div class="week-day__head">${escapeHtml(new Intl.DateTimeFormat('fa-IR', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date(date)))}</div>
                ${list.length === 0
                    ? '<div class="text-xs text-muted">بدون نوبت</div>'
                    : list.sort((a, b) => String(a.start_time).localeCompare(String(b.start_time))).map((ev) => `
                        <a class="week-day__item block" href="<?= url('/admin/appointments/') ?>${ev.id}">
                            <span class="mr-num font-bold">${toFa(String(ev.start_time).slice(0, 5))}</span>
                            <span class="block">${escapeHtml(ev.customer_name || ev.title || '')}</span>
                            <span class="mr-badge ${STATUS_CLASS[ev.status] || ''}" style="font-size:.62rem">${escapeHtml(ev.status_label || ev.status || '')}</span>
                        </a>`).join('')}
            </div>`).join('')}</div>`;
    } catch (err) {
        showError(host, err.message, load);
    }
}

document.getElementById('prevWeek').addEventListener('click', () => { fromInput.value = shift(fromInput.value, -7); load(); });
document.getElementById('nextWeek').addEventListener('click', () => { fromInput.value = shift(fromInput.value, 7); load(); });
['branchFilter', 'staffFilter', 'fromDate'].forEach((id) => document.getElementById(id).addEventListener('change', load));

load();
</script>
<?php View::endSection();
