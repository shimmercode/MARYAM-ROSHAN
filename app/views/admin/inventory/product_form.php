<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array|null $product @var array $categories @var array $brands @var array $branches */
$isEdit = $product !== null;
$action = $isEdit ? '/admin/inventory/products/' . $product['id'] : '/admin/inventory/products';
$v = static fn (string $k, $d = '') => old($k, $product[$k] ?? $d);
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb"><a href="<?= url('/admin/inventory/products') ?>">کالاها</a> / <?= $isEdit ? 'ویرایش' : 'جدید' ?></div>
        <h1><?= $isEdit ? e($product['name']) : 'کالای جدید' ?></h1>
    </div>
    <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/inventory/products') ?>">بازگشت</a>
</div>

<form method="post" action="<?= url($action) ?>">
    <?= csrf_field() ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="col-span-2">
            <div class="mr-card">
                <div class="mr-card__head"><h2 class="mr-card__title">مشخصات کالا</h2></div>
                <div class="mr-card__body grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="mr-field mb-0 col-span-2">
                        <label class="mr-label" for="name">نام کالا <span class="req">*</span></label>
                        <input class="mr-input" id="name" name="name" required value="<?= e($v('name')) ?>">
                        <div class="mr-error" data-error-for="name"><?= e(error_for('name')) ?></div>
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="sku">کد کالا (SKU) <span class="req">*</span></label>
                        <input class="mr-input mr-num" id="sku" name="sku" dir="ltr" required value="<?= e($v('sku')) ?>">
                        <div class="mr-error" data-error-for="sku"><?= e(error_for('sku')) ?></div>
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="unit">واحد شمارش <span class="req">*</span></label>
                        <input class="mr-input" id="unit" name="unit" required value="<?= e($v('unit', 'عدد')) ?>" placeholder="عدد / گرم / میلی‌لیتر">
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="category_id">دسته‌بندی</label>
                        <select class="mr-select" id="category_id" name="category_id">
                            <option value="">—</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= (int)$v('category_id') === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="brand_id">برند</label>
                        <select class="mr-select" id="brand_id" name="brand_id">
                            <option value="">—</option>
                            <?php foreach ($brands as $b): ?>
                                <option value="<?= (int)$b['id'] ?>" <?= (int)$v('brand_id') === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="purchase_price">قیمت خرید <span class="req">*</span></label>
                        <input class="mr-input mr-num" id="purchase_price" name="purchase_price" type="number" min="0" step="1000" required value="<?= e($v('purchase_price', 0)) ?>">
                        <div class="mr-error" data-error-for="purchase_price"><?= e(error_for('purchase_price')) ?></div>
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="sale_price">قیمت فروش</label>
                        <input class="mr-input mr-num" id="sale_price" name="sale_price" type="number" min="0" step="1000" value="<?= e($v('sale_price', 0)) ?>">
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="reorder_level">نقطه سفارش مجدد</label>
                        <input class="mr-input mr-num" id="reorder_level" name="reorder_level" type="number" min="0" step="1" value="<?= e($v('reorder_level', 0)) ?>">
                        <div class="mr-help">وقتی موجودی به این عدد برسد هشدار داده می‌شود.</div>
                    </div>
                </div>
            </div>
        </div>

        <aside>
            <div class="mr-card mb-4">
                <div class="mr-card__head"><h2 class="mr-card__title">تنظیمات</h2></div>
                <div class="mr-card__body">
                    <div class="mr-field">
                        <label class="mr-label" for="status">وضعیت</label>
                        <select class="mr-select" id="status" name="status">
                            <option value="ACTIVE" <?= $v('status', 'ACTIVE') === 'ACTIVE' ? 'selected' : '' ?>>فعال</option>
                            <option value="INACTIVE" <?= $v('status') === 'INACTIVE' ? 'selected' : '' ?>>غیرفعال</option>
                        </select>
                    </div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_retail" value="1" <?= (int)$v('is_retail', 0) === 1 ? 'checked' : '' ?>>
                        <span class="text-sm">قابل فروش در صندوق (POS)</span>
                    </label>
                </div>
            </div>

            <?php if (!$isEdit): ?>
                <div class="mr-card mb-4">
                    <div class="mr-card__head"><h2 class="mr-card__title">موجودی اولیه</h2></div>
                    <div class="mr-card__body">
                        <div class="mr-field">
                            <label class="mr-label" for="branch_id">شعبه</label>
                            <select class="mr-select" id="branch_id" name="branch_id">
                                <?php foreach ($branches as $b): ?>
                                    <option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mr-field mb-0">
                            <label class="mr-label" for="initial_quantity">مقدار اولیه</label>
                            <input class="mr-input mr-num" id="initial_quantity" name="initial_quantity" type="number" min="0" step="0.01" value="0">
                            <div class="mr-help">به‌صورت تراکنش «ورود» در گردش انبار ثبت می‌شود.</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="flex gap-2">
                <button class="mr-btn mr-btn--primary flex-1" type="submit"><?= $isEdit ? 'ذخیره' : 'ثبت کالا' ?></button>
                <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/inventory/products') ?>">انصراف</a>
            </div>
        </aside>
    </div>
</form>
<?php View::endSection();
