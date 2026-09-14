<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array $automations @var array $logs @var array $triggers @var array $templates */
$actions = ['SEND_SMS' => 'ارسال پیامک', 'SEND_EMAIL' => 'ارسال ایمیل', 'ADD_TAG' => 'افزودن برچسب', 'REMOVE_TAG' => 'حذف برچسب', 'ADD_POINTS' => 'افزودن امتیاز', 'CREATE_TASK' => 'ایجاد وظیفه', 'NOTIFY_ADMIN' => 'اطلاع به مدیر'];
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb"><a href="<?= url('/admin/marketing/campaigns') ?>">بازاریابی</a> / اتوماسیون</div>
        <h1>اتوماسیون بازاریابی</h1>
        <p>قانون‌های «رویداد ← شرط ← اقدام» که خودکار اجرا می‌شوند</p>
    </div>
    <button class="mr-btn mr-btn--primary" type="button" data-modal-open="autoModal">قانون جدید</button>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="col-span-2">
        <div class="mr-card">
            <div class="mr-card__head"><h2 class="mr-card__title">قانون‌ها</h2></div>
            <div class="mr-card__body">
                <?php if ($automations === []): ?>
                    <?= component('empty', ['icon' => '⚙️', 'title' => 'قانونی تعریف نشده است', 'text' => 'مثلاً: «۲۴ ساعت پس از تکمیل نوبت، پیام نظرسنجی ارسال شود».']) ?>
                <?php else: ?>
                    <?php foreach ($automations as $a): ?>
                        <div class="flex items-center gap-3 border-b py-3">
                            <div class="flex-1">
                                <strong><?= e($a['name']) ?></strong>
                                <div class="text-xs text-muted">
                                    رویداد: <?= e($triggers[$a['trigger_event']] ?? $a['trigger_event']) ?> ·
                                    <?= fa((int)$a['rules_count']) ?> شرط · <?= fa((int)$a['actions_count']) ?> اقدام
                                </div>
                                <?php if (!empty($a['description'])): ?>
                                    <div class="text-xs text-muted"><?= e($a['description']) ?></div>
                                <?php endif; ?>
                            </div>
                            <?= component('status_badge', ['status' => $a['status']]) ?>
                            <form method="post" action="<?= url('/admin/marketing/automations/' . $a['id'] . '/toggle') ?>">
                                <?= csrf_field() ?>
                                <button class="mr-btn mr-btn--ghost mr-btn--sm" type="submit">
                                    <?= $a['status'] === 'ACTIVE' ? 'غیرفعال کردن' : 'فعال کردن' ?>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <aside>
        <div class="mr-card">
            <div class="mr-card__head"><h2 class="mr-card__title">آخرین اجراها</h2></div>
            <div class="mr-card__body">
                <?php if ($logs === []): ?>
                    <?= component('empty', ['icon' => '🪵', 'title' => 'هنوز اجرایی ثبت نشده است']) ?>
                <?php else: ?>
                    <ul class="mr-timeline">
                        <?php foreach ($logs as $l): ?>
                            <li>
                                <strong class="text-sm"><?= e($l['name']) ?></strong>
                                <div class="text-xs text-muted">
                                    <?= jdate($l['created_at'], 'j F — H:i') ?> ·
                                    <?= e($l['status'] ?? '') ?>
                                    <?= !empty($l['message']) ? ' · ' . e(mb_substr((string)$l['message'], 0, 60)) : '' ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </aside>
</div>

<div class="mr-modal" id="autoModal" hidden>
    <div class="mr-modal__panel">
        <form method="post" action="<?= url('/admin/marketing/automations') ?>">
            <?= csrf_field() ?>
            <div class="mr-card__head">
                <h2 class="mr-card__title">قانون جدید</h2>
                <button class="mr-iconbtn" type="button" data-modal-close="autoModal">✕</button>
            </div>
            <div class="mr-card__body">
                <div class="mr-field">
                    <label class="mr-label" for="aname">نام قانون <span class="req">*</span></label>
                    <input class="mr-input" id="aname" name="name" required maxlength="150">
                </div>
                <div class="mr-field">
                    <label class="mr-label" for="atrigger">۱. رویداد راه‌انداز <span class="req">*</span></label>
                    <select class="mr-select" id="atrigger" name="trigger_event" required>
                        <?php foreach ($triggers as $k => $lbl): ?>
                            <option value="<?= e($k) ?>"><?= e($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <fieldset class="mr-field">
                    <legend class="mr-label">۲. شرط (اختیاری)</legend>
                    <div class="grid grid-cols-3 gap-2">
                        <input class="mr-input" name="condition_field" placeholder="فیلد (مثلاً total_spent)">
                        <select class="mr-select" name="condition_operator">
                            <option value="EQ">مساوی</option>
                            <option value="NEQ">نامساوی</option>
                            <option value="GT">بزرگ‌تر از</option>
                            <option value="LT">کوچک‌تر از</option>
                            <option value="CONTAINS">شامل</option>
                        </select>
                        <input class="mr-input" name="condition_value" placeholder="مقدار">
                    </div>
                </fieldset>
                <div class="mr-field">
                    <label class="mr-label" for="aaction">۳. اقدام <span class="req">*</span></label>
                    <select class="mr-select" id="aaction" name="action_type" required>
                        <?php foreach ($actions as $k => $lbl): ?>
                            <option value="<?= e($k) ?>"><?= e($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mr-field">
                    <label class="mr-label" for="atemplate">قالب پیام</label>
                    <select class="mr-select" id="atemplate" name="template_id">
                        <option value="">بدون قالب (متن دستی)</option>
                        <?php foreach ($templates as $t): ?>
                            <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mr-field">
                    <label class="mr-label" for="amessage">متن پیام / برچسب / امتیاز</label>
                    <textarea class="mr-textarea" id="amessage" name="action_message" rows="3"></textarea>
                    <div class="grid grid-cols-2 gap-2 mt-2">
                        <input class="mr-input" name="action_tag" placeholder="برچسب (برای اقدام برچسب)">
                        <input class="mr-input mr-num" name="action_points" type="number" min="0" placeholder="امتیاز">
                    </div>
                </div>
                <div class="mr-field mb-0">
                    <label class="mr-label" for="adelay">تأخیر اجرا (دقیقه)</label>
                    <input class="mr-input mr-num" id="adelay" name="delay_minutes" type="number" min="0" value="0">
                </div>
            </div>
            <div class="mr-card__foot flex gap-2 justify-end">
                <button class="mr-btn mr-btn--ghost" type="button" data-modal-close="autoModal">انصراف</button>
                <button class="mr-btn mr-btn--primary" type="submit">ایجاد قانون</button>
            </div>
        </form>
    </div>
</div>
<?php View::endSection();
