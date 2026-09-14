<?php
declare(strict_types=1);

/** Router, response envelope and booking-rule tests (no database). */

use App\Core\Exceptions\HttpException;
use App\Core\Response;
use App\Core\Router;
use App\Services\AppointmentService;

T::suite('مسیریاب');

$router = new Router();
$router->get('/', 'Public_\HomeController@index');
$router->get('/services/{slug}', 'Public_\HomeController@service');
$router->post('/admin/customers', 'Admin\CustomerController@store');
$router->group(['prefix' => '/admin', 'middleware' => ['Auth']], function ($r): void {
    $r->get('/customers/{id}', 'Admin\CustomerController@show');
});

$m = $router->match('GET', '/');
T::same('Public_\HomeController@index', $m['handler'], 'مسیر ریشه پیدا می‌شود');

$m = $router->match('GET', '/services/hair-cut');
T::same('hair-cut', $m['params']['slug'], 'پارامتر مسیر استخراج می‌شود');

$m = $router->match('GET', '/services/کوتاهی-مو');
T::same('کوتاهی-مو', $m['params']['slug'], 'پارامتر فارسی در URL پشتیبانی می‌شود');

$m = $router->match('GET', '/admin/customers/42');
T::same('42', $m['params']['id'], 'مسیر گروهی با پیشوند کار می‌کند');
T::ok(in_array('Auth', $m['middleware'], true), 'میان‌افزار گروه اعمال می‌شود');

T::throws(static fn () => $router->match('GET', '/no-such-page'), HttpException::class, 'مسیر ناموجود ۴۰۴ می‌دهد');

try {
    $router->match('DELETE', '/admin/customers');
} catch (HttpException $e) {
    T::same(405, $e->status(), 'متد اشتباه ۴۰۵ می‌دهد نه ۴۰۴');
}

T::suite('پوشش پاسخ API');

$ok = json_decode(Response::success(['id' => 1], ['total' => 5])->body(), true);
T::ok($ok['success'] === true, 'پاسخ موفق پرچم success دارد');
T::same(1, $ok['data']['id'], 'داده در کلید data قرار می‌گیرد');
T::same(5, $ok['meta']['total'], 'متادیتا ضمیمه می‌شود');

$errResponse = Response::error('DOUBLE_BOOKING', 'این بازه زمانی رزرو شده است.', 409);
$err = json_decode($errResponse->body(), true);
T::ok($err['success'] === false, 'پاسخ خطا پرچم success=false دارد');
T::same('DOUBLE_BOOKING', $err['error']['code'], 'کد خطای ماشین‌خوان موجود است');
T::same(409, $errResponse->status(), 'کد وضعیت HTTP درست است');
T::ok(str_contains($err['error']['message'], 'رزرو'), 'پیام خطا فارسی است');

T::suite('قوانین نوبت‌دهی');

T::same(0, AppointmentService::weekdayIndex('2024-03-23'), 'شنبه اندیس ۰ دارد');
T::same(6, AppointmentService::weekdayIndex('2024-03-22'), 'جمعه اندیس ۶ دارد');
T::same(1, AppointmentService::weekdayIndex('2024-03-24'), 'یکشنبه اندیس ۱ دارد');

T::ok(isset(AppointmentService::STATUSES['CONFIRMED']), 'وضعیت CONFIRMED تعریف شده است');
T::same('تکمیل شده', AppointmentService::statusLabel('COMPLETED'), 'برچسب فارسی وضعیت');

$transitions = AppointmentService::TRANSITIONS;
T::ok(in_array('CONFIRMED', $transitions['PENDING'], true), 'PENDING به CONFIRMED می‌رود');
T::ok(!in_array('COMPLETED', $transitions['PENDING'], true), 'PENDING مستقیم COMPLETED نمی‌شود');
T::same([], $transitions['COMPLETED'], 'COMPLETED وضعیت نهایی است');
T::same([], $transitions['CANCELLED'], 'CANCELLED وضعیت نهایی است');

T::suite('پیامک بدون کلید سرویس');

$provider = new \App\Services\Sms\LogSmsProvider();
$result = $provider->send('09121234567', 'پیام آزمایشی');
T::ok($result['success'] === true, 'ارائه‌دهنده لاگ بدون کلید کار می‌کند');
T::ok(!empty($result['reference']), 'شناسه پیگیری پیام برگردانده می‌شود');
T::same('log', $provider->name(), 'نام ارائه‌دهنده پیش‌فرض log است');
