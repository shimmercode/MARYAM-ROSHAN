<?php
/**
 * Appointment / invoice / generic status pill — literal `.bs` mockup
 * vocabulary. Maps every real enum value used across this app
 * (appointments.status, invoices.payment_status, invoices.status,
 * staff.status, customers.tier/status) onto the mockup's exact modifier
 * set: confirmed/pending/completed/paid/active/ok/sent/success/cancelled/
 * low/danger/vip/inactive/closed/new/open.
 *
 * @var string $status
 */
$map = [
    // appointments.status
    'PENDING'     => ['در انتظار تأیید', 'pending'],
    'CONFIRMED'   => ['تأیید شده',       'confirmed'],
    'CHECKED_IN'  => ['حاضر در سالن',    'confirmed'],
    'IN_PROGRESS' => ['در حال انجام',    'open'],
    'COMPLETED'   => ['انجام شد',        'completed'],
    'CANCELLED'   => ['لغو شده',         'cancelled'],
    'NO_SHOW'     => ['عدم حضور',        'danger'],
    // invoices.payment_status
    'PAID'        => ['پرداخت شده',      'paid'],
    'PARTIAL'     => ['پرداخت جزئی',     'pending'],
    'UNPAID'      => ['پرداخت نشده',     'danger'],
    'REFUNDED'    => ['بازپرداخت شده',   'open'],
    // invoices.status
    'ISSUED'      => ['صادر شده',        'new'],
    'DRAFT'       => ['پیش‌نویس',        'pending'],
    // payments.status
    'SUCCESS'     => ['موفق',            'success'],
    'FAILED'      => ['ناموفق',          'danger'],
    // generic
    'ACTIVE'      => ['فعال',            'active'],
    'INACTIVE'    => ['غیرفعال',         'inactive'],
    'ON_LEAVE'    => ['در مرخصی',        'pending'],
    'BLACKLIST'   => ['لیست سیاه',       'danger'],
    'VIP'         => ['VIP',             'vip'],
];
[$label, $variant] = $map[$status ?? ''] ?? [(string)($status ?? '—'), 'inactive'];
?>
<span class="bs <?= e($variant) ?>"><?= e($label) ?></span>
