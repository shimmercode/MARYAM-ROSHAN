-- =============================================================================
-- داده‌های اولیه سالن زیبایی مریم روشن
-- نقش‌ها، دسترسی‌ها، تنظیمات، شعبه، سطوح وفاداری، خدمات و کارکنان نمونه
-- رمز عبور کاربران نمونه در نصاب ساخته می‌شود (هرگز هش ثابت در مخزن نیست).
-- =============================================================================

SET NAMES utf8mb4;

/* --------------------------------- نقش‌ها --------------------------------- */
INSERT INTO roles (slug, name, description, is_system) VALUES
  ('SUPER_ADMIN',    'مدیر ارشد',        'دسترسی کامل به تمام بخش‌های سامانه', 1),
  ('ADMIN',          'مدیر',             'مدیریت کامل به‌جز تنظیمات حساس سیستم', 1),
  ('BRANCH_MANAGER', 'مدیر شعبه',        'مدیریت عملیات یک شعبه', 1),
  ('RECEPTION',      'پذیرش',            'ثبت نوبت، مشتری و صدور فاکتور', 1),
  ('SPECIALIST',     'متخصص',            'مشاهده برنامه کاری و نوبت‌های خود', 1),
  ('CUSTOMER',       'مشتری',            'پرتال مشتریان', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

/* ------------------------------- دسترسی‌ها ------------------------------- */
INSERT INTO permissions (slug, name, module) VALUES
  ('dashboard.view',      'مشاهده داشبورد',            'dashboard'),
  ('customers.view',      'مشاهده مشتریان',            'customers'),
  ('customers.create',    'ثبت مشتری',                 'customers'),
  ('customers.edit',      'ویرایش مشتری',              'customers'),
  ('customers.delete',    'حذف مشتری',                 'customers'),
  ('appointments.view',   'مشاهده نوبت‌ها',            'appointments'),
  ('appointments.create', 'ثبت نوبت',                  'appointments'),
  ('appointments.edit',   'ویرایش نوبت',               'appointments'),
  ('appointments.cancel', 'لغو نوبت',                  'appointments'),
  ('services.view',       'مشاهده خدمات',              'services'),
  ('services.create',     'ثبت خدمت',                  'services'),
  ('services.edit',       'ویرایش خدمت',               'services'),
  ('services.delete',     'حذف خدمت',                  'services'),
  ('staff.view',          'مشاهده کارکنان',            'staff'),
  ('staff.create',        'ثبت کارمند',                'staff'),
  ('staff.edit',          'ویرایش کارمند',             'staff'),
  ('staff.delete',        'حذف کارمند',                'staff'),
  ('finance.view',        'مشاهده امور مالی',          'finance'),
  ('finance.create',      'صدور فاکتور و ثبت پرداخت',  'finance'),
  ('finance.refund',      'بازپرداخت و ابطال فاکتور',  'finance'),
  ('inventory.view',      'مشاهده انبار',              'inventory'),
  ('inventory.manage',    'مدیریت انبار',              'inventory'),
  ('marketing.view',      'مشاهده بازاریابی',          'marketing'),
  ('marketing.manage',    'مدیریت کمپین و اتوماسیون',  'marketing'),
  ('reports.view',        'مشاهده گزارش‌ها',           'reports'),
  ('commissions.view',    'مشاهده کمیسیون',            'staff'),
  ('commissions.manage',  'مدیریت و تسویه کمیسیون',    'staff'),
  ('reviews.manage',      'مدیریت نظرات',              'marketing'),
  ('settings.manage',     'مدیریت تنظیمات',            'system'),
  ('users.manage',        'مدیریت کاربران و نقش‌ها',   'system'),
  ('audit.view',          'مشاهده گزارش ممیزی',        'system'),
  ('backup.manage',       'پشتیبان‌گیری و بازیابی',    'system')
ON DUPLICATE KEY UPDATE name = VALUES(name);

/* مدیر ارشد: همه دسترسی‌ها */
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.slug = 'SUPER_ADMIN';

/* مدیر: همه به‌جز پشتیبان‌گیری و مدیریت کاربران */
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'ADMIN' AND p.slug NOT IN ('backup.manage');

/* مدیر شعبه */
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'BRANCH_MANAGER' AND p.slug IN (
  'dashboard.view','customers.view','customers.create','customers.edit',
  'appointments.view','appointments.create','appointments.edit','appointments.cancel',
  'services.view','staff.view','staff.edit','finance.view','finance.create',
  'inventory.view','inventory.manage','marketing.view','reports.view','commissions.view','reviews.manage');

/* پذیرش */
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'RECEPTION' AND p.slug IN (
  'dashboard.view','customers.view','customers.create','customers.edit',
  'appointments.view','appointments.create','appointments.edit','appointments.cancel',
  'services.view','staff.view','finance.view','finance.create');

/* متخصص */
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'SPECIALIST' AND p.slug IN (
  'appointments.view','appointments.edit','customers.view','services.view','commissions.view');

/* ------------------------------- تنظیمات -------------------------------- */
INSERT INTO settings (setting_key, setting_value, type, group_name, label, is_public) VALUES
  ('salon_name',        'سالن زیبایی مریم روشن', 'STRING', 'general', 'نام سالن', 1),
  ('salon_tagline',     'زیبایی شما، تخصص ما',   'STRING', 'general', 'شعار', 1),
  ('salon_phone',       '۰۲۱-۸۸۷۷۶۶۵۵',         'STRING', 'general', 'تلفن تماس', 1),
  ('salon_mobile',      '09121234567',           'STRING', 'general', 'موبایل', 1),
  ('salon_email',       'info@maryamroshan.ir',  'STRING', 'general', 'ایمیل', 1),
  ('salon_address',     'تهران، سعادت‌آباد، بلوار دریا، پلاک ۱۲۰', 'STRING', 'general', 'آدرس', 1),
  ('salon_instagram',   'maryamroshan.salon',    'STRING', 'general', 'اینستاگرام', 1),
  ('currency',          'تومان',                 'STRING', 'general', 'واحد پول', 1),
  ('tax_rate',          '9',                     'DECIMAL','finance', 'نرخ مالیات (٪)', 0),
  ('invoice_footer',    'از اعتماد شما سپاسگزاریم.', 'TEXT', 'finance', 'پاورقی فاکتور', 0),
  ('loyalty_earn_rate', '1',                     'DECIMAL','loyalty', 'امتیاز به ازای هر ۱۰۰۰ تومان', 0),
  ('loyalty_point_value','100',                  'DECIMAL','loyalty', 'ارزش ریالی هر امتیاز', 0),
  ('loyalty_max_redeem_percent','30',            'DECIMAL','loyalty', 'حداکثر درصد پرداخت با امتیاز', 0),
  ('loyalty_birthday_points','200',              'INT',    'loyalty', 'امتیاز هدیه تولد', 0),
  ('loyalty_referral_points','300',              'INT',    'loyalty', 'امتیاز معرفی دوست', 0),
  ('booking_min_lead_minutes','60',              'INT',    'booking', 'حداقل فاصله رزرو (دقیقه)', 0),
  ('booking_max_advance_days','60',              'INT',    'booking', 'حداکثر رزرو آینده (روز)', 0),
  ('reminder_hours_before','24',                 'INT',    'booking', 'ارسال یادآوری (ساعت قبل)', 0),
  ('sms_provider',      'log',                   'STRING', 'sms',     'سرویس پیامک', 0),
  ('sms_sender',        '10008000',              'STRING', 'sms',     'شماره فرستنده', 0),
  ('meta_title',        'سالن زیبایی مریم روشن | خدمات تخصصی زیبایی در تهران', 'STRING', 'seo', 'عنوان سئو', 1),
  ('meta_description',  'خدمات تخصصی مو، پوست، ناخن و میکاپ با بهترین متخصصان در سالن زیبایی مریم روشن. رزرو آنلاین نوبت.', 'TEXT', 'seo', 'توضیحات سئو', 1)
ON DUPLICATE KEY UPDATE label = VALUES(label);

/* --------------------------------- شعبه ---------------------------------- */
INSERT INTO branches (id, name, slug, phone, address, city, province, opening_time, closing_time, working_days, status) VALUES
  (1, 'شعبه مرکزی سعادت‌آباد', 'saadatabad', '۰۲۱-۸۸۷۷۶۶۵۵', 'تهران، سعادت‌آباد، بلوار دریا، پلاک ۱۲۰', 'تهران', 'تهران', '09:00:00', '21:00:00', '0,1,2,3,4,5', 'ACTIVE')
ON DUPLICATE KEY UPDATE name = VALUES(name);

/* ----------------------------- سطوح وفاداری ------------------------------ */
INSERT INTO loyalty_tiers (name, slug, min_points, discount_percent, point_multiplier, color, benefits, sort_order) VALUES
  ('برنزی',  'bronze', 0,    0,  1.00, '#b08d57', 'عضویت پایه و جمع‌آوری امتیاز', 1),
  ('نقره‌ای','silver', 1000, 5,  1.10, '#aab1b8', '۵٪ تخفیف دائمی روی خدمات', 2),
  ('طلایی',  'gold',   3000, 10, 1.25, '#c9a227', '۱۰٪ تخفیف، اولویت رزرو نوبت', 3),
  ('ویژه',   'vip',    8000, 15, 1.50, '#7b2d52', '۱۵٪ تخفیف، هدیه تولد و مشاوره رایگان', 4)
ON DUPLICATE KEY UPDATE discount_percent = VALUES(discount_percent);

/* ------------------------------ روش پرداخت ------------------------------- */
INSERT INTO payment_methods (slug, name, is_online, status, sort_order) VALUES
  ('CASH', 'نقدی', 0, 'ACTIVE', 1), ('POS', 'کارتخوان', 0, 'ACTIVE', 2),
  ('TRANSFER', 'کارت به کارت', 0, 'ACTIVE', 3), ('WALLET', 'کیف پول', 0, 'ACTIVE', 4),
  ('ONLINE', 'پرداخت آنلاین', 1, 'ACTIVE', 5)
ON DUPLICATE KEY UPDATE name = VALUES(name);

/* ---------------------------- دسته‌بندی خدمات ---------------------------- */
INSERT INTO service_categories (id, name, slug, icon, sort_order, description) VALUES
  (1, 'خدمات مو',     'hair',    '💇‍♀️', 1, 'کوتاهی، رنگ، کراتین و مراقبت تخصصی مو'),
  (2, 'خدمات پوست',   'skin',    '✨',   2, 'پاکسازی، فیشیال و جوان‌سازی پوست'),
  (3, 'ناخن',         'nails',   '💅',   3, 'مانیکور، پدیکور و کاشت ناخن'),
  (4, 'میکاپ',        'makeup',  '💄',   4, 'میکاپ عروس، مجلسی و آموزش آرایش'),
  (5, 'اصلاح و ابرو', 'brows',   '🪒',   5, 'اصلاح صورت، طراحی و میکروبلیدینگ ابرو')
ON DUPLICATE KEY UPDATE name = VALUES(name);

/* --------------------------------- خدمات --------------------------------- */
INSERT INTO services (id, category_id, name, slug, short_description, duration_minutes, buffer_minutes, price, cost, commission_type, commission_value, loyalty_points, gender, online_booking, is_featured, status) VALUES
  (1, 1, 'کوتاهی مو بانوان',        'hair-cut',        'کوتاهی تخصصی متناسب با فرم صورت', 45,  10, 3500000,  400000,  'PERCENT', 20, 30, 'FEMALE', 1, 1, 'ACTIVE'),
  (2, 1, 'رنگ مو و مش',             'hair-color',      'رنگ‌آمیزی حرفه‌ای با مواد بدون آمونیاک', 150, 15, 12000000, 3500000, 'PERCENT', 18, 120, 'FEMALE', 1, 1, 'ACTIVE'),
  (3, 1, 'کراتینه و صافی مو',       'keratin',         'صاف‌کننده و ترمیم‌کننده تخصصی مو', 180, 20, 18000000, 5500000, 'PERCENT', 15, 180, 'FEMALE', 1, 1, 'ACTIVE'),
  (4, 2, 'پاکسازی صورت',            'facial-cleaning', 'پاکسازی عمیق پوست با دستگاه', 60,  10, 4500000,  700000,  'PERCENT', 20, 45, 'FEMALE', 1, 0, 'ACTIVE'),
  (5, 2, 'هیدرافیشیال',             'hydrafacial',     'آبرسانی و شفافیت فوری پوست', 75,  15, 8500000,  1800000, 'PERCENT', 18, 85, 'FEMALE', 1, 1, 'ACTIVE'),
  (6, 3, 'مانیکور و لاک ژل',        'manicure-gel',    'مانیکور کامل همراه با لاک ژل', 60,  10, 3200000,  450000,  'PERCENT', 25, 32, 'FEMALE', 1, 0, 'ACTIVE'),
  (7, 3, 'کاشت ناخن',               'nail-extension',  'کاشت ناخن با متریال درجه یک', 120, 15, 7500000,  1500000, 'PERCENT', 22, 75, 'FEMALE', 1, 1, 'ACTIVE'),
  (8, 4, 'میکاپ عروس',              'bridal-makeup',   'میکاپ کامل عروس به همراه شینیون', 240, 30, 35000000, 6000000, 'PERCENT', 20, 350, 'FEMALE', 1, 1, 'ACTIVE'),
  (9, 4, 'میکاپ مجلسی',             'party-makeup',    'آرایش مجلسی سبک و ماندگار', 90, 15, 9500000,  1200000, 'PERCENT', 20, 95, 'FEMALE', 1, 0, 'ACTIVE'),
  (10, 5, 'اصلاح صورت و ابرو',      'face-brows',      'بند و ابرو با اصلاح فرم تخصصی', 30, 10, 1800000, 150000, 'PERCENT', 25, 18, 'FEMALE', 1, 0, 'ACTIVE'),
  (11, 5, 'میکروبلیدینگ ابرو',      'microblading',    'طراحی تار به تار ابرو با ماندگاری بالا', 120, 20, 22000000, 4500000, 'PERCENT', 15, 220, 'FEMALE', 1, 1, 'ACTIVE')
ON DUPLICATE KEY UPDATE price = VALUES(price);

INSERT IGNORE INTO service_branches (service_id, branch_id)
SELECT id, 1 FROM services;

/* ------------------------------ دسته کالاها ------------------------------ */
INSERT INTO product_categories (id, name, slug) VALUES
  (1, 'مواد مصرفی مو', 'hair-supplies'),
  (2, 'مواد مصرفی پوست', 'skin-supplies'),
  (3, 'مواد مصرفی ناخن', 'nail-supplies')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO products (id, category_id, name, sku, unit, purchase_price, sale_price, reorder_level, is_retail, status) VALUES
  (1, 1, 'رنگ مو حرفه‌ای (تیوب ۱۰۰ میلی)', 'HC-100', 'عدد', 350000, 0,       10, 0, 'ACTIVE'),
  (2, 1, 'اکسیدان ۹٪',                      'OX-9',   'لیتر', 180000, 0,       5,  0, 'ACTIVE'),
  (3, 1, 'محلول کراتین',                    'KR-500', 'میلی‌لیتر', 4200, 0,   500,0, 'ACTIVE'),
  (4, 2, 'ماسک آبرسان صورت',                'SK-MSK', 'عدد', 220000, 450000, 15, 1, 'ACTIVE'),
  (5, 3, 'پودر کاشت ناخن',                  'NL-PWD', 'گرم', 9500,  0,       200,0, 'ACTIVE'),
  (6, 1, 'شامپو تخصصی موی رنگ‌شده',         'SH-CLR', 'عدد', 480000, 890000, 12, 1, 'ACTIVE')
ON DUPLICATE KEY UPDATE sale_price = VALUES(sale_price);

INSERT IGNORE INTO inventory (product_id, branch_id, quantity) VALUES
  (1, 1, 24), (2, 1, 18), (3, 1, 4000), (4, 1, 30), (5, 1, 1500), (6, 1, 20);

/* دستور مصرف مواد برای خدمات (کسر خودکار موجودی) */
INSERT IGNORE INTO service_recipes (id, service_id, name) VALUES
  (1, 2, 'مواد رنگ مو'), (2, 3, 'مواد کراتین'), (3, 7, 'مواد کاشت ناخن');
INSERT IGNORE INTO recipe_items (recipe_id, product_id, quantity, unit) VALUES
  (1, 1, 1,   'عدد'), (1, 2, 0.15, 'لیتر'),
  (2, 3, 120, 'میلی‌لیتر'),
  (3, 5, 35,  'گرم');

/* ------------------------------ قوانین رزرو ------------------------------ */
INSERT IGNORE INTO booking_rules (branch_id, min_lead_minutes, max_advance_days, slot_step_minutes, cancellation_hours, allow_online_booking, require_deposit, deposit_percent)
VALUES (NULL, 60, 60, 15, 6, 1, 0, 0);

/* ---------------------------- قوانین کمیسیون ----------------------------- */
INSERT IGNORE INTO commission_rules (name, scope, scope_id, calc_type, value, priority, status) VALUES
  ('کمیسیون پیش‌فرض خدمات', 'GLOBAL', NULL, 'PERCENT', 18, 100, 'ACTIVE');

/* ------------------------- معیارهای ارزیابی (۱۷) ------------------------- */
INSERT INTO evaluation_criteria (code, name, weight, source, calculation, max_score, sort_order) VALUES
  ('REVENUE',        'میزان فروش دوره',              10, 'AUTO',   'revenue',        100, 1),
  ('SERVICE_COUNT',  'تعداد خدمات ارائه‌شده',        8,  'AUTO',   'service_count',  100, 2),
  ('CUSTOMER_RATING','میانگین امتیاز مشتریان',       10, 'AUTO',   'rating',         100, 3),
  ('REBOOK_RATE',    'نرخ رزرو مجدد مشتریان',        9,  'AUTO',   'rebook_rate',    100, 4),
  ('RETENTION',      'حفظ مشتریان ثابت',             8,  'AUTO',   'retention',      100, 5),
  ('PUNCTUALITY',    'وقت‌شناسی و حضور به‌موقع',     7,  'AUTO',   'punctuality',    100, 6),
  ('UPSELL',         'فروش مکمل و محصولات',          6,  'AUTO',   'upsell',         100, 7),
  ('NO_SHOW_MGMT',   'مدیریت عدم حضور مشتری',        5,  'AUTO',   'no_show',        100, 8),
  ('HYGIENE',        'رعایت بهداشت و ایمنی',         8,  'MANUAL', NULL,             100, 9),
  ('TEAMWORK',       'کار تیمی و همکاری',            6,  'MANUAL', NULL,             100, 10),
  ('COMMUNICATION',  'مهارت ارتباط با مشتری',        7,  'MANUAL', NULL,             100, 11),
  ('SKILL_QUALITY',  'کیفیت فنی کار',                10, 'MANUAL', NULL,             100, 12),
  ('LEARNING',       'یادگیری و به‌روزرسانی مهارت',  4,  'MANUAL', NULL,             100, 13),
  ('DISCIPLINE',     'نظم و رعایت مقررات',           5,  'MANUAL', NULL,             100, 14),
  ('MATERIAL_USE',   'مصرف بهینه مواد',              4,  'AUTO',   'material_use',   100, 15),
  ('COMPLAINTS',     'تعداد شکایات ثبت‌شده',         6,  'AUTO',   'complaints',     100, 16),
  ('SURVEY_NPS',     'امتیاز نظرسنجی (NPS)',         7,  'SURVEY', 'nps',            100, 17)
ON DUPLICATE KEY UPDATE name = VALUES(name), weight = VALUES(weight);

/* --------------------------- قالب‌های پیام‌رسانی -------------------------- */
INSERT INTO message_templates (code, name, channel, subject, body, variables, status) VALUES
  ('APPOINTMENT_CONFIRMED', 'تأیید نوبت', 'SMS', NULL,
   '{name} عزیز، نوبت شما در {salon} ثبت شد. منتظر دیدار شما هستیم.', 'name,salon', 'ACTIVE'),
  ('APPOINTMENT_REMINDER',  'یادآوری نوبت', 'SMS', NULL,
   '{name} عزیز، یادآوری نوبت فردای شما در {salon}. لغو یا تغییر: تماس با سالن.', 'name,salon', 'ACTIVE'),
  ('APPOINTMENT_CANCELLED', 'لغو نوبت', 'SMS', NULL,
   '{name} عزیز، نوبت شما در {salon} لغو شد. برای رزرو مجدد با ما تماس بگیرید.', 'name,salon', 'ACTIVE'),
  ('BIRTHDAY',              'تبریک تولد', 'SMS', NULL,
   '{first} عزیز، تولدت مبارک! هدیه امتیاز تولد به حساب شما در {salon} اضافه شد.', 'first,salon', 'ACTIVE'),
  ('INVOICE_ISSUED',        'صدور فاکتور', 'SMS', NULL,
   '{name} عزیز، فاکتور شما در {salon} صادر شد. سپاس از اعتماد شما.', 'name,salon', 'ACTIVE'),
  ('WIN_BACK',              'بازگشت مشتری', 'SMS', NULL,
   '{first} عزیز، دلمان برایتان تنگ شده! یک تخفیف ویژه در {salon} منتظر شماست.', 'first,salon', 'ACTIVE'),
  ('REVIEW_REQUEST',        'درخواست نظر', 'SMS', NULL,
   '{first} عزیز، از تجربه امروزتان در {salon} راضی بودید؟ نظرتان برای ما ارزشمند است.', 'first,salon', 'ACTIVE')
ON DUPLICATE KEY UPDATE body = VALUES(body);

/* ----------------------------- برچسب مشتریان ----------------------------- */
INSERT INTO customer_tags (name, color) VALUES
  ('مشتری وفادار', '#c9a227'), ('حساسیت پوستی', '#c0392b'),
  ('معرفی‌شده', '#2c6fb5'), ('عروس', '#a44861'), ('نیازمند پیگیری', '#b7791f')
ON DUPLICATE KEY UPDATE color = VALUES(color);

/* ------------------------------ صفحات سایت ------------------------------- */
INSERT INTO pages (slug, title, body, meta_title, meta_description, status) VALUES
  ('about', 'درباره ما',
   '<p>سالن زیبایی مریم روشن با بیش از ۱۵ سال تجربه در ارائه خدمات تخصصی زیبایی، با تکیه بر تیمی از متخصصان مجرب و استفاده از مواد و تجهیزات روز دنیا، در خدمت بانوان تهران است.</p>',
   'درباره سالن زیبایی مریم روشن', 'آشنایی با تیم و خدمات سالن زیبایی مریم روشن در سعادت‌آباد تهران.', 'PUBLISHED'),
  ('terms', 'قوانین و مقررات',
   '<p>رزرو نوبت تنها با تأیید سالن قطعی می‌شود. لغو نوبت تا ۶ ساعت پیش از زمان مقرر بدون جریمه امکان‌پذیر است.</p>',
   'قوانین سالن زیبایی مریم روشن', 'شرایط رزرو، لغو و بازپرداخت در سالن زیبایی مریم روشن.', 'PUBLISHED'),
  ('privacy', 'حریم خصوصی',
   '<p>اطلاعات شخصی مشتریان نزد ما محفوظ است و تنها برای ارائه خدمات و اطلاع‌رسانی استفاده می‌شود.</p>',
   'حریم خصوصی', 'سیاست حفظ حریم خصوصی مشتریان سالن زیبایی مریم روشن.', 'PUBLISHED')
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO posts (slug, title, excerpt, body, tags, status, published_at) VALUES
  ('keratin-aftercare', 'مراقبت از مو پس از کراتینه',
   'برای ماندگاری بیشتر کراتین، ۷۲ ساعت اول اهمیت ویژه‌ای دارد.',
   '<p>پس از انجام کراتینه، تا ۷۲ ساعت از شستشو، بستن و گیره زدن مو خودداری کنید. استفاده از شامپوی بدون سولفات ماندگاری نتیجه را تا دو برابر افزایش می‌دهد.</p>',
   'مو,کراتین,مراقبت', 'PUBLISHED', NOW()),
  ('skin-winter-care', 'مراقبت از پوست در فصل سرد',
   'خشکی پوست در زمستان با چند عادت ساده قابل پیشگیری است.',
   '<p>در فصل سرد، آبرسانی روزانه و استفاده از ضدآفتاب حتی در روزهای ابری ضروری است. پاکسازی ماهانه به بازسازی سد دفاعی پوست کمک می‌کند.</p>',
   'پوست,زمستان,آبرسانی', 'PUBLISHED', NOW()),
  ('bridal-checklist', 'چک‌لیست زیبایی عروس',
   'برنامه‌ریزی سه‌ماهه پیش از مراسم، تفاوت را می‌سازد.',
   '<p>سه ماه پیش از مراسم مشاوره پوست، دو ماه قبل تست میکاپ و یک هفته پیش از عروسی آخرین جلسه مراقبت پوست را رزرو کنید.</p>',
   'عروس,میکاپ,برنامه', 'PUBLISHED', NOW())
ON DUPLICATE KEY UPDATE title = VALUES(title);

/* --------------------------- اتوماسیون بازاریابی -------------------------- */
INSERT IGNORE INTO automations (id, name, trigger_event, description, status) VALUES
  (1, 'تأیید نوبت برای مشتری', 'appointment_created', 'ارسال پیامک تأیید بلافاصله پس از ثبت نوبت', 'ACTIVE'),
  (2, 'هدیه تولد مشتریان',     'birthday',            'ارسال پیام تبریک و افزودن امتیاز هدیه', 'ACTIVE'),
  (3, 'بازگشت مشتریان غیرفعال','customer_inactive',   'پیام تشویقی برای مشتریان بدون مراجعه در ۹۰ روز', 'ACTIVE');

INSERT IGNORE INTO automation_actions (automation_id, action_type, params, delay_minutes, sort_order) VALUES
  (1, 'SEND_SMS',   '{"template":"APPOINTMENT_CONFIRMED"}', 0, 1),
  (2, 'SEND_SMS',   '{"template":"BIRTHDAY"}', 0, 1),
  (2, 'ADD_POINTS', '{"points":200,"description":"هدیه تولد"}', 0, 2),
  (3, 'SEND_SMS',   '{"template":"WIN_BACK"}', 0, 1),
  (3, 'ADD_TAG',    '{"tag":"نیازمند پیگیری"}', 0, 2);

INSERT IGNORE INTO automation_rules (automation_id, field, operator, value) VALUES
  (3, 'days_since_last_visit', 'GTE', '90'),
  (3, 'marketing_opt_in', 'EQ', '1');
