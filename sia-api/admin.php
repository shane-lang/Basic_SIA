<?php

require_once __DIR__ . '/config.php';
applyCors();
ob_start();
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/audit_helper.php';

$action = $_GET['action'] ?? '';

require_once __DIR__ . '/auth_middleware.php';
// get_enrollment_period is read-only and needed by accounting role to auto-fill
// semester/school year. It exposes no sensitive data — only label, dates, open status.
$publicActions = ['get_programs', 'get_enrollment_period'];
// Both 'admin' and 'registrar' are permitted to use this file.
// requireAuth() with no role arg validates the token; we then check the role ourselves.
$authUser = in_array($action, $publicActions) ? null : requireAuth($conn);
if ($authUser && !in_array($authUser['role'], ['admin', 'registrar'], true)) {
    http_response_code(403);
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => false, 'message' => 'Access denied.', 'code' => 'FORBIDDEN']);
    exit();
}

// ================================================================
//  AUTO-CREATE AUDIT LOG TABLE
// ================================================================
$_sfRes = $conn->query("SELECT 1 FROM seed_flags WHERE name='program_courses' LIMIT 1");
if (!$_sfRes || $_sfRes->num_rows === 0) {
    $pcSeedCount = (int)((($_r=$conn->query("SELECT COUNT(*) AS c FROM program_courses")) ? $_r->fetch_assoc()['c'] : 0) ?? 0);
    if ($pcSeedCount === 0) {
        $progSeedCount = (int)((($_r=$conn->query("SELECT COUNT(*) AS c FROM programs")) ? $_r->fetch_assoc()['c'] : 0) ?? 0);
        if ($progSeedCount === 0) {
            $conn->query("INSERT IGNORE INTO programs (name, code, level_type, department)
                SELECT DISTINCT program, program, 'College', COALESCE(department,'')
                FROM courses WHERE program IS NOT NULL AND program <> ''");
        }
        // Single INSERT matching by code OR name (avoids duplicate rows from two separate INSERTs)
        $conn->query("INSERT IGNORE INTO program_courses (program_id, course_id)
            SELECT p.id, c.id FROM courses c
            JOIN programs p ON (p.code = c.program OR p.name = c.program)
            WHERE c.program IS NOT NULL AND c.program <> ''");
    }
    $conn->query("INSERT IGNORE INTO seed_flags (name) VALUES ('program_courses')");
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

// ================================================================
//  AUDIT LOG HELPER
// ================================================================
function logAudit(mysqli $conn, array $authUser = null, string $action, string $targetType = '', int $targetId = 0, string $description = '', $oldValues = null, $newValues = null): void {
    // Delegate to shared helper so all roles log to the same table
    logAuditShared($conn, $authUser, $action, $targetType, $targetId, $description, $oldValues, $newValues);
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
        $where[]  = "(s.student_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR CONCAT(s.first_name,' ',s.last_name) LIKE ? OR u.email LIKE ?)";
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
        SELECT s.id, s.student_number, s.first_name, s.last_name, u.email,
               s.program, s.year_level, s.semester, s.student_type,
               s.enrollment_status, s.phone AS contact_number, s.address,
               s.created_at, s.created_at AS updated_at,
               u.email AS user_email,
               s.is_scholar, s.scholar_type, s.scholar_grantor,
               s.scholarship_amount AS scholar_discount,
               s.payment_status
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
        SELECT e.*, c.code, c.name AS course_name, c.credits,
               COALESCE(c.lec_units, c.credits) AS lec_units,
               COALESCE(c.lab_units, 0) AS lab_units,
               COALESCE(c.is_general, 0) AS is_general,
               COALESCE(c.is_lab, 0) AS is_lab,
               CONCAT(COALESCE(f.first_name,''),' ',COALESCE(f.last_name,'')) AS instructor
        FROM enrollments e
        JOIN courses c ON c.id = e.course_id
        LEFT JOIN faculty f ON f.user_id = c.faculty_id
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
    $total      = (int)(($_r=$conn->query("SELECT COUNT(*) AS c FROM students")) ? $_r->fetch_assoc() : null)['c'];
    $enrolled   = (int)(($_r=$conn->query("SELECT COUNT(*) AS c FROM students WHERE enrollment_status='Enrolled'")) ? $_r->fetch_assoc() : null)['c'];
    $pending    = (int)(($_r=$conn->query("SELECT COUNT(*) AS c FROM students WHERE enrollment_status='Pending'")) ? $_r->fetch_assoc() : null)['c'];
    $inactive   = (int)(($_r=$conn->query("SELECT COUNT(*) AS c FROM students WHERE enrollment_status NOT IN ('Enrolled','Pending')")) ? $_r->fetch_assoc() : null)['c'];

    // Total registered user accounts per role
    $userCounts = [];
    $ucRes = $conn->query("SELECT role, COUNT(*) AS cnt FROM users GROUP BY role ORDER BY cnt DESC");
    while ($ur = $ucRes->fetch_assoc()) $userCounts[$ur['role']] = (int)$ur['cnt'];
    $totalUserAccounts = array_sum($userCounts);
    $totalStudentAccounts = $userCounts['student'] ?? 0;

    // Students with no profile yet (user account exists but students record missing)
    $orphanRes = $conn->query("SELECT COUNT(*) AS c FROM users u LEFT JOIN students s ON s.user_id = u.id WHERE u.role='student' AND s.id IS NULL");
    $orphanAccounts = (int)(($orphanRes ? $orphanRes->fetch_assoc()['c'] : 0) ?? 0);

    $byProgram = [];
    $res = $conn->query("SELECT program, COUNT(*) AS cnt FROM students GROUP BY program ORDER BY cnt DESC LIMIT 10");
    while ($r = $res->fetch_assoc()) $byProgram[] = $r;

    $byYearLevel = [];
    $res2 = $conn->query("SELECT year_level, COUNT(*) AS cnt FROM students GROUP BY year_level ORDER BY year_level");
    while ($r = $res2->fetch_assoc()) $byYearLevel[] = $r;

    ob_end_clean();
    echo json_encode([
        'success'               => true,
        'total'                 => $total,
        'enrolled'              => $enrolled,
        'pending'               => $pending,
        'inactive'              => $inactive,
        'byProgram'             => $byProgram,
        'byYearLevel'           => $byYearLevel,
        // User account breakdown
        'total_user_accounts'   => $totalUserAccounts,
        'student_user_accounts' => $totalStudentAccounts,
        'orphan_accounts'       => $orphanAccounts,
        'user_counts_by_role'   => $userCounts,
    ]);
    exit();
}

// ================================================================
//  FACULTY
// ================================================================
if ($action === 'get_faculty') {
    $rows = [];
    $res  = $conn->query("
        SELECT f.*,
            (SELECT COUNT(*) FROM courses c WHERE c.faculty_id = f.user_id) AS course_count
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
    $max = (int)((($_r=$conn->query("SELECT MAX(CAST(SUBSTRING_INDEX(faculty_id,'-',-1) AS UNSIGNED)) AS m FROM faculty WHERE faculty_id LIKE 'FAC-$yr-%'")) ? $_r->fetch_assoc()['m'] : null) ?? 0);
    $fid = 'FAC-'.$yr.'-'.str_pad($max+1,3,'0',STR_PAD_LEFT);

    // Check duplicate email
    $emailCheck = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $emailCheck->bind_param("s", $em);
    $emailCheck->execute();
    if ($emailCheck->get_result()->num_rows > 0) {
        ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Email already exists']); exit();
    }
    $emailCheck->close();

    // 1. Create users row so faculty can log in
    // Default password: LastName + current year  e.g. Santos2026
    $defaultPw   = $ln . date('Y');
    $hashedPw    = password_hash($defaultPw, PASSWORD_BCRYPT, ['cost' => 12]);
    $uStmt = $conn->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'faculty')");
    $uStmt->bind_param("ss", $em, $hashedPw);
    $uStmt->execute();
    $newUserId = $uStmt->insert_id;
    $uStmt->close();

    if (!$newUserId) {
        ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Failed to create user account']); exit();
    }

    // 2. Create faculty row linked to new user
    $st = $conn->prepare("INSERT INTO faculty (user_id,faculty_id,first_name,last_name,email,department,specialty,subjects,status) VALUES (?,?,?,?,?,?,?,?,?)");
    $st->bind_param("issssssss", $newUserId,$fid,$fn,$ln,$em,$dept,$spec,$subj,$stat);
    try {
        $st->execute();
        $newId = $st->insert_id;
        $subjArr = $input['subjects'] ?? [];
        if ($newId && count($subjArr) > 0) {
            foreach ($subjArr as $code) {
                // FIX FACULTY-CODE-01: use the correct variable and match suffixed codes too
                $esc_c = $conn->real_escape_string(trim($code));
                $conn->query("UPDATE courses SET faculty_id=$newUserId
                              WHERE (code='$esc_c' OR code LIKE '$esc_c-%')
                                AND (faculty_id IS NULL OR faculty_id=$newUserId)");
            }
        }
        logAudit($conn, $authUser, 'CREATE_FACULTY', 'faculty', $newId, "Created faculty: $fn $ln", null, ['faculty_id'=>$fid,'name'=>"$fn $ln",'email'=>$em]);
        // DATA PRIVACY: Never return plain-text passwords in API responses.
        // The default password is displayed once in the admin UI then discarded.
        // In production, send it via a secure email or one-time link instead.
        ob_end_clean(); echo json_encode(['success'=>true,'id'=>$newId,'faculty_id'=>$fid,'default_password'=>$defaultPw]);
    } catch (Exception $e) {
        $conn->query("DELETE FROM users WHERE id=$newUserId");
        ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Failed to create faculty: '.$e->getMessage()]);
    }
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
    $facUserId = (int)($oldData['user_id'] ?? 0);

    $st = $conn->prepare("UPDATE faculty SET first_name=?,last_name=?,email=?,department=?,specialty=?,subjects=?,status=? WHERE id=?");
    $st->bind_param("sssssssi",$fn,$ln,$em,$dept,$spec,$subj,$stat,$id);
    try {
        $st->execute();
        // Sync email in users table if it changed
        if ($facUserId) {
            $syncEmail = $conn->prepare("UPDATE users SET email = ? WHERE id = ? AND role = 'faculty'");
            $syncEmail->bind_param('si', $em, $facUserId);
            $syncEmail->execute();
            $syncEmail->close();
        }
        // Re-sync subject codes: clear old links, apply new ones
        // courses.faculty_id FK → users.id, so use facUserId
        $conn->query("UPDATE courses SET faculty_id=NULL WHERE faculty_id=$facUserId");
        $subjArr = $input['subjects'] ?? [];
        foreach ($subjArr as $code) {
            // FIX FACULTY-CODE-01: get_courses returns clean codes (CC100) but
            // courses.code in DB may still have program suffixes (CC100-IT, CC100-BMD).
            // Match BOTH so faculty_id is never left NULL for suffixed rows.
            $esc = $conn->real_escape_string(trim($code));
            $conn->query("UPDATE courses SET faculty_id=$facUserId
                          WHERE (code='$esc' OR code LIKE '$esc-%')
                            AND (faculty_id IS NULL OR faculty_id=$facUserId)");
        }
        // Sync course_sections AFTER courses are re-assigned so students see updated instructor
        $conn->query("UPDATE course_sections SET faculty_id=$facUserId WHERE course_id IN (SELECT id FROM courses WHERE faculty_id=$facUserId)");
        // Clear faculty from sections whose course was unlinked from this faculty
        $conn->query("UPDATE course_sections cs JOIN courses c ON cs.course_id=c.id SET cs.faculty_id=NULL WHERE cs.faculty_id=$facUserId AND c.faculty_id != $facUserId");
        logAudit($conn, $authUser, 'UPDATE_FACULTY', 'faculty', $id, "Updated faculty: $fn $ln", $oldData, ['name'=>"$fn $ln",'email'=>$em,'status'=>$stat]);
        ob_end_clean(); echo json_encode(['success'=>true]);
    } catch (Exception $e) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Update failed: '.$e->getMessage()]); }
    exit();
}

if ($action === 'delete_faculty') {
    $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
    if (!$id) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit(); }
    $oldRes       = $conn->query("SELECT * FROM faculty WHERE id=$id LIMIT 1");
    $oldData      = $oldRes ? $oldRes->fetch_assoc() : null;
    $linkedUserId = (int)($oldData['user_id'] ?? 0);
    // courses.faculty_id FK → users.id, so clear using linkedUserId
    if ($linkedUserId) {
        $conn->query("UPDATE courses SET faculty_id=NULL WHERE faculty_id=$linkedUserId");
    }
    $conn->query("DELETE FROM faculty WHERE id=$id");
    if ($linkedUserId) {
        $conn->query("DELETE FROM sessions WHERE user_id=$linkedUserId");
        $conn->query("DELETE FROM users WHERE id=$linkedUserId AND role='faculty'");
    }
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
               COALESCE(CONCAT(f.first_name,' ',f.last_name),'') AS instructor,
               f.faculty_id AS faculty_code,
               f.id         AS faculty_db_id,
               COALESCE(
                   (SELECT p.level_type FROM programs p
                    INNER JOIN program_courses pc ON pc.program_id = p.id
                    WHERE pc.course_id = c.id LIMIT 1),
                   (SELECT p.level_type FROM programs p
                    WHERE p.name = c.program OR p.code = c.program LIMIT 1),
                   'College'
               ) AS level_type,
               cp.prerequisite_id,
               preq.code  AS prerequisite_code,
               preq.name  AS prerequisite_name
        FROM courses c
        LEFT JOIN faculty f ON c.faculty_id = f.user_id
        LEFT JOIN course_prerequisites cp   ON cp.course_id = c.id
        LEFT JOIN courses              preq ON preq.id = cp.prerequisite_id
        GROUP BY c.id
        ORDER BY c.program, c.code ASC");
    while ($r = $res->fetch_assoc()) { $r['code'] = cleanCode($r['code']); $rows[] = $r; }
    ob_end_clean(); echo json_encode(['success'=>true,'courses'=>$rows]); exit();
}

if ($action === 'create_course') {
    $code  = strtoupper(trim($input['code']        ?? ''));
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
    $cap   = 40;
    $fid   = !empty($input['faculty_id']) ? (int)$input['faculty_id'] : null;
    $instr = trim($input['instructor']  ?? '');
    // PREREQ: optional prerequisite course id
    $prereqId = !empty($input['prerequisite_id']) ? (int)$input['prerequisite_id'] : null;

    // courses.faculty_id FK → users.id; frontend sends faculty.id, so resolve user_id here
    $facUserId = null;
    if ($fid) {
        $fr = (($_r=$conn->query("SELECT user_id, CONCAT(first_name,' ',last_name) AS fn FROM faculty WHERE id=$fid LIMIT 1")) ? $_r->fetch_assoc() : null);
        if ($fr) { $instr = $fr['fn']; $facUserId = (int)$fr['user_id'] ?: null; }
    }

    if (!$code||!$name) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Code and name required']); exit(); }

    $esc = fn($s) => $s;

    // If course code already exists, reuse it — just link to the new programs (no duplicate row)
    $existing = (($_r=$conn->query("SELECT id FROM courses WHERE UPPER(code)=UPPER('{$esc($code)}') LIMIT 1")) ? $_r->fetch_assoc() : null);
    if ($existing) {
        $cid = (int)$existing['id'];
        // Link to primary program if not yet linked
        if ($prog) {
            $pr = (($_r=$conn->query("SELECT id FROM programs WHERE name='{$esc($prog)}' LIMIT 1")) ? $_r->fetch_assoc() : null);
            if ($pr) $conn->query("INSERT IGNORE INTO program_courses (program_id,course_id) VALUES ({$pr['id']},$cid)");
        }
        // Link to additional shared programs
        $sharedIds = $input['shared_program_ids'] ?? [];
        if (is_array($sharedIds)) {
            $ins = $conn->prepare("INSERT IGNORE INTO program_courses (program_id,course_id) VALUES (?,?)");
            foreach ($sharedIds as $pid) { $pid=(int)$pid; if($pid>0){$ins->bind_param("ii",$pid,$cid);$ins->execute();}}
        }
        _savePrerequisite($conn, $cid, $prereqId); // PREREQ
        logAudit($conn, $authUser, 'REUSE_COURSE', 'course', $cid, "Reused existing course: $code - linked to new program(s)", null, ['code'=>$code,'program'=>$prog]);
        ob_end_clean(); echo json_encode(['success'=>true,'course_id'=>$cid,'reused'=>true]); exit();
    }

    // courses.faculty_id stores users.id (not faculty.id); $facUserId resolved above
    $fidVal = $facUserId ?: 'NULL';
    $is_general = (int)($input['is_general'] ?? 0) ? 1 : 0;
    $lec_units = (int)($input['lec_units'] ?? $cred);
    $lab_units = (int)($input['lab_units'] ?? 0);
    if ($lec_units + $lab_units > 0) $cred = $lec_units + $lab_units;

    $sql = "INSERT INTO courses (code,name,description,credits,lec_units,lab_units,department,program,year_level,semester,capacity,faculty_id,is_general)
            VALUES ('{$esc($code)}','{$esc($name)}','{$esc($desc)}',$cred,$lec_units,$lab_units,'{$esc($dept)}','{$esc($prog)}','{$esc($yl)}','{$esc($sem)}',$cap,$fidVal,$is_general)";

    if ($conn->query($sql)) {
        $cid = $conn->insert_id;
        // Link to primary program
        if ($prog) {
            $pr = (($_r=$conn->query("SELECT id FROM programs WHERE name='{$esc($prog)}' LIMIT 1")) ? $_r->fetch_assoc() : null);
            if ($pr) $conn->query("INSERT IGNORE INTO program_courses (program_id,course_id) VALUES ({$pr['id']},$cid)");
        }
        // Link to additional shared programs
        $sharedIds = $input['shared_program_ids'] ?? [];
        if (is_array($sharedIds)) {
            $ins = $conn->prepare("INSERT IGNORE INTO program_courses (program_id,course_id) VALUES (?,?)");
            foreach ($sharedIds as $pid) { $pid=(int)$pid; if($pid>0){$ins->bind_param("ii",$pid,$cid);$ins->execute();}}
        }
        _savePrerequisite($conn, $cid, $prereqId); // PREREQ
        logAudit($conn, $authUser, 'CREATE_COURSE', 'course', $cid, "Created course: $code - $name", null, ['code'=>$code,'name'=>$name,'program'=>$prog]);
        ob_end_clean(); echo json_encode(['success'=>true,'course_id'=>$cid]);
    } else {
        ob_end_clean(); echo json_encode(['success'=>false,'message'=>'DB error: '.$conn->error]);
    }
    exit();
}

if ($action === 'update_course') {
    $id    = (int)($input['id']         ?? 0);
    $code  = strtoupper(trim($input['code']        ?? ''));
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
    $cap   = 40;
    $fid   = !empty($input['faculty_id']) ? (int)$input['faculty_id'] : null;
    $instr = trim($input['instructor']  ?? '');
    // PREREQ: null = admin cleared the prerequisite
    $prereqId = !empty($input['prerequisite_id']) ? (int)$input['prerequisite_id'] : null;

    // courses.faculty_id FK → users.id; resolve user_id from faculty.id
    $facUserId = null;
    if ($fid) {
        $fr = (($_r=$conn->query("SELECT user_id, CONCAT(first_name,' ',last_name) AS fn FROM faculty WHERE id=$fid LIMIT 1")) ? $_r->fetch_assoc() : null);
        if ($fr) { $instr = $fr['fn']; $facUserId = (int)$fr['user_id'] ?: null; }
    }
    if (!$id) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit(); }

    $esc = fn($s) => $s;

    // Duplicate check (excluding self)
    $dupCheck = $conn->query("SELECT id FROM courses WHERE (UPPER(code)=UPPER('{$esc($code)}') OR LOWER(name)=LOWER('{$esc($name)}')) AND id<>$id LIMIT 1");
    if ($dupCheck && $dupCheck->num_rows > 0) {
        ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Course code or name already exists.']); exit();
    }

    $oldRes  = $conn->query("SELECT * FROM courses WHERE id=$id LIMIT 1");
    $oldData = $oldRes ? $oldRes->fetch_assoc() : null;

    // courses.faculty_id stores users.id (not faculty.id); $facUserId resolved above
    $fidVal = $facUserId ?: 'NULL';
    $is_general = (int)($input['is_general'] ?? 0) ? 1 : 0;
    $lec_units = (int)($input['lec_units'] ?? $cred);
    $lab_units = (int)($input['lab_units'] ?? 0);
    if ($lec_units + $lab_units > 0) $cred = $lec_units + $lab_units;

    $sql = "UPDATE courses SET code='{$esc($code)}',name='{$esc($name)}',description='{$esc($desc)}',credits=$cred,
            lec_units=$lec_units,lab_units=$lab_units,
            department='{$esc($dept)}',program='{$esc($prog)}',year_level='{$esc($yl)}',semester='{$esc($sem)}',
            capacity=$cap,faculty_id=$fidVal,is_general=$is_general WHERE id=$id";

    if ($conn->query($sql)) {
        $conn->query("DELETE FROM program_courses WHERE course_id=$id");
        // Link to primary program
        if ($prog) {
            $pr = (($_r=$conn->query("SELECT id FROM programs WHERE name='{$esc($prog)}' LIMIT 1")) ? $_r->fetch_assoc() : null);
            if ($pr) $conn->query("INSERT IGNORE INTO program_courses (program_id,course_id) VALUES ({$pr['id']},$id)");
        }
        // Link to additional shared programs
        $sharedIds = $input['shared_program_ids'] ?? [];
        if (is_array($sharedIds)) {
            $ins = $conn->prepare("INSERT IGNORE INTO program_courses (program_id,course_id) VALUES (?,?)");
            foreach ($sharedIds as $pid) { $pid=(int)$pid; if($pid>0){$ins->bind_param("ii",$pid,$id);$ins->execute();}}
        }
        _savePrerequisite($conn, $id, $prereqId); // PREREQ
        // Propagate faculty change to course_sections so students see the updated instructor
        if ($facUserId) {
            // course_sections.faculty_id FK → users.id; use resolved $facUserId
            $conn->query("UPDATE course_sections SET faculty_id=$facUserId WHERE course_id=$id");
        } else {
            $conn->query("UPDATE course_sections SET faculty_id=NULL WHERE course_id=$id");
        }
        logAudit($conn, $authUser, 'UPDATE_COURSE', 'course', $id, "Updated course: $code - $name", $oldData, ['code'=>$code,'name'=>$name,'program'=>$prog]);
        ob_end_clean(); echo json_encode(['success'=>true]);
    } else {
        ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Update failed: '.$conn->error]);
    }
    exit();
}

// ── PREREQ helper ────────────────────────────────────────────────────────────
// Upserts or clears the single prerequisite row for a course.
// $prereqId === null  → delete existing row (admin removed it)
// $prereqId === int   → replace with new prerequisite (self-reference silently skipped)
function _savePrerequisite(mysqli $conn, int $courseId, ?int $prereqId): void {
    $del = $conn->prepare("DELETE FROM course_prerequisites WHERE course_id = ?");
    $del->bind_param('i', $courseId);
    $del->execute();
    $del->close();
    if (!$prereqId || $prereqId <= 0 || $prereqId === $courseId) return;
    $ins = $conn->prepare("INSERT IGNORE INTO course_prerequisites (course_id, prerequisite_id) VALUES (?, ?)");
    $ins->bind_param('ii', $courseId, $prereqId);
    $ins->execute();
    $ins->close();
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
            $updProg = $conn->prepare("UPDATE courses SET program = ? WHERE id = ?");
            foreach ($cids as $cid) {
                $cid=(int)$cid; $ins->bind_param("ii",$pid,$cid); $ins->execute();
                $updProg->bind_param("si",$name,$cid); $updProg->execute();
            }
            $updProg->close();
        }
        // ── Sync courses.department for all assigned courses ──
        $esc = fn($s) => $s;
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
                    $conn->query("UPDATE courses SET program='' WHERE id=$oid AND program='".$name."'");
                }
            }
        }

        $conn->query("DELETE FROM program_courses WHERE program_id=$id");
        if (!empty($cids)) {
            $ins = $conn->prepare("INSERT IGNORE INTO program_courses (program_id,course_id) VALUES (?,?)");
            $updProg2 = $conn->prepare("UPDATE courses SET program = ? WHERE id = ?");
            foreach ($cids as $cid) {
                $cid=(int)$cid; $ins->bind_param("ii",$id,$cid); $ins->execute();
                $updProg2->bind_param("si",$name,$cid); $updProg2->execute();
            }
            $updProg2->close();
        }

        // ── Immediately sync courses.department for ALL courses in this program ──
        // Match by program name OR code so no course is left with a stale dept
        $esc = fn($s) => $s;
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
    $esc = fn($s) => $s;

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
    $esc = fn($s) => $s;

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
    while ($r = $res->fetch_assoc()) { $r['code'] = cleanCode($r['code']); $rows[] = $r; }
    ob_end_clean(); echo json_encode(['success'=>true,'rooms'=>$rows]); exit();
}

if ($action === 'create_room') {
    $rn  = trim($input['room_name'] ?? '');
    $bld = trim($input['building']  ?? '');
    $cap = (int)($input['capacity'] ?? '');
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
    $cap = (int)($input['capacity'] ?? '');
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

// ================================================================
//  UTILITY — remove duplicate program_courses rows (keep lowest id)
// ================================================================
if ($action === 'deduplicate_program_courses') {
    $conn->query("
        DELETE pc1 FROM program_courses pc1
        INNER JOIN program_courses pc2
            ON pc1.program_id = pc2.program_id
           AND pc1.course_id  = pc2.course_id
           AND pc1.id > pc2.id
    ");
    $deleted = $conn->affected_rows;
    logAudit($conn, $authUser, 'DEDUP_PROGRAM_COURSES', 'program_courses', 0,
        "Removed $deleted duplicate program_courses rows", null, ['deleted' => $deleted]);
    ob_end_clean();
    echo json_encode(['success' => true, 'duplicates_removed' => $deleted]);
    exit();
}


// ================================================================
//  ENROLLMENT PERIOD MANAGEMENT
//  GET  ?action=get_enrollment_period
//  POST ?action=set_enrollment_period
// ================================================================
if ($action === "get_enrollment_period") {
    $res = $conn->query("SELECT config_value FROM sys_config WHERE config_key = \'enrollment_period\' LIMIT 1");
    $val = ($res && $res->num_rows > 0)
        ? json_decode($res->fetch_assoc()["config_value"], true)
        : ["start" => null, "end" => null, "is_open" => false, "label" => "",
           "semester" => "", "school_year" => ""];

    // FIX EP-01: Back-fill semester/school_year from label for legacy rows
    // that were saved before these fields existed.
    if (empty($val["semester"]) || empty($val["school_year"])) {
        $lbl = trim($val["label"] ?? "");
        if ($lbl !== "" && preg_match('/^(.+?),\s*AY\s*(\d{4}-\d{4})/i', $lbl, $lm)) {
            if (empty($val["semester"]))   $val["semester"]   = trim($lm[1]);
            if (empty($val["school_year"])) $val["school_year"] = trim($lm[2]);
        }
    }

    $nowRes = $conn->query("SELECT NOW() AS now");
    $nowStr = $nowRes ? $nowRes->fetch_assoc()["now"] : date("Y-m-d H:i:s");
    $now    = strtotime($nowStr);
    $isOpen = (bool)($val["is_open"] ?? false);
    if ($isOpen) {
        $s = !empty($val["start"]) ? strtotime(str_replace("T"," ",$val["start"])) : null;
        $e = !empty($val["end"])   ? strtotime(str_replace("T"," ",$val["end"]))   : null;
        if ($s && $now < $s) $isOpen = false;
        if ($e && $now > $e) $isOpen = false;
    }
    ob_end_clean();
    // period now always includes: label, semester, school_year, start, end, is_open
    echo json_encode(["success" => true, "period" => $val, "is_currently_open" => $isOpen]);
    exit();
}

if ($action === "set_enrollment_period") {
    $isOpen     = (bool)($input["is_open"]     ?? false);
    $label      = trim($input["label"]          ?? "");
    $start      = trim($input["start"]          ?? "");
    $end        = trim($input["end"]            ?? "");
    // FIX EP-01: Store semester term + school_year as separate fields so
    // Accounting can auto-populate due-date forms without re-typing them.
    // The frontend sends these explicitly; we also derive from label as fallback.
    $semTerm    = trim($input["semester"]        ?? "");   // e.g. "1st Semester"
    $schoolYear = trim($input["school_year"]     ?? "");   // e.g. "2025-2026"

    if ($isOpen && $label === "") {
        ob_end_clean();
        echo json_encode(["success" => false, "message" => "Semester label is required when opening enrollment."]);
        exit();
    }

    // Derive semester/school_year from label when not explicitly supplied.
    // Label format: "1st Semester, AY 2025-2026"
    if ($label !== "" && ($semTerm === "" || $schoolYear === "")) {
        if (preg_match('/^(.+?),\s*AY\s*(\d{4}-\d{4})/i', $label, $lm)) {
            if ($semTerm   === "") $semTerm   = trim($lm[1]);
            if ($schoolYear === "") $schoolYear = trim($lm[2]);
        }
    }
    // If still missing school_year, build it from current calendar year.
    if ($schoolYear === "") {
        $y = (int)date('Y');
        $schoolYear = $y . '-' . ($y + 1);
    }

    $start = $start ? str_replace("T", " ", $start) : null;
    $end   = $end   ? str_replace("T", " ", $end)   : null;
    $valJson = json_encode([
        "is_open"     => $isOpen,
        "label"       => $label,
        "start"       => $start,
        "end"         => $end,
        "semester"    => $semTerm,    // FIX EP-01: new field
        "school_year" => $schoolYear, // FIX EP-01: new field
    ]);

    $existing = $conn->query("SELECT id FROM sys_config WHERE config_key = \'enrollment_period\' LIMIT 1");
    if ($existing && $existing->num_rows > 0) {
        $st = $conn->prepare("UPDATE sys_config SET config_value = ? WHERE config_key = \'enrollment_period\'");
        $st->bind_param("s", $valJson); $st->execute(); $st->close();
    } else {
        $st = $conn->prepare("INSERT INTO sys_config (config_key, config_value) VALUES (\'enrollment_period\', ?)");
        $st->bind_param("s", $valJson); $st->execute(); $st->close();
    }

    // FIX EP-02: When admin saves/updates the enrollment period, auto-create the
    // scoped due-dates key for this semester so the student SOA always resolves
    // to the correct term. We only INITIALIZE if no scoped key exists yet —
    // we never overwrite due dates that Accounting has already configured.
    if ($semTerm !== "" && $schoolYear !== "") {
        $semSlug    = preg_replace('/[^a-z0-9_]/', '_', strtolower($semTerm));
        $yrSlug     = preg_replace('/[^0-9-]/', '', $schoolYear);
        $dueDateKey = "payment_due_dates:{$semSlug}:{$yrSlug}";
        $chk = $conn->prepare("SELECT id FROM sys_config WHERE config_key = ? LIMIT 1");
        $chk->bind_param('s', $dueDateKey);
        $chk->execute();
        $exists = $chk->get_result()->num_rows > 0;
        $chk->close();
        if (!$exists) {
            // Seed empty due-date record; Accounting fills values later.
            $emptyDates = json_encode([
                'downpayment' => ['label' => 'Downpayment', 'date_range' => ''],
                'prelim'      => ['label' => 'Prelim',      'date_range' => ''],
                'midterm'     => ['label' => 'Midterm',     'date_range' => ''],
                'finals'      => ['label' => 'Finals',      'date_range' => ''],
            ]);
            $seed = $conn->prepare("INSERT IGNORE INTO sys_config (config_key, config_value) VALUES (?, ?)");
            $seed->bind_param('ss', $dueDateKey, $emptyDates);
            $seed->execute();
            $seed->close();
        }
    }

    $flag = $isOpen ? "OPEN" : "CLOSE";
    logAudit($conn, $authUser, "ENROLLMENT_PERIOD_{$flag}", "sys_config", 0,
        "Enrollment period {$flag}: label=\"{$label}\", semester={$semTerm}, school_year={$schoolYear}, start={$start}, end={$end}");

    ob_end_clean();
    echo json_encode(["success" => true, "message" => "Enrollment period updated.", "period" => json_decode($valJson, true)]);
    exit();
}

// ================================================================
//  STAFF ACCOUNTS — admin, accounting, registrar
//  (faculty has its own CRUD above; students are read-only here)
//
//  Actions:
//    GET  ?action=get_staff_accounts          — paginated list with filters
//    POST ?action=create_staff_account        — create user + staff_profiles row
//    POST ?action=update_staff_account        — update profile & email
//    POST ?action=reset_staff_password        — admin-force password reset
//    POST ?action=toggle_staff_status         — activate / deactivate login
//    POST ?action=delete_staff_account        — hard delete (with safeguard)
// ================================================================

// ── Ensure is_active column exists on users table (safe no-op if already there) ──
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1");

// ── GET list ─────────────────────────────────────────────────────────────────
if ($action === 'get_staff_accounts') {
    $page   = max(1, (int)($_GET['page']   ?? 1));
    $limit  = min(100, max(10, (int)($_GET['limit']  ?? 25)));
    $offset = ($page - 1) * $limit;
    $search = trim($_GET['q']    ?? '');
    $role   = trim($_GET['role'] ?? '');   // admin | accounting | registrar | '' (all)

    $staffRoles = ['admin', 'accounting', 'registrar'];
    $where  = ['u.role IN ("admin","accounting","registrar")'];
    $params = [];
    $types  = '';

    if ($search) {
        $sq = "%$search%";
        $where[]  = "(sp.first_name LIKE ? OR sp.last_name LIKE ? OR CONCAT(sp.first_name,' ',sp.last_name) LIKE ? OR u.email LIKE ?)";
        $params   = array_merge($params, [$sq, $sq, $sq, $sq]);
        $types   .= 'ssss';
    }
    if ($role && in_array($role, $staffRoles, true)) {
        $where[]  = 'u.role = ?';
        $params[] = $role;
        $types   .= 's';
    }

    $whereStr = implode(' AND ', $where);

    $cntStmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM users u
        LEFT JOIN staff_profiles sp ON sp.user_id = u.id
        WHERE $whereStr
    ");
    if ($params) $cntStmt->bind_param($types, ...$params);
    $cntStmt->execute();
    $total = (int)$cntStmt->get_result()->fetch_assoc()['total'];
    $cntStmt->close();

    $dataStmt = $conn->prepare("
        SELECT u.id AS user_id, u.email, u.role, u.is_active,
               u.created_at AS account_created,
               sp.id        AS profile_id,
               sp.first_name, sp.last_name,
               sp.phone, sp.position, sp.department
        FROM users u
        LEFT JOIN staff_profiles sp ON sp.user_id = u.id
        WHERE $whereStr
        ORDER BY sp.last_name, sp.first_name, u.email
        LIMIT ? OFFSET ?
    ");
    $allP = array_merge($params, [$limit, $offset]);
    $allT = $types . 'ii';
    $dataStmt->bind_param($allT, ...$allP);
    $dataStmt->execute();
    $res   = $dataStmt->get_result();
    $staff = [];
    while ($r = $res->fetch_assoc()) $staff[] = $r;
    $dataStmt->close();

    ob_end_clean();
    echo json_encode([
        'success'    => true,
        'staff'      => $staff,
        'total'      => $total,
        'page'       => $page,
        'limit'      => $limit,
        'totalPages' => (int)ceil($total / max(1, $limit)),
    ]);
    exit();
}

// ── CREATE ────────────────────────────────────────────────────────────────────
if ($action === 'create_staff_account') {
    // Only admin may create staff accounts
    if ($authUser['role'] !== 'admin') {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Only admins can create staff accounts.']);
        exit();
    }

    $fn     = trim($input['first_name']  ?? '');
    $ln     = trim($input['last_name']   ?? '');
    $em     = strtolower(trim($input['email'] ?? ''));
    $role   = trim($input['role']        ?? '');
    $phone  = trim($input['phone']       ?? '');
    $pos    = trim($input['position']    ?? '');
    $dept   = trim($input['department']  ?? '');

    $allowed = ['admin', 'accounting', 'registrar'];
    if (!$fn || !$ln || !$em) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'First name, last name, and email are required.']);
        exit();
    }
    if (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
        exit();
    }
    if (!in_array($role, $allowed, true)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Role must be admin, accounting, or registrar.']);
        exit();
    }

    // Duplicate e-mail check
    $chk = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $chk->bind_param('s', $em);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'An account with that email already exists.']);
        exit();
    }
    $chk->close();

    // Default password: Role@Year  e.g.  Admin@2025
    $defaultPw = ucfirst($role) . '@' . date('Y');
    $hashed    = password_hash($defaultPw, PASSWORD_BCRYPT, ['cost' => 12]);

    $conn->begin_transaction();
    try {
        // 1. users row
        $uStmt = $conn->prepare("INSERT INTO users (email, password, role, is_active) VALUES (?, ?, ?, 1)");
        $uStmt->bind_param('sss', $em, $hashed, $role);
        $uStmt->execute();
        $newUserId = (int)$uStmt->insert_id;
        $uStmt->close();

        if (!$newUserId) throw new RuntimeException('Failed to insert user row.');

        // 2. staff_profiles row
        $spStmt = $conn->prepare("
            INSERT INTO staff_profiles (user_id, first_name, last_name, phone, position, department)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $spStmt->bind_param('isssss', $newUserId, $fn, $ln, $phone, $pos, $dept);
        $spStmt->execute();
        $newProfileId = (int)$spStmt->insert_id;
        $spStmt->close();

        $conn->commit();

        logAudit($conn, $authUser, 'CREATE_STAFF_ACCOUNT', 'staff_profiles', $newProfileId,
            "Created $role account: $fn $ln ($em)",
            null, ['email' => $em, 'role' => $role, 'name' => "$fn $ln"]);

        ob_end_clean();
        echo json_encode([
            'success'              => true,
            'user_id'              => $newUserId,
            'profile_id'           => $newProfileId,
            'temp_credential_hint' => "$defaultPw — share securely, not via this channel",
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to create account: ' . $e->getMessage()]);
    }
    exit();
}

// ── UPDATE ────────────────────────────────────────────────────────────────────
if ($action === 'update_staff_account') {
    $userId = (int)($input['user_id'] ?? 0);
    $fn     = trim($input['first_name']  ?? '');
    $ln     = trim($input['last_name']   ?? '');
    $em     = strtolower(trim($input['email'] ?? ''));
    $phone  = trim($input['phone']       ?? '');
    $pos    = trim($input['position']    ?? '');
    $dept   = trim($input['department']  ?? '');
    // Role change is intentionally NOT allowed here to prevent privilege escalation.
    // Use delete + create if a role change is truly needed.

    if (!$userId || !$fn || !$ln || !$em) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'user_id, first_name, last_name, and email are required.']);
        exit();
    }
    if (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
        exit();
    }

    // Confirm target is a non-student, non-faculty staff member
    $targetRes = $conn->query("SELECT id, role, email FROM users WHERE id=$userId AND role IN ('admin','accounting','registrar') LIMIT 1");
    $target    = $targetRes ? $targetRes->fetch_assoc() : null;
    if (!$target) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Staff account not found.']);
        exit();
    }

    // Email uniqueness (exclude self)
    $emChk = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
    $emChk->bind_param('si', $em, $userId);
    $emChk->execute();
    if ($emChk->get_result()->num_rows > 0) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'That email is already used by another account.']);
        exit();
    }
    $emChk->close();

    // Snapshot before update for audit trail
    $oldSpRes = $conn->query("SELECT * FROM staff_profiles WHERE user_id=$userId LIMIT 1");
    $oldSp    = $oldSpRes ? $oldSpRes->fetch_assoc() : [];

    $conn->begin_transaction();
    try {
        // Update users.email
        $uUpd = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
        $uUpd->bind_param('si', $em, $userId);
        $uUpd->execute();
        $uUpd->close();

        // Upsert staff_profiles (create if somehow missing)
        $spCheck = $conn->prepare("SELECT id FROM staff_profiles WHERE user_id = ? LIMIT 1");
        $spCheck->bind_param('i', $userId);
        $spCheck->execute();
        $spExists = $spCheck->get_result()->num_rows > 0;
        $spCheck->close();

        if ($spExists) {
            $spUpd = $conn->prepare("
                UPDATE staff_profiles
                SET first_name=?, last_name=?, phone=?, position=?, department=?
                WHERE user_id=?
            ");
            $spUpd->bind_param('sssssi', $fn, $ln, $phone, $pos, $dept, $userId);
            $spUpd->execute();
            $spUpd->close();
        } else {
            $spIns = $conn->prepare("
                INSERT INTO staff_profiles (user_id, first_name, last_name, phone, position, department)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $spIns->bind_param('isssss', $userId, $fn, $ln, $phone, $pos, $dept);
            $spIns->execute();
            $spIns->close();
        }

        $conn->commit();

        logAudit($conn, $authUser, 'UPDATE_STAFF_ACCOUNT', 'staff_profiles', $userId,
            "Updated {$target['role']} account: $fn $ln ($em)",
            $oldSp, ['first_name' => $fn, 'last_name' => $ln, 'email' => $em, 'phone' => $phone, 'position' => $pos, 'department' => $dept]);

        ob_end_clean();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Update failed: ' . $e->getMessage()]);
    }
    exit();
}

// ── RESET PASSWORD ────────────────────────────────────────────────────────────
if ($action === 'reset_staff_password') {
    if ($authUser['role'] !== 'admin') {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Only admins can reset staff passwords.']);
        exit();
    }

    $userId  = (int)($input['user_id']      ?? 0);
    $newPw   = trim($input['new_password']  ?? '');

    if (!$userId) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'user_id is required.']);
        exit();
    }

    // Confirm target is a staff member
    $targetRes = $conn->query("SELECT id, role, email FROM users WHERE id=$userId AND role IN ('admin','accounting','registrar') LIMIT 1");
    $target    = $targetRes ? $targetRes->fetch_assoc() : null;
    if (!$target) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Staff account not found.']);
        exit();
    }

    // If caller didn't supply a password, generate a default
    if ($newPw === '') {
        $newPw = ucfirst($target['role']) . '@' . date('Y');
    }
    if (strlen($newPw) < 8) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
        exit();
    }

    $hashed = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
    $pwStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $pwStmt->bind_param('si', $hashed, $userId);
    $pwStmt->execute();
    $pwStmt->close();

    // Invalidate active sessions so they must log in with the new password
    $conn->query("DELETE FROM sessions WHERE user_id = $userId");

    logAudit($conn, $authUser, 'RESET_STAFF_PASSWORD', 'users', $userId,
        "Password reset for {$target['role']}: {$target['email']}", null, ['email' => $target['email']]);

    ob_end_clean();
    echo json_encode([
        'success'              => true,
        'temp_credential_hint' => "$newPw — share securely, not via this channel",
    ]);
    exit();
}

// ── TOGGLE ACTIVE STATUS ──────────────────────────────────────────────────────
if ($action === 'toggle_staff_status') {
    if ($authUser['role'] !== 'admin') {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Only admins can change staff account status.']);
        exit();
    }

    $userId    = (int)($input['user_id']   ?? 0);
    $isActive  = isset($input['is_active']) ? (int)(bool)$input['is_active'] : null;

    if (!$userId || $isActive === null) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'user_id and is_active are required.']);
        exit();
    }

    // Prevent admin from deactivating their own account
    if ($userId === (int)$authUser['user_id']) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'You cannot deactivate your own account.']);
        exit();
    }

    $targetRes = $conn->query("SELECT id, role, email FROM users WHERE id=$userId AND role IN ('admin','accounting','registrar') LIMIT 1");
    $target    = $targetRes ? $targetRes->fetch_assoc() : null;
    if (!$target) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Staff account not found.']);
        exit();
    }

    $togStmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
    $togStmt->bind_param('ii', $isActive, $userId);
    $togStmt->execute();
    $togStmt->close();

    // Kill active sessions when deactivating
    if (!$isActive) {
        $conn->query("DELETE FROM sessions WHERE user_id = $userId");
    }

    $statusLabel = $isActive ? 'activated' : 'deactivated';
    logAudit($conn, $authUser, 'TOGGLE_STAFF_STATUS', 'users', $userId,
        "Account {$statusLabel}: {$target['role']} {$target['email']}",
        ['is_active' => !$isActive], ['is_active' => (bool)$isActive]);

    ob_end_clean();
    echo json_encode(['success' => true, 'is_active' => (bool)$isActive]);
    exit();
}

// ── DELETE ─────────────────────────────────────────────────────────────────────
if ($action === 'delete_staff_account') {
    if ($authUser['role'] !== 'admin') {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Only admins can delete staff accounts.']);
        exit();
    }

    $userId = (int)($input['user_id'] ?? $_GET['user_id'] ?? 0);
    if (!$userId) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'user_id is required.']);
        exit();
    }

    // Cannot delete yourself
    if ($userId === (int)$authUser['user_id']) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'You cannot delete your own account.']);
        exit();
    }

    $targetRes = $conn->query("SELECT id, role, email FROM users WHERE id=$userId AND role IN ('admin','accounting','registrar') LIMIT 1");
    $target    = $targetRes ? $targetRes->fetch_assoc() : null;
    if (!$target) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Staff account not found.']);
        exit();
    }

    $oldSpRes = $conn->query("SELECT * FROM staff_profiles WHERE user_id=$userId LIMIT 1");
    $oldSp    = $oldSpRes ? $oldSpRes->fetch_assoc() : [];

    $conn->begin_transaction();
    try {
        $conn->query("DELETE FROM sessions       WHERE user_id = $userId");
        $conn->query("DELETE FROM staff_profiles WHERE user_id = $userId");
        $conn->query("DELETE FROM users          WHERE id      = $userId AND role IN ('admin','accounting','registrar')");
        $conn->commit();

        logAudit($conn, $authUser, 'DELETE_STAFF_ACCOUNT', 'users', $userId,
            "Deleted {$target['role']} account: {$target['email']}", array_merge($oldSp, ['email' => $target['email'], 'role' => $target['role']]), null);

        ob_end_clean();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
    }
    exit();
}

echo json_encode(['success'=>false,'message'=>'Unknown action: '.$action]);
$conn->close();
?>