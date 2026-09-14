<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array|null $customer @var array $branches @var array $staff @var array $tags @var array $selectedTags */
$isEdit = $customer !== null;
$action = $isEdit ? '/admin/customers/' . $customer['id'] : '/admin/customers';
$v = static fn (string $key, $default = '') => old($key, $customer[$key] ?? $default);
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb">
            <a href="<?= url('/admin/customers') ?>">مشتریان</a> / <?= $isEdit ? 'ویرایش' : 'ثبت جدید' ?>
        </div>
        <h1><?= $isEdit ? e($customer['first_name'] . ' ' . $customer['last_name']) : 'مشتری جدید' ?></h1>
    </div>
    <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/customers') ?>">بازگشت</a>
</div>

<form method="post" action="<?= url($action) ?>" id="customerForm">
    <?= csrf_field() ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="col-span-2">
            <div class="mr-card mb-4">
                <div class="mr-card__head"><h2 class="mr-card__title">اطلاعات پایه</h2></div>
                <div class="mr-card__body grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="first_name">نام <span class="req">*</span></label>
                        <input class="mr-input" id="first_name" name="first_name" required value="<?= e($v('first_name')) ?>">
                        <div class="mr-error" data-error-for="first_name"><?= e(error_for('first_name')) ?></div>
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="last_name">نام خانوادگی <span class="req">*</span></label>
                        <input class="mr-input" id="last_name" name="last_name" required value="<?= e($v('last_name')) ?>">
                        <div class="mr-error" data-error-for="last_name"><?= e(error_for('last_name')) ?></div>
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="mobile">موبایل <span class="req">*</span></label>
                        <input class="mr-input mr-num" id="mobile" name="mobile" dir="ltr" inputmode="numeric"
                               placeholder="09121234567" required value="<?= e($v('mobile')) ?>">
                        <div class="mr-error" data-error-for="mobile"><?= e(error_for('mobile')) ?></div>
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="email">ایمیل</label>
                        <input class="mr-input" id="email" name="email" type="email" dir="ltr" value="<?= e($v('email')) ?>">
                        <div class="mr-error" data-error-for="email"><?= e(error_for('email')) ?></div>
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="gender">جنسیت</label>
                        <select class="mr-select" id="gender" name="gender">
                            <?php foreach (['FEMALE' => 'خانم', 'MALE' => 'آقا', 'OTHER' => 'سایر'] as $k => $lbl): ?>
                                <option value="<?= $k ?>" <?= $v('gender', 'FEMALE') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="birth_date">تاریخ تولد</label>
                        <input class="mr-input mr-num" id="birth_date" name="birth_date" type="date" dir="ltr" value="<?= e($v('birth_date')) ?>">
                        <div class="mr-error" data-error-for="birth_date"><?= e(error_for('birth_date')) ?></div>
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="national_code">کد ملی</label>
                        <input class="mr-input mr-num" id="national_code" name="national_code" dir="ltr" value="<?= e($v('national_code')) ?>">
                        <div class="mr-error" data-error-for="national_code"><?= e(error_for('national_code')) ?></div>
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="source">نحوه آشنایی</label>
                        <input class="mr-input" id="source" name="source" list="sourceList" value="<?= e($v('source')) ?>">
                        <datalist id="sourceList">
                            <option value="اینستاگرام"><option value="معرفی دوستان">
                            <option value="مراجعه حضوری"><option value="وب‌سایت">
                        </datalist>
                    </div>
                </div>
            </div>

            <div class="mr-card">
                <div class="mr-card__head"><h2 class="mr-card__title">پرونده زیبایی</h2></div>
                <div class="mr-card__body grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="skin_type">نوع پوست</label>
                        <input class="mr-input" id="skin_type" name="skin_type" value="<?= e($v('skin_type')) ?>">
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="hair_type">نوع مو</label>
                        <input class="mr-input" id="hair_type" name="hair_type" value="<?= e($v('hair_type')) ?>">
                    </div>
                    <div class="mr-field mb-0 col-span-2">
                        <label class="mr-label" for="allergies">حساسیت‌ها</label>
                        <textarea class="mr-textarea" id="allergies" name="allergies" rows="2"><?= e($v('allergies')) ?></textarea>
                        <div class="mr-help">اطلاعات حساسیت پیش از هر خدمت به متخصص نمایش داده می‌شود.</div>
                    </div>
                    <div class="mr-field mb-0 col-span-2">
                        <label class="mr-label" for="medical_notes">نکات پزشکی</label>
                        <textarea class="mr-textarea" id="medical_notes" name="medical_notes" rows="2"><?= e($v('medical_notes')) ?></textarea>
                    </div>
                    <div class="mr-field mb-0 col-span-2">
                        <label class="mr-label" for="preferences">ترجیحات مشتری</label>
                        <textarea class="mr-textarea" id="preferences" name="preferences" rows="2"><?= e($v('preferences')) ?></textarea>
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="instagram">اینستاگرام</label>
                        <input class="mr-input" id="instagram" name="instagram" dir="ltr" value="<?= e($v('instagram')) ?>">
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
                            <?php foreach (['ACTIVE' => 'فعال', 'INACTIVE' => 'غیرفعال', 'BLACKLIST' => 'لیست سیاه'] as $k => $lbl): ?>
                                <option value="<?= $k ?>" <?= $v('status', 'ACTIVE') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mr-field">
                        <label class="mr-label" for="preferred_branch_id">شعبه ترجیحی</label>
                        <select class="mr-select" id="preferred_branch_id" name="preferred_branch_id">
                            <option value="">—</option>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= (int)$b['id'] ?>" <?= (int)$v('preferred_branch_id') === (int)$b['id'] ? 'selected' : '' ?>>
                                    <?= e($b['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mr-field">
                        <label class="mr-label" for="preferred_staff_id">متخصص ترجیحی</label>
                        <select class="mr-select" id="preferred_staff_id" name="preferred_staff_id">
                            <option value="">—</option>
                            <?php foreach ($staff as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" <?= (int)$v('preferred_staff_id') === (int)$s['id'] ? 'selected' : '' ?>>
                                    <?= e($s['first_name'] . ' ' . $s['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="marketing_opt_in" value="1"
                            <?= (int)$v('marketing_opt_in', 1) === 1 ? 'checked' : '' ?>>
                        <span class="text-sm">دریافت پیامک تبلیغاتی</span>
                    </label>
                </div>
            </div>

            <div class="mr-card mb-4">
                <div class="mr-card__head"><h2 class="mr-card__title">برچسب‌ها</h2></div>
                <div class="mr-card__body flex gap-2 flex-wrap">
                    <?php if ($tags === []): ?>
                        <span class="text-sm text-muted">برچسبی تعریف نشده است.</span>
                    <?php else: ?>
                        <?php foreach ($tags as $t): ?>
                            <label class="mr-badge" style="cursor:pointer;border-color:<?= e($t['color']) ?>">
                                <input type="checkbox" name="tags[]" value="<?= (int)$t['id'] ?>"
                                    <?= in_array((int)$t['id'], array_map('intval', $selectedTags), true) ? 'checked' : '' ?>>
                                <?= e($t['name']) ?>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex gap-2">
                <button class="mr-btn mr-btn--primary flex-1" type="submit" id="saveBtn">
                    <?= $isEdit ? 'ذخیره تغییرات' : 'ثبت مشتری' ?>
                </button>
                <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/customers') ?>">انصراف</a>
            </div>
        </aside>
    </div>
</form>
<?php View::endSection();

View::section('scripts'); ?>
<script type="module">
import { busy } from '<?= asset('js/core.js') ?>';
document.getElementById('customerForm')?.addEventListener('submit', () => {
    busy(document.getElementById('saveBtn'), true, 'در حال ذخیره…');
});
</script>
<?php View::endSection();
