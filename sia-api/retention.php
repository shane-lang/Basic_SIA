<?php
// =============================================================================
// retention.php — Student Data Retention & Archiving API
//
// Compliant with RA 10173 (Data Privacy Act of 2012)
// Only admin role can trigger archiving and anonymization.
//
// Endpoints:
//   GET  ?action=list_archived          — list archived students
//   GET  ?action=due_anonymization      — students whose 10 years is up
//   POST ?action=archive_student        — archive a student (soft delete)
//   POST ?action=anonymize_student      — anonymize PII after 10 years
//   POST ?action=bulk_archive           — archive multiple students
// =============================================================================
require_once __DIR__ . '/config.php';
applyCors();
ob_start();

require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/audit_helper.php';

$authUser = requireAuth($conn);
$request  = $_GET['action'] ?? '';
$method   = $_SERVER['REQUEST_METHOD'];

// All retention actions are admin-only
if ($authUser['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin only.']);
    exit();
}

function retentionRespond(array $payload, int $code = 200): never {
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
}

// =============================================================================
// GET: List archived students
// =============================================================================
if ($request === 'list_archived' && $method === 'GET') {
    $stmt = $conn->prepare("
        SELECT sal.*, s.enrollment_status, s.is_anonymized AS current_anon
        FROM student_archive_log sal
        LEFT JOIN students s ON s.id = sal.student_id
        ORDER BY sal.archived_at DESC
        LIMIT 200
    ");
    $stmt->execute();
    $rows = [];
    $res  = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    retentionRespond(['success' => true, 'data' => $rows]);
}

// =============================================================================
// GET: Students whose 10-year retention period has expired (due anonymization)
// =============================================================================
if ($request === 'due_anonymization' && $method === 'GET') {
    $stmt = $conn->prepare("
        SELECT *
        FROM v_students_due_anonymization
        WHERE days_remaining <= 0
    ");
    $stmt->execute();
    $rows = [];
    $res  = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    retentionRespond(['success' => true, 'data' => $rows, 'count' => count($rows)]);
}

// =============================================================================
// GET: Students whose 10-year period will expire within N days (early warning)
// =============================================================================
if ($request === 'expiring_soon' && $method === 'GET') {
    $days = max(1, (int)($_GET['days'] ?? 365)); // default: warn 1 year before
    $stmt = $conn->prepare("
        SELECT *
        FROM v_students_due_anonymization
        WHERE days_remaining BETWEEN 0 AND ?
        ORDER BY days_remaining ASC
    ");
    $stmt->bind_param('i', $days);
    $stmt->execute();
    $rows = [];
    $res  = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    retentionRespond(['success' => true, 'data' => $rows]);
}

// =============================================================================
// POST: Archive a single student
// Body: { student_id, reason }
// =============================================================================
if ($request === 'archive_student' && $method === 'POST') {
    $data       = json_decode(file_get_contents('php://input'), true) ?? [];
    $studentId  = (int)($data['student_id'] ?? 0);
    $reason     = trim($data['reason'] ?? 'Archived by admin');

    if (!$studentId) retentionRespond(['success' => false, 'message' => 'student_id required'], 400);

    // Fetch student
    $st = $conn->prepare("
        SELECT s.*, u.email
        FROM students s
        JOIN users u ON u.id = s.user_id
        WHERE s.id = ? AND s.archived_at IS NULL
        LIMIT 1
    ");
    $st->bind_param('i', $studentId);
    $st->execute();
    $student = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$student) retentionRespond(['success' => false, 'message' => 'Student not found or already archived'], 404);

    // Determine last active year from latest enrollment
    $lyStmt = $conn->prepare("
        SELECT MAX(YEAR(enrollment_date)) AS last_year FROM enrollments WHERE student_id = ?
    ");
    $lyStmt->bind_param('i', $studentId);
    $lyStmt->execute();
    $lyRow      = $lyStmt->get_result()->fetch_assoc();
    $lyStmt->close();
    $lastYear   = $lyRow['last_year'] ?? date('Y');

    // Anonymize date = last active year + 10 years
    $scheduleAnon = ($lastYear + 10) . '-12-31'; // End of that calendar year

    // Create archive log entry
    $fullName = $student['first_name'] . ' ' .
                ($student['middle_name'] ? $student['middle_name'] . ' ' : '') .
                $student['last_name'];
    $archivedAt = date('Y-m-d H:i:s');
    $adminId    = $authUser['user_id'];

    $ins = $conn->prepare("
        INSERT INTO student_archive_log
            (student_id, student_number, full_name, program, year_level,
             last_active_year, archive_reason, archived_by, archived_at,
             scheduled_anonymize_at, is_anonymized)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
    ");
    $ins->bind_param('issssissss',
        $studentId,
        $student['student_number'],
        $fullName,
        $student['program'],
        $student['year_level'],
        $lastYear,
        $reason,
        $adminId,
        $archivedAt,
        $scheduleAnon
    );
    $ins->execute();
    $ins->close();

    // Soft-delete: mark archived_at on students table
    $upd = $conn->prepare("
        UPDATE students
        SET archived_at      = ?,
            archived_by      = ?,
            archive_reason   = ?,
            last_active_year = ?,
            enrollment_status = 'Dropped'
        WHERE id = ?
    ");
    $upd->bind_param('sisii', $archivedAt, $adminId, $reason, $lastYear, $studentId);
    $upd->execute();
    $upd->close();

    // Audit log
    logAudit($conn, $authUser, 'ARCHIVE_STUDENT', 'student', $studentId,
        "Student archived: $fullName ({$student['student_number']}). " .
        "Reason: $reason. Scheduled anonymization: $scheduleAnon",
        ['enrollment_status' => $student['enrollment_status']],
        ['archived_at' => $archivedAt, 'schedule_anonymize' => $scheduleAnon]
    );

    retentionRespond([
        'success'              => true,
        'message'              => 'Student archived successfully.',
        'student_number'       => $student['student_number'],
        'full_name'            => $fullName,
        'scheduled_anonymize'  => $scheduleAnon,
        'note'                 => 'Academic records (grades, TOR) are retained permanently per CHED policy. Personal data will be anonymized on ' . $scheduleAnon . ' per RA 10173.',
    ]);
}

// =============================================================================
// POST: Anonymize a student's PII (after 10-year retention period)
// Body: { student_id }
// This is IRREVERSIBLE. Only run when days_remaining <= 0.
// =============================================================================
if ($request === 'anonymize_student' && $method === 'POST') {
    $data      = json_decode(file_get_contents('php://input'), true) ?? [];
    $studentId = (int)($data['student_id'] ?? 0);

    if (!$studentId) retentionRespond(['success' => false, 'message' => 'student_id required'], 400);

    // Must be already archived
    $chk = $conn->prepare("
        SELECT id, student_number, archived_at, last_active_year, is_anonymized
        FROM students WHERE id = ? AND archived_at IS NOT NULL LIMIT 1
    ");
    $chk->bind_param('i', $studentId);
    $chk->execute();
    $student = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$student) retentionRespond(['success' => false, 'message' => 'Student must be archived before anonymization'], 422);
    if ($student['is_anonymized']) retentionRespond(['success' => false, 'message' => 'Already anonymized'], 422);

    // Verify 10-year period has passed
    $lastYear = (int)($student['last_active_year'] ?? 0);
    if ($lastYear > 0 && (date('Y') - $lastYear) < 10) {
        retentionRespond([
            'success' => false,
            'message' => "10-year retention period not yet reached. Last active: $lastYear. Eligible in: " . ($lastYear + 10) . ".",
        ], 422);
    }

    $anonTag  = 'ANONYMIZED-' . $student['student_number'];
    $nowDt    = date('Y-m-d H:i:s');
    $adminId  = $authUser['user_id'];

    // Wipe PII from students table — keep student_number, program, grades intact
    $wipe = $conn->prepare("
        UPDATE students SET
            first_name             = '[Anonymized]',
            last_name              = '[Anonymized]',
            middle_name            = NULL,
            suffix                 = NULL,
            lrn_no                 = NULL,
            psa_birth_cert_no      = NULL,
            date_of_birth          = NULL,
            phone                  = NULL,
            address                = NULL,
            place_of_birth         = NULL,
            religion               = NULL,
            citizenship            = NULL,
            mother_tongue          = NULL,
            emergency_contact      = NULL,
            emergency_phone        = NULL,
            profile_picture        = NULL,
            tor_file               = NULL,
            psa_file               = NULL,
            last_school_attended   = NULL,
            strand                 = NULL,
            special_needs_details  = NULL,
            assistive_tech_details = NULL,
            is_anonymized          = 1,
            anonymized_at          = ?
        WHERE id = ?
    ");
    $wipe->bind_param('si', $nowDt, $studentId);
    $wipe->execute();
    $wipe->close();

    // Wipe PII from student_guardians
    $wipeSg = $conn->prepare("
        UPDATE student_guardians SET
            guardian_name = '[Anonymized]',
            address       = NULL,
            contact       = NULL,
            email         = NULL
        WHERE student_id = ?
    ");
    $wipeSg->bind_param('i', $studentId);
    $wipeSg->execute();
    $wipeSg->close();

    // Anonymize the user account email
    $wipeUser = $conn->prepare("
        UPDATE users SET email = ? WHERE id = (SELECT user_id FROM students WHERE id = ?)
    ");
    $wipeUser->bind_param('si', $anonTag, $studentId);
    $wipeUser->execute();
    $wipeUser->close();

    // Mark archive log
    $updLog = $conn->prepare("
        UPDATE student_archive_log
        SET is_anonymized = 1, anonymized_at = ?
        WHERE student_id = ? AND is_anonymized = 0
    ");
    $updLog->bind_param('si', $nowDt, $studentId);
    $updLog->execute();
    $updLog->close();

    // Audit
    logAudit($conn, $authUser, 'ANONYMIZE_STUDENT', 'student', $studentId,
        "PII anonymized for student {$student['student_number']} (RA 10173 compliance). " .
        "Academic records retained.",
        null,
        ['anonymized_at' => $nowDt, 'triggered_by' => $adminId]
    );

    retentionRespond([
        'success'    => true,
        'message'    => 'Student PII anonymized successfully.',
        'student_number' => $student['student_number'],
        'anonymized_at'  => $nowDt,
        'note'       => 'Academic records (grades, transcript) are permanently retained per CHED policy. All personal identifiers have been removed per RA 10173.',
    ]);
}

// =============================================================================
// POST: Bulk archive — archive all students inactive for X years
// Body: { years_inactive: 10, reason: "..." }
// =============================================================================
if ($request === 'bulk_archive' && $method === 'POST') {
    $data          = json_decode(file_get_contents('php://input'), true) ?? [];
    $yearsInactive = max(1, (int)($data['years_inactive'] ?? 10));
    $reason        = trim($data['reason'] ?? "Bulk archive: inactive for $yearsInactive+ years");
    $cutoffYear    = date('Y') - $yearsInactive;

    // Find students whose last enrollment was before cutoff year and not yet archived
    $find = $conn->prepare("
        SELECT s.id, s.student_number,
               CONCAT(s.first_name, ' ', s.last_name) AS full_name,
               s.program, s.year_level, s.user_id,
               MAX(YEAR(e.enrollment_date)) AS last_enroll_year
        FROM students s
        LEFT JOIN enrollments e ON e.student_id = s.id
        WHERE s.archived_at IS NULL
        GROUP BY s.id
        HAVING last_enroll_year <= ? OR last_enroll_year IS NULL
    ");
    $find->bind_param('i', $cutoffYear);
    $find->execute();
    $toArchive = [];
    $res = $find->get_result();
    while ($r = $res->fetch_assoc()) $toArchive[] = $r;
    $find->close();

    if (empty($toArchive)) {
        retentionRespond(['success' => true, 'message' => 'No students found matching criteria.', 'archived_count' => 0]);
    }

    $archivedAt = date('Y-m-d H:i:s');
    $adminId    = $authUser['user_id'];
    $count      = 0;

    foreach ($toArchive as $student) {
        $sid       = (int)$student['id'];
        $lastYear  = (int)($student['last_enroll_year'] ?? date('Y'));
        $schedAnon = ($lastYear + 10) . '-12-31';

        // Insert archive log
        $ins = $conn->prepare("
            INSERT IGNORE INTO student_archive_log
                (student_id, student_number, full_name, program, year_level,
                 last_active_year, archive_reason, archived_by, archived_at,
                 scheduled_anonymize_at, is_anonymized)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
        ");
        $ins->bind_param('issssissss',
            $sid, $student['student_number'], $student['full_name'],
            $student['program'], $student['year_level'],
            $lastYear, $reason, $adminId, $archivedAt, $schedAnon
        );
        $ins->execute();
        $ins->close();

        // Soft delete
        $upd = $conn->prepare("
            UPDATE students
            SET archived_at = ?, archived_by = ?, archive_reason = ?,
                last_active_year = ?, enrollment_status = 'Dropped'
            WHERE id = ? AND archived_at IS NULL
        ");
        $upd->bind_param('sisii', $archivedAt, $adminId, $reason, $lastYear, $sid);
        $upd->execute();
        $upd->close();

        $count++;
    }

    logAudit($conn, $authUser, 'BULK_ARCHIVE_STUDENTS', 'student', null,
        "Bulk archived $count students inactive since $cutoffYear or earlier. Reason: $reason",
        ['years_inactive' => $yearsInactive],
        ['archived_count' => $count, 'archived_at' => $archivedAt]
    );

    retentionRespond([
        'success'        => true,
        'message'        => "Successfully archived $count student(s).",
        'archived_count' => $count,
        'cutoff_year'    => $cutoffYear,
    ]);
}

$conn->close();
retentionRespond(['success' => false, 'message' => 'Unknown action'], 400);