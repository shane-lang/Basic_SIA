<?php
// =============================================================================
// soa_helper.php — Standalone SOA snapshot helper.
//
// Contains ONLY saveSoaSnapshot(). No auth checks, no HTTP routing, no
// top-level code with side effects — safe to include from any file.
//
// Both enrollment.php and Accounting.php require_once this file.
// Neither should require_once the other.
//
// Depends on:
//   • helpers.php    — for cleanCode()
//   • config.php     — for $conn (caller's responsibility to include first)
// =============================================================================

if (!function_exists('saveSoaSnapshot')):

/**
 * Create (or refresh) a frozen SOA row in soa_snapshots for a
 * specific student + semester.
 *
 * The snapshot stores every fee line item, the enrolled subjects
 * JSON, payment history JSON, and the computed balance — all as
 * they stood at the moment this function is called.
 *
 * Safe to call multiple times: uses ON DUPLICATE KEY UPDATE so
 * the most recent call always wins (allows corrections before
 * the semester is fully closed).
 *
 * @param mysqli $conn
 * @param int    $student_id   students.id
 * @param string $semester     Full semester label (e.g. "1st Semester, AY 2025-2026")
 *                             Defaults to students.semester when empty.
 * @return bool  true on success, false if no data was found to snapshot
 */
function saveSoaSnapshot(mysqli $conn, int $student_id, string $semester = ''): bool {

    // ── Auto-create soa_snapshots table (safe no-op if already exists) ──────
    $conn->query("CREATE TABLE IF NOT EXISTS soa_snapshots (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        student_id       INT         NOT NULL,
        semester         VARCHAR(100) NOT NULL,
        -- Fee breakdown (mirrors tuition_fees columns)
        units            INT          NOT NULL DEFAULT 0,
        tuition_fee      DECIMAL(10,2) NOT NULL DEFAULT 0,
        miscellaneous_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
        registration_fee  DECIMAL(10,2) NOT NULL DEFAULT 0,
        laboratory_fee    DECIMAL(10,2) NOT NULL DEFAULT 0,
        energy_fee        DECIMAL(10,2) NOT NULL DEFAULT 0,
        subtotal         DECIMAL(10,2) NOT NULL DEFAULT 0,
        discount         DECIMAL(10,2) NOT NULL DEFAULT 0,
        installment_fee  DECIMAL(10,2) NOT NULL DEFAULT 0,
        total_assessment DECIMAL(10,2) NOT NULL DEFAULT 0,
        -- Payment summary
        total_paid       DECIMAL(10,2) NOT NULL DEFAULT 0,
        balance          DECIMAL(10,2) NOT NULL DEFAULT 0,
        payment_plan     VARCHAR(20)  NOT NULL DEFAULT 'full',
        payment_status   VARCHAR(30)  NOT NULL DEFAULT 'Pending',
        -- Enrolled subjects snapshot (JSON array)
        subjects_json    MEDIUMTEXT   DEFAULT NULL,
        -- Payment receipts snapshot (JSON array of installment_payments rows)
        payments_json    MEDIUMTEXT   DEFAULT NULL,
        -- Metadata
        snapshotted_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_student_semester (student_id, semester),
        INDEX idx_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── Resolve semester if not provided ──────────────────────────────────────
    if ($semester === '') {
        $semR = $conn->prepare("SELECT semester FROM students WHERE id = ? LIMIT 1");
        $semR->bind_param('i', $student_id);
        $semR->execute();
        $semester = trim($semR->get_result()->fetch_assoc()['semester'] ?? '');
        $semR->close();
    }
    if ($semester === '') return false;

    // ── Read tuition_fees row — MUST be scoped to the requested semester ────────
    // FIX SOA-TF-SEMESTER-01: tuition_fees has a semester column but the original
    // query used no WHERE semester clause, so it always returned the current live row
    // (student_id is UNIQUE — one active row per student, overwritten each re-enroll).
    // When reEnroll() called saveSoaSnapshot($conn, $id, $pastSem) before deleting
    // tuition_fees, it read the NEW semester's fees (just computed by computeFeesNew)
    // instead of the fees that applied during $pastSem — making every past-semester
    // snapshot show the current unit count and fees.
    //
    // Priority:
    //   1. Exact semester match  → the Accounting-written row for that specific term
    //   2. NULL/empty semester   → legacy rows from before semester column existed
    //   3. No match              → $tf = null → falls through to free-student check below
    $semEscTf = $conn->real_escape_string($semester);
    $tfRes = $conn->query(
        "SELECT * FROM tuition_fees
         WHERE student_id = $student_id
           AND (semester = '$semEscTf' OR semester IS NULL OR semester = '')
         ORDER BY (semester = '$semEscTf') DESC, id DESC
         LIMIT 1"
    );
    $tf = $tfRes ? $tfRes->fetch_assoc() : null;
    // If the exact-semester row was not found (new semester not yet computed) but there
    // is a current live row (NULL-semester or different semester), do NOT use it —
    // it belongs to the current term and would corrupt the past snapshot.
    // Return false so the caller knows no fee data exists for this semester yet.
    if ($tf !== null && !empty($tf['semester']) && $tf['semester'] !== $semester) {
        $tf = null; // wrong semester row — refuse to use it
    }

    if (!$tf) {
        // BUG-SOA-FREE-01 FIX: SHS (non-transferee) and TVET NC-II/NC-III free students
        // have no tuition_fees row (getSHSFee deletes it; getTVETFee never creates one).
        // Rather than returning false (which leaves the SOA blank), create a ₱0 snapshot
        // so getSoaSnapshot() can return meaningful data ("Free – Government Subsidy").
        // Check whether this student is genuinely free before creating the ₱0 snapshot.
        $catChk = $conn->prepare("SELECT student_category, student_type FROM students WHERE id = ? LIMIT 1");
        $catChk->bind_param('i', $student_id);
        $catChk->execute();
        $catRow = $catChk->get_result()->fetch_assoc();
        $catChk->close();
        $cat   = strtoupper(trim($catRow['student_category'] ?? ''));
        $stype = trim($catRow['student_type'] ?? '');
        $isFreeStudent = ($cat === 'SHS' && $stype !== 'Transferee')
                      || ($cat === 'TVET' && $stype !== 'Transferee');
        if (!$isFreeStudent) return false; // College / transferee with no fees yet — skip

        // Build enrolled subjects list (same as below)
        $semEscFree = $conn->real_escape_string($semester);
        $subResFree = $conn->query("
            SELECT c.code, c.name, c.credits,
                   COALESCE(c.lec_units, c.credits) AS lec_units,
                   COALESCE(c.lab_units, 0)         AS lab_units,
                   e.status
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            WHERE e.student_id = $student_id
              AND e.status IN ('Enrolled','Pending','Completed')
              AND e.semester = '$semEscFree'
            ORDER BY c.code
        ");
        $subjectsFree = [];
        if ($subResFree) {
            while ($row = $subResFree->fetch_assoc()) {
                $row['code'] = cleanCode($row['code']);
                $subjectsFree[] = $row;
            }
        }
        $subjectsJsonFree = json_encode($subjectsFree);
        $paymentsJsonFree = json_encode([]);

        $stmtFree = $conn->prepare("
            INSERT INTO soa_snapshots
                (student_id, semester, units, tuition_fee, miscellaneous_fee,
                 registration_fee, laboratory_fee, energy_fee, subtotal, discount,
                 installment_fee, total_assessment, total_paid, balance,
                 payment_plan, payment_status, subjects_json, payments_json)
            VALUES (?, ?, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 'full', 'Free', ?, ?)
            ON DUPLICATE KEY UPDATE
                total_paid       = 0,
                balance          = 0,
                payment_status   = 'Free',
                subjects_json    = VALUES(subjects_json),
                payments_json    = VALUES(payments_json),
                snapshotted_at   = NOW()
        ");
        if (!$stmtFree) return false;
        $stmtFree->bind_param('isss', $student_id, $semester, $subjectsJsonFree, $paymentsJsonFree);
        $ok = $stmtFree->execute();
        $stmtFree->close();
        return $ok;
    }

    $units       = (int)$tf['units'];
    $tuition     = (float)$tf['tuition_fee'];
    $misc        = (float)$tf['miscellaneous_fee'];
    $reg         = (float)$tf['registration_fee'];
    $lab         = (float)$tf['laboratory_fee'];
    $energy      = (float)$tf['energy_fee'];
    $subtotal    = (float)$tf['subtotal'];
    $discount    = (float)$tf['discount'];
    $instFee     = (float)$tf['installment_fee'];
    $totalAssess = (float)$tf['total_assessment'];

    // ── Enrolled subjects for this semester ───────────────────────────────────
    $semEsc = $conn->real_escape_string($semester);
    $subRes = $conn->query("
        SELECT c.code, c.name, c.credits,
               COALESCE(c.lec_units, c.credits) AS lec_units,
               COALESCE(c.lab_units, 0)         AS lab_units,
               e.status
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = $student_id
          AND e.status IN ('Enrolled','Pending','Completed')
          AND e.semester = '$semEsc'
        ORDER BY c.code
    ");
    $subjects = [];
    if ($subRes) {
        while ($row = $subRes->fetch_assoc()) {
            $row['code'] = cleanCode($row['code']);
            $subjects[]  = $row;
        }
    }

    // ── Payment history for this semester ─────────────────────────────────────
    $payRes = $conn->query("
        SELECT or_ar_number, or_ar_type, exam_period, payment_date, payment_method, amount, semester
        FROM installment_payments
        WHERE student_id = $student_id
          AND semester   = '$semEsc'
        ORDER BY payment_date ASC, id ASC
    ");
    $payments  = [];
    $totalPaid = 0.0;
    if ($payRes) {
        while ($row = $payRes->fetch_assoc()) {
            $totalPaid += (float)$row['amount'];
            $payments[] = $row;
        }
    }

    // Also pull GCash-verified payments not yet in installment_payments
    $plRes = $conn->query("
        SELECT pl.gcash_date AS payment_date, pl.payment_method,
               pl.gcash_amount AS amount, pl.semester,
               COALESCE(pl.or_ar_number, CONCAT('OR-', pl.id)) AS or_ar_number,
               'OR' AS or_ar_type, 'Full' AS exam_period
        FROM payment_logs pl
        WHERE pl.student_id = $student_id
          AND pl.status     = 'Verified'
          AND pl.semester   = '$semEsc'
          AND NOT EXISTS (
              SELECT 1 FROM installment_payments ip
              WHERE ip.payment_log_id = pl.id
          )
    ");
    if ($plRes) {
        while ($row = $plRes->fetch_assoc()) {
            $totalPaid += (float)$row['amount'];
            $payments[] = $row;
        }
    }

    $balance   = max(0.0, $totalAssess - $totalPaid);
    $payStatus = $balance <= 0 && $totalPaid > 0 ? 'Fully Paid'
               : ($totalPaid > 0 ? 'Partially Paid' : 'Pending');

    // Payment plan — derive from the historical tuition_fees row, NOT students.payment_plan.
    // FIX SOA-PLAN-HIST-01: students.payment_plan is reset to NULL on re-enroll and then
    // updated to the new semester's choice. Reading it for a past-semester snapshot returns
    // the CURRENT plan, not the one in effect that semester.
    // Reliable proxy: if installment_fee > 0 in the stored tuition_fees row, it was installment.
    $payPlan = ($instFee > 0) ? 'installment' : 'full';

    $subjectsJson = json_encode($subjects);
    $paymentsJson = json_encode($payments);

    // ── Upsert ────────────────────────────────────────────────────────────────
    // FIX SOA-FREEZE-01: On DUPLICATE KEY, NEVER overwrite fee breakdown columns
    // (units, tuition_fee, miscellaneous_fee, etc.) for a snapshot that already exists.
    // These are frozen at the moment the semester closes (reEnroll → saveSoaSnapshot).
    // Only payment-tracking fields (total_paid, balance, payment_status, payments_json)
    // and subjects_json should be refreshable — payments are recorded after the snapshot
    // is first written and must be visible in the SOA immediately.
    // Fee columns are kept via IF(units=0, VALUES(units), units) — allows the very
    // first write (when fees are 0) to populate, but subsequent calls cannot overwrite
    // a non-zero value with either 0 or a different semester's fee total.
    $stmt = $conn->prepare("
        INSERT INTO soa_snapshots
            (student_id, semester, units, tuition_fee, miscellaneous_fee,
             registration_fee, laboratory_fee, energy_fee, subtotal, discount,
             installment_fee, total_assessment, total_paid, balance,
             payment_plan, payment_status, subjects_json, payments_json)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            units             = IF(units = 0,             VALUES(units),             units),
            tuition_fee       = IF(tuition_fee = 0,       VALUES(tuition_fee),       tuition_fee),
            miscellaneous_fee = IF(miscellaneous_fee = 0, VALUES(miscellaneous_fee), miscellaneous_fee),
            registration_fee  = IF(registration_fee = 0,  VALUES(registration_fee),  registration_fee),
            laboratory_fee    = IF(laboratory_fee = 0,    VALUES(laboratory_fee),    laboratory_fee),
            energy_fee        = IF(energy_fee = 0,        VALUES(energy_fee),        energy_fee),
            subtotal          = IF(subtotal = 0,          VALUES(subtotal),          subtotal),
            discount          = IF(discount = 0,          VALUES(discount),          discount),
            installment_fee   = IF(installment_fee = 0,   VALUES(installment_fee),   installment_fee),
            total_assessment  = IF(total_assessment = 0,  VALUES(total_assessment),  total_assessment),
            payment_plan      = IF(payment_plan = 'full' AND VALUES(payment_plan) = 'installment', VALUES(payment_plan), payment_plan),
            total_paid        = VALUES(total_paid),
            balance           = VALUES(balance),
            payment_status    = VALUES(payment_status),
            subjects_json     = IF(subjects_json IS NULL OR subjects_json = '[]', VALUES(subjects_json), subjects_json),
            payments_json     = VALUES(payments_json),
            snapshotted_at    = NOW()
    ");
    if (!$stmt) return false;

    $stmt->bind_param(
        'isddddddddddddssss',
        $student_id, $semester,
        $units, $tuition, $misc, $reg, $lab, $energy, $subtotal,
        $discount, $instFee, $totalAssess,
        $totalPaid, $balance,
        $payPlan, $payStatus,
        $subjectsJson, $paymentsJson
    );
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

endif; // function_exists('saveSoaSnapshot')