<?php
error_reporting(0);
ini_set('display_errors', 0);

// ── cleanCode() — strips program-disambiguation suffixes from course codes ──
// e.g. GE103-BMD → GE103, PE1-BMD → PE1, NSTP1-CA → NSTP1
if (!function_exists('cleanCode')) {
    function cleanCode($code) {
        if (!$code) return $code;
        static $suffixes = ['-BMD','-CA','-BSA','-BSCA','-BSE','-CIMT','-BSIT','-BSREM','-ICTD','-HMD','-CED','-CAS'];
        $upper = strtoupper($code);
        foreach ($suffixes as $s) {
            if (substr($upper, -strlen($s)) === $s) {
                return substr($code, 0, strlen($code) - strlen($s));
            }
        }
        return $code;
    }
}

// FIX A-02: Restrict CORS to trusted origins only
$allowedOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$trustedOrigins = [
    'http://localhost:4200',
    'http://localhost',
    'http://127.0.0.1:4200',
    'http://127.0.0.1',
];
if (in_array($allowedOrigin, $trustedOrigins, true)) {
    header("Access-Control-Allow-Origin: $allowedOrigin");
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: http://localhost:4200');
}

header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-User-Id");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

$conn = new mysqli('localhost', 'root', '', 'sia_db');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->connect_error]); exit();
}
$conn->set_charset("utf8mb4");
require_once __DIR__ . '/audit_helper.php';

// ================================================================
// FEE CONFIG HELPER — shared by all fee computation functions.
// Loads rates from `fee_config` table; seeds defaults on first run.
// Usage: $r = loadFeeConfig($conn, 'College');
//        $tuition_rate = (float)($r['tuition_rate_per_unit']['value'] ?? 650);
// ================================================================
function loadFeeConfig(mysqli $conn, string $category): array {
    $conn->query("CREATE TABLE IF NOT EXISTS `fee_config` (
        `id`          INT NOT NULL AUTO_INCREMENT,
        `category`    ENUM('College','SHS','TVET') NOT NULL DEFAULT 'College',
        `fee_key`     VARCHAR(60)  NOT NULL,
        `fee_label`   VARCHAR(120) NOT NULL,
        `value`       DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
        `is_per_unit` TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1=multiply by enrolled units',
        `applies_to`  VARCHAR(200) NOT NULL DEFAULT 'All',
        `description` VARCHAR(255) DEFAULT NULL,
        `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
        `sort_order`  INT          NOT NULL DEFAULT 0,
        `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_cat_key` (`category`,`fee_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $cnt = (int)($conn->query("SELECT COUNT(*) AS c FROM fee_config")->fetch_assoc()['c'] ?? 0);
    if ($cnt === 0) {
        $conn->query("INSERT IGNORE INTO fee_config
            (category,fee_key,fee_label,value,is_per_unit,applies_to,description,sort_order) VALUES
            ('College','tuition_rate_per_unit','Tuition Fee (per unit)',650,1,'All','Charged per enrolled unit',1),
            ('College','misc_fee','Miscellaneous Fee',6688,0,'All','Fixed miscellaneous fee',2),
            ('College','reg_fee','Registration Fee',700,0,'All','Fixed registration fee',3),
            ('College','lab_fee_per_room','Laboratory Fee (per lab room)',1900,0,'All','Per laboratory room on campus',4),
            ('College','energy_rate_per_unit','Energy Fee (per unit)',63,1,'All','units × ₱21 × 3 terms = ₱63/unit',5),
            ('College','installment_fee','Installment Surcharge',750,0,'All','Added when payment plan is installment',6),
            ('SHS','transferee_flat_rate','Transferee Flat Rate',20000,0,'Transferee','Flat fee for SHS transferees',1),
            ('SHS','installment_fee','Installment Surcharge',750,0,'All','Added when payment plan is installment',2),
            ('TVET','misc_fee','Miscellaneous Fee',3500,0,'All','Fixed miscellaneous fee for TVET',1),
            ('TVET','reg_fee','Registration Fee',500,0,'All','Fixed registration fee for TVET',2),
            ('TVET','installment_fee','Installment Surcharge',500,0,'All','Added when payment plan is installment',3),
            ('TVET','transferee_flat_rate','Transferee Flat Rate',20000,0,'Transferee','Flat fee for TVET transferees',4)");
    }

    $cat = $conn->real_escape_string($category);
    $res = $conn->query("SELECT * FROM fee_config WHERE category='$cat' AND is_active=1 ORDER BY sort_order");
    $cfg = [];
    if ($res) while ($r = $res->fetch_assoc()) $cfg[$r['fee_key']] = $r;
    return $cfg;
}


$action = $_GET['action'] ?? '';

require_once __DIR__ . '/auth_middleware.php';
// Fee preview actions called during enrollment wizard (no token yet)
$publicActions = ['get_fee_preview', 'get_shs_fee', 'get_tvet_fee', 'get_fee_config'];
$authUser = in_array($action, $publicActions) ? null : requireAuth($conn);

// ── Auto-create required tables if missing (so migrate.php is not mandatory) ──
$conn->query("CREATE TABLE IF NOT EXISTS tuition_fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL UNIQUE,
    units INT DEFAULT 0,
    tuition_fee DECIMAL(10,2) DEFAULT 0,
    miscellaneous_fee DECIMAL(10,2) DEFAULT 0,
    registration_fee DECIMAL(10,2) DEFAULT 0,
    laboratory_fee DECIMAL(10,2) DEFAULT 0,
    energy_fee DECIMAL(10,2) DEFAULT 0,
    subtotal DECIMAL(10,2) DEFAULT 0,
    discount DECIMAL(10,2) DEFAULT 0,
    installment_fee DECIMAL(10,2) DEFAULT 0,
    total_assessment DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS or_ar_sequences (
    year INT NOT NULL UNIQUE,
    last_seq INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS installment_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    payment_log_id INT DEFAULT NULL,
    or_ar_number VARCHAR(30) DEFAULT NULL,
    or_ar_type VARCHAR(5) DEFAULT 'OR',
    amount DECIMAL(10,2) DEFAULT 0,
    payment_date DATE DEFAULT NULL,
    payment_method VARCHAR(20) DEFAULT 'Cash',
    gcash_reference VARCHAR(100) DEFAULT NULL,
    exam_period VARCHAR(30) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    recorded_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS payment_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL UNIQUE,
    payment_type VARCHAR(20) DEFAULT 'full',
    total_assessment DECIMAL(10,2) DEFAULT 0,
    prelim_due DECIMAL(10,2) DEFAULT 0,
    prelim_paid DECIMAL(10,2) DEFAULT 0,
    prelim_status VARCHAR(20) DEFAULT 'locked',
    prelim_unlocked_at DATETIME DEFAULT NULL,
    midterm_due DECIMAL(10,2) DEFAULT 0,
    midterm_paid DECIMAL(10,2) DEFAULT 0,
    midterm_status VARCHAR(20) DEFAULT 'locked',
    midterm_unlocked_at DATETIME DEFAULT NULL,
    finals_due DECIMAL(10,2) DEFAULT 0,
    finals_paid DECIMAL(10,2) DEFAULT 0,
    finals_status VARCHAR(20) DEFAULT 'locked',
    finals_unlocked_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS exam_permits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    exam_period VARCHAR(30) NOT NULL,
    school_year VARCHAR(20) DEFAULT NULL,
    semester VARCHAR(50) DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'Pending',
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME DEFAULT NULL,
    approved_by INT DEFAULT NULL,
    remarks TEXT DEFAULT NULL,
    UNIQUE KEY uniq_permit (student_id, exam_period, school_year, semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS payment_notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    exam_period VARCHAR(30) NOT NULL,
    amount_due DECIMAL(10,2) DEFAULT 0,
    due_date DATE DEFAULT NULL,
    message TEXT DEFAULT NULL,
    sent_by INT DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_notice (student_id, exam_period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── End auto-create ──

// ── Ensure required columns exist ──

// ── Ensure tuition_fees table ──
// Schema managed by migrate.php

// ── Ensure installment_payments table ──
// Schema managed by migrate.php
// Schema managed by migrate.php

// Schema managed by migrate.php

// Schema managed by migrate.php

// ── Ensure payment_logs has all required columns ──
$conn->query("ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS verified_by INT DEFAULT NULL");
$conn->query("ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS verified_at DATETIME DEFAULT NULL");
$conn->query("ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS gcash_amount DECIMAL(10,2) DEFAULT 0");
$conn->query("ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS gcash_date DATE DEFAULT NULL");
$conn->query("ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS gcash_reference VARCHAR(100) DEFAULT NULL");
$conn->query("ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS payment_method VARCHAR(20) DEFAULT 'GCash'");
$conn->query("ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS notes TEXT DEFAULT NULL");
$conn->query("ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'Pending'");
$conn->query("ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS exam_period VARCHAR(30) DEFAULT NULL");
$conn->query("ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(100) DEFAULT NULL");
$conn->query("ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS semester VARCHAR(100) DEFAULT NULL");
// ── Ensure students table has accounting approval columns ──
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS accounting_approved_by INT DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS accounting_approved_at DATETIME DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS accounting_notes TEXT DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS enrollment_status VARCHAR(30) DEFAULT 'Pending'");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS payment_status VARCHAR(20) DEFAULT 'Pending'");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS approval_status VARCHAR(20) DEFAULT 'Pending'");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS payment_plan VARCHAR(20) DEFAULT 'full'");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS payment_method VARCHAR(20) DEFAULT 'GCash'");
// ── End column fixes ──

// Fix legacy Cash logs
$conn->query("UPDATE payment_logs SET payment_method = 'Cash' WHERE gcash_reference = 'CASH-PAYMENT' AND payment_method = 'GCash'");

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        switch ($action) {
            case 'get_pending_payments':  getPendingPayments($conn);  break;
            case 'get_payment_history':          getPaymentHistory($conn);             break;
            case 'get_student_payment_history':  getStudentPaymentHistory($conn);      break;
            case 'get_tuition_fees':      getTuitionFees($conn);      break;
            case 'get_liquidation':       getLiquidation($conn);      break;
            case 'get_student_receipts':  getStudentReceipts($conn);  break;
            case 'get_fee_preview':       getFeePreview($conn);       break;
            case 'get_payment_schedule':      getPaymentSchedule($conn);      break;
            case 'get_exam_permits':          getExamPermits($conn);          break;
            case 'get_student_permit_status': getStudentPermitStatus($conn);  break;
            case 'get_payment_notices':       getPaymentNotices($conn);       break;
            case 'get_all_enrolled_students': getAllEnrolledStudents($conn); break;
            case 'recalc_payment_schedules':  recalcAllPaymentSchedules($conn); break;
            case 'get_student_installment':   getStudentInstallment($conn); break;
            case 'get_permit_details':         getPermitDetails($conn);      break;
            case 'get_installment_students':   getInstallmentStudents($conn); break;
            case 'get_shs_fee':               getSHSFee($conn);              break;
            case 'get_tvet_fee':              getTVETFee($conn);             break;
            case 'get_fee_config':            getFeeConfig($conn);           break;
            // ── Income Report Generator ──
            case 'get_income_report':         getIncomeReport($conn);        break;
            case 'get_income_summary':        getIncomeSummary($conn);       break;
            case 'get_income_by_program':     getIncomeByProgram($conn);     break;
            // ── Payment Due Dates ──
            case 'get_due_dates':             getPaymentDueDates($conn);     break;
            default: echo json_encode(['success' => false, 'message' => 'Unknown action']);
        }
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { echo json_encode(['success' => false, 'message' => 'Invalid JSON']); exit(); }
        switch ($action) {
            case 'submit_gcash':        submitGcash($conn, $data);        break;
            case 'verify_payment':      verifyPayment($conn, $data);      break;
            case 'reject_payment':      rejectPayment($conn, $data);      break;
            case 'compute_fees':        computeFees($conn, $data);        break;
            case 'save_fee_config':     saveFeeConfig($conn, $data);      break;
            case 'add_fee_config':      addFeeConfig($conn, $data);       break;
            case 'delete_fee_config':   deleteFeeConfig($conn, $data);    break;
            case 'record_installment':  recordInstallment($conn, $data);  break;
            case 'send_payment_notice':  sendPaymentNotice($conn, $data);  break;
            case 'send_bulk_notice':      sendBulkNotice($conn, $data);      break;
            case 'request_exam_permit':  requestExamPermit($conn, $data);  break;
            case 'process_exam_permit':  processExamPermit($conn, $data);  break;
            case 'unlock_payment_period': unlockPaymentPeriod($conn, $data); break;
            case 'submit_installment_payment': submitInstallmentPayment($conn, $data); break;
            case 'edit_payment':              editPayment($conn, $data);              break;
            case 'correct_verified_payment': correctVerifiedPayment($conn, $data); break;
            // ── Payment Due Dates ──
            case 'save_due_dates':            savePaymentDueDates($conn, $data); break;
            default: echo json_encode(['success' => false, 'message' => 'Unknown action']);
        }
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
$conn->close();

// ─────────────────────────────────────────────────────────────
// FEE PREVIEW — called BEFORE payment so student sees breakdown
// ─────────────────────────────────────────────────────────────
// FEE PREVIEW — called BEFORE payment so student sees breakdown
// GET ?action=get_fee_preview&program=BS+IT&student_id=XX&discount=0&has_installment=0
//
// Priority for units:
//  1. tuition_fees table (set by registrar TOR evaluation for transferees)
//  2. program_courses → courses sum
//  3. courses.program sum
//  4. default 18
// ─────────────────────────────────────────────────────────────
function getFeePreview($conn) {
    $program_name = trim($_GET['program']    ?? '');
    $student_id   = (int)($_GET['student_id'] ?? 0);
    // year_level and semester passed from login page (pre-registration, no student_id yet)
    $param_year_level = trim($_GET['year_level'] ?? '');
    $param_semester   = trim($_GET['semester']   ?? '');
    // Accept override discount and installment flag from login page (before student exists in DB)
    $override_discount    = isset($_GET['discount'])        ? (float)$_GET['discount']         : null;
    $override_installment = isset($_GET['has_installment']) ? (bool)(int)$_GET['has_installment'] : null;

    if (!$program_name) {
        echo json_encode(['success' => false, 'message' => 'program required']); return;
    }

    // Route SHS / TVET students to their own fee functions
    // so the college formula (units x650, misc, lab, energy) is never applied to them.
    if ($student_id > 0) {
        $catRes  = $conn->query("SELECT student_category, student_type, payment_plan, scholarship_amount FROM students WHERE id=$student_id LIMIT 1");
        $catRow  = $catRes ? $catRes->fetch_assoc() : null;
        $cat     = strtoupper(trim($catRow['student_category'] ?? ''));
        $stype   = trim($catRow['student_type'] ?? 'New');
        $disc    = $override_discount !== null ? $override_discount : (float)($catRow['scholarship_amount'] ?? 0);
        $hasInst = $override_installment !== null ? $override_installment : (($catRow['payment_plan'] ?? 'full') === 'installment');
        if ($cat === 'SHS') {
            $_GET['student_type']    = $stype;
            $_GET['student_id']      = $student_id;
            $_GET['discount']        = $disc;
            $_GET['has_installment'] = $hasInst ? 1 : 0;
            getSHSFee($conn);
            return;
        }
        if ($cat === 'TVET') {
            $_GET['student_type']    = $stype;
            $_GET['student_id']      = $student_id;
            $_GET['program']         = $program_name;
            $_GET['discount']        = $disc;
            $_GET['has_installment'] = $hasInst ? 1 : 0;
            getTVETFee($conn);
            return;
        }
    }

    $pn    = $conn->real_escape_string($program_name);
    $units = 0;

    // 1. For transferees: always use tor_evaluations.approved_units FIRST.
    //    This is the post-credit unit count the registrar approved — it overrides
    //    any stale tuition_fees.units that may have been saved before evaluation.
    if ($student_id > 0) {
        $typeRes = $conn->query("SELECT student_type FROM students WHERE id=$student_id LIMIT 1");
        $typeRow = $typeRes ? $typeRes->fetch_assoc() : null;
        if (trim($typeRow['student_type'] ?? '') === 'Transferee') {
            $te = $conn->query("SELECT approved_units FROM tor_evaluations WHERE student_id = $student_id AND status = 'Evaluated' LIMIT 1");
            $te_row = $te ? $te->fetch_assoc() : null;
            if ($te_row && (int)$te_row['approved_units'] > 0) {
                $units = (int)$te_row['approved_units'];
            }
        }
    }

    // 2. Non-transferee or unevaluated: check tuition_fees table
    if ($units <= 0 && $student_id > 0) {
        $tf_res = $conn->query("SELECT units FROM tuition_fees WHERE student_id = $student_id LIMIT 1");
        $tf_row = $tf_res ? $tf_res->fetch_assoc() : null;
        if ($tf_row && (int)$tf_row['units'] > 0) {
            $units = (int)$tf_row['units'];
        }
    }

    // 2. Resolve year_level and semester — from query params (login page, no student yet)
    //    or from the student's DB record (enrolled student with student_id).
    //    year_level is REQUIRED to avoid counting courses from all years.
    //    semester is stripped to term-only so it matches courses of any school year.
    $year_level = '';
    $semester_term = '';

    // Priority 1: explicit query params (login page pre-registration)
    if ($param_year_level !== '') {
        $year_level = $conn->real_escape_string($param_year_level);
    }
    if ($param_semester !== '') {
        if (preg_match('/^(1st Semester|2nd Semester|Summer|Midyear)/i', $param_semester, $m)) {
            $semester_term = $conn->real_escape_string($m[1]);
        } else {
            $semester_term = $conn->real_escape_string($param_semester);
        }
    }

    // Priority 2: student DB record (if student_id provided)
    if ($student_id > 0) {
        $stRes = $conn->query("SELECT semester, year_level FROM students WHERE id = $student_id LIMIT 1");
        $stRow = $stRes ? $stRes->fetch_assoc() : null;
        if ($stRow) {
            if ($year_level === '') $year_level = $conn->real_escape_string(trim($stRow['year_level'] ?? ''));
            if ($semester_term === '') {
                $rawSem = trim($stRow['semester'] ?? '');
                if (preg_match('/^(1st Semester|2nd Semester|Summer|Midyear)/i', $rawSem, $m)) {
                    $semester_term = $conn->real_escape_string($m[1]);
                }
            }
        }
    }

    // Build filter clauses — both semester AND year_level are required for correct counts
    $semFilter  = ($semester_term !== '') ? "AND c.semester LIKE '$semester_term%'" : '';
    $ylFilter   = ($year_level !== '')    ? "AND c.year_level = '$year_level'"       : '';
    $sfNoJoin   = ($semester_term !== '') ? "AND semester LIKE '$semester_term%'"    : '';
    $ylNoJoin   = ($year_level !== '')    ? "AND year_level = '$year_level'"         : '';

    // 2. Sum from program_courses → programs → courses, filtered by year_level + semester
    if ($units <= 0) {
        $units_res = $conn->query("
            SELECT COALESCE(SUM(c.credits), 0) AS total_units
            FROM program_courses pc
            JOIN programs p ON pc.program_id = p.id
            JOIN courses  c ON pc.course_id  = c.id
            WHERE (p.name = '$pn' OR p.code = '$pn')
            $ylFilter $semFilter
        ");
        $units_row = $units_res ? $units_res->fetch_assoc() : null;
        $units     = (int)($units_row['total_units'] ?? 0);
    }

    // 3. Fallback: courses.program column
    if ($units <= 0) {
        $fb    = $conn->query("SELECT COALESCE(SUM(credits),0) AS total_units FROM courses WHERE program='$pn' $ylNoJoin $sfNoJoin");
        $units = (int)(($fb ? $fb->fetch_assoc()['total_units'] : 0) ?: 0);
    }

    // 4. Hard fallback
    if ($units <= 0) $units = 18;

    // Scholar discount
    // Priority: override param (from login page before DB record exists) → DB scholarship_amount
    $discount = 0.00;
    if ($override_discount !== null) {
        $discount = (float)$override_discount;
    } elseif ($student_id > 0) {
        $sr = $conn->prepare("SELECT scholarship_amount FROM students WHERE id = ?");
        $sr->bind_param("i", $student_id);
        $sr->execute();
        $srow    = $sr->get_result()->fetch_assoc();
        $discount = (float)($srow['scholarship_amount'] ?? 0);
    }

    // Installment flag — override param (login page) or check existing tuition_fees record
    $has_installment = false;
    if ($override_installment !== null) {
        $has_installment = $override_installment;
    } elseif ($student_id > 0) {
        $pi_res = $conn->query("SELECT payment_plan FROM students WHERE id = $student_id LIMIT 1");
        $pi_row = $pi_res ? $pi_res->fetch_assoc() : null;
        $has_installment = ($pi_row['payment_plan'] ?? 'full') === 'installment';
    }

    // Lab fee: based on total number of Laboratory rooms
    $lab_res   = $conn->query("SELECT COUNT(*) AS cnt FROM rooms WHERE room_type = 'Laboratory'");
    $lab_count = (int)(($lab_res ? $lab_res->fetch_assoc()['cnt'] : 0) ?? 0);

    // Load rates from fee_config (managed by Accounting)
    $fc = loadFeeConfig($conn, 'College');
    $r_tuition  = (float)($fc['tuition_rate_per_unit']['value'] ?? 650);
    $r_misc     = (float)($fc['misc_fee']['value']              ?? 6688);
    $r_reg      = (float)($fc['reg_fee']['value']               ?? 700);
    $r_lab_room = (float)($fc['lab_fee_per_room']['value']      ?? 1900);
    $r_energy   = (float)($fc['energy_rate_per_unit']['value']  ?? 63);
    $r_install  = (float)($fc['installment_fee']['value']       ?? 750);
    // Any extra active fees (not one of the standard built-in keys)
    $standard_keys = ['tuition_rate_per_unit','misc_fee','reg_fee','lab_fee_per_room','energy_rate_per_unit','installment_fee'];
    $extra_fees      = 0.00;
    $extra_fees_list = [];   // NEW: individual line items for the UI
    foreach ($fc as $fk => $frow) {
        if (!in_array($fk, $standard_keys)) {
            $line_amount = (float)$frow['value'] * ($frow['is_per_unit'] ? $units : 1);
            $extra_fees += $line_amount;
            $extra_fees_list[] = [
                'fee_key'    => $fk,
                'fee_label'  => $frow['fee_label'],
                'is_per_unit'=> (int)$frow['is_per_unit'],
                'rate'       => (float)$frow['value'],
                'amount'     => $line_amount,
            ];
        }
    }

    // Fee computation
    $tuition_fee    = $units * $r_tuition;
    $miscellaneous  = $r_misc;
    $registration   = $r_reg;
    $laboratory_fee = $lab_count * $r_lab_room;
    $energy_fee     = $units * $r_energy;
    $subtotal       = $tuition_fee + $miscellaneous + $registration + $laboratory_fee + $energy_fee + $extra_fees;
    $installment_fee = $has_installment ? $r_install : 0.00;
    $total          = max(0, $subtotal - $discount + $installment_fee);

    // If student already has enrolled courses, use ACTUAL enrolled credits as units
    // This ensures SOA always matches the enrolled subjects list
    if ($student_id > 0) {
        $enrolledUnitsRes = $conn->query("
            SELECT COALESCE(SUM(c.credits), 0) AS enrolled_units
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            WHERE e.student_id = $student_id
              AND e.status IN ('Enrolled','Pending')
        ");
        $enrolledUnits = (int)(($enrolledUnitsRes ? $enrolledUnitsRes->fetch_assoc()['enrolled_units'] : 0) ?? 0);
        if ($enrolledUnits > 0) {
            // Recompute fees based on actual enrolled units
            $units          = $enrolledUnits;
            $tuition_fee    = $units * $r_tuition;
            $energy_fee     = $units * $r_energy;
            // recalculate extra per-unit fees
            $extra_fees      = 0.00;
            $extra_fees_list = [];
            foreach ($fc as $fk => $frow) {
                if (!in_array($fk, $standard_keys)) {
                    $line_amount = (float)$frow['value'] * ($frow['is_per_unit'] ? $units : 1);
                    $extra_fees += $line_amount;
                    $extra_fees_list[] = [
                        'fee_key'    => $fk,
                        'fee_label'  => $frow['fee_label'],
                        'is_per_unit'=> (int)$frow['is_per_unit'],
                        'rate'       => (float)$frow['value'],
                        'amount'     => $line_amount,
                    ];
                }
            }
            $subtotal       = $tuition_fee + $miscellaneous + $registration + $laboratory_fee + $energy_fee + $extra_fees;
            $total          = max(0, $subtotal - $discount + $installment_fee);
        }
    }

    // Save / update tuition_fees if student_id provided
    if ($student_id > 0) {
        $stmt = $conn->prepare("
            INSERT INTO tuition_fees
                (student_id, units, tuition_fee, miscellaneous_fee, registration_fee,
                 laboratory_fee, energy_fee, subtotal, discount, installment_fee, total_assessment)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                units=VALUES(units), tuition_fee=VALUES(tuition_fee),
                miscellaneous_fee=VALUES(miscellaneous_fee),
                registration_fee=VALUES(registration_fee), laboratory_fee=VALUES(laboratory_fee),
                energy_fee=VALUES(energy_fee), subtotal=VALUES(subtotal),
                discount=VALUES(discount), installment_fee=VALUES(installment_fee),
                total_assessment=VALUES(total_assessment)
        ");
        $stmt->bind_param("iiddddddddd",
            $student_id, $units, $tuition_fee, $miscellaneous, $registration,
            $laboratory_fee, $energy_fee, $subtotal, $discount, $installment_fee, $total);
        $stmt->execute();
    }

    echo json_encode([
        'success' => true,
        'fees' => [
            'units'            => $units,
            'tuitionFee'       => $tuition_fee,
            'miscellaneousFee' => $miscellaneous,
            'registrationFee'  => $registration,
            'laboratoryFee'    => $laboratory_fee,
            'energyFee'        => $energy_fee,
            'extraFees'        => $extra_fees_list,
            'subtotal'         => $subtotal,
            'discount'         => $discount,
            'installmentFee'   => $installment_fee,
            'totalAssessment'  => $total,
        ]
    ]);
}

// ─────────────────────────────────────────────────────────────
// COMPUTE & SAVE TUITION FEES
// POST ?action=compute_fees
// Body: { student_id, units, has_installment? }
//
// FORMULA:
//   Tuition Fee    = units × 650
//   Miscellaneous  = 6,688 (fixed)
//   Registration   = 700   (fixed)
//   Laboratory Fee = units × 1,900
//   Energy Fee     = units × 21 × 3
//   Subtotal       = sum of above
//   Discount       = scholar discount (from students table)
//   Installment    = 750 if installment, else 0
//   Total          = Subtotal - Discount + Installment Fee
// ─────────────────────────────────────────────────────────────
function computeFees($conn, $data) {
    $student_id      = (int)($data['student_id']      ?? 0);
    $units           = (int)($data['units']           ?? 18);
    $has_installment = (bool)($data['has_installment'] ?? false);

    if (!$student_id || $units <= 0) {
        echo json_encode(['success' => false, 'message' => 'student_id and units required']); return;
    }

    $sr = $conn->prepare("SELECT scholarship_amount FROM students WHERE id = ?");
    $sr->bind_param("i", $student_id);
    $sr->execute();
    $srow = $sr->get_result()->fetch_assoc();
    $discount = (float)($srow['scholarship_amount'] ?? 0);

    // Count lab subjects for this student's program
    $prog_res  = $conn->query("SELECT program FROM students WHERE id = $student_id LIMIT 1");
    $prog_row  = $prog_res ? $prog_res->fetch_assoc() : null;
    $prog_name = $conn->real_escape_string($prog_row['program'] ?? '');
    // Lab fee: based on total number of Laboratory rooms (same for all students)
    $lab_res2  = $conn->query("SELECT COUNT(*) AS cnt FROM rooms WHERE room_type = 'Laboratory'");
    $lab_count = (int)(($lab_res2 ? $lab_res2->fetch_assoc()['cnt'] : 0) ?? 0);

    $fc2 = loadFeeConfig($conn, 'College');
    $r_tuition  = (float)($fc2['tuition_rate_per_unit']['value'] ?? 650);
    $r_misc     = (float)($fc2['misc_fee']['value']              ?? 6688);
    $r_reg      = (float)($fc2['reg_fee']['value']               ?? 700);
    $r_lab_room = (float)($fc2['lab_fee_per_room']['value']      ?? 1900);
    $r_energy   = (float)($fc2['energy_rate_per_unit']['value']  ?? 63);
    $r_install  = (float)($fc2['installment_fee']['value']       ?? 750);
    $std_keys2  = ['tuition_rate_per_unit','misc_fee','reg_fee','lab_fee_per_room','energy_rate_per_unit','installment_fee'];
    $extra2 = 0.00;
    $extra2_list = [];
    foreach ($fc2 as $fk => $frow) {
        if (!in_array($fk, $std_keys2)) {
            $line_amt = (float)$frow['value'] * ($frow['is_per_unit'] ? $units : 1);
            $extra2 += $line_amt;
            $extra2_list[] = [
                'fee_key'    => $fk,
                'fee_label'  => $frow['fee_label'],
                'is_per_unit'=> (int)$frow['is_per_unit'],
                'rate'       => (float)$frow['value'],
                'amount'     => $line_amt,
            ];
        }
    }

    $tuition_fee    = $units * $r_tuition;
    $miscellaneous  = $r_misc;
    $registration   = $r_reg;
    $laboratory_fee = $lab_count * $r_lab_room;
    $energy_fee     = $units * $r_energy;
    $subtotal       = $tuition_fee + $miscellaneous + $registration + $laboratory_fee + $energy_fee + $extra2;
    $installment_fee = $has_installment ? $r_install : 0.00;
    $total          = max(0, $subtotal - $discount + $installment_fee);

    $stmt = $conn->prepare("
        INSERT INTO tuition_fees (student_id, units, tuition_fee, miscellaneous_fee, registration_fee, laboratory_fee, energy_fee, subtotal, discount, installment_fee, total_assessment)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            units=VALUES(units), tuition_fee=VALUES(tuition_fee), miscellaneous_fee=VALUES(miscellaneous_fee),
            registration_fee=VALUES(registration_fee), laboratory_fee=VALUES(laboratory_fee),
            energy_fee=VALUES(energy_fee), subtotal=VALUES(subtotal), discount=VALUES(discount),
            installment_fee=VALUES(installment_fee), total_assessment=VALUES(total_assessment)
    ");
    $stmt->bind_param("iiddddddddd", $student_id, $units, $tuition_fee, $miscellaneous, $registration, $laboratory_fee, $energy_fee, $subtotal, $discount, $installment_fee, $total);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'fees' => [
            'units'            => $units,
            'tuitionFee'       => $tuition_fee,
            'miscellaneousFee' => $miscellaneous,
            'registrationFee'  => $registration,
            'laboratoryFee'    => $laboratory_fee,
            'energyFee'        => $energy_fee,
            'extraFees'        => $extra2_list,
            'subtotal'         => $subtotal,
            'discount'         => $discount,
            'installmentFee'   => $installment_fee,
            'totalAssessment'  => $total,
        ]
    ]);
}

// ─────────────────────────────────────────────────────────────
// SHARED HELPER: build extraFees line items from fee_config
// ─────────────────────────────────────────────────────────────
function _buildExtraFeesList(mysqli $conn, string $category, int $units): array {
    $stdKeys = ['tuition_rate_per_unit','misc_fee','reg_fee','lab_fee_per_room','energy_rate_per_unit','installment_fee'];
    $cat     = $conn->real_escape_string($category);
    $res     = $conn->query("SELECT fee_key, fee_label, value, is_per_unit FROM fee_config WHERE category='$cat' AND is_active=1 ORDER BY sort_order");
    $list    = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            if (!in_array($r['fee_key'], $stdKeys)) {
                $amt    = (float)$r['value'] * ($r['is_per_unit'] ? $units : 1);
                $list[] = [
                    'fee_key'    => $r['fee_key'],
                    'fee_label'  => $r['fee_label'],
                    'is_per_unit'=> (int)$r['is_per_unit'],
                    'rate'       => (float)$r['value'],
                    'amount'     => $amt,
                ];
            }
        }
    }
    return $list;
}

// ─────────────────────────────────────────────────────────────
// GET TUITION FEES for a student
// GET ?action=get_tuition_fees&student_id=XX
// ─────────────────────────────────────────────────────────────
function getTuitionFees($conn) {
    $student_id = (int)($_GET['student_id'] ?? 0);
    if (!$student_id) { echo json_encode(['success' => false, 'message' => 'student_id required']); return; }

    $res = $conn->query("SELECT * FROM tuition_fees WHERE student_id = $student_id LIMIT 1");
    $row = $res ? $res->fetch_assoc() : null;

    $paid_res   = $conn->query("SELECT COALESCE(SUM(amount),0) AS total_paid FROM installment_payments WHERE student_id = $student_id");
    $total_paid = (float)($paid_res->fetch_assoc()['total_paid'] ?? 0);

    if (!$row) { echo json_encode(['success' => false, 'message' => 'No fee record found']); return; }

    $balance = max(0, (float)$row['total_assessment'] - $total_paid);

    // Build extra fees list from fee_config
    $extraList = _buildExtraFeesList($conn, 'College', (int)$row['units']);

    echo json_encode([
        'success' => true,
        'fees' => [
            'units'            => (int)$row['units'],
            'tuitionFee'       => (float)$row['tuition_fee'],
            'miscellaneousFee' => (float)$row['miscellaneous_fee'],
            'registrationFee'  => (float)$row['registration_fee'],
            'laboratoryFee'    => (float)$row['laboratory_fee'],
            'energyFee'        => (float)$row['energy_fee'],
            'extraFees'        => $extraList,
            'subtotal'         => (float)$row['subtotal'],
            'discount'         => (float)$row['discount'],
            'installmentFee'   => (float)$row['installment_fee'],
            'totalAssessment'  => (float)$row['total_assessment'],
            'totalPaid'        => $total_paid,
            'balance'          => $balance,
            'paymentStatus'    => $balance <= 0 ? 'Fully Paid' : ($total_paid > 0 ? 'Partial' : 'Unpaid'),
        ]
    ]);
}

// ─────────────────────────────────────────────────────────────
// RECORD INSTALLMENT PAYMENT
// POST ?action=record_installment
// Body: { student_id, amount, payment_date, payment_method, gcash_reference?, exam_period, notes?, accounting_user_id, or_ar_type }
// ─────────────────────────────────────────────────────────────
function recordInstallment($conn, $data) {
    $student_id     = (int)($data['student_id']         ?? 0);
    $amount         = (float)($data['amount']            ?? 0);
    $payment_date   = trim($data['payment_date']        ?? date('Y-m-d'));
    $payment_method = trim($data['payment_method']      ?? 'Cash');
    $gcash_ref      = trim($data['gcash_reference']     ?? '');
    $exam_period    = trim($data['exam_period']         ?? 'Downpayment');
    $notes          = trim($data['notes']               ?? '');
    $acc_user_id    = (int)($data['accounting_user_id'] ?? 0);
    $or_ar_type     = trim($data['or_ar_type']          ?? 'AR');

    if (!$student_id || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'student_id and amount required']); return;
    }

    // Generate sequential OR/AR number: AR-20260001
    // FIX AC-02: Atomic OR/AR via sequence table (avoids race condition)
    $year = (int)date('Y');
    $conn->query("INSERT INTO or_ar_sequences (year, last_seq) VALUES ($year, 1) ON DUPLICATE KEY UPDATE last_seq = last_seq + 1");
    $seqRow   = $conn->query("SELECT last_seq FROM or_ar_sequences WHERE year = $year")->fetch_assoc();
    $seq      = (int)($seqRow['last_seq'] ?? 1);
    $or_ar_no = $or_ar_type . '-' . $year . str_pad($seq, 4, '0', STR_PAD_LEFT);

    $stmt = $conn->prepare("
        INSERT INTO installment_payments (student_id, or_ar_number, or_ar_type, amount, payment_date, payment_method, gcash_reference, exam_period, notes, recorded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issdsssssi", $student_id, $or_ar_no, $or_ar_type, $amount, $payment_date, $payment_method, $gcash_ref, $exam_period, $notes, $acc_user_id);
    $stmt->execute();

    // Check if fully paid
    $feeStmt = $conn->prepare("SELECT total_assessment FROM tuition_fees WHERE student_id = ? LIMIT 1");
    $feeStmt->bind_param("i", $student_id);
    $feeStmt->execute();
    $fee_res = $feeStmt->get_result();
    $fee_row          = $fee_res ? $fee_res->fetch_assoc() : null;
    $total_assessment = (float)($fee_row['total_assessment'] ?? 0);

    $paidStmt2 = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS tp FROM installment_payments WHERE student_id = ?");
    $paidStmt2->bind_param("i", $student_id);
    $paidStmt2->execute();
    $paid_res = $paidStmt2->get_result();
    $total_paid = (float)($paid_res->fetch_assoc()['tp'] ?? 0);
    $is_fully_paid = $total_assessment > 0 && $total_paid >= $total_assessment;

    $pay_status = $is_fully_paid ? 'Paid' : ($total_paid > 0 ? 'Partially Paid' : 'Pending');
    if ($is_fully_paid) {
        // Fully paid via cash installment — auto-approve and enroll
        $conn->prepare("UPDATE students SET payment_status='Paid', approval_status='Approved', enrollment_status='Enrolled' WHERE id=?")->bind_param("i", $student_id) ?: null;
        $updFull = $conn->prepare("UPDATE students SET payment_status='Paid', approval_status='Approved', enrollment_status='Enrolled' WHERE id=?");
        $updFull->bind_param("i", $student_id);
        $updFull->execute();
        // Auto-enroll in courses
        $semRow = $conn->query("SELECT semester FROM students WHERE id=$student_id LIMIT 1")->fetch_assoc();
        $semester = trim($semRow['semester'] ?? '');
        autoEnrollAll($conn, ['student_id' => $student_id, 'semester' => $semester], false);
    } else {
        // Partially paid — mark Approved so student can see SOA, but not fully enrolled yet
        $conn->query("UPDATE students SET payment_status='$pay_status', approval_status='Approved', enrollment_status='Enrolled' WHERE id=$student_id");
    }

    // ── Sync payment_schedules — recompute dues from actual DP, actual paid per period ──
    // Rule: DP can be any amount. Term dues = (total - dpPaid) / 3.
    // Each period's paid = actual SUM from installment_payments. No carry-over between terms.
    $schedChkStmt = $conn->prepare("SELECT id FROM payment_schedules WHERE student_id = ? LIMIT 1");
    $schedChkStmt->bind_param("i", $student_id);
    $schedChkStmt->execute();
    $sched_check = $schedChkStmt->get_result();
    if ($sched_check && $sched_check->num_rows > 0) {
        // Step 1: Get total assessment
        $tfR  = $conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id = $student_id LIMIT 1");
        $tfRw = $tfR ? $tfR->fetch_assoc() : null;
        $tot  = $tfRw ? (float)$tfRw['total_assessment'] : $total_assessment;

        // Step 2: Get actual DP paid
        $dpR   = $conn->query("SELECT COALESCE(SUM(amount),0) AS paid FROM installment_payments WHERE student_id = $student_id AND exam_period = 'Downpayment'");
        $dpPd  = $dpR ? (float)$dpR->fetch_assoc()['paid'] : 0;
        // Fall back to scheduled quarter if no DP recorded yet
        $dpEff = $dpPd > 0 ? $dpPd : ($tot > 0 ? round($tot / 4, 2) : 0);

        // Steps 3+4: Recompute all dues and paid amounts dynamically.
        // recomputeSchedule() redistributes remaining balance across unpaid terms
        // and stores actual paid per period from installment_payments.
        recomputeSchedule($conn, $student_id);
    }
    // ─────────────────────────────────────────────────────────────────────

    logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'RECORD_PAYMENT', 'student', $student_id,
        "Recorded $exam_period ₱" . number_format($amount, 2) . " for student ID $student_id (OR: $or_ar_no)");
    echo json_encode([
        'success'     => true,
        'message'     => 'Payment recorded.',
        'orArNumber'  => $or_ar_no,
        'orArType'    => $or_ar_type,
        'totalPaid'   => $total_paid,
        'balance'     => max(0, $total_assessment - $total_paid),
        'isFullyPaid' => $is_fully_paid,
    ]);
}

// ─────────────────────────────────────────────────────────────
// GET STUDENT RECEIPTS (student view)
// GET ?action=get_student_receipts&student_id=XX
// ─────────────────────────────────────────────────────────────
function getStudentReceipts($conn) {
    $student_id = (int)($_GET['student_id'] ?? 0);
    if (!$student_id) { echo json_encode(['success' => false, 'message' => 'student_id required']); return; }

    $fee_res = $conn->query("SELECT * FROM tuition_fees WHERE student_id = $student_id LIMIT 1");
    $fee_row = $fee_res ? $fee_res->fetch_assoc() : null;

    $st_res  = $conn->query("SELECT first_name, last_name, program, year_level, student_number, payment_plan FROM students WHERE id = $student_id LIMIT 1");
    $student = $st_res ? $st_res->fetch_assoc() : null;
    $paymentPlan = $student['payment_plan'] ?? 'full';

    // Self-heal payment_plan: if AR records exist in installment_payments, student is installment
    if ($paymentPlan === 'full') {
        $arCheck = $conn->query("SELECT id FROM installment_payments WHERE student_id = $student_id AND or_ar_type = 'AR' LIMIT 1");
        if ($arCheck && $arCheck->num_rows > 0) {
            $paymentPlan = 'installment';
            $conn->query("UPDATE students SET payment_plan = 'installment' WHERE id = $student_id");
        }
    }

    // FIX AC-05: Removed self-healing fee recomputation on every receipt load.
    // Fee corrections must be done explicitly via the computeFees action, not silently on read.

    // If tuition_fees row is missing, auto-compute and save it now
    // NOTE: Do NOT override if row already exists — registrar may have set correct values
    if (!$fee_row && $student) {
        $programName     = trim($student['program'] ?? '');
        $has_installment = ($paymentPlan === 'installment');

        // Get units — TOR approved_units first (transferees), then program_courses, then courses
        $units  = 0;
        $te     = $conn->query("SELECT approved_units FROM tor_evaluations WHERE student_id = $student_id AND status = 'Evaluated' LIMIT 1");
        $te_row = $te ? $te->fetch_assoc() : null;
        if ($te_row && (int)$te_row['approved_units'] > 0) {
            $units = (int)$te_row['approved_units'];
        }
        if ($units <= 0 && $programName) {
            $pn = $conn->real_escape_string($programName);
            $hasPCTable = $conn->query("SHOW TABLES LIKE 'program_courses'")->num_rows > 0;
            $hasPTable  = $conn->query("SHOW TABLES LIKE 'programs'")->num_rows > 0;
            if ($hasPCTable && $hasPTable) {
                $ur = $conn->query("SELECT SUM(c.credits) AS total FROM program_courses pc JOIN programs p ON pc.program_id=p.id JOIN courses c ON pc.course_id=c.id WHERE p.name='$pn' OR p.code='$pn'");
                $u  = $ur ? (int)($ur->fetch_assoc()['total'] ?? 0) : 0;
                if ($u > 0) $units = $u;
            }
            if ($units <= 0) {
                $ur = $conn->query("SELECT SUM(credits) AS total FROM courses WHERE program='$pn'");
                $u  = $ur ? (int)($ur->fetch_assoc()['total'] ?? 0) : 0;
                if ($u > 0) $units = $u;
            }
        }
        if ($units <= 0) $units = 18;

        // Count lab subjects (room LIKE '%Lab%') for this program — merge both sources
        $pn_esc  = $conn->real_escape_string($programName);
        $lab_res = $conn->query("
            SELECT COUNT(DISTINCT c.id) AS cnt FROM courses c
            WHERE c.is_lab = 1
              AND (c.program = '$pn_esc'
                OR c.id IN (SELECT pc.course_id FROM program_courses pc JOIN programs p ON pc.program_id=p.id WHERE p.name='$pn_esc' OR p.code='$pn_esc'))
        ");
        $lab_cnt = (int)(($lab_res ? $lab_res->fetch_assoc()['cnt'] : 0) ?? 0);

        // Compute fees
        $fc3 = loadFeeConfig($conn, 'College');
        $r3_tuition  = (float)($fc3['tuition_rate_per_unit']['value'] ?? 650);
        $r3_misc     = (float)($fc3['misc_fee']['value']              ?? 6688);
        $r3_reg      = (float)($fc3['reg_fee']['value']               ?? 700);
        $r3_lab_room = (float)($fc3['lab_fee_per_room']['value']      ?? 1900);
        $r3_energy   = (float)($fc3['energy_rate_per_unit']['value']  ?? 63);
        $r3_install  = (float)($fc3['installment_fee']['value']       ?? 750);
        $tuition       = $units * $r3_tuition;
        $miscellaneous = $r3_misc;
        $registration  = $r3_reg;
        $laboratory    = $lab_cnt * $r3_lab_room;
        $energy        = $units * $r3_energy;
        $subtotal      = $tuition + $miscellaneous + $registration + $laboratory + $energy;

        $disc_res  = $conn->query("SELECT is_scholar, scholarship_amount FROM students WHERE id = $student_id LIMIT 1");
        $disc_row  = $disc_res ? $disc_res->fetch_assoc() : null;
        $discount  = ($disc_row && $disc_row['is_scholar']) ? (float)($disc_row['scholarship_amount'] ?? 0) : 0;

        $install_fee = $has_installment ? $r3_install : 0.00;
        $total       = max(0, $subtotal - $discount + $install_fee);

        $conn->query("INSERT INTO tuition_fees (student_id, units, tuition_fee, miscellaneous_fee, registration_fee, laboratory_fee, energy_fee, subtotal, discount, installment_fee, total_assessment)
            VALUES ($student_id, $units, $tuition, $miscellaneous, $registration, $laboratory, $energy, $subtotal, $discount, $install_fee, $total)
            ON DUPLICATE KEY UPDATE units=$units, tuition_fee=$tuition, miscellaneous_fee=$miscellaneous, registration_fee=$registration, laboratory_fee=$laboratory, energy_fee=$energy, subtotal=$subtotal, discount=$discount, installment_fee=$install_fee, total_assessment=$total, updated_at=NOW()");

        $fee_res2 = $conn->query("SELECT * FROM tuition_fees WHERE student_id = $student_id LIMIT 1");
        $fee_row  = $fee_res2 ? $fee_res2->fetch_assoc() : null;
    }

    $ip_res = $conn->query("
        SELECT ip.*, u.first_name AS recorded_by_name
        FROM installment_payments ip
        LEFT JOIN users u ON ip.recorded_by = u.id
        WHERE ip.student_id = $student_id
        ORDER BY ip.payment_date ASC, ip.created_at ASC
    ");
    $payments   = [];
    $total_paid = 0;
    if ($ip_res) {
        while ($r = $ip_res->fetch_assoc()) {
            $payments[] = [
                'id'             => (int)$r['id'],
                'orArNumber'     => $r['or_ar_number'],
                'orArType'       => $r['or_ar_type'],
                'amount'         => (float)$r['amount'],
                'paymentDate'    => $r['payment_date'],
                'paymentMethod'  => $r['payment_method'],
                'gcashReference' => $r['gcash_reference'],
                'examPeriod'     => $r['exam_period'],
                'notes'          => $r['notes'],
                'recordedByName' => $r['recorded_by_name'] ?? 'Accounting',
            ];
            $total_paid += (float)$r['amount'];
        }
    }

    // FALLBACK: if no installment_payments records exist yet but payment_logs shows Verified,
    // backfill from payment_logs so existing verified students see their paid amount
    if (empty($payments)) {
        $pl_res = $conn->query("
            SELECT pl.*, u.first_name AS verified_by_name
            FROM payment_logs pl
            LEFT JOIN users u ON pl.verified_by = u.id
            WHERE pl.student_id = $student_id AND pl.status = 'Verified'
            ORDER BY pl.verified_at ASC
        ");
        if ($pl_res) {
            while ($r = $pl_res->fetch_assoc()) {
                $amount = (float)$r['gcash_amount'];
                // For old cash payments verified before the amount-capture fix,
                // gcash_amount was saved as 0 — use total_assessment instead
                if ($amount <= 0 && $fee_row) {
                    $amount = (float)$fee_row['total_assessment'];
                }

                // Auto-create installment_payments record so future loads use the proper table
                $year     = date('Y', strtotime($r['verified_at']));
                $cntRes   = $conn->query("SELECT COUNT(*) AS cnt FROM installment_payments WHERE YEAR(created_at) = $year");
                $cnt      = (int)($cntRes->fetch_assoc()['cnt'] ?? 0) + 1;
                $pm       = strtolower($r['payment_method']) === 'cash' ? 'Cash' : 'GCash';
                $plan     = $paymentPlan === 'installment' ? 'installment' : 'full';
                $orType   = ($plan === 'installment') ? 'AR' : 'OR';
                $period   = ($plan === 'installment') ? 'Downpayment' : 'Full';
                $orNo     = $orType . '-' . $year . str_pad($cnt, 4, '0', STR_PAD_LEFT);
                $pDate    = $r['gcash_date'] ?? date('Y-m-d', strtotime($r['verified_at']));
                $gcashRef = $r['gcash_reference'] ?? '';
                $logId    = (int)$r['id'];
                $verBy    = (int)($r['verified_by'] ?? 0);

                // Only insert if not already there
                $dupQ = $conn->query("SELECT id FROM installment_payments WHERE payment_log_id = $logId LIMIT 1");
                if ($dupQ && $dupQ->num_rows === 0) {
                    $conn->query("INSERT INTO installment_payments
                        (student_id, payment_log_id, or_ar_number, or_ar_type, amount, payment_date, payment_method, gcash_reference, exam_period, notes, recorded_by)
                        VALUES ($student_id, $logId, '$orNo', '$orType', $amount, '$pDate', '$pm', '" . $conn->real_escape_string($gcashRef) . "', '$period', '', $verBy)");
                }

                $payments[] = [
                    'id'             => $logId,
                    'orArNumber'     => $orNo,
                    'orArType'       => $orType,
                    'amount'         => $amount,
                    'paymentDate'    => $pDate,
                    'paymentMethod'  => $pm,
                    'gcashReference' => $gcashRef,
                    'examPeriod'     => $period,
                    'notes'          => '',
                    'recordedByName' => $r['verified_by_name'] ?? 'Accounting',
                ];
                $total_paid += $amount;
            }
        }
    }

    $total_assessment = $fee_row ? (float)$fee_row['total_assessment'] : 0;
    $balance          = max(0, $total_assessment - $total_paid);
    $is_fully_paid    = $total_assessment > 0 && $balance == 0;

    // Build per-term breakdown for installment students
    $termOrder     = ['Downpayment', 'Prelim', 'Midterm', 'Finals', 'Full'];
    $termBreakdown = [];
    foreach ($payments as $p) {
        $period = $p['examPeriod'];
        if (!isset($termBreakdown[$period])) {
            $termBreakdown[$period] = ['period' => $period, 'amountPaid' => 0, 'orArNumber' => '', 'orArType' => '', 'paymentDate' => '', 'paymentMethod' => ''];
        }
        $termBreakdown[$period]['amountPaid']    += $p['amount'];
        $termBreakdown[$period]['orArNumber']     = $p['orArNumber'];
        $termBreakdown[$period]['orArType']       = $p['orArType'];
        $termBreakdown[$period]['paymentDate']    = $p['paymentDate'];
        $termBreakdown[$period]['paymentMethod']  = $p['paymentMethod'];
    }
    // Sort by canonical term order
    $sortedTerms = [];
    foreach ($termOrder as $t) { if (isset($termBreakdown[$t])) $sortedTerms[] = $termBreakdown[$t]; }

    echo json_encode([
        'success'       => true,
        'student'       => $student,
        'paymentPlan'   => $paymentPlan,
        'fees'          => $fee_row ? [
            'units'            => (int)$fee_row['units'],
            'tuitionFee'       => (float)$fee_row['tuition_fee'],
            'miscellaneousFee' => (float)$fee_row['miscellaneous_fee'],
            'registrationFee'  => (float)$fee_row['registration_fee'],
            'laboratoryFee'    => (float)$fee_row['laboratory_fee'],
            'energyFee'        => (float)$fee_row['energy_fee'],
            'extraFees'        => _buildExtraFeesList($conn, 'College', (int)$fee_row['units']),
            'subtotal'         => (float)$fee_row['subtotal'],
            'discount'         => (float)$fee_row['discount'],
            'installmentFee'   => (float)$fee_row['installment_fee'],
            'totalAssessment'  => $total_assessment,
        ] : null,
        'payments'      => $payments,
        'termBreakdown' => $sortedTerms,
        'totalPaid'     => $total_paid,
        'balance'       => $balance,
        'isFullyPaid'   => $is_fully_paid,
        'paymentStatus' => $is_fully_paid ? 'Fully Paid' : ($total_paid > 0 ? 'Partial' : 'Unpaid'),
    ]);
}

// ─────────────────────────────────────────────────────────────
// LIQUIDATION / AUDIT REPORT
// GET ?action=get_liquidation&date_from=YYYY-MM-DD&date_to=YYYY-MM-DD
// ─────────────────────────────────────────────────────────────
function getLiquidation($conn) {
    $date_from = trim($_GET['date_from'] ?? date('Y-m-01'));
    $date_to   = trim($_GET['date_to']   ?? date('Y-m-d'));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = date('Y-m-01');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   $date_to   = date('Y-m-d');

    $df = $conn->real_escape_string($date_from);
    $dt = $conn->real_escape_string($date_to);

    $sql = "
        SELECT
            ip.id, ip.or_ar_number, ip.or_ar_type, ip.amount, ip.payment_date,
            ip.payment_method, ip.gcash_reference, ip.exam_period, ip.notes, ip.created_at,
            s.student_number, s.first_name, s.last_name, s.program, s.year_level,
            tf.total_assessment,
            u.first_name AS recorded_by_name
        FROM installment_payments ip
        JOIN students s ON ip.student_id = s.id
        LEFT JOIN tuition_fees tf ON tf.student_id = ip.student_id
        LEFT JOIN users u ON ip.recorded_by = u.id
        WHERE ip.payment_date BETWEEN '$df' AND '$dt'
        ORDER BY ip.payment_date ASC, ip.created_at ASC
    ";

    $result     = $conn->query($sql);
    $rows       = [];
    $total_cash  = 0;
    $total_gcash = 0;

    if ($result) {
        while ($r = $result->fetch_assoc()) {
            $amt = (float)$r['amount'];
            if (strtolower($r['payment_method']) === 'cash') $total_cash  += $amt;
            else                                              $total_gcash += $amt;

            $rows[] = [
                'id'             => (int)$r['id'],
                'orArNumber'     => $r['or_ar_number'],
                'orArType'       => $r['or_ar_type'],
                'studentNumber'  => $r['student_number'],
                'studentName'    => $r['last_name'] . ', ' . $r['first_name'],
                'program'        => $r['program'],
                'yearLevel'      => $r['year_level'],
                'paymentMethod'  => $r['payment_method'],
                'gcashReference' => $r['gcash_reference'],
                'examPeriod'     => $r['exam_period'],
                'amount'         => $amt,
                'totalAssessment'=> (float)($r['total_assessment'] ?? 0),
                'paymentDate'    => $r['payment_date'],
                'notes'          => $r['notes'],
                'recordedBy'     => $r['recorded_by_name'] ?? 'Accounting',
                'createdAt'      => $r['created_at'],
            ];
        }
    }

    echo json_encode([
        'success'      => true,
        'dateFrom'     => $date_from,
        'dateTo'       => $date_to,
        'entries'      => $rows,
        'totalEntries' => count($rows),
        'totalCash'    => $total_cash,
        'totalGCash'   => $total_gcash,
        'grandTotal'   => $total_cash + $total_gcash,
    ]);
}

// ─────────────────────────────────────────────────────────────
// STUDENT: Submit GCash reference
// POST ?action=submit_gcash
// ─────────────────────────────────────────────────────────────
function submitGcash($conn, $data) {
    $student_id = (int)($data['student_id']     ?? 0);
    $reference  = trim($data['gcash_reference'] ?? '');
    $amount     = (float)($data['gcash_amount'] ?? 0);
    $date       = trim($data['gcash_date']      ?? date('Y-m-d'));
    $txn_id     = trim($data['transaction_id']  ?? '');
    $semester   = trim($data['semester']        ?? '');

    if (!$student_id || !$reference || !$amount) {
        echo json_encode(['success' => false, 'message' => 'student_id, gcash_reference and gcash_amount are required']);
        return;
    }

    $stmt = $conn->prepare("
        UPDATE students
        SET gcash_reference = ?, gcash_amount = ?, gcash_date = ?,
            gcash_transaction_id = ?, payment_status = 'Pending', payment_method = 'GCash'
        WHERE id = ?
    ");
    $stmt->bind_param("sdssi", $reference, $amount, $date, $txn_id, $student_id);
    $stmt->execute();

    // Auto-detect exam_period based on payment_plan and what's already been paid
    $stPlan = $conn->query("SELECT payment_plan, enrollment_status FROM students WHERE id=$student_id LIMIT 1")->fetch_assoc();
    $paymentPlan    = $stPlan['payment_plan']    ?? 'full';
    $enrollmentStatus = $stPlan['enrollment_status'] ?? '';
    $exam_period    = '';
    if ($paymentPlan === 'installment') {
        // Determine which term this payment is for based on what's already paid
        $paidTerms = $conn->query("SELECT DISTINCT exam_period FROM installment_payments WHERE student_id=$student_id AND amount > 0");
        $paidList  = [];
        if ($paidTerms) while ($r = $paidTerms->fetch_assoc()) $paidList[] = $r['exam_period'];
        if (!in_array('Downpayment', $paidList))      $exam_period = 'Downpayment';
        elseif (!in_array('Prelim', $paidList))       $exam_period = 'Prelim';
        elseif (!in_array('Midterm', $paidList))      $exam_period = 'Midterm';
        else                                           $exam_period = 'Finals';
    } else {
        $exam_period = 'Full';
    }

    $checkStmt = $conn->prepare("SELECT id FROM payment_logs WHERE student_id = ? AND status = 'Pending' LIMIT 1");
    $checkStmt->bind_param("i", $student_id);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();

    if ($existing) {
        $upd = $conn->prepare("
            UPDATE payment_logs
            SET payment_method = 'GCash', gcash_reference = ?, gcash_amount = ?,
                gcash_date = ?, transaction_id = ?, semester = ?, exam_period = ?
            WHERE id = ?
        ");
        $upd->bind_param("sdssssi", $reference, $amount, $date, $txn_id, $semester, $exam_period, $existing['id']);
        $upd->execute();
        $log_id = $existing['id'];
    } else {
        $ins = $conn->prepare("
            INSERT INTO payment_logs (student_id, payment_method, gcash_reference, gcash_amount, gcash_date, transaction_id, semester, exam_period, status)
            VALUES (?, 'GCash', ?, ?, ?, ?, ?, ?, 'Pending')
        ");
        $ins->bind_param("isdssss", $student_id, $reference, $amount, $date, $txn_id, $semester, $exam_period);
        $ins->execute();
        $log_id = $ins->insert_id;
    }

    echo json_encode(['success' => true, 'message' => 'GCash payment submitted. Waiting for accounting verification.', 'log_id' => $log_id, 'exam_period' => $exam_period]);
}

// ─────────────────────────────────────────────────────────────
// ACCOUNTING: Get pending payments
// ─────────────────────────────────────────────────────────────
function _getStudentPaymentPlan($conn, $sid) {
    $r = $conn->query("SELECT payment_plan FROM students WHERE id=$sid LIMIT 1");
    return $r ? ($r->fetch_assoc()['payment_plan'] ?? 'full') : 'full';
}
// ─────────────────────────────────────────────────────────────────────────────
// SHARED HELPER: Compute installment term dues based on actual payments.
//
//   Rule (mirrors Angular enrollment.ts installmentAmounts getter):
//     - After each paid term, remaining balance is split EQUALLY among ALL
//       subsequent terms (paid or not — uses actual credit, not scheduled due)
//     - Overpay → all later terms get cheaper
//     - Underpay → all later terms get more expensive
//
//   Returns: ['downpayment'=>N, 'prelim'=>N, 'midterm'=>N, 'finals'=>N, 'total'=>N]
//   where each value is the DUE for that term (show actual paid if paid, else
//   the recomputed share).
// ─────────────────────────────────────────────────────────────────────────────
function _calcInstallmentDues(float $total, array $paid): array {
    // ─────────────────────────────────────────────────────────────────────────
    // Computes the DUE amount for each installment term given what was actually
    // paid per period.
    //
    //   Logic:
    //     - DP due = total / 4  (scheduled quarter)
    //     - DP credit = actual dpPaid (could be less OR more than scheduled)
    //     - Remaining after DP credit is split equally among remaining terms
    //     - This means underpayment at any term increases future term dues,
    //       and overpayment decreases them — balance always preserved.
    //
    //   Returns:
    //     'downpayment' => the scheduled DP due (not what was paid)
    //     'prelim'      => computed due for prelim (after DP credit)
    //     'midterm'     => computed due for midterm (after DP+Prelim credit)
    //     'finals'      => whatever is left after all prior credits
    //     'balance'     => total - sum of all actual payments
    // ─────────────────────────────────────────────────────────────────────────
    if ($total <= 0) return ['downpayment'=>0.0,'prelim'=>0.0,'midterm'=>0.0,'finals'=>0.0,'total'=>0.0,'balance'=>0.0];

    $dpPaid  = (float)($paid['Downpayment'] ?? 0.0);
    $prPaid  = (float)($paid['Prelim']      ?? 0.0);
    $midPaid = (float)($paid['Midterm']     ?? 0.0);
    $finPaid = (float)($paid['Finals']      ?? 0.0);
    $allPaid = $dpPaid + $prPaid + $midPaid + $finPaid;

    $quarter = round($total / 4, 2);

    // ── Downpayment ───────────────────────────────────────────────────────────
    // Due = scheduled quarter. Credit = what was actually paid (may be less or more).
    $dpDue    = $quarter;
    $dpCredit = $dpPaid > 0 ? $dpPaid : $dpDue;  // use scheduled if nothing paid yet

    // ── Prelim ────────────────────────────────────────────────────────────────
    // Remaining after DP credit, split among 3 terms
    $rem1   = max(0.0, $total - $dpCredit);
    $prDue  = $rem1 > 0 ? ceil($rem1 / 3 * 100) / 100 : 0.0;
    // Actual credit used = what was actually paid (may be less → leftover carried to next terms)
    $prCredit = $prPaid > 0 ? $prPaid : $prDue;

    // ── Midterm ───────────────────────────────────────────────────────────────
    // Remaining after DP + Prelim credit, split among 2 remaining terms
    $rem2    = max(0.0, $rem1 - $prCredit);
    $midDue  = $rem2 > 0 ? ceil($rem2 / 2 * 100) / 100 : 0.0;
    $midCredit = $midPaid > 0 ? $midPaid : $midDue;

    // ── Finals ────────────────────────────────────────────────────────────────
    // Whatever is left after all prior credits
    $rem3   = max(0.0, $rem2 - $midCredit);
    $finDue = round($rem3, 2);

    return [
        'downpayment' => round($dpDue,  2),
        'prelim'      => round($prDue,  2),
        'midterm'     => round($midDue, 2),
        'finals'      => round($finDue, 2),
        'total'       => round($total,  2),
        'balance'     => round(max(0.0, $total - $allPaid), 2),
    ];
}

function _getScheduleAmounts($conn, $sid) {
    $tf    = $conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id=$sid LIMIT 1");
    $total = $tf ? (float)($tf->fetch_assoc()['total_assessment'] ?? 0) : 0;

    $paidRes = $conn->query("SELECT exam_period, COALESCE(SUM(amount),0) AS paid FROM installment_payments WHERE student_id=$sid GROUP BY exam_period");
    $paid = ['Downpayment'=>0.0,'Prelim'=>0.0,'Midterm'=>0.0,'Finals'=>0.0];
    if ($paidRes) while ($r = $paidRes->fetch_assoc()) $paid[$r['exam_period']] = (float)$r['paid'];

    $dues = _calcInstallmentDues($total, $paid);
    // Also expose per-term paid amounts so accounting modal can show correct remaining balance
    $dues['termPaid'] = [
        'downpayment' => round($paid['Downpayment'], 2),
        'prelim'      => round($paid['Prelim'],      2),
        'midterm'     => round($paid['Midterm'],      2),
        'finals'      => round($paid['Finals'],       2),
    ];
    return $dues;
}
function getPendingPayments($conn) {
    $rows = [];

    // Fetch ALL pending payment_logs — includes both enrollment payments
    // AND installment term payments (Prelim/Midterm/Finals) from enrolled students
    $sql = "
        SELECT pl.id AS log_id, pl.student_id, pl.payment_method, pl.gcash_reference,
               pl.gcash_amount, pl.gcash_date, pl.transaction_id,
               COALESCE(NULLIF(pl.semester,''), s.semester) AS semester,
               pl.notes, pl.exam_period AS log_exam_period, pl.created_at AS submitted_at,
               s.student_number, s.first_name, s.last_name, s.program, s.year_level,
               s.payment_status, s.approval_status, s.enrollment_status,
               s.student_category,
               tf.total_assessment,
               COALESCE(p.department,'') AS department
        FROM payment_logs pl
        JOIN students s ON pl.student_id = s.id
        LEFT JOIN tuition_fees tf ON tf.student_id = s.id
        LEFT JOIN programs p ON (p.name = s.program OR p.code = s.program)
                              AND p.level_type = s.student_category
        WHERE pl.status = 'Pending'
        ORDER BY pl.created_at DESC
    ";

    $result = $conn->query($sql);
    if ($result) {
        while ($r = $result->fetch_assoc()) {
            $isCash  = strtolower($r['payment_method'] ?? '') === 'cash' || ($r['gcash_reference'] ?? '') === 'CASH-PAYMENT';
            $sid     = (int)$r['student_id'];
            $pr      = $conn->query("SELECT COALESCE(SUM(amount),0) AS tp FROM installment_payments WHERE student_id = $sid");
            $total_paid = (float)($pr->fetch_assoc()['tp'] ?? 0);

            // exam_period: dedicated column wins, fall back to notes prefix parsing
            $notesRaw   = $r['notes'] ?? '';
            $examPeriod = trim($r['log_exam_period'] ?? '');
            if (!$examPeriod && preg_match('/^(Prelim|Midterm|Finals|Downpayment|Full)\|?/i', $notesRaw, $m)) {
                $examPeriod = $m[1];
                $notesRaw   = trim(substr($notesRaw, strlen($m[0])));
            }
            // Still empty? Auto-detect from payment plan and what's already been paid
            if (!$examPeriod) {
                $planRow = $conn->query("SELECT payment_plan FROM students WHERE id=$sid LIMIT 1")->fetch_assoc();
                if (($planRow['payment_plan'] ?? 'full') === 'installment') {
                    $paidTerms = $conn->query("SELECT DISTINCT exam_period FROM installment_payments WHERE student_id=$sid AND amount > 0");
                    $paidList  = [];
                    if ($paidTerms) while ($pr2 = $paidTerms->fetch_assoc()) $paidList[] = $pr2['exam_period'];
                    if (!in_array('Downpayment', $paidList))    $examPeriod = 'Downpayment';
                    elseif (!in_array('Prelim', $paidList))     $examPeriod = 'Prelim';
                    elseif (!in_array('Midterm', $paidList))    $examPeriod = 'Midterm';
                    else                                         $examPeriod = 'Finals';
                    // Save it back so verifyPayment can use it
                    $conn->query("UPDATE payment_logs SET exam_period='$examPeriod' WHERE id=" . (int)$r['log_id']);
                } else {
                    $examPeriod = 'Full';
                    $conn->query("UPDATE payment_logs SET exam_period='Full' WHERE id=" . (int)$r['log_id']);
                }
            }

            $rows[] = [
                'logId'          => (int)$r['log_id'],
                'studentId'      => $sid,
                'studentNumber'  => $r['student_number'],
                'firstName'      => $r['first_name'],
                'lastName'       => $r['last_name'],
                'program'        => $r['program'],
                'yearLevel'      => $r['year_level'],
                'department'     => $r['department'] ?? '',
                'studentCategory'=> $r['student_category'] ?? '',
                'enrollmentStatus'=> $r['enrollment_status'],
                'paymentMethod'  => $isCash ? 'Cash' : 'GCash',
                'gcashReference' => $isCash ? '' : ($r['gcash_reference'] ?? ''),
                'gcashAmount'    => $isCash ? 0 : (float)($r['gcash_amount'] ?? 0),
                'gcashDate'      => $isCash ? '' : ($r['gcash_date'] ?? ''),
                'transactionId'  => $isCash ? '' : ($r['transaction_id'] ?? ''),
                'semester'       => $r['semester'] ?? '',
                'examPeriod'     => $examPeriod,   // which term this payment is for
                'notes'          => $notesRaw,
                'status'         => 'Pending',
                'submittedAt'    => $r['submitted_at'],
                'paymentStatus'  => $r['payment_status'],
                'approvalStatus' => $r['approval_status'],
                'totalAssessment'=> (float)($r['total_assessment'] ?? 0),
                'totalPaid'      => $total_paid,
                'balance'        => max(0, (float)($r['total_assessment'] ?? 0) - $total_paid),
                'paymentPlan'    => _getStudentPaymentPlan($conn, $sid),
                'scheduleAmounts'=> (function() use ($conn, $sid) {
                    $sa = _getScheduleAmounts($conn, $sid);
                    return ['downpayment'=>$sa['downpayment'],'prelim'=>$sa['prelim'],'midterm'=>$sa['midterm'],'finals'=>$sa['finals'],'total'=>$sa['total']];
                })(),
                'termPaidAmounts'=> (function() use ($conn, $sid) {
                    $sa = _getScheduleAmounts($conn, $sid);
                    return $sa['termPaid'] ?? ['downpayment'=>0,'prelim'=>0,'midterm'=>0,'finals'=>0];
                })(),
            ];
        }
    }

    // Also show students with no payment_log (Cash pending enrollment, not yet enrolled)
    $noLogSql = "
        SELECT s.id AS student_id, s.student_number, s.first_name, s.last_name,
               s.program, s.year_level, s.payment_status, s.approval_status,
               s.payment_method, s.semester, s.created_at AS submitted_at,
               s.student_category,
               tf.total_assessment,
               COALESCE(p.department,'') AS department
        FROM students s
        LEFT JOIN payment_logs pl ON pl.student_id = s.id AND pl.status = 'Pending'
        LEFT JOIN tuition_fees tf ON tf.student_id = s.id
        LEFT JOIN programs p ON (p.name = s.program OR p.code = s.program)
                              AND p.level_type = s.student_category
        WHERE s.enrollment_status != 'Enrolled'
          AND s.payment_status = 'Pending'
          AND pl.id IS NULL
    ";
    $noLogResult  = $conn->query($noLogSql);
    $alreadyAdded = array_column($rows, 'studentId');
    if ($noLogResult) {
        while ($r = $noLogResult->fetch_assoc()) {
            $sid = (int)$r['student_id'];
            if (in_array($sid, $alreadyAdded)) continue;

            $rawSem   = trim($r['semester'] ?? '');
            $semester = $rawSem ?: '1st Semester, AY ' . date('Y') . '-' . (date('Y')+1);

            // Auto-detect exam_period for this Cash student
            $noLogPlan = $r['payment_plan'] ?? _getStudentPaymentPlan($conn, $sid);
            $noLogExamPeriod = 'Full';
            if ($noLogPlan === 'installment') {
                $paidTerms2 = $conn->query("SELECT DISTINCT exam_period FROM installment_payments WHERE student_id=$sid AND amount > 0");
                $paidList2  = [];
                if ($paidTerms2) while ($pt2 = $paidTerms2->fetch_assoc()) $paidList2[] = $pt2['exam_period'];
                if (!in_array('Downpayment', $paidList2))    $noLogExamPeriod = 'Downpayment';
                elseif (!in_array('Prelim', $paidList2))     $noLogExamPeriod = 'Prelim';
                elseif (!in_array('Midterm', $paidList2))    $noLogExamPeriod = 'Midterm';
                else                                          $noLogExamPeriod = 'Finals';
            }

            $ins = $conn->prepare("INSERT INTO payment_logs (student_id, payment_method, gcash_reference, gcash_amount, semester, exam_period, status) VALUES (?, 'Cash', '', 0, ?, ?, 'Pending')");
            $ins->bind_param("iss", $sid, $semester, $noLogExamPeriod);
            $ins->execute();
            $logId = $ins->insert_id;

            $pr = $conn->query("SELECT COALESCE(SUM(amount),0) AS tp FROM installment_payments WHERE student_id = $sid");
            $total_paid = (float)($pr->fetch_assoc()['tp'] ?? 0);

            $rows[] = [
                'logId'          => $logId,
                'studentId'      => $sid,
                'studentNumber'  => $r['student_number'],
                'firstName'      => $r['first_name'],
                'lastName'       => $r['last_name'],
                'program'        => $r['program'],
                'yearLevel'      => $r['year_level'],
                'department'     => $r['department'] ?? '',
                'studentCategory'=> $r['student_category'] ?? '',
                'enrollmentStatus'=> 'Pending',
                'paymentMethod'  => 'Cash',
                'gcashReference' => '', 'gcashAmount' => 0, 'gcashDate' => '', 'transactionId' => '',
                'semester'       => $r['semester'] ?: $semester,
                'examPeriod'     => $noLogExamPeriod,
                'notes'          => '',
                'status'         => 'Pending',
                'submittedAt'    => $r['submitted_at'],
                'paymentStatus'  => $r['payment_status'],
                'approvalStatus' => $r['approval_status'],
                'totalAssessment'=> (float)($r['total_assessment'] ?? 0),
                'totalPaid'      => $total_paid,
                'balance'        => max(0, (float)($r['total_assessment'] ?? 0) - $total_paid),
                'paymentPlan'    => _getStudentPaymentPlan($conn, $sid),
                'scheduleAmounts'=> (function() use ($conn, $sid) {
                    $sa = _getScheduleAmounts($conn, $sid);
                    return ['downpayment'=>$sa['downpayment'],'prelim'=>$sa['prelim'],'midterm'=>$sa['midterm'],'finals'=>$sa['finals'],'total'=>$sa['total']];
                })(),
                'termPaidAmounts'=> (function() use ($conn, $sid) {
                    $sa = _getScheduleAmounts($conn, $sid);
                    return $sa['termPaid'] ?? ['downpayment'=>0,'prelim'=>0,'midterm'=>0,'finals'=>0];
                })(),
            ];
        }
    }

    echo json_encode(['success' => true, 'payments' => $rows]);
}

// ─────────────────────────────────────────────────────────────
// ACCOUNTING: Payment history
// ─────────────────────────────────────────────────────────────
// GET ?action=get_student_payment_history&student_id=X
// Returns verified payments for a single student (student view)
function getStudentPaymentHistory($conn) {
    $student_id = (int)($_GET['student_id'] ?? 0);
    if (!$student_id) { echo json_encode(['success'=>false,'message'=>'student_id required']); return; }

    // Support user_id fallback
    $stRes = $conn->query("SELECT id FROM students WHERE id=$student_id LIMIT 1");
    if (!$stRes || $stRes->num_rows === 0) {
        $stRes2 = $conn->query("SELECT id FROM students WHERE user_id=$student_id LIMIT 1");
        $row2   = $stRes2 ? $stRes2->fetch_assoc() : null;
        if ($row2) $student_id = (int)$row2['id'];
        else { echo json_encode(['success'=>true,'history'=>[]]); return; }
    }

    $result = $conn->query("
        SELECT ip.id, ip.or_ar_number, ip.or_ar_type, ip.amount, ip.payment_date,
               ip.payment_method, ip.gcash_reference, ip.exam_period, ip.notes,
               ip.created_at,
               u.first_name AS verified_by_fname, u.last_name AS verified_by_lname,
               tf.total_assessment
        FROM installment_payments ip
        LEFT JOIN users u ON ip.recorded_by = u.id
        LEFT JOIN tuition_fees tf ON tf.student_id = ip.student_id
        WHERE ip.student_id = $student_id
        ORDER BY ip.payment_date DESC, ip.created_at DESC
    ");

    $rows = [];
    if ($result) {
        while ($r = $result->fetch_assoc()) {
            $rows[] = [
                'id'           => (int)$r['id'],
                'orArNumber'   => $r['or_ar_number'] ?? '',
                'orArType'     => $r['or_ar_type']   ?? 'AR',
                'amount'       => (float)$r['amount'],
                'paymentDate'  => $r['payment_date']  ?? '',
                'paymentMethod'=> $r['payment_method'] ?? '',
                'gcashRef'     => $r['gcash_reference'] ?? '',
                'examPeriod'   => $r['exam_period']  ?? '',
                'notes'        => $r['notes']         ?? '',
                'createdAt'    => $r['created_at']    ?? '',
                'verifiedBy'   => trim(($r['verified_by_fname'] ?? '') . ' ' . ($r['verified_by_lname'] ?? '')) ?: 'Accounting',
                'totalAssessment' => (float)($r['total_assessment'] ?? 0),
            ];
        }
    }

    // Compute running totals
    $totalPaid = array_sum(array_column($rows, 'amount'));
    $totalAssessment = $rows[0]['totalAssessment'] ?? 0;

    // Student info for SOA header
    $sRow = $conn->query("SELECT first_name, last_name, student_number, program, year_level, semester FROM students WHERE id=$student_id LIMIT 1")->fetch_assoc();

    echo json_encode([
        'success'         => true,
        'history'         => $rows,
        'totalPaid'       => $totalPaid,
        'totalAssessment' => $totalAssessment,
        'balance'         => max(0, $totalAssessment - $totalPaid),
        'student'         => $sRow,
    ]);
}

function getPaymentHistory($conn) {
    $result = $conn->query("
        SELECT pl.id AS log_id, pl.student_id, pl.payment_method, pl.gcash_reference,
               pl.gcash_amount, pl.gcash_date, pl.transaction_id, pl.semester, pl.status,
               pl.notes, pl.verified_at, pl.created_at AS submitted_at,
               s.student_number, s.first_name, s.last_name, s.program, s.year_level,
               s.student_category,
               u.first_name AS verified_by_fname, u.last_name AS verified_by_lname, tf.total_assessment,
               ip.or_ar_number, ip.or_ar_type, ip.exam_period,
               ip.amount AS ip_amount, ip.payment_date AS ip_payment_date,
               COALESCE(p.department,'') AS department
        FROM payment_logs pl
        JOIN students s ON pl.student_id = s.id
        LEFT JOIN users u ON pl.verified_by = u.id
        LEFT JOIN tuition_fees tf ON tf.student_id = s.id
        LEFT JOIN installment_payments ip ON ip.payment_log_id = pl.id
        LEFT JOIN programs p ON (p.name = s.program OR p.code = s.program)
                              AND p.level_type = s.student_category
        WHERE pl.status IN ('Verified','Rejected')
        ORDER BY pl.verified_at DESC
    ");

    $rows = [];
    if ($result) {
        while ($r = $result->fetch_assoc()) {
            $isCash = strtolower($r['payment_method'] ?? '') === 'cash' || ($r['gcash_reference'] ?? '') === 'CASH-PAYMENT';
            $sid    = (int)$r['student_id'];
            $pr     = $conn->query("SELECT COALESCE(SUM(amount),0) AS tp FROM installment_payments WHERE student_id = $sid");
            $total_paid = (float)($pr->fetch_assoc()['tp'] ?? 0);

            $rows[] = [
                'logId'          => (int)$r['log_id'],
                'studentId'      => $sid,
                'studentNumber'  => $r['student_number'],
                'firstName'      => $r['first_name'],
                'lastName'       => $r['last_name'],
                'program'        => $r['program'],
                'yearLevel'      => $r['year_level'],
                'department'     => $r['department'] ?? '',
                'studentCategory'=> $r['student_category'] ?? '',
                'paymentMethod'  => $isCash ? 'Cash' : 'GCash',
                'gcashReference' => $isCash ? '' : ($r['gcash_reference'] ?? ''),
                'gcashAmount'    => isset($r['ip_amount']) && $r['ip_amount'] !== null
                                        ? (float)$r['ip_amount']
                                        : (float)($r['gcash_amount'] ?? 0),
                'gcashDate'      => isset($r['ip_payment_date']) && $r['ip_payment_date']
                                        ? $r['ip_payment_date']
                                        : ($isCash ? ($r['verified_at'] ? date('Y-m-d', strtotime($r['verified_at'])) : '') : ($r['gcash_date'] ?? '')),
                'transactionId'  => $isCash ? '' : ($r['transaction_id'] ?? ''),
                'semester'       => $r['semester'],
                'status'         => $r['status'],
                'notes'          => $r['notes'],
                'verifiedAt'     => $r['verified_at'],
                'verifiedByName' => trim(($r['verified_by_fname'] ?? '') . ' ' . ($r['verified_by_lname'] ?? '')) ?: 'Accounting',
                'submittedAt'    => $r['submitted_at'],
                'totalAssessment'=> (float)($r['total_assessment'] ?? 0),
                'totalPaid'      => $total_paid,
                'balance'        => max(0, (float)($r['total_assessment'] ?? 0) - $total_paid),
                'orArNumber'     => $r['or_ar_number'] ?? '',
                'orArType'       => $r['or_ar_type']   ?? '',
                'examPeriod'     => $r['exam_period']   ?? '',
            ];
        }
    }
    echo json_encode(['success' => true, 'history' => $rows]);
}

// ─────────────────────────────────────────────────────────────
// ACCOUNTING: Verify payment
// ─────────────────────────────────────────────────────────────
function verifyPayment($conn, $data) {
    $log_id         = (int)($data['log_id']             ?? 0);
    $student_id     = (int)($data['student_id']         ?? 0);
    $acc_user_id    = (int)($data['accounting_user_id'] ?? 0);
    $notes          = trim($data['notes']               ?? '');
    $payment_method = strtolower(trim($data['payment_method'] ?? ''));

    // For cash: accounting enters amount + date; for GCash: read from payment_log
    $cash_amount = isset($data['cash_amount']) ? (float)$data['cash_amount'] : null;
    $cash_date   = isset($data['cash_date'])   ? trim($data['cash_date'])    : date('Y-m-d');

    if (!$log_id || !$student_id) {
        echo json_encode(['success' => false, 'message' => 'log_id and student_id required']); return;
    }

    // Validate cash_amount: must be positive
    if ($cash_amount !== null && $cash_amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Cash amount must be greater than zero.']);
        return;
    }

    // Read original notes + amount BEFORE updating (notes will be overwritten by accounting notes)
    $logRow = $conn->query("SELECT gcash_amount, gcash_date, payment_method, notes, status, exam_period FROM payment_logs WHERE id = $log_id LIMIT 1")->fetch_assoc();
    if (!$logRow) {
        echo json_encode(['success' => false, 'message' => 'Payment log not found']); return;
    }
    if ($logRow['status'] !== 'Pending') {
        echo json_encode(['success' => false, 'message' => 'Payment already processed']); return;
    }
    $originalNotes = $logRow['notes']; // preserve exam_period prefix before overwriting

    // Update payment log (notes field = accounting remarks, may overwrite original notes)
    if ($payment_method === 'cash' && $cash_amount !== null) {
        $stmt = $conn->prepare("UPDATE payment_logs SET status = 'Verified', verified_by = ?, verified_at = NOW(), notes = ?, gcash_amount = ?, gcash_date = ? WHERE id = ?");
        $stmt->bind_param("isdsi", $acc_user_id, $notes, $cash_amount, $cash_date, $log_id);
    } else {
        $stmt = $conn->prepare("UPDATE payment_logs SET status = 'Verified', verified_by = ?, verified_at = NOW(), notes = ? WHERE id = ?");
        $stmt->bind_param("isi", $acc_user_id, $notes, $log_id);
    }
    $stmt->execute();
    // Note: do NOT block on affected_rows === 0 — MySQL returns 0 when the row values
    // didn't change (e.g. notes was already blank). The status check above already
    // guarantees the log was Pending before this call.

    // Use pre-UPDATE logRow (has original notes with exam_period prefix)
    $final_amount = ($payment_method === 'cash') ? ($cash_amount ?? 0) : (float)($logRow['gcash_amount'] ?? 0);
    $final_date   = ($payment_method === 'cash') ? $cash_date : ($logRow['gcash_date'] ?? date('Y-m-d'));
    $pm_label     = ($payment_method === 'cash') ? 'Cash' : 'GCash';

    // Get student payment plan
    $stRow       = $conn->query("SELECT payment_plan, enrollment_status FROM students WHERE id = $student_id LIMIT 1")->fetch_assoc();
    $paymentPlan = $stRow['payment_plan'] ?? 'full';
    $isEnrolled  = ($stRow['enrollment_status'] ?? '') === 'Enrolled';

    // Parse exam_period — first check dedicated column, then fall back to ORIGINAL notes prefix
    $notesRaw   = trim($originalNotes ?? $logRow['notes'] ?? '');
    $examPeriod = trim($logRow['exam_period'] ?? ''); // dedicated column wins
    if (!$examPeriod && preg_match('/^(Prelim|Midterm|Finals|Downpayment|Full)\|?/i', $notesRaw, $m)) {
        $examPeriod = $m[1];
        $notes      = $notes ?: trim(substr($notesRaw, strlen($m[0])));
    }

    // Auto-create installment_payments record if not already done
    // Avoid duplicates via payment_log_id check
    $dupCheck = $conn->prepare("SELECT id FROM installment_payments WHERE payment_log_id = ? LIMIT 1");
    $dupCheck->bind_param("i", $log_id);
    $dupCheck->execute();
    $dupResult = $dupCheck->get_result();

    // Determine OR/AR type and exam period label (needed even if dup, for response message)
    $year = (int)date('Y');
    if ($isEnrolled && $examPeriod && in_array($examPeriod, ['Prelim','Midterm','Finals'])) {
        $or_ar_type = 'AR';
    } elseif ($paymentPlan === 'installment') {
        $or_ar_type = 'AR';
        $examPeriod = $examPeriod ?: 'Downpayment';
    } else {
        $or_ar_type = 'OR';
        $examPeriod = $examPeriod ?: 'Full';
    }

    if ($dupResult->num_rows === 0) {
        // FIX AC-02: Atomic sequence for OR/AR number
        $conn->query("INSERT INTO or_ar_sequences (year, last_seq) VALUES ($year, 1) ON DUPLICATE KEY UPDATE last_seq = last_seq + 1");
        $seqRow2 = $conn->query("SELECT last_seq FROM or_ar_sequences WHERE year = $year")->fetch_assoc();
        $seq2    = (int)($seqRow2['last_seq'] ?? 1);
        $or_no   = $or_ar_type . '-' . $year . str_pad($seq2, 4, '0', STR_PAD_LEFT);

        if ($payment_method !== 'cash') {
            $grStmt = $conn->prepare("SELECT gcash_reference FROM payment_logs WHERE id = ? LIMIT 1");
            $grStmt->bind_param("i", $log_id);
            $grStmt->execute();
            $gcash_ref = $grStmt->get_result()->fetch_assoc()['gcash_reference'] ?? '';
        } else {
            $gcash_ref = '';
        }

        $ins = $conn->prepare("
            INSERT INTO installment_payments
                (student_id, payment_log_id, or_ar_number, or_ar_type, amount, payment_date, payment_method, gcash_reference, exam_period, notes, recorded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->bind_param("iissdsssssi", $student_id, $log_id, $or_no, $or_ar_type, $final_amount, $final_date, $pm_label, $gcash_ref, $examPeriod, $notes, $acc_user_id);
        $ins->execute();
    } else {
        // Already inserted — retrieve existing OR/AR number for response
        $exRow = $conn->query("SELECT or_ar_number FROM installment_payments WHERE payment_log_id = $log_id LIMIT 1")->fetch_assoc();
        $or_no = $exRow['or_ar_number'] ?? '';
    }

    // ── Always run post-payment updates (schedule + enrollment status) ──────
    // Sync payment_schedules for Prelim/Midterm/Finals
    if ($isEnrolled && in_array($examPeriod, ['Prelim','Midterm','Finals'])) {
        $ep        = strtolower($examPeriod);
        $schedRes  = $conn->query("SELECT {$ep}_due FROM payment_schedules WHERE student_id=$student_id LIMIT 1");
        $schedRow  = $schedRes ? $schedRes->fetch_assoc() : null;
        $periodDue = $schedRow ? (float)$schedRow[$ep.'_due'] : 0;
        $paidRes   = $conn->query("SELECT COALESCE(SUM(amount),0) AS paid FROM installment_payments WHERE student_id=$student_id AND exam_period='$examPeriod'");
        $periodPaid = (float)$paidRes->fetch_assoc()['paid'];
        $newStatus  = $periodPaid <= 0 ? 'unpaid' : ($periodPaid >= $periodDue ? 'paid' : 'partial');
        $conn->query("UPDATE payment_schedules SET {$ep}_paid=$periodPaid, {$ep}_status='$newStatus' WHERE student_id=$student_id");

        recomputeSchedule($conn, $student_id);

        // Check if fully paid now — update student payment_status accordingly
        $tfR2      = $conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id=$student_id LIMIT 1")->fetch_assoc();
        $totalAmt  = $tfR2 ? (float)$tfR2['total_assessment'] : 0;
        $allPaidR  = $conn->query("SELECT COALESCE(SUM(amount),0) AS paid FROM installment_payments WHERE student_id=$student_id")->fetch_assoc();
        $allPaid   = (float)$allPaidR['paid'];
        $newPayStatus = ($totalAmt > 0 && $allPaid >= $totalAmt) ? 'Paid' : 'Partial';
        $conn->query("UPDATE students SET payment_status='$newPayStatus' WHERE id=$student_id");

        logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'VERIFY_PAYMENT', 'student', $student_id,
            "Verified $examPeriod payment ₱" . number_format($final_amount, 2) . " for student ID $student_id (OR: $or_no)");
        echo json_encode(['success' => true, 'message' => "$examPeriod payment verified. ₱" . number_format($final_amount, 2) . " recorded."]);
        return;
    }

    // Downpayment for installment plan: enroll and recompute remaining term dues
    if ($paymentPlan === 'installment' && $examPeriod === 'Downpayment') {
        recomputeSchedule($conn, $student_id);

        $upd = $conn->prepare("UPDATE students SET payment_status='Partial', approval_status='Approved', enrollment_status='Enrolled', accounting_approved_by=?, accounting_approved_at=NOW(), accounting_notes=? WHERE id=?");
        $upd->bind_param("isi", $acc_user_id, $notes, $student_id);
        $upd->execute();

        logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'VERIFY_PAYMENT', 'student', $student_id,
            "Downpayment verified ₱" . number_format($final_amount, 2) . ", installment enrollment approved for student ID $student_id (AR: $or_no)");
        echo json_encode(['success' => true, 'message' => "Downpayment verified. ₱" . number_format($final_amount, 2) . " recorded. Student enrolled — remaining balance split across installment terms."]);
        return;
    }

    $upd = $conn->prepare("UPDATE students SET payment_status='Paid', approval_status='Approved', enrollment_status='Enrolled', accounting_approved_by=?, accounting_approved_at=NOW(), accounting_notes=? WHERE id=?");
    $upd->bind_param("isi", $acc_user_id, $notes, $student_id);
    $upd->execute();

    // For full-plan: mark all schedule periods as paid immediately
    $tfRow = $conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id=$student_id LIMIT 1")->fetch_assoc();
    if ($tfRow) {
        $conn->query("UPDATE payment_schedules
            SET prelim_paid=prelim_due, midterm_paid=midterm_due, finals_paid=finals_due,
                prelim_status='paid', midterm_status='paid', finals_status='paid'
            WHERE student_id=$student_id");
    }

    logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'VERIFY_PAYMENT', 'student', $student_id,
        "Full payment verified, enrollment approved for student ID $student_id");
    echo json_encode(['success' => true, 'message' => 'Payment verified. Student enrollment approved.']);
}

// ─────────────────────────────────────────────────────────────
// ACCOUNTING: Reject payment
// ─────────────────────────────────────────────────────────────
function rejectPayment($conn, $data) {
    $log_id      = (int)($data['log_id']             ?? 0);
    $student_id  = (int)($data['student_id']         ?? 0);
    $acc_user_id = (int)($data['accounting_user_id'] ?? 0);
    $notes       = trim($data['notes'] ?? '');

    if (!$log_id || !$student_id) { echo json_encode(['success' => false, 'message' => 'log_id and student_id required']); return; }

    $stmt = $conn->prepare("UPDATE payment_logs SET status = 'Rejected', verified_by = ?, verified_at = NOW(), notes = ? WHERE id = ? AND status = 'Pending'");
    $stmt->bind_param("isi", $acc_user_id, $notes, $log_id);
    $stmt->execute();

    $upd = $conn->prepare("UPDATE students SET payment_status = 'Pending', approval_status = 'Pending' WHERE id = ?");
    $upd->bind_param("i", $student_id);
    $upd->execute();

    logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'REJECT_PAYMENT', 'student', $student_id,
        "Payment rejected for student ID $student_id. Log: $log_id. Reason: $notes");
    echo json_encode(['success' => true, 'message' => 'Payment rejected.']);
}

// ═══════════════════════════════════════════════════════════════════════════════
// SHARED HELPER: Recompute and save payment_schedules for one installment student
// Call this after any payment is recorded or on page load.
//
// Algorithm:
//   remaining = total - sum(ALL installment_payments for this student)
//   For each period in [Prelim, Midterm, Finals]:
//     - If locked: due unchanged, paid=0
//     - If paid (actual_paid >= due):
//         actual_paid = SUM from installment_payments
//         due = original due (unchanged once paid)
//         status = 'paid'
//     - If unpaid/partial:
//         Redistribute remaining balance equally across these unpaid terms
//         due (updated) = ceil(remaining_for_unpaid_terms / count_unpaid_terms)
//         paid = actual from installment_payments
//         status = 'unpaid'|'partial'
//
// This means: if student overpays Prelim, the extra reduces remaining balance,
// which shrinks Midterm+Finals dues proportionally.
// ═══════════════════════════════════════════════════════════════════════════════
function recomputeSchedule(mysqli $conn, int $student_id): void {
    // ── Get total assessment ──────────────────────────────────────────────────
    $tfR   = $conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id=$student_id LIMIT 1");
    $total = $tfR ? (float)($tfR->fetch_assoc()['total_assessment'] ?? 0) : 0;
    if ($total <= 0) return;

    // ── Get current schedule row ──────────────────────────────────────────────
    $schRes = $conn->query("SELECT * FROM payment_schedules WHERE student_id=$student_id LIMIT 1");
    $sch    = $schRes ? $schRes->fetch_assoc() : null;
    if (!$sch || ($sch['payment_type'] ?? '') !== 'installment') return;

    // ── Actual paid per period from installment_payments ─────────────────────
    $paidRes = $conn->query("
        SELECT exam_period, COALESCE(SUM(amount),0) AS paid
        FROM installment_payments WHERE student_id=$student_id GROUP BY exam_period
    ");
    $paid = ['Downpayment'=>0.0, 'Prelim'=>0.0, 'Midterm'=>0.0, 'Finals'=>0.0];
    if ($paidRes) while ($r = $paidRes->fetch_assoc()) $paid[$r['exam_period']] = (float)$r['paid'];

    // ── Use shared helper to compute correct dues ────────────────────────────
    $dues    = _calcInstallmentDues($total, $paid);
    $newDue  = ['Prelim' => $dues['prelim'], 'Midterm' => $dues['midterm'], 'Finals' => $dues['finals']];

    // ── Build UPDATE ──────────────────────────────────────────────────────────
    $periods = ['Prelim', 'Midterm', 'Finals'];
    $updates = [];
    foreach ($periods as $p) {
        $col        = strtolower($p);
        $curStatus  = $sch[$col.'_status'] ?? 'locked';
        $newD       = round($newDue[$p], 2);
        $actualPaid = round($paid[$p], 2);

        // Determine status from actual paid vs recomputed due
        $totalPaid  = array_sum($paid);
        $fullyPaid  = ($totalPaid >= $total && $total > 0);

        if ($newD <= 0 && $fullyPaid) {
            // Zero due because everything was covered by prior payments
            $st = 'paid';
        } elseif ($actualPaid >= $newD && $newD > 0) {
            // Paid at least the recomputed due for this term
            $st = 'paid';
        } elseif ($actualPaid > 0) {
            // Some payment recorded but less than due = partial
            $st = 'partial';
        } elseif ($curStatus === 'locked') {
            // Not yet unlocked by accounting
            $st = 'locked';
        } else {
            $st = 'unpaid';
        }

        $updates[] = "{$col}_due=$newD, {$col}_paid=$actualPaid, {$col}_status='$st'";
    }

    $conn->query("UPDATE payment_schedules SET total_assessment=$total, "
        . implode(', ', $updates) . " WHERE student_id=$student_id");
}


function getPaymentSchedule($conn) {
    $student_id = (int)($_GET['student_id'] ?? 0);
    if (!$student_id) { echo json_encode(['success'=>false,'message'=>'student_id required']); return; }

    // Support lookup by user_id as fallback
    $stRes = $conn->query("SELECT id, payment_method, payment_plan, semester FROM students WHERE id=$student_id LIMIT 1");
    $stRow = $stRes ? $stRes->fetch_assoc() : null;
    if (!$stRow) {
        // Try by user_id
        $stRes2 = $conn->query("SELECT id, payment_method, payment_plan, semester FROM students WHERE user_id=$student_id LIMIT 1");
        $stRow  = $stRes2 ? $stRes2->fetch_assoc() : null;
        if ($stRow) $student_id = (int)$stRow['id'];
    }
    if (!$stRow) { echo json_encode(['success'=>true,'schedule'=>null,'notices'=>[]]); return; }

    $ptype = (strtolower($stRow['payment_plan'] ?? '') === 'full') ? 'full' : 'installment';

    $tfRes = $conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id=$student_id LIMIT 1");
    $tfRow = $tfRes ? $tfRes->fetch_assoc() : null;
    $total = $tfRow ? (float)$tfRow['total_assessment'] : 0;

    // ── Recompute term dues based on actual DP paid ──────────────────────────
    // Rule: DP can be any amount the student can afford.
    //   - scheduled DP = total / 4
    //   - remaining after DP = total - dpPaid  (not total - scheduledDP)
    //   - remaining is split equally across Prelim/Midterm/Finals
    // This means:
    //   - small DP  → larger term dues
    //   - large DP  → smaller term dues (or zero if DP covers everything)
    $dpPaidRes  = $conn->query("SELECT COALESCE(SUM(amount),0) AS paid FROM installment_payments WHERE student_id=$student_id AND exam_period='Downpayment'");
    $dpPaid     = $dpPaidRes ? (float)$dpPaidRes->fetch_assoc()['paid'] : 0;
    // Use actual DP paid if any, else fall back to scheduled quarter
    $dpCredit   = $dpPaid > 0 ? $dpPaid : ($total > 0 ? round($total / 4, 2) : 0);
    $remaining  = max(0, $total - $dpCredit);
    $pd         = $remaining > 0 ? (ceil($remaining / 3 * 100) / 100) : 0;
    $md         = $pd;
    $fd         = $remaining > 0 ? round(max(0, $remaining - $pd * 2), 2) : 0;

    // Always upsert — dues must reflect the actual DP paid (recomputed every load)
    $conn->query("INSERT INTO payment_schedules
        (student_id,payment_type,total_assessment,prelim_due,midterm_due,finals_due)
        VALUES ($student_id,'$ptype',$total,$pd,$md,$fd)
        ON DUPLICATE KEY UPDATE
          payment_type     = '$ptype',
          total_assessment = IF($total>0, $total, total_assessment),
          prelim_due       = IF($total>0, $pd, prelim_due),
          midterm_due      = IF($total>0, $md, midterm_due),
          finals_due       = IF($total>0, $fd, finals_due)");

    $res = $conn->query("SELECT * FROM payment_schedules WHERE student_id=$student_id LIMIT 1");
    $schedule = $res ? $res->fetch_assoc() : null;
    if (!$schedule) { echo json_encode(['success'=>true,'schedule'=>null]); return; }

    // ── Compute actual paid amounts directly from installment_payments ──────
    // Sum every verified payment per period — no caps, no hacks, no manual DB edits needed.
    $allPaidRes = $conn->query("
        SELECT exam_period, COALESCE(SUM(amount),0) AS paid
        FROM installment_payments
        WHERE student_id=$student_id
        GROUP BY exam_period
    ");
    $paidByPeriod = [];
    if ($allPaidRes) {
        while ($row = $allPaidRes->fetch_assoc()) {
            $paidByPeriod[$row['exam_period']] = (float)$row['paid'];
        }
    }

    // Total of ALL payments (Downpayment + Full + Prelim + Midterm + Finals)
    $totalPaidAll = array_sum($paidByPeriod);

    if ($schedule['payment_type'] === 'full') {
        // Full-payment: student pays everything at once (exam_period = 'Full' or 'Downpayment')
        // Show all periods as paid if total covers the assessment
        $isFullyPaid = $totalPaidAll >= (float)$schedule['total_assessment'];
        if (!$isFullyPaid) {
            // Check students.payment_status as fallback
            $stPayRow = $conn->query("SELECT payment_status FROM students WHERE id=$student_id LIMIT 1")->fetch_assoc();
            $isFullyPaid = ($stPayRow && $stPayRow['payment_status'] === 'Paid');
        }
        if ($isFullyPaid) {
            $schedule['prelim_paid']    = (float)$schedule['prelim_due'];
            $schedule['midterm_paid']   = (float)$schedule['midterm_due'];
            $schedule['finals_paid']    = (float)$schedule['finals_due'];
            $schedule['prelim_status']  = 'paid';
            $schedule['midterm_status'] = 'paid';
            $schedule['finals_status']  = 'paid';
            $conn->query("UPDATE payment_schedules
                SET prelim_paid=prelim_due, midterm_paid=midterm_due, finals_paid=finals_due,
                    prelim_status='paid', midterm_status='paid', finals_status='paid'
                WHERE student_id=$student_id");
        }
        // downpayment_paid = remaining after prelim+midterm+finals (the 4th quarter)
        $sumPeriods = (float)$schedule['prelim_paid'] + (float)$schedule['midterm_paid'] + (float)$schedule['finals_paid'];
        $schedule['downpayment_paid'] = max(0, min($totalPaidAll, (float)$schedule['total_assessment']) - $sumPeriods);
    } else {
        // Installment: use recomputeSchedule() to dynamically redistribute dues and sync paid amounts.
        recomputeSchedule($conn, $student_id);
        // Re-fetch updated schedule after recompute
        $refetchRes = $conn->query("SELECT * FROM payment_schedules WHERE student_id=$student_id LIMIT 1");
        if ($refetchRes) {
            $schedule = $refetchRes->fetch_assoc();
        }
        $schedule['downpayment_paid'] = $dpPaid;
    }

    $noticeRes = $conn->query("SELECT exam_period, amount_due, due_date, message, sent_at, is_read
        FROM payment_notices WHERE student_id=$student_id");
    $notices = [];
    while ($row = $noticeRes->fetch_assoc()) $notices[$row['exam_period']] = $row;

    // Cast all numeric fields — fetch_assoc returns DECIMAL as strings.
    // Without this, JS arithmetic becomes string concatenation (e.g. 5000+"0.00" = "50000.00").
    foreach (['total_assessment','prelim_due','midterm_due','finals_due',
              'prelim_paid','midterm_paid','finals_paid','downpayment_paid'] as $f) {
        if (isset($schedule[$f])) $schedule[$f] = (float)$schedule[$f];
    }
    // total_paid = authoritative sum from installment_payments (never sum of period fields)
    $schedule['total_paid'] = round($totalPaidAll, 2);

    echo json_encode(['success'=>true,'schedule'=>$schedule,'notices'=>$notices]);
}

function getPaymentNotices($conn) {
    $student_id = (int)($_GET['student_id'] ?? 0);
    if (!$student_id) { echo json_encode(['success'=>false]); return; }
    $res = $conn->query("SELECT * FROM payment_notices WHERE student_id=$student_id ORDER BY sent_at DESC");
    $notices = [];
    while ($row = $res->fetch_assoc()) $notices[] = $row;
    $conn->query("UPDATE payment_notices SET is_read=1 WHERE student_id=$student_id");
    echo json_encode(['success'=>true,'notices'=>$notices]);
}

function sendPaymentNotice($conn, $data) {
    $student_id  = (int)($data['student_id']  ?? 0);
    $exam_period = $conn->real_escape_string($data['exam_period'] ?? '');
    $amount_due  = (float)($data['amount_due'] ?? 0);
    $due_date    = $conn->real_escape_string($data['due_date']   ?? '');
    $message     = $conn->real_escape_string($data['message']    ?? '');
    $sent_by     = (int)($data['accounting_user_id'] ?? 0);

    if (!$student_id || !in_array($exam_period, ['Prelim','Midterm','Finals'])) {
        echo json_encode(['success'=>false,'message'=>'Invalid data']); return;
    }

    $p = strtolower($exam_period);
    $due_date_val = $due_date ? "'$due_date'" : 'NULL';

    // Save/update the payment notice AND unlock the period so student can pay
    $conn->query("INSERT INTO payment_notices (student_id,exam_period,amount_due,due_date,message,sent_by)
        VALUES ($student_id,'$exam_period',$amount_due,$due_date_val,'$message',$sent_by)
        ON DUPLICATE KEY UPDATE amount_due=$amount_due,due_date=$due_date_val,
        message='$message',sent_by=$sent_by,sent_at=NOW(),is_read=0");

    // Unlock the period so student can now pay
    $unlocked_col = $p.'_unlocked_at';
    $conn->query("UPDATE payment_schedules
        SET {$p}_status=IF({$p}_status='locked','unpaid',{$p}_status),
            $unlocked_col=IF($unlocked_col IS NULL,NOW(),$unlocked_col)
        WHERE student_id=$student_id");

    // Create a payment_schedules row if none exists yet (new student)
    $check = $conn->query("SELECT id FROM payment_schedules WHERE student_id=$student_id");
    if (!$check || $check->num_rows === 0) {
        $tfRes = $conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id=$student_id LIMIT 1");
        $tfRow = $tfRes ? $tfRes->fetch_assoc() : null;
        $total = $tfRow ? (float)$tfRow['total_assessment'] : $amount_due;
        $dpPaidRes2 = $conn->query("SELECT COALESCE(SUM(amount),0) AS paid FROM installment_payments WHERE student_id=$student_id AND exam_period='Downpayment'");
        $dpPaid2    = $dpPaidRes2 ? (float)$dpPaidRes2->fetch_assoc()['paid'] : 0;
        $dpCredit2  = $dpPaid2 > 0 ? $dpPaid2 : round($total/4, 2);
        $rem2       = max(0, $total - $dpCredit2);
        $pd = ceil($rem2/3*100)/100; $md = $pd; $fd = round($rem2-$pd*2,2);
        $conn->query("INSERT INTO payment_schedules
            (student_id,payment_type,total_assessment,prelim_due,midterm_due,finals_due,{$p}_status,{$unlocked_col})
            VALUES ($student_id,'installment',$total,$pd,$md,$fd,'unpaid',NOW())");
    }

    logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'SEND_NOTICE', 'student', $student_id,
        "Sent $exam_period notice to student ID $student_id (₱" . number_format($amount_due,2) . ")");
    echo json_encode(['success'=>true,'message'=>"$exam_period notice sent. Payment period is now unlocked for the student."]);
}


// ─────────────────────────────────────────────────────────────
// BULK NOTICE — send payment notice to all matching students
// POST ?action=send_bulk_notice
// Body: { exam_period, category (College|SHS|TVET|all), due_date, message_template, accounting_user_id }
// ─────────────────────────────────────────────────────────────
function sendBulkNotice($conn, $data) {
    $exam_period  = trim($data['exam_period']        ?? '');
    $category     = strtoupper(trim($data['category'] ?? 'ALL'));
    $due_date     = trim($data['due_date']            ?? '');
    $msg_template = trim($data['message_template']   ?? '');
    $acc_user_id  = (int)($data['accounting_user_id'] ?? 0);

    if (!in_array($exam_period, ['Prelim','Midterm','Finals'])) {
        echo json_encode(['success'=>false,'message'=>'Invalid exam_period']); return;
    }

    // Build WHERE clause for category filter
    $cat_where = '';
    if ($category !== 'ALL') {
        $cat_esc   = $conn->real_escape_string($category);
        $cat_where = "AND UPPER(COALESCE(s.student_category,'College')) = '$cat_esc'";
    }

    $p = strtolower($exam_period);
    $res = $conn->query("
        SELECT s.id, s.first_name, s.last_name,
               COALESCE(ps.{$p}_due, ROUND(COALESCE(tf.total_assessment,0)/4,2)) AS period_due,
               COALESCE(ps.{$p}_paid, 0) AS period_paid
        FROM students s
        LEFT JOIN tuition_fees      tf ON tf.student_id = s.id
        LEFT JOIN payment_schedules ps ON ps.student_id = s.id
        WHERE s.approval_status   = 'Approved'
          AND s.enrollment_status = 'Enrolled'
          AND s.payment_plan      = 'installment'
          $cat_where
        ORDER BY s.last_name ASC
    ");

    $sent = 0; $skipped = 0;
    $unlocked_col = $p.'_unlocked_at';
    $due_val = $due_date ? "'$due_date'" : 'NULL';

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $sid       = (int)$row['id'];
            $fname     = $row['first_name'];
            $lname     = $row['last_name'];
            $amount    = (float)$row['period_due'] - (float)$row['period_paid'];
            if ($amount <= 0) { $skipped++; continue; } // already paid

            $message = $msg_template
                ? str_replace(['{name}','{period}','{amount}'],
                              [$fname, $exam_period, '₱'.number_format($amount,2)],
                              $msg_template)
                : "Dear $fname, your $exam_period payment of ₱".number_format($amount,2)." is now due. Please settle at the Accounting office.";
            $msg_esc = $conn->real_escape_string($message);

            $conn->query("INSERT INTO payment_notices (student_id,exam_period,amount_due,due_date,message,sent_by)
                VALUES ($sid,'$exam_period',$amount,$due_val,'$msg_esc',$acc_user_id)
                ON DUPLICATE KEY UPDATE amount_due=$amount,due_date=$due_val,message='$msg_esc',sent_by=$acc_user_id,sent_at=NOW(),is_read=0");

            // Unlock the period
            $conn->query("UPDATE payment_schedules
                SET {$p}_status = IF({$p}_status='locked','unpaid',{$p}_status),
                    $unlocked_col = IF($unlocked_col IS NULL, NOW(), $unlocked_col)
                WHERE student_id = $sid");

            // Create payment_schedules row if missing
            $chk = $conn->query("SELECT id FROM payment_schedules WHERE student_id=$sid");
            if (!$chk || $chk->num_rows === 0) {
                $tfRes = $conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id=$sid LIMIT 1");
                $tfRow = $tfRes ? $tfRes->fetch_assoc() : null;
                $total = $tfRow ? (float)$tfRow['total_assessment'] : 0;
                $dpR   = $conn->query("SELECT COALESCE(SUM(amount),0) AS paid FROM installment_payments WHERE student_id=$sid AND exam_period='Downpayment'");
                $dpPd  = $dpR ? (float)$dpR->fetch_assoc()['paid'] : 0;
                $dpCr  = $dpPd > 0 ? $dpPd : round($total/4,2);
                $rem   = max(0, $total - $dpCr);
                $pd = ceil($rem/3*100)/100; $md = $pd; $fd = round($rem-$pd*2,2);
                $conn->query("INSERT INTO payment_schedules
                    (student_id,payment_type,total_assessment,prelim_due,midterm_due,finals_due,{$p}_status,{$unlocked_col})
                    VALUES ($sid,'installment',$total,$pd,$md,$fd,'unpaid',NOW())");
            }
            $sent++;
        }
    }

    logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'BULK_SEND_NOTICE', 'notice', 0,
        "Bulk $exam_period notice: $sent sent, $skipped skipped. Category: $category");
    echo json_encode(['success'=>true,'sent'=>$sent,'skipped'=>$skipped,
        'message'=>"Notice sent to $sent student(s). $skipped already paid/skipped."]);
}


// ─────────────────────────────────────────────────────────────────────────────
// RECALC ALL PAYMENT SCHEDULES
// GET ?action=recalc_payment_schedules
// Corrects any stale carry-over data in payment_schedules.
// For every installment student: sets period_paid = actual SUM(installment_payments)
// and period_status based on that, for unlocked periods only.
// ─────────────────────────────────────────────────────────────────────────────
function recalcAllPaymentSchedules($conn) {
    // Get all installment students who have a payment_schedules row
    $res = $conn->query("
        SELECT s.id AS student_id
        FROM students s
        INNER JOIN payment_schedules ps ON ps.student_id = s.id
        WHERE s.payment_plan = 'installment'
    ");

    $fixed = 0;
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            recomputeSchedule($conn, (int)$row['student_id']);
            $fixed++;
        }
    }
    echo json_encode(['success'=>true,'message'=>"Recalculated $fixed student payment schedules.",'fixed'=>$fixed]);
}

function getExamPermits($conn) {
    $status = $conn->real_escape_string($_GET['status'] ?? 'pending');
    $res = $conn->query("
        SELECT ep.*, s.student_number, s.first_name, s.last_name,
               s.program, s.year_level, s.semester AS student_raw_sem,
               u.first_name AS approved_by_first, u.last_name AS approved_by_last
        FROM exam_permits ep
        JOIN students s ON ep.student_id=s.id
        LEFT JOIN users u ON ep.approved_by=u.id
        WHERE ep.status='$status'
        ORDER BY ep.requested_at DESC");
    $permits = [];
    while ($row = $res->fetch_assoc()) {
        // Parse semester + school_year from stored permit values (set correctly at request time)
        // But if they look like old hardcoded values, fix them using the student's raw semester
        $raw = trim($row['student_raw_sem'] ?? '');
        $sem = trim($row['semester'] ?? '');
        $ay  = trim($row['school_year'] ?? '');

        // If permit semester looks like a full combined string, re-parse it
        if (preg_match('/^(.+?),\s*AY\s*([\d]{4}-[\d]{4})/i', $sem, $m)) {
            $sem = trim($m[1]);
            $ay  = trim($m[2]);
        } elseif (preg_match('/^(.+?),\s*AY\s*([\d]{4}-[\d]{4})/i', $raw, $m)) {
            // Fall back to student's raw semester
            $sem = trim($m[1]);
            $ay  = trim($m[2]);
        } elseif (preg_match('/([\d]{4}-[\d]{4})/', $raw, $m)) {
            $ay  = $m[1];
            $sem = trim(preg_replace('/,?\s*AY\s*[\d]{4}-[\d]{4}/i', '', $raw));
        }

        $row['semester']    = $sem;
        $row['school_year'] = $ay;
        unset($row['student_raw_sem']);
        $permits[] = $row;
    }
    echo json_encode(['success'=>true,'permits'=>$permits]);
}

function getStudentPermitStatus($conn) {
    $student_id = (int)($_GET['student_id'] ?? 0);
    if (!$student_id) { echo json_encode(['success'=>false]); return; }
    $res = $conn->query("SELECT * FROM exam_permits WHERE student_id=$student_id ORDER BY requested_at DESC");
    $permits = [];
    while ($row = $res->fetch_assoc()) $permits[] = $row;
    echo json_encode(['success'=>true,'permits'=>$permits]);
}

function requestExamPermit($conn, $data) {
    $student_id  = (int)($data['student_id']  ?? 0);
    $exam_period = $conn->real_escape_string($data['exam_period'] ?? '');

    if (!$student_id || !in_array($exam_period, ['Prelim','Midterm','Finals'])) {
        echo json_encode(['success'=>false,'message'=>'Invalid data']); return;
    }

    // ── Resolve accurate semester + school_year from the student's own record ──
    // The students.semester field stores the full string e.g. "2nd Semester, AY 2025-2026"
    // We split it to get clean values for the permit.
    $stRes = $conn->query("SELECT semester FROM students WHERE id = $student_id LIMIT 1");
    $stRow = $stRes ? $stRes->fetch_assoc() : null;
    $rawSemester = trim($stRow['semester'] ?? '');

    // Parse "2nd Semester, AY 2025-2026" → semester="2nd Semester" school_year="2025-2026"
    // Also handle "1st Semester" without AY, or data passed directly from the client
    $semester    = $rawSemester;
    $school_year = date('Y') . '-' . (date('Y') + 1); // default fallback

    if (preg_match('/^(.+?),\s*AY\s*([\d]{4}-[\d]{4})/i', $rawSemester, $m)) {
        $semester    = trim($m[1]);   // "2nd Semester"
        $school_year = trim($m[2]);   // "2025-2026"
    } elseif (preg_match('/([\d]{4}-[\d]{4})/', $rawSemester, $m)) {
        $school_year = $m[1];
        $semester    = trim(preg_replace('/,?\s*AY\s*[\d]{4}-[\d]{4}/i', '', $rawSemester));
    }

    // Fall back to client-supplied values only if DB has nothing
    if (!$semester)    $semester    = $conn->real_escape_string($data['semester']    ?? '2nd Semester');
    if (!$school_year) $school_year = $conn->real_escape_string($data['school_year'] ?? date('Y').'-'.(date('Y')+1));

    $semester_esc    = $conn->real_escape_string($semester);
    $school_year_esc = $conn->real_escape_string($school_year);

    $p   = strtolower($exam_period);
    $stPlanRes = $conn->query("SELECT payment_status, payment_plan FROM students WHERE id=$student_id LIMIT 1");
    $stPlanRow = $stPlanRes ? $stPlanRes->fetch_assoc() : null;
    $isFullPlan  = $stPlanRow && strtolower($stPlanRow['payment_plan'] ?? '') === 'full';
    $studentPaid = $stPlanRow && $stPlanRow['payment_status'] === 'Paid';
    if ($isFullPlan) {
        if (!$studentPaid) {
            echo json_encode(['success'=>false,'message'=>'Full payment not yet verified by Accounting.']); return;
        }
    } else {
        $res = $conn->query("SELECT * FROM payment_schedules WHERE student_id=$student_id LIMIT 1");
        $sch = $res ? $res->fetch_assoc() : null;
        if (!$sch) { echo json_encode(['success'=>false,'message'=>'No payment record. Contact Accounting.']); return; }
        if ($sch[$p.'_status'] === 'locked') {
            echo json_encode(['success'=>false,'message'=>"$exam_period not unlocked yet. Wait for Accounting notice."]); return;
        }
        $paidRes = $conn->query("SELECT COALESCE(SUM(amount),0) AS paid FROM installment_payments WHERE student_id=$student_id AND exam_period='$exam_period'");
        $paid = $paidRes ? (float)$paidRes->fetch_assoc()['paid'] : 0;
        $due  = (float)($sch[$p.'_due'] ?? 0);
        // Allow permit request even for partial payment — balance will appear on permit
        // Only block if NO payment has been recorded at all for this period
        if ($paid <= 0) {
            echo json_encode(['success'=>false,'message'=>"No payment recorded for $exam_period yet. Please pay at the Accounting office first."]); return;
        }
    }

    $stmt = $conn->prepare("INSERT INTO exam_permits
        (student_id,exam_period,school_year,semester,status)
        VALUES (?,?,?,?,'pending')
        ON DUPLICATE KEY UPDATE status='pending',requested_at=NOW(),remarks=NULL,approved_at=NULL");
    $stmt->bind_param("isss", $student_id, $exam_period, $school_year, $semester);
    $stmt->execute(); $stmt->close();
    echo json_encode(['success'=>true,'message'=>"$exam_period permit request submitted! Accounting will process it shortly."]);
}

function processExamPermit($conn, $data) {
    $permit_id   = (int)($data['permit_id']          ?? 0);
    $action      = ($data['action'] === 'approve') ? 'approved' : 'rejected';
    $remarks     = $conn->real_escape_string($data['remarks'] ?? '');
    $approved_by = (int)($data['accounting_user_id'] ?? 0);
    if (!$permit_id) { echo json_encode(['success'=>false,'message'=>'permit_id required']); return; }
    $conn->query("UPDATE exam_permits SET status='$action',approved_at=NOW(),
        approved_by=$approved_by,remarks='$remarks' WHERE id=$permit_id");
    logAuditShared($conn, $GLOBALS['authUser'] ?? null, strtoupper($action).'_PERMIT', 'exam_permit', $permit_id,
        "Exam permit {$action}d. ID: $permit_id. Remarks: $remarks");
    echo json_encode(['success'=>true,'message'=>"Permit $action."]);
}
function getAllEnrolledStudents($conn) {
    $res = $conn->query("
        SELECT
            s.id             AS student_id,
            s.student_number,
            s.first_name,
            s.last_name,
            s.program,
            s.year_level,
            s.semester,
            s.payment_method,
            s.payment_plan,
            s.approval_status,
            s.enrollment_status,
            UPPER(COALESCE(s.student_category, 'College')) AS student_category,
            COALESCE(tf.total_assessment, 0) AS total_assessment,
            COALESCE(ps.prelim_due,   ROUND(COALESCE(tf.total_assessment,0) / 4, 2)) AS prelim_due,
            COALESCE(ps.midterm_due,  ROUND(COALESCE(tf.total_assessment,0) / 4, 2)) AS midterm_due,
            COALESCE(ps.finals_due,   ROUND(COALESCE(tf.total_assessment,0) / 4, 2)) AS finals_due,
            COALESCE(ps.prelim_paid,  0) AS prelim_paid,
            COALESCE(ps.midterm_paid, 0) AS midterm_paid,
            COALESCE(ps.finals_paid,  0) AS finals_paid,
            COALESCE(ps.prelim_status,  'locked') AS prelim_status,
            COALESCE(ps.midterm_status, 'locked') AS midterm_status,
            COALESCE(ps.finals_status,  'locked') AS finals_status
        FROM students s
        LEFT JOIN tuition_fees      tf ON tf.student_id = s.id
        LEFT JOIN payment_schedules ps ON ps.student_id = s.id
        WHERE s.approval_status   = 'Approved'
          AND s.enrollment_status = 'Enrolled'
          AND s.payment_plan      = 'installment'
        ORDER BY s.last_name ASC, s.first_name ASC
    ");

    $students = [];
    while ($row = $res->fetch_assoc()) {
        $students[] = buildStudentRow($row);
    }

    // DEBUG: if nothing found, return all students with actual status so we can diagnose
    if (empty($students)) {
        $all = $conn->query("
            SELECT
                s.id AS student_id, s.student_number, s.first_name, s.last_name,
                s.program, s.year_level, s.semester, s.payment_method, s.payment_plan,
                s.approval_status, s.enrollment_status,
                COALESCE(tf.total_assessment,0) AS total_assessment,
                ROUND(COALESCE(tf.total_assessment,0)/4,2) AS prelim_due,
                ROUND(COALESCE(tf.total_assessment,0)/4,2) AS midterm_due,
                ROUND(COALESCE(tf.total_assessment,0)/4,2) AS finals_due,
                0 AS prelim_paid, 0 AS midterm_paid, 0 AS finals_paid,
                'locked' AS prelim_status, 'locked' AS midterm_status, 'locked' AS finals_status
            FROM students s
            LEFT JOIN tuition_fees tf ON tf.student_id = s.id
            ORDER BY s.last_name ASC
        ");
        $debug = [];
        while ($row = $all->fetch_assoc()) {
            $debug[] = buildStudentRow($row);
        }
        echo json_encode(['success' => true, 'students' => $debug, 'debug' => true, 'note' => 'No Enrolled+Approved students found. Showing all students for diagnosis.']);
        return;
    }

    echo json_encode(['success' => true, 'students' => $students]);
}

function buildStudentRow($row) {
    return [
        'studentId'      => (int)$row['student_id'],
        'studentNumber'  => $row['student_number'],
        'firstName'      => $row['first_name'],
        'lastName'       => $row['last_name'],
        'program'        => $row['program'],
        'yearLevel'      => $row['year_level'],
        'semester'       => $row['semester'],
        'paymentMethod'  => $row['payment_method'],
        'paymentPlan'    => $row['payment_plan'],
        'approvalStatus' => $row['approval_status'] ?? '',
        'enrollmentStatus'  => $row['enrollment_status'] ?? '',
        'studentCategory'   => strtoupper($row['student_category'] ?? 'College'),
        'totalAssessment'=> (float)$row['total_assessment'],
        'prelimDue'      => (float)$row['prelim_due'],
        'midtermDue'     => (float)$row['midterm_due'],
        'finalsDue'      => (float)$row['finals_due'],
        'prelimPaid'     => (float)$row['prelim_paid'],
        'midtermPaid'    => (float)$row['midterm_paid'],
        'finalsPaid'     => (float)$row['finals_paid'],
        'prelimStatus'   => $row['prelim_status'],
        'midtermStatus'  => $row['midterm_status'],
        'finalsStatus'   => $row['finals_status'],
    ];
}

// ─────────────────────────────────────────────────────────────
// ACCOUNTING: Unlock Payment Period (Direct unlock without sending notice)
// POST ?action=unlock_payment_period
// Body: { student_id, exam_period, accounting_user_id }
// ─────────────────────────────────────────────────────────────
function unlockPaymentPeriod($conn, $data) {
    $student_id  = (int)($data['student_id']  ?? 0);
    $exam_period = trim($data['exam_period']  ?? '');
    $acc_user_id = (int)($data['accounting_user_id'] ?? 0);

    if (!$student_id || !in_array($exam_period, ['Prelim', 'Midterm', 'Finals'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid student_id or exam_period']);
        return;
    }

    // ── 1. Check whether payment_schedules record already exists ──────────
    // FIX AC-03: Use prepared statements in unlockPaymentPeriod
    $checkStmt = $conn->prepare("SELECT id FROM payment_schedules WHERE student_id = ? LIMIT 1");
    $checkStmt->bind_param("i", $student_id);
    $checkStmt->execute();
    $check = $checkStmt->get_result();
    if (!$check) {
        echo json_encode(['success' => false, 'message' => 'DB error (check): ' . $conn->error]);
        return;
    }

    if ($check->num_rows === 0) {
        // ── 2a. No record yet — fetch total_assessment from tuition_fees ──
        $tfRes = $conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id = $student_id LIMIT 1");
        $tfRow = $tfRes ? $tfRes->fetch_assoc() : null;
        $total = $tfRow ? (float)$tfRow['total_assessment'] : 0;

        // Fallback: compute from students + formula if tuition_fees row is missing
        if ($total <= 0) {
            $stRes = $conn->query("SELECT program FROM students WHERE id = $student_id LIMIT 1");
            $stRow = $stRes ? $stRes->fetch_assoc() : null;
            if ($stRow) {
                // Use default 18 units as last resort — same fallback as getFeePreview()
                $units         = 18;
                $total         = ($units * 650) + 6688 + 700 + ($units * 21 * 3);
            }
        }

        if ($total <= 0) {
            echo json_encode(['success' => false, 'message' => 'No tuition fees found for this student. Please compute fees first.']);
            return;
        }

        $pd = round($total / 4, 2);
        $md = round($total / 4, 2);
        $fd = round($total - $pd - $md * 2, 2);

       
        $prelim_status  = ($exam_period === 'Prelim')   ? 'unpaid' : 'locked';
        $midterm_status = ($exam_period === 'Midterm')  ? 'unpaid' : 'locked';
        $finals_status  = ($exam_period === 'Finals')   ? 'unpaid' : 'locked';
        $prelim_ts      = ($exam_period === 'Prelim')   ? 'NOW()' : 'NULL';
        $midterm_ts     = ($exam_period === 'Midterm')  ? 'NOW()' : 'NULL';
        $finals_ts      = ($exam_period === 'Finals')   ? 'NOW()' : 'NULL';

        $insertSql = "
            INSERT INTO payment_schedules
                (student_id, payment_type, total_assessment,
                 prelim_due, midterm_due, finals_due,
                 prelim_status, midterm_status, finals_status,
                 prelim_unlocked_at, midterm_unlocked_at, finals_unlocked_at)
            VALUES (
                $student_id, 'installment', $total,
                $pd, $md, $fd,
                '$prelim_status', '$midterm_status', '$finals_status',
                $prelim_ts, $midterm_ts, $finals_ts
            )
        ";

        $conn->query($insertSql);
        if ($conn->error) {
            echo json_encode(['success' => false, 'message' => 'DB error (insert): ' . $conn->error]);
            return;
        }

    } else {
        
        //
        if ($exam_period === 'Prelim') {
            $sql = "UPDATE payment_schedules
                    SET prelim_status      = IF(prelim_status      = 'locked', 'unpaid', prelim_status),
                        prelim_unlocked_at = IF(prelim_unlocked_at IS NULL, NOW(), prelim_unlocked_at)
                    WHERE student_id = $student_id";
        } elseif ($exam_period === 'Midterm') {
            $sql = "UPDATE payment_schedules
                    SET midterm_status      = IF(midterm_status      = 'locked', 'unpaid', midterm_status),
                        midterm_unlocked_at = IF(midterm_unlocked_at IS NULL, NOW(), midterm_unlocked_at)
                    WHERE student_id = $student_id";
        } else { // Finals
            $sql = "UPDATE payment_schedules
                    SET finals_status      = IF(finals_status      = 'locked', 'unpaid', finals_status),
                        finals_unlocked_at = IF(finals_unlocked_at IS NULL, NOW(), finals_unlocked_at)
                    WHERE student_id = $student_id";
        }

        $conn->query($sql);
        if ($conn->error) {
            echo json_encode(['success' => false, 'message' => 'DB error (update): ' . $conn->error]);
            return;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "$exam_period payment period has been unlocked.",
    ]);
    logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'UNLOCK_PERIOD', 'student', $student_id,
        "Unlocked $exam_period payment period for student ID $student_id");
}
// ─────────────────────────────────────────────────────────────
// GET STUDENT INSTALLMENT BREAKDOWN
// GET ?action=get_student_installment&student_id=XX
//
// Returns the full installment schedule for a student:
// - per-term due/paid/status/balance
// - total assessment, total paid, remaining balance
// - all payment receipts (installment_payments rows)
// - payment notices per period
// Used by: accounting permit page, student payment-schedule page
// ─────────────────────────────────────────────────────────────
function getStudentInstallment($conn) {
    $student_id = (int)($_GET['student_id'] ?? 0);

    // Also accept user_id and resolve to student_id (defensive)
    if (!$student_id) {
        $user_id = (int)($_GET['user_id'] ?? 0);
        if ($user_id) {
            $ur = $conn->query("SELECT id FROM students WHERE user_id = $user_id LIMIT 1");
            $ur_row = $ur ? $ur->fetch_assoc() : null;
            if ($ur_row) $student_id = (int)$ur_row['id'];
        }
    }
    if (!$student_id) { echo json_encode(['success' => false, 'message' => 'student_id required']); return; }

    // ── Student info ──────────────────────────────────────────
    $stRes = $conn->query("SELECT id, student_number, first_name, last_name, program, year_level,
                                  semester, payment_plan, payment_method, payment_status
                           FROM students WHERE id = $student_id LIMIT 1");
    $student = $stRes ? $stRes->fetch_assoc() : null;
    if (!$student) { echo json_encode(['success' => false, 'message' => 'Student not found']); return; }

    // ── Tuition fees ──────────────────────────────────────────
    $tfRes = $conn->query("SELECT * FROM tuition_fees WHERE student_id = $student_id LIMIT 1");
    $tf    = $tfRes ? $tfRes->fetch_assoc() : null;
    $total_assessment = $tf ? (float)$tf['total_assessment'] : 0;

    // ── Payment schedule row (per-term due/paid/status) ───────
    $psRes = $conn->query("SELECT * FROM payment_schedules WHERE student_id = $student_id LIMIT 1");
    $ps    = $psRes ? $psRes->fetch_assoc() : null;

    // Compute per-term due amounts (4 equal terms for installment)
    $term_due = $total_assessment > 0 ? round($total_assessment / 4, 2) : 0;

    $terms = ['Prelim', 'Midterm', 'Finals'];
    $term_data = [];
    $total_paid_all = 0;

    // Downpayment (DP) — first installment
    $dp_res  = $conn->query("SELECT COALESCE(SUM(amount),0) AS paid FROM installment_payments WHERE student_id=$student_id AND exam_period='Downpayment'");
    $dp_paid = (float)($dp_res->fetch_assoc()['paid'] ?? 0);
    $dp_due  = $term_due;
    $dp_balance = max(0, $dp_due - $dp_paid);
    $dp_status  = $dp_paid <= 0 ? 'unpaid' : ($dp_paid >= $dp_due ? 'paid' : 'partial');
    $total_paid_all += $dp_paid;

    $term_data[] = [
        'period'   => 'Downpayment',
        'due'      => $dp_due,
        'paid'     => $dp_paid,
        'balance'  => $dp_balance,
        'status'   => $dp_status,
        'schedStatus' => $dp_status,
    ];

    foreach ($terms as $period) {
        $p = strtolower($period);
        $due    = $ps ? (float)$ps[$p.'_due']  : $term_due;
        $paid   = $ps ? (float)$ps[$p.'_paid'] : 0;
        $status = $ps ? $ps[$p.'_status'] : 'locked';

        // Recompute paid from installment_payments for accuracy
        $pr = $conn->query("SELECT COALESCE(SUM(amount),0) AS paid FROM installment_payments WHERE student_id=$student_id AND exam_period='$period'");
        $paid = (float)($pr->fetch_assoc()['paid'] ?? 0);

        $balance = max(0, $due - $paid);
        $total_paid_all += $paid;

        $term_data[] = [
            'period'      => $period,
            'due'         => $due,
            'paid'        => $paid,
            'balance'     => $balance,
            'status'      => $status,
            'schedStatus' => $status,
        ];
    }

    $remaining_balance = max(0, $total_assessment - $total_paid_all);

    // ── All payment receipts ──────────────────────────────────
    $ipRes = $conn->query("
        SELECT ip.*, u.first_name AS recorded_by_name
        FROM installment_payments ip
        LEFT JOIN users u ON ip.recorded_by = u.id
        WHERE ip.student_id = $student_id
        ORDER BY ip.payment_date ASC, ip.created_at ASC
    ");
    $receipts = [];
    if ($ipRes) {
        while ($r = $ipRes->fetch_assoc()) {
            $receipts[] = [
                'id'             => (int)$r['id'],
                'orArNumber'     => $r['or_ar_number'],
                'orArType'       => $r['or_ar_type'],
                'amount'         => (float)$r['amount'],
                'paymentDate'    => $r['payment_date'],
                'paymentMethod'  => $r['payment_method'],
                'gcashReference' => $r['gcash_reference'] ?? '',
                'examPeriod'     => $r['exam_period'],
                'notes'          => $r['notes'] ?? '',
                'recordedByName' => $r['recorded_by_name'] ?? 'Accounting',
            ];
        }
    }

    // ── Payment notices per period ────────────────────────────
    $notRes = $conn->query("SELECT exam_period, amount_due, due_date, message, sent_at, is_read
                            FROM payment_notices WHERE student_id=$student_id");
    $notices = [];
    if ($notRes) {
        while ($r = $notRes->fetch_assoc()) $notices[$r['exam_period']] = $r;
    }

    echo json_encode([
        'success'          => true,
        'student'          => $student,
        'fees'             => $tf ? [
            'units'            => (int)$tf['units'],
            'tuitionFee'       => (float)$tf['tuition_fee'],
            'miscellaneousFee' => (float)$tf['miscellaneous_fee'],
            'registrationFee'  => (float)$tf['registration_fee'],
            'laboratoryFee'    => (float)$tf['laboratory_fee'],
            'energyFee'        => (float)$tf['energy_fee'],
            'extraFees'        => _buildExtraFeesList($conn, 'College', (int)$tf['units']),
            'subtotal'         => (float)$tf['subtotal'],
            'discount'         => (float)$tf['discount'],
            'installmentFee'   => (float)$tf['installment_fee'],
            'totalAssessment'  => $total_assessment,
        ] : null,
        'terms'            => $term_data,       // per-term: due/paid/balance/status
        'receipts'         => $receipts,        // all OR/AR receipts
        'notices'          => $notices,         // payment notices per period
        'totalAssessment'  => $total_assessment,
        'totalPaid'        => $total_paid_all,
        'remainingBalance' => $remaining_balance,
        'paymentStatus'    => $total_paid_all <= 0 ? 'Unpaid'
                              : ($remaining_balance <= 0 ? 'Fully Paid' : 'Partial'),
    ]);
}
// ─────────────────────────────────────────────────────────────
// STUDENT: Submit installment payment for Accounting approval
// POST ?action=submit_installment_payment
// Body: { student_id, amount, payment_date, payment_method,
//         gcash_reference?, exam_period, notes? }
//
// This mirrors the enrollment GCash/Cash flow:
//  1. Saves a pending payment_log record
//  2. Accounting sees it in their queue (get_pending_payments)
//  3. Accounting verifies → verify_payment → recordInstallment runs
//     which also syncs payment_schedules
// ─────────────────────────────────────────────────────────────
function submitInstallmentPayment($conn, $data) {
    $student_id     = (int)($data['student_id']      ?? 0);
    $amount         = (float)($data['amount']         ?? 0);
    $payment_date   = trim($data['payment_date']     ?? date('Y-m-d'));
    $payment_method = trim($data['payment_method']   ?? 'Cash');
    $gcash_ref      = trim($data['gcash_reference']  ?? '');
    $exam_period    = trim($data['exam_period']      ?? '');
    $notes          = trim($data['notes']            ?? '');

    if (!$student_id || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'student_id and amount required']); return;
    }
    if (!in_array($exam_period, ['Prelim', 'Midterm', 'Finals'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid exam_period']); return;
    }

    // Generate a temporary reference number for tracking
    $year    = date('Y');
    $cntRes  = $conn->query("SELECT COUNT(*) AS cnt FROM payment_logs WHERE YEAR(created_at) = $year");
    $cnt     = (int)($cntRes->fetch_assoc()['cnt'] ?? 0) + 1;
    $ref     = 'PAY-' . $year . str_pad($cnt, 4, '0', STR_PAD_LEFT);

    $pm_esc    = $conn->real_escape_string($payment_method);
    $ref_esc   = $conn->real_escape_string($gcash_ref ?: $ref);
    $date_esc  = $conn->real_escape_string($payment_date);
    $ep_esc    = $conn->real_escape_string($exam_period);
    $extra_esc = $conn->real_escape_string($notes);
    // notes format: "Midterm|[Midterm] extra notes" — exam_period prefix BEFORE bracket
    // This is what verifyPayment parses with regex /^(Prelim|Midterm|Finals...)\|?/
    $notes_full = $conn->real_escape_string("$exam_period|[$exam_period] $notes");

    // Insert into payment_logs as Pending — same table Accounting watches
    // notes set in one query to avoid race condition
    $conn->query("INSERT INTO payment_logs
        (student_id, gcash_reference, gcash_amount, gcash_date, payment_method, status, notes)
        VALUES ($student_id, '$ref_esc', $amount, '$date_esc', '$pm_esc', 'Pending', '$notes_full')");

    if ($conn->error) {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]); return;
    }

    $log_id = $conn->insert_id;

    echo json_encode([
        'success'     => true,
        'message'     => 'Payment submitted. Waiting for Accounting to verify.',
        'orArNumber'  => $ref,
        'logId'       => $log_id,
    ]);
}

// ─────────────────────────────────────────────────────────────
// GET PERMIT DETAILS — for student permit viewer
// GET ?action=get_permit_details&permit_id=XX&student_id=XX
//
// Returns permit + student info + enrolled courses
// ─────────────────────────────────────────────────────────────
function getPermitDetails($conn) {
    $permit_id  = (int)($_GET['permit_id']  ?? 0);
    $student_id = (int)($_GET['student_id'] ?? 0);
    if (!$permit_id || !$student_id) {
        echo json_encode(['success' => false, 'message' => 'permit_id and student_id required']); return;
    }

    // Permit + approver info
    $pRes = $conn->query("
        SELECT ep.*,
               s.student_number, s.first_name, s.last_name, s.program, s.year_level,
               s.semester AS raw_semester,
               u.first_name AS approved_by_first, u.last_name AS approved_by_last
        FROM exam_permits ep
        JOIN students s ON ep.student_id = s.id
        LEFT JOIN users u ON ep.approved_by = u.id
        WHERE ep.id = $permit_id AND ep.student_id = $student_id
        LIMIT 1
    ");
    $permit = $pRes ? $pRes->fetch_assoc() : null;
    if (!$permit) {
        echo json_encode(['success' => false, 'message' => 'Permit not found']); return;
    }

    // ── Parse accurate semester + school_year from DB ─────────────────────
    // students.semester stores full string: "2nd Semester, AY 2025-2026"
    $rawSemester = trim($permit['raw_semester'] ?? '');
    $semLabel    = $permit['semester'];    // already stored when permit was created
    $schoolYear  = $permit['school_year']; // already stored when permit was created

    // If the stored permit semester looks like the full raw string, re-parse it
    if (preg_match('/^(.+?),\s*AY\s*([\d]{4}-[\d]{4})/i', $semLabel, $m)) {
        $semLabel   = trim($m[1]);
        $schoolYear = trim($m[2]);
    } elseif (preg_match('/^(.+?),\s*AY\s*([\d]{4}-[\d]{4})/i', $rawSemester, $m)) {
        $semLabel   = trim($m[1]);
        $schoolYear = trim($m[2]);
    }

    $permit['semester']    = $semLabel;
    $permit['school_year'] = $schoolYear;

    // ── Enrolled courses filtered by the student's current semester ───────
    // Use the semester stored in the courses table for accurate matching.
    // The raw semester from students table (e.g. "2nd Semester, AY 2025-2026")
    // must match courses.semester exactly — or we do a partial match on the AY.
    $semEsc = $conn->real_escape_string($rawSemester ?: $semLabel);
    $ayEsc  = $conn->real_escape_string($schoolYear);

    $cRes = $conn->query("
        SELECT DISTINCT c.code, c.name, c.instructor, c.semester AS course_sem
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = $student_id
          AND e.status = 'Enrolled'
          AND (
            c.semester = '$semEsc'
            OR c.semester LIKE '%$ayEsc%'
            OR c.semester LIKE '%$semLabel%'
          )
        ORDER BY c.code ASC
    ");
    $courses = [];
    if ($cRes) {
        while ($r = $cRes->fetch_assoc()) {
            $courses[] = ['code' => cleanCode($r['code']), 'name' => $r['name'], 'instructor' => $r['instructor']];
        }
    }

    // Fallback: if no courses matched the semester filter, return all enrolled courses
    if (empty($courses)) {
        $cRes2 = $conn->query("
            SELECT DISTINCT c.code, c.name, c.instructor
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            WHERE e.student_id = $student_id AND e.status = 'Enrolled'
            ORDER BY c.code ASC
        ");
        if ($cRes2) {
            while ($r = $cRes2->fetch_assoc()) {
                $courses[] = ['code' => cleanCode($r['code']), 'name' => $r['name'], 'instructor' => $r['instructor']];
            }
        }
    }

    $permit['courses'] = $courses;
    unset($permit['raw_semester']); // clean up internal field

    echo json_encode(['success' => true, 'permit' => $permit]);
}

// ─────────────────────────────────────────────────────────────
// GET INSTALLMENT STUDENTS
// Returns all students using installment payment plan with their balance.
// Called by accounting.ts tab for installment management.
// ─────────────────────────────────────────────────────────────
function getInstallmentStudents($conn) {
    $res = $conn->query("
        SELECT
            s.id AS student_id,
            s.student_number,
            s.first_name,
            s.last_name,
            s.program,
            s.year_level,
            s.student_category,
            s.payment_plan,
            s.payment_status,
            s.approval_status,
            s.enrollment_status,
            tf.total_assessment,
            COALESCE((SELECT SUM(amount) FROM installment_payments WHERE student_id = s.id), 0) AS total_paid,
            COALESCE(p.department,'') AS department
        FROM students s
        LEFT JOIN tuition_fees tf ON tf.student_id = s.id
        LEFT JOIN programs p ON (p.name = s.program OR p.code = s.program)
                              AND p.level_type = s.student_category
        WHERE s.payment_plan = 'installment'
        ORDER BY s.last_name, s.first_name
    ");
    $students = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $total      = (float)($r['total_assessment'] ?? 0);
            $paid       = (float)($r['total_paid']       ?? 0);
            $r['balance']   = max(0, $total - $paid);
            $r['total_paid']= $paid;
            $students[]     = $r;
        }
    }
    echo json_encode(['success' => true, 'students' => $students]);
}

// ─────────────────────────────────────────────────────────────
// EDIT PAYMENT — update an existing payment_log record
// Used by accounting staff to correct payment amounts or status.
// POST body: { payment_log_id, amount, status, notes }
// ─────────────────────────────────────────────────────────────
function editPayment($conn, $data) {
    $log_id = (int)($data['payment_log_id'] ?? 0);
    if (!$log_id) {
        echo json_encode(['success' => false, 'message' => 'payment_log_id required']); return;
    }
    $amount = (float)($data['amount'] ?? 0);
    $status = trim($data['status']   ?? 'Pending');
    $notes  = trim($data['notes']    ?? '');

    // Validate status
    $allowed = ['Pending', 'Verified', 'Rejected'];
    if (!in_array($status, $allowed)) $status = 'Pending';

    $stmt = $conn->prepare("UPDATE payment_logs SET gcash_amount = ?, status = ?, notes = ? WHERE id = ?");
    $stmt->bind_param("dssi", $amount, $status, $notes, $log_id);
    $stmt->execute();

    if ($stmt->affected_rows >= 0) {
        echo json_encode(['success' => true, 'message' => 'Payment updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed: ' . $conn->error]);
    }
}

// ─────────────────────────────────────────────────────────────
// GET SHS FEE — fee preview for SHS students
// SHS is tuition-free (gov't) unless student is a paying enrollee.
// GET ?action=get_shs_fee&student_type=New&discount=0&has_installment=0
// ─────────────────────────────────────────────────────────────
function getSHSFee($conn) {
    $studentType    = trim($_GET['student_type']  ?? 'New');
    $discount       = (float)($_GET['discount']   ?? 0);
    $hasInstallment = (bool)(int)($_GET['has_installment'] ?? 0);
    $sid            = (int)($_GET['student_id'] ?? 0);

    $fc_shs   = loadFeeConfig($conn, 'SHS');
    $inst_fee = $hasInstallment ? (float)($fc_shs['installment_fee']['value'] ?? 750) : 0.00;

    // SHS Transferee: flat rate (configurable)
    if ($studentType === 'Transferee') {
        $flat_rate = (float)($fc_shs['transferee_flat_rate']['value'] ?? 20000);
        // Add any extra SHS fees and build line items
        $std_shs       = ['transferee_flat_rate','installment_fee'];
        $extra_shs_list = [];
        foreach ($fc_shs as $fk => $frow) {
            if (!in_array($fk, $std_shs)) {
                $line_amt        = (float)$frow['value'];
                $flat_rate      += $line_amt;
                $extra_shs_list[] = [
                    'fee_key'    => $fk,
                    'fee_label'  => $frow['fee_label'],
                    'is_per_unit'=> 0,
                    'rate'       => (float)$frow['value'],
                    'amount'     => $line_amt,
                ];
            }
        }
        $total = max(0, $flat_rate - $discount + $inst_fee);
        if ($sid > 0) {
            $conn->query("INSERT INTO tuition_fees
                (student_id,units,tuition_fee,miscellaneous_fee,registration_fee,
                 laboratory_fee,energy_fee,subtotal,discount,installment_fee,total_assessment)
                VALUES ($sid,0,$flat_rate,0,0,0,0,$flat_rate,$discount,$inst_fee,$total)
                ON DUPLICATE KEY UPDATE
                    units=0,tuition_fee=$flat_rate,miscellaneous_fee=0,
                    registration_fee=0,laboratory_fee=0,energy_fee=0,
                    subtotal=$flat_rate,discount=$discount,
                    installment_fee=$inst_fee,total_assessment=$total,updated_at=NOW()");
        }
        echo json_encode(['success'=>true,'isFree'=>false,'fees'=>[
            'tuitionFee'=>$flat_rate,'miscellaneousFee'=>0,'registrationFee'=>0,
            'laboratoryFee'=>0,'energyFee'=>0,
            'extraFees'=>$extra_shs_list,
            'subtotal'=>$flat_rate,
            'discount'=>$discount,'installmentFee'=>$inst_fee,
            'totalAssessment'=>$total,'shsFlatRate'=>true]]);
        return;
    }

    // SHS New / Old: FREE (K-12 Government Subsidy)
    if ($sid > 0) { $conn->query("DELETE FROM tuition_fees WHERE student_id=$sid"); }
    echo json_encode(['success'=>true,'isFree'=>true,'fees'=>[
        'tuitionFee'=>0,'miscellaneousFee'=>0,'registrationFee'=>0,
        'laboratoryFee'=>0,'energyFee'=>0,'extraFees'=>[],'subtotal'=>0,
        'discount'=>0,'installmentFee'=>0,'totalAssessment'=>0,'shsFlatRate'=>false]]);
}

// ─────────────────────────────────────────────────────────────
// GET TVET FEE — fee preview for TVET programs
// TESDA-funded TVET programs may be free; others have fees.
// GET ?action=get_tvet_fee&program=Cookery+NCII&student_type=New
// ─────────────────────────────────────────────────────────────
function getTVETFee($conn) {
    $programName = trim($_GET['program']      ?? '');
    $studentType = trim($_GET['student_type'] ?? 'New');
    $discount    = (float)($_GET['discount']  ?? 0);
    $hasInst     = (bool)(int)($_GET['has_installment'] ?? 0);
    $sid         = (int)($_GET['student_id'] ?? 0);

    $fc_tvet  = loadFeeConfig($conn, 'TVET');
    $inst_fee = $hasInst ? (float)($fc_tvet['installment_fee']['value'] ?? 500) : 0.00;

    // ── TVET Transferee: flat rate ₱20,000 (same as SHS transferee) ──
    if ($studentType === 'Transferee') {
        // Reuse the SHS transferee_flat_rate from fee_config (or TVET if set separately)
        $fc_shs    = loadFeeConfig($conn, 'SHS');
        $flat_rate = (float)($fc_tvet['transferee_flat_rate']['value']
                     ?? $fc_shs['transferee_flat_rate']['value']
                     ?? 20000);
        $total = max(0, $flat_rate - $discount + $inst_fee);
        if ($sid > 0) {
            $conn->query("INSERT INTO tuition_fees
                (student_id,units,tuition_fee,miscellaneous_fee,registration_fee,
                 laboratory_fee,energy_fee,subtotal,discount,installment_fee,total_assessment)
                VALUES ($sid,0,$flat_rate,0,0,0,0,$flat_rate,$discount,$inst_fee,$total)
                ON DUPLICATE KEY UPDATE
                    units=0,tuition_fee=$flat_rate,miscellaneous_fee=0,
                    registration_fee=0,laboratory_fee=0,energy_fee=0,
                    subtotal=$flat_rate,discount=$discount,
                    installment_fee=$inst_fee,total_assessment=$total,updated_at=NOW()");
        }
        echo json_encode(['success' => true, 'isFree' => false, 'fees' => [
            'tuitionFee'      => $flat_rate, 'miscellaneousFee' => 0, 'registrationFee' => 0,
            'laboratoryFee'   => 0, 'energyFee' => 0, 'extraFees' => [],
            'subtotal'        => $flat_rate,
            'discount'        => $discount, 'installmentFee' => $inst_fee,
            'totalAssessment' => $total, 'tvetFlatRate' => true,
        ]]);
        return;
    }

    // ── TVET New / Old: check if NC (free) or Diploma (paid) ──
    $isFree  = false;
    $pnUpper = strtoupper($programName);
    if (str_contains($pnUpper, 'NCII') || str_contains($pnUpper, 'NCIII') ||
        str_contains($pnUpper, 'NC II') || str_contains($pnUpper, 'NC III')) {
        $isFree = true;
    }

    $misc_fee = (float)($fc_tvet['misc_fee']['value'] ?? 3500);
    $reg_fee  = (float)($fc_tvet['reg_fee']['value']  ?? 500);
    // Any extra TVET fees — build list
    $std_tvet        = ['misc_fee', 'reg_fee', 'installment_fee', 'transferee_flat_rate'];
    $extra_tvet      = 0.00;
    $extra_tvet_list = [];
    foreach ($fc_tvet as $fk => $frow) {
        if (!in_array($fk, $std_tvet)) {
            $line_amt         = (float)$frow['value'];
            $extra_tvet      += $line_amt;
            $extra_tvet_list[] = [
                'fee_key'    => $fk,
                'fee_label'  => $frow['fee_label'],
                'is_per_unit'=> 0,
                'rate'       => (float)$frow['value'],
                'amount'     => $line_amt,
            ];
        }
    }
    $subtotal = $misc_fee + $reg_fee + $extra_tvet;
    $total    = $isFree ? 0.00 : max(0, $subtotal - $discount + $inst_fee);

    echo json_encode([
        'success' => true,
        'isFree'  => $isFree,
        'fees'    => [
            'tuitionFee'       => 0,
            'miscellaneousFee' => $isFree ? 0 : $misc_fee,
            'registrationFee'  => $isFree ? 0 : $reg_fee,
            'laboratoryFee'    => 0,
            'energyFee'        => 0,
            'extraFees'        => $isFree ? [] : $extra_tvet_list,
            'subtotal'         => $isFree ? 0 : $subtotal,
            'discount'         => $discount,
            'installmentFee'   => $isFree ? 0 : $inst_fee,
            'totalAssessment'  => $total,
        ],
    ]);
}
// ================================================================
// FEE CONFIG API — GET / SAVE / ADD / DELETE
// ================================================================

/** GET ?action=get_fee_config&category=College|SHS|TVET */
function getFeeConfig(mysqli $conn): void {
    $categories = ['College', 'SHS', 'TVET'];
    $out = [];
    foreach ($categories as $cat) {
        $rows = array_values(loadFeeConfig($conn, $cat));
        // Cast numeric fields so JSON encodes them as numbers, not strings
        foreach ($rows as &$row) {
            $row['value']       = (float)$row['value'];
            $row['is_per_unit'] = (int)$row['is_per_unit'];
            $row['is_active']   = (int)$row['is_active'];
            $row['sort_order']  = (int)$row['sort_order'];
        }
        $out[$cat] = $rows;
    }
    echo json_encode(['success' => true, 'config' => $out]);
}

/** POST ?action=save_fee_config  body: {updates:[{id,value,fee_label,description,is_per_unit}]} */
function saveFeeConfig(mysqli $conn, array $data): void {
    $updates = $data['updates'] ?? [];
    if (empty($updates)) { echo json_encode(['success' => false, 'message' => 'No updates provided']); return; }
    $saved = 0;
    foreach ($updates as $u) {
        $id        = (int)($u['id']          ?? 0);
        $val       = (float)($u['value']      ?? 0);
        $lbl       = $conn->real_escape_string(trim($u['fee_label']    ?? ''));
        $desc      = $conn->real_escape_string(trim($u['description']  ?? ''));
        $isPerUnit = (int)(bool)($u['is_per_unit'] ?? 0);
        if ($id <= 0) continue;
        $conn->query("UPDATE fee_config SET value=$val, fee_label='$lbl', description='$desc', is_per_unit=$isPerUnit WHERE id=$id");
        $saved++;
    }
    logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'SAVE_FEE_CONFIG', 'fee_config', 0,
        "$saved fee config(s) updated");
    echo json_encode(['success' => true, 'saved' => $saved, 'message' => "$saved fee(s) updated. New rates apply to all future enrollments."]);
}

/** POST ?action=add_fee_config  body: {category, fee_key, fee_label, value, is_per_unit, applies_to, description} */
function addFeeConfig(mysqli $conn, array $data): void {
    $cat      = $conn->real_escape_string(trim($data['category']    ?? 'College'));
    $rawKey   = strtolower(trim($data['fee_key']    ?? ''));
    $key      = $conn->real_escape_string(preg_replace('/[^a-z0-9_]/', '_', $rawKey));
    $label    = $conn->real_escape_string(trim($data['fee_label']   ?? ''));
    $val      = (float)($data['value']       ?? 0);
    $perUnit  = (int)(bool)($data['is_per_unit']  ?? 0);
    $appTo    = $conn->real_escape_string(trim($data['applies_to']  ?? 'All'));
    $desc     = $conn->real_escape_string(trim($data['description'] ?? ''));

    if (!$key || !$label) { echo json_encode(['success' => false, 'message' => 'fee_key and fee_label are required']); return; }

    $maxSort = (int)($conn->query("SELECT COALESCE(MAX(sort_order),0)+1 AS s FROM fee_config WHERE category='$cat'")->fetch_assoc()['s'] ?? 1);
    $conn->query("INSERT INTO fee_config (category,fee_key,fee_label,value,is_per_unit,applies_to,description,sort_order)
                  VALUES ('$cat','$key','$label',$val,$perUnit,'$appTo','$desc',$maxSort)");
    if ($conn->insert_id) {
        logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'ADD_FEE_CONFIG', 'fee_config', $conn->insert_id,
            "Added fee config '$label' (₱$val) for category '$cat'");
        echo json_encode(['success' => true, 'id' => $conn->insert_id, 'message' => 'Fee added successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add fee (key may already exist): ' . $conn->error]);
    }
}

/** POST ?action=delete_fee_config  body: {id} */
function deleteFeeConfig(mysqli $conn, array $data): void {
    $id = (int)($data['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'id required']); return; }
    $conn->query("UPDATE fee_config SET is_active=0 WHERE id=$id");
    logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'DELETE_FEE_CONFIG', 'fee_config', $id,
        "Deactivated fee config ID $id");
    echo json_encode(['success' => true, 'message' => 'Fee removed.']);
}

function correctVerifiedPayment($conn, $data) {
    $log_id     = (int)($data['log_id']     ?? 0);
    $student_id = (int)($data['student_id'] ?? 0);
    if (!$log_id || !$student_id) { echo json_encode(['success'=>false,'message'=>'log_id and student_id required']); return; }
    $logRow = $conn->query("SELECT payment_method, status FROM payment_logs WHERE id=$log_id LIMIT 1")->fetch_assoc();
    if (!$logRow) { echo json_encode(['success'=>false,'message'=>'Payment log not found']); return; }
    if ($logRow['status'] !== 'Verified') { echo json_encode(['success'=>false,'message'=>'Only Verified payments can be corrected']); return; }
    $isCash = strtolower($logRow['payment_method'] ?? '') === 'cash';
    $notes  = trim($data['notes'] ?? '');
    if ($isCash) {
        $amount = (float)($data['cash_amount'] ?? 0);
        $date   = trim($data['cash_date'] ?? date('Y-m-d'));
        if ($amount <= 0) { echo json_encode(['success'=>false,'message'=>'Amount must be > 0']); return; }
        $stmt = $conn->prepare("UPDATE payment_logs SET gcash_amount=?, gcash_date=?, notes=? WHERE id=?");
        $stmt->bind_param("dssi", $amount, $date, $notes, $log_id);
    } else {
        $amount    = (float)($data['gcash_amount']  ?? 0);
        $date      = trim($data['gcash_date']       ?? date('Y-m-d'));
        $gcash_ref = trim($data['gcash_reference']  ?? '');
        $stmt = $conn->prepare("UPDATE payment_logs SET gcash_reference=?, gcash_amount=?, gcash_date=?, notes=? WHERE id=?");
        $stmt->bind_param("sdssi", $gcash_ref, $amount, $date, $notes, $log_id);
    }
    $stmt->execute();
    if ($stmt->error) { echo json_encode(['success'=>false,'message'=>'DB error: '.$stmt->error]); return; }
    // Sync installment_payments
    $ins = $conn->prepare("UPDATE installment_payments SET amount=?, payment_date=?, notes=? WHERE payment_log_id=?");
    $ins->bind_param("dssi", $amount, $date, $notes, $log_id);
    $ins->execute();
    // Re-sync student payment_status
    $tp  = (float)$conn->query("SELECT COALESCE(SUM(amount),0) AS tp FROM installment_payments WHERE student_id=$student_id")->fetch_assoc()['tp'];
    $ta  = (float)($conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id=$student_id LIMIT 1")->fetch_assoc()['total_assessment'] ?? 0);
    $ns  = ($ta > 0 && $tp >= $ta) ? 'Paid' : 'Partial';
    $conn->query("UPDATE students SET payment_status='$ns' WHERE id=$student_id");
    echo json_encode(['success'=>true,'message'=>'Payment corrected successfully.']);
}

// ================================================================
// INCOME REPORT GENERATOR
// Weekly / Monthly / Yearly income from student tuition payments
// ================================================================

/**
 * GET ?action=get_income_report&period=weekly|monthly|yearly
 *     &date_from=YYYY-MM-DD&date_to=YYYY-MM-DD
 *     &year=YYYY&month=MM&week_start=YYYY-MM-DD
 * Returns income broken down by day/week/month + totals.
 */
function getIncomeReport($conn) {
    $period    = trim($_GET['period']     ?? 'monthly');
    $year      = (int)($_GET['year']      ?? date('Y'));
    $month     = (int)($_GET['month']     ?? date('n'));
    $dateFrom  = trim($_GET['date_from']  ?? '');
    $dateTo    = trim($_GET['date_to']    ?? '');
    $weekStart = trim($_GET['week_start'] ?? '');

    // Determine actual date range
    switch ($period) {
        case 'weekly':
            if ($weekStart) {
                $from = $weekStart;
                $to   = date('Y-m-d', strtotime($weekStart . ' +6 days'));
            } else {
                $from = date('Y-m-d', strtotime('monday this week'));
                $to   = date('Y-m-d', strtotime('sunday this week'));
            }
            $groupExpr  = 'DATE(p.created_at)';
            $labelExpr  = 'DATE(p.created_at)';
            $groupAlias = 'day';
            break;

        case 'yearly':
            $from       = "$year-01-01";
            $to         = "$year-12-31";
            $groupExpr  = 'MONTH(p.created_at)';
            $labelExpr  = 'DATE_FORMAT(p.created_at, "%b %Y")';
            $groupAlias = 'month';
            break;

        case 'monthly':
        default:
            $period     = 'monthly';
            $from       = date('Y-m-01', mktime(0,0,0,$month,1,$year));
            $to         = date('Y-m-t',  mktime(0,0,0,$month,1,$year));
            $groupExpr  = 'WEEK(p.created_at, 1)';
            $labelExpr  = 'CONCAT("Week ", WEEK(p.created_at,1)-WEEK(DATE_FORMAT(p.created_at,"%%Y-%%m-01"),1)+1, " (", DATE_FORMAT(MIN(p.created_at),"%%b %%d"), ")")';
            $groupAlias = 'week';
            break;
    }

    // Override with custom range if provided
    if ($dateFrom && $dateTo) { $from = $dateFrom; $to = $dateTo; }

    $fromEsc = $conn->real_escape_string($from);
    $toEsc   = $conn->real_escape_string($to);

    // Main breakdown query — use installment_payments as source of truth for actual cash collected
    $breakdownRes = $conn->query("
        SELECT
            $groupExpr AS period_key,
            $labelExpr AS period_label,
            COUNT(DISTINCT p.id) AS transaction_count,
            COUNT(DISTINCT p.student_id) AS student_count,
            SUM(p.amount) AS total_amount,
            SUM(CASE WHEN p.payment_method='Cash'  THEN p.amount ELSE 0 END) AS cash_amount,
            SUM(CASE WHEN p.payment_method='GCash' THEN p.amount ELSE 0 END) AS gcash_amount,
            SUM(CASE WHEN p.payment_method='Check' THEN p.amount ELSE 0 END) AS check_amount
        FROM installment_payments p
        WHERE DATE(p.created_at) BETWEEN '$fromEsc' AND '$toEsc'
        GROUP BY period_key
        ORDER BY MIN(p.created_at) ASC
    ");

    $breakdown = [];
    $grandTotal = 0;
    $grandCount = 0;
    while ($r = $breakdownRes->fetch_assoc()) {
        $breakdown[] = [
            'periodKey'       => $r['period_key'],
            'periodLabel'     => $r['period_label'],
            'transactionCount'=> (int)$r['transaction_count'],
            'studentCount'    => (int)$r['student_count'],
            'totalAmount'     => (float)$r['total_amount'],
            'cashAmount'      => (float)$r['cash_amount'],
            'gcashAmount'     => (float)$r['gcash_amount'],
            'checkAmount'     => (float)$r['check_amount'],
        ];
        $grandTotal += (float)$r['total_amount'];
        $grandCount += (int)$r['transaction_count'];
    }

    // Payment method breakdown
    $methodRes = $conn->query("
        SELECT payment_method,
               COUNT(*) AS txn_count,
               SUM(amount) AS total
        FROM installment_payments
        WHERE DATE(created_at) BETWEEN '$fromEsc' AND '$toEsc'
        GROUP BY payment_method
    ");
    $byMethod = [];
    while ($r = $methodRes->fetch_assoc()) {
        $byMethod[] = ['method' => $r['payment_method'], 'count' => (int)$r['txn_count'], 'total' => (float)$r['total']];
    }

    // Exam period breakdown
    $periodRes = $conn->query("
        SELECT COALESCE(exam_period,'General') AS exam_period,
               COUNT(*) AS txn_count,
               SUM(amount) AS total
        FROM installment_payments
        WHERE DATE(created_at) BETWEEN '$fromEsc' AND '$toEsc'
        GROUP BY exam_period
        ORDER BY total DESC
    ");
    $byExamPeriod = [];
    while ($r = $periodRes->fetch_assoc()) {
        $byExamPeriod[] = ['period' => $r['exam_period'], 'count' => (int)$r['txn_count'], 'total' => (float)$r['total']];
    }

    // Top paying students
    $topRes = $conn->query("
        SELECT s.student_number, s.first_name, s.last_name, s.program,
               SUM(p.amount) AS total_paid,
               COUNT(p.id) AS txn_count
        FROM installment_payments p
        JOIN students s ON s.id = p.student_id
        WHERE DATE(p.created_at) BETWEEN '$fromEsc' AND '$toEsc'
        GROUP BY p.student_id
        ORDER BY total_paid DESC
        LIMIT 10
    ");
    $topStudents = [];
    while ($r = $topRes->fetch_assoc()) {
        $topStudents[] = [
            'studentNumber' => $r['student_number'],
            'name'          => $r['first_name'].' '.$r['last_name'],
            'program'       => $r['program'],
            'totalPaid'     => (float)$r['total_paid'],
            'txnCount'      => (int)$r['txn_count'],
        ];
    }

    echo json_encode([
        'success'      => true,
        'period'       => $period,
        'dateFrom'     => $from,
        'dateTo'       => $to,
        'grandTotal'   => round($grandTotal, 2),
        'grandCount'   => $grandCount,
        'breakdown'    => $breakdown,
        'byMethod'     => $byMethod,
        'byExamPeriod' => $byExamPeriod,
        'topStudents'  => $topStudents,
    ]);
}

/**
 * GET ?action=get_income_summary
 * High-level KPIs: today, this week, this month, this year + YoY comparison.
 */
function getIncomeSummary($conn) {
    $today     = date('Y-m-d');
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $weekEnd   = date('Y-m-d', strtotime('sunday this week'));
    $monthStart= date('Y-m-01');
    $monthEnd  = date('Y-m-t');
    $yearStart = date('Y-01-01');
    $yearEnd   = date('Y-12-31');
    $prevYrS   = date('Y-01-01', strtotime('-1 year'));
    $prevYrE   = date('Y-12-31', strtotime('-1 year'));

    $q = fn($from, $to) => (float)($conn->query(
        "SELECT COALESCE(SUM(amount),0) AS t FROM installment_payments WHERE DATE(created_at) BETWEEN '$from' AND '$to'"
    )->fetch_assoc()['t'] ?? 0);

    $todayAmt   = $q($today, $today);
    $weekAmt    = $q($weekStart, $weekEnd);
    $monthAmt   = $q($monthStart, $monthEnd);
    $yearAmt    = $q($yearStart, $yearEnd);
    $prevYrAmt  = $q($prevYrS, $prevYrE);

    $yoyChange  = $prevYrAmt > 0 ? round(($yearAmt - $prevYrAmt) / $prevYrAmt * 100, 1) : null;

    // Monthly trend for current year (for sparkline)
    $trendRes = $conn->query("
        SELECT MONTH(created_at) AS m, MONTHNAME(created_at) AS label, SUM(amount) AS total
        FROM installment_payments
        WHERE YEAR(created_at) = YEAR(CURDATE())
        GROUP BY MONTH(created_at)
        ORDER BY m ASC
    ");
    $monthlyTrend = [];
    while ($r = $trendRes->fetch_assoc()) {
        $monthlyTrend[] = ['month' => (int)$r['m'], 'label' => $r['label'], 'total' => (float)$r['total']];
    }

    // Outstanding balances
    $outstandingRes = $conn->query("
        SELECT COALESCE(SUM(
            GREATEST(0, tf.total_assessment - COALESCE(paid.total_paid, 0))
        ), 0) AS outstanding
        FROM tuition_fees tf
        JOIN students s ON s.id = tf.student_id AND s.enrollment_status = 'Enrolled'
        LEFT JOIN (
            SELECT student_id, SUM(amount) AS total_paid
            FROM installment_payments
            GROUP BY student_id
        ) paid ON paid.student_id = tf.student_id
    ");
    $outstanding = (float)($outstandingRes->fetch_assoc()['outstanding'] ?? 0);

    // Total enrolled students with fees
    $enrolledCount = (int)$conn->query("SELECT COUNT(*) AS c FROM students WHERE enrollment_status='Enrolled'")->fetch_assoc()['c'];

    echo json_encode([
        'success'       => true,
        'today'         => round($todayAmt, 2),
        'thisWeek'      => round($weekAmt, 2),
        'thisMonth'     => round($monthAmt, 2),
        'thisYear'      => round($yearAmt, 2),
        'prevYear'      => round($prevYrAmt, 2),
        'yoyChange'     => $yoyChange,
        'outstanding'   => round($outstanding, 2),
        'enrolledCount' => $enrolledCount,
        'monthlyTrend'  => $monthlyTrend,
    ]);
}

/**
 * GET ?action=get_income_by_program&period=monthly&year=YYYY&month=MM
 * Income grouped by student program.
 */
function getIncomeByProgram($conn) {
    $year  = (int)($_GET['year']  ?? date('Y'));
    $month = (int)($_GET['month'] ?? 0);

    $dateFilter = $month
        ? "YEAR(p.created_at)=$year AND MONTH(p.created_at)=$month"
        : "YEAR(p.created_at)=$year";

    $res = $conn->query("
        SELECT s.program,
               COUNT(DISTINCT p.student_id) AS student_count,
               COUNT(p.id) AS txn_count,
               SUM(p.amount) AS total_amount
        FROM installment_payments p
        JOIN students s ON s.id = p.student_id
        WHERE $dateFilter
        GROUP BY s.program
        ORDER BY total_amount DESC
    ");

    $programs = [];
    $grandTotal = 0;
    while ($r = $res->fetch_assoc()) {
        $programs[]  = [
            'program'      => $r['program'] ?: 'Unassigned',
            'studentCount' => (int)$r['student_count'],
            'txnCount'     => (int)$r['txn_count'],
            'totalAmount'  => (float)$r['total_amount'],
        ];
        $grandTotal += (float)$r['total_amount'];
    }

    // Add percentage
    foreach ($programs as &$p) {
        $p['percentage'] = $grandTotal > 0 ? round($p['totalAmount'] / $grandTotal * 100, 1) : 0;
    }

    echo json_encode([
        'success'    => true,
        'year'       => $year,
        'month'      => $month,
        'grandTotal' => round($grandTotal, 2),
        'programs'   => $programs,
    ]);
}

// ─────────────────────────────────────────────────────────────
// GET PAYMENT DUE DATES
// GET ?action=get_due_dates
// Returns the installment due date ranges set by Accounting.
// Stored in sys_config as JSON under key 'payment_due_dates'.
// Public — no auth needed (SOA needs to show it to students).
// ─────────────────────────────────────────────────────────────
function getPaymentDueDates(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS sys_config (
        config_key   VARCHAR(100) PRIMARY KEY,
        config_value TEXT DEFAULT NULL,
        updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $res = $conn->query("SELECT config_value FROM sys_config WHERE config_key = 'payment_due_dates' LIMIT 1");
    $row = $res ? $res->fetch_assoc() : null;

    $defaults = [
        'downpayment' => ['label' => 'Downpayment', 'date_range' => ''],
        'prelim'      => ['label' => 'Prelim',      'date_range' => 'JANUARY 10-16, 2026'],
        'midterm'     => ['label' => 'Midterm',      'date_range' => 'FEBRUARY 9 - 14, 2026'],
        'finals'      => ['label' => 'Finals',       'date_range' => 'MARCH 30 - APRIL 4, 2026'],
    ];

    $dates = $defaults;
    if ($row && !empty($row['config_value'])) {
        $saved = json_decode($row['config_value'], true);
        if (is_array($saved)) $dates = array_merge($defaults, $saved);
    }

    echo json_encode(['success' => true, 'dueDates' => $dates]);
}

// ─────────────────────────────────────────────────────────────
// SAVE PAYMENT DUE DATES
// POST ?action=save_due_dates
// Body: { downpayment, prelim, midterm, finals }
// Each period: { label, date_range }
// Only Accounting role can call this.
// ─────────────────────────────────────────────────────────────
function savePaymentDueDates(mysqli $conn): void {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) { echo json_encode(['success' => false, 'message' => 'Invalid JSON']); return; }

    $allowed = ['downpayment', 'prelim', 'midterm', 'finals'];
    $toSave  = [];
    foreach ($allowed as $period) {
        if (isset($data[$period])) {
            $toSave[$period] = [
                'label'      => trim($data[$period]['label']      ?? ucfirst($period)),
                'date_range' => trim($data[$period]['date_range'] ?? ''),
            ];
        }
    }

    $json = $conn->real_escape_string(json_encode($toSave));
    $conn->query("INSERT INTO sys_config (config_key, config_value)
                  VALUES ('payment_due_dates', '$json')
                  ON DUPLICATE KEY UPDATE config_value = '$json', updated_at = NOW()");

    echo json_encode(['success' => true, 'message' => 'Due dates saved successfully.', 'dueDates' => $toSave]);
}

?>