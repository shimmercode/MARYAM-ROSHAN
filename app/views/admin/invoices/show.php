<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array $invoice @var array $methods */
$inv = $invoice;
$due = (float)$inv['total'] - (float)$inv['paid_amount'];
?>
<div class="ph mr-page-head">
    <div>
        <div class="mr-breadcrumb"><a href="<?= url('/admin/invoices') ?>">فاکتورها</a> / <?= e($inv['invoice_number']) ?></div>
        <h1>فاکتور <span class="mr-num" dir="ltr"><?= e($inv['invoice_number']) ?></span></h1>
        <p><?= jdate($inv['issue_date'], 'j F Y') ?> · <?= e($inv['branch_name']) ?></p>
    </div>
    <div class="pa flex gap-2 flex-wrap">
        <?= component('status_badge', ['status' => $inv['payment_status'] ?? $inv['status']]) ?>
        <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/invoices/' . $inv['id'] . '/print') ?>" target="_blank" rel="noopener">
            <i class="bi bi-printer"></i> چاپ
        </a>
    </div>
</div>

<div class="mr-card mb-4" style="padding:0;overflow:hidden">
    <?= partial('admin/invoice_preview', ['invoice' => $inv]) ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="col-span-2">
        <div class="mr-card">
            <div class="mr-card__head"><h2 class="mr-card__title">پرداخت‌ها</h2></div>
            <div class="mr-card__body">
                <?php if ($inv['payments'] === []): ?>
                    <?= component('empty', ['icon' => '💳', 'title' => 'پرداختی ثبت نشده است']) ?>
                <?php else: ?>
                    <div class="mr-table__wrap">
                        <table class="mr-table">
                            <thead><tr><th>شماره</th><th>نوع</th><th>روش</th><th>مبلغ</th><th>تاریخ</th><th>وضعیت</th></tr></thead>
                            <tbody>
                            <?php foreach ($inv['payments'] as $p): ?>
                                <tr>
                                    <td class="mr-num text-xs" dir="ltr"><?= e($p['payment_number']) ?></td>
                                    <td><?= $p['type'] === 'REFUND' ? '<span class="mr-badge mr-badge--danger">بازپرداخت</span>' : 'دریافت' ?></td>
                                    <td><?= e($methods[$p['method_slug']] ?? $p['method_slug']) ?></td>
                                    <td class="mr-num"><?= money($p['amount'], false) ?></td>
                                    <td class="text-xs"><?= jdate($p['paid_at'], 'j F Y — H:i') ?></td>
                                    <td><?= component('status_badge', ['status' => $p['status']]) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <aside>
        <div class="mr-card mb-4">
            <div class="mr-card__head"><h2 class="mr-card__title">مشتری</h2></div>
            <div class="mr-card__body">
                <a class="font-medium block" href="<?= url('/admin/customers/' . $inv['customer_id']) ?>">
                    <?= e($inv['first_name'] . ' ' . $inv['last_name']) ?>
                </a>
                <a class="text-sm mr-num" dir="ltr" href="tel:<?= e($inv['mobile']) ?>"><?= fa($inv['mobile']) ?></a>
            </div>
        </div>

        <div class="mr-card mb-4">
            <div class="mr-card__head"><h2 class="mr-card__title">جمع‌بندی مالی</h2></div>
            <div class="mr-card__body">
                <?php foreach ([
                    'جمع اقلام'   => $inv['subtotal'],
                    'تخفیف'       => $inv['discount_amount'],
                    'مالیات'      => $inv['tax_amount'],
                    'مبلغ نهایی'  => $inv['total'],
                    'پرداخت‌شده'  => $inv['paid_amount'],
                ] as $label => $value): ?>
                    <div class="flex justify-between border-b py-2 text-sm">
                        <span class="text-muted"><?= e($label) ?></span>
                        <span class="mr-num"><?= money($value) ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="flex justify-between py-3">
                    <strong>مانده</strong>
                    <strong class="mr-num <?= $due > 0 ? 'text-danger' : 'text-success' ?>"><?= money($due) ?></strong>
                </div>
            </div>
        </div>

        <?php if ($due > 0 && $inv['status'] !== 'CANCELLED'): ?>
            <div class="mr-card mb-4">
                <div class="mr-card__head"><h2 class="mr-card__title">ثبت پرداخت</h2></div>
                <div class="mr-card__body">
                    <form method="post" action="<?= url('/admin/invoices/' . $inv['id'] . '/pay') ?>">
                        <?= csrf_field() ?>
                        <div class="mr-field">
                            <label class="mr-label" for="amount">مبلغ <span class="req">*</span></label>
                            <input class="mr-input mr-num" id="amount" name="amount" type="number" min="1"
                                   step="1000" value="<?= (int)$due ?>" required>
                        </div>
                        <div class="mr-field">
                            <label class="mr-label" for="method">روش پرداخت <span class="req">*</span></label>
                            <select class="mr-select" id="method" name="method" required>
                                <?php foreach ($methods as $k => $lbl): ?>
                                    <option value="<?= e($k) ?>"><?= e($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mr-field">
                            <label class="mr-label" for="reference">شماره پیگیری</label>
                            <input class="mr-input mr-num" id="reference" name="reference" dir="ltr">
                        </div>
                        <button class="mr-btn mr-btn--primary mr-btn--block" type="submit">ثبت پرداخت</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if ((float)$inv['paid_amount'] > 0 && $inv['status'] !== 'CANCELLED'): ?>
            <div class="mr-card">
                <div class="mr-card__head"><h2 class="mr-card__title">بازپرداخت</h2></div>
                <div class="mr-card__body">
                    <p class="mr-help mb-3">بازپرداخت، تراکنش جبرانی ایجاد می‌کند و سابقه مالی حذف نمی‌شود.</p>
                    <form method="post" action="<?= url('/admin/invoices/' . $inv['id'] . '/refund') ?>"
                          data-confirm="از ثبت بازپرداخت مطمئن هستید؟">
                        <?= csrf_field() ?>
                        <div class="mr-field">
                            <label class="mr-label" for="refund_amount">مبلغ بازپرداخت</label>
                            <input class="mr-input mr-num" id="refund_amount" name="amount" type="number"
                                   min="1" max="<?= (int)$inv['paid_amount'] ?>" step="1000" required>
                        </div>
                        <div class="mr-field">
                            <label class="mr-label" for="reason">علت</label>
                            <input class="mr-input" id="reason" name="reason">
                        </div>
                        <button class="mr-btn mr-btn--danger mr-btn--block" type="submit">ثبت بازپرداخت</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </aside>
</div>
<?php View::endSection();
