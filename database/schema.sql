-- =====================================================================
-- CATP Connect — Database Schema
-- =====================================================================
-- Custom tables for the CATP Connect companion app, layered on top of a
-- WordPress install. These are NOT WordPress core tables; they are
-- created by the app-conversion / booking plugins (or a small custom
-- plugin using dbDelta) alongside wp_*.
--
-- Naming: table and column names here are given unprefixed / snake_case
-- to match the spec document. At install time, prefix every table name
-- with the site's $wpdb->prefix (e.g. `wp_student`) if tables are
-- created via a custom plugin using dbDelta, so multisite / table-prefix
-- conventions stay consistent with the rest of WordPress. This file
-- leaves them unprefixed for readability; find/replace `` -> `wp_`
-- (or your prefix of choice) before running if desired.
--
-- Engine: InnoDB (required for foreign keys). Charset: utf8mb4 to match
-- modern WordPress defaults.
--
-- Design notes / open questions carried over from the project context:
--   - `product`: exact large-board size and whether dry mount tissue has
--     a different price for it are still open. Schema is unaffected —
--     these are just row values in `product`, not structural.
--   - `student_id` validation method (how the system confirms an ID
--     belongs to a real enrolled student) is not yet decided. The
--     `student` table here is intentionally minimal (just the ID) since
--     there are no user accounts/logins — it exists so every FK in this
--     file has something to reference. Extend it later if the app needs
--     to store anything beyond the bare ID (it currently does not).
--   - `photographer`: spec only defines `photographer_id`. No name/email
--     column is specified yet; add one when that's decided.
--   - Envelope-per-submission and other Goods pricing rules are business
--     logic, not schema — enforced in the ordering UI/plugin, not here.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Catalog tables
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `student` (
  `student_id` VARCHAR(20) NOT NULL,
  -- School-issued ID, used as the sole identifier anywhere in the app.
  -- No name, email, or login is stored — there are no user accounts.
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `location` (
  `location_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `teacher` (
  `teacher_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `location_id` INT UNSIGNED NULL,
  PRIMARY KEY (`teacher_id`),
  KEY `idx_teacher_location` (`location_id`),
  CONSTRAINT `fk_teacher_location`
    FOREIGN KEY (`location_id`) REFERENCES `location` (`location_id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `subject` (
  `subject_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `teacher_id` INT UNSIGNED NOT NULL,
  -- The subject determines the teacher; teacher_id is intentionally
  -- never duplicated onto tutoring_booking rows (see relationships).
  PRIMARY KEY (`subject_id`),
  KEY `idx_subject_teacher` (`teacher_id`),
  CONSTRAINT `fk_subject_teacher`
    FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`teacher_id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `equipment` (
  `equipment_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `number` VARCHAR(20) NOT NULL,
  -- e.g. the iPad's asset number/label, not necessarily numeric-only.
  `color` VARCHAR(30) NULL,
  `type` VARCHAR(50) NOT NULL DEFAULT 'iPad',
  -- Currently all iPads; may expand to laptops etc. per spec.
  `status` ENUM('available', 'checked_out', 'maintenance') NOT NULL DEFAULT 'available',
  PRIMARY KEY (`equipment_id`),
  UNIQUE KEY `uq_equipment_number` (`number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `product` (
  `product_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `size` VARCHAR(50) NULL,
  -- e.g. "8x10", "11x14"; NULL for products with no size (Pullover).
  `price` DECIMAL(6,2) NOT NULL,
  `category` ENUM('print_material', 'merch') NOT NULL,
  PRIMARY KEY (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `event` (
  `event_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `date` DATE NOT NULL,
  `description` TEXT NULL,
  PRIMARY KEY (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `photographer` (
  `photographer_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  -- Spec defines no other fields yet (name/contact TBD).
  PRIMARY KEY (`photographer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `studio_gear` (
  `gear_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`gear_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Transactional tables
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `tutoring_booking` (
  `booking_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` VARCHAR(20) NOT NULL,
  `subject_id` INT UNSIGNED NOT NULL,
  `date` DATE NOT NULL,
  `time` TIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`booking_id`),
  KEY `idx_tutoring_booking_student` (`student_id`),
  KEY `idx_tutoring_booking_subject` (`subject_id`),
  KEY `idx_tutoring_booking_date_time` (`date`, `time`),
  CONSTRAINT `fk_tutoring_booking_student`
    FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tutoring_booking_subject`
    FOREIGN KEY (`subject_id`) REFERENCES `subject` (`subject_id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `studio_booking` (
  `booking_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` VARCHAR(20) NOT NULL,
  `date` DATE NOT NULL,
  `time_slot` ENUM('08:00-10:00', '10:00-12:00', '13:00-15:00', '15:00-17:00') NOT NULL,
  `contact` VARCHAR(150) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`booking_id`),
  KEY `idx_studio_booking_student` (`student_id`),
  UNIQUE KEY `uq_studio_booking_date_slot` (`date`, `time_slot`),
  -- One booking per slot per day. Blocking slots already occupied by
  -- regular scheduled classes is enforced at the application layer
  -- (against the class schedule), since that schedule isn't modeled
  -- as a table here yet.
  CONSTRAINT `fk_studio_booking_student`
    FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `studio_booking_gear` (
  `booking_id` INT UNSIGNED NOT NULL,
  `gear_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`booking_id`, `gear_id`),
  KEY `idx_studio_booking_gear_gear` (`gear_id`),
  CONSTRAINT `fk_studio_booking_gear_booking`
    FOREIGN KEY (`booking_id`) REFERENCES `studio_booking` (`booking_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_studio_booking_gear_gear`
    FOREIGN KEY (`gear_id`) REFERENCES `studio_gear` (`gear_id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `equipment_request` (
  `request_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` VARCHAR(20) NOT NULL,
  `equipment_id` INT UNSIGNED NOT NULL,
  `location_id` INT UNSIGNED NOT NULL,
  -- The classroom the equipment must be used in (same-day, in-class only).
  `date` DATE NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`request_id`),
  KEY `idx_equipment_request_student` (`student_id`),
  KEY `idx_equipment_request_equipment` (`equipment_id`),
  KEY `idx_equipment_request_location` (`location_id`),
  CONSTRAINT `fk_equipment_request_student`
    FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_equipment_request_equipment`
    FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`equipment_id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_equipment_request_location`
    FOREIGN KEY (`location_id`) REFERENCES `location` (`location_id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- `order` is a reserved SQL keyword — always backtick-quote it.
CREATE TABLE IF NOT EXISTS `order` (
  `order_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` VARCHAR(20) NOT NULL,
  `date` DATE NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`order_id`),
  KEY `idx_order_student` (`student_id`),
  CONSTRAINT `fk_order_student`
    FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `order_product` (
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `quantity` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`order_id`, `product_id`),
  KEY `idx_order_product_product` (`product_id`),
  CONSTRAINT `fk_order_product_order`
    FOREIGN KEY (`order_id`) REFERENCES `order` (`order_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_product_product`
    FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `volunteer_request` (
  `request_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` VARCHAR(20) NOT NULL,
  `event_id` INT UNSIGNED NOT NULL,
  `date` DATE NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`request_id`),
  KEY `idx_volunteer_request_student` (`student_id`),
  KEY `idx_volunteer_request_event` (`event_id`),
  CONSTRAINT `fk_volunteer_request_student`
    FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_volunteer_request_event`
    FOREIGN KEY (`event_id`) REFERENCES `event` (`event_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `peer_tutor` (
  `peer_tutor_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` VARCHAR(20) NOT NULL,
  `subject_id` INT UNSIGNED NOT NULL,
  `availability` VARCHAR(255) NOT NULL,
  `status` ENUM('pending', 'approved') NOT NULL DEFAULT 'pending',
  -- Approval is manual (admin via ACF), never automatic — this column
  -- just reflects that decision; nothing here flips it programmatically.
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`peer_tutor_id`),
  KEY `idx_peer_tutor_student` (`student_id`),
  KEY `idx_peer_tutor_subject` (`subject_id`),
  KEY `idx_peer_tutor_status` (`status`),
  CONSTRAINT `fk_peer_tutor_student`
    FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_peer_tutor_subject`
    FOREIGN KEY (`subject_id`) REFERENCES `subject` (`subject_id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `photo_session_request` (
  `request_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` VARCHAR(20) NOT NULL,
  -- Always the responsible student, even when the guest is the subject.
  `photographer_id` INT UNSIGNED NULL,
  `date_requested` DATE NOT NULL,
  `session_type` ENUM('model', 'photographer') NOT NULL,
  -- 'model' = student requests to be photographed;
  -- 'photographer' = student requests to be the photographer.
  `desired_date` DATE NOT NULL,
  `has_guest` TINYINT(1) NOT NULL DEFAULT 0,
  `guest_name` VARCHAR(150) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`request_id`),
  KEY `idx_photo_session_request_student` (`student_id`),
  KEY `idx_photo_session_request_photographer` (`photographer_id`),
  CONSTRAINT `fk_photo_session_request_student`
    FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_photo_session_request_photographer`
    FOREIGN KEY (`photographer_id`) REFERENCES `photographer` (`photographer_id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `submit_work` (
  `submission_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` VARCHAR(20) NOT NULL,
  `event_id` INT UNSIGNED NULL,
  `work_type` ENUM('Ad', 'Photo', 'Web') NOT NULL,
  `onedrive_link` VARCHAR(500) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`submission_id`),
  KEY `idx_submit_work_student` (`student_id`),
  KEY `idx_submit_work_event` (`event_id`),
  CONSTRAINT `fk_submit_work_student`
    FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_submit_work_event`
    FOREIGN KEY (`event_id`) REFERENCES `event` (`event_id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
