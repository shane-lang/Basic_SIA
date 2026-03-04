<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
// FIX R-01: Removed set_error_handler that threw exceptions on every PHP notice.
// Use a safe exception handler only for truly unexpected exceptions.
set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error. Please try again.']);
    exit();
});
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

// Fee config helper — reads rates from fee_config table (managed by Accounting)
if (!function_exists('loadFeeConfig')) {
function loadFeeConfig(mysqli $conn, string $category): array {
    $cnt = (int)($conn->query("SELECT COUNT(*) AS c FROM fee_config")->fetch_assoc()['c'] ?? 0);
    if ($cnt === 0) { // table might not exist yet — just return defaults
        return [];
    }
    $cat = $conn->real_escape_string($category);
    $res = $conn->query("SELECT * FROM fee_config WHERE category='$cat' AND is_active=1 ORDER BY sort_order");
    $cfg = [];
    if ($res) while ($r = $res->fetch_assoc()) $cfg[$r['fee_key']] = $r;
    return $cfg;
}
}

$action = $_GET['action'] ?? '';

require_once __DIR__ . '/auth_middleware.php';
// Actions called during enrollment wizard (no token yet)
$publicActions = [
    'upload_tor_file', 'submit_tor', 'get_program_courses', 'get_tor_evaluation',
];
$authUser = in_array($action, $publicActions) ? null : requireAuth($conn);

// Schema managed by migrate.php — no DDL at request time

$method = $_SERVER['REQUEST_METHOD'];

// Read request body — Angular sends JSON via http.post()
$raw  = file_get_contents('php://input');
$data = ($raw && $raw !== '') ? json_decode($raw, true) : null;
// Fallback: form-encoded POST or GET params
if (!is_array($data) || empty($data)) {
    $merged = array_merge($_GET, $_POST);
    unset($merged['action']);
    $data = !empty($merged) ? $merged : [];
}
if (!is_array($data)) $data = [];
// Accept action from body too
if (!$action && isset($data['action'])) $action = $data['action'];

// Handle file upload (multipart)
if ($action === 'upload_tor_file')  { uploadTorFile($conn);    exit(); }
if ($action === 'upload_document') { uploadDocument($conn);   exit(); }

// Read-only actions (GET)
switch ($action) {
    case 'get_pending_tor':        getPendingTOR($conn);               exit();
    case 'get_lab_room_count':
        $cnt = (int)($conn->query("SELECT COUNT(*) AS c FROM rooms WHERE room_type='Laboratory'")->fetch_assoc()['c'] ?? 0);
        echo json_encode(['success' => true, 'count' => $cnt]);
        exit();
    case 'get_evaluated_tor':      getEvaluatedTOR($conn);             exit();
    case 'get_tor_evaluation':     getTORForStudent($conn);            exit();
    case 'get_program_courses':    getProgramCourses($conn);           exit();
    case 'get_student_curriculum': getStudentCurriculum($conn);        exit();
    case 'debug_tor':              debugTOR($conn);                    exit();
    // ── Grade Submission Student Listing ──
    case 'get_grade_students':     getGradeStudents($conn);            exit();
    case 'get_grade_student_detail': getGradeStudentDetail($conn);     exit();
    case 'get_grade_courses':      getGradeCourses($conn);             exit();
    case 'get_course_students':    getCourseStudents($conn);           exit();
}

// Write actions (POST body required)
switch ($action) {
    case 'submit_tor':   submitTOR($conn, $data);    exit();
    case 'evaluate_tor': evaluateTOR($conn, $data);  exit();
    case 'reject_tor':   rejectTOR($conn, $data);    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown action: '.$method.'/'.$action]);
$conn->close();

// ================================================================
// GRADE SUBMISSION — STUDENT THUMBNAIL/LIST VIEW
// ================================================================

/**
 * GET ?action=get_grade_students
 * Returns enrolled students with grade completion summary.
 * Supports: q (search), program, year_level, semester, view_mode (thumbnail|list), page, limit
 */
function getGradeStudents($conn) {
    $page      = max(1, (int)($_GET['page']       ?? 1));
    $limit     = min(100, max(10, (int)($_GET['limit'] ?? 24)));
    $offset    = ($page - 1) * $limit;
    $search    = trim($_GET['q']          ?? '');
    $program   = trim($_GET['program']    ?? '');
    $yearLevel = trim($_GET['year_level'] ?? '');
    $semester  = trim($_GET['semester']   ?? '');
    $status    = trim($_GET['status']     ?? 'Enrolled');

    $where  = ['s.enrollment_status IN ("Enrolled","Pending")'];
    $params = [];
    $types  = '';

    if ($search) {
        $sq = '%' . $conn->real_escape_string($search) . '%';
        $where[] = "(s.student_number LIKE '$sq' OR s.first_name LIKE '$sq' OR s.last_name LIKE '$sq' OR CONCAT(s.first_name,' ',s.last_name) LIKE '$sq')";
    }
    if ($program)   { $where[] = 's.program = ?';    $params[] = $program;   $types .= 's'; }
    if ($yearLevel) { $where[] = 's.year_level = ?'; $params[] = $yearLevel; $types .= 's'; }
    if ($semester)  { $where[] = 's.semester = ?';   $params[] = $semester;  $types .= 's'; }

    $whereStr = implode(' AND ', $where);

    // Count
    $countSql  = "SELECT COUNT(DISTINCT s.id) AS total FROM students s WHERE $whereStr";
    $countStmt = $conn->prepare($countSql);
    if ($params) $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $total = (int)$countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    // Data with grade completion stats — use prelim_grade/midterm_grade/final_grade directly from enrollments
    $dataSql = "
        SELECT s.id, s.student_number, s.first_name, s.last_name,
               s.program, s.year_level, s.semester, s.enrollment_status,
               s.phone,
               COUNT(DISTINCT e.id) AS total_subjects,
               SUM(CASE WHEN e.prelim_grade  IS NOT NULL THEN 1 ELSE 0 END) AS prelim_done,
               SUM(CASE WHEN e.midterm_grade IS NOT NULL THEN 1 ELSE 0 END) AS midterm_done,
               SUM(CASE WHEN e.final_grade   IS NOT NULL THEN 1 ELSE 0 END) AS final_done
        FROM students s
        LEFT JOIN enrollments e ON e.student_id = s.id AND e.status IN ('Enrolled','Pending')
        WHERE $whereStr
        GROUP BY s.id
        ORDER BY s.last_name, s.first_name
        LIMIT ? OFFSET ?
    ";
    $allP = array_merge($params, [$limit, $offset]);
    $allT = $types . 'ii';
    $dataStmt = $conn->prepare($dataSql);
    $dataStmt->bind_param($allT, ...$allP);
    $dataStmt->execute();
    $res      = $dataStmt->get_result();
    $students = [];
    while ($r = $res->fetch_assoc()) {
        $total_s  = (int)$r['total_subjects'];
        $students[] = [
            'id'              => (int)$r['id'],
            'studentNumber'   => $r['student_number'],
            'firstName'       => $r['first_name'],
            'lastName'        => $r['last_name'],
            'fullName'        => $r['first_name'] . ' ' . $r['last_name'],
            'program'         => $r['program'],
            'yearLevel'       => $r['year_level'],
            'semester'        => $r['semester'],
            'status'          => $r['enrollment_status'],
            'contactNumber'   => $r['phone'] ?? '',
            'totalSubjects'   => $total_s,
            'prelimDone'      => (int)$r['prelim_done'],
            'midtermDone'     => (int)$r['midterm_done'],
            'finalDone'       => (int)$r['final_done'],
            'gradeCompletion' => $total_s > 0 ? round(((int)$r['prelim_done'] + (int)$r['midterm_done'] + (int)$r['final_done']) / ($total_s * 3) * 100) : 0,
            // Initials for avatar
            'initials'        => strtoupper(substr($r['first_name'], 0, 1) . substr($r['last_name'], 0, 1)),
        ];
    }
    $dataStmt->close();

    echo json_encode([
        'success'    => true,
        'students'   => $students,
        'total'      => $total,
        'page'       => $page,
        'limit'      => $limit,
        'totalPages' => (int)ceil($total / $limit),
    ]);
}

/**
 * GET ?action=get_grade_student_detail&student_id=X
 * Full grade sheet for a student — used when registrar clicks on a card/row.
 */
function getGradeStudentDetail($conn) {
    $sid = (int)($_GET['student_id'] ?? 0);
    if (!$sid) { echo json_encode(['success'=>false,'message'=>'student_id required']); return; }

    $sRes = $conn->query("SELECT * FROM students WHERE id=$sid LIMIT 1");
    $student = $sRes ? $sRes->fetch_assoc() : null;
    if (!$student) { echo json_encode(['success'=>false,'message'=>'Student not found']); return; }

    $res = $conn->query("
        SELECT e.id AS enrollment_id, e.semester, e.status,
               c.id AS course_id, c.code, c.name, c.credits, c.instructor,
               e.prelim_grade  AS prelim,
               e.midterm_grade AS midterm,
               e.final_grade   AS final,
               NULL AS prelim_at, NULL AS midterm_at, NULL AS final_at
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = $sid AND e.status IN ('Enrolled','Pending','Completed')
        ORDER BY c.code ASC
    ");

    $subjects = [];
    while ($r = $res->fetch_assoc()) {
        $prelim  = $r['prelim']  !== null ? (float)$r['prelim']  : null;
        $midterm = $r['midterm'] !== null ? (float)$r['midterm'] : null;
        $final   = $r['final']   !== null ? (float)$r['final']   : null;
        $vals    = array_filter([$prelim,$midterm,$final], fn($v) => $v !== null);
        $overall = count($vals) > 0 ? round(array_sum($vals) / count($vals), 2) : null;
        $remarks = $final !== null ? ($overall <= 3.0 ? 'Passed' : 'Failed') : 'In Progress';
        $subjects[] = [
            'enrollmentId' => (int)$r['enrollment_id'],
            'courseId'     => (int)$r['course_id'],
            'code'         => $r['code'],
            'name'         => $r['name'],
            'credits'      => (int)$r['credits'],
            'instructor'   => $r['instructor'] ?? '',
            'semester'     => $r['semester'] ?? '',
            'prelim'       => $prelim,
            'midterm'      => $midterm,
            'final'        => $final,
            'overall'      => $overall,
            'remarks'      => $remarks,
            'prelimAt'     => $r['prelim_at']  ?? null,
            'midtermAt'    => $r['midterm_at'] ?? null,
            'finalAt'      => $r['final_at']   ?? null,
        ];
    }

    echo json_encode([
        'success'  => true,
        'student'  => $student,
        'subjects' => $subjects,
        'initials' => strtoupper(substr($student['first_name'],0,1).substr($student['last_name'],0,1)),
    ]);
}

/**
 * GET ?action=get_grade_courses
 * Returns courses with enrolled student count + grade completion stats.
 * Used as an alternative entry point: pick course → see students.
 */
function getGradeCourses($conn) {
    $semester = trim($_GET['semester'] ?? '');
    $program  = trim($_GET['program']  ?? '');

    $where = ['e.status IN ("Enrolled","Pending")'];
    if ($semester) $where[] = "e.semester='".$conn->real_escape_string($semester)."'";
    if ($program)  $where[] = "c.program='".$conn->real_escape_string($program)."'";
    $whereStr = implode(' AND ', $where);

    $res = $conn->query("
        SELECT c.id, c.code, c.name, c.instructor, c.program, c.credits,
               COUNT(DISTINCT e.student_id) AS enrolled_count,
               SUM(CASE WHEN e.prelim_grade  IS NOT NULL THEN 1 ELSE 0 END) AS prelim_done,
               SUM(CASE WHEN e.midterm_grade IS NOT NULL THEN 1 ELSE 0 END) AS midterm_done,
               SUM(CASE WHEN e.final_grade   IS NOT NULL THEN 1 ELSE 0 END) AS final_done
        FROM courses c
        JOIN enrollments e ON e.course_id = c.id
        WHERE $whereStr
        GROUP BY c.id
        ORDER BY c.code ASC
    ");
    $courses = [];
    while ($r = $res->fetch_assoc()) {
        $enrolled = (int)$r['enrolled_count'];
        $courses[] = [
            'id'           => (int)$r['id'],
            'code'         => $r['code'],
            'name'         => $r['name'],
            'instructor'   => $r['instructor'] ?? '',
            'program'      => $r['program'],
            'credits'      => (int)$r['credits'],
            'enrolledCount'=> $enrolled,
            'prelimDone'   => (int)$r['prelim_done'],
            'midtermDone'  => (int)$r['midterm_done'],
            'finalDone'    => (int)$r['final_done'],
            'gradeCompletion' => $enrolled > 0 ? round(((int)$r['prelim_done']+(int)$r['midterm_done']+(int)$r['final_done'])/($enrolled*3)*100) : 0,
        ];
    }
    echo json_encode(['success'=>true,'courses'=>$courses]);
}

/**
 * GET ?action=get_course_students&course_id=X
 * Returns all enrolled students in a specific course with their grades.
 */
function getCourseStudents($conn) {
    $cid = (int)($_GET['course_id'] ?? 0);
    if (!$cid) { echo json_encode(['success'=>false,'message'=>'course_id required']); return; }

    $cRes   = $conn->query("SELECT * FROM courses WHERE id=$cid LIMIT 1");
    $course = $cRes ? $cRes->fetch_assoc() : null;
    if (!$course) { echo json_encode(['success'=>false,'message'=>'Course not found']); return; }

    $res = $conn->query("
        SELECT s.id AS student_id, s.student_number, s.first_name, s.last_name,
               s.program, s.year_level, s.enrollment_status,
               e.id AS enrollment_id, e.semester,
               e.prelim_grade  AS prelim,
               e.midterm_grade AS midterm,
               e.final_grade   AS final
        FROM enrollments e
        JOIN students s ON s.id = e.student_id
        WHERE e.course_id = $cid AND e.status IN ('Enrolled','Pending')
        ORDER BY s.last_name, s.first_name
    ");

    $students = [];
    while ($r = $res->fetch_assoc()) {
        $prelim  = $r['prelim']  !== null ? (float)$r['prelim']  : null;
        $midterm = $r['midterm'] !== null ? (float)$r['midterm'] : null;
        $final   = $r['final']   !== null ? (float)$r['final']   : null;
        $vals    = array_filter([$prelim,$midterm,$final], fn($v) => $v !== null);
        $overall = count($vals) > 0 ? round(array_sum($vals)/count($vals), 2) : null;
        $students[] = [
            'studentId'    => (int)$r['student_id'],
            'enrollmentId' => (int)$r['enrollment_id'],
            'studentNumber'=> $r['student_number'],
            'firstName'    => $r['first_name'],
            'lastName'     => $r['last_name'],
            'fullName'     => $r['first_name'].' '.$r['last_name'],
            'program'      => $r['program'],
            'yearLevel'    => $r['year_level'],
            'semester'     => $r['semester'],
            'initials'     => strtoupper(substr($r['first_name'],0,1).substr($r['last_name'],0,1)),
            'prelim'       => $prelim,
            'midterm'      => $midterm,
            'final'        => $final,
            'overall'      => $overall,
            'remarks'      => $final !== null ? ($overall <= 3.0 ? 'Passed' : 'Failed') : 'In Progress',
        ];
    }
    echo json_encode(['success'=>true,'course'=>$course,'students'=>$students]);
}


// ─────────────────────────────────────────────────────────────
// DEBUG: GET ?action=debug_tor&student_id=XX
// Dumps all intermediate values used in evaluateTOR computation
// ─────────────────────────────────────────────────────────────
function debugTOR($conn) {
    $student_id = (int)($_GET['student_id'] ?? 0);
    if (!$student_id) { echo json_encode(['error' => 'student_id required']); return; }

    $out = ['student_id' => $student_id];

    // 1. Student row
    $st = $conn->query("SELECT program, semester, year_level, payment_plan, tor_eval_status, enrollment_status FROM students WHERE id=$student_id LIMIT 1");
    $stRow = $st ? $st->fetch_assoc() : null;
    $out['student'] = $stRow;

    if (!$stRow) { echo json_encode($out); return; }

    $pn      = $conn->real_escape_string($stRow['program'] ?? '');
    $sem_raw = trim($stRow['semester'] ?? '');
    $yl      = $conn->real_escape_string($stRow['year_level'] ?? '1st Year');

    // 2. Sem/year filter construction
    preg_match('/^(1st Semester|2nd Semester|Summer)/i', $sem_raw, $sm);
    $semTerm        = $conn->real_escape_string($sm[1] ?? $sem_raw);
    $semFilter      = $semTerm ? "AND c.semester LIKE '$semTerm%'" : '';
    $semFilterPlain = $semTerm ? "AND semester LIKE '$semTerm%'" : '';
    $ylFilter       = $yl ? "AND c.year_level = '$yl'" : '';
    $ylFilterPlain  = $yl ? "AND year_level = '$yl'" : '';
    $out['filters'] = compact('pn','sem_raw','semTerm','yl','ylFilter','semFilter','ylFilterPlain','semFilterPlain');

    // 3. Tables existence
    $out['tables'] = [
        'program_courses' => $conn->query("SHOW TABLES LIKE 'program_courses'")->num_rows > 0,
        'programs'        => $conn->query("SHOW TABLES LIKE 'programs'")->num_rows > 0,
        'courses'         => $conn->query("SHOW TABLES LIKE 'courses'")->num_rows > 0,
    ];

    // 4. Source 1: program_courses JOIN
    $sql1 = "SELECT COALESCE(SUM(c.credits),0) AS u FROM program_courses pc JOIN programs pr ON pc.program_id=pr.id JOIN courses c ON pc.course_id=c.id WHERE (pr.name='$pn' OR pr.code='$pn') $ylFilter $semFilter";
    $r1 = $conn->query($sql1);
    $out['source1_sql']    = $sql1;
    $out['source1_error']  = $conn->error ?: null;
    $out['source1_units']  = $r1 ? (int)$r1->fetch_assoc()['u'] : null;

    // 5. Source 2: courses.program direct
    $sql2 = "SELECT COALESCE(SUM(credits),0) AS u FROM courses WHERE program='$pn' $ylFilterPlain $semFilterPlain";
    $r2 = $conn->query($sql2);
    $out['source2_sql']    = $sql2;
    $out['source2_error']  = $conn->error ?: null;
    $out['source2_units']  = $r2 ? (int)$r2->fetch_assoc()['u'] : null;

    // 6. All program values in courses table
    $progs = $conn->query("SELECT DISTINCT program, year_level, semester FROM courses WHERE program LIKE '%$pn%' OR program LIKE '%IT%' OR program LIKE '%Information%' LIMIT 20");
    $out['courses_programs'] = [];
    if ($progs) while ($r = $progs->fetch_assoc()) $out['courses_programs'][] = $r;

    // 7. tor_evaluations row
    $te = $conn->query("SELECT * FROM tor_evaluations WHERE student_id=$student_id ORDER BY id DESC LIMIT 1");
    $out['tor_evaluation'] = $te ? $te->fetch_assoc() : null;

    // 8. tuition_fees row
    $tf = $conn->query("SELECT * FROM tuition_fees WHERE student_id=$student_id LIMIT 1");
    $out['tuition_fees'] = $tf ? $tf->fetch_assoc() : null;

    echo json_encode($out, JSON_PRETTY_PRINT);
}

// ─────────────────────────────────────────────────────────────
// STUDENT: Upload TOR file and create tor_evaluation record
// POST ?action=upload_tor_file  (multipart/form-data)
// Fields: student_id, tor_file (file)
// ─────────────────────────────────────────────────────────────
function uploadTorFile($conn) {
    $student_id = (int)($_POST['student_id'] ?? 0);
    if (!$student_id) { echo json_encode(['success' => false, 'message' => 'student_id required']); return; }

    // Build upload directory — works on both Windows XAMPP and Linux
    $scriptDir  = dirname($_SERVER['SCRIPT_FILENAME']);
    $uploadDir  = $scriptDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            echo json_encode(['success' => false, 'message' => 'Could not create uploads folder. Create C:\\xampp\\htdocs\\sia-api\\uploads\\ manually.']);
            return;
        }
    }

    $torUrl = '';
    if (!empty($_FILES['tor_file']) && $_FILES['tor_file']['error'] === UPLOAD_ERR_OK) {
        $ext     = strtolower(pathinfo($_FILES['tor_file']['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf','jpg','jpeg','png','gif','webp'];
        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Only PDF and image files allowed.']);
            return;
        }
        $filename = 'tor_' . $student_id . '_' . time() . '.' . $ext;
        $dest     = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['tor_file']['tmp_name'], $dest)) {
            $torUrl = $filename;
        } else {
            echo json_encode(['success' => false, 'message' => 'File move failed. Check folder permissions for ' . $uploadDir]);
            return;
        }
    }

    // Save tor_file path to students table
        if ($torUrl) {
        $stmt = $conn->prepare("UPDATE students SET tor_file = ? WHERE id = ?");
        $stmt->bind_param("si", $torUrl, $student_id);
        $stmt->execute();
    }

    // Upsert tor_evaluation record
    $stmt2 = $conn->prepare("
        INSERT INTO tor_evaluations (student_id, status)
        VALUES (?, 'Pending')
        ON DUPLICATE KEY UPDATE status = IF(status = 'Rejected', 'Pending', status), updated_at = NOW()
    ");
    $stmt2->bind_param("i", $student_id);
    $stmt2->execute();

    $conn->query("UPDATE students SET tor_eval_status = 'Pending' WHERE id = $student_id");

    // FIX R-04: Use configurable base URL (set SIA_UPLOAD_URL in environment or .env)
    $baseUrl  = rtrim(getenv('SIA_UPLOAD_URL') ?: ('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/sia-api/uploads'), '/') . '/';
    $fileUrl  = $torUrl ? ($baseUrl . $torUrl) : '';
    echo json_encode([
        'success'  => true,
        'message'  => 'TOR submitted for Registrar evaluation.',
        'tor_file' => $torUrl,
        'tor_url'  => $fileUrl,
    ]);
}

// ─────────────────────────────────────────────────────────────
// STUDENT: Submit TOR for evaluation (called on enrollment)
// Body: { student_id }
// Creates a pending tor_evaluation record for registrar to fill
// ─────────────────────────────────────────────────────────────
function submitTOR($conn, $data) {
    $student_id = (int)($data['student_id'] ?? 0);
    if (!$student_id) { echo json_encode(['success' => false, 'message' => 'student_id required']); return; }

    // Upsert tor_evaluation record
    $stmt = $conn->prepare("
        INSERT INTO tor_evaluations (student_id, status)
        VALUES (?, 'Pending')
        ON DUPLICATE KEY UPDATE status = IF(status = 'Rejected', 'Pending', status), updated_at = NOW()
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();

    // Mark student as transferee awaiting eval
    $conn->query("UPDATE students SET tor_eval_status = 'Pending' WHERE id = $student_id");

    echo json_encode(['success' => true, 'message' => 'TOR submitted for evaluation.']);
}

// ─────────────────────────────────────────────────────────────
// HELPER: Compute total program units live (semester + year filtered)
// Used by getPendingTOR, getTORForStudent — replaces stale tuition_fees.units.
// ─────────────────────────────────────────────────────────────
function computeProgramUnitsLive(mysqli $conn, string $programName, string $yearLevel, string $semester): int {
    $pn  = $conn->real_escape_string($programName);
    $yl  = $conn->real_escape_string($yearLevel);

    $semFilter = ''; $sfNoJoin = '';
    if ($semester !== '') {
        preg_match('/^(1st Semester|2nd Semester|Summer)/i', $semester, $sm);
        $semTerm   = $conn->real_escape_string($sm[1] ?? $semester);
        $semFilter = "AND c.semester LIKE '$semTerm%'";
        $sfNoJoin  = "AND semester LIKE '$semTerm%'";
    }
    // Normalize year_level format: 'Year 1' → '1st Year', etc.
    // Some legacy course records use the 'Year N' format instead of 'Nth Year'.
    // The DB fix in migrate.php corrects this at rest, but we also handle it
    // here defensively so queries never miss courses due to format mismatch.
    $ylNormMap = [
        'Year 1' => '1st Year', 'Year 2' => '2nd Year',
        'Year 3' => '3rd Year', 'Year 4' => '4th Year', 'Year 5' => '5th Year',
        '1st Year' => '1st Year', '2nd Year' => '2nd Year',
        '3rd Year' => '3rd Year', '4th Year' => '4th Year', '5th Year' => '5th Year',
    ];
    $ylNorm = $ylNormMap[$yearLevel] ?? $yearLevel;
    $yl     = $conn->real_escape_string($ylNorm);

    // Use CASE-insensitive year_level match covering both 'Year 1' and '1st Year' formats
    $ylFilter   = ($yl !== '') ? "AND (c.year_level = '$yl' OR c.year_level = '" . $conn->real_escape_string($yearLevel) . "')" : '';
    $ylFilterNJ = ($yl !== '') ? "AND (year_level = '$yl' OR year_level = '" . $conn->real_escape_string($yearLevel) . "')" : '';

    // Source 1: program_courses junction table
    $res    = $conn->query("SELECT COALESCE(SUM(c.credits),0) AS u
        FROM program_courses pc
        JOIN programs p ON pc.program_id=p.id
        JOIN courses c  ON pc.course_id=c.id
        WHERE (p.name='$pn' OR p.code='$pn') $ylFilter $semFilter");
    $units1 = (int)(($res ? $res->fetch_assoc()['u'] : 0) ?: 0);

    // Source 2: courses.program direct column
    // FIX: Always run BOTH sources and take the MAX.
    // Some courses may be linked in the junction table but others may only
    // exist via courses.program (e.g. courses added before program_courses was
    // populated, or courses whose program_courses link was accidentally deleted).
    // Using only source 1 as primary + source 2 as fallback caused under-counting
    // when the junction table was incomplete (e.g. 23 units instead of 26).
    $res2   = $conn->query("SELECT COALESCE(SUM(credits),0) AS u
        FROM courses WHERE program='$pn' $ylFilterNJ $sfNoJoin");
    $units2 = (int)(($res2 ? $res2->fetch_assoc()['u'] : 0) ?: 0);

    // Also try resolved program code (students.program stores full name;
    // courses.program may store short code — check both)
    $pcRow  = $conn->query("SELECT code FROM programs WHERE name='$pn' OR code='$pn' LIMIT 1");
    $pc     = $pcRow && $pcRow->num_rows > 0
        ? $conn->real_escape_string($pcRow->fetch_assoc()['code'])
        : $pn;
    $units3 = 0;
    if ($pc !== $pn) {
        $res3   = $conn->query("SELECT COALESCE(SUM(credits),0) AS u
            FROM courses WHERE program='$pc' $ylFilterNJ $sfNoJoin");
        $units3 = (int)(($res3 ? $res3->fetch_assoc()['u'] : 0) ?: 0);
    }

    $units = max($units1, $units2, $units3);
    return $units > 0 ? $units : 18;
}

// ─────────────────────────────────────────────────────────────
// REGISTRAR: Get all pending TOR evaluations
// GET ?action=get_pending_tor
// ─────────────────────────────────────────────────────────────
function getPendingTOR($conn) {
    $result = $conn->query("
        SELECT
            te.id           AS eval_id,
            te.student_id,
            te.status,
            te.credited_units,
            te.approved_units,
            te.credited_subjects,
            te.registrar_notes,
            te.evaluated_at,
            te.created_at,
            s.student_number,
            s.first_name,
            s.last_name,
            s.program,
            s.year_level,
            s.semester,
            s.last_school_attended,
            s.student_type,
            s.tor_eval_status,
            s.tor_file
        FROM tor_evaluations te
        JOIN students s ON te.student_id = s.id
        WHERE te.status = 'Pending'
        ORDER BY te.created_at ASC
    ");

    $rows = [];
    if ($result) {
        while ($r = $result->fetch_assoc()) {
            $torFileUrl = '';
            if (!empty($r['tor_file'])) {
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $torFileUrl = 'http://' . $host . '/sia-api/uploads/' . $r['tor_file'];
            }
            // Compute programUnits live (total semester units, not cached stale value)
            $programUnits = computeProgramUnitsLive($conn, $r['program'], $r['year_level'], $r['semester'] ?? '');
            $rows[] = [
                'evalId'             => (int)$r['eval_id'],
                'studentId'          => (int)$r['student_id'],
                'studentNumber'      => $r['student_number'],
                'firstName'          => $r['first_name'],
                'lastName'           => $r['last_name'],
                'program'            => $r['program'],
                'yearLevel'          => $r['year_level'],
                'lastSchoolAttended' => $r['last_school_attended'],
                'studentType'        => $r['student_type'],
                'status'             => $r['status'],
                'creditedUnits'      => (int)$r['credited_units'],
                'approvedUnits'      => (int)$r['approved_units'],
                'creditedSubjects'   => $r['credited_subjects'] ? json_decode($r['credited_subjects'], true) : [],
                'registrarNotes'     => $r['registrar_notes'],
                'evaluatedAt'        => $r['evaluated_at'],
                'submittedAt'        => $r['created_at'],
                'programUnits'       => $programUnits,
                'torFileUrl'         => $torFileUrl,
            ];
        }
    }
    echo json_encode(['success' => true, 'evaluations' => $rows]);
}

// ─────────────────────────────────────────────────────────────
// REGISTRAR: Get already-evaluated TOR list (Evaluated + Rejected)
// GET ?action=get_evaluated_tor
// ─────────────────────────────────────────────────────────────
function getEvaluatedTOR($conn) {
    $result = $conn->query("
        SELECT
            te.id           AS eval_id,
            te.student_id,
            te.status,
            te.credited_units,
            te.approved_units,
            te.credited_subjects,
            te.registrar_notes,
            te.evaluated_at,
            te.created_at,
            s.student_number,
            s.first_name,
            s.last_name,
            s.program,
            s.year_level,
            s.last_school_attended
        FROM tor_evaluations te
        JOIN students s ON te.student_id = s.id
        WHERE te.status IN ('Evaluated', 'Rejected')
        ORDER BY te.evaluated_at DESC
        LIMIT 100
    ");

    $rows = [];
    if ($result) {
        while ($r = $result->fetch_assoc()) {
            $rows[] = [
                'evalId'             => (int)$r['eval_id'],
                'studentId'          => (int)$r['student_id'],
                'studentNumber'      => $r['student_number'],
                'firstName'          => $r['first_name'],
                'lastName'           => $r['last_name'],
                'program'            => $r['program'],
                'lastSchoolAttended' => $r['last_school_attended'],
                'status'             => $r['status'],
                'creditedUnits'      => (int)$r['credited_units'],
                'approvedUnits'      => (int)$r['approved_units'],
                'creditedSubjects'   => $r['credited_subjects'] ? json_decode($r['credited_subjects'], true) : [],
                'registrarNotes'     => $r['registrar_notes'],
                'evaluatedAt'        => $r['evaluated_at'],
            ];
        }
    }
    echo json_encode(['success' => true, 'evaluations' => $rows]);
}

// ─────────────────────────────────────────────────────────────
// REGISTRAR: Get TOR evaluation for a specific student
// GET ?action=get_tor_evaluation&student_id=XX
// ─────────────────────────────────────────────────────────────
function getTORForStudent($conn) {
    $student_id = (int)($_GET['student_id'] ?? 0);
    if (!$student_id) { echo json_encode(['success' => false, 'message' => 'student_id required']); return; }

    $res = $conn->query("SELECT te.*, s.student_number, s.first_name, s.last_name, s.program, s.year_level, s.semester
        FROM tor_evaluations te
        JOIN students s ON te.student_id = s.id
        WHERE te.student_id = $student_id LIMIT 1");
    $r = $res ? $res->fetch_assoc() : null;

    if (!$r) { echo json_encode(['success' => false, 'message' => 'No TOR evaluation found for this student']); return; }

    $programUnits = computeProgramUnitsLive($conn, $r['program'], $r['year_level'], $r['semester'] ?? '');

    echo json_encode([
        'success' => true,
        'evaluation' => [
            'evalId'           => (int)$r['id'],
            'studentId'        => (int)$r['student_id'],
            'studentNumber'    => $r['student_number'],
            'studentName'      => $r['first_name'] . ' ' . $r['last_name'],
            'program'          => $r['program'],
            'programUnits'     => $programUnits,
            'status'           => $r['status'],
            'creditedUnits'    => (int)$r['credited_units'],
            'approvedUnits'    => (int)$r['approved_units'],
            'creditedSubjects' => $r['credited_subjects'] ? json_decode($r['credited_subjects'], true) : [],
            'registrarNotes'   => $r['registrar_notes'],
            'evaluatedAt'      => $r['evaluated_at'],
        ]
    ]);
}

// ─────────────────────────────────────────────────────────────
// REGISTRAR: Get program courses (to select which to credit)
// ─────────────────────────────────────────────────────────────
// REGISTRAR: Get all program courses grouped by year & semester
// GET ?action=get_program_courses&program=BS+IT&student_id=XX
// Returns courses with year_level, semester, plus which ones are
// already credited for this student (if student_id provided)
// ─────────────────────────────────────────────────────────────
function getProgramCourses($conn) {
    $program    = trim($_GET['program']    ?? '');
    $student_id = (int)($_GET['student_id'] ?? 0);
    if (!$program) { echo json_encode(['success' => false, 'message' => 'program required']); return; }
    $p = $conn->real_escape_string($program);

    // Normalize semester helper — strips "AY 2024-2025" suffix
    if (!function_exists('normalizeSem')) {
        function normalizeSem($sem) {
            $l = strtolower($sem);
            if (strpos($l, 'summer') !== false || strpos($l, 'midyear') !== false) return 'Summer';
            if (strpos($l, '2nd') !== false || strpos($l, 'second') !== false)     return '2nd Semester';
            return '1st Semester';
        }
    }

    // Normalize year level
    if (!function_exists('normalizeYear')) {
        function normalizeYear($yr) {
            $l = strtolower(trim($yr));
            if (strpos($l, '5') !== false || strpos($l, 'fifth') !== false)  return '5th Year';
            if (strpos($l, '4') !== false || strpos($l, 'fourth') !== false) return '4th Year';
            if (strpos($l, '3') !== false || strpos($l, 'third') !== false)  return '3rd Year';
            if (strpos($l, '2') !== false || strpos($l, 'second') !== false) return '2nd Year';
            return '1st Year';
        }
    }

    $courses = [];
    $seen    = [];

    // Source 1: program_courses junction (authoritative — reflects current program assignments)
    // This is the ONLY reliable source. The fallback via courses.program direct column
    // is intentionally removed because courses.program may still hold a stale program name
    // after a course is unlinked from a program via update_program (admin removes it from
    // the course list). Using the junction table exclusively prevents deleted/removed
    // subjects from reappearing in TOR evaluation views.
    $hasPCTable = $conn->query("SHOW TABLES LIKE 'program_courses'")->num_rows > 0;
    $hasPTable  = $conn->query("SHOW TABLES LIKE 'programs'")->num_rows > 0;

    if ($hasPCTable && $hasPTable) {
        $res = $conn->query("
            SELECT c.id, c.code, c.name, c.credits,
                   IFNULL(NULLIF(TRIM(c.year_level),''), '1st Year') AS year_level,
                   IFNULL(NULLIF(TRIM(c.semester),''), '1st Semester') AS semester,
                   c.description
            FROM program_courses pc
            JOIN programs pr ON pc.program_id = pr.id
            JOIN courses c   ON pc.course_id  = c.id
            WHERE pr.name = '$p' OR pr.code = '$p'
            ORDER BY c.year_level, c.semester, c.code
        ");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                if (isset($seen[$r['id']])) continue;
                $seen[$r['id']] = true;
                $courses[] = $r;
            }
        }
    }

    // Fallback ONLY when program_courses table is unavailable (should not happen in production)
    if (empty($courses)) {
        $res2 = $conn->query("
            SELECT id, code, name, credits,
                   IFNULL(NULLIF(TRIM(year_level),''), '1st Year') AS year_level,
                   IFNULL(NULLIF(TRIM(semester),''), '1st Semester') AS semester,
                   description
            FROM courses
            WHERE program = '$p'
            ORDER BY year_level, semester, code
        ");
        if ($res2) {
            while ($r = $res2->fetch_assoc()) {
                if (isset($seen[$r['id']])) continue;
                $seen[$r['id']] = true;
                $courses[] = $r;
            }
        }
    }

    // Fetch already-credited course IDs for this student
    $credited_ids = [];
    if ($student_id > 0) {
        $te = $conn->query("SELECT credited_subjects FROM tor_evaluations WHERE student_id = $student_id AND status = 'Evaluated' LIMIT 1");
        $te_row = $te ? $te->fetch_assoc() : null;
        if ($te_row && $te_row['credited_subjects']) {
            $subs = json_decode($te_row['credited_subjects'], true);
            if (is_array($subs)) {
                foreach ($subs as $s) { $credited_ids[(int)($s['courseId'] ?? 0)] = true; }
            }
        }
    }

    $out = [];
    foreach ($courses as $r) {
        $yr  = normalizeYear($r['year_level']);
        $sem = normalizeSem($r['semester']);
        $out[] = [
            'courseId'   => (int)$r['id'],
            'code'       => $r['code'],
            'name'       => $r['name'],
            'credits'    => (int)$r['credits'],
            'yearLevel'  => $yr,
            'semester'   => $sem,
            'description'=> $r['description'] ?? '',
            'isCredited' => isset($credited_ids[(int)$r['id']]),
            'selected'   => isset($credited_ids[(int)$r['id']]),
        ];
    }

    echo json_encode(['success' => true, 'courses' => $out, 'total' => count($out)]);
}

// ─────────────────────────────────────────────────────────────
// REGISTRAR: Evaluate TOR — credit subjects, set approved units
// POST ?action=evaluate_tor
// Body: { eval_id, student_id, registrar_user_id,
//         credited_subjects: [{ courseId, code, name, credits, creditedFrom }],
//         registrar_notes? }
// ─────────────────────────────────────────────────────────────
function evaluateTOR($conn, $data) {
    $eval_id       = (int)($data['eval_id']           ?? 0);
    $student_id    = (int)($data['student_id']        ?? 0);
    $registrar_id  = (int)($data['registrar_user_id'] ?? 0);
    $credited_subs = $data['credited_subjects']        ?? [];
    $notes         = trim($data['registrar_notes']    ?? '');

    if (!$eval_id || !$student_id) {
        echo json_encode(['success' => false, 'message' => 'eval_id and student_id required']); return;
    }

    // Sum credited units and build both JSON stores
    $credited_units  = array_sum(array_column($credited_subs, 'credits'));
    $credited_json   = json_encode($credited_subs);
    // Int array of course IDs for fast enrollment skip — e.g. [18,22,24]
    $course_id_ints  = array_values(array_map(fn($s) => (int)$s['courseId'], $credited_subs));
    $course_ids_json = json_encode($course_id_ints);

    // Get student's program, semester, year_level, and existing payment settings
    $st_res  = $conn->query("SELECT program, semester, year_level FROM students WHERE id = $student_id LIMIT 1");
    $st_row  = $st_res ? $st_res->fetch_assoc() : null;
    $pn      = $conn->real_escape_string($st_row['program']    ?? '');
    $sem_raw = trim($st_row['semester']   ?? '');
    $yl      = $conn->real_escape_string($st_row['year_level'] ?? '1st Year');

    // Resolve program code (students.program = full name; courses.program = code)
    $pc_row = $conn->query("SELECT code FROM programs WHERE name = '$pn' OR code = '$pn' LIMIT 1");
    $programCode = ($pc_row && $pc_row->num_rows > 0) ? $pc_row->fetch_assoc()['code'] : $st_row['program'];
    $pc = $conn->real_escape_string($programCode);

    // Get existing discount/installment_fee from tuition_fees (do NOT use cached units)
    $tf_res   = $conn->query("SELECT discount, installment_fee FROM tuition_fees WHERE student_id = $student_id LIMIT 1");
    $tf_row   = $tf_res ? $tf_res->fetch_assoc() : null;
    $discount = (float)($tf_row['discount']        ?? 0);
    $inst_fee = (float)($tf_row['installment_fee'] ?? 0);

    // Compute program_units live — filtered by year_level + semester term only.
    // NEVER use tuition_fees.units as the base: it may be stale (wrong year/sem).
    // Strip AY suffix so courses stored under any school year are matched.
    $semTerm   = '';
    $semFilter = '';
    $semFilterPlain = '';
    if ($sem_raw !== '') {
        preg_match('/^(1st Semester|2nd Semester|Summer)/i', $sem_raw, $sm);
        $semTerm        = $conn->real_escape_string($sm[1] ?? $sem_raw);
        $semFilter      = "AND c.semester LIKE '$semTerm%'";
        $semFilterPlain = "AND semester LIKE '$semTerm%'";
    }
    $ylNormMap2 = [
        'Year 1'=>'1st Year','Year 2'=>'2nd Year','Year 3'=>'3rd Year',
        'Year 4'=>'4th Year','Year 5'=>'5th Year',
    ];
    $ylRaw  = $st_row['year_level'] ?? '1st Year';
    $ylNorm = $ylNormMap2[$ylRaw] ?? $ylRaw;
    $yl     = $conn->real_escape_string($ylNorm);
    $ylOrig = $conn->real_escape_string($ylRaw);

    // Match both normalized ('1st Year') and legacy ('Year 1') formats
    $ylFilter      = ($yl !== '') ? "AND (c.year_level = '$yl' OR c.year_level = '$ylOrig')" : '';
    $ylFilterPlain = ($yl !== '') ? "AND (year_level = '$yl' OR year_level = '$ylOrig')" : '';

    // FIX: Always run ALL sources and take MAX — same logic as computeProgramUnitsLive.
    // Using only source 1 with source 2 as fallback caused under-counting (e.g. 23 vs 26)
    // when some courses existed in courses.program but were missing from program_courses.

    // Source 1: program_courses junction table
    $pu_sql  = "SELECT COALESCE(SUM(c.credits),0) AS u
        FROM program_courses pc
        JOIN programs pr ON pc.program_id=pr.id
        JOIN courses c   ON pc.course_id=c.id
        WHERE (pr.name=? OR pr.code=?) $ylFilter $semFilter";
    $pu_stmt = $conn->prepare($pu_sql);
    $pu_stmt->bind_param("ss", $pn, $pn);
    $pu_stmt->execute();
    $pu1 = (int)(($pu_stmt->get_result()->fetch_assoc()['u'] ?? 0) ?: 0);

    // Source 2: courses.program direct (full name + short code)
    $fb_sql  = "SELECT COALESCE(SUM(credits),0) AS u FROM courses WHERE (program=? OR program=?) $ylFilterPlain $semFilterPlain";
    $fb_stmt = $conn->prepare($fb_sql);
    $fb_stmt->bind_param("ss", $pc, $pn);
    $fb_stmt->execute();
    $pu2 = (int)(($fb_stmt->get_result()->fetch_assoc()['u'] ?? 0) ?: 0);

    $program_units = max($pu1, $pu2);
    if ($program_units <= 0) $program_units = 18;

    // FIX: approved_units = units the student must ENROLL in (and pay for).
    // = current semester's total units MINUS credited units that belong to this semester.
    // We count credited_units that overlap with this semester's courses, not all credited.
    $sem_credited_units = 0;
    if (!empty($course_id_ints) && $semTerm !== '') {
        $ids_str = implode(',', $course_id_ints);
        $scRes = $conn->query("SELECT COALESCE(SUM(credits),0) AS u FROM courses
            WHERE id IN ($ids_str) $semFilterPlain $ylFilterPlain");
        if ($scRes) $sem_credited_units = (int)($scRes->fetch_assoc()['u'] ?? 0);
    }
    // If no semester filter (shouldn't happen), fall back to total credited_units
    if ($semTerm === '') $sem_credited_units = $credited_units;

    $approved_units = max(0, $program_units - $sem_credited_units);

    // ── 1. Upsert tor_evaluations ─────────────────────────────
    // Use INSERT ... ON DUPLICATE KEY UPDATE so it works whether or not
    // a tor_evaluations row already exists for this student/eval_id.
    // FIX R-03: INSERT keyed only on student_id (the UNIQUE column), not eval_id (PK).
    // Using eval_id in the INSERT caused the ON DUPLICATE KEY to fire on the wrong constraint.
    $upsert = $conn->prepare("
        INSERT INTO tor_evaluations
            (student_id, status, credited_units, approved_units,
             credited_subjects, credited_course_ids, registrar_notes,
             evaluated_by, evaluated_at)
        VALUES (?, 'Evaluated', ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            status              = 'Evaluated',
            credited_units      = VALUES(credited_units),
            approved_units      = VALUES(approved_units),
            credited_subjects   = VALUES(credited_subjects),
            credited_course_ids = VALUES(credited_course_ids),
            registrar_notes     = VALUES(registrar_notes),
            evaluated_by        = VALUES(evaluated_by),
            evaluated_at        = NOW()
    ");
    if (!$upsert) {
        echo json_encode(['success'=>false,'message'=>'DB error: '.$conn->error]); return;
    }
    $upsert->bind_param("iiisssi",
        $student_id,
        $credited_units, $approved_units,
        $credited_json, $course_ids_json,
        $notes, $registrar_id);
    $upsert->execute();
    if ($upsert->errno !== 0) {
        echo json_encode(['success'=>false,'message'=>'Failed to save evaluation: '.$upsert->error]); return;
    }

    // ── 2. Update student tor_eval_status ──────────────────────
    $conn->query("UPDATE students SET tor_eval_status = 'Evaluated' WHERE id = $student_id");

    // ── 3. Recompute tuition with approved_units ───────────────
    $u = $approved_units > 0 ? $approved_units : max(1, $program_units - $sem_credited_units);

    // Load fee rates from fee_config table (managed by Accounting)
    $fc_r = loadFeeConfig($conn, 'College');
    $r_tuition  = (float)($fc_r['tuition_rate_per_unit']['value'] ?? 650);
    $r_misc     = (float)($fc_r['misc_fee']['value']              ?? 6688);
    $r_reg      = (float)($fc_r['reg_fee']['value']               ?? 700);
    $r_lab_room = (float)($fc_r['lab_fee_per_room']['value']      ?? 1900);
    $r_energy   = (float)($fc_r['energy_rate_per_unit']['value']  ?? 63);
    $std_keys_r = ['tuition_rate_per_unit','misc_fee','reg_fee','lab_fee_per_room','energy_rate_per_unit','installment_fee'];
    $extra_r = 0.00;
    foreach ($fc_r as $fk => $frow) {
        if (!in_array($fk, $std_keys_r)) $extra_r += (float)$frow['value'] * ($frow['is_per_unit'] ? $u : 1);
    }

    $tuition_fee = $u * $r_tuition;
    $misc_fee    = $r_misc;
    $reg_fee     = $r_reg;
    $energy_fee  = $u * $r_energy;

    // Lab fee: count Laboratory rooms (same as _buildFees in enrollment.php)
    $labRoomRes = $conn->query("SELECT COUNT(*) AS cnt FROM rooms WHERE room_type = 'Laboratory'");
    $lab_cnt    = (int)(($labRoomRes ? $labRoomRes->fetch_assoc()['cnt'] : 0) ?? 0);
    $lab_fee    = $lab_cnt * $r_lab_room;
    $subtotal   = $tuition_fee + $misc_fee + $reg_fee + $lab_fee + $energy_fee + $extra_r;
    $total      = max(0, $subtotal - $discount + $inst_fee);

    $upd = $conn->prepare("
        INSERT INTO tuition_fees
            (student_id, units, tuition_fee, miscellaneous_fee, registration_fee,
             laboratory_fee, energy_fee, subtotal, discount, installment_fee, total_assessment)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            units=VALUES(units), tuition_fee=VALUES(tuition_fee),
            miscellaneous_fee=VALUES(miscellaneous_fee),
            registration_fee=VALUES(registration_fee),
            laboratory_fee=VALUES(laboratory_fee),
            energy_fee=VALUES(energy_fee), subtotal=VALUES(subtotal),
            discount=VALUES(discount), installment_fee=VALUES(installment_fee),
            total_assessment=VALUES(total_assessment), updated_at=NOW()
    ");
    $upd->bind_param("iiddddddddd",
        $student_id, $u,
        $tuition_fee, $misc_fee, $reg_fee,
        $lab_fee, $energy_fee, $subtotal,
        $discount, $inst_fee, $total);
    $upd->execute();

    // ── 4. Sync credited courses in enrollments ────────────────
    // Strategy: enrollments has UNIQUE KEY(student_id, course_id).
    // We INSERT IGNORE a 'Dropped' row for every credited course so
    // auto_enroll will never re-enroll them (UNIQUE constraint blocks it).
    //
    // FIX: Also DELETE 'Dropped/TOR Credit' rows for courses that were
    // previously credited but are NOW unchecked (removed from credited list).
    // Without this, unchecking a subject in the registrar UI has no effect —
    // the old Dropped row blocks re-enrollment forever.

    $today = date('Y-m-d');

    // Step A: Remove stale TOR-credited Dropped rows for courses NOT in the new list
    if (!empty($course_id_ints)) {
        $ids_str = implode(',', $course_id_ints);
        // Delete Dropped rows that were created by TOR but are no longer in the credited list
        $conn->query("
            DELETE FROM enrollments
            WHERE student_id = $student_id
              AND status = 'Dropped'
              AND notes = 'Credited via TOR evaluation — permanently excluded'
              AND course_id NOT IN ($ids_str)
        ");
    } else {
        // All credits removed — clear ALL TOR-credited Dropped rows
        $conn->query("
            DELETE FROM enrollments
            WHERE student_id = $student_id
              AND status = 'Dropped'
              AND notes = 'Credited via TOR evaluation — permanently excluded'
        ");
    }

    // Step B: Add/update Dropped rows for the current credited list
    if (!empty($course_id_ints)) {
        $ids_str = implode(',', $course_id_ints);

        // Update any existing enrollment rows to Dropped (catches Enrolled rows too)
        $conn->query("
            UPDATE enrollments
            SET    status = 'Dropped',
                   notes  = 'Credited via TOR evaluation — permanently excluded'
            WHERE  student_id = $student_id
              AND  course_id  IN ($ids_str)
        ");

        // Insert Dropped rows for courses not yet in enrollments
        foreach ($course_id_ints as $cid) {
            $conn->query("
                INSERT IGNORE INTO enrollments
                    (student_id, course_id, enrollment_date, status, notes, semester)
                VALUES
                    ($student_id, $cid, '$today', 'Dropped',
                     'Credited via TOR evaluation — permanently excluded',
                     'TOR Credit')
            ");
        }
    }

    echo json_encode([
        'success'       => true,
        'message'       => 'TOR evaluated. Tuition recomputed.',
        'creditedUnits' => $credited_units,
        'approvedUnits' => $approved_units,
        'newUnits'      => $u,
        'newTotal'      => $total,
        'fees' => [
            'units'            => $u,
            'tuitionFee'       => $tuition_fee,
            'miscellaneousFee' => $misc_fee,
            'registrationFee'  => $reg_fee,
            'laboratoryFee'    => $lab_fee,
            'energyFee'        => $energy_fee,
            'subtotal'         => $subtotal,
            'discount'         => $discount,
            'installmentFee'   => $inst_fee,
            'totalAssessment'  => $total,
        ]
    ]);
}

// ─────────────────────────────────────────────────────────────
// REGISTRAR: Reject TOR
// POST ?action=reject_tor
// Body: { eval_id, student_id, registrar_user_id, registrar_notes }
// ─────────────────────────────────────────────────────────────
function rejectTOR($conn, $data) {
    $eval_id      = (int)($data['eval_id']           ?? 0);
    $student_id   = (int)($data['student_id']        ?? 0);
    $registrar_id = (int)($data['registrar_user_id'] ?? 0);
    $notes        = trim($data['registrar_notes']    ?? '');

    if (!$eval_id || !$student_id) {
        echo json_encode(['success' => false, 'message' => 'eval_id and student_id required']); return;
    }

    $stmt = $conn->prepare("UPDATE tor_evaluations SET status='Rejected', registrar_notes=?, evaluated_by=?, evaluated_at=NOW() WHERE id=? AND student_id=?");
    $stmt->bind_param("siii", $notes, $registrar_id, $eval_id, $student_id);
    $stmt->execute();

    $conn->query("UPDATE students SET tor_eval_status = 'Rejected' WHERE id = $student_id");

    echo json_encode(['success' => true, 'message' => 'TOR rejected. Student has been notified.']);
}

// ─────────────────────────────────────────────────────────────
// STUDENT/REGISTRAR: Full curriculum view for a student
// GET ?action=get_student_curriculum&student_id=XX
//
// Returns all program courses grouped by year_level + semester,
// with per-course status: 'Credited' | 'Completed' | 'Enrolled' | 'Pending'
// Used by:
//  - Registrar TOR evaluation modal (full curriculum view)
//  - Student dashboard (see their progress / credited subjects)
// ─────────────────────────────────────────────────────────────
function getStudentCurriculum($conn) {
    $student_id = (int)($_GET['student_id'] ?? 0);
    if (!$student_id) { echo json_encode(['success' => false, 'message' => 'student_id required']); return; }

    // Get student's program
    $st = $conn->query("SELECT program, student_type FROM students WHERE id = $student_id LIMIT 1");
    $st_row = $st ? $st->fetch_assoc() : null;
    if (!$st_row) { echo json_encode(['success' => false, 'message' => 'Student not found']); return; }

    $p = $conn->real_escape_string($st_row['program']);

    // Resolve program code from programs table.
    // students.program may store the full name; courses.program stores the code.
    $pc_row = $conn->query("SELECT code FROM programs WHERE name = '$p' OR code = '$p' LIMIT 1");
    $programCode = ($pc_row && $pc_row->num_rows > 0) ? $pc_row->fetch_assoc()['code'] : $st_row['program'];
    $pc = $conn->real_escape_string($programCode);

    // All program courses — match via junction table (uses pr.name/code)
    // UNION with courses.program using RESOLVED CODE to avoid wrongly-tagged courses
    $result = $conn->query("
        SELECT c.id, c.code, c.name, c.credits, c.year_level, c.semester, c.description
        FROM program_courses pc
        JOIN programs pr ON pc.program_id = pr.id
        JOIN courses c   ON pc.course_id  = c.id
        WHERE pr.name = '$p' OR pr.code = '$p' OR pr.code = '$pc'
        UNION
        SELECT id, code, name, credits, year_level, semester, description
        FROM courses WHERE program = '$pc'
        ORDER BY year_level, semester, code
    ");

    // Get credited course IDs from TOR evaluation
    $credited_ids = [];
    $credited_from = [];
    $te = $conn->query("
        SELECT credited_subjects FROM tor_evaluations
        WHERE student_id = $student_id AND status = 'Evaluated' LIMIT 1
    ");
    $te_row = $te ? $te->fetch_assoc() : null;
    if ($te_row && $te_row['credited_subjects']) {
        $subs = json_decode($te_row['credited_subjects'], true) ?: [];
        foreach ($subs as $s) {
            $cid = (int)$s['courseId'];
            $credited_ids[$cid]  = true;
            $credited_from[$cid] = $s['creditedFrom'] ?? 'Previous School';
        }
    }

    // Get enrollment status per course
    $enrollment_status = [];
    $grades            = [];
    $er = $conn->query("
        SELECT course_id, status, grade FROM enrollments
        WHERE student_id = $student_id
        ORDER BY created_at DESC
    ");
    if ($er) {
        while ($row = $er->fetch_assoc()) {
            $cid = (int)$row['course_id'];
            if (!isset($enrollment_status[$cid])) {
                $enrollment_status[$cid] = $row['status'];
                $grades[$cid]            = $row['grade'];
            }
        }
    }

    $courses = [];
    $seen    = [];
    $total_units    = 0;
    $credited_units = 0;
    $completed_units= 0;
    $enrolled_units = 0;

    if ($result) {
        while ($r = $result->fetch_assoc()) {
            $cid = (int)$r['id'];
            if (isset($seen[$cid])) continue;
            $seen[$cid] = true;

            $is_credited = isset($credited_ids[$cid]);
            $enroll_stat = $enrollment_status[$cid] ?? null;

            // Determine display status
            if ($is_credited) {
                $display_status = 'Credited';
                $credited_units += (int)$r['credits'];
            } elseif ($enroll_stat === 'Completed') {
                $display_status = 'Completed';
                $completed_units += (int)$r['credits'];
            } elseif ($enroll_stat === 'Enrolled' || $enroll_stat === 'Pending') {
                $display_status = 'Enrolled';
                $enrolled_units += (int)$r['credits'];
            } else {
                $display_status = 'Pending';
            }

            $total_units += (int)$r['credits'];

            $courses[] = [
                'courseId'     => $cid,
                'code'         => $r['code'],
                'name'         => $r['name'],
                'credits'      => (int)$r['credits'],
                'yearLevel'    => $r['year_level']  ?: '1st Year',
                'semester'     => $r['semester']    ?: '1st Semester',
                'description'  => $r['description'] ?: '',
                'status'       => $display_status,
                'grade'        => $grades[$cid]     ?? null,
                'creditedFrom' => $credited_from[$cid] ?? null,
            ];
        }
    }

    $remaining_units = $total_units - $credited_units - $completed_units;

    echo json_encode([
        'success' => true,
        'program' => $st_row['program'],
        'summary' => [
            'totalUnits'     => $total_units,
            'creditedUnits'  => $credited_units,
            'completedUnits' => $completed_units,
            'enrolledUnits'  => $enrolled_units,
            'remainingUnits' => $remaining_units,
        ],
        'courses' => $courses,
    ]);
}

// ─────────────────────────────────────────────────────────────
// POST ?action=upload_document  (multipart/form-data)
// Fields: student_id, document_type (e.g. 'form138', 'psa', 'good_moral'),
//         file (binary)
// Used during SHS/TVET enrollment wizard to attach supporting documents
// ─────────────────────────────────────────────────────────────
function uploadDocument($conn) {
    $student_id    = (int)($_POST['student_id']    ?? 0);
    $document_type = trim($_POST['document_type']  ?? 'document');

    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'student_id required']);
        return;
    }

    // Validate document_type to prevent path traversal
    $allowed_types = ['form138', 'psa', 'good_moral', 'birth_cert', 'id_picture',
                      'report_card', 'diploma', 'transcript', 'certificate', 'document'];
    if (!in_array($document_type, $allowed_types)) {
        $document_type = 'document';
    }

    // Build upload directory
    $scriptDir = dirname($_SERVER['SCRIPT_FILENAME']);
    $uploadDir = $scriptDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            echo json_encode(['success' => false, 'message' => 'Could not create uploads folder.']);
            return;
        }
    }

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        // No file uploaded — still return success (document step is optional)
        echo json_encode(['success' => true, 'message' => 'No file uploaded.', 'file_url' => '']);
        return;
    }

    $ext     = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Only PDF and image files allowed.']);
        return;
    }

    $filename = $document_type . '_' . $student_id . '_' . time() . '.' . $ext;
    $dest     = $uploadDir . $filename;

    if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
        echo json_encode(['success' => false, 'message' => 'File move failed. Check folder permissions.']);
        return;
    }

    // Map document_type to the correct students column
    $col_map = [
        'form138'    => 'tor_file',
        'psa'        => 'psa_file',
        'transcript' => 'tor_file',
    ];

    $col = $col_map[$document_type] ?? null;
    if ($col) {
                $stmt = $conn->prepare("UPDATE students SET $col = ? WHERE id = ?");
        $stmt->bind_param("si", $filename, $student_id);
        $stmt->execute();
    }

    // FIX R-04: configurable upload base URL
    $baseUrl = rtrim(getenv('SIA_UPLOAD_URL') ?: ('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/sia-api/uploads'), '/') . '/';
    echo json_encode([
        'success'      => true,
        'message'      => 'Document uploaded successfully.',
        'file_name'    => $filename,
        'file_url'     => $baseUrl . $filename,
        'document_type'=> $document_type,
    ]);
}