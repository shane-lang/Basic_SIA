<?php
// =============================================================================
// auth.php — Login, Register, Token Validation, Password Reset, Change Password
// =============================================================================

// ── 1. Output buffer FIRST — captures stray notices before any output ────────
ob_start();

// ── 2. Exception/Error handler — returns JSON + CORS headers on any crash ────
// Placed before config.php so even a DB-connect failure returns readable JSON.
set_exception_handler(function (Throwable $e) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token');
    header('Content-Type: application/json');
    http_response_code(200); // 200 so Angular interceptor reads the body
    $isDev = (getenv('APP_ENV') ?: 'development') === 'development';
    echo json_encode([
        'success' => false,
        'message' => $isDev
            ? $e->getMessage() . ' [' . basename($e->getFile()) . ':' . $e->getLine() . ']'
            : 'Server error. Please try again.',
    ]);
    exit();
});

// ── 3. Config + CORS (OPTIONS preflight handled inside config.php) ────────────
require_once __DIR__ . '/config.php';
applyCors();

// ── FIX: Sync PHP timezone to Asia/Manila so date() matches MySQL NOW() ──────
// Without this, PHP date('+15 minutes') and MySQL NOW() can differ by hours,
// causing OTPs to appear expired the moment they are inserted.
date_default_timezone_set('Asia/Manila');

// ── 4. Auth middleware (requireAuth helper) ───────────────────────────────────
require_once __DIR__ . '/auth_middleware.php';

// ── 5. Read request body ONCE — php://input is a one-time stream ─────────────
$rawInput = file_get_contents('php://input');
$action   = $_GET['action'] ?? '';

// =============================================================================
// HELPERS
// =============================================================================
function respond(array $payload, int $code = 200): never {
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
}

function getProfileName(mysqli $conn, int $userId, string $role): array {
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
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return [
        'first_name' => $row['first_name'] ?? '',
        'last_name'  => $row['last_name']  ?? '',
    ];
}

// =============================================================================
// FIX: Ensure login_otp table exists AND has all required columns.
// CREATE TABLE IF NOT EXISTS won't fix a table that already exists with the
// wrong schema (e.g. missing the 'email' column from an older version).
// This function is called once inside forgot_password before any OTP queries.
// =============================================================================
function ensureLoginOtpTable(mysqli $conn): void {
    // 1. Create the table fresh if it doesn't exist at all.
    //    This is the correct target schema — email-based, no user_id dependency.
    $conn->query("
        CREATE TABLE IF NOT EXISTS login_otp (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            email      VARCHAR(255) NOT NULL,
            otp        VARCHAR(6)   NOT NULL,
            expires_at DATETIME     NOT NULL,
            used       TINYINT(1)   NOT NULL DEFAULT 0,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 2. Inspect existing columns and indexes so we can migrate an old table.
    $cols    = [];
    $res     = $conn->query("SHOW COLUMNS FROM login_otp");
    while ($r = $res->fetch_assoc()) { $cols[] = $r['Field']; }

    $indexes = [];
    $idxRes  = $conn->query("SHOW INDEX FROM login_otp");
    while ($r = $idxRes->fetch_assoc()) { $indexes[] = $r['Key_name']; }

    // ── FIX: Drop the UNIQUE constraint on user_id ───────────────────────────
    // The old schema had: ADD UNIQUE KEY `user_id` (`user_id`)
    // This caused "Duplicate entry '0' for key 'user_id'" because auth.php
    // inserts by email only — user_id defaulted to 0 on every row.
    // We must drop both the unique index and the column before adding email.
    if (in_array('user_id', $indexes, true)) {
        @$conn->query("ALTER TABLE login_otp DROP INDEX `user_id`");
    }
    // Also drop the idx_user index if it exists
    if (in_array('idx_user', $indexes, true)) {
        @$conn->query("ALTER TABLE login_otp DROP INDEX `idx_user`");
    }
    // Now safe to drop the user_id column if it still exists
    if (in_array('user_id', $cols, true)) {
        @$conn->query("ALTER TABLE login_otp DROP COLUMN `user_id`");
        $cols = array_diff($cols, ['user_id']); // keep $cols in sync
    }

    // ── Add email column if missing ──────────────────────────────────────────
    if (!in_array('email', $cols, true)) {
        $conn->query("ALTER TABLE login_otp ADD COLUMN email VARCHAR(255) NOT NULL DEFAULT '' AFTER id");
        @$conn->query("ALTER TABLE login_otp ADD INDEX idx_email (email)");
    }

    // ── Rename otp_code → otp (old schema used otp_code) ────────────────────
    if (!in_array('otp', $cols, true)) {
        if (in_array('otp_code', $cols, true)) {
            $conn->query("ALTER TABLE login_otp CHANGE COLUMN `otp_code` `otp` VARCHAR(6) NOT NULL");
        } elseif (in_array('code', $cols, true)) {
            $conn->query("ALTER TABLE login_otp CHANGE COLUMN `code` `otp` VARCHAR(6) NOT NULL");
        } else {
            $conn->query("ALTER TABLE login_otp ADD COLUMN otp VARCHAR(6) NOT NULL DEFAULT '' AFTER email");
        }
    }

    // ── Ensure remaining columns exist ───────────────────────────────────────
    if (!in_array('used', $cols, true)) {
        $conn->query("ALTER TABLE login_otp ADD COLUMN used TINYINT(1) NOT NULL DEFAULT 0");
    }
    if (!in_array('expires_at', $cols, true)) {
        $conn->query("ALTER TABLE login_otp ADD COLUMN expires_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }
    if (!in_array('created_at', $cols, true)) {
        $conn->query("ALTER TABLE login_otp ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }
}

// =============================================================================
// PASSWORD STRENGTH VALIDATOR
// Rules: min 8 chars, uppercase, lowercase, number, special character.
// Returns array of error strings (empty = strong enough).
// =============================================================================
function validatePasswordStrength(string $password): array {
    $errors = [];
    if (strlen($password) < 8)
        $errors[] = 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Z]/', $password))
        $errors[] = 'Password must contain at least one uppercase letter.';
    if (!preg_match('/[a-z]/', $password))
        $errors[] = 'Password must contain at least one lowercase letter.';
    if (!preg_match('/[0-9]/', $password))
        $errors[] = 'Password must contain at least one number.';
    if (!preg_match('/[!@#$%^&*()\-_=+\[\]{};\':",.<>\/?`~\\\\|]/', $password))
        $errors[] = 'Password must contain at least one special character (e.g. !@#$%).';
    return $errors;
}

const PORTAL_ROLES = [
    'student'    => 'student',
    'admin'      => 'admin',
    'accounting' => 'accounting',
    'registrar'  => 'registrar',
    'faculty'    => 'faculty',
];

// =============================================================================
// LOGOUT — POST ?action=logout
// Deletes the session row from the DB so the token can never be replayed.
// Also accepts GET (e.g. beacon/fetch keepalive) for browser tab-close support.
// =============================================================================
if ($action === 'logout') {
    // Extract raw Bearer token — mirrors auth_middleware extraction logic.
    // allowPublic=true so we don't hard-fail if the session is already gone.
    $token = '';
    $candidates = [
        $_SERVER['HTTP_AUTHORIZATION']          ?? '',
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '',
        $_SERVER['HTTP_X_AUTH_TOKEN']           ?? '',
        getenv('HTTP_AUTHORIZATION')            ?: '',
    ];
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $_n => $_v) {
            if (strtolower($_n) === 'authorization') { $candidates[] = $_v; break; }
        }
    }
    foreach ($candidates as $_c) {
        $_c = trim((string)$_c);
        if (preg_match('/^Bearer\s+(\S+)$/i', $_c, $_m)) { $token = $_m[1]; break; }
        if (strlen($_c) === 64 && ctype_xdigit($_c))       { $token = $_c;   break; }
    }
    // Last resort: _token query param (e.g. sendBeacon fallback)
    if (!$token && !empty($_GET['_token'])) {
        $token = trim($_GET['_token']);
    }

    if ($token) {
        $del = $conn->prepare('DELETE FROM sessions WHERE token = ?');
        if ($del) {
            $del->bind_param('s', $token);
            $del->execute();
            $del->close();
        }
    }

    respond(['success' => true, 'message' => 'Logged out successfully.']);
}

// =============================================================================
// VALIDATE TOKEN — GET ?action=validate_token
// =============================================================================
if ($action === 'validate_token') {
    $authUser = requireAuth($conn, '', true);
    if ($authUser && $authUser['role'] === 'student') {
        respond([
            'success' => true,
            'user' => [
                'id'         => $authUser['user_id'],
                'email'      => $authUser['email'],
                'role'       => $authUser['role'],
                'first_name' => $authUser['first_name'],
                'last_name'  => $authUser['last_name'],
            ],
        ]);
    }
    respond(['success' => false, 'message' => 'Invalid or expired token'], 401);
}

// =============================================================================
// REGISTER — POST ?action=register
// =============================================================================
if ($action === 'register') {
    $data = json_decode($rawInput, true);
    if (!$data) respond(['success' => false, 'message' => 'Invalid JSON'], 400);

    $email    = trim($data['email']    ?? '');
    $password = trim($data['password'] ?? '');

    if ($email === '')
        respond(['success' => false, 'message' => 'Email is required.'], 400);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        respond(['success' => false, 'message' => 'Please enter a valid email address.'], 400);
    if (strlen($email) > 255)
        respond(['success' => false, 'message' => 'Email address is too long.'], 400);
    if ($password === '')
        respond(['success' => false, 'message' => 'Password is required.'], 400);
    if (strlen($password) > 255)
        respond(['success' => false, 'message' => 'Password is too long.'], 400);
    $pwErrors = validatePasswordStrength($password);
    if ($pwErrors)
        respond(['success' => false, 'message' => $pwErrors[0], 'errors' => $pwErrors], 422);

    $check = $conn->prepare("SELECT id, role FROM users WHERE email = ? LIMIT 1");
    $check->bind_param("s", $email);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    $check->close();

    if ($existing) {
        respond([
            'success'         => false,
            'message'         => 'This email address is already registered. Please use a different email or log in to your existing account.',
            'code'            => 'EMAIL_EXISTS',
            'already_existed' => true,
        ], 409);
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $conn->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'student')");
    $stmt->bind_param("ss", $email, $hashedPassword);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        respond([
            'success'         => true,
            'message'         => 'Account created successfully',
            'user_id'         => (int)$stmt->insert_id,
            'email'           => $email,
            'role'            => 'student',
            'already_existed' => false,
        ]);
    }
    respond(['success' => false, 'message' => 'Failed to create account.'], 500);
}

// =============================================================================

// =============================================================================
// VERIFY PASSWORD — POST ?action=verify_password
// Requires a valid Bearer token. Returns success:true if the supplied password
// matches the authenticated user's stored hash. Used by the frontend password
// gate before displaying sensitive documents (COE, SOA, Receipts, Grades).
// No session is created or altered — this is a read-only check.
// =============================================================================
if ($action === 'verify_password') {
    $authUser = requireAuth($conn);

    $data     = json_decode($rawInput, true);
    $password = trim($data['password'] ?? '');

    if ($password === '')
        respond(['success' => false, 'message' => 'Password is required.'], 400);

    $stmt = $conn->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $authUser['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row)
        respond(['success' => false, 'message' => 'User not found.'], 404);

    // Support both bcrypt and legacy plain-text (same as login)
    $valid = password_verify($password, $row['password'])
          || $password === $row['password'];

    if (!$valid)
        respond(['success' => false, 'message' => 'Incorrect password.'], 403);

    respond(['success' => true, 'message' => 'Password verified.']);
}

// =============================================================================
// CHANGE PASSWORD — POST ?action=change_password
// Requires a valid Bearer token (any role).
// =============================================================================
if ($action === 'change_password') {
    $authUser = requireAuth($conn);

    $data            = json_decode($rawInput, true);
    $currentPassword = trim($data['current_password'] ?? '');
    $newPassword     = trim($data['new_password']     ?? '');
    $confirmPassword = trim($data['confirm_password'] ?? '');

    if (!$currentPassword || !$newPassword || !$confirmPassword)
        respond(['success' => false, 'message' => 'All fields are required.'], 400);
    if (strlen($newPassword) < 8 || !preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword) || !preg_match('/[!@#$%^&*()\-_=+\[\]{};\':",.<>\/?`~\\\\|]/', $newPassword)) {
        $pwErrors = validatePasswordStrength($newPassword);
        respond(['success' => false, 'message' => $pwErrors[0], 'errors' => $pwErrors], 422);
    }
    if ($newPassword !== $confirmPassword)
        respond(['success' => false, 'message' => 'New password and confirmation do not match.'], 422);

    $stmt = $conn->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $authUser['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !password_verify($currentPassword, $row['password']))
        respond(['success' => false, 'message' => 'Current password is incorrect.'], 403);

    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    $upd  = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
    $upd->bind_param('si', $hash, $authUser['user_id']);
    $upd->execute();
    $upd->close();

    respond(['success' => true, 'message' => 'Password changed successfully.']);
}

// =============================================================================
// FORGOT PASSWORD — POST ?action=forgot_password
// Generates a 6-digit OTP, saves to login_otp, emails via PHPMailer.
// Always returns the same generic message to prevent email enumeration.
// =============================================================================
if ($action === 'forgot_password') {
    $data  = json_decode($rawInput, true);
    $email = trim($data['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL))
        respond(['success' => false, 'message' => 'A valid email address is required.'], 400);

    $uStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $uStmt->bind_param('s', $email);
    $uStmt->execute();
    $user = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();

    if ($user) {
        // FIX: Ensure login_otp table exists AND has the correct schema
        // (handles the "Unknown column 'email' in 'where clause'" error caused
        // by an existing table that was created without the email column)
        ensureLoginOtpTable($conn);

        // Invalidate existing unused OTPs for this email
        $invStmt = $conn->prepare("UPDATE login_otp SET used = 1 WHERE email = ? AND used = 0");
        $invStmt->bind_param('s', $email);
        $invStmt->execute();
        $invStmt->close();

        // Generate 6-digit OTP
        // FIX: Use MySQL NOW() + INTERVAL for expires_at to eliminate PHP/MySQL timezone mismatch.
        // When PHP date() and MySQL NOW() use different timezones, the OTP expires immediately.
        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $otpStmt = $conn->prepare("INSERT INTO login_otp (email, otp, expires_at) VALUES (?, ?, NOW() + INTERVAL 15 MINUTE)");
        $otpStmt->bind_param('ss', $email, $otp);
        $otpStmt->execute();
        $otpStmt->close();

        // ── Load PHPMailer (supports Composer autoload and manual install) ──
        $mailerLoaded = false;
        foreach ([__DIR__ . '/vendor/autoload.php', __DIR__ . '/../vendor/autoload.php'] as $p) {
            if (file_exists($p)) { require_once $p; $mailerLoaded = true; break; }
        }
        if (!$mailerLoaded) {
            $srcPath = __DIR__ . '/vendor/phpmailer/phpmailer/src/';
            if (file_exists($srcPath . 'PHPMailer.php')) {
                require_once $srcPath . 'Exception.php';
                require_once $srcPath . 'PHPMailer.php';
                require_once $srcPath . 'SMTP.php';
                $mailerLoaded = true;
            }
        }

        $schoolName = env('SCHOOL_NAME', 'St. Benilde SIA');
        $subject    = "Password Reset OTP — {$schoolName}";
        $htmlBody   = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8">
<style>
  body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0;color:#333}
  .wrap{max-width:520px;margin:30px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1)}
  .header{background:#1a3a6b;color:#fff;padding:24px 32px}
  .header h1{margin:0;font-size:20px}
  .body{padding:28px 32px}
  .otp-box{background:#f0f4ff;border:2px solid #1a3a6b;border-radius:10px;text-align:center;padding:20px;margin:20px 0}
  .otp-code{font-size:42px;font-weight:bold;letter-spacing:12px;color:#1a3a6b;font-family:monospace}
  .otp-note{color:#6b7280;font-size:13px;margin-top:8px}
  .footer{background:#f0f4f8;padding:14px 32px;text-align:center;font-size:12px;color:#888}
</style>
</head>
<body>
<div class="wrap">
  <div class="header"><h1>&#128272; {$schoolName} &mdash; Password Reset</h1></div>
  <div class="body">
    <p>We received a request to reset your password. Use the code below to proceed:</p>
    <div class="otp-box">
      <div class="otp-code">{$otp}</div>
      <div class="otp-note">Valid for <strong>15 minutes</strong>. Do not share this code with anyone.</div>
    </div>
    <p>If you did not request a password reset, you can safely ignore this email.</p>
  </div>
  <div class="footer">&copy; {$schoolName} &mdash; Automated message, do not reply.</div>
</div>
</body>
</html>
HTML;

        $sent = false;
        if ($mailerLoaded) {
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = env('MAIL_HOST', 'smtp.gmail.com');
                $mail->SMTPAuth   = true;
                $mail->Username   = env('MAIL_USERNAME', '');
                $mail->Password   = env('MAIL_PASSWORD', '');
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = (int) env('MAIL_PORT', '587');
                $mail->setFrom(env('MAIL_USERNAME', 'noreply@school.edu'), env('MAIL_FROM_NAME', $schoolName));
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->CharSet = 'UTF-8';
                $mail->Subject = $subject;
                $mail->Body    = $htmlBody;
                $mail->AltBody = "Your password reset OTP is: {$otp}\nValid for 15 minutes.";
                $mail->send();
                $sent = true;
            } catch (\Exception $e) {
                error_log("Forgot password mailer error: " . $e->getMessage());
            }
        } else {
            // Fallback: native PHP mail()
            $headers  = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: " . env('MAIL_FROM_NAME', $schoolName) . " <" . env('MAIL_USERNAME', 'noreply@school.edu') . ">\r\n";
            $sent = mail($email, $subject, $htmlBody, $headers);
        }

        if (!$sent) {
            error_log("Forgot password: failed to send OTP email to {$email}");
        }
    }

    // Always same response — prevents email enumeration
    respond(['success' => true, 'message' => 'If an account with that email exists, a reset code has been sent.']);
}

// =============================================================================
// RESET PASSWORD — POST ?action=reset_password
// Validates OTP, updates password, marks OTP as used.
// =============================================================================
if ($action === 'reset_password') {
    $data = json_decode($rawInput, true);

    $email     = trim($data['email']            ?? '');
    $otp       = trim($data['otp']              ?? '');
    $new_pass  = trim($data['new_password']     ?? '');
    $conf_pass = trim($data['confirm_password'] ?? '');

    if (!$email || !$otp || !$new_pass || !$conf_pass)
        respond(['success' => false, 'message' => 'Lahat ng fields ay kailangan.']);

    $pwErrors = validatePasswordStrength($new_pass);
    if ($pwErrors)
        respond(['success' => false, 'message' => $pwErrors[0], 'errors' => $pwErrors]);

    if ($new_pass !== $conf_pass)
        respond(['success' => false, 'message' => 'Hindi magkatugma ang password.']);

    // Validate OTP
    $otpStmt = $conn->prepare(
        "SELECT id FROM login_otp WHERE email = ? AND otp = ? AND used = 0 AND expires_at > NOW() LIMIT 1"
    );
    $otpStmt->bind_param('ss', $email, $otp);
    $otpStmt->execute();
    $otpRow = $otpStmt->get_result()->fetch_assoc();
    $otpStmt->close();

    if (!$otpRow)
        respond(['success' => false, 'message' => 'Mali o expired na ang reset code.']);

    // Update password
    $hashed = password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => 12]);
    $upd    = $conn->prepare('UPDATE users SET password = ? WHERE email = ?');
    $upd->bind_param('ss', $hashed, $email);

    if (!$upd->execute())
        respond(['success' => false, 'message' => 'Database update failed. Please try again.']);

    $upd->close();

    // Mark OTP as used
    $markUsed = $conn->prepare('UPDATE login_otp SET used = 1 WHERE id = ?');
    $markUsed->bind_param('i', $otpRow['id']);
    $markUsed->execute();
    $markUsed->close();

    respond(['success' => true, 'message' => 'Password reset successful!']);
}

// =============================================================================
// LOGIN — plain POST, no action param
// All named-action routes above call respond() and exit() before reaching here.
// =============================================================================
$data = json_decode($rawInput, true);

if (!$data || !isset($data['email']) || !isset($data['password'])) {
    respond(['success' => false, 'message' => 'Email and password are required'], 400);
}

$email        = trim($data['email']);
$password     = trim($data['password']);
$ip           = $_SERVER['REMOTE_ADDR'] ?? '';
$portal       = trim($data['portal'] ?? '');
$requiredRole = $portal ? (PORTAL_ROLES[$portal] ?? null) : null;

// ── Input validation ──────────────────────────────────────────────────────────
if ($email === '') {
    respond(['success' => false, 'message' => 'Email is required.'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(['success' => false, 'message' => 'Please enter a valid email address.'], 400);
}
if (strlen($email) > 255) {
    respond(['success' => false, 'message' => 'Email address is too long.'], 400);
}
if ($password === '') {
    respond(['success' => false, 'message' => 'Password is required.'], 400);
}
if (strlen($password) < 6) {
    respond(['success' => false, 'message' => 'Password must be at least 6 characters.'], 400);
}
if (strlen($password) > 255) {
    respond(['success' => false, 'message' => 'Password is too long.'], 400);
}
// ─────────────────────────────────────────────────────────────────────────────

if ($portal && $requiredRole === null) {
    respond(['success' => false, 'message' => 'Invalid portal specified.'], 400);
}

// Rate limiting — max 10 attempts per 15 minutes per email
$window  = date('Y-m-d H:i:s', strtotime('-15 minutes'));
$cntStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM login_attempts WHERE email = ? AND attempted_at > ?");
$cntStmt->bind_param("ss", $email, $window);
$cntStmt->execute();
$attempts = (int)$cntStmt->get_result()->fetch_assoc()['cnt'];
$cntStmt->close();

if ($attempts >= 10) {
    respond(['success' => false, 'message' => 'Too many login attempts. Please try again in 15 minutes.'], 429);
}

$logStmt = $conn->prepare("INSERT INTO login_attempts (email, ip) VALUES (?, ?)");
$logStmt->bind_param("ss", $email, $ip);
$logStmt->execute();
$logStmt->close();

$loginStmt = $conn->prepare("SELECT id, email, password, role FROM users WHERE email = ? LIMIT 1");
$loginStmt->bind_param("s", $email);
$loginStmt->execute();
$result = $loginStmt->get_result();
$loginStmt->close();

if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();

    $passwordValid = false;
    if (password_verify($password, $user['password'])) {
        $passwordValid = true;
    } elseif ($password === $user['password']) {
        // Legacy plain-text password migration
        $passwordValid = true;
        $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $mig = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $mig->bind_param("si", $newHash, $user['id']);
        $mig->execute();
        $mig->close();
    }

    if ($passwordValid) {
        if ($requiredRole && $user['role'] !== $requiredRole) {
            respond(['success' => false, 'message' => 'This account does not have access to this portal. Please use the correct account.']);
        }

        $profile = getProfileName($conn, (int)$user['id'], $user['role']);

        // Ensure device_id and ip_address columns exist
        $conn->query("ALTER TABLE sessions ADD COLUMN IF NOT EXISTS device_id  VARCHAR(64)  DEFAULT NULL");
        $conn->query("ALTER TABLE sessions ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45)  DEFAULT NULL");

        // No-dual-login: one active session per user per device
        $deviceId = trim($data['device_id'] ?? '');

        if ($deviceId) {
            $kickStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM sessions WHERE user_id = ? AND device_id = ?");
            $kickStmt->bind_param("is", $user['id'], $deviceId);
            $kickStmt->execute();
            $activeSessions = (int)$kickStmt->get_result()->fetch_assoc()['cnt'];
            $kickStmt->close();

            $delSes = $conn->prepare("DELETE FROM sessions WHERE user_id = ? AND device_id = ?");
            $delSes->bind_param("is", $user['id'], $deviceId);
            $delSes->execute();
            $delSes->close();
        } else {
            // FIX AUTO-LOGOUT-01: When device_id is absent (page refresh, new tab, cleared
            // localStorage), only purge sessions that also have no device_id.
            // The old DELETE WHERE user_id=? killed ALL sessions for the user — including
            // active tabs that DID have a device_id — causing phantom logouts where the
            // student never clicked Logout but was suddenly redirected to the login page.
            $kickStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM sessions WHERE user_id = ? AND (device_id IS NULL OR device_id = '')");
            $kickStmt->bind_param("i", $user['id']);
            $kickStmt->execute();
            $activeSessions = (int)$kickStmt->get_result()->fetch_assoc()['cnt'];
            $kickStmt->close();

            $delSes = $conn->prepare("DELETE FROM sessions WHERE user_id = ? AND (device_id IS NULL OR device_id = '')");
            $delSes->bind_param("i", $user['id']);
            $delSes->execute();
            $delSes->close();
        }

        // Clear rate-limit log on success
        $clr = $conn->prepare("DELETE FROM login_attempts WHERE email = ?");
        $clr->bind_param("s", $email);
        $clr->execute();
        $clr->close();

        // Issue new session token
        $token     = bin2hex(random_bytes(32));
        $ttlHours  = (int) env('SESSION_TTL_HOURS', '8');
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$ttlHours} hours"));

        $insSes = $conn->prepare("INSERT INTO sessions (user_id, token, role, expires_at, device_id, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $insSes->bind_param("isssss", $user['id'], $token, $user['role'], $expiresAt, $deviceId, $ip);
        $insSes->execute();
        $insSes->close();

        respond([
            'success'          => true,
            'token'            => $token,
            'role'             => $user['role'],
            'session_replaced' => $activeSessions > 0,
            'user'             => [
                'id'         => (int)$user['id'],
                'email'      => $user['email'],
                'role'       => $user['role'],
                'first_name' => $profile['first_name'],
                'last_name'  => $profile['last_name'],
            ],
        ]);
    }
}

$conn->close();
respond(['success' => false, 'message' => 'Invalid email or password'], 401);