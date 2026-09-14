<?php
use App\Core\View;
use App\Services\AppointmentService;
View::extend('admin');
View::section('content');
/** @var array $appointment @var array $items @var array $history @var array $statuses */
$a = $appointment;
$allowed = AppointmentService::TRANSITIONS[$a['status']] ?? [];
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb"><a href="<?= url('/admin/appointments') ?>">نوبت‌ها</a> / <?= e($a['code']) ?></div>
        <h1>نوبت <span class="mr-num" dir="ltr"><?= e($a['code']) ?></span></h1>
        <p><?= jdate($a['appointment_date'], 'l j F Y') ?> — ساعت <span class="mr-num"><?= fa(substr((string)$a['start_time'], 0, 5)) ?></span></p>
    </div>
    <div class="flex gap-2 flex-wrap">
        <?= component('status_badge', ['status' => $a['status']]) ?>
        <?php if (in_array($a['status'], ['COMPLETED'], true)): ?>
            <a class="mr-btn mr-btn--primary" href="<?= url('/admin/pos?appointment_id=' . $a['id']) ?>">صدور فاکتور</a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="col-span-2">
        <div class="mr-card mb-4">
            <div class="mr-card__head"><h2 class="mr-card__title">خدمات این نوبت</h2></div>
            <div class="mr-card__body">
                <?php if ($items === []): ?>
                    <?= component('empty', ['icon' => '✂️', 'title' => 'خدمتی ثبت نشده است']) ?>
                <?php else: ?>
                    <div class="mr-table__wrap">
                        <table class="mr-table">
                            <thead><tr><th>خدمت</th><th>مدت</th><th>قیمت</th></tr></thead>
                            <tbody>
                            <?php foreach ($items as $it): ?>
                                <tr>
                                    <td><?= e($it['service_name'] ?? $it['name'] ?? '—') ?></td>
                                    <td class="mr-num"><?= fa((int)($it['duration_minutes'] ?? 0)) ?> دقیقه</td>
                                    <td class="mr-num"><?= money($it['price'], false) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                            <tr>
                                <th colspan="2">جمع کل</th>
                                <th class="mr-num"><?= money($a['total_price']) ?></th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($a['notes'])): ?>
            <div class="mr-alert mr-alert--info mb-4"><span>📝</span><span><?= nl2br(e($a['notes'])) ?></span></div>
        <?php endif; ?>

        <div class="mr-card">
            <div class="mr-card__head"><h2 class="mr-card__title">تاریخچه وضعیت</h2></div>
            <div class="mr-card__body">
                <?php if ($history === []): ?>
                    <?= component('empty', ['icon' => '🕐', 'title' => 'تغییری ثبت نشده است']) ?>
                <?php else: ?>
                    <ul class="mr-timeline">
                        <?php foreach ($history as $h): ?>
                            <li>
                                <strong class="text-sm">
                                    <?= e($statuses[$h['from_status']] ?? $h['from_status'] ?? 'ایجاد') ?>
                                    ← <?= e($statuses[$h['to_status']] ?? $h['to_status']) ?>
                                </strong>
                                <?php if (!empty($h['note'])): ?><p class="text-xs text-muted"><?= e($h['note']) ?></p><?php endif; ?>
                                <small class="text-xs text-muted">
                                    <?= e($h['user_name'] ?? 'سیستم') ?> · <?= jdate($h['created_at'], 'j F Y — H:i') ?>
                                </small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <aside>
        <div class="mr-card mb-4">
            <div class="mr-card__head"><h2 class="mr-card__title">مشتری</h2></div>
            <div class="mr-card__body">
                <a class="font-medium block mb-1" href="<?= url('/admin/customers/' . $a['customer_id']) ?>">
                    <?= e(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?>
                </a>
                <a class="text-sm mr-num" dir="ltr" href="tel:<?= e($a['mobile'] ?? '') ?>"><?= fa($a['mobile'] ?? '') ?></a>
                <div class="text-xs text-muted mt-2">کد مشتری: <span class="mr-num"><?= e($a['customer_code'] ?? '—') ?></span></div>
            </div>
        </div>

        <div class="mr-card mb-4">
            <div class="mr-card__head"><h2 class="mr-card__title">جزئیات</h2></div>
            <div class="mr-card__body">
                <?php
                $rows = [
                    'متخصص' => $a['staff_name'] ?? '—',
                    'شعبه'  => $a['branch_name'] ?? '—',
                    'شروع'  => fa(substr((string)$a['start_time'], 0, 5)),
                    'پایان' => fa(substr((string)$a['end_time'], 0, 5)),
                    'منبع'  => $a['source'] ?? '—',
                ];
                foreach ($rows as $label => $value): ?>
                    <div class="flex justify-between border-b py-2 text-sm">
                        <span class="text-muted"><?= e($label) ?></span><span><?= e($value) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($allowed !== []): ?>
            <div class="mr-card">
                <div class="mr-card__head"><h2 class="mr-card__title">تغییر وضعیت</h2></div>
                <div class="mr-card__body flex gap-2 flex-wrap">
                    <?php foreach ($allowed as $target): ?>
                        <form method="post" action="<?= url('/admin/appointments/' . $a['id'] . '/status') ?>"
                              <?= in_array($target, ['CANCELLED', 'NO_SHOW'], true) ? 'data-confirm="از تغییر وضعیت مطمئن هستید؟"' : '' ?>>
                            <?= csrf_field() ?>
                            <input type="hidden" name="status" value="<?= e($target) ?>">
                            <button class="mr-btn mr-btn--sm <?= $target === 'COMPLETED' ? 'mr-btn--primary' : 'mr-btn--soft' ?>" type="submit">
                                <?= e($statuses[$target] ?? $target) ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </aside>
</div>
<?php View::endSection();
