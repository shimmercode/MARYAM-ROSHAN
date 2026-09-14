<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array $campaigns @var array $segments @var array $stats */
$statuses = ['DRAFT' => 'پیش‌نویس', 'SCHEDULED' => 'زمان‌بندی‌شده', 'RUNNING' => 'در حال ارسال', 'COMPLETED' => 'ارسال‌شده', 'CANCELLED' => 'لغو شده'];
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb">پنل مدیریت / بازاریابی</div>
        <h1>کمپین‌های پیامکی</h1>
        <p>ارسال گروهی به بخش‌های هدف مشتریان</p>
    </div>
    <div class="flex gap-2">
        <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/marketing/automations') ?>">اتوماسیون</a>
        <button class="mr-btn mr-btn--primary" type="button" data-modal-open="campaignModal">کمپین جدید</button>
    </div>
</div>

<section class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <?= component('kpi', ['label' => 'کل کمپین‌ها', 'icon' => '📣', 'value' => fa((int)$stats['total'])]) ?>
    <?= component('kpi', ['label' => 'پیام ارسال‌شده', 'icon' => '✅', 'value' => fa((int)$stats['sent'])]) ?>
    <?= component('kpi', ['label' => 'ناموفق', 'icon' => '⚠️', 'value' => fa((int)$stats['failed'])]) ?>
    <?= component('kpi', ['label' => 'انصراف از تبلیغات', 'icon' => '🚫', 'value' => fa((int)$stats['optout'])]) ?>
</section>

<div class="mr-card">
    <div class="mr-card__body">
        <?php if ($campaigns === []): ?>
            <?= component('empty', ['icon' => '📣', 'title' => 'هنوز کمپینی نساخته‌اید', 'text' => 'یک بخش از مشتریان را انتخاب و پیام خود را ارسال کنید.']) ?>
        <?php else: ?>
            <div class="mr-table__wrap">
                <table class="mr-table">
                    <thead><tr><th>نام</th><th>کانال</th><th>مخاطب</th><th>تعداد</th><th>ارسال</th><th>ناموفق</th><th>وضعیت</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($campaigns as $c): ?>
                        <tr>
                            <td>
                                <strong><?= e($c['name']) ?></strong>
                                <div class="text-xs text-muted"><?= e(mb_substr((string)$c['message'], 0, 60)) ?>…</div>
                            </td>
                            <td><?= e($c['channel']) ?></td>
                            <td class="text-xs"><?= e($c['segment_name'] ?? 'دستی') ?></td>
                            <td class="mr-num"><?= fa((int)$c['audience_count']) ?></td>
                            <td class="mr-num"><?= fa((int)$c['sent_count']) ?></td>
                            <td class="mr-num"><?= fa((int)$c['failed_count']) ?></td>
                            <td><span class="mr-badge"><?= e($statuses[$c['status']] ?? $c['status']) ?></span></td>
                            <td>
                                <?php if (!in_array($c['status'], ['COMPLETED', 'CANCELLED'], true)): ?>
                                    <form method="post" action="<?= url('/admin/marketing/campaigns/' . $c['id'] . '/send') ?>">
                                        <?= csrf_field() ?>
                                        <button class="mr-btn mr-btn--soft mr-btn--sm" type="submit"
                                                data-confirm="پیام برای <?= fa((int)$c['audience_count']) ?> مشتری ارسال شود؟">ارسال</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-xs text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="mr-modal" id="campaignModal" hidden>
    <div class="mr-modal__panel">
        <form method="post" action="<?= url('/admin/marketing/campaigns') ?>">
            <?= csrf_field() ?>
            <div class="mr-card__head">
                <h2 class="mr-card__title">کمپین جدید</h2>
                <button class="mr-iconbtn" type="button" data-modal-close="campaignModal">✕</button>
            </div>
            <div class="mr-card__body">
                <div class="mr-field">
                    <label class="mr-label" for="cname">نام کمپین <span class="req">*</span></label>
                    <input class="mr-input" id="cname" name="name" required maxlength="150">
                </div>
                <div class="mr-field">
                    <label class="mr-label" for="cchannel">کانال</label>
                    <select class="mr-select" id="cchannel" name="channel">
                        <option value="SMS">پیامک</option>
                        <option value="EMAIL">ایمیل</option>
                        <option value="INAPP">اعلان در پنل مشتری</option>
                    </select>
                </div>
                <div class="mr-field">
                    <label class="mr-label" for="csegment">مخاطبان <span class="req">*</span></label>
                    <select class="mr-select" id="csegment" name="segment" required>
                        <?php foreach ($segments as $s): ?>
                            <option value="<?= e($s['key']) ?>"><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mr-field">
                    <label class="mr-label" for="cmessage">متن پیام <span class="req">*</span></label>
                    <textarea class="mr-textarea" id="cmessage" name="message" rows="4" maxlength="600" required
                              placeholder="سلام {first} عزیز، ..."></textarea>
                    <div class="mr-help">می‌توانید از <code>{name}</code> و <code>{first}</code> استفاده کنید.</div>
                </div>
                <div class="mr-field mb-0">
                    <label class="mr-label" for="cschedule">زمان‌بندی ارسال</label>
                    <input class="mr-input mr-num" id="cschedule" name="scheduled_at" type="date" dir="ltr">
                    <div class="mr-help">خالی بگذارید تا به‌صورت پیش‌نویس ذخیره شود.</div>
                </div>
            </div>
            <div class="mr-card__foot flex gap-2 justify-end">
                <button class="mr-btn mr-btn--ghost" type="button" data-modal-close="campaignModal">انصراف</button>
                <button class="mr-btn mr-btn--primary" type="submit">ذخیره کمپین</button>
            </div>
        </form>
    </div>
</div>
<?php View::endSection();
