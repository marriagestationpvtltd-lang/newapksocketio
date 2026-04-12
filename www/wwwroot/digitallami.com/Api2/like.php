<?php
/**
 * like.php — Add or remove a like between two users.
 *
 * POST parameters:
 *   myid   : ID of the user performing the action
 *   userid : ID of the profile being liked/unliked
 *   like   : "1" to like, "0" to unlike
 */
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', '0');
error_reporting(E_ERROR);

require_once __DIR__ . '/../shared/activity_logger.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // Normalise parameters
    $sender_id   = isset($_REQUEST['myid'])   ? intval($_REQUEST['myid'])   : 0;
    $receiver_id = isset($_REQUEST['userid']) ? intval($_REQUEST['userid']) : 0;
    $likeValue   = $_REQUEST['like'] ?? null;
    $action      = ($likeValue === '1' || $likeValue === 1) ? 'add' : 'delete';

    if ($sender_id <= 0 || $receiver_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid myid or userid']);
        exit;
    }

    if ($action === 'add') {
        // Prevent duplicate likes
        $check = $pdo->prepare('SELECT id FROM likes WHERE sender_id = :s AND receiver_id = :r LIMIT 1');
        $check->execute([':s' => $sender_id, ':r' => $receiver_id]);
        if ($check->fetch()) {
            echo json_encode(['success' => true, 'message' => 'Already liked', 'like' => true]);
            exit;
        }

        $pdo->prepare('INSERT INTO likes (sender_id, receiver_id) VALUES (:s, :r)')
            ->execute([':s' => $sender_id, ':r' => $receiver_id]);

        $names = $pdo->prepare('SELECT id, CONCAT(firstName," ",lastName) AS name FROM users WHERE id IN (:s,:r)');
        $names->execute([':s' => $sender_id, ':r' => $receiver_id]);
        $nameMap = [];
        foreach ($names->fetchAll() as $row) { $nameMap[$row['id']] = $row['name']; }

        logActivity($pdo, [
            'user_id'       => $sender_id,
            'user_name'     => $nameMap[$sender_id]   ?? "User $sender_id",
            'target_id'     => $receiver_id,
            'target_name'   => $nameMap[$receiver_id] ?? "User $receiver_id",
            'activity_type' => 'like_sent',
            'description'   => ($nameMap[$sender_id] ?? "User $sender_id")
                               . ' le ' . ($nameMap[$receiver_id] ?? "User $receiver_id") . ' lai like garyo',
        ]);

        echo json_encode(['success' => true, 'message' => 'Liked successfully', 'like' => true]);
    } else {
        $pdo->prepare('DELETE FROM likes WHERE sender_id = :s AND receiver_id = :r')
            ->execute([':s' => $sender_id, ':r' => $receiver_id]);

        echo json_encode(['success' => true, 'message' => 'Like removed', 'like' => false]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
