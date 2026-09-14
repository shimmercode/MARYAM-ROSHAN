<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/**
 * Customer 360.
 * @var array $customer @var array $tags @var array $appointments @var array $invoices
 * @var array $payments @var array $services @var array $notes @var array $loyalty
 * @var array $wallet @var array $reviews @var array $activity @var float $walletBalance
 */
$cid = (int)$customer['id'];
?>
<div class="mr-breadcrumb"><a href="<?= url('/admin/customers') ?>">مشتریان</a> / پرونده</div>
<div class="mr-prh mb-5">
    <span class="mr-avatar mr-avatar--lg"><?= e(mb_substr((string)$customer['first_name'], 0, 1)) ?></span>
    <div class="mr-prh__info">
        <h1 class="m-0"><?= e($customer['first_name'] . ' ' . $customer['last_name']) ?></h1>
        <p class="m-0">
            کد <span class="mr-num"><?= e($customer['code']) ?></span> ·
            <a class="mr-num" dir="ltr" href="tel:<?= e($customer['mobile']) ?>"><?= fa($customer['mobile']) ?></a>
        </p>
        <div class="mr-prh__stats">
            <span><i class="bi bi-cash-coin" aria-hidden="true"></i> <?= money($customer['total_spent'], false) ?></span>
            <span><i class="bi bi-calendar-check" aria-hidden="true"></i> <?= fa((int)$customer['visits_count']) ?> مراجعه</span>
            <span><i class="bi bi-gift" aria-hidden="true"></i> <?= fa((int)$customer['loyalty_points']) ?> امتیاز</span>
        </div>
    </div>
    <div class="mr-prh__actions flex gap-2 flex-wrap">
        <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/customers/' . $cid . '/edit') ?>">ویرایش</a>
        <a class="mr-btn mr-btn--soft" href="<?= url('/admin/appointments/create?customer_id=' . $cid) ?>">نوبت جدید</a>
        <a class="mr-btn mr-btn--primary" href="<?= url('/admin/pos?customer_id=' . $cid) ?>">فروش جدید</a>
    </div>
</div>

<?php if (!empty($customer['allergies'])): ?>
    <div class="mr-alert mr-alert--danger mb-4">
        <span aria-hidden="true">⚠️</span>
        <span><strong>حساسیت:</strong> <?= e($customer['allergies']) ?></span>
    </div>
<?php endif; ?>

<section class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
    <?= component('kpi', ['label' => 'مجموع خرید', 'icon' => '💰', 'tone' => 'success', 'value' => money($customer['total_spent'])]) ?>
    <?= component('kpi', ['label' => 'تعداد مراجعه', 'icon' => '💺', 'tone' => 'info', 'value' => fa((int)$customer['visits_count'])]) ?>
    <?= component('kpi', ['label' => 'امتیاز وفاداری', 'icon' => '🎁', 'tone' => 'primary', 'value' => fa((int)$customer['loyalty_points']), 'hint' => $customer['tier_name'] ?? 'برنزی']) ?>
    <?= component('kpi', ['label' => 'کیف پول', 'icon' => '👛', 'tone' => 'warning', 'value' => money($walletBalance)]) ?>
    <?= component('kpi', [
        'label' => 'ریسک ریزش', 'icon' => '📉', 'tone' => (int)$customer['churn_risk'] >= 50 ? 'danger' : 'info',
        'value' => fa((int)$customer['churn_risk']) . '٪',
        'hint'  => $customer['last_visit_at'] ? 'آخرین مراجعه ' . jdate($customer['last_visit_at'], 'j F Y') : 'بدون مراجعه',
    ]) ?>
</section>

<?php if ($tags !== []): ?>
    <div class="flex gap-2 flex-wrap mb-4">
        <?php foreach ($tags as $t): ?>
            <span class="mr-badge" style="border-color:<?= e($t['color']) ?>"><?= e($t['name']) ?></span>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="mr-card">
    <div class="mr-card__head">
        <div class="flex gap-1 flex-wrap" data-tabs>
            <button class="mr-tab is-active" data-tab="appointments">نوبت‌ها</button>
            <button class="mr-tab" data-tab="invoices">فاکتورها</button>
            <button class="mr-tab" data-tab="services">خدمات</button>
            <button class="mr-tab" data-tab="loyalty">وفاداری و کیف پول</button>
            <button class="mr-tab" data-tab="notes">یادداشت‌ها</button>
            <button class="mr-tab" data-tab="reviews">نظرها</button>
            <button class="mr-tab" data-tab="activity">فعالیت‌ها</button>
        </div>
    </div>
    <div class="mr-card__body">

        <div data-tab-panel="appointments">
            <?php if ($appointments === []): ?>
                <?= component('empty', ['icon' => '🗓', 'title' => 'نوبتی ثبت نشده است']) ?>
            <?php else: ?>
                <div class="mr-table__wrap">
                    <table class="mr-table">
                        <thead><tr><th>تاریخ</th><th>ساعت</th><th>خدمات</th><th>متخصص</th><th>مبلغ</th><th>وضعیت</th></tr></thead>
                        <tbody>
                        <?php foreach ($appointments as $a): ?>
                            <tr>
                                <td><a href="<?= url('/admin/appointments/' . $a['id']) ?>"><?= jdate($a['appointment_date'], 'j F Y') ?></a></td>
                                <td class="mr-num"><?= fa(substr((string)$a['start_time'], 0, 5)) ?></td>
                                <td><?= e($a['services'] ?? $a['service_name'] ?? '—') ?></td>
                                <td><?= e($a['staff_name'] ?? '—') ?></td>
                                <td class="mr-num"><?= money($a['total_price'], false) ?></td>
                                <td><?= component('status_badge', ['status' => $a['status']]) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div data-tab-panel="invoices" hidden>
            <?php if ($invoices === []): ?>
                <?= component('empty', ['icon' => '🧾', 'title' => 'فاکتوری صادر نشده است']) ?>
            <?php else: ?>
                <div class="mr-table__wrap">
                    <table class="mr-table">
                        <thead><tr><th>شماره</th><th>تاریخ</th><th>مبلغ</th><th>پرداختی</th><th>وضعیت</th></tr></thead>
                        <tbody>
                        <?php foreach ($invoices as $i): ?>
                            <tr>
                                <td><a class="mr-num" dir="ltr" href="<?= url('/admin/invoices/' . $i['id']) ?>"><?= e($i['invoice_number']) ?></a></td>
                                <td><?= jdate($i['issue_date'], 'j F Y') ?></td>
                                <td class="mr-num"><?= money($i['total'], false) ?></td>
                                <td class="mr-num"><?= money($i['paid_amount'], false) ?></td>
                                <td><?= component('status_badge', ['status' => $i['payment_status']]) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div data-tab-panel="services" hidden>
            <?php if ($services === []): ?>
                <?= component('empty', ['icon' => '✂️', 'title' => 'خدمتی دریافت نشده است']) ?>
            <?php else: ?>
                <div class="flex gap-2 flex-wrap">
                    <?php foreach ($services as $s): ?>
                        <span class="mr-badge mr-badge--info">
                            <?= e($s['name']) ?> × <span class="mr-num"><?= fa((int)$s['times']) ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div data-tab-panel="loyalty" hidden>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <h3 class="text-md mb-2">تاریخچه امتیاز</h3>
                    <?php if ($loyalty === []): ?>
                        <?= component('empty', ['icon' => '🎁', 'title' => 'تراکنش امتیازی وجود ندارد']) ?>
                    <?php else: ?>
                        <?php foreach ($loyalty as $l): ?>
                            <div class="flex justify-between border-b py-2 text-sm">
                                <span><?= e($l['description'] ?? $l['type']) ?><br><small class="text-muted"><?= jdate($l['created_at'], 'j F Y') ?></small></span>
                                <strong class="mr-num <?= (int)$l['points'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= (int)$l['points'] >= 0 ? '+' : '−' ?><?= fa(abs((int)$l['points'])) ?>
                                </strong>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 class="text-md mb-2">گردش کیف پول</h3>
                    <?php if ($wallet === []): ?>
                        <?= component('empty', ['icon' => '👛', 'title' => 'تراکنش کیف پولی وجود ندارد']) ?>
                    <?php else: ?>
                        <?php foreach ($wallet as $w): ?>
                            <div class="flex justify-between border-b py-2 text-sm">
                                <span><?= e($w['description'] ?? $w['type']) ?><br><small class="text-muted"><?= jdate($w['created_at'], 'j F Y') ?></small></span>
                                <strong class="mr-num"><?= money($w['amount'], false) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div data-tab-panel="notes" hidden>
            <form class="mb-4" method="post" action="<?= url('/admin/customers/' . $cid . '/notes') ?>">
                <?= csrf_field() ?>
                <div class="mr-field">
                    <label class="mr-label" for="note">یادداشت جدید</label>
                    <textarea class="mr-textarea" id="note" name="note" rows="2" required
                              placeholder="نکته‌ای درباره این مشتری بنویسید…"></textarea>
                </div>
                <button class="mr-btn mr-btn--soft mr-btn--sm" type="submit">ثبت یادداشت</button>
            </form>
            <?php if ($notes === []): ?>
                <?= component('empty', ['icon' => '📝', 'title' => 'یادداشتی ثبت نشده است']) ?>
            <?php else: ?>
                <?php foreach ($notes as $n): ?>
                    <div class="border-b py-3">
                        <p class="text-sm mb-1"><?= nl2br(e($n['note'])) ?></p>
                        <small class="text-xs text-muted">
                            <?= e($n['user_name'] ?? 'سیستم') ?> · <?= jdate($n['created_at'], 'j F Y — H:i') ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div data-tab-panel="reviews" hidden>
            <?php if ($reviews === []): ?>
                <?= component('empty', ['icon' => '💬', 'title' => 'نظری ثبت نشده است']) ?>
            <?php else: ?>
                <?php foreach ($reviews as $r): ?>
                    <div class="border-b py-3">
                        <div class="flex justify-between mb-1">
                            <span class="stars"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></span>
                            <?= component('status_badge', ['status' => $r['status']]) ?>
                        </div>
                        <p class="text-sm"><?= e($r['comment'] ?? '') ?></p>
                        <small class="text-xs text-muted"><?= jdate($r['created_at'], 'j F Y') ?></small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div data-tab-panel="activity" hidden>
            <?php if ($activity === []): ?>
                <?= component('empty', ['icon' => '🕐', 'title' => 'فعالیتی ثبت نشده است']) ?>
            <?php else: ?>
                <ul class="mr-timeline">
                    <?php foreach ($activity as $act): ?>
                        <li>
                            <strong class="text-sm"><?= e($act['title'] ?? $act['kind'] ?? 'رویداد') ?></strong>
                            <small class="text-xs text-muted"><?= jdate($act['at'], 'j F Y — H:i') ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php View::endSection();
