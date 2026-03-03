<?php
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
            'code'         => $r['code'],
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
            'code'    => $r['code'],   'name'       => $r['name'],
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