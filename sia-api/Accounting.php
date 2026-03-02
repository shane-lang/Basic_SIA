<?php
error_reporting(0);
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

$conn = new mysqli('localhost', 'root', '', 'sia_db');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->connect_error]); exit();
}
$conn->set_charset("utf8mb4");

// ── Ensure required columns exist ──
$conn->query("ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS payment_method VARCHAR(20) NOT NULL DEFAULT 'GCash' AFTER student_id");
$conn->query("ALTER TABLE students     ADD COLUMN IF NOT EXISTS payment_method VARCHAR(20) NOT NULL DEFAULT 'GCash' AFTER approval_status");

// ── Ensure tuition_fees table ──
$conn->query("
  CREATE TABLE IF NOT EXISTS tuition_fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL UNIQUE,
    units INT NOT NULL DEFAULT 18,
    tuition_fee DECIMAL(10,2) NOT NULL,
    miscellaneous_fee DECIMAL(10,2) NOT NULL DEFAULT 6688.00,
    registration_fee DECIMAL(10,2) NOT NULL DEFAULT 700.00,
    laboratory_fee DECIMAL(10,2) NOT NULL,
    energy_fee DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    installment_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_assessment DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── Ensure installment_payments table ──
$conn->query("
  CREATE TABLE IF NOT EXISTS installment_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    payment_log_id INT DEFAULT NULL,
    or_ar_number VARCHAR(30) NOT NULL,
    or_ar_type ENUM('OR','AR') NOT NULL DEFAULT 'AR',
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(20) NOT NULL DEFAULT 'Cash',
    gcash_reference VARCHAR(100) DEFAULT NULL,
    exam_period ENUM('Downpayment','Prelim','Midterm','Finals','Full') NOT NULL DEFAULT 'Downpayment',
    notes TEXT DEFAULT NULL,
    recorded_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
$conn->query("
  CREATE TABLE IF NOT EXISTS payment_schedules (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    student_id     INT NOT NULL UNIQUE,
    payment_type   ENUM('full','installment') NOT NULL DEFAULT 'installment',
    total_assessment DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    prelim_due     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    midterm_due    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    finals_due     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    prelim_paid    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    midterm_paid   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    finals_paid    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    prelim_status  ENUM('locked','unpaid','partial','paid') NOT NULL DEFAULT 'locked',
    midterm_status ENUM('locked','unpaid','partial','paid') NOT NULL DEFAULT 'locked',
    finals_status  ENUM('locked','unpaid','partial','paid') NOT NULL DEFAULT 'locked',
    prelim_unlocked_at  TIMESTAMP NULL,
    midterm_unlocked_at TIMESTAMP NULL,
    finals_unlocked_at  TIMESTAMP NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->query("
  CREATE TABLE IF NOT EXISTS payment_notices (
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
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->query("
  CREATE TABLE IF NOT EXISTS exam_permits (
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
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
// Fix legacy Cash logs
$conn->query("UPDATE payment_logs SET payment_method = 'Cash' WHERE gcash_reference = 'CASH-PAYMENT' AND payment_method = 'GCash'");

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        switch ($action) {
            case 'get_pending_payments':  getPendingPayments($conn);  break;
            case 'get_payment_history':   getPaymentHistory($conn);   break;
            case 'get_tuition_fees':      getTuitionFees($conn);      break;
            case 'get_liquidation':       getLiquidation($conn);      break;
            case 'get_student_receipts':  getStudentReceipts($conn);  break;
            case 'get_fee_preview':       getFeePreview($conn);       break;
            case 'get_payment_schedule':      getPaymentSchedule($conn);      break;
            case 'get_exam_permits':          getExamPermits($conn);          break;
            case 'get_student_permit_status': getStudentPermitStatus($conn);  break;
            case 'get_payment_notices':       getPaymentNotices($conn);       break;
            case 'get_all_enrolled_students': getAllEnrolledStudents($conn); break;
            case 'get_student_installment':   getStudentInstallment($conn); break;
            case 'get_permit_details':         getPermitDetails($conn);      break;
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
            case 'record_installment':  recordInstallment($conn, $data);  break;
            case 'send_payment_notice':  sendPaymentNotice($conn, $data);  break;
            case 'request_exam_permit':  requestExamPermit($conn, $data);  break;
            case 'process_exam_permit':  processExamPermit($conn, $data);  break;
            case 'unlock_payment_period': unlockPaymentPeriod($conn, $data); break;
            case 'submit_installment_payment': submitInstallmentPayment($conn, $data); break;
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

    $pn    = $conn->real_escape_string($program_name);
    $units = 0;

    // 1. Check tuition_fees table first (already evaluated by registrar for transferees)
    if ($student_id > 0) {
        $tf_res = $conn->query("SELECT units FROM tuition_fees WHERE student_id = $student_id LIMIT 1");
        $tf_row = $tf_res ? $tf_res->fetch_assoc() : null;
        if ($tf_row && (int)$tf_row['units'] > 0) {
            $units = (int)$tf_row['units'];
        }
    }

    // 1b. If still zero — check tor_evaluations.approved_units (permanent across all semesters)
    if ($units <= 0 && $student_id > 0) {
        $te = $conn->query("SELECT approved_units FROM tor_evaluations WHERE student_id = $student_id AND status = 'Evaluated' LIMIT 1");
        $te_row = $te ? $te->fetch_assoc() : null;
        if ($te_row && (int)$te_row['approved_units'] > 0) {
            $units = (int)$te_row['approved_units'];
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

    // Count lab subjects — filtered by year_level + semester (same scope as unit count).
    // Without these filters, ALL lab courses across ALL years inflate the lab fee.
    $lab_count = 0;
    if ($program_name) {
        $lab_res = $conn->query("
            SELECT COUNT(DISTINCT c.id) AS lab_cnt
            FROM courses c
            WHERE c.room LIKE '%Lab%'
              $ylFilter
              $semFilter
              AND (
                c.program = '$pn'
                OR c.id IN (
                    SELECT pc.course_id FROM program_courses pc
                    JOIN programs p ON pc.program_id = p.id
                    WHERE p.name = '$pn' OR p.code = '$pn'
                )
              )
        ");
        $lab_count = (int)(($lab_res ? $lab_res->fetch_assoc()['lab_cnt'] : 0) ?? 0);
    }

    // Fee computation
    $tuition_fee    = $units * 650;
    $miscellaneous  = 6688.00;
    $registration   = 700.00;
    $laboratory_fee = $lab_count * 1900;   // number of lab subjects × 1,900
    $energy_fee     = $units * 21 * 3;
    $subtotal       = $tuition_fee + $miscellaneous + $registration + $laboratory_fee + $energy_fee;
    $installment_fee = $has_installment ? 750.00 : 0.00;
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
            $tuition_fee    = $units * 650;
            $energy_fee     = $units * 21 * 3;
            $subtotal       = $tuition_fee + $miscellaneous + $registration + $laboratory_fee + $energy_fee;
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
    $lab_count = 0;
    if ($prog_name) {
        $lab_res = $conn->query("
            SELECT COUNT(DISTINCT c.id) AS lab_cnt
            FROM courses c
            WHERE c.room LIKE '%Lab%'
              AND (
                c.program = '$prog_name'
                OR c.id IN (
                    SELECT pc.course_id FROM program_courses pc
                    JOIN programs p ON pc.program_id = p.id
                    WHERE p.name = '$prog_name' OR p.code = '$prog_name'
                )
              )
        ");
        $lab_count = (int)(($lab_res ? $lab_res->fetch_assoc()['lab_cnt'] : 0) ?? 0);
    }

    $tuition_fee    = $units * 650;
    $miscellaneous  = 6688.00;
    $registration   = 700.00;
    $laboratory_fee = $lab_count * 1900;   // number of lab subjects × 1,900
    $energy_fee     = $units * 21 * 3;
    $subtotal       = $tuition_fee + $miscellaneous + $registration + $laboratory_fee + $energy_fee;
    $installment_fee = $has_installment ? 750.00 : 0.00;
    $total          = $subtotal - $discount + $installment_fee;

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
            'subtotal'         => $subtotal,
            'discount'         => $discount,
            'installmentFee'   => $installment_fee,
            'totalAssessment'  => $total,
        ]
    ]);
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

    echo json_encode([
        'success' => true,
        'fees' => [
            'units'            => (int)$row['units'],
            'tuitionFee'       => (float)$row['tuition_fee'],
            'miscellaneousFee' => (float)$row['miscellaneous_fee'],
            'registrationFee'  => (float)$row['registration_fee'],
            'laboratoryFee'    => (float)$row['laboratory_fee'],
            'energyFee'        => (float)$row['energy_fee'],
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
    $year      = date('Y');
    $count_res = $conn->query("SELECT COUNT(*) AS cnt FROM installment_payments WHERE YEAR(created_at) = $year");
    $count     = (int)($count_res->fetch_assoc()['cnt'] ?? 0) + 1;
    $or_ar_no  = $or_ar_type . '-' . $year . str_pad($count, 4, '0', STR_PAD_LEFT);

    $stmt = $conn->prepare("
        INSERT INTO installment_payments (student_id, or_ar_number, or_ar_type, amount, payment_date, payment_method, gcash_reference, exam_period, notes, recorded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issdsssssi", $student_id, $or_ar_no, $or_ar_type, $amount, $payment_date, $payment_method, $gcash_ref, $exam_period, $notes, $acc_user_id);
    $stmt->execute();

    // Check if fully paid
    $fee_res          = $conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id = $student_id LIMIT 1");
    $fee_row          = $fee_res ? $fee_res->fetch_assoc() : null;
    $total_assessment = (float)($fee_row['total_assessment'] ?? 0);

    $paid_res   = $conn->query("SELECT COALESCE(SUM(amount),0) AS tp FROM installment_payments WHERE student_id = $student_id");
    $total_paid = (float)($paid_res->fetch_assoc()['tp'] ?? 0);
    $is_fully_paid = $total_assessment > 0 && $total_paid >= $total_assessment;

    $pay_status = $is_fully_paid ? 'Paid' : 'Pending';
    $conn->query("UPDATE students SET payment_status = '$pay_status' WHERE id = $student_id");

    // ── BUG FIX: Sync payment_schedules after recording payment ──────────
    // After each installment, recalculate how much was paid per period and
    // update the status so the student's Payment Schedule page reflects it.
    $sched_check = $conn->query("SELECT id FROM payment_schedules WHERE student_id = $student_id LIMIT 1");
    if ($sched_check && $sched_check->num_rows > 0) {
        foreach (['Prelim', 'Midterm', 'Finals'] as $period) {
            $p = strtolower($period);
            // Only update non-locked periods
            $lock_check = $conn->query("SELECT {$p}_status, {$p}_due FROM payment_schedules WHERE student_id = $student_id LIMIT 1");
            $lock_row = $lock_check ? $lock_check->fetch_assoc() : null;
            if (!$lock_row || $lock_row[$p.'_status'] === 'locked') continue;

            $period_paid_res = $conn->query("SELECT COALESCE(SUM(amount),0) AS paid FROM installment_payments WHERE student_id = $student_id AND exam_period = '$period'");
            $period_paid = (float)($period_paid_res->fetch_assoc()['paid'] ?? 0);
            $period_due  = (float)($lock_row[$p.'_due'] ?? 0);
            $new_status  = $period_paid <= 0 ? 'unpaid' : ($period_paid >= $period_due ? 'paid' : 'partial');
            $conn->query("UPDATE payment_schedules SET {$p}_paid = $period_paid, {$p}_status = '$new_status' WHERE student_id = $student_id");
        }
    }
    // ─────────────────────────────────────────────────────────────────────

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

    // Self-heal fee_row: recompute lab_count and fix fee_row if lab_fee looks wrong (units × 1900)
    if ($fee_row && $student) {
        $pn_esc  = $conn->real_escape_string(trim($student['program'] ?? ''));
        $lab_res = $conn->query("
            SELECT COUNT(DISTINCT c.id) AS cnt FROM courses c
            WHERE c.room LIKE '%Lab%'
              AND (c.program = '$pn_esc'
                OR c.id IN (SELECT pc.course_id FROM program_courses pc JOIN programs p ON pc.program_id=p.id WHERE p.name='$pn_esc' OR p.code='$pn_esc'))
        ");
        $lab_cnt = (int)(($lab_res ? $lab_res->fetch_assoc()['cnt'] : 0) ?? 0);
        $correct_lab_fee  = $lab_cnt * 1900;
        $correct_inst_fee = ($paymentPlan === 'installment') ? 750.00 : 0.00;
        $stored_units     = (int)$fee_row['units'];
        $wrong_lab_fee    = $stored_units * 1900; // old formula

        // Fix if lab_fee equals old wrong formula AND differs from correct value
        $needs_fix = (abs((float)$fee_row['laboratory_fee'] - $correct_lab_fee) > 0.01)
                  || (abs((float)$fee_row['installment_fee'] - $correct_inst_fee) > 0.01);

        if ($needs_fix) {
            $new_subtotal    = (float)$fee_row['tuition_fee'] + (float)$fee_row['miscellaneous_fee']
                             + (float)$fee_row['registration_fee'] + $correct_lab_fee + (float)$fee_row['energy_fee'];
            $new_total       = max(0, $new_subtotal - (float)$fee_row['discount'] + $correct_inst_fee);
            $conn->query("UPDATE tuition_fees SET laboratory_fee=$correct_lab_fee, installment_fee=$correct_inst_fee, subtotal=$new_subtotal, total_assessment=$new_total, updated_at=NOW() WHERE student_id=$student_id");
            // Reload
            $fee_res2 = $conn->query("SELECT * FROM tuition_fees WHERE student_id = $student_id LIMIT 1");
            $fee_row  = $fee_res2 ? $fee_res2->fetch_assoc() : null;
        }
    }

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
            WHERE c.room LIKE '%Lab%'
              AND (c.program = '$pn_esc'
                OR c.id IN (SELECT pc.course_id FROM program_courses pc JOIN programs p ON pc.program_id=p.id WHERE p.name='$pn_esc' OR p.code='$pn_esc'))
        ");
        $lab_cnt = (int)(($lab_res ? $lab_res->fetch_assoc()['cnt'] : 0) ?? 0);

        // Compute fees
        $tuition       = $units * 650;
        $miscellaneous = 6688;
        $registration  = 700;
        $laboratory    = $lab_cnt * 1900;   // number of lab subjects × 1,900
        $energy        = $units * 21 * 3;
        $subtotal      = $tuition + $miscellaneous + $registration + $laboratory + $energy;

        $disc_res  = $conn->query("SELECT is_scholar, scholarship_amount FROM students WHERE id = $student_id LIMIT 1");
        $disc_row  = $disc_res ? $disc_res->fetch_assoc() : null;
        $discount  = ($disc_row && $disc_row['is_scholar']) ? (float)($disc_row['scholarship_amount'] ?? 0) : 0;

        $install_fee = $has_installment ? 750.00 : 0.00;
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

    $checkStmt = $conn->prepare("SELECT id FROM payment_logs WHERE student_id = ? AND status = 'Pending' LIMIT 1");
    $checkStmt->bind_param("i", $student_id);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();

    if ($existing) {
        $upd = $conn->prepare("
            UPDATE payment_logs
            SET payment_method = 'GCash', gcash_reference = ?, gcash_amount = ?,
                gcash_date = ?, transaction_id = ?, semester = ?
            WHERE id = ?
        ");
        $upd->bind_param("sdsssi", $reference, $amount, $date, $txn_id, $semester, $existing['id']);
        $upd->execute();
        $log_id = $existing['id'];
    } else {
        $ins = $conn->prepare("
            INSERT INTO payment_logs (student_id, payment_method, gcash_reference, gcash_amount, gcash_date, transaction_id, semester, status)
            VALUES (?, 'GCash', ?, ?, ?, ?, ?, 'Pending')
        ");
        $ins->bind_param("isdsss", $student_id, $reference, $amount, $date, $txn_id, $semester);
        $ins->execute();
        $log_id = $ins->insert_id;
    }

    echo json_encode(['success' => true, 'message' => 'GCash payment submitted. Waiting for accounting verification.', 'log_id' => $log_id]);
}

// ─────────────────────────────────────────────────────────────
// ACCOUNTING: Get pending payments
// ─────────────────────────────────────────────────────────────
function getPendingPayments($conn) {
    $rows = [];

    // Fetch ALL pending payment_logs — includes both enrollment payments
    // AND installment term payments (Prelim/Midterm/Finals) from enrolled students
    $sql = "
        SELECT pl.id AS log_id, pl.student_id, pl.payment_method, pl.gcash_reference,
               pl.gcash_amount, pl.gcash_date, pl.transaction_id, pl.semester,
               pl.notes, pl.created_at AS submitted_at,
               s.student_number, s.first_name, s.last_name, s.program, s.year_level,
               s.payment_status, s.approval_status, s.enrollment_status,
               tf.total_assessment
        FROM payment_logs pl
        JOIN students s ON pl.student_id = s.id
        LEFT JOIN tuition_fees tf ON tf.student_id = s.id
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

            // Parse exam_period from notes field (format: "Prelim|notes" or "Midterm|notes")
            $notesRaw   = $r['notes'] ?? '';
            $examPeriod = '';
            if (preg_match('/^(Prelim|Midterm|Finals|Downpayment|Full)\|?/i', $notesRaw, $m)) {
                $examPeriod = $m[1];
                $notesRaw   = trim(substr($notesRaw, strlen($m[0])));
            }

            $rows[] = [
                'logId'          => (int)$r['log_id'],
                'studentId'      => $sid,
                'studentNumber'  => $r['student_number'],
                'firstName'      => $r['first_name'],
                'lastName'       => $r['last_name'],
                'program'        => $r['program'],
                'yearLevel'      => $r['year_level'],
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
            ];
        }
    }

    // Also show students with no payment_log (Cash pending enrollment, not yet enrolled)
    $noLogSql = "
        SELECT s.id AS student_id, s.student_number, s.first_name, s.last_name,
               s.program, s.year_level, s.payment_status, s.approval_status,
               s.payment_method, s.semester, s.created_at AS submitted_at,
               tf.total_assessment
        FROM students s
        LEFT JOIN payment_logs pl ON pl.student_id = s.id AND pl.status = 'Pending'
        LEFT JOIN tuition_fees tf ON tf.student_id = s.id
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
            $ins = $conn->prepare("INSERT INTO payment_logs (student_id, payment_method, gcash_reference, gcash_amount, semester, status) VALUES (?, 'Cash', '', 0, ?, 'Pending')");
            $ins->bind_param("is", $sid, $semester);
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
                'enrollmentStatus'=> 'Pending',
                'paymentMethod'  => 'Cash',
                'gcashReference' => '', 'gcashAmount' => 0, 'gcashDate' => '', 'transactionId' => '',
                'semester'       => $semester,
                'examPeriod'     => '',
                'notes'          => '',
                'status'         => 'Pending',
                'submittedAt'    => $r['submitted_at'],
                'paymentStatus'  => $r['payment_status'],
                'approvalStatus' => $r['approval_status'],
                'totalAssessment'=> (float)($r['total_assessment'] ?? 0),
                'totalPaid'      => $total_paid,
                'balance'        => max(0, (float)($r['total_assessment'] ?? 0) - $total_paid),
            ];
        }
    }

    echo json_encode(['success' => true, 'payments' => $rows]);
}

// ─────────────────────────────────────────────────────────────
// ACCOUNTING: Payment history
// ─────────────────────────────────────────────────────────────
function getPaymentHistory($conn) {
    $result = $conn->query("
        SELECT pl.id AS log_id, pl.student_id, pl.payment_method, pl.gcash_reference,
               pl.gcash_amount, pl.gcash_date, pl.transaction_id, pl.semester, pl.status,
               pl.notes, pl.verified_at, pl.created_at AS submitted_at,
               s.student_number, s.first_name, s.last_name, s.program, s.year_level,
               u.first_name AS verified_by_fname, u.last_name AS verified_by_lname, tf.total_assessment
        FROM payment_logs pl
        JOIN students s ON pl.student_id = s.id
        LEFT JOIN users u ON pl.verified_by = u.id
        LEFT JOIN tuition_fees tf ON tf.student_id = s.id
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
                'paymentMethod'  => $isCash ? 'Cash' : 'GCash',
                'gcashReference' => $isCash ? '' : ($r['gcash_reference'] ?? ''),
                'gcashAmount'    => (float)($r['gcash_amount'] ?? 0),
                'gcashDate'      => $isCash ? ($r['verified_at'] ? date('Y-m-d', strtotime($r['verified_at'])) : '') : ($r['gcash_date'] ?? ''),
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

    // Read original notes + amount BEFORE updating (notes will be overwritten by accounting notes)
    $logRow = $conn->query("SELECT gcash_amount, gcash_date, payment_method, notes, status FROM payment_logs WHERE id = $log_id LIMIT 1")->fetch_assoc();
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
    if ($stmt->affected_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Failed to update payment log']); return;
    }

    // Use pre-UPDATE logRow (has original notes with exam_period prefix)
    $final_amount = ($payment_method === 'cash') ? ($cash_amount ?? 0) : (float)($logRow['gcash_amount'] ?? 0);
    $final_date   = ($payment_method === 'cash') ? $cash_date : ($logRow['gcash_date'] ?? date('Y-m-d'));
    $pm_label     = ($payment_method === 'cash') ? 'Cash' : 'GCash';

    // Get student payment plan
    $stRow       = $conn->query("SELECT payment_plan, enrollment_status FROM students WHERE id = $student_id LIMIT 1")->fetch_assoc();
    $paymentPlan = $stRow['payment_plan'] ?? 'full';
    $isEnrolled  = ($stRow['enrollment_status'] ?? '') === 'Enrolled';

    // Parse exam_period from ORIGINAL notes (before accounting overwrote it)
    $notesRaw   = trim($originalNotes ?? $logRow['notes'] ?? '');
    $examPeriod = '';
    if (preg_match('/^(Prelim|Midterm|Finals|Downpayment|Full)\|?/i', $notesRaw, $m)) {
        $examPeriod = $m[1];
        $notes      = $notes ?: trim(substr($notesRaw, strlen($m[0])));
    }

    // Auto-create installment_payments record if not already done
    // Avoid duplicates via payment_log_id check
    $dupCheck = $conn->prepare("SELECT id FROM installment_payments WHERE payment_log_id = ? LIMIT 1");
    $dupCheck->bind_param("i", $log_id);
    $dupCheck->execute();
    if ($final_amount > 0 && $dupCheck->get_result()->num_rows === 0) {
        $year      = date('Y');
        $count_res = $conn->query("SELECT COUNT(*) AS cnt FROM installment_payments WHERE YEAR(created_at) = $year");
        $count     = (int)($count_res->fetch_assoc()['cnt'] ?? 0) + 1;

        if ($isEnrolled && $examPeriod && in_array($examPeriod, ['Prelim','Midterm','Finals'])) {
            // Installment term payment (Prelim/Midterm/Finals) from enrolled student
            $or_ar_type = 'AR';
            $or_no      = 'AR-' . $year . str_pad($count, 4, '0', STR_PAD_LEFT);
        } elseif ($paymentPlan === 'installment') {
            // Initial downpayment AR
            $or_ar_type  = 'AR';
            $examPeriod  = $examPeriod ?: 'Downpayment';
            $or_no       = 'AR-' . $year . str_pad($count, 4, '0', STR_PAD_LEFT);
        } else {
            // Full payment OR
            $or_ar_type  = 'OR';
            $examPeriod  = $examPeriod ?: 'Full';
            $or_no       = 'OR-' . $year . str_pad($count, 4, '0', STR_PAD_LEFT);
        }

        $gcash_ref = ($payment_method !== 'cash') ? ($conn->query("SELECT gcash_reference FROM payment_logs WHERE id = $log_id LIMIT 1")->fetch_assoc()['gcash_reference'] ?? '') : '';

        $ins = $conn->prepare("
            INSERT INTO installment_payments
                (student_id, payment_log_id, or_ar_number, or_ar_type, amount, payment_date, payment_method, gcash_reference, exam_period, notes, recorded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->bind_param("iissdsssssi", $student_id, $log_id, $or_no, $or_ar_type, $final_amount, $final_date, $pm_label, $gcash_ref, $examPeriod, $notes, $acc_user_id);
        $ins->execute();

        // Sync payment_schedules for this period
        if ($isEnrolled && in_array($examPeriod, ['Prelim','Midterm','Finals'])) {
            $ep = strtolower($examPeriod);
            $schedRes = $conn->query("SELECT {$ep}_due FROM payment_schedules WHERE student_id=$student_id LIMIT 1");
            $schedRow = $schedRes ? $schedRes->fetch_assoc() : null;
            $periodDue = $schedRow ? (float)$schedRow[$ep.'_due'] : 0;
            $paidRes   = $conn->query("SELECT COALESCE(SUM(amount),0) AS paid FROM installment_payments WHERE student_id=$student_id AND exam_period='$examPeriod'");
            $periodPaid = (float)$paidRes->fetch_assoc()['paid'];
            $newStatus  = $periodPaid <= 0 ? 'unpaid' : ($periodPaid >= $periodDue ? 'paid' : 'partial');
            $conn->query("UPDATE payment_schedules SET {$ep}_paid=$periodPaid, {$ep}_status='$newStatus' WHERE student_id=$student_id");

            // Don't change enrollment/approval status for term payments — student already enrolled
            echo json_encode(['success' => true, 'message' => "$examPeriod payment verified. ₱" . number_format($final_amount, 2) . " recorded."]);
            return;
        }
    }

    $upd = $conn->prepare("UPDATE students SET payment_status='Paid', approval_status='Approved', enrollment_status='Enrolled', accounting_approved_by=?, accounting_approved_at=NOW(), accounting_notes=? WHERE id=?");
    $upd->bind_param("isi", $acc_user_id, $notes, $student_id);
    $upd->execute();

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

    echo json_encode(['success' => true, 'message' => 'Payment rejected.']);
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
    $pd    = $total > 0 ? round($total / 4, 2) : 0;
    $md    = $pd;
    $fd    = $total > 0 ? round($total - $pd - $md * 2, 2) : 0;

    // Always ensure a payment_schedules row exists (even if total=0 for now)
    $conn->query("INSERT INTO payment_schedules
        (student_id,payment_type,total_assessment,prelim_due,midterm_due,finals_due)
        VALUES ($student_id,'$ptype',$total,$pd,$md,$fd)
        ON DUPLICATE KEY UPDATE
          total_assessment=IF(total_assessment=0 AND $total>0,$total,total_assessment),
          payment_type='$ptype',
          prelim_due=IF($pd>0 AND ABS(prelim_due - $pd) > 100, $pd, prelim_due),
          midterm_due=IF($md>0 AND ABS(midterm_due - $md) > 100, $md, midterm_due),
          finals_due=IF($fd>0 AND ABS(finals_due - $fd) > 100, $fd, finals_due)");

    $res = $conn->query("SELECT * FROM payment_schedules WHERE student_id=$student_id LIMIT 1");
    $schedule = $res ? $res->fetch_assoc() : null;
    if (!$schedule) { echo json_encode(['success'=>true,'schedule'=>null]); return; }

    foreach (['Prelim','Midterm','Finals'] as $period) {
        $p = strtolower($period);
        if ($schedule[$p.'_status'] === 'locked') continue;
        $paidRes = $conn->query("SELECT COALESCE(SUM(amount),0) AS paid
            FROM installment_payments WHERE student_id=$student_id AND exam_period='$period'");
        $paid = $paidRes ? (float)$paidRes->fetch_assoc()['paid'] : 0;
        $due  = (float)$schedule[$p.'_due'];
        $status = $paid <= 0 ? 'unpaid' : ($paid >= $due ? 'paid' : 'partial');
        $conn->query("UPDATE payment_schedules SET {$p}_paid=$paid,{$p}_status='$status' WHERE student_id=$student_id");
        $schedule[$p.'_paid']   = $paid;
        $schedule[$p.'_status'] = $status;
    }

    if ($schedule['payment_type'] === 'full') {
        $stPayRes = $conn->query("SELECT payment_status FROM students WHERE id=$student_id LIMIT 1");
        $stPayRow = $stPayRes ? $stPayRes->fetch_assoc() : null;
        $isFullyPaid = ($stPayRow && $stPayRow['payment_status'] === 'Paid');
        if (!$isFullyPaid) {
            $tpRes = $conn->query("SELECT COALESCE(SUM(amount),0) AS tp FROM installment_payments WHERE student_id=$student_id");
            $tp = $tpRes ? (float)$tpRes->fetch_assoc()['tp'] : 0;
            $isFullyPaid = ($schedule['total_assessment'] > 0 && $tp >= (float)$schedule['total_assessment']);
        }
        if ($isFullyPaid) {
            $schedule['prelim_status']  = 'paid';
            $schedule['midterm_status'] = 'paid';
            $schedule['finals_status']  = 'paid';
            $conn->query("UPDATE payment_schedules SET prelim_status='paid',midterm_status='paid',finals_status='paid' WHERE student_id=$student_id");
        }
    }

    // Add downpayment_paid for accurate total balance display
    $dpRes = $conn->query("SELECT COALESCE(SUM(amount),0) AS dp FROM installment_payments WHERE student_id=$student_id AND exam_period='Downpayment'");
    $schedule['downpayment_paid'] = $dpRes ? (float)$dpRes->fetch_assoc()['dp'] : 0;

    $noticeRes = $conn->query("SELECT exam_period, amount_due, due_date, message, sent_at, is_read
        FROM payment_notices WHERE student_id=$student_id");
    $notices = [];
    while ($row = $noticeRes->fetch_assoc()) $notices[$row['exam_period']] = $row;

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
        $pd = round($total/4,2); $md = round($total/4,2); $fd = round($total-$pd-$md*2,2);
        $conn->query("INSERT INTO payment_schedules
            (student_id,payment_type,total_assessment,prelim_due,midterm_due,finals_due,{$p}_status,{$unlocked_col})
            VALUES ($student_id,'installment',$total,$pd,$md,$fd,'unpaid',NOW())");
    }

    echo json_encode(['success'=>true,'message'=>"$exam_period notice sent. Payment period is now unlocked for the student."]);
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
        if ($paid < $due) {
            $bal = number_format($due - $paid, 2);
            echo json_encode(['success'=>false,'message'=>"$exam_period balance ₱$bal must be paid first."]); return;
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
        'enrollmentStatus'=> $row['enrollment_status'] ?? '',
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
    $check = $conn->query("SELECT id FROM payment_schedules WHERE student_id = $student_id LIMIT 1");
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

    // ── BUG FIX: Write a payment_notice so student sees the unlocked period ──
    // Without this, the student's Payment Schedule page stays blank after unlock
    // because it checks payment_notices to know which periods are active.
    $schedRes = $conn->query("SELECT {$p}_due FROM payment_schedules WHERE student_id = $student_id LIMIT 1");
    $schedRow = $schedRes ? $schedRes->fetch_assoc() : null;
    $due_amt  = $schedRow ? (float)$schedRow[$p.'_due'] : 0;
    if ($due_amt <= 0) {
        $tfRes2 = $conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id = $student_id LIMIT 1");
        $tfRow2 = $tfRes2 ? $tfRes2->fetch_assoc() : null;
        $total2 = $tfRow2 ? (float)$tfRow2['total_assessment'] : 0;
        $due_amt = round($total2 / 4, 2);
    }
    $msg_esc = $conn->real_escape_string("Your $exam_period payment of ₱" . number_format($due_amt, 2) . " has been unlocked. Please settle at the Accounting office.");
    $conn->query("INSERT INTO payment_notices (student_id, exam_period, amount_due, message, sent_by)
        VALUES ($student_id, '$exam_period', $due_amt, '$msg_esc', $acc_user_id)
        ON DUPLICATE KEY UPDATE
            amount_due = $due_amt,
            message    = '$msg_esc',
            sent_by    = $acc_user_id,
            sent_at    = NOW(),
            is_read    = 0");
    // ─────────────────────────────────────────────────────────────────────

    echo json_encode([
        'success' => true,
        'message' => "$exam_period payment period has been unlocked.",
    ]);
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
            $courses[] = ['code' => $r['code'], 'name' => $r['name'], 'instructor' => $r['instructor']];
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
                $courses[] = ['code' => $r['code'], 'name' => $r['name'], 'instructor' => $r['instructor']];
            }
        }
    }

    $permit['courses'] = $courses;
    unset($permit['raw_semester']); // clean up internal field

    echo json_encode(['success' => true, 'permit' => $permit]);
}
?>