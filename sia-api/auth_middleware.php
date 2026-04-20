<?php
// =============================================================================
// auth_middleware.php — Updated: first_name/last_name fetched from
//                       role-specific tables (students, faculty, staff_profiles)
//                       instead of the users table.
//
// FIX AUTH-01: Token extraction now uses EVERY possible source Apache/XAMPP
//              can deliver the Authorization header through, plus multiple
//              fallback channels (X-Auth-Token header, _token query param).
//              This resolves the {"success":false,"message":"Authentication
//              required","code":"NO_TOKEN"} error that occurs because Apache
//              strips the Authorization header before PHP sees it.
// =============================================================================

define('REFRESH_WINDOW_MINUTES', 30);

/**
 * Verify the Bearer token and return the authenticated user array.
 * Exits with 401/403 JSON on failure.
 *
 * Returned array keys: user_id, role, email, first_name, last_name
 * Optional extra key:  new_token (set when the token was silently rotated)
 */
function requireAuth(mysqli $conn, string $requiredRole = '', bool $allowPublic = false): ?array {

    // ── 1. Extract Bearer token — try EVERY possible source ──────────────────
    //
    //  Apache/XAMPP has multiple ways it may (or may not) deliver the
    //  Authorization header to PHP depending on:
    //    • Apache version & OS
    //    • PHP SAPI (mod_php vs CGI vs FPM)
    //    • Whether AllowOverride is set and .htaccess RewriteRules fired
    //    • Whether mod_rewrite rewrote the env var name
    //
    //  We probe every known location in priority order so that at least one
    //  succeeds on any deployment.

    $token = '';

    // --- Source A: Standard server variables (populated by mod_php or FPM) ---
    $candidates = [
        // Set by .htaccess: RewriteRule ^ - [E=HTTP_AUTHORIZATION:...]
        $_SERVER['HTTP_AUTHORIZATION']          ?? '',
        // Alternative capitalisation some XAMPP builds use
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '',
        // Set by .htaccess: SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
        $_SERVER['HTTP_X_AUTH_TOKEN']           ?? '',
        // Raw env variable written by some CGI wrappers
        getenv('HTTP_AUTHORIZATION')            ?: '',
        getenv('REDIRECT_HTTP_AUTHORIZATION')   ?: '',
    ];

    // --- Source B: getallheaders() — available in mod_php, PHP 7.3+ globally --
    if (function_exists('getallheaders')) {
        $allHeaders = getallheaders();
        // Header names are case-insensitive per RFC 7230
        foreach ($allHeaders as $name => $value) {
            $normalized = strtolower($name);
            if ($normalized === 'authorization') {
                $candidates[] = $value;
                break;
            }
            if ($normalized === 'x-auth-token') {
                // Also store X-Auth-Token from getallheaders() as a low-priority candidate
                $candidates[] = 'X-Auth-Token: ' . $value;
            }
        }
    }

    // --- Source C: apache_request_headers() — alias for getallheaders() ------
    if (!$token && function_exists('apache_request_headers')) {
        $apacheHeaders = apache_request_headers();
        foreach ($apacheHeaders as $name => $value) {
            if (strtolower($name) === 'authorization') {
                $candidates[] = $value;
                break;
            }
        }
    }

    // --- Extract Bearer token from whichever candidate contains it -----------
    foreach ($candidates as $candidate) {
        $candidate = trim((string)$candidate);
        if ($candidate === '') continue;

        // Standard: "Bearer <token>"
        if (preg_match('/^Bearer\s+(\S+)$/i', $candidate, $m)) {
            $token = $m[1];
            break;
        }

        // X-Auth-Token forwarded as "X-Auth-Token: <value>" string
        if (preg_match('/^X-Auth-Token:\s*(\S+)$/i', $candidate, $m)) {
            $token = $m[1];
            break;
        }

        // Raw token without "Bearer" prefix (some mobile clients)
        if (strlen($candidate) === 64 && ctype_xdigit($candidate)) {
            $token = $candidate;
            break;
        }
    }

    // --- Source D: X-Auth-Token header (explicit, client-side alternative) ---
    if (!$token && !empty($_SERVER['HTTP_X_AUTH_TOKEN'])) {
        $token = trim($_SERVER['HTTP_X_AUTH_TOKEN']);
    }

    // --- Source E: _token query param (last resort, e.g. for downloads) ------
    if (!$token && !empty($_GET['_token'])) {
        $token = trim($_GET['_token']);
    }

    // ── 2. No token found ─────────────────────────────────────────────────────
    if (!$token) {
        if ($allowPublic) return null;
        _authFail(401, 'Authentication required', 'NO_TOKEN');
    }

    // ── 3. Look up session ────────────────────────────────────────────────────
    // FIX TOKEN-ROTATION-01: Also add prev_token column support so that during
    // the 2-minute grace window after a silent rotation, the old token still
    // resolves to the session. This prevents the race condition where Angular
    // hasn't yet saved the X-New-Token header value back to localStorage and
    // a concurrent request fires with the old token → 401 → phantom logout.
    $conn->query("ALTER TABLE sessions ADD COLUMN IF NOT EXISTS prev_token VARCHAR(64) DEFAULT NULL");
    $conn->query("ALTER TABLE sessions ADD COLUMN IF NOT EXISTS prev_expires DATETIME DEFAULT NULL");

    $stmt = $conn->prepare("
        SELECT s.user_id, s.role, s.expires_at, s.token,
               u.email
        FROM   sessions s
        JOIN   users    u ON u.id = s.user_id
        WHERE  s.token = ?
        LIMIT  1
    ");

    if (!$stmt) {
        _authFail(500, 'Session store unavailable', 'DB_ERROR');
    }

    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        // FIX TOKEN-ROTATION-01 (continued): Primary lookup failed — try prev_token
        // within its grace window. This handles the race where:
        //   1. Backend rotated the token and sent X-New-Token in a response header
        //   2. Angular received the response but hadn't saved the new token yet
        //   3. A concurrent or immediate subsequent request fired with the old token
        // Without this fallback, step 3 returns SESSION_NOT_FOUND → 401 → logout.
        $stmtPrev = $conn->prepare("
            SELECT s.user_id, s.role, s.expires_at, s.token,
                   u.email
            FROM   sessions s
            JOIN   users    u ON u.id = s.user_id
            WHERE  s.prev_token = ? AND s.prev_expires > NOW()
            LIMIT  1
        ");
        if ($stmtPrev) {
            $stmtPrev->bind_param('s', $token);
            $stmtPrev->execute();
            $row = $stmtPrev->get_result()->fetch_assoc();
            $stmtPrev->close();
        }
    }

    if (!$row) {
        // The session was not found. This could mean:
        // 1. Token is invalid/expired
        // 2. Another device logged in and replaced this session (no-dual-login)
        // We can't distinguish without the user_id, so return a generic 401.
        // The Angular interceptor will redirect to login and show the message.
        _authFail(401, 'Your session has ended. You may have been logged in from another device.', 'SESSION_NOT_FOUND');
    }

    $expiresAt = strtotime($row['expires_at']);

    // ── 4. Hard-expired ───────────────────────────────────────────────────────
    if ($expiresAt < time()) {
        $del = $conn->prepare('DELETE FROM sessions WHERE token = ?');
        $del->bind_param('s', $token);
        $del->execute();
        $del->close();
        _authFail(401, 'Session expired. Please log in again.', 'SESSION_EXPIRED');
    }

    // ── 5. Role check ─────────────────────────────────────────────────────────
    if ($requiredRole && $row['role'] !== $requiredRole) {
        _authFail(403, 'Access denied.', 'FORBIDDEN');
    }

    // ── 6. Silent token rotation when close to expiry ─────────────────────────
    $newToken = null;
    $ttlHours = (int) env('SESSION_TTL_HOURS', '8');

    if ($expiresAt - time() < REFRESH_WINDOW_MINUTES * 60) {
        $newToken  = bin2hex(random_bytes(32));
        $newExpiry = date('Y-m-d H:i:s', strtotime("+{$ttlHours} hours"));
        // FIX TOKEN-ROTATION-01: Store the old token in prev_token with a 2-minute
        // grace window so concurrent requests using the old token still resolve.
        // Without this, any request in-flight during rotation hits SESSION_NOT_FOUND.
        $prevGrace = date('Y-m-d H:i:s', time() + 120);

        $upd = $conn->prepare('UPDATE sessions SET token = ?, expires_at = ?, prev_token = ?, prev_expires = ? WHERE token = ?');
        $upd->bind_param('sssss', $newToken, $newExpiry, $token, $prevGrace, $token);
        $upd->execute();
        $upd->close();

        header("X-New-Token: $newToken");
    }

    // ── 7. Fetch name from the correct profile table ──────────────────────────
    $profile = _getProfileName($conn, (int)$row['user_id'], $row['role']);

    $authUser = [
        'user_id'    => (int) $row['user_id'],
        'role'       => $row['role'],
        'email'      => $row['email'],
        'first_name' => $profile['first_name'],
        'last_name'  => $profile['last_name'],
    ];
    if ($newToken) {
        $authUser['new_token'] = $newToken;
    }

    return $authUser;
}

// ── Fetch name from role-specific table ───────────────────────────────────────
function _getProfileName(mysqli $conn, int $userId, string $role): array {
    switch ($role) {
        case 'student':
            $st = $conn->prepare("SELECT first_name, last_name FROM students WHERE user_id = ? LIMIT 1");
            break;
        case 'faculty':
            $st = $conn->prepare("SELECT first_name, last_name FROM faculty WHERE user_id = ? LIMIT 1");
            break;
        case 'admin':
        case 'accounting':
        case 'registrar':
            $st = $conn->prepare("SELECT first_name, last_name FROM staff_profiles WHERE user_id = ? LIMIT 1");
            break;
        default:
            return ['first_name' => '', 'last_name' => ''];
    }

    if (!$st) return ['first_name' => '', 'last_name' => ''];
    $st->bind_param('i', $userId);
    $st->execute();
    $profile = $st->get_result()->fetch_assoc();
    $st->close();

    return [
        'first_name' => $profile['first_name'] ?? '',
        'last_name'  => $profile['last_name']  ?? '',
    ];
}

// ── Internal helper ───────────────────────────────────────────────────────────
function _authFail(int $code, string $message, string $errorCode): never {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message, 'code' => $errorCode]);
    exit();
}