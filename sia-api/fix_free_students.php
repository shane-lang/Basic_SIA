<?php
// =============================================================================
// fix_free_students.php — ONE-TIME migration script
//
// Run this ONCE via browser: http://localhost/sia-api/fix_free_students.php
// or via CLI: php fix_free_students.php
//
// What it does:
//   1. Finds all TVET non-transferee and SHS non-transferee students
//   2. Deletes their stale tuition_fees rows (written when TVET-COLLEGE-FLOW-01
//      was mistakenly active — these had unit-based fees like ₱29,572)
//   3. Deletes their stale soa_snapshots rows with total_assessment > 0
//      (the ON DUPLICATE KEY guard blocks ₱0 writes if non-zero exists)
//   4. Sets their approval_status=Approved, payment_status=Paid, enrollment_status=Enrolled
//   5. Prints a summary of what was fixed
//
// Safe to run multiple times — all operations are idempotent.
// =============================================================================

require_once __DIR__ . '/config.php'; // provides $conn
require_once __DIR__ . '/soa_helper.php'; // provides saveSoaSnapshot()
require_once __DIR__ . '/helpers.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Free Student Fix Script ===\n";
echo "Running at: " . date('Y-m-d H:i:s') . "\n\n";

// ── Find all free students (TVET non-transferee + SHS non-transferee) ────────
$res = $conn->query("
    SELECT id, student_number, first_name, last_name,
           student_category, student_type, semester,
           approval_status, payment_status, enrollment_status
    FROM students
    WHERE (
            (student_category = 'TVET' AND student_type != 'Transferee')
         OR (student_category = 'SHS'  AND student_type != 'Transferee')
          )
      AND is_anonymized = 0
    ORDER BY student_category, id
");

if (!$res || $res->num_rows === 0) {
    echo "No free TVET/SHS non-transferee students found.\n";
    exit;
}

$fixed = 0;
$skipped = 0;

while ($s = $res->fetch_assoc()) {
    $sid      = (int)$s['id'];
    $sNum     = $s['student_number'];
    $name     = $s['first_name'] . ' ' . $s['last_name'];
    $cat      = $s['student_category'];
    $type     = $s['student_type'];
    $sem      = $s['semester'] ?? '';
    $semEsc   = $conn->real_escape_string($sem);

    echo "[$cat | $type] $sNum — $name\n";

    // 1. Delete stale tuition_fees
    $tfDel = $conn->query("DELETE FROM tuition_fees WHERE student_id = $sid");
    $tfRows = $conn->affected_rows;
    if ($tfRows > 0) {
        echo "  ✓ Deleted $tfRows tuition_fees row(s)\n";
    } else {
        echo "  — No tuition_fees rows to delete\n";
    }

    // 2. Delete stale soa_snapshots (only non-zero ones — ₱0 snapshots are correct)
    if ($sem !== '') {
        $snapDel = $conn->query("DELETE FROM soa_snapshots WHERE student_id = $sid AND total_assessment > 0");
        $snapRows = $conn->affected_rows;
        if ($snapRows > 0) {
            echo "  ✓ Deleted $snapRows stale soa_snapshots row(s)\n";
        } else {
            echo "  — No stale snapshots to delete\n";
        }
    }

    // 2b. Delete stale payment_schedules (frozen ₱29,572 or any non-zero row)
    $psDel = $conn->query("DELETE FROM payment_schedules WHERE student_id = $sid");
    $psRows = $conn->affected_rows;
    if ($psRows > 0) {
        echo "  ✓ Deleted $psRows payment_schedules row(s)\n";
    } else {
        echo "  — No payment_schedules rows to delete\n";
    }

    // 3. Auto-approve if not already
    if ($s['approval_status'] !== 'Approved') {
        $conn->query("UPDATE students
            SET approval_status='Approved', payment_status='Paid', enrollment_status='Enrolled'
            WHERE id=$sid");
        echo "  ✓ Set Approved / Paid / Enrolled\n";
    } else {
        echo "  — Already Approved\n";
    }

    // 4. Re-seed correct ₱0 snapshot
    if ($sem !== '') {
        $seeded = saveSoaSnapshot($conn, $sid, $sem);
        echo "  " . ($seeded ? "✓ ₱0 snapshot saved for: $sem" : "✗ snapshot seed failed") . "\n";
    } else {
        echo "  — No semester set, skipping snapshot\n";
    }

    echo "\n";
    $fixed++;
}

echo "=== Done ===\n";
echo "Fixed: $fixed student(s)\n";

$conn->close();