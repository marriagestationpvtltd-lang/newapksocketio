<?php
// google_signin.php — Google / Firebase sign-in for Flutter Web
// Flutter sends: email, google_id (Firebase UID), firebase_token, displayName
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../shared/activity_logger.php';

$dbHost = DB_HOST;
$dbUser = DB_USER;
$dbPass = DB_PASS;
$dbName = DB_NAME;

function respond($code, $payload) {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    respond(204, []);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'message' => 'Only POST allowed']);
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') === false) {
    respond(400, ['success' => false, 'message' => 'JSON content required']);
}

$raw  = file_get_contents('php://input');
$json = json_decode($raw, true);
if (!is_array($json)) {
    respond(400, ['success' => false, 'message' => 'Invalid JSON']);
}

$email        = trim($json['email']        ?? '');
$googleId     = trim($json['google_id']    ?? '');
$displayName  = trim($json['displayName']  ?? '');

if (empty($email)) {
    respond(400, ['success' => false, 'message' => 'Email is required']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(400, ['success' => false, 'message' => 'Invalid email format']);
}

$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($mysqli->connect_errno) {
    respond(500, ['success' => false, 'message' => 'DB connection failed: ' . $mysqli->connect_error]);
}
$mysqli->set_charset('utf8mb4');

try {
    // 1) Look up existing user by email
    $stmt = $mysqli->prepare("
        SELECT u.id, u.firstName, u.lastName, u.email, u.profile_picture,
               up.birthDate, up.profileForId
        FROM users u
        LEFT JOIN userpersonaldetail up ON up.userid = u.id
        WHERE u.email = ? LIMIT 1
    ");
    if (!$stmt) throw new Exception("Prepare failed: " . $mysqli->error);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Existing user — update google_id if needed
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!empty($googleId)) {
            $upd = $mysqli->prepare("UPDATE users SET google_id = ? WHERE id = ? AND (google_id IS NULL OR google_id = '')");
            if ($upd) {
                $upd->bind_param('si', $googleId, $user['id']);
                $upd->execute();
                $upd->close();
            }
        }
    } else {
        // New user — register automatically
        $stmt->close();

        $nameParts = explode(' ', $displayName, 2);
        $firstName = ($nameParts[0] ?? '') ?: 'User';
        $lastName  = $nameParts[1] ?? '';

        $randomPw     = bin2hex(random_bytes(16));
        $hashedPw     = password_hash($randomPw, PASSWORD_DEFAULT);

        $ins = $mysqli->prepare("
            INSERT INTO users (firstName, lastName, email, password, google_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        if (!$ins) throw new Exception("Prepare failed (insert user): " . $mysqli->error);
        $ins->bind_param('sssss', $firstName, $lastName, $email, $hashedPw, $googleId);
        if (!$ins->execute()) throw new Exception("Execute failed (insert user): " . $ins->error);
        $newId = $ins->insert_id;
        $ins->close();

        // Create personal detail row
        $pd = $mysqli->prepare("INSERT INTO userpersonaldetail (userid) VALUES (?)");
        if ($pd) { $pd->bind_param('i', $newId); $pd->execute(); $pd->close(); }

        // Fetch the new user
        $stmt = $mysqli->prepare("
            SELECT u.id, u.firstName, u.lastName, u.email, u.profile_picture,
                   up.birthDate, up.profileForId
            FROM users u
            LEFT JOIN userpersonaldetail up ON up.userid = u.id
            WHERE u.id = ? LIMIT 1
        ");
        if (!$stmt) throw new Exception("Prepare failed (fetch new user): " . $mysqli->error);
        $stmt->bind_param('i', $newId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();
    }

    // 2) Generate token
    $token     = bin2hex(random_bytes(30));
    $createdAt = date('Y-m-d H:i:s');
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

    // Ensure user_tokens table
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS user_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            userid INT NOT NULL,
            token VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NULL,
            INDEX (userid),
            INDEX (token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Add expires_at column if missing
    $chk = $mysqli->query("SHOW COLUMNS FROM user_tokens LIKE 'expires_at'");
    if ($chk && $chk->num_rows === 0) {
        $mysqli->query("ALTER TABLE user_tokens ADD COLUMN expires_at DATETIME NULL");
    }

    // Delete old tokens (>30 days)
    $del = $mysqli->prepare("DELETE FROM user_tokens WHERE userid = ? AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    if ($del) { $del->bind_param('i', $user['id']); $del->execute(); $del->close(); }

    // Insert new token
    $ins = $mysqli->prepare("INSERT INTO user_tokens (userid, token, created_at, expires_at) VALUES (?, ?, ?, ?)");
    if ($ins) {
        $ins->bind_param('isss', $user['id'], $token, $createdAt, $expiresAt);
        if (!$ins->execute()) throw new Exception("Token insert failed: " . $ins->error);
        $ins->close();
    } else {
        $ins2 = $mysqli->prepare("INSERT INTO user_tokens (userid, token, created_at) VALUES (?, ?, ?)");
        if (!$ins2) throw new Exception("Prepare failed (token fallback): " . $mysqli->error);
        $ins2->bind_param('iss', $user['id'], $token, $createdAt);
        $ins2->execute();
        $ins2->close();
        $expiresAt = null;
    }

    // 3) Update last_login
    $chkCol = $mysqli->query("SHOW COLUMNS FROM users LIKE 'last_login'");
    if ($chkCol && $chkCol->num_rows === 0) {
        $mysqli->query("ALTER TABLE users ADD COLUMN last_login DATETIME NULL");
    }
    $upd = $mysqli->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    if ($upd) { $upd->bind_param('i', $user['id']); $upd->execute(); $upd->close(); }

    // 4) Log activity
    try {
        $actPdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $fullName = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
        if (!$fullName) $fullName = "User " . $user['id'];
        logActivity($actPdo, [
            'user_id'       => $user['id'],
            'user_name'     => $fullName,
            'activity_type' => 'login',
            'description'   => "$fullName le Google login garyo",
        ]);
    } catch (Exception $e) { /* never break login */ }

    $responseData = [
        'success'      => true,
        'message'      => 'Google login successful',
        'data'         => $user,
        'bearer_token' => $token,
    ];
    if ($expiresAt) $responseData['token_expires'] = $expiresAt;

    respond(200, $responseData);

} catch (Exception $e) {
    respond(500, ['success' => false, 'message' => 'Google sign-in failed: ' . $e->getMessage()]);
}
?>
