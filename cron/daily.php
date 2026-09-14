<?php
declare(strict_types=1);

/**
 * Nightly maintenance — run once a day, after closing time.
 *
 *   30 2 * * *  /usr/bin/php /path/to/cron/daily.php >> /dev/null 2>&1
 *
 * Each task is isolated: one failure is logged and the run continues, so a
 * broken SMS provider never stops the analytics rollup.
 */

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\AnalyticsService;
use App\Services\AutomationService;
use App\Services\BackupService;
use App\Services\ContractAlertService;
use App\Services\NotificationService;
use App\Services\SettingsService;

$db        = Database::instance();
$analytics = new AnalyticsService();
$yesterday = date('Y-m-d', strtotime('-1 day'));
$tasks     = [];

$tasks[] = cron_task('هشدار انقضای قراردادها', static function (): string {
    return sprintf('%d قرارداد نزدیک به انقضا شناسایی شد.', (new ContractAlertService())->process(10));
});

/* ---------------------------------------------------------- analytics -- */

$tasks[] = cron_task('تجمیع آمار روز گذشته', static function () use ($analytics, $db, $yesterday): string {
    $analytics->aggregateDaily($yesterday);
    foreach ($db->select("SELECT id FROM branches WHERE deleted_at IS NULL AND status='ACTIVE'") as $branch) {
        $analytics->aggregateDaily($yesterday, (int)$branch['id']);
    }
    return 'آمار ' . $yesterday . ' ذخیره شد.';
});

$tasks[] = cron_task('محاسبه امتیاز RFM مشتریان', static function () use ($analytics): string {
    return sprintf('%d مشتری امتیازدهی شد.', $analytics->computeRfm());
});

/* -------------------------------------------------- no-show detection -- */

$tasks[] = cron_task('علامت‌گذاری نوبت‌های بدون مراجعه', static function () use ($db, $yesterday): string {
    // Anything still pending/confirmed the morning after its slot is a no-show.
    $affected = $db->execute(
        "UPDATE appointments
         SET status = 'NO_SHOW', updated_at = NOW()
         WHERE status IN ('PENDING','CONFIRMED')
           AND appointment_date <= :d",
        ['d' => $yesterday]
    );
    return sprintf('%d نوبت به «عدم مراجعه» تغییر کرد.', $affected);
});

/* -------------------------------------------------------- automations -- */

$tasks[] = cron_task('اجرای اتوماسیون تولد مشتریان', static function () use ($db): string {
    $rows = $db->select(
        "SELECT id, first_name, last_name, mobile, email
         FROM customers
         WHERE deleted_at IS NULL AND status = 'ACTIVE' AND birth_date IS NOT NULL
           AND DATE_FORMAT(birth_date, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')
         LIMIT 200"
    );
    foreach ($rows as $customer) {
        AutomationService::fire('birthday', [
            'customer_id' => (int)$customer['id'],
            'first_name'  => (string)$customer['first_name'],
            'last_name'   => (string)$customer['last_name'],
            'mobile'      => (string)$customer['mobile'],
            'email'       => (string)($customer['email'] ?? ''),
        ]);
    }
    return sprintf('%d تولد پردازش شد.', count($rows));
});

$tasks[] = cron_task('اجرای اتوماسیون مشتریان غیرفعال', static function () use ($db): string {
    $days = (int)SettingsService::get('inactive_days', 90);
    $rows = $db->select(
        "SELECT id, first_name, last_name, mobile, email, last_visit_at
         FROM customers
         WHERE deleted_at IS NULL AND status = 'ACTIVE' AND visits_count > 0
           AND last_visit_at IS NOT NULL
           AND DATE(last_visit_at) = DATE_SUB(CURDATE(), INTERVAL :d DAY)
         LIMIT 200",
        ['d' => $days]
    );
    foreach ($rows as $customer) {
        AutomationService::fire('customer_inactive', [
            'customer_id' => (int)$customer['id'],
            'first_name'  => (string)$customer['first_name'],
            'last_name'   => (string)$customer['last_name'],
            'mobile'      => (string)$customer['mobile'],
            'email'       => (string)($customer['email'] ?? ''),
            'days'        => $days,
        ]);
    }
    return sprintf('%d مشتری غیرفعال اطلاع‌رسانی شد.', count($rows));
});

/* ------------------------------------------------------ stock alerts -- */

$tasks[] = cron_task('هشدار موجودی کم انبار', static function () use ($db): string {
    $low = $db->select(
        'SELECT p.name, p.unit, COALESCE(i.quantity,0) AS quantity, p.reorder_level
         FROM products p
         LEFT JOIN inventory i ON i.product_id = p.id
         WHERE p.deleted_at IS NULL AND p.status = \'ACTIVE\'
           AND COALESCE(i.quantity,0) <= p.reorder_level
         LIMIT 50'
    );
    if ($low === []) {
        return 'همه کالاها بالای نقطه سفارش هستند.';
    }

    $names = array_slice(array_column($low, 'name'), 0, 8);
    NotificationService::notifyByPermission(
        'inventory.view',
        'هشدار موجودی انبار',
        sprintf('%d کالا به نقطه سفارش رسیده‌اند: %s', count($low), implode('، ', $names)),
        'WARNING',
        '/admin/inventory'
    );
    return sprintf('%d کالای کم‌موجود گزارش شد.', count($low));
});

/* ------------------------------------------------------------ backups -- */

$tasks[] = cron_task('پشتیبان‌گیری پایگاه داده', static function (): string {
    $result = BackupService::create();
    $pruned = BackupService::prune(10);
    return sprintf('%s ساخته شد، %d نسخه قدیمی حذف شد.', (string)($result['name'] ?? ''), $pruned);
});

/* ------------------------------------------------------------ cleanup -- */

$tasks[] = cron_task('پاک‌سازی داده‌های موقت', static function () use ($db): int {
    $removed = 0;
    $removed += $db->execute('DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
    $removed += $db->execute('DELETE FROM sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY)');
    $removed += $db->execute('DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 365 DAY)');

    foreach ((glob(MR_ROOT . '/storage/cache/*') ?: []) as $file) {
        if (is_file($file) && filemtime($file) < time() - 86400) {
            @unlink($file);
            $removed++;
        }
    }
    return $removed;
});

cron_finish($tasks);
