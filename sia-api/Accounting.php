<?php
require_once __DIR__ . '/config.php';
applyCors();
ob_start(); // capture stray notices so JSON is never corrupted

// Shared helpers: cleanCode(), loadFeeConfig(), safeStudentId(), jsonOut()
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/audit_helper.php';
// soa_helper.php provides saveSoaSnapshot() used in verifyPayment().
// It is a pure function file — no auth checks, no HTTP routing, no side effects.
require_once __DIR__ . '/soa_helper.php';

// ── Inline privacy layer (self-contained, no external file needed) ─────────────
if (!function_exists('applyPrivacy')) {
    function maskEmail(string $v): string {
        if (!str_contains($v,'@')) return '***@***.***';
        [$l,$d] = explode('@',$v,2);
        return substr($l,0,min(2,strlen($l))).str_repeat('*',max(3,strlen($l)-2)).'@'.$d;
    }
    function maskPhone(string $v): string {
        $d=preg_replace('/\D/','',$v); $len=strlen($d);
        if ($len<7) return str_repeat('*',$len);
        return (str_starts_with(trim($v),'+') ? '+' : '').substr($d,0,4).str_repeat('*',max(3,$len-8)).substr($d,-4);
    }
    function maskAmount(float $v): string {
        if ($v<=0) return '\u20b10';
        $mag=pow(10,floor(log10($v)));
        return '\u20b1'.number_format(floor($v/$mag)*$mag).'+';
    }
    function maskGrade(?float $v): string {
        if ($v===null) return 'N/A';
        if ($v>=5.0) return '5.0 (INC/Failed)';
        $l=floor($v*2)/2;
        return number_format($l,2).'\u2013'.number_format($l+0.5,2);
    }
    function maskGpa(float $v): string {
        $r=round($v,1); $label=$v<=3.0?'Passing':($v<5.0?'At risk':'Failed');
        return "~{$r} ({$label})";
    }
    function maskStudentNumber(string $v): string {
        $len=strlen($v); if ($len<=6) return str_repeat('*',$len);
        return substr($v,0,4).str_repeat('*',$len-6).substr($v,-2);
    }
    function maskAddress(string $v): string {
        $p=array_map('trim',explode(',',$v));
        if (count($p)>=3) return implode(', ',array_slice($p,-2));
        return count($p)===2 ? $p[1] : '***';
    }
    function _castForMask(string $fn, $v): mixed {
        return match($fn) {
            'maskAmount','maskGpa' => (float)$v,
            'maskGrade'            => ($v===null ? null : (float)$v),
            default                => (string)$v,
        };
    }
    function getPrivacyPolicy(): array {
        return [
            'email'           =>['roles_full'=>['admin','registrar'],'roles_masked'=>['student','accounting','faculty'],'mask_fn'=>'maskEmail'],
            'phone'           =>['roles_full'=>['admin','registrar'],'roles_masked'=>['student','accounting'],'mask_fn'=>'maskPhone'],
            'address'         =>['roles_full'=>['admin','registrar'],'roles_masked'=>['accounting'],'mask_fn'=>'maskAddress'],
            'date_of_birth'   =>['roles_full'=>['admin','registrar'],'roles_masked'=>[]],
            'lrn_no'          =>['roles_full'=>['admin','registrar'],'roles_masked'=>['accounting'],'mask_fn'=>'maskStudentNumber'],
            'student_number'  =>['roles_full'=>['admin','registrar','accounting','student'],'roles_masked'=>['faculty'],'mask_fn'=>'maskStudentNumber'],
            'gpa'             =>['roles_full'=>['admin','registrar','student'],'roles_masked'=>['faculty','accounting'],'mask_fn'=>'maskGpa'],
            'prelim'          =>['roles_full'=>['admin','registrar','faculty','student'],'roles_masked'=>['accounting'],'mask_fn'=>'maskGrade'],
            'midterm'         =>['roles_full'=>['admin','registrar','faculty','student'],'roles_masked'=>['accounting'],'mask_fn'=>'maskGrade'],
            'final'           =>['roles_full'=>['admin','registrar','faculty','student'],'roles_masked'=>['accounting'],'mask_fn'=>'maskGrade'],
            'grade'           =>['roles_full'=>['admin','registrar','faculty','student'],'roles_masked'=>['accounting'],'mask_fn'=>'maskGrade'],
            'amount'          =>['roles_full'=>['admin','accounting'],'roles_masked'=>['registrar'],'roles_redacted'=>['student'],'mask_fn'=>'maskAmount'],
            'gcash_amount'    =>['roles_full'=>['admin','accounting'],'roles_masked'=>['registrar'],'roles_redacted'=>['student'],'mask_fn'=>'maskAmount'],
            'gcashAmount'     =>['roles_full'=>['admin','accounting'],'roles_masked'=>['registrar'],'roles_redacted'=>['student'],'mask_fn'=>'maskAmount'],
            'totalAssessment' =>['roles_full'=>['admin','accounting'],'roles_masked'=>['registrar'],'roles_redacted'=>['student'],'mask_fn'=>'maskAmount'],
            'totalPaid'       =>['roles_full'=>['admin','accounting'],'roles_masked'=>['registrar'],'roles_redacted'=>['student'],'mask_fn'=>'maskAmount'],
            'balance'         =>['roles_full'=>['admin','accounting'],'roles_masked'=>['registrar'],'roles_redacted'=>['student'],'mask_fn'=>'maskAmount'],
            'gcash_reference' =>['roles_full'=>['admin','accounting'],'roles_masked'=>[]],
            'gcashReference'  =>['roles_full'=>['admin','accounting'],'roles_masked'=>[]],
            'reference_number'=>['roles_full'=>['admin','accounting'],'roles_masked'=>[]],
            'password'        =>['roles_full'=>[],'roles_masked'=>[]],
            'token'           =>['roles_full'=>[],'roles_masked'=>[]],
            // Guardian contact fields — accounting needs full guardian email for SOA notifications
            'guardianEmail'   =>['roles_full'=>['admin','registrar','accounting'],'roles_masked'=>['faculty'],'mask_fn'=>'maskEmail'],
            'guardian_email'  =>['roles_full'=>['admin','registrar','accounting'],'roles_masked'=>['faculty'],'mask_fn'=>'maskEmail'],
            'guardianContact' =>['roles_full'=>['admin','registrar','accounting'],'roles_masked'=>[]],
            'guardian_contact'=>['roles_full'=>['admin','registrar','accounting'],'roles_masked'=>[]],
            'guardianName'    =>['roles_full'=>['admin','registrar','accounting','faculty','student'],'roles_masked'=>[]],
            'guardian_name'   =>['roles_full'=>['admin','registrar','accounting','faculty','student'],'roles_masked'=>[]],
        ];
    }
    function applyPrivacy(array $record, ?array $authUser, string $context='', bool $isOwner=false): array {
        $role = $authUser['role'] ?? 'guest';
        if ($role === 'admin') return $record;
        $policy = getPrivacyPolicy(); $result = [];
        foreach ($record as $key => $value) {
            if ($value === null || $value === '') { $result[$key] = $value; continue; }
            if (!isset($policy[$key])) { $result[$key] = $value; continue; }
            $rule = $policy[$key];
            $eff  = ($isOwner && $role === 'student') ? 'admin' : $role;
            if ($eff === 'admin') { $result[$key] = $value; continue; }
            $full     = $rule['roles_full']     ?? [];
            $masked   = $rule['roles_masked']   ?? [];
            $redacted = $rule['roles_redacted'] ?? [];
            $fn       = $rule['mask_fn']        ?? null;
            if (in_array($eff,$full,true)) { $result[$key] = $value; }
            elseif (in_array($eff,$redacted,true)) { $result[$key] = '****'; }
            elseif (in_array($eff,$masked,true) && $fn && function_exists($fn)) { $result[$key] = $fn(_castForMask($fn,$value)); }
            else { $result[$key] = null; } // role has no access — redact to null, never silently drop the key
        }
        return $result;
    }
    function applyPrivacyList(array $records, ?array $authUser, string $context='', ?int $ownerStudentId=null): array {
        return array_map(function(array $r) use ($authUser,$context,$ownerStudentId) {
            $isOwner=false;
            if ($ownerStudentId!==null) { $rid=(int)($r['id']??$r['student_id']??$r['studentId']??0); $isOwner=($rid===$ownerStudentId); }
            return applyPrivacy($r,$authUser,$context,$isOwner);
        },$records);
    }
    function privacyMeta(?array $authUser): array {
        return ['role'=>$authUser['role']??'guest','masking_active'=>true,'masked_at'=>date('c')];
    }
}


$action = $_GET['action'] ?? '';

require_once __DIR__ . '/auth_middleware.php';
// Fee preview actions called during enrollment wizard (no token yet)
$publicActions = ['get_fee_preview', 'get_shs_fee', 'get_tvet_fee', 'get_fee_config', 'get_due_dates'];
$authUser = in_array($action, $publicActions) ? null : requireAuth($conn);

// ── Auto-create required tables if missing (so migrate.php is not mandatory) ──
// ── End auto-create ──

// ── Ensure required columns exist ──

// ── Ensure tuition_fees table ──
// Schema managed by migrate.php

// ── Ensure installment_payments table ──
// Schema managed by migrate.php
// Schema managed by migrate.php

// Schema and data fixes managed by migrate.php

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        switch ($action) {
            case 'get_pending_payments':  getPendingPayments($conn);  break;
            case 'get_payment_history':          getPaymentHistory($conn);             break;
            case 'get_student_payment_history':  getStudentPaymentHistory($conn);      break;
            case 'get_soa_semesters':            getSoaSemesters($conn);               break;
            case 'get_tuition_fees':      getTuitionFees($conn);      break;
            case 'get_liquidation':       getLiquidation($conn);      break;
            case 'get_student_receipts':  getStudentReceipts($conn);  break;
            case 'get_fee_preview':       getFeePreview($conn);       break;
            case 'get_payment_schedule':      getPaymentSchedule($conn);      break;
            case 'get_exam_permits':          getExamPermits($conn);          break;
            case 'get_student_permit_status': getStudentPermitStatus($conn);  break;
            case 'get_payment_notices':       getPaymentNotices($conn);       break;
            case 'get_course_groups':         getCourseGroups($conn);         break;
            case 'get_permit_course_groups':  getPermitCourseGroups($conn);   break;
            case 'preview_bulk_notice':       previewBulkNotice($conn);       break;
            case 'get_all_enrolled_students': getAllEnrolledStudents($conn); break;
            case 'recalc_payment_schedules':  recalcAllPaymentSchedules($conn); break;
            case 'get_student_installment':   getStudentInstallment($conn); break;
            case 'get_permit_details':         getPermitDetails($conn);      break;
            case 'get_installment_students':   getInstallmentStudents($conn); break;
            case 'get_shs_fee':               getSHSFee($conn);              break;
            case 'get_tvet_fee':              getTVETFee($conn);             break;
            case 'get_fee_config':            getFeeConfig($conn);           break;
            // ── Income Report Generator ──
            case 'get_income_report':         getIncomeReport($conn);        break;
            case 'get_income_summary':        getIncomeSummary($conn);       break;
            case 'get_income_by_program':     getIncomeByProgram($conn);     break;
            // ── Payment Due Dates ──
            case 'get_due_dates':             getPaymentDueDates($conn);     break;
            case 'get_student_scholarship':  getStudentScholarship($conn);  break;
            case 'get_scholarship_history':  getScholarshipHistory($conn);  break;
            case 'get_subject_fee_log':      getSubjectFeeLog($conn);       break;
            case 'get_pending_scholarships': getPendingScholarships($conn); break;
            case 'get_add_drop_requests_accounting': getAddDropRequestsForAccounting($conn); break;
            case 'backfill_fee_log':                 backfillSubjectFeeLog($conn);           break;
            default: while (ob_get_level() > 0) { ob_end_clean(); } echo json_encode(['success' => false, 'message' => 'Unknown action']);
        }
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { while (ob_get_level() > 0) { ob_end_clean(); } echo json_encode(['success' => false, 'message' => 'Invalid JSON']); exit(); }
        switch ($action) {
            case 'submit_gcash':        submitGcash($conn, $data);        break;
            case 'notify_cash_pending': notifyCashPending($conn, $data);  break;
            case 'verify_payment':      verifyPayment($conn, $data);      break;
            case 'reject_payment':      rejectPayment($conn, $data);      break;
            case 'compute_fees':        computeFees($conn, $data);        break;
            case 'save_fee_config':     saveFeeConfig($conn, $data);      break;
            case 'add_fee_config':      addFeeConfig($conn, $data);       break;
            case 'delete_fee_config':   deleteFeeConfig($conn, $data);    break;
            case 'record_installment':  recordInstallment($conn, $data);  break;
            case 'send_payment_notice':  sendPaymentNotice($conn, $data);  break;
            case 'send_bulk_notice':      sendBulkNotice($conn, $data);      break;
            case 'request_exam_permit':  requestExamPermit($conn, $data);  break;
            case 'process_exam_permit':  processExamPermit($conn, $data);  break;
            case 'unlock_payment_period': unlockPaymentPeriod($conn, $data); break;
            case 'submit_installment_payment': submitInstallmentPayment($conn, $data); break;
            case 'edit_payment':              editPayment($conn, $data);              break;
            case 'correct_verified_payment': correctVerifiedPayment($conn, $data); break;
            // ── Payment Due Dates ──
            case 'save_due_dates':            savePaymentDueDates($conn, $data); break;
            case 'grant_scholarship':         grantScholarship($conn, $data);    break;
            case 'remove_scholarship':        removeScholarship($conn, $data);   break;
            case 'approve_scholarship':       approveScholarship($conn, $data);  break;
            case 'reject_scholarship':        rejectScholarship($conn, $data);   break;
            case 'accounting_approve_add_drop':   accountingApproveAddDropFromAccounting($conn, $data); break;
            default: while (ob_get_level() > 0) { ob_end_clean(); } echo json_encode(['success' => false, 'message' => 'Unknown action']);
        }
        break;
    default:
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
$conn->close();

// ─────────────────────────────────────────────────────────────
// FEE PREVIEW — called BEFORE payment so student sees breakdown
// ─────────────────────────────────────────────────────────────
// FEE PREVIEW — called BEFORE payment so student sees breakdown
// GET ?action=get_fee_preview&program=BS+IT&student_id=XX&discount=0&has_installment=0
//
// Priority for units:
//  1. tuition_fees table (set by registrar TOR evaluation for transferees)
//  2. program_courses → courses sum
//  3. courses.program sum
//  4. default 18
// ─────────────────────────────────────────────────────────────
function getFeePreview($conn) {
    $program_name = trim($_GET['program']    ?? '');
    $student_id   = (int)($_GET['student_id'] ?? 0);
    // year_level and semester passed from login page (pre-registration, no student_id yet)
    $param_year_level = trim($_GET['year_level'] ?? '');
    $param_semester   = trim($_GET['semester']   ?? '');
    // Accept override discount and installment flag from login page (before student exists in DB)
    $override_discount    = isset($_GET['discount'])        ? (float)$_GET['discount']         : null;
    $override_installment = isset($_GET['has_installment']) ? (bool)(int)$_GET['has_installment'] : null;
    // FIX SUBJ-UNITS-01: Accept explicit units override from frontend after subject approval.
    // When the Registrar approves a subset of subjects, the approved unit count is sent
    // directly here so the fee is computed from the actual approved subjects, not the
    // full curriculum unit count.
    $override_units = isset($_GET['units']) && (int)$_GET['units'] > 0 ? (int)$_GET['units'] : 0;

    if (!$program_name) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'program required']); return;
    }

    // Route SHS / TVET students to their own fee functions
    // so the college formula (units x650, misc, lab, energy) is never applied to them.
    if ($student_id > 0) {
        $catSt = $conn->prepare("SELECT student_category, student_type, payment_plan, scholarship_amount FROM students WHERE id=? LIMIT 1");
        $catSt->bind_param('i', $student_id);
        $catSt->execute();
        $catRes = $catSt->get_result();
        $catSt->close();
        $catRow  = $catRes ? $catRes->fetch_assoc() : null;
        $cat     = strtoupper(trim($catRow['student_category'] ?? ''));
        $stype   = trim($catRow['student_type'] ?? 'New');
        $disc    = $override_discount !== null ? $override_discount : (float)($catRow['scholarship_amount'] ?? 0);
        $hasInst = $override_installment !== null ? $override_installment : (($catRow['payment_plan'] ?? 'full') === 'installment');
        if ($cat === 'SHS') {
            $_GET['student_type']    = $stype;
            $_GET['student_id']      = $student_id;
            $_GET['discount']        = $disc;
            $_GET['has_installment'] = $hasInst ? 1 : 0;
            getSHSFee($conn);
            return;
        }
        if ($cat === 'TVET') {
            $_GET['student_type']    = $stype;
            $_GET['student_id']      = $student_id;
            $_GET['program']         = $program_name;
            $_GET['discount']        = $disc;
            $_GET['has_installment'] = $hasInst ? 1 : 0;
            getTVETFee($conn);
            return;
        }
    }

    $pn    = $program_name;
    // FIX SUBJ-UNITS-01: If frontend sent explicit approved units, use them directly.
    // This is set after subject approval so fees reflect only the approved subjects.
    $units = $override_units > 0 ? $override_units : 0;

    // 1. For transferees: always use tor_evaluations.approved_units FIRST.
    //    This is the post-credit unit count the registrar approved — it overrides
    //    any stale tuition_fees.units that may have been saved before evaluation.
    if ($student_id > 0) {
        $typeSt = $conn->prepare("SELECT student_type FROM students WHERE id=? LIMIT 1");
        $typeSt->bind_param('i', $student_id);
        $typeSt->execute();
        $typeRes = $typeSt->get_result();
        $typeSt->close();
        $typeRow = $typeRes ? $typeRes->fetch_assoc() : null;
        if (trim($typeRow['student_type'] ?? '') === 'Transferee') {
            $teSt = $conn->prepare("SELECT approved_units FROM tor_evaluations WHERE student_id = ? AND status = 'Evaluated' LIMIT 1");
            $teSt->bind_param('i', $student_id);
            $teSt->execute();
            $te = $teSt->get_result();
            $teSt->close();
            $te_row = $te ? $te->fetch_assoc() : null;
            if ($te_row && (int)$te_row['approved_units'] > 0) {
                $units = (int)$te_row['approved_units'];
            }
        }
    }

    // 2. Non-transferee or unevaluated: check tuition_fees table
    if ($units <= 0 && $student_id > 0) {
        $tfSt = $conn->prepare("SELECT units FROM tuition_fees WHERE student_id = ? LIMIT 1");
        $tfSt->bind_param('i', $student_id);
        $tfSt->execute();
        $tf_res = $tfSt->get_result();
        $tfSt->close();
        $tf_row = $tf_res ? $tf_res->fetch_assoc() : null;
        if ($tf_row && (int)$tf_row['units'] > 0) {
            $units = (int)$tf_row['units'];
        }
    }

    // 2. Resolve year_level and semester — from query params (login page, no student yet)
    //    or from the student's DB record (enrolled student with student_id).
    //    year_level is REQUIRED to avoid counting courses from all years.
    //    semester is stripped to term-only so it matches courses of any school year.
    $year_level = '';
    $semester_term = '';

    // Priority 1: explicit query params (login page pre-registration)
    if ($param_year_level !== '') {
        $year_level = $param_year_level;
    }
    if ($param_semester !== '') {
        if (preg_match('/^(1st Semester|2nd Semester|Summer|Midyear)/i', $param_semester, $m)) {
            $semester_term = $m[1];
        } else {
            $semester_term = $param_semester;
        }
    }

    // Priority 2: student DB record (if student_id provided)
    if ($student_id > 0) {
        $stSt = $conn->prepare("SELECT semester, year_level FROM students WHERE id = ? LIMIT 1");
        $stSt->bind_param('i', $student_id);
        $stSt->execute();
        $stRes = $stSt->get_result();
        $stSt->close();
        $stRow = $stRes ? $stRes->fetch_assoc() : null;
        if ($stRow) {
            if ($year_level === '') $year_level = trim($stRow['year_level'] ?? '');
            if ($semester_term === '') {
                $rawSem = trim($stRow['semester'] ?? '');
                if (preg_match('/^(1st Semester|2nd Semester|Summer|Midyear)/i', $rawSem, $m)) {
                    $semester_term = $m[1];
                }
            }
        }
    }

    // Build filter clauses — both semester AND year_level are required for correct counts
    $semFilter  = ($semester_term !== '') ? "AND c.semester LIKE '$semester_term%'" : '';
    $ylFilter   = ($year_level !== '')    ? "AND c.year_level = '$year_level'"       : '';
    $sfNoJoin   = ($semester_term !== '') ? "AND semester LIKE '$semester_term%'"    : '';
    $ylNoJoin   = ($year_level !== '')    ? "AND year_level = '$year_level'"         : '';

    // 2. Sum from program_courses → programs → courses, filtered by year_level + semester
    if ($units <= 0) {
        $units_res = $conn->query("
            SELECT COALESCE(SUM(c.credits), 0) AS total_units
            FROM program_courses pc
            JOIN programs p ON pc.program_id = p.id
            JOIN courses  c ON pc.course_id  = c.id
            WHERE (p.name = '$pn' OR p.code = '$pn')
            $ylFilter $semFilter
        ");
        $units_row = $units_res ? $units_res->fetch_assoc() : null;
        $units     = (int)($units_row['total_units'] ?? 0);
    }

    // 3. Fallback: courses.program column
    if ($units <= 0) {
        $fb    = $conn->query("SELECT COALESCE(SUM(credits),0) AS total_units FROM courses WHERE program='$pn' $ylNoJoin $sfNoJoin");
        $units = (int)(($fb ? $fb->fetch_assoc()['total_units'] : 0) ?: 0);
    }

    // 4. Hard fallback
    if ($units <= 0) $units = 18;

    // Scholar discount
    // Priority: override param (from login page before DB record exists) → DB scholarship_amount
    $discount = 0.00;
    // Scholar discount — detect full vs partial scholar
    $discount   = 0.00;
    $isScholar  = false;
    $isFullScholar = false;
    if ($override_discount !== null) {
        $discount = (float)$override_discount;
    } elseif ($student_id > 0) {
        $sr = $conn->prepare("SELECT is_scholar, scholarship_amount FROM students WHERE id = ?");
        $sr->bind_param("i", $student_id);
        $sr->execute();
        $srow      = $sr->get_result()->fetch_assoc();
        $sr->close();
        $isScholar         = (int)($srow['is_scholar']         ?? 0) === 1;
        $scholarshipAmount = (float)($srow['scholarship_amount'] ?? 0);
        // Will compute isFullScholar after subtotal is known (below)
        // FIX SCHOLAR-PENDING-DISCOUNT-04: If students.scholarship_amount is 0 (old record
        // registered before the patch saved scholarship_amount), read it directly from
        // the pending student_scholarships row as a fallback.
        if ($isScholar && $scholarshipAmount <= 0) {
            $pSchFb = $conn->query("SELECT scholarship_amount FROM student_scholarships WHERE student_id=$student_id AND status='pending' ORDER BY id DESC LIMIT 1");
            $pSchFbRow = $pSchFb ? $pSchFb->fetch_assoc() : null;
            if ($pSchFbRow && (float)$pSchFbRow['scholarship_amount'] > 0) {
                $scholarshipAmount = (float)$pSchFbRow['scholarship_amount'];
                // Also backfill students table so future reads don't need this fallback
                $conn->query("UPDATE students SET scholarship_amount=$scholarshipAmount WHERE id=$student_id AND (scholarship_amount IS NULL OR scholarship_amount=0)");
            }
        }
        $discount = $scholarshipAmount;
    }

    // Installment flag — override param (login page) or check existing tuition_fees record
    $has_installment = false;
    $student_semester = ''; // BUG-FEE-PREVIEW-01 FIX: always initialise so bind_param never uses an undefined variable
    if ($override_installment !== null) {
        $has_installment = $override_installment;
        // Resolve semester separately when the installment flag was passed as an override param
        if ($student_id > 0) {
            $semOnlySt = $conn->prepare("SELECT semester FROM students WHERE id = ? LIMIT 1");
            $semOnlySt->bind_param('i', $student_id);
            $semOnlySt->execute();
            $semOnlyRow = $semOnlySt->get_result()->fetch_assoc();
            $semOnlySt->close();
            $student_semester = trim($semOnlyRow['semester'] ?? '');
        }
    } elseif ($student_id > 0) {
        $piSt = $conn->prepare("SELECT payment_plan, semester FROM students WHERE id = ? LIMIT 1");
        $piSt->bind_param('i', $student_id);
        $piSt->execute();
        $pi_res = $piSt->get_result();
        $piSt->close();
        $pi_row = $pi_res ? $pi_res->fetch_assoc() : null;
        $has_installment  = ($pi_row['payment_plan'] ?? 'full') === 'installment';
        $student_semester = trim($pi_row['semester'] ?? '');
    }

    // Lab fee: based on total number of Laboratory rooms
    $lab_res   = $conn->query("SELECT COUNT(*) AS cnt FROM rooms WHERE room_type = 'Laboratory'");
    $lab_count = (int)(($lab_res ? $lab_res->fetch_assoc()['cnt'] : 0) ?? 0);

    // Load rates from fee_config (managed by Accounting)
    $fc = loadFeeConfig($conn, 'College');
    $r_tuition  = (float)($fc['tuition_rate_per_unit']['value'] ?? 650);
    $r_misc     = (float)($fc['misc_fee']['value']              ?? 6688);
    $r_reg      = (float)($fc['reg_fee']['value']               ?? 700);
    $r_lab_room = (float)($fc['lab_fee_per_room']['value']      ?? 1900);
    $r_energy   = (float)($fc['energy_rate_per_unit']['value']  ?? 63);
    $r_install  = (float)($fc['installment_fee']['value']       ?? 750);
    // Any extra active fees (not one of the standard built-in keys)
    $standard_keys = ['tuition_rate_per_unit','misc_fee','reg_fee','lab_fee_per_room','energy_rate_per_unit','installment_fee'];
    $extra_fees      = 0.00;
    $extra_fees_list = [];   // NEW: individual line items for the UI
    foreach ($fc as $fk => $frow) {
        if (!in_array($fk, $standard_keys)) {
            $line_amount = (float)$frow['value'] * ($frow['is_per_unit'] ? $units : 1);
            $extra_fees += $line_amount;
            $extra_fees_list[] = [
                'fee_key'    => $fk,
                'fee_label'  => $frow['fee_label'],
                'is_per_unit'=> (int)$frow['is_per_unit'],
                'rate'       => (float)$frow['value'],
                'amount'     => $line_amount,
            ];
        }
    }

    // Fee computation
    $tuition_fee    = $units * $r_tuition;
    $miscellaneous  = $r_misc;
    $registration   = $r_reg;
    $laboratory_fee = $lab_count * $r_lab_room;
    $energy_fee     = $units * $r_energy;
    $subtotal       = $tuition_fee + $miscellaneous + $registration + $laboratory_fee + $energy_fee + $extra_fees;
    $installment_fee = $has_installment ? $r_install : 0.00;

    // Full scholar: discount must always cover the CURRENT subtotal → ₱0 total
    // Re-check now that we know subtotal
    // FIX SCHOLAR-PENDING-FULL-01: Also treat a PENDING scholarship as full when
    // scholarship_amount >= subtotal. Before approval, is_active=0 — the old code
    // only checked is_active=1, so full-scholarship students still saw the full fee
    // on the payment instructions screen until Accounting approved.
    if ($isScholar && $subtotal > 0) {
        // Check approved (is_active=1) first
        $ssChk = $conn->query("SELECT id FROM student_scholarships WHERE student_id=$student_id AND is_active=1 LIMIT 1");
        if ($ssChk && $ssChk->num_rows > 0) {
            $discount      = $subtotal;
            $isFullScholar = true;
        } elseif (isset($scholarshipAmount) && $scholarshipAmount >= $subtotal) {
            // Declared amount covers full tuition (may be pending approval)
            $discount      = $subtotal;
            $isFullScholar = true;
        } elseif ($student_id > 0) {
            // Check pending scholarship amount from student_scholarships
            $pSchkR = $conn->query("SELECT scholarship_amount FROM student_scholarships WHERE student_id=$student_id AND status='pending' ORDER BY id DESC LIMIT 1");
            $pSchkRow = $pSchkR ? $pSchkR->fetch_assoc() : null;
            if ($pSchkRow && (float)$pSchkRow['scholarship_amount'] >= $subtotal) {
                $discount      = $subtotal;
                $isFullScholar = true;
            } else {
                // Fallback: check existing tuition_fees total
                $tfChkR = $conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id=$student_id LIMIT 1");
                $tfChkRow = $tfChkR ? $tfChkR->fetch_assoc() : null;
                if ($tfChkRow && (float)$tfChkRow['total_assessment'] <= 0) {
                    $discount      = $subtotal;
                    $isFullScholar = true;
                }
            }
        }
    }
    $total = $isFullScholar ? 0.00 : max(0, $subtotal - $discount + $installment_fee);

    // If student already has enrolled courses, use ACTUAL enrolled credits as units
    // This ensures SOA always matches the enrolled subjects list
    if ($student_id > 0) {
        $enrolledUnitsRes = $conn->query("
            SELECT COALESCE(SUM(c.credits), 0) AS enrolled_units
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            WHERE e.student_id = $student_id
              AND e.status IN ('Enrolled','Pending')
        ");
        $enrolledUnits = (int)(($enrolledUnitsRes ? $enrolledUnitsRes->fetch_assoc()['enrolled_units'] : 0) ?? 0);
        if ($enrolledUnits > 0) {
            // Recompute fees based on actual enrolled units
            $units          = $enrolledUnits;
            $tuition_fee    = $units * $r_tuition;
            $energy_fee     = $units * $r_energy;
            // recalculate extra per-unit fees
            $extra_fees      = 0.00;
            $extra_fees_list = [];
            foreach ($fc as $fk => $frow) {
                if (!in_array($fk, $standard_keys)) {
                    $line_amount = (float)$frow['value'] * ($frow['is_per_unit'] ? $units : 1);
                    $extra_fees += $line_amount;
                    $extra_fees_list[] = [
                        'fee_key'    => $fk,
                        'fee_label'  => $frow['fee_label'],
                        'is_per_unit'=> (int)$frow['is_per_unit'],
                        'rate'       => (float)$frow['value'],
                        'amount'     => $line_amount,
                    ];
                }
            }
            $subtotal       = $tuition_fee + $miscellaneous + $registration + $laboratory_fee + $energy_fee + $extra_fees;
            // Re-apply full scholar logic after unit recompute
            if ($isFullScholar) {
                $discount = $subtotal;
                $total    = 0.00;
            } else {
                $total = max(0, $subtotal - $discount + $installment_fee);
            }
        }
    }

    // Save / update tuition_fees if student_id provided
    if ($student_id > 0) {
        $stmt = $conn->prepare("
            INSERT INTO tuition_fees
                (student_id, units, tuition_fee, miscellaneous_fee, registration_fee,
                 laboratory_fee, energy_fee, subtotal, discount, installment_fee,
                 total_assessment, semester)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                units=VALUES(units), tuition_fee=VALUES(tuition_fee),
                miscellaneous_fee=VALUES(miscellaneous_fee),
                registration_fee=VALUES(registration_fee), laboratory_fee=VALUES(laboratory_fee),
                energy_fee=VALUES(energy_fee), subtotal=VALUES(subtotal),
                discount=VALUES(discount),
                installment_fee=IF(VALUES(installment_fee) > 0, VALUES(installment_fee), installment_fee),
                total_assessment=VALUES(total_assessment),
                semester=VALUES(semester)
        ");
        $stmt->bind_param("iiddddddddds",
            $student_id, $units, $tuition_fee, $miscellaneous, $registration,
            $laboratory_fee, $energy_fee, $subtotal, $discount, $installment_fee,
            $total, $student_semester);
        $stmt->execute();

        // For full scholars: sync scholarship_amount to match new subtotal
        // so SOA and dashboard always show ₱0 balance
        if ($isFullScholar && $subtotal > 0) {
            $conn->query("UPDATE students SET scholarship_amount=$subtotal, payment_status='Paid' WHERE id=$student_id");
            $conn->query("UPDATE student_scholarships SET scholarship_amount=$subtotal WHERE student_id=$student_id AND is_active=1");
        }
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success' => true,
        'fees' => [
            'units'            => $units,
            'tuitionFee'       => $tuition_fee,
            'miscellaneousFee' => $miscellaneous,
            'registrationFee'  => $registration,
            'laboratoryFee'    => $laboratory_fee,
            'energyFee'        => $energy_fee,
            'extraFees'        => $extra_fees_list,
            'subtotal'         => $subtotal,
            'discount'         => $discount,
            'installmentFee'   => $installment_fee,
            'totalAssessment'  => $total,
        ]
    ]);
}

// ─────────────────────────────────────────────────────────────
// COMPUTE & SAVE TUITION FEES
// POST ?action=compute_fees
// Body: { student_id, units, has_installment? }
//
// FORMULA:
//   Tuition Fee    = units × 650
//   Miscellaneous  = 6,688 (fixed)
//   Registration   = 700   (fixed)
//   Laboratory Fee = units × 1,900
//   Energy Fee     = units × 21 × 3
//   Subtotal       = sum of above
//   Discount       = scholar discount (from students table)
//   Installment    = 750 if installment, else 0
//   Total          = Subtotal - Discount + Installment Fee
// ─────────────────────────────────────────────────────────────
function computeFees($conn, $data) {
    $student_id      = (int)($data['student_id']      ?? 0);
    $units           = (int)($data['units']           ?? 18);
    // FIX INSTALLMENT-COLLEGE-01: Always read payment_plan from DB.
    // The has_installment flag from the request body is unreliable — callers
    // (including the enrollment wizard) sometimes omit it or send false even
    // when the student chose installment. DB is the single source of truth.
    $has_installment = isset($data['has_installment']) ? (bool)$data['has_installment'] : null;

    if (!$student_id || $units <= 0) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'student_id and units required']); return;
    }

    $sr = $conn->prepare("SELECT scholarship_amount, payment_plan FROM students WHERE id = ?");
    $sr->bind_param("i", $student_id);
    $sr->execute();
    $srow    = $sr->get_result()->fetch_assoc();
    $sr->close();
    $discount = (float)($srow['scholarship_amount'] ?? 0);
    // FIX INSTALLMENT-COLLEGE-01: If caller didn't pass has_installment, read from DB.
    // Covers College transferees where the wizard sends compute_fees without the flag.
    if ($has_installment === null) {
        $has_installment = ($srow['payment_plan'] ?? 'full') === 'installment';
    }

    // Count lab subjects for this student's program
    $progSt = $conn->prepare("SELECT program FROM students WHERE id = ? LIMIT 1");
    $progSt->bind_param('i', $student_id);
    $progSt->execute();
    $prog_res = $progSt->get_result();
    $progSt->close();
    $prog_row  = $prog_res ? $prog_res->fetch_assoc() : null;
    $prog_name = $prog_row['program'] ?? '';
    // Lab fee: based on total number of Laboratory rooms (same for all students)
    $lab_res2  = $conn->query("SELECT COUNT(*) AS cnt FROM rooms WHERE room_type = 'Laboratory'");
    $lab_count = (int)(($lab_res2 ? $lab_res2->fetch_assoc()['cnt'] : 0) ?? 0);

    $fc2 = loadFeeConfig($conn, 'College');
    $r_tuition  = (float)($fc2['tuition_rate_per_unit']['value'] ?? 650);
    $r_misc     = (float)($fc2['misc_fee']['value']              ?? 6688);
    $r_reg      = (float)($fc2['reg_fee']['value']               ?? 700);
    $r_lab_room = (float)($fc2['lab_fee_per_room']['value']      ?? 1900);
    $r_energy   = (float)($fc2['energy_rate_per_unit']['value']  ?? 63);
    $r_install  = (float)($fc2['installment_fee']['value']       ?? 750);
    $std_keys2  = ['tuition_rate_per_unit','misc_fee','reg_fee','lab_fee_per_room','energy_rate_per_unit','installment_fee'];
    $extra2 = 0.00;
    $extra2_list = [];
    foreach ($fc2 as $fk => $frow) {
        if (!in_array($fk, $std_keys2)) {
            $line_amt = (float)$frow['value'] * ($frow['is_per_unit'] ? $units : 1);
            $extra2 += $line_amt;
            $extra2_list[] = [
                'fee_key'    => $fk,
                'fee_label'  => $frow['fee_label'],
                'is_per_unit'=> (int)$frow['is_per_unit'],
                'rate'       => (float)$frow['value'],
                'amount'     => $line_amt,
            ];
        }
    }

    $tuition_fee    = $units * $r_tuition;
    $miscellaneous  = $r_misc;
    $registration   = $r_reg;
    $laboratory_fee = $lab_count * $r_lab_room;
    $energy_fee     = $units * $r_energy;
    $subtotal       = $tuition_fee + $miscellaneous + $registration + $laboratory_fee + $energy_fee + $extra2;
    $installment_fee = $has_installment ? $r_install : 0.00;
    $total          = max(0, $subtotal - $discount + $installment_fee);

    // Fetch student semester for tuition_fees record
    $semSt3 = $conn->prepare("SELECT semester FROM students WHERE id=? LIMIT 1");
    $semSt3->bind_param('i', $student_id); $semSt3->execute();
    $semRow3 = $semSt3->get_result()->fetch_assoc(); $semSt3->close();
    $sem3 = trim($semRow3['semester'] ?? '');
    $stmt = $conn->prepare("
        INSERT INTO tuition_fees (student_id, units, tuition_fee, miscellaneous_fee, registration_fee, laboratory_fee, energy_fee, subtotal, discount, installment_fee, total_assessment, semester)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            units=VALUES(units), tuition_fee=VALUES(tuition_fee), miscellaneous_fee=VALUES(miscellaneous_fee),
            registration_fee=VALUES(registration_fee), laboratory_fee=VALUES(laboratory_fee),
            energy_fee=VALUES(energy_fee), subtotal=VALUES(subtotal), discount=VALUES(discount),
            installment_fee=VALUES(installment_fee), total_assessment=VALUES(total_assessment),
            semester=VALUES(semester)
    ");
    $stmt->bind_param("iiddddddddds", $student_id, $units, $tuition_fee, $miscellaneous, $registration, $laboratory_fee, $energy_fee, $subtotal, $discount, $installment_fee, $total, $sem3);
    $stmt->execute();

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success' => true,
        'fees' => [
            'units'            => $units,
            'tuitionFee'       => $tuition_fee,
            'miscellaneousFee' => $miscellaneous,
            'registrationFee'  => $registration,
            'laboratoryFee'    => $laboratory_fee,
            'energyFee'        => $energy_fee,
            'extraFees'        => $extra2_list,
            'subtotal'         => $subtotal,
            'discount'         => $discount,
            'installmentFee'   => $installment_fee,
            'totalAssessment'  => $total,
        ]
    ]);
}

// ─────────────────────────────────────────────────────────────
// SHARED HELPER: build extraFees line items from fee_config
// ─────────────────────────────────────────────────────────────
function _buildExtraFeesList(mysqli $conn, string $category, int $units): array {
    $stdKeys = ['tuition_rate_per_unit','misc_fee','reg_fee','lab_fee_per_room','energy_rate_per_unit','installment_fee'];
    $stmt    = $conn->prepare("SELECT fee_key, fee_label, value, is_per_unit FROM fee_config WHERE category=? AND is_active=1 ORDER BY sort_order");
    $stmt->bind_param('s', $category);
    $stmt->execute();
    $res     = $stmt->get_result();
    $list    = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            if (!in_array($r['fee_key'], $stdKeys)) {
                $amt    = (float)$r['value'] * ($r['is_per_unit'] ? $units : 1);
                $list[] = [
                    'fee_key'    => $r['fee_key'],
                    'fee_label'  => $r['fee_label'],
                    'is_per_unit'=> (int)$r['is_per_unit'],
                    'rate'       => (float)$r['value'],
                    'amount'     => $amt,
                ];
            }
        }
    }
    return $list;
}

// ─────────────────────────────────────────────────────────────
// SHARED HELPER: _getInstallmentPaymentDates()
//
// For installment students, returns per-term date info for SOA display:
//
//   "dueDates" source (in priority order):
//     1. payment_notices.due_date  — the exact due date Accounting set when
//        they sent the payment notice for that period
//     2. sys_config payment_due_dates (scoped per semester, or global fallback)
//        — the date range string entered in the Accounting due-dates settings
//
//   "paidDate" source:
//     - The actual payment_date from installment_payments for that term
//       (earliest record if multiple partial payments exist)
//     - NULL if no payment has been recorded yet
//
// Return shape:
//   [
//     'Downpayment' => ['dueDate'=>'YYYY-MM-DD','dueDateRange'=>'...','paidDate'=>'YYYY-MM-DD'|null,'amountDue'=>float,'amountPaid'=>float,'status'=>'paid|unpaid|partial'],
//     'Prelim'      => [...],
//     'Midterm'     => [...],
//     'Finals'      => [...],
//   ]
//
// The student SOA component should:
//   - Show "Due: <dueDateRange or dueDate>" when paidDate is null
//   - Replace the due date cell with "Paid: <paidDate>  ₱<amountPaid>" when paidDate is set
// ─────────────────────────────────────────────────────────────
if (!function_exists('_getInstallmentPaymentDates')) {
    function _getInstallmentPaymentDates(mysqli $conn, int $student_id, string $semester = ''): array {

        // ── Step 1: Get actual paid amounts + earliest payment_date per term ──
        $semFilter = '';
        if ($semester !== '') {
            $safeSem   = $conn->real_escape_string($semester);
            $semFilter = "AND ip.semester = '$safeSem'";
        } else {
            // Scope to student's current semester by default
            $semFilter = "AND ip.semester = (SELECT semester FROM students WHERE id = $student_id LIMIT 1)";
        }

        $paidRes = $conn->query("
            SELECT ip.exam_period,
                   COALESCE(SUM(ip.amount), 0)           AS amount_paid,
                   MIN(ip.payment_date)                   AS first_paid_date,
                   MAX(ip.payment_date)                   AS last_paid_date
            FROM installment_payments ip
            WHERE ip.student_id = $student_id $semFilter
            GROUP BY ip.exam_period
        ");
        $paidByPeriod = [];
        if ($paidRes) {
            while ($r = $paidRes->fetch_assoc()) {
                $paidByPeriod[$r['exam_period']] = [
                    'amount_paid'     => (float)$r['amount_paid'],
                    'first_paid_date' => $r['first_paid_date'],
                    'last_paid_date'  => $r['last_paid_date'],
                ];
            }
        }

        // ── Step 2: Get due dates from payment_notices (most specific — set by Accounting) ──
        $noticeRes = $conn->query("
            SELECT exam_period, due_date, amount_due
            FROM payment_notices
            WHERE student_id = $student_id
        ");
        $noticeByPeriod = [];
        if ($noticeRes) {
            while ($r = $noticeRes->fetch_assoc()) {
                $noticeByPeriod[$r['exam_period']] = [
                    'due_date'   => $r['due_date'],
                    'amount_due' => (float)$r['amount_due'],
                ];
            }
        }

        // ── Step 3: Get date ranges from sys_config (global/scoped due dates) ──
        // FIX DUE-DATE-SOA-02: Three-tier lookup:
        //   3a. Exact scoped key for this student's semester  → most specific, always preferred
        //   3b. Global key — only when it belongs to the SAME semester as the student
        //       (prevents cross-semester bleed: e.g. future AY dates leaking into past SOAs)
        //   3c. No match → $configDates stays [] → SOA correctly shows blank dates
        //
        // The previous code only built $studentScopedKey for the "1st Semester, AY YYYY-YYYY"
        // combined format and blocked the global fallback when $studentScopedKey was empty,
        // so any student whose semester string didn't include ", AY" always got blank due dates.
        $configDates = [];
        $studentScopedKey = '';

        // Build scoped key — handle both "1st Semester, AY 2025-2026" and "1st Semester 2025-2026"
        if ($semester !== '') {
            if (preg_match('/^(.+?),\s*AY\s*(\d{4}-\d{4})/i', $semester, $m)) {
                $semSlug = preg_replace('/[^a-z0-9_]/', '_', strtolower(trim($m[1])));
                $yrSlug  = preg_replace('/[^0-9-]/', '', trim($m[2]));
                $studentScopedKey = "payment_due_dates:{$semSlug}:{$yrSlug}";
            } elseif (preg_match('/^(.+?)\s+(\d{4}-\d{4})/', $semester, $m)) {
                $semSlug = preg_replace('/[^a-z0-9_]/', '_', strtolower(trim($m[1])));
                $yrSlug  = preg_replace('/[^0-9-]/', '', trim($m[2]));
                $studentScopedKey = "payment_due_dates:{$semSlug}:{$yrSlug}";
            }
        }

        // Step 3a: exact scoped key
        if ($studentScopedKey !== '') {
            $cfgRes = $conn->query("SELECT config_value FROM sys_config WHERE config_key = '" . $conn->real_escape_string($studentScopedKey) . "' LIMIT 1");
            $cfgRow = $cfgRes ? $cfgRes->fetch_assoc() : null;
            if ($cfgRow && !empty($cfgRow['config_value'])) {
                $saved = json_decode($cfgRow['config_value'], true);
                if (is_array($saved)) { $configDates = $saved; }
            }
        }

        // Step 3b: global key fallback — verify it belongs to this student's semester.
        // FIX DUE-DATE-SOA-02 (v2): Use the payment_due_dates_active_semester marker
        // written by savePaymentDueDates instead of enrollment_period.  The marker
        // always holds the scoped key that the global fallback currently mirrors, so
        // the comparison is a direct string equality check — no enrollment_period
        // parsing needed, and the result stays correct even when the two are out of sync.
        if (empty($configDates)) {
            $markerRes = $conn->query("SELECT config_value FROM sys_config WHERE config_key = 'payment_due_dates_active_semester' LIMIT 1");
            $markerRow = $markerRes ? $markerRes->fetch_assoc() : null;
            $activeMarkerSOA2 = trim($markerRow['config_value'] ?? '');

            // Allow global fallback only when the marker's scoped key exactly matches
            // the student's own scoped key (same semester saved by accounting), OR
            // when the student has no parseable scoped key at all (legacy format).
            $useGlobal = ($studentScopedKey !== '' && $activeMarkerSOA2 === $studentScopedKey)
                      || ($studentScopedKey === '' && $activeMarkerSOA2 !== '');

            if ($useGlobal) {
                $globalSOA2 = $conn->query("SELECT config_value FROM sys_config WHERE config_key = 'payment_due_dates' LIMIT 1");
                $globalRowSOA2 = $globalSOA2 ? $globalSOA2->fetch_assoc() : null;
                if ($globalRowSOA2 && !empty($globalRowSOA2['config_value'])) {
                    $savedG2 = json_decode($globalRowSOA2['config_value'], true);
                    if (is_array($savedG2)) { $configDates = $savedG2; }
                }
            }
            // Mismatch → $configDates stays [] → blank due dates for past/other-semester student (correct)
        }

        // ── Step 4: Get per-term dues from payment_schedules ──────────────────
        $psRes = $conn->query("SELECT prelim_due, midterm_due, finals_due, downpayment_due, total_assessment FROM payment_schedules WHERE student_id = $student_id LIMIT 1");
        $ps    = $psRes ? $psRes->fetch_assoc() : null;

        // ── Step 5: Build result per term ─────────────────────────────────────
        $periods = [
            'Downpayment' => ['configKey' => 'downpayment', 'schedCol' => 'downpayment_due'],
            'Prelim'      => ['configKey' => 'prelim',      'schedCol' => 'prelim_due'],
            'Midterm'     => ['configKey' => 'midterm',     'schedCol' => 'midterm_due'],
            'Finals'      => ['configKey' => 'finals',      'schedCol' => 'finals_due'],
        ];

        $result = [];
        foreach ($periods as $periodName => $meta) {
            $paid       = $paidByPeriod[$periodName] ?? null;
            $notice     = $noticeByPeriod[$periodName] ?? null;
            $cfgEntry   = $configDates[$meta['configKey']] ?? null;

            // Due date: notice > config date_range
            $dueDate      = $notice['due_date']       ?? null;   // exact date from Accounting notice
            $dueDateRange = $cfgEntry['date_range']   ?? '';     // display string e.g. "JANUARY 15-16, 2026"

            // Amount due: notice > payment_schedules > 0
            $schedDue  = $ps ? (float)($ps[$meta['schedCol']] ?? 0) : 0;
            $amountDue = $notice ? $notice['amount_due'] : $schedDue;

            // Amount paid + payment date
            $amountPaid = $paid ? $paid['amount_paid']     : 0;
            // Use last_paid_date as the representative "paid on" date for SOA display
            $paidDate   = $paid ? $paid['last_paid_date']  : null;

            // Status
            if ($amountPaid <= 0) {
                $status = 'unpaid';
            } elseif ($amountDue > 0 && $amountPaid >= $amountDue) {
                $status = 'paid';
            } else {
                $status = 'partial';
            }

            $result[$periodName] = [
                'dueDate'      => $dueDate,      // exact YYYY-MM-DD from Accounting notice (may be null)
                'dueDateRange' => $dueDateRange, // display string from sys_config (e.g. "JAN 15-16")
                'paidDate'     => $paidDate,     // actual payment_date from installment_payments (null = not yet paid)
                'amountDue'    => round($amountDue,  2),
                'amountPaid'   => round($amountPaid, 2),
                'status'       => $status,
            ];
        }

        return $result;
    }
}

// ─────────────────────────────────────────────────────────────
// GET TUITION FEES for a student
// GET ?action=get_tuition_fees&student_id=XX
// ─────────────────────────────────────────────────────────────
function getTuitionFees($conn) {
    $student_id = (int)($_GET['student_id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$student_id) { echo json_encode(['success' => false, 'message' => 'student_id required']); return; }

    $tfStmt = $conn->prepare("SELECT * FROM tuition_fees WHERE student_id = ? LIMIT 1");
    $tfStmt->bind_param('i', $student_id);
    $tfStmt->execute();
    $res = $tfStmt->get_result();
    $tfStmt->close();
    $row = $res ? $res->fetch_assoc() : null;

    $paidSt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS total_paid FROM installment_payments WHERE student_id = ? AND semester=(SELECT semester FROM students WHERE id=? LIMIT 1)");
    $paidSt->bind_param('ii', $student_id, $student_id);
    $paidSt->execute();
    $paid_res = $paidSt->get_result();
    $paidSt->close();
    $total_paid = (float)($paid_res->fetch_assoc()['total_paid'] ?? 0);

    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$row) { echo json_encode(['success' => false, 'message' => 'No fee record found']); return; }

    $balance = max(0, (float)$row['total_assessment'] - $total_paid);

    // FIX TVET-FEES-02: Resolve fee category from student record so TVET/SHS
    // students get the correct extra-fee labels, not College labels.
    $fcatRes = $conn->query("SELECT student_category FROM students WHERE id=$student_id LIMIT 1");
    $fcatRow = $fcatRes ? $fcatRes->fetch_assoc() : null;
    $feeCat  = match(strtoupper(trim($fcatRow['student_category'] ?? ''))) {
        'SHS'  => 'SHS',
        'TVET' => 'TVET',
        default => 'College',
    };
    $extraList = _buildExtraFeesList($conn, $feeCat, (int)$row['units']);

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success' => true,
        'fees' => [
            'units'            => (int)$row['units'],
            'tuitionFee'       => (float)$row['tuition_fee'],
            'miscellaneousFee' => (float)$row['miscellaneous_fee'],
            'registrationFee'  => (float)$row['registration_fee'],
            'laboratoryFee'    => (float)$row['laboratory_fee'],
            'energyFee'        => (float)$row['energy_fee'],
            'extraFees'        => $extraList,
            'subtotal'         => (float)$row['subtotal'],
            'discount'         => (float)$row['discount'],
            'installmentFee'   => (float)$row['installment_fee'],
            'totalAssessment'  => (float)$row['total_assessment'],
            'totalPaid'        => $total_paid,
            'balance'          => $balance,
            'paymentStatus'    => $balance <= 0 ? 'Fully Paid' : ($total_paid > 0 ? 'Partial' : 'Unpaid'),
        ]
    ]);
}

// ─────────────────────────────────────────────────────────────
// RECORD INSTALLMENT PAYMENT
// POST ?action=record_installment
// Body: { student_id, amount, payment_date, payment_method, gcash_reference?, exam_period, notes?,
//         accounting_user_id, or_ar_type, or_ar_number }
//
// CHANGE OR-MANUAL-01: or_ar_number is now a REQUIRED manual input from the cashier.
// The cashier must type the exact OR number from the physical official receipt so that
// the system record matches what the student physically holds.
// The old auto-sequence logic (or_ar_sequences table) is no longer used here.
// ─────────────────────────────────────────────────────────────
function recordInstallment($conn, $data) {
    $student_id     = (int)($data['student_id']         ?? 0);
    $amount         = (float)($data['amount']            ?? 0);
    $payment_date   = trim($data['payment_date']        ?? date('Y-m-d'));
    $payment_method = trim($data['payment_method']      ?? 'Cash');
    $gcash_ref      = trim($data['gcash_reference']     ?? '');
    $exam_period    = trim($data['exam_period']         ?? 'Downpayment');
    $notes          = trim($data['notes']               ?? '');
    $acc_user_id    = (int)($data['accounting_user_id'] ?? 0);
    $or_ar_type     = trim($data['or_ar_type']          ?? 'AR');

    // CHANGE OR-MANUAL-01: Read the OR/AR number supplied by the cashier.
    // This must match the number printed on the physical official receipt.
    $or_ar_no = trim($data['or_ar_number'] ?? '');

    if (!$student_id || $amount <= 0) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'student_id and amount required']); return;
    }

    // CHANGE OR-MANUAL-01: Reject the request if the cashier did not provide an OR/AR number.
    if ($or_ar_no === '') {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'OR/AR number is required. Please enter the number from the physical official receipt.']); return;
    }

    // CHANGE OR-MANUAL-01: Prevent duplicate OR/AR numbers across all payments.
    // Two receipts with the same number would make reconciliation impossible.
    $dupOrStmt = $conn->prepare("SELECT id FROM installment_payments WHERE or_ar_number = ? LIMIT 1");
    if ($dupOrStmt) {
        $dupOrStmt->bind_param('s', $or_ar_no);
        $dupOrStmt->execute();
        $dupOrRow = $dupOrStmt->get_result()->fetch_assoc();
        $dupOrStmt->close();
        if ($dupOrRow) {
            while (ob_get_level() > 0) { ob_end_clean(); }
            echo json_encode(['success' => false, 'message' => "OR/AR number '{$or_ar_no}' already exists in the system. Please check the physical receipt and enter the correct number."]);
            return;
        }
    }

    $stmt = $conn->prepare("
        INSERT INTO installment_payments (student_id, or_ar_number, or_ar_type, amount, payment_date, payment_method, gcash_reference, exam_period, notes, recorded_by, semester)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, (SELECT semester FROM students WHERE id=? LIMIT 1))
    ");
    $stmt->bind_param("issdsssssii", $student_id, $or_ar_no, $or_ar_type, $amount, $payment_date, $payment_method, $gcash_ref, $exam_period, $notes, $acc_user_id, $student_id);
    $stmt->execute();

    // Check if fully paid
    $feeStmt = $conn->prepare("SELECT total_assessment FROM tuition_fees WHERE student_id = ? LIMIT 1");
    $feeStmt->bind_param("i", $student_id);
    $feeStmt->execute();
    $fee_res = $feeStmt->get_result();
    $fee_row          = $fee_res ? $fee_res->fetch_assoc() : null;
    $total_assessment = (float)($fee_row['total_assessment'] ?? 0);

    $paidStmt2 = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS tp FROM installment_payments WHERE student_id = ? AND semester=(SELECT semester FROM students WHERE id=? LIMIT 1)");
    $paidStmt2->bind_param("ii", $student_id, $student_id);
    $paidStmt2->execute();
    $paid_res = $paidStmt2->get_result();
    $total_paid = (float)($paid_res->fetch_assoc()['tp'] ?? 0);
    $is_fully_paid = $total_assessment > 0 && $total_paid >= $total_assessment;

    // Use 'Partial' so Accounting side reflects actual partial payment state.
    // 'Pending' is reserved for students who have NOT paid anything yet.
    $pay_status = $is_fully_paid ? 'Paid' : ($total_paid > 0 ? 'Partial' : 'Pending');

    // FIX: Read current enrollment_status BEFORE updating.
    // For already-enrolled students paying Prelim/Midterm/Finals, we must NOT
    // touch enrollment_status — resetting it to 'Confirmed' kicks them out of
    // 'Enrolled' and breaks their enrollment until the registrar re-confirms.
    $stChk = $conn->prepare("SELECT enrollment_status FROM students WHERE id = ? LIMIT 1");
    $stChk->bind_param('i', $student_id);
    $stChk->execute();
    $currentEnrollStatus = $stChk->get_result()->fetch_assoc()['enrollment_status'] ?? '';
    $stChk->close();
    $isAlreadyEnrolled = ($currentEnrollStatus === 'Enrolled');

    if ($is_fully_paid) {
        if ($isAlreadyEnrolled) {
            // Already enrolled — only update payment_status, leave enrollment untouched
            $updFull = $conn->prepare("UPDATE students SET payment_status='Paid' WHERE id=?");
            $updFull->bind_param("i", $student_id);
        } else {
            // First full payment — approve and move to Confirmed (Registrar confirms next)
            $updFull = $conn->prepare("UPDATE students SET payment_status='Paid', approval_status='Approved', enrollment_status='Confirmed' WHERE id=?");
            $updFull->bind_param("i", $student_id);
        }
        $updFull->execute();
        $updFull->close();
    } else {
        if ($isAlreadyEnrolled) {
            // Already enrolled — only update payment_status
            $updSt = $conn->prepare("UPDATE students SET payment_status=? WHERE id=?");
            $updSt->bind_param('si', $pay_status, $student_id);
        } else {
            // Partially paid — mark Approved so student can see SOA, awaiting registrar
            $updSt = $conn->prepare("UPDATE students SET payment_status=?, approval_status='Approved', enrollment_status='Confirmed' WHERE id=?");
            $updSt->bind_param('si', $pay_status, $student_id);
        }
        $updSt->execute();
        $updSt->close();
    }

    // ── Sync payment_schedules — recompute dues from actual DP, actual paid per period ──
    // Rule: DP can be any amount. Term dues = (total - dpPaid) / 3.
    // Each period's paid = actual SUM from installment_payments. No carry-over between terms.
    $schedChkStmt = $conn->prepare("SELECT id FROM payment_schedules WHERE student_id = ? LIMIT 1");
    $schedChkStmt->bind_param("i", $student_id);
    $schedChkStmt->execute();
    $sched_check = $schedChkStmt->get_result();
    if ($sched_check && $sched_check->num_rows > 0) {
        // Step 1: Get total assessment
        $tfR2 = $conn->prepare("SELECT total_assessment FROM tuition_fees WHERE student_id = ? LIMIT 1");
        $tfR2->bind_param('i', $student_id);
        $tfR2->execute();
        $tfR = $tfR2->get_result();
        $tfR2->close();
        $tfRw = $tfR ? $tfR->fetch_assoc() : null;
        $tot  = $tfRw ? (float)$tfRw['total_assessment'] : $total_assessment;

        // Step 2: Get actual DP paid
        $dpSt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS paid FROM installment_payments WHERE student_id = ? AND exam_period = 'Downpayment' AND semester=(SELECT semester FROM students WHERE id=? LIMIT 1)");
        $dpSt->bind_param('ii', $student_id, $student_id);
        $dpSt->execute();
        $dpR = $dpSt->get_result();
        $dpSt->close();
        $dpPd  = $dpR ? (float)$dpR->fetch_assoc()['paid'] : 0;
        // Fall back to scheduled quarter if no DP recorded yet
        $dpEff = $dpPd > 0 ? $dpPd : ($tot > 0 ? round($tot / 4, 2) : 0);

        // Steps 3+4: Recompute all dues and paid amounts dynamically.
        // recomputeSchedule() redistributes remaining balance across unpaid terms
        // and stores actual paid per period from installment_payments.
        recomputeSchedule($conn, $student_id);
    }
    // ─────────────────────────────────────────────────────────────────────

    logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'RECORD_PAYMENT', 'student', $student_id,
        "Recorded $exam_period ₱" . number_format($amount, 2) . " for student ID $student_id (OR: $or_ar_no)");

    // FIX SOA-SNAPSHOT-03: Refresh the SOA snapshot every time a payment is recorded
    // directly by the cashier so the Accounting SOA viewer stays in sync.
    if (function_exists('saveSoaSnapshot')) {
        saveSoaSnapshot($conn, $student_id);
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'     => true,
        'message'     => 'Payment recorded.',
        'orArNumber'  => $or_ar_no,
        'orArType'    => $or_ar_type,
        'totalPaid'   => $total_paid,
        'balance'     => max(0, $total_assessment - $total_paid),
        'isFullyPaid' => $is_fully_paid,
    ]);
}

// ─────────────────────────────────────────────────────────────
// GET STUDENT RECEIPTS (student view)
// GET ?action=get_student_receipts&student_id=XX
// ─────────────────────────────────────────────────────────────
function getStudentReceipts($conn) {
    $student_id = (int)($_GET['student_id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$student_id) { echo json_encode(['success' => false, 'message' => 'student_id required']); return; }

    $feeStmt = $conn->prepare("SELECT * FROM tuition_fees WHERE student_id = ? LIMIT 1");
    $feeStmt->bind_param('i', $student_id);
    $feeStmt->execute();
    $fee_res = $feeStmt->get_result();
    $feeStmt->close();
    $fee_row = $fee_res ? $fee_res->fetch_assoc() : null;

    $stStmt2 = $conn->prepare("SELECT first_name, last_name, program, year_level, student_number, payment_plan, student_category FROM students WHERE id = ? LIMIT 1");
    $stStmt2->bind_param('i', $student_id);
    $stStmt2->execute();
    $st_res = $stStmt2->get_result();
    $stStmt2->close();
    $student = $st_res ? $st_res->fetch_assoc() : null;
    $paymentPlan = $student['payment_plan'] ?? 'full';

    // Self-heal payment_plan: if AR records exist in installment_payments, student is installment
    if ($paymentPlan === 'full') {
        $arChk = $conn->prepare("SELECT id FROM installment_payments WHERE student_id = ? AND or_ar_type = 'AR' LIMIT 1");
        $arChk->bind_param('i', $student_id);
        $arChk->execute();
        $arCheck = $arChk->get_result();
        $arChk->close();
        if ($arCheck && $arCheck->num_rows > 0) {
            $paymentPlan = 'installment';
            $psUpdStmt = $conn->prepare("UPDATE payment_schedules SET payment_type = 'installment' WHERE student_id = ?");
            $psUpdStmt->bind_param('i', $student_id);
            $psUpdStmt->execute();
            $psUpdStmt->close();
        }
    }

    // FIX AC-05: Removed self-healing fee recomputation on every receipt load.
    // Fee corrections must be done explicitly via the computeFees action, not silently on read.

    // If tuition_fees row is missing, auto-compute and save it now
    // NOTE: Do NOT override if row already exists — registrar may have set correct values
    if (!$fee_row && $student) {
        $programName     = trim($student['program'] ?? '');
        $has_installment = ($paymentPlan === 'installment');

        // Get units — TOR approved_units first (transferees), then program_courses, then courses
        $units  = 0;
        $teSt2 = $conn->prepare("SELECT approved_units FROM tor_evaluations WHERE student_id = ? AND status = 'Evaluated' LIMIT 1");
        $teSt2->bind_param('i', $student_id);
        $teSt2->execute();
        $te = $teSt2->get_result();
        $teSt2->close();
        $te_row = $te ? $te->fetch_assoc() : null;
        if ($te_row && (int)$te_row['approved_units'] > 0) {
            $units = (int)$te_row['approved_units'];
        }
        if ($units <= 0 && $programName) {
            $pn = $programName;
            $hasPCTable = $conn->query("SHOW TABLES LIKE 'program_courses'")->num_rows > 0;
            $hasPTable  = $conn->query("SHOW TABLES LIKE 'programs'")->num_rows > 0;
            if ($hasPCTable && $hasPTable) {
                $ur = $conn->query("SELECT SUM(c.credits) AS total FROM program_courses pc JOIN programs p ON pc.program_id=p.id JOIN courses c ON pc.course_id=c.id WHERE p.name='$pn' OR p.code='$pn'");
                $u  = $ur ? (int)($ur->fetch_assoc()['total'] ?? 0) : 0;
                if ($u > 0) $units = $u;
            }
            if ($units <= 0) {
                $ur = $conn->query("SELECT SUM(credits) AS total FROM courses WHERE program='$pn'");
                $u  = $ur ? (int)($ur->fetch_assoc()['total'] ?? 0) : 0;
                if ($u > 0) $units = $u;
            }
        }
        if ($units <= 0) $units = 18;

        // Count lab subjects (room LIKE '%Lab%') for this program — merge both sources
        $pn_esc  = $programName;
        $lab_res = $conn->query("
            SELECT COUNT(DISTINCT c.id) AS cnt FROM courses c
            WHERE c.is_lab = 1
              AND (c.program = '$pn_esc'
                OR c.id IN (SELECT pc.course_id FROM program_courses pc JOIN programs p ON pc.program_id=p.id WHERE p.name='$pn_esc' OR p.code='$pn_esc'))
        ");
        $lab_cnt = (int)(($lab_res ? $lab_res->fetch_assoc()['cnt'] : 0) ?? 0);

        // Compute fees
        $fc3 = loadFeeConfig($conn, 'College');
        $r3_tuition  = (float)($fc3['tuition_rate_per_unit']['value'] ?? 650);
        $r3_misc     = (float)($fc3['misc_fee']['value']              ?? 6688);
        $r3_reg      = (float)($fc3['reg_fee']['value']               ?? 700);
        $r3_lab_room = (float)($fc3['lab_fee_per_room']['value']      ?? 1900);
        $r3_energy   = (float)($fc3['energy_rate_per_unit']['value']  ?? 63);
        $r3_install  = (float)($fc3['installment_fee']['value']       ?? 750);
        $tuition       = $units * $r3_tuition;
        $miscellaneous = $r3_misc;
        $registration  = $r3_reg;
        $laboratory    = $lab_cnt * $r3_lab_room;
        $energy        = $units * $r3_energy;
        $subtotal      = $tuition + $miscellaneous + $registration + $laboratory + $energy;

        $discStmt = $conn->prepare("SELECT is_scholar, scholarship_amount FROM students WHERE id = ? LIMIT 1");
        $discStmt->bind_param('i', $student_id);
        $discStmt->execute();
        $disc_res = $discStmt->get_result();
        $discStmt->close();
        $disc_row  = $disc_res ? $disc_res->fetch_assoc() : null;
        $discount  = ($disc_row && $disc_row['is_scholar']) ? (float)($disc_row['scholarship_amount'] ?? 0) : 0;

        $install_fee = $has_installment ? $r3_install : 0.00;
        $total       = max(0, $subtotal - $discount + $install_fee);

        // Fetch current semester to stamp on tuition_fees row
        $semRes4 = $conn->query("SELECT semester FROM students WHERE id=$student_id LIMIT 1");
        $sem4 = trim(($semRes4 ? $semRes4->fetch_assoc()['semester'] : '') ?? '');
        $safeSem4 = $conn->real_escape_string($sem4);
        $conn->query("INSERT INTO tuition_fees (student_id, units, tuition_fee, miscellaneous_fee, registration_fee, laboratory_fee, energy_fee, subtotal, discount, installment_fee, total_assessment, semester)
            VALUES ($student_id, $units, $tuition, $miscellaneous, $registration, $laboratory, $energy, $subtotal, $discount, $install_fee, $total, '$safeSem4')
            ON DUPLICATE KEY UPDATE units=$units, tuition_fee=$tuition, miscellaneous_fee=$miscellaneous, registration_fee=$registration, laboratory_fee=$laboratory, energy_fee=$energy, subtotal=$subtotal, discount=$discount, installment_fee=$install_fee, total_assessment=$total, semester='$safeSem4', updated_at=NOW()");

        $feeStmt2 = $conn->prepare("SELECT * FROM tuition_fees WHERE student_id = ? LIMIT 1");
        $feeStmt2->bind_param('i', $student_id);
        $feeStmt2->execute();
        $fee_res2 = $feeStmt2->get_result();
        $feeStmt2->close();
        $fee_row  = $fee_res2 ? $fee_res2->fetch_assoc() : null;
    }

    $ip_res = $conn->query("
        SELECT ip.*, COALESCE(sp.first_name, f2.first_name) AS recorded_by_name
        FROM installment_payments ip
        LEFT JOIN users u ON ip.recorded_by = u.id
        LEFT JOIN staff_profiles sp ON sp.user_id = u.id
        LEFT JOIN faculty f2 ON f2.user_id = u.id
        WHERE ip.student_id = $student_id
        ORDER BY ip.payment_date ASC, ip.created_at ASC
    ");
    $payments   = [];
    $total_paid = 0;
    if ($ip_res) {
        while ($r = $ip_res->fetch_assoc()) {
            $payments[] = [
                'id'             => (int)$r['id'],
                'orArNumber'     => $r['or_ar_number'],
                'orArType'       => $r['or_ar_type'],
                'amount'         => (float)$r['amount'],
                'paymentDate'    => $r['payment_date'],
                'paymentMethod'  => $r['payment_method'],
                'gcashReference' => $r['gcash_reference'],
                'examPeriod'     => $r['exam_period'],
                'notes'          => $r['notes'],
                'recordedByName' => $r['recorded_by_name'] ?? 'Accounting',
            ];
            $total_paid += (float)$r['amount'];
        }
    }

    // FALLBACK: if no installment_payments records exist yet but payment_logs shows Verified,
    // backfill from payment_logs so existing verified students see their paid amount
    if (empty($payments)) {
        $pl_res = $conn->query("
            SELECT pl.*, COALESCE(sp.first_name, f2.first_name) AS verified_by_name
            FROM payment_logs pl
            LEFT JOIN users u ON pl.verified_by = u.id
            LEFT JOIN staff_profiles sp ON sp.user_id = u.id
            LEFT JOIN faculty f2 ON f2.user_id = u.id
            WHERE pl.student_id = $student_id AND pl.status = 'Verified'
            ORDER BY pl.verified_at ASC
        ");
        if ($pl_res) {
            while ($r = $pl_res->fetch_assoc()) {
                $amount = (float)$r['gcash_amount'];
                // For old cash payments verified before the amount-capture fix,
                // gcash_amount was saved as 0 — use total_assessment instead
                if ($amount <= 0 && $fee_row) {
                    $amount = (float)$fee_row['total_assessment'];
                }

                // Auto-create installment_payments record so future loads use the proper table
                $year     = date('Y', strtotime($r['verified_at']));
                $cntSt = $conn->prepare("SELECT COUNT(*) AS cnt FROM installment_payments WHERE YEAR(created_at) = ?");
                $cntSt->bind_param('i', $year);
                $cntSt->execute();
                $cntRes = $cntSt->get_result();
                $cntSt->close();
                $cnt      = (int)($cntRes->fetch_assoc()['cnt'] ?? 0) + 1;
                $pm       = strtolower($r['payment_method']) === 'cash' ? 'Cash' : 'GCash';
                $plan     = $paymentPlan === 'installment' ? 'installment' : 'full';
                $orType   = ($plan === 'installment') ? 'AR' : 'OR';
                $period   = ($plan === 'installment') ? 'Downpayment' : 'Full';
                $orNo     = $orType . '-' . $year . str_pad($cnt, 4, '0', STR_PAD_LEFT);
                $pDate    = $r['gcash_date'] ?? date('Y-m-d', strtotime($r['verified_at']));
                $gcashRef = $r['gcash_reference'] ?? '';
                $logId    = (int)$r['id'];
                $verBy    = (int)($r['verified_by'] ?? 0);

                // Only insert if not already there
                $dupQSt = $conn->prepare("SELECT id FROM installment_payments WHERE payment_log_id = ? LIMIT 1");
                $dupQSt->bind_param('i', $logId);
                $dupQSt->execute();
                $dupQRes = $dupQSt->get_result();
                $dupQSt->close();
                if ($dupQRes->num_rows === 0) {
                    $ipIns = $conn->prepare("INSERT INTO installment_payments
                        (student_id, payment_log_id, or_ar_number, or_ar_type, amount, payment_date, payment_method, gcash_reference, exam_period, notes, recorded_by, semester)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '', ?, (SELECT semester FROM students WHERE id=? LIMIT 1))");
                    $ipIns->bind_param('iissdssssii', $student_id, $logId, $orNo, $orType, $amount, $pDate, $pm, $gcashRef, $period, $verBy, $student_id);
                    $ipIns->execute();
                    $ipIns->close();
                }

                $payments[] = [
                    'id'             => $logId,
                    'orArNumber'     => $orNo,
                    'orArType'       => $orType,
                    'amount'         => $amount,
                    'paymentDate'    => $pDate,
                    'paymentMethod'  => $pm,
                    'gcashReference' => $gcashRef,
                    'examPeriod'     => $period,
                    'notes'          => '',
                    'recordedByName' => $r['verified_by_name'] ?? 'Accounting',
                ];
                $total_paid += $amount;
            }
        }
    }

    $total_assessment = $fee_row ? (float)$fee_row['total_assessment'] : 0;
    $balance          = max(0, $total_assessment - $total_paid);
    $is_fully_paid    = $total_assessment > 0 && $balance == 0;

    // Build per-term breakdown for installment students
    $termOrder     = ['Downpayment', 'Prelim', 'Midterm', 'Finals', 'Full'];
    $termBreakdown = [];
    foreach ($payments as $p) {
        $period = $p['examPeriod'];
        if (!isset($termBreakdown[$period])) {
            $termBreakdown[$period] = ['period' => $period, 'amountPaid' => 0, 'orArNumber' => '', 'orArType' => '', 'paymentDate' => '', 'paymentMethod' => ''];
        }
        $termBreakdown[$period]['amountPaid']    += $p['amount'];
        $termBreakdown[$period]['orArNumber']     = $p['orArNumber'];
        $termBreakdown[$period]['orArType']       = $p['orArType'];
        $termBreakdown[$period]['paymentDate']    = $p['paymentDate'];
        $termBreakdown[$period]['paymentMethod']  = $p['paymentMethod'];
    }
    // Sort by canonical term order
    $sortedTerms = [];
    foreach ($termOrder as $t) { if (isset($termBreakdown[$t])) $sortedTerms[] = $termBreakdown[$t]; }

    // ── Installment due dates vs actual paid dates (for SOA timeline) ─────────
    // Only meaningful for installment students — empty array for full-payment plans.
    $installmentDueDates = [];
    if ($paymentPlan === 'installment') {
        $stSemRes = $conn->query("SELECT semester FROM students WHERE id=$student_id LIMIT 1");
        $stSemRow = $stSemRes ? $stSemRes->fetch_assoc() : null;
        $currentSem = trim($stSemRow['semester'] ?? '');
        $installmentDueDates = _getInstallmentPaymentDates($conn, $student_id, $currentSem);
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'              => true,
        'student'              => $student,
        'paymentPlan'          => $paymentPlan,
        'fees'                 => $fee_row ? [
            'units'            => (int)$fee_row['units'],
            'tuitionFee'       => (float)$fee_row['tuition_fee'],
            'miscellaneousFee' => (float)$fee_row['miscellaneous_fee'],
            'registrationFee'  => (float)$fee_row['registration_fee'],
            'laboratoryFee'    => (float)$fee_row['laboratory_fee'],
            'energyFee'        => (float)$fee_row['energy_fee'],
            'extraFees'        => _buildExtraFeesList($conn, match(strtoupper(trim($student['student_category'] ?? ''))) { 'SHS' => 'SHS', 'TVET' => 'TVET', default => 'College' }, (int)$fee_row['units']),
            'subtotal'         => (float)$fee_row['subtotal'],
            'discount'         => (float)$fee_row['discount'],
            'installmentFee'   => (float)$fee_row['installment_fee'],
            'totalAssessment'  => $total_assessment,
        ] : null,
        'payments'             => $payments,
        'termBreakdown'        => $sortedTerms,
        // SOA-DUE-DATES: per-term due dates + actual paid dates.
        // Angular SOA: show "Due: <dueDateRange>" → swap to "Paid: <paidDate>" once paid.
        'installmentDueDates'  => $installmentDueDates,
        'totalPaid'            => $total_paid,
        'balance'              => $balance,
        'isFullyPaid'          => $is_fully_paid,
        'paymentStatus'        => $is_fully_paid ? 'Fully Paid' : ($total_paid > 0 ? 'Partial' : 'Unpaid'),
    ]);
}

// ─────────────────────────────────────────────────────────────
// LIQUIDATION / AUDIT REPORT
// GET ?action=get_liquidation&date_from=YYYY-MM-DD&date_to=YYYY-MM-DD
// ─────────────────────────────────────────────────────────────
function getLiquidation($conn) {
    $date_from = trim($_GET['date_from'] ?? date('Y-m-01'));
    $date_to   = trim($_GET['date_to']   ?? date('Y-m-d'));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = date('Y-m-01');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   $date_to   = date('Y-m-d');
    $df = $date_from;
    $dt = $date_to;

    $sql = "
        SELECT
            ip.id, ip.or_ar_number, ip.or_ar_type, ip.amount, ip.payment_date,
            ip.payment_method, ip.gcash_reference, ip.exam_period, ip.notes, ip.created_at,
            s.student_number, s.first_name, s.last_name, s.program, s.year_level,
            tf.total_assessment,
            COALESCE(sp.first_name, f2.first_name) AS recorded_by_name
        FROM installment_payments ip
        JOIN students s ON ip.student_id = s.id
        LEFT JOIN tuition_fees tf ON tf.student_id = ip.student_id
        LEFT JOIN users u ON ip.recorded_by = u.id
        LEFT JOIN staff_profiles sp ON sp.user_id = u.id
        LEFT JOIN faculty f2 ON f2.user_id = u.id
        WHERE ip.payment_date BETWEEN '$df' AND '$dt'
        ORDER BY ip.payment_date ASC, ip.created_at ASC
    ";

    $result     = $conn->query($sql);
    $rows       = [];
    $total_cash  = 0;
    $total_gcash = 0;

    if ($result) {
        while ($r = $result->fetch_assoc()) {
            $amt = (float)$r['amount'];
            if (strtolower($r['payment_method']) === 'cash') $total_cash  += $amt;
            else                                              $total_gcash += $amt;

            $rows[] = [
                'id'             => (int)$r['id'],
                'orArNumber'     => $r['or_ar_number'],
                'orArType'       => $r['or_ar_type'],
                'studentNumber'  => $r['student_number'],
                'studentName'    => $r['last_name'] . ', ' . $r['first_name'],
                'program'        => $r['program'],
                'yearLevel'      => $r['year_level'],
                'paymentMethod'  => $r['payment_method'],
                'gcashReference' => $r['gcash_reference'],
                'examPeriod'     => $r['exam_period'],
                'amount'         => $amt,
                'totalAssessment'=> (float)($r['total_assessment'] ?? 0),
                'paymentDate'    => $r['payment_date'],
                'notes'          => $r['notes'],
                'recordedBy'     => $r['recorded_by_name'] ?? 'Accounting',
                'createdAt'      => $r['created_at'],
            ];
        }
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    global $authUser;
    $rows = applyPrivacyList($rows, $authUser, 'financial');
    echo json_encode([
        'success'      => true,
        'dateFrom'     => $date_from,
        'dateTo'       => $date_to,
        'entries'      => $rows,
        'totalEntries' => count($rows),
        'totalCash'    => $total_cash,
        'totalGCash'   => $total_gcash,
        'grandTotal'   => $total_cash + $total_gcash,
    ]);
}

// ─────────────────────────────────────────────────────────────
// STUDENT: Submit GCash reference
// POST ?action=notify_cash_pending
// ─────────────────────────────────────────────────────────────
// Called by the frontend when a Cash student clicks "Proceed to Cash Payment".
// Sets payment_status = 'Pending' so Accounting can see them in the queue,
// and upserts a payment_log row so getPendingPayments() returns them.
function notifyCashPending($conn, $data) {
    $student_id = (int)($data['student_id'] ?? 0);
    $semester   = trim($data['semester']    ?? '');

    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'student_id required']);
        return;
    }

    // Pull authoritative semester from DB (same pattern as submitGcash)
    $semSt = $conn->prepare("SELECT semester, payment_method, payment_plan FROM students WHERE id = ? LIMIT 1");
    $semSt->bind_param('i', $student_id);
    $semSt->execute();
    $sRow = $semSt->get_result()->fetch_assoc();
    $semSt->close();

    if (!$sRow) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        return;
    }

    // Safety check — must be a Cash student
    if (strtolower(trim($sRow['payment_method'] ?? '')) !== 'cash') {
        echo json_encode(['success' => false, 'message' => 'Student payment method is not Cash']);
        return;
    }

    $semester   = $sRow['semester'] ?: $semester;
    $examPeriod = ($sRow['payment_plan'] === 'installment') ? 'Downpayment' : 'Full';

    // Mark student as payment-pending so Accounting queue picks them up
    // BUG-TVET-CASH-01 FIX: Also ensure payment_method='Cash' is persisted to students table.
    // For TVET students, payment_method may still be '' if updatePaymentPlan ran before
    // getTVETFee completed. Stamping it here guarantees getPendingPayments recovery logic
    // correctly routes this student to Cash (not GCash) in the Accounting queue.
    $upd = $conn->prepare("UPDATE students SET payment_status = 'Pending', payment_method = 'Cash' WHERE id = ?");
    $upd->bind_param('i', $student_id);
    $upd->execute();
    $upd->close();

    // Upsert payment_log — avoid duplicate if already created by getPendingPayments()
    $chk = $conn->prepare(
        "SELECT id FROM payment_logs WHERE student_id = ? AND semester = ? AND payment_method = 'Cash' LIMIT 1"
    );
    $chk->bind_param('is', $student_id, $semester);
    $chk->execute();
    $existing = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$existing) {
        $ins = $conn->prepare(
            "INSERT INTO payment_logs
                 (student_id, payment_method, gcash_reference, gcash_amount, semester, exam_period, status)
             VALUES (?, 'Cash', '', 0, ?, ?, 'Pending')"
        );
        $ins->bind_param('iss', $student_id, $semester, $examPeriod);
        $ins->execute();
        $ins->close();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Cash payment pending. Please proceed to the Accounting Office.',
    ]);
}

// POST ?action=submit_gcash
// ─────────────────────────────────────────────────────────────
function submitGcash($conn, $data) {
    $student_id = (int)($data['student_id']     ?? 0);
    $reference  = trim($data['gcash_reference'] ?? '');
    $amount     = (float)($data['gcash_amount'] ?? 0);
    $date       = trim($data['gcash_date']      ?? date('Y-m-d'));
    $txn_id     = trim($data['transaction_id']  ?? '');
    $semester   = trim($data['semester']        ?? '');

    // FIX AC-SEMESTER-01: Always use students.semester as authoritative source.
    // After reEnroll(), students.semester is already the new label. The Angular
    // client may still carry the old semester from the previous session cache.
    // Override with DB value so payment_logs is never stamped with stale semesters.
    if ($student_id > 0) {
        $semDbSt = $conn->prepare("SELECT semester FROM students WHERE id = ? LIMIT 1");
        $semDbSt->bind_param('i', $student_id);
        $semDbSt->execute();
        $semDbRow = $semDbSt->get_result()->fetch_assoc();
        $semDbSt->close();
        $dbSemester = trim($semDbRow['semester'] ?? '');
        if ($dbSemester !== '') {
            $semester = $dbSemester;
        }
    }

    if (!$student_id || !$reference || !$amount) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'student_id, gcash_reference and gcash_amount are required']);
        return;
    }

    // FIX: SQL comment inside prepared string is invalid; duplicate payment_status column;
    // non-existent column gcash_transaction_id. GCash data goes to payment_logs.
    // Only reset student payment_status to Pending while awaiting accounting verification.
    // FIX REJECT-NOTES-01: Also clear rejection_reason so the old rejection message
    // doesn't linger after the student has corrected and resubmitted their payment.
    $conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS rejection_reason TEXT DEFAULT NULL");
    $stmt = $conn->prepare("UPDATE students SET payment_status = 'Pending', rejection_reason = NULL WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $stmt->close();

    // Auto-detect exam_period based on payment_plan and what's already been paid
    $stPlanSt = $conn->prepare("SELECT payment_plan, enrollment_status FROM students WHERE id=? LIMIT 1");
    $stPlanSt->bind_param('i', $student_id);
    $stPlanSt->execute();
    $stPlan = $stPlanSt->get_result()->fetch_assoc();
    $stPlanSt->close();
    $paymentPlan      = $stPlan['payment_plan']      ?? null;
    $enrollmentStatus = $stPlan['enrollment_status'] ?? '';

    // FIX AC-PLAN-01: Block GCash submission if student hasn't selected a payment plan yet.
    // This happens on re-enrollment — reEnroll() resets payment_plan to NULL so the student
    // must explicitly choose full vs installment before paying. Without this guard, NULL
    // falls through to 'full' and skips the installment option entirely.
    if ($paymentPlan === null || $paymentPlan === '') {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode([
            'success'   => false,
            'need_plan' => true,
            'message'   => 'Please select a payment plan (full or installment) before submitting payment.',
        ]);
        return;
    }
    $exam_period    = '';
    if ($paymentPlan === 'installment') {
        // FIX GCASH-TERM-01: Scope to the student's CURRENT semester only.
        // Without this, a re-enrolled student who paid Downpayment last semester has that
        // row in installment_payments, so the unscoped query finds 'Downpayment' in paidList
        // and sets exam_period='Prelim' — skipping Downpayment for the new semester entirely.
        // This caused payment_logs to be stamped with the wrong exam_period, verifyPayment to
        // issue an AR (term type) instead of the initial Downpayment AR, and the schedule to
        // be updated as if Prelim was paid when the student was actually paying their downpayment.
        $paidTermsSt = $conn->prepare("
            SELECT DISTINCT ip.exam_period
            FROM installment_payments ip
            JOIN students _st ON _st.id = ip.student_id
            WHERE ip.student_id = ?
              AND ip.amount > 0
              AND ip.semester = _st.semester
        ");
        $paidTermsSt->bind_param('i', $student_id);
        $paidTermsSt->execute();
        $paidTerms = $paidTermsSt->get_result();
        $paidTermsSt->close();
        $paidList  = [];
        if ($paidTerms) while ($r = $paidTerms->fetch_assoc()) $paidList[] = $r['exam_period'];
        if (!in_array('Downpayment', $paidList))      $exam_period = 'Downpayment';
        elseif (!in_array('Prelim', $paidList))       $exam_period = 'Prelim';
        elseif (!in_array('Midterm', $paidList))      $exam_period = 'Midterm';
        else                                           $exam_period = 'Finals';
    } else {
        $exam_period = 'Full';
    }

    // ── Lock check: Prelim/Midterm/Finals require accounting to send a notice first ──
    // Accounting unlocks each period by sending a payment notice (send_payment_notice)
    // or using unlock_payment_period. Until unlocked, payment_schedules.{period}_status
    // stays 'locked' and the student should not be able to submit a payment.
    if (in_array($exam_period, ['Prelim', 'Midterm', 'Finals'])) {
        $p        = strtolower($exam_period);
        $lockChk  = $conn->prepare("SELECT {$p}_status FROM payment_schedules WHERE student_id = ? LIMIT 1");
        $lockChk->bind_param('i', $student_id);
        $lockChk->execute();
        $lockRow  = $lockChk->get_result()->fetch_assoc();
        $lockChk->close();
        $periodStatus = $lockRow[$p . '_status'] ?? 'locked';
        if ($periodStatus === 'locked') {
            while (ob_get_level() > 0) { ob_end_clean(); }
            echo json_encode([
                'success' => false,
                'locked'  => true,
                'message' => "$exam_period payment is not yet open. Please wait for a payment notice from Accounting.",
            ]);
            return;
        }
    }

    // BUG-GCASH-01: Block GCash submission for Cash students.
    // A Cash student who re-enrolled would reach here if the frontend mistakenly
    // called submit_gcash instead of showing the Cash payment UI.
    // Also prevents the existing Cash payment_log (auto-created for Cash students)
    // from being overwritten with GCash data, which caused GCash to always appear
    // in Accounting even when the student chose Cash.
    $pmChkSt = $conn->prepare("SELECT payment_method FROM students WHERE id = ? LIMIT 1");
    $pmChkSt->bind_param('i', $student_id);
    $pmChkSt->execute();
    $pmChkRow = $pmChkSt->get_result()->fetch_assoc();
    $pmChkSt->close();
    // FIX PM-NULL-04: Do NOT default NULL/empty payment_method to 'gcash' here.
    // If the DB value is still empty (student just registered, method not written yet),
    // treat as unknown — do NOT allow a GCash submission to slip through for a student
    // who may have chosen Cash. The correct gate is: only block if explicitly 'cash'.
    $studentPaymentMethod = strtolower(trim($pmChkRow['payment_method'] ?? ''));
    if ($studentPaymentMethod === 'cash') {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode([
            'success'     => false,
            'is_cash'     => true,
            'message'     => 'Your payment method is Cash. Please proceed to the cashier — no GCash submission needed.',
        ]);
        return;
    }

    $checkStmt = $conn->prepare("SELECT id, payment_method FROM payment_logs WHERE student_id = ? AND status = 'Pending' LIMIT 1");
    $checkStmt->bind_param("i", $student_id);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();

    if ($existing) {
        // Update GCash-specific fields only. The WHERE clause guards against touching a Cash log
        // (belt-and-suspenders — the cash guard above already returned early for Cash students).
        $upd = $conn->prepare("
            UPDATE payment_logs
            SET gcash_reference=?, gcash_amount=?, gcash_date=?,
                transaction_id=?, semester=?, exam_period=?, payment_method='GCash'
            WHERE id=? AND LOWER(payment_method) != 'cash'
        ");
        // 7 params: reference(s), amount(d), date(s), txn_id(s), semester(s), exam_period(s), id(i)
        $upd->bind_param("sdssssi", $reference, $amount, $date, $txn_id, $semester, $exam_period, $existing['id']);
        $upd->execute();
        $log_id = $existing['id'];
    } else {
        $ins = $conn->prepare("
            INSERT INTO payment_logs (student_id, payment_method, gcash_reference, gcash_amount, gcash_date, transaction_id, semester, exam_period, status)
            VALUES (?, 'GCash', ?, ?, ?, ?, ?, ?, 'Pending')
        ");
        $ins->bind_param("isdssss", $student_id, $reference, $amount, $date, $txn_id, $semester, $exam_period);
        $ins->execute();
        $log_id = $ins->insert_id;
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'message' => 'GCash payment submitted. Waiting for accounting verification.', 'log_id' => $log_id, 'exam_period' => $exam_period]);
}

// ─────────────────────────────────────────────────────────────
// ACCOUNTING: Get pending payments
// ─────────────────────────────────────────────────────────────
function _getStudentPaymentPlan($conn, $sid) {
    $r = $conn->query("SELECT payment_plan FROM students WHERE id=$sid LIMIT 1");
    $plan = $r ? ($r->fetch_assoc()['payment_plan'] ?? '') : '';

    // FIX PAYMENT-PLAN-FALLBACK-01: students.payment_plan may be NULL/empty if
    // update_payment_plan() hadn't committed yet when the row was first read.
    // Fall back to payment_schedules (the source of truth written by the student
    // during enrollment) before defaulting to 'full'.
    if (!$plan || $plan === 'full') {
        $ps = $conn->query("SELECT payment_type FROM payment_schedules WHERE student_id=$sid ORDER BY id DESC LIMIT 1");
        if ($ps) {
            $psRow = $ps->fetch_assoc();
            if ($psRow && strtolower($psRow['payment_type'] ?? '') === 'installment') {
                $plan = 'installment';
            }
        }
    }

    return $plan ?: 'full';
}
// ─────────────────────────────────────────────────────────────────────────────
// SHARED HELPER: Compute installment term dues based on actual payments.
//
//   Rule (mirrors Angular enrollment.ts installmentAmounts getter):
//     - After each paid term, remaining balance is split EQUALLY among ALL
//       subsequent terms (paid or not — uses actual credit, not scheduled due)
//     - Overpay → all later terms get cheaper
//     - Underpay → all later terms get more expensive
//
//   Returns: ['downpayment'=>N, 'prelim'=>N, 'midterm'=>N, 'finals'=>N, 'total'=>N]
//   where each value is the DUE for that term (show actual paid if paid, else
//   the recomputed share).
// ─────────────────────────────────────────────────────────────────────────────
function _calcInstallmentDues(float $total, array $paid, array $approvedPeriods = []): array {
    // ─────────────────────────────────────────────────────────────────────────
    // Computes the DUE amount for each installment term given what was actually
    // paid per period.
    //
    //   Logic:
    //     - DP due = total / 4  (scheduled quarter)
    //     - DP credit = actual dpPaid (could be less OR more than scheduled)
    //     - Remaining after DP credit is split equally among remaining terms
    //     - This means underpayment at any term increases future term dues,
    //       and overpayment decreases them — balance always preserved.
    //
    //   FIX CARRY-OVER-01: $approvedPeriods parameter (array of period names
    //     like ['Prelim', 'Midterm']) tells this function which periods have
    //     an accounting-approved exam permit. For approved periods:
    //       • The credit used = ACTUAL amount paid (even if less than due).
    //       • The unpaid shortfall is AUTOMATICALLY carried to the next term.
    //       • The approved period is shown as 'paid' (balance = 0 for that term).
    //     For periods NOT yet approved (future terms), the credit falls back to
    //     the scheduled due so future dues compute correctly before any payment.
    //
    //   Returns:
    //     'downpayment' => the scheduled DP due (not what was paid)
    //     'prelim'      => computed due for prelim (after DP credit)
    //     'midterm'     => computed due for midterm (after DP+Prelim credit)
    //     'finals'      => whatever is left after all prior credits
    //     'balance'     => total - sum of all actual payments
    // ─────────────────────────────────────────────────────────────────────────
    if ($total <= 0) return ['downpayment'=>0.0,'prelim'=>0.0,'midterm'=>0.0,'finals'=>0.0,'total'=>0.0,'balance'=>0.0];

    $dpPaid  = (float)($paid['Downpayment'] ?? 0.0);
    $prPaid  = (float)($paid['Prelim']      ?? 0.0);
    $midPaid = (float)($paid['Midterm']     ?? 0.0);
    $finPaid = (float)($paid['Finals']      ?? 0.0);
    $allPaid = $dpPaid + $prPaid + $midPaid + $finPaid;

    // Approved-period flags — approved means the period is cleared regardless of partial payment
    $dpApproved  = in_array('Downpayment', $approvedPeriods, true);
    $prApproved  = in_array('Prelim',      $approvedPeriods, true);
    $midApproved = in_array('Midterm',     $approvedPeriods, true);

    $quarter = round($total / 4, 2);

    // ── Downpayment ───────────────────────────────────────────────────────────
    // Due = scheduled quarter. Credit = what was actually paid (may be less or more).
    $dpDue = $quarter;
    if ($dpApproved) {
        // Period cleared by approved permit: credit only what was paid (shortfall carries forward)
        $dpCredit = $dpPaid;
    } else {
        // Not yet approved: use actual paid if any, else use scheduled due so future terms compute correctly
        $dpCredit = $dpPaid > 0 ? $dpPaid : $dpDue;
    }

    // ── Prelim ────────────────────────────────────────────────────────────────
    // Remaining after DP credit, split among 3 terms
    $rem1  = max(0.0, $total - $dpCredit);
    $prDue = $rem1 > 0 ? ceil($rem1 / 3 * 100) / 100 : 0.0;
    if ($prApproved) {
        // FIX CARRY-OVER-01: Approved with partial payment — carry unpaid balance to Midterm/Finals.
        // Use actual paid as credit (not $prDue). The difference ($prDue - $prPaid) automatically
        // flows into $rem2 → midterm and finals dues become larger to absorb the shortfall.
        $prCredit = $prPaid;
    } else {
        // Not yet approved: use actual paid if any, else scheduled due (no carry-over yet)
        $prCredit = $prPaid > 0 ? $prPaid : $prDue;
    }

    // ── Midterm ───────────────────────────────────────────────────────────────
    // Remaining after DP + Prelim credit, split among 2 remaining terms
    $rem2   = max(0.0, $rem1 - $prCredit);
    $midDue = $rem2 > 0 ? ceil($rem2 / 2 * 100) / 100 : 0.0;
    if ($midApproved) {
        // Same carry-over logic: approved partial → shortfall flows into Finals
        $midCredit = $midPaid;
    } else {
        $midCredit = $midPaid > 0 ? $midPaid : $midDue;
    }

    // ── Finals ────────────────────────────────────────────────────────────────
    // Whatever is left after all prior credits
    $rem3   = max(0.0, $rem2 - $midCredit);
    $finDue = round($rem3, 2);

    return [
        'downpayment' => round($dpDue,  2),
        'prelim'      => round($prDue,  2),
        'midterm'     => round($midDue, 2),
        'finals'      => round($finDue, 2),
        'total'       => round($total,  2),
        'balance'     => round(max(0.0, $total - $allPaid), 2),
    ];
}

function _getScheduleAmounts($conn, $sid) {
    $tf    = $conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id=$sid LIMIT 1");
    $total = $tf ? (float)($tf->fetch_assoc()['total_assessment'] ?? 0) : 0;

    $paidStmtSid = $conn->prepare("SELECT exam_period, COALESCE(SUM(amount),0) AS paid FROM installment_payments WHERE student_id=? AND semester=(SELECT semester FROM students WHERE id=? LIMIT 1) GROUP BY exam_period");
    $paidStmtSid->bind_param('ii', $sid, $sid);
    $paidStmtSid->execute();
    $paidRes = $paidStmtSid->get_result();
    $paidStmtSid->close();
    $paid = ['Downpayment'=>0.0,'Prelim'=>0.0,'Midterm'=>0.0,'Finals'=>0.0];
    if ($paidRes) while ($r = $paidRes->fetch_assoc()) $paid[$r['exam_period']] = (float)$r['paid'];

    // FIX CARRY-OVER-01: Fetch approved permits so _calcInstallmentDues() correctly
    // uses actual-paid-only as credit for approved periods, carrying shortfalls forward.
    $apRes = $conn->query("SELECT exam_period FROM exam_permits WHERE student_id=$sid AND status='approved'");
    $approvedPeriods = [];
    if ($apRes) while ($apr = $apRes->fetch_assoc()) $approvedPeriods[] = $apr['exam_period'];

    $dues = _calcInstallmentDues($total, $paid, $approvedPeriods);
    // Also expose per-term paid amounts so accounting modal can show correct remaining balance
    $dues['termPaid'] = [
        'downpayment' => round($paid['Downpayment'], 2),
        'prelim'      => round($paid['Prelim'],      2),
        'midterm'     => round($paid['Midterm'],      2),
        'finals'      => round($paid['Finals'],       2),
    ];
    return $dues;
}
function getPendingPayments($conn) {
    $rows = [];

    // Fetch ALL pending payment_logs — includes both enrollment payments
    // AND installment term payments (Prelim/Midterm/Finals) from enrolled students
    $sql = "
        SELECT pl.id AS log_id, pl.student_id, pl.payment_method, pl.gcash_reference,
               pl.gcash_amount, pl.gcash_date, pl.transaction_id,
               COALESCE(NULLIF(pl.semester,''), s.semester) AS semester,
               pl.notes, pl.exam_period AS log_exam_period, pl.created_at AS submitted_at,
               s.student_number, s.first_name, s.last_name, s.program, s.year_level,
               s.payment_status, s.approval_status, s.enrollment_status,
               s.student_category,
               s.is_scholar, s.scholar_type, s.scholarship_amount,
               (SELECT COUNT(*) FROM student_scholarships ss3
                WHERE ss3.student_id = s.id AND ss3.status = 'pending') AS has_pending_scholarship,
               (SELECT ss3.scholar_type FROM student_scholarships ss3
                WHERE ss3.student_id = s.id AND ss3.status = 'pending'
                ORDER BY ss3.id DESC LIMIT 1) AS pending_scholar_type,
               tf.total_assessment,
               s.program AS department,
               (SELECT sg.email
                FROM student_guardians sg
                WHERE sg.student_id = s.id
                  AND sg.email IS NOT NULL
                  AND TRIM(sg.email) != ''
                ORDER BY sg.is_emergency DESC, sg.id ASC
                LIMIT 1) AS guardianEmail
        FROM payment_logs pl
        JOIN students s ON pl.student_id = s.id
        LEFT JOIN (SELECT student_id, total_assessment, subtotal, units FROM tuition_fees WHERE id IN (SELECT MAX(id) FROM tuition_fees GROUP BY student_id)) tf ON tf.student_id = s.id
        LEFT JOIN programs p ON (p.name = s.program OR p.code = s.program)
                              AND p.level_type = s.student_category
        WHERE pl.status = 'Pending'
          AND (
              s.is_scholar = 0
              OR (
                  s.is_scholar = 1
                  AND s.payment_status != 'Free'
                  AND (
                      -- FIX SCHOLAR-PENDING-QUEUE-01: Show scholars even when scholarship
                      -- is still pending approval (is_active=0). Previously only students
                      -- with an already-approved (is_active=1) scholarship appeared in the
                      -- Accounting queue — students who declared at Step 4 but weren't
                      -- approved yet were invisible to Accounting entirely.
                      -- Now: any scholar (approved OR pending) appears in the queue so
                      -- Accounting can verify payment AND approve the scholarship together.
                      EXISTS (
                          SELECT 1 FROM student_scholarships ss2
                          WHERE ss2.student_id = s.id
                            AND ss2.status IN ('pending', 'approved')
                      )
                  )
              )
          )
          -- FIX TVET-QUEUE-01: Exclude free SHS/TVET non-transferee students.
          -- They are auto-approved in getStudentContext / getTVETFee / getSHSFee.
          -- If they appear here it means auto-approve hasn't run yet — they have
          -- no tuition_fees record and a pending payment_log that should not exist.
          AND NOT (
              UPPER(COALESCE(s.student_category,'')) IN ('SHS','TVET')
              AND LOWER(COALESCE(s.student_type,'new')) != 'transferee'
              AND COALESCE(tf.total_assessment, 0) = 0
          )
        ORDER BY pl.created_at DESC
    ";

    $result = $conn->query($sql);
    $seenLogIds = [];
    if ($result) {
        while ($r = $result->fetch_assoc()) {
            // Guard: skip if this log_id was already added (can happen if tuition_fees
            // still has edge-case duplicate rows not caught by the subquery join).
            $logIdKey = (int)$r['log_id'];
            if (isset($seenLogIds[$logIdKey])) continue;
            $seenLogIds[$logIdKey] = true;

            $isCash  = strtolower($r['payment_method'] ?? '') === 'cash' || ($r['gcash_reference'] ?? '') === 'CASH-PAYMENT';
            // BUG-TVET-CASH-02 FIX: A phantom 'GCash' payment_log may have been auto-created
            // by the noLogSql path before the student chose their payment method. After the
            // student picks Cash via updatePaymentPlan(), students.payment_method is corrected
            // but the phantom log's payment_method may still be 'GCash'. Re-check against
            // students.payment_method (authoritative after updatePaymentPlan) and override.
            $sid     = (int)$r['student_id'];  // FIX: moved up — was used before assignment
            $studentMethodOverride = strtolower($r['student_category'] ?? ''); // placeholder, fetched below
            $stuMethodSt = $conn->prepare("SELECT payment_method FROM students WHERE id = ? LIMIT 1");
            $stuMethodSt->bind_param('i', $sid);
            $stuMethodSt->execute();
            $stuMethodRow = $stuMethodSt->get_result()->fetch_assoc();
            $stuMethodSt->close();
            $stuMethod = strtolower(trim($stuMethodRow['payment_method'] ?? ''));
            if ($stuMethod === 'cash') {
                $isCash = true; // Override: student explicitly chose Cash
            }
            $prSt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS tp FROM installment_payments WHERE student_id = ? AND semester=(SELECT semester FROM students WHERE id=? LIMIT 1)");
            $prSt->bind_param('ii', $sid, $sid);
            $prSt->execute();
            $pr = $prSt->get_result();
            $prSt->close();
            $total_paid = (float)($pr->fetch_assoc()['tp'] ?? 0);

            // exam_period: dedicated column wins, fall back to notes prefix parsing
            $notesRaw   = $r['notes'] ?? '';
            $examPeriod = trim($r['log_exam_period'] ?? '');
            if (!$examPeriod && preg_match('/^(Prelim|Midterm|Finals|Downpayment|Full)\|?/i', $notesRaw, $m)) {
                $examPeriod = $m[1];
                $notesRaw   = trim(substr($notesRaw, strlen($m[0])));
            }
            // Still empty? Auto-detect from payment plan and what's already been paid
            if (!$examPeriod) {
            // BUG-TERM-02: payment_plan may be NULL after re-enroll (not yet selected).
            // Check payment_schedules first — reEnroll() seeds a row there immediately.
            $planRow = (($_r=$conn->query("SELECT payment_plan FROM students WHERE id=$sid LIMIT 1")) ? $_r->fetch_assoc() : null);
            $resolvedPlan = $planRow['payment_plan'] ?? null;
            if ($resolvedPlan === null || $resolvedPlan === '') {
                $psChk = $conn->query("SELECT payment_type FROM payment_schedules WHERE student_id=$sid ORDER BY id DESC LIMIT 1");
                $resolvedPlan = $psChk ? ($psChk->fetch_assoc()['payment_type'] ?? 'full') : 'full';
            }
            if ($resolvedPlan === 'installment') {
                    // FIX GCASH-TERM-01 (getPendingPayments): Same semester-scope fix.
                    // Prior-semester Downpayment rows must not advance the term counter for the new semester.
                    $ptSt = $conn->prepare("
                        SELECT DISTINCT ip.exam_period
                        FROM installment_payments ip
                        JOIN students _st ON _st.id = ip.student_id
                        WHERE ip.student_id = ?
                          AND ip.amount > 0
                          AND ip.semester = _st.semester
                    ");
                    $ptSt->bind_param('i', $sid);
                    $ptSt->execute();
                    $paidTerms = $ptSt->get_result();
                    $ptSt->close();
                    $paidList  = [];
                    if ($paidTerms) while ($pr2 = $paidTerms->fetch_assoc()) $paidList[] = $pr2['exam_period'];
                    if (!in_array('Downpayment', $paidList))    $examPeriod = 'Downpayment';
                    elseif (!in_array('Prelim', $paidList))     $examPeriod = 'Prelim';
                    elseif (!in_array('Midterm', $paidList))    $examPeriod = 'Midterm';
                    else                                         $examPeriod = 'Finals';
                    // Save it back so verifyPayment can use it
                    $conn->query("UPDATE payment_logs SET exam_period='$examPeriod' WHERE id=" . (int)$r['log_id']);
                } else {
                    $examPeriod = 'Full';
                    $conn->query("UPDATE payment_logs SET exam_period='Full' WHERE id=" . (int)$r['log_id']);
                }
            }

            $rows[] = [
                'logId'          => (int)$r['log_id'],
                'studentId'      => $sid,
                'studentNumber'  => $r['student_number'],
                'firstName'      => $r['first_name'],
                'lastName'       => $r['last_name'],
                'program'        => $r['program'],
                'yearLevel'      => $r['year_level'],
                'department'     => $r['department'] ?? '',
                'studentCategory'=> $r['student_category'] ?? '',
                'enrollmentStatus'=> $r['enrollment_status'],
                'paymentMethod'  => $isCash ? 'Cash' : 'GCash',
                'gcashReference' => $isCash ? '' : ($r['gcash_reference'] ?? ''),
                'gcashAmount'    => $isCash ? 0 : (float)($r['gcash_amount'] ?? 0),
                'gcashDate'      => $isCash ? '' : ($r['gcash_date'] ?? ''),
                'transactionId'  => $isCash ? '' : ($r['transaction_id'] ?? ''),
                'semester'       => $r['semester'] ?? '',
                'examPeriod'     => $examPeriod,   // which term this payment is for
                'notes'          => $notesRaw,
                'status'         => 'Pending',
                'submittedAt'    => $r['submitted_at'],
                'paymentStatus'  => $r['payment_status'],
                'approvalStatus' => $r['approval_status'],
                'totalAssessment'=> (float)($r['total_assessment'] ?? 0),
                'totalPaid'      => $total_paid,
                'balance'        => max(0, (float)($r['total_assessment'] ?? 0) - $total_paid),
                'guardianEmail'  => $r['guardianEmail'] ?? '',
                // FIX SCHOLAR-VERIFY-01: expose pending-scholarship flag to the frontend
                // so it can show a warning badge and disable the Approve button.
                'isScholar'             => (bool)($r['is_scholar'] ?? false),
                'scholarType'           => $r['scholar_type'] ?? '',
                'scholarshipAmount'     => (float)($r['scholarship_amount'] ?? 0),
                'hasPendingScholarship' => (int)($r['has_pending_scholarship'] ?? 0) > 0,
                'pendingScholarType'    => $r['pending_scholar_type'] ?? '',
                'paymentPlan'    => _getStudentPaymentPlan($conn, $sid),
                'scheduleAmounts'=> (function() use ($conn, $sid) {
                    $sa = _getScheduleAmounts($conn, $sid);
                    return ['downpayment'=>$sa['downpayment'],'prelim'=>$sa['prelim'],'midterm'=>$sa['midterm'],'finals'=>$sa['finals'],'total'=>$sa['total']];
                })(),
                'termPaidAmounts'=> (function() use ($conn, $sid) {
                    $sa = _getScheduleAmounts($conn, $sid);
                    return $sa['termPaid'] ?? ['downpayment'=>0,'prelim'=>0,'midterm'=>0,'finals'=>0];
                })(),
            ];
        }
    }

    // Also show students with no payment_log (Cash pending enrollment, not yet enrolled)
    $noLogSql = "
        SELECT s.id AS student_id, s.student_number, s.first_name, s.last_name,
               s.program, s.year_level, s.payment_status, s.approval_status,
               s.payment_method, s.payment_plan, s.semester, s.created_at AS submitted_at,
               s.student_category,
               tf.total_assessment,
               s.program AS department,
               (SELECT sg.email
                FROM student_guardians sg
                WHERE sg.student_id = s.id
                  AND sg.email IS NOT NULL
                  AND TRIM(sg.email) != ''
                ORDER BY sg.is_emergency DESC, sg.id ASC
                LIMIT 1) AS guardianEmail
        FROM students s
        LEFT JOIN payment_logs pl ON pl.student_id = s.id AND pl.status = 'Pending'
        LEFT JOIN (SELECT student_id, total_assessment, subtotal, units FROM tuition_fees WHERE id IN (SELECT MAX(id) FROM tuition_fees GROUP BY student_id)) tf ON tf.student_id = s.id
        LEFT JOIN programs p ON (p.name = s.program OR p.code = s.program)
                              AND p.level_type = s.student_category
        WHERE s.payment_status = 'Pending'
          AND s.approval_status NOT IN ('Approved')
          AND s.enrollment_status NOT IN ('Enrolled','Graduated')
          AND pl.id IS NULL
          -- FIX TVET-QUEUE-01: Exclude free SHS/TVET non-transferee (no fees to collect)
          AND NOT (
              UPPER(COALESCE(s.student_category,'')) IN ('SHS','TVET')
              AND LOWER(COALESCE(s.student_type,'new')) != 'transferee'
              AND COALESCE(tf.total_assessment, 0) = 0
          )
    ";
    $noLogResult  = $conn->query($noLogSql);
    $alreadyAdded = array_column($rows, 'studentId');
    if ($noLogResult) {
        while ($r = $noLogResult->fetch_assoc()) {
            $sid = (int)$r['student_id'];
            if (in_array($sid, $alreadyAdded)) continue;
            // Mark immediately so duplicate rows from the same student (e.g. from
            // multiple tuition_fees rows surviving any edge-case) are skipped.
            $alreadyAdded[] = $sid;

            $rawSem   = trim($r['semester'] ?? '');
            $semester = $rawSem ?: '1st Semester, AY ' . date('Y') . '-' . (date('Y')+1);

            // Auto-detect exam_period for this Cash student
            // BUG-TERM-01: noLogSql now includes s.payment_plan so $r['payment_plan'] is populated.
            // If still NULL (student hasn't chosen yet after re-enroll), check payment_schedules
            // before falling back — reEnroll() seeds a 'full' row there as a placeholder.
            $rawNoLogPlan = $r['payment_plan'] ?? null;
            if ($rawNoLogPlan === null || $rawNoLogPlan === '') {
                $psRow2 = $conn->query("SELECT payment_type FROM payment_schedules WHERE student_id = $sid ORDER BY id DESC LIMIT 1");
                $rawNoLogPlan = $psRow2 ? ($psRow2->fetch_assoc()['payment_type'] ?? null) : null;
            }
            $noLogPlan = $rawNoLogPlan ?: 'full';
            $noLogExamPeriod = 'Full';
            if ($noLogPlan === 'installment') {
                // FIX GCASH-TERM-01 (noLog path): same semester-scope fix
                $ptSt2 = $conn->prepare("
                    SELECT DISTINCT ip.exam_period
                    FROM installment_payments ip
                    JOIN students _st ON _st.id = ip.student_id
                    WHERE ip.student_id = ?
                      AND ip.amount > 0
                      AND ip.semester = _st.semester
                ");
                $ptSt2->bind_param('i', $sid);
                $ptSt2->execute();
                $paidTerms2 = $ptSt2->get_result();
                $ptSt2->close();
                $paidList2  = [];
                if ($paidTerms2) while ($pt2 = $paidTerms2->fetch_assoc()) $paidList2[] = $pt2['exam_period'];
                if (!in_array('Downpayment', $paidList2))    $noLogExamPeriod = 'Downpayment';
                elseif (!in_array('Prelim', $paidList2))     $noLogExamPeriod = 'Prelim';
                elseif (!in_array('Midterm', $paidList2))    $noLogExamPeriod = 'Midterm';
                else                                          $noLogExamPeriod = 'Finals';
            }

            // Only auto-create a pending payment_log for Cash students.
            // GCash students submit their own log via submit_gcash — creating a phantom
            // 'Cash' log here was the bug that made GCash students appear as Cash in Accounting.
            // FIX RE-ENROLL-METHOD-03: recover NULL payment_method from payment_logs
            // history. reEnroll() used to set payment_method=NULL; affected students
            // would appear here as 'gcash' (the ?? fallback) and never get a Cash log.
            $rawPm = trim($r['payment_method'] ?? '');
            if ($rawPm === '') {
                $pmRecSt = $conn->prepare(
                    "SELECT payment_method FROM payment_logs
                     WHERE student_id = ? AND payment_method IS NOT NULL AND payment_method != ''
                     ORDER BY created_at DESC LIMIT 1"
                );
                $pmRecSt->bind_param('i', $sid);
                $pmRecSt->execute();
                $pmRecRow = $pmRecSt->get_result()->fetch_assoc();
                $pmRecSt->close();
                $rawPm = $pmRecRow['payment_method'] ?? '';
                // Persist the recovered method so next load is instant
                if ($rawPm !== '') {
                    $conn->query("UPDATE students SET payment_method = '" . $conn->real_escape_string($rawPm) . "' WHERE id = $sid AND (payment_method IS NULL OR payment_method = '')");
                }
            }
            $noLogPaymentMethod = strtolower($rawPm); // BUG-GCASH-03: no ?: 'gcash' fallback — unknown method must not default to GCash
            // BUG-TVET-CASH-01 FIX: When payment_method is empty (student enrolled before
            // choosing method, or TVET auto-approved before updatePaymentPlan ran), check
            // payment_plan as a secondary signal before routing to GCash path.
            // Cash + installment students call notifyCashPending which creates a Cash payment_log
            // — but that log is in the FIRST query ($sql) and won't reach this noLog path.
            // If we're here with empty method AND no log, it's genuinely unknown — skip it
            // rather than creating a phantom GCash entry that misleads Accounting.
            if ($noLogPaymentMethod === '' && !empty($r['payment_plan'])) {
                // Try to resolve from payment_plan context: installment without a log and
                // unknown payment method usually means Cash student who hasn't notified yet.
                // Skip this student — they'll appear once they submit via notifyCashPending.
                // NOTE: GCash+installment students should NOT reach here after the
                // FIX PM-GCASH-LOG-01 patch (updatePaymentPlan now seeds their log immediately).
                // If they somehow do (e.g. registered before the patch), fall through to
                // the GCash path below so they appear in Accounting rather than being invisible.
                // We treat unknown+installment as GCash-AwaitingSubmission as a safe fallback.
                // Determine if this is more likely Cash (no log at all and plan is full) or GCash.
                // For installment with unknown method: show as GCash AwaitingSubmission.
                if ($r['payment_plan'] !== 'installment') {
                    continue; // full-payment + unknown method: skip, will appear when notified
                }
                // installment + unknown: treat as GCash AwaitingSubmission (safe fallback)
                $noLogPaymentMethod = 'gcash';
            }
            if ($noLogPaymentMethod === 'gcash') {
                // GCash student with no log yet — show as AwaitingSubmission so Accounting knows to wait
                $logId = 0; // no real log_id
                $pr = $conn->query("SELECT COALESCE(SUM(amount),0) AS tp FROM installment_payments ip JOIN students _st ON _st.id=ip.student_id WHERE ip.student_id=$sid AND ip.semester=_st.semester");
                $total_paid = (float)($pr->fetch_assoc()['tp'] ?? 0);

                $rows[] = [
                    'logId'          => null,
                    'studentId'      => $sid,
                    'studentNumber'  => $r['student_number'],
                    'firstName'      => $r['first_name'],
                    'lastName'       => $r['last_name'],
                    'program'        => $r['program'],
                    'yearLevel'      => $r['year_level'],
                    'department'     => $r['department'] ?? '',
                    'studentCategory'=> $r['student_category'] ?? '',
                    'enrollmentStatus'=> 'Pending',
                    'paymentMethod'  => 'GCash',
                    'gcashReference' => '', 'gcashAmount' => 0, 'gcashDate' => '', 'transactionId' => '',
                    'semester'       => $r['semester'] ?: $semester,
                    'examPeriod'     => $noLogExamPeriod,
                    'notes'          => '',
                    'status'         => 'AwaitingSubmission', // student hasn't submitted GCash ref yet
                    'submittedAt'    => $r['submitted_at'],
                    'paymentStatus'  => $r['payment_status'],
                    'approvalStatus' => $r['approval_status'],
                    'totalAssessment'=> (float)($r['total_assessment'] ?? 0),
                    'totalPaid'      => $total_paid,
                    'balance'        => max(0, (float)($r['total_assessment'] ?? 0) - $total_paid),
                    'guardianEmail'  => $r['guardianEmail'] ?? '',
                    'paymentPlan'    => _getStudentPaymentPlan($conn, $sid),
                    'scheduleAmounts'=> (function() use ($conn, $sid) {
                        $sa = _getScheduleAmounts($conn, $sid);
                        return ['downpayment'=>$sa['downpayment'],'prelim'=>$sa['prelim'],'midterm'=>$sa['midterm'],'finals'=>$sa['finals'],'total'=>$sa['total']];
                    })(),
                    'termPaidAmounts'=> (function() use ($conn, $sid) {
                        $sa = _getScheduleAmounts($conn, $sid);
                        return $sa['termPaid'] ?? ['downpayment'=>0,'prelim'=>0,'midterm'=>0,'finals'=>0];
                    })(),
                ];
                continue;
            }

            // Cash student — auto-create a pending payment_log so Accounting can record payment
            $ins = $conn->prepare("INSERT INTO payment_logs (student_id, payment_method, gcash_reference, gcash_amount, semester, exam_period, status) VALUES (?, 'Cash', '', 0, ?, ?, 'Pending')");
            $ins->bind_param("iss", $sid, $semester, $noLogExamPeriod);
            $ins->execute();
            $logId = $ins->insert_id;

            $pr = $conn->query("SELECT COALESCE(SUM(amount),0) AS tp FROM installment_payments ip JOIN students _st ON _st.id=ip.student_id WHERE ip.student_id=$sid AND ip.semester=_st.semester");
            $total_paid = (float)($pr->fetch_assoc()['tp'] ?? 0);

            $rows[] = [
                'logId'          => $logId,
                'studentId'      => $sid,
                'studentNumber'  => $r['student_number'],
                'firstName'      => $r['first_name'],
                'lastName'       => $r['last_name'],
                'program'        => $r['program'],
                'yearLevel'      => $r['year_level'],
                'department'     => $r['department'] ?? '',
                'studentCategory'=> $r['student_category'] ?? '',
                'enrollmentStatus'=> 'Pending',
                'paymentMethod'  => 'Cash',
                'gcashReference' => '', 'gcashAmount' => 0, 'gcashDate' => '', 'transactionId' => '',
                'semester'       => $r['semester'] ?: $semester,
                'examPeriod'     => $noLogExamPeriod,
                'notes'          => '',
                'status'         => 'Pending',
                'submittedAt'    => $r['submitted_at'],
                'paymentStatus'  => $r['payment_status'],
                'approvalStatus' => $r['approval_status'],
                'totalAssessment'=> (float)($r['total_assessment'] ?? 0),
                'totalPaid'      => $total_paid,
                'balance'        => max(0, (float)($r['total_assessment'] ?? 0) - $total_paid),
                'guardianEmail'  => $r['guardianEmail'] ?? '',
                'paymentPlan'    => _getStudentPaymentPlan($conn, $sid),
                'scheduleAmounts'=> (function() use ($conn, $sid) {
                    $sa = _getScheduleAmounts($conn, $sid);
                    return ['downpayment'=>$sa['downpayment'],'prelim'=>$sa['prelim'],'midterm'=>$sa['midterm'],'finals'=>$sa['finals'],'total'=>$sa['total']];
                })(),
                'termPaidAmounts'=> (function() use ($conn, $sid) {
                    $sa = _getScheduleAmounts($conn, $sid);
                    return $sa['termPaid'] ?? ['downpayment'=>0,'prelim'=>0,'midterm'=>0,'finals'=>0];
                })(),
            ];
        }
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    global $authUser;
    $rows = applyPrivacyList($rows, $authUser, 'financial');
    echo json_encode(['success' => true, 'payments' => $rows]);
}

// ─────────────────────────────────────────────────────────────
// ACCOUNTING: Payment history
// ─────────────────────────────────────────────────────────────
// GET ?action=get_student_payment_history&student_id=X
// ── STUDENT: Get distinct SOA semester list ───────────────────────────────────
// Returns every semester for which this student has payment records, plus the
// current semester (even if no payments yet).  Used to populate the semester
// dropdown in the Enrollment & Fees (SOA) component.
//
// GET Accounting.php?action=get_soa_semesters&student_id=NNN
// Auth: student (own record only), accounting, admin, registrar
function getSoaSemesters(mysqli $conn): void {
    global $authUser;
    $student_id = (int)($_GET['student_id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$student_id) { echo json_encode(['success'=>false,'message'=>'student_id required']); return; }

    // Ownership check — students may only query their own record
    if ($authUser['role'] === 'student') {
        $own = $conn->prepare("SELECT id FROM students WHERE id = ? AND user_id = ? LIMIT 1");
        $own->bind_param('ii', $student_id, $authUser['user_id']);
        $own->execute();
        if ($own->get_result()->num_rows === 0) {
            echo json_encode(['success'=>false,'message'=>'Access denied.']);
            return;
        }
        $own->close();
    }

    $semesters = [];

    // Source 1: installment_payments.semester (already present in live DB)
    $r1 = $conn->query("
        SELECT DISTINCT semester FROM installment_payments
        WHERE student_id = $student_id
          AND semester IS NOT NULL AND TRIM(semester) != ''
    ");
    if ($r1) while ($row = $r1->fetch_assoc()) $semesters[$row['semester']] = true;

    // Source 2: payment_logs.semester (GCash / cash pending logs)
    $r2 = $conn->query("
        SELECT DISTINCT semester FROM payment_logs
        WHERE student_id = $student_id
          AND semester IS NOT NULL AND TRIM(semester) != ''
          AND status IN ('Verified','Approved','Pending')
    ");
    if ($r2) while ($row = $r2->fetch_assoc()) $semesters[$row['semester']] = true;

    // Source 3: always include the student's current semester so the dropdown
    //            shows "current term" even before any payment has been recorded.
    $curRes = $conn->query("SELECT semester FROM students WHERE id = $student_id LIMIT 1");
    if ($curRes) {
        $cs = trim($curRes->fetch_assoc()['semester'] ?? '');
        if ($cs !== '') $semesters[$cs] = true;
    }

    // Sort newest first.
    // Semester strings look like "1st Semester, AY 2025-2026" or "2nd Semester, AY 2024-2025".
    // Primary key  : end year of the AY  (2026 > 2025)
    // Secondary key: Summer=3 > 2nd=2 > 1st=1 within same AY
    $list = array_keys($semesters);
    usort($list, function (string $a, string $b): int {
        preg_match('/(\d{4})-(\d{4})/', $a, $ma);
        preg_match('/(\d{4})-(\d{4})/', $b, $mb);
        $ya = (int)($ma[2] ?? 0);
        $yb = (int)($mb[2] ?? 0);
        if ($ya !== $yb) return $yb - $ya;

        $termOrder = ['Summer'=>3,'Midyear'=>3,'2nd Semester'=>2,'1st Semester'=>1];
        $oa = 0;
        $ob = 0;
        foreach ($termOrder as $k => $v) {
            if (str_starts_with($a, $k)) { $oa = $v; break; }
        }
        foreach ($termOrder as $k => $v) {
            if (str_starts_with($b, $k)) { $ob = $v; break; }
        }
        return $ob - $oa;
    });

    echo json_encode(['success'=>true,'semesters'=>$list]);
}

// Returns verified payments for a single student (student view)
function getStudentPaymentHistory($conn) {
    $student_id = (int)($_GET['student_id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$student_id) { echo json_encode(['success'=>false,'message'=>'student_id required']); return; }

    // FIX SOA-SEM-01: Accept optional ?semester= to scope history to a specific term.
    // When omitted, returns ALL semesters (current behaviour — no regression).
    $filterSemester = trim($_GET['semester'] ?? '');

    // FIX SOA-ALL-SEM-01: When Angular opens the per-student payment modal it sends
    // ?all_semesters=1 to fetch every payment record across ALL terms so the semester
    // selector can be populated.  In that case we must NOT add a semester WHERE clause —
    // doing so returned only the current semester and made Prelim/Midterm/Finals records
    // from other semesters invisible.
    $allSemesters = !empty($_GET['all_semesters']) && (int)$_GET['all_semesters'] === 1;

    // Support user_id fallback
    // BUG-SOA-01 FIX: The original code had a dead-code early-return that always fired
    // after the user_id lookup, returning empty history even for valid students.
    $stResSt = $conn->prepare("SELECT id FROM students WHERE id=? LIMIT 1");
    $stResSt->bind_param('i', $student_id);
    $stResSt->execute();
    $stRes = $stResSt->get_result();
    $stResSt->close();
    if (!$stRes || $stRes->num_rows === 0) {
        // Try resolving by user_id
        $stRes2St = $conn->prepare("SELECT id FROM students WHERE user_id=? LIMIT 1");
        $stRes2St->bind_param('i', $student_id);
        $stRes2St->execute();
        $stRes2 = $stRes2St->get_result();
        $stRes2St->close();
        $row2 = $stRes2 ? $stRes2->fetch_assoc() : null;
        if ($row2) {
            $student_id = (int)$row2['id'];
        } else {
            // No student found by id or user_id — return empty but valid response
            while (ob_get_level() > 0) { ob_end_clean(); }
            echo json_encode(['success'=>true,'history'=>[],'totalPaid'=>0,'totalAssessment'=>0,'balance'=>0,'semFees'=>null,'student'=>null,'semester'=>$filterSemester]);
            return;
        }
    }

    // Build semester WHERE clause safely
    $semClause = '';
    // BUG-SOA-SEM-03 complement: use the same effectiveSemester computed below for semFees,
    // but we need to know it NOW before building the query. Derive it early.
    // Priority: explicit ?semester= param → student's current semester → first payment row (unknown yet)
    $earlyEffectiveSem = $filterSemester;
    if ($earlyEffectiveSem === '') {
        // Try to resolve from the student's current semester (fetched after the query, but we need it now)
        // Use a quick single-column query here; the full $sRow is fetched below for the header
        $earlyStSt = $conn->prepare("SELECT semester FROM students WHERE id = ? LIMIT 1");
        $earlyStSt->bind_param('i', $student_id);
        $earlyStSt->execute();
        $earlyStRow = $earlyStSt->get_result()->fetch_assoc();
        $earlyStSt->close();
        $earlyEffectiveSem = trim($earlyStRow['semester'] ?? '');
    }
    // FIX SOA-ALL-SEM-01: When Angular opens the student payment modal it sends
    // ?all_semesters=1. Do NOT add a semester WHERE clause in that case — the modal
    // needs every payment record so its semester selector can list all terms and
    // Prelim/Midterm/Finals from past semesters are visible.
    if (!$allSemesters && $earlyEffectiveSem !== '') {
        $safeSem   = $conn->real_escape_string($earlyEffectiveSem);
        $semClause = "AND ip.semester = '$safeSem'";
    }

    $result = $conn->query("
        SELECT ip.id, ip.or_ar_number, ip.or_ar_type, ip.amount, ip.payment_date,
               ip.payment_method, ip.gcash_reference, ip.exam_period, ip.notes,
               ip.created_at,
               COALESCE(ip.semester, '') AS semester,
               COALESCE(sp.first_name, f2.first_name) AS verified_by_fname, COALESCE(sp.last_name, f2.last_name) AS verified_by_lname,
               -- BUG-RECEIPT-DUPE FIX: Replaced unscoped LEFT JOIN tuition_fees (which produced a
               -- Cartesian product when a student has multiple tuition_fees rows — one per semester)
               -- with a correlated subquery that always returns exactly one row matched by semester.
               (SELECT tf2.total_assessment
                FROM tuition_fees tf2
                WHERE tf2.student_id = ip.student_id
                  AND tf2.semester   = COALESCE(ip.semester, '')
                ORDER BY tf2.id DESC LIMIT 1) AS total_assessment
        FROM installment_payments ip
        LEFT JOIN users u ON ip.recorded_by = u.id
        LEFT JOIN staff_profiles sp ON sp.user_id = u.id
        LEFT JOIN faculty f2 ON f2.user_id = u.id
        WHERE ip.student_id = $student_id $semClause
        ORDER BY ip.payment_date DESC, ip.created_at DESC
    ");

    $rows = [];
    if ($result) {
        while ($r = $result->fetch_assoc()) {
            $rows[] = [
                'id'           => (int)$r['id'],
                'orArNumber'   => $r['or_ar_number'] ?? '',
                'orArType'     => $r['or_ar_type']   ?? 'AR',
                'amount'       => (float)$r['amount'],
                'paymentDate'  => $r['payment_date']  ?? '',
                'paymentMethod'=> $r['payment_method'] ?? '',
                'gcashRef'     => $r['gcash_reference'] ?? '',
                'examPeriod'   => $r['exam_period']  ?? '',
                'notes'        => $r['notes']         ?? '',
                'createdAt'    => $r['created_at']    ?? '',
                'verifiedBy'   => trim(($r['verified_by_fname'] ?? '') . ' ' . ($r['verified_by_lname'] ?? '')) ?: 'Accounting',
                'totalAssessment' => (float)($r['total_assessment'] ?? 0),
                'semester'     => $r['semester'] ?? '',
            ];
        }
    }

    // BUG-SOA-02 FIX: Compute totalPaid scoped to the selected semester only.
    // Previously used array_sum over ALL returned rows regardless of semester filter,
    // which made totalPaid correct but $semFees->totalPaid could differ.
    // Now both use the same semester-scoped sum so balance is always accurate.
    $totalPaid = array_sum(array_column($rows, 'amount'));

    // Fallback totalAssessment from first row — will be overridden by semFees below
    $totalAssessment = $rows[0]['totalAssessment'] ?? 0;

    // Student info for SOA header — include guardian email for the SOA modal tab
    $sRowSt = $conn->prepare("
        SELECT s.first_name, s.last_name, s.student_number, s.program, s.year_level, s.semester, s.payment_plan,
               (SELECT sg.email
                FROM student_guardians sg
                WHERE sg.student_id = s.id
                  AND sg.email IS NOT NULL
                  AND TRIM(sg.email) != ''
                ORDER BY sg.is_emergency DESC, sg.id ASC
                LIMIT 1) AS guardian_email
        FROM students s
        WHERE s.id = ? LIMIT 1
    ");
    $sRowSt->bind_param('i', $student_id);
    $sRowSt->execute();
    $sRow = $sRowSt->get_result()->fetch_assoc();
    $sRowSt->close();

    // FIX SOA-SEM-02: Return the full per-semester fee breakdown so Angular
    // refreshes the entire SOA (not just totalPaid/balance) when switching terms.
    //
    // Strategy (in priority order):
    //   1. tuition_fees WHERE semester = $filterSemester  — exact stored assessment
    //   2. tuition_fees latest row (semester IS NULL or no match) — legacy fallback
    //   3. Recompute from subject_fee_log / enrollments  — last resort
    //
    // tuition_fees is the authoritative source because Accounting writes the
    // actual assessed amount there when confirming enrollment. Recomputing from
    // subjects can give wrong results when ENR-01 mutated enrollments.semester.
    $semFees = null;
    // BUG-SOA-SEM-03 FIX: When Angular loads the SOA without a ?semester= param
    // (initial page load), $filterSemester is '' so the entire fee-lookup block was
    // skipped and semFees was always null. Use the student's current semester as the
    // effective filter so the fee breakdown is always populated on first load.
    // $earlyEffectiveSem was already resolved above (before the SQL query) to scope
    // payment rows correctly — reuse it here so both the SQL and semFees use the same sem.
    $effectiveSemester = $earlyEffectiveSem;
    if ($effectiveSemester === '' && !empty($rows)) {
        // Last resort: derive from the first payment row (only if semester was totally unknown)
        $effectiveSemester = $rows[0]['semester'] ?? '';
    }
    if ($effectiveSemester !== '') {
        $safeSemFL = $conn->real_escape_string($effectiveSemester);

        // ── Priority 1: exact semester match in tuition_fees ─────────────────
        $tfRes = $conn->query("
            SELECT units, tuition_fee, miscellaneous_fee, registration_fee,
                   laboratory_fee, energy_fee, subtotal, discount,
                   installment_fee, total_assessment
            FROM tuition_fees
            WHERE student_id = $student_id
              AND semester = '$safeSemFL'
            ORDER BY id DESC LIMIT 1
        ");
        $tfRow = $tfRes ? $tfRes->fetch_assoc() : null;

        // ── Priority 1b: semester IS NULL fallback — stamp and use it ──────────
        // FIX-TUITION-SEMESTER-01: Old code wrote tuition_fees rows with semester=NULL
        // (ON DUPLICATE KEY never fired — no unique key on student_id alone). When
        // Priority 1 finds no row for this semester, check for NULL-semester rows
        // and stamp them so future lookups work. Use the latest NULL row as the fee
        // data for this semester if we have nothing better.
        if (!$tfRow) {
            $tfNullRes = $conn->query("
                SELECT id, units, tuition_fee, miscellaneous_fee, registration_fee,
                       laboratory_fee, energy_fee, subtotal, discount,
                       installment_fee, total_assessment
                FROM tuition_fees
                WHERE student_id = $student_id AND semester IS NULL
                ORDER BY id DESC LIMIT 1
            ");
            $tfNullRow = $tfNullRes ? $tfNullRes->fetch_assoc() : null;
            if ($tfNullRow) {
                // Stamp this NULL row with the effective semester so it matches next time
                $nullId = (int)$tfNullRow['id'];
                $conn->query("UPDATE tuition_fees SET semester='$safeSemFL' WHERE id=$nullId");
                $tfRow = $tfNullRow;
            }
        }

        // ── Priority 2: latest tuition_fees row ONLY when no semester filter is active ─
        // BUG-SOA-03 FIX: When a semester filter IS active and no exact row is found,
        // do NOT fall back to the latest tuition_fees row — it belongs to a different
        // term and will show wrong fees when the user switches to an older semester.
        // Instead, fall through to Priority 3 (recompute) or leave semFees null.
        if (!$tfRow && $filterSemester === '') {
            $tfRes2 = $conn->query("
                SELECT units, tuition_fee, miscellaneous_fee, registration_fee,
                       laboratory_fee, energy_fee, subtotal, discount,
                       installment_fee, total_assessment
                FROM tuition_fees
                WHERE student_id = $student_id
                ORDER BY id DESC LIMIT 1
            ");
            $tfRow = $tfRes2 ? $tfRes2->fetch_assoc() : null;
        }

        if ($tfRow) {
            // Use the stored assessment directly — this is what Accounting issued.
            // FIX SOA-PLAN-01: Infer the HISTORICAL payment plan from the stored
            // installment_fee in tuition_fees rather than the student's current
            // payment_plan column (which reflects the current semester, not the
            // past one being viewed). If installment_fee > 0, it was installment.
            $storedInstFee = (float)($tfRow['installment_fee'] ?? 0);
            $historicalPlan = $storedInstFee > 0 ? 'installment' : 'full';

            $semTotal   = (float)($tfRow['total_assessment'] ?? 0);
            $semBalance = max(0, $semTotal - $totalPaid);
            // BUG-SOA-DUES-01 FIX: Read the per-term scheduled dues from payment_schedules
            // so the frontend can correctly render the installment schedule table for
            // past semesters without having to recalculate from scratch (which gives
            // wrong figures when the DP paid differed from exactly total/4).
            // payment_schedules only has one row per student (current-semester snapshot),
            // so only use it when the viewed semester matches the student's current semester.
            $psRow = null;
            if ($historicalPlan === 'installment') {
                $curSemChk = $conn->query("SELECT semester FROM students WHERE id=$student_id LIMIT 1");
                $curSemVal = trim(($curSemChk ? $curSemChk->fetch_assoc()['semester'] : '') ?? '');
                if ($curSemVal === $safeSemFL) {
                    // Viewing current semester — use live payment_schedules
                    $psChk = $conn->query("SELECT prelim_due, midterm_due, finals_due FROM payment_schedules WHERE student_id=$student_id LIMIT 1");
                    $psRow = $psChk ? $psChk->fetch_assoc() : null;
                }
                // For past semesters: reconstruct dues the same way recomputeSchedule() does.
                // Sum DP paid for THAT semester (not current), split remainder 3 ways.
                if (!$psRow) {
                    $dpHistRes = $conn->query("
                        SELECT COALESCE(SUM(amount),0) AS dp
                        FROM installment_payments
                        WHERE student_id=$student_id
                          AND exam_period='Downpayment'
                          AND semester='$safeSemFL'
                    ");
                    $dpHistPaid = $dpHistRes ? (float)$dpHistRes->fetch_assoc()['dp'] : 0;
                    $dpCredit   = $dpHistPaid > 0 ? $dpHistPaid : ($semTotal > 0 ? round($semTotal / 4, 2) : 0);
                    $remHist    = max(0, $semTotal - $dpCredit);
                    $pdHist     = $remHist > 0 ? (ceil($remHist / 3 * 100) / 100) : 0;
                    $psRow      = ['prelim_due' => $pdHist, 'midterm_due' => $pdHist, 'finals_due' => round(max(0, $remHist - $pdHist * 2), 2)];
                }
            }

            // Per-term DP due = total/4 for display purposes (what was originally scheduled)
            $dpDue = $semTotal > 0 ? round($semTotal / 4, 2) : 0;

            $semFees = [
                'units'           => (int)$tfRow['units'],
                'tuitionFee'      => (float)$tfRow['tuition_fee'],
                'miscellaneousFee'=> (float)$tfRow['miscellaneous_fee'],
                'registrationFee' => (float)$tfRow['registration_fee'],
                'laboratoryFee'   => (float)$tfRow['laboratory_fee'],
                'energyFee'       => (float)$tfRow['energy_fee'],
                'subtotal'        => (float)$tfRow['subtotal'],
                'discount'        => (float)$tfRow['discount'],
                'installmentFee'  => $storedInstFee,
                'totalAssessment' => $semTotal,
                'totalPaid'       => $totalPaid,
                'balance'         => $semBalance,
                'paymentStatus'   => $semBalance <= 0 ? 'Fully Paid'
                                   : ($totalPaid > 0 ? 'Partially Paid' : 'Unpaid'),
                'paymentPlan'     => $historicalPlan,   // FIX SOA-PLAN-01: historical plan
                // BUG-SOA-DUES-01: per-term scheduled dues for installment schedule table
                'dpDue'           => $dpDue,
                'prelimDue'       => $psRow ? (float)$psRow['prelim_due']  : 0,
                'midtermDue'      => $psRow ? (float)$psRow['midterm_due'] : 0,
                'finalsDue'       => $psRow ? (float)$psRow['finals_due']  : 0,
            ];
            // BUG-SOA-04 FIX: Sync totalAssessment so the response balance is correct
            // for the selected semester, not the student's current/latest semester.
            $totalAssessment = $semTotal;
        } else {
            // ── Priority 3: recompute from subject_fee_log (no stored row) ───
            $flRes = $conn->query("
                SELECT sfl.units, sfl.lec_units, sfl.lab_units
                FROM subject_fee_log sfl
                WHERE sfl.student_id = $student_id
                  AND sfl.action = 'Add'
                  AND sfl.semester = '$safeSemFL'
            ");
            $semSubjects = [];
            if ($flRes) while ($sr = $flRes->fetch_assoc()) $semSubjects[] = $sr;

            if (empty($semSubjects)) {
                $eflRes = $conn->query("
                    SELECT c.credits AS units, COALESCE(c.lab_units,0) AS lab_units
                    FROM enrollments e JOIN courses c ON c.id = e.course_id
                    WHERE e.student_id = $student_id AND e.semester = '$safeSemFL'
                ");
                if ($eflRes) while ($sr = $eflRes->fetch_assoc()) $semSubjects[] = $sr;
            }

            if (!empty($semSubjects)) {
                $catRes = $conn->query("SELECT student_category, payment_plan, is_scholar, scholarship_amount FROM students WHERE id=$student_id LIMIT 1");
                $catRow = $catRes ? $catRes->fetch_assoc() : null;
                $category = $catRow['student_category'] ?? 'College';
                $payPlan  = $catRow['payment_plan']      ?? 'full';
                $discount = (int)($catRow['is_scholar'] ?? 0) ? (float)($catRow['scholarship_amount'] ?? 0) : 0;

                $fc = [];
                $fcR = $conn->query("SELECT fee_key, value FROM fee_config WHERE category='$category' AND is_active=1");
                if ($fcR) while ($fr = $fcR->fetch_assoc()) $fc[$fr['fee_key']] = (float)$fr['value'];

                $r_t = $fc['tuition_rate_per_unit'] ?? 650;
                $r_m = $fc['misc_fee']              ?? 6688;
                $r_r = $fc['reg_fee']               ?? 700;
                $r_l = $fc['lab_fee_per_room']      ?? 1900;
                $r_e = $fc['energy_rate_per_unit']  ?? 63;
                $r_i = $fc['installment_fee']       ?? 750;

                $totalUnits = array_sum(array_column($semSubjects, 'units'));
                $labCount   = count(array_filter($semSubjects, fn($s) => (int)($s['lab_units'] ?? 0) > 0));
                if ($labCount === 0 && $totalUnits > 0) {
                    $lrR = $conn->query("SELECT COUNT(*) AS cnt FROM rooms WHERE room_type='Laboratory'");
                    $labCount = (int)(($lrR ? $lrR->fetch_assoc()['cnt'] : 0) ?? 0);
                }
                $tuitionFee = $totalUnits * $r_t;
                $energyFee  = $totalUnits * $r_e;
                $labFee     = $labCount   * $r_l;
                $instFee    = ($payPlan === 'installment') ? $r_i : 0;
                $sub        = $tuitionFee + $r_m + $r_r + $labFee + $energyFee;
                $semTotal   = max(0, $sub - $discount + $instFee);
                $semBalance = max(0, $semTotal - $totalPaid);
                $semFees = [
                    'units'           => $totalUnits,
                    'tuitionFee'      => $tuitionFee,
                    'miscellaneousFee'=> $r_m,
                    'registrationFee' => $r_r,
                    'laboratoryFee'   => $labFee,
                    'energyFee'       => $energyFee,
                    'subtotal'        => $sub,
                    'discount'        => $discount,
                    'installmentFee'  => $instFee,
                    'totalAssessment' => $semTotal,
                    'totalPaid'       => $totalPaid,
                    'balance'         => $semBalance,
                    'paymentStatus'   => $semBalance <= 0 ? 'Fully Paid'
                                       : ($totalPaid > 0 ? 'Partially Paid' : 'Unpaid'),
                    'paymentPlan'     => $payPlan,   // FIX SOA-PLAN-01: historical plan (Priority 3)
                ];
                // BUG-SOA-04 FIX (Priority 3 path): sync totalAssessment for the response
                $totalAssessment = $semTotal;
            }
        }
    }

    // ── Installment due dates + actual paid dates per term ────────────────────
    // For installment students: show Accounting-set due dates for each period,
    // and replace with the actual payment_date once the student has paid.
    // For full-payment students this will be an empty array (no per-term dates).
    //
    // BUG-SOA-PLAN-02 FIX: Use the HISTORICAL payment plan from $semFees['paymentPlan']
    // as the authoritative source. This is already correctly derived from the stored
    // installment_fee in tuition_fees (installment_fee > 0 = was installment that sem).
    //
    // Previously the code also checked students.payment_plan (current semester) which
    // caused full-payment students from a prior semester to be shown installment due-date
    // rows on the current semester SOA — because their current plan IS installment even
    // though the viewed semester was full-payment.
    //
    // Priority:
    //   1. $semFees['paymentPlan'] — historical plan derived from stored installment_fee
    //      (most accurate; covers both Priority 1 and Priority 3 recompute paths)
    //   2. Live DB students.payment_plan — ONLY used when $semFees is null entirely
    //      (no tuition_fees row at all for this semester, e.g. very old records)
    $installmentDueDates = [];
    if ($semFees !== null) {
        // We have a fee record for this semester — use its derived historical plan.
        // Never fall back to the current-semester students.payment_plan here.
        $isInstallmentStudent = ($semFees['paymentPlan'] ?? 'full') === 'installment';
    } else {
        // No fee record for this semester at all — fall back to current plan as last resort.
        $planRow = $conn->query("SELECT payment_plan FROM students WHERE id=$student_id LIMIT 1");
        $isInstallmentStudent = ($planRow && ($planRow->fetch_assoc()['payment_plan'] ?? '') === 'installment');
    }
    if ($isInstallmentStudent) {
        $installmentDueDates = _getInstallmentPaymentDates($conn, $student_id, $effectiveSemester);
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    global $authUser;
    // BUG-SOA-PRIVACY-01 FIX: Pass ownerStudentId so a student viewing their own
    // payment history gets isOwner=true → promoted to 'admin' level in applyPrivacy,
    // which means amount/totalAssessment/totalPaid/balance are returned as real numbers
    // instead of '****'. Without this, every financial field is redacted for students.
    $rows = applyPrivacyList($rows, $authUser, 'financial', $student_id);
    $isOwner = ($authUser && $authUser['role'] === 'student');
    $sRow = $sRow ? applyPrivacy($sRow, $authUser, 'student', $isOwner) : null;

    // FIX SOA-ALL-SEM-01: Build a map of { semester => totalAssessment } for every
    // semester this student has a tuition_fees row, so Angular can update the
    // totalAssessment / balance display correctly when the user switches semesters
    // inside the modal — without needing a second API call.
    $semesterAssessments = [];
    $saRes = $conn->query("
        SELECT semester, total_assessment
        FROM tuition_fees
        WHERE student_id = $student_id
          AND semester IS NOT NULL
        ORDER BY id DESC
    ");
    if ($saRes) {
        while ($saRow = $saRes->fetch_assoc()) {
            $sem = trim($saRow['semester']);
            if ($sem !== '' && !isset($semesterAssessments[$sem])) {
                $semesterAssessments[$sem] = (float)$saRow['total_assessment'];
            }
        }
    }

    echo json_encode([
        'success'              => true,
        'history'              => $rows,
        'totalPaid'            => $totalPaid,
        'totalAssessment'      => $totalAssessment,
        'balance'              => max(0, $totalAssessment - $totalPaid),
        'semFees'              => $semFees,   // FIX SOA-SEM-02: full fee breakdown for selected term
        'student'              => $sRow,
        'semester'             => $filterSemester,
        // FIX SOA-ALL-SEM-01: per-semester totalAssessment map — Angular uses this when
        // the user switches semesters in the payment modal so totalAssessment & balance
        // reflect the viewed term, not just the student's current semester.
        'semesterAssessments'  => $semesterAssessments,
        // SOA-DUE-DATES: per-term due dates vs actual paid dates for installment students.
        // Angular SOA component uses this to show "Due: <date>" or "Paid: <date>" per row.
        'installmentDueDates'  => $installmentDueDates,
    ]);
}

function getPaymentHistory($conn) {
    // ── Pagination & filter parameters ───────────────────────────────────────
    $page        = max(1, (int)($_GET['page']        ?? 1));
    $limit       = min(100, max(1, (int)($_GET['limit']      ?? 25)));
    $q           = trim($_GET['q']           ?? '');
    $method      = trim($_GET['method']      ?? '');   // 'Cash' | 'GCash' | ''
    $examPeriod  = trim($_GET['exam_period'] ?? '');   // 'Prelim' | 'Midterm' | 'Finals' | 'Full' | ''
    $semester    = trim($_GET['semester']    ?? '');   // e.g. '1st Semester' | ''
    $category    = trim($_GET['category']    ?? '');   // 'College' | 'SHS' | 'TVET' | ''
    $department  = trim($_GET['department']  ?? '');   // partial match on programs.department | ''
    $yearLevel   = trim($_GET['year_level']  ?? '');   // '1st Year' | '2nd Year' | etc. | ''
    $status      = trim($_GET['status']      ?? '');   // 'Verified' | 'Rejected' | ''
    $offset      = ($page - 1) * $limit;

    // ── Build WHERE clauses dynamically ──────────────────────────────────────
    $whereParts = ["pl.status IN ('Verified','Rejected')"];
    $bindTypes  = '';
    $bindValues = [];

    if ($q !== '') {
        $whereParts[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_number LIKE ? OR CONCAT(s.first_name,' ',s.last_name) LIKE ? OR s.program LIKE ?)";
        $like = '%' . $q . '%';
        $bindTypes  .= 'sssss';
        $bindValues  = array_merge($bindValues, [$like, $like, $like, $like, $like]);
    }
    if ($method !== '') {
        if (strtolower($method) === 'cash') {
            $whereParts[] = "(LOWER(pl.payment_method) = 'cash' OR pl.gcash_reference = 'CASH-PAYMENT')";
        } elseif (strtolower($method) === 'gcash') {
            $whereParts[] = "(LOWER(pl.payment_method) = 'gcash' AND (pl.gcash_reference IS NULL OR pl.gcash_reference != 'CASH-PAYMENT'))";
        }
    }
    if ($examPeriod !== '') {
        $whereParts[] = "ip.exam_period = ?";
        $bindTypes   .= 's';
        $bindValues[] = $examPeriod;
    }
    if ($semester !== '') {
        $whereParts[] = "pl.semester LIKE ?";
        $bindTypes   .= 's';
        $bindValues[] = '%' . $semester . '%';
    }
    if ($category !== '') {
        // FIX FILTER-03: case-insensitive match; stored values may differ in case
        $whereParts[] = "UPPER(s.student_category) = UPPER(?)";
        $bindTypes   .= 's';
        $bindValues[] = $category;
    }
    if ($department !== '') {
        $whereParts[] = "s.program LIKE ?";
        $bindTypes   .= 's';
        $bindValues[] = '%' . $department . '%';
    }
    if ($yearLevel !== '') {
        $whereParts[] = "LOWER(s.year_level) = LOWER(?)";
        $bindTypes   .= 's';
        $bindValues[] = $yearLevel;
    }
    if ($status !== '') {
        // FIX FILTER-01: Replace the default IN clause at index 0 AND prepend the
        // bind value BEFORE any other values already accumulated so the ? positions
        // in $whereParts[0] and $bindValues stay in sync. Using array_unshift on
        // $bindValues alone was correct only when no other filters preceded this
        // block — now we rebuild both arrays atomically to guarantee order.
        $whereParts[0] = "pl.status = ?";
        // Only prepend if not already prepended (idempotent guard)
        if (substr($bindTypes, 0, 1) !== 's' || empty($bindValues) || $bindValues[0] !== $status) {
            $bindTypes  = 's' . $bindTypes;
            array_unshift($bindValues, $status);
        }
    }

    $whereSQL = 'WHERE ' . implode(' AND ', $whereParts);

    // ── COUNT query for pagination metadata ──────────────────────────────────
    // FIX FILTER-02: When exam_period filter is active, ip.exam_period = ? only
    // matches rows that have a linked installment_payments record. Use the same
    // JOIN type here as in the data query so counts stay in sync with results.
    $ipJoinType = ($examPeriod !== '') ? 'INNER JOIN' : 'LEFT JOIN';
    $countSQL = "
        SELECT COUNT(*) AS total
        FROM payment_logs pl
        JOIN students s ON pl.student_id = s.id
        {$ipJoinType} installment_payments ip ON ip.payment_log_id = pl.id
        $whereSQL
    ";
    if ($bindTypes !== '') {
        $cStmt = $conn->prepare($countSQL);
        $cStmt->bind_param($bindTypes, ...$bindValues);
        $cStmt->execute();
        $totalCount = (int)($cStmt->get_result()->fetch_assoc()['total'] ?? 0);
        $cStmt->close();
    } else {
        $totalCount = (int)($conn->query($countSQL)->fetch_assoc()['total'] ?? 0);
    }
    $totalPages = $totalCount > 0 ? (int)ceil($totalCount / $limit) : 1;

    // ── Main data query with LIMIT / OFFSET ───────────────────────────────────
    $dataSQL = "
        SELECT pl.id AS log_id, pl.student_id, pl.payment_method, pl.gcash_reference,
               pl.gcash_amount, pl.gcash_date, pl.transaction_id, pl.semester, pl.status,
               pl.notes, pl.verified_at, pl.created_at AS submitted_at,
               s.student_number, s.first_name, s.last_name, s.program, s.year_level,
               s.student_category,
               COALESCE(sp.first_name, f2.first_name) AS verified_by_fname,
               COALESCE(sp.last_name,  f2.last_name)  AS verified_by_lname,
               tf.total_assessment,
               ip.or_ar_number, ip.or_ar_type, ip.exam_period,
               ip.amount AS ip_amount, ip.payment_date AS ip_payment_date,
               s.program AS department
        FROM payment_logs pl
        JOIN students s ON pl.student_id = s.id
        LEFT JOIN users u ON pl.verified_by = u.id
        LEFT JOIN staff_profiles sp ON sp.user_id = u.id
        LEFT JOIN faculty f2 ON f2.user_id = u.id
        LEFT JOIN (SELECT student_id, total_assessment, subtotal, units FROM tuition_fees WHERE id IN (SELECT MAX(id) FROM tuition_fees GROUP BY student_id)) tf ON tf.student_id = s.id
        {$ipJoinType} installment_payments ip ON ip.payment_log_id = pl.id
        LEFT JOIN programs p ON (p.name = s.program OR p.code = s.program)
                              AND p.level_type = s.student_category
        $whereSQL
        ORDER BY pl.verified_at DESC
        LIMIT ? OFFSET ?
    ";
    $dataBindTypes  = $bindTypes . 'ii';
    $dataBindValues = array_merge($bindValues, [$limit, $offset]);

    $dStmt = $conn->prepare($dataSQL);
    $dStmt->bind_param($dataBindTypes, ...$dataBindValues);
    $dStmt->execute();
    $result = $dStmt->get_result();
    $dStmt->close();

    // ── Build rows (same shape as before) ────────────────────────────────────
    $rows = [];
    if ($result) {
        // Collect all student IDs so we can batch-fetch total_paid
        $sids = [];
        $raw  = [];
        while ($r = $result->fetch_assoc()) {
            $raw[]  = $r;
            $sids[] = (int)$r['student_id'];
        }
        // Batch total_paid lookup to avoid N+1 queries
        $totalPaidMap = [];
        if (!empty($sids)) {
            $inList   = implode(',', array_unique($sids));
            // FIX SEM-SCOPE: Join to students to scope SUM to current semester only.
            // Without this, payments from previous semesters are included in totalPaid,
            // making returning students appear to have already paid for the current term.
            $tpResult = $conn->query("SELECT ip.student_id, COALESCE(SUM(ip.amount),0) AS tp FROM installment_payments ip JOIN students _st ON _st.id = ip.student_id WHERE ip.student_id IN ($inList) AND ip.semester = _st.semester GROUP BY ip.student_id");
            if ($tpResult) {
                while ($tp = $tpResult->fetch_assoc()) {
                    $totalPaidMap[(int)$tp['student_id']] = (float)$tp['tp'];
                }
            }
        }

        foreach ($raw as $r) {
            $isCash    = strtolower($r['payment_method'] ?? '') === 'cash' || ($r['gcash_reference'] ?? '') === 'CASH-PAYMENT';
            $sid       = (int)$r['student_id'];
            $totalPaid = $totalPaidMap[$sid] ?? 0.0;

            $rows[] = [
                'logId'          => (int)$r['log_id'],
                'studentId'      => $sid,
                'studentNumber'  => $r['student_number'],
                'firstName'      => $r['first_name'],
                'lastName'       => $r['last_name'],
                'program'        => $r['program'],
                'yearLevel'      => $r['year_level'],
                'department'     => $r['department'] ?? '',
                'studentCategory'=> $r['student_category'] ?? '',
                'paymentMethod'  => $isCash ? 'Cash' : 'GCash',
                'gcashReference' => $isCash ? '' : ($r['gcash_reference'] ?? ''),
                'gcashAmount'    => isset($r['ip_amount']) && $r['ip_amount'] !== null
                                        ? (float)$r['ip_amount']
                                        : (float)($r['gcash_amount'] ?? 0),
                'gcashDate'      => isset($r['ip_payment_date']) && $r['ip_payment_date']
                                        ? $r['ip_payment_date']
                                        : ($isCash ? ($r['verified_at'] ? date('Y-m-d', strtotime($r['verified_at'])) : '') : ($r['gcash_date'] ?? '')),
                'transactionId'  => $isCash ? '' : ($r['transaction_id'] ?? ''),
                'semester'       => $r['semester'],
                'status'         => $r['status'],
                'notes'          => $r['notes'],
                'verifiedAt'     => $r['verified_at'],
                'verifiedByName' => trim(($r['verified_by_fname'] ?? '') . ' ' . ($r['verified_by_lname'] ?? '')) ?: 'Accounting',
                'submittedAt'    => $r['submitted_at'],
                'totalAssessment'=> (float)($r['total_assessment'] ?? 0),
                'totalPaid'      => $totalPaid,
                'balance'        => max(0, (float)($r['total_assessment'] ?? 0) - $totalPaid),
                'orArNumber'     => $r['or_ar_number'] ?? '',
                'orArType'       => $r['or_ar_type']   ?? '',
                'examPeriod'     => $r['exam_period']   ?? '',
            ];
        }
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    global $authUser;
    $rows = applyPrivacyList($rows, $authUser, 'financial');
    echo json_encode([
        'success'    => true,
        'history'    => $rows,
        'total'      => $totalCount,
        'page'       => $page,
        'limit'      => $limit,
        'totalPages' => $totalPages,
    ]);
}

// ─────────────────────────────────────────────────────────────
// ACCOUNTING: Verify payment
// ─────────────────────────────────────────────────────────────
// =============================================================================
// triggerPaymentVerifiedEmail()
// After a payment is verified, look up the student's primary/emergency guardian
// email from student_guardians. If one exists, log a pending notification in
// email_notifications, then fire a non-blocking internal HTTP call to
// notify.php?action=send_soa so the student/guardian receives an automatic SOA.
//
// The call uses file_get_contents with a 3-second timeout and is intentionally
// fire-and-forget — a failure here never blocks the verify response.
// =============================================================================
function triggerPaymentVerifiedEmail(mysqli $conn, int $student_id, string $examPeriod, float $amount): void {
    // ── 1. Resolve guardian email ────────────────────────────────────────────
    // Prefer the emergency guardian with an email. Fall back to any guardian
    // with an email if no emergency record exists.
    $gStmt = $conn->prepare(
        "SELECT email FROM student_guardians
          WHERE student_id = ? AND email IS NOT NULL AND TRIM(email) != ''
          ORDER BY is_emergency DESC, id ASC
          LIMIT 1"
    );
    if (!$gStmt) return;
    $gStmt->bind_param('i', $student_id);
    $gStmt->execute();
    $gRow = $gStmt->get_result()->fetch_assoc();
    $gStmt->close();

    if (!$gRow || empty(trim($gRow['email']))) {
        // No guardian email on file — nothing to send
        return;
    }
    $guardianEmail = trim($gRow['email']);

    // ── 2. Log notification attempt ──────────────────────────────────────────
    $subject = "Payment Verified – $examPeriod (₱" . number_format($amount, 2) . ")";
    $logStmt = $conn->prepare(
        "INSERT INTO email_notifications
             (student_id, recipient, type, subject, status, created_at)
         VALUES (?, ?, 'soa', ?, 'pending', NOW())"
    );
    if (!$logStmt) return;
    $logStmt->bind_param('iss', $student_id, $guardianEmail, $subject);
    $logStmt->execute();
    $notifId = (int)$conn->insert_id;
    $logStmt->close();

    // ── 3. Fire non-blocking HTTP call to notify.php?action=send_soa ────────
    // We use the current request's Bearer token so notify.php can authenticate.
    // The token is extracted from the Authorization or X-Auth-Token header —
    // the same sources auth_middleware already probed.
    $token = '';
    $candidates = [
        $_SERVER['HTTP_AUTHORIZATION']          ?? '',
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '',
        $_SERVER['HTTP_X_AUTH_TOKEN']           ?? '',
        getenv('HTTP_AUTHORIZATION')            ?: '',
    ];
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $hName => $hVal) {
            if (strtolower($hName) === 'authorization') { $candidates[] = $hVal; break; }
        }
    }
    foreach ($candidates as $c) {
        $c = trim((string)$c);
        if (preg_match('/^Bearer\s+(\S+)$/i', $c, $m)) { $token = $m[1]; break; }
        if (strlen($c) === 64 && ctype_xdigit($c))     { $token = $c;    break; }
    }
    if (!$token && !empty($_SERVER['HTTP_X_AUTH_TOKEN'])) {
        $token = trim($_SERVER['HTTP_X_AUTH_TOKEN']);
    }

    // Build the internal URL — same host + path as the current request
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir      = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
    $notifyUrl = "$scheme://$host$dir/notify.php?action=send_soa&student_id=$student_id&notif_id=$notifId";

    $context = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'header'        => "Authorization: Bearer $token\r\nX-Auth-Token: $token\r\n",
            'timeout'       => 3,           // 3 s max — never blocks main response
            'ignore_errors' => true,
        ],
        'ssl'  => [
            'verify_peer'      => false,    // safe for localhost/intranet
            'verify_peer_name' => false,
        ],
    ]);

    // Suppress errors — fire-and-forget; result is intentionally discarded
    @file_get_contents($notifyUrl, false, $context);
}

function verifyPayment($conn, $data) {
    $log_id         = (int)($data['log_id']             ?? 0);
    $student_id     = (int)($data['student_id']         ?? 0);
    $acc_user_id    = (int)($data['accounting_user_id'] ?? 0);
    $notes          = trim($data['notes']               ?? '');
    $payment_method = strtolower(trim($data['payment_method'] ?? ''));

    // For cash: accounting enters amount + date; for GCash: read from payment_log
    $cash_amount = isset($data['cash_amount']) ? (float)$data['cash_amount'] : null;
    $cash_date   = isset($data['cash_date'])   ? trim($data['cash_date'])    : date('Y-m-d');

    if (!$log_id || !$student_id) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'log_id and student_id required']); return;
    }

    // Validate cash_amount: must be positive
    if ($cash_amount !== null && $cash_amount <= 0) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'Cash amount must be greater than zero.']);
        return;
    }

    // Read original notes + amount BEFORE updating (notes will be overwritten by accounting notes)
    $logRow = (($_r=$conn->query("SELECT gcash_amount, gcash_date, payment_method, notes, status, exam_period, semester FROM payment_logs WHERE id = $log_id LIMIT 1")) ? $_r->fetch_assoc() : null);

    // FIX AC-SEMESTER-03: Sync payment_logs.semester with students.semester before verification.
    // After reEnroll(), old payment_logs may carry the previous semester. Correct it now so
    // receipts, SOA, and liquidation reports show the correct new semester label.
    $currentSemSt = $conn->prepare("SELECT semester FROM students WHERE id = ? LIMIT 1");
    $currentSemSt->bind_param('i', $student_id);
    $currentSemSt->execute();
    $currentSemRow = $currentSemSt->get_result()->fetch_assoc();
    $currentSemSt->close();
    $currentSem = trim($currentSemRow['semester'] ?? '');
    if ($currentSem !== '' && ($logRow['semester'] ?? '') !== $currentSem) {
        $safeSem = $conn->real_escape_string($currentSem);
        $conn->query("UPDATE payment_logs SET semester = '$safeSem' WHERE id = $log_id");
    }
    if (!$logRow) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'Payment log not found']); return;
    }
    if ($logRow['status'] !== 'Pending') {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'Payment already processed']); return;
    }

    // ── FIX SCHOLAR-VERIFY-01: Block payment verification while scholarship is pending ──
    // Scholarship must be approved OR rejected first before any payment can be verified.
    // Without this guard, accepting payment before scholarship review leads to corrupt state:
    //   - rejectScholarship() resets discount=0 and payment_status='Pending' AFTER payment
    //     was already marked Verified, leaving the student with a wrong balance.
    //   - approveScholarship() changes total_assessment AFTER payment was already recorded,
    //     so the payment amount no longer matches the correct balance.
    // Flow must be: Scholarship reviewed → discount applied → student pays → payment verified.
    $pendingSchStmt = $conn->prepare("
        SELECT id, scholar_type
        FROM   student_scholarships
        WHERE  student_id = ?
          AND  status     = 'pending'
        LIMIT  1
    ");
    if ($pendingSchStmt) {
        $pendingSchStmt->bind_param('i', $student_id);
        $pendingSchStmt->execute();
        $pendingSchRow = $pendingSchStmt->get_result()->fetch_assoc();
        $pendingSchStmt->close();
        if ($pendingSchRow) {
            while (ob_get_level() > 0) { ob_end_clean(); }
            echo json_encode([
                'success'          => false,
                'pending_scholarship' => true,
                'message'          => 'Cannot verify payment — this student has a pending scholarship application ('
                                      . htmlspecialchars($pendingSchRow['scholar_type'] ?? 'Scholar')
                                      . '). Please approve or reject the scholarship first so the correct balance is computed before payment is accepted.',
            ]);
            return;
        }
    }
    // ── END FIX SCHOLAR-VERIFY-01 ─────────────────────────────────────────────────────────

    $originalNotes = $logRow['notes']; // preserve exam_period prefix before overwriting

    // Update payment log (notes field = accounting remarks, may overwrite original notes)
    if ($payment_method === 'cash' && $cash_amount !== null) {
        $stmt = $conn->prepare("UPDATE payment_logs SET status = 'Verified', verified_by = ?, verified_at = NOW(), notes = ?, gcash_amount = ?, gcash_date = ? WHERE id = ?");
        $stmt->bind_param("isdsi", $acc_user_id, $notes, $cash_amount, $cash_date, $log_id);
    } else {
        $stmt = $conn->prepare("UPDATE payment_logs SET status = 'Verified', verified_by = ?, verified_at = NOW(), notes = ? WHERE id = ?");
        $stmt->bind_param("isi", $acc_user_id, $notes, $log_id);
    }
    $stmt->execute();
    // Note: do NOT block on affected_rows === 0 — MySQL returns 0 when the row values
    // didn't change (e.g. notes was already blank). The status check above already
    // guarantees the log was Pending before this call.

    // Use pre-UPDATE logRow (has original notes with exam_period prefix)
    $final_amount = ($payment_method === 'cash') ? ($cash_amount ?? 0) : (float)($logRow['gcash_amount'] ?? 0);
    $final_date   = ($payment_method === 'cash') ? $cash_date : ($logRow['gcash_date'] ?? date('Y-m-d'));
    $pm_label     = ($payment_method === 'cash') ? 'Cash' : 'GCash';

    // Get student payment plan
    $stRowSt = $conn->prepare("SELECT payment_plan, enrollment_status FROM students WHERE id = ? LIMIT 1");
    $stRowSt->bind_param('i', $student_id);
    $stRowSt->execute();
    $stRow = $stRowSt->get_result()->fetch_assoc();
    $stRowSt->close();
    // FIX AC-PLAN-02: NULL payment_plan means student hasn't chosen yet (re-enrollment).
    // Default to 'full' here so verify can proceed — the plan should already be set
    // by updatePaymentPlan() before payment is submitted. If somehow still NULL,
    // treat as full payment so OR (not AR) is issued.
    $paymentPlan = ($stRow['payment_plan'] !== null && $stRow['payment_plan'] !== '')
        ? $stRow['payment_plan']
        : 'full';
    $isEnrolled  = ($stRow['enrollment_status'] ?? '') === 'Enrolled';

    // Parse exam_period — first check dedicated column, then fall back to ORIGINAL notes prefix
    $notesRaw   = trim($originalNotes ?? $logRow['notes'] ?? '');
    $examPeriod = trim($logRow['exam_period'] ?? ''); // dedicated column wins
    if (!$examPeriod && preg_match('/^(Prelim|Midterm|Finals|Downpayment|Full)\|?/i', $notesRaw, $m)) {
        $examPeriod = $m[1];
        $notes      = $notes ?: trim(substr($notesRaw, strlen($m[0])));
    }

    // ── Lock check: block approval if accounting hasn't unlocked this period ──
    // Prevents approving a Prelim/Midterm/Finals payment that arrived before the
    // notice was sent. Accounting must unlock the period first via send_payment_notice
    // or unlock_payment_period.
    if ($paymentPlan === 'installment' && in_array($examPeriod, ['Prelim', 'Midterm', 'Finals'])) {
        $p       = strtolower($examPeriod);
        $lkChk   = $conn->prepare("SELECT {$p}_status FROM payment_schedules WHERE student_id = ? LIMIT 1");
        $lkChk->bind_param('i', $student_id);
        $lkChk->execute();
        $lkRow   = $lkChk->get_result()->fetch_assoc();
        $lkChk->close();
        if (($lkRow[$p . '_status'] ?? 'locked') === 'locked') {
            while (ob_get_level() > 0) { ob_end_clean(); }
            echo json_encode([
                'success' => false,
                'locked'  => true,
                'message' => "$examPeriod is still locked. Send a payment notice first to unlock this period before approving.",
            ]);
            return;
        }
    }

    // Auto-create installment_payments record if not already done
    // Avoid duplicates via payment_log_id check
    $dupCheck = $conn->prepare("SELECT id FROM installment_payments WHERE payment_log_id = ? LIMIT 1");
    $dupCheck->bind_param("i", $log_id);
    $dupCheck->execute();
    $dupResult = $dupCheck->get_result();

    // Determine OR/AR type and exam period label (needed even if dup, for response message)
    $year = (int)date('Y');
    // FIX GCASH-TERM-02: Full-payment students must always issue OR and exam_period='Full',
    // regardless of what was stamped in payment_logs.exam_period. If a stale exam_period
    // (e.g. 'Prelim' from a prior semester's installment row) leaked through before Fix
    // GCASH-TERM-01, this guard prevents a full-payment from being treated as an AR term payment.
    if ($paymentPlan === 'full') {
        $examPeriod = 'Full';
        $or_ar_type = 'OR';
    } elseif ($isEnrolled && $examPeriod && in_array($examPeriod, ['Prelim','Midterm','Finals'])) {
        $or_ar_type = 'AR';
    } elseif ($paymentPlan === 'installment') {
        $or_ar_type = 'AR';
        $examPeriod = $examPeriod ?: 'Downpayment';
    } else {
        $or_ar_type = 'OR';
        $examPeriod = $examPeriod ?: 'Full';
    }

    if ($dupResult->num_rows === 0) {
        // CHANGE OR-MANUAL-01: OR/AR number is now entered manually by the cashier
        // to match the number printed on the physical official receipt.
        // The old auto-sequence logic (or_ar_sequences table) is no longer used here.
        $or_no = trim($data['or_ar_number'] ?? '');

        if ($or_no === '') {
            while (ob_get_level() > 0) { ob_end_clean(); }
            echo json_encode(['success' => false, 'message' => 'OR/AR number is required. Please enter the number from the physical official receipt.']);
            return;
        }

        // CHANGE OR-MANUAL-01: Prevent duplicate OR/AR numbers.
        $dupOrChk = $conn->prepare("SELECT id FROM installment_payments WHERE or_ar_number = ? LIMIT 1");
        if ($dupOrChk) {
            $dupOrChk->bind_param('s', $or_no);
            $dupOrChk->execute();
            $dupOrExisting = $dupOrChk->get_result()->fetch_assoc();
            $dupOrChk->close();
            if ($dupOrExisting) {
                while (ob_get_level() > 0) { ob_end_clean(); }
                echo json_encode(['success' => false, 'message' => "OR/AR number '{$or_no}' already exists in the system. Please check the physical receipt and enter the correct number."]);
                return;
            }
        }

        if ($payment_method !== 'cash') {
            $grStmt = $conn->prepare("SELECT gcash_reference FROM payment_logs WHERE id = ? LIMIT 1");
            $grStmt->bind_param("i", $log_id);
            $grStmt->execute();
            $gcash_ref = $grStmt->get_result()->fetch_assoc()['gcash_reference'] ?? '';
        } else {
            $gcash_ref = '';
        }

        $ins = $conn->prepare("
            INSERT INTO installment_payments
                (student_id, payment_log_id, or_ar_number, or_ar_type, amount, payment_date, payment_method, gcash_reference, exam_period, notes, recorded_by, semester)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, (SELECT semester FROM students WHERE id=? LIMIT 1))
        ");
        $ins->bind_param("iissdsssssii", $student_id, $log_id, $or_no, $or_ar_type, $final_amount, $final_date, $pm_label, $gcash_ref, $examPeriod, $notes, $acc_user_id, $student_id);
        $ins->execute();
    } else {
        // Already inserted — retrieve existing OR/AR number for response
        $exRow = (($_r=$conn->query("SELECT or_ar_number FROM installment_payments WHERE payment_log_id = $log_id LIMIT 1")) ? $_r->fetch_assoc() : null);
        $or_no = $exRow['or_ar_number'] ?? '';
    }

    // ── Always run post-payment updates (schedule + enrollment status) ──────
    // Sync payment_schedules for Prelim/Midterm/Finals.
    // FIX: was checking $isEnrolled (enrollment_status='Enrolled') but an installment student
    // can legitimately be 'Confirmed' and still paying term fees. The correct gate is whether
    // the exam_period is a term payment — not whether enrollment_status is exactly 'Enrolled'.
    $isTermPayment = in_array($examPeriod, ['Prelim','Midterm','Finals']);
    if ($isTermPayment) {
        $ep        = strtolower($examPeriod);
        $schedRes  = $conn->query("SELECT {$ep}_due FROM payment_schedules WHERE student_id=$student_id LIMIT 1");
        $schedRow  = $schedRes ? $schedRes->fetch_assoc() : null;
        $periodDue = $schedRow ? (float)$schedRow[$ep.'_due'] : 0;
        $paidRes   = $conn->query("SELECT COALESCE(SUM(amount),0) AS paid FROM installment_payments ip JOIN students _st ON _st.id=ip.student_id WHERE ip.student_id=$student_id AND ip.exam_period='$examPeriod' AND ip.semester=_st.semester");
        $periodPaid = (float)$paidRes->fetch_assoc()['paid'];
        $newStatus  = $periodPaid <= 0 ? 'unpaid' : ($periodPaid >= $periodDue ? 'paid' : 'partial');
        $conn->query("UPDATE payment_schedules SET {$ep}_paid=$periodPaid, {$ep}_status='$newStatus' WHERE student_id=$student_id");

        recomputeSchedule($conn, $student_id);

        // Check if fully paid now — update student payment_status accordingly
        $tfR2      = (($_r=$conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id=$student_id LIMIT 1")) ? $_r->fetch_assoc() : null);
        $totalAmt  = $tfR2 ? (float)$tfR2['total_assessment'] : 0;
        $allPaidR  = (($_r=$conn->query("SELECT COALESCE(SUM(amount),0) AS paid FROM installment_payments ip JOIN students _st ON _st.id=ip.student_id WHERE ip.student_id=$student_id AND ip.semester=_st.semester")) ? $_r->fetch_assoc() : null);
        $allPaid   = (float)$allPaidR['paid'];
        // Correctly reflect partial payments: 'Partial' is valid and used elsewhere in this file.
        $newPayStatus = ($totalAmt > 0 && $allPaid >= $totalAmt) ? 'Paid'
                      : ($allPaid > 0 ? 'Partial' : 'Pending');
        $conn->query("UPDATE students SET payment_status='$newPayStatus' WHERE id=$student_id");

        logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'VERIFY_PAYMENT', 'student', $student_id,
            "Verified $examPeriod payment ₱" . number_format($final_amount, 2) . " for student ID $student_id (OR: $or_no)");

        // Auto-notify: attempt to email guardian SOA for term payment
        triggerPaymentVerifiedEmail($conn, $student_id, $examPeriod, $final_amount);

        // FIX SOA-SNAPSHOT-03: Refresh snapshot on every verified term payment so the
        // Accounting SOA viewer always reflects the latest paid/balance state.
        if (function_exists('saveSoaSnapshot')) {
            saveSoaSnapshot($conn, $student_id);
        }

        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => true, 'message' => "$examPeriod payment verified. ₱" . number_format($final_amount, 2) . " recorded."]);
        return;
    }

    // Downpayment for installment plan: enroll and recompute remaining term dues
    if ($paymentPlan === 'installment' && $examPeriod === 'Downpayment') {
        // FIX TVET-SCHED-01: For TVET/SHS flat-rate students, payment_schedules may
        // not exist yet (recomputeSchedule returns early if total_assessment=0 or
        // no installment row). Seed it from tuition_fees before calling recompute.
        $schedChkTV = $conn->query("SELECT id FROM payment_schedules WHERE student_id=$student_id LIMIT 1");
        if (!$schedChkTV || $schedChkTV->num_rows === 0) {
            $tfSeedR = $conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id=$student_id LIMIT 1");
            $tfSeed  = $tfSeedR ? (float)($tfSeedR->fetch_assoc()['total_assessment'] ?? 0) : 0;
            if ($tfSeed > 0) {
                $dpSeed = round($tfSeed / 4, 2);
                $remSeed = max(0, $tfSeed - $dpSeed);
                $pdSeed  = ceil($remSeed / 3 * 100) / 100;
                $fdSeed  = round($remSeed - $pdSeed * 2, 2);
                $conn->query("INSERT INTO payment_schedules
                    (student_id, payment_type, total_assessment,
                     prelim_due, midterm_due, finals_due,
                     prelim_status, midterm_status, finals_status)
                    VALUES ($student_id, 'installment', $tfSeed,
                            $pdSeed, $pdSeed, $fdSeed,
                            'locked', 'locked', 'locked')
                    ON DUPLICATE KEY UPDATE
                        payment_type='installment', total_assessment=$tfSeed,
                        prelim_due=$pdSeed, midterm_due=$pdSeed, finals_due=$fdSeed");
            }
        }
        recomputeSchedule($conn, $student_id);

        // Downpayment verified — set Confirmed (awaiting registrar final approval).
        // FIX DASH-PAYSTATUS-01: Use 'Partial' (not 'Pending') so the student dashboard
        // correctly shows that a payment has been received, not that they haven't paid yet.
        // FIX AC-PLAN-HEAL-01: COALESCE heals a NULL payment_plan (reset by reEnroll() but
        // not yet committed by updatePaymentPlan()). Without this, needsPlanSelection stays
        // true on next Angular loadContext() → student re-routed to plan selector after paying.
        $upd = $conn->prepare("UPDATE students SET payment_status='Partial', approval_status='Approved', enrollment_status='Confirmed', payment_plan=COALESCE(NULLIF(payment_plan,''),'$paymentPlan'), accounting_approved_by=?, accounting_approved_at=NOW(), accounting_notes=? WHERE id=?");
        $upd->bind_param("isi", $acc_user_id, $notes, $student_id);
        $upd->execute();

        logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'VERIFY_PAYMENT', 'student', $student_id,
            "Downpayment verified ₱" . number_format($final_amount, 2) . " for student ID $student_id — awaiting registrar final approval");

        // FIX SOA-SNAPSHOT-02: Freeze SOA at first confirmed payment so historical
        // statements survive re-enrollment. Called here (Downpayment) and at Full payment.
        if (function_exists('saveSoaSnapshot')) {
            saveSoaSnapshot($conn, $student_id);
        }

        // Auto-notify: attempt to email guardian SOA for downpayment
        triggerPaymentVerifiedEmail($conn, $student_id, 'Downpayment', $final_amount);

        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => true, 'message' => "Downpayment verified. ₱" . number_format($final_amount, 2) . " recorded. Forwarded to Registrar for final enrollment approval."]);
        return;
    }

    // Full payment verified — set Confirmed (awaiting registrar final approval).
    // Safety guard: if this is an installment student, never treat any single payment
    // as a full payment regardless of examPeriod. This prevents the bug where
    // Prelim/Midterm/Finals payments were incorrectly marking payment_status='Paid'
    // and setting all schedule periods to paid.
    if ($paymentPlan === 'installment') {
        // Installment student reached here — this should only happen for edge cases
        // (e.g. examPeriod was somehow empty). Treat it as a Downpayment and recompute.
        // FIX DASH-PAYSTATUS-01: Use 'Partial' so student sees payment was received.
        // FIX AC-PLAN-HEAL-01: COALESCE heals a NULL payment_plan — see downpayment path above.
        recomputeSchedule($conn, $student_id);
        $upd = $conn->prepare("UPDATE students SET payment_status='Partial', approval_status='Approved', enrollment_status='Confirmed', payment_plan=COALESCE(NULLIF(payment_plan,''),'$paymentPlan'), accounting_approved_by=?, accounting_approved_at=NOW(), accounting_notes=? WHERE id=?");
        $upd->bind_param("isi", $acc_user_id, $notes, $student_id);
        $upd->execute();
        logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'VERIFY_PAYMENT', 'student', $student_id,
            "Installment payment verified ₱" . number_format($final_amount, 2) . " for student ID $student_id (OR: $or_no)");

        // Auto-notify: attempt to email guardian SOA for installment payment
        triggerPaymentVerifiedEmail($conn, $student_id, $examPeriod ?: 'Payment', $final_amount);

        // FIX SOA-SNAPSHOT-03: Refresh snapshot on installment fallback path too.
        if (function_exists('saveSoaSnapshot')) {
            saveSoaSnapshot($conn, $student_id);
        }

        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => true, 'message' => "Payment verified. ₱" . number_format($final_amount, 2) . " recorded."]);
        return;
    }

    // FIX AC-PLAN-HEAL-01: COALESCE heals a NULL payment_plan — see downpayment path above.
    $upd = $conn->prepare("UPDATE students SET payment_status='Paid', approval_status='Approved', enrollment_status='Confirmed', payment_plan=COALESCE(NULLIF(payment_plan,''),'$paymentPlan'), accounting_approved_by=?, accounting_approved_at=NOW(), accounting_notes=? WHERE id=?");
    $upd->bind_param("isi", $acc_user_id, $notes, $student_id);
    $upd->execute();

    // Mark all schedule periods as paid
    $tfRow = (($_r=$conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id=$student_id LIMIT 1")) ? $_r->fetch_assoc() : null);
    if ($tfRow) {
        $conn->query("UPDATE payment_schedules
            SET prelim_paid=prelim_due, midterm_paid=midterm_due, finals_paid=finals_due,
                prelim_status='paid', midterm_status='paid', finals_status='paid'
            WHERE student_id=$student_id");
    }

    logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'VERIFY_PAYMENT', 'student', $student_id,
        "Full payment verified for student ID $student_id — awaiting registrar final approval");

    // FIX SOA-SNAPSHOT-02: Freeze SOA on full payment confirmation so historical
    // statements survive re-enrollment. Snapshot includes all enrolled subjects and receipts.
    if (function_exists('saveSoaSnapshot')) {
        saveSoaSnapshot($conn, $student_id);
    }

    // Auto-notify: attempt to email guardian SOA for full payment
    triggerPaymentVerifiedEmail($conn, $student_id, 'Full', $final_amount);

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'message' => 'Payment verified. Forwarded to Registrar for final enrollment approval.']);
}

// ─────────────────────────────────────────────────────────────
// ACCOUNTING: Reject payment
// ─────────────────────────────────────────────────────────────
function rejectPayment($conn, $data) {
    $log_id      = (int)($data['log_id']             ?? 0);
    $student_id  = (int)($data['student_id']         ?? 0);
    $acc_user_id = (int)($data['accounting_user_id'] ?? 0);
    $notes       = trim($data['notes'] ?? '');

    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$log_id || !$student_id) { echo json_encode(['success' => false, 'message' => 'log_id and student_id required']); return; }

    // FIX REJECT-NOTES-01: Add a dedicated rejection_reason column so the
    // accounting rejection message is never overwritten by later payment notes.
    // The notes column is reused for many purposes (exam_period prefix, etc.),
    // which caused the student to see a blank reason after rejection.
    $conn->query("ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS rejection_reason TEXT DEFAULT NULL");

    // FIX REJECT-NOTES-01 (continued): Also add rejection_reason to the students
    // table so getPaymentStatus() can return it without an extra JOIN.
    $conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS rejection_reason TEXT DEFAULT NULL");

    // Update payment_logs: mark Rejected, save notes AND rejection_reason.
    $stmt = $conn->prepare("UPDATE payment_logs SET status = 'Rejected', verified_by = ?, verified_at = NOW(), notes = ?, rejection_reason = ? WHERE id = ? AND status = 'Pending'");
    $stmt->bind_param("issi", $acc_user_id, $notes, $notes, $log_id);
    $stmt->execute();
    $stmt->close();

    // Reset student payment/approval status AND store the rejection reason
    // directly on the students row so the enrollment page can display it
    // immediately via the lightweight getPaymentStatus() poll — no extra JOIN.
    $upd = $conn->prepare("UPDATE students SET payment_status = 'Pending', approval_status = 'Pending', rejection_reason = ? WHERE id = ?");
    $upd->bind_param("si", $notes, $student_id);
    $upd->execute();
    $upd->close();

    logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'REJECT_PAYMENT', 'student', $student_id,
        "Payment rejected for student ID $student_id. Log: $log_id. Reason: $notes");
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'message' => 'Payment rejected.']);
}

// ═══════════════════════════════════════════════════════════════════════════════
// SHARED HELPER: Recompute and save payment_schedules for one installment student
// Call this after any payment is recorded or on page load.
//
// Algorithm:
//   remaining = total - sum(ALL installment_payments for this student)
//   For each period in [Prelim, Midterm, Finals]:
//     - If locked: due unchanged, paid=0
//     - If paid (actual_paid >= due):
//         actual_paid = SUM from installment_payments
//         due = original due (unchanged once paid)
//         status = 'paid'
//     - If unpaid/partial:
//         Redistribute remaining balance equally across these unpaid terms
//         due (updated) = ceil(remaining_for_unpaid_terms / count_unpaid_terms)
//         paid = actual from installment_payments
//         status = 'unpaid'|'partial'
//
// This means: if student overpays Prelim, the extra reduces remaining balance,
// which shrinks Midterm+Finals dues proportionally.
// ═══════════════════════════════════════════════════════════════════════════════
function recomputeSchedule(mysqli $conn, int $student_id): void {
    // ── Get total assessment — scoped to student's CURRENT semester ───────────
    // FIX RECOMPUTE-SEM-01: Without the semester filter, re-enrolled students
    // (who have one tuition_fees row per semester) return the first/oldest row,
    // causing the payment schedule to show the wrong semester's total_assessment.
    $semRR = $conn->query("SELECT semester FROM students WHERE id=$student_id LIMIT 1");
    $curSemRR = $semRR ? $conn->real_escape_string(trim($semRR->fetch_assoc()['semester'] ?? '')) : '';
    $tfR = $curSemRR !== ''
        ? $conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id=$student_id AND semester='$curSemRR' ORDER BY id DESC LIMIT 1")
        : null;
    if (!$tfR || !($tfRowRR = $tfR->fetch_assoc())) {
        // Fallback: latest row regardless of semester (handles legacy NULL-semester rows)
        $tfR2 = $conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id=$student_id ORDER BY id DESC LIMIT 1");
        $tfRowRR = $tfR2 ? $tfR2->fetch_assoc() : null;
    }
    $total = $tfRowRR ? (float)($tfRowRR['total_assessment'] ?? 0) : 0;
    if ($total <= 0) return;

    // ── Get current schedule row ──────────────────────────────────────────────
    $schRes = $conn->query("SELECT * FROM payment_schedules WHERE student_id=$student_id LIMIT 1");
    $sch    = $schRes ? $schRes->fetch_assoc() : null;
    if (!$sch || ($sch['payment_type'] ?? '') !== 'installment') return;

    // ── Actual paid per period from installment_payments ─────────────────────
    $paidRes = $conn->query("
        SELECT ip.exam_period, COALESCE(SUM(ip.amount),0) AS paid
        FROM installment_payments ip JOIN students _st ON _st.id=ip.student_id
        WHERE ip.student_id=$student_id AND ip.semester=_st.semester GROUP BY ip.exam_period
    ");
    $paid = ['Downpayment'=>0.0, 'Prelim'=>0.0, 'Midterm'=>0.0, 'Finals'=>0.0];
    if ($paidRes) while ($r = $paidRes->fetch_assoc()) $paid[$r['exam_period']] = (float)$r['paid'];

    // ── FIX CARRY-OVER: Check which periods have an APPROVED permit ───────────
    // If a period has an approved permit, it means Accounting accepted the student
    // for that exam regardless of partial payment. The unpaid balance is treated as
    // SETTLED for that period (carry-over to next term) — so:
    //   • {period}_status  → 'paid'   (exam cleared)
    //   • {period}_due     → actual amount paid (not the original due)
    // _calcInstallmentDues() already redistributes remaining balance to future terms
    // because prCredit = prPaid (not prDue) when prPaid < prDue.
    $permRes = $conn->query("
        SELECT exam_period FROM exam_permits
        WHERE student_id=$student_id AND status='approved'
    ");
    $approvedPermits = [];
    if ($permRes) while ($pr = $permRes->fetch_assoc()) $approvedPermits[] = $pr['exam_period'];

    // ── Use shared helper to compute correct dues ────────────────────────────
    // FIX CARRY-OVER-01: Pass $approvedPermits so _calcInstallmentDues() uses
    // actual paid (not scheduled due) as credit for approved periods. This causes
    // any shortfall to automatically increase the next term's due — carry-over.
    $dues    = _calcInstallmentDues($total, $paid, $approvedPermits);
    $newDue  = ['Prelim' => $dues['prelim'], 'Midterm' => $dues['midterm'], 'Finals' => $dues['finals']];

    // ── Build UPDATE ──────────────────────────────────────────────────────────
    $periods = ['Prelim', 'Midterm', 'Finals'];
    $updates = [];
    foreach ($periods as $p) {
        $col        = strtolower($p);
        $curStatus  = $sch[$col.'_status'] ?? 'locked';
        $newD       = round($newDue[$p], 2);
        $actualPaid = round($paid[$p], 2);

        // FIX CARRY-OVER-01: Approved permit = period is CLEARED regardless of partial payment.
        // _calcInstallmentDues() already used $prPaid (not $prDue) as credit, so the
        // unpaid shortfall is already folded into the next term's $newD. Here we simply
        // set due = paid (balance = 0) and status = 'paid' so the student is NOT asked
        // to pay this period again. The carry-over is visible in the NEXT term's higher due.
        if (in_array($p, $approvedPermits)) {
            $clearAmt  = $actualPaid; // mark as cleared for exactly what was paid
            $updates[] = "{$col}_due=$clearAmt, {$col}_paid=$clearAmt, {$col}_status='paid'";
            continue;
        }

        // Determine status from actual paid vs recomputed due
        $totalPaid  = array_sum($paid);
        $fullyPaid  = ($totalPaid >= $total && $total > 0);

        if ($newD <= 0 && $fullyPaid) {
            $st = 'paid';
        } elseif ($actualPaid >= $newD && $newD > 0) {
            $st = 'paid';
        } elseif ($actualPaid > 0) {
            $st = 'partial';
        } elseif ($curStatus === 'locked') {
            $st = 'locked';
        } else {
            $st = 'unpaid';
        }

        $updates[] = "{$col}_due=$newD, {$col}_paid=$actualPaid, {$col}_status='$st'";
    }

    $conn->query("UPDATE payment_schedules SET total_assessment=$total, "
        . implode(', ', $updates) . " WHERE student_id=$student_id");
}


function getPaymentSchedule($conn) {
    $student_id = (int)($_GET['student_id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$student_id) { echo json_encode(['success'=>false,'message'=>'student_id required']); return; }

    // Support lookup by user_id as fallback
    $stRes = $conn->query("SELECT id, payment_method, payment_plan, semester FROM students WHERE id=$student_id LIMIT 1");
    $stRow = $stRes ? $stRes->fetch_assoc() : null;
    if (!$stRow) {
        // Try by user_id
        $stRes2 = $conn->query("SELECT id, payment_method, payment_plan, semester FROM students WHERE user_id=$student_id LIMIT 1");
        $stRow  = $stRes2 ? $stRes2->fetch_assoc() : null;
        if ($stRow) $student_id = (int)$stRow['id'];
    }
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$stRow) { echo json_encode(['success'=>true,'schedule'=>null,'notices'=>[]]); return; }

    $ptype = (strtolower($stRow['payment_plan'] ?? '') === 'full') ? 'full' : 'installment';

    // FIX-TUITION-SEMESTER-01: scope to current semester to avoid returning stale
    // NULL-semester rows when the student has multiple tuition_fees rows.
    $curSemPS  = $conn->real_escape_string(trim($stRow['semester'] ?? ''));
    $tfRes = $curSemPS !== ''
        ? $conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id=$student_id AND semester='$curSemPS' ORDER BY id DESC LIMIT 1")
        : null;
    if (!$tfRes || !($tfRow = $tfRes->fetch_assoc())) {
        // Fallback: latest row regardless of semester (handles NULL-semester legacy data)
        $tfRes2 = $conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id=$student_id ORDER BY id DESC LIMIT 1");
        $tfRow  = $tfRes2 ? $tfRes2->fetch_assoc() : null;
    }
    $total = $tfRow ? (float)$tfRow['total_assessment'] : 0;

    // ── Recompute term dues based on actual DP paid ──────────────────────────
    // Rule: DP can be any amount the student can afford.
    //   - scheduled DP = total / 4
    //   - remaining after DP = total - dpPaid  (not total - scheduledDP)
    //   - remaining is split equally across Prelim/Midterm/Finals
    // This means:
    //   - small DP  → larger term dues
    //   - large DP  → smaller term dues (or zero if DP covers everything)
    $dpPaidRes  = $conn->query("SELECT COALESCE(SUM(amount),0) AS paid FROM installment_payments ip JOIN students _st ON _st.id=ip.student_id WHERE ip.student_id=$student_id AND ip.exam_period='Downpayment' AND ip.semester=_st.semester");
    $dpPaid     = $dpPaidRes ? (float)$dpPaidRes->fetch_assoc()['paid'] : 0;
    // Use actual DP paid if any, else fall back to scheduled quarter
    $dpCredit   = $dpPaid > 0 ? $dpPaid : ($total > 0 ? round($total / 4, 2) : 0);
    $remaining  = max(0, $total - $dpCredit);
    $pd         = $remaining > 0 ? (ceil($remaining / 3 * 100) / 100) : 0;
    $md         = $pd;
    $fd         = $remaining > 0 ? round(max(0, $remaining - $pd * 2), 2) : 0;

    // Always upsert — dues must reflect the actual DP paid (recomputed every load)
    // NOTE: Do NOT auto-set period statuses here — statuses are only changed
    // when accounting explicitly sends a notice (sendPaymentNotice).
    $conn->query("INSERT INTO payment_schedules
        (student_id,payment_type,total_assessment,prelim_due,midterm_due,finals_due)
        VALUES ($student_id,'$ptype',$total,$pd,$md,$fd)
        ON DUPLICATE KEY UPDATE
          payment_type     = '$ptype',
          total_assessment = IF($total>0, $total, total_assessment),
          prelim_due       = IF($total>0, $pd, prelim_due),
          midterm_due      = IF($total>0, $md, midterm_due),
          finals_due       = IF($total>0, $fd, finals_due)");

    $res = $conn->query("SELECT * FROM payment_schedules WHERE student_id=$student_id LIMIT 1");
    $schedule = $res ? $res->fetch_assoc() : null;
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$schedule) { echo json_encode(['success'=>true,'schedule'=>null]); return; }

    // ── Compute actual paid amounts directly from installment_payments ──────
    // Sum every verified payment per period — no caps, no hacks, no manual DB edits needed.
    $allPaidRes = $conn->query("
        SELECT ip.exam_period, COALESCE(SUM(ip.amount),0) AS paid
        FROM installment_payments ip JOIN students _st ON _st.id=ip.student_id
        WHERE ip.student_id=$student_id AND ip.semester=_st.semester
        GROUP BY ip.exam_period
    ");
    $paidByPeriod = [];
    if ($allPaidRes) {
        while ($row = $allPaidRes->fetch_assoc()) {
            $paidByPeriod[$row['exam_period']] = (float)$row['paid'];
        }
    }

    // Total of ALL payments (Downpayment + Full + Prelim + Midterm + Finals)
    $totalPaidAll = array_sum($paidByPeriod);

    if ($schedule['payment_type'] === 'full') {
        // Full-payment: student pays everything at once.
        // Period statuses are controlled EXCLUSIVELY by accounting sending a notice.
        // Use payment_notices table as source of truth for which periods are unlocked.
        // This ensures DB rows with stale 'paid' status don't bypass the notice requirement.
        $isFullyPaid = $totalPaidAll >= (float)$schedule['total_assessment'];
        if (!$isFullyPaid) {
            $stPayRow = (($_r=$conn->query("SELECT payment_status FROM students WHERE id=$student_id LIMIT 1")) ? $_r->fetch_assoc() : null);
            $isFullyPaid = ($stPayRow && $stPayRow['payment_status'] === 'Paid');
        }
        if ($isFullyPaid) {
            $schedule['prelim_paid']  = (float)$schedule['prelim_due'];
            $schedule['midterm_paid'] = (float)$schedule['midterm_due'];
            $schedule['finals_paid']  = (float)$schedule['finals_due'];
        }
        // Override period statuses from payment_notices (not the DB row).
        // locked   = no notice sent yet
        // paid     = notice was sent (full-payment students don't need to pay after notice)
        $noticeCheckRes = $conn->query("SELECT exam_period FROM payment_notices WHERE student_id=$student_id");
        $noticesSent = [];
        if ($noticeCheckRes) while ($nr = $noticeCheckRes->fetch_assoc()) $noticesSent[] = $nr['exam_period'];
        $schedule['prelim_status']  = in_array('Prelim',  $noticesSent) ? 'paid' : 'locked';
        $schedule['midterm_status'] = in_array('Midterm', $noticesSent) ? 'paid' : 'locked';
        $schedule['finals_status']  = in_array('Finals',  $noticesSent) ? 'paid' : 'locked';
        // downpayment_paid display
        $sumPeriods = (float)$schedule['prelim_paid'] + (float)$schedule['midterm_paid'] + (float)$schedule['finals_paid'];
        $schedule['downpayment_paid'] = max(0, min($totalPaidAll, (float)$schedule['total_assessment']) - $sumPeriods);
    } else {
        // Installment: use recomputeSchedule() to dynamically redistribute dues and sync paid amounts.
        recomputeSchedule($conn, $student_id);
        // Re-fetch updated schedule after recompute
        $refetchRes = $conn->query("SELECT * FROM payment_schedules WHERE student_id=$student_id LIMIT 1");
        if ($refetchRes) {
            $schedule = $refetchRes->fetch_assoc();
        }
        $schedule['downpayment_paid'] = $dpPaid;
    }

    $noticeRes = $conn->query("SELECT exam_period, amount_due, due_date, message, sent_at, is_read
        FROM payment_notices WHERE student_id=$student_id");
    $notices = [];
    while ($row = $noticeRes->fetch_assoc()) $notices[$row['exam_period']] = $row;

    // ── FIX DISPLAY-CARRY-01: Fetch approved permits — frontend uses this to
    // hide the "Pay X · remaining" button and show status as Paid for cleared periods.
    $apRes2 = $conn->query("SELECT exam_period, permit_identifier, approved_at FROM exam_permits WHERE student_id=$student_id AND status='approved' ORDER BY approved_at DESC");
    $approvedPermitsMap = [];
    if ($apRes2) while ($apr2 = $apRes2->fetch_assoc()) $approvedPermitsMap[$apr2['exam_period']] = ['permit_identifier'=>$apr2['permit_identifier'],'approved_at'=>$apr2['approved_at']];
    $schedule['approved_permits'] = $approvedPermitsMap;
    // Override stale notice amount_due for approved periods so balance shows 0
    foreach ($approvedPermitsMap as $apPeriod => $apData) {
        $col = strtolower($apPeriod);
        if (isset($notices[$apPeriod])) {
            $notices[$apPeriod]['amount_due']       = (float)($schedule[$col.'_due'] ?? 0);
            $notices[$apPeriod]['amount_paid']      = (float)($schedule[$col.'_paid'] ?? 0);
            $notices[$apPeriod]['balance']          = 0.0;
            $notices[$apPeriod]['permit_cleared']   = true;
            $notices[$apPeriod]['permit_identifier']= $apData['permit_identifier'];
        }
        $schedule[$col.'_status'] = 'paid';
    }

    // Cast all numeric fields — fetch_assoc returns DECIMAL as strings.
    // Without this, JS arithmetic becomes string concatenation (e.g. 5000+"0.00" = "50000.00").
    foreach (['total_assessment','prelim_due','midterm_due','finals_due',
              'prelim_paid','midterm_paid','finals_paid','downpayment_paid'] as $f) {
        if (isset($schedule[$f])) $schedule[$f] = (float)$schedule[$f];
    }
    // total_paid = authoritative sum from installment_payments (never sum of period fields)
    $schedule['total_paid'] = round($totalPaidAll, 2);

    // ── FIX PERMIT-CARRY-01: Attach carry-over amounts per period to the schedule response ───
    // These columns are added by migrate.php. If they don't exist yet, default to 0.
    // The Angular payment-schedule component uses these to show:
    //   "Includes ₱X.XX carry-over from [previous period]"
    foreach (['prelim_carry_over', 'midterm_carry_over', 'finals_carry_over'] as $coField) {
        $schedule[$coField] = isset($schedule[$coField]) ? (float)$schedule[$coField] : 0.0;
    }
    // Convenience: carry_over_total so the UI can show a single summary badge
    $schedule['carry_over_total'] = round(
        $schedule['prelim_carry_over'] + $schedule['midterm_carry_over'] + $schedule['finals_carry_over'], 2
    );

    // FIX AC-SEMESTER-02: Include student's current semester in schedule response.
    // Angular uses this to display the correct semester label on the CEO/SOA header.
    // After reEnroll(), students.semester is already the new label.
    $semForSchedule = trim($stRow['semester'] ?? '');
    $schedule['semester'] = $semForSchedule;
    $schedule['year_level'] = (function() use ($conn, $student_id) {
        $ylR = $conn->query("SELECT year_level FROM students WHERE id=$student_id LIMIT 1");
        return $ylR ? ($ylR->fetch_assoc()['year_level'] ?? '') : '';
    })();

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success'=>true,'schedule'=>$schedule,'notices'=>$notices]);
}

function getPaymentNotices($conn) {
    $student_id = (int)($_GET['student_id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$student_id) { echo json_encode(['success'=>false]); return; }
    $res = $conn->query("SELECT * FROM payment_notices WHERE student_id=$student_id ORDER BY sent_at DESC");
    $notices = [];
    while ($row = $res->fetch_assoc()) $notices[] = $row;
    $conn->query("UPDATE payment_notices SET is_read=1 WHERE student_id=$student_id");
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success'=>true,'notices'=>$notices]);
}

function sendPaymentNotice($conn, $data) {
    $student_id  = (int)($data['student_id']  ?? 0);
    $exam_period = $data['exam_period'] ?? '';
    $amount_due  = (float)($data['amount_due'] ?? 0);
    $due_date    = $data['due_date']   ?? '';
    $message     = $data['message']    ?? '';
    $sent_by     = (int)($data['accounting_user_id'] ?? 0);

    if (!$student_id || !in_array($exam_period, ['Prelim','Midterm','Finals'])) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success'=>false,'message'=>'Invalid data']); return;
    }

    $p = strtolower($exam_period);
    $due_date_val = $due_date ? "'$due_date'" : 'NULL';

    // Determine payment_plan for this student to set correct unlock status
    $planRes  = $conn->query("SELECT payment_plan, payment_status FROM students WHERE id=$student_id LIMIT 1");
    $planRow  = $planRes ? $planRes->fetch_assoc() : null;
    $isFullPlan   = strtolower($planRow['payment_plan']  ?? 'full') === 'full';
    $isStudentPaid = ($planRow['payment_status'] ?? '') === 'Paid';
    // Full-payment Paid students: unlock directly to 'paid' (no payment needed)
    // Installment students: unlock to 'unpaid' so they can go pay
    $unlockStatus = ($isFullPlan && $isStudentPaid) ? 'paid' : 'unpaid';
    $paymentType  = $isFullPlan ? 'full' : 'installment';

    // Save/update the payment notice AND unlock the period
    $conn->query("INSERT INTO payment_notices (student_id,exam_period,amount_due,due_date,message,sent_by)
        VALUES ($student_id,'$exam_period',$amount_due,$due_date_val,'$message',$sent_by)
        ON DUPLICATE KEY UPDATE amount_due=$amount_due,due_date=$due_date_val,
        message='$message',sent_by=$sent_by,sent_at=NOW(),is_read=0");

    // Unlock the period
    $unlocked_col = $p.'_unlocked_at';
    $conn->query("UPDATE payment_schedules
        SET {$p}_status=IF({$p}_status='locked','$unlockStatus',{$p}_status),
            $unlocked_col=IF($unlocked_col IS NULL,NOW(),$unlocked_col)
        WHERE student_id=$student_id");

    // Create a payment_schedules row if none exists yet
    $check = $conn->query("SELECT id FROM payment_schedules WHERE student_id=$student_id");
    if (!$check || $check->num_rows === 0) {
        $tfRes = $conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id=$student_id LIMIT 1");
        $tfRow = $tfRes ? $tfRes->fetch_assoc() : null;
        $total = $tfRow ? (float)$tfRow['total_assessment'] : $amount_due;
        $dpPaidRes2 = $conn->query("SELECT COALESCE(SUM(amount),0) AS paid FROM installment_payments ip JOIN students _st ON _st.id=ip.student_id WHERE ip.student_id=$student_id AND ip.exam_period='Downpayment' AND ip.semester=_st.semester");
        $dpPaid2    = $dpPaidRes2 ? (float)$dpPaidRes2->fetch_assoc()['paid'] : 0;
        $dpCredit2  = $dpPaid2 > 0 ? $dpPaid2 : round($total/4, 2);
        $rem2       = max(0, $total - $dpCredit2);
        $pd = ceil($rem2/3*100)/100; $md = $pd; $fd = round($rem2-$pd*2,2);
        $conn->query("INSERT INTO payment_schedules
            (student_id,payment_type,total_assessment,prelim_due,midterm_due,finals_due,{$p}_status,{$unlocked_col})
            VALUES ($student_id,'$paymentType',$total,$pd,$md,$fd,'$unlockStatus',NOW())");
    }

    logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'SEND_NOTICE', 'student', $student_id,
        "Sent $exam_period notice to student ID $student_id (₱" . number_format($amount_due,2) . ")");
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success'=>true,'message'=>"$exam_period notice sent. Payment period is now unlocked for the student."]);
}


// ─────────────────────────────────────────────────────────────
// BULK NOTICE — send payment notice to all matching students
// ─────────────────────────────────────────────────────────────────────────────
// GET ?action=get_course_groups
// Returns distinct student groupings (category · program · year_level · strand · semester)
// with per-group counts for the course-card UI.
// Optional GET params: semester, category
// ─────────────────────────────────────────────────────────────────────────────
function getCourseGroups(mysqli $conn): void {
    $semFilter = trim($_GET['semester'] ?? '');
    $catFilter = strtoupper(trim($_GET['category'] ?? ''));

    // FIX COURSE-GROUP-01: Detect the current active semester from sys_config
    // so only students enrolled in the ACTIVE semester are shown.
    $currentSem = '';
    $epRes = $conn->query("SELECT config_value FROM sys_config WHERE config_key = 'enrollment_period' LIMIT 1");
    if ($epRes) {
        $epRow = $epRes->fetch_assoc();
        $ep = json_decode($epRow['config_value'] ?? '{}', true);
        if (!empty($ep['label'])) {
            $currentSem = trim($ep['label']);
        }
    }

    // FIX COURSE-GROUP-02: Always scope to the current enrollment semester.
    // If the caller passes an explicit ?semester= filter, use that.
    // Otherwise default to $currentSem (the active enrollment period).
    // This prevents students from a previous semester (who haven't re-enrolled yet)
    // from appearing in the Send Notice / Permit Generation cards.
    $activeSem = $semFilter !== '' ? $semFilter : $currentSem;
    // FIX COURSE-GROUP-03: Use flexible semester matching (same as FILTER-09).
    // The sys_config label (e.g. "1st Semester, AY 2024-2025") often doesn't exactly
    // match students.semester (e.g. "1st Semester"). Match both directions with LIKE.
    if ($activeSem !== '') {
        $escFull  = $conn->real_escape_string($activeSem);
        $semPart  = $conn->real_escape_string(trim(preg_replace('/[,\s]*AY[\s\d\-]+$/i', '', $activeSem)));
        $semWhere = "AND (
            s.semester = '$escFull'
            OR s.semester LIKE '$semPart%'
            OR '$escFull' LIKE CONCAT(s.semester, '%')
        )";
    } else {
        $semWhere = '';
    }
    $catWhere = ($catFilter && $catFilter !== 'ALL')
        ? "AND UPPER(COALESCE(s.student_category,'College')) = '" . $conn->real_escape_string($catFilter) . "'"
        : '';

    $sql = "
        SELECT
            COALESCE(s.student_category, 'College')          AS category,
            COALESCE(s.program, '—')                         AS program,
            COALESCE(s.year_level, '—')                      AS year_level,
            COALESCE(s.strand, '')                           AS strand,
            COALESCE(s.semester, '')                         AS semester,
            COUNT(*)                                         AS student_count,
            SUM(CASE WHEN s.payment_status IN ('Pending','Partial','Overdue') THEN 1 ELSE 0 END)
                                                             AS pending_count,
            SUM(CASE WHEN s.payment_status = 'Paid'  THEN 1 ELSE 0 END) AS paid_count,
            SUM(CASE WHEN s.payment_plan   = 'installment'  THEN 1 ELSE 0 END) AS installment_count,
            SUM(CASE WHEN COALESCE(s.payment_plan,'full') = 'full' THEN 1 ELSE 0 END) AS full_count
        FROM students s
        WHERE s.approval_status   = 'Approved'
          AND s.enrollment_status IN ('Enrolled','Confirmed')
          AND (s.archived_at IS NULL OR s.archived_at = '')
          $semWhere
          $catWhere
        GROUP BY
            COALESCE(s.student_category,'College'),
            COALESCE(s.program,'—'),
            COALESCE(s.year_level,'—'),
            COALESCE(s.strand,''),
            COALESCE(s.semester,'')
        ORDER BY category ASC, program ASC, year_level ASC
    ";

    $res = $conn->query($sql);
    $groups = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['student_count']     = (int)$row['student_count'];
            $row['pending_count']     = (int)$row['pending_count'];
            $row['paid_count']        = (int)$row['paid_count'];
            $row['installment_count'] = (int)$row['installment_count'];
            $row['full_count']        = (int)$row['full_count'];
            $groups[] = $row;
        }
    }

    while (ob_get_level() > 0) ob_end_clean();
    echo json_encode([
        'success'         => true,
        'groups'          => $groups,
        // FIX COURSE-GROUP-01: Expose the active enrollment semester so Angular
        // can auto-select the correct semester card and warn about stale groups.
        'currentSemester' => $currentSem,
    ]);
}


// ─────────────────────────────────────────────────────────────────────────────
// GET ?action=get_permit_course_groups
// Same groupings as get_course_groups BUT the count shown is the number of
// exam permits (filtered by status/exam_period) — not the student headcount.
// Used by the Approved/Pending/Rejected tabs in the Permit Generation component
// so the Quick Filter badges reflect permit counts, not student counts.
//
// Optional GET params: semester, category, status (approved|pending|rejected|all),
//                      exam_period
// ─────────────────────────────────────────────────────────────────────────────
function getPermitCourseGroups(mysqli $conn): void {
    $semFilter    = trim($_GET['semester']    ?? '');
    $catFilter    = strtoupper(trim($_GET['category']   ?? ''));
    $rawStatus    = $_GET['status']      ?? 'approved';
    $examPeriod   = trim($_GET['exam_period'] ?? '');
    $status       = in_array($rawStatus, ['pending','approved','rejected','all'], true)
                    ? $rawStatus : 'approved';

    // FIX PERMIT-COURSE-GROUP-01: Use flexible semester matching — same issue as
    // FILTER-09 / COURSE-GROUP-03. The stored students.semester often omits the
    // "AY YYYY-YYYY" suffix that the sys_config label includes, causing zero results.
    if ($semFilter !== '') {
        $escFull = $conn->real_escape_string($semFilter);
        $semPart = $conn->real_escape_string(trim(preg_replace('/[,\s]*AY[\s\d\-]+$/i', '', $semFilter)));
        $semWhere = "AND (
            s.semester = '$escFull'
            OR s.semester LIKE '$semPart%'
            OR '$escFull' LIKE CONCAT(s.semester, '%')
        )";
    } else {
        $semWhere = '';
    }
    $catWhere   = ($catFilter && $catFilter !== 'ALL')
        ? "AND UPPER(COALESCE(s.student_category,'College')) = '" . $conn->real_escape_string($catFilter) . "'"
        : '';
    $statusWhere = ($status !== 'all')
        ? "AND ep.status = '" . $conn->real_escape_string($status) . "'"
        : '';
    $periodWhere = ($examPeriod !== '')
        ? "AND ep.exam_period = '" . $conn->real_escape_string($examPeriod) . "'"
        : '';

    $sql = "
        SELECT
            COALESCE(s.student_category, 'College')  AS category,
            COALESCE(s.program, '—')                 AS program,
            COALESCE(s.year_level, '—')              AS year_level,
            COALESCE(s.strand, '')                   AS strand,
            COALESCE(s.semester, '')                 AS semester,
            COUNT(DISTINCT ep.student_id)            AS student_count,
            COUNT(ep.id)                             AS permit_count
        FROM exam_permits ep
        JOIN students s ON ep.student_id = s.id
        WHERE 1=1
          $statusWhere
          $periodWhere
          $semWhere
          $catWhere
          AND (s.archived_at IS NULL OR s.archived_at = '')
        GROUP BY
            COALESCE(s.student_category,'College'),
            COALESCE(s.program,'—'),
            COALESCE(s.year_level,'—'),
            COALESCE(s.strand,''),
            COALESCE(s.semester,'')
        ORDER BY category ASC, program ASC, year_level ASC
    ";

    $res    = $conn->query($sql);
    $groups = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['student_count'] = (int)$row['student_count'];
            $row['permit_count']  = (int)$row['permit_count'];
            $groups[] = $row;
        }
    }

    while (ob_get_level() > 0) ob_end_clean();
    echo json_encode(['success' => true, 'groups' => $groups]);
}


// ─────────────────────────────────────────────────────────────────────────────
// GET ?action=preview_bulk_notice
// Returns filtered student list with balance data — does NOT send notices.
// Accounting reviews this before confirming send.
//
// Optional GET params:
//   exam_period, semester, category, program, year_level, strand, payment_plan
// ─────────────────────────────────────────────────────────────────────────────
function previewBulkNotice(mysqli $conn): void {
    $exam_period = trim($_GET['exam_period'] ?? '');
    $semester    = trim($_GET['semester']    ?? '');
    $category    = strtoupper(trim($_GET['category']    ?? ''));
    $program     = trim($_GET['program']     ?? '');
    $year_level  = trim($_GET['year_level']  ?? '');
    $strand      = trim($_GET['strand']      ?? '');
    $pay_plan    = strtolower(trim($_GET['payment_plan'] ?? ''));

    $semWhere    = $semester    ? "AND s.semester    = '" . $conn->real_escape_string($semester)    . "'" : '';
    $catWhere    = ($category && $category !== 'ALL')
                                ? "AND UPPER(COALESCE(s.student_category,'College')) = '" . $conn->real_escape_string($category) . "'" : '';
    $progWhere   = $program     ? "AND s.program     = '" . $conn->real_escape_string($program)     . "'" : '';
    $ylWhere     = $year_level  ? "AND s.year_level  = '" . $conn->real_escape_string($year_level)  . "'" : '';
    $strandWhere = $strand      ? "AND s.strand      = '" . $conn->real_escape_string($strand)      . "'" : '';
    $planWhere   = ($pay_plan && $pay_plan !== 'all')
                                ? "AND s.payment_plan = '" . $conn->real_escape_string($pay_plan)   . "'" : '';

    $p           = strtolower(in_array($exam_period, ['Prelim','Midterm','Finals'], true) ? $exam_period : 'prelim');
    $epEsc       = $conn->real_escape_string($exam_period);

    $sql = "
        SELECT
            s.id, s.student_number, s.first_name, s.last_name,
            s.program, s.year_level, s.strand, s.semester,
            s.student_category, s.payment_plan, s.payment_status,
            COALESCE(tf.total_assessment, 0)                              AS total_assessment,
            COALESCE(ip_sum.total_paid, 0)                                AS total_paid,
            COALESCE(ps.{$p}_due,
                ROUND(COALESCE(tf.total_assessment,0)/4, 2))              AS period_due,
            COALESCE(ps.{$p}_paid, 0)                                     AS period_paid,
            (pn.id IS NOT NULL)                                           AS notice_sent,
            pn.amount_due                                                 AS notice_amount,
            pn.sent_at                                                    AS notice_sent_at
        FROM students s
        LEFT JOIN tuition_fees tf          ON tf.student_id  = s.id
        LEFT JOIN payment_schedules ps     ON ps.student_id  = s.id
        LEFT JOIN (
            SELECT student_id, SUM(amount) AS total_paid
            FROM installment_payments GROUP BY student_id
        ) ip_sum                           ON ip_sum.student_id = s.id
        LEFT JOIN payment_notices pn       ON pn.student_id  = s.id
                                          AND pn.exam_period = '$epEsc'
        WHERE s.approval_status   = 'Approved'
          AND s.enrollment_status IN ('Enrolled','Confirmed')
          AND (s.archived_at IS NULL OR s.archived_at = '')
          $semWhere $catWhere $progWhere $ylWhere $strandWhere $planWhere
        ORDER BY s.last_name ASC, s.first_name ASC
    ";

    $res = $conn->query($sql);
    $students = [];

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $totalAssess = (float)$row['total_assessment'];
            $totalPaid   = (float)$row['total_paid'];
            $periodDue   = (float)$row['period_due'];
            $periodPaid  = (float)$row['period_paid'];
            $balanceDue  = $exam_period
                ? max(0, $periodDue - $periodPaid)
                : max(0, $totalAssess - $totalPaid);

            $students[] = [
                'id'              => (int)$row['id'],
                'student_number'  => $row['student_number'],
                'first_name'      => $row['first_name'],
                'last_name'       => $row['last_name'],
                'program'         => $row['program'],
                'year_level'      => $row['year_level'],
                'strand'          => $row['strand'] ?: null,
                'semester'        => $row['semester'],
                'student_category'=> $row['student_category'],
                'payment_plan'    => $row['payment_plan'],
                'payment_status'  => $row['payment_status'],
                'total_assessment'=> round($totalAssess, 2),
                'total_paid'      => round($totalPaid,   2),
                'balance_due'     => round($balanceDue,  2),
                'notice_sent'     => (bool)$row['notice_sent'],
                'notice_amount'   => $row['notice_amount'] !== null ? (float)$row['notice_amount'] : null,
                'notice_sent_at'  => $row['notice_sent_at'],
            ];
        }
    }

    while (ob_get_level() > 0) ob_end_clean();
    echo json_encode([
        'success'  => true,
        'total'    => count($students),
        'students' => $students,
    ]);
}


// POST ?action=send_bulk_notice
// Body: { exam_period, student_ids (from preview), due_date, message_template,
//         accounting_user_id }
// Fallback filters (used only when student_ids is empty):
//   category, program, year_level, strand, semester, payment_plan
// ─────────────────────────────────────────────────────────────
function sendBulkNotice($conn, $data) {
    $exam_period  = trim($data['exam_period']         ?? '');
    $acc_user_id  = (int)($data['accounting_user_id'] ?? 0);
    $due_date     = trim($data['due_date']            ?? '');
    $msg_template = trim($data['message_template']    ?? '');
    $student_ids  = array_map('intval', (array)($data['student_ids'] ?? []));

    if (!in_array($exam_period, ['Prelim','Midterm','Finals'], true)) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success'=>false,'message'=>'Invalid exam_period.']); return;
    }

    $p            = strtolower($exam_period);
    $due_val      = $due_date ? "'" . $conn->real_escape_string($due_date) . "'" : 'NULL';
    $unlocked_col = $p . '_unlocked_at';

    // When student_ids is provided (from preview selection), use those directly.
    // Otherwise fall back to filter params (backwards-compatible with old callers).
    if (!empty($student_ids)) {
        $id_list = implode(',', $student_ids);
        $where   = "WHERE s.id IN ($id_list)
                      AND s.approval_status   = 'Approved'
                      AND s.enrollment_status IN ('Enrolled','Confirmed')";
    } else {
        $category    = strtoupper(trim($data['category']    ?? 'ALL'));
        $program     = trim($data['program']     ?? '');
        $year_level  = trim($data['year_level']  ?? '');
        $strand      = trim($data['strand']      ?? '');
        $semester    = trim($data['semester']    ?? '');
        $pay_plan    = strtolower(trim($data['payment_plan'] ?? ''));

        $catWhere    = ($category && $category !== 'ALL')
            ? "AND UPPER(COALESCE(s.student_category,'College')) = '" . $conn->real_escape_string($category)   . "'" : '';
        $progWhere   = $program    ? "AND s.program    = '" . $conn->real_escape_string($program)    . "'" : '';
        $ylWhere     = $year_level ? "AND s.year_level = '" . $conn->real_escape_string($year_level) . "'" : '';
        $strandWhere = $strand     ? "AND s.strand     = '" . $conn->real_escape_string($strand)     . "'" : '';
        $semWhere    = $semester   ? "AND s.semester   = '" . $conn->real_escape_string($semester)   . "'" : '';
        $planWhere   = ($pay_plan && $pay_plan !== 'all')
            ? "AND s.payment_plan = '" . $conn->real_escape_string($pay_plan) . "'" : "AND s.payment_plan = 'installment'";

        $where = "WHERE s.approval_status   = 'Approved'
                    AND s.enrollment_status IN ('Enrolled','Confirmed')
                    AND (s.archived_at IS NULL OR s.archived_at = '')
                    $catWhere $progWhere $ylWhere $strandWhere $semWhere $planWhere";
    }

    $res = $conn->query("
        SELECT s.id, s.first_name, s.last_name,
               s.payment_plan, s.payment_status, s.student_number, s.semester AS stu_semester,
               COALESCE(ps.{$p}_due, ROUND(COALESCE(tf.total_assessment,0)/4,2)) AS period_due,
               COALESCE(ps.{$p}_paid, 0) AS period_paid
        FROM students s
        LEFT JOIN tuition_fees      tf ON tf.student_id = s.id
        LEFT JOIN payment_schedules ps ON ps.student_id = s.id
        $where
        ORDER BY s.last_name ASC
    ");

    $sent = 0; $skipped = 0; $autoApproved = 0;

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $sid           = (int)$row['id'];
            $fname         = $row['first_name'];
            $isFullPlan    = strtolower($row['payment_plan'] ?? 'full') === 'full';
            $isStudentPaid = ($row['payment_status'] ?? '') === 'Paid';
            $periodDue     = (float)$row['period_due'];
            $periodPaid    = (float)$row['period_paid'];
            $amount        = max(0, round($periodDue - $periodPaid, 2));

            if ($amount <= 0 && !$isFullPlan) { $skipped++; continue; }

            $unlockStatus = ($isFullPlan && $isStudentPaid) ? 'paid' : 'unpaid';
            $paymentType  = $isFullPlan ? 'full' : 'installment';

            $message = $msg_template
                ? str_replace(
                    ['{name}', '{period}', '{amount}'],
                    [$fname, $exam_period, '₱' . number_format($amount, 2)],
                    $msg_template
                  )
                : "Dear $fname, your $exam_period payment of ₱" . number_format($amount, 2) . " is now due. Please settle at the Accounting office.";
            $msg_esc = $conn->real_escape_string($message);

            $conn->query("INSERT INTO payment_notices
                (student_id, exam_period, amount_due, due_date, message, sent_by)
                VALUES ($sid, '$exam_period', $amount, $due_val, '$msg_esc', $acc_user_id)
                ON DUPLICATE KEY UPDATE
                    amount_due = $amount, due_date = $due_val,
                    message = '$msg_esc', sent_by = $acc_user_id,
                    sent_at = NOW(), is_read = 0");

            $conn->query("UPDATE payment_schedules
                SET {$p}_status   = IF({$p}_status='locked','$unlockStatus',{$p}_status),
                    $unlocked_col = IF($unlocked_col IS NULL, NOW(), $unlocked_col)
                WHERE student_id = $sid");

            $chk = $conn->query("SELECT id FROM payment_schedules WHERE student_id=$sid");
            if (!$chk || $chk->num_rows === 0) {
                $tfRes = $conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id=$sid LIMIT 1");
                $total = $tfRes ? (float)($tfRes->fetch_assoc()['total_assessment'] ?? 0) : $amount * 4;
                $dpR   = $conn->query("SELECT COALESCE(SUM(amount),0) AS paid
                    FROM installment_payments ip JOIN students _st ON _st.id=ip.student_id
                    WHERE ip.student_id=$sid AND ip.exam_period='Downpayment' AND ip.semester=_st.semester");
                $dpPd  = $dpR ? (float)($dpR->fetch_assoc()['paid'] ?? 0) : 0;
                $dpCr  = $dpPd > 0 ? $dpPd : round($total / 4, 2);
                $rem   = max(0, $total - $dpCr);
                $pd = ceil($rem / 3 * 100) / 100; $md = $pd; $fd = round($rem - $pd * 2, 2);
                $conn->query("INSERT INTO payment_schedules
                    (student_id,payment_type,total_assessment,prelim_due,midterm_due,finals_due,{$p}_status,$unlocked_col)
                    VALUES ($sid,'$paymentType',$total,$pd,$md,$fd,'$unlockStatus',NOW())");
            }

            if ($isFullPlan && $isStudentPaid) {
                $stuNum = preg_replace('/[^A-Z0-9\-]/i', '', $row['student_number']);
                $sem    = $conn->real_escape_string($row['stu_semester']);
                $ay     = '';
                if (preg_match('/AY\s*([\d]{4}-[\d]{4})/i', $row['stu_semester'], $m)) $ay = $m[1];
                $pc     = strtoupper(substr($exam_period, 0, 2));
                $permit = $conn->real_escape_string('EP-' . date('Ymd') . '-' . $stuNum . '-' . $pc);
                $conn->query("INSERT INTO exam_permits
                    (student_id, exam_period, school_year, semester, status, permit_identifier, approved_at)
                    VALUES ($sid, '$exam_period', '$ay', '$sem', 'approved', '$permit', NOW())
                    ON DUPLICATE KEY UPDATE
                        status = 'approved', permit_identifier = '$permit', approved_at = NOW()");
                $autoApproved++;
            }

            $sent++;
        }
    }

    logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'BULK_SEND_NOTICE', 'notice', 0,
        "Bulk $exam_period notice: $sent sent, $skipped skipped, $autoApproved auto-approved.");
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'       => true,
        'sent'          => $sent,
        'skipped'       => $skipped,
        'auto_approved' => $autoApproved,
        'message'       => "Notice sent to $sent student(s)."
                         . ($skipped      ? " $skipped already paid/skipped."      : '')
                         . ($autoApproved ? " $autoApproved permit(s) auto-approved." : ''),
    ]);
}



// ─────────────────────────────────────────────────────────────────────────────
// RECALC ALL PAYMENT SCHEDULES
// GET ?action=recalc_payment_schedules
// Corrects any stale carry-over data in payment_schedules.
// For every installment student: sets period_paid = actual SUM(installment_payments)
// and period_status based on that, for unlocked periods only.
// ─────────────────────────────────────────────────────────────────────────────
function recalcAllPaymentSchedules($conn) {
    // Get all installment students who have a payment_schedules row
    $res = $conn->query("
        SELECT s.id AS student_id
        FROM students s
        INNER JOIN payment_schedules ps ON ps.student_id = s.id
        WHERE ps.payment_type = 'installment'
    ");

    $fixed = 0;
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            recomputeSchedule($conn, (int)$row['student_id']);
            $fixed++;
        }
    }
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success'=>true,'message'=>"Recalculated $fixed student payment schedules.",'fixed'=>$fixed]);
}

function getExamPermits($conn) {
    $rawStatus  = $_GET['status'] ?? 'pending';
    $status     = in_array($rawStatus, ['pending','approved','rejected','all'], true) ? $rawStatus : 'pending';
    // FIX PERMIT-01: Optional filters for semester scoping and program — prevents
    // mixed-semester permits from appearing in the Pending/Approved tabs.
    $filterSemester = trim($_GET['semester']   ?? '');
    $filterProgram  = trim($_GET['program']    ?? '');
    $filterCategory = trim($_GET['category']   ?? '');

    // FIX PERMIT-02: status='all' was producing "WHERE ep.status='all'" which is an
    // invalid ENUM value — returns zero rows for the All Permits tab. Fix: omit the
    // WHERE clause when status is 'all'.
    $statusWhere = ($status !== 'all') ? "AND ep.status = '$status'" : '';
    $semWhere    = ($filterSemester !== '')
        ? "AND (s.semester = '" . $conn->real_escape_string($filterSemester) . "' OR ep.semester LIKE '%" . $conn->real_escape_string($filterSemester) . "%')"
        : '';
    $progWhere   = ($filterProgram !== '')
        ? "AND s.program = '" . $conn->real_escape_string($filterProgram) . "'"
        : '';
    $catWhere    = ($filterCategory !== '')
        ? "AND UPPER(COALESCE(s.student_category,'College')) = UPPER('" . $conn->real_escape_string($filterCategory) . "')"
        : '';

    $statusStmt = $conn->prepare("
        SELECT ep.*, s.student_number, s.first_name, s.last_name,
               s.program, s.year_level, s.semester AS student_raw_sem,
               COALESCE(sp.first_name, f2.first_name) AS approved_by_first, COALESCE(sp.last_name, f2.last_name) AS approved_by_last
        FROM exam_permits ep
        JOIN students s ON ep.student_id=s.id
        LEFT JOIN users u ON ep.approved_by=u.id
        LEFT JOIN staff_profiles sp ON sp.user_id = u.id
        LEFT JOIN faculty f2 ON f2.user_id = u.id
        WHERE 1=1 $statusWhere $semWhere $progWhere $catWhere
        ORDER BY ep.requested_at DESC");
    if (!$statusStmt) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success'=>false,'message'=>'Query prepare failed: '.$conn->error]);
        return;
    }
    $statusStmt->execute();
    $res = $statusStmt->get_result();
    $permits = [];
    while ($row = $res->fetch_assoc()) {
        // Parse semester + school_year from stored permit values (set correctly at request time)
        // But if they look like old hardcoded values, fix them using the student's raw semester
        $raw = trim($row['student_raw_sem'] ?? '');
        $sem = trim($row['semester'] ?? '');
        $ay  = trim($row['school_year'] ?? '');

        // If permit semester looks like a full combined string, re-parse it
        if (preg_match('/^(.+?),\s*AY\s*([\d]{4}-[\d]{4})/i', $sem, $m)) {
            $sem = trim($m[1]);
            $ay  = trim($m[2]);
        } elseif (preg_match('/^(.+?),\s*AY\s*([\d]{4}-[\d]{4})/i', $raw, $m)) {
            // Fall back to student's raw semester
            $sem = trim($m[1]);
            $ay  = trim($m[2]);
        } elseif (preg_match('/([\d]{4}-[\d]{4})/', $raw, $m)) {
            $ay  = $m[1];
            $sem = trim(preg_replace('/,?\s*AY\s*[\d]{4}-[\d]{4}/i', '', $raw));
        }

        $row['semester']    = $sem;
        $row['school_year'] = $ay;
        unset($row['student_raw_sem']);
        $permits[] = $row;
    }
    $statusStmt->close();
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success'=>true,'permits'=>$permits]);
}

function getStudentPermitStatus($conn) {
    $student_id = (int)($_GET['student_id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$student_id) { echo json_encode(['success'=>false]); return; }
    $res = $conn->query("SELECT * FROM exam_permits WHERE student_id=$student_id ORDER BY requested_at DESC");
    $permits = [];
    while ($row = $res->fetch_assoc()) $permits[] = $row;
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success'=>true,'permits'=>$permits]);
}

function requestExamPermit($conn, $data) {
    $student_id  = (int)($data['student_id']  ?? 0);
    $exam_period = $data['exam_period'] ?? '';

    // BUG-PERMIT-01 FIX: Accept 'Full' as a valid exam_period.
    // Full-payment students send 'Full' from the frontend; the old whitelist
    // ['Prelim','Midterm','Finals'] rejected it, so they could never request a permit.
    if (!$student_id || !in_array($exam_period, ['Prelim','Midterm','Finals','Full'])) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success'=>false,'message'=>'Invalid data']); return;
    }

    // ── Resolve accurate semester + school_year from the student's own record ──
    $stRes = $conn->query("SELECT semester FROM students WHERE id = $student_id LIMIT 1");
    $stRow = $stRes ? $stRes->fetch_assoc() : null;
    $rawSemester = trim($stRow['semester'] ?? '');

    $semester    = $rawSemester;
    $school_year = date('Y') . '-' . (date('Y') + 1);

    if (preg_match('/^(.+?),\s*AY\s*([\d]{4}-[\d]{4})/i', $rawSemester, $m)) {
        $semester    = trim($m[1]);
        $school_year = trim($m[2]);
    } elseif (preg_match('/([\d]{4}-[\d]{4})/', $rawSemester, $m)) {
        $school_year = $m[1];
        $semester    = trim(preg_replace('/,?\s*AY\s*[\d]{4}-[\d]{4}/i', '', $rawSemester));
    }

    if (!$semester)    $semester    = $data['semester']    ?? '2nd Semester';
    if (!$school_year) $school_year = $data['school_year'] ?? date('Y'.'-'.(date('Y')+1));

    $stPlanRes = $conn->query("SELECT payment_status, payment_plan FROM students WHERE id=$student_id LIMIT 1");
    $stPlanRow = $stPlanRes ? $stPlanRes->fetch_assoc() : null;
    $isFullPlan  = $stPlanRow && strtolower($stPlanRow['payment_plan'] ?? '') === 'full';
    $studentPaid = $stPlanRow && $stPlanRow['payment_status'] === 'Paid';

    // ── 'Full' exam_period — legacy frontend value for full-payment students ──
    // Kept for backwards compatibility: redirect it to the per-period flow below
    // by resolving which specific period(s) still need a permit.
    // Rule: each period is requestable independently as soon as Accounting sends
    // a notice for that period (unlocks it). No need to wait for ALL periods to
    // be notified. Full-plan students do NOT need payment_status='Paid' to request
    // a permit — the notice itself is the unlock signal.
    if ($exam_period === 'Full') {
        if (!$isFullPlan) {
            while (ob_get_level() > 0) { ob_end_clean(); }
            echo json_encode(['success'=>false,'message'=>'Full period permits are only for full-payment students.']); return;
        }

        // Check which periods have a notice sent and are not yet approved/pending
        $stuRes2    = $conn->query("SELECT student_number FROM students WHERE id=$student_id LIMIT 1");
        $stuNum2    = $stuRes2 ? ($stuRes2->fetch_assoc()['student_number'] ?? 'STU') : 'STU';
        $stuNumClean2 = preg_replace('/[^A-Z0-9]/', '', strtoupper($stuNum2));

        $insertedPeriods = [];
        $skippedPeriods  = [];

        foreach (['Prelim','Midterm','Finals'] as $p3) {
            $p3lower = strtolower($p3);

            // Is there a payment_notice for this period?
            $noticeChk = $conn->query("SELECT id FROM payment_notices WHERE student_id=$student_id AND exam_period='$p3' LIMIT 1");
            $hasNotice = $noticeChk && $noticeChk->num_rows > 0;

            // Is the period unlocked in payment_schedules?
            $schChk = $conn->query("SELECT {$p3lower}_status FROM payment_schedules WHERE student_id=$student_id LIMIT 1");
            $schRow3 = $schChk ? $schChk->fetch_assoc() : null;
            $periodUnlocked = $schRow3 && ($schRow3[$p3lower.'_status'] ?? 'locked') !== 'locked';

            if (!$hasNotice && !$periodUnlocked) {
                $skippedPeriods[] = $p3; // not yet unlocked by Accounting
                continue;
            }

            // Is a permit already pending or approved for this period?
            $existsChk = $conn->query("SELECT status FROM exam_permits WHERE student_id=$student_id AND exam_period='$p3' AND school_year='$school_year' LIMIT 1");
            $existsRow = $existsChk ? $existsChk->fetch_assoc() : null;
            if ($existsRow && in_array($existsRow['status'], ['pending','approved'])) {
                $skippedPeriods[] = $p3; // already submitted
                continue;
            }

            $code3   = strtoupper(substr($p3, 0, 3));
            $permId3 = 'EP-' . date('Ymd') . '-' . $stuNumClean2 . '-' . $code3;

            // Full-plan + notice sent = auto-approve (no separate Accounting step needed)
            $st3 = $conn->prepare("INSERT INTO exam_permits
                (student_id,exam_period,school_year,semester,status,permit_identifier,approved_at)
                VALUES (?,?,?,?,'approved',?,NOW())
                ON DUPLICATE KEY UPDATE
                    status=IF(status='rejected','approved',status),
                    requested_at=NOW(),
                    permit_identifier=VALUES(permit_identifier),
                    approved_at=IF(approved_at IS NULL,NOW(),approved_at)");
            $st3->bind_param("issss", $student_id, $p3, $school_year, $semester, $permId3);
            $st3->execute(); $st3->close();
            $insertedPeriods[] = $p3;
        }

        while (ob_get_level() > 0) { ob_end_clean(); }

        if (empty($insertedPeriods)) {
            if (!empty($skippedPeriods)) {
                // FIX PERMIT-03: Check WHY periods were skipped before returning success.
                // If ALL skipped periods already have approved/pending permits → genuine success.
                // If some were skipped because they had no notice AND no unlock → still locked.
                $alreadyHavePermit = [];
                $stillLocked       = [];
                foreach ($skippedPeriods as $sp) {
                    $spLower   = strtolower($sp);
                    $noticeChkSp = $conn->query("SELECT id FROM payment_notices WHERE student_id=$student_id AND exam_period='$sp' LIMIT 1");
                    $hasNoticeSp = $noticeChkSp && $noticeChkSp->num_rows > 0;
                    $schChkSp    = $conn->query("SELECT {$spLower}_status FROM payment_schedules WHERE student_id=$student_id LIMIT 1");
                    $schRowSp    = $schChkSp ? $schChkSp->fetch_assoc() : null;
                    $unlockedSp  = $schRowSp && ($schRowSp[$spLower.'_status'] ?? 'locked') !== 'locked';
                    $existsChkSp = $conn->query("SELECT status FROM exam_permits WHERE student_id=$student_id AND exam_period='$sp' AND school_year='$school_year' LIMIT 1");
                    $existsRowSp = $existsChkSp ? $existsChkSp->fetch_assoc() : null;
                    if ($existsRowSp && in_array($existsRowSp['status'], ['pending','approved'])) {
                        $alreadyHavePermit[] = $sp;
                    } elseif (!$hasNoticeSp && !$unlockedSp) {
                        $stillLocked[] = $sp;
                    } else {
                        $alreadyHavePermit[] = $sp;
                    }
                }
                if (!empty($stillLocked) && empty($alreadyHavePermit)) {
                    // Nothing was approved AND nothing was previously issued — truly locked
                    echo json_encode(['success'=>false,'locked'=>true,'message'=>'Exam permits are not yet available. Please wait for a notice from Accounting.']);
                } else {
                    // At least some permits already exist — tell student to view them
                    echo json_encode(['success'=>true,'message'=>'Your exam permits are already submitted or approved. Please check your permits page.','periods'=>$alreadyHavePermit,'auto_approved'=>true]);
                }
            } else {
                echo json_encode(['success'=>false,'locked'=>true,'message'=>'Exam permits are not yet available. Please wait for a notice from Accounting.']);
            }
            return;
        }

        $periodList = implode(', ', $insertedPeriods);
        logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'REQUEST_PERMIT', 'student', $student_id,
            "Auto-approved $periodList permit(s) for student ID $student_id (full plan, notice received)");
        echo json_encode([
            'success'      => true,
            'message'      => 'Exam permit(s) approved for: ' . $periodList . '. You can now view and print your permit(s).',
            'periods'      => $insertedPeriods,
            'auto_approved'=> true,
        ]);
        return;
    }

    // ── Single-period logic: Prelim / Midterm / Finals ────────────────────────
    //
    // RULE (per school policy):
    //   • Accounting sends a payment notice for the period  →  period unlocks
    //   • Full-plan students: notice sent = permit requestable immediately
    //     (they already paid in full; notice is just the formal unlock signal)
    //   • Installment students: notice sent + period payment recorded = permit requestable
    //
    $p   = strtolower($exam_period);
    $schRes = $conn->query("SELECT {$p}_status FROM payment_schedules WHERE student_id=$student_id LIMIT 1");
    $schRow = $schRes ? $schRes->fetch_assoc() : null;
    $periodStatus = $schRow ? ($schRow[$p.'_status'] ?? 'locked') : 'locked';

    // ── Gate 1: Period must be unlocked (Accounting sent a notice) ────────────
    if ($periodStatus === 'locked') {
        // Double-check via payment_notices table (in case payment_schedules row is missing)
        $noticeChk2 = $conn->query("SELECT id FROM payment_notices WHERE student_id=$student_id AND exam_period='$exam_period' LIMIT 1");
        if (!$noticeChk2 || $noticeChk2->num_rows === 0) {
            while (ob_get_level() > 0) { ob_end_clean(); }
            echo json_encode(['success'=>false,'locked'=>true,'message'=>"$exam_period permit is not yet available. Please wait for a payment notice from Accounting."]);
            return;
        }
        // Notice exists but payment_schedules not yet updated — fall through (treat as unlocked)
    }

    if ($isFullPlan) {
        // ── Full-plan: notice sent is sufficient — no payment_status check needed ──
        // The notice IS the unlock. Accounting explicitly chose to send it, meaning
        // they have confirmed the student may take the exam.
        $noticeRes = $conn->query("SELECT id FROM payment_notices WHERE student_id=$student_id AND exam_period='$exam_period' LIMIT 1");
        $hasNotice = $noticeRes && $noticeRes->num_rows > 0;
        if (!$hasNotice && $periodStatus === 'locked') {
            while (ob_get_level() > 0) { ob_end_clean(); }
            echo json_encode(['success'=>false,'locked'=>true,'message'=>"$exam_period permit is not yet available. Please wait for a payment notice from Accounting."]); return;
        }
        // Auto-approve for full-plan students (no extra Accounting step needed)
        $stuRes = $conn->query("SELECT student_number FROM students WHERE id=$student_id LIMIT 1");
        $stuNum = $stuRes ? ($stuRes->fetch_assoc()['student_number'] ?? 'STU') : 'STU';
        $stuNumClean  = preg_replace('/[^A-Z0-9]/', '', strtoupper($stuNum));
        $periodCode   = strtoupper(substr($exam_period, 0, 3));
        $permitIdentifier = 'EP-' . date('Ymd') . '-' . $stuNumClean . '-' . $periodCode;
        $stmt = $conn->prepare("INSERT INTO exam_permits
            (student_id,exam_period,school_year,semester,status,permit_identifier,approved_at)
            VALUES (?,?,?,?,'approved',?,NOW())
            ON DUPLICATE KEY UPDATE
                status=IF(status='rejected','approved',status),
                requested_at=NOW(),
                permit_identifier=VALUES(permit_identifier),
                approved_at=IF(approved_at IS NULL,NOW(),approved_at)");
        $stmt->bind_param("issss", $student_id, $exam_period, $school_year, $semester, $permitIdentifier);
        $stmt->execute(); $stmt->close();
        logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'REQUEST_PERMIT', 'student', $student_id,
            "Auto-approved $exam_period permit for student ID $student_id (full plan, notice received)");
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode([
            'success'           => true,
            'message'           => "$exam_period permit approved! You can now view and print your permit.",
            'permit_identifier' => $permitIdentifier,
            'auto_approved'     => true,
        ]);
        return;
    }

    // ── Installment-plan: permit allowed with partial/zero payment if Accounting sent a notice ───
    //
    // CARRY-OVER POLICY (FIX PERMIT-CARRY-01):
    //   • Old behavior: blocked permit if paid == 0. Broken for partial-payment students.
    //   • New behavior: permit is requestable as long as Accounting sent a notice (the unlock
    //     signal). Any unpaid balance for this period is shown to Accounting as carry-over info
    //     in the remarks. On APPROVAL, processExamPermit() records the carry-over amount in
    //     payment_schedules and recomputeSchedule() redistributes it to remaining terms.
    //   • The student does NOT need to re-pay the old period balance before the next exam —
    //     it is automatically folded into the next term's due amount.
    $paidRes = $conn->query("SELECT COALESCE(SUM(amount),0) AS paid FROM installment_payments ip JOIN students _st ON _st.id=ip.student_id WHERE ip.student_id=$student_id AND ip.exam_period='$exam_period' AND ip.semester=_st.semester");
    $paid = $paidRes ? (float)$paidRes->fetch_assoc()['paid'] : 0;

    // Get the recomputed due for this period so we can calculate carry-over
    $pCol    = strtolower($exam_period);
    $schDueR = $conn->query("SELECT {$pCol}_due FROM payment_schedules WHERE student_id=$student_id LIMIT 1");
    $schDueRow  = $schDueR ? $schDueR->fetch_assoc() : null;
    $periodDue  = $schDueRow ? (float)($schDueRow[$pCol . '_due'] ?? 0) : 0;
    $carryOver  = max(0.0, round($periodDue - $paid, 2));

    $stuRes = $conn->query("SELECT student_number FROM students WHERE id=$student_id LIMIT 1");
    $stuNum = $stuRes ? ($stuRes->fetch_assoc()['student_number'] ?? 'STU') : 'STU';
    $stuNumClean  = preg_replace('/[^A-Z0-9]/', '', strtoupper($stuNum));
    $periodCode   = strtoupper(substr($exam_period, 0, 3));
    $permitIdentifier = 'EP-' . date('Ymd') . '-' . $stuNumClean . '-' . $periodCode;

    // Build remarks so Accounting sees the carry-over situation immediately
    $autoRemarks = $carryOver > 0
        ? "₱" . number_format($paid, 2) . " paid of ₱" . number_format($periodDue, 2) . " due. "
          . "Carry-over: ₱" . number_format($carryOver, 2) . " will be added to the next term upon approval."
        : 'Fully paid for this period.';

    $stmt = $conn->prepare("INSERT INTO exam_permits
        (student_id,exam_period,school_year,semester,status,permit_identifier,remarks)
        VALUES (?,?,?,?,'pending',?,?)
        ON DUPLICATE KEY UPDATE
            status='pending',requested_at=NOW(),approved_at=NULL,
            permit_identifier=VALUES(permit_identifier),
            remarks=VALUES(remarks)");
    $stmt->bind_param("isssss", $student_id, $exam_period, $school_year, $semester, $permitIdentifier, $autoRemarks);
    $stmt->execute(); $stmt->close();

    $logNote = $carryOver > 0
        ? "installment, ₱".number_format($paid,2)." paid, ₱".number_format($carryOver,2)." carry-over pending"
        : "installment, fully paid for period";
    logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'REQUEST_PERMIT', 'student', $student_id,
        "Requested $exam_period permit for student ID $student_id ($logNote)");
    while (ob_get_level() > 0) { ob_end_clean(); }

    $studentMsg = "$exam_period permit request submitted! Accounting will process it shortly.";
    if ($carryOver > 0) {
        $studentMsg .= " Your remaining balance of ₱" . number_format($carryOver, 2)
            . " will automatically carry over to your next term's payment once your permit is approved.";
    }
    echo json_encode([
        'success'           => true,
        'message'           => $studentMsg,
        'permit_identifier' => $permitIdentifier,
        'carry_over'        => $carryOver,
        'paid_so_far'       => $paid,
        'period_due'        => $periodDue,
    ]);
}

function processExamPermit($conn, $data) {
    $permit_id   = (int)($data['permit_id']          ?? 0);
    $action      = ($data['action'] === 'approve') ? 'approved' : 'rejected';
    $remarks     = trim($data['remarks'] ?? '');
    $approved_by = (int)($data['accounting_user_id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$permit_id) { echo json_encode(['success'=>false,'message'=>'permit_id required']); return; }

    // Fetch permit details before updating so we can compute carry-over on approval
    $permRow = (($_r=$conn->query("SELECT student_id, exam_period, permit_identifier FROM exam_permits WHERE id=$permit_id LIMIT 1")) ? $_r->fetch_assoc() : null);
    if (!$permRow) { echo json_encode(['success'=>false,'message'=>'Permit not found.']); return; }

    $permitIdentifier = $permRow['permit_identifier'] ?? '';
    $student_id       = (int)$permRow['student_id'];
    $exam_period      = $permRow['exam_period'];  // Prelim | Midterm | Finals

    $escapedRemarks = $conn->real_escape_string($remarks);
    $conn->query("UPDATE exam_permits SET status='$action',approved_at=NOW(),
        approved_by=$approved_by,remarks='$escapedRemarks' WHERE id=$permit_id");

    // ── CARRY-OVER: on approval, record unpaid balance and recompute future dues ────────────
    //
    // FIX PERMIT-CARRY-01: When Accounting approves a partial-payment permit,
    // compute the carry-over amount (period_due - period_paid) and save it in
    // payment_schedules. Then call recomputeSchedule() which redistributes the
    // total remaining balance (including carry-over) evenly across unlocked future
    // terms. The student does NOT pay the old balance again — it rolls forward.
    $carryOver = 0.0;
    if ($action === 'approved') {
        $pCol    = strtolower($exam_period);

        // Actual paid for this period
        $paidR   = $conn->query("SELECT COALESCE(SUM(ip.amount),0) AS paid
            FROM installment_payments ip JOIN students _st ON _st.id=ip.student_id
            WHERE ip.student_id=$student_id AND ip.exam_period='$exam_period' AND ip.semester=_st.semester");
        $paid    = $paidR ? (float)$paidR->fetch_assoc()['paid'] : 0.0;

        // Scheduled due for this period
        $dueR    = $conn->query("SELECT {$pCol}_due FROM payment_schedules WHERE student_id=$student_id LIMIT 1");
        $dueRow  = $dueR ? $dueR->fetch_assoc() : null;
        $due     = $dueRow ? (float)($dueRow[$pCol . '_due'] ?? 0) : 0.0;

        $carryOver = max(0.0, round($due - $paid, 2));

        if ($carryOver > 0) {
            // Persist carry-over in payment_schedules (column added by migrate.php)
            // Use ADD COLUMN IF NOT EXISTS guard so this is safe even before migration runs
            $safeCol = $pCol . '_carry_over'; // prelim_carry_over | midterm_carry_over | finals_carry_over
            $conn->query("UPDATE payment_schedules SET {$safeCol}=$carryOver WHERE student_id=$student_id");

            // recomputeSchedule() sees total_paid < total_assessment and redistributes
            // the remaining balance (which includes this carry-over) across future terms.
            recomputeSchedule($conn, $student_id);
        }
    }

    $logDetail = "Exam permit {$action}d. ID: $permit_id. Permit#: $permitIdentifier.";
    if ($carryOver > 0) $logDetail .= " Carry-over ₱" . number_format($carryOver, 2) . " redistributed to next term.";
    if ($remarks) $logDetail .= " Remarks: $remarks";

    logAuditShared($conn, $GLOBALS['authUser'] ?? null, strtoupper($action).'_PERMIT', 'exam_permit', $permit_id, $logDetail);
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'           => true,
        'message'           => "Permit $action." . ($carryOver > 0 ? " Carry-over of ₱" . number_format($carryOver, 2) . " has been added to the student's next term." : ''),
        'permit_identifier' => $permitIdentifier,
        'carry_over'        => $carryOver,
    ]);
}
function getAllEnrolledStudents($conn) {
    // FIX FILTER-06: Accept optional filters so the enrolled students tab can be
    // scoped by semester, program, category, year level, payment plan, and payment status.
    $filterProgram    = trim($_GET['program']        ?? '');
    $filterYearLevel  = trim($_GET['year_level']     ?? '');
    $filterCategory   = trim($_GET['category']       ?? '');
    $filterSemester   = trim($_GET['semester']       ?? '');
    $filterPayPlan    = trim($_GET['payment_plan']   ?? '');
    $filterPayStatus  = trim($_GET['payment_status'] ?? '');

    // FIX FILTER-08: Default to the current enrollment period semester when no
    // semester filter is passed. This prevents students from a previous semester
    // (who haven't re-enrolled yet) from appearing in the Send Notice list.
    // Only students enrolled in the ACTIVE semester should be visible here.
    if ($filterSemester === '') {
        $epRow = $conn->query("SELECT config_value FROM sys_config WHERE config_key = 'enrollment_period' LIMIT 1");
        if ($epRow) {
            $epData = $epRow->fetch_assoc();
            $ep = json_decode($epData['config_value'] ?? '{}', true);
            if (!empty($ep['label'])) {
                $filterSemester = trim($ep['label']);
            }
        }
    }

    $extraWhere = '';
    if ($filterProgram   !== '') $extraWhere .= " AND s.program = '" . $conn->real_escape_string($filterProgram) . "'";
    if ($filterYearLevel !== '') $extraWhere .= " AND LOWER(s.year_level) = LOWER('" . $conn->real_escape_string($filterYearLevel) . "')";
    if ($filterCategory  !== '') $extraWhere .= " AND UPPER(COALESCE(s.student_category,'College')) = UPPER('" . $conn->real_escape_string($filterCategory) . "')";
    // FIX FILTER-09: Use flexible semester matching instead of exact string equality.
    // The sys_config enrollment_period label (e.g. "1st Semester, AY 2024-2025") often
    // does not exactly match what is stored in students.semester (e.g. "1st Semester" or
    // "1st Semester, AY 2024-2025"). Using LIKE on both sides ensures students are found
    // regardless of whether the AY suffix is present in the stored value or the filter.
    if ($filterSemester !== '') {
        $escFull = $conn->real_escape_string($filterSemester);
        // Strip trailing ", AY YYYY-YYYY" to get the bare semester label (e.g. "1st Semester")
        $semPart = $conn->real_escape_string(trim(preg_replace('/[,\s]*AY[\s\d\-]+$/i', '', $filterSemester)));
        $extraWhere .= " AND (
            s.semester = '$escFull'
            OR s.semester LIKE '$semPart%'
            OR '$escFull' LIKE CONCAT(s.semester, '%')
        )";
    }
    if ($filterPayPlan   !== '') $extraWhere .= " AND s.payment_plan = '" . $conn->real_escape_string($filterPayPlan) . "'";
    if ($filterPayStatus !== '') $extraWhere .= " AND s.payment_status = '" . $conn->real_escape_string($filterPayStatus) . "'";

    // FIX FILTER-07: Join tuition_fees scoped to s.semester — same fix as
    // getInstallmentStudents — avoids wrong total_assessment for re-enrolled students.
    $res = $conn->query("
        SELECT
            s.id             AS student_id,
            s.student_number,
            s.first_name,
            s.last_name,
            s.program,
            s.year_level,
            s.semester,
            s.payment_method,
            s.payment_plan,
            s.payment_status,
            s.approval_status,
            s.enrollment_status,
            UPPER(COALESCE(s.student_category, 'College')) AS student_category,
            COALESCE(tf.total_assessment, 0) AS total_assessment,
            COALESCE(ps.prelim_due,   ROUND(COALESCE(tf.total_assessment,0) / 4, 2)) AS prelim_due,
            COALESCE(ps.midterm_due,  ROUND(COALESCE(tf.total_assessment,0) / 4, 2)) AS midterm_due,
            COALESCE(ps.finals_due,   ROUND(COALESCE(tf.total_assessment,0) / 4, 2)) AS finals_due,
            COALESCE(ps.prelim_paid,  0) AS prelim_paid,
            COALESCE(ps.midterm_paid, 0) AS midterm_paid,
            COALESCE(ps.finals_paid,  0) AS finals_paid,
            COALESCE(ps.prelim_status,  'locked') AS prelim_status,
            COALESCE(ps.midterm_status, 'locked') AS midterm_status,
            COALESCE(ps.finals_status,  'locked') AS finals_status,
            (SELECT sg.email
             FROM student_guardians sg
             WHERE sg.student_id = s.id
               AND sg.email IS NOT NULL
               AND TRIM(sg.email) != ''
             ORDER BY sg.is_emergency DESC, sg.id ASC
             LIMIT 1) AS guardianEmail
        FROM students s
        LEFT JOIN tuition_fees      tf ON tf.student_id = s.id AND tf.semester = s.semester
        LEFT JOIN payment_schedules ps ON ps.student_id = s.id
        WHERE s.approval_status   = 'Approved'
          AND s.enrollment_status IN ('Enrolled', 'Confirmed')
          $extraWhere
        ORDER BY s.payment_plan ASC, s.last_name ASC, s.first_name ASC
    ");

    $students = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $students[] = buildStudentRow($row);
        }
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    global $authUser;
    $students = applyPrivacyList($students, $authUser, 'financial');
    echo json_encode(['success' => true, 'students' => $students]);
}

function buildStudentRow($row) {
    return [
        'studentId'      => (int)$row['student_id'],
        'studentNumber'  => $row['student_number'],
        'firstName'      => $row['first_name'],
        'lastName'       => $row['last_name'],
        'program'        => $row['program'],
        'yearLevel'      => $row['year_level'],
        'semester'       => $row['semester'],
        'paymentMethod'  => $row['payment_method'],
        'paymentPlan'    => $row['payment_plan'],
        'paymentStatus'  => $row['payment_status'] ?? '',
        'approvalStatus' => $row['approval_status'] ?? '',
        'enrollmentStatus'  => $row['enrollment_status'] ?? '',
        'studentCategory'   => strtoupper($row['student_category'] ?? 'College'),
        'totalAssessment'=> (float)$row['total_assessment'],
        'prelimDue'      => (float)$row['prelim_due'],
        'midtermDue'     => (float)$row['midterm_due'],
        'finalsDue'      => (float)$row['finals_due'],
        'prelimPaid'     => (float)$row['prelim_paid'],
        'midtermPaid'    => (float)$row['midterm_paid'],
        'finalsPaid'     => (float)$row['finals_paid'],
        'prelimStatus'   => $row['prelim_status'],
        'midtermStatus'  => $row['midterm_status'],
        'finalsStatus'   => $row['finals_status'],
        'guardianEmail'  => $row['guardianEmail'] ?? '',
    ];
}

// ─────────────────────────────────────────────────────────────
// ACCOUNTING: Unlock Payment Period (Direct unlock without sending notice)
// POST ?action=unlock_payment_period
// Body: { student_id, exam_period, accounting_user_id }
// ─────────────────────────────────────────────────────────────
function unlockPaymentPeriod($conn, $data) {
    $student_id  = (int)($data['student_id']  ?? 0);
    $exam_period = trim($data['exam_period']  ?? '');
    $acc_user_id = (int)($data['accounting_user_id'] ?? 0);

    if (!$student_id || !in_array($exam_period, ['Prelim', 'Midterm', 'Finals'])) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'Invalid student_id or exam_period']);
        return;
    }

    // ── 1. Check whether payment_schedules record already exists ──────────
    // FIX AC-03: Use prepared statements in unlockPaymentPeriod
    $checkStmt = $conn->prepare("SELECT id FROM payment_schedules WHERE student_id = ? LIMIT 1");
    $checkStmt->bind_param("i", $student_id);
    $checkStmt->execute();
    $check = $checkStmt->get_result();
    if (!$check) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'DB error (check): ' . $conn->error]);
        return;
    }

    if ($check->num_rows === 0) {
        // ── 2a. No record yet — fetch total_assessment from tuition_fees ──
        $tfRes = $conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id = $student_id LIMIT 1");
        $tfRow = $tfRes ? $tfRes->fetch_assoc() : null;
        $total = $tfRow ? (float)$tfRow['total_assessment'] : 0;

        // Fallback: compute from students + formula if tuition_fees row is missing
        if ($total <= 0) {
            $stRes = $conn->query("SELECT program FROM students WHERE id = $student_id LIMIT 1");
            $stRow = $stRes ? $stRes->fetch_assoc() : null;
            if ($stRow) {
                // Use default 18 units as last resort — same fallback as getFeePreview()
                $units         = 18;
                $total         = ($units * 650) + 6688 + 700 + ($units * 21 * 3);
            }
        }

        if ($total <= 0) {
            while (ob_get_level() > 0) { ob_end_clean(); }
            echo json_encode(['success' => false, 'message' => 'No tuition fees found for this student. Please compute fees first.']);
            return;
        }

        $pd = round($total / 4, 2);
        $md = round($total / 4, 2);
        $fd = round($total - $pd - $md * 2, 2);

       
        $prelim_status  = ($exam_period === 'Prelim')   ? 'unpaid' : 'locked';
        $midterm_status = ($exam_period === 'Midterm')  ? 'unpaid' : 'locked';
        $finals_status  = ($exam_period === 'Finals')   ? 'unpaid' : 'locked';
        $prelim_ts      = ($exam_period === 'Prelim')   ? 'NOW()' : 'NULL';
        $midterm_ts     = ($exam_period === 'Midterm')  ? 'NOW()' : 'NULL';
        $finals_ts      = ($exam_period === 'Finals')   ? 'NOW()' : 'NULL';

        $insertSql = "
            INSERT INTO payment_schedules
                (student_id, payment_type, total_assessment,
                 prelim_due, midterm_due, finals_due,
                 prelim_status, midterm_status, finals_status,
                 prelim_unlocked_at, midterm_unlocked_at, finals_unlocked_at)
            VALUES (
                $student_id, 'installment', $total,
                $pd, $md, $fd,
                '$prelim_status', '$midterm_status', '$finals_status',
                $prelim_ts, $midterm_ts, $finals_ts
            )
        ";

        $conn->query($insertSql);
        if ($conn->error) {
            while (ob_get_level() > 0) { ob_end_clean(); }
            echo json_encode(['success' => false, 'message' => 'DB error (insert): ' . $conn->error]);
            return;
        }

    } else {
        
        //
        if ($exam_period === 'Prelim') {
            $sql = "UPDATE payment_schedules
                    SET prelim_status      = IF(prelim_status      = 'locked', 'unpaid', prelim_status),
                        prelim_unlocked_at = IF(prelim_unlocked_at IS NULL, NOW(), prelim_unlocked_at)
                    WHERE student_id = $student_id";
        } elseif ($exam_period === 'Midterm') {
            $sql = "UPDATE payment_schedules
                    SET midterm_status      = IF(midterm_status      = 'locked', 'unpaid', midterm_status),
                        midterm_unlocked_at = IF(midterm_unlocked_at IS NULL, NOW(), midterm_unlocked_at)
                    WHERE student_id = $student_id";
        } else { // Finals
            $sql = "UPDATE payment_schedules
                    SET finals_status      = IF(finals_status      = 'locked', 'unpaid', finals_status),
                        finals_unlocked_at = IF(finals_unlocked_at IS NULL, NOW(), finals_unlocked_at)
                    WHERE student_id = $student_id";
        }

        $conn->query($sql);
        if ($conn->error) {
            while (ob_get_level() > 0) { ob_end_clean(); }
            echo json_encode(['success' => false, 'message' => 'DB error (update): ' . $conn->error]);
            return;
        }
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success' => true,
        'message' => "$exam_period payment period has been unlocked.",
    ]);
    logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'UNLOCK_PERIOD', 'student', $student_id,
        "Unlocked $exam_period payment period for student ID $student_id");
}
// ─────────────────────────────────────────────────────────────
// GET STUDENT INSTALLMENT BREAKDOWN
// GET ?action=get_student_installment&student_id=XX
//
// Returns the full installment schedule for a student:
// - per-term due/paid/status/balance
// - total assessment, total paid, remaining balance
// - all payment receipts (installment_payments rows)
// - payment notices per period
// Used by: accounting permit page, student payment-schedule page
// ─────────────────────────────────────────────────────────────
function getStudentInstallment($conn) {
    $student_id = (int)($_GET['student_id'] ?? 0);

    // Also accept user_id and resolve to student_id (defensive)
    if (!$student_id) {
        $user_id = (int)($_GET['user_id'] ?? 0);
        if ($user_id) {
            $ur = $conn->query("SELECT id FROM students WHERE user_id = $user_id LIMIT 1");
            $ur_row = $ur ? $ur->fetch_assoc() : null;
            if ($ur_row) $student_id = (int)$ur_row['id'];
        }
    }
    // FIX TVET-FEES-01: Use the student's actual category for fee config lookup.
    // TVET diploma students have misc_fee / reg_fee from the TVET config,
    // not the College config. Using 'College' gave wrong fee breakdown labels.
    $stCatRes = $conn->query("SELECT student_category FROM students WHERE id=$student_id LIMIT 1");
    $stCatRow = $stCatRes ? $stCatRes->fetch_assoc() : null;
    $feeCategory = match(strtoupper(trim($stCatRow['student_category'] ?? ''))) {
        'SHS'  => 'SHS',
        'TVET' => 'TVET',
        default => 'College',
    };

    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$student_id) { echo json_encode(['success' => false, 'message' => 'student_id required']); return; }

    // ── Student info ──────────────────────────────────────────
    $stRes = $conn->query("SELECT id, student_number, first_name, last_name, program, year_level,
                                  semester, payment_plan, payment_method, payment_status
                           FROM students WHERE id = $student_id LIMIT 1");
    $student = $stRes ? $stRes->fetch_assoc() : null;
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$student) { echo json_encode(['success' => false, 'message' => 'Student not found']); return; }

    // ── Tuition fees ──────────────────────────────────────────
    $tfRes = $conn->query("SELECT * FROM tuition_fees WHERE student_id = $student_id LIMIT 1");
    $tf    = $tfRes ? $tfRes->fetch_assoc() : null;
    $total_assessment = $tf ? (float)$tf['total_assessment'] : 0;

    // ── Payment schedule row (per-term due/paid/status) ───────
    $psRes = $conn->query("SELECT * FROM payment_schedules WHERE student_id = $student_id LIMIT 1");
    $ps    = $psRes ? $psRes->fetch_assoc() : null;

    // Compute per-term due amounts (4 equal terms for installment)
    $term_due = $total_assessment > 0 ? round($total_assessment / 4, 2) : 0;

    $terms = ['Prelim', 'Midterm', 'Finals'];
    $term_data = [];
    $total_paid_all = 0;

    // Downpayment (DP) — first installment
    $dp_res  = $conn->query("SELECT COALESCE(SUM(amount),0) AS paid FROM installment_payments ip JOIN students _st ON _st.id=ip.student_id WHERE ip.student_id=$student_id AND ip.exam_period='Downpayment' AND ip.semester=_st.semester");
    $dp_paid = (float)($dp_res->fetch_assoc()['paid'] ?? 0);
    $dp_due  = $term_due;
    $dp_balance = max(0, $dp_due - $dp_paid);
    $dp_status  = $dp_paid <= 0 ? 'unpaid' : ($dp_paid >= $dp_due ? 'paid' : 'partial');
    $total_paid_all += $dp_paid;

    $term_data[] = [
        'period'   => 'Downpayment',
        'due'      => $dp_due,
        'paid'     => $dp_paid,
        'balance'  => $dp_balance,
        'status'   => $dp_status,
        'schedStatus' => $dp_status,
    ];

    foreach ($terms as $period) {
        $p = strtolower($period);
        $due    = $ps ? (float)$ps[$p.'_due']  : $term_due;
        $paid   = $ps ? (float)$ps[$p.'_paid'] : 0;
        $status = $ps ? $ps[$p.'_status'] : 'locked';

        // Recompute paid from installment_payments for accuracy
        $pr = $conn->query("SELECT COALESCE(SUM(amount),0) AS paid FROM installment_payments ip JOIN students _st ON _st.id=ip.student_id WHERE ip.student_id=$student_id AND ip.exam_period='$period' AND ip.semester=_st.semester");
        $paid = (float)($pr->fetch_assoc()['paid'] ?? 0);

        $balance = max(0, $due - $paid);
        $total_paid_all += $paid;

        $term_data[] = [
            'period'      => $period,
            'due'         => $due,
            'paid'        => $paid,
            'balance'     => $balance,
            'status'      => $status,
            'schedStatus' => $status,
        ];
    }

    $remaining_balance = max(0, $total_assessment - $total_paid_all);

    // ── All payment receipts ──────────────────────────────────
    $ipRes = $conn->query("
        SELECT ip.*, COALESCE(sp.first_name, f2.first_name) AS recorded_by_name
        FROM installment_payments ip
        LEFT JOIN users u ON ip.recorded_by = u.id
        LEFT JOIN staff_profiles sp ON sp.user_id = u.id
        LEFT JOIN faculty f2 ON f2.user_id = u.id
        WHERE ip.student_id = $student_id
        ORDER BY ip.payment_date ASC, ip.created_at ASC
    ");
    $receipts = [];
    if ($ipRes) {
        while ($r = $ipRes->fetch_assoc()) {
            $receipts[] = [
                'id'             => (int)$r['id'],
                'orArNumber'     => $r['or_ar_number'],
                'orArType'       => $r['or_ar_type'],
                'amount'         => (float)$r['amount'],
                'paymentDate'    => $r['payment_date'],
                'paymentMethod'  => $r['payment_method'],
                'gcashReference' => $r['gcash_reference'] ?? '',
                'examPeriod'     => $r['exam_period'],
                'notes'          => $r['notes'] ?? '',
                'recordedByName' => $r['recorded_by_name'] ?? 'Accounting',
            ];
        }
    }

    // ── Payment notices per period ────────────────────────────
    $notRes = $conn->query("SELECT exam_period, amount_due, due_date, message, sent_at, is_read
                            FROM payment_notices WHERE student_id=$student_id");
    $notices = [];
    if ($notRes) {
        while ($r = $notRes->fetch_assoc()) $notices[$r['exam_period']] = $r;
    }

    // ── Installment due dates vs actual paid dates (for SOA timeline display) ──
    // Shows Accounting-set due date per term; replaces with actual payment date once paid.
    $currentSem = trim($student['semester'] ?? '');
    $installmentDueDates = _getInstallmentPaymentDates($conn, $student_id, $currentSem);

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'              => true,
        'student'              => $student,
        'fees'                 => $tf ? [
            'units'            => (int)$tf['units'],
            'tuitionFee'       => (float)$tf['tuition_fee'],
            'miscellaneousFee' => (float)$tf['miscellaneous_fee'],
            'registrationFee'  => (float)$tf['registration_fee'],
            'laboratoryFee'    => (float)$tf['laboratory_fee'],
            'energyFee'        => (float)$tf['energy_fee'],
            'extraFees'        => _buildExtraFeesList($conn, $feeCategory, (int)$tf['units']),
            'subtotal'         => (float)$tf['subtotal'],
            'discount'         => (float)$tf['discount'],
            'installmentFee'   => (float)$tf['installment_fee'],
            'totalAssessment'  => $total_assessment,
        ] : null,
        'terms'               => $term_data,       // per-term: due/paid/balance/status
        'receipts'            => $receipts,        // all OR/AR receipts
        'notices'             => $notices,         // payment notices per period
        // SOA-DUE-DATES: per-term due dates + actual paid dates for the SOA table.
        // Angular: show "Due: <dueDateRange>" → replace with "Paid on: <paidDate>" once paid.
        'installmentDueDates' => $installmentDueDates,
        'totalAssessment'     => $total_assessment,
        'totalPaid'           => $total_paid_all,
        'remainingBalance'    => $remaining_balance,
        'paymentStatus'       => $total_paid_all <= 0 ? 'Unpaid'
                                  : ($remaining_balance <= 0 ? 'Fully Paid' : 'Partial'),
    ]);
}
// ─────────────────────────────────────────────────────────────
// STUDENT: Submit installment payment for Accounting approval
// POST ?action=submit_installment_payment
// Body: { student_id, amount, payment_date, payment_method,
//         gcash_reference?, exam_period, notes? }
//
// This mirrors the enrollment GCash/Cash flow:
//  1. Saves a pending payment_log record
//  2. Accounting sees it in their queue (get_pending_payments)
//  3. Accounting verifies → verify_payment → recordInstallment runs
//     which also syncs payment_schedules
// ─────────────────────────────────────────────────────────────
function submitInstallmentPayment($conn, $data) {
    $student_id     = (int)($data['student_id']      ?? 0);
    $amount         = (float)($data['amount']         ?? 0);
    $payment_date   = trim($data['payment_date']     ?? date('Y-m-d'));
    $payment_method = trim($data['payment_method']   ?? 'Cash');
    $gcash_ref      = trim($data['gcash_reference']  ?? '');
    $exam_period    = trim($data['exam_period']      ?? '');
    $notes          = trim($data['notes']            ?? '');

    if (!$student_id || $amount <= 0) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'student_id and amount required']); return;
    }
    // FIX PM-GCASH-DOWNPAYMENT-01: Added 'Downpayment' and 'Full' as valid exam_period values.
    // GCash installment students submit their first payment as 'Downpayment' — previously
    // this was rejected here, causing their payment to never reach Accounting.
    // 'Full' is needed for full-payment GCash students submitting via this endpoint.
    if (!in_array($exam_period, ['Downpayment', 'Prelim', 'Midterm', 'Finals', 'Full'])) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'Invalid exam_period']); return;
    }

    // Generate a temporary reference number for tracking
    $year    = date('Y');
    $cntRes  = $conn->query("SELECT COUNT(*) AS cnt FROM payment_logs WHERE YEAR(created_at) = $year");
    $cnt     = (int)($cntRes->fetch_assoc()['cnt'] ?? 0) + 1;
    $ref     = 'PAY-' . $year . str_pad($cnt, 4, '0', STR_PAD_LEFT);

    $pm_esc    = $payment_method;
    $ref_esc   = $gcash_ref ?: $ref;
    $date_esc  = $payment_date;
    $ep_esc    = $exam_period;
    $extra_esc = $notes;
    // notes format: "Midterm|[Midterm] extra notes" — exam_period prefix BEFORE bracket
    // This is what verifyPayment parses with regex /^(Prelim|Midterm|Finals...)\|?/
    $notes_full = "$exam_period|[$exam_period] $notes";

    // Insert into payment_logs as Pending — same table Accounting watches.
    // exam_period is saved to its dedicated column so verifyPayment() can reliably
    // detect which installment term this payment belongs to without regex parsing.
    $conn->query("INSERT INTO payment_logs
        (student_id, gcash_reference, gcash_amount, gcash_date, payment_method, exam_period, status, notes)
        VALUES ($student_id, '$ref_esc', $amount, '$date_esc', '$pm_esc', '$ep_esc', 'Pending', '$notes_full')");

    if ($conn->error) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]); return;
    }

    $log_id = $conn->insert_id;

    // FIX REJECT-NOTES-01: Clear the rejection reason now that the student has
    // resubmitted, so the old accounting rejection message doesn't persist on
    // the enrollment page during the new pending-verification period.
    $conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS rejection_reason TEXT DEFAULT NULL");
    $conn->query("UPDATE students SET rejection_reason = NULL WHERE id = $student_id");

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'     => true,
        'message'     => 'Payment submitted. Waiting for Accounting to verify.',
        'orArNumber'  => $ref,
        'logId'       => $log_id,
    ]);
}

// ─────────────────────────────────────────────────────────────
// GET PERMIT DETAILS — for student permit viewer
// GET ?action=get_permit_details&permit_id=XX&student_id=XX
//
// Returns permit + student info + enrolled courses
// ─────────────────────────────────────────────────────────────
function getPermitDetails($conn) {
    $permit_id  = (int)($_GET['permit_id']  ?? 0);
    $student_id = (int)($_GET['student_id'] ?? 0);
    if (!$permit_id || !$student_id) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'permit_id and student_id required']); return;
    }

    // Permit + approver info
    $pRes = $conn->query("
        SELECT ep.*,
               s.student_number, s.first_name, s.last_name, s.program, s.year_level,
               s.semester AS raw_semester,
               COALESCE(sp.first_name, f2.first_name) AS approved_by_first, COALESCE(sp.last_name, f2.last_name) AS approved_by_last
        FROM exam_permits ep
        JOIN students s ON ep.student_id = s.id
        LEFT JOIN users u ON ep.approved_by = u.id
        LEFT JOIN staff_profiles sp ON sp.user_id = u.id
        LEFT JOIN faculty f2 ON f2.user_id = u.id
        WHERE ep.id = $permit_id AND ep.student_id = $student_id
        LIMIT 1
    ");
    $permit = $pRes ? $pRes->fetch_assoc() : null;
    if (!$permit) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'Permit not found']); return;
    }

    // ── Parse accurate semester + school_year from DB ─────────────────────
    // students.semester stores full string: "2nd Semester, AY 2025-2026"
    $rawSemester = trim($permit['raw_semester'] ?? '');
    $semLabel    = $permit['semester'];    // already stored when permit was created
    $schoolYear  = $permit['school_year']; // already stored when permit was created

    // If the stored permit semester looks like the full raw string, re-parse it
    if (preg_match('/^(.+?),\s*AY\s*([\d]{4}-[\d]{4})/i', $semLabel, $m)) {
        $semLabel   = trim($m[1]);
        $schoolYear = trim($m[2]);
    } elseif (preg_match('/^(.+?),\s*AY\s*([\d]{4}-[\d]{4})/i', $rawSemester, $m)) {
        $semLabel   = trim($m[1]);
        $schoolYear = trim($m[2]);
    }

    $permit['semester']    = $semLabel;
    $permit['school_year'] = $schoolYear;

    // ── Enrolled courses filtered by the student's current semester ───────
    // Primary: match on enrollments.semester (most reliable — written when the
    // student enrolled) rather than courses.semester (which may differ in format).
    // This prevents the fallback from returning courses from a different semester.
    $semEsc = $conn->real_escape_string($rawSemester ?: $semLabel);
    $ayEsc  = $conn->real_escape_string($schoolYear);
    $semLabelEsc = $conn->real_escape_string($semLabel);

    // FIX PERMIT-04: Scope primary query to enrollments.semester, not courses.semester.
    // courses.semester stores the term a course was OFFERED — it may be a different
    // AY or format. enrollments.semester is stamped when the student actually enrolled
    // and is always the correct scope for permit subject listing.
    $cRes = $conn->query("
        SELECT DISTINCT c.code, c.name,
               CONCAT(COALESCE(f.first_name,''),' ',COALESCE(f.last_name,'')) AS instructor
        FROM enrollments e
        JOIN courses c    ON e.course_id  = c.id
        LEFT JOIN faculty f ON f.id = c.faculty_id
        WHERE e.student_id = $student_id
          AND e.status = 'Enrolled'
          AND (
            e.semester = '$semEsc'
            OR e.semester LIKE '%$ayEsc%'
            OR e.semester LIKE '%$semLabelEsc%'
          )
        ORDER BY c.code ASC
    ");
    $courses = [];
    if ($cRes) {
        while ($r = $cRes->fetch_assoc()) {
            $courses[] = ['code' => cleanCode($r['code']), 'name' => $r['name'], 'instructor' => $r['instructor']];
        }
    }

    // FIX PERMIT-05: Only fall back when there is genuinely no semester data to
    // match on (e.g. legacy enrollments with NULL semester). Do NOT fall back
    // unconditionally — that was the bug that caused wrong-semester subjects to
    // appear on permits when the semester string format didn't match exactly.
    if (empty($courses) && ($semEsc === '' && $ayEsc === '')) {
        // Truly no semester context — last resort: all enrolled courses for this student
        $cRes2 = $conn->query("
            SELECT DISTINCT c.code, c.name,
                   CONCAT(COALESCE(f.first_name,''),' ',COALESCE(f.last_name,'')) AS instructor
            FROM enrollments e
            JOIN courses c    ON e.course_id  = c.id
            LEFT JOIN faculty f ON f.id = c.faculty_id
            WHERE e.student_id = $student_id AND e.status = 'Enrolled'
            ORDER BY c.code ASC
        ");
        if ($cRes2) {
            while ($r = $cRes2->fetch_assoc()) {
                $courses[] = ['code' => cleanCode($r['code']), 'name' => $r['name'], 'instructor' => $r['instructor']];
            }
        }
    }

    $permit['courses'] = $courses;
    unset($permit['raw_semester']); // clean up internal field

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'permit' => $permit]);
}

// ─────────────────────────────────────────────────────────────
// GET INSTALLMENT STUDENTS
// Returns all students using installment payment plan with their balance.
// Called by accounting.ts tab for installment management.
// ─────────────────────────────────────────────────────────────
function getInstallmentStudents($conn) {
    // FIX FILTER-04: Accept optional filters for better tab usability
    $filterProgram   = trim($_GET['program']    ?? '');
    $filterYearLevel = trim($_GET['year_level'] ?? '');
    $filterCategory  = trim($_GET['category']   ?? '');
    $filterSemester  = trim($_GET['semester']   ?? '');
    $filterStatus    = trim($_GET['payment_status'] ?? '');

    $extraWhere = '';
    if ($filterProgram   !== '') $extraWhere .= " AND s.program = '" . $conn->real_escape_string($filterProgram) . "'";
    if ($filterYearLevel !== '') $extraWhere .= " AND LOWER(s.year_level) = LOWER('" . $conn->real_escape_string($filterYearLevel) . "')";
    if ($filterCategory  !== '') $extraWhere .= " AND UPPER(COALESCE(s.student_category,'College')) = UPPER('" . $conn->real_escape_string($filterCategory) . "')";
    if ($filterSemester  !== '') $extraWhere .= " AND s.semester = '" . $conn->real_escape_string($filterSemester) . "'";
    if ($filterStatus    !== '') $extraWhere .= " AND s.payment_status = '" . $conn->real_escape_string($filterStatus) . "'";

    // FIX FILTER-05: Join tuition_fees scoped to s.semester (not MAX(id)) so
    // re-enrolled students always get the correct current-semester assessment.
    // MAX(id) picks the newest row globally — if a student re-enrolled and a
    // new tuition_fees row was inserted for the new semester, MAX(id) is correct
    // by coincidence; but if rows were inserted out-of-order (e.g. corrections),
    // it silently returns the wrong amount. Scoping by semester is unambiguous.
    $res = $conn->query("
        SELECT
            s.id AS student_id,
            s.student_number,
            s.first_name,
            s.last_name,
            s.program,
            s.year_level,
            s.semester,
            s.student_category,
            s.payment_plan,
            s.payment_status,
            s.approval_status,
            s.enrollment_status,
            tf.total_assessment,
            COALESCE((SELECT SUM(ip2.amount) FROM installment_payments ip2 WHERE ip2.student_id = s.id AND ip2.semester = s.semester), 0) AS total_paid,
            s.program AS department
        FROM students s
        LEFT JOIN tuition_fees tf ON tf.student_id = s.id
            AND tf.semester = s.semester
        LEFT JOIN programs p ON (p.name = s.program OR p.code = s.program)
                              AND p.level_type = s.student_category
        WHERE s.payment_plan = 'installment' $extraWhere
        ORDER BY s.last_name, s.first_name
    ");
    $students = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $total      = (float)($r['total_assessment'] ?? 0);
            $paid       = (float)($r['total_paid']       ?? 0);
            $r['balance']   = max(0, $total - $paid);
            $r['total_paid']= $paid;
            $students[]     = $r;
        }
    }
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'students' => $students]);
}

// ─────────────────────────────────────────────────────────────
// EDIT PAYMENT — update an existing payment_log record
// Used by accounting staff to correct payment amounts or status.
// POST body: { payment_log_id, amount, status, notes }
// ─────────────────────────────────────────────────────────────
function editPayment($conn, $data) {
    $log_id    = (int)($data['payment_log_id'] ?? 0);   // FIX: was reading 'payment_log_id' but frontend was sending 'log_id' — both fixed now
    if (!$log_id) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'payment_log_id required']); return;
    }
    $amount    = (float)($data['amount']          ?? 0);   // FIX: was 'amount', frontend now sends 'amount'
    $gcash_ref = trim($data['gcash_reference']    ?? '');
    $gcash_date = trim($data['gcash_date']        ?? '');
    $notes     = trim($data['notes']              ?? '');

    // Keep status as Pending — this is an edit before approval, not a status change
    $stmt = $conn->prepare("UPDATE payment_logs SET gcash_amount = ?, gcash_reference = ?, gcash_date = ?, notes = ? WHERE id = ? AND status = 'Pending'");
    $stmt->bind_param("dsssi", $amount, $gcash_ref, $gcash_date, $notes, $log_id);
    $stmt->execute();

    if ($stmt->affected_rows >= 0) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => true, 'message' => 'Payment updated successfully.']);
    } else {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'Update failed: ' . $conn->error]);
    }
}

// ─────────────────────────────────────────────────────────────
// GET SHS FEE — fee preview for SHS students
// SHS is tuition-free (gov't) unless student is a paying enrollee.
// GET ?action=get_shs_fee&student_type=New&discount=0&has_installment=0
// ─────────────────────────────────────────────────────────────
function getSHSFee($conn) {
    $studentType    = trim($_GET['student_type']  ?? 'New');
    $discount       = (float)($_GET['discount']   ?? 0);
    $hasInstallment = (bool)(int)($_GET['has_installment'] ?? 0);
    $sid            = (int)($_GET['student_id'] ?? 0);

    $fc_shs   = loadFeeConfig($conn, 'SHS');
    $inst_fee = $hasInstallment ? (float)($fc_shs['installment_fee']['value'] ?? 750) : 0.00;

    // SHS Transferee: flat rate (configurable)
    if ($studentType === 'Transferee') {
        $flat_rate = (float)($fc_shs['transferee_flat_rate']['value'] ?? 20000);
        // Add any extra SHS fees and build line items
        $std_shs       = ['transferee_flat_rate','installment_fee'];
        $extra_shs_list = [];
        foreach ($fc_shs as $fk => $frow) {
            if (!in_array($fk, $std_shs)) {
                $line_amt        = (float)$frow['value'];
                $flat_rate      += $line_amt;
                $extra_shs_list[] = [
                    'fee_key'    => $fk,
                    'fee_label'  => $frow['fee_label'],
                    'is_per_unit'=> 0,
                    'rate'       => (float)$frow['value'],
                    'amount'     => $line_amt,
                ];
            }
        }
        $total = $flat_rate + $inst_fee; // FIX TVET-FLAT-DISCOUNT-01: flat rate is fixed, discount/credits do NOT reduce it
        if ($sid > 0) {
            $semRes5 = $conn->query("SELECT semester FROM students WHERE id=$sid LIMIT 1");
            $sem5 = $conn->real_escape_string(trim(($semRes5 ? $semRes5->fetch_assoc()['semester'] : '') ?? ''));
            // FIX SHS-TRANSFEREE-PLAN-01: Same fix as TVET-TRANSFEREE-PLAN-01.
            // Insert with installment_fee=0; ON DUPLICATE KEY preserves whatever
            // updatePaymentPlan() already wrote so the installment fee is never wiped.
            $conn->query("INSERT INTO tuition_fees
                (student_id,units,tuition_fee,miscellaneous_fee,registration_fee,
                 laboratory_fee,energy_fee,subtotal,discount,installment_fee,total_assessment,semester)
                VALUES ($sid,0,$flat_rate,0,0,0,0,$flat_rate,0,0,$flat_rate,'$sem5')
                ON DUPLICATE KEY UPDATE
                    units=0,tuition_fee=$flat_rate,miscellaneous_fee=0,
                    registration_fee=0,laboratory_fee=0,energy_fee=0,
                    subtotal=$flat_rate,discount=0,
                    installment_fee=installment_fee,
                    total_assessment=GREATEST($flat_rate, subtotal + installment_fee),
                    semester='$sem5',updated_at=NOW()");
        }
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success'=>true,'isFree'=>false,'fees'=>[
            'tuitionFee'=>$flat_rate,'miscellaneousFee'=>0,'registrationFee'=>0,
            'laboratoryFee'=>0,'energyFee'=>0,
            'extraFees'=>$extra_shs_list,
            'subtotal'=>$flat_rate,
            'discount'=>0,'installmentFee'=>$inst_fee,
            'totalAssessment'=>$total,'shsFlatRate'=>true]]);
        return;
    }

    // SHS New / Old: FREE (K-12 Government Subsidy)
    if ($sid > 0) { $conn->query("DELETE FROM tuition_fees WHERE student_id=$sid"); }
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success'=>true,'isFree'=>true,'fees'=>[
        'tuitionFee'=>0,'miscellaneousFee'=>0,'registrationFee'=>0,
        'laboratoryFee'=>0,'energyFee'=>0,'extraFees'=>[],'subtotal'=>0,
        'discount'=>0,'installmentFee'=>0,'totalAssessment'=>0,'shsFlatRate'=>false]]);
}

// ─────────────────────────────────────────────────────────────
// GET TVET FEE — fee preview for TVET programs
// TESDA-funded TVET programs may be free; others have fees.
// GET ?action=get_tvet_fee&program=Cookery+NCII&student_type=New
// ─────────────────────────────────────────────────────────────
function getTVETFee($conn) {
    $programName = trim($_GET['program']      ?? '');
    $studentType = trim($_GET['student_type'] ?? 'New');
    $discount    = (float)($_GET['discount']  ?? 0);
    $hasInst     = (bool)(int)($_GET['has_installment'] ?? 0);
    $sid         = (int)($_GET['student_id'] ?? 0);

    $fc_tvet  = loadFeeConfig($conn, 'TVET');
    $inst_fee = $hasInst ? (float)($fc_tvet['installment_fee']['value'] ?? 750) : 0.00; // FIX TVET-INST-01: was 500

    // ── TVET Transferee: flat rate ₱20,000 (same as SHS transferee) ──
    if ($studentType === 'Transferee') {
        // Reuse the SHS transferee_flat_rate from fee_config (or TVET if set separately)
        $fc_shs    = loadFeeConfig($conn, 'SHS');
        $flat_rate = (float)($fc_tvet['transferee_flat_rate']['value']
                     ?? $fc_shs['transferee_flat_rate']['value']
                     ?? 20000);
        // FIX TVET-TRANSFEREE-PLAN-01: Mirror College flow — save subtotal=flat_rate only.
        // installment_fee starts at 0 here; updatePaymentPlan() will add the correct fee
        // later when the student picks their plan (same as College getFeePreview does).
        // Old code always overwrote installment_fee with $inst_fee (always 0 on first load),
        // and ON DUPLICATE KEY also overwrote it — so updatePaymentPlan()'s update was wiped
        // every time the fee preview re-loaded, leaving installment_fee permanently ₱0.
        $total = $flat_rate + $inst_fee;
        if ($sid > 0) {
            $semRes6 = $conn->query("SELECT semester FROM students WHERE id=$sid LIMIT 1");
            $sem6 = $conn->real_escape_string(trim(($semRes6 ? $semRes6->fetch_assoc()['semester'] : '') ?? ''));
            $conn->query("INSERT INTO tuition_fees
                (student_id,units,tuition_fee,miscellaneous_fee,registration_fee,
                 laboratory_fee,energy_fee,subtotal,discount,installment_fee,total_assessment,semester)
                VALUES ($sid,0,$flat_rate,0,0,0,0,$flat_rate,0,0,$flat_rate,'$sem6')
                ON DUPLICATE KEY UPDATE
                    units=0,tuition_fee=$flat_rate,miscellaneous_fee=0,
                    registration_fee=0,laboratory_fee=0,energy_fee=0,
                    subtotal=$flat_rate,discount=0,
                    installment_fee=installment_fee,
                    total_assessment=GREATEST($flat_rate, subtotal + installment_fee),
                    semester='$sem6',updated_at=NOW()");
        }
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => true, 'isFree' => false, 'fees' => [
            'tuitionFee'      => $flat_rate, 'miscellaneousFee' => 0, 'registrationFee' => 0,
            'laboratoryFee'   => 0, 'energyFee' => 0, 'extraFees' => [],
            'subtotal'        => $flat_rate,
            'discount'        => 0, 'installmentFee' => $inst_fee,
            'totalAssessment' => $total, 'tvetFlatRate' => true,
        ]]);
        return;
    }

    // ── TVET New / Old: check if NC (free) or Diploma (paid) ──
    // FIX TVET-FREE-01: Use programs.department as primary signal.
    // 'Short Programs(NC)' department = FREE (TESDA-funded NC programs).
    // 'Collge Diploma' (sic) department = PAID (2yr/3yr diploma programs).
    // Name-based NC detection as secondary fallback for programs not in DB.
    $isFree  = false;
    $pnUpper = strtoupper($programName);

    // Primary: check programs table by name or code
    $progDeptRes = $conn->query(
        "SELECT department FROM programs
         WHERE name = '" . $conn->real_escape_string($programName) . "'
            OR code = '" . $conn->real_escape_string($programName) . "'
         LIMIT 1"
    );
    $progDeptRow = $progDeptRes ? $progDeptRes->fetch_assoc() : null;
    if ($progDeptRow) {
        $dept = strtolower(trim($progDeptRow['department'] ?? ''));
        // 'short programs(nc)' = free NC; 'collge diploma' (typo in DB) = paid diploma
        $isFree = str_contains($dept, 'short programs') || str_contains($dept, 'nc)');
    } else {
        // Fallback: name-based detection
        if (str_contains($pnUpper, 'NCII') || str_contains($pnUpper, 'NCIII') ||
            str_contains($pnUpper, 'NC II') || str_contains($pnUpper, 'NC III') ||
            str_contains($pnUpper, ' NC ') || preg_match('/\bNC\s*[IVXLCD]+\b/i', $programName)) {
            $isFree = true;
        }
    }

    // FIX TVET-FREE-02: For free NC students, delete any stale tuition_fees row
    // (same as getSHSFee does). Without this, a stale row from a previous attempt
    // causes getPendingPayments / getStudentContext to show a non-zero assessment.
    if ($isFree && $sid > 0) {
        $conn->query("DELETE FROM tuition_fees WHERE student_id = $sid");
        // Also auto-approve free TVET students so they proceed directly to enrollment
        $conn->query("UPDATE students SET approval_status='Approved', payment_status='Free', enrollment_status='Enrolled'
                       WHERE id=$sid AND student_type != 'Transferee' AND approval_status != 'Approved'");
    }

    $misc_fee = (float)($fc_tvet['misc_fee']['value'] ?? 3500);
    $reg_fee  = (float)($fc_tvet['reg_fee']['value']  ?? 500);
    // Any extra TVET fees — build list
    $std_tvet        = ['misc_fee', 'reg_fee', 'installment_fee', 'transferee_flat_rate'];
    $extra_tvet      = 0.00;
    $extra_tvet_list = [];
    foreach ($fc_tvet as $fk => $frow) {
        if (!in_array($fk, $std_tvet)) {
            $line_amt         = (float)$frow['value'];
            $extra_tvet      += $line_amt;
            $extra_tvet_list[] = [
                'fee_key'    => $fk,
                'fee_label'  => $frow['fee_label'],
                'is_per_unit'=> 0,
                'rate'       => (float)$frow['value'],
                'amount'     => $line_amt,
            ];
        }
    }
    $subtotal = $misc_fee + $reg_fee + $extra_tvet;
    $total    = $isFree ? 0.00 : max(0, $subtotal - $discount + $inst_fee);

    // FIX TVET-DIPLOMA-01: Save tuition_fees for paid TVET diploma students
    // so Accounting can see correct assessment in getPendingPayments/SOA.
    if (!$isFree && $sid > 0 && $total > 0) {
        $semResTVET = $conn->query("SELECT semester FROM students WHERE id=$sid LIMIT 1");
        $semTVET = $conn->real_escape_string(trim(($semResTVET ? $semResTVET->fetch_assoc()['semester'] : '') ?? ''));
        $conn->query("INSERT INTO tuition_fees
            (student_id,units,tuition_fee,miscellaneous_fee,registration_fee,
             laboratory_fee,energy_fee,subtotal,discount,installment_fee,total_assessment,semester)
            VALUES ($sid,0,0,$misc_fee,$reg_fee,0,0,$subtotal,$discount,$inst_fee,$total,'$semTVET')
            ON DUPLICATE KEY UPDATE
                units=0,tuition_fee=0,miscellaneous_fee=$misc_fee,
                registration_fee=$reg_fee,laboratory_fee=0,energy_fee=0,
                subtotal=$subtotal,discount=$discount,
                installment_fee=$inst_fee,total_assessment=$total,
                semester='$semTVET',updated_at=NOW()");
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success' => true,
        'isFree'  => $isFree,
        'fees'    => [
            'tuitionFee'       => 0,
            'miscellaneousFee' => $isFree ? 0 : $misc_fee,
            'registrationFee'  => $isFree ? 0 : $reg_fee,
            'laboratoryFee'    => 0,
            'energyFee'        => 0,
            'extraFees'        => $isFree ? [] : $extra_tvet_list,
            'subtotal'         => $isFree ? 0 : $subtotal,
            'discount'         => $discount,
            'installmentFee'   => $isFree ? 0 : $inst_fee,
            'totalAssessment'  => $total,
        ],
    ]);
}
// ================================================================
// FEE CONFIG API — GET / SAVE / ADD / DELETE
// ================================================================

/** GET ?action=get_fee_config&category=College|SHS|TVET */
function getFeeConfig(mysqli $conn): void {
    $categories = ['College', 'SHS', 'TVET'];
    $out = [];
    foreach ($categories as $cat) {
        $rows = array_values(loadFeeConfig($conn, $cat));
        // Cast numeric fields so JSON encodes them as numbers, not strings
        foreach ($rows as &$row) {
            $row['value']       = (float)$row['value'];
            $row['is_per_unit'] = (int)$row['is_per_unit'];
            $row['is_active']   = (int)$row['is_active'];
            $row['sort_order']  = (int)$row['sort_order'];
        }
        $out[$cat] = $rows;
    }
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'config' => $out]);
}

/** POST ?action=save_fee_config  body: {updates:[{id,value,fee_label,description,is_per_unit}]} */
function saveFeeConfig(mysqli $conn, array $data): void {
    $updates = $data['updates'] ?? [];
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (empty($updates)) { echo json_encode(['success' => false, 'message' => 'No updates provided']); return; }
    $saved = 0;
    foreach ($updates as $u) {
        $id        = (int)($u['id']          ?? 0);
        $val       = (float)($u['value']      ?? 0);
        $lbl       = trim($u['fee_label']    ?? '');
        $desc      = trim($u['description']  ?? '');
        $isPerUnit = (int)(bool)($u['is_per_unit'] ?? 0);
        if ($id <= 0) continue;
        $conn->query("UPDATE fee_config SET value=$val, fee_label='$lbl', description='$desc', is_per_unit=$isPerUnit WHERE id=$id");
        $saved++;
    }
    logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'SAVE_FEE_CONFIG', 'fee_config', 0,
        "$saved fee config(s) updated");
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'saved' => $saved, 'message' => "$saved fee(s) updated. New rates apply to all future enrollments."]);
}

/** POST ?action=add_fee_config  body: {category, fee_key, fee_label, value, is_per_unit, applies_to, description} */
function addFeeConfig(mysqli $conn, array $data): void {
    $cat      = trim($data['category']    ?? 'College');
    $rawKey   = strtolower(trim($data['fee_key']    ?? ''));
    $key      = preg_replace('/[^a-z0-9_]/', '_', $rawKey);
    $label    = trim($data['fee_label']   ?? '');
    $val      = (float)($data['value']       ?? 0);
    $perUnit  = (int)(bool)($data['is_per_unit']  ?? 0);
    $appTo    = trim($data['applies_to']  ?? 'All');
    $desc     = trim($data['description'] ?? '');

    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$key || !$label) { echo json_encode(['success' => false, 'message' => 'fee_key and fee_label are required']); return; }

    $maxSortRes = $conn->prepare("SELECT COALESCE(MAX(sort_order),0)+1 AS s FROM fee_config WHERE category = ?");
    $maxSortRes->bind_param('s', $cat);
    $maxSortRes->execute();
    $maxSort = (int)(($maxSortRes->get_result()->fetch_assoc()['s'] ?? 1));
    $maxSortRes->close();

    $insStmt = $conn->prepare("INSERT INTO fee_config (category,fee_key,fee_label,value,is_per_unit,applies_to,description,sort_order) VALUES (?,?,?,?,?,?,?,?)");
    $insStmt->bind_param('sssdiisi', $cat, $key, $label, $val, $perUnit, $appTo, $desc, $maxSort);
    $insStmt->execute();
    $newId = (int)$conn->insert_id;
    $insStmt->close();
    if ($newId) {
        logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'ADD_FEE_CONFIG', 'fee_config', $newId,
            "Added fee config '$label' (₱$val) for category '$cat'");
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Fee added successfully.']);
    } else {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'Failed to add fee (key may already exist): ' . $conn->error]);
    }
}

/** POST ?action=delete_fee_config  body: {id} */
function deleteFeeConfig(mysqli $conn, array $data): void {
    $id = (int)($data['id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$id) { echo json_encode(['success' => false, 'message' => 'id required']); return; }
    $conn->query("UPDATE fee_config SET is_active=0 WHERE id=$id");
    logAuditShared($conn, $GLOBALS['authUser'] ?? null, 'DELETE_FEE_CONFIG', 'fee_config', $id,
        "Deactivated fee config ID $id");
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'message' => 'Fee removed.']);
}

function correctVerifiedPayment($conn, $data) {
    $log_id     = (int)($data['log_id']     ?? 0);
    $student_id = (int)($data['student_id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$log_id || !$student_id) { echo json_encode(['success'=>false,'message'=>'log_id and student_id required']); return; }
    $logRow = (($_r=$conn->query("SELECT payment_method, status FROM payment_logs WHERE id=$log_id LIMIT 1")) ? $_r->fetch_assoc() : null);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$logRow) { echo json_encode(['success'=>false,'message'=>'Payment log not found']); return; }
    while (ob_get_level() > 0) { ob_end_clean(); }
    if ($logRow['status'] !== 'Verified') { echo json_encode(['success'=>false,'message'=>'Only Verified payments can be corrected']); return; }
    $isCash = strtolower($logRow['payment_method'] ?? '') === 'cash';
    $notes  = trim($data['notes'] ?? '');
    if ($isCash) {
        $amount = (float)($data['cash_amount'] ?? 0);
        $date   = trim($data['cash_date'] ?? date('Y-m-d'));
        while (ob_get_level() > 0) { ob_end_clean(); }
        if ($amount <= 0) { echo json_encode(['success'=>false,'message'=>'Amount must be > 0']); return; }
        $stmt = $conn->prepare("UPDATE payment_logs SET gcash_amount=?, gcash_date=?, notes=? WHERE id=?");
        $stmt->bind_param("dssi", $amount, $date, $notes, $log_id);
    } else {
        $amount    = (float)($data['gcash_amount']  ?? 0);
        $date      = trim($data['gcash_date']       ?? date('Y-m-d'));
        $gcash_ref = trim($data['gcash_reference']  ?? '');
        $stmt = $conn->prepare("UPDATE payment_logs SET gcash_reference=?, gcash_amount=?, gcash_date=?, notes=? WHERE id=?");
        $stmt->bind_param("sdssi", $gcash_ref, $amount, $date, $notes, $log_id);
    }
    $stmt->execute();
    while (ob_get_level() > 0) { ob_end_clean(); }
    if ($stmt->error) { echo json_encode(['success'=>false,'message'=>'DB error: '.$stmt->error]); return; }
    // Sync installment_payments
    $ins = $conn->prepare("UPDATE installment_payments SET amount=?, payment_date=?, notes=? WHERE payment_log_id=?");
    $ins->bind_param("dssi", $amount, $date, $notes, $log_id);
    $ins->execute();
    // Re-sync student payment_status
    $tp  = (float)(($_r=$conn->query("SELECT COALESCE(SUM(amount),0) AS tp FROM installment_payments ip JOIN students _st ON _st.id=ip.student_id WHERE ip.student_id=$student_id AND ip.semester=_st.semester")) ? $_r->fetch_assoc() : null)['tp'];
    $ta  = (float)((($_r=$conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id=$student_id LIMIT 1")) ? $_r->fetch_assoc()['total_assessment'] : null) ?? 0);
    // FIX: 'Partial' not valid enum. Use 'Pending' for remaining balance.
    $ns  = ($ta > 0 && $tp >= $ta) ? 'Paid' : 'Pending';
    $conn->query("UPDATE students SET payment_status='$ns' WHERE id=$student_id");
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success'=>true,'message'=>'Payment corrected successfully.']);
}

// ================================================================
// INCOME REPORT GENERATOR
// Weekly / Monthly / Yearly income from student tuition payments
// ================================================================

/**
 * GET ?action=get_income_report&period=weekly|monthly|yearly
 *     &date_from=YYYY-MM-DD&date_to=YYYY-MM-DD
 *     &year=YYYY&month=MM&week_start=YYYY-MM-DD
 * Returns income broken down by day/week/month + totals.
 */
function getIncomeReport($conn) {
    $period    = trim($_GET['period']     ?? 'monthly');
    $year      = (int)($_GET['year']      ?? date('Y'));
    $month     = (int)($_GET['month']     ?? date('n'));
    $dateFrom  = trim($_GET['date_from']  ?? '');
    $dateTo    = trim($_GET['date_to']    ?? '');
    $weekStart = trim($_GET['week_start'] ?? '');

    // Determine actual date range
    switch ($period) {
        case 'weekly':
            if ($weekStart) {
                $from = $weekStart;
                $to   = date('Y-m-d', strtotime($weekStart . ' +6 days'));
            } else {
                $from = date('Y-m-d', strtotime('monday this week'));
                $to   = date('Y-m-d', strtotime('sunday this week'));
            }
            $groupExpr  = 'DATE(p.created_at)';
            $labelExpr  = 'DATE(p.created_at)';
            $groupAlias = 'day';
            break;

        case 'yearly':
            $from       = "$year-01-01";
            $to         = "$year-12-31";
            $groupExpr  = 'MONTH(p.created_at)';
            $labelExpr  = 'DATE_FORMAT(p.created_at, "%b %Y")';
            $groupAlias = 'month';
            break;

        case 'monthly':
        default:
            $period     = 'monthly';
            $from       = date('Y-m-01', mktime(0,0,0,$month,1,$year));
            $to         = date('Y-m-t',  mktime(0,0,0,$month,1,$year));
            $groupExpr  = 'WEEK(p.created_at, 1)';
            $labelExpr  = 'CONCAT("Week ", WEEK(p.created_at,1)-WEEK(DATE_FORMAT(p.created_at,"%%Y-%%m-01"),1)+1, " (", DATE_FORMAT(MIN(p.created_at),"%%b %%d"), ")")';
            $groupAlias = 'week';
            break;
    }

    // Override with custom range if provided
    if ($dateFrom && $dateTo) { $from = $dateFrom; $to = $dateTo; }

    $fromEsc = $from;
    $toEsc   = $to;

    // Main breakdown query — use installment_payments as source of truth for actual cash collected
    $breakdownRes = $conn->query("
        SELECT
            $groupExpr AS period_key,
            $labelExpr AS period_label,
            COUNT(DISTINCT p.id) AS transaction_count,
            COUNT(DISTINCT p.student_id) AS student_count,
            SUM(p.amount) AS total_amount,
            SUM(CASE WHEN p.payment_method='Cash'  THEN p.amount ELSE 0 END) AS cash_amount,
            SUM(CASE WHEN p.payment_method='GCash' THEN p.amount ELSE 0 END) AS gcash_amount,
            SUM(CASE WHEN p.payment_method='Check' THEN p.amount ELSE 0 END) AS check_amount
        FROM installment_payments p
        WHERE DATE(p.created_at) BETWEEN '$fromEsc' AND '$toEsc'
        GROUP BY period_key
        ORDER BY MIN(p.created_at) ASC
    ");

    $breakdown = [];
    $grandTotal = 0;
    $grandCount = 0;
    while ($r = $breakdownRes->fetch_assoc()) {
        $breakdown[] = [
            'periodKey'       => $r['period_key'],
            'periodLabel'     => $r['period_label'],
            'transactionCount'=> (int)$r['transaction_count'],
            'studentCount'    => (int)$r['student_count'],
            'totalAmount'     => (float)$r['total_amount'],
            'cashAmount'      => (float)$r['cash_amount'],
            'gcashAmount'     => (float)$r['gcash_amount'],
            'checkAmount'     => (float)$r['check_amount'],
        ];
        $grandTotal += (float)$r['total_amount'];
        $grandCount += (int)$r['transaction_count'];
    }

    // Payment method breakdown
    $methodRes = $conn->query("
        SELECT payment_method,
               COUNT(*) AS txn_count,
               SUM(amount) AS total
        FROM installment_payments
        WHERE DATE(created_at) BETWEEN '$fromEsc' AND '$toEsc'
        GROUP BY payment_method
    ");
    $byMethod = [];
    while ($r = $methodRes->fetch_assoc()) {
        $byMethod[] = ['method' => $r['payment_method'], 'count' => (int)$r['txn_count'], 'total' => (float)$r['total']];
    }

    // Exam period breakdown
    $periodRes = $conn->query("
        SELECT COALESCE(exam_period,'General') AS exam_period,
               COUNT(*) AS txn_count,
               SUM(amount) AS total
        FROM installment_payments
        WHERE DATE(created_at) BETWEEN '$fromEsc' AND '$toEsc'
        GROUP BY exam_period
        ORDER BY total DESC
    ");
    $byExamPeriod = [];
    while ($r = $periodRes->fetch_assoc()) {
        $byExamPeriod[] = ['period' => $r['exam_period'], 'count' => (int)$r['txn_count'], 'total' => (float)$r['total']];
    }

    // Top paying students
    $topRes = $conn->query("
        SELECT s.student_number, s.first_name, s.last_name, s.program,
               SUM(p.amount) AS total_paid,
               COUNT(p.id) AS txn_count
        FROM installment_payments p
        JOIN students s ON s.id = p.student_id
        WHERE DATE(p.created_at) BETWEEN '$fromEsc' AND '$toEsc'
        GROUP BY p.student_id
        ORDER BY total_paid DESC
        LIMIT 10
    ");
    $topStudents = [];
    while ($r = $topRes->fetch_assoc()) {
        $topStudents[] = [
            'studentNumber' => $r['student_number'],
            'name'          => $r['first_name'].' '.$r['last_name'],
            'program'       => $r['program'],
            'totalPaid'     => (float)$r['total_paid'],
            'txnCount'      => (int)$r['txn_count'],
        ];
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'      => true,
        'period'       => $period,
        'dateFrom'     => $from,
        'dateTo'       => $to,
        'grandTotal'   => round($grandTotal, 2),
        'grandCount'   => $grandCount,
        'breakdown'    => $breakdown,
        'byMethod'     => $byMethod,
        'byExamPeriod' => $byExamPeriod,
        'topStudents'  => $topStudents,
    ]);
}

/**
 * GET ?action=get_income_summary
 * High-level KPIs: today, this week, this month, this year + YoY comparison.
 */
function getIncomeSummary($conn) {
    $today     = date('Y-m-d');
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $weekEnd   = date('Y-m-d', strtotime('sunday this week'));
    $monthStart= date('Y-m-01');
    $monthEnd  = date('Y-m-t');
    $yearStart = date('Y-01-01');
    $yearEnd   = date('Y-12-31');
    $prevYrS   = date('Y-01-01', strtotime('-1 year'));
    $prevYrE   = date('Y-12-31', strtotime('-1 year'));

    $q = fn($from, $to) => (float)($conn->query(
        "SELECT COALESCE(SUM(amount),0) AS t FROM installment_payments WHERE DATE(created_at) BETWEEN '$from' AND '$to'"
    )->fetch_assoc()['t'] ?? 0);

    $todayAmt   = $q($today, $today);
    $weekAmt    = $q($weekStart, $weekEnd);
    $monthAmt   = $q($monthStart, $monthEnd);
    $yearAmt    = $q($yearStart, $yearEnd);
    $prevYrAmt  = $q($prevYrS, $prevYrE);

    $yoyChange  = $prevYrAmt > 0 ? round(($yearAmt - $prevYrAmt) / $prevYrAmt * 100, 1) : null;

    // Monthly trend for current year (for sparkline)
    $trendRes = $conn->query("
        SELECT MONTH(created_at) AS m, MONTHNAME(created_at) AS label, SUM(amount) AS total
        FROM installment_payments
        WHERE YEAR(created_at) = YEAR(CURDATE())
        GROUP BY MONTH(created_at)
        ORDER BY m ASC
    ");
    $monthlyTrend = [];
    while ($r = $trendRes->fetch_assoc()) {
        $monthlyTrend[] = ['month' => (int)$r['m'], 'label' => $r['label'], 'total' => (float)$r['total']];
    }

    // Outstanding balances
    $outstandingRes = $conn->query("
        SELECT COALESCE(SUM(
            GREATEST(0, tf.total_assessment - COALESCE(paid.total_paid, 0))
        ), 0) AS outstanding
        FROM tuition_fees tf
        JOIN students s ON s.id = tf.student_id AND s.enrollment_status = 'Enrolled'
        LEFT JOIN (
            SELECT student_id, SUM(amount) AS total_paid
            FROM installment_payments
            GROUP BY student_id
        ) paid ON paid.student_id = tf.student_id
    ");
    $outstanding = (float)($outstandingRes->fetch_assoc()['outstanding'] ?? 0);

    // Total enrolled students with fees
    $enrolledCount = (int)(($_r=$conn->query("SELECT COUNT(*) AS c FROM students WHERE enrollment_status='Enrolled'")) ? $_r->fetch_assoc() : null)['c'];

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'       => true,
        'today'         => round($todayAmt, 2),
        'thisWeek'      => round($weekAmt, 2),
        'thisMonth'     => round($monthAmt, 2),
        'thisYear'      => round($yearAmt, 2),
        'prevYear'      => round($prevYrAmt, 2),
        'yoyChange'     => $yoyChange,
        'outstanding'   => round($outstanding, 2),
        'enrolledCount' => $enrolledCount,
        'monthlyTrend'  => $monthlyTrend,
    ]);
}

/**
 * GET ?action=get_income_by_program&period=monthly&year=YYYY&month=MM
 * Income grouped by student program.
 */
function getIncomeByProgram($conn) {
    $year  = (int)($_GET['year']  ?? date('Y'));
    $month = (int)($_GET['month'] ?? 0);

    $dateFilter = $month
        ? "YEAR(p.created_at)=$year AND MONTH(p.created_at)=$month"
        : "YEAR(p.created_at)=$year";

    $res = $conn->query("
        SELECT s.program,
               COUNT(DISTINCT p.student_id) AS student_count,
               COUNT(p.id) AS txn_count,
               SUM(p.amount) AS total_amount
        FROM installment_payments p
        JOIN students s ON s.id = p.student_id
        WHERE $dateFilter
        GROUP BY s.program
        ORDER BY total_amount DESC
    ");

    $programs = [];
    $grandTotal = 0;
    while ($r = $res->fetch_assoc()) {
        $programs[]  = [
            'program'      => $r['program'] ?: 'Unassigned',
            'studentCount' => (int)$r['student_count'],
            'txnCount'     => (int)$r['txn_count'],
            'totalAmount'  => (float)$r['total_amount'],
        ];
        $grandTotal += (float)$r['total_amount'];
    }

    // Add percentage
    foreach ($programs as &$p) {
        $p['percentage'] = $grandTotal > 0 ? round($p['totalAmount'] / $grandTotal * 100, 1) : 0;
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'    => true,
        'year'       => $year,
        'month'      => $month,
        'grandTotal' => round($grandTotal, 2),
        'programs'   => $programs,
    ]);
}

// ─────────────────────────────────────────────────────────────
// GET PAYMENT DUE DATES
// GET ?action=get_due_dates[&semester=1st+Semester&school_year=2025-2026]
//
// Returns the installment due date ranges set by Accounting for a
// specific semester + school year.  When semester/school_year are
// supplied the lookup is scoped to that term so the SOA never shows
// due dates from a previous semester.
//
// Storage key format in sys_config:
//   payment_due_dates:{semester_slug}:{school_year}   (per-term key)
//   payment_due_dates                                 (legacy global key, fallback)
//
// Public — no auth needed (SOA needs to show it to students).
// ─────────────────────────────────────────────────────────────
function getPaymentDueDates(mysqli $conn): void {

    // ── Resolve semester + school_year ────────────────────────────────────────
    // Priority 1: explicit query params (passed by SOA / accounting UI)
    // Priority 2: student_id → resolve from students table
    // Priority 3: global fallback (no filter)
    $semester   = trim($_GET['semester']    ?? '');
    $schoolYear = trim($_GET['school_year'] ?? '');

    // FIX DUE-DATE-GET-01 (v2): Always normalise the semester param by stripping
    // any embedded ", AY YYYY-YYYY" tail before building the scoped key.
    //
    // The Angular student component sends BOTH semester (full combined string, e.g.
    // "1st Semester, AY 2027-2028") AND school_year ("2027-2028") at the same time.
    // The old guard `if ($schoolYear === '')` meant the parser never fired when both
    // arrived together, leaving $semester as the full string. The slug builder then
    // produced "1st_semester__ay_2027_2028" instead of "1st_semester", which never
    // matched the stored scoped key "payment_due_dates:1st_semester:2027-2028".
    //
    // New approach: unconditionally parse regardless of whether $schoolYear is set,
    // so the slug is always built from the clean short form ("1st Semester").
    if ($semester !== '') {
        if (preg_match('/^(.+?),\s*AY\s*(\d{4}-\d{4})/i', $semester, $m)) {
            $semester   = trim($m[1]);
            if ($schoolYear === '') $schoolYear = trim($m[2]);
        } elseif (preg_match('/(\d{4}-\d{4})/', $semester, $m)) {
            if ($schoolYear === '') $schoolYear = $m[1];
            $semester = trim(preg_replace('/,?\s*AY\s*\d{4}-\d{4}/i', '', $semester));
        }
    }

    // If a student_id is provided, derive semester/school_year from their record
    $studentId = (int)($_GET['student_id'] ?? 0);
    if ($studentId > 0 && ($semester === '' || $schoolYear === '')) {
        $sRes = $conn->prepare("SELECT semester FROM students WHERE id = ? LIMIT 1");
        $sRes->bind_param('i', $studentId);
        $sRes->execute();
        $sRow = $sRes->get_result()->fetch_assoc();
        $sRes->close();
        $rawSem = trim($sRow['semester'] ?? '');
        // e.g. "1st Semester, AY 2025-2026"
        if (preg_match('/^(.+?),\s*AY\s*(\d{4}-\d{4})/i', $rawSem, $m)) {
            if ($semester === '')   $semester   = trim($m[1]);
            if ($schoolYear === '') $schoolYear = trim($m[2]);
        }
    }

    // ── Build the scoped config key ───────────────────────────────────────────
    // Slug: lowercase, spaces → underscores, strip punctuation except hyphen
    $scopedKey  = '';
    $usedScoped = false;
    if ($semester !== '' && $schoolYear !== '') {
        $semSlug   = preg_replace('/[^a-z0-9_]/', '_', strtolower($semester));
        $yrSlug    = preg_replace('/[^0-9-]/', '', $schoolYear);
        $scopedKey = "payment_due_dates:{$semSlug}:{$yrSlug}";
    }

    // ── Empty defaults (no legacy hard-coded dates bleeding in) ──────────────
    $defaults = [
        'downpayment' => ['label' => 'Downpayment', 'date_range' => ''],
        'prelim'      => ['label' => 'Prelim',      'date_range' => ''],
        'midterm'     => ['label' => 'Midterm',      'date_range' => ''],
        'finals'      => ['label' => 'Finals',       'date_range' => ''],
    ];

    $dates = $defaults;

    // ── Step 1: try scoped key first ──────────────────────────────────────────
    if ($scopedKey !== '') {
        $stmt = $conn->prepare("SELECT config_value FROM sys_config WHERE config_key = ? LIMIT 1");
        $stmt->bind_param('s', $scopedKey);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && !empty($row['config_value'])) {
            $saved = json_decode($row['config_value'], true);
            if (is_array($saved)) {
                $dates     = array_merge($defaults, $saved);
                $usedScoped = true;
            }
        }
    }

    // ── Helper: read the payment_due_dates_active_semester marker ────────────
    // This marker is written by savePaymentDueDates every time accounting saves
    // any semester's due dates.  It holds the scoped config key that the global
    // payment_due_dates key currently mirrors, e.g.:
    //   "payment_due_dates:1st_semester:2027-2028"
    // Using this marker (instead of enrollment_period) decouples due-date reads
    // from the enrollment lifecycle — they stay correct even when the two are
    // out of sync. (FIX DUE-DATE-02 v5)
    $getActiveMarker = function() use ($conn): string {
        $r = $conn->query("SELECT config_value FROM sys_config WHERE config_key = 'payment_due_dates_active_semester' LIMIT 1");
        $row = $r ? $r->fetch_assoc() : null;
        return trim($row['config_value'] ?? '');
    };

    // ── Step 2: scoped key was attempted but not found ───────────────────────
    // Fall back to the global key only when it belongs to the same semester as
    // the student — determined via the payment_due_dates_active_semester marker,
    // NOT enrollment_period (which may be stale / out-of-sync).
    if (!$usedScoped && $scopedKey !== '') {
        // a) Global key — only use if it was saved for this student's semester
        $globalRes = $conn->query("SELECT config_value FROM sys_config WHERE config_key = 'payment_due_dates' LIMIT 1");
        $globalRow = $globalRes ? $globalRes->fetch_assoc() : null;
        if ($globalRow && !empty($globalRow['config_value'])) {
            $globalSaved = json_decode($globalRow['config_value'], true);
            if (is_array($globalSaved)) {
                $hasAnyDate = false;
                foreach ($globalSaved as $gd) { if (!empty($gd['date_range'])) { $hasAnyDate = true; break; } }
                if ($hasAnyDate) {
                    // The global key mirrors the semester recorded in the active marker.
                    // Only apply it when that semester matches the student's own semester.
                    $activeMarker        = $getActiveMarker();
                    $globalMatchesStudent = ($activeMarker !== '' && $activeMarker === $scopedKey);
                    if ($globalMatchesStudent) {
                        $dates = array_merge($defaults, $globalSaved);
                    }
                    // Mismatch → do NOT apply; student gets empty dates for past/other semesters.
                }
            }
        }
        // b) Still blank? Try the scoped key that the active marker points to —
        //    only if it equals the student's own scoped key (same semester).
        $allBlankFb = true;
        foreach ($dates as $d) { if (!empty($d['date_range'])) { $allBlankFb = false; break; } }
        if ($allBlankFb) {
            $activeMarker = $getActiveMarker();
            if ($activeMarker !== '' && $activeMarker === $scopedKey) {
                $r2 = $conn->query("SELECT config_value FROM sys_config WHERE config_key = '" . $conn->real_escape_string($activeMarker) . "' LIMIT 1");
                $r2Row = $r2 ? $r2->fetch_assoc() : null;
                if ($r2Row && !empty($r2Row['config_value'])) {
                    $saved2 = json_decode($r2Row['config_value'], true);
                    if (is_array($saved2)) $dates = array_merge($defaults, $saved2);
                }
            }
            // Mismatch → leave $dates as $defaults (empty) — correct for past semesters
        }
    }

    // ── Step 3: no semester context at all — global key then marker's scoped key ──
    // Truly anonymous request (no student_id, no semester param).
    // Try global key first, then the scoped key the active marker points to.
    if (!$usedScoped && $scopedKey === '') {
        $res = $conn->query("SELECT config_value FROM sys_config WHERE config_key = 'payment_due_dates' LIMIT 1");
        $row = $res ? $res->fetch_assoc() : null;
        if ($row && !empty($row['config_value'])) {
            $saved = json_decode($row['config_value'], true);
            if (is_array($saved)) $dates = array_merge($defaults, $saved);
        }
        $allBlank = true;
        foreach ($dates as $d) { if (!empty($d['date_range'])) { $allBlank = false; break; } }
        if ($allBlank) {
            $activeMarkerAnon = $getActiveMarker();
            if ($activeMarkerAnon !== '') {
                $r3 = $conn->query("SELECT config_value FROM sys_config WHERE config_key = '" . $conn->real_escape_string($activeMarkerAnon) . "' LIMIT 1");
                $r3Row = $r3 ? $r3->fetch_assoc() : null;
                if ($r3Row && !empty($r3Row['config_value'])) {
                    $saved3 = json_decode($r3Row['config_value'], true);
                    if (is_array($saved3)) $dates = array_merge($defaults, $saved3);
                }
            }
        }
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'    => true,
        'dueDates'   => $dates,
        'semester'   => $semester,
        'schoolYear' => $schoolYear,
        'scopedKey'  => $scopedKey ?: 'payment_due_dates',
    ]);
}

// ─────────────────────────────────────────────────────────────
// SAVE PAYMENT DUE DATES
// POST ?action=save_due_dates
// Body: { semester, school_year, downpayment, prelim, midterm, finals }
// Each period: { label, date_range }
//
// Saves under a scoped key  payment_due_dates:{semester_slug}:{year}
// so each semester has its own due dates.  If semester/school_year
// are omitted, saves to the legacy global key (backward-compat).
//
// Only Accounting role can call this.
// ─────────────────────────────────────────────────────────────
function savePaymentDueDates(mysqli $conn): void {
    $data = json_decode(file_get_contents('php://input'), true);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$data) { echo json_encode(['success' => false, 'message' => 'Invalid JSON']); return; }

    // ── Determine config key ──────────────────────────────────────────────────
    $semester   = trim($data['semester']    ?? '');
    $schoolYear = trim($data['school_year'] ?? '');

    // FIX DUE-DATE-SAVE-01 (v2): Unconditionally strip any embedded ", AY YYYY-YYYY"
    // tail from the semester param before building the scoped key — same logic as
    // getPaymentDueDates so both functions always produce identical key strings.
    if ($semester !== '') {
        if (preg_match('/^(.+?),\s*AY\s*(\d{4}-\d{4})/i', $semester, $m)) {
            $semester   = trim($m[1]);
            if ($schoolYear === '') $schoolYear = trim($m[2]);
        } elseif (preg_match('/(\d{4}-\d{4})/', $semester, $m)) {
            if ($schoolYear === '') $schoolYear = $m[1];
            $semester = trim(preg_replace('/,?\s*AY\s*\d{4}-\d{4}/i', '', $semester));
        }
    }

    if ($semester !== '' && $schoolYear !== '') {
        $semSlug    = preg_replace('/[^a-z0-9_]/', '_', strtolower($semester));
        $yrSlug     = preg_replace('/[^0-9-]/', '', $schoolYear);
        $configKey  = "payment_due_dates:{$semSlug}:{$yrSlug}";
    } else {
        // No semester supplied — save only to the global key
        $configKey = 'payment_due_dates';
    }

    // ── Build payload ─────────────────────────────────────────────────────────
    $allowed = ['downpayment', 'prelim', 'midterm', 'finals'];
    $toSave  = [];
    foreach ($allowed as $period) {
        if (isset($data[$period])) {
            $toSave[$period] = [
                'label'      => trim($data[$period]['label']      ?? ucfirst($period)),
                'date_range' => trim($data[$period]['date_range'] ?? ''),
            ];
        }
    }

    $json = json_encode($toSave);

    // Save to the scoped key (semester-specific)
    $jsonStmt = $conn->prepare(
        "INSERT INTO sys_config (config_key, config_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE config_value = ?, updated_at = NOW()"
    );
    $jsonStmt->bind_param('sss', $configKey, $json, $json);
    $jsonStmt->execute();
    $jsonStmt->close();

    // FIX DUE-DATE-SAVE-03 (v2): Always update the global key to mirror the most
    // recently saved semester's due dates, and always write a
    // payment_due_dates_active_semester marker so readers know which semester the
    // global key belongs to.
    //
    // The old approach only updated the global key when the saved semester matched
    // enrollment_period — so if accounting saved dates for a semester that differed
    // from enrollment_period (e.g. a future term), the global key was never updated
    // and students got empty due dates ($isSavingActivePeriod was always false).
    //
    // The new approach: global key = whatever accounting most recently saved.
    // Readers (getPaymentDueDates / _getInstallmentPaymentDates) compare the student's
    // scoped key against payment_due_dates_active_semester — not enrollment_period —
    // to decide whether the global fallback is relevant for that student.
    if ($configKey !== 'payment_due_dates') {
        // Always mirror the global key with what was just saved
        $globalKey  = 'payment_due_dates';
        $globalStmt = $conn->prepare(
            "INSERT INTO sys_config (config_key, config_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE config_value = ?, updated_at = NOW()"
        );
        $globalStmt->bind_param('sss', $globalKey, $json, $json);
        $globalStmt->execute();
        $globalStmt->close();

        // Write the active-semester marker so readers can validate the global key
        // without relying on enrollment_period (which may be stale or out-of-sync).
        $activeSemMarker     = $configKey; // e.g. "payment_due_dates:1st_semester:2027-2028"
        $activeSemMarkerKey  = 'payment_due_dates_active_semester';
        $markerStmt = $conn->prepare(
            "INSERT INTO sys_config (config_key, config_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE config_value = ?, updated_at = NOW()"
        );
        $markerStmt->bind_param('sss', $activeSemMarkerKey, $activeSemMarker, $activeSemMarker);
        $markerStmt->execute();
        $markerStmt->close();
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'    => true,
        'message'    => 'Due dates saved successfully.',
        'dueDates'   => $toSave,
        'configKey'  => $configKey,
        'semester'   => $semester,
        'schoolYear' => $schoolYear,
    ]);
}



// =============================================================================
// FEATURE: Full Scholarship — grant / remove / auto-approve
// =============================================================================

/**
 * GET ?action=get_student_scholarship&student_id=XX
 * Returns active scholarship record + full history for this student.
 */
function getStudentScholarship(mysqli $conn): void {
    $sid = (int)($_GET['student_id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$sid) { echo json_encode(['success'=>false,'message'=>'student_id required']); return; }

    // Active scholarship
    $res = $conn->prepare("SELECT * FROM student_scholarships WHERE student_id=? AND is_active=1 ORDER BY id DESC LIMIT 1");
    $res->bind_param('i', $sid);
    $res->execute();
    $active = $res->get_result()->fetch_assoc();
    $res->close();

    // All-time history
    $hist = $conn->prepare("SELECT * FROM student_scholarships WHERE student_id=? ORDER BY id DESC");
    $hist->bind_param('i', $sid);
    $hist->execute();
    $history = [];
    $histRes = $hist->get_result();
    while ($h = $histRes->fetch_assoc()) $history[] = $h;
    $hist->close();

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success'=>true, 'scholarship'=>$active, 'history'=>$history]);
}

/**
 * GET ?action=get_scholarship_history&student_id=XX
 * Returns full scholarship history (all semesters, active + revoked).
 */
function getScholarshipHistory(mysqli $conn): void {
    $sid = (int)($_GET['student_id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$sid) { echo json_encode(['success'=>false,'message'=>'student_id required']); return; }

    $stmt = $conn->prepare(
        "SELECT ss.*,
                s.first_name, s.last_name, s.student_number, s.program
         FROM student_scholarships ss
         JOIN students s ON s.id = ss.student_id
         WHERE ss.student_id = ?
         ORDER BY ss.id DESC"
    );
    $stmt->bind_param('i', $sid);
    $stmt->execute();
    $rows = [];
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $rows[] = $row;
    $stmt->close();

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success'=>true, 'history'=>$rows, 'total'=>count($rows)]);
}

/**
 * GET ?action=get_subject_fee_log&student_id=XX (optional)
 * Returns subject add/drop fee impact log visible to Accounting.
 * If student_id omitted, returns all recent entries (last 200).
 */
function getSubjectFeeLog(mysqli $conn): void {
    $sid   = (int)($_GET['student_id'] ?? 0);
    $limit = min((int)($_GET['limit'] ?? 100), 500);

    // Auto-create table if migrate.php hasn't been run yet
    $conn->query("
        CREATE TABLE IF NOT EXISTS subject_fee_log (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            student_id       INT NOT NULL,
            course_id        INT NOT NULL,
            course_code      VARCHAR(20)   DEFAULT NULL,
            course_name      VARCHAR(150)  DEFAULT NULL,
            action           ENUM('Add','Drop') NOT NULL DEFAULT 'Add',
            subject_type     VARCHAR(50)   DEFAULT NULL,
            course_category  VARCHAR(50)   DEFAULT NULL,
            units            INT           NOT NULL DEFAULT 0,
            lec_units        INT           NOT NULL DEFAULT 0,
            lab_units        INT           NOT NULL DEFAULT 0,
            tuition_impact   DECIMAL(10,2) NOT NULL DEFAULT 0,
            lab_fee_impact   DECIMAL(10,2) NOT NULL DEFAULT 0,
            energy_impact    DECIMAL(10,2) NOT NULL DEFAULT 0,
            total_impact     DECIMAL(10,2) NOT NULL DEFAULT 0,
            semester         VARCHAR(100)  DEFAULT NULL,
            reason           VARCHAR(255)  DEFAULT NULL,
            added_by_role    VARCHAR(50)   DEFAULT NULL,
            added_by_email   VARCHAR(150)  DEFAULT NULL,
            performed_by     INT           DEFAULT NULL,
            created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_student (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    if ($sid) {
        $stmt = $conn->prepare(
            "SELECT sfl.*,
                    s.first_name, s.last_name, s.student_number, s.program, s.year_level
             FROM subject_fee_log sfl
             JOIN students s ON s.id = sfl.student_id
             WHERE sfl.student_id = ?
             ORDER BY sfl.created_at DESC
             LIMIT ?"
        );
        $stmt->bind_param('ii', $sid, $limit);
    } else {
        $stmt = $conn->prepare(
            "SELECT sfl.*,
                    s.first_name, s.last_name, s.student_number, s.program, s.year_level
             FROM subject_fee_log sfl
             JOIN students s ON s.id = sfl.student_id
             ORDER BY sfl.created_at DESC
             LIMIT ?"
        );
        $stmt->bind_param('i', $limit);
    }

    $stmt->execute();
    $rows = [];
    $res  = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $stmt->close();

    // BUG-FEELOG-01 FIX: Removed fallback that pulled from add_drop_requests when
    // subject_fee_log was empty. That caused ALL add/drop requests (including pending,
    // rejected, and unprocessed ones) to appear in the fee log.
    //
    // subject_fee_log entries are only written by _logSubjectFeeImpact() when the
    // Registrar gives final approval. An empty log = no approved Add/Drop activity yet.
    // The "Pending Add/Drop Requests" tab (separate) shows items awaiting approval.

    // Aggregate totals
    $totalTuitionImpact = array_sum(array_column($rows, 'tuition_impact'));
    $totalLabImpact     = array_sum(array_column($rows, 'lab_fee_impact'));
    $totalNetImpact     = array_sum(array_column($rows, 'total_impact'));

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'              => true,
        'logs'                 => $rows,
        'count'                => count($rows),
        'total_tuition_impact' => round($totalTuitionImpact, 2),
        'total_lab_impact'     => round($totalLabImpact, 2),
        'total_net_impact'     => round($totalNetImpact, 2),
    ]);
}

/**
 * GET ?action=backfill_fee_log
 *
 * BUG-ADDDROP-01 BACKFILL: Retroactively writes subject_fee_log entries for
 * any add_drop_requests row where accounting_status='Approved' but no matching
 * fee log entry exists yet (i.e., requests approved before the fix was deployed).
 *
 * Safe to call multiple times — skips requests that already have a log entry.
 * Returns count of entries written.
 */
function backfillSubjectFeeLog(mysqli $conn): void {
    // Ensure both tables exist
    $conn->query("CREATE TABLE IF NOT EXISTS subject_fee_log (
        id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL, course_id INT NOT NULL,
        course_code VARCHAR(20) DEFAULT NULL, course_name VARCHAR(150) DEFAULT NULL,
        action ENUM('Add','Drop') NOT NULL DEFAULT 'Add',
        subject_type VARCHAR(50) DEFAULT NULL, course_category VARCHAR(50) DEFAULT NULL,
        units INT DEFAULT 0, lec_units INT DEFAULT 0, lab_units INT DEFAULT 0,
        tuition_impact DECIMAL(10,2) NOT NULL DEFAULT 0,
        lab_fee_impact DECIMAL(10,2) NOT NULL DEFAULT 0,
        energy_impact DECIMAL(10,2) NOT NULL DEFAULT 0,
        total_impact DECIMAL(10,2) NOT NULL DEFAULT 0,
        semester VARCHAR(100) DEFAULT NULL, reason VARCHAR(255) DEFAULT NULL,
        added_by_role VARCHAR(50) DEFAULT NULL, added_by_email VARCHAR(150) DEFAULT NULL,
        performed_by INT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Find all accounting-approved requests that have NO fee log entry yet.
    // We match on student_id + course_id + action to detect existing entries.
    $res = $conn->query("
        SELECT r.id, r.student_id, r.course_id, r.request_type,
               r.accounting_reviewed_at, r.accounting_notes,
               r.fee_impact, r.new_total_assessment
        FROM add_drop_requests r
        WHERE r.accounting_status = 'Approved'
          AND NOT EXISTS (
              SELECT 1 FROM subject_fee_log sfl
              WHERE sfl.student_id = r.student_id
                AND sfl.course_id  = r.course_id
                AND sfl.action     = r.request_type
          )
        ORDER BY r.accounting_reviewed_at ASC
    ");

    if (!$res) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'Query failed: ' . $conn->error]);
        return;
    }

    // Fetch fee rates once
    $fcRes = $conn->query("SELECT fee_key, value FROM fee_config WHERE category='College' AND is_active=1");
    $fc = [];
    if ($fcRes) while ($fr = $fcRes->fetch_assoc()) $fc[$fr['fee_key']] = (float)$fr['value'];
    $tuitionRate = $fc['tuition_rate_per_unit'] ?? 650;
    $energyRate  = $fc['energy_rate_per_unit']  ?? 63;
    $labFeeRate  = $fc['lab_fee_per_room']       ?? 1900;

    $written = 0;
    $skipped = 0;
    $errors  = [];

    while ($r = $res->fetch_assoc()) {
        $sid = (int)$r['student_id'];
        $cid = (int)$r['course_id'];
        $action = $r['request_type']; // 'Add' or 'Drop'
        $rid = (int)$r['id'];

        // Fetch course details
        $cRes = $conn->prepare("SELECT code, name, credits, lec_units, lab_units, is_lab FROM courses WHERE id=? LIMIT 1");
        $cRes->bind_param('i', $cid);
        $cRes->execute();
        $c = $cRes->get_result()->fetch_assoc();
        $cRes->close();
        if (!$c) { $skipped++; $errors[] = "request #$rid: course $cid not found"; continue; }

        // Fetch student semester
        $semR = $conn->prepare("SELECT semester FROM students WHERE id=? LIMIT 1");
        $semR->bind_param('i', $sid);
        $semR->execute();
        $semRow = $semR->get_result()->fetch_assoc();
        $semR->close();
        $semester = $semRow['semester'] ?? '';

        $units    = (int)($c['credits']   ?? 0);
        $lecUnits = (int)($c['lec_units'] ?? 0);
        $labUnits = (int)($c['lab_units'] ?? 0);
        $isLab    = (int)($c['is_lab']    ?? 0);

        $subjectType = ($isLab || $labUnits > 0) ? 'Laboratory' : 'Lecture';
        $code = strtoupper($c['code'] ?? '');
        if      (preg_match('/^(GE|NSTP|PE)/i', $code))          $category = 'General Education';
        elseif  (preg_match('/^(IT|CC|CS|IS|ICT)/i', $code))     $category = 'Major';
        else                                                       $category = 'Minor';

        $sign          = ($action === 'Drop') ? -1 : 1;
        $tuitionImpact = round($sign * $units * $tuitionRate, 2);
        // FIX LAB-REMOVE-01: Lab fee removed — tuition + energy only.
        $labFeeImpact  = 0.00;
        $energyImpact  = round($sign * $units * $energyRate, 2);
        $totalImpact   = round($tuitionImpact + $energyImpact, 2);

        $reason       = "Accounting Approved (backfill): Add/Drop request #$rid";
        $addedByRole  = 'accounting';
        $addedByEmail = '';
        $courseCode   = $c['code'];
        $courseName   = $c['name'];

        $ins = $conn->prepare("
            INSERT INTO subject_fee_log
                (student_id, course_id, course_code, course_name, action, subject_type,
                 course_category, units, lec_units, lab_units,
                 tuition_impact, lab_fee_impact, energy_impact, total_impact,
                 semester, reason, added_by_role, added_by_email)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        if (!$ins) { $skipped++; $errors[] = "request #$rid: prepare failed"; continue; }
        $ins->bind_param(
            'iisssssiiddddssss',
            $sid, $cid, $courseCode, $courseName, $action, $subjectType,
            $category, $units, $lecUnits, $labUnits,
            $tuitionImpact, $labFeeImpact, $energyImpact, $totalImpact,
            $semester, $reason, $addedByRole, $addedByEmail
        );
        if ($ins->execute()) { $written++; } else { $skipped++; $errors[] = "request #$rid: " . $ins->error; }
        $ins->close();
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success' => true,
        'message' => "Backfill complete. $written entries written, $skipped skipped.",
        'written' => $written,
        'skipped' => $skipped,
        'errors'  => $errors,
    ]);
}

/**
 * POST ?action=grant_scholarship
 * Body: { student_id, scholar_type, grantor, scholarship_amount, semester, full_tuition }
 *
 * If full_tuition=true, scholarship_amount is set to the student's total_assessment
 * so the net balance is ₱0 — student is auto-approved and enrolled with no payment needed.
 */
function grantScholarship(mysqli $conn, array $data = []): void {
    global $authUser;
    $sid           = (int)($data['student_id']        ?? 0);
    $scholarType   = trim($data['scholar_type']       ?? 'Full Scholarship');
    $grantor       = trim($data['grantor']            ?? '');
    $semester      = trim($data['semester']           ?? '');
    $fullTuition   = !empty($data['full_tuition']);
    $manualAmount  = isset($data['scholarship_amount']) ? (float)$data['scholarship_amount'] : null;

    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$sid) { echo json_encode(['success'=>false,'message'=>'student_id required']); return; }

    // Resolve tuition total
    $tfRow = (($_r=$conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id=$sid LIMIT 1")) ? $_r->fetch_assoc() : null);
    $totalAssessment = $tfRow ? (float)$tfRow['total_assessment'] : 0;

    if ($fullTuition) {
        // Full scholarship = discount equals the entire assessment → zero balance
        $amount = $totalAssessment > 0 ? $totalAssessment : ($manualAmount ?? 0);
    } else {
        $amount = $manualAmount ?? 0;
    }

    // Deactivate previous scholarships for this student/semester
    $conn->query("UPDATE student_scholarships SET is_active=0, revoked_at=NOW(), revoked_by_email='" . $conn->real_escape_string($authUser['email'] ?? '') . "', revoke_reason='Superseded by new scholarship grant' WHERE student_id=$sid AND is_active=1");

    // Insert new scholarship record with full audit trail
    $grantedByEmail = $authUser['email'] ?? '';
    $grantedById    = (int)($authUser['user_id'] ?? 0);
    $notes          = trim($data['notes'] ?? '');
    $ins = $conn->prepare("INSERT INTO student_scholarships (student_id, scholar_type, grantor, scholarship_amount, semester, is_active, granted_by, granted_by_email, notes) VALUES (?,?,?,?,?,1,?,?,?)");
    $ins->bind_param('issdsiis', $sid, $scholarType, $grantor, $amount, $semester, $grantedById, $grantedByEmail, $notes);
    $ins->execute();
    $ins->close();

    // Update students table for fast reads
    $upd = $conn->prepare("UPDATE students SET is_scholar=1, scholar_type=?, scholar_grantor=?, scholarship_amount=? WHERE id=?");
    $upd->bind_param('ssdi', $scholarType, $grantor, $amount, $sid);
    $upd->execute();
    $upd->close();

    // Recompute tuition_fees with the new discount
    // FIX SCHOLAR-05: Scope the tuition_fees update to the student's current semester only.
    // The old WHERE included OR semester IS NULL which could update stale rows from
    // previous semesters, corrupting historical tuition data.
    $recompute = $conn->prepare("UPDATE tuition_fees SET discount=?, total_assessment=GREATEST(0, subtotal - ? + installment_fee), updated_at=NOW() WHERE student_id=? AND semester=(SELECT semester FROM students WHERE id=? LIMIT 1)");
    $recompute->bind_param('ddii', $amount, $amount, $sid, $sid);
    $recompute->execute();
    $recompute->close();

    // Fetch updated total
    $newTf = (($_r=$conn->query("SELECT total_assessment FROM tuition_fees WHERE student_id=$sid LIMIT 1")) ? $_r->fetch_assoc() : null);
    $newTotal = $newTf ? (float)$newTf['total_assessment'] : 0;

    // If full scholarship (net total = 0), auto-approve and enroll
    if ($newTotal <= 0) {
        $accId = (int)($authUser['user_id'] ?? 0);
        $note  = "Full scholarship granted by " . ($authUser['email'] ?? 'accounting');
        // Dead code removed — updated below with Confirmed status
        $updFull = $conn->prepare("UPDATE students SET payment_status='Paid', approval_status='Approved', enrollment_status='Confirmed', accounting_approved_by=?, accounting_approved_at=NOW(), accounting_notes=? WHERE id=?");
        $updFull->bind_param('isi', $accId, $note, $sid);
        $updFull->execute();
        $updFull->close();

        // Mark tuition as fully paid via scholarship (no payment log needed)
        $conn->query("UPDATE payment_schedules SET prelim_paid=prelim_due, midterm_paid=midterm_due, finals_paid=finals_due, prelim_status='paid', midterm_status='paid', finals_status='paid' WHERE student_id=$sid");

        // FIX SCHOLAR-01: Dismiss any pending payment_logs so the student no longer
        // appears in the Accounting queue after a full scholarship is granted.
        $conn->query("UPDATE payment_logs SET status='Cancelled', notes='Cancelled — full scholarship granted' WHERE student_id=$sid AND status='Pending'");

        logAuditShared($conn, $authUser ?? null, 'GRANT_FULL_SCHOLARSHIP', 'student', $sid,
            "Full scholarship granted: $scholarType by $grantor. Total assessment ₱" . number_format($totalAssessment, 2) . " fully covered. Student auto-approved.");

        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode([
            'success'         => true,
            'message'         => "Full scholarship granted. Student auto-approved and enrolled — no payment required.",
            'scholarship_amount' => $amount,
            'new_total'       => 0,
            'auto_approved'   => true,
        ]);
        return;
    }

    logAuditShared($conn, $authUser ?? null, 'GRANT_SCHOLARSHIP', 'student', $sid,
        "Partial scholarship granted: $scholarType ₱" . number_format($amount, 2));

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'            => true,
        'message'            => "Scholarship of ₱" . number_format($amount, 2) . " applied. New balance: ₱" . number_format($newTotal, 2),
        'scholarship_amount' => $amount,
        'new_total'          => $newTotal,
        'auto_approved'      => false,
    ]);
}

/**
 * POST ?action=remove_scholarship
 * Body: { student_id }
 */
function removeScholarship(mysqli $conn, array $data = []): void {
    global $authUser;
    $sid    = (int)($data['student_id'] ?? 0);
    $reason = trim($data['reason'] ?? 'Removed by accounting');
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$sid) { echo json_encode(['success'=>false,'message'=>'student_id required']); return; }

    $revokerEmail = $conn->real_escape_string($authUser['email'] ?? '');
    $reasonEsc    = $conn->real_escape_string($reason);
    $conn->query("UPDATE student_scholarships SET is_active=0, revoked_at=NOW(), revoked_by_email='$revokerEmail', revoke_reason='$reasonEsc' WHERE student_id=$sid AND is_active=1");
    // FIX SCHOLAR-04: Guard before clearing is_scholar — only clear if no other active
    // scholarship exists. Also reset payment_status to Pending so the student re-enters
    // the normal payment flow instead of being stuck with a stale status.
    $otherActive4 = $conn->query("SELECT id FROM student_scholarships WHERE student_id=$sid AND is_active=1 LIMIT 1");
    if (!$otherActive4 || $otherActive4->num_rows === 0) {
        $conn->query("UPDATE students SET is_scholar=0, scholarship_amount=0, scholar_type=NULL, scholar_grantor=NULL, payment_status='Pending' WHERE id=$sid");
    }
    // Revert tuition_fees discount to 0.
    // No semester filter — matches approveScholarship behavior and avoids silent skip
    // when tuition_fees.semester is NULL or mismatched (same fix as FIX BUG-4 in rejectScholarship).
    $_rmTfSt = $conn->prepare("UPDATE tuition_fees SET discount=0, total_assessment=GREATEST(0, subtotal + installment_fee), updated_at=NOW() WHERE student_id=?");
    $_rmTfSt->bind_param('i', $sid); $_rmTfSt->execute(); $_rmTfSt->close();

    logAuditShared($conn, $authUser ?? null, 'REMOVE_SCHOLARSHIP', 'student', $sid, "Scholarship removed. Reason: $reason");
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success'=>true,'message'=>'Scholarship removed. Tuition fees restored.']);
}


function getPendingScholarships(mysqli $conn): void {
    // Auto-add columns if migrate.php hasn't been run yet — safe no-op if already exist
    $conn->query("ALTER TABLE student_scholarships ADD COLUMN IF NOT EXISTS status ENUM('pending','approved','rejected','superseded') NOT NULL DEFAULT 'approved'");
    $conn->query("ALTER TABLE student_scholarships ADD COLUMN IF NOT EXISTS reviewed_by_email VARCHAR(150) DEFAULT NULL");
    $conn->query("ALTER TABLE student_scholarships ADD COLUMN IF NOT EXISTS reviewed_at DATETIME DEFAULT NULL");
    $conn->query("ALTER TABLE student_scholarships ADD COLUMN IF NOT EXISTS reject_reason TEXT DEFAULT NULL");
    $conn->query("ALTER TABLE student_scholarships ADD COLUMN IF NOT EXISTS granted_by INT DEFAULT NULL");
    $conn->query("ALTER TABLE student_scholarships ADD COLUMN IF NOT EXISTS granted_by_email VARCHAR(150) DEFAULT NULL");
    $conn->query("ALTER TABLE student_scholarships ADD COLUMN IF NOT EXISTS notes TEXT DEFAULT NULL");
    $conn->query("ALTER TABLE student_scholarships ADD COLUMN IF NOT EXISTS revoked_at DATETIME DEFAULT NULL");
    $conn->query("ALTER TABLE student_scholarships ADD COLUMN IF NOT EXISTS revoked_by_email VARCHAR(150) DEFAULT NULL");
    $conn->query("ALTER TABLE student_scholarships ADD COLUMN IF NOT EXISTS revoke_reason TEXT DEFAULT NULL");

    $statusFilter = $_GET['status'] ?? 'pending';
    $allowed      = ['pending', 'approved', 'rejected', 'all'];
    if (!in_array($statusFilter, $allowed, true)) $statusFilter = 'pending';

    $where = $statusFilter === 'all' ? '' : "WHERE ss.status = '$statusFilter'";

    $res = $conn->query("
        SELECT ss.*,
               s.first_name, s.last_name, s.student_number,
               s.program, s.year_level, s.student_category,
               s.enrollment_status, s.payment_plan,
               COALESCE(tf.total_assessment, 0) AS total_assessment,
               COALESCE(tf.subtotal, 0)          AS subtotal
        FROM student_scholarships ss
        JOIN students s  ON s.id  = ss.student_id
        -- FIX SCHOLAR-06: Scope tuition_fees to the student's current semester so the
        -- correct total_assessment is shown (not a stale row from a previous semester).
        LEFT JOIN tuition_fees tf ON tf.student_id = ss.student_id
                                  AND tf.semester = s.semester
        $where
        ORDER BY ss.created_at DESC
    ");

    if (!$res) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'Query failed: ' . $conn->error]);
        return;
    }

    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'scholarships' => $rows, 'count' => count($rows)]);
}

// =============================================================================
// APPROVE SCHOLARSHIP — POST ?action=approve_scholarship
// Body: { scholarship_id, notes? }
//
// Sets status=approved, is_active=1, applies discount to tuition_fees.
// If scholarship covers 100% of total → auto-approve student (Enrolled, Paid).
// =============================================================================
function approveScholarship(mysqli $conn, array $data = []): void {
    global $authUser;
    $schId          = (int)($data['scholarship_id']  ?? 0);
    $notes          = trim($data['notes']            ?? '');
    $overrideAmount = isset($data['override_amount']) ? (float)$data['override_amount'] : null;
    $fullTuition    = !empty($data['full_tuition']); // true = cover entire assessment

    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$schId) { echo json_encode(['success'=>false,'message'=>'scholarship_id required']); return; }

    // Fetch the scholarship record
    $schStmt = $conn->prepare("SELECT * FROM student_scholarships WHERE id=? LIMIT 1");
    $schStmt->bind_param('i', $schId);
    $schStmt->execute();
    $sch = $schStmt->get_result()->fetch_assoc();
    $schStmt->close();

    if (!$sch) { echo json_encode(['success'=>false,'message'=>'Scholarship record not found']); return; }
    if ($sch['status'] === 'approved') { echo json_encode(['success'=>false,'message'=>'Already approved']); return; }

    $sid           = (int)$sch['student_id'];
    $reviewerEmail = $authUser['email'] ?? '';

    // Resolve the scholarship amount:
    // 1. full_tuition flag → set amount = student's total assessment
    // 2. override_amount sent by accounting → use that
    // 3. fallback to whatever the student declared
    $_tfSt = $conn->prepare("SELECT total_assessment, subtotal FROM tuition_fees WHERE student_id=? LIMIT 1");
    $_tfSt->bind_param('i', $sid); $_tfSt->execute();
    $tfRow = $_tfSt->get_result()->fetch_assoc(); $_tfSt->close();
    $totalAssessment = $tfRow ? (float)$tfRow['total_assessment'] : 0;
    $subtotal        = $tfRow ? (float)$tfRow['subtotal']         : 0;

    if ($fullTuition) {
        // Cover the entire subtotal so net = 0
        $amount = $subtotal > 0 ? $subtotal : $totalAssessment;
    } elseif ($overrideAmount !== null && $overrideAmount > 0) {
        $amount = $overrideAmount;
    } else {
        $amount = (float)$sch['scholarship_amount'];
    }

    // Update the scholarship record with the final amount
    $notesEsc = $conn->real_escape_string($notes);
    $conn->query("UPDATE student_scholarships
                  SET status='approved', is_active=1,
                      scholarship_amount=$amount,
                      reviewed_by_email='$reviewerEmail',
                      reviewed_at=NOW()
                      " . ($notes ? ", notes='$notesEsc'" : "") . "
                  WHERE id=$schId");

    // Sync students table
    $updStmt = $conn->prepare("UPDATE students SET is_scholar=1, scholarship_amount=?, scholar_type=?, scholar_grantor=? WHERE id=?");
    $updStmt->bind_param('dssi', $amount, $sch['scholar_type'], $sch['grantor'], $sid);
    $updStmt->execute();
    $updStmt->close();

    // Apply discount to tuition_fees
    $recomp = $conn->prepare("UPDATE tuition_fees
                               SET discount=?, total_assessment=GREATEST(0, subtotal - ? + installment_fee), updated_at=NOW()
                               WHERE student_id=?");
    $recomp->bind_param('ddi', $amount, $amount, $sid);
    $recomp->execute();
    $recomp->close();

    // Fetch new total
    $_ntSt = $conn->prepare("SELECT total_assessment FROM tuition_fees WHERE student_id=? LIMIT 1");
    $_ntSt->bind_param('i', $sid); $_ntSt->execute();
    $newTfRow = $_ntSt->get_result()->fetch_assoc(); $_ntSt->close();
    $newTotal = $newTfRow ? (float)$newTfRow['total_assessment'] : 0;

    $autoApproved = false;
    if ($newTotal <= 0) {
        // Full scholarship — auto approve & enroll student
        $accId = (int)($authUser['user_id'] ?? 0);
        $note  = "Full scholarship approved by " . $reviewerEmail;
        $fullStmt = $conn->prepare("UPDATE students SET payment_status='Paid', approval_status='Approved', enrollment_status='Confirmed', accounting_approved_by=?, accounting_approved_at=NOW(), accounting_notes=? WHERE id=?");
        $fullStmt->bind_param('isi', $accId, $note, $sid);
        $fullStmt->execute();
        $fullStmt->close();

        // Unlock all payment periods
        $_psSt = $conn->prepare("UPDATE payment_schedules SET prelim_paid=prelim_due, midterm_paid=midterm_due, finals_paid=finals_due, prelim_status='paid', midterm_status='paid', finals_status='paid' WHERE student_id=?");
        $_psSt->bind_param('i', $sid); $_psSt->execute(); $_psSt->close();

        // FIX SCHOLAR-02: Same as SCHOLAR-01 — dismiss pending payment_logs so the
        // Accounting queue no longer shows this student after full scholarship is approved.
        $_plSt = $conn->prepare("UPDATE payment_logs SET status='Cancelled', notes='Cancelled — full scholarship approved' WHERE student_id=? AND status='Pending'");
        $_plSt->bind_param('i', $sid); $_plSt->execute(); $_plSt->close();

        $autoApproved = true;
    }

    logAuditShared($conn, $authUser ?? null, 'APPROVE_SCHOLARSHIP', 'student', $sid,
        "Scholarship approved: {$sch['scholar_type']} ₱" . number_format($amount, 2) . " by $reviewerEmail" . ($fullTuition ? " (Full Tuition)" : ""));

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'            => true,
        'message'            => $autoApproved
            ? "Full scholarship approved. Student auto-enrolled — no payment required."
            : "Scholarship approved. Discount of ₱" . number_format($amount, 2) . " applied. New balance: ₱" . number_format($newTotal, 2),
        'scholarship_amount' => $amount,
        'new_total'          => $newTotal,
        'auto_approved'      => $autoApproved,
    ]);
}

// =============================================================================
// REJECT SCHOLARSHIP — POST ?action=reject_scholarship
// Body: { scholarship_id, reason }
// =============================================================================
function rejectScholarship(mysqli $conn, array $data = []): void {
    global $authUser;
    $schId  = (int)($data['scholarship_id'] ?? 0);
    $reason = trim($data['reason'] ?? '');

    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$schId)   { echo json_encode(['success'=>false,'message'=>'scholarship_id required']); return; }
    if (!$reason)  { echo json_encode(['success'=>false,'message'=>'reason required']); return; }

    $schStmt = $conn->prepare("SELECT * FROM student_scholarships WHERE id=? LIMIT 1");
    $schStmt->bind_param('i', $schId);
    $schStmt->execute();
    $sch = $schStmt->get_result()->fetch_assoc();
    $schStmt->close();

    if (!$sch) { echo json_encode(['success'=>false,'message'=>'Scholarship record not found']); return; }

    $sid           = (int)$sch['student_id'];
    $reviewerEmail = $conn->real_escape_string($authUser['email'] ?? '');
    $reasonEsc     = $conn->real_escape_string($reason);

    $conn->query("UPDATE student_scholarships
                  SET status='rejected', is_active=0,
                      reviewed_by_email='$reviewerEmail',
                      reviewed_at=NOW(),
                      reject_reason='$reasonEsc'
                  WHERE id=$schId");

    // FIX SCHOLAR-03: Only clear is_scholar if this was the ONLY active scholarship.
    // If the student somehow has another active scholarship record (edge case), keep the flag.
    // Also restore payment_status to Pending so the student can proceed with payment or re-declare.
    $_oaSt = $conn->prepare("SELECT id FROM student_scholarships WHERE student_id=? AND is_active=1 LIMIT 1");
    $_oaSt->bind_param('i', $sid); $_oaSt->execute();
    $otherActive = $_oaSt->get_result(); $_oaSt->close();
    if (!$otherActive || $otherActive->num_rows === 0) {
        $_clrSt = $conn->prepare("UPDATE students SET is_scholar=0, scholarship_amount=0, scholar_type=NULL, scholar_grantor=NULL, payment_status='Pending' WHERE id=?");
        $_clrSt->bind_param('i', $sid); $_clrSt->execute(); $_clrSt->close();
    }

    // FIX SCHOLAR-REJECT-01: Restore tuition_fees discount to 0.
    // removeScholarship() does this correctly; rejectScholarship() was missing it.
    // Without this, a student who self-declared an amount had their balance permanently
    // wrong after rejection — the discount row was never cleared.
    // FIX BUG-4: Removed the AND semester=(subquery) filter — it silently skipped rows
    // when tuition_fees.semester was NULL, empty, or mismatched. Match by student_id only,
    // consistent with approveScholarship's UPDATE which has no semester filter.
    $_tfClrSt = $conn->prepare("UPDATE tuition_fees
                  SET discount=0,
                      total_assessment=GREATEST(0, subtotal + installment_fee),
                      updated_at=NOW()
                  WHERE student_id=?");
    $_tfClrSt->bind_param('i', $sid); $_tfClrSt->execute(); $_tfClrSt->close();

    // FIX SCHOLAR-REJECT-02: Safety net — if somehow a payment was Verified before
    // the scholarship was reviewed (should not happen after SCHOLAR-VERIFY-01 guard,
    // but defensive coding for older records), cancel those verified logs and flag them
    // so accounting is aware. The balance has already been corrected above.
    $_plCanSt = $conn->prepare("UPDATE payment_logs
                  SET status = 'Cancelled',
                      notes  = CONCAT(COALESCE(notes,''), ' | Auto-cancelled: scholarship was rejected after verification (SCHOLAR-REJECT-02)')
                  WHERE student_id = ?
                    AND status     = 'Verified'
                    AND created_at > (SELECT COALESCE(created_at, NOW() - INTERVAL 1 YEAR)
                                      FROM student_scholarships WHERE id = ? LIMIT 1)");
    $_plCanSt->bind_param('ii', $sid, $schId); $_plCanSt->execute(); $_plCanSt->close();

    logAuditShared($conn, $authUser ?? null, 'REJECT_SCHOLARSHIP', 'student', $sid,
        "Scholarship rejected: {$sch['scholar_type']}. Reason: $reason");

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success'=>true,'message'=>'Scholarship application rejected.']);
}

// ================================================================
// ACCOUNTING: Get add/drop requests with fee preview
// GET ?action=get_add_drop_requests_accounting[&status=Pending|Approved|Rejected|All]
// Proxies to add_drop_requests table — same data as enrollment.php
// but accessible from Accounting portal.
// ================================================================
function getAddDropRequestsForAccounting(mysqli $conn): void {
    // Ensure new columns exist (safe no-op if already added by enrollment.php)
    $conn->query("ALTER TABLE add_drop_requests ADD COLUMN IF NOT EXISTS accounting_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending'");
    $conn->query("ALTER TABLE add_drop_requests ADD COLUMN IF NOT EXISTS accounting_notes TEXT DEFAULT NULL");
    $conn->query("ALTER TABLE add_drop_requests ADD COLUMN IF NOT EXISTS fee_impact DECIMAL(10,2) DEFAULT 0");
    $conn->query("ALTER TABLE add_drop_requests ADD COLUMN IF NOT EXISTS new_total_assessment DECIMAL(10,2) DEFAULT 0");

    // FIX: default to 'All' so the fee log isn't empty when entries exist with any status.
    // Previously defaulted to 'Pending' which hid all already-processed entries.
    $status = trim($_GET['status'] ?? 'All');
    if (!in_array($status, ['Pending','Approved','Rejected','All'], true)) $status = 'All';
    $where  = $status === 'All' ? '' : "AND r.accounting_status = '$status'";

    $res = $conn->query("
        SELECT r.*,
               c.code, c.name AS course_name, c.credits, c.is_lab,
               s.first_name, s.last_name, s.student_number,
               s.program, s.year_level, s.semester, s.student_category,
               tf.total_assessment AS current_total,
               tf.units AS current_units
        FROM add_drop_requests r
        JOIN courses  c ON r.course_id  = c.id
        JOIN students s ON r.student_id = s.id
        LEFT JOIN (SELECT student_id, total_assessment, subtotal, units FROM tuition_fees WHERE id IN (SELECT MAX(id) FROM tuition_fees GROUP BY student_id)) tf ON tf.student_id = s.id
        WHERE 1=1 $where
        ORDER BY r.created_at DESC
    ");

    $rows = [];
    if ($res) {
        // Load fee config once
        $fcR = $conn->query("SELECT fee_key, value FROM fee_config WHERE category='College' AND is_active=1");
        $fc  = [];
        if ($fcR) while ($fr = $fcR->fetch_assoc()) $fc[$fr['fee_key']] = (float)$fr['value'];
        $rTuition = $fc['tuition_rate_per_unit'] ?? 650;
        $rEnergy  = $fc['energy_rate_per_unit']  ?? 63;
        $rLab     = $fc['lab_fee_per_room']       ?? 1900;

        while ($r = $res->fetch_assoc()) {
            $currentTotal  = (float)($r['current_total']  ?? 0);
            $courseCredits = (int)$r['credits'];
            $isLab         = (int)($r['is_lab'] ?? 0);
            $sign          = ($r['request_type'] === 'Drop') ? -1 : 1;
            $tuitionImpact = round($sign * $courseCredits * $rTuition, 2);
            // FIX LAB-REMOVE-01: Lab fee removed — tuition + energy only.
            $labImpact     = 0.00;
            $energyImpact  = round($sign * $courseCredits * $rEnergy, 2);
            $totalImpact   = round($tuitionImpact + $energyImpact, 2);
            $newTotal      = round(max(0, $currentTotal + $totalImpact), 2);

           
            // FIX LAB-REMOVE-01: Lab fee impact removed — only tuition + energy shown.
            // Re-compute totalImpact and newTotal without lab fee.
            $tuitionImpact = round($sign * $courseCredits * $rTuition, 2);
            $energyImpact  = round($sign * $courseCredits * $rEnergy, 2);
            $totalImpact   = round($tuitionImpact + $energyImpact, 2);
            $newTotal      = round(max(0, $currentTotal + $totalImpact), 2);

            // Always use freshly computed values — stored fee_impact/new_total_assessment
            // may be stale (e.g. included lab fee before LAB-REMOVE-01 fix).
            $r['fee_preview'] = [
                'currentTotal'  => $currentTotal,
                'currentUnits'  => (int)($r['current_units'] ?? 0),
                'courseUnits'   => $courseCredits,
                'tuitionImpact' => $tuitionImpact,
                'energyImpact'  => $energyImpact,
                'totalImpact'   => $totalImpact,
                'newTotal'      => $newTotal,
            ];
            unset($r['current_total'], $r['current_units'], $r['is_lab']);
            $rows[] = $r;
        }
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'requests' => $rows, 'count' => count($rows)]);
}
// ================================================================
// FIX ADD-DROP-01: accounting_approve_add_drop was missing from
// Accounting.php — the route only existed in enrollment.php which
// the Accounting portal never calls for this action. This caused:
//   1. accounting_status stayed 'Pending' forever → Registrar was
//      blocked from approving ("awaiting Accounting approval")
//   2. subject_fee_log was never written on accounting approval
//
// FIX ADD-DROP-02: Drop requests no longer deduct lab_fee.
//   Lab fee is charged per Laboratory room (building/facility cost),
//   NOT per subject. Dropping a subject does NOT free up a lab room,
//   so the lab fee component is excluded from Drop fee impact.
//   Only tuition (per unit) and energy (per unit) are adjusted.
// ================================================================
function accountingApproveAddDropFromAccounting(mysqli $conn, array $data): void {
    global $authUser;

    if (!$authUser || !in_array($authUser['role'] ?? '', ['accounting','admin'], true)) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success'=>false,'message'=>'Access denied. Only Accounting or Admin can review add/drop fee impact.']);
        return;
    }

    $rid    = (int)($data['request_id'] ?? 0);
    $action = trim($data['action']      ?? '');
    $notes  = trim($data['notes']       ?? '');

    if (!$rid || !in_array($action, ['Approved','Rejected'], true)) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success'=>false,'message'=>'request_id and action (Approved|Rejected) required']);
        return;
    }

    // Ensure required columns exist
    $conn->query("ALTER TABLE add_drop_requests ADD COLUMN IF NOT EXISTS accounting_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending'");
    $conn->query("ALTER TABLE add_drop_requests ADD COLUMN IF NOT EXISTS accounting_notes TEXT DEFAULT NULL");
    $conn->query("ALTER TABLE add_drop_requests ADD COLUMN IF NOT EXISTS fee_impact DECIMAL(10,2) DEFAULT 0");
    $conn->query("ALTER TABLE add_drop_requests ADD COLUMN IF NOT EXISTS new_total_assessment DECIMAL(10,2) DEFAULT 0");
    $conn->query("ALTER TABLE add_drop_requests ADD COLUMN IF NOT EXISTS accounting_forwarded_at DATETIME DEFAULT NULL");
    $conn->query("ALTER TABLE add_drop_requests ADD COLUMN IF NOT EXISTS accounting_reviewed_by INT DEFAULT NULL");
    $conn->query("ALTER TABLE add_drop_requests ADD COLUMN IF NOT EXISTS accounting_reviewed_at DATETIME DEFAULT NULL");

    // Fetch the request
    $reqSt = $conn->prepare("SELECT * FROM add_drop_requests WHERE id=? LIMIT 1");
    $reqSt->bind_param('i', $rid);
    $reqSt->execute();
    $req = $reqSt->get_result()->fetch_assoc();
    $reqSt->close();

    if (!$req) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success'=>false,'message'=>'Request not found']);
        return;
    }

    $sid = (int)$req['student_id'];
    $cid = (int)$req['course_id'];
    $requestType = $req['request_type']; // 'Add' or 'Drop'

    // ── FIX ADD-DROP-02: Compute fee impact WITHOUT lab_fee for Drop ─────────
    // Lab fee = per-room facility cost. Dropping a subject doesn't vacate a lab
    // room — the room stays booked for other students. Only unit-based fees change.
    $feeImpact = 0.00;
    $newTotal  = 0.00;
    $isFullScholar = false;

    // Fetch course credits
    $cR = $conn->prepare("SELECT credits, is_lab FROM courses WHERE id=? LIMIT 1");
    $cR->bind_param('i', $cid);
    $cR->execute();
    $cRow = $cR->get_result()->fetch_assoc();
    $cR->close();
    $courseCredits = (int)($cRow['credits'] ?? 0);
    $isLab         = (int)($cRow['is_lab']  ?? 0);

    // Fetch fee rates
    $fcR = $conn->query("SELECT fee_key, value FROM fee_config WHERE category='College' AND is_active=1");
    $fc  = [];
    if ($fcR) while ($fr = $fcR->fetch_assoc()) $fc[$fr['fee_key']] = (float)$fr['value'];
    $rTuition = $fc['tuition_rate_per_unit'] ?? 650;
    $rEnergy  = $fc['energy_rate_per_unit']  ?? 63;
    $rLab     = $fc['lab_fee_per_room']       ?? 1900;

    // Scholar info
    $schR = $conn->prepare("SELECT is_scholar, scholarship_amount FROM students WHERE id=? LIMIT 1");
    $schR->bind_param('i', $sid);
    $schR->execute();
    $schRow = $schR->get_result()->fetch_assoc();
    $schR->close();
    $isScholarFlag     = (int)($schRow['is_scholar']         ?? 0);
    $scholarshipAmount = (float)($schRow['scholarship_amount'] ?? 0);

    // Full scholar detection
    if ($isScholarFlag) {
        $ssR = $conn->prepare("SELECT id FROM student_scholarships WHERE student_id=? AND is_active=1 LIMIT 1");
        $ssR->bind_param('i', $sid);
        $ssR->execute();
        $isFullScholar = $ssR->get_result()->num_rows > 0;
        $ssR->close();
    }

    // Fetch current totals
    $tfR = $conn->prepare("SELECT total_assessment, subtotal FROM tuition_fees WHERE student_id=? LIMIT 1");
    $tfR->bind_param('i', $sid);
    $tfR->execute();
    $tfRow = $tfR->get_result()->fetch_assoc();
    $tfR->close();
    $currentTotal    = (float)($tfRow['total_assessment'] ?? 0);
    $currentSubtotal = (float)($tfRow['subtotal']         ?? 0);

    $sign          = ($requestType === 'Drop') ? -1 : 1;
    $tuitionImpact = round($sign * $courseCredits * $rTuition, 2);
    $energyImpact  = round($sign * $courseCredits * $rEnergy, 2);

    // FIX LAB-REMOVE-01: Lab fee removed — tuition + energy only.
    $labImpact   = 0.00;

    $totalImpact = round($tuitionImpact + $energyImpact, 2);

    if ($isFullScholar) {
        $feeImpact = 0.00;
        $newTotal  = 0.00;
    } elseif ($isScholarFlag && $scholarshipAmount > 0) {
        $newSubtotal = max(0, $currentSubtotal + ($tuitionImpact + $energyImpact));
        $feeImpact   = $totalImpact;
        $newTotal    = round(max(0, $newSubtotal - $scholarshipAmount), 2);
    } else {
        $feeImpact = $totalImpact;
        $newTotal  = round(max(0, $currentTotal + $totalImpact), 2);
    }

    $reviewerId = (int)($authUser['user_id'] ?? 0);

    // Save accounting review result + fee impact
    $upd = $conn->prepare("UPDATE add_drop_requests
        SET accounting_status=?, accounting_reviewed_by=?, accounting_reviewed_at=NOW(),
            accounting_notes=?, fee_impact=?, new_total_assessment=?
        WHERE id=?");
    $upd->bind_param('sisddi', $action, $reviewerId, $notes, $feeImpact, $newTotal, $rid);
    $upd->execute();
    $upd->close();

    // If approved → write fee log + forward to registrar
    if ($action === 'Approved') {
        // Write subject_fee_log with the corrected (no lab on drop) fee impact
        $conn->query("CREATE TABLE IF NOT EXISTS subject_fee_log (
            id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL, course_id INT NOT NULL,
            course_code VARCHAR(20) DEFAULT NULL, course_name VARCHAR(150) DEFAULT NULL,
            action ENUM('Add','Drop') NOT NULL DEFAULT 'Add',
            subject_type VARCHAR(50) DEFAULT NULL, course_category VARCHAR(50) DEFAULT NULL,
            units INT DEFAULT 0, lec_units INT DEFAULT 0, lab_units INT DEFAULT 0,
            tuition_impact DECIMAL(10,2) NOT NULL DEFAULT 0,
            lab_fee_impact DECIMAL(10,2) NOT NULL DEFAULT 0,
            energy_impact DECIMAL(10,2) NOT NULL DEFAULT 0,
            total_impact DECIMAL(10,2) NOT NULL DEFAULT 0,
            semester VARCHAR(100) DEFAULT NULL, reason VARCHAR(255) DEFAULT NULL,
            added_by_role VARCHAR(50) DEFAULT NULL, added_by_email VARCHAR(150) DEFAULT NULL,
            performed_by INT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_student (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Fetch course details for the log
        $cLogR = $conn->prepare("SELECT code, name, credits, lec_units, lab_units, is_lab FROM courses WHERE id=? LIMIT 1");
        $cLogR->bind_param('i', $cid);
        $cLogR->execute();
        $cLog = $cLogR->get_result()->fetch_assoc();
        $cLogR->close();

        $semR2 = $conn->prepare("SELECT semester FROM students WHERE id=? LIMIT 1");
        $semR2->bind_param('i', $sid);
        $semR2->execute();
        $semRow2 = $semR2->get_result()->fetch_assoc();
        $semR2->close();
        $semester = $semRow2['semester'] ?? '';

        if ($cLog) {
            $units    = (int)($cLog['credits']   ?? 0);
            $lecUnits = (int)($cLog['lec_units'] ?? 0);
            $labUnits = (int)($cLog['lab_units'] ?? 0);
            $subjectType = ($isLab || $labUnits > 0) ? 'Laboratory' : 'Lecture';
            $code = strtoupper($cLog['code'] ?? '');
            if      (preg_match('/^(GE|NSTP|PE)/i', $code)) $category = 'General Education';
            elseif  (preg_match('/^(IT|CC|CS|IS|ICT)/i', $code)) $category = 'Major';
            else    $category = 'Minor';

            $logReason    = "Accounting Approved: Add/Drop request #$rid — awaiting Registrar final approval";
            $addedByRole  = $authUser['role'] ?? 'accounting';
            $addedByEmail = $authUser['email'] ?? '';
            $courseCode   = $cLog['code'];
            $courseName   = $cLog['name'];

            $insLog = $conn->prepare("INSERT INTO subject_fee_log
                (student_id, course_id, course_code, course_name, action, subject_type,
                 course_category, units, lec_units, lab_units,
                 tuition_impact, lab_fee_impact, energy_impact, total_impact,
                 semester, reason, added_by_role, added_by_email)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            if ($insLog) {
                $insLog->bind_param(
                    'iisssssiiddddssss',
                    $sid, $cid, $courseCode, $courseName, $requestType, $subjectType,
                    $category, $units, $lecUnits, $labUnits,
                    $tuitionImpact, $labImpact, $energyImpact, $totalImpact,
                    $semester, $logReason, $addedByRole, $addedByEmail
                );
                $insLog->execute();
                $insLog->close();
            }
        }

        // Mark as forwarded to Registrar
        $fwdUpd = $conn->prepare("UPDATE add_drop_requests SET accounting_forwarded_at=NOW() WHERE id=?");
        $fwdUpd->bind_param('i', $rid);
        $fwdUpd->execute();
        $fwdUpd->close();
    }

    logAuditShared($conn, $authUser, 'ACCOUNTING_REVIEW_ADD_DROP', 'add_drop_requests', $rid,
        "Add/Drop request #$rid {$action} by Accounting. Fee impact: ₱" . number_format($feeImpact, 2) . ". New total: ₱" . number_format($newTotal, 2));

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'       => true,
        'message'       => "Request {$action} by Accounting. " . ($action === 'Approved'
            ? ($isFullScholar
                ? "Student is a Full Scholar — no additional charges. New total remains ₱0.00."
                : "Fee impact: ₱" . number_format(abs($feeImpact), 2) . ". New total after approval: ₱" . number_format($newTotal, 2))
            : "Request returned to student."),
        'feeImpact'     => $feeImpact,
        'newTotal'      => $newTotal,
        'isFullScholar' => $isFullScholar,
    ]);
}