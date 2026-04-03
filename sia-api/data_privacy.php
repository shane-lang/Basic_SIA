<?php
// =============================================================================
// data_privacy.php — Centralized Data Privacy & Security Layer
//
// PURPOSE:
//   Enforces two complementary controls for every sensitive data field:
//     1. RBAC  — "can this role even receive this field at all?"
//     2. Masking — "if they can receive it, how much do they see?"
//
// USAGE (in any API/endpoint PHP file):
//   require_once __DIR__ . '/data_privacy.php';
//
//   // Filter a single student record before sending to client:
//   $student = applyPrivacy($student, $authUser, 'student');
//
//   // Filter an array of records:
//   $students = applyPrivacyList($students, $authUser, 'student');
//
// ROLES in this system:
//   admin       — full access to everything
//   registrar   — full access to academic / personal data; masked financials
//   faculty     — masked student PII; no financials; own grades only
//   accounting  — full financial data; masked PII
//   student     — own record only; masked email; no GPA from others
// =============================================================================

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 1 — MASKING UTILITIES
// Each function takes a raw value and returns a safe-to-display version.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Mask an email address.
 * "juan.dela.cruz@example.com" → "ju***@example.com"
 */
function maskEmail(string $email): string {
    if (!str_contains($email, '@')) return '***@***.***';
    [$local, $domain] = explode('@', $email, 2);
    $visible = substr($local, 0, min(2, strlen($local)));
    return $visible . str_repeat('*', max(3, strlen($local) - 2)) . '@' . $domain;
}

/**
 * Mask a Philippine mobile number.
 * "09171234567" → "0917***4567"
 * "+639171234567" → "+6391***4567"
 */
function maskPhone(string $phone): string {
    $digits = preg_replace('/\D/', '', $phone);
    $len = strlen($digits);
    if ($len < 7) return str_repeat('*', $len);
    // Keep first 4 and last 4 digits; mask the middle
    $visible_head = substr($digits, 0, 4);
    $visible_tail = substr($digits, -4);
    $masked_mid   = str_repeat('*', max(3, $len - 8));
    $masked = $visible_head . $masked_mid . $visible_tail;
    // Restore leading + if original had it
    return (str_starts_with(trim($phone), '+') ? '+' : '') . $masked;
}

/**
 * Mask a street/home address — show only the city/province level.
 * "123 Rizal St., Brgy. West, Olongapo City, Zambales" → "Olongapo City, Zambales"
 * Falls back to showing only the last comma-delimited segment.
 */
function maskAddress(string $address): string {
    $parts = array_map('trim', explode(',', $address));
    if (count($parts) >= 3) {
        // Return last two segments (city + province)
        return implode(', ', array_slice($parts, -2));
    }
    if (count($parts) === 2) {
        return $parts[1]; // Just the second segment
    }
    return '***'; // Single-segment address — fully masked
}

/**
 * Mask a financial amount — show only the order of magnitude.
 * 12500.00 → "₱10,000+"   |   500 → "₱100+"   |   0 → "₱0"
 */
function maskAmount(float $amount): string {
    if ($amount <= 0) return '₱0';
    $magnitude = pow(10, floor(log10($amount)));
    $floor = floor($amount / $magnitude) * $magnitude;
    return '₱' . number_format($floor) . '+';
}

/**
 * Redact a value entirely.
 */
function redact(): string {
    return '[REDACTED]';
}

/**
 * Partially mask a student/LRN number — show first 4 and last 2 chars.
 * "2024-00123" → "2024-****23"
 */
function maskStudentNumber(string $sn): string {
    $len = strlen($sn);
    if ($len <= 6) return str_repeat('*', $len);
    return substr($sn, 0, 4) . str_repeat('*', $len - 6) . substr($sn, -2);
}

/**
 * Mask a grade value — replace with a range band instead of exact figure.
 * Philippine system: 1.0 (highest) — 5.0 (lowest/failed)
 * 1.25 → "1.00–1.50"  |  2.75 → "2.50–3.00"  |  5.0 → "5.0 (INC)"
 */
function maskGrade(?float $grade): string {
    if ($grade === null) return 'N/A';
    if ($grade >= 5.0)   return '5.0 (INC/Failed)';
    $lower = floor($grade * 2) / 2;       // nearest 0.5 below
    $upper = $lower + 0.5;
    return number_format($lower, 2) . '–' . number_format($upper, 2);
}

/**
 * Mask a GPA to one decimal place and indicate pass/fail only.
 * 1.75 → "≈1.8 (Passing)"  |  4.5 → "≈4.5 (At risk)"
 */
function maskGpa(float $gpa): string {
    $rounded = round($gpa, 1);
    $label = $gpa <= 3.0 ? 'Passing' : ($gpa < 5.0 ? 'At risk' : 'Failed');
    return "≈{$rounded} ({$label})";
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 2 — RBAC + MASKING POLICY TABLE
//
// Format per entry:
//   '<field_key>' => [
//       'roles_full'    => ['role1','role2'],   // see exact value
//       'roles_masked'  => ['role3'],            // see masked value
//       'mask_fn'       => 'maskXxx',            // function name to apply
//       // if absent, field is REMOVED for roles not in roles_full/roles_masked
//   ]
// ─────────────────────────────────────────────────────────────────────────────

function getPrivacyPolicy(): array {
    return [

        // ── Personal Identifiers ──────────────────────────────────────────────

        'email' => [
            'roles_full'   => ['admin', 'registrar'],
            'roles_masked' => ['student', 'accounting', 'faculty'],
            'mask_fn'      => 'maskEmail',
        ],

        'phone' => [
            'roles_full'   => ['admin', 'registrar'],
            'roles_masked' => ['student', 'accounting'],
            'mask_fn'      => 'maskPhone',
            // faculty: completely hidden
        ],

        'address' => [
            'roles_full'   => ['admin', 'registrar'],
            'roles_masked' => ['accounting'],
            'mask_fn'      => 'maskAddress',
            // faculty, student (viewing others): hidden
        ],

        'date_of_birth' => [
            'roles_full'   => ['admin', 'registrar'],
            'roles_masked' => [],
            // accounting, faculty, student: hidden
        ],

        'place_of_birth' => [
            'roles_full'   => ['admin', 'registrar'],
            'roles_masked' => [],
        ],

        'lrn_no' => [
            'roles_full'   => ['admin', 'registrar'],
            'roles_masked' => ['accounting'],
            'mask_fn'      => 'maskStudentNumber',
        ],

        'student_number' => [
            'roles_full'   => ['admin', 'registrar', 'accounting', 'student'],
            'roles_masked' => ['faculty'],
            'mask_fn'      => 'maskStudentNumber',
        ],

        'sex' => [
            'roles_full'   => ['admin', 'registrar'],
            'roles_masked' => [],
        ],

        'religion' => [
            'roles_full'   => ['admin', 'registrar'],
            'roles_masked' => [],
        ],

        'citizenship' => [
            'roles_full'   => ['admin', 'registrar'],
            'roles_masked' => [],
        ],

        'mother_tongue' => [
            'roles_full'   => ['admin', 'registrar'],
            'roles_masked' => [],
        ],

        // ── Guardian Info ─────────────────────────────────────────────────────

        'guardian_name' => [
            'roles_full'   => ['admin', 'registrar'],
            'roles_masked' => [],
        ],

        'guardian_phone' => [
            'roles_full'   => ['admin', 'registrar'],
            'roles_masked' => [],
        ],

        'guardian_address' => [
            'roles_full'   => ['admin', 'registrar'],
            'roles_masked' => [],
        ],

        // camelCase variants returned by enrollment.php and api.php responses
        'guardianEmail' => [
            'roles_full'   => ['admin', 'registrar', 'student'],
            'roles_masked' => [],
            // faculty, accounting: hidden
        ],

        'guardian_email' => [
            'roles_full'   => ['admin', 'registrar', 'student'],
            'roles_masked' => [],
        ],

        'guardianName' => [
            'roles_full'   => ['admin', 'registrar', 'student'],
            'roles_masked' => [],
        ],

        'guardianContact' => [
            'roles_full'   => ['admin', 'registrar', 'student'],
            'roles_masked' => [],
        ],

        'guardianRelationship' => [
            'roles_full'   => ['admin', 'registrar', 'student'],
            'roles_masked' => [],
        ],

        // ── Academic / Grades ─────────────────────────────────────────────────

        'gpa' => [
            'roles_full'   => ['admin', 'registrar', 'student'],
            'roles_masked' => ['faculty', 'accounting'],
            'mask_fn'      => 'maskGpa',
        ],

        'prelim' => [
            'roles_full'   => ['admin', 'registrar', 'faculty', 'student'],
            'roles_masked' => ['accounting'],
            'mask_fn'      => 'maskGrade',
        ],

        'midterm' => [
            'roles_full'   => ['admin', 'registrar', 'faculty', 'student'],
            'roles_masked' => ['accounting'],
            'mask_fn'      => 'maskGrade',
        ],

        'final' => [
            'roles_full'   => ['admin', 'registrar', 'faculty', 'student'],
            'roles_masked' => ['accounting'],
            'mask_fn'      => 'maskGrade',
        ],

        'grade' => [
            'roles_full'   => ['admin', 'registrar', 'faculty', 'student'],
            'roles_masked' => ['accounting'],
            'mask_fn'      => 'maskGrade',
        ],

        'final_average' => [
            'roles_full'   => ['admin', 'registrar', 'student'],
            'roles_masked' => ['faculty'],
            'mask_fn'      => 'maskGrade',
        ],

        'grade_letter' => [
            'roles_full'   => ['admin', 'registrar', 'student'],
            'roles_masked' => ['faculty'],
            'mask_fn'      => 'maskGrade',
        ],

        // ── Financial / Billing ───────────────────────────────────────────────

        'amount' => [
            'roles_full'   => ['admin', 'accounting', 'student'],
            'roles_masked' => ['registrar'],
            'mask_fn'      => 'maskAmount',
        ],

        'balance' => [
            'roles_full'   => ['admin', 'accounting', 'student'],
            'roles_masked' => ['registrar'],
            'mask_fn'      => 'maskAmount',
        ],

        'total_paid' => [
            'roles_full'   => ['admin', 'accounting', 'student'],
            'roles_masked' => ['registrar'],
            'mask_fn'      => 'maskAmount',
        ],

        'total_amount' => [
            'roles_full'   => ['admin', 'accounting', 'student'],
            'roles_masked' => ['registrar'],
            'mask_fn'      => 'maskAmount',
        ],

        'gcash_amount' => [
            'roles_full'   => ['admin', 'accounting'],
            'roles_masked' => ['registrar', 'student'],
            'mask_fn'      => 'maskAmount',
        ],

        'remaining_balance' => [
            'roles_full'   => ['admin', 'accounting', 'student'],
            'roles_masked' => ['registrar'],
            'mask_fn'      => 'maskAmount',
        ],

        'amountPaid' => [    // camelCase variant used in dashboard response
            'roles_full'   => ['admin', 'accounting', 'student'],
            'roles_masked' => ['registrar'],
            'mask_fn'      => 'maskAmount',
        ],

        'totalAmount' => [
            'roles_full'   => ['admin', 'accounting', 'student'],
            'roles_masked' => ['registrar'],
            'mask_fn'      => 'maskAmount',
        ],

        'remainingBalance' => [
            'roles_full'   => ['admin', 'accounting', 'student'],
            'roles_masked' => ['registrar'],
            'mask_fn'      => 'maskAmount',
        ],

        'reference_number' => [
            'roles_full'   => ['admin', 'accounting'],
            'roles_masked' => [],
            // registrar, faculty, student: hidden (prevents GCash ref fraud)
        ],

        'gcash_reference' => [
            'roles_full'   => ['admin', 'accounting'],
            'roles_masked' => [],
        ],

        'OR_number' => [
            'roles_full'   => ['admin', 'accounting', 'student'],
            'roles_masked' => ['registrar'],
            'mask_fn'      => 'maskStudentNumber',
        ],

        // ── Credentials / Auth ────────────────────────────────────────────────
        // These should NEVER be in API responses but we guard them anyway.

        'password' => [
            'roles_full'   => [],
            'roles_masked' => [],
            // All roles: field is always stripped
        ],

        'token' => [
            'roles_full'   => [],
            'roles_masked' => [],
        ],

        'device_id' => [
            'roles_full'   => ['admin'],
            'roles_masked' => [],
        ],

        'ip_address' => [
            'roles_full'   => ['admin'],
            'roles_masked' => [],
        ],

    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 3 — CORE APPLICATION FUNCTIONS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Apply privacy rules to a single associative array (e.g. one student record).
 *
 * @param array       $record   Raw DB row or API response array
 * @param array|null  $authUser Result of requireAuth() — contains 'role' key
 * @param string      $context  Optional hint: 'student', 'grade', 'financial'
 *                              (reserved for future context-aware overrides)
 * @param bool        $isOwner  True when the requesting student is viewing
 *                              their OWN record (grants student "full" access
 *                              to their own PII).
 * @return array      Filtered / masked record safe to send to client
 */
function applyPrivacy(array $record, ?array $authUser, string $context = '', bool $isOwner = false): array {
    $role = $authUser['role'] ?? 'guest';

    // Admin sees everything unmasked
    if ($role === 'admin') return $record;

    $policy = getPrivacyPolicy();
    $result = [];

    foreach ($record as $key => $value) {
        // Null / empty values pass through untouched
        if ($value === null || $value === '') {
            $result[$key] = $value;
            continue;
        }

        // No policy entry for this field → pass through
        if (!isset($policy[$key])) {
            $result[$key] = $value;
            continue;
        }

        $rule = $policy[$key];

        // Students viewing their OWN record get the same access as 'full'
        // for PII fields (they should see their own phone/address/email).
        $effectiveRole = ($isOwner && $role === 'student') ? 'admin' : $role;
        if ($effectiveRole === 'admin') {
            $result[$key] = $value;
            continue;
        }

        $fullRoles   = $rule['roles_full']   ?? [];
        $maskedRoles = $rule['roles_masked'] ?? [];
        $maskFn      = $rule['mask_fn']      ?? null;

        if (in_array($effectiveRole, $fullRoles, true)) {
            // Role has full access — pass raw value
            $result[$key] = $value;

        } elseif (in_array($effectiveRole, $maskedRoles, true) && $maskFn && function_exists($maskFn)) {
            // Role has partial access — apply masking function
            // Cast value to appropriate type for the mask function
            $castValue = _castForMask($maskFn, $value);
            $result[$key] = $maskFn($castValue);

        }
        // else: role not in either list → field is silently omitted (not included in $result)
    }

    return $result;
}

/**
 * Apply privacy to an array of records (list endpoints).
 *
 * @param array       $records  Array of DB rows
 * @param array|null  $authUser From requireAuth()
 * @param string      $context  Optional context hint
 * @param int|null    $ownerStudentId  If set, records matching this student ID
 *                              will be treated as $isOwner = true
 * @return array
 */
function applyPrivacyList(array $records, ?array $authUser, string $context = '', ?int $ownerStudentId = null): array {
    return array_map(function (array $record) use ($authUser, $context, $ownerStudentId) {
        $isOwner = false;
        if ($ownerStudentId !== null) {
            $recordStudentId = (int)(
                $record['id']         ??
                $record['student_id'] ??
                $record['dbId']       ?? 0
            );
            $isOwner = ($recordStudentId === $ownerStudentId);
        }
        return applyPrivacy($record, $authUser, $context, $isOwner);
    }, $records);
}

/**
 * Determine if a given role has FULL access to a specific field.
 * Useful for conditional logic in endpoints (e.g. skip expensive join if data will be stripped).
 */
function canSeeField(string $role, string $field): bool {
    if ($role === 'admin') return true;
    $policy = getPrivacyPolicy();
    if (!isset($policy[$field])) return true; // unprotected field
    return in_array($role, $policy[$field]['roles_full'] ?? [], true);
}

/**
 * Determine if a role can see ANY form of a field (full or masked).
 */
function canAccessField(string $role, string $field): bool {
    if ($role === 'admin') return true;
    $policy = getPrivacyPolicy();
    if (!isset($policy[$field])) return true;
    $rule = $policy[$field];
    return in_array($role, array_merge(
        $rule['roles_full']   ?? [],
        $rule['roles_masked'] ?? []
    ), true);
}

/**
 * Append a privacy metadata block to any API response.
 * Helps Angular distinguish masked values from real ones.
 *
 * Usage:  $response['_privacy'] = privacyMeta($authUser);
 */
function privacyMeta(?array $authUser): array {
    return [
        'role'          => $authUser['role']  ?? 'guest',
        'masking_active'=> true,
        'masked_at'     => date('c'),          // ISO 8601 timestamp
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 4 — INTERNAL HELPERS (not for external use)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Cast a raw DB value to the type expected by a mask function.
 */
function _castForMask(string $maskFn, $value): mixed {
    return match ($maskFn) {
        'maskAmount', 'maskGpa' => (float)$value,
        'maskGrade'             => ($value === null ? null : (float)$value),
        default                 => (string)$value,
    };
}