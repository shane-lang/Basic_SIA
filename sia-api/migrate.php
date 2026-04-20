<?php
// =============================================================================
// migrate.php
//
// FIX API-03: All CREATE TABLE IF NOT EXISTS / schema bootstrapping lives here.
// Run this once after deployment — not on every API request.
//
// Usage (CLI):            php migrate.php
// Usage (dev browser):    http://localhost/migrate.php
// Usage (prod browser):   http://yoursite.com/migrate.php?secret=<APP_SECRET>
// =============================================================================

require_once __DIR__ . '/config.php';

// ── Auth guard ────────────────────────────────────────────────────────────────
// Development: open in browser with no secret needed.
// Production:  must supply ?secret=<APP_SECRET> or run from CLI.
if (PHP_SAPI !== 'cli') {
    header('Content-Type: application/json');
    if (!IS_DEV) {
        $secret = env('APP_SECRET', '');
        if (!$secret || ($_GET['secret'] ?? '') !== $secret) {
            http_response_code(403);
            die(json_encode(['success' => false, 'message' => 'Forbidden. Provide ?secret= or run via CLI.']));
        }
    }
}

$results = [];

function runMigration(mysqli $conn, string $label, string $sql): void {
    global $results;
    if ($conn->query($sql)) {
        $results[] = "  ✓  $label";
    } else {
        $results[] = "  ✗  $label: " . $conn->error;
    }
}

// ── sessions ──────────────────────────────────────────────────────────────────
runMigration($conn, 'sessions table', "
    CREATE TABLE IF NOT EXISTS sessions (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT NOT NULL,
        token      VARCHAR(64) NOT NULL UNIQUE,
        role       VARCHAR(30) NOT NULL DEFAULT 'student',
        expires_at DATETIME    NOT NULL,
        created_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token),
        INDEX idx_user  (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── login_attempts ─────────────────────────────────────────────────────────────
runMigration($conn, 'login_attempts table', "
    CREATE TABLE IF NOT EXISTS login_attempts (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        email        VARCHAR(150) NOT NULL,
        ip           VARCHAR(45)  NOT NULL DEFAULT '',
        attempted_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email_time (email, attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── audit_logs ─────────────────────────────────────────────────────────────────
runMigration($conn, 'audit_logs table', "
    CREATE TABLE IF NOT EXISTS audit_logs (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        user_id     INT,
        user_email  VARCHAR(255),
        user_role   VARCHAR(50),
        action      VARCHAR(100) NOT NULL,
        target_type VARCHAR(50),
        target_id   INT          DEFAULT 0,
        description TEXT,
        old_values  LONGTEXT,
        new_values  LONGTEXT,
        ip_address  VARCHAR(45),
        user_agent  VARCHAR(255),
        created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        KEY idx_created (created_at),
        KEY idx_role    (user_role),
        KEY idx_action  (action)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── student_grades ─────────────────────────────────────────────────────────────
runMigration($conn, 'student_grades table', "
    CREATE TABLE IF NOT EXISTS student_grades (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        enrollment_id INT NOT NULL,
        student_id    INT NOT NULL,
        course_id     INT NOT NULL,
        semester      VARCHAR(100) DEFAULT '',
        term          ENUM('Prelim','Midterm','Final') NOT NULL,
        grade         DECIMAL(4,2) DEFAULT NULL,
        submitted_by  INT          DEFAULT NULL,
        submitted_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_grade    (enrollment_id, term),
        KEY idx_student (student_id),
        KEY idx_course  (course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── fee_config ─────────────────────────────────────────────────────────────────
runMigration($conn, 'fee_config table', "
    CREATE TABLE IF NOT EXISTS fee_config (
        id          INT          NOT NULL AUTO_INCREMENT,
        category    ENUM('College','SHS','TVET') NOT NULL DEFAULT 'College',
        fee_key     VARCHAR(60)  NOT NULL,
        fee_label   VARCHAR(120) NOT NULL,
        value       DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
        is_per_unit TINYINT(1)   NOT NULL DEFAULT 0,
        applies_to  VARCHAR(200) NOT NULL DEFAULT 'All',
        description VARCHAR(255) DEFAULT NULL,
        is_active   TINYINT(1)   NOT NULL DEFAULT 1,
        sort_order  INT          NOT NULL DEFAULT 0,
        created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_cat_key (category, fee_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── tuition_fees ───────────────────────────────────────────────────────────────
runMigration($conn, 'tuition_fees table', "
    CREATE TABLE IF NOT EXISTS tuition_fees (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        student_id        INT NOT NULL UNIQUE,
        units             INT DEFAULT 0,
        tuition_fee       DECIMAL(10,2) DEFAULT 0,
        miscellaneous_fee DECIMAL(10,2) DEFAULT 6688,
        registration_fee  DECIMAL(10,2) DEFAULT 700,
        laboratory_fee    DECIMAL(10,2) DEFAULT 0,
        energy_fee        DECIMAL(10,2) DEFAULT 0,
        subtotal          DECIMAL(10,2) DEFAULT 0,
        discount          DECIMAL(10,2) DEFAULT 0,
        installment_fee   DECIMAL(10,2) DEFAULT 0,
        total_assessment  DECIMAL(10,2) DEFAULT 0,
        created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── course_sections (from DB migration fix #5) ────────────────────────────────
runMigration($conn, 'course_sections table', "
    CREATE TABLE IF NOT EXISTS course_sections (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        course_id    INT NOT NULL,
        section_code VARCHAR(30) NOT NULL,
        faculty_id   INT DEFAULT NULL,
        room_id      INT DEFAULT NULL,
        day          VARCHAR(50) DEFAULT NULL,
        time_start   TIME DEFAULT NULL,
        time_end     TIME DEFAULT NULL,
        capacity     INT NOT NULL DEFAULT 40,
        semester     VARCHAR(50) DEFAULT NULL,
        school_year  VARCHAR(20) DEFAULT NULL,
        is_active    TINYINT(1) NOT NULL DEFAULT 1,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_section (course_id, section_code, semester, school_year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── FIX: Expand payment_status enum to include 'Partial' for installment display ──
// 'Free' was also used in dashboard.php for SHS/TVET — now normalized to 'Paid'.
// 'Partial' is kept here as a valid UI display value even though DB writes now use 'Pending'.
// This ALTER is safe to run multiple times (no-op if already expanded).
runMigration($conn, 'Expand payment_status enum', "
    ALTER TABLE students
    MODIFY COLUMN payment_status
        ENUM('Pending','Paid','Overdue','Partial','Free')
        DEFAULT 'Pending'
");

// ── FIX: Ensure audit_logs user_email column is wide enough ──────────────────
runMigration($conn, 'Widen audit_logs user_email', "
    ALTER TABLE audit_logs
    MODIFY COLUMN user_email VARCHAR(255)
");

// ── FIX: Add payment_method column to payment_logs if missing ────────────────
runMigration($conn, 'payment_logs.payment_method column', "
    ALTER TABLE payment_logs
    ADD COLUMN IF NOT EXISTS payment_method VARCHAR(20) DEFAULT 'GCash'
");

// ── FIX: Ensure rooms table exists (referenced by course_sections) ───────────
runMigration($conn, 'rooms table', "
    CREATE TABLE IF NOT EXISTS rooms (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        room_name   VARCHAR(100) NOT NULL,
        room_type   ENUM('Lecture','Laboratory','Gymnasium','Other') DEFAULT 'Lecture',
        capacity    INT DEFAULT 40,
        building    VARCHAR(100) DEFAULT NULL,
        floor       VARCHAR(20)  DEFAULT NULL,
        is_active   TINYINT(1)   NOT NULL DEFAULT 1,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");


// ── payment_logs columns (previously added at runtime in Accounting.php) ─────
runMigration($conn, 'payment_logs.verified_by', "ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS verified_by INT DEFAULT NULL");
runMigration($conn, 'payment_logs.verified_at', "ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS verified_at DATETIME DEFAULT NULL");
runMigration($conn, 'payment_logs.gcash_amount', "ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS gcash_amount DECIMAL(10,2) DEFAULT 0");
runMigration($conn, 'payment_logs.gcash_date', "ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS gcash_date DATE DEFAULT NULL");
runMigration($conn, 'payment_logs.gcash_reference', "ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS gcash_reference VARCHAR(100) DEFAULT NULL");
runMigration($conn, 'payment_logs.notes', "ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS notes TEXT DEFAULT NULL");
runMigration($conn, 'payment_logs.status', "ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'Pending'");
runMigration($conn, 'payment_logs.exam_period', "ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS exam_period VARCHAR(30) DEFAULT NULL");
runMigration($conn, 'payment_logs.transaction_id', "ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(100) DEFAULT NULL");
runMigration($conn, 'payment_logs.semester', "ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS semester VARCHAR(100) DEFAULT NULL");
runMigration($conn, 'payment_logs.or_ar_number', "ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS or_ar_number VARCHAR(30) DEFAULT NULL");

// ── 2FA: login_otp table ─────────────────────────────────────────────────────
runMigration($conn, 'login_otp table', "
    CREATE TABLE IF NOT EXISTS login_otp (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT NOT NULL UNIQUE,
        otp_code   VARCHAR(6) NOT NULL,
        expires_at DATETIME NOT NULL,
        used       TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── Registration confirmation columns on students table ───────────────────────
runMigration($conn, 'students.registrar_confirmed',    "ALTER TABLE students ADD COLUMN IF NOT EXISTS registrar_confirmed ENUM('Pending','Confirmed','Rejected') DEFAULT 'Pending'");
runMigration($conn, 'students.registrar_confirmed_at', "ALTER TABLE students ADD COLUMN IF NOT EXISTS registrar_confirmed_at DATETIME DEFAULT NULL");
runMigration($conn, 'students.registrar_confirmed_by', "ALTER TABLE students ADD COLUMN IF NOT EXISTS registrar_confirmed_by INT DEFAULT NULL");
runMigration($conn, 'students.registrar_notes',        "ALTER TABLE students ADD COLUMN IF NOT EXISTS registrar_notes TEXT DEFAULT NULL");

// ── students accounting/status columns (previously added at runtime in Accounting.php) ──
runMigration($conn, 'students.accounting_approved_by', "ALTER TABLE students ADD COLUMN IF NOT EXISTS accounting_approved_by INT DEFAULT NULL");
runMigration($conn, 'students.accounting_approved_at', "ALTER TABLE students ADD COLUMN IF NOT EXISTS accounting_approved_at DATETIME DEFAULT NULL");
runMigration($conn, 'students.accounting_notes', "ALTER TABLE students ADD COLUMN IF NOT EXISTS accounting_notes TEXT DEFAULT NULL");
runMigration($conn, 'students.enrollment_status', "ALTER TABLE students ADD COLUMN IF NOT EXISTS enrollment_status VARCHAR(30) DEFAULT 'Pending'");
runMigration($conn, 'students.enrollment_status.graduated', "ALTER TABLE students MODIFY COLUMN enrollment_status ENUM('Pending','Enrolled','Confirmed','Completed','Graduated','Inactive','Dropped') DEFAULT 'Pending'");
runMigration($conn, 'students.payment_status', "ALTER TABLE students ADD COLUMN IF NOT EXISTS payment_status VARCHAR(20) DEFAULT 'Pending'");
runMigration($conn, 'students.approval_status', "ALTER TABLE students ADD COLUMN IF NOT EXISTS approval_status VARCHAR(20) DEFAULT 'Pending'");
runMigration($conn, 'students.payment_plan', "ALTER TABLE students ADD COLUMN IF NOT EXISTS payment_plan VARCHAR(20) DEFAULT 'full'");
runMigration($conn, 'students.payment_method', "ALTER TABLE students ADD COLUMN IF NOT EXISTS payment_method VARCHAR(20) DEFAULT 'GCash'");

// ── courses columns (previously added at runtime in enrollment.php) ───────────
runMigration($conn, 'courses.year_level', "ALTER TABLE courses ADD COLUMN IF NOT EXISTS year_level VARCHAR(20) DEFAULT '1st Year'");
runMigration($conn, 'courses.semester', "ALTER TABLE courses ADD COLUMN IF NOT EXISTS semester VARCHAR(50) DEFAULT NULL");
runMigration($conn, 'courses.lec_units', "ALTER TABLE courses ADD COLUMN IF NOT EXISTS lec_units INT DEFAULT 0");
runMigration($conn, 'courses.lab_units', "ALTER TABLE courses ADD COLUMN IF NOT EXISTS lab_units INT DEFAULT 0");
runMigration($conn, 'courses.is_general', "ALTER TABLE courses ADD COLUMN IF NOT EXISTS is_general TINYINT(1) NOT NULL DEFAULT 0");
runMigration($conn, 'courses.is_lab', "ALTER TABLE courses ADD COLUMN IF NOT EXISTS is_lab TINYINT(1) DEFAULT 0");
runMigration($conn, 'courses.department', "ALTER TABLE courses ADD COLUMN IF NOT EXISTS department VARCHAR(100) DEFAULT NULL");
runMigration($conn, 'courses.description', "ALTER TABLE courses ADD COLUMN IF NOT EXISTS description TEXT DEFAULT NULL");
runMigration($conn, 'courses.capacity', "ALTER TABLE courses ADD COLUMN IF NOT EXISTS capacity INT DEFAULT 40");
runMigration($conn, 'courses.faculty_id', "ALTER TABLE courses ADD COLUMN IF NOT EXISTS faculty_id INT DEFAULT NULL");
runMigration($conn, 'courses.program', "ALTER TABLE courses ADD COLUMN IF NOT EXISTS program VARCHAR(100) DEFAULT NULL");

// ── enrollments columns (previously added at runtime in enrollment.php) ───────
runMigration($conn, 'enrollments.notes', "ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS notes VARCHAR(255) DEFAULT NULL");
runMigration($conn, 'enrollments.semester', "ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS semester VARCHAR(100) DEFAULT NULL");

// ── FIX HISTORY-01: Replace UNIQUE KEY(student_id, course_id) with
//    UNIQUE KEY(student_id, course_id, semester) so a student can have
//    one enrollment row PER COURSE PER SEMESTER across their full history.
//    The old key caused INSERT IGNORE to silently skip re-enrollments,
//    leaving every semester after the first completely empty in history.
//
//    Safe/idempotent: drops old key only if it exists, adds new key only if absent.
runMigration($conn, 'enrollments.semester_widen',
    "ALTER TABLE enrollments MODIFY COLUMN semester VARCHAR(100) DEFAULT NULL");
runMigration($conn, 'enrollments.drop_student_course_uq', "
    ALTER TABLE enrollments DROP INDEX IF EXISTS student_course
");
runMigration($conn, 'enrollments.uq_enrollment_semester',
    "ALTER TABLE enrollments ADD UNIQUE KEY IF NOT EXISTS
     uq_enrollment_semester (student_id, course_id, semester(100))");
runMigration($conn, 'enrollments.remarks', "ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS remarks VARCHAR(20) DEFAULT 'In Progress'");
runMigration($conn, 'enrollments.grade_released', "ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS grade_released TINYINT(1) DEFAULT 0");
runMigration($conn, 'enrollments.grade_submitted', "ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS grade_submitted TINYINT(1) DEFAULT 0");
runMigration($conn, 'enrollments.grade_submitted_at', "ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS grade_submitted_at DATETIME DEFAULT NULL");
runMigration($conn, 'enrollments.grade_released_at', "ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS grade_released_at DATETIME DEFAULT NULL");

// ── installment_payments receipt token columns (used by receipt.php) ──────────
runMigration($conn, 'installment_payments.receipt_token', "ALTER TABLE installment_payments ADD COLUMN IF NOT EXISTS receipt_token VARCHAR(64) DEFAULT NULL");

// ── FIX: permit_identifier — unique human-readable exam permit code ────────────
runMigration($conn, 'exam_permits.permit_identifier', "ALTER TABLE exam_permits ADD COLUMN IF NOT EXISTS permit_identifier VARCHAR(60) DEFAULT NULL");
runMigration($conn, 'exam_permits.permit_identifier_idx', "ALTER TABLE exam_permits ADD UNIQUE KEY IF NOT EXISTS uq_permit_identifier (permit_identifier)");

// ── FIX: Missing students columns for scholarship tracking ────────────────────
runMigration($conn, 'students.age',               "ALTER TABLE students ADD COLUMN IF NOT EXISTS age INT DEFAULT NULL");
runMigration($conn, 'students.is_scholar',        "ALTER TABLE students ADD COLUMN IF NOT EXISTS is_scholar TINYINT(1) NOT NULL DEFAULT 0");
runMigration($conn, 'students.scholar_grantor',   "ALTER TABLE students ADD COLUMN IF NOT EXISTS scholar_grantor VARCHAR(150) DEFAULT NULL");
runMigration($conn, 'students.scholar_type',      "ALTER TABLE students ADD COLUMN IF NOT EXISTS scholar_type VARCHAR(100) DEFAULT NULL");
runMigration($conn, 'students.scholarship_amount',"ALTER TABLE students ADD COLUMN IF NOT EXISTS scholarship_amount DECIMAL(10,2) DEFAULT 0.00");
runMigration($conn, 'installment_payments.receipt_signed_at', "ALTER TABLE installment_payments ADD COLUMN IF NOT EXISTS receipt_signed_at DATETIME DEFAULT NULL");
runMigration($conn, 'installment_payments.payment_log_id', "ALTER TABLE installment_payments ADD COLUMN IF NOT EXISTS payment_log_id INT DEFAULT NULL");

// ── add_drop_requests table ───────────────────────────────────────────────────
runMigration($conn, 'add_drop_requests table', "
    CREATE TABLE IF NOT EXISTS add_drop_requests (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        student_id    INT NOT NULL,
        request_type  ENUM('Add','Drop') NOT NULL,
        course_id     INT NOT NULL,
        enrollment_id INT DEFAULT NULL,
        reason        TEXT DEFAULT NULL,
        status        ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        remarks       TEXT DEFAULT NULL,
        processed_by  INT DEFAULT NULL,
        processed_at  DATETIME DEFAULT NULL,
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_student (student_id),
        INDEX idx_status  (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── add_drop_window table ─────────────────────────────────────────────────────
runMigration($conn, 'add_drop_window table', "
    CREATE TABLE IF NOT EXISTS add_drop_window (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        start_date DATETIME NOT NULL,
        end_date   DATETIME NOT NULL,
        label      VARCHAR(100) DEFAULT NULL,
        is_active  TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── sys_config table (used by enrollment period logic) ────────────────────────
runMigration($conn, 'sys_config table', "
    CREATE TABLE IF NOT EXISTS sys_config (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        config_key   VARCHAR(100) NOT NULL UNIQUE,
        config_value LONGTEXT DEFAULT NULL,
        updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── staff_profiles table (used by auth for name resolution) ──────────────────
runMigration($conn, 'staff_profiles table', "
    CREATE TABLE IF NOT EXISTS staff_profiles (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT NOT NULL UNIQUE,
        first_name VARCHAR(100) DEFAULT NULL,
        last_name  VARCHAR(100) DEFAULT NULL,
        department VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── or_ar_sequences table (used by Accounting.php for OR/AR numbering) ────────
runMigration($conn, 'or_ar_sequences table', "
    CREATE TABLE IF NOT EXISTS or_ar_sequences (
        year     YEAR NOT NULL PRIMARY KEY,
        last_seq INT  NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── Legacy Cash payment_method fix (safe to run every time) ──────────────────
runMigration($conn, 'Fix legacy Cash payment_method in payment_logs', "
    UPDATE payment_logs SET payment_method = 'Cash'
    WHERE gcash_reference = 'CASH-PAYMENT' AND payment_method = 'GCash'
");

// ── FEATURE: Scholarship history — add granted_by + notes to student_scholarships ─
runMigration($conn, 'student_scholarships.notes',       "ALTER TABLE student_scholarships ADD COLUMN IF NOT EXISTS notes TEXT DEFAULT NULL");
runMigration($conn, 'student_scholarships.granted_by',  "ALTER TABLE student_scholarships ADD COLUMN IF NOT EXISTS granted_by INT DEFAULT NULL");
runMigration($conn, 'student_scholarships.granted_by_email', "ALTER TABLE student_scholarships ADD COLUMN IF NOT EXISTS granted_by_email VARCHAR(150) DEFAULT NULL");
runMigration($conn, 'student_scholarships.revoked_at',  "ALTER TABLE student_scholarships ADD COLUMN IF NOT EXISTS revoked_at DATETIME DEFAULT NULL");
runMigration($conn, 'student_scholarships.revoked_by',  "ALTER TABLE student_scholarships ADD COLUMN IF NOT EXISTS revoked_by_email VARCHAR(150) DEFAULT NULL");
runMigration($conn, 'student_scholarships.revoke_reason',"ALTER TABLE student_scholarships ADD COLUMN IF NOT EXISTS revoke_reason TEXT DEFAULT NULL");

// ── FEATURE: Enrollment history in registrar ─────────────────────────────────
// enrollment_snapshots captures a point-in-time record of a student's enrolled subjects per semester
runMigration($conn, 'enrollment_snapshots table', "
    CREATE TABLE IF NOT EXISTS enrollment_snapshots (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        student_id  INT NOT NULL,
        semester    VARCHAR(100) NOT NULL,
        year_level  VARCHAR(30)  DEFAULT NULL,
        program     VARCHAR(150) DEFAULT NULL,
        snapshot    LONGTEXT     NOT NULL COMMENT 'JSON array of enrolled courses at snapshot time',
        created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        created_by  INT          DEFAULT NULL,
        INDEX idx_student  (student_id),
        INDEX idx_semester (semester)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── FEATURE: Accounting subject-add fee log ───────────────────────────────────
// Logs fee impact when a subject is added (by registrar or student add/drop)
runMigration($conn, 'subject_fee_log table', "
    CREATE TABLE IF NOT EXISTS subject_fee_log (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        student_id      INT NOT NULL,
        course_id       INT NOT NULL,
        course_code     VARCHAR(30)  DEFAULT NULL,
        course_name     VARCHAR(150) DEFAULT NULL,
        action          ENUM('Add','Drop') NOT NULL DEFAULT 'Add',
        subject_type    VARCHAR(20)  DEFAULT 'Lecture' COMMENT 'Lecture or Laboratory',
        course_category VARCHAR(30)  DEFAULT NULL COMMENT 'Major, Minor, GE, PE, NSTP, Elective',
        units           INT          DEFAULT 0,
        lec_units       INT          DEFAULT 0,
        lab_units       INT          DEFAULT 0,
        tuition_impact  DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Change in tuition (units × rate)',
        lab_fee_impact  DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Lab fee added (1 lab room = ₱1900)',
        energy_impact   DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Energy fee impact',
        total_impact    DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Net change in total assessment',
        semester        VARCHAR(100)  DEFAULT NULL,
        reason          TEXT          DEFAULT NULL,
        added_by_role   VARCHAR(30)   DEFAULT NULL,
        added_by_email  VARCHAR(150)  DEFAULT NULL,
        created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_student  (student_id),
        INDEX idx_created  (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── FIX SEM-FILTER-01: Add semester column to installment_payments ────────────
// Without this column every SUM(amount) aggregates payments from ALL semesters,
// causing balance=0 for returning students even when the current semester is unpaid.
runMigration($conn, 'installment_payments.semester column',
    "ALTER TABLE installment_payments
     ADD COLUMN IF NOT EXISTS semester VARCHAR(100) DEFAULT NULL COMMENT 'Semester this payment belongs to'"
);
runMigration($conn, 'installment_payments.semester index',
    "ALTER TABLE installment_payments ADD INDEX IF NOT EXISTS idx_ip_semester (student_id, semester)"
);

// Back-fill semester for existing rows using the payment_log's semester when available,
// otherwise fall back to the student's current semester.
runMigration($conn, 'installment_payments.semester back-fill',
    "UPDATE installment_payments ip
     LEFT JOIN payment_logs pl ON pl.id = ip.payment_log_id AND pl.semester IS NOT NULL AND TRIM(pl.semester) != ''
     JOIN students s ON s.id = ip.student_id
     SET ip.semester = COALESCE(
         NULLIF(TRIM(pl.semester), ''),
         NULLIF(TRIM(s.semester), ''),
         '1st Semester, AY 2025-2026'
     )
     WHERE ip.semester IS NULL OR ip.semester = ''"
);

// ── FIX PERMIT-CARRY-01: Carry-over columns on payment_schedules ───────────────────────
// These columns store the unpaid balance that was carried forward to the next term
// when Accounting approved a permit for a student who hadn't fully paid that period.
// recomputeSchedule() uses total paid vs total assessment to redistribute dues, so
// the carry-over amount is automatically folded into the next term's due. These
// columns are kept for audit/display purposes (UI shows "includes ₱X carry-over").
runMigration($conn, 'payment_schedules.prelim_carry_over column',
    "ALTER TABLE payment_schedules
     ADD COLUMN IF NOT EXISTS prelim_carry_over DECIMAL(10,2) NOT NULL DEFAULT 0.00
     COMMENT 'Unpaid Prelim balance carried forward to next term'"
);
runMigration($conn, 'payment_schedules.midterm_carry_over column',
    "ALTER TABLE payment_schedules
     ADD COLUMN IF NOT EXISTS midterm_carry_over DECIMAL(10,2) NOT NULL DEFAULT 0.00
     COMMENT 'Unpaid Midterm balance carried forward to next term'"
);
runMigration($conn, 'payment_schedules.finals_carry_over column',
    "ALTER TABLE payment_schedules
     ADD COLUMN IF NOT EXISTS finals_carry_over DECIMAL(10,2) NOT NULL DEFAULT 0.00
     COMMENT 'Unpaid Finals balance carried forward (for SOA audit)'"
);

$output = implode("\n", $results);
if (PHP_SAPI === 'cli') {
    echo "SIA Migrations\n==============\n$output\n";
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'results' => $results]);
}

// ── DATA RETENTION PURGE (RA 10173 compliance) ───────────────────────────────
// Run this section periodically (monthly via cron) to purge stale personal data.
// Keeping data longer than necessary violates the Data Privacy Act of 2012.
//
// Recommended cron: 0 2 1 * * php /path/to/sia-api/migrate.php --purge
//
// Only runs when explicitly called with --purge flag or ?action=purge_retention
$runPurge = (PHP_SAPI === 'cli' && in_array('--purge', $argv ?? []))
         || (($_GET['action'] ?? '') === 'purge_retention' && ($_GET['secret'] ?? '') === env('APP_SECRET', ''));

if ($runPurge) {
    $purgeResults = [];

    // 1. Login attempts older than 30 days
    $conn->query("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $purgeResults[] = "login_attempts: removed " . $conn->affected_rows . " rows older than 30 days";

    // 2. Expired sessions (already past expires_at)
    $conn->query("DELETE FROM sessions WHERE expires_at < NOW()");
    $purgeResults[] = "sessions: removed " . $conn->affected_rows . " expired sessions";

    // 3. Audit logs older than 2 years (archive first in production)
    $conn->query("DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 YEAR)");
    $purgeResults[] = "audit_logs: removed " . $conn->affected_rows . " rows older than 2 years";

    // 4. Anonymize old payment logs (preserve amounts for accounting, remove PII)
    // GCash reference numbers older than 1 year are anonymized — not deleted
    // (financial records must be kept per BIR regulations)
    $conn->query("UPDATE payment_logs
                  SET gcash_reference = CONCAT('ANON-', id),
                      gcash_transaction_id = NULL,
                      notes = '[Anonymized per RA 10173 retention policy]'
                  WHERE gcash_date < DATE_SUB(NOW(), INTERVAL 1 YEAR)
                  AND gcash_reference NOT LIKE 'ANON-%'");
    $purgeResults[] = "payment_logs: anonymized " . $conn->affected_rows . " GCash references older than 1 year";

    if (PHP_SAPI === 'cli') {
        echo "\nDATA RETENTION PURGE\n====================\n" . implode("\n", $purgeResults) . "\n";
    } else {
        echo json_encode(['success' => true, 'purge_results' => $purgeResults]);
    }
}