<?php
// FIX R-01: Removed set_error_handler that threw exceptions on every PHP notice.
// Use a safe exception handler only for truly unexpected exceptions.
set_exception_handler(function($e) {
    http_response_code(500);
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success'=>false,'message'=>'Server error. Please try again.']);
    exit();
});
require_once __DIR__ . '/config.php';
applyCors();
ob_start(); // capture stray notices so JSON is never corrupted
// Shared helpers: cleanCode(), loadFeeConfig(), safeStudentId(), jsonOut()
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/audit_helper.php';   // ← FIX REG-AUDIT-01: logAuditShared() used in confirmRegistration/rejectRegistration

// ── Inline privacy layer (self-contained, no external file needed) ─────────────
if (!function_exists('applyPrivacy')) {
    function maskEmail(string $v): string {
        if (!str_contains($v,'@')) return '***@***.***';
        [$l,$d] = explode('@',$v,2);
        return substr($l,0,min(2,strlen($l))).str_repeat('*',max(3,strlen($l)-2)).'@'.$d;
    }
    function maskPhone(string $v): string {
        $d=preg_replace('/\D/','',$v); $len=strlen($d);
        if ($len<7) return str_repeat('*',$len);
        return (str_starts_with(trim($v),'+') ? '+' : '').substr($d,0,4).str_repeat('*',max(3,$len-8)).substr($d,-4);
    }
    function maskAmount(float $v): string {
        if ($v<=0) return 'P0';
        $mag=pow(10,floor(log10($v)));
        return 'P'.number_format(floor($v/$mag)*$mag).'+';
    }
    function maskGrade(?float $v): string {
        if ($v===null) return 'N/A';
        if ($v>=5.0) return '5.0 (INC/Failed)';
        $l=floor($v*2)/2;
        return number_format($l,2).'-'.number_format($l+0.5,2);
    }
    function maskGpa(float $v): string {
        $r=round($v,1); $label=$v<=3.0?'Passing':($v<5.0?'At risk':'Failed');
        return "~{$r} ({$label})";
    }
    function maskStudentNumber(string $v): string {
        $len=strlen($v); if ($len<=6) return str_repeat('*',$len);
        return substr($v,0,4).str_repeat('*',$len-6).substr($v,-2);
    }
    function maskAddress(string $v): string {
        $p=array_map('trim',explode(',',$v));
        if (count($p)>=3) return implode(', ',array_slice($p,-2));
        return count($p)===2 ? $p[1] : '***';
    }
    function _castForMask(string $fn, $v): mixed {
        return match($fn) {
            'maskAmount','maskGpa' => (float)$v,
            'maskGrade'            => ($v===null ? null : (float)$v),
            default                => (string)$v,
        };
    }
    function getPrivacyPolicy(): array {
        return [
            'email'           =>['roles_full'=>['admin','registrar'],'roles_masked'=>['student','accounting','faculty'],'mask_fn'=>'maskEmail'],
            'phone'           =>['roles_full'=>['admin','registrar'],'roles_masked'=>['student','accounting'],'mask_fn'=>'maskPhone'],
            'address'         =>['roles_full'=>['admin','registrar'],'roles_masked'=>['accounting'],'mask_fn'=>'maskAddress'],
            'date_of_birth'   =>['roles_full'=>['admin','registrar'],'roles_masked'=>[]],
            'lrn_no'          =>['roles_full'=>['admin','registrar'],'roles_masked'=>['accounting'],'mask_fn'=>'maskStudentNumber'],
            'student_number'  =>['roles_full'=>['admin','registrar','accounting','student'],'roles_masked'=>['faculty'],'mask_fn'=>'maskStudentNumber'],
            'gpa'             =>['roles_full'=>['admin','registrar','student'],'roles_masked'=>['faculty','accounting'],'mask_fn'=>'maskGpa'],
            'prelim'          =>['roles_full'=>['admin','registrar','faculty','student'],'roles_masked'=>['accounting'],'mask_fn'=>'maskGrade'],
            'midterm'         =>['roles_full'=>['admin','registrar','faculty','student'],'roles_masked'=>['accounting'],'mask_fn'=>'maskGrade'],
            'final'           =>['roles_full'=>['admin','registrar','faculty','student'],'roles_masked'=>['accounting'],'mask_fn'=>'maskGrade'],
            'grade'           =>['roles_full'=>['admin','registrar','faculty','student'],'roles_masked'=>['accounting'],'mask_fn'=>'maskGrade'],
            'amount'          =>['roles_full'=>['admin','accounting','student'],'roles_masked'=>['registrar'],'mask_fn'=>'maskAmount'],
            'gcash_amount'    =>['roles_full'=>['admin','accounting'],'roles_masked'=>['registrar'],'mask_fn'=>'maskAmount'],
            'gcashAmount'     =>['roles_full'=>['admin','accounting'],'roles_masked'=>['registrar'],'mask_fn'=>'maskAmount'],
            // Registrar needs exact payment figures to decide whether to confirm registration.
            // Amounts are already verified by Accounting before reaching this view.
            'totalAssessment' =>['roles_full'=>['admin','accounting','student','registrar'],'roles_masked'=>[]],
            'totalPaid'       =>['roles_full'=>['admin','accounting','student','registrar'],'roles_masked'=>[]],
            'balance'         =>['roles_full'=>['admin','accounting','student','registrar'],'roles_masked'=>[]],
            'gcash_reference' =>['roles_full'=>['admin','accounting'],'roles_masked'=>[]],
            'gcashReference'  =>['roles_full'=>['admin','accounting'],'roles_masked'=>[]],
            'reference_number'=>['roles_full'=>['admin','accounting'],'roles_masked'=>[]],
            'password'        =>['roles_full'=>[],'roles_masked'=>[]],
            'token'           =>['roles_full'=>[],'roles_masked'=>[]],
            // Guardian contact fields — registrar & admin see full; accounting sees masked email
            'guardianEmail'   =>['roles_full'=>['admin','registrar','accounting'],'roles_masked'=>['faculty'],'mask_fn'=>'maskEmail'],
            'guardian_email'  =>['roles_full'=>['admin','registrar','accounting'],'roles_masked'=>['faculty'],'mask_fn'=>'maskEmail'],
            'guardianContact' =>['roles_full'=>['admin','registrar','accounting'],'roles_masked'=>[]],
            'guardian_contact'=>['roles_full'=>['admin','registrar','accounting'],'roles_masked'=>[]],
            'guardianName'    =>['roles_full'=>['admin','registrar','accounting','faculty','student'],'roles_masked'=>[]],
            'guardian_name'   =>['roles_full'=>['admin','registrar','accounting','faculty','student'],'roles_masked'=>[]],
            'guardianAddress' =>['roles_full'=>['admin','registrar'],'roles_masked'=>['accounting'],'mask_fn'=>'maskAddress'],
            'guardian_address'=>['roles_full'=>['admin','registrar'],'roles_masked'=>['accounting'],'mask_fn'=>'maskAddress'],
        ];
    }
    function applyPrivacy(array $record, ?array $authUser, string $context='', bool $isOwner=false): array {
        $role = $authUser['role'] ?? 'guest';
        if ($role === 'admin') return $record;
        $policy = getPrivacyPolicy(); $result = [];
        foreach ($record as $key => $value) {
            if ($value === null || $value === '') { $result[$key] = $value; continue; }
            if (!isset($policy[$key])) { $result[$key] = $value; continue; }
            $rule = $policy[$key];
            $eff  = ($isOwner && $role === 'student') ? 'admin' : $role;
            if ($eff === 'admin') { $result[$key] = $value; continue; }
            $full   = $rule['roles_full']   ?? [];
            $masked = $rule['roles_masked'] ?? [];
            $fn     = $rule['mask_fn']      ?? null;
            if (in_array($eff,$full,true)) { $result[$key] = $value; }
            elseif (in_array($eff,$masked,true) && $fn && function_exists($fn)) { $result[$key] = $fn(_castForMask($fn,$value)); }
            else { $result[$key] = null; } // role has no access — redact to null, never silently drop the key
        }
        return $result;
    }
    function applyPrivacyList(array $records, ?array $authUser, string $context='', ?int $ownerStudentId=null): array {
        return array_map(function(array $r) use ($authUser,$context,$ownerStudentId) {
            $isOwner=false;
            if ($ownerStudentId!==null) { $rid=(int)($r['id']??$r['student_id']??$r['studentId']??0); $isOwner=($rid===$ownerStudentId); }
            return applyPrivacy($r,$authUser,$context,$isOwner);
        },$records);
    }
    function privacyMeta(?array $authUser): array {
        return ['role'=>$authUser['role']??'guest','masking_active'=>true,'masked_at'=>date('c')];
    }
}

$action = $_GET['action'] ?? '';

require_once __DIR__ . '/auth_middleware.php';

/**
 * Returns the base URL for uploaded files.
 * Reads SIA_UPLOAD_URL from .env first (set this on Hostinger).
 * Falls back to auto-detecting the current host with https:// on live,
 * http:// on localhost — so TOR links always work on both environments.
 *
 * On Hostinger, add this line to your .env:
 *   SIA_UPLOAD_URL=https://steelblue-marten-571548.hostingersite.com/sia-api/uploads
 */
function getUploadBaseUrl(): string {
    $env = getenv('SIA_UPLOAD_URL');
    if ($env) return rtrim($env, '/') . '/';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $host . '/sia-api/uploads/';
}
// Actions called during enrollment wizard (no token yet)
$publicActions = [
    'upload_tor_file', 'submit_tor', 'get_program_courses', 'get_tor_evaluation',
    'upload_scholar_proof',
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
if ($action === 'upload_tor_file')    { uploadTorFile($conn);      exit(); }
if ($action === 'upload_document')   { uploadDocument($conn);     exit(); }
if ($action === 'upload_scholar_proof') { uploadScholarProof($conn); exit(); }

// Read-only actions (GET)
switch ($action) {
    case 'get_pending_tor':        getPendingTOR($conn);               exit();
    case 'get_lab_room_count':
        $cnt = (int)((($_r=$conn->query("SELECT COUNT(*) AS c FROM rooms WHERE room_type='Laboratory'")) ? $_r->fetch_assoc()['c'] : 0) ?? 0);
        while (ob_get_level() > 0) { ob_end_clean(); }
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
    // ── Masterlist ──
    case 'masterlist_students':    getMasterlistStudents($conn);       exit();
    case 'masterlist_subjects':    getMasterlistSubjects($conn);       exit();
    case 'masterlist_courses':     getMasterlistCourses($conn);        exit();
    case 'masterlist_programs':    getMasterlistPrograms($conn);       exit();
    case 'masterlist_program_subjects': getMasterlistProgramSubjects($conn); exit();
    case 'report_students_per_year':   reportStudentsPerYear($conn);         exit();
    case 'report_subjects_per_year':   reportSubjectsPerYear($conn);         exit();
    case 'masterlist_course_students': getMasterlistCourseStudents($conn); exit();
    case 'get_enrollment_history': getEnrollmentHistory($conn); exit();
    // ── Certificate of Enrollment (GET) ───────────────────────────────────
    case 'coe_get_my_requests':    coeGetMyRequests($conn);         exit();
    case 'coe_check_eligibility':  coeCheckEligibility($conn);      exit();
    case 'coe_get_pending':     coeGetPending($conn);      exit();
    case 'coe_get_detail':      coeGetDetail($conn);       exit();
    case 'coe_detail_by_student': coeGetDetailByStudent($conn); exit();
    case 'coe_get_semesters':     coeGetSemesters($conn);           exit();
    case 'get_scholarship_students': getScholarshipStudents($conn); exit();
    // ── Add/Drop (GET) ───────────────────────────────────────────────────────
    case 'get_add_drop_requests':
        require_once __DIR__ . '/enrollment.php';
        getAddDropRequests($conn); exit();
    case 'get_my_add_drop':
        require_once __DIR__ . '/enrollment.php';
        getMyAddDrop($conn); exit();
    case 'get_add_drop_window':
        require_once __DIR__ . '/enrollment.php';
        getAddDropWindow($conn); exit();
    // ── Subject Selection (GET) ──────────────────────────────────────────────
    case 'get_pending_subject_selections':
        require_once __DIR__ . '/enrollment.php';
        $GLOBALS['authUser'] = $authUser;
        getPendingSubjectSelections($conn); exit();
    case 'get_subject_selection':
        require_once __DIR__ . '/enrollment.php';
        $GLOBALS['authUser'] = $authUser;
        getSubjectSelection($conn); exit();
    case 'search_students':
        require_once __DIR__ . '/enrollment.php';
        searchStudents($conn); exit();
    case 'get_student_enrollments':
        require_once __DIR__ . '/enrollment.php';
        getStudentEnrollments($conn); exit();
    // ── Class Blocks (GET) ───────────────────────────────────────────────────
    case 'get_blocks':
        require_once __DIR__ . '/blocks.php';
        $GLOBALS['authUser'] = $authUser;
        ensureBlockTables($conn);
        getBlocks($conn); exit();
    case 'get_block_detail':
        require_once __DIR__ . '/blocks.php';
        $GLOBALS['authUser'] = $authUser;
        ensureBlockTables($conn);
        getBlockDetail($conn); exit();
    case 'get_student_block':
        require_once __DIR__ . '/blocks.php';
        $GLOBALS['authUser'] = $authUser;
        ensureBlockTables($conn);
        getStudentBlock($conn); exit();
}

// Write actions (POST body required)
switch ($action) {
    case 'submit_tor':   submitTOR($conn, $data);    exit();
    case 'evaluate_tor': evaluateTOR($conn, $data);  exit();
    case 'reject_tor':   rejectTOR($conn, $data);    exit();
    case 'confirm_registration': confirmRegistration($conn, $data); exit();
    case 'reject_registration':  rejectRegistration($conn, $data);  exit();
    case 'update_student_info':  updateStudentInfo($conn, $data);   exit();
    case 'get_pending_registrations':  getPendingRegistrations($conn);  exit();
    case 'get_confirmed_enrollments':  getConfirmedEnrollments($conn);  exit();
    // ── Certificate of Enrollment (POST) ──────────────────────────────────
    case 'coe_request':  coeRequest($conn, $data);    exit();
    case 'coe_approve':  coeApprove($conn, $data);    exit();
    case 'coe_reject':   coeReject($conn, $data);     exit();
    // ── Add/Drop (POST) ──────────────────────────────────────────────────────
    case 'registrar_add_subject':
        require_once __DIR__ . '/enrollment.php';
        registrarAddSubject($conn, $data); exit();
    case 'registrar_drop_subject':
        require_once __DIR__ . '/enrollment.php';
        registrarDropSubject($conn, $data); exit();
    // ── Subject Selection (POST) ─────────────────────────────────────────────
    case 'approve_subject_selection':
        require_once __DIR__ . '/enrollment.php';
        $GLOBALS['authUser'] = $authUser;
        approveSubjectSelection($conn, $data); exit();
    case 'process_add_drop':
        require_once __DIR__ . '/enrollment.php';
        try { processAddDropRequest($conn, $data); }
        catch (Throwable $e) {
            while (ob_get_level() > 0) { ob_end_clean(); }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    case 'set_add_drop_window':
        require_once __DIR__ . '/enrollment.php';
        setAddDropWindow($conn, $data); exit();
    // ── Class Blocks (POST) ──────────────────────────────────────────────────
    case 'assign_block':
        require_once __DIR__ . '/blocks.php';
        $GLOBALS['authUser'] = $authUser;
        ensureBlockTables($conn);
        assignBlock($conn, $data); exit();
    case 'auto_assign_block':
        require_once __DIR__ . '/blocks.php';
        $GLOBALS['authUser'] = $authUser;
        ensureBlockTables($conn);
        autoAssignBlock($conn, $data); exit();
    case 'create_block':
        require_once __DIR__ . '/blocks.php';
        $GLOBALS['authUser'] = $authUser;
        ensureBlockTables($conn);
        createBlock($conn, $data); exit();
    case 'update_block':
        require_once __DIR__ . '/blocks.php';
        $GLOBALS['authUser'] = $authUser;
        ensureBlockTables($conn);
        updateBlock($conn, $data); exit();
    case 'assign_block_section':
        require_once __DIR__ . '/blocks.php';
        $GLOBALS['authUser'] = $authUser;
        ensureBlockTables($conn);
        assignBlockSection($conn, $data); exit();
}

while (ob_get_level() > 0) { ob_end_clean(); }
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
        $sq = '%' . $search . '%';
        $where[] = "(s.student_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR CONCAT(s.first_name,' ',s.last_name) LIKE ?)";
        $params[] = $sq; $params[] = $sq; $params[] = $sq; $params[] = $sq;
        $types .= 'ssss';
    }
    if ($program)   { $where[] = 's.program = ?';          $params[] = $program;   $types .= 's'; }
    if ($yearLevel) { $where[] = 's.year_level = ?';       $params[] = $yearLevel; $types .= 's'; }
    if ($semester)  { $where[] = 's.semester = ?';         $params[] = $semester;  $types .= 's'; }
    $category = trim($_GET['category'] ?? '');
    if ($category)  { $where[] = 's.student_category = ?'; $params[] = $category;  $types .= 's'; }

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
               -- FIX: prelim/midterm/final_grade columns removed from enrollments.
               -- Grades now in student_grades table. Count submitted grades per term.
               COUNT(DISTINCT CASE WHEN sg_p.term='Prelim'  THEN e.id END) AS prelim_done,
               COUNT(DISTINCT CASE WHEN sg_m.term='Midterm' THEN e.id END) AS midterm_done,
               COUNT(DISTINCT CASE WHEN sg_f.term='Final'   THEN e.id END) AS final_done
        FROM students s
        LEFT JOIN enrollments e ON e.student_id = s.id AND e.status IN ('Enrolled','Pending')
        LEFT JOIN student_grades sg_p ON sg_p.enrollment_id = e.id AND sg_p.term = 'Prelim'
        LEFT JOIN student_grades sg_m ON sg_m.enrollment_id = e.id AND sg_m.term = 'Midterm'
        LEFT JOIN student_grades sg_f ON sg_f.enrollment_id = e.id AND sg_f.term = 'Final' 
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

    while (ob_get_level() > 0) { ob_end_clean(); }
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
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$sid) { echo json_encode(['success'=>false,'message'=>'student_id required']); return; }

    $sResSt = $conn->prepare("SELECT * FROM students WHERE id=? LIMIT 1");
    $sResSt->bind_param('i', $sid);
    $sResSt->execute();
    $sRes = $sResSt->get_result();
    $sResSt->close();
    $student = $sRes ? $sRes->fetch_assoc() : null;
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$student) { echo json_encode(['success'=>false,'message'=>'Student not found']); return; }

    // FIX: "NULL AS col AS alias" is a SQL syntax error (double AS).
    // Grades are now in student_grades table, not enrollments. Join them per term.
    $res = $conn->query("
        SELECT e.id AS enrollment_id, e.semester, e.status,
               c.id AS course_id, c.code, c.name, c.credits,
               c.year_level  AS course_year_level,
               c.semester    AS course_semester,
               TRIM(CONCAT(COALESCE(f.first_name,''),' ',COALESCE(f.last_name,''))) AS instructor,
               sg_p.grade    AS prelim,
               sg_m.grade    AS midterm,
               sg_f.grade    AS final,
               sg_p.submitted_at AS prelim_at,
               sg_m.submitted_at AS midterm_at,
               sg_f.submitted_at AS final_at
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN faculty f ON f.user_id = c.faculty_id
        LEFT JOIN student_grades sg_p ON sg_p.enrollment_id = e.id AND sg_p.term = 'Prelim'
        LEFT JOIN student_grades sg_m ON sg_m.enrollment_id = e.id AND sg_m.term = 'Midterm'
        LEFT JOIN student_grades sg_f ON sg_f.enrollment_id = e.id AND sg_f.term = 'Final'
        WHERE e.student_id = $sid AND e.status IN ('Enrolled','Pending','Completed')
        ORDER BY c.year_level, c.semester, c.code ASC
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
            'enrollmentId'   => (int)$r['enrollment_id'],
            'courseId'       => (int)$r['course_id'],
            'code'           => cleanCode($r['code']),
            'name'           => $r['name'],
            'credits'        => (int)$r['credits'],
            'instructor'     => $r['instructor'] ?? '',
            'semester'       => $r['semester'] ?? '',
            'yearLevel'      => $r['course_year_level'] ?? '',
            'courseSemester' => $r['course_semester']   ?? '',
            'prelim'         => $prelim,
            'midterm'        => $midterm,
            'final'          => $final,
            'overall'        => $overall,
            'remarks'        => $remarks,
            'prelimAt'       => $r['prelim_at']  ?? null,
            'midtermAt'      => $r['midterm_at'] ?? null,
            'finalAt'        => $r['final_at']   ?? null,
        ];
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    global $authUser;
    $student  = applyPrivacy($student,  $authUser, 'student');
    $subjects = applyPrivacyList($subjects, $authUser, 'grade');
    echo json_encode([
        'success'  => true,
        'student'  => $student,
        'subjects' => $subjects,
        'initials' => strtoupper(substr($student['first_name']??'',0,1).substr($student['last_name']??'',0,1)),
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

    $where  = ['e.status IN ("Enrolled","Pending")'];
    $params = [];
    $types  = '';
    if ($semester) { $where[] = "e.semester=?"; $params[] = $semester; $types .= 's'; }
    if ($program)  { $where[] = "c.program=?";  $params[] = $program;  $types .= 's'; }
    $courseCategory = trim($_GET['category'] ?? '');
    if ($courseCategory) {
        $where[] = "EXISTS (SELECT 1 FROM students s2 JOIN enrollments e2 ON e2.student_id=s2.id WHERE e2.course_id=c.id AND s2.student_category=? LIMIT 1)";
        $params[] = $courseCategory;
        $types   .= 's';
    }
    $whereStr = implode(' AND ', $where);

    // FIX: use prepared statement to safely bind optional params
    $sql = "
        SELECT c.id, c.code, c.name,
               TRIM(CONCAT(COALESCE(f.first_name,''),' ',COALESCE(f.last_name,''))) AS instructor,
               c.program, c.credits,
               c.year_level, c.semester AS course_semester,
               COUNT(DISTINCT e.student_id) AS enrolled_count,
               COUNT(DISTINCT CASE WHEN sg_p.term='Prelim'  THEN e.id END) AS prelim_done,
               COUNT(DISTINCT CASE WHEN sg_m.term='Midterm' THEN e.id END) AS midterm_done,
               COUNT(DISTINCT CASE WHEN sg_f.term='Final'   THEN e.id END) AS final_done
        FROM courses c
        JOIN enrollments e ON e.course_id = c.id
        LEFT JOIN faculty f ON f.user_id = c.faculty_id
        LEFT JOIN student_grades sg_p ON sg_p.enrollment_id = e.id AND sg_p.term = 'Prelim'
        LEFT JOIN student_grades sg_m ON sg_m.enrollment_id = e.id AND sg_m.term = 'Midterm'
        LEFT JOIN student_grades sg_f ON sg_f.enrollment_id = e.id AND sg_f.term = 'Final'
        WHERE $whereStr
        GROUP BY c.id
        ORDER BY c.year_level, c.semester, c.code ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success'=>false,'message'=>'Query error: '.$conn->error]);
        return;
    }
    if ($types && count($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();
    $courses = [];
    while ($r = $res->fetch_assoc()) {
        $enrolled = (int)$r['enrolled_count'];
        $courses[] = [
            'id'              => (int)$r['id'],
            'code'            => cleanCode($r['code']),
            'name'            => $r['name'],
            'instructor'      => $r['instructor'] ?? '',
            'program'         => $r['program'],
            'credits'         => (int)$r['credits'],
            'yearLevel'       => $r['year_level']      ?? '',
            'courseSemester'  => $r['course_semester'] ?? '',
            'enrolledCount'   => $enrolled,
            'prelimDone'      => (int)$r['prelim_done'],
            'midtermDone'     => (int)$r['midterm_done'],
            'finalDone'       => (int)$r['final_done'],
            'gradeCompletion' => $enrolled > 0 ? round(((int)$r['prelim_done']+(int)$r['midterm_done']+(int)$r['final_done'])/($enrolled*3)*100) : 0,
        ];
    }
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success'=>true,'courses'=>$courses]);
}

/**
 * GET ?action=get_course_students&course_id=X
 * Returns all enrolled students in a specific course with their grades.
 */
function getCourseStudents($conn) {
    $cid = (int)($_GET['course_id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$cid) { echo json_encode(['success'=>false,'message'=>'course_id required']); return; }

    $cRes   = $conn->query("SELECT * FROM courses WHERE id=$cid LIMIT 1");
    $course = $cRes ? $cRes->fetch_assoc() : null;
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$course) { echo json_encode(['success'=>false,'message'=>'Course not found']); return; }

    // FIX: "NULL AS col AS alias" is a SQL syntax error. Grades join from student_grades.
    $res = $conn->query("
        SELECT s.id AS student_id, s.student_number, s.first_name, s.last_name,
               s.program, s.year_level, s.enrollment_status,
               e.id AS enrollment_id, e.semester,
               sg_p.grade AS prelim,
               sg_m.grade AS midterm,
               sg_f.grade AS final
        FROM enrollments e
        JOIN students s ON s.id = e.student_id
        LEFT JOIN student_grades sg_p ON sg_p.enrollment_id = e.id AND sg_p.term = 'Prelim'
        LEFT JOIN student_grades sg_m ON sg_m.enrollment_id = e.id AND sg_m.term = 'Midterm'
        LEFT JOIN student_grades sg_f ON sg_f.enrollment_id = e.id AND sg_f.term = 'Final'
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
    while (ob_get_level() > 0) { ob_end_clean(); }
    global $authUser;
    $students = applyPrivacyList($students, $authUser, 'student');
    echo json_encode(['success'=>true,'course'=>$course,'students'=>$students]);
}


// ─────────────────────────────────────────────────────────────
// DEBUG: GET ?action=debug_tor&student_id=XX
// Dumps all intermediate values used in evaluateTOR computation
// ─────────────────────────────────────────────────────────────
function debugTOR($conn) {
    $student_id = (int)($_GET['student_id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$student_id) { echo json_encode(['error' => 'student_id required']); return; }

    $out = ['student_id' => $student_id];

    // 1. Student row
    $stSt = $conn->prepare("SELECT program, semester, year_level, payment_plan, tor_eval_status, enrollment_status FROM students WHERE id=? LIMIT 1");
    $stSt->bind_param('i', $student_id);
    $stSt->execute();
    $st = $stSt->get_result();
    $stSt->close();
    $stRow = $st ? $st->fetch_assoc() : null;
    $out['student'] = $stRow;

    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$stRow) { echo json_encode($out); return; }

    $pn      = $stRow['program'] ?? '';
    $sem_raw = trim($stRow['semester'] ?? '');
    $yl      = $stRow['year_level'] ?? '1st Year';

    // 2. Sem/year filter construction
    preg_match('/^(1st Semester|2nd Semester|Summer)/i', $sem_raw, $sm);
    $semTerm        = $sm[1] ?? $sem_raw;
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
    $pnLike = '%' . $pn . '%';
    $progsStmt = $conn->prepare("SELECT DISTINCT program, year_level, semester FROM courses WHERE program LIKE ? OR program LIKE '%IT%' OR program LIKE '%Information%' LIMIT 20");
    $progsStmt->bind_param('s', $pnLike);
    $progsStmt->execute();
    $progs = $progsStmt->get_result();
    $progsStmt->close();
    $out['courses_programs'] = [];
    if ($progs) while ($r = $progs->fetch_assoc()) $out['courses_programs'][] = $r;

    // 7. tor_evaluations row
    $teSt = $conn->prepare("SELECT * FROM tor_evaluations WHERE student_id=? ORDER BY id DESC LIMIT 1");
    $teSt->bind_param('i', $student_id);
    $teSt->execute();
    $te = $teSt->get_result();
    $teSt->close();
    $out['tor_evaluation'] = $te ? $te->fetch_assoc() : null;

    // 8. tuition_fees row
    $tfSt = $conn->prepare("SELECT * FROM tuition_fees WHERE student_id=? LIMIT 1");
    $tfSt->bind_param('i', $student_id);
    $tfSt->execute();
    $tf = $tfSt->get_result();
    $tfSt->close();
    $out['tuition_fees'] = $tf ? $tf->fetch_assoc() : null;

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode($out, JSON_PRETTY_PRINT);
}

// ─────────────────────────────────────────────────────────────
// STUDENT: Upload TOR file and create tor_evaluation record
// POST ?action=upload_tor_file  (multipart/form-data)
// Fields: student_id, tor_file (file)
// ─────────────────────────────────────────────────────────────
function uploadTorFile($conn) {
    $student_id = (int)($_POST['student_id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$student_id) { echo json_encode(['success' => false, 'message' => 'student_id required']); return; }

    // Build upload directory — works on both Windows XAMPP and Linux
    $scriptDir  = dirname($_SERVER['SCRIPT_FILENAME']);
    $uploadDir  = $scriptDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            while (ob_get_level() > 0) { ob_end_clean(); }
            echo json_encode(['success' => false, 'message' => 'Could not create uploads folder. Create C:\\xampp\\htdocs\\sia-api\\uploads\\ manually.']);
            return;
        }
    }

    $torUrl = '';
    if (!empty($_FILES['tor_file']) && $_FILES['tor_file']['error'] === UPLOAD_ERR_OK) {
        $ext     = strtolower(pathinfo($_FILES['tor_file']['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf','jpg','jpeg','png','gif','webp'];
        if (!in_array($ext, $allowed)) {
            while (ob_get_level() > 0) { ob_end_clean(); }
            echo json_encode(['success' => false, 'message' => 'Only PDF and image files allowed.']);
            return;
        }
        $filename = 'tor_' . $student_id . '_' . time() . '.' . $ext;
        $dest     = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['tor_file']['tmp_name'], $dest)) {
            $torUrl = $filename;
        } else {
            while (ob_get_level() > 0) { ob_end_clean(); }
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

    $torUpdSt = $conn->prepare("UPDATE students SET tor_eval_status = 'Pending' WHERE id = ?");
    $torUpdSt->bind_param('i', $student_id);
    $torUpdSt->execute();
    $torUpdSt->close();

    // FIX R-04: Use configurable base URL (set SIA_UPLOAD_URL in environment or .env)
    $baseUrl  = getUploadBaseUrl();
    $fileUrl  = $torUrl ? ($baseUrl . $torUrl) : '';
    while (ob_get_level() > 0) { ob_end_clean(); }
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
    while (ob_get_level() > 0) { ob_end_clean(); }
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
    $torUpdSt = $conn->prepare("UPDATE students SET tor_eval_status = 'Pending' WHERE id = ?");
    $torUpdSt->bind_param('i', $student_id);
    $torUpdSt->execute();
    $torUpdSt->close();

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'message' => 'TOR submitted for evaluation.']);
}

// ─────────────────────────────────────────────────────────────
// HELPER: Compute total program units live (semester + year filtered)
// Used by getPendingTOR, getTORForStudent — replaces stale tuition_fees.units.
// ─────────────────────────────────────────────────────────────
function computeProgramUnitsLive(mysqli $conn, string $programName, string $yearLevel, string $semester): int {
    $pn  = $programName;  // used only in prepared statements
    $yl  = $yearLevel;

    $semFilter = ''; $sfNoJoin = '';
    if ($semester !== '') {
        preg_match('/^(1st Semester|2nd Semester|Summer)/i', $semester, $sm);
        $semTerm   = $sm[1] ?? $semester;
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
    $yl     = $ylNorm;

    // Use CASE-insensitive year_level match covering both 'Year 1' and '1st Year' formats
    // ylFilter  = year_level filter for JOINed queries (alias c.year_level)
    // ylFilterNJ = year_level filter for non-joined queries (plain year_level column)
    $ylFilter   = ($yl !== '') ? "AND (c.year_level = '$yl' OR c.year_level = '$yearLevel')" : '';
    $ylFilterNJ = ($yl !== '') ? "AND (year_level = '$yl' OR year_level = '$yearLevel')" : '';


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
    $pcRowSt = $conn->prepare("SELECT code FROM programs WHERE name=? OR code=? LIMIT 1");
    $pcRowSt->bind_param('ss', $pn, $pn);
    $pcRowSt->execute();
    $pcRow = $pcRowSt->get_result();
    $pcRowSt->close();
    $pc     = $pcRow && $pcRow->num_rows > 0
        ? ($pcRow->fetch_assoc()['code'] ?? $pn)
        : $pn;
    $units3 = 0;
    if ($pc !== $pn) {
        $res3   = $conn->query("SELECT COALESCE(SUM(credits),0) AS u
            FROM courses WHERE program='$pc' $ylFilterNJ $sfNoJoin");
        $units3 = (int)(($res3 ? $res3->fetch_assoc()['u'] : 0) ?: 0);
    }

    $units = max($units1, $units2, $units3);

    // FIX TOR-UNITS-ACCURATE-01: If all filtered sources returned 0, try once more
    // WITHOUT year_level + semester filters. TVET/SHS programs often don't have
    // year_level or semester set on their courses, so the filtered queries miss them.
    // We only use this wider result to compute the TOTAL program unit count shown on
    // the TOR card — it is NOT used to select which courses to auto-enroll.
    if ($units === 0) {
        $res4 = $conn->query("SELECT COALESCE(SUM(c.credits),0) AS u
            FROM program_courses pc
            JOIN programs p ON pc.program_id=p.id
            JOIN courses c  ON pc.course_id=c.id
            WHERE (p.name='$pn' OR p.code='$pn' OR p.code='$pc')");
        $units4 = (int)(($res4 ? $res4->fetch_assoc()['u'] : 0) ?: 0);

        $res5 = $conn->query("SELECT COALESCE(SUM(credits),0) AS u
            FROM courses WHERE program='$pn' OR program='$pc'");
        $units5 = (int)(($res5 ? $res5->fetch_assoc()['u'] : 0) ?: 0);

        $units = max($units4, $units5);
    }

    // Return the real count — 0 means no courses are set up for this program yet.
    // Callers should surface this to the registrar rather than silently using 18.
    return $units;
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
            s.student_category,
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
                $torFileUrl = getUploadBaseUrl() . $r['tor_file'];
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
                'studentCategory'    => $r['student_category'] ?? 'College',
                // FIX TOR-DEPT-01: Compute the correct department label for TVET/SHS
                // so the TOR evaluation modal header shows the right department instead
                // of the College department stored in programs.department (e.g. "ICTD").
                'department'         => match(strtoupper(trim($r['student_category'] ?? ''))) {
                    'TVET'  => 'Technical-Vocational Education and Training (TVET)',
                    'SHS'   => 'Senior High School (SHS)',
                    default => '',
                },
                'isTVETTransferee'   => (strtoupper(trim($r['student_category'] ?? '')) === 'TVET'
                                         && strcasecmp(trim($r['student_type'] ?? ''), 'Transferee') === 0),
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
    while (ob_get_level() > 0) { ob_end_clean(); }
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
            s.last_school_attended,
            s.student_type,
            s.student_category
        FROM tor_evaluations te
        JOIN students s ON te.student_id = s.id
        WHERE te.status IN ('Evaluated', 'Rejected')
        ORDER BY te.evaluated_at DESC
        LIMIT 100
    ");

    $rows = [];
    if ($result) {
        while ($r = $result->fetch_assoc()) {
            // FIX TOR-APPROVED-UNITS-DISPLAY-01: Recompute approvedUnits live instead
            // of trusting the stale stored value. The stored value may have been saved
            // before the sem_credited_units bug was fixed (e.g. TVET blank metadata
            // caused sem_credited_units = 0 → approvedUnits never subtracted credits).
            $creditedUnits = (int)$r['credited_units'];
            $programUnits  = computeProgramUnitsLive($conn, $r['program'], $r['year_level'] ?? '', '');
            $approvedUnits = max(0, $programUnits - $creditedUnits);
            // If recomputed result looks wrong (0 program units = no courses set up),
            // fall back to the stored value so we don’t show worse data than before.
            if ($programUnits === 0) {
                $approvedUnits = (int)$r['approved_units'];
            }

            $rows[] = [
                'evalId'             => (int)$r['eval_id'],
                'studentId'          => (int)$r['student_id'],
                'studentNumber'      => $r['student_number'],
                'firstName'          => $r['first_name'],
                'lastName'           => $r['last_name'],
                'program'            => $r['program'],
                'lastSchoolAttended' => $r['last_school_attended'],
                'studentType'        => $r['student_type'] ?? '',
                'studentCategory'    => $r['student_category'] ?? 'College',
                // FIX TOR-DEPT-01: correct department label for TVET/SHS
                'department'         => match(strtoupper(trim($r['student_category'] ?? ''))) {
                    'TVET'  => 'Technical-Vocational Education and Training (TVET)',
                    'SHS'   => 'Senior High School (SHS)',
                    default => '',
                },
                'isTVETTransferee'   => (strtoupper(trim($r['student_category'] ?? '')) === 'TVET'
                                         && strcasecmp(trim($r['student_type'] ?? ''), 'Transferee') === 0),
                'status'             => $r['status'],
                'creditedUnits'      => $creditedUnits,
                'approvedUnits'      => $approvedUnits,
                'programUnits'       => $programUnits,
                'creditedSubjects'   => $r['credited_subjects'] ? json_decode($r['credited_subjects'], true) : [],
                'registrarNotes'     => $r['registrar_notes'],
                'evaluatedAt'        => $r['evaluated_at'],
            ];
        }
    }
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'evaluations' => $rows]);
}

// ─────────────────────────────────────────────────────────────
// REGISTRAR: Get TOR evaluation for a specific student
// GET ?action=get_tor_evaluation&student_id=XX
// ─────────────────────────────────────────────────────────────
function getTORForStudent($conn) {
    $student_id = (int)($_GET['student_id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$student_id) { echo json_encode(['success' => false, 'message' => 'student_id required']); return; }

    // FIX REG-TOR-04: was using raw interpolation "$student_id" directly in query string
    $torSt = $conn->prepare("
        SELECT te.*, s.student_number, s.first_name, s.last_name, s.program, s.year_level, s.semester,
               s.student_category, s.student_type
        FROM tor_evaluations te
        JOIN students s ON te.student_id = s.id
        WHERE te.student_id = ? LIMIT 1
    ");
    if (!$torSt) {
        echo json_encode(['success' => false, 'message' => 'DB error: '.$conn->error]); return;
    }
    $torSt->bind_param('i', $student_id);
    $torSt->execute();
    $res = $torSt->get_result();
    $r = $res ? $res->fetch_assoc() : null;
    $torSt->close();

    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$r) { echo json_encode(['success' => false, 'message' => 'No TOR evaluation found for this student']); return; }

    $programUnits = computeProgramUnitsLive($conn, $r['program'], $r['year_level'], $r['semester'] ?? '');

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success' => true,
        'evaluation' => [
            'evalId'           => (int)$r['id'],
            'studentId'        => (int)$r['student_id'],
            'studentNumber'    => $r['student_number'],
            'studentName'      => $r['first_name'] . ' ' . $r['last_name'],
            'program'          => $r['program'],
            'programUnits'     => $programUnits,
            'studentCategory'  => $r['student_category'] ?? 'College',
            'studentType'      => $r['student_type'] ?? '',
            // FIX TOR-DEPT-01: Correct department label for TVET/SHS — prevents
            // "ICTD" from appearing in the TOR evaluation modal header.
            'department'       => match(strtoupper(trim($r['student_category'] ?? ''))) {
                'TVET'  => 'Technical-Vocational Education and Training (TVET)',
                'SHS'   => 'Senior High School (SHS)',
                default => '',
            },
            // Lets the Angular TOR modal show "Flat Rate – ₱20,000" instead of
            // the unit-based fee selector for TVET transferees.
            'isTVETTransferee' => (strtoupper(trim($r['student_category'] ?? '')) === 'TVET'
                                   && strcasecmp(trim($r['student_type'] ?? ''), 'Transferee') === 0),
            'status'           => $r['status'],
            'creditedUnits'    => (int)$r['credited_units'],
            // FIX TOR-APPROVED-UNITS-DISPLAY-01: Recompute live — stale stored value
            // may be wrong for TVET (blank course metadata caused sem filter to miss).
            'approvedUnits'    => $programUnits > 0
                ? max(0, $programUnits - (int)$r['credited_units'])
                : (int)$r['approved_units'],
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
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$program) { echo json_encode(['success' => false, 'message' => 'program required']); return; }
    $p = $program;  // used only in prepared statements below

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
        // FIX REG-TOR-02: use prepared statement — raw $p interpolation was SQL-injectable
        $pcSt = $conn->prepare("
            SELECT c.id, c.code, c.name, c.credits,
                   COALESCE(NULLIF(TRIM(c.year_level),''), '') AS year_level,
                   COALESCE(NULLIF(TRIM(c.semester),''), '')   AS semester,
                   c.description
            FROM program_courses pc
            JOIN programs pr ON pc.program_id = pr.id
            JOIN courses c   ON pc.course_id  = c.id
            WHERE pr.name = ? OR pr.code = ?
            ORDER BY c.year_level, c.semester, c.code
        ");
        if ($pcSt) {
            $pcSt->bind_param('ss', $p, $p);
            $pcSt->execute();
            $res = $pcSt->get_result();
            $pcSt->close();
        } else {
            $res = null;
        }
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                if (isset($seen[$r['id']])) continue;
                $seen[$r['id']] = true;
                $courses[] = $r;
            }
        }
    }

    // FIX TVET-PROGRAM-COURSES-01: Fallback via courses.program column.
    // Previously this only ran when the program_courses TABLE was missing entirely.
    // That was too narrow — TVET programs are often not yet wired into the junction
    // table (admin adds courses through the Subjects page which sets courses.program
    // directly, but may not always insert into program_courses).
    // Now we also run the fallback whenever the junction query returned zero rows,
    // regardless of whether the table exists. This means TVET subjects show up
    // even when program_courses has no entries for that program.
    //
    // Note: We match by BOTH the full program name AND the program code so that
    // courses stored with either value in courses.program are found correctly.
    if (empty($courses)) {
        // Try matching by full program name first, then by code
        $programCode = '';
        if ($hasPTable) {
            $codeStmt = $conn->prepare("SELECT code FROM programs WHERE name = ? OR code = ? LIMIT 1");
            if ($codeStmt) {
                $codeStmt->bind_param('ss', $p, $p);
                $codeStmt->execute();
                $codeRow = $codeStmt->get_result()->fetch_assoc();
                $codeStmt->close();
                $programCode = $codeRow['code'] ?? '';
            }
        }

        // FIX REG-TOR-02: prepared statement instead of raw interpolation
        $fbSt = $conn->prepare("
            SELECT id, code, name, credits,
                   COALESCE(NULLIF(TRIM(year_level),''), '') AS year_level,
                   COALESCE(NULLIF(TRIM(semester),''), '')   AS semester,
                   description
            FROM courses
            WHERE program = ? OR (? != '' AND program = ?)
            ORDER BY year_level, semester, code
        ");
        if ($fbSt) {
            $fbSt->bind_param('sss', $p, $programCode, $programCode);
            $fbSt->execute();
            $res2 = $fbSt->get_result();
            $fbSt->close();
        } else {
            $res2 = null;
        }
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
        $teSt2 = $conn->prepare("SELECT credited_subjects FROM tor_evaluations WHERE student_id = ? AND status = 'Evaluated' LIMIT 1");
        $teSt2->bind_param('i', $student_id);
        $teSt2->execute();
        $te = $teSt2->get_result();
        $teSt2->close();
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
        // FIX TOR-UNITS-ACCURATE-01: For TVET/SHS programs, courses often have
        // blank year_level and semester. normalizeYear/normalizeSem default these
        // to '1st Year'/'1st Semester' — wrong for multi-year TVET diplomas.
        // Use raw values when blank; only normalize when a value is actually set.
        $rawYl  = trim($r['year_level'] ?? '');
        $rawSem = trim($r['semester']   ?? '');
        $yr  = $rawYl  !== '' ? normalizeYear($rawYl)  : '';
        $sem = $rawSem !== '' ? normalizeSem($rawSem)   : '';
        $out[] = [
            'courseId'   => (int)$r['id'],
            'code'       => cleanCode($r['code']),
            'name'       => $r['name'],
            'credits'    => (int)$r['credits'],
            'yearLevel'  => $yr,
            'semester'   => $sem,
            'description'=> $r['description'] ?? '',
            'isCredited' => isset($credited_ids[(int)$r['id']]),
            'selected'   => isset($credited_ids[(int)$r['id']]),
        ];
    }

    // FIX REG-TOR-03: resolve the student's current year_level so the Angular
    // TOR eval modal can default-select the correct year tab on open.
    // Without this, the modal always opens on "1st Year" even for 3rd-year transferees.
    $studentYearLevel = '';
    $studentSemester  = '';
    if ($student_id > 0) {
        $ylSt = $conn->prepare("SELECT year_level, semester FROM students WHERE id = ? LIMIT 1");
        if ($ylSt) {
            $ylSt->bind_param('i', $student_id);
            $ylSt->execute();
            $ylRow = $ylSt->get_result()->fetch_assoc();
            $ylSt->close();
            $studentYearLevel = $ylRow['year_level'] ?? '';
            $studentSemester  = $ylRow['semester']   ?? '';
        }
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'          => true,
        'courses'          => $out,
        'total'            => count($out),
        'studentYearLevel' => $studentYearLevel,  // e.g. "3rd Year"
        'studentSemester'  => $studentSemester,   // e.g. "1st Semester, AY 2025-2026"
    ]);
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
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'eval_id and student_id required']); return;
    }

    // Sum credited units and build both JSON stores
    $credited_units  = array_sum(array_column($credited_subs, 'credits'));
    $credited_json   = json_encode($credited_subs);
    // Int array of course IDs for fast enrollment skip — e.g. [18,22,24]
    $course_id_ints  = array_values(array_map(fn($s) => (int)$s['courseId'], $credited_subs));
    $course_ids_json = json_encode($course_id_ints);

    // Get student's program, semester, year_level, and existing payment settings
    $stResSt = $conn->prepare("SELECT program, semester, year_level FROM students WHERE id = ? LIMIT 1");
    $stResSt->bind_param('i', $student_id);
    $stResSt->execute();
    $st_res = $stResSt->get_result();
    $stResSt->close();
    $st_row  = $st_res ? $st_res->fetch_assoc() : null;
    $pn      = $st_row['program']    ?? '';
    $sem_raw = trim($st_row['semester']   ?? '');
    $yl      = $st_row['year_level'] ?? '1st Year';

    // Resolve program code (students.program = full name; courses.program = code)
    $pcRowSt2 = $conn->prepare("SELECT code FROM programs WHERE name = ? OR code = ? LIMIT 1");
    $pcRowSt2->bind_param('ss', $pn, $pn);
    $pcRowSt2->execute();
    $pc_row = $pcRowSt2->get_result();
    $pcRowSt2->close();
    $programCode = ($pc_row && $pc_row->num_rows > 0) ? $pc_row->fetch_assoc()['code'] : $st_row['program'];
    $pc = $programCode;

    // Get existing discount/installment_fee from tuition_fees (do NOT use cached units)
    $tfResSt = $conn->prepare("SELECT discount, installment_fee FROM tuition_fees WHERE student_id = ? LIMIT 1");
    $tfResSt->bind_param('i', $student_id);
    $tfResSt->execute();
    $tf_res = $tfResSt->get_result();
    $tfResSt->close();
    $tf_row   = $tf_res ? $tf_res->fetch_assoc() : null;
    $discount = (float)($tf_row['discount']        ?? 0);
    $inst_fee = (float)($tf_row['installment_fee'] ?? 0);

    // FIX INSTALLMENT-TOR-01: tuition_fees.installment_fee may be 0 if it was
    // seeded before the student chose a payment plan, or if getFeePreview was
    // called without has_installment=1 before the plan was saved to students.
    // Always re-derive from students.payment_plan so the registrar's TOR
    // evaluation never wipes out the installment surcharge the student already chose.
    if ($inst_fee <= 0) {
        $planStTor = $conn->prepare("SELECT payment_plan FROM students WHERE id = ? LIMIT 1");
        $planStTor->bind_param('i', $student_id);
        $planStTor->execute();
        $planRowTor = $planStTor->get_result()->fetch_assoc();
        $planStTor->close();
        if (($planRowTor['payment_plan'] ?? 'full') === 'installment') {
            $fcInstTor = loadFeeConfig($conn, 'College');
            $inst_fee  = (float)($fcInstTor['installment_fee']['value'] ?? 750);
        }
    }

    // Compute program_units live — filtered by year_level + semester term only.
    // NEVER use tuition_fees.units as the base: it may be stale (wrong year/sem).
    // Strip AY suffix so courses stored under any school year are matched.
    $semTerm   = '';
    $semFilter = '';
    $semFilterPlain = '';
    if ($sem_raw !== '') {
        preg_match('/^(1st Semester|2nd Semester|Summer)/i', $sem_raw, $sm);
        $semTerm        = $sm[1] ?? $sem_raw;
        $semFilter      = "AND c.semester LIKE '$semTerm%'";
        $semFilterPlain = "AND semester LIKE '$semTerm%'";
    }
    $ylNormMap2 = [
        'Year 1'=>'1st Year','Year 2'=>'2nd Year','Year 3'=>'3rd Year',
        'Year 4'=>'4th Year','Year 5'=>'5th Year',
    ];
    $ylRaw  = $st_row['year_level'] ?? '1st Year';
    $ylNorm = $ylNormMap2[$ylRaw] ?? $ylRaw;
    $yl     = $ylNorm;
    $ylOrig = $ylRaw;

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

    // FIX TOR-UNITS-ACCURATE-01: If filtered queries returned 0, widen the search
    // (drop year_level + semester) so TVET programs with unset course metadata match.
    if ($program_units <= 0) {
        $wide1 = $conn->prepare("SELECT COALESCE(SUM(c.credits),0) AS u
            FROM program_courses pc JOIN programs pr ON pc.program_id=pr.id
            JOIN courses c ON pc.course_id=c.id WHERE (pr.name=? OR pr.code=?)");
        $wide1->bind_param('ss', $pn, $pn);
        $wide1->execute();
        $wu1 = (int)(($wide1->get_result()->fetch_assoc()['u'] ?? 0) ?: 0);
        $wide1->close();

        $wide2 = $conn->prepare("SELECT COALESCE(SUM(credits),0) AS u FROM courses WHERE program=? OR program=?");
        $wide2->bind_param('ss', $pn, $pc);
        $wide2->execute();
        $wu2 = (int)(($wide2->get_result()->fetch_assoc()['u'] ?? 0) ?: 0);
        $wide2->close();

        $program_units = max($wu1, $wu2);
        // Still 0 means no courses exist at all for this program — leave as 0
        // so the response accurately reflects missing curriculum data.
    }

    // FIX: approved_units = units the student must ENROLL in (and pay for).
    // = current semester's total units MINUS credited units that belong to this semester.
    // We count credited_units that overlap with this semester's courses, not all credited.
    $sem_credited_units = 0;
    if (!empty($course_id_ints)) {
        $ids_str = implode(',', $course_id_ints);
        // FIX TOR-APPROVED-UNITS-01: For TVET/SHS programs, courses have blank
        // semester and year_level — the old $semFilterPlain + $ylFilterPlain caused
        // zero matches, so sem_credited_units stayed 0 and approved_units was never
        // reduced by the credited amount (e.g. showed 6 instead of 3).
        // Fix: try filtered first; if still 0 despite having credited courses,
        // count by course ID alone (no semester/year filter). This is correct because
        // we already know exactly which courses were credited (the IDs are explicit).
        if ($semTerm !== '') {
            $scRes = $conn->query("SELECT COALESCE(SUM(credits),0) AS u FROM courses
                WHERE id IN ($ids_str) $semFilterPlain $ylFilterPlain");
            if ($scRes) $sem_credited_units = (int)($scRes->fetch_assoc()['u'] ?? 0);
        }
        // If filtered count returned 0 (blank metadata on TVET courses), count by ID only
        if ($sem_credited_units === 0) {
            $scRes2 = $conn->query("SELECT COALESCE(SUM(credits),0) AS u FROM courses WHERE id IN ($ids_str)");
            if ($scRes2) $sem_credited_units = (int)($scRes2->fetch_assoc()['u'] ?? 0);
        }
    }
    // If no semester filter at all, use full credited_units total
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
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success'=>false,'message'=>'DB error: '.$conn->error]); return;
    }
    $upsert->bind_param("iiisssi",
        $student_id,
        $credited_units, $approved_units,
        $credited_json, $course_ids_json,
        $notes, $registrar_id);
    $upsert->execute();
    if ($upsert->errno !== 0) {
        $errMsg = $upsert->error;
        $upsert->close();
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success'=>false,'message'=>'Failed to save evaluation: '.$errMsg]); return;
    }
    $upsert->close(); // FIX REG-TOR-01: close before subsequent prepare() calls

    // ── 2. Update student tor_eval_status ──────────────────────
    $torEvalSt = $conn->prepare("UPDATE students SET tor_eval_status = 'Evaluated' WHERE id = ?");
    $torEvalSt->bind_param('i', $student_id);
    $torEvalSt->execute();
    $torEvalSt->close();

    // ── 3. Recompute tuition with approved_units ───────────────
    //
    // FIX TOR-FEE-CATEGORY-01: TVET and SHS Transferees use a FLAT RATE (₱20,000),
    // NOT the College unit-based formula. Before this fix, evaluateTOR() always ran
    // the College formula (units × ₱650 + misc + reg + lab + energy) regardless of
    // student_category, overwriting the correct flat-rate tuition_fees row that
    // getTVETFee() / getSHSFee() had previously written. Result: Accounting saw
    // ₱28,822 in the Cash dialog instead of ₱20,000 (or ₱20,750 with installment fee).
    //
    // Fix: detect student_category + student_type here and route accordingly.
    //   TVET Transferee → preserve existing tuition_fees flat-rate row (no overwrite)
    //   SHS  Transferee → preserve existing tuition_fees flat-rate row (no overwrite)
    //   College Transferee → run College unit-based formula as before

    $catStTor = $conn->prepare("SELECT student_category, student_type FROM students WHERE id = ? LIMIT 1");
    $catStTor->bind_param('i', $student_id);
    $catStTor->execute();
    $catRowTor = $catStTor->get_result()->fetch_assoc();
    $catStTor->close();
    $catTor   = strtoupper(trim($catRowTor['student_category'] ?? ''));
    $stypeTor = trim($catRowTor['student_type'] ?? '');

    $isTVETorSHSTransferee = (($catTor === 'TVET' || $catTor === 'SHS') && $stypeTor === 'Transferee');

    if ($isTVETorSHSTransferee) {
        // TVET/SHS Transferee: flat rate already correct in tuition_fees.
        // DO NOT overwrite with College formula. Just ensure the row exists
        // (getTVETFee/getSHSFee already wrote it; this is a safety check only).
        $tfCheckTor = $conn->query("SELECT id, total_assessment FROM tuition_fees WHERE student_id = $student_id ORDER BY id DESC LIMIT 1");
        $tfRowTor   = $tfCheckTor ? $tfCheckTor->fetch_assoc() : null;
        if (!$tfRowTor || (float)$tfRowTor['total_assessment'] <= 0) {
            // Flat rate row missing — write it now using TVET/SHS fee config
            $fcTorFlat = loadFeeConfig($conn, $catTor);
            $fcTorSHS  = loadFeeConfig($conn, 'SHS');
            $flatRate  = (float)($fcTorFlat['transferee_flat_rate']['value']
                         ?? $fcTorSHS['transferee_flat_rate']['value']
                         ?? 20000);
            $instFlat  = $inst_fee; // from tuition_fees or 0
            $totalFlat = max(0, $flatRate - $discount + $instFlat);
            $semEscTor = $conn->real_escape_string($sem_raw);
            $conn->query("INSERT INTO tuition_fees
                (student_id, units, tuition_fee, miscellaneous_fee, registration_fee,
                 laboratory_fee, energy_fee, subtotal, discount, installment_fee,
                 total_assessment, semester)
                VALUES ($student_id, 0, $flatRate, 0, 0, 0, 0,
                        $flatRate, $discount, $instFlat, $totalFlat, '$semEscTor')
                ON DUPLICATE KEY UPDATE
                    units=0, tuition_fee=$flatRate, miscellaneous_fee=0,
                    registration_fee=0, laboratory_fee=0, energy_fee=0,
                    subtotal=$flatRate, discount=$discount,
                    installment_fee=$instFlat, total_assessment=$totalFlat,
                    semester='$semEscTor', updated_at=NOW()");
        }
        // Skip the College fee block below — jump straight to enrollment sync
    } else {
    // College Transferee: unit-based fee formula
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
    } // end College fee block

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
        // FIX HISTORY-01: Scope the UPDATE to the current semester so Completed rows
        // from previous semesters are NOT retroactively changed to Dropped.
        $conn->query("
            UPDATE enrollments
            SET    status = 'Dropped',
                   notes  = 'Credited via TOR evaluation — permanently excluded'
            WHERE  student_id = $student_id
              AND  course_id  IN ($ids_str)
              AND  status IN ('Enrolled','Pending')
        ");

        // Insert Dropped rows for this semester for courses not yet in enrollments
        // ON DUPLICATE KEY UPDATE is safe: if a Completed row exists from a prior
        // semester, it stays; only same-semester conflicts are updated.
        $torSemStmt = $conn->prepare("SELECT semester FROM students WHERE id=? LIMIT 1");
        $torSemStmt->bind_param('i', $student_id);
        $torSemStmt->execute();
        $torSemRow = $torSemStmt->get_result()->fetch_assoc();
        $torSemStmt->close();
        $torCurrentSem = $torSemRow['semester'] ?? 'TOR Credit';

        foreach ($course_id_ints as $cid) {
            $conn->query("
                INSERT INTO enrollments
                    (student_id, course_id, enrollment_date, status, notes, semester)
                VALUES
                    ($student_id, $cid, '$today', 'Dropped',
                     'Credited via TOR evaluation — permanently excluded',
                     '$torCurrentSem')
                ON DUPLICATE KEY UPDATE
                    status = 'Dropped',
                    notes  = 'Credited via TOR evaluation — permanently excluded'
            ");
        }
    }

    // FIX TOR-PLAN-DEFAULT-01: Auto-set payment_plan='installment' + payment_method='Cash'
    // when TOR is evaluated — only if student has not already made a payment choice.
    // The students table DEFAULT 'full' means we must explicitly overwrite it here.
    $psChkTor = $conn->query("SELECT id FROM payment_schedules WHERE student_id = $student_id LIMIT 1");
    $hasPsTor = $psChkTor && $psChkTor->num_rows > 0;
    if (!$hasPsTor) {
        $conn->query("UPDATE students SET payment_plan = 'installment', payment_method = 'Cash' WHERE id = $student_id");
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    $uOut     = $u     ?? 0;
    $totalOut = $total ?? 0;
    echo json_encode([
        'success'       => true,
        'message'       => 'TOR evaluated. Tuition recomputed.',
        'creditedUnits' => $credited_units,
        'approvedUnits' => $approved_units,
        'newUnits'      => $uOut,
        'newTotal'      => $totalOut,
        'fees' => [
            'units'            => $uOut,
            'tuitionFee'       => $tuition_fee       ?? 0,
            'miscellaneousFee' => $misc_fee           ?? 0,
            'registrationFee'  => $reg_fee            ?? 0,
            'laboratoryFee'    => $lab_fee            ?? 0,
            'energyFee'        => $energy_fee         ?? 0,
            'subtotal'         => $subtotal           ?? 0,
            'discount'         => $discount,
            'installmentFee'   => $inst_fee,
            'totalAssessment'  => $totalOut,
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
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'eval_id and student_id required']); return;
    }

    $stmt = $conn->prepare("UPDATE tor_evaluations SET status='Rejected', registrar_notes=?, evaluated_by=?, evaluated_at=NOW() WHERE id=? AND student_id=?");
    $stmt->bind_param("siii", $notes, $registrar_id, $eval_id, $student_id);
    $stmt->execute();

    $torRejSt = $conn->prepare("UPDATE students SET tor_eval_status = 'Rejected' WHERE id = ?");
    $torRejSt->bind_param('i', $student_id);
    $torRejSt->execute();
    $torRejSt->close();

    while (ob_get_level() > 0) { ob_end_clean(); }
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
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$student_id) { echo json_encode(['success' => false, 'message' => 'student_id required']); return; }

    // Get student's program
    $stSt2 = $conn->prepare("SELECT program, student_type FROM students WHERE id = ? LIMIT 1");
    $stSt2->bind_param('i', $student_id);
    $stSt2->execute();
    $st = $stSt2->get_result();
    $stSt2->close();
    $st_row = $st ? $st->fetch_assoc() : null;
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$st_row) { echo json_encode(['success' => false, 'message' => 'Student not found']); return; }

    $p = $st_row['program'];

    // Resolve program code from programs table.
    // students.program may store the full name; courses.program stores the code.
    $pc_rowSt3 = $conn->prepare("SELECT code FROM programs WHERE name = ? OR code = ? LIMIT 1");
    $pc_rowSt3->bind_param('ss', $p, $p);
    $pc_rowSt3->execute();
    $pc_row = $pc_rowSt3->get_result();
    $pc_rowSt3->close();
    $programCode = ($pc_row && $pc_row->num_rows > 0) ? $pc_row->fetch_assoc()['code'] : $st_row['program'];
    $pc = $programCode;

    // All program courses — match via junction table (uses pr.name/code)
    // UNION with courses.program using RESOLVED CODE to avoid wrongly-tagged courses
    // FIX REG-CURRICULUM-02: replace raw $p/$pc interpolation with prepared statement
    $currSt = $conn->prepare("
        SELECT c.id, c.code, c.name, c.credits, c.year_level, c.semester, c.description,
               COALESCE(c.lec_units, c.credits) AS lec_units,
               COALESCE(c.lab_units, 0)         AS lab_units,
               COALESCE(c.is_general, 0)        AS is_general,
               COALESCE(c.is_lab, 0)            AS is_lab
        FROM program_courses pc
        JOIN programs pr ON pc.program_id = pr.id
        JOIN courses c   ON pc.course_id  = c.id
        WHERE pr.name = ? OR pr.code = ? OR pr.code = ?
        UNION
        SELECT id, code, name, credits, year_level, semester, description,
               COALESCE(lec_units, credits) AS lec_units,
               COALESCE(lab_units, 0)       AS lab_units,
               COALESCE(is_general, 0)      AS is_general,
               COALESCE(is_lab, 0)          AS is_lab
        FROM courses WHERE program = ?
        ORDER BY year_level, semester, code
    ");
    if ($currSt) {
        $currSt->bind_param('ssss', $p, $p, $pc, $pc);
        $currSt->execute();
        $result = $currSt->get_result();
        $currSt->close();
    } else {
        $result = null;
    }

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
    // FIX REG-CURRICULUM-01: enrollments has no plain 'grade' column.
    // Use overall_grade (computed average stored by grading flow) for display.
    $enrollment_status = [];
    $grades            = [];
    $er = $conn->query("
        SELECT course_id, status, overall_grade AS grade FROM enrollments
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
                'code'         => cleanCode($r['code']),
                'name'         => $r['name'],
                'credits'      => (int)$r['credits'],
                'lecUnits'     => (int)($r['lec_units'] ?? $r['credits']),
                'labUnits'     => (int)($r['lab_units'] ?? 0),
                'isGeneral'    => (bool)($r['is_general'] ?? false),
                'isLab'        => (bool)($r['is_lab'] ?? false),
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

    while (ob_get_level() > 0) { ob_end_clean(); }
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
        while (ob_get_level() > 0) { ob_end_clean(); }
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
            while (ob_get_level() > 0) { ob_end_clean(); }
            echo json_encode(['success' => false, 'message' => 'Could not create uploads folder.']);
            return;
        }
    }

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        // No file uploaded — still return success (document step is optional)
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => true, 'message' => 'No file uploaded.', 'file_url' => '']);
        return;
    }

    $ext     = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed)) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'Only PDF and image files allowed.']);
        return;
    }

    $filename = $document_type . '_' . $student_id . '_' . time() . '.' . $ext;
    $dest     = $uploadDir . $filename;

    if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
        while (ob_get_level() > 0) { ob_end_clean(); }
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
    $baseUrl = getUploadBaseUrl();
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'      => true,
        'message'      => 'Document uploaded successfully.',
        'file_name'    => $filename,
        'file_url'     => $baseUrl . $filename,
        'document_type'=> $document_type,
    ]);
}
// ================================================================
// UPLOAD SCHOLARSHIP PROOF
// POST ?action=upload_scholar_proof  (multipart/form-data)
// Fields: student_id, proof_file (file: PDF or image)
// Called right after enrollment registration — stores the voucher /
// award letter the student uploaded as proof for Accounting review.
// ================================================================
function uploadScholarProof(mysqli $conn): void {
    $student_id = (int)($_POST['student_id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }

    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'student_id required']);
        return;
    }

    $scriptDir = dirname($_SERVER['SCRIPT_FILENAME']);
    $uploadDir = $scriptDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            echo json_encode(['success' => false, 'message' => 'Could not create uploads folder.']);
            return;
        }
    }

    if (empty($_FILES['proof_file']) || $_FILES['proof_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'proof_file is required.']);
        return;
    }

    $ext     = strtolower(pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Only PDF or image files are allowed.']);
        return;
    }

    $filename = 'scholar_proof_' . $student_id . '_' . time() . '.' . $ext;
    $dest     = $uploadDir . $filename;

    if (!move_uploaded_file($_FILES['proof_file']['tmp_name'], $dest)) {
        echo json_encode(['success' => false, 'message' => 'File move failed. Check folder permissions.']);
        return;
    }

    $conn->query("ALTER TABLE student_scholarships ADD COLUMN IF NOT EXISTS claim_code VARCHAR(30) DEFAULT NULL");
    $conn->query("ALTER TABLE student_scholarships ADD COLUMN IF NOT EXISTS proof_file VARCHAR(255) DEFAULT NULL");

    $upd = $conn->prepare("
        UPDATE student_scholarships
        SET    proof_file = ?
        WHERE  student_id = ?
          AND  status     = 'pending'
        ORDER  BY id DESC
        LIMIT  1
    ");
    $upd->bind_param('si', $filename, $student_id);
    $upd->execute();
    $upd->close();

    $baseUrl = getUploadBaseUrl();

    echo json_encode([
        'success'   => true,
        'message'   => 'Scholarship proof uploaded.',
        'file_name' => $filename,
        'file_url'  => $baseUrl . $filename,
    ]);
}

// ================================================================
// STUDENT MASTERLIST
// ================================================================
function getMasterlistStudents($conn) {
    $page      = max(1, (int)($_GET['page']       ?? 1));
    $limit     = min(100, max(10, (int)($_GET['limit'] ?? 20)));
    $offset    = ($page - 1) * $limit;
    $search     = trim($_GET['q']          ?? '');
    $category   = trim($_GET['category']   ?? '');
    $program    = trim($_GET['program']    ?? '');
    $yearLevel  = trim($_GET['year_level'] ?? '');
    $status     = trim($_GET['status']     ?? '');
    $department = trim($_GET['department'] ?? '');
    $strand        = trim($_GET['strand']   ?? '');
    $scholarFilter = trim($_GET['scholar']  ?? ''); // '1' = scholars only, '0' = non-scholars only
    $paymentFilter = trim($_GET['payment']  ?? ''); // 'Pending','Partial','Paid','Free'

    $where  = ['1=1'];
    $params = [];
    $types  = '';

    if ($search) {
        $sq = '%' . $search . '%';
        $where[] = '(s.student_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR CONCAT(s.first_name,\' \',s.last_name) LIKE ? OR u.email LIKE ?)';
        array_push($params, $sq, $sq, $sq, $sq, $sq);
        $types .= 'sssss';
    }
    if ($category)  { $where[] = 's.student_category = ?'; $params[] = $category;  $types .= 's'; }
    if ($program)   { $where[] = 's.program = ?';          $params[] = $program;   $types .= 's'; }
    if ($yearLevel) { $where[] = 's.year_level = ?';       $params[] = $yearLevel; $types .= 's'; }
    if ($status)     { $where[] = 's.enrollment_status = ?'; $params[] = $status;     $types .= 's'; }
    if ($department) { $where[] = 'p_dept.department = ?';   $params[] = $department; $types .= 's'; }
    if ($strand)     { $where[] = 's.strand = ?';            $params[] = $strand;     $types .= 's'; }
    // Scholar filter: checks live from student_scholarships for accuracy
    if ($scholarFilter === '1') {
        $where[] = '(SELECT COUNT(*) FROM student_scholarships ss WHERE ss.student_id = s.id AND ss.is_active=1) > 0';
    } elseif ($scholarFilter === '0') {
        $where[] = '(SELECT COUNT(*) FROM student_scholarships ss WHERE ss.student_id = s.id AND ss.is_active=1) = 0';
    }
    // Payment status filter
    if ($paymentFilter) { $where[] = 's.payment_status = ?'; $params[] = $paymentFilter; $types .= 's'; }

    $whereStr = implode(' AND ', $where);

    // Count (LEFT JOIN programs to allow department filter)
    // FIX: added JOIN users so u.email search filter works in WHERE clause
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM students s LEFT JOIN programs p_dept ON p_dept.name = s.program LEFT JOIN users u ON u.id = s.user_id WHERE $whereStr");
    if ($params) $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $total = (int)$countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    // Data — derive department from programs table (students table has no department column)
    $dataStmt = $conn->prepare("
        SELECT s.id, s.student_number, s.first_name, s.last_name, s.middle_name, s.suffix,
               u.email, s.phone, s.date_of_birth, TIMESTAMPDIFF(YEAR, s.date_of_birth, NOW()) AS age, s.sex, s.religion,
               s.place_of_birth, s.citizenship, s.mother_tongue, s.address,
               s.lrn_no, s.psa_birth_cert_no, s.is_indigenous,
               s.has_special_needs, s.special_needs_details,
               s.has_assistive_tech, s.assistive_tech_details,
               s.strand, COALESCE(p_dept.department,'') AS department,
               s.learning_delivery, s.last_school_attended,
               (SELECT sg.guardian_name FROM student_guardians sg WHERE sg.student_id = s.id LIMIT 1) AS guardian_name,
               (SELECT sg.address FROM student_guardians sg WHERE sg.student_id = s.id LIMIT 1) AS guardian_address,
               (SELECT sg.contact FROM student_guardians sg WHERE sg.student_id = s.id LIMIT 1) AS guardian_contact,
               (SELECT sg.email FROM student_guardians sg WHERE sg.student_id = s.id AND sg.email IS NOT NULL AND TRIM(sg.email) != '' ORDER BY sg.is_emergency DESC, sg.id ASC LIMIT 1) AS guardian_email,
               s.program, s.year_level, s.semester, s.student_type,
               s.student_category, s.enrollment_status, s.payment_status,
               s.approval_status, (SELECT COUNT(*) FROM student_scholarships ss WHERE ss.student_id = s.id AND ss.is_active=1) > 0 AS is_scholar, (SELECT ss.scholar_type FROM student_scholarships ss WHERE ss.student_id = s.id AND ss.is_active=1 LIMIT 1) AS scholar_type,
               DATE_FORMAT(s.enrollment_date,'%Y-%m-%d') AS enrollment_date,
               s.tor_file, s.tor_eval_status
        FROM students s
        LEFT JOIN programs p_dept ON p_dept.name = s.program
        LEFT JOIN users u ON u.id = s.user_id
        WHERE $whereStr
        ORDER BY s.last_name, s.first_name
        LIMIT ? OFFSET ?
    ");
    $allP = array_merge($params, [$limit, $offset]);
    $allT = $types . 'ii';
    $dataStmt->bind_param($allT, ...$allP);
    $dataStmt->execute();
    $res = $dataStmt->get_result();

    $students = [];
    while ($r = $res->fetch_assoc()) {
        $students[] = [
            'id'                  => (int)$r['id'],
            'studentNumber'       => $r['student_number'],
            'firstName'           => $r['first_name'],
            'lastName'            => $r['last_name'],
            'middleName'          => $r['middle_name'] ?? '',
            'suffix'              => $r['suffix'] ?? '',
            'fullName'            => $r['first_name'] . ' ' . $r['last_name'],
            'email'               => $r['email'] ?? '',
            'phone'               => $r['phone'] ?? '',
            'dateOfBirth'         => $r['date_of_birth'] ?? '',
            'age'                 => $r['age'] ?? '',
            'sex'                 => $r['sex'] ?? '',
            'religion'            => $r['religion'] ?? '',
            'placeOfBirth'        => $r['place_of_birth'] ?? '',
            'citizenship'         => $r['citizenship'] ?? '',
            'motherTongue'        => $r['mother_tongue'] ?? '',
            'address'             => $r['address'] ?? '',
            'lrnNo'               => $r['lrn_no'] ?? '',
            'psaBirthCertNo'      => $r['psa_birth_cert_no'] ?? '',
            'isIndigenous'        => $r['is_indigenous'] ?? '',
            'hasSpecialNeeds'     => $r['has_special_needs'] ?? '',
            'specialNeedsDetails' => $r['special_needs_details'] ?? '',
            'hasAssistiveTech'    => $r['has_assistive_tech'] ?? '',
            'assistiveTechDetails'=> $r['assistive_tech_details'] ?? '',
            'strand'              => $r['strand'] ?? '',
            // FIX COE-DEPT-TVET-01: TVET/SHS programs are stored under the College
            // department in programs.department (e.g. "ICTD"). Override with the
            // correct label so the student list and any downstream views show the
            // right department — consistent with getProfile() and saveSoaSnapshot().
            'department'          => match(strtoupper(trim($r['student_category'] ?? ''))) {
                'TVET'  => 'Technical-Vocational Education and Training (TVET)',
                'SHS'   => 'Senior High School (SHS)',
                default => $r['department'] ?? '',
            },
            'learningDelivery'    => $r['learning_delivery'] ?? '',
            'lastSchoolAttended'  => $r['last_school_attended'] ?? '',
            'guardianName'        => $r['guardian_name'] ?? '',
            'guardianAddress'     => $r['guardian_address'] ?? '',
            'guardianContact'     => $r['guardian_contact'] ?? '',
            'guardianEmail'       => $r['guardian_email'] ?? '',
            'program'             => $r['program'],
            'yearLevel'           => $r['year_level'],
            'semester'            => $r['semester'] ?? '',
            'studentType'         => $r['student_type'] ?? '',
            'studentCategory'     => $r['student_category'] ?? 'College',
            'enrollmentStatus'    => $r['enrollment_status'] ?? '',
            'paymentStatus'       => $r['payment_status'] ?? '',
            'approvalStatus'      => $r['approval_status'] ?? '',
            'isScholar'           => (int)$r['is_scholar'],
            'scholarType'         => $r['scholar_type'] ?? '',
            'enrollmentDate'      => $r['enrollment_date'] ?? '',
            'torEvalStatus'       => $r['tor_eval_status'] ?? '',
            'torFile'             => $r['tor_file'] ?? null,
            'initials'            => strtoupper(substr($r['first_name'],0,1) . substr($r['last_name'],0,1)),
        ];
    }
    $dataStmt->close();

    // Programs list for filter dropdown
    $progRes  = $conn->query("SELECT DISTINCT program FROM students ORDER BY program");
    $programs = [];
    while ($p = $progRes->fetch_assoc()) { if ($p['program']) $programs[] = $p['program']; }

    // Departments list for College filter — derived from programs table
    $deptRes  = $conn->query("SELECT DISTINCT p2.department FROM students s2 JOIN programs p2 ON p2.name = s2.program WHERE s2.student_category='College' AND p2.department IS NOT NULL AND p2.department != '' ORDER BY p2.department");
    $departments = [];
    while ($d = $deptRes->fetch_assoc()) { if ($d['department']) $departments[] = $d['department']; }

    while (ob_get_level() > 0) { ob_end_clean(); }
    global $authUser;
    $students = applyPrivacyList($students, $authUser, 'student');
    echo json_encode([
        'success'     => true,
        'students'    => $students,
        'total'       => $total,
        'page'        => $page,
        'limit'       => $limit,
        'totalPages'  => (int)ceil($total / $limit),
        'programs'    => $programs,
        'departments' => $departments,
    ]);
}

// ================================================================
// SUBJECTS MASTERLIST
// ================================================================
function getMasterlistSubjects($conn) {
    $search   = trim($_GET['q']        ?? '');
    $category = trim($_GET['category'] ?? '');
    $semester = trim($_GET['semester'] ?? '');
    $course   = trim($_GET['course']   ?? '');

    // FIX: initialize $params and $types before conditionally appending to them
    $where  = ['e.status IN ("Enrolled","Pending","Completed")'];
    $params = [];
    $types  = '';

    if ($search) {
        $sq = '%' . $search . '%';
        // FIX: use prepared-statement placeholders for search terms to avoid SQL injection
        $where[] = '(s.first_name LIKE ? OR s.last_name LIKE ? OR CONCAT(s.first_name,\' \',s.last_name) LIKE ? OR s.student_number LIKE ? OR c.code LIKE ? OR c.name LIKE ?)';
        array_push($params, $sq, $sq, $sq, $sq, $sq, $sq);
        $types  .= 'ssssss';
    }
    if ($category) { $where[] = 's.student_category=?'; $params[] = $category; $types .= 's'; }
    if ($semester) { $semLike = '%' . $semester . '%'; $where[] = 'e.semester LIKE ?'; $params[] = $semLike; $types .= 's'; }
    if ($course)   { $where[] = 'c.code=?'; $params[] = $course; $types .= 's'; }

    $whereStr = implode(' AND ', $where);

    // FIX: use prepared statement so bind_param is actually invoked for all filter params
    $stmt = $conn->prepare("
        SELECT s.student_number, s.first_name, s.last_name, s.program, s.year_level,
               c.code AS course_code, c.name AS course_name, c.credits,
               TRIM(CONCAT(COALESCE(f.first_name,''),' ',COALESCE(f.last_name,''))) AS instructor,
               e.semester,
               sg_p.grade AS prelim_grade,
               sg_m.grade AS midterm_grade,
               sg_f.grade AS final_grade,
               e.status
        FROM enrollments e
        JOIN students s     ON s.id = e.student_id
        JOIN courses c      ON c.id = e.course_id
        LEFT JOIN faculty f ON f.user_id = c.faculty_id
        LEFT JOIN student_grades sg_p ON sg_p.enrollment_id = e.id AND sg_p.term = 'Prelim'
        LEFT JOIN student_grades sg_m ON sg_m.enrollment_id = e.id AND sg_m.term = 'Midterm'
        LEFT JOIN student_grades sg_f ON sg_f.enrollment_id = e.id AND sg_f.term = 'Final'
        WHERE $whereStr
        ORDER BY s.last_name, s.first_name, c.code
        LIMIT 1000
    ");
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $records = [];
    $courseSet = [];
    while ($r = $res->fetch_assoc()) {
        // FIX: column aliases in getMasterlistSubjects SQL are prelim_grade etc.
        $prelim  = isset($r['prelim_grade'])  && $r['prelim_grade']  !== null ? (float)$r['prelim_grade']  : null;
        $midterm = isset($r['midterm_grade']) && $r['midterm_grade'] !== null ? (float)$r['midterm_grade'] : null;
        $final   = isset($r['final_grade'])   && $r['final_grade']   !== null ? (float)$r['final_grade']   : null;
        $vals    = array_filter([$prelim,$midterm,$final], fn($v) => $v !== null);
        $overall = count($vals) > 0 ? round(array_sum($vals)/count($vals), 2) : null;
        $records[] = [
            'studentNumber' => $r['student_number'],
            'fullName'      => $r['first_name'] . ' ' . $r['last_name'],
            'program'       => $r['program'],
            'yearLevel'     => $r['year_level'],
            'courseCode'    => $r['course_code'],
            'courseName'    => $r['course_name'],
            'credits'       => (int)$r['credits'],
            'instructor'    => $r['instructor'] ?? '',
            'semester'      => $r['semester'] ?? '',
            'prelimGrade'   => $prelim,
            'midtermGrade'  => $midterm,
            'finalGrade'    => $final,
            'overall'       => $overall,
            'remarks'       => $final !== null ? ($overall <= 3.0 ? 'Passed' : 'Failed') : 'In Progress',
            'status'        => $r['status'],
        ];
        $courseSet[$r['course_code']] = true;
    }

    $stmt->close();
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success' => true,
        'records' => $records,
        'courses' => array_keys($courseSet),
    ]);
}

// ================================================================
// COURSES MASTERLIST — list all courses with enrollment counts
// ================================================================
function getMasterlistCourses($conn) {
    $search   = trim($_GET['q']          ?? '');
    $semester = trim($_GET['semester']   ?? '');
    $dept     = trim($_GET['department'] ?? '');

    $where = ['1=1'];
    if ($search) {
        $sq = '%' . $search . '%';
        // FIX: alias syntax (AS instructor) not valid in WHERE clause
        $where[] = "(c.code LIKE '$sq' OR c.name LIKE '$sq' OR CONCAT(COALESCE(f.first_name,''),' ',COALESCE(f.last_name,'')) LIKE '$sq')";
    }
    if ($semester) { $semLike2 = '%' . $semester . '%'; $where[] = 'c.semester LIKE ?'; $params[] = $semLike2; $types .= 's'; }
    if ($dept)     { $where[] = 'c.department = ?'; $params[] = $dept; $types .= 's'; }
    $whereStr = implode(' AND ', $where);

    // FIX: LEFT JOINs were inside SELECT list (invalid SQL). cs.schedule doesn't exist.
    // Grades now in student_grades. Moved JOINs to FROM clause, fixed all columns.
    $res = $conn->query("
        SELECT c.id, c.code, c.name, c.credits,
               TRIM(CONCAT(COALESCE(f.first_name,''),' ',COALESCE(f.last_name,''))) AS instructor,
               COALESCE(c.department,'') AS department,
               COALESCE(c.program,'') AS program,
               COALESCE(c.year_level,'') AS year_level,
               COALESCE(c.semester,'') AS semester,
               COALESCE(cs.day,'') AS day,
               CONCAT(COALESCE(cs.time_start,''),' - ',COALESCE(cs.time_end,'')) AS time,
               COALESCE(r.room_name,'') AS room,
               COALESCE(c.capacity,40) AS capacity,
               COUNT(e.id) AS enrolled_count,
               COUNT(DISTINCT CASE WHEN sg_p.term='Prelim'  THEN e.id END) AS prelim_done,
               COUNT(DISTINCT CASE WHEN sg_m.term='Midterm' THEN e.id END) AS midterm_done,
               COUNT(DISTINCT CASE WHEN sg_f.term='Final'   THEN e.id END) AS final_done
        FROM courses c
        LEFT JOIN faculty f ON f.user_id = c.faculty_id
        LEFT JOIN course_sections cs ON cs.course_id = c.id AND cs.is_active = 1
        LEFT JOIN rooms r ON r.id = cs.room_id
        LEFT JOIN enrollments e ON e.course_id = c.id AND e.status IN ('Enrolled','Pending','Completed')
        LEFT JOIN student_grades sg_p ON sg_p.enrollment_id = e.id AND sg_p.term = 'Prelim'
        LEFT JOIN student_grades sg_m ON sg_m.enrollment_id = e.id AND sg_m.term = 'Midterm'
        LEFT JOIN student_grades sg_f ON sg_f.enrollment_id = e.id AND sg_f.term = 'Final'
        WHERE $whereStr
        GROUP BY c.id
        ORDER BY c.code
    ");

    $courses = [];
    $deptSet = [];
    while ($r = $res->fetch_assoc()) {
        $courses[] = [
            'id'           => (int)$r['id'],
            'code'         => cleanCode($r['code']),
            'name'         => $r['name'],
            'credits'      => (int)$r['credits'],
            'instructor'   => $r['instructor'] ?? '',
            'department'   => $r['department'],
            'program'      => $r['program'],
            'yearLevel'    => $r['year_level'],
            'semester'     => $r['semester'],
            'schedule'     => $r['schedule'],
            'day'          => $r['day'],
            'time'         => $r['time'],
            'room'         => $r['room'],
            'capacity'     => 0, // removed from query (no direct column)
            'enrolledCount'=> (int)$r['enrolled_count'],
            'prelimDone'   => (int)$r['prelim_done'],
            'midtermDone'  => (int)$r['midterm_done'],
            'finalDone'    => (int)$r['final_done'],
        ];
        if ($r['department']) $deptSet[$r['department']] = true;
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'     => true,
        'courses'     => $courses,
        'departments' => array_keys($deptSet),
    ]);
}

// ================================================================
// COURSE STUDENTS — enrolled students for one course (lazy load)
// ================================================================
function getMasterlistCourseStudents($conn) {
    $courseId = (int)($_GET['course_id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$courseId) { echo json_encode(['success'=>false,'message'=>'Missing course_id']); return; }

    // FIX: grades now in student_grades, not enrollments columns
    $stmt = $conn->prepare("
        SELECT s.student_number, s.first_name, s.last_name, s.program, s.year_level,
               sg_p.grade AS prelim_grade, sg_m.grade AS midterm_grade,
               sg_f.grade AS final_grade, NULL AS overall_grade, e.remarks
        FROM enrollments e
        JOIN students s ON s.id = e.student_id
        LEFT JOIN student_grades sg_p ON sg_p.enrollment_id = e.id AND sg_p.term = 'Prelim'
        LEFT JOIN student_grades sg_m ON sg_m.enrollment_id = e.id AND sg_m.term = 'Midterm'
        LEFT JOIN student_grades sg_f ON sg_f.enrollment_id = e.id AND sg_f.term = 'Final'
        WHERE e.course_id = ? AND e.status IN ('Enrolled','Pending','Completed')
        ORDER BY s.last_name, s.first_name
    ");
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $res = $stmt->get_result();

    $students = [];
    while ($r = $res->fetch_assoc()) {
        $p  = $r['prelim_grade']  !== null ? (float)$r['prelim_grade']  : null;
        $m  = $r['midterm_grade'] !== null ? (float)$r['midterm_grade'] : null;
        $f  = $r['final_grade']   !== null ? (float)$r['final_grade']   : null;
        $ov = $r['overall_grade'] !== null ? (float)$r['overall_grade'] : null;
        if ($ov === null && ($p||$m||$f)) {
            $vals = array_filter([$p,$m,$f], fn($v) => $v !== null);
            $ov = count($vals) ? round(array_sum($vals)/count($vals),2) : null;
        }
        $remarks = $r['remarks'] ?: ($f !== null ? ($ov <= 3.0 ? 'Passed':'Failed') : 'In Progress');
        $students[] = [
            'studentNumber' => $r['student_number'],
            'fullName'      => $r['first_name'] . ' ' . $r['last_name'],
            'program'       => $r['program'],
            'yearLevel'     => $r['year_level'],
            'prelimGrade'   => $p,
            'midtermGrade'  => $m,
            'finalGrade'    => $f,
            'overall'       => $ov,
            'remarks'       => $remarks,
        ];
    }
    $stmt->close();
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success'=>true,'students'=>$students]);
}

// ================================================================
// PROGRAMS LIST (College / SHS / TVET) with enrolled count
// ================================================================
function getMasterlistPrograms($conn) {
    $level = trim($_GET['level'] ?? 'College');
    $allowed = ['College','SHS','TVET'];
    if (!in_array($level, $allowed)) $level = 'College';

    $res = $conn->prepare("
        SELECT p.id, p.name, p.code, p.level_type, p.department,
               COALESCE(p.description,'') AS description,
               COALESCE(p.duration,0) AS duration,
               COUNT(DISTINCT s.id) AS total_enrolled
        FROM programs p
        LEFT JOIN students s ON s.program = p.name
            AND s.enrollment_status IN ('Enrolled','Approved','Pending')
        WHERE p.level_type = ?
        GROUP BY p.id
        ORDER BY p.name
    ");
    $res->bind_param('s', $level);
    $res->execute();
    $result = $res->get_result();

    $programs = [];
    while ($r = $result->fetch_assoc()) {
        $programs[] = [
            'id'            => (int)$r['id'],
            'name'          => $r['name'],
            'code'          => cleanCode($r['code']),
            'levelType'     => $r['level_type'],
            'department'    => $r['department'] ?? '',
            'description'   => $r['description'],
            'duration'      => (int)$r['duration'],
            'totalEnrolled' => (int)$r['total_enrolled'],
        ];
    }
    $res->close();
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'programs' => $programs]);
}

// ================================================================
// SUBJECTS FOR A PROGRAM (via courses.program name match)
// ================================================================
function getMasterlistProgramSubjects($conn) {
    $programId = (int)($_GET['program_id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$programId) { echo json_encode(['success'=>false,'message'=>'Missing program_id']); return; }

    // Get program name first
    $pStmt = $conn->prepare("SELECT name FROM programs WHERE id = ?");
    $pStmt->bind_param('i', $programId);
    $pStmt->execute();
    $pRow = $pStmt->get_result()->fetch_assoc();
    $pStmt->close();
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$pRow) { echo json_encode(['success'=>false,'message'=>'Program not found']); return; }
    $progName = $pRow['name'];

    // FIX: LEFT JOINs were inside SELECT list (invalid SQL). Broken COALESCE/CONCAT.
    // cs.schedule doesn't exist. Moved all JOINs to FROM clause.
    $res = $conn->query("
        SELECT DISTINCT c.id, c.code, c.name, c.credits,
               TRIM(CONCAT(COALESCE(f.first_name,''),' ',COALESCE(f.last_name,''))) AS instructor,
               COALESCE(c.semester,'') AS semester,
               COALESCE(cs.day,'') AS day,
               CONCAT(COALESCE(cs.time_start,''),' - ',COALESCE(cs.time_end,'')) AS time,
               COALESCE(r.room_name,'') AS room,
               COALESCE(c.year_level,'') AS year_level,
               COALESCE(c.is_general,0) AS is_general,
               (SELECT COUNT(*) FROM enrollments e
                WHERE e.course_id = c.id
                AND e.status IN ('Enrolled','Pending','Completed')) AS enrolled_count
        FROM courses c
        LEFT JOIN faculty f ON f.user_id = c.faculty_id
        LEFT JOIN course_sections cs ON cs.course_id = c.id AND cs.is_active = 1
        LEFT JOIN rooms r ON r.id = cs.room_id
        LEFT JOIN program_courses pc ON pc.course_id = c.id
        LEFT JOIN programs p2 ON p2.id = pc.program_id
        WHERE p2.name = ?
           OR c.program = ?
           OR c.is_general = 1
        ORDER BY c.is_general, c.year_level, c.semester, c.code
    ");

    $subjects = [];
    while ($r = $res->fetch_assoc()) {
        $subjects[] = [
            'id'           => (int)$r['id'],
            'code'         => cleanCode($r['code']),
            'name'         => $r['name'],
            'credits'      => (int)$r['credits'],
            'instructor'   => $r['instructor'],
            'semester'     => $r['semester'],
            'day'          => $r['day'],
            'time'         => $r['time'],
            'room'         => $r['room'],
            'capacity'     => 0, // removed from query (no direct column)
            'yearLevel'    => $r['year_level'],
            'isGeneral'    => (bool)$r['is_general'],
            'enrolledCount'=> (int)$r['enrolled_count'],
        ];
    }
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'subjects' => $subjects]);
}
// ================================================================
// REPORT: Students enrolled per program per school year
// Returns rows: { program, ay, count, yearLevel }
// ================================================================
function reportStudentsPerYear($conn) {
    $level    = trim($_GET['level']      ?? 'College');
    $progId   = (int)($_GET['program_id'] ?? 0);
    $yearLvl  = trim($_GET['year_level'] ?? '');

    // Build program filter
    $progFilter = '';
    if ($progId > 0) {
        $pStmt = $conn->prepare("SELECT name FROM programs WHERE id = ?");
        $pStmt->bind_param('i', $progId);
        $pStmt->execute();
        $pRow = $pStmt->get_result()->fetch_assoc();
        $pStmt->close();
        if ($pRow) {
            $pn = $pRow['name'];
            $progFilter = "AND s.program = '$pn'";
        }
    } else {
        // Filter by level_type via join
        $lv = $level;
        $progFilter = "AND EXISTS (SELECT 1 FROM programs p WHERE p.name = s.program AND p.level_type = '$lv')";
    }

    $ylFilter = '';
    if ($yearLvl) {
        $yl = $yearLvl;
        $ylFilter = "AND s.year_level = '$yl'";
    }

    // Extract AY from semester string: "1st Semester, AY 2025-2026" → "AY 2025-2026"
    // Also group by program and year_level
    $res = $conn->query("
        SELECT
            s.program,
            s.year_level,
            CASE
                WHEN s.semester REGEXP 'AY [0-9]{4}-[0-9]{4}'
                THEN REGEXP_SUBSTR(s.semester, 'AY [0-9]{4}-[0-9]{4}')
                WHEN s.enrollment_date IS NOT NULL
                THEN CONCAT('AY ', YEAR(s.enrollment_date), '-', YEAR(s.enrollment_date)+1)
                ELSE 'AY Unknown'
            END AS school_year,
            COUNT(*) AS student_count
        FROM students s
        WHERE s.enrollment_status IN ('Enrolled','Approved','Completed','Pending')
        $progFilter
        $ylFilter
        GROUP BY s.program, s.year_level, school_year
        ORDER BY school_year DESC, s.program, s.year_level
    ");

    $rows = [];
    $years   = [];
    $programs = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = [
            'program'     => $r['program'],
            'yearLevel'   => $r['year_level'],
            'schoolYear'  => $r['school_year'],
            'count'       => (int)$r['student_count'],
        ];
        $years[$r['school_year']]  = true;
        $programs[$r['program']] = true;
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'  => true,
        'rows'     => $rows,
        'years'    => array_keys($years),
        'programs' => array_keys($programs),
    ]);
}

// ================================================================
// REPORT: Students enrolled per subject per school year
// Returns rows: { subjectCode, subjectName, program, ay, count }
// ================================================================
function reportSubjectsPerYear($conn) {
    $level   = trim($_GET['level']       ?? 'College');
    $progId  = (int)($_GET['program_id'] ?? 0);
    $yearLvl = trim($_GET['year_level']  ?? '');

    $progFilter = '';
    $progJoin   = '';
    if ($progId > 0) {
        $pStmt = $conn->prepare("SELECT name FROM programs WHERE id = ?");
        $pStmt->bind_param('i', $progId);
        $pStmt->execute();
        $pRow = $pStmt->get_result()->fetch_assoc();
        $pStmt->close();
        if ($pRow) {
            $pn = $pRow['name'];
            $progFilter = "AND s.program = '$pn'";
        }
    } else {
        $lv = $level;
        $progFilter = "AND EXISTS (SELECT 1 FROM programs p WHERE p.name = s.program AND p.level_type = '$lv')";
    }

    $ylFilter = '';
    if ($yearLvl) {
        $yl = $yearLvl;
        $ylFilter = "AND c.year_level = '$yl'";
    }

    // Extract AY from enrollments.semester or students.semester
    $res = $conn->query("
        SELECT
            c.code   AS subject_code,
            c.name   AS subject_name,
            c.credits,
            COALESCE(c.year_level,'') AS year_level,
            s.program,
            CASE
                WHEN e.semester REGEXP 'AY [0-9]{4}-[0-9]{4}'
                THEN REGEXP_SUBSTR(e.semester, 'AY [0-9]{4}-[0-9]{4}')
                WHEN s.semester REGEXP 'AY [0-9]{4}-[0-9]{4}'
                THEN REGEXP_SUBSTR(s.semester, 'AY [0-9]{4}-[0-9]{4}')
                WHEN e.enrollment_date IS NOT NULL
                THEN CONCAT('AY ', YEAR(e.enrollment_date), '-', YEAR(e.enrollment_date)+1)
                ELSE 'AY Unknown'
            END AS school_year,
            COUNT(DISTINCT e.student_id) AS student_count
        FROM enrollments e
        JOIN students s  ON s.id  = e.student_id
        JOIN courses  c  ON c.id  = e.course_id
        WHERE e.status IN ('Enrolled','Pending','Completed')
        $progFilter
        $ylFilter
        GROUP BY c.id, school_year, s.program
        ORDER BY school_year DESC, c.code, s.program
    ");

    $rows    = [];
    $years   = [];
    $subjects = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = [
            'subjectCode' => $r['subject_code'],
            'subjectName' => $r['subject_name'],
            'credits'     => (int)$r['credits'],
            'yearLevel'   => $r['year_level'],
            'program'     => $r['program'],
            'schoolYear'  => $r['school_year'],
            'count'       => (int)$r['student_count'],
        ];
        $years[$r['school_year']]   = true;
        $subjects[$r['subject_code']] = true;
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'  => true,
        'rows'     => $rows,
        'years'    => array_keys($years),
        'subjects' => array_keys($subjects),
    ]);
}


// ══════════════════════════════════════════
// CERTIFICATE OF ENROLLMENT FUNCTIONS
// ══════════════════════════════════════════

// ── HELPER: Parse semester label + school_year from students.semester ────────
// students.semester stores the full label e.g. "1st Semester, AY 2025-2026".
// Returns [semLabel, schoolYear] e.g. ["1st Semester", "2025-2026"].
// Both strings are already SQL-escaped and safe to embed in queries.
function _parseSemesterLabel(mysqli $conn, int $student_id): array {
    $res = $conn->query("SELECT semester FROM students WHERE id = $student_id LIMIT 1");
    $raw = trim($res ? ($res->fetch_assoc()['semester'] ?? '') : '');
    $semLabel   = '';
    $schoolYear = '';
    if (preg_match('/^(1st Semester|2nd Semester|Summer|Midyear)/i', $raw, $sm)) {
        $semLabel = $conn->real_escape_string($sm[1]);
    }
    if (preg_match('/(\d{4}-\d{4})/', $raw, $ay)) {
        $schoolYear = $conn->real_escape_string($ay[1]);
    }
    return [$semLabel, $schoolYear];
}

// ── STUDENT: Get distinct COE semester list ───────────────────────────────────
// Returns every semester for which this student has a COE record, plus the
// current semester. Used to populate the semester dropdown in the COE Request
// component so students can view COEs from past terms.
//
// GET registrar.php?action=coe_get_semesters
// Auth: student (own record only), admin, registrar
function coeGetSemesters(mysqli $conn): void {
    global $authUser;
    $user_id = (int)($authUser['user_id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$user_id) { echo json_encode(['success'=>false,'message'=>'Authentication required']); return; }

    // Resolve student
    $sr = $conn->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
    $sr->bind_param('i', $user_id);
    $sr->execute();
    $student = $sr->get_result()->fetch_assoc();
    $sr->close();
    if (!$student) { echo json_encode(['success'=>true,'semesters'=>[]]); return; }
    $sid = (int)$student['id'];

    // Ensure semester + school_year columns exist
    $conn->query("ALTER TABLE coe_requests
        ADD COLUMN IF NOT EXISTS semester    VARCHAR(100) DEFAULT '' AFTER control_number,
        ADD COLUMN IF NOT EXISTS school_year VARCHAR(20)  DEFAULT '' AFTER semester");

    // ── Auto-backfill old COE rows that have blank semester ───────────────────
    // Strategy: join coe_requests to enrollments (which records semester per enrollment)
    // and use the semester from the enrollment closest in time to the COE request.
    // This handles students who enrolled across multiple semesters.
    $backfill = $conn->query("
        SELECT cr.id,
               e.semester AS enroll_sem
        FROM coe_requests cr
        JOIN enrollments e ON e.student_id = cr.student_id
        WHERE cr.student_id = $sid
          AND (cr.semester IS NULL OR TRIM(cr.semester) = '')
          AND e.semester IS NOT NULL AND TRIM(e.semester) != ''
        GROUP BY cr.id
        ORDER BY cr.id, ABS(DATEDIFF(cr.requested_at, e.enrollment_date)) ASC
    ");
    if ($backfill) {
        $seen = [];
        while ($brow = $backfill->fetch_assoc()) {
            $coeId  = (int)$brow['id'];
            if (isset($seen[$coeId])) continue;   // only use closest-date enrollment
            $seen[$coeId] = true;
            $rawSem = trim($brow['enroll_sem'] ?? '');
            if ($rawSem === '') continue;
            $semLabel   = '';
            $schoolYear = '';
            if (preg_match('/^(1st Semester|2nd Semester|Summer|Midyear)/i', $rawSem, $sm)) {
                $semLabel = $conn->real_escape_string($sm[1]);
            }
            if (preg_match('/(\d{4}-\d{4})/', $rawSem, $ay)) {
                $schoolYear = $conn->real_escape_string($ay[1]);
            }
            if ($semLabel !== '' && $schoolYear !== '') {
                $conn->query("UPDATE coe_requests
                    SET semester='$semLabel', school_year='$schoolYear'
                    WHERE id=$coeId AND student_id=$sid");
            }
        }
    }

    // FIX COE-ALLTERMS-01: Ensure every enrollment term has an Approved COE row.
    // Must run before we build semMap so has_approved_coe tags are accurate.
    autoEnsureAllTermCoes($conn, $sid);

    // ── Also pull all distinct semesters from enrollments history ─────────────
    // This catches cases where student has enrollment records but no COE row yet
    // for that semester (e.g. they re-enrolled but never requested a COE).
    $semMap = [];

    $enrRes = $conn->query("
        SELECT DISTINCT semester FROM enrollments
        WHERE student_id = $sid
          AND semester IS NOT NULL AND TRIM(semester) != ''
        ORDER BY semester DESC
    ");
    if ($enrRes) {
        while ($row = $enrRes->fetch_assoc()) {
            $raw = trim($row['semester']);
            $semLabel   = '';
            $schoolYear = '';
            if (preg_match('/^(1st Semester|2nd Semester|Summer|Midyear)/i', $raw, $sm)) {
                $semLabel = $sm[1];
            }
            if (preg_match('/(\d{4}-\d{4})/', $raw, $ay)) {
                $schoolYear = $ay[1];
            }
            if ($semLabel !== '' && $schoolYear !== '') {
                $key = $semLabel . '|' . $schoolYear;
                $semMap[$key] = ['label' => $semLabel . ', AY ' . $schoolYear,
                                 'semester' => $semLabel, 'school_year' => $schoolYear];
            }
        }
    }

    // ── Pull from coe_requests (now backfilled) ───────────────────────────────
    $coeRes = $conn->query("
        SELECT DISTINCT semester, school_year
        FROM coe_requests
        WHERE student_id = $sid
          AND semester    IS NOT NULL AND TRIM(semester)    != ''
          AND school_year IS NOT NULL AND TRIM(school_year) != ''
    ");
    if ($coeRes) {
        while ($row = $coeRes->fetch_assoc()) {
            $key = $row['semester'] . '|' . $row['school_year'];
            $semMap[$key] = [
                'label'       => $row['semester'] . ', AY ' . $row['school_year'],
                'semester'    => $row['semester'],
                'school_year' => $row['school_year'],
            ];
        }
    }

    // ── Always include current semester ───────────────────────────────────────
    [$curSem, $curAY] = _parseSemesterLabel($conn, $sid);
    if ($curSem !== '' && $curAY !== '') {
        $key = $curSem . '|' . $curAY;
        $semMap[$key] = ['label' => $curSem . ', AY ' . $curAY,
                         'semester' => $curSem, 'school_year' => $curAY];
    }

    // ── Sort: newest AY first, then Summer > 2nd > 1st within same AY ────────
    $list = array_values($semMap);
    usort($list, function(array $a, array $b): int {
        // Compare end year of AY (e.g. "2025-2026" → 2026)
        preg_match('/(\d{4})-(\d{4})/', $a['school_year'], $ma);
        preg_match('/(\d{4})-(\d{4})/', $b['school_year'], $mb);
        $ya = (int)($ma[2] ?? 0);
        $yb = (int)($mb[2] ?? 0);
        if ($ya !== $yb) return $yb - $ya;
        $order = ['Summer'=>3,'Midyear'=>3,'2nd Semester'=>2,'1st Semester'=>1];
        $oa = 0; $ob = 0;
        foreach ($order as $k => $v) { if (str_starts_with($a['semester'], $k)) { $oa=$v; break; } }
        foreach ($order as $k => $v) { if (str_starts_with($b['semester'], $k)) { $ob=$v; break; } }
        return $ob - $oa;
    });

    // ── Tag each entry: does an Approved COE exist for this semester? ─────────
    // Angular will default to the first entry that has has_approved_coe = true.
    $approvedSems = [];
    $aprRes = $conn->query("
        SELECT DISTINCT semester, school_year
        FROM coe_requests
        WHERE student_id = $sid AND status = 'Approved'
          AND semester != '' AND school_year != ''
    ");
    if ($aprRes) {
        while ($ar = $aprRes->fetch_assoc()) {
            $approvedSems[$ar['semester'] . '|' . $ar['school_year']] = true;
        }
    }

    $defaultSem = null;
    foreach ($list as &$entry) {
        $key = $entry['semester'] . '|' . $entry['school_year'];
        $entry['has_approved_coe'] = isset($approvedSems[$key]);
        // First entry with an approved COE becomes the suggested default
        if ($defaultSem === null && $entry['has_approved_coe']) {
            $defaultSem = $entry;
        }
    }
    unset($entry);

    // If no approved COE in any past semester, default to first (current) semester
    if ($defaultSem === null && !empty($list)) {
        $defaultSem = $list[0];
    }

    echo json_encode([
        'success'         => true,
        'semesters'       => $list,
        'default_semester'=> $defaultSem,   // Angular should select this on load
    ]);
}

// ── HELPER: Auto-create AND immediately approve a COE when student is enrolled ─
// COE no longer requires manual registrar approval — it is auto-issued the
// moment the registrar confirms the enrollment registration.
function autoApproveCoeRequest(mysqli $conn, int $student_id, int $approved_by = 0): string {
    // Ensure table exists with semester + school_year columns
    $conn->query("CREATE TABLE IF NOT EXISTS coe_requests (
        id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL,
        purpose VARCHAR(255) DEFAULT 'General Purpose', copies TINYINT DEFAULT 1,
        status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
        registrar_notes TEXT DEFAULT NULL, approved_by INT DEFAULT NULL,
        approved_at DATETIME DEFAULT NULL, control_number VARCHAR(30) DEFAULT NULL,
        semester VARCHAR(100) DEFAULT '', school_year VARCHAR(20) DEFAULT '',
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_student (student_id), INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Add semester/school_year columns if they were missing from an older schema
    $conn->query("ALTER TABLE coe_requests
        ADD COLUMN IF NOT EXISTS semester    VARCHAR(100) DEFAULT '' AFTER control_number,
        ADD COLUMN IF NOT EXISTS school_year VARCHAR(20)  DEFAULT '' AFTER semester");

    // Resolve semester + school_year from student record FIRST so the idempotency
    // check is SCOPED TO THE CURRENT SEMESTER.
    // FIX COE-SWITCH-01: The old check used no semester filter, so it returned any
    // old Approved COE (e.g. from a past term) as if it were the current one.
    // This caused the "Not Yet Enrolled" bug when switching back to the current
    // term: coeGetMyRequests found the mismatched control number and showed an
    // empty/wrong COE because the semester+school_year on that row didn't match
    // what the student just selected in the dropdown.
    [$semLabel, $schoolYear] = _parseSemesterLabel($conn, $student_id);

    // If an Approved COE already exists FOR THIS SPECIFIC SEMESTER, return it — idempotent
    if ($semLabel !== '' && $schoolYear !== '') {
        $safeSem = $conn->real_escape_string($semLabel);
        $safeAY  = $conn->real_escape_string($schoolYear);
        $chk = $conn->query("SELECT id, control_number FROM coe_requests
            WHERE student_id = $student_id AND status = 'Approved'
              AND semester = '$safeSem' AND school_year = '$safeAY'
            LIMIT 1");
        if ($chk && $chk->num_rows > 0) {
            return (string)($chk->fetch_assoc()['control_number'] ?? '');
        }
    } else {
        // Fallback for legacy rows with no semester stamp: check any approved row
        $chk = $conn->query("SELECT id, control_number FROM coe_requests
            WHERE student_id = $student_id AND status = 'Approved'
              AND (semester = '' OR semester IS NULL)
            LIMIT 1");
        if ($chk && $chk->num_rows > 0) {
            return (string)($chk->fetch_assoc()['control_number'] ?? '');
        }
    }

    // Generate control number: COE-YYYYMM-XXXX
    $yr  = date('Ym');
    $seq = (int)(($conn->query("SELECT COUNT(*) AS c FROM coe_requests WHERE status='Approved' AND DATE_FORMAT(approved_at,'%Y%m') = '$yr'"))->fetch_assoc()['c'] ?? 0);
    $ctrl = 'COE-' . $yr . '-' . str_pad($seq + 1, 4, '0', STR_PAD_LEFT);

    // Upsert: upgrade any existing Pending row to Approved, or insert new Approved row
    $existing = $conn->query("SELECT id FROM coe_requests WHERE student_id = $student_id AND status = 'Pending' ORDER BY id DESC LIMIT 1");
    if ($existing && $existing->num_rows > 0) {
        $eid = (int)$existing->fetch_assoc()['id'];
        $conn->query("UPDATE coe_requests SET status='Approved', approved_by=$approved_by, approved_at=NOW(), control_number='$ctrl', semester='$semLabel', school_year='$schoolYear', registrar_notes='Auto-approved on enrollment confirmation' WHERE id=$eid");
    } else {
        $conn->query("INSERT INTO coe_requests (student_id, purpose, copies, status, approved_by, approved_at, control_number, semester, school_year, registrar_notes) VALUES ($student_id, 'General Purpose', 1, 'Approved', $approved_by, NOW(), '$ctrl', '$semLabel', '$schoolYear', 'Auto-approved on enrollment confirmation')");
    }

    return $ctrl;
}

// Backwards-compat alias (called from coeGetMyRequests / coeCheckEligibility)
function autoEnsureCoeRequest(mysqli $conn, int $student_id): void {
    autoApproveCoeRequest($conn, $student_id, 0);
}

// ── FIX COE-ALLTERMS-01: Ensure one Approved COE row exists per enrollment term ─
// Called from coeGetSemesters so that every term a student was enrolled in
// has a corresponding coe_requests row stamped with the correct semester +
// school_year. Without this, clicking a past term shows "No COE for this term"
// even though the student was genuinely enrolled.
//
// Logic per term:
//   1. If an Approved COE already exists for that exact semester+school_year → skip (idempotent)
//   2. If a Pending row exists for that term → promote it to Approved
//   3. Otherwise → insert a new Approved row
//
// The control number format is COE-YYYYMM-XXXX, generated at approval time.
function autoEnsureAllTermCoes(mysqli $conn, int $student_id): void {
    // Ensure table + columns exist
    $conn->query("CREATE TABLE IF NOT EXISTS coe_requests (
        id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL,
        purpose VARCHAR(255) DEFAULT 'General Purpose', copies TINYINT DEFAULT 1,
        status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
        registrar_notes TEXT DEFAULT NULL, approved_by INT DEFAULT NULL,
        approved_at DATETIME DEFAULT NULL, control_number VARCHAR(30) DEFAULT NULL,
        semester VARCHAR(100) DEFAULT '', school_year VARCHAR(20) DEFAULT '',
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_student (student_id), INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->query("ALTER TABLE coe_requests
        ADD COLUMN IF NOT EXISTS semester    VARCHAR(100) DEFAULT '' AFTER control_number,
        ADD COLUMN IF NOT EXISTS school_year VARCHAR(20)  DEFAULT '' AFTER semester");

    // Collect all distinct enrollment terms for this student
    $enrRes = $conn->query("
        SELECT DISTINCT semester
        FROM enrollments
        WHERE student_id = $student_id
          AND semester IS NOT NULL AND TRIM(semester) != ''
          -- No status filter: all enrollment records count for past COE generation
    ");
    if (!$enrRes || $enrRes->num_rows === 0) return;

    $terms = [];
    while ($row = $enrRes->fetch_assoc()) {
        $raw = trim($row['semester']);
        $semLabel   = '';
        $schoolYear = '';
        if (preg_match('/^(1st Semester|2nd Semester|Summer|Midyear)/i', $raw, $sm)) {
            $semLabel = $sm[1];
        }
        if (preg_match('/(\d{4}-\d{4})/', $raw, $ay)) {
            $schoolYear = $ay[1];
        }
        if ($semLabel !== '' && $schoolYear !== '') {
            $key = $semLabel . '|' . $schoolYear;
            $terms[$key] = ['semester' => $semLabel, 'school_year' => $schoolYear];
        }
    }

    // FIX: Before processing per-term, stamp any existing Approved COE rows
    // that have blank semester/school_year using the student's enrollment history.
    // This handles the original auto-approved COE created at registration time
    // before semester stamping was introduced.
    foreach ($terms as $termKey => $term) {
        $safeSem = $conn->real_escape_string($term['semester']);
        $safeAY  = $conn->real_escape_string($term['school_year']);
        // Stamp blank Approved rows that belong to this term (match by enrollment date proximity)
        $conn->query("
            UPDATE coe_requests cr
            JOIN (
                SELECT MIN(enrollment_date) AS sem_start, MAX(enrollment_date) AS sem_end
                FROM enrollments
                WHERE student_id = $student_id
                  AND semester LIKE '$safeSem%$safeAY%'
            ) sem_range ON cr.requested_at BETWEEN
                DATE_SUB(sem_range.sem_start, INTERVAL 90 DAY)
                AND DATE_ADD(sem_range.sem_end,  INTERVAL 270 DAY)
            SET cr.semester = '$safeSem', cr.school_year = '$safeAY'
            WHERE cr.student_id = $student_id
              AND cr.status = 'Approved'
              AND (cr.semester = '' OR cr.semester IS NULL)
        ");
    }

    foreach ($terms as $term) {
        $safeSem = $conn->real_escape_string($term['semester']);
        $safeAY  = $conn->real_escape_string($term['school_year']);

        // 1. Skip if Approved COE already exists for this exact term
        $chk = $conn->query("
            SELECT id FROM coe_requests
            WHERE student_id = $student_id
              AND status = 'Approved'
              AND semester = '$safeSem'
              AND school_year = '$safeAY'
            LIMIT 1
        ");
        if ($chk && $chk->num_rows > 0) continue;

        // Generate control number
        $yr   = date('Ym');
        $seqR = $conn->query("SELECT COUNT(*) AS c FROM coe_requests WHERE status='Approved' AND DATE_FORMAT(approved_at,'%Y%m') = '$yr'");
        $seq  = (int)(($seqR ? $seqR->fetch_assoc()['c'] : 0) ?? 0);
        $ctrl = $conn->real_escape_string('COE-' . $yr . '-' . str_pad($seq + 1, 4, '0', STR_PAD_LEFT));

        // 2. Promote existing Pending row for this term if one exists
        $pending = $conn->query("
            SELECT id FROM coe_requests
            WHERE student_id = $student_id
              AND status = 'Pending'
              AND semester = '$safeSem'
              AND school_year = '$safeAY'
            ORDER BY id DESC LIMIT 1
        ");
        if ($pending && $pending->num_rows > 0) {
            $pid = (int)$pending->fetch_assoc()['id'];
            $conn->query("
                UPDATE coe_requests
                SET status='Approved', approved_at=NOW(),
                    control_number='$ctrl',
                    registrar_notes='Auto-approved for past enrollment term'
                WHERE id=$pid
            ");
            continue;
        }

        // 3. Insert a fresh Approved COE row for this past term
        $conn->query("
            INSERT INTO coe_requests
                (student_id, purpose, copies, status, approved_by,
                 approved_at, control_number, semester, school_year, registrar_notes)
            VALUES
                ($student_id, 'General Purpose', 1, 'Approved', 0,
                 NOW(), '$ctrl', '$safeSem', '$safeAY',
                 'Auto-approved for past enrollment term')
        ");
    }
}

// ── STUDENT: Check COE Eligibility (payment gate) ────────────────────────────
function coeCheckEligibility(mysqli $conn): void {
    // FIX: Use the verified session user_id from $authUser (already resolved by requireAuth)
    // We also accept user_id from GET as fallback for backwards compat
    global $authUser;

    $user_id = (int)($authUser['user_id'] ?? $_GET['user_id'] ?? 0);

    if (!$user_id) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'eligible' => false, 'message' => 'Could not resolve user ID from session.']);
        return;
    }

    $sr = $conn->prepare("SELECT id, enrollment_status, payment_status, payment_plan, semester FROM students WHERE user_id = ? LIMIT 1");
    $sr->bind_param("i", $user_id);
    $sr->execute();
    $student = $sr->get_result()->fetch_assoc();

    if (!$student) {
        // Also try: maybe the students table uses a different FK — look up by users.id directly
        // Some legacy rows may have student.id = user.id (no user_id FK set)
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode([
            'success'  => false,
            'eligible' => false,
            'message'  => 'Student record not found. No student row linked to your account (user_id=' . $user_id . ').',
            'user_id'  => $user_id,
        ]);
        return;
    }

    $enrollStatus = $student['enrollment_status'] ?? '';
    $paymentPlan  = $student['payment_plan']      ?? 'full';
    // Accept both 'Enrolled' (registrar-confirmed) and 'Confirmed' (accounting-approved,
    // awaiting registrar final step) so installment students aren't blocked after downpayment.
    $enrolled = in_array($enrollStatus, ['Enrolled', 'Confirmed']);

    // Accept: Paid, Partial, Partially Paid, Free, or empty-but-enrolled (admin-enrolled)
    // Also accept 'Pending' for installment students — 'Pending' means downpayment was
    // verified by Accounting; full payment_status='Paid' only arrives after all instalments.
    $payStatus = trim($student['payment_status'] ?? '');
    $paid = in_array($payStatus, ['Paid', 'Partial', 'Partially Paid', 'Free'])
         || ($enrolled && $payStatus === '')   // admin-enrolled with no payment record
         || ($paymentPlan === 'installment' && $payStatus === 'Pending' && $enrolled); // installment downpayment verified

    $eligible = $enrolled && $paid;

    // If eligible, silently ensure a COE request row exists for THIS semester.
    // FIX COE-SWITCH-01: autoEnsureCoeRequest → autoApproveCoeRequest is now
    // semester-scoped (Bug 1 fix), so calling it here no longer pollutes
    // past-term COE rows or causes ghost "Not Yet Enrolled" on term switch-back.
    if ($eligible) {
        autoEnsureCoeRequest($conn, (int)$student['id']);
    }

    // Parse semester + school_year from the raw students.semester string so the
    // frontend can pass them as exact filter params to coe_get_my_requests.
    // Without this, Angular had to pass the raw label directly, which didn't
    // always match the split semester / school_year columns on coe_requests rows.
    [$parsedSem, $parsedAY] = _parseSemesterLabel($conn, (int)$student['id']);

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'           => true,
        'eligible'          => $eligible,
        'enrollment_status' => $student['enrollment_status'],
        'payment_status'    => $payStatus ?: 'N/A',
        'payment_plan'      => $student['payment_plan'],
        'current_semester'  => $student['semester'] ?? '',
        'semester'          => $parsedSem,    // parsed term label e.g. "1st Semester"
        'school_year'       => $parsedAY,     // parsed AY   e.g. "2025-2026"
        'message'           => !$enrolled
            ? 'You are not yet officially enrolled.'
            : (!$paid
                ? 'Your payment has not been verified by Accounting yet. COE will be available once your payment is confirmed.'
                : 'Your Certificate of Enrollment is ready.'),
    ]);
}

// ── STUDENT: Request COE ──────────────────────────────────────────────────────
function coeRequest(mysqli $conn, array $data): void {
    // Ensure table exists
    $conn->query("
        CREATE TABLE IF NOT EXISTS coe_requests (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            student_id      INT NOT NULL,
            purpose         VARCHAR(255) NOT NULL DEFAULT 'General Purpose',
            copies          TINYINT NOT NULL DEFAULT 1,
            status          ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
            registrar_notes TEXT DEFAULT NULL,
            approved_by     INT DEFAULT NULL,
            approved_at     DATETIME DEFAULT NULL,
            control_number  VARCHAR(30) DEFAULT NULL,
            requested_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_student (student_id),
            INDEX idx_status  (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Use session-verified user_id first (most reliable)
    global $authUser;
    $user_id = (int)($authUser['user_id'] ?? $_GET['user_id'] ?? $data['user_id'] ?? 0);
    if (!$user_id) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'User ID required']); return;
    }

    // Resolve student
    $sr = $conn->prepare("SELECT id, enrollment_status, approval_status, payment_status, payment_plan FROM students WHERE user_id = ? LIMIT 1");
    $sr->bind_param("i", $user_id);
    $sr->execute();
    $student = $sr->get_result()->fetch_assoc();

    if (!$student) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'Student record not found']); return;
    }
    if (!in_array($student['enrollment_status'], ['Enrolled', 'Confirmed'])) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'Only enrolled students may request a Certificate of Enrollment.']); return;
    }
    // PAYMENT GATE: COE can only be requested after Accounting has verified payment.
    // For installment students, 'Pending' means downpayment was verified — allow COE.
    // 'Paid' is set only after all instalments are complete.
    $payStatus   = trim($student['payment_status'] ?? '');
    $paymentPlan = trim($student['payment_plan']   ?? 'full');
    $paid = in_array($payStatus, ['Paid', 'Partial', 'Partially Paid', 'Free'])
         || $payStatus === ''  // empty = admin-enrolled, allow
         || ($paymentPlan === 'installment' && $payStatus === 'Pending'); // downpayment verified
    if (!$paid) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode([
            'success'        => false,
            'payment_gate'   => true,
            'message'        => 'Your COE cannot be issued yet. Please complete your payment and wait for Accounting to verify it before requesting a Certificate of Enrollment.',
        ]); return;
    }

    $student_id = (int)$student['id'];
    $purpose    = trim($data['purpose'] ?? 'General Purpose');
    $copies     = max(1, min(10, (int)($data['copies'] ?? 1)));

    // COE is auto-approved on submission — no manual registrar step needed.
    // If an Approved COE already exists, return it immediately (idempotent).
    $ctrl = autoApproveCoeRequest($conn, $student_id, 0);

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'        => true,
        'message'        => 'Your Certificate of Enrollment has been issued.',
        'control_number' => $ctrl,
        'status'         => 'Approved',
    ]);
}

// ── STUDENT: Get My COE Requests ──────────────────────────────────────────────
function coeGetMyRequests(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS coe_requests (
        id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL,
        purpose VARCHAR(255) DEFAULT 'General Purpose', copies TINYINT DEFAULT 1,
        status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
        registrar_notes TEXT DEFAULT NULL, approved_by INT DEFAULT NULL,
        approved_at DATETIME DEFAULT NULL, control_number VARCHAR(30) DEFAULT NULL,
        semester VARCHAR(100) DEFAULT '', school_year VARCHAR(20) DEFAULT '',
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Add columns if schema is from an older version
    $conn->query("ALTER TABLE coe_requests
        ADD COLUMN IF NOT EXISTS semester    VARCHAR(100) DEFAULT '' AFTER control_number,
        ADD COLUMN IF NOT EXISTS school_year VARCHAR(20)  DEFAULT '' AFTER semester");

    $user_id = (int)(isset($GLOBALS['authUser']) ? ($GLOBALS['authUser']['user_id'] ?? 0) : ($_GET['user_id'] ?? 0));
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$user_id) { echo json_encode(['success' => false, 'message' => 'User ID required']); return; }

    $sr = $conn->prepare("SELECT id, enrollment_status, payment_status, payment_plan FROM students WHERE user_id = ? LIMIT 1");
    $sr->bind_param("i", $user_id);
    $sr->execute();
    $student = $sr->get_result()->fetch_assoc();
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$student) { echo json_encode(['success' => true, 'requests' => []]); return; }

    $sid = (int)$student['id'];

    // If student is enrolled+paid and has no COE request yet, auto-create one.
    // 'Confirmed' = accounting approved (installment downpayment verified, awaiting registrar).
    // Installment students with payment_status='Pending' are eligible once downpayment is verified.
    $payStatus   = trim($student['payment_status'] ?? '');
    $paymentPlan = trim($student['payment_plan']   ?? 'full');
    $enrollOk    = in_array($student['enrollment_status'], ['Enrolled', 'Confirmed']);
    $payOk       = in_array($payStatus, ['Paid','Partial','Partially Paid','Free'])
                || $payStatus === ''
                || ($paymentPlan === 'installment' && $payStatus === 'Pending' && $enrollOk);
    $isEligible  = $enrollOk && $payOk;
    if ($isEligible) {
        autoEnsureCoeRequest($conn, $sid);
    }

    // FIX COE-SEM-01: Support ?semester= + ?school_year= filter.
    // For old COE rows that still have blank semester (pre-migration), we correlate
    // by joining to enrollments and matching the semester from the closest enrollment.
    $filterSem = trim($_GET['semester']    ?? '');
    $filterAY  = trim($_GET['school_year'] ?? '');

    if ($filterSem !== '' && $filterAY !== '') {
        $safeSem = $conn->real_escape_string($filterSem);
        $safeAY  = $conn->real_escape_string($filterAY);

        // Primary: rows already stamped with the right semester
        $res = $conn->query("
            SELECT cr.*, COALESCE(sp.first_name, f2.first_name) AS approved_by_name
            FROM coe_requests cr
            LEFT JOIN users u ON cr.approved_by = u.id
            LEFT JOIN staff_profiles sp ON sp.user_id = u.id
            LEFT JOIN faculty f2 ON f2.user_id = u.id
            WHERE cr.student_id = $sid
              AND cr.semester = '$safeSem' AND cr.school_year = '$safeAY'
            ORDER BY cr.requested_at DESC
            LIMIT 20
        ");

        $requests = [];
        if ($res) while ($r = $res->fetch_assoc()) $requests[] = $r;

        // Fallback: if no stamped rows found, look for COE rows whose requested_at
        // falls within an enrollment period that matches the requested semester.
        if (empty($requests)) {
            $fullLabel = $conn->real_escape_string("$filterSem, AY $filterAY");
            $fallback = $conn->query("
                SELECT cr.*, COALESCE(sp.first_name, f2.first_name) AS approved_by_name
                FROM coe_requests cr
                JOIN (
                    SELECT MIN(enrollment_date) AS sem_start,
                           MAX(enrollment_date) AS sem_end
                    FROM enrollments
                    WHERE student_id = $sid
                      AND (semester = '$fullLabel'
                           OR semester LIKE '$safeSem%$safeAY%'
                           OR semester LIKE '$safeAY%')
                ) sem_range ON cr.requested_at BETWEEN
                    DATE_SUB(sem_range.sem_start, INTERVAL 60 DAY)
                    AND DATE_ADD(sem_range.sem_end, INTERVAL 180 DAY)
                LEFT JOIN users u ON cr.approved_by = u.id
                LEFT JOIN staff_profiles sp ON sp.user_id = u.id
                LEFT JOIN faculty f2 ON f2.user_id = u.id
                WHERE cr.student_id = $sid
                  AND (cr.semester = '' OR cr.semester IS NULL)
                ORDER BY cr.requested_at DESC
                LIMIT 20
            ");
            if ($fallback) while ($r = $fallback->fetch_assoc()) $requests[] = $r;
        }

        // FIX COE-FILTER-01 / COE-SWITCH-01: Last-resort fallback — only used when
        // backfill hasn't stamped this COE row yet. Scope STRICTLY to exact
        // semester + school_year. No OR/LIKE fallback that could pull in a
        // different term's COE and cause the "Not Yet Enrolled" ghost on return.
        if (empty($requests)) {
            $lastRes = $conn->query("
                SELECT cr.*, COALESCE(sp.first_name, f2.first_name) AS approved_by_name
                FROM coe_requests cr
                LEFT JOIN users u ON cr.approved_by = u.id
                LEFT JOIN staff_profiles sp ON sp.user_id = u.id
                LEFT JOIN faculty f2 ON f2.user_id = u.id
                WHERE cr.student_id = $sid
                  AND cr.status = 'Approved'
                  AND cr.semester    = '$safeSem'
                  AND cr.school_year = '$safeAY'
                ORDER BY cr.approved_at DESC, cr.requested_at DESC
                LIMIT 1
            ");
            if ($lastRes) while ($r = $lastRes->fetch_assoc()) $requests[] = $r;
        }

    } else {
        // No filter — return all COE rows for this student
        $res = $conn->query("
            SELECT cr.*, COALESCE(sp.first_name, f2.first_name) AS approved_by_name
            FROM coe_requests cr
            LEFT JOIN users u ON cr.approved_by = u.id
            LEFT JOIN staff_profiles sp ON sp.user_id = u.id
            LEFT JOIN faculty f2 ON f2.user_id = u.id
            WHERE cr.student_id = $sid
            ORDER BY cr.requested_at DESC
            LIMIT 20
        ");
        $requests = [];
        if ($res) while ($r = $res->fetch_assoc()) $requests[] = $r;
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'requests' => $requests]);
}

// ── REGISTRAR: Get All Pending COE Requests ───────────────────────────────────
function coeGetPending(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS coe_requests (
        id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL,
        purpose VARCHAR(255) DEFAULT 'General Purpose', copies TINYINT DEFAULT 1,
        status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
        registrar_notes TEXT DEFAULT NULL, approved_by INT DEFAULT NULL,
        approved_at DATETIME DEFAULT NULL, control_number VARCHAR(30) DEFAULT NULL,
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $statusFilter = $_GET['status'] ?? 'Pending';
    if (!in_array($statusFilter, ['Pending','Approved','Rejected','All'], true)) $statusFilter = 'Pending';
    $allowed = ['Pending', 'Approved', 'Rejected', 'All'];
    if (!in_array($statusFilter, $allowed)) $statusFilter = 'Pending';

    $studentId = (int)($_GET['student_id'] ?? 0);

    $conditions = [];
    if ($statusFilter !== 'All') $conditions[] = "cr.status = '$statusFilter'";
    if ($studentId > 0)          $conditions[] = "cr.student_id = $studentId";
    $where = count($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $res = $conn->query("
        SELECT cr.*,
               s.first_name, s.last_name, s.student_number, s.program,
               s.year_level, s.semester, s.student_category, s.student_type,
               s.enrollment_status, s.payment_status, u.email AS student_email,
               COALESCE(sp2.first_name, f3.first_name, '') AS approved_by_name
        FROM coe_requests cr
        JOIN students s ON cr.student_id = s.id
        LEFT JOIN users u ON u.id = s.user_id
        LEFT JOIN users u2 ON cr.approved_by = u2.id
        LEFT JOIN staff_profiles sp2 ON sp2.user_id = u2.id
        LEFT JOIN faculty f3 ON f3.user_id = u2.id
        $where
        ORDER BY cr.requested_at DESC
        LIMIT 200
    ");

    $requests = [];
    if ($res) while ($r = $res->fetch_assoc()) $requests[] = $r;

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'requests' => $requests, 'count' => count($requests)]);
}

// ── REGISTRAR: Get COE Detail (for print) ─────────────────────────────────────
function coeGetDetail(mysqli $conn): void {
    $id = (int)($_GET['id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$id) { echo json_encode(['success' => false, 'message' => 'ID required']); return; }

    $res = $conn->query("
        SELECT cr.*,
               s.first_name, s.last_name, s.middle_name, s.suffix,
               s.student_number, s.program, s.year_level,
               s.strand, s.learning_delivery,
               -- FIX COE-DETAIL-SEM-01: alias s.semester so it does NOT overwrite
               -- cr.semester (which comes from cr.*). cr.semester holds the split
               -- term label e.g. '1st Semester' used for filtering subjects.
               -- s.semester holds the student's CURRENT semester — which is wrong
               -- when viewing a past-term COE. We expose s.semester separately as
               -- student_current_semester and build the display label from cr columns.
               s.semester AS student_current_semester,
               -- Build the human-readable semester label from the COE row's own columns
               -- so the frontend always shows the term the COE belongs to, not the
               -- student's current enrollment term.
               CASE
                   WHEN cr.semester != '' AND cr.school_year != ''
                   THEN CONCAT(cr.semester, ', AY ', cr.school_year)
                   ELSE s.semester
               END AS semester,
               s.user_id,
               s.student_category, s.student_type, s.enrollment_status,
               s.payment_status, s.payment_plan,
               s.accounting_approved_at, s.accounting_notes,
               s.enrollment_date, u.email AS student_email, s.lrn_no,
               s.date_of_birth, s.sex,
               (SELECT sg.guardian_name FROM student_guardians sg WHERE sg.student_id = s.id LIMIT 1) AS guardian_name,
               s.address,
               (SELECT sg.address FROM student_guardians sg WHERE sg.student_id = s.id LIMIT 1) AS guardian_address,
               (SELECT sg.contact FROM student_guardians sg WHERE sg.student_id = s.id LIMIT 1) AS guardian_contact,
               s.phone,
               s.last_school_attended, s.strand,
               COALESCE(p_dept.department,'') AS department,
               COALESCE(sp2.first_name, f3.first_name, '') AS reg_first,
               COALESCE(sp2.last_name,  f3.last_name,  '') AS reg_last
        FROM coe_requests cr
        JOIN students s ON cr.student_id = s.id
        LEFT JOIN users u ON u.id = s.user_id
        LEFT JOIN programs p_dept ON p_dept.name = s.program
        LEFT JOIN users u2 ON cr.approved_by = u2.id
        LEFT JOIN staff_profiles sp2 ON sp2.user_id = u2.id
        LEFT JOIN faculty f3 ON f3.user_id = u2.id
        WHERE cr.id = $id
        LIMIT 1
    ");

    $row = $res ? $res->fetch_assoc() : null;
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$row) { echo json_encode(['success' => false, 'message' => 'Request not found']); return; }

    // FIX COE-DEPT-TVET-01: TVET and SHS programs are stored under the College
    // department in programs.department (e.g. "ICTD"). The SQL JOIN above reads
    // that College value. Override it here with the correct department label based
    // on student_category — matching the same logic in getProfile() and saveSoaSnapshot().
    $coeDetailCat = strtoupper(trim($row['student_category'] ?? ''));
    if ($coeDetailCat === 'TVET') {
        $row['department'] = 'Technical-Vocational Education and Training (TVET)';
    } elseif ($coeDetailCat === 'SHS') {
        $row['department'] = 'Senior High School (SHS)';
    }
    // College students: keep the programs-table value already in $row['department'].

    // BUG-COE-SID-01 FIX: $sid was never assigned — every SQL query inside this
    // function interpolated an undefined variable, sending WHERE student_id = 0
    // to MySQL (no rows returned) and raising a PHP 8 TypeError in strict mode,
    // which the exception handler caught and returned as a 500 Internal Server Error.
    $sid = (int)$row['student_id'];

    $subjects = [];

    // FIX COE-DETAIL-SEM-01 (subject query):
    // coe_requests stores semester as two split columns:
    //   cr.semester    = "1st Semester"   (term label only)
    //   cr.school_year = "2028-2029"      (AY only)
    //
    // enrollments.semester stores the FULL label: "1st Semester, AY 2028-2029"
    //
    // The old code did: AND e.semester = '$coeSemester'
    // where $coeSemester = "1st Semester" — this NEVER matched the full label,
    // so it always fell through to the date-proximity fallback which returned
    // the current term's subjects regardless of which tab was selected.
    //
    // Fix: reconstruct the full label from both columns and use LIKE so minor
    // formatting differences (comma spacing, etc.) don't break the match.
    $coeTermLabel  = trim($row['semester']    ?? '');  // "1st Semester" (built by CASE above)
    $coeSchoolYear = trim($row['school_year'] ?? '');  // "2028-2029"

    // Extract just the term part in case semester was built as full label by the CASE
    if (preg_match('/^(1st Semester|2nd Semester|Summer|Midyear)/i', $coeTermLabel, $termMatch)) {
        $coeTermOnly = $termMatch[1];
    } else {
        $coeTermOnly = $coeTermLabel;
    }

    // BUG-COE-ICURRENT-FIX: define $isCurrent HERE — before the fallback subject
    // query uses it at line ~3321. Previously it was only defined in the fees section
    // (below), so PHP treated it as null/false inside the subjects block, always
    // applying the "past term" status filter even for the current semester.
    $studentCurrentSem = trim($row['student_current_semester'] ?? '');
    $isCurrent = ($coeTermOnly !== '' && $coeSchoolYear !== '' &&
                  strpos($studentCurrentSem, $coeTermOnly)  !== false &&
                  strpos($studentCurrentSem, $coeSchoolYear) !== false);

    if ($coeTermOnly !== '' && $coeSchoolYear !== '') {
        $safeTermOnly  = $conn->real_escape_string($coeTermOnly);
        $safeCoeAY     = $conn->real_escape_string($coeSchoolYear);

        // Build LIKE pattern: "1st Semester%2028-2029%" — matches the full label
        // stored in enrollments.semester regardless of exact formatting
        // FIX COE-01: Use subject_fee_log as the source of truth for historical subjects.
        // enrollments.semester is mutable — ENR-01 fix updates it when a student
        // re-enrolls a previously-Completed course in a new semester, which means
        // querying enrollments for a past COE term finds nothing (semester field changed).
        // subject_fee_log records each enrollment action with the semester at that
        // moment and is never updated, making it immune to this mutation.
        // We UNION with enrollments as a fallback for students enrolled before
        // subject_fee_log existed.
        $subRes = $conn->query("
            SELECT c.code, c.name, c.credits, c.lec_units, c.lab_units,
                   COALESCE(cs.day,'') AS day,
                   CONCAT(COALESCE(cs.time_start,''),' - ',COALESCE(cs.time_end,'')) AS time,
                   COALESCE(r.room_name,'') AS room,
                   TRIM(CONCAT(COALESCE(f.first_name,''),' ',COALESCE(f.last_name,''))) AS instructor
            FROM subject_fee_log sfl
            JOIN courses c ON c.id = sfl.course_id
            LEFT JOIN faculty f ON f.user_id = c.faculty_id
            LEFT JOIN course_sections cs ON cs.course_id = c.id AND cs.is_active = 1
            LEFT JOIN rooms r ON r.id = cs.room_id
            WHERE sfl.student_id = $sid
              AND sfl.action = 'Add'
              AND sfl.semester LIKE '$safeTermOnly%$safeCoeAY%'
              AND (sfl.reason IS NULL OR sfl.reason NOT LIKE '%TOR%')
            GROUP BY c.id
            ORDER BY c.code
        ");
        if ($subRes) while ($s = $subRes->fetch_assoc()) $subjects[] = $s;

        // Fallback: query enrollments for students who enrolled before subject_fee_log existed,
        // OR for auto-enrolled students (autoEnrollNew never writes to subject_fee_log).
        // BUG-COE-SUBJECTS-01 FIX: autoEnrollNew() inserts directly into enrollments without
        // writing to subject_fee_log (only registrar Add/Drop writes there).  So auto-enrolled
        // students always get an empty $subjects list from the primary query above.
        // Fix: the fallback must always run for the current term — not only when
        // subject_fee_log is empty AND we are viewing a past term.
        // For current-term COEs: filter by status IN ('Enrolled','Pending') so Dropped rows
        // are excluded.  For past terms: relax to any non-Dropped status so completed
        // enrollments are still visible.
        if (empty($subjects)) {
            $safeFullLabel = $conn->real_escape_string("$coeTermOnly, AY $coeSchoolYear");
            // For current-term COEs only show active enrollments; for past terms show all non-Dropped
            $statusFilter = $isCurrent
                ? "AND e.status IN ('Enrolled','Pending')"
                : "AND e.status NOT IN ('Dropped')";
            $subResFB = $conn->query("
                SELECT c.code, c.name, c.credits, c.lec_units, c.lab_units,
                       COALESCE(cs.day,'') AS day,
                       CONCAT(COALESCE(cs.time_start,''),' - ',COALESCE(cs.time_end,'')) AS time,
                       COALESCE(r.room_name,'') AS room,
                       TRIM(CONCAT(COALESCE(f.first_name,''),' ',COALESCE(f.last_name,''))) AS instructor
                FROM enrollments e
                JOIN courses c ON e.course_id = c.id
                LEFT JOIN faculty f ON f.user_id = c.faculty_id
                LEFT JOIN course_sections cs ON cs.course_id = c.id AND cs.is_active = 1
                LEFT JOIN rooms r ON r.id = cs.room_id
                WHERE e.student_id = $sid
                  AND e.semester LIKE '$safeTermOnly%$safeCoeAY%'
                  AND e.notes NOT LIKE '%TOR%'
                  $statusFilter
                GROUP BY c.id
                ORDER BY c.code
            ");
            if ($subResFB) while ($s = $subResFB->fetch_assoc()) $subjects[] = $s;
        }
    } else {
        // Legacy COE row with no semester stamp — show current active enrollments
        $subRes = $conn->query("
            SELECT c.code, c.name, c.credits, c.lec_units, c.lab_units,
                   COALESCE(cs.day,'') AS day,
                   CONCAT(COALESCE(cs.time_start,''),' - ',COALESCE(cs.time_end,'')) AS time,
                   COALESCE(r.room_name,'') AS room,
                   TRIM(CONCAT(COALESCE(f.first_name,''),' ',COALESCE(f.last_name,''))) AS instructor
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            LEFT JOIN faculty f ON f.user_id = c.faculty_id
            LEFT JOIN course_sections cs ON cs.course_id = c.id AND cs.is_active = 1
            LEFT JOIN rooms r ON r.id = cs.room_id
            WHERE e.student_id = $sid
              AND e.status IN ('Enrolled','Pending')
            ORDER BY c.code
        ");
        if ($subRes) while ($s = $subRes->fetch_assoc()) $subjects[] = $s;
    }

    // ── Compute fees dynamically from the term's subjects ─────────────────────
    // tuition_fees has ONE row per student (no semester history), so reading it
    // for a past term always returns the CURRENT semester's assessment — wrong.
    //
    // Fix: recompute fees from the subjects already retrieved for this term using
    // the same formula as enrollment.php / evaluateTOR. We still fall back to the
    // stored row for the CURRENT term (when semester matches student's current one)
    // so existing data is not silently overwritten.
    $fees = null;

    // ── SHS / TVET fee override ───────────────────────────────────────────────
    // Non-transferee SHS/TVET = ₱0 (K-12 Gov subsidy / TESDA).
    // Transferee SHS/TVET     = flat rate from fee_config.
    // College students fall through to the original unit-based logic below.
    $coeCat  = strtoupper(trim($row['student_category'] ?? ''));
    $coeType = strtolower(trim($row['student_type']     ?? ''));
    $isCOESHS  = ($coeCat === 'SHS');
    $isCOETVET = ($coeCat === 'TVET');

    // Normalise year_level for SHS COE display
    $rawYLCoe = trim($row['year_level'] ?? '');
    if ($isCOESHS) {
        $row['grade_level'] = (stripos($rawYLCoe, '12') !== false) ? 'Grade 12' : 'Grade 11';
    } else {
        $row['grade_level'] = $rawYLCoe;
    }

    if (($isCOESHS || $isCOETVET) && $coeType !== 'transferee') {
        // FREE — K-12 subsidy / TESDA scholarship
        $fees = [
            'units'             => 0,
            'tuition_fee'       => 0,
            'miscellaneous_fee' => 0,
            'registration_fee'  => 0,
            'laboratory_fee'    => 0,
            'energy_fee'        => 0,
            'subtotal'          => 0,
            'discount'          => 0,
            'installment_fee'   => 0,
            'total_assessment'  => 0,
            'is_free'           => true,
            'free_label'        => $isCOESHS
                ? 'Free – K-12 Government Subsidy (SHS Voucher)'
                : 'Free – TESDA Government Scholarship (PESFA/STEP)',
            '_computed'         => true,
        ];
    } elseif (($isCOESHS || $isCOETVET) && $coeType === 'transferee') {
        // Transferee flat rate
        $fcCatCoe = $isCOESHS ? 'SHS' : 'TVET';
        $fcCoe    = loadFeeConfig($conn, $fcCatCoe);
        $flatRate = (float)($fcCoe['transferee_flat_rate']['value'] ?? 20000);
        $planCoe  = trim($row['payment_plan'] ?? 'full');
        $instFee  = ($planCoe === 'installment') ? (float)($fcCoe['installment_fee']['value'] ?? 750) : 0.0;
        $fees = [
            'units'             => 0,
            'tuition_fee'       => 0,
            'miscellaneous_fee' => 0,
            'registration_fee'  => 0,
            'laboratory_fee'    => 0,
            'energy_fee'        => 0,
            'subtotal'          => $flatRate,
            'discount'          => 0,
            'installment_fee'   => $instFee,
            'total_assessment'  => $flatRate + $instFee,
            'is_flat_rate'      => true,
            'flat_rate_label'   => 'Government Transferee Flat Rate',
            '_computed'         => true,
        ];
    } else {

    // Always try to get the stored row first for reference (discount, installment_fee).
    // BUG-COE-UNITS-01 FIX: tuition_fees is now semester-scoped (FIX-TUITION-SEMESTER-01).
    // Querying with ORDER BY id DESC LIMIT 1 returns the LATEST semester's row, which
    // may be a different term than the COE being viewed.  Scope the lookup to the COE's
    // semester so units + assessment always belong to the same term as the document.
    //
    // FIX COE-UNDEFINED-VARS-01: $safeTermOnly and $safeCoeAY are defined inside the
    // subjects if-block above (scoped to $coeTermOnly !== ''). This College else-block
    // is outside that scope so PHP 8 emits notices and the query uses empty strings →
    // always falls through to the fallback. Redefine here unconditionally.
    $safeTermOnly  = $conn->real_escape_string($coeTermOnly);
    $safeCoeAY     = $conn->real_escape_string($coeSchoolYear);
    $safeCoeFullSem = $conn->real_escape_string("$coeTermOnly, AY $coeSchoolYear");
    $storedFeeRes = $conn->query(
        "SELECT * FROM tuition_fees
         WHERE student_id=$sid
           AND semester LIKE '$safeTermOnly%$safeCoeAY%'
         ORDER BY id DESC LIMIT 1"
    );
    // Fallback: no semester-matched row → use the most recent row (preserves old behaviour)
    if (!$storedFeeRes || $storedFeeRes->num_rows === 0) {
        $storedFeeRes = $conn->query("SELECT * FROM tuition_fees WHERE student_id=$sid ORDER BY id DESC LIMIT 1");
    }
    $storedFees = $storedFeeRes ? $storedFeeRes->fetch_assoc() : null;

    // Detect whether the requested COE term matches the student's current semester
    $studentCurrentSem = trim($row['student_current_semester'] ?? '');
    $isCurrent = ($coeTermOnly !== '' && $coeSchoolYear !== '' &&
                  strpos($studentCurrentSem, $coeTermOnly)  !== false &&
                  strpos($studentCurrentSem, $coeSchoolYear) !== false);

    if ($isCurrent && $storedFees) {
        // Current term — use the stored assessment (already computed by Accounting).
        // BUG-COE-UNITS-02 FIX: Override units with the actual count from $subjects
        // so the COE always shows the real enrolled unit count, not a stale cached value
        // that may have been saved before or after add/drop changes.
        $fees = $storedFees;
        if (!empty($subjects)) {
            $actualUnits = array_sum(array_column($subjects, 'credits'));
            if ($actualUnits > 0) {
                $fees['units'] = $actualUnits;
            }
        }
    } else {
        // Past term — compute from actual subjects fetched for this term
        $fc = loadFeeConfig($conn, 'College');
        $r_tuition  = (float)($fc['tuition_rate_per_unit']['value'] ?? 650);
        $r_misc     = (float)($fc['misc_fee']['value']              ?? 6688);
        $r_reg      = (float)($fc['reg_fee']['value']               ?? 700);
        $r_lab_room = (float)($fc['lab_fee_per_room']['value']      ?? 1900);
        $r_energy   = (float)($fc['energy_rate_per_unit']['value']  ?? 63);

        // Count total units and lab subjects from this term's subjects
        $totalUnits = 0;
        $labCount   = 0;
        foreach ($subjects as $subj) {
            $u = (int)($subj['credits'] ?? 0);
            $totalUnits += $u;
            if ((int)($subj['lab_units'] ?? 0) > 0 || !empty($subj['is_lab'])) {
                $labCount++;
            }
        }
        // BUG-COE-LABFEE-FIX: removed the fallback that counted ALL lab rooms in the
        // rooms table when $labCount was 0. That inflated the lab fee for students
        // with no lab subjects (e.g. 5 lab rooms × ₱1900 = ₱9500 added incorrectly).
        // If no subjects have lab_units > 0, the student owes zero lab fee.

        $discount   = (float)($storedFees['discount']         ?? 0);
        $inst_fee   = (float)($storedFees['installment_fee']  ?? 0);

        $tuition_fee = $totalUnits * $r_tuition;
        $energy_fee  = $totalUnits * $r_energy;
        $lab_fee     = $labCount   * $r_lab_room;
        $subtotal    = $tuition_fee + $r_misc + $r_reg + $lab_fee + $energy_fee;
        $total       = max(0, $subtotal - $discount + $inst_fee);

        $fees = [
            'units'             => $totalUnits,
            'tuition_fee'       => $tuition_fee,
            'miscellaneous_fee' => $r_misc,
            'registration_fee'  => $r_reg,
            'laboratory_fee'    => $lab_fee,
            'energy_fee'        => $energy_fee,
            'subtotal'          => $subtotal,
            'discount'          => $discount,
            'installment_fee'   => $inst_fee,
            'total_assessment'  => $total,
            '_computed'         => true,   // flag: dynamically computed, not stored
        ];
    }
    } // end College fee else block

    $row['subjects']         = $subjects;
    $row['fees']             = $fees;
    // SHS/TVET enrichment fields for COE template
    $row['is_shs']           = $isCOESHS;
    $row['is_tvet']          = $isCOETVET;
    $row['is_shs_or_tvet']   = ($isCOESHS || $isCOETVET);
    $row['grade_level']      = $row['grade_level'] ?? $rawYLCoe; // Grade 11 / Grade 12
    $row['learning_delivery']= $row['learning_delivery'] ?? '';   // Face-to-Face / Modular
    $row['strand']           = $row['strand']           ?? '';    // STEM / ABM / HUMSS etc.
    $row['allow_add_drop']   = !($isCOESHS || $isCOETVET);

    while (ob_get_level() > 0) { ob_end_clean(); }
    global $authUser;
    // FIX COE-PRIVACY-01: $isOwner=true when the student is viewing their own COE so
    // applyPrivacy() does not redact address, date_of_birth, guardian_address,
    // guardian_contact. Requires s.user_id in the SELECT above.
    $isOwner = ($authUser['role'] === 'student'
             && (int)($authUser['user_id'] ?? 0) === (int)($row['user_id'] ?? -1));
    $row = applyPrivacy($row, $authUser, 'student', $isOwner);
    echo json_encode(['success' => true, 'coe' => $row]);
}

// ── REGISTRAR: Get COE Detail by student_id (for Generate PDF tab) ────────────
// Looks up the most recent Approved (or any) COE request for the given student.
// If no COE request exists yet, auto-creates a Pending one so the PDF can still
// be generated by the registrar on the fly.
function coeGetDetailByStudent(mysqli $conn): void {
    $student_id = (int)($_GET['student_id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$student_id) { echo json_encode(['success' => false, 'message' => 'student_id required']); return; }

    // Find the best COE request: prefer Approved, then Pending, then any
    $res = $conn->query("
        SELECT cr.*
        FROM coe_requests cr
        WHERE cr.student_id = $student_id
        ORDER BY
            CASE cr.status WHEN 'Approved' THEN 0 WHEN 'Pending' THEN 1 ELSE 2 END,
            cr.requested_at DESC
        LIMIT 1
    ");
    $coeRow = $res ? $res->fetch_assoc() : null;

    // Auto-create a Pending request if none exists so registrar can generate the PDF
    if (!$coeRow) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS coe_requests (
                id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL,
                purpose VARCHAR(255) DEFAULT 'General Purpose', copies TINYINT DEFAULT 1,
                status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
                registrar_notes TEXT DEFAULT NULL, approved_by INT DEFAULT NULL,
                approved_at DATETIME DEFAULT NULL, control_number VARCHAR(30) DEFAULT NULL,
                requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_student (student_id), INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $conn->query("INSERT INTO coe_requests (student_id, purpose, copies) VALUES ($student_id, 'General Purpose', 1)");
        $newId = (int)$conn->insert_id;
        $res2  = $conn->query("SELECT cr.* FROM coe_requests cr WHERE cr.id = $newId LIMIT 1");
        $coeRow = $res2 ? $res2->fetch_assoc() : null;
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$coeRow) { echo json_encode(['success' => false, 'message' => 'Could not create COE request row.']); return; }

    // Reuse coeGetDetail logic by faking $_GET['id']
    $_GET['id'] = (int)$coeRow['id'];
    coeGetDetail($conn);
}

// ── REGISTRAR: Approve COE ────────────────────────────────────────────────────
function coeApprove(mysqli $conn, array $data): void {
    $id = (int)($data['id'] ?? $_GET['id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$id) { echo json_encode(['success' => false, 'message' => 'ID required']); return; }

    // Get registrar user_id from token
    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_replace('Bearer ', '', $token);
    $registrar_id = 0;
    if ($token) {
        $tr = $conn->prepare("SELECT user_id FROM sessions WHERE token = ? AND expires_at > NOW() LIMIT 1");
        $tr->bind_param("s", $token);
        $tr->execute();
        $tr_row = $tr->get_result()->fetch_assoc();
        $registrar_id = (int)($tr_row['user_id'] ?? 0);
    }

    $notes = trim($data['notes'] ?? '');

    // Generate control number: COE-YYYYMM-XXXX
    $yr   = date('Ym');
    $seq  = (($_r=$conn->query("SELECT COUNT(*) AS c FROM coe_requests WHERE status='Approved' AND DATE_FORMAT(approved_at,'%Y%m') = '$yr'")) ? $_r->fetch_assoc() : null)['c'] ?? 0;
    $ctrl = 'COE-' . $yr . '-' . str_pad((int)$seq + 1, 4, '0', STR_PAD_LEFT);

    $conn->query("
        UPDATE coe_requests
        SET status='Approved', approved_by=$registrar_id,
            approved_at=NOW(), control_number='$ctrl',
            registrar_notes='$notes'
        WHERE id=$id AND status='Pending'
    ");

    if ($conn->affected_rows > 0) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => true, 'message' => 'COE approved.', 'control_number' => $ctrl]);
    } else {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'Could not approve — already processed or not found.']);
    }
}

// ── REGISTRAR: Reject COE ─────────────────────────────────────────────────────
function coeReject(mysqli $conn, array $data): void {
    $id    = (int)($data['id'] ?? 0);
    $notes = trim($data['notes'] ?? 'Request rejected by registrar.');
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$id) { echo json_encode(['success' => false, 'message' => 'ID required']); return; }

    $conn->query("UPDATE coe_requests SET status='Rejected', registrar_notes='$notes', updated_at=NOW() WHERE id=$id AND status='Pending'");

    if ($conn->affected_rows > 0) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => true, 'message' => 'Request rejected.']);
    } else {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'Could not reject — already processed or not found.']);
    }
}

// =============================================================================
// FEATURE: Registration Confirmation by Registrar
//
// Flow:
//   1. Student submits enrollment (status = 'Pending')
//   2. Registrar reviews: GET ?action=get_pending_registrations
//   3. Registrar confirms: POST ?action=confirm_registration
//      → enrollment_status = 'Confirmed' (signals Accounting to compute fees)
//   4. OR rejects: POST ?action=reject_registration
//      → enrollment_status = 'Rejected', student gets a reason
// =============================================================================

/**
 * GET ?action=get_pending_registrations
 * Returns all students with enrollment_status = 'Pending' that need registrar review.
 */
function getPendingRegistrations(mysqli $conn): void {
    $page   = max(1, (int)($_GET['page']  ?? 1));
    $limit  = min(50, max(10, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $search = trim($_GET['q'] ?? '');

    // Show students whose payment was verified by Accounting (Confirmed) — waiting for registrar final approval
    // tor_eval_status is NOT filtered here — Transferees are already gated by Accounting
    // (they cannot reach 'Confirmed' without passing TOR eval first). Filtering here
    // only blocks Continuing/Old/Regular students who never needed TOR in the first place.
    $where  = ["s.enrollment_status = 'Confirmed'"];
    $params = [];
    $types  = '';

    if ($search) {
        $sq      = '%' . $search . '%';
        $where[] = "(s.student_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR CONCAT(s.first_name,' ',s.last_name) LIKE ?)";
        array_push($params, $sq, $sq, $sq, $sq);
        $types  .= 'ssss';
    }

    $whereStr = implode(' AND ', $where);

    $cnt = $conn->prepare("SELECT COUNT(*) AS total FROM students s WHERE $whereStr");
    if ($params) $cnt->bind_param($types, ...$params);
    $cnt->execute();
    $total = (int)$cnt->get_result()->fetch_assoc()['total'];
    $cnt->close();

    $stmt = $conn->prepare("
        SELECT s.id, s.student_number, s.first_name, s.last_name, s.middle_name,
               s.program, s.year_level, s.semester, s.student_type,
               s.student_category, s.enrollment_status, s.payment_status,
               s.approval_status, s.tor_eval_status,
               s.registrar_confirmed, s.registrar_notes,
               s.address, s.phone, s.lrn_no, s.sex,
               s.payment_plan,
               s.accounting_notes,
               s.is_scholar, s.scholar_type, s.scholar_grantor, s.scholarship_amount AS scholar_discount,
               DATE_FORMAT(s.date_of_birth,'%Y-%m-%d') AS date_of_birth,
               s.strand, s.last_school_attended,
               DATE_FORMAT(s.enrollment_date,'%Y-%m-%d') AS enrollment_date,
               DATE_FORMAT(s.accounting_approved_at,'%Y-%m-%d %H:%i') AS accounting_approved_at,
               u.email,
               COALESCE(tf.total_assessment, 0) AS totalAssessment,
               COALESCE(tf.subtotal, 0)          AS subtotal,
               COALESCE(tf.discount, 0)           AS discount,
               COALESCE(tf.installment_fee, 0)    AS installmentFee,
               COALESCE(tf.tuition_fee, 0)        AS tuitionFee,
               (
                 -- Primary: sum from installment_payments scoped to current semester only.
                 -- FIX SEM-SCOPE: previous semesters excluded so returning students
                 -- don't appear to have already paid for the new term.
                 SELECT COALESCE(SUM(ip.amount), 0)
                 FROM installment_payments ip
                 WHERE ip.student_id = s.id
                   AND ip.semester   = s.semester
               ) +
               (
                 -- Secondary: any Verified payment_log not yet migrated to installment_payments,
                 -- also scoped to current semester.
                 SELECT COALESCE(SUM(pl.gcash_amount), 0)
                 FROM payment_logs pl
                 WHERE pl.student_id = s.id
                   AND pl.semester   = s.semester
                   AND pl.status = 'Verified'
                   AND NOT EXISTS (
                     SELECT 1 FROM installment_payments ip2
                     WHERE ip2.payment_log_id = pl.id
                   )
               ) AS totalPaid,
               (SELECT sg.email
                FROM student_guardians sg
                WHERE sg.student_id = s.id
                  AND sg.email IS NOT NULL
                  AND TRIM(sg.email) != ''
                ORDER BY sg.is_emergency DESC, sg.id ASC
                LIMIT 1) AS guardianEmail
        FROM students s
        JOIN users u ON u.id = s.user_id
        LEFT JOIN (SELECT student_id, total_assessment, subtotal, units, discount, installment_fee, tuition_fee FROM tuition_fees WHERE id IN (SELECT MAX(id) FROM tuition_fees GROUP BY student_id)) tf ON tf.student_id = s.id
        WHERE $whereStr
        ORDER BY s.accounting_approved_at ASC, s.last_name ASC
        LIMIT ? OFFSET ?
    ");
    $allP = array_merge($params, [$limit, $offset]);
    $allT = $types . 'ii';
    $stmt->bind_param($allT, ...$allP);
    $stmt->execute();
    $res      = $stmt->get_result();
    $students = [];
    $seenStudentIds = [];
    while ($r = $res->fetch_assoc()) {
        if (isset($seenStudentIds[(int)$r["id"]])) continue;
        $seenStudentIds[(int)$r["id"]] = true;
        $students[] = [
            'id'               => (int)$r['id'],
            'studentNumber'    => $r['student_number'],
            'firstName'        => $r['first_name'],
            'lastName'         => $r['last_name'],
            'fullName'         => trim($r['first_name'] . ' ' . $r['last_name']),
            'email'            => $r['email'] ?? '',
            'program'          => $r['program'],
            'yearLevel'        => $r['year_level'],
            'semester'         => $r['semester'],
            'studentType'      => $r['student_type'],
            'studentCategory'  => $r['student_category'],
            'enrollmentStatus'     => $r['enrollment_status'],
            'paymentStatus'        => $r['payment_status'],
            'approvalStatus'       => $r['approval_status'],
            'torEvalStatus'        => $r['tor_eval_status'],
            'enrollmentDate'       => $r['enrollment_date'],
            'registrarConfirmed'   => $r['registrar_confirmed'] ?? 'Pending',
            'registrarNotes'       => $r['registrar_notes'] ?? '',
            'address'              => $r['address'] ?? '',
            'phone'                => $r['phone'] ?? '',
            'lrnNo'                => $r['lrn_no'] ?? '',
            'sex'                  => $r['sex'] ?? '',
            'dateOfBirth'          => $r['date_of_birth'] ?? '',
            'strand'               => $r['strand'] ?? '',
            'lastSchoolAttended'   => $r['last_school_attended'] ?? '',
            'middleName'           => $r['middle_name'] ?? '',
            'registeredAt'         => $r['accounting_approved_at'] ?? $r['enrollment_date'] ?? '',
            // ── Payment & Assessment ─────────────────────────────────────
            'totalAssessment'      => (float)($r['totalAssessment'] ?? 0),
            'subtotal'             => (float)($r['subtotal']        ?? 0),
            'discount'             => (float)($r['discount']        ?? 0),
            'installmentFee'       => (float)($r['installmentFee']  ?? 0),
            'tuitionFee'           => (float)($r['tuitionFee']      ?? 0),
            'totalPaid'            => (float)($r['totalPaid']       ?? 0),
            // balance: never negative; 0 if fully paid or no assessment on file yet
            'balance'              => max(0, (float)($r['totalAssessment'] ?? 0) - (float)($r['totalPaid'] ?? 0)),
            'paymentPlan'          => $r['payment_plan']     ?? '',
            'accountingNotes'      => $r['accounting_notes'] ?? '',
            // ── Scholarship ──────────────────────────────────────────────
            'isScholar'            => (int)($r['is_scholar']      ?? 0),
            'scholarType'          => $r['scholar_type']    ?? '',
            'scholarGrantor'       => $r['scholar_grantor'] ?? '',
            'scholarDiscount'      => (float)($r['scholar_discount'] ?? 0),
            // ── Guardian contact ─────────────────────────────────────────
            'guardianEmail'        => $r['guardianEmail'] ?? '',
            // ── Free enrollment flag (SHS/TVET — no Accounting payment needed) ──
            // Frontend uses this to show "Free Enrollment" banner instead of
            // "Payment Verified by Accounting" for tuition-free students.
            'isFreeEnrollment'     => in_array(
                strtoupper(trim($r['student_category'] ?? '')),
                ['SHS', 'TVET']
            ),
        ];
    }
    $stmt->close();

    // ── Attach approved subjects from subject_selections to each student ──────
    // This shows the registrar exactly which subjects were approved for the student
    // so they can review them in the Pending Approvals panel before confirming.
    // Priority: approved_course_ids (if registrar already reviewed) → requested_course_ids.
    if (!empty($students)) {
        $sidList = implode(',', array_map(fn($s) => (int)$s['id'], $students));

        // Fetch the latest subject_selection row per student
        $selRes = $conn->query("
            SELECT ss.student_id,
                   COALESCE(ss.approved_course_ids, ss.requested_course_ids) AS course_ids_json,
                   ss.status AS selection_status,
                   ss.registrar_notes AS selection_notes
            FROM subject_selections ss
            INNER JOIN (
                SELECT student_id, MAX(id) AS max_id
                FROM subject_selections
                WHERE student_id IN ($sidList)
                GROUP BY student_id
            ) latest ON latest.student_id = ss.student_id AND latest.max_id = ss.id
        ");

        // Build map: student_id → [course_ids, status]
        $selMap = [];
        $allCourseIds = [];
        if ($selRes) {
            while ($selRow = $selRes->fetch_assoc()) {
                $ids = json_decode($selRow['course_ids_json'] ?? '[]', true) ?: [];
                $ids = array_values(array_filter(array_map('intval', $ids)));
                $selMap[(int)$selRow['student_id']] = [
                    'course_ids' => $ids,
                    'status'     => $selRow['selection_status'] ?? '',
                    'notes'      => $selRow['selection_notes']  ?? '',
                ];
                foreach ($ids as $cid) $allCourseIds[$cid] = true;
            }
        }

        // Bulk-fetch course details for all referenced IDs
        $courseMap = [];
        if (!empty($allCourseIds)) {
            $ph = implode(',', array_keys($allCourseIds));
            $cRes = $conn->query("
                SELECT c.id, c.code, c.name, c.credits,
                       COALESCE(c.lec_units, c.credits) AS lec_units,
                       COALESCE(c.lab_units, 0)         AS lab_units
                FROM courses c
                WHERE c.id IN ($ph)
            ");
            if ($cRes) {
                while ($c = $cRes->fetch_assoc()) {
                    $c['code'] = cleanCode($c['code']);
                    $courseMap[(int)$c['id']] = $c;
                }
            }
        }

        // Attach subjects array to each student
        foreach ($students as &$stu) {
            $sid2 = (int)$stu['id'];
            if (isset($selMap[$sid2])) {
                $sel = $selMap[$sid2];
                $stu['approvedSubjects']      = array_values(array_filter(
                    array_map(fn($id) => $courseMap[$id] ?? null, $sel['course_ids'])
                ));
                $stu['selectionStatus']       = $sel['status'];
                $stu['selectionNotes']        = $sel['notes'];
                $stu['approvedSubjectCount']  = count($stu['approvedSubjects']);
                $stu['approvedUnits']         = array_sum(array_column($stu['approvedSubjects'], 'credits'));
            } else {
                // No subject selection recorded — student may be New (auto-enrolled)
                $stu['approvedSubjects']      = [];
                $stu['selectionStatus']       = '';
                $stu['selectionNotes']        = '';
                $stu['approvedSubjectCount']  = 0;
                $stu['approvedUnits']         = 0;
            }
        }
        unset($stu);
    }
    // ── End approved subjects attachment ──────────────────────────────────────

    while (ob_get_level() > 0) { ob_end_clean(); }
    global $authUser;
    $students = applyPrivacyList($students, $authUser, 'student');
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
 * POST ?action=confirm_registration
 * Body: { student_id, notes? }
 *
 * Sets enrollment_status = 'Confirmed'.
 * Accounting can now compute fees and approve payment.
 */

// ================================================================
// GET CONFIRMED ENROLLMENTS
// GET ?action=get_confirmed_enrollments[&q=search][&page=1][&limit=20]
//
// Returns students whose enrollment_status = 'Enrolled'
// (i.e. Registrar has already confirmed/approved them).
// Used by the Registrar "Enrolled Students" display tab.
// ================================================================
function getConfirmedEnrollments(mysqli $conn): void {
    $page   = max(1, (int)($_GET['page']  ?? 1));
    $limit  = min(50, max(10, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $search = trim($_GET['q'] ?? '');

    $where  = ["s.enrollment_status = 'Enrolled'"];
    $params = [];
    $types  = '';

    if ($search) {
        $sq      = '%' . $search . '%';
        $where[] = "(s.student_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR CONCAT(s.first_name,' ',s.last_name) LIKE ?)";
        array_push($params, $sq, $sq, $sq, $sq);
        $types  .= 'ssss';
    }

    $whereStr = implode(' AND ', $where);

    $cnt = $conn->prepare("SELECT COUNT(*) AS total FROM students s WHERE $whereStr");
    if ($params) $cnt->bind_param($types, ...$params);
    $cnt->execute();
    $total = (int)$cnt->get_result()->fetch_assoc()['total'];
    $cnt->close();

    $stmt = $conn->prepare("
        SELECT s.id, s.student_number, s.first_name, s.last_name, s.middle_name,
               s.program, s.year_level, s.semester, s.student_type,
               s.student_category, s.enrollment_status, s.payment_status,
               s.approval_status, s.registrar_confirmed_at,
               DATE_FORMAT(s.enrollment_date,'%Y-%m-%d') AS enrollment_date,
               s.registrar_notes, s.accounting_notes,
               u.email,
               COALESCE(tf.total_assessment, 0) AS totalAssessment,
               COALESCE(tf.units, 0)             AS enrolledUnits,
               (
                 -- FIX SEM-SCOPE: scope to current semester only
                 SELECT COALESCE(SUM(ip.amount), 0)
                 FROM installment_payments ip
                 WHERE ip.student_id = s.id
                   AND ip.semester   = s.semester
               ) +
               (
                 SELECT COALESCE(SUM(pl.gcash_amount), 0)
                 FROM payment_logs pl
                 WHERE pl.student_id = s.id
                   AND pl.semester   = s.semester
                   AND pl.status = 'Verified'
                   AND NOT EXISTS (
                     SELECT 1 FROM installment_payments ip2
                     WHERE ip2.payment_log_id = pl.id
                   )
               ) AS totalPaid,
               COUNT(DISTINCT e.id) AS subjectCount
        FROM students s
        JOIN users u ON u.id = s.user_id
        LEFT JOIN (SELECT student_id, total_assessment, subtotal, units, discount, installment_fee, tuition_fee FROM tuition_fees WHERE id IN (SELECT MAX(id) FROM tuition_fees GROUP BY student_id)) tf ON tf.student_id = s.id
        LEFT JOIN enrollments e ON e.student_id = s.id AND e.status IN ('Enrolled','Pending')
        WHERE $whereStr
        GROUP BY s.id
        ORDER BY s.registrar_confirmed_at DESC, s.last_name ASC
        LIMIT ? OFFSET ?
    ");
    $allP = array_merge($params, [$limit, $offset]);
    $allT = $types . 'ii';
    $stmt->bind_param($allT, ...$allP);
    $stmt->execute();
    $res      = $stmt->get_result();
    $students = [];
    while ($r = $res->fetch_assoc()) {
        $students[] = [
            'id'               => (int)$r['id'],
            'studentNumber'    => $r['student_number'],
            'firstName'        => $r['first_name'],
            'lastName'         => $r['last_name'],
            'fullName'         => trim($r['first_name'] . ' ' . $r['last_name']),
            'middleName'       => $r['middle_name'] ?? '',
            'email'            => $r['email'] ?? '',
            'program'          => $r['program'],
            'yearLevel'        => $r['year_level'],
            'semester'         => $r['semester'],
            'studentType'      => $r['student_type'],
            'studentCategory'  => $r['student_category'],
            'enrollmentStatus' => $r['enrollment_status'],
            'paymentStatus'    => $r['payment_status'],
            'approvalStatus'   => $r['approval_status'],
            'enrollmentDate'   => $r['enrollment_date'],
            'confirmedAt'      => $r['registrar_confirmed_at'] ?? '',
            'registrarNotes'   => $r['registrar_notes'] ?? '',
            'accountingNotes'  => $r['accounting_notes'] ?? '',
            'totalAssessment'  => (float)$r['totalAssessment'],
            'enrolledUnits'    => (int)$r['enrolledUnits'],
            'totalPaid'        => (float)$r['totalPaid'],
            'balance'          => max(0, (float)$r['totalAssessment'] - (float)$r['totalPaid']),
            'subjectCount'     => (int)$r['subjectCount'],
        ];
    }
    $stmt->close();

    while (ob_get_level() > 0) { ob_end_clean(); }
    global $authUser;
    $students = applyPrivacyList($students, $authUser, 'student');
    echo json_encode([
        'success'    => true,
        'students'   => $students,
        'total'      => $total,
        'page'       => $page,
        'limit'      => $limit,
        'totalPages' => (int)ceil($total / $limit),
    ]);
}

// =============================================================================
// AUTO-ASSIGN BLOCK ON CONFIRM
// Called automatically inside confirmRegistration() after autoEnrollAll().
// Idempotent — safe to call multiple times; no-op if block already assigned.
// =============================================================================
function autoAssignBlockOnConfirm(mysqli $conn, int $studentId, array $authUser): string
{
    require_once __DIR__ . '/blocks.php';
    ensureBlockTables($conn);

    // Load student info
    $st = $conn->prepare("SELECT program, year_level, semester, block_id FROM students WHERE id = ? LIMIT 1");
    $st->bind_param('i', $studentId);
    $st->execute();
    $s = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$s) return '';

    // Already has a block — idempotent, return existing code
    if (!empty($s['block_id'])) {
        $bc = $conn->prepare("SELECT block_code FROM class_blocks WHERE id = ? LIMIT 1");
        $bc->bind_param('i', $s['block_id']);
        $bc->execute();
        $bRow = $bc->get_result()->fetch_assoc();
        $bc->close();
        return $bRow['block_code'] ?? '';
    }

    $program   = $s['program']    ?? '';
    $yearLevel = $s['year_level'] ?? '';
    $semester  = $s['semester']   ?? '';

    if (!$program || !$yearLevel || !$semester) return '';

    // Derive school_year from semester label (e.g. "1st Semester, AY 2026-2027")
    $schoolYear = '';
    if (preg_match('/AY\s*(\d{4}-\d{4})/i', $semester, $m)) {
        $schoolYear = $m[1];
    } else {
        $y = (int)date('Y');
        $schoolYear = "$y-" . ($y + 1);
    }

    // Strip AY suffix for LIKE matching
    $semTerm = $semester;
    if (preg_match('/^(1st Semester|2nd Semester|Summer|Midyear)/i', $semester, $sm)) {
        $semTerm = $sm[1];
    }

    $esc = $conn->real_escape_string($program);
    $ylE = $conn->real_escape_string($yearLevel);
    $smE = $conn->real_escape_string($semTerm);

    // Find first block with available space for this program+year+semester
    $blocksRes = $conn->query("
        SELECT b.id, b.block_code, b.max_capacity,
               COUNT(s2.id) AS enrolled_count
        FROM   class_blocks b
        LEFT JOIN students s2 ON s2.block_id = b.id
             AND s2.enrollment_status NOT IN ('Graduated','Dropped','Inactive')
        WHERE  b.program    = '$esc'
          AND  b.year_level = '$ylE'
          AND  b.semester   LIKE '$smE%'
          AND  b.is_active  = 1
        GROUP BY b.id
        ORDER BY b.block_code ASC
    ");

    $targetId   = null;
    $targetCode = '';
    $isNew      = false;
    $lastLetter = '';

    if ($blocksRes) {
        while ($b = $blocksRes->fetch_assoc()) {
            // Track the last letter suffix for overflow (e.g. "BSIT-1A" → "A")
            $dash = strrpos($b['block_code'], '-');
            if ($dash !== false) {
                $suffix     = substr($b['block_code'], $dash + 1); // "1A"
                $lastLetter = preg_replace('/\d/', '', $suffix);   // "A"
            }
            if ((int)$b['enrolled_count'] < (int)$b['max_capacity']) {
                $targetId   = (int)$b['id'];
                $targetCode = $b['block_code'];
                break;
            }
        }
    }

    // No block with space → create next one (A→B→C…)
    if (!$targetId) {
        $nextLetter = nextBlockLetter($lastLetter); // '' → 'A', 'A' → 'B', …
        $targetCode = buildBlockCode($conn, $program, $yearLevel, $nextLetter);
        $maxCap     = 40;

        $ins = $conn->prepare("
            INSERT INTO class_blocks (block_code, program, year_level, semester, school_year, max_capacity)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $ins->bind_param('sssssi', $targetCode, $program, $yearLevel, $semester, $schoolYear, $maxCap);
        $ins->execute();
        $targetId = (int)$ins->insert_id;
        $ins->close();
        $isNew = true;

        logAuditShared($conn, $authUser, 'CREATE_BLOCK', 'class_blocks', $targetId,
            "Auto-created block $targetCode for $program $yearLevel ($semester) on registration confirm");
    }

    // Assign student
    $upd = $conn->prepare("UPDATE students SET block_id = ? WHERE id = ?");
    $upd->bind_param('ii', $targetId, $studentId);
    $upd->execute();
    $upd->close();

    logAuditShared($conn, $authUser, 'AUTO_ASSIGN_BLOCK', 'students', $studentId,
        "Student $studentId auto-assigned to block $targetCode (ID $targetId)" .
        ($isNew ? ' [new block created]' : '') . ' — triggered by confirm_registration');

    return $targetCode;
}

function confirmRegistration(mysqli $conn, array $data = []): void {
    global $authUser;
    // $data is passed from the router (which already consumed php://input).
    // Do NOT re-read php://input here — it would always be empty.
    $sid     = (int)($data['student_id'] ?? 0);
    $notes   = trim($data['notes'] ?? '');
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$sid) { echo json_encode(['success'=>false,'message'=>'student_id required']); return; }

    // Check student exists and Accounting has verified their payment.
    // Accepted statuses:
    //   'Confirmed' — normal new enrollment (Accounting set this after verifying payment)
    //   'Enrolled'  — re-enrolling student whose payment was verified by Accounting but
    //                 whose enrollment_status was never reset to 'Confirmed' because
    //                 Accounting.php skips the → Confirmed transition when the student is
    //                 already 'Enrolled' (to avoid breaking their active enrollment).
    //                 We also check approval_status='Approved' to ensure Accounting has
    //                 actually signed off — not just a student who enrolled last semester.
    $check = $conn->prepare("
        SELECT id, first_name, last_name, student_number, enrollment_status AS prev_status
        FROM students
        WHERE id = ?
          AND (
            enrollment_status = 'Confirmed'
            OR (enrollment_status = 'Enrolled' AND approval_status = 'Approved'
                AND accounting_approved_at IS NOT NULL)
          )
        LIMIT 1
    ");
    $check->bind_param('i', $sid);
    $check->execute();
    $student = $check->get_result()->fetch_assoc();
    $check->close();
    if (!$student) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success'=>false,'message'=>'Student not found or payment not yet verified by Accounting.']); return;
    }

    $regBy = (int)($authUser['user_id'] ?? 0);
    // Registrar confirms — student is now officially Enrolled.
    // enrollment_status MUST be 'Enrolled' before autoEnrollAll is called,
    // because autoEnrollNew/autoEnrollTransfereeAction guard on exactly this value.
    $upd = $conn->prepare("UPDATE students SET enrollment_status='Enrolled', registrar_confirmed='Confirmed', registrar_confirmed_at=NOW(), registrar_confirmed_by=?, registrar_notes=? WHERE id=?");
    $upd->bind_param('isi', $regBy, $notes, $sid);
    $upd->execute();
    $upd->close();

    // Auto-enroll student in their program courses now that registrar has confirmed.
    // Skip if the student was already 'Enrolled' (re-enrolling student whose status
    // never reset to 'Confirmed' — they were auto-enrolled in a prior confirmation).
    $prevStatus = $student['prev_status'] ?? '';
    $semSt = $conn->prepare("SELECT semester FROM students WHERE id=? LIMIT 1");
    $semSt->bind_param('i', $sid);
    $semSt->execute();
    $semRow = $semSt->get_result()->fetch_assoc();
    $semSt->close();
    $semester = trim($semRow['semester'] ?? '');
    if ($prevStatus !== 'Enrolled') {
        require_once __DIR__ . '/enrollment.php';

        // ── FIX SUBJECT-SEL-ENROLL-01: If the student has an approved subject selection,
        //    enroll ONLY the registrar-approved courses instead of the full program curriculum.
        //    This respects the subject selection step where the Registrar may have removed
        //    overloaded, prerequisite-blocked, or schedule-conflicting subjects.
        $selApproved = false;
        $conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS subject_selection_status VARCHAR(20) NOT NULL DEFAULT 'Pending'");
        $selStatusR = $conn->query("SELECT subject_selection_status FROM students WHERE id=$sid LIMIT 1");
        $selStatusVal = $selStatusR ? ($selStatusR->fetch_assoc()['subject_selection_status'] ?? 'Pending') : 'Pending';

        if ($selStatusVal === 'Approved') {
            // Fetch approved course IDs from subject_selections
            $selCidsR = $conn->query("SELECT approved_course_ids FROM subject_selections WHERE student_id=$sid ORDER BY id DESC LIMIT 1");
            $selRow = $selCidsR ? $selCidsR->fetch_assoc() : null;
            $approvedCids = $selRow ? (json_decode($selRow['approved_course_ids'] ?? '[]', true) ?: []) : [];
            if (!empty($approvedCids)) {
                $selApproved = true;
                // Build a course list matching the format expected by insertEnrollments()
                $ph = implode(',', array_fill(0, count($approvedCids), '?'));
                $ty = str_repeat('i', count($approvedCids));
                $cSt = $conn->prepare("SELECT id, name, semester FROM courses WHERE id IN ($ph)");
                $cSt->bind_param($ty, ...$approvedCids);
                $cSt->execute();
                $approvedCourses = $cSt->get_result()->fetch_all(MYSQLI_ASSOC);
                $cSt->close();
                // Determine note based on student type
                $selNoteType = strcasecmp(trim($data['student_type'] ?? ''), 'Transferee') === 0
                    ? 'Auto-enrolled (Transferee)'
                    : 'Auto-enrolled';
                insertEnrollments($conn, $sid, $approvedCourses, $semester, $selNoteType);
            }
        }

        // Fall back to full auto-enroll if no approved selection exists
        if (!$selApproved && function_exists('autoEnrollAll')) {
            autoEnrollAll($conn, ['student_id' => $sid, 'semester' => $semester], false);
        }
    }

    // ── AUTO-ASSIGN BLOCK ──────────────────────────────────────────────────────
    // Place student in the correct block section (e.g. BSIT-1A).
    // If the current block is full, the next block letter is created (A→B→C…).
    // Idempotent — no-op if the student already has a block assigned.
    $assignedBlockCode = autoAssignBlockOnConfirm($conn, $sid, $authUser ?? []);
    // ── END AUTO-ASSIGN BLOCK ─────────────────────────────────────────────────

    // ── Auto-approve COE immediately — no manual approval step needed ────────
    // The COE is issued automatically the moment the registrar confirms enrollment.
    $regById = (int)($authUser['user_id'] ?? 0);
    $coeCtrl = autoApproveCoeRequest($conn, $sid, $regById);

    logAuditShared($conn, $authUser ?? null, 'CONFIRM_REGISTRATION', 'student', $sid,
        "Registration confirmed for {$student['first_name']} {$student['last_name']} ({$student['student_number']}). Notes: $notes" .
        ($assignedBlockCode ? " | Block: $assignedBlockCode" : ''));
    // ── Send enrollment confirmation email to guardian ────────────────────────
    // Email is sent inline AFTER autoEnrollAll so subjects are already populated.
    // The old fire-and-forget file_get_contents() to notify.php was removed —
    // it fired before auto-enroll completed causing "No enrolled subjects" errors.
    // Get full student info for the email
    $fullSt = $conn->prepare(
        "SELECT s.first_name, s.last_name, s.student_number, s.program, s.year_level, s.semester,
                u.email AS student_email,
                (SELECT sg.email FROM student_guardians sg
                 WHERE sg.student_id = s.id AND sg.email IS NOT NULL AND TRIM(sg.email) != ''
                 ORDER BY sg.is_emergency DESC, sg.id ASC LIMIT 1) AS guardian_email
         FROM students s JOIN users u ON u.id = s.user_id
         WHERE s.id = ? LIMIT 1"
    );
    $fullSt->bind_param('i', $sid);
    $fullSt->execute();
    $fullRow = $fullSt->get_result()->fetch_assoc();
    $fullSt->close();

    $guardianEmailAddr = trim($fullRow['guardian_email'] ?? '');
    $emailSent = false;
    $emailError = '';

    if ($guardianEmailAddr) {
        // ── Build enrollment report email ────────────────────────────────────
        $subject       = "Enrollment Confirmed \u{2013} {$fullRow['first_name']} {$fullRow['last_name']} ({$fullRow['student_number']})";
        $studentName   = htmlspecialchars("{$fullRow['first_name']} {$fullRow['last_name']}");
        $studentNumber = htmlspecialchars($fullRow['student_number'] ?? '');
        $program       = htmlspecialchars($fullRow['program']        ?? '');
        $yearLevel     = htmlspecialchars($fullRow['year_level']     ?? '');
        $semester      = htmlspecialchars($fullRow['semester']       ?? '');
        $notesHtml     = $notes
            ? '<p><strong>Registrar Notes:</strong> ' . htmlspecialchars($notes) . '</p>'
            : '';

        // Fetch enrolled subjects for the report
        $subjectsHtml = '';
        $subRes = $conn->query(
            "SELECT c.code, c.name, c.credits, e.status
             FROM enrollments e
             JOIN courses c ON c.id = e.course_id
             WHERE e.student_id = $sid AND e.status IN ('Enrolled','Pending')
             ORDER BY c.code"
        );
        if ($subRes && $subRes->num_rows > 0) {
            $subjectsHtml = '
            <table border="1" cellpadding="7" cellspacing="0"
                   style="border-collapse:collapse;width:100%;font-family:Arial,sans-serif;font-size:13px;">
                <tr style="background:#1B3A6B;color:#fff;">
                    <th align="left">Code</th>
                    <th align="left">Subject</th>
                    <th align="center">Units</th>
                    <th align="left">Status</th>
                </tr>';
            while ($sr = $subRes->fetch_assoc()) {
                $subjectsHtml .= '<tr>
                    <td>' . htmlspecialchars($sr['code'])    . '</td>
                    <td>' . htmlspecialchars($sr['name'])    . '</td>
                    <td align="center">' . htmlspecialchars((string)$sr['credits']) . '</td>
                    <td>' . htmlspecialchars($sr['status'])  . '</td>
                </tr>';
            }
            $subjectsHtml .= '</table>';
        } else {
            $subjectsHtml = '<p><em>Subjects will be reflected in the student portal.</em></p>';
        }

        $htmlBody = "
        <div style='font-family:Arial,sans-serif;max-width:620px;margin:0 auto;border:1px solid #ddd;'>
            <div style='background:#1B3A6B;color:#fff;padding:22px 24px;'>
                <h2 style='margin:0;font-size:18px;'>St. Benilde BASIC System</h2>
                <p style='margin:4px 0 0;font-size:13px;opacity:.85;'>Registrar&rsquo;s Office &mdash; Enrollment Confirmation</p>
            </div>
            <div style='padding:24px;background:#f9f9f9;'>
                <p style='margin-top:0;'>Dear Parent / Guardian,</p>
                <p>We are pleased to inform you that the enrollment of your student has been
                   <strong>officially confirmed</strong> by the Registrar&rsquo;s Office.</p>

                <table style='width:100%;font-size:13px;border-collapse:collapse;margin-bottom:18px;'>
                    <tr><td style='padding:4px 8px;width:40%;'><strong>Student Name</strong></td>
                        <td style='padding:4px 8px;'>$studentName</td></tr>
                    <tr style='background:#eef2f7;'><td style='padding:4px 8px;'><strong>Student No.</strong></td>
                        <td style='padding:4px 8px;'>$studentNumber</td></tr>
                    <tr><td style='padding:4px 8px;'><strong>Program</strong></td>
                        <td style='padding:4px 8px;'>$program</td></tr>
                    <tr style='background:#eef2f7;'><td style='padding:4px 8px;'><strong>Year Level</strong></td>
                        <td style='padding:4px 8px;'>$yearLevel</td></tr>
                    <tr><td style='padding:4px 8px;'><strong>Semester</strong></td>
                        <td style='padding:4px 8px;'>$semester</td></tr>
                </table>

                $notesHtml

                <h3 style='color:#1B3A6B;font-size:14px;margin-bottom:8px;'>Enrolled Subjects</h3>
                $subjectsHtml

                <p style='margin-top:20px;font-size:12px;color:#888;'>
                    This is an automated message from the St. Benilde BASIC System.<br>
                    Please do not reply to this email.
                </p>
            </div>
        </div>";

        // ── Send via PHP mail() ───────────────────────────────────────────────
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: BASIC Registrar <no-reply@stbenilde.edu.ph>\r\n";
        $headers .= "X-Mailer: PHP/" . PHP_VERSION . "\r\n";

        $mailResult = @mail($guardianEmailAddr, $subject, $htmlBody, $headers);
        $emailSent  = (bool)$mailResult;

        // ── Log result in email_notifications ────────────────────────────────
        $mailStatus = $emailSent ? 'sent' : 'failed';
        $logStmt = $conn->prepare(
            "INSERT INTO email_notifications
                 (student_id, recipient, type, subject, status, sent_at, created_at)
             VALUES (?, ?, 'enrollment_confirmation', ?, ?, IF(? = 'sent', NOW(), NULL), NOW())"
        );
        if ($logStmt) {
            $logStmt->bind_param('issss', $sid, $guardianEmailAddr, $subject, $mailStatus, $mailStatus);
            $logStmt->execute();
            $logStmt->close();
        }

        if (!$emailSent) {
            $emailError = 'Guardian email found but mail() failed. Check server mail (sendmail/SMTP) configuration.';
        }
    } else {
        $emailError = 'No guardian email on file \u{2014} confirmation email not sent.';
    }

    while (ob_get_level() > 0) { ob_end_clean(); }

    // ── Fetch the auto-enrolled subjects so the frontend can display them immediately ──
    // This avoids the race condition where the student's dashboard loads before
    // a second round-trip to getStudentContext completes.
    $enrolledSubjects = [];
    $eStmt = $conn->prepare("
        SELECT c.id AS course_id, c.code, c.name, c.credits,
               COALESCE(c.lec_units, c.credits) AS lec_units,
               COALESCE(c.lab_units, 0) AS lab_units,
               COALESCE(NULLIF(TRIM(CONCAT(COALESCE(f.first_name,''),' ',COALESCE(f.last_name,''))), ''), TRIM(CONCAT(COALESCE(fc.first_name,''),' ',COALESCE(fc.last_name,''))), '') AS instructor,
               cs.day,
               CONCAT(COALESCE(cs.time_start,''), ' - ', COALESCE(cs.time_end,'')) AS time,
               r.room_name AS room,
               c.semester AS course_semester,
               e.status
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN course_sections cs ON cs.course_id = c.id AND cs.is_active = 1
        LEFT JOIN faculty f ON f.user_id = cs.faculty_id
        LEFT JOIN faculty fc ON fc.user_id = c.faculty_id
        LEFT JOIN rooms r ON r.id = cs.room_id
        WHERE e.student_id = ?
          AND (
            e.status IN ('Enrolled','Pending')
            OR (
              -- FIX ENR-01: After autoEnrollAll, courses re-enrolled from a prior
              -- semester may remain 'Completed' due to the unique key conflict.
              -- Include them for the current semester so the confirmation response
              -- always returns the subject list.
              e.status   = 'Completed'
              AND e.semester = (SELECT semester FROM students WHERE id = ? LIMIT 1)
            )
          )
        ORDER BY c.code
    ");
    $eStmt->bind_param('ii', $sid, $sid);
    $eStmt->execute();
    $eRes = $eStmt->get_result();
    while ($row = $eRes->fetch_assoc()) {
        $enrolledSubjects[] = [
            'courseId'   => (int)$row['course_id'],
            'code'       => $row['code'],
            'name'       => $row['name'],
            'credits'    => (int)$row['credits'],
            'lecUnits'   => (int)$row['lec_units'],
            'labUnits'   => (int)$row['lab_units'],
            'instructor' => trim($row['instructor'] ?? ''),
            'day'        => $row['day'] ?? '',
            'time'       => $row['time'] ?? '',
            'room'       => $row['room'] ?? '',
            'semester'   => $row['course_semester'] ?? '',
            'status'     => $row['status'],
        ];
    }
    $eStmt->close();

    echo json_encode([
        'success'            => true,
        'message'            => "Registration confirmed for {$student['first_name']} {$student['last_name']}.",
        'student_id'         => $sid,
        'new_status'         => 'Enrolled',
        'coe_control_number' => $coeCtrl,   // COE auto-issued — no approval step needed
        'enrolled_subjects'  => $enrolledSubjects,
        'subject_count'      => count($enrolledSubjects),
        'email_sent'         => $emailSent,
        'email_to'           => $guardianEmailAddr ?: null,
        'email_note'         => $emailError ?: null,
    ]);
}

/**
 * POST ?action=reject_registration
 * Body: { student_id, reason }
 *
 * Sets enrollment_status = 'Rejected'.
 * Student will see rejection reason when they log in.
 */

// =============================================================================
// POST ?action=update_student_info
//
// Allows the Registrar to correct student personal information directly from
// the Student Masterlist detail panel.
//
// Only personal/contact fields are editable — academic fields (program,
// year_level, student_number, semester, student_category, student_type)
// are intentionally excluded.
// =============================================================================
function updateStudentInfo(mysqli $conn, array $data): void {
    global $authUser;
    while (ob_get_level() > 0) { ob_end_clean(); }

    $sid = (int)($data['student_id'] ?? 0);
    if (!$sid) {
        echo json_encode(['success' => false, 'message' => 'student_id required']);
        return;
    }

    // ── Editable fields only ─────────────────────────────────────────────────
    $allowed = [
        'first_name', 'last_name', 'middle_name', 'suffix',
        'phone', 'address', 'sex', 'date_of_birth', 'lrn_no',
        'religion', 'place_of_birth', 'citizenship', 'mother_tongue',
        'emergency_contact', 'emergency_phone',
        'psa_birth_cert_no', 'strand', 'last_school_attended',
    ];

    $sets   = [];
    $params = [];
    $types  = '';

    foreach ($allowed as $col) {
        if (array_key_exists($col, $data)) {
            $sets[]   = "$col = ?";
            $params[] = ($data[$col] === '' || $data[$col] === null) ? null : $data[$col];
            $types   .= 's';
        }
    }

    if (!$sets) {
        echo json_encode(['success' => false, 'message' => 'No fields to update.']);
        return;
    }

    $params[] = $sid;
    $types   .= 'i';

    $stmt = $conn->prepare('UPDATE students SET ' . implode(', ', $sets) . ' WHERE id = ?');
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Query preparation failed.']);
        return;
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();

    // Re-fetch updated row so Angular refreshes without a full page reload
    $fetch = $conn->prepare("
        SELECT s.first_name, s.last_name, s.middle_name, s.suffix,
               s.phone, s.address, s.sex,
               DATE_FORMAT(s.date_of_birth,'%Y-%m-%d') AS date_of_birth,
               s.lrn_no, s.religion, s.place_of_birth, s.citizenship,
               s.mother_tongue, s.emergency_contact, s.emergency_phone,
               s.psa_birth_cert_no, s.strand, s.last_school_attended
        FROM students s WHERE s.id = ? LIMIT 1
    ");
    $fetch->bind_param('i', $sid);
    $fetch->execute();
    $updated = $fetch->get_result()->fetch_assoc();
    $fetch->close();

    logAuditShared($conn, $authUser ?? null, 'UPDATE_STUDENT_INFO', 'student', $sid,
        "Registrar updated personal info for student ID $sid.");

    echo json_encode([
        'success' => true,
        'message' => 'Student information updated.',
        'updated' => $updated,
    ]);
}

function rejectRegistration(mysqli $conn, array $data = []): void {
    global $authUser;
    // $data is passed from the router (which already consumed php://input).
    $sid    = (int)($data['student_id'] ?? 0);
    $reason = trim($data['reason'] ?? '');
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$sid)    { echo json_encode(['success'=>false,'message'=>'student_id required']); return; }
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$reason) { echo json_encode(['success'=>false,'message'=>'reason is required']); return; }

    $check = $conn->prepare("SELECT id, first_name, last_name, student_number FROM students WHERE id=? LIMIT 1");
    $check->bind_param('i', $sid);
    $check->execute();
    $student = $check->get_result()->fetch_assoc();
    $check->close();
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$student) { echo json_encode(['success'=>false,'message'=>'Student not found.']); return; }

    // FIX REG-REJECT-SUBJSEL-01: Reset subject_selection_status to 'Pending' so
    // the student is forced back to subject-selection on next login.
    // Without this, subject_selection_status stays 'Approved' after rejection →
    // the enrollment.ts subject-selection routing check is skipped → student is
    // sent to the payment step instead of the re-selection form.
    $upd = $conn->prepare("UPDATE students SET enrollment_status='Rejected', approval_status='Rejected', accounting_notes=?, subject_selection_status='Pending' WHERE id=?");
    $upd->bind_param('si', $reason, $sid);
    $upd->execute();
    $upd->close();

    // Also reset the subject_selections record to Pending so wasRejectedSubjectSelection=true
    // triggers the pre-fill logic in the enrollment wizard (student sees their previous choices).
    $conn->query("UPDATE subject_selections SET status='Rejected', registrar_notes='" . $conn->real_escape_string($reason) . "' WHERE student_id=$sid ORDER BY id DESC LIMIT 1");

    logAuditShared($conn, $authUser ?? null, 'REJECT_REGISTRATION', 'student', $sid,
        "Registration rejected for {$student['first_name']} {$student['last_name']} ({$student['student_number']}). Reason: $reason");

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'    => true,
        'message'    => "Registration rejected for {$student['first_name']} {$student['last_name']}.",
        'student_id' => $sid,
        'new_status' => 'Rejected',
    ]);
}

// =============================================================================
// GET ?action=get_enrollment_history&student_id=XX
//
// Returns full enrollment history for a student grouped by semester.
// Each semester entry contains: subjects enrolled, status, grades (if any),
// units, and a summary (total units, GPA for that term).
//
// Accessible by: registrar, admin
// =============================================================================
function getEnrollmentHistory(mysqli $conn): void {
    global $authUser;

    $sid = (int)($_GET['student_id'] ?? 0);
    if (!$sid) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success'=>false,'message'=>'student_id required']);
        return;
    }

    // Role guard: only registrar and admin
    if (!in_array($authUser['role'] ?? '', ['registrar','admin'], true)) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        http_response_code(403);
        echo json_encode(['success'=>false,'message'=>'Access denied.']);
        return;
    }

    // ── Student profile ───────────────────────────────────────────────────────
    $profStmt = $conn->prepare(
        "SELECT s.id, s.student_number, s.first_name, s.last_name, s.middle_name,
                s.suffix, s.program, s.year_level, s.enrollment_status,
                s.approval_status, s.semester AS current_semester,
                s.enrollment_date, u.email
         FROM students s
         JOIN users u ON u.id = s.user_id
         WHERE s.id = ? LIMIT 1"
    );
    $profStmt->bind_param('i', $sid);
    $profStmt->execute();
    $student = $profStmt->get_result()->fetch_assoc();
    $profStmt->close();

    if (!$student) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success'=>false,'message'=>'Student not found.']);
        return;
    }

    // ── FIX HISTORY-03: Use subject_fee_log as source of truth ──────────────
    //
    // WHY: enrollments has UNIQUE KEY (student_id, course_id) — one row per course
    // ever. When a student re-enrolls a failed subject in a later semester, the old
    // row's semester field is overwritten. Querying enrollments directly therefore
    // loses all subjects from previous semesters that share a course_id with any
    // current enrollment. subject_fee_log records every Add action with the correct
    // semester and is never overwritten, making it the reliable history source.
    //
    // Strategy:
    //   1. subject_fee_log (action='Add') → one row per subject per semester
    //   2. LEFT JOIN enrollments ON (student_id + course_id) → current status/grade
    //   3. LEFT JOIN student_grades ON (student_id + course_id + term=Final) → grade
    //   4. Fall back to enrollments.final_grade for pre-migration records

    // Ensure subject_fee_log table exists (safe no-op if already created)
    $conn->query("CREATE TABLE IF NOT EXISTS subject_fee_log (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        student_id       INT NOT NULL,
        course_id        INT NOT NULL,
        course_code      VARCHAR(20)   DEFAULT NULL,
        course_name      VARCHAR(150)  DEFAULT NULL,
        action           VARCHAR(10)   NOT NULL DEFAULT 'Add',
        subject_type     VARCHAR(50)   DEFAULT NULL,
        course_category  VARCHAR(50)   DEFAULT NULL,
        units            INT           NOT NULL DEFAULT 0,
        lec_units        INT           NOT NULL DEFAULT 0,
        lab_units        INT           NOT NULL DEFAULT 0,
        tuition_impact   DECIMAL(10,2) NOT NULL DEFAULT 0,
        lab_fee_impact   DECIMAL(10,2) NOT NULL DEFAULT 0,
        energy_impact    DECIMAL(10,2) NOT NULL DEFAULT 0,
        total_impact     DECIMAL(10,2) NOT NULL DEFAULT 0,
        semester         VARCHAR(100)  DEFAULT NULL,
        reason           TEXT          DEFAULT NULL,
        added_by_role    VARCHAR(50)   DEFAULT NULL,
        added_by_email   VARCHAR(150)  DEFAULT NULL,
        created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $logStmt = $conn->prepare(
        "SELECT
            sfl.course_id,
            sfl.course_code,
            sfl.course_name,
            sfl.units,
            sfl.lec_units,
            sfl.lab_units,
            sfl.semester,
            sfl.created_at   AS enrollment_date,
            -- Current enrollment row for this student+course (may span semesters)
            e.id             AS enrollment_id,
            e.status         AS enrollment_status,
            e.notes,
            c.is_lab,
            c.department,
            c.year_level     AS course_year_level,
            -- Grade: prefer student_grades table, fall back to enrollments.final_grade
            COALESCE(sg.grade, e.final_grade) AS final_grade
         FROM subject_fee_log sfl
         LEFT JOIN courses c
               ON c.id = sfl.course_id
         LEFT JOIN enrollments e
               ON e.student_id = ? AND e.course_id = sfl.course_id
         LEFT JOIN student_grades sg
               ON sg.student_id = ? AND sg.course_id = sfl.course_id AND sg.term = 'Final'
         WHERE sfl.student_id = ?
           AND sfl.action     = 'Add'
         ORDER BY sfl.semester DESC, sfl.course_code ASC"
    );

    $rows = [];
    if ($logStmt) {
        $logStmt->bind_param('iii', $sid, $sid, $sid);
        $logStmt->execute();
        $logRes = $logStmt->get_result();
        while ($r = $logRes->fetch_assoc()) $rows[] = $r;
        $logStmt->close();
    }

    // FIX HISTORY-04: Always merge enrollments as fallback — not just when subject_fee_log
    // is empty. Students enrolled before subject_fee_log existed will be missing from
    // the log. We merge both sources and deduplicate by (semester + course_id).
    $fallStmt = $conn->prepare(
        "SELECT
            e.id            AS enrollment_id,
            e.status        AS enrollment_status,
            e.enrollment_date,
            e.semester,
            e.notes,
            c.id            AS course_id,
            c.code          AS course_code,
            c.name          AS course_name,
            c.credits       AS units,
            c.lec_units,
            c.lab_units,
            c.is_lab,
            c.department,
            c.year_level    AS course_year_level,
            COALESCE(sg.grade, e.final_grade) AS final_grade
         FROM enrollments e
         JOIN courses c ON c.id = e.course_id
         LEFT JOIN student_grades sg
               ON sg.enrollment_id = e.id AND sg.term = 'Final'
         WHERE e.student_id = ?
         ORDER BY e.semester DESC, c.code ASC"
    );
    if ($fallStmt) {
        $fallStmt->bind_param('i', $sid);
        $fallStmt->execute();
        $fallRes = $fallStmt->get_result();

        // Build a set of (semester + course_id) keys already in $rows from subject_fee_log
        $seen = [];
        foreach ($rows as $r) {
            $seen[($r['semester'] ?? '') . '|' . ($r['course_id'] ?? '')] = true;
        }

        while ($r = $fallRes->fetch_assoc()) {
            $key = ($r['semester'] ?? '') . '|' . ($r['course_id'] ?? '');
            if (!isset($seen[$key])) {
                $rows[]       = $r;
                $seen[$key]   = true;
            }
        }
        $fallStmt->close();
    }

    // ── Group by semester ─────────────────────────────────────────────────────
    $bySemester = [];
    foreach ($rows as $row) {
        $sem = $row['semester'] ?: 'Unknown Semester';
        if (!isset($bySemester[$sem])) {
            $bySemester[$sem] = [
                'semester'        => $sem,
                'subjects'        => [],
                'total_units'     => 0,
                'graded_units'    => 0,
                'gpa'             => null,
                'grade_sum'       => 0,
                'subject_count'   => 0,
                'passed'          => 0,
                'failed'          => 0,
                'dropped'         => 0,
            ];
        }

        $units = (int)($row['units'] ?? 0);
        $grade = $row['final_grade'] !== null ? (float)$row['final_grade'] : null;

        // Normalize: strip program disambiguation suffixes (GE103-BMD → GE103)
        $row['course_code'] = cleanCode($row['course_code'] ?? '');

        // Derive enrollment_status: use the joined enrollment status if available.
        // If null (subject_fee_log row has no matching enrollment yet), infer from grade.
        // CRITICAL FIX: if a grade exists and it's > 3.0, always mark as Failed
        // regardless of what enrollment_status says — this handles the case where
        // enrollment.status was wrongly set to 'Completed' before the SQL fix was run,
        // or where subject_fee_log's JOIN picked up the wrong enrollment row.
        $enrStatus = $row['enrollment_status'] ?? null;
        if ($grade !== null && $grade > 3.0) {
            $enrStatus = 'Failed';
        } elseif ($enrStatus === null) {
            $enrStatus = $grade !== null ? 'Completed' : 'Enrolled';
        }

        // FIX TOR-HISTORY-01: TOR-credited subjects are stored as status='Dropped'
        // with a specific notes value so autoEnrollAll() skips them. But in the
        // history view they should display as 'Credited' — not 'Dropped' — so the
        // registrar and student see the correct status.
        if ($enrStatus === 'Dropped'
            && isset($row['notes'])
            && str_contains((string)$row['notes'], 'Credited via TOR evaluation')
        ) {
            $enrStatus = 'Credited';
        }

        $row['enrollment_status'] = $enrStatus;

        // FIX HISTORY-05: MySQL DECIMAL columns come back as strings from mysqli.
        // Cast every numeric field here so JSON encodes them as numbers (not strings).
        // Without this, Angular receives final_grade as "2.50" (string) and calling
        // g.toFixed(2) throws "TypeError: g.toFixed is not a function".
        $row['final_grade']       = $grade;                               // already float|null
        $row['units']             = (int)($row['units']          ?? 0);
        $row['lec_units']         = (int)($row['lec_units']      ?? 0);
        $row['lab_units']         = (int)($row['lab_units']      ?? 0);
        $row['is_lab']            = (int)($row['is_lab']         ?? 0);
        $row['enrollment_id']     = isset($row['enrollment_id'])  ? (int)$row['enrollment_id']  : null;

        $bySemester[$sem]['subjects'][]     = $row;
        $bySemester[$sem]['subject_count']++;
        $bySemester[$sem]['total_units']   += $units;

        if ($grade !== null) {
            $bySemester[$sem]['graded_units'] += $units;
            $bySemester[$sem]['grade_sum']    += $grade;
            if ($grade <= 3.0)  $bySemester[$sem]['passed']++;
            else                $bySemester[$sem]['failed']++;
        }

        if ($enrStatus === 'Dropped') $bySemester[$sem]['dropped']++;
    }

    // Compute per-semester GPA
    foreach ($bySemester as &$semEntry) {
        $gradedCount = count(array_filter($semEntry['subjects'], fn($s) => $s['final_grade'] !== null));
        if ($gradedCount > 0) {
            $semEntry['gpa'] = round($semEntry['grade_sum'] / $gradedCount, 4);
        }
        unset($semEntry['grade_sum']);
    }
    unset($semEntry);

    // Return semesters newest first
    $history = array_values($bySemester);

    // ── Subject fee log entries for this student ──────────────────────────────
    // FIX: Use the full schema matching enrollment.php/_logSubjectFeeImpact and
    // Accounting.php/getSubjectFeeLog. The old stripped-down CREATE TABLE here was
    // subject_fee_log table already created above; just query it
    $feeLogRes = $conn->query("SELECT * FROM subject_fee_log WHERE student_id=$sid ORDER BY created_at DESC LIMIT 200");
    $feeLog = [];
    if ($feeLogRes) while ($fl = $feeLogRes->fetch_assoc()) $feeLog[] = $fl;

    // BUG-FEELOG-01 FIX: Removed backfill block that was inserting ALL enrolled
    // subjects into subject_fee_log. That caused the Subject Fee Log (Accounting)
    // to show every enrolled subject with "Backfilled from enrollment record" —
    // making it impossible to distinguish actual Add/Drop fee events.
    //
    // subject_fee_log must ONLY contain real Add/Drop transactions written by
    // _logSubjectFeeImpact() (called from processAddDrop / registrarAddSubject /
    // registrarDropSubject). An empty log simply means no Add/Drop activity yet.

    // ── Scholarship history ───────────────────────────────────────────────────
    $schRes = $conn->query("SELECT * FROM student_scholarships WHERE student_id=$sid ORDER BY id DESC");
    $scholarships = [];
    if ($schRes) while ($sc = $schRes->fetch_assoc()) $scholarships[] = $sc;

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'      => true,
        'student'      => $student,
        'history'      => $history,
        'semester_count'  => count($history),
        'total_subjects'  => count($rows),
        'fee_log'         => $feeLog,
        'scholarships'    => $scholarships,
    ]);
}

// =============================================================================
// GET ?action=get_scholarship_students
// Returns all students with an active scholarship — visible to registrar & admin.
// Optional query params: q (search), status (active|all)
// =============================================================================
function getScholarshipStudents(mysqli $conn): void {
    $search = trim($_GET['q']      ?? '');
    $status = trim($_GET['status'] ?? 'active');

    $where  = $status === 'all' ? '1=1' : 'ss.is_active = 1';
    $params = [];
    $types  = '';

    if ($search) {
        $sq      = '%' . $search . '%';
        $where  .= " AND (s.student_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?
                         OR CONCAT(s.first_name,' ',s.last_name) LIKE ? OR ss.scholar_type LIKE ?)";
        array_push($params, $sq, $sq, $sq, $sq, $sq);
        $types  .= 'sssss';
    }

    $sql = "
        SELECT ss.id AS scholarship_id,
               ss.student_id, ss.scholar_type, ss.grantor, ss.scholarship_amount,
               ss.semester, ss.is_active, ss.status AS scholarship_status,
               ss.granted_by_email, ss.reviewed_by_email, ss.reviewed_at,
               ss.notes, ss.created_at AS granted_at,
               ss.revoke_reason, ss.revoked_at,
               s.student_number, s.first_name, s.last_name, s.program,
               s.year_level, s.student_category, s.enrollment_status,
               s.payment_status, s.payment_plan,
               COALESCE(tf.total_assessment, 0) AS totalAssessment,
               COALESCE(tf.discount, 0)          AS tuitionDiscount
        FROM student_scholarships ss
        JOIN students s  ON s.id  = ss.student_id
        LEFT JOIN (SELECT student_id, total_assessment, subtotal, units, discount, installment_fee, tuition_fee FROM tuition_fees WHERE id IN (SELECT MAX(id) FROM tuition_fees GROUP BY student_id)) tf ON tf.student_id = ss.student_id
        WHERE $where
        ORDER BY ss.is_active DESC, ss.created_at DESC
    ";

    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res  = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = [
            'scholarshipId'     => (int)$r['scholarship_id'],
            'studentId'         => (int)$r['student_id'],
            'studentNumber'     => $r['student_number'],
            'firstName'         => $r['first_name'],
            'lastName'          => $r['last_name'],
            'fullName'          => trim($r['first_name'] . ' ' . $r['last_name']),
            'program'           => $r['program'],
            'yearLevel'         => $r['year_level'],
            'studentCategory'   => $r['student_category'],
            'enrollmentStatus'  => $r['enrollment_status'],
            'paymentStatus'     => $r['payment_status'],
            'paymentPlan'       => $r['payment_plan'],
            'scholarType'       => $r['scholar_type'],
            'grantor'           => $r['grantor'] ?? '',
            'scholarshipAmount' => (float)$r['scholarship_amount'],
            'totalAssessment'   => (float)$r['totalAssessment'],
            'tuitionDiscount'   => (float)$r['tuitionDiscount'],
            'semester'          => $r['semester'],
            'isActive'          => (int)$r['is_active'],
            'scholarshipStatus' => $r['scholarship_status'] ?? 'approved',
            'grantedByEmail'    => $r['granted_by_email'] ?? '',
            'reviewedByEmail'   => $r['reviewed_by_email'] ?? '',
            'reviewedAt'        => $r['reviewed_at'] ?? '',
            'notes'             => $r['notes'] ?? '',
            'grantedAt'         => $r['granted_at'] ?? '',
            'revokeReason'      => $r['revoke_reason'] ?? '',
            'revokedAt'         => $r['revoked_at'] ?? '',
        ];
    }
    $stmt->close();

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'  => true,
        'scholars' => $rows,
        'count'    => count($rows),
        'active'   => count(array_filter($rows, fn($r) => $r['isActive'])),
    ]);
}