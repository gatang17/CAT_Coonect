-- =====================================================================
-- CATP Connect — Database Schema
-- =====================================================================
-- Custom tables for the CATP Connect companion app, layered on top of a
-- WordPress install. These are NOT WordPress core tables; they are
-- created by the app-conversion / booking plugins (or a small custom
-- plugin using dbDelta) alongside wp_*. Several tables here (location,
-- teacher, subject, subject_teacher, product, event, photographer,
-- peer_tutor, board_post) are physically implemented as WordPress custom
-- post types + ACF fields, not raw SQL tables — see wordpress/ for that.
-- (wordpress/ also holds one more thing with no table here at all: a
-- singleton `app_setting` post carrying the Tutoring external link.)
-- They're still modeled here as tables because this file's job is to be
-- the one place that defines the "correct" shape of the data and its
-- relationships, independent of which plugin or WordPress feature ends
-- up storing it.
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
--   - Identifier: the app's sole identifier is `school_email`, not a
--     student ID. Validated by a plain domain check (must end in
--     `@kctcs.edu`) — a regex/pattern rule at the form layer, not a
--     roster lookup or paid service. The CHECK constraint on `student`
--     below is a DB-level backstop for the same rule, not a replacement
--     for the form-layer check.
--   - Tutoring is NOT modeled here at all. It used to be (a
--     tutoring_booking table with a composite FK into subject_teacher),
--     but the feature was cut: Tutoring is now a single static external
--     link (to the program's existing Microsoft Bookings page), held in
--     one ACF field on a singleton `app_setting` post — see wordpress/.
--     No database entity needed for that.
--   - Goods is a pure calculator, not a request/reservation system —
--     there used to be `order`/`order_product` tables for a "submit this
--     supply request" flow; that flow was cut. `product` (below) still
--     exists because the calculator needs the pricing data, but nothing
--     a student does in Goods gets written anywhere.
--   - `product`: exact large-board size and whether dry mount tissue has
--     a different price for it are still open. Schema is unaffected —
--     these are just row values in `product`, not structural.
--   - `photographer` and `peer_tutor` are roles, not separate people: a
--     photographer/peer tutor is always a student (though not every
--     student holds either role), so both are subtype tables keyed
--     directly on `school_email` — no independent surrogate key.
--   - `subject` <-> `teacher` is many-to-many (real faculty data showed
--     e.g. 3 teachers for Advertising Design), via the `subject_teacher`
--     bridge. This still matters with Tutoring gone: it's what backs the
--     My Program directory and Peer Tutor subject matching.
--   - `board_post` is captured from the front end (a submission) but
--     moderated through ACF/wp-admin like catalog data — see its own
--     comment below for how that's expected to work physically.
--   - How a student earns the `photographer` role isn't decided (no
--     review step described, unlike peer tutoring).
--   - Envelope-per-submission and other Goods pricing rules are business
--     logic, not schema — enforced in the calculator UI, not here.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Catalog tables
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `student` (
  `school_email` VARCHAR(150) NOT NULL,
  -- The school-issued email, used as the sole identifier anywhere in the
  -- app. No name or login is stored — there are no user accounts.
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`school_email`),
  CONSTRAINT `chk_student_school_email_domain`
    CHECK (`school_email` LIKE '%@kctcs.edu')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `location` (
  `location_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  -- e.g. Classroom, Studio, Office. `type = 'Office'` is what lets this
  -- same catalog back a teacher's office (see `teacher.office_location_id`)
  -- instead of needing a second, parallel place-catalog.
  PRIMARY KEY (`location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `teacher` (
  `teacher_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `title` VARCHAR(100) NULL,
  -- e.g. "Professor", "Instructor", "Business & Technology Division Dean".
  `office_location_id` INT UNSIGNED NULL,
  -- e.g. "Chestnut Hall 307D" — a `location` row with type = 'Office'.
  -- Nullable: not every teacher has this entered right away.
  PRIMARY KEY (`teacher_id`),
  KEY `idx_teacher_office_location` (`office_location_id`),
  CONSTRAINT `fk_teacher_office_location`
    FOREIGN KEY (`office_location_id`) REFERENCES `location` (`location_id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `subject` (
  `subject_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  -- Real values: Advertising Design, Web Design, Photography, Digital Video.
  `location_id` INT UNSIGNED NOT NULL,
  -- The classroom belongs to the class (subject) itself, not to any one
  -- teacher — a subject can have several teachers (see subject_teacher)
  -- but only one classroom. This is also how a peer tutor inherits a
  -- classroom: peer_tutor is assigned a subject_id, and that subject
  -- already carries its room.
  PRIMARY KEY (`subject_id`),
  KEY `idx_subject_location` (`location_id`),
  CONSTRAINT `fk_subject_location`
    FOREIGN KEY (`location_id`) REFERENCES `location` (`location_id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `subject_teacher` (
  `subject_id` INT UNSIGNED NOT NULL,
  `teacher_id` INT UNSIGNED NOT NULL,
  -- Many-to-many: real faculty data showed multiple teachers per subject
  -- (e.g. 3 for Advertising Design). Backs the My Program directory and
  -- Peer Tutor subject matching — Tutoring itself no longer uses this
  -- table at all (see the header comment above).
  PRIMARY KEY (`subject_id`, `teacher_id`),
  KEY `idx_subject_teacher_teacher` (`teacher_id`),
  CONSTRAINT `fk_subject_teacher_subject`
    FOREIGN KEY (`subject_id`) REFERENCES `subject` (`subject_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_subject_teacher_teacher`
    FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`teacher_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
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
  `school_email` VARCHAR(150) NOT NULL,
  -- A photographer IS a student (not every student is a photographer),
  -- so this table has no independent surrogate key — it's the subset of
  -- `student` rows that also hold the photographer role. How a student
  -- earns this role isn't decided yet (no review step described, unlike
  -- peer tutoring).
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`school_email`),
  CONSTRAINT `fk_photographer_student`
    FOREIGN KEY (`school_email`) REFERENCES `student` (`school_email`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `studio_gear` (
  `gear_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`gear_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Transactional tables
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `studio_booking` (
  `booking_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_email` VARCHAR(150) NOT NULL,
  -- Also serves as the contact for this booking — there's no separate
  -- contact field.
  `date` DATE NOT NULL,
  `time_slot` ENUM('08:00-10:00', '10:00-12:00', '13:00-15:00', '15:00-17:00') NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`booking_id`),
  KEY `idx_studio_booking_email` (`school_email`),
  UNIQUE KEY `uq_studio_booking_date_slot` (`date`, `time_slot`),
  -- One booking per slot per day. Blocking slots already occupied by
  -- regular scheduled classes is enforced at the application layer
  -- (against the class schedule), since that schedule isn't modeled
  -- as a table here yet.
  CONSTRAINT `fk_studio_booking_student`
    FOREIGN KEY (`school_email`) REFERENCES `student` (`school_email`)
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
  `school_email` VARCHAR(150) NOT NULL,
  `equipment_id` INT UNSIGNED NOT NULL,
  `location_id` INT UNSIGNED NOT NULL,
  -- The classroom the equipment must be used in (same-day, in-class only).
  `date` DATE NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`request_id`),
  KEY `idx_equipment_request_email` (`school_email`),
  KEY `idx_equipment_request_equipment` (`equipment_id`),
  KEY `idx_equipment_request_location` (`location_id`),
  CONSTRAINT `fk_equipment_request_student`
    FOREIGN KEY (`school_email`) REFERENCES `student` (`school_email`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_equipment_request_equipment`
    FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`equipment_id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_equipment_request_location`
    FOREIGN KEY (`location_id`) REFERENCES `location` (`location_id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `volunteer_request` (
  `request_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_email` VARCHAR(150) NOT NULL,
  `event_id` INT UNSIGNED NOT NULL,
  `date` DATE NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`request_id`),
  KEY `idx_volunteer_request_email` (`school_email`),
  KEY `idx_volunteer_request_event` (`event_id`),
  CONSTRAINT `fk_volunteer_request_student`
    FOREIGN KEY (`school_email`) REFERENCES `student` (`school_email`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_volunteer_request_event`
    FOREIGN KEY (`event_id`) REFERENCES `event` (`event_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `peer_tutor` (
  `peer_tutor_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_email` VARCHAR(150) NOT NULL,
  `subject_id` INT UNSIGNED NOT NULL,
  `availability` VARCHAR(255) NOT NULL,
  `status` ENUM('pending', 'approved') NOT NULL DEFAULT 'pending',
  -- Approval is manual (admin via ACF), never automatic — this column
  -- just reflects that decision; nothing here flips it programmatically.
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`peer_tutor_id`),
  KEY `idx_peer_tutor_email` (`school_email`),
  KEY `idx_peer_tutor_subject` (`subject_id`),
  KEY `idx_peer_tutor_status` (`status`),
  CONSTRAINT `fk_peer_tutor_student`
    FOREIGN KEY (`school_email`) REFERENCES `student` (`school_email`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_peer_tutor_subject`
    FOREIGN KEY (`subject_id`) REFERENCES `subject` (`subject_id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `photo_session_request` (
  `request_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_email` VARCHAR(150) NOT NULL,
  -- Always the responsible student, even when the guest is the subject.
  `photographer_school_email` VARCHAR(150) NULL,
  -- The student acting as photographer for this session (references the
  -- `photographer` subtype, not `student` directly, so only students who
  -- hold the photographer role can be assigned here).
  `date_requested` DATE NOT NULL,
  `session_type` ENUM('model', 'photographer') NOT NULL,
  -- 'model' = student requests to be photographed;
  -- 'photographer' = student requests to be the photographer.
  `desired_date` DATE NOT NULL,
  `has_guest` TINYINT(1) NOT NULL DEFAULT 0,
  `guest_name` VARCHAR(150) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`request_id`),
  KEY `idx_photo_session_request_email` (`school_email`),
  KEY `idx_photo_session_request_photographer` (`photographer_school_email`),
  CONSTRAINT `fk_photo_session_request_student`
    FOREIGN KEY (`school_email`) REFERENCES `student` (`school_email`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_photo_session_request_photographer`
    FOREIGN KEY (`photographer_school_email`) REFERENCES `photographer` (`school_email`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `submit_work` (
  `submission_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_email` VARCHAR(150) NOT NULL,
  `event_id` INT UNSIGNED NULL,
  `work_type` ENUM('Ad', 'Photo', 'Web') NOT NULL,
  `onedrive_link` VARCHAR(500) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`submission_id`),
  KEY `idx_submit_work_email` (`school_email`),
  KEY `idx_submit_work_event` (`event_id`),
  CONSTRAINT `fk_submit_work_student`
    FOREIGN KEY (`school_email`) REFERENCES `student` (`school_email`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_submit_work_event`
    FOREIGN KEY (`event_id`) REFERENCES `event` (`event_id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `board_post` (
  `post_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_email` VARCHAR(150) NOT NULL,
  -- Required to submit; used only for moderation contact (e.g. following
  -- up on a rejected post) — NEVER displayed publicly.
  `title` VARCHAR(150) NOT NULL,
  `image_url` VARCHAR(500) NOT NULL,
  `display_name` VARCHAR(150) NULL,
  -- Shown publicly if present; the front end shows "Anonymous" when
  -- NULL. This is the one place in the whole app a real name is shown.
  `date` DATE NOT NULL,
  `status` ENUM('pending', 'approved') NOT NULL DEFAULT 'pending',
  -- Physically: the submission (Forminator's Post Creation feature)
  -- creates this as a `board_post` CPT post in draft status; an admin
  -- reviews and publishes it via wp-admin/ACF to approve — WordPress's
  -- native draft/publish status IS the pending/approved state here,
  -- there's no separate custom status field to keep in sync. Approval
  -- bar is deliberately low: reject only if discriminatory/disrespectful.
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`post_id`),
  KEY `idx_board_post_email` (`school_email`),
  CONSTRAINT `fk_board_post_student`
    FOREIGN KEY (`school_email`) REFERENCES `student` (`school_email`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
