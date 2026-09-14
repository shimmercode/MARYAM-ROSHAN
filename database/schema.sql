-- =============================================================================
-- سالن زیبایی مریم روشن — ساختار پایگاه داده
-- MySQL 8+ / MariaDB 10.6+
-- همه مبالغ DECIMAL هستند. هرگز FLOAT برای پول استفاده نشده است.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- AUTH
-- =============================================================================

CREATE TABLE IF NOT EXISTS roles (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug            VARCHAR(50)  NOT NULL,
  name            VARCHAR(100) NOT NULL,
  description     VARCHAR(255) NULL,
  is_system       TINYINT(1)   NOT NULL DEFAULT 0,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug            VARCHAR(80)  NOT NULL,
  name            VARCHAR(120) NOT NULL,
  module          VARCHAR(50)  NOT NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_permissions_slug (slug),
  KEY idx_permissions_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id         INT UNSIGNED NOT NULL,
  permission_id   INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  KEY idx_rp_permission (permission_id),
  CONSTRAINT fk_rp_role       FOREIGN KEY (role_id)       REFERENCES roles (id)       ON DELETE CASCADE,
  CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  first_name          VARCHAR(80)  NOT NULL,
  last_name           VARCHAR(80)  NOT NULL,
  mobile              VARCHAR(15)  NOT NULL,
  email               VARCHAR(150) NULL,
  password_hash       VARCHAR(255) NOT NULL,
  avatar              VARCHAR(255) NULL,
  status              ENUM('ACTIVE','INACTIVE','SUSPENDED') NOT NULL DEFAULT 'ACTIVE',
  branch_id           INT UNSIGNED NULL,
  last_login_at       DATETIME     NULL,
  last_login_ip       VARCHAR(45)  NULL,
  password_changed_at DATETIME     NULL,
  must_change_password TINYINT(1)  NOT NULL DEFAULT 0,
  two_factor_enabled  TINYINT(1)   NOT NULL DEFAULT 0,
  remember_token      VARCHAR(255) NULL,
  remember_expires_at DATETIME     NULL,
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at          DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_mobile (mobile),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_status (status),
  KEY idx_users_branch (branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_roles (
  user_id   INT UNSIGNED NOT NULL,
  role_id   INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, role_id),
  KEY idx_ur_role (role_id),
  CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessions (
  id            VARCHAR(128) NOT NULL,
  user_id       INT UNSIGNED NULL,
  ip_address    VARCHAR(45)  NULL,
  user_agent    VARCHAR(255) NULL,
  payload       TEXT         NULL,
  last_activity DATETIME     NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sessions_user (user_id),
  KEY idx_sessions_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  identifier  VARCHAR(150) NOT NULL,
  ip_address  VARCHAR(45)  NOT NULL,
  user_agent  VARCHAR(255) NULL,
  successful  TINYINT(1)   NOT NULL DEFAULT 0,
  attempted_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_la_identifier (identifier, attempted_at),
  KEY idx_la_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS two_factor_auth (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  channel     ENUM('SMS','EMAIL','TOTP') NOT NULL DEFAULT 'SMS',
  code_hash   VARCHAR(255) NOT NULL,
  expires_at  DATETIME     NOT NULL,
  consumed_at DATETIME     NULL,
  attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tfa_user (user_id, expires_at),
  CONSTRAINT fk_tfa_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  token_hash  VARCHAR(255) NOT NULL,
  expires_at  DATETIME     NOT NULL,
  used_at     DATETIME     NULL,
  ip_address  VARCHAR(45)  NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pr_user (user_id),
  KEY idx_pr_token (token_hash(64)),
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NULL,
  action      VARCHAR(80)  NOT NULL,
  entity      VARCHAR(80)  NULL,
  entity_id   BIGINT UNSIGNED NULL,
  old_data    JSON         NULL,
  new_data    JSON         NULL,
  ip_address  VARCHAR(45)  NULL,
  user_agent  VARCHAR(255) NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_user (user_id, created_at),
  KEY idx_audit_entity (entity, entity_id),
  KEY idx_audit_action (action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- BRANCHES
-- =============================================================================

CREATE TABLE IF NOT EXISTS branches (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(120) NOT NULL,
  slug          VARCHAR(150) NOT NULL,
  phone         VARCHAR(20)  NULL,
  address       VARCHAR(255) NULL,
  city          VARCHAR(80)  NULL,
  province      VARCHAR(80)  NULL,
  postal_code   VARCHAR(20)  NULL,
  latitude      DECIMAL(10,7) NULL,
  longitude     DECIMAL(10,7) NULL,
  opening_time  TIME         NOT NULL DEFAULT '09:00:00',
  closing_time  TIME         NOT NULL DEFAULT '21:00:00',
  working_days  VARCHAR(20)  NOT NULL DEFAULT '0,1,2,3,4,5',  -- 0=Saturday .. 6=Friday
  manager_id    INT UNSIGNED NULL,
  status        ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at    DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_branches_slug (slug),
  KEY idx_branches_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS branch_settings (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  branch_id   INT UNSIGNED NOT NULL,
  setting_key VARCHAR(80)  NOT NULL,
  setting_value TEXT       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bs (branch_id, setting_key),
  CONSTRAINT fk_bs_branch FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- CUSTOMERS
-- =============================================================================

CREATE TABLE IF NOT EXISTS customers (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id            INT UNSIGNED NULL,
  code               VARCHAR(20)  NOT NULL,
  first_name         VARCHAR(80)  NOT NULL,
  last_name          VARCHAR(80)  NOT NULL,
  mobile             VARCHAR(15)  NOT NULL,
  email              VARCHAR(150) NULL,
  gender             ENUM('FEMALE','MALE','OTHER') NOT NULL DEFAULT 'FEMALE',
  birth_date         DATE         NULL,
  national_code      VARCHAR(10)  NULL,
  preferred_branch_id INT UNSIGNED NULL,
  preferred_staff_id INT UNSIGNED NULL,
  source             VARCHAR(50)  NULL,
  status             ENUM('ACTIVE','INACTIVE','BLACKLIST') NOT NULL DEFAULT 'ACTIVE',
  visits_count       INT UNSIGNED NOT NULL DEFAULT 0,
  total_spent        DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  last_visit_at      DATETIME     NULL,
  first_visit_at     DATETIME     NULL,
  loyalty_points     INT          NOT NULL DEFAULT 0,
  loyalty_tier_id    INT UNSIGNED NULL,
  health_score       TINYINT UNSIGNED NOT NULL DEFAULT 50,
  churn_risk         DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  marketing_opt_in   TINYINT(1)   NOT NULL DEFAULT 1,
  created_by         INT UNSIGNED NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at         DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customers_code (code),
  UNIQUE KEY uq_customers_mobile (mobile),
  KEY idx_customers_name (last_name, first_name),
  KEY idx_customers_status (status),
  KEY idx_customers_branch (preferred_branch_id),
  KEY idx_customers_lastvisit (last_visit_at),
  KEY idx_customers_tier (loyalty_tier_id),
  CONSTRAINT fk_customers_user   FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_customers_branch FOREIGN KEY (preferred_branch_id) REFERENCES branches (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_profiles (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id    INT UNSIGNED NOT NULL,
  skin_type      VARCHAR(50)  NULL,
  hair_type      VARCHAR(50)  NULL,
  allergies      TEXT         NULL,
  medical_notes  TEXT         NULL,
  preferences    TEXT         NULL,
  occupation     VARCHAR(80)  NULL,
  instagram      VARCHAR(80)  NULL,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cp_customer (customer_id),
  CONSTRAINT fk_cp_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_addresses (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id INT UNSIGNED NOT NULL,
  title       VARCHAR(50)  NOT NULL DEFAULT 'منزل',
  province    VARCHAR(80)  NULL,
  city        VARCHAR(80)  NULL,
  address     VARCHAR(255) NOT NULL,
  postal_code VARCHAR(20)  NULL,
  is_default  TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ca_customer (customer_id),
  CONSTRAINT fk_ca_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_notes (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NULL,
  note        TEXT         NOT NULL,
  is_pinned   TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cn_customer (customer_id, created_at),
  CONSTRAINT fk_cn_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_tags (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(60)  NOT NULL,
  color       VARCHAR(20)  NOT NULL DEFAULT '#C9A227',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ct_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_tag_map (
  customer_id INT UNSIGNED NOT NULL,
  tag_id      INT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (customer_id, tag_id),
  KEY idx_ctm_tag (tag_id),
  CONSTRAINT fk_ctm_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE,
  CONSTRAINT fk_ctm_tag      FOREIGN KEY (tag_id)      REFERENCES customer_tags (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_segments (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  rules       JSON         NULL,
  is_dynamic  TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cs_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loyalty_tiers (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug            VARCHAR(30)  NOT NULL,
  name            VARCHAR(60)  NOT NULL,
  min_points      INT UNSIGNED NOT NULL DEFAULT 0,
  discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  point_multiplier DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  color           VARCHAR(20)  NOT NULL DEFAULT '#C9A227',
  benefits        TEXT         NULL,
  sort_order      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lt_slug (slug),
  KEY idx_lt_points (min_points)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_loyalty (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id     INT UNSIGNED NOT NULL,
  tier_id         INT UNSIGNED NULL,
  points_balance  INT NOT NULL DEFAULT 0,
  points_earned   INT NOT NULL DEFAULT 0,
  points_redeemed INT NOT NULL DEFAULT 0,
  tier_changed_at DATETIME NULL,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cl_customer (customer_id),
  CONSTRAINT fk_cl_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE,
  CONSTRAINT fk_cl_tier     FOREIGN KEY (tier_id)     REFERENCES loyalty_tiers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loyalty_transactions (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id  INT UNSIGNED NOT NULL,
  type         ENUM('EARN','REDEEM','ADJUST','EXPIRE','REFERRAL','BIRTHDAY') NOT NULL,
  points       INT          NOT NULL,
  balance_after INT         NOT NULL DEFAULT 0,
  reference_type VARCHAR(40) NULL,
  reference_id BIGINT UNSIGNED NULL,
  description  VARCHAR(255) NULL,
  created_by   INT UNSIGNED NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ltx_customer (customer_id, created_at),
  KEY idx_ltx_ref (reference_type, reference_id),
  CONSTRAINT fk_ltx_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_health_scores (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id  INT UNSIGNED NOT NULL,
  score        TINYINT UNSIGNED NOT NULL,
  recency_score   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  frequency_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  monetary_score  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  computed_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_chs_customer (customer_id, computed_at),
  CONSTRAINT fk_chs_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS churn_predictions (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id   INT UNSIGNED NOT NULL,
  risk_score    DECIMAL(5,2) NOT NULL,
  risk_level    ENUM('LOW','MEDIUM','HIGH','CRITICAL') NOT NULL DEFAULT 'LOW',
  days_since_visit INT UNSIGNED NULL,
  reasons       VARCHAR(255) NULL,
  computed_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cpr_customer (customer_id, computed_at),
  KEY idx_cpr_level (risk_level),
  CONSTRAINT fk_cpr_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS referrals (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  referrer_id   INT UNSIGNED NOT NULL,
  referred_id   INT UNSIGNED NULL,
  code          VARCHAR(20)  NOT NULL,
  status        ENUM('PENDING','COMPLETED','EXPIRED') NOT NULL DEFAULT 'PENDING',
  reward_points INT UNSIGNED NOT NULL DEFAULT 0,
  completed_at  DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ref_code (code),
  KEY idx_ref_referrer (referrer_id),
  CONSTRAINT fk_ref_referrer FOREIGN KEY (referrer_id) REFERENCES customers (id) ON DELETE CASCADE,
  CONSTRAINT fk_ref_referred FOREIGN KEY (referred_id) REFERENCES customers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SERVICES
-- =============================================================================

CREATE TABLE IF NOT EXISTS service_categories (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(80)  NOT NULL,
  slug        VARCHAR(100) NOT NULL,
  icon        VARCHAR(50)  NULL,
  description VARCHAR(255) NULL,
  sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  status      ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sc_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id        INT UNSIGNED NOT NULL,
  name               VARCHAR(150) NOT NULL,
  slug               VARCHAR(180) NOT NULL,
  description        TEXT         NULL,
  short_description  VARCHAR(255) NULL,
  duration_minutes   SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  buffer_minutes     SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  price              DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  cost               DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  commission_type    ENUM('PERCENT','FIXED','NONE') NOT NULL DEFAULT 'PERCENT',
  commission_value   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  loyalty_points     INT UNSIGNED NOT NULL DEFAULT 0,
  image              VARCHAR(255) NULL,
  gender             ENUM('FEMALE','MALE','ANY') NOT NULL DEFAULT 'ANY',
  online_booking     TINYINT(1)   NOT NULL DEFAULT 1,
  requires_deposit   TINYINT(1)   NOT NULL DEFAULT 0,
  deposit_amount     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  is_featured        TINYINT(1)   NOT NULL DEFAULT 0,
  requires_resource  TINYINT(1)   NOT NULL DEFAULT 0,
  status             ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  meta_title         VARCHAR(180) NULL,
  meta_description   VARCHAR(255) NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at         DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_services_slug (slug),
  KEY idx_services_category (category_id),
  KEY idx_services_status (status),
  KEY idx_services_name (name),
  CONSTRAINT fk_services_category FOREIGN KEY (category_id) REFERENCES service_categories (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_packages (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(150) NOT NULL,
  slug        VARCHAR(180) NOT NULL,
  description TEXT         NULL,
  price       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  valid_days  SMALLINT UNSIGNED NOT NULL DEFAULT 90,
  status      ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sp_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS package_items (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  package_id  INT UNSIGNED NOT NULL,
  service_id  INT UNSIGNED NOT NULL,
  quantity    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pi (package_id, service_id),
  CONSTRAINT fk_pi_package FOREIGN KEY (package_id) REFERENCES service_packages (id) ON DELETE CASCADE,
  CONSTRAINT fk_pi_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_pricing (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  service_id  INT UNSIGNED NOT NULL,
  branch_id   INT UNSIGNED NULL,
  staff_id    INT UNSIGNED NULL,
  price       DECIMAL(12,2) NOT NULL,
  valid_from  DATE NULL,
  valid_to    DATE NULL,
  PRIMARY KEY (id),
  KEY idx_spr_service (service_id),
  CONSTRAINT fk_spr_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_branches (
  service_id INT UNSIGNED NOT NULL,
  branch_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (service_id, branch_id),
  KEY idx_sb_branch (branch_id),
  CONSTRAINT fk_sb_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE,
  CONSTRAINT fk_sb_branch  FOREIGN KEY (branch_id)  REFERENCES branches (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- STAFF
-- =============================================================================

CREATE TABLE IF NOT EXISTS staff (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        INT UNSIGNED NULL,
  code           VARCHAR(20)  NOT NULL,
  first_name     VARCHAR(80)  NOT NULL,
  last_name      VARCHAR(80)  NOT NULL,
  mobile         VARCHAR(15)  NOT NULL,
  email          VARCHAR(150) NULL,
  branch_id      INT UNSIGNED NULL,
  job_title      VARCHAR(80)  NULL,
  employment_type ENUM('FULL_TIME','PART_TIME','CONTRACT') NOT NULL DEFAULT 'FULL_TIME',
  hire_date      DATE         NULL,
  base_salary    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  commission_type    ENUM('PERCENT','FIXED','TIERED','NONE') NOT NULL DEFAULT 'PERCENT',
  commission_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  online_booking TINYINT(1)   NOT NULL DEFAULT 1,
  color          VARCHAR(20)  NOT NULL DEFAULT '#C9A227',
  avatar         VARCHAR(255) NULL,
  bio            TEXT         NULL,
  rating         DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  status         ENUM('ACTIVE','INACTIVE','ON_LEAVE') NOT NULL DEFAULT 'ACTIVE',
  is_public      TINYINT(1)   NOT NULL DEFAULT 1,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at     DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_staff_code (code),
  UNIQUE KEY uq_staff_mobile (mobile),
  KEY idx_staff_branch (branch_id),
  KEY idx_staff_status (status),
  CONSTRAINT fk_staff_user   FOREIGN KEY (user_id)   REFERENCES users (id)    ON DELETE SET NULL,
  CONSTRAINT fk_staff_branch FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_profiles (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id      INT UNSIGNED NOT NULL,
  national_code VARCHAR(10)  NULL,
  birth_date    DATE         NULL,
  address       VARCHAR(255) NULL,
  emergency_contact VARCHAR(120) NULL,
  emergency_phone   VARCHAR(20)  NULL,
  bank_account  VARCHAR(40)  NULL,
  instagram     VARCHAR(80)  NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stp_staff (staff_id),
  CONSTRAINT fk_stp_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_specialties (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id INT UNSIGNED NOT NULL,
  title    VARCHAR(80) NOT NULL,
  level    ENUM('JUNIOR','MID','SENIOR','MASTER') NOT NULL DEFAULT 'MID',
  PRIMARY KEY (id),
  KEY idx_ss_staff (staff_id),
  KEY idx_ss_title (title),
  CONSTRAINT fk_ss_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_services (
  staff_id   INT UNSIGNED NOT NULL,
  service_id INT UNSIGNED NOT NULL,
  custom_price DECIMAL(12,2) NULL,
  custom_duration SMALLINT UNSIGNED NULL,
  commission_percent DECIMAL(5,2) NULL,
  PRIMARY KEY (staff_id, service_id),
  KEY idx_sts_service (service_id),
  CONSTRAINT fk_sts_staff   FOREIGN KEY (staff_id)   REFERENCES staff (id)    ON DELETE CASCADE,
  CONSTRAINT fk_sts_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shift_templates (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(80) NOT NULL,
  start_time TIME NOT NULL,
  end_time   TIME NOT NULL,
  break_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_weekly_shifts (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id   INT UNSIGNED NOT NULL,
  weekday    TINYINT UNSIGNED NOT NULL,      -- 0=Saturday … 6=Friday
  start_time TIME NOT NULL,
  end_time   TIME NOT NULL,
  break_start TIME NULL,
  break_end   TIME NULL,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sws (staff_id, weekday),
  CONSTRAINT fk_sws_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_shifts (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id   INT UNSIGNED NOT NULL,
  branch_id  INT UNSIGNED NOT NULL,
  shift_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time   TIME NOT NULL,
  break_start TIME NULL,
  break_end   TIME NULL,
  status     ENUM('SCHEDULED','COMPLETED','CANCELLED') NOT NULL DEFAULT 'SCHEDULED',
  notes      VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_shift (staff_id, shift_date, start_time),
  KEY idx_shift_date (shift_date, branch_id),
  CONSTRAINT fk_shift_staff  FOREIGN KEY (staff_id)  REFERENCES staff (id)    ON DELETE CASCADE,
  CONSTRAINT fk_shift_branch FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id   INT UNSIGNED NOT NULL,
  work_date  DATE NOT NULL,
  check_in   DATETIME NULL,
  check_out  DATETIME NULL,
  worked_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  late_minutes   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  status     ENUM('PRESENT','ABSENT','LATE','LEAVE','HOLIDAY') NOT NULL DEFAULT 'PRESENT',
  notes      VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_att (staff_id, work_date),
  KEY idx_att_date (work_date),
  CONSTRAINT fk_att_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leaves (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id   INT UNSIGNED NOT NULL,
  type       ENUM('ANNUAL','SICK','UNPAID','MISSION','OTHER') NOT NULL DEFAULT 'ANNUAL',
  start_date DATE NOT NULL,
  end_date   DATE NOT NULL,
  start_time TIME NULL,
  end_time   TIME NULL,
  reason     VARCHAR(255) NULL,
  status     ENUM('PENDING','APPROVED','REJECTED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  approved_by INT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_leave_staff (staff_id, start_date, end_date),
  KEY idx_leave_status (status),
  CONSTRAINT fk_leave_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commission_rules (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(120) NOT NULL,
  scope        ENUM('GLOBAL','SERVICE','STAFF','BRANCH','CATEGORY') NOT NULL DEFAULT 'GLOBAL',
  scope_id     INT UNSIGNED NULL,
  calc_type    ENUM('PERCENT','FIXED','TIERED') NOT NULL DEFAULT 'PERCENT',
  value        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  tiers        JSON NULL,
  priority     TINYINT UNSIGNED NOT NULL DEFAULT 10,
  valid_from   DATE NULL,
  valid_to     DATE NULL,
  status       ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cr_scope (scope, scope_id, status),
  KEY idx_cr_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commissions (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id       INT UNSIGNED NOT NULL,
  invoice_id     BIGINT UNSIGNED NULL,
  invoice_item_id BIGINT UNSIGNED NULL,
  service_id     INT UNSIGNED NULL,
  rule_id        INT UNSIGNED NULL,
  base_amount    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  calc_type      ENUM('PERCENT','FIXED','TIERED') NOT NULL DEFAULT 'PERCENT',
  calc_value     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  amount         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  status         ENUM('PENDING','APPROVED','PAID','CANCELLED') NOT NULL DEFAULT 'PENDING',
  period         CHAR(7) NULL,               -- YYYY-MM
  paid_at        DATETIME NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_com_staff (staff_id, period),
  KEY idx_com_invoice (invoice_id),
  KEY idx_com_status (status),
  CONSTRAINT fk_com_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluation_criteria (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code         VARCHAR(50)  NOT NULL,
  name         VARCHAR(150) NOT NULL,
  description  VARCHAR(255) NULL,
  weight       DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  source       ENUM('AUTO','MANUAL','SURVEY') NOT NULL DEFAULT 'MANUAL',
  calculation  VARCHAR(80)  NULL,
  max_score    TINYINT UNSIGNED NOT NULL DEFAULT 100,
  sort_order   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  status       ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (id),
  UNIQUE KEY uq_ec_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS performance_evaluations (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id     INT UNSIGNED NOT NULL,
  period_start DATE NOT NULL,
  period_end   DATE NOT NULL,
  total_score  DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  special_score DECIMAL(4,2) NOT NULL DEFAULT 0.00,  -- امتیاز ویژه مدیریت (حداکثر ۴ امتیاز)
  level        ENUM('EXCELLENT','GREAT','GOOD','AVERAGE','WEAK') NOT NULL DEFAULT 'AVERAGE',
  rank_label   VARCHAR(20) NULL,                     -- ممتاز/عالی/خوب/متوسط/ضعیف — طبق جدول رتبه‌بندی
  summary      TEXT NULL,
  evaluated_by INT UNSIGNED NULL,
  status       ENUM('DRAFT','FINAL') NOT NULL DEFAULT 'DRAFT',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pe_staff (staff_id, period_start),
  CONSTRAINT fk_pe_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ردیف‌های شاخص‌محورِ فرم ارزیابی عملکرد و جدول امتیازبندی پرسنل (نسخه ۶، ۱۴۰۴):
-- هر ردیف یک شاخص با ضریب مشخص است؛ امتیاز خام هر ردیف بین ۰ تا ۳ (ضعیف=۰، متوسط=۱، خوب=۲، عالی=۳)
-- و weighted_points = score * weight است. مجموع همه ردیف‌ها + امتیاز ویژه مدیریت = نتیجه عملکرد از ۱۰۰.
CREATE TABLE IF NOT EXISTS performance_evaluation_items (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  evaluation_id   BIGINT UNSIGNED NOT NULL,
  category        VARCHAR(150) NOT NULL,
  indicator       VARCHAR(500) NOT NULL,
  weight          TINYINT UNSIGNED NOT NULL DEFAULT 1,
  score           TINYINT UNSIGNED NULL,             -- ۰ تا ۳
  weighted_points DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  sort_order      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_pei_eval (evaluation_id),
  CONSTRAINT fk_pei_eval FOREIGN KEY (evaluation_id) REFERENCES performance_evaluations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluation_scores (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  evaluation_id BIGINT UNSIGNED NOT NULL,
  criterion_id  INT UNSIGNED NOT NULL,
  raw_score     DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  weighted_score DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  note          VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_es (evaluation_id, criterion_id),
  CONSTRAINT fk_es_eval      FOREIGN KEY (evaluation_id) REFERENCES performance_evaluations (id) ON DELETE CASCADE,
  CONSTRAINT fk_es_criterion FOREIGN KEY (criterion_id)  REFERENCES evaluation_criteria (id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluation_actions (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  evaluation_id BIGINT UNSIGNED NOT NULL,
  action        VARCHAR(255) NOT NULL,
  due_date      DATE NULL,
  status        ENUM('OPEN','DONE','CANCELLED') NOT NULL DEFAULT 'OPEN',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ea_eval (evaluation_id),
  CONSTRAINT fk_ea_eval FOREIGN KEY (evaluation_id) REFERENCES performance_evaluations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ردیف‌های «فرم آفر در لحظه» به ازای هر پرسنل. برای مراجعه‌کننده‌های تازه (بدون
-- پرونده مشتری) نام/نام‌خانوادگی/موبایل مستقیماً ثبت می‌شود.
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

-- ردیف‌های «گزارش سفر مشتریان پرسنل» (نسخه ۲ — بدون موبایل، چون در پرونده مشتری موجود است).
CREATE TABLE IF NOT EXISTS customer_journey_entries (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id           INT UNSIGNED NOT NULL,
  customer_id        INT UNSIGNED NULL,
  first_name         VARCHAR(80)  NULL,
  last_name          VARCHAR(80)  NULL,
  service_line       VARCHAR(120) NULL,
  source             VARCHAR(120) NULL,
  consulted_date       DATE NULL,   -- مشاوره انجام‌شده
  appointment_date     DATE NULL,   -- نوبت‌دهی مشتری
  purchase_date        DATE NULL,   -- خرید مشتری
  satisfaction_date    DATE NULL,   -- کسب رضایت مشتری
  next_purchase_date   DATE NULL,   -- نوبت خرید بعدی
  referred_date         DATE NULL,  -- ارجاع به مدیر داخلی جهت لاین دیگر
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

-- «جدول مدیریت مشتریان» — مقایسه دوره گزارش با دوره قبل به ازای هر لاین خدمات پرسنل.
-- برخی شاخص‌ها (تعداد/رقم فروش) از داده سیستم قابل محاسبه‌اند و برخی (آفر، رتبه، کمپین)
-- در منبع اصلی به‌صورت دستی تکمیل می‌شدند؛ این جدول برای ورود/ثبت دستی دوره‌ای طراحی شده است.
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

-- =============================================================================
-- RESOURCES
-- =============================================================================

CREATE TABLE IF NOT EXISTS resources (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  branch_id INT UNSIGNED NOT NULL,
  name      VARCHAR(100) NOT NULL,
  type      ENUM('ROOM','CHAIR','DEVICE','OTHER') NOT NULL DEFAULT 'ROOM',
  capacity  TINYINT UNSIGNED NOT NULL DEFAULT 1,
  status    ENUM('ACTIVE','MAINTENANCE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (id),
  KEY idx_res_branch (branch_id),
  CONSTRAINT fk_res_branch FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resource_availability (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  resource_id INT UNSIGNED NOT NULL,
  weekday     TINYINT UNSIGNED NOT NULL,
  start_time  TIME NOT NULL,
  end_time    TIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_ra_resource (resource_id, weekday),
  CONSTRAINT fk_ra_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- APPOINTMENTS
-- =============================================================================

CREATE TABLE IF NOT EXISTS booking_rules (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  branch_id   INT UNSIGNED NULL,
  min_lead_minutes    SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  max_advance_days    SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  slot_step_minutes   SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  cancellation_hours  SMALLINT UNSIGNED NOT NULL DEFAULT 6,
  allow_online_booking TINYINT(1) NOT NULL DEFAULT 1,
  require_deposit     TINYINT(1) NOT NULL DEFAULT 0,
  deposit_percent     DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (id),
  KEY idx_br_branch (branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appointments (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code           VARCHAR(20)  NOT NULL,
  customer_id    INT UNSIGNED NOT NULL,
  staff_id       INT UNSIGNED NOT NULL,
  branch_id      INT UNSIGNED NOT NULL,
  resource_id    INT UNSIGNED NULL,
  appointment_date DATE      NOT NULL,
  start_time     TIME        NOT NULL,
  end_time       TIME        NOT NULL,
  starts_at      DATETIME    NOT NULL,
  ends_at        DATETIME    NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  buffer_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  status         ENUM('PENDING','CONFIRMED','CHECKED_IN','IN_PROGRESS','COMPLETED','CANCELLED','NO_SHOW') NOT NULL DEFAULT 'PENDING',
  source         ENUM('ADMIN','ONLINE','PHONE','WALK_IN') NOT NULL DEFAULT 'ADMIN',
  total_price    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  deposit_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  notes          VARCHAR(500) NULL,
  cancel_reason  VARCHAR(255) NULL,
  cancelled_at   DATETIME NULL,
  completed_at   DATETIME NULL,
  reminder_sent_at DATETIME NULL,
  invoice_id     BIGINT UNSIGNED NULL,
  created_by     INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_app_code (code),
  KEY idx_app_staff_time (staff_id, appointment_date, start_time),
  KEY idx_app_customer (customer_id, appointment_date),
  KEY idx_app_branch_date (branch_id, appointment_date),
  KEY idx_app_status (status, appointment_date),
  KEY idx_app_range (starts_at, ends_at),
  KEY idx_app_resource (resource_id, appointment_date),
  KEY idx_app_reminder (reminder_sent_at, starts_at),
  CONSTRAINT fk_app_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_app_staff    FOREIGN KEY (staff_id)    REFERENCES staff (id)     ON DELETE RESTRICT,
  CONSTRAINT fk_app_branch   FOREIGN KEY (branch_id)   REFERENCES branches (id)  ON DELETE RESTRICT,
  CONSTRAINT fk_app_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appointment_items (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  appointment_id BIGINT UNSIGNED NOT NULL,
  service_id     INT UNSIGNED NOT NULL,
  staff_id       INT UNSIGNED NULL,
  price          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  quantity       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_ai_appointment (appointment_id),
  KEY idx_ai_service (service_id),
  CONSTRAINT fk_ai_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE CASCADE,
  CONSTRAINT fk_ai_service     FOREIGN KEY (service_id)     REFERENCES services (id)     ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appointment_status_history (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  appointment_id BIGINT UNSIGNED NOT NULL,
  from_status    VARCHAR(20) NULL,
  to_status      VARCHAR(20) NOT NULL,
  note           VARCHAR(255) NULL,
  changed_by     INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ash_appointment (appointment_id, created_at),
  CONSTRAINT fk_ash_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appointment_reminders (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  appointment_id BIGINT UNSIGNED NOT NULL,
  channel        ENUM('SMS','EMAIL','INAPP') NOT NULL DEFAULT 'SMS',
  scheduled_at   DATETIME NOT NULL,
  sent_at        DATETIME NULL,
  status         ENUM('PENDING','SENT','FAILED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  error          VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY idx_ar_schedule (status, scheduled_at),
  KEY idx_ar_appointment (appointment_id),
  CONSTRAINT fk_ar_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS waitlist (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id INT UNSIGNED NOT NULL,
  service_id  INT UNSIGNED NULL,
  staff_id    INT UNSIGNED NULL,
  branch_id   INT UNSIGNED NULL,
  preferred_date DATE NULL,
  preferred_time_from TIME NULL,
  preferred_time_to   TIME NULL,
  status      ENUM('WAITING','NOTIFIED','BOOKED','EXPIRED') NOT NULL DEFAULT 'WAITING',
  notes       VARCHAR(255) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_wl_status (status, preferred_date),
  CONSTRAINT fk_wl_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resource_allocations (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  resource_id    INT UNSIGNED NOT NULL,
  appointment_id BIGINT UNSIGNED NOT NULL,
  starts_at      DATETIME NOT NULL,
  ends_at        DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_rall_resource (resource_id, starts_at, ends_at),
  CONSTRAINT fk_rall_resource    FOREIGN KEY (resource_id)    REFERENCES resources (id)    ON DELETE CASCADE,
  CONSTRAINT fk_rall_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- FINANCE
-- =============================================================================

CREATE TABLE IF NOT EXISTS payment_methods (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug     VARCHAR(30)  NOT NULL,
  name     VARCHAR(60)  NOT NULL,
  icon     VARCHAR(40)  NULL,
  is_online TINYINT(1)  NOT NULL DEFAULT 0,
  status   ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pm_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS taxes (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name     VARCHAR(60) NOT NULL,
  rate     DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  status   ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS discounts (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(120) NOT NULL,
  type          ENUM('PERCENT','FIXED') NOT NULL DEFAULT 'PERCENT',
  value         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  max_amount    DECIMAL(12,2) NULL,
  min_order     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  scope         ENUM('ALL','SERVICE','CATEGORY','CUSTOMER','TIER') NOT NULL DEFAULT 'ALL',
  scope_id      INT UNSIGNED NULL,
  starts_at     DATETIME NULL,
  ends_at       DATETIME NULL,
  usage_limit   INT UNSIGNED NULL,
  used_count    INT UNSIGNED NOT NULL DEFAULT 0,
  status        ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_disc_status (status, starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS discount_codes (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  discount_id INT UNSIGNED NOT NULL,
  code        VARCHAR(30) NOT NULL,
  usage_limit INT UNSIGNED NULL,
  used_count  INT UNSIGNED NOT NULL DEFAULT 0,
  status      ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (id),
  UNIQUE KEY uq_dc_code (code),
  CONSTRAINT fk_dc_discount FOREIGN KEY (discount_id) REFERENCES discounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_number  VARCHAR(25)  NOT NULL,
  customer_id     INT UNSIGNED NOT NULL,
  staff_id        INT UNSIGNED NULL,
  branch_id       INT UNSIGNED NOT NULL,
  appointment_id  BIGINT UNSIGNED NULL,
  issue_date      DATE NOT NULL,
  subtotal        DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  discount_id     INT UNSIGNED NULL,
  loyalty_discount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  loyalty_points_used INT UNSIGNED NOT NULL DEFAULT 0,
  tax_rate        DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  tax_amount      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  total           DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  paid_amount     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  due_amount      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  refunded_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  payment_status  ENUM('UNPAID','PARTIAL','PAID','REFUNDED','CANCELLED') NOT NULL DEFAULT 'UNPAID',
  status          ENUM('DRAFT','ISSUED','CANCELLED') NOT NULL DEFAULT 'ISSUED',
  notes           VARCHAR(500) NULL,
  created_by      INT UNSIGNED NULL,
  cancelled_at    DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_inv_number (invoice_number),
  KEY idx_inv_customer (customer_id, issue_date),
  KEY idx_inv_branch (branch_id, issue_date),
  KEY idx_inv_status (payment_status, issue_date),
  KEY idx_inv_date (issue_date),
  CONSTRAINT fk_inv_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_inv_branch   FOREIGN KEY (branch_id)   REFERENCES branches (id)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id   BIGINT UNSIGNED NOT NULL,
  item_type    ENUM('SERVICE','PRODUCT','PACKAGE','OTHER') NOT NULL DEFAULT 'SERVICE',
  service_id   INT UNSIGNED NULL,
  product_id   INT UNSIGNED NULL,
  staff_id     INT UNSIGNED NULL,
  title        VARCHAR(180) NOT NULL,
  quantity     DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  unit_price   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  discount     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total        DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  cost         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (id),
  KEY idx_ii_invoice (invoice_id),
  KEY idx_ii_service (service_id),
  KEY idx_ii_staff (staff_id),
  CONSTRAINT fk_ii_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_number VARCHAR(25) NOT NULL,
  invoice_id    BIGINT UNSIGNED NULL,
  customer_id   INT UNSIGNED NOT NULL,
  branch_id     INT UNSIGNED NULL,
  method_id     INT UNSIGNED NULL,
  method_slug   VARCHAR(30) NOT NULL DEFAULT 'CASH',
  type          ENUM('PAYMENT','REFUND','DEPOSIT','WALLET_TOPUP') NOT NULL DEFAULT 'PAYMENT',
  amount        DECIMAL(14,2) NOT NULL,
  status        ENUM('PENDING','SUCCESS','FAILED','CANCELLED') NOT NULL DEFAULT 'SUCCESS',
  reference     VARCHAR(80) NULL,
  gateway_ref   VARCHAR(120) NULL,
  parent_payment_id BIGINT UNSIGNED NULL,
  note          VARCHAR(255) NULL,
  paid_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pay_number (payment_number),
  KEY idx_pay_invoice (invoice_id),
  KEY idx_pay_customer (customer_id, paid_at),
  KEY idx_pay_date (paid_at),
  KEY idx_pay_type (type, status),
  CONSTRAINT fk_pay_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pay_invoice  FOREIGN KEY (invoice_id)  REFERENCES invoices (id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallet_accounts (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id INT UNSIGNED NOT NULL,
  balance     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  status      ENUM('ACTIVE','FROZEN') NOT NULL DEFAULT 'ACTIVE',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wa_customer (customer_id),
  CONSTRAINT fk_wa_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallet_transactions (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  wallet_id   INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  type        ENUM('CREDIT','DEBIT') NOT NULL,
  amount      DECIMAL(14,2) NOT NULL,
  balance_after DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  reference_type VARCHAR(40) NULL,
  reference_id BIGINT UNSIGNED NULL,
  description VARCHAR(255) NULL,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_wt_wallet (wallet_id, created_at),
  KEY idx_wt_customer (customer_id, created_at),
  CONSTRAINT fk_wt_wallet FOREIGN KEY (wallet_id) REFERENCES wallet_accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS discount_usages (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  discount_id INT UNSIGNED NOT NULL,
  code_id     INT UNSIGNED NULL,
  customer_id INT UNSIGNED NULL,
  invoice_id  BIGINT UNSIGNED NULL,
  amount      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_du_discount (discount_id),
  KEY idx_du_invoice (invoice_id),
  CONSTRAINT fk_du_discount FOREIGN KEY (discount_id) REFERENCES discounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_costs (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  service_id  INT UNSIGNED NOT NULL,
  invoice_item_id BIGINT UNSIGNED NULL,
  material_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  labor_cost    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  overhead_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  computed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sco_service (service_id),
  CONSTRAINT fk_sco_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- INVENTORY
-- =============================================================================

CREATE TABLE IF NOT EXISTS product_categories (
  id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pc_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_brands (
  id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  country VARCHAR(60) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pb_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suppliers (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(120) NOT NULL,
  contact_name  VARCHAR(100) NULL,
  phone         VARCHAR(20)  NULL,
  email         VARCHAR(150) NULL,
  address       VARCHAR(255) NULL,
  balance       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  status        ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sup_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sku          VARCHAR(40)  NOT NULL,
  name         VARCHAR(150) NOT NULL,
  category_id  INT UNSIGNED NULL,
  brand_id     INT UNSIGNED NULL,
  unit         VARCHAR(20)  NOT NULL DEFAULT 'عدد',
  purchase_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  sale_price   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  is_retail    TINYINT(1)   NOT NULL DEFAULT 0,
  reorder_level DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  image        VARCHAR(255) NULL,
  status       ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at   DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_prod_sku (sku),
  KEY idx_prod_name (name),
  KEY idx_prod_category (category_id),
  CONSTRAINT fk_prod_category FOREIGN KEY (category_id) REFERENCES product_categories (id) ON DELETE SET NULL,
  CONSTRAINT fk_prod_brand    FOREIGN KEY (brand_id)    REFERENCES product_brands (id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id  INT UNSIGNED NOT NULL,
  branch_id   INT UNSIGNED NOT NULL,
  quantity    DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  reserved    DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_inv_prod_branch (product_id, branch_id),
  KEY idx_inv_branch (branch_id),
  CONSTRAINT fk_invstk_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_invstk_branch  FOREIGN KEY (branch_id)  REFERENCES branches (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_transactions (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id  INT UNSIGNED NOT NULL,
  branch_id   INT UNSIGNED NOT NULL,
  type        ENUM('IN','OUT','ADJUST','WASTE','TRANSFER','CONSUME') NOT NULL,
  quantity    DECIMAL(12,3) NOT NULL,
  balance_after DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  unit_cost   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  reference_type VARCHAR(40) NULL,
  reference_id BIGINT UNSIGNED NULL,
  note        VARCHAR(255) NULL,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_itx_product (product_id, created_at),
  KEY idx_itx_ref (reference_type, reference_id),
  CONSTRAINT fk_itx_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_alerts (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id  INT UNSIGNED NOT NULL,
  branch_id   INT UNSIGNED NOT NULL,
  level       DECIMAL(12,3) NOT NULL,
  threshold   DECIMAL(12,3) NOT NULL,
  status      ENUM('OPEN','RESOLVED') NOT NULL DEFAULT 'OPEN',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_sa_status (status, created_at),
  CONSTRAINT fk_sa_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_recipes (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  service_id INT UNSIGNED NOT NULL,
  name       VARCHAR(120) NOT NULL DEFAULT 'دستور پیش‌فرض',
  status     ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (id),
  UNIQUE KEY uq_sr_service (service_id),
  CONSTRAINT fk_sr_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_items (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  recipe_id  INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity   DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  unit       VARCHAR(20) NOT NULL DEFAULT 'گرم',
  PRIMARY KEY (id),
  UNIQUE KEY uq_ri (recipe_id, product_id),
  CONSTRAINT fk_ri_recipe  FOREIGN KEY (recipe_id)  REFERENCES service_recipes (id) ON DELETE CASCADE,
  CONSTRAINT fk_ri_product FOREIGN KEY (product_id) REFERENCES products (id)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_orders (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  po_number   VARCHAR(25) NOT NULL,
  supplier_id INT UNSIGNED NOT NULL,
  branch_id   INT UNSIGNED NOT NULL,
  order_date  DATE NOT NULL,
  expected_date DATE NULL,
  subtotal    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  tax_amount  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  total       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  status      ENUM('DRAFT','ORDERED','PARTIAL','RECEIVED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  notes       VARCHAR(255) NULL,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_po_number (po_number),
  KEY idx_po_supplier (supplier_id, order_date),
  CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_order_items (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  po_id      BIGINT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity   DECIMAL(12,3) NOT NULL,
  received_quantity DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (id),
  KEY idx_poi_po (po_id),
  CONSTRAINT fk_poi_po      FOREIGN KEY (po_id)      REFERENCES purchase_orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_poi_product FOREIGN KEY (product_id) REFERENCES products (id)        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS goods_receipts (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  po_id       BIGINT UNSIGNED NOT NULL,
  receipt_number VARCHAR(25) NOT NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  received_by INT UNSIGNED NULL,
  notes       VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gr_number (receipt_number),
  KEY idx_gr_po (po_id),
  CONSTRAINT fk_gr_po FOREIGN KEY (po_id) REFERENCES purchase_orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- MARKETING
-- =============================================================================

CREATE TABLE IF NOT EXISTS message_templates (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code      VARCHAR(60)  NOT NULL,
  name      VARCHAR(120) NOT NULL,
  channel   ENUM('SMS','EMAIL','INAPP') NOT NULL DEFAULT 'SMS',
  subject   VARCHAR(180) NULL,
  body      TEXT NOT NULL,
  variables VARCHAR(255) NULL,
  status    ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (id),
  UNIQUE KEY uq_mt_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaigns (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(150) NOT NULL,
  channel     ENUM('SMS','EMAIL','INAPP') NOT NULL DEFAULT 'SMS',
  template_id INT UNSIGNED NULL,
  message     TEXT NULL,
  segment_id  INT UNSIGNED NULL,
  scheduled_at DATETIME NULL,
  status      ENUM('DRAFT','SCHEDULED','RUNNING','COMPLETED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  sent_count  INT UNSIGNED NOT NULL DEFAULT 0,
  failed_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_camp_status (status, scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_audiences (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  status      ENUM('PENDING','SENT','FAILED','SKIPPED') NOT NULL DEFAULT 'PENDING',
  PRIMARY KEY (id),
  UNIQUE KEY uq_ca (campaign_id, customer_id),
  CONSTRAINT fk_caud_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns (id) ON DELETE CASCADE,
  CONSTRAINT fk_caud_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_messages (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  channel     ENUM('SMS','EMAIL','INAPP') NOT NULL DEFAULT 'SMS',
  recipient   VARCHAR(150) NOT NULL,
  body        TEXT NOT NULL,
  status      ENUM('PENDING','SENT','FAILED') NOT NULL DEFAULT 'PENDING',
  error       VARCHAR(255) NULL,
  sent_at     DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_cm_campaign (campaign_id, status),
  CONSTRAINT fk_cm_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_results (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id INT UNSIGNED NOT NULL,
  metric      VARCHAR(50) NOT NULL,
  value       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  computed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cres_campaign (campaign_id),
  CONSTRAINT fk_cres_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automations (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(150) NOT NULL,
  trigger_event VARCHAR(60) NOT NULL,
  description VARCHAR(255) NULL,
  status      ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  run_count   INT UNSIGNED NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_auto_trigger (trigger_event, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automation_rules (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  automation_id INT UNSIGNED NOT NULL,
  field         VARCHAR(60) NOT NULL,
  operator      ENUM('EQ','NEQ','GT','GTE','LT','LTE','IN','CONTAINS') NOT NULL DEFAULT 'EQ',
  value         VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY idx_arule_auto (automation_id),
  CONSTRAINT fk_arule_auto FOREIGN KEY (automation_id) REFERENCES automations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automation_actions (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  automation_id INT UNSIGNED NOT NULL,
  action_type   ENUM('SEND_SMS','SEND_EMAIL','ADD_TAG','REMOVE_TAG','ADD_POINTS','CREATE_TASK','NOTIFY_ADMIN') NOT NULL,
  template_id   INT UNSIGNED NULL,
  params        JSON NULL,
  delay_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  sort_order    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_aact_auto (automation_id),
  CONSTRAINT fk_aact_auto FOREIGN KEY (automation_id) REFERENCES automations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automation_logs (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  automation_id INT UNSIGNED NOT NULL,
  trigger_event VARCHAR(60) NOT NULL,
  context       JSON NULL,
  matched       TINYINT(1) NOT NULL DEFAULT 0,
  actions_run   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  error         VARCHAR(255) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_alog_auto (automation_id, created_at),
  CONSTRAINT fk_alog_auto FOREIGN KEY (automation_id) REFERENCES automations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_logs (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id INT UNSIGNED NULL,
  user_id     INT UNSIGNED NULL,
  channel     ENUM('SMS','EMAIL','INAPP','CALL') NOT NULL DEFAULT 'SMS',
  direction   ENUM('OUT','IN') NOT NULL DEFAULT 'OUT',
  recipient   VARCHAR(150) NULL,
  subject     VARCHAR(180) NULL,
  body        TEXT NULL,
  status      ENUM('PENDING','SENT','FAILED','DELIVERED') NOT NULL DEFAULT 'SENT',
  provider    VARCHAR(40) NULL,
  provider_ref VARCHAR(120) NULL,
  error       VARCHAR(255) NULL,
  reference_type VARCHAR(40) NULL,
  reference_id BIGINT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_comlog_customer (customer_id, created_at),
  KEY idx_comlog_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- REVIEWS / SURVEYS / TICKETS
-- =============================================================================

CREATE TABLE IF NOT EXISTS surveys (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title       VARCHAR(150) NOT NULL,
  description VARCHAR(255) NULL,
  trigger_event VARCHAR(60) NULL,
  status      ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS survey_questions (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  survey_id  INT UNSIGNED NOT NULL,
  question   VARCHAR(255) NOT NULL,
  type       ENUM('RATING','TEXT','CHOICE','NPS') NOT NULL DEFAULT 'RATING',
  options    JSON NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_sq_survey (survey_id),
  CONSTRAINT fk_sq_survey FOREIGN KEY (survey_id) REFERENCES surveys (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS survey_responses (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  survey_id   INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NULL,
  appointment_id BIGINT UNSIGNED NULL,
  score       DECIMAL(5,2) NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sres_survey (survey_id, submitted_at),
  CONSTRAINT fk_sres_survey FOREIGN KEY (survey_id) REFERENCES surveys (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS survey_answers (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  response_id BIGINT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  answer_text TEXT NULL,
  answer_value DECIMAL(6,2) NULL,
  PRIMARY KEY (id),
  KEY idx_sans_response (response_id),
  CONSTRAINT fk_sans_response FOREIGN KEY (response_id) REFERENCES survey_responses (id) ON DELETE CASCADE,
  CONSTRAINT fk_sans_question FOREIGN KEY (question_id) REFERENCES survey_questions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reviews (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id INT UNSIGNED NOT NULL,
  staff_id    INT UNSIGNED NULL,
  service_id  INT UNSIGNED NULL,
  branch_id   INT UNSIGNED NULL,
  appointment_id BIGINT UNSIGNED NULL,
  rating      TINYINT UNSIGNED NOT NULL,
  title       VARCHAR(150) NULL,
  comment     TEXT NULL,
  status      ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  is_public   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rev_status (status, created_at),
  KEY idx_rev_staff (staff_id, status),
  KEY idx_rev_customer (customer_id),
  CONSTRAINT fk_rev_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS review_replies (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  review_id  BIGINT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NULL,
  reply      TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rr_review (review_id),
  CONSTRAINT fk_rr_review FOREIGN KEY (review_id) REFERENCES reviews (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tickets (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_number VARCHAR(20) NOT NULL,
  customer_id INT UNSIGNED NULL,
  branch_id   INT UNSIGNED NULL,
  subject     VARCHAR(180) NOT NULL,
  category    VARCHAR(60) NULL,
  priority    ENUM('LOW','NORMAL','HIGH','URGENT') NOT NULL DEFAULT 'NORMAL',
  status      ENUM('OPEN','IN_PROGRESS','WAITING','RESOLVED','CLOSED') NOT NULL DEFAULT 'OPEN',
  assigned_to INT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tick_number (ticket_number),
  KEY idx_tick_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_messages (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id  BIGINT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NULL,
  customer_id INT UNSIGNED NULL,
  message    TEXT NOT NULL,
  is_internal TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tm_ticket (ticket_id, created_at),
  CONSTRAINT fk_tm_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- ANALYTICS
-- =============================================================================

CREATE TABLE IF NOT EXISTS daily_metrics (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  metric_date   DATE NOT NULL,
  branch_id     INT UNSIGNED NULL,
  revenue       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  invoices_count INT UNSIGNED NOT NULL DEFAULT 0,
  appointments_count INT UNSIGNED NOT NULL DEFAULT 0,
  completed_count INT UNSIGNED NOT NULL DEFAULT 0,
  cancelled_count INT UNSIGNED NOT NULL DEFAULT 0,
  no_show_count INT UNSIGNED NOT NULL DEFAULT 0,
  new_customers INT UNSIGNED NOT NULL DEFAULT 0,
  returning_customers INT UNSIGNED NOT NULL DEFAULT 0,
  average_ticket DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  computed_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dm (metric_date, branch_id),
  KEY idx_dm_date (metric_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS monthly_metrics (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  period        CHAR(7) NOT NULL,
  branch_id     INT UNSIGNED NULL,
  revenue       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  invoices_count INT UNSIGNED NOT NULL DEFAULT 0,
  appointments_count INT UNSIGNED NOT NULL DEFAULT 0,
  new_customers INT UNSIGNED NOT NULL DEFAULT 0,
  retention_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  average_ticket DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  computed_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mm (period, branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kpi_snapshots (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kpi_key     VARCHAR(60) NOT NULL,
  kpi_value   DECIMAL(16,4) NOT NULL DEFAULT 0.0000,
  period      VARCHAR(20) NULL,
  branch_id   INT UNSIGNED NULL,
  computed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_kpi_key (kpi_key, computed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cohort_analysis (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cohort_period CHAR(7) NOT NULL,
  period_offset TINYINT UNSIGNED NOT NULL DEFAULT 0,
  customers_count INT UNSIGNED NOT NULL DEFAULT 0,
  retained_count  INT UNSIGNED NOT NULL DEFAULT 0,
  retention_rate  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  revenue       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  computed_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cohort (cohort_period, period_offset)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rfm_segments (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id  INT UNSIGNED NOT NULL,
  recency      INT UNSIGNED NOT NULL DEFAULT 0,
  frequency    INT UNSIGNED NOT NULL DEFAULT 0,
  monetary     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  r_score      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  f_score      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  m_score      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  segment      VARCHAR(40) NOT NULL DEFAULT 'NEW',
  computed_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rfm_customer (customer_id),
  KEY idx_rfm_segment (segment),
  CONSTRAINT fk_rfm_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS forecasts (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  metric      VARCHAR(50) NOT NULL,
  period      VARCHAR(20) NOT NULL,
  branch_id   INT UNSIGNED NULL,
  predicted_value DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  actual_value    DECIMAL(16,2) NULL,
  method      VARCHAR(40) NOT NULL DEFAULT 'MOVING_AVERAGE',
  computed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_forecast (metric, period, branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SYSTEM
-- =============================================================================

CREATE TABLE IF NOT EXISTS settings (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key VARCHAR(80)  NOT NULL,
  setting_value TEXT       NULL,
  type        ENUM('STRING','INT','DECIMAL','BOOL','JSON','TEXT') NOT NULL DEFAULT 'STRING',
  group_name  VARCHAR(50)  NOT NULL DEFAULT 'general',
  label       VARCHAR(150) NULL,
  is_public   TINYINT(1)   NOT NULL DEFAULT 0,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_set_key (setting_key),
  KEY idx_set_group (group_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title       VARCHAR(180) NOT NULL,
  body        TEXT NULL,
  type        VARCHAR(40) NOT NULL DEFAULT 'INFO',
  icon        VARCHAR(40) NULL,
  link        VARCHAR(255) NULL,
  reference_type VARCHAR(40) NULL,
  reference_id BIGINT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_notifications (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  notification_id BIGINT UNSIGNED NOT NULL,
  user_id         INT UNSIGNED NULL,
  customer_id     INT UNSIGNED NULL,
  status          ENUM('UNREAD','READ','ARCHIVED') NOT NULL DEFAULT 'UNREAD',
  read_at         DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_un_user (user_id, status, created_at),
  KEY idx_un_customer (customer_id, status, created_at),
  CONSTRAINT fk_un_notification FOREIGN KEY (notification_id) REFERENCES notifications (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS file_uploads (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  original_name VARCHAR(255) NOT NULL,
  stored_name   VARCHAR(255) NOT NULL,
  path          VARCHAR(255) NOT NULL,
  mime_type     VARCHAR(100) NOT NULL,
  extension     VARCHAR(10)  NOT NULL,
  size_bytes    INT UNSIGNED NOT NULL DEFAULT 0,
  entity_type   VARCHAR(40)  NULL,
  entity_id     BIGINT UNSIGNED NULL,
  uploaded_by   INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_fu_stored (stored_name),
  KEY idx_fu_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- آیین‌نامه‌ها و اسناد قابل‌دانلود (تنظیمات › آیین‌نامه و اسناد). فایل‌ها خارج از
-- مسیر عمومی public/ در storage/documents نگهداری و فقط از طریق کنترلر (با بررسی
-- دسترسی) قابل دانلود هستند — نه با لینک مستقیم و لیست‌شونده.
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

CREATE TABLE IF NOT EXISTS integrations (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(80) NOT NULL,
  provider    VARCHAR(60) NOT NULL,
  type        ENUM('SMS','EMAIL','PAYMENT','CALENDAR','OTHER') NOT NULL DEFAULT 'OTHER',
  config      JSON NULL,
  status      ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'INACTIVE',
  last_used_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_integ_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS integration_logs (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  integration_id INT UNSIGNED NULL,
  provider       VARCHAR(60) NOT NULL,
  operation      VARCHAR(60) NOT NULL,
  request_summary VARCHAR(255) NULL,
  response_code  VARCHAR(20) NULL,
  success        TINYINT(1) NOT NULL DEFAULT 1,
  error          VARCHAR(255) NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ilog_provider (provider, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webhooks (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(80) NOT NULL,
  event      VARCHAR(60) NOT NULL,
  url        VARCHAR(255) NOT NULL,
  secret     VARCHAR(120) NULL,
  status     ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  last_fired_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_wh_event (event, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS import_batches (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entity      VARCHAR(40) NOT NULL,
  filename    VARCHAR(255) NOT NULL,
  total_rows  INT UNSIGNED NOT NULL DEFAULT 0,
  imported    INT UNSIGNED NOT NULL DEFAULT 0,
  updated     INT UNSIGNED NOT NULL DEFAULT 0,
  skipped     INT UNSIGNED NOT NULL DEFAULT 0,
  failed      INT UNSIGNED NOT NULL DEFAULT 0,
  errors      JSON NULL,
  status      ENUM('PENDING','PREVIEW','COMPLETED','FAILED') NOT NULL DEFAULT 'PENDING',
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ib_entity (entity, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- CONTENT (public website)
-- =============================================================================

CREATE TABLE IF NOT EXISTS pages (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug        VARCHAR(150) NOT NULL,
  title       VARCHAR(180) NOT NULL,
  body        LONGTEXT NULL,
  meta_title       VARCHAR(180) NULL,
  meta_description VARCHAR(255) NULL,
  status      ENUM('DRAFT','PUBLISHED') NOT NULL DEFAULT 'PUBLISHED',
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pages_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS posts (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug        VARCHAR(180) NOT NULL,
  title       VARCHAR(200) NOT NULL,
  excerpt     VARCHAR(400) NULL,
  body        LONGTEXT NULL,
  cover       VARCHAR(255) NULL,
  author_id   INT UNSIGNED NULL,
  tags        VARCHAR(255) NULL,
  meta_title       VARCHAR(180) NULL,
  meta_description VARCHAR(255) NULL,
  views       INT UNSIGNED NOT NULL DEFAULT 0,
  status      ENUM('DRAFT','PUBLISHED') NOT NULL DEFAULT 'PUBLISHED',
  published_at DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_posts_slug (slug),
  KEY idx_posts_status (status, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gallery_items (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title       VARCHAR(150) NULL,
  image       VARCHAR(255) NOT NULL,
  service_id  INT UNSIGNED NULL,
  staff_id    INT UNSIGNED NULL,
  category    VARCHAR(80) NULL,
  sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  status      ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_gal_status (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(120) NOT NULL,
  mobile      VARCHAR(15)  NOT NULL,
  email       VARCHAR(150) NULL,
  subject     VARCHAR(180) NULL,
  message     TEXT NOT NULL,
  ip_address  VARCHAR(45) NULL,
  status      ENUM('NEW','READ','REPLIED','ARCHIVED') NOT NULL DEFAULT 'NEW',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cm_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
