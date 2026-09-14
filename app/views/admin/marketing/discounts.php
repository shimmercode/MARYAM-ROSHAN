<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array $discounts @var array $services @var array $tiers */
$scopes = ['ALL' => 'همه خدمات', 'SERVICE' => 'خدمت خاص', 'CATEGORY' => 'دسته خدمت', 'CUSTOMER' => 'مشتری خاص', 'TIER' => 'سطح وفاداری'];
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb"><a href="<?= url('/admin/marketing/campaigns') ?>">بازاریابی</a> / تخفیف‌ها</div>
        <h1>تخفیف‌ها و کدها</h1>
        <p>کدهای تخفیف قابل استفاده در صندوق و رزرو آنلاین</p>
    </div>
    <button class="mr-btn mr-btn--primary" type="button" data-modal-open="discountModal">تخفیف جدید</button>
</div>

<div class="mr-card">
    <div class="mr-card__body">
        <?php if ($discounts === []): ?>
            <?= component('empty', ['icon' => '🎟', 'title' => 'تخفیفی تعریف نشده است', 'text' => 'کد تخفیف بسازید تا در صندوق قابل اعمال باشد.']) ?>
        <?php else: ?>
            <div class="mr-table__wrap">
                <table class="mr-table">
                    <thead><tr><th>عنوان</th><th>کد</th><th>مقدار</th><th>دامنه</th><th>بازه</th><th>مصرف</th><th>وضعیت</th></tr></thead>
                    <tbody>
                    <?php foreach ($discounts as $d): ?>
                        <tr>
                            <td><?= e($d['name']) ?></td>
                            <td class="mr-num text-xs"><?= e($d['codes'] ?? '—') ?></td>
                            <td class="mr-num">
                                <?= $d['type'] === 'PERCENT' ? fa((float)$d['value']) . '٪' : money($d['value'], false) ?>
                            </td>
                            <td class="text-xs"><?= e($scopes[$d['scope']] ?? $d['scope']) ?></td>
                            <td class="text-xs">
                                <?= $d['starts_at'] ? jdate($d['starts_at'], 'j F') : '—' ?> تا
                                <?= $d['ends_at'] ? jdate($d['ends_at'], 'j F Y') : 'نامحدود' ?>
                            </td>
                            <td class="mr-num text-xs">
                                <?= fa((int)$d['used_count']) ?><?= (int)$d['usage_limit'] > 0 ? ' / ' . fa((int)$d['usage_limit']) : '' ?>
                            </td>
                            <td><?= component('status_badge', ['status' => $d['status']]) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="mr-modal" id="discountModal" hidden>
    <div class="mr-modal__panel">
        <form method="post" action="<?= url('/admin/marketing/discounts') ?>">
            <?= csrf_field() ?>
            <div class="mr-card__head">
                <h2 class="mr-card__title">تخفیف جدید</h2>
                <button class="mr-iconbtn" type="button" data-modal-close="discountModal">✕</button>
            </div>
            <div class="mr-card__body grid grid-cols-2 gap-3">
                <div class="mr-field mb-0 col-span-2">
                    <label class="mr-label" for="dname">عنوان <span class="req">*</span></label>
                    <input class="mr-input" id="dname" name="name" required maxlength="120">
                </div>
                <div class="mr-field mb-0">
                    <label class="mr-label" for="dtype">نوع</label>
                    <select class="mr-select" id="dtype" name="type">
                        <option value="PERCENT">درصدی</option>
                        <option value="FIXED">مبلغ ثابت</option>
                    </select>
                </div>
                <div class="mr-field mb-0">
                    <label class="mr-label" for="dvalue">مقدار <span class="req">*</span></label>
                    <input class="mr-input mr-num" id="dvalue" name="value" type="number" min="0" step="1" required>
                </div>
                <div class="mr-field mb-0">
                    <label class="mr-label" for="dmax">سقف تخفیف</label>
                    <input class="mr-input mr-num" id="dmax" name="max_amount" type="number" min="0" step="1000">
                </div>
                <div class="mr-field mb-0">
                    <label class="mr-label" for="dmin">حداقل مبلغ فاکتور</label>
                    <input class="mr-input mr-num" id="dmin" name="min_order" type="number" min="0" step="1000">
                </div>
                <div class="mr-field mb-0">
                    <label class="mr-label" for="dscope">دامنه</label>
                    <select class="mr-select" id="dscope" name="scope">
                        <?php foreach ($scopes as $k => $lbl): ?>
                            <option value="<?= e($k) ?>"><?= e($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mr-field mb-0">
                    <label class="mr-label" for="dscopeid">مورد هدف</label>
                    <select class="mr-select" id="dscopeid" name="scope_id">
                        <option value="">—</option>
                        <optgroup label="خدمات">
                            <?php foreach ($services as $s): ?>
                                <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="سطوح وفاداری">
                            <?php foreach ($tiers as $t): ?>
                                <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
                <div class="mr-field mb-0">
                    <label class="mr-label" for="dstart">شروع</label>
                    <input class="mr-input mr-num" id="dstart" name="starts_at" type="date" dir="ltr">
                </div>
                <div class="mr-field mb-0">
                    <label class="mr-label" for="dend">پایان</label>
                    <input class="mr-input mr-num" id="dend" name="ends_at" type="date" dir="ltr">
                </div>
                <div class="mr-field mb-0">
                    <label class="mr-label" for="dlimit">سقف دفعات استفاده</label>
                    <input class="mr-input mr-num" id="dlimit" name="usage_limit" type="number" min="0" value="0">
                </div>
                <div class="mr-field mb-0">
                    <label class="mr-label" for="dcode">کد تخفیف</label>
                    <input class="mr-input mr-num" id="dcode" name="code" dir="ltr" maxlength="30" placeholder="NOWRUZ1404">
                    <div class="mr-help">خالی بگذارید تا تخفیف بدون کد باشد.</div>
                </div>
            </div>
            <div class="mr-card__foot flex gap-2 justify-end">
                <button class="mr-btn mr-btn--ghost" type="button" data-modal-close="discountModal">انصراف</button>
                <button class="mr-btn mr-btn--primary" type="submit">ذخیره</button>
            </div>
        </form>
    </div>
</div>
<?php View::endSection();
