<?php
/**
 * Admin endpoint: send a push notification to a user.
 *
 * POST /api9/send_admin_notification.php
 * JSON body:
 *   userid  – int    – target user ID
 *   title   – string – notification title
 *   message – string – notification body
 *
 * Sends an FCM push notification to the user's registered device and
 * stores the notification in user_notifications for in-app display.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../Api2/common_fcm.php';
require_once __DIR__ . '/../shared/admin_auth.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

admin_auth_guard();

$input   = json_decode(file_get_contents('php://input'), true);
$userid  = isset($input['userid'])  ? (int)$input['userid']              : 0;
$title   = isset($input['title'])   ? trim((string)$input['title'])      : '';
$message = isset($input['message']) ? trim((string)$input['message'])    : '';

if ($userid <= 0 || $title === '' || $message === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'userid, title and message are required']);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Fetch user's FCM token
    $stmt = $pdo->prepare("SELECT id, fcm_token FROM users WHERE id = ? AND isDelete = 0");
    $stmt->execute([$userid]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    $fcmSent = false;
    $fcmError = null;

    // Send FCM push notification if user has a token
    if (!empty($user['fcm_token'])) {
        try {
            sendFCM(
                $user['fcm_token'],
                $title,
                $message,
                [
                    'type'    => 'admin_notification',
                    'userid'  => (string)$userid,
                    'title'   => $title,
                    'message' => $message,
                ],
                'general_notifications'
            );
            $fcmSent = true;
        } catch (Exception $e) {
            $fcmError = $e->getMessage();
            error_log('send_admin_notification.php FCM error: ' . $e->getMessage());
        }
    }

    // Always store notification in DB for in-app notification centre
    $ins = $pdo->prepare("
        INSERT INTO user_notifications (user_id, type, title, message, is_read)
        VALUES (?, 'admin_notification', ?, ?, 0)
    ");
    $ins->execute([$userid, $title, $message]);

    echo json_encode([
        'success'   => true,
        'message'   => 'Notification sent',
        'fcm_sent'  => $fcmSent,
    ]);

} catch (PDOException $e) {
    error_log('send_admin_notification.php DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
