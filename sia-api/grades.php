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
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit(); }

$conn = new mysqli('localhost', 'root', '', 'sia_db');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->connect_error]);
    exit();
}
$conn->set_charset('utf8mb4');

// ── Ensure student_grades table ───────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS student_grades (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id INT NOT NULL,
    student_id    INT NOT NULL,
    course_id     INT NOT NULL,
    semester      VARCHAR(100) DEFAULT '',
    term          ENUM('Prelim','Midterm','Final') NOT NULL,
    grade         DECIMAL(4,2) DEFAULT NULL,
    submitted_by  INT DEFAULT NULL,
    submitted_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_grade (enrollment_id, term),
    INDEX idx_student (student_id),
    INDEX idx_course (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$action = trim($_GET['action'] ?? $_POST['action'] ?? '');
$data   = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {

// ════════════════════════════════════════════════════════════════
// REGISTRAR: Search students
// GET ?action=search_students&q=keyword
// ════════════════════════════════════════════════════════════════
case 'search_students':
    $q = '%' . $conn->real_escape_string(trim($_GET['q'] ?? '')) . '%';
    $res = $conn->query("
        SELECT s.id, s.student_number, s.first_name, s.last_name,
               s.program, s.year_level, s.semester, s.student_type,
               s.enrollment_status
        FROM students s
        WHERE s.enrollment_status IN ('Enrolled','Pending')
          AND (s.student_number LIKE '$q'
            OR s.first_name LIKE '$q'
            OR s.last_name LIKE '$q'
            OR CONCAT(s.first_name,' ',s.last_name) LIKE '$q'
            OR CONCAT(s.last_name,' ',s.first_name) LIKE '$q')
        ORDER BY s.last_name, s.first_name
        LIMIT 20
    ");
    $students = [];
    while ($r = $res->fetch_assoc()) $students[] = $r;
    echo json_encode(['success' => true, 'students' => $students]);
    break;

// ════════════════════════════════════════════════════════════════
// REGISTRAR: Get all enrolled subjects of a student with grades
// GET ?action=get_student_subjects&student_id=X
// ════════════════════════════════════════════════════════════════
case 'get_student_subjects':
    $sid = (int)($_GET['student_id'] ?? 0);
    if (!$sid) { echo json_encode(['success'=>false,'message'=>'student_id required']); break; }

    $res = $conn->query("
        SELECT e.id AS enrollment_id, e.semester, e.status,
               c.id AS course_id, c.code, c.name, c.credits, c.instructor,
               MAX(CASE WHEN sg.term='Prelim'  THEN sg.grade END) AS prelim,
               MAX(CASE WHEN sg.term='Midterm' THEN sg.grade END) AS midterm,
               MAX(CASE WHEN sg.term='Final'   THEN sg.grade END) AS final,
               MAX(CASE WHEN sg.term='Prelim'  THEN sg.updated_at END) AS prelim_at,
               MAX(CASE WHEN sg.term='Midterm' THEN sg.updated_at END) AS midterm_at,
               MAX(CASE WHEN sg.term='Final'   THEN sg.updated_at END) AS final_at
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN student_grades sg ON sg.enrollment_id = e.id
        WHERE e.student_id = $sid
          AND e.status IN ('Enrolled','Pending')
        GROUP BY e.id, c.id
        ORDER BY c.code ASC
    ");
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
            'code'           => cleanCode($r['code']),
            'name'         => $r['name'],
            'credits'      => (int)$r['credits'],
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
    echo json_encode(['success' => true, 'subjects' => $subjects]);
    break;

// ════════════════════════════════════════════════════════════════
// REGISTRAR: Save a single grade entry
// POST { enrollment_id, student_id, course_id, term, grade }
// ════════════════════════════════════════════════════════════════
case 'save_grade':
    $eid      = (int)($data['enrollment_id'] ?? 0);
    $sid      = (int)($data['student_id']    ?? 0);
    $cid      = (int)($data['course_id']     ?? 0);
    $term     = trim($data['term']           ?? '');
    $gradeVal = ($data['grade'] !== null && $data['grade'] !== '') ? (float)$data['grade'] : null;
    $submittedBy = (int)($data['submitted_by'] ?? 0);

    if (!$eid || !$sid || !$cid || !in_array($term, ['Prelim','Midterm','Final'])) {
        echo json_encode(['success'=>false,'message'=>'Missing required fields']); break;
    }
    if ($gradeVal !== null && ($gradeVal < 1.0 || $gradeVal > 5.0)) {
        echo json_encode(['success'=>false,'message'=>'Grade must be 1.00–5.00']); break;
    }

    // Get semester from enrollment
    $semRow = $conn->query("SELECT semester FROM enrollments WHERE id=$eid LIMIT 1")->fetch_assoc();
    $semester = $conn->real_escape_string($semRow['semester'] ?? '');

    if ($gradeVal !== null) {
        $stmt = $conn->prepare("
            INSERT INTO student_grades (enrollment_id, student_id, course_id, semester, term, grade, submitted_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE grade=VALUES(grade), submitted_by=VALUES(submitted_by), updated_at=NOW()
        ");
        $stmt->bind_param("iiissdi", $eid, $sid, $cid, $semester, $term, $gradeVal, $submittedBy);
    } else {
        // Save NULL grade (clear it)
        $stmt = $conn->prepare("
            INSERT INTO student_grades (enrollment_id, student_id, course_id, semester, term, grade, submitted_by)
            VALUES (?, ?, ?, ?, ?, NULL, ?)
            ON DUPLICATE KEY UPDATE grade=NULL, submitted_by=VALUES(submitted_by), updated_at=NOW()
        ");
        $stmt->bind_param("iiissi", $eid, $sid, $cid, $semester, $term, $submittedBy);
    }

    if ($stmt->execute()) {
        _updateOverallGrade($conn, $eid);
        echo json_encode(['success'=>true,'message'=>"$term grade saved"]);
    } else {
        echo json_encode(['success'=>false,'message'=>$conn->error]);
    }
    $stmt->close();
    break;

// ════════════════════════════════════════════════════════════════
// STUDENT: Get semesters
// ════════════════════════════════════════════════════════════════
case 'get_semesters':
    $sid = _resolveStudent($conn);
    if (!$sid) { echo json_encode(['success'=>false,'message'=>'Student not found']); break; }
    $res = $conn->query("
        SELECT DISTINCT semester FROM enrollments
        WHERE student_id=$sid AND status IN ('Enrolled','Pending','Completed')
        ORDER BY semester DESC
    ");
    $semesters = [];
    while ($r = $res->fetch_assoc()) if ($r['semester']) $semesters[] = $r['semester'];
    if (empty($semesters)) {
        $stRow = $conn->query("SELECT semester FROM students WHERE id=$sid LIMIT 1")->fetch_assoc();
        if ($stRow && $stRow['semester']) $semesters[] = $stRow['semester'];
    }
    echo json_encode(['success'=>true,'semesters'=>$semesters]);
    break;

// ════════════════════════════════════════════════════════════════
// STUDENT: Get grades for a semester
// ════════════════════════════════════════════════════════════════
case 'get_grades':
    $sid = _resolveStudent($conn);
    if (!$sid) { echo json_encode(['success'=>false,'message'=>'Student not found']); break; }

    $sem = trim($_GET['semester'] ?? '');
    $semFilter = $sem ? "AND e.semester='".$conn->real_escape_string($sem)."'" : '';

    $res = $conn->query("
        SELECT e.id AS enrollment_id, e.semester, e.status,
               c.code, c.name, c.credits, c.instructor,
               MAX(CASE WHEN sg.term='Prelim'  THEN sg.grade END) AS prelim,
               MAX(CASE WHEN sg.term='Midterm' THEN sg.grade END) AS midterm,
               MAX(CASE WHEN sg.term='Final'   THEN sg.grade END) AS final
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN student_grades sg ON sg.enrollment_id = e.id
        WHERE e.student_id=$sid AND e.status IN ('Enrolled','Pending','Completed') $semFilter
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
            'code'           => cleanCode($r['code']),   'name'       => $r['name'],
            'credits' => $credits,     'instructor' => $r['instructor'] ?? '',
            'semester'=> $r['semester'] ?? '', 'status' => $r['status'],
            'prelim'  => $prelim,  'midterm' => $midterm,
            'final'   => $final,   'overall' => $overall,
            'remarks' => $remarks, 'description' => '',
        ];
    }
    $gwa = $totalC > 0 ? round($totalW/$totalC, 2) : null;
    echo json_encode(['success'=>true,'grades'=>$grades,'gwa'=>$gwa,'totalCredits'=>$totalC]);
    break;

// ════════════════════════════════════════════════════════════════
// STUDENT: Overall academic summary
// ════════════════════════════════════════════════════════════════
case 'get_grade_summary':
    $sid = _resolveStudent($conn);
    if (!$sid) { echo json_encode(['success'=>false,'message'=>'Student not found']); break; }

    $res = $conn->query("
        SELECT e.semester, c.credits,
               MAX(CASE WHEN sg.term='Final' THEN sg.grade END) AS final_grade
        FROM enrollments e
        JOIN courses c ON e.course_id=c.id
        LEFT JOIN student_grades sg ON sg.enrollment_id=e.id
        WHERE e.student_id=$sid AND e.status IN ('Enrolled','Pending','Completed')
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
    echo json_encode(['success'=>true,'overallGWA'=>$overallGWA,'academicStatus'=>$status,'semesterGWA'=>$semGWA]);
    break;

// ════════════════════════════════════════════════════════════════
// ADMIN: Get all faculty with their assigned subjects
// GET ?action=admin_get_faculty
// ════════════════════════════════════════════════════════════════
case 'admin_get_faculty':
    $res = $conn->query("
        SELECT f.id, f.faculty_id, f.first_name, f.last_name,
               f.department, f.specialty, f.subjects, f.status
        FROM faculty f
        WHERE f.status = 'Active'
        ORDER BY f.last_name, f.first_name
    ");
    $faculty = [];
    while ($r = $res->fetch_assoc()) {
        $subjectsArr = json_decode($r['subjects'] ?? '[]', true) ?: [];
        $r['subjects_arr'] = $subjectsArr;
        // Count courses that match the faculty's subject codes OR have faculty_id set
        $courseCount = 0;
        if (count($subjectsArr) > 0) {
            $placeholders = implode(',', array_fill(0, count($subjectsArr), '?'));
            $cntStmt = $conn->prepare(
                "SELECT COUNT(*) AS cnt FROM courses WHERE code IN ($placeholders)"
            );
            $types = str_repeat('s', count($subjectsArr));
            $cntStmt->bind_param($types, ...$subjectsArr);
            $cntStmt->execute();
            $courseCount = (int)$cntStmt->get_result()->fetch_assoc()['cnt'];
            $cntStmt->close();
        }
        $r['course_count'] = $courseCount;
        $faculty[] = $r;
    }
    echo json_encode(['success' => true, 'faculty' => $faculty]);
    break;

// ════════════════════════════════════════════════════════════════
// ADMIN: Get subjects (courses) assigned to a specific faculty
// GET ?action=admin_get_faculty_subjects&faculty_id=X
// ════════════════════════════════════════════════════════════════
case 'admin_get_faculty_subjects':
    $fid = (int)($_GET['faculty_id'] ?? 0);
    if (!$fid) { echo json_encode(['success'=>false,'message'=>'faculty_id required']); break; }

    // Get the faculty's subject codes from JSON array
    $fRow = $conn->query("SELECT subjects FROM faculty WHERE id=$fid LIMIT 1")->fetch_assoc();
    if (!$fRow) { echo json_encode(['success'=>false,'message'=>'Faculty not found']); break; }

    $subjectCodes = json_decode($fRow['subjects'] ?? '[]', true) ?: [];

    // Also include courses where faculty_id is set to this faculty
    $subjects = [];

    if (count($subjectCodes) > 0) {
        $placeholders = implode(',', array_fill(0, count($subjectCodes), '?'));
        $stmt = $conn->prepare("
            SELECT c.id AS course_id, c.code, c.name, c.credits,
                   c.semester, c.department, c.program, c.year_level,
                   COUNT(DISTINCT e.id) AS enrolled_count,
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
            $subjects[] = [
                'courseId'        => (int)$r['course_id'],
                'code'           => cleanCode($r['code']),
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
        $stmt->close();
    }

    // Also auto-update courses.faculty_id so they're properly linked going forward
    if (count($subjectCodes) > 0) {
        $placeholders = implode(',', array_fill(0, count($subjectCodes), '?'));
        $updStmt = $conn->prepare("UPDATE courses SET faculty_id=? WHERE code IN ($placeholders) AND (faculty_id IS NULL OR faculty_id=$fid)");
        $types = 'i' . str_repeat('s', count($subjectCodes));
        $updStmt->bind_param($types, $fid, ...$subjectCodes);
        $updStmt->execute();
        $updStmt->close();
    }

    echo json_encode(['success' => true, 'subjects' => $subjects]);
    break;

// ════════════════════════════════════════════════════════════════
// ADMIN: Get students enrolled in a specific course + their grades
// GET ?action=admin_get_course_students&course_id=X
// ════════════════════════════════════════════════════════════════
case 'admin_get_course_students':
    $cid = (int)($_GET['course_id'] ?? 0);
    if (!$cid) { echo json_encode(['success'=>false,'message'=>'course_id required']); break; }

    // Ensure columns exist
    $conn->query("ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS grade_released   TINYINT(1) DEFAULT 0");
    $conn->query("ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS grade_submitted   TINYINT(1) DEFAULT 0");

    $res = $conn->query("
        SELECT e.id AS enrollment_id, e.semester, e.status AS enrollment_status,
               s.id AS student_id, s.student_number, s.first_name, s.last_name,
               s.year_level, s.program,
               MAX(CASE WHEN sg.term='Prelim'  THEN sg.grade END) AS prelim,
               MAX(CASE WHEN sg.term='Midterm' THEN sg.grade END) AS midterm,
               MAX(CASE WHEN sg.term='Final'   THEN sg.grade END) AS final,
               COALESCE(e.grade_released, 0) AS grade_released
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
    echo json_encode(['success' => true, 'students' => $students]);
    break;

// ════════════════════════════════════════════════════════════════
// ADMIN: Save grade for ONE student in ONE subject (1-to-1 input)
// POST { enrollment_id, student_id, course_id, term, grade, submitted_by }
// Same endpoint as existing save_grade — already handled above
// Extra admin alias for clarity:
// ════════════════════════════════════════════════════════════════
case 'admin_save_grade':
    $eid      = (int)($data['enrollment_id'] ?? 0);
    $sid      = (int)($data['student_id']    ?? 0);
    $cid      = (int)($data['course_id']     ?? 0);
    $term     = trim($data['term']           ?? '');
    $gradeVal = ($data['grade'] !== '' && $data['grade'] !== null) ? (float)$data['grade'] : null;
    $submittedBy = (int)($data['submitted_by'] ?? 0);

    if (!$eid || !$sid || !$cid || !in_array($term, ['Prelim','Midterm','Final'])) {
        echo json_encode(['success'=>false,'message'=>'Missing required fields']); break;
    }
    if ($gradeVal !== null && ($gradeVal < 1.0 || $gradeVal > 5.0)) {
        echo json_encode(['success'=>false,'message'=>'Grade must be between 1.00 and 5.00']); break;
    }

    $semRow   = $conn->query("SELECT semester FROM enrollments WHERE id=$eid LIMIT 1")->fetch_assoc();
    $semester = $conn->real_escape_string($semRow['semester'] ?? '');

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
        _updateOverallGrade($conn, $eid);
        echo json_encode(['success' => true, 'message' => "$term grade saved successfully"]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    $stmt->close();
    break;

// ════════════════════════════════════════════════════════════════
// ADMIN → REGISTRAR: Submit/send grades for a course to registrar
// Marks all graded enrollments in a course as grade_submitted
// POST { course_id, submitted_by }
// ════════════════════════════════════════════════════════════════
case 'admin_submit_to_registrar':
    // Ensure column exists
    $conn->query("ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS grade_submitted TINYINT(1) DEFAULT 0");
    $conn->query("ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS grade_submitted_at DATETIME DEFAULT NULL");
    $conn->query("ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS grade_released   TINYINT(1) DEFAULT 0");
    $conn->query("ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS grade_released_at DATETIME DEFAULT NULL");

    $cid         = (int)($data['course_id']     ?? 0);
    $submittedBy = (int)($data['submitted_by']  ?? 0);
    if (!$cid) { echo json_encode(['success'=>false,'message'=>'course_id required']); break; }

    // Only submit enrollments that have at least one grade entered
    $upd = $conn->prepare("
        UPDATE enrollments e
        SET grade_submitted = 1, grade_submitted_at = NOW()
        WHERE e.course_id = ?
          AND e.status IN ('Enrolled','Pending')
          AND e.grade_submitted = 0
          AND (e.prelim_grade IS NOT NULL OR e.midterm_grade IS NOT NULL OR e.final_grade IS NOT NULL)
    ");
    $upd->bind_param("i", $cid);
    $upd->execute();
    $affected = $upd->affected_rows;
    $upd->close();

    echo json_encode([
        'success'  => true,
        'message'  => "$affected student grade(s) submitted to registrar",
        'affected' => $affected,
    ]);
    break;

// ════════════════════════════════════════════════════════════════
// REGISTRAR: Get courses with pending grade submissions
// GET ?action=registrar_pending_grades
// ════════════════════════════════════════════════════════════════
case 'registrar_pending_grades':
    $conn->query("ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS grade_submitted TINYINT(1) DEFAULT 0");
    $conn->query("ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS grade_released   TINYINT(1) DEFAULT 0");

    $res = $conn->query("
        SELECT c.id AS course_id, c.code, c.name,
               CONCAT(f.first_name,' ',f.last_name) AS faculty_name,
               c.semester, c.department,
               COUNT(e.id) AS submitted_count,
               SUM(CASE WHEN e.grade_released=1 THEN 1 ELSE 0 END) AS released_count
        FROM enrollments e
        JOIN courses c ON c.id = e.course_id
        LEFT JOIN faculty f ON f.id = c.faculty_id
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
    echo json_encode(['success' => true, 'courses' => $courses]);
    break;

// ════════════════════════════════════════════════════════════════
// REGISTRAR → STUDENT: Release grades for a course
// Marks submitted grades as released so students can see them
// POST { course_id } or { enrollment_id } for single student
// ════════════════════════════════════════════════════════════════
case 'registrar_release_grades':
    $conn->query("ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS grade_released   TINYINT(1) DEFAULT 0");
    $conn->query("ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS grade_released_at DATETIME DEFAULT NULL");

    $cid = (int)($data['course_id']    ?? 0);
    $eid = (int)($data['enrollment_id'] ?? 0);

    if (!$cid && !$eid) {
        echo json_encode(['success'=>false,'message'=>'course_id or enrollment_id required']); break;
    }

    if ($eid) {
        // Release single student grade
        $upd = $conn->prepare("
            UPDATE enrollments
            SET grade_released = 1, grade_released_at = NOW()
            WHERE id = ? AND grade_submitted = 1
        ");
        $upd->bind_param("i", $eid);
    } else {
        // Release all submitted grades for a course
        $upd = $conn->prepare("
            UPDATE enrollments
            SET grade_released = 1, grade_released_at = NOW()
            WHERE course_id = ? AND grade_submitted = 1 AND grade_released = 0
        ");
        $upd->bind_param("i", $cid);
    }

    $upd->execute();
    $affected = $upd->affected_rows;
    $upd->close();

    echo json_encode([
        'success'  => true,
        'message'  => "$affected grade(s) released to students",
        'affected' => $affected,
    ]);
    break;

// ════════════════════════════════════════════════════════════════
// STUDENT: Get released grades only (visible after registrar releases)
// GET ?action=get_released_grades&student_id=X (or user_id=X)
// ════════════════════════════════════════════════════════════════
case 'get_released_grades':
    $conn->query("ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS grade_released TINYINT(1) DEFAULT 0");

    $sid = _resolveStudent($conn);
    if (!$sid) { echo json_encode(['success'=>false,'message'=>'Student not found']); break; }

    $sem = trim($_GET['semester'] ?? '');
    $semFilter = $sem ? "AND e.semester='".$conn->real_escape_string($sem)."'" : '';

    $res = $conn->query("
        SELECT e.id AS enrollment_id, e.semester, e.grade_released,
               c.code, c.name, c.credits, c.instructor,
               MAX(CASE WHEN sg.term='Prelim'  THEN sg.grade END) AS prelim,
               MAX(CASE WHEN sg.term='Midterm' THEN sg.grade END) AS midterm,
               MAX(CASE WHEN sg.term='Final'   THEN sg.grade END) AS final
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN student_grades sg ON sg.enrollment_id = e.id
        WHERE e.student_id = $sid
          AND e.status IN ('Enrolled','Pending','Completed')
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
            'code'           => cleanCode($r['code']),
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
    echo json_encode(['success'=>true,'grades'=>$grades,'gwa'=>$gwa,'totalCredits'=>$totalC]);
    break;

default:
    echo json_encode(['success'=>false,'message'=>'Unknown action: '.$action]);
}

function _resolveStudent($conn) {
    $sid = (int)($_GET['student_id'] ?? 0);
    if (!$sid) {
        $uid = (int)($_GET['user_id'] ?? 0);
        if ($uid) {
            $r = $conn->query("SELECT id FROM students WHERE user_id=$uid LIMIT 1")->fetch_assoc();
            $sid = $r ? (int)$r['id'] : 0;
        }
    }
    return $sid;
}

function _updateOverallGrade($conn, $eid) {
    // Sync individual term grades from student_grades into enrollments columns
    $res = $conn->query("
        SELECT
            MAX(CASE WHEN term='Prelim'  THEN grade END) AS prelim,
            MAX(CASE WHEN term='Midterm' THEN grade END) AS midterm,
            MAX(CASE WHEN term='Final'   THEN grade END) AS final,
            AVG(grade) AS avg
        FROM student_grades
        WHERE enrollment_id=$eid AND grade IS NOT NULL
    ");
    $row = $res ? $res->fetch_assoc() : null;
    if ($row) {
        $prelim  = $row['prelim']  !== null ? (float)$row['prelim']  : null;
        $midterm = $row['midterm'] !== null ? (float)$row['midterm'] : null;
        $final   = $row['final']   !== null ? (float)$row['final']   : null;
        $avg     = $row['avg']     !== null ? round((float)$row['avg'], 2) : null;
        $remarks = $final !== null ? ($avg !== null && $avg <= 3.0 ? 'Passed' : 'Failed') : 'In Progress';
        $pVal  = $prelim  !== null ? $prelim  : 'NULL';
        $mVal  = $midterm !== null ? $midterm : 'NULL';
        $fVal  = $final   !== null ? $final   : 'NULL';
        $oVal  = $avg     !== null ? $avg     : 'NULL';
        $rem   = $conn->real_escape_string($remarks);
        $conn->query("UPDATE enrollments SET
            prelim_grade=$pVal, midterm_grade=$mVal, final_grade=$fVal,
            overall_grade=$oVal, grade=$oVal, remarks='$rem'
            WHERE id=$eid");
    }
}