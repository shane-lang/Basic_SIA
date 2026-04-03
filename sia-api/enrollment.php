<?php
// ── Output buffer: captures any stray PHP notices/warnings so they never
//    corrupt the JSON response. ob_end_clean() is called before each echo.
ob_start();

// ── Exception handler: returns clean JSON instead of HTML on fatal errors ──
set_exception_handler(function(Throwable $e) {
    while (ob_get_level() > 0) { ob_end_clean(); } $_cO = $_SERVER['HTTP_ORIGIN'] ?? '*'; header("Access-Control-Allow-Origin: $_cO", true); header('Access-Control-Allow-Credentials: true', true); header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS', true); header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token', true); header('Access-Control-Expose-Headers: X-New-Token', true); header('Content-Type: application/json', true);
    http_response_code(200); // Use 200 so Angular interceptor reads the JSON body
    header('Content-Type: application/json');
    $isDev = (getenv('APP_ENV') ?: 'development') === 'development';
    echo json_encode([
        'success' => false,
        'message' => $isDev
            ? $e->getMessage() . ' [' . basename($e->getFile()) . ':' . $e->getLine() . ']'
            : 'Server error. Please try again.',
    ]);
    exit();
});

require_once __DIR__ . '/config.php';
applyCors();

// Shared helpers: cleanCode(), loadFeeConfig(), safeStudentId(), jsonOut()
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/soa_helper.php'; // saveSoaSnapshot() — used at line ~3319, must load before router

// Prevent HTML error output from breaking JSON
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

require_once __DIR__ . '/audit_helper.php';
require_once __DIR__ . '/auth_middleware.php';

// jsonOut() is defined in helpers.php — do not redeclare here.

// Actions that happen BEFORE login (enrollment wizard) — no token exists yet
$publicActions = [
    'register_student', 'register_student_shs', 'register_student_tvet', 'register_transferee',
    'get_enrollment_period',  // public — students & login page need to check if enrollment is open
];
// Add/drop actions: try to get auth but don't block if no token
// (Angular interceptor may not send token if sessionStorage cleared across tabs)
$addDropAuthRequired = ['process_add_drop','get_add_drop_requests',
    'registrar_add_subject','registrar_drop_subject','set_add_drop_window',
    'accounting_approve_add_drop','get_pending_add_drop_for_accounting'];
$addDropPublicOk = ['submit_add_drop','get_add_drop_window',
    'get_student_enrollments','search_students'];
if (in_array($action, $addDropAuthRequired)) {
    $authUser = requireAuth($conn);
} elseif (in_array($action, $addDropPublicOk)) {
    $authUser = requireAuth($conn, '', true);
} elseif (in_array($action, $publicActions)) {
    $authUser = null;
} else {
    $authUser = requireAuth($conn);
}
$GLOBALS['authUser'] = $authUser;


// Schema managed by migrate.php

// ── sys_config table (enrollment period, etc.) ───────────────────────────────
// ── Enrollment period helpers ─────────────────────────────────────────────────
function getEnrollmentPeriodRow(mysqli $conn): array {
    $res = $conn->query("SELECT config_value FROM sys_config WHERE config_key = 'enrollment_period' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $val = json_decode($res->fetch_assoc()['config_value'], true);
        if (is_array($val)) {
            // Back-fill semester/school_year parsed from label for rows saved
            // before these fields were added to the stored JSON.
            if (empty($val['semester']) || empty($val['school_year'])) {
                $lbl = trim($val['label'] ?? '');
                if ($lbl !== '' && preg_match('/^(.+?),\s*AY\s*(\d{4}-\d{4})/i', $lbl, $lm)) {
                    if (empty($val['semester']))    $val['semester']    = trim($lm[1]);
                    if (empty($val['school_year'])) $val['school_year'] = trim($lm[2]);
                }
            }
            return $val;
        }
    }
    return ['start' => null, 'end' => null, 'is_open' => false, 'label' => '', 'semester' => '', 'school_year' => ''];
}

function isEnrollmentOpen(mysqli $conn): bool {
    $p = getEnrollmentPeriodRow($conn);
    if (!($p['is_open'] ?? false)) return false;

    // Use MySQL NOW() to avoid PHP timezone vs stored datetime mismatch
    $nowRes = $conn->query("SELECT NOW() AS now");
    $nowStr = $nowRes ? $nowRes->fetch_assoc()['now'] : date('Y-m-d H:i:s');
    $now    = strtotime($nowStr);

    // Normalize: browser sends "2026-03-20T01:09" — strip T separator
    $startRaw = str_replace('T', ' ', trim($p['start'] ?? ''));
    $endRaw   = str_replace('T', ' ', trim($p['end']   ?? ''));

    $start = !empty($startRaw) ? strtotime($startRaw) : null;
    $end   = !empty($endRaw)   ? strtotime($endRaw)   : null;

    if ($start && $now < $start) return false;
    if ($end   && $now > $end)   return false;
    return true;
}

// (action already defined above)

// ── INCLUDE GUARD ────────────────────────────────────────────────────────────
// When enrollment.php is require_once'd by registrar.php (to call autoEnrollAll),
// the router switch and $conn->close() must NOT execute — they would close the
// shared DB connection and corrupt the registrar response.
// Only run the router when this file is the direct entry point.
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'enrollment.php'):

switch ($method) {
    case 'GET':
        switch ($action) {
            case 'get_profile':             getProfile($conn);                  break;
            case 'get_schedule':            getSchedule($conn);                 break;
            case 'get_courses':             getAvailableCourses($conn);         break;
            case 'search_students':          searchStudents($conn);               break;
            case 'get_student_enrollments':  getStudentEnrollments($conn);        break;
            case 'get_add_drop_requests':    getAddDropRequests($conn);           break;
            case 'get_my_add_drop':          getMyAddDrop($conn);                 break;
            case 'get_add_drop_window':      getAddDropWindow($conn);             break;
            case 'get_pending_add_drop_for_accounting': getPendingAddDropForAccounting($conn); break;
            case 'get_enrollments':         getEnrollments($conn);              break;
            case 'get_payment_status':      getPaymentStatus($conn);            break;
            case 'get_enrollment_summary':  getEnrollmentSummary($conn);        break;
            case 'get_student_context':     getStudentContext($conn);           break;
            case 'get_curriculum':          getCurriculum($conn);               break;
            case 'get_soa_snapshot':        getSoaSnapshot($conn);              break;
            case 'get_enrollment_period':   getEnrollmentPeriod($conn);         break;
            default: echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
        }
        break;
    case 'POST':
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'Invalid JSON. Raw: ' . substr($raw, 0, 200)]);
            exit();
        }
        switch ($action) {
            // ── Registration ──────────────────────────────────────────
            case 'register_student':        registerStudent($conn, $data);          break;
            case 'register_student_shs':    registerStudentSHS($conn, $data);       break;
            case 'register_student_tvet':   registerStudentTVET($conn, $data);      break;
            case 'register_transferee':     registerTransferee($conn, $data);       break;
            // ── Enrollment ────────────────────────────────────────────
            case 'enroll_course':           enrollCourse($conn, $data);             break;
            case 'registrar_add_subject':   registrarAddSubject($conn, $data);      break;
            case 'declare_scholarship':     declareScholarship($conn, $data);       break;
            case 'registrar_drop_subject':  registrarDropSubject($conn, $data);     break;
            case 'submit_add_drop':          submitAddDropRequest($conn, $data);     break;
            case 'process_add_drop':
                try { processAddDropRequest($conn, $data); }
                catch (Throwable $e) {
                    while (ob_get_level() > 0) { ob_end_clean(); } $_cO = $_SERVER['HTTP_ORIGIN'] ?? '*'; header("Access-Control-Allow-Origin: $_cO", true); header('Access-Control-Allow-Credentials: true', true); header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS', true); header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token', true); header('Access-Control-Expose-Headers: X-New-Token', true); header('Content-Type: application/json', true);
                    http_response_code(200);
                    echo json_encode(['success'=>false,'message'=>$e->getMessage().' ['.$e->getFile().':'.$e->getLine().']']);
                }
                break;
            case 'accounting_approve_add_drop':
                try { accountingApproveAddDrop($conn, $data); }
                catch (Throwable $e) {
                    while (ob_get_level() > 0) { ob_end_clean(); } $_cO = $_SERVER['HTTP_ORIGIN'] ?? '*'; header("Access-Control-Allow-Origin: $_cO", true); header('Access-Control-Allow-Credentials: true', true); header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS', true); header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token', true); header('Access-Control-Expose-Headers: X-New-Token', true); header('Content-Type: application/json', true);
                    http_response_code(200);
                    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
                }
                break;
            case 'set_add_drop_window':      setAddDropWindow($conn, $data);         break;
            case 'set_enrollment_period':   setEnrollmentPeriod($conn, $data);  break;
            case 'auto_enroll_new':         autoEnrollNew($conn, $data);            break;  // NEW/regular students
            case 'auto_enroll_transferee':  autoEnrollTransfereeAction($conn, $data); break; // Transferee students
            case 'auto_enroll_all':         autoEnrollAll($conn, $data);            break;  // legacy router (kept for compatibility)
            // ── Payment & Approval ────────────────────────────────────
            case 'update_payment':          updatePayment($conn, $data);            break;
            case 'approve_enrollment':      approveEnrollment($conn, $data);        break;
            case 'update_payment_plan':     updatePaymentPlan($conn, $data);        break;
            case 're_enroll':               reEnroll($conn, $data);                 break;
            // ── Misc ──────────────────────────────────────────────────
            case 'update_profile':          updateProfile($conn, $data);            break;
            default: echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
        }
        break;
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        switch ($action) {
            case 'drop_course': dropCourse($conn, $data); break;
            default: echo json_encode(['success' => false, 'message' => 'Unknown action']);
        }
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}


// NOTE: $conn->close() intentionally removed — PHP closes it at script end.
// Explicit close() caused "mysqli object is already closed" in late-running functions.

endif; // end include guard

function getStudentIdFromRequest($conn) {
    // Priority 1: explicit student_id GET param (DB primary key)
    $student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
    if ($student_id > 0) return $student_id;
    // Priority 2: user_id GET param → look up students.id
    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    // Priority 3: fall back to authenticated session user_id
    if (!$user_id && isset($GLOBALS['authUser']['user_id'])) {
        $user_id = (int)$GLOBALS['authUser']['user_id'];
    }
    if ($user_id > 0) {
        $stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
        if (!$stmt) return 0;
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $r = $stmt->get_result();
        $stmt->close();
        if ($r && $r->num_rows > 0) return (int)$r->fetch_assoc()['id'];
    }
    return 0;
}

// ─────────────────────────────────────────────────────────────
// GET PROFILE
// ─────────────────────────────────────────────────────────────
function getProfile($conn) {
    $student_id = getStudentIdFromRequest($conn);
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        return;
    }

    $stmt = $conn->prepare("SELECT s.id, s.user_id, s.student_number, s.first_name, s.last_name, s.middle_name, s.suffix, s.date_of_birth, s.age, s.sex, s.address, s.phone, s.program, s.year_level, s.semester, s.student_category, s.student_type, s.enrollment_status, s.approval_status, s.payment_status, s.payment_method, s.payment_plan, s.enrollment_date, s.strand, s.learning_delivery, s.last_school_attended, s.gpa, s.profile_picture, s.is_scholar, s.scholar_type, s.scholar_grantor, s.scholarship_amount, s.has_special_needs, s.special_needs_details, s.has_assistive_tech, s.assistive_tech_details, u.email AS user_email FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ? LIMIT 1");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result || $result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Profile not found. Please complete enrollment first.', 'student_id' => $student_id]);
        return;
    }

    $s   = $result->fetch_assoc();
    $pic = $s['profile_picture'] ?: 'https://ui-avatars.com/api/?name=' . urlencode(($s['first_name'] ?? '') . '+' . ($s['last_name'] ?? '')) . '&size=150';

    // Resolve program details from programs table if it exists
    $programName = $s['program'] ?? '';
    $programCode = ''; $levelType = $s['student_category'] ?? ''; $department = '';
    $hasPTable = $conn->query("SHOW TABLES LIKE 'programs'")->num_rows > 0;
    if ($hasPTable && $programName) {
        $progStmt = $conn->prepare("SELECT code, level_type, department FROM programs WHERE name = ? OR code = ? LIMIT 1");
        $progStmt->bind_param("ss", $programName, $programName);
        $progStmt->execute();
        $progRow = $progStmt->get_result()->fetch_assoc();
        if ($progRow) {
            $programCode = $progRow['code']       ?? '';
            $levelType   = $progRow['level_type'] ?? $levelType;
            $department  = $progRow['department'] ?? '';
        }
    }

    // Use the student's own semester field (set at registration) as primary source
    // Fall back to detecting from enrolled courses for older accounts
    $semester = trim($s['semester'] ?? '');
    if ($semester === '') {
        $semStmt = $conn->prepare("
            SELECT c.semester FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            WHERE e.student_id = ? AND e.status IN ('Pending','Enrolled')
            ORDER BY e.created_at DESC LIMIT 1
        ");
        $semStmt->bind_param("i", $student_id);
        $semStmt->execute();
        $semRow   = $semStmt->get_result()->fetch_assoc();
        $semester = $semRow['semester'] ?? '';
    }

    // Guardian data now in student_guardians table
    $guardianName = ''; $guardianAddress = ''; $guardianContact = '';
    $gRow = $conn->query("SELECT guardian_name, address, contact FROM student_guardians WHERE student_id = {$s['id']} LIMIT 1");
    if ($gRow && $gr = $gRow->fetch_assoc()) {
        $guardianName    = $gr['guardian_name'] ?? '';
        $guardianAddress = $gr['address']       ?? '';
        $guardianContact = $gr['contact']       ?? '';
    }
    if (!$guardianName)    $guardianName    = $s['emergency_contact'] ?? '';
    if (!$guardianContact) $guardianContact = $s['emergency_phone']   ?? '';

    // FIX DASH-PAYSTATUS-01: Derive paymentStatus from actual paid vs total_assessment
    // instead of the raw students.payment_status column. The DB column lags for
    // installment students (stays 'Pending' after Accounting verifies Downpayment).
    $tfResP = $conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id = {$s['id']} LIMIT 1");
    $tfRowP = $tfResP ? $tfResP->fetch_assoc() : null;
    $totalAssessmentP = $tfRowP ? (float)$tfRowP['total_assessment'] : 0;
    $paidResP = $conn->query("SELECT COALESCE(SUM(amount),0) AS paid FROM installment_payments WHERE student_id = {$s['id']}");
    $totalPaidP = $paidResP ? (float)$paidResP->fetch_assoc()['paid'] : 0;
    $studentCatP = strtoupper(trim($s['student_category'] ?? ''));
    $studentTypeP = strtolower(trim($s['student_type'] ?? ''));
    $isFreeP = in_array($studentCatP, ['SHS','TVET']) && $studentTypeP !== 'transferee';
    if ($isFreeP) {
        $computedPaymentStatus = 'Free';
    } elseif ($totalAssessmentP <= 0) {
        // No fee record yet — fall back to DB column
        $computedPaymentStatus = $s['payment_status'] ?? 'Pending';
    } elseif ($totalPaidP >= $totalAssessmentP) {
        $computedPaymentStatus = 'Paid';
    } elseif ($totalPaidP > 0) {
        $computedPaymentStatus = 'Partial';
    } else {
        $computedPaymentStatus = $s['payment_status'] ?? 'Pending';
    }

    echo json_encode(['success' => true, 'student' => [
        // Identity
        'id'                  => $s['student_number'],
        'dbId'                => (int)$s['id'],
        'firstName'           => $s['first_name']        ?? '',
        'lastName'            => $s['last_name']         ?? '',
        'middleName'          => $s['middle_name']       ?? '',
        'suffix'              => $s['suffix']            ?? '',
        'email'               => $s['user_email'] ?? '',
        'phone'               => $s['phone']             ?? '',
        'profilePicture'      => $pic,
        // Academic
        'program'             => $programName,
        'programCode'         => $programCode,
        'studentCategory'     => $levelType,
        'department'          => $department,
        'yearLevel'           => $s['year_level']        ?? '1st Year',
        'studentType'         => $s['student_type']      ?? '',
        'strand'              => $s['strand']            ?? '',
        'lastSchoolAttended'  => $s['last_school_attended'] ?? '',
        'learningDelivery'    => $s['learning_delivery'] ?? '',
        'semester'            => $semester,
        'gpa'                 => (float)($s['gpa']       ?? 0),
        'enrollmentStatus'    => $s['enrollment_status'] ?? '',
        'enrollmentDate'      => $s['enrollment_date']   ?? '',
        // Payment
        'paymentStatus'       => $computedPaymentStatus,
        'paymentMethod'       => $s['payment_method'] ?: '',  // BUG-A FIX: '' not 'GCash'; getStudentContext() heals NULL via payment_logs history
        'paymentPlan'         => $s['payment_plan']      ?? 'full',
        'approvalStatus'      => $s['approval_status']   ?? '',
        'isScholar'           => false,
        'scholarType'         => '',
        'scholarGrantor'      => '',
        'scholarshipAmount'   => 0.0,
        // Personal
        'lrnNo'               => $s['lrn_no']            ?? '',
        'dateOfBirth'         => $s['date_of_birth']     ?? '',
        'sex'                 => $s['sex']               ?? '',
        'religion'            => $s['religion']          ?? '',
        'age'                 => isset($s['date_of_birth']) && $s['date_of_birth'] ? (int)date_diff(date_create($s['date_of_birth']), date_create('today'))->y : '',
        'placeOfBirth'        => $s['place_of_birth']    ?? '',
        'citizenship'         => $s['citizenship']       ?? '',
        'address'             => $s['address']           ?? '',
        'motherTongue'        => $s['mother_tongue']     ?? '',
        'isIndigenous'        => (bool)($s['is_indigenous']      ?? false),
        'psaBirthCertNo'      => $s['psa_birth_cert_no'] ?? '',
        // Special needs
        'hasSpecialNeeds'     => (bool)($s['has_special_needs']   ?? false),
        'specialNeedsDetails' => $s['special_needs_details']      ?? '',
        'hasAssistiveTech'    => (bool)($s['has_assistive_tech']  ?? false),
        'assistiveTechDetails'=> $s['assistive_tech_details']     ?? '',
        // Guardian
        'guardianName'        => $guardianName,
        'guardianAddress'     => $guardianAddress,
        'guardianContact'     => $guardianContact,
        'emergencyContact'    => $s['emergency_contact'] ?? $guardianName,
        'emergencyPhone'      => $s['emergency_phone']   ?? $guardianContact,
    ]]);
}

// ─────────────────────────────────────────────────────────────
// GET PAYMENT STATUS — lightweight poll endpoint
// ─────────────────────────────────────────────────────────────
function getPaymentStatus($conn) {
    $student_id = getStudentIdFromRequest($conn);
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        return;
    }
    $stmt = $conn->prepare("SELECT payment_status, payment_method, payment_plan, approval_status, enrollment_status FROM students WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    if ($r) {
        // BUG-A FIX: Never default to 'GCash' here — if payment_method is NULL
        // (e.g. after re-enrollment before the student re-selects) return '' so
        // the frontend uses its own stored value rather than forcing GCash on
        // Cash students.  getStudentContext() already has a full self-heal that
        // reads payment_logs history; getPaymentStatus() is a lightweight poll
        // that must not override that resolved value with a wrong default.
        $resolvedMethod = trim($r['payment_method'] ?? '');
        if ($resolvedMethod === '') {
            // Quick heal: check most recent payment_log for this student
            $pmQ = $conn->prepare(
                "SELECT payment_method FROM payment_logs
                 WHERE student_id = ? AND payment_method IS NOT NULL AND payment_method != ''
                 ORDER BY created_at DESC LIMIT 1"
            );
            $pmQ->bind_param('i', $student_id);
            $pmQ->execute();
            $pmR = $pmQ->get_result()->fetch_assoc();
            $pmQ->close();
            if ($pmR) {
                $resolvedMethod = (strtolower($pmR['payment_method']) === 'cash') ? 'Cash' : 'GCash';
            }
            // If still empty leave as '' — do NOT default to GCash
        }
        echo json_encode([
            'success'          => true,
            'paymentStatus'    => $r['payment_status'],
            'paymentMethod'    => $resolvedMethod,
            'paymentPlan'      => $r['payment_plan']    ?? 'full',
            'approvalStatus'   => $r['approval_status'],
            'enrollmentStatus' => $r['enrollment_status'],
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
    }
}

// ─────────────────────────────────────────────────────────────
// GET SCHEDULE
// Returns all Enrolled courses with day/time breakdown for schedule grid
// ─────────────────────────────────────────────────────────────
function getSchedule($conn) {
    $student_id = getStudentIdFromRequest($conn);
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        return;
    }

    $stmt = $conn->prepare("
        SELECT
            c.id        AS course_id,
            c.code,
            c.name,
            COALESCE(
                NULLIF(TRIM(CONCAT(COALESCE(fj.first_name,''),' ',COALESCE(fj.last_name,''))), ''),
                NULLIF(TRIM(CONCAT(COALESCE(f.first_name,''), ' ',COALESCE(f.last_name,''))), ''),
                NULLIF(TRIM(CONCAT(COALESCE(fc.first_name,''),' ',COALESCE(fc.last_name,''))), ''),
                ''
            ) AS instructor,
            cs.day,
            CONCAT(cs.time_start,' - ',cs.time_end) AS time,
            r.room_name AS room,
            c.semester,
            c.credits,
            e.status
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN course_sections cs ON cs.course_id = c.id AND cs.is_active = 1
        LEFT JOIN faculty f  ON f.user_id  = cs.faculty_id
        LEFT JOIN faculty fc ON fc.user_id = c.faculty_id
        LEFT JOIN faculty fj ON fj.status = 'Active'
            AND (
                JSON_CONTAINS(fj.subjects, CONCAT(CHAR(34), SUBSTRING_INDEX(c.code,'-',1), CHAR(34)))
                OR JSON_CONTAINS(fj.subjects, CONCAT(CHAR(34), c.code, CHAR(34)))
            )
        LEFT JOIN rooms r ON r.id = cs.room_id
        WHERE e.student_id = ?
          AND e.status = 'Enrolled'
        GROUP BY e.id, cs.day, cs.time_start
        ORDER BY cs.day, cs.time_start
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $schedule = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Split multi-day entries (e.g. "Monday,Wednesday,Friday")
            $days = explode(',', $row['day'] ?? '');
            foreach ($days as $day) {
                $day = trim($day);
                if (!$day) continue;
                $schedule[] = [
                    'courseId'   => (int)$row['course_id'],
                    'day'        => $day,
                    'time'       => $row['time'] ?? '',
                    'courseName' => $row['name'],
                    'courseCode' => cleanCode($row['code']),
                    'instructor' => trim($row['instructor'] ?? ''),
                    'room'       => $row['room']     ?? '',
                    'semester'   => $row['semester'] ?? '',
                    'credits'    => (int)$row['credits'],
                    'status'     => $row['status'],
                    'grade'      => null,
                ];
            }
        }
    }
    echo json_encode(['success' => true, 'schedule' => $schedule]);
}

// ─────────────────────────────────────────────────────────────
// GET AVAILABLE COURSES (not yet enrolled by this student)
// Uses real-time enrollment count from enrollments table.
// Capacity defaults to 50 if not set (0 or NULL).
// ─────────────────────────────────────────────────────────────
function getAvailableCourses($conn) {
    $student_id = getStudentIdFromRequest($conn);
    $semester   = isset($_GET['semester']) ? trim($_GET['semester']) : '';

    // Base query: real-time enrolled count + safe capacity fallback
    // COALESCE(capacity,50) prevents division by zero / always-full bug
    // GREATEST(capacity,1) same safeguard
    $baseSelect = "
        SELECT
            c.*,
            COALESCE(c.capacity, 50)                           AS safe_capacity,
            COUNT(e.id)                                        AS real_enrolled,
            GREATEST(COALESCE(c.capacity, 50) - COUNT(e.id), 0) AS available_seats
        FROM courses c
        LEFT JOIN enrollments e
            ON e.course_id = c.id AND e.status IN ('Pending','Enrolled')
    ";

    if ($student_id > 0 && $semester !== '') {
        $sql  = $baseSelect . "
            WHERE c.semester = ?
              AND c.id NOT IN (
                SELECT course_id FROM enrollments
                WHERE student_id = ? AND semester = ? AND status IN ('Pending','Enrolled')
              )
            GROUP BY c.id
            ORDER BY c.code
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sis", $semester, $student_id, $semester);

    } elseif ($student_id > 0) {
        $sql  = $baseSelect . "
            WHERE c.id NOT IN (
                SELECT course_id FROM enrollments
                WHERE student_id = ? AND status IN ('Pending','Enrolled')
            )
            GROUP BY c.id
            ORDER BY c.code
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $student_id);

    } elseif ($semester !== '') {
        $sql  = $baseSelect . "WHERE c.semester = ? GROUP BY c.id ORDER BY c.code";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $semester);

    } else {
        $sql  = $baseSelect . "GROUP BY c.id ORDER BY c.code";
        $stmt = $conn->prepare($sql);
    }

    $stmt->execute();
    $result  = $stmt->get_result();
    $courses = [];

    if ($result) {
        while ($r = $result->fetch_assoc()) {
            $cap       = (int)$r['safe_capacity'];
            $enrolled  = (int)$r['real_enrolled'];
            $available = (int)$r['available_seats'];

            $courses[] = [
                'id'          => (int)$r['id'],
                'code'           => cleanCode($r['code']),
                'name'        => $r['name'],
                'credits'     => (int)$r['credits'],
                'instructor'  => '',
                'schedule'    => '',
                'day'         => '',
                'time'        => '',
                'room'        => '',
                'capacity'    => $cap,
                'enrolled'    => $enrolled,
                'available'   => $available,
                'semester'    => $r['semester']    ?? '',
                'description' => $r['description'] ?? '',
                'department'  => $r['department']  ?? '',
            ];
        }
    }
    echo json_encode(['success' => true, 'courses' => $courses]);
}

// ─────────────────────────────────────────────────────────────
// GET ENROLLMENTS — returns all enrollments for a student
// ─────────────────────────────────────────────────────────────
function getEnrollments($conn) {
    $student_id = getStudentIdFromRequest($conn);
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        return;
    }

    // Get student's year_level and semester for filtering
    $stuQ = $conn->prepare("SELECT year_level, semester, student_type FROM students WHERE id = ? LIMIT 1");
    $stuQ->bind_param("i", $student_id);
    $stuQ->execute();
    $stuRow = $stuQ->get_result()->fetch_assoc();
    $stuYearLevel = $stuRow['year_level'] ?? '';
    $stuSemRaw    = $stuRow['semester']   ?? '';
    $stuType      = $stuRow['student_type'] ?? '';

    // Extract semester term (strip AY suffix)
    $semTerm = '';
    if ($stuSemRaw !== '') {
        preg_match('/^(1st Semester|2nd Semester|Summer)/i', $stuSemRaw, $sm);
        $semTerm = $sm[1] ?? $stuSemRaw;
    }

    // Get credited course IDs for this student (transferees) — exclude from enrolled list
    $creditedIds = [];
    $torQ = $conn->prepare("SELECT credited_subjects FROM tor_evaluations WHERE student_id = ? AND status = 'Evaluated' LIMIT 1");
    $torQ->bind_param("i", $student_id);
    $torQ->execute();
    $torRow = $torQ->get_result()->fetch_assoc();
    if ($torRow && !empty($torRow['credited_subjects'])) {
        $dec = json_decode($torRow['credited_subjects'], true);
        if (is_array($dec)) {
            foreach ($dec as $subject) {
                if (isset($subject['courseId'])) {
                    $creditedIds[] = (int)$subject['courseId'];
                }
            }
        }
    }
    $safeIds    = array_filter(array_map('intval', $creditedIds), fn($v) => $v > 0);
    $excludeSql = !empty($safeIds) ? 'AND c.id NOT IN (' . implode(',', $safeIds) . ')' : '';

    // Filters removed: year_level + semester exact-match filters caused enrolled subjects
    // to disappear when courses table format didn't match student record exactly.
    // The enrollment row itself is the source of truth — always show all Enrolled/Pending rows.
    $ylFilter  = '';
    $semFilter = '';

    // FIX INSTRUCTOR-01: courses.faculty_id / course_sections.faculty_id are unreliable
    // when course codes have program suffixes (CC100-IT vs CC100). Instead join faculty
    // via their subjects JSON which the admin UI actually writes. JSON_CONTAINS matches
    // the clean base code (CC100) against what is stored in faculty.subjects.
    $baseCode = "SUBSTRING_INDEX(c.code, '-', 1)"; // strips suffix: CC100-IT -> CC100
    $stmt = $conn->prepare("
        SELECT
            e.id,
            e.status,
            e.enrollment_date,
            e.notes,
            c.id        AS course_id,
            c.code,
            c.name,
            c.credits,
            c.lec_units,
            c.lab_units,
            COALESCE(
                NULLIF(TRIM(CONCAT(COALESCE(fj.first_name,''),' ',COALESCE(fj.last_name,''))), ''),
                NULLIF(TRIM(CONCAT(COALESCE(f.first_name,''), ' ',COALESCE(f.last_name,''))), ''),
                NULLIF(TRIM(CONCAT(COALESCE(fc.first_name,''),' ',COALESCE(fc.last_name,''))), ''),
                ''
            ) AS instructor,
            cs.day,
            CONCAT(cs.time_start,' - ',cs.time_end) AS time,
            r.room_name AS room,
            c.semester,
            c.year_level
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN course_sections cs ON cs.course_id = c.id AND cs.is_active = 1
        LEFT JOIN faculty f  ON f.user_id  = cs.faculty_id
        LEFT JOIN faculty fc ON fc.user_id = c.faculty_id
        LEFT JOIN rooms r    ON r.id = cs.room_id
        LEFT JOIN faculty fj ON fj.status = 'Active'
            AND (
                JSON_CONTAINS(fj.subjects, CONCAT(CHAR(34), SUBSTRING_INDEX(c.code,'-',1), CHAR(34)))
                OR JSON_CONTAINS(fj.subjects, CONCAT(CHAR(34), c.code, CHAR(34)))
            )
        WHERE e.student_id = ?
          AND (
            e.status IN ('Pending', 'Enrolled')
            OR (
              -- FIX ENR-01: Completed rows for the student's current semester must also
              -- show (unique key conflict leaves them Completed instead of Enrolled).
              e.status   = 'Completed'
              AND e.semester = (SELECT semester FROM students WHERE id = ? LIMIT 1)
            )
          )
          $ylFilter
          $semFilter
          $excludeSql
        GROUP BY e.id
        ORDER BY c.year_level, c.semester, c.code
    ");
    $stmt->bind_param("ii", $student_id, $student_id);
    $stmt->execute();
    $result      = $stmt->get_result();
    $enrollments = [];
    if ($result) {
        while ($r = $result->fetch_assoc()) {
            $lec = (int)($r['lec_units'] ?? 0);
            $lab = (int)($r['lab_units'] ?? 0);
            $cred = (int)$r['credits'];
            // Apply same lec_units fix as get_courses
            if ($lec === 0 && $lab === 0 && $cred > 0) $lec = $cred;
            $enrollments[] = [
                'id'             => (int)$r['id'],
                'courseId'       => (int)$r['course_id'],
                'code'           => cleanCode($r['code']),
                'name'           => $r['name'],
                'credits'        => $cred,
                'lecUnits'       => $lec,
                'labUnits'       => $lab,
                'instructor'     => $r['instructor'] ?? '',
                'schedule'       => $r['schedule'] ?? '',
                'day'            => $r['day'] ?? '',
                'time'           => $r['time'] ?? '',
                'room'           => $r['room'] ?? '',
                'semester'       => $r['semester'],
                'yearLevel'      => $r['year_level'],
                'enrollmentDate' => $r['enrollment_date'],
                'status'         => $r['status'],
                'notes'          => $r['notes'],
            ];
        }
    }
    echo json_encode(['success' => true, 'enrollments' => $enrollments]);
}
// ─────────────────────────────────────────────────────────────
// REGISTER TRANSFEREE - Separate function for transferee students
// Transferees need TOR evaluation before payment and enrollment
// ─────────────────────────────────────────────────────────────
function registerTransferee($conn, $data) {
    // ── Enrollment period gate ────────────────────────────────────
    if (empty($data['bypass_period_check']) && !isEnrollmentOpen($conn)) {
        $p = getEnrollmentPeriodRow($conn);
        $msg = 'Enrollment is currently closed.';
        if (!empty($p['start'])) $msg .= ' Opens: ' . date('M d, Y g:i A', strtotime($p['start']));
        if (!empty($p['label'])) $msg .= ' (' . $p['label'] . ')';
        echo json_encode(['success' => false, 'message' => $msg, 'enrollment_closed' => true]);
        return;
    }
    // Validate required fields
    foreach (['user_id', 'firstName', 'lastName', 'email', 'program'] as $f) {
        if (empty($data[$f])) {
            echo json_encode(['success' => false, 'message' => "Field '$f' is required"]);
            return;
        }
    }

    $user_id             = (int)$data['user_id'];
    $firstName           = trim($data['firstName']);
    $lastName            = trim($data['lastName']);
    $middleName          = trim($data['middleName']          ?? '');
    $suffix              = trim($data['suffix']              ?? '');
    $email               = trim($data['email']);
    $phone               = trim($data['phone']               ?? '');
    $dateOfBirth         = trim($data['dateOfBirth']         ?? '');
    $address             = trim($data['address']             ?? '');
    $program             = trim($data['program']);
    $semester            = trim($data['semester']            ?? '');
    $lastSchoolAttended  = trim($data['lastSchoolAttended']  ?? '');
    
    // Transferee specific
    $studentType         = 'Transferee';  // Force transferee type
    $studentCategory     = trim($data['studentCategory'] ?? '');
    
    // Normalize payment method
    $rawMethod          = strtolower(trim($data['paymentMethod'] ?? 'gcash'));
    $paymentMethod      = ($rawMethod === 'cash') ? 'Cash' : 'GCash';
    
    // Payment plan: full or installment
    $rawPlan           = strtolower(trim($data['paymentPlan'] ?? 'full'));
    $paymentPlan       = ($rawPlan === 'installment') ? 'installment' : 'full';

    // Personal info
    $lrnNo               = trim($data['lrnNo']               ?? '');
    $sex                 = trim($data['sex']                 ?? '');
    $religion            = trim($data['religion']            ?? '');
    $age                 = trim($data['age']                 ?? '');
    $placeOfBirth        = trim($data['placeOfBirth']        ?? '');
    $citizenship         = trim($data['citizenship']         ?? '');
    $motherTongue        = trim($data['motherTongue']        ?? '');
    $isIndigenous        = ($data['isIndigenous'] === 'Yes'  || $data['isIndigenous']  == 1) ? 1 : 0;
    $psaBirthCertNo      = trim($data['psaBirthCertNo']      ?? '');

    // Special needs
    $hasSpecialNeeds     = ($data['hasSpecialNeeds']     === 'Yes' || $data['hasSpecialNeeds']     == 1) ? 1 : 0;
    $specialNeedsDetails = trim($data['specialNeedsDetails']  ?? '');
    $hasAssistiveTech    = ($data['hasAssistiveTech']    === 'Yes' || $data['hasAssistiveTech']    == 1) ? 1 : 0;
    $assistiveTechDetails= trim($data['assistiveTechDetails'] ?? '');

    // Guardian
    $guardianName         = trim($data['guardianName']         ?? $data['emergencyContact'] ?? '');
    $guardianAddress      = trim($data['guardianAddress']      ?? '');
    $guardianContact      = trim($data['guardianContact']      ?? $data['emergencyPhone']   ?? '');
    $guardianEmail        = trim($data['guardianEmail']        ?? '');
    $guardianRelationship = trim($data['guardianRelationship'] ?? '');
    $emergencyContact     = $guardianName;
    $emergencyPhone       = $guardianContact;

    // Scholar
    $isScholar           = (int)($data['isScholar']         ?? 0);
    $scholarType         = trim($data['scholarType']        ?? '');
    $scholarGrantor      = trim($data['scholarGrantor']     ?? '');
    $scholarshipAmount   = (float)($data['scholarshipAmount'] ?? 0);

    // Verify user exists
    $chk = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
    $chk->bind_param("i", $user_id);
    $chk->execute();
    if ($chk->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User ID ' . $user_id . ' not found.']);
        return;
    }

    // Check for existing student record
    $ex = $conn->prepare("SELECT id, student_number FROM students WHERE user_id = ? LIMIT 1");
    $ex->bind_param("i", $user_id);
    $ex->execute();
    $exRes = $ex->get_result();
    if ($exRes->num_rows > 0) {
        $existing = $exRes->fetch_assoc();
        // FIX E-04: Return success:true (idempotent) so enrollment wizard can retry
        echo json_encode([
            'success'        => true,
            'message'        => 'Student record already exists. Continuing enrollment.',
            'student_id'     => (int)$existing['id'],
            'student_number' => $existing['student_number'],
            'already_existed'=> true,
        ]);
        return;
    }

    // FIX E-05: Atomic student number — use transaction + FOR UPDATE
    $year   = date('Y');
    $prefix = "STU-$year-";
    $conn->begin_transaction();
    $like    = $prefix . '%';
    $maxStmt = $conn->prepare(
        "SELECT MAX(CAST(SUBSTRING_INDEX(student_number, '-', -1) AS UNSIGNED)) AS maxNum
          FROM students WHERE student_number LIKE ? FOR UPDATE"
    );
    $maxStmt->bind_param("s", $like);
    $maxStmt->execute();
    $maxNum        = (int)($maxStmt->get_result()->fetch_assoc()['maxNum'] ?? 0);
    $studentNumber = $prefix . str_pad($maxNum + 1, 4, '0', STR_PAD_LEFT);

    $dobBind = (!empty($dateOfBirth)) ? $dateOfBirth : '';

    // Schema managed by migrate.php

    // INSERT student record
    $ins = $conn->prepare("
        INSERT INTO students
          (user_id, student_number,
           first_name, last_name, middle_name, suffix,
           phone, date_of_birth, address,
           emergency_contact, emergency_phone,
           program, student_type, student_category, semester, enrollment_date,
           lrn_no, sex, religion, place_of_birth, citizenship, mother_tongue,
           is_indigenous, psa_birth_cert_no,
           last_school_attended,
           has_special_needs, special_needs_details,
           has_assistive_tech, assistive_tech_details,
           payment_plan, payment_method,
           tor_eval_status,
           enrollment_status, payment_status, approval_status)
        VALUES
          (?, ?,
           ?, ?, ?, ?,
           ?, ?, ?,
           ?, ?,
           ?, ?, ?, ?, ?,
           ?, ?, ?, ?, ?, ?,
           ?, ?,
           ?,
           ?, ?,
           ?, ?,
           ?, ?,
           'Pending',
           'Pending', 'Pending', 'Pending')
    ");

    if (!$ins) {
        echo json_encode(['success' => false, 'message' => 'DB prepare error: ' . $conn->error]);
        return;
    }

    $ins->bind_param("isssssssssssssssssssssissisiss",
        $user_id, $studentNumber,
        $firstName, $lastName, $middleName, $suffix,
        $phone, $dobBind, $address,
        $emergencyContact, $emergencyPhone,
        $program, $studentType, $studentCategory, $semester, date('Y-m-d'),
        $lrnNo, $sex, $religion, $placeOfBirth, $citizenship, $motherTongue,
        $isIndigenous, $psaBirthCertNo,
        $lastSchoolAttended,
        $hasSpecialNeeds, $specialNeedsDetails,
        $hasAssistiveTech, $assistiveTechDetails,
        $paymentPlan, $paymentMethod
    );

    try { $ins->execute(); }
    catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'DB execute error: ' . $e->getMessage()]);
        return;
    }
    if ($ins->errno) {
        echo json_encode(['success' => false, 'message' => 'DB insert error: ' . $ins->error]);
        return;
    }

    if ($ins->affected_rows > 0) {
        $conn->commit(); // FIX E-05: commit the student number transaction
        $newStudentId = $ins->insert_id;
        
        // Create pending TOR evaluation record for transferee
        $torStmt = $conn->prepare("
            INSERT INTO tor_evaluations (student_id, status, credited_units, approved_units)
            VALUES (?, 'Pending', 0, 0)
            ON DUPLICATE KEY UPDATE status = 'Pending', updated_at = NOW()
        ");
        $torStmt->bind_param("i", $newStudentId);
        $torStmt->execute();

        // Create initial payment log entry
        if ($paymentMethod === 'Cash') {
            $logStmt = $conn->prepare("
                INSERT INTO payment_logs (student_id, payment_method, gcash_reference, gcash_amount, semester, status)
                VALUES (?, 'Cash', '', 0, ?, 'Pending')
            ");
            $logStmt->bind_param("is", $newStudentId, $semester);
            $logStmt->execute();
        }

        // FIX E-GUARDIAN-TRANSFEREE: registerTransferee never wrote to student_guardians.
        // Guardian email typed in the enrollment form was silently discarded.
        // Use ON DUPLICATE KEY UPDATE so retries also refresh the email.
        if ($guardianName || $guardianEmail) {
            $gIns = $conn->prepare("
                INSERT INTO student_guardians
                    (student_id, guardian_name, address, contact, email, relationship, is_emergency)
                VALUES (?, ?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE
                    guardian_name  = IF(VALUES(guardian_name) != '', VALUES(guardian_name), guardian_name),
                    address        = IF(VALUES(address)        != '', VALUES(address),        address),
                    contact        = IF(VALUES(contact)        != '', VALUES(contact),        contact),
                    email          = IF(VALUES(email)          != '', VALUES(email),          email),
                    relationship   = IF(VALUES(relationship)   != '', VALUES(relationship),   relationship)
            ");
            $gIns->bind_param("isssss", $newStudentId, $guardianName, $guardianAddress, $guardianContact, $guardianEmail, $guardianRelationship);
            $gIns->execute();
            $gIns->close();
        }

                echo json_encode([
            'success'         => true,
            'message'         => 'Transferee registered successfully. Please submit your TOR (Transcript of Records) for evaluation.',
            'student_id'      => $newStudentId,
            'student_number'  => $studentNumber,
            'student_type'    => $studentType,
            'payment_method'  => $paymentMethod,
            'tor_status'      => 'Pending',
            'next_step'       => 'submit_tor',
            'instructions'    => 'As a transferee, you need to submit your TOR for evaluation by the Registrar before proceeding to payment and enrollment.'
        ]);
    } else {
        $conn->rollback(); // FIX E-05
        echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $conn->error]);
    }
}

// ─────────────────────────────────────────────────────────────
// REGISTER STUDENT — SHS (Grade 11 / 12)
// Wrapper: forces studentCategory='SHS', maps gradeLevel to yearLevel
// ─────────────────────────────────────────────────────────────
function registerStudentSHS($conn, $data) {
    // Force category
    $data['studentCategory'] = 'SHS';

    // Map gradeLevel to yearLevel (Grade 11 → Year 1, Grade 12 → Year 2)
    if (!empty($data['gradeLevel'])) {
        $gl = trim($data['gradeLevel']);
        if (stripos($gl, '11') !== false) {
            $data['yearLevel'] = 'Grade 11';
        } elseif (stripos($gl, '12') !== false) {
            $data['yearLevel'] = 'Grade 12';
        } else {
            $data['yearLevel'] = $gl;
        }
    } elseif (empty($data['yearLevel'])) {
        $data['yearLevel'] = 'Grade 11';
    }

    registerStudent($conn, $data);
}

// ─────────────────────────────────────────────────────────────
// REGISTER STUDENT — TVET
// Wrapper: forces studentCategory='TVET', stores tvetType
// ─────────────────────────────────────────────────────────────
function registerStudentTVET($conn, $data) {
    // Force category
    $data['studentCategory'] = 'TVET';

    // Store tvetType in the data (registerStudent already handles tvet_type via extraCols)
    if (!empty($data['tvetType']) && empty($data['tvet_type'])) {
        $data['tvet_type'] = $data['tvetType'];
    }

    registerStudent($conn, $data);
}

// ─────────────────────────────────────────────────────────────
// REGISTER STUDENT
// ─────────────────────────────────────────────────────────────
function registerStudent($conn, $data) {
    // ── Enrollment period gate ────────────────────────────────────
    // Skip gate for admin-initiated registrations (data has 'bypass_period_check')
    if (empty($data['bypass_period_check']) && !isEnrollmentOpen($conn)) {
        $p = getEnrollmentPeriodRow($conn);
        $msg = 'Enrollment is currently closed.';
        if (!empty($p['start'])) $msg .= ' Opens: ' . date('M d, Y g:i A', strtotime($p['start']));
        if (!empty($p['label'])) $msg .= ' (' . $p['label'] . ')';
        echo json_encode(['success' => false, 'message' => $msg, 'enrollment_closed' => true]);
        return;
    }
    // ─────────────────────────────────────────────────────────────
    foreach (['user_id', 'firstName', 'lastName', 'email', 'program'] as $f) {
        if (empty($data[$f])) {
            echo json_encode(['success' => false, 'message' => "Field '$f' is required"]);
            return;
        }
    }

    $user_id             = (int)$data['user_id'];
    $firstName           = trim($data['firstName']);
    $lastName            = trim($data['lastName']);
    $middleName          = trim($data['middleName']          ?? '');
    $suffix              = trim($data['suffix']              ?? '');
    $email               = trim($data['email']);
    $phone               = trim($data['phone']               ?? '');
    $dateOfBirth         = trim($data['dateOfBirth']         ?? '');
    $address             = trim($data['address']             ?? '');
    $program             = trim($data['program']);
    $studentType         = trim($data['studentType']         ?? 'New');
    $studentCategory     = trim($data['studentCategory']     ?? '');
    $enrollmentDate      = date('Y-m-d');

    // Normalize payment method
    $rawMethod     = strtolower(trim($data['paymentMethod'] ?? 'gcash'));
    $paymentMethod = ($rawMethod === 'cash') ? 'Cash' : 'GCash';

    // Payment plan: full or installment
    $rawPlan     = strtolower(trim($data['paymentPlan'] ?? 'full'));
    $paymentPlan = ($rawPlan === 'installment') ? 'installment' : 'full';

    // Semester the student is enrolling in
    $semester = trim($data['semester'] ?? '');

    // Personal info
    $lrnNo               = trim($data['lrnNo']               ?? '');
    $sex                 = trim($data['sex']                 ?? '');
    $religion            = trim($data['religion']            ?? '');
    $age                 = trim($data['age']                 ?? '');
    $placeOfBirth        = trim($data['placeOfBirth']        ?? '');
    $citizenship         = trim($data['citizenship']         ?? '');
    $motherTongue        = trim($data['motherTongue']        ?? '');
    $isIndigenous        = ($data['isIndigenous'] === 'Yes'  || $data['isIndigenous']  == 1) ? 1 : 0;
    $psaBirthCertNo      = trim($data['psaBirthCertNo']      ?? '');
    $lastSchoolAttended  = trim($data['lastSchoolAttended']  ?? '');
    $strand              = trim($data['strand']              ?? '');
    $learningDelivery    = trim($data['learningDelivery']    ?? '');

    // Special needs
    $hasSpecialNeeds     = ($data['hasSpecialNeeds']     === 'Yes' || $data['hasSpecialNeeds']     == 1) ? 1 : 0;
    $specialNeedsDetails = trim($data['specialNeedsDetails']  ?? '');
    $hasAssistiveTech    = ($data['hasAssistiveTech']    === 'Yes' || $data['hasAssistiveTech']    == 1) ? 1 : 0;
    $assistiveTechDetails= trim($data['assistiveTechDetails'] ?? '');

    // Guardian  (DB columns: guardian_name, guardian_address, guardian_contact)
    $guardianName         = trim($data['guardianName']         ?? $data['emergencyContact'] ?? '');
    $guardianAddress      = trim($data['guardianAddress']      ?? '');
    $guardianContact      = trim($data['guardianContact']      ?? $data['emergencyPhone']   ?? '');
    $guardianEmail        = trim($data['guardianEmail']        ?? '');
    $guardianRelationship = trim($data['guardianRelationship'] ?? '');

    // Emergency contact (can mirror guardian or be separate)
    $emergencyContact = $guardianName;
    $emergencyPhone   = $guardianContact;

    // Scholar
    $isScholar         = (int)($data['isScholar']         ?? 0);
    $scholarType       = trim($data['scholarType']        ?? '');
    $scholarGrantor    = trim($data['scholarGrantor']     ?? '');
    $scholarshipAmount = (float)($data['scholarshipAmount'] ?? 0);

    // Verify user exists
    $chk = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
    $chk->bind_param("i", $user_id);
    $chk->execute();
    if ($chk->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User ID ' . $user_id . ' not found.']);
        return;
    }

    // Check for existing student record
    $ex = $conn->prepare("SELECT id, student_number FROM students WHERE user_id = ? LIMIT 1");
    $ex->bind_param("i", $user_id);
    $ex->execute();
    $exRes = $ex->get_result();
    if ($exRes->num_rows > 0) {
        $existing = $exRes->fetch_assoc();
        // FIX E-04: Return success:true (idempotent) so enrollment wizard can retry
        echo json_encode([
            'success'        => true,
            'message'        => 'Student record already exists. Continuing enrollment.',
            'student_id'     => (int)$existing['id'],
            'student_number' => $existing['student_number'],
            'already_existed'=> true,
        ]);
        return;
    }

    // Generate student number — use transaction + FOR UPDATE to prevent duplicates
    $year   = date('Y');
    $prefix = "STU-$year-";
    $conn->begin_transaction();
    $like    = $prefix . '%';
    $maxStmt = $conn->prepare(
        "SELECT MAX(CAST(SUBSTRING_INDEX(student_number, '-', -1) AS UNSIGNED)) AS maxNum
          FROM students WHERE student_number LIKE ? FOR UPDATE"
    );
    $maxStmt->bind_param("s", $like);
    $maxStmt->execute();
    $maxNum        = (int)($maxStmt->get_result()->fetch_assoc()['maxNum'] ?? 0);
    $maxStmt->close();
    $studentNumber = $prefix . str_pad($maxNum + 1, 4, '0', STR_PAD_LEFT);

    $dobBind = (!empty($dateOfBirth)) ? $dateOfBirth : '';

    // Schema managed by migrate.php

    // INSERT using actual DB column names
    $ins = $conn->prepare("
        INSERT INTO students
          (user_id, student_number,
           first_name, last_name, middle_name, suffix,
           phone, date_of_birth, address,
           emergency_contact, emergency_phone,
           program, student_type, student_category, semester, enrollment_date,
           lrn_no, sex, religion, place_of_birth, citizenship, mother_tongue,
           is_indigenous, psa_birth_cert_no,
           last_school_attended, strand, learning_delivery,
           has_special_needs, special_needs_details,
           has_assistive_tech, assistive_tech_details,
           payment_plan, payment_method,
           enrollment_status, payment_status, approval_status)
        VALUES
          (?, ?,
           ?, ?, ?, ?,
           ?, ?, ?,
           ?, ?,
           ?, ?, ?, ?, ?,
           ?, ?, ?, ?, ?, ?,
           ?, ?,
           ?, ?, ?,
           ?, ?,
           ?, ?,
           ?, ?,
           'Pending', 'Pending', 'Pending')
    ");

    if (!$ins) {
        echo json_encode(['success' => false, 'message' => 'DB prepare error: ' . $conn->error]);
        return;
    }

    $ins->bind_param("isssssssssssssssssssssissssisisss",
        $user_id, $studentNumber,
        $firstName, $lastName, $middleName, $suffix,
        $phone, $dobBind, $address,
        $emergencyContact, $emergencyPhone,
        $program, $studentType, $studentCategory, $semester, $enrollmentDate,
        $lrnNo, $sex, $religion, $placeOfBirth, $citizenship, $motherTongue,
        $isIndigenous, $psaBirthCertNo,
        $lastSchoolAttended, $strand, $learningDelivery,
        $hasSpecialNeeds, $specialNeedsDetails,
        $hasAssistiveTech, $assistiveTechDetails,
        $paymentPlan, $paymentMethod
    );

    try { $ins->execute(); }
    catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'DB execute error: ' . $e->getMessage()]);
        return;
    }
    if ($ins->errno) {
        echo json_encode(['success' => false, 'message' => 'DB insert error: ' . $ins->error]);
        return;
    }

    if ($ins->affected_rows > 0) {
        $conn->commit(); // FIX E-05: commit the student number transaction
        $newStudentId = $ins->insert_id;
        // Save guardian to student_guardians table
        // FIX: Save if ANY guardian field is provided (name OR email), not just when name exists.
        // This prevents the "No guardian email on file" warning in Registrar pending-approvals
        // when the student fills in email but the guardianName check silently skipped the insert.
        if ($guardianName || $guardianEmail || $guardianContact) {
            $gIns = $conn->prepare("
                INSERT INTO student_guardians
                    (student_id, guardian_name, address, contact, email, relationship, is_emergency)
                VALUES (?, ?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE
                    guardian_name  = IF(VALUES(guardian_name) != '', VALUES(guardian_name), guardian_name),
                    address        = IF(VALUES(address)        != '', VALUES(address),        address),
                    contact        = IF(VALUES(contact)        != '', VALUES(contact),        contact),
                    email          = IF(VALUES(email)          != '', VALUES(email),          email),
                    relationship   = IF(VALUES(relationship)   != '', VALUES(relationship),   relationship)
            ");
            $gIns->bind_param("isssss", $newStudentId, $guardianName, $guardianAddress, $guardianContact, $guardianEmail, $guardianRelationship);
            $gIns->execute();
            $gIns->close();
        }
        // Save scholarship declaration to student_scholarships table
        // Status = 'pending' — accounting must approve before discount is applied.
        // is_active stays 0 until accounting approves.
        if ($isScholar && $scholarType) {
            $sIns = $conn->prepare("INSERT INTO student_scholarships (student_id, scholar_type, grantor, scholarship_amount, semester, is_active, status) VALUES (?, ?, ?, ?, ?, 0, 'pending')");
            $sIns->bind_param("issds", $newStudentId, $scholarType, $scholarGrantor, $scholarshipAmount, $semester);
            $sIns->execute();
            $sIns->close();
            // Mark student as scholar-pending (does NOT apply discount yet)
            $schUpd = $conn->prepare("UPDATE students SET is_scholar=1, scholar_type=?, scholar_grantor=? WHERE id=?");
            $schUpd->bind_param("ssi", $scholarType, $scholarGrantor, $newStudentId);
            $schUpd->execute();
            $schUpd->close();
        }
        if ($paymentMethod === 'Cash') {
            // Use the student's actual semester — do NOT overwrite with a hardcoded value
            $logStmt = $conn->prepare(
                "INSERT INTO payment_logs (student_id, payment_method, gcash_reference, gcash_amount, semester, status)
                 VALUES (?, 'Cash', '', 0, ?, 'Pending')"
            );
            $logStmt->bind_param("is", $newStudentId, $semester);
            $logStmt->execute();
            $logStmt->close();
        }
        echo json_encode([
            'success'        => true,
            'message'        => 'Student registered successfully',
            'student_id'     => $newStudentId,
            'student_number' => $studentNumber,
            'payment_method' => $paymentMethod,
        ]);
    } else {
        $conn->rollback(); // FIX E-05
        echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $conn->error]);
    }
}

// ─────────────────────────────────────────────────────────────
// ENROLL COURSE
// Status set to 'Enrolled' immediately (auto-approved)
// since payment was already verified by Accounting before
// the student reaches the enlistment step.
// ─────────────────────────────────────────────────────────────
function enrollCourse($conn, $data) {
    $student_id = (int)($data['student_id'] ?? 0);
    $course_id  = (int)($data['course_id']  ?? 0);

    if (!$student_id || !$course_id) {
        echo json_encode(['success' => false,
            'message' => 'student_id and course_id required. Got student_id=' . $student_id . ', course_id=' . $course_id]);
        return;
    }

    // Check approval status — must be Approved to enlist
    $st = $conn->prepare("SELECT approval_status, first_name, last_name FROM students WHERE id = ? LIMIT 1");
    $st->bind_param("i", $student_id);
    $st->execute();
    $stRes = $st->get_result();
    if (!$stRes || $stRes->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Student not found (id=' . $student_id . ')']);
        return;
    }
    $studentRow = $stRes->fetch_assoc();
    if ($studentRow['approval_status'] !== 'Approved') {
        echo json_encode(['success' => false,
            'message' => 'Enrollment not approved yet. Payment status: ' . $studentRow['approval_status']]);
        return;
    }

    // Duplicate check — scoped to current semester so re-enrollment across semesters is allowed
    $dup = $conn->prepare("SELECT id FROM enrollments WHERE student_id=? AND course_id=? AND semester=? AND status IN ('Pending','Enrolled') LIMIT 1");
    $dup->bind_param("iis", $student_id, $course_id, $semester);
    $dup->execute();
    if ($dup->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Already enrolled in this course']);
        return;
    }

    // Capacity check — real-time count from enrollments table
    $cr = $conn->prepare("
        SELECT c.id, c.name, c.semester,
               COALESCE(c.capacity, 50) AS safe_capacity,
               COUNT(e.id)              AS real_enrolled
        FROM courses c
        LEFT JOIN enrollments e ON e.course_id = c.id AND e.status IN ('Pending','Enrolled')
        WHERE c.id = ?
        GROUP BY c.id
        LIMIT 1
    ");
    $cr->bind_param("i", $course_id);
    $cr->execute();
    $crRes = $cr->get_result();
    if (!$crRes || $crRes->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Course not found']);
        return;
    }
    $course = $crRes->fetch_assoc();
    if ((int)$course['real_enrolled'] >= (int)$course['safe_capacity']) {
        echo json_encode(['success' => false, 'message' => 'Course is full (' . $course['real_enrolled'] . '/' . $course['safe_capacity'] . ')']);
        return;
    }

    $notes      = trim($data['notes']    ?? '');
    $semester   = trim($data['semester'] ?? $course['semester']);
    $enrollDate = date('Y-m-d');

    // Insert as 'Enrolled' directly — payment already verified
    // ON DUPLICATE KEY UPDATE reinstates an accidentally Dropped row for this semester
    $ins = $conn->prepare("
        INSERT INTO enrollments (student_id, course_id, enrollment_date, status, semester, notes)
        VALUES (?, ?, ?, 'Enrolled', ?, ?)
        ON DUPLICATE KEY UPDATE
            status = IF(status='Dropped','Enrolled',status),
            enrollment_date = IF(status='Dropped',VALUES(enrollment_date),enrollment_date)
    ");
    $ins->bind_param("iisss", $student_id, $course_id, $enrollDate, $semester, $notes);
    $ins->execute();

    if ($ins->affected_rows > 0) {
        $eid = $ins->insert_id;

        // Only mark Enrolled if student is already Confirmed by Registrar (re-enroll/add-drop)
        $eCheck = $conn->prepare("SELECT enrollment_status FROM students WHERE id=? LIMIT 1");
        $eCheck->bind_param("i", $student_id);
        $eCheck->execute();
        $eRow = $eCheck->get_result()->fetch_assoc();
        $eCheck->close();
        if (($eRow['enrollment_status'] ?? '') === 'Confirmed') {
            $updSt = $conn->prepare("UPDATE students SET enrollment_status = 'Enrolled' WHERE id = ?");
            $updSt->bind_param("i", $student_id);
            $updSt->execute();
        }

        echo json_encode([
            'success'       => true,
            'message'       => 'Enlisted successfully! ' . $course['name'] . ' is now in your schedule.',
            'enrollment_id' => $eid,
            'status'        => 'Enrolled',
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $conn->error]);
    }
}

// ─────────────────────────────────────────────────────────────
// DROP COURSE
// ─────────────────────────────────────────────────────────────
function dropCourse($conn, $data) {
    $enrollment_id = (int)($data['enrollment_id'] ?? 0);
    $student_id    = (int)($data['student_id']    ?? 0);
    if (!$enrollment_id || !$student_id) {
        echo json_encode(['success' => false, 'message' => 'enrollment_id and student_id required']);
        return;
    }

    $st = $conn->prepare("SELECT course_id FROM enrollments WHERE id=? AND student_id=? LIMIT 1");
    $st->bind_param("ii", $enrollment_id, $student_id);
    $st->execute();
    $res = $st->get_result();
    if (!$res || $res->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Enrollment not found']);
        return;
    }
    $course_id = (int)$res->fetch_assoc()['course_id'];

    $upd = $conn->prepare("UPDATE enrollments SET status='Dropped' WHERE id=? AND student_id=?");
    $upd->bind_param("ii", $enrollment_id, $student_id);
    $upd->execute();

    if ($upd->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Course dropped successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Drop failed']);
    }
}

// ─────────────────────────────────────────────────────────────
// UPDATE PAYMENT
// ─────────────────────────────────────────────────────────────
function updatePayment($conn, $data) {
    $student_id = (int)($data['student_id'] ?? 0);
    $status     = trim($data['payment_status'] ?? 'Pending');
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'student_id required']);
        return;
    }
    $stmt = $conn->prepare("UPDATE students SET payment_status=? WHERE id=?");
    $stmt->bind_param("si", $status, $student_id);
    $stmt->execute();
    echo json_encode(['success' => true, 'message' => 'Payment updated']);
}

// ─────────────────────────────────────────────────────────────
// APPROVE ENROLLMENT (manual admin override)
// ─────────────────────────────────────────────────────────────
function approveEnrollment($conn, $data) {
    $student_id = (int)($data['student_id'] ?? 0);
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'student_id required']);
        return;
    }
    $stmt = $conn->prepare("UPDATE students SET approval_status='Approved', enrollment_status='Confirmed' WHERE id=?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();

    // Pass semester from DB so autoEnrollAll can match courses correctly
    $semRow = (($_r=$conn->query("SELECT semester FROM students WHERE id=$student_id LIMIT 1")) ? $_r->fetch_assoc() : null);
    $semester = trim($semRow['semester'] ?? '');

    $enrolled = autoEnrollAll($conn, ['student_id' => $student_id, 'semester' => $semester], false);
    logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'APPROVE_ENROLLMENT', 'student', $student_id,
        "Enrollment approved for student ID $student_id. Auto-enrolled $enrolled subjects.");
    echo json_encode(['success' => true, 'message' => 'Enrollment approved', 'auto_enrolled' => $enrolled]);
}
// ─────────────────────────────────────────────────────────────
// GET ENROLLMENT SUMMARY
// Returns full enrollment details for the summary tab
// ─────────────────────────────────────────────────────────────
function getEnrollmentSummary($conn) {
    $student_id = getStudentIdFromRequest($conn);
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        return;
    }

    // 1. Student info
    $stmt = $conn->prepare("SELECT s.id, s.user_id, s.student_number, s.first_name, s.last_name, s.middle_name, s.suffix, s.date_of_birth, s.age, s.sex, s.address, s.phone, s.program, s.year_level, s.semester, s.student_category, s.student_type, s.enrollment_status, s.approval_status, s.payment_status, s.payment_method, s.payment_plan, s.enrollment_date, s.strand, s.learning_delivery, s.last_school_attended, s.gpa, s.profile_picture, s.is_scholar, s.scholar_type, s.scholar_grantor, s.scholarship_amount, s.has_special_needs, s.special_needs_details, s.has_assistive_tech, s.assistive_tech_details, u.email AS user_email FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ? LIMIT 1");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $s = $stmt->get_result()->fetch_assoc();
    if (!$s) { echo json_encode(['success' => false, 'message' => 'Student not found']); return; }

    // 2. Enrolled courses
    $cStmt = $conn->prepare("
        SELECT c.code, c.name, c.credits,
               COALESCE(c.lec_units, c.credits) AS lec_units,
               COALESCE(c.lab_units, 0)         AS lab_units,
               COALESCE(c.is_general, 0)        AS is_general,
               COALESCE(c.is_lab, 0)            AS is_lab,
               COALESCE(
                NULLIF(TRIM(CONCAT(COALESCE(fj.first_name,''),' ',COALESCE(fj.last_name,''))), ''),
                NULLIF(TRIM(CONCAT(COALESCE(f.first_name,''), ' ',COALESCE(f.last_name,''))), ''),
                NULLIF(TRIM(CONCAT(COALESCE(fc.first_name,''),' ',COALESCE(fc.last_name,''))), ''),
                ''
            ) AS instructor,
               cs.day, CONCAT(cs.time_start,' - ',cs.time_end) AS time,
               r.room_name AS room, c.semester, e.status
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN course_sections cs ON cs.course_id = c.id AND cs.is_active = 1
        LEFT JOIN faculty f  ON f.user_id  = cs.faculty_id
        LEFT JOIN faculty fc ON fc.user_id = c.faculty_id
        LEFT JOIN faculty fj ON fj.status = 'Active'
            AND (
                JSON_CONTAINS(fj.subjects, CONCAT(CHAR(34), SUBSTRING_INDEX(c.code,'-',1), CHAR(34)))
                OR JSON_CONTAINS(fj.subjects, CONCAT(CHAR(34), c.code, CHAR(34)))
            )
        LEFT JOIN rooms r ON r.id = cs.room_id
        WHERE e.student_id = ? AND e.status IN ('Pending','Enrolled')
        GROUP BY e.id
        ORDER BY c.code
    ");
    $cStmt->bind_param("i", $student_id);
    $cStmt->execute();
    $cResult = $cStmt->get_result();
    $courses = []; $totalCredits = 0;
    while ($r = $cResult->fetch_assoc()) {
        $lec = (int)($r['lec_units'] ?? 0);
        $lab = (int)($r['lab_units'] ?? 0);
        $cred = (int)$r['credits'];
        if ($lec === 0 && $lab === 0 && $cred > 0) $lec = $cred;
        $r['code']      = cleanCode($r['code']);
        $r['lecUnits']  = $lec;
        $r['labUnits']  = $lab;
        $r['isGeneral'] = (bool)($r['is_general'] ?? false);
        $r['isLab']     = (bool)($r['is_lab'] ?? false);
        $courses[]      = $r;
        $totalCredits  += $cred;
    }

    // ── Resolve student category and type ─────────────────────────────────────
    $studentCatUp  = strtoupper(trim($s['student_category'] ?? ''));
    $studentTypeLC = strtolower(trim($s['student_type']     ?? 'new'));
    $isSHS         = ($studentCatUp === 'SHS');
    $isTVET        = ($studentCatUp === 'TVET');
    $isSHSorTVET   = ($isSHS || $isTVET);

    // ── Correct year level display for SHS ────────────────────────────────────
    $rawYL = trim($s['year_level'] ?? '');
    if ($isSHS) {
        if (stripos($rawYL, '12') !== false) {
            $displayYearLevel = 'Grade 12';
        } else {
            $displayYearLevel = 'Grade 11';
        }
    } elseif ($isTVET) {
        $displayYearLevel = $s['program'] ?: 'TVET';
    } else {
        $displayYearLevel = $rawYL ?: '1st Year';
    }

    // 3. Semester — from student record or enrolled courses
    $semester = trim($s['semester'] ?? '');
    if ($semester === '') {
        $semStmt = $conn->prepare("SELECT c.semester FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE e.student_id = ? AND e.status IN ('Pending','Enrolled') ORDER BY e.created_at DESC LIMIT 1");
        $semStmt->bind_param("i", $student_id);
        $semStmt->execute();
        $semRow   = $semStmt->get_result()->fetch_assoc();
        $semester = $semRow['semester'] ?? ('1st Semester, AY ' . date('Y') . '-' . (date('Y') + 1));
    }

    // ── 4. Resolve payment plan ───────────────────────────────────────────────
    $rawPlan     = trim($s['payment_plan'] ?? 'full') ?: 'full';
    $paymentPlan = ($rawPlan === 'installment') ? 'installment' : 'full';

    // ── 5. Fee computation — branched by student category ─────────────────────
    //
    //  SHS / TVET non-transferee  → ₱0 (K-12 Gov subsidy / TESDA)
    //  SHS / TVET transferee      → flat rate from fee_config
    //  College                    → unit-based formula from tuition_fees
    //
    if ($isSHSorTVET && $studentTypeLC !== 'transferee') {
        // ── FREE students ──────────────────────────────────────────────────────
        $totalAssessment = 0.0;
        $totalPaid       = 0.0;
        $payment = [
            'units'           => 0,
            'tuitionFee'      => 0,
            'miscellaneousFee'=> 0,
            'registrationFee' => 0,
            'laboratoryFee'   => 0,
            'energyFee'       => 0,
            'subtotal'        => 0,
            'totalFee'        => 0,
            'scholarDiscount' => 0,
            'installmentFee'  => 0,
            'amountDue'       => 0,
            'amountPaid'      => 0,
            'balance'         => 0,
            'status'          => 'Free',
            'method'          => '',
            'paymentDate'     => null,
            'isFree'          => true,
            'freeLabel'       => $isSHS
                ? 'Free – K-12 Government Subsidy (SHS Voucher)'
                : 'Free – TESDA Government Scholarship (PESFA/STEP)',
        ];
        $termPayments = [];

    } elseif ($isSHSorTVET && $studentTypeLC === 'transferee') {
        // ── TRANSFEREE flat rate ───────────────────────────────────────────────
        $fcCat    = $isSHS ? 'SHS' : 'TVET';
        $fcTR     = loadFeeConfig($conn, $fcCat);
        $flatRate = (float)($fcTR['transferee_flat_rate']['value'] ?? 20000);
        $instFee  = ($paymentPlan === 'installment')
                    ? (float)($fcTR['installment_fee']['value'] ?? 750)
                    : 0.0;
        $totalAssessment = $flatRate + $instFee;

        // Actual amount paid
        $paidStmtTR = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS p FROM installment_payments WHERE student_id=?");
        $paidStmtTR->bind_param('i', $student_id);
        $paidStmtTR->execute();
        $totalPaid = (float)($paidStmtTR->get_result()->fetch_assoc()['p'] ?? 0);
        $paidStmtTR->close();
        $balance    = max(0.0, $totalAssessment - $totalPaid);
        $payStatus  = $balance <= 0 ? 'Fully Paid' : ($totalPaid > 0 ? 'Partial' : 'Pending');

        $payment = [
            'units'           => 0,
            'tuitionFee'      => 0,
            'miscellaneousFee'=> 0,
            'registrationFee' => 0,
            'laboratoryFee'   => 0,
            'energyFee'       => 0,
            'subtotal'        => $flatRate,
            'totalFee'        => $totalAssessment,
            'scholarDiscount' => 0,
            'installmentFee'  => $instFee,
            'amountDue'       => $totalAssessment,
            'amountPaid'      => $totalPaid,
            'balance'         => $balance,
            'status'          => $payStatus,
            'method'          => $s['payment_method'] ?? 'GCash',
            'paymentDate'     => null,
            'isFlatRate'      => true,
            'flatRateLabel'   => 'Government Transferee Flat Rate',
        ];

        // Term payment schedule for transferees
        $psPlanRow = $conn->query("SELECT payment_type FROM payment_schedules WHERE student_id=$student_id ORDER BY id DESC LIMIT 1");
        if ($psPlanRow && $psPR = $psPlanRow->fetch_assoc() && ($psPR['payment_type'] ?? '') === 'installment') {
            $paymentPlan = 'installment';
        }
        $ipResTR = $conn->query("SELECT exam_period, SUM(amount) AS amt, MAX(payment_date) AS pay_date, MAX(or_ar_number) AS or_no FROM installment_payments WHERE student_id=$student_id GROUP BY exam_period");
        $termMapTR = [];
        if ($ipResTR) { while ($r = $ipResTR->fetch_assoc()) $termMapTR[$r['exam_period']] = $r; }
        $termPayments = [];
        if ($paymentPlan === 'installment') {
            $tAmt = $totalAssessment > 0 ? (int)ceil($totalAssessment / 4) : 0;
            foreach (['Downpayment','Prelim','Midterm','Finals'] as $term) {
                $pd = isset($termMapTR[$term]) ? (float)$termMapTR[$term]['amt'] : 0;
                $termPayments[] = ['term'=>$term,'amountDue'=>$tAmt,'amountPaid'=>$pd,
                    'paymentDate'=>$termMapTR[$term]['pay_date']??null,'orNumber'=>$termMapTR[$term]['or_no']??'',
                    'status'=>$pd>=$tAmt?'Paid':($pd>0?'Partial':'Unpaid')];
            }
        } else {
            $pd = isset($termMapTR['Full']) ? (float)$termMapTR['Full']['amt'] : $totalPaid;
            $termPayments[] = ['term'=>'Full Payment','amountDue'=>$totalAssessment,'amountPaid'=>$pd,
                'paymentDate'=>$termMapTR['Full']['pay_date']??null,'orNumber'=>$termMapTR['Full']['or_no']??'',
                'status'=>$pd>=$totalAssessment?'Paid':($pd>0?'Partial':'Unpaid')];
        }

    } else {
        // ── COLLEGE students (unit-based fees) ────────────────────────────────
        // 4a. Real fees from tuition_fees table (set by Accounting)
        $tfStmt = $conn->prepare("SELECT * FROM tuition_fees WHERE student_id = ? LIMIT 1");
        $tfStmt->bind_param("i", $student_id);
        $tfStmt->execute();
        $tf = $tfStmt->get_result()->fetch_assoc();

        // 4b. Real amount paid
        $paidStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS total_paid FROM installment_payments WHERE student_id = ? AND semester=(SELECT semester FROM students WHERE id=? LIMIT 1)");
        $paidStmt->bind_param("ii", $student_id, $student_id);
        $paidStmt->execute();
        $totalPaid = (float)($paidStmt->get_result()->fetch_assoc()['total_paid'] ?? 0);
        // FIX E-07: Add verified GCash payments not yet in installment_payments
        $plStmt = $conn->prepare("
            SELECT COALESCE(SUM(pl.gcash_amount),0) AS pl_paid
            FROM payment_logs pl
            WHERE pl.student_id = ?
              AND pl.status = 'Verified'
              AND pl.semester = (SELECT semester FROM students WHERE id=? LIMIT 1)
              AND NOT EXISTS (
                  SELECT 1 FROM installment_payments ip
                  WHERE ip.payment_log_id = pl.id AND ip.student_id = ?
              )
        ");
        $plStmt->bind_param("iii", $student_id, $student_id, $student_id);
        $plStmt->execute();
        $totalPaid += (float)($plStmt->get_result()->fetch_assoc()['pl_paid'] ?? 0);

        if ($tf) {
            $totalAssessment = (float)$tf['total_assessment'];
            $discount        = (float)($tf['discount'] ?? 0);
            $balance         = max(0.0, $totalAssessment - $totalPaid);
            $payStatus       = $balance <= 0 ? 'Fully Paid' : ($totalPaid > 0 ? 'Partial' : ($s['payment_status'] ?? 'Pending'));
            $payment = [
                'units'           => (int)$tf['units'],
                'tuitionFee'      => (float)$tf['tuition_fee'],
                'miscellaneousFee'=> (float)$tf['miscellaneous_fee'],
                'registrationFee' => (float)$tf['registration_fee'],
                'laboratoryFee'   => (float)$tf['laboratory_fee'],
                'energyFee'       => (float)$tf['energy_fee'],
                'subtotal'        => (float)$tf['subtotal'],
                'totalFee'        => $totalAssessment,
                'scholarDiscount' => $discount,
                'installmentFee'  => (float)($tf['installment_fee'] ?? 0),
                'amountDue'       => $totalAssessment,
                'amountPaid'      => $totalPaid,
                'balance'         => $balance,
                'status'          => $payStatus,
                'method'          => $s['payment_method'] ?? 'GCash',
                'paymentDate'     => null,
            ];
        } else {
            // Fallback if no tuition_fees record — use fee_config rates
            $fc_fb = loadFeeConfig($conn, 'College');
            $totalAssessment = $totalCredits * (float)($fc_fb['tuition_rate_per_unit']['value'] ?? 650)
                             + (float)($fc_fb['misc_fee']['value'] ?? 6688)
                             + (float)($fc_fb['reg_fee']['value']  ?? 700)
                             + $totalCredits * (float)($fc_fb['energy_rate_per_unit']['value'] ?? 63);
            $schRow  = $conn->query("SELECT COALESCE(SUM(scholarship_amount),0) AS total FROM student_scholarships WHERE student_id = {$s['id']} AND is_active = 1");
            $discount = (float)($schRow ? $schRow->fetch_assoc()['total'] : 0);
            $balance  = max(0.0, $totalAssessment - $discount - $totalPaid);
            $payment  = [
                'totalFee'        => $totalAssessment,
                'scholarDiscount' => $discount,
                'amountDue'       => max(0, $totalAssessment - $discount),
                'amountPaid'      => $totalPaid,
                'balance'         => $balance,
                'status'          => $s['payment_status'] ?? 'Pending',
                'method'          => $s['payment_method'] ?? 'GCash',
                'paymentDate'     => null,
            ];
        }

        // 6. Real term payments from installment_payments
        $ipRes   = $conn->query("SELECT ip.exam_period, SUM(ip.amount) AS amt, MAX(ip.payment_date) AS pay_date, MAX(ip.or_ar_number) AS or_no FROM installment_payments ip JOIN students _st ON _st.id=ip.student_id WHERE ip.student_id = $student_id AND ip.semester=_st.semester GROUP BY ip.exam_period");
        $termMap = [];
        if ($ipRes) { while ($r = $ipRes->fetch_assoc()) $termMap[$r['exam_period']] = $r; }

        $psPlanRow = $conn->query("SELECT payment_type FROM payment_schedules WHERE student_id = $student_id ORDER BY id DESC LIMIT 1");
        if ($psPlanRow && $psPR = $psPlanRow->fetch_assoc()) {
            if (($psPR['payment_type'] ?? '') === 'installment') {
                $paymentPlan = 'installment';
            }
        }
        $termPayments = [];
        if ($paymentPlan === 'installment') {
            $termAmt = $totalAssessment > 0 ? (int)ceil($totalAssessment / 4) : 0;
            foreach (['Downpayment', 'Prelim', 'Midterm', 'Finals'] as $term) {
                $paid = isset($termMap[$term]) ? (float)$termMap[$term]['amt'] : 0;
                $termPayments[] = [
                    'term'        => $term,
                    'amountDue'   => $termAmt,
                    'amountPaid'  => $paid,
                    'paymentDate' => $termMap[$term]['pay_date'] ?? null,
                    'orNumber'    => $termMap[$term]['or_no']    ?? '',
                    'status'      => $paid >= $termAmt ? 'Paid' : ($paid > 0 ? 'Partial' : 'Unpaid'),
                ];
            }
        } else {
            $paid = isset($termMap['Full']) ? (float)$termMap['Full']['amt'] : $totalPaid;
            $termPayments[] = [
                'term'        => 'Full Payment',
                'amountDue'   => $totalAssessment,
                'amountPaid'  => $paid,
                'paymentDate' => $termMap['Full']['pay_date'] ?? null,
                'orNumber'    => $termMap['Full']['or_no']    ?? '',
                'status'      => $paid >= $totalAssessment ? 'Paid' : ($paid > 0 ? 'Partial' : 'Unpaid'),
            ];
        }
    } // end College fee block

    echo json_encode([
        'success'        => true,
        'enrollmentDate' => $s['enrollment_date'],
        'semester'       => $semester,
        'program'        => $s['program'],
        'yearLevel'      => $displayYearLevel,    // Grade 11/12 for SHS; NC level for TVET
        'strand'         => $s['strand']           ?? null,   // SHS strand (e.g. STEM, ABM)
        'learningDelivery'=> $s['learning_delivery'] ?? null, // Face-to-Face / Modular
        'totalCourses'   => count($courses),
        'totalCredits'   => $totalCredits,
        'courses'        => $courses,
        'payment'        => $payment,
        'termPayments'   => $termPayments,
        // Category flags — Angular uses these to conditionally show/hide UI sections
        'isSHS'          => $isSHS,
        'isTVET'         => $isTVET,
        'isSHSorTVET'    => $isSHSorTVET,
        'allowAddDrop'   => !$isSHSorTVET,   // SHS/TVET have no add/drop window
    ]);
}

// ─────────────────────────────────────────────────────────────
// AUTO ENROLL ALL — router: picks the right strategy per student type.
//
//  • Transferee  → autoEnrollTransferee()
//    Strictly respects TOR evaluation: credited courses are NEVER
//    enrolled (they already have a 'Dropped' row from evaluateTOR).
//    Only courses that are NOT credited and NOT yet enrolled are inserted.
//
//  • Regular (New / Old / Continuing / Returning) → autoEnrollRegular()
//    Enrolls all program courses for the student's semester that are
//    not yet in enrollments at all.
//
// Both helpers use INSERT IGNORE so the UNIQUE KEY(student_id,course_id)
// never causes a silent failure that wipes out the whole batch.
// Safe to call multiple times.

// ═════════════════════════════════════════════════════════════════
//  AUTO-ENROLLMENT — NEW / REGULAR STUDENTS
//  (student_type: New, Old, Continuing, Returning)
//
//  Enrolls all program courses for the student's semester that are
//  not yet in enrollments. Uses INSERT IGNORE so re-runs are safe.
//  Does NOT touch TOR logic at all.
// ═════════════════════════════════════════════════════════════════
// ═════════════════════════════════════════════════════════════════
//  PREREQUISITE GATE
//  Returns true if the student has passed ALL prerequisites of a course.
//  "Passed" = final grade ≤ 3.0 in student_grades (lower = better in PH
//  grading), OR enrollment status = 'Completed' with no failing grade on record.
//  Returns true (open gate) when the course has no prerequisites defined,
//  or when the course_prerequisites table doesn't exist yet (migration not run).
// ═════════════════════════════════════════════════════════════════
function studentPassedPrerequisites(mysqli $conn, int $student_id, int $course_id): bool {
    // Guard: table may not exist before migration is run
    $tableCheck = $conn->query("SHOW TABLES LIKE 'course_prerequisites'");
    if (!$tableCheck || $tableCheck->num_rows === 0) return true;

    $pStmt = $conn->prepare("SELECT prerequisite_id FROM course_prerequisites WHERE course_id = ?");
    $pStmt->bind_param('i', $course_id);
    $pStmt->execute();
    $pRes = $pStmt->get_result();
    $pStmt->close();

    if ($pRes->num_rows === 0) return true; // no prerequisites defined

    while ($prereqRow = $pRes->fetch_assoc()) {
        $prereq_id = (int)$prereqRow['prerequisite_id'];
        $passed = false;

        // Check 1: student_grades has a passing grade (≤ 3.0; lower = better)
        $gradeStmt = $conn->prepare("
            SELECT sg.grade
            FROM   student_grades sg
            JOIN   enrollments e ON sg.enrollment_id = e.id
            WHERE  sg.student_id = ? AND e.course_id = ?
            ORDER  BY sg.updated_at DESC, sg.id DESC
            LIMIT  1
        ");
        $gradeStmt->bind_param('ii', $student_id, $prereq_id);
        $gradeStmt->execute();
        $gradeRow = $gradeStmt->get_result()->fetch_assoc();
        $gradeStmt->close();
        if ($gradeRow !== null) {
            $passed = ((float)$gradeRow['grade'] <= 3.0);
        }

        // Check 2: Completed enrollment with no failing grade on record
        if (!$passed) {
            $complStmt = $conn->prepare("
                SELECT e.id FROM enrollments e
                WHERE  e.student_id = ? AND e.course_id = ? AND e.status = 'Completed'
                  AND  NOT EXISTS (
                      SELECT 1 FROM student_grades sg2
                      WHERE  sg2.enrollment_id = e.id AND sg2.grade > 3.0
                  )
                LIMIT 1
            ");
            $complStmt->bind_param('ii', $student_id, $prereq_id);
            $complStmt->execute();
            $passed = ($complStmt->get_result()->num_rows > 0);
            $complStmt->close();
        }

        if (!$passed) return false; // at least one prerequisite not met
    }
    return true; // all prerequisites passed
}

function autoEnrollNew($conn, $data, $respondJson = true) {
    // Ensure year_level column exists (safe no-op if already present)
    $student_id = (int)($data['student_id'] ?? 0);
    if (!$student_id) {
        if ($respondJson) echo json_encode(['success' => false, 'message' => 'student_id required']);
        return 0;
    }

    $st = $conn->prepare("SELECT program, semester, year_level, student_type, enrollment_status FROM students WHERE id = ? LIMIT 1");
    $st->bind_param("i", $student_id);
    $st->execute();
    $student = $st->get_result()->fetch_assoc();
    if (!$student) {
        if ($respondJson) echo json_encode(['success' => false, 'message' => 'Student not found']);
        return 0;
    }

    // Only auto-enroll once Registrar has set enrollment_status = 'Enrolled'.
    // The Angular frontend calls auto_enroll_all on every page load — without this
    // guard it inserts enrollment rows before Registrar approval, bypassing the workflow.
    if (($student['enrollment_status'] ?? '') !== 'Enrolled') {
        if ($respondJson) echo json_encode([
            'success'  => true,
            'enrolled' => 0,
            'program'  => $student['program'],
            'message'  => 'Waiting for Registrar approval before auto-enrolling courses.',
        ]);
        return 0;
    }

    $semester    = trim($data['semester'] ?? $student['semester'] ?? '');
    $programName = trim($student['program']);
    $yearLevel   = trim($student['year_level'] ?? '1st Year');
    $semesterTerm = '';
    if (preg_match('/^(1st Semester|2nd Semester|Summer)/i', $semester, $m)) {
        $semesterTerm = $m[1];
    }
    if ($semesterTerm === '') $semesterTerm = '1st Semester'; // safety default

    // ── IDEMPOTENCY CHECK ─────────────────────────────────────
    // FIX RE-ENROLL-SUBJECTS-01: Only skip auto-enroll if the student ALREADY has
    // active (Enrolled/Pending) rows for the correct year_level + semester.
    // Re-enrolled (reEnroll()) students have enrollment_status='Pending' and all
    // their old rows are now 'Completed' — so the count will be 0 and we proceed.
    // We also verify the student's stored semester matches the target semester to
    // prevent stale idempotency blocks after a semester advance.
    $ylEsc  = $yearLevel;
    $semEsc = $semesterTerm;

    // Only run the idempotency skip if the student's DB semester matches our target.
    // After reEnroll(), students.semester is already set to the new label, so this
    // ensures we NEVER skip enrollment for a freshly re-enrolled student.
    $storedSemStmt = $conn->prepare("SELECT semester, enrollment_status FROM students WHERE id = ? LIMIT 1");
    $storedSemStmt->bind_param('i', $student_id);
    $storedSemStmt->execute();
    $storedSemRow  = $storedSemStmt->get_result()->fetch_assoc();
    $storedSemStmt->close();
    $storedSemTerm  = '';
    $storedEnrollSt = $storedSemRow['enrollment_status'] ?? '';
    if (preg_match('/^(1st Semester|2nd Semester|Summer)/i', $storedSemRow['semester'] ?? '', $_sm2)) {
        $storedSemTerm = preg_replace('/\s+/', ' ', trim($_sm2[1]));
    }

    $semTermsMatch = (strcasecmp($storedSemTerm, $semEsc) === 0);

    $alreadyCorrect = 0;
    if ($semTermsMatch) {
        $chkStmt = $conn->prepare("
            SELECT COUNT(*) AS cnt FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            WHERE e.student_id = ?
              AND e.status IN ('Enrolled','Pending')
              AND c.year_level = ?
              AND c.semester LIKE ?
        ");
        $semEscLike = $semEsc . '%';
        $chkStmt->bind_param('iss', $student_id, $ylEsc, $semEscLike);
        $chkStmt->execute();
        $alreadyCorrect = (int)$chkStmt->get_result()->fetch_assoc()['cnt'];
        $chkStmt->close();
    }

    if ($alreadyCorrect > 0) {
        if ($respondJson) echo json_encode([
            'success'  => true,
            'enrolled' => 0,
            'program'  => $programName,
            'message'  => 'Already enrolled in correct courses for this semester.',
        ]);
        return 0;
    }

    // Collect courses — no credits to exclude for regular students
    $courses = collectProgramCourses($conn, $programName, $semesterTerm, $yearLevel, $student_id, []);

    // Filter out courses whose prerequisites the student has not yet passed
    $eligibleCourses = [];
    $skippedCourses  = [];
    foreach ($courses as $course) {
        if (studentPassedPrerequisites($conn, $student_id, (int)$course['id'])) {
            $eligibleCourses[] = $course;
        } else {
            $skippedCourses[] = $course['name'] ?? $course['code'] ?? $course['id'];
        }
    }

    $enrolled = insertEnrollments($conn, $student_id, $eligibleCourses, $semester, 'Auto-enrolled');

    // Courses enrolled — enrollment_status stays as-is (Registrar sets Enrolled)
    // Do NOT set Enrolled here — Registrar must approve first

    if ($respondJson) {
        $message = $enrolled > 0
            ? "$enrolled course(s) auto-enrolled for $programName."
            : 'Already enrolled in all available courses for this program.';
        if (!empty($skippedCourses)) {
            $message .= ' Skipped ' . count($skippedCourses) . ' course(s) due to unmet prerequisites: '
                      . implode(', ', array_slice($skippedCourses, 0, 5))
                      . (count($skippedCourses) > 5 ? '…' : '.');
        }
        echo json_encode([
            'success'        => true,
            'enrolled'       => $enrolled,
            'skipped_prereq' => count($skippedCourses),
            'program'        => $programName,
            'message'        => $message,
        ]);
    }
    return $enrolled;
}

// ═════════════════════════════════════════════════════════════════
//  AUTO-ENROLLMENT — TRANSFEREE STUDENTS
//
//  Rules:
//    1. TOR not yet Evaluated  → enroll NOTHING (wait for registrar).
//    2. TOR Evaluated          → enroll ONLY non-credited courses.
//       Credited courses already have a 'Dropped' row (inserted by
//       evaluateTOR in registrar.php). INSERT IGNORE skips them
//       silently. We also exclude them from the SELECT explicitly
//       so the enrolled count is clean and accurate.
// ═════════════════════════════════════════════════════════════════
function autoEnrollTransfereeAction($conn, $data, $respondJson = true) {
    $student_id = (int)($data['student_id'] ?? 0);
    if (!$student_id) {
        if ($respondJson) echo json_encode(['success' => false, 'message' => 'student_id required']);
        return 0;
    }

    $st = $conn->prepare("SELECT program, semester, year_level, enrollment_status FROM students WHERE id = ? LIMIT 1");
    $st->bind_param("i", $student_id);
    $st->execute();
    $student = $st->get_result()->fetch_assoc();
    if (!$student) {
        if ($respondJson) echo json_encode(['success' => false, 'message' => 'Student not found']);
        return 0;
    }

    // Only auto-enroll once Registrar has confirmed (enrollment_status = 'Enrolled').
    if (($student['enrollment_status'] ?? '') !== 'Enrolled') {
        if ($respondJson) echo json_encode([
            'success'  => true,
            'enrolled' => 0,
            'program'  => $student['program'],
            'message'  => 'Waiting for Registrar approval before auto-enrolling courses.',
        ]);
        return 0;
    }

    $semester    = trim($data['semester'] ?? $student['semester'] ?? '');
    $programName = trim($student['program']);
    $yearLevel   = trim($student['year_level'] ?? '1st Year');

    // ── Step 1: Require evaluated TOR ────────────────────────
    $torQ = $conn->prepare("
        SELECT status, credited_subjects, credited_course_ids
        FROM tor_evaluations
        WHERE student_id = ? ORDER BY id DESC LIMIT 1
    ");
    $torQ->bind_param("i", $student_id);
    $torQ->execute();
    $torRow = $torQ->get_result()->fetch_assoc();

    if (!$torRow || $torRow['status'] !== 'Evaluated') {
        if ($respondJson) echo json_encode([
            'success'  => false,
            'enrolled' => 0,
            'message'  => 'TOR has not been evaluated yet. Enrollment will proceed after registrar evaluation.',
        ]);
        return 0;
    }

    // ── Step 2: Collect credited course IDs to exclude ───────
    $creditedIds = [];

    // Primary: credited_course_ids int-array JSON (e.g. [18,22,24])
    if (!empty($torRow['credited_course_ids'])) {
        $arr = json_decode($torRow['credited_course_ids'], true);
        if (is_array($arr)) $creditedIds = array_map('intval', $arr);
    }

    // Fallback: parse credited_subjects object array
    if (empty($creditedIds) && !empty($torRow['credited_subjects'])) {
        $subs = json_decode($torRow['credited_subjects'], true);
        if (is_array($subs)) {
            foreach ($subs as $sub) {
                if (isset($sub['courseId'])) $creditedIds[] = (int)$sub['courseId'];
            }
        }
    }

    // ── Step 3: Enroll non-credited courses only ──────────────
    $semesterTerm = '';
    if (preg_match('/^(1st Semester|2nd Semester|Summer)/i', $semester, $m)) {
        $semesterTerm = $m[1];
    }
    if ($semesterTerm === '') $semesterTerm = '1st Semester'; // safety default

    $ylEscClean  = $yearLevel;
    $semEscClean = $semesterTerm;

    // ── IDEMPOTENCY CHECK ─────────────────────────────────────
    // auto_enroll_all is called on EVERY page load by the Angular frontend.
    // To prevent re-enrolling on every visit, check if the student already
    // has correct enrollments for this year+semester.
    // If they do AND there are no wrong-year/wrong-semester enrollments to clean,
    // skip the rest entirely.
    $wrongEnrollCount = (int)$conn->query("
        SELECT COUNT(*) AS cnt FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = $student_id
          AND e.status IN ('Enrolled','Pending')
          AND e.notes IN ('Auto-enrolled (Transferee)', 'Auto-enrolled')
          AND (
            (c.year_level != '$ylEscClean'  AND c.year_level != '' AND c.year_level IS NOT NULL)
            OR
            (c.semester NOT LIKE '$semEscClean%' AND c.semester != '' AND c.semester IS NOT NULL)
          )
    ")->fetch_assoc()['cnt'];

    $correctEnrollCount = (int)$conn->query("
        SELECT COUNT(*) AS cnt FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = $student_id
          AND e.status IN ('Enrolled','Pending')
          AND c.year_level = '$ylEscClean'
          AND c.semester LIKE '$semEscClean%'
    ")->fetch_assoc()['cnt'];

    // If already correctly enrolled and nothing to clean up → skip
    if ($wrongEnrollCount === 0 && $correctEnrollCount > 0) {
        if ($respondJson) echo json_encode([
            'success'       => true,
            'enrolled'      => 0,
            'program'       => $programName,
            'creditedCount' => count($creditedIds),
            'message'       => 'Already enrolled in correct courses for this semester.',
        ]);
        return 0;
    }

    // ── CLEANUP: Remove wrong-semester or wrong-year auto-enrollments ──
    $conn->query("
        DELETE e FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = $student_id
          AND e.status IN ('Enrolled','Pending')
          AND e.notes IN ('Auto-enrolled (Transferee)', 'Auto-enrolled')
          AND (
            (c.year_level != '$ylEscClean'  AND c.year_level != '' AND c.year_level IS NOT NULL)
            OR
            (c.semester NOT LIKE '$semEscClean%' AND c.semester != '' AND c.semester IS NOT NULL)
          )
    ");

    // Re-check: if correct enrollments still exist after cleanup, we're done.
    // This prevents re-enrolling on every page load after the first cleanup.
    $correctAfterCleanup = (int)$conn->query("
        SELECT COUNT(*) AS cnt FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = $student_id
          AND e.status IN ('Enrolled','Pending')
          AND c.year_level = '$ylEscClean'
          AND c.semester LIKE '$semEscClean%'
    ")->fetch_assoc()['cnt'];

    if ($correctAfterCleanup > 0) {
        if ($respondJson) echo json_encode([
            'success'       => true,
            'enrolled'      => 0,
            'program'       => $programName,
            'creditedCount' => count($creditedIds),
            'message'       => 'Enrollment verified for this semester.',
        ]);
        return 0;
    }

    $courses  = collectProgramCourses($conn, $programName, $semesterTerm, $yearLevel, $student_id, $creditedIds);
    $enrolled = insertEnrollments($conn, $student_id, $courses, $semester, 'Auto-enrolled (Transferee)');

    // Courses enrolled — enrollment_status stays as-is (Registrar sets Enrolled)
    // Do NOT set Enrolled here — Registrar must approve first

    if ($respondJson) {
        echo json_encode([
            'success'       => true,
            'enrolled'      => $enrolled,
            'program'       => $programName,
            'creditedCount' => count($creditedIds),
            'message'       => $enrolled > 0
                ? "$enrolled course(s) auto-enrolled for $programName. " . count($creditedIds) . " credited subject(s) excluded."
                : 'All available non-credited courses are already enrolled.',
        ]);
    }
    return $enrolled;
}

// ═════════════════════════════════════════════════════════════════
//  AUTO ENROLL ALL — legacy compatibility router
//  Reads student_type and calls the correct function above.
//  Kept so existing frontend calls to auto_enroll_all still work.
// ═════════════════════════════════════════════════════════════════
function autoEnrollAll($conn, $data, $respondJson = true) {
    // Ensure year_level column exists (safe no-op if already present)
    $student_id = (int)($data['student_id'] ?? 0);
    if (!$student_id) {
        if ($respondJson) echo json_encode(['success' => false, 'message' => 'student_id required']);
        return 0;
    }

    $st = $conn->prepare("SELECT student_type FROM students WHERE id = ? LIMIT 1");
    $st->bind_param("i", $student_id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if (!$row) {
        if ($respondJson) echo json_encode(['success' => false, 'message' => 'Student not found']);
        return 0;
    }

    if (trim($row['student_type']) === 'Transferee') {
        return autoEnrollTransfereeAction($conn, $data, $respondJson);
    } else {
        return autoEnrollNew($conn, $data, $respondJson);
    }
}

// ─────────────────────────────────────────────────────────────
// SHARED HELPER — collect courses for a program / semester
// from both program_courses junction table and courses.program column.
// $excludeIds  = course IDs to hard-exclude (credited subjects).
// Already-enrolled (Enrolled/Pending) rows are skipped via subquery.
// Dropped rows are NOT excluded here — INSERT IGNORE handles them.
// ─────────────────────────────────────────────────────────────
function collectProgramCourses($conn, $programName, $semesterTerm, $yearLevel, $student_id, $excludeIds) {
    $allCourses = [];
    $pn_esc     = $programName;
    $yl_esc     = $yearLevel;
    $st_esc     = $semesterTerm;

    $excludeClause = !empty($excludeIds)
        ? 'AND c.id NOT IN (' . implode(',', array_map('intval', $excludeIds)) . ')'
        : '';

    // FIX HISTORY-01: Scope the already-enrolled exclusion to ACTIVE (Enrolled/Pending)
    // rows only — AND to the current target semester. This ensures that a course the
    // student completed in a previous semester is NOT excluded from the new semester's
    // enrollment. Without semester scoping, Completed rows from e.g. AY 2026-2027
    // blocked insertEnrollments() from adding the same course in AY 2027-2028.
    $alreadyEnrolledSub = "SELECT course_id FROM enrollments
                           WHERE student_id = $student_id
                             AND status IN ('Enrolled','Pending')
                             AND semester LIKE '$st_esc%'";

    // Semester: match ONLY the term portion (e.g. '1st Semester').
    // Courses are stored under old AY values ('1st Semester, AY 2024-2025')
    // but a student enrolling in AY 2026-2027 still needs those courses.
    // LIKE '1st Semester%' matches correctly regardless of AY suffix.
    //
    // SAFETY: If semesterTerm is empty, default to '1st Semester' to prevent
    // enrolling courses from ALL semesters at once.
    if ($st_esc === '') {
        $st_esc = '1st Semester';
    }
    // Two variants:
    //   $semClause   — uses "c.semester" alias  (JOIN queries that have alias c)
    //   $semClauseNJ — uses "semester" bare      (FROM courses queries, no alias)
    $semClause   = "AND c.semester LIKE '$st_esc%'";
    $semClauseNJ = "AND semester   LIKE '$st_esc%'";

    // Two variants for the exclude list too (same alias reason):
    //   $excludeClause   — uses "c.id NOT IN ..."  (JOIN queries)
    //   $excludeClauseNJ — uses "id NOT IN ..."    (bare FROM courses queries)
    $excludeClauseNJ = !empty($excludeIds)
        ? 'AND id NOT IN (' . implode(',', array_map('intval', $excludeIds)) . ')'
        : '';

    // ── Year level column guard ──────────────────────────────────────────────
    // The year_level column was added after initial deployment.  Older installs
    // won't have it.  We:
    //   1) Attempt to ADD it (no-op if it already exists — MariaDB IF NOT EXISTS).
    //   2) Verify it actually exists with SHOW COLUMNS (confirms ALTER succeeded).
    //   3) Build FOUR variables so every query in this function is safe:
    //        $ylClause     — "AND c.year_level = ..."  (queries with JOIN alias c)
    //        $ylClauseNJ   — "AND year_level = ..."    (bare FROM courses queries)
    //        $selectYl     — ", c.year_level"          (JOIN SELECT list)
    //        $selectYlBare — ", year_level"            (bare SELECT list)
    //      All four collapse to "" / "NULL AS year_level" when column is absent.
    $hasYearLevelCol = true; // guaranteed by migrate.php

    $isGradeBased = (stripos($yl_esc, 'grade') !== false || preg_match('/^\d+$/', $yl_esc));
    if ($yl_esc !== '' && $hasYearLevelCol) {
        $ylClause   = $isGradeBased ? "AND c.year_level LIKE '%$yl_esc%'" : "AND c.year_level = '$yl_esc'";
        $ylClauseNJ = $isGradeBased ? "AND year_level LIKE '%$yl_esc%'"   : "AND year_level = '$yl_esc'";
    } else {
        $ylClause   = '';
        $ylClauseNJ = '';
    }
    // SELECT list helpers — NULL AS year_level is a safe placeholder when column absent
    $selectYl     = $hasYearLevelCol ? ", c.year_level"  : ", NULL AS year_level";
    $selectYlBare = $hasYearLevelCol ? ", year_level"    : ", NULL AS year_level";

    // ── Resolve program code from programs table ──────────────
    // students.program stores the FULL NAME (e.g. "Bachelor of Science in Information Technology")
    // but courses.program stores the SHORT CODE (e.g. "BSIT").
    // We resolve both so we can match either way.
    $programCode = $programName; // fallback: use as-is
    $pRow = $conn->query("
        SELECT code FROM programs
        WHERE name = '$pn_esc' OR code = '$pn_esc'
        LIMIT 1
    ");
    if ($pRow && $pRow->num_rows > 0) {
        $programCode = $pRow->fetch_assoc()['code'];
    }
    $pc_esc = $programCode;

    // Source 1: program_courses junction table (most reliable)
    $hasPCTable = $conn->query("SHOW TABLES LIKE 'program_courses'")->num_rows > 0;
    $hasPTable  = $conn->query("SHOW TABLES LIKE 'programs'")->num_rows > 0;

    if ($hasPCTable && $hasPTable) {
        $res = $conn->query("
            SELECT c.id, c.name, c.semester $selectYl
            FROM program_courses pc
            JOIN programs p ON pc.program_id = p.id
            JOIN courses  c ON pc.course_id  = c.id
            WHERE (p.name = '$pn_esc' OR p.code = '$pn_esc' OR p.code = '$pc_esc')
              AND c.id NOT IN ($alreadyEnrolledSub)
              $ylClause
              $semClause
              $excludeClause
            LIMIT 200
        ");
        if ($res) {
            foreach ($res->fetch_all(MYSQLI_ASSOC) as $c) {
                $allCourses[$c['id']] = $c;
            }
        }
    }

    // Source 2: courses.program direct column — match by FULL NAME first (primary storage),
    // then fall back to resolved CODE. This matches actual DB storage where courses.program
    // stores the full program name (e.g. "Bachelor of Science in Information Technology").
    $res = $conn->query("
        SELECT id, name, semester $selectYlBare
        FROM courses
        WHERE program = '$pn_esc'
          AND id NOT IN ($alreadyEnrolledSub)
          $ylClauseNJ
          $semClauseNJ
          $excludeClauseNJ
        LIMIT 200
    ");
    if ($res) {
        foreach ($res->fetch_all(MYSQLI_ASSOC) as $c) {
            $allCourses[$c['id']] = $c;
        }
    }

    // Source 3: if still empty, try by resolved code (legacy data compatibility)
    if (empty($allCourses) && $pc_esc !== $pn_esc) {
        $res = $conn->query("
            SELECT id, name, semester $selectYlBare
            FROM courses
            WHERE program = '$pc_esc'
              AND id NOT IN ($alreadyEnrolledSub)
              $ylClauseNJ
              $semClauseNJ
              $excludeClauseNJ
            LIMIT 200
        ");
        if ($res) {
            foreach ($res->fetch_all(MYSQLI_ASSOC) as $c) {
                $allCourses[$c['id']] = $c;
            }
        }
    }

    // FIX Source 4: Last resort — if ALL sources returned zero courses AND the
    // semester/year_level filters were applied, retry relaxing ONLY the year_level
    // filter (keep semester). This handles format mismatches (e.g. courses stored
    // without year_level) WITHOUT dumping the whole program curriculum at once.
    // Previously this removed BOTH filters, causing every subject for all years to
    // be enrolled simultaneously on re-enrollment.
    if (empty($allCourses)) {
        $res = $conn->query("
            SELECT id, name, semester $selectYlBare
            FROM courses
            WHERE (program = '$pc_esc' OR program = '$pn_esc')
              AND id NOT IN ($alreadyEnrolledSub)
              $semClauseNJ
              $excludeClauseNJ
            LIMIT 200
        ");
        if ($res) {
            foreach ($res->fetch_all(MYSQLI_ASSOC) as $c) {
                $allCourses[$c['id']] = $c;
            }
        }
        // Also try program_courses junction without year_level filter (keep semester)
        if (empty($allCourses) && $hasPCTable && $hasPTable) {
            $res = $conn->query("
                SELECT c.id, c.name, c.semester $selectYl
                FROM program_courses pc
                JOIN programs p ON pc.program_id = p.id
                JOIN courses  c ON pc.course_id  = c.id
                WHERE (p.name = '$pn_esc' OR p.code = '$pn_esc' OR p.code = '$pc_esc')
                  AND c.id NOT IN ($alreadyEnrolledSub)
                  $semClause
                  $excludeClause
                LIMIT 200
            ");
            if ($res) {
                foreach ($res->fetch_all(MYSQLI_ASSOC) as $c) {
                    $allCourses[$c['id']] = $c;
                }
            }
        }
    }

    // ABSOLUTE LAST RESORT: only if semester is also empty/unset do we drop all filters.
    // This prevents the all-units dump that happens on re-enrollment.
    if (empty($allCourses) && $st_esc === '') {
        $res = $conn->query("
            SELECT id, name, semester $selectYlBare
            FROM courses
            WHERE (program = '$pc_esc' OR program = '$pn_esc')
              AND id NOT IN ($alreadyEnrolledSub)
              $excludeClauseNJ
            LIMIT 200
        ");
        if ($res) {
            foreach ($res->fetch_all(MYSQLI_ASSOC) as $c) {
                $allCourses[$c['id']] = $c;
            }
        }
    }

    return array_values($allCourses);
}

// ─────────────────────────────────────────────────────────────
// SHARED HELPER — bulk-insert enrollment rows.
// Uses INSERT IGNORE so UNIQUE KEY(student_id,course_id) violations
// (e.g. credited 'Dropped' rows) never kill the rest of the batch.
// Returns count of newly inserted rows.
// ─────────────────────────────────────────────────────────────
function insertEnrollments($conn, $student_id, $courses, $semester, $notes) {
    $enrolled   = 0;
    $enrollDate = date('Y-m-d');

    foreach ($courses as $course) {
        $useSemester = ($semester !== '') ? $semester : ($course['semester'] ?? '');
        $cid = (int)$course['id'];

        // FIX HISTORY-01: With the new UNIQUE KEY(student_id, course_id, semester),
        // each course can appear once per semester. We no longer need to UPDATE
        // Dropped rows back to Enrolled (that was a workaround for the old 2-col key).
        // FIX ENR-01: ON DUPLICATE KEY UPDATE must also reinstate 'Completed' rows
        // for the CURRENT semester. The enrollments unique key is (student_id, course_id)
        // without semester, so a course completed in a prior semester causes a duplicate
        // key conflict. The old IF only reinstated 'Dropped' rows — 'Completed' rows were
        // left unchanged, making the student appear to have 0 enrolled subjects.
        // Fix: also flip 'Completed' → 'Enrolled' when the semester changes (i.e. the
        // incoming semester differs from the stored one), AND update the semester field
        // so the row is correctly attributed to the new term.
        $ins = $conn->prepare("
            INSERT INTO enrollments
                (student_id, course_id, enrollment_date, status, semester, notes)
            VALUES (?, ?, ?, 'Enrolled', ?, ?)
            ON DUPLICATE KEY UPDATE
                status          = IF(status IN ('Dropped','Completed'), 'Enrolled', status),
                semester        = IF(status IN ('Dropped','Completed'), VALUES(semester), semester),
                enrollment_date = IF(status IN ('Dropped','Completed'), VALUES(enrollment_date), enrollment_date),
                notes           = IF(status IN ('Dropped','Completed'), VALUES(notes), notes)
        ");
        $ins->bind_param("iisss", $student_id, $cid, $enrollDate, $useSemester, $notes);
        if ($ins->execute() && ($ins->affected_rows > 0)) {
            $enrolled++;
        }
        $ins->close();
    }

    return $enrolled;
}

function updateProfile($conn, $data) {
    $student_id       = (int)($data['student_id']       ?? 0);
    $phone            = trim($data['phone']              ?? '');
    $address          = trim($data['address']            ?? '');
    $emergencyContact = trim($data['emergencyContact']   ?? '');
    $emergencyPhone   = trim($data['emergencyPhone']     ?? '');
    $dateOfBirth          = trim($data['dateOfBirth']          ?? '');
    $guardianEmail        = trim($data['guardianEmail']        ?? '');
    $guardianRelationship = trim($data['guardianRelationship'] ?? '');

    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'student_id required']);
        return;
    }

    // Update students table
    $stmt = $conn->prepare("
        UPDATE students
        SET phone = ?, address = ?, emergency_contact = ?, emergency_phone = ?, date_of_birth = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sssssi", $phone, $address, $emergencyContact, $emergencyPhone, $dateOfBirth, $student_id);
    $stmt->execute();
    $stmt->close();

    // ── NEW: Update guardian email + relationship if provided ─────────────────
    if ($guardianEmail !== '') {
        // Check if guardian record exists for this student
        $chk = $conn->prepare("SELECT id FROM student_guardians WHERE student_id = ? AND is_emergency = 1 LIMIT 1");
        $chk->bind_param('i', $student_id);
        $chk->execute();
        $existing = $chk->get_result()->fetch_assoc();
        $chk->close();

        if ($existing) {
            // Update existing guardian record
            $upd = $conn->prepare("
                UPDATE student_guardians
                SET email = ?, relationship = ?
                WHERE student_id = ? AND is_emergency = 1
            ");
            $upd->bind_param('ssi', $guardianEmail, $guardianRelationship, $student_id);
            $upd->execute();
            $upd->close();
        } else {
            // Create guardian record using emergency_contact name from students table
            $sRow = $conn->prepare("SELECT emergency_contact, emergency_phone FROM students WHERE id = ? LIMIT 1");
            $sRow->bind_param('i', $student_id);
            $sRow->execute();
            $sData = $sRow->get_result()->fetch_assoc();
            $sRow->close();

            $gName  = $sData['emergency_contact'] ?? '';
            $gPhone = $sData['emergency_phone']   ?? '';
            $ins    = $conn->prepare("
                INSERT INTO student_guardians (student_id, guardian_name, contact, email, relationship, is_emergency)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $ins->bind_param('issss', $student_id, $gName, $gPhone, $guardianEmail, $guardianRelationship);
            $ins->execute();
            $ins->close();
        }
    }

    logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'UPDATE_PROFILE', 'student', $student_id ?? 0,
        "Profile updated for student ID " . ($student_id ?? 0));
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
}

// ─────────────────────────────────────────────────────────────
// UPDATE PAYMENT PLAN + METHOD
// Called by login.ts finishTorReview() to persist installment/full choice to DB
// ─────────────────────────────────────────────────────────────
function updatePaymentPlan($conn, $data) {
    $student_id     = (int)($data['student_id']     ?? 0);
    $raw_plan       = strtolower(trim($data['payment_plan']   ?? 'full'));
    $payment_plan   = ($raw_plan === 'installment') ? 'installment' : 'full';
    $raw_method     = trim($data['payment_method']  ?? 'Cash');
    $payment_method = in_array($raw_method, ['GCash','Cash']) ? $raw_method : 'Cash';

    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'student_id required']);
        return;
    }

    // FIX RE-ENROLL-PLAN-01: Save payment_plan AND payment_method to students table.
    // reEnroll() resets both to NULL so Angular shows the plan selector again on next login.
    // Without this UPDATE, students.payment_plan stays NULL forever:
    //   - getStudentContext() keeps returning needsPlanSelection=true (plan selector loops)
    //   - submitGcash() blocks with need_plan:true (payment never goes through)
    //   - verifyPayment() falls back to 'full' even when student chose installment

    // Verify student exists before any writes (prevents FK failures downstream)
    $existsChk = $conn->prepare("SELECT id FROM students WHERE id = ? LIMIT 1");
    $existsChk->bind_param('i', $student_id);
    $existsChk->execute();
    if ($existsChk->get_result()->num_rows === 0) {
        $existsChk->close();
        echo json_encode(['success' => false, 'message' => 'Student not found (id=' . $student_id . ')']);
        return;
    }
    $existsChk->close();

    $stUpd = $conn->prepare("UPDATE students SET payment_plan = ?, payment_method = ? WHERE id = ?");
    $stUpd->bind_param('ssi', $payment_plan, $payment_method, $student_id);
    $stUpd->execute();
    $stUpd->close();

    // Also add installment_fee to tuition_fees if switching to installment.
    // Read rate from fee_config — never hardcode monetary values.
    $fc = loadFeeConfig($conn, 'College');
    $installFee = (float)($fc['installment_fee']['value'] ?? 750);

    if ($payment_plan === 'installment') {
        $stmt2 = $conn->prepare(
            "UPDATE tuition_fees
             SET installment_fee = ?,
                 total_assessment = GREATEST(0, subtotal - discount + ?),
                 updated_at = NOW()
             WHERE student_id = ? AND installment_fee = 0"
        );
        $stmt2->bind_param('ddi', $installFee, $installFee, $student_id);
        $stmt2->execute();
        $stmt2->close();
    } else {
        $stmt2 = $conn->prepare(
            "UPDATE tuition_fees
             SET installment_fee = 0.00,
                 total_assessment = GREATEST(0, subtotal - discount),
                 updated_at = NOW()
             WHERE student_id = ? AND installment_fee > 0"
        );
        $stmt2->bind_param('i', $student_id);
        $stmt2->execute();
        $stmt2->close();
    }

    // Upsert payment_schedules so the row exists and has the correct type.
    // INSERT ... ON DUPLICATE KEY handles both first-time and update cases.
    // FIX FK-PS-01: Column order is (student_id, payment_type, total_assessment) so bind
    // must be 'isi' — student_id(int) first, payment_plan(string) second, student_id(int) third.
    // The previous 'sii' swapped types causing the FK violation because the int student_id
    // was being sent as a string in the first placeholder (mapped to student_id column).
    $psUpd = $conn->prepare(
        "INSERT INTO payment_schedules (student_id, payment_type, total_assessment)
         VALUES (?, ?,
             COALESCE((SELECT total_assessment FROM tuition_fees WHERE student_id = ? LIMIT 1), 0))
         ON DUPLICATE KEY UPDATE payment_type = VALUES(payment_type)"
    );
    $psUpd->bind_param('isi', $student_id, $payment_plan, $student_id);
    $psUpd->execute();
    $psUpd->close();

    logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'UPDATE_PAYMENT_PLAN', 'student', $student_id,
        "Payment plan updated to '$payment_plan' ($payment_method) for student ID $student_id");
    echo json_encode(['success' => true, 'paymentPlan' => $payment_plan, 'paymentMethod' => $payment_method]);
}

// ═════════════════════════════════════════════════════════════════
// GET STUDENT CONTEXT — single endpoint that returns everything
// the enrollment page needs: profile + correct fee + tor_eval status
// Called once on page load — no more split between feePreview and feeBreakdown
// ═════════════════════════════════════════════════════════════════

// ═════════════════════════════════════════════════════════════════
//  SOA COMPUTE — NEW / REGULAR STUDENTS
//  (student_type: New, Old, Continuing, Returning)
//
//  Units come from: tuition_fees table → program_courses sum → courses.program sum → fallback 18
//  No TOR logic is applied.
//  Returns an associative array of all fee fields.
// ═════════════════════════════════════════════════════════════════
function computeFeesNew($conn, $student_id, $programName, $semester, $yearLevel, $paymentPlan, $discount) {
    $pn_esc = $programName;
    $yl_esc = $yearLevel;

    // Extract semester term (e.g. '1st Semester') — strip AY suffix so we match
    // courses stored under any school year.
    $semTerm   = '';
    $semFilter = ''; $sfNoJoin = '';
    if ($semester !== '') {
        preg_match('/^(1st Semester|2nd Semester|Summer)/i', $semester, $sm);
        $semTerm   = $sm[1] ?? $semester;
        $semFilter = "AND c.semester LIKE '$semTerm%'";
        $sfNoJoin  = "AND semester LIKE '$semTerm%'";
    }

    // Year level filter — only count units for the student's current year.
    $ylFilter   = ($yl_esc !== '') ? "AND c.year_level = '$yl_esc'" : '';
    $ylFilterNJ = ($yl_esc !== '') ? "AND year_level = '$yl_esc'" : '';

    // Always recount units live from courses filtered by semester + year_level.
    // Never rely on cached tuition_fees.units — it may have been saved before
    // the year_level filter was applied (e.g. stale 15-unit value from old code).
    $units = 0;

    // Source 1: Actual enrolled units (most accurate — avoids format mismatches)
    $enrolledUnitsRes = $conn->query("SELECT COALESCE(SUM(c.credits), 0) AS u
        FROM enrollments e JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = $student_id AND e.status IN ('Enrolled','Pending')");
    $units = (int)(($enrolledUnitsRes ? $enrolledUnitsRes->fetch_assoc()['u'] : 0) ?: 0);

    // Source 2: program_courses junction (if no enrollments yet)
    if ($units <= 0) {
        $pu = $conn->query("SELECT COALESCE(SUM(c.credits),0) AS u
            FROM program_courses pc JOIN programs p ON pc.program_id=p.id JOIN courses c ON pc.course_id=c.id
            WHERE (p.name='$pn_esc' OR p.code='$pn_esc') $ylFilter $semFilter");
        $units = (int)(($pu ? $pu->fetch_assoc()['u'] : 0) ?: 0);
    }

    // Source 3: program_courses without year/sem filters (format mismatch fallback)
    if ($units <= 0 && $pn_esc !== '') {
        $pu2 = $conn->query("SELECT COALESCE(SUM(c.credits),0) AS u
            FROM program_courses pc JOIN programs p ON pc.program_id=p.id JOIN courses c ON pc.course_id=c.id
            WHERE (p.name='$pn_esc' OR p.code='$pn_esc')");
        $units = (int)(($pu2 ? $pu2->fetch_assoc()['u'] : 0) ?: 0);
    }

    // Source 4: courses.program direct column
    if ($units <= 0) {
        $fb = $conn->query("SELECT COALESCE(SUM(credits),0) AS u
            FROM courses WHERE program='$pn_esc' $ylFilterNJ $sfNoJoin");
        $units = (int)(($fb ? $fb->fetch_assoc()['u'] : 0) ?: 0);
    }

    if ($units <= 0) $units = 18; // absolute fallback

    return _buildFees($conn, $student_id, $programName, $semester, $yearLevel, $units, $paymentPlan, $discount);
}


// ═════════════════════════════════════════════════════════════════
//  SOA COMPUTE — TRANSFEREE STUDENTS
//
//  Units come from: TOR approved_units (after evaluation) →
//                   tuition_fees table → program sum → fallback 18
//  Credited subjects are EXCLUDED from unit count (they don't pay for those).
//  Before TOR evaluation the full program unit count is used as an estimate
//  so the student can see a preliminary SOA during the payment step.
// ═════════════════════════════════════════════════════════════════
function computeFeesTransferee($conn, $student_id, $programName, $semester, $yearLevel, $paymentPlan, $discount) {
    // Ensure year_level column exists (safe no-op if already present)
    $pn_esc = $programName;
    $yl_esc = $yearLevel;

    // Extract semester term — strip AY suffix
    $semTerm   = '';
    $semFilter = ''; $sfNoJoin = '';
    if ($semester !== '') {
        preg_match('/^(1st Semester|2nd Semester|Summer)/i', $semester, $sm);
        $semTerm   = $sm[1] ?? $semester;
        $semFilter = "AND c.semester LIKE '$semTerm%'";
        $sfNoJoin  = "AND semester LIKE '$semTerm%'";
    }

    $ylFilter   = ($yl_esc !== '') ? "AND c.year_level = '$yl_esc'" : '';
    $ylFilterNJ = ($yl_esc !== '') ? "AND year_level = '$yl_esc'" : '';

    // ── Priority 1: Count units from ACTUAL active (non-credited) enrollments ──
    // This is the most accurate: only what the student is actually enrolled in,
    // after TOR evaluation credited courses have been Dropped.
    // Avoids the stale tor_evaluations.approved_units bug where approved_units
    // was saved when program_courses was incomplete (e.g. only 12 units linked).
    $units = 0;
    $actualRes = $conn->query("
        SELECT COALESCE(SUM(c.credits), 0) AS u
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = $student_id
          AND e.status IN ('Enrolled', 'Pending')
    ");
    if ($actualRes) {
        $units = (int)($actualRes->fetch_assoc()['u'] ?? 0);
    }

    // ── Priority 2: TOR approved_units — only if no active enrollments yet ──
    // (Pre-enrollment estimate: student paid but auto-enroll hasn't run yet)
    if ($units <= 0) {
        $tor_r = $conn->query("SELECT approved_units, credited_units FROM tor_evaluations
                               WHERE student_id = $student_id AND status = 'Evaluated'
                               ORDER BY id DESC LIMIT 1");
        $tor   = $tor_r ? $tor_r->fetch_assoc() : null;
        if ($tor && (int)$tor['approved_units'] > 0) {
            $units = (int)$tor['approved_units'];
        }
    }

    // ── Priority 3: Live count from program curriculum minus credited courses ──
    // Used when TOR evaluated but approved_units is 0/stale.
    if ($units <= 0) {
        // Count total semester+year units from program
        $totalRes = $conn->query("SELECT COALESCE(SUM(c.credits),0) AS u
            FROM program_courses pc
            JOIN programs p ON pc.program_id=p.id
            JOIN courses c  ON pc.course_id=c.id
            WHERE (p.name='$pn_esc' OR p.code='$pn_esc') $ylFilter $semFilter");
        $totalUnits = (int)(($totalRes ? $totalRes->fetch_assoc()['u'] : 0) ?: 0);

        if ($totalUnits <= 0) {
            $fb = $conn->query("SELECT COALESCE(SUM(credits),0) AS u
                FROM courses WHERE program='$pn_esc' $ylFilterNJ $sfNoJoin");
            $totalUnits = (int)(($fb ? $fb->fetch_assoc()['u'] : 0) ?: 0);
        }

        // Subtract credited units from TOR
        $creditedUnits = 0;
        $torCr = $conn->query("SELECT credited_units FROM tor_evaluations
                               WHERE student_id = $student_id ORDER BY id DESC LIMIT 1");
        if ($torCr) {
            $creditedUnits = (int)($torCr->fetch_assoc()['credited_units'] ?? 0);
        }
        $units = max(0, $totalUnits - $creditedUnits);
    }

    if ($units <= 0) $units = 18; // absolute fallback

    return _buildFees($conn, $student_id, $programName, $semester, $yearLevel, $units, $paymentPlan, $discount);
}


// ─────────────────────────────────────────────────────────────
// SHARED FEE BUILDER — used by both compute functions above.
// Computes all fee line items, saves to tuition_fees, returns array.
// ─────────────────────────────────────────────────────────────
function _buildFees($conn, $student_id, $programName, $semester, $yearLevel, $units, $paymentPlan, $discount) {
    $pn_esc = $programName;
    $yl_esc = $yearLevel;

    // ── If student has approved add/drop requests, use ACTUAL enrolled units ──
    // This overrides the program-curriculum unit count so dropped subjects
    // reduce the SOA and added subjects increase it.
    $adRes = $conn->query("
        SELECT COUNT(*) AS cnt FROM add_drop_requests
        WHERE student_id = $student_id AND status = 'Approved'
        LIMIT 1
    ");
    $hasAddDrop = $adRes && (int)$adRes->fetch_assoc()['cnt'] > 0;

    if ($hasAddDrop) {
        $actualUnitsRes = $conn->query("
            SELECT COALESCE(SUM(c.credits), 0) AS total_units
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            WHERE e.student_id = $student_id AND e.status IN ('Enrolled','Pending')
        ");
        $actualUnits = (int)(($actualUnitsRes ? $actualUnitsRes->fetch_assoc()['total_units'] : 0) ?: 0);
        if ($actualUnits > 0) {
            $units = $actualUnits;
        }
    }

    // Lab fee: based on total number of Laboratory rooms in the rooms table
    $conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS is_lab TINYINT(1) DEFAULT 0");
    $labRoomRes = $conn->query("SELECT COUNT(*) AS cnt FROM rooms WHERE room_type = 'Laboratory'");
    $lab_cnt    = (int)(($labRoomRes ? $labRoomRes->fetch_assoc()['cnt'] : 0) ?? 0);

    // Load fee rates from fee_config table (managed by Accounting)
    $fc = loadFeeConfig($conn, 'College');
    $r_tuition  = (float)($fc['tuition_rate_per_unit']['value'] ?? 650);
    $r_misc     = (float)($fc['misc_fee']['value']              ?? 6688);
    $r_reg      = (float)($fc['reg_fee']['value']               ?? 700);
    $r_lab_room = (float)($fc['lab_fee_per_room']['value']      ?? 1900);
    $r_energy   = (float)($fc['energy_rate_per_unit']['value']  ?? 63);
    $r_install  = (float)($fc['installment_fee']['value']       ?? 750);
    $std_keys   = ['tuition_rate_per_unit','misc_fee','reg_fee','lab_fee_per_room','energy_rate_per_unit','installment_fee'];
    $extra_fees      = 0.00;
    $extra_fees_list = [];
    foreach ($fc as $fk => $frow) {
        if (!in_array($fk, $std_keys)) {
            $amt           = (float)$frow['value'] * ($frow['is_per_unit'] ? $units : 1);
            $extra_fees   += $amt;
            $extra_fees_list[] = [
                'fee_label'   => $frow['fee_label'],
                'rate'        => (float)$frow['value'],
                'is_per_unit' => (bool)$frow['is_per_unit'],
                'amount'      => $amt,
            ];
        }
    }

    $tuition_fee = $units * $r_tuition;
    $misc_fee    = $r_misc;
    $reg_fee     = $r_reg;
    $lab_fee     = $lab_cnt * $r_lab_room;
    $energy_fee  = $units * $r_energy;
    $subtotal    = $tuition_fee + $misc_fee + $reg_fee + $lab_fee + $energy_fee + $extra_fees;
    $inst_fee    = ($paymentPlan === 'installment') ? $r_install : 0.00;
    $total       = max(0, $subtotal - $discount + $inst_fee);

    // FIX-TUITION-SEMESTER-01: Always stamp semester on every write so each term
    // gets its own row and getStudentPaymentHistory can find the correct fees for
    // a past semester. tuition_fees has no UNIQUE KEY on student_id alone so
    // ON DUPLICATE KEY never fired — every call created a NULL-semester duplicate.
    $safeSemTF = $conn->real_escape_string($semester);
    $_tfChk = $conn->query("SELECT id FROM tuition_fees WHERE student_id=$student_id AND semester='$safeSemTF' ORDER BY id DESC LIMIT 1");
    $_tfRow = $_tfChk ? $_tfChk->fetch_assoc() : null;
    if ($_tfRow) {
        $_tfId = (int)$_tfRow['id'];
        $conn->query("UPDATE tuition_fees SET units=$units, tuition_fee=$tuition_fee, miscellaneous_fee=$misc_fee, registration_fee=$reg_fee, laboratory_fee=$lab_fee, energy_fee=$energy_fee, subtotal=$subtotal, discount=$discount, installment_fee=$inst_fee, total_assessment=$total, updated_at=NOW() WHERE id=$_tfId");
    } else {
        $conn->query("INSERT INTO tuition_fees (student_id, units, tuition_fee, miscellaneous_fee, registration_fee, laboratory_fee, energy_fee, subtotal, discount, installment_fee, total_assessment, semester) VALUES ($student_id, $units, $tuition_fee, $misc_fee, $reg_fee, $lab_fee, $energy_fee, $subtotal, $discount, $inst_fee, $total, '$safeSemTF')");
    }

    return [
        'units'            => $units,
        'tuitionFee'       => $tuition_fee,
        'miscellaneousFee' => $misc_fee,
        'registrationFee'  => $reg_fee,
        'laboratoryFee'    => $lab_fee,
        'energyFee'        => $energy_fee,
        'extraFees'        => $extra_fees_list,
        'subtotal'         => $subtotal,
        'discount'         => $discount,
        'installmentFee'   => $inst_fee,
        'totalAssessment'  => $total,
    ];
}

// ═════════════════════════════════════════════════════════════════
//  GET STUDENT CONTEXT
//  Single endpoint: profile + fees + TOR + payment receipts.
//  Fee computation is routed to computeFeesNew() or
//  computeFeesTransferee() based on student_type.
// ═════════════════════════════════════════════════════════════════
function getStudentContext($conn) {
    // Central guard: ensure year_level column exists before ANY sub-function runs.
    // This is the top-level function called by Angular on every page load — guaranteeing
    // it runs here means all child calls (autoEnrollNew, autoEnrollAll, collectProgramCourses, etc.)
    // will already have the column available.
    $student_id = getStudentIdFromRequest($conn);
    if (!$student_id) {
        while (ob_get_level() > 0) { ob_end_clean(); } $_cO = $_SERVER['HTTP_ORIGIN'] ?? '*'; header("Access-Control-Allow-Origin: $_cO", true); header('Access-Control-Allow-Credentials: true', true); header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS', true); header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token', true); header('Access-Control-Expose-Headers: X-New-Token', true); header('Content-Type: application/json', true);
        echo json_encode(['success' => false, 'message' => 'Student not found']); return;
    }

    // ── 1. Load student row ────────────────────────────────────
    $s_res = $conn->prepare("SELECT s.id, s.user_id, s.student_number, s.first_name, s.last_name, s.middle_name, s.suffix, s.date_of_birth, s.age, s.sex, s.address, s.phone, s.program, s.year_level, s.semester, s.student_category, s.student_type, s.enrollment_status, s.approval_status, s.payment_status, s.payment_method, s.payment_plan, s.enrollment_date, s.strand, s.learning_delivery, s.last_school_attended, s.gpa, s.profile_picture, s.is_scholar, s.scholar_type, s.scholar_grantor, s.scholarship_amount, s.has_special_needs, s.special_needs_details, s.has_assistive_tech, s.assistive_tech_details, u.email AS user_email FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ? LIMIT 1");
    $s_res->bind_param("i", $student_id);
    $s_res->execute();
    $s = $s_res->get_result()->fetch_assoc();
    if (!$s) { echo json_encode(['success' => false, 'message' => 'Student not found']); return; }

    $programName    = trim($s['program']          ?? '');
    $studentType    = trim($s['student_type']      ?? 'New');
    $yearLevel      = trim($s['year_level']        ?? '1st Year');

    // FIX RE-ENROLL-04: payment_plan can be NULL after reEnroll() resets it.
    // When NULL, expose needsPlanSelection=true to Angular so it shows the
    // plan selector before the payment form. Fall back to 'full' for fee
    // computation only so the SOA still renders with a valid number.
    $rawPlan = $s['payment_plan'] ?? null;
    $needsPlanSelection = ($rawPlan === null || $rawPlan === '');
    $paymentPlan = $needsPlanSelection ? 'full' : trim($rawPlan);
    if ($paymentPlan === '') $paymentPlan = 'full';

    // Secondary: confirm/correct from payment_schedules if it exists
    if ($paymentPlan !== 'installment') {
        $psRow = $conn->query("SELECT payment_type FROM payment_schedules WHERE student_id = {$s['id']} ORDER BY id DESC LIMIT 1");
        if ($psRow && $psR = $psRow->fetch_assoc() && ($psR['payment_type'] ?? '') === 'installment') {
            $paymentPlan = 'installment';
        }
    }

    // Tertiary self-heal: AR installment_payments records prove this is installment
    if ($paymentPlan !== 'installment') {
        $arChk = $conn->query("SELECT id FROM installment_payments WHERE student_id = $student_id AND or_ar_type = 'AR' LIMIT 1");
        if ($arChk && $arChk->num_rows > 0) {
            $paymentPlan = 'installment';
        }
    }

    // Sync payment_schedules.payment_type if it disagrees with the resolved plan
    if ($paymentPlan === 'installment') {
        $conn->query("UPDATE payment_schedules SET payment_type = 'installment' WHERE student_id = $student_id AND payment_type != 'installment'");
    }

    $paymentMethod  = trim($s['payment_method'] ?? '') ?: '';
    // FIX RE-ENROLL-METHOD-02: Self-heal NULL payment_method for students who
    // re-enrolled before this patch (reEnroll() used to set payment_method=NULL).
    // Recover the method from their most recent payment_log entry, then persist
    // it back to students so all subsequent reads are correct.
    if ($paymentMethod === '') {
        $pmHealSt = $conn->prepare(
            "SELECT payment_method FROM payment_logs
             WHERE student_id = ? AND payment_method IS NOT NULL AND payment_method != ''
             ORDER BY created_at DESC LIMIT 1"
        );
        $pmHealSt->bind_param('i', $student_id);
        $pmHealSt->execute();
        $pmHealRow = $pmHealSt->get_result()->fetch_assoc();
        $pmHealSt->close();
        $paymentMethod = $pmHealRow
            ? (strtolower($pmHealRow['payment_method']) === 'cash' ? 'Cash' : 'GCash')
            : 'GCash'; // absolute fallback — no history at all
        if ($paymentMethod !== '') {
            $pmFixSt = $conn->prepare("UPDATE students SET payment_method = ? WHERE id = ? AND (payment_method IS NULL OR payment_method = '')");
            $pmFixSt->bind_param('si', $paymentMethod, $student_id);
            $pmFixSt->execute();
            $pmFixSt->close();
        }
    }
    $approvalStatus = $s['approval_status']  ?? 'Pending';
    $paymentStatus  = $s['payment_status']   ?? 'Pending';
    $enrollStatus   = $s['enrollment_status'] ?? 'Pending';

    // ── 2. TOR evaluation (transferees only, null for regular) ─
    $torStatus = null; $torCreditedUnits = 0; $torApprovedUnits = 0;
    $torCreditedSubjects = []; $torNotes = ''; $torEvalAt = '';

    if ($studentType === 'Transferee') {
        $tor_res = $conn->query("SELECT * FROM tor_evaluations WHERE student_id = $student_id ORDER BY id DESC LIMIT 1");
        if ($tor_res && $tor_res->num_rows > 0) {
            $tor = $tor_res->fetch_assoc();
            $torStatus           = $tor['status'];
            $torCreditedUnits    = (int)($tor['credited_units']  ?? 0);
            $torApprovedUnits    = (int)($tor['approved_units']  ?? 0);
            $torNotes            = $tor['registrar_notes']        ?? '';
            $torEvalAt           = $tor['evaluated_at']           ?? '';
            $torCreditedSubjects = json_decode($tor['credited_subjects'] ?? '[]', true) ?: [];
        }
    }

    // ── 3. Resolve semester ────────────────────────────────────
    $semester = trim($s['semester'] ?? '');
    if ($semester === '') {
        $semQ = $conn->prepare("SELECT c.semester FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE e.student_id = ? AND e.status IN ('Pending','Enrolled') ORDER BY e.created_at DESC LIMIT 1");
        $semQ->bind_param("i", $student_id);
        $semQ->execute();
        $semRow   = $semQ->get_result()->fetch_assoc();
        $semester = $semRow['semester'] ?? '';
    }

    // ── 4. Compute fees — route by student type ────────────────
    // Scholar discount from student_scholarships table
    $schCtx = $conn->query("SELECT COALESCE(SUM(scholarship_amount),0) AS total FROM student_scholarships WHERE student_id = {$s['id']} AND is_active = 1");
    $discount = (float)($schCtx ? $schCtx->fetch_assoc()['total'] : 0);

    $studentCategory = strtoupper(trim($s['student_category'] ?? ''));
    $isSHSTVET = ($studentCategory === 'SHS' || $studentCategory === 'TVET');

    if ($studentType === 'Transferee') {
        $fees = computeFeesTransferee($conn, $student_id, $programName, $semester, $yearLevel, $paymentPlan, $discount);
    } elseif ($isSHSTVET) {
        // SHS/TVET New & Old — FREE (K-12 Voucher / TESDA PRISAA)
        // Return zero fees and clear any stale tuition_fees record
        $conn->query("DELETE FROM tuition_fees WHERE student_id = $student_id");
        $fees = [
            'units' => 0, 'tuitionFee' => 0, 'miscellaneousFee' => 0,
            'registrationFee' => 0, 'laboratoryFee' => 0, 'energyFee' => 0,
            'subtotal' => 0, 'discount' => 0, 'installmentFee' => 0,
            'totalAssessment' => 0,
        ];
        // FIX: SHS/TVET non-transferee students are FREE — auto-approve immediately
        // so the frontend can route them to the dashboard and trigger auto-enrollment.
        // Without this they stay Pending forever because verifyPayment() never runs.
        $isTransfereeCheck = (trim($studentType) === 'Transferee');
        if (!$isTransfereeCheck && $approvalStatus !== 'Approved') {
            $conn->query("UPDATE students SET approval_status='Approved', payment_status='Paid', enrollment_status='Enrolled' WHERE id=$student_id AND approval_status != 'Approved'");
            $approvalStatus = 'Approved';
            $paymentStatus  = 'Paid';
            $enrollStatus   = 'Enrolled';
        }
    } else {
        $fees = computeFeesNew($conn, $student_id, $programName, $semester, $yearLevel, $paymentPlan, $discount);

        // Auto-approve installment students who are fully paid but still Pending/stuck
        // This fixes students who paid cash in full but approval_status was never updated.
        if ($approvalStatus !== 'Approved' && $paymentPlan === 'installment') {
            $paidChk = $conn->query("SELECT COALESCE(SUM(amount),0) AS tp FROM installment_payments ip JOIN students _st ON _st.id=ip.student_id WHERE ip.student_id=$student_id AND ip.semester=_st.semester");
            $totalPaidChk = $paidChk ? (float)$paidChk->fetch_assoc()['tp'] : 0;
            if ($totalPaidChk >= (float)$fees['totalAssessment'] && $fees['totalAssessment'] > 0) {
                // Fully paid via installment — set Confirmed, wait for Registrar approval
                $conn->query("UPDATE students SET approval_status='Approved', payment_status='Paid', enrollment_status='Confirmed' WHERE id=$student_id");
                $approvalStatus = 'Approved';
                $paymentStatus  = 'Paid';
                $enrollStatus   = 'Confirmed';
            }
        }
    }

    $total = $fees['totalAssessment'];

    // ── 5. Payment receipts & term breakdown ──────────────────
    $total_paid = 0; $payments = []; $termBreakdown = [];
    $termOrder  = ['Downpayment','Prelim','Midterm','Finals','Full'];

    // Compute total_paid scoped to current semester only (for balance calculation)
    $_curSemR   = $conn->query("SELECT semester FROM students WHERE id=$student_id LIMIT 1");
    $_curSemEsc = $conn->real_escape_string($_curSemR ? ($_curSemR->fetch_assoc()['semester'] ?? '') : '');
    $_tpScopedR = $conn->query("SELECT COALESCE(SUM(amount),0) AS tp FROM installment_payments WHERE student_id=$student_id AND semester='$_curSemEsc'");
    $total_paid = $_tpScopedR ? (float)$_tpScopedR->fetch_assoc()['tp'] : 0;

    // Display ALL semesters in receipts/SOA — history must remain visible
    $ip_res = $conn->query("SELECT ip.*, COALESCE(sp.first_name, f2.first_name, st2.first_name) AS rec_by FROM installment_payments ip LEFT JOIN users u ON ip.recorded_by = u.id LEFT JOIN staff_profiles sp ON sp.user_id = u.id LEFT JOIN faculty f2 ON f2.user_id = u.id LEFT JOIN students st2 ON st2.user_id = u.id WHERE ip.student_id = $student_id ORDER BY ip.created_at ASC");
    if ($ip_res) {
        while ($r = $ip_res->fetch_assoc()) {
            $amt         = (float)$r['amount'];
            // Only add to total_paid if current semester (already computed above via scoped SUM)
            $payments[]  = [
                'orArNumber'  => $r['or_ar_number'],
                'orArType'    => $r['or_ar_type'],
                'type'        => $r['or_ar_type'],
                'period'      => $r['exam_period'],
                'paymentDate' => $r['payment_date'],
                'method'      => $r['payment_method'] ?? '',
                'amount'      => $amt,
                'recordedBy'  => $r['rec_by'] ?? '',
                'semester'    => $r['semester'] ?? '',
            ];
            // Only include in termBreakdown for current semester (used for balance display)
            // BUG-TERM-BREAKDOWN-01 FIX: SUM all payments for the same exam_period instead of
            // only keeping the first row. A student can have multiple installment_payments rows
            // for the same period (e.g. partial then top-up). We always want the total paid,
            // the latest OR number, and the latest payment date for that period.
            $key = $r['exam_period'];
            if (($r['semester'] ?? '') === $_curSemEsc) {
                if (!isset($termBreakdown[$key])) {
                    $termBreakdown[$key] = ['period' => $key, 'amountPaid' => $amt, 'orArNumber' => $r['or_ar_number'], 'orArType' => $r['or_ar_type'], 'paymentDate' => $r['payment_date'], 'paymentMethod' => $r['payment_method'] ?? ''];
                } else {
                    // Accumulate — add amount, keep latest OR number and payment date
                    $termBreakdown[$key]['amountPaid'] += $amt;
                    $termBreakdown[$key]['orArNumber']  = $r['or_ar_number'];   // last OR wins
                    $termBreakdown[$key]['paymentDate'] = $r['payment_date'];   // latest date
                }
            }
        }
    }

    // Also pull GCash-verified payments not yet in installment_payments
    // Display ALL semester GCash receipts in SOA — filter by semester only for balance
    $pl_res = $conn->query("SELECT pl.*, COALESCE(sp.first_name, f2.first_name, st2.first_name) AS vby FROM payment_logs pl LEFT JOIN users u ON pl.verified_by=u.id LEFT JOIN staff_profiles sp ON sp.user_id = u.id LEFT JOIN faculty f2 ON f2.user_id = u.id LEFT JOIN students st2 ON st2.user_id = u.id WHERE pl.student_id=$student_id AND pl.status='Verified' ORDER BY pl.verified_at ASC");
    if ($pl_res) {
        while ($r = $pl_res->fetch_assoc()) {
            $amt    = (float)$r['gcash_amount'] ?? '';
            if ($amt <= 0) $amt = $total;
            $plChkS = $conn->prepare("SELECT id FROM installment_payments WHERE payment_log_id = ? LIMIT 1");
                $plChkS->bind_param("i", $r['id']);
                $plChkS->execute();
                $plChk  = $plChkS->get_result();
            if ($plChk && $plChk->num_rows === 0) {
                $period = ($paymentPlan === 'installment') ? 'Downpayment' : 'Full';
                $isCurrentSem = (trim($r['semester'] ?? '') === $_curSemEsc);
                // Always show in receipts/SOA regardless of semester
                $payments[]   = [
                    'orArNumber'  => $r['or_ar_number'] ?? ('OR-' . $r['id']),
                    'orArType'    => ($paymentPlan === 'installment') ? 'AR' : 'OR',
                    'type'        => ($paymentPlan === 'installment') ? 'AR' : 'OR',
                    'period'      => $period,
                    'paymentDate' => $r['gcash_date'] ?? date('Y-m-d', strtotime($r['verified_at'] ?? '')),
                    'method'      => $r['payment_method'] ?? 'GCash',
                    'amount'      => $amt,
                    'recordedBy'  => $r['vby'] ?? '',
                    'semester'    => $r['semester'] ?? '',
                ];
                // Only apply to termBreakdown and total_paid for current semester
                // BUG-TERM-BREAKDOWN-01 FIX (GCash path): accumulate like installment_payments above.
                if ($isCurrentSem) {
                    $orNo   = $r['or_ar_number'] ?? ('OR-' . $r['id']);
                    $pDate  = $r['gcash_date'] ?? date('Y-m-d', strtotime($r['verified_at'] ?? ''));
                    $orType = ($paymentPlan === 'installment') ? 'AR' : 'OR';
                    if (!isset($termBreakdown[$period])) {
                        $termBreakdown[$period] = ['period' => $period, 'amountPaid' => $amt, 'orArNumber' => $orNo, 'orArType' => $orType, 'paymentDate' => $pDate, 'paymentMethod' => $r['payment_method'] ?? 'GCash'];
                    } else {
                        $termBreakdown[$period]['amountPaid'] += $amt;
                        $termBreakdown[$period]['orArNumber']  = $orNo;
                        $termBreakdown[$period]['paymentDate'] = $pDate;
                    }
                    // top-up total_paid for GCash-only payments not yet in installment_payments
                    $total_paid += $amt;
                }
            }
        }
    }

    $balance        = max(0, $total - $total_paid);
    $is_fully_paid  = ($balance <= 0 && $total_paid > 0);
    $sortedTerms    = [];
    foreach ($termOrder as $t) { if (isset($termBreakdown[$t])) $sortedTerms[] = $termBreakdown[$t]; }

    // ── 5b. SERVER-SIDE AUTO-ENROLL GUARD ────────────────────
    // If student is Approved but has zero active enrollments, trigger auto-enrollment
    // here so subjects are always present on context load — no client round-trip needed.
    // This fixes the race condition where the Angular UI loads before the auto_enroll POST
    // completes, leaving the subjects list permanently empty.
    if ($approvalStatus === 'Approved' && $studentType !== 'Transferee' && !$isSHSTVET) {
        $enrollCount = (int)$conn->query(
            "SELECT COUNT(*) AS c FROM enrollments WHERE student_id = $student_id AND status IN ('Enrolled','Pending')"
        )->fetch_assoc()['c'];
        if ($enrollCount === 0) {
            // Re-use autoEnrollNew logic inline (non-responding — just run it)
            autoEnrollNew($conn, ['student_id' => $student_id, 'semester' => $semester], false);
        }
    }

    // ── 6. Build response ─────────────────────────────────────
    $pic = $s['profile_picture'] ?: 'https://ui-avatars.com/api/?name=' . urlencode(($s['first_name']??'').'+'.($s['last_name']??'')) . '&size=150';
    // Guardian from student_guardians table
    $guardianName = ''; $guardianContact = ''; $guardianEmail = ''; $guardianRelationship = '';
    $gCtx = $conn->query("SELECT guardian_name, contact, email, relationship FROM student_guardians WHERE student_id = {$s['id']} AND is_emergency = 1 LIMIT 1");
    if ($gCtx && $gc = $gCtx->fetch_assoc()) {
        $guardianName         = $gc['guardian_name'] ?? '';
        $guardianContact      = $gc['contact']       ?? '';
        $guardianEmail        = $gc['email']         ?? '';
        $guardianRelationship = $gc['relationship']  ?? '';
    }
    if (!$guardianName)    $guardianName    = $s['emergency_contact'] ?? '';
    if (!$guardianContact) $guardianContact = $s['emergency_phone']   ?? '';

    // ── RE-ENROLLMENT DETECTION ──────────────────────────────────────────────
    $needsReEnroll  = false;
    $nextSemester   = '';
    $nextYearLevel  = '';
    $periodLabel    = '';

    // BUG-REENROLL-NEW-FIX: removed $studentType !== 'New' gate — New students
    // must be allowed to re-enroll so they can become Old. The gate caused New
    // students to be permanently stuck on their first semester with no way to advance.
    //
    // FIX REENROLL-NEWSTUDENT-01: However, a brand-new student who was JUST confirmed
    // by the Registrar (student_type='New', zero Completed enrollments) must NOT be
    // flagged for re-enrollment. The old gate removal exposed this: if the enrollment
    // period label differs even slightly from the student's stored semester, the
    // detection fires and shows "Re-enrollment" to a student on their very first login.
    //
    // Rule: New students only see re-enrollment prompt if they already have at least
    // one Completed enrollment row — meaning they've finished their first semester.
    // Old/Continuing/Transferee students keep the existing behavior.
    $hasCompletedEnrollments = true; // default: allow re-enroll check
    if ($studentType === 'New') {
        $compChkR = $conn->query(
            "SELECT COUNT(*) AS cnt FROM enrollments
             WHERE student_id = $student_id AND status = 'Completed'"
        );
        $hasCompletedEnrollments = $compChkR && (int)$compChkR->fetch_assoc()['cnt'] > 0;
    }

    if ($approvalStatus === 'Approved' && $enrollStatus === 'Enrolled' && $hasCompletedEnrollments) {
        $period = getEnrollmentPeriodRow($conn);
        if ($period['is_open'] ?? false) {
            $periodLabel = trim($period['label'] ?? '');
            // FIX RE-ENROLL-LOOP: Use $semester (resolved at line ~2992, re-fetched from DB
            // after any auto-approve writes) instead of stale $s['semester'] which still holds
            // the OLD semester value from the initial SELECT — causing needsReEnroll=true
            // immediately after re-enrollment because the comparison never matched.
            $studentSem  = trim($semester ?? $s['semester'] ?? '');
            // Apply the same AY normalization to $studentSem so the comparison is apples-to-apples
            $studentSem = preg_replace_callback(
                '/AY\s*(\d{4})-(\d{4})/i',
                function($m) {
                    $y1 = (int)$m[1]; $y2 = (int)$m[2];
                    return 'AY ' . min($y1,$y2) . '-' . max($y1,$y2);
                },
                $studentSem
            );

            // FIX RE-ENROLL-AY: Normalize reversed AY (e.g. "AY 2027-2026" -> "AY 2026-2027")
            // Admin may accidentally type the years in reverse order.
            $periodLabel = preg_replace_callback(
                '/AY\s*(\d{4})-(\d{4})/i',
                function($m) {
                    $y1 = (int)$m[1]; $y2 = (int)$m[2];
                    return 'AY ' . min($y1,$y2) . '-' . max($y1,$y2);
                },
                $periodLabel
            );

            // FIX RE-ENROLL-03: Compare term-only (strip AY suffix) so
            // "2nd Semester, AY 2025-2026" vs "1st Semester, AY 2026-2027"
            // correctly triggers re-enrollment instead of being skipped.
            // Guard: same full label = already enrolled for this period.
            if ($periodLabel !== '' && $periodLabel !== $studentSem) {
                $needsReEnroll = true;

                $ylMap  = ['1st Year'=>1,'2nd Year'=>2,'3rd Year'=>3,'4th Year'=>4,'5th Year'=>5];
                $ylBack = [1=>'1st Year',2=>'2nd Year',3=>'3rd Year',4=>'4th Year',5=>'5th Year'];

                $nextSemester  = $periodLabel;
                $nextYearLevel = $yearLevel;

                // Extract term parts for year-level advancement decision
                $periodTerm  = '';
                $studentTerm = '';
                if (preg_match('/^(1st\s+Semester|2nd\s+Semester|Summer|Midyear)/i', $periodLabel, $_pm)) {
                    $periodTerm = preg_replace('/\s+/', ' ', trim($_pm[1]));
                }
                if (preg_match('/^(1st\s+Semester|2nd\s+Semester|Summer|Midyear)/i', $studentSem, $_sm)) {
                    $studentTerm = preg_replace('/\s+/', ' ', trim($_sm[1]));
                }

                // Advance year level: 2nd Sem → 1st Sem of next AY
                if (strcasecmp($periodTerm, '1st Semester') === 0 &&
                    strcasecmp($studentTerm, '2nd Semester') === 0) {
                    $curYLNum      = $ylMap[$yearLevel] ?? 1;
                    $nextYLNum     = min($curYLNum + 1, 5);
                    $nextYearLevel = $ylBack[$nextYLNum];
                }
            }
        }
    }

    // Flush output buffer — discard any stray notices before sending JSON
    while (ob_get_level() > 0) { ob_end_clean(); } $_cO = $_SERVER['HTTP_ORIGIN'] ?? '*'; header("Access-Control-Allow-Origin: $_cO", true); header('Access-Control-Allow-Credentials: true', true); header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS', true); header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token', true); header('Access-Control-Expose-Headers: X-New-Token', true); header('Content-Type: application/json', true);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'student' => [
            'id'               => $s['student_number'],
            'studentNumber'    => $s['student_number'],
            'dbId'             => (int)$s['id'],
            'firstName'        => $s['first_name']       ?? '',
            'lastName'         => $s['last_name']        ?? '',
            'middleName'       => $s['middle_name']      ?? '',
            'suffix'           => $s['suffix']           ?? '',
            'email'            => $s['user_email'] ?? '',
            'phone'            => $s['phone']            ?? '',
            'profilePicture'   => $pic,
            'program'          => $programName,
            'yearLevel'        => $s['year_level']       ?? '1st Year',
            'studentType'      => $studentType,
            'studentCategory'  => strtoupper(trim($s['student_category'] ?? '')),
            'semester'         => $semester,
            'gpa'              => (float)($s['gpa']      ?? 0),
            'enrollmentStatus' => $enrollStatus,
            'enrollmentDate'   => $s['enrollment_date']  ?? '',
            'paymentStatus'    => $paymentStatus,
            'paymentMethod'    => $paymentMethod,
            'paymentPlan'      => $paymentPlan,
            'approvalStatus'   => $approvalStatus,
            'isScholar'        => $discount > 0,
            'scholarshipAmount'=> $discount,
            'torEvalStatus'    => $torStatus,
            'lrnNo'            => $s['lrn_no']           ?? '',
            'dateOfBirth'      => $s['date_of_birth']    ?? '',
            'sex'              => $s['sex']              ?? '',
            'address'          => $s['address']          ?? '',
            'guardianName'     => $guardianName,
            'guardianContact'  => $guardianContact,
            'emergencyContact'    => $s['emergency_contact'] ?? $guardianName,
            'emergencyPhone'      => $s['emergency_phone']   ?? $guardianContact,
            'guardianEmail'       => $guardianEmail,
            'guardianRelationship'=> $guardianRelationship,
        ],
        'torEvaluation' => ($studentType === 'Transferee' && $torStatus) ? [
            'status'           => $torStatus,
            'creditedUnits'    => $torCreditedUnits,
            'approvedUnits'    => $torApprovedUnits,
            'creditedSubjects' => $torCreditedSubjects,
            'registrarNotes'   => $torNotes,
            'evaluatedAt'      => $torEvalAt,
        ] : null,
        'fees' => array_merge($fees, [
            'totalPaid'     => $total_paid,
            'balance'       => $balance,
            'paymentStatus' => $is_fully_paid ? 'Fully Paid' : ($total_paid > 0 ? 'Partially Paid' : 'Unpaid'),
        ]),
        'paymentPlan'   => $paymentPlan,
        'payments'      => $payments,
        'termBreakdown' => $sortedTerms,
        'totalPaid'     => $total_paid,
        'balance'       => $balance,
        'isFullyPaid'   => $is_fully_paid,
        'needsReEnroll' => $needsReEnroll,
        'nextSemester'  => $nextSemester,
        'nextYearLevel' => $nextYearLevel,
        'needsPlanSelection' => $needsPlanSelection ?? false,
    ]);
}

// ─────────────────────────────────────────────────────────────
// RE-ENROLL — called when enrollment period opens for a new term
//
// What it does:
//   1. Validates student is Approved+Enrolled and enrollment period is open
//   2. Drops all current enrollments (marks as Completed or Dropped)
//   3. Advances year_level if moving from 2nd Sem → 1st Sem of next year
//   4. Updates semester, student_type → 'Old'/'Continuing'
//   5. Resets payment_status, approval_status, enrollment_status → Pending
//   6. Clears tuition_fees so a fresh SOA is computed
//   7. Returns the new year_level + semester so Angular can show the payment screen
// ─────────────────────────────────────────────────────────────
function reEnroll($conn, $data) {
    $student_id = (int)($data['student_id'] ?? 0);
    if (!$student_id) {
        jsonOut(['success' => false, 'message' => 'student_id required']);
    }

    // Load student
    $s = (($_r=$conn->query("SELECT * FROM students WHERE id = $student_id LIMIT 1")) ? $_r->fetch_assoc() : null);
    if (!$s) {
        jsonOut(['success' => false, 'message' => 'Student not found']);
    }

    // Must be Approved + Enrolled to re-enroll
    if ($s['approval_status'] !== 'Approved' || $s['enrollment_status'] !== 'Enrolled') {
        jsonOut(['success' => false, 'message' => 'Student is not in an active enrollment. Current status: ' . $s['enrollment_status']]);
    }

    // Enrollment period must be open
    if (!isEnrollmentOpen($conn)) {
        $p   = getEnrollmentPeriodRow($conn);
        $msg = 'Enrollment period is not open yet.';
        if (!empty($p['start'])) $msg .= ' Opens: ' . date('M d, Y', strtotime($p['start']));
        jsonOut(['success' => false, 'message' => $msg, 'enrollment_closed' => true]);
    }

    $period      = getEnrollmentPeriodRow($conn);
    $newSemLabel = trim($period['label'] ?? '');
    if ($newSemLabel === '') {
        jsonOut(['success' => false, 'message' => 'Enrollment period has no label/semester set. Please ask admin to set it.']);
    }

    // FIX RE-ENROLL-AY: Normalize reversed AY in label (e.g. "AY 2027-2026" -> "AY 2026-2027")
    $newSemLabel = preg_replace_callback(
        '/AY\s*(\d{4})-(\d{4})/i',
        function($m) {
            $y1 = (int)$m[1]; $y2 = (int)$m[2];
            return 'AY ' . min($y1,$y2) . '-' . max($y1,$y2);
        },
        $newSemLabel
    );

    // ── Year-level maps (College + SHS) ───────────────────────────────────
    // College: 1st Year … 4th Year (max = 4)
    // SHS:     Grade 11, Grade 12   (max grade = 12)
    $ylMap  = ['1st Year'=>1,'2nd Year'=>2,'3rd Year'=>3,'4th Year'=>4,'5th Year'=>5,
               'Grade 11'=>11,'Grade 12'=>12];
    $ylBack = [1=>'1st Year',2=>'2nd Year',3=>'3rd Year',4=>'4th Year',5=>'5th Year'];

    $curYL    = trim($s['year_level']       ?? '1st Year');
    $curSem   = trim($s['semester']         ?? '');
    $category = strtoupper(trim($s['student_category'] ?? ''));
    $newYL    = $curYL;

    // FIX RE-ENROLL-02: Extract only the TERM part (strip AY suffix) before
    // comparing — prevents "1st Semester, AY 2025-2026" vs "2nd Semester, AY 2025-2026"
    // mismatch that caused isAdvancing to always be false.
    // Also handle labels without comma (e.g. "1st Semester AY 2026-2027") and
    // labels that start with the term directly (e.g. "2nd Semester").
    $curSemTerm = '';
    if (preg_match('/^(1st\s+Semester|2nd\s+Semester|Summer|Midyear)/i', $curSem, $_m)) {
        $curSemTerm = preg_replace('/\s+/', ' ', trim($_m[1]));
    }
    $newSemTerm = '';
    if (preg_match('/^(1st\s+Semester|2nd\s+Semester|Summer|Midyear)/i', $newSemLabel, $_m)) {
        $newSemTerm = preg_replace('/\s+/', ' ', trim($_m[1]));
    }

    // Advance year level when moving from 2nd Semester → 1st Semester of next AY.
    // Use case-insensitive normalized comparison to avoid whitespace issues.
    $isAdvancing = (strcasecmp($newSemTerm, '1st Semester') === 0)
                && (strcasecmp($curSemTerm, '2nd Semester') === 0);

    // ── Graduation detection ──────────────────────────────────────────────
    // A student graduates when they complete their program's final year/grade
    // and the next period would push them beyond that final year:
    //   • College: completed 4th Year 2nd Semester → would advance to 5th Year
    //   • SHS:     completed Grade 12 2nd Semester → no Grade 13 exists
    $isGraduating = false;
    if ($isAdvancing) {
        $curNum = $ylMap[$curYL] ?? 0;
        if ($category === 'SHS' && $curNum === 12) {
            $isGraduating = true;
        } elseif ($category !== 'SHS' && $curNum >= 4) {
            $isGraduating = true;
        }
    }

    // ── Handle graduation ─────────────────────────────────────────────────
    if ($isGraduating) {
        // Mark failed subjects as 'Failed', not 'Completed'
        $conn->query("UPDATE enrollments SET status = 'Failed'
                      WHERE student_id = $student_id AND status IN ('Enrolled','Pending')
                        AND EXISTS (
                            SELECT 1 FROM student_grades sg
                            WHERE sg.enrollment_id = enrollments.id
                              AND sg.term = 'Final' AND sg.grade > 3.0
                        )");
        // Mark the rest (passed or no grade yet) as 'Completed'
        $conn->query("UPDATE enrollments SET status = 'Completed'
                      WHERE student_id = $student_id AND status IN ('Enrolled','Pending')");

        $gradStmt = $conn->prepare(
            "UPDATE students SET
                enrollment_status = 'Graduated',
                approval_status   = 'Approved'
             WHERE id = ?"
        );
        $gradStmt->bind_param('i', $student_id);
        $gradStmt->execute();
        $gradStmt->close();

        logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'GRADUATED', 'student', $student_id,
            "Student $student_id graduated: completed $curYL $curSemTerm (category: $category)");

        jsonOut([
            'success'     => true,
            'isGraduated' => true,
            'message'     => 'Congratulations! You have successfully completed your program.',
            'yearLevel'   => $curYL,
            'semester'    => $curSem,
            'program'     => $s['program'] ?? '',
        ]);
    }

    // ── Normal re-enrollment: advance year level if moving to 1st Semester ─
    // Check for failed subjects BEFORE deciding whether to advance.
    // A subject is failed when student_grades has a grade > 3.0 for that enrollment.
    $failedCount   = 0;
    $failedCourses = [];
    $failedRes = $conn->query("
        SELECT c.name AS course_name
        FROM   enrollments e
        JOIN   courses c ON c.id = e.course_id
        WHERE  e.student_id = $student_id
          AND  e.status IN ('Enrolled','Pending')
          AND  EXISTS (
               SELECT 1 FROM student_grades sg
               WHERE  sg.enrollment_id = e.id AND sg.grade > 3.0
          )
    ");
    if ($failedRes) {
        while ($r = $failedRes->fetch_assoc()) {
            $failedCourses[] = $r['course_name'];
            $failedCount++;
        }
    }

    $becameIrregular = false;
    if ($failedCount > 0) {
        // Student failed at least one subject → keep same year level, mark irregular
        $newYL           = $curYL;
        $becameIrregular = true;
    } elseif ($isAdvancing) {
        $curNum = $ylMap[$curYL] ?? 1;
        $newYL  = $ylBack[min($curNum + 1, 5)] ?? $curYL;
    }

    // Determine new student_type: New→Old, Transferee→Continuing, rest→Continuing
    $curType = trim($s['student_type'] ?? 'New');
    $newType = match(true) {
        $curType === 'New'        => 'Old',
        $curType === 'Transferee' => 'Continuing',
        default                   => 'Continuing',
    };

    // ── Step 1: Mark all current enrollments as Completed (or Failed if applicable) ──
    // FIX: Subjects with a failing Final grade (> 3.0) must be marked 'Failed',
    // not 'Completed'. Marking them 'Completed' caused the bug where a subject
    // showed remarks="Failed" but enrollment status="Completed".
    $conn->query("UPDATE enrollments SET status = 'Failed'
                  WHERE student_id = $student_id AND status IN ('Enrolled','Pending')
                    AND EXISTS (
                        SELECT 1 FROM student_grades sg
                        WHERE sg.enrollment_id = enrollments.id
                          AND sg.term = 'Final' AND sg.grade > 3.0
                    )");
    // All remaining active enrollments (passed or no final grade yet) → Completed
    $conn->query("UPDATE enrollments SET status = 'Completed'
                  WHERE student_id = $student_id AND status IN ('Enrolled','Pending')");

    // ── Step 2: Clear tuition_fees + payment_schedules + payment_logs so a
    //           fresh SOA and fresh payment selection is computed next load.
    //           DO NOT delete installment_payments — those are historical OR/AR records.
    //
    // FIX SOA-SNAPSHOT-01: Freeze the current semester's SOA BEFORE deleting
    // tuition_fees. This preserves the full fee breakdown + subjects + payment
    // history permanently in soa_snapshots so getSoaSnapshot() can retrieve
    // any past semester without hitting the now-deleted tuition_fees row.
    saveSoaSnapshot($conn, $student_id, $curSem);

    $conn->query("DELETE FROM tuition_fees       WHERE student_id = $student_id");
    $conn->query("DELETE FROM payment_schedules  WHERE student_id = $student_id");
    // Only delete Pending/unverified payment logs — keep Verified records for audit
    $conn->query("DELETE FROM payment_logs       WHERE student_id = $student_id AND status = 'Pending'");
    // FIX RE-ENROLL-PERMITS-01: Delete old exam permits from the previous semester.
    // Without this, old Prelim/Midterm/Finals permits remained in the DB after re-enrollment,
    // causing the student to appear in the wrong semester's Permit Generation groups
    // and polluting the getCourseGroups / getPermitCourseGroups counts.
    // Verified payment history (installment_payments) is preserved — only permits are cleared.
    $conn->query("DELETE FROM exam_permits WHERE student_id = $student_id");

    // ── Step 3: Update student record ──────────────────────────────────────
    // FIX RE-ENROLL-SOA-01: Update the student record FIRST (semester + year_level)
    // so that computeFeesNew() reads the correct values when we pre-seed the SOA below.
    // FIX RE-ENROLL-01: Reset payment_plan to NULL so Angular shows the payment
    // plan selector (full vs installment) again on next login — the semester fee
    // total may differ and the student must re-confirm their choice.
    // NOTE: payment_method is intentionally preserved (NOT reset to NULL).
    //   Nulling it caused GCash to always appear for Cash students (NULL fallback
    //   in getStudentContext/getProfile → 'GCash'), broke Accounting's payment queue
    //   (NULL read as 'gcash' → no Cash log created → student never reaches
    //   Confirmed → Registrar queue empty → subjects never auto-enrolled).
    // is_irregular = 1 when the student failed at least one subject this semester.
    $isIrregularVal = $becameIrregular ? 1 : 0;
    // FIX RE-ENROLL-METHOD-01: Do NOT null out payment_method on re-enrollment.
    // Nulling it caused two cascading bugs:
    //   1. getStudentContext() / getProfile() default NULL → 'GCash', so Cash
    //      students see GCash in the payment UI even when they chose Cash.
    //   2. getPendingPayments() reads NULL payment_method as 'gcash' (fallback),
    //      creating an AwaitingSubmission GCash row instead of a Cash row —
    //      Accounting can't record cash payment, student never reaches Confirmed,
    //      and Registrar never sees the student for confirmation.
    // payment_plan is still reset to NULL so the plan selector (full/installment)
    // appears again — the plan amount may change based on new semester fees.
    $updStmt = $conn->prepare(
        "UPDATE students SET
            year_level        = ?,
            semester          = ?,
            student_type      = ?,
            is_irregular      = ?,
            enrollment_status = 'Pending',
            payment_status    = 'Pending',
            approval_status   = 'Pending',
            payment_plan      = NULL,
            enrollment_date   = CURDATE(),
            registrar_confirmed    = 'Pending',
            registrar_confirmed_at = NULL,
            registrar_confirmed_by = NULL,
            registrar_notes        = NULL
         WHERE id = ?"
    );
    $updStmt->bind_param('sssii', $newYL, $newSemLabel, $newType, $isIrregularVal, $student_id);
    $updStmt->execute();
    $updStmt->close();

    // ── Step 4 (NEW): Pre-seed the SOA for the new semester ────────────────
    // FIX RE-ENROLL-SOA-01: After re-enrollment the student must choose their
    // payment plan (full / installment) — but we can still pre-compute the fee
    // TOTAL now so that:
    //   a) Accounting can already see the new assessment in get_pending_payments
    //   b) The student's payment screen shows the correct amount (not blank)
    //   c) payment_schedules has a row with the new semester from the start
    //
    // payment_plan is NULL here (student hasn't chosen yet), so we compute using
    // 'full' as a neutral baseline. updatePaymentPlan() will recalculate with the
    // surcharge when the student actually selects installment.
    //
    // computeFeesNew() reads students.semester + year_level (just updated above)
    // and uses enrolled credits — but since enrollments are all Completed now,
    // it will fall back to program_courses credits for the new year+sem.
    // That is exactly what we want for the preliminary SOA.
    $schCtxRe = $conn->query("SELECT COALESCE(SUM(scholarship_amount),0) AS total FROM student_scholarships WHERE student_id = $student_id AND is_active = 1");
    $discountRe = (float)($schCtxRe ? $schCtxRe->fetch_assoc()['total'] : 0);
    // NOTE: computeFeesNew is defined in enrollment.php above — always available here.
    computeFeesNew($conn, $student_id, $s['program'] ?? '', $newSemLabel, $newYL, 'full', $discountRe);

    // Seed payment_schedules with 'full' plan + new semester total (placeholder).
    // The ON DUPLICATE KEY ensures we don't clobber an existing row if somehow one exists.
    //
    // FIX RE-ENROLL-PRELIM-LOCK-02: On re-enrollment ALL three term periods must start
    // as 'locked'. Accounting unlocks each by sending a payment notice (send_payment_notice).
    //
    // The previous fix (RE-ENROLL-PRELIM-LOCK-01) seeded prelim_status='unpaid' to avoid
    // locking students out of their first payment — but this had the opposite problem:
    // the student could submit a Prelim payment BEFORE Accounting sent any notice, bypassing
    // the intended workflow entirely (Accounting sends notice → student pays → permit issued).
    //
    // Correct flow:
    //   1. Re-enroll → all three terms locked
    //   2. Accounting verifies Downpayment (or Full payment) → sends Prelim notice
    //   3. Prelim notice INSERT/UPDATE sets prelim_status='unpaid' → student can now pay
    //   4. Same pattern for Midterm and Finals
    //
    // Also clear stale payment_notices from the prior semester so old unlocks can't
    // bleed into the new semester's period gate.
    $conn->query("DELETE FROM payment_notices WHERE student_id = $student_id");

    $seedTfRow = $conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id = $student_id LIMIT 1");
    $seedTotal = $seedTfRow ? (float)($seedTfRow->fetch_assoc()['total_assessment'] ?? 0) : 0;
    if ($seedTotal > 0) {
        $conn->query("INSERT INTO payment_schedules
                          (student_id, payment_type, total_assessment,
                           prelim_status, midterm_status, finals_status)
                      VALUES ($student_id, 'full', $seedTotal,
                              'locked', 'locked', 'locked')
                      ON DUPLICATE KEY UPDATE
                          payment_type     = 'full',
                          total_assessment = $seedTotal,
                          prelim_status    = 'locked',
                          midterm_status   = 'locked',
                          finals_status    = 'locked'");
    }


    $auditNote = "Student $student_id re-enrolled: $curYL $curSemTerm → $newYL $newSemLabel (type: $curType → $newType)";
    if ($becameIrregular) {
        $auditNote .= ". Marked IRREGULAR — $failedCount failed subject(s): "
                    . implode(', ', array_slice($failedCourses, 0, 5));
    }
    logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'RE_ENROLL', 'student', $student_id, $auditNote);

    $message = 'Re-enrollment started. Please complete payment for ' . $newSemLabel . '.';
    if ($becameIrregular) {
        $message .= ' Note: you have been marked as irregular due to '
                  . $failedCount . ' failed subject(s). Please consult your adviser.';
    }

    jsonOut([
        'success'       => true,
        'isGraduated'   => false,
        'isIrregular'   => $becameIrregular,
        'failedCount'   => $failedCount,
        'failedCourses' => $failedCourses,
        'message'       => $message,
        'newYearLevel'  => $newYL,
        'newSemester'   => $newSemLabel,
        'newType'       => $newType,
    ]);
}

// ----------------------------------------------------------------
// SEARCH STUDENTS (Registrar: Add/Drop)
// GET ?action=search_students&q=juan&limit=10
// ----------------------------------------------------------------
function searchStudents($conn) {
    $q     = trim($_GET['q']     ?? '');
    $limit = min((int)($_GET['limit'] ?? 15), 50);
    if (strlen($q) < 2) {
        echo json_encode(['success' => true, 'students' => []]);
        return;
    }
    $like = '%' . $q . '%';
    $stmt = $conn->prepare("
        SELECT s.id, s.student_number, s.first_name, s.last_name,
               s.program, s.year_level, s.semester, s.student_category,
               s.enrollment_status, s.approval_status, u.email
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.student_number LIKE ?
           OR s.first_name     LIKE ?
           OR s.last_name      LIKE ?
           OR CONCAT(s.first_name,' ',s.last_name) LIKE ?
        ORDER BY s.last_name, s.first_name
        LIMIT ?
    ");
    $stmt->bind_param('ssssi', $like, $like, $like, $like, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $students = [];
    while ($r = $res->fetch_assoc()) {
        $students[] = [
            'id'               => (int)$r['id'],
            'studentNumber'    => $r['student_number'],
            'firstName'        => $r['first_name'],
            'lastName'         => $r['last_name'],
            'fullName'         => trim($r['first_name'] . ' ' . $r['last_name']),
            'program'          => $r['program'],
            'yearLevel'        => $r['year_level'],
            'semester'         => $r['semester'],
            'studentCategory'  => $r['student_category'],
            'enrollmentStatus' => $r['enrollment_status'],
            'approvalStatus'   => $r['approval_status'],
            'email'            => $r['email'],
        ];
    }
    $stmt->close();
    echo json_encode(['success' => true, 'students' => $students]);
}

// ----------------------------------------------------------------
// GET STUDENT ENROLLMENTS (for Add/Drop view)
// GET ?action=get_student_enrollments&student_id=X
// ----------------------------------------------------------------
function getStudentEnrollments($conn) {
    $sid = (int)($_GET['student_id'] ?? 0);
    // Also accept user_id and resolve to students.id
    if (!$sid) {
        $uid = (int)($_GET['user_id'] ?? 0);
        if ($uid) {
            $rs = $conn->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
            $rs->bind_param("i", $uid);
            $rs->execute();
            $rr = $rs->get_result()->fetch_assoc();
            $rs->close();
            $sid = $rr ? (int)$rr['id'] : 0;
        }
    }
    if (!$sid) { echo json_encode(['success' => false, 'message' => 'student_id required']); return; }

    // Current enrollments
    $stmt = $conn->prepare("
        SELECT e.id AS enrollment_id, e.status, e.enrollment_date,
               c.id AS course_id, c.code, c.name, c.credits,
               COALESCE(c.lec_units, c.credits) AS lec_units,
               COALESCE(c.lab_units, 0)         AS lab_units,
               COALESCE(c.is_general, 0)        AS is_general,
               COALESCE(c.is_lab, 0)            AS is_lab,
               COALESCE(
                NULLIF(TRIM(CONCAT(COALESCE(fj.first_name,''),' ',COALESCE(fj.last_name,''))), ''),
                NULLIF(TRIM(CONCAT(COALESCE(f.first_name,''), ' ',COALESCE(f.last_name,''))), ''),
                NULLIF(TRIM(CONCAT(COALESCE(fc.first_name,''),' ',COALESCE(fc.last_name,''))), ''),
                ''
            ) AS instructor,
               cs.day, CONCAT(cs.time_start,' - ',cs.time_end) AS time,
               r.room_name AS room, c.semester
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN course_sections cs ON cs.course_id = c.id AND cs.is_active = 1
        LEFT JOIN faculty f  ON f.user_id  = cs.faculty_id
        LEFT JOIN faculty fc ON fc.user_id = c.faculty_id
        LEFT JOIN faculty fj ON fj.status = 'Active'
            AND (
                JSON_CONTAINS(fj.subjects, CONCAT(CHAR(34), SUBSTRING_INDEX(c.code,'-',1), CHAR(34)))
                OR JSON_CONTAINS(fj.subjects, CONCAT(CHAR(34), c.code, CHAR(34)))
            )
        LEFT JOIN rooms r ON r.id = cs.room_id
        WHERE e.student_id = ?
          AND (
            e.status IN ('Enrolled','Pending')
            OR (
              -- FIX ENR-01: The unique key on enrollments is (student_id, course_id)
              -- with no semester column. When autoEnrollAll re-inserts a course the
              -- student already completed in a prior semester, ON DUPLICATE KEY UPDATE
              -- leaves the status as 'Completed' instead of 'Enrolled'.
              -- Fix: also show 'Completed' rows matching the student's current semester.
              e.status   = 'Completed'
              AND e.semester = (SELECT semester FROM students WHERE id = ? LIMIT 1)
            )
          )
        GROUP BY e.id
        ORDER BY c.code
    ");
    $stmt->bind_param('ii', $sid, $sid);
    $stmt->execute();
    $res = $stmt->get_result();
    $enrolled = [];
    while ($r = $res->fetch_assoc()) {
        $lec = (int)($r['lec_units'] ?? 0);
        $lab = (int)($r['lab_units'] ?? 0);
        $cred = (int)($r['credits'] ?? 0);
        if ($lec === 0 && $lab === 0 && $cred > 0) $lec = $cred;
        $r['code']      = cleanCode($r['code']);
        $r['lecUnits']  = $lec;
        $r['labUnits']  = $lab;
        $r['isGeneral'] = (bool)($r['is_general'] ?? false);
        $r['isLab']     = (bool)($r['is_lab'] ?? false);
        $enrolled[] = $r;
    }
    $stmt->close();

    // Available courses - get student program first
    $stuStmt = $conn->prepare("SELECT semester, program FROM students WHERE id = ? LIMIT 1");
    $stuStmt->bind_param('i', $sid);
    $stuStmt->execute();
    $stuRow  = $stuStmt->get_result()->fetch_assoc() ?? [];
    $stuStmt->close();
    $stuProg = $stuRow['program'] ?? '';

    // Build available courses query with parameterized program filter
    if ($stuProg !== '') {
        $avStmt = $conn->prepare("
            SELECT c.id, c.code, c.name, c.credits,
                   COALESCE(c.lec_units, c.credits) AS lec_units,
                   COALESCE(c.lab_units, 0)         AS lab_units,
                   COALESCE(c.is_general, 0)        AS is_general,
                   COALESCE(c.is_lab, 0)            AS is_lab,
                   '' AS instructor, '' AS day, '' AS time, '' AS room, c.semester,
                   COALESCE(c.capacity,50) AS capacity,
                   COUNT(e2.id) AS enrolled_count
            FROM courses c
            LEFT JOIN enrollments e2 ON e2.course_id = c.id AND e2.status IN ('Enrolled','Pending')
            WHERE c.id NOT IN (
                SELECT course_id FROM enrollments
                WHERE student_id = ? AND semester = ? AND status IN ('Enrolled','Pending')
            )
            AND (c.program = ? OR c.is_general = 1)
            GROUP BY c.id
            ORDER BY c.code
        ");
        $avSem = $stuRow['semester'] ?? '';
        $avStmt->bind_param('iss', $sid, $avSem, $stuProg);
    } else {
        $avStmt = $conn->prepare("
            SELECT c.id, c.code, c.name, c.credits,
                   COALESCE(c.lec_units, c.credits) AS lec_units,
                   COALESCE(c.lab_units, 0)         AS lab_units,
                   COALESCE(c.is_general, 0)        AS is_general,
                   COALESCE(c.is_lab, 0)            AS is_lab,
                   '' AS instructor, '' AS day, '' AS time, '' AS room, c.semester,
                   COALESCE(c.capacity,50) AS capacity,
                   COUNT(e2.id) AS enrolled_count
            FROM courses c
            LEFT JOIN enrollments e2 ON e2.course_id = c.id AND e2.status IN ('Enrolled','Pending')
            WHERE c.id NOT IN (
                SELECT course_id FROM enrollments
                WHERE student_id = ? AND status IN ('Enrolled','Pending')
            )
            GROUP BY c.id
            ORDER BY c.code
        ");
        $avStmt->bind_param('i', $sid);
    }
    $avStmt->execute();
    $avRes = $avStmt->get_result();
    $available = [];
    while ($r = $avRes->fetch_assoc()) {
        $r['code'] = cleanCode($r['code']);
        $r['available_seats'] = max(0, (int)$r['capacity'] - (int)$r['enrolled_count']);
        $lec = (int)($r['lec_units'] ?? 0);
        $lab = (int)($r['lab_units'] ?? 0);
        $cred = (int)($r['credits'] ?? 0);
        if ($lec === 0 && $lab === 0 && $cred > 0) $lec = $cred;
        $r['lecUnits']  = $lec;
        $r['labUnits']  = $lab;
        $r['isGeneral'] = (bool)($r['is_general'] ?? false);
        $r['isLab']     = (bool)($r['is_lab'] ?? false);
        $available[] = $r;
    }
    $avStmt->close();

    echo json_encode(['success' => true, 'student_id' => $sid, 'enrolled' => $enrolled, 'available' => $available]);
}

// ----------------------------------------------------------------
// REGISTRAR: ADD SUBJECT to student
// POST { student_id, course_id, reason }
// ----------------------------------------------------------------
// ─────────────────────────────────────────────────────────────────────────────
// STUDENT: Declare scholarship during enrollment
// POST { student_id, scholar_type, grantor, scholarship_amount }
// Saves as pending — accounting must approve before discount is applied.
// ─────────────────────────────────────────────────────────────────────────────
function declareScholarship($conn, $data) {
    global $authUser;
    $sid    = (int)($data['student_id']        ?? 0);
    $type   = trim($data['scholar_type']       ?? '');
    $grantor= trim($data['grantor']            ?? '');
    $amount = (float)($data['scholarship_amount'] ?? 0);

    if (!$sid || !$type || !$grantor) {
        echo json_encode(['success'=>false,'message'=>'student_id, scholar_type, and grantor required']);
        return;
    }

    // Ownership check — student can only declare their own scholarship
    if (($authUser['role'] ?? '') === 'student') {
        $own = $conn->prepare("SELECT id FROM students WHERE id=? AND user_id=? LIMIT 1");
        $own->bind_param('ii', $sid, $authUser['user_id']);
        $own->execute();
        if ($own->get_result()->num_rows === 0) {
            echo json_encode(['success'=>false,'message'=>'Access denied.']);
            return;
        }
        $own->close();
    }

    // Get student semester
    $semRes = $conn->query("SELECT semester FROM students WHERE id=$sid LIMIT 1");
    $semester = $semRes ? ($semRes->fetch_assoc()['semester'] ?? '') : '';

    // Deactivate any previous pending scholarship for this student
    $conn->query("UPDATE student_scholarships SET is_active=0, status='superseded' WHERE student_id=$sid AND status='pending'");

    // Insert new pending scholarship
    $ins = $conn->prepare("INSERT INTO student_scholarships (student_id, scholar_type, grantor, scholarship_amount, semester, is_active, status) VALUES (?,?,?,?,?,0,'pending')");
    $ins->bind_param('issds', $sid, $type, $grantor, $amount, $semester);
    $ins->execute();
    $ins->close();

    // Mark student as scholar (pending — no discount yet)
    $upd = $conn->prepare("UPDATE students SET is_scholar=1, scholar_type=?, scholar_grantor=? WHERE id=?");
    $upd->bind_param('ssi', $type, $grantor, $sid);
    $upd->execute();
    $upd->close();

    logAuditShared($conn, $authUser, 'DECLARE_SCHOLARSHIP', 'student', $sid,
        "Student declared scholarship: $type by $grantor. Pending accounting approval.");

    while (ob_get_level() > 0) { ob_end_clean(); } $_cO = $_SERVER['HTTP_ORIGIN'] ?? '*'; header("Access-Control-Allow-Origin: $_cO", true); header('Access-Control-Allow-Credentials: true', true); header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS', true); header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token', true); header('Access-Control-Expose-Headers: X-New-Token', true); header('Content-Type: application/json', true);
    echo json_encode([
        'success' => true,
        'message' => 'Scholarship application submitted. Accounting will review and approve.',
    ]);
}

function registrarAddSubject($conn, $data) {
    $sid = (int)($data['student_id'] ?? 0);
    $cid = (int)($data['course_id']  ?? 0);
    if (!$sid || !$cid) { echo json_encode(['success' => false, 'message' => 'student_id and course_id required']); return; }

    // Duplicate check — semester-scoped so registrar can add a course the student
    // previously completed in a different semester
    $stuSemStmt = $conn->prepare("SELECT semester FROM students WHERE id=? LIMIT 1");
    $stuSemStmt->bind_param('i', $sid);
    $stuSemStmt->execute();
    $stuSemRow = $stuSemStmt->get_result()->fetch_assoc();
    $stuSemStmt->close();
    $currentSem = $stuSemRow['semester'] ?? '';

    $dup = $conn->prepare("SELECT id FROM enrollments WHERE student_id=? AND course_id=? AND semester=? AND status IN ('Enrolled','Pending') LIMIT 1");
    $dup->bind_param("iis", $sid, $cid, $currentSem);
    $dup->execute();
    $isDup = $dup->get_result()->num_rows > 0;
    $dup->close();
    if ($isDup) { echo json_encode(['success' => false, 'message' => 'Student is already enrolled in this subject']); return; }

    // Capacity check
    $capStmt = $conn->prepare("SELECT COALESCE(capacity,50) AS cap, COUNT(e.id) AS cnt
        FROM courses c LEFT JOIN enrollments e ON e.course_id=c.id AND e.status IN ('Enrolled','Pending')
        WHERE c.id=? GROUP BY c.id LIMIT 1");
    $capStmt->bind_param('i', $cid);
    $capStmt->execute();
    $cap = $capStmt->get_result()->fetch_assoc();
    $capStmt->close();
    if ($cap && (int)$cap['cnt'] >= (int)$cap['cap']) {
        echo json_encode(['success' => false, 'message' => 'Subject is full']); return;
    }

    $reason = trim($data['reason'] ?? 'Add/Drop by Registrar');
    $date   = date('Y-m-d');
    $ins = $conn->prepare("
        INSERT INTO enrollments (student_id,course_id,enrollment_date,status,semester,notes)
        VALUES (?,?,?,'Enrolled',?,?)
        ON DUPLICATE KEY UPDATE
            status = IF(status='Dropped','Enrolled',status),
            enrollment_date = IF(status='Dropped',VALUES(enrollment_date),enrollment_date),
            notes = IF(status='Dropped',VALUES(notes),notes)
    ");
    $ins->bind_param('iisss', $sid, $cid, $date, $currentSem, $reason);
    $ins->execute();
    if ($ins->affected_rows > 0) {
        $ins->close();
        logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'ADD_SUBJECT', 'enrollment', $cid,
            "Registrar added subject (course $cid) for student $sid: $reason");

        // ── Log fee impact for Accounting ────────────────────────────────────
        try { _logSubjectFeeImpact($conn, $sid, $cid, 'Add', $reason); }
        catch (Throwable $e) { error_log('[registrarAdd fee_log] ' . $e->getMessage()); }

        echo json_encode(['success' => true, 'message' => 'Subject added successfully']);
    } else {
        $err = $ins->error;
        $ins->close();
        echo json_encode(['success' => false, 'message' => 'Failed to add subject: ' . $err]);
    }
}

/**
 * Log the fee impact of adding or dropping a subject to subject_fee_log.
 * Called by registrarAddSubject and registrarDropSubject.
 */
function _logSubjectFeeImpact(mysqli $conn, int $sid, int $cid, string $action, string $reason): void {
    // Auto-create table if migrate.php hasn't been run yet
    $conn->query("CREATE TABLE IF NOT EXISTS subject_fee_log (
        id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL, course_id INT NOT NULL,
        course_code VARCHAR(20) DEFAULT NULL, course_name VARCHAR(150) DEFAULT NULL,
        action ENUM('Add','Drop') NOT NULL DEFAULT 'Add',
        subject_type VARCHAR(50) DEFAULT NULL, course_category VARCHAR(50) DEFAULT NULL,
        units INT DEFAULT 0, lec_units INT DEFAULT 0, lab_units INT DEFAULT 0,
        tuition_impact DECIMAL(10,2) NOT NULL DEFAULT 0,
        lab_fee_impact DECIMAL(10,2) NOT NULL DEFAULT 0,
        energy_impact DECIMAL(10,2) NOT NULL DEFAULT 0,
        total_impact DECIMAL(10,2) NOT NULL DEFAULT 0,
        semester VARCHAR(100) DEFAULT NULL, reason VARCHAR(255) DEFAULT NULL,
        added_by_role VARCHAR(50) DEFAULT NULL, added_by_email VARCHAR(150) DEFAULT NULL,
        performed_by INT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Fetch course details
    $cRes = $conn->prepare("SELECT code, name, credits, lec_units, lab_units, is_lab, department, program, semester FROM courses WHERE id=? LIMIT 1");
    $cRes->bind_param('i', $cid);
    $cRes->execute();
    $c = $cRes->get_result()->fetch_assoc();
    $cRes->close();
    if (!$c) return;

    // Fetch fee rates
    $fcRes = $conn->query("SELECT fee_key, value FROM fee_config WHERE category='College' AND is_active=1");
    $fc = [];
    if ($fcRes) while ($fr = $fcRes->fetch_assoc()) $fc[$fr['fee_key']] = (float)$fr['value'];
    $tuitionRate = $fc['tuition_rate_per_unit'] ?? 650;
    $energyRate  = $fc['energy_rate_per_unit']  ?? 63;
    $labFeeRate  = $fc['lab_fee_per_room']       ?? 1900;

    $units    = (int)($c['credits']   ?? 0);
    $lecUnits = (int)($c['lec_units'] ?? 0);
    $labUnits = (int)($c['lab_units'] ?? 0);
    $isLab    = (int)($c['is_lab']    ?? 0);

    // Determine subject type label
    $subjectType = ($isLab || $labUnits > 0) ? 'Laboratory' : 'Lecture';

    // Determine category from course code / department
    $code = strtoupper($c['code'] ?? '');
    if (preg_match('/^(GE|NSTP|PE)/i', $code))          $category = 'General Education';
    elseif (preg_match('/^PE/i', $code))                 $category = 'Physical Education';
    elseif (preg_match('/^NSTP/i', $code))               $category = 'NSTP';
    elseif (preg_match('/^(IT|CC|CS|IS|ICT)/i', $code)) $category = 'Major';
    else                                                  $category = 'Minor';

    // Calculate fee impacts
    // FIX ADD-DROP-02: Lab fee is a per-room facility charge — it is only added
    // when a lab subject is ADDED. Dropping a subject does NOT reduce the lab room
    // count (other students still use it), so lab_fee_impact is always 0 on Drop.
    $sign           = ($action === 'Drop') ? -1 : 1;
    $tuitionImpact  = round($sign * $units * $tuitionRate, 2);
    $labFeeImpact   = ($action === 'Add' && $isLab) ? round($labFeeRate, 2) : 0.00;
    $energyImpact   = round($sign * $units * $energyRate, 2);
    $totalImpact    = round($tuitionImpact + $labFeeImpact + $energyImpact, 2);

    // Fetch student semester
    $semRes = $conn->prepare("SELECT semester FROM students WHERE id=? LIMIT 1");
    $semRes->bind_param('i', $sid);
    $semRes->execute();
    $semRow = $semRes->get_result()->fetch_assoc();
    $semRes->close();
    $semester = $semRow['semester'] ?? '';

    $authUser      = $GLOBALS['authUser'] ?? null;
    $addedByRole   = $authUser['role']  ?? 'registrar';
    $addedByEmail  = $authUser['email'] ?? '';
    $courseCode    = $c['code'];
    $courseName    = $c['name'];

    $stmt = $conn->prepare(
        "INSERT INTO subject_fee_log
            (student_id, course_id, course_code, course_name, action, subject_type,
             course_category, units, lec_units, lab_units,
             tuition_impact, lab_fee_impact, energy_impact, total_impact,
             semester, reason, added_by_role, added_by_email)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    if (!$stmt) {
        error_log('[subject_fee_log] prepare failed: ' . $conn->error);
        return;
    }
    $stmt->bind_param(
        'iisssssiiddddssss',
        $sid, $cid, $courseCode, $courseName, $action, $subjectType,
        $category, $units, $lecUnits, $labUnits,
        $tuitionImpact, $labFeeImpact, $energyImpact, $totalImpact,
        $semester, $reason, $addedByRole, $addedByEmail
    );
    if (!$stmt->execute()) {
        error_log('[subject_fee_log] execute failed: ' . $stmt->error);
    }
    $stmt->close();
}

// ----------------------------------------------------------------
// REGISTRAR: DROP SUBJECT from student
// POST { student_id, enrollment_id, reason }
// ----------------------------------------------------------------
function registrarDropSubject($conn, $data) {
    $sid = (int)($data['student_id']    ?? 0);
    $eid = (int)($data['enrollment_id'] ?? 0);
    if (!$sid || !$eid) { echo json_encode(['success' => false, 'message' => 'student_id and enrollment_id required']); return; }

    $rowStmt = $conn->prepare("SELECT course_id FROM enrollments WHERE id=? AND student_id=? AND status IN ('Enrolled','Pending','Completed') LIMIT 1");
    $rowStmt->bind_param('ii', $eid, $sid);
    $rowStmt->execute();
    $row = $rowStmt->get_result()->fetch_assoc();
    $rowStmt->close();
    if (!$row) { echo json_encode(['success' => false, 'message' => 'Enrollment not found']); return; }

    $cid    = (int)$row['course_id'];
    $reason = trim($data['reason'] ?? 'Dropped by Registrar');
    $dropNote = ' | Drop reason: ' . $reason;

    $upd = $conn->prepare("UPDATE enrollments SET status='Dropped', notes=CONCAT(COALESCE(notes,''),?) WHERE id=? AND student_id=?");
    $upd->bind_param('sii', $dropNote, $eid, $sid);
    $upd->execute();
    if ($upd->affected_rows > 0) {
        $upd->close();
        logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'DROP_SUBJECT', 'enrollment', $cid,
            "Registrar dropped subject (course $cid) for student $sid: $reason");
        // ── Log fee impact for Accounting ────────────────────────────────────
        try { _logSubjectFeeImpact($conn, $sid, $cid, 'Drop', $reason); }
        catch (Throwable $e) { error_log('[registrarDrop fee_log] ' . $e->getMessage()); }
        echo json_encode(['success' => true, 'message' => 'Subject dropped successfully']);
    } else {
        $err = $upd->error;
        $upd->close();
        echo json_encode(['success' => false, 'message' => 'Drop failed: ' . $err]);
    }
}

// ----------------------------------------------------------------
// AUTO-CREATE add_drop_requests table if missing
// Called lazily on first use
// ----------------------------------------------------------------
function ensureAddDropTable($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS add_drop_requests (
        id                      INT AUTO_INCREMENT PRIMARY KEY,
        student_id              INT NOT NULL,
        request_type            ENUM('Add','Drop') NOT NULL,
        course_id               INT NOT NULL,
        enrollment_id           INT DEFAULT NULL,
        reason                  TEXT DEFAULT NULL,
        status                  ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        remarks                 TEXT DEFAULT NULL,
        processed_by            INT DEFAULT NULL,
        processed_at            DATETIME DEFAULT NULL,
        -- Accounting review fields
        accounting_status       ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        accounting_reviewed_by  INT DEFAULT NULL,
        accounting_reviewed_at  DATETIME DEFAULT NULL,
        accounting_notes        TEXT DEFAULT NULL,
        fee_impact              DECIMAL(10,2) DEFAULT 0,
        new_total_assessment    DECIMAL(10,2) DEFAULT 0,
        created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_student (student_id),
        INDEX idx_status  (status),
        INDEX idx_acc_status (accounting_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Safely add columns if table already exists (for existing deployments)
    foreach ([
        "ALTER TABLE add_drop_requests ADD COLUMN IF NOT EXISTS accounting_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending'",
        "ALTER TABLE add_drop_requests ADD COLUMN IF NOT EXISTS accounting_reviewed_by INT DEFAULT NULL",
        "ALTER TABLE add_drop_requests ADD COLUMN IF NOT EXISTS accounting_reviewed_at DATETIME DEFAULT NULL",
        "ALTER TABLE add_drop_requests ADD COLUMN IF NOT EXISTS accounting_notes TEXT DEFAULT NULL",
        "ALTER TABLE add_drop_requests ADD COLUMN IF NOT EXISTS fee_impact DECIMAL(10,2) DEFAULT 0",
        "ALTER TABLE add_drop_requests ADD COLUMN IF NOT EXISTS new_total_assessment DECIMAL(10,2) DEFAULT 0",
    ] as $sql) {
        $conn->query($sql);
    }
}

// ----------------------------------------------------------------
// STUDENT: Submit Add or Drop request
// POST { student_id, request_type:'Add'|'Drop', course_id, enrollment_id?, reason }
// ----------------------------------------------------------------
function submitAddDropRequest($conn, $data) {
    ensureAddDropTable($conn);
    ensureAddDropWindowTable($conn);
    $sid  = (int)($data['student_id']    ?? 0);
    // Resolve user_id → students.id if needed
    if (!$sid) {
        $uid = (int)($data['user_id'] ?? 0);
        if ($uid) {
            $rs = $conn->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
            $rs->bind_param("i", $uid);
            $rs->execute();
            $rr = $rs->get_result()->fetch_assoc();
            $rs->close();
            $sid = $rr ? (int)$rr['id'] : 0;
        }
    }
    $type = trim($data['request_type']   ?? '');
    $cid  = (int)($data['course_id']     ?? 0);
    $eid  = (int)($data['enrollment_id'] ?? 0);
    $reason = trim($data['reason']       ?? '');

    if (!$sid || !$type || !$cid) {
        echo json_encode(['success'=>false,'message'=>'student_id, request_type, course_id required']); return;
    }
    if (!in_array($type, ['Add','Drop'])) {
        echo json_encode(['success'=>false,'message'=>'request_type must be Add or Drop']); return;
    }

    // Check if Add/Drop window is open
    $winRes = $conn->query("SELECT * FROM add_drop_window WHERE is_active=1 ORDER BY id DESC LIMIT 1");
    $win = $winRes ? $winRes->fetch_assoc() : null;
    $now = date('Y-m-d H:i:s');
    if (!$win || $now < $win['start_date'] || $now > $win['end_date']) {
        $msg = $win
            ? 'Add/Drop period is closed. Window: ' . date('M d, Y h:i A', strtotime($win['start_date'])) . ' – ' . date('M d, Y h:i A', strtotime($win['end_date']))
            : 'Add/Drop is not currently open. Please wait for the Registrar to set the schedule.';
        echo json_encode(['success'=>false,'message'=>$msg,'window_closed'=>true]); return;
    }

    // Prevent duplicate pending requests for the same course in the same semester
    $dupStmt = $conn->prepare("SELECT id FROM add_drop_requests WHERE student_id=? AND course_id=? AND request_type=? AND status='Pending' LIMIT 1");
    $dupStmt->bind_param('iis', $sid, $cid, $type);
    $dupStmt->execute();
    $isDup = $dupStmt->get_result()->num_rows > 0;
    $dupStmt->close();
    if ($isDup) {
        echo json_encode(['success'=>false,'message'=>'You already have a pending '.$type.' request for this subject']); return;
    }

    $eidVal = $eid > 0 ? $eid : null;
    $stmt = $conn->prepare("INSERT INTO add_drop_requests (student_id,request_type,course_id,enrollment_id,reason) VALUES (?,?,?,?,?)");
    if (!$stmt) {
        echo json_encode(['success'=>false,'message'=>'DB prepare error: '.$conn->error]); return;
    }
    $stmt->bind_param("issis", $sid, $type, $cid, $eidVal, $reason);
    $stmt->execute();
    $insertId = $conn->insert_id;
    $affected = $stmt->affected_rows;
    $stmtErr  = $stmt->error;
    $stmt->close();
    if ($affected > 0) {
        logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'SUBMIT_ADD_DROP', 'enrollment', $cid,
            "$type request submitted by student ID $sid for course ID $cid. Reason: $reason");
        echo json_encode(['success'=>true,'message'=>ucfirst(strtolower($type)).' request submitted. Awaiting registrar approval.','id'=>$insertId]);
    } else {
        echo json_encode(['success'=>false,'message'=>'Failed to submit request: '.$stmtErr]);
    }
}

// ----------------------------------------------------------------
// STUDENT: Get own add/drop requests
// GET ?action=get_my_add_drop&student_id=X
// ----------------------------------------------------------------
function getMyAddDrop($conn) {
    ensureAddDropTable($conn);
    $sid = (int)($_GET['student_id'] ?? 0);
    // Also accept user_id
    if (!$sid) {
        $uid = (int)($_GET['user_id'] ?? 0);
        if ($uid) {
            $rs = $conn->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
            $rs->bind_param("i", $uid);
            $rs->execute();
            $rr = $rs->get_result()->fetch_assoc();
            $rs->close();
            $sid = $rr ? (int)$rr['id'] : 0;
        }
    }
    if (!$sid) { echo json_encode(['success'=>false,'message'=>'student_id required']); return; }

    $res = $conn->query("
        SELECT r.*, c.code, c.name AS course_name, c.credits,
               COALESCE(
                NULLIF(TRIM(CONCAT(COALESCE(fj.first_name,''),' ',COALESCE(fj.last_name,''))), ''),
                NULLIF(TRIM(CONCAT(COALESCE(f.first_name,''), ' ',COALESCE(f.last_name,''))), ''),
                NULLIF(TRIM(CONCAT(COALESCE(fc.first_name,''),' ',COALESCE(fc.last_name,''))), ''),
                ''
            ) AS instructor,
               cs.day, CONCAT(cs.time_start,' - ',cs.time_end) AS time,
               rm.room_name AS room
        FROM add_drop_requests r
        JOIN courses c ON r.course_id = c.id
        LEFT JOIN course_sections cs ON cs.course_id = c.id AND cs.is_active = 1
        LEFT JOIN faculty f  ON f.user_id  = cs.faculty_id
        LEFT JOIN faculty fc ON fc.user_id = c.faculty_id
        LEFT JOIN faculty fj ON fj.status = 'Active'
            AND fj.subjects IS NOT NULL
            AND (
                COALESCE(fj.subjects,'') LIKE CONCAT('%', CHAR(34), SUBSTRING_INDEX(c.code,'-',1), CHAR(34), '%')
                OR COALESCE(fj.subjects,'') LIKE CONCAT('%', CHAR(34), c.code, CHAR(34), '%')
            )
        LEFT JOIN rooms rm ON rm.id = cs.room_id
        WHERE r.student_id = $sid
        ORDER BY r.created_at DESC
    ");
    $rows = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $r['code'] = cleanCode($r['code']);
            $rows[] = $r;
        }
    }
    echo json_encode(['success'=>true,'requests'=>$rows]);
}

// ----------------------------------------------------------------
// REGISTRAR: Get all pending add/drop requests (or filter by status)
// GET ?action=get_add_drop_requests&status=Pending
// ----------------------------------------------------------------
function getAddDropRequests($conn) {
    ensureAddDropTable($conn);
    $status = trim($_GET['status'] ?? '');

    $validStatuses = ['Pending','Approved','Rejected'];
    if ($status !== '' && !in_array($status, $validStatuses, true)) {
        echo json_encode(['success'=>false,'message'=>'Invalid status filter']); return;
    }

    if ($status !== '') {
        $stmt = $conn->prepare("
            SELECT r.*,
                   c.code, c.name AS course_name, c.credits,
                   COALESCE(
                NULLIF(TRIM(CONCAT(COALESCE(f.first_name,''), ' ',COALESCE(f.last_name,''))), ''),
                NULLIF(TRIM(CONCAT(COALESCE(fc.first_name,''),' ',COALESCE(fc.last_name,''))), ''),
                ''
            ) AS instructor,
                   cs.day, CONCAT(cs.time_start,' - ',cs.time_end) AS time,
                   r2.room_name AS room,
                   s.first_name, s.last_name, s.student_number, s.program, s.year_level, s.student_category,
                   tf.total_assessment AS current_total,
                   COALESCE(r.accounting_status,'Pending') AS accounting_status,
                   r.accounting_notes, r.fee_impact, r.new_total_assessment
            FROM add_drop_requests r
            JOIN courses  c ON r.course_id  = c.id
            LEFT JOIN course_sections cs ON cs.course_id = c.id AND cs.is_active = 1
            LEFT JOIN faculty f ON f.user_id = cs.faculty_id
            LEFT JOIN faculty fc ON fc.user_id = c.faculty_id
            LEFT JOIN rooms r2 ON r2.id = cs.room_id
            JOIN students s ON r.student_id = s.id
            LEFT JOIN (SELECT student_id, semester, total_assessment, subtotal, units, discount, installment_fee, tuition_fee FROM tuition_fees WHERE id IN (SELECT MAX(id) FROM tuition_fees GROUP BY student_id)) tf ON tf.student_id = s.id
                                     AND tf.semester   = s.semester
            WHERE r.status = ?
            ORDER BY r.created_at DESC
        ");
        $stmt->bind_param('s', $status);
    } else {
        $stmt = $conn->prepare("
            SELECT r.*,
                   c.code, c.name AS course_name, c.credits,
                   COALESCE(
                NULLIF(TRIM(CONCAT(COALESCE(f.first_name,''), ' ',COALESCE(f.last_name,''))), ''),
                NULLIF(TRIM(CONCAT(COALESCE(fc.first_name,''),' ',COALESCE(fc.last_name,''))), ''),
                ''
            ) AS instructor,
                   cs.day, CONCAT(cs.time_start,' - ',cs.time_end) AS time,
                   r2.room_name AS room,
                   s.first_name, s.last_name, s.student_number, s.program, s.year_level, s.student_category,
                   tf.total_assessment AS current_total,
                   COALESCE(r.accounting_status,'Pending') AS accounting_status,
                   r.accounting_notes, r.fee_impact, r.new_total_assessment
            FROM add_drop_requests r
            JOIN courses  c ON r.course_id  = c.id
            LEFT JOIN course_sections cs ON cs.course_id = c.id AND cs.is_active = 1
            LEFT JOIN faculty f ON f.user_id = cs.faculty_id
            LEFT JOIN faculty fc ON fc.user_id = c.faculty_id
            LEFT JOIN rooms r2 ON r2.id = cs.room_id
            JOIN students s ON r.student_id = s.id
            LEFT JOIN (SELECT student_id, semester, total_assessment, subtotal, units, discount, installment_fee, tuition_fee FROM tuition_fees WHERE id IN (SELECT MAX(id) FROM tuition_fees GROUP BY student_id)) tf ON tf.student_id = s.id
                                     AND tf.semester   = s.semester
            ORDER BY r.created_at DESC
        ");
    }
    $stmt->execute();
    $res  = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $r['code'] = cleanCode($r['code']);
        $rows[] = $r;
    }
    $stmt->close();
    echo json_encode(['success'=>true,'requests'=>$rows]);
}
// POST { request_id, action:'Approved'|'Rejected', remarks, processed_by }
// ----------------------------------------------------------------
function processAddDropRequest($conn, $data) {
    try {
    ensureAddDropTable($conn);

    // ── Role guard ───────────────────────────────────────────────────────────
    $authUser = $GLOBALS['authUser'] ?? null;
    if (!$authUser || !in_array($authUser['role'] ?? '', ['registrar','admin'], true)) {
        while (ob_get_level() > 0) { ob_end_clean(); } $_cO = $_SERVER['HTTP_ORIGIN'] ?? '*'; header("Access-Control-Allow-Origin: $_cO", true); header('Access-Control-Allow-Credentials: true', true); header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS', true); header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token', true); header('Access-Control-Expose-Headers: X-New-Token', true); header('Content-Type: application/json', true);
        echo json_encode(['success'=>false,'message'=>'Access denied. Only Registrar or Admin can approve add/drop requests.']);
        return;
    }

    $rid    = (int)($data['request_id']   ?? 0);
    $action = trim($data['action']        ?? '');
    $remarks= trim($data['remarks']       ?? '');
    $pby    = (int)($authUser['user_id']  ?? 0);

    if (!$rid || !in_array($action, ['Approved','Rejected'])) {
        while (ob_get_level() > 0) { ob_end_clean(); } $_cO = $_SERVER['HTTP_ORIGIN'] ?? '*'; header("Access-Control-Allow-Origin: $_cO", true); header('Access-Control-Allow-Credentials: true', true); header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS', true); header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token', true); header('Access-Control-Expose-Headers: X-New-Token', true); header('Content-Type: application/json', true);
        echo json_encode(['success'=>false,'message'=>'request_id and action (Approved|Rejected) required']); return;
    }

    $reqStmt = $conn->prepare("SELECT * FROM add_drop_requests WHERE id=? AND status='Pending' LIMIT 1");
    $reqStmt->bind_param('i', $rid);
    $reqStmt->execute();
    $req = $reqStmt->get_result()->fetch_assoc();
    $reqStmt->close();
    if (!$req) { while (ob_get_level() > 0) { ob_end_clean(); } $_cO = $_SERVER['HTTP_ORIGIN'] ?? '*'; header("Access-Control-Allow-Origin: $_cO", true); header('Access-Control-Allow-Credentials: true', true); header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS', true); header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token', true); header('Access-Control-Expose-Headers: X-New-Token', true); header('Content-Type: application/json', true); echo json_encode(['success'=>false,'message'=>'Request not found or already processed']); return; }

    // ── Require Accounting approval before Registrar can approve ────────────
    $accStatus = $req['accounting_status'] ?? 'Pending';
    if ($action === 'Approved' && $accStatus !== 'Approved') {
        while (ob_get_level() > 0) { ob_end_clean(); } $_cO = $_SERVER['HTTP_ORIGIN'] ?? '*'; header("Access-Control-Allow-Origin: $_cO", true); header('Access-Control-Allow-Credentials: true', true); header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS', true); header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token', true); header('Access-Control-Expose-Headers: X-New-Token', true); header('Content-Type: application/json', true);
        echo json_encode([
            'success' => false,
            'message' => 'This request is still awaiting Accounting approval. Please ask Accounting to review the fee impact first.',
            'accounting_status' => $accStatus,
        ]);
        return;
    }

    $sid  = (int)$req['student_id'];
    $cid  = (int)$req['course_id'];
    $eid  = (int)$req['enrollment_id'];
    $type = $req['request_type'];

    if ($action === 'Approved') {
        // Resolve student's current semester for scoped duplicate check and INSERT
        $adSemStmt = $conn->prepare("SELECT semester FROM students WHERE id=? LIMIT 1");
        $adSemStmt->bind_param('i', $sid);
        $adSemStmt->execute();
        $adSemRow = $adSemStmt->get_result()->fetch_assoc();
        $adSemStmt->close();
        $adCurrentSem = $adSemRow['semester'] ?? '';

        if ($type === 'Add') {
            // FIX HISTORY-01: Semester-scoped duplicate check
            $dupChk = $conn->prepare("SELECT id FROM enrollments WHERE student_id=? AND course_id=? AND semester=? AND status IN ('Enrolled','Pending') LIMIT 1");
            $dupChk->bind_param('iis', $sid, $cid, $adCurrentSem);
            $dupChk->execute();
            $alreadyIn = $dupChk->get_result()->num_rows > 0;
            $dupChk->close();
            if ($alreadyIn) {
                echo json_encode(['success'=>true,'message'=>'Request approved (student already enrolled in this subject)']); return;
            }
            $date = date('Y-m-d');
            $note = "Add/Drop Request #$rid approved by Registrar";
            $insE = $conn->prepare("
                INSERT INTO enrollments (student_id,course_id,enrollment_date,status,semester,notes)
                VALUES (?,?,?,'Enrolled',?,?)
                ON DUPLICATE KEY UPDATE
                    status=IF(status='Dropped','Enrolled',status),
                    enrollment_date=IF(status='Dropped',VALUES(enrollment_date),enrollment_date),
                    notes=IF(status='Dropped',VALUES(notes),notes)
            ");
            $insE->bind_param('iisss', $sid, $cid, $date, $adCurrentSem, $note);
            $insE->execute();
            $insE->close();
            // Log fee impact — labeled "Registrar Approved" to distinguish from the
            // "Accounting Approved" pre-approval entry written by accountingApproveAddDrop().
            try { _logSubjectFeeImpact($conn, $sid, $cid, 'Add', "Registrar Approved: " . ($remarks ?: "Add/Drop request #$rid")); }
            catch (Throwable $e) { error_log('[add_drop fee_log] ' . $e->getMessage()); }
        } else {
            $dropNote = " | Add/Drop Request #$rid approved";
            if ($eid > 0) {
                $dropStmt = $conn->prepare("UPDATE enrollments SET status='Dropped',notes=CONCAT(COALESCE(notes,''),?) WHERE id=? AND status IN ('Enrolled','Pending') LIMIT 1");
                $dropStmt->bind_param('si', $dropNote, $eid);
            } else {
                $dropStmt = $conn->prepare("UPDATE enrollments SET status='Dropped',notes=CONCAT(COALESCE(notes,''),?) WHERE student_id=? AND course_id=? AND status IN ('Enrolled','Pending') LIMIT 1");
                $dropStmt->bind_param('sii', $dropNote, $sid, $cid);
            }
            $dropStmt->execute();
            $dropStmt->close();
            // Log fee impact — labeled "Registrar Approved" to distinguish from Accounting's pre-approval entry.
            try { _logSubjectFeeImpact($conn, $sid, $cid, 'Drop', "Registrar Approved: " . ($remarks ?: "Add/Drop request #$rid")); }
            catch (Throwable $e) { error_log('[add_drop fee_log] ' . $e->getMessage()); }
        }
    }

    // Update request status AFTER successful processing
    $updReq = $conn->prepare("UPDATE add_drop_requests SET status=?,remarks=?,processed_by=?,processed_at=NOW() WHERE id=?");
    $updReq->bind_param('ssii', $action, $remarks, $pby, $rid);
    $updReq->execute();
    $updReq->close();

    if ($action === 'Approved') {
        try { recalcTuitionAfterAddDrop($conn, $sid); }
        catch (Throwable $e) { error_log('[add_drop recalc] ' . $e->getMessage()); }
    }

    logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'PROCESS_ADD_DROP', 'enrollment', $rid,
        "Add/Drop request #$rid ($type course $cid for student $sid) $action by registrar.");
    while (ob_get_level() > 0) { ob_end_clean(); } $_cO = $_SERVER['HTTP_ORIGIN'] ?? '*'; header("Access-Control-Allow-Origin: $_cO", true); header('Access-Control-Allow-Credentials: true', true); header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS', true); header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token', true); header('Access-Control-Expose-Headers: X-New-Token', true); header('Content-Type: application/json', true);
    echo json_encode(['success'=>true,'message'=>'Request '.$action.' successfully']);
    } catch (Throwable $e) {
        while (ob_get_level() > 0) { ob_end_clean(); } $_cO = $_SERVER['HTTP_ORIGIN'] ?? '*'; header("Access-Control-Allow-Origin: $_cO", true); header('Access-Control-Allow-Credentials: true', true); header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS', true); header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token', true); header('Access-Control-Expose-Headers: X-New-Token', true); header('Content-Type: application/json', true);
        http_response_code(200); // Return 200 so Angular reads the body
        echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage().' in '.$e->getFile().' line '.$e->getLine()]);
    }
}
// ----------------------------------------------------------------
// RECALCULATE TUITION FEES after Add/Drop approval
// Uses actual enrolled credits from enrollments table
// ----------------------------------------------------------------
function recalcTuitionAfterAddDrop($conn, $sid) {
    if (!$sid) return;

    // Get student info
    $stStmt = $conn->prepare("SELECT program FROM students WHERE id=? LIMIT 1");
    $stStmt->bind_param('i', $sid);
    $stStmt->execute();
    $stRow = $stStmt->get_result()->fetch_assoc();
    $stStmt->close();
    if (!$stRow) return;

    // Scholar discount — check if full scholar first
    $schStmt = $conn->prepare("
        SELECT s.is_scholar, s.scholarship_amount,
               COALESCE(ss.scholarship_amount, 0) AS active_scholarship
        FROM students s
        LEFT JOIN student_scholarships ss ON ss.student_id = s.id AND ss.is_active = 1
        WHERE s.id = ? LIMIT 1
    ");
    $schStmt->bind_param('i', $sid);
    $schStmt->execute();
    $schRow = $schStmt->get_result()->fetch_assoc();
    $schStmt->close();
    $isScholar         = (int)($schRow['is_scholar']          ?? 0);
    $scholarshipAmount = (float)($schRow['scholarship_amount'] ?? 0);

    $psStmt = $conn->prepare("SELECT payment_type FROM payment_schedules WHERE student_id=? ORDER BY id DESC LIMIT 1");
    $psStmt->bind_param('i', $sid);
    $psStmt->execute();
    $psRow2 = $psStmt->get_result()->fetch_assoc();
    $psStmt->close();
    $has_installment = ($psRow2 && $psRow2['payment_type'] === 'installment');

    // Sum ACTUAL enrolled credits from enrollments table
    // FIX ENR-01: Also count 'Completed' rows for the current semester —
    // same root-cause as getStudentEnrollments: unique key (student_id, course_id)
    // means re-enrolled courses stay 'Completed' instead of becoming 'Enrolled'.
    $unitsStmt = $conn->prepare("
        SELECT COALESCE(SUM(c.credits), 0) AS total_units
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = ?
          AND (
            e.status IN ('Enrolled','Pending')
            OR (e.status = 'Completed'
                AND e.semester = (SELECT semester FROM students WHERE id = ? LIMIT 1))
          )
    ");
    $unitsStmt->bind_param('ii', $sid, $sid);
    $unitsStmt->execute();
    $units = (int)($unitsStmt->get_result()->fetch_assoc()['total_units'] ?? 0);
    $unitsStmt->close();
    if ($units <= 0) $units = 18;

    // Lab fee: based on total number of Laboratory rooms (same for all students)
    $labStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM rooms WHERE room_type = 'Laboratory'");
    $labStmt->execute();
    $lab_count = (int)($labStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $labStmt->close();

    $fc_ad = loadFeeConfig($conn, 'College');
    $r_t   = (float)($fc_ad['tuition_rate_per_unit']['value'] ?? 650);
    $r_m   = (float)($fc_ad['misc_fee']['value']              ?? 6688);
    $r_r   = (float)($fc_ad['reg_fee']['value']               ?? 700);
    $r_lb  = (float)($fc_ad['lab_fee_per_room']['value']      ?? 1900);
    $r_e   = (float)($fc_ad['energy_rate_per_unit']['value']  ?? 63);
    $r_i   = (float)($fc_ad['installment_fee']['value']       ?? 750);
    $std_ad = ['tuition_rate_per_unit','misc_fee','reg_fee','lab_fee_per_room','energy_rate_per_unit','installment_fee'];
    $extra_ad = 0.00;
    foreach ($fc_ad as $fk => $frow) { if (!in_array($fk,$std_ad)) $extra_ad += (float)$frow['value'] * ($frow['is_per_unit'] ? $units : 1); }

    $tuition_fee     = $units * $r_t;
    $miscellaneous   = $r_m;
    $registration    = $r_r;
    $laboratory_fee  = $lab_count * $r_lb;
    $energy_fee      = $units * $r_e;
    $subtotal        = $tuition_fee + $miscellaneous + $registration + $laboratory_fee + $energy_fee + $extra_ad;
    $installment_fee = $has_installment ? $r_i : 0.00;

    // ── Scholar discount logic ───────────────────────────────────────────────
    // Full scholar: discount always equals the full subtotal → total_assessment = ₱0
    // Partial scholar: discount = fixed scholarship_amount (may have remaining balance)
    // Non-scholar: discount = 0
    //
    // Full scholar detection: is_scholar=1 AND either scholarship covers full subtotal
    // OR their existing total_assessment was already ₱0 (full scholarship previously applied)
    $existingTfStmt = $conn->prepare("SELECT total_assessment FROM tuition_fees WHERE student_id=? LIMIT 1");
    $existingTfStmt->bind_param('i', $sid);
    $existingTfStmt->execute();
    $existingTf = $existingTfStmt->get_result()->fetch_assoc();
    $existingTfStmt->close();
    $existingTotal = (float)($existingTf['total_assessment'] ?? -1);

    // Full scholar detection — check active student_scholarships record
    // This is the most reliable signal: accounting sets is_active=1 only for approved full scholars
    $isFullScholar = false;
    if ($isScholar) {
        $ssChkStmt = $conn->prepare("SELECT id FROM student_scholarships WHERE student_id=? AND is_active=1 LIMIT 1");
        $ssChkStmt->bind_param('i', $sid);
        $ssChkStmt->execute();
        $isFullScholar = $ssChkStmt->get_result()->num_rows > 0;
        $ssChkStmt->close();

        // Fallback: if existing tuition_fees shows discount >= subtotal, also full scholar
        if (!$isFullScholar && $existingTotal <= 0 && $existingTotal >= 0) {
            $isFullScholar = true;
        }
    }

    if ($isFullScholar) {
        // Full scholar — cover entire subtotal so balance is always ₱0
        $discount = $subtotal;
        $total    = 0.00;
    } elseif ($isScholar && $scholarshipAmount > 0) {
        // Partial scholar — apply fixed discount
        $discount = $scholarshipAmount;
        $total    = max(0, $subtotal - $discount + $installment_fee);
    } else {
        $discount = 0.00;
        $total    = max(0, $subtotal + $installment_fee);
    }

    // FIX-TUITION-SEMESTER-01 (recalcTuitionAfterAddDrop): stamp the student's
    // current semester on every tuition_fees write so past-semester lookups work.
    $semAdStmt = $conn->prepare("SELECT semester FROM students WHERE id=? LIMIT 1");
    $semAdStmt->bind_param('i', $sid);
    $semAdStmt->execute();
    $semAdRow = $semAdStmt->get_result()->fetch_assoc();
    $semAdStmt->close();
    $semAd = $conn->real_escape_string(trim($semAdRow['semester'] ?? ''));

    $tfChkAd = $conn->query("SELECT id FROM tuition_fees WHERE student_id=$sid AND semester='$semAd' ORDER BY id DESC LIMIT 1");
    $tfRowAd = $tfChkAd ? $tfChkAd->fetch_assoc() : null;
    if ($tfRowAd) {
        $tfIdAd = (int)$tfRowAd['id'];
        $conn->query("UPDATE tuition_fees SET units=$units, tuition_fee=$tuition_fee, miscellaneous_fee=$miscellaneous, registration_fee=$registration, laboratory_fee=$laboratory_fee, energy_fee=$energy_fee, subtotal=$subtotal, discount=$discount, installment_fee=$installment_fee, total_assessment=$total, updated_at=NOW() WHERE id=$tfIdAd");
    } else {
        $stmt = $conn->prepare("INSERT INTO tuition_fees (student_id, units, tuition_fee, miscellaneous_fee, registration_fee, laboratory_fee, energy_fee, subtotal, discount, installment_fee, total_assessment, semester) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iiddddddddds",
                $sid, $units, $tuition_fee, $miscellaneous, $registration,
                $laboratory_fee, $energy_fee, $subtotal, $discount, $installment_fee, $total,
                $semAd
            );
            $stmt->execute();
            $stmt->close();
        }
    }

    // ── For full scholars: sync scholarship_amount in students + student_scholarships ──
    // The SOA reads students.scholarship_amount directly, so it must match
    // tuition_fees.discount (= new subtotal) after every add/drop.
    if ($isFullScholar && $subtotal > 0) {
        // Update students.scholarship_amount to match new subtotal
        $updSchStmt = $conn->prepare("UPDATE students SET scholarship_amount=? WHERE id=?");
        $updSchStmt->bind_param('di', $subtotal, $sid);
        $updSchStmt->execute();
        $updSchStmt->close();

        // Update active student_scholarships record too
        $updSsStmt = $conn->prepare("UPDATE student_scholarships SET scholarship_amount=? WHERE student_id=? AND is_active=1");
        $updSsStmt->bind_param('di', $subtotal, $sid);
        $updSsStmt->execute();
        $updSsStmt->close();

        // Ensure payment_status stays Paid
        $conn->query("UPDATE students SET payment_status='Paid' WHERE id=$sid");
    }

    // ── Update payment_schedules — recalculate installment amounts ───────────
    $ptype = $has_installment ? 'installment' : 'full';

    // Full scholar: total = ₱0 → mark all periods as paid with ₱0 due
    if ($total <= 0 && $isScholar) {
        $conn->query("INSERT INTO payment_schedules
            (student_id, payment_type, total_assessment,
             prelim_due, prelim_paid, prelim_status,
             midterm_due, midterm_paid, midterm_status,
             finals_due, finals_paid, finals_status)
            VALUES ($sid, '$ptype', 0, 0, 0, 'paid', 0, 0, 'paid', 0, 0, 'paid')
            ON DUPLICATE KEY UPDATE
                total_assessment=0,
                prelim_due=0,  prelim_paid=0,  prelim_status='paid',
                midterm_due=0, midterm_paid=0, midterm_status='paid',
                finals_due=0,  finals_paid=0,  finals_status='paid'");

        // Also update students table to reflect Paid status
        $conn->query("UPDATE students SET payment_status='Paid' WHERE id=$sid");
        return;
    }

    if ($has_installment) {
        // ── Read actual paid amounts per period ─────────────────────────────
        $paidStmt = $conn->prepare("SELECT exam_period, COALESCE(SUM(amount),0) AS paid FROM installment_payments WHERE student_id=? AND semester=(SELECT semester FROM students WHERE id=? LIMIT 1) GROUP BY exam_period");
        $paidStmt->bind_param('ii', $sid, $sid);
        $paidStmt->execute();
        $paidRes = $paidStmt->get_result();
        $paidMap = [];
        while ($r = $paidRes->fetch_assoc()) $paidMap[$r['exam_period']] = (float)$r['paid'];
        $paidStmt->close();

        $dp_paid = $paidMap['Downpayment'] ?? 0;
        $p_paid  = $paidMap['Prelim']      ?? 0;
        $m_paid  = $paidMap['Midterm']     ?? 0;
        $f_paid  = $paidMap['Finals']      ?? 0;

        $dp_scheduled = round($total / 4, 2);
        $dp_credit    = $dp_paid > 0 ? $dp_paid : $dp_scheduled;
        $remaining    = max(0, $total - $dp_credit);
        $pd = ceil($remaining / 3 * 100) / 100;
        $md = $pd;
        $fd = round($remaining - $pd * 2, 2);

        $p_status = $p_paid <= 0 ? 'unpaid' : ($p_paid >= $pd ? 'paid' : 'partial');
        $m_status = $m_paid <= 0 ? 'locked' : ($m_paid >= $md ? 'paid' : 'partial');
        $f_status = $f_paid <= 0 ? 'locked' : ($f_paid >= $fd ? 'paid' : 'partial');

        $psInsStmt = $conn->prepare("
            INSERT INTO payment_schedules
                (student_id, payment_type, total_assessment,
                 prelim_due, prelim_paid, prelim_status,
                 midterm_due, midterm_paid, midterm_status,
                 finals_due, finals_paid, finals_status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
                payment_type=VALUES(payment_type), total_assessment=VALUES(total_assessment),
                prelim_due=VALUES(prelim_due), prelim_paid=VALUES(prelim_paid), prelim_status=VALUES(prelim_status),
                midterm_due=VALUES(midterm_due), midterm_paid=VALUES(midterm_paid), midterm_status=VALUES(midterm_status),
                finals_due=VALUES(finals_due), finals_paid=VALUES(finals_paid), finals_status=VALUES(finals_status)
        ");
        $psInsStmt->bind_param('isdddsddsdds',
            $sid, $ptype, $total,
            $pd, $p_paid, $p_status,
            $md, $m_paid, $m_status,
            $fd, $f_paid, $f_status
        );
        $psInsStmt->execute();
        $psInsStmt->close();
    } else {
        $psFullStmt = $conn->prepare("
            INSERT INTO payment_schedules (student_id, payment_type, total_assessment)
            VALUES (?, 'full', ?)
            ON DUPLICATE KEY UPDATE payment_type='full', total_assessment=VALUES(total_assessment)
        ");
        $psFullStmt->bind_param('id', $sid, $total);
        $psFullStmt->execute();
        $psFullStmt->close();
    }
}

// ----------------------------------------------------------------
// ================================================================
// HELPER: Calculate fee impact of adding/dropping one course
// Respects scholar discount — full scholars always net ₱0.
// ================================================================
function _calcAddDropFeeImpact(mysqli $conn, int $sid, int $cid, string $requestType): array {
    // Current total assessment + discount info
    $tfR = $conn->prepare("SELECT total_assessment, units, discount, subtotal FROM tuition_fees WHERE student_id=? LIMIT 1");
    $tfR->bind_param('i', $sid);
    $tfR->execute();
    $tfRow = $tfR->get_result()->fetch_assoc();
    $tfR->close();
    $currentTotal    = (float)($tfRow['total_assessment'] ?? 0);
    $currentUnits    = (int)($tfRow['units']              ?? 0);
    $currentDiscount = (float)($tfRow['discount']         ?? 0);
    $currentSubtotal = (float)($tfRow['subtotal']         ?? 0);

    // Scholar info
    $schR = $conn->prepare("SELECT is_scholar, scholarship_amount FROM students WHERE id=? LIMIT 1");
    $schR->bind_param('i', $sid);
    $schR->execute();
    $schRow = $schR->get_result()->fetch_assoc();
    $schR->close();
    $isScholar         = (int)($schRow['is_scholar']         ?? 0);
    $scholarshipAmount = (float)($schRow['scholarship_amount'] ?? 0);

    // Full scholar detection — use the most reliable signals in priority order:
    // 1. tuition_fees.discount >= tuition_fees.subtotal (discount covers full cost)
    // 2. student_scholarships has an active record (means accounting granted full scholarship)
    // 3. students.scholarship_amount >= current subtotal
    $isFullScholar = false;
    if ($isScholar) {
        // Check active scholarship record
        $ssR = $conn->prepare("SELECT id FROM student_scholarships WHERE student_id=? AND is_active=1 LIMIT 1");
        $ssR->bind_param('i', $sid);
        $ssR->execute();
        $hasActiveScholarship = $ssR->get_result()->num_rows > 0;
        $ssR->close();

        if ($hasActiveScholarship) {
            // Has an active scholarship — treat as full scholar
            // (accounting would not have set is_active=1 unless it's a full scholarship)
            $isFullScholar = true;
        } elseif ($currentSubtotal > 0 && $currentDiscount >= $currentSubtotal) {
            // Discount in tuition_fees already covers full subtotal
            $isFullScholar = true;
        }
    }

    // Course info
    $cR = $conn->prepare("SELECT credits, is_lab FROM courses WHERE id=? LIMIT 1");
    $cR->bind_param('i', $cid);
    $cR->execute();
    $cRow = $cR->get_result()->fetch_assoc();
    $cR->close();
    $courseCredits = (int)($cRow['credits'] ?? 0);
    $isLab         = (int)($cRow['is_lab']  ?? 0);

    // Fee rates from fee_config
    $fcR = $conn->query("SELECT fee_key, value FROM fee_config WHERE category='College' AND is_active=1");
    $fc  = [];
    if ($fcR) while ($fr = $fcR->fetch_assoc()) $fc[$fr['fee_key']] = (float)$fr['value'];
    $rTuition = $fc['tuition_rate_per_unit'] ?? 650;
    $rEnergy  = $fc['energy_rate_per_unit']  ?? 63;
    $rLab     = $fc['lab_fee_per_room']       ?? 1900;

    $sign          = ($requestType === 'Drop') ? -1 : 1;
    $tuitionImpact = round($sign * $courseCredits * $rTuition, 2);
    // FIX ADD-DROP-02: Lab fee only added for Add (facility cost), never deducted on Drop
    $labImpact     = ($requestType === 'Add' && $isLab) ? round($rLab, 2) : 0.00;
    $energyImpact  = round($sign * $courseCredits * $rEnergy, 2);
    $totalImpact   = round($tuitionImpact + $labImpact + $energyImpact, 2);

    // Full scholar: ₱0 always — no fee impact at all
    // Partial scholar: compute new subtotal, apply fixed discount
    if ($isFullScholar) {
        $newTotal    = 0.00;
        $totalImpact = 0.00; // No fee change for full scholars
        $tuitionImpact = 0.00;
        $labImpact     = 0.00;
        $energyImpact  = 0.00;
    } elseif ($isScholar && $scholarshipAmount > 0) {
        // Partial scholar — compute new subtotal after add/drop, apply same discount
        $newSubtotal = max(0, $currentSubtotal + ($tuitionImpact + $labImpact + $energyImpact));
        $newTotal    = round(max(0, $newSubtotal - $scholarshipAmount), 2);
    } else {
        $newTotal = round(max(0, $currentTotal + $totalImpact), 2);
    }

    return [
        'currentTotal'    => $currentTotal,
        'currentUnits'    => $currentUnits,
        'courseUnits'     => $courseCredits,
        'tuitionImpact'   => $tuitionImpact,
        'labImpact'       => $labImpact,
        'energyImpact'    => $energyImpact,
        'totalImpact'     => $totalImpact,
        'newTotal'        => $newTotal,
        'isFullScholar'   => $isFullScholar,
        'scholarDiscount' => $scholarshipAmount,
    ];
}

// ================================================================
// ACCOUNTING: Get all pending add/drop requests with fee preview
// GET ?action=get_pending_add_drop_for_accounting[&status=Pending]
// ================================================================
function getPendingAddDropForAccounting(mysqli $conn): void {
    ensureAddDropTable($conn);
    $status = trim($_GET['status'] ?? 'Pending');
    if (!in_array($status, ['Pending','Approved','Rejected','All'], true)) $status = 'Pending';
    $where  = $status === 'All' ? '' : "AND r.accounting_status = '$status'";

    // BUG-ACCTG-02 FIX: semester was not included in the tf subquery SELECT list,
    // so AND tf.semester = s.semester in the ON clause always evaluated to
    // NULL = s.semester → false → LEFT JOIN returned all-NULLs for tf.* on every row.
    // Fix: include semester in the subquery so the ON clause can actually filter it.
    $res = $conn->query("
        SELECT r.*,
               c.code, c.name AS course_name, c.credits, c.is_lab,
               s.first_name, s.last_name, s.student_number,
               s.program, s.year_level, s.semester, s.student_category,
               tf.total_assessment AS current_total, tf.units AS current_units
        FROM add_drop_requests r
        JOIN courses  c ON r.course_id  = c.id
        JOIN students s ON r.student_id = s.id
        LEFT JOIN (
            SELECT student_id, semester, total_assessment, subtotal,
                   units, discount, installment_fee, tuition_fee
            FROM tuition_fees
            WHERE id IN (SELECT MAX(id) FROM tuition_fees GROUP BY student_id)
        ) tf ON tf.student_id = s.id
             AND tf.semester  = s.semester
        WHERE 1=1 $where
        ORDER BY r.created_at DESC
    ");

    $rows = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            // Always compute live from _calcAddDropFeeImpact for accurate breakdown
            $calc = _calcAddDropFeeImpact($conn, (int)$r['student_id'], (int)$r['course_id'], $r['request_type']);

            // If already reviewed by accounting, use the stored totals but keep live breakdown
            $storedImpact = (float)$r['fee_impact'];
            $storedNew    = (float)$r['new_total_assessment'];

            $r['fee_preview'] = [
                'currentTotal'  => $calc['currentTotal'],
                'currentUnits'  => $calc['currentUnits'],
                'courseUnits'   => $calc['courseUnits'],
                'tuitionImpact' => $calc['tuitionImpact'],
                'labImpact'     => $calc['labImpact'],
                'energyImpact'  => $calc['energyImpact'],
                'totalImpact'   => $storedImpact ?: $calc['totalImpact'],
                'newTotal'      => $storedNew    ?: $calc['newTotal'],
            ];
            unset($r['current_total'], $r['current_units'], $r['is_lab']);
            $rows[] = $r;
        }
    }
    while (ob_get_level() > 0) { ob_end_clean(); } $_cO = $_SERVER['HTTP_ORIGIN'] ?? '*'; header("Access-Control-Allow-Origin: $_cO", true); header('Access-Control-Allow-Credentials: true', true); header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS', true); header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token', true); header('Access-Control-Expose-Headers: X-New-Token', true); header('Content-Type: application/json', true);
    echo json_encode(['success' => true, 'requests' => $rows, 'count' => count($rows)]);
}

// ================================================================
// ACCOUNTING: Approve or reject an add/drop request (fee review)
// POST ?action=accounting_approve_add_drop
// Body: { request_id, action:'Approved'|'Rejected', notes? }
//
// This does NOT enroll/drop the student — that is still Registrar's job.
// It only marks accounting_status and saves the computed fee impact
// so the Registrar can see the accounting-approved requests.
// ================================================================
function accountingApproveAddDrop(mysqli $conn, array $data): void {
    ensureAddDropTable($conn);

    $authUser = $GLOBALS['authUser'] ?? null;
    if (!$authUser || !in_array($authUser['role'] ?? '', ['accounting','admin'], true)) {
        while (ob_get_level() > 0) { ob_end_clean(); } $_cO = $_SERVER['HTTP_ORIGIN'] ?? '*'; header("Access-Control-Allow-Origin: $_cO", true); header('Access-Control-Allow-Credentials: true', true); header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS', true); header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token', true); header('Access-Control-Expose-Headers: X-New-Token', true); header('Content-Type: application/json', true);
        echo json_encode(['success'=>false,'message'=>'Access denied. Only Accounting or Admin can review add/drop fee impact.']);
        return;
    }

    $rid    = (int)($data['request_id'] ?? 0);
    $action = trim($data['action']      ?? '');
    $notes  = trim($data['notes']       ?? '');

    if (!$rid || !in_array($action, ['Approved','Rejected'], true)) {
        while (ob_get_level() > 0) { ob_end_clean(); } $_cO = $_SERVER['HTTP_ORIGIN'] ?? '*'; header("Access-Control-Allow-Origin: $_cO", true); header('Access-Control-Allow-Credentials: true', true); header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS', true); header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token', true); header('Access-Control-Expose-Headers: X-New-Token', true); header('Content-Type: application/json', true);
        echo json_encode(['success'=>false,'message'=>'request_id and action (Approved|Rejected) required']);
        return;
    }

    // Fetch the request
    $reqSt = $conn->prepare("SELECT * FROM add_drop_requests WHERE id=? LIMIT 1");
    $reqSt->bind_param('i', $rid);
    $reqSt->execute();
    $req = $reqSt->get_result()->fetch_assoc();
    $reqSt->close();

    if (!$req) {
        while (ob_get_level() > 0) { ob_end_clean(); } $_cO = $_SERVER['HTTP_ORIGIN'] ?? '*'; header("Access-Control-Allow-Origin: $_cO", true); header('Access-Control-Allow-Credentials: true', true); header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS', true); header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token', true); header('Access-Control-Expose-Headers: X-New-Token', true); header('Content-Type: application/json', true);
        echo json_encode(['success'=>false,'message'=>'Request not found']);
        return;
    }

    $sid = (int)$req['student_id'];
    $cid = (int)$req['course_id'];

    // Calculate fee impact (respects scholarship)
    $calc      = _calcAddDropFeeImpact($conn, $sid, $cid, $req['request_type']);
    $feeImpact = $calc['totalImpact'];
    $newTotal  = $calc['newTotal'];
    $isFullScholar = $calc['isFullScholar'] ?? false;

    $reviewerId = (int)($authUser['user_id'] ?? 0);

    // Save accounting review result + fee impact onto the request row
    $upd = $conn->prepare("UPDATE add_drop_requests
        SET accounting_status=?, accounting_reviewed_by=?, accounting_reviewed_at=NOW(),
            accounting_notes=?, fee_impact=?, new_total_assessment=?
        WHERE id=?");
    $upd->bind_param('sisddi', $action, $reviewerId, $notes, $feeImpact, $newTotal, $rid);
    $upd->execute();
    $upd->close();

    // If approved → write the Subject Fee Log entry immediately so Accounting can
    // see the impact in the fee log right after they approve, without waiting for
    // the Registrar's final step.
    //
    // BUG-ADDDROP-01 FIX: Previously _logSubjectFeeImpact() was intentionally skipped
    // here to avoid double-logging. However this left the Subject Fee Log empty until
    // the Registrar also approved — which broke the Accounting fee log view entirely.
    //
    // Solution: write the log here with reason prefixed "Accounting Approved:" so it is
    // distinct from the Registrar's later entry (prefixed "Registrar Approved:").
    // processAddDropRequest() already has its own _logSubjectFeeImpact() call that runs
    // when the enrollment row is actually created/dropped — those two entries together
    // give a complete audit trail (Accounting pre-approval + Registrar execution).
    if ($action === 'Approved') {
        $accLogReason = "Accounting Approved: Add/Drop request #$rid — awaiting Registrar final approval";
        try { _logSubjectFeeImpact($conn, $sid, $cid, $req['request_type'], $accLogReason); }
        catch (Throwable $e) { error_log('[add_drop acc_fee_log] ' . $e->getMessage()); }

        // Also stamp accounting_forwarded_to_registrar_at so the Registrar portal
        // can highlight newly accounting-cleared requests.
        $conn->query("ALTER TABLE add_drop_requests ADD COLUMN IF NOT EXISTS accounting_forwarded_at DATETIME DEFAULT NULL");
        $fwdUpd = $conn->prepare("UPDATE add_drop_requests SET accounting_forwarded_at=NOW() WHERE id=?");
        $fwdUpd->bind_param('i', $rid);
        $fwdUpd->execute();
        $fwdUpd->close();
    }

    logAuditShared($conn, $authUser, 'ACCOUNTING_REVIEW_ADD_DROP', 'add_drop_requests', $rid,
        "Add/Drop request #$rid {$action} by Accounting. Fee impact: ₱" . number_format($feeImpact, 2) . ". New total: ₱" . number_format($newTotal, 2));

    while (ob_get_level() > 0) { ob_end_clean(); } $_cO = $_SERVER['HTTP_ORIGIN'] ?? '*'; header("Access-Control-Allow-Origin: $_cO", true); header('Access-Control-Allow-Credentials: true', true); header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS', true); header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token', true); header('Access-Control-Expose-Headers: X-New-Token', true); header('Content-Type: application/json', true);
    echo json_encode([
        'success'        => true,
        'message'        => "Request {$action} by Accounting. " . ($action === 'Approved'
            ? ($isFullScholar
                ? "Student is a Full Scholar — no additional charges. New total remains ₱0.00."
                : "Fee impact: ₱" . number_format(abs($feeImpact), 2) . ". New total after approval: ₱" . number_format($newTotal, 2))
            : "Request returned to student."),
        'feeImpact'      => $feeImpact,
        'newTotal'       => $newTotal,
        'isFullScholar'  => $isFullScholar,
    ]);
}

// ADD/DROP WINDOW MANAGEMENT
// ----------------------------------------------------------------
function ensureAddDropWindowTable($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS add_drop_window (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        start_date DATETIME NOT NULL,
        end_date   DATETIME NOT NULL,
        label      VARCHAR(100) DEFAULT NULL,
        is_active  TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// GET ?action=get_add_drop_window
function getAddDropWindow($conn) {
    ensureAddDropWindowTable($conn);

    // FIX: Do NOT auto-deactivate with UPDATE here — MySQL NOW() may be UTC
    // while stored datetimes are Philippine time, causing valid windows to be
    // incorrectly deactivated. Fetch latest window and use MySQL NOW() for the
    // open/closed comparison so both sides use the same clock.
    $res    = $conn->query("SELECT * FROM add_drop_window ORDER BY id DESC LIMIT 1");
    $window = $res ? $res->fetch_assoc() : null;
    $isOpen = false;
    if ($window) {
        $s   = $conn->real_escape_string($window['start_date']);
        $e   = $conn->real_escape_string($window['end_date']);
        $chk = $conn->query("SELECT (NOW() >= '$s' AND NOW() <= '$e') AS open");
        $isOpen = $chk ? (bool)$chk->fetch_assoc()['open'] : false;
    }
    echo json_encode([
        'success'  => true,
        'window'   => $window,
        'is_open'  => $isOpen,
        'now'      => date('Y-m-d H:i:s'),
    ]);
}

// POST ?action=set_add_drop_window
// Body: { start_date, end_date, label }
function setAddDropWindow($conn, $data) {
    ensureAddDropWindowTable($conn);
    $start = trim($data['start_date'] ?? '');
    $end   = trim($data['end_date']   ?? '');
    $label = trim($data['label']      ?? '');

    if (!$start || !$end) {
        echo json_encode(['success' => false, 'message' => 'start_date and end_date are required']);
        return;
    }
    if (strtotime($end) <= strtotime($start)) {
        echo json_encode(['success' => false, 'message' => 'end_date must be after start_date']);
        return;
    }

    // Deactivate all previous windows
    $conn->query("UPDATE add_drop_window SET is_active=0");

    $ins = $conn->prepare("INSERT INTO add_drop_window (start_date, end_date, label, is_active) VALUES (?,?,?,1)");
    $ins->bind_param('sss', $start, $end, $label);
    $ins->execute();
    if ($ins->affected_rows > 0) {
        $ins->close();
        echo json_encode(['success' => true, 'message' => 'Add/Drop window updated successfully']);
    } else {
        $ins->close();
        echo json_encode(['success' => false, 'message' => 'Failed to save window']);
    }
}
// ─────────────────────────────────────────────────────────────
// GET CURRICULUM
// GET ?action=get_curriculum&student_id=XX
//
// Returns the full program curriculum for the student,
// grouped by year level → semester, with per-course status
// (completed / enrolled / failed / not_taken) based on
// their actual enrollment + grade records.
// ─────────────────────────────────────────────────────────────
function getCurriculum($conn) {
    $student_id = (int)($_GET['student_id'] ?? 0);
    if (!$student_id) {
        $user_id = (int)($_GET['user_id'] ?? 0);
        if (!$user_id && isset($GLOBALS['authUser']['user_id'])) {
            $user_id = (int)$GLOBALS['authUser']['user_id'];
        }
        if ($user_id > 0) {
            $st = $conn->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
            if ($st) { $st->bind_param('i', $user_id); $st->execute(); $r = $st->get_result()->fetch_assoc(); $st->close(); $student_id = $r ? (int)$r['id'] : 0; }
        }
    }
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'student_id required']); return;
    }

    // Get student info — including semester for AY computation
    $stRes = $conn->query("SELECT program, year_level, semester FROM students WHERE id = $student_id LIMIT 1");
    $stRow = $stRes ? $stRes->fetch_assoc() : null;
    if (!$stRow) {
        echo json_encode(['success' => false, 'message' => 'Student not found']); return;
    }
    $programName      = trim($stRow['program']    ?? '');
    $currentYearLevel = trim($stRow['year_level'] ?? '1st Year');
    $currentSemRaw    = trim($stRow['semester']   ?? '');

    // Extract current AY from semester field (e.g. "1st Semester, AY 2025-2026" → 2025)
    $currentAYStart = (int)date('Y');
    if (preg_match('/AY\s*(\d{4})-(\d{4})/i', $currentSemRaw, $ayM)) {
        $currentAYStart = (int)$ayM[1];
    }
    // Map year level name → number (1st=1, 2nd=2, etc.)
    $ylMap = ['1st Year'=>1,'2nd Year'=>2,'3rd Year'=>3,'4th Year'=>4,'5th Year'=>5];
    $currentYLNum = $ylMap[$currentYearLevel] ?? 1;

    // Resolve program to id (support both name and code)
    $pn  = $programName;
    $pRes = $conn->query("SELECT id FROM programs WHERE name = '$pn' OR code = '$pn' LIMIT 1");
    $pRow = $pRes ? $pRes->fetch_assoc() : null;
    $programId = $pRow ? (int)$pRow['id'] : 0;

    // Fetch all program courses via program_courses junction OR courses.program column
    $courses = [];
    if ($programId > 0) {
        $cRes = $conn->query("
            SELECT c.id, c.code, c.name, c.credits,
                   COALESCE(c.lec_units, c.credits, 0) AS lec_units,
                   COALESCE(c.lab_units, 0) AS lab_units,
                   c.semester, c.year_level,
                   COALESCE(c.description, '') AS description
            FROM program_courses pc
            JOIN courses c ON pc.course_id = c.id
            WHERE pc.program_id = $programId
            ORDER BY c.year_level, c.semester, c.code
        ");
        if ($cRes) {
            while ($row = $cRes->fetch_assoc()) $courses[] = $row;
        }
    }

    // Fallback: courses.program column
    if (empty($courses)) {
        $cRes2 = $conn->query("
            SELECT id, code, name, credits,
                   COALESCE(lec_units, credits, 0) AS lec_units,
                   COALESCE(lab_units, 0) AS lab_units,
                   semester, year_level,
                   COALESCE(description, '') AS description
            FROM courses
            WHERE program = '$pn' OR program = (
                SELECT code FROM programs WHERE name = '$pn' LIMIT 1
            )
            ORDER BY year_level, semester, code
        ");
        if ($cRes2) {
            while ($row = $cRes2->fetch_assoc()) $courses[] = $row;
        }
    }

    if (empty($courses)) {
        echo json_encode(['success' => true, 'program' => $programName, 'yearGroups' => []]); return;
    }

    // Fetch student's enrollment + grade records for all courses
    // FIX CURRICULUM-01: Join student_grades to get Final grade so 'completed'
    // status and Overall Progress work correctly.
    $enrollMap = [];
    $eRes = $conn->query("
        SELECT e.course_id, e.status,
               MAX(CASE WHEN sg.term='Final' THEN sg.grade END) AS final_grade
        FROM enrollments e
        LEFT JOIN student_grades sg ON sg.enrollment_id = e.id
        WHERE e.student_id = $student_id
        GROUP BY e.id, e.course_id, e.status
    ");
    if ($eRes) {
        while ($r = $eRes->fetch_assoc()) {
            $cid = (int)$r['course_id'];
            // Prefer 'Enrolled' over 'Pending' if multiple records exist
            if (!isset($enrollMap[$cid]) || $r['status'] === 'Enrolled') {
                $enrollMap[$cid] = $r;
            }
        }
    }

    // Fetch prerequisite relationships for all courses in this program
    // Key: course_id → ['prereq_id'=>int, 'prereq_code'=>string]
    $prereqMap = [];
    $prereqTableCheck = $conn->query("SHOW TABLES LIKE 'course_prerequisites'");
    if ($prereqTableCheck && $prereqTableCheck->num_rows > 0) {
        $prRes = $conn->query("
            SELECT cp.course_id, cp.prerequisite_id,
                   COALESCE(c.code,'') AS prereq_code,
                   COALESCE(c.name,'') AS prereq_name
            FROM course_prerequisites cp
            LEFT JOIN courses c ON c.id = cp.prerequisite_id
        ");
        if ($prRes) {
            while ($pr = $prRes->fetch_assoc()) {
                $prereqMap[(int)$pr['course_id']] = [
                    'prereq_id'   => (int)$pr['prerequisite_id'],
                    'prereq_code' => cleanCode($pr['prereq_code']),
                    'prereq_name' => $pr['prereq_name'],
                ];
            }
        }
    }

    // Build course list with status
    $yearData = [];
    foreach ($courses as $c) {
        $cid  = (int)$c['id'];
        $yrRaw = trim($c['year_level'] ?? '');
        // Normalize year_level: "Year 1" → "1st Year", "Year 2" → "2nd Year", etc.
        $ylNormMap = [
            'Year 1'=>'1st Year','Year 2'=>'2nd Year','Year 3'=>'3rd Year','Year 4'=>'4th Year','Year 5'=>'4th Year',
            '1'=>'1st Year','2'=>'2nd Year','3'=>'3rd Year','4'=>'4th Year',
        ];
        $yr = $ylNormMap[$yrRaw] ?? ($yrRaw !== '' ? $yrRaw : 'Other');
        $semRaw = trim($c['semester'] ?? 'Unknown Semester');
        // Strip AY suffix from course semester — we compute AY from year level
        if (preg_match('/^(1st Semester|2nd Semester|Summer|Midyear)/i', $semRaw, $sm)) {
            $semTerm = $sm[1];
        } else {
            $semTerm = $semRaw;
        }
        // Compute correct AY for this year level:
        //   AY offset = (this year level number) - (student's current year level number)
        $thisYLNum = $ylMap[$yr] ?? 1;
        $ayOffset  = $thisYLNum - $currentYLNum;
        $ayStart   = $currentAYStart + $ayOffset;
        $ayEnd     = $ayStart + 1;
        $sem       = "$semTerm, AY $ayStart-$ayEnd";
        $enr  = $enrollMap[$cid] ?? null;

        // Determine status
        // FIX CURRICULUM-01: Use actual final_grade from student_grades.
        $status = 'not_taken';
        $grade  = null;
        if ($enr) {
            $finalGrade = $enr['final_grade'] !== null ? (float)$enr['final_grade'] : null;
            if ($finalGrade !== null) {
                $grade  = $finalGrade;
                $status = $finalGrade <= 3.0 ? 'completed' : 'failed';
            } elseif ($enr['status'] === 'Enrolled' || $enr['status'] === 'Pending') {
                $status = 'enrolled';
            }
        }

        $yearData[$yr][$sem][] = [
            'courseId'    => $cid,
            'code'           => cleanCode($c['code']),
            'name'        => $c['name'],
            'credits'     => (int)$c['credits'],
            'lecUnits'    => (int)$c['lec_units'],
            'labUnits'    => (int)$c['lab_units'],
            'semester'    => $sem,
            'yearLevel'   => $yr,
            'description' => $c['description'],
            'status'         => $status,
            'grade'          => $grade,
            'prerequisiteId'   => $prereqMap[$cid]['prereq_id']   ?? null,
            'prerequisiteCode' => $prereqMap[$cid]['prereq_code'] ?? null,
            'prerequisiteName' => $prereqMap[$cid]['prereq_name'] ?? null,
        ];
    }

    // Sort years in natural order
    $yearOrder = ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'];
    uksort($yearData, function($a, $b) use ($yearOrder) {
        $ia = array_search($a, $yearOrder);
        $ib = array_search($b, $yearOrder);
        $ia = $ia === false ? 99 : $ia;
        $ib = $ib === false ? 99 : $ib;
        return $ia - $ib;
    });

    $semOrder = ['1st Semester', '2nd Semester', 'Summer', 'Midyear'];

    // Build response structure
    $yearGroups = [];
    foreach ($yearData as $yr => $sems) {
        uksort($sems, function($a, $b) use ($semOrder) {
            $ia = 99; $ib = 99;
            foreach ($semOrder as $i => $s) {
                if (stripos($a, $s) !== false) $ia = $i;
                if (stripos($b, $s) !== false) $ib = $i;
            }
            return $ia - $ib;
        });

        $yearTotalUnits = 0; $yearCompletedUnits = 0;
        $semGroups = [];
        foreach ($sems as $sem => $cs) {
            $semTotal = array_sum(array_column($cs, 'credits'));
            $semCompleted = array_sum(array_map(
                fn($c) => $c['status'] === 'completed' ? $c['credits'] : 0, $cs
            ));
            $yearTotalUnits     += $semTotal;
            $yearCompletedUnits += $semCompleted;
            $semGroups[] = [
                'semester'       => $sem,
                'courses'        => array_values($cs),
                'totalUnits'     => $semTotal,
                'completedUnits' => $semCompleted,
            ];
        }
        $yearGroups[] = [
            'yearLevel'      => $yr,
            'semesters'      => $semGroups,
            'totalUnits'     => $yearTotalUnits,
            'completedUnits' => $yearCompletedUnits,
        ];
    }

    // Compute overall totals across all year groups
    $grandTotal     = array_sum(array_column($yearGroups, 'totalUnits'));
    $grandCompleted = array_sum(array_column($yearGroups, 'completedUnits'));

    echo json_encode([
        'success'         => true,
        'program'         => $programName,
        'yearGroups'      => $yearGroups,
        'totalUnits'      => $grandTotal,
        'completedUnits'  => $grandCompleted,
        'overallProgress' => $grandTotal > 0 ? round($grandCompleted / $grandTotal * 100, 1) : 0,
    ]);
}
// ════════════════════════════════════════════════════════════════
//  SOA SNAPSHOT — saveSoaSnapshot() lives in soa_helper.php so
//  Accounting.php can include it without triggering this file's
//  auth check or HTTP router. Both files require_once soa_helper.php.
//
//  getSoaSnapshot() remains here as the HTTP endpoint handler.
//  saveSoaSnapshot() is called:
//   • By verifyPayment() in Accounting.php on Downpayment or Full
//     payment approval (first time money is confirmed for a semester)
//   • By reEnroll() BEFORE deleting tuition_fees, so the current
//     semester's SOA is safely preserved before the slate is wiped.
//
//  GET enrollment.php?action=get_soa_snapshot&student_id=X
//  GET enrollment.php?action=get_soa_snapshot&student_id=X&semester=1st+Semester,+AY+2025-2026
// ════════════════════════════════════════════════════════════════


/**
 * GET endpoint: return a frozen SOA snapshot for a student.
 *
 * Query params:
 *   student_id  (required) — students.id  OR resolved from user_id
 *   semester    (optional) — if omitted, returns the most recent snapshot
 *
 * Returns:
 *   { success, snapshot: { ... }, semesters: ['1st Semester, AY …', …] }
 */
function getSoaSnapshot(mysqli $conn): void {

    // Resolve student_id
    $student_id = (int)($_GET['student_id'] ?? 0);
    if (!$student_id) {
        $uid = (int)($_GET['user_id'] ?? 0);
        if ($uid) {
            $rs = $conn->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
            $rs->bind_param('i', $uid);
            $rs->execute();
            $r = $rs->get_result()->fetch_assoc();
            $rs->close();
            $student_id = $r ? (int)$r['id'] : 0;
        }
    }
    if (!$student_id) {
        jsonOut(['success' => false, 'message' => 'student_id required'], 400);
    }

    // Auto-create table (defensive — may not exist on first request)
    $conn->query("CREATE TABLE IF NOT EXISTS soa_snapshots (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        student_id       INT          NOT NULL,
        semester         VARCHAR(100) NOT NULL,
        units            INT          NOT NULL DEFAULT 0,
        tuition_fee      DECIMAL(10,2) NOT NULL DEFAULT 0,
        miscellaneous_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
        registration_fee  DECIMAL(10,2) NOT NULL DEFAULT 0,
        laboratory_fee    DECIMAL(10,2) NOT NULL DEFAULT 0,
        energy_fee        DECIMAL(10,2) NOT NULL DEFAULT 0,
        subtotal         DECIMAL(10,2) NOT NULL DEFAULT 0,
        discount         DECIMAL(10,2) NOT NULL DEFAULT 0,
        installment_fee  DECIMAL(10,2) NOT NULL DEFAULT 0,
        total_assessment DECIMAL(10,2) NOT NULL DEFAULT 0,
        total_paid       DECIMAL(10,2) NOT NULL DEFAULT 0,
        balance          DECIMAL(10,2) NOT NULL DEFAULT 0,
        payment_plan     VARCHAR(20)  NOT NULL DEFAULT 'full',
        payment_status   VARCHAR(30)  NOT NULL DEFAULT 'Pending',
        subjects_json    MEDIUMTEXT   DEFAULT NULL,
        payments_json    MEDIUMTEXT   DEFAULT NULL,
        snapshotted_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_student_semester (student_id, semester),
        INDEX idx_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // List of all available semesters for this student
    $semListRes = $conn->prepare(
        "SELECT semester, snapshotted_at FROM soa_snapshots
         WHERE student_id = ?
         ORDER BY snapshotted_at DESC"
    );
    $semListRes->bind_param('i', $student_id);
    $semListRes->execute();
    $semRows = $semListRes->get_result()->fetch_all(MYSQLI_ASSOC);
    $semListRes->close();
    $availableSemesters = array_column($semRows, 'semester');

    // Determine which semester to fetch
    $semester = trim($_GET['semester'] ?? '');
    if ($semester === '' && !empty($availableSemesters)) {
        $semester = $availableSemesters[0]; // most recent
    }

    if ($semester === '') {
        // No snapshots exist yet — try to seed one from current tuition_fees
        $seeded = saveSoaSnapshot($conn, $student_id);
        if (!$seeded) {
            // BUG-SOA-FREE-02 FIX: For free SHS/TVET students and students awaiting
            // fee computation, saveSoaSnapshot() may still return false (e.g. the
            // ₱0 snapshot INSERT failed due to a DB error). Return a structured
            // "pending" response so Angular shows something useful rather than a
            // blank SOA page or unhandled null.
            $stInfoR = $conn->prepare("SELECT semester, student_category, student_type, first_name, last_name FROM students WHERE id = ? LIMIT 1");
            $stInfoR->bind_param('i', $student_id);
            $stInfoR->execute();
            $stInfo = $stInfoR->get_result()->fetch_assoc();
            $stInfoR->close();
            $cat   = strtoupper(trim($stInfo['student_category'] ?? ''));
            $stype = trim($stInfo['student_type'] ?? '');
            $isFree = ($cat === 'SHS' && $stype !== 'Transferee')
                   || ($cat === 'TVET' && $stype !== 'Transferee');
            jsonOut([
                'success'            => true,
                'snapshot'           => null,
                'availableSemesters' => [],
                'isFree'             => $isFree,
                'message'            => $isFree
                    ? 'This student is enrolled under a government-funded program (no tuition fees).'
                    : 'No SOA snapshot found yet. Fees will appear once Accounting has processed your enrollment.',
            ]);
        }
        // Re-read after seed
        $semR2 = $conn->prepare("SELECT semester FROM students WHERE id = ? LIMIT 1");
        $semR2->bind_param('i', $student_id);
        $semR2->execute();
        $semester = trim($semR2->get_result()->fetch_assoc()['semester'] ?? '');
        $semR2->close();
        $availableSemesters = [$semester];
    }

    // Fetch the snapshot row
    $snapStmt = $conn->prepare(
        "SELECT * FROM soa_snapshots WHERE student_id = ? AND semester = ? LIMIT 1"
    );
    $snapStmt->bind_param('is', $student_id, $semester);
    $snapStmt->execute();
    $snap = $snapStmt->get_result()->fetch_assoc();
    $snapStmt->close();

    // FIX SOA-STALE-01 + SOA-UNITS-01:
    // Re-seed the snapshot from live data ONLY when the student is viewing their
    // CURRENT semester. This keeps payment records up to date (Prelim/Midterm/Finals
    // payments recorded after enrollment appear immediately) while preventing past-semester
    // snapshots from being overwritten with the current tuition_fees row.
    //
    // Root cause of SOA-UNITS-01: tuition_fees has no semester column — it always
    // reflects the present enrollment. When Registrar adds a subject the unit count
    // rises. If we unconditionally re-seed for any semester, the past snapshot absorbs
    // the new (wrong) unit total, inflating historical SOA balances and units.
    //
    // Fix: compare the requested $semester to students.semester (the live term).
    // Only call saveSoaSnapshot() — which reads tuition_fees — for the current term.
    // Past-semester snapshots are frozen at re-enroll time (reEnroll calls saveSoaSnapshot
    // before deleting tuition_fees) and should never be touched again.
    $curSemChk = $conn->prepare("SELECT semester FROM students WHERE id = ? LIMIT 1");
    $curSemChk->bind_param('i', $student_id);
    $curSemChk->execute();
    $curSemRow = $curSemChk->get_result()->fetch_assoc();
    $curSemChk->close();
    $isCurrentSem = (trim($curSemRow['semester'] ?? '') === $semester);

    if ($isCurrentSem) {
        saveSoaSnapshot($conn, $student_id, $semester);
        // Re-fetch after refresh so $snap has the latest payment data
        $snapRefresh = $conn->prepare(
            "SELECT * FROM soa_snapshots WHERE student_id = ? AND semester = ? LIMIT 1"
        );
        $snapRefresh->bind_param('is', $student_id, $semester);
        $snapRefresh->execute();
        $snapFresh = $snapRefresh->get_result()->fetch_assoc();
        $snapRefresh->close();
        if ($snapFresh) $snap = $snapFresh;
    }

    if (!$snap) {
        // Try seeding from live tuition_fees (current semester only)
        $curSemR = $conn->prepare("SELECT semester FROM students WHERE id = ? LIMIT 1");
        $curSemR->bind_param('i', $student_id);
        $curSemR->execute();
        $curSem = trim($curSemR->get_result()->fetch_assoc()['semester'] ?? '');
        $curSemR->close();

        if ($curSem === $semester) {
            saveSoaSnapshot($conn, $student_id, $semester);
            $snapStmt2 = $conn->prepare(
                "SELECT * FROM soa_snapshots WHERE student_id = ? AND semester = ? LIMIT 1"
            );
            $snapStmt2->bind_param('is', $student_id, $semester);
            $snapStmt2->execute();
            $snap = $snapStmt2->get_result()->fetch_assoc();
            $snapStmt2->close();
        }

        if (!$snap) {
            jsonOut([
                'success'            => true,
                'snapshot'           => null,
                'availableSemesters' => $availableSemesters,
                'message'            => "No SOA data found for semester: $semester",
            ]);
        }
    }

    // Decode JSON fields
    $snap['subjects']  = json_decode($snap['subjects_json']  ?? '[]', true) ?: [];
    $snap['payments']  = json_decode($snap['payments_json']  ?? '[]', true) ?: [];
    unset($snap['subjects_json'], $snap['payments_json']);

    // Cast numerics
    foreach (['units','tuition_fee','miscellaneous_fee','registration_fee','laboratory_fee',
              'energy_fee','subtotal','discount','installment_fee','total_assessment',
              'total_paid','balance'] as $col) {
        $snap[$col] = (float)$snap[$col];
    }
    $snap['units'] = (int)$snap['units'];

    jsonOut([
        'success'            => true,
        'snapshot'           => $snap,
        'availableSemesters' => $availableSemesters,
    ]);
}

// ════════════════════════════════════════════════════════════════
//  ENROLLMENT PERIOD — Admin sets open/close dates
// ════════════════════════════════════════════════════════════════

/** GET ?action=get_enrollment_period — public, no auth needed */
function getEnrollmentPeriod(mysqli $conn): void {
    $p   = getEnrollmentPeriodRow($conn);
    $now = date('Y-m-d\TH:i');
    echo json_encode([
        'success'   => true,
        'period'    => $p,
        'is_open'   => isEnrollmentOpen($conn),
        'server_now'=> $now,
    ]);
}

/** POST ?action=set_enrollment_period — admin only */
function setEnrollmentPeriod(mysqli $conn, array $data): void {
    $authUser = requireAuth($conn, 'admin');

    $isOpen = (bool)($data['is_open'] ?? false);
    $label  = trim($data['label']   ?? '');

    // Normalize: browser sends "2026-03-20T01:09" — store with space separator
    $startRaw = trim($data['start'] ?? '');
    $endRaw   = trim($data['end']   ?? '');
    $start = $startRaw ? str_replace('T', ' ', $startRaw) : null;
    $end   = $endRaw   ? str_replace('T', ' ', $endRaw)   : null;

    // FIX EP-ENROLL-01: Derive semester + school_year from the label so that
    // accounting.ts can read them directly from period.semester / period.school_year
    // without having to regex-parse the label itself.
    // Label format expected: "1st Semester, AY 2025-2026"
    $semTerm    = '';
    $schoolYear = '';
    if ($label !== '' && preg_match('/^(.+?),\s*AY\s*(\d{4}-\d{4})/i', $label, $lm)) {
        $semTerm    = trim($lm[1]);
        $schoolYear = trim($lm[2]);
    } else {
        // Fallback: no AY found in label — derive school year from current date
        $y = (int)date('Y');
        $schoolYear = $y . '-' . ($y + 1);
    }

    $val = json_encode([
        'is_open'     => $isOpen,
        'start'       => $start,
        'end'         => $end,
        'label'       => $label,
        'semester'    => $semTerm,    
        'school_year' => $schoolYear, 
    ]);
    $stmt = $conn->prepare("INSERT INTO sys_config (config_key, config_value)
                  VALUES ('enrollment_period', ?)
                  ON DUPLICATE KEY UPDATE config_value = ?, updated_at = NOW()");
    $stmt->bind_param('ss', $val, $val);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'is_open' => $isOpen, 'period' => json_decode($val, true)]);}