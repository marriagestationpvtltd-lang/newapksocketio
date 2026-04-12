<?php
/**
 * Admin endpoint: per-user activity stats.
 *
 * GET /api9/get_user_activity.php?userid=<id>
 *
 * Returns:
 *   requests_sent       – proposals sent by this user
 *   requests_received   – proposals received by this user
 *   chat_requests_sent  – chat/photo/profile proposals sent
 *   chat_requests_accepted – proposals accepted by counterpart
 *   profile_views       – times this user's profile was viewed
 *   matches_count       – accepted proposals (mutual interest)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../shared/admin_auth.php';
header('Content-Type: application/json; charset=utf-8');

admin_auth_guard();

$userid = isset($_GET['userid']) ? (int)$_GET['userid'] : 0;
if ($userid <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid userid']);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Requests sent by user (all types)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM proposals WHERE sender_id = ?");
    $stmt->execute([$userid]);
    $requestsSent = (int)$stmt->fetchColumn();

    // Requests received by user (all types)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM proposals WHERE receiver_id = ?");
    $stmt->execute([$userid]);
    $requestsReceived = (int)$stmt->fetchColumn();

    // Chat requests sent (Chat or Photo type proposals)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM proposals
        WHERE sender_id = ? AND request_type IN ('Chat', 'Photo')
    ");
    $stmt->execute([$userid]);
    $chatRequestsSent = (int)$stmt->fetchColumn();

    // Chat requests accepted (where this user's proposals were accepted)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM proposals
        WHERE sender_id = ? AND status = 'accepted'
    ");
    $stmt->execute([$userid]);
    $chatRequestsAccepted = (int)$stmt->fetchColumn();

    // Profile views received
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM profile_view WHERE userid = ?");
    $stmt->execute([$userid]);
    $profileViews = (int)$stmt->fetchColumn();

    // Matches: proposals this user sent that were accepted
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM proposals
        WHERE sender_id = ? AND status = 'accepted'
    ");
    $stmt->execute([$userid]);
    $matchesCount = (int)$stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'data'    => [
            'requests_sent'          => $requestsSent,
            'requests_received'      => $requestsReceived,
            'chat_requests_sent'     => $chatRequestsSent,
            'chat_requests_accepted' => $chatRequestsAccepted,
            'profile_views'          => $profileViews,
            'matches_count'          => $matchesCount,
        ],
    ]);

} catch (PDOException $e) {
    error_log('get_user_activity.php DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
