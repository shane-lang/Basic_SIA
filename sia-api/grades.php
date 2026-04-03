<?php
// grades.php — Updated for normalized schema
// Changes:
//   - _updateOverallGrade() no longer syncs to enrollments (grade columns dropped)
//   - c.instructor removed from queries (column dropped, join faculty instead)
//   - enrollments.prelim_grade/midterm_grade/final_grade references removed
//   - admin_submit_to_registrar: removed check on e.prelim_grade etc., uses student_grades

require_once __DIR__ . '/config.php';
applyCors();
ob_start(); // capture stray notices so JSON is never corrupted
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth_middleware.php';

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
            'totalAssessment' =>['roles_full'=>['admin','accounting','student'],'roles_masked'=>['registrar'],'mask_fn'=>'maskAmount'],
            'totalPaid'       =>['roles_full'=>['admin','accounting','student'],'roles_masked'=>['registrar'],'mask_fn'=>'maskAmount'],
            'balance'         =>['roles_full'=>['admin','accounting','student'],'roles_masked'=>['registrar'],'mask_fn'=>'maskAmount'],
            'gcash_reference' =>['roles_full'=>['admin','accounting'],'roles_masked'=>[]],
            'gcashReference'  =>['roles_full'=>['admin','accounting'],'roles_masked'=>[]],
            'reference_number'=>['roles_full'=>['admin','accounting'],'roles_masked'=>[]],
            'password'        =>['roles_full'=>[],'roles_masked'=>[]],
            'token'           =>['roles_full'=>[],'roles_masked'=>[]],
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

$authUser = requireAuth($conn);   // all grade endpoints require a valid session

$action = trim($_GET['action'] ?? $_POST['action'] ?? '');
$data   = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {

case 'search_students':
    $q = '%' . trim($_GET['q'] ?? '') . '%';
    $stmt = $conn->prepare("
        SELECT s.id, s.student_number, s.first_name, s.last_name,
               COALESCE(p.name, s.program) AS program,
               s.year_level, s.semester, s.student_type,
               s.enrollment_status
        FROM students s
        LEFT JOIN programs p ON p.id = s.program_id
        WHERE s.enrollment_status IN ('Enrolled','Pending')
          AND (s.student_number LIKE ?
            OR s.first_name LIKE ?
            OR s.last_name LIKE ?
            OR CONCAT(s.first_name,' ',s.last_name) LIKE ?
            OR CONCAT(s.last_name,' ',s.first_name) LIKE ?)
        ORDER BY s.last_name, s.first_name
        LIMIT 20
    ");
    $stmt->bind_param('sssss', $q, $q, $q, $q, $q);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();
    $students = [];
    while ($r = $res->fetch_assoc()) $students[] = $r;
    $students = applyPrivacyList($students, $authUser, 'student');
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'students' => $students]);
    break;

case 'get_student_subjects':
    $sid = (int)($_GET['student_id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$sid) { echo json_encode(['success'=>false,'message'=>'student_id required']); break; }

    $subjStmt = $conn->prepare("
        SELECT e.id AS enrollment_id, e.semester, e.status,
               c.id AS course_id, c.code, c.name, c.credits,
               COALESCE(c.lec_units,c.credits) AS lec_units, COALESCE(c.lab_units,0) AS lab_units,
               COALESCE(c.is_general,0) AS is_general, COALESCE(c.is_lab,0) AS is_lab,
               CONCAT(COALESCE(f.first_name,''),' ',COALESCE(f.last_name,'')) AS instructor,
               MAX(CASE WHEN sg.term='Prelim'  THEN sg.grade END) AS prelim,
               MAX(CASE WHEN sg.term='Midterm' THEN sg.grade END) AS midterm,
               MAX(CASE WHEN sg.term='Final'   THEN sg.grade END) AS final,
               MAX(CASE WHEN sg.term='Prelim'  THEN sg.updated_at END) AS prelim_at,
               MAX(CASE WHEN sg.term='Midterm' THEN sg.updated_at END) AS midterm_at,
               MAX(CASE WHEN sg.term='Final'   THEN sg.updated_at END) AS final_at
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN faculty f ON f.user_id = c.faculty_id
        LEFT JOIN student_grades sg ON sg.enrollment_id = e.id
        WHERE e.student_id = ?
          AND e.status IN ('Enrolled','Pending')
        GROUP BY e.id, c.id
        ORDER BY c.code ASC
    ");
    $subjStmt->bind_param('i', $sid);
    $subjStmt->execute();
    $res = $subjStmt->get_result();
    $subjStmt->close();
    $subjects = [];
    while ($r = $res->fetch_assoc()) {
        $prelim  = $r['prelim']  !== null ? (float)$r['prelim']  : null;
        $midterm = $r['midterm'] !== null ? (float)$r['midterm'] : null;
        $final   = $r['final']   !== null ? (float)$r['final']   : null;
        $vals    = array_filter([$prelim,$midterm,$final], function($v){ return $v !== null; });
        $overall = count($vals) > 0 ? round(array_sum($vals)/count($vals), 2) : null;
        $remarks = $final !== null ? ($overall <= 3.0 ? 'Passed' : 'Failed') : 'In Progress';
        $subjects[] = [
            'enrollmentId' => (int)$r['enrollment_id'],
            'courseId'     => (int)$r['course_id'],
            'code'         => cleanCode($r['code']),
            'name'         => $r['name'],
            'credits'      => (int)$r['credits'],
            'lecUnits'     => (int)($r['lec_units'] ?? $r['credits']),
            'labUnits'     => (int)($r['lab_units'] ?? 0),
            'isGeneral'    => (bool)($r['is_general'] ?? false),
            'isLab'        => (bool)($r['is_lab'] ?? false),
            'instructor'   => $r['instructor'] ?? '',
            'semester'     => $r['semester']   ?? '',
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
    $subjects = applyPrivacyList($subjects, $authUser, 'grade');
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'subjects' => $subjects]);
    break;

case 'save_grade':
    $eid         = (int)($data['enrollment_id'] ?? 0);
    $sid         = (int)($data['student_id']    ?? 0);
    $cid         = (int)($data['course_id']     ?? 0);
    $term        = trim($data['term']           ?? '');
    $gradeVal    = ($data['grade'] !== null && $data['grade'] !== '') ? (float)$data['grade'] : null;
    $submittedBy = (int)($data['submitted_by']  ?? 0);

    if (!$eid || !$sid || !$cid || !in_array($term, ['Prelim','Midterm','Final'])) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success'=>false,'message'=>'Missing required fields']); break;
    }
    if ($gradeVal !== null && ($gradeVal < 1.0 || $gradeVal > 5.0)) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success'=>false,'message'=>'Grade must be 1.00–5.00']); break;
    }

    $semRow   = (($_r=$conn->query("SELECT semester FROM enrollments WHERE id=$eid LIMIT 1")) ? $_r->fetch_assoc() : null);
    $semester = $semRow['semester'] ?? '';

    if ($gradeVal !== null) {
        $stmt = $conn->prepare("
            INSERT INTO student_grades (enrollment_id, student_id, course_id, semester, term, grade, submitted_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE grade=VALUES(grade), submitted_by=VALUES(submitted_by), updated_at=NOW()
        ");
        $stmt->bind_param("iiissdi", $eid, $sid, $cid, $semester, $term, $gradeVal, $submittedBy);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO student_grades (enrollment_id, student_id, course_id, semester, term, grade, submitted_by)
            VALUES (?, ?, ?, ?, ?, NULL, ?)
            ON DUPLICATE KEY UPDATE grade=NULL, submitted_by=VALUES(submitted_by), updated_at=NOW()
        ");
        $stmt->bind_param("iiissi", $eid, $sid, $cid, $semester, $term, $submittedBy);
    }

    if ($stmt->execute()) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success'=>true,'message'=>"$term grade saved"]);
    } else {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success'=>false,'message'=>$conn->error]);
    }
    $stmt->close();
    break;

case 'get_semesters':
    $sid = _resolveStudent($conn);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$sid) { echo json_encode(['success'=>false,'message'=>'Student not found']); break; }
    $res = $conn->query("
        SELECT DISTINCT semester FROM enrollments
        WHERE student_id=$sid AND status IN ('Enrolled','Pending','Completed','Failed')
        ORDER BY semester DESC
    ");
    $semesters = [];
    while ($r = $res->fetch_assoc()) if ($r['semester']) $semesters[] = $r['semester'];
    if (empty($semesters)) {
        $stRow = (($_r=$conn->query("SELECT semester FROM students WHERE id=$sid LIMIT 1")) ? $_r->fetch_assoc() : null);
        if ($stRow && $stRow['semester']) $semesters[] = $stRow['semester'];
    }
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success'=>true,'semesters'=>$semesters]);
    break;

case 'get_grades':
    $sid = _resolveStudent($conn);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$sid) { echo json_encode(['success'=>false,'message'=>'Student not found']); break; }

    $sem = trim($_GET['semester'] ?? '');
    $semFilter = $sem ? "AND e.semester='".$sem."'" : '';

    $res = $conn->query("
        SELECT e.id AS enrollment_id, e.semester, e.status,
               c.code, c.name, c.credits,
               COALESCE(c.lec_units,c.credits) AS lec_units, COALESCE(c.lab_units,0) AS lab_units,
               COALESCE(c.is_general,0) AS is_general, COALESCE(c.is_lab,0) AS is_lab,
               CONCAT(f.first_name,' ',f.last_name) AS instructor,
               MAX(CASE WHEN sg.term='Prelim'  THEN sg.grade END) AS prelim,
               MAX(CASE WHEN sg.term='Midterm' THEN sg.grade END) AS midterm,
               MAX(CASE WHEN sg.term='Final'   THEN sg.grade END) AS final
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN faculty f ON f.user_id = c.faculty_id
        LEFT JOIN student_grades sg ON sg.enrollment_id = e.id
        WHERE e.student_id=$sid AND e.status IN ('Enrolled','Pending','Completed','Failed') $semFilter
        GROUP BY e.id, c.id
        ORDER BY c.code ASC
    ");

    $grades = []; $totalW = 0; $totalC = 0;
    while ($r = $res->fetch_assoc()) {
        $prelim  = $r['prelim']  !== null ? (float)$r['prelim']  : null;
        $midterm = $r['midterm'] !== null ? (float)$r['midterm'] : null;
        $final   = $r['final']   !== null ? (float)$r['final']   : null;
        $vals    = array_filter([$prelim,$midterm,$final], function($v){ return $v !== null; });
        $overall = count($vals) > 0 ? round(array_sum($vals)/count($vals), 2) : null;
        $remarks = $final !== null ? ($overall <= 3.0 ? 'Passed' : 'Failed') : 'In Progress';
        $credits = (int)$r['credits'];
        if ($overall !== null && $final !== null) { $totalW += $overall*$credits; $totalC += $credits; }
        $grades[] = [
            'enrollmentId' => (int)$r['enrollment_id'],
            'code'         => cleanCode($r['code']),
            'name'         => $r['name'],
            'credits'      => $credits,
            'lecUnits'     => (int)($r['lec_units'] ?? $credits),
            'labUnits'     => (int)($r['lab_units'] ?? 0),
            'isGeneral'    => (bool)($r['is_general'] ?? false),
            'isLab'        => (bool)($r['is_lab'] ?? false),
            'instructor'   => $r['instructor'] ?? '',
            'semester'     => $r['semester'] ?? '',
            'status'       => $r['status'],
            'prelim'       => $prelim,
            'midterm'      => $midterm,
            'final'        => $final,
            'overall'      => $overall,
            'remarks'      => $remarks,
            'description'  => '',
        ];
    }
    $gwa = $totalC > 0 ? round($totalW/$totalC, 2) : null;
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success'=>true,'grades'=>$grades,'gwa'=>$gwa,'totalCredits'=>$totalC]);
    break;

case 'get_grade_summary':
    $sid = _resolveStudent($conn);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$sid) { echo json_encode(['success'=>false,'message'=>'Student not found']); break; }

    $res = $conn->query("
        SELECT e.semester, c.credits,
               MAX(CASE WHEN sg.term='Final' THEN sg.grade END) AS final_grade
        FROM enrollments e
        JOIN courses c ON e.course_id=c.id
        LEFT JOIN student_grades sg ON sg.enrollment_id=e.id
        WHERE e.student_id=$sid AND e.status IN ('Enrolled','Pending','Completed','Failed')
        GROUP BY e.id
    ");
    $semMap=[]; $totalW=0; $totalC=0;
    while ($r = $res->fetch_assoc()) {
        $sem=$r['semester']??'Current'; $fg=$r['final_grade']!==null?(float)$r['final_grade']:null; $cr=(int)$r['credits'];
        if (!isset($semMap[$sem])) $semMap[$sem]=['weighted'=>0,'credits'=>0];
        if ($fg!==null) { $semMap[$sem]['weighted']+=$fg*$cr; $semMap[$sem]['credits']+=$cr; $totalW+=$fg*$cr; $totalC+=$cr; }
    }
    $semGWA=[];
    foreach ($semMap as $sem=>$d) {
        $semGWA[]=['semester'=>$sem,'gwa'=>$d['credits']>0?round($d['weighted']/$d['credits'],2):null,'credits'=>$d['credits']];
    }
    $overallGWA=$totalC>0?round($totalW/$totalC,2):null;
    $status=$overallGWA===null?'No grades yet':($overallGWA<=1.5?'With Honors':($overallGWA<=3.0?'Good Standing':'Academic Concern'));
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success'=>true,'overallGWA'=>$overallGWA,'academicStatus'=>$status,'semesterGWA'=>$semGWA]);
    break;

case 'admin_get_faculty':
    $res = $conn->query("
        SELECT f.id, f.faculty_id, f.first_name, f.last_name,
               f.department, f.specialty, f.status,
               COUNT(fs.id) AS subject_count
        FROM faculty f
        LEFT JOIN faculty_subjects fs ON fs.faculty_id = f.id
        WHERE f.status = 'Active'
        GROUP BY f.id
        ORDER BY f.last_name, f.first_name
    ");
    $faculty = [];
    while ($r = $res->fetch_assoc()) {
        // Get subject codes for backward compat
        $codes = [];
        $cRes = $conn->query("SELECT course_code FROM faculty_subjects WHERE faculty_id={$r['id']}");
        while ($cr = $cRes->fetch_assoc()) $codes[] = $cr['course_code'];
        $r['subjects_arr'] = $codes;
        $r['course_count'] = (int)$r['subject_count'];
        $faculty[] = $r;
    }
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'faculty' => $faculty]);
    break;

case 'admin_get_faculty_subjects':
    $fid = (int)($_GET['faculty_id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$fid) { echo json_encode(['success'=>false,'message'=>'faculty_id required']); break; }

    $res = $conn->query("
        SELECT c.id AS course_id, c.code, c.name, c.credits,
               c.semester, c.department,
               COALESCE(p.name, c.program) AS program,
               c.year_level,
               COUNT(DISTINCT e.id) AS enrolled_count,
               SUM(CASE WHEN EXISTS(SELECT 1 FROM student_grades sg WHERE sg.enrollment_id=e.id AND sg.term='Prelim')  THEN 1 ELSE 0 END) AS prelim_done,
               SUM(CASE WHEN EXISTS(SELECT 1 FROM student_grades sg WHERE sg.enrollment_id=e.id AND sg.term='Midterm') THEN 1 ELSE 0 END) AS midterm_done,
               SUM(CASE WHEN EXISTS(SELECT 1 FROM student_grades sg WHERE sg.enrollment_id=e.id AND sg.term='Final')   THEN 1 ELSE 0 END) AS final_done
        FROM faculty_subjects fs
        JOIN courses c ON c.id = fs.course_id
        LEFT JOIN programs p ON p.id = (
            SELECT pc.program_id FROM program_courses pc WHERE pc.course_id = c.id LIMIT 1
        )
        LEFT JOIN enrollments e ON e.course_id = c.id AND e.status IN ('Enrolled','Pending')
        WHERE fs.faculty_id = $fid
        GROUP BY c.id
        ORDER BY c.code ASC
    ");
    $subjects = [];
    while ($r = $res->fetch_assoc()) {
        $enrolled = (int)$r['enrolled_count'];
        $subjects[] = [
            'courseId'        => (int)$r['course_id'],
            'code'            => cleanCode($r['code']),
            'name'            => $r['name'],
            'credits'         => (int)$r['credits'],
            'semester'        => $r['semester'] ?? '',
            'department'      => $r['department'] ?? '',
            'program'         => $r['program'] ?? '',
            'yearLevel'       => $r['year_level'] ?? '',
            'enrolledCount'   => $enrolled,
            'prelimDone'      => (int)$r['prelim_done'],
            'midtermDone'     => (int)$r['midterm_done'],
            'finalDone'       => (int)$r['final_done'],
            'gradeCompletion' => $enrolled > 0
                ? round(((int)$r['prelim_done']+(int)$r['midterm_done']+(int)$r['final_done']) / ($enrolled * 3) * 100)
                : 0,
        ];
    }
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'subjects' => $subjects]);
    break;

case 'admin_get_course_students':
    $cid = (int)($_GET['course_id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$cid) { echo json_encode(['success'=>false,'message'=>'course_id required']); break; }

    $res = $conn->query("
        SELECT e.id AS enrollment_id, e.semester, e.status AS enrollment_status,
               s.id AS student_id, s.student_number, s.first_name, s.last_name,
               s.year_level,
               COALESCE(p.name, s.program) AS program,
               MAX(CASE WHEN sg.term='Prelim'  THEN sg.grade END) AS prelim,
               MAX(CASE WHEN sg.term='Midterm' THEN sg.grade END) AS midterm,
               MAX(CASE WHEN sg.term='Final'   THEN sg.grade END) AS final,
               COALESCE(e.grade_released, 0) AS grade_released
        FROM enrollments e
        JOIN students s ON s.id = e.student_id
        LEFT JOIN programs p ON p.id = s.program_id
        LEFT JOIN student_grades sg ON sg.enrollment_id = e.id
        WHERE e.course_id = $cid AND e.status IN ('Enrolled','Pending')
        GROUP BY e.id, s.id
        ORDER BY s.last_name, s.first_name
    ");
    $students = [];
    while ($r = $res->fetch_assoc()) {
        $prelim  = $r['prelim']  !== null ? (float)$r['prelim']  : null;
        $midterm = $r['midterm'] !== null ? (float)$r['midterm'] : null;
        $final   = $r['final']   !== null ? (float)$r['final']   : null;
        $vals    = array_filter([$prelim,$midterm,$final], fn($v) => $v !== null);
        $overall = count($vals) > 0 ? round(array_sum($vals)/count($vals), 2) : null;
        $remarks = $final !== null ? ($overall <= 3.0 ? 'Passed' : 'Failed') : 'In Progress';
        $students[] = [
            'enrollmentId'     => (int)$r['enrollment_id'],
            'studentId'        => (int)$r['student_id'],
            'studentNumber'    => $r['student_number'],
            'firstName'        => $r['first_name'],
            'lastName'         => $r['last_name'],
            'yearLevel'        => $r['year_level'] ?? '',
            'program'          => $r['program'] ?? '',
            'semester'         => $r['semester'] ?? '',
            'enrollmentStatus' => $r['enrollment_status'],
            'prelim'           => $prelim,
            'midterm'          => $midterm,
            'final'            => $final,
            'overall'          => $overall,
            'remarks'          => $remarks,
            'gradeReleased'    => (int)$r['grade_released'] === 1,
        ];
    }
    $students = applyPrivacyList($students, $authUser, 'student');
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'students' => $students]);
    break;

case 'admin_save_grade':
    $eid         = (int)($data['enrollment_id'] ?? 0);
    $sid         = (int)($data['student_id']    ?? 0);
    $cid         = (int)($data['course_id']     ?? 0);
    $term        = trim($data['term']           ?? '');
    $gradeVal    = ($data['grade'] !== '' && $data['grade'] !== null) ? (float)$data['grade'] : null;
    $submittedBy = (int)($data['submitted_by']  ?? 0);

    if (!$eid || !$sid || !$cid || !in_array($term, ['Prelim','Midterm','Final'])) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success'=>false,'message'=>'Missing required fields']); break;
    }
    if ($gradeVal !== null && ($gradeVal < 1.0 || $gradeVal > 5.0)) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success'=>false,'message'=>'Grade must be between 1.00 and 5.00']); break;
    }

    $semRow   = (($_r=$conn->query("SELECT semester FROM enrollments WHERE id=$eid LIMIT 1")) ? $_r->fetch_assoc() : null);
    $semester = $semRow['semester'] ?? '';

    if ($gradeVal !== null) {
        $stmt = $conn->prepare("
            INSERT INTO student_grades (enrollment_id, student_id, course_id, semester, term, grade, submitted_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE grade=VALUES(grade), submitted_by=VALUES(submitted_by), updated_at=NOW()
        ");
        $stmt->bind_param("iiissdi", $eid, $sid, $cid, $semester, $term, $gradeVal, $submittedBy);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO student_grades (enrollment_id, student_id, course_id, semester, term, grade, submitted_by)
            VALUES (?, ?, ?, ?, ?, NULL, ?)
            ON DUPLICATE KEY UPDATE grade=NULL, submitted_by=VALUES(submitted_by), updated_at=NOW()
        ");
        $stmt->bind_param("iiissi", $eid, $sid, $cid, $semester, $term, $submittedBy);
    }

    if ($stmt->execute()) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => true, 'message' => "$term grade saved successfully"]);
    } else {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    $stmt->close();
    break;

case 'admin_submit_to_registrar':
    $cid         = (int)($data['course_id']    ?? 0);
    $submittedBy = (int)($data['submitted_by'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$cid) { echo json_encode(['success'=>false,'message'=>'course_id required']); break; }

    // Submit enrollments that have at least one grade in student_grades
    $upd = $conn->prepare("
        UPDATE enrollments e
        SET grade_submitted = 1, grade_submitted_at = NOW()
        WHERE e.course_id = ?
          AND e.status IN ('Enrolled','Pending')
          AND e.grade_submitted = 0
          AND EXISTS (
            SELECT 1 FROM student_grades sg WHERE sg.enrollment_id = e.id
          )
    ");
    $upd->bind_param("i", $cid);
    $upd->execute();
    $affected = $upd->affected_rows;
    $upd->close();

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'  => true,
        'message'  => "$affected student grade(s) submitted to registrar",
        'affected' => $affected,
    ]);
    break;

case 'registrar_pending_grades':
    $res = $conn->query("
        SELECT c.id AS course_id, c.code, c.name,
               CONCAT(f.first_name,' ',f.last_name) AS faculty_name,
               c.semester, c.department,
               COUNT(e.id) AS submitted_count,
               SUM(CASE WHEN e.grade_released=1 THEN 1 ELSE 0 END) AS released_count
        FROM enrollments e
        JOIN courses c ON c.id = e.course_id
        LEFT JOIN faculty f ON f.user_id = c.faculty_id
        WHERE e.grade_submitted = 1
        GROUP BY c.id
        ORDER BY c.code ASC
    ");
    $courses = [];
    while ($r = $res->fetch_assoc()) {
        $courses[] = [
            'courseId'       => (int)$r['course_id'],
            'code'           => cleanCode($r['code']),
            'name'           => $r['name'],
            'facultyName'    => $r['faculty_name'] ?? 'N/A',
            'semester'       => $r['semester'] ?? '',
            'department'     => $r['department'] ?? '',
            'submittedCount' => (int)$r['submitted_count'],
            'releasedCount'  => (int)$r['released_count'],
            'pendingRelease' => (int)$r['submitted_count'] - (int)$r['released_count'],
        ];
    }
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'courses' => $courses]);
    break;

case 'registrar_release_grades':
    $cid = (int)($data['course_id']    ?? 0);
    $eid = (int)($data['enrollment_id'] ?? 0);

    if (!$cid && !$eid) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success'=>false,'message'=>'course_id or enrollment_id required']); break;
    }

    if ($eid) {
        $upd = $conn->prepare("UPDATE enrollments SET grade_released=1, grade_released_at=NOW() WHERE id=? AND grade_submitted=1");
        $upd->bind_param("i", $eid);
    } else {
        $upd = $conn->prepare("UPDATE enrollments SET grade_released=1, grade_released_at=NOW() WHERE course_id=? AND grade_submitted=1 AND grade_released=0");
        $upd->bind_param("i", $cid);
    }

    $upd->execute();
    $affected = $upd->affected_rows;
    $upd->close();

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'message' => "$affected grade(s) released to students", 'affected' => $affected]);
    break;

case 'get_released_grades':
    $sid = _resolveStudent($conn);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$sid) { echo json_encode(['success'=>false,'message'=>'Student not found']); break; }

    $sem = trim($_GET['semester'] ?? '');
    $semFilter = $sem ? "AND e.semester='".$sem."'" : '';

    $res = $conn->query("
        SELECT e.id AS enrollment_id, e.semester, e.grade_released,
               c.code, c.name, c.credits,
               COALESCE(c.lec_units,c.credits) AS lec_units, COALESCE(c.lab_units,0) AS lab_units,
               COALESCE(c.is_general,0) AS is_general, COALESCE(c.is_lab,0) AS is_lab,
               CONCAT(f.first_name,' ',f.last_name) AS instructor,
               MAX(CASE WHEN sg.term='Prelim'  THEN sg.grade END) AS prelim,
               MAX(CASE WHEN sg.term='Midterm' THEN sg.grade END) AS midterm,
               MAX(CASE WHEN sg.term='Final'   THEN sg.grade END) AS final
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN faculty f ON f.user_id = c.faculty_id
        LEFT JOIN student_grades sg ON sg.enrollment_id = e.id
        WHERE e.student_id = $sid
          AND e.status IN ('Enrolled','Pending','Completed','Failed')
          AND e.grade_released = 1
          $semFilter
        GROUP BY e.id, c.id
        ORDER BY c.code ASC
    ");

    $grades = []; $totalW = 0; $totalC = 0;
    while ($r = $res->fetch_assoc()) {
        $prelim  = $r['prelim']  !== null ? (float)$r['prelim']  : null;
        $midterm = $r['midterm'] !== null ? (float)$r['midterm'] : null;
        $final   = $r['final']   !== null ? (float)$r['final']   : null;
        $vals    = array_filter([$prelim,$midterm,$final], fn($v) => $v !== null);
        $overall = count($vals) > 0 ? round(array_sum($vals)/count($vals), 2) : null;
        $remarks = $final !== null ? ($overall <= 3.0 ? 'Passed' : 'Failed') : 'In Progress';
        $credits = (int)$r['credits'];
        if ($overall !== null && $final !== null) { $totalW += $overall*$credits; $totalC += $credits; }
        $grades[] = [
            'enrollmentId' => (int)$r['enrollment_id'],
            'code'         => cleanCode($r['code']),
            'name'         => $r['name'],
            'credits'      => $credits,
            'instructor'   => $r['instructor'] ?? '',
            'semester'     => $r['semester'] ?? '',
            'prelim'       => $prelim,
            'midterm'      => $midterm,
            'final'        => $final,
            'overall'      => $overall,
            'remarks'      => $remarks,
        ];
    }
    $gwa = $totalC > 0 ? round($totalW/$totalC, 2) : null;
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success'=>true,'grades'=>$grades,'gwa'=>$gwa,'totalCredits'=>$totalC]);
    break;

default:
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success'=>false,'message'=>'Unknown action: '.$action]);
}

function _resolveStudent($conn) {
    $sid = (int)($_GET['student_id'] ?? 0);
    if (!$sid) {
        $uid = (int)($_GET['user_id'] ?? 0);
        if ($uid) {
            $r = (($_r=$conn->query("SELECT id FROM students WHERE user_id=$uid LIMIT 1")) ? $_r->fetch_assoc() : null);
            $sid = $r ? (int)$r['id'] : 0;
        }
    }
    return $sid;
}