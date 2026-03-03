<?php
error_reporting(0);
ini_set('display_errors', 0);

// ── CORS: restrict to known frontend origins (FIX A-02) ───────────────────
$allowedOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$trustedOrigins = [
    'http://localhost:4200',
    'http://localhost',
    'http://127.0.0.1:4200',
    'http://127.0.0.1',
];
if (in_array($allowedOrigin, $trustedOrigins, true)) {
    header("Access-Control-Allow-Origin: $allowedOrigin");
} else {
    header('Access-Control-Allow-Origin: http://localhost:4200');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

$conn = new mysqli('localhost', 'root', '', 'sia_db');
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}
$conn->set_charset("utf8mb4");

// ── Ensure sessions table exists (FIX A-04) ───────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS sessions (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT NOT NULL,
        token      VARCHAR(64) NOT NULL UNIQUE,
        role       VARCHAR(30) NOT NULL DEFAULT 'student',
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token),
        INDEX idx_user  (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── Ensure login_attempts table exists (FIX A-03) ─────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS login_attempts (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        email        VARCHAR(150) NOT NULL,
        ip           VARCHAR(45)  NOT NULL DEFAULT '',
        attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email_time (email, attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$action = $_GET['action'] ?? '';

function respond(array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload);
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// REGISTER
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'register') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) respond(['success' => false, 'message' => 'Invalid JSON'], 400);

    $email     = trim($data['email']      ?? '');
    $password  = trim($data['password']   ?? '');
    $firstName = trim($data['first_name'] ?? '');
    $lastName  = trim($data['last_name']  ?? '');
    $role      = 'student';

    // Validate: email/username and password required; min 6 chars password
    if (!$email || !$password) {
        respond(['success' => false, 'message' => 'Email and password are required'], 400);
    }
    if (strlen($password) < 6) {
        respond(['success' => false, 'message' => 'Password must be at least 6 characters'], 400);
    }

    // FIX BUG #1: If email exists, return existing user_id (idempotent)
    $check = $conn->prepare("SELECT id, role FROM users WHERE email = ? LIMIT 1");
    $check->bind_param("s", $email);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    $check->close();

    if ($existing) {
        respond([
            'success'        => true,
            'message'        => 'Account already exists. Continuing enrollment.',
            'user_id'        => (int)$existing['id'],
            'email'          => $email,
            'role'           => $existing['role'],
            'already_existed'=> true,
        ]);
    }

    // FIX A-01: Hash password before storing
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    $stmt = $conn->prepare(
        "INSERT INTO users (email, password, role, first_name, last_name) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("sssss", $email, $hashedPassword, $role, $firstName, $lastName);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        respond([
            'success'        => true,
            'message'        => 'Account created successfully',
            'user_id'        => (int)$stmt->insert_id,
            'email'          => $email,
            'role'           => $role,
            'already_existed'=> false,
        ]);
    }
    respond(['success' => false, 'message' => 'Failed to create account. Please try again.'], 500);
}

// ─────────────────────────────────────────────────────────────────────────────
// LOGIN
// ─────────────────────────────────────────────────────────────────────────────
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['email']) || !isset($data['password'])) {
    respond(['success' => false, 'message' => 'Email and password are required'], 400);
}

$email    = trim($data['email']);
$password = trim($data['password']);
$ip       = $_SERVER['REMOTE_ADDR'] ?? '';

// FIX A-03: Rate limit — max 10 attempts per email per 15 minutes
$window    = date('Y-m-d H:i:s', strtotime('-15 minutes'));
$cntStmt   = $conn->prepare("SELECT COUNT(*) AS cnt FROM login_attempts WHERE email = ? AND attempted_at > ?");
$cntStmt->bind_param("ss", $email, $window);
$cntStmt->execute();
$attempts  = (int)$cntStmt->get_result()->fetch_assoc()['cnt'];
$cntStmt->close();

if ($attempts >= 10) {
    respond([
        'success' => false,
        'message' => 'Too many login attempts. Please try again in 15 minutes.',
    ], 429);
}

// Record this attempt
$logStmt = $conn->prepare("INSERT INTO login_attempts (email, ip) VALUES (?, ?)");
$logStmt->bind_param("ss", $email, $ip);
$logStmt->execute();
$logStmt->close();

// FIX A-01: Fetch user and verify with password_hash
$loginStmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$loginStmt->bind_param("s", $email);
$loginStmt->execute();
$result = $loginStmt->get_result();

if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();

    // Support both bcrypt and legacy plain-text (migrate on first login)
    $passwordValid = false;
    if (password_verify($password, $user['password'])) {
        $passwordValid = true;
    } elseif ($password === $user['password']) {
        // Legacy plain-text — upgrade to bcrypt now
        $passwordValid = true;
        $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $migStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $migStmt->bind_param("si", $newHash, $user['id']);
        $migStmt->execute();
        $migStmt->close();
    }

    if ($passwordValid) {
        // FIX A-04: Generate and STORE the session token server-side
        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+8 hours'));

        // Invalidate old sessions for this user
        $delStmt = $conn->prepare("DELETE FROM sessions WHERE user_id = ?");
        $delStmt->bind_param("i", $user['id']);
        $delStmt->execute();
        $delStmt->close();

        $sesStmt = $conn->prepare(
            "INSERT INTO sessions (user_id, token, role, expires_at) VALUES (?, ?, ?, ?)"
        );
        $sesStmt->bind_param("isss", $user['id'], $token, $user['role'], $expiresAt);
        $sesStmt->execute();
        $sesStmt->close();

        // Clear failed attempts on successful login
        $clearStmt = $conn->prepare("DELETE FROM login_attempts WHERE email = ?");
        $clearStmt->bind_param("s", $email);
        $clearStmt->execute();
        $clearStmt->close();

        respond([
            'success' => true,
            'token'   => $token,
            'role'    => $user['role'],
            'user'    => [
                'id'         => (int)$user['id'],   // FIX BUG #2: cast to int
                'email'      => $user['email'],
                'role'       => $user['role'],
                'first_name' => $user['first_name'],
                'last_name'  => $user['last_name'],
            ],
        ]);
    }
}

$conn->close();
respond(['success' => false, 'message' => 'Invalid email or password'], 401);
?>