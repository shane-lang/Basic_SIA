<?php
error_reporting(0);
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

$conn = new mysqli('localhost', 'root', '', 'sia_db');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->connect_error]); exit();
}
$conn->set_charset("utf8mb4");

// Ensure new columns exist
$conn->query("
  CREATE TABLE IF NOT EXISTS tor_evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    status ENUM('Pending','Evaluated','Rejected') NOT NULL DEFAULT 'Pending',
    credited_units INT NOT NULL DEFAULT 0,
    approved_units INT NOT NULL DEFAULT 0,
    credited_subjects TEXT DEFAULT NULL,
    registrar_notes TEXT DEFAULT NULL,
    evaluated_by INT DEFAULT NULL,
    evaluated_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY student_id (student_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS tor_eval_status ENUM('NotRequired','Pending','Evaluated','Rejected') NOT NULL DEFAULT 'NotRequired' AFTER student_type");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS payment_plan ENUM('full','installment') NOT NULL DEFAULT 'full' AFTER payment_method");

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        switch ($action) {
            case 'get_pending_tor':       getPendingTOR($conn);       break;
            case 'get_evaluated_tor':     getEvaluatedTOR($conn);     break;
            case 'get_tor_evaluation':    getTORForStudent($conn);     break;
            case 'get_program_courses':   getProgramCourses($conn);    break;
            case 'get_student_curriculum':getStudentCurriculum($conn); break;
            default: echo json_encode(['success' => false, 'message' => 'Unknown action']);
        }
        break;
    case 'POST':
        // Handle file upload separately (multipart/form-data)
        if ($action === 'upload_tor_file') { uploadTorFile($conn); break; }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { echo json_encode(['success' => false, 'message' => 'Invalid JSON']); exit(); }
        switch ($action) {
            case 'submit_tor':      submitTOR($conn, $data);      break;
            case 'evaluate_tor':    evaluateTOR($conn, $data);    break;
            case 'reject_tor':      rejectTOR($conn, $data);      break;
            default: echo json_encode(['success' => false, 'message' => 'Unknown action']);
        }
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
$conn->close();

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
    $conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS tor_file VARCHAR(255) DEFAULT NULL");
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

    $baseUrl  = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/sia-api/uploads/';
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
            s.last_school_attended,
            s.student_type,
            s.tor_eval_status,
            s.tor_file,
            tf.units AS program_units
        FROM tor_evaluations te
        JOIN students s ON te.student_id = s.id
        LEFT JOIN tuition_fees tf ON tf.student_id = s.id
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
                'programUnits'       => (int)($r['program_units'] ?? 0),
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

    $res = $conn->query("SELECT te.*, s.student_number, s.first_name, s.last_name, s.program, tf.units AS program_units
        FROM tor_evaluations te
        JOIN students s ON te.student_id = s.id
        LEFT JOIN tuition_fees tf ON tf.student_id = s.id
        WHERE te.student_id = $student_id LIMIT 1");
    $r = $res ? $res->fetch_assoc() : null;

    if (!$r) { echo json_encode(['success' => false, 'message' => 'No TOR evaluation found for this student']); return; }

    echo json_encode([
        'success' => true,
        'evaluation' => [
            'evalId'           => (int)$r['id'],
            'studentId'        => (int)$r['student_id'],
            'studentNumber'    => $r['student_number'],
            'studentName'      => $r['first_name'] . ' ' . $r['last_name'],
            'program'          => $r['program'],
            'programUnits'     => (int)($r['program_units'] ?? 0),
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

    // Try program_courses + programs join if tables exist
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

    // Fallback: courses.program direct column
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

    // Get program units + existing discount/installment from tuition_fees
    $tf_res        = $conn->query("SELECT units, discount, installment_fee FROM tuition_fees WHERE student_id = $student_id LIMIT 1");
    $tf_row        = $tf_res ? $tf_res->fetch_assoc() : null;
    $program_units = (int)($tf_row['units']           ?? 0);
    $discount      = (float)($tf_row['discount']       ?? 0);
    $inst_fee      = (float)($tf_row['installment_fee'] ?? 0);

    // Fallback: sum program_courses if tuition_fees not yet written
    if ($program_units === 0) {
        $st_res = $conn->query("SELECT program FROM students WHERE id = $student_id LIMIT 1");
        $st_row = $st_res ? $st_res->fetch_assoc() : null;
        $pn     = $conn->real_escape_string($st_row['program'] ?? '');
        $pu_res = $conn->query("
            SELECT COALESCE(SUM(c.credits),0) AS u
            FROM program_courses pc
            JOIN programs pr ON pc.program_id=pr.id
            JOIN courses c   ON pc.course_id=c.id
            WHERE pr.name='$pn' OR pr.code='$pn'");
        $program_units = (int)(($pu_res ? $pu_res->fetch_assoc()['u'] : 0) ?: 18);
    }

    $approved_units = max(0, $program_units - $credited_units);

    // ── 1. Update tor_evaluations ──────────────────────────────
    $stmt = $conn->prepare("
        UPDATE tor_evaluations
        SET status              = 'Evaluated',
            credited_units      = ?,
            approved_units      = ?,
            credited_subjects   = ?,
            credited_course_ids = ?,
            registrar_notes     = ?,
            evaluated_by        = ?,
            evaluated_at        = NOW()
        WHERE id = ? AND student_id = ?
    ");
    $stmt->bind_param("iisssiii",
        $credited_units, $approved_units,
        $credited_json, $course_ids_json,
        $notes, $registrar_id,
        $eval_id, $student_id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Evaluation record not found']); return;
    }

    // ── 2. Update student tor_eval_status ──────────────────────
    $conn->query("UPDATE students SET tor_eval_status = 'Evaluated' WHERE id = $student_id");

    // ── 3. Recompute tuition with approved_units ───────────────
    $u           = $approved_units > 0 ? $approved_units : $program_units;
    $tuition_fee = $u * 650;
    $misc_fee    = 6688.00;
    $reg_fee     = 700.00;
    $lab_fee     = $u * 1900;
    $energy_fee  = $u * 21 * 3;
    $subtotal    = $tuition_fee + $misc_fee + $reg_fee + $lab_fee + $energy_fee;
    $total       = max(0, $subtotal - $discount + $inst_fee);

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
            total_assessment=VALUES(total_assessment)
    ");
    $upd->bind_param("iiddddddddd",
        $student_id, $u,
        $tuition_fee, $misc_fee, $reg_fee,
        $lab_fee, $energy_fee, $subtotal,
        $discount, $inst_fee, $total);
    $upd->execute();

    // ── 4. Permanently block credited courses via enrollments ──
    // Strategy: enrollments has UNIQUE KEY(student_id, course_id).
    // We INSERT IGNORE a 'Dropped' row for every credited course.
    // This means auto_enroll_all's INSERT IGNORE will silently skip
    // them forever — the UNIQUE constraint blocks re-enrollment.
    if (!empty($course_id_ints)) {
        $ids_str = implode(',', $course_id_ints);
        $today   = date('Y-m-d');

        // Update any existing enrollment rows to Dropped
        $conn->query("
            UPDATE enrollments
            SET    status = 'Dropped',
                   notes  = 'Credited via TOR evaluation — permanently excluded'
            WHERE  student_id = $student_id
              AND  course_id  IN ($ids_str)
        ");

        // Insert Dropped rows for courses not yet in enrollments
        // UNIQUE KEY prevents duplicates; INSERT IGNORE skips if already exists
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

    // All program courses
    $result = $conn->query("
        SELECT c.id, c.code, c.name, c.credits, c.year_level, c.semester, c.description
        FROM program_courses pc
        JOIN programs pr ON pc.program_id = pr.id
        JOIN courses c   ON pc.course_id  = c.id
        WHERE pr.name = '$p' OR pr.code = '$p'
        UNION
        SELECT id, code, name, credits, year_level, semester, description
        FROM courses WHERE program = '$p'
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