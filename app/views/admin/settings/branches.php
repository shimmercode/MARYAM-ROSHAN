<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array $branches */
$weekdays = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb"><a href="<?= url('/admin/settings') ?>">تنظیمات</a> / شعبه‌ها</div>
        <h1>شعبه‌ها</h1>
        <p>ساعت کاری و مشخصات هر شعبه</p>
    </div>
    <button class="mr-btn mr-btn--primary" type="button" data-modal-open="branchModal" id="newBranchBtn">شعبه جدید</button>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <?php if ($branches === []): ?>
        <?= component('empty', ['icon' => '🏢', 'title' => 'شعبه‌ای ثبت نشده است']) ?>
    <?php else: ?>
        <?php foreach ($branches as $b): $days = array_filter(explode(',', (string)$b['working_days']), 'strlen'); ?>
            <article class="mr-card">
                <div class="mr-card__head">
                    <h2 class="mr-card__title"><?= e($b['name']) ?></h2>
                    <?= component('status_badge', ['status' => $b['status']]) ?>
                </div>
                <div class="mr-card__body">
                    <div class="flex justify-between border-b py-2 text-sm">
                        <span class="text-muted">کد</span><span class="mr-num"><?= e($b['slug']) ?></span>
                    </div>
                    <div class="flex justify-between border-b py-2 text-sm">
                        <span class="text-muted">تلفن</span><span class="mr-num" dir="ltr"><?= fa($b['phone']) ?></span>
                    </div>
                    <div class="flex justify-between border-b py-2 text-sm">
                        <span class="text-muted">ساعت کاری</span>
                        <span class="mr-num"><?= fa(substr((string)$b['opening_time'], 0, 5)) ?> — <?= fa(substr((string)$b['closing_time'], 0, 5)) ?></span>
                    </div>
                    <div class="flex justify-between border-b py-2 text-sm">
                        <span class="text-muted">پرسنل</span><span class="mr-num"><?= fa((int)$b['staff_count']) ?></span>
                    </div>
                    <p class="text-sm text-muted mt-2"><?= e($b['address']) ?></p>
                    <div class="flex gap-1 flex-wrap mt-2">
                        <?php foreach ($days as $d): ?>
                            <span class="mr-badge mr-badge--info"><?= e($weekdays[(int)$d] ?? '') ?></span>
                        <?php endforeach; ?>
                    </div>
                    <button class="mr-btn mr-btn--soft mr-btn--sm mt-3" type="button"
                            data-edit-branch='<?= e(json_encode([
                                'id' => (int)$b['id'], 'name' => $b['name'], 'slug' => $b['slug'],
                                'phone' => $b['phone'], 'address' => $b['address'],
                                'opening_time' => substr((string)$b['opening_time'], 0, 5),
                                'closing_time' => substr((string)$b['closing_time'], 0, 5),
                                'latitude' => $b['latitude'], 'longitude' => $b['longitude'],
                                'status' => $b['status'], 'days' => array_map('intval', $days),
                            ], JSON_UNESCAPED_UNICODE)) ?>'>ویرایش</button>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="mr-modal" id="branchModal" hidden>
    <div class="mr-modal__panel">
        <form method="post" action="<?= url('/admin/settings/branches') ?>" id="branchForm">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="b_id" value="">
            <div class="mr-card__head">
                <h2 class="mr-card__title" id="branchModalTitle">شعبه جدید</h2>
                <button class="mr-iconbtn" type="button" data-modal-close="branchModal">✕</button>
            </div>
            <div class="mr-card__body grid grid-cols-2 gap-3">
                <div class="mr-field mb-0">
                    <label class="mr-label" for="b_name">نام شعبه <span class="req">*</span></label>
                    <input class="mr-input" id="b_name" name="name" required maxlength="120">
                </div>
                <div class="mr-field mb-0">
                    <label class="mr-label" for="b_slug">کد شعبه <span class="req">*</span></label>
                    <input class="mr-input mr-num" id="b_slug" name="slug" dir="ltr" required maxlength="20">
                </div>
                <div class="mr-field mb-0">
                    <label class="mr-label" for="b_phone">تلفن <span class="req">*</span></label>
                    <input class="mr-input mr-num" id="b_phone" name="phone" dir="ltr" required maxlength="20">
                </div>
                <div class="mr-field mb-0">
                    <label class="mr-label" for="b_status">وضعیت</label>
                    <select class="mr-select" id="b_status" name="status">
                        <option value="ACTIVE">فعال</option>
                        <option value="INACTIVE">غیرفعال</option>
                    </select>
                </div>
                <div class="mr-field mb-0 col-span-2">
                    <label class="mr-label" for="b_address">آدرس <span class="req">*</span></label>
                    <textarea class="mr-textarea" id="b_address" name="address" rows="2" required maxlength="255"></textarea>
                </div>
                <div class="mr-field mb-0">
                    <label class="mr-label" for="b_open">ساعت شروع <span class="req">*</span></label>
                    <input class="mr-input mr-num" id="b_open" name="opening_time" type="time" dir="ltr" required value="09:00">
                </div>
                <div class="mr-field mb-0">
                    <label class="mr-label" for="b_close">ساعت پایان <span class="req">*</span></label>
                    <input class="mr-input mr-num" id="b_close" name="closing_time" type="time" dir="ltr" required value="21:00">
                </div>
                <div class="mr-field mb-0">
                    <label class="mr-label" for="b_lat">عرض جغرافیایی</label>
                    <input class="mr-input mr-num" id="b_lat" name="latitude" dir="ltr" maxlength="20">
                </div>
                <div class="mr-field mb-0">
                    <label class="mr-label" for="b_lng">طول جغرافیایی</label>
                    <input class="mr-input mr-num" id="b_lng" name="longitude" dir="ltr" maxlength="20">
                </div>
                <fieldset class="mr-field mb-0 col-span-2">
                    <legend class="mr-label">روزهای کاری</legend>
                    <div class="flex gap-2 flex-wrap">
                        <?php foreach ($weekdays as $i => $day): ?>
                            <label class="mr-badge" style="cursor:pointer">
                                <input type="checkbox" name="working_days[]" value="<?= $i ?>" data-day="<?= $i ?>" <?= $i < 6 ? 'checked' : '' ?>>
                                <?= e($day) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            </div>
            <div class="mr-card__foot flex gap-2 justify-end">
                <button class="mr-btn mr-btn--ghost" type="button" data-modal-close="branchModal">انصراف</button>
                <button class="mr-btn mr-btn--primary" type="submit">ذخیره شعبه</button>
            </div>
        </form>
    </div>
</div>
<?php View::endSection();

View::section('scripts'); ?>
<script type="module">
import { openModal } from '<?= asset('js/core.js') ?>';

const form = document.getElementById('branchForm');
const title = document.getElementById('branchModalTitle');

document.getElementById('newBranchBtn').addEventListener('click', () => {
    form.reset();
    document.getElementById('b_id').value = '';
    title.textContent = 'شعبه جدید';
});

document.querySelectorAll('[data-edit-branch]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const b = JSON.parse(btn.dataset.editBranch);
        title.textContent = 'ویرایش ' + b.name;
        document.getElementById('b_id').value = b.id;
        document.getElementById('b_name').value = b.name || '';
        document.getElementById('b_slug').value = b.slug || '';
        document.getElementById('b_phone').value = b.phone || '';
        document.getElementById('b_address').value = b.address || '';
        document.getElementById('b_open').value = b.opening_time || '';
        document.getElementById('b_close').value = b.closing_time || '';
        document.getElementById('b_lat').value = b.latitude || '';
        document.getElementById('b_lng').value = b.longitude || '';
        document.getElementById('b_status').value = b.status || 'ACTIVE';
        form.querySelectorAll('[data-day]').forEach((cb) => {
            cb.checked = b.days.includes(Number(cb.dataset.day));
        });
        openModal('branchModal');
    });
});
</script>
<?php View::endSection();
