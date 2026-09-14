<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\BusinessException;
use App\Helpers\Format;
use App\Helpers\Jalali;
use App\Repositories\CustomerRepository;

/**
 * CSV import with validate -> preview -> confirm -> report flow.
 * Rows are never imported blindly: each row is validated and reported.
 */
final class ImportService
{
    public const ENTITIES = [
        'customers' => 'مشتریان',
        'services'  => 'خدمات',
        'products'  => 'محصولات',
    ];

    private const COLUMNS = [
        'customers' => ['first_name' => 'نام', 'last_name' => 'نام خانوادگی', 'mobile' => 'موبایل', 'email' => 'ایمیل', 'birth_date' => 'تاریخ تولد', 'gender' => 'جنسیت', 'notes' => 'یادداشت'],
        'services'  => ['name' => 'نام خدمت', 'category' => 'دسته', 'price' => 'قیمت', 'duration_minutes' => 'مدت (دقیقه)', 'cost' => 'بهای تمام‌شده'],
        'products'  => ['sku' => 'کد کالا', 'name' => 'نام محصول', 'unit' => 'واحد', 'purchase_price' => 'قیمت خرید', 'sale_price' => 'قیمت فروش', 'quantity' => 'موجودی اولیه'],
    ];

    public static function template(string $entity): string
    {
        $cols = self::COLUMNS[$entity] ?? [];
        return ExportService::csv([], array_keys($cols));
    }

    public static function columns(string $entity): array
    {
        return self::COLUMNS[$entity] ?? [];
    }

    /** Parse an uploaded CSV into rows keyed by header. */
    public static function parse(string $path, int $maxRows = 5000): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new BusinessException('فایل قابل خواندن نیست.', 'UNREADABLE_FILE', 422);
        }
        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            throw new BusinessException('فایل خالی است.', 'EMPTY_FILE', 422);
        }
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$headers[0]);
        $headers    = array_map(static fn ($h) => trim((string)$h), $headers);

        $rows = [];
        $n    = 0;
        while (($line = fgetcsv($handle)) !== false && $n < $maxRows) {
            if (count(array_filter($line, static fn ($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }
            $row = [];
            foreach ($headers as $i => $h) {
                $row[$h] = isset($line[$i]) ? trim((string)$line[$i]) : '';
            }
            $rows[] = $row;
            $n++;
        }
        fclose($handle);
        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Validate rows and return a per-row report.
     * @return array{valid:array, invalid:array, summary:array}
     */
    public static function validateRows(string $entity, array $rows): array
    {
        $valid   = [];
        $invalid = [];
        $db      = Database::instance();

        foreach ($rows as $index => $row) {
            $lineNo = $index + 2; // header is line 1
            $errors = [];
            $clean  = [];

            if ($entity === 'customers') {
                $first  = trim((string)($row['first_name'] ?? ''));
                $last   = trim((string)($row['last_name'] ?? ''));
                $mobile = Format::mobile((string)($row['mobile'] ?? ''));
                if ($first === '') {
                    $errors[] = 'نام الزامی است.';
                }
                if ($last === '') {
                    $errors[] = 'نام خانوادگی الزامی است.';
                }
                if (!Format::isValidMobile($mobile)) {
                    $errors[] = 'شماره موبایل معتبر نیست.';
                }
                $email = trim((string)($row['email'] ?? ''));
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'ایمیل معتبر نیست.';
                }
                $birth = trim((string)($row['birth_date'] ?? ''));
                $birthG = null;
                if ($birth !== '') {
                    $birthG = Jalali::parse($birth) ?? (strtotime($birth) !== false ? date('Y-m-d', strtotime($birth)) : null);
                    if ($birthG === null) {
                        $errors[] = 'تاریخ تولد معتبر نیست.';
                    }
                }
                $clean = [
                    'first_name' => $first, 'last_name' => $last, 'mobile' => $mobile,
                    'email' => $email ?: null, 'birth_date' => $birthG,
                    'gender' => in_array((string)($row['gender'] ?? ''), ['MALE', 'مرد'], true) ? 'MALE' : 'FEMALE',
                    'notes' => trim((string)($row['notes'] ?? '')),
                ];
                if ($errors === []) {
                    $existing = $db->scalar('SELECT id FROM customers WHERE mobile = :m AND deleted_at IS NULL', ['m' => $mobile]);
                    $clean['_existing_id'] = $existing ? (int)$existing : null;
                }
            } elseif ($entity === 'services') {
                $name = trim((string)($row['name'] ?? ''));
                if ($name === '') {
                    $errors[] = 'نام خدمت الزامی است.';
                }
                $price = (float)Format::toEnglishDigits(str_replace(',', '', (string)($row['price'] ?? '0')));
                if ($price < 0) {
                    $errors[] = 'قیمت نامعتبر است.';
                }
                $catName = trim((string)($row['category'] ?? ''));
                $catId   = $catName !== '' ? $db->scalar('SELECT id FROM service_categories WHERE name = :n', ['n' => $catName]) : null;
                if ($catName !== '' && !$catId) {
                    $errors[] = 'دسته «' . $catName . '» یافت نشد.';
                }
                $clean = [
                    'name' => $name, 'category_id' => $catId ? (int)$catId : null,
                    'price' => $price,
                    'duration_minutes' => max(5, (int)Format::toEnglishDigits((string)($row['duration_minutes'] ?? '60'))),
                    'cost' => (float)Format::toEnglishDigits(str_replace(',', '', (string)($row['cost'] ?? '0'))),
                ];
                if ($errors === []) {
                    $existing = $db->scalar('SELECT id FROM services WHERE name = :n AND deleted_at IS NULL', ['n' => $name]);
                    $clean['_existing_id'] = $existing ? (int)$existing : null;
                }
            } elseif ($entity === 'products') {
                $sku  = trim((string)($row['sku'] ?? ''));
                $name = trim((string)($row['name'] ?? ''));
                if ($name === '') {
                    $errors[] = 'نام محصول الزامی است.';
                }
                if ($sku === '') {
                    $errors[] = 'کد کالا الزامی است.';
                }
                $clean = [
                    'sku' => $sku, 'name' => $name,
                    'unit' => trim((string)($row['unit'] ?? 'عدد')) ?: 'عدد',
                    'purchase_price' => (float)Format::toEnglishDigits(str_replace(',', '', (string)($row['purchase_price'] ?? '0'))),
                    'sale_price'     => (float)Format::toEnglishDigits(str_replace(',', '', (string)($row['sale_price'] ?? '0'))),
                    'quantity'       => (float)Format::toEnglishDigits(str_replace(',', '', (string)($row['quantity'] ?? '0'))),
                ];
                if ($errors === []) {
                    $existing = $db->scalar('SELECT id FROM products WHERE sku = :s AND deleted_at IS NULL', ['s' => $sku]);
                    $clean['_existing_id'] = $existing ? (int)$existing : null;
                }
            } else {
                $errors[] = 'نوع ورود اطلاعات پشتیبانی نمی‌شود.';
            }

            if ($errors === []) {
                $valid[] = ['line' => $lineNo, 'data' => $clean, 'action' => ($clean['_existing_id'] ?? null) ? 'UPDATE' : 'INSERT'];
            } else {
                $invalid[] = ['line' => $lineNo, 'raw' => $row, 'errors' => $errors];
            }
        }

        return [
            'valid'   => $valid,
            'invalid' => $invalid,
            'summary' => [
                'total'   => count($rows),
                'insert'  => count(array_filter($valid, static fn ($v) => $v['action'] === 'INSERT')),
                'update'  => count(array_filter($valid, static fn ($v) => $v['action'] === 'UPDATE')),
                'failed'  => count($invalid),
            ],
        ];
    }

    /** Persist validated rows; returns the final report. */
    public static function commit(string $entity, array $validRows, string $filename, int $branchId = 1): array
    {
        $db       = Database::instance();
        $imported = 0;
        $updated  = 0;
        $skipped  = 0;
        $failed   = 0;
        $errors   = [];

        foreach ($validRows as $row) {
            try {
                $db->transaction(function () use ($entity, $row, $db, $branchId, &$imported, &$updated, &$skipped): void {
                    $data = $row['data'];
                    $existingId = $data['_existing_id'] ?? null;
                    unset($data['_existing_id']);

                    if ($entity === 'customers') {
                        $notes = $data['notes'] ?? '';
                        unset($data['notes']);
                        if ($existingId) {
                            $db->update('customers', $data, 'id = :id', ['id' => $existingId]);
                            $updated++;
                            $cid = (int)$existingId;
                        } else {
                            $repo = new CustomerRepository($db);
                            $data['code'] = $repo->nextCode();
                            $cid = $db->insert('customers', $data);
                            $db->insert('customer_loyalty', ['customer_id' => $cid, 'points_balance' => 0]);
                            $db->insert('wallet_accounts', ['customer_id' => $cid, 'balance' => '0.00']);
                            $imported++;
                        }
                        if ($notes !== '') {
                            $db->insert('customer_notes', ['customer_id' => $cid, 'note' => $notes, 'user_id' => AuthService::id()]);
                        }
                    } elseif ($entity === 'services') {
                        if ($data['category_id'] === null) {
                            $data['category_id'] = (int)($db->scalar('SELECT id FROM service_categories ORDER BY id LIMIT 1') ?? 1);
                        }
                        $data['price'] = number_format((float)$data['price'], 2, '.', '');
                        $data['cost']  = number_format((float)$data['cost'], 2, '.', '');
                        if ($existingId) {
                            $db->update('services', $data, 'id = :id', ['id' => $existingId]);
                            $updated++;
                        } else {
                            $data['slug'] = Format::slug($data['name']) . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
                            $db->insert('services', $data);
                            $imported++;
                        }
                    } elseif ($entity === 'products') {
                        $qty = (float)($data['quantity'] ?? 0);
                        unset($data['quantity']);
                        $data['purchase_price'] = number_format((float)$data['purchase_price'], 2, '.', '');
                        $data['sale_price']     = number_format((float)$data['sale_price'], 2, '.', '');
                        if ($existingId) {
                            $db->update('products', $data, 'id = :id', ['id' => $existingId]);
                            $updated++;
                            $pid = (int)$existingId;
                        } else {
                            $pid = $db->insert('products', $data);
                            $imported++;
                        }
                        if ($qty > 0) {
                            (new InventoryService($db))->move($pid, $branchId, 'IN', $qty, 'import', null, 'موجودی اولیه (ورود از فایل)');
                        }
                    } else {
                        $skipped++;
                    }
                });
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['line' => $row['line'], 'error' => $e->getMessage()];
            }
        }

        $batchId = $db->insert('import_batches', [
            'entity'     => $entity,
            'filename'   => mb_substr($filename, 0, 255),
            'total_rows' => count($validRows),
            'imported'   => $imported,
            'updated'    => $updated,
            'skipped'    => $skipped,
            'failed'     => $failed,
            'errors'     => json_encode($errors, JSON_UNESCAPED_UNICODE),
            'status'     => $failed > 0 && $imported + $updated === 0 ? 'FAILED' : 'COMPLETED',
            'created_by' => AuthService::id(),
        ]);
        AuditService::log('data_imported', 'import_batches', $batchId, null, [
            'entity' => $entity, 'imported' => $imported, 'updated' => $updated, 'failed' => $failed,
        ]);

        return [
            'batch_id' => $batchId, 'imported' => $imported, 'updated' => $updated,
            'skipped' => $skipped, 'failed' => $failed, 'errors' => $errors,
        ];
    }

    public static function history(int $limit = 20): array
    {
        return Database::instance()->select(
            "SELECT b.*, CONCAT(u.first_name,' ',u.last_name) AS user_name
             FROM import_batches b LEFT JOIN users u ON u.id = b.created_by
             ORDER BY b.id DESC LIMIT " . $limit
        );
    }
}
