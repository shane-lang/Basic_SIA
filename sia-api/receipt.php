<?php
// =============================================================================
// receipt.php — Service Invoice (online/browser) + OR/AR Receipt (print)
//
// DOCUMENT TYPES:
//   • Service Invoice  — shown online/browser BEFORE or AFTER payment.
//                        This is what the student sees on-screen. It lists
//                        what is being charged, NOT a proof of payment.
//   • Official Receipt / Acknowledgement Receipt (OR/AR)
//                      — the physical/printable document issued AFTER payment
//                        is recorded. This is the legal proof of payment.
//
// WHY THE DISTINCTION:
//   BIR rules and standard accounting practice treat "receipt" as a proof
//   of payment (cash/GCash received). Before payment, or when viewing online,
//   what you're showing is a billing statement / service invoice.
//
// ENDPOINTS:
//   GET ?action=get_printable&payment_id=XX     → OR/AR receipt HTML (print)
//   GET ?action=get_invoice&payment_id=XX       → Service Invoice HTML (online view)
//   GET ?action=verify&id=XX&token=XXXX         → QR scan verification page
//   GET ?action=get_token&payment_id=XX         → JSON with token (for Angular)
//   POST ?action=sign_payment  body:{payment_id}→ generate and store token
// =============================================================================

require_once __DIR__ . '/config.php';
applyCors();

$action = $_GET['action'] ?? 'verify';

// ── Token generation ───────────────────────────────────────────────────────────
function generateReceiptToken(int $payment_id, string $or_ar_number, float $amount, int $student_id): string {
    $secret = env('APP_SECRET', '');
    if ($secret === '') {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server configuration error: APP_SECRET not set.']);
        exit();
    }
    $payload = "$payment_id|$or_ar_number|$amount|$student_id";
    return hash_hmac('sha256', $payload, $secret);
}

function verifyReceiptToken(string $token, int $payment_id, string $or_ar_number, float $amount, int $student_id): bool {
    $expected = generateReceiptToken($payment_id, $or_ar_number, $amount, $student_id);
    return hash_equals($expected, $token);
}

// ── Helper: fetch and sign payment ────────────────────────────────────────────
function getAndSignPayment(mysqli $conn, int $payment_id): ?array {
    $stmt = $conn->prepare("
        SELECT ip.*, s.student_number, s.first_name, s.last_name, s.middle_name,
               s.program, s.year_level, s.semester, s.student_category, s.payment_plan,
               tf.total_assessment, tf.tuition_fee, tf.miscellaneous_fee,
               tf.registration_fee, tf.laboratory_fee, tf.energy_fee,
               tf.subtotal, tf.discount, tf.installment_fee,
               COALESCE(sp.first_name, fac.first_name, stu.first_name, '') AS cashier_first,
               COALESCE(sp.last_name,  fac.last_name,  stu.last_name,  '') AS cashier_last,
               COALESCE(
                   (SELECT SUM(ip2.amount) FROM installment_payments ip2 WHERE ip2.student_id = s.id),
                   0
               ) AS total_paid
        FROM installment_payments ip
        JOIN students s ON ip.student_id = s.id
        LEFT JOIN tuition_fees tf ON tf.student_id = s.id
        LEFT JOIN users u ON ip.recorded_by = u.id
        LEFT JOIN staff_profiles sp  ON sp.user_id  = u.id
        LEFT JOIN faculty        fac ON fac.user_id = u.id
        LEFT JOIN students       stu ON stu.user_id = u.id
        WHERE ip.id = ?
    ");
    $stmt->bind_param('i', $payment_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return null;

    // Generate token if not yet signed
    if (empty($row['receipt_token'])) {
        $token = generateReceiptToken($payment_id, $row['or_ar_number'], (float)$row['amount'], (int)$row['student_id']);
        $updStmt = $conn->prepare("UPDATE installment_payments SET receipt_token = ?, receipt_signed_at = NOW() WHERE id = ?");
        $updStmt->bind_param('si', $token, $payment_id);
        $updStmt->execute();
        $updStmt->close();
        $row['receipt_token']     = $token;
        $row['receipt_signed_at'] = date('Y-m-d H:i:s');
    }
    return $row;
}

// ── School info (configurable via sys_config) ─────────────────────────────────
function getSchoolInfo(mysqli $conn): array {
    $res = $conn->query("SELECT config_key, config_value FROM sys_config WHERE config_key LIKE 'school_%'");
    $info = [
        'name'    => 'St. Benilde Center for Global Competence, Inc.',
        'address' => '#2647 Rizal Avenue, West Bajac-Bajac, Olongapo City | Tel/Fax: (047) 223-9031',
        'logo_url'=> '',
        'tagline' => 'Service Invoice',
        'tin'     => '',
    ];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $k = str_replace('school_', '', $r['config_key']);
            if (!empty($r['config_value'])) $info[$k] = $r['config_value'];
        }
    }
    return $info;
}

// ── Common HTML head styles ────────────────────────────────────────────────────
function documentStyles(): string {
    return "
<style>
@import url('https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&family=Source+Serif+4:ital,wght@0,400;0,700;1,400&display=swap');
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Source Sans 3', sans-serif; background: #eee; padding: 20px; font-size: 12px; color: #111; }
.page { background: white; width: 148mm; margin: 0 auto; padding: 10mm 12mm 8mm; box-shadow: 0 2px 12px rgba(0,0,0,.15); }
.school-header { display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #1a3c6e; padding-bottom: 8px; margin-bottom: 8px; }
.school-logo { width: 52px; height: 52px; object-fit: contain; flex-shrink: 0; }
.school-logo-placeholder { width: 52px; height: 52px; background: #1a3c6e; border-radius: 50%; flex-shrink: 0; display:flex; align-items:center; justify-content:center; color:white; font-weight:700; font-size:18px; }
.school-info { flex: 1; }
.school-name { font-family: 'Source Serif 4', serif; font-size: 15px; font-weight: 700; color: #1a3c6e; line-height: 1.2; }
.school-address { font-size: 10px; color: #555; }
.doc-type { margin-top: 12px; text-align: center; }
.doc-type h2 { font-family: 'Source Serif 4', serif; font-size: 14px; letter-spacing: 2px; color: #1a3c6e; text-transform: uppercase; }
.doc-number { font-size: 18px; font-weight: 700; color: #c8352a; letter-spacing: 1px; margin-top: 2px; }
.meta-row { display: flex; justify-content: space-between; font-size: 10px; color: #666; margin-top: 4px; }
.divider { border: none; border-top: 1px dashed #ccc; margin: 8px 0; }
.section-title { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #888; font-weight: 600; margin-bottom: 4px; }
.student-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2px 16px; margin-bottom: 8px; }
.field label { font-size: 9px; color: #888; text-transform: uppercase; display: block; }
.field span { font-size: 11px; font-weight: 600; }
.payment-box { background: #f8f9fb; border: 1px solid #dde; border-radius: 4px; padding: 8px 10px; margin-bottom: 8px; }
.amount-display { text-align: center; margin: 4px 0 8px; }
.amount-display .label { font-size: 9px; color: #888; text-transform: uppercase; letter-spacing: 1px; }
.amount-display .value { font-size: 26px; font-weight: 700; color: #1a3c6e; font-family: 'Source Serif 4', serif; }
.breakdown { width: 100%; font-size: 10px; border-collapse: collapse; }
.breakdown tr td { padding: 2px 4px; }
.breakdown tr td:last-child { text-align: right; font-weight: 600; }
.breakdown .total-row td { border-top: 1px solid #ccc; padding-top: 4px; font-weight: 700; font-size: 11px; }
.balance-row td { color: #c8352a; }
.balance-row.paid td { color: #1a7a3c; }
.footer-row { display: flex; align-items: flex-end; justify-content: space-between; margin-top: 10px; }
.signature { text-align: center; }
.signature .line { border-top: 1px solid #333; width: 100px; margin: 22px auto 2px; }
.signature .name { font-size: 10px; font-weight: 600; }
.signature .role { font-size: 9px; color: #888; }
.qr-section { text-align: center; }
.qr-section img { width: 70px; height: 70px; border: 1px solid #ddd; padding: 2px; }
.qr-section p { font-size: 8px; color: #999; margin-top: 2px; }
.watermark { text-align: center; font-size: 9px; color: #bbb; margin-top: 8px; letter-spacing: 1px; border-top: 1px solid #eee; padding-top: 6px; }
.invoice-badge { display: inline-block; background: #fff3cd; color: #856404; border: 1px solid #ffc107; border-radius: 4px; font-size: 9px; font-weight: 700; letter-spacing: 1px; padding: 2px 8px; margin-top: 4px; text-transform: uppercase; }
.status-badge { display: inline-block; border-radius: 4px; font-size: 9px; font-weight: 700; letter-spacing: 1px; padding: 2px 8px; margin-top: 4px; text-transform: uppercase; }
.status-paid   { background: #d1e7dd; color: #0a6640; border: 1px solid #a3cfbb; }
.status-partial{ background: #fff3cd; color: #856404; border: 1px solid #ffc107; }
.status-unpaid { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
@media print {
  body { background: white; padding: 0; }
  .page { box-shadow: none; }
  .no-print { display: none; }
}
</style>";
}

// =============================================================================
// ACTION: sign_payment
// =============================================================================
if ($action === 'sign_payment') {
    require_once __DIR__ . '/auth_middleware.php';
    requireAuth($conn, 'accounting');

    $data = json_decode(file_get_contents('php://input'), true);
    $payment_id = (int)($data['payment_id'] ?? 0);
    if (!$payment_id) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'payment_id required']); exit(); }

    $row = getAndSignPayment($conn, $payment_id);
    if (!$row) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'Payment not found']); exit(); }

    $baseUrl   = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['SCRIPT_NAME']);
    $verifyUrl = rtrim($baseUrl,'/').'/receipt.php?action=verify&id='.$payment_id.'&token='.$row['receipt_token'];

    echo json_encode([
        'success'    => true,
        'token'      => $row['receipt_token'],
        'verifyUrl'  => $verifyUrl,
        'signedAt'   => $row['receipt_signed_at'],
    ]);
    exit();
}

// =============================================================================
// ACTION: get_token
// =============================================================================
if ($action === 'get_token') {
    require_once __DIR__ . '/auth_middleware.php';
    requireAuth($conn);

    $payment_id = (int)($_GET['payment_id'] ?? 0);
    if (!$payment_id) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'payment_id required']); exit(); }

    $row = getAndSignPayment($conn, $payment_id);
    if (!$row) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'Payment not found']); exit(); }

    $baseUrl   = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['SCRIPT_NAME']);
    $verifyUrl = rtrim($baseUrl,'/').'/receipt.php?action=verify&id='.$payment_id.'&token='.$row['receipt_token'];

    echo json_encode(['success'=>true,'token'=>$row['receipt_token'],'verifyUrl'=>$verifyUrl]);
    exit();
}

// =============================================================================
// ACTION: verify — QR scan verification page
// =============================================================================
if ($action === 'verify') {
    header('Content-Type: text/html; charset=utf-8');

    $payment_id = (int)($_GET['id'] ?? 0);
    $token      = trim($_GET['token'] ?? '');
    $format     = trim($_GET['format'] ?? 'html');

    if (!$payment_id || !$token) {
        http_response_code(400);
        if ($format === 'json') { echo json_encode(['valid'=>false,'message'=>'Missing id or token']); }
        else { echo '<h1>Invalid QR Code</h1><p>Missing payment ID or token.</p>'; }
        exit();
    }

    $stmt = $conn->prepare("SELECT ip.*, s.student_number, s.first_name, s.last_name, s.program, s.year_level, s.semester FROM installment_payments ip JOIN students s ON ip.student_id = s.id WHERE ip.id = ?");
    $stmt->bind_param('i', $payment_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        if ($format === 'json') { echo json_encode(['valid'=>false,'message'=>'Payment not found']); }
        else { echo renderVerifyPage(false, null, $payment_id); }
        exit();
    }

    $isValid = !empty($row['receipt_token']) &&
               hash_equals($row['receipt_token'], $token) &&
               verifyReceiptToken($token, $payment_id, $row['or_ar_number'], (float)$row['amount'], (int)$row['student_id']);

    if ($format === 'json') {
        echo json_encode([
            'valid'       => $isValid,
            'orArNumber'  => $isValid ? $row['or_ar_number'] : null,
            'studentName' => $isValid ? ($row['first_name'].' '.$row['last_name']) : null,
            'amount'      => $isValid ? (float)$row['amount'] : null,
            'paymentDate' => $isValid ? $row['payment_date'] : null,
            'examPeriod'  => $isValid ? $row['exam_period'] : null,
        ]);
    } else {
        echo renderVerifyPage($isValid, $isValid ? $row : null, $payment_id);
    }
    exit();
}

// =============================================================================
// ACTION: get_invoice — Service Invoice (for online/browser viewing)
// GET ?action=get_invoice&payment_id=XX
// This is what students/staff see on-screen. NOT a proof of payment.
// =============================================================================
if ($action === 'get_invoice') {
    require_once __DIR__ . '/auth_middleware.php';
    requireAuth($conn);

    header('Content-Type: text/html; charset=utf-8');
    $payment_id = (int)($_GET['payment_id'] ?? 0);
    if (!$payment_id) { echo '<p>payment_id required</p>'; exit(); }

    $row = getAndSignPayment($conn, $payment_id);
    if (!$row) { echo '<p>Payment not found</p>'; exit(); }

    $school = getSchoolInfo($conn);

    $total_paid  = (float)$row['total_paid'];
    $balance     = max(0, (float)($row['total_assessment']??0) - $total_paid);
    $studentName = trim($row['first_name'].' '.($row['middle_name']?$row['middle_name'][0].'. ':'').$row['last_name']);
    $cashierName = trim(($row['cashier_first']??'').' '.($row['cashier_last']??'')) ?: 'Accounting Office';
    $issuedDate  = date('F d, Y', strtotime($row['payment_date']));
    $issuedTime  = date('g:i A', strtotime($row['created_at']??'now'));

    echo renderServiceInvoice($row, $school, $studentName, $cashierName, $issuedDate, $issuedTime, $balance, $total_paid);
    exit();
}

// =============================================================================
// ACTION: get_printable — Official OR/AR Receipt (for printing only)
// GET ?action=get_printable&payment_id=XX
// =============================================================================
if ($action === 'get_printable') {
    require_once __DIR__ . '/auth_middleware.php';
    requireAuth($conn);

    header('Content-Type: text/html; charset=utf-8');
    $payment_id = (int)($_GET['payment_id'] ?? 0);
    if (!$payment_id) { echo '<p>payment_id required</p>'; exit(); }

    $row = getAndSignPayment($conn, $payment_id);
    if (!$row) { echo '<p>Payment not found</p>'; exit(); }

    $school = getSchoolInfo($conn);

    $baseUrl   = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['SCRIPT_NAME']);
    $verifyUrl = rtrim($baseUrl,'/').'/receipt.php?action=verify&id='.$payment_id.'&token='.$row['receipt_token'];

    $total_paid  = (float)$row['total_paid'];
    $balance     = max(0, (float)($row['total_assessment']??0) - $total_paid);
    $studentName = trim($row['first_name'].' '.($row['middle_name']?$row['middle_name'][0].'. ':'').$row['last_name']);
    $cashierName = trim(($row['cashier_first']??'').' '.($row['cashier_last']??'')) ?: 'Accounting Office';
    $issuedDate  = date('F d, Y', strtotime($row['payment_date']));
    $issuedTime  = date('g:i A', strtotime($row['created_at']??'now'));

    echo renderPrintableReceipt($row, $school, $verifyUrl, $studentName, $cashierName, $issuedDate, $issuedTime, $balance, $total_paid);
    exit();
}

// =============================================================================
// RENDER: Service Invoice (online browser view)
// =============================================================================
function renderServiceInvoice(array $row, array $school, string $studentName, string $cashierName, string $issuedDate, string $issuedTime, float $balance, float $total_paid): string {

    // Determine payment status badge
    $totalAssess = (float)($row['total_assessment']??0);
    if ($balance <= 0) {
        $statusClass = 'status-paid';
        $statusLabel = 'FULLY PAID';
    } elseif ($total_paid > 0) {
        $statusClass = 'status-partial';
        $statusLabel = 'PARTIALLY PAID';
    } else {
        $statusClass = 'status-unpaid';
        $statusLabel = 'UNPAID';
    }

    $invoiceNo   = htmlspecialchars($row['or_ar_number']); // reuse OR number as invoice ref
    $amount      = number_format((float)$row['amount'], 2);
    $period      = htmlspecialchars($row['exam_period']);
    $method      = htmlspecialchars($row['payment_method']);
    $gcash       = !empty($row['gcash_reference']) ? '<tr><td>GCash Ref</td><td>'.htmlspecialchars($row['gcash_reference']).'</td></tr>' : '';
    $program     = htmlspecialchars($row['program']??'');
    $yr          = htmlspecialchars($row['year_level']??'');
    $sem         = htmlspecialchars($row['semester']??'');
    $stNum       = htmlspecialchars($row['student_number']??'');
    $totalAssessFmt = number_format($totalAssess, 2);
    $balanceFmt     = number_format($balance, 2);
    $totalPaidFmt   = number_format($total_paid, 2);
    $schoolName     = htmlspecialchars($school['name']);
    $schoolAddr     = htmlspecialchars($school['address']);
    $schoolTin      = $school['tin'] ? '<span>TIN: '.htmlspecialchars($school['tin']).'</span>' : '';

    // Fee breakdown rows
    $tuitionFmt  = number_format((float)($row['tuition_fee']??0), 2);
    $miscFmt     = number_format((float)($row['miscellaneous_fee']??0), 2);
    $regFmt      = number_format((float)($row['registration_fee']??0), 2);
    $labFmt      = number_format((float)($row['laboratory_fee']??0), 2);
    $energyFmt   = number_format((float)($row['energy_fee']??0), 2);
    $discountFmt = number_format((float)($row['discount']??0), 2);
    $installFmt  = number_format((float)($row['installment_fee']??0), 2);

    return "<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width, initial-scale=1'>
<title>Service Invoice — $invoiceNo</title>
" . documentStyles() . "
</head>
<body>
<div class='no-print' style='text-align:center; margin-bottom:12px;'>
  <button onclick='window.print()' style='background:#1a3c6e;color:white;border:none;padding:8px 20px;border-radius:4px;cursor:pointer;font-size:13px;'>🖨️ Print Invoice</button>
  <button onclick='window.close()' style='margin-left:8px;background:#eee;border:none;padding:8px 16px;border-radius:4px;cursor:pointer;font-size:13px;'>Close</button>
</div>
<div class='page'>

  <div class='school-header'>
    <div class='school-logo-placeholder'>S</div>
    <div class='school-info'>
      <div class='school-name'>$schoolName</div>
      <div class='school-address'>$schoolAddr $schoolTin</div>
    </div>
  </div>

  <div class='doc-type'>
    <h2>Service Invoice</h2>
    <div class='doc-number'>Ref. No. $invoiceNo</div>
    <div class='meta-row'>
      <span>Date: $issuedDate</span>
      <span>Time: $issuedTime</span>
    </div>
    <div style='text-align:center; margin-top:4px;'>
      <span class='status-badge $statusClass'>$statusLabel</span>
      <span class='invoice-badge'>This is NOT an Official Receipt</span>
    </div>
  </div>

  <hr class='divider'>

  <div class='section-title'>Student Information</div>
  <div class='student-grid'>
    <div class='field'><label>Name</label><span>$studentName</span></div>
    <div class='field'><label>Student No.</label><span>$stNum</span></div>
    <div class='field'><label>Program</label><span>$program</span></div>
    <div class='field'><label>Year Level</label><span>$yr</span></div>
    <div class='field'><label>Semester</label><span>$sem</span></div>
    <div class='field'><label>Payment Plan</label><span>" . htmlspecialchars($row['payment_plan']??'') . "</span></div>
  </div>

  <hr class='divider'>

  <div class='section-title'>Assessment Breakdown</div>
  <div class='payment-box'>
    <table class='breakdown'>
      <tr><td>Tuition Fee</td><td>₱$tuitionFmt</td></tr>
      <tr><td>Miscellaneous Fee</td><td>₱$miscFmt</td></tr>
      <tr><td>Registration Fee</td><td>₱$regFmt</td></tr>
      <tr><td>Laboratory Fee</td><td>₱$labFmt</td></tr>
      <tr><td>Energy Fee</td><td>₱$energyFmt</td></tr>
      " . ((float)($row['discount']??0) > 0 ? "<tr><td>Discount</td><td style='color:#1a7a3c;'>- ₱$discountFmt</td></tr>" : '') . "
      " . ((float)($row['installment_fee']??0) > 0 ? "<tr><td>Installment Fee</td><td>₱$installFmt</td></tr>" : '') . "
      <tr class='total-row'><td>Total Assessment</td><td>₱$totalAssessFmt</td></tr>
    </table>
  </div>

  <hr class='divider'>

  <div class='section-title'>Payment for This Period</div>
  <div class='payment-box'>
    <div class='amount-display'>
      <div class='label'>Amount for $period</div>
      <div class='value'>₱$amount</div>
    </div>
    <table class='breakdown'>
      <tr><td>Payment Method</td><td>$method</td></tr>
      $gcash
      <tr class='total-row'><td>Total Paid to Date</td><td>₱$totalPaidFmt</td></tr>
      <tr class='balance-row " . ($balance <= 0 ? 'paid' : '') . "'><td>Remaining Balance</td><td>₱$balanceFmt</td></tr>
    </table>
  </div>

  <div class='footer-row'>
    <div class='signature'>
      <div class='line'></div>
      <div class='name'>$cashierName</div>
      <div class='role'>Accounting Staff</div>
    </div>
    <div style='text-align:center; font-size:9px; color:#888; max-width:120px; line-height:1.4;'>
      <p>An <strong>Official Receipt</strong> will be issued upon payment confirmation.</p>
      <p style='margin-top:4px; color:#aaa;'>Ref: " . substr($row['receipt_token']??'', 0, 12) . "...</p>
    </div>
    <div class='signature'>
      <div class='line'></div>
      <div class='name'>$studentName</div>
      <div class='role'>Student / Authorized Representative</div>
    </div>
  </div>

  <div class='watermark'>
    ✦ SERVICE INVOICE — NOT AN OFFICIAL RECEIPT ✦ · For inquiries: Accounting Office
  </div>
</div>
</body></html>";
}

// =============================================================================
// RENDER: Printable OR/AR Receipt (official, post-payment)
// =============================================================================
function renderPrintableReceipt(array $row, array $school, string $verifyUrl, string $studentName, string $cashierName, string $issuedDate, string $issuedTime, float $balance, float $total_paid): string {

    $orType    = $row['or_ar_type'] === 'OR' ? 'OFFICIAL RECEIPT' : 'ACKNOWLEDGEMENT RECEIPT';
    $orNo      = htmlspecialchars($row['or_ar_number']);
    $amount    = number_format((float)$row['amount'], 2);
    $period    = htmlspecialchars($row['exam_period']);
    $method    = htmlspecialchars($row['payment_method']);
    $gcash     = !empty($row['gcash_reference']) ? '<tr><td>GCash Ref</td><td>'.$row['gcash_reference'].'</td></tr>' : '';
    $program   = htmlspecialchars($row['program']??'');
    $yr        = htmlspecialchars($row['year_level']??'');
    $sem       = htmlspecialchars($row['semester']??'');
    $stNum     = htmlspecialchars($row['student_number']??'');
    $totalAssess = number_format((float)($row['total_assessment']??0), 2);
    $balanceFmt  = number_format($balance, 2);
    $totalPaidFmt= number_format($total_paid, 2);
    $schoolName  = htmlspecialchars($school['name']);
    $schoolAddr  = htmlspecialchars($school['address']);
    $schoolTin   = $school['tin'] ? '<span>TIN: '.htmlspecialchars($school['tin']).'</span>' : '';

    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=' . urlencode($verifyUrl);

    return "<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
<title>$orType — $orNo</title>
" . documentStyles() . "
</head>
<body>
<div class='no-print' style='text-align:center; margin-bottom:12px;'>
  <button onclick='window.print()' style='background:#1a3c6e;color:white;border:none;padding:8px 20px;border-radius:4px;cursor:pointer;font-size:13px;'>🖨️ Print Receipt</button>
  <button onclick='window.close()' style='margin-left:8px;background:#eee;border:none;padding:8px 16px;border-radius:4px;cursor:pointer;font-size:13px;'>Close</button>
</div>
<div class='page'>
  <div class='school-header'>
    <div class='school-logo-placeholder'>S</div>
    <div class='school-info'>
      <div class='school-name'>$schoolName</div>
      <div class='school-address'>$schoolAddr $schoolTin</div>
    </div>
  </div>

  <div class='doc-type'>
    <h2>$orType</h2>
    <div class='doc-number'>$orNo</div>
    <div class='meta-row'>
      <span>Date: $issuedDate</span>
      <span>Time: $issuedTime</span>
    </div>
  </div>

  <hr class='divider'>

  <div class='section-title'>Student Information</div>
  <div class='student-grid'>
    <div class='field'><label>Name</label><span>$studentName</span></div>
    <div class='field'><label>Student No.</label><span>$stNum</span></div>
    <div class='field'><label>Program</label><span>$program</span></div>
    <div class='field'><label>Year Level</label><span>$yr</span></div>
    <div class='field'><label>Semester</label><span>$sem</span></div>
  </div>

  <hr class='divider'>

  <div class='payment-box'>
    <div class='amount-display'>
      <div class='label'>Amount Paid</div>
      <div class='value'>₱$amount</div>
    </div>
    <table class='breakdown'>
      <tr><td>Payment Period</td><td>$period</td></tr>
      <tr><td>Payment Method</td><td>$method</td></tr>
      $gcash
      <tr class='total-row'><td>Total Assessment</td><td>₱$totalAssess</td></tr>
      <tr><td>Total Paid to Date</td><td>₱$totalPaidFmt</td></tr>
      <tr class='balance-row " . ($balance <= 0 ? 'paid' : '') . "'><td>Remaining Balance</td><td>₱$balanceFmt</td></tr>
    </table>
  </div>

  <div class='footer-row'>
    <div class='signature'>
      <div class='line'></div>
      <div class='name'>$cashierName</div>
      <div class='role'>Cashier / Accounting Staff</div>
    </div>
    <div class='qr-section'>
      <img src='$qrUrl' alt='Verify QR'>
      <p>Scan to verify</p>
      <p>authenticity</p>
    </div>
    <div class='signature'>
      <div class='line'></div>
      <div class='name'>$studentName</div>
      <div class='role'>Student Signature</div>
    </div>
  </div>

  <div class='watermark'>
    ✦ VERIFIED OFFICIAL DOCUMENT ✦ · Token: " . substr($row['receipt_token'], 0, 16) . "...
  </div>
</div>
</body></html>";
}

// =============================================================================
// RENDER: QR Verification page
// =============================================================================
function renderVerifyPage(bool $valid, ?array $row, int $payment_id): string {
    $color  = $valid ? '#0a6640' : '#9b2335';
    $icon   = $valid ? '✓' : '✗';
    $title  = $valid ? 'RECEIPT VERIFIED' : 'RECEIPT INVALID';
    $msg    = $valid ? 'This is an official and authentic payment receipt.' : 'This receipt could not be verified. It may be counterfeit or tampered.';

    $details = '';
    if ($valid && $row) {
        $details = "
        <table class='details'>
            <tr><td>OR/AR Number</td><td><strong>{$row['or_ar_number']}</strong></td></tr>
            <tr><td>Student</td><td>{$row['first_name']} {$row['last_name']}</td></tr>
            <tr><td>Student No.</td><td>{$row['student_number']}</td></tr>
            <tr><td>Program</td><td>{$row['program']}</td></tr>
            <tr><td>Amount Paid</td><td><strong>₱" . number_format((float)$row['amount'], 2) . "</strong></td></tr>
            <tr><td>Payment Date</td><td>{$row['payment_date']}</td></tr>
            <tr><td>Period</td><td>{$row['exam_period']}</td></tr>
            <tr><td>Method</td><td>{$row['payment_method']}</td></tr>
        </table>";
    }

    return "<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width, initial-scale=1'>
<title>Receipt Verification</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f5f5f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
.card { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.12); max-width: 480px; width: 100%; overflow: hidden; }
.header { background: $color; color: white; padding: 32px 24px; text-align: center; }
.icon { font-size: 48px; margin-bottom: 8px; }
.title { font-size: 22px; font-weight: 700; letter-spacing: 1px; }
.subtitle { font-size: 13px; margin-top: 6px; opacity: .85; }
.body { padding: 24px; }
.msg { text-align: center; color: #555; margin-bottom: 20px; font-size: 14px; line-height: 1.5; }
.details { width: 100%; border-collapse: collapse; font-size: 14px; }
.details tr { border-bottom: 1px solid #eee; }
.details td { padding: 10px 4px; }
.details td:first-child { color: #888; width: 45%; }
.footer { text-align: center; font-size: 12px; color: #aaa; padding: 16px; border-top: 1px solid #eee; }
</style>
</head>
<body>
<div class='card'>
  <div class='header'>
    <div class='icon'>$icon</div>
    <div class='title'>$title</div>
    <div class='subtitle'>Student Information System — Payment Verification</div>
  </div>
  <div class='body'>
    <p class='msg'>$msg</p>
    $details
  </div>
  <div class='footer'>Verified on " . date('F d, Y g:i A') . " · Payment ID #$payment_id</div>
</div>
</body></html>";
}

$conn->close();
?>