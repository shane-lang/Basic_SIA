<?php
error_reporting(0);
ini_set('display_errors', 0);
mysqli_report(MYSQLI_REPORT_OFF);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

ob_start();

$conn = new mysqli('localhost', 'root', '', 'sia_db');
if ($conn->connect_error) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'DB connection failed: ' . $conn->connect_error]);
    exit();
}
$conn->set_charset("utf8mb4");

// ================================================================
//  AUTO-CREATE MISSING TABLES (safe — only creates if not exists)
// ================================================================
$conn->query("CREATE TABLE IF NOT EXISTS `faculty` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` VARCHAR(20) NOT NULL UNIQUE,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name`  VARCHAR(100) NOT NULL,
    `email`      VARCHAR(150) NOT NULL UNIQUE,
    `department` VARCHAR(100) DEFAULT '',
    `specialty`  VARCHAR(100) DEFAULT '',
    `subjects`   JSON,
    `status`     ENUM('Active','Inactive','On Leave') DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `programs` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(150) NOT NULL,
    `code`        VARCHAR(20)  NOT NULL UNIQUE,
    `level_type`  ENUM('College','SHS','TVET') DEFAULT 'College',
    `duration`    INT DEFAULT 4,
    `description` TEXT,
    `department`  VARCHAR(100) DEFAULT '',
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `program_courses` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `program_id` INT NOT NULL,
    `course_id`  INT NOT NULL,
    UNIQUE KEY `uq_pc` (`program_id`,`course_id`),
    FOREIGN KEY (`program_id`) REFERENCES `programs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`)  REFERENCES `courses`(`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `rooms` (
    `id`        INT AUTO_INCREMENT PRIMARY KEY,
    `room_name` VARCHAR(100) NOT NULL,
    `building`  VARCHAR(100) DEFAULT '',
    `capacity`  INT DEFAULT 40,
    `room_type` ENUM('Classroom','Laboratory','Lecture Hall') DEFAULT 'Classroom',
    `status`    ENUM('Available','Occupied','Under Maintenance') DEFAULT 'Available',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add faculty_id FK column to courses if not yet there
$conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS faculty_id INT NULL DEFAULT NULL");

// ── One-time seed: build programs/program_courses from existing courses.program column
$seedCount = (int)($conn->query("SELECT COUNT(*) AS c FROM programs")->fetch_assoc()['c'] ?? 0);
if ($seedCount === 0) {
    $conn->query("INSERT IGNORE INTO programs (name, code, level_type, department)
        SELECT DISTINCT program,
            UPPER(TRIM(REPLACE(REPLACE(REPLACE(program,'Bachelor of Science in ','BS '),' ',''),'.', ''))) AS code,
            'College', COALESCE(department,'')
        FROM courses WHERE program IS NOT NULL AND program <> ''");
    $conn->query("INSERT IGNORE INTO program_courses (program_id, course_id)
        SELECT p.id, c.id FROM courses c
        JOIN programs p ON p.name = c.program
        WHERE c.program IS NOT NULL AND c.program <> ''");
}

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

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
    // lightweight for dropdowns
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
    try { $st->execute(); ob_end_clean(); echo json_encode(['success'=>true,'id'=>$st->insert_id,'faculty_id'=>$fid]); }
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
    $st = $conn->prepare("UPDATE faculty SET first_name=?,last_name=?,email=?,department=?,specialty=?,subjects=?,status=? WHERE id=?");
    $st->bind_param("sssssssi",$fn,$ln,$em,$dept,$spec,$subj,$stat,$id);
    try {
        $st->execute();
        // Sync instructor name in all courses linked to this faculty
        $full = $conn->real_escape_string("$fn $ln");
        $conn->query("UPDATE courses SET instructor='$full' WHERE faculty_id=$id");
        ob_end_clean(); echo json_encode(['success'=>true]);
    } catch (Exception $e) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Update failed']); }
    exit();
}

if ($action === 'delete_faculty') {
    $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
    if (!$id) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit(); }
    $conn->query("UPDATE courses SET faculty_id=NULL WHERE faculty_id=$id");
    $conn->query("DELETE FROM faculty WHERE id=$id");
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
               f.id         AS faculty_db_id
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

    // Resolve instructor from faculty if faculty_id given
    if ($fid) {
        $fr = $conn->query("SELECT CONCAT(first_name,' ',last_name) fn FROM faculty WHERE id=$fid LIMIT 1")->fetch_assoc();
        if ($fr) $instr = $fr['fn'];
    }

    if (!$code||!$name) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Code and name required']); exit(); }

    $fidVal = $fid ?: 'NULL';
    $esc = fn($s) => $conn->real_escape_string($s);
    $sql = "INSERT INTO courses (code,name,description,credits,department,program,year_level,semester,instructor,day,time,room,capacity,faculty_id)
            VALUES ('{$esc($code)}','{$esc($name)}','{$esc($desc)}',$cred,'{$esc($dept)}','{$esc($prog)}','{$esc($yl)}','{$esc($sem)}','{$esc($instr)}','{$esc($day)}','{$esc($time)}','{$esc($room)}',$cap,$fidVal)";

    if ($conn->query($sql)) {
        $cid = $conn->insert_id;
        // Link to program_courses
        if ($prog) {
            $pr = $conn->query("SELECT id FROM programs WHERE name='{$esc($prog)}' LIMIT 1")->fetch_assoc();
            if ($pr) $conn->query("INSERT IGNORE INTO program_courses (program_id,course_id) VALUES ({$pr['id']},$cid)");
        }
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

    $fidVal = $fid ?: 'NULL';
    $esc = fn($s) => $conn->real_escape_string($s);
    $sql = "UPDATE courses SET code='{$esc($code)}',name='{$esc($name)}',description='{$esc($desc)}',credits=$cred,
            department='{$esc($dept)}',program='{$esc($prog)}',year_level='{$esc($yl)}',semester='{$esc($sem)}',
            instructor='{$esc($instr)}',day='{$esc($day)}',time='{$esc($time)}',room='{$esc($room)}',
            capacity=$cap,faculty_id=$fidVal WHERE id=$id";

    if ($conn->query($sql)) {
        // Rebuild program_courses link
        $conn->query("DELETE FROM program_courses WHERE course_id=$id");
        if ($prog) {
            $pr = $conn->query("SELECT id FROM programs WHERE name='{$esc($prog)}' LIMIT 1")->fetch_assoc();
            if ($pr) $conn->query("INSERT IGNORE INTO program_courses (program_id,course_id) VALUES ({$pr['id']},$id)");
        }
        ob_end_clean(); echo json_encode(['success'=>true]);
    } else {
        ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Update failed: '.$conn->error]);
    }
    exit();
}

if ($action === 'delete_course') {
    $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
    if (!$id) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit(); }
    $conn->query("DELETE FROM program_courses WHERE course_id=$id");
    $conn->query("DELETE FROM courses WHERE id=$id");
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
    $st = $conn->prepare("UPDATE programs SET name=?,code=?,level_type=?,duration=?,description=?,department=? WHERE id=?");
    $st->bind_param("sssissi",$name,$code,$lt,$dur,$desc,$dept,$id);
    try {
        $st->execute();
        $conn->query("DELETE FROM program_courses WHERE program_id=$id");
        if (!empty($cids)) {
            $ins = $conn->prepare("INSERT IGNORE INTO program_courses (program_id,course_id) VALUES (?,?)");
            foreach ($cids as $cid) {
                $cid=(int)$cid; $ins->bind_param("ii",$id,$cid); $ins->execute();
                $conn->query("UPDATE courses SET program='".$conn->real_escape_string($name)."' WHERE id=$cid");
            }
        }
        ob_end_clean(); echo json_encode(['success'=>true]);
    } catch (Exception $e) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Update failed']); }
    exit();
}

if ($action === 'delete_program') {
    $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
    if (!$id) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit(); }
    $conn->query("DELETE FROM program_courses WHERE program_id=$id");
    $conn->query("DELETE FROM programs WHERE id=$id");
    ob_end_clean(); echo json_encode(['success'=>true]); exit();
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
    try { $st->execute(); ob_end_clean(); echo json_encode(['success'=>true,'room_id'=>$st->insert_id]); }
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
    try { $st->execute(); ob_end_clean(); echo json_encode(['success'=>true]); }
    catch (Exception $e) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Update failed']); }
    exit();
}

if ($action === 'delete_room') {
    $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
    if (!$id) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit(); }
    $conn->query("DELETE FROM rooms WHERE id=$id");
    ob_end_clean(); echo json_encode(['success'=>true]); exit();
}

ob_end_clean();
echo json_encode(['success'=>false,'message'=>'Unknown action: '.$action]);
$conn->close();
?>