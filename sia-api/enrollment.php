<?php
// Prevent HTML error output from breaking JSON - must be FIRST line
error_reporting(0);
ini_set('display_errors', 0);

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

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$conn = new mysqli('localhost', 'root', '', 'sia_db');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}
$conn->set_charset("utf8mb4");

require_once __DIR__ . '/auth_middleware.php';
// Actions that happen BEFORE login (enrollment wizard) — no token exists yet
$publicActions = [
    'register_student', 'register_student_shs', 'register_student_tvet', 'register_transferee',
];
$authUser = in_array($action, $publicActions) ? null : requireAuth($conn);


error_reporting(0);
ini_set('display_errors', 0);
mysqli_report(MYSQLI_REPORT_OFF);

// Schema managed by migrate.php — no ALTER TABLE at request time

// (action already defined above)

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
            case 'get_enrollments':         getEnrollments($conn);              break;
            case 'get_payment_status':      getPaymentStatus($conn);            break;
            case 'get_enrollment_summary':  getEnrollmentSummary($conn);        break;
            case 'get_student_context':     getStudentContext($conn);           break;
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
            case 'registrar_drop_subject':  registrarDropSubject($conn, $data);     break;
            case 'submit_add_drop':          submitAddDropRequest($conn, $data);     break;
            case 'process_add_drop':         processAddDropRequest($conn, $data);    break;
            case 'set_add_drop_window':      setAddDropWindow($conn, $data);         break;
            case 'auto_enroll_new':         autoEnrollNew($conn, $data);            break;  // NEW/regular students
            case 'auto_enroll_transferee':  autoEnrollTransfereeAction($conn, $data); break; // Transferee students
            case 'auto_enroll_all':         autoEnrollAll($conn, $data);            break;  // legacy router (kept for compatibility)
            // ── Payment & Approval ────────────────────────────────────
            case 'update_payment':          updatePayment($conn, $data);            break;
            case 'approve_enrollment':      approveEnrollment($conn, $data);        break;
            case 'update_payment_plan':     updatePaymentPlan($conn, $data);        break;
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


$conn->close();

function getStudentIdFromRequest($conn) {
    $student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
    $user_id    = isset($_GET['user_id'])    ? (int)$_GET['user_id']    : 0;
    if ($student_id > 0) return $student_id;
    if ($user_id > 0) {
        $stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $r = $stmt->get_result();
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

    $stmt = $conn->prepare("SELECT s.*, u.email AS user_email FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ? LIMIT 1");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result || $result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Profile not found']);
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

    // guardian_name / guardian_contact are the real DB columns
    // emergency_contact / emergency_phone also exist (may duplicate guardian)
    $guardianName    = $s['guardian_name']    ?? $s['emergency_contact'] ?? '';
    $guardianAddress = $s['guardian_address'] ?? '';
    $guardianContact = $s['guardian_contact'] ?? $s['emergency_phone']   ?? '';

    echo json_encode(['success' => true, 'student' => [
        // Identity
        'id'                  => $s['student_number'],
        'dbId'                => (int)$s['id'],
        'firstName'           => $s['first_name']        ?? '',
        'lastName'            => $s['last_name']         ?? '',
        'middleName'          => $s['middle_name']       ?? '',
        'suffix'              => $s['suffix']            ?? '',
        'email'               => $s['email']             ?? $s['user_email'] ?? '',
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
        'paymentStatus'       => $s['payment_status']    ?? '',
        'paymentMethod'       => $s['payment_method']    ?? 'GCash',
        'paymentPlan'         => $s['payment_plan']      ?? 'full',
        'approvalStatus'      => $s['approval_status']   ?? '',
        'isScholar'           => (bool)($s['is_scholar'] ?? false),
        'scholarType'         => $s['scholar_type']      ?? '',
        'scholarGrantor'      => $s['scholar_grantor']   ?? '',
        'scholarshipAmount'   => (float)($s['scholarship_amount'] ?? 0),
        // Personal
        'lrnNo'               => $s['lrn_no']            ?? '',
        'dateOfBirth'         => $s['date_of_birth']     ?? '',
        'sex'                 => $s['sex']               ?? '',
        'religion'            => $s['religion']          ?? '',
        'age'                 => $s['age']               ?? '',
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
    $stmt = $conn->prepare("SELECT payment_status, payment_method, approval_status, enrollment_status FROM students WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    if ($r) {
        echo json_encode([
            'success'          => true,
            'paymentStatus'    => $r['payment_status'],
            'paymentMethod'    => $r['payment_method'] ?? 'GCash',
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
            c.instructor,
            c.day,
            c.time,
            c.room,
            c.semester,
            c.credits,
            e.status,
            e.grade
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = ?
          AND e.status = 'Enrolled'
        ORDER BY c.day, c.time
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
                    'time'       => $row['time'],
                    'courseName' => $row['name'],
                    'courseCode' => $row['code'],
                    'instructor' => $row['instructor'],
                    'room'       => $row['room'],
                    'semester'   => $row['semester'],
                    'credits'    => (int)$row['credits'],
                    'status'     => $row['status'],
                    'grade'      => $row['grade'],
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
                WHERE student_id = ? AND status IN ('Pending','Enrolled')
              )
            GROUP BY c.id
            ORDER BY c.code
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $semester, $student_id);

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
                'code'        => $r['code'],
                'name'        => $r['name'],
                'credits'     => (int)$r['credits'],
                'instructor'  => $r['instructor'],
                'schedule'    => $r['schedule'] ?? '',
                'day'         => $r['day']      ?? '',
                'time'        => $r['time']     ?? '',
                'room'        => $r['room']     ?? '',
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

    // Get credited course IDs for this student (transferees) — exclude from enrolled list
    // FIX: Use credited_subjects JSON field instead of credited_course_ids
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
    // FIX E-02: Validate all credited IDs are integers before interpolating
    $safeIds    = array_filter(array_map('intval', $creditedIds), fn($v) => $v > 0);
    $excludeSql = !empty($safeIds) ? 'AND c.id NOT IN (' . implode(',', $safeIds) . ')' : '';

    $stmt = $conn->prepare("
        SELECT
            e.id,
            e.status,
            e.enrollment_date,
            e.grade,
            e.notes,
            c.id        AS course_id,
            c.code,
            c.name,
            c.credits,
            c.instructor,
            c.schedule,
            c.day,
            c.time,
            c.room,
            c.semester
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = ?
          AND e.status IN ('Pending', 'Enrolled')
          $excludeSql
        ORDER BY e.created_at DESC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result      = $stmt->get_result();
    $enrollments = [];
    if ($result) {
        while ($r = $result->fetch_assoc()) {
            $enrollments[] = [
                'id'             => (int)$r['id'],
                'courseId'       => (int)$r['course_id'],
                'code'           => $r['code'],
                'name'           => $r['name'],
                'credits'        => (int)$r['credits'],
                'instructor'     => $r['instructor'],
                'schedule'       => $r['schedule'],
                'day'            => $r['day'],
                'time'           => $r['time'],
                'room'           => $r['room'],
                'semester'       => $r['semester'],
                'enrollmentDate' => $r['enrollment_date'],
                'status'         => $r['status'],
                'grade'          => $r['grade'],
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
    $guardianName        = trim($data['guardianName']    ?? $data['emergencyContact'] ?? '');
    $guardianAddress     = trim($data['guardianAddress'] ?? '');
    $guardianContact     = trim($data['guardianContact'] ?? $data['emergencyPhone']   ?? '');
    $emergencyContact    = $guardianName;
    $emergencyPhone      = $guardianContact;

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
           email, phone, date_of_birth, address,
           emergency_contact, emergency_phone,
           guardian_name, guardian_address, guardian_contact,
           program, student_type, student_category, payment_method, payment_plan, semester, enrollment_date,
           lrn_no, sex, religion, age, place_of_birth, citizenship, mother_tongue,
           is_indigenous, psa_birth_cert_no,
           last_school_attended,
           has_special_needs, special_needs_details,
           has_assistive_tech, assistive_tech_details,
           is_scholar, scholar_type, scholar_grantor, scholarship_amount,
           tor_eval_status,
           enrollment_status, payment_status, approval_status)
        VALUES
          (?, ?,
           ?, ?, ?, ?,
           ?, ?, ?, ?,
           ?, ?,
           ?, ?, ?,
           ?, ?, ?, ?, ?, ?, ?,
           ?, ?, ?, ?, ?, ?, ?,
           ?, ?,
           ?,
           ?, ?,
           ?, ?,
           ?, ?, ?, ?,
           'Pending',
           'Pending', 'Pending', 'Pending')
    ");

    if (!$ins) {
        echo json_encode(['success' => false, 'message' => 'DB prepare error: ' . $conn->error]);
        return;
    }

    $ins->bind_param("isssssssssssssssssssssssssssissisississd",
        $user_id, $studentNumber,
        $firstName, $lastName, $middleName, $suffix,
        $email, $phone, $dobBind, $address,
        $emergencyContact, $emergencyPhone,
        $guardianName, $guardianAddress, $guardianContact,
        $program, $studentType, $studentCategory, $paymentMethod, $paymentPlan, $semester, date('Y-m-d'),
        $lrnNo, $sex, $religion, $age, $placeOfBirth, $citizenship, $motherTongue,
        $isIndigenous, $psaBirthCertNo,
        $lastSchoolAttended,
        $hasSpecialNeeds, $specialNeedsDetails,
        $hasAssistiveTech, $assistiveTechDetails,
        $isScholar, $scholarType, $scholarGrantor, $scholarshipAmount
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
    $guardianName    = trim($data['guardianName']    ?? $data['emergencyContact'] ?? '');
    $guardianAddress = trim($data['guardianAddress'] ?? '');
    $guardianContact = trim($data['guardianContact'] ?? $data['emergencyPhone']   ?? '');

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

    // Generate student number
    $year    = date('Y');
    $prefix  = "STU-$year-";
    $maxStmt = $conn->prepare(
        "SELECT MAX(CAST(SUBSTRING_INDEX(student_number, '-', -1) AS UNSIGNED)) AS maxNum
          FROM students WHERE student_number LIKE ?"
    );
    $like = $prefix . '%';
    $maxStmt->bind_param("s", $like);
    $maxStmt->execute();
    $maxNum        = (int)($maxStmt->get_result()->fetch_assoc()['maxNum'] ?? 0);
    $studentNumber = $prefix . str_pad($maxNum + 1, 4, '0', STR_PAD_LEFT);

    $dobBind = (!empty($dateOfBirth)) ? $dateOfBirth : '';

    // Schema managed by migrate.php

    // INSERT using actual DB column names
    $ins = $conn->prepare("
        INSERT INTO students
          (user_id, student_number,
           first_name, last_name, middle_name, suffix,
           email, phone, date_of_birth, address,
           emergency_contact, emergency_phone,
           guardian_name, guardian_address, guardian_contact,
           program, student_type, student_category, payment_method, payment_plan, semester, enrollment_date,
           lrn_no, sex, religion, age, place_of_birth, citizenship, mother_tongue,
           is_indigenous, psa_birth_cert_no,
           last_school_attended, strand, learning_delivery,
           has_special_needs, special_needs_details,
           has_assistive_tech, assistive_tech_details,
           is_scholar, scholar_type, scholar_grantor, scholarship_amount,
           enrollment_status, payment_status, approval_status)
        VALUES
          (?, ?,
           ?, ?, ?, ?,
           ?, ?, ?, ?,
           ?, ?,
           ?, ?, ?,
           ?, ?, ?, ?, ?, ?, ?,
           ?, ?, ?, ?, ?, ?, ?,
           ?, ?,
           ?, ?, ?,
           ?, ?,
           ?, ?,
           ?, ?, ?, ?,
           'Pending', 'Pending', 'Pending')
    ");

    if (!$ins) {
        echo json_encode(['success' => false, 'message' => 'DB prepare error: ' . $conn->error]);
        return;
    }

    // 42 bound params
    $ins->bind_param("isssssssssssssssssssssssssssissssssisisssd",
        $user_id, $studentNumber,
        $firstName, $lastName, $middleName, $suffix,
        $email, $phone, $dobBind, $address,
        $emergencyContact, $emergencyPhone,
        $guardianName, $guardianAddress, $guardianContact,
        $program, $studentType, $studentCategory, $paymentMethod, $paymentPlan, $semester, $enrollmentDate,
        $lrnNo, $sex, $religion, $age, $placeOfBirth, $citizenship, $motherTongue,
        $isIndigenous, $psaBirthCertNo,
        $lastSchoolAttended, $strand, $learningDelivery,
        $hasSpecialNeeds, $specialNeedsDetails,
        $hasAssistiveTech, $assistiveTechDetails,
        $isScholar, $scholarType, $scholarGrantor, $scholarshipAmount
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
        if ($paymentMethod === 'Cash') {
            $semester = '1st Semester, AY ' . date('Y') . '-' . (date('Y') + 1);
            $logStmt  = $conn->prepare("
                INSERT INTO payment_logs (student_id, payment_method, gcash_reference, gcash_amount, semester, status)
                VALUES (?, 'Cash', '', 0, ?, 'Pending')
            ");
            $logStmt->bind_param("is", $newStudentId, $semester);
            $logStmt->execute();
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

    // Duplicate check
    $dup = $conn->prepare("SELECT id FROM enrollments WHERE student_id=? AND course_id=? AND status IN ('Pending','Enrolled') LIMIT 1");
    $dup->bind_param("ii", $student_id, $course_id);
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
    $ins = $conn->prepare("
        INSERT INTO enrollments (student_id, course_id, enrollment_date, status, semester, notes)
        VALUES (?, ?, ?, 'Enrolled', ?, ?)
    ");
    $ins->bind_param("iisss", $student_id, $course_id, $enrollDate, $semester, $notes);
    $ins->execute();

    if ($ins->affected_rows > 0) {
        $eid = $ins->insert_id;

        // Increment course enrolled count
        $upd = $conn->prepare("UPDATE courses SET enrolled_count = enrolled_count + 1 WHERE id = ?");
        $upd->bind_param("i", $course_id);
        $upd->execute();

        // Update student enrollment_status = Enrolled
        $updSt = $conn->prepare("UPDATE students SET enrollment_status = 'Enrolled' WHERE id = ?");
        $updSt->bind_param("i", $student_id);
        $updSt->execute();

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
        $dec = $conn->prepare("UPDATE courses SET enrolled_count = GREATEST(enrolled_count-1,0) WHERE id=?");
        $dec->bind_param("i", $course_id);
        $dec->execute();
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
    $stmt = $conn->prepare("UPDATE students SET approval_status='Approved', enrollment_status='Enrolled' WHERE id=?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();

    // BUG FIX: Auto-enroll the student in their program courses right at approval time.
    // Without this, transferees arrive at the dashboard with 0 enrollment rows because:
    //   1. auto_enroll_all ran before approval (when courses couldn't be inserted yet), OR
    //   2. ensureEnrolledThenLoad() on the frontend sees approval_status='Approved' on
    //      first load and calls auto_enroll_all — but for transferees the TOR-credited
    //      exclusion query may have silently wiped out the course list.
    // Calling it here at the moment of approval guarantees rows are inserted.
    $enrolled = autoEnrollAll($conn, ['student_id' => $student_id], false);
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
    $stmt = $conn->prepare("SELECT s.*, u.email AS user_email FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ? LIMIT 1");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $s = $stmt->get_result()->fetch_assoc();
    if (!$s) { echo json_encode(['success' => false, 'message' => 'Student not found']); return; }

    // 2. Enrolled courses
    $cStmt = $conn->prepare("
        SELECT c.code, c.name, c.credits, c.instructor, c.day, c.time, c.room, c.semester, e.status
        FROM enrollments e JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = ? AND e.status IN ('Pending','Enrolled')
        ORDER BY c.code
    ");
    $cStmt->bind_param("i", $student_id);
    $cStmt->execute();
    $cResult = $cStmt->get_result();
    $courses = []; $totalCredits = 0;
    while ($r = $cResult->fetch_assoc()) {
        $courses[]     = $r;
        $totalCredits += (int)$r['credits'];
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

    // 4. Real fees from tuition_fees table (set by Accounting)
    $tfStmt = $conn->prepare("SELECT * FROM tuition_fees WHERE student_id = ? LIMIT 1");
    $tfStmt->bind_param("i", $student_id);
    $tfStmt->execute();
    $tf = $tfStmt->get_result()->fetch_assoc();

    // 5. Real amount paid from installment_payments (FIX E-06: use prepared statements)
    $paidStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS total_paid FROM installment_payments WHERE student_id = ?");
    $paidStmt->bind_param("i", $student_id);
    $paidStmt->execute();
    $totalPaid = (float)($paidStmt->get_result()->fetch_assoc()['total_paid'] ?? 0);
    // FIX E-07: Add verified GCash payments not yet in installment_payments
    // Use NOT EXISTS instead of NOT IN with COALESCE to avoid null-matching bug
    $plStmt = $conn->prepare("
        SELECT COALESCE(SUM(pl.gcash_amount),0) AS pl_paid
        FROM payment_logs pl
        WHERE pl.student_id = ?
          AND pl.status = 'Verified'
          AND NOT EXISTS (
              SELECT 1 FROM installment_payments ip
              WHERE ip.payment_log_id = pl.id AND ip.student_id = ?
          )
    ");
    $plStmt->bind_param("ii", $student_id, $student_id);
    $plStmt->execute();
    $totalPaid += (float)($plStmt->get_result()->fetch_assoc()['pl_paid'] ?? 0);

    if ($tf) {
        $totalAssessment = (float)$tf['total_assessment'];
        $discount        = (float)($tf['discount'] ?? 0);
        $balance         = max(0.0, $totalAssessment - $totalPaid);
        $payStatus       = $balance <= 0 ? 'Fully Paid' : ($totalPaid > 0 ? 'Partial' : ($s['payment_status'] ?? 'Pending'));
        $payment = [
            'units'          => (int)$tf['units'],
            'tuitionFee'     => (float)$tf['tuition_fee'],
            'miscFee'        => (float)$tf['miscellaneous_fee'],
            'registrationFee'=> (float)$tf['registration_fee'],
            'laboratoryFee'  => (float)$tf['laboratory_fee'],
            'energyFee'      => (float)$tf['energy_fee'],
            'subtotal'       => (float)$tf['subtotal'],
            'totalFee'       => $totalAssessment,
            'scholarDiscount'=> $discount,
            'installmentFee' => (float)($tf['installment_fee'] ?? 0),
            'amountDue'      => $totalAssessment,
            'amountPaid'     => $totalPaid,
            'balance'        => $balance,
            'status'         => $payStatus,
            'method'         => $s['payment_method'] ?? 'GCash',
            'paymentDate'    => null,
        ];
    } else {
        // Fallback if no tuition_fees record
        $totalAssessment = $totalCredits * 650 + 6688 + 700 + ($totalCredits * 63);
        $discount        = (float)($s['scholarship_amount'] ?? 0);
        $balance         = max(0.0, $totalAssessment - $discount - $totalPaid);
        $payment = [
            'totalFee'       => $totalAssessment,
            'scholarDiscount'=> $discount,
            'amountDue'      => max(0, $totalAssessment - $discount),
            'amountPaid'     => $totalPaid,
            'balance'        => $balance,
            'status'         => $s['payment_status'] ?? 'Pending',
            'method'         => $s['payment_method'] ?? 'GCash',
            'paymentDate'    => null,
        ];
    }

    // 6. Real term payments from installment_payments
    $ipRes   = $conn->query("SELECT exam_period, SUM(amount) AS amt, MAX(payment_date) AS pay_date, MAX(or_ar_number) AS or_no FROM installment_payments WHERE student_id = $student_id GROUP BY exam_period");
    $termMap = [];
    if ($ipRes) { while ($r = $ipRes->fetch_assoc()) $termMap[$r['exam_period']] = $r; }

    $paymentPlan  = $s['payment_plan'] ?? 'full';
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

    echo json_encode([
        'success'        => true,
        'enrollmentDate' => $s['enrollment_date'],
        'semester'       => $semester,
        'program'        => $s['program'],
        'yearLevel'      => $s['year_level'] ?? '1st Year',
        'totalCourses'   => count($courses),
        'totalCredits'   => $totalCredits,
        'courses'        => $courses,
        'payment'        => $payment,
        'termPayments'   => $termPayments,
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
function autoEnrollNew($conn, $data, $respondJson = true) {
    $student_id = (int)($data['student_id'] ?? 0);
    if (!$student_id) {
        if ($respondJson) echo json_encode(['success' => false, 'message' => 'student_id required']);
        return 0;
    }

    $st = $conn->prepare("SELECT program, semester, year_level, student_type FROM students WHERE id = ? LIMIT 1");
    $st->bind_param("i", $student_id);
    $st->execute();
    $student = $st->get_result()->fetch_assoc();
    if (!$student) {
        if ($respondJson) echo json_encode(['success' => false, 'message' => 'Student not found']);
        return 0;
    }

    $semester    = trim($data['semester'] ?? $student['semester'] ?? '');
    $programName = trim($student['program']);
    $yearLevel   = trim($student['year_level'] ?? '1st Year');
    $semesterTerm = '';
    if (preg_match('/^(1st Semester|2nd Semester|Summer)/i', $semester, $m)) {
        $semesterTerm = $m[1];
    }

    // Collect courses — no credits to exclude for regular students
    $courses  = collectProgramCourses($conn, $programName, $semesterTerm, $yearLevel, $student_id, []);
    $enrolled = insertEnrollments($conn, $student_id, $courses, $semester, 'Auto-enrolled');

    // Mark enrollment_status only when accounting has already approved
    if ($enrolled > 0) {
        $ap = $conn->query("SELECT approval_status FROM students WHERE id = $student_id LIMIT 1")->fetch_assoc();
        if ($ap && $ap['approval_status'] === 'Approved') {
            $conn->query("UPDATE students SET enrollment_status = 'Enrolled' WHERE id = $student_id");
        }
    }

    if ($respondJson) {
        echo json_encode([
            'success'  => true,
            'enrolled' => $enrolled,
            'program'  => $programName,
            'message'  => $enrolled > 0
                ? "$enrolled course(s) auto-enrolled for $programName."
                : 'Already enrolled in all available courses for this program.',
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

    $st = $conn->prepare("SELECT program, semester, year_level FROM students WHERE id = ? LIMIT 1");
    $st->bind_param("i", $student_id);
    $st->execute();
    $student = $st->get_result()->fetch_assoc();
    if (!$student) {
        if ($respondJson) echo json_encode(['success' => false, 'message' => 'Student not found']);
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

    $courses  = collectProgramCourses($conn, $programName, $semesterTerm, $yearLevel, $student_id, $creditedIds);
    $enrolled = insertEnrollments($conn, $student_id, $courses, $semester, 'Auto-enrolled (Transferee)');

    // Mark enrollment_status only when accounting has already approved
    if ($enrolled > 0) {
        $ap = $conn->query("SELECT approval_status FROM students WHERE id = $student_id LIMIT 1")->fetch_assoc();
        if ($ap && $ap['approval_status'] === 'Approved') {
            $conn->query("UPDATE students SET enrollment_status = 'Enrolled' WHERE id = $student_id");
        }
    }

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
    $pn_esc     = $conn->real_escape_string($programName);
    $yl_esc     = $conn->real_escape_string($yearLevel);
    $st_esc     = $conn->real_escape_string($semesterTerm);

    $excludeClause = !empty($excludeIds)
        ? 'AND c.id NOT IN (' . implode(',', array_map('intval', $excludeIds)) . ')'
        : '';

    // Skip courses already actively enrolled (Enrolled or Pending).
    // Dropped rows (credited subjects) are intentionally NOT excluded here —
    // INSERT IGNORE will silently skip them via the UNIQUE KEY constraint.
    $alreadyEnrolledSub = "SELECT course_id FROM enrollments
                           WHERE student_id = $student_id
                             AND status IN ('Enrolled','Pending')";

    // Semester: match ONLY the term portion (e.g. '1st Semester').
    // Courses are stored under old AY values ('1st Semester, AY 2024-2025')
    // but a student enrolling in AY 2026-2027 still needs those courses.
    // LIKE '1st Semester%' matches correctly regardless of AY suffix.
    $semClause = ($st_esc !== '') ? "AND c.semester LIKE '$st_esc%'" : '';

    // Year level: REQUIRED — prevents 1st year students from being enrolled
    // in 2nd, 3rd, or 4th year subjects.
    $ylClause = ($yl_esc !== '') ? "AND c.year_level = '$yl_esc'" : '';

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
    $pc_esc = $conn->real_escape_string($programCode);

    // Source 1: program_courses junction table (most reliable)
    $hasPCTable = $conn->query("SHOW TABLES LIKE 'program_courses'")->num_rows > 0;
    $hasPTable  = $conn->query("SHOW TABLES LIKE 'programs'")->num_rows > 0;

    if ($hasPCTable && $hasPTable) {
        $res = $conn->query("
            SELECT c.id, c.name, c.semester, c.year_level
            FROM program_courses pc
            JOIN programs p ON pc.program_id = p.id
            JOIN courses  c ON pc.course_id  = c.id
            WHERE (p.name = '$pn_esc' OR p.code = '$pn_esc' OR p.code = '$pc_esc')
              AND c.id NOT IN ($alreadyEnrolledSub)
              $ylClause
              $semClause
              $excludeClause
            LIMIT 40
        ");
        if ($res) {
            foreach ($res->fetch_all(MYSQLI_ASSOC) as $c) {
                $allCourses[$c['id']] = $c;
            }
        }
    }

    // Source 2: courses.program direct column — match by RESOLVED CODE first,
    // then fall back to full name. This handles both storage conventions.
    // We search by code (primary) so that AEC courses wrongly tagged with the
    // full BSIT name do NOT contaminate a CIMT/BSIT student's enrollment.
    $res = $conn->query("
        SELECT id, name, semester, year_level
        FROM courses
        WHERE program = '$pc_esc'
          AND id NOT IN ($alreadyEnrolledSub)
          $ylClause
          $semClause
          $excludeClause
        LIMIT 40
    ");
    if ($res) {
        foreach ($res->fetch_all(MYSQLI_ASSOC) as $c) {
            $allCourses[$c['id']] = $c;
        }
    }

    // Source 3: if still empty, try by full name (legacy data compatibility)
    if (empty($allCourses) && $pc_esc !== $pn_esc) {
        $res = $conn->query("
            SELECT id, name, semester, year_level
            FROM courses
            WHERE program = '$pn_esc'
              AND id NOT IN ($alreadyEnrolledSub)
              $ylClause
              $semClause
              $excludeClause
            LIMIT 40
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
        $ins = $conn->prepare("
            INSERT IGNORE INTO enrollments
                (student_id, course_id, enrollment_date, status, semester, notes)
            VALUES (?, ?, ?, 'Enrolled', ?, ?)
        ");
        $ins->bind_param("iisss", $student_id, $course['id'], $enrollDate, $useSemester, $notes);
        if ($ins->execute() && $ins->affected_rows > 0) {
            $enrolled++;
            $conn->query("UPDATE courses SET enrolled_count = enrolled_count + 1 WHERE id = " . (int)$course['id']);
        }
    }

    return $enrolled;
}

function updateProfile($conn, $data) {
    $student_id       = (int)($data['student_id']       ?? 0);
    $phone            = trim($data['phone']              ?? '');
    $address          = trim($data['address']            ?? '');
    $emergencyContact = trim($data['emergencyContact']   ?? '');
    $emergencyPhone   = trim($data['emergencyPhone']     ?? '');
    $dateOfBirth      = trim($data['dateOfBirth']        ?? '');

    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'student_id required']);
        return;
    }

    $stmt = $conn->prepare("
        UPDATE students
        SET phone = ?, address = ?, emergency_contact = ?, emergency_phone = ?, date_of_birth = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sssssi", $phone, $address, $emergencyContact, $emergencyPhone, $dateOfBirth, $student_id);
    $stmt->execute();

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

    // Also add installment_fee to tuition_fees if switching to installment
    if ($payment_plan === 'installment') {
        $conn->query("UPDATE tuition_fees
            SET installment_fee = 750.00,
                total_assessment = GREATEST(0, subtotal - discount + 750.00),
                updated_at = NOW()
            WHERE student_id = $student_id AND installment_fee = 0");
    } else {
        $conn->query("UPDATE tuition_fees
            SET installment_fee = 0.00,
                total_assessment = GREATEST(0, subtotal - discount),
                updated_at = NOW()
            WHERE student_id = $student_id AND installment_fee > 0");
    }

    $stmt = $conn->prepare("UPDATE students SET payment_plan = ?, payment_method = ? WHERE id = ?");
    $stmt->bind_param("ssi", $payment_plan, $payment_method, $student_id);
    $stmt->execute();

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
    $pn_esc = $conn->real_escape_string($programName);
    $yl_esc = $conn->real_escape_string($yearLevel);

    // Extract semester term (e.g. '1st Semester') — strip AY suffix so we match
    // courses stored under any school year.
    $semTerm   = '';
    $semFilter = ''; $sfNoJoin = '';
    if ($semester !== '') {
        preg_match('/^(1st Semester|2nd Semester|Summer)/i', $semester, $sm);
        $semTerm   = $conn->real_escape_string($sm[1] ?? $semester);
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

    // Source 1: program_courses junction (most accurate if populated)
    $pu = $conn->query("SELECT COALESCE(SUM(c.credits),0) AS u
        FROM program_courses pc
        JOIN programs p ON pc.program_id=p.id
        JOIN courses c  ON pc.course_id=c.id
        WHERE (p.name='$pn_esc' OR p.code='$pn_esc') $ylFilter $semFilter");
    $units = (int)(($pu ? $pu->fetch_assoc()['u'] : 0) ?: 0);

    // Source 2: courses.program direct column
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
    $pn_esc = $conn->real_escape_string($programName);
    $yl_esc = $conn->real_escape_string($yearLevel);

    // Extract semester term — strip AY suffix
    $semTerm   = '';
    $semFilter = ''; $sfNoJoin = '';
    if ($semester !== '') {
        preg_match('/^(1st Semester|2nd Semester|Summer)/i', $semester, $sm);
        $semTerm   = $conn->real_escape_string($sm[1] ?? $semester);
        $semFilter = "AND c.semester LIKE '$semTerm%'";
        $sfNoJoin  = "AND semester LIKE '$semTerm%'";
    }

    $ylFilter   = ($yl_esc !== '') ? "AND c.year_level = '$yl_esc'" : '';
    $ylFilterNJ = ($yl_esc !== '') ? "AND year_level = '$yl_esc'" : '';

    // Priority 1: TOR approved_units — authoritative after registrar evaluation.
    // This already accounts for credited subjects (program_units - credited_units).
    $units = 0;
    $tor_r = $conn->query("SELECT approved_units FROM tor_evaluations
                           WHERE student_id = $student_id AND status = 'Evaluated'
                           ORDER BY id DESC LIMIT 1");
    $tor   = $tor_r ? $tor_r->fetch_assoc() : null;
    if ($tor && (int)$tor['approved_units'] > 0) {
        $units = (int)$tor['approved_units'];
    }

    // Priority 2 (pre-evaluation estimate): count live from courses using
    // semester + year_level filters.
    // NEVER use cached tuition_fees.units — it may be a stale value computed
    // before the year_level filter existed (e.g. 15 instead of 12).
    if ($units <= 0) {
        $pu = $conn->query("SELECT COALESCE(SUM(c.credits),0) AS u
            FROM program_courses pc
            JOIN programs p ON pc.program_id=p.id
            JOIN courses c  ON pc.course_id=c.id
            WHERE (p.name='$pn_esc' OR p.code='$pn_esc') $ylFilter $semFilter");
        $units = (int)(($pu ? $pu->fetch_assoc()['u'] : 0) ?: 0);
    }
    if ($units <= 0) {
        $fb = $conn->query("SELECT COALESCE(SUM(credits),0) AS u
            FROM courses WHERE program='$pn_esc' $ylFilterNJ $sfNoJoin");
        $units = (int)(($fb ? $fb->fetch_assoc()['u'] : 0) ?: 0);
    }
    if ($units <= 0) $units = 18;

    return _buildFees($conn, $student_id, $programName, $semester, $yearLevel, $units, $paymentPlan, $discount);
}


// ─────────────────────────────────────────────────────────────
// SHARED FEE BUILDER — used by both compute functions above.
// Computes all fee line items, saves to tuition_fees, returns array.
// ─────────────────────────────────────────────────────────────
function _buildFees($conn, $student_id, $programName, $semester, $yearLevel, $units, $paymentPlan, $discount) {
    $pn_esc = $conn->real_escape_string($programName);
    $yl_esc = $conn->real_escape_string($yearLevel);

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
    // (not per-student enrollment — all students pay the same lab count × ₱1,900)
    $conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS is_lab TINYINT(1) DEFAULT 0");
    $labRoomRes = $conn->query("SELECT COUNT(*) AS cnt FROM rooms WHERE room_type = 'Laboratory'");
    $lab_cnt    = (int)(($labRoomRes ? $labRoomRes->fetch_assoc()['cnt'] : 0) ?? 0);

    $tuition_fee = $units * 650;
    $misc_fee    = 6688.00;
    $reg_fee     = 700.00;
    $lab_fee     = $lab_cnt * 1900;
    $energy_fee  = $units * 21 * 3;
    $subtotal    = $tuition_fee + $misc_fee + $reg_fee + $lab_fee + $energy_fee;
    $inst_fee    = ($paymentPlan === 'installment') ? 750.00 : 0.00;
    $total       = max(0, $subtotal - $discount + $inst_fee);

    // Persist to tuition_fees
    $conn->query("
        INSERT INTO tuition_fees
            (student_id, units, tuition_fee, miscellaneous_fee, registration_fee,
             laboratory_fee, energy_fee, subtotal, discount, installment_fee, total_assessment)
        VALUES
            ($student_id, $units, $tuition_fee, $misc_fee, $reg_fee,
             $lab_fee, $energy_fee, $subtotal, $discount, $inst_fee, $total)
        ON DUPLICATE KEY UPDATE
            units=$units, tuition_fee=$tuition_fee, miscellaneous_fee=$misc_fee,
            registration_fee=$reg_fee, laboratory_fee=$lab_fee, energy_fee=$energy_fee,
            subtotal=$subtotal, discount=$discount, installment_fee=$inst_fee,
            total_assessment=$total, updated_at=NOW()
    ");

    return [
        'units'            => $units,
        'tuitionFee'       => $tuition_fee,
        'miscellaneousFee' => $misc_fee,
        'registrationFee'  => $reg_fee,
        'laboratoryFee'    => $lab_fee,
        'energyFee'        => $energy_fee,
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
    $student_id = getStudentIdFromRequest($conn);
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student not found']); return;
    }

    // ── 1. Load student row ────────────────────────────────────
    $s_res = $conn->prepare("SELECT s.*, u.email AS user_email FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ? LIMIT 1");
    $s_res->bind_param("i", $student_id);
    $s_res->execute();
    $s = $s_res->get_result()->fetch_assoc();
    if (!$s) { echo json_encode(['success' => false, 'message' => 'Student not found']); return; }

    $programName    = trim($s['program']          ?? '');
    $studentType    = trim($s['student_type']      ?? 'New');
    $yearLevel      = trim($s['year_level']        ?? '1st Year');
    $paymentPlan    = $s['payment_plan']            ?? 'full';
    $paymentMethod  = $s['payment_method']          ?? 'GCash';
    $approvalStatus = $s['approval_status']         ?? 'Pending';
    $paymentStatus  = $s['payment_status']          ?? 'Pending';
    $enrollStatus   = $s['enrollment_status']       ?? 'Pending';

    // Self-heal payment_plan from AR records
    if ($paymentPlan === 'full') {
        $arChk = $conn->query("SELECT id FROM installment_payments WHERE student_id = $student_id AND or_ar_type = 'AR' LIMIT 1");
        if ($arChk && $arChk->num_rows > 0) {
            $paymentPlan = 'installment';
            $conn->query("UPDATE students SET payment_plan = 'installment' WHERE id = $student_id");
        }
    }

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
    $discount = (float)($s['scholarship_amount'] ?? 0);

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
    } else {
        $fees = computeFeesNew($conn, $student_id, $programName, $semester, $yearLevel, $paymentPlan, $discount);
    }

    $total = $fees['totalAssessment'];

    // ── 5. Payment receipts & term breakdown ──────────────────
    $total_paid = 0; $payments = []; $termBreakdown = [];
    $termOrder  = ['Downpayment','Prelim','Midterm','Finals','Full'];

    $ip_res = $conn->query("SELECT ip.*, u.first_name AS rec_by FROM installment_payments ip LEFT JOIN users u ON ip.recorded_by = u.id WHERE ip.student_id = $student_id ORDER BY ip.created_at ASC");
    if ($ip_res) {
        while ($r = $ip_res->fetch_assoc()) {
            $amt         = (float)$r['amount'];
            $total_paid += $amt;
            $payments[]  = [
                'orArNumber'  => $r['or_ar_number'],
                'orArType'    => $r['or_ar_type'],
                'type'        => $r['or_ar_type'],
                'period'      => $r['exam_period'],
                'paymentDate' => $r['payment_date'],
                'method'      => $r['payment_method'],
                'amount'      => $amt,
                'recordedBy'  => $r['rec_by'] ?? '',
            ];
            $key = $r['exam_period'];
            if (!isset($termBreakdown[$key])) {
                $termBreakdown[$key] = ['period' => $key, 'amountPaid' => $amt, 'orArNumber' => $r['or_ar_number'], 'orArType' => $r['or_ar_type'], 'paymentDate' => $r['payment_date'], 'paymentMethod' => $r['payment_method']];
            }
        }
    }

    // Also pull GCash-verified payments not yet in installment_payments
    $pl_res = $conn->query("SELECT pl.*, u.first_name AS vby FROM payment_logs pl LEFT JOIN users u ON pl.verified_by=u.id WHERE pl.student_id=$student_id AND pl.status='Verified' ORDER BY pl.verified_at ASC");
    if ($pl_res) {
        while ($r = $pl_res->fetch_assoc()) {
            $amt    = (float)$r['gcash_amount'];
            if ($amt <= 0) $amt = $total;
            $plChkS = $conn->prepare("SELECT id FROM installment_payments WHERE payment_log_id = ? LIMIT 1");
                $plChkS->bind_param("i", $r['id']);
                $plChkS->execute();
                $plChk  = $plChkS->get_result();
            if ($plChk && $plChk->num_rows === 0) {
                $period = ($paymentPlan === 'installment') ? 'Downpayment' : 'Full';
                if (!isset($termBreakdown[$period])) {
                    $total_paid  += $amt;
                    $payments[]   = [
                        'orArNumber'  => $r['or_ar_number'] ?? ('OR-' . $r['id']),
                        'orArType'    => ($paymentPlan === 'installment') ? 'AR' : 'OR',
                        'type'        => ($paymentPlan === 'installment') ? 'AR' : 'OR',
                        'period'      => $period,
                        'paymentDate' => $r['gcash_date'] ?? date('Y-m-d', strtotime($r['verified_at'])),
                        'method'      => $r['payment_method'] ?? 'GCash',
                        'amount'      => $amt,
                        'recordedBy'  => $r['vby'] ?? '',
                    ];
                    $termBreakdown[$period] = ['period' => $period, 'amountPaid' => $amt, 'orArNumber' => $r['or_ar_number'] ?? 'OR-'.$r['id'], 'orArType' => ($paymentPlan==='installment')?'AR':'OR', 'paymentDate' => $r['gcash_date'] ?? date('Y-m-d', strtotime($r['verified_at'])), 'paymentMethod' => $r['payment_method'] ?? 'GCash'];
                }
            }
        }
    }

    $balance        = max(0, $total - $total_paid);
    $is_fully_paid  = ($balance <= 0 && $total_paid > 0);
    $sortedTerms    = [];
    foreach ($termOrder as $t) { if (isset($termBreakdown[$t])) $sortedTerms[] = $termBreakdown[$t]; }

    // ── 6. Build response ─────────────────────────────────────
    $pic             = $s['profile_picture'] ?: 'https://ui-avatars.com/api/?name=' . urlencode(($s['first_name']??'').'+'.($s['last_name']??'')) . '&size=150';
    $guardianName    = $s['guardian_name']    ?? $s['emergency_contact'] ?? '';
    $guardianContact = $s['guardian_contact'] ?? $s['emergency_phone']   ?? '';

    echo json_encode([
        'success' => true,
        'student' => [
            'id'               => $s['student_number'],
            'dbId'             => (int)$s['id'],
            'firstName'        => $s['first_name']       ?? '',
            'lastName'         => $s['last_name']        ?? '',
            'middleName'       => $s['middle_name']      ?? '',
            'suffix'           => $s['suffix']           ?? '',
            'email'            => $s['email']            ?? $s['user_email'] ?? '',
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
            'isScholar'        => (bool)($s['is_scholar'] ?? false),
            'scholarshipAmount'=> $discount,
            'torEvalStatus'    => $torStatus,
            'lrnNo'            => $s['lrn_no']           ?? '',
            'dateOfBirth'      => $s['date_of_birth']    ?? '',
            'sex'              => $s['sex']              ?? '',
            'address'          => $s['address']          ?? '',
            'guardianName'     => $guardianName,
            'guardianContact'  => $guardianContact,
            'emergencyContact' => $s['emergency_contact'] ?? $guardianName,
            'emergencyPhone'   => $s['emergency_phone']   ?? $guardianContact,
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
    $like = '%' . $conn->real_escape_string($q) . '%';
    $res  = $conn->query("
        SELECT s.id, s.student_number, s.first_name, s.last_name,
               s.program, s.year_level, s.semester, s.student_category,
               s.enrollment_status, s.approval_status, u.email
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.student_number LIKE '$like'
           OR s.first_name     LIKE '$like'
           OR s.last_name      LIKE '$like'
           OR CONCAT(s.first_name,' ',s.last_name) LIKE '$like'
        ORDER BY s.last_name, s.first_name
        LIMIT $limit
    ");
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
    $res = $conn->query("
        SELECT e.id AS enrollment_id, e.status, e.enrollment_date,
               c.id AS course_id, c.code, c.name, c.credits,
               c.instructor, c.day, c.time, c.room, c.semester
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = $sid AND e.status IN ('Enrolled','Pending')
        ORDER BY c.code
    ");
    $enrolled = [];
    while ($r = $res->fetch_assoc()) { $enrolled[] = $r; }

    // Available courses (not already enrolled)
    // Get student program so we can prioritize relevant courses
    $stuRes = $conn->query("SELECT semester, program FROM students WHERE id=$sid LIMIT 1");
    $stuRow = $stuRes ? $stuRes->fetch_assoc() : [];
    $stuProg = $conn->real_escape_string($stuRow['program'] ?? '');

    $avRes = $conn->query("
        SELECT c.id, c.code, c.name, c.credits, c.instructor,
               c.day, c.time, c.room, c.semester,
               COALESCE(c.capacity,50) AS capacity,
               COUNT(e2.id) AS enrolled_count
        FROM courses c
        LEFT JOIN enrollments e2 ON e2.course_id = c.id AND e2.status IN ('Enrolled','Pending')
        WHERE c.id NOT IN (
            SELECT course_id FROM enrollments
            WHERE student_id=$sid AND status IN ('Enrolled','Pending')
        )
        GROUP BY c.id
        ORDER BY c.code
    ");
    $available = [];
    while ($r = $avRes->fetch_assoc()) {
        $r['available_seats'] = max(0, (int)$r['capacity'] - (int)$r['enrolled_count']);
        $available[] = $r;
    }

    echo json_encode(['success' => true, 'student_id' => $sid, 'enrolled' => $enrolled, 'available' => $available]);
}

// ----------------------------------------------------------------
// REGISTRAR: ADD SUBJECT to student
// POST { student_id, course_id, reason }
// ----------------------------------------------------------------
function registrarAddSubject($conn, $data) {
    $sid = (int)($data['student_id'] ?? 0);
    $cid = (int)($data['course_id']  ?? 0);
    if (!$sid || !$cid) { echo json_encode(['success' => false, 'message' => 'student_id and course_id required']); return; }

    // Duplicate check
    $dup = $conn->prepare("SELECT id FROM enrollments WHERE student_id=? AND course_id=? AND status IN ('Enrolled','Pending') LIMIT 1");
    $dup->bind_param("ii", $sid, $cid);
    $dup->execute();
    if ($dup->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Student is already enrolled in this subject']); return;
    }

    // Capacity check
    $cap = $conn->query("SELECT COALESCE(capacity,50) AS cap, COUNT(e.id) AS cnt
        FROM courses c LEFT JOIN enrollments e ON e.course_id=c.id AND e.status IN ('Enrolled','Pending')
        WHERE c.id=$cid GROUP BY c.id LIMIT 1")->fetch_assoc();
    if ($cap && (int)$cap['cnt'] >= (int)$cap['cap']) {
        echo json_encode(['success' => false, 'message' => 'Subject is full']); return;
    }

    $reason = $conn->real_escape_string(trim($data['reason'] ?? 'Add/Drop by Registrar'));
    $date   = date('Y-m-d');
    $conn->query("INSERT INTO enrollments (student_id,course_id,enrollment_date,status,notes)
                  VALUES ($sid,$cid,'$date','Enrolled','$reason')");
    if ($conn->affected_rows > 0) {
        $conn->query("UPDATE courses SET enrolled_count=enrolled_count+1 WHERE id=$cid");
        // Log it
        $conn->query("INSERT INTO audit_logs (action,description,performed_by,created_at)
                      VALUES ('add_subject','Registrar added subject (course $cid) for student $sid: $reason','registrar',NOW())");
        echo json_encode(['success' => true, 'message' => 'Subject added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add subject: ' . $conn->error]);
    }
}

// ----------------------------------------------------------------
// REGISTRAR: DROP SUBJECT from student
// POST { student_id, enrollment_id, reason }
// ----------------------------------------------------------------
function registrarDropSubject($conn, $data) {
    $sid = (int)($data['student_id']    ?? 0);
    $eid = (int)($data['enrollment_id'] ?? 0);
    if (!$sid || !$eid) { echo json_encode(['success' => false, 'message' => 'student_id and enrollment_id required']); return; }

    $row = $conn->query("SELECT course_id FROM enrollments WHERE id=$eid AND student_id=$sid AND status IN ('Enrolled','Pending') LIMIT 1")->fetch_assoc();
    if (!$row) { echo json_encode(['success' => false, 'message' => 'Enrollment not found']); return; }
    $cid    = (int)$row['course_id'];
    $reason = $conn->real_escape_string(trim($data['reason'] ?? 'Dropped by Registrar'));

    $conn->query("UPDATE enrollments SET status='Dropped', notes=CONCAT(COALESCE(notes,''),' | Drop reason: $reason') WHERE id=$eid AND student_id=$sid");
    if ($conn->affected_rows > 0) {
        $conn->query("UPDATE courses SET enrolled_count=GREATEST(enrolled_count-1,0) WHERE id=$cid");
        $conn->query("INSERT INTO audit_logs (action,description,performed_by,created_at)
                      VALUES ('drop_subject','Registrar dropped subject (course $cid) for student $sid: $reason','registrar',NOW())");
        echo json_encode(['success' => true, 'message' => 'Subject dropped successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Drop failed: ' . $conn->error]);
    }
}

// ----------------------------------------------------------------
// AUTO-CREATE add_drop_requests table if missing
// Called lazily on first use
// ----------------------------------------------------------------
function ensureAddDropTable($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS add_drop_requests (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        student_id    INT NOT NULL,
        request_type  ENUM('Add','Drop') NOT NULL,
        course_id     INT NOT NULL,
        enrollment_id INT DEFAULT NULL,
        reason        TEXT,
        status        ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
        remarks       TEXT,
        processed_by  INT DEFAULT NULL,
        processed_at  DATETIME DEFAULT NULL,
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (student_id),
        INDEX (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
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

    // Prevent duplicate pending requests
    $dup = $conn->query("SELECT id FROM add_drop_requests WHERE student_id=$sid AND course_id=$cid AND request_type='$type' AND status='Pending' LIMIT 1");
    if ($dup && $dup->num_rows > 0) {
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
        SELECT r.*, c.code, c.name AS course_name, c.credits, c.instructor, c.day, c.time, c.room
        FROM add_drop_requests r
        JOIN courses c ON r.course_id = c.id
        WHERE r.student_id = $sid
        ORDER BY r.created_at DESC
    ");
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode(['success'=>true,'requests'=>$rows]);
}

// ----------------------------------------------------------------
// REGISTRAR: Get all pending add/drop requests (or filter by status)
// GET ?action=get_add_drop_requests&status=Pending
// ----------------------------------------------------------------
function getAddDropRequests($conn) {
    ensureAddDropTable($conn);
    $status = trim($_GET['status'] ?? '');
    $where  = $status ? "WHERE r.status='".($conn->real_escape_string($status))."'" : "";

    $res = $conn->query("
        SELECT r.*,
               c.code, c.name AS course_name, c.credits, c.instructor, c.day, c.time, c.room,
               s.first_name, s.last_name, s.student_number, s.program, s.year_level, s.student_category
        FROM add_drop_requests r
        JOIN courses  c ON r.course_id  = c.id
        JOIN students s ON r.student_id = s.id
        $where
        ORDER BY r.created_at DESC
    ");
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode(['success'=>true,'requests'=>$rows]);
}

// ----------------------------------------------------------------
// REGISTRAR: Approve or Reject an add/drop request
// POST { request_id, action:'Approved'|'Rejected', remarks, processed_by }
// ----------------------------------------------------------------
function processAddDropRequest($conn, $data) {
    ensureAddDropTable($conn);
    $rid    = (int)($data['request_id']   ?? 0);
    $action = trim($data['action']        ?? '');
    $remarks= trim($data['remarks']       ?? '');
    $pby    = (int)($data['processed_by'] ?? 0);

    if (!$rid || !in_array($action, ['Approved','Rejected'])) {
        echo json_encode(['success'=>false,'message'=>'request_id and action (Approved|Rejected) required']); return;
    }

    $req = $conn->query("SELECT * FROM add_drop_requests WHERE id=$rid AND status='Pending' LIMIT 1")->fetch_assoc();
    if (!$req) { echo json_encode(['success'=>false,'message'=>'Request not found or already processed']); return; }

    $sid  = (int)$req['student_id'];
    $cid  = (int)$req['course_id'];
    $eid  = (int)$req['enrollment_id'];
    $type = $req['request_type'];
    $remarksEsc = $conn->real_escape_string($remarks);

    // Update request status
    $conn->query("UPDATE add_drop_requests SET status='$action',remarks='$remarksEsc',processed_by=$pby,processed_at=NOW() WHERE id=$rid");

    if ($action === 'Approved') {
        if ($type === 'Add') {
            // Duplicate check
            $dup = $conn->query("SELECT id FROM enrollments WHERE student_id=$sid AND course_id=$cid AND status IN ('Enrolled','Pending') LIMIT 1");
            if ($dup && $dup->num_rows > 0) {
                echo json_encode(['success'=>true,'message'=>'Request approved (student already enrolled in this subject)']); return;
            }
            $date = date('Y-m-d');
            $conn->query("INSERT INTO enrollments (student_id,course_id,enrollment_date,status,notes) VALUES ($sid,$cid,'$date','Enrolled','Add/Drop Request #$rid approved by Registrar')");
            $conn->query("UPDATE courses SET enrolled_count=enrolled_count+1 WHERE id=$cid");
        } else {
            // Drop
            $target = $eid > 0 ? "id=$eid" : "student_id=$sid AND course_id=$cid";
            $conn->query("UPDATE enrollments SET status='Dropped',notes=CONCAT(COALESCE(notes,''),' | Add/Drop Request #$rid approved') WHERE $target AND status IN ('Enrolled','Pending') LIMIT 1");
            $conn->query("UPDATE courses SET enrolled_count=GREATEST(enrolled_count-1,0) WHERE id=$cid");
        }
    }

    $conn->query("INSERT INTO audit_logs (action,description,performed_by,created_at) VALUES ('add_drop_processed','Request #$rid ($type $cid for student $sid) $action by registrar','registrar',NOW())");

    // ── Recalculate SOA (tuition_fees) if Approved ──────────────────────────
    if ($action === 'Approved') {
        recalcTuitionAfterAddDrop($conn, $sid);
    }

    echo json_encode(['success'=>true,'message'=>'Request '.$action.' successfully']);
}
// ----------------------------------------------------------------
// RECALCULATE TUITION FEES after Add/Drop approval
// Uses actual enrolled credits from enrollments table
// ----------------------------------------------------------------
function recalcTuitionAfterAddDrop($conn, $sid) {
    if (!$sid) return;

    // Get student info
    $stRes = $conn->query("SELECT program, payment_plan, scholarship_amount FROM students WHERE id=$sid LIMIT 1");
    $stRow = $stRes ? $stRes->fetch_assoc() : null;
    if (!$stRow) return;

    $discount        = (float)($stRow['scholarship_amount'] ?? 0);
    $has_installment = (strtolower($stRow['payment_plan'] ?? '') === 'installment');

    // Sum ACTUAL enrolled credits from enrollments table
    $unitsRes = $conn->query("
        SELECT COALESCE(SUM(c.credits), 0) AS total_units
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = $sid AND e.status IN ('Enrolled','Pending')
    ");
    $units = (int)(($unitsRes ? $unitsRes->fetch_assoc()['total_units'] : 0) ?: 0);
    if ($units <= 0) $units = 18;

    // Lab fee: based on total number of Laboratory rooms (same for all students)
    $labRoomRes2 = $conn->query("SELECT COUNT(*) AS cnt FROM rooms WHERE room_type = 'Laboratory'");
    $lab_count   = (int)(($labRoomRes2 ? $labRoomRes2->fetch_assoc()['cnt'] : 0) ?? 0);

    $tuition_fee     = $units * 650;
    $miscellaneous   = 6688.00;
    $registration    = 700.00;
    $laboratory_fee  = $lab_count * 1900;
    $energy_fee      = $units * 21 * 3;
    $subtotal        = $tuition_fee + $miscellaneous + $registration + $laboratory_fee + $energy_fee;
    $installment_fee = $has_installment ? 750.00 : 0.00;
    $total           = $subtotal - $discount + $installment_fee;

    // ── FORCE update tuition_fees (no conditional guards) ───────────────────
    $stmt = $conn->prepare("
        INSERT INTO tuition_fees
            (student_id, units, tuition_fee, miscellaneous_fee, registration_fee,
             laboratory_fee, energy_fee, subtotal, discount, installment_fee, total_assessment)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            units             = VALUES(units),
            tuition_fee       = VALUES(tuition_fee),
            miscellaneous_fee = VALUES(miscellaneous_fee),
            registration_fee  = VALUES(registration_fee),
            laboratory_fee    = VALUES(laboratory_fee),
            energy_fee        = VALUES(energy_fee),
            subtotal          = VALUES(subtotal),
            discount          = VALUES(discount),
            installment_fee   = VALUES(installment_fee),
            total_assessment  = VALUES(total_assessment)
    ");
    if ($stmt) {
        $stmt->bind_param("iiddddddddd",
            $sid, $units, $tuition_fee, $miscellaneous, $registration,
            $laboratory_fee, $energy_fee, $subtotal, $discount, $installment_fee, $total
        );
        $stmt->execute();
        $stmt->close();
    }

    // ── Update payment_schedules — recalculate installment amounts ───────────
    $ptype = $has_installment ? 'installment' : 'full';

    if ($has_installment) {
        // ── Read actual paid amounts per period ─────────────────────────────
        $paidRes = $conn->query("SELECT exam_period, COALESCE(SUM(amount),0) AS paid FROM installment_payments WHERE student_id=$sid GROUP BY exam_period");
        $paidMap = [];
        if ($paidRes) { while ($r = $paidRes->fetch_assoc()) $paidMap[$r['exam_period']] = (float)$r['paid']; }

        $dp_paid = $paidMap['Downpayment'] ?? 0;
        $p_paid  = $paidMap['Prelim']      ?? 0;
        $m_paid  = $paidMap['Midterm']     ?? 0;
        $f_paid  = $paidMap['Finals']      ?? 0;

        // DP scheduled = total/4; if student already paid more, use actual paid as DP credit
        $dp_scheduled = round($total / 4, 2);
        $dp_credit    = $dp_paid > 0 ? $dp_paid : $dp_scheduled;

        // Remaining balance after DP
        $remaining = max(0, $total - $dp_credit);
        $pd = ceil($remaining / 3 * 100) / 100;  // prelim due
        $md = $pd;                                 // midterm due
        $fd = round($remaining - $pd * 2, 2);     // finals gets remainder

        $p_status = $p_paid <= 0 ? 'unpaid'  : ($p_paid >= $pd ? 'paid' : 'partial');
        $m_status = $m_paid <= 0 ? 'locked'  : ($m_paid >= $md ? 'paid' : 'partial');
        $f_status = $f_paid <= 0 ? 'locked'  : ($f_paid >= $fd ? 'paid' : 'partial');

        $conn->query("
            INSERT INTO payment_schedules
                (student_id, payment_type, total_assessment,
                 prelim_due,  prelim_paid,  prelim_status,
                 midterm_due, midterm_paid, midterm_status,
                 finals_due,  finals_paid,  finals_status)
            VALUES ($sid,'$ptype',$total,
                    $pd,$p_paid,'$p_status',
                    $md,$m_paid,'$m_status',
                    $fd,$f_paid,'$f_status')
            ON DUPLICATE KEY UPDATE
                payment_type     = '$ptype',
                total_assessment = $total,
                prelim_due       = $pd,
                prelim_paid      = $p_paid,
                prelim_status    = '$p_status',
                midterm_due      = $md,
                midterm_paid     = $m_paid,
                midterm_status   = '$m_status',
                finals_due       = $fd,
                finals_paid      = $f_paid,
                finals_status    = '$f_status'
        ");
    } else {
        // Full payment — just update total_assessment
        $conn->query("
            INSERT INTO payment_schedules (student_id, payment_type, total_assessment)
            VALUES ($sid, 'full', $total)
            ON DUPLICATE KEY UPDATE
                payment_type     = 'full',
                total_assessment = $total
        ");
    }
}

// ----------------------------------------------------------------
// ADD/DROP WINDOW MANAGEMENT
// ----------------------------------------------------------------
function ensureAddDropWindowTable($conn) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS add_drop_window (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            start_date DATETIME NOT NULL,
            end_date   DATETIME NOT NULL,
            label      VARCHAR(150) DEFAULT '',
            is_active  TINYINT(1) DEFAULT 1,
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// GET ?action=get_add_drop_window
function getAddDropWindow($conn) {
    ensureAddDropWindowTable($conn);
    $res = $conn->query("SELECT * FROM add_drop_window WHERE is_active=1 ORDER BY id DESC LIMIT 1");
    $window = $res ? $res->fetch_assoc() : null;
    $now = date('Y-m-d H:i:s');
    $isOpen = false;
    if ($window) {
        $isOpen = ($now >= $window['start_date'] && $now <= $window['end_date']);
    }
    echo json_encode([
        'success'  => true,
        'window'   => $window,
        'is_open'  => $isOpen,
        'now'      => $now,
    ]);
}

// POST ?action=set_add_drop_window
// Body: { start_date, end_date, label }
function setAddDropWindow($conn, $data) {
    ensureAddDropWindowTable($conn);
    $start = trim($data['start_date'] ?? '');
    $end   = trim($data['end_date']   ?? '');
    $label = $conn->real_escape_string(trim($data['label'] ?? ''));

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

    $startEsc = $conn->real_escape_string($start);
    $endEsc   = $conn->real_escape_string($end);
    $conn->query("INSERT INTO add_drop_window (start_date, end_date, label, is_active) VALUES ('$startEsc','$endEsc','$label',1)");

    if ($conn->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Add/Drop window updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save window']);
    }
}