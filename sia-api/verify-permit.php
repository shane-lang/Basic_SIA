<?php
// =============================================================================
// verify-permit.php — Public QR Code Verification Page
//
// Called when anyone scans an exam permit QR code.
// URL: /sia-api/verify-permit?id=<permit_identifier>
//
// NO LOGIN REQUIRED — this is intentionally public so that faculty/proctors
// can scan and verify a permit without needing a system account.
// Only non-sensitive, permit-level info is displayed.
// =============================================================================

require_once __DIR__ . '/config.php';
// Override Content-Type — this endpoint returns HTML, not JSON
header('Content-Type: text/html; charset=utf-8', true);

$permitIdentifier = trim($_GET['id'] ?? '');

// ── Fetch permit by permit_identifier ────────────────────────────────────────
$permit   = null;
$courses  = [];
$notFound = false;

if ($permitIdentifier === '') {
    $notFound = true;
} else {
    $esc = $conn->real_escape_string($permitIdentifier);

    $pRes = $conn->query("
        SELECT ep.id, ep.exam_period, ep.school_year, ep.semester,
               ep.status, ep.permit_identifier, ep.approved_at,
               s.student_number, s.first_name, s.last_name,
               s.program, s.year_level, s.id AS student_id,
               COALESCE(sp.first_name, f2.first_name) AS approved_by_first,
               COALESCE(sp.last_name,  f2.last_name)  AS approved_by_last
        FROM exam_permits ep
        JOIN students s ON ep.student_id = s.id
        LEFT JOIN users u         ON ep.approved_by = u.id
        LEFT JOIN staff_profiles sp ON sp.user_id = u.id
        LEFT JOIN faculty f2        ON f2.user_id  = u.id
        WHERE ep.permit_identifier = '$esc'
        LIMIT 1
    ");

    $permit = $pRes ? $pRes->fetch_assoc() : null;

    if (!$permit) {
        $notFound = true;
    } else {
        // ── Parse semester label + school year ────────────────────────────
        $semLabel   = $permit['semester']   ?? '';
        $schoolYear = $permit['school_year'] ?? '';
        if (preg_match('/^(.+?),\s*AY\s*([\d]{4}-[\d]{4})/i', $semLabel, $m)) {
            $semLabel   = trim($m[1]);
            $schoolYear = trim($m[2]);
        }
        $permit['semester']    = $semLabel;
        $permit['school_year'] = $schoolYear;

        // ── Fetch enrolled courses for this permit ─────────────────────────
        $sid    = (int)$permit['student_id'];
        $semEsc = $conn->real_escape_string($semLabel);
        $ayEsc  = $conn->real_escape_string($schoolYear);

        $cRes = $conn->query("
            SELECT DISTINCT c.code, c.name,
                   CONCAT(COALESCE(f.first_name,''),' ',COALESCE(f.last_name,'')) AS instructor
            FROM enrollments e
            JOIN courses c    ON e.course_id = c.id
            LEFT JOIN faculty f ON f.id = c.faculty_id
            WHERE e.student_id = $sid
              AND e.status = 'Enrolled'
              AND (
                e.semester LIKE '%$ayEsc%'
                OR e.semester LIKE '%$semEsc%'
              )
            ORDER BY c.code ASC
        ");
        if ($cRes) {
            while ($r = $cRes->fetch_assoc()) {
                $courses[] = $r;
            }
        }
    }
}

$conn->close();

// ── Helpers ───────────────────────────────────────────────────────────────────
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function fmtDate(?string $d): string {
    if (!$d) return '—';
    $ts = strtotime($d);
    return $ts ? date('F j, Y g:i A', $ts) : $d;
}

$isApproved = $permit && strtolower($permit['status'] ?? '') === 'approved';
$statusColor = $isApproved ? '#16a34a' : '#dc2626';
$statusLabel = $isApproved ? '✅ VALID PERMIT' : '❌ NOT APPROVED';
$statusBg    = $isApproved ? '#dcfce7' : '#fee2e2';
$statusBorder= $isApproved ? '#86efac' : '#fca5a5';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Exam Permit Verification — St. Benilde</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: #f1f5f9;
      color: #1e293b;
      min-height: 100vh;
      padding: 24px 16px 48px;
    }
    .container { max-width: 520px; margin: 0 auto; }

    /* Header */
    .school-header {
      background: #1a4fa0;
      color: white;
      border-radius: 12px 12px 0 0;
      padding: 20px 24px;
      display: flex;
      align-items: center;
      gap: 14px;
    }
    .school-logo {
      width: 52px; height: 52px;
      background: rgba(255,255,255,0.15);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 24px; flex-shrink: 0;
    }
    .school-name { font-size: 17px; font-weight: 800; letter-spacing: .5px; }
    .school-sub  { font-size: 11px; opacity: .8; margin-top: 2px; }

    /* Card body */
    .card {
      background: white;
      border-radius: 0 0 12px 12px;
      box-shadow: 0 4px 24px rgba(0,0,0,.08);
      overflow: hidden;
    }

    /* Status badge */
    .status-banner {
      padding: 14px 24px;
      text-align: center;
      font-size: 15px;
      font-weight: 800;
      letter-spacing: 1px;
      background: <?= $statusBg ?>;
      color: <?= $statusColor ?>;
      border-bottom: 2px solid <?= $statusBorder ?>;
    }

    /* Student info */
    .info-block { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; }
    .info-block:last-child { border-bottom: none; }
    .section-title {
      font-size: 10px; font-weight: 700; letter-spacing: 1.5px;
      text-transform: uppercase; color: #94a3b8; margin-bottom: 12px;
    }
    .info-row { display: flex; gap: 8px; margin-bottom: 8px; align-items: flex-start; }
    .info-label { font-size: 12px; color: #64748b; min-width: 110px; flex-shrink: 0; padding-top: 1px; }
    .info-value { font-size: 14px; font-weight: 600; color: #0f172a; }
    .info-value.name { font-size: 16px; font-weight: 800; text-transform: uppercase; }
    .badge {
      display: inline-block;
      background: #1a4fa0; color: white;
      font-size: 12px; font-weight: 700;
      padding: 3px 10px; border-radius: 6px;
    }

    /* Courses table */
    .courses-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .courses-table th {
      background: #f8fafc; font-size: 11px; font-weight: 700;
      padding: 8px 12px; text-align: left;
      color: #64748b; border-bottom: 2px solid #e2e8f0;
    }
    .courses-table td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; color: #334155; }
    .courses-table tr:last-child td { border-bottom: none; }
    .courses-table tr:hover td { background: #f8fafc; }

    /* Not found */
    .not-found {
      text-align: center; padding: 48px 24px;
    }
    .not-found .icon { font-size: 48px; margin-bottom: 12px; }
    .not-found h2 { font-size: 18px; font-weight: 700; color: #dc2626; margin-bottom: 8px; }
    .not-found p  { font-size: 14px; color: #64748b; }

    /* Footer */
    .footer {
      text-align: center; margin-top: 20px;
      font-size: 11px; color: #94a3b8;
    }
    .approved-by { font-size: 12px; color: #475569; font-style: italic; }
  </style>
</head>
<body>
<div class="container">

  <div class="school-header">
    <div class="school-logo">🏫</div>
    <div>
      <div class="school-name">ST. BENILDE</div>
      <div class="school-sub">Center for Global Competence, Inc. — Exam Permit Verification</div>
    </div>
  </div>

  <div class="card">
    <?php if ($notFound): ?>
    <div class="not-found">
      <div class="icon">🔍</div>
      <h2>Permit Not Found</h2>
      <p>The QR code you scanned does not match any permit in the system.<br>
         It may be invalid, revoked, or from an older term.</p>
    </div>

    <?php else: ?>
    <!-- Status -->
    <div class="status-banner"><?= $statusLabel ?></div>

    <!-- Permit Info -->
    <div class="info-block">
      <div class="section-title">Permit Details</div>
      <div class="info-row">
        <span class="info-label">Permit No.</span>
        <span class="info-value"><?= h($permit['permit_identifier'] ?? '—') ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Exam Period</span>
        <span class="info-value">
          <span class="badge"><?= h($permit['exam_period'] ?? '—') ?></span>
        </span>
      </div>
      <div class="info-row">
        <span class="info-label">Semester</span>
        <span class="info-value"><?= h($permit['semester'] ?? '—') ?> &nbsp; A.Y. <?= h($permit['school_year'] ?? '—') ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Approved</span>
        <span class="info-value"><?= fmtDate($permit['approved_at'] ?? null) ?></span>
      </div>
      <?php if (!empty($permit['approved_by_first']) || !empty($permit['approved_by_last'])): ?>
      <div class="info-row">
        <span class="info-label">Approved By</span>
        <span class="info-value approved-by">
          <?= h(trim(($permit['approved_by_first'] ?? '') . ' ' . ($permit['approved_by_last'] ?? ''))) ?>
        </span>
      </div>
      <?php endif; ?>
    </div>

    <!-- Student Info -->
    <div class="info-block">
      <div class="section-title">Student Information</div>
      <div class="info-row">
        <span class="info-label">Name</span>
        <span class="info-value name">
          <?= h(strtoupper($permit['last_name'] ?? '')) ?>, <?= h(strtoupper($permit['first_name'] ?? '')) ?>
        </span>
      </div>
      <div class="info-row">
        <span class="info-label">Student No.</span>
        <span class="info-value"><?= h($permit['student_number'] ?? '—') ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Program</span>
        <span class="info-value">
          <span class="badge"><?= h($permit['program'] ?? '—') ?></span>
        </span>
      </div>
      <div class="info-row">
        <span class="info-label">Year Level</span>
        <span class="info-value"><?= h($permit['year_level'] ?? '—') ?></span>
      </div>
    </div>

    <!-- Enrolled Subjects -->
    <?php if (!empty($courses)): ?>
    <div class="info-block">
      <div class="section-title">Enrolled Subjects (<?= count($courses) ?>)</div>
      <table class="courses-table">
        <thead>
          <tr>
            <th>Code</th>
            <th>Subject</th>
            <th>Instructor</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($courses as $c): ?>
          <tr>
            <td><?= h($c['code']) ?></td>
            <td><?= h($c['name']) ?></td>
            <td><?= h(trim($c['instructor']) ?: '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php endif; ?>
  </div>

  <div class="footer">
    Verified via St. Benilde SIA System &nbsp;·&nbsp; <?= date('F j, Y g:i A') ?>
  </div>

</div>
</body>
</html>
