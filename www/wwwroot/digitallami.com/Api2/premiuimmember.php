<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    // === CONFIG ===
    $dbHost = DB_HOST;
    $dbName = DB_NAME;
    $dbUser = DB_USER;
    $dbPass = DB_PASS;

    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // INPUT CHECK
    if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
        echo json_encode(["success" => false, "message" => "user_id is required and must be numeric"]);
        exit;
    }
    $userId = (int) $_GET['user_id'];

    // GET REQUESTING USER
    $stmt = $pdo->prepare("SELECT id, gender FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$me) {
        echo json_encode(["success" => false, "message" => "User not found"]);
        exit;
    }

    // Normalize gender
    $rawGender = $me['gender'];
    $norm = strtolower(trim($rawGender));
    $opposite = ($norm === 'male') ? 'female' : 'male';

    // FETCH OPPOSITE GENDER + PAID + INCLUDE isVerified + AGE + CITY + PRIVACY
    $sql = "
        SELECT
            u.id,
            u.firstName,
            u.lastName,
            u.email,
            u.gender,
            u.usertype,
            u.isVerified,
            u.profile_picture,
            u.privacy,
            ud.birthDate,
            TIMESTAMPDIFF(YEAR, ud.birthDate, CURDATE()) AS age,
            pa.city
        FROM users u
        LEFT JOIN userpersonaldetail ud ON ud.userId = u.id
        LEFT JOIN (SELECT userid, city FROM permanent_address GROUP BY userid) pa ON pa.userid = u.id
        WHERE TRIM(LOWER(u.gender)) = :opp_gender
          AND TRIM(LOWER(u.usertype)) = 'paid'
          AND u.id != :me
          AND u.isActive = 1 AND u.isDelete = 0
        ORDER BY u.id DESC
    ";

    $stmt2 = $pdo->prepare($sql);
    $stmt2->execute([
        ':opp_gender' => $opposite,
        ':me' => $userId
    ]);

    $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($rows)) {
        // Pre-fetch photo requests (avoid N+1)
        $rowIds    = array_column($rows, 'id');
        $rowIdsStr = implode(',', array_map('intval', $rowIds));
        $stmtBatch = $pdo->prepare("
            SELECT sender_id, receiver_id, status
            FROM proposals
            WHERE request_type = 'Photo'
              AND ((sender_id = ? AND receiver_id IN ($rowIdsStr))
                   OR (sender_id IN ($rowIdsStr) AND receiver_id = ?))
            ORDER BY id DESC
        ");
        $stmtBatch->execute([$userId, $userId]);
        $photoMap = [];
        foreach ($stmtBatch->fetchAll(PDO::FETCH_ASSOC) as $pr) {
            $otherId = ($pr['sender_id'] == $userId) ? (int)$pr['receiver_id'] : (int)$pr['sender_id'];
            if (!isset($photoMap[$otherId])) {
                $photoMap[$otherId] = ($pr['status'] === 'accepted') ? 'accepted' : 'pending';
            }
        }
    } else {
        $photoMap = [];
    }

    foreach ($rows as &$row) {
        $row['photo_request'] = $photoMap[(int)$row['id']] ?? "not sent";
    }

    echo json_encode([
        "success" => true,
        "message" => "fetched successfully",
        "data" => $rows
    ]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Server error: " . $e->getMessage()]);
}
