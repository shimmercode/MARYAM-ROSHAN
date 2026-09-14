<?php
declare(strict_types=1);

/**
 * Appointment reminders — run hourly.
 *
 *   0 * * * *  /usr/bin/php /path/to/cron/reminders.php >> /dev/null 2>&1
 *
 * Sends the APPOINTMENT_REMINDER template to every customer whose appointment
 * starts inside the configured window. A reminder_sent_at stamp guarantees a
 * customer is never messaged twice for the same appointment, even if cron runs
 * more often than expected or a previous run died halfway through.
 */

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\NotificationService;
use App\Services\SettingsService;

$tasks = [];

$tasks[] = cron_task('ارسال یادآوری نوبت‌ها', static function (): string {
    $db    = Database::instance();
    $hours = (int)SettingsService::get('reminder_hours_before', 24);
    if ($hours <= 0) {
        return 'یادآوری غیرفعال است.';
    }

    $salon = (string)SettingsService::get('salon_name', 'سالن زیبایی مریم روشن');

    // Appointments starting within the window that have not been reminded yet.
    $rows = $db->select(
        "SELECT a.id, a.appointment_date, a.start_time,
                c.id AS customer_id, c.first_name, c.last_name, c.mobile, c.marketing_opt_in,
                b.name AS branch_name
         FROM appointments a
         JOIN customers c ON c.id = a.customer_id
         LEFT JOIN branches b ON b.id = a.branch_id
         WHERE a.status IN ('PENDING','CONFIRMED')
           AND a.reminder_sent_at IS NULL
           AND c.mobile IS NOT NULL AND c.mobile <> ''
           AND TIMESTAMP(a.appointment_date, a.start_time)
               BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL :h HOUR)
         ORDER BY a.appointment_date, a.start_time
         LIMIT 300",
        ['h' => $hours]
    );

    if ($rows === []) {
        return 'نوبتی برای یادآوری وجود ندارد.';
    }

    $sent = 0;
    $failed = 0;

    foreach ($rows as $row) {
        $result = NotificationService::sendTemplate(
            'APPOINTMENT_REMINDER',
            (string)$row['mobile'],
            [
                'name'  => trim($row['first_name'] . ' ' . $row['last_name']),
                'first' => (string)$row['first_name'],
                'salon' => $salon,
                'date'  => \App\Helpers\Jalali::format((string)$row['appointment_date'], 'j F Y'),
                'time'  => substr((string)$row['start_time'], 0, 5),
                'branch' => (string)($row['branch_name'] ?? ''),
            ],
            (int)$row['customer_id'],
            'appointment',
            (int)$row['id']
        );

        // Stamp regardless of provider success: a failed send is logged in
        // sms_logs and must not cause an endless retry loop every hour.
        $db->execute(
            'UPDATE appointments SET reminder_sent_at = NOW() WHERE id = :i',
            ['i' => (int)$row['id']]
        );

        ($result['success'] ?? false) ? $sent++ : $failed++;
    }

    return sprintf('%d پیام ارسال شد، %d ناموفق.', $sent, $failed);
});

cron_finish($tasks);
