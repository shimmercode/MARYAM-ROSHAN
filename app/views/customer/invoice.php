<?php
use App\Core\View;
View::extend('portal');
View::section('content');
/** @var array $invoice @var array $items @var array $payments */
?>
<div class="mr-card">
    <div class="mr-card__body">
        <div class="flex justify-between items-start mb-4">
            <div>
                <div class="text-xs text-muted">شماره فاکتور</div>
                <div class="text-lg font-bold mr-num" dir="ltr"><?= e($invoice['invoice_number']) ?></div>
                <div class="text-sm text-muted"><?= jdate($invoice['issue_date'], 'j F Y') ?></div>
            </div>
            <?= component('status_badge', ['status' => $invoice['status']]) ?>
        </div>

        <div class="mr-table__wrap mb-4">
            <table class="mr-table">
                <thead><tr><th>شرح</th><th>تعداد</th><th>مبلغ واحد</th><th>جمع</th></tr></thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td><?= e($it['description']) ?></td>
                        <td class="mr-num"><?= fa((float)$it['quantity']) ?></td>
                        <td class="mr-num"><?= money($it['unit_price'], false) ?></td>
                        <td class="mr-num"><?= money($it['total'], false) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
        $lines = [
            'جمع کل'      => $invoice['subtotal'],
            'تخفیف'       => $invoice['discount_amount'],
            'مالیات'      => $invoice['tax_amount'],
            'مبلغ نهایی'  => $invoice['total'],
            'پرداخت‌شده'  => $invoice['paid_amount'],
        ];
        foreach ($lines as $label => $value): ?>
            <div class="flex justify-between border-b py-2 text-sm">
                <span class="text-muted"><?= e($label) ?></span>
                <span class="mr-num font-medium"><?= money($value) ?></span>
            </div>
        <?php endforeach; ?>
        <div class="flex justify-between py-3">
            <strong>مانده قابل پرداخت</strong>
            <strong class="mr-num text-danger"><?= money((float)$invoice['total'] - (float)$invoice['paid_amount']) ?></strong>
        </div>

        <?php if ($payments !== []): ?>
            <h2 class="text-md mt-4 mb-2">پرداخت‌ها</h2>
            <?php foreach ($payments as $p): ?>
                <div class="slot-row">
                    <div class="slot-row__body">
                        <strong class="mr-num"><?= money($p['amount']) ?></strong>
                        <small><?= jdate($p['paid_at'], 'j F Y — H:i') ?> · <?= e($p['method']) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="flex gap-2 mt-4 mr-no-print">
            <a class="mr-btn mr-btn--ghost flex-1" href="<?= url('/customer/invoices') ?>">بازگشت</a>
            <button class="mr-btn mr-btn--soft flex-1" type="button" onclick="window.print()">چاپ</button>
        </div>
    </div>
</div>
<?php View::endSection();
