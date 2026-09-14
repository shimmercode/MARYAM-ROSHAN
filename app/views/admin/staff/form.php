<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/**
 * @var array|null $staff @var array $branches @var array $services @var array $roles
 * @var array $selectedServices @var array $specialties @var array $shifts @var array $weekdays
 */
$isEdit = $staff !== null;
$action = $isEdit ? '/admin/staff/' . $staff['id'] : '/admin/staff';
$v = static fn (string $key, $default = '') => old($key, $staff[$key] ?? $default);
$shiftBy = [];
foreach ($shifts as $sh) {
    $shiftBy[(int)$sh['weekday']] = $sh;
}
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb"><a href="<?= url('/admin/staff') ?>">کارکنان</a> / <?= $isEdit ? 'ویرایش' : 'جدید' ?></div>
        <h1><?= $isEdit ? e($staff['first_name'] . ' ' . $staff['last_name']) : 'کارمند جدید' ?></h1>
    </div>
    <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/staff') ?>">بازگشت</a>
</div>

<form method="post" action="<?= url($action) ?>" enctype="multipart/form-data" id="staffForm">
    <?= csrf_field() ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="col-span-2">
            <div class="mr-card mb-4">
                <div class="mr-card__head"><h2 class="mr-card__title">اطلاعات فردی</h2></div>
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
                        <input class="mr-input mr-num" id="mobile" name="mobile" dir="ltr" required value="<?= e($v('mobile')) ?>">
                        <div class="mr-error" data-error-for="mobile"><?= e(error_for('mobile')) ?></div>
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="email">ایمیل</label>
                        <input class="mr-input" id="email" name="email" type="email" dir="ltr" value="<?= e($v('email')) ?>">
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="national_code">کد ملی</label>
                        <input class="mr-input mr-num" id="national_code" name="national_code" dir="ltr" value="<?= e($v('national_code')) ?>">
                        <div class="mr-error" data-error-for="national_code"><?= e(error_for('national_code')) ?></div>
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="job_title">سمت</label>
                        <input class="mr-input" id="job_title" name="job_title" value="<?= e($v('job_title')) ?>"
                               placeholder="مثلاً متخصص رنگ و مش">
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="avatar">تصویر پروفایل</label>
                        <input class="mr-input" id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp">
                        <div class="mr-help">فرمت JPG/PNG/WebP، حداکثر ۲ مگابایت.</div>
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="hire_date">تاریخ استخدام</label>
                        <input class="mr-input mr-num" id="hire_date" name="hire_date" type="date" dir="ltr" value="<?= e($v('hire_date')) ?>">
                    </div>
                    <div class="mr-field mb-0 col-span-2">
                        <label class="mr-label" for="bio">معرفی (نمایش در وب‌سایت)</label>
                        <textarea class="mr-textarea" id="bio" name="bio" rows="3"><?= e($v('bio')) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="mr-card mb-4">
                <div class="mr-card__head"><h2 class="mr-card__title">خدمات قابل ارائه</h2></div>
                <div class="mr-card__body flex gap-2 flex-wrap">
                    <?php foreach ($services as $svc): ?>
                        <label class="mr-badge" style="cursor:pointer">
                            <input type="checkbox" name="service_ids[]" value="<?= (int)$svc['id'] ?>"
                                <?= in_array((int)$svc['id'], array_map('intval', $selectedServices), true) ? 'checked' : '' ?>>
                            <?= e($svc['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mr-card">
                <div class="mr-card__head"><h2 class="mr-card__title">شیفت هفتگی</h2></div>
                <div class="mr-card__body">
                    <p class="mr-help mb-3">فقط روزهایی که تیک می‌خورند در تقویم رزرو باز می‌شوند.</p>
                    <?php foreach ($weekdays as $i => $dayName): $sh = $shiftBy[$i] ?? null; ?>
                        <div class="flex items-center gap-3 border-b py-2">
                            <label class="flex items-center gap-2" style="min-width:120px">
                                <input type="checkbox" name="shifts[<?= $i ?>][active]" value="1" <?= $sh ? 'checked' : '' ?>>
                                <span class="text-sm"><?= e($dayName) ?></span>
                            </label>
                            <input class="mr-input mr-input--sm mr-num" type="time" dir="ltr"
                                   name="shifts[<?= $i ?>][start_time]" value="<?= e(substr((string)($sh['start_time'] ?? '09:00'), 0, 5)) ?>">
                            <span class="text-muted">تا</span>
                            <input class="mr-input mr-input--sm mr-num" type="time" dir="ltr"
                                   name="shifts[<?= $i ?>][end_time]" value="<?= e(substr((string)($sh['end_time'] ?? '20:00'), 0, 5)) ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <aside>
            <div class="mr-card mb-4">
                <div class="mr-card__head"><h2 class="mr-card__title">شغلی و مالی</h2></div>
                <div class="mr-card__body">
                    <div class="mr-field">
                        <label class="mr-label" for="branch_id">شعبه <span class="req">*</span></label>
                        <select class="mr-select" id="branch_id" name="branch_id" required>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= (int)$b['id'] ?>" <?= (int)$v('branch_id') === (int)$b['id'] ? 'selected' : '' ?>>
                                    <?= e($b['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mr-field">
                        <label class="mr-label" for="status">وضعیت</label>
                        <select class="mr-select" id="status" name="status">
                            <?php foreach (['ACTIVE' => 'فعال', 'ON_LEAVE' => 'در مرخصی', 'INACTIVE' => 'غیرفعال'] as $k => $lbl): ?>
                                <option value="<?= $k ?>" <?= $v('status', 'ACTIVE') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mr-field">
                        <label class="mr-label" for="base_salary">حقوق پایه</label>
                        <input class="mr-input mr-num" id="base_salary" name="base_salary" type="number"
                               min="0" step="100000" value="<?= e($v('base_salary', 0)) ?>">
                    </div>
                    <div class="mr-field">
                        <label class="mr-label" for="commission_type">نوع پورسانت</label>
                        <select class="mr-select" id="commission_type" name="commission_type">
                            <?php foreach (['PERCENT' => 'درصدی', 'FIXED' => 'مبلغ ثابت', 'TIERED' => 'پلکانی', 'NONE' => 'ندارد'] as $k => $lbl): ?>
                                <option value="<?= $k ?>" <?= $v('commission_type', 'PERCENT') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mr-field mb-0">
                        <label class="mr-label" for="commission_percent">درصد پورسانت</label>
                        <input class="mr-input mr-num" id="commission_percent" name="commission_percent" type="number"
                               min="0" max="100" step="1" value="<?= e($v('commission_percent', 0)) ?>">
                        <div class="mr-error" data-error-for="commission_percent"><?= e(error_for('commission_percent')) ?></div>
                    </div>
                </div>
            </div>

            <div class="mr-card mb-4">
                <div class="mr-card__head"><h2 class="mr-card__title">نمایش عمومی</h2></div>
                <div class="mr-card__body">
                    <label class="flex items-center gap-2 mb-2">
                        <input type="checkbox" name="show_on_website" value="1" <?= (int)$v('is_public', 1) === 1 ? 'checked' : '' ?>>
                        <span class="text-sm">نمایش در صفحه تیم وب‌سایت</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="online_booking" value="1" <?= (int)$v('online_booking', 1) === 1 ? 'checked' : '' ?>>
                        <span class="text-sm">قابل انتخاب در رزرو آنلاین</span>
                    </label>
                </div>
            </div>

            <?php if (!$isEdit): ?>
                <div class="mr-card mb-4">
                    <div class="mr-card__head"><h2 class="mr-card__title">حساب کاربری</h2></div>
                    <div class="mr-card__body">
                        <label class="flex items-center gap-2 mb-3">
                            <input type="checkbox" name="create_account" value="1" id="createAccount">
                            <span class="text-sm">ساخت حساب ورود به پنل</span>
                        </label>
                        <div id="accountFields" hidden>
                            <div class="mr-field">
                                <label class="mr-label" for="role">نقش</label>
                                <select class="mr-select" id="role" name="role">
                                    <?php foreach ($roles as $r): ?>
                                        <option value="<?= e($r['slug']) ?>" <?= $r['slug'] === 'SPECIALIST' ? 'selected' : '' ?>>
                                            <?= e($r['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mr-field">
                                <label class="mr-label" for="password">رمز عبور</label>
                                <input class="mr-input" id="password" name="password" type="password" autocomplete="new-password">
                                <div class="mr-help">حداقل ۸ کاراکتر.</div>
                                <div class="mr-error" data-error-for="password"><?= e(error_for('password')) ?></div>
                            </div>
                            <div class="mr-field mb-0">
                                <label class="mr-label" for="password_confirmation">تکرار رمز عبور</label>
                                <input class="mr-input" id="password_confirmation" name="password_confirmation"
                                       type="password" autocomplete="new-password">
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="flex gap-2">
                <button class="mr-btn mr-btn--primary flex-1" type="submit" id="saveBtn">
                    <?= $isEdit ? 'ذخیره تغییرات' : 'ثبت کارمند' ?>
                </button>
                <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/staff') ?>">انصراف</a>
            </div>
        </aside>
    </div>
</form>
<?php View::endSection();

View::section('scripts'); ?>
<script type="module">
import { busy } from '<?= asset('js/core.js') ?>';

const toggle = document.getElementById('createAccount');
toggle?.addEventListener('change', () => {
    document.getElementById('accountFields').hidden = !toggle.checked;
});

document.getElementById('staffForm').addEventListener('submit', () => {
    busy(document.getElementById('saveBtn'), true, 'در حال ذخیره…');
});
</script>
<?php View::endSection();
