<?php
// ─────────────────────────────────────────────────────────────────────────────
// SHARED AUDIT LOG HELPER
// Include this in any PHP file that needs to write to audit_logs.
// Usage: logAuditShared($conn, $authUser, 'ACTION', 'target_type', $id, 'description', $old, $new);
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('logAuditShared')) {
    function logAuditShared(mysqli $conn, $authUser, string $action, string $targetType = '', int $targetId = 0, string $description = '', $oldValues = null, $newValues = null): void {
        $userId    = $authUser ? (int)($authUser['user_id'] ?? $authUser['id'] ?? 0) : null;
        $userEmail = $authUser ? ($authUser['email'] ?? '') : 'system';
        $userRole  = $authUser ? ($authUser['role']  ?? '') : '';
        $ip        = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua        = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $oldJson   = $oldValues !== null ? json_encode($oldValues) : null;
        $newJson   = $newValues !== null ? json_encode($newValues) : null;

        // Ensure audit_logs table exists (in case this file is included standalone)
        $conn->query("CREATE TABLE IF NOT EXISTS audit_logs (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            user_id     INT,
            user_email  VARCHAR(255),
            user_role   VARCHAR(50),
            action      VARCHAR(100) NOT NULL,
            target_type VARCHAR(50),
            target_id   INT DEFAULT 0,
            description TEXT,
            old_values  LONGTEXT,
            new_values  LONGTEXT,
            ip_address  VARCHAR(45),
            user_agent  VARCHAR(255),
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_created  (created_at),
            KEY idx_role     (user_role),
            KEY idx_action   (action)
        )");

        $st = $conn->prepare("INSERT INTO audit_logs (user_id,user_email,user_role,action,target_type,target_id,description,old_values,new_values,ip_address,user_agent) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        if ($st) {
            $st->bind_param("issssisssss", $userId, $userEmail, $userRole, $action, $targetType, $targetId, $description, $oldJson, $newJson, $ip, $ua);
            $st->execute();
            $st->close();
        }
    }
}