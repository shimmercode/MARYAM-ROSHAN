<?php
/**
 * Printable invoice — full `.invoice-preview` mockup vocabulary, verbatim.
 * Wrapped by ExportService::printableHtml(), so this file is self-contained
 * (its own <style>, no layout) and shares markup with admin/invoices/show.php
 * via partials/admin/invoice_preview.php.
 * @var array $invoice
 */
?>
<style>
  .invoice-preview{background:#fff;color:#1f2340;padding:0;direction:rtl;font-family:Vazirmatn,Tahoma,sans-serif}
  .inv-header{display:flex;justify-content:space-between;align-items:flex-start;padding:28px 32px;background:linear-gradient(135deg,#384381,#4690bf);color:#fff;border-radius:18px 18px 0 0;flex-wrap:wrap;gap:16px}
  .inv-brand{display:flex;align-items:center;gap:14px}
  .inv-logo{width:56px;height:56px;border-radius:14px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:26px;border:1px solid rgba(255,255,255,.3)}
  .inv-brand h2{font-size:20px;font-weight:800;margin:0 0 2px}
  .inv-brand p{font-size:11px;opacity:.85;margin:0}
  .inv-meta{text-align:left}
  .inv-meta h3{font-size:22px;font-weight:800;margin:0 0 4px}
  .inv-meta p{font-size:11px;opacity:.85;margin:0 0 2px}
  .inv-body{padding:24px 32px}
  .inv-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px}
  .inv-info-box{background:#f7f8fc;border-radius:12px;padding:16px}
  .inv-info-box h4{font-size:11px;color:#6b7190;font-weight:600;margin:0 0 8px;text-transform:uppercase}
  .inv-info-box p{font-size:13px;font-weight:600;margin:0 0 3px;color:#1f2340}
  .inv-info-box span{font-size:11px;color:#6b7190}
  .inv-table{width:100%;border-collapse:collapse;margin-bottom:20px}
  .inv-table thead th{background:#384381;color:#fff;padding:12px 14px;font-size:12px;font-weight:600;text-align:right}
  .inv-table thead th:first-child{border-radius:0 10px 10px 0}
  .inv-table thead th:last-child{border-radius:10px 0 0 10px}
  .inv-table tbody td{padding:12px 14px;border-bottom:1px solid #e7e9f3;font-size:12px}
  .inv-table .item-name{font-weight:600;color:#1f2340}
  .inv-table .item-cat{font-size:10px;color:#6b7190}
  .inv-totals{display:flex;justify-content:flex-start;margin-bottom:24px}
  .inv-totals-box{width:280px;background:#f7f8fc;border-radius:12px;padding:16px}
  .inv-total-row{display:flex;justify-content:space-between;padding:6px 0;font-size:12px;color:#6b7190}
  .inv-total-row.discount{color:#d9534f}
  .inv-total-row.grand{font-size:16px;font-weight:800;color:#384381;border-top:2px solid #384381;margin-top:8px;padding-top:10px}
  .inv-footer{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;padding:20px 32px;border-top:1px solid #e7e9f3;background:#fafbff;border-radius:0 0 18px 18px}
  .inv-footer-box{text-align:center;padding:12px;background:#fff;border-radius:10px;border:1px solid #e7e9f3}
  .inv-footer-box i{font-size:20px;color:#4690bf;margin-bottom:4px;display:block}
  .inv-footer-box strong{font-size:12px;display:block;margin-bottom:2px;color:#1f2340}
  .inv-footer-box span{font-size:10px;color:#6b7190}
  .inv-barcode{text-align:center;padding:16px 32px;border-top:1px dashed #e7e9f3;font-family:monospace;font-size:11px;color:#6b7190;letter-spacing:3px}
  .inv-thanks{text-align:center;padding:12px 32px 20px;font-size:12px;color:#4690bf;font-weight:600}
  .bs{display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border-radius:20px;font-size:10px;font-weight:600}
  .bs.confirmed,.bs.new,.bs.open{background:rgba(70,144,191,.12);color:#4690bf}
  .bs.pending{background:rgba(224,164,58,.12);color:#e0a43a}
  .bs.completed,.bs.paid,.bs.active,.bs.ok,.bs.sent,.bs.success{background:rgba(46,158,107,.12);color:#2e9e6b}
  .bs.cancelled,.bs.low,.bs.danger{background:rgba(217,83,79,.12);color:#d9534f}
  .bs.inactive,.bs.closed{background:rgba(107,113,144,.12);color:#6b7190}
  .mr-num{font-variant-numeric:tabular-nums}
  @media print{.no-print{display:none}}
</style>
<?= partial('admin/invoice_preview', ['invoice' => $invoice]) ?>
