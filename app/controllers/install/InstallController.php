<?php
declare(strict_types=1);

namespace App\Controllers\Install;

use App\Controllers\BaseController;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\InstallerService;
use App\Validators\Validator;

/**
 * Four-step installer. It writes .env, runs schema + seeds, creates the first
 * SUPER_ADMIN and finally drops storage/installed.lock, which disables itself.
 */
final class InstallController extends BaseController
{
    private InstallerService $installer;

    public function __construct()
    {
        $this->installer = new InstallerService();
    }

    public function welcome(Request $request): Response
    {
        return $this->installView('install/welcome', ['step' => 1, 'title' => 'نصب سامانه']);
    }

    public function requirements(Request $request): Response
    {
        $checks = $this->installer->requirements();
        return $this->installView('install/requirements', [
            'step'   => 2,
            'title'  => 'بررسی پیش‌نیازها',
            'checks' => $checks,
            'passed' => $this->installer->requirementsPassed(),
        ]);
    }

    public function database(Request $request): Response
    {
        return $this->installView('install/database', [
            'step'  => 3,
            'title' => 'اتصال به پایگاه داده',
        ]);
    }

    public function saveDatabase(Request $request): Response
    {
        $data = Validator::validate($request->all(), [
            'db_host' => 'required|string|max:120',
            'db_port' => 'required|int',
            'db_name' => 'required|string|max:64',
            'db_user' => 'required|string|max:64',
            'db_pass' => 'nullable|string|max:200',
            'app_url' => 'required|string|max:200',
        ], [
            'db_host' => 'میزبان', 'db_port' => 'پورت', 'db_name' => 'نام پایگاه داده',
            'db_user' => 'نام کاربری', 'app_url' => 'آدرس سایت',
        ]);

        $cfg = [
            'host'     => (string)$data['db_host'],
            'port'     => (string)$data['db_port'],
            'database' => (string)$data['db_name'],
            'username' => (string)$data['db_user'],
            'password' => (string)($data['db_pass'] ?? ''),
        ];

        // Throws BusinessException (handled globally) when the connection fails.
        $pdo   = $this->installer->testConnection($cfg);
        $stats = $this->installer->migrate($pdo, true);

        $this->installer->writeEnv([
            'APP_URL'   => rtrim((string)$data['app_url'], '/'),
            'DB_HOST'   => $cfg['host'],
            'DB_PORT'   => $cfg['port'],
            'DB_NAME'   => $cfg['database'],
            'DB_USER'   => $cfg['username'],
            'DB_PASS'   => $cfg['password'],
        ]);

        Session::set('install_db', $cfg);
        Session::flash('success', 'پایگاه داده با موفقیت ساخته شد (' . $stats['tables'] . ' جدول).');
        return $this->redirect('/install/admin');
    }

    public function admin(Request $request): Response
    {
        if (Session::get('install_db') === null) {
            return $this->redirect('/install/database', 'error', 'ابتدا اتصال پایگاه داده را تنظیم کنید.');
        }
        return $this->installView('install/admin', ['step' => 4, 'title' => 'ساخت حساب مدیر']);
    }

    public function saveAdmin(Request $request): Response
    {
        $cfg = Session::get('install_db');
        if (!is_array($cfg)) {
            return $this->redirect('/install/database', 'error', 'اطلاعات پایگاه داده یافت نشد.');
        }

        $data = Validator::validate($request->all(), [
            'first_name' => 'required|string|max:80',
            'last_name'  => 'required|string|max:80',
            'mobile'     => 'required|mobile',
            'email'      => 'nullable|email|max:150',
            'password'   => 'required|string|min:8|confirmed',
            'demo_data'  => 'nullable|bool',
        ], [
            'first_name' => 'نام', 'last_name' => 'نام خانوادگی',
            'mobile' => 'موبایل', 'password' => 'رمز عبور',
        ]);

        $pdo = $this->installer->testConnection($cfg);
        $this->installer->createAdmin($pdo, [
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'mobile'     => $data['mobile'],
            'email'      => $data['email'] ?? null,
            'password'   => $data['password'],
        ]);

        if ($request->bool('demo_data')) {
            $this->installer->seedDemoData($pdo);
        }

        $this->installer->lock();
        Session::forget('install_db');
        Session::set('install_done_mobile', $data['mobile']);

        return $this->redirect('/install/finish');
    }

    public function finish(Request $request): Response
    {
        // Reached right after lock(); the NotInstalled middleware is skipped here
        // on purpose so the success page can still be shown once.
        return Response::html(\App\Core\View::render('install/finish', [
            'step'   => 5,
            'title'  => 'نصب کامل شد',
            'mobile' => (static function () { $v = \App\Core\Session::get('install_done_mobile'); \App\Core\Session::forget('install_done_mobile'); return $v; })(),
        ]));
    }

    private function installView(string $template, array $data): Response
    {
        $flashes = Session::pullFlash();
        $errors  = Session::pullErrors();
        $old     = Session::oldInput();

        // The old()/error_for() template helpers read from View::sharedData(),
        // not from locally-passed template vars, so these must also be shared
        // here (the same way BaseController::view() does it) or every
        // validation error and old input value on the install wizard silently
        // fails to render — the form just looks like it reloaded blank.
        \App\Core\View::share('flashes', $flashes);
        \App\Core\View::share('errors', $errors);
        \App\Core\View::share('old', $old);

        return Response::html(\App\Core\View::render($template, array_merge([
            'flashes' => $flashes,
            'errors'  => $errors,
            'old'     => $old,
        ], $data)));
    }
}
