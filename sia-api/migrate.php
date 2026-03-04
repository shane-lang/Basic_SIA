<?php
/**
 * migrate.php
 * Run ONCE at deploy time:  php migrate.php
 *
 * This replaces all ALTER TABLE / CREATE TABLE IF NOT EXISTS calls
 * that were previously scattered across per-request handlers.
 * Safe to run multiple times (idempotent).
 */

$conn = new mysqli('localhost', 'root', '', 'sia_db');
if ($conn->connect_error) { die("DB error: " . $conn->connect_error . "\n"); }
$conn->set_charset("utf8mb4");

$migrations = [

    // ── auth.php tables ───────────────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS sessions (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT NOT NULL,
        token      VARCHAR(64) NOT NULL UNIQUE,
        role       VARCHAR(30) NOT NULL DEFAULT 'student',
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token),
        INDEX idx_user  (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS login_attempts (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        email        VARCHAR(150) NOT NULL,
        ip           VARCHAR(45)  NOT NULL DEFAULT '',
        attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email_time (email, attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ── students table extended columns ───────────────────────────────────
    "ALTER TABLE students MODIFY COLUMN student_type ENUM('New','Old','Continuing','Returning','Transferee') DEFAULT 'New'",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS middle_name         VARCHAR(100) DEFAULT ''",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS suffix              VARCHAR(20)  DEFAULT ''",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS lrn_no              VARCHAR(50)  DEFAULT ''",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS sex                 ENUM('Male','Female','') DEFAULT ''",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS religion            VARCHAR(100) DEFAULT ''",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS age                 VARCHAR(10)  DEFAULT ''",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS place_of_birth      VARCHAR(255) DEFAULT ''",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS citizenship         VARCHAR(100) DEFAULT ''",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS mother_tongue       VARCHAR(100) DEFAULT ''",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS is_indigenous       TINYINT(1)   DEFAULT 0",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS psa_birth_cert_no   VARCHAR(100) DEFAULT ''",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS has_special_needs   TINYINT(1)   DEFAULT 0",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS special_needs_details VARCHAR(255) DEFAULT ''",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS has_assistive_tech  TINYINT(1)   DEFAULT 0",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS assistive_tech_details VARCHAR(255) DEFAULT ''",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS strand              VARCHAR(100) DEFAULT ''",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS learning_delivery   VARCHAR(100) DEFAULT ''",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS last_school_attended VARCHAR(255) DEFAULT ''",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS guardian_name       VARCHAR(255) DEFAULT ''",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS guardian_address    VARCHAR(255) DEFAULT ''",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS guardian_contact    VARCHAR(50)  DEFAULT ''",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS student_category    VARCHAR(50)  DEFAULT ''",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS is_scholar          TINYINT(1)   DEFAULT 0",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS scholar_type        VARCHAR(100) DEFAULT ''",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS scholar_grantor     VARCHAR(255) DEFAULT ''",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS scholarship_amount  DECIMAL(10,2) DEFAULT 0",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS payment_method      VARCHAR(20)  NOT NULL DEFAULT 'GCash'",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS payment_plan        ENUM('full','installment') NOT NULL DEFAULT 'full'",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS semester            VARCHAR(100) DEFAULT ''",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS tor_eval_status     ENUM('NotRequired','Pending','Evaluated','Rejected') NOT NULL DEFAULT 'NotRequired'",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS tor_file            VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS psa_file            VARCHAR(255) DEFAULT NULL",

    // ── courses table ─────────────────────────────────────────────────────
    "ALTER TABLE courses ADD COLUMN IF NOT EXISTS faculty_id INT NULL DEFAULT NULL",

    // ── payment_logs table ────────────────────────────────────────────────
    "ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS payment_method VARCHAR(20) NOT NULL DEFAULT 'GCash' AFTER student_id",

    // ── tor_evaluations table ──────────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS tor_evaluations (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        student_id          INT NOT NULL,
        status              ENUM('Pending','Evaluated','Rejected') NOT NULL DEFAULT 'Pending',
        credited_units      INT NOT NULL DEFAULT 0,
        approved_units      INT NOT NULL DEFAULT 0,
        credited_subjects   TEXT DEFAULT NULL,
        credited_course_ids TEXT DEFAULT NULL,
        registrar_notes     TEXT DEFAULT NULL,
        evaluated_by        INT DEFAULT NULL,
        evaluated_at        TIMESTAMP NULL DEFAULT NULL,
        created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY student_id (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ── tuition_fees table ────────────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS tuition_fees (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        student_id        INT NOT NULL UNIQUE,
        units             INT NOT NULL DEFAULT 18,
        tuition_fee       DECIMAL(10,2) NOT NULL,
        miscellaneous_fee DECIMAL(10,2) NOT NULL DEFAULT 6688.00,
        registration_fee  DECIMAL(10,2) NOT NULL DEFAULT 700.00,
        laboratory_fee    DECIMAL(10,2) NOT NULL,
        energy_fee        DECIMAL(10,2) NOT NULL,
        subtotal          DECIMAL(10,2) NOT NULL,
        discount          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        installment_fee   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        total_assessment  DECIMAL(10,2) NOT NULL,
        created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ── installment_payments table ────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS installment_payments (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        student_id       INT NOT NULL,
        payment_log_id   INT DEFAULT NULL,
        or_ar_number     VARCHAR(30) NOT NULL,
        or_ar_type       ENUM('OR','AR') NOT NULL DEFAULT 'AR',
        amount           DECIMAL(10,2) NOT NULL,
        payment_date     DATE NOT NULL,
        payment_method   VARCHAR(20) NOT NULL DEFAULT 'Cash',
        gcash_reference  VARCHAR(100) DEFAULT NULL,
        exam_period      ENUM('Downpayment','Prelim','Midterm','Finals','Full') NOT NULL DEFAULT 'Downpayment',
        notes            TEXT DEFAULT NULL,
        recorded_by      INT DEFAULT NULL,
        created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ── or_ar_sequences table (FIX AC-02: atomic OR/AR number generation) ─
    "CREATE TABLE IF NOT EXISTS or_ar_sequences (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        year        YEAR NOT NULL UNIQUE,
        last_seq    INT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ── payment_schedules ─────────────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS payment_schedules (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        student_id      INT NOT NULL UNIQUE,
        payment_type    ENUM('full','installment') NOT NULL DEFAULT 'installment',
        total_assessment DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        prelim_due      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        midterm_due     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        finals_due      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        prelim_paid     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        midterm_paid    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        finals_paid     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        prelim_status   ENUM('locked','unpaid','partial','paid') NOT NULL DEFAULT 'locked',
        midterm_status  ENUM('locked','unpaid','partial','paid') NOT NULL DEFAULT 'locked',
        finals_status   ENUM('locked','unpaid','partial','paid') NOT NULL DEFAULT 'locked',
        prelim_unlocked_at  TIMESTAMP NULL,
        midterm_unlocked_at TIMESTAMP NULL,
        finals_unlocked_at  TIMESTAMP NULL,
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ── payment_notices ───────────────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS payment_notices (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        student_id   INT NOT NULL,
        exam_period  ENUM('Prelim','Midterm','Finals') NOT NULL,
        amount_due   DECIMAL(10,2) NOT NULL,
        due_date     DATE NULL,
        message      TEXT NULL,
        sent_by      INT NOT NULL,
        sent_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_read      TINYINT(1) NOT NULL DEFAULT 0,
        UNIQUE KEY one_notice (student_id, exam_period)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ── exam_permits ──────────────────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS exam_permits (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        student_id   INT NOT NULL,
        exam_period  ENUM('Prelim','Midterm','Finals') NOT NULL,
        school_year  VARCHAR(20) NOT NULL DEFAULT '2025-2026',
        semester     VARCHAR(30) NOT NULL DEFAULT '2nd Semester',
        status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        approved_at  TIMESTAMP NULL,
        approved_by  INT NULL,
        remarks      TEXT NULL,
        UNIQUE KEY unique_permit (student_id, exam_period, school_year, semester)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ── programs table ────────────────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS programs (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(150) NOT NULL,
        code        VARCHAR(20)  NOT NULL UNIQUE,
        level_type  ENUM('College','SHS','TVET') DEFAULT 'College',
        duration    INT DEFAULT 4,
        description TEXT,
        department  VARCHAR(100) DEFAULT '',
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "ALTER TABLE programs MODIFY COLUMN level_type ENUM('College','SHS','TVET') DEFAULT 'College'",

    // ── faculty table ─────────────────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS faculty (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        faculty_id VARCHAR(20) NOT NULL UNIQUE,
        first_name VARCHAR(100) NOT NULL,
        last_name  VARCHAR(100) NOT NULL,
        email      VARCHAR(150) NOT NULL UNIQUE,
        department VARCHAR(100) DEFAULT '',
        specialty  VARCHAR(100) DEFAULT '',
        subjects   JSON,
        status     ENUM('Active','Inactive','On Leave') DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ── rooms table ───────────────────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS rooms (
        id        INT AUTO_INCREMENT PRIMARY KEY,
        room_name VARCHAR(100) NOT NULL,
        building  VARCHAR(100) DEFAULT '',
        capacity  INT DEFAULT 40,
        room_type ENUM('Classroom','Laboratory','Lecture Hall') DEFAULT 'Classroom',
        status    ENUM('Available','Occupied','Under Maintenance') DEFAULT 'Available',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ── program_courses junction ──────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS program_courses (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        program_id INT NOT NULL,
        course_id  INT NOT NULL,
        UNIQUE KEY uq_pc (program_id, course_id),
        FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id)  REFERENCES courses(id)  ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ── Fix legacy Cash payment logs ──────────────────────────────────────
    "UPDATE payment_logs SET payment_method = 'Cash' WHERE gcash_reference = 'CASH-PAYMENT' AND payment_method = 'GCash'",

    // ── FIX: Populate missing BSIT courses in program_courses ─────────────
    // courses.program stores full name; program 6 = BSIT
    "INSERT IGNORE INTO `program_courses` (`program_id`, `course_id`)
     SELECT 6, c.id FROM courses c
     WHERE c.program = 'Bachelor of Science in Information Technology'
       AND NOT EXISTS (SELECT 1 FROM program_courses pc WHERE pc.program_id = 6 AND pc.course_id = c.id)",

    // ── FIX: Sync all programs — link courses by full name match ──────────
    "INSERT IGNORE INTO `program_courses` (`program_id`, `course_id`)
     SELECT p.id, c.id FROM programs p
     JOIN courses c ON c.program = p.name
     WHERE c.id NOT IN (SELECT course_id FROM program_courses WHERE program_id = p.id)",

    // ── FIX: Fix lec_units for courses where both lec and lab are 0 ───────
    // When lec_units=0 AND lab_units=0 but credits>0, set lec_units=credits
    "UPDATE courses SET lec_units = credits WHERE lec_units = 0 AND lab_units = 0 AND credits > 0 AND is_lab = 0",

    // ── FIX: Normalize malformed year_level values in courses table ──────
    // Some courses were imported with 'Year 1', 'Year 2' etc. instead of
    // '1st Year', '2nd Year' etc. This causes unit-count queries that filter
    // by year_level = '1st Year' to miss those courses (e.g. AEC105 had 'Year 1',
    // causing computeProgramUnitsLive to return 23 instead of the correct 26).
    "UPDATE courses SET year_level = '1st Year' WHERE year_level IN ('Year 1', 'Year-1', '1', 'First Year')",
    "UPDATE courses SET year_level = '2nd Year' WHERE year_level IN ('Year 2', 'Year-2', '2', 'Second Year')",
    "UPDATE courses SET year_level = '3rd Year' WHERE year_level IN ('Year 3', 'Year-3', '3', 'Third Year')",
    "UPDATE courses SET year_level = '4th Year' WHERE year_level IN ('Year 4', 'Year-4', '4', 'Fourth Year')",
    "UPDATE courses SET year_level = '5th Year' WHERE year_level IN ('Year 5', 'Year-5', '5', 'Fifth Year')",

    // ── FIX: Clean up cross-year auto-enrollments (wrong year_level) ──────
    // Removes enrollments where course year_level != student year_level
    // caused by old code that enrolled all years when program_courses was empty
    "DELETE e FROM enrollments e
     JOIN courses c  ON e.course_id  = c.id
     JOIN students s ON e.student_id = s.id
     WHERE s.student_type = 'Transferee'
       AND e.status = 'Enrolled'
       AND c.year_level != s.year_level
       AND c.year_level NOT IN ('', 'Year 1')
       AND e.notes LIKE 'Auto-enrolled%'",

    // ── FIX: Reset stale tor_evaluations.approved_units ──────────────────
    // approved_units was computed when program_courses was incomplete.
    // Setting to 0 forces computeFeesTransferee to recount live from enrollments.
    "UPDATE tor_evaluations SET approved_units = 0
     WHERE status = 'Evaluated' AND approved_units > 0 AND approved_units < credited_units",

    // ── FIX: Drop cross-year/cross-semester auto-enrollments (data corruption) ──
    // Remove enrollments where the course's year_level does not match the student's
    // year_level. These were created by the old Source 4 fallback that enrolled
    // ALL courses with no year filter when program_courses was empty.
    // Safe: only removes 'Enrolled'/'Pending' rows tagged as 'Auto-enrolled'
    // and only when c.year_level != s.year_level.
    "DELETE e FROM enrollments e
     JOIN courses c  ON e.course_id  = c.id
     JOIN students s ON e.student_id = s.id
     WHERE e.status IN ('Enrolled','Pending')
       AND c.year_level != '' AND c.year_level IS NOT NULL
       AND s.year_level  != '' AND s.year_level  IS NOT NULL
       AND c.year_level != s.year_level
       AND (e.notes LIKE 'Auto-enrolled%' OR e.notes IS NULL OR e.notes = '')",
];

$ok = 0; $fail = 0;
foreach ($migrations as $sql) {
    if ($conn->query($sql)) {
        $ok++;
    } else {
        // Many ALTERs will "fail" with "Duplicate column name" on re-run — that's fine
        $err = $conn->error;
        if (stripos($err, 'Duplicate column') !== false || stripos($err, 'already exists') !== false) {
            $ok++;
        } else {
            echo "WARN: $err\n  SQL: " . substr($sql, 0, 80) . "...\n";
            $fail++;
        }
    }
}
$conn->close();
echo "Migration complete: $ok OK, $fail warnings.\n";
?>