<?php
declare(strict_types=1);

/** Pure-function tests: no database required. */

use App\Core\Csrf;
use App\Helpers\Format;
use App\Helpers\Jalali;

T::suite('تبدیل تاریخ جلالی');

T::same([1403, 1, 1], Jalali::toJalali(2024, 3, 20), 'نوروز ۱۴۰۳ برابر ۲۰ مارس ۲۰۲۴ است');
T::same([1399, 10, 11], Jalali::toJalali(2020, 12, 31), 'پایان ۲۰۲۰ برابر ۱۱ دی ۱۳۹۹ است');
T::same([2024, 3, 20], Jalali::toGregorian(1403, 1, 1), 'تبدیل معکوس به میلادی درست است');
T::same('1403/01/01', Format::toEnglishDigits(Jalali::format('2024-03-20', 'Y/m/d')), 'قالب‌بندی تاریخ');
T::same('2024-03-20', Jalali::parse('1403/01/01'), 'تجزیه تاریخ جلالی ورودی کاربر');
T::same(null, Jalali::parse('نامعتبر'), 'ورودی نامعتبر null برمی‌گرداند');
T::same('-', Jalali::format(null), 'تاریخ خالی خط تیره می‌شود');

T::suite('سال کبیسه جلالی');
T::ok(Jalali::isLeap(1403), '۱۴۰۳ کبیسه است');
T::ok(!Jalali::isLeap(1404), '۱۴۰۴ کبیسه نیست');
T::same(30, Jalali::daysInMonth(1403, 12), 'اسفند سال کبیسه ۳۰ روز است');
T::same(29, Jalali::daysInMonth(1404, 12), 'اسفند سال عادی ۲۹ روز است');
T::same(31, Jalali::daysInMonth(1403, 3), 'خرداد ۳۱ روز است');

T::suite('قالب‌بندی فارسی');
T::same('۱۲۳۴', Format::digits('1234'), 'تبدیل ارقام لاتین به فارسی');
T::same('1234', Format::toEnglishDigits('۱۲۳۴'), 'تبدیل ارقام فارسی به لاتین');
T::ok(str_contains(Format::money(1500000), 'تومان'), 'واحد پول در خروجی قیمت هست');
T::ok(Format::isValidMobile('09121234567'), 'شماره موبایل معتبر پذیرفته می‌شود');
T::ok(!Format::isValidMobile('12345'), 'شماره کوتاه رد می‌شود');
T::ok(!Format::isValidMobile('08121234567'), 'شماره با پیش‌شماره اشتباه رد می‌شود');
T::ok(Format::isValidMobile('۰۹۱۲۱۲۳۴۵۶۷'), 'شماره با ارقام فارسی هم معتبر است');
T::ok(str_contains(Format::duration(90), 'ساعت'), 'مدت زمان به فارسی نمایش داده می‌شود');
T::same('فا', Format::initials('فاطمه', 'احمدی'), 'حروف اول نام');
T::ok(str_contains(Format::maskMobile('09121234567'), '*'), 'شماره موبایل ماسک می‌شود');

T::suite('توابع کمکی سراسری');
T::same('&lt;script&gt;', e('<script>'), 'خروجی HTML امن‌سازی می‌شود');
T::ok(str_contains(slugify('کوتاهی مو بانوان'), 'کوتاهی'), 'اسلاگ فارسی حفظ می‌شود');
T::same('hair-cut', slugify('Hair  Cut!'), 'اسلاگ انگلیسی نرمال‌سازی می‌شود');
T::same(16, strlen(str_random(16)), 'رشته تصادفی طول درست دارد');
T::ok(str_random(10) !== str_random(10), 'رشته تصادفی تکراری نیست');

T::suite('اعتبارسنجی');
$rules = [
    'first_name' => 'required|string|max:10',
    'mobile'     => 'required|mobile',
    'email'      => 'nullable|email',
    'age'        => 'nullable|int|min:1',
];
$labels = ['first_name' => 'نام', 'mobile' => 'موبایل'];

$valid = \App\Validators\Validator::validate(
    ['first_name' => 'مریم', 'mobile' => '09121234567', 'email' => null, 'age' => '30'],
    $rules,
    $labels
);
T::same('مریم', $valid['first_name'], 'داده معتبر بدون تغییر عبور می‌کند');
T::same(30, $valid['age'], 'عدد به int تبدیل می‌شود');

T::throws(
    static fn () => \App\Validators\Validator::validate(['mobile' => 'x'], $rules, $labels),
    \App\Core\Exceptions\ValidationException::class,
    'داده نامعتبر استثنا می‌اندازد'
);

try {
    \App\Validators\Validator::validate(['first_name' => '', 'mobile' => '123'], $rules, $labels);
} catch (\App\Core\Exceptions\ValidationException $e) {
    $errors = $e->errors();
    T::ok(isset($errors['first_name']), 'خطای فیلد الزامی گزارش می‌شود');
    T::ok(isset($errors['mobile']), 'خطای فرمت موبایل گزارش می‌شود');
    T::ok(str_contains(implode(' ', (array)$errors['first_name']), 'نام'), 'پیام خطا از برچسب فارسی استفاده می‌کند');
}
