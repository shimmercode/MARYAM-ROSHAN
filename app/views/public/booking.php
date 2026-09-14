<?php
use App\Core\View;
View::extend('public');
View::section('content');
/** @var array $categories @var array $services @var array $branches @var array $rules @var string $maxDate @var string $preselect */
?>
<section class="hero" style="padding-block:var(--mr-s6)">
    <div class="site-container text-center">
        <h1 class="hero__title" style="font-size:clamp(1.5rem,3.2vw,2.2rem)">رزرو آنلاین نوبت</h1>
        <p class="hero__lead mx-auto">در چهار گام ساده نوبت خود را ثبت کنید.</p>
    </div>
</section>

<section class="section" style="padding-block:var(--mr-s6)">
    <div class="site-container" style="max-width:900px">

        <div class="wizard__steps" id="wizardSteps">
            <div class="wizard__step is-active" data-step="1"><b>۱</b> انتخاب خدمات</div>
            <div class="wizard__step" data-step="2"><b>۲</b> تاریخ و شعبه</div>
            <div class="wizard__step" data-step="3"><b>۳</b> متخصص و ساعت</div>
            <div class="wizard__step" data-step="4"><b>۴</b> اطلاعات شما</div>
        </div>

        <form id="bookingForm" method="post" action="<?= url('/booking') ?>" novalidate>
            <?= csrf_field() ?>

            <!-- ------------------------------------------------ step 1 -->
            <div class="mr-card" data-panel="1">
                <div class="mr-card__head"><h2 class="mr-card__title">چه خدماتی می‌خواهید؟</h2></div>
                <div class="mr-card__body">
                    <?php if ($services === []): ?>
                        <?= component('empty', ['icon' => '✂️', 'title' => 'خدمتی برای رزرو آنلاین فعال نیست', 'text' => 'لطفاً تلفنی تماس بگیرید.']) ?>
                    <?php else: ?>
                        <div class="flex gap-2 flex-wrap mb-4" id="catFilters">
                            <button type="button" class="mr-btn mr-btn--primary mr-btn--sm" data-cat="">همه</button>
                            <?php foreach ($categories as $c): ?>
                                <button type="button" class="mr-btn mr-btn--ghost mr-btn--sm" data-cat="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></button>
                            <?php endforeach; ?>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2" id="serviceList">
                            <?php foreach ($services as $s): ?>
                                <button type="button" class="pick" data-service="<?= (int)$s['id'] ?>"
                                        data-cat="<?= (int)$s['category_id'] ?>"
                                        data-price="<?= (float)$s['price'] ?>"
                                        data-duration="<?= (int)$s['duration_minutes'] ?>"
                                        data-name="<?= e($s['name']) ?>"
                                        data-slug="<?= e($s['slug']) ?>">
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
                    <?php endif; ?>
                </div>
                <div class="mr-card__foot flex justify-between items-center">
                    <span class="text-sm text-muted" id="summary1">خدمتی انتخاب نشده است</span>
                    <button type="button" class="mr-btn mr-btn--primary" data-next="2" disabled>ادامه</button>
                </div>
            </div>

            <!-- ------------------------------------------------ step 2 -->
            <div class="mr-card hidden" data-panel="2">
                <div class="mr-card__head"><h2 class="mr-card__title">شعبه و تاریخ</h2></div>
                <div class="mr-card__body">
                    <div class="mr-field">
                        <label class="mr-label" for="branch_id">شعبه <span class="req">*</span></label>
                        <select class="mr-select" id="branch_id" name="branch_id" required>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?> — <?= e($b['address']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mr-field">
                        <label class="mr-label" for="date">تاریخ مراجعه <span class="req">*</span></label>
                        <input class="mr-input mr-num" id="date" name="date" type="date" dir="ltr" required
                               min="<?= date('Y-m-d') ?>" max="<?= e($maxDate) ?>" value="<?= date('Y-m-d') ?>">
                        <div class="mr-help" id="jalaliHint"></div>
                    </div>
                </div>
                <div class="mr-card__foot flex justify-between">
                    <button type="button" class="mr-btn mr-btn--ghost" data-prev="1">بازگشت</button>
                    <button type="button" class="mr-btn mr-btn--primary" data-next="3">جستجوی زمان آزاد</button>
                </div>
            </div>

            <!-- ------------------------------------------------ step 3 -->
            <div class="mr-card hidden" data-panel="3">
                <div class="mr-card__head"><h2 class="mr-card__title">متخصص و ساعت</h2></div>
                <div class="mr-card__body" id="slotsHost">
                    <!-- loading / empty / error / data states injected here -->
                </div>
                <div class="mr-card__foot flex justify-between">
                    <button type="button" class="mr-btn mr-btn--ghost" data-prev="2">بازگشت</button>
                    <button type="button" class="mr-btn mr-btn--primary" data-next="4" disabled>ادامه</button>
                </div>
            </div>

            <!-- ------------------------------------------------ step 4 -->
            <div class="mr-card hidden" data-panel="4">
                <div class="mr-card__head"><h2 class="mr-card__title">اطلاعات تماس شما</h2></div>
                <div class="mr-card__body">
                    <div class="mr-alert mr-alert--info" id="finalSummary"></div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="mr-field">
                            <label class="mr-label" for="first_name">نام <span class="req">*</span></label>
                            <input class="mr-input" id="first_name" name="first_name" required>
                            <div class="mr-error" data-error-for="first_name"></div>
                        </div>
                        <div class="mr-field">
                            <label class="mr-label" for="last_name">نام خانوادگی <span class="req">*</span></label>
                            <input class="mr-input" id="last_name" name="last_name" required>
                            <div class="mr-error" data-error-for="last_name"></div>
                        </div>
                    </div>
                    <div class="mr-field">
                        <label class="mr-label" for="mobile">موبایل <span class="req">*</span></label>
                        <input class="mr-input mr-num" id="mobile" name="mobile" dir="ltr" inputmode="numeric"
                               placeholder="09121234567" required>
                        <div class="mr-help">کد پیگیری و یادآوری نوبت به این شماره ارسال می‌شود.</div>
                        <div class="mr-error" data-error-for="mobile"></div>
                    </div>
                    <div class="mr-field">
                        <label class="mr-label" for="notes">توضیحات (اختیاری)</label>
                        <textarea class="mr-textarea" id="notes" name="notes" rows="3"></textarea>
                    </div>

                    <input type="hidden" name="staff_id" id="staff_id">
                    <input type="hidden" name="time" id="time">
                </div>
                <div class="mr-card__foot flex justify-between">
                    <button type="button" class="mr-btn mr-btn--ghost" data-prev="3">بازگشت</button>
                    <button type="submit" class="mr-btn mr-btn--primary mr-btn--lg" id="bookingSubmit">ثبت نهایی نوبت</button>
                </div>
            </div>
        </form>
    </div>
</section>
<?php View::endSection();

View::section('scripts'); ?>
<script type="module">
import { api, busy, toast, toastError, escapeHtml, toFa, money, showLoading, showEmpty, showError, applyFieldErrors }
  from '<?= asset('js/core.js') ?>';

const form      = document.getElementById('bookingForm');
const slotsHost = document.getElementById('slotsHost');
const state     = { services: [], staffId: null, time: null, staffName: '' };

/* ---------------------------------------------------------- step 1 */
document.getElementById('catFilters')?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-cat]');
    if (!btn) return;
    document.querySelectorAll('#catFilters [data-cat]').forEach((b) => {
        b.classList.remove('mr-btn--primary'); b.classList.add('mr-btn--ghost');
    });
    btn.classList.add('mr-btn--primary'); btn.classList.remove('mr-btn--ghost');
    const cat = btn.dataset.cat;
    document.querySelectorAll('#serviceList .pick').forEach((p) => {
        p.style.display = (!cat || p.dataset.cat === cat) ? '' : 'none';
    });
});

document.getElementById('serviceList')?.addEventListener('click', (e) => {
    const pick = e.target.closest('.pick');
    if (!pick) return;
    pick.classList.toggle('is-selected');
    const id = Number(pick.dataset.service);
    const i = state.services.findIndex((s) => s.id === id);
    if (i >= 0) state.services.splice(i, 1);
    else state.services.push({ id, name: pick.dataset.name, price: Number(pick.dataset.price), duration: Number(pick.dataset.duration) });
    renderSummary1();
});

function renderSummary1() {
    const total = state.services.reduce((a, s) => a + s.price, 0);
    const mins  = state.services.reduce((a, s) => a + s.duration, 0);
    const el = document.getElementById('summary1');
    el.textContent = state.services.length
        ? `${toFa(state.services.length)} خدمت · ${toFa(mins)} دقیقه · ${money(total)}`
        : 'خدمتی انتخاب نشده است';
    document.querySelector('[data-next="2"]').disabled = state.services.length === 0;
}

// Preselect a service coming from /services/{slug}
const preselect = <?= json_encode($preselect, JSON_UNESCAPED_UNICODE) ?>;
if (preselect) {
    document.querySelector(`.pick[data-slug="${CSS.escape(preselect)}"]`)?.click();
}

/* ---------------------------------------------------------- navigation */
function goto(step) {
    document.querySelectorAll('[data-panel]').forEach((p) => p.classList.toggle('hidden', p.dataset.panel !== String(step)));
    document.querySelectorAll('.wizard__step').forEach((s) => {
        const n = Number(s.dataset.step);
        s.classList.toggle('is-active', n === step);
        s.classList.toggle('is-done', n < step);
    });
    window.scrollTo({ top: document.getElementById('wizardSteps').offsetTop - 90, behavior: 'smooth' });
}

document.addEventListener('click', (e) => {
    const next = e.target.closest('[data-next]');
    const prev = e.target.closest('[data-prev]');
    if (prev) goto(Number(prev.dataset.prev));
    if (next) {
        const step = Number(next.dataset.next);
        if (step === 3) { loadSlots(); }
        if (step === 4) { renderFinalSummary(); }
        goto(step);
    }
});

/* ---------------------------------------------------------- step 2 → 3 */
const dateInput = document.getElementById('date');
dateInput?.addEventListener('change', () => { state.staffId = null; state.time = null; });

async function loadSlots() {
    showLoading(slotsHost, 4);
    state.staffId = null; state.time = null;
    document.querySelector('[data-next="4"]').disabled = true;

    try {
        const data = await api('<?= url('/booking/slots') ?>', {
            method: 'POST',
            body: {
                branch_id: document.getElementById('branch_id').value,
                date: dateInput.value,
                service_ids: state.services.map((s) => s.id),
            },
        });

        if (!data.staff || data.staff.length === 0) {
            showEmpty(slotsHost, 'زمان آزادی در این تاریخ نیست',
                'تاریخ دیگری را انتخاب کنید یا با سالن تماس بگیرید.', '📅');
            return;
        }

        slotsHost.innerHTML = `<div class="text-sm text-muted mb-4">${escapeHtml(data.jalali)}</div>` +
            data.staff.map((row) => `
              <div class="mb-5">
                <div class="flex items-center gap-3 mb-2">
                  <span class="mr-avatar">${escapeHtml((row.staff.name || '؟').slice(0, 1))}</span>
                  <span>
                    <span class="block font-medium">${escapeHtml(row.staff.name)}</span>
                    <span class="block text-xs text-muted">${escapeHtml(row.staff.job_title || '')}</span>
                  </span>
                </div>
                <div class="flex gap-2 flex-wrap">
                  ${row.slots.map((t) => `<button type="button" class="slot"
                      data-staff="${row.staff.id}" data-time="${escapeHtml(t)}"
                      data-staff-name="${escapeHtml(row.staff.name)}">${toFa(t)}</button>`).join('')}
                </div>
              </div>`).join('');
    } catch (err) {
        showError(slotsHost, err.message, loadSlots);
    }
}

slotsHost.addEventListener('click', (e) => {
    const slot = e.target.closest('.slot');
    if (!slot) return;
    slotsHost.querySelectorAll('.slot').forEach((s) => s.classList.remove('is-selected'));
    slot.classList.add('is-selected');
    state.staffId  = slot.dataset.staff;
    state.time     = slot.dataset.time;
    state.staffName = slot.dataset.staffName;
    document.getElementById('staff_id').value = state.staffId;
    document.getElementById('time').value = state.time;
    document.querySelector('[data-next="4"]').disabled = false;
});

/* ---------------------------------------------------------- step 4 */
function renderFinalSummary() {
    const total = state.services.reduce((a, s) => a + s.price, 0);
    document.getElementById('finalSummary').innerHTML =
        `<span>📋</span><span>${escapeHtml(state.services.map((s) => s.name).join('، '))} — ` +
        `${escapeHtml(state.staffName)} — ساعت ${toFa(state.time || '')} — ${money(total)}</span>`;
}

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!state.staffId || !state.time) { toast('لطفاً ساعت نوبت را انتخاب کنید.', 'error'); return; }

    const btn = document.getElementById('bookingSubmit');
    busy(btn, true, 'در حال ثبت نوبت…');
    try {
        const data = await api('<?= url('/booking') ?>', {
            method: 'POST',
            body: {
                first_name: form.first_name.value.trim(),
                last_name: form.last_name.value.trim(),
                mobile: form.mobile.value.trim(),
                branch_id: document.getElementById('branch_id').value,
                staff_id: state.staffId,
                date: dateInput.value,
                time: state.time,
                service_ids: state.services.map((s) => s.id),
                notes: form.notes.value.trim(),
            },
        });
        window.location.href = data.redirect;
    } catch (err) {
        busy(btn, false);
        applyFieldErrors(form, err.details || {});
        toastError(err);
        if (err.code === 'DOUBLE_BOOKING') { goto(3); loadSlots(); }
    }
});
</script>
<?php View::endSection();
