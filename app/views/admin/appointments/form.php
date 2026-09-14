<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array $branches @var array $categories @var array $services @var array $staff @var int|null $customerId */
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb"><a href="<?= url('/admin/appointments') ?>">نوبت‌ها</a> / رزرو جدید</div>
        <h1>رزرو نوبت جدید</h1>
        <p>مشتری، خدمات و زمان را انتخاب کنید. سامانه از تداخل زمانی جلوگیری می‌کند.</p>
    </div>
    <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/appointments') ?>">بازگشت</a>
</div>

<form id="apptForm">
    <?= csrf_field() ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="col-span-2">
            <div class="mr-card mb-4">
                <div class="mr-card__head"><h2 class="mr-card__title">۱. مشتری</h2></div>
                <div class="mr-card__body">
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="customerSearch">جستجوی مشتری <span class="req">*</span></label>
                        <input class="mr-input" id="customerSearch" autocomplete="off"
                               placeholder="نام، موبایل یا کد مشتری را بنویسید…">
                        <input type="hidden" name="customer_id" id="customer_id" value="<?= $customerId ? (int)$customerId : '' ?>">
                        <div id="customerResults" class="mt-2"></div>
                        <div class="mr-error" data-error-for="customer_id"></div>
                        <div class="mr-help">
                            مشتری جدید است؟
                            <a href="<?= url('/admin/customers/create') ?>" target="_blank" rel="noopener">ابتدا او را ثبت کنید</a>.
                        </div>
                    </div>
                </div>
            </div>

            <div class="mr-card mb-4">
                <div class="mr-card__head"><h2 class="mr-card__title">۲. خدمات</h2></div>
                <div class="mr-card__body">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2" id="serviceList">
                        <?php foreach ($services as $s): ?>
                            <button type="button" class="pick" data-service="<?= (int)$s['id'] ?>"
                                    data-price="<?= (float)$s['price'] ?>" data-duration="<?= (int)$s['duration_minutes'] ?>"
                                    data-name="<?= e($s['name']) ?>">
                                <span class="flex justify-between items-center gap-2">
                                    <span>
                                        <span class="block font-medium"><?= e($s['name']) ?></span>
                                        <span class="block text-xs text-muted mr-num">⏱ <?= fa((int)$s['duration_minutes']) ?> دقیقه</span>
                                    </span>
                                    <span class="text-sm font-bold text-primary mr-num"><?= money($s['price'], false) ?></span>
                                </span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="mr-error" data-error-for="service_ids"></div>
                </div>
            </div>

            <div class="mr-card">
                <div class="mr-card__head"><h2 class="mr-card__title">۳. زمان</h2></div>
                <div class="mr-card__body">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="mr-field">
                            <label class="mr-label" for="branch_id">شعبه <span class="req">*</span></label>
                            <select class="mr-select" id="branch_id" name="branch_id" required>
                                <?php foreach ($branches as $b): ?>
                                    <option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mr-field">
                            <label class="mr-label" for="staff_id">متخصص <span class="req">*</span></label>
                            <select class="mr-select" id="staff_id" name="staff_id" required>
                                <?php foreach ($staff as $s): ?>
                                    <option value="<?= (int)$s['id'] ?>"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mr-field">
                            <label class="mr-label" for="date">تاریخ <span class="req">*</span></label>
                            <input class="mr-input mr-num" id="date" name="date" type="date" dir="ltr"
                                   min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="flex items-center justify-between mb-2">
                        <span class="mr-label mb-0">ساعت‌های آزاد</span>
                        <button class="mr-btn mr-btn--ghost mr-btn--sm" type="button" id="reloadSlots">بارگذاری مجدد</button>
                    </div>
                    <div id="slotsHost"></div>
                    <input type="hidden" name="time" id="time">
                    <div class="mr-error" data-error-for="time"></div>
                </div>
            </div>
        </div>

        <aside>
            <div class="mr-card mb-4 sticky" style="top:90px">
                <div class="mr-card__head"><h2 class="mr-card__title">خلاصه رزرو</h2></div>
                <div class="mr-card__body">
                    <div id="summary" class="text-sm text-muted mb-3">هنوز چیزی انتخاب نشده است.</div>
                    <div class="flex justify-between border-b py-2 text-sm">
                        <span class="text-muted">مدت زمان</span><span class="mr-num" id="sumDuration">—</span>
                    </div>
                    <div class="flex justify-between border-b py-2 text-sm">
                        <span class="text-muted">مبلغ کل</span><strong class="mr-num" id="sumPrice">—</strong>
                    </div>

                    <div class="mr-field mt-3">
                        <label class="mr-label" for="status">وضعیت اولیه</label>
                        <select class="mr-select" id="status" name="status">
                            <option value="CONFIRMED">تأیید شده</option>
                            <option value="PENDING">در انتظار تأیید</option>
                        </select>
                    </div>
                    <div class="mr-field">
                        <label class="mr-label" for="notes">یادداشت</label>
                        <textarea class="mr-textarea" id="notes" name="notes" rows="2"></textarea>
                    </div>

                    <button class="mr-btn mr-btn--primary mr-btn--block mr-btn--lg" type="submit" id="submitBtn">ثبت نوبت</button>
                </div>
            </div>
        </aside>
    </div>
</form>
<?php View::endSection();

View::section('scripts'); ?>
<script type="module">
import { api, busy, toast, toastError, escapeHtml, toFa, money, showLoading, showEmpty, showError, applyFieldErrors, debounce }
  from '<?= asset('js/core.js') ?>';

const form      = document.getElementById('apptForm');
const slotsHost = document.getElementById('slotsHost');
const state     = { services: [], time: null };

/* ------------------------------------------------ customer typeahead --- */
const results = document.getElementById('customerResults');
document.getElementById('customerSearch').addEventListener('input', debounce(async (e) => {
    const term = e.target.value.trim();
    if (term.length < 2) { results.innerHTML = ''; return; }
    showLoading(results, 2);
    try {
        const data = await api(`<?= url('/api/v1/customers') ?>?search=${encodeURIComponent(term)}`);
        if (!data.items.length) { showEmpty(results, 'مشتری یافت نشد', 'ابتدا مشتری را ثبت کنید.', '🔍'); return; }
        results.innerHTML = data.items.map((c) => `
            <button type="button" class="pick" data-id="${c.id}"
                    data-label="${escapeHtml(c.first_name + ' ' + c.last_name)}">
                <span class="block font-medium">${escapeHtml(c.first_name + ' ' + c.last_name)}</span>
                <span class="block text-xs text-muted mr-num" dir="ltr">${toFa(c.mobile)}</span>
            </button>`).join('');
    } catch (err) {
        showError(results, err.message);
    }
}, 300));

results.addEventListener('click', (e) => {
    const pick = e.target.closest('.pick');
    if (!pick) return;
    document.getElementById('customer_id').value = pick.dataset.id;
    document.getElementById('customerSearch').value = pick.dataset.label;
    results.innerHTML = `<div class="mr-alert mr-alert--success"><span>✅</span><span>مشتری انتخاب شد: ${escapeHtml(pick.dataset.label)}</span></div>`;
});

/* ------------------------------------------------------------ services --- */
document.getElementById('serviceList').addEventListener('click', (e) => {
    const pick = e.target.closest('.pick');
    if (!pick) return;
    pick.classList.toggle('is-selected');
    const id = Number(pick.dataset.service);
    const i  = state.services.findIndex((s) => s.id === id);
    if (i >= 0) state.services.splice(i, 1);
    else state.services.push({ id, name: pick.dataset.name, price: Number(pick.dataset.price), duration: Number(pick.dataset.duration) });
    renderSummary();
    loadSlots();
});

function renderSummary() {
    const total = state.services.reduce((a, s) => a + s.price, 0);
    const mins  = state.services.reduce((a, s) => a + s.duration, 0);
    document.getElementById('summary').textContent =
        state.services.length ? state.services.map((s) => s.name).join('، ') : 'هنوز چیزی انتخاب نشده است.';
    document.getElementById('sumDuration').textContent = mins ? `${toFa(mins)} دقیقه` : '—';
    document.getElementById('sumPrice').textContent    = mins ? money(total) : '—';
}

/* --------------------------------------------------------------- slots --- */
async function loadSlots() {
    const duration = state.services.reduce((a, s) => a + s.duration, 0);
    state.time = null;
    document.getElementById('time').value = '';
    if (!duration) { slotsHost.innerHTML = '<p class="text-sm text-muted">ابتدا خدمات را انتخاب کنید.</p>'; return; }

    showLoading(slotsHost, 3);
    try {
        const data = await api('<?= url('/api/v1/slots') ?>', {
            method: 'POST',
            body: {
                staff_id: document.getElementById('staff_id').value,
                branch_id: document.getElementById('branch_id').value,
                date: document.getElementById('date').value,
                duration,
            },
        });
        if (!data.slots.length) {
            showEmpty(slotsHost, 'زمان آزادی در این روز نیست', 'تاریخ یا متخصص دیگری را امتحان کنید.', '📅');
            return;
        }
        slotsHost.innerHTML = `<div class="text-xs text-muted mb-2">${escapeHtml(data.jalali)}</div>
            <div class="flex gap-2 flex-wrap">${data.slots.map((t) => `<button type="button" class="slot" data-time="${escapeHtml(t)}">${toFa(t)}</button>`).join('')}</div>`;
    } catch (err) {
        showError(slotsHost, err.message, loadSlots);
    }
}

slotsHost.addEventListener('click', (e) => {
    const slot = e.target.closest('.slot');
    if (!slot) return;
    slotsHost.querySelectorAll('.slot').forEach((s) => s.classList.remove('is-selected'));
    slot.classList.add('is-selected');
    state.time = slot.dataset.time;
    document.getElementById('time').value = state.time;
});

['staff_id', 'branch_id', 'date'].forEach((id) => document.getElementById(id).addEventListener('change', loadSlots));
document.getElementById('reloadSlots').addEventListener('click', loadSlots);

/* -------------------------------------------------------------- submit --- */
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!document.getElementById('customer_id').value) { toast('ابتدا مشتری را انتخاب کنید.', 'error'); return; }
    if (!state.services.length) { toast('حداقل یک خدمت انتخاب کنید.', 'error'); return; }
    if (!state.time) { toast('ساعت نوبت را انتخاب کنید.', 'error'); return; }

    const btn = document.getElementById('submitBtn');
    busy(btn, true, 'در حال ثبت…');
    try {
        const data = await api('<?= url('/api/v1/appointments') ?>', {
            method: 'POST',
            body: {
                customer_id: document.getElementById('customer_id').value,
                staff_id: document.getElementById('staff_id').value,
                branch_id: document.getElementById('branch_id').value,
                date: document.getElementById('date').value,
                time: state.time,
                service_ids: state.services.map((s) => s.id),
                notes: document.getElementById('notes').value.trim(),
            },
        });
        window.location.href = '<?= url('/admin/appointments/') ?>' + data.appointment.id;
    } catch (err) {
        busy(btn, false);
        applyFieldErrors(form, err.details || {});
        toastError(err);
        if (err.code === 'DOUBLE_BOOKING') loadSlots();
    }
});

renderSummary();
</script>
<?php View::endSection();
