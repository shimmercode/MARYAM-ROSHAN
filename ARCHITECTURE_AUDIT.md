# ممیزی معماری پورتال مریم روشن

**تاریخ:** ۱۴۰۵/۰۶/۲۳ (2026-09-14)  
**نسخه بررسی‌شده:** ZIP آپلودشده در `origin/main`، commit `6a84c3b`

## خلاصه اجرایی

نسخه استخراج‌شده اکنون در workspace بررسی شد. پروژه یک PHP MVC اختصاصی و بدون فریم‌ورک سنگین است و بخش بزرگی از نیازمندی‌های پایه را از قبل دارد. بنابراین مسیر درست، تکمیل و اتصال ماژول‌های موجود است، نه بازنویسی از صفر.

> فایل `.env` استخراج نشد و در workspace/commit قرار نگرفت تا credentialها افشا نشوند. فقط `.env.example` استفاده می‌شود.

## 1. Architecture Audit واقعی

### Stack و اجرا

- PHP 8.2+، MySQL/MariaDB، PDO و prepared statements
- MVC سبک اختصاصی: `app/core`, `controllers`, `services`, `repositories`, `views`
- Frontend فعلی: Bootstrap 5.3 RTL + CSS اختصاصی و Vanilla ES Modules/Fetch؛ Chart.js محلی
- `public/` به‌عنوان web root و `.htaccess` برای routing
- بدون Composer و بدون build اجباری؛ `package.json` فقط وابستگی `@php-wasm/node` دارد
- نصب چهارمرحله‌ای در `/install` و قفل `storage/installed.lock`

### ساختار موجود

```text
app/
  core/          App, Router, Request, Response, Database, Session, Csrf, View, Logger
  controllers/   admin, api, customer, staff, public_, install
  services/      منطق کسب‌وکار
  repositories/  query layer
  middleware/    Auth, Guest, Csrf, Role, Permission, RateLimit, Installed
  helpers/       Format, Jalali, functions
  views/         قالب‌های RTL
config/ database/ cron/ design/ public/ routes/ storage/ tests/ tools/
```

### قابلیت‌های حاضر

- وب‌سایت عمومی و SEO routes
- Login/Auth، session، CSRF، rate limit، نقش و permission
- پنل مدیریت: dashboard، مشتری، نوبت، خدمات، کارکنان، POS، فاکتور، انبار، بازاریابی، گزارش، import، settings
- پرتال پرسنل: برنامه، نوبت‌ها، پورسانت، عملکرد و مشتری
- پرتال مشتری
- API داخلی `/api/v1`
- پرداخت، تخفیف، وفاداری، پورسانت، گزارش و audit
- فرم‌های موجود در پرونده پرسنل: آفر در لحظه، سفر مشتری، گزارش مدیریت مشتری و ارزیابی عملکرد
- طراحی مرجع HTML در `design/maryamroshan.html`؛ این فایل حاوی داده‌های demo و CDN است و منبع داده نهایی نیست.

## 2. Database Analysis

`database/schema.sql` یک schema نسبتاً گسترده با حدود ۱۱۴ جدول است. گروه‌های اصلی:

```text
auth: users, roles, permissions, sessions, login_attempts, audit_logs
branches: branches, branch_settings
crm: customers, profiles, notes, tags, loyalty, referrals
services: categories, services, pricing, packages, branches
hr: staff, profiles, specialties, staff_services, shifts, attendance, leaves
performance: commissions, evaluations, instant_offers, customer_journey_entries
booking: appointments, items, status_history, reminders, waitlist
operations: resources, resource_availability, resource_allocations
finance: invoices, payments, refunds, taxes, discounts, wallets
```

نقاط مثبت: foreign key و indexهای اصلی، `DECIMAL` برای پول، timestamps، history برای appointment و audit log. داده‌های seed باید در محیط توسعه با داده production قاطی نشود.

### Gap مهم نسبت به Live Status

در schema فعلی `attendance`، `staff_shifts` و appointment وجود دارد، اما برای جریان کامل حضور زنده این موارد به‌صورت مستقل مشاهده نشد:

- heartbeat/`last_activity_at` پرسنل
- رویدادهای شروع/پایان خدمت
- جدول seat/resource با تخصیص live به پرسنل
- current customer/service روی presence
- endpoint امن heartbeat و endpoint incremental live dashboard

`resources` برای منابع رزرو وجود دارد، اما جایگزین قطعی مدل seat عملیاتی نیست و باید با بررسی migration/منطق فعلی توسعه یابد.

## 3. Data Relationship Map

```text
users -> user_roles -> roles -> role_permissions -> permissions
users -> staff
staff -> staff_profiles / staff_services / staff_shifts / attendance / leaves
staff -> appointments -> customers
appointments -> appointment_items -> services
branches -> staff / services / appointments / resources
appointments -> invoices -> payments / refunds
staff -> commissions / evaluations / activity forms
all sensitive operations -> audit_logs
```

تمام قابلیت‌های جدید live باید بر اساس `staff.id` به این زنجیره متصل شود؛ نباید نام پرسنل یا status در frontend به‌صورت hard-coded ذخیره شود.

## 4. Live Status Architecture پیشنهادی

```text
Staff portal login/activity
  -> authenticated heartbeat + attendance/service events
  -> live_presence / service_sessions / seat_assignments
  -> LiveStatusService (server time + shift + attendance + appointment + leave)
  -> /api/v1/admin/live-status (JSON, changed_since/ETag)
  -> dashboard partial update + alerts
```

Status precedence پیشنهادی:

1. inactive/disabled
2. leave/absent
3. outside shift
4. break
5. active service => در حال ارائه خدمت
6. present + online + no service => آزاد/آماده خدمت
7. shift active + stale activity => نیازمند بررسی

Polling ده‌ثانیه‌ای، پاسخ incremental و بدون reload کل صفحه برای hosting فعلی مناسب‌تر از WebSocket است.

## 5. Seat Management

مدل پیشنهادی مستقل:

```text
seats(id, branch_id, code, name, type, status, active)
seat_assignments(id, seat_id, staff_id, appointment_id, service_id,
                 customer_id, status, started_at, ended_at, updated_at)
```

هشدار: شیفت فعال + حضور مورد انتظار + بدون تخصیص/فعالیت پس از آستانه قابل تنظیم. تمام تغییرات باید audit و قابل ردیابی باشند.

## 6. RBAC و Security

معماری فعلی middlewareهای `Auth`, `Role`, `Permission`, `Csrf`, `RateLimit` دارد و از roleهای توسعه‌پذیر استفاده می‌کند. نقش‌های مستندشده: `SUPER_ADMIN`, `ADMIN`, `BRANCH_MANAGER`, `RECEPTION`, `SPECIALIST`, `CUSTOMER`.

مواردی که باید در QA واقعی تأیید شوند: scope شعبه، دسترسی employee به فقط رکورد خود، مسیرهای download/upload، secretهای `.env`، install lock، خروجی exception، و rate limit login/API.

## 7. Design System و UX

HTML مرجع و viewهای فعلی RTL و Vazirmatn هستند. design system فعلی بر CSS variables (`primary`, `accent`, `surface`, `border`, `text`, `success`, `warning`, `danger`) و componentهای اختصاصی استوار است. با این حال README استفاده از Bootstrap 5.3 را ثبت کرده و Tailwind در runtime وجود ندارد؛ بنابراین «بازنویسی کامل به Tailwind» بدون حذف assetها ضروری یا کم‌ریسک نیست. پیشنهاد: توکن‌ها و component contract فعلی حفظ، سپس utility layer تدریجی اضافه شود.

نقص قطعی مرجع: `design/maryamroshan.html` داده‌های ثابت در `const D` و credential نمایشی `admin/123456` دارد. این فایل فقط prototype است و نباید در production route یا data source استفاده شود.

## 8. Refactoring Plan

### مرحله 1 — baseline و hardening

- نصب PHP/MySQL در محیط اجرا و اجرای `tests/run.php`
- بررسی secret/credential و پاک‌سازی demo login از prototype
- route inventory و smoke test همه controllerهای موجود
- فعال‌سازی error log امن و جلوگیری از افشای exception

### مرحله 2 — Live Operations vertical slice

- migration برای presence، service session و seats
- heartbeat از staff portal
- controller/service/repository برای محاسبه status
- endpoint admin live status با auth/permission و polling incremental
- dashboard cards/list/feed/alerts واقعی از DB

### مرحله 3 — تکمیل HR/Attendance/Schedule

- clock-in/out و break eventها
- اتصال appointment start/end به service session
- وضعیت حضور، شیفت و مرخصی با timezone سرور

### مرحله 4 — UI system و performance

- استخراج componentهای تکراری view
- حفظ RTL/dark mode و ارتقای responsive
- pagination، debounce، query indexes و cache کنترل‌شده

### مرحله 5 — QA و تحویل

- سناریوهای login، shift، start/end service، absent، stale activity و contract alert
- تست امنیت، permission، upload/download، SQL و XSS
- تست desktop/tablet/mobile و regression تمام routeهای فعلی

## 9. وضعیت فعلی اجرا

- سورس ZIP از `origin/main` دریافت و استخراج شد.
- فایل `.env` عمداً وارد workspace نشد.
- اجرای تست محلی انجام نشد چون PHP در sandbox نصب نیست (`php: command not found`).
- هنوز هیچ mock status یا dashboard جعلی به سیستم production اضافه نشده است.

## 10. تصمیم اجرایی

از اینجا توسعه باید روی کد واقعی موجود ادامه یابد. اولین تغییر کدنویسی امن، vertical slice مربوط به live presence است؛ اما قبل از آن باید محیط PHP/MySQL یا یک DB تستی فراهم شود تا migration و تست integration قابل اعتبارسنجی باشد. هیچ ادعایی درباره موفقیت runtime بدون آن محیط پذیرفته نیست.
