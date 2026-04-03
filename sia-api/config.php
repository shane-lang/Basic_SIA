<?php
// =============================================================================
// EARLY CORS + OPTIONS HANDLER
// Must be the very first code that runs so preflight never gets blocked.
// This fires even before the DB connection, env load, or applyCors() call.
// Without this, Apache passes OPTIONS to PHP but the DB connection overhead
// can cause timeouts before headers are sent.
// =============================================================================
// ── Emit CORS headers immediately — before ANY other code can exit() ─────────
// Uses replace=true so applyCors() can safely overwrite these with
// origin-specific values later without creating duplicate headers.
$_earlyOrigin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $_earlyOrigin", true);
header('Access-Control-Allow-Credentials: true', true);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS', true);
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token', true);
header('Access-Control-Expose-Headers: X-New-Token', true);
header('Content-Type: application/json', true);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Max-Age: 86400', true);
    http_response_code(200);
    exit();
}

// =============================================================================
// FIX AUTH-01: Authorization header rescue (PHP-level, no .htaccess needed)
// Apache/XAMPP strips the Authorization header before PHP sees it when
// AllowOverride is off. This block rescues it from every possible source
// and injects it into $_SERVER['HTTP_AUTHORIZATION'] so auth_middleware
// always finds it — regardless of Apache config or .htaccess.
// =============================================================================
if (empty($_SERVER['HTTP_AUTHORIZATION'])) {
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        // Set by .htaccess RewriteRule E= flag (when .htaccess works)
        $_SERVER['HTTP_AUTHORIZATION'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('getallheaders')) {
        // mod_php: scan all headers case-insensitively
        foreach (getallheaders() as $_hName => $_hVal) {
            if (strtolower($_hName) === 'authorization') {
                $_SERVER['HTTP_AUTHORIZATION'] = $_hVal;
                break;
            }
        }
    } elseif (function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $_hName => $_hVal) {
            if (strtolower($_hName) === 'authorization') {
                $_SERVER['HTTP_AUTHORIZATION'] = $_hVal;
                break;
            }
        }
    } elseif (getenv('HTTP_AUTHORIZATION')) {
        // CGI / FastCGI mode
        $_SERVER['HTTP_AUTHORIZATION'] = getenv('HTTP_AUTHORIZATION');
    }
    // Last resort: X-Auth-Token header (Angular interceptor sends this as fallback).
    // Apache never strips custom headers, so this always arrives even when
    // Authorization is blocked. Reconstruct Authorization from it.
    if (empty($_SERVER['HTTP_AUTHORIZATION']) && !empty($_SERVER['HTTP_X_AUTH_TOKEN'])) {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $_SERVER['HTTP_X_AUTH_TOKEN'];
    }
}

// =============================================================================
// config.php — Single source of truth for DB credentials and app settings.
//
// FIX API-01 / API-02: No more hardcoded credentials in every PHP file.
// All files should `require_once __DIR__ . '/config.php';` and use $conn.
// =============================================================================

// ── Load .env file (simple parser — no external dependency needed) ───────────
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        // Strip inline comments
        if (($pos = strpos($val, ' #')) !== false) {
            $val = trim(substr($val, 0, $pos));
        }
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $val;
            putenv("$key=$val");
        }
    }
}

// ── Helper: read env with fallback ───────────────────────────────────────────
function env(string $key, string $default = ''): string {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// ── Dev mode flag ─────────────────────────────────────────────────────────────
define('IS_DEV', env('APP_ENV', 'development') === 'development');

// Always log errors server-side; NEVER display them inline (breaks JSON responses)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
// In development you can tail the web-server error log to see PHP notices.

// ── Database connection ───────────────────────────────────────────────────────
// XAMPP defaults: root user, empty password.
// Works without .env — just make sure sia_db exists in phpMyAdmin.
$dbHost = env('DB_HOST', 'localhost');
$dbUser = env('DB_USER', 'root');
$dbPass = env('DB_PASS', '');
$dbName = env('DB_NAME', 'sia_db');

// Fail fast in production if DB credentials are not configured
if (!IS_DEV && ($dbUser === '' || $dbName === '')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server configuration error.']);
    exit();
}

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    http_response_code(500);
    // In dev, expose the real error; in prod, hide it
    $msg = IS_DEV
        ? 'DB connection failed: ' . $conn->connect_error
        : 'Database connection failed.';
    echo json_encode(['success' => false, 'message' => $msg]);
    exit();
}
$conn->set_charset('utf8mb4');

// ── CORS helper (call once per entry-point) ───────────────────────────────────
// Uses header(..., true) — the replace flag — so these calls always overwrite
// the early-send headers from the top of this file, never append to them.
function applyCors(): void {
    if (IS_DEV) {
        // Development: accept any origin so every local port / tool works
        // (Postman, Angular :4200, Vite :5173, plain browser, etc.)
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        header("Access-Control-Allow-Origin: $origin", true);
        header('Access-Control-Allow-Credentials: true', true);
    } else {
        // Production: restrict to the trusted list from .env
        $allowed = array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:4200')));
        $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (in_array($origin, $allowed, true)) {
            header("Access-Control-Allow-Origin: $origin", true);
            header('Access-Control-Allow-Credentials: true', true);
        } else {
            header('Access-Control-Allow-Origin: ' . ($allowed[0] ?? 'http://localhost:4200'), true);
        }
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS', true);
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token', true);
    header('Access-Control-Expose-Headers: X-New-Token', true);
    header('Content-Type: application/json', true);

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}