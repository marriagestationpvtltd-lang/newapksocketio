<?php
require_once __DIR__ . '/../config/db.php';
header("Content-Type: application/json; charset=utf-8");

// ================= DB CONFIG =================
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // ================= AUTO-CREATE TABLE (idempotent) =================
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `user_activities` (
          `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `user_id`       INT          NOT NULL,
          `user_name`     VARCHAR(200) DEFAULT '',
          `target_id`     INT          DEFAULT NULL,
          `target_name`   VARCHAR(200) DEFAULT NULL,
          `activity_type` ENUM(
            'like_sent','like_removed','message_sent',
            'request_sent','request_accepted','request_rejected',
            'call_made','call_received','profile_viewed',
            'login','logout','photo_uploaded','package_bought'
          ) NOT NULL,
          `description`   TEXT,
          `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
          INDEX `idx_ua_user_id`       (`user_id`),
          INDEX `idx_ua_created_at`    (`created_at`),
          INDEX `idx_ua_activity_type` (`activity_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // ================= QUERY PARAMS =================
    $page     = max(1, (int)($_GET['page']     ?? 1));
    $limit    = min(100, max(1, (int)($_GET['limit'] ?? 50)));
    $offset   = ($page - 1) * $limit;

    $userId       = isset($_GET['user_id'])       ? (int)$_GET['user_id']              : null;
    $activityType = isset($_GET['activity_type'])  ? trim($_GET['activity_type'])        : null;
    $dateFrom     = isset($_GET['date_from'])      ? trim($_GET['date_from'])            : null;
    $dateTo       = isset($_GET['date_to'])        ? trim($_GET['date_to'])              : null;
    $search       = isset($_GET['search'])         ? trim($_GET['search'])               : null;

    $validTypes = [
        'like_sent','like_removed','message_sent',
        'request_sent','request_accepted','request_rejected',
        'call_made','call_received','profile_viewed',
        'login','logout','photo_uploaded','package_bought',
    ];

    // ================= BUILD WHERE CLAUSE =================
    $where  = [];
    $params = [];

    if ($userId) {
        $where[]  = 'user_id = :user_id';
        $params[':user_id'] = $userId;
    }

    if ($activityType && in_array($activityType, $validTypes, true)) {
        $where[]  = 'activity_type = :activity_type';
        $params[':activity_type'] = $activityType;
    }

    if ($dateFrom) {
        $where[]  = 'created_at >= :date_from';
        $params[':date_from'] = $dateFrom . ' 00:00:00';
    }

    if ($dateTo) {
        $where[]  = 'created_at <= :date_to';
        $params[':date_to'] = $dateTo . ' 23:59:59';
    }

    if ($search) {
        $where[]  = '(user_name LIKE :search OR target_name LIKE :search2 OR description LIKE :search3)';
        $params[':search']  = '%' . $search . '%';
        $params[':search2'] = '%' . $search . '%';
        $params[':search3'] = '%' . $search . '%';
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    // ================= TOTAL COUNT =================
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM user_activities $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // ================= FETCH ACTIVITIES =================
    $dataStmt = $pdo->prepare("
        SELECT id, user_id, user_name, target_id, target_name,
               activity_type, description, created_at
        FROM user_activities
        $whereSql
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $k => $v) {
        $dataStmt->bindValue($k, $v);
    }
    $dataStmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dataStmt->execute();
    $rows = $dataStmt->fetchAll();

    $activities = array_map(function ($row) {
        return [
            'id'            => (int)$row['id'],
            'user_id'       => (int)$row['user_id'],
            'user_name'     => $row['user_name'],
            'target_id'     => $row['target_id'] !== null ? (int)$row['target_id'] : null,
            'target_name'   => $row['target_name'],
            'activity_type' => $row['activity_type'],
            'description'   => $row['description'],
            'created_at'    => $row['created_at'],
        ];
    }, $rows);

    echo json_encode([
        'success'      => true,
        'activities'   => $activities,
        'total'        => $total,
        'page'         => $page,
        'limit'        => $limit,
        'total_pages'  => (int)ceil($total / $limit),
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
