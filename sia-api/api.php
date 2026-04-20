<?php
require_once __DIR__ . '/config.php';
applyCors();
ob_start(); // capture stray notices so JSON is never corrupted

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/audit_helper.php';
require_once __DIR__ . '/data_privacy.php';   // ← Privacy layer

$request = $_GET['action'] ?? '';
$method  = $_SERVER['REQUEST_METHOD'];

// ── Auth: public GET actions that need no session ──────────────────────────
$publicGetActions = ['available_courses'];
$authUser = null;
if ($method !== 'GET' || !in_array($request, $publicGetActions, true)) {
    $authUser = requireAuth($conn);
}

function apiRespond(array $payload, int $code = 200): never {
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
}

// ─── STUDENT ENDPOINTS ─────────────────────────────────────────────────────

if ($request === 'student_dashboard') {
    $student_id = (int)($_GET['student_id'] ?? 0);
    if (!$student_id) apiRespond(['success' => false, 'message' => 'student_id required'], 400);

    // Ownership check: students may only view their own record
    $isOwner = false;
    if ($authUser['role'] === 'student') {
        $own = $conn->prepare("SELECT id FROM students WHERE id = ? AND user_id = ? LIMIT 1");
        $own->bind_param('ii', $student_id, $authUser['user_id']);
        $own->execute();
        if ($own->get_result()->num_rows === 0) {
            apiRespond(['success' => false, 'message' => 'Access denied.'], 403);
        }
        $own->close();
        $isOwner = true;  // student is viewing their own record
    }

    $stmt = $conn->prepare(
        "SELECT s.*, u.email,
                sg.email        AS guardian_email,
                sg.guardian_name AS guardian_name,
                sg.contact      AS guardian_phone
         FROM students s
         JOIN users u ON s.user_id = u.id
         LEFT JOIN student_guardians sg ON sg.student_id = s.id AND sg.is_emergency = 1
         WHERE s.id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        apiRespond(['success' => false, 'message' => 'Student not found']);
    }

    // ── Apply RBAC + masking ─────────────────────────────────────────────────
    $safeRow = applyPrivacy($row, $authUser, 'student', $isOwner);
    $safeRow['_privacy'] = privacyMeta($authUser);

    apiRespond($safeRow);
}

if ($request === 'enrollments') {
    $student_id = (int)($_GET['student_id'] ?? 0);
    if (!$student_id) apiRespond(['success' => false, 'message' => 'student_id required'], 400);

    // Ownership check
    $isOwner = false;
    if ($authUser['role'] === 'student') {
        $own = $conn->prepare("SELECT id FROM students WHERE id = ? AND user_id = ? LIMIT 1");
        $own->bind_param('ii', $student_id, $authUser['user_id']);
        $own->execute();
        if ($own->get_result()->num_rows === 0) {
            apiRespond(['success' => false, 'message' => 'Access denied.'], 403);
        }
        $own->close();
        $isOwner = true;
    }

    // FIX REG-SUBJECTS-01: Scope to current semester only.
    // Accept ?semester= from caller; fall back to students.semester so that
    // old completed enrollments from previous semesters never leak through.
    $semester = trim($_GET['semester'] ?? '');
    if ($semester === '') {
        $semSt = $conn->prepare("SELECT semester FROM students WHERE id = ? LIMIT 1");
        $semSt->bind_param('i', $student_id);
        $semSt->execute();
        $semRow = $semSt->get_result()->fetch_assoc();
        $semSt->close();
        $semester = $semRow['semester'] ?? '';
    }

    // GROUP BY e.id prevents duplicate rows when a course has multiple active sections
    if ($semester !== '') {
        $stmt = $conn->prepare(
            "SELECT e.id, e.student_id, e.course_id, e.status, e.notes, e.enrollment_date, e.semester,
                    c.code AS course_code, c.name AS course_name, c.credits AS units,
                    cs.section_code AS section_name,
                    CONCAT(COALESCE(cs.day,''), ' ',
                           COALESCE(cs.time_start,''), '-',
                           COALESCE(cs.time_end,''))   AS schedule,
                    r.room_name                         AS room,
                    NULL AS final_average,
                    NULL AS grade_letter,
                    cp.prerequisite_id AS prereq_id,
                    prereq.code        AS prereq_code,
                    prereq.name        AS prereq_name
             FROM enrollments e
             JOIN courses c       ON e.course_id  = c.id
             LEFT JOIN course_sections cs ON cs.course_id = c.id AND cs.is_active = 1
             LEFT JOIN rooms r    ON r.id = cs.room_id
             LEFT JOIN course_prerequisites cp ON cp.course_id = c.id
             LEFT JOIN courses prereq           ON prereq.id = cp.prerequisite_id
             WHERE e.student_id = ? AND e.semester = ?
             GROUP BY e.id"
        );
        $stmt->bind_param('is', $student_id, $semester);
    } else {
        $stmt = $conn->prepare(
            "SELECT e.id, e.student_id, e.course_id, e.status, e.notes, e.enrollment_date, e.semester,
                    c.code AS course_code, c.name AS course_name, c.credits AS units,
                    cs.section_code AS section_name,
                    CONCAT(COALESCE(cs.day,''), ' ',
                           COALESCE(cs.time_start,''), '-',
                           COALESCE(cs.time_end,''))   AS schedule,
                    r.room_name                         AS room,
                    NULL AS final_average,
                    NULL AS grade_letter,
                    cp.prerequisite_id AS prereq_id,
                    prereq.code        AS prereq_code,
                    prereq.name        AS prereq_name
             FROM enrollments e
             JOIN courses c       ON e.course_id  = c.id
             LEFT JOIN course_sections cs ON cs.course_id = c.id AND cs.is_active = 1
             LEFT JOIN rooms r    ON r.id = cs.room_id
             LEFT JOIN course_prerequisites cp ON cp.course_id = c.id
             LEFT JOIN courses prereq           ON prereq.id = cp.prerequisite_id
             WHERE e.student_id = ?
             GROUP BY e.id"
        );
        $stmt->bind_param('i', $student_id);
    }
    $stmt->execute();
    $result      = $stmt->get_result();
    $enrollments = [];
    while ($row = $result->fetch_assoc()) {
        // PREREQ-LABEL-01: build human-readable label for the student portal
        $row['prereq_label'] = $row['prereq_id']
            ? ($row['prereq_code'] . ' - ' . $row['prereq_name'])
            : null;
        $enrollments[] = $row;
    }
    $stmt->close();

    // ── Apply privacy to every enrollment row ────────────────────────────────
    $safeEnrollments = applyPrivacyList($enrollments, $authUser, 'enrollment',
        $isOwner ? $student_id : null);

    apiRespond($safeEnrollments);
}

if ($request === 'available_courses') {
    // PREREQ-LABEL-01: Include prerequisite code + name so the student portal
    // can display a label like "Prereq: GE101 - English 1" on each subject card.
    // prereq_label is null when the course has no prerequisite.
    $stmt = $conn->prepare(
        "SELECT DISTINCT c.*,
                cs.id             AS section_id,
                cs.section_code   AS section_name,
                CONCAT(COALESCE(cs.day,''), ' ',
                       COALESCE(cs.time_start,''), '-',
                       COALESCE(cs.time_end,''))    AS schedule,
                r.room_name       AS room,
                cs.capacity,
                cp.prerequisite_id                  AS prereq_id,
                prereq.code       AS prereq_code,
                prereq.name       AS prereq_name
         FROM courses c
         JOIN course_sections cs  ON c.id = cs.course_id AND cs.is_active = 1
         LEFT JOIN rooms r        ON r.id = cs.room_id
         LEFT JOIN course_prerequisites cp ON cp.course_id = c.id
         LEFT JOIN courses prereq           ON prereq.id = cp.prerequisite_id"
    );
    $stmt->execute();
    $result  = $stmt->get_result();
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        // Human-readable label for the UI; null when no prerequisite exists
        $row['prereq_label'] = $row['prereq_id']
            ? ($row['prereq_code'] . ' - ' . $row['prereq_name'])
            : null;
        $courses[] = $row;
    }
    $stmt->close();
    // Available courses have no sensitive personal data — pass through
    apiRespond($courses);
}

// ─── ADMIN ENDPOINTS (admin role required) ─────────────────────────────────

if (in_array($request, ['all_students','all_courses','all_faculty','class_sections','audit_logs'], true)) {
    if (!$authUser || $authUser['role'] !== 'admin') {
        apiRespond(['success' => false, 'message' => 'Access denied.'], 403);
    }
}

if ($request === 'all_students') {
    $stmt = $conn->prepare(
        "SELECT s.*, u.email
         FROM students s
         JOIN users u ON s.user_id = u.id
         ORDER BY s.last_name, s.first_name"
    );
    $stmt->execute();
    $result   = $stmt->get_result();
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();

    // Admin endpoint — but still run through privacy so password/token
    // fields are stripped even if they somehow end up in the query result.
    $safeStudents = applyPrivacyList($students, $authUser, 'student');
    apiRespond($safeStudents);
}

if ($request === 'all_courses') {
    $stmt = $conn->prepare("SELECT * FROM courses ORDER BY code");
    $stmt->execute();
    $result  = $stmt->get_result();
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    $stmt->close();
    apiRespond($courses);
}

if ($request === 'all_faculty') {
    $stmt = $conn->prepare("SELECT f.* FROM faculty f ORDER BY f.last_name, f.first_name");
    $stmt->execute();
    $result  = $stmt->get_result();
    $faculty = [];
    while ($row = $result->fetch_assoc()) {
        $faculty[] = $row;
    }
    $stmt->close();

    // Strip any credential fields from faculty records
    $safeFaculty = applyPrivacyList($faculty, $authUser, 'faculty');
    apiRespond($safeFaculty);
}

if ($request === 'class_sections') {
    $stmt = $conn->prepare(
        "SELECT cs.*,
                c.code AS course_code, c.name AS course_name,
                f.first_name, f.last_name
         FROM course_sections cs
         JOIN courses c ON cs.course_id = c.id
         LEFT JOIN faculty f ON f.user_id = cs.faculty_id
         LEFT JOIN faculty fc ON fc.user_id = c.faculty_id
         ORDER BY c.code, cs.section_code"
    );
    $stmt->execute();
    $result   = $stmt->get_result();
    $sections = [];
    while ($row = $result->fetch_assoc()) {
        $sections[] = $row;
    }
    $stmt->close();
    apiRespond($sections);
}

if ($request === 'audit_logs') {
    $stmt = $conn->prepare(
        "SELECT al.* FROM audit_logs al ORDER BY al.created_at DESC LIMIT 100"
    );
    $stmt->execute();
    $result = $stmt->get_result();
    $logs   = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    $stmt->close();
    // Audit logs go to admin only (checked above). Mask IP addresses for
    // any non-admin that somehow reaches here as a defence-in-depth measure.
    $safeLogs = applyPrivacyList($logs, $authUser, 'audit');
    apiRespond($safeLogs);
}

// ─── POST ENDPOINTS ────────────────────────────────────────────────────────

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) apiRespond(['success' => false, 'message' => 'Invalid JSON'], 400);

    if ($request === 'enroll_course') {
        $student_id = (int)($data['student_id'] ?? 0);
        $course_id  = (int)($data['course_id']  ?? 0);

        if (!$student_id || !$course_id) {
            apiRespond(['success' => false, 'message' => 'student_id and course_id required'], 400);
        }

        // Ownership check: students can only enroll themselves
        if ($authUser['role'] === 'student') {
            $own = $conn->prepare("SELECT id FROM students WHERE id = ? AND user_id = ? LIMIT 1");
            $own->bind_param('ii', $student_id, $authUser['user_id']);
            $own->execute();
            if ($own->get_result()->num_rows === 0) {
                apiRespond(['success' => false, 'message' => 'Access denied.'], 403);
            }
            $own->close();
        }

        $stmt = $conn->prepare(
            "INSERT INTO enrollments (student_id, course_id, enrollment_date, status)
             VALUES (?, ?, CURDATE(), 'Enrolled')"
        );
        $stmt->bind_param('ii', $student_id, $course_id);
        if ($stmt->execute()) {
            $stmt->close();
            apiRespond(['success' => true, 'message' => 'Enrolled successfully']);
        }
        $err = $stmt->error;
        $stmt->close();
        apiRespond(['success' => false, 'message' => IS_DEV ? 'Enroll failed: ' . $err : 'Enrollment failed.'], 500);
    }

    // submit_grades — restricted to faculty, admin, registrar
    if ($request === 'submit_grades') {
        if (!in_array($authUser['role'] ?? '', ['faculty','admin','registrar'], true)) {
            apiRespond(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $enrollment_id = (int)($data['enrollment_id'] ?? 0);
        $midterm       = (float)($data['midterm_grade'] ?? 0);
        $final_grade   = (float)($data['final_grade']   ?? 0);

        if (!$enrollment_id) {
            apiRespond(['success' => false, 'message' => 'enrollment_id required'], 400);
        }

        // Validate grade range (Philippine system: 1.0 = highest, 5.0 = lowest, 3.0 = passing)
        foreach (['midterm' => $midterm, 'final' => $final_grade] as $label => $val) {
            if ($val < 1.0 || $val > 5.0) {
                apiRespond(['success' => false, 'message' => "Invalid $label grade: must be 1.00–5.00"], 422);
            }
        }

        $average = round(($midterm + $final_grade) / 2, 2);

        $eStmt = $conn->prepare("SELECT student_id, course_id, semester FROM enrollments WHERE id = ? LIMIT 1");
        $eStmt->bind_param('i', $enrollment_id);
        $eStmt->execute();
        $enr = $eStmt->get_result()->fetch_assoc();
        $eStmt->close();

        if (!$enr) {
            apiRespond(['success' => false, 'message' => 'Enrollment not found'], 404);
        }

        $submittedBy = (int)($authUser['user_id'] ?? 0);

        $stmt = $conn->prepare(
            "INSERT INTO student_grades
                 (enrollment_id, student_id, course_id, semester, term, grade, submitted_by)
             VALUES (?, ?, ?, ?, 'Final', ?, ?)
             ON DUPLICATE KEY UPDATE grade = VALUES(grade), submitted_by = VALUES(submitted_by), updated_at = NOW()"
        );
        $stmt->bind_param('iiisdi', $enrollment_id, $enr['student_id'], $enr['course_id'], $enr['semester'], $average, $submittedBy);
        $stmt->execute();
        $stmt->close();

        apiRespond(['success' => true, 'average' => $average]);
    }
}

$conn->close();