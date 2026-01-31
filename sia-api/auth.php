<?php
// filepath: C:\xampp\htdocs\sia-api\auth.php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

$conn = new mysqli('localhost', 'root', '', 'sia_db');
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]));
}
$conn->set_charset("utf8mb4");

$action = $_GET['action'] ?? '';

// ── REGISTER (new student account creation) ──────────────────
if ($action === 'register') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) { echo json_encode(['success' => false, 'message' => 'Invalid JSON']); exit(); }

    $email      = trim($data['email']      ?? '');
    $password   = trim($data['password']   ?? '');
    $role       = 'student'; // always student from enrollment modal
    $firstName  = trim($data['first_name'] ?? '');
    $lastName   = trim($data['last_name']  ?? '');

    if (!$email || !$password) {
        echo json_encode(['success' => false, 'message' => 'Email and password are required']); exit();
    }

    // Check if email already exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already registered. Please login instead.']); exit();
    }
    $check->close();

    // Insert new user (plain password — same pattern as existing auth.php)
    $stmt = $conn->prepare("INSERT INTO users (email, password, role, first_name, last_name) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $email, $password, $role, $firstName, $lastName);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $userId = $stmt->insert_id;
        echo json_encode([
            'success'  => true,
            'message'  => 'Account created successfully',
            'user_id'  => $userId,
            'email'    => $email,
            'role'     => $role
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create account. Please try again.']);
    }
    $conn->close();
    exit();
}

// ── LOGIN ─────────────────────────────────────────────────────
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['email']) || !isset($data['password'])) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit();
}

$email    = $conn->real_escape_string($data['email']);
$password = $conn->real_escape_string($data['password']);

$result = $conn->query("SELECT * FROM users WHERE email='$email' AND password='$password'");

if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'token'   => bin2hex(random_bytes(16)),
        'role'    => $user['role'],
        'user'    => [
            'id'         => $user['id'],
            'email'      => $user['email'],
            'role'       => $user['role'],
            'first_name' => $user['first_name'],
            'last_name'  => $user['last_name']
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
}

$conn->close();
?>