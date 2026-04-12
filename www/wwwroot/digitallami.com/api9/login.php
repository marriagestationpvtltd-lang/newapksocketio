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

/**
 * Create the admins table and seed the default admin account if it does not
 * exist yet.  Called automatically when SQLSTATE 42S02 (table not found) is
 * caught during login so no manual server-side setup step is needed.
 */
function ensureAdminsTable(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `admins` (
          `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `name`       VARCHAR(100) NOT NULL,
          `email`      VARCHAR(150) NOT NULL,
          `password`   VARCHAR(255) NOT NULL,
          `role`       ENUM('super_admin','admin') DEFAULT 'admin',
          `is_active`  TINYINT(1)  DEFAULT 1,
          `last_login` DATETIME    DEFAULT NULL,
          `created_at` TIMESTAMP   NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP   NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Seed the default super-admin account.
    // Password hash is for 'Admin@123' (bcrypt, cost 10).
    $pdo->exec("
        INSERT IGNORE INTO `admins`
            (`id`, `name`, `email`, `password`, `role`, `is_active`)
        VALUES
            (1, 'System Admin', 'admin@ms.com',
             '\$2y\$10\$gFzOiJlud/nhFIKO97hU9Off.Bx8Jg7u8W4CIXypZW3WyyDerF40K',
             'super_admin', 1)
    ");
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $tableCreated = false;
    _login:
    try {
        $stmt = $pdo->prepare("
            SELECT id, name, email, password, role, is_active
            FROM admins
            WHERE email = :email
            LIMIT 1
        ");
        $stmt->execute(['email' => $email]);
    } catch (PDOException $inner) {
        // SQLSTATE 42S02 = table/view not found.  Create the table on first
        // request and retry once so the login works immediately without any
        // manual server-side setup step.
        if ($inner->getCode() === '42S02' && !$tableCreated) {
            error_log('api9/login.php: admins table missing – auto-creating now.');
            ensureAdminsTable($pdo);
            $tableCreated = true;
            goto _login;
        }
        throw $inner;
    }

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
    error_log('api9/login.php DB error [SQLSTATE ' . $e->getCode() . ']: ' . $e->getMessage());
    response(false, 'Database error. Check server logs.', [], 500);
} catch (Exception $e) {
    error_log('api9/login.php Exception: ' . $e->getMessage());
    response(false, 'Server error', [], 500);
}
