<?php
// Prevent HTML error output from breaking JSON - must be FIRST line
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'sia_db');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}
$conn->set_charset("utf8mb4");

// Buffer all output. At the end we flush ONLY the intended JSON.
// This eliminates any stray characters from MySQL warnings, notices, etc.
ob_start();

// Prevent PHP errors from outputting HTML that breaks JSON responses
error_reporting(0);
ini_set('display_errors', 0);
// Disable mysqli exception throwing so errors are handled manually
mysqli_report(MYSQLI_REPORT_OFF);

// Ensure payment_method column exists
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS payment_method VARCHAR(20) NOT NULL DEFAULT 'GCash' AFTER approval_status");

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        switch ($action) {
            case 'get_profile':        getProfile($conn);          break;
            case 'get_schedule':       getSchedule($conn);         break;
            case 'get_courses':        getAvailableCourses($conn); break;
            case 'get_enrollments':    getEnrollments($conn);      break;
            case 'get_payment_status':    getPaymentStatus($conn);       break;
            case 'get_enrollment_summary': getEnrollmentSummary($conn);  break;
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
            case 'register_student':   registerStudent($conn, $data);   break;
            case 'enroll_course':      enrollCourse($conn, $data);      break;
            case 'update_payment':     updatePayment($conn, $data);     break;
            case 'approve_enrollment': approveEnrollment($conn, $data); break;
            case 'auto_enroll_all':    autoEnrollAll($conn, $data);    break;
            case 'update_profile':     updateProfile($conn, $data);    break;
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
// Get buffered output, extract only the JSON part (strip any stray chars)
$buffered = ob_get_clean();
// Find the last complete JSON object in the output
$lastBrace = strrpos($buffered, '}');
if ($lastBrace !== false) {
    // Find matching opening brace
    $depth = 0;
    $start = 0;
    for ($i = $lastBrace; $i >= 0; $i--) {
        if ($buffered[$i] === '}') $depth++;
        elseif ($buffered[$i] === '{') { $depth--; if ($depth === 0) { $start = $i; break; } }
    }
    echo substr($buffered, $start, $lastBrace - $start + 1);
} else {
    echo $buffered;
}
$conn->close();

// ─────────────────────────────────────────────────────────────
// HELPER: resolve student id from ?student_id=X or ?user_id=X
// ─────────────────────────────────────────────────────────────
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
    $creditedIds = [];
    $torQ = $conn->prepare("SELECT credited_course_ids FROM tor_evaluations WHERE student_id = ? AND status = 'Evaluated' LIMIT 1");
    $torQ->bind_param("i", $student_id);
    $torQ->execute();
    $torRow = $torQ->get_result()->fetch_assoc();
    if ($torRow && !empty($torRow['credited_course_ids'])) {
        $dec = json_decode($torRow['credited_course_ids'], true);
        if (is_array($dec)) $creditedIds = array_map('intval', $dec);
    }
    $excludeSql = !empty($creditedIds) ? 'AND c.id NOT IN (' . implode(',', $creditedIds) . ')' : '';

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
        echo json_encode([
            'success'        => false,
            'message'        => 'Student record already exists.',
            'student_id'     => (int)$existing['id'],
            'student_number' => $existing['student_number'],
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

    // Ensure all extended columns exist
    $conn->query("ALTER TABLE students MODIFY COLUMN student_type ENUM('New','Old','Continuing','Returning','Transferee') DEFAULT 'New'");
    $extraCols = [
        "middle_name VARCHAR(100) DEFAULT ''",
        "suffix VARCHAR(20) DEFAULT ''",
        "lrn_no VARCHAR(50) DEFAULT ''",
        "sex ENUM('Male','Female','') DEFAULT ''",
        "religion VARCHAR(100) DEFAULT ''",
        "age VARCHAR(10) DEFAULT ''",
        "place_of_birth VARCHAR(255) DEFAULT ''",
        "citizenship VARCHAR(100) DEFAULT ''",
        "mother_tongue VARCHAR(100) DEFAULT ''",
        "is_indigenous TINYINT(1) DEFAULT 0",
        "psa_birth_cert_no VARCHAR(100) DEFAULT ''",
        "has_special_needs TINYINT(1) DEFAULT 0",
        "special_needs_details VARCHAR(255) DEFAULT ''",
        "has_assistive_tech TINYINT(1) DEFAULT 0",
        "assistive_tech_details VARCHAR(255) DEFAULT ''",
        "strand VARCHAR(100) DEFAULT ''",
        "learning_delivery VARCHAR(100) DEFAULT ''",
        "last_school_attended VARCHAR(255) DEFAULT ''",
        "guardian_name VARCHAR(255) DEFAULT ''",
        "guardian_address VARCHAR(255) DEFAULT ''",
        "guardian_contact VARCHAR(50) DEFAULT ''",
        "student_category VARCHAR(50) DEFAULT ''",
        "is_scholar TINYINT(1) DEFAULT 0",
        "scholar_type VARCHAR(100) DEFAULT ''",
        "scholar_grantor VARCHAR(255) DEFAULT ''",
        "scholarship_amount DECIMAL(10,2) DEFAULT 0",
        "payment_method VARCHAR(20) NOT NULL DEFAULT 'GCash'",
        "payment_plan ENUM('full','installment') NOT NULL DEFAULT 'full'",
        "semester VARCHAR(100) DEFAULT ''",
    ];
    foreach ($extraCols as $col) {
        $colName = explode(' ', $col)[0];
        $conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS $col");
    }

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
    echo json_encode(['success' => true, 'message' => 'Enrollment approved']);
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

    // Get student info
    $stmt = $conn->prepare("SELECT s.*, u.email AS user_email FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ? LIMIT 1");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $s = $stmt->get_result()->fetch_assoc();
    if (!$s) { echo json_encode(['success' => false, 'message' => 'Student not found']); return; }

    // Get enrolled courses
    $cStmt = $conn->prepare("
        SELECT c.code, c.name, c.credits, c.instructor, c.day, c.time, c.room, c.semester, e.status
        FROM enrollments e JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = ? AND e.status IN ('Pending','Enrolled')
        ORDER BY c.code
    ");
    $cStmt->bind_param("i", $student_id);
    $cStmt->execute();
    $cResult = $cStmt->get_result();
    $courses = [];
    $totalCredits = 0;
    while ($r = $cResult->fetch_assoc()) {
        $courses[] = $r;
        $totalCredits += (int)$r['credits'];
    }

    // Get payment info
    $pStmt = $conn->prepare("SELECT payment_status, payment_method, gcash_reference, gcash_amount, gcash_date, scholarship_amount, is_scholar FROM students WHERE id = ? LIMIT 1");
    $pStmt->bind_param("i", $student_id);
    $pStmt->execute();
    $p = $pStmt->get_result()->fetch_assoc();

    // Detect semester from enrolled courses
    $semStmt = $conn->prepare("SELECT c.semester FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE e.student_id = ? AND e.status IN ('Pending','Enrolled') ORDER BY e.created_at DESC LIMIT 1");
    $semStmt->bind_param("i", $student_id);
    $semStmt->execute();
    $semRow = $semStmt->get_result()->fetch_assoc();
    $detectedSemester = $semRow['semester'] ?? ('1st Semester, AY ' . date('Y') . '-' . (date('Y') + 1));

    $tuition = 25000;
    $discount = (float)($p['scholarship_amount'] ?? 0);
    $amountDue = max(0, $tuition - $discount);
    $amountPaid = ($p['payment_status'] === 'Paid') ? (float)($p['gcash_amount'] ?? 0) : 0;

    echo json_encode([
        'success'       => true,
        'enrollmentDate'=> $s['enrollment_date'],
        'semester'      => $detectedSemester,
        'program'       => $s['program'],
        'yearLevel'     => $s['year_level'] ?? '1st Year',
        'totalCourses'  => count($courses),
        'totalCredits'  => $totalCredits,
        'courses'       => $courses,
        'payment'       => [
            'totalFee'       => $tuition,
            'scholarDiscount'=> $discount,
            'amountDue'      => $amountDue,
            'amountPaid'     => $amountPaid,
            'balance'        => max(0, $amountDue - $amountPaid),
            'status'         => $p['payment_status'] ?? 'Pending',
            'method'         => $p['payment_method'] ?? 'GCash',
            'paymentDate'    => $p['gcash_date'] ?? null,
        ],
        'termPayments'  => [
            ['term'=>'Prelim',  'amountDue'=>round($amountDue/3,2), 'amountPaid'=>0, 'paymentDate'=>null, 'status'=>'Unpaid'],
            ['term'=>'Midterm', 'amountDue'=>round($amountDue/3,2), 'amountPaid'=>0, 'paymentDate'=>null, 'status'=>'Unpaid'],
            ['term'=>'Finals',  'amountDue'=>round($amountDue/3,2), 'amountPaid'=>0, 'paymentDate'=>null, 'status'=>'Unpaid'],
        ],
    ]);
}

// ─────────────────────────────────────────────────────────────
// AUTO ENROLL ALL — enroll student in courses matching their program
// Primary:  courses.program = student.program  (direct column match)
// Fallback: program_courses table if it exists
// Safe to call multiple times (skips already-enrolled courses).
// ─────────────────────────────────────────────────────────────
function autoEnrollAll($conn, $data) {
    $student_id = (int)($data['student_id'] ?? 0);
    $semester   = trim($data['semester']    ?? '');

    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'student_id required']);
        return;
    }

    $st = $conn->prepare("SELECT program, student_category, semester FROM students WHERE id = ? LIMIT 1");
    $st->bind_param("i", $student_id);
    $st->execute();
    $student = $st->get_result()->fetch_assoc();
    if (!$student) { echo json_encode(['success' => false, 'message' => 'Student not found']); return; }

    // Use passed semester, or fall back to the student's registered semester
    if ($semester === '' && !empty($student['semester'])) {
        $semester = trim($student['semester']);
    }

    // Extract just the term part (e.g. "1st Semester" from "1st Semester, AY 2026-2027")
    // so we match courses regardless of which school year they were created under
    $semesterTerm = $semester;
    if (preg_match('/^(1st Semester|2nd Semester|Summer)/i', $semester, $m)) {
        $semesterTerm = $m[1];
    }

    $programName = trim($student['program']);
    $enrolled    = 0;
    $enrollDate  = date('Y-m-d');
    $courses     = [];

    // Get credited course IDs to skip (transferees with evaluated TOR)
    $creditedIds = [];
    $torQ = $conn->prepare("SELECT credited_course_ids FROM tor_evaluations WHERE student_id = ? AND status = 'Evaluated' LIMIT 1");
    $torQ->bind_param("i", $student_id);
    $torQ->execute();
    $torRow = $torQ->get_result()->fetch_assoc();
    if ($torRow && !empty($torRow['credited_course_ids'])) {
        $dec = json_decode($torRow['credited_course_ids'], true);
        if (is_array($dec)) $creditedIds = array_map('intval', $dec);
    }
    $creditedExclude = !empty($creditedIds) ? 'AND c.id NOT IN (' . implode(',', $creditedIds) . ')' : '';

    // Remove any previously auto-enrolled credited courses (cleanup)
    if (!empty($creditedIds)) {
        $conn->query("DELETE FROM enrollments WHERE student_id = $student_id AND course_id IN (" . implode(',', $creditedIds) . ")");
    }

    // ── Try program_courses + programs table first ────────────
    $hasPCTable = $conn->query("SHOW TABLES LIKE 'program_courses'")->num_rows > 0;
    $hasPTable  = $conn->query("SHOW TABLES LIKE 'programs'")->num_rows > 0;

    if ($hasPCTable && $hasPTable) {
        $sql = "SELECT c.id, c.name, c.semester FROM program_courses pc
                JOIN programs p  ON pc.program_id = p.id
                JOIN courses   c ON pc.course_id  = c.id
                WHERE (p.name = ? OR p.code = ?)
                  AND c.id NOT IN (SELECT course_id FROM enrollments WHERE student_id = ?)
                  $creditedExclude";
        if ($semesterTerm !== '') {
            $semLike = '%' . $semesterTerm . '%';
            $stmt = $conn->prepare($sql . " AND c.semester LIKE ? LIMIT 40");
            $stmt->bind_param("ssis", $programName, $programName, $student_id, $semLike);
        } else {
            $stmt = $conn->prepare($sql . " LIMIT 40");
            $stmt->bind_param("ssi", $programName, $programName, $student_id);
        }
        $stmt->execute();
        $courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ── Primary: courses.program column ──────────────────────
    if (empty($courses)) {
        $sql = "SELECT id, name, semester FROM courses WHERE program = ?
                AND id NOT IN (SELECT course_id FROM enrollments WHERE student_id = ?)
                $creditedExclude";
        if ($semesterTerm !== '') {
            $semLike = '%' . $semesterTerm . '%';
            $stmt = $conn->prepare($sql . " AND semester LIKE ? LIMIT 40");
            $stmt->bind_param("sis", $programName, $student_id, $semLike);
        } else {
            $stmt = $conn->prepare($sql . " LIMIT 40");
            $stmt->bind_param("si", $programName, $student_id);
        }
        $stmt->execute();
        $courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    foreach ($courses as $course) {
        $useSemester = ($semester !== '') ? $semester : ($course['semester'] ?? '');
        $ins = $conn->prepare("INSERT INTO enrollments (student_id, course_id, enrollment_date, status, semester, notes) VALUES (?, ?, ?, 'Enrolled', ?, 'Auto-enrolled')");
        $ins->bind_param("iiss", $student_id, $course['id'], $enrollDate, $useSemester);
        if ($ins->execute() && $ins->affected_rows > 0) {
            $enrolled++;
            $conn->query("UPDATE courses SET enrolled_count = enrolled_count + 1 WHERE id = " . (int)$course['id']);
        }
    }

    if ($enrolled > 0) {
        $conn->query("UPDATE students SET enrollment_status = 'Enrolled' WHERE id = $student_id");
    }

    echo json_encode([
        'success'  => true,
        'enrolled' => $enrolled,
        'program'  => $programName,
        'message'  => $enrolled > 0
            ? "$enrolled course(s) auto-enrolled for $programName."
            : 'Already enrolled in all available courses for this program.',
    ]);
}

// ─────────────────────────────────────────────────────────────
// UPDATE PROFILE — student edits contact info
// ─────────────────────────────────────────────────────────────
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
?>