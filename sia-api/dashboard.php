<?php
require_once __DIR__ . '/config.php';
ob_start(); // capture stray notices so JSON is never corrupted
applyCors();
// Shared helpers: cleanCode(), loadFeeConfig(), safeStudentId(), jsonOut()
require_once __DIR__ . '/helpers.php';
// ================================================================
//  dashboard.php  —  Student Dashboard API
//  Place in: C:\xampp\htdocs\sia-api\dashboard.php
//
//  Endpoints:
//    GET ?action=get_dashboard&student_id=X   (preferred)
//    GET ?action=get_dashboard&user_id=X      (fallback via user table)
//    GET ?action=get_announcements
//    GET ?action=get_events
// ================================================================// ── DB CONNECTION ─────────────────────────────────────────────
$action = $_GET['action'] ?? '';

// Auth routing:
//   public   — no token needed
//   admin    — admin token required
//   student  — student token required (default)
require_once __DIR__ . '/auth_middleware.php';

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
        if ($v<=0) return 'P0';
        $mag=pow(10,floor(log10($v)));
        return 'P'.number_format(floor($v/$mag)*$mag).'+';
    }
    function maskGrade(?float $v): string {
        if ($v===null) return 'N/A';
        if ($v>=5.0) return '5.0 (INC/Failed)';
        $l=floor($v*2)/2;
        return number_format($l,2).'-'.number_format($l+0.5,2);
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
            'amount'          =>['roles_full'=>['admin','accounting','student'],'roles_masked'=>['registrar'],'mask_fn'=>'maskAmount'],
            'gcash_amount'    =>['roles_full'=>['admin','accounting'],'roles_masked'=>['registrar'],'mask_fn'=>'maskAmount'],
            'gcashAmount'     =>['roles_full'=>['admin','accounting'],'roles_masked'=>['registrar'],'mask_fn'=>'maskAmount'],
            'totalAssessment' =>['roles_full'=>['admin','accounting','student'],'roles_masked'=>['registrar'],'mask_fn'=>'maskAmount'],
            'totalPaid'       =>['roles_full'=>['admin','accounting','student'],'roles_masked'=>['registrar'],'mask_fn'=>'maskAmount'],
            'balance'         =>['roles_full'=>['admin','accounting','student'],'roles_masked'=>['registrar'],'mask_fn'=>'maskAmount'],
            'gcash_reference' =>['roles_full'=>['admin','accounting'],'roles_masked'=>[]],
            'gcashReference'  =>['roles_full'=>['admin','accounting'],'roles_masked'=>[]],
            'reference_number'=>['roles_full'=>['admin','accounting'],'roles_masked'=>[]],
            'password'        =>['roles_full'=>[],'roles_masked'=>[]],
            'token'           =>['roles_full'=>[],'roles_masked'=>[]],
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
            $full   = $rule['roles_full']   ?? [];
            $masked = $rule['roles_masked'] ?? [];
            $fn     = $rule['mask_fn']      ?? null;
            if (in_array($eff,$full,true)) { $result[$key] = $value; }
            elseif (in_array($eff,$masked,true) && $fn && function_exists($fn)) { $result[$key] = $fn(_castForMask($fn,$value)); }
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

$publicDashActions = ['get_announcements', 'get_events'];
$adminDashActions  = ['add_announcement','update_announcement','delete_announcement',
                      'add_event','update_event','delete_event'];
if (in_array($action, $adminDashActions, true)) {
    $authUser = requireAuth($conn, 'admin');
} elseif (!in_array($action, $publicDashActions, true)) {
    $authUser = requireAuth($conn);
} else {
    $authUser = null;
}

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
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'Provide student_id or user_id']);
        exit();
    }

    // ── 1. Student base info ─────────────────────────────────
    $stmt = $conn->prepare(
        "SELECT s.id                    AS dbId,
                s.student_number,
                s.first_name,
                s.last_name,
                u.email,
                s.phone,
                s.program,
                s.year_level,
                s.gpa,
                s.enrollment_status,
                s.payment_status,
                s.approval_status,
                s.student_type,
                s.student_category,
                s.enrollment_date,
                s.semester
         FROM students s
         JOIN users u ON u.id = s.user_id
         WHERE $whereCol = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $param);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$student) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit();
    }

    // ── AUTO-APPROVE SHS + TVET non-transferee free students ─────────────────
    // TVET non-transferee = FREE (TESDA/PESFA/STEP government scholarship)
    // SHS  non-transferee = FREE (K-12 DepEd voucher)
    // TVET/SHS transferee = flat rate (₱20k); College = unit-based fees
    $studentDbId = (int) $student['dbId'];
    $cat = strtoupper(trim($student['student_category'] ?? ''));
    if (empty($cat)) {
        $sNum = $student['student_number'] ?? '';
        if     (strpos($sNum, 'SHS-')  === 0) $cat = 'SHS';
        elseif (strpos($sNum, 'TVET-') === 0) $cat = 'TVET';
    }
    $sType  = strtolower(trim($student['student_type'] ?? 'new'));
    $isFree = (($cat === 'SHS' || $cat === 'TVET') && $sType !== 'transferee');
    if ($isFree) {
        if (empty($student['student_category'])) {
            $catUpdStmt = $conn->prepare("UPDATE students SET student_category=? WHERE id=?");
            $catUpdStmt->bind_param('si', $cat, $studentDbId);
            $catUpdStmt->execute();
            $catUpdStmt->close();
            $student['student_category'] = $cat;
        }
        if (($student['approval_status'] ?? '') !== 'Approved') {
            // SHS/TVET non-transferee students pay nothing — auto-approve as Paid
            $appUpdStmt = $conn->prepare("UPDATE students
                SET approval_status='Approved', enrollment_status='Enrolled', payment_status='Paid'
                WHERE id=?");
            $appUpdStmt->bind_param('i', $studentDbId);
            $appUpdStmt->execute();
            $appUpdStmt->close();
            $student['approval_status']   = 'Approved';
            $student['enrollment_status'] = 'Enrolled';
            $student['payment_status']    = 'Paid';
        }
    }

    // ── 2. Enrolled courses (Enrolled + Pending statuses) ────
    $stmt = $conn->prepare(
        "SELECT c.id,
                c.code,
                c.name,
                c.credits,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(f.first_name,''),' ',COALESCE(f.last_name,''))), ''), TRIM(CONCAT(COALESCE(fc.first_name,''),' ',COALESCE(fc.last_name,''))), '') AS instructor,
                '' AS schedule,
                cs.day,
                CONCAT(COALESCE(cs.time_start,''),' - ',COALESCE(cs.time_end,'')) AS time,
                r.room_name AS room,
                c.semester,
                c.description,
                c.department,
                e.status          AS enrollment_status,
                e.enrollment_date AS enrolled_on
         FROM enrollments e
         JOIN courses c ON e.course_id = c.id
         LEFT JOIN course_sections cs ON cs.course_id = c.id AND cs.is_active = 1
         LEFT JOIN faculty f ON f.user_id = cs.faculty_id
         LEFT JOIN faculty fc ON fc.user_id = c.faculty_id
         LEFT JOIN rooms r ON r.id = cs.room_id
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
    // SHS/TVET fee overrides are applied later in the academic/flags block.
    // Here we compute College fees; $fees may be overwritten below for SHS/TVET.
    // FIX DASH-SEM-01: Filter by the student's current semester so that
    // re-enrolled students (who have one tuition_fees row per semester) always
    // get the current semester's assessment instead of the first/oldest row.
    $safeDashSem = $conn->real_escape_string($student['semester'] ?? '');
    $tfRes = $conn->query(
        "SELECT units, tuition_fee, miscellaneous_fee, registration_fee,
                laboratory_fee, energy_fee, subtotal, discount,
                installment_fee, total_assessment
         FROM tuition_fees
         WHERE student_id = $studentDbId
           AND semester = '$safeDashSem'
         LIMIT 1"
    );
    // Fallback: if no row matches the current semester (e.g. semester not yet set),
    // fall back to the most recent row so the dashboard never shows blank fees.
    if (!$tfRes || !$tfRes->num_rows) {
        $tfRes = $conn->query(
            "SELECT units, tuition_fee, miscellaneous_fee, registration_fee,
                    laboratory_fee, energy_fee, subtotal, discount,
                    installment_fee, total_assessment
             FROM tuition_fees
             WHERE student_id = $studentDbId
             ORDER BY id DESC LIMIT 1"
        );
    }
    $tf = $tfRes ? $tfRes->fetch_assoc() : null;

    // FIX DASH-PAID-01: Scope to the student's CURRENT semester only.
    // Without this, re-enrolled students carry forward all prior-semester
    // installment_payments, making the new semester balance appear as ₱0 / already paid.
    $paidRes = $conn->query(
        "SELECT COALESCE(SUM(amount), 0) AS total_paid
         FROM installment_payments
         WHERE student_id = $studentDbId
           AND semester = (SELECT semester FROM students WHERE id = $studentDbId LIMIT 1)"
    );
    $totalPaid = (float)(($paidRes ? $paidRes->fetch_assoc()['total_paid'] : 0) ?? 0);

    // Load extra (custom) fees from fee_config for display as line items
    $extraFeesList = [];
    $stdKeys = ['tuition_rate_per_unit','misc_fee','reg_fee','lab_fee_per_room','energy_rate_per_unit','installment_fee'];
    $studentCat = strtoupper(trim($student['student_category'] ?? 'College'));
    $feeCategory = in_array($studentCat, ['SHS','TVET']) ? $studentCat : 'College';
    $fcRes = $conn->query("SELECT fee_key, fee_label, value, is_per_unit FROM fee_config WHERE category='$feeCategory' AND is_active=1 ORDER BY sort_order");
    if ($fcRes) {
        while ($fcRow = $fcRes->fetch_assoc()) {
            if (!in_array($fcRow['fee_key'], $stdKeys)) {
                $units_for_extra = $tf ? (int)$tf['units'] : 0;
                $lineAmt = (float)$fcRow['value'] * ($fcRow['is_per_unit'] ? $units_for_extra : 1);
                $extraFeesList[] = [
                    'fee_key'    => $fcRow['fee_key'],
                    'fee_label'  => $fcRow['fee_label'],
                    'is_per_unit'=> (int)$fcRow['is_per_unit'],
                    'rate'       => (float)$fcRow['value'],
                    'amount'     => $lineAmt,
                ];
            }
        }
    }

    // College fee computation (may be overridden for SHS/TVET in the academic block below)
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
            'extraFees'       => $extraFeesList,
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
            'extraFees'       => $extraFeesList,
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

    // ── Determine student category for SHS/TVET-specific logic ──────────────
    $studentCatUpper = strtoupper(trim($student['student_category'] ?? ''));
    $isSHSStudent    = ($studentCatUpper === 'SHS');
    $isTVETStudent   = ($studentCatUpper === 'TVET');
    $isSHSorTVET     = ($isSHSStudent || $isTVETStudent);

    // ── Resolve display year level ────────────────────────────────────────────
    // For SHS: show "Grade 11" or "Grade 12" (never "1st Year" / "2nd Year")
    // For TVET: show the program name or TVET NC level (not a year)
    // For College: show as-is (1st Year, 2nd Year, etc.)
    $rawYearLevel = trim($student['year_level'] ?? '');
    if ($isSHSStudent) {
        // Normalize to Grade 11 / Grade 12 regardless of how it was stored
        if (stripos($rawYearLevel, '11') !== false || stripos($rawYearLevel, 'grade 11') !== false) {
            $displayYearLevel = 'Grade 11';
        } elseif (stripos($rawYearLevel, '12') !== false || stripos($rawYearLevel, 'grade 12') !== false) {
            $displayYearLevel = 'Grade 12';
        } else {
            $displayYearLevel = 'Grade 11'; // default for SHS
        }
    } elseif ($isTVETStudent) {
        // FIX TVET-YEARLEVEL-DASH-01: Show the actual year level stored in the DB
        // (e.g. '1st Year', '2nd Year', '3rd Year') — NOT the program/course name.
        // The program/course is already displayed in its own field in the dashboard.
        if (preg_match('/1st|first/i', $rawYearLevel) || $rawYearLevel === '1') {
            $displayYearLevel = '1st Year';
        } elseif (preg_match('/2nd|second/i', $rawYearLevel) || $rawYearLevel === '2') {
            $displayYearLevel = '2nd Year';
        } elseif (preg_match('/3rd|third/i', $rawYearLevel) || $rawYearLevel === '3') {
            $displayYearLevel = '3rd Year';
        } else {
            $displayYearLevel = $rawYearLevel ?: '1st Year';
        }
    } else {
        $displayYearLevel = $rawYearLevel ?: '1st Year';
    }

    // ── Determine if student is Irregular ────────────────────────────────────
    // SHS/TVET students are always Regular (no TOR system for K-12)
    $enrollmentStatusDisplay = $student['enrollment_status'];
    $isIrregular = false;
    if (!$isSHSorTVET && strtolower($student['student_type'] ?? '') === 'transferee') {
        $torRow = $conn->query("
            SELECT credited_units FROM tor_evaluations
            WHERE student_id = {$student['dbId']} AND status = 'Evaluated'
            ORDER BY id DESC LIMIT 1
        ");
        $tor = $torRow ? $torRow->fetch_assoc() : null;
        if ($tor && (int)$tor['credited_units'] > 0) {
            $enrollmentStatusDisplay = 'Irregular';
            $isIrregular = true;
        } else {
            $enrollmentStatusDisplay = 'Regular';
        }
    } else {
        $enrollmentStatusDisplay = 'Regular';
    }

    // ── Override fees for SHS + TVET non-transferee (FREE) and transferees (flat rate) ──
    //   SHS  non-transferee  → FREE (K-12 DepEd voucher)
    //   TVET non-transferee  → FREE (TESDA/PESFA/STEP government scholarship)
    //   SHS  transferee      → ₱20k flat rate from fee_config
    //   TVET transferee      → ₱20k flat rate from fee_config
    //   College              → unit-based fees (already computed above, no override)
    $studentTypeLC = strtolower(trim($student['student_type'] ?? 'new'));
    if ($isSHSorTVET) {
        if ($studentTypeLC === 'transferee') {
            // SHS or TVET transferee — ₱20k flat rate from fee_config
            $flatRateKey = $isSHSStudent ? 'SHS' : 'TVET';
            $fcSHS = loadFeeConfig($conn, $flatRateKey);
            $flatRate  = (float)($fcSHS['transferee_flat_rate']['value'] ?? 20000);
            $instFeeSHS = (float)($fcSHS['installment_fee']['value'] ?? 750);
            // Read payment plan for installment surcharge
            $planSHSRes = $conn->query("SELECT payment_plan FROM students WHERE id=$studentDbId LIMIT 1");
            $planSHSRow = $planSHSRes ? $planSHSRes->fetch_assoc() : [];
            $payPlanSHS = trim($planSHSRow['payment_plan'] ?? 'full');
            $instFeeSHSApplied = ($payPlanSHS === 'installment') ? $instFeeSHS : 0.0;
            $totalAssSHS = $flatRate + $instFeeSHSApplied;
            // Read actual paid
            $paidSHSRes = $conn->query("SELECT COALESCE(SUM(amount),0) AS p FROM installment_payments WHERE student_id=$studentDbId");
            $paidSHS = (float)(($paidSHSRes ? $paidSHSRes->fetch_assoc()['p'] : 0) ?? 0);
            $balSHS  = max(0.0, $totalAssSHS - $paidSHS);
            $fees = [
                'units'           => 0,
                'tuitionFee'      => 0,
                'tuitionBase'     => 0,
                'miscFee'         => 0,
                'registrationFee' => 0,
                'laboratoryFee'   => 0,
                'energyFee'       => 0,
                'extraFees'       => [],
                'subtotal'        => $flatRate,
                'discount'        => 0,
                'scholarship'     => 0,
                'installmentFee'  => $instFeeSHSApplied,
                'totalAssessment' => $totalAssSHS,
                'totalFees'       => $totalAssSHS,
                'amountPaid'      => $paidSHS,
                'remainingBal'    => $balSHS,
                'dueDate'         => date('Y-m-d', strtotime('+30 days')),
                'paymentStatus'   => $balSHS <= 0 ? 'Fully Paid' : ($paidSHS > 0 ? 'Partial' : $student['payment_status']),
                'isFlatRate'      => true,
                'flatRateLabel'   => 'Government Transferee Flat Rate',
            ];
        } else {
            // SHS non-transferee  → FREE (K-12 DepEd voucher)
            // TVET non-transferee → FREE (TESDA/PESFA/STEP government scholarship)
            $fees = [
                'units'           => 0,
                'tuitionFee'      => 0,
                'tuitionBase'     => 0,
                'miscFee'         => 0,
                'registrationFee' => 0,
                'laboratoryFee'   => 0,
                'energyFee'       => 0,
                'extraFees'       => [],
                'subtotal'        => 0,
                'discount'        => 0,
                'scholarship'     => 0,
                'installmentFee'  => 0,
                'totalAssessment' => 0,
                'totalFees'       => 0,
                'amountPaid'      => 0,
                'remainingBal'    => 0,
                'dueDate'         => null,
                'paymentStatus'   => 'Free',
                'isFree'          => true,
                'freeLabel'       => $isSHSStudent
                    ? 'Free – K-12 Government Subsidy (SHS Voucher)'
                    : 'Free – TESDA Government Scholarship (PESFA/STEP)',
            ];
        }
    }

    $academic = [
        'yearLevel'    => $displayYearLevel,       // Grade 11/12 for SHS; NC level for TVET; 1st Year etc for College
        'rawYearLevel' => $rawYearLevel,            // original DB value for internal use
        'gpa'          => (float) $student['gpa'],
        'totalCredits' => $totalCredits,
        'courseCount'  => count($courses),
        'status'       => $enrollmentStatusDisplay,
        'semester'     => $semesterStr,
        'academicYear' => $academicYear,
        'isSHS'        => $isSHSStudent,
        'isTVET'       => $isTVETStudent,
        'isSHSorTVET'  => $isSHSorTVET,
        'allowAddDrop' => !$isSHSorTVET,           // SHS/TVET have no add/drop system
    ];

    // ── 8. Class block assigned to this student ──────────────
    $blockInfo = null;
    $blkStmt = $conn->prepare("
        SELECT b.id, b.block_code, b.program, b.year_level,
               b.semester, b.school_year, b.max_capacity,
               COUNT(s2.id) AS enrolled_count
        FROM   students s
        LEFT JOIN class_blocks b  ON b.id = s.block_id
        LEFT JOIN students    s2 ON s2.block_id = b.id
               AND s2.enrollment_status NOT IN ('Graduated','Dropped','Inactive')
        WHERE  s.id = ?
        GROUP  BY s.id, b.id
        LIMIT  1
    ");
    if ($blkStmt) {
        $blkStmt->bind_param('i', $studentDbId);
        $blkStmt->execute();
        $blkRow = $blkStmt->get_result()->fetch_assoc();
        $blkStmt->close();
        if ($blkRow && $blkRow['id']) {
            $blockInfo = [
                'id'            => (int) $blkRow['id'],
                'blockCode'     => $blkRow['block_code'],
                'program'       => $blkRow['program'],
                'yearLevel'     => $blkRow['year_level'],
                'semester'      => $blkRow['semester'],
                'schoolYear'    => $blkRow['school_year'],
                'maxCapacity'   => (int) $blkRow['max_capacity'],
                'enrolledCount' => (int) $blkRow['enrolled_count'],
            ];
        }
    }

    // ── 9. Apply privacy then send response ─────────────────
    // Student always views own record — apply privacy to PII fields only (email, phone, etc.)
    // Financial amounts are sent as real numbers; Angular fmt() handles visual masking
    $isOwner = ($authUser && $authUser['role'] === 'student');
    $student = applyPrivacy($student, $authUser, 'student', $isOwner);
    // $fees and $paymentHistory passed through as-is — amounts are masked by Angular

    while (ob_get_level() > 0) { ob_end_clean(); }
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
            // FIX DASH-PAYSTATUS-01: Use the computed fees.paymentStatus (derived from
            // actual paid vs total_assessment) instead of the raw students.payment_status
            // column. The DB column can lag behind (e.g. installment students remain
            // 'Pending' in the DB after Accounting verifies, until they're fully paid),
            // causing the dashboard to show "Pending" even after payment is confirmed.
            'paymentStatus'    => $fees['paymentStatus'] ?? $student['payment_status'],
            'approvalStatus'   => $student['approval_status'],
            'studentType'      => $student['student_type'],
            'studentCategory'  => strtoupper(trim($student['student_category'] ?? '')),
            'enrollmentDate'   => $student['enrollment_date'],
            'isIrregular'      => $isIrregular,
            // SHS/TVET specific fields
            'strand'           => $student['strand']            ?? null,
            'learningDelivery' => $student['learning_delivery'] ?? null,
            'gradeLevel'       => $displayYearLevel,            // Grade 11/12 for SHS
        ],
        'academic'       => $academic,
        'courses'        => $courses,       // all enrolled courses (for schedule + course list)
        'nextClass'      => $nextClass,     // nearest upcoming class
        'fees'           => $fees,          // financial breakdown (full detail)
        // ── financialSummary: compact block for the dashboard Financial Summary card ──
        // Always present regardless of student category so the Angular component
        // never has to null-check before rendering the card.
        'financialSummary' => [
            'totalAssessment' => $fees['totalAssessment'] ?? 0,
            'amountPaid'      => $fees['amountPaid']      ?? 0,
            'remainingBal'    => $fees['remainingBal']    ?? 0,
            'paymentStatus'   => $fees['paymentStatus']   ?? $student['payment_status'],
            'isFree'          => (bool)($fees['isFree']      ?? false),
            'isFlatRate'      => (bool)($fees['isFlatRate']  ?? false),
            'freeLabel'       => $fees['freeLabel']       ?? null,
            'flatRateLabel'   => $fees['flatRateLabel']   ?? null,
            'installmentFee'  => $fees['installmentFee']  ?? 0,
            'dueDate'         => $fees['dueDate']         ?? null,
        ],
        'paymentHistory' => $paymentHistory, // all payment records
        'block'          => $blockInfo       // class block/section (null if not yet assigned)
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
        // Seed defaults only if table is empty
    $cntRes = $conn->query("SELECT COUNT(*) AS c FROM announcements");
    $cnt = $cntRes ? (int)($cntRes->fetch_assoc()['c'] ?? 0) : 0;
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

    while (ob_get_level() > 0) { ob_end_clean(); }
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
        // Seed defaults only if table is empty
    $cntRes = $conn->query("SELECT COUNT(*) AS c FROM school_events");
    $cnt = $cntRes ? (int)($cntRes->fetch_assoc()['c'] ?? 0) : 0;
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

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'events' => $events]);
    $conn->close();
    exit();
}

// ================================================================
//  ADMIN ANNOUNCEMENT CRUD
// ================================================================
if ($action === 'add_announcement') {
    $data  = json_decode(file_get_contents('php://input'), true) ?? [];
    $title = trim($data['title']    ?? '');
    $msg   = trim($data['message']  ?? '');
    $date  = trim($data['date']     ?? date('Y-m-d'));
    $type  = in_array($data['type'] ?? '', ['enrollment','payment','school','department','system']) ? $data['type'] : 'school';
    $pri   = in_array($data['priority'] ?? '', ['high','normal','low']) ? $data['priority'] : 'normal';
    $icon  = trim($data['icon']     ?? '📢');
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$title || !$msg) { echo json_encode(['success'=>false,'message'=>'title and message required']); exit(); }
    $annIns = $conn->prepare("INSERT INTO announcements (title,message,date,type,priority,icon) VALUES (?,?,?,?,?,?)");
    $annIns->bind_param('ssssss', $title, $msg, $date, $type, $pri, $icon);
    $annIns->execute();
    $newId = (int)$conn->insert_id;
    $annIns->close();
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success'=>true,'id'=>$newId]);
    $conn->close(); exit();
}

if ($action === 'update_announcement') {
    $data  = json_decode(file_get_contents('php://input'), true) ?? [];
    $id    = (int)($data['id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$id) { echo json_encode(['success'=>false,'message'=>'id required']); exit(); }
    $title = trim($data['title']   ?? '');
    $msg   = trim($data['message'] ?? '');
    $date  = trim($data['date']    ?? date('Y-m-d'));
    $type  = in_array($data['type'] ?? '', ['enrollment','payment','school','department','system']) ? $data['type'] : 'school';
    $pri   = in_array($data['priority'] ?? '', ['high','normal','low']) ? $data['priority'] : 'normal';
    $icon  = trim($data['icon']    ?? '📢');
    $annUpd = $conn->prepare("UPDATE announcements SET title=?,message=?,date=?,type=?,priority=?,icon=? WHERE id=?");
    $annUpd->bind_param('ssssssi', $title, $msg, $date, $type, $pri, $icon, $id);
    $annUpd->execute();
    $annUpd->close();
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success'=>true]);
    $conn->close(); exit();
}

if ($action === 'delete_announcement') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($data['id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$id) { echo json_encode(['success'=>false,'message'=>'id required']); exit(); }
    $annDel = $conn->prepare("DELETE FROM announcements WHERE id=?");
    $annDel->bind_param('i', $id);
    $annDel->execute();
    $annDel->close();
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success'=>true]);
    $conn->close(); exit();
}

// ================================================================
//  ADMIN EVENTS CRUD
// ================================================================
if ($action === 'add_event') {
    $data  = json_decode(file_get_contents('php://input'), true) ?? [];
    $title = trim($data['title']       ?? '');
    $date  = trim($data['event_date']  ?? date('Y-m-d'));
    $type  = in_array($data['type'] ?? '', ['enrollment','payment','exam','activity','holiday']) ? $data['type'] : 'activity';
    $desc  = trim($data['description'] ?? '');
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$title || !$date) { echo json_encode(['success'=>false,'message'=>'title and event_date required']); exit(); }
    $evtIns = $conn->prepare("INSERT INTO school_events (title,event_date,type,description) VALUES (?,?,?,?)");
    $evtIns->bind_param('ssss', $title, $date, $type, $desc);
    $evtIns->execute();
    $evtId = (int)$conn->insert_id;
    $evtIns->close();
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success'=>true,'id'=>$evtId]);
    $conn->close(); exit();
}

if ($action === 'update_event') {
    $data  = json_decode(file_get_contents('php://input'), true) ?? [];
    $id    = (int)($data['id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$id) { echo json_encode(['success'=>false,'message'=>'id required']); exit(); }
    $title = trim($data['title']       ?? '');
    $date  = trim($data['event_date']  ?? date('Y-m-d'));
    $type  = in_array($data['type'] ?? '', ['enrollment','payment','exam','activity','holiday']) ? $data['type'] : 'activity';
    $desc  = trim($data['description'] ?? '');
    $evtUpd = $conn->prepare("UPDATE school_events SET title=?,event_date=?,type=?,description=? WHERE id=?");
    $evtUpd->bind_param('ssssi', $title, $date, $type, $desc, $id);
    $evtUpd->execute();
    $evtUpd->close();
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success'=>true]);
    $conn->close(); exit();
}

if ($action === 'delete_event') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($data['id'] ?? 0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!$id) { echo json_encode(['success'=>false,'message'=>'id required']); exit(); }
    $evtDel = $conn->prepare("DELETE FROM school_events WHERE id=?");
    $evtDel->bind_param('i', $id);
    $evtDel->execute();
    $evtDel->close();
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success'=>true]);
    $conn->close(); exit();
}

// ── Unknown action ────────────────────────────────────────────
while (ob_get_level() > 0) { ob_end_clean(); }
echo json_encode(['success' => false, 'message' => "Unknown action: '$action'"]);
$conn->close();
?>