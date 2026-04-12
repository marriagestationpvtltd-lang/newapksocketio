<?php
// Suppress PHP notices/warnings BEFORE any require so they can never
// corrupt the JSON response or cause output before headers are sent.
ini_set('display_errors', '0');
error_reporting(E_ERROR);

require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../shared/activity_logger.php';

// ==== CONFIG ====
$dbHost = DB_HOST;
$dbUser = DB_USER;
$dbPass = DB_PASS;
$dbName = DB_NAME;
function respond($code, $payload) {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'message' => 'Only POST allowed']);
}

// Get input
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$input = [];

if (stripos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (!is_array($json)) respond(400, ['success'=>false, 'message'=>'Invalid JSON']);
    $input['email'] = $json['email'] ?? null;
    $input['password'] = $json['password'] ?? null;
} else {
    $input['email'] = $_POST['email'] ?? null;
    $input['password'] = $_POST['password'] ?? null;
}

// Validation
if (empty($input['email']) || empty($input['password'])) {
    respond(400, ['success' => false, 'message' => 'Email and password are required']);
}

if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    respond(400, ['success' => false, 'message' => 'Invalid email format']);
}

// DB connection
$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($mysqli->connect_errno) {
    respond(500, ['success'=>false, 'message'=>'DB connection failed: '.$mysqli->connect_error]);
}
$mysqli->set_charset('utf8mb4');

try {
    // 1) Fetch user by email
    $stmt = $mysqli->prepare("
        SELECT u.id, u.firstName, u.lastName, u.email, u.password, u.contactNo, 
               u.gender, u.languages, u.nationality, u.profile_picture,
               u.isDelete, u.isDisable, u.createdDate,
               up.birthDate, up.profileForId
        FROM users u
        LEFT JOIN userpersonaldetail up ON up.userid = u.id
        WHERE u.email = ? LIMIT 1
    ");
    
    if (!$stmt) throw new Exception("Prepare failed: ".$mysqli->error);
    
    $stmt->bind_param('s', $input['email']);
    $stmt->execute();
    $stmt->bind_result(
        $col_id, $col_firstName, $col_lastName, $col_email, $col_password,
        $col_contactNo, $col_gender, $col_languages, $col_nationality,
        $col_profile_picture, $col_isDelete, $col_isDisable, $col_createdDate,
        $col_birthDate, $col_profileForId
    );

    if (!$stmt->fetch()) {
        $stmt->close();
        respond(401, ['success' => false, 'message' => 'Invalid email or password']);
    }

    $user = [
        'id'              => $col_id,
        'firstName'       => $col_firstName,
        'lastName'        => $col_lastName,
        'email'           => $col_email,
        'password'        => $col_password,
        'contactNo'       => $col_contactNo,
        'gender'          => $col_gender,
        'languages'       => $col_languages,
        'nationality'     => $col_nationality,
        'profile_picture' => $col_profile_picture,
        'isDelete'        => $col_isDelete,
        'isDisable'       => $col_isDisable,
        'createdDate'     => $col_createdDate,
        'birthDate'       => $col_birthDate,
        'profileForId'    => $col_profileForId,
    ];
    $stmt->close();

    // 2) Check account status before verifying password
    if (!empty($user['isDelete'])) {
        respond(401, ['success' => false, 'message' => 'This account no longer exists. Please contact support.']);
    }
    if (!empty($user['isDisable'])) {
        respond(403, ['success' => false, 'message' => 'Your account has been disabled. Please contact support.']);
    }
    
    // 3) Verify password
    if (empty($user['password'])) {
        // Password field missing or empty — data integrity issue
        respond(500, ['success' => false, 'message' => 'Account data is incomplete. Please contact support.']);
    }
    if (!password_verify($input['password'], $user['password'])) {
        respond(401, ['success' => false, 'message' => 'Invalid email or password']);
    }
    
    // Remove internal fields from response
    unset($user['password'], $user['isDelete'], $user['isDisable']);
    
    // 3) Generate new token
    $token = bin2hex(random_bytes(30));
    $createdAt = date('Y-m-d H:i:s');
    
    // Ensure tokens table exists WITHOUT expires_at column
    $createTokensSql = "
        CREATE TABLE IF NOT EXISTS user_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            userid INT NOT NULL,
            token VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX (userid),
            INDEX (token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    if (!$mysqli->query($createTokensSql)) {
        throw new Exception("Failed ensuring tokens table: " . $mysqli->error);
    }
    
    // Check if expires_at column exists, if not, add it
    $checkColumn = $mysqli->query("SHOW COLUMNS FROM user_tokens LIKE 'expires_at'");
    if ($checkColumn !== false) {
        if ($checkColumn->num_rows === 0) {
            // Add expires_at column
            $mysqli->query("ALTER TABLE user_tokens ADD COLUMN expires_at DATETIME NULL");
        }
        $checkColumn->free();
    }
    
    // Delete old tokens (older than 30 days)
    $deleteOld = $mysqli->prepare("
        DELETE FROM user_tokens 
        WHERE userid = ? AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    if ($deleteOld) {
        $deleteOld->bind_param('i', $user['id']);
        $deleteOld->execute();
        $deleteOld->close();
    }
    
    // Insert new token with expires_at
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
    $stmt = $mysqli->prepare("
        INSERT INTO user_tokens (userid, token, created_at, expires_at) 
        VALUES (?, ?, ?, ?)
    ");
    if (!$stmt) {
        // If still getting error, try without expires_at
        $stmt = $mysqli->prepare("
            INSERT INTO user_tokens (userid, token, created_at) 
            VALUES (?, ?, ?)
        ");
        if (!$stmt) throw new Exception("Prepare failed (token insert): " . $mysqli->error);
        $stmt->bind_param('iss', $user['id'], $token, $createdAt);
        $expiresAt = null;
    } else {
        $stmt->bind_param('isss', $user['id'], $token, $createdAt, $expiresAt);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed (token insert): " . $stmt->error);
    }
    $stmt->close();
    
    // 4) Update last login time (add column if needed)
    $checkLastLogin = $mysqli->query("SHOW COLUMNS FROM users LIKE 'last_login'");
    if ($checkLastLogin !== false) {
        if ($checkLastLogin->num_rows === 0) {
            $mysqli->query("ALTER TABLE users ADD COLUMN last_login DATETIME NULL");
        }
        $checkLastLogin->free();
    }
    
    $updateLogin = $mysqli->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    if ($updateLogin) {
        $updateLogin->bind_param('i', $user['id']);
        $updateLogin->execute();
        $updateLogin->close();
    }
    
    // 5) Prepare response
    $responseData = [
        'success' => true,
        'message' => 'Login successful',
        'data' => $user,
        'bearer_token' => $token,
    ];
    
    if ($expiresAt) {
        $responseData['token_expires'] = $expiresAt;
    }

    // 6) Log login activity
    try {
        $actPdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $fullName = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
        if (!$fullName) $fullName = "User " . $user['id'];
        logActivity($actPdo, [
            'user_id'       => $user['id'],
            'user_name'     => $fullName,
            'activity_type' => 'login',
            'description'   => "$fullName le login garyo",
        ]);
    } catch (Exception $e) {
        // Never let activity logging break the login response
    }
    
    respond(200, $responseData);
    
} catch (\Throwable $e) {
    respond(500, ['success' => false, 'message' => 'Login failed: ' . $e->getMessage()]);
}
?>