<?php
require_once __DIR__ . '/../config/db.php';
header("Content-Type: application/json; charset=UTF-8");

function response($success, $message, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response(false, 'Invalid request method', [], 405);
}

$input = json_decode(file_get_contents("php://input"), true);

$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (!$email || !$password) {
    response(false, 'Email and password required', [], 422);
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->prepare("
        SELECT id, name, email, password, role, is_active
        FROM admins
        WHERE email = :email
        LIMIT 1
    ");
    $stmt->execute(['email' => $email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        response(false, 'Invalid credentials', [], 401);
    }

    if (!$admin['is_active']) {
        response(false, 'Admin account disabled', [], 403);
    }

    if (!password_verify($password, $admin['password'])) {
        response(false, 'Invalid credentials', [], 401);
    }

    // 🔐 Simple Token (JWT-like)
    $payload = [
        'admin_id' => $admin['id'],
        'email' => $admin['email'],
        'role' => $admin['role'],
        'iat' => time(),
        'exp' => time() + (60 * 60 * 24) // 24 hours
    ];

    $secret = 'CHANGE_THIS_SECRET_KEY';
    $token = base64_encode(json_encode($payload)) . '.' . hash_hmac('sha256', json_encode($payload), $secret);

    // Update last login
    $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?")
        ->execute([$admin['id']]);

    response(true, 'Login successful', [
        'token' => $token,
        'admin' => [
            'id' => $admin['id'],
            'name' => $admin['name'],
            'email' => $admin['email'],
            'role' => $admin['role']
        ]
    ]);

} catch (PDOException $e) {
    $sqlstate = $e->getCode();
    error_log('api9/login.php DB error [SQLSTATE ' . $sqlstate . ']: ' . $e->getMessage());
    if ($sqlstate === '42S02') {
        // Table not found – log a clear hint for the server admin but return a generic message.
        error_log('api9/login.php: admins table missing – run migrations/001_create_admins_table.sql on the ms database.');
    }
    response(false, 'Database error. Check server logs.', [], 500);
} catch (Exception $e) {
    error_log('api9/login.php Exception: ' . $e->getMessage());
    response(false, 'Server error', [], 500);
}
