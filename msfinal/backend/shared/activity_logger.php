<?php
/**
 * activity_logger.php
 * Shared helper for writing rows to the user_activities table.
 *
 * Usage (include this file, then call logActivity):
 *   require_once __DIR__ . '/../shared/activity_logger.php';
 *   logActivity($pdo, [
 *       'user_id'       => 42,
 *       'user_name'     => 'Ram Bahadur',
 *       'target_id'     => 7,
 *       'target_name'   => 'Sita Devi',
 *       'activity_type' => 'like_sent',
 *       'description'   => 'Ram Bahadur le Sita Devi lai like garyo',
 *   ]);
 *
 * Acceptable activity_type values:
 *   like_sent, like_removed, message_sent,
 *   request_sent, request_accepted, request_rejected,
 *   call_made, call_received,
 *   profile_viewed, login, logout,
 *   photo_uploaded, package_bought
 */

function logActivity($pdo, array $data): void {
    static $validTypes = [
        'like_sent', 'like_removed', 'message_sent',
        'request_sent', 'request_accepted', 'request_rejected',
        'call_made', 'call_received', 'profile_viewed',
        'login', 'logout', 'photo_uploaded', 'package_bought',
    ];

    $userId       = isset($data['user_id'])       ? (int)$data['user_id']           : 0;
    $userName     = isset($data['user_name'])      ? (string)$data['user_name']      : '';
    $targetId     = isset($data['target_id'])      ? (int)$data['target_id']         : null;
    $targetName   = isset($data['target_name'])    ? (string)$data['target_name']    : null;
    $activityType = isset($data['activity_type'])  ? (string)$data['activity_type']  : '';
    $description  = isset($data['description'])    ? (string)$data['description']    : '';

    if ($userId <= 0 || !in_array($activityType, $validTypes, true)) {
        return; // silently skip invalid calls
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_activities
                (user_id, user_name, target_id, target_name, activity_type, description)
            VALUES
                (:user_id, :user_name, :target_id, :target_name, :activity_type, :description)
        ");
        $stmt->execute([
            ':user_id'       => $userId,
            ':user_name'     => $userName,
            ':target_id'     => $targetId,
            ':target_name'   => $targetName,
            ':activity_type' => $activityType,
            ':description'   => $description,
        ]);
    } catch (Exception $e) {
        // Never let activity logging break the main request
        error_log('logActivity error: ' . $e->getMessage());
    }
}
