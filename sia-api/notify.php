<?php
// =============================================================================
// notify.php — Email Notifications to Parent/Guardian
//
// Sends:
//   1. Enrollment Report (list of enrolled subjects + schedule)
//   2. Statement of Account / SOA (fees, payments, balance)
//
// Requires: PHPMailer (install via Composer: composer require phpmailer/phpmailer)
// OR use the bundled SMTP alternative at the bottom if no Composer.
//
// Endpoints:
//   POST ?action=send_enrollment_report  { student_id }
//   POST ?action=send_soa                { student_id }
//   GET  ?action=email_log               { student_id }
//
// .env keys needed:
//   MAIL_HOST=smtp.gmail.com
//   MAIL_PORT=587
//   MAIL_USERNAME=youremail@gmail.com
//   MAIL_PASSWORD=your_app_password
//   MAIL_FROM_NAME=SIA School Portal
//   SCHOOL_NAME=Your School Name
//   SCHOOL_ADDRESS=123 Main St, City
//   SCHOOL_PHONE=09XX-XXX-XXXX
// =============================================================================
require_once __DIR__ . '/config.php';
applyCors();
ob_start();

require_once __DIR__ . '/auth_middleware.php';

// PHPMailer — supports both Composer autoload and manual install
$mailerLoaded = false;

// Option A: Composer autoload
$composerAutoload = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
];
foreach ($composerAutoload as $path) {
    if (file_exists($path)) {
        require_once $path;
        $mailerLoaded = true;
        break;
    }
}

// Option B: Manual install (PHPMailer files directly in vendor/phpmailer/phpmailer/src/)
if (!$mailerLoaded) {
    $srcPath = __DIR__ . '/vendor/phpmailer/phpmailer/src/';
    $files   = ['Exception.php', 'PHPMailer.php', 'SMTP.php'];
    $allFound = true;
    foreach ($files as $file) {
        if (!file_exists($srcPath . $file)) { $allFound = false; break; }
    }
    if ($allFound) {
        require_once $srcPath . 'Exception.php';
        require_once $srcPath . 'PHPMailer.php';
        require_once $srcPath . 'SMTP.php';
        $mailerLoaded = true;
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

$authUser = requireAuth($conn);
$request  = $_GET['action'] ?? '';
$method   = $_SERVER['REQUEST_METHOD'];

// Only accounting, admin, registrar can send notifications
$allowed = ['admin', 'accounting', 'registrar'];
if (!in_array($authUser['role'], $allowed, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit();
}

function notifyRespond(array $payload, int $code = 200): never {
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
}

// =============================================================================
// EMAIL HELPERS
// =============================================================================

/**
 * Log every email attempt to email_notifications table
 */
function logEmail(mysqli $conn, int $studentId, string $recipient, string $type,
                  string $subject, string $status, string $error = ''): void {
    $stmt = $conn->prepare("
        INSERT INTO email_notifications
            (student_id, recipient, type, subject, status, error_message, sent_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $sentAt = $status === 'sent' ? date('Y-m-d H:i:s') : null;
    $stmt->bind_param('issssss', $studentId, $recipient, $type, $subject, $status, $error, $sentAt);
    $stmt->execute();
    $stmt->close();
}

/**
 * Send email via PHPMailer (SMTP) or PHP mail() as fallback
 */
function sendEmail(string $toEmail, string $toName, string $subject,
                   string $htmlBody, bool $mailerLoaded): bool {
    if ($mailerLoaded) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = env('MAIL_HOST', 'smtp.gmail.com');
            $mail->SMTPAuth   = true;
            $mail->Username   = env('MAIL_USERNAME', '');
            $mail->Password   = env('MAIL_PASSWORD', '');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) env('MAIL_PORT', '587');

            $fromEmail = env('MAIL_USERNAME', 'noreply@school.edu');
            $fromName  = env('MAIL_FROM_NAME', 'SIA School Portal');

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->CharSet  = 'UTF-8';
            $mail->Subject  = $subject;
            $mail->Body     = $htmlBody;
            $mail->AltBody  = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

            $mail->send();
            return true;
        } catch (MailException $e) {
            error_log("PHPMailer error: " . $e->getMessage());
            return false;
        }
    }

    // Fallback: PHP mail() — works on XAMPP with Fake Sendmail configured
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . env('MAIL_FROM_NAME', 'SIA Portal') .
                " <" . env('MAIL_USERNAME', 'noreply@school.edu') . ">\r\n";

    return mail($toEmail, $subject, $htmlBody, $headers);
}

/**
 * Shared email wrapper: school header, footer, Philippine peso formatting
 */
function buildEmailHtml(string $title, string $bodyHtml): string {
    $schoolName    = env('SCHOOL_NAME', 'School Information System');
    $schoolAddress = env('SCHOOL_ADDRESS', '');
    $schoolPhone   = env('SCHOOL_PHONE', '');
    $year          = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body        { font-family: Arial, sans-serif; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
  .wrap       { max-width: 680px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
  .header     { background: #1a3a6b; color: #fff; padding: 28px 32px; }
  .header h1  { margin: 0; font-size: 22px; }
  .header p   { margin: 4px 0 0; font-size: 13px; opacity: .85; }
  .body       { padding: 28px 32px; }
  h2          { color: #1a3a6b; border-bottom: 2px solid #e0e7ef; padding-bottom: 8px; }
  table       { width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 14px; }
  th          { background: #1a3a6b; color: #fff; padding: 10px 12px; text-align: left; }
  td          { padding: 9px 12px; border-bottom: 1px solid #e9ecef; }
  tr:nth-child(even) td { background: #f8fafc; }
  .total-row td { font-weight: bold; background: #e0e7ef !important; }
  .badge      { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; }
  .paid       { background: #d1fae5; color: #065f46; }
  .pending    { background: #fef3c7; color: #92400e; }
  .footer     { background: #f0f4f8; padding: 16px 32px; text-align: center; font-size: 12px; color: #888; }
  .notice     { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 12px 16px; margin: 16px 0; font-size: 13px; border-radius: 4px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>🏫 {$schoolName}</h1>
    <p>{$schoolAddress} &nbsp;|&nbsp; {$schoolPhone}</p>
  </div>
  <div class="body">
    <h2>{$title}</h2>
    {$bodyHtml}
  </div>
  <div class="footer">
    &copy; {$year} {$schoolName} &mdash; This is a system-generated email. Do not reply.<br>
    For inquiries, contact the Registrar or Accounting Office.
  </div>
</div>
</body>
</html>
HTML;
}

// =============================================================================
// FETCH STUDENT + GUARDIAN HELPER
// =============================================================================
function fetchStudentWithGuardian(mysqli $conn, int $studentId): ?array {
    $st = $conn->prepare("
        SELECT s.*, u.email AS student_email,
               sg.guardian_name, sg.relationship, sg.contact AS guardian_phone,
               sg.email AS guardian_email
        FROM students s
        JOIN users u ON u.id = s.user_id
        LEFT JOIN student_guardians sg ON sg.student_id = s.id
            AND sg.email IS NOT NULL AND TRIM(sg.email) != ''
            AND sg.is_emergency = 1
        WHERE s.id = ?
        LIMIT 1
    ");
    $st->bind_param('i', $studentId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    // Fallback: if no emergency guardian with email, get any guardian with email
    if ($row && empty($row['guardian_email'])) {
        $fb = $conn->prepare("
            SELECT guardian_name, email AS guardian_email, contact AS guardian_phone, relationship
            FROM student_guardians
            WHERE student_id = ? AND email IS NOT NULL AND TRIM(email) != ''
            ORDER BY is_emergency DESC, id ASC
            LIMIT 1
        ");
        $fb->bind_param('i', $studentId);
        $fb->execute();
        $guardian = $fb->get_result()->fetch_assoc();
        $fb->close();
        if ($guardian) {
            $row['guardian_email'] = $guardian['guardian_email'];
            $row['guardian_name']  = $guardian['guardian_name'];
            $row['guardian_phone'] = $guardian['guardian_phone'];
            $row['relationship']   = $guardian['relationship'];
        }
    }

    return $row ?: null;
}

// =============================================================================
// GET: Email log for a student
// =============================================================================
if ($request === 'email_log' && $method === 'GET') {
    $studentId = (int)($_GET['student_id'] ?? 0);
    if (!$studentId) notifyRespond(['success' => false, 'message' => 'student_id required'], 400);

    $st = $conn->prepare("
        SELECT * FROM email_notifications WHERE student_id = ? ORDER BY created_at DESC LIMIT 50
    ");
    $st->bind_param('i', $studentId);
    $st->execute();
    $rows = [];
    $res  = $st->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $st->close();

    notifyRespond(['success' => true, 'data' => $rows]);
}

// =============================================================================
// POST or GET: Send Enrollment Report to Parent/Guardian
// Called POST manually from frontend, or GET fire-and-forget from registrar.php
// 'send_enrollment_confirmation' is an alias used by confirmRegistration()
// =============================================================================
if (in_array($request, ['send_enrollment_report', 'send_enrollment_confirmation'], true)) {
    $data      = json_decode(file_get_contents('php://input'), true) ?? [];
    $studentId = (int)($data['student_id'] ?? $_GET['student_id'] ?? 0);
    // Support both POST body and GET query param for fire-and-forget calls
    $studentId = (int)($data['student_id'] ?? $_GET['student_id'] ?? 0);

    if (!$studentId) notifyRespond(['success' => false, 'message' => 'student_id required'], 400);

    $student = fetchStudentWithGuardian($conn, $studentId);
    if (!$student) notifyRespond(['success' => false, 'message' => 'Student not found'], 404);

    // Determine recipients: student email + guardian email (if set)
    $recipients = [];
    if (!empty($student['student_email'])) {
        $recipients[] = ['email' => $student['student_email'], 'name' => $student['first_name'] . ' ' . $student['last_name']];
    }
    if (!empty($student['guardian_email'])) {
        $recipients[] = ['email' => $student['guardian_email'], 'name' => $student['guardian_name']];
    }

    if (empty($recipients)) {
        notifyRespond(['success' => false, 'message' => 'No email address found for student or guardian. Please add an email address in the guardian record.'], 422);
    }

    // Fetch enrolled subjects
    $enrStmt = $conn->prepare("
        SELECT e.id, c.code AS course_code, c.name AS course_name, c.credits AS units,
               cs.section_code, cs.day, cs.time_start, cs.time_end,
               r.room_name, e.status, e.semester
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN course_sections cs ON cs.course_id = c.id AND cs.is_active = 1
        LEFT JOIN rooms r ON r.id = cs.room_id
        WHERE e.student_id = ? AND e.status = 'Enrolled'
        GROUP BY e.id
        ORDER BY c.code
    ");
    $enrStmt->bind_param('i', $studentId);
    $enrStmt->execute();
    $enrollments = [];
    $totalUnits  = 0;
    $enrRes = $enrStmt->get_result();
    while ($row = $enrRes->fetch_assoc()) {
        $enrollments[] = $row;
        $totalUnits   += (int)$row['units'];
    }
    $enrStmt->close();

    if (empty($enrollments)) {
        notifyRespond(['success' => false, 'message' => 'No enrolled subjects found for this student.'], 422);
    }

    $semester    = $enrollments[0]['semester'] ?? $student['semester'];
    $studentName = $student['first_name'] . ' ' . $student['last_name'];
    $schoolName  = env('SCHOOL_NAME', 'School');
    $today       = date('F d, Y');

    // Build subject rows HTML
    $rowsHtml = '';
    foreach ($enrollments as $enr) {
        $schedule = trim(($enr['day'] ?? '') . ' ' . ($enr['time_start'] ?? '') . '-' . ($enr['time_end'] ?? ''));
        $rowsHtml .= "
        <tr>
            <td><strong>{$enr['course_code']}</strong></td>
            <td>{$enr['course_name']}</td>
            <td style='text-align:center'>{$enr['units']}</td>
            <td>{$enr['section_code']}</td>
            <td>{$schedule}</td>
            <td>{$enr['room_name']}</td>
        </tr>";
    }

    $bodyHtml = "
    <p>Dear Parent/Guardian,</p>
    <p>This is to inform you that <strong>{$studentName}</strong> has been officially enrolled for <strong>{$semester}</strong> at <strong>{$schoolName}</strong>.</p>

    <table>
      <tr>
        <th>Subject Code</th><th>Subject Name</th><th>Units</th>
        <th>Section</th><th>Schedule</th><th>Room</th>
      </tr>
      {$rowsHtml}
      <tr class='total-row'>
        <td colspan='2'><strong>Total Units</strong></td>
        <td style='text-align:center'><strong>{$totalUnits}</strong></td>
        <td colspan='3'></td>
      </tr>
    </table>

    <table style='width:auto'>
      <tr><th>Student Name</th><td>{$studentName}</td></tr>
      <tr><th>Student Number</th><td>{$student['student_number']}</td></tr>
      <tr><th>Program</th><td>{$student['program']}</td></tr>
      <tr><th>Year Level</th><td>{$student['year_level']}</td></tr>
      <tr><th>Semester</th><td>{$semester}</td></tr>
      <tr><th>Date Generated</th><td>{$today}</td></tr>
    </table>

    <div class='notice'>
      📌 <strong>Reminder:</strong> Please ensure all tuition fees are settled before the payment deadline to avoid disruption of enrollment.
      Contact the Accounting Office for your payment schedule.
    </div>

    <p>For questions or concerns, please visit the Registrar's Office or contact us at the number above.</p>
    <p>Thank you and God bless!</p>";

    $subject  = "Enrollment Confirmation — {$studentName} | {$semester}";
    $htmlBody = buildEmailHtml('Enrollment Report', $bodyHtml);

    // Send to all recipients
    $results = [];
    foreach ($recipients as $rec) {
        $sent   = sendEmail($rec['email'], $rec['name'], $subject, $htmlBody, $mailerLoaded);
        $status = $sent ? 'sent' : 'failed';
        logEmail($conn, $studentId, $rec['email'], 'enrollment_report', $subject, $status);
        $results[] = ['email' => $rec['email'], 'status' => $status];
    }

    $allSent = !in_array('failed', array_column($results, 'status'));
    notifyRespond([
        'success'    => $allSent,
        'message'    => $allSent ? 'Enrollment report sent successfully.' : 'Some emails failed to send.',
        'recipients' => $results,
        'student'    => $studentName,
        'semester'   => $semester,
    ]);
}

// =============================================================================
// POST: Send Statement of Account (SOA) to Parent/Guardian
// =============================================================================
if ($request === 'send_soa') {
    $data      = json_decode(file_get_contents('php://input'), true) ?? [];
    $studentId = (int)($data['student_id'] ?? $_GET['student_id'] ?? 0);
    if (!$studentId) notifyRespond(['success' => false, 'message' => 'student_id required'], 400);

    $student = fetchStudentWithGuardian($conn, $studentId);
    if (!$student) notifyRespond(['success' => false, 'message' => 'Student not found'], 404);

    // Determine recipients
    $recipients = [];
    if (!empty($student['student_email'])) {
        $recipients[] = ['email' => $student['student_email'], 'name' => $student['first_name'] . ' ' . $student['last_name']];
    }
    if (!empty($student['guardian_email'])) {
        $recipients[] = ['email' => $student['guardian_email'], 'name' => $student['guardian_name']];
    }
    if (empty($recipients)) {
        notifyRespond(['success' => false, 'message' => 'No email address found. Please add a guardian email first.'], 422);
    }

    // Fetch payment schedule / assessment
    $psStmt = $conn->prepare("
        SELECT * FROM payment_schedules WHERE student_id = ? ORDER BY id DESC LIMIT 1
    ");
    $psStmt->bind_param('i', $studentId);
    $psStmt->execute();
    $schedule = $psStmt->get_result()->fetch_assoc();
    $psStmt->close();

    // Fetch all payments made
    $payStmt = $conn->prepare("
        SELECT ip.or_ar_number, ip.amount, ip.payment_date, ip.payment_method,
               ip.exam_period, ip.gcash_reference, ip.or_ar_type
        FROM installment_payments ip
        WHERE ip.student_id = ?
        ORDER BY ip.payment_date ASC
    ");
    $payStmt->bind_param('i', $studentId);
    $payStmt->execute();
    $payments    = [];
    $totalPaid   = 0.0;
    $payRes = $payStmt->get_result();
    while ($row = $payRes->fetch_assoc()) {
        $payments[]  = $row;
        $totalPaid  += (float)$row['amount'];
    }
    $payStmt->close();

    // Fee breakdown from fee_config
    $feeStmt = $conn->prepare("
        SELECT fee_label, value, is_per_unit, fee_key
        FROM fee_config
        WHERE category = ? AND is_active = 1
        ORDER BY sort_order
    ");
    $category = $student['student_category'] ?? 'College';
    $feeStmt->bind_param('s', $category);
    $feeStmt->execute();
    $fees    = [];
    $feeRes  = $feeStmt->get_result();
    while ($row = $feeRes->fetch_assoc()) $fees[] = $row;
    $feeStmt->close();

    // Get total units enrolled this semester
    $unitStmt = $conn->prepare("
        SELECT COALESCE(SUM(c.credits), 0) AS total_units
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = ? AND e.status = 'Enrolled'
    ");
    $unitStmt->bind_param('i', $studentId);
    $unitStmt->execute();
    $unitRow    = $unitStmt->get_result()->fetch_assoc();
    $unitStmt->close();
    $totalUnits = (int)($unitRow['total_units'] ?? 0);

    // Compute assessment
    $totalAssessment = (float)($schedule['total_assessment'] ?? 0);
    $balance         = $totalAssessment - $totalPaid;
    $studentName     = $student['first_name'] . ' ' . $student['last_name'];
    $semester        = $student['semester'] ?? '';
    $today           = date('F d, Y');
    $schoolName      = env('SCHOOL_NAME', 'School');

    // Fee breakdown rows
    $feeRowsHtml = '';
    $feeTotal    = 0.0;
    foreach ($fees as $fee) {
        $amount = (float)$fee['value'];
        if ($fee['is_per_unit'] && $totalUnits > 0) {
            $amount *= $totalUnits;
            $label   = $fee['fee_label'] . " ({$totalUnits} units × ₱" . number_format($fee['value'], 2) . ")";
        } else {
            $label = $fee['fee_label'];
        }
        // Skip installment fee if student paid full
        if ($fee['fee_key'] === 'installment_fee' && $student['payment_plan'] !== 'installment') continue;
        $feeTotal    += $amount;
        $feeRowsHtml .= "<tr><td>{$label}</td><td style='text-align:right'>₱" . number_format($amount, 2) . "</td></tr>";
    }
    if ($totalAssessment > 0) {
        $feeRowsHtml .= "<tr class='total-row'><td><strong>Total Assessment</strong></td><td style='text-align:right'><strong>₱" . number_format($totalAssessment, 2) . "</strong></td></tr>";
    }

    // Payment history rows
    $payRowsHtml = '';
    foreach ($payments as $pay) {
        $ref = $pay['gcash_reference'] ? " ({$pay['gcash_reference']})" : '';
        $payRowsHtml .= "
        <tr>
            <td>{$pay['or_ar_number']}</td>
            <td>{$pay['exam_period']}</td>
            <td>{$pay['payment_date']}</td>
            <td>{$pay['payment_method']}{$ref}</td>
            <td style='text-align:right; color:#065f46'>₱" . number_format($pay['amount'], 2) . "</td>
        </tr>";
    }

    $balanceColor = $balance > 0 ? '#dc2626' : '#065f46';
    $balanceLabel = $balance > 0 ? 'Balance Due' : 'Overpayment';
    $badgeClass   = $balance <= 0 ? 'paid' : 'pending';
    $payStatus    = $balance <= 0 ? 'FULLY PAID' : 'WITH BALANCE';

    $bodyHtml = "
    <p>Dear Parent/Guardian of <strong>{$studentName}</strong>,</p>
    <p>Please find below the Statement of Account for <strong>{$semester}</strong>.</p>

    <table style='width:auto'>
      <tr><th>Student Name</th><td>{$studentName}</td></tr>
      <tr><th>Student Number</th><td>{$student['student_number']}</td></tr>
      <tr><th>Program</th><td>{$student['program']}</td></tr>
      <tr><th>Year Level</th><td>{$student['year_level']}</td></tr>
      <tr><th>Semester</th><td>{$semester}</td></tr>
      <tr><th>Payment Plan</th><td>" . ucfirst($student['payment_plan'] ?? 'Full') . "</td></tr>
      <tr><th>Payment Status</th><td><span class='badge {$badgeClass}'>{$payStatus}</span></td></tr>
    </table>

    <h3 style='color:#1a3a6b'>📋 Fee Assessment</h3>
    <table>
      <tr><th>Fee</th><th style='text-align:right'>Amount</th></tr>
      {$feeRowsHtml}
    </table>

    <h3 style='color:#1a3a6b'>✅ Payment History</h3>
    <table>
      <tr><th>OR/AR #</th><th>Period</th><th>Date</th><th>Method</th><th style='text-align:right'>Amount</th></tr>
      {$payRowsHtml}
      <tr class='total-row'>
        <td colspan='4'><strong>Total Paid</strong></td>
        <td style='text-align:right; color:#065f46'><strong>₱" . number_format($totalPaid, 2) . "</strong></td>
      </tr>
      <tr class='total-row'>
        <td colspan='4'><strong>{$balanceLabel}</strong></td>
        <td style='text-align:right; color:{$balanceColor}'><strong>₱" . number_format(abs($balance), 2) . "</strong></td>
      </tr>
    </table>";

    if ($balance > 0) {
        $bodyHtml .= "
    <div class='notice'>
      ⚠️ <strong>Notice:</strong> A balance of <strong>₱" . number_format($balance, 2) . "</strong> remains unpaid.
      Please settle your account at the Accounting Office to avoid being disqualified from taking examinations.
      Payment can be made via <strong>Cash</strong> or <strong>GCash</strong>.
    </div>";
    } else {
        $bodyHtml .= "
    <div class='notice' style='border-color:#10b981; background:#f0fdf4'>
      ✅ <strong>Account is fully settled.</strong> Thank you for your payment!
    </div>";
    }

    $bodyHtml .= "
    <p>Generated on: <strong>{$today}</strong></p>
    <p>For payment concerns, please contact the Accounting Office directly. Please bring this SOA and your valid ID.</p>";

    $subject  = "Statement of Account — {$studentName} | {$semester}";
    $htmlBody = buildEmailHtml('Statement of Account (SOA)', $bodyHtml);

    // Send to all recipients
    $results = [];
    foreach ($recipients as $rec) {
        $sent   = sendEmail($rec['email'], $rec['name'], $subject, $htmlBody, $mailerLoaded);
        $status = $sent ? 'sent' : 'failed';
        logEmail($conn, $studentId, $rec['email'], 'soa', $subject, $status);
        $results[] = ['email' => $rec['email'], 'status' => $status];
    }

    $allSent = !in_array('failed', array_column($results, 'status'));
    notifyRespond([
        'success'       => $allSent,
        'message'       => $allSent ? 'SOA sent successfully.' : 'Some emails failed to send.',
        'recipients'    => $results,
        'student'       => $studentName,
        'total_assessment' => $totalAssessment,
        'total_paid'    => $totalPaid,
        'balance'       => $balance,
        'semester'      => $semester,
    ]);
}

$conn->close();
notifyRespond(['success' => false, 'message' => 'Unknown action'], 400);