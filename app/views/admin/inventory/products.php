<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array $products @var int $page @var int $lastPage @var int $total @var string $search */
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb"><a href="<?= url('/admin/inventory') ?>">انبار</a> / کالاها</div>
        <h1>کالاها</h1>
        <p><span class="mr-num"><?= fa($total) ?></span> قلم کالا</p>
    </div>
    <a class="mr-btn mr-btn--primary" href="<?= url('/admin/inventory/products/create') ?>">کالای جدید</a>
</div>

<form class="mr-card mb-4" method="get" action="<?= url('/admin/inventory/products') ?>">
    <div class="mr-card__body flex gap-3">
        <input class="mr-input flex-1" name="search" value="<?= e($search) ?>" placeholder="نام یا کد کالا" aria-label="جستجو">
        <button class="mr-btn mr-btn--primary" type="submit">جستجو</button>
    </div>
</form>

<div class="mr-card">
    <div class="mr-card__body">
        <?php if ($products === []): ?>
            <?= component('empty', ['icon' => '🧴', 'title' => 'کالایی ثبت نشده است', 'text' => 'محصولات مصرفی و فروشی سالن را اینجا تعریف کنید.', 'actionHref' => '/admin/inventory/products/create', 'actionLabel' => 'افزودن کالا']) ?>
        <?php else: ?>
            <div class="mr-table__wrap">
                <table class="mr-table">
                    <thead>
                    <tr><th>نام</th><th>کد</th><th>دسته</th><th>موجودی کل</th><th>خرید</th><th>فروش</th><th>فروش مستقیم</th><th>وضعیت</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td><?= e($p['name']) ?></td>
                            <td class="mr-num text-xs"><?= e($p['sku']) ?></td>
                            <td><?= e($p['category_name'] ?? '—') ?></td>
                            <td class="mr-num"><?= fa((float)$p['total_quantity']) ?> <?= e($p['unit']) ?></td>
                            <td class="mr-num"><?= money($p['purchase_price'], false) ?></td>
                            <td class="mr-num"><?= money($p['sale_price'], false) ?></td>
                            <td><?= (int)$p['is_retail'] === 1 ? '✓' : '—' ?></td>
                            <td><?= component('status_badge', ['status' => $p['status']]) ?></td>
                            <td><a class="mr-btn mr-btn--ghost mr-btn--sm" href="<?= url('/admin/inventory/products/' . $p['id'] . '/edit') ?>">ویرایش</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= component('pagination', ['page' => $page, 'lastPage' => $lastPage, 'query' => ['search' => $search]]) ?>
        <?php endif; ?>
    </div>
</div>
<?php View::endSection();
