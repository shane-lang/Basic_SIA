<?php
/**
 * auth_middleware.php — self-healing version
 * Creates the sessions table automatically if it doesn't exist.
 * Include at the top of every protected PHP file.
 */
function requireAuth(mysqli $conn, string $requiredRole = ''): array {

    // ── Auto-create sessions table if missing (safe to run every request) ──
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

    // ── Extract Bearer token from Authorization header ──
    $header = $_SERVER['HTTP_AUTHORIZATION']
           ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
           ?? (function_exists('apache_request_headers')
               ? (apache_request_headers()['Authorization']
               ?? apache_request_headers()['authorization'] ?? '') : '');

    $token = '';
    if (preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
        $token = $m[1];
    }
    // Fallback: allow ?token= query param
    if (!$token && !empty($_GET['token'])) {
        $token = trim($_GET['token']);
    }

    if (!$token) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Authentication required',
            'code'    => 'NO_TOKEN',
        ]);
        exit();
    }

    $stmt = $conn->prepare("
        SELECT s.user_id, s.role, s.expires_at,
               u.email, u.first_name, u.last_name
        FROM sessions s
        JOIN users u ON u.id = s.user_id
        WHERE s.token = ? LIMIT 1
    ");

    if (!$stmt) {
        // sessions table still doesn't exist somehow
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Session store unavailable', 'code' => 'DB_ERROR']);
        exit();
    }

    $stmt->bind_param("s", $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Session expired. Please log in again.',
            'code'    => 'SESSION_EXPIRED',
        ]);
        exit();
    }

    if (strtotime($row['expires_at']) < time()) {
        $del = $conn->prepare("DELETE FROM sessions WHERE token = ?");
        $del->bind_param("s", $token);
        $del->execute();
        $del->close();
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Session expired. Please log in again.',
            'code'    => 'SESSION_EXPIRED',
        ]);
        exit();
    }

    if ($requiredRole && $row['role'] !== $requiredRole) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied.', 'code' => 'FORBIDDEN']);
        exit();
    }

    return $row;
}
?>