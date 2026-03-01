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
    $program_name = trim($_GET['program'] ?? '');
    $student_id   = (int)($_GET['student_id'] ?? 0);
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

    // 2. Sum from program_courses → programs → courses
    if ($units <= 0) {
        $units_res = $conn->query("
            SELECT COALESCE(SUM(c.credits), 0) AS total_units
            FROM program_courses pc
            JOIN programs p ON pc.program_id = p.id
            JOIN courses  c ON pc.course_id  = c.id
            WHERE p.name = '$pn' OR p.code = '$pn'
        ");
        $units_row = $units_res ? $units_res->fetch_assoc() : null;
        $units     = (int)($units_row['total_units'] ?? 0);
    }

    // 3. Fallback: courses.program column
    if ($units <= 0) {
        $fb     = $conn->query("SELECT COALESCE(SUM(credits),0) AS total_units FROM courses WHERE program = '$pn'");
        $fb_row = $fb ? $fb->fetch_assoc() : null;
        $units  = (int)($fb_row['total_units'] ?? 0);
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

    // Count lab subjects for this program (room name contains 'Lab')
    $lab_count = 0;
    if ($program_name) {
        $pn_esc = $conn->real_escape_string($program_name);
        $lab_res = $conn->query("
            SELECT COUNT(*) AS lab_cnt
            FROM program_courses pc
            JOIN programs p ON pc.program_id = p.id
            JOIN courses  c ON pc.course_id  = c.id
            WHERE (p.name = '$pn_esc' OR p.code = '$pn_esc')
              AND c.room LIKE '%Lab%'
        ");
        $lab_row  = $lab_res ? $lab_res->fetch_assoc() : null;
        $lab_count = (int)($lab_row['lab_cnt'] ?? 0);
        // Fallback: check courses.program column
        if ($lab_count === 0) {
            $fb_lab = $conn->query("SELECT COUNT(*) AS lab_cnt FROM courses WHERE program = '$pn_esc' AND room LIKE '%Lab%'");
            $fb_row = $fb_lab ? $fb_lab->fetch_assoc() : null;
            $lab_count = (int)($fb_row['lab_cnt'] ?? 0);
        }
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

    // Save / update tuition_fees if student_id provided
    if ($student_id > 0) {
        $stmt = $conn->prepare("
            INSERT INTO tuition_fees
                (student_id, units, tuition_fee, miscellaneous_fee, registration_fee,
                 laboratory_fee, energy_fee, subtotal, discount, installment_fee, total_assessment)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                tuition_fee=VALUES(tuition_fee), miscellaneous_fee=VALUES(miscellaneous_fee),
                registration_fee=VALUES(registration_fee), laboratory_fee=VALUES(laboratory_fee),
                energy_fee=VALUES(energy_fee), subtotal=VALUES(subtotal),
                discount=VALUES(discount), installment_fee=VALUES(installment_fee),
                total_assessment=VALUES(total_assessment)
        ");
        // Note: units NOT overwritten in ON DUPLICATE KEY — preserves registrar-set units for transferees
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
            SELECT COUNT(*) AS lab_cnt
            FROM program_courses pc
            JOIN programs p ON pc.program_id = p.id
            JOIN courses  c ON pc.course_id  = c.id
            WHERE (p.name = '$prog_name' OR p.code = '$prog_name')
              AND c.room LIKE '%Lab%'
        ");
        $lab_row   = $lab_res ? $lab_res->fetch_assoc() : null;
        $lab_count = (int)($lab_row['lab_cnt'] ?? 0);
        if ($lab_count === 0) {
            $fb_lab = $conn->query("SELECT COUNT(*) AS lab_cnt FROM courses WHERE program = '$prog_name' AND room LIKE '%Lab%'");
            $lab_count = (int)(($fb_lab ? $fb_lab->fetch_assoc()['lab_cnt'] : 0) ?? 0);
        }
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

        // Compute fees
        $tuition       = $units * 650;
        $miscellaneous = 6688;
        $registration  = 700;
        $laboratory    = $units * 1900;
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

    $sql = "
        SELECT pl.id AS log_id, pl.student_id, pl.payment_method, pl.gcash_reference,
               pl.gcash_amount, pl.gcash_date, pl.transaction_id, pl.semester, pl.created_at AS submitted_at,
               s.student_number, s.first_name, s.last_name, s.program, s.year_level,
               s.payment_status, s.approval_status,
               tf.total_assessment, tf.units
        FROM payment_logs pl
        JOIN students s ON pl.student_id = s.id
        LEFT JOIN tuition_fees tf ON tf.student_id = s.id
        WHERE pl.status = 'Pending'
          AND s.enrollment_status != 'Enrolled'
        ORDER BY pl.created_at DESC
    ";

    $result = $conn->query($sql);
    if ($result) {
        while ($r = $result->fetch_assoc()) {
            $isCash  = strtolower($r['payment_method'] ?? '') === 'cash' || ($r['gcash_reference'] ?? '') === 'CASH-PAYMENT';
            $sid     = (int)$r['student_id'];
            $pr      = $conn->query("SELECT COALESCE(SUM(amount),0) AS tp FROM installment_payments WHERE student_id = $sid");
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
                'gcashAmount'    => $isCash ? 0 : (float)($r['gcash_amount'] ?? 0),
                'gcashDate'      => $isCash ? '' : ($r['gcash_date'] ?? ''),
                'transactionId'  => $isCash ? '' : ($r['transaction_id'] ?? ''),
                'semester'       => $r['semester'] ?? '',
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

    // Students (Cash OR GCash) with no payment_log yet — show them so accounting can see
    $noLogSql = "
        SELECT s.id AS student_id, s.student_number, s.first_name, s.last_name,
               s.program, s.year_level, s.payment_status, s.approval_status,
               s.payment_method, s.created_at AS submitted_at,
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

            $semester = '1st Semester, AY 2024-2025';
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
                'paymentMethod'  => 'Cash',
                'gcashReference' => '', 'gcashAmount' => 0, 'gcashDate' => '', 'transactionId' => '',
                'semester'       => $semester,
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
               u.first_name AS verified_by_name, tf.total_assessment
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
                'gcashDate'      => $isCash ? '' : ($r['gcash_date'] ?? ''),
                'transactionId'  => $isCash ? '' : ($r['transaction_id'] ?? ''),
                'semester'       => $r['semester'],
                'status'         => $r['status'],
                'notes'          => $r['notes'],
                'verifiedAt'     => $r['verified_at'],
                'verifiedByName' => $r['verified_by_name'],
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

    // Update payment log
    if ($payment_method === 'cash' && $cash_amount !== null) {
        $stmt = $conn->prepare("UPDATE payment_logs SET status = 'Verified', verified_by = ?, verified_at = NOW(), notes = ?, gcash_amount = ?, gcash_date = ? WHERE id = ? AND status = 'Pending'");
        $stmt->bind_param("isdsi", $acc_user_id, $notes, $cash_amount, $cash_date, $log_id);
    } else {
        $stmt = $conn->prepare("UPDATE payment_logs SET status = 'Verified', verified_by = ?, verified_at = NOW(), notes = ? WHERE id = ? AND status = 'Pending'");
        $stmt->bind_param("isi", $acc_user_id, $notes, $log_id);
    }
    $stmt->execute();
    if ($stmt->affected_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Payment log not found or already processed']); return;
    }

    // Get payment_log details (for GCash we need amount + date from the log)
    $logRow = $conn->query("SELECT gcash_amount, gcash_date, payment_method FROM payment_logs WHERE id = $log_id LIMIT 1")->fetch_assoc();
    $final_amount = ($payment_method === 'cash') ? ($cash_amount ?? 0) : (float)($logRow['gcash_amount'] ?? 0);
    $final_date   = ($payment_method === 'cash') ? $cash_date : ($logRow['gcash_date'] ?? date('Y-m-d'));
    $pm_label     = ($payment_method === 'cash') ? 'Cash' : 'GCash';

    // Get student payment plan
    $stRow       = $conn->query("SELECT payment_plan FROM students WHERE id = $student_id LIMIT 1")->fetch_assoc();
    $paymentPlan = $stRow['payment_plan'] ?? 'full';

    // Auto-create installment_payments record if not already done
    // Avoid duplicates via payment_log_id check
    $dupCheck = $conn->prepare("SELECT id FROM installment_payments WHERE payment_log_id = ? LIMIT 1");
    $dupCheck->bind_param("i", $log_id);
    $dupCheck->execute();
    if ($final_amount > 0 && $dupCheck->get_result()->num_rows === 0) {
        $year      = date('Y');
        $count_res = $conn->query("SELECT COUNT(*) AS cnt FROM installment_payments WHERE YEAR(created_at) = $year");
        $count     = (int)($count_res->fetch_assoc()['cnt'] ?? 0) + 1;

        if ($paymentPlan === 'installment') {
            // Installment: first payment is Downpayment AR
            $or_ar_type  = 'AR';
            $exam_period = 'Downpayment';
            $or_no       = 'AR-' . $year . str_pad($count, 4, '0', STR_PAD_LEFT);
        } else {
            // Full payment: OR
            $or_ar_type  = 'OR';
            $exam_period = 'Full';
            $or_no       = 'OR-' . $year . str_pad($count, 4, '0', STR_PAD_LEFT);
        }

        $gcash_ref = ($payment_method !== 'cash') ? ($conn->query("SELECT gcash_reference FROM payment_logs WHERE id = $log_id LIMIT 1")->fetch_assoc()['gcash_reference'] ?? '') : '';

        $ins = $conn->prepare("
            INSERT INTO installment_payments
                (student_id, payment_log_id, or_ar_number, or_ar_type, amount, payment_date, payment_method, gcash_reference, exam_period, notes, recorded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->bind_param("iissdsssssi", $student_id, $log_id, $or_no, $or_ar_type, $final_amount, $final_date, $pm_label, $gcash_ref, $exam_period, $notes, $acc_user_id);
        $ins->execute();
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
?>