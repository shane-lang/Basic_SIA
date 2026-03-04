<?php
error_reporting(0);
ini_set('display_errors', 0);
mysqli_report(MYSQLI_REPORT_OFF);

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

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

$conn = new mysqli('localhost', 'root', '', 'sia_db');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed: ' . $conn->connect_error]);
    exit();
}
$conn->set_charset("utf8mb4");

$action = $_GET['action'] ?? '';

require_once __DIR__ . '/auth_middleware.php';
$publicActions = ['get_programs'];
$authUser = in_array($action, $publicActions) ? null : requireAuth($conn, 'admin');

// ================================================================
//  AUTO-CREATE AUDIT LOG TABLE
// ================================================================
$conn->query("CREATE TABLE IF NOT EXISTS audit_logs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT DEFAULT NULL,
    user_email   VARCHAR(150) DEFAULT NULL,
    user_role    VARCHAR(30)  DEFAULT NULL,
    action       VARCHAR(100) NOT NULL,
    target_type  VARCHAR(50)  DEFAULT NULL,
    target_id    INT          DEFAULT NULL,
    description  TEXT         DEFAULT NULL,
    old_values   JSON         DEFAULT NULL,
    new_values   JSON         DEFAULT NULL,
    ip_address   VARCHAR(45)  DEFAULT NULL,
    user_agent   VARCHAR(255) DEFAULT NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user    (user_id),
    INDEX idx_action  (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS seed_flags (name VARCHAR(100) PRIMARY KEY)");
$_sfRes = $conn->query("SELECT 1 FROM seed_flags WHERE name='program_courses' LIMIT 1");
if (!$_sfRes || $_sfRes->num_rows === 0) {
    $pcSeedCount = (int)($conn->query("SELECT COUNT(*) AS c FROM program_courses")->fetch_assoc()['c'] ?? 0);
    if ($pcSeedCount === 0) {
        $progSeedCount = (int)($conn->query("SELECT COUNT(*) AS c FROM programs")->fetch_assoc()['c'] ?? 0);
        if ($progSeedCount === 0) {
            $conn->query("INSERT IGNORE INTO programs (name, code, level_type, department)
                SELECT DISTINCT program, program, 'College', COALESCE(department,'')
                FROM courses WHERE program IS NOT NULL AND program <> ''");
        }
        $conn->query("INSERT IGNORE INTO program_courses (program_id, course_id)
            SELECT p.id, c.id FROM courses c
            JOIN programs p ON p.code = c.program
            WHERE c.program IS NOT NULL AND c.program <> ''");
        $conn->query("INSERT IGNORE INTO program_courses (program_id, course_id)
            SELECT p.id, c.id FROM courses c
            JOIN programs p ON p.name = c.program
            WHERE c.program IS NOT NULL AND c.program <> ''");
    }
    $conn->query("INSERT IGNORE INTO seed_flags (name) VALUES ('program_courses')");
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

// ================================================================
//  AUDIT LOG HELPER
// ================================================================
function logAudit(mysqli $conn, array $authUser = null, string $action, string $targetType = '', int $targetId = 0, string $description = '', $oldValues = null, $newValues = null): void {
    $userId    = $authUser ? (int)($authUser['user_id'] ?? 0) : null;
    $userEmail = $authUser ? ($authUser['email'] ?? '') : '';
    $userRole  = $authUser ? ($authUser['role']  ?? '') : '';
    $ip        = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua        = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $oldJson   = $oldValues !== null ? json_encode($oldValues) : null;
    $newJson   = $newValues !== null ? json_encode($newValues) : null;

    $st = $conn->prepare("INSERT INTO audit_logs (user_id, user_email, user_role, action, target_type, target_id, description, old_values, new_values, ip_address, user_agent) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $st->bind_param("issssisssss", $userId, $userEmail, $userRole, $action, $targetType, $targetId, $description, $oldJson, $newJson, $ip, $ua);
    $st->execute();
    $st->close();
}

// ================================================================
//  AUDIT LOGS — READ
// ================================================================
if ($action === 'get_audit_logs') {
    $page     = max(1, (int)($_GET['page']     ?? 1));
    $limit    = min(100, max(10, (int)($_GET['limit'] ?? 25)));
    $offset   = ($page - 1) * $limit;
    $filterAction = trim($_GET['filter_action'] ?? '');
    $filterRole   = trim($_GET['filter_role']   ?? '');
    $filterUser   = trim($_GET['filter_user']   ?? '');
    $dateFrom     = trim($_GET['date_from']     ?? '');
    $dateTo       = trim($_GET['date_to']       ?? '');

    $where = ['1=1'];
    $params = [];
    $types  = '';

    if ($filterAction) { $where[] = 'action LIKE ?'; $params[] = "%$filterAction%"; $types .= 's'; }
    if ($filterRole)   { $where[] = 'user_role = ?'; $params[] = $filterRole;       $types .= 's'; }
    if ($filterUser)   { $where[] = '(user_email LIKE ? OR CAST(user_id AS CHAR) = ?)'; $params[] = "%$filterUser%"; $params[] = $filterUser; $types .= 'ss'; }
    if ($dateFrom)     { $where[] = 'DATE(created_at) >= ?'; $params[] = $dateFrom; $types .= 's'; }
    if ($dateTo)       { $where[] = 'DATE(created_at) <= ?'; $params[] = $dateTo;   $types .= 's'; }

    $whereStr = implode(' AND ', $where);

    // Count
    $countSql = "SELECT COUNT(*) AS total FROM audit_logs WHERE $whereStr";
    $countStmt = $conn->prepare($countSql);
    if ($params) $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $total = (int)$countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    // Data
    $dataSql = "SELECT * FROM audit_logs WHERE $whereStr ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $dataStmt = $conn->prepare($dataSql);
    $allParams  = array_merge($params, [$limit, $offset]);
    $allTypes   = $types . 'ii';
    $dataStmt->bind_param($allTypes, ...$allParams);
    $dataStmt->execute();
    $res  = $dataStmt->get_result();
    $logs = [];
    while ($r = $res->fetch_assoc()) {
        $r['old_values'] = $r['old_values'] ? json_decode($r['old_values'], true) : null;
        $r['new_values'] = $r['new_values'] ? json_decode($r['new_values'], true) : null;
        $logs[] = $r;
    }
    $dataStmt->close();

    ob_end_clean();
    echo json_encode([
        'success'    => true,
        'logs'       => $logs,
        'total'      => $total,
        'page'       => $page,
        'limit'      => $limit,
        'totalPages' => (int)ceil($total / $limit),
    ]);
    exit();
}

if ($action === 'get_audit_stats') {
    $stats = [];

    $res = $conn->query("SELECT action, COUNT(*) AS cnt FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY action ORDER BY cnt DESC LIMIT 10");
    $topActions = [];
    while ($r = $res->fetch_assoc()) $topActions[] = $r;

    $res2 = $conn->query("SELECT user_role, COUNT(*) AS cnt FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY user_role");
    $byRole = [];
    while ($r = $res2->fetch_assoc()) $byRole[] = $r;

    $res3 = $conn->query("SELECT DATE(created_at) AS day, COUNT(*) AS cnt FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) GROUP BY DATE(created_at) ORDER BY day ASC");
    $daily = [];
    while ($r = $res3->fetch_assoc()) $daily[] = $r;

    $res4 = $conn->query("SELECT COUNT(*) AS total FROM audit_logs");
    $totalAll = (int)$res4->fetch_assoc()['total'];

    $res5 = $conn->query("SELECT COUNT(*) AS cnt FROM audit_logs WHERE DATE(created_at)=CURDATE()");
    $today = (int)$res5->fetch_assoc()['cnt'];

    ob_end_clean();
    echo json_encode([
        'success'    => true,
        'totalAll'   => $totalAll,
        'today'      => $today,
        'topActions' => $topActions,
        'byRole'     => $byRole,
        'daily'      => $daily,
    ]);
    exit();
}

// ================================================================
//  STUDENTS — VIEW ONLY (admin cannot edit)
// ================================================================
if ($action === 'get_students') {
    $page    = max(1, (int)($_GET['page']   ?? 1));
    $limit   = min(100, max(10, (int)($_GET['limit']  ?? 25)));
    $offset  = ($page - 1) * $limit;
    $search  = trim($_GET['q']      ?? '');
    $program = trim($_GET['program'] ?? '');
    $status  = trim($_GET['status']  ?? '');
    $yl      = trim($_GET['year_level'] ?? '');

    $where  = ['1=1'];
    $params = [];
    $types  = '';

    if ($search) {
        $where[]  = "(s.student_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR CONCAT(s.first_name,' ',s.last_name) LIKE ? OR s.email LIKE ?)";
        $sq = "%$search%";
        $params   = array_merge($params, [$sq,$sq,$sq,$sq,$sq]);
        $types   .= 'sssss';
    }
    if ($program) { $where[] = 's.program = ?'; $params[] = $program; $types .= 's'; }
    if ($status)  { $where[] = 's.enrollment_status = ?'; $params[] = $status; $types .= 's'; }
    if ($yl)      { $where[] = 's.year_level = ?'; $params[] = $yl; $types .= 's'; }

    $whereStr = implode(' AND ', $where);

    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM students s WHERE $whereStr");
    if ($params) $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $total = (int)$countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    $dataStmt = $conn->prepare("
        SELECT s.id, s.student_number, s.first_name, s.last_name, s.email,
               s.program, s.year_level, s.semester, s.student_type,
               s.enrollment_status, s.phone AS contact_number, s.address,
               s.created_at, s.created_at AS updated_at,
               u.email AS user_email
        FROM students s
        LEFT JOIN users u ON u.id = s.user_id
        WHERE $whereStr
        ORDER BY s.last_name, s.first_name
        LIMIT ? OFFSET ?
    ");
    $allP = array_merge($params, [$limit, $offset]);
    $allT = $types . 'ii';
    $dataStmt->bind_param($allT, ...$allP);
    $dataStmt->execute();
    $res      = $dataStmt->get_result();
    $students = [];
    while ($r = $res->fetch_assoc()) $students[] = $r;
    $dataStmt->close();

    ob_end_clean();
    echo json_encode([
        'success'    => true,
        'students'   => $students,
        'total'      => $total,
        'page'       => $page,
        'limit'      => $limit,
        'totalPages' => (int)ceil($total / $limit),
    ]);
    exit();
}

if ($action === 'get_student_detail') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'ID required']); exit(); }

    $res = $conn->query("
        SELECT s.*, u.email AS user_email, u.created_at AS account_created
        FROM students s
        LEFT JOIN users u ON u.id = s.user_id
        WHERE s.id = $id LIMIT 1
    ");
    $student = $res ? $res->fetch_assoc() : null;
    if (!$student) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Student not found']); exit(); }

    // Enrollments
    $eRes = $conn->query("
        SELECT e.*, c.code, c.name AS course_name, c.credits, c.instructor
        FROM enrollments e
        JOIN courses c ON c.id = e.course_id
        WHERE e.student_id = $id
        ORDER BY e.semester DESC, c.code ASC
    ");
    $enrollments = [];
    while ($r = $eRes->fetch_assoc()) $enrollments[] = $r;

    // Grades summary
    $gRes = $conn->query("
        SELECT c.code, c.name AS course_name,
               MAX(CASE WHEN sg.term='Final' THEN sg.grade END) AS final_grade,
               e.semester
        FROM enrollments e
        JOIN courses c ON c.id = e.course_id
        LEFT JOIN student_grades sg ON sg.enrollment_id = e.id
        WHERE e.student_id = $id
        GROUP BY e.id, c.id
    ");
    $grades = [];
    while ($r = $gRes->fetch_assoc()) $grades[] = $r;

    logAudit($conn, $authUser, 'VIEW_STUDENT', 'student', $id, "Admin viewed student record: {$student['first_name']} {$student['last_name']}");

    ob_end_clean();
    echo json_encode(['success'=>true,'student'=>$student,'enrollments'=>$enrollments,'grades'=>$grades]);
    exit();
}

if ($action === 'get_student_stats') {
    $total      = (int)$conn->query("SELECT COUNT(*) AS c FROM students")->fetch_assoc()['c'];
    $enrolled   = (int)$conn->query("SELECT COUNT(*) AS c FROM students WHERE enrollment_status='Enrolled'")->fetch_assoc()['c'];
    $pending    = (int)$conn->query("SELECT COUNT(*) AS c FROM students WHERE enrollment_status='Pending'")->fetch_assoc()['c'];
    $inactive   = (int)$conn->query("SELECT COUNT(*) AS c FROM students WHERE enrollment_status NOT IN ('Enrolled','Pending')")->fetch_assoc()['c'];

    $byProgram = [];
    $res = $conn->query("SELECT program, COUNT(*) AS cnt FROM students GROUP BY program ORDER BY cnt DESC LIMIT 10");
    while ($r = $res->fetch_assoc()) $byProgram[] = $r;

    $byYearLevel = [];
    $res2 = $conn->query("SELECT year_level, COUNT(*) AS cnt FROM students GROUP BY year_level ORDER BY year_level");
    while ($r = $res2->fetch_assoc()) $byYearLevel[] = $r;

    ob_end_clean();
    echo json_encode(['success'=>true,'total'=>$total,'enrolled'=>$enrolled,'pending'=>$pending,'inactive'=>$inactive,'byProgram'=>$byProgram,'byYearLevel'=>$byYearLevel]);
    exit();
}

// ================================================================
//  FACULTY
// ================================================================
if ($action === 'get_faculty') {
    $rows = [];
    $res  = $conn->query("
        SELECT f.*,
            (SELECT COUNT(*) FROM courses c WHERE c.faculty_id = f.id) AS course_count
        FROM faculty f ORDER BY f.last_name, f.first_name ASC");
    while ($r = $res->fetch_assoc()) {
        $r['subjects'] = json_decode($r['subjects'] ?? '[]', true);
        $rows[] = $r;
    }
    ob_end_clean(); echo json_encode(['success' => true, 'faculty' => $rows]); exit();
}

if ($action === 'get_faculty_list') {
    $rows = [];
    $res  = $conn->query("SELECT id, faculty_id, first_name, last_name, department, specialty FROM faculty WHERE status='Active' ORDER BY last_name, first_name ASC");
    while ($r = $res->fetch_assoc()) { $r['full_name'] = $r['first_name'].' '.$r['last_name']; $rows[] = $r; }
    ob_end_clean(); echo json_encode(['success' => true, 'faculty' => $rows]); exit();
}

if ($action === 'create_faculty') {
    $fn   = trim($input['first_name']  ?? '');
    $ln   = trim($input['last_name']   ?? '');
    $em   = trim($input['email']       ?? '');
    $dept = trim($input['department']  ?? '');
    $spec = trim($input['specialty']   ?? '');
    $subj = json_encode($input['subjects'] ?? []);
    $stat = trim($input['status']      ?? 'Active');

    if (!$fn||!$ln||!$em) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'First name, last name, email required']); exit(); }

    $yr  = date('Y');
    $max = (int)($conn->query("SELECT MAX(CAST(SUBSTRING_INDEX(faculty_id,'-',-1) AS UNSIGNED)) AS m FROM faculty WHERE faculty_id LIKE 'FAC-$yr-%'")->fetch_assoc()['m'] ?? 0);
    $fid = 'FAC-'.$yr.'-'.str_pad($max+1,3,'0',STR_PAD_LEFT);

    $st  = $conn->prepare("INSERT INTO faculty (faculty_id,first_name,last_name,email,department,specialty,subjects,status) VALUES (?,?,?,?,?,?,?,?)");
    $st->bind_param("ssssssss",$fid,$fn,$ln,$em,$dept,$spec,$subj,$stat);
    try {
        $st->execute();
        $newId = $st->insert_id;
        logAudit($conn, $authUser, 'CREATE_FACULTY', 'faculty', $newId, "Created faculty: $fn $ln", null, ['faculty_id'=>$fid,'name'=>"$fn $ln",'email'=>$em]);
        ob_end_clean(); echo json_encode(['success'=>true,'id'=>$newId,'faculty_id'=>$fid]);
    }
    catch (Exception $e) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Email already exists']); }
    exit();
}

if ($action === 'update_faculty') {
    $id   = (int)($input['id']          ?? 0);
    $fn   = trim($input['first_name']   ?? '');
    $ln   = trim($input['last_name']    ?? '');
    $em   = trim($input['email']        ?? '');
    $dept = trim($input['department']   ?? '');
    $spec = trim($input['specialty']    ?? '');
    $subj = json_encode($input['subjects'] ?? []);
    $stat = trim($input['status']       ?? 'Active');

    if (!$id) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit(); }

    $oldRes = $conn->query("SELECT * FROM faculty WHERE id=$id LIMIT 1");
    $oldData = $oldRes ? $oldRes->fetch_assoc() : null;

    $st = $conn->prepare("UPDATE faculty SET first_name=?,last_name=?,email=?,department=?,specialty=?,subjects=?,status=? WHERE id=?");
    $st->bind_param("sssssssi",$fn,$ln,$em,$dept,$spec,$subj,$stat,$id);
    try {
        $st->execute();
        $full = $conn->real_escape_string("$fn $ln");
        $conn->query("UPDATE courses SET instructor='$full' WHERE faculty_id=$id");
        logAudit($conn, $authUser, 'UPDATE_FACULTY', 'faculty', $id, "Updated faculty: $fn $ln", $oldData, ['name'=>"$fn $ln",'email'=>$em,'status'=>$stat]);
        ob_end_clean(); echo json_encode(['success'=>true]);
    } catch (Exception $e) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Update failed']); }
    exit();
}

if ($action === 'delete_faculty') {
    $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
    if (!$id) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit(); }
    $oldRes  = $conn->query("SELECT * FROM faculty WHERE id=$id LIMIT 1");
    $oldData = $oldRes ? $oldRes->fetch_assoc() : null;
    $conn->query("UPDATE courses SET faculty_id=NULL WHERE faculty_id=$id");
    $conn->query("DELETE FROM faculty WHERE id=$id");
    logAudit($conn, $authUser, 'DELETE_FACULTY', 'faculty', $id, "Deleted faculty: ".($oldData ? $oldData['first_name'].' '.$oldData['last_name'] : $id), $oldData, null);
    ob_end_clean(); echo json_encode(['success'=>true]); exit();
}

// ================================================================
//  COURSES
// ================================================================
if ($action === 'get_courses') {
    $rows = [];
    $res  = $conn->query("
        SELECT c.*,
               COALESCE(CONCAT(f.first_name,' ',f.last_name), c.instructor, '') AS instructor,
               f.faculty_id AS faculty_code,
               f.id         AS faculty_db_id,
               COALESCE(
                   (SELECT p.level_type FROM programs p
                    INNER JOIN program_courses pc ON pc.program_id = p.id
                    WHERE pc.course_id = c.id LIMIT 1),
                   (SELECT p.level_type FROM programs p
                    WHERE p.name = c.program OR p.code = c.program LIMIT 1),
                   'College'
               ) AS level_type
        FROM courses c
        LEFT JOIN faculty f ON c.faculty_id = f.id
        ORDER BY c.program, c.code ASC");
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    ob_end_clean(); echo json_encode(['success'=>true,'courses'=>$rows]); exit();
}

if ($action === 'create_course') {
    $code  = trim($input['code']        ?? '');
    $name  = trim($input['name']        ?? '');
    $desc  = trim($input['description'] ?? '');
    $cred  = (int)($input['credits']    ?? 3);
    $dept  = trim($input['department']  ?? '');
    $prog  = trim($input['program']     ?? '');
    $yl    = trim($input['year_level']  ?? '1st Year');
    $sem   = trim($input['semester']    ?? '');
    $day   = trim($input['day']         ?? '');
    $time  = trim($input['time']        ?? '');
    $room  = trim($input['room']        ?? '');
    $cap   = (int)($input['capacity']   ?? 40);
    $fid   = !empty($input['faculty_id']) ? (int)$input['faculty_id'] : null;
    $instr = trim($input['instructor']  ?? '');

    if ($fid) {
        $fr = $conn->query("SELECT CONCAT(first_name,' ',last_name) fn FROM faculty WHERE id=$fid LIMIT 1")->fetch_assoc();
        if ($fr) $instr = $fr['fn'];
    }

    if (!$code||!$name) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Code and name required']); exit(); }

    $fidVal = $fid ?: 'NULL';
    $is_lab = (int)($input['is_lab'] ?? 0) ? 1 : 0;
    $esc = fn($s) => $conn->real_escape_string($s);
    $lec_units = (int)($input['lec_units'] ?? $cred);
    $lab_units = (int)($input['lab_units'] ?? 0);
    if ($lec_units + $lab_units > 0) $cred = $lec_units + $lab_units;
    $conn->query('ALTER TABLE courses ADD COLUMN IF NOT EXISTS is_lab TINYINT(1) DEFAULT 0');
    $conn->query('ALTER TABLE courses ADD COLUMN IF NOT EXISTS lec_units INT DEFAULT 0');
    $conn->query('ALTER TABLE courses ADD COLUMN IF NOT EXISTS lab_units INT DEFAULT 0');
    $sql = "INSERT INTO courses (code,name,description,credits,lec_units,lab_units,department,program,year_level,semester,instructor,day,time,room,capacity,faculty_id,is_lab)
            VALUES ('{$esc($code)}','{$esc($name)}','{$esc($desc)}',$cred,$lec_units,$lab_units,'{$esc($dept)}','{$esc($prog)}','{$esc($yl)}','{$esc($sem)}','{$esc($instr)}','{$esc($day)}','{$esc($time)}','{$esc($room)}',$cap,$fidVal,$is_lab)";

    if ($conn->query($sql)) {
        $cid = $conn->insert_id;
        if ($prog) {
            $pr = $conn->query("SELECT id FROM programs WHERE name='{$esc($prog)}' LIMIT 1")->fetch_assoc();
            if ($pr) $conn->query("INSERT IGNORE INTO program_courses (program_id,course_id) VALUES ({$pr['id']},$cid)");
        }
        logAudit($conn, $authUser, 'CREATE_COURSE', 'course', $cid, "Created course: $code - $name", null, ['code'=>$code,'name'=>$name,'program'=>$prog]);
        ob_end_clean(); echo json_encode(['success'=>true,'course_id'=>$cid]);
    } else {
        ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Course code already exists or DB error: '.$conn->error]);
    }
    exit();
}

if ($action === 'update_course') {
    $id    = (int)($input['id']         ?? 0);
    $code  = trim($input['code']        ?? '');
    $name  = trim($input['name']        ?? '');
    $desc  = trim($input['description'] ?? '');
    $cred  = (int)($input['credits']    ?? 3);
    $dept  = trim($input['department']  ?? '');
    $prog  = trim($input['program']     ?? '');
    $yl    = trim($input['year_level']  ?? '1st Year');
    $sem   = trim($input['semester']    ?? '');
    $day   = trim($input['day']         ?? '');
    $time  = trim($input['time']        ?? '');
    $room  = trim($input['room']        ?? '');
    $cap   = (int)($input['capacity']   ?? 40);
    $fid   = !empty($input['faculty_id']) ? (int)$input['faculty_id'] : null;
    $instr = trim($input['instructor']  ?? '');

    if ($fid) {
        $fr = $conn->query("SELECT CONCAT(first_name,' ',last_name) fn FROM faculty WHERE id=$fid LIMIT 1")->fetch_assoc();
        if ($fr) $instr = $fr['fn'];
    }
    if (!$id) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit(); }

    $oldRes  = $conn->query("SELECT * FROM courses WHERE id=$id LIMIT 1");
    $oldData = $oldRes ? $oldRes->fetch_assoc() : null;

    $fidVal = $fid ?: 'NULL';
    $is_lab    = (int)($input['is_lab']    ?? 0) ? 1 : 0;
    $lec_units = (int)($input['lec_units'] ?? $cred);
    $lab_units = (int)($input['lab_units'] ?? 0);
    if ($lec_units + $lab_units > 0) $cred = $lec_units + $lab_units;
    $esc = fn($s) => $conn->real_escape_string($s);
    $conn->query('ALTER TABLE courses ADD COLUMN IF NOT EXISTS is_lab TINYINT(1) DEFAULT 0');
    $conn->query('ALTER TABLE courses ADD COLUMN IF NOT EXISTS lec_units INT DEFAULT 0');
    $conn->query('ALTER TABLE courses ADD COLUMN IF NOT EXISTS lab_units INT DEFAULT 0');
    $sql = "UPDATE courses SET code='{$esc($code)}',name='{$esc($name)}',description='{$esc($desc)}',credits=$cred,
            lec_units=$lec_units,lab_units=$lab_units,
            department='{$esc($dept)}',program='{$esc($prog)}',year_level='{$esc($yl)}',semester='{$esc($sem)}',
            instructor='{$esc($instr)}',day='{$esc($day)}',time='{$esc($time)}',room='{$esc($room)}',
            capacity=$cap,faculty_id=$fidVal,is_lab=$is_lab WHERE id=$id";

    if ($conn->query($sql)) {
        $conn->query("DELETE FROM program_courses WHERE course_id=$id");
        if ($prog) {
            $pr = $conn->query("SELECT id FROM programs WHERE name='{$esc($prog)}' LIMIT 1")->fetch_assoc();
            if ($pr) $conn->query("INSERT IGNORE INTO program_courses (program_id,course_id) VALUES ({$pr['id']},$id)");
        }
        logAudit($conn, $authUser, 'UPDATE_COURSE', 'course', $id, "Updated course: $code - $name", $oldData, ['code'=>$code,'name'=>$name,'program'=>$prog]);
        ob_end_clean(); echo json_encode(['success'=>true]);
    } else {
        ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Update failed: '.$conn->error]);
    }
    exit();
}

if ($action === 'delete_course') {
    $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
    if (!$id) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit(); }
    $oldRes  = $conn->query("SELECT * FROM courses WHERE id=$id LIMIT 1");
    $oldData = $oldRes ? $oldRes->fetch_assoc() : null;
    $conn->query("DELETE FROM program_courses WHERE course_id=$id");
    $conn->query("DELETE FROM courses WHERE id=$id");
    logAudit($conn, $authUser, 'DELETE_COURSE', 'course', $id, "Deleted course: ".($oldData ? $oldData['code'].' - '.$oldData['name'] : $id), $oldData, null);
    ob_end_clean(); echo json_encode(['success'=>true]); exit();
}

// ================================================================
//  PROGRAMS
// ================================================================
if ($action === 'get_programs') {
    $rows = [];
    $res  = $conn->query("
        SELECT p.*, GROUP_CONCAT(pc.course_id ORDER BY pc.course_id) AS course_ids, COUNT(pc.id) AS course_count
        FROM programs p LEFT JOIN program_courses pc ON p.id=pc.program_id
        GROUP BY p.id ORDER BY p.level_type, p.name ASC");
    while ($r = $res->fetch_assoc()) {
        $r['course_ids']   = $r['course_ids'] ? array_map('intval', explode(',', $r['course_ids'])) : [];
        $r['course_count'] = (int)$r['course_count'];
        $rows[] = $r;
    }
    ob_end_clean(); echo json_encode(['success'=>true,'programs'=>$rows]); exit();
}

if ($action === 'create_program') {
    $name  = trim($input['name']        ?? '');
    $code  = trim($input['code']        ?? '');
    $lt    = trim($input['level_type']  ?? 'College');
    $dur   = (int)($input['duration']   ?? 4);
    $desc  = trim($input['description'] ?? '');
    $dept  = trim($input['department']  ?? '');
    $cids  = $input['course_ids']       ?? [];

    if (!$name||!$code) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Name and code required']); exit(); }

    $st = $conn->prepare("INSERT INTO programs (name,code,level_type,duration,description,department) VALUES (?,?,?,?,?,?)");
    $st->bind_param("sssiss",$name,$code,$lt,$dur,$desc,$dept);
    try {
        $st->execute(); $pid = $st->insert_id;
        if (!empty($cids)) {
            $ins = $conn->prepare("INSERT IGNORE INTO program_courses (program_id,course_id) VALUES (?,?)");
            foreach ($cids as $cid) {
                $cid=(int)$cid; $ins->bind_param("ii",$pid,$cid); $ins->execute();
                $conn->query("UPDATE courses SET program='".$conn->real_escape_string($name)."' WHERE id=$cid");
            }
        }
        // ── Sync courses.department for all assigned courses ──
        $esc = fn($s) => $conn->real_escape_string($s);
        if ($dept !== '' && !empty($cids)) {
            $conn->query("UPDATE courses c
                INNER JOIN program_courses pc ON pc.course_id = c.id
                SET c.department = '{$esc($dept)}'
                WHERE pc.program_id = $pid");
        }
        logAudit($conn, $authUser, 'CREATE_PROGRAM', 'program', $pid, "Created program: $code - $name", null, ['name'=>$name,'code'=>$code,'level_type'=>$lt,'department'=>$dept]);
        ob_end_clean(); echo json_encode(['success'=>true,'program_id'=>$pid]);
    } catch (Exception $e) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Code already exists']); }
    exit();
}

if ($action === 'update_program') {
    $id   = (int)($input['id']         ?? 0);
    $name = trim($input['name']        ?? '');
    $code = trim($input['code']        ?? '');
    $lt   = trim($input['level_type']  ?? 'College');
    $dur  = (int)($input['duration']   ?? 4);
    $desc = trim($input['description'] ?? '');
    $dept = trim($input['department']  ?? '');
    $cids = $input['course_ids']       ?? [];

    if (!$id) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit(); }

    $oldRes  = $conn->query("SELECT * FROM programs WHERE id=$id LIMIT 1");
    $oldData = $oldRes ? $oldRes->fetch_assoc() : null;

    $st = $conn->prepare("UPDATE programs SET name=?,code=?,level_type=?,duration=?,description=?,department=? WHERE id=?");
    $st->bind_param("sssissi",$name,$code,$lt,$dur,$desc,$dept,$id);
    try {
        $st->execute();

        // FIX: Find courses that are being REMOVED from this program
        // (they were linked before but won't be in the new $cids list).
        // Clear their courses.program field so getProgramCourses Source 2
        // (WHERE program = name) no longer returns them after unlinking.
        $newCidInts = array_map('intval', $cids);
        $oldLinkedRes = $conn->query("SELECT course_id FROM program_courses WHERE program_id=$id");
        if ($oldLinkedRes) {
            while ($row = $oldLinkedRes->fetch_assoc()) {
                $oid = (int)$row['course_id'];
                if (!in_array($oid, $newCidInts, true)) {
                    // This course is being removed — clear its program field
                    $conn->query("UPDATE courses SET program='' WHERE id=$oid AND program='".$conn->real_escape_string($name)."'");
                }
            }
        }

        $conn->query("DELETE FROM program_courses WHERE program_id=$id");
        if (!empty($cids)) {
            $ins = $conn->prepare("INSERT IGNORE INTO program_courses (program_id,course_id) VALUES (?,?)");
            foreach ($cids as $cid) {
                $cid=(int)$cid; $ins->bind_param("ii",$id,$cid); $ins->execute();
                $conn->query("UPDATE courses SET program='".$conn->real_escape_string($name)."' WHERE id=$cid");
            }
        }

        // ── Immediately sync courses.department for ALL courses in this program ──
        // Match by program name OR code so no course is left with a stale dept
        $esc = fn($s) => $conn->real_escape_string($s);
        if ($dept !== '') {
            $conn->query("UPDATE courses SET department='{$esc($dept)}'
                WHERE program='{$esc($name)}' OR program='{$esc($code)}'");
            // Also sync via program_courses join (catches courses linked but with different program field)
            $conn->query("UPDATE courses c
                INNER JOIN program_courses pc ON pc.course_id = c.id
                SET c.department = '{$esc($dept)}'
                WHERE pc.program_id = $id");
        }

        logAudit($conn, $authUser, 'UPDATE_PROGRAM', 'program', $id, "Updated program: $code - $name", $oldData, ['name'=>$name,'code'=>$code,'department'=>$dept]);
        ob_end_clean(); echo json_encode(['success'=>true]);
    } catch (Exception $e) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Update failed: '.$e->getMessage()]); }
    exit();
}

if ($action === 'delete_program') {
    $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
    if (!$id) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit(); }
    $oldRes  = $conn->query("SELECT * FROM programs WHERE id=$id LIMIT 1");
    $oldData = $oldRes ? $oldRes->fetch_assoc() : null;
    $conn->query("DELETE FROM program_courses WHERE program_id=$id");
    $conn->query("DELETE FROM programs WHERE id=$id");
    logAudit($conn, $authUser, 'DELETE_PROGRAM', 'program', $id, "Deleted program: ".($oldData ? $oldData['code'] : $id), $oldData, null);
    ob_end_clean(); echo json_encode(['success'=>true]); exit();
}

// ================================================================
//  DEPARTMENT MANAGEMENT — sync, rename, delete
// ================================================================

// Called on every Courses page load to sync course.department from programs.department
if ($action === 'sync_course_departments') {
    // Match by program name — always overwrite (no stale dept check)
    $conn->query("UPDATE courses c
        INNER JOIN programs p ON c.program = p.name
        SET c.department = p.department
        WHERE p.department != ''");

    // Match by program code
    $conn->query("UPDATE courses c
        INNER JOIN programs p ON c.program = p.code
        SET c.department = p.department
        WHERE p.department != ''");

    // Match via program_courses join (catches courses where program field may differ)
    $conn->query("UPDATE courses c
        INNER JOIN program_courses pc ON pc.course_id = c.id
        INNER JOIN programs p ON p.id = pc.program_id
        SET c.department = p.department
        WHERE p.department != ''");

    // Also ensure lec_units / lab_units columns exist
    $conn->query('ALTER TABLE courses ADD COLUMN IF NOT EXISTS lec_units INT DEFAULT 0');
    $conn->query('ALTER TABLE courses ADD COLUMN IF NOT EXISTS lab_units INT DEFAULT 0');

    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Course departments synced']);
    exit();
}

// Rename a department across ALL programs AND courses
if ($action === 'rename_department') {
    $oldName = trim($input['old_name'] ?? '');
    $newName = trim($input['new_name'] ?? '');
    if (!$oldName || !$newName) {
        ob_end_clean(); echo json_encode(['success'=>false,'message'=>'old_name and new_name required']); exit();
    }
    $esc = fn($s) => $conn->real_escape_string($s);

    $conn->query("UPDATE programs SET department='{$esc($newName)}' WHERE department='{$esc($oldName)}'");
    $progUpdated = $conn->affected_rows;

    $conn->query("UPDATE courses SET department='{$esc($newName)}' WHERE department='{$esc($oldName)}'");
    $courseUpdated = $conn->affected_rows;

    logAudit($conn, $authUser, 'RENAME_DEPARTMENT', 'department', 0,
        "Renamed department: $oldName → $newName", ['name'=>$oldName], ['name'=>$newName]);

    ob_end_clean();
    echo json_encode([
        'success'          => true,
        'programs_updated' => $progUpdated,
        'courses_updated'  => $courseUpdated,
    ]);
    exit();
}

// Delete (clear) a department from ALL programs AND courses
if ($action === 'delete_department') {
    $deptName = trim($input['dept_name'] ?? '');
    if (!$deptName) {
        ob_end_clean(); echo json_encode(['success'=>false,'message'=>'dept_name required']); exit();
    }
    $esc = fn($s) => $conn->real_escape_string($s);

    $conn->query("UPDATE programs SET department='' WHERE department='{$esc($deptName)}'");
    $progUpdated = $conn->affected_rows;

    $conn->query("UPDATE courses SET department='' WHERE department='{$esc($deptName)}'");
    $courseUpdated = $conn->affected_rows;

    logAudit($conn, $authUser, 'DELETE_DEPARTMENT', 'department', 0,
        "Deleted department: $deptName", ['name'=>$deptName], null);

    ob_end_clean();
    echo json_encode([
        'success'          => true,
        'programs_updated' => $progUpdated,
        'courses_updated'  => $courseUpdated,
    ]);
    exit();
}

// ================================================================
//  ROOMS
// ================================================================
if ($action === 'get_rooms') {
    $rows = [];
    $res  = $conn->query("SELECT * FROM rooms ORDER BY building, room_name ASC");
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    ob_end_clean(); echo json_encode(['success'=>true,'rooms'=>$rows]); exit();
}

if ($action === 'create_room') {
    $rn  = trim($input['room_name'] ?? '');
    $bld = trim($input['building']  ?? '');
    $cap = (int)($input['capacity'] ?? 40);
    $rt  = trim($input['room_type'] ?? 'Classroom');
    $st2 = trim($input['status']    ?? 'Available');
    if (!$rn) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Room name required']); exit(); }
    $st = $conn->prepare("INSERT INTO rooms (room_name,building,capacity,room_type,status) VALUES (?,?,?,?,?)");
    $st->bind_param("ssiss",$rn,$bld,$cap,$rt,$st2);
    try {
        $st->execute();
        $newId = $st->insert_id;
        logAudit($conn, $authUser, 'CREATE_ROOM', 'room', $newId, "Created room: $rn in $bld", null, ['room_name'=>$rn,'building'=>$bld]);
        ob_end_clean(); echo json_encode(['success'=>true,'room_id'=>$newId]);
    }
    catch (Exception $e) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Room exists']); }
    exit();
}

if ($action === 'update_room') {
    $id  = (int)($input['id']       ?? 0);
    $rn  = trim($input['room_name'] ?? '');
    $bld = trim($input['building']  ?? '');
    $cap = (int)($input['capacity'] ?? 40);
    $rt  = trim($input['room_type'] ?? 'Classroom');
    $st2 = trim($input['status']    ?? 'Available');
    if (!$id) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit(); }
    $st = $conn->prepare("UPDATE rooms SET room_name=?,building=?,capacity=?,room_type=?,status=? WHERE id=?");
    $st->bind_param("ssissi",$rn,$bld,$cap,$rt,$st2,$id);
    try {
        $st->execute();
        logAudit($conn, $authUser, 'UPDATE_ROOM', 'room', $id, "Updated room: $rn", null, ['room_name'=>$rn,'building'=>$bld,'status'=>$st2]);
        ob_end_clean(); echo json_encode(['success'=>true]);
    }
    catch (Exception $e) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Update failed']); }
    exit();
}

if ($action === 'delete_room') {
    $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
    if (!$id) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit(); }
    $oldRes  = $conn->query("SELECT * FROM rooms WHERE id=$id LIMIT 1");
    $oldData = $oldRes ? $oldRes->fetch_assoc() : null;
    $conn->query("DELETE FROM rooms WHERE id=$id");
    logAudit($conn, $authUser, 'DELETE_ROOM', 'room', $id, "Deleted room: ".($oldData ? $oldData['room_name'] : $id), $oldData, null);
    ob_end_clean(); echo json_encode(['success'=>true]); exit();
}

echo json_encode(['success'=>false,'message'=>'Unknown action: '.$action]);
$conn->close();
?>