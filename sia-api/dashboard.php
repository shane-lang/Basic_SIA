<?php
// ================================================================
//  dashboard.php  —  Student Dashboard API
//  Place in: C:\xampp\htdocs\sia-api\dashboard.php
//
//  Endpoints:
//    GET ?action=get_dashboard&student_id=X   (preferred)
//    GET ?action=get_dashboard&user_id=X      (fallback via user table)
//    GET ?action=get_announcements
//    GET ?action=get_events
// ================================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

// ── DB CONNECTION ─────────────────────────────────────────────
$conn = new mysqli('localhost', 'root', '', 'sia_db');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed: ' . $conn->connect_error]);
    exit();
}
$conn->set_charset('utf8mb4');

$action = $_GET['action'] ?? '';

// ================================================================
//  ACTION: get_dashboard
//  Returns: student info, enrolled courses, fees, payment history,
//           next class, academic summary
// ================================================================
if ($action === 'get_dashboard') {

    // ── Resolve which column to query ────────────────────────
    if (!empty($_GET['student_id'])) {
        $param    = (int) $_GET['student_id'];
        $whereCol = 's.id';
    } elseif (!empty($_GET['user_id'])) {
        $param    = (int) $_GET['user_id'];
        $whereCol = 's.user_id';
    } else {
        echo json_encode(['success' => false, 'message' => 'Provide student_id or user_id']);
        exit();
    }

    // ── 1. Student base info ─────────────────────────────────
    $stmt = $conn->prepare(
        "SELECT s.id                    AS dbId,
                s.student_number,
                s.first_name,
                s.last_name,
                s.email,
                s.phone,
                s.program,
                s.year_level,
                s.gpa,
                s.enrollment_status,
                s.payment_status,
                s.approval_status,
                s.student_type,
                s.enrollment_date,
                s.payment_method,
                s.gcash_amount,
                s.gcash_reference,
                s.gcash_transaction_id,
                s.gcash_date
         FROM students s
         WHERE $whereCol = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $param);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit();
    }

    $studentDbId = (int) $student['dbId'];

    // ── 2. Enrolled courses (Enrolled + Pending statuses) ────
    $stmt = $conn->prepare(
        "SELECT c.id,
                c.code,
                c.name,
                c.credits,
                c.instructor,
                c.schedule,
                c.day,
                c.time,
                c.room,
                c.semester,
                c.description,
                c.department,
                e.status          AS enrollment_status,
                e.grade,
                e.enrollment_date AS enrolled_on
         FROM enrollments e
         JOIN courses c ON e.course_id = c.id
         WHERE e.student_id = ?
           AND e.status IN ('Enrolled', 'Pending')
         ORDER BY c.code ASC"
    );
    $stmt->bind_param('i', $studentDbId);
    $stmt->execute();
    $res = $stmt->get_result();
    $courses = [];
    while ($row = $res->fetch_assoc()) {
        $courses[] = $row;
    }
    $stmt->close();

    // ── 3. Total units / credits ─────────────────────────────
    $totalCredits = (int) array_sum(array_column($courses, 'credits'));

    // ── 4. Determine next class (closest upcoming day) ───────
    $nextClass = null;
    $dayIsoMap = [
        'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
        'thursday' => 4, 'friday' => 5, 'saturday' => 6, 'sunday' => 7,
    ];
    $todayIso = (int) date('N'); // 1=Mon … 7=Sun

    $getDayDiff = function(string $dayStr) use ($dayIsoMap, $todayIso): int {
        $parts = array_map('trim', explode(',', strtolower($dayStr)));
        $best  = 99;
        foreach ($parts as $p) {
            foreach ($dayIsoMap as $name => $iso) {
                if (strpos($name, $p) === 0 || strpos($p, $name) === 0) {
                    $diff = ($iso - $todayIso + 7) % 7;
                    if ($diff < $best) $best = $diff;
                }
            }
        }
        return $best;
    };

    if (!empty($courses)) {
        $sorted = $courses;
        usort($sorted, function($a, $b) use ($getDayDiff) {
            return $getDayDiff($a['day'] ?? '') <=> $getDayDiff($b['day'] ?? '');
        });
        $nextClass = $sorted[0];
    }

    // ── 5. Payment history from payment_logs ─────────────────
    $stmt = $conn->prepare(
        "SELECT id,
                payment_method  AS method,
                gcash_reference AS reference,
                gcash_amount    AS amount,
                gcash_date      AS date,
                transaction_id,
                semester,
                status,
                notes,
                created_at
         FROM payment_logs
         WHERE student_id = ?
         ORDER BY created_at DESC"
    );
    $stmt->bind_param('i', $studentDbId);
    $stmt->execute();
    $res = $stmt->get_result();
    $paymentHistory = [];
    while ($row = $res->fetch_assoc()) {
        $paymentHistory[] = $row;
    }
    $stmt->close();

    // ── 6. Fee calculation ───────────────────────────────────
    // School fee rates — adjust per institution policy
    $tuitionPerUnit = 650;    // ₱650 per credit unit
    $miscFee        = 1500;   // flat miscellaneous fee
    $tuitionBase    = $totalCredits * $tuitionPerUnit;
    $totalFees      = $tuitionBase + $miscFee;

    // Sum verified payments from payment_logs
    $amountPaid = 0.0;
    foreach ($paymentHistory as $p) {
        if ($p['status'] === 'Verified') {
            $amountPaid += (float) $p['amount'];
        }
    }

    // Fallback: use student.gcash_amount if no payment_logs amount found
    if ($amountPaid === 0.0 && !empty($student['gcash_amount'])) {
        $amountPaid = (float) $student['gcash_amount'];
    }

    $remainingBal = max(0.0, $totalFees - $amountPaid);

    $fees = [
        'tuitionBase'   => $tuitionBase,
        'miscFee'       => $miscFee,
        'totalFees'     => $totalFees,
        'scholarship'   => 0,
        'amountPaid'    => $amountPaid,
        'remainingBal'  => $remainingBal,
        'dueDate'       => date('Y-m-d', strtotime('+30 days')),
        'paymentStatus' => $student['payment_status'],  // from students table
    ];

    // ── 7. Academic summary ──────────────────────────────────
    $semesterStr  = '1st Semester';
    $academicYear = '2024–2025';

    if (!empty($courses)) {
        $rawSem = $courses[0]['semester'] ?? '';
        // e.g. "1st Semester, AY 2024-2025"
        if (preg_match('/AY\s*([\d\-–]+)/i', $rawSem, $m)) {
            $academicYear = $m[1];
        }
        if (preg_match('/^([^,]+)/i', $rawSem, $m2)) {
            $semesterStr = trim($m2[1]);
        }
    }

    $academic = [
        'yearLevel'    => $student['year_level'],
        'gpa'          => (float) $student['gpa'],
        'totalCredits' => $totalCredits,
        'courseCount'  => count($courses),
        'status'       => $student['enrollment_status'],
        'semester'     => $semesterStr,
        'academicYear' => $academicYear,
    ];

    // ── 8. Send response ─────────────────────────────────────
    echo json_encode([
        'success'        => true,
        'student'        => [
            'dbId'             => $student['dbId'],
            'id'               => $student['student_number'],    // e.g. STU-2026-0005
            'firstName'        => $student['first_name'],
            'lastName'         => $student['last_name'],
            'email'            => $student['email'],
            'phone'            => $student['phone'],
            'program'          => $student['program'],
            'enrollmentStatus' => $student['enrollment_status'],
            'paymentStatus'    => $student['payment_status'],
            'approvalStatus'   => $student['approval_status'],
            'studentType'      => $student['student_type'],
            'enrollmentDate'   => $student['enrollment_date'],
        ],
        'academic'       => $academic,
        'courses'        => $courses,       // all enrolled courses (for schedule + course list)
        'nextClass'      => $nextClass,     // nearest upcoming class
        'fees'           => $fees,          // financial breakdown
        'paymentHistory' => $paymentHistory // all payment records
    ]);

    $conn->close();
    exit();
}

// ================================================================
//  ACTION: get_announcements
//  Static school announcements — replace with a DB table later
// ================================================================
if ($action === 'get_announcements') {

    $announcements = [
        [
            'id'       => 1,
            'title'    => 'Enrollment for 1st Semester AY 2024-2025 is NOW OPEN',
            'message'  => 'All students must complete their enrollment before January 31, 2026. '
                        . 'Coordinate with your Academic Adviser for pre-enrollment requirements.',
            'date'     => '2026-01-31',
            'type'     => 'enrollment',
            'priority' => 'high',
            'icon'     => '📋',
        ],
        [
            'id'       => 2,
            'title'    => 'Tuition Fee Payment Deadline — January 31, 2026',
            'message'  => 'Tuition fees must be paid within 30 days from enrollment. '
                        . 'Submit your GCash or Cash payment proof through the portal.',
            'date'     => '2026-01-31',
            'type'     => 'payment',
            'priority' => 'high',
            'icon'     => '💳',
        ],
        [
            'id'       => 3,
            'title'    => 'IT Department New Student Orientation',
            'message'  => 'All new BSIT students are required to attend the department orientation '
                        . 'scheduled on February 3, 2026, 9:00 AM at the IT Auditorium. Attendance is mandatory.',
            'date'     => '2026-01-30',
            'type'     => 'department',
            'priority' => 'high',
            'icon'     => '📚',
        ],
        [
            'id'       => 4,
            'title'    => 'Library Hours Extended',
            'message'  => 'The university library is now open Monday–Saturday, 7:00 AM to 8:00 PM '
                        . 'to accommodate students during the enrollment and early semester period.',
            'date'     => '2026-01-28',
            'type'     => 'school',
            'priority' => 'normal',
            'icon'     => '🏫',
        ],
        [
            'id'       => 5,
            'title'    => 'Grade Submission Portal Now Available',
            'message'  => 'Faculty members may now submit grades through the SIA portal. '
                        . 'Students can view their grades once submission is complete.',
            'date'     => '2026-01-29',
            'type'     => 'school',
            'priority' => 'normal',
            'icon'     => '🏫',
        ],
        [
            'id'       => 6,
            'title'    => 'System Maintenance — Every Sunday 12 AM–4 AM',
            'message'  => 'The Student Information System undergoes weekly maintenance every Sunday. '
                        . 'Please complete transactions before midnight Saturday.',
            'date'     => '2026-01-29',
            'type'     => 'system',
            'priority' => 'normal',
            'icon'     => '⚙️',
        ],
    ];

    echo json_encode(['success' => true, 'announcements' => $announcements]);
    $conn->close();
    exit();
}

// ================================================================
//  ACTION: get_events
//  School calendar events — add a `school_events` DB table later
// ================================================================
if ($action === 'get_events') {

    $y = date('Y');

    $events = [
        // Enrollment
        ['id' =>  1, 'title' => 'Enrollment Period Opens',      'event_date' => "$y-01-20", 'type' => 'enrollment', 'description' => '1st Semester AY 2024-2025 enrollment starts'],
        ['id' =>  2, 'title' => 'Enrollment Deadline',          'event_date' => "$y-01-31", 'type' => 'enrollment', 'description' => 'Last day to enroll without late penalty'],

        // Payment
        ['id' =>  3, 'title' => 'Tuition Payment Deadline',     'event_date' => "$y-02-28", 'type' => 'payment',    'description' => 'Pay tuition to avoid holds on your account'],

        // Activities
        ['id' =>  4, 'title' => 'IT Dept Orientation',          'event_date' => "$y-02-03", 'type' => 'activity',   'description' => 'New student orientation for BSIT freshmen — 9 AM, IT Auditorium'],
        ['id' =>  5, 'title' => 'University Sports Fest',       'event_date' => "$y-02-14", 'type' => 'activity',   'description' => 'Annual inter-department sports festival'],

        // Exams
        ['id' =>  6, 'title' => 'Midterm Examinations',         'event_date' => "$y-03-10", 'type' => 'exam',       'description' => 'Midterm exam week begins — all departments'],
        ['id' =>  7, 'title' => 'Midterm Exams End',            'event_date' => "$y-03-14", 'type' => 'exam',       'description' => 'Last day of midterm examinations'],

        // Holidays
        ['id' =>  8, 'title' => 'Foundation Day (No Classes)',  'event_date' => "$y-03-25", 'type' => 'holiday',    'description' => 'University Foundation Day — school holiday'],
        ['id' =>  9, 'title' => 'Araw ng Kagitingan',           'event_date' => "$y-04-09", 'type' => 'holiday',    'description' => 'Day of Valor — national holiday'],
        ['id' => 10, 'title' => 'Holy Thursday',                'event_date' => "$y-04-17", 'type' => 'holiday',    'description' => 'Holy Week — school suspended'],
        ['id' => 11, 'title' => 'Good Friday',                  'event_date' => "$y-04-18", 'type' => 'holiday',    'description' => 'Holy Week — school suspended'],
        ['id' => 12, 'title' => 'Black Saturday',               'event_date' => "$y-04-19", 'type' => 'holiday',    'description' => 'Holy Week — school suspended'],

        // Final Exams
        ['id' => 13, 'title' => 'Final Examinations Begin',     'event_date' => "$y-05-05", 'type' => 'exam',       'description' => 'Final examination period starts'],
        ['id' => 14, 'title' => 'Final Examinations End',       'event_date' => "$y-05-09", 'type' => 'exam',       'description' => 'Last day of final examinations'],

        // Grade release & 2nd sem
        ['id' => 15, 'title' => 'Official Grades Released',     'event_date' => "$y-05-20", 'type' => 'activity',   'description' => 'Final grades viewable via student portal'],
        ['id' => 16, 'title' => 'Enrollment — 2nd Semester',    'event_date' => "$y-06-01", 'type' => 'enrollment', 'description' => 'Enrollment opens for 2nd Semester AY 2024-2025'],
        ['id' => 17, 'title' => 'Independence Day',             'event_date' => "$y-06-12", 'type' => 'holiday',    'description' => 'Philippine Independence Day — no classes'],
    ];

    echo json_encode(['success' => true, 'events' => $events]);
    $conn->close();
    exit();
}

// ── Unknown action ────────────────────────────────────────────
echo json_encode(['success' => false, 'message' => "Unknown action: '$action'"]);
$conn->close();
?>