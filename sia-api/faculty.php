<?php

// ── cleanCode() ──────────────────────────────────────────────────────────────
// Strips internal disambiguation suffixes from course codes.
// These suffixes are used in the DB to keep codes unique across programs
// that share the same subject (e.g. GE103 shared by BSA and BSCA gets
// stored as GE103-BMD and GE103-CA so they can have separate records).
// Legitimate curriculum codes with dashes (RE-FUN013, GE-ENG013,
// BN-MGT013, IT-CSA013, AC-TAX013, etc.) are NOT affected.
if (!function_exists('cleanCode')) {
    function cleanCode($code) {
        if (!$code) return $code;
        static $suffixes = ['-BMD','-CA','-BSA','-BSCA','-BSE','-CIMT','-BSIT','-BSREM'];
        $upper = strtoupper($code);
        foreach ($suffixes as $s) {
            if (substr($upper, -strlen($s)) === $s) {
                return substr($code, 0, strlen($code) - strlen($s));
            }
        }
        return $code;
    }
}
/**
 * faculty.php — Instructor Portal API
 * Handles all instructor-facing requests:
 *   - get_my_courses
 *   - get_course_students
 *   - save_grade
 *   - submit_grades_to_registrar
 *   - get_profile
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

ob_start();

$conn = new mysqli('localhost', 'root', '', 'sia_db');
if ($conn->connect_error) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'DB error']); exit(); }
$conn->set_charset('utf8mb4');

require_once __DIR__ . '/auth_middleware.php';

$action = trim($_GET['action'] ?? $_POST['action'] ?? '');
$data   = json_decode(file_get_contents('php://input'), true) ?? [];

// ── Require faculty auth ──
$authUser = requireAuth($conn, 'faculty');
$userId   = (int)$authUser['user_id'];

// ── Resolve faculty.id from user_id ──
$facRow = $conn->query("SELECT f.id, f.faculty_id, f.first_name, f.last_name, f.department, f.specialty, f.subjects, IFNULL(f.program_levels,'[]') AS program_levels FROM faculty f JOIN users u ON u.id = $userId WHERE u.role='faculty' LIMIT 1")->fetch_assoc();

// If not found by user linkage, try by email
if (!$facRow) {
    $emailRow = $conn->query("SELECT email FROM users WHERE id=$userId LIMIT 1")->fetch_assoc();
    if ($emailRow) {
        $em = $conn->real_escape_string($emailRow['email']);
        $facRow = $conn->query("SELECT f.id, f.faculty_id, f.first_name, f.last_name, f.department, f.specialty, f.subjects, IFNULL(f.program_levels,'[]') AS program_levels FROM faculty f WHERE f.email='$em' LIMIT 1")->fetch_assoc();
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
    $conn->query("ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS grade_submitted TINYINT(1) DEFAULT 0");
    $conn->query("ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS grade_released  TINYINT(1) DEFAULT 0");

    $courses = [];
    if (count($subjectCodes) > 0) {
        $placeholders = implode(',', array_fill(0, count($subjectCodes), '?'));
        $stmt = $conn->prepare("
            SELECT c.id, c.code, c.name, c.credits, c.semester,
                   c.department, c.program, c.year_level, c.is_lab,
                   COUNT(DISTINCT e.id)    AS enrolled_count,
                   SUM(CASE WHEN e.grade_submitted=1 THEN 1 ELSE 0 END)  AS submitted_count,
                   SUM(CASE WHEN e.grade_released=1  THEN 1 ELSE 0 END)  AS released_count,
                   SUM(CASE WHEN e.prelim_grade  IS NOT NULL THEN 1 ELSE 0 END) AS prelim_done,
                   SUM(CASE WHEN e.midterm_grade IS NOT NULL THEN 1 ELSE 0 END) AS midterm_done,
                   SUM(CASE WHEN e.final_grade   IS NOT NULL THEN 1 ELSE 0 END) AS final_done
            FROM courses c
            LEFT JOIN enrollments e ON e.course_id = c.id AND e.status IN ('Enrolled','Pending')
            WHERE c.code IN ($placeholders)
            GROUP BY c.id
            ORDER BY c.code ASC
        ");
        $types = str_repeat('s', count($subjectCodes));
        $stmt->bind_param($types, ...$subjectCodes);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $enrolled = (int)$r['enrolled_count'];
            $done     = (int)$r['prelim_done'] + (int)$r['midterm_done'] + (int)$r['final_done'];
            $courses[] = [
                'id'            => (int)$r['id'],
                'code'           => cleanCode($r['code']),
                'name'          => $r['name'],
                'credits'       => (int)$r['credits'],
                'semester'      => $r['semester'] ?? '',
                'department'    => $r['department'] ?? '',
                'program'       => $r['program'] ?? '',
                'yearLevel'     => $r['year_level'] ?? '',
                'isLab'         => (bool)$r['is_lab'],
                'enrolledCount' => $enrolled,
                'submittedCount'=> (int)$r['submitted_count'],
                'releasedCount' => (int)$r['released_count'],
                'prelimDone'    => (int)$r['prelim_done'],
                'midtermDone'   => (int)$r['midterm_done'],
                'finalDone'     => (int)$r['final_done'],
                'gradeCompletion'=> $enrolled > 0 ? round($done / ($enrolled * 3) * 100) : 0,
            ];
        }
        $stmt->close();
        // Sync faculty_id on courses
        $full = $conn->real_escape_string($facRow['first_name'].' '.$facRow['last_name']);
        foreach ($subjectCodes as $code) {
            $esc = $conn->real_escape_string(trim($code));
            $conn->query("UPDATE courses SET faculty_id=$facId, instructor='$full' WHERE code='$esc' AND (faculty_id IS NULL OR faculty_id=$facId)");
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
    $cid = (int)($_GET['course_id'] ?? 0);
    if (!$cid) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'course_id required']); exit(); }

    // Verify this course belongs to this instructor
    if (count($subjectCodes) > 0) {
        $codeRow = $conn->query("SELECT code FROM courses WHERE id=$cid LIMIT 1")->fetch_assoc();
        if (!$codeRow || !in_array($codeRow['code'], $subjectCodes)) {
            ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit();
        }
    }

    $conn->query("ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS grade_released  TINYINT(1) DEFAULT 0");
    $conn->query("ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS grade_submitted  TINYINT(1) DEFAULT 0");

    $res = $conn->query("
        SELECT e.id AS enrollment_id, e.semester, e.status AS enrollment_status,
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

    // Verify course belongs to instructor
    $codeRow = $conn->query("SELECT code FROM courses WHERE id=$cid LIMIT 1")->fetch_assoc();
    if (!$codeRow || !in_array($codeRow['code'], $subjectCodes)) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit(); }

    // Check not already released
    $relRow = $conn->query("SELECT COALESCE(grade_released,0) AS gr FROM enrollments WHERE id=$eid LIMIT 1")->fetch_assoc();
    if ($relRow && (int)$relRow['gr'] === 1) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Grades already released — cannot edit']); exit(); }

    $semRow = $conn->query("SELECT semester FROM enrollments WHERE id=$eid LIMIT 1")->fetch_assoc();
    $sem    = $semRow['semester'] ?? '';

    // Upsert student_grades
    $existing = $conn->query("SELECT id FROM student_grades WHERE enrollment_id=$eid AND term='$term' LIMIT 1")->fetch_assoc();
    if ($existing) {
        if ($grade !== null) {
            $stmt = $conn->prepare("UPDATE student_grades SET grade=?, submitted_by=? WHERE enrollment_id=? AND term=?");
            $stmt->bind_param("diis", $grade, $facId, $eid, $term);
        } else {
            $stmt = $conn->prepare("UPDATE student_grades SET grade=NULL, submitted_by=? WHERE enrollment_id=? AND term=?");
            $stmt->bind_param("iis", $facId, $eid, $term);
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO student_grades (enrollment_id,student_id,course_id,semester,term,grade,submitted_by) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("iiissdi", $eid, $sid, $cid, $sem, $term, $grade, $facId);
    }
    $stmt->execute();
    $stmt->close();

    // Update enrollments term grade columns
    $termCol = ['Prelim'=>'prelim_grade','Midterm'=>'midterm_grade','Final'=>'final_grade'][$term];
    if ($grade !== null) $conn->query("UPDATE enrollments SET $termCol=$grade WHERE id=$eid");
    else                 $conn->query("UPDATE enrollments SET $termCol=NULL WHERE id=$eid");

    // Recalculate overall
    $gRow = $conn->query("SELECT prelim_grade, midterm_grade, final_grade FROM enrollments WHERE id=$eid LIMIT 1")->fetch_assoc();
    $vals = array_filter([$gRow['prelim_grade'],$gRow['midterm_grade'],$gRow['final_grade']], fn($v) => $v !== null);
    if (count($vals) > 0) {
        $overall  = round(array_sum($vals)/count($vals), 2);
        $remarks  = $gRow['final_grade'] !== null ? ($overall <= 3.0 ? 'Passed' : 'Failed') : 'In Progress';
        $conn->query("UPDATE enrollments SET overall_grade=$overall, remarks='$remarks' WHERE id=$eid");
    }

    ob_end_clean();
    echo json_encode(['success'=>true,'message'=>'Grade saved']);
    break;

// ════════════════════════════════════════════════════════════════
// SUBMIT TO REGISTRAR — mark all graded enrollments as submitted
// POST { course_id }
// ════════════════════════════════════════════════════════════════
case 'submit_to_registrar':
    $cid = (int)($data['course_id'] ?? 0);
    if (!$cid) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'course_id required']); exit(); }

    $codeRow = $conn->query("SELECT code FROM courses WHERE id=$cid LIMIT 1")->fetch_assoc();
    if (!$codeRow || !in_array($codeRow['code'], $subjectCodes)) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit(); }

    $conn->query("ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS grade_submitted     TINYINT(1) DEFAULT 0");
    $conn->query("ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS grade_submitted_at  DATETIME DEFAULT NULL");

    $now = date('Y-m-d H:i:s');
    $conn->query("
        UPDATE enrollments
        SET grade_submitted=1, grade_submitted_at='$now'
        WHERE course_id=$cid
          AND status IN ('Enrolled','Pending')
          AND (prelim_grade IS NOT NULL OR midterm_grade IS NOT NULL OR final_grade IS NOT NULL)
          AND grade_released=0
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