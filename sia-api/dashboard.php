<?php
error_reporting(0);
ini_set('display_errors', 0);
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
                s.gcash_date,
                s.semester
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

        // -- 6. Fee calculation: read from tuition_fees + installment_payments --
    $tfRes = $conn->query(
        "SELECT units, tuition_fee, miscellaneous_fee, registration_fee,
                laboratory_fee, energy_fee, subtotal, discount,
                installment_fee, total_assessment
         FROM tuition_fees WHERE student_id = $studentDbId LIMIT 1"
    );
    $tf = $tfRes ? $tfRes->fetch_assoc() : null;

    $paidRes = $conn->query(
        "SELECT COALESCE(SUM(amount), 0) AS total_paid FROM installment_payments WHERE student_id = $studentDbId"
    );
    $totalPaid = (float)(($paidRes ? $paidRes->fetch_assoc()['total_paid'] : 0) ?? 0);

    if ($tf) {
        $totalAssessment = (float)$tf['total_assessment'];
        $discount        = (float)($tf['discount'] ?? 0);
        $remainingBal    = max(0.0, $totalAssessment - $totalPaid);
        $fees = [
            'units'           => (int)$tf['units'],
            'tuitionFee'      => (float)$tf['tuition_fee'],
            'tuitionBase'     => (float)$tf['tuition_fee'],
            'miscFee'         => (float)$tf['miscellaneous_fee'],
            'registrationFee' => (float)$tf['registration_fee'],
            'laboratoryFee'   => (float)$tf['laboratory_fee'],
            'energyFee'       => (float)$tf['energy_fee'],
            'subtotal'        => (float)$tf['subtotal'],
            'discount'        => $discount,
            'scholarship'     => $discount,
            'installmentFee'  => (float)($tf['installment_fee'] ?? 0),
            'totalAssessment' => $totalAssessment,
            'totalFees'       => $totalAssessment,
            'amountPaid'      => $totalPaid,
            'remainingBal'    => $remainingBal,
            'dueDate'         => date('Y-m-d', strtotime('+30 days')),
            'paymentStatus'   => $remainingBal <= 0 ? 'Fully Paid'
                               : ($totalPaid > 0 ? 'Partial' : $student['payment_status']),
        ];
    } else {
        $tuitionBase  = $totalCredits * 650;
        $miscFee      = 6688.00;
        $regFee       = 700.00;
        $energyFee    = $totalCredits * 63;
        $totalFees    = $tuitionBase + $miscFee + $regFee + $energyFee;
        $remainingBal = max(0.0, $totalFees - $totalPaid);
        $fees = [
            'units'           => $totalCredits,
            'tuitionFee'      => $tuitionBase,
            'tuitionBase'     => $tuitionBase,
            'miscFee'         => $miscFee,
            'registrationFee' => $regFee,
            'laboratoryFee'   => 0,
            'energyFee'       => $energyFee,
            'subtotal'        => $totalFees,
            'discount'        => 0,
            'scholarship'     => 0,
            'installmentFee'  => 0,
            'totalAssessment' => $totalFees,
            'totalFees'       => $totalFees,
            'amountPaid'      => $totalPaid,
            'remainingBal'    => $remainingBal,
            'dueDate'         => date('Y-m-d', strtotime('+30 days')),
            'paymentStatus'   => $student['payment_status'],
        ];
    }

    // -- 7. Academic summary: semester from student record (set during enrollment) --
    $rawSem = trim($student['semester'] ?? '');
    if ($rawSem === '' && !empty($courses)) {
        $rawSem = $courses[0]['semester'] ?? '';
    }
    if ($rawSem === '') {
        $mo = (int)date('n'); $yr = (int)date('Y');
        $semLabel = $mo >= 6 ? '1st Semester' : '2nd Semester';
        $ayStart  = $mo >= 6 ? $yr : $yr - 1;
        $rawSem   = $semLabel . ', AY ' . $ayStart . '-' . ($ayStart + 1);
    }
    $semesterStr  = '1st Semester';
    $academicYear = date('Y') . '-' . ((int)date('Y') + 1);
    if (preg_match('/^([^,]+)/i', $rawSem, $m2))  { $semesterStr = trim($m2[1]); }
    if (preg_match('/AY\s*([\d]{4}[-][\d]{2,4})/i', $rawSem, $m)) { $academicYear = $m[1]; }

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
//  Reads from `announcements` table (auto-created with defaults if empty)
// ================================================================
if ($action === 'get_announcements') {

    // Auto-create table if not exists
    $conn->query("
        CREATE TABLE IF NOT EXISTS announcements (
            id       INT AUTO_INCREMENT PRIMARY KEY,
            title    VARCHAR(255) NOT NULL,
            message  TEXT NOT NULL,
            date     DATE NOT NULL,
            type     ENUM('enrollment','payment','school','department','system') DEFAULT 'school',
            priority ENUM('high','normal','low') DEFAULT 'normal',
            icon     VARCHAR(10) DEFAULT '📢',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Seed defaults only if table is empty
    $cnt = $conn->query("SELECT COUNT(*) AS c FROM announcements")->fetch_assoc()['c'];
    if ((int)$cnt === 0) {
        $y = date('Y');
        $conn->query("INSERT INTO announcements (title, message, date, type, priority, icon) VALUES
            ('Enrollment for 1st Semester AY $y is NOW OPEN', 'All students must complete their enrollment. Coordinate with your Academic Adviser for pre-enrollment requirements.', '$y-01-31', 'enrollment', 'high', '📋'),
            ('Tuition Fee Payment Deadline', 'Tuition fees must be paid within 30 days from enrollment. Submit your GCash or Cash payment proof through the portal.', '$y-01-31', 'payment', 'high', '💳'),
            ('Library Hours Extended', 'The university library is now open Monday–Saturday, 7:00 AM to 8:00 PM to accommodate students during enrollment.', '$y-01-28', 'school', 'normal', '🏫'),
            ('Grade Submission Portal Now Available', 'Faculty members may now submit grades through the SIA portal. Students can view their grades once submission is complete.', '$y-01-29', 'school', 'normal', '🏫'),
            ('System Maintenance — Every Sunday 12 AM–4 AM', 'The Student Information System undergoes weekly maintenance every Sunday.', '$y-01-29', 'system', 'normal', '⚙️')
        ");
    }

    $res = $conn->query("SELECT * FROM announcements ORDER BY date DESC, priority='high' DESC LIMIT 20");
    $announcements = [];
    while ($row = $res->fetch_assoc()) {
        $announcements[] = $row;
    }

    echo json_encode(['success' => true, 'announcements' => $announcements]);
    $conn->close();
    exit();
}

// ================================================================
//  ACTION: get_events
//  Reads from `school_events` table (auto-created with defaults if empty)
// ================================================================
if ($action === 'get_events') {

    // Auto-create table if not exists
    $conn->query("
        CREATE TABLE IF NOT EXISTS school_events (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            title       VARCHAR(255) NOT NULL,
            event_date  DATE NOT NULL,
            type        ENUM('enrollment','payment','exam','activity','holiday') DEFAULT 'activity',
            description TEXT,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Seed defaults only if table is empty
    $cnt = $conn->query("SELECT COUNT(*) AS c FROM school_events")->fetch_assoc()['c'];
    if ((int)$cnt === 0) {
        $y = date('Y');
        $conn->query("INSERT INTO school_events (title, event_date, type, description) VALUES
            ('Enrollment Period Opens',    '$y-01-20', 'enrollment', '1st Semester enrollment starts'),
            ('Enrollment Deadline',        '$y-01-31', 'enrollment', 'Last day to enroll without late penalty'),
            ('Tuition Payment Deadline',   '$y-02-28', 'payment',    'Pay tuition to avoid holds on your account'),
            ('University Sports Fest',     '$y-02-14', 'activity',   'Annual inter-department sports festival'),
            ('Midterm Examinations',       '$y-03-10', 'exam',       'Midterm exam week begins — all departments'),
            ('Midterm Exams End',          '$y-03-14', 'exam',       'Last day of midterm examinations'),
            ('Foundation Day (No Classes)','$y-03-25', 'holiday',    'University Foundation Day — school holiday'),
            ('Araw ng Kagitingan',         '$y-04-09', 'holiday',    'Day of Valor — national holiday'),
            ('Holy Thursday',              '$y-04-17', 'holiday',    'Holy Week — school suspended'),
            ('Good Friday',                '$y-04-18', 'holiday',    'Holy Week — school suspended'),
            ('Final Examinations Begin',   '$y-05-05', 'exam',       'Final examination period starts'),
            ('Final Examinations End',     '$y-05-09', 'exam',       'Last day of final examinations'),
            ('Official Grades Released',   '$y-05-20', 'activity',   'Final grades viewable via student portal'),
            ('Enrollment — 2nd Semester',  '$y-06-01', 'enrollment', 'Enrollment opens for 2nd Semester'),
            ('Independence Day',           '$y-06-12', 'holiday',    'Philippine Independence Day — no classes')
        ");
    }

    $res = $conn->query("SELECT * FROM school_events ORDER BY event_date ASC");
    $events = [];
    while ($row = $res->fetch_assoc()) {
        $events[] = $row;
    }

    echo json_encode(['success' => true, 'events' => $events]);
    $conn->close();
    exit();
}

// ── Unknown action ────────────────────────────────────────────
echo json_encode(['success' => false, 'message' => "Unknown action: '$action'"]);
$conn->close();
?>