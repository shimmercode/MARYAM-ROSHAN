<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array $categories @var array $services @var array $products @var array $staff @var array $branches @var array $methods @var float $taxRate @var int|null $appointment */
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb">پنل مدیریت / مالی</div>
        <h1>صندوق فروش</h1>
        <p>ثبت سریع فروش خدمات و محصولات</p>
    </div>
    <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/invoices') ?>">فاکتورها</a>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 pos-layout">
    <!-- ------------------------------------------------------- catalogue -->
    <div class="col-span-2">
        <div class="mr-card mb-4">
            <div class="mr-card__head">
                <div class="flex gap-1 flex-wrap" data-tabs>
                    <button class="mr-tab is-active" data-tab="services">خدمات</button>
                    <button class="mr-tab" data-tab="products">محصولات</button>
                </div>
                <input class="mr-input mr-input--sm" id="catalogSearch" placeholder="جستجو…" style="max-width:200px">
            </div>
            <div class="mr-card__body">
                <div data-tab-panel="services">
                    <div class="flex gap-2 flex-wrap mb-3" id="catFilters">
                        <button type="button" class="mr-btn mr-btn--primary mr-btn--sm" data-cat="">همه</button>
                        <?php foreach ($categories as $c): ?>
                            <button type="button" class="mr-btn mr-btn--ghost mr-btn--sm" data-cat="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2" id="serviceGrid">
                        <?php foreach ($services as $s): ?>
                            <button type="button" class="pick" data-kind="SERVICE" data-id="<?= (int)$s['id'] ?>"
                                    data-cat="<?= (int)($s['category_id'] ?? 0) ?>"
                                    data-name="<?= e($s['name']) ?>" data-price="<?= (float)$s['price'] ?>">
                                <span class="block font-medium text-sm"><?= e($s['name']) ?></span>
                                <span class="block text-xs text-primary mr-num"><?= money($s['price'], false) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div data-tab-panel="products" hidden>
                    <?php if ($products === []): ?>
                        <?= component('empty', ['icon' => '🧴', 'title' => 'محصول قابل فروشی ثبت نشده است', 'actionHref' => '/admin/inventory/products/create', 'actionLabel' => 'افزودن محصول']) ?>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-2" id="productGrid">
                            <?php foreach ($products as $p): ?>
                                <button type="button" class="pick" data-kind="PRODUCT" data-id="<?= (int)$p['id'] ?>"
                                        data-name="<?= e($p['name']) ?>" data-price="<?= (float)$p['price'] ?>">
                                    <span class="block font-medium text-sm"><?= e($p['name']) ?></span>
                                    <span class="block text-xs text-primary mr-num"><?= money($p['price'], false) ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ------------------------------------------------------------ cart -->
    <aside class="pos-cart">
        <div class="mr-card sticky" style="top:90px">
            <div class="mr-card__head"><h2 class="mr-card__title">سبد فروش</h2></div>
            <div class="mr-card__body">
                <div class="mr-field">
                    <label class="mr-label" for="customerSearch">مشتری <span class="req">*</span></label>
                    <input class="mr-input" id="customerSearch" autocomplete="off" placeholder="نام یا موبایل…">
                    <input type="hidden" id="customer_id">
                    <div id="customerResults" class="mt-2"></div>
                </div>

                <div class="mr-field">
                    <label class="mr-label" for="staff_id">متخصص</label>
                    <select class="mr-select" id="staff_id">
                        <option value="">—</option>
                        <?php foreach ($staff as $s): ?>
                            <option value="<?= (int)$s['id'] ?>"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="cartHost" class="mb-3"></div>

                <div class="mr-field">
                    <label class="mr-label" for="discount_code">کد تخفیف</label>
                    <input class="mr-input" id="discount_code" dir="ltr" placeholder="اختیاری">
                </div>
                <div class="mr-field">
                    <label class="mr-label" for="manual_discount">تخفیف دستی (تومان)</label>
                    <input class="mr-input mr-num" id="manual_discount" type="number" min="0" step="1000" value="0">
                </div>

                <div class="flex justify-between border-b py-2 text-sm">
                    <span class="text-muted">جمع اقلام</span><span class="mr-num" id="sumSubtotal">۰</span>
                </div>
                <div class="flex justify-between border-b py-2 text-sm">
                    <span class="text-muted">تخفیف</span><span class="mr-num" id="sumDiscount">۰</span>
                </div>
                <div class="flex justify-between border-b py-2 text-sm">
                    <span class="text-muted">مالیات (<?= fa($taxRate) ?>٪)</span><span class="mr-num" id="sumTax">۰</span>
                </div>
                <div class="flex justify-between py-3">
                    <strong>مبلغ قابل پرداخت</strong><strong class="mr-num" id="sumTotal">۰</strong>
                </div>

                <div class="mr-field">
                    <label class="mr-label" for="method">روش پرداخت</label>
                    <select class="mr-select" id="method">
                        <?php foreach ($methods as $k => $lbl): ?>
                            <option value="<?= e($k) ?>"><?= e($lbl) ?></option>
                        <?php endforeach; ?>
                        <option value="">بعداً پرداخت می‌شود</option>
                    </select>
                </div>

                <button class="mr-btn mr-btn--primary mr-btn--block mr-btn--lg" type="button" id="checkoutBtn">ثبت فاکتور</button>
            </div>
        </div>
    </aside>
</div>
<?php View::endSection();

View::section('scripts'); ?>
<script type="module">
import { api, busy, toast, toastError, escapeHtml, toFa, money, showLoading, showEmpty, showError, debounce }
  from '<?= asset('js/core.js') ?>';

const TAX_RATE = <?= (float)$taxRate ?>;
const cart = [];

/* ------------------------------------------------------------ catalogue */
document.getElementById('catFilters')?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-cat]');
    if (!btn) return;
    document.querySelectorAll('#catFilters [data-cat]').forEach((b) => {
        b.classList.remove('mr-btn--primary'); b.classList.add('mr-btn--ghost');
    });
    btn.classList.add('mr-btn--primary'); btn.classList.remove('mr-btn--ghost');
    const cat = btn.dataset.cat;
    document.querySelectorAll('#serviceGrid .pick').forEach((p) => {
        p.style.display = (!cat || p.dataset.cat === cat) ? '' : 'none';
    });
});

document.getElementById('catalogSearch').addEventListener('input', (e) => {
    const term = e.target.value.trim();
    document.querySelectorAll('.pick[data-kind]').forEach((p) => {
        p.style.display = (!term || p.dataset.name.includes(term)) ? '' : 'none';
    });
});

document.addEventListener('click', (e) => {
    const pick = e.target.closest('.pick[data-kind]');
    if (!pick) return;
    const key  = `${pick.dataset.kind}:${pick.dataset.id}`;
    const line = cart.find((l) => l.key === key);
    if (line) line.qty++;
    else cart.push({
        key, kind: pick.dataset.kind, id: Number(pick.dataset.id),
        name: pick.dataset.name, price: Number(pick.dataset.price), qty: 1,
    });
    renderCart();
});

/* ----------------------------------------------------------------- cart */
const cartHost = document.getElementById('cartHost');

function renderCart() {
    if (cart.length === 0) {
        showEmpty(cartHost, 'سبد خالی است', 'برای شروع، خدمت یا محصول را انتخاب کنید.', '🛒');
    } else {
        cartHost.innerHTML = cart.map((l, i) => `
            <div class="slot-row pos-ci">
                <div class="slot-row__body">
                    <strong class="text-sm">${escapeHtml(l.name)}</strong>
                    <small class="mr-num">${money(l.price)}</small>
                </div>
                <div class="flex items-center gap-1">
                    <button class="mr-iconbtn" type="button" data-dec="${i}" aria-label="کم کردن">−</button>
                    <span class="mr-num" style="min-width:24px;text-align:center">${toFa(l.qty)}</span>
                    <button class="mr-iconbtn" type="button" data-inc="${i}" aria-label="اضافه کردن">+</button>
                    <button class="mr-iconbtn" type="button" data-del="${i}" aria-label="حذف">×</button>
                </div>
            </div>`).join('');
    }
    recalc();
}

cartHost.addEventListener('click', (e) => {
    const inc = e.target.closest('[data-inc]');
    const dec = e.target.closest('[data-dec]');
    const del = e.target.closest('[data-del]');
    if (inc) cart[Number(inc.dataset.inc)].qty++;
    if (dec) { const l = cart[Number(dec.dataset.dec)]; l.qty = Math.max(1, l.qty - 1); }
    if (del) cart.splice(Number(del.dataset.del), 1);
    if (inc || dec || del) renderCart();
});

function recalc() {
    const subtotal = cart.reduce((a, l) => a + l.price * l.qty, 0);
    const discount = Math.min(subtotal, Number(document.getElementById('manual_discount').value || 0));
    const tax      = Math.round((subtotal - discount) * TAX_RATE / 100);
    document.getElementById('sumSubtotal').textContent = money(subtotal);
    document.getElementById('sumDiscount').textContent = money(discount);
    document.getElementById('sumTax').textContent      = money(tax);
    document.getElementById('sumTotal').textContent    = money(subtotal - discount + tax);
}
document.getElementById('manual_discount').addEventListener('input', recalc);

/* ---------------------------------------------------- customer selector */
const results = document.getElementById('customerResults');
document.getElementById('customerSearch').addEventListener('input', debounce(async (e) => {
    const term = e.target.value.trim();
    if (term.length < 2) { results.innerHTML = ''; return; }
    showLoading(results, 2);
    try {
        const data = await api(`<?= url('/api/v1/customers') ?>?search=${encodeURIComponent(term)}`);
        if (!data.items.length) { showEmpty(results, 'مشتری یافت نشد', '', '🔍'); return; }
        results.innerHTML = data.items.map((c) => `
            <button type="button" class="pick" data-id="${c.id}" data-label="${escapeHtml(c.first_name + ' ' + c.last_name)}">
                <span class="block text-sm">${escapeHtml(c.first_name + ' ' + c.last_name)}</span>
                <span class="block text-xs text-muted mr-num" dir="ltr">${toFa(c.mobile)}</span>
            </button>`).join('');
    } catch (err) { showError(results, err.message); }
}, 300));

results.addEventListener('click', (e) => {
    const pick = e.target.closest('.pick');
    if (!pick) return;
    document.getElementById('customer_id').value = pick.dataset.id;
    document.getElementById('customerSearch').value = pick.dataset.label;
    results.innerHTML = '';
});

/* ------------------------------------------------------------- checkout */
document.getElementById('checkoutBtn').addEventListener('click', async () => {
    const customerId = document.getElementById('customer_id').value;
    if (!customerId) { toast('ابتدا مشتری را انتخاب کنید.', 'error'); return; }
    if (cart.length === 0) { toast('سبد فروش خالی است.', 'error'); return; }

    const btn = document.getElementById('checkoutBtn');
    busy(btn, true, 'در حال صدور فاکتور…');
    const method = document.getElementById('method').value;
    const total  = cart.reduce((a, l) => a + l.price * l.qty, 0)
                 - Number(document.getElementById('manual_discount').value || 0);

    try {
        const data = await api('<?= url('/admin/invoices') ?>', {
            method: 'POST',
            body: {
                customer_id: customerId,
                staff_id: document.getElementById('staff_id').value || null,
                items: cart.map((l) => ({ type: l.kind, id: l.id, quantity: l.qty, unit_price: l.price })),
                discount_code: document.getElementById('discount_code').value.trim() || null,
                manual_discount: Number(document.getElementById('manual_discount').value || 0),
                payments: method ? [{ method, amount: Math.max(0, Math.round(total * (1 + TAX_RATE / 100))) }] : [],
            },
        });
        window.location.href = '<?= url('/admin/invoices/') ?>' + data.id;
    } catch (err) {
        busy(btn, false);
        toastError(err);
    }
});

renderCart();
</script>
<?php View::endSection();
