<?php
/**
 * Shared invoice preview — literal `.invoice-preview` mockup vocabulary.
 * Used by both admin/invoices/show.php (embedded) and
 * admin/invoices/print.php (full printable page) so the two never drift.
 * @var array $invoice  (InvoiceService::findFull output, real DB data only)
 */
$inv = $invoice;
$due = (float)$inv['total'] - (float)$inv['paid_amount'];
$typeLabels = ['SERVICE' => 'خدمت', 'PRODUCT' => 'محصول', 'PACKAGE' => 'بسته', 'OTHER' => 'سایر'];
?>
<div class="invoice-preview">
    <div class="inv-header">
        <div class="inv-brand">
            <div class="inv-logo" aria-hidden="true">م</div>
            <div>
                <h2><?= e(setting('salon_name', 'سالن زیبایی مریم روشن')) ?></h2>
                <p><?= e($inv['branch_name']) ?> — <?= e($inv['branch_address']) ?></p>
                <p dir="ltr"><?= e($inv['branch_phone']) ?></p>
            </div>
        </div>
        <div class="inv-meta">
            <h3>فاکتور فروش</h3>
            <p>شماره: <span dir="ltr"><?= e($inv['invoice_number']) ?></span></p>
            <p><?= jdate($inv['issue_date'], 'j F Y') ?></p>
        </div>
    </div>

    <div class="inv-body">
        <div class="inv-info-grid">
            <div class="inv-info-box">
                <h4>مشخصات مشتری</h4>
                <p><?= e($inv['first_name'] . ' ' . $inv['last_name']) ?></p>
                <span dir="ltr"><?= e($inv['mobile']) ?></span>
                <?php if (!empty($inv['customer_code'])): ?>
                    <br><span>کد مشتری: <span dir="ltr"><?= e($inv['customer_code']) ?></span></span>
                <?php endif; ?>
            </div>
            <div class="inv-info-box">
                <h4>جزئیات فاکتور</h4>
                <p><?= component('status_badge', ['status' => $inv['payment_status'] ?? $inv['status']]) ?></p>
                <?php if (!empty($inv['staff_name'])): ?>
                    <span>متخصص: <?= e($inv['staff_name']) ?></span><br>
                <?php endif; ?>
                <span>صادرکننده: <?= e($inv['created_by_name'] ?? '—') ?></span>
            </div>
        </div>

        <table class="inv-table">
            <thead>
                <tr><th>#</th><th>شرح</th><th>تعداد</th><th>مبلغ واحد</th><th>تخفیف</th><th>جمع</th></tr>
            </thead>
            <tbody>
            <?php foreach ($inv['items'] as $n => $it): ?>
                <tr>
                    <td class="mr-num"><?= fa($n + 1) ?></td>
                    <td>
                        <div class="item-name"><?= e($it['title']) ?></div>
                        <div class="item-cat"><?= e($typeLabels[$it['item_type']] ?? $it['item_type']) ?><?= !empty($it['staff_name']) ? ' · ' . e($it['staff_name']) : '' ?></div>
                    </td>
                    <td class="mr-num"><?= fa((float)$it['quantity']) ?></td>
                    <td class="mr-num"><?= money($it['unit_price'], false) ?></td>
                    <td class="mr-num"><?= money($it['discount'], false) ?></td>
                    <td class="mr-num"><?= money($it['total'], false) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="inv-totals">
            <div class="inv-totals-box">
                <div class="inv-total-row"><span>جمع اقلام</span><span class="mr-num"><?= money($inv['subtotal']) ?></span></div>
                <div class="inv-total-row discount"><span>تخفیف</span><span class="mr-num">−<?= money($inv['discount_amount']) ?></span></div>
                <div class="inv-total-row"><span>مالیات</span><span class="mr-num"><?= money($inv['tax_amount']) ?></span></div>
                <div class="inv-total-row grand"><span>مبلغ نهایی</span><span class="mr-num"><?= money($inv['total']) ?></span></div>
                <div class="inv-total-row"><span>پرداخت‌شده</span><span class="mr-num"><?= money($inv['paid_amount']) ?></span></div>
                <div class="inv-total-row" style="<?= $due > 0 ? 'color:var(--danger);font-weight:700' : '' ?>"><span>مانده</span><span class="mr-num"><?= money($due) ?></span></div>
            </div>
        </div>
    </div>

    <div class="inv-footer">
        <div class="inv-footer-box">
            <i class="bi bi-telephone" aria-hidden="true"></i>
            <strong dir="ltr"><?= e($inv['branch_phone']) ?></strong>
            <span>تماس با شعبه</span>
        </div>
        <div class="inv-footer-box">
            <i class="bi bi-geo-alt" aria-hidden="true"></i>
            <strong><?= e($inv['branch_name']) ?></strong>
            <span><?= e($inv['branch_address']) ?></span>
        </div>
        <div class="inv-footer-box">
            <i class="bi bi-shield-check" aria-hidden="true"></i>
            <strong><?= component('status_badge', ['status' => $inv['payment_status'] ?? $inv['status']]) ?></strong>
            <span>وضعیت پرداخت</span>
        </div>
    </div>

    <div class="inv-barcode"><?= e($inv['invoice_number']) ?></div>
    <div class="inv-thanks"><?= e(setting('invoice_footer', 'از انتخاب شما سپاسگزاریم. سلامتی و زیبایی شما افتخار ماست.')) ?></div>
</div>
