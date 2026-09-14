-- Live personnel presence and seat operations.
-- Run after schema.sql. All state is sourced from authenticated staff activity.
CREATE TABLE IF NOT EXISTS staff_presence (
  staff_id INT UNSIGNED NOT NULL,
  online_status TINYINT(1) NOT NULL DEFAULT 0,
  last_activity_at DATETIME NULL,
  last_ip VARCHAR(45) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (staff_id),
  CONSTRAINT fk_presence_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
  KEY idx_presence_activity (online_status, last_activity_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_service_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id INT UNSIGNED NOT NULL,
  appointment_id BIGINT UNSIGNED NULL,
  customer_id INT UNSIGNED NULL,
  service_id INT UNSIGNED NULL,
  seat_id INT UNSIGNED NULL,
  started_at DATETIME NOT NULL,
  expected_end_at DATETIME NULL,
  ended_at DATETIME NULL,
  status ENUM('ACTIVE','COMPLETED','CANCELLED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_live_session_staff (staff_id, status, started_at),
  KEY idx_live_session_appointment (appointment_id),
  CONSTRAINT fk_live_session_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
  CONSTRAINT fk_live_session_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
  CONSTRAINT fk_live_session_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_live_session_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS operational_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id INT UNSIGNED NULL,
  event_type VARCHAR(40) NOT NULL,
  entity_type VARCHAR(40) NULL,
  entity_id BIGINT UNSIGNED NULL,
  payload JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ops_events_created (created_at),
  KEY idx_ops_events_staff (staff_id, created_at),
  CONSTRAINT fk_ops_event_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional physical seats. Existing resources remain available for booking.
CREATE TABLE IF NOT EXISTS seats (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  branch_id INT UNSIGNED NOT NULL,
  code VARCHAR(30) NOT NULL,
  name VARCHAR(100) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_seat_branch_code (branch_id, code),
  KEY idx_seat_branch_active (branch_id, active),
  CONSTRAINT fk_seat_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seat_assignments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  seat_id INT UNSIGNED NOT NULL,
  staff_id INT UNSIGNED NOT NULL,
  appointment_id BIGINT UNSIGNED NULL,
  customer_id INT UNSIGNED NULL,
  service_id INT UNSIGNED NULL,
  status ENUM('OCCUPIED','AVAILABLE','RELEASED') NOT NULL DEFAULT 'OCCUPIED',
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at DATETIME NULL,
  PRIMARY KEY (id), KEY idx_seat_assignment_live (seat_id, status),
  CONSTRAINT fk_sa_seat FOREIGN KEY (seat_id) REFERENCES seats(id) ON DELETE CASCADE,
  CONSTRAINT fk_sa_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
  CONSTRAINT fk_sa_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_breaks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id INT UNSIGNED NOT NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at DATETIME NULL,
  reason VARCHAR(120) NULL,
  PRIMARY KEY (id), KEY idx_break_staff_live (staff_id, ended_at),
  CONSTRAINT fk_break_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
