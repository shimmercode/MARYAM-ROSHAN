<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array $result @var array $filters @var array $statuses @var array $counts @var array $branches @var array $staff */
$rows = $result['data'];
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb">پنل مدیریت / نوبت‌ها</div>
        <h1>نوبت‌ها</h1>
        <p><span class="mr-num"><?= fa((int)$result['total']) ?></span> نوبت مطابق فیلتر فعلی</p>
    </div>
    <div class="flex gap-2 flex-wrap">
        <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/appointments/calendar') ?>">نمای تقویم</a>
        <a class="mr-btn mr-btn--primary" href="<?= url('/admin/appointments/create') ?>">رزرو نوبت</a>
    </div>
</div>

<section class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <?php foreach (['PENDING', 'CONFIRMED', 'COMPLETED', 'NO_SHOW'] as $key): ?>
        <?= component('kpi', [
            'label' => $statuses[$key] ?? $key,
            'value' => fa((int)($counts[$key] ?? 0)),
            'icon'  => ['PENDING' => '⏳', 'CONFIRMED' => '✅', 'COMPLETED' => '🎉', 'NO_SHOW' => '🚫'][$key],
            'href'  => '/admin/appointments?status=' . $key,
        ]) ?>
    <?php endforeach; ?>
</section>

<form class="mr-card mb-4" method="get" action="<?= url('/admin/appointments') ?>">
    <div class="mr-card__body grid grid-cols-1 md:grid-cols-6 gap-3">
        <div class="mr-field mb-0 col-span-2">
            <label class="mr-label" for="search">جستجو</label>
            <input class="mr-input" id="search" name="search" placeholder="کد نوبت، نام یا موبایل مشتری" value="<?= e($filters['search']) ?>">
        </div>
        <div class="mr-field mb-0">
            <label class="mr-label" for="status">وضعیت</label>
            <select class="mr-select" id="status" name="status">
                <option value="">همه</option>
                <?php foreach ($statuses as $k => $lbl): ?>
                    <option value="<?= e($k) ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mr-field mb-0">
            <label class="mr-label" for="staff_id">متخصص</label>
            <select class="mr-select" id="staff_id" name="staff_id">
                <option value="">همه</option>
                <?php foreach ($staff as $s): ?>
                    <option value="<?= (int)$s['id'] ?>" <?= (int)$filters['staff_id'] === (int)$s['id'] ? 'selected' : '' ?>>
                        <?= e($s['first_name'] . ' ' . $s['last_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mr-field mb-0">
            <label class="mr-label" for="date_from">از تاریخ</label>
            <input class="mr-input mr-num" id="date_from" name="date_from" type="date" dir="ltr" value="<?= e($filters['date_from']) ?>">
        </div>
        <div class="flex items-end gap-2">
            <button class="mr-btn mr-btn--primary flex-1" type="submit">فیلتر</button>
            <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/appointments') ?>">حذف</a>
        </div>
    </div>
</form>

<div class="mr-card">
    <div class="mr-card__body">
        <?php if ($rows === []): ?>
            <?= component('empty', [
                'icon' => '🗓', 'title' => 'نوبتی یافت نشد',
                'text' => 'با شرایط انتخابی نوبتی ثبت نشده است.',
                'actionHref' => '/admin/appointments/create', 'actionLabel' => 'رزرو نوبت جدید',
            ]) ?>
        <?php else: ?>
            <div class="mr-table__wrap">
                <table class="mr-table">
                    <thead><tr><th>کد</th><th>تاریخ</th><th>ساعت</th><th>مشتری</th><th>متخصص</th><th>خدمات</th><th>مبلغ</th><th>وضعیت</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $a): ?>
                        <tr>
                            <td class="text-xs mr-num"><a href="<?= url('/admin/appointments/' . $a['id']) ?>"><?= e($a['code']) ?></a></td>
                            <td><?= jdate($a['appointment_date'], 'j F Y') ?></td>
                            <td class="mr-num"><?= fa(substr((string)$a['start_time'], 0, 5)) ?></td>
                            <td><?= e(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?></td>
                            <td><?= e($a['staff_name'] ?? '—') ?></td>
                            <td class="text-xs"><?= e($a['services'] ?? '—') ?></td>
                            <td class="mr-num"><?= money($a['total_price'], false) ?></td>
                            <td><?= component('status_badge', ['status' => $a['status']]) ?></td>
                            <td class="mr-table__actions">
                                <a class="mr-iconbtn mr-iconbtn--sm" href="<?= url('/admin/appointments/' . $a['id']) ?>" title="جزئیات" aria-label="جزئیات"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= component('pagination', ['page' => $result['page'], 'lastPage' => $result['last_page'], 'query' => $filters]) ?>
        <?php endif; ?>
    </div>
</div>
<?php View::endSection();
