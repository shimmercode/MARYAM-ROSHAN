<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array $result @var array $filters @var array $branches */
$rows = $result['data'];
$sum  = static fn (string $k) => array_sum(array_map(static fn ($r) => (float)($r[$k] ?? 0), $rows));
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb">پنل مدیریت / مالی</div>
        <h1>فاکتورها</h1>
        <p><span class="mr-num"><?= fa((int)$result['total']) ?></span> فاکتور ثبت شده است</p>
    </div>
    <a class="mr-btn mr-btn--primary" href="<?= url('/admin/pos') ?>">صندوق فروش</a>
</div>

<section class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
    <?= component('kpi', ['label' => 'جمع این صفحه', 'icon' => '🧾', 'value' => money($sum('total'))]) ?>
    <?= component('kpi', ['label' => 'دریافت‌شده', 'icon' => '✅', 'value' => money($sum('paid_amount'))]) ?>
    <?= component('kpi', ['label' => 'مانده', 'icon' => '⏳', 'value' => money($sum('total') - $sum('paid_amount'))]) ?>
</section>

<form class="mr-card mb-4" method="get" action="<?= url('/admin/invoices') ?>">
    <div class="mr-card__body grid grid-cols-1 md:grid-cols-5 gap-3">
        <div class="mr-field mb-0 col-span-2">
            <label class="mr-label" for="search">جستجو</label>
            <input class="mr-input" id="search" name="search" placeholder="شماره فاکتور یا نام مشتری"
                   value="<?= e($filters['search'] ?? '') ?>">
        </div>
        <div class="mr-field mb-0">
            <label class="mr-label" for="status">وضعیت</label>
            <select class="mr-select" id="status" name="status">
                <option value="">همه</option>
                <?php foreach (['ISSUED' => 'صادر شده', 'PARTIAL' => 'پرداخت جزئی', 'PAID' => 'پرداخت شده', 'REFUNDED' => 'بازپرداخت', 'CANCELLED' => 'لغو شده'] as $k => $lbl): ?>
                    <option value="<?= $k ?>" <?= ($filters['status'] ?? '') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mr-field mb-0">
            <label class="mr-label" for="date_from">از تاریخ</label>
            <input class="mr-input mr-num" id="date_from" name="date_from" type="date" dir="ltr" value="<?= e($filters['date_from'] ?? '') ?>">
        </div>
        <div class="flex items-end gap-2">
            <button class="mr-btn mr-btn--primary flex-1" type="submit">فیلتر</button>
            <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/invoices') ?>">حذف</a>
        </div>
    </div>
</form>

<div class="mr-card">
    <div class="mr-card__body">
        <?php if ($rows === []): ?>
            <?= component('empty', [
                'icon' => '🧾', 'title' => 'فاکتوری یافت نشد',
                'text' => 'اولین فروش خود را از طریق صندوق ثبت کنید.',
                'actionHref' => '/admin/pos', 'actionLabel' => 'صندوق فروش',
            ]) ?>
        <?php else: ?>
            <div class="mr-table__wrap">
                <table class="mr-table">
                    <thead><tr><th>شماره</th><th>تاریخ</th><th>مشتری</th><th>مبلغ کل</th><th>پرداختی</th><th>مانده</th><th>وضعیت</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $i): ?>
                        <tr>
                            <td><a class="mr-num" dir="ltr" href="<?= url('/admin/invoices/' . $i['id']) ?>"><?= e($i['invoice_number']) ?></a></td>
                            <td><?= jdate($i['issue_date'], 'j F Y') ?></td>
                            <td><?= e(($i['first_name'] ?? '') . ' ' . ($i['last_name'] ?? '')) ?></td>
                            <td class="mr-num"><?= money($i['total'], false) ?></td>
                            <td class="mr-num"><?= money($i['paid_amount'], false) ?></td>
                            <td class="mr-num"><?= money((float)$i['total'] - (float)$i['paid_amount'], false) ?></td>
                            <td><?= component('status_badge', ['status' => $i['status']]) ?></td>
                            <td class="mr-table__actions">
                                <a class="mr-iconbtn mr-iconbtn--sm" href="<?= url('/admin/invoices/' . $i['id']) ?>" title="جزئیات" aria-label="جزئیات"><i class="bi bi-eye"></i></a>
                                <a class="mr-iconbtn mr-iconbtn--sm" href="<?= url('/admin/invoices/' . $i['id'] . '/print') ?>" target="_blank" rel="noopener" title="چاپ" aria-label="چاپ"><i class="bi bi-printer"></i></a>
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
