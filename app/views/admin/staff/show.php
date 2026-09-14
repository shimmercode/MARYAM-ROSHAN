<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/**
 * @var array $staff @var array $specialties @var array $schedule @var array $performance
 * @var string $period @var array $weeklyShifts @var array $commissions
 * @var array $commissionSummary @var array $evaluations
 * @var array $instantOffers @var array $journeyEntries @var array $customerReports
 * @var array $evaluationRubric @var array $evaluationLevels @var array $lastEvaluationItems
 */
$s = $staff;
$weekdays = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];
$scoreLabels = [3 => 'عالی (۳)', 2 => 'خوب (۲)', 1 => 'متوسط (۱)', 0 => 'ضعیف (۰)'];
?>
<div class="mr-breadcrumb"><a href="<?= url('/admin/staff') ?>">کارکنان</a> / پرونده</div>
<div class="mr-card mb-5">
<div class="mr-prh prh" style="border:0;box-shadow:none;padding:0 0 14px">
    <span class="mr-avatar mr-avatar--lg prav"><?= e(mb_substr((string)$s['first_name'], 0, 1)) ?></span>
    <div class="mr-prh__info pri">
        <h1 class="m-0" style="display:none"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></h1>
        <h3><?= e($s['first_name'] . ' ' . $s['last_name']) ?></h3>
        <p>
            <?= e($s['job_title'] ?? '—') ?> · <?= e($s['branch_name'] ?? '—') ?> ·
            <a class="mr-num" dir="ltr" href="tel:<?= e($s['mobile']) ?>"><?= fa($s['mobile']) ?></a>
        </p>
    </div>
    <div class="flex gap-2 flex-wrap">
        <?= component('status_badge', ['status' => $s['status']]) ?>
        <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/staff/' . $s['id'] . '/edit') ?>">ویرایش</a>
    </div>
</div>
<div class="pk">
    <div class="pki"><div class="pkv mr-num"><?= money($performance['revenue'], false) ?></div><div class="pkl">درآمد این ماه</div></div>
    <div class="pki"><div class="pkv mr-num"><?= fa((int)$performance['appointments']) ?></div><div class="pkl">نوبت‌ها</div></div>
    <div class="pki"><div class="pkv mr-num"><?= fa(number_format((float)$performance['rating'], 1)) ?></div><div class="pkl">امتیاز</div></div>
</div>
</div>

<section class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
    <?= component('kpi', ['label' => 'درآمد این ماه', 'icon' => '📈', 'tone' => 'success', 'value' => money($performance['revenue'])]) ?>
    <?= component('kpi', ['label' => 'نوبت‌ها', 'icon' => '🗓', 'tone' => 'info', 'value' => fa((int)$performance['appointments']), 'hint' => 'تکمیل: ' . fa((int)$performance['completed'])]) ?>
    <?= component('kpi', ['label' => 'عدم حضور', 'icon' => '🚫', 'tone' => 'danger', 'value' => fa((int)$performance['no_show'])]) ?>
    <?= component('kpi', ['label' => 'پورسانت دوره', 'icon' => '💰', 'tone' => 'warning', 'value' => money($commissionSummary['total'] ?? 0)]) ?>
    <?= component('kpi', ['label' => 'امتیاز مشتریان', 'icon' => '⭐', 'tone' => 'primary', 'value' => fa(number_format((float)$performance['rating'], 1))]) ?>
</section>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="col-span-2">
        <div class="mr-card mb-4">
            <div class="mr-card__head"><h2 class="mr-card__title">برنامه امروز</h2></div>
            <div class="mr-card__body">
                <?php if ($schedule === []): ?>
                    <?= component('empty', ['icon' => '☕️', 'title' => 'امروز نوبتی ندارد']) ?>
                <?php else: ?>
                    <?php foreach ($schedule as $a): ?>
                        <div class="slot-row">
                            <div class="slot-row__time mr-num"><?= fa(substr((string)$a['start_time'], 0, 5)) ?></div>
                            <div class="slot-row__body">
                                <strong><?= e($a['customer_name'] ?? '') ?></strong>
                                <small><?= e($a['services'] ?? $a['service_name'] ?? '') ?></small>
                            </div>
                            <?= component('status_badge', ['status' => $a['status']]) ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="mr-card mb-4">
            <div class="mr-card__head">
                <h2 class="mr-card__title">پورسانت دوره <?= e($period) ?></h2>
                <span class="mr-badge">پرداخت‌شده: <?= money($commissionSummary['paid'] ?? 0) ?></span>
            </div>
            <div class="mr-card__body">
                <?php if ($commissions === []): ?>
                    <?= component('empty', ['icon' => '💰', 'title' => 'پورسانتی در این دوره ثبت نشده است']) ?>
                <?php else: ?>
                    <div class="mr-table__wrap">
                        <table class="mr-table">
                            <thead><tr><th>تاریخ</th><th>خدمت</th><th>مبلغ پایه</th><th>پورسانت</th><th>وضعیت</th></tr></thead>
                            <tbody>
                            <?php foreach ($commissions as $c): ?>
                                <tr>
                                    <td class="text-xs"><?= jdate($c['created_at'], 'j F') ?></td>
                                    <td><?= e($c['service_name'] ?? '—') ?></td>
                                    <td class="mr-num"><?= money($c['base_amount'], false) ?></td>
                                    <td class="mr-num font-bold"><?= money($c['amount'], false) ?></td>
                                    <td><?= component('status_badge', ['status' => $c['status']]) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="mr-card">
            <div class="mr-card__head"><h2 class="mr-card__title">ارزیابی عملکرد</h2></div>
            <div class="mr-card__body">
                <?php if ($evaluations === []): ?>
                    <?= component('empty', ['icon' => '📋', 'title' => 'ارزیابی ثبت نشده است']) ?>
                <?php else: ?>
                    <?php foreach ($evaluations as $ev): ?>
                        <div class="slot-row">
                            <div class="slot-row__body">
                                <strong><?= jdate($ev['period_start'], 'j F') ?> تا <?= jdate($ev['period_end'], 'j F Y') ?></strong>
                                <small>سطح: <?= e($ev['level'] ?? '—') ?></small>
                            </div>
                            <strong class="mr-num"><?= fa(number_format((float)$ev['total_score'], 1)) ?></strong>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <aside>
        <div class="mr-card mb-4">
            <div class="mr-card__head"><h2 class="mr-card__title">تخصص‌ها</h2></div>
            <div class="mr-card__body flex gap-2 flex-wrap">
                <?php if ($specialties === []): ?>
                    <span class="text-sm text-muted">تخصصی ثبت نشده است.</span>
                <?php else: ?>
                    <?php foreach ($specialties as $sp): ?>
                        <span class="mr-badge mr-badge--info"><?= e($sp['title'] ?? $sp['name'] ?? '') ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="mr-card mb-4">
            <div class="mr-card__head"><h2 class="mr-card__title">شیفت هفتگی</h2></div>
            <div class="mr-card__body">
                <?php if ($weeklyShifts === []): ?>
                    <?= component('empty', ['icon' => '🕐', 'title' => 'شیفتی تعریف نشده است']) ?>
                <?php else: ?>
                    <?php foreach ($weeklyShifts as $w): ?>
                        <div class="flex justify-between border-b py-2 text-sm">
                            <span><?= e($weekdays[(int)$w['weekday']] ?? '—') ?></span>
                            <span class="mr-num">
                                <?= fa(substr((string)$w['start_time'], 0, 5)) ?> — <?= fa(substr((string)$w['end_time'], 0, 5)) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="mr-card">
            <div class="mr-card__head"><h2 class="mr-card__title">اطلاعات استخدام</h2></div>
            <div class="mr-card__body">
                <?php foreach ([
                    'تاریخ استخدام' => $s['hire_date'] ? jdate($s['hire_date'], 'j F Y') : '—',
                    'حقوق پایه'     => money($s['base_salary']),
                    'نوع پورسانت'   => ['PERCENT' => 'درصدی', 'FIXED' => 'ثابت', 'TIERED' => 'پلکانی', 'NONE' => 'ندارد'][$s['commission_type']] ?? '—',
                    'درصد پورسانت'  => fa((float)$s['commission_percent']) . '٪',
                    'نمایش در سایت' => (int)$s['is_public'] === 1 ? 'بله' : 'خیر',
                ] as $label => $value): ?>
                    <div class="flex justify-between border-b py-2 text-sm">
                        <span class="text-muted"><?= e($label) ?></span><span><?= e($value) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </aside>
</div>

<div class="mr-card mt-4">
    <div class="mr-card__head">
        <div class="mr-tabs tabs" data-tabs>
            <button class="mr-tab is-active" data-tab="instant-offers">آفر در لحظه</button>
            <button class="mr-tab" data-tab="journey">سفر مشتری</button>
            <button class="mr-tab" data-tab="customer-reports">مدیریت مشتریان</button>
            <button class="mr-tab" data-tab="evaluation">ارزیابی عملکرد</button>
        </div>
    </div>
    <div class="mr-card__body">

        <!-- ===================== آفر در لحظه ===================== -->
        <div data-tab-panel="instant-offers">
            <details class="mb-4">
                <summary class="mr-btn mr-btn--soft mr-btn--sm" style="display:inline-block;cursor:pointer">+ ثبت آفر جدید</summary>
                <form class="mt-3" method="post" action="<?= url('/admin/staff/' . $s['id'] . '/instant-offers') ?>">
                    <?= csrf_field() ?>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="mr-field"><label class="mr-label">کد مشتری (اختیاری)</label>
                            <input class="mr-input mr-num" name="customer_code" placeholder="در صورت وجود پرونده مشتری"></div>
                        <div class="mr-field"><label class="mr-label">نام</label><input class="mr-input" name="first_name"></div>
                        <div class="mr-field"><label class="mr-label">نام خانوادگی</label><input class="mr-input" name="last_name"></div>
                        <div class="mr-field"><label class="mr-label">شماره تماس</label><input class="mr-input mr-num" name="mobile" dir="ltr"></div>
                        <div class="mr-field"><label class="mr-label">لاین خدمات مشتری</label><input class="mr-input" name="service_line"></div>
                        <div class="mr-field"><label class="mr-label">نحوه آشنایی</label><input class="mr-input" name="source"></div>
                        <div class="mr-field"><label class="mr-label">لاین پیشنهادی ۱</label><input class="mr-input" name="offer_line_1"></div>
                        <div class="mr-field"><label class="mr-label">لاین پیشنهادی ۲</label><input class="mr-input" name="offer_line_2"></div>
                        <div class="mr-field"><label class="mr-label">لاین پیشنهادی ۳</label><input class="mr-input" name="offer_line_3"></div>
                        <div class="mr-field"><label class="mr-label">رزرو وقت (تاریخ)</label><input class="mr-input mr-num" name="booking_date" placeholder="1404/06/22"></div>
                        <div class="mr-field"><label class="mr-label">خرید مشتری (تاریخ)</label><input class="mr-input mr-num" name="purchase_date" placeholder="1404/06/22"></div>
                        <div class="mr-field"><label class="mr-label">نوبت خرید بعدی (تاریخ)</label><input class="mr-input mr-num" name="next_purchase_date" placeholder="1404/06/22"></div>
                        <div class="mr-field md:col-span-3"><label class="mr-label">توضیحات پشت برگه</label><textarea class="mr-textarea" name="notes" rows="2"></textarea></div>
                    </div>
                    <button class="mr-btn mr-btn--primary mr-btn--sm mt-2" type="submit">ثبت</button>
                </form>
            </details>
            <?php if ($instantOffers === []): ?>
                <?= component('empty', ['icon' => '🎁', 'title' => 'آفری ثبت نشده است']) ?>
            <?php else: ?>
                <div class="mr-table__wrap">
                    <table class="mr-table">
                        <thead><tr><th>مشتری</th><th>لاین خدمات</th><th>پیشنهادها</th><th>رزرو</th><th>خرید</th><th>خرید بعدی</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($instantOffers as $o): ?>
                            <tr>
                                <td><?= e($o['customer_name'] ?? trim(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? ''))) ?></td>
                                <td><?= e($o['service_line'] ?? '—') ?></td>
                                <td class="text-xs"><?= e(implode(' / ', array_filter([$o['offer_line_1'] ?? null, $o['offer_line_2'] ?? null, $o['offer_line_3'] ?? null]))) ?: '—' ?></td>
                                <td class="text-xs"><?= $o['booking_date'] ? jdate($o['booking_date'], 'j F') : '—' ?></td>
                                <td class="text-xs"><?= $o['purchase_date'] ? jdate($o['purchase_date'], 'j F') : '—' ?></td>
                                <td class="text-xs"><?= $o['next_purchase_date'] ? jdate($o['next_purchase_date'], 'j F') : '—' ?></td>
                                <td>
                                    <form method="post" action="<?= url('/admin/staff/' . $s['id'] . '/instant-offers/' . $o['id'] . '/delete') ?>">
                                        <?= csrf_field() ?>
                                        <button class="mr-btn mr-btn--danger mr-btn--sm" type="submit" data-confirm="این ردیف حذف شود؟">حذف</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ===================== گزارش سفر مشتری ===================== -->
        <div data-tab-panel="journey" hidden>
            <details class="mb-4">
                <summary class="mr-btn mr-btn--soft mr-btn--sm" style="display:inline-block;cursor:pointer">+ ثبت مرحله سفر مشتری جدید</summary>
                <form class="mt-3" method="post" action="<?= url('/admin/staff/' . $s['id'] . '/journey') ?>">
                    <?= csrf_field() ?>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="mr-field"><label class="mr-label">کد مشتری (اختیاری)</label><input class="mr-input mr-num" name="customer_code"></div>
                        <div class="mr-field"><label class="mr-label">نام</label><input class="mr-input" name="first_name"></div>
                        <div class="mr-field"><label class="mr-label">نام خانوادگی</label><input class="mr-input" name="last_name"></div>
                        <div class="mr-field"><label class="mr-label">لاین خدمات مشتری</label><input class="mr-input" name="service_line"></div>
                        <div class="mr-field"><label class="mr-label">نحوه آشنایی</label><input class="mr-input" name="source"></div>
                        <div class="mr-field"><label class="mr-label">مشاوره انجام‌شده (تاریخ)</label><input class="mr-input mr-num" name="consulted_date" placeholder="1404/06/22"></div>
                        <div class="mr-field"><label class="mr-label">نوبت‌دهی مشتری (تاریخ)</label><input class="mr-input mr-num" name="appointment_date" placeholder="1404/06/22"></div>
                        <div class="mr-field"><label class="mr-label">خرید مشتری (تاریخ)</label><input class="mr-input mr-num" name="purchase_date" placeholder="1404/06/22"></div>
                        <div class="mr-field"><label class="mr-label">کسب رضایت مشتری (تاریخ)</label><input class="mr-input mr-num" name="satisfaction_date" placeholder="1404/06/22"></div>
                        <div class="mr-field"><label class="mr-label">نوبت خرید بعدی (تاریخ)</label><input class="mr-input mr-num" name="next_purchase_date" placeholder="1404/06/22"></div>
                        <div class="mr-field"><label class="mr-label">ارجاع به مدیر داخلی (تاریخ)</label><input class="mr-input mr-num" name="referred_date" placeholder="1404/06/22"></div>
                        <div class="mr-field md:col-span-3"><label class="mr-label">توضیحات</label><textarea class="mr-textarea" name="notes" rows="2"></textarea></div>
                    </div>
                    <button class="mr-btn mr-btn--primary mr-btn--sm mt-2" type="submit">ثبت</button>
                </form>
            </details>
            <?php if ($journeyEntries === []): ?>
                <?= component('empty', ['icon' => '🧭', 'title' => 'رکوردی ثبت نشده است']) ?>
            <?php else: ?>
                <div class="mr-table__wrap">
                    <table class="mr-table">
                        <thead><tr><th>مشتری</th><th>لاین خدمات</th><th>مشاوره</th><th>نوبت‌دهی</th><th>خرید</th><th>رضایت</th><th>خرید بعدی</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($journeyEntries as $j): ?>
                            <tr>
                                <td><?= e($j['customer_name'] ?? trim(($j['first_name'] ?? '') . ' ' . ($j['last_name'] ?? ''))) ?></td>
                                <td><?= e($j['service_line'] ?? '—') ?></td>
                                <td class="text-xs"><?= $j['consulted_date'] ? jdate($j['consulted_date'], 'j F') : '—' ?></td>
                                <td class="text-xs"><?= $j['appointment_date'] ? jdate($j['appointment_date'], 'j F') : '—' ?></td>
                                <td class="text-xs"><?= $j['purchase_date'] ? jdate($j['purchase_date'], 'j F') : '—' ?></td>
                                <td class="text-xs"><?= $j['satisfaction_date'] ? jdate($j['satisfaction_date'], 'j F') : '—' ?></td>
                                <td class="text-xs"><?= $j['next_purchase_date'] ? jdate($j['next_purchase_date'], 'j F') : '—' ?></td>
                                <td>
                                    <form method="post" action="<?= url('/admin/staff/' . $s['id'] . '/journey/' . $j['id'] . '/delete') ?>">
                                        <?= csrf_field() ?>
                                        <button class="mr-btn mr-btn--danger mr-btn--sm" type="submit" data-confirm="این ردیف حذف شود؟">حذف</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ===================== جدول مدیریت مشتریان ===================== -->
        <div data-tab-panel="customer-reports" hidden>
            <details class="mb-4">
                <summary class="mr-btn mr-btn--soft mr-btn--sm" style="display:inline-block;cursor:pointer">+ ثبت گزارش دوره‌ای جدید</summary>
                <form class="mt-3" method="post" action="<?= url('/admin/staff/' . $s['id'] . '/customer-reports') ?>">
                    <?= csrf_field() ?>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-2">
                        <div class="mr-field"><label class="mr-label">نام لاین خدمات</label><input class="mr-input" name="line_name" required></div>
                        <div class="mr-field"><label class="mr-label">از تاریخ دوره گزارش</label><input class="mr-input mr-num" name="period_from" placeholder="1404/06/01" required></div>
                        <div class="mr-field"><label class="mr-label">تا تاریخ دوره گزارش</label><input class="mr-input mr-num" name="period_to" placeholder="1404/06/22" required></div>
                        <div class="mr-field"><label class="mr-label">از تاریخ دوره قبل</label><input class="mr-input mr-num" name="prev_period_from" placeholder="1404/05/01"></div>
                        <div class="mr-field"><label class="mr-label">تا تاریخ دوره قبل</label><input class="mr-input mr-num" name="prev_period_to" placeholder="1404/05/30"></div>
                    </div>
                    <div class="mr-table__wrap mb-2">
                        <table class="mr-table">
                            <thead><tr><th>شاخص</th><th>دوره گزارش</th><th>دوره قبل</th></tr></thead>
                            <tbody>
                            <?php foreach ([
                                ['total_customers', 'تعداد کل مشتریان', 'number'],
                                ['new_customers', 'مشتریان جدید', 'number'],
                                ['returning_customers', 'بازگشت مشتریان', 'number'],
                                ['loyalty_percent', 'درصد وفادارسازی', 'number'],
                                ['sales_amount', 'مبلغ فروش (تومان)', 'number'],
                                ['sales_count', 'تعداد فروش', 'number'],
                                ['salon_credit', 'اعتبار از سالن', 'number'],
                                ['offer_amount', 'آفر - مبلغ آفر', 'number'],
                                ['offer_purchase', 'آفر - خرید از آفر', 'number'],
                                ['other_campaigns', 'سایر کمپین‌ها', 'text'],
                                ['rank_in_line', 'رتبه / جایگاه در لاین', 'text'],
                            ] as [$key, $label, $type]): ?>
                                <tr>
                                    <td><?= e($label) ?></td>
                                    <td><input class="mr-input <?= $type === 'number' ? 'mr-num' : '' ?>" type="<?= $type === 'number' ? 'number' : 'text' ?>" step="any" name="<?= e($key) ?>"></td>
                                    <td><input class="mr-input <?= $type === 'number' ? 'mr-num' : '' ?>" type="<?= $type === 'number' ? 'number' : 'text' ?>" step="any" name="<?= e($key) ?>_prev"></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mr-field"><label class="mr-label">توضیحات / تحلیل</label><textarea class="mr-textarea" name="analysis" rows="2"></textarea></div>
                    <button class="mr-btn mr-btn--primary mr-btn--sm mt-2" type="submit">ثبت گزارش</button>
                </form>
            </details>
            <?php if ($customerReports === []): ?>
                <?= component('empty', ['icon' => '📊', 'title' => 'گزارشی ثبت نشده است']) ?>
            <?php else: ?>
                <div class="mr-table__wrap">
                    <table class="mr-table">
                        <thead><tr><th>لاین</th><th>دوره گزارش</th><th>مشتریان کل</th><th>مشتریان جدید</th><th>مبلغ فروش</th><th>رتبه</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($customerReports as $r): ?>
                            <tr>
                                <td><?= e($r['line_name']) ?></td>
                                <td class="text-xs"><?= jdate($r['period_from'], 'j F') ?> تا <?= jdate($r['period_to'], 'j F Y') ?></td>
                                <td class="mr-num"><?= $r['total_customers'] !== null ? fa((int)$r['total_customers']) : '—' ?></td>
                                <td class="mr-num"><?= $r['new_customers'] !== null ? fa((int)$r['new_customers']) : '—' ?></td>
                                <td class="mr-num"><?= $r['sales_amount'] !== null ? money($r['sales_amount'], false) : '—' ?></td>
                                <td><?= e($r['rank_in_line'] ?? '—') ?></td>
                                <td>
                                    <form method="post" action="<?= url('/admin/staff/' . $s['id'] . '/customer-reports/' . $r['id'] . '/delete') ?>">
                                        <?= csrf_field() ?>
                                        <button class="mr-btn mr-btn--danger mr-btn--sm" type="submit" data-confirm="این گزارش حذف شود؟">حذف</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ===================== ارزیابی عملکرد ===================== -->
        <div data-tab-panel="evaluation" hidden>
            <details class="mb-4">
                <summary class="mr-btn mr-btn--soft mr-btn--sm" style="display:inline-block;cursor:pointer">+ ثبت ارزیابی عملکرد جدید</summary>
                <form class="mt-3" method="post" action="<?= url('/admin/staff/' . $s['id'] . '/evaluations') ?>">
                    <?= csrf_field() ?>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                        <div class="mr-field"><label class="mr-label">شروع دوره</label><input class="mr-input mr-num" name="period_start" placeholder="1404/04/01" required></div>
                        <div class="mr-field"><label class="mr-label">پایان دوره</label><input class="mr-input mr-num" name="period_end" placeholder="1404/06/30" required></div>
                        <div class="mr-field"><label class="mr-label">امتیاز ویژه مدیریت (حداکثر ۴)</label>
                            <input class="mr-input mr-num" type="number" step="0.5" min="0" max="4" name="special_score" value="0"></div>
                    </div>
                    <div class="mr-table__wrap mb-3">
                        <table class="mr-table">
                            <thead><tr><th>معیار</th><th>شاخص</th><th style="width:180px">امتیاز</th></tr></thead>
                            <tbody>
                            <?php $prevCat = null; foreach ($evaluationRubric as $i => $row): ?>
                                <tr>
                                    <td><?= $row['category'] !== $prevCat ? '<strong>' . e($row['category']) . '</strong>' : '' ?></td>
                                    <td class="text-xs"><?= e($row['indicator']) ?></td>
                                    <td>
                                        <select class="mr-input" name="scores[<?= $i ?>]" required>
                                            <option value="">—</option>
                                            <?php foreach ($scoreLabels as $val => $lbl): ?>
                                                <option value="<?= $val ?>"><?= e($lbl) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php $prevCat = $row['category']; endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mr-field"><label class="mr-label">نتیجه عملکرد از دیدگاه مدیر سالن / عارضه‌یابی</label>
                        <textarea class="mr-textarea" name="summary" rows="2"></textarea></div>
                    <button class="mr-btn mr-btn--primary mr-btn--sm mt-2" type="submit">ثبت ارزیابی</button>
                </form>
            </details>

            <?php if ($evaluations === []): ?>
                <?= component('empty', ['icon' => '📋', 'title' => 'ارزیابی ثبت نشده است']) ?>
            <?php else: ?>
                <div class="mr-table__wrap mb-4">
                    <table class="mr-table">
                        <thead><tr><th>دوره</th><th>نتیجه عملکرد</th><th>امتیاز ویژه</th><th>رتبه</th><th>وضعیت</th></tr></thead>
                        <tbody>
                        <?php foreach ($evaluations as $ev): ?>
                            <tr>
                                <td class="text-xs"><?= jdate($ev['period_start'], 'j F') ?> تا <?= jdate($ev['period_end'], 'j F Y') ?></td>
                                <td class="mr-num font-bold"><?= fa(number_format((float)$ev['total_score'], 1)) ?></td>
                                <td class="mr-num"><?= fa(number_format((float)($ev['special_score'] ?? 0), 1)) ?></td>
                                <td><?= e($ev['rank_label'] ?? $ev['level'] ?? '—') ?></td>
                                <td><?= component('status_badge', ['status' => $ev['status'] ?? 'DRAFT']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <details>
                <summary class="text-sm text-muted" style="cursor:pointer">جدول امتیازبندی و رتبه‌بندی (سیاست پاداش/جریمه)</summary>
                <div class="mr-table__wrap mt-2">
                    <table class="mr-table">
                        <thead><tr><th>بازه امتیاز</th><th>نتیجه عملکرد</th><th>رتبه پرسنل</th><th>اقدامات مدیریت</th></tr></thead>
                        <tbody>
                        <?php foreach ($evaluationLevels as $lv): ?>
                            <tr>
                                <td class="mr-num"><?= $lv['min'] < 0 ? 'کمتر از ۵۰' : fa((int)$lv['min']) . ' تا ' . fa((int)min(100, $lv['max'])) ?></td>
                                <td><?= e($lv['result']) ?></td>
                                <td><strong><?= e($lv['rank']) ?></strong></td>
                                <td class="text-xs"><?= e($lv['actions']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        </div>
    </div>
</div>
<?php View::endSection();
