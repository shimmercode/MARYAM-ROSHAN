<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/**
 * @var array|null $service @var array $categories @var array $branches @var array $staff
 * @var array $products @var array $selectedStaff @var array $selectedBranches @var array $recipe
 */
$isEdit = $service !== null;
$action = $isEdit ? '/admin/services/' . $service['id'] : '/admin/services';
$v = static fn (string $key, $default = '') => old($key, $service[$key] ?? $default);
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb"><a href="<?= url('/admin/services') ?>">خدمات</a> / <?= $isEdit ? 'ویرایش' : 'جدید' ?></div>
        <h1><?= $isEdit ? e($service['name']) : 'خدمت جدید' ?></h1>
    </div>
    <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/services') ?>">بازگشت</a>
</div>

<form method="post" action="<?= url($action) ?>" id="serviceForm">
    <?= csrf_field() ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="col-span-2">
            <div class="mr-card mb-4">
                <div class="mr-card__head"><h2 class="mr-card__title">مشخصات خدمت</h2></div>
                <div class="mr-card__body grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="mr-field mb-0 col-span-2">
                        <label class="mr-label" for="name">نام خدمت <span class="req">*</span></label>
                        <input class="mr-input" id="name" name="name" required value="<?= e($v('name')) ?>">
                        <div class="mr-error" data-error-for="name"><?= e(error_for('name')) ?></div>
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="category_id">دسته‌بندی <span class="req">*</span></label>
                        <select class="mr-select" id="category_id" name="category_id" required>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= (int)$v('category_id') === (int)$c['id'] ? 'selected' : '' ?>>
                                    <?= e($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="gender">مناسب برای</label>
                        <select class="mr-select" id="gender" name="gender">
                            <?php foreach (['ANY' => 'همه', 'FEMALE' => 'بانوان', 'MALE' => 'آقایان'] as $k => $lbl): ?>
                                <option value="<?= $k ?>" <?= $v('gender', 'ANY') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="price">قیمت (تومان) <span class="req">*</span></label>
                        <input class="mr-input mr-num" id="price" name="price" type="number" min="0" step="1000"
                               required value="<?= e($v('price', 0)) ?>">
                        <div class="mr-error" data-error-for="price"><?= e(error_for('price')) ?></div>
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="cost">بهای تمام‌شده</label>
                        <input class="mr-input mr-num" id="cost" name="cost" type="number" min="0" step="1000" value="<?= e($v('cost', 0)) ?>">
                        <div class="mr-help">برای محاسبه حاشیه سود استفاده می‌شود.</div>
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="duration_minutes">مدت زمان (دقیقه) <span class="req">*</span></label>
                        <input class="mr-input mr-num" id="duration_minutes" name="duration_minutes" type="number"
                               min="5" max="600" step="5" required value="<?= e($v('duration_minutes', 60)) ?>">
                        <div class="mr-error" data-error-for="duration_minutes"><?= e(error_for('duration_minutes')) ?></div>
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="buffer_minutes">زمان آماده‌سازی (دقیقه)</label>
                        <input class="mr-input mr-num" id="buffer_minutes" name="buffer_minutes" type="number"
                               min="0" max="120" step="5" value="<?= e($v('buffer_minutes', 10)) ?>">
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="commission_percent">درصد پورسانت پیش‌فرض</label>
                        <input class="mr-input mr-num" id="commission_percent" name="commission_percent" type="number"
                               min="0" max="100" step="1" value="<?= e($v('commission_value', 0)) ?>">
                    </div>
                    <div class="mr-field mb-0 col-span-2">
                        <label class="mr-label" for="short_description">توضیح کوتاه</label>
                        <input class="mr-input" id="short_description" name="short_description"
                               maxlength="300" value="<?= e($v('short_description')) ?>">
                        <div class="mr-help">در کارت خدمت در وب‌سایت نمایش داده می‌شود.</div>
                    </div>
                    <div class="mr-field mb-0 col-span-2">
                        <label class="mr-label" for="description">توضیح کامل</label>
                        <textarea class="mr-textarea" id="description" name="description" rows="4"><?= e($v('description')) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="mr-card mb-4">
                <div class="mr-card__head"><h2 class="mr-card__title">متخصصان ارائه‌دهنده</h2></div>
                <div class="mr-card__body flex gap-2 flex-wrap">
                    <?php foreach ($staff as $s): ?>
                        <label class="mr-badge" style="cursor:pointer">
                            <input type="checkbox" name="staff_ids[]" value="<?= (int)$s['id'] ?>"
                                <?= in_array((int)$s['id'], array_map('intval', $selectedStaff), true) ? 'checked' : '' ?>>
                            <?= e($s['first_name'] . ' ' . $s['last_name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mr-card">
                <div class="mr-card__head">
                    <h2 class="mr-card__title">دستور مصرف (کسر خودکار از انبار)</h2>
                    <button class="mr-btn mr-btn--soft mr-btn--sm" type="button" id="addRecipeRow">افزودن ماده</button>
                </div>
                <div class="mr-card__body">
                    <p class="mr-help mb-3">با تکمیل هر نوبت، این مقادیر به‌صورت خودکار از موجودی انبار کسر می‌شود.</p>
                    <div id="recipeRows">
                        <?php foreach ($recipe as $n => $r): ?>
                            <div class="flex gap-2 mb-2 recipe-row">
                                <select class="mr-select" name="recipe[<?= $n ?>][product_id]">
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?= (int)$p['id'] ?>" <?= (int)$r['product_id'] === (int)$p['id'] ? 'selected' : '' ?>>
                                            <?= e($p['name']) ?> (<?= e($p['unit']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input class="mr-input mr-num" name="recipe[<?= $n ?>][quantity]" type="number"
                                       min="0" step="0.001" value="<?= e($r['quantity']) ?>" style="max-width:120px">
                                <button class="mr-btn mr-btn--danger mr-btn--sm" type="button" data-remove-row>×</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($products === []): ?>
                        <p class="text-sm text-muted">ابتدا در بخش انبار محصول تعریف کنید.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <aside>
            <div class="mr-card mb-4">
                <div class="mr-card__head"><h2 class="mr-card__title">انتشار</h2></div>
                <div class="mr-card__body">
                    <div class="mr-field">
                        <label class="mr-label" for="status">وضعیت</label>
                        <select class="mr-select" id="status" name="status">
                            <option value="ACTIVE" <?= $v('status', 'ACTIVE') === 'ACTIVE' ? 'selected' : '' ?>>فعال</option>
                            <option value="INACTIVE" <?= $v('status') === 'INACTIVE' ? 'selected' : '' ?>>غیرفعال</option>
                        </select>
                    </div>
                    <label class="flex items-center gap-2 mb-2">
                        <input type="checkbox" name="online_booking" value="1" <?= (int)$v('online_booking', 1) === 1 ? 'checked' : '' ?>>
                        <span class="text-sm">قابل رزرو آنلاین</span>
                    </label>
                    <label class="flex items-center gap-2 mb-2">
                        <input type="checkbox" name="is_featured" value="1" <?= (int)$v('is_featured', 0) === 1 ? 'checked' : '' ?>>
                        <span class="text-sm">نمایش به‌عنوان خدمت ویژه</span>
                    </label>
                    <label class="flex items-center gap-2 mb-3">
                        <input type="checkbox" name="requires_deposit" value="1" id="requiresDeposit"
                            <?= (int)$v('requires_deposit', 0) === 1 ? 'checked' : '' ?>>
                        <span class="text-sm">نیازمند بیعانه</span>
                    </label>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="deposit_amount">مبلغ بیعانه</label>
                        <input class="mr-input mr-num" id="deposit_amount" name="deposit_amount" type="number"
                               min="0" step="1000" value="<?= e($v('deposit_amount', 0)) ?>">
                    </div>
                </div>
            </div>

            <div class="mr-card mb-4">
                <div class="mr-card__head"><h2 class="mr-card__title">شعبه‌ها</h2></div>
                <div class="mr-card__body flex gap-2 flex-wrap">
                    <?php foreach ($branches as $b): ?>
                        <label class="mr-badge" style="cursor:pointer">
                            <input type="checkbox" name="branch_ids[]" value="<?= (int)$b['id'] ?>"
                                <?= $selectedBranches === [] || in_array((int)$b['id'], array_map('intval', $selectedBranches), true) ? 'checked' : '' ?>>
                            <?= e($b['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mr-card mb-4">
                <div class="mr-card__head"><h2 class="mr-card__title">سئو</h2></div>
                <div class="mr-card__body">
                    <div class="mr-field">
                        <label class="mr-label" for="slug">نشانی اینترنتی (slug)</label>
                        <input class="mr-input" id="slug" name="slug" dir="ltr" value="<?= e($v('slug')) ?>">
                        <div class="mr-help">خالی بگذارید تا خودکار ساخته شود.</div>
                    </div>
                    <div class="mr-field">
                        <label class="mr-label" for="meta_title">عنوان متا</label>
                        <input class="mr-input" id="meta_title" name="meta_title" maxlength="160" value="<?= e($v('meta_title')) ?>">
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="meta_description">توضیح متا</label>
                        <textarea class="mr-textarea" id="meta_description" name="meta_description" rows="2"
                                  maxlength="300"><?= e($v('meta_description')) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="flex gap-2">
                <button class="mr-btn mr-btn--primary flex-1" type="submit" id="saveBtn">
                    <?= $isEdit ? 'ذخیره تغییرات' : 'ثبت خدمت' ?>
                </button>
                <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/services') ?>">انصراف</a>
            </div>
        </aside>
    </div>
</form>
<?php View::endSection();

View::section('scripts'); ?>
<script type="module">
import { busy } from '<?= asset('js/core.js') ?>';

const products = <?= json_encode(array_map(static fn ($p) => [
    'id' => (int)$p['id'], 'name' => $p['name'] . ' (' . $p['unit'] . ')',
], $products), JSON_UNESCAPED_UNICODE) ?>;

let rowIndex = <?= count($recipe) ?>;
document.getElementById('addRecipeRow').addEventListener('click', () => {
    if (products.length === 0) return;
    const row = document.createElement('div');
    row.className = 'flex gap-2 mb-2 recipe-row';
    row.innerHTML = `
        <select class="mr-select" name="recipe[${rowIndex}][product_id]">
            ${products.map((p) => `<option value="${p.id}">${p.name}</option>`).join('')}
        </select>
        <input class="mr-input mr-num" name="recipe[${rowIndex}][quantity]" type="number" min="0" step="0.001" value="1" style="max-width:120px">
        <button class="mr-btn mr-btn--danger mr-btn--sm" type="button" data-remove-row>×</button>`;
    document.getElementById('recipeRows').appendChild(row);
    rowIndex++;
});

document.getElementById('recipeRows').addEventListener('click', (e) => {
    if (e.target.closest('[data-remove-row]')) e.target.closest('.recipe-row').remove();
});

document.getElementById('serviceForm').addEventListener('submit', () => {
    busy(document.getElementById('saveBtn'), true, 'در حال ذخیره…');
});
</script>
<?php View::endSection();
