<?php
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

// ── Ensure required columns exist (safe to run every request) ──
$conn->query("ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS payment_method VARCHAR(20) NOT NULL DEFAULT 'GCash' AFTER student_id");
$conn->query("ALTER TABLE students     ADD COLUMN IF NOT EXISTS payment_method VARCHAR(20) NOT NULL DEFAULT 'GCash' AFTER approval_status");

// Fix any legacy Cash logs that used 'CASH-PAYMENT' as reference
$conn->query("UPDATE payment_logs SET payment_method = 'Cash' WHERE gcash_reference = 'CASH-PAYMENT' AND payment_method = 'GCash'");

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        switch ($action) {
            case 'get_pending_payments': getPendingPayments($conn); break;
            case 'get_payment_history':  getPaymentHistory($conn);  break;
            default: echo json_encode(['success' => false, 'message' => 'Unknown action']);
        }
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { echo json_encode(['success' => false, 'message' => 'Invalid JSON']); exit(); }
        switch ($action) {
            case 'submit_gcash':   submitGcash($conn, $data);   break;
            case 'verify_payment': verifyPayment($conn, $data); break;
            case 'reject_payment': rejectPayment($conn, $data); break;
            default: echo json_encode(['success' => false, 'message' => 'Unknown action']);
        }
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
$conn->close();

// ─────────────────────────────────────────────────────────────
// STUDENT: Submit GCash reference number
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

    // Update student gcash fields
    $stmt = $conn->prepare("
        UPDATE students
        SET gcash_reference = ?, gcash_amount = ?, gcash_date = ?,
            gcash_transaction_id = ?, payment_status = 'Pending', payment_method = 'GCash'
        WHERE id = ?
    ");
    $stmt->bind_param("sdssi", $reference, $amount, $date, $txn_id, $student_id);
    $stmt->execute();

    // Check for existing pending log for this student
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

    echo json_encode([
        'success' => true,
        'message' => 'GCash payment submitted. Waiting for accounting verification.',
        'log_id'  => $log_id
    ]);
}

// ─────────────────────────────────────────────────────────────
// ACCOUNTING: Get all pending payment verifications
// GET ?action=get_pending_payments
// Returns both Cash and GCash students with Pending status
// ─────────────────────────────────────────────────────────────
function getPendingPayments($conn) {
    $rows = [];

    // Single query — join payment_logs with students
    // payment_method column now guaranteed to exist (added at top)
    $sql = "
        SELECT
            pl.id               AS log_id,
            pl.student_id,
            pl.payment_method,
            pl.gcash_reference,
            pl.gcash_amount,
            pl.gcash_date,
            pl.transaction_id,
            pl.semester,
            pl.created_at       AS submitted_at,
            s.student_number,
            s.first_name,
            s.last_name,
            s.program,
            s.year_level,
            s.payment_status,
            s.approval_status
        FROM payment_logs pl
        JOIN students s ON pl.student_id = s.id
        WHERE pl.status = 'Pending'
          AND s.approval_status = 'Pending'
        ORDER BY pl.created_at DESC
    ";

    $result = $conn->query($sql);
    if ($result) {
        while ($r = $result->fetch_assoc()) {
            $isCash = strtolower($r['payment_method'] ?? '') === 'cash'
                   || ($r['gcash_reference'] ?? '') === 'CASH-PAYMENT';

            $rows[] = [
                'logId'          => (int)$r['log_id'],
                'studentId'      => (int)$r['student_id'],
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
            ];
        }
    }

    // Also find Cash students who have NO payment_log yet (edge case)
    $noLogSql = "
        SELECT
            s.id               AS student_id,
            s.student_number,
            s.first_name,
            s.last_name,
            s.program,
            s.year_level,
            s.payment_status,
            s.approval_status,
            s.payment_method,
            s.created_at       AS submitted_at
        FROM students s
        LEFT JOIN payment_logs pl ON pl.student_id = s.id AND pl.status = 'Pending'
        WHERE s.payment_method = 'Cash'
          AND s.approval_status = 'Pending'
          AND s.payment_status  = 'Pending'
          AND pl.id IS NULL
    ";

    $noLogResult = $conn->query($noLogSql);
    if ($noLogResult) {
        $alreadyAdded = array_column($rows, 'studentId');
        while ($r = $noLogResult->fetch_assoc()) {
            $sid = (int)$r['student_id'];
            if (in_array($sid, $alreadyAdded)) continue;

            // Create a payment_log so verifyPayment can work
            $semester = '1st Semester, AY 2024-2025';
            $ins = $conn->prepare("
                INSERT INTO payment_logs (student_id, payment_method, gcash_reference, gcash_amount, semester, status)
                VALUES (?, 'Cash', '', 0, ?, 'Pending')
            ");
            $ins->bind_param("is", $sid, $semester);
            $ins->execute();
            $logId = $ins->insert_id;

            $rows[] = [
                'logId'          => $logId,
                'studentId'      => $sid,
                'studentNumber'  => $r['student_number'],
                'firstName'      => $r['first_name'],
                'lastName'       => $r['last_name'],
                'program'        => $r['program'],
                'yearLevel'      => $r['year_level'],
                'paymentMethod'  => 'Cash',
                'gcashReference' => '',
                'gcashAmount'    => 0,
                'gcashDate'      => '',
                'transactionId'  => '',
                'semester'       => $semester,
                'status'         => 'Pending',
                'submittedAt'    => $r['submitted_at'],
                'paymentStatus'  => $r['payment_status'],
                'approvalStatus' => $r['approval_status'],
            ];
        }
    }

    echo json_encode(['success' => true, 'payments' => $rows]);
}

// ─────────────────────────────────────────────────────────────
// ACCOUNTING: Payment history (Verified + Rejected)
// GET ?action=get_payment_history
// ─────────────────────────────────────────────────────────────
function getPaymentHistory($conn) {
    $result = $conn->query("
        SELECT
            pl.id               AS log_id,
            pl.student_id,
            pl.payment_method,
            pl.gcash_reference,
            pl.gcash_amount,
            pl.gcash_date,
            pl.transaction_id,
            pl.semester,
            pl.status,
            pl.notes,
            pl.verified_at,
            pl.created_at       AS submitted_at,
            s.student_number,
            s.first_name,
            s.last_name,
            s.program,
            s.year_level,
            u.first_name        AS verified_by_name
        FROM payment_logs pl
        JOIN students s ON pl.student_id = s.id
        LEFT JOIN users u ON pl.verified_by = u.id
        WHERE pl.status IN ('Verified','Rejected')
        ORDER BY pl.verified_at DESC
    ");

    $rows = [];
    if ($result) {
        while ($r = $result->fetch_assoc()) {
            $isCash = strtolower($r['payment_method'] ?? '') === 'cash'
                   || ($r['gcash_reference'] ?? '') === 'CASH-PAYMENT';

            $rows[] = [
                'logId'          => (int)$r['log_id'],
                'studentId'      => (int)$r['student_id'],
                'studentNumber'  => $r['student_number'],
                'firstName'      => $r['first_name'],
                'lastName'       => $r['last_name'],
                'program'        => $r['program'],
                'yearLevel'      => $r['year_level'],
                'paymentMethod'  => $isCash ? 'Cash' : 'GCash',
                'gcashReference' => $isCash ? '' : ($r['gcash_reference'] ?? ''),
                'gcashAmount'    => $isCash ? 25000 : (float)($r['gcash_amount'] ?? 0),
                'gcashDate'      => $isCash ? '' : ($r['gcash_date'] ?? ''),
                'transactionId'  => $isCash ? '' : ($r['transaction_id'] ?? ''),
                'semester'       => $r['semester'],
                'status'         => $r['status'],
                'notes'          => $r['notes'],
                'verifiedAt'     => $r['verified_at'],
                'verifiedByName' => $r['verified_by_name'],
                'submittedAt'    => $r['submitted_at'],
            ];
        }
    }
    echo json_encode(['success' => true, 'history' => $rows]);
}

// ─────────────────────────────────────────────────────────────
// ACCOUNTING: Approve payment (Cash or GCash)
// POST ?action=verify_payment
// Body: { log_id, student_id, accounting_user_id, notes? }
// ─────────────────────────────────────────────────────────────
function verifyPayment($conn, $data) {
    $log_id      = (int)($data['log_id']             ?? 0);
    $student_id  = (int)($data['student_id']         ?? 0);
    $acc_user_id = (int)($data['accounting_user_id'] ?? 0);
    $notes       = trim($data['notes'] ?? '');

    if (!$log_id || !$student_id) {
        echo json_encode(['success' => false, 'message' => 'log_id and student_id required']); return;
    }

    // Mark log as Verified
    $stmt = $conn->prepare("
        UPDATE payment_logs
        SET status = 'Verified', verified_by = ?, verified_at = NOW(), notes = ?
        WHERE id = ? AND status = 'Pending'
    ");
    $stmt->bind_param("isi", $acc_user_id, $notes, $log_id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Payment log not found or already processed']); return;
    }

    // Update student: mark Paid, Approved, Enrolled
    $upd = $conn->prepare("
        UPDATE students
        SET payment_status     = 'Paid',
            approval_status    = 'Approved',
            enrollment_status  = 'Enrolled',
            accounting_approved_by = ?,
            accounting_approved_at = NOW(),
            accounting_notes   = ?
        WHERE id = ?
    ");
    $upd->bind_param("isi", $acc_user_id, $notes, $student_id);
    $upd->execute();

    echo json_encode(['success' => true, 'message' => 'Payment verified. Student enrollment approved.']);
}

// ─────────────────────────────────────────────────────────────
// ACCOUNTING: Reject payment
// POST ?action=reject_payment
// Body: { log_id, student_id, accounting_user_id, notes }
// ─────────────────────────────────────────────────────────────
function rejectPayment($conn, $data) {
    $log_id      = (int)($data['log_id']             ?? 0);
    $student_id  = (int)($data['student_id']         ?? 0);
    $acc_user_id = (int)($data['accounting_user_id'] ?? 0);
    $notes       = trim($data['notes'] ?? '');

    if (!$log_id || !$student_id) {
        echo json_encode(['success' => false, 'message' => 'log_id and student_id required']); return;
    }

    $stmt = $conn->prepare("
        UPDATE payment_logs
        SET status = 'Rejected', verified_by = ?, verified_at = NOW(), notes = ?
        WHERE id = ? AND status = 'Pending'
    ");
    $stmt->bind_param("isi", $acc_user_id, $notes, $log_id);
    $stmt->execute();

    // Reset student payment status back to Pending
    $upd = $conn->prepare("
        UPDATE students
        SET payment_status  = 'Pending',
            approval_status = 'Pending'
        WHERE id = ?
    ");
    $upd->bind_param("i", $student_id);
    $upd->execute();

    echo json_encode(['success' => true, 'message' => 'Payment rejected.']);
}
?>