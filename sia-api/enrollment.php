<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'sia_db');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}
$conn->set_charset("utf8mb4");

// Ensure payment_method column exists
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS payment_method VARCHAR(20) NOT NULL DEFAULT 'GCash' AFTER approval_status");

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        switch ($action) {
            case 'get_profile':        getProfile($conn);          break;
            case 'get_schedule':       getSchedule($conn);         break;
            case 'get_courses':        getAvailableCourses($conn); break;
            case 'get_enrollments':    getEnrollments($conn);      break;
            case 'get_payment_status': getPaymentStatus($conn);    break;
            default: echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
        }
        break;
    case 'POST':
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'Invalid JSON. Raw: ' . substr($raw, 0, 200)]);
            exit();
        }
        switch ($action) {
            case 'register_student':   registerStudent($conn, $data);   break;
            case 'enroll_course':      enrollCourse($conn, $data);      break;
            case 'update_payment':     updatePayment($conn, $data);     break;
            case 'approve_enrollment': approveEnrollment($conn, $data); break;
            default: echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
        }
        break;
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        switch ($action) {
            case 'drop_course': dropCourse($conn, $data); break;
            default: echo json_encode(['success' => false, 'message' => 'Unknown action']);
        }
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
$conn->close();

// ─────────────────────────────────────────────────────────────
// HELPER: resolve student id from ?student_id=X or ?user_id=X
// ─────────────────────────────────────────────────────────────
function getStudentIdFromRequest($conn) {
    $student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
    $user_id    = isset($_GET['user_id'])    ? (int)$_GET['user_id']    : 0;
    if ($student_id > 0) return $student_id;
    if ($user_id > 0) {
        $stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r && $r->num_rows > 0) return (int)$r->fetch_assoc()['id'];
    }
    return 0;
}

// ─────────────────────────────────────────────────────────────
// GET PROFILE
// ─────────────────────────────────────────────────────────────
function getProfile($conn) {
    $student_id = getStudentIdFromRequest($conn);
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        return;
    }
    $stmt = $conn->prepare("SELECT s.*, u.email AS user_email FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ? LIMIT 1");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $s = $result->fetch_assoc();
        $pic = $s['profile_picture'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($s['first_name'] . '+' . $s['last_name']) . '&size=150';
        echo json_encode(['success' => true, 'student' => [
            'id'               => $s['student_number'],
            'dbId'             => (int)$s['id'],
            'firstName'        => $s['first_name'],
            'lastName'         => $s['last_name'],
            'email'            => $s['email'],
            'phone'            => $s['phone'] ?? '',
            'program'          => $s['program'],
            'yearLevel'        => $s['year_level'],
            'gpa'              => (float)$s['gpa'],
            'enrollmentStatus' => $s['enrollment_status'],
            'studentType'      => $s['student_type'],
            'paymentStatus'    => $s['payment_status'],
            'paymentMethod'    => $s['payment_method'] ?? 'GCash',
            'approvalStatus'   => $s['approval_status'],
            'dateOfBirth'      => $s['date_of_birth'],
            'address'          => $s['address'] ?? '',
            'emergencyContact' => $s['emergency_contact'] ?? '',
            'emergencyPhone'   => $s['emergency_phone'] ?? '',
            'profilePicture'   => $pic,
            'enrollmentDate'   => $s['enrollment_date'],
        ]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Profile not found']);
    }
}

// ─────────────────────────────────────────────────────────────
// GET PAYMENT STATUS — lightweight poll endpoint
// ─────────────────────────────────────────────────────────────
function getPaymentStatus($conn) {
    $student_id = getStudentIdFromRequest($conn);
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        return;
    }
    $stmt = $conn->prepare("SELECT payment_status, payment_method, approval_status, enrollment_status FROM students WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    if ($r) {
        echo json_encode([
            'success'          => true,
            'paymentStatus'    => $r['payment_status'],
            'paymentMethod'    => $r['payment_method'] ?? 'GCash',
            'approvalStatus'   => $r['approval_status'],
            'enrollmentStatus' => $r['enrollment_status'],
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
    }
}

// ─────────────────────────────────────────────────────────────
// GET SCHEDULE
// Returns all Enrolled courses with day/time breakdown for schedule grid
// ─────────────────────────────────────────────────────────────
function getSchedule($conn) {
    $student_id = getStudentIdFromRequest($conn);
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        return;
    }

    $stmt = $conn->prepare("
        SELECT
            c.id        AS course_id,
            c.code,
            c.name,
            c.instructor,
            c.day,
            c.time,
            c.room,
            c.semester,
            c.credits,
            e.status,
            e.grade
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = ?
          AND e.status = 'Enrolled'
        ORDER BY c.day, c.time
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $schedule = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Split multi-day entries (e.g. "Monday,Wednesday,Friday")
            $days = explode(',', $row['day'] ?? '');
            foreach ($days as $day) {
                $day = trim($day);
                if (!$day) continue;
                $schedule[] = [
                    'courseId'   => (int)$row['course_id'],
                    'day'        => $day,
                    'time'       => $row['time'],
                    'courseName' => $row['name'],
                    'courseCode' => $row['code'],
                    'instructor' => $row['instructor'],
                    'room'       => $row['room'],
                    'semester'   => $row['semester'],
                    'credits'    => (int)$row['credits'],
                    'status'     => $row['status'],
                    'grade'      => $row['grade'],
                ];
            }
        }
    }
    echo json_encode(['success' => true, 'schedule' => $schedule]);
}

// ─────────────────────────────────────────────────────────────
// GET AVAILABLE COURSES (not yet enrolled by this student)
// Uses real-time enrollment count from enrollments table.
// Capacity defaults to 50 if not set (0 or NULL).
// ─────────────────────────────────────────────────────────────
function getAvailableCourses($conn) {
    $student_id = getStudentIdFromRequest($conn);
    $semester   = isset($_GET['semester']) ? trim($_GET['semester']) : '';

    // Base query: real-time enrolled count + safe capacity fallback
    // COALESCE(capacity,50) prevents division by zero / always-full bug
    // GREATEST(capacity,1) same safeguard
    $baseSelect = "
        SELECT
            c.*,
            COALESCE(c.capacity, 50)                           AS safe_capacity,
            COUNT(e.id)                                        AS real_enrolled,
            GREATEST(COALESCE(c.capacity, 50) - COUNT(e.id), 0) AS available_seats
        FROM courses c
        LEFT JOIN enrollments e
            ON e.course_id = c.id AND e.status IN ('Pending','Enrolled')
    ";

    if ($student_id > 0 && $semester !== '') {
        $sql  = $baseSelect . "
            WHERE c.semester = ?
              AND c.id NOT IN (
                SELECT course_id FROM enrollments
                WHERE student_id = ? AND status IN ('Pending','Enrolled')
              )
            GROUP BY c.id
            ORDER BY c.code
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $semester, $student_id);

    } elseif ($student_id > 0) {
        $sql  = $baseSelect . "
            WHERE c.id NOT IN (
                SELECT course_id FROM enrollments
                WHERE student_id = ? AND status IN ('Pending','Enrolled')
            )
            GROUP BY c.id
            ORDER BY c.code
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $student_id);

    } elseif ($semester !== '') {
        $sql  = $baseSelect . "WHERE c.semester = ? GROUP BY c.id ORDER BY c.code";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $semester);

    } else {
        $sql  = $baseSelect . "GROUP BY c.id ORDER BY c.code";
        $stmt = $conn->prepare($sql);
    }

    $stmt->execute();
    $result  = $stmt->get_result();
    $courses = [];

    if ($result) {
        while ($r = $result->fetch_assoc()) {
            $cap       = (int)$r['safe_capacity'];
            $enrolled  = (int)$r['real_enrolled'];
            $available = (int)$r['available_seats'];

            $courses[] = [
                'id'          => (int)$r['id'],
                'code'        => $r['code'],
                'name'        => $r['name'],
                'credits'     => (int)$r['credits'],
                'instructor'  => $r['instructor'],
                'schedule'    => $r['schedule'] ?? '',
                'day'         => $r['day']      ?? '',
                'time'        => $r['time']     ?? '',
                'room'        => $r['room']     ?? '',
                'capacity'    => $cap,
                'enrolled'    => $enrolled,
                'available'   => $available,
                'semester'    => $r['semester']    ?? '',
                'description' => $r['description'] ?? '',
                'department'  => $r['department']  ?? '',
            ];
        }
    }
    echo json_encode(['success' => true, 'courses' => $courses]);
}

// ─────────────────────────────────────────────────────────────
// GET ENROLLMENTS — returns all enrollments for a student
// ─────────────────────────────────────────────────────────────
function getEnrollments($conn) {
    $student_id = getStudentIdFromRequest($conn);
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        return;
    }

    $stmt = $conn->prepare("
        SELECT
            e.id,
            e.status,
            e.enrollment_date,
            e.grade,
            e.notes,
            c.id        AS course_id,
            c.code,
            c.name,
            c.credits,
            c.instructor,
            c.schedule,
            c.day,
            c.time,
            c.room,
            c.semester
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = ?
          AND e.status IN ('Pending', 'Enrolled')
        ORDER BY e.created_at DESC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result      = $stmt->get_result();
    $enrollments = [];
    if ($result) {
        while ($r = $result->fetch_assoc()) {
            $enrollments[] = [
                'id'             => (int)$r['id'],
                'courseId'       => (int)$r['course_id'],
                'code'           => $r['code'],
                'name'           => $r['name'],
                'credits'        => (int)$r['credits'],
                'instructor'     => $r['instructor'],
                'schedule'       => $r['schedule'],
                'day'            => $r['day'],
                'time'           => $r['time'],
                'room'           => $r['room'],
                'semester'       => $r['semester'],
                'enrollmentDate' => $r['enrollment_date'],
                'status'         => $r['status'],
                'grade'          => $r['grade'],
                'notes'          => $r['notes'],
            ];
        }
    }
    echo json_encode(['success' => true, 'enrollments' => $enrollments]);
}

// ─────────────────────────────────────────────────────────────
// REGISTER STUDENT
// ─────────────────────────────────────────────────────────────
function registerStudent($conn, $data) {
    foreach (['user_id', 'firstName', 'lastName', 'email', 'program'] as $f) {
        if (empty($data[$f])) {
            echo json_encode(['success' => false, 'message' => "Field '$f' is required"]);
            return;
        }
    }

    $user_id          = (int)$data['user_id'];
    $firstName        = trim($data['firstName']);
    $lastName         = trim($data['lastName']);
    $email            = trim($data['email']);
    $phone            = trim($data['phone']            ?? '');
    $dateOfBirth      = trim($data['dateOfBirth']      ?? '');
    $address          = trim($data['address']          ?? '');
    $emergencyContact = trim($data['emergencyContact'] ?? '');
    $emergencyPhone   = trim($data['emergencyPhone']   ?? '');
    $program          = trim($data['program']);
    $studentType      = trim($data['studentType']      ?? 'New');
    $enrollmentDate   = date('Y-m-d');

    // Normalize payment method
    $rawMethod     = strtolower(trim($data['paymentMethod'] ?? 'gcash'));
    $paymentMethod = ($rawMethod === 'cash') ? 'Cash' : 'GCash';

    // Verify user exists
    $chk = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
    $chk->bind_param("i", $user_id);
    $chk->execute();
    if ($chk->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User ID ' . $user_id . ' not found. Please login again.']);
        return;
    }

    // Check for existing student record
    $ex = $conn->prepare("SELECT id, student_number FROM students WHERE user_id = ? LIMIT 1");
    $ex->bind_param("i", $user_id);
    $ex->execute();
    $exRes = $ex->get_result();
    if ($exRes->num_rows > 0) {
        $existing = $exRes->fetch_assoc();
        echo json_encode([
            'success'        => false,
            'message'        => 'Student record already exists.',
            'student_id'     => (int)$existing['id'],
            'student_number' => $existing['student_number'],
        ]);
        return;
    }

    // Generate student number
    $year    = date('Y');
    $cntStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM students WHERE YEAR(created_at) = ?");
    $cntStmt->bind_param("i", $year);
    $cntStmt->execute();
    $cnt           = (int)$cntStmt->get_result()->fetch_assoc()['cnt'] + 1;
    $studentNumber = "STU-$year-" . str_pad($cnt, 4, '0', STR_PAD_LEFT);

    $dob = (!empty($dateOfBirth)) ? $dateOfBirth : null;

    // INSERT with payment_method column
    $ins = $conn->prepare("
        INSERT INTO students
          (user_id, student_number, first_name, last_name, email, phone,
           date_of_birth, address, emergency_contact, emergency_phone,
           program, student_type, payment_method, enrollment_date,
           enrollment_status, payment_status, approval_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 'Pending', 'Pending')
    ");
    if (!$ins) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
        return;
    }
    $ins->bind_param("isssssssssssss",
        $user_id, $studentNumber, $firstName, $lastName, $email, $phone,
        $dob, $address, $emergencyContact, $emergencyPhone,
        $program, $studentType, $paymentMethod, $enrollmentDate
    );
    $ins->execute();

    if ($ins->affected_rows > 0) {
        $newStudentId = $ins->insert_id;

        // For Cash students: create a pending payment_log so Accounting can see them
        if ($paymentMethod === 'Cash') {
            $semester = '1st Semester, AY 2024-2025';
            $logStmt  = $conn->prepare("
                INSERT INTO payment_logs (student_id, payment_method, gcash_reference, gcash_amount, semester, status)
                VALUES (?, 'Cash', '', 0, ?, 'Pending')
            ");
            $logStmt->bind_param("is", $newStudentId, $semester);
            $logStmt->execute();
        }

        echo json_encode([
            'success'        => true,
            'message'        => 'Student registered successfully',
            'student_id'     => $newStudentId,
            'student_number' => $studentNumber,
            'payment_method' => $paymentMethod,
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $conn->error]);
    }
}

// ─────────────────────────────────────────────────────────────
// ENROLL COURSE
// Status set to 'Enrolled' immediately (auto-approved)
// since payment was already verified by Accounting before
// the student reaches the enlistment step.
// ─────────────────────────────────────────────────────────────
function enrollCourse($conn, $data) {
    $student_id = (int)($data['student_id'] ?? 0);
    $course_id  = (int)($data['course_id']  ?? 0);

    if (!$student_id || !$course_id) {
        echo json_encode(['success' => false,
            'message' => 'student_id and course_id required. Got student_id=' . $student_id . ', course_id=' . $course_id]);
        return;
    }

    // Check approval status — must be Approved to enlist
    $st = $conn->prepare("SELECT approval_status, first_name, last_name FROM students WHERE id = ? LIMIT 1");
    $st->bind_param("i", $student_id);
    $st->execute();
    $stRes = $st->get_result();
    if (!$stRes || $stRes->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Student not found (id=' . $student_id . ')']);
        return;
    }
    $studentRow = $stRes->fetch_assoc();
    if ($studentRow['approval_status'] !== 'Approved') {
        echo json_encode(['success' => false,
            'message' => 'Enrollment not approved yet. Payment status: ' . $studentRow['approval_status']]);
        return;
    }

    // Duplicate check
    $dup = $conn->prepare("SELECT id FROM enrollments WHERE student_id=? AND course_id=? AND status IN ('Pending','Enrolled') LIMIT 1");
    $dup->bind_param("ii", $student_id, $course_id);
    $dup->execute();
    if ($dup->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Already enrolled in this course']);
        return;
    }

    // Capacity check — real-time count from enrollments table
    $cr = $conn->prepare("
        SELECT c.id, c.name, c.semester,
               COALESCE(c.capacity, 50) AS safe_capacity,
               COUNT(e.id)              AS real_enrolled
        FROM courses c
        LEFT JOIN enrollments e ON e.course_id = c.id AND e.status IN ('Pending','Enrolled')
        WHERE c.id = ?
        GROUP BY c.id
        LIMIT 1
    ");
    $cr->bind_param("i", $course_id);
    $cr->execute();
    $crRes = $cr->get_result();
    if (!$crRes || $crRes->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Course not found']);
        return;
    }
    $course = $crRes->fetch_assoc();
    if ((int)$course['real_enrolled'] >= (int)$course['safe_capacity']) {
        echo json_encode(['success' => false, 'message' => 'Course is full (' . $course['real_enrolled'] . '/' . $course['safe_capacity'] . ')']);
        return;
    }

    $notes      = trim($data['notes']    ?? '');
    $semester   = trim($data['semester'] ?? $course['semester']);
    $enrollDate = date('Y-m-d');

    // Insert as 'Enrolled' directly — payment already verified
    $ins = $conn->prepare("
        INSERT INTO enrollments (student_id, course_id, enrollment_date, status, semester, notes)
        VALUES (?, ?, ?, 'Enrolled', ?, ?)
    ");
    $ins->bind_param("iisss", $student_id, $course_id, $enrollDate, $semester, $notes);
    $ins->execute();

    if ($ins->affected_rows > 0) {
        $eid = $ins->insert_id;

        // Increment course enrolled count
        $upd = $conn->prepare("UPDATE courses SET enrolled_count = enrolled_count + 1 WHERE id = ?");
        $upd->bind_param("i", $course_id);
        $upd->execute();

        // Update student enrollment_status = Enrolled
        $updSt = $conn->prepare("UPDATE students SET enrollment_status = 'Enrolled' WHERE id = ?");
        $updSt->bind_param("i", $student_id);
        $updSt->execute();

        echo json_encode([
            'success'       => true,
            'message'       => 'Enlisted successfully! ' . $course['name'] . ' is now in your schedule.',
            'enrollment_id' => $eid,
            'status'        => 'Enrolled',
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $conn->error]);
    }
}

// ─────────────────────────────────────────────────────────────
// DROP COURSE
// ─────────────────────────────────────────────────────────────
function dropCourse($conn, $data) {
    $enrollment_id = (int)($data['enrollment_id'] ?? 0);
    $student_id    = (int)($data['student_id']    ?? 0);
    if (!$enrollment_id || !$student_id) {
        echo json_encode(['success' => false, 'message' => 'enrollment_id and student_id required']);
        return;
    }

    $st = $conn->prepare("SELECT course_id FROM enrollments WHERE id=? AND student_id=? LIMIT 1");
    $st->bind_param("ii", $enrollment_id, $student_id);
    $st->execute();
    $res = $st->get_result();
    if (!$res || $res->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Enrollment not found']);
        return;
    }
    $course_id = (int)$res->fetch_assoc()['course_id'];

    $upd = $conn->prepare("UPDATE enrollments SET status='Dropped' WHERE id=? AND student_id=?");
    $upd->bind_param("ii", $enrollment_id, $student_id);
    $upd->execute();

    if ($upd->affected_rows > 0) {
        $dec = $conn->prepare("UPDATE courses SET enrolled_count = GREATEST(enrolled_count-1,0) WHERE id=?");
        $dec->bind_param("i", $course_id);
        $dec->execute();
        echo json_encode(['success' => true, 'message' => 'Course dropped successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Drop failed']);
    }
}

// ─────────────────────────────────────────────────────────────
// UPDATE PAYMENT
// ─────────────────────────────────────────────────────────────
function updatePayment($conn, $data) {
    $student_id = (int)($data['student_id'] ?? 0);
    $status     = trim($data['payment_status'] ?? 'Pending');
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'student_id required']);
        return;
    }
    $stmt = $conn->prepare("UPDATE students SET payment_status=? WHERE id=?");
    $stmt->bind_param("si", $status, $student_id);
    $stmt->execute();
    echo json_encode(['success' => true, 'message' => 'Payment updated']);
}

// ─────────────────────────────────────────────────────────────
// APPROVE ENROLLMENT (manual admin override)
// ─────────────────────────────────────────────────────────────
function approveEnrollment($conn, $data) {
    $student_id = (int)($data['student_id'] ?? 0);
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'student_id required']);
        return;
    }
    $stmt = $conn->prepare("UPDATE students SET approval_status='Approved', enrollment_status='Enrolled' WHERE id=?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    echo json_encode(['success' => true, 'message' => 'Enrollment approved']);
}
?>