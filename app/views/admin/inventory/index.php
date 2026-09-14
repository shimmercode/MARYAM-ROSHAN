<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array $levels @var array $lowStock @var array $branches @var array $filters @var array $totals */
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb">پنل مدیریت / انبار</div>
        <h1>موجودی انبار</h1>
        <p>وضعیت لحظه‌ای کالاها به تفکیک شعبه</p>
    </div>
    <div class="flex gap-2">
        <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/inventory/transactions') ?>">گردش انبار</a>
        <a class="mr-btn mr-btn--primary" href="<?= url('/admin/inventory/products') ?>">مدیریت کالاها</a>
    </div>
</div>

<section class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">
    <?= component('kpi', ['label' => 'ارزش کل انبار', 'icon' => '🏷', 'value' => money($totals['value'])]) ?>
    <?= component('kpi', ['label' => 'کالاهای فعال', 'icon' => '📦', 'value' => fa((int)$totals['products'])]) ?>
    <?= component('kpi', ['label' => 'کم‌موجود', 'icon' => '⚠️', 'value' => fa(count($lowStock)), 'hint' => 'زیر نقطه سفارش']) ?>
</section>

<?php if ($lowStock !== []): ?>
    <div class="mr-alert mr-alert--warning mb-4">
        <strong>هشدار موجودی:</strong>
        <?php foreach (array_slice($lowStock, 0, 6) as $l): ?>
            <span class="mr-badge mr-badge--warning"><?= e($l['name']) ?> (<?= fa((float)$l['quantity']) ?>)</span>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form class="mr-card mb-4" method="get" action="<?= url('/admin/inventory') ?>">
    <div class="mr-card__body grid grid-cols-1 md:grid-cols-4 gap-3">
        <div class="mr-field mb-0 col-span-2">
            <label class="mr-label" for="search">جستجوی کالا</label>
            <input class="mr-input" id="search" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="نام یا کد کالا">
        </div>
        <div class="mr-field mb-0">
            <label class="mr-label" for="branch_id">شعبه</label>
            <select class="mr-select" id="branch_id" name="branch_id">
                <option value="">همه</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= (int)$b['id'] ?>" <?= (int)($filters['branch_id'] ?? 0) === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-end"><button class="mr-btn mr-btn--primary w-full" type="submit">اعمال</button></div>
    </div>
</form>

<div class="mr-card">
    <div class="mr-card__body">
        <?php if ($levels === []): ?>
            <?= component('empty', ['icon' => '📦', 'title' => 'کالایی یافت نشد', 'actionHref' => '/admin/inventory/products/create', 'actionLabel' => 'افزودن کالا']) ?>
        <?php else: ?>
            <div class="mr-table__wrap">
                <table class="mr-table">
                    <thead>
                    <tr><th>کالا</th><th>کد</th><th>شعبه</th><th>موجودی</th><th>نقطه سفارش</th><th>ارزش</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($levels as $row): $low = (float)$row['quantity'] <= (float)$row['reorder_level']; ?>
                        <tr>
                            <td><?= e($row['name']) ?></td>
                            <td class="mr-num text-xs"><?= e($row['sku']) ?></td>
                            <td><?= e($row['branch_name'] ?? '—') ?></td>
                            <td class="mr-num <?= $low ? 'text-danger font-bold' : '' ?>">
                                <?= fa((float)$row['quantity']) ?> <?= e($row['unit']) ?>
                            </td>
                            <td class="mr-num text-xs"><?= fa((float)$row['reorder_level']) ?></td>
                            <td class="mr-num"><?= money($row['stock_value'], false) ?></td>
                            <td>
                                <button class="mr-btn mr-btn--soft mr-btn--sm" type="button"
                                        data-adjust="<?= (int)$row['id'] ?>"
                                        data-name="<?= e($row['name']) ?>"
                                        data-branch="<?= (int)($row['branch_id'] ?? 0) ?>">اصلاح موجودی</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="mr-modal" id="adjustModal" hidden>
    <div class="mr-modal__panel">
        <form method="post" action="<?= url('/admin/inventory/adjust') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" id="adjProduct">
            <div class="mr-card__head">
                <h2 class="mr-card__title">اصلاح موجودی — <span id="adjName"></span></h2>
                <button class="mr-iconbtn" type="button" data-modal-close="adjustModal">✕</button>
            </div>
            <div class="mr-card__body">
                <div class="mr-field">
                    <label class="mr-label" for="adjBranch">شعبه</label>
                    <select class="mr-select" id="adjBranch" name="branch_id" required>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mr-field">
                    <label class="mr-label" for="adjType">نوع تراکنش</label>
                    <select class="mr-select" id="adjType" name="type" required>
                        <option value="IN">ورود به انبار</option>
                        <option value="OUT">خروج از انبار</option>
                        <option value="ADJUST">اصلاح شمارش</option>
                        <option value="WASTE">ضایعات</option>
                    </select>
                </div>
                <div class="mr-field">
                    <label class="mr-label" for="adjQty">مقدار</label>
                    <input class="mr-input mr-num" id="adjQty" name="quantity" type="number" step="0.01" min="0.01" required>
                </div>
                <div class="mr-field mb-0">
                    <label class="mr-label" for="adjNote">توضیح</label>
                    <input class="mr-input" id="adjNote" name="note" maxlength="255" placeholder="علت تغییر موجودی">
                </div>
            </div>
            <div class="mr-card__foot flex gap-2 justify-end">
                <button class="mr-btn mr-btn--ghost" type="button" data-modal-close="adjustModal">انصراف</button>
                <button class="mr-btn mr-btn--primary" type="submit">ثبت</button>
            </div>
        </form>
    </div>
</div>
<?php View::endSection();

View::section('scripts'); ?>
<script type="module">
import { openModal } from '<?= asset('js/core.js') ?>';
document.querySelectorAll('[data-adjust]').forEach((btn) => {
    btn.addEventListener('click', () => {
        document.getElementById('adjProduct').value = btn.dataset.adjust;
        document.getElementById('adjName').textContent = btn.dataset.name;
        const branch = btn.dataset.branch;
        if (branch && branch !== '0') document.getElementById('adjBranch').value = branch;
        openModal('adjustModal');
    });
});
</script>
<?php View::endSection();
