<?php

// ── cleanCode() ──────────────────────────────────────────────────────────────
// Strips internal disambiguation suffixes from course codes.
// These suffixes are used in the DB to keep codes unique across programs
// that share the same subject (e.g. GE103 shared by BSA and BSCA gets
// stored as GE103-BMD and GE103-CA so they can have separate records).
// Legitimate curriculum codes with dashes (RE-FUN013, GE-ENG013,
// BN-MGT013, IT-CSA013, AC-TAX013, etc.) are NOT affected.
require_once __DIR__ . '/config.php';
applyCors();
ob_start();
require_once __DIR__ . '/helpers.php';

/**
 * faculty.php — Instructor Portal API
 * Handles all instructor-facing requests:
 *   - get_my_courses
 *   - get_course_students
 *   - save_grade
 *   - submit_grades_to_registrar
 *   - get_profile
 */

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

$action = trim($_GET['action'] ?? $_POST['action'] ?? '');
$data   = json_decode(file_get_contents('php://input'), true) ?? [];

// ── Require faculty auth ──
$authUser = requireAuth($conn, 'faculty');
$userId   = (int)$authUser['user_id'];

// ── Resolve faculty row from user_id ──
// Primary: match directly on faculty.user_id
$facRow = $conn->query("
    SELECT f.id, f.user_id, f.faculty_id, f.first_name, f.last_name,
           f.department, f.specialty, f.subjects,
           IFNULL(f.program_levels,'[]') AS program_levels
    FROM faculty f
    WHERE f.user_id = $userId
    LIMIT 1
")->fetch_assoc();

// Fallback: match by email (for legacy records with no user_id linked)
if (!$facRow) {
    $emailRow = (($_r=$conn->query("SELECT email FROM users WHERE id=$userId LIMIT 1")) ? $_r->fetch_assoc() : null);
    if ($emailRow) {
        $em = $emailRow['email'];
        $facRow = $conn->query("
            SELECT f.id, f.user_id, f.faculty_id, f.first_name, f.last_name,
                   f.department, f.specialty, f.subjects,
                   IFNULL(f.program_levels,'[]') AS program_levels
            FROM faculty f
            WHERE f.email = '$em'
            LIMIT 1
        ")->fetch_assoc();
        // Backfill user_id so future lookups hit primary path
        if ($facRow && empty($facRow['user_id'])) {
            $conn->query("UPDATE faculty SET user_id=$userId WHERE id={$facRow['id']}");
        }
    }
}

if (!$facRow) {
    ob_end_clean();
    echo json_encode(['success'=>false,'message'=>'Faculty record not found. Contact admin.']);
    exit();
}

$facId       = (int)$facRow['id'];
$subjectCodes = json_decode($facRow['subjects'] ?? '[]', true) ?: [];

switch ($action) {

// ════════════════════════════════════════════════════════════════
// GET PROFILE
// ════════════════════════════════════════════════════════════════
case 'get_profile':
    $facRow['subjects']       = json_decode($facRow['subjects']       ?? '[]', true);
    $facRow['program_levels'] = json_decode($facRow['program_levels'] ?? '[]', true);
    ob_end_clean();
    echo json_encode(['success'=>true,'faculty'=>$facRow]);
    break;

// ════════════════════════════════════════════════════════════════
// GET MY COURSES — courses assigned to this instructor
// ════════════════════════════════════════════════════════════════
case 'get_my_courses':
    $courses = [];
    if (empty($subjectCodes)) {
        ob_end_clean();
        echo json_encode(['success' => true, 'courses' => []]);
        break;
    }
    if (count($subjectCodes) > 0) {
        // Match exact code AND suffix variants (e.g. "AEC111" matches "AEC111-BMD")
        $likeConditions = implode(' OR ', array_fill(0, count($subjectCodes), "c.code = ? OR c.code LIKE CONCAT(?, '-%')"));
        $doubledCodes   = [];
        foreach ($subjectCodes as $code) { $doubledCodes[] = $code; $doubledCodes[] = $code; }
        $types = str_repeat('ss', count($subjectCodes));

        // Grades are stored in student_grades table, not enrollments
        $stmt = $conn->prepare("
            SELECT c.id, c.code, c.name, c.credits, c.semester,
                   c.department, c.program, c.year_level, c.is_lab,
                   COALESCE(c.is_general,0) AS is_general,
                   COALESCE(c.lec_units,c.credits) AS lec_units,
                   COALESCE(c.lab_units,0) AS lab_units,
                   COUNT(DISTINCT e.id)                                  AS enrolled_count,
                   SUM(CASE WHEN e.grade_submitted=1 THEN 1 ELSE 0 END) AS submitted_count,
                   SUM(CASE WHEN e.grade_released=1  THEN 1 ELSE 0 END) AS released_count,
                   COUNT(DISTINCT sg_p.id)                               AS prelim_done,
                   COUNT(DISTINCT sg_m.id)                               AS midterm_done,
                   COUNT(DISTINCT sg_f.id)                               AS final_done
            FROM courses c
            LEFT JOIN enrollments e
                   ON e.course_id = c.id AND e.status IN ('Enrolled','Pending')
            LEFT JOIN student_grades sg_p
                   ON sg_p.enrollment_id = e.id AND sg_p.term = 'Prelim'  AND sg_p.grade IS NOT NULL
            LEFT JOIN student_grades sg_m
                   ON sg_m.enrollment_id = e.id AND sg_m.term = 'Midterm' AND sg_m.grade IS NOT NULL
            LEFT JOIN student_grades sg_f
                   ON sg_f.enrollment_id = e.id AND sg_f.term = 'Final'   AND sg_f.grade IS NOT NULL
            WHERE $likeConditions
            GROUP BY c.id
            ORDER BY c.code ASC
        ");
        $stmt->bind_param($types, ...$doubledCodes);
        $stmt->execute();
        $res = $stmt->get_result();

        // FIX: Merge suffix-variants (e.g. GE103-BMD + GE103-CA) into one entry
        // per clean base code. Without this, a faculty assigned "GE103" gets one
        // card per DB variant, each showing its own enrolled_count — so 1 student
        // enrolled in GE103-BMD appears as "1 student" across 8 separate course cards.
        $byCode = [];   // keyed by cleanCode
        while ($r = $res->fetch_assoc()) {
            $baseCode = cleanCode($r['code']);

            if (!isset($byCode[$baseCode])) {
                // First variant seen — use its metadata as the canonical row.
                // Store the lowest numeric id so course_id stays stable.
                $byCode[$baseCode] = [
                    'id'             => (int)$r['id'],
                    'code'           => $baseCode,
                    'name'           => $r['name'],
                    'credits'        => (int)$r['credits'],
                    'semester'       => $r['semester'] ?? '',
                    'department'     => $r['department'] ?? '',
                    'program'        => $r['program'] ?? '',
                    'yearLevel'      => $r['year_level'] ?? '',
                    'isLab'          => (bool)$r['is_lab'],
                    'isGeneral'      => (bool)$r['is_general'],
                    'lecUnits'       => (int)$r['lec_units'],
                    'labUnits'       => (int)$r['lab_units'],
                    // Accumulating counters:
                    'enrolledCount'  => (int)$r['enrolled_count'],
                    'submittedCount' => (int)$r['submitted_count'],
                    'releasedCount'  => (int)$r['released_count'],
                    'prelimDone'     => (int)$r['prelim_done'],
                    'midtermDone'    => (int)$r['midterm_done'],
                    'finalDone'      => (int)$r['final_done'],
                    // Track all variant course_ids so get_course_students can filter them
                    '_courseIds'     => [(int)$r['id']],
                ];
            } else {
                // Subsequent variant — sum up the counts only.
                $byCode[$baseCode]['enrolledCount']  += (int)$r['enrolled_count'];
                $byCode[$baseCode]['submittedCount'] += (int)$r['submitted_count'];
                $byCode[$baseCode]['releasedCount']  += (int)$r['released_count'];
                $byCode[$baseCode]['prelimDone']     += (int)$r['prelim_done'];
                $byCode[$baseCode]['midtermDone']    += (int)$r['midterm_done'];
                $byCode[$baseCode]['finalDone']      += (int)$r['final_done'];
                $byCode[$baseCode]['_courseIds'][]    = (int)$r['id'];
            }
        }
        $stmt->close();

        foreach ($byCode as $entry) {
            $enrolled = $entry['enrolledCount'];
            $done     = $entry['prelimDone'] + $entry['midtermDone'] + $entry['finalDone'];
            $courses[] = [
                'id'             => $entry['id'],
                'code'           => $entry['code'],
                'name'           => $entry['name'],
                'credits'        => $entry['credits'],
                'semester'       => $entry['semester'],
                'department'     => $entry['department'],
                'program'        => $entry['program'],
                'yearLevel'      => $entry['yearLevel'],
                'isLab'          => $entry['isLab'],
                'isGeneral'      => $entry['isGeneral'],
                'lecUnits'       => $entry['lecUnits'],
                'labUnits'       => $entry['labUnits'],
                'enrolledCount'  => $enrolled,
                'submittedCount' => $entry['submittedCount'],
                'releasedCount'  => $entry['releasedCount'],
                'prelimDone'     => $entry['prelimDone'],
                'midtermDone'    => $entry['midtermDone'],
                'finalDone'      => $entry['finalDone'],
                'gradeCompletion'=> $enrolled > 0 ? round($done / ($enrolled * 3) * 100) : 0,
                // Pass all variant IDs so the frontend can fetch students across variants.
                // Angular can send course_ids[]=1&course_ids[]=2 or use the primary id.
                'courseIds'      => $entry['_courseIds'],
            ];
        }
    }
    ob_end_clean();
    echo json_encode(['success'=>true,'courses'=>$courses,'faculty'=>[
        'id'         => $facId,
        'name'       => $facRow['first_name'].' '.$facRow['last_name'],
        'department' => $facRow['department'] ?? '',
        'specialty'  => $facRow['specialty']  ?? '',
    ]]);
    break;

// ════════════════════════════════════════════════════════════════
// GET COURSE STUDENTS — enrolled students + their grades
// GET ?action=get_course_students&course_id=X
// ════════════════════════════════════════════════════════════════
case 'get_course_students':
    // Accept either a single course_id OR multiple course_ids[] (for merged variants).
    // Angular sends course_ids[]=1&course_ids[]=2 when a course has suffix variants.
    $rawIds = [];
    if (!empty($_GET['course_ids']) && is_array($_GET['course_ids'])) {
        foreach ($_GET['course_ids'] as $v) { $rawIds[] = (int)$v; }
    } elseif (!empty($_GET['course_id'])) {
        $rawIds[] = (int)$_GET['course_id'];
    }
    $rawIds = array_filter($rawIds);  // remove zeros
    if (!$rawIds) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'course_id required']); exit(); }

    // Use first id as canonical for backward compatibility
    $cid = $rawIds[0];

    // Verify EVERY requested course belongs to this instructor (suffix-aware)
    $allIds = implode(',', $rawIds);
    $codeRows = $conn->query("SELECT id, code FROM courses WHERE id IN ($allIds)");
    $authorized = false;
    $verifiedIds = [];
    if ($codeRows) {
        while ($cRow = $codeRows->fetch_assoc()) {
            $cleanDb = cleanCode($cRow['code']);
            if (in_array($cleanDb, $subjectCodes, true) || in_array($cRow['code'], $subjectCodes, true)) {
                $verifiedIds[] = (int)$cRow['id'];
                $authorized    = true;
            }
        }
    }
    if (!$authorized || !$verifiedIds) {
        ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit();
    }

    $inClause = implode(',', $verifiedIds);
    $res = $conn->query("
        SELECT e.id AS enrollment_id, e.course_id, e.semester, e.status AS enrollment_status,
               COALESCE(e.grade_submitted,0) AS grade_submitted,
               COALESCE(e.grade_released,0)  AS grade_released,
               s.id AS student_id, s.student_number, s.first_name, s.last_name,
               s.year_level, s.program, s.student_category,
               MAX(CASE WHEN sg.term='Prelim'  THEN sg.grade END) AS prelim,
               MAX(CASE WHEN sg.term='Midterm' THEN sg.grade END) AS midterm,
               MAX(CASE WHEN sg.term='Final'   THEN sg.grade END) AS final
        FROM enrollments e
        JOIN students s ON s.id = e.student_id
        LEFT JOIN student_grades sg ON sg.enrollment_id = e.id
        WHERE e.course_id IN ($inClause) AND e.status IN ('Enrolled','Pending')
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
            'enrollmentId'    => (int)$r['enrollment_id'],
            'studentId'       => (int)$r['student_id'],
            'studentNumber'   => $r['student_number'],
            'firstName'       => $r['first_name'],
            'lastName'        => $r['last_name'],
            'yearLevel'       => $r['year_level'] ?? '',
            'program'         => $r['program'] ?? '',
            'studentCategory' => $r['student_category'] ?? '',
            'semester'        => $r['semester'] ?? '',
            'gradeSubmitted'  => (int)$r['grade_submitted'] === 1,
            'gradeReleased'   => (int)$r['grade_released']  === 1,
            'prelim'          => $prelim,
            'midterm'         => $midterm,
            'final'           => $final,
            'overall'         => $overall,
            'remarks'         => $remarks,
        ];
    }
    ob_end_clean();
    $students = applyPrivacyList($students, $authUser, 'student');
    echo json_encode(['success'=>true,'students'=>$students]);
    break;

// ════════════════════════════════════════════════════════════════
// SAVE GRADE — instructor saves one term grade for one student
// POST { enrollment_id, student_id, course_id, term, grade }
// ════════════════════════════════════════════════════════════════
case 'save_grade':
    $eid    = (int)($data['enrollment_id'] ?? 0);
    $sid    = (int)($data['student_id']    ?? 0);
    $cid    = (int)($data['course_id']     ?? 0);
    $term   = trim($data['term']  ?? '');
    $grade  = isset($data['grade']) ? (float)$data['grade'] : null;

    if (!$eid || !$sid || !$cid || !$term) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Missing fields']); exit(); }
    if ($grade !== null && ($grade < 1.0 || $grade > 5.0)) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Grade must be 1.00–5.00']); exit(); }

    $validTerms = ['Prelim','Midterm','Final'];
    if (!in_array($term, $validTerms)) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Invalid term']); exit(); }

    // Verify course belongs to instructor (suffix-aware)
    $codeRow = (($_r=$conn->query("SELECT code FROM courses WHERE id=$cid LIMIT 1")) ? $_r->fetch_assoc() : null);
    $cleanDb = $codeRow ? cleanCode($codeRow['code']) : '';
    if (!$codeRow || (!in_array($cleanDb, $subjectCodes) && !in_array($codeRow['code'], $subjectCodes))) {
        ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit();
    }

    // Check not already released
    $relRow = (($_r=$conn->query("SELECT COALESCE(grade_released,0) AS gr FROM enrollments WHERE id=$eid LIMIT 1")) ? $_r->fetch_assoc() : null);
    if ($relRow && (int)$relRow['gr'] === 1) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Grades already released — cannot edit']); exit(); }

    $semRow = (($_r=$conn->query("SELECT semester FROM enrollments WHERE id=$eid LIMIT 1")) ? $_r->fetch_assoc() : null);
    $sem    = $semRow['semester'] ?? '';

    // Upsert student_grades
    // FIX-1: submitted_by FK → users.id, so use $userId (not $facId which is faculty.id)
    // FIX-2: separate INSERT branch for null grade — avoids bind_param null-in-float-slot crash
    $existStmt = $conn->prepare("SELECT id FROM student_grades WHERE enrollment_id = ? AND term = ? LIMIT 1");
    $existStmt->bind_param('is', $eid, $term);
    $existStmt->execute();
    $existing = $existStmt->get_result()->fetch_assoc();
    $existStmt->close();

    if ($existing) {
        if ($grade !== null) {
            $stmt = $conn->prepare("UPDATE student_grades SET grade=?, submitted_by=? WHERE enrollment_id=? AND term=?");
            $stmt->bind_param("diis", $grade, $userId, $eid, $term);
        } else {
            $stmt = $conn->prepare("UPDATE student_grades SET grade=NULL, submitted_by=? WHERE enrollment_id=? AND term=?");
            $stmt->bind_param("iis", $userId, $eid, $term);
        }
    } else {
        if ($grade !== null) {
            $stmt = $conn->prepare("INSERT INTO student_grades (enrollment_id,student_id,course_id,semester,term,grade,submitted_by) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param("iiissdi", $eid, $sid, $cid, $sem, $term, $grade, $userId);
        } else {
            $stmt = $conn->prepare("INSERT INTO student_grades (enrollment_id,student_id,course_id,semester,term,grade,submitted_by) VALUES (?,?,?,?,?,NULL,?)");
            $stmt->bind_param("iiissi", $eid, $sid, $cid, $sem, $term, $userId);
        }
    }

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => IS_DEV ? 'DB error: ' . $err : 'Failed to save grade.']);
        exit();
    }
    $stmt->close();

    // Recalculate overall from student_grades (grades live there, not on enrollments)
    $gRes = $conn->query("SELECT term, grade FROM student_grades WHERE enrollment_id=$eid AND grade IS NOT NULL");
    $gradeMap = [];
    while ($gRow = $gRes->fetch_assoc()) { $gradeMap[$gRow['term']] = (float)$gRow['grade']; }
    $vals    = array_values($gradeMap);
    $overall = count($vals) > 0 ? round(array_sum($vals)/count($vals), 2) : null;
    $remarks = isset($gradeMap['Final']) ? ($overall <= 3.0 ? 'Passed' : 'Failed') : 'In Progress';
    $remStmt = $conn->prepare("UPDATE enrollments SET remarks = ? WHERE id = ?");
    $remStmt->bind_param('si', $remarks, $eid);
    $remStmt->execute();
    $remStmt->close();

    ob_end_clean();
    echo json_encode(['success'=>true,'message'=>'Grade saved','overall'=>$overall,'remarks'=>$remarks]);
    break;

// ════════════════════════════════════════════════════════════════
// SUBMIT TO REGISTRAR — mark all graded enrollments as submitted
// POST { course_id }
// ════════════════════════════════════════════════════════════════
case 'submit_to_registrar':
    $cid = (int)($data['course_id'] ?? 0);
    if (!$cid) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'course_id required']); exit(); }

    // Support multiple variant IDs sent from Angular (course_ids array)
    $rawCourseIds = [$cid];
    if (!empty($data['course_ids']) && is_array($data['course_ids'])) {
        foreach ($data['course_ids'] as $v) { $rawCourseIds[] = (int)$v; }
        $rawCourseIds = array_unique(array_filter($rawCourseIds));
    }

    // Verify ALL ids belong to this instructor (suffix-aware)
    $verifiedSubmitIds = [];
    foreach ($rawCourseIds as $checkId) {
        $codeRow = (($_r=$conn->query("SELECT code FROM courses WHERE id=$checkId LIMIT 1")) ? $_r->fetch_assoc() : null);
        $cleanDb = $codeRow ? cleanCode($codeRow['code']) : '';
        if ($codeRow && (in_array($cleanDb, $subjectCodes) || in_array($codeRow['code'], $subjectCodes))) {
            $verifiedSubmitIds[] = $checkId;
        }
    }
    if (!$verifiedSubmitIds) {
        ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit();
    }

    $inSubmit = implode(',', $verifiedSubmitIds);
    $now = date('Y-m-d H:i:s');
    $conn->query("
        UPDATE enrollments e
        SET e.grade_submitted=1, e.grade_submitted_at='$now'
        WHERE e.course_id IN ($inSubmit)
          AND e.status IN ('Enrolled','Pending')
          AND e.grade_released=0
          AND EXISTS (SELECT 1 FROM student_grades sg WHERE sg.enrollment_id = e.id AND sg.grade IS NOT NULL)
    ");
    $affected = $conn->affected_rows;
    ob_end_clean();
    echo json_encode(['success'=>true,'submitted'=>$affected,'message'=>"$affected grade(s) submitted to Registrar"]);
    break;

default:
    ob_end_clean();
    echo json_encode(['success'=>false,'message'=>"Unknown action: $action"]);
    break;
}

$conn->close();