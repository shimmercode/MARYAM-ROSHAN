-- =============================================================================
-- ارتقا به نسخه ۰.۱.۴ — اجرای دستی روی نصب‌های موجود (مثلاً از طریق phpMyAdmin)
-- این اسکریپت idempotent است: هر بخش را می‌توان چند بار اجرا کرد بدون خطا.
-- =============================================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------------
-- ۱) آیین‌نامه و اسناد (تنظیمات › اسناد)
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS documents (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title             VARCHAR(200) NOT NULL,
  description       TEXT NULL,
  category          VARCHAR(30)  NOT NULL DEFAULT 'other',
  original_filename VARCHAR(255) NOT NULL,
  stored_filename   VARCHAR(255) NOT NULL,
  mime_type         VARCHAR(120) NOT NULL,
  file_size         INT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_by       INT UNSIGNED NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_documents_stored (stored_filename),
  KEY idx_documents_category (category),
  CONSTRAINT fk_documents_user FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- ۲) فرم آفر در لحظه (داخل پروفایل پرسنل)
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS instant_offers (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id           INT UNSIGNED NOT NULL,
  customer_id        INT UNSIGNED NULL,
  first_name         VARCHAR(80)  NULL,
  last_name          VARCHAR(80)  NULL,
  mobile             VARCHAR(15)  NULL,
  service_line       VARCHAR(120) NULL,
  source             VARCHAR(120) NULL,
  offer_line_1       VARCHAR(150) NULL,
  offer_line_2       VARCHAR(150) NULL,
  offer_line_3       VARCHAR(150) NULL,
  booking_date       DATE NULL,
  purchase_date      DATE NULL,
  next_purchase_date DATE NULL,
  notes              TEXT NULL,
  created_by         INT UNSIGNED NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_io_staff (staff_id),
  KEY idx_io_customer (customer_id),
  CONSTRAINT fk_io_staff    FOREIGN KEY (staff_id)    REFERENCES staff (id)    ON DELETE CASCADE,
  CONSTRAINT fk_io_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- ۳) گزارش سفر مشتریان پرسنل (داخل پروفایل پرسنل)
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS customer_journey_entries (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id           INT UNSIGNED NOT NULL,
  customer_id        INT UNSIGNED NULL,
  first_name         VARCHAR(80)  NULL,
  last_name          VARCHAR(80)  NULL,
  service_line       VARCHAR(120) NULL,
  source             VARCHAR(120) NULL,
  consulted_date       DATE NULL,
  appointment_date     DATE NULL,
  purchase_date        DATE NULL,
  satisfaction_date    DATE NULL,
  next_purchase_date   DATE NULL,
  referred_date        DATE NULL,
  notes              TEXT NULL,
  created_by         INT UNSIGNED NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cje_staff (staff_id),
  KEY idx_cje_customer (customer_id),
  CONSTRAINT fk_cje_staff    FOREIGN KEY (staff_id)    REFERENCES staff (id)     ON DELETE CASCADE,
  CONSTRAINT fk_cje_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- ۴) جدول مدیریت مشتریان (گزارش دوره‌ای مقایسه‌ای، داخل پروفایل پرسنل)
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS customer_management_reports (
  id                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id                 INT UNSIGNED NOT NULL,
  line_name                VARCHAR(120) NOT NULL,
  period_from              DATE NOT NULL,
  period_to                DATE NOT NULL,
  prev_period_from         DATE NULL,
  prev_period_to           DATE NULL,
  total_customers          INT UNSIGNED NULL,
  total_customers_prev     INT UNSIGNED NULL,
  new_customers            INT UNSIGNED NULL,
  new_customers_prev       INT UNSIGNED NULL,
  returning_customers      INT UNSIGNED NULL,
  returning_customers_prev INT UNSIGNED NULL,
  loyalty_percent          DECIMAL(5,2) NULL,
  loyalty_percent_prev     DECIMAL(5,2) NULL,
  sales_amount             DECIMAL(14,2) NULL,
  sales_amount_prev        DECIMAL(14,2) NULL,
  sales_count              INT UNSIGNED NULL,
  sales_count_prev         INT UNSIGNED NULL,
  salon_credit             DECIMAL(14,2) NULL,
  salon_credit_prev        DECIMAL(14,2) NULL,
  offer_amount             DECIMAL(14,2) NULL,
  offer_amount_prev        DECIMAL(14,2) NULL,
  offer_purchase           DECIMAL(14,2) NULL,
  offer_purchase_prev      DECIMAL(14,2) NULL,
  other_campaigns          VARCHAR(255) NULL,
  other_campaigns_prev     VARCHAR(255) NULL,
  rank_in_line             VARCHAR(60) NULL,
  rank_in_line_prev        VARCHAR(60) NULL,
  analysis                 TEXT NULL,
  created_by               INT UNSIGNED NULL,
  created_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cmr_staff (staff_id, period_to),
  CONSTRAINT fk_cmr_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- ۵) بسط ارزیابی عملکرد پرسنل: ستون‌های جدید روی performance_evaluations
--    و جدول فرزند ردیف‌های شاخص‌محور (رابطه رتبه‌بندی ۶، ۱۴۰۴)
--    توجه: ADD COLUMN IF NOT EXISTS نیازمند MySQL 8.0.29+ یا MariaDB 10.5+
--    است. در نسخه‌های قدیمی‌تر، اگر ستون از قبل نبود، دستور دستی زیر را بدون
--    IF NOT EXISTS اجرا کنید.
-- ------------------------------------------------------------------
ALTER TABLE performance_evaluations
  ADD COLUMN IF NOT EXISTS special_score DECIMAL(4,2) NOT NULL DEFAULT 0.00 AFTER total_score,
  ADD COLUMN IF NOT EXISTS rank_label VARCHAR(20) NULL AFTER level;

CREATE TABLE IF NOT EXISTS performance_evaluation_items (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  evaluation_id   BIGINT UNSIGNED NOT NULL,
  category        VARCHAR(150) NOT NULL,
  indicator       VARCHAR(500) NOT NULL,
  weight          TINYINT UNSIGNED NOT NULL DEFAULT 1,
  score           TINYINT UNSIGNED NULL,
  weighted_points DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  sort_order      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_pei_eval (evaluation_id),
  CONSTRAINT fk_pei_eval FOREIGN KEY (evaluation_id) REFERENCES performance_evaluations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
