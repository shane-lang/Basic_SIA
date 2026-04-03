<?php
// =============================================================================
// helpers.php — Shared utility functions used across all SIA API files.
//
// Include this ONCE in each entry-point after config.php:
//   require_once __DIR__ . '/helpers.php';
//
// Functions defined here:
//   cleanCode($code)                        — strips program disambiguation suffixes
//   loadFeeConfig(mysqli, string): array    — reads fee rates from fee_config table
//   safeStudentId(mysqli, array): int       — resolves student_id or user_id → students.id
//   jsonOut(array, int): never              — safe JSON response with ob cleanup
// =============================================================================

// ── cleanCode() ──────────────────────────────────────────────────────────────
// Strips internal disambiguation suffixes from course codes.
// e.g. GE103-BMD → GE103, PE1-CA → PE1, NSTP1-BSIT → NSTP1
// Legitimate curriculum codes with dashes (RE-FUN013, GE-ENG013,
// BN-MGT013, IT-CSA013, AC-TAX013) are NOT affected.
if (!function_exists('cleanCode')) {
    function cleanCode($code) {
        if (!$code) return $code;
        // FULL suffix list — kept in one place to avoid drift across files
        static $suffixes = [
            '-BMD','-CA','-BSA','-BSCA','-BSE','-CIMT',
            '-BSIT','-BSREM','-ICTD','-HMD','-CED','-CAS','-GEN',
        ];
        $upper = strtoupper($code);
        foreach ($suffixes as $s) {
            if (substr($upper, -strlen($s)) === $s) {
                return substr($code, 0, strlen($code) - strlen($s));
            }
        }
        return $code;
    }
}

// ── loadFeeConfig() ───────────────────────────────────────────────────────────
// Reads active fee rows from fee_config table for a given category.
// Seeds default values on first run (when table is empty).
// Returns associative array keyed by fee_key.
if (!function_exists('loadFeeConfig')) {
    function loadFeeConfig(mysqli $conn, string $category): array {
        $cntRes = $conn->query("SELECT COUNT(*) AS c FROM fee_config");
        $cnt = (int)(($cntRes ? $cntRes->fetch_assoc()['c'] : 0) ?? 0);
        if ($cnt === 0) {
            $conn->query("INSERT IGNORE INTO fee_config
                (category,fee_key,fee_label,value,is_per_unit,applies_to,description,sort_order) VALUES
                ('College','tuition_rate_per_unit','Tuition Fee (per unit)',650,1,'All','Charged per enrolled unit',1),
                ('College','misc_fee','Miscellaneous Fee',6688,0,'All','Fixed miscellaneous fee',2),
                ('College','reg_fee','Registration Fee',700,0,'All','Fixed registration fee',3),
                ('College','lab_fee_per_room','Laboratory Fee (per lab room)',1900,0,'All','Per laboratory room on campus',4),
                ('College','energy_rate_per_unit','Energy Fee (per unit)',63,1,'All','units × ₱21 × 3 terms = ₱63/unit',5),
                ('College','installment_fee','Installment Surcharge',750,0,'All','Added when payment plan is installment',6),
                ('SHS','transferee_flat_rate','Transferee Flat Rate',20000,0,'Transferee','Flat fee for SHS transferees',1),
                ('SHS','installment_fee','Installment Surcharge',750,0,'All','Added when payment plan is installment',2),
                ('TVET','misc_fee','Miscellaneous Fee',3500,0,'All','Fixed miscellaneous fee for TVET',1),
                ('TVET','reg_fee','Registration Fee',500,0,'All','Fixed registration fee for TVET',2),
                ('TVET','installment_fee','Installment Surcharge',500,0,'All','Added when payment plan is installment',3),
                ('TVET','transferee_flat_rate','Transferee Flat Rate',20000,0,'Transferee','Flat fee for TVET transferees',4)");
        }

        $stmt = $conn->prepare("SELECT * FROM fee_config WHERE category = ? AND is_active = 1 ORDER BY sort_order");
        if (!$stmt) return [];
        $stmt->bind_param('s', $category);
        $stmt->execute();
        $res = $stmt->get_result();
        $cfg = [];
        while ($r = $res->fetch_assoc()) {
            $cfg[$r['fee_key']] = $r;
        }
        $stmt->close();
        return $cfg;
    }
}

// ── safeStudentId() ───────────────────────────────────────────────────────────
// Resolves student_id from request params (student_id or user_id fallback).
// Returns 0 if not found.
if (!function_exists('safeStudentId')) {
    function safeStudentId(mysqli $conn, array $params): int {
        $sid = (int)($params['student_id'] ?? 0);
        if ($sid > 0) return $sid;
        $uid = (int)($params['user_id'] ?? 0);
        if (!$uid) return 0;
        $st = $conn->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
        if (!$st) return 0;
        $st->bind_param('i', $uid);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        return $r ? (int)$r['id'] : 0;
    }
}

// ── jsonOut() ─────────────────────────────────────────────────────────────────
// Safe JSON output: clears any buffered output (PHP notices/warnings),
// sets HTTP status + Content-Type header, echoes JSON, then exits.
if (!function_exists('jsonOut')) {
    function jsonOut(array $data, int $code = 200): never {
        while (ob_get_level() > 0) { ob_end_clean(); }
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
}