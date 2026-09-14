<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array $transactions @var array $products @var int|null $productId */
$types = ['IN' => 'ورود', 'OUT' => 'خروج', 'ADJUST' => 'اصلاح', 'WASTE' => 'ضایعات', 'TRANSFER' => 'انتقال', 'CONSUME' => 'مصرف خدمت'];
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb"><a href="<?= url('/admin/inventory') ?>">انبار</a> / گردش</div>
        <h1>گردش انبار</h1>
        <p>تاریخچه ورود، خروج و مصرف کالاها</p>
    </div>
    <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/inventory/transactions?export=csv') ?>">خروجی CSV</a>
</div>

<form class="mr-card mb-4" method="get" action="<?= url('/admin/inventory/transactions') ?>">
    <div class="mr-card__body flex gap-3">
        <select class="mr-select flex-1" name="product_id" aria-label="کالا">
            <option value="">همه کالاها</option>
            <?php foreach ($products as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= (int)$productId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="mr-btn mr-btn--primary" type="submit">فیلتر</button>
    </div>
</form>

<div class="mr-card">
    <div class="mr-card__body">
        <?php if ($transactions === []): ?>
            <?= component('empty', ['icon' => '🔄', 'title' => 'تراکنشی ثبت نشده است']) ?>
        <?php else: ?>
            <div class="mr-table__wrap">
                <table class="mr-table">
                    <thead><tr><th>تاریخ</th><th>کالا</th><th>نوع</th><th>مقدار</th><th>مانده</th><th>شعبه</th><th>کاربر</th><th>توضیح</th></tr></thead>
                    <tbody>
                    <?php foreach ($transactions as $t): $out = in_array($t['type'], ['OUT', 'WASTE', 'CONSUME'], true); ?>
                        <tr>
                            <td class="text-xs"><?= jdate($t['created_at'], 'j F — H:i') ?></td>
                            <td><?= e($t['product_name'] ?? '—') ?></td>
                            <td><span class="mr-badge mr-badge--<?= $out ? 'danger' : 'success' ?>"><?= e($types[$t['type']] ?? $t['type']) ?></span></td>
                            <td class="mr-num"><?= $out ? '−' : '+' ?><?= fa((float)$t['quantity']) ?></td>
                            <td class="mr-num"><?= fa((float)$t['balance_after']) ?></td>
                            <td class="text-xs"><?= e($t['branch_name'] ?? '—') ?></td>
                            <td class="text-xs"><?= e($t['user_name'] ?? 'سیستم') ?></td>
                            <td class="text-xs text-muted"><?= e($t['note'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php View::endSection();
