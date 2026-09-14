<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Exceptions\BusinessException;
use App\Core\Logger;
use PDO;
use PDOException;
use Throwable;

/**
 * Installation logic kept out of the controller: requirement checks, .env
 * writing, schema/seed execution and the self-disabling lock file.
 */
final class InstallerService
{
    /** @return array<int, array{label:string, ok:bool, hint:string, required:bool}> */
    public function requirements(): array
    {
        $root = dirname(__DIR__, 2);
        $checks = [];

        $checks[] = [
            'label'    => 'نسخه PHP 8.2 یا بالاتر',
            'ok'       => version_compare(PHP_VERSION, '8.2.0', '>='),
            'hint'     => 'نسخه فعلی: ' . PHP_VERSION,
            'required' => true,
        ];

        foreach (['pdo_mysql' => 'اتصال به MySQL', 'mbstring' => 'پردازش متن فارسی', 'json' => 'پردازش JSON', 'fileinfo' => 'اعتبارسنجی فایل آپلودی', 'openssl' => 'رمزنگاری'] as $ext => $why) {
            $checks[] = [
                'label'    => 'افزونه ' . $ext,
                'ok'       => extension_loaded($ext),
                'hint'     => $why,
                'required' => true,
            ];
        }
        $checks[] = [
            'label'    => 'افزونه gd (پردازش تصویر)',
            'ok'       => extension_loaded('gd'),
            'hint'     => 'برای ساخت تصاویر بندانگشتی گالری توصیه می‌شود',
            'required' => false,
        ];

        foreach ([
            'storage'           => $root . '/storage',
            'storage/logs'      => $root . '/storage/logs',
            'storage/cache'     => $root . '/storage/cache',
            'storage/backups'   => $root . '/storage/backups',
            'public/uploads'    => $root . '/public/uploads',
        ] as $label => $path) {
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }
            $checks[] = [
                'label'    => 'قابلیت نوشتن در ' . $label,
                'ok'       => is_dir($path) && is_writable($path),
                'hint'     => 'دسترسی 775 لازم است',
                'required' => true,
            ];
        }

        $envOk = is_writable($root . '/.env') || (!is_file($root . '/.env') && is_writable($root));
        $checks[] = [
            'label'    => 'قابلیت ساخت فایل .env',
            'ok'       => $envOk,
            'hint'     => 'در ریشه پروژه باید قابل نوشتن باشد',
            'required' => true,
        ];

        $checks[] = [
            'label'    => 'فعال بودن mod_rewrite',
            'ok'       => !function_exists('apache_get_modules') || in_array('mod_rewrite', apache_get_modules(), true),
            'hint'     => 'برای مسیریابی آدرس‌ها ضروری است',
            'required' => false,
        ];

        return $checks;
    }

    public function requirementsPassed(): bool
    {
        foreach ($this->requirements() as $c) {
            if ($c['required'] && !$c['ok']) {
                return false;
            }
        }
        return true;
    }

    /** Verify credentials and create the database if it does not exist. */
    public function testConnection(array $cfg): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $cfg['host'], $cfg['port']);
        try {
            $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            throw new BusinessException(
                'اتصال به پایگاه داده برقرار نشد: ' . $e->getMessage(),
                'DB_CONNECTION_FAILED',
                422
            );
        }

        $name = (string)$cfg['database'];
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new BusinessException('نام پایگاه داده تنها می‌تواند شامل حروف، عدد و زیرخط باشد.', 'INVALID_DB_NAME', 422);
        }
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$name}`");

        return $pdo;
    }

    /** Write (or update) the .env file. Never committed to git. */
    public function writeEnv(array $values): void
    {
        $root = dirname(__DIR__, 2);
        $path = $root . '/.env';

        $existing = [];
        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $existing[trim($k)] = trim($v);
            }
        }

        $merged = array_merge([
            'APP_NAME'  => 'سالن زیبایی مریم روشن',
            'APP_ENV'   => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL'   => 'http://localhost',
            'APP_KEY'   => bin2hex(random_bytes(32)),
            'DB_DRIVER' => 'mysql',
        ], $existing, $values);

        $lines = ['# تولید شده توسط نصاب — این فایل را در گیت قرار ندهید.'];
        foreach ($merged as $k => $v) {
            $needsQuotes = preg_match('/[\s#"\']/', (string)$v) === 1;
            $lines[] = $k . '=' . ($needsQuotes ? '"' . str_replace('"', '\"', (string)$v) . '"' : $v);
        }

        if (@file_put_contents($path, implode("\n", $lines) . "\n", LOCK_EX) === false) {
            throw new BusinessException('نوشتن فایل .env ممکن نشد. دسترسی پوشه ریشه را بررسی کنید.', 'ENV_WRITE_FAILED', 500);
        }
        @chmod($path, 0640);
    }

    /** Execute schema.sql then seeds.sql. */
    public function migrate(PDO $pdo, bool $withSeeds = true): array
    {
        $root  = dirname(__DIR__, 2);
        $stats = ['tables' => 0, 'statements' => 0];

        $this->runSqlFile($pdo, $root . '/database/schema.sql', $stats);
        if ($withSeeds) {
            $this->runSqlFile($pdo, $root . '/database/seeds.sql', $stats);
        }

        $stats['tables'] = (int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
        return $stats;
    }

    private function runSqlFile(PDO $pdo, string $file, array &$stats): void
    {
        if (!is_file($file)) {
            throw new BusinessException('فایل SQL یافت نشد: ' . basename($file), 'SQL_FILE_MISSING', 500);
        }
        $sql = (string)file_get_contents($file);

        foreach ($this->splitStatements($sql) as $statement) {
            try {
                $pdo->exec($statement);
                $stats['statements']++;
            } catch (PDOException $e) {
                Logger::error('Install SQL failed', ['file' => basename($file), 'error' => $e->getMessage()]);
                throw new BusinessException(
                    'اجرای دستور پایگاه داده با خطا مواجه شد: ' . $e->getMessage(),
                    'SQL_EXECUTION_FAILED',
                    500
                );
            }
        }
    }

    /** Split a SQL file on semicolons while respecting quotes and comments. */
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $current    = '';
        $inSingle   = false;
        $inDouble   = false;
        $len        = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $ch   = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if (!$inSingle && !$inDouble && $ch === '-' && $next === '-') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }
            if (!$inSingle && !$inDouble && $ch === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i);
                $i   = $end === false ? $len : $end + 1;
                continue;
            }
            if ($ch === "'" && !$inDouble && ($sql[$i - 1] ?? '') !== '\\') {
                $inSingle = !$inSingle;
            } elseif ($ch === '"' && !$inSingle && ($sql[$i - 1] ?? '') !== '\\') {
                $inDouble = !$inDouble;
            }

            if ($ch === ';' && !$inSingle && !$inDouble) {
                $trimmed = trim($current);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $current = '';
                continue;
            }
            $current .= $ch;
        }

        $trimmed = trim($current);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }
        return $statements;
    }

    /** Create the first SUPER_ADMIN account. */
    public function createAdmin(PDO $pdo, array $data): int
    {
        $hash = password_hash((string)$data['password'], PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $pdo->prepare(
            'INSERT INTO users (first_name, last_name, mobile, email, password_hash, status, branch_id, password_changed_at)
             VALUES (:f, :l, :m, :e, :p, \'ACTIVE\', 1, NOW())
             ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), status = \'ACTIVE\''
        );
        $stmt->execute([
            'f' => $data['first_name'],
            'l' => $data['last_name'],
            'm' => $data['mobile'],
            'e' => $data['email'] ?? null,
            'p' => $hash,
        ]);

        $userId = (int)$pdo->lastInsertId();
        if ($userId === 0) {
            $q = $pdo->prepare('SELECT id FROM users WHERE mobile = :m');
            $q->execute(['m' => $data['mobile']]);
            $userId = (int)$q->fetchColumn();
        }

        $pdo->prepare(
            'INSERT IGNORE INTO user_roles (user_id, role_id)
             SELECT :u, id FROM roles WHERE slug = \'SUPER_ADMIN\''
        )->execute(['u' => $userId]);

        return $userId;
    }

    /** Realistic Persian demo data so the panel is never empty on first run. */
    public function seedDemoData(PDO $pdo): void
    {
        try {
            $this->seedDemo($pdo);
        } catch (Throwable $e) {
            // Demo data must never block a successful installation.
            Logger::warning('Demo data seeding skipped', ['error' => $e->getMessage()]);
        }
    }

    private function seedDemo(PDO $pdo): void
    {
        /* ---- شعب: مرکزی از قبل با id=1 وجود دارد؛ ونک و تجریش اضافه می‌شوند ---- */
        $pdo->exec("UPDATE branches SET name='شعبه مرکزی', slug='markazi', phone='021-88776655',
                     address='تهران، ولیعصر، پلاک ۱۲۳', city='تهران', province='تهران' WHERE id = 1");
        $pdo->exec("INSERT IGNORE INTO branches (id, name, slug, phone, address, city, province, opening_time, closing_time, working_days, status) VALUES
            (2, 'شعبه ونک',   'vanak',   '021-88554433', 'تهران، ونک، ملاصدرا', 'تهران', 'تهران', '09:00:00', '21:00:00', '0,1,2,3,4,5', 'ACTIVE'),
            (3, 'شعبه تجریش', 'tajrish', '021-88332211', 'تهران، تجریش، دربند', 'تهران', 'تهران', '09:00:00', '21:00:00', '0,1,2,3,4,5', 'ACTIVE')");
        $pdo->exec('INSERT IGNORE INTO service_branches (service_id, branch_id) SELECT id, 2 FROM services');
        $pdo->exec('INSERT IGNORE INTO service_branches (service_id, branch_id) SELECT id, 3 FROM services');

        /* ---- کارکنان: نام، تخصص، شعبه و رنگ برگرفته از طرح اولیه پورتال ---- */
        $staff = [
            ['نازنین', 'موسوی',  '09131111101', 1, 'متخصص رنگ و مش', '#a44861', 30, [2, 3]],
            ['فرشته',  'رحیمی',  '09131111102', 2, 'متخصص کوتاهی مو', '#4690bf', 25, [1]],
            ['شیدا',   'کاظمی',  '09131111103', 1, 'متخصص ناخن و مژه', '#c9a227', 28, [6, 7]],
            ['مهسا',   'جعفری',  '09131111104', 3, 'متخصص آرایش',      '#83384d', 35, [8, 9]],
            ['رویا',   'امینی',  '09131111105', 2, 'متخصص پوست',       '#2c6fb5', 25, [4, 5]],
            ['سحر',    'بهرامی', '09131111106', 1, 'متخصص لیزر',       '#1e8e63', 30, [10, 11]],
        ];
        $insertStaff = $pdo->prepare(
            'INSERT IGNORE INTO staff (code, first_name, last_name, mobile, branch_id, job_title, color,
                                       hire_date, base_salary, commission_type, commission_percent, rating, status, is_public, online_booking)
             VALUES (:c, :f, :l, :m, :br, :j, :col, :h, 0, \'PERCENT\', :cp, :rt, \'ACTIVE\', 1, 1)'
        );
        $linkService = $pdo->prepare('INSERT IGNORE INTO staff_services (staff_id, service_id) VALUES (:s, :sv)');
        $shift = $pdo->prepare(
            'INSERT IGNORE INTO staff_weekly_shifts (staff_id, weekday, start_time, end_time, break_start, break_end, is_active)
             VALUES (:s, :w, \'10:00:00\', \'19:00:00\', \'13:00:00\', \'14:00:00\', 1)'
        );
        $ratings   = [4.9, 4.7, 4.8, 5.0, 4.6, 4.8];
        $staffIds  = [];

        foreach ($staff as $i => [$first, $last, $mobile, $branchId, $title, $color, $commission, $services]) {
            $code = 'STF-' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT);
            $insertStaff->execute([
                'c' => $code, 'f' => $first, 'l' => $last, 'm' => $mobile, 'br' => $branchId,
                'j' => $title, 'col' => $color, 'h' => date('Y-m-d', strtotime('-' . (500 - $i * 40) . ' days')),
                'cp' => $commission, 'rt' => $ratings[$i],
            ]);
            $q = $pdo->prepare('SELECT id FROM staff WHERE mobile = :m');
            $q->execute(['m' => $mobile]);
            $staffId = (int)$q->fetchColumn();
            if ($staffId === 0) {
                continue;
            }
            $staffIds[$i + 1] = $staffId;
            foreach ($services as $sv) {
                $linkService->execute(['s' => $staffId, 'sv' => $sv]);
            }
            foreach ([0, 1, 2, 3, 4, 5] as $weekday) {
                $shift->execute(['s' => $staffId, 'w' => $weekday]);
            }
        }

        /* ---- مشتریان: نام، موبایل، تاریخ تولد، تعداد مراجعه و مجموع خرید برگرفته از طرح اولیه ---- */
        $customers = [
            // first, last, mobile, email, birthJalali, branch, staffIndex, status, visits, spent, points, health, lastVisitDaysAgo
            ['سارا',   'احمدی',  '09121234567', 'sara@ex.com',    '1372/05/12', 1, 1,    'ACTIVE',   24, 18500000, 4820,  92, 1],
            ['مریم',   'رضایی',  '09122345678', 'maryam@ex.com',  '1375/08/20', 2, 2,    'ACTIVE',   12, 8200000,  2150,  78, 2],
            ['نازنین', 'کریمی',  '09123456789', 'nazanin@ex.com', '1378/03/05', 1, 3,    'ACTIVE',   8,  5400000,  1320,  65, 6],
            ['فاطمه',  'محمدی',  '09124567890', 'fatemeh@ex.com', '1380/11/15', 3, null, 'ACTIVE',   3,  1800000,  450,   55, 27],
            ['لیلا',   'حسینی',  '09125678901', 'leila@ex.com',   '1368/09/08', 3, 4,    'ACTIVE',   45, 42000000, 12500, 40, 66],
            ['زهرا',   'نوری',   '09126789012', 'zahra@ex.com',   '1382/02/28', 1, null, 'INACTIVE', 1,  450000,   110,   15, 103],
            ['الهام',  'صادقی',  '09127890123', 'elham@ex.com',   '1374/07/14', 1, 6,    'ACTIVE',   18, 12800000, 3200,  82, 4],
            ['شیما',   'قاسمی',  '09128901234', 'shima@ex.com',   '1379/04/22', 3, 1,    'ACTIVE',   6,  3200000,  800,   70, 0],
        ];
        $insertCustomer = $pdo->prepare(
            'INSERT IGNORE INTO customers (code, first_name, last_name, mobile, email, gender, birth_date,
                                           preferred_branch_id, preferred_staff_id, source, status, marketing_opt_in,
                                           visits_count, total_spent, loyalty_points, health_score, churn_risk,
                                           last_visit_at, first_visit_at)
             VALUES (:c, :f, :l, :m, :e, \'FEMALE\', :b, :br, :st, :src, :status, 1, :v, :spent, :pts, :health, :churn,
                     :lv, :fv)'
        );
        $customerIds = [];
        foreach ($customers as $i => [$first, $last, $mobile, $email, $birthJalali, $branchId, $staffIdx, $status, $visits, $spent, $points, $health, $lastVisitDays]) {
            $insertCustomer->execute([
                'c' => 'CUS-' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                'f' => $first, 'l' => $last, 'm' => $mobile, 'e' => $email,
                'b' => $this->jalaliToGregorian($birthJalali),
                'br' => $branchId, 'st' => $staffIdx !== null ? ($staffIds[$staffIdx] ?? null) : null,
                'src' => 'REFERRAL', 'status' => $status, 'v' => $visits, 'spent' => $spent, 'pts' => $points,
                'health' => $health, 'churn' => round(100 - $health, 2),
                'lv' => date('Y-m-d H:i:s', strtotime("-{$lastVisitDays} days")),
                'fv' => date('Y-m-d H:i:s', strtotime('-' . ($lastVisitDays + $visits * 20) . ' days')),
            ]);
            $q = $pdo->prepare('SELECT id FROM customers WHERE mobile = :m');
            $q->execute(['m' => $mobile]);
            $cid = (int)$q->fetchColumn();
            if ($cid > 0) {
                $customerIds[$i + 1] = $cid;
                $pdo->prepare('INSERT IGNORE INTO customer_loyalty (customer_id, points_balance) VALUES (:c, :p)')->execute(['c' => $cid, 'p' => $points]);
                $pdo->prepare('INSERT IGNORE INTO wallet_accounts (customer_id, balance) VALUES (:c, 0)')->execute(['c' => $cid]);
            }
        }

        /* ---- نوبت‌های امروز: مطابق طرح اولیه (زمان، خدمت، متخصص، شعبه، وضعیت) ---- */
        $today = date('Y-m-d');
        $appointments = [
            // customerIdx, staffIdx, branch, start, end, serviceId, status, price
            [1, 1, 1, '10:00:00', '12:00:00', 2,  'CONFIRMED', 12000000],
            [2, 2, 2, '11:30:00', '12:15:00', 1,  'COMPLETED', 3500000],
            [3, 3, 1, '13:00:00', '14:15:00', 6,  'PENDING',   3200000],
            [4, 4, 3, '14:30:00', '18:30:00', 8,  'CONFIRMED', 35000000],
            [5, 5, 2, '16:00:00', '17:15:00', 4,  'COMPLETED', 4500000],
            [6, 3, 1, '17:30:00', '19:30:00', 7,  'CANCELLED', 7500000],
        ];
        $insertAppt = $pdo->prepare(
            'INSERT IGNORE INTO appointments (code, customer_id, staff_id, branch_id, appointment_date,
                                              start_time, end_time, starts_at, ends_at, duration_minutes, status, source, total_price)
             VALUES (:code, :cust, :staff, :branch, :d, :st, :et, :sa, :ea, :dur, :status, \'ADMIN\', :price)'
        );
        $apptIds = [];
        foreach ($appointments as $i => [$custIdx, $staffIdx, $branchId, $start, $end, $svcId, $status, $price]) {
            if (!isset($customerIds[$custIdx], $staffIds[$staffIdx])) {
                continue;
            }
            $insertAppt->execute([
                'code' => 'APT-' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                'cust' => $customerIds[$custIdx], 'staff' => $staffIds[$staffIdx], 'branch' => $branchId,
                'd' => $today, 'st' => $start, 'et' => $end,
                'sa' => "$today $start", 'ea' => "$today $end",
                'dur' => (strtotime($end) - strtotime($start)) / 60,
                'status' => $status, 'price' => $price,
            ]);
            $q = $pdo->prepare('SELECT id FROM appointments WHERE code = :c');
            $q->execute(['c' => 'APT-' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT)]);
            $apptIds[$i + 1] = (int)$q->fetchColumn();
        }

        /* ---- فاکتورهای نمونه ---- */
        $invoices = [
            // number, customerIdx, staffIdx, branch, subtotal, discount, total, status, items:[[svcId,title,price]]
            ['INV-1024', 1, 1, 1, 1980000, 0,      1980000, 'PAID',   [[2, 'رنگ مو و مش', 1800000], [10, 'اصلاح صورت و ابرو', 180000]]],
            ['INV-1023', 2, 2, 2, 450000,  0,      450000,  'PAID',   [[1, 'کوتاهی مو بانوان', 450000]]],
            ['INV-1022', 5, 5, 2, 1270000, 50000,  1220000, 'PAID',   [[4, 'پاکسازی صورت', 950000]]],
            ['INV-1021', 3, 3, 1, 680000,  0,      680000,  'UNPAID', [[6, 'مانیکور و لاک ژل', 680000]]],
            ['INV-1020', 4, 4, 3, 4500000, 500000, 4000000, 'PAID',   [[8, 'میکاپ عروس', 4500000]]],
        ];
        $insertInvoice = $pdo->prepare(
            'INSERT IGNORE INTO invoices (invoice_number, customer_id, staff_id, branch_id, issue_date,
                                          subtotal, discount_amount, total, paid_amount, due_amount, payment_status, status)
             VALUES (:num, :cust, :staff, :branch, :d, :sub, :dis, :total, :paid, :due, :pstatus, \'ISSUED\')'
        );
        $insertItem = $pdo->prepare(
            'INSERT INTO invoice_items (invoice_id, item_type, service_id, staff_id, title, quantity, unit_price, total)
             VALUES (:inv, \'SERVICE\', :svc, :staff, :title, 1, :price, :price)'
        );
        foreach ($invoices as [$num, $custIdx, $staffIdx, $branchId, $sub, $dis, $total, $status, $items]) {
            if (!isset($customerIds[$custIdx], $staffIds[$staffIdx])) {
                continue;
            }
            $insertInvoice->execute([
                'num' => $num, 'cust' => $customerIds[$custIdx], 'staff' => $staffIds[$staffIdx], 'branch' => $branchId,
                'd' => $today, 'sub' => $sub, 'dis' => $dis, 'total' => $total,
                'paid' => $status === 'PAID' ? $total : 0, 'due' => $status === 'PAID' ? 0 : $total, 'pstatus' => $status,
            ]);
            $q = $pdo->prepare('SELECT id FROM invoices WHERE invoice_number = :n');
            $q->execute(['n' => $num]);
            $invId = (int)$q->fetchColumn();
            if ($invId === 0) {
                continue;
            }
            foreach ($items as [$svcId, $title, $price]) {
                $insertItem->execute(['inv' => $invId, 'svc' => $svcId, 'staff' => $staffIds[$staffIdx], 'title' => $title, 'price' => $price]);
            }
        }

        /* ---- تیکت و نظرات نمونه ---- */
        if (isset($customerIds[5])) {
            $pdo->prepare(
                "INSERT IGNORE INTO tickets (ticket_number, customer_id, subject, category, priority, status)
                 VALUES ('TK-101', :c, 'عدم رضایت از رنگ مو', 'شکایت', 'HIGH', 'OPEN')"
            )->execute(['c' => $customerIds[5]]);
        }
        if (isset($customerIds[4])) {
            $pdo->prepare(
                "INSERT IGNORE INTO tickets (ticket_number, customer_id, subject, category, priority, status)
                 VALUES ('TK-102', :c, 'تغییر زمان نوبت', 'درخواست', 'NORMAL', 'OPEN')"
            )->execute(['c' => $customerIds[4]]);
        }
        if (isset($customerIds[2], $staffIds[2])) {
            $pdo->prepare(
                'INSERT INTO reviews (customer_id, staff_id, service_id, rating, comment, status, is_public)
                 VALUES (:c, :s, 1, 5, :cm, \'APPROVED\', 1)'
            )->execute(['c' => $customerIds[2], 's' => $staffIds[2], 'cm' => 'خدمات عالی بود.']);
        }
    }

    /** Minimal Jalali → Gregorian conversion for seed birth dates (no external dependency). */
    private function jalaliToGregorian(string $jalali): string
    {
        [$jy, $jm, $jd] = array_map('intval', explode('/', $jalali));
        $jy += 1595;
        $days = -355668 + (365 * $jy) + ((int)($jy / 33) * 8) + (int)((($jy % 33) + 3) / 4) + $jd
              + ($jm < 7 ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
        $gy = 400 * (int)($days / 146097);
        $days %= 146097;
        if ($days > 36524) {
            $gy += 100 * (int)(--$days / 36524);
            $days %= 36524;
            if ($days >= 365) {
                $days++;
            }
        }
        $gy += 4 * (int)($days / 1461);
        $days %= 1461;
        if ($days > 365) {
            $gy += (int)(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        $gd = $days + 1;
        $monthDays = [31, (($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $gm = 0;
        for (; $gm < 12 && $gd > $monthDays[$gm]; $gm++) {
            $gd -= $monthDays[$gm];
        }
        return sprintf('%04d-%02d-%02d', $gy, $gm + 1, $gd);
    }

    public function lock(): void
    {
        $flag = (string)Config::get('app.installed_flag');
        $dir  = dirname($flag);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        file_put_contents($flag, json_encode([
            'installed_at' => date('c'),
            'version'      => Config::get('app.version', '0.1.0'),
        ], JSON_UNESCAPED_UNICODE));
    }

    public function isInstalled(): bool
    {
        return is_file((string)Config::get('app.installed_flag'));
    }
}
