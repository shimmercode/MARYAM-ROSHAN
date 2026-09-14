<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\ExportService;
use App\Services\ImportService;

/**
 * Two-phase CSV import: preview (validate, nothing written) then commit.
 * The validated payload is held in the session between the two steps so the
 * user never has to upload twice.
 */
final class ImportController extends BaseController
{
    private const SESSION_KEY = 'import.pending';
    private const MAX_BYTES   = 4 * 1024 * 1024;

    public function index(Request $request): Response
    {
        $this->authorize('import.manage');

        return $this->view('admin/import/index', [
            'title'    => 'ورود اطلاعات از فایل',
            'entities' => ImportService::ENTITIES,
            'columns'  => array_map(
                static fn ($e) => ImportService::columns($e),
                array_combine(array_keys(ImportService::ENTITIES), array_keys(ImportService::ENTITIES))
            ),
            'history'  => ImportService::history(15),
            'branches' => Database::instance()->select("SELECT id, name FROM branches WHERE deleted_at IS NULL AND status='ACTIVE' ORDER BY name"),
        ]);
    }

    public function template(Request $request, string $entity): Response
    {
        $this->authorize('import.manage');
        if (!isset(ImportService::ENTITIES[$entity])) {
            $this->notFound('قالب یافت نشد.');
        }
        return Response::download(
            ImportService::template($entity),
            'template-' . $entity . '.csv',
            'text/csv; charset=UTF-8'
        );
    }

    public function preview(Request $request): Response
    {
        $this->authorize('import.manage');
        $entity = $request->str('entity');
        if (!isset(ImportService::ENTITIES[$entity])) {
            return $this->back('error', 'نوع اطلاعات انتخاب‌شده معتبر نیست.');
        }

        $file = $request->file('file');
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $this->back('error', 'فایلی انتخاب نشده است یا آپلود ناقص بوده است.');
        }
        if ((int)$file['size'] > self::MAX_BYTES) {
            return $this->back('error', 'حجم فایل نباید بیشتر از ۴ مگابایت باشد.');
        }
        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'], true)) {
            return $this->back('error', 'فقط فایل CSV پشتیبانی می‌شود. فایل اکسل را با فرمت CSV UTF-8 ذخیره کنید.');
        }

        $parsed = ImportService::parse((string)$file['tmp_name']);
        if ($parsed['rows'] === []) {
            return $this->back('error', 'هیچ سطر داده‌ای در فایل پیدا نشد.');
        }

        $report = ImportService::validateRows($entity, $parsed['rows']);

        Session::set(self::SESSION_KEY, [
            'entity'   => $entity,
            'filename' => (string)$file['name'],
            'valid'    => $report['valid'],
            'created'  => time(),
        ]);

        return $this->view('admin/import/preview', [
            'title'    => 'پیش‌نمایش ورود اطلاعات',
            'entity'   => $entity,
            'label'    => ImportService::ENTITIES[$entity],
            'filename' => (string)$file['name'],
            'columns'  => ImportService::columns($entity),
            'headers'  => $parsed['headers'],
            'valid'    => array_slice($report['valid'], 0, 100),
            'invalid'  => $report['invalid'],
            'summary'  => $report['summary'],
            'branches' => Database::instance()->select("SELECT id, name FROM branches WHERE deleted_at IS NULL AND status='ACTIVE' ORDER BY name"),
        ]);
    }

    public function commit(Request $request): Response
    {
        $this->authorize('import.manage');
        $pending = Session::get(self::SESSION_KEY);

        if (!is_array($pending) || empty($pending['valid'])) {
            return $this->redirect('/admin/import', 'error', 'داده‌ای برای ثبت وجود ندارد. دوباره فایل را بارگذاری کنید.');
        }
        if (time() - (int)$pending['created'] > 1800) {
            Session::forget(self::SESSION_KEY);
            return $this->redirect('/admin/import', 'error', 'زمان پیش‌نمایش منقضی شده است. فایل را دوباره بارگذاری کنید.');
        }

        $result = ImportService::commit(
            (string)$pending['entity'],
            $pending['valid'],
            (string)$pending['filename'],
            $request->int('branch_id') ?? 1
        );
        Session::forget(self::SESSION_KEY);

        $message = sprintf(
            'ورود اطلاعات انجام شد: %d رکورد جدید، %d به‌روزرسانی، %d ناموفق.',
            $result['imported'],
            $result['updated'],
            $result['failed']
        );

        return $this->redirect('/admin/import', $result['failed'] > 0 ? 'warning' : 'success', $message);
    }

    /** Downloads the rejected rows of the last preview so they can be fixed offline. */
    public function errors(Request $request): Response
    {
        $this->authorize('import.manage');
        $batch = Database::instance()->selectOne(
            'SELECT entity, filename, errors FROM import_batches ORDER BY id DESC LIMIT 1'
        );
        $rows = $batch !== null ? (json_decode((string)$batch['errors'], true) ?: []) : [];
        $csv  = array_map(static fn ($r) => ['سطر' => $r['line'] ?? '', 'خطا' => $r['error'] ?? ''], $rows);

        return Response::download(
            ExportService::csv($csv, ['سطر', 'خطا']),
            ExportService::filename('import-errors'),
            'text/csv; charset=UTF-8'
        );
    }
}
