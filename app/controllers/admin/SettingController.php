<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Config;
use App\Core\Database;
use App\Core\Exceptions\BusinessException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Jalali;
use App\Services\AuditService;
use App\Services\BackupService;
use App\Services\SettingsService;
use App\Validators\Validator;

final class SettingController extends BaseController
{
    /** Whitelisted setting keys with their storage type. Anything else is ignored. */
    private const FIELDS = [
        // general
        'salon_name'        => ['STRING', 'general'],
        'salon_tagline'     => ['STRING', 'general'],
        'salon_phone'       => ['STRING', 'general'],
        'salon_email'       => ['STRING', 'general'],
        'salon_address'     => ['STRING', 'general'],
        'instagram'         => ['STRING', 'general'],
        'telegram'          => ['STRING', 'general'],
        'whatsapp'          => ['STRING', 'general'],
        // booking
        'booking_enabled'       => ['BOOL', 'booking'],
        'booking_max_days'      => ['INT', 'booking'],
        'booking_min_hours'     => ['INT', 'booking'],
        'booking_slot_minutes'  => ['INT', 'booking'],
        'booking_auto_confirm'  => ['BOOL', 'booking'],
        'booking_buffer_minutes' => ['INT', 'booking'],
        'cancel_hours'          => ['INT', 'booking'],
        // finance
        'currency'          => ['STRING', 'finance'],
        'tax_percent'       => ['FLOAT', 'finance'],
        'invoice_prefix'    => ['STRING', 'finance'],
        'invoice_footer'    => ['STRING', 'finance'],
        // loyalty
        'loyalty_enabled'      => ['BOOL', 'loyalty'],
        'loyalty_points_per_unit' => ['FLOAT', 'loyalty'],
        'loyalty_point_value'  => ['FLOAT', 'loyalty'],
        // notifications
        'sms_enabled'          => ['BOOL', 'notification'],
        'sms_reminder_hours'   => ['INT', 'notification'],
        'notify_on_booking'    => ['BOOL', 'notification'],
        // seo
        'meta_title'        => ['STRING', 'seo'],
        'meta_description'  => ['STRING', 'seo'],
        'google_analytics'  => ['STRING', 'seo'],
    ];

    public function index(Request $request): Response
    {
        $this->authorize('settings.manage');

        return $this->view('admin/settings/index', [
            'title'    => 'تنظیمات سامانه',
            'settings' => SettingsService::all(),
            'fields'   => self::FIELDS,
            'smsDriver' => (string)config('services.sms.driver', 'log'),
            'smsReady'  => (bool)config('services.sms.enabled', false),
        ]);
    }

    public function save(Request $request): Response
    {
        $this->authorize('settings.manage');
        $changed = [];

        foreach (self::FIELDS as $key => [$type, $group]) {
            if (!array_key_exists($key, $request->all())) {
                // Checkboxes are absent when unchecked.
                if ($type === 'BOOL') {
                    SettingsService::set($key, '0', 'BOOL', $group);
                }
                continue;
            }
            $value = match ($type) {
                'BOOL'  => $request->bool($key) ? '1' : '0',
                'INT'   => (string)(int)$request->int($key, 0),
                'FLOAT' => (string)(float)$request->float($key, 0),
                default => mb_substr($request->str($key), 0, 1000),
            };
            SettingsService::set($key, $value, $type, $group);
            $changed[$key] = $value;
        }

        SettingsService::flush();
        AuditService::log('settings_updated', 'settings', null, null, $changed);

        return $this->back('success', 'تنظیمات ذخیره شد.');
    }

    /* ======================================================= branches == */

    public function branches(Request $request): Response
    {
        $this->authorize('settings.manage');

        return $this->view('admin/settings/branches', [
            'title'    => 'شعبه‌ها',
            'branches' => Database::instance()->select(
                "SELECT b.id, b.name, b.slug, b.phone, b.address, b.opening_time, b.closing_time,
                        b.working_days, b.status, b.latitude, b.longitude,
                        (SELECT COUNT(*) FROM staff WHERE branch_id = b.id AND deleted_at IS NULL) AS staff_count
                 FROM branches b WHERE b.deleted_at IS NULL ORDER BY b.name"
            ),
        ]);
    }

    public function saveBranch(Request $request): Response
    {
        $this->authorize('settings.manage');
        $id   = $request->int('id');
        $data = Validator::validate($request->all(), [
            'name'         => 'required|string|max:120',
            'slug'         => 'required|string|max:20',
            'phone'        => 'required|string|max:20',
            'address'      => 'required|string|max:255',
            'opening_time' => 'required|string|max:8',
            'closing_time' => 'required|string|max:8',
            'latitude'     => 'nullable|string|max:20',
            'longitude'    => 'nullable|string|max:20',
            'status'       => 'nullable|in:ACTIVE,INACTIVE',
        ], ['name' => 'نام شعبه', 'slug' => 'کد شعبه', 'phone' => 'تلفن', 'address' => 'آدرس']);

        $days = array_values(array_filter(
            array_map('intval', $request->arr('working_days')),
            static fn ($d) => $d >= 0 && $d <= 6
        ));

        $payload = [
            'name'         => $data['name'],
            'slug'         => $data['slug'],
            'phone'        => $data['phone'],
            'address'      => $data['address'],
            'opening_time' => $data['opening_time'],
            'closing_time' => $data['closing_time'],
            'working_days' => implode(',', $days ?: [0, 1, 2, 3, 4, 5]),
            'latitude'     => $data['latitude'] ?: null,
            'longitude'    => $data['longitude'] ?: null,
            'status'       => $data['status'] ?? 'ACTIVE',
        ];

        $db = Database::instance();
        if ($id) {
            $old = $db->selectOne('SELECT * FROM branches WHERE id = :id', ['id' => $id]);
            $db->update('branches', $payload, 'id = :id', ['id' => $id]);
            AuditService::log('branch_updated', 'branches', $id, $old, $payload);
            $message = 'شعبه به‌روزرسانی شد.';
        } else {
            $newId = $db->insert('branches', $payload);
            AuditService::log('branch_created', 'branches', $newId, null, $payload);
            $message = 'شعبه جدید ثبت شد.';
        }

        return $this->redirect('/admin/settings/branches', 'success', $message);
    }

    /* ========================================================== users == */

    public function users(Request $request): Response
    {
        $this->authorize('users.manage');
        $db = Database::instance();

        return $this->view('admin/settings/users', [
            'title' => 'کاربران و دسترسی‌ها',
            'users' => $db->select(
                "SELECT u.id, u.first_name, u.last_name, u.mobile, u.email, u.status, u.last_login_at,
                        b.name AS branch_name,
                        (SELECT GROUP_CONCAT(r.name) FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id) AS roles
                 FROM users u LEFT JOIN branches b ON b.id = u.branch_id
                 WHERE u.deleted_at IS NULL ORDER BY u.id DESC LIMIT 200"
            ),
            'roles' => $db->select('SELECT id, slug, name FROM roles ORDER BY id'),
        ]);
    }

    public function saveUserRoles(Request $request, string $id): Response
    {
        $this->authorize('users.manage');
        $userId = (int)$id;
        $db     = Database::instance();

        $user = $db->selectOne('SELECT id, first_name, last_name FROM users WHERE id = :id AND deleted_at IS NULL', ['id' => $userId]);
        if ($user === null) {
            $this->notFound('کاربر یافت نشد.');
        }
        if ($userId === $this->userId()) {
            return $this->back('error', 'نقش‌های حساب خودتان را نمی‌توانید تغییر دهید.');
        }

        $roleIds = array_values(array_unique(array_filter(array_map('intval', $request->arr('roles')))));
        if ($roleIds === []) {
            return $this->back('error', 'حداقل یک نقش باید انتخاب شود.');
        }

        // Never allow the last SUPER_ADMIN to be demoted.
        $superId = (int)$db->scalar("SELECT id FROM roles WHERE slug = 'SUPER_ADMIN'");
        $wasSuper = (int)$db->scalar('SELECT COUNT(*) FROM user_roles WHERE user_id = :u AND role_id = :r', ['u' => $userId, 'r' => $superId]) > 0;
        if ($wasSuper && !in_array($superId, $roleIds, true)) {
            $remaining = (int)$db->scalar('SELECT COUNT(*) FROM user_roles WHERE role_id = :r AND user_id <> :u', ['r' => $superId, 'u' => $userId]);
            if ($remaining === 0) {
                return $this->back('error', 'حداقل یک مدیر ارشد باید در سامانه باقی بماند.');
            }
        }

        $db->transaction(function () use ($db, $userId, $roleIds): void {
            $db->delete('user_roles', 'user_id = :u', ['u' => $userId]);
            foreach ($roleIds as $rid) {
                $db->insert('user_roles', ['user_id' => $userId, 'role_id' => $rid]);
            }
        });

        AuditService::log('user_roles_updated', 'users', $userId, null, ['roles' => $roleIds]);
        return $this->back('success', 'نقش‌های کاربر به‌روزرسانی شد.');
    }

    /* ======================================================== backups == */

    public function backups(Request $request): Response
    {
        $this->authorize('settings.manage');

        return $this->view('admin/settings/backups', [
            'title'   => 'پشتیبان‌گیری',
            'backups' => BackupService::listAll(),
        ]);
    }

    public function createBackup(Request $request): Response
    {
        $this->authorize('settings.manage');
        try {
            $result = BackupService::create();
            BackupService::prune(10);
            AuditService::log('backup_created', 'backups', null, null, ['file' => $result['name'] ?? '']);
            return $this->back('success', 'پشتیبان با موفقیت ساخته شد: ' . ($result['name'] ?? ''));
        } catch (\Throwable $e) {
            return $this->back('error', 'ساخت پشتیبان ناموفق بود: ' . $e->getMessage());
        }
    }

    public function downloadBackup(Request $request, string $file): Response
    {
        $this->authorize('settings.manage');
        $name = basename($file);
        try {
            return Response::download(BackupService::read($name), $name, 'application/sql');
        } catch (\Throwable) {
            $this->notFound('فایل پشتیبان یافت نشد.');
        }
    }

    public function deleteBackup(Request $request, string $file): Response
    {
        $this->authorize('settings.manage');
        $name = basename($file);
        $ok   = BackupService::delete($name);
        AuditService::log('backup_deleted', 'backups', null, null, ['file' => $name]);

        return $this->back($ok ? 'success' : 'error', $ok ? 'فایل پشتیبان حذف شد.' : 'فایل یافت نشد.');
    }

    /* ========================================================== audit == */

    public function audit(Request $request): Response
    {
        $this->authorize('audit.view');
        $db     = Database::instance();
        $page   = $request->page();
        $per    = $request->perPage();
        $action = $request->str('action');
        $userId = $request->int('user_id');

        $where  = 'WHERE 1=1';
        $params = [];
        if ($action !== '') {
            $where .= ' AND l.action = :a';
            $params['a'] = $action;
        }
        if ($userId) {
            $where .= ' AND l.user_id = :u';
            $params['u'] = $userId;
        }

        $total = (int)$db->scalar("SELECT COUNT(*) FROM audit_logs l {$where}", $params);
        $rows  = $db->select(
            "SELECT l.id, l.action, l.entity, l.entity_id, l.ip_address, l.created_at,
                    CONCAT(u.first_name,' ',u.last_name) AS user_name
             FROM audit_logs l LEFT JOIN users u ON u.id = l.user_id
             {$where} ORDER BY l.id DESC LIMIT {$per} OFFSET " . (($page - 1) * $per),
            $params
        );
        foreach ($rows as &$r) {
            $r['created_fa'] = Jalali::format($r['created_at'], 'j F Y — H:i');
        }
        unset($r);

        return $this->view('admin/settings/audit', [
            'title'    => 'گزارش فعالیت‌ها',
            'logs'     => $rows,
            'page'     => $page,
            'lastPage' => max(1, (int)ceil($total / $per)),
            'total'    => $total,
            'actions'  => $db->select('SELECT DISTINCT action FROM audit_logs ORDER BY action LIMIT 100'),
            'filters'  => ['action' => $action, 'user_id' => $userId],
        ]);
    }

    /* ==================================================== documents == */

    private const DOCUMENT_CATEGORIES = [
        'bylaw'    => 'آیین‌نامه',
        'contract' => 'قرارداد',
        'minutes'  => 'صورتجلسه',
        'marketing' => 'بازاریابی',
        'other'    => 'سایر',
    ];

    public function documents(Request $request): Response
    {
        $this->authorize('settings.manage');
        $db = Database::instance();

        $rows = $db->select(
            "SELECT d.*, CONCAT(u.first_name,' ',u.last_name) AS uploader_name
             FROM documents d LEFT JOIN users u ON u.id = d.uploaded_by
             ORDER BY d.created_at DESC"
        );

        return $this->view('admin/settings/documents', [
            'title'      => 'آیین‌نامه و اسناد',
            'documents'  => $rows,
            'categories' => self::DOCUMENT_CATEGORIES,
        ]);
    }

    public function uploadDocument(Request $request): Response
    {
        $this->authorize('settings.manage');

        $data = Validator::validate($request->all(), [
            'title'       => 'required|string|max:200',
            'description' => 'nullable|string|max:1000',
            'category'    => 'required|in:' . implode(',', array_keys(self::DOCUMENT_CATEGORIES)),
        ], ['title' => 'عنوان سند', 'category' => 'دسته‌بندی']);

        $file = $request->file('file');
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $this->back('error', 'انتخاب فایل الزامی است.');
        }

        try {
            $stored = $this->storeDocumentFile($file);
        } catch (BusinessException $e) {
            return $this->back('error', $e->getMessage());
        }

        $id = Database::instance()->insert('documents', [
            'title'             => $data['title'],
            'description'       => $data['description'] ?? null,
            'category'          => $data['category'],
            'original_filename' => $stored['original_filename'],
            'stored_filename'   => $stored['stored_filename'],
            'mime_type'         => $stored['mime_type'],
            'file_size'         => $stored['file_size'],
            'uploaded_by'       => $this->userId(),
        ]);

        AuditService::log('document_uploaded', 'documents', $id, null, ['title' => $data['title']]);
        return $this->redirect('/admin/settings/documents', 'success', 'سند با موفقیت بارگذاری شد.');
    }

    public function downloadDocument(Request $request, string $id): Response
    {
        $this->authorize('settings.manage');
        $doc = Database::instance()->selectOne('SELECT * FROM documents WHERE id = :id', ['id' => (int)$id]);
        if ($doc === null) {
            $this->notFound('سند یافت نشد.');
        }

        $path = self::documentsDir() . '/' . $doc['stored_filename'];
        if (!is_file($path)) {
            $this->notFound('فایل سند روی سرور یافت نشد.');
        }

        $content = (string)file_get_contents($path);
        return Response::download($content, $doc['original_filename'], $doc['mime_type']);
    }

    public function deleteDocument(Request $request, string $id): Response
    {
        $this->authorize('settings.manage');
        $doc = Database::instance()->selectOne('SELECT * FROM documents WHERE id = :id', ['id' => (int)$id]);
        if ($doc === null) {
            $this->notFound('سند یافت نشد.');
        }

        $path = self::documentsDir() . '/' . $doc['stored_filename'];
        if (is_file($path)) {
            @unlink($path);
        }
        Database::instance()->delete('documents', 'id = :id', ['id' => (int)$id]);
        AuditService::log('document_deleted', 'documents', (int)$id, $doc, null);

        return $this->back('success', 'سند حذف شد.');
    }

    private static function documentsDir(): string
    {
        $dir = rtrim((string)Config::get('app.storage_path'), '/') . '/documents';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new BusinessException('امکان ایجاد پوشه ذخیره‌سازی اسناد وجود ندارد.', 'STORAGE_ERROR', 500);
        }
        return $dir;
    }

    /**
     * فایل سند را — مشابه دقیق منطق UploadService — با بررسی پسوند/حجم/MIME
     * اعتبارسنجی و در storage/documents (خارج از مسیر عمومی) ذخیره می‌کند.
     * @return array{original_filename:string, stored_filename:string, mime_type:string, file_size:int}
     */
    private function storeDocumentFile(array $file): array
    {
        if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new BusinessException('بارگذاری فایل ناموفق بود.', 'UPLOAD_ERROR', 422);
        }

        $maxSize = (int)Config::get('app.uploads.max_size', 5242880);
        $size    = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxSize) {
            throw new BusinessException('حجم فایل باید بین ۱ بایت تا ' . round($maxSize / 1048576, 1) . ' مگابایت باشد.', 'FILE_TOO_LARGE', 422);
        }

        $original  = (string)($file['name'] ?? 'file');
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowed   = (array)Config::get('app.uploads.allowed_ext', []);
        if ($extension === '' || !in_array($extension, $allowed, true)) {
            throw new BusinessException('نوع فایل مجاز نیست. فرمت‌های مجاز: ' . implode(', ', $allowed), 'INVALID_EXTENSION', 422);
        }

        $mime = \App\Services\UploadService::detectMime((string)$file['tmp_name']);
        $allowedMimes = (array)Config::get('app.uploads.allowed_mimes', []);
        if (!in_array($mime, $allowedMimes, true)) {
            Logger::security('Blocked document upload MIME', ['mime' => $mime, 'name' => $original]);
            throw new BusinessException('محتوای فایل با فرمت مجاز مطابقت ندارد.', 'INVALID_MIME', 422);
        }

        $dir = self::documentsDir();
        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
        $fullPath   = $dir . '/' . $storedName;

        $moved = is_uploaded_file((string)$file['tmp_name'])
            ? move_uploaded_file((string)$file['tmp_name'], $fullPath)
            : rename((string)$file['tmp_name'], $fullPath);
        if (!$moved) {
            throw new BusinessException('ذخیره فایل ناموفق بود.', 'STORAGE_ERROR', 500);
        }
        @chmod($fullPath, 0644);

        return [
            'original_filename' => mb_substr($original, 0, 255),
            'stored_filename'   => $storedName,
            'mime_type'         => $mime,
            'file_size'         => $size,
        ];
    }
}
